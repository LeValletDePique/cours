<?php
/**
 * API JSON pour les notes (appelée en AJAX par l'éditeur).
 *   ?action=creer     : crée une note vide dans une matière
 *   ?action=maj       : met à jour titre / contenu / matière (auto-save)
 *   ?action=supprimer : envoie la note à la corbeille (soft delete)
 *
 * Toutes les actions sont en POST, protégées par le jeton CSRF, et ne
 * touchent QUE les notes de l'utilisateur connecté.
 */
require_once __DIR__ . '/../includes/auth.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
verifier_csrf();

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';
$data   = corps_json();

/** Vrai si la note appartient à l'utilisateur connecté. */
function note_appartient(int $uid, int $id): bool
{
    $stmt = db()->prepare('SELECT 1 FROM notes WHERE id = ? AND utilisateur_id = ?');
    $stmt->execute([$id, $uid]);
    return (bool) $stmt->fetch();
}

/** Vrai si la matière appartient à l'utilisateur (via son UE). */
function matiere_appartient(int $uid, int $matiere_id): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE m.id = ? AND u.utilisateur_id = ?'
    );
    $stmt->execute([$matiere_id, $uid]);
    return (bool) $stmt->fetch();
}

switch ($action) {

    // ---------------------------------------------------------
    case 'creer':
        $matiere_id = isset($data['matiere_id']) ? (int) $data['matiere_id'] : 0;
        if ($matiere_id && !matiere_appartient($uid, $matiere_id)) {
            repondre_json(['erreur' => 'Matière inconnue'], 403);
        }
        $titre = trim($data['titre'] ?? '');
        if ($titre === '') {
            $titre = 'Sans titre';
        }
        $stmt = db()->prepare(
            'INSERT INTO notes (matiere_id, utilisateur_id, titre, contenu)
             VALUES (?, ?, ?, "")'
        );
        $stmt->execute([$matiere_id ?: null, $uid, $titre]);
        repondre_json(['id' => (int) db()->lastInsertId()]);
        break;

    // ---------------------------------------------------------
    case 'maj':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !note_appartient($uid, $id)) {
            repondre_json(['erreur' => 'Note introuvable'], 404);
        }
        $titre   = trim($data['titre'] ?? '');
        if ($titre === '') {
            $titre = 'Sans titre';
        }
        $contenu = (string) ($data['contenu'] ?? '');

        // Matière : peut être changée, ou vidée (note non classée).
        $matiere_id = null;
        if (array_key_exists('matiere_id', $data)) {
            $matiere_id = (int) $data['matiere_id'] ?: null;
            if ($matiere_id && !matiere_appartient($uid, $matiere_id)) {
                repondre_json(['erreur' => 'Matière inconnue'], 403);
            }
        }

        $stmt = db()->prepare(
            'UPDATE notes
                SET titre = ?, contenu = ?, matiere_id = ?
              WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$titre, $contenu, $matiere_id, $id, $uid]);

        repondre_json([
            'ok'                => true,
            'date_modification' => date('H:i:s'),
        ]);
        break;

    // ---------------------------------------------------------
    case 'supprimer':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !note_appartient($uid, $id)) {
            repondre_json(['erreur' => 'Note introuvable'], 404);
        }
        $stmt = db()->prepare(
            'UPDATE notes SET supprime = 1, date_suppression = NOW()
              WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$id, $uid]);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    case 'epingler':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !note_appartient($uid, $id)) {
            repondre_json(['erreur' => 'Note introuvable'], 404);
        }
        // Bascule 0/1
        $stmt = db()->prepare(
            'UPDATE notes SET epingle = 1 - epingle
              WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$id, $uid]);
        $stmt = db()->prepare('SELECT epingle FROM notes WHERE id = ?');
        $stmt->execute([$id]);
        repondre_json(['ok' => true, 'epingle' => (int) $stmt->fetchColumn()]);
        break;

    // ---------------------------------------------------------
    case 'restaurer':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !note_appartient($uid, $id)) {
            repondre_json(['erreur' => 'Note introuvable'], 404);
        }
        $stmt = db()->prepare(
            'UPDATE notes SET supprime = 0, date_suppression = NULL
              WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$id, $uid]);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    case 'supprimer_definitif':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !note_appartient($uid, $id)) {
            repondre_json(['erreur' => 'Note introuvable'], 404);
        }
        effacer_fichiers_note(db(), $uid, $id);   // fichiers du disque
        $stmt = db()->prepare('DELETE FROM notes WHERE id = ? AND utilisateur_id = ?');
        $stmt->execute([$id, $uid]);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    case 'vider_corbeille':
        // Récupère les notes en corbeille pour effacer leurs fichiers.
        $stmt = db()->prepare(
            'SELECT id FROM notes WHERE utilisateur_id = ? AND supprime = 1'
        );
        $stmt->execute([$uid]);
        foreach ($stmt->fetchAll() as $ligne) {
            effacer_fichiers_note(db(), $uid, (int) $ligne['id']);
        }
        $stmt = db()->prepare(
            'DELETE FROM notes WHERE utilisateur_id = ? AND supprime = 1'
        );
        $stmt->execute([$uid]);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    default:
        repondre_json(['erreur' => 'Action inconnue'], 400);
}
