<?php
/**
 * Atelier comptable (Gestion de l'entreprise) : on saisit les écritures
 * (saisie rapide « 607 / 401 14000 », opérations courantes expliquées ou
 * lignes libres) et tout le reste se calcule : grand livre (comptes en T),
 * balance, compte de résultat, bilan, analyse (FR, BFR, trésorerie, SIG,
 * ratios). Aide-mémoire du cours en dernier onglet.
 * Calculs : assets/js/compta-moteur.js ; interface : assets/js/compta.js.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/compta.php';
exiger_connexion();
installer_compta();

$uid = utilisateur_id();
$stmt = db()->prepare(
    'SELECT m.id, m.nom, u.code AS ue_code FROM matieres m JOIN ue u ON u.id = m.ue_id
      WHERE u.utilisateur_id = ? ORDER BY u.position, m.position'
);
$stmt->execute([$uid]);
$matieres = $stmt->fetchAll();
$matiere_defaut = matiere_gestion($uid);

$titre_page = 'Comptabilité';
$matiere_page = $matiere_defaut;   // « Nouvelle note » depuis cette page : rangée en gestion
require __DIR__ . '/includes/header.php';
?>
<header class="mc-hello compta-entete">
    <p class="mc-eyebrow">Gestion de l'entreprise</p>
    <h1 class="mc-title">Atelier comptable</h1>
    <p>Saisis les opérations : le grand livre, la balance, le compte de résultat et le bilan se calculent tout seuls.</p>
</header>

<section class="mc-card compta-barre" aria-label="Dossier">
    <div class="compta-barre__ligne">
        <label class="mc-sr" for="compta-dossier">Dossier</label>
        <select id="compta-dossier" class="mc-select compta-barre__dossier" title="Changer de dossier"></select>
        <button type="button" class="mc-btn mc-btn--sm" data-c="nouveau"><?= icone('plus', 'mc-ico-sm') ?>Nouveau</button>
        <button type="button" class="mc-btn mc-btn--sm" data-c="exemple" title="Cas Brico Dépôt : grand livre au 30/12 et opérations du 31/12"><?= icone('livre', 'mc-ico-sm') ?>Exemple Brico Dépôt</button>
        <span id="compta-statut" class="statut-save" role="status"></span>
        <span class="mc-actions compta-barre__fin">
            <button type="button" class="mc-btn mc-btn--sm" data-c="note" data-dossier-requis title="Crée une note avec le journal, le grand livre, la balance, le compte de résultat et le bilan"><?= icone('note', 'mc-ico-sm') ?>Créer une note</button>
            <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-c="imprimer" data-dossier-requis title="Imprimer l'onglet affiché (ou PDF)" aria-label="Imprimer"><?= icone('imprimer', 'mc-ico-sm') ?></button>
            <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-c="supprimer" data-dossier-requis title="Supprimer ce dossier" aria-label="Supprimer ce dossier"><?= icone('corbeille', 'mc-ico-sm') ?></button>
        </span>
    </div>
    <div class="compta-barre__ligne compta-barre__infos" data-dossier-requis>
        <label class="compta-champ">Titre
            <input type="text" id="compta-titre" class="mc-input" maxlength="255" placeholder="Ex. TD 2 – Brico Dépôt"></label>
        <label class="compta-champ">Entreprise
            <input type="text" id="compta-entreprise" class="mc-input" maxlength="120" placeholder="Ex. BRICO DÉPÔT"></label>
        <label class="compta-champ compta-champ--court">Clôture
            <input type="text" id="compta-cloture" class="mc-input" maxlength="20" placeholder="31/12/N"></label>
        <label class="compta-champ">Notes rangées dans
            <select id="compta-matiere" class="mc-select">
                <option value="">Non classées</option>
                <?php foreach ($matieres as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === $matiere_defaut ? 'selected' : '' ?>>
                        <?= e($m['ue_code'] . ' · ' . $m['nom']) ?></option>
                <?php endforeach; ?>
            </select></label>
    </div>
</section>

<div class="mc-seg compta-onglets" role="tablist" aria-label="Étapes">
    <button type="button" role="tab" data-onglet="journal" class="actif">1. Journal</button>
    <button type="button" role="tab" data-onglet="grand-livre">2. Grand livre</button>
    <button type="button" role="tab" data-onglet="balance">3. Balance</button>
    <button type="button" role="tab" data-onglet="resultat">4. Compte de résultat</button>
    <button type="button" role="tab" data-onglet="bilan">5. Bilan</button>
    <button type="button" role="tab" data-onglet="analyse">Analyse</button>
    <button type="button" role="tab" data-onglet="memo">Aide-mémoire</button>
</div>

<!-- Aucun dossier encore -->
<section class="mc-card" id="compta-vide" hidden>
    <div class="mc-empty">
        <p>Crée un dossier pour un exercice (un TD, un cas d'entreprise…), ou ouvre l'exemple Brico Dépôt pour voir le résultat attendu.</p>
        <div class="mc-actions">
            <button type="button" class="mc-btn mc-btn--sm mc-btn--primary" data-c="nouveau"><?= icone('plus', 'mc-ico-sm') ?>Nouveau dossier</button>
            <button type="button" class="mc-btn mc-btn--sm" data-c="exemple"><?= icone('livre', 'mc-ico-sm') ?>Ouvrir l'exemple Brico Dépôt</button>
        </div>
    </div>
</section>

<!-- 1. Journal -->
<div class="compta-panneau" data-panneau="journal">
    <section class="mc-card compta-saisie-carte" aria-labelledby="titre-saisie">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-saisie"><?= icone('crayon', 'mc-ico-sm') ?>Enregistrer une opération</h2></div>
        <div class="compta-saisie">
            <form class="compta-saisie__bloc" id="form-rapide" autocomplete="off">
                <label class="mc-eyebrow" for="saisie-rapide">Saisie rapide</label>
                <div class="mc-form-ligne">
                    <input type="text" id="saisie-rapide" class="mc-input mc-mono"
                           placeholder="31/12 607 / 401 14 000 Achat de marchandises à crédit">
                    <button type="submit" class="mc-btn mc-btn--sm mc-btn--primary">Ajouter</button>
                </div>
                <p class="mc-meta">Date (facultative) · compte <b>débité</b> / compte <b>crédité</b> · montant · libellé.
                    Les comptes s'écrivent en chiffres ou en mots : <code>banque / ventes 45000</code>. Entrée pour valider. <span id="rapide-apercu" class="compta-apercu"></span></p>
            </form>
            <form class="compta-saisie__bloc" id="form-operation" autocomplete="off">
                <label class="mc-eyebrow" for="op-type">Opération courante (expliquée)</label>
                <div class="mc-form-ligne">
                    <select id="op-type" class="mc-select mc-input--large"></select>
                    <input type="text" id="op-montant" class="mc-input mc-input--court" inputmode="decimal" placeholder="Montant" aria-label="Montant">
                    <input type="text" id="op-date" class="mc-input mc-input--court" placeholder="31/12" aria-label="Date">
                    <button type="submit" class="mc-btn mc-btn--sm">Ajouter</button>
                </div>
                <p class="compta-pourquoi" id="op-pourquoi" aria-live="polite"></p>
            </form>
        </div>
        <p class="mc-meta">Une opération avec plusieurs comptes (TVA, salaires…) :
            <button type="button" class="mc-link compta-lien" data-c="ecriture-vide">ajoute une écriture vide</button> et remplis ses lignes.</p>
    </section>

    <section class="mc-card" aria-labelledby="titre-journal">
        <div class="mc-card__head">
            <h2 class="mc-h" id="titre-journal"><?= icone('livre', 'mc-ico-sm') ?>Journal</h2>
            <span class="mc-meta" id="journal-resume"></span>
        </div>
        <datalist id="pcg-liste"></datalist>
        <div id="ecritures" class="compta-ecritures"></div>
        <div class="mc-actions compta-ecritures__pied">
            <button type="button" class="mc-btn mc-btn--sm" data-c="ecriture-vide"><?= icone('plus', 'mc-ico-sm') ?>Écriture vide</button>
            <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-c="vue-journal" title="Le journal présenté comme sur une copie">Voir le journal « sur papier »</button>
        </div>
        <div id="journal-papier" hidden></div>
    </section>
</div>

<!-- 2. Grand livre -->
<section class="mc-card compta-panneau" data-panneau="grand-livre" hidden aria-labelledby="titre-gl">
    <div class="mc-card__head">
        <h2 class="mc-h" id="titre-gl">Grand livre (comptes en T)</h2>
        <input type="search" id="gl-filtre" class="mc-input compta-filtre" placeholder="Filtrer : 512, banque, classe 6…" aria-label="Filtrer les comptes">
    </div>
    <p class="mc-meta">Chaque montant du journal est reporté du même côté (débit à gauche, crédit à droite).
        Survole un montant pour voir l'écriture d'où il vient.</p>
    <div id="grand-livre"></div>
</section>

<!-- 3. Balance -->
<section class="mc-card compta-panneau" data-panneau="balance" hidden aria-labelledby="titre-balance">
    <div class="mc-card__head"><h2 class="mc-h" id="titre-balance">Balance</h2><span class="mc-meta" id="balance-date"></span></div>
    <p class="mc-meta">Pour chaque compte : total des débits, total des crédits, puis le solde (la différence) du côté le plus fort.</p>
    <div id="balance"></div>
</section>

<!-- 4. Compte de résultat -->
<section class="mc-card compta-panneau" data-panneau="resultat" hidden aria-labelledby="titre-resultat">
    <div class="mc-card__head"><h2 class="mc-h" id="titre-resultat">Compte de résultat</h2></div>
    <p class="mc-meta">Charges = comptes de classe 6, produits = classe 7. Résultat = produits − charges ;
        un bénéfice s'inscrit côté charges (et une perte côté produits) pour équilibrer les deux colonnes.</p>
    <div id="resultat"></div>
</section>

<!-- 5. Bilan -->
<section class="mc-card compta-panneau" data-panneau="bilan" hidden aria-labelledby="titre-bilan">
    <div class="mc-card__head"><h2 class="mc-h" id="titre-bilan">Bilan</h2></div>
    <p class="mc-meta">Comptes de classes 1 à 5 avec leur solde : ce que l'entreprise possède (actif) et
        comment c'est financé (passif). Le résultat du compte de résultat rejoint les capitaux propres.</p>
    <div id="bilan"></div>
</section>

<!-- Analyse -->
<div class="compta-panneau" data-panneau="analyse" hidden>
    <section class="mc-card" aria-labelledby="titre-equilibre">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-equilibre">Équilibre financier</h2></div>
        <div id="analyse-equilibre" class="compta-kpis"></div>
        <p class="mc-meta" id="analyse-commentaire"></p>
    </section>
    <div class="mc-two compta-analyse">
        <section class="mc-card" aria-labelledby="titre-sig">
            <div class="mc-card__head"><h2 class="mc-h" id="titre-sig">Soldes intermédiaires de gestion</h2></div>
            <div id="analyse-sig"></div>
        </section>
        <section class="mc-card" aria-labelledby="titre-ratios">
            <div class="mc-card__head"><h2 class="mc-h" id="titre-ratios">Ratios</h2></div>
            <div id="analyse-ratios"></div>
        </section>
    </div>
</div>

<!-- Aide-mémoire du cours -->
<div class="compta-panneau compta-memo" data-panneau="memo" hidden>
    <section class="mc-card mc-aide">
        <div class="mc-card__head"><h2 class="mc-h"><?= icone('chapeau', 'mc-ico-sm') ?>La méthode, étape par étape</h2></div>
        <ol class="guide">
            <li><strong>Analyser l'opération</strong> : quels comptes sont touchés ? Augmentent-ils ou diminuent-ils ?
                Il y a toujours <em>au moins deux comptes</em> (principe de la partie double).</li>
            <li><strong>Journal</strong> : on écrit l'opération (date, comptes, montants, libellé).
                Total des débits = total des crédits, toujours.</li>
            <li><strong>Grand livre</strong> : chaque montant est reporté dans son compte en T, du même côté
                (un débit du journal va à gauche, un crédit à droite).</li>
            <li><strong>Balance</strong> : pour chaque compte, total débit, total crédit et solde.
                Les totaux des débits et des crédits sont égaux ; les soldes débiteurs et créditeurs aussi.</li>
            <li><strong>Compte de résultat</strong> : comptes de classe 6 (charges) et 7 (produits).
                <code>Résultat = Produits − Charges</code>.</li>
            <li><strong>Bilan</strong> : comptes de classes 1 à 5 avec leur solde, plus le résultat dans les capitaux propres.
                <code>Total actif = Total passif</code>.</li>
        </ol>
    </section>

    <div class="mc-two">
        <section class="mc-card mc-aide">
            <div class="mc-card__head"><h2 class="mc-h">Débit ou crédit ?</h2></div>
            <table class="cpt-table compta-memo__table">
                <thead><tr><th>Nature du compte</th><th>Augmente au</th><th>Diminue au</th></tr></thead>
                <tbody>
                    <tr><td><b>Actif</b> : immobilisations, stocks, créances clients, banque, caisse</td><td>Débit</td><td>Crédit</td></tr>
                    <tr><td><b>Passif</b> : capital, emprunts, dettes fournisseurs, sociales, fiscales</td><td>Crédit</td><td>Débit</td></tr>
                    <tr><td><b>Charges</b> (classe 6)</td><td>Débit</td><td>Crédit <small>(rare : annulation)</small></td></tr>
                    <tr><td><b>Produits</b> (classe 7)</td><td>Crédit</td><td>Débit <small>(rare : annulation)</small></td></tr>
                </tbody>
            </table>
            <ul class="guide compta-memo__astuces">
                <li><strong>Emplois au débit, ressources au crédit</strong> : ce que l'entreprise reçoit ou utilise
                    (un bien, une charge, de l'argent en banque) va au débit ; d'où ça vient (une vente, une dette, un apport) va au crédit.</li>
                <li>Pour la trésorerie : <strong>l'argent qui entre</strong> en banque ou en caisse → débit 512 / 530 ;
                    <strong>l'argent qui sort</strong> → crédit 512 / 530.</li>
                <li>Partie double : chaque écriture a <strong>autant au débit qu'au crédit</strong>.</li>
            </ul>
        </section>

        <section class="mc-card mc-aide">
            <div class="mc-card__head"><h2 class="mc-h">Les 7 classes du plan comptable</h2></div>
            <table class="cpt-table compta-memo__table">
                <thead><tr><th>Classe</th><th>Contenu</th><th>Solde habituel</th><th>Va dans</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td>Capitaux : capital, réserves, résultat, emprunts (16)</td><td>Créditeur</td><td>Passif</td></tr>
                    <tr><td>2</td><td>Immobilisations (terrains, constructions, matériel…) et amortissements (28)</td><td>Débiteur</td><td>Actif</td></tr>
                    <tr><td>3</td><td>Stocks (marchandises 37, matières 31…)</td><td>Débiteur</td><td>Actif</td></tr>
                    <tr><td>4</td><td>Tiers : fournisseurs 401, clients 411, personnel 42, État 44…</td><td>Selon le compte</td><td>Actif ou passif</td></tr>
                    <tr><td>5</td><td>Financiers : banque 512, caisse 530, VMP 50</td><td>Débiteur (créditeur = découvert)</td><td>Actif (ou dettes)</td></tr>
                    <tr><td>6</td><td>Charges : achats 60, services 61-62, impôts 63, personnel 64, intérêts 66…</td><td>Débiteur</td><td>Compte de résultat</td></tr>
                    <tr><td>7</td><td>Produits : ventes 70, subventions 74, produits financiers 76…</td><td>Créditeur</td><td>Compte de résultat</td></tr>
                </tbody>
            </table>
        </section>
    </div>

    <div class="mc-two">
        <section class="mc-card mc-aide">
            <div class="mc-card__head"><h2 class="mc-h">Structure du bilan</h2></div>
            <div class="compta-schema">
                <div><p class="mc-eyebrow">Actif — ce que l'entreprise possède</p>
                    <p><b>Actif immobilisé</b> (classe 2) : incorporelles (logiciels, fonds commercial), corporelles
                        (terrains, constructions, matériel), financières (titres, prêts), <em>moins</em> les amortissements (28) et dépréciations (29).</p>
                    <p><b>Actif circulant</b> : stocks (3), créances (clients 411, TVA déductible…), disponibilités (banque, caisse).</p>
                    <p class="mc-meta">Classé du moins liquide au plus liquide.</p></div>
                <div><p class="mc-eyebrow">Passif — comment c'est financé</p>
                    <p><b>Capitaux propres</b> : capital, réserves, report à nouveau, <b>résultat de l'exercice</b> (négatif si perte).</p>
                    <p><b>Provisions</b> pour risques et charges (15).</p>
                    <p><b>Dettes</b> : emprunts (16), fournisseurs (401, 404), dettes sociales et fiscales (42-44),
                        concours bancaires (banque créditrice).</p>
                    <p class="mc-meta">Classé du moins exigible au plus exigible.</p></div>
            </div>
        </section>

        <section class="mc-card mc-aide">
            <div class="mc-card__head"><h2 class="mc-h">Structure du compte de résultat</h2></div>
            <div class="compta-schema">
                <div><p class="mc-eyebrow">Charges (classe 6)</p>
                    <p><b>Exploitation</b> : achats (60), services extérieurs (61-62), impôts et taxes (63),
                        personnel (64), autres charges (65), dotations (681).</p>
                    <p><b>Financières</b> : intérêts (66), dotations (686).</p>
                    <p><b>Exceptionnelles</b> : 67, 687.</p>
                    <p><b>Participation, impôt sur les bénéfices</b> : 69.</p></div>
                <div><p class="mc-eyebrow">Produits (classe 7)</p>
                    <p><b>Exploitation</b> : ventes (70), production stockée (71) et immobilisée (72),
                        subventions (74), autres produits (75), reprises (781).</p>
                    <p><b>Financiers</b> : 76, 786.</p>
                    <p><b>Exceptionnels</b> : 77, 787.</p>
                    <p class="mc-meta">Bénéfice si produits &gt; charges, perte sinon.</p></div>
            </div>
        </section>
    </div>

    <section class="mc-card mc-aide">
        <div class="mc-card__head"><h2 class="mc-h"><?= icone('maths', 'mc-ico-sm') ?>Formules à connaître</h2></div>
        <div class="compta-formules">
            <div><h3>Résultat et bilan</h3>
                <p><code>Résultat = Produits − Charges</code></p>
                <p><code>Actif = Passif</code> (le résultat est inclus dans les capitaux propres)</p>
                <p><code>Solde = Total débit − Total crédit</code> (débiteur si positif, créditeur sinon)</p></div>
            <div><h3>Équilibre financier</h3>
                <p><code>FR = (Capitaux propres + Provisions + Dettes financières) − Actif immobilisé</code></p>
                <p><code>BFR = (Stocks + Créances) − Dettes d'exploitation</code></p>
                <p><code>Trésorerie nette = FR − BFR = Disponibilités − Concours bancaires</code></p></div>
            <div><h3>Soldes intermédiaires de gestion</h3>
                <p><code>Marge commerciale = Ventes de marchandises − Coût d'achat des marchandises vendues</code></p>
                <p><code>VA = Marge + Production − Consommations en provenance des tiers (60 hors marchandises, 61, 62)</code></p>
                <p><code>EBE = VA + Subventions − Impôts et taxes − Charges de personnel</code></p>
                <p><code>Résultat d'exploitation = EBE + Autres produits + Reprises − Autres charges − Dotations</code></p></div>
            <div><h3>Stocks</h3>
                <p><code>Coût d'achat des marchandises vendues = Achats + Stock initial − Stock final</code></p>
                <p><code>Variation de stock (6037) = Stock initial − Stock final</code></p></div>
            <div><h3>TVA (taux normal 20 %)</h3>
                <p><code>TTC = HT × 1,2</code> · <code>TVA = HT × 0,2</code> · <code>HT = TTC ÷ 1,2</code></p>
                <p><code>TVA à décaisser = TVA collectée (44571) − TVA déductible (44566)</code></p></div>
            <div><h3>Amortissement linéaire</h3>
                <p><code>Annuité = Valeur d'origine ÷ Durée d'utilisation</code> (× mois ÷ 12 la première année)</p>
                <p><code>VNC = Valeur d'origine − Cumul des amortissements</code></p>
                <p><code>Taux = 100 % ÷ Durée</code> (5 ans → 20 %)</p></div>
        </div>
    </section>

    <section class="mc-card mc-aide">
        <div class="mc-card__head"><h2 class="mc-h"><?= icone('alerte', 'mc-ico-sm') ?>Pièges fréquents</h2></div>
        <ul class="guide">
            <li>Le <strong>solde</strong> n'est pas un total : c'est la différence entre le total débit et le total crédit.</li>
            <li>Un <strong>achat d'immobilisation</strong> (véhicule, ordinateur…) va en classe 2, pas en charge (classe 6).</li>
            <li><strong>Remboursement d'emprunt</strong> : le capital remboursé diminue la dette (débit 164) ; seuls les intérêts sont une charge (661).</li>
            <li>Les <strong>charges et produits sont hors taxe</strong> ; la TVA va dans les comptes 445 (déductible 44566, collectée 44571).</li>
            <li>Les comptes de <strong>tiers et la banque</strong> changent de côté selon leur solde : banque créditrice = découvert (dettes), fournisseur débiteur = créance.</li>
            <li>Le <strong>résultat</strong> va au passif dans les capitaux propres ; une perte y est inscrite en négatif.</li>
            <li>Un <strong>terrain ne s'amortit pas</strong> ; les amortissements (28) se retranchent de l'actif immobilisé.</li>
            <li>Une « facture à payer dans 30 jours » est une <strong>dette</strong> (401), pas un paiement : la banque ne bouge pas.</li>
        </ul>
    </section>

    <section class="mc-card mc-aide">
        <div class="mc-card__head"><h2 class="mc-h"><?= icone('lien', 'mc-ico-sm') ?>Opérations courantes : quel compte ?</h2></div>
        <p class="mc-meta">Clique sur une ligne pour la préparer dans l'onglet Journal (il ne restera que le montant).</p>
        <div id="memo-operations"></div>
    </section>

    <section class="mc-card mc-aide">
        <div class="mc-card__head"><h2 class="mc-h"><?= icone('recherche', 'mc-ico-sm') ?>Plan comptable</h2></div>
        <input type="search" id="pcg-recherche" class="mc-input maths-recherche" placeholder="Chercher : banque, 607, amortissement, TVA…" aria-label="Chercher un compte">
        <div id="memo-pcg" class="compta-pcg"></div>
    </section>

    <section class="mc-card mc-aide">
        <div class="mc-card__head"><h2 class="mc-h"><?= icone('note', 'mc-ico-sm') ?>De la compta dans tes notes</h2></div>
        <p>Dans l'éditeur de note, le menu <strong>Compta</strong> insère un compte en T, des écritures, une balance,
            un compte de résultat ou un bilan : les totaux, soldes et le résultat sont calculés à l'affichage.
            Toutes les syntaxes sont dans <a href="aide.php#compta">l'aide</a>. Le bouton « Créer une note » ci-dessus
            y copie tout le dossier.</p>
    </section>
</div>

<script defer src="<?= asset('assets/js/compta-moteur.js') ?>"></script>
<script defer src="<?= asset('assets/js/compta.js') ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
