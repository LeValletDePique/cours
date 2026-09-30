<?php
/**
 * API JSON de l'emploi du temps.
 *   ?action=importer     : envoi d'un fichier .ics (formulaire multipart, champ « fichier »)
 *   ?action=connecter    : enregistre un lien d'abonnement .ics et synchronise { url }
 *   ?action=synchroniser : relance la synchronisation depuis le lien enregistré
 *   ?action=oublier      : retire le lien (les cours déjà importés restent affichés)
 *
 * Toutes les actions sont en POST et protégées par le jeton CSRF.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/edt.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    repondre_json(['erreur' => 'Méthode non autorisée'], 405);
}
verifier_csrf();
if (!edt_installe()) {
    repondre_json(['erreur' => 'Lance d\'abord « php migrations/appliquer.php ».'], 503);
}

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';

switch ($action) {

    // ---------------------------------------------------------
    case 'importer':
        $f = $_FILES['fichier'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            repondre_json(['erreur' => 'Choisis le fichier .ics exporté depuis l\'ENT.'], 400);
        }
        if ($f['size'] > EDT_TAILLE_MAX) {
            repondre_json(['erreur' => 'Fichier trop gros (5 Mo max).'], 400);
        }
        try {
            $resume = edt_enregistrer($uid, (string) file_get_contents($f['tmp_name']));
        } catch (InvalidArgumentException $e) {
            repondre_json(['erreur' => $e->getMessage()], 400);
        }
        repondre_json(['ok' => true, 'resume' => $resume]);
        break;

    // ---------------------------------------------------------
    case 'connecter':
        $data = corps_json();
        try {
            $url = edt_normaliser_url((string) ($data['url'] ?? ''));
            // On vérifie le lien AVANT de l'enregistrer : un lien faux est signalé tout de suite.
            $resume = edt_enregistrer($uid, edt_telecharger($url));
        } catch (InvalidArgumentException | RuntimeException $e) {
            repondre_json(['erreur' => $e->getMessage()], 400);
        }
        db()->prepare(
            'INSERT INTO edt_sources (utilisateur_id, url, derniere_synchro, derniere_erreur)
             VALUES (?, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE url = VALUES(url),
                 derniere_synchro = VALUES(derniere_synchro), derniere_erreur = NULL'
        )->execute([$uid, $url, edt_maintenant()->format('Y-m-d H:i:s')]);
        repondre_json(['ok' => true, 'resume' => $resume]);
        break;

    // ---------------------------------------------------------
    case 'synchroniser':
        $res = edt_synchroniser($uid);
        repondre_json($res, $res['ok'] ? 200 : 502);
        break;

    // ---------------------------------------------------------
    case 'oublier':
        db()->prepare('UPDATE edt_sources SET url = NULL, derniere_erreur = NULL WHERE utilisateur_id = ?')
            ->execute([$uid]);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    default:
        repondre_json(['erreur' => 'Action inconnue'], 400);
}
