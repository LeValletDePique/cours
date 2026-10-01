<?php
/**
 * Petites fonctions d'affichage partagées par les pages (design system « mc-* ») :
 * couleur d'UE, noms courts, dates en français, temps relatifs, cours en cours.
 */

const JOURS_FR      = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const JOURS_COURTS  = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
const MOIS_FR       = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
                       'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const MOIS_COURTS   = ['janv', 'févr', 'mars', 'avr', 'mai', 'juin', 'juil', 'août',
                       'sept', 'oct', 'nov', 'déc'];

/**
 * Classe de couleur de chaque UE de l'utilisateur, d'après son rang
 * (ordre des UE) : mc-ue-1 … mc-ue-4, puis mc-ue-autre.
 * Renvoie [ue_id => classe].
 */
function classes_ue(int $uid): array
{
    static $cache = [];
    if (!isset($cache[$uid])) {
        $stmt = db()->prepare('SELECT id FROM ue WHERE utilisateur_id = ? ORDER BY position, id');
        $stmt->execute([$uid]);
        $cache[$uid] = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rang => $id) {
            $cache[$uid][(int) $id] = $rang < 4 ? 'mc-ue-' . ($rang + 1) : 'mc-ue-autre';
        }
    }
    return $cache[$uid];
}

/** Classe de couleur d'une UE (mc-ue-autre si inconnue ou sans UE). */
function classe_ue(int $uid, ?int $ue_id): string
{
    return $ue_id ? (classes_ue($uid)[$ue_id] ?? 'mc-ue-autre') : 'mc-ue-autre';
}

/**
 * Matières de l'utilisateur avec leur UE : [matiere_id => infos].
 * infos : id, nom, court, ue_id, ue_code, ue_nom, classe.
 */
function infos_matieres(int $uid): array
{
    static $cache = [];
    if (!isset($cache[$uid])) {
        $stmt = db()->prepare(
            'SELECT m.id, m.nom, u.id AS ue_id, u.code AS ue_code, u.nom AS ue_nom
               FROM matieres m JOIN ue u ON u.id = m.ue_id
              WHERE u.utilisateur_id = ? ORDER BY u.position, m.position, m.id'
        );
        $stmt->execute([$uid]);
        $cache[$uid] = [];
        foreach ($stmt as $m) {
            $cache[$uid][(int) $m['id']] = [
                'id'      => (int) $m['id'],
                'nom'     => $m['nom'],
                'court'   => nom_court($m['nom']),
                'ue_id'   => (int) $m['ue_id'],
                'ue_code' => $m['ue_code'],
                'ue_nom'  => $m['ue_nom'],
                'classe'  => classe_ue($uid, (int) $m['ue_id']),
            ];
        }
    }
    return $cache[$uid];
}

/** « Algorithmique procédurale (MAN 1) » -> « Algorithmique procédurale ». */
function nom_court(string $nom): string
{
    $court = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $nom));
    return $court !== '' ? $court : $nom;
}

/** « Langage et données S1 » -> « Langage et données » (semestre retiré). */
function nom_court_ue(string $nom): string
{
    return trim(preg_replace('/\s+S\d+$/u', '', $nom));
}

/** « mercredi 30 septembre » (+ l'année si ce n'est pas l'année en cours). */
function date_longue(int $ts): string
{
    $txt = JOURS_FR[(int) date('w', $ts)] . ' ' . (int) date('j', $ts) . ' ' . MOIS_FR[(int) date('n', $ts) - 1];
    return date('Y', $ts) === date('Y') ? $txt : $txt . ' ' . date('Y', $ts);
}

/** « jeu. 2 » (jour court + numéro). */
function date_courte(int $ts): string
{
    return JOURS_COURTS[(int) date('w', $ts)] . ' ' . (int) date('j', $ts);
}

/** Début du jour (minuit) d'un horodatage. */
function debut_jour(int $ts): int
{
    return strtotime(date('Y-m-d 00:00:00', $ts));
}

/** Nombre de jours calendaires entre aujourd'hui et $ts (négatif = passé). */
function ecart_jours(int $ts, ?int $maintenant = null): int
{
    $maintenant ??= time();
    return (int) round((debut_jour($ts) - debut_jour($maintenant)) / 86400);
}

/**
 * Temps relatif pour les listes (« à l'instant », « 12 min », « 2 h », « hier », « 30/09 »).
 */
function temps_relatif(string $datetime): string
{
    $ts = strtotime($datetime);
    $secondes = time() - $ts;
    $jours = ecart_jours($ts);
    if ($jours === 0) {
        if ($secondes < 60) return 'à l\'instant';
        if ($secondes < 3600) return intdiv($secondes, 60) . ' min';
        return intdiv($secondes, 3600) . ' h';
    }
    if ($jours === -1) return 'hier';
    return date('d/m', $ts);
}

/**
 * Délai jusqu'à une date, en toutes lettres : « aujourd'hui », « demain »,
 * « dans 3 jours », « dans 2 semaines », « en retard ».
 */
function delai_texte(int $ts, bool $avec_heure = false): string
{
    if ($ts < time() && ($avec_heure || ecart_jours($ts) < 0)) {
        return 'en retard';
    }
    $j = ecart_jours($ts);
    if ($j === 0) return 'aujourd\'hui';
    if ($j === 1) return 'demain';
    if ($j < 14) return 'dans ' . $j . ' jours';
    return 'dans ' . intdiv($j, 7) . ' semaines';
}

/** Durée en minutes -> « 25 min », « 1 h », « 1 h 18 ». */
function duree_texte(int $minutes): string
{
    $minutes = max(0, $minutes);
    if ($minutes < 60) return $minutes . ' min';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . ' h' . ($m ? ' ' . sprintf('%02d', $m) : '');
}

/** « 1 note », « 3 notes » (accord simple). */
function pluriel(int $n, string $mot, ?string $pluriel = null): string
{
    return $n . ' ' . ($n > 1 ? ($pluriel ?? $mot . 's') : $mot);
}

/**
 * Cours en train de se dérouler d'après l'agenda (ou null).
 * Sert au bouton « Nouvelle note » et à la touche N : la note est alors pré-remplie.
 */
function cours_en_cours(int $uid): ?array
{
    require_once __DIR__ . '/agenda.php';
    installer_agenda();
    $maintenant = date('Y-m-d H:i:s');
    $stmt = db()->prepare(
        "SELECT id, titre, matiere_id FROM evenements
          WHERE utilisateur_id = ? AND type = 'cours' AND journee = 0
            AND debut <= ? AND fin > ?
          ORDER BY debut DESC LIMIT 1"
    );
    $stmt->execute([$uid, $maintenant, $maintenant]);
    return $stmt->fetch() ?: null;
}
