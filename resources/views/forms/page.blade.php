<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <style>
        body { margin: 0; padding: 24px 16px; background: {{ $design['background'] }}; color: {{ $design['text'] }}; font-family: {!! $design['font'] !!}; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; padding: 32px; border-radius: {{ $design['radius'] }}px; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
        .logo { max-height: 56px; max-width: 220px; display: block; margin: 0 auto 24px; }
        h1 { font-size: 22px; margin: 0 0 12px; }
        label.field { display: block; margin: 12px 0; }
        label.row { display: flex; gap: 8px; align-items: flex-start; margin: 12px 0; }
        input[type=text], input[type=email], input[type=number], input[type=date], select { width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #c8c8c8; border-radius: {{ $design['radius'] }}px; }
        button { margin-top: 20px; padding: 10px 20px; border: 0; cursor: pointer; background: {{ $design['primary'] }}; color: {{ $design['button_text'] }}; border-radius: {{ $design['radius'] }}px; font-size: 15px; }
        .notice { padding: 10px 12px; background: #eef6ee; border-left: 4px solid {{ $design['primary'] }}; }
        .error { color: #b10e1c; font-size: 13px; margin: 4px 0; }
        .muted { color: #616161; font-size: 13px; }
        .hp { position: absolute; left: -9999px; opacity: 0; height: 0; width: 0; }
    </style>
</head>
<body>
<main class="card">
    @if ($design['logo'])
        <img class="logo" src="{{ $design['logo'] }}" alt="{{ $design['organization'] }}">
    @endif

    @if ($thanks)
        <h1>{{ $title }}</h1>
        <p class="notice" role="status">{{ $message }}</p>
    @else
        <h1>{{ $title }}</h1>
        @if ($description)
            <p>{{ $description }}</p>
        @endif
        @foreach ($errors->all() as $error)
            <p class="error" role="alert">{{ $error }}</p>
        @endforeach
        <form method="POST" action="{{ $action }}">
            @csrf
            @foreach ($fields as $field)
                @php($name = 'attributes['.$field['attribute'].']')
                <label class="field">
                    {{ $field['label'] }}@if ($field['required']) *@endif
                    @if ($field['type'] === 'category')
                        <select name="{{ $name }}" @required($field['required'])>
                            <option value=""></option>
                            @foreach ($field['options'] ?? [] as $option)
                                <option value="{{ $option['value'] }}" @selected(old('attributes.'.$field['attribute']) === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @elseif ($field['type'] === 'multiple_choice')
                        <select name="{{ $name }}[]" multiple @required($field['required'])>
                            @foreach ($field['options'] ?? [] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @elseif ($field['type'] === 'boolean')
                        <input type="checkbox" name="{{ $name }}" value="1" @checked(old('attributes.'.$field['attribute'])) @required($field['required'])>
                    @else
                        <input type="{{ ['email' => 'email', 'number' => 'number', 'date' => 'date'][$field['type']] ?? 'text' }}"
                               name="{{ $name }}" value="{{ old('attributes.'.$field['attribute']) }}"
                               @if ($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endif
                               @required($field['required'])>
                    @endif
                    @if ($field['help_text'])<span class="muted">{{ $field['help_text'] }}</span>@endif
                </label>
            @endforeach

            @if (! empty($gdpr['enabled']))
                <label class="row">
                    <input type="checkbox" name="gdpr_consent" value="1" @required(! empty($gdpr['required']))>
                    <span>{{ $gdpr['text'] }}</span>
                </label>
            @endif

            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

            @if ($captcha)
                <div class="{{ $captcha['provider'] === 'hcaptcha' ? 'h-captcha' : 'cf-turnstile' }}" data-sitekey="{{ $captcha['site_key'] }}"></div>
                <script src="{{ $captcha['provider'] === 'hcaptcha' ? 'https://js.hcaptcha.com/1/api.js' : 'https://challenges.cloudflare.com/turnstile/v0/api.js' }}" async defer></script>
            @endif

            <button type="submit">{{ $button }}</button>
        </form>
    @endif
</main>
</body>
</html>
