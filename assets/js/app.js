/* ============================================================
   Script commun à toutes les pages.
   - Bascule du thème clair / sombre (mémorisée dans le navigateur et en base)
   - Petit utilitaire pour envoyer des requêtes AJAX avec le jeton CSRF
   ============================================================ */

// --- Jeton CSRF (lu dans la balise <meta> du header) ---
const CSRF = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') || '';

/**
 * Requête AJAX en JSON, jeton CSRF inclus automatiquement.
 * Renvoie l'objet JSON de la réponse.
 */
async function apiJson(url, methode = 'GET', donnees = null) {
    const options = {
        method: methode,
        headers: { 'X-CSRF': CSRF },
    };
    if (donnees !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(donnees);
    }
    const rep = await fetch(url, options);
    return rep.json();
}

// --- Thème clair / sombre ---
// Sur la page : data-theme="light" | "dark" ; en base : « clair » | « sombre ».
function appliquerTheme(theme) {
    const t = theme === 'dark' || theme === 'sombre' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem('theme', t); } catch (e) {}
    // Mémorise aussi côté serveur (ignore l'erreur si non connecté).
    apiJson('api/preferences.php?action=theme', 'POST', { theme: t === 'dark' ? 'sombre' : 'clair' })
        .catch(() => {});
    return t;
}
function basculerTheme() {
    return appliquerTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
}
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-action="theme"]')) basculerTheme();
});

// --- Icône Lucide en SVG inline (données : window.ICONES, voir includes/icones.php) ---
function icone(nom, classe = '') {
    return '<svg class="lucide ' + classe + '" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"'
        + ' width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        + ' stroke-linecap="round" stroke-linejoin="round">' + ((window.ICONES || {})[nom] || '') + '</svg>';
}

// --- Nouvelle note (bouton « Nouvelle note », touche N, boutons « Prendre des notes ») ---
// Avec un créneau de l'agenda : note pré-remplie « Matière (TD) – jj/mm/aaaa », rangée
// dans sa matière. Sinon : note vide, dans la matière de la page s'il y en a une.
let creationEnCours = false;
async function nouvelleNote(evenementId) {
    if (creationEnCours) return;
    creationEnCours = true;
    const corps = document.body.dataset;
    let cours = evenementId || corps.cours || '';
    // Sur la page d'une autre matière, la note va dans la matière affichée.
    if (!evenementId && cours && corps.matiere && corps.coursMatiere !== corps.matiere) cours = '';
    try {
        const rep = cours
            ? await apiJson('api/agenda.php?action=nouvelle_note', 'POST', { id: parseInt(cours, 10) })
            : await apiJson('api/notes.php?action=creer', 'POST',
                corps.matiere ? { matiere_id: parseInt(corps.matiere, 10) } : {});
        if (rep && rep.id) { location.href = 'note.php?id=' + rep.id; return; }
        alert((rep && rep.erreur) || 'Impossible de créer la note.');
    } catch (e) {
        alert('Impossible de créer la note (connexion perdue ?).');
    }
    creationEnCours = false;
}

// --- Menu latéral sur téléphone ---
function menuMobile(ouvrir) {
    document.body.classList.toggle('menu-ouvert', ouvrir);
    const voile = document.querySelector('.mc-rail-voile');
    if (voile) voile.hidden = !ouvrir;
    document.querySelector('[data-action="menu"]')?.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
    if (ouvrir) document.querySelector('.mc-rail .mc-nav')?.focus();
}

// --- Actions déclenchées par data-action (délégation) ---
document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-action]');
    if (el) {
        const action = el.dataset.action;
        if (action === 'nouvelle-note') { e.preventDefault(); nouvelleNote(el.dataset.evenement); }
        else if (action === 'menu') menuMobile(!document.body.classList.contains('menu-ouvert'));
        else if (action === 'fermer-menu') menuMobile(false);
        return;
    }
    // NoteRow d'une note non classée : « Classer » ouvre la note sur le choix de matière.
    const classer = e.target.closest('[data-classer]');
    if (classer) {
        e.preventDefault();
        location.href = 'note.php?id=' + classer.dataset.classer + '#classer';
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.body.classList.contains('menu-ouvert')) menuMobile(false);
});

