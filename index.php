<?php
/**
 * Tableau de bord (accueil).
 * Affiche la structure UE → matières de l'utilisateur, ses dernières notes
 * et quelques statistiques. Tout vient de la base (rien codé en dur).
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();

// --- Structure UE + matières (triée), avec le nombre de notes par matière ---
$stmt = db()->prepare(
    'SELECT u.id AS ue_id, u.code, u.nom AS ue_nom, u.couleur AS ue_couleur,
            m.id AS matiere_id, m.nom AS matiere_nom,
            (SELECT COUNT(*) FROM notes n
             WHERE n.matiere_id = m.id AND n.supprime = 0) AS nb_notes
       FROM ue u
       LEFT JOIN matieres m ON m.ue_id = u.id
      WHERE u.utilisateur_id = ?
      ORDER BY u.position, m.position'
);
$stmt->execute([$uid]);

$ues = [];
foreach ($stmt as $ligne) {
    $ueId = $ligne['ue_id'];
    if (!isset($ues[$ueId])) {
        $ues[$ueId] = [
            'code'     => $ligne['code'],
            'nom'      => $ligne['ue_nom'],
            'couleur'  => $ligne['ue_couleur'],
            'matieres' => [],
        ];
    }
    if ($ligne['matiere_id'] !== null) {
        $ues[$ueId]['matieres'][] = [
            'id'       => $ligne['matiere_id'],
            'nom'      => $ligne['matiere_nom'],
            'nb_notes' => (int) $ligne['nb_notes'],
        ];
    }
}

// --- Dernières notes modifiées ---
$stmt = db()->prepare(
    'SELECT n.id, n.titre, n.date_modification, m.nom AS matiere
       FROM notes n
       LEFT JOIN matieres m ON m.id = n.matiere_id
      WHERE n.utilisateur_id = ? AND n.supprime = 0
      ORDER BY n.date_modification DESC
      LIMIT 6'
);
$stmt->execute([$uid]);
$dernieres_notes = $stmt->fetchAll();

// --- Notes épinglées (favoris) ---
$stmt = db()->prepare(
    'SELECT n.id, n.titre, m.nom AS matiere
       FROM notes n LEFT JOIN matieres m ON m.id = n.matiere_id
      WHERE n.utilisateur_id = ? AND n.supprime = 0 AND n.epingle = 1
      ORDER BY n.date_modification DESC LIMIT 6'
);
$stmt->execute([$uid]);
$favoris = $stmt->fetchAll();

// --- Prochaines échéances ---
$stmt = db()->prepare(
    'SELECT e.titre, e.type, e.date_echeance, m.nom AS matiere
       FROM echeances e LEFT JOIN matieres m ON m.id = e.matiere_id
      WHERE e.utilisateur_id = ? AND e.termine = 0 AND e.date_echeance >= NOW()
      ORDER BY e.date_echeance ASC LIMIT 5'
);
$stmt->execute([$uid]);
$prochaines = $stmt->fetchAll();

// --- Matière la plus travaillée ---
$stmt = db()->prepare(
    'SELECT m.nom, COUNT(*) AS c FROM notes n JOIN matieres m ON m.id = n.matiere_id
      WHERE n.utilisateur_id = ? AND n.supprime = 0
      GROUP BY m.id ORDER BY c DESC LIMIT 1'
);
$stmt->execute([$uid]);
$top_matiere = $stmt->fetch();

// --- Statistiques rapides ---
$stmt = db()->prepare(
    'SELECT
        (SELECT COUNT(*) FROM notes WHERE utilisateur_id = ? AND supprime = 0) AS nb_notes,
        (SELECT COUNT(*) FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE u.utilisateur_id = ?) AS nb_matieres,
        (SELECT COUNT(*) FROM echeances
          WHERE utilisateur_id = ? AND termine = 0 AND date_echeance >= NOW()) AS nb_echeances'
);
$stmt->execute([$uid, $uid, $uid]);
$stats = $stmt->fetch();

// --- Cours du jour (emploi du temps importé), si la migration est appliquée ---
require_once __DIR__ . '/includes/edt.php';
$cours_du_jour = [];
if (edt_installe()) {
    $aujourdhui = edt_maintenant()->setTime(0, 0);
    $cours_du_jour = array_filter(
        edt_cours_entre($uid, $aujourdhui, $aujourdhui->modify('+1 day')),
        fn($c) => !$c['journee']
    );
}

$titre_page = 'Accueil';
require __DIR__ . '/includes/header.php';
?>
<h1>Bonjour <?= e(utilisateur_connecte()['nom_utilisateur']) ?> 👋</h1>

<section class="stats">
    <div class="stat"><span class="stat-nb"><?= (int) $stats['nb_notes'] ?></span> notes</div>
    <div class="stat"><span class="stat-nb"><?= (int) $stats['nb_matieres'] ?></span> matières</div>
    <div class="stat"><span class="stat-nb"><?= (int) $stats['nb_echeances'] ?></span> échéances à venir</div>
    <?php if ($top_matiere): ?>
        <div class="stat"><span class="stat-nb">🏆</span> <?= e($top_matiere['nom']) ?>
            <small>(<?= (int) $top_matiere['c'] ?> notes)</small></div>
    <?php endif; ?>
</section>

<?php if ($cours_du_jour): ?>
<section class="bloc">
    <h2>🗓️ Aujourd'hui</h2>
    <ul class="liste-notes">
        <?php foreach ($cours_du_jour as $c): ?>
            <li<?= $c['fin'] <= edt_maintenant()->format('Y-m-d H:i:s') ? ' class="edt-passe"' : '' ?>>
                <span class="note-titre">
                    <?= e(substr($c['debut'], 11, 5)) ?>–<?= e(substr($c['fin'], 11, 5)) ?> ·
                    <?php if ($c['matiere_id']): ?>
                        <a href="matiere.php?id=<?= (int) $c['matiere_id'] ?>"><?= e($c['intitule']) ?></a>
                    <?php else: ?>
                        <?= e($c['intitule']) ?>
                    <?php endif; ?>
                </span>
                <span class="note-meta"><?= e($c['lieu'] ?? '') ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="lien-bas"><a href="emploi-du-temps.php">Toute la semaine →</a></p>
</section>
<?php endif; ?>

<div class="grille-2">
    <section class="bloc">
        <h2>⭐ Favoris</h2>
        <?php if ($favoris): ?>
            <ul class="liste-notes">
                <?php foreach ($favoris as $note): ?>
                    <li>
                        <a class="note-titre" href="note.php?id=<?= (int) $note['id'] ?>"><?= e($note['titre']) ?></a>
                        <span class="note-meta"><?= e($note['matiere'] ?? 'Non classée') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="vide">Épingle une note (⭐) pour la retrouver ici.</p>
        <?php endif; ?>
    </section>

    <section class="bloc">
        <h2>📅 Prochaines échéances</h2>
        <?php if ($prochaines): ?>
            <ul class="liste-notes">
                <?php foreach ($prochaines as $ec): ?>
                    <li>
                        <span class="note-titre">
                            <span class="ech-type type-<?= e($ec['type']) ?>"><?= e($ec['type']) ?></span>
                            <?= e($ec['titre']) ?>
                        </span>
                        <span class="note-meta">
                            <?= e($ec['matiere'] ?? '') ?> ·
                            <?= e(date('d/m/Y H:i', strtotime($ec['date_echeance']))) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="lien-bas"><a href="echeances.php">Toutes les échéances →</a></p>
        <?php else: ?>
            <p class="vide">Aucune échéance à venir. <a href="echeances.php">En ajouter</a></p>
        <?php endif; ?>
    </section>
</div>

<section class="bloc">
    <h2>Dernières notes modifiées</h2>
    <?php if ($dernieres_notes): ?>
        <ul class="liste-notes">
            <?php foreach ($dernieres_notes as $note): ?>
                <li>
                    <a class="note-titre" href="note.php?id=<?= (int) $note['id'] ?>"><?= e($note['titre']) ?></a>
                    <span class="note-meta">
                        <?= e($note['matiere'] ?? 'Non classée') ?> ·
                        <?= e(date('d/m/Y H:i', strtotime($note['date_modification']))) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="vide">Aucune note pour l'instant.</p>
    <?php endif; ?>
</section>

<section class="bloc">
    <h2>Mes matières</h2>
    <div class="grille-ue">
        <?php foreach ($ues as $ue): ?>
            <article class="carte-ue" style="--couleur-ue: <?= e($ue['couleur']) ?>">
                <h3><span class="badge-ue"><?= e($ue['code']) ?></span> <?= e($ue['nom']) ?></h3>
                <ul class="liste-matieres">
                    <?php foreach ($ue['matieres'] as $m): ?>
                        <li>
                            <a href="matiere.php?id=<?= (int) $m['id'] ?>"><?= e($m['nom']) ?></a>
                            <span class="compteur"><?= $m['nb_notes'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
