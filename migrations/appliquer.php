<?php
/**
 * Applique les migrations SQL manquantes, dans l'ordre (000, 001, 002…).
 * Usage (à la racine du projet) : php migrations/appliquer.php
 *
 * Sécurités :
 *  - une sauvegarde mysqldump est faite AVANT toute migration
 *    (option --sans-sauvegarde pour s'en passer, déconseillé) ;
 *  - un fichier contenant « DROP TABLE » ou « TRUNCATE » est refusé ;
 *  - chaque fichier appliqué est noté dans schema_migrations
 *    et ne sera jamais rejoué.
 */
if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/sauvegarde.php';

$config = require __DIR__ . '/../config.php';
$pdo    = db();

// Liste des fichiers NNN_nom.sql, triés.
$fichiers = glob(__DIR__ . '/[0-9][0-9][0-9]_*.sql');
sort($fichiers);

// Migrations déjà appliquées (la table n'existe pas encore au tout premier lancement).
$deja = [];
try {
    $deja = $pdo->query('SELECT nom FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Table absente : rien n'a encore été appliqué.
}

$a_faire = array_values(array_filter($fichiers, fn($f) => !in_array(basename($f), $deja, true)));
if (!$a_faire) {
    echo "Base à jour : aucune migration à appliquer.\n";
    exit(0);
}

echo "Migrations à appliquer :\n";
foreach ($a_faire as $f) {
    echo '  - ' . basename($f) . "\n";
}

// Sauvegarde préalable.
if (!in_array('--sans-sauvegarde', $argv, true)) {
    try {
        echo 'Sauvegarde : ' . sauvegarder_base($config) . "\n";
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\nMigration annulée (aucune modification).\n");
        exit(1);
    }
}

foreach ($a_faire as $f) {
    $nom = basename($f);
    $sql = file_get_contents($f);

    // Retire les commentaires « -- … » puis découpe sur les « ; » de fin de ligne.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    if (preg_match('/\b(DROP\s+TABLE|TRUNCATE)\b/i', $sql)) {
        fwrite(STDERR, "$nom refusé : il contient DROP TABLE / TRUNCATE.\n");
        exit(1);
    }
    $requetes = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));

    try {
        foreach ($requetes as $requete) {
            $pdo->exec($requete);
        }
        $pdo->prepare('INSERT INTO schema_migrations (nom) VALUES (?)')->execute([$nom]);
        echo "OK  $nom\n";
    } catch (PDOException $e) {
        // Les CREATE/ALTER de MySQL ne sont pas annulables : on s'arrête net
        // pour pouvoir corriger (la sauvegarde permet de revenir en arrière).
        fwrite(STDERR, "ÉCHEC $nom : " . $e->getMessage() . "\n");
        exit(1);
    }
}
echo "Terminé.\n";
