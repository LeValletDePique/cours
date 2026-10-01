<?php
/**
 * Inscription d'un nouvel utilisateur.
 * Le mot de passe est haché (password_hash) — jamais stocké en clair.
 */
require_once __DIR__ . '/includes/auth.php';

// Déjà connecté ? On va directement à l'accueil.
if (utilisateur_connecte()) {
    header('Location: index.php');
    exit;
}

$erreurs = [];
$valeurs = ['nom_utilisateur' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    $nom   = trim($_POST['nom_utilisateur'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';
    $mdp2  = $_POST['mot_de_passe_confirm'] ?? '';
    $valeurs = ['nom_utilisateur' => $nom, 'email' => $email];

    // --- Validation ---
    if (mb_strlen($nom) < 3 || mb_strlen($nom) > 50) {
        $erreurs[] = "L'identifiant doit faire entre 3 et 50 caractères.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse e-mail n'est pas valide.";
    }
    if (mb_strlen($mdp) < 8) {
        $erreurs[] = 'Le mot de passe doit faire au moins 8 caractères.';
    }
    if ($mdp !== $mdp2) {
        $erreurs[] = 'Les deux mots de passe ne correspondent pas.';
    }

    // --- Unicité identifiant / e-mail ---
    if (!$erreurs) {
        $stmt = db()->prepare(
            'SELECT 1 FROM utilisateurs WHERE nom_utilisateur = ? OR email = ?'
        );
        $stmt->execute([$nom, $email]);
        if ($stmt->fetch()) {
            $erreurs[] = 'Cet identifiant ou cet e-mail est déjà utilisé.';
        }
    }

    // --- Création du compte ---
    if (!$erreurs) {
        $hash = password_hash($mdp, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            'INSERT INTO utilisateurs (nom_utilisateur, email, mot_de_passe)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$nom, $email, $hash]);
        $id = (int) db()->lastInsertId();

        // On pré-remplit ses UE/matières.
        creer_structure_par_defaut(db(), $id);

        connecter($id);
        header('Location: index.php');
        exit;
    }
}

$titre_page = 'Inscription';
require __DIR__ . '/includes/header.php';
?>
<section class="mc-card" aria-labelledby="titre-inscription">
    <h1 class="mc-title" id="titre-inscription">Créer un compte</h1>
    <p class="mc-meta">Tes UE et matières du semestre sont créées tout de suite.</p>

    <?php foreach ($erreurs as $err): ?>
        <p class="mc-message mc-message--erreur" role="alert"><?= icone('alerte', 'mc-ico-sm') ?><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post" action="inscription.php" class="mc-form">
        <?= champ_csrf() ?>
        <label class="mc-label">Identifiant
            <input class="mc-input" type="text" name="nom_utilisateur" required autocomplete="username"
                   value="<?= e($valeurs['nom_utilisateur']) ?>" autofocus>
        </label>
        <label class="mc-label">E-mail
            <input class="mc-input" type="email" name="email" required autocomplete="email"
                   value="<?= e($valeurs['email']) ?>">
        </label>
        <label class="mc-label">Mot de passe (8 caractères min.)
            <input class="mc-input" type="password" name="mot_de_passe" required minlength="8" autocomplete="new-password">
        </label>
        <label class="mc-label">Confirmer le mot de passe
            <input class="mc-input" type="password" name="mot_de_passe_confirm" required minlength="8" autocomplete="new-password">
        </label>
        <button type="submit" class="mc-btn mc-btn--primary mc-btn--lg mc-btn--plein"><?= icone('personne', 'mc-ico-sm') ?>Créer mon compte</button>
    </form>

    <p class="mc-meta">Déjà un compte ? <a class="mc-link" href="connexion.php">Me connecter</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
