<?php

namespace App\Modules\Campaigns\Http\Requests;

use App\Modules\Campaigns\Enums\CampaignSmtpStrategy;
use App\Modules\Campaigns\Models\CampaignFolder;
use App\Modules\Campaigns\Services\CampaignService;
use App\Modules\Campaigns\Support\ExternalReferences;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Templates\Models\Template;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared v2 validation + mapping of `CampaignUpdateInput` /
 * `CampaignAudienceInput` (docs/45 §45.4.2) onto `campaigns` columns.
 * Contract names whose column already existed are mapped here:
 * `requires_approval` → `approval_required`, `smtp_account_id` →
 * `single_smtp_account_id`, `send_window` → `business_hours_*`,
 * `smtp_strategy` `rotation|single` → `auto_rotate|pinned`.
 */
trait ValidatesCampaignInput
{
    /**
     * @return array<string, mixed>
     */
    protected function audienceRules(): array
    {
        $max = 'max:'.self::maxAudienceTargets();

        return [
            'audience' => ['sometimes', 'array'],
            'audience.lists' => ['sometimes', 'array', $max],
            'audience.lists.*' => ['string', 'exists:recipient_lists,uuid'],
            'audience.exclusion_lists' => ['sometimes', 'array', $max],
            'audience.exclusion_lists.*' => ['string', 'exists:recipient_lists,uuid'],
            'audience.segments' => ['sometimes', 'array', $max],
            'audience.segments.*' => ['string', 'exists:segments,uuid'],
            'audience.excluded_segments' => ['sometimes', 'array', $max],
            'audience.excluded_segments.*' => ['string', 'exists:segments,uuid'],
        ];
    }

    public static function maxAudienceTargets(): int
    {
        return 50;
    }

    /**
     * @return array<string, mixed>
     */
    protected function setupRules(): array
    {
        return [
            'preview_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sender_id' => ['sometimes', 'nullable', 'string', $this->verifiedSenderRule()],
            'reply_to' => ['sometimes', 'nullable', 'email', 'max:254'],
            'to_field' => ['sometimes', 'nullable', 'string', 'max:255'],
            'folder_id' => ['sometimes', 'nullable', 'string', 'exists:campaign_folders,uuid'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],

            'utm' => ['sometimes', 'nullable', 'array'],
            'utm.enabled' => ['sometimes', 'boolean'],
            'utm.source' => ['sometimes', 'nullable', 'string', 'max:100'],
            'utm.medium' => ['sometimes', 'nullable', 'string', 'max:100'],
            'utm.campaign' => ['sometimes', 'nullable', 'string', 'max:100'],
            'utm.content' => ['sometimes', 'nullable', 'string', 'max:100'],
            'utm.term' => ['sometimes', 'nullable', 'string', 'max:100'],
            'utm.id' => ['sometimes', 'nullable', 'string', 'max:100'],

            'mirror_link_enabled' => ['sometimes', 'boolean'],
            'inline_images' => ['sometimes', 'boolean'],
            'attachment_id' => ['sometimes', 'nullable', 'string', 'exists:attachments,uuid'],
            'email_expiration' => ['sometimes', 'nullable', 'array'],
            'email_expiration.duration' => ['required_with:email_expiration', 'integer', 'between:1,365'],
            'email_expiration.unit' => ['required_with:email_expiration', Rule::in(['days', 'weeks', 'months'])],
            'unsubscribe_page_id' => ['sometimes', 'nullable', 'string', $this->externalRule(ExternalReferences::UNSUBSCRIBE_PAGES_TABLE)],
            'update_profile_form_id' => ['sometimes', 'nullable', 'string', $this->externalRule(ExternalReferences::UPDATE_PROFILE_FORMS_TABLE)],

            'tracking_enabled' => ['sometimes', 'boolean'],
            'smtp_strategy' => ['sometimes', Rule::in(CampaignSmtpStrategy::acceptedInputValues())],
            'smtp_account_id' => ['sometimes', 'nullable', 'string', 'exists:smtp_accounts,uuid'],
            'send_window' => ['sometimes', 'nullable', 'array'],
            'send_window.days' => ['required_with:send_window', 'array', 'min:1', 'max:7'],
            'send_window.days.*' => ['integer', 'between:0,6'],
            'send_window.start' => ['required_with:send_window', 'date_format:H:i'],
            'send_window.end' => ['required_with:send_window', 'date_format:H:i', 'after:send_window.start'],
            'requires_approval' => ['sometimes', 'boolean'],
        ];
    }

    protected function verifiedSenderRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $refs = app(ExternalReferences::class);
            $id = $refs->idForUuid(ExternalReferences::SENDERS_TABLE, (string) $value);

            if ($id === null) {
                $fail('Expéditeur introuvable.');

                return;
            }

