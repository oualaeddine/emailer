<?php

namespace Tests\Feature\Modules\Suppression\Preferences;

use App\Modules\Recipients\Models\Recipient;
use App\Modules\Suppression\Preferences\Mail\DoubleOptInMail;
use App\Modules\Suppression\Preferences\Services\DoubleOptInService;
use App\Modules\Suppression\Preferences\Services\PreferenceTokenService;
use Database\Seeders\DefaultPreferencePagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DoubleOptInTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_mails_a_signed_confirmation_link(): void
    {
        Mail::fake();
        $recipient = Recipient::factory()->create(['email' => 'nadia@exemple.dz']);

        $url = app(DoubleOptInService::class)->send($recipient);

        $this->assertStringContainsString('/preferences/', $url);
        $this->assertStringContainsString('/confirm', $url);
        Mail::assertQueued(DoubleOptInMail::class, fn (DoubleOptInMail $m) => $m->hasTo('nadia@exemple.dz') && $m->confirmationUrl === $url);
    }

    public function test_seeded_template_is_rendered_with_the_link(): void
    {
        $this->seed(DefaultPreferencePagesSeeder::class);
        Mail::fake();
        $recipient = Recipient::factory()->create(['email' => 'nadia@exemple.dz']);

        $url = app(DoubleOptInService::class)->send($recipient);

        Mail::assertQueued(DoubleOptInMail::class, function (DoubleOptInMail $m) use ($url) {
            $this->assertNotNull($m->bodyHtml);
            $this->assertStringContainsString(e($url), $m->bodyHtml);
            $this->assertStringNotContainsString('{{ doi_link }}', $m->bodyHtml);

            return true;
        });
    }

    public function test_mailable_renders_the_blade_view_without_template(): void
    {
        $html = (new DoubleOptInMail('https://exemple.dz/preferences/x/confirm'))->render();

        $this->assertStringContainsString('https://exemple.dz/preferences/x/confirm', $html);
    }

    public function test_confirm_sets_opt_in_columns_once(): void
    {
        $recipient = Recipient::factory()->create(['email' => 'nadia@exemple.dz']);
        $url = app(PreferenceTokenService::class)->confirmUrl($recipient->email);

        $this->get($url)->assertOk()->assertSee('confirmée');

        $recipient->refresh();
        $this->assertNotNull($recipient->opt_in_at);
        $this->assertSame('double_opt_in', $recipient->opt_in_source);
        $first = $recipient->opt_in_at;

        $this->travel(1)->hours();
        $this->get($url)->assertOk();
        $this->assertEquals($first, $recipient->fresh()->opt_in_at);
    }

    public function test_confirm_rejects_tampered_expired_and_unknown_links(): void
    {
        $recipient = Recipient::factory()->create(['email' => 'nadia@exemple.dz']);
        $url = app(PreferenceTokenService::class)->confirmUrl($recipient->email);

        $this->get($url.'0')->assertForbidden();
        $expired = URL::temporarySignedRoute('preferences.confirm', now()->subMinute(), ['token' => \App\Modules\Suppression\Support\UnsubscribeUrlGenerator::encode($recipient->email)]);
        $this->get($expired)->assertForbidden();
        $this->get(app(PreferenceTokenService::class)->confirmUrl('absent@exemple.dz'))->assertNotFound();
        $this->assertNull($recipient->fresh()->opt_in_at);
    }
}
