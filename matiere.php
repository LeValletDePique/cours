<?php
/**
 * Page d'une matière : liste de ses notes, avec création d'une nouvelle note.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();
$matiere_id = (int) ($_GET['id'] ?? 0);

// --- Récupère la matière en vérifiant qu'elle appartient à l'utilisateur ---
$stmt = db()->prepare(
    'SELECT m.id, m.nom, u.code AS ue_code, u.nom AS ue_nom, u.couleur
       FROM matieres m
       JOIN ue u ON u.id = m.ue_id
      WHERE m.id = ? AND u.utilisateur_id = ?'
);
$stmt->execute([$matiere_id, $uid]);
$matiere = $stmt->fetch();

if (!$matiere) {
    http_response_code(404);
    $titre_page = 'Matière introuvable';
    require __DIR__ . '/includes/header.php';
    echo '<p class="alerte">Cette matière n\'existe pas ou ne t\'appartient pas.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// --- Création d'une nouvelle note (POST) puis redirection vers l'éditeur ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $stmt = db()->prepare(
        'INSERT INTO notes (matiere_id, utilisateur_id, titre, contenu)
         VALUES (?, ?, "Sans titre", "")'
    );
    $stmt->execute([$matiere_id, $uid]);
    header('Location: note.php?id=' . (int) db()->lastInsertId());
    exit;
}

// --- Liste des notes de la matière (hors corbeille) ---
$stmt = db()->prepare(
    'SELECT id, titre, epingle, date_modification
       FROM notes
      WHERE matiere_id = ? AND utilisateur_id = ? AND supprime = 0
      ORDER BY epingle DESC, date_modification DESC'
);
$stmt->execute([$matiere_id, $uid]);
$notes = $stmt->fetchAll();

// --- Prochains cours de cette matière (emploi du temps importé) ---
require_once __DIR__ . '/includes/agenda.php';
installer_agenda();
$stmt = db()->prepare(
    'SELECT debut, fin, journee, titre, lieu, source_id FROM evenements
      WHERE utilisateur_id = ? AND matiere_id = ? AND fin > ?
      ORDER BY debut LIMIT 3'
);
$stmt->execute([$uid, $matiere_id, date('Y-m-d H:i:s')]);
$prochains_cours = $stmt->fetchAll();

$titre_page = $matiere['nom'];
require __DIR__ . '/includes/header.php';
?>
<p class="fil"><a href="index.php">Accueil</a> ›
   <span class="badge-ue" style="--couleur-ue: <?= e($matiere['couleur']) ?>; background: <?= e($matiere['couleur']) ?>"><?= e($matiere['ue_code']) ?></span>
   <?= e($matiere['ue_nom']) ?></p>

<div class="entete-matiere">
    <h1><?= e($matiere['nom']) ?></h1>
    <form method="post">
        <?= champ_csrf() ?>
        <button type="submit" class="btn-principal">＋ Nouvelle note</button>
    </form>
</div>

<?php if ($prochains_cours): ?>
    <p class="prochains-cours">📆 Prochains cours :
        <?php foreach ($prochains_cours as $i => $c): ?>
            <?= $i ? ' · ' : '' ?><a href="agenda.php"><?= e(date($c['journee'] ? 'd/m' : 'd/m H:i', strtotime($c['debut']))) ?></a>
            <?php $salle = $c['source_id'] ? nettoyer_salle((string) $c['lieu']) : $c['lieu']; ?>
            <?= $salle ? '<small>(' . e($salle) . ')</small>' : '' ?>
        <?php endforeach; ?>
    </p>
<?php endif; ?>

<?php if ($notes): ?>
    <ul class="liste-notes grande">
        <?php foreach ($notes as $note): ?>
            <li>
                <a class="note-titre" href="note.php?id=<?= (int) $note['id'] ?>">
                    <?= $note['epingle'] ? '📌 ' : '' ?><?= e($note['titre']) ?>
                </a>
                <span class="note-meta">
                    modifiée le <?= e(date('d/m/Y à H:i', strtotime($note['date_modification']))) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="vide">Aucune note dans cette matière. Clique sur « Nouvelle note » pour commencer.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
