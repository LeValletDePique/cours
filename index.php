<?php
/**
 * Accueil « Aujourd'hui » (design/maquette-accueil.html).
 * Salut + résumé de la journée → cours en cours (NowCard) + semaine (WeekStreak)
 * → journée (DayTimeline) + À faire / Échéances → Matières + Reprendre.
 * Tout est calculé depuis la base : agenda (evenements), notes, échéances, fiches.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/agenda.php';
exiger_connexion();
installer_agenda();

$uid        = utilisateur_id();
$maintenant = time();
$debut_jour = date('Y-m-d 00:00:00');
$fin_jour   = date('Y-m-d 00:00:00', strtotime('+1 day'));
$matieres   = infos_matieres($uid);

/** Infos d'affichage d'un créneau de l'agenda. */
function creneau(array $e, array $matieres): array
{
    $m = $e['matiere_id'] ? ($matieres[(int) $e['matiere_id']] ?? null) : null;
    return [
        'id'         => (int) $e['id'],
        'cours'      => $e['type'] === 'cours',
        'type'       => $e['type'],
        'categorie'  => in_array($e['categorie'], ['CM', 'TD', 'TP'], true) ? $e['categorie'] : null,
        'titre'      => titre_affiche($e),
        'titre_note' => titre_note_cours($e),
        'salle'      => $e['source_id'] ? nettoyer_salle((string) $e['lieu']) : (string) $e['lieu'],
        'debut'      => strtotime($e['debut']),
        'fin'        => strtotime($e['fin'] ?: $e['debut']),
        'journee'    => (bool) $e['journee'],
        'matiere_id' => $m ? $m['id'] : null,
        'matiere'    => $m,
        'classe'     => $m ? $m['classe'] : 'mc-ue-autre',
    ];
}

/** Étiquette CM / TD / TP (mc-tag, plein pour un CM). */
function etiquette(?string $categorie): string
{
    return $categorie ? '<span class="mc-tag' . ($categorie === 'CM' ? ' mc-tag--cm' : '') . '">' . $categorie . '</span>' : '';
}

/** Horaire « 11:30–13:00 » (ou « 18:30 » sans durée, « Journée »). */
function horaire(array $c, string $sep = '–'): string
{
    if ($c['journee']) return 'Journée';
    return date('H:i', $c['debut']) . ($c['fin'] > $c['debut'] ? $sep . date('H:i', $c['fin']) : '');
}

// ---------------------------------------------------------------
//  Créneaux du jour (hors tâches)
// ---------------------------------------------------------------
$stmt = db()->prepare(
    "SELECT e.*, m.nom AS matiere FROM evenements e
       LEFT JOIN matieres m ON m.id = e.matiere_id
      WHERE e.utilisateur_id = ? AND e.type <> 'tache' AND e.debut IS NOT NULL
        AND e.debut < ? AND (e.fin > ? OR e.debut >= ?)
      ORDER BY e.journee DESC, e.debut, e.id"
);
$stmt->execute([$uid, $fin_jour, $debut_jour, $debut_jour]);
$journee = array_map(fn($e) => creneau($e, $matieres), $stmt->fetchAll());

// Notes créées ou modifiées aujourd'hui, par matière (« ✓ 1 note » dans la journée).
$stmt = db()->prepare(
    'SELECT matiere_id, COUNT(*) FROM notes
      WHERE utilisateur_id = ? AND supprime = 0 AND matiere_id IS NOT NULL
        AND ((date_creation >= ? AND date_creation < ?) OR (date_modification >= ? AND date_modification < ?))
      GROUP BY matiere_id'
);
$stmt->execute([$uid, $debut_jour, $fin_jour, $debut_jour, $fin_jour]);
$notes_du_jour = array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

foreach ($journee as &$c) {
    $c['etat'] = $c['fin'] <= $maintenant && !$c['journee'] ? 'passe'
        : ($c['debut'] <= $maintenant && !$c['journee'] ? 'maintenant' : 'avenir');
    $c['nb_notes'] = $c['matiere_id'] ? ($notes_du_jour[$c['matiere_id']] ?? 0) : 0;
}
unset($c);

