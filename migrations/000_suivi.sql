-- ============================================================
--  000 — Suivi des migrations
--  Garde la liste des fichiers de migrations déjà appliqués,
--  pour que « php migrations/appliquer.php » ne rejoue jamais
--  deux fois le même fichier.
-- ============================================================

CREATE TABLE IF NOT EXISTS schema_migrations (
    nom              VARCHAR(190) NOT NULL PRIMARY KEY,   -- ex. "001_emploi_du_temps.sql"
    date_application DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
