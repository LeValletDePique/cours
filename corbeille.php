<?php
/**
 * Corbeille : notes supprimées (soft delete). On peut les restaurer
 * ou les supprimer définitivement.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();

$stmt = db()->prepare(
    'SELECT n.id, n.titre, n.matiere_id, n.date_suppression, m.nom AS matiere
       FROM notes n LEFT JOIN matieres m ON m.id = n.matiere_id
      WHERE n.utilisateur_id = ? AND n.supprime = 1
      ORDER BY n.date_suppression DESC'
);
$stmt->execute([$uid]);
$notes = $stmt->fetchAll();

$titre_page = 'Corbeille';
require __DIR__ . '/includes/header.php';
?>
<header class="mc-hello mc-page">
    <p class="mc-eyebrow"><?= $notes ? pluriel(count($notes), 'note') : 'Vide' ?></p>
    <div class="mc-entete">
        <h1 class="mc-title">Corbeille</h1>
        <?php if ($notes): ?>
            <button type="button" id="btn-vider" class="mc-btn mc-btn--sm"><?= icone('corbeille', 'mc-ico-sm') ?>Vider la corbeille</button>
        <?php endif; ?>
    </div>
</header>

<section class="mc-card mc-page" aria-label="Notes supprimées">
    <?php if ($notes): $infos = infos_matieres($uid); ?>
        <ul class="mc-lignes" id="liste-corbeille">
            <?php foreach ($notes as $note):
                $m = $note['matiere_id'] ? ($infos[(int) $note['matiere_id']] ?? null) : null; ?>
                <li class="mc-ligne <?= $m ? e($m['classe']) : 'mc-ue-autre' ?>" data-id="<?= (int) $note['id'] ?>">
                    <div class="mc-ligne__corps">
                        <span class="mc-note__title"><?= e($note['titre']) ?></span>
                        <span class="mc-note__sub"><span class="mc-dot"></span><?= e($m ? $m['court'] : 'Non classée') ?> ·
                            supprimée le <?= e(date('d/m/Y à H:i', strtotime($note['date_suppression']))) ?></span>
                    </div>
                    <div class="mc-actions">
                        <button type="button" class="mc-btn mc-btn--sm" data-corbeille="restaurer"><?= icone('restaurer', 'mc-ico-sm') ?>Restaurer</button>
                        <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-corbeille="definitif"><?= icone('fermer', 'mc-ico-sm') ?>Supprimer</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <?= html_vide('La corbeille est vide. Une note supprimée reste ici jusqu\'à ce que tu la vides : tu peux toujours la restaurer.') ?>
    <?php endif; ?>
</section>

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
        const btn = e.target.closest('button[data-corbeille]');
        if (!btn) return;
        const li = btn.closest('li');
        const id = parseInt(li.dataset.id, 10);

        if (btn.dataset.corbeille === 'restaurer') {
            if ((await api('restaurer', { id })).ok) li.remove();
        } else {
            if (!confirm('Supprimer définitivement cette note ? Tu ne pourras plus la récupérer.')) return;
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
