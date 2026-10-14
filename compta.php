<?php
/**
 * Atelier comptable (Gestion de l'entreprise) : on part d'un bilan de départ
 * (facultatif, ses montants ouvrent les comptes : à-nouveaux), on saisit les écritures
 * (saisie rapide « 607 / 401 14000 », opérations courantes expliquées ou
 * lignes libres) et tout le reste se calcule : grand livre (comptes en T),
 * balance, compte de résultat, bilan, analyse (FR, BFR, trésorerie, SIG,
 * ratios). Onglet « Factures & TVA » : une facture (remises, escompte, port,
 * acompte) ou un avoir donne ses écritures chez le fournisseur et chez le client ;
 * ristourne selon un barème ; déclaration de TVA. Aide-mémoire du cours en dernier onglet.
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
        <label class="mc-sr" for="compta-exemple">Ouvrir un exemple</label>
        <select id="compta-exemple" class="mc-select compta-barre__exemple" title="Crée un dossier d'exemple pour voir le résultat attendu">
            <option value="">Ouvrir un exemple…</option>
            <option value="depart">Du bilan de départ au bilan de fin</option>
            <option value="brico">Cas Brico Dépôt (grand livre au 30/12)</option>
            <option value="facturation">La facturation (factures, avoirs, TVA)</option>
        </select>
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
    <button type="button" role="tab" data-onglet="depart">0. Bilan de départ</button>
    <button type="button" role="tab" data-onglet="journal" class="actif">1. Journal</button>
    <button type="button" role="tab" data-onglet="factures" title="Facture, avoir, ristourne, déclaration de TVA">Factures &amp; TVA</button>
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
        <p>Crée un dossier pour un exercice (un TD, un cas d'entreprise…), ou ouvre un exemple pour voir le résultat attendu.</p>
        <div class="mc-actions">
            <button type="button" class="mc-btn mc-btn--sm mc-btn--primary" data-c="nouveau"><?= icone('plus', 'mc-ico-sm') ?>Nouveau dossier</button>
            <button type="button" class="mc-btn mc-btn--sm" data-c="exemple-depart"><?= icone('livre', 'mc-ico-sm') ?>Exemple : du bilan de départ au bilan de fin</button>
            <button type="button" class="mc-btn mc-btn--sm" data-c="exemple"><?= icone('livre', 'mc-ico-sm') ?>Exemple : cas Brico Dépôt</button>
            <button type="button" class="mc-btn mc-btn--sm" data-c="exemple-facturation"><?= icone('livre', 'mc-ico-sm') ?>Exemple : la facturation</button>
        </div>
    </div>
</section>

<!-- 0. Bilan de départ -->
<div class="compta-panneau" data-panneau="depart" hidden>
    <section class="mc-card" aria-labelledby="titre-depart">
        <div class="mc-card__head">
            <h2 class="mc-h" id="titre-depart"><?= icone('tableau', 'mc-ico-sm') ?>Bilan de départ</h2>
            <label class="compta-champ compta-champ--court compta-depart__date">Date
                <input type="text" id="depart-date" class="mc-input" maxlength="20" placeholder="01/01/N"></label>
        </div>
        <p class="mc-meta">Recopie le bilan donné dans l'énoncé : chaque montant ouvre son compte dans le grand livre
            (un « à-nouveau », noté <b>AN</b>), puis les opérations du journal s'y ajoutent. Tape le numéro du compte
            <em>ou</em> son nom (« banque », « capital », « dettes fournisseurs »). Un amortissement se saisit en négatif à l'actif.
            Laisse vide si l'exercice part de zéro.</p>
        <div class="compta-depart" id="depart-editeur">
            <div class="compta-depart__cote" data-cote="actif">
                <p class="mc-eyebrow">Actif — ce que l'entreprise possède</p>
                <table class="cpt-ecr__lignes"><thead><tr><th>Compte</th><th>Intitulé</th><th>Montant</th><th></th></tr></thead><tbody></tbody></table>
                <div class="compta-depart__pied"><button type="button" class="mc-link compta-lien" data-c="depart-ligne">+ ligne</button>
                    <span class="compta-depart__total"></span></div>
            </div>
            <div class="compta-depart__cote" data-cote="passif">
                <p class="mc-eyebrow">Passif — comment c'est financé</p>
                <table class="cpt-ecr__lignes"><thead><tr><th>Compte</th><th>Intitulé</th><th>Montant</th><th></th></tr></thead><tbody></tbody></table>
                <div class="compta-depart__pied"><button type="button" class="mc-link compta-lien" data-c="depart-ligne">+ ligne</button>
                    <span class="compta-depart__total"></span></div>
            </div>
        </div>
        <p class="cpt-verif" id="depart-verif"></p>
        <details class="compta-coller">
            <summary>Coller ou taper tout le bilan d'un coup</summary>
            <p class="mc-meta">Une ligne par poste avec son montant, « Actif » puis « Passif » (les titres de rubriques sont ignorés).
                Exemple : <code>Terrains 300 000</code>, <code>Capital : 500 000</code>, <code>164 Emprunts 100000</code>.</p>
            <textarea id="depart-texte" class="mc-textarea mc-mono" rows="8" placeholder="Actif&#10;Constructions 120 000&#10;Matériel de transport 30 000&#10;Banque 22 000&#10;Passif&#10;Capital 150 000&#10;Emprunts 22 000"></textarea>
            <div class="mc-actions"><button type="button" class="mc-btn mc-btn--sm" data-c="depart-remplir">Remplir le bilan de départ</button>
                <span class="mc-meta" id="depart-texte-statut"></span></div>
        </details>
    </section>
    <section class="mc-card" aria-labelledby="titre-depart-apercu">
        <div class="mc-card__head"><h2 class="mc-h" id="titre-depart-apercu">Aperçu</h2>
            <button type="button" class="mc-btn mc-btn--sm" data-c="aller-journal">Passer au journal<?= icone('suivant', 'mc-ico-sm') ?></button></div>
        <div id="depart-apercu"></div>
    </section>
</div>

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
                    <select id="op-taux" class="mc-select mc-input--court" aria-label="Taux de TVA" hidden>
                        <option value="20">TVA 20 %</option><option value="10">TVA 10 %</option>
                        <option value="5.5">TVA 5,5 %</option><option value="2.1">TVA 2,1 %</option>
                    </select>
                    <input type="text" id="op-date" class="mc-input mc-input--court" placeholder="31/12" aria-label="Date">
                    <button type="submit" class="mc-btn mc-btn--sm">Ajouter</button>
                </div>
                <p class="compta-pourquoi" id="op-pourquoi" aria-live="polite"></p>
            </form>
        </div>
        <p class="mc-meta">Une opération avec plusieurs comptes (TVA, salaires…) :
            <button type="button" class="mc-link compta-lien" data-c="ecriture-vide">ajoute une écriture vide</button> et remplis ses lignes.
            Une facture avec remise, escompte, acompte ou un avoir :
            <button type="button" class="mc-link compta-lien" data-c="aller-factures">onglet Factures &amp; TVA</button>.</p>
    </section>

    <section class="mc-card" aria-labelledby="titre-journal">
        <div class="mc-card__head">
            <h2 class="mc-h" id="titre-journal"><?= icone('livre', 'mc-ico-sm') ?>Journal</h2>
            <span class="mc-meta" id="journal-resume"></span>
        </div>
        <p class="mc-meta compta-an" id="journal-an" hidden></p>
        <datalist id="pcg-liste"></datalist>
        <div id="ecritures" class="compta-ecritures"></div>
        <div class="mc-actions compta-ecritures__pied">
            <button type="button" class="mc-btn mc-btn--sm" data-c="ecriture-vide"><?= icone('plus', 'mc-ico-sm') ?>Écriture vide</button>
            <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-c="vue-journal" title="Le journal présenté comme sur une copie">Voir le journal « sur papier »</button>
        </div>
        <div id="journal-papier" hidden></div>
    </section>
</div>

<!-- Factures et TVA (chapitre « Les échanges commerciaux et la facturation ») -->
<div class="compta-panneau" data-panneau="factures" hidden>
    <section class="mc-card" aria-labelledby="titre-facture" id="carte-facture">
        <div class="mc-card__head">
            <h2 class="mc-h" id="titre-facture"><?= icone('calculatrice', 'mc-ico-sm') ?>Facture → écritures</h2>
            <label class="mc-sr" for="fact-type">Type de facture</label>
            <select id="fact-type" class="mc-select compta-facture__type">
                <option value="doit">Facture (de doit)</option>
                <option value="retour">Avoir : retour de marchandises</option>
                <option value="reduction">Avoir : réduction hors facture (ristourne, rabais, remise oubliée)</option>
            </select>
        </div>
        <p class="mc-meta">Recopie la facture de l'énoncé : le net commercial, l'escompte, la TVA et le net à payer se calculent,
            et les écritures sont données <b>chez le fournisseur</b> et <b>chez le client</b>, avec le pourquoi de chaque compte.
            Pratique pour vérifier un corrigé ; « Ajouter au journal » les recopie dans le dossier ouvert.</p>
        <div class="compta-facture">
            <div class="compta-facture__saisie">
                <div class="compta-facture__champs">
                    <label class="compta-champ">Fournisseur <input type="text" id="fact-fournisseur" class="mc-input" placeholder="Fournisseur Y"></label>
                    <label class="compta-champ">Client <input type="text" id="fact-client" class="mc-input" placeholder="Client X"></label>
                    <label class="compta-champ compta-champ--court">N° <input type="text" id="fact-numero" class="mc-input" placeholder="123"></label>
                    <label class="compta-champ compta-champ--court">Date <input type="text" id="fact-date" class="mc-input" placeholder="10/03"></label>
                </div>
                <table class="cpt-ecr__lignes compta-facture__lignes">
                    <thead><tr><th>Désignation</th><th>Qté</th><th>PU HT</th><th>Montant</th><th></th></tr></thead>
                    <tbody id="fact-lignes"></tbody>
                </table>
                <button type="button" class="mc-link compta-lien" data-f="ligne">+ ligne</button>
                <div class="compta-facture__champs">
                    <label class="compta-champ">Réductions commerciales (%)
                        <input type="text" id="fact-remises" class="mc-input" placeholder="25 ou 10 + 5 ou rabais 5"
                               title="Remise, rabais ou ristourne sur la facture, en %. Plusieurs : « 10 + 5 » (en cascade). Un montant : « 50 € »."></label>
                    <label class="compta-champ compta-champ--court">Escompte (%)
                        <input type="text" id="fact-escompte" class="mc-input" inputmode="decimal" placeholder="2"></label>
                    <label class="compta-champ compta-champ--court" data-fact-doit>Frais de port HT
                        <input type="text" id="fact-port" class="mc-input" inputmode="decimal" placeholder="0"></label>
                    <label class="compta-champ compta-champ--court">TVA
                        <select id="fact-tva" class="mc-select">
                            <option value="20">20 %</option><option value="10">10 %</option>
                            <option value="5.5">5,5 %</option><option value="2.1">2,1 %</option><option value="0">Sans TVA</option>
                        </select></label>
                </div>
                <div class="compta-facture__champs" data-fact-doit>
                    <label class="compta-champ compta-champ--court">Acompte versé
                        <input type="text" id="fact-acompte" class="mc-input" inputmode="decimal" placeholder="0"></label>
                    <label class="compta-facture__case"><input type="checkbox" id="fact-acompte-verse" class="mc-check" checked>
                        Écrire aussi le versement de l'acompte (à la commande)</label>
                </div>
                <div class="compta-facture__champs">
                    <label class="compta-champ">Ce qui est vendu
                        <select id="fact-nature" class="mc-select"></select></label>
                    <label class="compta-champ compta-champ--court">Vente
                        <input type="text" id="fact-vente" class="mc-input mc-mono" list="pcg-liste" title="Compte de vente chez le fournisseur (707, 701, 706…)"></label>
                    <label class="compta-champ compta-champ--court">Achat
                        <input type="text" id="fact-achat" class="mc-input mc-mono" list="pcg-liste" title="Compte d'achat chez le client (607, 601, 61/62…, 2… pour une immobilisation)"></label>
                    <label class="compta-champ">Règlement
                        <select id="fact-reglement" class="mc-select">
                            <option value="credit">À crédit (à payer plus tard)</option>
                            <option value="512">Au comptant : chèque, CB, virement (512)</option>
                            <option value="530">Au comptant : espèces (530)</option>
                        </select></label>
                </div>
                <div class="mc-actions">
                    <button type="button" class="mc-btn mc-btn--sm" data-f="copier" title="Copie la facture au format ```facture : à coller dans une note">
                        <?= icone('note', 'mc-ico-sm') ?>Copier pour une note</button>
                    <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-f="vider">Nouvelle facture</button>
                    <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-f="exemple">Exemple du cours</button>
                    <span class="mc-meta" id="fact-statut" role="status"></span>
                </div>
            </div>
            <div class="compta-facture__apercu" id="fact-apercu"></div>
        </div>
    </section>

    <div class="mc-two compta-facture__cotes">
        <?php foreach (['fournisseur' => 'Chez le fournisseur', 'client' => 'Chez le client'] as $cote => $titre_cote): ?>
            <section class="mc-card" aria-labelledby="titre-fact-<?= $cote ?>">
                <div class="mc-card__head">
                    <h2 class="mc-h" id="titre-fact-<?= $cote ?>"><?= $titre_cote ?></h2>
                    <button type="button" class="mc-btn mc-btn--sm" data-f="ajouter" data-cote="<?= $cote ?>"
                            title="Recopie ces écritures dans le journal du dossier ouvert"><?= icone('plus', 'mc-ico-sm') ?>Ajouter au journal</button>
                </div>
                <div id="fact-ecr-<?= $cote ?>"></div>
                <ul class="guide compta-facture__pourquoi" id="fact-pourquoi-<?= $cote ?>"></ul>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="mc-two">
        <section class="mc-card" aria-labelledby="titre-ristourne">
            <div class="mc-card__head"><h2 class="mc-h" id="titre-ristourne">Ristourne selon un barème</h2></div>
            <p class="mc-meta">Chaque tranche du chiffre d'affaires HT a son taux (comme dans le cours). L'avoir se prépare ensuite en un clic.</p>
            <div class="compta-facture__champs">
                <label class="compta-champ compta-champ--court">CA HT de l'année
                    <input type="text" id="rist-ca" class="mc-input" inputmode="decimal" placeholder="10 000"></label>
                <label class="compta-champ">Calcul
                    <select id="rist-mode" class="mc-select">
                        <option value="tranches">Par tranches (chaque tranche à son taux)</option>
                        <option value="global">Global (tout le CA au taux de la tranche atteinte)</option>
                    </select></label>
            </div>
            <table class="cpt-ecr__lignes compta-facture__lignes">
                <thead><tr><th>CA de</th><th>à</th><th>Taux %</th><th></th></tr></thead>
                <tbody id="rist-tranches"></tbody>
            </table>
            <button type="button" class="mc-link compta-lien" data-f="tranche">+ tranche</button>
            <p class="compta-facture__resultat" id="rist-resultat" aria-live="polite"></p>
            <div class="mc-actions"><button type="button" class="mc-btn mc-btn--sm" data-f="avoir-ristourne">Préparer l'avoir de cette ristourne</button></div>
        </section>

        <section class="mc-card" aria-labelledby="titre-tva">
            <div class="mc-card__head"><h2 class="mc-h" id="titre-tva">Déclaration de TVA (fin de mois)</h2>
                <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-f="tva-soldes" title="Reprend les soldes des comptes de TVA du dossier">
                    <?= icone('synchro', 'mc-ico-sm') ?>Soldes du dossier</button></div>
            <p class="mc-meta">On solde les comptes de TVA du mois : <code>TVA à décaisser = TVA collectée − TVA déductible</code>.
                Si la TVA déductible est plus forte, c'est un crédit de TVA (44567), imputé le mois suivant.</p>
            <div class="compta-facture__champs">
                <label class="compta-champ compta-champ--court">44571 collectée <input type="text" id="tva-collectee" class="mc-input" inputmode="decimal"></label>
                <label class="compta-champ compta-champ--court">44566 déd. sur ABS <input type="text" id="tva-abs" class="mc-input" inputmode="decimal"></label>
                <label class="compta-champ compta-champ--court">44562 déd. sur immo <input type="text" id="tva-immo" class="mc-input" inputmode="decimal"></label>
                <label class="compta-champ compta-champ--court">44567 crédit antérieur <input type="text" id="tva-credit" class="mc-input" inputmode="decimal"></label>
                <label class="compta-champ compta-champ--court">Date <input type="text" id="tva-date" class="mc-input" placeholder="31/03"></label>
            </div>
            <p class="compta-facture__resultat" id="tva-resultat" aria-live="polite"></p>
            <div id="tva-ecriture"></div>
            <div class="mc-actions"><button type="button" class="mc-btn mc-btn--sm" data-f="tva-ajouter"><?= icone('plus', 'mc-ico-sm') ?>Ajouter au journal</button>
                <span class="mc-meta" id="tva-statut" role="status"></span></div>
        </section>
    </div>
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
    <details class="compta-comparer" id="bilan-comparer" hidden>
        <summary>Comparer avec le bilan de départ</summary>
        <div id="bilan-depart"></div>
    </details>
    <div class="mc-actions compta-bilan__pied">
        <button type="button" class="mc-btn mc-btn--sm" data-c="exercice-suivant" title="Nouveau dossier dont le bilan de départ est ce bilan de fin">
            <?= icone('suivant', 'mc-ico-sm') ?>Exercice suivant à partir de ce bilan</button>
    </div>
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
            <li><strong>Bilan de départ</strong> (s'il y en a un) : chaque poste ouvre son compte dans le grand livre,
                du côté où il figure au bilan : actif au débit, passif au crédit (les « à-nouveaux »).</li>
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

    <section class="mc-card mc-aide" id="memo-facturation">
        <div class="mc-card__head"><h2 class="mc-h"><?= icone('calculatrice', 'mc-ico-sm') ?>La facture : du montant brut au net à payer</h2>
            <button type="button" class="mc-btn mc-btn--sm" data-c="aller-factures">Calculer une facture<?= icone('suivant', 'mc-ico-sm') ?></button></div>
        <div class="compta-schema">
            <div>
                <table class="cpt-table compta-memo__table">
                    <tbody>
                        <tr><td>Montant brut HT</td><td>Σ quantité × prix unitaire HT</td></tr>
                        <tr><td>− Réductions commerciales</td><td>remise, rabais, ristourne (en cascade)</td></tr>
                        <tr class="cpt-total"><td>= Net commercial</td><td>montant enregistré en 707 / 607</td></tr>
                        <tr><td>− Escompte de règlement</td><td>% du net commercial</td></tr>
                        <tr class="cpt-total"><td>= Net financier</td><td></td></tr>
                        <tr><td>+ Frais annexes HT</td><td>port, emballages…</td></tr>
                        <tr><td>+ TVA</td><td>taux × (net financier + frais)</td></tr>
                        <tr class="cpt-total"><td>= Montant TTC</td><td></td></tr>
                        <tr><td>− Acompte déjà versé</td><td></td></tr>
                        <tr class="cpt-total"><td>= Net à payer TTC</td><td>411 / 401</td></tr>
                    </tbody>
                </table>
            </div>
            <div>
                <p><b>Remise</b> : réduction pour la qualité du client (grosse commande, fidélité…).</p>
                <p><b>Rabais</b> : réduction exceptionnelle (défaut, retard de livraison…).</p>
                <p><b>Ristourne</b> : réduction sur l'ensemble des achats d'une période (souvent l'année), souvent par tranches de chiffre d'affaires.</p>
                <p><b>Escompte</b> : réduction <em>financière</em> pour paiement rapide → 665 (fournisseur) / 765 (client).</p>
                <p class="mc-meta">Sur la facture, les réductions commerciales ne se comptabilisent pas : on enregistre le net commercial.
                    Après la facture (oubli, ristourne de fin d'année), elles passent par une <b>facture d'avoir</b> (709 / 609).</p>
                <p class="mc-meta">Facture obligatoire entre professionnels, en deux exemplaires, en français. En-tête : vendeur, acheteur,
                    date, numéro, date de règlement ; corps : désignation, prix, réductions, frais, TVA, conditions de vente.</p>
            </div>
        </div>
    </section>

    <section class="mc-card mc-aide">
        <div class="mc-card__head"><h2 class="mc-h">Facturation : chez le fournisseur et chez le client</h2></div>
        <p class="mc-meta">Même opération, vue des deux côtés : ce qui est une vente (7) et une créance (411) pour le fournisseur
            est un achat (6) et une dette (401) pour le client.</p>
        <div class="compta-memo__defile">
        <table class="cpt-table compta-memo__table compta-memo__fact">
            <thead><tr><th>Opération</th><th>Chez le fournisseur</th><th>Chez le client</th></tr></thead>
            <tbody>
                <tr><td><b>Facture de doit</b></td>
                    <td>D 411 Clients (TTC)<br>C 707 Ventes (net commercial HT)<br>C 44571 TVA collectée</td>
                    <td>D 607 Achats (net commercial HT)<br>D 44566 TVA déductible<br>C 401 Fournisseurs (TTC)</td></tr>
                <tr><td><b>… avec escompte</b></td>
                    <td>+ D 665 Escomptes accordés (escompte)</td>
                    <td>+ C 765 Escomptes obtenus (escompte)</td></tr>
                <tr><td><b>Acompte</b> versé à la commande</td>
                    <td>D 512 Banque<br>C 4191 Clients – avances reçues</td>
                    <td>D 4091 Fournisseurs – avances versées<br>C 512 Banque</td></tr>
                <tr><td><b>Facture après acompte</b></td>
                    <td>D 411 (solde) + D 4191 (acompte)<br>C 707 (HT), C 44571 (TVA)</td>
                    <td>D 607 (HT), D 44566 (TVA)<br>C 4091 (acompte) + C 401 (solde)</td></tr>
                <tr><td><b>Avoir</b> : réduction hors facture (ristourne…)</td>
                    <td>D 709 RRR accordés (HT)<br>D 44571 (TVA)<br>C 411 (TTC)</td>
                    <td>D 401 (TTC)<br>C 609 RRR obtenus (HT)<br>C 44566 (TVA)</td></tr>
                <tr><td><b>Avoir</b> : retour de marchandises</td>
                    <td>La vente « à l'envers » :<br>D 707 (HT), D 44571 / C 411</td>
                    <td>L'achat « à l'envers » :<br>D 401 / C 607 (HT), C 44566</td></tr>
                <tr><td><b>Règlement au comptant</b> (2<sup>e</sup> écriture)</td>
                    <td>D 512 Banque ou 530 Caisse<br>C 411 Clients</td>
                    <td>D 401 Fournisseurs<br>C 512 Banque ou 530 Caisse</td></tr>
                <tr><td><b>Achat d'immobilisation</b></td>
                    <td>(une vente normale : 411 / 707, 44571)</td>
                    <td>D 2… Immobilisation (HT)<br>D 44562 TVA sur immobilisations<br>C 404 Fournisseurs d'immobilisations</td></tr>
            </tbody>
        </table>
        </div>
        <p class="mc-meta">Chèque, carte, virement, TIP → 512 Banque ; espèces → 530 Caisse. Retour : les biens sont repris au prix de vente,
            moins la remise accordée sur la facture d'origine.</p>
    </section>

    <div class="mc-two">
        <section class="mc-card mc-aide">
            <div class="mc-card__head"><h2 class="mc-h">Les taux de TVA</h2></div>
            <table class="cpt-table compta-memo__table">
                <thead><tr><th>Taux</th><th>Pour quoi ?</th></tr></thead>
                <tbody>
                    <tr><td><b>20 %</b> normal</td><td>La majorité des biens et services (tout ce qui n'a pas d'autre taux).</td></tr>
                    <tr><td><b>10 %</b> réduit</td><td>Restauration, transports de voyageurs, travaux d'amélioration du logement,
                        produits agricoles non transformés, bois de chauffage, entrées des musées et zoos…</td></tr>
                    <tr><td><b>5,5 %</b> réduit</td><td>Produits alimentaires, livres, abonnements gaz et électricité, cantines scolaires,
                        équipements pour handicapés, billetterie du spectacle vivant, logements sociaux…</td></tr>
                    <tr><td><b>2,1 %</b> particulier</td><td>Médicaments remboursables, presse, redevance télé, certains spectacles…</td></tr>
                </tbody>
            </table>
            <p class="mc-meta">La TVA est un impôt sur la consommation : l'entreprise collecte la TVA sur ses ventes, déduit celle de ses
                achats, et reverse la différence à l'État.</p>
        </section>

        <section class="mc-card mc-aide">
            <div class="mc-card__head"><h2 class="mc-h">Déclaration de TVA (réel normal)</h2></div>
            <p><code>TVA à décaisser = TVA collectée − TVA déductible</code></p>
            <p class="mc-meta">Chaque mois (déclaration envoyée le mois suivant, souvent enregistrée en fin de mois), on solde les comptes de TVA :</p>
            <table class="cpt-table compta-memo__table">
                <thead><tr><th></th><th>TVA à décaisser</th><th>Crédit de TVA</th></tr></thead>
                <tbody>
                    <tr><td>Débit</td><td>44571 TVA collectée</td><td>44571 TVA collectée<br>44567 Crédit de TVA</td></tr>
                    <tr><td>Crédit</td><td>44566 TVA déd. sur ABS<br>44562 TVA déd. sur immo<br>44551 TVA à décaisser</td>
                        <td>44566 TVA déd. sur ABS<br>44562 TVA déd. sur immo</td></tr>
                </tbody>
            </table>
            <p class="mc-meta">Paiement le mois suivant : D 44551 / C 512. Un crédit de TVA (44567) se déduit à la déclaration suivante.</p>
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
                <p>Autre taux t : <code>TTC = HT × (1 + t)</code> (10 % → × 1,1 ; 5,5 % → × 1,055)</p>
                <p><code>TVA à décaisser = TVA collectée (44571) − TVA déductible (44566 + 44562)</code></p></div>
            <div><h3>Facture</h3>
                <p><code>Net commercial = Brut HT − Remises</code> (plusieurs remises : en cascade)</p>
                <p><code>Net financier = Net commercial − Escompte</code></p>
                <p><code>Net à payer = (Net financier + Port) × (1 + TVA) − Acompte</code></p>
                <p>Ristourne par tranches : chaque part du CA à son taux (ex. <code>4 000 × 3 % + 2 000 × 5 % = 220</code>)</p></div>
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
            <li>Sur une facture, les <strong>remises, rabais, ristournes ne se comptabilisent pas</strong> : on enregistre le net commercial.
                Seul l'<strong>escompte</strong> (réduction financière) a son compte : 665 chez le fournisseur, 765 chez le client.</li>
            <li>La <strong>TVA se calcule sur le net financier</strong> (après escompte), frais de port compris.</li>
            <li>Un <strong>acompte</strong> n'est pas une vente : 4191 chez le fournisseur, 4091 chez le client ; à la facture,
                le 411 / 401 ne reçoit que le solde.</li>
            <li><strong>Avoir</strong> : réduction après la facture → 709 / 609 ; retour de marchandises → la facture « à l'envers » (707 / 607).</li>
            <li>Immobilisation achetée : TVA en <strong>44562</strong> (pas 44566) et dette en <strong>404</strong> (pas 401).</li>
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
