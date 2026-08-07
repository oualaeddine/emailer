<?php

namespace Database\Seeders;

use App\Domain\Enums\ImportJobStatus;
use App\Domain\Enums\ImportRowValidationStatus;
use App\Domain\Enums\ImportSourceType;
use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RecipientListType;
use App\Domain\Enums\RecipientSource;
use App\Domain\Enums\RecipientStatus;
use App\Domain\Enums\RoleName;
use App\Domain\Enums\SendAttemptStatus;
use App\Domain\Enums\SuppressionReason;
use App\Modules\Campaigns\Enums\CampaignSendMode;
use App\Modules\Campaigns\Enums\CampaignSmtpStrategy;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Verification\Enums\VerificationVerdict;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data simulating ~6 months of active, high-load usage of the
 * "PageJaunes Mailer" B2B email tool by an Algerian sales/marketing team —
 * every module gets realistic French-language data: team members,
 * recipients, lists, templates, SMTP infrastructure, campaigns and their
 * per-recipient messages/send attempts, suppression, verification,
 * imports and an audit trail.
 *
 * Bulk-volume tables (recipients, messages, send_attempts, ...) are
 * inserted via chunked `DB::table()->insert()` rather than Eloquent for
 * speed; low-volume/relational tables use Eloquent so related IDs are
 * available immediately.
 */
class AlgeriaB2bDemoSeeder extends Seeder
{
    private const RECIPIENT_COUNT = 900;

    private const CAMPAIGN_COUNT = 34;

    private Carbon $periodStart;

    private Carbon $periodEnd;

    /** @var array<string, int> role name => id */
    private array $roleIds = [];

    /** @var list<int> */
    private array $userIds = [];

    /** @var array<string, int> user email => id */
    private array $userIdsByEmail = [];

    /** @var array<string, int> tag name => id */
    private array $tagIds = [];

    /** @var list<int> */
    private array $pageJaunesCacheIds = [];

    /** @var list<array{id: int, email: string, status: string, wilaya: string, sector: string, company: string}> */
    private array $recipients = [];

    /** @var array<string, array{id: int, type: string}> list name => info */
    private array $lists = [];

    /** @var list<array{id: int, html: string}> */
    private array $templates = [];

    /** @var list<array{id: int, pinned: bool, is_active: bool}> */
    private array $smtpAccounts = [];

    private array $wilayas = [
        ['Alger', 25], ['Oran', 15], ['Constantine', 10], ['Annaba', 6], ['Blida', 8],
        ['Sétif', 7], ['Tlemcen', 5], ['Tizi Ouzou', 6], ['Béjaïa', 5], ['Batna', 5],
        ['Mostaganem', 4], ['Ghardaïa', 3], ['Skikda', 3], ['Chlef', 3],
    ];

    private array $sectors = [
        'BTP', 'Industrie', 'Textiles', 'Négoce', 'Distribution', 'Agroalimentaire',
        'Logistique', 'Énergie', 'Consulting', 'Import-Export', 'Emballages',
        'Sidérurgie', 'Électronique', 'Chimie', 'Construction', 'Matériaux',
        'Solaire', 'Pêche', 'Automobile', 'Informatique',
    ];

    private array $brandWords = [
        'Numidia', 'Atlas', 'Sahara', 'Maghreb', 'Kahina', 'Djurdjura', 'Hoggar',
        'Tassili', 'Cirta', 'Icosium', 'Hydra', 'Zianide', 'Aurès', 'Kabylie',
        'Chenoua', 'Cheliff', 'Soummam', 'Tafna', 'Mitidja', 'Zibans',
    ];

    private array $legalForms = ['SARL', 'EURL', 'SPA', 'SNC'];

    private array $firstNames = [
        'Yacine', 'Karim', 'Nadia', 'Sofiane', 'Amel', 'Riad', 'Imane', 'Farid',
        'Meriem', 'Hocine', 'Djamila', 'Adel', 'Samira', 'Leila', 'Mourad',
        'Nassim', 'Sabrina', 'Walid', 'Ines', 'Khaled', 'Amine', 'Sarah',
        'Bilal', 'Yasmine', 'Fouad', 'Nawel', 'Rachid', 'Souad', 'Tarek', 'Wafa',
    ];

    private array $lastNames = [
        'Boudiaf', 'Kerrouche', 'Belhadj', 'Chergui', 'Ammour', 'Bensalah',
        'Meziane', 'Hamidi', 'Zerrouki', 'Rahmani', 'Benaissa', 'Ait-Saadi',
        'Yahiaoui', 'Ferhat', 'Bouzid', 'Cherfaoui', 'Toumi', 'Kaddour',
        'Belkacem', 'Ouali', 'Bentayeb', 'Mansouri', 'Boumediene', 'Haddad',
        'Guerroudj', 'Larbi', 'Saidi', 'Bouazza', 'Khelif', 'Mokrani',
    ];

    public function run(): void
    {
        $this->periodEnd = Carbon::now();
        $this->periodStart = Carbon::now()->subMonths(6);

        DB::transaction(function (): void {
            $this->seedUsers();
            $this->seedTags();
            $this->seedPageJaunesCache();
            $this->seedRecipients();
            $this->seedRecipientTags();
            $this->seedRecipientLists();
            $this->seedSegment();
            $this->seedRecipientNotes();
            $this->seedTemplates();
            $this->seedSmtpInfrastructure();
            $this->seedSignatures();
            $this->seedDrafts();
            $this->seedCampaignsAndMessages();
            $this->seedSuppressionEntries();
            $this->seedVerificationResults();
            $this->seedImportJobs();
            $this->seedAuditLogs();
        });
    }

    private function weightedPick(array $pairs): mixed
    {
        $total = array_sum(array_column($pairs, 1));
        $r = mt_rand(1, (int) $total);

        foreach ($pairs as [$value, $weight]) {
            $r -= $weight;

            if ($r <= 0) {
                return $value;
            }
        }

        return $pairs[array_key_last($pairs)][0];
    }

    private function algerianPhone(): string
    {
        $prefix = fake()->randomElement(['05', '06', '07']);

        return $prefix.fake()->numerify('########');
    }

    private function randomTimestamp(): Carbon
    {
        return Carbon::createFromTimestamp(
            fake()->numberBetween($this->periodStart->timestamp, $this->periodEnd->timestamp)
        );
    }

    // ------------------------------------------------------------------
    // Users
    // ------------------------------------------------------------------

