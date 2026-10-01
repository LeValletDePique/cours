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
document.querySelectorAll('[data-action="theme"]').forEach((b) => b.addEventListener('click', basculerTheme));
