<?php
/**
 * Emploi du temps : lecture d'un fichier .ics (export de l'ENT),
 * reconnaissance automatique de la matière, enregistrement en base
 * et synchronisation depuis un lien d'abonnement.
 *
 * Toutes les dates sont stockées en heure locale (Europe/Paris par défaut,
 * modifiable avec la clé 'fuseau' de config.php).
 */

/** Taille maximale d'un .ics accepté (fichier ou lien). */
const EDT_TAILLE_MAX = 5 * 1024 * 1024;

/** Une synchronisation par lien est relancée si la dernière date de plus de… */
const EDT_DELAI_SYNCHRO = 3 * 3600;

/** Fuseau horaire local de l'application. */
function edt_fuseau(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        $tz = new DateTimeZone(config_app()['fuseau'] ?? 'Europe/Paris');
    }
    return $tz;
}

/** « Maintenant » en heure locale. */
function edt_maintenant(): DateTimeImmutable
{
    return new DateTimeImmutable('now', edt_fuseau());
}

// ============================================================
//  1. Lecture du fichier .ics
// ============================================================

/**
 * Transforme le texte d'un .ics en liste de cours :
 *   [['debut' => DateTimeImmutable, 'fin' => …, 'journee' => bool,
 *     'intitule' => '…', 'lieu' => '…', 'description' => '…'], …]
 * Lève une InvalidArgumentException si ce n'est pas un calendrier.
 */
