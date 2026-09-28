<?php
/**
 * Recherche plein texte sur le titre et le contenu des notes,
 * avec filtre optionnel par tag. N'affiche que les notes de l'utilisateur.
 */
require_once __DIR__ . '/includes/auth.php';
exiger_connexion();

$uid = utilisateur_id();
$q   = trim($_GET['q'] ?? '');
$tag = (int) ($_GET['tag'] ?? 0);

// Liste des tags (pour proposer un filtre).
$stmt = db()->prepare('SELECT id, nom, couleur FROM tags WHERE utilisateur_id = ? ORDER BY nom');
$stmt->execute([$uid]);
$tags = $stmt->fetchAll();

$resultats = [];
if ($q !== '' || $tag) {
    // Construction dynamique de la requête (valeurs toujours en paramètres liés).
    $where  = ['n.utilisateur_id = ?', 'n.supprime = 0'];
    $params = [$uid];
    $tri    = 'n.date_modification DESC';

    if ($q !== '') {
        $bool = requete_fulltext($q);
        if ($bool !== '') {
            // Recherche plein texte (rapide, avec score de pertinence).
            $where[] = 'MATCH(n.titre, n.contenu) AGAINST(? IN BOOLEAN MODE)';
            $params[] = $bool;
            $tri = 'pertinence DESC';
            $select_score = ', MATCH(n.titre, n.contenu) AGAINST(? IN BOOLEAN MODE) AS pertinence';
            $params_score = [$bool];
        } else {
            // Mots trop courts : on retombe sur une recherche simple LIKE.
            $where[] = '(n.titre LIKE ? OR n.contenu LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
            $select_score = '';
            $params_score = [];
        }
    } else {
        $select_score = '';
        $params_score = [];
    }

    if ($tag) {
        $where[] = 'n.id IN (SELECT note_id FROM note_tags WHERE tag_id = ?)';
        $params[] = $tag;
    }

    $sql = 'SELECT n.id, n.titre, n.contenu, n.date_modification, m.nom AS matiere'
         . $select_score
         . ' FROM notes n LEFT JOIN matieres m ON m.id = n.matiere_id'
         . ' WHERE ' . implode(' AND ', $where)
         . ' ORDER BY ' . $tri . ' LIMIT 50';

    // L'éventuel score se place en tête des paramètres (SELECT avant WHERE).
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge($params_score, $params));
    $resultats = $stmt->fetchAll();
}

/** Petit extrait du contenu autour, sans le balisage Markdown. */
function extrait(string $contenu, int $max = 180): string
{
    $txt = preg_replace('/[#*`>_~\-]+/', ' ', $contenu);
    $txt = trim(preg_replace('/\s+/', ' ', $txt));
    return mb_strlen($txt) > $max ? mb_substr($txt, 0, $max) . '…' : $txt;
}

$titre_page = 'Recherche';
require __DIR__ . '/includes/header.php';
?>
<h1>Recherche</h1>

<form method="get" action="recherche.php" class="barre-recherche">
    <input type="search" name="q" value="<?= e($q) ?>"
           placeholder="Rechercher dans mes notes…" autofocus>
    <?php if ($tag): ?><input type="hidden" name="tag" value="<?= $tag ?>"><?php endif; ?>
    <button type="submit" class="btn-principal">Chercher</button>
</form>

<?php if ($tags): ?>
    <div class="filtres-tags">
        <span class="filtre-libelle">Filtrer :</span>
        <?php foreach ($tags as $t): ?>
            <a class="chip <?= $tag === (int) $t['id'] ? 'actif' : '' ?>"
               style="background: <?= e($t['couleur']) ?>"
               href="recherche.php?q=<?= urlencode($q) ?>&tag=<?= (int) $t['id'] ?>">
                <?= e($t['nom']) ?>
            </a>
        <?php endforeach; ?>
        <?php if ($tag): ?>
            <a class="chip-annuler" href="recherche.php?q=<?= urlencode($q) ?>">✕ retirer le filtre</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($q !== '' || $tag): ?>
    <p class="info-resultats"><?= count($resultats) ?> résultat(s)</p>
    <ul class="liste-notes grande">
        <?php foreach ($resultats as $r): ?>
            <li>
                <a class="note-titre" href="note.php?id=<?= (int) $r['id'] ?>"><?= e($r['titre']) ?></a>
                <span class="note-meta"><?= e($r['matiere'] ?? 'Non classée') ?></span>
                <p class="extrait"><?= e(extrait($r['contenu'])) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (!$resultats): ?>
        <p class="vide">Aucune note ne correspond.</p>
    <?php endif; ?>
<?php else: ?>
    <p class="vide">Tape un mot-clé pour chercher dans tes notes.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
