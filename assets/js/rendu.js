/* ============================================================
   Rendu Markdown partagé (éditeur, aide, impression, assistant).
   Expose window.rendreMarkdown(source, elementCible) -> Promise
   - protège les formules LaTeX avant le Markdown
   - tolère les notes indentées (voir normaliserRetraits)
   - texte en couleur : [texte]{rouge}   surlignage : ==texte==
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

    if (window.marked) {
        marked.use({ gfm: true, breaks: true, extensions });
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

    window.rendreMarkdown = function (source, cible) {
        const { texte, math } = protegerMath((source || '').replace(/\r\n?/g, '\n'));
        let html = marked.parse(normaliserRetraits(texte));
        html = DOMPurify.sanitize(html);
        html = html.replace(/MJXPH(\d+)ENDPH/g, (_, i) => echapper(math[i]));
        cible.innerHTML = html;

        if (window.hljs) {
            cible.querySelectorAll('pre code').forEach((b) => hljs.highlightElement(b));
        }
        if (window.MathJax && MathJax.typesetPromise) {
            return MathJax.typesetPromise([cible]).catch(() => {});
        }
        return Promise.resolve();
    };
})();
