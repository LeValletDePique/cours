<?php
/**
 * API JSON de l'agenda.
 *   ?action=lister&debut=AAAA-MM-JJ&fin=AAAA-MM-JJ : événements + échéances + tâches
 *   ?action=creer / maj / supprimer / basculer       : événements perso (réunion, tâche…)
 *   ?action=sources                                  : emplois du temps + mots-clés des matières
 *   ?action=source_ajouter / source_supprimer / synchroniser
 *   ?action=importer (POST multipart, champ « fichier ») : import d'un fichier .ics
 *   ?action=mots_cles                                : mots-clés de rattachement des matières
 *   ?action=notes_seance / nouvelle_note             : lien cours <-> notes
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/agenda.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}

$uid    = utilisateur_id();
$action = $_GET['action'] ?? '';
installer_agenda();

// Les lectures passent en GET ; tout le reste exige le jeton CSRF.
if (!in_array($action, ['lister', 'sources', 'notes_seance'], true)) {
    verifier_csrf();
}
$data = $action === 'importer' ? $_POST : corps_json();

$types_valides = ['cours', 'reunion', 'tache', 'perso', 'autre'];

/** Vrai si la matière appartient à l'utilisateur (ou si aucune matière). */
function matiere_permise(int $uid, ?int $matiere_id): bool
{
    if (!$matiere_id) {
        return true;
    }
    $stmt = db()->prepare(
        'SELECT 1 FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE m.id = ? AND u.utilisateur_id = ?'
    );
    $stmt->execute([$matiere_id, $uid]);
    return (bool) $stmt->fetch();
}

