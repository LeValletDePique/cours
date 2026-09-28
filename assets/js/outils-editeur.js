/* ============================================================
   Outils de saisie de l'éditeur de note :
   - barre d'outils (gras, titres, listes, couleurs, maths, code, tableaux)
   - historique maison : Ctrl+Z / Ctrl+Y (fiable même après un bouton)
   - Ctrl+S = enregistrer tout de suite, Ctrl+B / Ctrl+I
   - Entrée continue une liste, Tab / Maj+Tab indentent
   - tableaux : Tab passe à la cellule suivante et aligne les colonnes,
     coller depuis Excel / Sheets crée un tableau Markdown
   Utilisation : window.installerOutilsEditeur({ zone, barre, auChangement, auEnregistrement })
   ============================================================ */

(function () {
    // ---------- Données : symboles mathématiques ----------
    const MATHS = [
        { groupe: 'Logique', items: [
            ['∀', '\\forall ', 'pour tout'], ['∃', '\\exists ', 'il existe'],
            ['∃!', '\\exists! ', 'il existe un unique'], ['∄', '\\nexists ', "il n'existe pas"],
            ['¬', '\\neg ', 'non'], ['∧', '\\land ', 'et'], ['∨', '\\lor ', 'ou'],
            ['⇒', '\\Rightarrow ', 'implique'], ['⇔', '\\Leftrightarrow ', 'équivaut'],
            ['⟹', '\\implies ', 'implique (long)'], ['⟺', '\\iff ', 'si et seulement si'],
            ['∴', '\\therefore ', 'donc'],
        ] },
        { groupe: 'Ensembles', items: [
            ['∈', '\\in ', 'appartient'], ['∉', '\\notin ', "n'appartient pas"],
            ['⊂', '\\subset ', 'inclus'], ['⊆', '\\subseteq ', 'inclus ou égal'],
            ['⊄', '\\not\\subset ', 'non inclus'], ['∪', '\\cup ', 'union'],
            ['∩', '\\cap ', 'intersection'], ['∖', '\\setminus ', 'privé de'],
            ['∅', '\\emptyset ', 'ensemble vide'], ['ℕ', '\\mathbb{N}', 'entiers naturels'],
            ['ℤ', '\\mathbb{Z}', 'entiers relatifs'], ['ℚ', '\\mathbb{Q}', 'rationnels'],
            ['ℝ', '\\mathbb{R}', 'réels'], ['ℂ', '\\mathbb{C}', 'complexes'],
            ['{ }', '\\{ x \\mid x > 0 \\}', 'ensemble en compréhension'],
            ['⟦ ⟧', '[\\![ 1, n ]\\!]', 'intervalle entier'],
        ] },
        { groupe: 'Relations & opérations', items: [
            ['≠', '\\neq ', 'différent'], ['≤', '\\leq ', 'inférieur ou égal'],
            ['≥', '\\geq ', 'supérieur ou égal'], ['≈', '\\approx ', 'environ'],
            ['≡', '\\equiv ', 'congru / équivalent'], ['∼', '\\sim ', 'équivalent'],
            ['∝', '\\propto ', 'proportionnel'], ['±', '\\pm ', 'plus ou moins'],
            ['×', '\\times ', 'fois'], ['÷', '\\div ', 'divisé'], ['·', '\\cdot ', 'point'],
            ['∘', '\\circ ', 'composée'], ['∞', '\\infty', 'infini'], ['…', '\\dots ', 'points'],
        ] },
        { groupe: 'Flèches', items: [
            ['→', '\\to ', 'tend vers'], ['←', '\\leftarrow ', 'flèche gauche'],
            ['↦', '\\mapsto ', 'associe'], ['⟶', '\\longrightarrow ', 'longue flèche'],
            ['↑', '\\uparrow ', 'croissant'], ['↓', '\\downarrow ', 'décroissant'],
        ] },
        { groupe: 'Analyse', items: [
            ['a⁄b', '\\frac{a}{b}', 'fraction'], ['√', '\\sqrt{x}', 'racine'],
            ['ⁿ√', '\\sqrt[n]{x}', 'racine n-ième'], ['xⁿ', 'x^{n}', 'puissance'],
            ['xᵢ', 'x_{i}', 'indice'], ['Σ', '\\sum_{i=1}^{n} ', 'somme'],
            ['Π', '\\prod_{i=1}^{n} ', 'produit'], ['∫', '\\int_{a}^{b} f(x) \\, dx', 'intégrale'],
            ['lim', '\\lim_{x \\to +\\infty} ', 'limite'], ["f'", "f'(x)", 'dérivée'],
            ['∂', '\\partial ', 'dérivée partielle'], ['∇', '\\nabla ', 'nabla'],
            ['|x|', '\\lvert x \\rvert', 'valeur absolue'], ['‖u‖', '\\lVert u \\rVert', 'norme'],
            ['u⃗', '\\vec{u}', 'vecteur'], ['z̄', '\\overline{z}', 'conjugué / barre'],
            ['⌊x⌋', '\\lfloor x \\rfloor', 'partie entière'], ['(n k)', '\\binom{n}{k}', 'coefficient binomial'],
            ['ln', '\\ln ', 'logarithme'], ['exp', '\\exp ', 'exponentielle'],
            ['sin', '\\sin ', 'sinus'], ['cos', '\\cos ', 'cosinus'],
        ] },
        { groupe: 'Lettres grecques', items: [
            ['α', '\\alpha '], ['β', '\\beta '], ['γ', '\\gamma '], ['δ', '\\delta '],
            ['ε', '\\varepsilon '], ['θ', '\\theta '], ['λ', '\\lambda '], ['μ', '\\mu '],
            ['π', '\\pi '], ['ρ', '\\rho '], ['σ', '\\sigma '], ['φ', '\\varphi '],
            ['ω', '\\omega '], ['Δ', '\\Delta '], ['Σ', '\\Sigma '], ['Ω', '\\Omega '],
        ] },
        { groupe: 'Structures', items: [
            ['{ cas', '\\begin{cases} x & \\text{si } x \\geq 0 \\\\ -x & \\text{sinon} \\end{cases}', 'définition par cas / système'],
            ['( M )', '\\begin{pmatrix} a & b \\\\ c & d \\end{pmatrix}', 'matrice'],
            ['| D |', '\\begin{vmatrix} a & b \\\\ c & d \\end{vmatrix}', 'déterminant'],
            ['≡ lignes', '\\begin{aligned} a &= b \\\\ &= c \\end{aligned}', 'calcul aligné'],
            ['txt', '\\text{texte}', 'texte dans une formule'],
        ] },
    ];

    const LANGAGES = [
        ['pseudo', 'Pseudo-code'], ['c', 'C'], ['python', 'Python'], ['sql', 'SQL'],
        ['javascript', 'JavaScript'], ['html', 'HTML'], ['css', 'CSS'], ['bash', 'Bash / Unix'],
        ['java', 'Java'], ['php', 'PHP'], ['', 'Texte brut'],
    ];

    const COULEURS = [
        ['rouge', '#dc2626'], ['orange', '#ea580c'], ['jaune', '#ca8a04'], ['vert', '#16a34a'],
        ['bleu', '#2563eb'], ['violet', '#7c3aed'], ['rose', '#db2777'], ['gris', '#6b7280'],
    ];

    window.installerOutilsEditeur = function ({ zone, barre, auChangement, auEnregistrement }) {
        const notifier = auChangement || (() => {});

        // =========================================================
        //  Historique (annuler / rétablir)
        //  On garde l'état du texte après chaque modification. Les frappes
        //  rapprochées (< 1 s, sans espace) sont regroupées en une étape.
        // =========================================================
        const histo = { pile: [], pos: -1, dernier: 0, groupable: false };

        function etat() {
            return { v: zone.value, d: zone.selectionStart, f: zone.selectionEnd };
        }
        function memoriser(regrouper) {
            const e = etat();
            const courant = histo.pile[histo.pos];
            if (courant && courant.v === e.v) { courant.d = e.d; courant.f = e.f; return; }
            const maintenant = Date.now();
            histo.pile.length = histo.pos + 1;
            if (regrouper && histo.groupable && histo.pos > 0 && maintenant - histo.dernier < 1000) {
                histo.pile[histo.pos] = e;
            } else {
                histo.pile.push(e);
                histo.pos++;
                if (histo.pile.length > 300) { histo.pile.shift(); histo.pos--; }
            }
            histo.groupable = regrouper;
            histo.dernier = maintenant;
        }
        function restaurer(e) {
            zone.value = e.v;
            zone.setSelectionRange(e.d, e.f);
            histo.groupable = false;
            notifier();
        }
        function annuler() {
            if (histo.pos > 0) restaurer(histo.pile[--histo.pos]);
        }
        function retablir() {
            if (histo.pos < histo.pile.length - 1) restaurer(histo.pile[++histo.pos]);
        }
        memoriser(false);

        zone.addEventListener('input', (e) => {
            const espace = typeof e.data === 'string' && /\s/.test(e.data);
            const frappe = /^(insertText|deleteContentBackward|deleteContentForward)$/.test(e.inputType || '');
            memoriser(frappe && !espace);
        });
        // Annuler depuis le menu du navigateur (clic droit, Édition…).
        zone.addEventListener('beforeinput', (e) => {
            if (e.inputType === 'historyUndo') { e.preventDefault(); annuler(); }
            if (e.inputType === 'historyRedo') { e.preventDefault(); retablir(); }
        });

        // =========================================================
        //  Modification du texte (toujours via cette fonction)
        // =========================================================
        function remplacer(debut, fin, texte, selDebut, selFin) {
            memoriser(false);
            zone.value = zone.value.slice(0, debut) + texte + zone.value.slice(fin);
            const d = selDebut ?? debut + texte.length;
            zone.focus();
            zone.setSelectionRange(d, selFin ?? d);
            memoriser(false);
            notifier();
        }

        // Entoure la sélection (ou un texte d'exemple sélectionné).
        function entourer(avant, apres, exemple) {
            const d = zone.selectionStart, f = zone.selectionEnd;
            const sel = zone.value.slice(d, f);
            // Déjà entouré ? on retire (bascule, comme dans un traitement de texte).
            if (sel && zone.value.slice(d - avant.length, d) === avant
                    && zone.value.slice(f, f + apres.length) === apres) {
                remplacer(d - avant.length, f + apres.length, sel,
                    d - avant.length, f - avant.length);
                return;
            }
            const contenu = sel || exemple;
            remplacer(d, f, avant + contenu + apres,
                d + avant.length, d + avant.length + contenu.length);
        }

        // Lignes touchées par la sélection.
        function lignesSelection() {
            const v = zone.value;
            const debut = v.lastIndexOf('\n', zone.selectionStart - 1) + 1;
            // Une sélection qui finit juste après un retour à la ligne
            // ne touche pas la ligne suivante.
            const f = zone.selectionEnd > zone.selectionStart && v[zone.selectionEnd - 1] === '\n'
                ? zone.selectionEnd - 1 : zone.selectionEnd;
            let fin = v.indexOf('\n', f);
            if (fin < 0) fin = v.length;
            return { debut, fin, lignes: v.slice(debut, fin).split('\n') };
        }

        // Ajoute / retire un préfixe au début de chaque ligne sélectionnée.
        function prefixerLignes(fabrique, motif) {
            const { debut, fin, lignes } = lignesSelection();
            const tous = lignes.every((l) => l.trim() === '' || motif.test(l.trimStart()));
            const nouvelles = lignes.map((l, i) => {
                if (l.trim() === '' && lignes.length > 1) return l;
                const r = l.match(/^\s*/)[0];
                const contenu = l.slice(r.length);
                return tous
                    ? r + contenu.replace(motif, '')
                    : r + fabrique(i) + contenu.replace(/^(?:[-*+]|\d+[.)])\s+(\[[ xX]\]\s+)?|^>\s?|^#{1,6}\s+/, '');
            });
            const texte = nouvelles.join('\n');
            remplacer(debut, fin, texte, debut + texte.length);
        }

        function indenter(retirer) {
            const { debut, fin, lignes } = lignesSelection();
            const nouvelles = lignes.map((l) => retirer
                ? l.replace(/^( {1,4}|\t)/, '')
                : '    ' + l);
            const texte = nouvelles.join('\n');
            remplacer(debut, fin, texte, debut, debut + texte.length);
        }

        // Insertion LaTeX : ajoute les $ si on n'est pas déjà dans une formule.
        function dansMaths(pos) {
            const avant = zone.value.slice(0, pos);
            const blocs = (avant.match(/\$\$/g) || []).length;
            if (blocs % 2 === 1) return true;
            const ligne = avant.slice(avant.lastIndexOf('\n') + 1).replace(/\$\$/g, '');
            return (ligne.match(/(?<!\\)\$/g) || []).length % 2 === 1;
        }
        function insererMaths(code) {
            const d = zone.selectionStart, f = zone.selectionEnd;
            if (dansMaths(d)) {
                remplacer(d, f, code);
            } else {
                const texte = '$' + code + '$';
                remplacer(d, f, texte, d + texte.length - 1);
            }
        }

        function insererBloc(texte, selDebut, selFin) {
            // Un bloc commence toujours sur une ligne vide.
            const d = zone.selectionStart, f = zone.selectionEnd;
            const avant = zone.value.slice(0, d);
            let prefixe = '';
            if (avant && !avant.endsWith('\n\n')) prefixe = avant.endsWith('\n') ? '\n' : '\n\n';
            const apres = zone.value.slice(f).startsWith('\n') ? '' : '\n';
            remplacer(d, f, prefixe + texte + apres,
                d + prefixe.length + selDebut, d + prefixe.length + (selFin ?? selDebut));
        }

        function insererCode(langage) {
            const sel = zone.value.slice(zone.selectionStart, zone.selectionEnd);
            const exemple = sel || (langage === 'pseudo'
                ? 'Algorithme nomAlgo\nVariables\n    i : Entier\n{\n    POUR i DE 1 À 10 FAIRE {\n        Ecrire(i)\n    }\n}'
                : '');
            const debutCode = 4 + langage.length;
            insererBloc('```' + langage + '\n' + exemple + '\n```', debutCode, debutCode + exemple.length);
        }

        // =========================================================
        //  Tableaux
        // =========================================================
        const RE_LIGNE_TAB = /^\s*\|/;
        const RE_SEPARATEUR = /^\s*:?-{1,}:?\s*$/;

        function cellules(ligne) {
            let l = ligne.trim();
            if (l.startsWith('|')) l = l.slice(1);
            if (l.endsWith('|') && !l.endsWith('\\|')) l = l.slice(0, -1);
            return l.split(/(?<!\\)\|/).map((c) => c.trim());
        }

        function blocTableau(pos) {
            const v = zone.value;
            const lignes = v.split('\n');
            let index = v.slice(0, pos).split('\n').length - 1;
            if (!RE_LIGNE_TAB.test(lignes[index] || '')) return null;
            let haut = index, bas = index;
            while (haut > 0 && RE_LIGNE_TAB.test(lignes[haut - 1])) haut--;
            while (bas < lignes.length - 1 && RE_LIGNE_TAB.test(lignes[bas + 1])) bas++;
            let debut = 0;
            for (let i = 0; i < haut; i++) debut += lignes[i].length + 1;
            const bloc = lignes.slice(haut, bas + 1);
            const fin = debut + bloc.join('\n').length;
            // Cellule où se trouve le curseur.
            let offLigne = debut;
            for (let i = haut; i < index; i++) offLigne += lignes[i].length + 1;
            const avantCurseur = lignes[index].slice(0, pos - offLigne);
            const nbBarres = (avantCurseur.match(/(?<!\\)\|/g) || []).length;
            const col = Math.max(0, nbBarres - (lignes[index].trim().startsWith('|') ? 1 : 0));
            return { debut, fin, lignes: bloc, ligne: index - haut, col };
        }

        // Aligne les colonnes ; renvoie le texte et la position de chaque cellule.
        function formaterTableau(lignes) {
            const rangs = lignes.map(cellules);
            const nbCol = Math.max(...rangs.map((r) => r.length));
            const estSep = rangs.map((r) => r.some((c) => RE_SEPARATEUR.test(c))
                && r.every((c) => RE_SEPARATEUR.test(c) || c === ''));
            rangs.forEach((r) => { while (r.length < nbCol) r.push(''); });
            const largeurs = [];
            for (let c = 0; c < nbCol; c++) {
                largeurs[c] = Math.max(3, ...rangs.filter((_, i) => !estSep[i]).map((r) => r[c].length));
            }
            const positions = [];
            let offset = 0;
            const texte = rangs.map((r, i) => {
                const pos = [];
                const morceaux = r.map((cellule, c) => {
                    const w = largeurs[c];
                    if (estSep[i]) {
                        const g = cellule.startsWith(':'), dr = cellule.endsWith(':') && cellule.length > 1;
                        return (g ? ':' : '-') + '-'.repeat(w - 2) + (dr ? ':' : '-');
                    }
                    return cellule + ' '.repeat(w - cellule.length);
                });
                let x = offset + 2;
                morceaux.forEach((m, c) => { pos.push([x, r[c].length]); x += largeurs[c] + 3; });
                positions.push(estSep[i] ? null : pos);
                const ligne = '| ' + morceaux.join(' | ') + ' |';
                offset += ligne.length + 1;
                return ligne;
            }).join('\n');
            return { texte, positions, nbCol };
        }

        // Tab / Maj+Tab dans un tableau : aligne puis va à la cellule voisine.
        function naviguerTableau(recul) {
            const t = blocTableau(zone.selectionStart);
            if (!t) return false;
            let lignes = t.lignes.slice();
            let { ligne, col } = t;
            let fmt = formaterTableau(lignes);
            if (recul) {
                col--;
                while (ligne >= 0 && (col < 0 || !fmt.positions[ligne])) {
                    ligne--; col = fmt.nbCol - 1;
                }
                if (ligne < 0) { ligne = 0; col = 0; }
            } else {
                col++;
                while (col >= fmt.nbCol || !fmt.positions[ligne]) {
                    ligne++; col = 0;
                    if (ligne >= lignes.length) {
                        lignes.push('|' + ' |'.repeat(fmt.nbCol));
                        fmt = formaterTableau(lignes);
                    }
                }
            }
            const [x, n] = fmt.positions[ligne][col];
            remplacer(t.debut, t.fin, fmt.texte, t.debut + x, t.debut + x + n);
            return true;
        }

        function insererTableau(nbLignes, nbCol) {
            const entete = [], vide = [];
            for (let c = 1; c <= nbCol; c++) { entete.push('Colonne ' + c); vide.push(''); }
            const lignes = ['| ' + entete.join(' | ') + ' |', '|' + ' --- |'.repeat(nbCol)];
            for (let l = 0; l < nbLignes; l++) lignes.push('| ' + vide.join(' | ') + ' |');
            const fmt = formaterTableau(lignes);
            const [x, n] = fmt.positions[0][0];
            insererBloc(fmt.texte, x, x + n);
        }

        // Collage depuis un tableur (colonnes séparées par des tabulations).
        zone.addEventListener('paste', (e) => {
            const texte = e.clipboardData && e.clipboardData.getData('text/plain');
            if (!texte || !texte.includes('\t')) return;
            const lignes = texte.replace(/\r\n?/g, '\n').replace(/\n+$/, '').split('\n');
            if (lignes.length < 2 || !lignes.every((l) => l.includes('\t'))) return;
            e.preventDefault();
            const rangs = lignes.map((l) => l.split('\t').map((c) => c.trim().replace(/\|/g, '\\|')));
            const nbCol = Math.max(...rangs.map((r) => r.length));
            const md = rangs.map((r) => '| ' + r.join(' | ') + ' |');
            md.splice(1, 0, '|' + ' --- |'.repeat(nbCol));
            const fmt = formaterTableau(md);
            insererBloc(fmt.texte, fmt.texte.length);
        });

        // =========================================================
        //  Clavier
        // =========================================================
        zone.addEventListener('keydown', (e) => {
            const mod = e.ctrlKey || e.metaKey;
            const k = e.key.toLowerCase();

            if (mod && !e.altKey && k === 'z' && !e.shiftKey) { e.preventDefault(); annuler(); return; }
            if (mod && !e.altKey && (k === 'y' || (k === 'z' && e.shiftKey))) { e.preventDefault(); retablir(); return; }
            if (mod && !e.altKey && k === 'b') { e.preventDefault(); entourer('**', '**', 'texte en gras'); return; }
            if (mod && !e.altKey && k === 'i') { e.preventDefault(); entourer('*', '*', 'texte en italique'); return; }

            if (e.key === 'Tab' && !mod && !e.altKey) {
                e.preventDefault();
                if (naviguerTableau(e.shiftKey)) return;
                if (e.shiftKey || zone.value.slice(zone.selectionStart, zone.selectionEnd).includes('\n')) {
                    indenter(e.shiftKey);
                } else {
                    remplacer(zone.selectionStart, zone.selectionEnd, '    ');
                }
                return;
            }

            // Entrée : continue la liste (ou la termine si l'élément est vide).
            if (e.key === 'Enter' && !mod && !e.shiftKey && !e.altKey
                    && zone.selectionStart === zone.selectionEnd) {
                const pos = zone.selectionStart;
                const debutLigne = zone.value.lastIndexOf('\n', pos - 1) + 1;
                const avant = zone.value.slice(debutLigne, pos);
                const m = avant.match(/^(\s*)([-*+]|(\d+)([.)]))(\s+)(\[[ xX]\]\s+)?/);
                if (!m) return;
                e.preventDefault();
                if (avant.slice(m[0].length).trim() === '' && zone.value.slice(pos).split('\n')[0].trim() === '') {
                    remplacer(debutLigne, pos, '');   // élément vide : fin de liste
                    return;
                }
                const puce = m[3] ? (parseInt(m[3], 10) + 1) + m[4] : m[2];
                remplacer(pos, pos, '\n' + m[1] + puce + m[5] + (m[6] ? '[ ] ' : ''));
            }
        });

        // Ctrl+S : enregistrement immédiat (sur toute la page de la note).
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 's') {
                e.preventDefault();
                if (auEnregistrement) auEnregistrement();
            }
        });

        // =========================================================
        //  Barre d'outils
        // =========================================================
        if (!barre) return;

        const actions = {
            gras:      () => entourer('**', '**', 'texte en gras'),
            italique:  () => entourer('*', '*', 'texte en italique'),
            barre:     () => entourer('~~', '~~', 'texte barré'),
            surligner: () => entourer('==', '==', 'texte surligné'),
            code:      () => entourer('`', '`', 'code'),
            titre1:    () => prefixerLignes(() => '# ', /^#\s+/),
            titre2:    () => prefixerLignes(() => '## ', /^##\s+/),
            titre3:    () => prefixerLignes(() => '### ', /^###\s+/),
            puces:     () => prefixerLignes(() => '- ', /^[-*+]\s+(?!\[[ xX]\])/),
            numeros:   () => prefixerLignes((i) => (i + 1) + '. ', /^\d+[.)]\s+/),
            cases:     () => prefixerLignes(() => '- [ ] ', /^[-*+]\s+\[[ xX]\]\s+/),
            citation:  () => prefixerLignes(() => '> ', /^>\s?/),
            lien:      () => {
                const d = zone.selectionStart, f = zone.selectionEnd;
                const sel = zone.value.slice(d, f) || 'texte du lien';
                const texte = '[' + sel + '](https://)';
                remplacer(d, f, texte, d + sel.length + 3, d + texte.length - 1);
            },
            separateur: () => insererBloc('---', 3),
            maths:     () => insererMaths(''),
            mathsBloc: () => {
                const sel = zone.value.slice(zone.selectionStart, zone.selectionEnd) || 'f(x) = x^2';
                insererBloc('$$\n' + sel + '\n$$', 3, 3 + sel.length);
            },
            annuler, retablir,
            enregistrer: () => auEnregistrement && auEnregistrement(),
        };

        // Menus déroulants (couleurs, maths, code, tableau).
        function fermerMenus(sauf) {
            barre.querySelectorAll('.outil-menu.ouvert').forEach((m) => {
                if (m !== sauf) m.classList.remove('ouvert');
            });
        }
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.outil-deroulant')) fermerMenus();
        });

        function construireMenus() {
            // Couleurs
            const menuCouleurs = barre.querySelector('[data-menu="couleurs"]');
            if (menuCouleurs) {
                COULEURS.forEach(([nom, hex]) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'pastille';
                    b.style.background = hex;
                    b.title = nom + '  →  [texte]{' + nom + '}';
                    b.addEventListener('click', () => { fermerMenus(); entourer('[', ']{' + nom + '}', 'texte'); });
                    menuCouleurs.appendChild(b);
                });
                const s = document.createElement('button');
                s.type = 'button';
                s.className = 'menu-ligne';
                s.innerHTML = '<mark>Surligner</mark> <small>==texte==</small>';
                s.addEventListener('click', () => { fermerMenus(); actions.surligner(); });
                menuCouleurs.appendChild(s);
            }

            // Maths
            const menuMaths = barre.querySelector('[data-menu="maths"]');
            if (menuMaths) {
                const haut = document.createElement('div');
                haut.className = 'menu-actions';
                [['Formule dans le texte  $…$', 'maths'], ['Formule centrée  $$…$$', 'mathsBloc']]
                    .forEach(([label, action]) => {
                        const b = document.createElement('button');
                        b.type = 'button'; b.className = 'menu-ligne'; b.textContent = label;
                        b.addEventListener('click', () => { fermerMenus(); actions[action](); });
                        haut.appendChild(b);
                    });
                menuMaths.appendChild(haut);
                MATHS.forEach(({ groupe, items }) => {
                    const h = document.createElement('div');
                    h.className = 'menu-titre'; h.textContent = groupe;
                    const grille = document.createElement('div');
                    grille.className = 'grille-symboles';
                    items.forEach(([label, code, desc]) => {
                        const b = document.createElement('button');
                        b.type = 'button'; b.className = 'symbole'; b.textContent = label;
                        b.title = (desc ? desc + '  —  ' : '') + code.trim();
                        b.addEventListener('click', () => insererMaths(code));
                        grille.appendChild(b);
                    });
                    menuMaths.append(h, grille);
                });
            }

            // Blocs de code
            const menuCode = barre.querySelector('[data-menu="code"]');
            if (menuCode) {
                LANGAGES.forEach(([id, nom]) => {
                    const b = document.createElement('button');
                    b.type = 'button'; b.className = 'menu-ligne'; b.textContent = nom;
                    b.addEventListener('click', () => { fermerMenus(); insererCode(id); });
                    menuCode.appendChild(b);
                });
            }

            // Tableau : grille à survoler (comme dans un traitement de texte).
            const menuTab = barre.querySelector('[data-menu="tableau"]');
            if (menuTab) {
                const grille = document.createElement('div');
                grille.className = 'grille-tableau';
                const legende = document.createElement('div');
                legende.className = 'menu-titre';
                legende.textContent = 'Choisis la taille';
                const MAXL = 8, MAXC = 8;
                for (let l = 1; l <= MAXL; l++) {
                    for (let c = 1; c <= MAXC; c++) {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.dataset.l = l; b.dataset.c = c;
                        b.addEventListener('mouseenter', () => {
                            legende.textContent = c + ' colonnes × ' + l + ' lignes';
                            grille.querySelectorAll('button').forEach((x) => x.classList.toggle('actif',
                                +x.dataset.l <= l && +x.dataset.c <= c));
                        });
                        b.addEventListener('click', () => { fermerMenus(); insererTableau(l, c); });
                        grille.appendChild(b);
                    }
                }
                const aide = document.createElement('p');
                aide.className = 'menu-aide';
                aide.innerHTML = '<b>Tab</b> : cellule suivante (et nouvelle ligne à la fin)<br>'
                    + '<b>Maj+Tab</b> : cellule précédente<br>Coller depuis Excel/Sheets = tableau auto';
                const aligner = document.createElement('button');
                aligner.type = 'button'; aligner.className = 'menu-ligne';
                aligner.textContent = '↔ Aligner le tableau sous le curseur';
                aligner.addEventListener('click', () => {
                    fermerMenus();
                    const t = blocTableau(zone.selectionStart);
                    if (t) remplacer(t.debut, t.fin, formaterTableau(t.lignes).texte, zone.selectionStart);
                });
                menuTab.append(legende, grille, aligner, aide);
            }
        }
        construireMenus();

        barre.addEventListener('mousedown', (e) => {
            // Garde la sélection du texte quand on clique sur un bouton.
            if (e.target.closest('button')) e.preventDefault();
        });
        barre.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            if (btn.dataset.ouvrir) {
                const menu = barre.querySelector('[data-menu="' + btn.dataset.ouvrir + '"]');
                fermerMenus(menu);
                menu.classList.toggle('ouvert');
                return;
            }
            if (btn.dataset.action && actions[btn.dataset.action]) actions[btn.dataset.action]();
        });
    };
})();