function ics_lire(string $texte): array
{
    // BOM éventuel + fins de ligne Windows.
    $texte = preg_replace('/^\xEF\xBB\xBF/', '', $texte);
    $texte = str_replace(["\r\n", "\r"], "\n", $texte);
    if (!preg_match('/^\s*BEGIN:VCALENDAR/i', $texte)) {
        throw new InvalidArgumentException("Ce fichier n'est pas un calendrier .ics.");
    }
    // Lignes « repliées » : une ligne qui commence par un espace continue la précédente.
    $texte = preg_replace('/\n[ \t]/', '', $texte);

    $cours = [];
    $evt   = null;
    foreach (explode("\n", $texte) as $ligne) {
        if ($ligne === '') continue;
        if (strcasecmp($ligne, 'BEGIN:VEVENT') === 0) { $evt = []; continue; }
        if (strcasecmp($ligne, 'END:VEVENT') === 0) {
            if ($evt !== null) {
                array_push($cours, ...ics_evenement_vers_cours($evt));
            }
            $evt = null;
            continue;
        }
        if ($evt === null) continue;             // hors VEVENT (VTIMEZONE, VALARM…)
        if (preg_match('/^(BEGIN|END):VALARM/i', $ligne)) continue;

        // NOM;PARAM=VAL;PARAM2=VAL:valeur
        $pos = ics_position_deux_points($ligne);
        if ($pos === null) continue;
        $gauche = substr($ligne, 0, $pos);
        $valeur = substr($ligne, $pos + 1);
        $morceaux = explode(';', $gauche);
        $nom = strtoupper(array_shift($morceaux));
        $params = [];
        foreach ($morceaux as $p) {
            [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
            $params[strtoupper($k)] = trim($v, '"');
        }
        // Certaines propriétés peuvent apparaître plusieurs fois (EXDATE).
        $evt[$nom][] = ['valeur' => $valeur, 'params' => $params];
    }
    return $cours;
}

/** Position du « : » séparant nom et valeur (en ignorant ceux entre guillemets). */
function ics_position_deux_points(string $ligne): ?int
{
    $guillemets = false;
    $n = strlen($ligne);
    for ($i = 0; $i < $n; $i++) {
        if ($ligne[$i] === '"') $guillemets = !$guillemets;
        elseif ($ligne[$i] === ':' && !$guillemets) return $i;
    }
    return null;
}

/** Décode le texte d'une propriété (\n, \, \; \\). */
function ics_texte(?array $prop): string
{
    if (!$prop) return '';
    $v = $prop[0]['valeur'];
    $v = strtr($v, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\\;' => ';', '\\\\' => '\\']);
    return trim($v);
}

/**
 * Convertit une date .ics en DateTimeImmutable (heure locale).
 * Formats gérés : 20260930T080000Z (UTC), 20260930T080000 avec TZID,
 * 20260930T080000 « flottante », et 20260930 (journée entière).
 * Renvoie [date, journee] ou null si illisible.
 */
function ics_date(string $valeur, array $params): ?array
{
    $valeur = trim($valeur);
    if (preg_match('/^(\d{8})$/', $valeur, $m)) {
        $d = DateTimeImmutable::createFromFormat('!Ymd', $m[1], edt_fuseau());
        return $d ? [$d, true] : null;
    }
    if (!preg_match('/^(\d{8}T\d{6})(Z?)$/', $valeur, $m)) {
        return null;
    }
    if ($m[2] === 'Z') {
        $tz = new DateTimeZone('UTC');
    } elseif (!empty($params['TZID'])) {
        try {
            $tz = new DateTimeZone($params['TZID']);
        } catch (Exception $e) {
            $tz = edt_fuseau();   // TZID exotique (ex. « Romance Standard Time ») : heure locale
        }
    } else {
        $tz = edt_fuseau();
    }
    $d = DateTimeImmutable::createFromFormat('Ymd\THis', $m[1], $tz);
    return $d ? [$d->setTimezone(edt_fuseau()), false] : null;
}

/**
 * Un VEVENT → un ou plusieurs cours (plusieurs si l'événement se répète).
 * Les événements annulés sont ignorés.
 */
function ics_evenement_vers_cours(array $evt): array
{
    if (strtoupper(ics_texte($evt['STATUS'] ?? null)) === 'CANCELLED') {
        return [];
    }
    if (empty($evt['DTSTART'])) return [];
    $debut = ics_date($evt['DTSTART'][0]['valeur'], $evt['DTSTART'][0]['params']);
    if (!$debut) return [];
    [$debut, $journee] = $debut;

    // Fin : DTEND, sinon DURATION, sinon 1 h (ou 1 jour).
    $fin = null;
    if (!empty($evt['DTEND'])) {
        $f = ics_date($evt['DTEND'][0]['valeur'], $evt['DTEND'][0]['params']);
        $fin = $f[0] ?? null;
    } elseif (!empty($evt['DURATION'])) {
        try {
            $fin = $debut->add(new DateInterval(ltrim(ics_texte($evt['DURATION']), '+')));
        } catch (Exception $e) {
            $fin = null;
        }
    }
    if (!$fin || $fin <= $debut) {
        $fin = $debut->modify($journee ? '+1 day' : '+1 hour');
    }

    $modele = [
        'journee'     => $journee,
        'intitule'    => mb_substr(ics_texte($evt['SUMMARY'] ?? null) ?: 'Cours', 0, 255),
        'lieu'        => mb_substr(ics_texte($evt['LOCATION'] ?? null), 0, 255),
        'description' => ics_texte($evt['DESCRIPTION'] ?? null),
    ];

    // Dates à exclure d'une répétition (EXDATE).
    $exclues = [];
    foreach ($evt['EXDATE'] ?? [] as $ex) {
        foreach (explode(',', $ex['valeur']) as $v) {
            if ($d = ics_date($v, $ex['params'])) {
                $exclues[$d[0]->format('Y-m-d H:i')] = true;
            }
        }
    }

    $duree = $debut->diff($fin);
    $cours = [];
    foreach (ics_repetitions($debut, ics_texte($evt['RRULE'] ?? null)) as $d) {
        if (isset($exclues[$d->format('Y-m-d H:i')])) continue;
        $cours[] = $modele + ['debut' => $d, 'fin' => $d->add($duree)];
    }
    return $cours;
}

/**
 * Dates de début d'un événement répété (RRULE simple : DAILY / WEEKLY,
 * avec INTERVAL, COUNT, UNTIL et BYDAY pour WEEKLY). Sans règle : la date seule.
 * Plafonné à 400 occurrences et à un an pour éviter les boucles infinies.
 */
function ics_repetitions(DateTimeImmutable $debut, string $rrule): array
{
    if ($rrule === '') return [$debut];

    $regle = [];
    foreach (explode(';', $rrule) as $p) {
        [$k, $v] = array_pad(explode('=', $p, 2), 2, '');
        $regle[strtoupper($k)] = strtoupper($v);
    }
    $freq = $regle['FREQ'] ?? '';
    if (!in_array($freq, ['DAILY', 'WEEKLY'], true)) return [$debut];

    $intervalle = max(1, (int) ($regle['INTERVAL'] ?? 1));
    $max        = isset($regle['COUNT']) ? min(400, (int) $regle['COUNT']) : 400;
    $limite     = $debut->modify('+1 year');
    if (!empty($regle['UNTIL']) && ($u = ics_date($regle['UNTIL'], []))) {
        $limite = min($limite, $u[1] ? $u[0]->modify('+1 day') : $u[0]);
    }

    // Jours de la semaine visés (WEEKLY;BYDAY=MO,WE) : décalages depuis le lundi.
    $jours_ics = ['MO' => 0, 'TU' => 1, 'WE' => 2, 'TH' => 3, 'FR' => 4, 'SA' => 5, 'SU' => 6];
    $jours = [];
    if ($freq === 'WEEKLY' && !empty($regle['BYDAY'])) {
        foreach (explode(',', $regle['BYDAY']) as $j) {
            $j = preg_replace('/^[+-]?\d+/', '', $j);
            if (isset($jours_ics[$j])) $jours[] = $jours_ics[$j];
        }
        sort($jours);
    }

    $dates = [];
    if ($jours) {
        // Semaine par semaine à partir du lundi de la semaine de départ.
        $lundi = $debut->modify('monday this week')->setTime(
            (int) $debut->format('H'), (int) $debut->format('i'), (int) $debut->format('s'));
        for ($s = 0; count($dates) < $max; $s += $intervalle) {
            $semaine = $lundi->modify("+$s week");
            if ($semaine > $limite) break;
            foreach ($jours as $j) {
                $d = $semaine->modify("+$j day");
                if ($d < $debut) continue;
                if ($d > $limite || count($dates) >= $max) break 2;
                $dates[] = $d;
            }
        }
    } else {
        $pas = $freq === 'DAILY' ? 'day' : 'week';
        for ($i = 0; count($dates) < $max; $i++) {
            $d = $debut->modify('+' . ($i * $intervalle) . " $pas");
            if ($d > $limite) break;
            $dates[] = $d;
        }
    }
    return $dates ?: [$debut];
}

// ============================================================
//  2. Reconnaissance de la matière
// ============================================================

/** Minuscules, sans accents ni ponctuation : « Base de Données - CM » → « base de donnees cm ». */
function edt_normaliser(string $texte): string
{
    $texte = mb_strtolower($texte, 'UTF-8');
    $texte = strtr($texte, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
    ]);
    $texte = preg_replace('/[^a-z0-9]+/', ' ', $texte);
    return trim($texte);
}

/** Mots significatifs d'un nom (sans petits mots ni types de séance). */
function edt_mots(string $texte): array
{
    static $ignores = ['les', 'des', 'une', 'aux', 'pour', 'sur', 'avec', 'dans', 'par',
                       'cm', 'td', 'tp', 'man', 'cours', 'groupe', 'grp', 'amphi', 'salle'];
    $mots = [];
    foreach (explode(' ', edt_normaliser($texte)) as $m) {
        if (strlen($m) >= 3 && !in_array($m, $ignores, true) && !ctype_digit($m)) {
            $mots[$m] = true;
        }
    }
    return array_keys($mots);
}

/**
 * Trouve la matière la plus proche d'un intitulé de cours.
 * $matieres : [['id' => 3, 'mots' => [...]], …] (voir edt_matieres_utilisateur).
 * Il faut qu'au moins la moitié des mots de la matière (et au moins un) se
 * retrouvent dans l'intitulé ; un mot tronqué (« proba » / « probabilistes »)
 * compte aussi. Renvoie l'id de la matière ou null.
 */
function edt_reconnaitre_matiere(string $intitule, array $matieres): ?int
{
    $mots_cours = edt_mots($intitule);
    if (!$mots_cours) return null;

    $meilleur = null;
    $meilleur_score = 0.0;
    foreach ($matieres as $m) {
        if (!$m['mots']) continue;
        $communs = 0;
        foreach ($m['mots'] as $mot) {
            foreach ($mots_cours as $mc) {
                if ($mot === $mc
                    || (strlen($mc) >= 4 && str_starts_with($mot, $mc))
                    || (strlen($mot) >= 4 && str_starts_with($mc, $mot))) {
                    $communs++;
                    break;
                }
            }
        }
        $score = $communs / count($m['mots']);
        if ($communs > 0 && $score >= 0.5 && $score > $meilleur_score) {
            $meilleur = (int) $m['id'];
            $meilleur_score = $score;
        }
    }
    return $meilleur;
}

/** Matières de l'utilisateur, prêtes pour edt_reconnaitre_matiere(). */
function edt_matieres_utilisateur(int $uid): array
{
    $stmt = db()->prepare(
        'SELECT m.id, m.nom FROM matieres m JOIN ue u ON u.id = m.ue_id
          WHERE u.utilisateur_id = ?'
    );
    $stmt->execute([$uid]);
    $liste = [];
    foreach ($stmt as $m) {
        // Le texte entre parenthèses (« (MAN 1) ») n'aide pas à reconnaître.
        $nom = preg_replace('/\([^)]*\)/', ' ', $m['nom']);
        $liste[] = ['id' => (int) $m['id'], 'mots' => edt_mots($nom)];
    }
    return $liste;
}

// ============================================================
//  3. Enregistrement et synchronisation
// ============================================================

/**
 * Enregistre les cours d'un .ics pour l'utilisateur.
 * Remplace les cours déjà connus à partir du premier jour couvert par le
 * fichier : les semaines plus anciennes (absentes d'un nouvel export) sont
 * conservées. Renvoie un résumé pour l'affichage.
 */
function edt_enregistrer(int $uid, string $texte_ics): array
{
    $cours = ics_lire($texte_ics);
    if (!$cours) {
        throw new InvalidArgumentException('Aucun cours trouvé dans ce calendrier.');
    }
    usort($cours, fn($a, $b) => $a['debut'] <=> $b['debut']);
    $premier = $cours[0]['debut']->setTime(0, 0);

    $matieres = edt_matieres_utilisateur($uid);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM edt_cours WHERE utilisateur_id = ? AND debut >= ?')
            ->execute([$uid, $premier->format('Y-m-d H:i:s')]);

        $ins = $pdo->prepare(
            'INSERT INTO edt_cours
                (utilisateur_id, debut, fin, journee, intitule, lieu, description, matiere_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $reconnus = 0;
        foreach ($cours as $c) {
            $matiere_id = $c['journee'] ? null : edt_reconnaitre_matiere($c['intitule'], $matieres);
            if ($matiere_id) $reconnus++;
            $ins->execute([
                $uid,
                $c['debut']->format('Y-m-d H:i:s'),
                $c['fin']->format('Y-m-d H:i:s'),
                $c['journee'] ? 1 : 0,
                $c['intitule'],
                $c['lieu'] !== '' ? $c['lieu'] : null,
                $c['description'] !== '' ? $c['description'] : null,
                $matiere_id,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'nb'       => count($cours),
        'reconnus' => $reconnus,
        'du'       => $cours[0]['debut']->format('d/m/Y'),
        'au'       => end($cours)['debut']->format('d/m/Y'),
    ];
}

/** Source (lien) de l'utilisateur, ou null. */
function edt_source(int $uid): ?array
{
    $stmt = db()->prepare('SELECT url, derniere_synchro, derniere_erreur FROM edt_sources WHERE utilisateur_id = ?');
    $stmt->execute([$uid]);
    return $stmt->fetch() ?: null;
}

/** Vérifie et normalise un lien d'abonnement (webcal:// → https://). */
function edt_normaliser_url(string $url): string
{
    $url = trim($url);
    $url = preg_replace('#^webcals?://#i', 'https://', $url);
    if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Lien invalide : il doit commencer par https:// (ou webcal://).');
    }
    return $url;
}

/** Télécharge un .ics (délai court : en amphi sans wifi, on n'attend pas). */
function edt_telecharger(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'MesCours/1.0 (emploi du temps)',
        // Coupe le téléchargement au-delà de la taille maximale.
        CURLOPT_NOPROGRESS     => false,
        CURLOPT_PROGRESSFUNCTION => fn($r, $total, $recu) => $recu > EDT_TAILLE_MAX ? 1 : 0,
    ]);
    $contenu = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur  = curl_error($ch);
    curl_close($ch);

    if ($contenu === false) {
        throw new RuntimeException('Téléchargement impossible (' . ($erreur ?: 'pas de connexion') . ').');
    }
    if ($code >= 400) {
        throw new RuntimeException("L'ENT a répondu une erreur $code (lien expiré ?).");
    }
    return $contenu;
}

/**
 * Synchronise depuis le lien enregistré. Ne lève pas d'exception :
 * renvoie ['ok' => true, 'resume' => …] ou ['ok' => false, 'erreur' => '…'].
 * En cas d'échec, les cours déjà enregistrés restent affichés.
 */
function edt_synchroniser(int $uid): array
{
    $source = edt_source($uid);
    if (!$source || !$source['url']) {
        return ['ok' => false, 'erreur' => 'Aucun lien enregistré.'];
    }
    try {
        $resume = edt_enregistrer($uid, edt_telecharger($source['url']));
        // Heure PHP (et non NOW() de MySQL) : les deux horloges peuvent avoir des fuseaux différents.
        db()->prepare('UPDATE edt_sources SET derniere_synchro = ?, derniere_erreur = NULL
                        WHERE utilisateur_id = ?')
            ->execute([edt_maintenant()->format('Y-m-d H:i:s'), $uid]);
        return ['ok' => true, 'resume' => $resume];
    } catch (Throwable $e) {
        $message = ($e instanceof InvalidArgumentException || $e instanceof RuntimeException)
            ? $e->getMessage() : 'Erreur inattendue pendant la synchronisation.';
        db()->prepare('UPDATE edt_sources SET derniere_erreur = ? WHERE utilisateur_id = ?')
            ->execute([mb_substr($message, 0, 255), $uid]);
        return ['ok' => false, 'erreur' => $message];
    }
}

/** Vrai si le lien doit être resynchronisé (jamais fait ou trop ancien). */
function edt_synchro_a_faire(?array $source): bool
{
    if (!$source || !$source['url']) return false;
    if (!$source['derniere_synchro']) return true;
    $derniere = new DateTimeImmutable($source['derniere_synchro'], edt_fuseau());
    return edt_maintenant()->getTimestamp() - $derniere->getTimestamp() > EDT_DELAI_SYNCHRO;
}

// ============================================================
//  4. Lecture pour l'affichage
// ============================================================

/** Cours de l'utilisateur entre deux dates (avec la matière reconnue). */
function edt_cours_entre(int $uid, DateTimeImmutable $du, DateTimeImmutable $au): array
{
    $stmt = db()->prepare(
        'SELECT c.id, c.debut, c.fin, c.journee, c.intitule, c.lieu, c.description,
                c.matiere_id, m.nom AS matiere
           FROM edt_cours c LEFT JOIN matieres m ON m.id = c.matiere_id
          WHERE c.utilisateur_id = ? AND c.debut < ? AND c.fin > ?
          ORDER BY c.debut'
    );
    $stmt->execute([$uid, $au->format('Y-m-d H:i:s'), $du->format('Y-m-d H:i:s')]);
    return $stmt->fetchAll();
}

/** Teinte stable (0-359) pour colorer un cours : même matière = même couleur. */
function edt_teinte(array $cours): int
{
    $cle = $cours['matiere_id'] ? 'm' . $cours['matiere_id'] : edt_normaliser($cours['intitule']);
    return (int) (hexdec(substr(md5($cle), 0, 6)) % 360);
}

/** Vrai si les tables de l'emploi du temps existent (migration 001 appliquée). */
function edt_installe(): bool
{
    try {
        db()->query('SELECT 1 FROM edt_cours LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
