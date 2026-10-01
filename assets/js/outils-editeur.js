/* ============================================================
   Outils de saisie de l'éditeur de note :
   - barre d'outils (gras, titres, listes, couleurs, maths, code, tableaux)
   - historique maison : Ctrl+Z / Ctrl+Y (fiable même après un bouton)
   - Ctrl+S = enregistrer tout de suite, Ctrl+B / Ctrl+I
   - Entrée continue une liste, Tab / Maj+Tab indentent
   - MATHS : palette avec recherche, autocomplétion en tapant \ (ex. \pourtout
     ou \forall), champs à remplir (Tab = champ suivant), aperçu de la
     formule sous le curseur
   - TABLEAUX : éditeur visuel (comme un tableur), « Nom | Âge » + Entrée
     crée un tableau, Entrée ajoute une ligne, Tab passe à la case suivante,
     coller depuis Excel / Sheets crée un tableau
   Utilisation : window.installerOutilsEditeur({ zone, barre, auChangement, auEnregistrement })
   Données des symboles : assets/js/maths-symboles.js
   ============================================================ */

(function () {
    const LANGAGES = [
        ['pseudo', 'Pseudo-code'], ['c', 'C'], ['python', 'Python'], ['sql', 'SQL'],
        ['javascript', 'JavaScript'], ['html', 'HTML'], ['css', 'CSS'], ['bash', 'Bash / Unix'],
        ['java', 'Java'], ['php', 'PHP'], ['math', 'Formule LaTeX (bloc)'], ['', 'Texte brut'],
    ];

    const COULEURS = [
        ['rouge', '#dc2626'], ['orange', '#ea580c'], ['jaune', '#ca8a04'], ['vert', '#16a34a'],
        ['bleu', '#2563eb'], ['violet', '#7c3aed'], ['rose', '#db2777'], ['gris', '#6b7280'],
    ];

    // Sans accents, en minuscules (pour les recherches).
    function normaliser(s) {
        return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }
    function echapperHtml(s) {
        return s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    }
    // « \frac{‹a›}{‹b›} » -> texte sans marqueurs + positions des champs.
    function preparerModele(code) {
        let texte = '';
        const champs = [];
        code.split(/(‹[^›]*›)/).forEach((morceau) => {
            if (morceau.startsWith('‹') && morceau.endsWith('›')) {
                const contenu = morceau.slice(1, -1);
                champs.push([texte.length, texte.length + contenu.length]);
                texte += contenu;
            } else {
                texte += morceau;
            }
        });
        return { texte, champs };
    }
    window.preparerModeleMaths = preparerModele;   // réutilisé par la page d'aide

    window.installerOutilsEditeur = function ({ zone, barre, auChangement, auEnregistrement }) {
        const notifier = auChangement || (() => {});
        const MATHS = window.MATHS_SYMBOLES || [];
        const COMMANDES = window.MATHS_COMMANDES || [];

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
            champs = [];
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
            longueurPrec = zone.value.length;
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
            const exemples = {
                pseudo: 'Algorithme nomAlgo\nVariables\n    i : Entier\n{\n    POUR i DE 1 À 10 FAIRE {\n        Ecrire(i)\n    }\n}',
                math: 'f(x) = x^2',
            };
            const exemple = sel || exemples[langage] || '';
            const debutCode = 4 + langage.length;
            insererBloc('```' + langage + '\n' + exemple + '\n```', debutCode, debutCode + exemple.length);
        }

        // Dans un bloc de code ``` ? (on n'y fait pas de conversion automatique)
        function dansBlocCode(pos) {
            const avant = zone.value.slice(0, zone.value.lastIndexOf('\n', pos - 1) + 1);
            return ((avant.match(/^[ \t]*(```|~~~)/gm) || []).length) % 2 === 1;
        }

        // =========================================================
        //  Position du curseur à l'écran (pour les bulles flottantes)
        // =========================================================
        const PROPS_MIROIR = ['boxSizing', 'width', 'fontFamily', 'fontSize', 'fontWeight',
            'lineHeight', 'letterSpacing', 'paddingTop', 'paddingRight', 'paddingBottom',
            'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth',
            'borderLeftWidth', 'tabSize', 'wordSpacing', 'textIndent'];
        function coordsCurseur(pos) {
            const cs = getComputedStyle(zone);
            const m = document.createElement('div');
            PROPS_MIROIR.forEach((p) => { m.style[p] = cs[p]; });
            Object.assign(m.style, { position: 'absolute', visibility: 'hidden', top: '0',
                left: '-9999px', whiteSpace: 'pre-wrap', overflowWrap: 'break-word' });
            m.textContent = zone.value.slice(0, pos);
            const s = document.createElement('span');
            s.textContent = zone.value.slice(pos, pos + 1) || '.';
            m.appendChild(s);
            document.body.appendChild(m);
            const r = zone.getBoundingClientRect();
            const h = parseFloat(cs.lineHeight) || parseFloat(cs.fontSize) * 1.5;
            const res = { x: r.left + s.offsetLeft - zone.scrollLeft,
                          y: r.top + s.offsetTop - zone.scrollTop, h };
            m.remove();
            return res;
        }
        function placerBulle(el, pos) {
            const c = coordsCurseur(pos);
            const r = zone.getBoundingClientRect();
            if (c.y < r.top - 4 || c.y > r.bottom) { el.hidden = true; return; }
            el.hidden = false;
            const larg = el.offsetWidth, haut = el.offsetHeight;
            let x = Math.min(c.x, window.innerWidth - larg - 8);
            let y = c.y + c.h + 4;
            if (y + haut > window.innerHeight - 8) y = c.y - haut - 4;   // pas de place dessous
            el.style.left = Math.max(8, x) + 'px';
            el.style.top = Math.max(8, y) + 'px';
        }

        // =========================================================
        //  MATHS
        // =========================================================

        // Formule qui contient la position : { debut, fin, bloc } (contenu sans les $).
        function zoneMaths(pos) {
            const v = zone.value;
            const avant = v.slice(0, pos);
            const nbBlocs = (avant.match(/(?<!\\)\$\$/g) || []).length;
            if (nbBlocs % 2 === 1) {
                const debut = avant.lastIndexOf('$$') + 2;
                let fin = v.indexOf('$$', pos);
                if (fin < 0) fin = v.length;
                return { debut, fin, bloc: true };
            }
            // Formule $…$ sur la ligne.
            const dl = v.lastIndexOf('\n', pos - 1) + 1;
            let fl = v.indexOf('\n', pos);
            if (fl < 0) fl = v.length;
            const ligne = v.slice(dl, fl);
            const dollars = [];
            for (let i = 0; i < ligne.length; i++) {
                if (ligne[i] !== '$' || ligne[i - 1] === '\\') continue;
                if (ligne[i + 1] === '$') { i++; continue; }      // $$ : ignoré ici
                dollars.push(dl + i);
            }
            for (let i = 0; i < dollars.length; i += 2) {
                const ouvre = dollars[i], ferme = dollars[i + 1] ?? fl;
                if (pos > ouvre && pos <= ferme) return { debut: ouvre + 1, fin: ferme, bloc: false };
            }
            return null;
        }

        // Champs à remplir du dernier modèle inséré (positions absolues).
        let champs = [];
        let longueurPrec = zone.value.length;
        zone.addEventListener('input', () => {
            // Décale les champs situés après la modification.
            const delta = zone.value.length - longueurPrec;
            const p = zone.selectionStart - Math.max(delta, 0);
            champs = champs.map(([a, b]) => (a >= p ? [a + delta, b + delta] : [a, b]))
                .filter(([a, b]) => a >= 0 && b <= zone.value.length);
            longueurPrec = zone.value.length;
        });

        // Insère un modèle ; ajoute les $ si on n'est pas déjà dans une formule.
        function insererMaths(code, debut, fin) {
            const d = debut ?? zone.selectionStart, f = fin ?? zone.selectionEnd;
            const { texte, champs: ch } = preparerModele(code);
            const dans = zoneMaths(d);
            const avant = dans ? '' : '$';
            const apres = dans ? '' : '$';
            const base = d + avant.length;
            const nouveaux = ch.map(([a, b]) => [base + a, base + b]);
            if (nouveaux.length) {
                remplacer(d, f, avant + texte + apres, nouveaux[0][0], nouveaux[0][1]);
                champs = nouveaux.slice(1);
            } else {
                remplacer(d, f, avant + texte + apres, base + texte.length);
                champs = [];
            }
            majApercuMaths();
        }

        // Tab dans une formule : champ suivant, sinon {…} suivant, sinon sortie de la formule.
        function champSuivant() {
            const z = zoneMaths(zone.selectionStart);
            if (!z) return false;
            const apres = zone.selectionEnd;
            const suivant = champs.find(([a]) => a >= apres && a <= z.fin);
            if (suivant) {
                champs = champs.filter((c) => c !== suivant);
                zone.setSelectionRange(suivant[0], suivant[1]);
                return true;
            }
            const v = zone.value;
            const acc = v.indexOf('{', apres);
            if (acc >= 0 && acc < z.fin) {
                let niveau = 0, i = acc;
                for (; i < z.fin; i++) {
                    if (v[i] === '{') niveau++;
                    if (v[i] === '}' && --niveau === 0) break;
                }
                zone.setSelectionRange(acc + 1, i);
                return true;
            }
            const sortie = Math.min(v.length, z.fin + (z.bloc ? 2 : 1));
            zone.setSelectionRange(sortie, sortie);
            champs = [];
            return true;
        }

        // ---------- Autocomplétion : \ + lettres ----------
        const popup = document.createElement('div');
        popup.className = 'maths-auto';
        popup.hidden = true;
        document.body.appendChild(popup);
        let suggestions = [], choix = 0, jeton = null;

        function chercherCommandes(q) {
            const nq = normaliser(q);
            const res = [];
            COMMANDES.forEach((c) => {
                const nom = c.nom.slice(1).toLowerCase();
                const desc = normaliser(c.desc + ' ' + c.groupe);
                let score = -1;
                if (nom === nq) score = 0;
                else if (nom.startsWith(nq)) score = 1;
                else if (desc.split(/[\s()/,'’-]+/).some((m) => m.startsWith(nq))) score = 2;
                else if (nq.length >= 3 && (nom.includes(nq) || desc.replace(/\s+/g, '').includes(nq))) score = 3;
                if (score >= 0) res.push({ c, score });
            });
            res.sort((a, b) => a.score - b.score || a.c.nom.length - b.c.nom.length);
            return res.slice(0, 8).map((r) => r.c);
        }
        function fermerAuto() { popup.hidden = true; suggestions = []; jeton = null; }
        function majAuto() {
            const pos = zone.selectionStart;
            if (pos !== zone.selectionEnd) return fermerAuto();
            const m = zone.value.slice(Math.max(0, pos - 30), pos).match(/\\([a-zA-ZÀ-ÿ]+)$/);
            const dans = zoneMaths(pos);
            // Hors formule, on attend 2 lettres (le \ sert rarement dans le texte).
            if (!m || (!dans && m[1].length < 2) || dansBlocCode(pos)) return fermerAuto();
            suggestions = chercherCommandes(m[1]);
            if (!suggestions.length) return fermerAuto();
            jeton = { debut: pos - m[0].length, fin: pos, dans: !!dans };
            choix = 0;
            dessinerAuto();
            placerBulle(popup, jeton.debut);
        }
        function dessinerAuto() {
            popup.innerHTML = suggestions.map((s, i) =>
                '<div class="auto-ligne' + (i === choix ? ' actif' : '') + '" data-i="' + i + '">'
                + '<span class="auto-rendu">' + echapperHtml(s.label) + '</span>'
                + '<code>' + echapperHtml(s.code.replace(/[‹›]/g, '')) + '</code>'
                + '<span class="auto-desc">' + echapperHtml(s.desc) + '</span></div>').join('')
                + '<div class="auto-aide">↑↓ choisir · Entrée/Tab valider · Échap fermer</div>';
        }
        function validerAuto(i) {
            const s = suggestions[i ?? choix];
            const j = jeton;
            fermerAuto();
            if (!s || !j) return;
            insererMaths(s.code, j.debut, j.fin);
        }
        popup.addEventListener('mousedown', (e) => {
            const l = e.target.closest('.auto-ligne');
            if (!l) return;
            e.preventDefault();
            validerAuto(parseInt(l.dataset.i, 10));
        });

        // ---------- Aperçu de la formule sous le curseur ----------
        const bulle = document.createElement('div');
        bulle.className = 'maths-bulle';
        bulle.hidden = true;
        document.body.appendChild(bulle);
        let dernierFormule = null, minuteurBulle = null;

        function majApercuMaths() {
            clearTimeout(minuteurBulle);
            minuteurBulle = setTimeout(() => {
                const z = document.activeElement === zone && zoneMaths(zone.selectionStart);
                if (!z || !window.MathJax || !MathJax.typesetPromise || !popup.hidden) {
                    bulle.hidden = true;
                    return;
                }
                const formule = zone.value.slice(z.debut, z.fin).trim();
                if (!formule) { bulle.hidden = true; return; }
                if (formule !== dernierFormule) {
                    dernierFormule = formule;
                    if (MathJax.typesetClear) MathJax.typesetClear([bulle]);
                    bulle.textContent = '\\[' + formule + '\\]';
                    MathJax.typesetPromise([bulle])
                        .then(() => placerBulle(bulle, z.fin))
                        .catch(() => {});
                }
                placerBulle(bulle, z.fin);
            }, 150);
        }
        ['keyup', 'click', 'input', 'focus'].forEach((ev) => zone.addEventListener(ev, majApercuMaths));
        zone.addEventListener('scroll', () => { bulle.hidden = true; fermerAuto(); });
        zone.addEventListener('blur', () => setTimeout(() => {
            if (document.activeElement !== zone) { bulle.hidden = true; fermerAuto(); }
        }, 150));
        zone.addEventListener('input', majAuto);

        // =========================================================
        //  TABLEAUX
        // =========================================================
        const RE_LIGNE_TAB = /^\s*\|/;
        const RE_SEPARATEUR = /^\s*:?-{1,}:?\s*$/;

        function cellules(ligne) {
            let l = ligne.trim();
            if (l.startsWith('|')) l = l.slice(1);
            if (l.endsWith('|') && !l.endsWith('\\|')) l = l.slice(0, -1);
            return l.split(/(?<!\\)\|/).map((c) => c.trim());
        }
        const estSeparateur = (r) => r.some((c) => RE_SEPARATEUR.test(c))
            && r.every((c) => RE_SEPARATEUR.test(c) || c === '');

        function blocTableau(pos) {
            const v = zone.value;
            const lignes = v.split('\n');
            const index = v.slice(0, pos).split('\n').length - 1;
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
            const estSep = rangs.map(estSeparateur);
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

        const ligneVide = (n) => '|' + ' |'.repeat(n);

        // Tab / Maj+Tab dans un tableau : aligne puis va à la cellule voisine.
        function naviguerTableau(recul) {
            const t = blocTableau(zone.selectionStart);
            if (!t) return false;
            const lignes = t.lignes.slice();
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
                        lignes.push(ligneVide(fmt.nbCol));
                        fmt = formaterTableau(lignes);
                    }
                }
            }
            const [x, n] = fmt.positions[ligne][col];
            remplacer(t.debut, t.fin, fmt.texte, t.debut + x, t.debut + x + n);
            return true;
        }

        // Entrée dans un tableau : nouvelle ligne en dessous (ou sortie si ligne vide).
        function entreeTableau() {
            const t = blocTableau(zone.selectionStart);
            if (!t) return false;
            const lignes = t.lignes.slice();
            const rang = cellules(lignes[t.ligne]);
            if (t.ligne >= 2 && rang.every((c) => c === '')) {
                // Ligne vide : on la supprime et on sort du tableau
                // (une ligne blanche est nécessaire pour que le texte suivant
                // ne soit pas pris pour une ligne du tableau).
                lignes.splice(t.ligne, 1);
                const texte = formaterTableau(lignes).texte;
                const sauts = zone.value.slice(t.fin).match(/^\n*/)[0].length;
                const reste = zone.value.slice(t.fin + sauts) !== '';
                remplacer(t.debut, t.fin + sauts, texte + '\n\n' + (reste ? '\n' : ''),
                    t.debut + texte.length + 2);
                return true;
            }
            const nbCol = formaterTableau(lignes).nbCol;
            const cible = t.ligne === 0 && lignes[1] && estSeparateur(cellules(lignes[1])) ? 2 : t.ligne + 1;
            lignes.splice(cible, 0, ligneVide(nbCol));
            const fmt = formaterTableau(lignes);
            const [x] = fmt.positions[cible][0];
            remplacer(t.debut, t.fin, fmt.texte, t.debut + x);
            return true;
        }

        // « Nom | Âge | Ville » + Entrée : devient un tableau.
        function ligneEnTableau() {
            const pos = zone.selectionStart;
            const v = zone.value;
            const dl = v.lastIndexOf('\n', pos - 1) + 1;
            let fl = v.indexOf('\n', pos);
            if (fl < 0) fl = v.length;
            if (v.slice(pos, fl).trim() !== '') return false;      // curseur en fin de ligne
            const ligne = v.slice(dl, fl);
            if (!ligne.includes('|') || RE_LIGNE_TAB.test(ligne) || ligne.includes('$')
                || /^\s*([-*+>#]|\d+[.)])\s/.test(ligne) || dansBlocCode(pos)) return false;
            const titres = ligne.split(/(?<!\\)\|/).map((c) => c.trim());
            if (titres.length < 2 || titres.some((c) => c === '')) return false;
            const retrait = ligne.match(/^\s*/)[0];
            const fmt = formaterTableau(['| ' + titres.join(' | ') + ' |',
                '|' + ' --- |'.repeat(titres.length), ligneVide(titres.length)]);
            const texte = fmt.texte.split('\n').map((l) => retrait + l).join('\n');
            const [x] = fmt.positions[2][0];
            remplacer(dl, fl, texte, dl + x + retrait.length * 3);
            return true;
        }

        // Collage depuis un tableur (colonnes séparées par des tabulations).
        function lireTableur(texte) {
            if (!texte || !texte.includes('\t')) return null;
            const lignes = texte.replace(/\r\n?/g, '\n').replace(/\n+$/, '').split('\n');
            if (!lignes.every((l) => l.includes('\t'))) return null;
            return lignes.map((l) => l.split('\t').map((c) => c.trim()));
        }
        const echapperBarres = (c) => c.replace(/(?<!\\)\|/g, '\\|').replace(/\n/g, ' ');

        zone.addEventListener('paste', (e) => {
            const rangs = lireTableur(e.clipboardData && e.clipboardData.getData('text/plain'));
            if (!rangs || rangs.length < 2) return;
            e.preventDefault();
            const nbCol = Math.max(...rangs.map((r) => r.length));
            const md = rangs.map((r) => '| ' + r.map(echapperBarres).join(' | ') + ' |');
            md.splice(1, 0, '|' + ' --- |'.repeat(nbCol));
            const fmt = formaterTableau(md);
            insererBloc(fmt.texte, fmt.texte.length);
        });

        // ---------- Éditeur visuel de tableau (fenêtre type tableur) ----------
        function ouvrirEditeurTableau() {
            const existant = blocTableau(zone.selectionStart);
            let donnees, aligns;
            if (existant) {
                const rangs = existant.lignes.map(cellules);
                const sep = rangs.findIndex(estSeparateur);
                const nbCol = Math.max(...rangs.map((r) => r.length));
                aligns = Array.from({ length: nbCol }, (_, c) => {
                    const s = sep >= 0 ? (rangs[sep][c] || '') : '';
                    const g = s.startsWith(':'), d = s.endsWith(':') && s.length > 1;
                    return g && d ? 'center' : d ? 'right' : g ? 'left' : '';
                });
                donnees = rangs.filter((_, i) => i !== sep)
                    .map((r) => Array.from({ length: nbCol }, (_, c) => r[c] || ''));
            } else {
                donnees = [['', '', ''], ['', '', ''], ['', '', '']];
                aligns = ['', '', ''];
            }
            const selDebut = zone.selectionStart, selFin = zone.selectionEnd;

            const fond = document.createElement('div');
            fond.className = 'modale-fond';
            fond.innerHTML = `
                <div class="modale tab-editeur" role="dialog" aria-label="Éditeur de tableau">
                    <div class="modale-entete">
                        <h3>▦ ${existant ? 'Modifier le tableau' : 'Nouveau tableau'}</h3>
                        <button type="button" class="modale-x" title="Fermer (Échap)">✕</button>
                    </div>
                    <div class="tab-actions">
                        <button type="button" data-t="ligne">+ Ligne</button>
                        <button type="button" data-t="colonne">+ Colonne</button>
                        <button type="button" data-t="suppr-ligne">− Ligne</button>
                        <button type="button" data-t="suppr-colonne">− Colonne</button>
                        <span class="outil-sep"></span>
                        <span class="tab-libelle">Colonne :</span>
                        <button type="button" data-a="left" title="Aligner à gauche">⇤</button>
                        <button type="button" data-a="center" title="Centrer">↔</button>
                        <button type="button" data-a="right" title="Aligner à droite">⇥</button>
                    </div>
                    <div class="tab-grille-cadre"><table class="tab-grille"></table></div>
                    <p class="menu-aide"><b>Tab</b> case suivante (crée une ligne à la fin) ·
                        <b>Entrée</b> case du dessous · <b>Ctrl+Entrée</b> insérer ·
                        colle un tableau Excel / Sheets dans une case pour tout remplir.
                        La 1<sup>re</sup> ligne est l'en-tête. Gras, couleurs et $maths$ acceptés.</p>
                    <div class="modale-pied">
                        <button type="button" class="btn-secondaire" data-t="annuler">Annuler</button>
                        <button type="button" class="btn-primaire" data-t="inserer">
                            ${existant ? 'Mettre à jour' : 'Insérer dans la note'}</button>
                    </div>
                </div>`;
            document.body.appendChild(fond);
            const table = fond.querySelector('.tab-grille');
            let actif = [0, 0];

            function dessiner(focus) {
                table.innerHTML = '';
                donnees.forEach((rang, r) => {
                    const tr = table.insertRow();
                    rang.forEach((val, c) => {
                        const td = tr.insertCell();
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.value = val;
                        input.dataset.r = r;
                        input.dataset.c = c;
                        input.placeholder = r === 0 ? 'Titre ' + (c + 1) : '';
                        if (r === 0) td.classList.add('entete');
                        if (aligns[c]) input.style.textAlign = aligns[c];
                        td.appendChild(input);
                    });
                });
                if (focus) aller(focus[0], focus[1]);
            }
            function aller(r, c) {
                r = Math.max(0, Math.min(donnees.length - 1, r));
                c = Math.max(0, Math.min(donnees[0].length - 1, c));
                const input = table.querySelector(`input[data-r="${r}"][data-c="${c}"]`);
                if (input) { input.focus(); input.select(); }
            }
            const nbCol = () => donnees[0].length;
            function ajouterLigne(apres) {
                donnees.splice(apres + 1, 0, Array(nbCol()).fill(''));
            }

            table.addEventListener('input', (e) => {
                const i = e.target;
                donnees[+i.dataset.r][+i.dataset.c] = i.value;
            });
            table.addEventListener('focusin', (e) => {
                if (e.target.dataset.r) actif = [+e.target.dataset.r, +e.target.dataset.c];
            });
            table.addEventListener('keydown', (e) => {
                const [r, c] = actif;
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); inserer(); return; }
                if (e.key === 'Tab') {
                    e.preventDefault();
                    if (e.shiftKey) {
                        if (c > 0) aller(r, c - 1); else if (r > 0) aller(r - 1, nbCol() - 1);
                    } else if (c < nbCol() - 1) {
                        aller(r, c + 1);
                    } else {
                        if (r === donnees.length - 1) { ajouterLigne(r); dessiner(); }
                        aller(r + 1, 0);
                    }
                } else if (e.key === 'Enter' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (e.key === 'Enter' && e.shiftKey) { aller(r - 1, c); return; }
                    if (r === donnees.length - 1) {
                        if (e.key === 'ArrowDown') return;
                        ajouterLigne(r); dessiner();
                    }
                    aller(r + 1, c);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault(); aller(r - 1, c);
                }
            });
            // Coller depuis un tableur : remplit la grille à partir de la case.
            table.addEventListener('paste', (e) => {
                const rangs = lireTableur(e.clipboardData && e.clipboardData.getData('text/plain'));
                if (!rangs) return;
                e.preventDefault();
                const [r0, c0] = actif;
                rangs.forEach((rang, i) => rang.forEach((val, j) => {
                    while (donnees.length <= r0 + i) donnees.push(Array(nbCol()).fill(''));
                    while (nbCol() <= c0 + j) { donnees.forEach((d) => d.push('')); aligns.push(''); }
                    donnees[r0 + i][c0 + j] = val;
                }));
                dessiner([r0, c0]);
            });

            fond.querySelector('.tab-actions').addEventListener('mousedown', (e) => e.preventDefault());
            fond.querySelector('.tab-actions').addEventListener('click', (e) => {
                const b = e.target.closest('button');
                if (!b) return;
                const [r, c] = actif;
                if (b.dataset.t === 'ligne') { ajouterLigne(r); dessiner([r + 1, c]); }
                if (b.dataset.t === 'colonne') {
                    donnees.forEach((d) => d.splice(c + 1, 0, ''));
                    aligns.splice(c + 1, 0, '');
                    dessiner([r, c + 1]);
                }
                if (b.dataset.t === 'suppr-ligne' && donnees.length > 2) {
                    donnees.splice(r, 1); dessiner([Math.min(r, donnees.length - 1), c]);
                }
                if (b.dataset.t === 'suppr-colonne' && nbCol() > 1) {
                    donnees.forEach((d) => d.splice(c, 1)); aligns.splice(c, 1);
                    dessiner([r, Math.min(c, nbCol() - 1)]);
                }
                if (b.dataset.a) {
                    aligns[c] = aligns[c] === b.dataset.a ? '' : b.dataset.a;
                    dessiner([r, c]);
                }
            });

            function fermer() {
                fond.remove();
                document.removeEventListener('keydown', echap, true);
                zone.focus();
                zone.setSelectionRange(selDebut, selFin);
            }
            function echap(e) { if (e.key === 'Escape') { e.preventDefault(); fermer(); } }
            document.addEventListener('keydown', echap, true);

            function inserer() {
                const sep = aligns.map((a) => (a === 'center' ? ':---:' : a === 'right' ? '---:' : a === 'left' ? ':---' : '---'));
                const md = donnees.map((r, i) => '| ' + r.map((c, j) =>
                    echapperBarres(c.trim()) || (i === 0 ? 'Colonne ' + (j + 1) : '')).join(' | ') + ' |');
                md.splice(1, 0, '| ' + sep.join(' | ') + ' |');
                const texte = formaterTableau(md).texte;
                fond.remove();
                document.removeEventListener('keydown', echap, true);
                zone.focus();
                if (existant) {
                    remplacer(existant.debut, existant.fin, texte, existant.debut + texte.length);
                } else {
                    zone.setSelectionRange(selDebut, selFin);
                    insererBloc(texte, texte.length);
                }
            }

            fond.addEventListener('click', (e) => {
                if (e.target === fond || e.target.closest('.modale-x') || e.target.dataset.t === 'annuler') fermer();
                if (e.target.closest('[data-t="inserer"]')) inserer();
            });
            dessiner([0, 0]);
        }

        // =========================================================
        //  Clavier
        // =========================================================
        zone.addEventListener('keydown', (e) => {
            const mod = e.ctrlKey || e.metaKey;
            const k = e.key.toLowerCase();

            // Autocomplétion ouverte : elle a la priorité.
            if (!popup.hidden && suggestions.length) {
                if (e.key === 'ArrowDown') { e.preventDefault(); choix = (choix + 1) % suggestions.length; dessinerAuto(); return; }
                if (e.key === 'ArrowUp') { e.preventDefault(); choix = (choix - 1 + suggestions.length) % suggestions.length; dessinerAuto(); return; }
                if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); validerAuto(); return; }
                if (e.key === 'Escape') { e.preventDefault(); fermerAuto(); return; }
            }

            if (mod && !e.altKey && k === 'z' && !e.shiftKey) { e.preventDefault(); annuler(); return; }
            if (mod && !e.altKey && (k === 'y' || (k === 'z' && e.shiftKey))) { e.preventDefault(); retablir(); return; }
            if (mod && !e.altKey && k === 'b') { e.preventDefault(); entourer('**', '**', 'texte en gras'); return; }
            if (mod && !e.altKey && k === 'i') { e.preventDefault(); entourer('*', '*', 'texte en italique'); return; }
            if (mod && !e.altKey && k === 'm') { e.preventDefault(); insererMaths(''); return; }
            if (e.key === 'Escape') { champs = []; bulle.hidden = true; return; }

            if (e.key === 'Tab' && !mod && !e.altKey) {
                e.preventDefault();
                if (!e.shiftKey && champSuivant()) return;
                if (naviguerTableau(e.shiftKey)) return;
                if (e.shiftKey || zone.value.slice(zone.selectionStart, zone.selectionEnd).includes('\n')) {
                    indenter(e.shiftKey);
                } else {
                    remplacer(zone.selectionStart, zone.selectionEnd, '    ');
                }
                return;
            }

            if (e.key !== 'Enter' || mod || e.shiftKey || e.altKey
                || zone.selectionStart !== zone.selectionEnd) return;

            // Entrée dans / pour un tableau.
            if (entreeTableau() || ligneEnTableau()) { e.preventDefault(); return; }

            // Entrée : continue la liste (ou la termine si l'élément est vide).
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
            tableau:   ouvrirEditeurTableau,
            maths:     () => insererMaths(''),
            mathsBloc: () => {
                const sel = zone.value.slice(zone.selectionStart, zone.selectionEnd) || 'f(x) = x^2';
                insererBloc('$$\n' + sel + '\n$$', 3, 3 + sel.length);
            },
            annuler, retablir,
            enregistrer: () => auEnregistrement && auEnregistrement(),
        };

        // Menus déroulants (couleurs, maths, code).
        function fermerMenus(sauf) {
            barre.querySelectorAll('.outil-menu.ouvert').forEach((m) => {
                if (m !== sauf) m.classList.remove('ouvert');
            });
        }
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.outil-deroulant')) fermerMenus();
        });

        function bouton(classe, contenu, titre, clic) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = classe;
            b.innerHTML = contenu;
            if (titre) b.title = titre;
            b.addEventListener('click', clic);
            return b;
        }

        function construireMenus() {
            // Couleurs
            const menuCouleurs = barre.querySelector('[data-menu="couleurs"]');
            if (menuCouleurs) {
                COULEURS.forEach(([nom, hex]) => {
                    const b = bouton('pastille', '', nom + '  →  [texte]{' + nom + '}',
                        () => { fermerMenus(); entourer('[', ']{' + nom + '}', 'texte'); });
                    b.style.background = hex;
                    menuCouleurs.appendChild(b);
                });
                menuCouleurs.appendChild(bouton('menu-ligne', '<mark>Surligner</mark> <small>==texte==</small>',
                    '', () => { fermerMenus(); actions.surligner(); }));
            }

            // Maths : recherche + formules + matrice sur mesure + tous les symboles.
            const menuMaths = barre.querySelector('[data-menu="maths"]');
            if (menuMaths) {
                const recherche = document.createElement('input');
                recherche.type = 'search';
                recherche.className = 'maths-recherche';
                recherche.placeholder = 'Chercher : pour tout, intégrale, matrice, lambda…';

                const haut = document.createElement('div');
                haut.className = 'menu-actions';
                haut.append(
                    bouton('menu-ligne', 'Formule dans le texte <small>$…$ (Ctrl+M)</small>', '',
                        () => { fermerMenus(); actions.maths(); }),
                    bouton('menu-ligne', 'Formule centrée <small>$$…$$</small>', '',
                        () => { fermerMenus(); actions.mathsBloc(); }));

                const matrice = document.createElement('div');
                matrice.className = 'maths-matrice';
                matrice.innerHTML = 'Matrice <input type="number" min="1" max="10" value="3" data-m="l"> × '
                    + '<input type="number" min="1" max="10" value="3" data-m="c"> '
                    + '<select data-m="t"><option value="pmatrix">( )</option><option value="bmatrix">[ ]</option>'
                    + '<option value="vmatrix">| | (déterminant)</option></select>';
                matrice.appendChild(bouton('menu-ligne', 'Insérer', '', () => {
                    const l = Math.min(10, Math.max(1, +matrice.querySelector('[data-m="l"]').value || 1));
                    const c = Math.min(10, Math.max(1, +matrice.querySelector('[data-m="c"]').value || 1));
                    const t = matrice.querySelector('[data-m="t"]').value;
                    const rangs = [];
                    for (let i = 1; i <= l; i++) {
                        const r = [];
                        for (let j = 1; j <= c; j++) r.push('‹a_{' + i + j + '}›');
                        rangs.push(r.join(' & '));
                    }
                    fermerMenus();
                    insererMaths('\\begin{' + t + '} ' + rangs.join(' \\\\ ') + ' \\end{' + t + '}');
                }));

                const liste = document.createElement('div');
                liste.className = 'maths-liste';
                MATHS.forEach(({ groupe, items }) => {
                    const h = document.createElement('div');
                    h.className = 'menu-titre';
                    h.textContent = groupe;
                    const grille = document.createElement('div');
                    grille.className = 'grille-symboles';
                    items.forEach(([label, code, desc]) => {
                        const b = bouton('symbole', echapperHtml(label),
                            (desc ? desc + '  —  ' : '') + code.replace(/[‹›]/g, '').trim(),
                            () => insererMaths(code));
                        b.dataset.cherche = normaliser(label + ' ' + code + ' ' + (desc || '') + ' ' + groupe);
                        grille.appendChild(b);
                    });
                    liste.append(h, grille);
                });
                recherche.addEventListener('input', () => {
                    const q = normaliser(recherche.value.trim());
                    liste.querySelectorAll('.grille-symboles').forEach((g) => {
                        let visibles = 0;
                        g.querySelectorAll('.symbole').forEach((b) => {
                            const ok = !q || b.dataset.cherche.includes(q);
                            b.hidden = !ok;
                            if (ok) visibles++;
                        });
                        g.hidden = !visibles;
                        g.previousElementSibling.hidden = !visibles;
                    });
                });
                recherche.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const premier = liste.querySelector('.symbole:not([hidden])');
                        if (premier) premier.click();
                    }
                });
                const aide = document.createElement('p');
                aide.className = 'menu-aide';
                aide.innerHTML = 'Astuce : dans une formule, tape <b>\\</b> puis le début du nom '
                    + '(<code>\\pour</code>, <code>\\int</code>, <code>\\alpha</code>…) : '
                    + 'une liste apparaît. <b>Tab</b> passe au champ suivant.';
                menuMaths.append(recherche, haut, matrice, aide, liste);
            }

            // Blocs de code
            const menuCode = barre.querySelector('[data-menu="code"]');
            if (menuCode) {
                LANGAGES.forEach(([id, nom]) => {
                    menuCode.appendChild(bouton('menu-ligne', echapperHtml(nom), '',
                        () => { fermerMenus(); insererCode(id); }));
                });
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
                const champ = menu.querySelector('input[type="search"]');
                if (champ && menu.classList.contains('ouvert')) champ.focus();
                return;
            }
            if (btn.dataset.action && actions[btn.dataset.action]) actions[btn.dataset.action]();
        });

        // Utilisé par editeur.js (insertion d'une image importée).
        return { insererBloc };
    };
})();
