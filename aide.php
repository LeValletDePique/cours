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
            "**gras**, *italique*, ~~barré~~, ++souligné++ et `code court`",
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
        'intro' => 'Pas besoin de taper les | et les tirets à la main : voir « Créer un tableau en 5 secondes » plus haut. Voici la syntaxe obtenue (:--- à gauche, :---: centré, ---: à droite).',
        'exemples' => [
            "| Notion   | Exemple   |\n| -------- | --------- |\n| Variable | x = 5     |\n| Boucle   | for/while |",
            '| Loi         | Espérance  | Variance    |' . "\n" . '| :---------- | :--------: | ----------: |' . "\n"
                . '| Binomiale   | $np$       | $np(1-p)$   |' . "\n" . '| **Poisson** | $\lambda$ | $\lambda$  |',
        ],
    ],
    [
        'titre' => 'Blocs de code colorés',
        'intro' => 'Trois accents graves ``` puis le nom du langage : la couleur est automatique. En SQL, en pseudo-code ou sans langage, [texte]{rouge}, ++souligné++, ~~barré~~, ==surligné== et **gras** restent actifs dedans. Dans les langages où ces symboles sont des opérateurs (C, Python, Java, JS…), seule la couleur marche.',
        'exemples' => [
            "```python\ndef carre(x):\n    return x * x\n```",
            "```c\nint somme(int a, int b) {\n    return a + b;\n}\n```",
            "```sql\nSELECT nom FROM matieres WHERE ue_id = 2;\n```",
            "Dans un bloc de code, couleurs, ++souligné++, ~~barré~~, ==surligné== et **gras** marchent aussi (pratique pour un MLD) :\n\n```sql\nPersonne(++idPersonne++, nom, prenom)\nTelephone(++idTelephone++, numero, #idPersonne) [← clé étrangère : pas soulignée]{rouge}\nVoiture(++idVoiture++, ~~appelation~~, **marque**, ==#idPersonne==)\n```",
        ],
    ],
    [
        'titre' => 'Couleurs et surlignage',
        'intro' => 'Dans l\'éditeur : sélectionne le texte puis clique sur « A » dans la barre d\'outils. Couleurs : rouge, orange, jaune, vert, bleu, violet, rose, gris (ou un code #hexa).',
        'exemples' => [
            "Un mot [important]{rouge}, une [définition]{bleu} et un [exemple]{vert}.",
            "Tu peux aussi ==surligner== un passage, le ++souligner++ (Ctrl+U), ou [**mettre en gras et en couleur**]{violet}.",
            "Tout se combine : ++[**titre souligné en rouge**]{rouge}++",
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
        'intro' => 'Comme pour le C ou le SQL : ```pseudo puis ton algorithme. Les mots-clés (SI, ALORS, POUR, TANT QUE, Entier…) sont colorés. Alias acceptés : algo, algorithme, pseudocode. Partout (texte ou pseudo-code), <- s\'affiche ← dans l\'aperçu.',
        'exemples' => [
            "Affectation : x <- 5",
            "```pseudo\nAlgorithme maximum\nVariables\n    a, b, max : Entier\n{\n    Lire(a)\n    Lire(b)\n    SI (a > b) ALORS {\n        max <- a\n    } SINON {\n        max <- b\n    }\n    Ecrire(\"Le max est \", max)\n}\n```",
            "```pseudo\nPOUR i DE 1 À n FAIRE {\n    TANT QUE (x ≠ 0) FAIRE {\n        x <- x DIV 2   // division entière\n    }\n}\n```",
        ],
    ],
    [
        'id' => 'compta',
        'titre' => 'Comptabilité : comptes en T et écritures',
        'intro' => 'Menu « Compta » de l\'éditeur, ou à la main. Un numéro de compte seul suffit : l\'intitulé du plan comptable s\'affiche (512 → Banque). Dans l\'éditeur, tape @banque ou @512 pour l\'insérer ; dans un tableau ou un bloc de compta, le numéro seul propose l\'intitulé (Entrée ou Tab). Montants : 1 500 000 ou 1500000. Totaux et soldes sont calculés. Atelier complet : page Comptabilité.',
        'exemples' => [
            "```comptes\n512\n900 000 | 790 000\n(4) 45 000 |\n| 25 000\n\n401 Fournisseurs\n200 000 | 435 000\n| 14 000\n```",
            "```journal\n31/12 Achat de marchandises à crédit\n607 | 14 000 |\n401 | | 14 000\n\n31/12 Ventes payées par chèque\nbanque / ventes 45 000\n```",
            "```balance\n512 | 60 000 | 25 000\n401 | 25 000 | 39 000\n607 | 39 000 |\n707 | | 60 000\n```",
        ],
    ],
    [
        'titre' => 'Comptabilité : compte de résultat et bilan',
        'intro' => 'Une colonne puis l\'autre (« Charges » / « Produits », « Actif » / « Passif »), « # Rubrique » pour un sous-total, une ligne par poste avec son montant à la fin. Le résultat (bénéfice ou perte) est calculé ; dans un bilan, « : ? » calcule le montant qui équilibre.',
        'exemples' => [
            "```resultat Compte de résultat N\nCharges\n607 554 000\n615 17 000\n641 Salaires 558 500\nProduits\n707 1 257 000\n```",
            "```bilan Bilan au 31/12/N\nActif\n# Actif immobilisé\n211 300 000\n2182 150 000\n# Actif circulant\n411 435 000\n512 91 500\nPassif\n# Capitaux propres\n10 Capital 230 000\nRésultat de l'exercice : ?\n# Dettes\n164 Emprunt 430 000\n401 229 000\n```",
        ],
    ],
    [
        'titre' => 'Images (ex. MCD draw.io)',
        'intro' => 'Exporte ton schéma en PNG, puis dans la note : « Fichiers joints » → Importer (ou colle-le avec Ctrl+V / glisse-le dans la zone de texte). L\'image est insérée à l\'endroit du curseur ; clique dessus dans l\'aperçu pour l\'agrandir.',
        'exemples' => [],
    ],
    [
        'titre' => 'Mathématiques (LaTeX)',
        'intro' => 'Entoure d\'un $ pour une formule dans le texte, de deux $$ (ou d\'un bloc ```math) pour une formule centrée. Toutes les commandes sont dans la « Référence complète » plus bas.',
        'exemples' => [
            'La vitesse vaut $v = \frac{d}{t}$ dans le texte.',
            'Puissances et indices : $x^2$, $a_{i}$, $x_1^2$, $e^{i\pi}$',
            'Racines : $\sqrt{2}$ et $\sqrt[3]{x}$',
            'Fraction centrée :' . "\n\n" . '$$\frac{a + b}{2}$$',
            'Somme et produit :' . "\n\n" . '$$\sum_{i=1}^{n} i = \frac{n(n+1)}{2} \qquad \prod_{k=1}^{n} k = n!$$',
            'Intégrale et limite :' . "\n\n" . '$$\int_0^1 x^2 \, dx = \frac{1}{3} \qquad \lim_{x \to +\infty} \frac{1}{x} = 0$$',
            'Lettres grecques : $\alpha, \beta, \gamma, \delta, \varepsilon, \lambda, \pi, \sigma, \Delta, \Omega$',
            "```math\n" . 'f(x) = \sum_{n=0}^{+\infty} \frac{f^{(n)}(0)}{n!} x^n' . "\n```",
            'Raccourcis du site : $\R, \N, \Z, \Q, \C$, $\abs{x}$, $\norm{u}$, $\ens{1, 2, 3}$, $\llbracket 1, n \rrbracket$, $\int_0^1 f(x) \dx$',
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
        'titre' => 'Maths : analyse, algèbre, probabilités',
        'exemples' => [
            'Dérivées : $f\'(x)$, $\frac{\mathrm{d}y}{\mathrm{d}x}$, $\frac{\partial f}{\partial x}$, $f^{(n)}$',
            'Suites : $u_n \xrightarrow[n \to +\infty]{} \ell$ et $\sin x \underset{x \to 0}{\sim} x$',
            'Algèbre linéaire : $A^{\top}$, $A^{-1}$, $\det(A)$, $\ker f$, $\operatorname{Im} f$, $\vec{u} \cdot \vec{v}$, $\lVert \vec{u} \rVert$',
            'Complexes : $z = a + ib$, $\overline{z}$, $\lvert z \rvert$, $e^{i\theta} = \cos\theta + i\sin\theta$',
            'Probabilités : $\mathbb{P}(A \mid B)$, $\mathbb{E}(X)$, $\mathbb{V}(X)$, $X \sim \mathcal{B}(n, p)$, $X \sim \mathcal{N}(\mu, \sigma^2)$',
            'Arithmétique : $a \equiv b \pmod{n}$, $\pgcd(a, b)$, $a \mid b$, $\lfloor x \rfloor$, $\binom{n}{k}$',
            'Mise en valeur : $\boxed{x = 2}$, $\underbrace{1 + 1 + \cdots + 1}_{n \text{ fois}}$, $\cancel{x}$, $\color{red}{x}$',
        ],
    ],
    [
        'titre' => 'Maths : structures',
        'exemples' => [
            'Valeur absolue, norme, vecteur : $\lvert x \rvert$, $\lVert \vec{u} \rVert$, $\lfloor x \rfloor$, $\binom{n}{k}$',
            'Définition par cas :' . "\n\n" . '$$|x| = \begin{cases} x & \text{si } x \geq 0 \\\\ -x & \text{sinon} \end{cases}$$',
            'Matrice :' . "\n\n" . '$$\begin{pmatrix} a & b \\\\ c & d \end{pmatrix}$$',
            'Calcul aligné :' . "\n\n" . '$$\begin{aligned} (a+b)^2 &= (a+b)(a+b) \\\\ &= a^2 + 2ab + b^2 \end{aligned}$$',
            'Système :' . "\n\n" . '$$\begin{cases} 2x + y = 3 \\\\ x - y = 0 \end{cases}$$',
            'Tableau de variations :' . "\n\n" . '$$\begin{array}{c|ccccc} x & -\infty & & 0 & & +\infty \\\\ \hline f\'(x) & & - & 0 & + & \\\\ \hline f(x) & & \searrow & 1 & \nearrow & \end{array}$$',
        ],
    ],
];

$exemple_bac = "# Ma première note\n\n"
    . "Voici un **point important** à retenir.\n\n"
    . "## Un algorithme\n\n"
    . "```python\nfor i in range(3):\n    print(i)\n```\n\n"
    . "## Une formule\n\n"
    . "L'aire du disque est \$A = \\pi r^2$.\n";

$titre_page = 'Aide Markdown';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" id="hljs-clair"
      href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
<link rel="stylesheet" id="hljs-sombre" disabled
      href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">

<header class="mc-hello">
    <p class="mc-eyebrow">Aide</p>
    <h1 class="mc-title">Écrire en Markdown</h1>
    <p>Du texte normal avec quelques symboles pour la mise en forme.
       Pour chaque exemple : à gauche <strong>ce que tu tapes</strong>, à droite <strong>ce que ça donne</strong>.</p>
</header>

<section class="mc-card mc-aide">
    <div class="mc-card__head"><h2 class="mc-h"><?= icone('clavier', 'mc-ico-sm') ?>Raccourcis du site</h2></div>
    <table class="tableau-raccourcis">
        <tr><td><kbd>N</kbd></td><td>Nouvelle note (pré-remplie et rangée si un cours est en cours)</td></tr>
        <tr><td><kbd>T</kbd></td><td>Ajouter une tâche (« pour vendredi » fixe la date, un nom de matière la range)</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>K</kbd> ou <kbd>/</kbd></td><td>Chercher une note, une matière ou une action</td></tr>
        <tr><td><kbd>Échap</kbd></td><td>Fermer la recherche ou une fenêtre</td></tr>
        <tr><td><kbd>←</kbd> / <kbd>→</kbd></td><td>Agenda : période précédente / suivante</td></tr>
    </table>
    <p class="mc-meta">Ces touches ne font rien quand tu écris dans un champ.</p>
</section>

<section class="mc-card mc-aide">
    <div class="mc-card__head"><h2 class="mc-h"><?= icone('crayon', 'mc-ico-sm') ?>Raccourcis de l'éditeur</h2></div>
    <table class="tableau-raccourcis">
        <tr><td><kbd>Ctrl</kbd> + <kbd>S</kbd></td><td>Enregistrer tout de suite (l'enregistrement auto continue aussi)</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>Z</kbd></td><td>Annuler</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>Y</kbd> ou <kbd>Ctrl</kbd> + <kbd>Maj</kbd> + <kbd>Z</kbd></td><td>Rétablir</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>B</kbd> / <kbd>Ctrl</kbd> + <kbd>I</kbd> / <kbd>Ctrl</kbd> + <kbd>U</kbd></td><td>Gras / italique / souligné (<code>++texte++</code>)</td></tr>
        <tr><td><kbd>@</kbd> + nom ou numéro de compte</td><td>Compte du plan comptable : <code>@banque</code> ou <code>@512</code> → « 512 Banque » (Entrée). Dans un tableau ou un bloc de compta, le numéro seul suffit.</td></tr>
        <tr><td><kbd>Tab</kbd> / <kbd>Maj</kbd> + <kbd>Tab</kbd></td><td>Décaler / recaler les lignes — dans un tableau : case suivante / précédente</td></tr>
        <tr><td><kbd>Entrée</kbd> dans une liste</td><td>Nouvelle puce automatiquement (Entrée sur une puce vide = fin de la liste)</td></tr>
        <tr><td><kbd>Entrée</kbd> dans un tableau</td><td>Nouvelle ligne (Entrée sur une ligne vide = sortir du tableau)</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>M</kbd></td><td>Nouvelle formule <code>$…$</code></td></tr>
        <tr><td><kbd>\</kbd> + lettres dans une formule</td><td>Autocomplétion LaTeX (<code>\pour</code> → ∀, <code>\int</code> → ∫…)</td></tr>
        <tr><td><kbd>Tab</kbd> dans une formule</td><td>Champ suivant à remplir (numérateur → dénominateur…), puis sortie de la formule</td></tr>
    </table>
</section>

<section class="mc-card mc-aide">
    <div class="mc-card__head"><h2 class="mc-h"><?= icone('tableau', 'mc-ico-sm') ?>Créer un tableau en 5 secondes</h2></div>
    <ol class="guide">
        <li><strong>Le plus rapide :</strong> tape les titres des colonnes séparés par <code>|</code>
            puis <kbd>Entrée</kbd> :
            <pre class="src"><code>Loi | Espérance | Variance</code></pre>
            → le tableau est créé et le curseur est dans la première case.
            <kbd>Tab</kbd> = case suivante, <kbd>Entrée</kbd> = nouvelle ligne,
            <kbd>Entrée</kbd> sur une ligne vide = fin du tableau. Les colonnes s'alignent toutes seules.</li>
        <li><strong>Comme dans un tableur :</strong> bouton <strong>Tableau</strong> de la barre d'outils.
            Tu remplis une grille (Tab / Entrée pour avancer), tu ajoutes lignes et colonnes,
            tu choisis l'alignement, puis « Insérer ». Curseur dans un tableau existant = le même bouton le <strong>modifie</strong>.</li>
        <li><strong>Depuis Excel / Google Sheets / LibreOffice :</strong> copie les cellules et colle-les
            dans la note (ou dans une case de l'éditeur de tableau) : c'est converti automatiquement.</li>
    </ol>
</section>

<section class="mc-card mc-aide">
    <div class="mc-card__head"><h2 class="mc-h"><?= icone('maths', 'mc-ico-sm') ?>Écrire des maths vite</h2></div>
    <ul class="guide">
        <li><kbd>Ctrl</kbd>+<kbd>M</kbd> (ou le menu <strong>Maths</strong>) ouvre une formule <code>$…$</code>.</li>
        <li>Dans une formule, tape <code>\</code> puis le début du nom <strong>ou du mot français</strong> :
            <code>\pour</code> → <code>\forall</code>, <code>\appart</code> → <code>\in</code>,
            <code>\integ</code> → intégrale, <code>\lam</code> → λ. <kbd>Entrée</kbd> valide.</li>
        <li>Les modèles (fraction, somme, intégrale, matrice…) sélectionnent la première case à remplir ;
            <kbd>Tab</kbd> passe à la suivante.</li>
        <li>Une <strong>bulle d'aperçu</strong> affiche la formule rendue sous le curseur pendant que tu tapes
            (les erreurs apparaissent en rouge).</li>
        <li>Le menu <strong>Maths</strong> a une barre de recherche (« intégrale », « matrice », « appartient »…)
            et un générateur de matrice n × p.</li>
        <li>Raccourcis propres au site : <code>\R \N \Z \Q \C</code>, <code>\abs{x}</code>, <code>\norm{u}</code>,
            <code>\ens{…}</code>, <code>\pgcd</code>, <code>\dx</code>, <code>\eps</code>.</li>
        <li>Toutes les commandes : <a href="#reference-maths">référence complète</a> en bas de la page.</li>
    </ul>
</section>

<?php foreach ($sections as $sec): ?>
    <section class="mc-card mc-aide"<?= !empty($sec['id']) ? ' id="' . e($sec['id']) . '"' : '' ?>>
        <div class="mc-card__head"><h2 class="mc-h"><?= e($sec['titre']) ?></h2></div>
        <?php if (!empty($sec['intro'])): ?>
            <p class="mc-meta"><?= e($sec['intro']) ?></p>
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

<section class="mc-card mc-aide" id="reference-maths">
    <div class="mc-card__head"><h2 class="mc-h"><?= icone('livre', 'mc-ico-sm') ?>Référence complète des maths</h2></div>
    <p class="mc-meta">Clique sur un code pour le copier. Dans l'éditeur, le menu Maths et la touche <kbd>\</kbd> insèrent ces codes directement.</p>
    <input type="search" id="ref-recherche" class="mc-input maths-recherche"
           placeholder="Chercher un symbole : pour tout, appartient, intégrale, matrice, variance…">
    <div id="ref-maths" class="ref-maths"></div>
</section>

<section class="mc-card mc-aide">
    <div class="mc-card__head"><h2 class="mc-h"><?= icone('essai', 'mc-ico-sm') ?>À toi d'essayer</h2></div>
    <p class="mc-meta">Écris à gauche, le rendu apparaît à droite. (Rien n'est enregistré ici.)</p>
    <div class="bac-corps">
        <textarea id="bac-saisie" class="editeur-saisie" spellcheck="false"><?= e($exemple_bac) ?></textarea>
        <div id="bac-apercu" class="editeur-apercu markdown"></div>
    </div>
</section>

<!-- Librairies (CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.9/purify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="<?= asset('assets/js/mathjax-config.js') ?>"></script>
<script async src="https://cdnjs.cloudflare.com/ajax/libs/mathjax/3.2.2/es5/tex-mml-chtml.js"></script>
<script defer src="<?= asset('assets/js/compta-moteur.js') ?>"></script>
<script defer src="<?= asset('assets/js/rendu.js') ?>"></script>
<script defer src="<?= asset('assets/js/maths-symboles.js') ?>"></script>
<script>
    // Coloration : suit le thème clair / sombre du site.
    function majThemeCodeAide() {
        const sombre = document.documentElement.getAttribute('data-theme') === 'dark';
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

        // Référence complète des maths (données : maths-symboles.js).
        const ref = document.getElementById('ref-maths');
        const echapper = (t) => t.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
        const sansAccents = (t) => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        ref.innerHTML = (window.MATHS_SYMBOLES || []).map(({ groupe, items }) =>
            '<div class="ref-groupe"><h3>' + echapper(groupe) + '</h3><table class="ref-table">'
            + items.map(([label, code, desc]) => {
                const brut = code.trim();
                const propre = brut.replace(/[‹›]/g, '');
                return '<tr data-cherche="' + echapper(sansAccents(label + ' ' + propre + ' ' + (desc || '') + ' ' + groupe)) + '">'
                    + '<td class="ref-rendu">\\(' + echapper(propre) + '\\)</td>'
                    + '<td><code class="ref-code" title="Cliquer pour copier">' + echapper(propre) + '</code></td>'
                    + '<td class="ref-desc">' + echapper(desc || '') + '</td></tr>';
            }).join('') + '</table></div>').join('');
        if (window.MathJax && MathJax.typesetPromise) MathJax.typesetPromise([ref]).catch(() => {});
        ref.addEventListener('click', (e) => {
            const c = e.target.closest('.ref-code');
            if (!c || !navigator.clipboard) return;
            navigator.clipboard.writeText(c.textContent.replace(/[‹›]/g, '')).then(() => {
                c.classList.add('copie');
                setTimeout(() => c.classList.remove('copie'), 900);
            }).catch(() => {});
        });
        document.getElementById('ref-recherche').addEventListener('input', (e) => {
            const q = sansAccents(e.target.value.trim());
            ref.querySelectorAll('.ref-groupe').forEach((g) => {
                let n = 0;
                g.querySelectorAll('tr').forEach((tr) => {
                    const ok = !q || tr.dataset.cherche.includes(q);
                    tr.hidden = !ok;
                    if (ok) n++;
                });
                g.hidden = !n;
            });
        });
    });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
