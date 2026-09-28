<?php
/**
 * Export au format Markdown (.md) d'une note ou d'une matière entière.
 * (L'export PDF se fait via imprimer.php + l'impression du navigateur.)
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid  = utilisateur_id();
$type = $_GET['type'] ?? 'note';
$id   = (int) ($_GET['id'] ?? 0);

/** Envoie le contenu en téléchargement Markdown puis stoppe. */
function telecharger_md(string $nom, string $contenu): void
{
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . nom_fichier_sur($nom) . '.md"');
    echo $contenu;
    exit;
}

if ($type === 'note') {
    $stmt = db()->prepare(
        'SELECT titre, contenu FROM notes
          WHERE id = ? AND utilisateur_id = ? AND supprime = 0'
    );
    $stmt->execute([$id, $uid]);
    $note = $stmt->fetch();
    if (!$note) {
        http_response_code(404);
        exit('Note introuvable.');
    }
    $md = '# ' . $note['titre'] . "\n\n" . $note['contenu'] . "\n";
    telecharger_md($note['titre'], $md);
}

if ($type === 'matiere') {
    // Vérifie la propriété de la matière.
    $stmt = db()->prepare(
        'SELECT m.nom FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE m.id = ? AND u.utilisateur_id = ?'
    );
    $stmt->execute([$id, $uid]);
    $matiere = $stmt->fetch();
    if (!$matiere) {
        http_response_code(404);
        exit('Matière introuvable.');
    }

    $stmt = db()->prepare(
        'SELECT titre, contenu FROM notes
          WHERE matiere_id = ? AND utilisateur_id = ? AND supprime = 0
          ORDER BY date_creation'
    );
    $stmt->execute([$id, $uid]);

    $md = '# ' . $matiere['nom'] . "\n\n";
    foreach ($stmt as $note) {
        $md .= '## ' . $note['titre'] . "\n\n" . $note['contenu'] . "\n\n---\n\n";
    }
    telecharger_md($matiere['nom'], $md);
}

http_response_code(400);
exit('Type d\'export inconnu.');
