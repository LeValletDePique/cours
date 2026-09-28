<?php
/**
 * Éditeur d'une note : Markdown à gauche, aperçu en direct à droite.
 * Coloration du code, formules LaTeX, auto-save, tags, favoris,
 * fichiers joints et export (Markdown / PDF).
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();
$id  = (int) ($_GET['id'] ?? 0);

// --- Charge la note en vérifiant la propriété ---
$stmt = db()->prepare(
    'SELECT id, titre, contenu, matiere_id, epingle, date_modification
       FROM notes
      WHERE id = ? AND utilisateur_id = ? AND supprime = 0'
);
$stmt->execute([$id, $uid]);
$note = $stmt->fetch();

if (!$note) {
    http_response_code(404);
    $titre_page = 'Note introuvable';
    require __DIR__ . '/includes/header.php';
    echo '<p class="alerte">Cette note n\'existe pas, a été supprimée, ou ne t\'appartient pas.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// --- Matières (menu de rattachement), groupées par UE ---
$stmt = db()->prepare(
    'SELECT m.id, m.nom, u.code AS ue_code
       FROM matieres m JOIN ue u ON u.id = m.ue_id
      WHERE u.utilisateur_id = ? ORDER BY u.position, m.position'
);
$stmt->execute([$uid]);
$matieres = $stmt->fetchAll();

// --- Tags de la note ---
$stmt = db()->prepare(
    'SELECT t.id, t.nom, t.couleur FROM note_tags nt
       JOIN tags t ON t.id = nt.tag_id
      WHERE nt.note_id = ? ORDER BY t.nom'
);
$stmt->execute([$id]);
$tags_note = $stmt->fetchAll();

// --- Tous les tags de l'utilisateur (suggestions) ---
$stmt = db()->prepare('SELECT nom FROM tags WHERE utilisateur_id = ? ORDER BY nom');
$stmt->execute([$uid]);
$tous_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

// --- Fichiers joints ---
$stmt = db()->prepare(
    'SELECT id, nom_original, type_mime, taille FROM fichiers
      WHERE note_id = ? AND utilisateur_id = ? ORDER BY date_upload'
);
$stmt->execute([$id, $uid]);
$fichiers = $stmt->fetchAll();

$titre_page = $note['titre'];
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" id="hljs-clair"
      href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
<link rel="stylesheet" id="hljs-sombre" disabled
      href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">

<div class="editeur" data-note-id="<?= (int) $note['id'] ?>">
    <div class="editeur-barre">
        <a class="retour" href="<?= $note['matiere_id']
            ? 'matiere.php?id=' . (int) $note['matiere_id'] : 'index.php' ?>">← Retour</a>

        <input type="text" id="note-titre" class="champ-titre"
               value="<?= e($note['titre']) ?>" placeholder="Titre de la note" maxlength="255">

        <select id="note-matiere" class="champ-matiere" title="Matière">
            <option value="">— Non classée —</option>
            <?php foreach ($matieres as $m): ?>
                <option value="<?= (int) $m['id'] ?>"
                    <?= (int) $m['id'] === (int) $note['matiere_id'] ? 'selected' : '' ?>>
                    <?= e($m['ue_code'] . ' · ' . $m['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <span id="statut-save" class="statut-save">Enregistré</span>

        <a class="btn-icone" href="aide.php" target="_blank"
           title="Aide Markdown (nouvel onglet)">❓</a>

        <div class="editeur-onglets">
            <button type="button" data-vue="deux" class="actif">Deux volets</button>
            <button type="button" data-vue="edition">Édition</button>
            <button type="button" data-vue="apercu">Aperçu</button>
        </div>

        <button type="button" id="btn-epingler" class="btn-icone"
                data-epingle="<?= (int) $note['epingle'] ?>"
                title="Épingler comme favori"><?= $note['epingle'] ? '⭐' : '☆' ?></button>
        <button type="button" id="btn-supprimer" class="btn-supprimer"
                title="Envoyer à la corbeille">🗑</button>
    </div>

    <!-- Barre d'outils (actions : assets/js/outils-editeur.js) -->
    <div id="barre-outils" class="barre-outils mathjax_ignore" role="toolbar" aria-label="Mise en forme">
        <button type="button" data-action="annuler" title="Annuler (Ctrl+Z)">↶</button>
        <button type="button" data-action="retablir" title="Rétablir (Ctrl+Y)">↷</button>
        <span class="outil-sep"></span>
        <button type="button" data-action="titre1" title="Grand titre (#)"><b>H1</b></button>
        <button type="button" data-action="titre2" title="Sous-titre (##)"><b>H2</b></button>
        <button type="button" data-action="titre3" title="Petit titre (###)"><b>H3</b></button>
        <span class="outil-sep"></span>
        <button type="button" data-action="gras" title="Gras (Ctrl+B)"><b>G</b></button>
        <button type="button" data-action="italique" title="Italique (Ctrl+I)"><i>I</i></button>
        <button type="button" data-action="barre" title="Barré"><s>S</s></button>
        <div class="outil-deroulant">
            <button type="button" data-ouvrir="couleurs" title="Texte en couleur / surligner">
                <span class="icone-couleur">A</span> ▾</button>
            <div class="outil-menu menu-couleurs" data-menu="couleurs"></div>
        </div>
        <span class="outil-sep"></span>
        <button type="button" data-action="puces" title="Liste à puces">• Liste</button>
        <button type="button" data-action="numeros" title="Liste numérotée">1. Liste</button>
        <button type="button" data-action="cases" title="Cases à cocher">☑</button>
        <button type="button" data-action="citation" title="Citation / rappel">❝</button>
        <span class="outil-sep"></span>
        <button type="button" data-action="tableau"
                title="Créer / modifier un tableau (éditeur visuel). Astuce : tape « Nom | Âge » puis Entrée">▦ Tableau</button>
        <div class="outil-deroulant">
            <button type="button" data-ouvrir="maths" title="Maths : symboles, modèles, recherche (dans une formule, tape \ pour l'autocomplétion)">∑ Maths ▾</button>
            <div class="outil-menu menu-maths" data-menu="maths"></div>
        </div>
        <div class="outil-deroulant">
            <button type="button" data-ouvrir="code" title="Bloc de code (pseudo-code, C, SQL…)">&lt;/&gt; Code ▾</button>
            <div class="outil-menu menu-code" data-menu="code"></div>
        </div>
        <button type="button" data-action="code" title="Code dans le texte">`c`</button>
        <button type="button" data-action="lien" title="Lien">🔗</button>
        <button type="button" data-action="separateur" title="Ligne de séparation">―</button>
        <span class="outil-sep"></span>
        <button type="button" data-action="enregistrer" title="Enregistrer maintenant (Ctrl+S)">💾</button>
    </div>

    <div class="editeur-corps">
        <textarea id="note-contenu" class="editeur-saisie" spellcheck="false"
                  placeholder="Prends tes notes en Markdown…"><?= e($note['contenu']) ?></textarea>
        <div id="apercu" class="editeur-apercu markdown"></div>
    </div>
</div>

<!-- Panneau : tags, fichiers, export -->
<div class="editeur-pied">
    <section class="pied-bloc">
        <h3>🏷️ Tags</h3>
        <div id="liste-tags" class="chips">
            <?php foreach ($tags_note as $t): ?>
                <span class="chip" data-tag-id="<?= (int) $t['id'] ?>"
                      style="background: <?= e($t['couleur']) ?>">
                    <?= e($t['nom']) ?> <button type="button" class="chip-x" title="Retirer">✕</button>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="ajout-tag">
            <input type="text" id="tag-nom" list="tags-suggestions"
                   placeholder="Ajouter un tag…" maxlength="50">
            <datalist id="tags-suggestions">
                <?php foreach ($tous_tags as $nom): ?>
                    <option value="<?= e($nom) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <button type="button" id="tag-ajouter" class="btn-secondaire">Ajouter</button>
        </div>
    </section>

    <section class="pied-bloc">
        <h3>📎 Fichiers joints</h3>
        <ul id="liste-fichiers" class="liste-fichiers">
            <?php foreach ($fichiers as $f): ?>
                <li data-fichier-id="<?= (int) $f['id'] ?>">
                    <a href="telecharger.php?id=<?= (int) $f['id'] ?>" target="_blank">
                        <?= e($f['nom_original']) ?>
                    </a>
                    <span class="taille"><?= round($f['taille'] / 1024) ?> Ko</span>
                    <button type="button" class="fichier-x" title="Supprimer">✕</button>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="ajout-fichier">
            <input type="file" id="fichier-input">
            <button type="button" id="fichier-envoyer" class="btn-secondaire">Importer</button>
            <span id="fichier-statut" class="statut-save"></span>
        </div>
        <p class="astuce-mini">PDF, images, .txt, .docx… (20 Mo max)</p>
    </section>

    <section class="pied-bloc">
        <h3>⬇️ Export</h3>
        <div class="boutons-export">
            <a class="btn-secondaire" href="export.php?type=note&id=<?= (int) $note['id'] ?>&format=md">Markdown</a>
            <a class="btn-secondaire" href="imprimer.php?type=note&id=<?= (int) $note['id'] ?>" target="_blank">PDF</a>
        </div>
        <?php if ($note['matiere_id']): ?>
            <p class="astuce-mini">Toute la matière :</p>
            <div class="boutons-export">
                <a class="btn-secondaire" href="export.php?type=matiere&id=<?= (int) $note['matiere_id'] ?>&format=md">Markdown</a>
                <a class="btn-secondaire" href="imprimer.php?type=matiere&id=<?= (int) $note['matiere_id'] ?>" target="_blank">PDF</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Librairies (CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.9/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="assets/js/mathjax-config.js"></script>
<script async src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-chtml.js"></script>
<script defer src="assets/js/rendu.js"></script>
<script defer src="assets/js/maths-symboles.js"></script>
<script defer src="assets/js/outils-editeur.js"></script>
<script defer src="assets/js/editeur.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
