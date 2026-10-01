<?php
/**
 * Favoris : les notes épinglées (étoile dans l'éditeur).
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();
$stmt = db()->prepare(
    'SELECT id, titre, matiere_id, date_modification FROM notes
      WHERE utilisateur_id = ? AND supprime = 0 AND epingle = 1
      ORDER BY date_modification DESC'
);
$stmt->execute([$uid]);
$favoris = $stmt->fetchAll();

$titre_page = 'Favoris';
require __DIR__ . '/includes/header.php';
?>
<header class="mc-hello">
    <p class="mc-eyebrow"><?= pluriel(count($favoris), 'note épinglée', 'notes épinglées') ?></p>
    <h1 class="mc-title">Favoris</h1>
</header>

<section class="mc-card mc-page" aria-label="Notes favorites">
    <?php if ($favoris): ?>
        <?php foreach ($favoris as $note) echo html_note_row($note, $uid); ?>
    <?php else: ?>
        <?= html_vide('Épingle une note avec l\'étoile de l\'éditeur : tu la retrouveras ici en un clic, sans chercher.',
            '<a class="mc-btn mc-btn--sm" href="index.php">' . icone('note', 'mc-ico-sm') . 'Voir mes dernières notes</a>') ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
