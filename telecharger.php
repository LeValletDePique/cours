<?php
/**
 * Sert un fichier importé, UNIQUEMENT si la note appartient à l'utilisateur
 * connecté. Le dossier uploads/ est inaccessible directement (voir .htaccess) :
 * tout passe par ce script, qui contrôle les droits.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();
$id  = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT nom_original, nom_stocke, type_mime
       FROM fichiers WHERE id = ? AND utilisateur_id = ?'
);
$stmt->execute([$id, $uid]);
$f = $stmt->fetch();

if (!$f) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

$chemin = config_app()['dossier_uploads'] . '/' . $f['nom_stocke'];
if (!is_file($chemin)) {
    http_response_code(404);
    exit('Fichier absent du serveur.');
}

// Les images et PDF s'affichent dans le navigateur ; le reste se télécharge.
$affichables = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];
$disposition = in_array($f['type_mime'], $affichables, true) ? 'inline' : 'attachment';

header('Content-Type: ' . $f['type_mime']);
header('Content-Length: ' . filesize($chemin));
header('Content-Disposition: ' . $disposition . '; filename="'
    . rawurlencode($f['nom_original']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($chemin);
exit;
