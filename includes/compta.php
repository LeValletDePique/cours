<?php
/**
 * Atelier comptable (Gestion de l'entreprise) : création automatique de la
 * table des dossiers. Un dossier = un exercice (écritures + noms de comptes),
 * stocké en JSON ; tous les calculs (grand livre, balance, compte de
 * résultat, bilan, analyse) sont faits dans le navigateur par
 * assets/js/compta-moteur.js.
 */

/** Taille maximale d'un dossier (JSON), par sécurité. */
const COMPTA_TAILLE_MAX = 2 * 1024 * 1024;

/**
 * Crée la table des dossiers si elle n'existe pas encore : une base importée
 * avant l'ajout de l'atelier fonctionne sans manipulation.
 */
function installer_compta(): void
{
    static $fait = false;
    if ($fait) {
        return;
    }
    $fait = true;
    db()->exec(
        "CREATE TABLE IF NOT EXISTS compta_dossiers (
            id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            utilisateur_id    INT UNSIGNED NOT NULL,
            titre             VARCHAR(255) NOT NULL DEFAULT 'Dossier comptable',
            donnees           MEDIUMTEXT NOT NULL,
            date_creation     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_compta_utilisateur FOREIGN KEY (utilisateur_id)
                REFERENCES utilisateurs(id) ON DELETE CASCADE,
            INDEX idx_compta_utilisateur (utilisateur_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Matière où ranger les notes de comptabilité : celle dont le nom évoque
 * la gestion ou la comptabilité (ex. « Gestion de l'entreprise I »), sinon null.
 */
function matiere_gestion(int $uid): ?int
{
    $stmt = db()->prepare(
        "SELECT m.id FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE u.utilisateur_id = ?
            AND (m.nom LIKE '%compta%' OR m.nom LIKE '%gestion%' OR m.nom LIKE '%finance%')
          ORDER BY (m.nom LIKE '%compta%') DESC, u.position, m.position LIMIT 1"
    );
    $stmt->execute([$uid]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}
