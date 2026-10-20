<?php

namespace Database\Seeders;

use App\Domain\Enums\MessageStatus;
use App\Domain\Enums\RecipientListType;
use App\Domain\Enums\RecipientSource;
use App\Domain\Enums\RecipientStatus;
use App\Domain\Enums\RoleName;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\PageJaunesIntegration\Models\PageJaunesCompanyCache;
use App\Modules\Recipients\Lists\Contracts\ListMembershipService;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Recipients\Models\Segment;
use App\Modules\Senders\Models\Sender;
use App\Modules\Templates\Models\Template;
use App\Modules\Tracking\Models\Message;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Fixtures for the end-to-end smoke suite (tests/e2e). NOT part of
 * DatabaseSeeder: run explicitly with
 * `php artisan db:seed --class=E2eSeeder` on a throw-away database.
 *
 * Knobs (all optional env vars): E2E_TOTP_SECRET (base32 TOTP secret of the
 * admin), E2E_SMTP_HOST / E2E_SMTP_PORT (where the local SMTP sink listens).
 * Idempotent: safe to run twice.
 */
class E2eSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'e2e-admin@pagejaunes-mailer.local';

    public const ADMIN_PASSWORD = 'password';

    public const DEFAULT_TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public const SENDER_EMAIL = 'e2e-sender@pagejaunes.test';

    public const REPLY_TO = 'e2e-reply@pagejaunes.test';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('E2eSeeder refuses to run when APP_ENV=production.');
        }

        // Reference data normally laid down by DatabaseSeeder; only fill the gaps
        // so the seeder also works standalone.
        if (! Role::query()->where('name', RoleName::Administrator->value)->exists()) {
            $this->call([RolePermissionSeeder::class, SettingsSeeder::class, ContactAttributeSeeder::class]);
        }
        if (! Template::query()->where('is_gallery', true)->exists()) {
            $this->call(TemplateGallerySeeder::class);
        }

        DB::transaction(function (): void {
            $admin = $this->seedAdmin();
            $this->seedSender($admin);
            $smtp = $this->seedSmtpAccount();
            $contacts = $this->seedContacts();
            $this->seedLists($admin, $contacts);
            $this->seedSegment($admin);
            $this->seedSentCampaign($admin, $smtp, $contacts);
        });
    }

    private function seedAdmin(): User
    {
        $role = Role::query()->where('name', RoleName::Administrator->value)->firstOrFail();

        $user = User::query()->firstOrNew(['email' => self::ADMIN_EMAIL]);
        $user->fill(['name' => 'Admin E2E', 'role_id' => $role->id, 'is_active' => true]);
        $user->forceFill([
            'password' => Hash::make(self::ADMIN_PASSWORD),
            'email_verified_at' => now(),
            'two_factor_secret' => (string) (env('E2E_TOTP_SECRET') ?: self::DEFAULT_TOTP_SECRET),
            'two_factor_recovery_codes' => ['E2E00-00001', 'E2E00-00002'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function seedSender(User $admin): void
    {
        Sender::query()->updateOrCreate(['email' => self::SENDER_EMAIL], [
            'name' => 'Équipe E2E',
            'reply_to' => self::REPLY_TO,
            'is_default' => false,
            'verification_method' => 'system',
            'verified_at' => now(),
            'created_by' => $admin->id,
        ]);
    }

    private function seedSmtpAccount(): SmtpAccount
    {
        return SmtpAccount::query()->updateOrCreate(['name' => 'Puits SMTP E2E'], [
            'provider' => 'custom',
            'host' => (string) (env('E2E_SMTP_HOST') ?: '127.0.0.1'),
            'port' => (int) (env('E2E_SMTP_PORT') ?: 2526),
            'encryption' => 'none',
            'username' => 'e2e',
            'password_encrypted' => 'e2e',
            'from_email' => self::SENDER_EMAIL,
            'from_name' => 'Équipe E2E',
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, Recipient> keyed by local part
     */
    private function seedContacts(): array
    {
        $company = PageJaunesCompanyCache::query()->updateOrCreate(['pagejaunes_company_id' => 900001], [
            'pagejaunes_code' => 'E2E900001',
            'company_name' => 'Société E2E Alger',
            'wilaya_label' => 'Alger',
            'locality_label' => 'Bab Ezzouar',
            'country_label' => 'Algérie',
            'synced_at' => now(),
        ]);

        $rows = [
            // [local part, first name, last name, company, in Alger wilaya?]
            ['nadia', 'Nadia', 'Boukhalfa', 'BTP Alger', true],
            ['karim', 'Karim', 'Belhadj', 'Atlas Négoce', false],
            ['amel', 'Amel', 'Meziane', 'Sahara Logistique', false],
            ['yacine', 'Yacine', 'Hamidi', 'Numidia Industrie', false],
            ['sofiane', 'Sofiane', 'Rahmani', 'Hoggar Solaire', false],
            ['leila', 'Leila', 'Toumi', 'Tassili Textiles', false],
            ['riad', 'Riad', 'Ouali', 'Cirta Distribution', false],
            ['samira', 'Samira', 'Haddad', 'Soummam Emballages', false],
        ];

        $contacts = [];
        foreach ($rows as [$local, $first, $last, $companyName, $inAlger]) {
            $contacts[$local] = Recipient::query()->updateOrCreate(['email' => "{$local}@e2e.test"], [
                'first_name' => $first,
                'last_name' => $last,
                'company_name' => $companyName,
                'source' => RecipientSource::Manual->value,
                'status' => RecipientStatus::Active->value,
                'pagejaunes_company_cache_id' => $inAlger ? $company->id : null,
            ]);
        }

        return $contacts;
    }

    /**
     * @param  array<string, Recipient>  $contacts
     */
    private function seedLists(User $admin, array $contacts): void
    {
        $lists = [
            'E2E Liste Alpha' => ['nadia', 'karim', 'amel'],
            'E2E Liste Bravo' => ['yacine', 'sofiane', 'nadia'],
            'E2E Liste Charlie' => ['leila', 'riad', 'samira', 'karim'],
        ];

        $memberships = app(ListMembershipService::class);
        foreach ($lists as $name => $members) {
            $list = RecipientList::query()->updateOrCreate(['name' => $name], [
                'description' => 'Liste de test de bout en bout',
                'type' => RecipientListType::Static->value,
                'created_by' => $admin->id,
            ]);
            $memberships->add($list, array_map(fn (string $m): int => $contacts[$m]->id, $members), 'manual');
            $memberships->recount($list);
        }
    }

    private function seedSegment(User $admin): void
    {
        Segment::query()->updateOrCreate(['name' => 'E2E Segment e2e.test'], [
            'description' => 'Contacts dont l\'adresse finit par @e2e.test',
            'rules' => [
                'version' => 2,
                'type' => 'group',
                'operator' => 'and',
                'children' => [
                    ['type' => 'condition', 'field' => 'attribute.EMAIL', 'operator' => 'ends_with', 'value' => '@e2e.test'],
                ],
            ],
            'rules_version' => 2,
            'created_by' => $admin->id,
        ]);
    }

    /**
     * A completed campaign with delivered / opened / clicked / bounced
     * messages so the report page has something to chart.
     *
     * @param  array<string, Recipient>  $contacts
     */
    private function seedSentCampaign(User $admin, SmtpAccount $smtp, array $contacts): void
    {
        $sender = Sender::query()->where('email', self::SENDER_EMAIL)->firstOrFail();
        $sentAt = now()->subDays(3);

        $campaign = Campaign::query()->firstOrNew(['name' => 'E2E Campagne envoyée']);
        if ($campaign->exists) {
            return;
        }

        $campaign->fill([
            'type' => 'classic',
            'subject' => 'Votre rapport mensuel E2E',
            'html_body' => '<p>Bonjour {{recipient.first_name|there}}</p><a href="{{unsubscribe_url}}">Se désinscrire</a>',
            'status' => CampaignStatus::Completed->value,
            'sender_id' => $sender->id,
            'created_by' => $admin->id,
            'recipient_list_id' => RecipientList::query()->where('name', 'E2E Liste Alpha')->value('id'),
        ]);
        $campaign->forceFill([
            'sent_at' => $sentAt,
            'completed_at' => $sentAt->copy()->addMinutes(5),
        ])->save();

        $plan = [
            ['nadia', MessageStatus::Clicked, 1, 2],
            ['karim', MessageStatus::Opened, 2, 0],
            ['amel', MessageStatus::Delivered, 0, 0],
            ['yacine', MessageStatus::HardBounced, 0, 0],
            ['sofiane', MessageStatus::Delivered, 0, 0],
        ];

        $counters = ['sent' => 0, 'delivered' => 0, 'opens' => 0, 'clicks' => 0, 'bounces' => 0];
        foreach ($plan as [$local, $status, $opens, $clicks]) {
            $bounced = $status === MessageStatus::HardBounced;
            $message = new Message([
                'campaign_id' => $campaign->id,
                'recipient_id' => $contacts[$local]->id,
                'smtp_account_id' => $smtp->id,
                'subject' => 'Votre rapport mensuel E2E',
                'html_body' => '<p>Bonjour '.$contacts[$local]->first_name.'</p>',
                'status' => $status->value,
                'queued_at' => $sentAt,
            ]);
            $message->forceFill([
                'sent_at' => $sentAt,
                'delivered_at' => $bounced ? null : $sentAt->copy()->addMinute(),
                'opened_at' => $opens > 0 ? $sentAt->copy()->addHour() : null,
                'clicked_at' => $clicks > 0 ? $sentAt->copy()->addHours(2) : null,
                'bounced_at' => $bounced ? $sentAt->copy()->addMinute() : null,
                'open_count' => $opens,
                'click_count' => $clicks,
            ])->save();

            $counters['sent']++;
            $counters['delivered'] += $bounced ? 0 : 1;
            $counters['opens'] += $opens;
            $counters['clicks'] += $clicks;
            $counters['bounces'] += $bounced ? 1 : 0;
        }

        $campaign->forceFill([
            'recipients_count' => $counters['sent'],
            'sent_count' => $counters['sent'],
            'delivered_count' => $counters['delivered'],
            'unique_opens' => 2,
            'total_opens' => $counters['opens'],
            'unique_clicks' => 1,
            'total_clicks' => $counters['clicks'],
            'hard_bounces' => $counters['bounces'],
        ])->save();
    }
}
