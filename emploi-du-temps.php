<?php
/**
 * Emploi du temps : vue semaine des cours importés depuis l'ENT (.ics).
 * Deux façons de le remplir, une seule fois :
 *   - coller le lien d'abonnement .ics (resynchronisé automatiquement) ;
 *   - ou importer le fichier .ics exporté (à réimporter quand il change).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/edt.php';
exiger_connexion();

$uid = utilisateur_id();
$titre_page = 'Emploi du temps';

if (!edt_installe()) {
    require __DIR__ . '/includes/header.php';
    echo '<h1>Emploi du temps</h1><p class="alerte">La base n\'est pas encore prête : '
       . 'lance <code>php migrations/appliquer.php</code> à la racine du projet, puis recharge la page.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// --- Semaine affichée (?semaine=AAAA-MM-JJ, n'importe quel jour de la semaine) ---
$maintenant = edt_maintenant();
$reference  = $maintenant;
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['semaine'] ?? '')) {
    $reference = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['semaine'], edt_fuseau()) ?: $maintenant;
}
$lundi    = $reference->modify('monday this week')->setTime(0, 0);
$lundi_suivant = $lundi->modify('+7 days');

$cours  = edt_cours_entre($uid, $lundi, $lundi_suivant);
$source = edt_source($uid);

// Y a-t-il au moins un cours enregistré (toutes semaines confondues) ?
$stmt = db()->prepare('SELECT COUNT(*) FROM edt_cours WHERE utilisateur_id = ?');
$stmt->execute([$uid]);
$nb_total = (int) $stmt->fetchColumn();

// --- Cours en ce moment (il peut y en avoir plusieurs) / prochain cours (aujourd'hui) ---
$en_cours = []; $prochain = null;
foreach (edt_cours_entre($uid, $maintenant, $maintenant->setTime(23, 59, 59)) as $c) {
    if ($c['journee']) continue;
    $d = new DateTimeImmutable($c['debut'], edt_fuseau());
    if ($d <= $maintenant) $en_cours[] = $c;
    elseif (!$prochain) $prochain = $c;
}

// --- Répartition par jour + plage horaire à afficher ---
$jours = [];                     // 0 = lundi … 6 = dimanche
for ($j = 0; $j < 7; $j++) $jours[$j] = ['journee' => [], 'cours' => []];
$h_min = 8; $h_max = 19;         // plage par défaut, élargie si besoin
foreach ($cours as $c) {
    $d = new DateTimeImmutable($c['debut'], edt_fuseau());
    $f = new DateTimeImmutable($c['fin'], edt_fuseau());
    $j = max(0, (int) $lundi->diff($d->setTime(0, 0))->format('%r%a'));
    if ($j > 6) continue;
    $c['d'] = $d; $c['f'] = $f;
    if ($c['journee'] || $d->format('Y-m-d') !== $f->modify('-1 second')->format('Y-m-d')) {
        $jours[$j]['journee'][] = $c;          // journée entière ou sur plusieurs jours
        continue;
    }
    $jours[$j]['cours'][] = $c;
    $h_min = min($h_min, (int) $d->format('G'));
    $h_max = max($h_max, (int) $f->format('G') + ((int) $f->format('i') > 0 ? 1 : 0));
}

/**
 * Place les cours qui se chevauchent côte à côte : chaque cours reçoit
 * une « voie » et le nombre de voies de son groupe de chevauchement.
 */
function placer_cours(array $liste): array
{
    usort($liste, fn($a, $b) => $a['d'] <=> $b['d']);
    $groupe = []; $fin_groupe = null; $resultat = [];
    $fermer = function () use (&$groupe, &$resultat) {
        $nb = 0;
        foreach ($groupe as $c) $nb = max($nb, $c['voie'] + 1);
        foreach ($groupe as $c) { $c['nb_voies'] = $nb; $resultat[] = $c; }
        $groupe = [];
    };
    foreach ($liste as $c) {
        if ($fin_groupe !== null && $c['d'] >= $fin_groupe) { $fermer(); $fin_groupe = null; }
        // Première voie libre dans le groupe courant.
        $voie = 0;
        while (true) {
            $libre = true;
            foreach ($groupe as $g) {
                if ($g['voie'] === $voie && $g['f'] > $c['d']) { $libre = false; break; }
            }
            if ($libre) break;
            $voie++;
        }
        $c['voie'] = $voie;
        $groupe[] = $c;
        $fin_groupe = $fin_groupe === null ? $c['f'] : max($fin_groupe, $c['f']);
    }
    if ($groupe) $fermer();
    return $resultat;
}

