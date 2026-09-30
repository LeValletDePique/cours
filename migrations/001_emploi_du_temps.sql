-- ============================================================
--  001 — Emploi du temps (import / abonnement .ics de l'ENT)
--  Migration additive : aucune table existante n'est modifiée.
-- ============================================================

-- Lien d'abonnement .ics (facultatif) et état de la dernière synchronisation.
CREATE TABLE IF NOT EXISTS edt_sources (
    utilisateur_id   INT UNSIGNED NOT NULL PRIMARY KEY,
    url              VARCHAR(2000) NULL,          -- NULL = import par fichier uniquement
    derniere_synchro DATETIME NULL,               -- dernière tentative réussie
    derniere_erreur  VARCHAR(255) NULL,           -- message de la dernière tentative ratée
    CONSTRAINT fk_edt_source_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les cours, en heure locale (Europe/Paris).
CREATE TABLE IF NOT EXISTS edt_cours (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    debut          DATETIME NOT NULL,
    fin            DATETIME NOT NULL,
    journee        TINYINT(1) NOT NULL DEFAULT 0,   -- événement « toute la journée »
    intitule       VARCHAR(255) NOT NULL,
    lieu           VARCHAR(255) NULL,
    description    TEXT NULL,
    matiere_id     INT UNSIGNED NULL,               -- matière reconnue automatiquement
    CONSTRAINT fk_edt_cours_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_edt_cours_matiere FOREIGN KEY (matiere_id)
        REFERENCES matieres(id) ON DELETE SET NULL,
    INDEX idx_edt_cours_debut (utilisateur_id, debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
