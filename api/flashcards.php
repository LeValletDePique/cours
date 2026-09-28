<?php
/**
 * API JSON pour les flashcards (mode révision).
 *   ?action=creer / supprimer
 */
require_once __DIR__ . '/../includes/auth.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
verifier_csrf();

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';
$data   = corps_json();

switch ($action) {

    case 'creer':
        $question = trim($data['question'] ?? '');
        $reponse  = trim($data['reponse'] ?? '');
        $matiere_id = (int) ($data['matiere_id'] ?? 0) ?: null;

        if ($question === '' || $reponse === '') {
            repondre_json(['erreur' => 'Question et réponse obligatoires'], 400);
        }
        // Vérifie la matière si fournie.
        if ($matiere_id) {
            $stmt = db()->prepare(
                'SELECT 1 FROM matieres m JOIN ue u ON u.id = m.ue_id
                  WHERE m.id = ? AND u.utilisateur_id = ?'
            );
            $stmt->execute([$matiere_id, $uid]);
            if (!$stmt->fetch()) {
                repondre_json(['erreur' => 'Matière inconnue'], 403);
            }
        }

        $stmt = db()->prepare(
            'INSERT INTO flashcards (utilisateur_id, matiere_id, question, reponse)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$uid, $matiere_id, $question, $reponse]);
        repondre_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
        break;

    case 'supprimer':
        $id = (int) ($data['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM flashcards WHERE id = ? AND utilisateur_id = ?');
        $stmt->execute([$id, $uid]);
        repondre_json(['ok' => true]);
        break;

    default:
        repondre_json(['erreur' => 'Action inconnue'], 400);
}
