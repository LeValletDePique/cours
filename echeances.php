<?php
/**
 * Échéances : liste des DS, rendus, examens… avec ajout, validation et
 * suppression. Regroupées en « en retard », « à venir » et « terminées ».
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();

// Matières pour le menu déroulant.
$stmt = db()->prepare(
    'SELECT m.id, m.nom, u.code AS ue_code FROM matieres m
       JOIN ue u ON u.id = m.ue_id
      WHERE u.utilisateur_id = ? ORDER BY u.position, m.position'
);
$stmt->execute([$uid]);
$matieres = $stmt->fetchAll();

// Toutes les échéances.
$stmt = db()->prepare(
    'SELECT e.id, e.titre, e.description, e.type, e.date_echeance, e.termine,
            e.matiere_id, m.nom AS matiere
       FROM echeances e LEFT JOIN matieres m ON m.id = e.matiere_id
      WHERE e.utilisateur_id = ?
      ORDER BY e.date_echeance ASC'
);
$stmt->execute([$uid]);
$toutes = $stmt->fetchAll();

$maintenant = time();
$en_retard = $a_venir = $terminees = [];
foreach ($toutes as $ec) {
    if ($ec['termine']) {
        $terminees[] = $ec;
    } elseif (strtotime($ec['date_echeance']) < $maintenant) {
        $en_retard[] = $ec;
    } else {
        $a_venir[] = $ec;
    }
}

/** Affiche un groupe d'échéances (composant Deadline). */
function afficher_groupe(string $titre, array $liste, int $uid, string $classe = ''): void
{
    if (!$liste) return;
    $matieres = infos_matieres($uid);
    $types = ['DS' => 'DS', 'TD' => 'TD', 'rendu' => 'Rendu', 'examen' => 'Examen', 'autre' => 'Autre'];
    echo '<section class="mc-card mc-page" aria-label="' . e($titre) . '"><div class="mc-card__head"><h2 class="mc-h">'
       . e($titre) . '</h2><span class="mc-meta">' . count($liste) . '</span></div>'
       . '<ul class="mc-deadlines liste-echeances ' . e($classe) . '">';
    foreach ($liste as $ec) {
        $ts = strtotime($ec['date_echeance']);
        $m = $ec['matiere_id'] ? ($matieres[(int) $ec['matiere_id']] ?? null) : null;
        $delai = $ec['termine'] ? 'terminée' : delai_texte($ts, true);
        $bientot = !$ec['termine'] && ($delai === 'en retard' || ecart_jours($ts) <= 3);
        echo '<li class="mc-deadline' . ($bientot ? ' mc-deadline--soon' : '') . ($ec['termine'] ? ' mc-deadline--fait' : '')
           . ' ' . ($m ? e($m['classe']) : 'mc-ue-autre') . '" data-id="' . (int) $ec['id'] . '">'
           . '<input type="checkbox" class="mc-check ech-fait" ' . ($ec['termine'] ? 'checked aria-label="Rouvrir « ' . e($ec['titre']) . ' »"' : 'aria-label="Marquer « ' . e($ec['titre']) . ' » comme faite"') . '>'
           . '<div class="mc-deadline__date"><span class="mc-deadline__day">' . (int) date('j', $ts) . '</span>'
           . '<span class="mc-deadline__month">' . MOIS_COURTS[(int) date('n', $ts) - 1] . '</span></div>'
           . '<div class="mc-deadline__body"><span class="mc-deadline__title">' . e($ec['titre']) . '</span>'
           . '<span class="mc-meta">' . ($m ? '<span class="mc-dot"></span> ' . e($m['court']) . ' · ' : '')
           . '<span class="mc-tag">' . e($types[$ec['type']] ?? $ec['type']) . '</span> · '
           . e(date('H:i', $ts)) . ' · <span class="mc-when' . ($bientot ? ' mc-when--soon' : '') . '">' . e($delai) . '</span></span></div>'
           . '<button type="button" class="mc-btn mc-btn--ghost mc-btn--sm ech-suppr" title="Supprimer" aria-label="Supprimer « ' . e($ec['titre']) . ' »">'
           . icone('corbeille', 'mc-ico-sm') . '</button>'
           . '</li>';
    }
    echo '</ul></section>';
}

$titre_page = 'Échéances';
require __DIR__ . '/includes/header.php';
?>
<header class="mc-hello">
    <p class="mc-eyebrow"><?= count($a_venir) ? pluriel(count($a_venir), 'à venir', 'à venir') : 'Rien à venir' ?><?= $en_retard ? ' · ' . count($en_retard) . ' en retard' : '' ?></p>
    <h1 class="mc-title">Échéances</h1>
</header>

<section class="mc-card mc-page" aria-labelledby="titre-ajout" id="ajout">
    <div class="mc-card__head"><h2 class="mc-h" id="titre-ajout">Ajouter une échéance</h2></div>
    <form id="form-echeance" class="mc-form-ligne">
        <label class="mc-sr" for="ech-titre">Intitulé</label>
        <input class="mc-input mc-input--large" id="ech-titre" type="text" name="titre" placeholder="Intitulé (ex. DS d'optimisation)" required>
        <label class="mc-sr" for="ech-type">Type</label>
        <select class="mc-select" id="ech-type" name="type">
            <option value="DS">DS</option>
            <option value="TD">TD</option>
            <option value="rendu">Rendu</option>
            <option value="examen">Examen</option>
            <option value="autre" selected>Autre</option>
        </select>
        <label class="mc-sr" for="ech-matiere">Matière</label>
        <select class="mc-select" id="ech-matiere" name="matiere_id">
            <option value="">Sans matière</option>
            <?php foreach ($matieres as $m): ?>
                <option value="<?= (int) $m['id'] ?>"><?= e($m['ue_code'] . ' · ' . $m['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="mc-sr" for="ech-date">Date et heure</label>
        <input class="mc-input" id="ech-date" type="datetime-local" name="date_echeance" required>
        <button type="submit" class="mc-btn"><?= icone('plus', 'mc-ico-sm') ?>Ajouter l'échéance</button>
    </form>
</section>

<div class="groupes-echeances mc-stack">
    <?php
    afficher_groupe('En retard', $en_retard, $uid, 'retard');
    afficher_groupe('À venir', $a_venir, $uid);
    afficher_groupe('Terminées', $terminees, $uid, 'terminees');
    if (!$toutes) echo '<div class="mc-page">' . html_vide('Rien à rendre pour l\'instant. Ajoute un partiel ou un rendu dès qu\'il est annoncé : il s\'affichera sur l\'accueil 7 jours avant.') . '</div>';
    ?>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    async function api(action, donnees) {
        return (await fetch('api/echeances.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify(donnees),
        })).json();
    }

    // Ajout
    document.getElementById('form-echeance').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const rep = await api('creer', {
            titre: f.titre.value,
            type: f.type.value,
            matiere_id: f.matiere_id.value ? parseInt(f.matiere_id.value, 10) : 0,
            date_echeance: f.date_echeance.value,
        });
        if (rep.ok) location.reload();
        else alert(rep.erreur || 'Erreur');
    });

    // Cocher / supprimer (délégation)
    document.querySelector('.groupes-echeances').addEventListener('click', async (e) => {
        const li = e.target.closest('li');
        if (!li) return;
        const id = parseInt(li.dataset.id, 10);

        if (e.target.classList.contains('ech-fait')) {
            await api('basculer', { id });
            location.reload();
        } else if (e.target.closest('.ech-suppr')) {
            if (!confirm('Supprimer cette échéance ?')) return;
            if ((await api('supprimer', { id })).ok) li.remove();
        }
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
