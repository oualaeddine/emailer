<!doctype html>
<html lang="fr">
<body style="font-family: Segoe UI, Arial, sans-serif; color: #242424;">
    <p>Bonjour,</p>
    <p>Une nouvelle inscription a été reçue via le formulaire « {{ $formName }} ».</p>
    <p><strong>Statut du contact :</strong> {{ $statusLabel }}</p>
    <table style="border-collapse: collapse;">
        @foreach ($values as $row)
            <tr>
                <td style="padding: 4px 16px 4px 0; color: #616161;">{{ $row['label'] }}</td>
                <td style="padding: 4px 0;">{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </table>
    @if ($contactUrl)
        <p><a href="{{ $contactUrl }}">Ouvrir le contact dans l’application</a></p>
    @endif
</body>
</html>