$cours_jour = array_values(array_filter($journee, fn($c) => $c['cours'] && !$c['journee']));
$actuel = null;
$prochain = null;
foreach ($cours_jour as $c) {
    if ($c['etat'] === 'maintenant' && !$actuel) $actuel = $c;
    if ($c['etat'] === 'avenir' && !$prochain) $prochain = $c;
}
$vedette = $actuel ?? $prochain;   // cours mis en avant dans la NowCard
$non_notes = array_values(array_filter($cours_jour, fn($c) => $c['etat'] === 'passe' && !$c['nb_notes']));
$nb_notes_cours = count(array_filter($cours_jour, fn($c) => $c['nb_notes'] > 0));

// Cours suivant (pied de la NowCard) : après la vedette, sinon le prochain à venir.
$apres = $vedette ? date('Y-m-d H:i:s', $vedette['debut'] + 60) : date('Y-m-d H:i:s');
$stmt = db()->prepare(
    "SELECT e.*, m.nom AS matiere FROM evenements e
       LEFT JOIN matieres m ON m.id = e.matiere_id
      WHERE e.utilisateur_id = ? AND e.type = 'cours' AND e.journee = 0 AND e.debut >= ?
        AND e.debut < ? ORDER BY e.debut LIMIT 1"
);
$stmt->execute([$uid, $apres, date('Y-m-d H:i:s', strtotime('+14 days'))]);
$suivant = ($l = $stmt->fetch()) ? creneau($l, $matieres) : null;

// Matière du cours en avant : nombre de notes et dernière note (« Reprendre … »).
$derniere_matiere = null;
$nb_notes_matiere = 0;
if ($vedette && $vedette['matiere_id']) {
    $stmt = db()->prepare(
        'SELECT id, titre, (SELECT COUNT(*) FROM notes WHERE matiere_id = ? AND supprime = 0) AS nb
           FROM notes WHERE matiere_id = ? AND utilisateur_id = ? AND supprime = 0
          ORDER BY date_modification DESC LIMIT 1'
    );
    $stmt->execute([$vedette['matiere_id'], $vedette['matiere_id'], $uid]);
    if ($derniere_matiere = $stmt->fetch()) {
        $nb_notes_matiere = (int) $derniere_matiere['nb'];
    }
}

// Note la plus récente du jour (« Relire les notes du jour » en fin de journée).
$stmt = db()->prepare(
    'SELECT id FROM notes WHERE utilisateur_id = ? AND supprime = 0 AND date_modification >= ?
      ORDER BY date_modification DESC LIMIT 1'
);
$stmt->execute([$uid, $debut_jour]);
$note_du_jour = $stmt->fetchColumn();

// ---------------------------------------------------------------
//  Série de jours avec des notes (WeekStreak)
// ---------------------------------------------------------------
$depuis = date('Y-m-d 00:00:00', strtotime('-400 days'));
$stmt = db()->prepare(
    'SELECT DATE(date_creation) FROM notes WHERE utilisateur_id = ? AND date_creation >= ?
     UNION SELECT DATE(date_modification) FROM notes WHERE utilisateur_id = ? AND date_modification >= ?'
);
$stmt->execute([$uid, $depuis, $uid, $depuis]);
$jours_notes = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

$aujourdhui_note = isset($jours_notes[date('Y-m-d')]);
$serie = 0;
for ($j = $aujourdhui_note ? 0 : 1; isset($jours_notes[date('Y-m-d', strtotime("-$j days"))]); $j++) {
    $serie++;
}
$meilleure = 0;
$courante = 0;
for ($j = 400; $j >= 0; $j--) {
    $courante = isset($jours_notes[date('Y-m-d', strtotime("-$j days"))]) ? $courante + 1 : 0;
    $meilleure = max($meilleure, $courante);
}
$lundi = strtotime('monday this week', $maintenant);
$semaine = [];
foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $i => $lettre) {
    $jour = strtotime("+$i days", $lundi);
    $semaine[] = [
        'lettre' => $lettre,
        'nom'    => JOURS_FR[(int) date('w', $jour)],
        'fait'   => isset($jours_notes[date('Y-m-d', $jour)]),
        'auj'    => date('Y-m-d', $jour) === date('Y-m-d'),
    ];
}
$stmt = db()->prepare('SELECT COUNT(*) FROM flashcards WHERE utilisateur_id = ?');
$stmt->execute([$uid]);
$nb_fiches = (int) $stmt->fetchColumn();
$week_end = (int) date('N') >= 6;

