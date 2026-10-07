/* ============================================================
   Atelier comptable (compta.php) : interface.
   - dossiers (un par exercice) enregistrés automatiquement (api/compta.php)
   - journal modifiable : saisie rapide « 607 / 401 14000 », opérations
     courantes expliquées, écritures à plusieurs lignes
   - onglets calculés à l'affichage : grand livre, balance, compte de
     résultat, bilan, analyse (calculs : compta-moteur.js)
   - aide-mémoire : opérations courantes et plan comptable
   - « Créer une note » : tout le dossier en Markdown dans une note
   ============================================================ */

(function () {
    const C = window.Compta;
    if (!C || !document.querySelector('[data-panneau="journal"]')) return;

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const ech = C.echapper;
    const sansAccents = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const URL_API = 'api/compta.php?action=';

    const etat = { dossiers: [], id: null, d: null, onglet: 'journal', modifie: false, minuteur: null };
    const elStatut = $('#compta-statut');

    function statut(texte, classe = '') {
        elStatut.textContent = texte;
        elStatut.className = 'statut-save ' + classe;
    }
    const dossierVide = (titre) => ({ version: 1, titre: titre || 'Nouveau dossier', entreprise: '',
        cloture: '31/12/N', comptes: {}, ecritures: [] });

    // =========================================================
    //  Dossiers : liste, ouverture, enregistrement automatique
    // =========================================================
    async function listerDossiers() {
        try {
            const rep = await (await fetch(URL_API + 'lister')).json();
            etat.dossiers = rep.dossiers || [];
        } catch (e) {
            etat.dossiers = [];
            statut('Hors ligne', 'erreur');
        }
        dessinerSelecteur();
    }

    function dessinerSelecteur() {
        const sel = $('#compta-dossier');
        sel.innerHTML = etat.dossiers.length
            ? etat.dossiers.map((d) => '<option value="' + d.id + '"' + (d.id === etat.id ? ' selected' : '') + '>'
                + ech(d.titre) + '</option>').join('')
            : '<option value="">Aucun dossier</option>';
        sel.disabled = !etat.dossiers.length;
    }

    async function ouvrir(id) {
        await sauvegarder();
        try {
            const rep = await (await fetch(URL_API + 'charger&id=' + id)).json();
            if (!rep.id) throw new Error(rep.erreur || 'introuvable');
            etat.id = rep.id;
            etat.d = Object.assign(dossierVide(rep.titre), rep.donnees || {}, { titre: rep.titre });
            if (!Array.isArray(etat.d.ecritures)) etat.d.ecritures = [];
            if (!etat.d.comptes || Array.isArray(etat.d.comptes)) etat.d.comptes = {};
            try { localStorage.setItem('compta-dossier', String(rep.id)); } catch (e) {}
            statut('');
        } catch (e) {
            statut('Impossible d\'ouvrir le dossier', 'erreur');
            return;
        }
        dessinerSelecteur();
        afficherDossier();
    }

    async function creer(donnees) {
        await sauvegarder();
        try {
            const rep = await apiJson(URL_API + 'creer', 'POST', { titre: donnees.titre, donnees });
            if (!rep.id) throw new Error(rep.erreur);
            await listerDossiers();
            await ouvrir(rep.id);
            return true;
        } catch (e) {
            alert('Impossible de créer le dossier (connexion perdue ?).');
            return false;
        }
    }

    function modifie() {
        etat.modifie = true;
        statut('Modifié…', 'modifie');
        clearTimeout(etat.minuteur);
        etat.minuteur = setTimeout(sauvegarder, 900);
    }

    async function sauvegarder() {
        clearTimeout(etat.minuteur);
        if (!etat.modifie || !etat.id) return;
        etat.modifie = false;
        statut('Enregistrement…');
        try {
            const rep = await apiJson(URL_API + 'enregistrer', 'POST', { id: etat.id, titre: etat.d.titre, donnees: etat.d });
            if (!rep.ok) throw new Error(rep.erreur);
            statut('Enregistré à ' + rep.heure, 'ok');
            const ligne = etat.dossiers.find((d) => d.id === etat.id);
            if (ligne && ligne.titre !== etat.d.titre) { ligne.titre = etat.d.titre; dessinerSelecteur(); }
        } catch (e) {
            etat.modifie = true;
            statut('Non enregistré (hors ligne ?)', 'erreur');
        }
    }

    // Page quittée avec des modifications en attente : envoi qui survit à la fermeture.
    window.addEventListener('pagehide', () => {
        if (!etat.modifie || !etat.id) return;
        fetch(URL_API + 'enregistrer', {
            method: 'POST', keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify({ id: etat.id, titre: etat.d.titre, donnees: etat.d }),
        }).catch(() => {});
    });

    function afficherDossier() {
        const present = !!etat.d;
        $$('[data-dossier-requis]').forEach((el) => {
            if (el.tagName === 'BUTTON') el.disabled = !present; else el.hidden = !present;
        });
        if (present) {
            $('#compta-titre').value = etat.d.titre || '';
            $('#compta-entreprise').value = etat.d.entreprise || '';
            $('#compta-cloture').value = etat.d.cloture || '';
            if (etat.d.matiere_id !== undefined) $('#compta-matiere').value = etat.d.matiere_id || '';
            dessinerJournal();
        }
        afficherOnglet(etat.onglet);
    }

    // =========================================================
    //  Onglets
    // =========================================================
    function afficherOnglet(nom) {
        if (!$('[data-panneau="' + nom + '"]')) nom = 'journal';
        etat.onglet = nom;
        $$('.compta-onglets [data-onglet]').forEach((b) => {
            const actif = b.dataset.onglet === nom;
            b.classList.toggle('actif', actif);
            b.setAttribute('aria-selected', actif ? 'true' : 'false');
        });
        const sansDossier = !etat.d && nom !== 'memo';
        $('#compta-vide').hidden = !sansDossier;
        $$('.compta-panneau').forEach((p) => { p.hidden = sansDossier || p.dataset.panneau !== nom; });
        if (!sansDossier) dessinerOnglet(nom);
        if (history.replaceState) history.replaceState(null, '', '#' + nom);
    }

    function dessinerOnglet(nom) {
        if (nom === 'memo' || !etat.d) return;
        const calc = C.calculer(etat.d);
        const cloture = etat.d.cloture || '31/12/N';
        if (nom === 'journal') majResume(calc);
        if (nom === 'grand-livre') dessinerGrandLivre(calc);
        if (nom === 'balance') {
            $('#balance').innerHTML = C.htmlBalance(calc.balance);
            $('#balance-date').textContent = 'au ' + cloture;
        }
        if (nom === 'resultat') $('#resultat').innerHTML = C.htmlDoc(C.docResultat(calc.resultat, 'Compte de résultat au ' + cloture));
        if (nom === 'bilan') $('#bilan').innerHTML = C.htmlDoc(C.docBilan(calc.bilan, 'Bilan au ' + cloture));
        if (nom === 'analyse') dessinerAnalyse(calc);
    }

    $('.compta-onglets').addEventListener('click', (e) => {
        const b = e.target.closest('[data-onglet]');
        if (b) afficherOnglet(b.dataset.onglet);
    });

    // =========================================================
    //  Journal modifiable
    // =========================================================
    const montantSaisi = (n) => (n == null || n === '' ? '' : C.fmt(n));
    const dateParDefaut = () => {
        const e = etat.d.ecritures;
        return (e.length && e[e.length - 1].date) || $('#op-date').value.trim() || '31/12';
    };

    function htmlLigne(l, j) {
        const n = C.normaliserCompte(l.compte);
        return '<tr data-l="' + j + '">'
            + '<td><input class="cpt-in cpt-in--compte" data-champ="compte" list="pcg-liste" inputmode="numeric" value="'
            + ech(l.compte || '') + '" placeholder="N° ou nom" aria-label="Numéro de compte (ou nom : banque, ventes…)"></td>'
            + '<td><input class="cpt-in" data-champ="nom" value="' + ech(n ? C.nomCompte(n, etat.d.comptes) : '')
            + '" placeholder="Intitulé" aria-label="Intitulé du compte"></td>'
            + '<td><input class="cpt-in cpt-in--mt" data-champ="debit" inputmode="decimal" value="' + ech(montantSaisi(l.debit))
            + '" aria-label="Débit"></td>'
            + '<td><input class="cpt-in cpt-in--mt" data-champ="credit" inputmode="decimal" value="' + ech(montantSaisi(l.credit))
            + '" aria-label="Crédit"></td>'
            + '<td><button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-c="suppr-ligne" title="Retirer la ligne" aria-label="Retirer la ligne">'
            + icone('fermer', 'mc-ico-sm') + '</button></td></tr>';
    }

    function htmlEcriture(ecr, i) {
        return '<article class="cpt-ecr" data-e="' + i + '">'
            + '<div class="cpt-ecr__tete"><span class="cpt-ecr__n">' + (i + 1) + '</span>'
            + '<input class="cpt-in cpt-ecr__date" data-champ="date" value="' + ech(ecr.date || '') + '" placeholder="Date" aria-label="Date">'
            + '<input class="cpt-in cpt-ecr__lib" data-champ="libelle" value="' + ech(ecr.libelle || '')
            + '" placeholder="Libellé de l\'opération" aria-label="Libellé">'
            + '<span class="cpt-ecr__etat"></span>'
            + '<button type="button" class="mc-btn mc-btn--ghost mc-btn--sm" data-c="suppr-ecriture" title="Supprimer l\'écriture" aria-label="Supprimer l\'écriture">'
            + icone('corbeille', 'mc-ico-sm') + '</button></div>'
            + '<div class="cpt-ecr__cadre"><table class="cpt-ecr__lignes"><thead><tr><th>Compte</th><th>Intitulé</th><th>Débit</th><th>Crédit</th><th></th></tr></thead>'
            + '<tbody>' + (ecr.lignes || []).map(htmlLigne).join('') + '</tbody></table></div>'
            + '<button type="button" class="mc-link compta-lien" data-c="ajout-ligne">+ ligne</button>'
            + '</article>';
    }

    function dessinerJournal(focus) {
        const zone = $('#ecritures');
        const ecritures = etat.d.ecritures;
        zone.innerHTML = ecritures.length ? ecritures.map(htmlEcriture).join('')
            : '<div class="mc-empty"><p>Aucune écriture. Utilise la saisie rapide ou une opération courante ci-dessus.</p></div>';
        ecritures.forEach((_, i) => majEtatEcriture(i));
        majResume();
        if (!$('#journal-papier').hidden) dessinerPapier();
        if (focus) {
            const el = zone.querySelector(focus);
            if (el) { el.focus(); if (el.select) el.select(); }
        }
    }

    function majEtatEcriture(i) {
        const art = $('#ecritures [data-e="' + i + '"]');
        if (!art) return;
        const e = C.ecartEcriture(etat.d.ecritures[i]);
        const el = art.querySelector('.cpt-ecr__etat');
        if (!e.debit && !e.credit) { el.textContent = 'Vide'; el.className = 'cpt-ecr__etat'; return; }
        el.textContent = e.ecart ? 'Écart ' + C.fmt(Math.abs(e.ecart)) + (e.ecart > 0 ? ' (débit > crédit)' : ' (crédit > débit)')
            : 'Équilibrée · ' + C.fmt(e.debit);
        el.className = 'cpt-ecr__etat ' + (e.ecart ? 'ko' : 'ok');
    }

    function majResume(calc) {
        const ecritures = etat.d.ecritures;
        const bal = (calc || C.calculer(etat.d)).balance;
        const ko = ecritures.filter((ecr) => C.ecartEcriture(ecr).ecart).length;
        $('#journal-resume').textContent = ecritures.length
            ? ecritures.length + ' écriture' + (ecritures.length > 1 ? 's' : '') + ' · débit ' + C.fmt(bal.sommesD)
              + (ko ? ' · ' + ko + ' déséquilibrée' + (ko > 1 ? 's' : '') : ' = crédit ✓')
            : '';
    }

    function dessinerPapier() {
        $('#journal-papier').innerHTML = C.htmlJournal(etat.d.ecritures, etat.d.comptes);
    }

    function ajouterEcriture(ecr, focus) {
        etat.d.ecritures.push(ecr);
        modifie();
        dessinerJournal(focus);
        const art = $('#ecritures [data-e="' + (etat.d.ecritures.length - 1) + '"]');
        if (art) {
            art.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            art.classList.add('cpt-ecr--nouvelle');
            setTimeout(() => art.classList.remove('cpt-ecr--nouvelle'), 1200);
        }
    }

    // Nom d'un compte modifié : propre au dossier (ex. « 16 Emprunt » au lieu de l'intitulé du PCG).
    function renommerCompte(num, nom) {
        if (!num) return;
        nom = nom.trim();
        const officiel = C.nomCompte(num);
        if (!nom || nom === officiel) delete etat.d.comptes[num]; else etat.d.comptes[num] = nom;
        $$('#ecritures tr').forEach((tr) => {
            const c = tr.querySelector('[data-champ="compte"]'), n = tr.querySelector('[data-champ="nom"]');
            if (c && n && n !== document.activeElement && C.normaliserCompte(c.value) === num) n.value = C.nomCompte(num, etat.d.comptes);
        });
    }

    const zoneEcritures = $('#ecritures');
    zoneEcritures.addEventListener('input', (e) => {
        const champ = e.target.dataset.champ;
        const art = e.target.closest('[data-e]');
        if (!champ || !art) return;
        const i = +art.dataset.e, ecr = etat.d.ecritures[i];
        const tr = e.target.closest('[data-l]');
        if (!tr) {
            ecr[champ] = e.target.value;
        } else {
            const l = ecr.lignes[+tr.dataset.l];
            if (champ === 'compte') {
                l.compte = e.target.value.trim();
                const n = C.normaliserCompte(l.compte);
                tr.querySelector('[data-champ="nom"]').value = n ? C.nomCompte(n, etat.d.comptes) : '';
            } else if (champ === 'nom') {
                renommerCompte(C.normaliserCompte(l.compte), e.target.value);
            } else {
                const brut = e.target.value.trim();
                const v = C.parseMontant(brut);
                e.target.setAttribute('aria-invalid', brut && v == null ? 'true' : 'false');
                l[champ] = v;
            }
        }
        majEtatEcriture(i);
        majResume();
        modifie();
    });

    zoneEcritures.addEventListener('change', (e) => {
        const champ = e.target.dataset.champ;
        const tr = e.target.closest('[data-l]');
        if (!tr) return;
        const i = +e.target.closest('[data-e]').dataset.e, ecr = etat.d.ecritures[i];
        const l = ecr.lignes[+tr.dataset.l];
        if (champ === 'debit' || champ === 'credit') {
            if (l[champ] != null) e.target.value = montantSaisi(l[champ]);
        }
        // Compte tapé en lettres (« banque », « ventes ») : remplacé par son numéro.
        if (champ === 'compte' && l.compte && !/^\d/.test(l.compte)) {
            const trouve = C.chercherCompte(l.compte, etat.d.comptes);
            if (trouve) {
                l.compte = trouve;
                e.target.value = trouve;
                tr.querySelector('[data-champ="nom"]').value = C.nomCompte(trouve, etat.d.comptes);
                modifie();
            }
        }
        // Nouveau compte sur une ligne sans montant : on propose le montant qui équilibre l'écriture.
        if (champ === 'compte' && C.normaliserCompte(l.compte) && l.debit == null && l.credit == null) {
            const ecart = C.ecartEcriture(ecr).ecart;
            if (ecart) {
                const cote = ecart > 0 ? 'credit' : 'debit';
                l[cote] = Math.abs(ecart);
                tr.querySelector('[data-champ="' + cote + '"]').value = montantSaisi(l[cote]);
                majEtatEcriture(i);
                majResume();
                modifie();
            }
        }
    });

    // Entrée dans la dernière ligne d'une écriture : nouvelle ligne.
    zoneEcritures.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const tr = e.target.closest('[data-l]');
        if (!tr) return;
        e.preventDefault();
        const i = +e.target.closest('[data-e]').dataset.e, ecr = etat.d.ecritures[i];
        const j = +tr.dataset.l;
        if (j === ecr.lignes.length - 1) {
            ecr.lignes.push({ compte: '', debit: null, credit: null });
            modifie();
            dessinerJournal('[data-e="' + i + '"] [data-l="' + (j + 1) + '"] [data-champ="compte"]');
        } else {
            const suivant = $('#ecritures [data-e="' + i + '"] [data-l="' + (j + 1) + '"] [data-champ="' + e.target.dataset.champ + '"]');
            if (suivant) suivant.focus();
        }
    });

    zoneEcritures.addEventListener('click', (e) => {
        const b = e.target.closest('[data-c]');
        if (!b) return;
        const i = +b.closest('[data-e]').dataset.e, ecr = etat.d.ecritures[i];
        if (b.dataset.c === 'ajout-ligne') {
            ecr.lignes.push({ compte: '', debit: null, credit: null });
            modifie();
            dessinerJournal('[data-e="' + i + '"] [data-l="' + (ecr.lignes.length - 1) + '"] [data-champ="compte"]');
        }
        if (b.dataset.c === 'suppr-ligne') {
            ecr.lignes.splice(+b.closest('[data-l]').dataset.l, 1);
            modifie();
            dessinerJournal();
        }
        if (b.dataset.c === 'suppr-ecriture') {
            const vide = !ecr.lignes.some((l) => l.compte || l.debit || l.credit);
            if (!vide && !confirm('Supprimer l\'écriture n° ' + (i + 1) + ' (« ' + (ecr.libelle || 'sans libellé') + ' ») ?')) return;
            etat.d.ecritures.splice(i, 1);
            modifie();
            dessinerJournal();
        }
    });

    // ---------- Saisie rapide ----------
    function decrireLignes(lignes) {
        return lignes.map((l) => (l.debit ? 'Débit ' : 'Crédit ') + l.compte + ' ' + C.nomCompte(l.compte, etat.d && etat.d.comptes)
            + ' ' + C.fmt(l.debit || l.credit)).join(' · ');
    }
    $('#saisie-rapide').addEventListener('input', (e) => {
        const v = e.target.value.trim();
        const r = v && C.saisieRapide(v, etat.d && etat.d.comptes);
        $('#rapide-apercu').textContent = !v ? '' : r ? '→ ' + decrireLignes(r.lignes) : 'Format non reconnu (ex. 512 / 707 45000 Ventes)';
        $('#rapide-apercu').className = 'compta-apercu ' + (v && !r ? 'ko' : '');
    });
    $('#form-rapide').addEventListener('submit', (e) => {
        e.preventDefault();
        if (!etat.d) return;
        const champ = $('#saisie-rapide');
        const r = C.saisieRapide(champ.value, etat.d.comptes);
        if (!r) { champ.focus(); $('#rapide-apercu').textContent = 'Format attendu : 607 / 401 14000 Libellé'; return; }
        if (!r.date) r.date = dateParDefaut();
        ajouterEcriture(r);
        champ.value = '';
        $('#rapide-apercu').textContent = 'Ajoutée : écriture n° ' + etat.d.ecritures.length;
        champ.focus();
    });

    // ---------- Opérations courantes ----------
    function remplirOperations() {
        const groupes = {};
        C.OPERATIONS.forEach((op, i) => { (groupes[op.groupe] = groupes[op.groupe] || []).push([op, i]); });
        $('#op-type').innerHTML = '<option value="">Choisir une opération…</option>'
            + Object.keys(groupes).map((g) => '<optgroup label="' + ech(g) + '">'
                + groupes[g].map(([op, i]) => '<option value="' + i + '">' + ech(op.libelle) + '</option>').join('')
                + '</optgroup>').join('');
    }
    function majPourquoi() {
        const op = C.OPERATIONS[$('#op-type').value];
        const el = $('#op-pourquoi');
        $('#op-montant').placeholder = op && op.tva ? 'Montant HT' : 'Montant';
        if (!op) { el.innerHTML = ''; return; }
        el.innerHTML = op.lignes.map(([compte, sens, part]) => '<span class="cpt-sens cpt-sens--' + sens + '">'
            + (sens === 'D' ? 'Débit' : 'Crédit') + ' <b>' + ech(compte) + '</b> ' + ech(C.nomCompte(compte))
            + (part !== 'm' ? ' <small>(' + part.toUpperCase() + ')</small>' : '') + '</span>').join('')
            + '<span class="compta-pourquoi__texte">' + ech(op.pourquoi) + '</span>';
    }
    $('#op-type').addEventListener('change', () => { majPourquoi(); $('#op-montant').focus(); });
    $('#form-operation').addEventListener('submit', (e) => {
        e.preventDefault();
        if (!etat.d) return;
        const op = C.OPERATIONS[$('#op-type').value];
        const montant = C.parseMontant($('#op-montant').value);
        if (!op) { $('#op-type').focus(); return; }
        if (!montant) { $('#op-montant').focus(); return; }
        ajouterEcriture(C.ecritureOperation(op, montant, $('#op-date').value.trim() || dateParDefaut()));
        $('#op-montant').value = '';
        $('#op-montant').focus();
    });

    // =========================================================
    //  Grand livre, analyse
    // =========================================================
    function dessinerGrandLivre(calc) {
        const q = sansAccents($('#gl-filtre').value.trim());
        const classe = q.match(/^classe\s*(\d)$/);
        const comptes = calc.comptes.filter((c) => !q || (classe ? c.num[0] === classe[1]
            : c.num.startsWith(q) || sansAccents(c.nom).includes(q)));
        $('#grand-livre').innerHTML = C.htmlComptesT(comptes);
    }
    $('#gl-filtre').addEventListener('input', () => etat.d && dessinerGrandLivre(C.calculer(etat.d)));

    function dessinerAnalyse(calc) {
        const a = calc.analyse;
        const tuile = (titre, valeur, formule) => '<div class="compta-kpi"><p class="mc-eyebrow">' + titre + '</p>'
            + '<p class="compta-kpi__val ' + (valeur < 0 ? 'neg' : '') + '">' + C.fmt(valeur) + ' €</p>'
            + '<p class="mc-meta">' + formule + '</p></div>';
        $('#analyse-equilibre').innerHTML = tuile('Fonds de roulement', a.fr, 'Capitaux permanents − actif immobilisé')
            + tuile('Besoin en fonds de roulement', a.bfr, '(Stocks + créances) − dettes d\'exploitation')
            + tuile('Trésorerie nette', a.tn, 'FR − BFR = disponibilités − découverts');
        const phrases = [
            a.fr >= 0 ? 'Les ressources stables financent toutes les immobilisations et dégagent ' + C.fmt(a.fr) + ' € pour l\'exploitation.'
                : 'Fonds de roulement négatif : une partie des immobilisations est financée par des dettes à court terme (situation risquée).',
            a.bfr > 0 ? 'L\'exploitation immobilise ' + C.fmt(a.bfr) + ' € (stocks et crédits clients supérieurs aux dettes fournisseurs).'
                : 'Ressource en fonds de roulement : les fournisseurs financent l\'exploitation (fréquent dans la grande distribution).',
            a.tn >= 0 ? 'Trésorerie positive : le fonds de roulement couvre le besoin en fonds de roulement.'
                : 'Trésorerie négative : l\'entreprise dépend de ses concours bancaires.',
        ];
        if (a.ecartSig) phrases.push('⚠ Les SIG donnent un résultat différent de ' + C.fmt(a.ecartSig) + ' € : un compte de classe 6 ou 7 a un numéro inhabituel.');
        $('#analyse-commentaire').textContent = phrases.join(' ');

        $('#analyse-sig').innerHTML = '<table class="cpt-table compta-sig"><tbody>' + a.sig.map((l) =>
            '<tr class="' + (l.niveau ? '' : 'cpt-total') + '"><td>' + ech(l.libelle)
            + (l.aide ? '<small>' + ech(l.aide) + '</small>' : '') + '</td><td class="cpt-mt">' + C.fmt(l.valeur) + '</td></tr>').join('')
            + '</tbody></table>';
        $('#analyse-ratios').innerHTML = '<table class="cpt-table compta-sig"><tbody>' + a.ratios.map((r) =>
            '<tr><td>' + ech(r.libelle) + '<small>' + ech(r.formule) + (r.aide ? ' — ' + ech(r.aide) : '') + '</small></td>'
            + '<td class="cpt-mt">' + (r.valeur == null ? '—' : C.fmt(r.valeur) + (r.unite ? ' ' + (r.unite === '%' ? '%' : r.unite) : ''))
            + '</td></tr>').join('') + '</tbody></table>';
    }

    // =========================================================
    //  Aide-mémoire : opérations courantes, plan comptable
    // =========================================================
    function remplirMemo() {
        let groupe = '';
        $('#memo-operations').innerHTML = '<table class="cpt-table compta-memo__table compta-ops"><thead><tr><th>Opération</th><th>Débit</th><th>Crédit</th><th>Pourquoi</th></tr></thead><tbody>'
            + C.OPERATIONS.map((op, i) => {
                const cote = (s) => op.lignes.filter((l) => l[1] === s).map(([n]) => '<b>' + ech(n) + '</b> ' + ech(C.nomCompte(n))).join('<br>');
                const tete = op.groupe !== groupe ? '<tr class="cpt-doc__rub"><th colspan="4">' + ech(groupe = op.groupe) + '</th></tr>' : '';
                return tete + '<tr data-op="' + i + '" tabindex="0" title="Préparer cette opération dans le journal"><td>' + ech(op.libelle)
                    + '</td><td>' + cote('D') + '</td><td>' + cote('C') + '</td><td class="mc-meta">' + ech(op.pourquoi) + '</td></tr>';
            }).join('') + '</tbody></table>';

        const parClasse = {};
        Object.keys(C.PCG).sort().forEach((n) => { (parClasse[n[0]] = parClasse[n[0]] || []).push(n); });
        $('#memo-pcg').innerHTML = Object.keys(parClasse).map((k) => '<div class="ref-groupe"><h3>Classe ' + k + ' · '
            + ech(C.CLASSES[k]) + '</h3><table class="ref-table">' + parClasse[k].map((n) =>
            '<tr data-cherche="' + ech(sansAccents(n + ' ' + C.PCG[n])) + '"><td class="cpt-mono compta-pcg__num" style="padding-left:'
            + ((n.length - 2) * 10 + 6) + 'px">' + n + '</td><td>' + ech(C.PCG[n]) + '</td></tr>').join('') + '</table></div>').join('');

        $('#pcg-liste').innerHTML = Object.keys(C.PCG).sort().map((n) =>
            '<option value="' + n + '">' + ech(C.PCG[n]) + '</option>').join('');
    }
    $('#pcg-recherche').addEventListener('input', (e) => {
        const q = sansAccents(e.target.value.trim());
        $$('#memo-pcg .ref-groupe').forEach((g) => {
            let n = 0;
            g.querySelectorAll('tr').forEach((tr) => {
                const ok = !q || tr.dataset.cherche.includes(q) || tr.dataset.cherche.startsWith(q);
                tr.hidden = !ok;
                if (ok) n++;
            });
            g.hidden = !n;
        });
    });
    function preparerOperation(ligne) {
        if (!ligne) return;
        $('#op-type').value = ligne.dataset.op;
        majPourquoi();
        afficherOnglet('journal');
        if (etat.d) { $('#op-montant').focus(); $('#form-operation').scrollIntoView({ block: 'center' }); }
    }
    $('#memo-operations').addEventListener('click', (e) => preparerOperation(e.target.closest('[data-op]')));
    $('#memo-operations').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') preparerOperation(e.target.closest('[data-op]'));
    });

    // =========================================================
    //  Barre du dossier
    // =========================================================
    $('#compta-dossier').addEventListener('change', (e) => { if (e.target.value) ouvrir(+e.target.value); });
    [['#compta-titre', 'titre'], ['#compta-entreprise', 'entreprise'], ['#compta-cloture', 'cloture']].forEach(([sel, cle]) => {
        $(sel).addEventListener('input', (e) => { if (etat.d) { etat.d[cle] = e.target.value; modifie(); } });
    });
    $('#compta-matiere').addEventListener('change', (e) => {
        if (etat.d) { etat.d.matiere_id = parseInt(e.target.value, 10) || 0; modifie(); }
    });

    async function creerNote(bouton) {
        bouton.disabled = true;
        await sauvegarder();
        const matiere = parseInt($('#compta-matiere').value, 10) || 0;
        const titre = etat.d.titre || 'Dossier comptable';
        try {
            const rep = await apiJson('api/notes.php?action=creer', 'POST', { titre, matiere_id: matiere });
            if (!rep.id) throw new Error(rep.erreur);
            const maj = await apiJson('api/notes.php?action=maj', 'POST',
                { id: rep.id, titre, contenu: C.versMarkdown(etat.d), matiere_id: matiere });
            if (!maj.ok) throw new Error(maj.erreur);
            location.href = 'note.php?id=' + rep.id;
        } catch (e) {
            alert('Impossible de créer la note (connexion perdue ?).');
            bouton.disabled = false;
        }
    }

    document.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-c]');
        if (!b || b.closest('#ecritures')) return;
        switch (b.dataset.c) {
            case 'nouveau':
                if (await creer(dossierVide())) { $('#compta-titre').focus(); $('#compta-titre').select(); }
                break;
            case 'exemple':
                await creer(C.exempleBricoDepot());
                break;
            case 'ecriture-vide':
                if (!etat.d) return;
                ajouterEcriture({ date: dateParDefaut(), libelle: '', lignes: [
                    { compte: '', debit: null, credit: null }, { compte: '', debit: null, credit: null }] },
                    '[data-e="' + etat.d.ecritures.length + '"] [data-champ="libelle"]');
                break;
            case 'vue-journal': {
                const p = $('#journal-papier');
                p.hidden = !p.hidden;
                b.textContent = p.hidden ? 'Voir le journal « sur papier »' : 'Masquer le journal « sur papier »';
                if (!p.hidden) { dessinerPapier(); p.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
                break;
            }
            case 'note':
                if (etat.d) creerNote(b);
                break;
            case 'imprimer':
                window.print();
                break;
            case 'supprimer': {
                if (!etat.d || !confirm('Supprimer définitivement le dossier « ' + etat.d.titre + ' » ?')) return;
                clearTimeout(etat.minuteur);
                etat.modifie = false;
                await apiJson(URL_API + 'supprimer', 'POST', { id: etat.id }).catch(() => {});
                etat.id = null;
                etat.d = null;
                await listerDossiers();
                if (etat.dossiers.length) await ouvrir(etat.dossiers[0].id);
                else { afficherDossier(); statut('Dossier supprimé'); }
                break;
            }
        }
    });

    // Impression : le journal s'imprime dans sa présentation « papier ».
    window.addEventListener('beforeprint', () => { if (etat.d) dessinerPapier(); });

    // =========================================================
    //  Démarrage
    // =========================================================
    (async function () {
        remplirOperations();
        remplirMemo();
        const hash = location.hash.slice(1);
        if (hash) etat.onglet = hash;
        await listerDossiers();
        let id = parseInt(new URLSearchParams(location.search).get('id'), 10);
        if (!id) { try { id = parseInt(localStorage.getItem('compta-dossier'), 10); } catch (e) {} }
        if (!etat.dossiers.some((d) => d.id === id)) id = etat.dossiers.length ? etat.dossiers[0].id : null;
        if (id) await ouvrir(id); else afficherDossier();
    })();
})();
