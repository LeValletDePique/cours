<?php
/**
 * API pour l'import de fichiers rattachés à une note.
 *   (POST multipart)   : envoie un fichier
 *   ?action=supprimer  : supprime un fichier (JSON)
 *
 * Validation stricte : extension + type MIME sur liste blanche, taille max,
 * nom stocké aléatoire. Les fichiers ne sont servis que via telecharger.php.
 */
require_once __DIR__ . '/../includes/auth.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
verifier_csrf();

$uid    = utilisateur_id();
$action = $_GET['action'] ?? 'envoyer';
$config = config_app();

function note_m(int $uid, int $id): bool
{
    $stmt = db()->prepare('SELECT 1 FROM notes WHERE id = ? AND utilisateur_id = ?');
    $stmt->execute([$id, $uid]);
    return (bool) $stmt->fetch();
}

// ---------- Suppression d'un fichier ----------
if ($action === 'supprimer') {
    $data = corps_json();
    $id = (int) ($data['id'] ?? 0);
    $stmt = db()->prepare(
        'SELECT nom_stocke FROM fichiers WHERE id = ? AND utilisateur_id = ?'
    );
    $stmt->execute([$id, $uid]);
    $f = $stmt->fetch();
    if (!$f) {
        repondre_json(['erreur' => 'Fichier introuvable'], 404);
    }
    $chemin = $config['dossier_uploads'] . '/' . $f['nom_stocke'];
    if (is_file($chemin)) {
        @unlink($chemin);
    }
    db()->prepare('DELETE FROM fichiers WHERE id = ? AND utilisateur_id = ?')
        ->execute([$id, $uid]);
    repondre_json(['ok' => true]);
}

// ---------- Envoi d'un fichier ----------
$note_id = (int) ($_POST['note_id'] ?? 0);
if (!$note_id || !note_m($uid, $note_id)) {
    repondre_json(['erreur' => 'Note introuvable'], 404);
}
if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
    repondre_json(['erreur' => 'Aucun fichier reçu (ou trop volumineux).'], 400);
}

$fichier = $_FILES['fichier'];

// Taille
if ($fichier['size'] > $config['upload_taille_max']) {
    $mo = round($config['upload_taille_max'] / 1048576);
    repondre_json(['erreur' => "Fichier trop lourd (max {$mo} Mo)."], 400);
}

// Extensions autorisées -> types MIME acceptés
$autorises = [
    'pdf'  => ['application/pdf'],
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'txt'  => ['text/plain'],
    'md'   => ['text/plain', 'text/markdown', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document',
               'application/zip', 'application/octet-stream'],
    'doc'  => ['application/msword', 'application/octet-stream'],
];

$ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
if (!isset($autorises[$ext])) {
    repondre_json(['erreur' => "Type de fichier non autorisé (.$ext)."], 400);
}

// Vérification réelle du type MIME (pas seulement l'extension).
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($fichier['tmp_name']) ?: 'application/octet-stream';
if (!in_array($mime, $autorises[$ext], true)) {
    repondre_json(['erreur' => "Le contenu ne correspond pas à l'extension .$ext."], 400);
}

// Nom de stockage aléatoire (on ne réutilise jamais le nom d'origine).
$nom_stocke = bin2hex(random_bytes(16)) . '.' . $ext;
$destination = $config['dossier_uploads'] . '/' . $nom_stocke;

if (!is_dir($config['dossier_uploads'])) {
    @mkdir($config['dossier_uploads'], 0775, true);
}
if (!move_uploaded_file($fichier['tmp_name'], $destination)) {
    repondre_json(['erreur' => "Échec de l'enregistrement du fichier."], 500);
}

$stmt = db()->prepare(
    'INSERT INTO fichiers
        (utilisateur_id, note_id, nom_original, nom_stocke, type_mime, taille)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $uid, $note_id, $fichier['name'], $nom_stocke, $mime, (int) $fichier['size'],
]);

repondre_json([
    'ok'     => true,
    'id'     => (int) db()->lastInsertId(),
    'nom'    => $fichier['name'],
    'taille' => (int) $fichier['size'],
]);