// ---------------------------------------------------------------
//  Tâches (TaskList) : ouvertes + cochées aujourd'hui
// ---------------------------------------------------------------
$stmt = db()->prepare(
    "SELECT id, titre, debut, journee, fait, date_fait, matiere_id FROM evenements
      WHERE utilisateur_id = ? AND type = 'tache' AND (fait = 0 OR date_fait >= ?)
      ORDER BY fait, debut IS NULL, debut, id LIMIT 50"
);
$stmt->execute([$uid, $debut_jour]);
$taches = $stmt->fetchAll();
$nb_ouvertes = count(array_filter($taches, fn($t) => !$t['fait']));

/** « demain » / « en retard » / « ven. 2 » pour une tâche (+ classe « bientôt »). */
function quand_tache(array $t): array
{
    if (!$t['debut']) return ['', false];
    $ts = strtotime($t['debut']);
    $j = ecart_jours($ts);
    $heure = $t['journee'] ? '' : ' ' . date('H:i', $ts);
    if ($j < 0) return ['en retard', true];
    if ($j === 0) return ['aujourd\'hui' . $heure, true];
    if ($j === 1) return ['demain' . $heure, true];
    return [date_courte($ts) . $heure, false];
}

// ---------------------------------------------------------------
//  Échéances des 7 prochains jours (et celles en retard)
// ---------------------------------------------------------------
$stmt = db()->prepare(
    'SELECT id, titre, type, date_echeance, matiere_id FROM echeances
      WHERE utilisateur_id = ? AND termine = 0 AND date_echeance < ?
      ORDER BY date_echeance LIMIT 6'
);
$stmt->execute([$uid, date('Y-m-d 00:00:00', strtotime('+8 days'))]);
$echeances = $stmt->fetchAll();
$types_echeance = ['DS' => 'DS', 'TD' => 'TD', 'rendu' => 'Rendu', 'examen' => 'Examen', 'autre' => ''];

// ---------------------------------------------------------------
//  Matières (UE de la journée en premier) et dernières notes
// ---------------------------------------------------------------
$ues = structure_ue($uid);
$ues_du_jour = [];
foreach ($cours_jour as $c) {
    if ($c['matiere']) $ues_du_jour[$c['matiere']['ue_id']] = true;
}
usort($ues, fn($a, $b) => (isset($ues_du_jour[$b['id']]) <=> isset($ues_du_jour[$a['id']]))
    ?: (($a['classe'] === 'mc-ue-autre') <=> ($b['classe'] === 'mc-ue-autre')));
[$col_gauche, $col_droite] = deux_colonnes($ues);
$nb_matieres = array_sum(array_map(fn($u) => count($u['matieres']), $ues));
$stmt = db()->prepare('SELECT COUNT(*) FROM notes WHERE utilisateur_id = ? AND supprime = 0');
$stmt->execute([$uid]);
$nb_notes = (int) $stmt->fetchColumn();

$stmt = db()->prepare(
    'SELECT id, titre, matiere_id, date_modification FROM notes
      WHERE utilisateur_id = ? AND supprime = 0
      ORDER BY date_modification DESC LIMIT 5'
);
$stmt->execute([$uid]);
$dernieres = $stmt->fetchAll();

// ---------------------------------------------------------------
//  Salut et phrase-résumé : cours, tâches, et UNE suggestion
// ---------------------------------------------------------------
$prenom = utilisateur_connecte()['nom_utilisateur'];
$salut = (int) date('G') >= 18 ? 'Bonsoir' : 'Bonjour';
$resume = [];
$n = count($cours_jour);
$resume[] = ($n ? pluriel($n, 'cours', 'cours') . ' aujourd\'hui' : 'Pas de cours aujourd\'hui')
    . ', ' . ($nb_ouvertes ? pluriel($nb_ouvertes, 'tâche ouverte', 'tâches ouvertes') : 'aucune tâche ouverte') . '.';
