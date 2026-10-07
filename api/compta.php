<?php
/**
 * API JSON de l'atelier comptable.
 *   ?action=lister               : dossiers de l'utilisateur (id, titre, date)
 *   ?action=charger&id=…         : un dossier complet (données JSON)
 *   ?action=creer                : nouveau dossier { titre, donnees }
 *   ?action=enregistrer          : met à jour { id, titre, donnees } (auto-save)
 *   ?action=supprimer            : supprime { id }
 *
 * Les lectures passent en GET ; les écritures exigent le jeton CSRF et ne
 * touchent que les dossiers de l'utilisateur connecté.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/compta.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';
installer_compta();

if (!in_array($action, ['lister', 'charger'], true)) {
    verifier_csrf();
}
$data = corps_json();

/** Titre nettoyé (jamais vide). */
function titre_dossier($titre): string
{
    $titre = trim(mb_substr((string) $titre, 0, 255));
    return $titre !== '' ? $titre : 'Dossier comptable';
}

/** Données du dossier ré-encodées en JSON (ou réponse d'erreur). */
function donnees_dossier($donnees): string
{
    if (!is_array($donnees)) {
        repondre_json(['erreur' => 'Données invalides'], 400);
    }
    $json = json_encode($donnees, JSON_UNESCAPED_UNICODE);
    if ($json === false || strlen($json) > COMPTA_TAILLE_MAX) {
        repondre_json(['erreur' => 'Dossier trop volumineux'], 400);
    }
    return $json;
}

switch ($action) {

    case 'lister':
        $stmt = db()->prepare(
            'SELECT id, titre, date_modification FROM compta_dossiers
              WHERE utilisateur_id = ? ORDER BY date_modification DESC, id DESC'
        );
        $stmt->execute([$uid]);
        repondre_json(['dossiers' => array_map(fn($d) => [
            'id'    => (int) $d['id'],
            'titre' => $d['titre'],
            'date'  => $d['date_modification'],
        ], $stmt->fetchAll())]);
        break;

    case 'charger':
        $stmt = db()->prepare(
            'SELECT id, titre, donnees FROM compta_dossiers WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([(int) ($_GET['id'] ?? 0), $uid]);
        $d = $stmt->fetch();
        if (!$d) {
            repondre_json(['erreur' => 'Dossier introuvable'], 404);
        }
        repondre_json(['id' => (int) $d['id'], 'titre' => $d['titre'],
                       'donnees' => json_decode($d['donnees'], true) ?: new stdClass()]);
        break;

    case 'creer':
        $stmt = db()->prepare(
            'INSERT INTO compta_dossiers (utilisateur_id, titre, donnees) VALUES (?, ?, ?)'
        );
        $stmt->execute([$uid, titre_dossier($data['titre'] ?? ''), donnees_dossier($data['donnees'] ?? [])]);
        repondre_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
        break;

    case 'enregistrer':
        $stmt = db()->prepare(
            'UPDATE compta_dossiers SET titre = ?, donnees = ? WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([titre_dossier($data['titre'] ?? ''), donnees_dossier($data['donnees'] ?? []),
                        (int) ($data['id'] ?? 0), $uid]);
        // rowCount() vaut 0 aussi quand rien n'a changé : on vérifie l'existence à part.
        $verif = db()->prepare('SELECT 1 FROM compta_dossiers WHERE id = ? AND utilisateur_id = ?');
        $verif->execute([(int) ($data['id'] ?? 0), $uid]);
        if (!$verif->fetch()) {
            repondre_json(['erreur' => 'Dossier introuvable'], 404);
        }
        repondre_json(['ok' => true, 'heure' => date('H:i:s')]);
        break;

    case 'supprimer':
        $stmt = db()->prepare('DELETE FROM compta_dossiers WHERE id = ? AND utilisateur_id = ?');
        $stmt->execute([(int) ($data['id'] ?? 0), $uid]);
        repondre_json(['ok' => true]);
        break;

    default:
        repondre_json(['erreur' => 'Action inconnue'], 400);
}
