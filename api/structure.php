<?php
/**
 * API JSON pour gérer la structure : UE et matières
 * (création, renommage, suppression). Utilisée par la page Réglages.
 */
require_once __DIR__ . '/../includes/auth.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
verifier_csrf();

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';
$data   = corps_json();

/** Vrai si l'UE appartient à l'utilisateur. */
function ue_appartient(int $uid, int $id): bool
{
    $stmt = db()->prepare('SELECT 1 FROM ue WHERE id = ? AND utilisateur_id = ?');
    $stmt->execute([$id, $uid]);
    return (bool) $stmt->fetch();
}

/** Vrai si la matière appartient à l'utilisateur (via son UE). */
function matiere_appartient_s(int $uid, int $id): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE m.id = ? AND u.utilisateur_id = ?'
    );
    $stmt->execute([$id, $uid]);
    return (bool) $stmt->fetch();
}

/** Valide un code couleur hexadécimal, sinon renvoie une valeur par défaut. */
function couleur_valide(?string $c, string $defaut): string
{
    return (is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c)) ? $c : $defaut;
}

switch ($action) {

    // ============ UE ============
    case 'ue_creer':
        $code = trim($data['code'] ?? '');
        $nom  = trim($data['nom'] ?? '');
        if ($code === '' || $nom === '') {
            repondre_json(['erreur' => 'Code et nom obligatoires'], 400);
        }
        $couleur = couleur_valide($data['couleur'] ?? null, '#4f46e5');
        $pos = db()->prepare('SELECT COALESCE(MAX(position),0)+1 FROM ue WHERE utilisateur_id = ?');
        $pos->execute([$uid]);
        $stmt = db()->prepare(
            'INSERT INTO ue (utilisateur_id, code, nom, couleur, position)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$uid, $code, $nom, $couleur, (int) $pos->fetchColumn()]);
        repondre_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
        break;

    case 'ue_renommer':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !ue_appartient($uid, $id)) {
            repondre_json(['erreur' => 'UE introuvable'], 404);
        }
        $code = trim($data['code'] ?? '');
        $nom  = trim($data['nom'] ?? '');
        if ($code === '' || $nom === '') {
            repondre_json(['erreur' => 'Code et nom obligatoires'], 400);
        }
        $couleur = couleur_valide($data['couleur'] ?? null, '#4f46e5');
        $stmt = db()->prepare(
            'UPDATE ue SET code = ?, nom = ?, couleur = ?
              WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$code, $nom, $couleur, $id, $uid]);
        repondre_json(['ok' => true]);
        break;

    case 'ue_supprimer':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !ue_appartient($uid, $id)) {
            repondre_json(['erreur' => 'UE introuvable'], 404);
        }
        // La suppression en cascade retire matières et notes liées.
        // On efface d'abord les fichiers physiques des notes concernées.
        $q = db()->prepare(
            'SELECT n.id FROM notes n
               JOIN matieres m ON m.id = n.matiere_id
              WHERE m.ue_id = ? AND n.utilisateur_id = ?'
        );
        $q->execute([$id, $uid]);
        foreach ($q->fetchAll() as $l) {
            effacer_fichiers_note(db(), $uid, (int) $l['id']);
        }
        $stmt = db()->prepare('DELETE FROM ue WHERE id = ? AND utilisateur_id = ?');
        $stmt->execute([$id, $uid]);
        repondre_json(['ok' => true]);
        break;

    // ============ MATIERES ============
    case 'matiere_creer':
        $ue_id = (int) ($data['ue_id'] ?? 0);
        if (!$ue_id || !ue_appartient($uid, $ue_id)) {
            repondre_json(['erreur' => 'UE inconnue'], 403);
        }
        $nom = trim($data['nom'] ?? '');
        if ($nom === '') {
            repondre_json(['erreur' => 'Nom obligatoire'], 400);
        }
        $couleur = couleur_valide($data['couleur'] ?? null, '#0891b2');
        $pos = db()->prepare('SELECT COALESCE(MAX(position),0)+1 FROM matieres WHERE ue_id = ?');
        $pos->execute([$ue_id]);
        $stmt = db()->prepare(
            'INSERT INTO matieres (ue_id, nom, couleur, position) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$ue_id, $nom, $couleur, (int) $pos->fetchColumn()]);
        repondre_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
        break;

    case 'matiere_renommer':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !matiere_appartient_s($uid, $id)) {
            repondre_json(['erreur' => 'Matière introuvable'], 404);
        }
        $nom = trim($data['nom'] ?? '');
        if ($nom === '') {
            repondre_json(['erreur' => 'Nom obligatoire'], 400);
        }
        $couleur = couleur_valide($data['couleur'] ?? null, '#0891b2');
        $stmt = db()->prepare('UPDATE matieres SET nom = ?, couleur = ? WHERE id = ?');
        $stmt->execute([$nom, $couleur, $id]);
        repondre_json(['ok' => true]);
        break;

    case 'matiere_supprimer':
        $id = (int) ($data['id'] ?? 0);
        if (!$id || !matiere_appartient_s($uid, $id)) {
            repondre_json(['erreur' => 'Matière introuvable'], 404);
        }
        // Les notes de cette matière ne sont PAS supprimées : elles
        // deviennent « non classées » (ON DELETE SET NULL), fichiers compris.
        $stmt = db()->prepare('DELETE FROM matieres WHERE id = ?');
        $stmt->execute([$id]);
        repondre_json(['ok' => true]);
        break;

    default:
        repondre_json(['erreur' => 'Action inconnue'], 400);
}