$proche = null;
foreach ($echeances as $ec) {
    if (ecart_jours(strtotime($ec['date_echeance'])) <= 3) { $proche = $ec; break; }
}
if (count($non_notes) === 1) {
    $moment = (int) date('G', $non_notes[0]['debut']) < 12 ? 'ce matin' : 'cet après-midi';
    $resume[] = 'Un cours sans notes ' . $moment . ', rattrape-le en 2 minutes.';
} elseif ($non_notes) {
    $resume[] = count($non_notes) . ' cours sans notes aujourd\'hui, rattrape-les en quelques minutes.';
} elseif ($proche) {
    $d = delai_texte(strtotime($proche['date_echeance']), true);
    $resume[] = '« ' . $proche['titre'] . ' » ' . ($d === 'en retard' ? 'est en retard.' : 'est pour ' . $d . '.');
} elseif ($serie > 0 && !$aujourdhui_note) {
    $resume[] = 'Une note aujourd\'hui et ta série passe à ' . pluriel($serie + 1, 'jour') . '.';
}

// Matières pour l'ajout rapide de tâche (reconnaissance du nom dans la saisie).
$motifs_js = [];
foreach ($matieres as $m) {
    $motifs_js[] = ['id' => $m['id'], 'nom' => $m['court'], 'classe' => $m['classe']];
}
$stmt = db()->prepare('SELECT m.id, m.mots_cles FROM matieres m JOIN ue u ON u.id = m.ue_id WHERE u.utilisateur_id = ?');
$stmt->execute([$uid]);
$mots = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($motifs_js as &$m) {
    $m['motifs'] = array_values(array_filter(array_map('trim', array_merge([$m['nom']], explode(',', (string) ($mots[$m['id']] ?? ''))))));
}
unset($m);

$titre_page = 'Aujourd\'hui';
require __DIR__ . '/includes/header.php';
?>
<header class="mc-hello">
    <p class="mc-eyebrow"><?= e(date_longue($maintenant)) ?> · semaine <?= (int) date('W') ?></p>
    <h1 class="mc-display"><?= e($salut . ' ' . mb_strtoupper(mb_substr($prenom, 0, 1)) . mb_substr($prenom, 1)) ?>.</h1>
    <p><?= e(implode(' ', $resume)) ?></p>
</header>

