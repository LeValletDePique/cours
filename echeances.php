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
            m.nom AS matiere
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

/** Affiche un groupe d'échéances. */
function afficher_groupe(string $titre, array $liste, string $classe = ''): void
{
    if (!$liste) return;
    echo '<h2>' . e($titre) . '</h2><ul class="liste-echeances ' . e($classe) . '">';
    foreach ($liste as $ec) {
        $date = date('d/m/Y à H:i', strtotime($ec['date_echeance']));
        echo '<li data-id="' . (int) $ec['id'] . '">'
           . '<input type="checkbox" class="ech-fait" ' . ($ec['termine'] ? 'checked' : '') . '>'
           . '<span class="ech-type type-' . e($ec['type']) . '">' . e($ec['type']) . '</span>'
           . '<span class="ech-titre">' . e($ec['titre']) . '</span>'
           . '<span class="ech-matiere">' . e($ec['matiere'] ?? '') . '</span>'
           . '<span class="ech-date">' . e($date) . '</span>'
           . '<button type="button" class="ech-suppr" title="Supprimer">✕</button>'
           . '</li>';
    }
    echo '</ul>';
}

$titre_page = 'Échéances';
require __DIR__ . '/includes/header.php';
?>
<h1>Échéances</h1>

<form id="form-echeance" class="form-echeance">
    <input type="text" name="titre" placeholder="Intitulé (ex. DS d'optimisation)" required>
    <select name="type">
        <option value="DS">DS</option>
        <option value="TD">TD</option>
        <option value="rendu">Rendu</option>
        <option value="examen">Examen</option>
        <option value="autre" selected>Autre</option>
    </select>
    <select name="matiere_id">
        <option value="">— Matière —</option>
        <?php foreach ($matieres as $m): ?>
            <option value="<?= (int) $m['id'] ?>"><?= e($m['ue_code'] . ' · ' . $m['nom']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="datetime-local" name="date_echeance" required>
    <button type="submit" class="btn-principal">Ajouter</button>
</form>

<div class="groupes-echeances">
    <?php
    afficher_groupe('⚠️ En retard', $en_retard, 'retard');
    afficher_groupe('📅 À venir', $a_venir);
    afficher_groupe('✅ Terminées', $terminees, 'terminees');
    if (!$toutes) echo '<p class="vide">Aucune échéance. Ajoute ton prochain DS ou rendu ci-dessus.</p>';
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
        } else if (e.target.classList.contains('ech-suppr')) {
            if (!confirm('Supprimer cette échéance ?')) return;
            if ((await api('supprimer', { id })).ok) li.remove();
        }
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
