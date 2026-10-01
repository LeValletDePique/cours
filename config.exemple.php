<?php
/**
 * Modèle de configuration.
 * Copie ce fichier en "config.php" puis adapte les identifiants.
 * (config.php n'est pas versionné : il contient tes secrets.)
 */

return [
    // --- Connexion à la base de données ---
    'db_host' => '127.0.0.1',   // "localhost" ou "127.0.0.1"
    'db_nom'  => 'cours_db',     // nom de la base créée par schema.sql
    'db_user' => 'root',         // XAMPP/WAMP par défaut : root
    'db_pass' => '',             // XAMPP par défaut : mot de passe vide
    'db_port' => 3306,

    // --- Application ---
    'nom_app'      => 'Mes Cours',
    // Chemin absolu du dossier des fichiers importés (hors web de préférence).
    'dossier_uploads' => __DIR__ . '/uploads',
    // Taille maximale d'un fichier importé (en octets). 20 Mo par défaut.
    'upload_taille_max' => 20 * 1024 * 1024,
    // Fuseau horaire (heures de l'agenda et de l'emploi du temps importé).
    'fuseau' => 'Europe/Paris',
];
