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
        'intro' => 'Les « | » séparent les colonnes ; la ligne de tirets sépare l\'en-tête. Plus simple dans l\'éditeur : bouton « ▦ Tableau ▾ » pour choisir la taille, puis Tab pour passer d\'une case à l\'autre (les colonnes s\'alignent toutes seules, une ligne est ajoutée à la fin). Tu peux aussi coller un tableau copié depuis Excel ou Google Sheets.',
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
        'titre' => 'Couleurs et surlignage',
        'intro' => 'Dans l\'éditeur : sélectionne le texte puis clique sur « A ▾ » dans la barre d\'outils. Couleurs : rouge, orange, jaune, vert, bleu, violet, rose, gris (ou un code #hexa).',
        'exemples' => [
            "Un mot [important]{rouge}, une [définition]{bleu} et un [exemple]{vert}.",
            "Tu peux aussi ==surligner== un passage, ou [**mettre en gras et en couleur**]{violet}.",
            "Couleur libre : [texte]{#e11d48}",
        ],
    ],
    [
        'titre' => 'Notes indentées',
        'intro' => 'Tu peux décaler tes lignes avec Tab (4 espaces) pour structurer ta note : le gras, les puces et les couleurs restent actifs, et le décalage est conservé à l\'affichage.',
        'exemples' => [
            "Rappels :\n    **Définition :** une entité est un objet du besoin\n    - attribut\n    - identifiant\n        - unique pour chaque objet",
        ],
    ],
    [
        'titre' => 'Pseudo-code',
        'intro' => 'Comme pour le C ou le SQL : ```pseudo puis ton algorithme. Les mots-clés (SI, ALORS, POUR, TANT QUE, Entier…) sont colorés. Alias acceptés : algo, algorithme, pseudocode.',
        'exemples' => [
            "```pseudo\nAlgorithme maximum\nVariables\n    a, b, max : Entier\n{\n    Lire(a)\n    Lire(b)\n    SI (a > b) ALORS {\n        max <- a\n    } SINON {\n        max <- b\n    }\n    Ecrire(\"Le max est \", max)\n}\n```",
            "```pseudo\nPOUR i DE 1 À n FAIRE {\n    TANT QUE (x ≠ 0) FAIRE {\n        x <- x DIV 2   // division entière\n    }\n}\n```",
        ],
    ],
    [
        'titre' => 'Mathématiques (LaTeX)',
        'intro' => 'Entoure d\'un $ pour une formule dans le texte, de deux $$ pour une formule centrée. Dans l\'éditeur, le menu « ∑ Maths ▾ » insère tous ces symboles en un clic.',
        'exemples' => [
            'La vitesse vaut $v = \frac{d}{t}$ dans le texte.',
            'Puissances et indices : $x^2$, $a_{i}$, $x_1^2$, $e^{i\pi}$',
            'Racines : $\sqrt{2}$ et $\sqrt[3]{x}$',
            'Fraction centrée :' . "\n\n" . '$$\frac{a + b}{2}$$',
            'Somme et produit :' . "\n\n" . '$$\sum_{i=1}^{n} i = \frac{n(n+1)}{2} \qquad \prod_{k=1}^{n} k = n!$$',
            'Intégrale et limite :' . "\n\n" . '$$\int_0^1 x^2 \, dx = \frac{1}{3} \qquad \lim_{x \to +\infty} \frac{1}{x} = 0$$',
            'Lettres grecques : $\alpha, \beta, \gamma, \delta, \varepsilon, \lambda, \pi, \sigma, \Delta, \Omega$',
        ],
    ],
    [
        'titre' => 'Maths : logique et quantificateurs',
        'exemples' => [
            'Quantificateurs : $\forall x \in \mathbb{R}, \exists n \in \mathbb{N}, n > x$',
            'Unicité et négation : $\exists! x$, $\nexists x$, $\neg P$',
            'Connecteurs : $P \land Q$, $P \lor Q$, $P \Rightarrow Q$, $P \Leftrightarrow Q$, $P \iff Q$',
            'Définition formelle de la limite :' . "\n\n" . '$$\forall \varepsilon > 0, \exists \eta > 0, \forall x, |x - a| < \eta \implies |f(x) - \ell| < \varepsilon$$',
        ],
    ],
    [
        'titre' => 'Maths : ensembles et relations',
        'exemples' => [
            'Ensembles usuels : $\mathbb{N} \subset \mathbb{Z} \subset \mathbb{Q} \subset \mathbb{R} \subset \mathbb{C}$',
            'Appartenance : $x \in A$, $x \notin B$, $A \subseteq B$, $\emptyset$',
            'Opérations : $A \cup B$, $A \cap B$, $A \setminus B$, $\overline{A}$',
            'En compréhension : $E = \{ x \in \mathbb{R} \mid x^2 < 4 \}$ et $[\![ 1, n ]\!]$',
            'Relations : $a \neq b$, $a \leq b$, $a \geq b$, $a \approx b$, $a \equiv b \pmod{n}$',
            'Opérateurs : $a \times b$, $a \cdot b$, $a \pm b$, $f \circ g$, $+\infty$',
            'Flèches : $x \to 0$, $f : x \mapsto x^2$, $u_n \longrightarrow \ell$',
        ],
    ],
    [
        'titre' => 'Maths : structures',
        'exemples' => [
            'Valeur absolue, norme, vecteur : $\lvert x \rvert$, $\lVert \vec{u} \rVert$, $\lfloor x \rfloor$, $\binom{n}{k}$',
            'Définition par cas :' . "\n\n" . '$$|x| = \begin{cases} x & \text{si } x \geq 0 \\\\ -x & \text{sinon} \end{cases}$$',
            'Matrice :' . "\n\n" . '$$\begin{pmatrix} a & b \\\\ c & d \end{pmatrix}$$',
            'Calcul aligné :' . "\n\n" . '$$\begin{aligned} (a+b)^2 &= (a+b)(a+b) \\\\ &= a^2 + 2ab + b^2 \end{aligned}$$',
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

<section class="bloc">
    <h2>⌨️ Raccourcis de l'éditeur</h2>
    <table class="tableau-raccourcis">
        <tr><td><kbd>Ctrl</kbd> + <kbd>S</kbd></td><td>Enregistrer tout de suite (l'enregistrement auto continue aussi)</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>Z</kbd></td><td>Annuler</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>Y</kbd> ou <kbd>Ctrl</kbd> + <kbd>Maj</kbd> + <kbd>Z</kbd></td><td>Rétablir</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>B</kbd> / <kbd>Ctrl</kbd> + <kbd>I</kbd></td><td>Gras / italique</td></tr>
        <tr><td><kbd>Tab</kbd> / <kbd>Maj</kbd> + <kbd>Tab</kbd></td><td>Décaler / recaler les lignes — dans un tableau : case suivante / précédente</td></tr>
        <tr><td><kbd>Entrée</kbd> dans une liste</td><td>Nouvelle puce automatiquement (Entrée sur une puce vide = fin de la liste)</td></tr>
    </table>
    <p class="astuce-mini">Un problème ? Clique sur <strong>🤖 Aide IA</strong> en bas à droite de l'écran.</p>
</section>

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