/** « 2026-10-01 » + « 08:30 » -> « 2026-10-01 08:30:00 » (ou null si invalide). */
function date_heure(?string $date, ?string $heure = null): ?string
{
    $date = trim((string) $date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    $heure = trim((string) $heure);
    if ($heure === '') {
        $heure = '00:00';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $heure)) {
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d H:i', "$date $heure");
    return $d ? $d->format('Y-m-d H:i:s') : null;
}

/** Ligne SQL -> objet JSON pour le calendrier. */
function evenement_json(array $e): array
{
    return [
        'id'          => (int) $e['id'],
        'type'        => $e['type'],
        'titre'       => titre_affiche($e),
        'intitule'    => $e['titre'],                     // tel qu'importé (ex. code Celcat)
        'description' => (string) $e['description'],
        // Cours importés : seulement le n° de salle (aussi pour les anciens imports).
        'lieu'        => $e['source_id'] ? nettoyer_salle((string) $e['lieu']) : (string) $e['lieu'],
        'categorie'   => $e['categorie'],                 // CM / TD / autre (cours importés)
        'intervenant' => (string) $e['intervenant'],
        'debut'       => $e['debut'] ? substr($e['debut'], 0, 16) : null,
        'fin'         => $e['fin'] ? substr($e['fin'], 0, 16) : null,
        'journee'     => (bool) $e['journee'],
        'fait'        => (bool) $e['fait'],
        'matiere_id'  => $e['matiere_id'] ? (int) $e['matiere_id'] : null,
        'matiere'     => $e['matiere'],
        'couleur'     => $e['couleur_ue'] ?: $e['couleur_source'],
        'source'      => $e['source'],
    ];
}

/**
 * Titre à afficher : pour un cours importé relié à une matière, le nom de la
 * matière (sans « (MAN 1) ») ; Celcat ne donne souvent qu'un code (DIDB1BDD).
 */
function titre_affiche(array $e): string
{
    if ($e['source_id'] && $e['type'] === 'cours' && $e['matiere']) {
        return trim(preg_replace('/\s*\(.*?\)\s*/', ' ', $e['matiere']));
    }
    return $e['titre'];
}

const SELECT_EVENEMENTS =
    'SELECT e.*, m.nom AS matiere, u.couleur AS couleur_ue,
            s.nom AS source, s.couleur AS couleur_source
       FROM evenements e
       LEFT JOIN matieres m ON m.id = e.matiere_id
       LEFT JOIN ue u ON u.id = m.ue_id
       LEFT JOIN agenda_sources s ON s.id = e.source_id';

switch ($action) {

    // ---------------------------------------------------------
    case 'lister':
        $debut = date_heure($_GET['debut'] ?? '');
        $fin   = date_heure($_GET['fin'] ?? '');
        if (!$debut || !$fin) {
            repondre_json(['erreur' => 'Période invalide'], 400);
        }

        // Tout ce qui chevauche la période [debut, fin[.
        $stmt = db()->prepare(SELECT_EVENEMENTS . '
            WHERE e.utilisateur_id = ? AND e.debut IS NOT NULL
              AND e.debut < ? AND (e.fin > ? OR e.debut >= ?)
            ORDER BY e.debut'
        );
        $stmt->execute([$uid, $fin, $debut, $debut]);
        $evenements = array_map('evenement_json', $stmt->fetchAll());

        $stmt = db()->prepare(
            'SELECT e.id, e.titre, e.type, e.date_echeance, e.termine, e.description,
                    m.nom AS matiere
               FROM echeances e LEFT JOIN matieres m ON m.id = e.matiere_id
              WHERE e.utilisateur_id = ? AND e.date_echeance >= ? AND e.date_echeance < ?
              ORDER BY e.date_echeance'
        );
        $stmt->execute([$uid, $debut, $fin]);
        $echeances = array_map(fn($e) => [
            'id'          => (int) $e['id'],
            'titre'       => $e['titre'],
            'categorie'   => $e['type'],
            'debut'       => substr($e['date_echeance'], 0, 16),
            'fait'        => (bool) $e['termine'],
            'matiere'     => $e['matiere'],
            'description' => (string) $e['description'],
        ], $stmt->fetchAll());

        // Tâches à faire (toutes, pas seulement celles de la période).
        $stmt = db()->prepare(SELECT_EVENEMENTS . "
            WHERE e.utilisateur_id = ? AND e.type = 'tache'
              AND (e.fait = 0 OR e.debut >= ?)
            ORDER BY e.fait, e.debut IS NULL, e.debut, e.id
            LIMIT 200"
        );
        $stmt->execute([$uid, date('Y-m-d 00:00:00', strtotime('-7 days'))]);
        $taches = array_map('evenement_json', $stmt->fetchAll());

        repondre_json([
            'evenements' => $evenements,
            'echeances'  => $echeances,
            'taches'     => $taches,
        ]);
        break;

    // ---------------------------------------------------------
    case 'creer':
    case 'maj':
        $titre = trim($data['titre'] ?? '');
        $type  = in_array($data['type'] ?? '', $types_valides, true) ? $data['type'] : 'autre';
        $journee = !empty($data['journee']);
        $matiere_id = (int) ($data['matiere_id'] ?? 0) ?: null;

        if ($titre === '') {
            repondre_json(['erreur' => 'Le titre est obligatoire.'], 400);
        }
        if (!matiere_permise($uid, $matiere_id)) {
            repondre_json(['erreur' => 'Matière inconnue'], 403);
        }

        $date = trim($data['date'] ?? '');
        $heure_debut = trim($data['heure_debut'] ?? '');
        $heure_fin   = trim($data['heure_fin'] ?? '');
        if ($type === 'tache' && $heure_debut === '') {
            $journee = true;                            // tâche « pour tel jour »
        } elseif (!$journee && $heure_debut === '') {
            repondre_json(['erreur' => 'Indique l\'heure de début (ou coche « Journée entière »).'], 400);
        }
        if ($date === '' && $type === 'tache') {
            $debut = $fin = null;                       // tâche sans date
        } else {
            $debut = date_heure($date, $journee ? '' : $heure_debut);
            if (!$debut) {
                repondre_json(['erreur' => 'Date ou heure invalide.'], 400);
            }
            if ($journee) {
                $date_fin = trim($data['date_fin'] ?? '') ?: $date;
                $fin = date_heure($date_fin);
                // Fin exclusive : le lendemain du dernier jour, à minuit.
                $fin = $fin ? date('Y-m-d H:i:s', strtotime($fin . ' +1 day')) : null;
            } elseif ($heure_fin === '') {
                // Sans heure de fin : tâche ponctuelle, sinon 1 h par défaut.
                $fin = $type === 'tache' ? $debut : date('Y-m-d H:i:s', strtotime($debut . ' +1 hour'));
            } else {
                $fin = date_heure($date, $heure_fin);
            }
            if (!$fin || $fin < $debut) {
                repondre_json(['erreur' => 'La fin doit être après le début.'], 400);
            }
        }

        $valeurs = [$matiere_id, $type, mb_substr($titre, 0, 255),
                    trim($data['description'] ?? ''), mb_substr(trim($data['lieu'] ?? ''), 0, 255),
                    $debut, $fin, $journee ? 1 : 0];

        if ($action === 'creer') {
            $stmt = db()->prepare(
                'INSERT INTO evenements
                    (utilisateur_id, matiere_id, type, titre, description, lieu, debut, fin, journee)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(array_merge([$uid], $valeurs));
            repondre_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
        }

        // Les cours importés sont recréés à chaque synchro : non modifiables.
        $stmt = db()->prepare(
            'UPDATE evenements
                SET matiere_id = ?, type = ?, titre = ?, description = ?, lieu = ?,
                    debut = ?, fin = ?, journee = ?
              WHERE id = ? AND utilisateur_id = ? AND source_id IS NULL'
        );
        $stmt->execute(array_merge($valeurs, [(int) ($data['id'] ?? 0), $uid]));
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    case 'basculer':   // tâche faite / à faire
        $stmt = db()->prepare(
            'UPDATE evenements SET fait = 1 - fait WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([(int) ($data['id'] ?? 0), $uid]);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    case 'supprimer':
        $stmt = db()->prepare(
            'DELETE FROM evenements WHERE id = ? AND utilisateur_id = ? AND source_id IS NULL'
        );
        $stmt->execute([(int) ($data['id'] ?? 0), $uid]);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    case 'sources':
        $stmt = db()->prepare(
            'SELECT id, nom, url, couleur, derniere_synchro, dernier_message, nb_evenements
               FROM agenda_sources WHERE utilisateur_id = ? ORDER BY id'
        );
        $stmt->execute([$uid]);
        $sources = array_map(fn($s) => [
            'id'        => (int) $s['id'],
            'nom'       => $s['nom'],
            'url'       => (string) $s['url'],
            'couleur'   => $s['couleur'],
            'synchro'   => $s['derniere_synchro']
                ? date('d/m/Y à H:i', strtotime($s['derniere_synchro'])) : null,
            'a_synchroniser' => $s['url'] && (!$s['derniere_synchro']
                || time() - strtotime($s['derniere_synchro']) > AGENDA_SYNCHRO_AUTO),
            'message'   => $s['dernier_message'],
            'nb'        => (int) $s['nb_evenements'],
        ], $stmt->fetchAll());

        // Matières + nombre de cours reconnus (pour régler les mots-clés).
        $stmt = db()->prepare(
            'SELECT m.id, m.nom, m.mots_cles, u.code AS ue_code,
                    (SELECT COUNT(*) FROM evenements e
                      WHERE e.matiere_id = m.id AND e.source_id IS NOT NULL) AS nb_cours
               FROM matieres m JOIN ue u ON u.id = m.ue_id
              WHERE u.utilisateur_id = ? ORDER BY u.position, m.position'
        );
        $stmt->execute([$uid]);
        $matieres = array_map(fn($m) => [
            'id' => (int) $m['id'], 'nom' => $m['nom'], 'ue' => $m['ue_code'],
            'mots_cles' => (string) $m['mots_cles'], 'nb_cours' => (int) $m['nb_cours'],
        ], $stmt->fetchAll());

        // Intitulés de cours qui ne correspondent à aucune matière.
        $stmt = db()->prepare(
            "SELECT titre, COUNT(*) AS nb FROM evenements
              WHERE utilisateur_id = ? AND source_id IS NOT NULL AND matiere_id IS NULL
                AND type = 'cours'
              GROUP BY titre ORDER BY nb DESC LIMIT 30"
        );
        $stmt->execute([$uid]);
        $non_reconnus = $stmt->fetchAll();
        // Matière devinée d'après le code (ex. DIDB1BDD -> Base de données), à confirmer.
        $suggestions = suggerer_matieres(array_column($non_reconnus, 'titre'), $matieres);
        foreach ($non_reconnus as &$n) {
            $n['nb'] = (int) $n['nb'];
            $n['suggestion'] = $suggestions[$n['titre']] ?? null;
        }
        unset($n);

        repondre_json(['sources' => $sources, 'matieres' => $matieres,
                       'non_reconnus' => $non_reconnus]);
        break;

    // ---------------------------------------------------------
    case 'source_ajouter':
        $nom = trim($data['nom'] ?? '') ?: 'Emploi du temps';
        $url = trim($data['url'] ?? '');
        $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', $data['couleur'] ?? '') ? $data['couleur'] : '#0891b2';
        try {
            $ics = ics_telecharger($url);   // vérifie le lien avant de l'enregistrer
        } catch (RuntimeException $e) {
            repondre_json(['erreur' => $e->getMessage()], 400);
        }
        $stmt = db()->prepare(
            'INSERT INTO agenda_sources (utilisateur_id, nom, url, couleur) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$uid, mb_substr($nom, 0, 100), mb_substr($url, 0, 1000), $couleur]);
        $id = (int) db()->lastInsertId();
        $nb = synchroniser_source($uid, $id, $ics);
        repondre_json(['ok' => true, 'id' => $id, 'nb' => $nb]);
        break;

    // ---------------------------------------------------------
    case 'synchroniser':
        $stmt = db()->prepare(
            'SELECT id, url FROM agenda_sources WHERE id = ? AND utilisateur_id = ?'
        );
        $stmt->execute([(int) ($data['id'] ?? 0), $uid]);
        $source = $stmt->fetch();
        if (!$source || !$source['url']) {
            repondre_json(['erreur' => 'Source introuvable (ou importée depuis un fichier).'], 404);
        }
        try {
            $nb = synchroniser_source($uid, (int) $source['id'], ics_telecharger($source['url']));
            repondre_json(['ok' => true, 'nb' => $nb]);
        } catch (RuntimeException $e) {
            // On garde les cours déjà importés et on mémorise l'erreur.
            db()->prepare(
                'UPDATE agenda_sources SET dernier_message = ?, derniere_synchro = ? WHERE id = ?'
            )->execute([mb_substr($e->getMessage(), 0, 255), date('Y-m-d H:i:s'), $source['id']]);
            repondre_json(['erreur' => $e->getMessage()], 400);
        }
        break;

    // ---------------------------------------------------------
    case 'importer':   // fichier .ics (emploi du temps accessible seulement connecté)
        $f = $_FILES['fichier'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            repondre_json(['erreur' => 'Aucun fichier reçu.'], 400);
        }
        if ($f['size'] > 10 * 1024 * 1024) {
            repondre_json(['erreur' => 'Fichier trop lourd (10 Mo max).'], 400);
        }
        $ics = file_get_contents($f['tmp_name']);
        if (stripos($ics, 'BEGIN:VCALENDAR') === false) {
            repondre_json(['erreur' => 'Ce fichier n\'est pas un calendrier iCal (.ics).'], 400);
        }
        // Réimporter un fichier du même nom remplace l'import précédent.
        $nom = mb_substr(trim($data['nom'] ?? '') ?: pathinfo($f['name'], PATHINFO_FILENAME), 0, 100);
        $stmt = db()->prepare(
            'SELECT id FROM agenda_sources WHERE utilisateur_id = ? AND url IS NULL AND nom = ?'
        );
        $stmt->execute([$uid, $nom]);
        $id = (int) $stmt->fetchColumn();
        if (!$id) {
            db()->prepare('INSERT INTO agenda_sources (utilisateur_id, nom) VALUES (?, ?)')
                ->execute([$uid, $nom]);
            $id = (int) db()->lastInsertId();
        }
        $nb = synchroniser_source($uid, $id, $ics);
        repondre_json(['ok' => true, 'id' => $id, 'nb' => $nb]);
        break;

    // ---------------------------------------------------------
    case 'source_supprimer':   // supprime aussi ses cours (ON DELETE CASCADE)
        $stmt = db()->prepare('DELETE FROM agenda_sources WHERE id = ? AND utilisateur_id = ?');
        $stmt->execute([(int) ($data['id'] ?? 0), $uid]);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    case 'mots_cles':   // { mots_cles: { id_matiere: "bdd, sgbd", … } }
        $maj = db()->prepare(
            'UPDATE matieres m JOIN ue u ON u.id = m.ue_id
                SET m.mots_cles = ?
              WHERE m.id = ? AND u.utilisateur_id = ?'
        );
        foreach ((array) ($data['mots_cles'] ?? []) as $id => $mots) {
            $mots = mb_substr(trim((string) $mots), 0, 255);
            $maj->execute([$mots === '' ? null : $mots, (int) $id, $uid]);
        }
        associer_matieres($uid);
        repondre_json(['ok' => true]);
        break;

    // ---------------------------------------------------------
    case 'notes_seance':   // notes de la matière créées le jour du cours
        $matiere_id = (int) ($_GET['matiere_id'] ?? 0);
        $jour = date_heure($_GET['date'] ?? '');
        if (!$matiere_id || !$jour) {
            repondre_json(['notes' => []]);
        }
        $stmt = db()->prepare(
            'SELECT id, titre FROM notes
              WHERE utilisateur_id = ? AND matiere_id = ? AND supprime = 0
                AND date_creation >= ? AND date_creation < ?
              ORDER BY date_creation'
        );
        $stmt->execute([$uid, $matiere_id, $jour, date('Y-m-d H:i:s', strtotime($jour . ' +1 day'))]);
        repondre_json(['notes' => $stmt->fetchAll()]);
        break;

    // ---------------------------------------------------------
    case 'nouvelle_note':   // note pré-remplie pour un cours, dans sa matière
        $stmt = db()->prepare(SELECT_EVENEMENTS . ' WHERE e.id = ? AND e.utilisateur_id = ?');
        $stmt->execute([(int) ($data['id'] ?? 0), $uid]);
        $ev = $stmt->fetch();
        if (!$ev || !$ev['debut']) {
            repondre_json(['erreur' => 'Événement introuvable'], 404);
        }
        $jour = date('d/m/Y', strtotime($ev['debut']));
        $type_cours = in_array($ev['categorie'], ['CM', 'TD'], true) ? ' (' . $ev['categorie'] . ')' : '';
        $titre = mb_substr(titre_affiche($ev) . $type_cours . ' – ' . $jour, 0, 255);
        // « 28/09/2026, 08:30–10:00 · M. Dupont · CM · Amphi 1 »
        $infos = $jour . ($ev['journee'] ? '' : ', ' . date('H:i', strtotime($ev['debut']))
                 . '–' . date('H:i', strtotime($ev['fin'])));
        $salle = $ev['source_id'] ? nettoyer_salle((string) $ev['lieu']) : $ev['lieu'];
        foreach ([$ev['intervenant'], $type_cours ? $ev['categorie'] : '', $salle] as $info) {
            if ($info) $infos .= ' · ' . $info;
        }
        $contenu = '# ' . titre_affiche($ev) . "\n\n*" . $infos . "*\n\n";
        $stmt = db()->prepare(
            'INSERT INTO notes (matiere_id, utilisateur_id, titre, contenu) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$ev['matiere_id'], $uid, $titre, $contenu]);
        repondre_json(['ok' => true, 'id' => (int) db()->lastInsertId()]);
        break;

    // ---------------------------------------------------------
    default:
        repondre_json(['erreur' => 'Action inconnue'], 400);
}
