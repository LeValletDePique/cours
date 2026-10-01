<?php
/**
 * Gestion des sessions et de l'authentification.
 * À inclure en TOUT PREMIER sur chaque page (avant tout affichage).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fonctions.php';

// Fuseau horaire des dates affichées (agenda, échéances…).
date_default_timezone_set(config_app()['fuseau'] ?? 'Europe/Paris');

// --- Démarrage sécurisé de la session ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,          // le cookie expire à la fermeture du navigateur
        'httponly' => true,       // inaccessible au JavaScript (anti-vol de session)
        'samesite' => 'Lax',      // limite l'envoi du cookie hors du site (anti-CSRF)
        // 'secure' => true,      // à activer si tu passes en HTTPS
    ]);
    session_start();
}

/**
 * Renvoie l'utilisateur connecté (tableau) ou null.
 * Le résultat est mis en cache le temps de la requête.
 */
function utilisateur_connecte(): ?array
{
    static $cache = false;

    if ($cache !== false) {
        return $cache;
    }
    if (empty($_SESSION['utilisateur_id'])) {
        return $cache = null;
    }

    $stmt = db()->prepare(
        'SELECT id, nom_utilisateur, email, theme FROM utilisateurs WHERE id = ?'
    );
    $stmt->execute([$_SESSION['utilisateur_id']]);
    $user = $stmt->fetch();

    return $cache = ($user ?: null);
}

/**
 * Identifiant de l'utilisateur connecté (ou null).
 */
function utilisateur_id(): ?int
{
    return $_SESSION['utilisateur_id'] ?? null;
}

/**
 * Redirige vers la connexion si personne n'est authentifié.
 * À appeler en haut de chaque page privée.
 */
function exiger_connexion(): void
{
    if (!utilisateur_connecte()) {
        header('Location: connexion.php');
        exit;
    }
}

/**
 * Ouvre la session pour un utilisateur (après vérification du mot de passe).
 * On régénère l'ID de session pour éviter la fixation de session.
 */
function connecter(int $utilisateur_id): void
{
    session_regenerate_id(true);
    $_SESSION['utilisateur_id'] = $utilisateur_id;

    $stmt = db()->prepare(
        'UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?'
    );
    $stmt->execute([$utilisateur_id]);
}

/**
 * Ferme la session.
 */
function deconnecter(): void
{
    $_SESSION = [];
    session_destroy();
}

// ============================================================
//  Protection CSRF (jeton anti-falsification de requête)
// ============================================================

/**
 * Renvoie le jeton CSRF de la session (le crée au besoin).
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Champ caché à insérer dans les formulaires.
 */
function champ_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Vérifie le jeton reçu (formulaire POST ou en-tête AJAX X-CSRF).
 * Interrompt la requête si le jeton est absent ou invalide.
 */
function verifier_csrf(): void
{
    $recu = $_POST['csrf']
        ?? $_SERVER['HTTP_X_CSRF']
        ?? '';

    if (!hash_equals(csrf_token(), (string) $recu)) {
        http_response_code(403);
        exit('Jeton de sécurité invalide (CSRF). Recharge la page.');
    }
}
