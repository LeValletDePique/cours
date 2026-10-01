<?php
/**
 * Connexion à la base de données via PDO.
 * Renvoie toujours la MÊME instance PDO (patron singleton) pour éviter
 * d'ouvrir plusieurs connexions par requête.
 *
 * Toutes les requêtes de l'application passent par des requêtes PRÉPARÉES
 * (jamais de concaténation de valeurs), ce qui protège des injections SQL.
 */

function db(): PDO
{
    // Conservée entre les appels grâce au mot-clé static.
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config.php';

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_nom']
    );

    $options = [
        // Lève une exception en cas d'erreur SQL (au lieu d'échouer en silence).
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Les résultats reviennent sous forme de tableaux associatifs.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Vraies requêtes préparées côté serveur (sécurité renforcée).
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
    } catch (PDOException $e) {
        // On n'affiche pas le détail technique à l'utilisateur final.
        http_response_code(500);
        exit('Erreur de connexion à la base de données. '
           . 'Vérifie config.php et que MySQL est démarré.');
    }

    // Même fuseau que PHP pour NOW() / CURRENT_TIMESTAMP (dates des notes),
    // sinon « aujourd'hui » diffère si MySQL tourne en UTC.
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    return $pdo;
}
