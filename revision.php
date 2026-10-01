<?php
/**
 * Mode révision : cartes question/réponse (flashcards).
 * On peut en créer, en supprimer, et lancer une session de révision
 * (carte à retourner, passage à la suivante, mélange).
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();

// Matières (création + filtre de révision).
$stmt = db()->prepare(
    'SELECT m.id, m.nom, u.code AS ue_code FROM matieres m
       JOIN ue u ON u.id = m.ue_id
      WHERE u.utilisateur_id = ? ORDER BY u.position, m.position'
);
$stmt->execute([$uid]);
$matieres = $stmt->fetchAll();

// Toutes les cartes.
$stmt = db()->prepare(
    'SELECT f.id, f.question, f.reponse, f.matiere_id, m.nom AS matiere
       FROM flashcards f LEFT JOIN matieres m ON m.id = f.matiere_id
      WHERE f.utilisateur_id = ? ORDER BY f.date_creation DESC'
);
$stmt->execute([$uid]);
$cartes = $stmt->fetchAll();

$titre_page = 'Révision';
require __DIR__ . '/includes/header.php';
?>
<header class="mc-hello">
    <p class="mc-eyebrow"><?= pluriel(count($cartes), 'fiche') ?></p>
    <h1 class="mc-title">Révision</h1>
</header>

<div class="mc-grid">
    <!-- Zone de révision -->
    <section class="mc-card mc-col-7" aria-labelledby="titre-reviser">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-reviser">Réviser</h2></div>
        <div class="mc-form-ligne">
            <label class="mc-sr" for="filtre-matiere">Matière à réviser</label>
            <select class="mc-select" id="filtre-matiere">
                <option value="">Toutes les matières</option>
                <?php foreach ($matieres as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"><?= e($m['ue_code'] . ' · ' . $m['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="btn-reviser" class="mc-btn"><?= icone('lecture', 'mc-ico-sm') ?>Commencer</button>
        </div>
        <div id="zone-carte" class="mc-fiche" hidden>
            <div class="mc-fiche__face" id="carte-contenu" aria-live="polite"></div>
            <div class="mc-actions mc-fiche__actions">
                <button type="button" id="btn-retourner" class="mc-btn"><?= icone('retourner', 'mc-ico-sm') ?>Retourner</button>
                <button type="button" id="btn-suivante" class="mc-btn"><?= icone('suivant', 'mc-ico-sm') ?>Suivante</button>
            </div>
            <p class="mc-meta" id="carte-progression"></p>
        </div>
    </section>

    <!-- Création d'une carte -->
    <section class="mc-card mc-col-5" aria-labelledby="titre-nouvelle-carte">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-nouvelle-carte">Nouvelle fiche</h2></div>
        <form id="form-carte" class="mc-form">
            <label class="mc-label">Question (recto)
                <textarea class="mc-textarea" name="question" required></textarea></label>
            <label class="mc-label">Réponse (verso)
                <textarea class="mc-textarea" name="reponse" required></textarea></label>
            <label class="mc-label">Matière
                <select class="mc-select" name="matiere_id">
                    <option value="">Sans matière</option>
                    <?php foreach ($matieres as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= e($m['ue_code'] . ' · ' . $m['nom']) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <div><button type="submit" class="mc-btn"><?= icone('plus', 'mc-ico-sm') ?>Ajouter la fiche</button></div>
        </form>
    </section>

    <!-- Liste des cartes -->
    <section class="mc-card mc-col-12" aria-labelledby="titre-cartes">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-cartes">Mes fiches</h2><span class="mc-meta"><?= count($cartes) ?></span></div>
        <ul class="mc-fiches liste-cartes" id="liste-cartes">
            <?php $infos = infos_matieres($uid); foreach ($cartes as $c):
                $m = $c['matiere_id'] ? ($infos[(int) $c['matiere_id']] ?? null) : null; ?>
                <li class="<?= $m ? e($m['classe']) : 'mc-ue-autre' ?>" data-id="<?= (int) $c['id'] ?>">
                    <span class="mc-fiches__q"><?= e($c['question']) ?></span>
                    <span class="mc-fiches__r"><?= e($c['reponse']) ?></span>
                    <span class="mc-meta mc-fiches__m"><?php if ($m): ?><span class="mc-dot"></span> <?= e($m['court']) ?><?php endif; ?></span>
                    <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm carte-suppr" title="Supprimer"
                            aria-label="Supprimer la fiche"><?= icone('corbeille', 'mc-ico-sm') ?></button>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$cartes): ?>
            <?= html_vide('Pas encore de fiche. Écris une question et sa réponse à côté : tu pourras les réviser mélangées avant un partiel.') ?>
        <?php endif; ?>
    </section>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    async function api(action, donnees) {
        return (await fetch('api/flashcards.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify(donnees),
        })).json();
    }

    // Données des cartes (fournies par le serveur).
    const CARTES = <?= json_encode(array_map(fn($c) => [
        'id' => (int) $c['id'], 'q' => $c['question'],
        'r' => $c['reponse'], 'm' => (int) $c['matiere_id'],
    ], $cartes), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    // ---- Ajout d'une carte ----
    document.getElementById('form-carte').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const rep = await api('creer', {
            question: f.question.value,
            reponse: f.reponse.value,
            matiere_id: f.matiere_id.value ? parseInt(f.matiere_id.value, 10) : 0,
        });
        if (rep.ok) location.reload();
        else alert(rep.erreur || 'Erreur');
    });

    // ---- Suppression ----
    document.getElementById('liste-cartes').addEventListener('click', async (e) => {
        if (!e.target.closest('.carte-suppr')) return;
        const li = e.target.closest('li');
        if ((await api('supprimer', { id: parseInt(li.dataset.id, 10) })).ok) li.remove();
    });

    // ---- Session de révision ----
    let paquet = [], index = 0, recto = true;
    const zone = document.getElementById('zone-carte');
    const contenu = document.getElementById('carte-contenu');
    const progression = document.getElementById('carte-progression');

    function melanger(t) { for (let i = t.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1)); [t[i], t[j]] = [t[j], t[i]]; } return t; }

    function afficher() {
        const carte = paquet[index];
        recto = true;
        contenu.textContent = carte.q;
        contenu.classList.remove('verso');
        progression.textContent = 'Fiche ' + (index + 1) + ' / ' + paquet.length;
    }

    document.getElementById('btn-reviser').addEventListener('click', () => {
        const filtre = document.getElementById('filtre-matiere').value;
        paquet = filtre ? CARTES.filter(c => c.m === parseInt(filtre, 10)) : CARTES.slice();
        if (!paquet.length) { alert('Pas de fiche pour cette matière. Ajoute-en une à côté.'); return; }
        melanger(paquet); index = 0; zone.hidden = false; afficher();
    });
    document.getElementById('btn-retourner').addEventListener('click', () => {
        recto = !recto;
        contenu.textContent = recto ? paquet[index].q : paquet[index].r;
        contenu.classList.toggle('verso', !recto);
    });
    document.getElementById('btn-suivante').addEventListener('click', () => {
        index = (index + 1) % paquet.length;
        afficher();
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
