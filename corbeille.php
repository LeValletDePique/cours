<?php
/**
 * Corbeille : notes supprimées (soft delete). On peut les restaurer
 * ou les supprimer définitivement.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();

$stmt = db()->prepare(
    'SELECT n.id, n.titre, n.date_suppression, m.nom AS matiere
       FROM notes n LEFT JOIN matieres m ON m.id = n.matiere_id
      WHERE n.utilisateur_id = ? AND n.supprime = 1
      ORDER BY n.date_suppression DESC'
);
$stmt->execute([$uid]);
$notes = $stmt->fetchAll();

$titre_page = 'Corbeille';
require __DIR__ . '/includes/header.php';
?>
<div class="entete-matiere">
    <h1>Corbeille</h1>
    <?php if ($notes): ?>
        <button type="button" id="btn-vider" class="btn-supprimer">Vider la corbeille</button>
    <?php endif; ?>
</div>

<?php if ($notes): ?>
    <ul class="liste-notes grande" id="liste-corbeille">
        <?php foreach ($notes as $note): ?>
            <li data-id="<?= (int) $note['id'] ?>">
                <span class="note-titre"><?= e($note['titre']) ?></span>
                <span class="note-meta">
                    <?= e($note['matiere'] ?? 'Non classée') ?> ·
                    supprimée le <?= e(date('d/m/Y H:i', strtotime($note['date_suppression']))) ?>
                </span>
                <span class="actions-corbeille">
                    <button type="button" class="btn-secondaire" data-action="restaurer">Restaurer</button>
                    <button type="button" class="btn-supprimer" data-action="definitif">Supprimer</button>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="vide">La corbeille est vide.</p>
<?php endif; ?>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    async function api(action, donnees) {
        return (await fetch('api/notes.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify(donnees),
        })).json();
    }

    const liste = document.getElementById('liste-corbeille');
    if (liste) liste.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const li = btn.closest('li');
        const id = parseInt(li.dataset.id, 10);

        if (btn.dataset.action === 'restaurer') {
            if ((await api('restaurer', { id })).ok) li.remove();
        } else {
            if (!confirm('Supprimer définitivement cette note ? (irréversible)')) return;
            if ((await api('supprimer_definitif', { id })).ok) li.remove();
        }
    });

    const vider = document.getElementById('btn-vider');
    if (vider) vider.addEventListener('click', async () => {
        if (!confirm('Vider toute la corbeille ? (irréversible)')) return;
        if ((await api('vider_corbeille', {})).ok) location.reload();
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