    private function seedUsers(): void
    {
        $this->roleIds = Role::query()->pluck('id', 'name')->all();

        $admin = User::query()->where('email', 'admin@pagejaunes-mailer.local')->first();

        if ($admin) {
            $this->userIds[] = $admin->id;
            $this->userIdsByEmail[$admin->email] = $admin->id;
        }

        $team = [
            ['name' => 'Amine Kaddour', 'email' => 'amine.kaddour@pagejaunes-mailer.local', 'role' => RoleName::MarketingManager],
            ['name' => 'Sofia Belkacem', 'email' => 'sofia.belkacem@pagejaunes-mailer.local', 'role' => RoleName::MarketingManager],
            ['name' => 'Yasmine Toumi', 'email' => 'yasmine.toumi@pagejaunes-mailer.local', 'role' => RoleName::MarketingOperator],
            ['name' => 'Riad Cherfaoui', 'email' => 'riad.cherfaoui@pagejaunes-mailer.local', 'role' => RoleName::MarketingOperator],
            ['name' => 'Nassim Ouali', 'email' => 'nassim.ouali@pagejaunes-mailer.local', 'role' => RoleName::Viewer],
        ];

        foreach ($team as $index => $member) {
            $createdAt = $this->periodStart->copy()->addDays($index * 3);

            $user = User::query()->updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['name'],
                    'password' => Hash::make('password'),
                    'role_id' => $this->roleIds[$member['role']->value],
                    'is_active' => true,
                    'last_login_at' => fake()->dateTimeBetween('-3 days', 'now'),
                ]
            );

            $user->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

