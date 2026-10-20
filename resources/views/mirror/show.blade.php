@extends('mirror.layout')

@section('title', $subject !== '' ? $subject : 'Version en ligne')

@section('content')
    <header class="mirror-bar">
        <small>Version en ligne de l'e-mail</small>
        <strong>{{ $subject }}</strong>
    </header>
    <iframe
        class="mirror-frame"
        title="{{ $subject !== '' ? $subject : 'E-mail' }}"
        sandbox="allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"
        referrerpolicy="no-referrer"
        srcdoc="{{ $html }}"
    ></iframe>
@endsection