// Samedi / dimanche seulement s'il y a cours.
$nb_jours = 5;
if ($jours[5]['cours'] || $jours[5]['journee']) $nb_jours = 6;
if ($jours[6]['cours'] || $jours[6]['journee']) $nb_jours = 7;

$NOMS_JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
$NOMS_MOIS  = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août',
               'sept.', 'oct.', 'nov.', 'déc.'];
$date_fr = fn(DateTimeImmutable $d) => $d->format('j') . ' ' . $NOMS_MOIS[(int) $d->format('n') - 1];

/** « il y a 12 min », « il y a 3 h », « le 28/09 à 14:05 ». */
function il_y_a(string $date): string
{
    $d = new DateTimeImmutable($date, edt_fuseau());
    $s = edt_maintenant()->getTimestamp() - $d->getTimestamp();
    if ($s < 60)    return 'à l\'instant';
    if ($s < 3600)  return 'il y a ' . intdiv($s, 60) . ' min';
    if ($s < 86400) return 'il y a ' . intdiv($s, 3600) . ' h';
    return 'le ' . $d->format('d/m à H:i');
}

$heure_px = 52;                                   // hauteur d'une heure (px), aussi dans le CSS
$synchro_auto = edt_synchro_a_faire($source);

require __DIR__ . '/includes/header.php';
?>
<h1>Emploi du temps</h1>

<?php if ($nb_total > 0): ?>
<!-- Maintenant -->
<section class="edt-maintenant">
    <?php foreach ($en_cours as $c): ?>
        <div class="edt-encart edt-encart-actuel" style="--teinte: <?= edt_teinte($c) ?>">
            <span class="edt-encart-libelle">En ce moment</span>
            <strong><?= e($c['intitule']) ?></strong>
            <span><?= e(substr($c['debut'], 11, 5)) ?>–<?= e(substr($c['fin'], 11, 5)) ?>
                <?= $c['lieu'] ? '· ' . e($c['lieu']) : '' ?></span>
        </div>
    <?php endforeach; ?>
    <?php if ($prochain): ?>
        <div class="edt-encart" style="--teinte: <?= edt_teinte($prochain) ?>">
            <span class="edt-encart-libelle">Prochain cours</span>
            <strong><?= e($prochain['intitule']) ?></strong>
            <span><?= e(substr($prochain['debut'], 11, 5)) ?>–<?= e(substr($prochain['fin'], 11, 5)) ?>
                <?= $prochain['lieu'] ? '· ' . e($prochain['lieu']) : '' ?></span>
        </div>
    <?php endif; ?>
    <?php if (!$en_cours && !$prochain): ?>
        <p class="vide">Plus de cours aujourd'hui.</p>
    <?php endif; ?>
</section>

<!-- Navigation entre les semaines -->
<nav class="edt-semaines">
    <a class="btn-secondaire" href="?semaine=<?= $lundi->modify('-7 days')->format('Y-m-d') ?>"
       title="Semaine précédente">‹</a>
    <a class="btn-secondaire" href="emploi-du-temps.php">Cette semaine</a>
    <a class="btn-secondaire" href="?semaine=<?= $lundi_suivant->format('Y-m-d') ?>"
       title="Semaine suivante">›</a>
    <span class="edt-periode">Semaine du <?= e($date_fr($lundi)) ?>
        au <?= e($date_fr($lundi->modify('+' . ($nb_jours - 1) . ' days'))) ?>
        <?= e($lundi->format('Y')) ?></span>
</nav>

