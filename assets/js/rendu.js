/* ============================================================
   Rendu Markdown partagé (éditeur + page d'impression).
   Expose window.rendreMarkdown(source, elementCible) -> Promise
   - protège les formules LaTeX avant le Markdown
   - nettoie le HTML (anti-XSS) avec DOMPurify
   - colore le code (highlight.js) et rend les maths (MathJax)
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
                .replace(/\$\$([\s\S]+?)\$\$/g, (m) => remplacer(m))
                .replace(/\\\[([\s\S]+?)\\\]/g, (m) => remplacer(m))
                .replace(/\\\(([\s\S]+?)\\\)/g, (m) => remplacer(m))
                .replace(/\$([^\$\n]+?)\$/g, (m) => remplacer(m)),
            math,
        };
    }

    window.rendreMarkdown = function (source, cible) {
        const { texte, math } = protegerMath(source || '');
        let html = marked.parse(texte);
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
