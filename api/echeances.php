<?php
/**
 * API JSON pour les échéances (DS, rendus, examens…).
 *   ?action=creer / maj / basculer / supprimer
 */
require_once __DIR__ . '/../includes/auth.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
verifier_csrf();

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';
$data   = corps_json();

$types_valides = ['DS', 'TD', 'rendu', 'examen', 'autre'];

/** Vrai si la matière appartient à l'utilisateur (ou valeur vide acceptée). */
function matiere_ok(int $uid, $matiere_id): bool
{
    if (!$matiere_id) {
        return true; // pas de matière = accepté
    }
    $stmt = db()->prepare(
        'SELECT 1 FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE m.id = ? AND u.utilisateur_id = ?'
    );
    $stmt->execute([$matiere_id, $uid]);
    return (bool) $stmt->fetch();
}

/** Normalise la date reçue du champ datetime-local (« T » -> espace). */
function normaliser_date(string $d): ?string
{
    $d = str_replace('T', ' ', trim($d));
    $t = strtotime($d);
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

switch ($action) {

    case 'creer':
    case 'maj':
        $titre = trim($data['titre'] ?? '');
        $type  = in_array($data['type'] ?? '', $types_valides, true) ? $data['type'] : 'autre';
        $date  = normaliser_date($data['date_echeance'] ?? '');
        $matiere_id = (int) ($data['matiere_id'] ?? 0) ?: null;
        $desc  = trim($data['description'] ?? '');

        if ($titre === '' || $date === null) {
            repondre_json(['erreur' => 'Titre et date obligatoires'], 400);
        }
        if (!matiere_ok($uid, $matiere_id)) {
            repondre_json(['erreur' => 'Matière inconnue'], 403);
        }

        if ($action === 'creer') {
            $stmt = db()->prepare(
                'INSERT INTO echeances
                    (utilisateur_id, matiere_id, titre, description, type, date_echeance)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$uid, $matiere_id, $titre, $desc, $type, $date]);
            repondre_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
        } else {
            $id = (int) ($data['id'] ?? 0);
            $stmt = db()->prepare(
                'UPDATE echeances
                    SET matiere_id = ?, titre = ?, description = ?, type = ?, date_echeance = ?
                  WHERE id = ? AND utilisateur_id = ?'
            );
            $stmt->execute([$matiere_id, $titre, $desc, $type, $date, $id, $uid]);
            repondre_json(['ok' => true]);
        }
        break;

    case 'basculer':
        $id = (int) ($data['id'] ?? 0);
        $stmt = db()->prepare(
            'UPDATE echeances SET termine = 1 - termine
              WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([$id, $uid]);
        repondre_json(['ok' => true]);
        break;

    case 'supprimer':
        $id = (int) ($data['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM echeances WHERE id = ? AND utilisateur_id = ?');
        $stmt->execute([$id, $uid]);
        repondre_json(['ok' => true]);
        break;

    default:
        repondre_json(['erreur' => 'Action inconnue'], 400);
}
