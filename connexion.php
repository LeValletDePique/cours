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
<div class="carte-auth">
    <h1>Connexion</h1>

    <?php if ($erreur): ?>
        <p class="alerte"><?= e($erreur) ?></p>
    <?php endif; ?>

    <form method="post" action="connexion.php" class="formulaire">
        <?= champ_csrf() ?>
        <label>Identifiant ou e-mail
            <input type="text" name="identifiant" required autofocus
                   value="<?= e($identifiant) ?>">
        </label>
        <label>Mot de passe
            <input type="password" name="mot_de_passe" required>
        </label>
        <button type="submit" class="btn-principal">Se connecter</button>
    </form>

    <p class="lien-bas">Pas encore de compte ? <a href="inscription.php">Créer un compte</a></p>
    <p class="astuce">Démo : identifiant <strong>demo</strong> / mot de passe <strong>demo1234</strong></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
