<?php
/**
 * Page d'une matière : ses notes et ses prochains cours.
 * « Nouvelle note » (barre du haut, touche N) crée la note dans cette matière.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();
$matiere_id = (int) ($_GET['id'] ?? 0);

// --- Récupère la matière en vérifiant qu'elle appartient à l'utilisateur ---
$stmt = db()->prepare(
    'SELECT m.id, m.nom, u.id AS ue_id, u.code AS ue_code, u.nom AS ue_nom, u.couleur
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
    echo html_vide('Cette matière n\'existe pas ou ne t\'appartient plus. Retrouve tes matières dans la liste.',
        '<a class="mc-btn mc-btn--sm" href="matieres.php">' . icone('matieres', 'mc-ico-sm') . 'Voir mes matières</a>');
    require __DIR__ . '/includes/footer.php';
    exit;
}

// --- Liste des notes de la matière (hors corbeille) ---
$stmt = db()->prepare(
    'SELECT id, titre, matiere_id, epingle, date_modification
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
    'SELECT id, debut, fin, journee, titre, lieu, source_id, categorie FROM evenements
      WHERE utilisateur_id = ? AND matiere_id = ? AND fin > ?
      ORDER BY debut LIMIT 3'
);
$stmt->execute([$uid, $matiere_id, date('Y-m-d H:i:s')]);
$prochains_cours = $stmt->fetchAll();

$titre_page = $matiere['nom'];
$matiere_page = $matiere_id;   // « Nouvelle note » (barre du haut, touche N) la range ici
require __DIR__ . '/includes/header.php';
?>
<?php $classe = classe_ue($uid, (int) $matiere['ue_id']); ?>
<header class="mc-hello <?= e($classe) ?>">
    <p class="mc-eyebrow mc-fil"><a class="mc-link" href="matieres.php">Matières</a> ·
        <span class="mc-ue"><?= e($matiere['ue_code']) ?></span> <?= e(nom_court_ue($matiere['ue_nom'])) ?></p>
    <div class="mc-entete">
        <h1 class="mc-title"><?= e($matiere['nom']) ?></h1>
        <div class="mc-actions">
            <a class="mc-btn mc-btn--sm" href="export.php?type=matiere&id=<?= (int) $matiere['id'] ?>&format=md"><?= icone('telecharger', 'mc-ico-sm') ?>Markdown</a>
            <a class="mc-btn mc-btn--sm" href="imprimer.php?type=matiere&id=<?= (int) $matiere['id'] ?>" target="_blank"><?= icone('imprimer', 'mc-ico-sm') ?>PDF</a>
        </div>
    </div>
</header>

<div class="mc-grid">
    <section class="mc-card mc-col-8" aria-label="Notes de la matière">
        <div class="mc-card__head">
            <h2 class="mc-h">Notes</h2>
            <span class="mc-meta"><?= pluriel(count($notes), 'note') ?></span>
        </div>
        <?php if ($notes): ?>
            <?php foreach ($notes as $note): ?>
                <?= html_note_row($note, $uid, 'le ' . date('d/m', strtotime($note['date_modification']))) ?>
            <?php endforeach; ?>
        <?php else: ?>
            <?= html_vide('Pas encore de note ici. Crée la première pendant le prochain cours : elle sera rangée dans cette matière.',
                '<button type="button" class="mc-btn mc-btn--sm" data-action="nouvelle-note">' . icone('plus', 'mc-ico-sm') . 'Nouvelle note</button>') ?>
        <?php endif; ?>
    </section>

    <section class="mc-card mc-col-4" aria-label="Prochains cours">
        <div class="mc-card__head">
            <h2 class="mc-h">Prochains cours</h2>
            <a class="mc-link" href="agenda.php">Agenda</a>
        </div>
        <?php if ($prochains_cours): ?>
            <ul class="mc-tl mc-tl--compact">
                <?php foreach ($prochains_cours as $c):
                    $ts = strtotime($c['debut']);
                    $salle = $c['source_id'] ? nettoyer_salle((string) $c['lieu']) : $c['lieu'];
                    $cat = in_array($c['categorie'], ['CM', 'TD', 'TP'], true) ? $c['categorie'] : ''; ?>
                    <li class="mc-tl__item <?= e($classe) ?>">
                        <span class="mc-tl__time"><?= e((ecart_jours($ts) === 0 ? 'auj.' : date_courte($ts)) . ($c['journee'] ? '' : ' ' . date('H:i', $ts))) ?></span>
                        <span class="mc-tl__body"><?php if ($cat): ?><span class="mc-tag<?= $cat === 'CM' ? ' mc-tag--cm' : '' ?>"><?= $cat ?></span><?php endif; ?>
                            <?php if ($salle): ?><span class="mc-tl__room"><?= e($salle) ?></span><?php endif; ?></span>
                        <button type="button" class="mc-btn mc-btn--sm mc-btn--ghost" data-action="nouvelle-note" data-evenement="<?= (int) $c['id'] ?>"
                                title="Prendre des notes pour ce cours"><?= icone('crayon', 'mc-ico-sm') ?><span class="mc-sr">Prendre des notes</span></button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= html_vide('Aucun cours relié à cette matière. Ajoute un mot-clé dans l\'emploi du temps pour voir ses prochains cours ici.',
                '<a class="mc-btn mc-btn--sm" href="agenda.php#emploi-du-temps">' . icone('lien', 'mc-ico-sm') . 'Relier les cours</a>') ?>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
