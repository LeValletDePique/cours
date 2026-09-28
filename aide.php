<?php
/**
 * Aide-mémoire Markdown : pour chaque syntaxe, on affiche « ce que tu tapes »
 * à gauche et le rendu réel à droite (code coloré + formules comprises).
 * En bas, un « bac à sable » pour s'entraîner librement.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

// Chaque section : titre, intro (facultative), et liste d'exemples (Markdown brut).
// NB : chaînes en apostrophes simples pour ne pas casser les \ du LaTeX.
$sections = [
    [
        'titre' => 'Les bases',
        'intro' => 'Le texte s\'écrit normalement ; on ajoute juste quelques symboles.',
        'exemples' => [
            "# Grand titre\n## Sous-titre\n### Petit titre",
            "**gras**, *italique*, ~~barré~~ et `code court`",
            "- Première puce\n- Deuxième puce\n    - Sous-puce (4 espaces devant)",
            "1. Premier\n2. Deuxième\n3. Troisième",
            "- [x] Chapitre revu\n- [ ] Exercices à finir",
            "> Une citation ou une remarque importante.",
            "Un lien : [le site de CY Tech](https://cytech.cyu.fr)",
            "Séparateur horizontal :\n\n---",
        ],
    ],
    [
        'titre' => 'Tableaux',
        'intro' => 'Les « | » séparent les colonnes ; la ligne de tirets sépare l\'en-tête.',
        'exemples' => [
            "| Notion | Exemple |\n|--------|---------|\n| Variable | x = 5 |\n| Boucle | for/while |",
        ],
    ],
    [
        'titre' => 'Blocs de code colorés',
        'intro' => 'Trois accents graves ``` puis le nom du langage : la couleur est automatique.',
        'exemples' => [
            "```python\ndef carre(x):\n    return x * x\n```",
            "```c\nint somme(int a, int b) {\n    return a + b;\n}\n```",
            "```sql\nSELECT nom FROM matieres WHERE ue_id = 2;\n```",
        ],
    ],
    [
        'titre' => 'Mathématiques (LaTeX)',
        'intro' => 'Entoure d\'un $ pour une formule dans le texte, de deux $$ pour une formule centrée.',
        'exemples' => [
            'La vitesse vaut $v = \frac{d}{t}$ dans le texte.',
            'Puissances et indices : $x^2$, $a_{i}$, $x_1^2$',
            'Racines : $\sqrt{2}$ et $\sqrt[3]{x}$',
            'Fraction centrée :\n\n$$\frac{a + b}{2}$$',
            'Somme :\n\n$$\sum_{i=1}^{n} i = \frac{n(n+1)}{2}$$',
            'Intégrale :\n\n$$\int_0^1 x^2 \, dx = \frac{1}{3}$$',
            'Lettres grecques : $\alpha, \beta, \pi, \Delta, \lambda$',
            'Matrice :\n\n$$\begin{pmatrix} a & b \\\\ c & d \end{pmatrix}$$',
        ],
    ],
];

$exemple_bac = "# Ma première note\n\n"
    . "Voici un **point important** à retenir.\n\n"
    . "## Un algorithme\n\n"
    . "```python\nfor i in range(3):\n    print(i)\n```\n\n"
    . "## Une formule\n\n"
    . "L'aire du disque est $A = \\pi r^2$.\n";

$titre_page = 'Aide Markdown';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" id="hljs-clair"
      href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
<link rel="stylesheet" id="hljs-sombre" disabled
      href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">

<h1>Aide — écrire en Markdown</h1>
<p class="intro-aide">Le Markdown, c'est du texte normal avec quelques symboles pour la mise en forme.
   Pour chaque exemple : à gauche <strong>ce que tu tapes</strong>, à droite <strong>ce que ça donne</strong>.</p>

<?php foreach ($sections as $sec): ?>
    <section class="bloc">
        <h2><?= e($sec['titre']) ?></h2>
        <?php if (!empty($sec['intro'])): ?>
            <p class="astuce-mini"><?= e($sec['intro']) ?></p>
        <?php endif; ?>
        <div class="aide-liste">
            <?php foreach ($sec['exemples'] as $ex): ?>
                <div class="exemple">
                    <pre class="src"><code><?= e($ex) ?></code></pre>
                    <div class="rendu markdown"></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<section class="bloc">
    <h2>🧪 À toi d'essayer</h2>
    <p class="astuce-mini">Écris à gauche, le rendu apparaît à droite. (Rien n'est enregistré ici.)</p>
    <div class="bac-corps">
        <textarea id="bac-saisie" class="editeur-saisie" spellcheck="false"><?= e($exemple_bac) ?></textarea>
        <div id="bac-apercu" class="editeur-apercu markdown"></div>
    </div>
</section>

<!-- Librairies (CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.9/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>
    window.MathJax = {
        tex: { inlineMath: [['$', '$'], ['\\(', '\\)']],
               displayMath: [['$$', '$$'], ['\\[', '\\]']] },
        options: { skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'] }
    };
</script>
<script async src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-chtml.js"></script>
<script defer src="assets/js/rendu.js"></script>
<script>
    // Coloration : suit le thème clair / sombre du site.
    function majThemeCodeAide() {
        const sombre = document.documentElement.getAttribute('data-theme') === 'sombre';
        const c = document.getElementById('hljs-clair'), n = document.getElementById('hljs-sombre');
        if (c) c.disabled = sombre;
        if (n) n.disabled = !sombre;
    }
    majThemeCodeAide();
    new MutationObserver(majThemeCodeAide).observe(document.documentElement,
        { attributes: true, attributeFilter: ['data-theme'] });

    window.addEventListener('load', () => {
        // Rendu de tous les exemples.
        document.querySelectorAll('.exemple').forEach((ex) => {
            const src = ex.querySelector('.src code').textContent;
            window.rendreMarkdown(src, ex.querySelector('.rendu'));
        });
        // Bac à sable (rendu en direct).
        const saisie = document.getElementById('bac-saisie');
        const apercu = document.getElementById('bac-apercu');
        let minuteur;
        const rendre = () => window.rendreMarkdown(saisie.value, apercu);
        saisie.addEventListener('input', () => {
            clearTimeout(minuteur); minuteur = setTimeout(rendre, 250);
        });
        rendre();
    });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
