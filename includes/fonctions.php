<?php
/**
 * Fonctions utilitaires partagées par toute l'application.
 */

/**
 * Échappe une chaîne pour un affichage HTML sûr (protection anti-XSS).
 * À utiliser sur TOUTE donnée venant de l'utilisateur ou de la base
 * qu'on insère dans du HTML.
 */
function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Renvoie une réponse JSON puis arrête le script.
 * Pratique pour les points d'entrée AJAX (dossier /api).
 */
function repondre_json(array $donnees, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Lit et décode le corps JSON d'une requête (POST AJAX).
 */
function corps_json(): array
{
    $brut = file_get_contents('php://input');
    $data = json_decode($brut, true);
    return is_array($data) ? $data : [];
}

/**
 * Crée la structure d'UE/matières par défaut pour un nouvel utilisateur.
 * Appelée juste après l'inscription : chaque compte démarre avec les
 * mêmes matières que celles du semestre.
 */
function creer_structure_par_defaut(PDO $pdo, int $utilisateur_id): void
{
    // Chaque UE : [code, nom, couleur, [matières...]]
    $structure = [
        ['UE1', 'Mise à niveau Maths Info S1', '#4f46e5', [
            'Algorithmique procédurale (MAN 1)',
            'Programmation procédurale (MAN 1)',
            'Développement web côté client (MAN 1)',
        ]],
        ['UE2', 'Langage et données S1', '#0891b2', [
            'Commande Unix et Architecture des Ordinateurs',
            'Base de données',
            'Optimisation linéaire',
            'Modèles probabilistes',
            'Data exploration',
        ]],
        ['UE3', "Culture de l'ingénieur S1", '#16a34a', [
            'TOEIC',
            'Communication et expression III',
            "Gestion de l'entreprise I",
            'Éthique sciences et technique',
            "Introduction à l'histoire du Design",
        ]],
        ['UE4', "Parcours d'engagement et de professionnalisation S1", '#ea580c', [
            'Engagement étudiant',
            'Projet Personnel et Professionnel',
        ]],
    ];

    $insUe      = $pdo->prepare(
        'INSERT INTO ue (utilisateur_id, code, nom, couleur, position)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insMatiere = $pdo->prepare(
        'INSERT INTO matieres (ue_id, nom, position) VALUES (?, ?, ?)'
    );

    foreach ($structure as $posUe => [$code, $nom, $couleur, $matieres]) {
        $insUe->execute([$utilisateur_id, $code, $nom, $couleur, $posUe + 1]);
        $ueId = (int) $pdo->lastInsertId();

        foreach ($matieres as $posMat => $nomMatiere) {
            $insMatiere->execute([$ueId, $nomMatiere, $posMat + 1]);
        }
    }

    // Quelques tags transversaux prêts à l'emploi.
    $insTag = $pdo->prepare(
        'INSERT INTO tags (utilisateur_id, nom, couleur) VALUES (?, ?, ?)'
    );
    foreach ([['à réviser', '#dc2626'], ['TD', '#2563eb'],
              ['examen', '#d97706'], ['important', '#7c3aed']] as [$t, $c]) {
        $insTag->execute([$utilisateur_id, $t, $c]);
    }
}

/**
 * Renvoie la configuration de l'application (chargée une seule fois).
 */
function config_app(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

/**
 * Nettoie un nom pour servir de nom de fichier téléchargé (sans / \ etc.).
 */
function nom_fichier_sur(string $nom, string $defaut = 'export'): string
{
    $nom = preg_replace('/[^\w\-. ]+/u', '_', $nom);
    $nom = trim($nom);
    return $nom !== '' ? $nom : $defaut;
}

/**
 * Efface du disque les fichiers physiques rattachés à une note
 * (les lignes en base partent ensuite via ON DELETE CASCADE).
 */
function effacer_fichiers_note(PDO $pdo, int $uid, int $note_id): void
{
    $stmt = $pdo->prepare(
        'SELECT nom_stocke FROM fichiers WHERE note_id = ? AND utilisateur_id = ?'
    );
    $stmt->execute([$note_id, $uid]);
    $dossier = config_app()['dossier_uploads'];
    foreach ($stmt as $ligne) {
        $chemin = $dossier . '/' . $ligne['nom_stocke'];
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }
}

/**
 * Transforme une saisie utilisateur en requête FULLTEXT « BOOLEAN MODE ».
 * Chaque mot devient « +mot* » (tous les mots requis, préfixe accepté).
 * Renvoie '' si la saisie ne contient aucun mot exploitable.
 */
function requete_fulltext(string $saisie): string
{
    // On retire les caractères spéciaux du mode booléen pour éviter les erreurs.
    $saisie = preg_replace('/[+\-><\(\)~*\"@]+/', ' ', $saisie);
    $mots = preg_split('/\s+/', trim($saisie), -1, PREG_SPLIT_NO_EMPTY);
    $termes = [];
    foreach ($mots as $mot) {
        if (mb_strlen($mot) >= 2) {          // les mots trop courts sont ignorés
            $termes[] = '+' . $mot . '*';
        }
    }
    return implode(' ', $termes);
}

/**
 * Recherche plein texte (titre + contenu) dans les notes de l'utilisateur,
 * avec filtre facultatif par tag. Sans mot ni tag : les notes les plus récentes.
 * Renvoie id, titre, contenu, matiere_id, epingle, date_modification, matiere.
 */
function rechercher_notes(int $uid, string $q, int $tag = 0, int $limite = 50): array
{
    // Construction dynamique de la requête (valeurs toujours en paramètres liés).
    $where  = ['n.utilisateur_id = ?', 'n.supprime = 0'];
    $params = [$uid];
    $tri    = 'n.date_modification DESC';
    $select_score = '';
    $params_score = [];

    if ($q !== '') {
        $bool = requete_fulltext($q);
        if ($bool !== '') {
            // Recherche plein texte (rapide, avec score de pertinence).
            $where[] = 'MATCH(n.titre, n.contenu) AGAINST(? IN BOOLEAN MODE)';
            $params[] = $bool;
            $tri = 'pertinence DESC, n.date_modification DESC';
            $select_score = ', MATCH(n.titre, n.contenu) AGAINST(? IN BOOLEAN MODE) AS pertinence';
            $params_score = [$bool];
        } else {
            // Mots trop courts : on retombe sur une recherche simple LIKE.
            $where[] = '(n.titre LIKE ? OR n.contenu LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
    }
    if ($tag) {
        $where[] = 'n.id IN (SELECT note_id FROM note_tags WHERE tag_id = ?)';
        $params[] = $tag;
    }

    $sql = 'SELECT n.id, n.titre, n.contenu, n.matiere_id, n.epingle, n.date_modification, m.nom AS matiere'
         . $select_score
         . ' FROM notes n LEFT JOIN matieres m ON m.id = n.matiere_id'
         . ' WHERE ' . implode(' AND ', $where)
         . ' ORDER BY ' . $tri . ' LIMIT ' . max(1, $limite);
    // L'éventuel score se place en tête des paramètres (SELECT avant WHERE).
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge($params_score, $params));
    return $stmt->fetchAll();
}
