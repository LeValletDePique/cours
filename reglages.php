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
<h1>Réglages</h1>

<?php if ($message): ?><p class="succes"><?= e($message) ?></p><?php endif; ?>
<?php if ($erreur): ?><p class="alerte"><?= e($erreur) ?></p><?php endif; ?>

<!-- Apparence -->
<section class="bloc">
    <h2>Apparence</h2>
    <label>Thème :
        <select id="choix-theme">
            <option value="clair"  <?= $user['theme'] === 'clair'  ? 'selected' : '' ?>>Clair</option>
            <option value="sombre" <?= $user['theme'] === 'sombre' ? 'selected' : '' ?>>Sombre</option>
        </select>
    </label>
</section>

<!-- Mot de passe -->
<section class="bloc">
    <h2>Mot de passe</h2>
    <form method="post" class="formulaire" style="max-width:400px">
        <?= champ_csrf() ?>
        <input type="hidden" name="form" value="mdp">
        <label>Mot de passe actuel <input type="password" name="actuel" required></label>
        <label>Nouveau mot de passe <input type="password" name="nouveau" required minlength="8"></label>
        <label>Confirmer <input type="password" name="confirm" required minlength="8"></label>
        <button type="submit" class="btn-principal">Changer</button>
    </form>
</section>

<!-- Structure UE / matières -->
<section class="bloc">
    <h2>Mes UE et matières</h2>
    <p class="astuce-mini">Ajoute, renomme ou supprime tes UE et matières. Supprimer une UE
       supprime aussi ses matières et leurs notes.</p>

    <div id="structure">
        <?php foreach ($ues as $ueId => $ue): ?>
            <div class="reglage-ue" data-ue-id="<?= (int) $ueId ?>" data-code="<?= e($ue['code']) ?>">
                <div class="reglage-ue-titre">
                    <span class="badge-ue" style="background: <?= e($ue['couleur']) ?>"><?= e($ue['code']) ?></span>
                    <strong><?= e($ue['nom']) ?></strong>
                    <button type="button" class="mini" data-act="ue-renommer">✎</button>
                    <button type="button" class="mini danger" data-act="ue-supprimer">🗑</button>
                </div>
                <ul class="reglage-matieres">
                    <?php foreach ($ue['matieres'] as $m): ?>
                        <li data-matiere-id="<?= (int) $m['id'] ?>">
                            <span><?= e($m['nom']) ?></span>
                            <button type="button" class="mini" data-act="matiere-renommer">✎</button>
                            <button type="button" class="mini danger" data-act="matiere-supprimer">🗑</button>
                        </li>
                    <?php endforeach; ?>
                    <li class="ajout-inline">
                        <input type="text" class="nouvelle-matiere" placeholder="Nouvelle matière…">
                        <button type="button" class="btn-secondaire" data-act="matiere-creer">Ajouter</button>
                    </li>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="ajout-ue">
        <input type="text" id="nouvelle-ue-code" placeholder="Code (UE5)" style="max-width:110px">
        <input type="text" id="nouvelle-ue-nom" placeholder="Nom de la nouvelle UE">
        <button type="button" class="btn-secondaire" id="btn-ajouter-ue">Ajouter une UE</button>
    </div>
</section>

<!-- Tags -->
<section class="bloc">
    <h2>Mes tags</h2>
    <div class="chips" id="liste-tags-reglages">
        <?php foreach ($tags as $t): ?>
            <span class="chip" data-tag-id="<?= (int) $t['id'] ?>" style="background: <?= e($t['couleur']) ?>">
                <?= e($t['nom']) ?> <button type="button" class="chip-x" title="Supprimer">✕</button>
            </span>
        <?php endforeach; ?>
    </div>
    <div class="ajout-tag">
        <input type="text" id="nouveau-tag" placeholder="Nouveau tag…" maxlength="50">
        <input type="color" id="couleur-tag" value="#64748b" title="Couleur">
        <button type="button" class="btn-secondaire" id="btn-ajouter-tag">Ajouter</button>
    </div>
</section>

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
            const nom = prompt('Nouveau nom de la matière :', li.querySelector('span').textContent);
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
        if (!e.target.classList.contains('chip-x')) return;
        const chip = e.target.closest('.chip');
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
