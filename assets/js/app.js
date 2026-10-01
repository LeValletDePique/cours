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
