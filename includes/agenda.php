<?php
/**
 * Agenda : création automatique des tables, lecture des calendriers iCal
 * (.ics) et synchronisation de l'emploi du temps.
 *
 * Un emploi du temps (Celcat, HyperPlanning, ADE, Google Agenda, Outlook…)
 * se branche via son lien iCal : chaque synchronisation remplace les cours
 * de cette source, puis chaque cours est rattaché à une matière grâce aux
 * mots-clés (voir associer_matieres()).
 */

/** Durée après laquelle une source est re-synchronisée automatiquement. */
const AGENDA_SYNCHRO_AUTO = 6 * 3600;

/**
 * Crée les tables de l'agenda si elles n'existent pas encore : une base
 * importée avant l'ajout de l'agenda fonctionne sans manipulation.
 */
function installer_agenda(): void
{
    static $fait = false;
    if ($fait) {
        return;
    }
    $fait = true;
    $pdo = db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS agenda_sources (
            id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            utilisateur_id   INT UNSIGNED NOT NULL,
            nom              VARCHAR(100)  NOT NULL,
            url              VARCHAR(1000) NULL,
            couleur          VARCHAR(7)    NOT NULL DEFAULT '#0891b2',
            derniere_synchro DATETIME NULL,
            dernier_message  VARCHAR(255) NULL,
            nb_evenements    INT UNSIGNED NOT NULL DEFAULT 0,
            date_creation    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_source_utilisateur FOREIGN KEY (utilisateur_id)
                REFERENCES utilisateurs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS evenements (
            id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            utilisateur_id INT UNSIGNED NOT NULL,
            source_id      INT UNSIGNED NULL,
            matiere_id     INT UNSIGNED NULL,
            type           ENUM('cours','reunion','tache','perso','autre') NOT NULL DEFAULT 'autre',
            titre          VARCHAR(255) NOT NULL,
            description    TEXT NULL,
            lieu           VARCHAR(255) NULL,
            debut          DATETIME NULL,
            fin            DATETIME NULL,
            journee        TINYINT(1) NOT NULL DEFAULT 0,
            fait           TINYINT(1) NOT NULL DEFAULT 0,
            date_creation  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_evt_utilisateur FOREIGN KEY (utilisateur_id)
                REFERENCES utilisateurs(id) ON DELETE CASCADE,
            CONSTRAINT fk_evt_source FOREIGN KEY (source_id)
                REFERENCES agenda_sources(id) ON DELETE CASCADE,
            CONSTRAINT fk_evt_matiere FOREIGN KEY (matiere_id)
                REFERENCES matieres(id) ON DELETE SET NULL,
            INDEX idx_evt_utilisateur_debut (utilisateur_id, debut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $existe = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matieres'
            AND COLUMN_NAME = 'mots_cles'"
    )->fetchColumn();
    if (!$existe) {
        $pdo->exec('ALTER TABLE matieres ADD mots_cles VARCHAR(255) NULL');
    }
}

// ============================================================
//  Lecture d'un fichier iCal (RFC 5545, l'essentiel)
// ============================================================

/** Texte iCal -> texte normal (\n, \, \; \\). */
function ics_texte(string $v): string
{
    return strtr($v, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\\;' => ';', '\\\\' => '\\']);
}

/**
 * Lit une date iCal. Renvoie [DateTimeImmutable, journée entière ?] ou null.
 *   20261001            (journée entière)
 *   20261001T080000Z    (UTC)
 *   20261001T080000     (heure locale, ou fuseau TZID)
 */
function ics_date(string $valeur, array $params): ?array
{
    $valeur = trim($valeur);
    $local = new DateTimeZone(date_default_timezone_get());

    if (preg_match('/^(\d{8})$/', $valeur, $m)) {
        $d = DateTimeImmutable::createFromFormat('!Ymd', $m[1], $local);
        return $d ? [$d, true] : null;
    }
    if (!preg_match('/^(\d{8}T\d{6})(Z?)$/', $valeur, $m)) {
        return null;
    }
    if ($m[2] === 'Z') {
        $fuseau = new DateTimeZone('UTC');
    } else {
        $fuseau = $local;
        if (!empty($params['TZID'])) {
            try {
                $fuseau = new DateTimeZone(trim($params['TZID'], '"/'));
            } catch (Exception $e) {
                // Fuseau inconnu (ex. noms Windows d'Outlook) : heure locale.
            }
        }
    }
    $d = DateTimeImmutable::createFromFormat('Ymd\THis', $m[1], $fuseau);
    return $d ? [$d, false] : null;
}

/**
 * Découpe un calendrier en événements :
 * [uid, titre, description, lieu, debut, fin, journee, rrule, exdates, recurrence_id]
 */
function ics_lire(string $ics): array
{
    // Les lignes longues sont « pliées » : retour à la ligne + espace.
    $ics = preg_replace("/\r?\n[ \t]/", '', $ics);
    $evenements = [];
    $ev = null;
    $profondeur = 0;   // composants imbriqués (VALARM…) ignorés

    foreach (preg_split("/\r?\n/", $ics) as $ligne) {
        if ($ligne === '') {
            continue;
        }
        if (strcasecmp($ligne, 'BEGIN:VEVENT') === 0) {
            $ev = ['uid' => '', 'titre' => '', 'description' => '', 'lieu' => '',
                   'debut' => null, 'fin' => null, 'journee' => false, 'duree' => null,
                   'rrule' => null, 'exdates' => [], 'recurrence_id' => null,
                   'annule' => false];
            $profondeur = 0;
            continue;
        }
        if ($ev === null) {
            continue;
        }
        if (stripos($ligne, 'BEGIN:') === 0) { $profondeur++; continue; }
        if (stripos($ligne, 'END:') === 0) {
            if ($profondeur > 0) { $profondeur--; continue; }
            if ($ev['debut'] && !$ev['annule']) {
                $evenements[] = ics_finaliser($ev);
            }
            $ev = null;
            continue;
        }
        if ($profondeur > 0) {
            continue;
        }

        // NOM;PARAM=val;PARAM2="v:al":VALEUR
        if (!preg_match('/^([A-Za-z0-9-]+)((?:;[^:;"]+=(?:"[^"]*"|[^:;"]*))*):(.*)$/s', $ligne, $m)) {
            continue;
        }
        $nom = strtoupper($m[1]);
        $params = [];
        if ($m[2] !== '' && preg_match_all('/;([^=;]+)=("[^"]*"|[^;]*)/', $m[2], $pp, PREG_SET_ORDER)) {
            foreach ($pp as $p) {
                $params[strtoupper($p[1])] = trim($p[2], '"');
            }
        }
        $valeur = $m[3];

        switch ($nom) {
            case 'UID':         $ev['uid'] = $valeur; break;
            case 'SUMMARY':     $ev['titre'] = ics_texte($valeur); break;
            case 'DESCRIPTION': $ev['description'] = ics_texte($valeur); break;
            case 'LOCATION':    $ev['lieu'] = ics_texte($valeur); break;
            case 'RRULE':       $ev['rrule'] = $valeur; break;
            case 'DURATION':    $ev['duree'] = $valeur; break;
            case 'STATUS':      $ev['annule'] = strtoupper(trim($valeur)) === 'CANCELLED'; break;
            case 'DTSTART':
                if ($d = ics_date($valeur, $params)) { [$ev['debut'], $ev['journee']] = $d; }
                break;
            case 'DTEND':
                if ($d = ics_date($valeur, $params)) { $ev['fin'] = $d[0]; }
                break;
            case 'EXDATE':
                foreach (explode(',', $valeur) as $v) {
                    if ($d = ics_date($v, $params)) { $ev['exdates'][] = $d[0]; }
                }
                break;
            case 'RECURRENCE-ID':
                if ($d = ics_date($valeur, $params)) { $ev['recurrence_id'] = $d[0]; }
                break;
        }
    }
    return $evenements;
}

/** Complète la fin d'un événement (DTEND absent -> DURATION ou valeur par défaut). */
function ics_finaliser(array $ev): array
{
    if (!$ev['fin']) {
        $fin = null;
        if ($ev['duree']) {
            try {
                $fin = $ev['debut']->add(new DateInterval(ltrim($ev['duree'], '+')));
            } catch (Exception $e) {
            }
        }
        $ev['fin'] = $fin ?? ($ev['journee'] ? $ev['debut']->modify('+1 day') : $ev['debut']);
    }
    unset($ev['duree'], $ev['annule']);
    return $ev;
}

/** Clé de comparaison d'un instant (indépendante du fuseau). */
function ics_cle(DateTimeImmutable $d): string
{
    return (string) $d->getTimestamp();
}

/**
 * Dates de début des occurrences d'un événement répété (RRULE), dans la
 * fenêtre [$de, $a]. Gère FREQ DAILY / WEEKLY (+BYDAY) / MONTHLY / YEARLY,
 * INTERVAL, COUNT, UNTIL.
 */
function ics_occurrences(array $ev, DateTimeImmutable $de, DateTimeImmutable $a): array
{
    $regle = [];
    foreach (explode(';', $ev['rrule']) as $morceau) {
        [$k, $v] = array_pad(explode('=', $morceau, 2), 2, '');
        $regle[strtoupper($k)] = strtoupper($v);
    }
    $freq = $regle['FREQ'] ?? '';
    $pas = max(1, (int) ($regle['INTERVAL'] ?? 1));
    $max = isset($regle['COUNT']) ? (int) $regle['COUNT'] : PHP_INT_MAX;
    $jusqua = null;
    if (!empty($regle['UNTIL'])) {
        $u = ics_date($regle['UNTIL'], []);
        // Une date seule = jusqu'à la fin de ce jour.
        $jusqua = $u ? ($u[1] ? $u[0]->modify('+1 day -1 second') : $u[0]) : null;
    }
    $debut = $ev['debut'];
    $limite = $jusqua && $jusqua < $a ? $jusqua : $a;

    $resultats = [];
    $compte = 0;
    $ajouter = function (DateTimeImmutable $d) use (&$resultats, &$compte, $de, $debut) {
        if ($d < $debut) {
            return;
        }
        $compte++;
        if ($d >= $de) {
            $resultats[] = $d;
        }
    };

    if ($freq === 'WEEKLY') {
        $codes = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
        $jours = [];
        foreach (explode(',', $regle['BYDAY'] ?? '') as $j) {
            $j = preg_replace('/^[+-]?\d+/', '', $j);
            if (isset($codes[$j])) $jours[] = $codes[$j];
        }
        if (!$jours) $jours = [(int) $debut->format('N')];
        sort($jours);
        $lundi = $debut->modify('-' . ((int) $debut->format('N') - 1) . ' days');
        for ($s = 0; $s < 1000 && $compte < $max; $s++) {
            $semaine = $lundi->modify('+' . ($s * $pas) . ' weeks');
            if ($semaine > $limite) break;
            foreach ($jours as $j) {
                if ($compte >= $max) break;
                $d = $semaine->modify('+' . ($j - 1) . ' days');
                if ($d > $limite) break 2;
                $ajouter($d);
            }
        }
    } elseif (in_array($freq, ['DAILY', 'MONTHLY', 'YEARLY'], true)) {
        $unite = ['DAILY' => 'days', 'MONTHLY' => 'months', 'YEARLY' => 'years'][$freq];
        for ($i = 0; $i < 2000 && $compte < $max; $i++) {
            $d = $debut->modify('+' . ($i * $pas) . ' ' . $unite);
            if ($d > $limite) break;
            // 31 du mois / 29 février : on saute les mois sans ce jour.
            if ($freq !== 'DAILY' && $d->format('d') !== $debut->format('d')) continue;
            $ajouter($d);
        }
    } else {
        $resultats[] = $debut;   // règle non gérée : seulement la 1re date
    }
    return $resultats;
}

/**
 * Liste « à plat » des créneaux d'un calendrier, répétitions dépliées,
 * restreinte à une fenêtre autour d'aujourd'hui.
 * Chaque créneau : [titre, description, lieu, debut, fin, journee] (dates locales Y-m-d H:i:s).
 */
function ics_creneaux(string $ics): array
{
    $local = new DateTimeZone(date_default_timezone_get());
    $de = new DateTimeImmutable('-120 days', $local);
    $a  = new DateTimeImmutable('+400 days', $local);
    $evenements = ics_lire($ics);

    // Occurrences modifiées individuellement (RECURRENCE-ID) : elles
    // remplacent l'occurrence d'origine de la série.
    $remplacees = [];
    foreach ($evenements as $ev) {
        if ($ev['recurrence_id']) {
            $remplacees[$ev['uid'] . '|' . ics_cle($ev['recurrence_id'])] = true;
        }
    }

    $creneaux = [];
    foreach ($evenements as $ev) {
        $duree = $ev['fin']->getTimestamp() - $ev['debut']->getTimestamp();
        $debuts = ($ev['rrule'] && !$ev['recurrence_id'])
            ? ics_occurrences($ev, $de->modify('-1 day'), $a)
            : [$ev['debut']];
        $exclues = array_flip(array_map('ics_cle', $ev['exdates']));

        foreach ($debuts as $d) {
            $cle = ics_cle($d);
            if (isset($exclues[$cle])) continue;
            if ($ev['rrule'] && !$ev['recurrence_id'] && isset($remplacees[$ev['uid'] . '|' . $cle])) continue;
            $f = $d->modify('+' . max(0, $duree) . ' seconds');
            if ($f < $de || $d > $a) continue;
            if ($ev['journee']) {
                // Journée entière : dates « murales », sans conversion de fuseau.
                $debutL = $d->format('Y-m-d 00:00:00');
                $finL   = $f->format('Y-m-d 00:00:00');
            } else {
                $debutL = $d->setTimezone($local)->format('Y-m-d H:i:s');
                $finL   = $f->setTimezone($local)->format('Y-m-d H:i:s');
            }
            $creneaux[] = [
                'titre'       => mb_substr(trim($ev['titre']) ?: '(sans titre)', 0, 255),
                'description' => trim($ev['description']),
                'lieu'        => mb_substr(trim($ev['lieu']), 0, 255),
                'debut'       => $debutL,
                'fin'         => $finL,
                'journee'     => $ev['journee'] ? 1 : 0,
            ];
        }
    }
    return $creneaux;
}

/**
 * Télécharge un calendrier depuis son lien iCal (http, https ou webcal).
 * Lève une RuntimeException avec un message compréhensible en cas d'échec.
 */
function ics_telecharger(string $url): string
{
    $url = preg_replace('#^webcals?://#i', 'https://', trim($url));
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('Lien invalide : il doit commencer par https:// (ou webcal://).');
    }
    $max = 10 * 1024 * 1024;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'MesCours-Agenda/1.0',
            CURLOPT_ENCODING       => '',
        ]);
        $contenu = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erreur = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($contenu === false) {
            if (in_array($errno, [35, 51, 58, 60, 77], true)) {
                throw new RuntimeException('Certificat HTTPS non vérifié par PHP (' . $erreur
                    . '). Voir le README, section « Agenda » : réglage curl.cainfo.');
            }
            throw new RuntimeException('Impossible de joindre le lien (' . $erreur . ').');
        }
        if ($code >= 400) {
            throw new RuntimeException("Le serveur de l'emploi du temps a répondu « erreur $code »"
                . ($code === 401 || $code === 403 ? ' : il faut être connecté. Télécharge le fichier .ics et importe-le.' : '.'));
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'timeout' => 30, 'user_agent' => 'MesCours-Agenda/1.0', 'follow_location' => 1,
        ]]);
        $contenu = @file_get_contents($url, false, $ctx, 0, $max);
        if ($contenu === false) {
            throw new RuntimeException('Impossible de télécharger le lien (vérifie l\'adresse et la connexion).');
        }
    }
    if (strlen($contenu) > $max) {
        throw new RuntimeException('Calendrier trop volumineux.');
    }
    if (stripos($contenu, 'BEGIN:VCALENDAR') === false) {
        throw new RuntimeException('Ce lien ne renvoie pas un calendrier iCal (.ics). '
            . 'S\'il demande une connexion, télécharge le fichier .ics et importe-le.');
    }
    return $contenu;
}

