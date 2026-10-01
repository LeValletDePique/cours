<?php
/**
 * Réglages : mot de passe, thème, et gestion de la structure
 * (UE / matières) et des tags.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();
$message = '';
$erreur  = '';

// --- Changement de mot de passe (POST classique, côté serveur) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'mdp') {
    verifier_csrf();
    $actuel = $_POST['actuel'] ?? '';
    $nouveau = $_POST['nouveau'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $stmt = db()->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id = ?');
    $stmt->execute([$uid]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($actuel, $hash)) {
        $erreur = 'Mot de passe actuel incorrect.';
    } elseif (mb_strlen($nouveau) < 8) {
        $erreur = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
    } elseif ($nouveau !== $confirm) {
        $erreur = 'La confirmation ne correspond pas.';
    } else {
        $stmt = db()->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?');
        $stmt->execute([password_hash($nouveau, PASSWORD_DEFAULT), $uid]);
        $message = 'Mot de passe mis à jour.';
    }
}

$user = utilisateur_connecte();

// --- Structure : UE + matières ---
$stmt = db()->prepare(
    'SELECT u.id AS ue_id, u.code, u.nom AS ue_nom, u.couleur,
            m.id AS matiere_id, m.nom AS matiere_nom
       FROM ue u LEFT JOIN matieres m ON m.ue_id = u.id
      WHERE u.utilisateur_id = ?
      ORDER BY u.position, m.position'
);
$stmt->execute([$uid]);
$ues = [];
foreach ($stmt as $l) {
    $ues[$l['ue_id']] ??= ['code' => $l['code'], 'nom' => $l['ue_nom'],
                           'couleur' => $l['couleur'], 'matieres' => []];
    if ($l['matiere_id']) {
        $ues[$l['ue_id']]['matieres'][] = ['id' => $l['matiere_id'], 'nom' => $l['matiere_nom']];
    }
}

// --- Tags ---
$stmt = db()->prepare('SELECT id, nom, couleur FROM tags WHERE utilisateur_id = ? ORDER BY nom');
$stmt->execute([$uid]);
$tags = $stmt->fetchAll();

$titre_page = 'Réglages';
require __DIR__ . '/includes/header.php';
?>
<header class="mc-hello"><h1 class="mc-title">Réglages</h1></header>

<?php if ($message): ?><p class="mc-message mc-message--ok mc-page" role="status"><?= icone('coche', 'mc-ico-sm') ?><?= e($message) ?></p><?php endif; ?>
<?php if ($erreur): ?><p class="mc-message mc-message--erreur mc-page" role="alert"><?= icone('alerte', 'mc-ico-sm') ?><?= e($erreur) ?></p><?php endif; ?>

<div class="mc-grid mc-page-large">
    <!-- Apparence -->
    <section class="mc-card mc-col-5" aria-labelledby="titre-apparence">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-apparence">Apparence</h2></div>
        <label class="mc-label">Thème
            <select class="mc-select" id="choix-theme">
                <option value="clair"  <?= $user['theme'] === 'clair'  ? 'selected' : '' ?>>Clair</option>
                <option value="sombre" <?= $user['theme'] === 'sombre' ? 'selected' : '' ?>>Sombre</option>
            </select>
        </label>
        <p class="mc-meta">Le bouton lune, en bas de la barre latérale, bascule aussi.</p>
    </section>

    <!-- Mot de passe -->
    <section class="mc-card mc-col-7" aria-labelledby="titre-mdp">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-mdp">Mot de passe</h2></div>
        <form method="post" class="mc-form">
            <?= champ_csrf() ?>
            <input type="hidden" name="form" value="mdp">
            <label class="mc-label">Mot de passe actuel <input class="mc-input" type="password" name="actuel" required autocomplete="current-password"></label>
            <label class="mc-label">Nouveau mot de passe (8 caractères min.) <input class="mc-input" type="password" name="nouveau" required minlength="8" autocomplete="new-password"></label>
            <label class="mc-label">Confirmer <input class="mc-input" type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
            <div><button type="submit" class="mc-btn">Changer le mot de passe</button></div>
        </form>
    </section>

    <!-- Structure UE / matières -->
    <section class="mc-card mc-col-12" aria-labelledby="titre-structure" id="structure-ancre">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-structure">Mes UE et matières</h2></div>
        <p class="mc-meta mc-reglages__aide">Ajoute, renomme ou supprime tes UE et matières. Supprimer une UE
           supprime aussi ses matières et leurs notes. La couleur d'une UE suit son ordre (UE1 à UE4, puis « autre »).</p>

        <div id="structure" class="mc-reglages-ue">
            <?php foreach ($ues as $ueId => $ue): ?>
                <div class="reglage-ue mc-ue-block <?= e(classe_ue($uid, (int) $ueId)) ?>" data-ue-id="<?= (int) $ueId ?>" data-code="<?= e($ue['code']) ?>">
                    <div class="mc-ue-block__head">
                        <span class="mc-ue"><?= e($ue['code']) ?></span>
                        <strong class="mc-ue-block__name"><?= e($ue['nom']) ?></strong>
                        <span class="mc-actions mc-reglages__boutons">
                            <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-act="ue-renommer"
                                    title="Renommer l'UE" aria-label="Renommer l'UE <?= e($ue['code']) ?>"><?= icone('modifier', 'mc-ico-sm') ?></button>
                            <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-act="ue-supprimer"
                                    title="Supprimer l'UE" aria-label="Supprimer l'UE <?= e($ue['code']) ?>"><?= icone('corbeille', 'mc-ico-sm') ?></button>
                        </span>
                    </div>
                    <ul class="mc-lignes">
                        <?php foreach ($ue['matieres'] as $m): ?>
                            <li class="mc-ligne" data-matiere-id="<?= (int) $m['id'] ?>">
                                <span class="mc-ligne__corps"><?= e($m['nom']) ?></span>
                                <span class="mc-actions mc-reglages__boutons">
                                    <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-act="matiere-renommer"
                                            title="Renommer" aria-label="Renommer <?= e($m['nom']) ?>"><?= icone('modifier', 'mc-ico-sm') ?></button>
                                    <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-act="matiere-supprimer"
                                            title="Supprimer" aria-label="Supprimer <?= e($m['nom']) ?>"><?= icone('corbeille', 'mc-ico-sm') ?></button>
                                </span>
                            </li>
                        <?php endforeach; ?>
                        <li class="mc-ligne mc-form-ligne">
                            <input type="text" class="mc-input nouvelle-matiere" placeholder="Nouvelle matière…" aria-label="Nouvelle matière dans <?= e($ue['code']) ?>">
                            <button type="button" class="mc-btn mc-btn--sm" data-act="matiere-creer"><?= icone('plus', 'mc-ico-sm') ?>Ajouter</button>
                        </li>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mc-form-ligne mc-reglages__ajout-ue">
            <input class="mc-input mc-input--court" type="text" id="nouvelle-ue-code" placeholder="Code (UE5)" aria-label="Code de la nouvelle UE">
            <input class="mc-input" type="text" id="nouvelle-ue-nom" placeholder="Nom de la nouvelle UE" aria-label="Nom de la nouvelle UE">
            <button type="button" class="mc-btn" id="btn-ajouter-ue"><?= icone('plus', 'mc-ico-sm') ?>Ajouter une UE</button>
        </div>
    </section>

    <!-- Tags -->
    <section class="mc-card mc-col-12" aria-labelledby="titre-tags">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-tags">Mes tags</h2></div>
        <div class="mc-chips" id="liste-tags-reglages">
            <?php foreach ($tags as $t): ?>
                <span class="mc-chip" data-tag-id="<?= (int) $t['id'] ?>" style="--tag: <?= e($t['couleur']) ?>">
                    <span class="mc-chip__dot"></span><?= e($t['nom']) ?>
                    <button type="button" class="mc-chip__x chip-x" title="Supprimer" aria-label="Supprimer le tag <?= e($t['nom']) ?>"><?= icone('fermer', 'mc-ico-sm') ?></button>
                </span>
            <?php endforeach; ?>
            <?php if (!$tags): ?><span class="mc-meta">Pas encore de tag.</span><?php endif; ?>
        </div>
        <div class="mc-form-ligne">
            <input class="mc-input" type="text" id="nouveau-tag" placeholder="Nouveau tag…" maxlength="50" aria-label="Nom du nouveau tag">
            <input class="mc-input" type="color" id="couleur-tag" value="#64748b" title="Couleur du tag" aria-label="Couleur du tag">
            <button type="button" class="mc-btn" id="btn-ajouter-tag"><?= icone('plus', 'mc-ico-sm') ?>Ajouter</button>
        </div>
    </section>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    async function api(ressource, action, donnees) {
        return (await fetch('api/' + ressource + '.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify(donnees),
        })).json();
    }

    // ---- Thème ----
    document.getElementById('choix-theme').addEventListener('change', async (e) => {
        appliquerTheme(e.target.value);   // assets/js/app.js
    });

    // ---- Structure ----
    document.getElementById('structure').addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-act]'); if (!btn) return;
        const act = btn.dataset.act;
        const ueDiv = btn.closest('.reglage-ue');
        const ueId = parseInt(ueDiv.dataset.ueId, 10);

        if (act === 'ue-renommer') {
            const nom = prompt('Nouveau nom de l\'UE :', ueDiv.querySelector('strong').textContent);
            if (nom) { await api('structure', 'ue_renommer',
                { id: ueId, code: ueDiv.dataset.code, nom }); location.reload(); }
        } else if (act === 'ue-supprimer') {
            if (confirm('Supprimer cette UE, ses matières et ses notes ?')) {
                await api('structure', 'ue_supprimer', { id: ueId }); location.reload(); }
        } else if (act === 'matiere-creer') {
            const input = btn.closest('li').querySelector('.nouvelle-matiere');
            if (input.value.trim()) { await api('structure', 'matiere_creer',
                { ue_id: ueId, nom: input.value.trim() }); location.reload(); }
        } else if (act === 'matiere-renommer') {
            const li = btn.closest('li');
            const nom = prompt('Nouveau nom de la matière :', li.querySelector('span').textContent.trim());
            if (nom) { await api('structure', 'matiere_renommer',
                { id: parseInt(li.dataset.matiereId, 10), nom }); location.reload(); }
        } else if (act === 'matiere-supprimer') {
            const li = btn.closest('li');
            if (confirm('Supprimer cette matière ? (ses notes deviennent « non classées »)')) {
                await api('structure', 'matiere_supprimer',
                    { id: parseInt(li.dataset.matiereId, 10) }); location.reload(); }
        }
    });

    document.getElementById('btn-ajouter-ue').addEventListener('click', async () => {
        const code = document.getElementById('nouvelle-ue-code').value.trim();
        const nom = document.getElementById('nouvelle-ue-nom').value.trim();
        if (!code || !nom) { alert('Code et nom requis.'); return; }
        await api('structure', 'ue_creer', { code, nom }); location.reload();
    });

    // ---- Tags ----
    document.getElementById('liste-tags-reglages').addEventListener('click', async (e) => {
        if (!e.target.closest('.chip-x')) return;
        const chip = e.target.closest('.mc-chip');
        if (confirm('Supprimer ce tag partout ?')) {
            await api('tags', 'supprimer', { tag_id: parseInt(chip.dataset.tagId, 10) });
            chip.remove();
        }
    });
    document.getElementById('btn-ajouter-tag').addEventListener('click', async () => {
        const nom = document.getElementById('nouveau-tag').value.trim();
        const couleur = document.getElementById('couleur-tag').value;
        if (!nom) return;
        const rep = await api('tags', 'creer', { nom, couleur });
        if (rep.ok) location.reload(); else alert(rep.erreur || 'Erreur');
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
