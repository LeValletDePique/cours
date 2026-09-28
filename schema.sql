-- ============================================================
--  Plateforme de prise de notes de cours
--  Schéma de la base de données + données de démo
--  À importer directement dans phpMyAdmin.
-- ============================================================
--  Toutes les tables sont en InnoDB / utf8mb4 (accents, emojis)
--  et reliées par des clés étrangères (ON DELETE CASCADE) pour
--  garder la base cohérente quand on supprime une UE, etc.
-- ============================================================

CREATE DATABASE IF NOT EXISTS cours_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE cours_db;

-- On repart d'une base propre (ordre inverse des dépendances)
DROP TABLE IF EXISTS flashcards;
DROP TABLE IF EXISTS echeances;
DROP TABLE IF EXISTS fichiers;
DROP TABLE IF EXISTS note_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS matieres;
DROP TABLE IF EXISTS ue;
DROP TABLE IF EXISTS utilisateurs;

-- ------------------------------------------------------------
--  UTILISATEURS
-- ------------------------------------------------------------
CREATE TABLE utilisateurs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_utilisateur VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe    VARCHAR(255) NOT NULL,          -- hash password_hash()
    theme           ENUM('clair','sombre') NOT NULL DEFAULT 'clair',
    date_creation      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  UE (Unités d'enseignement)  -- appartiennent à un utilisateur
-- ------------------------------------------------------------
CREATE TABLE ue (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED NOT NULL,
    code            VARCHAR(20)  NOT NULL,           -- "UE1"
    nom             VARCHAR(150) NOT NULL,
    couleur         VARCHAR(7)   NOT NULL DEFAULT '#4f46e5',
    position        INT NOT NULL DEFAULT 0,
    date_creation   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ue_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_ue_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  MATIERES  -- appartiennent à une UE
-- ------------------------------------------------------------
CREATE TABLE matieres (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ue_id         INT UNSIGNED NOT NULL,
    nom           VARCHAR(150) NOT NULL,
    couleur       VARCHAR(7)   NOT NULL DEFAULT '#0891b2',
    position      INT NOT NULL DEFAULT 0,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_matiere_ue FOREIGN KEY (ue_id)
        REFERENCES ue(id) ON DELETE CASCADE,
    INDEX idx_matiere_ue (ue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  NOTES (séances)  -- le cœur de l'application
--  utilisateur_id est dupliqué ici pour filtrer et rechercher
--  vite sans jointure sur toute la hiérarchie.
-- ------------------------------------------------------------
CREATE TABLE notes (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matiere_id        INT UNSIGNED NULL,             -- NULL = note non classée
    utilisateur_id    INT UNSIGNED NOT NULL,
    titre             VARCHAR(255) NOT NULL DEFAULT 'Sans titre',
    contenu           MEDIUMTEXT NULL,               -- Markdown
    epingle           TINYINT(1) NOT NULL DEFAULT 0, -- favori
    supprime          TINYINT(1) NOT NULL DEFAULT 0, -- corbeille (soft delete)
    date_suppression  DATETIME NULL,
    date_creation     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_note_matiere FOREIGN KEY (matiere_id)
        REFERENCES matieres(id) ON DELETE SET NULL,
    CONSTRAINT fk_note_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id) ON DELETE CASCADE,
    INDEX idx_note_utilisateur (utilisateur_id),
    INDEX idx_note_matiere (matiere_id),
    FULLTEXT KEY ft_note_recherche (titre, contenu)  -- recherche plein texte
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  TAGS (étiquettes transversales)  -- propres à l'utilisateur
-- ------------------------------------------------------------
CREATE TABLE tags (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    nom            VARCHAR(50) NOT NULL,
    couleur        VARCHAR(7)  NOT NULL DEFAULT '#64748b',
    CONSTRAINT fk_tag_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tag (utilisateur_id, nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liaison notes <-> tags (plusieurs-à-plusieurs)
CREATE TABLE note_tags (
    note_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (note_id, tag_id),
    CONSTRAINT fk_nt_note FOREIGN KEY (note_id)
        REFERENCES notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_nt_tag FOREIGN KEY (tag_id)
        REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  FICHIERS importés  -- rattachés à une note ou à une matière
-- ------------------------------------------------------------
CREATE TABLE fichiers (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    note_id        INT UNSIGNED NULL,
    matiere_id     INT UNSIGNED NULL,
    nom_original   VARCHAR(255) NOT NULL,
    nom_stocke     VARCHAR(255) NOT NULL,            -- nom aléatoire sur le disque
    type_mime      VARCHAR(100) NOT NULL,
    taille         INT UNSIGNED NOT NULL,            -- octets
    date_upload    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fichier_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_fichier_note FOREIGN KEY (note_id)
        REFERENCES notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_fichier_matiere FOREIGN KEY (matiere_id)
        REFERENCES matieres(id) ON DELETE CASCADE,
    INDEX idx_fichier_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  ECHEANCES (deadlines & rappels)
-- ------------------------------------------------------------
CREATE TABLE echeances (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    matiere_id     INT UNSIGNED NULL,
    titre          VARCHAR(255) NOT NULL,
    description    TEXT NULL,
    type           ENUM('DS','TD','rendu','examen','autre') NOT NULL DEFAULT 'autre',
    date_echeance  DATETIME NOT NULL,
    termine        TINYINT(1) NOT NULL DEFAULT 0,
    date_creation  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_echeance_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_echeance_matiere FOREIGN KEY (matiere_id)
        REFERENCES matieres(id) ON DELETE SET NULL,
    INDEX idx_echeance_utilisateur (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  FLASHCARDS (mode révision, phase 2)
-- ------------------------------------------------------------
CREATE TABLE flashcards (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    note_id        INT UNSIGNED NULL,
    matiere_id     INT UNSIGNED NULL,
    question       TEXT NOT NULL,
    reponse        TEXT NOT NULL,
    date_creation  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_flash_utilisateur FOREIGN KEY (utilisateur_id)
        REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_flash_note FOREIGN KEY (note_id)
        REFERENCES notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_flash_matiere FOREIGN KEY (matiere_id)
        REFERENCES matieres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  DONNÉES DE DÉMO
--  Compte de test :  identifiant = demo   /   mot de passe = demo1234
--  (À supprimer une fois ton vrai compte créé.)
--  Toute nouvelle inscription recrée automatiquement cette même
--  structure d'UE/matières (voir includes/fonctions.php).
-- ============================================================

INSERT INTO utilisateurs (id, nom_utilisateur, email, mot_de_passe, theme) VALUES
(1, 'demo', 'demo@exemple.fr',
 '$2y$12$AWaVZpzdA.5PjU9FCef6IuLdRH5XCezZBBhumfSrDTwTlKJynVxaK', 'clair');

-- Les 4 UE réelles
INSERT INTO ue (id, utilisateur_id, code, nom, couleur, position) VALUES
(1, 1, 'UE1', 'Mise à niveau Maths Info S1',                          '#4f46e5', 1),
(2, 1, 'UE2', 'Langage et données S1',                                '#0891b2', 2),
(3, 1, 'UE3', 'Culture de l''ingénieur S1',                           '#16a34a', 3),
(4, 1, 'UE4', 'Parcours d''engagement et de professionnalisation S1', '#ea580c', 4);

-- Les matières réelles, rattachées à leur UE
INSERT INTO matieres (ue_id, nom, position) VALUES
-- UE1
(1, 'Algorithmique procédurale (MAN 1)',       1),
(1, 'Programmation procédurale (MAN 1)',        2),
(1, 'Développement web côté client (MAN 1)',    3),
-- UE2
(2, 'Commande Unix et Architecture des Ordinateurs', 1),
(2, 'Base de données',                          2),
(2, 'Optimisation linéaire',                    3),
(2, 'Modèles probabilistes',                    4),
(2, 'Data exploration',                         5),
-- UE3
(3, 'TOEIC',                                    1),
(3, 'Communication et expression III',          2),
(3, 'Gestion de l''entreprise I',               3),
(3, 'Éthique sciences et technique',            4),
(3, 'Introduction à l''histoire du Design',     5),
-- UE4
(4, 'Engagement étudiant',                      1),
(4, 'Projet Personnel et Professionnel',        2);

-- Quelques tags transversaux d'exemple
INSERT INTO tags (utilisateur_id, nom, couleur) VALUES
(1, 'à réviser', '#dc2626'),
(1, 'TD',        '#2563eb'),
(1, 'examen',    '#d97706'),
(1, 'important', '#7c3aed');

-- Une note d'exemple (montre code coloré + formule LaTeX)
INSERT INTO notes (matiere_id, utilisateur_id, titre, contenu, epingle) VALUES
(1, 1, 'CM1 – Éléments de base',
'# Algorithmique procédurale

L''algorithmique permet de décomposer un problème complexe en opérations
simples et précises, **indépendamment du langage** de programmation.

## Un exemple de code

```c
int somme(int a, int b) {
    return a + b;
}
```

## Une formule

La complexité moyenne d''un tri rapide est $O(n \\log n)$ :

$$T(n) = 2\\,T\\!\\left(\\frac{n}{2}\\right) + O(n)$$

> Astuce : tu peux mélanger texte, code et maths dans la même note.',
 1);
