<?php
/**
 * Composants du design system rendus en PHP (HTML repris de design/composants/).
 *   SubjectList  : structure_ue() + html_blocs_ue()
 *   NoteRow      : html_note_row()
 *   EmptyState   : html_vide()
 */

/**
 * UE de l'utilisateur avec leurs matières et le nombre de notes de chacune.
 * Chaque UE : id, code, nom, court, classe, matieres[] (id, nom, nb_notes).
 */
function structure_ue(int $uid): array
{
    $stmt = db()->prepare(
        'SELECT u.id AS ue_id, u.code, u.nom AS ue_nom,
                m.id AS matiere_id, m.nom AS matiere_nom,
                (SELECT COUNT(*) FROM notes n
                  WHERE n.matiere_id = m.id AND n.supprime = 0) AS nb_notes
           FROM ue u
           LEFT JOIN matieres m ON m.ue_id = u.id
          WHERE u.utilisateur_id = ?
          ORDER BY u.position, u.id, m.position, m.id'
    );
    $stmt->execute([$uid]);
    $ues = [];
    foreach ($stmt as $l) {
        $id = (int) $l['ue_id'];
        $ues[$id] ??= [
            'id'       => $id,
            'code'     => $l['code'],
            'nom'      => $l['ue_nom'],
            'court'    => nom_court_ue($l['ue_nom']),
            'classe'   => classe_ue($uid, $id),
            'matieres' => [],
        ];
        if ($l['matiere_id'] !== null) {
            $ues[$id]['matieres'][] = [
                'id'       => (int) $l['matiere_id'],
                'nom'      => $l['matiere_nom'],
                'nb_notes' => (int) $l['nb_notes'],
            ];
        }
    }
    return array_values($ues);
}

/** Blocs « UE + matières » (SubjectList). Matière sans note : point creux. */
function html_blocs_ue(array $ues): string
{
    $html = '';
    foreach ($ues as $ue) {
        $html .= '<div class="mc-ue-block ' . e($ue['classe']) . '"><div class="mc-ue-block__head">'
               . '<span class="mc-ue">' . e($ue['code']) . '</span>'
               . '<span class="mc-ue-block__name" title="' . e($ue['nom']) . '">' . e($ue['court']) . '</span></div>';
        foreach ($ue['matieres'] as $m) {
            $html .= '<a class="mc-subj ' . e($ue['classe']) . ($m['nb_notes'] ? '' : ' mc-subj--empty')
                   . '" href="matiere.php?id=' . $m['id'] . '"><span class="mc-dot"></span>'
                   . '<span class="mc-subj__name" title="' . e($m['nom']) . '">' . e($m['nom']) . '</span>'
                   . '<span class="mc-subj__count">' . $m['nb_notes'] . '</span></a>';
        }
        if (!$ue['matieres']) {
            $html .= '<p class="mc-meta">Pas encore de matière. <a class="mc-link" href="reglages.php#structure">En ajouter</a></p>';
        }
        $html .= '</div>';
    }
    return $html;
}

/** Répartit des blocs d'UE sur deux colonnes de hauteur proche (mc-two). */
function deux_colonnes(array $ues): array
{
    $total = array_sum(array_map(fn($u) => count($u['matieres']) + 2, $ues));
    $gauche = $droite = [];
    $cumul = 0;
    foreach ($ues as $ue) {
        if ($cumul < $total / 2) {
            $gauche[] = $ue;
            $cumul += count($ue['matieres']) + 2;
        } else {
            $droite[] = $ue;
        }
    }
    return [$gauche, $droite];
}

/**
 * Une note dans une liste (NoteRow).
 * $note : id, titre, matiere_id, date_modification ; $date : texte affiché à droite
 * (temps relatif par défaut).
 */
function html_note_row(array $note, int $uid, ?string $date = null, string $extra = ''): string
{
    $matieres = infos_matieres($uid);
    $m = $note['matiere_id'] ? ($matieres[(int) $note['matiere_id']] ?? null) : null;
    $id = (int) $note['id'];
    $sous = $m
        ? '<span class="mc-dot"></span><span class="mc-note__matiere">' . e($m['court']) . '</span>'
        : '<span class="mc-dot"></span>Non classée · <span class="mc-link" data-classer="' . $id . '">Classer</span>';
    $etoile = !empty($note['epingle']) ? '<span class="mc-note__fav" title="Favori">' . icone('favori', 'mc-ico-sm') . '</span>' : '';
    return '<a class="mc-note ' . ($m ? e($m['classe']) : 'mc-ue-autre') . '" href="note.php?id=' . $id . '">'
         . '<span class="mc-note__title">' . $etoile . e($note['titre']) . '</span>'
         . '<span class="mc-note__time">' . e($date ?? temps_relatif($note['date_modification'])) . '</span>'
         . '<span class="mc-note__sub">' . $sous . '</span>' . $extra . '</a>';
}

/** État vide (EmptyState) : une phrase qui dit quoi faire, et le bouton pour le faire. */
function html_vide(string $texte, string $bouton = ''): string
{
    return '<div class="mc-empty"><p>' . e($texte) . '</p>' . $bouton . '</div>';
}