            if (! $refs->senderIsVerified($id)) {
                $fail("L'expéditeur doit être vérifié.");
            }
        };
    }

    protected function externalRule(string $table): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($table): void {
            if (app(ExternalReferences::class)->idForUuid($table, (string) $value) === null) {
                $fail('Élément introuvable.');
            }
        };
    }

    protected function validateAudienceShape(Validator $validator): void
    {
        $audience = $this->input('audience');

        if (! is_array($audience)) {
            return;
        }

        $total = 0;
        foreach (['lists', 'exclusion_lists', 'segments', 'excluded_segments'] as $key) {
            $total += count((array) ($audience[$key] ?? []));
        }

        if ($total > self::maxAudienceTargets()) {
            $validator->errors()->add('audience', 'Une campagne peut cibler au maximum 50 listes et segments.');
        }

        if (array_intersect((array) ($audience['lists'] ?? []), (array) ($audience['exclusion_lists'] ?? [])) !== []) {
            $validator->errors()->add('audience.exclusion_lists', 'Une liste ne peut pas être à la fois incluse et exclue.');
        }

        if (array_intersect((array) ($audience['segments'] ?? []), (array) ($audience['excluded_segments'] ?? [])) !== []) {
            $validator->errors()->add('audience.excluded_segments', 'Un segment ne peut pas être à la fois inclus et exclu.');
        }
    }

    /**
     * Validated payload → `campaigns` attributes (internal ids) + optional
     * `audience` (internal ids) for {@see CampaignService}.
     *
     * @return array<string, mixed>
     */
    public function toCampaignAttributes(): array
    {
        $data = $this->validated();
        $out = [];

        $plain = [
            'name', 'type', 'subject', 'preview_text', 'html_body', 'reply_to', 'to_field', 'tags',
            'mirror_link_enabled', 'inline_images', 'tracking_enabled', 'send_mode', 'recurrence_rule',
            'business_hours_only', 'business_hours_start', 'business_hours_end', 'business_days',
            'approval_required',
        ];
        foreach ($plain as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }

        $uuidMaps = [
            'template_id' => [Template::class, 'template_id'],
            'recipient_list_id' => [RecipientList::class, 'recipient_list_id'],
            'segment_id' => [Segment::class, 'segment_id'],
            'folder_id' => [CampaignFolder::class, 'folder_id'],
            'single_smtp_account_id' => [SmtpAccount::class, 'single_smtp_account_id'],
            'smtp_account_id' => [SmtpAccount::class, 'single_smtp_account_id'],
        ];
        foreach ($uuidMaps as $key => [$model, $column]) {
            if (array_key_exists($key, $data)) {
                $out[$column] = $data[$key] === null ? null : $model::query()->where('uuid', $data[$key])->value('id');
            }
        }

        $refs = app(ExternalReferences::class);
        if (array_key_exists('sender_id', $data)) {
            $out['sender_id'] = $refs->idForUuid(ExternalReferences::SENDERS_TABLE, $data['sender_id']);
        }
        if (array_key_exists('unsubscribe_page_id', $data)) {
            $out['unsubscribe_page_id'] = $refs->idForUuid(ExternalReferences::UNSUBSCRIBE_PAGES_TABLE, $data['unsubscribe_page_id']);
        }
        if (array_key_exists('update_profile_form_id', $data)) {
            $out['update_profile_form_id'] = $refs->idForUuid(ExternalReferences::UPDATE_PROFILE_FORMS_TABLE, $data['update_profile_form_id']);
        }
        if (array_key_exists('attachment_id', $data)) {
            $out['attachment_id'] = $data['attachment_id'] === null
                ? null
                : DB::table('attachments')->where('uuid', $data['attachment_id'])->value('id');
        }

        if (array_key_exists('smtp_strategy', $data)) {
            $out['smtp_strategy'] = CampaignSmtpStrategy::fromApi($data['smtp_strategy'])->value;
        }
        if (array_key_exists('requires_approval', $data)) {
            $out['approval_required'] = (bool) $data['requires_approval'];
        }

        if (array_key_exists('utm', $data)) {
            $utm = (array) ($data['utm'] ?? []);
            $out['utm_enabled'] = (bool) ($utm['enabled'] ?? false);
            foreach (['source', 'medium', 'campaign', 'content', 'term', 'id'] as $key) {
                if ($data['utm'] === null || array_key_exists($key, $utm)) {
                    $out['utm_'.$key] = $utm[$key] ?? null;
                }
            }
        }

        if (array_key_exists('email_expiration', $data)) {
            $out['email_expiration_duration'] = $data['email_expiration']['duration'] ?? null;
            $out['email_expiration_unit'] = $data['email_expiration']['unit'] ?? null;
        }

        if (array_key_exists('send_window', $data)) {
            $window = $data['send_window'];
            $out['business_hours_only'] = $window !== null;
            $out['business_hours_start'] = $window['start'] ?? null;
            $out['business_hours_end'] = $window['end'] ?? null;
            $out['business_days'] = $window === null ? null : array_map('intval', $window['days']);
        }

        if (array_key_exists('audience', $data)) {
            $audience = (array) $data['audience'];
            $out['audience'] = [
                'lists' => $this->idsFor(RecipientList::class, $audience['lists'] ?? []),
                'exclusion_lists' => $this->idsFor(RecipientList::class, $audience['exclusion_lists'] ?? []),
                'segments' => $this->idsFor(Segment::class, $audience['segments'] ?? []),
                'excluded_segments' => $this->idsFor(Segment::class, $audience['excluded_segments'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $uuids
     * @return list<int>
     */
    private function idsFor(string $model, array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }

        return $model::query()->whereIn('uuid', $uuids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
