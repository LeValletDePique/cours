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
    'SELECT m.id, m.nom, u.code AS ue_code FROM matieres m
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
        <h1>Agenda</h1>
        <div class="agenda-nav">
            <button type="button" class="btn-secondaire" data-nav="-1" title="Précédent (←)">‹</button>
            <button type="button" class="btn-secondaire" data-nav="0" title="Aujourd'hui (T)">Aujourd'hui</button>
            <button type="button" class="btn-secondaire" data-nav="1" title="Suivant (→)">›</button>
            <span class="agenda-periode" id="agenda-periode"></span>
        </div>
        <div class="agenda-vues" role="tablist">
            <button type="button" data-vue="semaine">Semaine</button>
            <button type="button" data-vue="mois">Mois</button>
            <button type="button" data-vue="liste">Liste</button>
        </div>
        <div class="agenda-actions">
            <button type="button" class="btn-principal" id="btn-ajouter">＋ Ajouter</button>
            <button type="button" class="btn-secondaire" id="btn-sources"
                    title="Connecter l'emploi du temps, relier les cours aux matières">🔗 Emploi du temps</button>
        </div>
    </div>
    <p class="agenda-info" id="agenda-info" hidden></p>

    <div class="agenda-corps">
        <div class="agenda-vue" id="agenda-vue"></div>

        <aside class="agenda-cote">
            <section class="bloc">
                <h2>✅ À faire</h2>
                <form id="form-tache" class="form-tache">
                    <input type="text" name="titre" placeholder="Nouvelle tâche…" maxlength="255" required>
                    <input type="date" name="date" title="Pour quand ? (facultatif)">
                    <button type="submit" class="btn-secondaire" title="Ajouter la tâche">＋</button>
                </form>
                <ul class="liste-taches" id="liste-taches"></ul>
            </section>
            <section class="bloc legende">
                <h2>Légende</h2>
                <ul>
                    <li><span class="pastille cat-CM"></span> CM</li>
                    <li><span class="pastille cat-TD"></span> TD</li>
                    <li><span class="pastille type-cours"></span> Autre cours (couleur de l'UE)</li>
                    <li><span class="pastille type-reunion"></span> Réunion</li>
                    <li><span class="pastille type-tache"></span> Tâche</li>
                    <li><span class="pastille type-perso"></span> Perso</li>
                    <li><span class="pastille type-echeance"></span> Échéance (DS, rendu…)</li>
                </ul>
                <p class="astuce-mini">Clique sur un créneau vide pour ajouter un événement à cette heure.</p>
            </section>
        </aside>
    </div>
</div>

<!-- Matières pour les menus (lues par agenda.js) -->
<script id="agenda-matieres" type="application/json"><?=
    json_encode(array_map(fn($m) => ['id' => (int) $m['id'], 'nom' => $m['ue_code'] . ' · ' . $m['nom']], $matieres),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script defer src="assets/js/agenda.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
