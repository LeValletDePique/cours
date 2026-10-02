<?php
/**
 * En-tête HTML commun à toutes les pages.
 * Une page doit inclure auth.php, définir $titre_page (optionnel),
 * puis inclure ce fichier.
 *
 * Variables facultatives :
 *   $nav_actif      entrée de la barre latérale à mettre en avant
 *                   (déduite du nom du fichier sinon)
 *   $matiere_page   id de la matière affichée : « Nouvelle note » la range dedans
 */
require_once __DIR__ . '/auth.php';

$config = require __DIR__ . '/../config.php';
$user   = utilisateur_connecte();
// En base : « clair » / « sombre » ; sur la page : data-theme="light" / "dark".
$theme  = ($user['theme'] ?? 'clair') === 'sombre' ? 'dark' : 'light';
$titre  = isset($titre_page) ? $titre_page . ' · ' . $config['nom_app'] : $config['nom_app'];

if ($user) {
    $uid_entete = (int) $user['id'];
    $cours_actuel = cours_en_cours($uid_entete);
    $stmt = db()->prepare(
        'SELECT (SELECT COUNT(*) FROM matieres m JOIN ue u ON u.id = m.ue_id WHERE u.utilisateur_id = ?) AS matieres,
                (SELECT COUNT(*) FROM flashcards WHERE utilisateur_id = ?) AS fiches'
    );
    $stmt->execute([$uid_entete, $uid_entete]);
    $compteurs = $stmt->fetch();

    $nav_actif ??= [
        'index' => 'aujourdhui', 'matieres' => 'matieres', 'matiere' => 'matieres',
        'agenda' => 'agenda', 'revision' => 'revision', 'echeances' => 'echeances',
        'favoris' => 'favori', 'corbeille' => 'corbeille', 'reglages' => 'reglages', 'aide' => 'aide',
    ][basename($_SERVER['SCRIPT_NAME'], '.php')] ?? '';

    /** Lien de la barre latérale (mc-nav). */
    $lien_nav = function (string $cle, string $href, string $libelle, ?int $compteur = null) use ($nav_actif): string {
        return '<a class="mc-nav" href="' . e($href) . '"' . ($cle === $nav_actif ? ' aria-current="page"' : '') . '>'
             . icone($cle) . e($libelle)
             . ($compteur ? '<span class="mc-nav__count">' . $compteur . '</span>' : '') . '</a>';
    };
}
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
                // Anciennes valeurs (« clair » / « sombre ») encore acceptées.
                if (t === 'sombre') t = 'dark';
                if (t === 'clair') t = 'light';
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
        // Icônes Lucide pour le JavaScript (même source que includes/icones.php).
        window.ICONES = <?= json_encode(ICONES, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="<?= asset('assets/css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/mes-cours.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
    <script defer src="<?= asset('assets/js/app.js') ?>"></script>
</head>
<?php if ($user): ?>
<body data-cours="<?= $cours_actuel ? (int) $cours_actuel['id'] : '' ?>"
      data-cours-matiere="<?= $cours_actuel ? (int) $cours_actuel['matiere_id'] : '' ?>"
      data-matiere="<?= !empty($matiere_page) ? (int) $matiere_page : '' ?>">
<div class="mc-app">
<nav class="mc-rail" id="rail" aria-label="Navigation">
    <a class="mc-brand" href="index.php"><span class="mc-brand__mark">M</span><?= e($config['nom_app']) ?></a>
    <?= $lien_nav('aujourdhui', 'index.php', 'Aujourd\'hui') ?>
    <?= $lien_nav('matieres', 'matieres.php', 'Matières', (int) $compteurs['matieres']) ?>
    <?= $lien_nav('agenda', 'agenda.php', 'Agenda') ?>
    <?= $lien_nav('revision', 'revision.php', 'Révision', (int) $compteurs['fiches']) ?>
    <?= $lien_nav('echeances', 'echeances.php', 'Échéances') ?>
    <div class="mc-rail__sep"></div>
    <?= $lien_nav('favori', 'favoris.php', 'Favoris') ?>
    <div class="mc-rail__foot">
        <?= $lien_nav('corbeille', 'corbeille.php', 'Corbeille') ?>
        <?= $lien_nav('reglages', 'reglages.php', 'Réglages') ?>
        <?= $lien_nav('aide', 'aide.php', 'Aide') ?>
        <div class="mc-user">
            <span class="mc-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['nom_utilisateur'], 0, 1))) ?></span>
            <span class="mc-user__nom"><?= e($user['nom_utilisateur']) ?></span>
            <a class="mc-btn mc-btn--ghost mc-btn--sm mc-user__deco" href="deconnexion.php"
               aria-label="Me déconnecter" title="Me déconnecter"><?= icone('deconnexion', 'mc-ico-sm') ?></a>
            <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-action="theme"
                    aria-label="Changer de thème" title="Changer de thème"><?= icone('lune', 'mc-ico-sm') ?></button>
        </div>
    </div>
</nav>
<div class="mc-rail-voile" data-action="fermer-menu" hidden></div>
<main class="mc-main" id="contenu">
<div class="mc-topbar">
    <button type="button" class="mc-btn mc-btn--ghost mc-menu" data-action="menu"
            aria-label="Ouvrir le menu" aria-controls="rail" aria-expanded="false"><?= icone('menu') ?></button>
    <button type="button" class="mc-search" data-action="palette" aria-haspopup="dialog"
            aria-keyshortcuts="Control+K /"><?= icone('recherche', 'mc-ico-sm') ?><span class="mc-search__texte">Rechercher une note, une matière…</span><span class="mc-kbd">Ctrl K</span></button>
    <button type="button" class="mc-btn mc-btn--primary" data-action="nouvelle-note" aria-label="Nouvelle note"
            aria-keyshortcuts="N"><?= icone('plus', 'mc-ico-sm') ?><span class="mc-topbar__libelle">Nouvelle note</span><span class="mc-kbd">N</span></button>
</div>
<?php else: ?>
<body>
<main class="mc-auth">
    <a class="mc-brand" href="index.php"><span class="mc-brand__mark">M</span><?= e($config['nom_app']) ?></a>
<?php endif; ?>
