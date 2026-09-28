/* ============================================================
   Script commun à toutes les pages.
   - Bascule du thème clair / sombre (mémorisée dans le navigateur)
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

// --- Bascule du thème ---
(function () {
    const bouton = document.getElementById('btn-theme');
    if (!bouton) return;

    bouton.addEventListener('click', () => {
        const actuel = document.documentElement.getAttribute('data-theme') === 'sombre'
            ? 'sombre' : 'clair';
        const nouveau = actuel === 'sombre' ? 'clair' : 'sombre';

        document.documentElement.setAttribute('data-theme', nouveau);
        try { localStorage.setItem('theme', nouveau); } catch (e) {}

        // Mémorise aussi côté serveur (ignore l'erreur si non connecté).
        apiJson('api/preferences.php?action=theme', 'POST', { theme: nouveau })
            .catch(() => {});
    });
})();