<!-- Grille de la semaine -->
<div class="edt-grille" style="--nb-jours: <?= $nb_jours ?>; --heure: <?= $heure_px ?>px; --nb-heures: <?= $h_max - $h_min ?>">
    <div class="edt-heures" aria-hidden="true">
        <?php for ($h = $h_min; $h < $h_max; $h++): ?>
            <span style="top: calc(var(--heure) * <?= $h - $h_min ?>)"><?= $h ?>h</span>
        <?php endfor; ?>
    </div>

    <?php for ($j = 0; $j < $nb_jours; $j++):
        $jour = $lundi->modify("+$j days");
        $aujourdhui = $jour->format('Y-m-d') === $maintenant->format('Y-m-d');
    ?>
        <section class="edt-jour<?= $aujourdhui ? ' aujourdhui' : '' ?>">
            <h2 class="edt-jour-titre"><?= $NOMS_JOURS[$j] ?> <small><?= e($date_fr($jour)) ?></small></h2>

            <div class="edt-colonne">
                <?php if ($jours[$j]['journee']): ?>
                    <div class="edt-journees">
                        <?php foreach ($jours[$j]['journee'] as $c): ?>
                            <div class="edt-journee" style="--teinte: <?= edt_teinte($c) ?>"
                                 title="<?= e($c['intitule']) ?>"><?= e($c['intitule']) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($aujourdhui):
                    $pos = ((int) $maintenant->format('G') - $h_min) + (int) $maintenant->format('i') / 60;
                    if ($pos >= 0 && $pos <= $h_max - $h_min): ?>
                    <div class="edt-ligne-maintenant" style="top: calc(var(--heure) * <?= round($pos, 3) ?>)"></div>
                <?php endif; endif; ?>

                <?php foreach (placer_cours($jours[$j]['cours']) as $c):
                    $haut   = ((int) $c['d']->format('G') - $h_min) + (int) $c['d']->format('i') / 60;
                    $hauteur = ($c['f']->getTimestamp() - $c['d']->getTimestamp()) / 3600;
                    $fini   = $c['f'] <= $maintenant;
                    $infobulle = $c['intitule'] . "\n" . $c['d']->format('H:i') . '–' . $c['f']->format('H:i')
                        . ($c['lieu'] ? "\n" . $c['lieu'] : '')
                        . ($c['matiere'] ? "\nMatière : " . $c['matiere'] : '')
                        . ($c['description'] ? "\n\n" . $c['description'] : '');
                    $balise = $c['matiere_id'] ? 'a' : 'div';
                ?>
                    <<?= $balise ?> class="edt-cours<?= $fini ? ' fini' : '' ?><?= $c['nb_voies'] >= 3 ? ' etroit' : '' ?>"
                        <?= $c['matiere_id'] ? 'href="matiere.php?id=' . (int) $c['matiere_id'] . '"' : '' ?>
                        title="<?= e($infobulle) ?>"
                        style="--teinte: <?= edt_teinte($c) ?>;
                               top: calc(var(--heure) * <?= round($haut, 3) ?>);
                               height: calc(var(--heure) * <?= round($hauteur, 3) ?> - 2px);
                               left: calc(100% * <?= $c['voie'] ?> / <?= $c['nb_voies'] ?>);
                               width: calc(100% / <?= $c['nb_voies'] ?> - 2px)">
                        <span class="edt-cours-heure"><?= $c['d']->format('H:i') ?>–<?= $c['f']->format('H:i') ?></span>
                        <span class="edt-cours-titre"><?= e($c['intitule']) ?></span>
                        <?php if ($c['lieu']): ?><span class="edt-cours-lieu"><?= e($c['lieu']) ?></span><?php endif; ?>
                    </<?= $balise ?>>
                <?php endforeach; ?>
            </div>

            <?php if (!$jours[$j]['cours'] && !$jours[$j]['journee']): ?>
                <p class="edt-jour-vide">Pas de cours</p>
            <?php endif; ?>
        </section>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Connexion de l'emploi du temps -->