// --- Ajout rapide de tâche en langage naturel ---
// « Finir le TD de BDD pour vendredi » -> titre « Finir le TD de BDD », date = vendredi,
// matière reconnue d'après son nom ou ses mots-clés. Utilisé sur l'accueil et dans l'Agenda.
const sansAccents = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
function analyserTache(texte, matieres = []) {
    const ymdLocal = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')
        + '-' + String(d.getDate()).padStart(2, '0');
    const auj = new Date(); auj.setHours(0, 0, 0, 0);
    const plus = (n) => { const d = new Date(auj); d.setDate(d.getDate() + n); return d; };
    const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    const MOIS = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout',
        'septembre', 'octobre', 'novembre', 'decembre'];
    const intro = "(?:pour |avant |d'ici |le |ce |d’ici )?";
    const fin = '(?=$|[\\s,.;!?])';
    const regles = [
        [new RegExp("(?:^|\\s)" + intro + "(?:aujourd'hui|aujourd’hui|ce soir)" + fin, 'i'), () => plus(0)],
        [new RegExp('(?:^|\\s)' + intro + 'apr[eè]s-demain' + fin, 'i'), () => plus(2)],
        [new RegExp('(?:^|\\s)' + intro + 'demain' + fin, 'i'), () => plus(1)],
        [new RegExp('(?:^|\\s)(?:dans|d\'ici|d’ici) (\\d{1,2}) jours?' + fin, 'i'), (m) => plus(parseInt(m[1], 10))],
        [new RegExp('(?:^|\\s)' + intro + '(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)(?: prochain)?' + fin, 'i'),
            (m) => { const c = JOURS.indexOf(m[1].toLowerCase()); return plus(((c - auj.getDay() + 6) % 7) + 1); }],
        [new RegExp('(?:^|\\s)' + intro + '(\\d{1,2})/(\\d{1,2})(?:/(\\d{2,4}))?' + fin, 'i'), (m) => {
            let an = m[3] ? parseInt(m[3], 10) : auj.getFullYear();
            if (an < 100) an += 2000;
            const d = new Date(an, parseInt(m[2], 10) - 1, parseInt(m[1], 10));
            if (!m[3] && d < auj) d.setFullYear(an + 1);
            return d;
        }],
        [new RegExp('(?:^|\\s)' + intro + '(\\d{1,2}) (janvier|f[ée]vrier|mars|avril|mai|juin|juillet|ao[uû]t|septembre|octobre|novembre|d[ée]cembre)' + fin, 'i'), (m) => {
            const d = new Date(auj.getFullYear(), MOIS.indexOf(sansAccents(m[2])), parseInt(m[1], 10));
            if (d < auj) d.setFullYear(d.getFullYear() + 1);
            return d;
        }],
    ];
    let titre = texte, date = '';
    for (const [re, calcul] of regles) {
        const m = titre.match(re);
        if (!m) continue;
        const d = calcul(m);
        if (d && !isNaN(d)) {
            date = ymdLocal(d);
            titre = (titre.slice(0, m.index) + ' ' + titre.slice(m.index + m[0].length));
        }
        break;
    }
    titre = titre.replace(/\s+/g, ' ').replace(/[\s,;:–-]+$/, '').trim() || texte.trim();
    // Matière : le motif le plus long trouvé en mots entiers.
    const cible = ' ' + sansAccents(texte).replace(/[^a-z0-9]+/g, ' ') + ' ';
    let matiere = null, longueur = 0;
    matieres.forEach((m) => (m.motifs || [m.nom]).forEach((motif) => {
        const x = sansAccents(motif).replace(/[^a-z0-9]+/g, ' ').trim();
        if (x.length >= 2 && x.length > longueur && cible.includes(' ' + x + ' ')) { matiere = m; longueur = x.length; }
    }));
    return { titre, date, matiere };
}

