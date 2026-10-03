<?php
// Enregistre chaque inscription dans donnees/inscriptions.csv (dossier protégé).
// Pour être prévenu par e-mail à chaque inscription, mets ton adresse ci-dessous.
// Laisse vide pour ne rien envoyer.
const EMAIL_ADMIN = '';

function retour(string $etat): void {
    header('Location: index.html?inscription=' . $etat . '#inscription');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') retour('erreur');
if (!empty($_POST['site'])) retour('ok'); // anti-robot : champ piège rempli

function propre(string $cle, int $max): string {
    $v = trim((string)($_POST[$cle] ?? ''));
    $v = preg_replace('/[\r\n\t]+/', ' ', $v);
    $v = mb_substr($v, 0, $max);
    // empêche l'interprétation comme formule dans Excel
    if ($v !== '' && strpos('=+-@', $v[0]) !== false) $v = "'" . $v;
    return $v;
}

$prenom  = propre('prenom', 80);
$nom     = propre('nom', 80);
$email   = propre('email', 120);
$classe  = propre('classe', 40);
$message = propre('message', 500);

if ($prenom === '' || $nom === '' || $classe === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    ($_POST['accord'] ?? '') !== 'oui') retour('erreur');

$dossier = __DIR__ . '/donnees';
if (!is_dir($dossier)) {
    mkdir($dossier, 0750, true);
    file_put_contents($dossier . '/.htaccess', "Require all denied\nDeny from all\n");
    file_put_contents($dossier . '/index.html', '');
}

$fichier = $dossier . '/inscriptions.csv';
$neuf = !file_exists($fichier);
$f = fopen($fichier, 'a');
if (!$f) retour('erreur');
flock($f, LOCK_EX);
if ($neuf) fwrite($f, "\xEF\xBB\xBF"); // pour que Excel lise les accents
if ($neuf) fputcsv($f, ['date', 'prenom', 'nom', 'email', 'classe', 'message']);
fputcsv($f, [date('Y-m-d H:i:s'), $prenom, $nom, $email, $classe, $message]);
flock($f, LOCK_UN);
fclose($f);

if (EMAIL_ADMIN !== '') {
    @mail(EMAIL_ADMIN, 'Nouvelle inscription Corniche',
        "Prénom : $prenom\nNom : $nom\nEmail : $email\nClasse : $classe\nMessage : $message",
        "Content-Type: text/plain; charset=UTF-8\r\n");
}

retour('ok');
