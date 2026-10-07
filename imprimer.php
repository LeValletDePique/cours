<?php
/**
 * Page imprimable d'une note ou d'une matière.
 * Le rendu (Markdown + code + LaTeX) se fait dans le navigateur, puis la
 * fenêtre d'impression s'ouvre : « Enregistrer au format PDF » donne le PDF.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid  = utilisateur_id();
$type = $_GET['type'] ?? 'note';
$id   = (int) ($_GET['id'] ?? 0);

$titre_global = '';
$sections = []; // [ ['titre'=>..., 'contenu'=>...], ... ]

if ($type === 'note') {
    $stmt = db()->prepare(
        'SELECT titre, contenu FROM notes
          WHERE id = ? AND utilisateur_id = ? AND supprime = 0'
    );
    $stmt->execute([$id, $uid]);
    $note = $stmt->fetch();
    if (!$note) { http_response_code(404); exit('Note introuvable.'); }
    $titre_global = $note['titre'];
    $sections[] = ['titre' => $note['titre'], 'contenu' => $note['contenu']];

} elseif ($type === 'matiere') {
    $stmt = db()->prepare(
        'SELECT m.nom FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE m.id = ? AND u.utilisateur_id = ?'
    );
    $stmt->execute([$id, $uid]);
    $matiere = $stmt->fetch();
    if (!$matiere) { http_response_code(404); exit('Matière introuvable.'); }
    $titre_global = $matiere['nom'];

    $stmt = db()->prepare(
        'SELECT titre, contenu FROM notes
          WHERE matiere_id = ? AND utilisateur_id = ? AND supprime = 0
          ORDER BY date_creation'
    );
    $stmt->execute([$id, $uid]);
    $sections = $stmt->fetchAll();
} else {
    http_response_code(400); exit('Type inconnu.');
}

$contenus = array_map(static fn($s) => $s['contenu'], $sections);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= e($titre_global) ?></title>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
    <style>
        body { font-family: Georgia, "Times New Roman", serif; color: #111;
               max-width: 780px; margin: 2rem auto; padding: 0 1.5rem; line-height: 1.6; }
        h1.doc-titre { font-size: 1.9rem; border-bottom: 2px solid #333; padding-bottom: .3rem; }
        .note-imp { margin-top: 1.5rem; }
        .note-imp > h2 { font-size: 1.4rem; border-bottom: 1px solid #ccc; }
        pre { background: #f5f5f5; border: 1px solid #ddd; border-radius: 6px;
              padding: .8rem; overflow-x: auto; }
        code { font-family: Consolas, monospace; font-size: .9em; }
        blockquote { border-left: 3px solid #888; margin: .8em 0; padding: .2em 1em; color: #444; }
        table { border-collapse: collapse; } th, td { border: 1px solid #999; padding: .3em .6em; }
        img { max-width: 100%; }
        .retrait-1 { margin-left: 1.5rem; } .retrait-2 { margin-left: 3rem; }
        .retrait-3 { margin-left: 4.5rem; } .retrait-4 { margin-left: 6rem; }
        .retrait-5 { margin-left: 7.5rem; } .retrait-6 { margin-left: 9rem; }
        .couleur-rouge { color: #dc2626; } .couleur-orange { color: #ea580c; }
        .couleur-jaune { color: #ca8a04; } .couleur-vert { color: #16a34a; }
        .couleur-bleu { color: #2563eb; } .couleur-violet { color: #7c3aed; }
        .couleur-rose { color: #db2777; } .couleur-gris { color: #6b7280; }
        mark { background: #fef08a; }
        /* Comptabilité (blocs ```comptes, ```bilan…) */
        .cpt-num, .cpt-mono { font-family: Consolas, monospace; font-size: .85em; color: #555; }
        .cpt-mt { text-align: right; white-space: nowrap; }
        .cpt-ref { font-size: .75em; color: #777; }
        .cpt-calc { font-size: .75em; color: #b8380f; }
        .cpt-verif { font-size: .85em; margin: .3em 0 0; color: #2b7349; }
        .cpt-verif.ko { color: #9a5800; }
        .cpt-doc { margin: .8em 0; }
        .cpt-table { width: 100%; font-size: .9em; }
        .cpt-table caption { font-weight: bold; padding: .3em; background: #eee; border: 1px solid #999; border-bottom: 0; }
        .cpt-table thead th, .cpt-table tfoot td, .cpt-doc__st td, .cpt-doc__total td { background: #f0f0f0; font-weight: bold; }
        .cpt-doc__rub th { background: #fff5c2; text-transform: uppercase; font-size: .85em; }
        .cpt-doc__resultat td { font-weight: bold; }
        .cpt-j__date td { text-align: center; background: #f5f5f5; font-size: .85em; }
        .cpt-j__credit { padding-left: 2em; } .cpt-j__lib td { font-style: italic; color: #555; }
        .cpt-t-grille { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: .8em 0; }
        .cpt-t { width: 100%; border-collapse: collapse; break-inside: avoid; font-size: .9em; }
        .cpt-t caption { font-weight: bold; border-bottom: 2px solid #000; }
        .cpt-t th, .cpt-t td { border: 0; width: 50%; text-align: right; padding: .1em .5em; }
        .cpt-t thead th { font-size: .7em; text-transform: uppercase; color: #777; text-align: center; }
        .cpt-t__d, .cpt-t thead th:first-child, .cpt-t__tot td:first-child { border-right: 2px solid #000 !important; }
        .cpt-t__tot td { border-top: 1px solid #999; font-weight: bold; }
        .cpt-t__solde td { text-align: center; font-size: .8em; color: #555; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .btn-imprimer { position: fixed; top: 1rem; right: 1rem; padding: .6rem 1rem;
                        background: #4f46e5; color: #fff; border: 0; border-radius: 8px;
                        font-size: 1rem; cursor: pointer; }
        @media print {
            body { margin: 0; }
            .btn-imprimer { display: none; }
            .note-imp { page-break-before: always; }
            .note-imp:first-of-type { page-break-before: avoid; }
        }
    </style>
</head>
<body>
    <button class="btn-imprimer" onclick="window.print()">Imprimer / PDF</button>
    <h1 class="doc-titre"><?= e($titre_global) ?></h1>

    <?php foreach ($sections as $i => $s): ?>
        <section class="note-imp">
            <?php if ($type === 'matiere'): ?><h2><?= e($s['titre']) ?></h2><?php endif; ?>
            <div class="rendu markdown" data-idx="<?= $i ?>"></div>
        </section>
    <?php endforeach; ?>

    <script id="donnees" type="application/json"><?=
        json_encode($contenus, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.9/purify.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="<?= asset('assets/js/mathjax-config.js') ?>"></script>
    <script async src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-chtml.js"></script>
    <script src="<?= asset('assets/js/compta-moteur.js') ?>"></script>
    <script src="<?= asset('assets/js/rendu.js') ?>"></script>
    <script>
        // Rend chaque section, puis ouvre l'impression une fois tout prêt.
        window.addEventListener('load', () => {
            const contenus = JSON.parse(document.getElementById('donnees').textContent);
            const cibles = document.querySelectorAll('.rendu');
            Promise.all([...cibles].map((c, i) => window.rendreMarkdown(contenus[i] || '', c)))
                .then(() => setTimeout(() => window.print(), 500));
        });
    </script>
</body>
</html>
