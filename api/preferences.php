<?php
/**
 * API des préférences utilisateur (pour l'instant : le thème).
 *   ?action=theme  body { theme: 'clair' | 'sombre' }  ('light' / 'dark' acceptés)
 */
require_once __DIR__ . '/../includes/auth.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
verifier_csrf();

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';
$data   = corps_json();

if ($action === 'theme') {
    $theme = in_array($data['theme'] ?? '', ['sombre', 'dark'], true) ? 'sombre' : 'clair';
    $stmt = db()->prepare('UPDATE utilisateurs SET theme = ? WHERE id = ?');
    $stmt->execute([$theme, $uid]);
    repondre_json(['ok' => true, 'theme' => $theme]);
}

repondre_json(['erreur' => 'Action inconnue'], 400);
