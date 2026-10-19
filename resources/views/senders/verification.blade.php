<!doctype html>
<html lang="fr">
<body style="font-family: sans-serif">
    <p>Bonjour,</p>
    <p>Vous avez ajouté l'adresse <strong>{{ $sender->email }}</strong> ({{ $sender->name }}) comme expéditeur.</p>
    <p>Pour la confirmer, cliquez sur le lien suivant (valable 48 heures) :</p>
    <p><a href="{{ $verificationUrl }}">Confirmer cette adresse</a></p>
    <p>Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.</p>
</body>
</html>