// --- Palette de commandes (Ctrl K ou /) : notes, matières et actions ---
const palette = (function () {
    let fond = null, champ, zone, elements = [], choix = 0, minuteur, numero = 0, retourFocus = null;
    const echapper = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const ACTIONS = [
        { libelle: 'Nouvelle note', icone: 'plus', kbd: 'N', faire: () => nouvelleNote() },
        { libelle: 'Ajouter une tâche', icone: 'case-cochee', kbd: 'T', faire: () => allerAjoutTache() },
        { libelle: 'Aujourd\'hui', icone: 'aujourdhui', href: 'index.php' },
        { libelle: 'Matières', icone: 'matieres', href: 'matieres.php' },
        { libelle: 'Agenda', icone: 'agenda', href: 'agenda.php' },
        { libelle: 'Révision', icone: 'revision', href: 'revision.php' },
        { libelle: 'Échéances', icone: 'echeances', href: 'echeances.php' },
        { libelle: 'Favoris', icone: 'favori', href: 'favoris.php' },
        { libelle: 'Corbeille', icone: 'corbeille', href: 'corbeille.php' },
        { libelle: 'Réglages', icone: 'reglages', href: 'reglages.php' },
        { libelle: 'Aide', icone: 'aide', href: 'aide.php' },
        { libelle: 'Changer de thème', icone: 'lune', faire: () => basculerTheme() },
    ];

    // Surligne (<mark>) les mots cherchés, sans tenir compte des accents ni des majuscules.
    function surligner(texte, q) {
        const mots = sansAccents(q).split(/\s+/).filter((m) => m.length >= 2);
        if (!mots.length) return echapper(texte);
        const lettres = [...texte];
        const norm = lettres.map((c) => sansAccents(c));
        const marque = new Array(lettres.length).fill(false);
        const plat = norm.join('');
        // Position dans « plat » -> index de lettre d'origine.
        const index = [];
        norm.forEach((n, i) => { for (let k = 0; k < n.length; k++) index.push(i); });
        mots.forEach((mot) => {
            let pos = plat.indexOf(mot);
            while (pos >= 0) {
                for (let k = pos; k < pos + mot.length; k++) marque[index[k]] = true;
                pos = plat.indexOf(mot, pos + mot.length);
            }
        });
        let html = '', ouvert = false;
        lettres.forEach((c, i) => {
            if (marque[i] && !ouvert) { html += '<mark>'; ouvert = true; }
            if (!marque[i] && ouvert) { html += '</mark>'; ouvert = false; }
            html += echapper(c);
        });
        return html + (ouvert ? '</mark>' : '');
    }

    function ouvrir() {
        if (fond) { champ.focus(); return; }
        retourFocus = document.activeElement;
        fond = document.createElement('div');
        fond.className = 'mc-palette-fond';
        fond.innerHTML = '<div class="mc-palette" role="dialog" aria-modal="true" aria-label="Palette de commandes">'
            + '<label class="mc-palette__input">' + icone('recherche', 'mc-ico-sm')
            + '<input type="text" role="combobox" aria-expanded="true" aria-controls="palette-resultats"'
            + ' aria-autocomplete="list" placeholder="Rechercher une note, une matière, une action…" autocomplete="off" spellcheck="false">'
            + '<span class="mc-kbd">Échap</span></label>'
            + '<div class="mc-palette__resultats" id="palette-resultats" role="listbox" aria-label="Résultats"></div></div>';
        document.body.append(fond);
        champ = fond.querySelector('input');
        zone = fond.querySelector('.mc-palette__resultats');
        fond.addEventListener('mousedown', (e) => { if (e.target === fond) fermer(); });
        champ.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(chercher, 150); });
        champ.addEventListener('keydown', clavier);
        zone.addEventListener('mousemove', (e) => {
            const it = e.target.closest('.mc-palette__item');
            if (it && +it.dataset.i !== choix) selectionner(+it.dataset.i);
        });
        zone.addEventListener('click', (e) => {
            const it = e.target.closest('.mc-palette__item');
            if (it) { e.preventDefault(); activer(+it.dataset.i); }
        });
        champ.focus();
        chercher();
    }

    function fermer() {
        if (!fond) return;
        fond.remove();
        fond = null;
        if (retourFocus && retourFocus.focus) retourFocus.focus();
    }

    async function chercher() {
        const q = champ.value.trim();
        const n = ++numero;
        let rep = { notes: [], matieres: [] };
        try { rep = await (await fetch('api/recherche.php?q=' + encodeURIComponent(q))).json(); } catch (e) {}
        if (n !== numero || !fond) return;   // une frappe plus récente a pris le relais
        const qn = sansAccents(q);
        const actions = ACTIONS.filter((a) => !qn || sansAccents(a.libelle).includes(qn));
        elements = [];
        let html = '';
        const groupe = (titre, lignes) => {
            if (!lignes.length) return;
            html += '<div class="mc-palette__group" role="group" aria-label="' + titre + '">'
                + '<p class="mc-eyebrow mc-palette__label" aria-hidden="true">' + titre + '</p>' + lignes.join('') + '</div>';
        };
        const ligne = (el, contenu, classe = '') => {
            el.i = elements.length;
            elements.push(el);
            return '<a class="mc-palette__item ' + classe + '" role="option" id="palette-' + el.i + '" data-i="' + el.i + '"'
                + ' aria-selected="false" href="' + echapper(el.href || '#') + '">' + contenu + '</a>';
        };
        groupe(q ? 'Notes' : 'Récentes', (rep.notes || []).map((no) => ligne({ href: 'note.php?id=' + no.id },
            '<span class="mc-dot"></span><span class="mc-palette__texte">' + surligner(no.titre, q) + '</span>'
            + '<span class="mc-meta">' + echapper(no.matiere) + '</span>', no.classe)));
        groupe('Matières', (rep.matieres || []).map((m) => ligne({ href: 'matiere.php?id=' + m.id },
            '<span class="mc-dot"></span><span class="mc-palette__texte">' + surligner(m.nom, q) + '</span>'
            + '<span class="mc-meta">' + echapper(m.ue) + '</span>', m.classe)));
        const lignesActions = actions.map((a) => ligne(a, icone(a.icone, 'mc-ico-sm')
            + '<span class="mc-palette__texte">' + surligner(a.libelle, q) + '</span>'
            + (a.kbd ? '<span class="mc-kbd" style="margin-left:auto">' + a.kbd + '</span>' : '')));
        if (q) {
            lignesActions.push(ligne({ href: 'recherche.php?q=' + encodeURIComponent(q) }, icone('recherche', 'mc-ico-sm')
                + '<span class="mc-palette__texte">Rechercher « ' + echapper(q) + ' » dans toutes les notes</span>'));
        }
        groupe('Actions', lignesActions);
        zone.innerHTML = html || '<p class="mc-palette__vide">Aucun résultat. Essaie un autre mot.</p>';
        selectionner(0);
    }

    function selectionner(i) {
        if (!elements.length) { champ.removeAttribute('aria-activedescendant'); return; }
        choix = (i + elements.length) % elements.length;
        zone.querySelectorAll('.mc-palette__item').forEach((it) =>
            it.setAttribute('aria-selected', +it.dataset.i === choix ? 'true' : 'false'));
        const actif = zone.querySelector('#palette-' + choix);
        champ.setAttribute('aria-activedescendant', 'palette-' + choix);
        actif?.scrollIntoView({ block: 'nearest' });
    }

    function activer(i) {
        const el = elements[i];
        if (!el) return;
        fermer();
        if (el.faire) el.faire();
        else location.href = el.href;
    }

    function clavier(e) {
        if (e.key === 'ArrowDown' || (e.key === 'Tab' && !e.shiftKey)) { e.preventDefault(); selectionner(choix + 1); }
        else if (e.key === 'ArrowUp' || (e.key === 'Tab' && e.shiftKey)) { e.preventDefault(); selectionner(choix - 1); }
        else if (e.key === 'Enter') { e.preventDefault(); activer(choix); }
        else if (e.key === 'Escape') { e.preventDefault(); fermer(); }
    }

    return { ouvrir, fermer, estOuverte: () => !!fond };
})();

