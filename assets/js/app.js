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