<details class="bloc edt-source" <?= $nb_total === 0 ? 'open' : '' ?>>
    <summary>
        <?php if ($source && $source['url']): ?>
            🔗 Emploi du temps connecté —
            <span id="edt-etat"><?= $source['derniere_synchro']
                ? 'synchronisé ' . e(il_y_a($source['derniere_synchro'])) : 'jamais synchronisé' ?></span>
        <?php elseif ($nb_total > 0): ?>
            📄 Emploi du temps importé depuis un fichier — mettre à jour
        <?php else: ?>
            ➕ Connecter mon emploi du temps
        <?php endif; ?>
    </summary>

    <?php if ($source && $source['derniere_erreur']): ?>
        <p class="alerte">Dernière synchronisation ratée : <?= e($source['derniere_erreur']) ?>
            (les cours affichés sont ceux de la dernière synchronisation réussie).</p>
    <?php endif; ?>

    <div class="edt-source-choix">
        <form id="edt-form-lien" class="edt-form">
            <h3>Par lien (recommandé)</h3>
            <p class="astuce-mini">Dans l'ENT, ouvre ton emploi du temps et cherche « Exporter »,
                « S'abonner », « iCal » ou « Synchroniser avec un agenda », puis copie le lien.
                Il sera resynchronisé tout seul (toutes les 3 h au plus, quand tu ouvres cette page).</p>
            <input type="url" name="url" required placeholder="https://… ou webcal://…"
                   value="<?= e($source['url'] ?? '') ?>">
            <div class="edt-form-boutons">
                <button type="submit" class="btn-principal"><?= $source && $source['url'] ? 'Changer le lien' : 'Connecter' ?></button>
                <?php if ($source && $source['url']): ?>
                    <button type="button" id="edt-synchro" class="btn-secondaire">Synchroniser maintenant</button>
                    <button type="button" id="edt-oublier" class="btn-secondaire">Déconnecter</button>
                <?php endif; ?>
            </div>
        </form>

        <form id="edt-form-fichier" class="edt-form">
            <h3>Par fichier</h3>
            <p class="astuce-mini">Si l'ENT ne donne pas de lien : exporte le fichier <code>.ics</code>
                et importe-le ici. À refaire quand l'emploi du temps change.</p>
            <input type="file" name="fichier" accept=".ics,text/calendar" required>
            <div class="edt-form-boutons">
                <button type="submit" class="btn-principal">Importer</button>
            </div>
        </form>
    </div>
    <p id="edt-message" class="edt-message" role="status"></p>
    <p class="astuce-mini">La matière de chaque cours est reconnue automatiquement d'après son
        intitulé : un cours reconnu est cliquable et mène à tes notes de la matière.</p>
</details>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const message = document.getElementById('edt-message');

    async function api(action, corps) {
        const options = { method: 'POST', headers: { 'X-CSRF': CSRF } };
        if (corps instanceof FormData) {
            corps.append('csrf', CSRF);
            options.body = corps;
        } else {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(corps || {});
        }
        try {
            return await (await fetch('api/edt.php?action=' + action, options)).json();
        } catch (e) {
            return { erreur: 'Serveur injoignable.' };
        }
    }

    function afficher(rep, enCours) {
        if (enCours) { message.textContent = enCours; message.className = 'edt-message'; return; }
        if (rep.ok && rep.resume) {
            const r = rep.resume;
            message.textContent = r.nb + ' cours enregistrés (du ' + r.du + ' au ' + r.au + '), '
                + r.reconnus + ' rattachés à une matière. Rechargement…';
            message.className = 'edt-message ok';
            setTimeout(() => location.reload(), 900);
        } else {
            message.textContent = rep.erreur || 'Erreur.';
            message.className = 'edt-message erreur';
        }
    }

    document.getElementById('edt-form-lien').addEventListener('submit', async (e) => {
        e.preventDefault();
        afficher(null, 'Connexion à l\'ENT…');
        afficher(await api('connecter', { url: e.target.url.value }));
    });

    document.getElementById('edt-form-fichier').addEventListener('submit', async (e) => {
        e.preventDefault();
        afficher(null, 'Import…');
        afficher(await api('importer', new FormData(e.target)));
    });

    const btnSynchro = document.getElementById('edt-synchro');
    if (btnSynchro) btnSynchro.addEventListener('click', async () => {
        afficher(null, 'Synchronisation…');
        afficher(await api('synchroniser'));
    });

    const btnOublier = document.getElementById('edt-oublier');
    if (btnOublier) btnOublier.addEventListener('click', async () => {
        if (!confirm('Déconnecter le lien ? Les cours déjà importés restent affichés.')) return;
        if ((await api('oublier')).ok) location.reload();
    });

    // Synchronisation automatique en arrière-plan si la dernière date de plus de 3 h.
    // La page s'affiche tout de suite avec les cours déjà connus (utile sans wifi).
    <?php if ($synchro_auto): ?>
    const etat = document.getElementById('edt-etat');
    if (etat) etat.textContent = 'synchronisation…';
    api('synchroniser').then((rep) => {
        if (rep.ok) location.reload();
        else if (etat) etat.textContent = 'hors ligne — dernière version affichée';
    });
    <?php endif; ?>
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
