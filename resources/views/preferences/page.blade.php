<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $content['title'] ?? '' }}</title>
    <style>
        body { margin: 0; padding: 24px 16px; background: {{ $design['background'] }}; color: {{ $design['text'] }}; font-family: {!! $design["font"] !!}; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; padding: 32px; border-radius: {{ $design['radius'] }}px; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
        .logo { max-height: 56px; max-width: 220px; display: block; margin: 0 auto 24px; }
        h1 { font-size: 22px; margin: 0 0 12px; }
        label.row { display: flex; gap: 8px; align-items: center; margin: 8px 0; }
        label.field { display: block; margin: 12px 0; }
        input[type=text], input[type=email] { width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #c8c8c8; border-radius: {{ $design['radius'] }}px; }
        button { margin-top: 20px; padding: 10px 20px; border: 0; cursor: pointer; background: {{ $design['primary'] }}; color: {{ $design['button_text'] }}; border-radius: {{ $design['radius'] }}px; font-size: 15px; }
        .notice { padding: 10px 12px; margin-bottom: 16px; background: #eef6ee; border-left: 4px solid {{ $design['primary'] }}; }
        .error { color: #b10e1c; font-size: 13px; }
        .muted { color: #616161; font-size: 13px; }
        h2 { font-size: 16px; margin: 20px 0 4px; }
    </style>
</head>
<body>
<main class="card">
    @if ($design['logo'])
        <img class="logo" src="{{ $design['logo'] }}" alt="{{ $design['organization'] }}">
    @endif
    <h1>{{ $content['title'] ?? '' }}</h1>

    @if (! empty($message))
        <p class="notice" role="status">{{ $message }}</p>
    @endif

    @if ($mode === 'preferences')
        @if (! empty($content['intro']))
            <p>{{ $content['intro'] }}</p>
        @endif
        <p class="muted">{{ $email }}</p>
        <form method="POST" action="{{ $action }}">
            @unless ($preview ?? false)
                @csrf
                @foreach ($errors->all() as $error)
                    <p class="error">{{ $error }}</p>
                @endforeach
            @endunless
            @foreach ($fields as $field)
                <label class="field">
                    {{ $field['label'] }}@if (! empty($field['required'])) *@endif
                    <input type="text" name="attributes[{{ $field['attribute'] }}]"
                           value="{{ ($preview ?? false) ? ($values[$field['attribute']] ?? '') : old('attributes.'.$field['attribute'], $values[$field['attribute']] ?? '') }}"
                           @if (! empty($field['required'])) required @endif>
                </label>
            @endforeach
            @if (count($lists) > 0)
                <h2>Mes abonnements</h2>
                @foreach ($lists as $list)
                    <label class="row">
                        <input type="checkbox" name="list_ids[]" value="{{ $list['id'] }}" @checked($list['subscribed'])>
                        {{ $list['name'] }}
                    </label>
                @endforeach
            @endif
            <input type="hidden" name="unsubscribe_all" value="0">
            <label class="row">
                <input type="checkbox" name="unsubscribe_all" value="1">
                Me désabonner de toutes les communications
            </label>
            <button type="submit">{{ $content['submit_label'] ?? 'Enregistrer' }}</button>
        </form>
    @elseif ($mode === 'unsubscribe')
        @if (! empty($content['body']))
            <p>{{ $content['body'] }}</p>
        @endif
        @if (! empty($action))
            <p class="muted">{{ $email ?? '' }}</p>
            <form method="POST" action="{{ $action }}">
                @csrf
                @if (! empty($content['show_reason_survey']) && ! empty($content['reasons']))
                    @foreach ($content['reasons'] as $reason)
                        <label class="row"><input type="radio" name="reason" value="{{ $reason }}"> {{ $reason }}</label>
                    @endforeach
                    <label class="field">Précisions (facultatif)
                        <input type="text" name="reason_other" maxlength="500">
                    </label>
                @endif
                <button type="submit">{{ $content['button_label'] }}</button>
            </form>
        @else
            <button type="button" disabled>{{ $content['button_label'] }}</button>
        @endif
    @elseif ($mode === 'confirm')
        <form method="POST" action="{{ $action }}">
            @csrf
            <button type="submit">{{ $content['button_label'] }}</button>
        </form>
    @endif
</main>
</body>
</html>