<div class="mc-grid">
    <!-- NowCard : le cours en cours, sinon le prochain, sinon la journée terminée -->
    <div class="mc-col-8">
    <?php if ($vedette): $c = $vedette; ?>
        <section class="mc-card mc-now <?= e($c['classe']) ?>" aria-label="<?= $actuel ? 'Cours en cours' : 'Prochain cours' ?>">
            <div class="mc-now__head">
                <?php if ($actuel): ?>
                    <span class="mc-live">En cours · fin dans <?= e(duree_texte((int) ceil(($c['fin'] - $maintenant) / 60))) ?></span>
                <?php else: ?>
                    <span class="mc-eyebrow">Dans <?= e(duree_texte((int) ceil(($c['debut'] - $maintenant) / 60))) ?></span>
                <?php endif; ?>
                <span class="mc-now__badges">
                    <?php if ($c['matiere']): ?><span class="mc-ue"><?= e($c['matiere']['ue_code']) ?></span><?php endif; ?>
                    <?= etiquette($c['categorie']) ?>
                </span>
            </div>
            <div class="mc-now__corps">
                <h2 class="mc-title"><?= e($c['titre']) ?></h2>
                <div class="mc-now__meta">
                    <span><?= icone('horloge', 'mc-ico-sm') ?><?= e(horaire($c, ' – ')) ?></span>
                    <?php if ($c['salle']): ?><span><?= icone('lieu', 'mc-ico-sm') ?><?= e($c['salle']) ?></span><?php endif; ?>
                    <?php if ($c['matiere']): ?><span><?= icone('note', 'mc-ico-sm') ?><?= pluriel($nb_notes_matiere, 'note') ?></span><?php endif; ?>
                </div>
            </div>
            <?php if ($actuel):
                $avance = (int) round(100 * ($maintenant - $c['debut']) / max(1, $c['fin'] - $c['debut'])); ?>
                <div class="mc-progress" role="progressbar" aria-valuenow="<?= $avance ?>" aria-valuemin="0" aria-valuemax="100"
                     aria-label="Avancement du cours"><span style="width:<?= $avance ?>%"></span></div>
            <?php endif; ?>
            <div class="mc-now__actions">
                <button type="button" class="mc-btn mc-btn--primary mc-btn--lg" data-action="nouvelle-note"
                        data-evenement="<?= $c['id'] ?>"><?= icone('crayon', 'mc-ico-sm') ?>Prendre des notes<?= $actuel ? '<span class="mc-kbd">N</span>' : '' ?></button>
                <?php if ($derniere_matiere): ?>
                    <a class="mc-btn mc-btn--lg" href="note.php?id=<?= (int) $derniere_matiere['id'] ?>"
                       title="<?= e($derniere_matiere['titre']) ?>"><?= icone('note', 'mc-ico-sm') ?>Reprendre <?= e(mb_strimwidth($derniere_matiere['titre'], 0, 24, '…')) ?></a>
                <?php endif; ?>
            </div>
            <p class="mc-now__hint">Crée « <?= e($c['titre_note']) ?> »,
                <?= $c['matiere'] ? 'déjà rangée dans ' . e($c['matiere']['ue_code']) . '.' : 'sans matière : tu pourras la classer ensuite.' ?></p>
            <?php if ($suivant): ?>
                <div class="mc-next <?= e($suivant['classe']) ?>"><span class="mc-eyebrow">Ensuite</span>
                    <span class="mc-mono mc-muted"><?= e((ecart_jours($suivant['debut']) ? date_courte($suivant['debut']) . ' ' : '') . date('H:i', $suivant['debut'])) ?></span>
                    <span class="mc-dot"></span><b><?= e($suivant['titre']) ?></b><?= etiquette($suivant['categorie']) ?>
                    <?php if ($suivant['salle']): ?><span class="mc-muted mc-mono mc-next__salle"><?= e($suivant['salle']) ?></span><?php endif; ?></div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="mc-card mc-now mc-ue-autre" aria-label="Ta journée">
            <div class="mc-now__head"><span class="mc-eyebrow"><?= $cours_jour ? 'Journée terminée' : 'Aujourd\'hui' ?></span></div>
            <div class="mc-now__corps">
                <h2 class="mc-title"><?= $cours_jour ? 'Journée terminée' : 'Pas de cours aujourd\'hui' ?></h2>
                <?php if ($cours_jour): ?>
                    <div class="mc-now__meta"><span><?= icone('coche', 'mc-ico-sm') ?><?= $nb_notes_cours ?> / <?= pluriel(count($cours_jour), 'cours noté', 'cours notés') ?></span></div>
                <?php endif; ?>
            </div>
            <div class="mc-now__actions">
                <?php if ($note_du_jour): ?>
                    <a class="mc-btn mc-btn--primary mc-btn--lg" href="note.php?id=<?= (int) $note_du_jour ?>"><?= icone('note', 'mc-ico-sm') ?>Relire les notes du jour</a>
                <?php elseif ($non_notes): ?>
                    <button type="button" class="mc-btn mc-btn--primary mc-btn--lg" data-action="nouvelle-note"
                            data-evenement="<?= $non_notes[0]['id'] ?>"><?= icone('crayon', 'mc-ico-sm') ?>Rattraper <?= e($non_notes[0]['titre']) ?></button>
                <?php elseif ($nb_fiches): ?>
                    <a class="mc-btn mc-btn--primary mc-btn--lg" href="revision.php"><?= icone('revision', 'mc-ico-sm') ?>Réviser <?= pluriel($nb_fiches, 'fiche') ?></a>
                <?php else: ?>
                    <button type="button" class="mc-btn mc-btn--primary mc-btn--lg" data-action="nouvelle-note"><?= icone('crayon', 'mc-ico-sm') ?>Prendre une note<span class="mc-kbd">N</span></button>
                <?php endif; ?>
            </div>
            <?php if ($suivant): ?>
                <div class="mc-next <?= e($suivant['classe']) ?>"><span class="mc-eyebrow">Prochain</span>
                    <span class="mc-mono mc-muted"><?= e((ecart_jours($suivant['debut']) === 1 ? 'demain' : date_courte($suivant['debut'])) . ' ' . date('H:i', $suivant['debut'])) ?></span>
                    <span class="mc-dot"></span><b><?= e($suivant['titre']) ?></b><?= etiquette($suivant['categorie']) ?>
                    <?php if ($suivant['salle']): ?><span class="mc-muted mc-mono mc-next__salle"><?= e($suivant['salle']) ?></span><?php endif; ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    </div>

    <!-- WeekStreak -->
    <div class="mc-col-4">
        <section class="mc-card mc-streak" aria-label="Ta semaine">
            <p class="mc-eyebrow">Ta semaine</p>
            <div>
                <div class="mc-numeral"><?= pluriel($serie, 'jour') ?></div>
                <div class="mc-muted mc-streak__texte"><?= $serie ? 'de suite avec des notes'
                    : ($meilleure ? 'Meilleure série : ' . pluriel($meilleure, 'jour') : 'Une note aujourd\'hui lance ta série') ?></div>
            </div>
            <div class="mc-week" role="list" aria-label="Jours avec des notes cette semaine">
                <?php foreach ($semaine as $j): ?>
                    <div class="mc-day<?= $j['fait'] ? ' mc-day--done' : '' ?><?= $j['auj'] ? ' mc-day--today' : '' ?>" role="listitem"
                         aria-label="<?= e(ucfirst($j['nom'])) ?> : <?= $j['fait'] ? 'notes prises' : 'pas de note' ?>"><b class="mc-day__cell"></b><?= $j['lettre'] ?></div>
                <?php endforeach; ?>
            </div>
            <?php if ($cours_jour && !$week_end): ?>
                <div class="mc-goal">
                    <div class="mc-goal__row"><span>Cours notés aujourd'hui</span><b><?= $nb_notes_cours ?> / <?= count($cours_jour) ?></b></div>
                    <div class="mc-progress"><span style="width:<?= (int) round(100 * $nb_notes_cours / count($cours_jour)) ?>%"></span></div>
                </div>
            <?php else: ?>
                <div class="mc-goal">
                    <div class="mc-goal__row"><span>Révision</span><b><?= pluriel($nb_fiches, 'fiche') ?></b></div>
                    <a class="mc-link" href="revision.php"><?= $nb_fiches ? 'Réviser maintenant' : 'Créer des fiches' ?></a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- DayTimeline -->
    <div class="mc-col-7">
        <section class="mc-card" aria-label="Aujourd'hui">
            <div class="mc-card__head"><h3 class="mc-h">Aujourd'hui</h3><a class="mc-link" href="agenda.php">Voir la semaine</a></div>
            <?php if ($journee): ?>
                <ul class="mc-tl">
                    <?php foreach ($journee as $c):
                        $classes = 'mc-tl__item ' . $c['classe'];
                        if (!$c['cours']) $classes .= ' mc-tl__item--perso';
                        if ($c['etat'] === 'passe') $classes .= ' mc-tl__item--past';
                        if ($c['etat'] === 'maintenant') $classes .= ' mc-tl__item--now'; ?>
                        <li class="<?= $classes ?>">
                            <span class="mc-tl__time"><?= e(horaire($c)) ?></span>
                            <span class="mc-tl__body"><span class="mc-dot"></span><span class="mc-tl__name" title="<?= e($c['titre']) ?>"><?= e($c['titre']) ?></span><?= etiquette($c['categorie']) ?><?php if ($c['salle']): ?><span class="mc-tl__room"><?= e($c['salle']) ?></span><?php endif; ?></span>
                            <?php if (!$c['cours']): ?>
                                <span class="mc-meta"><?= e(['reunion' => 'réunion', 'perso' => 'perso'][$c['type']] ?? 'autre') ?></span>
                            <?php elseif ($c['etat'] === 'maintenant'): ?>
                                <span class="mc-live mc-tl__live">Maintenant</span>
                            <?php elseif ($c['etat'] === 'passe' && $c['nb_notes']): ?>
                                <span class="mc-ok"><?= icone('coche', 'mc-ico-sm') ?><?= pluriel($c['nb_notes'], 'note') ?></span>
                            <?php elseif ($c['etat'] === 'passe'): ?>
                                <button type="button" class="mc-btn mc-btn--sm" data-action="nouvelle-note" data-evenement="<?= $c['id'] ?>"
                                        title="Créer « <?= e($c['titre_note']) ?> »"><?= icone('plus', 'mc-ico-sm') ?>Notes</button>
                            <?php elseif ($c['journee']): ?>
                                <span class="mc-meta">aujourd'hui</span>
                            <?php else: ?>
                                <span class="mc-meta">dans <?= e(duree_texte((int) ceil(($c['debut'] - $maintenant) / 60))) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?php
                $stmt = db()->prepare('SELECT COUNT(*) FROM agenda_sources WHERE utilisateur_id = ?');
                $stmt->execute([$uid]);
                echo $stmt->fetchColumn()
                    ? html_vide('Rien de prévu aujourd\'hui. Profites-en pour relire tes dernières notes ou avancer une tâche.',
                        '<a class="mc-btn mc-btn--sm" href="agenda.php">' . icone('agenda', 'mc-ico-sm') . 'Voir la semaine</a>')
                    : html_vide('Connecte ton emploi du temps : tes cours s\'afficheront ici, avec un bouton pour prendre des notes.',
                        '<a class="mc-btn mc-btn--sm" href="agenda.php#emploi-du-temps">' . icone('lien', 'mc-ico-sm') . 'Connecter l\'emploi du temps</a>');
                ?>
            <?php endif; ?>
        </section>
    </div>

    <div class="mc-col-5 mc-stack">
        <!-- TaskList -->
        <section class="mc-card" aria-label="À faire" id="taches">
            <div class="mc-card__head"><h3 class="mc-h">À faire</h3>
                <span class="mc-meta" id="taches-compteur"><?= $nb_ouvertes ? pluriel($nb_ouvertes, 'ouverte') : 'tout est fait' ?></span></div>
            <form class="mc-quickadd-form" id="form-ajout-tache">
                <label class="mc-quickadd"><?= icone('plus', 'mc-ico-sm') ?><span class="mc-sr">Ajouter une tâche</span>
                    <input id="ajout-tache" data-ajout-tache name="titre" maxlength="255" autocomplete="off"
                           placeholder="Ajouter une tâche…" aria-describedby="ajout-tache-aide" aria-keyshortcuts="T"><span class="mc-kbd">T</span></label>
                <p class="mc-sr" id="ajout-tache-aide">« pour vendredi » fixe la date, un nom de matière la range.</p>
            </form>
            <ul class="mc-tasks" id="liste-taches-accueil">
                <?php foreach ($taches as $t):
                    $m = $t['matiere_id'] ? ($matieres[(int) $t['matiere_id']] ?? null) : null;
                    [$quand, $bientot] = quand_tache($t);
                    $meta = ($m ? '<span class="mc-dot"></span><span class="mc-task__matiere">' . e($m['court']) . '</span>' : '')
                          . ($m && $quand ? '<span aria-hidden="true">·</span>' : '')
                          . ($quand ? '<span class="mc-when' . ($bientot ? ' mc-when--soon' : '') . '">' . e($quand) . '</span>' : ''); ?>
                    <li class="mc-task <?= $m ? e($m['classe']) : 'mc-ue-autre' ?><?= $t['fait'] ? ' mc-task--done' : '' ?>" data-id="<?= (int) $t['id'] ?>"
                        data-meta="<?= e($meta) ?>">
                        <input type="checkbox" class="mc-check" <?= $t['fait'] ? 'checked aria-label="Rouvrir"' : 'aria-label="Terminer"' ?>>
                        <div><div class="mc-task__text"><?= e($t['titre']) ?></div>
                            <div class="mc-task__meta"><?= $t['fait']
                                ? ($t['date_fait'] ? 'Fait à ' . e(date('H:i', strtotime($t['date_fait']))) : 'Fait')
                                : $meta ?></div></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$taches): ?>
                <p class="mc-meta mc-taches-vide" id="taches-vide">Rien à faire pour l'instant. Note ici ce qu'il te reste à finir, tu le retrouveras demain.</p>
            <?php endif; ?>
        </section>

        <!-- Échéances (Deadline / EmptyState) -->
        <section class="mc-card" aria-label="Échéances">
            <div class="mc-card__head"><h3 class="mc-h">Échéances</h3><?php if ($echeances): ?><a class="mc-link" href="echeances.php">Toutes</a><?php endif; ?></div>
            <?php if ($echeances): ?>
                <div class="mc-deadlines">
                    <?php foreach ($echeances as $ec):
                        $ts = strtotime($ec['date_echeance']);
                        $m = $ec['matiere_id'] ? ($matieres[(int) $ec['matiere_id']] ?? null) : null;
                        $delai = delai_texte($ts, true);
                        $bientot = $delai === 'en retard' || ecart_jours($ts) <= 3; ?>
                        <div class="mc-deadline<?= $bientot ? ' mc-deadline--soon' : '' ?> <?= $m ? e($m['classe']) : 'mc-ue-autre' ?>">
                            <div class="mc-deadline__date"><span class="mc-deadline__day"><?= (int) date('j', $ts) ?></span><span class="mc-deadline__month"><?= MOIS_COURTS[(int) date('n', $ts) - 1] ?></span></div>
                            <div class="mc-deadline__body"><span class="mc-deadline__title"><?= e($ec['titre']) ?></span>
                                <span class="mc-meta"><?php if ($m): ?><span class="mc-dot"></span> <?= e($m['court']) ?> · <?php endif; ?><?= $types_echeance[$ec['type']] ? e($types_echeance[$ec['type']]) . ' · ' : '' ?><span class="mc-when<?= $bientot ? ' mc-when--soon' : '' ?>"><?= e($delai) ?></span></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= html_vide('Rien à rendre cette semaine. Ajoute un partiel ou un rendu dès qu\'il est annoncé, il apparaîtra ici 7 jours avant.',
                    '<a class="mc-btn mc-btn--sm" href="echeances.php#ajout">' . icone('plus', 'mc-ico-sm') . 'Ajouter une échéance</a>') ?>
            <?php endif; ?>
        </section>
    </div>

    <!-- SubjectList -->
    <div class="mc-col-8">
        <section class="mc-card" aria-label="Mes matières">
            <div class="mc-card__head"><h3 class="mc-h">Mes matières</h3><span class="mc-meta"><?= pluriel($nb_notes, 'note') ?> · <?= pluriel($nb_matieres, 'matière') ?></span></div>
            <?php if ($ues): ?>
                <div class="mc-two">
                    <div><?= html_blocs_ue($col_gauche) ?></div>
                    <div><?= html_blocs_ue($col_droite) ?></div>
                </div>
            <?php else: ?>
                <?= html_vide('Crée tes UE et leurs matières : chaque note sera rangée au bon endroit.',
                    '<a class="mc-btn mc-btn--sm" href="reglages.php#structure">' . icone('plus', 'mc-ico-sm') . 'Ajouter une UE</a>') ?>
            <?php endif; ?>
        </section>
    </div>

    <!-- Reprendre (NoteRow) -->
    <div class="mc-col-4">
        <section class="mc-card" aria-label="Dernières notes">
            <div class="mc-card__head"><h3 class="mc-h">Reprendre</h3><a class="mc-link" href="recherche.php">Toutes</a></div>
            <?php if ($dernieres): ?>
                <?php foreach ($dernieres as $note) echo html_note_row($note, $uid); ?>
            <?php else: ?>
                <?= html_vide('Ta première note apparaîtra ici, pour la reprendre en un clic.',
                    '<button type="button" class="mc-btn mc-btn--sm" data-action="nouvelle-note">' . icone('plus', 'mc-ico-sm') . 'Nouvelle note</button>') ?>
            <?php endif; ?>
        </section>
    </div>
</div>

<script id="matieres-taches" type="application/json"><?= json_encode($motifs_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script defer src="assets/js/accueil.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