            $this->userIds[] = $user->id;
            $this->userIdsByEmail[$user->email] = $user->id;
        }
    }

    /** Any user except a Viewer — used for "who created/sent this" fields. */
    private function randomActiveUserId(): int
    {
        $viewerId = isset($this->userIdsByEmail['nassim.ouali@pagejaunes-mailer.local'])
            ? $this->userIdsByEmail['nassim.ouali@pagejaunes-mailer.local']
            : null;

        $pool = array_values(array_filter($this->userIds, fn (int $id) => $id !== $viewerId));

        return fake()->randomElement($pool ?: $this->userIds);
    }

    // ------------------------------------------------------------------
    // Tags
    // ------------------------------------------------------------------

    private function seedTags(): void
    {
        $colors = ['brand', 'success', 'warning', 'danger', 'informative', 'severe', 'important', 'subtle'];
        $names = array_merge(
            array_column($this->wilayas, 0),
            $this->sectors,
            ['Prospect chaud', 'Client fidèle', 'À relancer', 'Grand compte']
        );

        foreach (array_unique($names) as $index => $name) {
            $tag = DB::table('tags')->where('name', $name)->first();

            if ($tag) {
                $this->tagIds[$name] = $tag->id;

                continue;
            }

            $this->tagIds[$name] = DB::table('tags')->insertGetId([
                'name' => $name,
                'color' => $colors[$index % count($colors)],
                'created_at' => $this->periodStart,
                'updated_at' => $this->periodStart,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // PageJaunes directory cache
    // ------------------------------------------------------------------

    private function seedPageJaunesCache(): void
    {
        $rows = [];

        for ($i = 0; $i < 60; $i++) {
            $wilaya = $this->weightedPick($this->wilayas);
            $sector = fake()->randomElement($this->sectors);
            $brand = fake()->randomElement($this->brandWords);
            $legal = fake()->randomElement($this->legalForms);
            $name = "{$legal} {$brand} {$sector}";
            $syncedAt = $this->randomTimestamp();

            $rows[] = [
                'pagejaunes_company_id' => 100000 + $i,
                'pagejaunes_code' => 'PJ-'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                'company_name' => $name,
                'abv_company_name' => Str::upper(Str::substr($brand, 0, 3)).'-'.Str::upper(Str::substr($sector, 0, 3)),
                'slug' => Str::slug($name).'-'.$i,
                'legal_form_label' => $legal,
                'country_label' => 'Algérie',
                'wilaya_label' => $wilaya,
                'locality_label' => $wilaya,
                'address' => fake()->numberBetween(1, 200).' Zone Industrielle, '.$wilaya,
                'about' => "Entreprise algérienne spécialisée en {$sector}, basée à {$wilaya}.",
                'is_distributor' => fake()->boolean(30),
                'is_exporter' => fake()->boolean(20),
                'is_importer' => fake()->boolean(35),
                'is_producer' => fake()->boolean(40),
                'is_customer' => fake()->boolean(15),
                'nb_employee' => fake()->randomElement(['1-9', '10-49', '50-199', '200-499', '500+']),
                'website' => 'https://www.'.Str::slug($brand.' '.$sector).'.dz',
                'main_picture_url' => null,
                'source_created_at' => $syncedAt->copy()->subDays(fake()->numberBetween(30, 900)),
                'source_deleted_at' => null,
                'raw_source_data' => json_encode(['legacy_id' => 100000 + $i]),
                'synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ];
        }

        DB::table('pagejaunes_company_cache')->insert($rows);

        $cache = DB::table('pagejaunes_company_cache')->select('id')->get();
        $this->pageJaunesCacheIds = $cache->pluck('id')->all();

        $emailRows = [];
        $emailIdSeq = 500000;

        foreach ($cache as $company) {
            $count = fake()->numberBetween(1, 2);

            for ($e = 0; $e < $count; $e++) {
                $emailRows[] = [
                    'pagejaunes_company_cache_id' => $company->id,
                    'pagejaunes_email_id' => $emailIdSeq++,
                    'email' => 'contact'.$emailIdSeq.'@pagesjaunes-dz-cache.dz',
                    'source_deleted_at' => null,
                    'synced_at' => $this->randomTimestamp(),
                ];
            }
        }

        DB::table('pagejaunes_company_emails_cache')->insert($emailRows);
    }

    // ------------------------------------------------------------------
    // Recipients
    // ------------------------------------------------------------------

    private function seedRecipients(): void
    {
        $meta = [];
        $rows = [];
        $usedEmails = [];

        for ($i = 0; $i < self::RECIPIENT_COUNT; $i++) {
            $wilaya = $this->weightedPick($this->wilayas);
            $sector = fake()->randomElement($this->sectors);
            $legal = fake()->randomElement($this->legalForms);
            $brand = fake()->randomElement($this->brandWords);
            $company = trim("{$legal} {$brand} {$sector}".(fake()->boolean(50) ? " {$wilaya}" : ''));

            $first = fake()->randomElement($this->firstNames);
            $last = fake()->randomElement($this->lastNames);
            $domain = Str::slug("{$brand} {$sector}", '-').$i.'.dz';
            $email = strtolower(substr($first, 0, 1)).'.'.Str::slug($last, '').'@'.$domain;

            $suffix = 1;

            while (isset($usedEmails[$email])) {
                $suffix++;
                $email = strtolower(substr($first, 0, 1)).'.'.Str::slug($last, '').$suffix.'@'.$domain;
            }

            $usedEmails[$email] = true;

            $status = $this->weightedPick([
                [RecipientStatus::Active->value, 88],
                [RecipientStatus::Suppressed->value, 7],
                [RecipientStatus::Invalid->value, 5],
            ]);

            $source = $this->weightedPick([
                [RecipientSource::Manual->value, 40],
                [RecipientSource::CsvImport->value, 20],
                [RecipientSource::ExcelImport->value, 10],
                [RecipientSource::PageJaunes->value, 25],
                [RecipientSource::InternalContact->value, 5],
            ]);

            $pjCacheId = $source === RecipientSource::PageJaunes->value && $this->pageJaunesCacheIds !== []
                ? fake()->randomElement($this->pageJaunesCacheIds)
                : null;

            $createdAt = $this->randomTimestamp();

            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'email' => $email,
                'first_name' => $first,
                'last_name' => $last,
                'company_name' => $company,
                'pagejaunes_company_cache_id' => $pjCacheId,
                'source' => $source,
                'status' => $status,
                'custom_fields' => json_encode(['wilaya' => $wilaya, 'secteur' => $sector, 'telephone' => $this->algerianPhone()]),
                'last_contacted_at' => fake()->boolean(55) ? fake()->dateTimeBetween($createdAt, $this->periodEnd) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            $meta[$email] = ['wilaya' => $wilaya, 'sector' => $sector, 'company' => $company];

            if (count($rows) >= 300) {
                DB::table('recipients')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('recipients')->insert($rows);
        }

        $this->recipients = DB::table('recipients')
            ->select('id', 'email', 'status')
            ->get()
            ->map(function (object $r) use ($meta): array {
                $m = $meta[$r->email] ?? ['wilaya' => 'Alger', 'sector' => 'Services', 'company' => ''];

                return [
                    'id' => $r->id,
                    'email' => $r->email,
                    'status' => $r->status,
                    'wilaya' => $m['wilaya'],
                    'sector' => $m['sector'],
                    'company' => $m['company'],
                ];
            })
            ->all();
    }

    private function seedRecipientTags(): void
    {
        $lifecycle = ['Prospect chaud', 'Client fidèle', 'À relancer', 'Grand compte'];
        $rows = [];

        foreach ($this->recipients as $recipient) {
            $names = [$recipient['wilaya'], $recipient['sector']];

            if (fake()->boolean(30)) {
                $names[] = fake()->randomElement($lifecycle);
            }

            foreach (array_unique($names) as $name) {
                if (! isset($this->tagIds[$name])) {
                    continue;
                }

                $rows[] = ['recipient_id' => $recipient['id'], 'tag_id' => $this->tagIds[$name]];
            }

            if (count($rows) >= 500) {
                DB::table('recipient_tag')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('recipient_tag')->insert($rows);
        }
    }

    // ------------------------------------------------------------------
    // Recipient lists
    // ------------------------------------------------------------------

    private function seedRecipientLists(): void
    {
        $masterId = DB::table('recipient_lists')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Liste maîtresse — Tous contacts B2B Algérie',
            'description' => 'Ensemble complet des contacts B2B algériens collectés (saisie manuelle, imports, annuaire PageJaunes).',
            'type' => RecipientListType::Static->value,
            'created_by' => $this->randomActiveUserId(),
            'created_at' => $this->periodStart,
            'updated_at' => $this->periodStart,
        ]);

        $this->lists['Liste maîtresse — Tous contacts B2B Algérie'] = ['id' => $masterId, 'type' => 'static'];

        $this->attachRecipientsToList($masterId, array_column($this->recipients, 'id'));

        $themedLists = [
            'Prospects BTP & Construction' => fn (array $r) => in_array($r['sector'], ['BTP', 'Construction', 'Matériaux'], true),
            'Industrie & Sidérurgie' => fn (array $r) => in_array($r['sector'], ['Industrie', 'Sidérurgie', 'Chimie'], true),
            'Import-Export & Logistique' => fn (array $r) => in_array($r['sector'], ['Import-Export', 'Logistique', 'Négoce'], true),
            'Alger & Blida' => fn (array $r) => in_array($r['wilaya'], ['Alger', 'Blida'], true),
            'Oran & Ouest algérien' => fn (array $r) => in_array($r['wilaya'], ['Oran', 'Tlemcen', 'Mostaganem', 'Chlef'], true),
            'Constantine & Est algérien' => fn (array $r) => in_array($r['wilaya'], ['Constantine', 'Annaba', 'Sétif', 'Batna', 'Skikda'], true),
            'Clients actifs' => fn (array $r) => $r['status'] === RecipientStatus::Active->value,
        ];

        foreach ($themedLists as $name => $filter) {
            $matching = array_values(array_filter($this->recipients, $filter));
            $sample = fake()->boolean(70) ? $matching : fake()->randomElements($matching, min(count($matching), fake()->numberBetween(60, 250)));

            $listId = DB::table('recipient_lists')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'description' => "Segmentation statique — {$name}.",
                'type' => RecipientListType::Static->value,
                'created_by' => $this->randomActiveUserId(),
                'created_at' => $this->randomTimestamp(),
                'updated_at' => $this->periodEnd,
            ]);

            $this->lists[$name] = ['id' => $listId, 'type' => 'static'];
            $this->attachRecipientsToList($listId, array_column($sample, 'id'));
        }

        $dynamicListId = DB::table('recipient_lists')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Segment dynamique — Prospects actifs Alger',
            'description' => 'Recalculé automatiquement : contacts actifs de la wilaya d\'Alger.',
            'type' => RecipientListType::DynamicSegment->value,
            'created_by' => $this->randomActiveUserId(),
            'created_at' => $this->randomTimestamp(),
            'updated_at' => $this->periodEnd,
        ]);

        $this->lists['Segment dynamique — Prospects actifs Alger'] = ['id' => $dynamicListId, 'type' => 'dynamic_segment'];
    }

    private function attachRecipientsToList(int $listId, array $recipientIds): void
    {
        $rows = [];

        foreach ($recipientIds as $recipientId) {
            $rows[] = [
                'recipient_list_id' => $listId,
                'recipient_id' => $recipientId,
                'added_at' => $this->randomTimestamp(),
            ];

            if (count($rows) >= 500) {
                DB::table('recipient_list_recipient')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('recipient_list_recipient')->insert($rows);
        }
    }

    private function seedSegment(): void
    {
        $dynamicList = $this->lists['Segment dynamique — Prospects actifs Alger'];
        $matching = array_values(array_filter(
            $this->recipients,
            fn (array $r) => $r['wilaya'] === 'Alger' && $r['status'] === RecipientStatus::Active->value
        ));

        DB::table('segments')->insert([
            'uuid' => (string) Str::uuid(),
            'recipient_list_id' => $dynamicList['id'],
            'rules' => json_encode(['status' => 'active', 'wilaya' => 'Alger']),
            'last_evaluated_at' => $this->periodEnd,
            'cached_count' => count($matching),
            'created_at' => $this->periodStart,
            'updated_at' => $this->periodEnd,
        ]);
    }

    private function seedRecipientNotes(): void
    {
        $noteTemplates = [
            'Contact injoignable au téléphone, relance par e-mail prévue.',
            'Intéressé par une offre groupée, à recontacter après le Ramadan.',
            'Demande un devis pour 500 unités, en attente de réponse commerciale.',
            'Client historique — privilégier les campagnes premium.',
            'A assisté au salon Alger BTP 2026, très bon prospect.',
            'Souhaite être retiré des campagnes de prospection à froid.',
            'Décideur confirmé, budget validé pour le prochain trimestre.',
            'Doublon possible avec un autre contact de la même société.',
        ];

        $sample = fake()->randomElements($this->recipients, min(count($this->recipients), 150));
        $rows = [];

        foreach ($sample as $recipient) {
            $rows[] = [
                'recipient_id' => $recipient['id'],
                'user_id' => $this->randomActiveUserId(),
                'body' => fake()->randomElement($noteTemplates),
                'created_at' => $this->randomTimestamp(),
            ];
        }

        DB::table('recipient_notes')->insert($rows);
    }

    // ------------------------------------------------------------------
    // Templates
    // ------------------------------------------------------------------

    private function seedTemplates(): void
    {
        $catalog = [
            ['name' => 'Prospection B2B — Premier contact', 'category' => 'Prospection', 'subject' => 'Développez votre activité en Algérie avec nos solutions B2B'],
            ['name' => 'Relance prospects froids', 'category' => 'Relance', 'subject' => 'On se recontacte ? Une offre pensée pour {{company_name}}'],
            ['name' => 'Invitation salon professionnel', 'category' => 'Événementiel', 'subject' => 'Rencontrons-nous au prochain salon B2B'],
            ['name' => 'Newsletter mensuelle partenaires', 'category' => 'Newsletter', 'subject' => 'Actualités du mois — Réseau B2B Algérie'],
            ['name' => 'Offre spéciale fin de trimestre', 'category' => 'Promotion', 'subject' => 'Offre exclusive avant la fin du trimestre'],
            ['name' => 'Remerciement après commande', 'category' => 'Fidélisation', 'subject' => 'Merci pour votre confiance, {{first_name}}'],
            ['name' => 'Annonce nouveau produit', 'category' => 'Lancement', 'subject' => 'Découvrez notre nouvelle gamme dès aujourd\'hui'],
            ['name' => 'Enquête de satisfaction', 'category' => 'Fidélisation', 'subject' => 'Votre avis compte — 2 minutes suffisent'],
            ['name' => 'Rappel de rendez-vous commercial', 'category' => 'Relance', 'subject' => 'Rappel : rendez-vous prévu cette semaine'],
            ['name' => 'Vœux professionnels', 'category' => 'Institutionnel', 'subject' => 'Toute notre équipe vous souhaite une excellente année'],
        ];

        foreach ($catalog as $entry) {
            $html = $this->templateHtml($entry['subject']);
            $createdAt = $this->randomTimestamp();

            $templateId = DB::table('templates')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $entry['name'],
                'category' => $entry['category'],
                'thumbnail_path' => null,
                'html_content' => $html,
                'is_archived' => false,
                'created_by' => $this->randomActiveUserId(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            DB::table('template_versions')->insert([
                'template_id' => $templateId,
                'version_number' => 1,
                'html_content' => $html,
                'created_by' => $this->randomActiveUserId(),
                'change_note' => 'Version initiale.',
                'created_at' => $createdAt,
            ]);

            if (fake()->boolean(40)) {
                $updatedHtml = $this->templateHtml($entry['subject'], revised: true);
                $revisedAt = Carbon::instance($createdAt)->addDays(fake()->numberBetween(5, 60))->min($this->periodEnd);

                DB::table('template_versions')->insert([
                    'template_id' => $templateId,
                    'version_number' => 2,
                    'html_content' => $updatedHtml,
                    'created_by' => $this->randomActiveUserId(),
                    'change_note' => 'Ajustement du ton et de l\'appel à l\'action.',
                    'created_at' => $revisedAt,
                ]);

                DB::table('templates')->where('id', $templateId)->update([
                    'html_content' => $updatedHtml,
                    'updated_at' => $revisedAt,
                ]);
                $html = $updatedHtml;
            }

            $this->templates[] = ['id' => $templateId, 'html' => $html, 'subject' => $entry['subject']];
        }

        $blocks = [
            ['name' => 'En-tête PageJaunes Mailer', 'type' => 'header'],
            ['name' => 'Pied de page avec désinscription', 'type' => 'footer'],
            ['name' => 'Bouton d\'appel à l\'action', 'type' => 'cta'],
            ['name' => 'Bloc coordonnées entreprise', 'type' => 'contact'],
            ['name' => 'Séparateur visuel', 'type' => 'divider'],
            ['name' => 'Témoignage client', 'type' => 'testimonial'],
        ];

        foreach ($blocks as $block) {
            DB::table('template_blocks')->insert([
                'uuid' => (string) Str::uuid(),
                'name' => $block['name'],
                'block_type' => $block['type'],
                'html_content' => "<div class=\"block-{$block['type']}\">".e($block['name']).'</div>',
                'created_by' => $this->randomActiveUserId(),
                'created_at' => $this->randomTimestamp(),
                'updated_at' => $this->periodEnd,
            ]);
        }
    }

    private function templateHtml(string $subject, bool $revised = false): string
    {
        $cta = $revised ? 'Réserver mon créneau' : 'Prendre rendez-vous';

        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #1f2933;">
            <h1 style="color: #DF0A0A;">{$subject}</h1>
            <p>Bonjour {{first_name}},</p>
            <p>
                Nous accompagnons les entreprises algériennes — du BTP à l'industrie
                en passant par l'import-export — dans la digitalisation de leur relation
                commerciale.
            </p>
            <p><strong>{{company_name}}</strong>, découvrez comment nous pouvons vous aider
            à générer plus de leads qualifiés partout en Algérie.</p>
            <p style="text-align: center; margin: 32px 0;">
                <a href="#" style="background: #DF0A0A; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none;">{$cta}</a>
            </p>
            <p>Cordialement,<br>L'équipe commerciale</p>
        </div>
        HTML;
    }

    // ------------------------------------------------------------------
    // SMTP infrastructure
    // ------------------------------------------------------------------

    private function seedSmtpInfrastructure(): void
    {
        $warmupId = DB::table('warmup_schedules')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Montée en charge standard — 30 jours',
            'stages' => json_encode([
                ['day' => 1, 'max_per_day' => 50],
                ['day' => 5, 'max_per_day' => 150],
                ['day' => 10, 'max_per_day' => 400],
                ['day' => 20, 'max_per_day' => 1000],
                ['day' => 30, 'max_per_day' => 2500],
            ]),
            'created_at' => $this->periodStart,
            'updated_at' => $this->periodStart,
        ]);

        $warmupIdFast = DB::table('warmup_schedules')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Montée en charge accélérée — 14 jours',
            'stages' => json_encode([
                ['day' => 1, 'max_per_day' => 100],
                ['day' => 7, 'max_per_day' => 600],
                ['day' => 14, 'max_per_day' => 2000],
            ]),
            'created_at' => $this->periodStart,
            'updated_at' => $this->periodStart,
        ]);

        $accounts = [
            ['name' => 'SMTP Ventes Algérie — Principal', 'from_email' => 'contact@ventes-algerie.dz', 'health' => 'healthy', 'warmup' => null, 'active' => true, 'daily' => 5000, 'hourly' => 600],
            ['name' => 'SMTP Prospection B2B', 'from_email' => 'prospection@ventes-algerie.dz', 'health' => 'healthy', 'warmup' => null, 'active' => true, 'daily' => 3000, 'hourly' => 400],
            ['name' => 'SMTP Newsletter & Fidélisation', 'from_email' => 'newsletter@ventes-algerie.dz', 'health' => 'degraded', 'warmup' => null, 'active' => true, 'daily' => 2000, 'hourly' => 250],
            ['name' => 'SMTP Nouveau compte — en warmup', 'from_email' => 'compte3@ventes-algerie.dz', 'health' => 'healthy', 'warmup' => $warmupId, 'active' => true, 'daily' => 1000, 'hourly' => 150],
            ['name' => 'SMTP Secours — désactivé', 'from_email' => 'secours@ventes-algerie.dz', 'health' => 'disabled', 'warmup' => $warmupIdFast, 'active' => false, 'daily' => 1500, 'hourly' => 200],
        ];

        foreach ($accounts as $account) {
            $createdAt = $this->periodStart->copy()->addDays(fake()->numberBetween(0, 30));

            $accountId = DB::table('smtp_accounts')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $account['name'],
                'provider' => 'smtp',
                'host' => 'smtp.'.Str::after($account['from_email'], '@'),
                'port' => 587,
                'encryption' => 'tls',
                'username' => $account['from_email'],
                'password_encrypted' => encrypt('demo-password-'.Str::random(8)),
                'from_email' => $account['from_email'],
                'from_name' => 'Équipe Commerciale',
                'daily_quota' => $account['daily'],
                'hourly_quota' => $account['hourly'],
                'minute_quota' => (int) round($account['hourly'] / 20),
                'max_concurrent_connections' => fake()->numberBetween(2, 8),
                'max_messages_per_connection' => fake()->numberBetween(50, 200),
                'priority' => fake()->numberBetween(1, 100),
                'rotation_weight' => fake()->numberBetween(1, 10),
                'warmup_enabled' => $account['warmup'] !== null,
                'warmup_schedule_id' => $account['warmup'],
                'health_status' => $account['health'],
                'bounce_rate' => fake()->randomFloat(2, 0, 5),
                'complaint_rate' => fake()->randomFloat(2, 0, 1),
                'reputation_score' => fake()->randomFloat(2, 70, 99),
                'is_active' => $account['active'],
                'last_tested_at' => $this->randomTimestamp(),
                'created_at' => $createdAt,
                'updated_at' => $this->periodEnd,
            ]);

            $this->smtpAccounts[] = [
                'id' => $accountId,
                'active' => $account['active'] && $account['health'] !== 'disabled',
            ];

            $ledgerRows = [];
            $cursor = $this->periodEnd->copy()->subDays(60)->startOfDay();

            while ($cursor->lte($this->periodEnd)) {
                $ledgerRows[] = [
                    'smtp_account_id' => $accountId,
                    'window_type' => 'daily',
                    'window_start' => $cursor->copy(),
                    'sent_count' => fake()->numberBetween(0, (int) ($account['daily'] * 0.85)),
                    'created_at' => $cursor->copy(),
                    'updated_at' => $cursor->copy(),
                ];
                $cursor->addDay();
            }

            foreach (array_chunk($ledgerRows, 200) as $chunk) {
                DB::table('quota_ledger')->insert($chunk);
            }
        }
    }

    // ------------------------------------------------------------------
    // Signatures & Drafts
    // ------------------------------------------------------------------

    private function seedSignatures(): void
    {
        foreach ($this->userIds as $userId) {
            DB::table('signatures')->insert([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'name' => 'Signature par défaut',
                'html_content' => '<p>Cordialement,<br><strong>L\'équipe commerciale</strong><br>PageJaunes Mailer — Algérie</p>',
                'is_default' => true,
                'created_at' => $this->periodStart,
                'updated_at' => $this->periodStart,
            ]);
        }
    }

    private function seedDrafts(): void
    {
        $signatureByUser = DB::table('signatures')->pluck('id', 'user_id');

        for ($i = 0; $i < 20; $i++) {
            $userId = $this->randomActiveUserId();
            $template = fake()->randomElement($this->templates);
            $createdAt = $this->randomTimestamp();
            $status = fake()->randomElement(['draft', 'draft', 'draft', 'ready']);

            $draftId = DB::table('drafts')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'template_id' => fake()->boolean(70) ? $template['id'] : null,
                'subject' => $template['subject'],
                'html_body' => $template['html'],
                'signature_id' => $signatureByUser[$userId] ?? null,
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            DB::table('draft_versions')->insert([
                'draft_id' => $draftId,
                'version_number' => 1,
                'html_body' => $template['html'],
                'subject' => $template['subject'],
                'created_by' => $userId,
                'created_at' => $createdAt,
            ]);

            if (fake()->boolean(30)) {
                DB::table('attachments')->insert([
                    'uuid' => (string) Str::uuid(),
                    'attachable_type' => 'App\\Modules\\Composer\\Models\\Draft',
                    'attachable_id' => $draftId,
                    'disk' => 'local',
                    'path' => 'attachments/'.Str::random(20).'.pdf',
                    'original_filename' => 'brochure-produits.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => fake()->numberBetween(50_000, 2_500_000),
                    'created_at' => $createdAt,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Campaigns & Messages
    // ------------------------------------------------------------------

    private function seedCampaignsAndMessages(): void
    {
        $campaignNames = [
            'Prospection B2B Algérie', 'Relance prospects froids', 'Invitation salon Alger BTP',
            'Newsletter partenaires', 'Offre fin de trimestre', 'Remerciement clients fidèles',
            'Lancement nouvelle gamme', 'Enquête satisfaction annuelle', 'Rappel rendez-vous commerciaux',
            'Vœux de fin d\'année', 'Campagne Ramadan — offres spéciales', 'Relance devis en attente',
            'Invitation webinaire produit', 'Prospection secteur BTP', 'Prospection secteur Industrie',
            'Prospection Import-Export', 'Réactivation comptes inactifs', 'Annonce partenariat stratégique',
        ];

        $listNames = array_keys($this->lists);
        $staticListNames = array_values(array_filter($listNames, fn (string $n) => $this->lists[$n]['type'] === 'static'));

        $statusPlan = $this->buildCampaignStatusPlan();

        foreach ($statusPlan as $index => $status) {
            $name = $campaignNames[$index % count($campaignNames)].' — '.$this->campaignBatchLabel($index);
            $template = fake()->randomElement($this->templates);
            $listName = fake()->randomElement($staticListNames);
            $list = $this->lists[$listName];

            $smtpStrategy = fake()->boolean(75) ? CampaignSmtpStrategy::AutoRotate : CampaignSmtpStrategy::Pinned;
            $pinnedAccount = $smtpStrategy === CampaignSmtpStrategy::Pinned
                ? fake()->randomElement($this->smtpAccounts)['id']
                : null;

            $createdBy = $this->randomActiveUserId();
            [$createdAt, $scheduledAt, $sentAt] = $this->campaignTimestampsFor($status);

            $approvalRequired = fake()->boolean(35);
            $approvedBy = $approvalRequired && $status !== CampaignStatus::Draft ? $this->randomActiveUserId() : null;
            $approvedAt = $approvedBy ? Carbon::instance($createdAt)->addHours(fake()->numberBetween(1, 48)) : null;

            $campaignId = DB::table('campaigns')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'template_id' => $template['id'],
                'subject' => $template['subject'],
                'html_body' => $template['html'],
                'recipient_list_id' => $list['id'],
                'segment_id' => null,
                'status' => $status->value,
                'smtp_strategy' => $smtpStrategy->value,
                'single_smtp_account_id' => $pinnedAccount,
                'send_mode' => $status === CampaignStatus::Scheduled ? CampaignSendMode::Scheduled->value : CampaignSendMode::Immediate->value,
                'scheduled_at' => $scheduledAt,
                'recurrence_rule' => null,
                'business_hours_only' => true,
                'business_hours_start' => '08:00:00',
                'business_hours_end' => '17:00:00',
                'business_days' => json_encode(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday']),
                'approval_required' => $approvalRequired,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
                'tracking_enabled' => true,
                'cloned_from_campaign_id' => null,
                'created_by' => $createdBy,
                'sent_at' => $sentAt,
                'created_at' => $createdAt,
                'updated_at' => $sentAt ?? $createdAt,
            ]);

            if (in_array($status, [CampaignStatus::Completed, CampaignStatus::Running, CampaignStatus::Paused], true)) {
                $this->seedMessagesForCampaign($campaignId, $list['id'], $status, $sentAt ?? $createdAt, $pinnedAccount);
            }
        }
    }

    /** @return list<CampaignStatus> */
    private function buildCampaignStatusPlan(): array
    {
        $plan = [];

        for ($i = 0; $i < 24; $i++) {
            $plan[] = CampaignStatus::Completed;
        }

        $plan[] = CampaignStatus::Running;
        $plan[] = CampaignStatus::Running;
        $plan[] = CampaignStatus::Paused;
        $plan[] = CampaignStatus::Scheduled;
        $plan[] = CampaignStatus::Scheduled;
        $plan[] = CampaignStatus::Scheduled;
        $plan[] = CampaignStatus::Draft;
        $plan[] = CampaignStatus::Draft;
        $plan[] = CampaignStatus::Draft;
        $plan[] = CampaignStatus::Cancelled;

        shuffle($plan);

        return array_slice(array_pad($plan, self::CAMPAIGN_COUNT, CampaignStatus::Completed), 0, self::CAMPAIGN_COUNT);
    }

    private function campaignBatchLabel(int $index): string
    {
        $monthOffset = intdiv($index, 6);
        $label = $this->periodStart->copy()->addMonths($monthOffset);

        return $label->translatedFormat('F Y');
    }

    /** @return array{0: Carbon, 1: Carbon|null, 2: Carbon|null} */
    private function campaignTimestampsFor(CampaignStatus $status): array
    {
        return match ($status) {
            CampaignStatus::Completed => (function () {
                $created = $this->randomTimestamp();
                $sent = Carbon::instance($created)->addHours(fake()->numberBetween(1, 6))->min($this->periodEnd);

                return [$created, $sent, $sent];
            })(),
            CampaignStatus::Running, CampaignStatus::Paused => (function () {
                $created = $this->periodEnd->copy()->subHours(fake()->numberBetween(2, 48));

                return [$created, null, null];
            })(),
            CampaignStatus::Scheduled => (function () {
                $created = $this->periodEnd->copy()->subDays(fake()->numberBetween(0, 5));
                $scheduled = $this->periodEnd->copy()->addDays(fake()->numberBetween(1, 21));

                return [$created, $scheduled, null];
            })(),
            CampaignStatus::Draft => (function () {
                $created = $this->periodEnd->copy()->subDays(fake()->numberBetween(0, 10));

                return [$created, null, null];
            })(),
            CampaignStatus::Cancelled => (function () {
                $created = $this->randomTimestamp();
                $scheduled = Carbon::instance($created)->addDays(fake()->numberBetween(1, 10));

                return [$created, $scheduled, null];
            })(),
        };
    }

    private function seedMessagesForCampaign(int $campaignId, int $listId, CampaignStatus $status, Carbon $sendStart, ?int $pinnedAccountId): void
    {
        $listRecipientIds = DB::table('recipient_list_recipient')
            ->where('recipient_list_id', $listId)
            ->pluck('recipient_id')
            ->all();

        if ($listRecipientIds === []) {
            return;
        }

        $targetCount = min(count($listRecipientIds), fake()->numberBetween(120, 450));
        $sampleIds = fake()->randomElements($listRecipientIds, $targetCount);

        // A running/paused campaign has only progressed through part of its list.
        if (in_array($status, [CampaignStatus::Running, CampaignStatus::Paused], true)) {
            $sampleIds = array_slice($sampleIds, 0, (int) round($targetCount * fake()->randomFloat(2, 0.3, 0.7)));
        }

        $messageRows = [];
        $messageUuids = [];

        foreach ($sampleIds as $recipientId) {
            $uuid = (string) Str::uuid();
            $messageUuids[] = $uuid;

            $statusOutcome = $this->weightedPick([
                [MessageStatus::Delivered->value, 45],
                [MessageStatus::Opened->value, 25],
                [MessageStatus::Clicked->value, 10],
                [MessageStatus::SoftBounced->value, 6],
                [MessageStatus::HardBounced->value, 5],
                [MessageStatus::Failed->value, 4],
                [MessageStatus::Accepted->value, 3],
                [MessageStatus::Queued->value, 2],
            ]);

            $queuedAt = $sendStart->copy()->addMinutes(fake()->numberBetween(0, 600));
            [$sentAt, $deliveredAt, $openedAt, $clickedAt, $bouncedAt, $failedAt, $openCount, $clickCount] =
                $this->messageTimelineFor($statusOutcome, $queuedAt);

            $accountId = $pinnedAccountId ?? fake()->randomElement($this->smtpAccounts)['id'];

            $messageRows[] = [
                'uuid' => $uuid,
                'campaign_id' => $campaignId,
                'draft_id' => null,
                'recipient_id' => $recipientId,
                'smtp_account_id' => $accountId,
                'subject' => 'Message de campagne',
                'html_body' => '<p>Contenu de campagne.</p>',
                'status' => $statusOutcome,
                'queued_at' => $queuedAt,
                'sent_at' => $sentAt,
                'delivered_at' => $deliveredAt,
                'opened_at' => $openedAt,
                'clicked_at' => $clickedAt,
                'bounced_at' => $bouncedAt,
                'failed_at' => $failedAt,
                'provider_message_id' => 'pjm-'.Str::random(16),
                'last_provider_response' => $statusOutcome === MessageStatus::Failed->value || $statusOutcome === MessageStatus::HardBounced->value
                    ? '550 5.1.1 The email account that you tried to reach does not exist.'
                    : '250 OK',
                'open_count' => $openCount,
                'click_count' => $clickCount,
                'created_at' => $queuedAt,
                'updated_at' => $deliveredAt ?? $queuedAt,
            ];

            if (count($messageRows) >= 400) {
                $this->flushMessages($messageRows, $messageUuids, $accountId);
                $messageRows = [];
                $messageUuids = [];
            }
        }

        if ($messageRows !== []) {
            $this->flushMessages($messageRows, $messageUuids, $pinnedAccountId ?? fake()->randomElement($this->smtpAccounts)['id']);
        }
    }

    /** @return array{0: ?Carbon, 1: ?Carbon, 2: ?Carbon, 3: ?Carbon, 4: ?Carbon, 5: ?Carbon, 6: int, 7: int} */
    private function messageTimelineFor(string $status, Carbon $queuedAt): array
    {
        $sentAt = $status === MessageStatus::Queued->value ? null : $queuedAt->copy()->addSeconds(fake()->numberBetween(5, 120));
        $deliveredAt = null;
        $openedAt = null;
        $clickedAt = null;
        $bouncedAt = null;
        $failedAt = null;
        $openCount = 0;
        $clickCount = 0;

        if (in_array($status, [MessageStatus::Delivered->value, MessageStatus::Opened->value, MessageStatus::Clicked->value], true)) {
            $deliveredAt = $sentAt->copy()->addMinutes(fake()->numberBetween(1, 30));
        }

        if (in_array($status, [MessageStatus::Opened->value, MessageStatus::Clicked->value], true)) {
            $openedAt = $deliveredAt->copy()->addMinutes(fake()->numberBetween(5, 2000));
            $openCount = fake()->numberBetween(1, 4);
        }

        if ($status === MessageStatus::Clicked->value) {
            $clickedAt = $openedAt->copy()->addMinutes(fake()->numberBetween(1, 120));
            $clickCount = fake()->numberBetween(1, 3);
        }

        if (in_array($status, [MessageStatus::SoftBounced->value, MessageStatus::HardBounced->value], true)) {
            $bouncedAt = $sentAt->copy()->addMinutes(fake()->numberBetween(1, 15));
        }

        if ($status === MessageStatus::Failed->value) {
            $failedAt = $queuedAt->copy()->addSeconds(fake()->numberBetween(1, 30));
        }

        return [$sentAt, $deliveredAt, $openedAt, $clickedAt, $bouncedAt, $failedAt, $openCount, $clickCount];
    }

    private function flushMessages(array $messageRows, array $messageUuids, int $fallbackAccountId): void
    {
        DB::table('messages')->insert($messageRows);

        $idsByUuid = DB::table('messages')
            ->whereIn('uuid', $messageUuids)
            ->pluck('id', 'uuid');

        $attemptRows = [];

        foreach ($messageRows as $row) {
            $messageId = $idsByUuid[$row['uuid']] ?? null;

            if ($messageId === null) {
                continue;
            }

            $succeeded = ! in_array($row['status'], [MessageStatus::Failed->value, MessageStatus::HardBounced->value, MessageStatus::SoftBounced->value], true);
            $attemptedAt = $row['sent_at'] ?? $row['queued_at'];

            $attemptRows[] = [
                'message_id' => $messageId,
                'smtp_account_id' => $row['smtp_account_id'] ?? $fallbackAccountId,
                'attempt_number' => 1,
                'status' => $succeeded ? SendAttemptStatus::Success->value : SendAttemptStatus::Failed->value,
                'provider_response' => $row['last_provider_response'],
                'error_code' => $succeeded ? null : 'SMTP_REJECTED',
                'attempted_at' => $attemptedAt,
                'duration_ms' => fake()->numberBetween(80, 2500),
                'is_test' => false,
            ];

            if (! $succeeded && fake()->boolean(25)) {
                $attemptRows[] = [
                    'message_id' => $messageId,
                    'smtp_account_id' => $row['smtp_account_id'] ?? $fallbackAccountId,
                    'attempt_number' => 2,
                    'status' => SendAttemptStatus::ConnectionError->value,
                    'provider_response' => 'Connection timed out',
                    'error_code' => 'CONNECTION_TIMEOUT',
                    'attempted_at' => Carbon::instance($attemptedAt)->addMinutes(5),
                    'duration_ms' => fake()->numberBetween(2000, 8000),
                    'is_test' => false,
                ];
            }

            if (count($attemptRows) >= 400) {
                DB::table('send_attempts')->insert($attemptRows);
                $attemptRows = [];
            }
        }

        if ($attemptRows !== []) {
            DB::table('send_attempts')->insert($attemptRows);
        }
    }

    // ------------------------------------------------------------------
    // Suppression
    // ------------------------------------------------------------------

    private function seedSuppressionEntries(): void
    {
        $suppressed = array_values(array_filter($this->recipients, fn (array $r) => $r['status'] === RecipientStatus::Suppressed->value));

        $reasons = [
            SuppressionReason::HardBounce, SuppressionReason::ManualUnsubscribe,
            SuppressionReason::SpamComplaint, SuppressionReason::InvalidAddress,
            SuppressionReason::ManualBlock, SuppressionReason::GlobalUnsubscribe,
        ];

        $rows = [];

        foreach ($suppressed as $recipient) {
            $reason = fake()->randomElement($reasons);

            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'email' => $recipient['email'],
                'reason' => $reason->value,
                'source_message_id' => null,
                'notes' => $reason === SuppressionReason::ManualBlock ? 'Ajouté manuellement suite à une demande du client.' : null,
                'added_by' => in_array($reason, [SuppressionReason::ManualUnsubscribe, SuppressionReason::ManualBlock], true) ? $this->randomActiveUserId() : null,
                'created_at' => $this->randomTimestamp(),
            ];
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('suppression_entries')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Verification
    // ------------------------------------------------------------------

    private function seedVerificationResults(): void
    {
        $sample = fake()->randomElements($this->recipients, min(count($this->recipients), 500));
        $rows = [];

        foreach ($sample as $recipient) {
            $isInvalid = $recipient['status'] === RecipientStatus::Invalid->value;

            $verdict = $isInvalid
                ? VerificationVerdict::Undeliverable
                : $this->weightedPick([
                    [VerificationVerdict::Deliverable, 70],
                    [VerificationVerdict::Risky, 15],
                    [VerificationVerdict::Undeliverable, 5],
                    [VerificationVerdict::Unknown, 10],
                ]);

            $checkedAt = $this->randomTimestamp();

            $rows[] = [
                'email' => $recipient['email'],
                'syntax_valid' => true,
                'mx_valid' => $verdict !== VerificationVerdict::Undeliverable,
                'is_disposable' => fake()->boolean(3),
                'is_role_based' => fake()->boolean(10),
                'is_catch_all' => fake()->boolean(8),
                'verdict' => $verdict->value,
                'provider' => fake()->randomElement(['zerobounce', 'neverbounce', 'internal']),
                'raw_response' => json_encode(['score' => fake()->numberBetween(0, 100)]),
                'checked_at' => $checkedAt,
                'expires_at' => Carbon::instance($checkedAt)->addDays(30),
            ];

            if (fake()->boolean(20)) {
                $recheckedAt = Carbon::instance($checkedAt)->addDays(fake()->numberBetween(31, 90))->min($this->periodEnd);

                $rows[] = [
                    'email' => $recipient['email'],
                    'syntax_valid' => true,
                    'mx_valid' => $verdict !== VerificationVerdict::Undeliverable,
                    'is_disposable' => false,
                    'is_role_based' => fake()->boolean(10),
                    'is_catch_all' => fake()->boolean(8),
                    'verdict' => $verdict->value,
                    'provider' => 'internal',
                    'raw_response' => json_encode(['score' => fake()->numberBetween(0, 100)]),
                    'checked_at' => $recheckedAt,
                    'expires_at' => Carbon::instance($recheckedAt)->addDays(30),
                ];
            }
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('verification_results')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Imports
    // ------------------------------------------------------------------

    private function seedImportJobs(): void
    {
        $masterListId = $this->lists['Liste maîtresse — Tous contacts B2B Algérie']['id'];

        for ($i = 0; $i < 12; $i++) {
            $sourceType = fake()->randomElement([ImportSourceType::Csv, ImportSourceType::Excel, ImportSourceType::PageJaunes]);
            $total = fake()->numberBetween(40, 250);
            $errorCount = fake()->numberBetween(0, (int) ($total * 0.1));
            $duplicateCount = fake()->numberBetween(0, (int) ($total * 0.15));
            $imported = max(0, $total - $errorCount - $duplicateCount);
            $startedAt = $this->randomTimestamp();
            $completedAt = $startedAt->copy()->addMinutes(fake()->numberBetween(1, 20));

            $jobId = DB::table('import_jobs')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'user_id' => $this->randomActiveUserId(),
                'source_type' => $sourceType->value,
                'file_path' => $sourceType === ImportSourceType::PageJaunes ? null : 'imports/'.Str::random(20).($sourceType === ImportSourceType::Csv ? '.csv' : '.xlsx'),
                'original_filename' => $sourceType === ImportSourceType::PageJaunes ? null : 'contacts-'.$startedAt->format('Y-m').'.'.($sourceType === ImportSourceType::Csv ? 'csv' : 'xlsx'),
                'status' => ImportJobStatus::Completed->value,
                'total_rows' => $total,
                'processed_rows' => $total,
                'imported_count' => $imported,
                'duplicate_count' => $duplicateCount,
                'error_count' => $errorCount,
                'column_mapping' => json_encode(['email' => 'Email', 'first_name' => 'Prénom', 'last_name' => 'Nom', 'company_name' => 'Société']),
                'target_recipient_list_id' => $masterListId,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'created_at' => $startedAt,
                'updated_at' => $completedAt,
            ]);

            $rowSample = min($total, 25);
            $rowRows = [];

            for ($r = 0; $r < $rowSample; $r++) {
                $validation = $this->weightedPick([
                    [ImportRowValidationStatus::Valid->value, 80],
                    [ImportRowValidationStatus::Duplicate->value, 12],
                    [ImportRowValidationStatus::Invalid->value, 8],
                ]);

                $rowRows[] = [
                    'import_job_id' => $jobId,
                    'row_number' => $r + 1,
                    'raw_data' => json_encode(['email' => fake()->unique()->safeEmail(), 'company' => 'Société '.fake()->numberBetween(1, 999)]),
                    'parsed_email' => $validation === ImportRowValidationStatus::Invalid->value ? null : fake()->safeEmail(),
                    'validation_status' => $validation,
                    'created_recipient_id' => null,
                ];
            }

            DB::table('import_rows')->insert($rowRows);

            if ($errorCount > 0) {
                $errorRows = [];

                for ($e = 0; $e < min($errorCount, 10); $e++) {
                    $errorRows[] = [
                        'import_job_id' => $jobId,
                        'row_number' => fake()->numberBetween(1, $total),
                        'error_code' => fake()->randomElement(['INVALID_EMAIL', 'MISSING_REQUIRED_FIELD', 'DUPLICATE_IN_FILE']),
                        'message' => fake()->randomElement([
                            "L'adresse e-mail est invalide.",
                            'Le champ société est requis.',
                            'Doublon détecté dans le fichier importé.',
                        ]),
                        'created_at' => $completedAt,
                    ];
                }

                DB::table('import_errors')->insert($errorRows);
            }
        }
    }

    // ------------------------------------------------------------------
    // Audit log
    // ------------------------------------------------------------------

    private function seedAuditLogs(): void
    {
        $actions = [
            ['action' => 'campaign.created', 'type' => 'App\\Modules\\Campaigns\\Models\\Campaign'],
            ['action' => 'campaign.sent', 'type' => 'App\\Modules\\Campaigns\\Models\\Campaign'],
            ['action' => 'campaign.paused', 'type' => 'App\\Modules\\Campaigns\\Models\\Campaign'],
            ['action' => 'template.created', 'type' => 'App\\Modules\\Templates\\Models\\Template'],
            ['action' => 'template.updated', 'type' => 'App\\Modules\\Templates\\Models\\Template'],
            ['action' => 'smtp_account.updated', 'type' => 'App\\Modules\\DeliveryEngine\\Models\\SmtpAccount'],
            ['action' => 'recipient.imported', 'type' => 'App\\Modules\\Importing\\Models\\ImportJob'],
            ['action' => 'suppression.added_manual', 'type' => 'App\\Modules\\Suppression\\Models\\SuppressionEntry'],
            ['action' => 'user.role_changed', 'type' => 'App\\Modules\\Identity\\Models\\User'],
            ['action' => 'user.login', 'type' => 'App\\Modules\\Identity\\Models\\User'],
            ['action' => 'settings.branding_updated', 'type' => 'App\\Modules\\Settings\\Models\\Setting'],
        ];

        $rows = [];

        for ($i = 0; $i < 400; $i++) {
            $entry = fake()->randomElement($actions);

            $rows[] = [
                'user_id' => fake()->boolean(95) ? $this->randomActiveUserId() : null,
                'action' => $entry['action'],
                'auditable_type' => $entry['type'],
                'auditable_id' => fake()->numberBetween(1, self::CAMPAIGN_COUNT),
                'old_values' => json_encode(['status' => 'draft']),
                'new_values' => json_encode(['status' => 'sent']),
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'created_at' => $this->randomTimestamp(),
            ];
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('audit_logs')->insert($chunk);
        }
    }
}

