<?php
/**
 * En-tête HTML commun à toutes les pages.
 * Une page doit inclure auth.php, définir $titre_page (optionnel),
 * puis inclure ce fichier.
 */
require_once __DIR__ . '/auth.php';

$config = require __DIR__ . '/../config.php';
$user   = utilisateur_connecte();
$theme  = $user['theme'] ?? 'clair';
$titre  = isset($titre_page) ? $titre_page . ' · ' . $config['nom_app'] : $config['nom_app'];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($titre) ?></title>
    <script>
        // Applique le thème mémorisé côté navigateur AVANT l'affichage
        // (évite un clignotement clair→sombre au chargement).
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t) document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script defer src="assets/js/app.js"></script>
</head>
<body>
<header class="barre">
    <a class="marque" href="index.php">📚 <?= e($config['nom_app']) ?></a>

    <?php if ($user): ?>
        <nav class="nav">
            <a href="index.php">Accueil</a>
            <a href="agenda.php">Agenda</a>
            <a href="recherche.php">Recherche</a>
            <a href="echeances.php">Échéances</a>
            <a href="revision.php">Révision</a>
            <a href="aide.php">Aide</a>
            <a href="corbeille.php">Corbeille</a>
            <a href="reglages.php">Réglages</a>
        </nav>
        <div class="barre-actions">
            <button type="button" id="btn-theme" class="btn-icone"
                    title="Changer de thème" aria-label="Changer de thème">🌓</button>
            <span class="utilisateur"><?= e($user['nom_utilisateur']) ?></span>
            <a class="btn-deco" href="deconnexion.php">Déconnexion</a>
        </div>
    <?php else: ?>
        <div class="barre-actions">
            <button type="button" id="btn-theme" class="btn-icone"
                    title="Changer de thème" aria-label="Changer de thème">🌓</button>
        </div>
    <?php endif; ?>
</header>

<main class="conteneur<?= !empty($page_large) ? ' large' : '' ?>">
