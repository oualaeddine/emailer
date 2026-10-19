<?php

namespace App\Modules\Campaigns\Services\Checklist;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Recipients\Services\SegmentEvaluationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.4.5 — pre-send checklist. Reads WP-42
 * columns/tables defensively so it works before and after WP-42 merges.
 */
class CampaignChecklistService implements CampaignChecklistContract
{
    public const EMAIL_SIZE_LIMIT = 102400;

    public function __construct(
        private readonly LinkChecker $links,
        private readonly SegmentEvaluationService $segments,
    ) {
    }

    public function evaluate(Campaign $campaign): CampaignChecklist
    {
        $html = (string) $campaign->html_body;

        return new CampaignChecklist($campaign->uuid, [
            $this->senderVerified($campaign),
            $this->recipientsSelected($campaign),
            $this->subjectFilled($campaign),
            $this->designReady($html),
            $this->unsubscribeLink($html),
            $this->testSent($campaign),
            $this->linksValid($campaign),
            $this->emailSize($html),
            $this->spamScore($campaign),
        ], Carbon::now());
    }

    public function blockingFailures(Campaign $campaign): array
    {
        return $this->evaluate($campaign)->blockingFailures();
    }

    private function senderVerified(Campaign $campaign): ChecklistItem
    {
        $verified = false;
        $senderId = $campaign->getAttribute('sender_id');

        if ($senderId !== null && Schema::hasTable('senders') && Schema::hasColumn('senders', 'verified_at')) {
            $verified = DB::table('senders')->where('id', $senderId)->whereNotNull('verified_at')->exists();
        }

        return $verified
            ? new ChecklistItem('sender_verified', ChecklistItem::OK, true, 'Expéditeur vérifié')
            : new ChecklistItem('sender_verified', ChecklistItem::ERROR, true, $senderId === null
                ? 'Aucun expéditeur sélectionné'
                : "L'expéditeur n'est pas vérifié");
    }

    private function recipientsSelected(Campaign $campaign): ChecklistItem
    {
        $count = $this->audienceCount($campaign);

        return $count > 0
            ? new ChecklistItem('recipients_selected', ChecklistItem::OK, true, "{$count} destinataire(s)", ['count' => $count])
            : new ChecklistItem('recipients_selected', ChecklistItem::ERROR, true, 'Aucun destinataire', ['count' => 0]);
    }

    private function audienceCount(Campaign $campaign): int
    {
        if (Schema::hasTable('campaign_audiences')) {
            $rows = DB::table('campaign_audiences')->where('campaign_id', $campaign->id)->get();
            if ($rows->isNotEmpty()) {
                $include = [];
                $exclude = [];
                foreach ($rows as $row) {
                    $ids = $this->targetRecipientIds($row->target_type, (int) $row->target_id);
                    if ($row->mode === 'exclude') {
                        $exclude += array_flip($ids);
                    } else {
                        $include += array_flip($ids);
                    }
                }

                return count(array_diff_key($include, $exclude));
            }
        }

        $ids = [];
        if ($campaign->recipient_list_id !== null) {
            $ids += array_flip($this->targetRecipientIds('list', (int) $campaign->recipient_list_id));
        }
        if ($campaign->segment_id !== null) {
            $ids += array_flip($this->targetRecipientIds('segment', (int) $campaign->segment_id));
        }
        if ($ids === [] && Schema::hasTable('campaign_recipients')) {
            return DB::table('campaign_recipients')->where('campaign_id', $campaign->id)->count();
        }

        return count($ids);
    }

    /**
     * @return list<int>
     */
    private function targetRecipientIds(string $type, int $id): array
    {
        if ($type === 'segment') {
            $segment = Segment::query()->find($id);

            return $segment ? $this->segments->evaluate($segment)->pluck('id')->map(fn ($v) => (int) $v)->all() : [];
        }

        $list = RecipientList::query()->find($id);

        return $list ? $list->recipients()->pluck('recipients.id')->map(fn ($v) => (int) $v)->all() : [];
    }

    private function subjectFilled(Campaign $campaign): ChecklistItem
    {
        return trim((string) $campaign->subject) !== ''
            ? new ChecklistItem('subject_filled', ChecklistItem::OK, true, 'Objet renseigné')
            : new ChecklistItem('subject_filled', ChecklistItem::ERROR, true, "L'objet est vide");
    }

    private function designReady(string $html): ChecklistItem
    {
        return trim($html) !== ''
            ? new ChecklistItem('design_ready', ChecklistItem::OK, true, 'Design prêt')
            : new ChecklistItem('design_ready', ChecklistItem::ERROR, true, "Le design de l'e-mail est vide");
    }

    private function unsubscribeLink(string $html): ChecklistItem
    {
        $present = preg_match('/\{\{\s*unsubscribe\s*\}\}/i', $html) === 1
            || preg_match('#href\s*=\s*["\'][^"\']*/unsubscribe/#i', $html) === 1;

        return $present
            ? new ChecklistItem('unsubscribe_link', ChecklistItem::OK, true, 'Lien de désinscription présent')
            : new ChecklistItem('unsubscribe_link', ChecklistItem::ERROR, true, 'Le design ne contient pas de lien de désinscription');
    }

    private function testSent(Campaign $campaign): ChecklistItem
    {
        return $campaign->getAttribute('test_sent_at') !== null
            ? new ChecklistItem('test_sent', ChecklistItem::OK, false, 'E-mail de test envoyé')
            : new ChecklistItem('test_sent', ChecklistItem::WARNING, false, 'Aucun e-mail de test envoyé');
    }

    private function linksValid(Campaign $campaign): ChecklistItem
    {
        $result = $this->links->cached($campaign->uuid, $campaign->html_body);

        if ($result === null) {
            return new ChecklistItem('links_valid', ChecklistItem::PENDING, false, 'Vérification des liens non effectuée', ['broken' => []]);
        }

        $broken = $result['broken'] ?? [];

        return $broken === []
            ? new ChecklistItem('links_valid', ChecklistItem::OK, false, 'Tous les liens sont valides', ['broken' => []])
            : new ChecklistItem('links_valid', ChecklistItem::WARNING, false, count($broken).' lien(s) invalide(s)', ['broken' => $broken]);
    }

    private function emailSize(string $html): ChecklistItem
    {
        $bytes = strlen($html);
        $details = ['bytes' => $bytes, 'limit' => self::EMAIL_SIZE_LIMIT];

        return $bytes <= self::EMAIL_SIZE_LIMIT
            ? new ChecklistItem('email_size', ChecklistItem::OK, false, "Taille de l'e-mail correcte", $details)
            : new ChecklistItem('email_size', ChecklistItem::WARNING, false, "L'e-mail dépasse 102 Ko (risque de coupure par Gmail)", $details);
    }

    /**
     * Hook for docs/39-deliverability-analyzer.md §39.2 — always skipped until
     * the analyzer exists.
     */
    private function spamScore(Campaign $campaign): ChecklistItem
    {
        return new ChecklistItem('spam_score', ChecklistItem::SKIPPED, false, "Analyse anti-spam non disponible");
    }
}
