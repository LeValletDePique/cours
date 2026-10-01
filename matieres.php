<?php
/**
 * Matières : toutes les matières, groupées par UE (SubjectList),
 * avec leur nombre de notes. Les matières sans note ont un point creux.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();
$ues = structure_ue($uid);
$nb_matieres = array_sum(array_map(fn($u) => count($u['matieres']), $ues));
$nb_notes = array_sum(array_map(fn($u) => array_sum(array_column($u['matieres'], 'nb_notes')), $ues));
$stmt = db()->prepare('SELECT COUNT(*) FROM notes WHERE utilisateur_id = ? AND supprime = 0 AND matiere_id IS NULL');
$stmt->execute([$uid]);
$non_classees = (int) $stmt->fetchColumn();
[$gauche, $droite] = deux_colonnes($ues);

$titre_page = 'Matières';
require __DIR__ . '/includes/header.php';
?>
<header class="mc-hello">
    <p class="mc-eyebrow"><?= pluriel($nb_matieres, 'matière') ?> · <?= pluriel($nb_notes, 'note') ?></p>
    <h1 class="mc-title">Matières</h1>
</header>

<section class="mc-card" aria-label="Mes matières">
    <div class="mc-card__head">
        <h2 class="mc-h">Par UE</h2>
        <a class="mc-link" href="reglages.php#structure">Gérer les UE et matières</a>
    </div>
    <?php if ($ues): ?>
        <div class="mc-two">
            <div><?= html_blocs_ue($gauche) ?></div>
            <div><?= html_blocs_ue($droite) ?></div>
        </div>
    <?php else: ?>
        <?= html_vide('Crée tes UE et leurs matières : chaque note sera rangée au bon endroit.',
            '<a class="mc-btn mc-btn--sm" href="reglages.php#structure">' . icone('plus', 'mc-ico-sm') . 'Ajouter une UE</a>') ?>
    <?php endif; ?>
</section>

<?php if ($non_classees): ?>
    <section class="mc-card" aria-label="Notes non classées">
        <div class="mc-card__head">
            <h2 class="mc-h">Non classées</h2>
            <span class="mc-meta"><?= pluriel($non_classees, 'note') ?></span>
        </div>
        <?php
        $stmt = db()->prepare(
            'SELECT id, titre, matiere_id, epingle, date_modification FROM notes
              WHERE utilisateur_id = ? AND supprime = 0 AND matiere_id IS NULL
              ORDER BY date_modification DESC'
        );
        $stmt->execute([$uid]);
        foreach ($stmt as $note) echo html_note_row($note, $uid);
        ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
