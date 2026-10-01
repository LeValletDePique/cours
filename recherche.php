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

// Sans mot ni tag : les notes les plus récentes (lien « Toutes » de l'accueil).
$resultats = rechercher_notes($uid, $q, $tag, 50);

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
<header class="mc-hello">
    <p class="mc-eyebrow"><?= $q !== '' || $tag ? pluriel(count($resultats), 'résultat') : 'Dernières notes' ?></p>
    <h1 class="mc-title">Recherche</h1>
</header>

<form method="get" action="recherche.php" class="mc-form-ligne mc-page" role="search">
    <label class="mc-quickadd mc-recherche"><?= icone('recherche', 'mc-ico-sm') ?><span class="mc-sr">Mots à chercher</span>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher dans mes notes…" <?= $q === '' ? 'autofocus' : '' ?>></label>
    <?php if ($tag): ?><input type="hidden" name="tag" value="<?= $tag ?>"><?php endif; ?>
    <button type="submit" class="mc-btn">Chercher</button>
</form>

<?php if ($tags): ?>
    <div class="mc-chips mc-page" aria-label="Filtrer par tag">
        <span class="mc-eyebrow">Filtrer</span>
        <?php foreach ($tags as $t): ?>
            <a class="mc-chip" style="--tag: <?= e($t['couleur']) ?>" <?= $tag === (int) $t['id'] ? 'aria-current="true"' : '' ?>
               href="recherche.php?q=<?= urlencode($q) ?>&tag=<?= (int) $t['id'] ?>"><span class="mc-chip__dot"></span><?= e($t['nom']) ?></a>
        <?php endforeach; ?>
        <?php if ($tag): ?>
            <a class="mc-btn mc-btn--ghost mc-btn--sm" href="recherche.php?q=<?= urlencode($q) ?>"><?= icone('fermer', 'mc-ico-sm') ?>Retirer le filtre</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="mc-card mc-page" aria-label="Résultats">
    <?php foreach ($resultats as $r): ?>
        <?= html_note_row($r, $uid, null, ($q !== '' && trim((string) $r['contenu']) !== '')
            ? '<span class="mc-note__extrait">' . e(extrait($r['contenu'])) . '</span>' : '') ?>
    <?php endforeach; ?>
    <?php if (!$resultats): ?>
        <?= $q !== '' || $tag
            ? html_vide('Aucune note ne correspond. Essaie un mot plus court ou retire le filtre : la recherche accepte le début des mots.')
            : html_vide('Tes notes apparaîtront ici. Crée la première, tu pourras ensuite la retrouver par n\'importe quel mot.',
                '<button type="button" class="mc-btn mc-btn--sm" data-action="nouvelle-note">' . icone('plus', 'mc-ico-sm') . 'Nouvelle note</button>') ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