// --- « T » : champ d'ajout de tâche de la page, sinon celui de l'accueil ---
function allerAjoutTache() {
    const champ = document.querySelector('[data-ajout-tache]');
    if (champ) { champ.focus(); champ.scrollIntoView({ block: 'center' }); }
    else location.href = 'index.php#ajout-tache';
}
if (location.hash === '#ajout-tache') {
    document.querySelector('[data-ajout-tache]')?.focus();
}

// --- Raccourcis clavier (hors champs de saisie) ---
//   N nouvelle note · T ajouter une tâche · Ctrl K ou / palette · Échap ferme
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        palette.ouvrir();
        return;
    }
    if (e.ctrlKey || e.metaKey || e.altKey || e.defaultPrevented) return;
    const cible = e.target;
    if (cible.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(cible.tagName)) return;
    // Une fenêtre ouverte (agenda, tableau, image, palette) garde la main.
    if (palette.estOuverte() || document.querySelector('.modale-fond, .visionneuse')) return;
    if (!document.querySelector('.mc-rail')) return;   // pages sans compte
    if (e.key === '/') { e.preventDefault(); palette.ouvrir(); }
    else if (e.key === 'n' || e.key === 'N') { e.preventDefault(); nouvelleNote(); }
    else if (e.key === 't' || e.key === 'T') { e.preventDefault(); allerAjoutTache(); }
});
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-action="palette"]')) palette.ouvrir();
});
