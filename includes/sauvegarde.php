<?php
/**
 * Sauvegarde complète de la base avec mysqldump (outil en ligne de commande).
 * Utilisée par outils/sauvegarder.php et avant chaque migration.
 */

/**
 * Écrit un fichier sauvegardes/cours_db_AAAA-MM-JJ_HHMMSS.sql.
 * Renvoie le chemin du fichier, ou lève une RuntimeException en cas d'échec.
 */
function sauvegarder_base(array $config): string
{
    $dossier = dirname(__DIR__) . '/sauvegardes';
    if (!is_dir($dossier) && !mkdir($dossier, 0700, true)) {
        throw new RuntimeException("Impossible de créer le dossier $dossier");
    }
    $fichier = $dossier . '/' . $config['db_nom'] . '_' . date('Y-m-d_His') . '.sql';

    // Le mot de passe passe par une variable d'environnement (invisible dans « ps »).
    $commande = sprintf(
        'mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4'
        . ' -h %s -P %d -u %s %s > %s',
        escapeshellarg($config['db_host']),
        (int) $config['db_port'],
        escapeshellarg($config['db_user']),
        escapeshellarg($config['db_nom']),
        escapeshellarg($fichier)
    );
    $env = getenv();
    $env['MYSQL_PWD'] = (string) $config['db_pass'];

    $proc = proc_open(['bash', '-c', $commande], [2 => ['pipe', 'w']], $tubes, null, $env);
    if (!is_resource($proc)) {
        throw new RuntimeException('Impossible de lancer mysqldump.');
    }
    $erreurs = stream_get_contents($tubes[2]);
    fclose($tubes[2]);
    $code = proc_close($proc);

    if ($code !== 0 || !is_file($fichier) || filesize($fichier) === 0) {
        @unlink($fichier);
        throw new RuntimeException('mysqldump a échoué : ' . trim($erreurs));
    }
    return $fichier;
}