/**
 * Remplace les cours d'une source par le contenu du calendrier.
 * Renvoie le nombre de créneaux importés.
 */
function synchroniser_source(int $uid, int $source_id, string $ics): int
{
    $creneaux = ics_creneaux($ics);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM evenements WHERE source_id = ? AND utilisateur_id = ?')
            ->execute([$source_id, $uid]);
        $ins = $pdo->prepare(
            "INSERT INTO evenements
                (utilisateur_id, source_id, type, titre, description, lieu, debut, fin, journee)
             VALUES (?, ?, 'cours', ?, ?, ?, ?, ?, ?)"
        );
        foreach ($creneaux as $c) {
            $ins->execute([$uid, $source_id, $c['titre'], $c['description'], $c['lieu'],
                           $c['debut'], $c['fin'], $c['journee']]);
        }
        $pdo->prepare(
            'UPDATE agenda_sources
                SET derniere_synchro = ?, dernier_message = NULL, nb_evenements = ?
              WHERE id = ? AND utilisateur_id = ?'
        )->execute([date('Y-m-d H:i:s'), count($creneaux), $source_id, $uid]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    associer_matieres($uid);
    return count($creneaux);
}

// ============================================================
//  Rattachement automatique cours -> matière
// ============================================================

/** Minuscules, sans accents ni ponctuation : « Base de Données (CM) » -> « base de donnees cm ». */
function texte_comparable(string $s): string
{
    if (class_exists('Normalizer')) {
        $s = preg_replace('/\p{Mn}+/u', '', Normalizer::normalize($s, Normalizer::FORM_D));
    } else {
        $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i',
                        'ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ÿ'=>'y',
                        'À'=>'A','Â'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Î'=>'I','Ô'=>'O','Û'=>'U','Ç'=>'C']);
    }
    $s = mb_strtolower($s, 'UTF-8');
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}

/**
 * Pour chaque matière, les expressions qui la reconnaissent dans un intitulé
 * de cours : ses mots-clés (séparés par des virgules) + son nom sans
 * parenthèses. Triées de la plus longue à la plus courte.
 */
function motifs_matieres(int $uid): array
{
    $stmt = db()->prepare(
        'SELECT m.id, m.nom, m.mots_cles FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE u.utilisateur_id = ?'
    );
    $stmt->execute([$uid]);
    $motifs = [];
    foreach ($stmt as $m) {
        $expressions = explode(',', (string) $m['mots_cles']);
        $expressions[] = preg_replace('/\s*\(.*?\)\s*/', ' ', $m['nom']);
        foreach ($expressions as $x) {
            $x = texte_comparable($x);
            if (mb_strlen($x) >= 2) {
                $motifs[] = [$x, (int) $m['id']];
            }
        }
    }
    usort($motifs, fn($a, $b) => strlen($b[0]) <=> strlen($a[0]));
    return $motifs;
}

/** Matière reconnue dans un texte (mots entiers), ou null. */
function trouver_matiere(array $motifs, string $texte): ?int
{
    $t = ' ' . texte_comparable($texte) . ' ';
    foreach ($motifs as [$x, $id]) {
        if (strpos($t, ' ' . $x . ' ') !== false) {
            return $id;
        }
    }
    return null;
}

/** Recalcule la matière de tous les cours importés (après synchro ou mots-clés). */
function associer_matieres(int $uid): void
{
    $motifs = motifs_matieres($uid);
    $stmt = db()->prepare(
        'SELECT id, titre, description, matiere_id FROM evenements
          WHERE utilisateur_id = ? AND source_id IS NOT NULL'
    );
    $stmt->execute([$uid]);
    $maj = db()->prepare('UPDATE evenements SET matiere_id = ? WHERE id = ?');
    $cache = [];
    foreach ($stmt->fetchAll() as $ev) {
        $cle = $ev['titre'] . "\n" . $ev['description'];
        if (!array_key_exists($cle, $cache)) {
            // L'intitulé d'abord, la description (où certains logiciels mettent le module) ensuite.
            $cache[$cle] = trouver_matiere($motifs, $ev['titre'])
                ?? trouver_matiere($motifs, $ev['description']);
        }
        $nouvelle = $cache[$cle];
        if ($nouvelle !== ($ev['matiere_id'] === null ? null : (int) $ev['matiere_id'])) {
            $maj->execute([$nouvelle, $ev['id']]);
        }
    }
}
