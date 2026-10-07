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
    echo html_vide('Cette note n\'existe pas, est dans la corbeille ou ne t\'appartient pas.',
        '<a class="mc-btn mc-btn--sm" href="corbeille.php">' . icone('corbeille', 'mc-ico-sm') . 'Ouvrir la corbeille</a>');
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
$matiere_page = $note['matiere_id'];   // « Nouvelle note » depuis l'éditeur : même matière
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" id="hljs-clair"
      href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
<link rel="stylesheet" id="hljs-sombre" disabled
      href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">

<div class="editeur" data-note-id="<?= (int) $note['id'] ?>">
    <div class="editeur-barre">
        <a class="mc-btn mc-btn--ghost mc-btn--sm retour" href="<?= $note['matiere_id']
            ? 'matiere.php?id=' . (int) $note['matiere_id'] : 'index.php' ?>"><?= icone('retour', 'mc-ico-sm') ?>Retour</a>

        <label class="mc-sr" for="note-titre">Titre de la note</label>
        <input type="text" id="note-titre" class="champ-titre"
               value="<?= e($note['titre']) ?>" placeholder="Titre de la note" maxlength="255">

        <label class="mc-sr" for="note-matiere">Matière</label>
        <select id="note-matiere" class="mc-select champ-matiere" title="Matière">
            <option value="">Non classée</option>
            <?php foreach ($matieres as $m): ?>
                <option value="<?= (int) $m['id'] ?>"
                    <?= (int) $m['id'] === (int) $note['matiere_id'] ? 'selected' : '' ?>>
                    <?= e($m['ue_code'] . ' · ' . $m['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <span id="statut-save" class="statut-save" role="status">Enregistré</span>

        <div class="editeur-onglets mc-seg" role="group" aria-label="Affichage">
            <button type="button" data-vue="deux" class="actif">Deux volets</button>
            <button type="button" data-vue="edition">Édition</button>
            <button type="button" data-vue="apercu">Aperçu</button>
        </div>

        <span class="mc-actions editeur-icones">
            <a class="mc-btn mc-btn--ghost mc-btn--sm" href="aide.php" target="_blank"
               title="Aide Markdown (nouvel onglet)" aria-label="Aide Markdown"><?= icone('aide', 'mc-ico-sm') ?></a>
            <button type="button" id="btn-epingler" class="mc-btn mc-btn--ghost mc-btn--sm btn-favori"
                    data-epingle="<?= (int) $note['epingle'] ?>" aria-pressed="<?= $note['epingle'] ? 'true' : 'false' ?>"
                    title="Épingler dans les favoris" aria-label="Favori"><?= icone('favori', 'mc-ico-sm') ?></button>
            <button type="button" id="btn-supprimer" class="mc-btn mc-btn--ghost mc-btn--sm"
                    title="Envoyer à la corbeille" aria-label="Envoyer à la corbeille"><?= icone('corbeille', 'mc-ico-sm') ?></button>
        </span>
    </div>

    <!-- Barre d'outils (actions : assets/js/outils-editeur.js) -->
    <div id="barre-outils" class="barre-outils mathjax_ignore" role="toolbar" aria-label="Mise en forme">
        <button type="button" data-action="annuler" title="Annuler (Ctrl+Z)" aria-label="Annuler"><?= icone('annuler', 'mc-ico-sm') ?></button>
        <button type="button" data-action="retablir" title="Rétablir (Ctrl+Y)" aria-label="Rétablir"><?= icone('retablir', 'mc-ico-sm') ?></button>
        <span class="outil-sep"></span>
        <button type="button" data-action="titre1" title="Grand titre (#)"><b>H1</b></button>
        <button type="button" data-action="titre2" title="Sous-titre (##)"><b>H2</b></button>
        <button type="button" data-action="titre3" title="Petit titre (###)"><b>H3</b></button>
        <span class="outil-sep"></span>
        <button type="button" data-action="gras" title="Gras (Ctrl+B)" aria-label="Gras"><b>G</b></button>
        <button type="button" data-action="italique" title="Italique (Ctrl+I)" aria-label="Italique"><i>I</i></button>
        <button type="button" data-action="souligner" title="Souligné (Ctrl+U) → ++texte++" aria-label="Souligné"><u>U</u></button>
        <button type="button" data-action="barre" title="Barré" aria-label="Barré"><s>S</s></button>
        <div class="outil-deroulant">
            <button type="button" data-ouvrir="couleurs" title="Texte en couleur / surligner" aria-label="Couleurs">
                <span class="icone-couleur">A</span><?= icone('bas', 'mc-ico-sm') ?></button>
            <div class="outil-menu menu-couleurs" data-menu="couleurs"></div>
        </div>
        <span class="outil-sep"></span>
        <button type="button" data-action="puces" title="Liste à puces" aria-label="Liste à puces"><?= icone('puces', 'mc-ico-sm') ?></button>
        <button type="button" data-action="numeros" title="Liste numérotée" aria-label="Liste numérotée"><?= icone('numeros', 'mc-ico-sm') ?></button>
        <button type="button" data-action="cases" title="Cases à cocher" aria-label="Cases à cocher"><?= icone('case-cochee', 'mc-ico-sm') ?></button>
        <button type="button" data-action="citation" title="Citation / rappel" aria-label="Citation"><?= icone('citation', 'mc-ico-sm') ?></button>
        <span class="outil-sep"></span>
        <button type="button" data-action="tableau"
                title="Créer / modifier un tableau (éditeur visuel). Astuce : tape « Nom | Âge » puis Entrée"><?= icone('tableau', 'mc-ico-sm') ?>Tableau</button>
        <div class="outil-deroulant">
            <button type="button" data-ouvrir="maths" title="Maths : symboles, modèles, recherche (dans une formule, tape \ pour l'autocomplétion)"><?= icone('maths', 'mc-ico-sm') ?>Maths<?= icone('bas', 'mc-ico-sm') ?></button>
            <div class="outil-menu menu-maths" data-menu="maths"></div>
        </div>
        <div class="outil-deroulant">
            <button type="button" data-ouvrir="code" title="Bloc de code (pseudo-code, C, SQL…)"><?= icone('code', 'mc-ico-sm') ?>Code<?= icone('bas', 'mc-ico-sm') ?></button>
            <div class="outil-menu menu-code" data-menu="code"></div>
        </div>
        <div class="outil-deroulant">
            <button type="button" data-ouvrir="compta" title="Comptabilité : compte en T, journal, balance, compte de résultat, bilan"><?= icone('calculatrice', 'mc-ico-sm') ?>Compta<?= icone('bas', 'mc-ico-sm') ?></button>
            <div class="outil-menu menu-compta" data-menu="compta"></div>
        </div>
        <button type="button" data-action="code" title="Code dans le texte" aria-label="Code dans le texte"><span class="mc-mono">`c`</span></button>
        <button type="button" data-action="lien" title="Lien" aria-label="Lien"><?= icone('lien', 'mc-ico-sm') ?></button>
        <button type="button" data-action="separateur" title="Ligne de séparation" aria-label="Ligne de séparation"><?= icone('trait', 'mc-ico-sm') ?></button>
        <span class="outil-sep"></span>
        <button type="button" data-action="enregistrer" title="Enregistrer maintenant (Ctrl+S)" aria-label="Enregistrer"><?= icone('enregistrer', 'mc-ico-sm') ?></button>
    </div>

    <div class="editeur-corps">
        <label class="mc-sr" for="note-contenu">Contenu de la note (Markdown)</label>
        <textarea id="note-contenu" class="editeur-saisie" spellcheck="false"
                  placeholder="Prends tes notes en Markdown…"><?= e($note['contenu']) ?></textarea>
        <div id="apercu" class="editeur-apercu markdown" aria-label="Aperçu"></div>
    </div>
</div>

<!-- Panneau : tags, fichiers, export -->
<div class="editeur-pied">
    <section class="mc-card" aria-labelledby="titre-tags">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-tags"><?= icone('tag', 'mc-ico-sm') ?>Tags</h2></div>
        <div id="liste-tags" class="mc-chips">
            <?php foreach ($tags_note as $t): ?>
                <span class="mc-chip chip" data-tag-id="<?= (int) $t['id'] ?>" style="--tag: <?= e($t['couleur']) ?>">
                    <span class="mc-chip__dot"></span><?= e($t['nom']) ?>
                    <button type="button" class="mc-chip__x chip-x" title="Retirer" aria-label="Retirer le tag <?= e($t['nom']) ?>"><?= icone('fermer', 'mc-ico-sm') ?></button>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="mc-form-ligne">
            <input type="text" id="tag-nom" class="mc-input" list="tags-suggestions"
                   placeholder="Ajouter un tag…" maxlength="50" aria-label="Nom du tag">
            <datalist id="tags-suggestions">
                <?php foreach ($tous_tags as $nom): ?>
                    <option value="<?= e($nom) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <button type="button" id="tag-ajouter" class="mc-btn mc-btn--sm">Ajouter</button>
        </div>
    </section>

    <section class="mc-card" aria-labelledby="titre-fichiers">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-fichiers"><?= icone('trombone', 'mc-ico-sm') ?>Fichiers joints</h2></div>
        <ul id="liste-fichiers" class="liste-fichiers">
            <?php foreach ($fichiers as $f):
                $est_image = str_starts_with($f['type_mime'], 'image/'); ?>
                <li data-fichier-id="<?= (int) $f['id'] ?>"
                    <?php if ($est_image): ?>data-image="1"<?php endif; ?>
                    data-nom="<?= e($f['nom_original']) ?>">
                    <?php if ($est_image): ?>
                        <img class="miniature" src="telecharger.php?id=<?= (int) $f['id'] ?>"
                             alt="" loading="lazy" title="Agrandir">
                    <?php endif; ?>
                    <a href="telecharger.php?id=<?= (int) $f['id'] ?>" target="_blank">
                        <?= e($f['nom_original']) ?>
                    </a>
                    <span class="taille"><?= round($f['taille'] / 1024) ?> Ko</span>
                    <?php if ($est_image): ?>
                        <button type="button" class="fichier-inserer mc-btn mc-btn--sm"
                                title="Afficher l'image dans la note, à l'endroit du curseur">Insérer</button>
                    <?php endif; ?>
                    <button type="button" class="fichier-x mc-btn mc-btn--ghost mc-btn--sm" title="Supprimer"
                            aria-label="Supprimer <?= e($f['nom_original']) ?>"><?= icone('fermer', 'mc-ico-sm') ?></button>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="mc-form-ligne">
            <input type="file" id="fichier-input" class="mc-input" aria-label="Fichier à joindre">
            <button type="button" id="fichier-envoyer" class="mc-btn mc-btn--sm"><?= icone('importer', 'mc-ico-sm') ?>Importer</button>
            <span id="fichier-statut" class="statut-save" role="status"></span>
        </div>
        <p class="mc-meta">PDF, images, .txt, .docx… (20 Mo max).
            Une image (ex. un MCD exporté de draw.io en PNG) s'affiche directement dans la note ;
            tu peux aussi la coller (Ctrl+V) ou la glisser dans la zone de texte.</p>
    </section>

    <section class="mc-card" aria-labelledby="titre-export">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-export"><?= icone('telecharger', 'mc-ico-sm') ?>Export</h2></div>
        <p class="mc-eyebrow">Cette note</p>
        <div class="mc-actions">
            <a class="mc-btn mc-btn--sm" href="export.php?type=note&id=<?= (int) $note['id'] ?>&format=md">Markdown</a>
            <a class="mc-btn mc-btn--sm" href="imprimer.php?type=note&id=<?= (int) $note['id'] ?>" target="_blank"><?= icone('imprimer', 'mc-ico-sm') ?>PDF</a>
        </div>
        <?php if ($note['matiere_id']): ?>
            <p class="mc-eyebrow">Toute la matière</p>
            <div class="mc-actions">
                <a class="mc-btn mc-btn--sm" href="export.php?type=matiere&id=<?= (int) $note['matiere_id'] ?>&format=md">Markdown</a>
                <a class="mc-btn mc-btn--sm" href="imprimer.php?type=matiere&id=<?= (int) $note['matiere_id'] ?>" target="_blank"><?= icone('imprimer', 'mc-ico-sm') ?>PDF</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Librairies (CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.9/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="<?= asset('assets/js/mathjax-config.js') ?>"></script>
<script async src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-chtml.js"></script>
<script defer src="<?= asset('assets/js/compta-moteur.js') ?>"></script>
<script defer src="<?= asset('assets/js/rendu.js') ?>"></script>
<script defer src="<?= asset('assets/js/maths-symboles.js') ?>"></script>
<script defer src="<?= asset('assets/js/outils-editeur.js') ?>"></script>
<script defer src="<?= asset('assets/js/editeur.js') ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
