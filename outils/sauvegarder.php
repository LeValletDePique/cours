<?php
/**
 * Sauvegarde manuelle de la base.
 * Usage (à la racine du projet) : php outils/sauvegarder.php
 */
if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
require __DIR__ . '/../includes/sauvegarde.php';

try {
    $fichier = sauvegarder_base(require __DIR__ . '/../config.php');
    echo "Sauvegarde écrite : $fichier\n";
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
