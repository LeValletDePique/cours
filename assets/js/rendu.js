/* ============================================================
   Rendu Markdown partagé (éditeur, aide, impression).
   Expose window.rendreMarkdown(source, elementCible) -> Promise
   - protège les formules LaTeX avant le Markdown
   - tolère les notes indentées (voir normaliserRetraits)
   - texte en couleur : [texte]{rouge}   surlignage : ==texte==   souligné : ++texte++
   - affectation du pseudo-code : <- s'affiche ←
   - comptabilité : blocs ```journal, ```comptes (comptes en T), ```balance,
     ```resultat et ```bilan (voir compta-moteur.js, chargé avant ce fichier)
   - nettoie le HTML (anti-XSS) avec DOMPurify
   - colore le code (highlight.js, + pseudo-code) et rend les maths (MathJax)
   ============================================================ */

(function () {
    function echapper(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Met les formules de côté pour que _ * \ ne soient pas mal interprétés.
    function protegerMath(src) {
        const math = [];
        const remplacer = (s) => 'MJXPH' + (math.push(s) - 1) + 'ENDPH';
        return {
            texte: src
                // Bloc ```math … ``` = formule centrée.
                .replace(/^[ \t]*(`{3,}|~{3,})[ \t]*(?:math|latex|tex)[ \t]*\n([\s\S]*?)\n[ \t]*\1[ \t]*$/gm,
                    (m, f, dedans) => remplacer('$$' + dedans + '$$'))
                .replace(/\$\$([\s\S]+?)\$\$/g, (m) => remplacer(m))
                .replace(/\\\[([\s\S]+?)\\\]/g, (m) => remplacer(m))
                .replace(/\\\(([\s\S]+?)\\\)/g, (m) => remplacer(m))
                .replace(/\$([^\$\n]+?)\$/g, (m) => remplacer(m)),
            math,
        };
    }

    // ---------- Notes indentées ----------
    // En Markdown, une ligne décalée de 4 espaces devient un bloc de code
    // (le **gras** et les puces n'y sont plus interprétés) ou se colle au
    // paragraphe précédent. On découpe donc la note en « segments » de même
    // retrait, on retire ce retrait, et on le rend visuellement avec un
    // <div class="retrait-N">. Les listes gardent leurs sous-niveaux.
    const RE_LISTE = /^(?:[-*+]|\d{1,9}[.)])(?:[ \t]|$)/;
    const RE_FENCE = /^(`{3,}|~{3,})/;

    function niveauRetrait(n) {
        return n === 0 ? 0 : Math.max(1, Math.min(6, Math.round(n / 4)));
    }

    function normaliserRetraits(src) {
        const sortie = [];
        let seg = null;     // { liste, base, lignes }
        let fence = null;   // { marque, seg }

        function emettre(s) {
            const corps = s.lignes.map((l) => {
                const r = l.match(/^ */)[0].length;
                return l.slice(Math.min(r, s.base));
            });
            const niveau = niveauRetrait(s.base);
            if (niveau === 0) {
                sortie.push(...corps, '');
            } else {
                sortie.push('<div class="retrait retrait-' + niveau + '">', '',
                    ...corps, '', '</div>', '');
            }
        }
        function vider() {
            if (seg) emettre(seg);
            seg = null;
        }

        for (const brute of src.split('\n')) {
            // Tabulations de début de ligne = 4 espaces ; puces « • » acceptées.
            const ligne = brute
                .replace(/^[ \t]+/, (m) => m.replace(/\t/g, '    '))
                .replace(/^( *)[•●▪◦‣]\s+/, '$1- ');
            const retrait = ligne.match(/^ */)[0].length;
            const contenu = ligne.slice(retrait);

            // À l'intérieur d'un bloc ``` : on recopie tel quel.
            if (fence) {
                fence.seg.lignes.push(ligne);
                const f = contenu.match(RE_FENCE);
                if (f && f[1][0] === fence.marque[0] && f[1].length >= fence.marque.length
                    && contenu.slice(f[1].length).trim() === '') {
                    if (fence.seg !== seg) emettre(fence.seg);
                    fence = null;
                }
                continue;
            }

            if (contenu === '') { vider(); sortie.push(''); continue; }

            const f = contenu.match(RE_FENCE);
            if (f) {
                if (seg && seg.liste && retrait > seg.base) {
                    // Bloc de code rattaché à un élément de liste.
                    seg.lignes.push(ligne);
                    fence = { marque: f[1], seg };
                } else {
                    vider();
                    fence = { marque: f[1], seg: { liste: false, base: retrait, lignes: [ligne] } };
                }
                continue;
            }

            const estListe = RE_LISTE.test(contenu);
            if (seg && seg.liste && (retrait > seg.base || (estListe && retrait === seg.base))) {
                seg.lignes.push(ligne);          // item suivant ou sous-niveau
            } else if (seg && !seg.liste && !estListe && retrait === seg.base) {
                seg.lignes.push(ligne);          // suite du même paragraphe
            } else {
                vider();
                seg = { liste: estListe, base: retrait, lignes: [ligne] };
            }
        }
        if (fence && fence.seg !== seg) emettre(fence.seg);   // bloc non fermé
        vider();
        return sortie.join('\n');
    }

    // ---------- Extensions Markdown ----------
    const COULEURS = ['rouge', 'orange', 'jaune', 'vert', 'bleu', 'violet', 'rose', 'gris'];
    const RE_COULEUR = new RegExp('^\\[([^\\[\\]\\n]+)\\]\\{(' + COULEURS.join('|')
        + '|#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?)\\}');

    const extensions = [
        {
            // [texte]{rouge}  ou  [texte]{#e11d48}
            name: 'couleur',
            level: 'inline',
            start(src) { const i = src.search(/\[[^\[\]\n]+\]\{/); return i < 0 ? undefined : i; },
            tokenizer(src) {
                const m = RE_COULEUR.exec(src);
                if (!m) return;
                return { type: 'couleur', raw: m[0], couleur: m[2],
                         tokens: this.lexer.inlineTokens(m[1]) };
            },
            renderer(t) {
                const attr = t.couleur[0] === '#'
                    ? 'style="color:' + t.couleur + '"'
                    : 'class="couleur-' + t.couleur + '"';
                return '<span ' + attr + '>' + this.parser.parseInline(t.tokens) + '</span>';
            },
        },
        {
            // ==surligné==
            name: 'surligne',
            level: 'inline',
            start(src) { const i = src.indexOf('=='); return i < 0 ? undefined : i; },
            tokenizer(src) {
                const m = /^==(?=\S)([^\n]*?\S)==/.exec(src);
                if (!m) return;
                return { type: 'surligne', raw: m[0], tokens: this.lexer.inlineTokens(m[1]) };
            },
            renderer(t) { return '<mark>' + this.parser.parseInline(t.tokens) + '</mark>'; },
        },
        {
            // ++souligné++  (« C++ » ou « i++ » seuls ne soulignent rien)
            name: 'souligne',
            level: 'inline',
            start(src) { const i = src.indexOf('++'); return i < 0 ? undefined : i; },
            tokenizer(src) {
                const m = /^\+\+(?=[^\s+])([^\n]*?[^\s+])\+\+(?!\+)/.exec(src);
                if (!m) return;
                return { type: 'souligne', raw: m[0], tokens: this.lexer.inlineTokens(m[1]) };
            },
            renderer(t) { return '<u>' + this.parser.parseInline(t.tokens) + '</u>'; },
        },
        {
            // **gras** plus tolérant que la norme : « **Rappel :**suite »,
            // « **mot**, » ou « l'**éthique** » fonctionnent toujours.
            name: 'grasSouple',
            level: 'inline',
            start(src) { const i = src.indexOf('**'); return i < 0 ? undefined : i; },
            tokenizer(src) {
                const m = /^\*\*(?=\S)([^\n]*?\S)\*\*(?!\*)/.exec(src);
                if (!m) return;
                return { type: 'grasSouple', raw: m[0], tokens: this.lexer.inlineTokens(m[1]) };
            },
            renderer(t) { return '<strong>' + this.parser.parseInline(t.tokens) + '</strong>'; },
        },
    ];

    // ---------- Couleur et souligné dans les blocs de code ----------
    // Dans un bloc ``` (SQL, MLD, pseudo-code…), [texte]{rouge} et ++texte++
    // restent actifs. On remplace d'abord ces balises par des caractères
    // invisibles (zone Unicode privée) qui traversent highlight.js sans être
    // touchés, puis marquerCode() les transforme en <span>/<u> dans le DOM.
    const M_SOUL = '', M_FIN_SOUL = '', M_FIN_COUL = '';
    const M_COUL = 0xE100;   // + n° de la couleur dans la liste du bloc
    const RE_MARQUES = /([--])/;
    // Langages où « ++ » est un opérateur : « ++i + j++ » ne souligne rien.
    const LANGAGES_PLUSPLUS = new Set(['c', 'h', 'cpp', 'cc', 'cxx', 'hpp', 'c++', 'java',
        'js', 'javascript', 'jsx', 'mjs', 'ts', 'typescript', 'tsx', 'cs', 'csharp', 'c#',
        'php', 'go', 'golang', 'kotlin', 'kt', 'swift', 'dart', 'objectivec', 'objc',
        'groovy', 'perl', 'pl', 'awk', 'arduino', 'ino', 'glsl', 'd']);
    const couleursCode = [];

    function baliserCode(code, langage) {
        let s = code.replace(new RegExp(RE_COULEUR.source.slice(1), 'g'), (m, texte, couleur) => {
            let i = couleursCode.indexOf(couleur);
            if (i < 0) i = couleursCode.push(couleur) - 1;
            return i > 0xFF ? m : String.fromCharCode(M_COUL + i) + texte + M_FIN_COUL;
        });
        if (!LANGAGES_PLUSPLUS.has(langage.toLowerCase().replace(/[^a-z0-9+#]/g, ''))) {
            s = s.replace(/(?<!\+)\+\+(?=[^\s+])([^\n]*?[^\s+])\+\+(?!\+)/g,
                (m, texte) => M_SOUL + texte + M_FIN_SOUL);
        }
        return s;
    }

    // Remplace les caractères-balises par de vrais éléments, morceau de texte
    // par morceau de texte : une couleur peut ainsi traverser les <span> de
    // highlight.js (commentaire, mot-clé…) sans casser le HTML.
    function marquerCode(bloc) {
        const textes = [];
        const parcours = document.createTreeWalker(bloc, NodeFilter.SHOW_TEXT);
        while (parcours.nextNode()) textes.push(parcours.currentNode);
        let souligne = 0;
        const pile = [];   // couleurs ouvertes
        textes.forEach((noeud) => {
            if (!RE_MARQUES.test(noeud.data) && !souligne && !pile.length) return;
            const frag = document.createDocumentFragment();
            noeud.data.split(RE_MARQUES).forEach((morceau) => {
                if (morceau === M_SOUL) souligne++;
                else if (morceau === M_FIN_SOUL) souligne = Math.max(0, souligne - 1);
                else if (morceau === M_FIN_COUL) pile.pop();
                else if (morceau.length === 1 && RE_MARQUES.test(morceau)) {
                    pile.push(couleursCode[morceau.charCodeAt(0) - M_COUL]);
                } else if (morceau) {
                    let el = document.createTextNode(morceau);
                    const couleur = pile[pile.length - 1];
                    if (couleur) {
                        const span = document.createElement('span');
                        if (couleur[0] === '#') span.style.color = couleur;
                        else span.className = 'couleur-' + couleur;
                        span.appendChild(el);
                        el = span;
                    }
                    if (souligne) {
                        const u = document.createElement('u');
                        u.appendChild(el);
                        el = u;
                    }
                    frag.appendChild(el);
                }
            });
            noeud.parentNode.replaceChild(frag, noeud);
        });
    }

    if (window.marked) {
        marked.use({
            gfm: true, breaks: true, extensions,
            renderer: {
                // Blocs de comptabilité : ```bilan, ```comptes… ; sinon bloc de
                // code normal, avec couleurs et souligné (voir baliserCode).
                code(code, info) {
                    const compta = window.Compta && window.Compta.blocMarkdown(info, code);
                    if (compta) return compta;
                    const langage = (info || '').match(/\S*/)[0];
                    const classe = langage
                        ? ' class="language-' + echapper(langage).replace(/"/g, '&quot;') + '"' : '';
                    return '<pre><code' + classe + '>'
                        + echapper(baliserCode(code.replace(/\n$/, ''), langage)) + '\n</code></pre>\n';
                },
            },
        });
    }

    // ---------- Coloration du pseudo-code (```pseudo) ----------
    if (window.hljs && !hljs.getLanguage('pseudo')) {
        hljs.registerLanguage('pseudo', function (h) {
            return {
                name: 'Pseudo-code',
                aliases: ['pseudocode', 'algo', 'algorithme'],
                case_insensitive: true,
                keywords: {
                    $pattern: /[A-Za-zÀ-ÿ_][A-Za-zÀ-ÿ_0-9]*/,
                    keyword: 'algorithme programme fonction procédure procedure variables variable '
                        + 'constantes constante début debut fin si alors sinon finsi selon cas défaut '
                        + 'defaut autrement finselon pour de à jusqu pas faire finpour tant que '
                        + 'fintantque répéter repeter retourner retourne renvoyer et ou non div mod '
                        + 'entrée entree sortie données donnees résultat resultat',
                    type: 'entier réel reel booléen booleen chaîne chaine caractère caractere '
                        + 'tableau enregistrement',
                    literal: 'vrai faux nul null',
                    built_in: 'lire écrire ecrire afficher saisir longueur racine abs',
                },
                contains: [
                    h.C_LINE_COMMENT_MODE,
                    h.C_BLOCK_COMMENT_MODE,
                    h.QUOTE_STRING_MODE,
                    { className: 'string', begin: "'", end: "'" },
                    h.C_NUMBER_MODE,
                    { className: 'operator', begin: /<-|←|:=|≠|≤|≥|<=|>=|&/ },
                    { className: 'title.function', begin: /[A-Za-zÀ-ÿ_]\w*(?=\s*\()/, relevance: 0 },
                ],
            };
        });
    }

    // ---------- Flèche d'affectation ----------
    // « <- » devient « ← » dans le texte, le `code` et le pseudo-code, mais
    // pas dans le code d'un vrai langage (en C, « x<-1 » veut dire x < -1)
    // ni dans les formules (encore mises de côté à ce stade).
    const RE_CODE_HTML = /(<code\b[^>]*>)([\s\S]*?)(<\/code>)/g;
    const RE_AUTRE_LANGAGE = /class="[^"]*\blanguage-(?!(?:pseudo|pseudocode|algo|algorithme)\b)/;

    function flechesAffectation(html) {
        const fleches = (s) => s.replace(/&lt;-(?!-|&gt;)/g, '←');
        let sortie = '', dernier = 0, m;
        RE_CODE_HTML.lastIndex = 0;
        while ((m = RE_CODE_HTML.exec(html))) {
            sortie += fleches(html.slice(dernier, m.index))
                + (RE_AUTRE_LANGAGE.test(m[1]) ? m[0] : m[1] + fleches(m[2]) + m[3]);
            dernier = m.index + m[0].length;
        }
        return sortie + fleches(html.slice(dernier));
    }

    window.rendreMarkdown = function (source, cible) {
        couleursCode.length = 0;
        const { texte, math } = protegerMath((source || '').replace(/\r\n?/g, '\n'));
        let html = marked.parse(normaliserRetraits(texte));
        html = flechesAffectation(DOMPurify.sanitize(html));
        html = html.replace(/MJXPH(\d+)ENDPH/g, (_, i) => echapper(math[i]));
        cible.innerHTML = html;

        if (window.hljs) {
            cible.querySelectorAll('pre code').forEach((b) => hljs.highlightElement(b));
        }
        cible.querySelectorAll('pre code').forEach(marquerCode);
        if (window.MathJax && MathJax.typesetPromise) {
            return MathJax.typesetPromise([cible]).catch(() => {});
        }
        return Promise.resolve();
    };
})();
