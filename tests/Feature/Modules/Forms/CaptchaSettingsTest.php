<?php

namespace Tests\Feature\Modules\Forms;

use App\Domain\Enums\RoleName;
use App\Modules\Forms\Contracts\CaptchaVerifier;
use App\Modules\Forms\Services\HttpCaptchaVerifier;
use App\Modules\Forms\Services\NullCaptcha;
use App\Modules\Forms\Services\SettingsCaptchaVerifier;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** gap-forms — GET / PUT /api/v1/forms/captcha-settings. */
class CaptchaSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/forms/captcha-settings';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Administrator)->create();
    }

    public function test_only_settings_managers_can_read_or_write(): void
    {
        $manager = User::factory()->withRole(RoleName::MarketingManager)->create();
        $payload = ['provider' => 'turnstile', 'site_key' => 'site', 'secret' => 'secret'];

        $this->actingAs($manager)->getJson(self::URL)->assertForbidden();
        $this->actingAs($manager)->putJson(self::URL, $payload)->assertForbidden();
        $this->assertDatabaseMissing('settings', ['key' => 'forms.captcha_provider']);
    }

    public function test_defaults_to_none_without_a_secret(): void
    {
        $this->actingAs($this->admin())->getJson(self::URL)->assertOk()
            ->assertExactJson(['data' => ['provider' => 'none', 'site_key' => '', 'has_secret' => false]]);
    }

    public function test_provider_must_be_known_and_keys_are_required_with_a_provider(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'recaptcha'])->assertUnprocessable()->assertJsonValidationErrors(['provider']);
        $this->actingAs($admin)->putJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors(['provider']);
        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'hcaptcha'])->assertUnprocessable()->assertJsonValidationErrors(['site_key', 'secret']);
        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'hcaptcha', 'site_key' => 'k'])->assertUnprocessable()->assertJsonValidationErrors(['secret'])
            ->assertJsonMissingValidationErrors(['site_key']);
        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'none'])->assertOk()->assertJsonPath('data.provider', 'none');
    }

    public function test_the_secret_is_stored_encrypted_and_never_returned(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->putJson(self::URL, ['provider' => 'turnstile', 'site_key' => 'site-123', 'secret' => 'sup3r-secret'])
            ->assertOk()
            ->assertExactJson(['data' => ['provider' => 'turnstile', 'site_key' => 'site-123', 'has_secret' => true]]);
        $this->assertStringNotContainsString('sup3r-secret', $response->getContent());
        $this->assertStringNotContainsString('sup3r-secret', $this->actingAs($admin)->getJson(self::URL)->getContent());

        $row = DB::table('settings')->where('key', 'forms.captcha_secret')->first();
        $this->assertSame(1, (int) $row->is_secret);
        $this->assertStringNotContainsString('sup3r-secret', (string) $row->value);

        // The generic settings listing redacts it too.
        $this->actingAs($admin)->getJson('/api/v1/settings')->assertJsonFragment(['key' => 'forms.captcha_secret', 'value' => null, 'is_secret' => true]);
        // The audit trail never carries the secret.
        $this->assertStringNotContainsString('sup3r-secret', (string) DB::table('audit_logs')->get()->toJson());
    }

    public function test_an_empty_secret_keeps_the_stored_one_and_a_new_one_replaces_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'hcaptcha', 'site_key' => 'a', 'secret' => 'first'])->assertOk();

        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'hcaptcha', 'site_key' => 'b', 'secret' => ''])->assertOk()
            ->assertJsonPath('data.site_key', 'b')->assertJsonPath('data.has_secret', true);
        $this->assertSame('first', $this->verifierSecret());

        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'hcaptcha', 'site_key' => 'b', 'secret' => 'second'])->assertOk();
        $this->assertSame('second', $this->verifierSecret());
    }

    public function test_the_verifier_reads_the_encrypted_secret_and_the_provider(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);
        $this->actingAs($this->admin())->putJson(self::URL, ['provider' => 'turnstile', 'site_key' => 'site', 'secret' => 'sup3r-secret'])->assertOk();

        $this->assertTrue(app(SettingsCaptchaVerifier::class)->verify('token', '203.0.113.9'));

        Http::assertSent(fn ($request) => $request->url() === HttpCaptchaVerifier::ENDPOINTS['turnstile']
            && ($request['secret'] ?? null) === 'sup3r-secret' && ($request['response'] ?? null) === 'token');
    }

    public function test_provider_none_disables_the_verification(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'turnstile', 'site_key' => 'site', 'secret' => 's'])->assertOk();
        $this->actingAs($admin)->putJson(self::URL, ['provider' => 'none'])->assertOk()->assertJsonPath('data.provider', 'none');

        Http::fake();
        $this->assertTrue(app(SettingsCaptchaVerifier::class)->verify(null, null));
        Http::assertNothingSent();
        $this->assertInstanceOf(CaptchaVerifier::class, new NullCaptcha);
    }

    private function verifierSecret(): string
    {
        return (string) app(\App\Modules\Settings\Services\SettingsService::class)->get('forms.captcha_secret');
    }
}
