<?php
/**
 * API JSON pour les tags (étiquettes transversales).
 *   ?action=attacher : ajoute un tag à une note (le crée au besoin)
 *   ?action=detacher : retire un tag d'une note
 *   ?action=supprimer: supprime un tag partout
 */
require_once __DIR__ . '/../includes/auth.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
verifier_csrf();

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';
$data   = corps_json();

function note_a_moi(int $uid, int $id): bool
{
    $stmt = db()->prepare('SELECT 1 FROM notes WHERE id = ? AND utilisateur_id = ?');
    $stmt->execute([$id, $uid]);
    return (bool) $stmt->fetch();
}

switch ($action) {

    case 'attacher':
        $note_id = (int) ($data['note_id'] ?? 0);
        $nom     = trim($data['nom'] ?? '');
        if (!$note_id || !note_a_moi($uid, $note_id)) {
            repondre_json(['erreur' => 'Note introuvable'], 404);
        }
        if ($nom === '' || mb_strlen($nom) > 50) {
            repondre_json(['erreur' => 'Nom de tag invalide'], 400);
        }

        // Le tag existe déjà pour cet utilisateur ?
        $stmt = db()->prepare(
            'SELECT id, couleur FROM tags WHERE utilisateur_id = ? AND nom = ?'
        );
        $stmt->execute([$uid, $nom]);
        $tag = $stmt->fetch();

        if (!$tag) {
            $couleur = (isset($data['couleur']) &&
                        preg_match('/^#[0-9a-fA-F]{6}$/', $data['couleur']))
                       ? $data['couleur'] : '#64748b';
            $stmt = db()->prepare(
                'INSERT INTO tags (utilisateur_id, nom, couleur) VALUES (?, ?, ?)'
            );
            $stmt->execute([$uid, $nom, $couleur]);
            $tag = ['id' => (int) db()->lastInsertId(), 'couleur' => $couleur];
        }

        // Lien note <-> tag (INSERT IGNORE évite le doublon).
        $stmt = db()->prepare(
            'INSERT IGNORE INTO note_tags (note_id, tag_id) VALUES (?, ?)'
        );
        $stmt->execute([$note_id, $tag['id']]);

        repondre_json([
            'ok'      => true,
            'tag_id'  => (int) $tag['id'],
            'nom'     => $nom,
            'couleur' => $tag['couleur'],
        ]);
        break;

    case 'creer':
        $nom = trim($data['nom'] ?? '');
        if ($nom === '' || mb_strlen($nom) > 50) {
            repondre_json(['erreur' => 'Nom de tag invalide'], 400);
        }
        $couleur = (isset($data['couleur']) &&
                    preg_match('/^#[0-9a-fA-F]{6}$/', $data['couleur']))
                   ? $data['couleur'] : '#64748b';
        // INSERT IGNORE : ne recrée pas un tag déjà existant (contrainte unique).
        $stmt = db()->prepare(
            'INSERT IGNORE INTO tags (utilisateur_id, nom, couleur) VALUES (?, ?, ?)'
        );
        $stmt->execute([$uid, $nom, $couleur]);
        repondre_json(['ok' => true]);
        break;

    case 'detacher':
        $note_id = (int) ($data['note_id'] ?? 0);
        $tag_id  = (int) ($data['tag_id'] ?? 0);
        if (!$note_id || !note_a_moi($uid, $note_id)) {
            repondre_json(['erreur' => 'Note introuvable'], 404);
        }
        $stmt = db()->prepare('DELETE FROM note_tags WHERE note_id = ? AND tag_id = ?');
        $stmt->execute([$note_id, $tag_id]);
        repondre_json(['ok' => true]);
        break;

    case 'supprimer':
        $tag_id = (int) ($data['tag_id'] ?? 0);
        // On vérifie que le tag appartient bien à l'utilisateur.
        $stmt = db()->prepare('DELETE FROM tags WHERE id = ? AND utilisateur_id = ?');
        $stmt->execute([$tag_id, $uid]);
        repondre_json(['ok' => true]);
        break;

    default:
        repondre_json(['erreur' => 'Action inconnue'], 400);
}
