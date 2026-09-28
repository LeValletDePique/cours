<?php
/**
 * Déconnexion : ferme la session puis renvoie vers la page de connexion.
 */
require_once __DIR__ . '/includes/auth.php';

deconnecter();
header('Location: connexion.php');
exit;
