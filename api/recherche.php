<?php
/**
 * Recherche pour la palette de commandes (Ctrl K) — lecture seule, GET.
 *   ?q=… : notes (plein texte, comme recherche.php) et matières dont le nom correspond.
 *   Sans q : les notes les plus récentes.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/agenda.php';   // texte_comparable()

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}

$uid = utilisateur_id();
$q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
$matieres = infos_matieres($uid);

$notes = array_map(fn($n) => [
    'id'      => (int) $n['id'],
    'titre'   => $n['titre'],
    'matiere' => $n['matiere_id'] && isset($matieres[(int) $n['matiere_id']])
        ? $matieres[(int) $n['matiere_id']]['court'] : 'Non classée',
    'classe'  => $n['matiere_id'] && isset($matieres[(int) $n['matiere_id']])
        ? $matieres[(int) $n['matiere_id']]['classe'] : 'mc-ue-autre',
], rechercher_notes($uid, $q, 0, $q === '' ? 5 : 8));

$trouvees = [];
if ($q !== '') {
    $cherche = texte_comparable($q);
    foreach ($matieres as $m) {
        if ($cherche !== '' && strpos(texte_comparable($m['nom'] . ' ' . $m['ue_code']), $cherche) !== false) {
            $trouvees[] = ['id' => $m['id'], 'nom' => $m['nom'], 'ue' => $m['ue_code'], 'classe' => $m['classe']];
        }
        if (count($trouvees) >= 5) break;
    }
}

repondre_json(['notes' => $notes, 'matieres' => $trouvees]);
