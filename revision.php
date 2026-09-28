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
<h1>Révision — Flashcards</h1>

<!-- Zone de révision -->
<section class="bloc">
    <h2>Réviser</h2>
    <div class="revision-controles">
        <select id="filtre-matiere">
            <option value="">Toutes les matières</option>
            <?php foreach ($matieres as $m): ?>
                <option value="<?= (int) $m['id'] ?>"><?= e($m['ue_code'] . ' · ' . $m['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" id="btn-reviser" class="btn-principal">Commencer</button>
    </div>

    <div id="zone-carte" class="carte-flash" hidden>
        <div class="carte-face" id="carte-contenu"></div>
        <div class="carte-actions">
            <button type="button" id="btn-retourner" class="btn-secondaire">Retourner</button>
            <button type="button" id="btn-suivante" class="btn-principal">Suivante →</button>
        </div>
        <p class="carte-progression" id="carte-progression"></p>
    </div>
</section>

<!-- Création d'une carte -->
<section class="bloc">
    <h2>Nouvelle carte</h2>
    <form id="form-carte" class="form-carte">
        <textarea name="question" placeholder="Question (recto)" required></textarea>
        <textarea name="reponse" placeholder="Réponse (verso)" required></textarea>
        <select name="matiere_id">
            <option value="">— Matière (facultatif) —</option>
            <?php foreach ($matieres as $m): ?>
                <option value="<?= (int) $m['id'] ?>"><?= e($m['ue_code'] . ' · ' . $m['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-principal">Ajouter la carte</button>
    </form>
</section>

<!-- Liste des cartes -->
<section class="bloc">
    <h2>Mes cartes (<?= count($cartes) ?>)</h2>
    <ul class="liste-cartes" id="liste-cartes">
        <?php foreach ($cartes as $c): ?>
            <li data-id="<?= (int) $c['id'] ?>">
                <span class="carte-q"><?= e($c['question']) ?></span>
                <span class="carte-r"><?= e($c['reponse']) ?></span>
                <span class="carte-mat"><?= e($c['matiere'] ?? '') ?></span>
                <button type="button" class="carte-suppr" title="Supprimer">✕</button>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (!$cartes): ?><p class="vide">Aucune carte pour l'instant.</p><?php endif; ?>
</section>

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
        if (!e.target.classList.contains('carte-suppr')) return;
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
        progression.textContent = 'Carte ' + (index + 1) + ' / ' + paquet.length;
    }

    document.getElementById('btn-reviser').addEventListener('click', () => {
        const filtre = document.getElementById('filtre-matiere').value;
        paquet = filtre ? CARTES.filter(c => c.m === parseInt(filtre, 10)) : CARTES.slice();
        if (!paquet.length) { alert('Aucune carte pour cette sélection.'); return; }
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
