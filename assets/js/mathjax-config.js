/* ============================================================
   Configuration MathJax commune (à charger AVANT le script MathJax).
   Ajoute des raccourcis pratiques en cours : \R \N \Z \Q \C \K,
   \abs{x} \norm{u} \ens{…} \llbracket \rrbracket \pgcd \dx \eps…
   ============================================================ */

window.MathJax = {
    loader: { load: ['[tex]/mathtools', '[tex]/cancel', '[tex]/color'] },
    tex: {
        inlineMath: [['$', '$'], ['\\(', '\\)']],
        displayMath: [['$$', '$$'], ['\\[', '\\]']],
        processEscapes: true,          // \$ affiche un vrai dollar
        tags: 'ams',                   // \begin{equation} numérotée
        packages: { '[+]': ['mathtools', 'cancel', 'color'] },
        macros: {
            R: '\\mathbb{R}', N: '\\mathbb{N}', Z: '\\mathbb{Z}',
            Q: '\\mathbb{Q}', C: '\\mathbb{C}', K: '\\mathbb{K}',
            llbracket: '[\\![', rrbracket: ']\\!]',
            abs: ['\\left\\lvert #1 \\right\\rvert', 1],
            norm: ['\\left\\lVert #1 \\right\\rVert', 1],
            ens: ['\\left\\{ #1 \\right\\}', 1],
            pgcd: '\\operatorname{pgcd}', ppcm: '\\operatorname{ppcm}',
            card: '\\operatorname{card}', Vect: '\\operatorname{Vect}',
            rg: '\\operatorname{rg}', tr: '\\operatorname{tr}', Id: '\\operatorname{Id}',
            ch: '\\operatorname{ch}', sh: '\\operatorname{sh}', th: '\\operatorname{th}',
            dx: '\\,\\mathrm{d}x', dt: '\\,\\mathrm{d}t', eps: '\\varepsilon',
        },
    },
    options: { skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'] },
};
