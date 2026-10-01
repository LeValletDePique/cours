<?php
/**
 * Agenda : emploi du temps (importé via un lien iCal), réunions, tâches
 * et échéances, en vue semaine / mois / liste.
 * L'affichage est construit par assets/js/agenda.js à partir de api/agenda.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/agenda.php';
exiger_connexion();
installer_agenda();

$uid = utilisateur_id();

// Matières (menu de rattachement d'un événement).
$stmt = db()->prepare(
    'SELECT m.id, m.nom, m.mots_cles, u.code AS ue_code FROM matieres m
       JOIN ue u ON u.id = m.ue_id
      WHERE u.utilisateur_id = ? ORDER BY u.position, m.position'
);
$stmt->execute([$uid]);
$matieres = $stmt->fetchAll();

$titre_page = 'Agenda';
$page_large = true;
require __DIR__ . '/includes/header.php';
?>
<div class="agenda" id="agenda">
    <div class="agenda-barre">
        <h1 class="mc-title">Agenda</h1>
        <div class="agenda-nav">
            <button type="button" class="mc-btn mc-btn--sm" data-nav="-1" title="Précédent (←)" aria-label="Période précédente"><?= icone('gauche', 'mc-ico-sm') ?></button>
            <button type="button" class="mc-btn mc-btn--sm" data-nav="0" title="Revenir à aujourd'hui">Aujourd'hui</button>
            <button type="button" class="mc-btn mc-btn--sm" data-nav="1" title="Suivant (→)" aria-label="Période suivante"><?= icone('droite', 'mc-ico-sm') ?></button>
            <span class="agenda-periode" id="agenda-periode" aria-live="polite"></span>
        </div>
        <div class="agenda-vues mc-seg" role="group" aria-label="Vue">
            <button type="button" data-vue="semaine">Semaine</button>
            <button type="button" data-vue="mois">Mois</button>
            <button type="button" data-vue="liste">Liste</button>
        </div>
        <div class="agenda-actions mc-actions">
            <button type="button" class="mc-btn mc-btn--sm" id="btn-ajouter"><?= icone('plus', 'mc-ico-sm') ?>Ajouter</button>
            <button type="button" class="mc-btn mc-btn--sm" id="btn-sources"
                    title="Connecter l'emploi du temps, relier les cours aux matières"><?= icone('lien', 'mc-ico-sm') ?>Emploi du temps</button>
        </div>
    </div>
    <p class="agenda-info mc-message mc-message--info" id="agenda-info" hidden></p>

    <div class="agenda-corps">
        <div class="agenda-vue" id="agenda-vue"></div>

        <aside class="agenda-cote mc-stack">
            <section class="mc-card" aria-labelledby="titre-a-faire">
                <div class="mc-card__head"><h2 class="mc-h" id="titre-a-faire">À faire</h2></div>
                <form id="form-tache" class="form-tache">
                    <label class="mc-quickadd"><?= icone('plus', 'mc-ico-sm') ?><span class="mc-sr">Nouvelle tâche</span>
                        <input type="text" name="titre" data-ajout-tache placeholder="Ajouter une tâche…" maxlength="255" required
                               aria-keyshortcuts="T" autocomplete="off"><span class="mc-kbd">T</span></label>
                    <div class="mc-form-ligne">
                        <label class="mc-sr" for="tache-date">Pour quand ? (facultatif)</label>
                        <input class="mc-input" id="tache-date" type="date" name="date" title="Pour quand ? (facultatif)">
                        <button type="submit" class="mc-btn mc-btn--sm">Ajouter</button>
                    </div>
                </form>
                <ul class="mc-tasks liste-taches" id="liste-taches"></ul>
            </section>
            <section class="mc-card legende" aria-labelledby="titre-legende">
                <div class="mc-card__head"><h2 class="mc-h" id="titre-legende">Légende</h2></div>
                <ul class="legende-liste">
                    <li><span class="mc-ue mc-ue-1">UE1</span><span class="mc-ue mc-ue-2">UE2</span><span class="mc-ue mc-ue-3">UE3</span><span class="mc-ue mc-ue-4">UE4</span><span class="mc-ue mc-ue-autre">Autre</span> couleur de l'UE</li>
                    <li><span class="mc-tag mc-tag--cm">CM</span><span class="mc-tag">TD</span><span class="mc-tag">TP</span> type de cours</li>
                    <li><span class="legende-perso"></span> réunion, perso, tâche</li>
                    <li><?= icone('echeances', 'mc-ico-sm') ?> échéance (DS, rendu…)</li>
                </ul>
                <p class="mc-meta">Clique sur un créneau vide pour ajouter un événement à cette heure.</p>
            </section>
        </aside>
    </div>
</div>

<!-- Matières pour les menus (lues par agenda.js) -->
<script id="agenda-matieres" type="application/json"><?=
    json_encode(array_map(fn($m) => [
        'id' => (int) $m['id'], 'nom' => $m['ue_code'] . ' · ' . $m['nom'],
        // Pour l'ajout rapide de tâche : nom sans parenthèses + mots-clés.
        'motifs' => array_values(array_filter(array_map('trim',
            array_merge([nom_court($m['nom'])], explode(',', (string) $m['mots_cles']))))),
    ], $matieres),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script defer src="assets/js/agenda.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
