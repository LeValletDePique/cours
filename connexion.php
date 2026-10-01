<?php
/**
 * Connexion. Accepte l'identifiant OU l'e-mail.
 * Vérifie le mot de passe avec password_verify (comparaison sûre).
 */
require_once __DIR__ . '/includes/auth.php';

if (utilisateur_connecte()) {
    header('Location: index.php');
    exit;
}

$erreur   = '';
$identifiant = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $identifiant = trim($_POST['identifiant'] ?? '');
    $mdp         = $_POST['mot_de_passe'] ?? '';

    $stmt = db()->prepare(
        'SELECT id, mot_de_passe FROM utilisateurs
         WHERE nom_utilisateur = ? OR email = ?'
    );
    $stmt->execute([$identifiant, $identifiant]);
    $user = $stmt->fetch();

    // Message volontairement générique (ne dit pas ce qui est faux).
    if ($user && password_verify($mdp, $user['mot_de_passe'])) {
        connecter((int) $user['id']);
        header('Location: index.php');
        exit;
    }
    $erreur = 'Identifiant ou mot de passe incorrect.';
}

$titre_page = 'Connexion';
require __DIR__ . '/includes/header.php';
?>
<section class="mc-card" aria-labelledby="titre-connexion">
    <h1 class="mc-title" id="titre-connexion">Connexion</h1>
    <?php if ($erreur): ?>
        <p class="mc-message mc-message--erreur" role="alert"><?= icone('alerte', 'mc-ico-sm') ?><?= e($erreur) ?></p>
    <?php endif; ?>
    <form method="post" action="connexion.php" class="mc-form">
        <?= champ_csrf() ?>
        <label class="mc-label">Identifiant ou e-mail
            <input class="mc-input" type="text" name="identifiant" required autofocus autocomplete="username"
                   value="<?= e($identifiant) ?>">
        </label>
        <label class="mc-label">Mot de passe
            <input class="mc-input" type="password" name="mot_de_passe" required autocomplete="current-password">
        </label>
        <button type="submit" class="mc-btn mc-btn--primary mc-btn--lg mc-btn--plein"><?= icone('connexion', 'mc-ico-sm') ?>Me connecter</button>
    </form>
    <p class="mc-meta">Pas encore de compte ? <a class="mc-link" href="inscription.php">Créer un compte</a></p>
    <p class="mc-message mc-message--info">Démo : identifiant <b>demo</b>, mot de passe <b>demo1234</b>.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
