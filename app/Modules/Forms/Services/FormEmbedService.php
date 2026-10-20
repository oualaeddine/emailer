<?php

namespace App\Modules\Forms\Services;

use App\Modules\Forms\Models\Form;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Settings\Services\SettingsService;

/** Builds the `embed.js` script and the typed field list shared by the API resource and the Blade page. */
class FormEmbedService
{
    public function __construct(
        private readonly ContactAttributeService $attributes,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Fields with their type / options derived from the attribute definitions (unknown attributes dropped).
     *
     * @return list<array<string, mixed>>
     */
    public function fields(Form $form): array
    {
        $definitions = $this->attributes->definitions();
        $out = [];
        foreach ($form->fields as $field) {
            $name = strtoupper((string) $field['attribute']);
            $def = $name === 'EMAIL' ? null : $definitions->get($name);
            if ($name !== 'EMAIL' && $def === null) {
                continue;
            }
            $type = $name === 'EMAIL' ? 'email' : $def->type;
            $out[] = [
                'attribute' => $name,
                'label' => $field['label'] ?? $name,
                'placeholder' => $field['placeholder'] ?? null,
                'required' => $name === 'EMAIL' ? true : (bool) ($field['required'] ?? false),
                'help_text' => $field['help_text'] ?? null,
                'type' => $type,
            ] + ($def && $def->isChoice() ? ['options' => $def->options ?? []] : []);
        }

        return $out;
    }

    /** @return array{provider: string, site_key: string}|null */
    public function captcha(Form $form): ?array
    {
        $provider = (string) $this->settings->get('forms.captcha_provider', 'none');
        if (empty($form->settings['captcha_enabled']) || ! isset(HttpCaptchaVerifier::ENDPOINTS[$provider])) {
            return null;
        }

        return ['provider' => $provider, 'site_key' => (string) $this->settings->get('forms.captcha_site_key', '')];
    }

    public function script(Form $form): string
    {
        $design = $form->design ?? [];
        $config = [
            'id' => $form->uuid,
            'action' => url('/f/'.$form->uuid),
            'title' => $design['title'] ?? null,
            'description' => $design['description'] ?? null,
            'button' => $design['button_text'] ?? "S'inscrire",
            'primary' => $design['primary_color'] ?? '#0F6CBD',
            'text' => $design['text_color'] ?? '#242424',
            'buttonText' => $design['button_text_color'] ?? '#FFFFFF',
            'radius' => (int) ($design['border_radius'] ?? 4),
            'font' => preg_replace('/[^A-Za-z0-9 ,_-]/', '', (string) ($design['font_family'] ?? 'sans-serif')),
            'fields' => $this->fields($form),
            'gdpr' => ! empty($form->gdpr['enabled']) ? ['text' => $form->gdpr['text'] ?? '', 'required' => (bool) ($form->gdpr['required'] ?? true)] : null,
            'captcha' => $this->captcha($form),
        ];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

        return <<<JS
(function () {
  var cfg = {$json};
  function el(tag, attrs, text) {
    var n = document.createElement(tag);
    for (var k in (attrs || {})) { n.setAttribute(k, attrs[k]); }
    if (text) { n.textContent = text; }
    return n;
  }
  function build() {
    var hosts = document.querySelectorAll('[data-pjm-form="' + cfg.id + '"]');
    Array.prototype.forEach.call(hosts, function (host) {
      if (host.getAttribute('data-pjm-ready')) { return; }
      host.setAttribute('data-pjm-ready', '1');
      host.style.fontFamily = cfg.font; host.style.color = cfg.text;
      var form = el('form', { method: 'POST', action: cfg.action });
      if (cfg.title) { form.appendChild(el('h3', {}, cfg.title)); }
      if (cfg.description) { form.appendChild(el('p', {}, cfg.description)); }
      cfg.fields.forEach(function (f) {
        var wrap = el('label', { style: 'display:block;margin:10px 0' }, f.label + (f.required ? ' *' : ''));
        var name = 'attributes[' + f.attribute + ']';
        var input;
        if (f.type === 'category' || f.type === 'multiple_choice') {
          input = el('select', { name: f.type === 'multiple_choice' ? name + '[]' : name });
          if (f.type === 'multiple_choice') { input.setAttribute('multiple', 'multiple'); }
          else { input.appendChild(el('option', { value: '' }, '')); }
          (f.options || []).forEach(function (o) { input.appendChild(el('option', { value: o.value }, o.label)); });
        } else if (f.type === 'boolean') {
          input = el('input', { type: 'checkbox', name: name, value: '1' });
        } else {
          var t = { email: 'email', number: 'number', date: 'date' }[f.type] || 'text';
          input = el('input', { type: t, name: name });
          if (f.placeholder) { input.setAttribute('placeholder', f.placeholder); }
        }
        if (f.required) { input.setAttribute('required', 'required'); }
        input.style.display = 'block'; input.style.width = f.type === 'boolean' ? 'auto' : '100%';
        input.style.boxSizing = 'border-box'; input.style.borderRadius = cfg.radius + 'px';
        wrap.appendChild(input);
        if (f.help_text) { wrap.appendChild(el('small', {}, f.help_text)); }
        form.appendChild(wrap);
      });
      if (cfg.gdpr) {
        var g = el('label', { style: 'display:block;margin:10px 0' });
        var c = el('input', { type: 'checkbox', name: 'gdpr_consent', value: '1' });
        if (cfg.gdpr.required) { c.setAttribute('required', 'required'); }
        g.appendChild(c); g.appendChild(document.createTextNode(' ' + cfg.gdpr.text));
        form.appendChild(g);
      }
      var hp = el('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true' });
      hp.style.cssText = 'position:absolute;left:-9999px;opacity:0;height:0;width:0';
      form.appendChild(hp);
      if (cfg.captcha) {
        var cls = cfg.captcha.provider === 'hcaptcha' ? 'h-captcha' : 'cf-turnstile';
        form.appendChild(el('div', { 'class': cls, 'data-sitekey': cfg.captcha.site_key }));
        var s = el('script', { src: cfg.captcha.provider === 'hcaptcha' ? 'https://js.hcaptcha.com/1/api.js' : 'https://challenges.cloudflare.com/turnstile/v0/api.js', async: 'async', defer: 'defer' });
        document.head.appendChild(s);
      }
      var btn = el('button', { type: 'submit' }, cfg.button);
      btn.style.cssText = 'margin-top:12px;padding:10px 20px;border:0;cursor:pointer;background:' + cfg.primary + ';color:' + cfg.buttonText + ';border-radius:' + cfg.radius + 'px';
      form.appendChild(btn);
      host.appendChild(form);
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', build); } else { build(); }
})();
JS;
    }
}
