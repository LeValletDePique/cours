/* ============================================================
   Comptabilité (Gestion de l'entreprise) : moteur partagé.
   Utilisé par l'atelier comptable (compta.php) ET par le rendu des
   notes (blocs ```journal, ```comptes, ```balance, ```resultat, ```bilan).

   - plan comptable général (PCG) : numéros et intitulés usuels
   - à partir des écritures : grand livre, balance, compte de résultat,
     bilan, analyse (FR / BFR / trésorerie, SIG, ratios)
   - rendu HTML de ces documents (comptes en T, tableaux)
   - lecture des blocs Markdown et export d'un dossier en Markdown
   - opérations courantes (quel compte débiter / créditer, et pourquoi)

   Montants : calculs en centimes (entiers) pour éviter les erreurs
   d'arrondi des nombres à virgule.
   Expose window.Compta (et module.exports sous Node pour les tests).
   ============================================================ */

(function (racine) {
    'use strict';

    // =========================================================
    //  Plan comptable général (comptes les plus utilisés en cours)
    // =========================================================
    const CLASSES = {
        1: 'Comptes de capitaux',
        2: "Comptes d'immobilisations",
        3: 'Comptes de stocks et en-cours',
        4: 'Comptes de tiers',
        5: 'Comptes financiers',
        6: 'Comptes de charges',
        7: 'Comptes de produits',
    };

    const PCG = {
        // --- Classe 1 : capitaux ---
        10: 'Capital', 101: 'Capital social', 1013: 'Capital souscrit – appelé, versé',
        104: 'Primes liées au capital', 106: 'Réserves', 1061: 'Réserve légale', 1063: 'Réserves statutaires',
        1068: 'Autres réserves', 108: "Compte de l'exploitant",
        11: 'Report à nouveau', 110: 'Report à nouveau (solde créditeur)', 119: 'Report à nouveau (solde débiteur)',
        12: "Résultat de l'exercice", 120: "Résultat de l'exercice (bénéfice)", 129: "Résultat de l'exercice (perte)",
        13: "Subventions d'investissement", 131: "Subventions d'équipement",
        139: 'Subventions inscrites au compte de résultat',
        14: 'Provisions réglementées', 145: 'Amortissements dérogatoires',
        15: 'Provisions pour risques et charges', 151: 'Provisions pour risques', 158: 'Autres provisions pour charges',
        16: 'Emprunts et dettes assimilées', 164: 'Emprunts auprès des établissements de crédit',
        165: 'Dépôts et cautionnements reçus', 168: 'Autres emprunts et dettes assimilées',
        1688: 'Intérêts courus sur emprunts',
        17: 'Dettes rattachées à des participations', 18: 'Comptes de liaison des établissements',

        // --- Classe 2 : immobilisations ---
        20: 'Immobilisations incorporelles', 201: "Frais d'établissement",
        203: 'Frais de recherche et de développement', 205: 'Concessions, brevets, licences, logiciels',
        206: 'Droit au bail', 207: 'Fonds commercial',
        21: 'Immobilisations corporelles', 211: 'Terrains', 212: 'Agencements et aménagements de terrains',
        213: 'Constructions', 215: 'Installations techniques, matériel et outillage',
        2154: 'Matériel industriel', 2155: 'Outillage industriel',
        218: 'Autres immobilisations corporelles', 2181: 'Installations générales, agencements',
        2182: 'Matériel de transport', 2183: 'Matériel de bureau et informatique', 2184: 'Mobilier',
        23: 'Immobilisations en cours', 231: 'Immobilisations corporelles en cours',
        26: 'Participations et créances rattachées', 261: 'Titres de participation',
        27: 'Autres immobilisations financières', 271: 'Titres immobilisés', 274: 'Prêts',
        275: 'Dépôts et cautionnements versés',
        28: 'Amortissements des immobilisations', 280: 'Amortissements des immobilisations incorporelles',
        281: 'Amortissements des immobilisations corporelles', 2813: 'Amortissements des constructions',
        2815: 'Amortissements des installations techniques',
        2818: 'Amortissements des autres immobilisations corporelles',
        28182: 'Amortissements du matériel de transport', 28183: 'Amortissements du matériel de bureau',
        28184: 'Amortissements du mobilier',
        29: 'Dépréciations des immobilisations', 290: 'Dépréciations des immobilisations incorporelles',
        291: 'Dépréciations des immobilisations corporelles', 296: 'Dépréciations des participations',
        297: 'Dépréciations des autres immobilisations financières',

        // --- Classe 3 : stocks ---
        31: 'Matières premières', 32: 'Autres approvisionnements', 321: 'Matières consommables',
        322: 'Fournitures consommables', 326: 'Emballages', 33: 'En-cours de production de biens',
        34: 'En-cours de production de services', 35: 'Stocks de produits', 355: 'Produits finis',
        37: 'Stocks de marchandises', 39: 'Dépréciations des stocks et en-cours',
        391: 'Dépréciations des matières premières', 397: 'Dépréciations des stocks de marchandises',

        // --- Classe 4 : tiers ---
        40: 'Fournisseurs et comptes rattachés', 401: 'Fournisseurs', 403: 'Fournisseurs – Effets à payer',
        404: "Fournisseurs d'immobilisations", 408: 'Fournisseurs – Factures non parvenues',
        409: 'Fournisseurs débiteurs', 4091: 'Fournisseurs – Avances et acomptes versés',
        4096: 'Fournisseurs – Créances pour emballages à rendre',
        41: 'Clients et comptes rattachés', 411: 'Clients', 413: 'Clients – Effets à recevoir',
        416: 'Clients douteux ou litigieux', 418: 'Clients – Produits non encore facturés',
        419: 'Clients créditeurs', 4191: 'Clients – Avances et acomptes reçus',
        42: 'Personnel et comptes rattachés', 421: 'Personnel – Rémunérations dues',
        425: 'Personnel – Avances et acomptes', 428: 'Personnel – Charges à payer',
        43: 'Sécurité sociale et autres organismes sociaux', 431: 'Sécurité sociale',
        437: 'Autres organismes sociaux',
        44: 'État et autres collectivités publiques', 444: 'État – Impôts sur les bénéfices',
        445: "État – Taxes sur le chiffre d'affaires", 44551: 'TVA à décaisser',
        44562: 'TVA déductible sur immobilisations', 44566: 'TVA déductible sur autres biens et services',
        44567: 'Crédit de TVA à reporter', 44571: 'TVA collectée',
        44586: 'TVA sur factures non parvenues', 44587: 'TVA sur factures à établir',
        447: 'Autres impôts, taxes et versements assimilés',
        45: 'Groupe et associés', 455: 'Associés – Comptes courants', 457: 'Associés – Dividendes à payer',
        46: 'Débiteurs divers et créditeurs divers', 462: "Créances sur cessions d'immobilisations",
        467: 'Autres comptes débiteurs ou créditeurs',
        47: "Comptes transitoires ou d'attente", 471: "Compte d'attente",
        48: 'Comptes de régularisation', 486: "Charges constatées d'avance", 487: "Produits constatés d'avance",
        49: 'Dépréciations des comptes de tiers', 491: 'Dépréciations des comptes de clients',

        // --- Classe 5 : financiers ---
        50: 'Valeurs mobilières de placement', 503: 'Actions', 506: 'Obligations',
        51: 'Banques, établissements financiers et assimilés', 511: "Valeurs à l'encaissement",
        5112: 'Chèques à encaisser', 512: 'Banque', 514: 'Chèques postaux', 517: 'Autres organismes financiers',
        519: 'Concours bancaires courants', 53: 'Caisse', 530: 'Caisse', 531: 'Caisse',
        58: 'Virements internes', 580: 'Virements internes', 59: 'Dépréciations des comptes financiers',

        // --- Classe 6 : charges ---
        60: 'Achats', 601: 'Achats stockés – Matières premières', 602: 'Achats stockés – Autres approvisionnements',
        603: 'Variations des stocks', 6031: 'Variation des stocks de matières premières',
        6032: 'Variation des stocks des autres approvisionnements', 6037: 'Variation des stocks de marchandises',
        604: "Achats d'études et prestations de services", 605: 'Achats de matériel, équipements et travaux',
        606: 'Achats non stockés de matières et fournitures', 6061: 'Fournitures non stockables (eau, énergie…)',
        6063: "Fournitures d'entretien et de petit équipement", 6064: 'Fournitures administratives',
        6068: 'Autres matières et fournitures', 607: 'Achats de marchandises', 608: "Frais accessoires d'achat",
        609: 'Rabais, remises et ristournes obtenus sur achats', 6097: 'RRR obtenus sur achats de marchandises',
        61: 'Services extérieurs', 611: 'Sous-traitance générale', 612: 'Redevances de crédit-bail',
        613: 'Locations', 614: 'Charges locatives et de copropriété', 615: 'Entretien et réparations',
        616: "Primes d'assurances", 617: 'Études et recherches', 618: 'Divers (documentation, séminaires…)',
        62: 'Autres services extérieurs', 621: "Personnel extérieur à l'entreprise",
        622: "Rémunérations d'intermédiaires et honoraires", 623: 'Publicité, publications, relations publiques',
        624: 'Transports de biens et transports collectifs du personnel', 625: 'Déplacements, missions et réceptions',
        626: 'Frais postaux et de télécommunications', 627: 'Services bancaires et assimilés',
        628: 'Divers (cotisations…)',
        63: 'Impôts, taxes et versements assimilés', 631: 'Impôts et taxes sur rémunérations',
        635: 'Autres impôts et taxes', 6351: 'Impôts directs (CET, taxe foncière…)', 637: 'Autres impôts et taxes (autres organismes)',
        64: 'Charges de personnel', 641: 'Rémunérations du personnel', 644: "Rémunération du travail de l'exploitant",
        645: 'Charges de sécurité sociale et de prévoyance', 646: "Cotisations sociales personnelles de l'exploitant",
        647: 'Autres charges sociales', 648: 'Autres charges de personnel',
        65: 'Autres charges de gestion courante', 651: 'Redevances pour concessions, brevets, licences',
        654: 'Pertes sur créances irrécouvrables', 658: 'Charges diverses de gestion courante',
        66: 'Charges financières', 661: "Charges d'intérêts", 6611: 'Intérêts des emprunts et dettes',
        665: 'Escomptes accordés', 666: 'Pertes de change', 667: 'Charges nettes sur cessions de VMP',
        668: 'Autres charges financières',
        67: 'Charges exceptionnelles', 671: 'Charges exceptionnelles sur opérations de gestion',
        675: "Valeurs comptables des éléments d'actif cédés", 678: 'Autres charges exceptionnelles',
        68: 'Dotations aux amortissements, dépréciations et provisions',
        681: "Dotations – Charges d'exploitation", 6811: 'Dotations aux amortissements des immobilisations',
        6815: "Dotations aux provisions d'exploitation", 6817: 'Dotations aux dépréciations des actifs circulants',
        686: 'Dotations – Charges financières', 687: 'Dotations – Charges exceptionnelles',
        69: 'Participation des salariés – Impôts sur les bénéfices', 691: 'Participation des salariés aux résultats',
        695: 'Impôts sur les bénéfices',

        // --- Classe 7 : produits ---
        70: 'Ventes de produits, prestations, marchandises', 701: 'Ventes de produits finis', 704: 'Travaux',
        706: 'Prestations de services', 707: 'Ventes de marchandises', 708: 'Produits des activités annexes',
        709: 'Rabais, remises et ristournes accordés', 7097: 'RRR accordés sur ventes de marchandises',
        71: 'Production stockée (ou déstockage)', 713: 'Variation des stocks (en-cours, produits)',
        72: 'Production immobilisée', 74: "Subventions d'exploitation",
        75: 'Autres produits de gestion courante', 751: 'Redevances pour concessions, brevets, licences',
        758: 'Produits divers de gestion courante',
        76: 'Produits financiers', 761: 'Produits de participations', 762: 'Produits des autres immobilisations financières',
        764: 'Revenus des valeurs mobilières de placement', 765: 'Escomptes obtenus', 766: 'Gains de change',
        767: 'Produits nets sur cessions de VMP', 768: 'Autres produits financiers',
        77: 'Produits exceptionnels', 771: 'Produits exceptionnels sur opérations de gestion',
        775: "Produits des cessions d'éléments d'actif", 777: "Quote-part des subventions d'investissement virée au résultat",
        778: 'Autres produits exceptionnels',
        78: 'Reprises sur amortissements, dépréciations et provisions', 781: "Reprises – Produits d'exploitation",
        786: 'Reprises – Produits financiers', 787: 'Reprises – Produits exceptionnels',
        79: 'Transferts de charges', 791: "Transferts de charges d'exploitation",
        796: 'Transferts de charges financières', 797: 'Transferts de charges exceptionnelles',
    };

    // =========================================================
    //  Outils
    // =========================================================
    function echapper(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    const cents = (x) => Math.round((Number(x) || 0) * 100);
    const euros = (c) => c / 100;

    // Montant écrit « à la française » : 1 500 000 · 1500000 · 45 000,50 · -200 · (200) · 1.500
    const NUM = '[-−–]?\\s?(?:\\d{1,3}(?:[ \\u00a0\\u202f.]\\d{3})+|\\d+)(?:[,.]\\d+)?';
    const NUMP = '\\(\\s*' + NUM + '\\s*\\)|' + NUM;
    const RE_MONTANT_FIN = new RegExp('^(.*?)[\\s:=]+(' + NUMP + ')\\s*€?$');

    function parseMontant(brut) {
        if (brut == null) return null;
        if (typeof brut === 'number') return isFinite(brut) ? brut : null;
        let s = String(brut).replace(/[\s  €]/g, '');
        if (s === '') return null;
        let negatif = false;
        if (/^\(.*\)$/.test(s)) { negatif = true; s = s.slice(1, -1); }
        if (/^[-−–]/.test(s)) { negatif = !negatif; s = s.slice(1); }
        if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
        else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
        if (!/^\d+(\.\d+)?$/.test(s)) return null;
        const n = parseFloat(s);
        return negatif ? -n : n;
    }

    // 1500000 -> « 1 500 000 » (espaces insécables), -200 -> « −200 »
    function fmt(n) {
        if (n == null || n === '' || isNaN(n)) return '';
        const v = Math.round(n * 100) / 100;
        const abs = Math.abs(v).toLocaleString('fr-FR', {
            minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2,
        });
        return (v < 0 ? '−' : '') + abs.replace(/\u202f/g, '\u00a0');
    }
    // Pour du texte brut (Markdown) : espaces normales, tiret.
    const fmtTexte = (n) => fmt(n).replace(/[  ]/g, ' ').replace('−', '-');

    function normaliserCompte(s) {
        const num = String(s ?? '').replace(/\s+/g, '');
        return /^[1-9][0-9A-Za-z]*$/.test(num) ? num : '';
    }

    // Intitulé : nom choisi dans le dossier, sinon PCG, sinon compte parent (4011 -> Fournisseurs).
    function nomCompte(num, perso) {
        num = String(num ?? '').trim();
        if (perso && perso[num]) return perso[num];
        if (PCG[num]) return PCG[num];
        for (let k = num.length - 1; k >= 2; k--) {
            if (PCG[num.slice(0, k)]) return PCG[num.slice(0, k)];
        }
        return '';
    }

    const commence = (num, prefixes) => prefixes.some((p) => num.startsWith(p));
    const sansAccents = (s) => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    // Mots du langage courant -> compte le plus utilisé en cours (« banque » -> 512).
    const ALIAS = {
        capital: '10', 'capital social': '101', reserves: '106', resultat: '120', emprunt: '164', emprunts: '164',
        terrain: '211', terrains: '211', construction: '213', constructions: '213', batiment: '213',
        vehicule: '2182', voiture: '2182', camion: '2182', ordinateur: '2183', informatique: '2183', mobilier: '2184',
        amortissement: '6811', amortissements: '6811', dotation: '6811',
        stock: '37', stocks: '37', fournisseur: '401', fournisseurs: '401', client: '411', clients: '411',
        urssaf: '431', 'securite sociale': '431', 'tva collectee': '44571', 'tva deductible': '44566',
        'tva a decaisser': '44551', banque: '512', caisse: '530', especes: '530',
        achat: '607', achats: '607', marchandises: '607', 'achats de marchandises': '607', 'variation de stock': '6037',
        energie: '6061', electricite: '6061', carburant: '6061', eau: '6061', fournitures: '6064',
        loyer: '613', location: '613', entretien: '615', reparations: '615', assurance: '616', assurances: '616',
        honoraires: '622', publicite: '623', transport: '624', deplacements: '625', telephone: '626', internet: '626',
        'frais bancaires': '627', impots: '635', salaire: '641', salaires: '641', 'charges sociales': '645',
        interets: '661', 'interets d\'emprunt': '661', vente: '707', ventes: '707', 'ventes de marchandises': '707',
        prestations: '706', services: '706', subvention: '74', subventions: '74', 'produits financiers': '768',
        'impot sur les benefices': '695', is: '695',
    };

    // Compte désigné par un numéro ou par son nom (« 512 », « banque », « Ventes de marchandises »).
    function chercherCompte(texte, perso) {
        const t = String(texte ?? '').trim();
        if (!t) return '';
        const num = normaliserCompte(t);
        if (num && /^\d/.test(t)) return num;
        return (suggestionsComptes(t, perso, 1)[0] || {}).num || '';
    }

    // Comptes qui correspondent au début d'un numéro ou à un mot (autocomplétion).
    function suggestionsComptes(texte, perso, limite = 8) {
        const q = sansAccents(String(texte ?? '').trim()).replace(/\s+/g, ' ');
        if (!q) return [];
        const noms = Object.assign({}, PCG, perso || {});
        const res = [];
        Object.keys(noms).forEach((n) => {
            const nom = sansAccents(noms[n]);
            let score = -1;
            if (/^\d+$/.test(q)) {
                if (n === q) score = 0;
                else if (n.startsWith(q)) score = 1 + n.length;
            } else if (ALIAS[q] === n) score = 0;
            else if (nom === q) score = 1;
            else if (nom.startsWith(q)) score = 2;
            else if (nom.split(/[\s'’(),–-]+/).some((m) => m.startsWith(q))) score = 3;
            else if (q.length >= 3 && nom.includes(q)) score = 4;
            if (score < 0) return;
            if (perso && perso[n]) score = nom === q ? -1 : score - 0.5;   // nom choisi dans le dossier : en premier
            // À égalité, les comptes à 3 chiffres (les plus utilisés) d'abord : « banq » -> 512 avant 51.
            else if (!/^\d+$/.test(q)) score += ({ 3: 0, 4: 0.2, 2: 0.3 })[n.length] ?? 0.4;
            res.push({ num: n, nom: noms[n], score });
        });
        res.sort((a, b) => a.score - b.score || a.num.length - b.num.length || (a.num < b.num ? -1 : 1));
        return res.slice(0, limite).map(({ num, nom }) => ({ num, nom }));
    }

    // =========================================================
    //  Grand livre et balance
    // =========================================================

    // dossier = { comptes: {num: nom}, ecritures: [{ date, libelle, lignes: [{ compte, debit, credit }] }] }
    function grandLivre(dossier) {
        const perso = (dossier && dossier.comptes) || {};
        const map = new Map();
        ((dossier && dossier.ecritures) || []).forEach((ecr, i) => {
            (ecr.lignes || []).forEach((l) => {
                const num = normaliserCompte(l.compte);
                const d = cents(l.debit), c = cents(l.credit);
                if (!num || (!d && !c)) return;
                if (!map.has(num)) map.set(num, { num, nom: nomCompte(num, perso), mvts: [], d: 0, c: 0 });
                const cpt = map.get(num);
                const info = { n: i + 1, date: ecr.date || '', libelle: ecr.libelle || '' };
                if (d) { cpt.mvts.push(Object.assign({ sens: 'D', montant: euros(d) }, info)); cpt.d += d; }
                if (c) { cpt.mvts.push(Object.assign({ sens: 'C', montant: euros(c) }, info)); cpt.c += c; }
            });
        });
        return [...map.values()]
            .sort((a, b) => (a.num < b.num ? -1 : a.num > b.num ? 1 : 0))
            .map((c) => ({ num: c.num, nom: c.nom, mvts: c.mvts,
                totalD: euros(c.d), totalC: euros(c.c), solde: euros(c.d - c.c) }));
    }

    // Écart débit - crédit d'une écriture (0 = équilibrée).
    function ecartEcriture(ecr) {
        let d = 0, c = 0;
        (ecr.lignes || []).forEach((l) => { d += cents(l.debit); c += cents(l.credit); });
        return { debit: euros(d), credit: euros(c), ecart: euros(d - c) };
    }

    function balance(comptes) {
        let sd = 0, sc = 0, dd = 0, dc = 0;
        const lignes = comptes.map((c) => {
            const s = cents(c.solde);
            sd += cents(c.totalD); sc += cents(c.totalC);
            if (s > 0) dd += s; else dc -= s;
            return { num: c.num, nom: c.nom, totalD: c.totalD, totalC: c.totalC,
                soldeD: s > 0 ? euros(s) : null, soldeC: s < 0 ? euros(-s) : null };
        });
        return { lignes, sommesD: euros(sd), sommesC: euros(sc), soldesD: euros(dd), soldesC: euros(dc),
            equilibree: sd === sc && dd === dc };
    }

    // =========================================================
    //  Compte de résultat
    // =========================================================
    const RUBRIQUES_CR = [
        { cle: 'exploitation', charges: "Charges d'exploitation", produits: "Produits d'exploitation" },
        { cle: 'financier', charges: 'Charges financières', produits: 'Produits financiers' },
        { cle: 'exceptionnel', charges: 'Charges exceptionnelles', produits: 'Produits exceptionnels' },
        { cle: 'impots', charges: 'Participation et impôts sur les bénéfices', produits: '' },
    ];
    function rubriqueResultat(num) {
        if (commence(num, ['66', '76', '686', '786', '796'])) return 'financier';
        if (commence(num, ['67', '77', '687', '787', '797'])) return 'exceptionnel';
        if (num.startsWith('69')) return 'impots';
        return 'exploitation';
    }

    function compteResultat(comptes) {
        const charges = {}, produits = {};
        RUBRIQUES_CR.forEach((r) => { charges[r.cle] = []; produits[r.cle] = []; });
        let tc = 0, tp = 0;
        comptes.forEach((c) => {
            const s = cents(c.solde);
            if (!s) return;
            if (c.num[0] === '6') {
                charges[rubriqueResultat(c.num)].push({ num: c.num, libelle: c.nom, montant: euros(s) });
                tc += s;
            } else if (c.num[0] === '7') {
                produits[rubriqueResultat(c.num)].push({ num: c.num, libelle: c.nom, montant: euros(-s) });
                tp -= s;
            }
        });
        return { charges, produits, totalCharges: euros(tc), totalProduits: euros(tp), resultat: euros(tp - tc) };
    }

    function docResultat(cr, titre) {
        let paires = RUBRIQUES_CR
            .map((r) => ({ g: { titre: r.charges, lignes: cr.charges[r.cle] },
                           d: { titre: r.produits, lignes: cr.produits[r.cle] } }))
            .filter((p) => p.g.lignes.length || p.d.lignes.length);
        // Une seule rubrique (cas courant en début d'année) : présentation simple, sans sous-titres.
        if (paires.length <= 1) paires = paires.map((p) => ({ g: { titre: '', lignes: p.g.lignes }, d: { titre: '', lignes: p.d.lignes } }));
        return completerDoc({
            type: 'resultat', titre: titre || 'Compte de résultat', entetes: ['Charges', 'Produits'],
            gauche: paires.map((p) => p.g), droite: paires.map((p) => p.d),
        });
    }

    // =========================================================
    //  Bilan
    // =========================================================
    // Où va un compte de bilan selon son numéro et le sens de son solde (en centimes, débit - crédit).
    function classerBilan(num, solde) {
        const k2 = num.slice(0, 2);
        switch (num[0]) {
            case '1':
                if (k2 === '15') return { cote: 'passif', rub: 'provisions' };
                if (k2 === '16' || k2 === '17' || k2 === '18') return { cote: 'passif', rub: 'dettes', type: 'financiere' };
                return { cote: 'passif', rub: 'cp' };
            case '2': return { cote: 'actif', rub: 'immo' };
            case '3': return { cote: 'actif', rub: 'circulant', type: 'stock' };
            case '4':
                if (k2 === '49' || solde >= 0) return { cote: 'actif', rub: 'circulant', type: 'creance' };
                return { cote: 'passif', rub: 'dettes', type: 'exploitation' };
            case '5':
                if (k2 === '59') return { cote: 'actif', rub: 'circulant', type: 'dispo' };
                if (num.startsWith('519') || solde < 0) return { cote: 'passif', rub: 'dettes', type: 'tresorerie' };
                return { cote: 'actif', rub: 'circulant', type: 'dispo' };
            default: return null;
        }
    }

    function bilan(comptes, resultat) {
        const r = { immo: [], circulant: [], cp: [], provisions: [], dettes: [] };
        const agg = { immo: 0, stock: 0, creance: 0, dispo: 0, cp: 0, provisions: 0,
            financiere: 0, exploitation: 0, tresorerie: 0 };
        comptes.forEach((c) => {
            const s = cents(c.solde);
            if (!s) return;
            const cl = classerBilan(c.num, s);
            if (!cl) return;
            const montant = cl.cote === 'actif' ? s : -s;
            r[cl.rub].push({ num: c.num, libelle: c.nom, montant: euros(montant) });
            agg[cl.type || cl.rub] += montant;
        });
        const res = cents(resultat);
        if (res || comptes.some((c) => c.num[0] === '6' || c.num[0] === '7')) {
            r.cp.push({ libelle: "Résultat de l'exercice (" + (res >= 0 ? 'bénéfice' : 'perte') + ')', montant: euros(res), calcule: true });
            agg.cp += res;
        }
        const totalActif = agg.immo + agg.stock + agg.creance + agg.dispo;
        const totalPassif = agg.cp + agg.provisions + agg.financiere + agg.exploitation + agg.tresorerie;
        const a = {};
        Object.keys(agg).forEach((k) => { a[k] = euros(agg[k]); });
        return { rubriques: r, agregats: a, totalActif: euros(totalActif), totalPassif: euros(totalPassif) };
    }

    function docBilan(bl, titre) {
        const r = bl.rubriques;
        const dettes = r.provisions.concat(r.dettes);
        return completerDoc({
            type: 'bilan', titre: titre || 'Bilan', entetes: ['Actif', 'Passif'],
            gauche: [{ titre: 'Actif immobilisé', lignes: r.immo }, { titre: 'Actif circulant', lignes: r.circulant }],
            droite: [{ titre: 'Capitaux propres', lignes: r.cp },
                     { titre: r.provisions.length ? 'Provisions et dettes' : 'Dettes', lignes: dettes }],
        });
    }

    // Totaux, résultat, ligne « ? » à calculer et message de vérification d'un bilan / compte de résultat.
    // doc = { type: 'bilan'|'resultat', titre, entetes, gauche: [rubrique], droite: [rubrique] }
    // rubrique = { titre, lignes: [{ num, libelle, montant, calcule }] }
    function completerDoc(doc) {
        const somme = (rubs) => rubs.reduce((t, rub) => t + rub.lignes.reduce((s, l) => s + cents(l.montant), 0), 0);
        // Une seule ligne « ? » : montant qui équilibre (ex. « Résultat : ? » dans un bilan).
        const auto = [];
        ['gauche', 'droite'].forEach((cote) => doc[cote].forEach((rub) => rub.lignes.forEach((l) => {
            if (l.auto) auto.push({ l, cote });
        })));
        if (doc.type === 'bilan' && auto.length === 1) {
            const { l, cote } = auto[0];
            l.montant = 0;
            const autre = cote === 'gauche' ? 'droite' : 'gauche';
            l.montant = euros(somme(doc[autre]) - somme(doc[cote]));
            l.calcule = true;
        }
        const tg = somme(doc.gauche), td = somme(doc.droite);
        doc.fin = [];
        if (doc.type === 'resultat') {
            const res = td - tg;
            doc.resultat = euros(res);
            doc.fin.push({ classe: 'st', g: { libelle: 'Total charges', montant: euros(tg) },
                d: { libelle: 'Total produits', montant: euros(td) } });
            doc.fin.push({ classe: 'resultat',
                g: res > 0 ? { libelle: 'Résultat : bénéfice', montant: euros(res) } : null,
                d: res < 0 ? { libelle: 'Résultat : perte', montant: euros(-res) } : null });
            const total = euros(Math.max(tg, td));
            doc.fin.push({ classe: 'total', g: { libelle: 'TOTAL', montant: total }, d: { libelle: 'TOTAL', montant: total } });
            doc.verif = res > 0 ? { ok: true, texte: 'Bénéfice de ' + fmt(euros(res)) + ' € (produits > charges)' }
                : res < 0 ? { ok: false, texte: 'Perte de ' + fmt(euros(-res)) + ' € (charges > produits)' }
                    : { ok: true, texte: 'Résultat nul' };
        } else {
            doc.fin.push({ classe: 'total', g: { libelle: 'TOTAL ' + doc.entetes[0].toUpperCase(), montant: euros(tg) },
                d: { libelle: 'TOTAL ' + doc.entetes[1].toUpperCase(), montant: euros(td) } });
            doc.verif = tg === td
                ? { ok: true, texte: doc.entetes[0] + ' = ' + doc.entetes[1] + ' : le bilan est équilibré' }
                : { ok: false, texte: 'Écart de ' + fmt(euros(Math.abs(tg - td))) + ' € entre ' + doc.entetes[0].toLowerCase()
                    + ' et ' + doc.entetes[1].toLowerCase() + ' : vérifie le résultat et les soldes' };
        }
        return doc;
    }

    // =========================================================
    //  Analyse : FR / BFR / trésorerie, SIG, ratios
    // =========================================================
    function analyse(comptes, cr, bl) {
        const somme = (classe, prefixes, sauf = []) => euros(comptes.reduce((t, c) => {
            if (c.num[0] !== classe || !commence(c.num, prefixes) || commence(c.num, sauf)) return t;
            const s = cents(c.solde);
            return t + (classe === '7' ? -s : s);
        }, 0));
        const P = (p, sauf) => somme('7', p, sauf);
        const C = (p, sauf) => somme('6', p, sauf);
        const MARCH = ['607', '6037', '6087', '6097'];

        const ventesMarch = P(['707', '7097']);
        const coutAchat = C(MARCH);
        const marge = ventesMarch - coutAchat;
        const production = P(['70', '71', '72', '73'], ['707', '7097']);
        const conso = C(['60', '61', '62'], MARCH);
        const va = marge + production - conso;
        const subv = P(['74']), impots = C(['63']), personnel = C(['64']);
        const ebe = va + subv - impots - personnel;
        const re = ebe + P(['75', '78', '79'], ['786', '787', '796', '797']) - C(['65', '68'], ['686', '687']);
        const rf = P(['76', '786', '796']) - C(['66', '686']);
        const rcai = re + rf;
        const rex = P(['77', '787', '797']) - C(['67', '687']);
        const rn = rcai + rex - C(['69']);
        const ca = P(['70']);
        const ar = (x) => Math.round(x * 100) / 100;

        const sig = [
            { libelle: 'Ventes de marchandises', valeur: ventesMarch, niveau: 1 },
            { libelle: '− Coût d\'achat des marchandises vendues', valeur: coutAchat, niveau: 1 },
            { libelle: 'Marge commerciale', valeur: marge, niveau: 0, aide: 'Ventes de marchandises − (achats de marchandises ± variation de stock)' },
            { libelle: 'Production de l\'exercice', valeur: production, niveau: 1 },
            { libelle: '− Consommations en provenance des tiers', valeur: conso, niveau: 1, aide: 'Comptes 60 (hors marchandises), 61 et 62' },
            { libelle: 'Valeur ajoutée', valeur: va, niveau: 0, aide: 'Richesse créée par l\'entreprise' },
            { libelle: '+ Subventions d\'exploitation', valeur: subv, niveau: 1 },
            { libelle: '− Impôts et taxes', valeur: impots, niveau: 1 },
            { libelle: '− Charges de personnel', valeur: personnel, niveau: 1 },
            { libelle: 'Excédent brut d\'exploitation (EBE)', valeur: ebe, niveau: 0, aide: 'Ce que rapporte l\'activité avant amortissements et financement' },
            { libelle: 'Résultat d\'exploitation', valeur: re, niveau: 0, aide: 'EBE + autres produits − autres charges − dotations' },
            { libelle: 'Résultat financier', valeur: rf, niveau: 1 },
            { libelle: 'Résultat courant avant impôts', valeur: rcai, niveau: 0 },
            { libelle: 'Résultat exceptionnel', valeur: rex, niveau: 1 },
            { libelle: 'Résultat net', valeur: rn, niveau: 0, aide: 'Doit être égal au résultat du compte de résultat' },
        ].map((l) => Object.assign(l, { valeur: ar(l.valeur) }));

        const a = bl.agregats;
        const fr = ar(a.cp + a.provisions + a.financiere - a.immo);
        const bfr = ar(a.stock + a.creance - a.exploitation);
        const tn = ar(a.dispo - a.tresorerie);
        const div = (x, y, k = 1) => (y ? ar((x / y) * k) : null);
        const clients = euros(comptes.filter((c) => commence(c.num, ['411', '413', '416', '418']))
            .reduce((t, c) => t + cents(c.solde), 0));
        const fournisseurs = euros(comptes.filter((c) => commence(c.num, ['401', '403', '408']))
            .reduce((t, c) => t - cents(c.solde), 0));
        const achats = C(['60', '61', '62'], ['603']);
        const passif = bl.totalPassif;
        const dettesCT = ar(a.exploitation + a.tresorerie);

        const ratios = [
            { libelle: 'Taux de marge commerciale', formule: 'Marge commerciale ÷ coût d\'achat des marchandises vendues × 100',
                valeur: div(marge, coutAchat, 100), unite: '%' },
            { libelle: 'Taux de marque', formule: 'Marge commerciale ÷ ventes de marchandises HT × 100',
                valeur: div(marge, ventesMarch, 100), unite: '%' },
            { libelle: 'Taux de valeur ajoutée', formule: 'Valeur ajoutée ÷ chiffre d\'affaires HT × 100',
                valeur: div(va, ca, 100), unite: '%' },
            { libelle: 'Poids des charges de personnel', formule: 'Charges de personnel ÷ valeur ajoutée × 100',
                valeur: div(personnel, va, 100), unite: '%' },
            { libelle: 'Rentabilité commerciale', formule: 'Résultat net ÷ chiffre d\'affaires HT × 100',
                valeur: div(cr.resultat, ca, 100), unite: '%', aide: 'Ce que rapportent 100 € de ventes' },
            { libelle: 'Rentabilité financière', formule: 'Résultat net ÷ capitaux propres × 100',
                valeur: div(cr.resultat, a.cp, 100), unite: '%', aide: 'Ce que rapportent 100 € apportés par les associés' },
            { libelle: 'Rentabilité économique', formule: 'Résultat d\'exploitation ÷ (actif immobilisé + BFR) × 100',
                valeur: div(re, a.immo + bfr, 100), unite: '%' },
            { libelle: 'Autonomie financière', formule: 'Capitaux propres ÷ total du passif × 100',
                valeur: div(a.cp, passif, 100), unite: '%', aide: 'Au-dessus de 20 à 25 % : l\'entreprise ne dépend pas trop des prêteurs' },
            { libelle: 'Endettement', formule: 'Dettes financières ÷ capitaux propres',
                valeur: div(a.financiere, a.cp), unite: '', aide: 'Au-dessous de 1 : capacité à emprunter encore' },
            { libelle: 'Liquidité générale', formule: 'Actif circulant ÷ dettes à court terme',
                valeur: div(a.stock + a.creance + a.dispo, dettesCT), unite: '', aide: 'Supérieur à 1 : les actifs à court terme couvrent les dettes à court terme' },
            { libelle: 'Délai moyen de paiement des clients', formule: 'Clients ÷ chiffre d\'affaires × 360',
                valeur: div(clients, ca, 360), unite: 'jours', aide: 'En principe clients TTC ÷ CA TTC (ici sans TVA)' },
            { libelle: 'Délai moyen de paiement des fournisseurs', formule: 'Fournisseurs ÷ achats × 360',
                valeur: div(fournisseurs, achats, 360), unite: 'jours', aide: 'En principe fournisseurs TTC ÷ achats TTC' },
        ];

        return { sig, fr, bfr, tn, ecartSig: ar(rn - cr.resultat), ratios, ca: ar(ca) };
    }

    // Tout calculer d'un coup à partir d'un dossier.
    function calculer(dossier) {
        const comptes = grandLivre(dossier);
        const bal = balance(comptes);
        const cr = compteResultat(comptes);
        const bl = bilan(comptes, cr.resultat);
        return { comptes, balance: bal, resultat: cr, bilan: bl, analyse: analyse(comptes, cr, bl) };
    }

    // =========================================================
    //  Rendu HTML (atelier + blocs des notes)
    // =========================================================
    const num = (n) => (n ? '<span class="cpt-num">' + echapper(n) + '</span> ' : '');

    // Comptes en T. comptes = [{ num, nom, mvts: [{ sens, montant, libelle?, n? }], totalD, totalC, solde }]
    function htmlComptesT(comptes) {
        if (!comptes.length) return '<p class="cpt-vide">Aucun compte mouvementé.</p>';
        return '<div class="cpt-t-grille">' + comptes.map((c) => {
            const d = c.mvts.filter((m) => m.sens === 'D'), cr = c.mvts.filter((m) => m.sens === 'C');
            const n = Math.max(d.length, cr.length, 1);
            const cellule = (m, cls) => {
                if (!m) return '<td class="' + cls + '"></td>';
                const info = [m.n ? 'Écriture n° ' + m.n : '', m.date, m.libelle].filter(Boolean).join(' · ');
                return '<td class="' + cls + '"' + (info ? ' title="' + echapper(info) + '"' : '') + '>'
                    + (m.ref ? '<span class="cpt-ref">(' + echapper(m.ref) + ')</span> ' : '')
                    + fmt(m.montant) + '</td>';
            };
            let lignes = '';
            for (let i = 0; i < n; i++) lignes += '<tr>' + cellule(d[i], 'cpt-t__d') + cellule(cr[i], 'cpt-t__c') + '</tr>';
            const s = c.solde;
            const solde = s > 0 ? 'Solde débiteur : ' + fmt(s) : s < 0 ? 'Solde créditeur : ' + fmt(-s) : 'Compte soldé';
            return '<table class="cpt-t"><caption>' + num(c.num) + echapper(c.nom || '') + '</caption>'
                + '<thead><tr><th>Débit</th><th>Crédit</th></tr></thead><tbody>' + lignes + '</tbody>'
                + '<tfoot><tr class="cpt-t__tot"><td>' + (d.length ? fmt(c.totalD) : '') + '</td><td>'
                + (cr.length ? fmt(c.totalC) : '') + '</td></tr>'
                + '<tr class="cpt-t__solde"><td colspan="2" class="' + (s > 0 ? 'sd' : s < 0 ? 'sc' : '') + '">'
                + solde + '</td></tr></tfoot></table>';
        }).join('') + '</div>';
    }

    function htmlBalance(bal) {
        const lignes = bal.lignes.map((l) => '<tr><td class="cpt-mono">' + echapper(l.num) + '</td><td>' + echapper(l.nom)
            + '</td><td class="cpt-mt">' + fmt(l.totalD) + '</td><td class="cpt-mt">' + fmt(l.totalC)
            + '</td><td class="cpt-mt">' + fmt(l.soldeD) + '</td><td class="cpt-mt">' + fmt(l.soldeC) + '</td></tr>').join('');
        const ok = bal.equilibree;
        return '<div class="cpt-doc"><table class="cpt-table cpt-balance">'
            + '<thead><tr><th rowspan="2">N° compte</th><th rowspan="2">Nom du compte</th><th colspan="2">Sommes</th><th colspan="2">Soldes</th></tr>'
            + '<tr><th>Débit</th><th>Crédit</th><th>Débit</th><th>Crédit</th></tr></thead>'
            + '<tbody>' + (lignes || '<tr><td colspan="6" class="cpt-vide">Aucun compte.</td></tr>') + '</tbody>'
            + '<tfoot><tr class="cpt-total"><td colspan="2">TOTAUX</td><td class="cpt-mt">' + fmt(bal.sommesD)
            + '</td><td class="cpt-mt">' + fmt(bal.sommesC) + '</td><td class="cpt-mt">' + fmt(bal.soldesD)
            + '</td><td class="cpt-mt">' + fmt(bal.soldesC) + '</td></tr></tfoot></table>'
            + '<p class="cpt-verif ' + (ok ? 'ok' : 'ko') + '">' + (ok
                ? 'Balance équilibrée : total des débits = total des crédits, et soldes débiteurs = soldes créditeurs.'
                : 'Balance déséquilibrée : une écriture n\'a pas autant au débit qu\'au crédit.') + '</p></div>';
    }

    // Bilan ou compte de résultat (deux colonnes alignées rubrique par rubrique).
    function htmlDoc(doc) {
        const cellules = (l) => {
            if (!l) return '<td class="cpt-doc__lib"></td><td class="cpt-mt"></td>';
            return '<td class="cpt-doc__lib">' + num(l.num) + echapper(l.libelle || '')
                + (l.calcule ? ' <span class="cpt-calc" title="Calculé automatiquement">calculé</span>' : '')
                + '</td><td class="cpt-mt">' + (l.montant == null ? '' : fmt(l.montant)) + '</td>';
        };
        const n = Math.max(doc.gauche.length, doc.droite.length);
        let corps = '';
        for (let i = 0; i < n; i++) {
            const g = doc.gauche[i] || { titre: '', lignes: [] }, d = doc.droite[i] || { titre: '', lignes: [] };
            if (g.titre || d.titre) {
                corps += '<tr class="cpt-doc__rub"><th colspan="2">' + echapper(g.titre) + '</th><th colspan="2">'
                    + echapper(d.titre) + '</th></tr>';
            }
            const lignes = Math.max(g.lignes.length, d.lignes.length);
            for (let j = 0; j < lignes; j++) corps += '<tr>' + cellules(g.lignes[j]) + cellules(d.lignes[j]) + '</tr>';
            if (g.titre || d.titre) {
                const tot = (r) => (r.titre ? { libelle: 'Total ' + r.titre.charAt(0).toLowerCase() + r.titre.slice(1),
                    montant: euros(r.lignes.reduce((s, l) => s + cents(l.montant), 0)) } : null);
                corps += '<tr class="cpt-doc__st">' + cellules(tot(g)) + cellules(tot(d)) + '</tr>';
            }
        }
        (doc.fin || []).forEach((f) => { corps += '<tr class="cpt-doc__' + f.classe + '">' + cellules(f.g) + cellules(f.d) + '</tr>'; });
        return '<div class="cpt-doc"><table class="cpt-table cpt-doc__table">'
            + (doc.titre ? '<caption>' + echapper(doc.titre) + '</caption>' : '')
            + '<colgroup><col><col class="cpt-col-mt"><col><col class="cpt-col-mt"></colgroup>'
            + '<thead><tr><th colspan="2">' + echapper(doc.entetes[0]) + '</th><th colspan="2">' + echapper(doc.entetes[1])
            + '</th></tr></thead><tbody>' + corps + '</tbody></table>'
            + (doc.verif ? '<p class="cpt-verif ' + (doc.verif.ok ? 'ok' : 'ko') + '">' + echapper(doc.verif.texte) + '</p>' : '')
            + '</div>';
    }

    // Journal à la française : comptes débités, puis crédités (décalés), libellé en dessous.
    function htmlJournal(ecritures, perso) {
        let td = 0, tc = 0, corps = '';
        ecritures.forEach((ecr, i) => {
            const lignes = (ecr.lignes || []).filter((l) => normaliserCompte(l.compte) && (cents(l.debit) || cents(l.credit)));
            const tri = lignes.filter((l) => cents(l.debit)).concat(lignes.filter((l) => !cents(l.debit)));
            corps += '<tr class="cpt-j__date"><td colspan="5"><span>' + (i + 1) + ' · ' + echapper(ecr.date || '') + '</span></td></tr>';
            tri.forEach((l) => {
                const n = normaliserCompte(l.compte), d = cents(l.debit), c = cents(l.credit);
                td += d; tc += c;
                const credit = !d;
                corps += '<tr><td class="cpt-mono">' + (credit ? '' : echapper(n)) + '</td><td class="cpt-mono">'
                    + (credit ? echapper(n) : '') + '</td><td' + (credit ? ' class="cpt-j__credit"' : '') + '>'
                    + echapper(nomCompte(n, perso)) + '</td><td class="cpt-mt">' + (d ? fmt(euros(d)) : '')
                    + '</td><td class="cpt-mt">' + (c ? fmt(euros(c)) : '') + '</td></tr>';
            });
            if (ecr.libelle) corps += '<tr class="cpt-j__lib"><td></td><td></td><td colspan="3">' + echapper(ecr.libelle) + '</td></tr>';
            const e = ecartEcriture({ lignes });
            if (e.ecart) {
                corps += '<tr class="cpt-j__ko"><td colspan="5">Écriture déséquilibrée : débit ' + fmt(e.debit)
                    + ' ≠ crédit ' + fmt(e.credit) + '</td></tr>';
            }
        });
        return '<div class="cpt-doc"><table class="cpt-table cpt-journal">'
            + '<thead><tr><th>N° débit</th><th>N° crédit</th><th>Libellé</th><th>Débit</th><th>Crédit</th></tr></thead>'
            + '<tbody>' + (corps || '<tr><td colspan="5" class="cpt-vide">Aucune écriture.</td></tr>') + '</tbody>'
            + '<tfoot><tr class="cpt-total"><td colspan="3">Totaux</td><td class="cpt-mt">' + fmt(euros(td))
            + '</td><td class="cpt-mt">' + fmt(euros(tc)) + '</td></tr></tfoot></table></div>';
    }

    // =========================================================
    //  Saisie rapide : « 607 / 401 14000 Achat de marchandises »
    // =========================================================
    const RE_DATE = /^(\d{1,2}[/.-]\d{1,2}(?:[/.-](?:\d{2,4}|N(?:[+-]1)?))?)\s*[-–:]?\s*/i;
    // Comptes en chiffres ou en lettres : « 512 / 707 45000 » ou « banque / ventes 45000 ».
    const RE_RAPIDE = new RegExp('^([^/|]+?)\\s*/\\s*([^/|]+?)\\s+(' + NUM + ')\\s*€?(?:\\s+[-–:]?\\s*(.*))?$');

    function saisieRapide(texte, perso) {
        let t = String(texte || '').trim();
        let date = '';
        const md = t.match(RE_DATE);
        if (md && RE_RAPIDE.test(t.slice(md[0].length))) { date = md[1]; t = t.slice(md[0].length); }
        const m = t.match(RE_RAPIDE);
        if (!m) return null;
        const montant = parseMontant(m[3]);
        const debit = chercherCompte(m[1], perso), credit = chercherCompte(m[2], perso);
        if (!montant || !debit || !credit) return null;
        return { date, libelle: (m[4] || '').trim(), lignes: [
            { compte: debit, debit: montant, credit: null },
            { compte: credit, debit: null, credit: montant },
        ] };
    }

    // =========================================================
    //  Blocs Markdown des notes
    // =========================================================
    const lignesDe = (code) => String(code || '').replace(/\r\n?/g, '\n').split('\n');

    // « 211 Terrains » -> { num: '211', nom: 'Terrains' } ; « Terrains » -> { num: '', nom: 'Terrains' }
    function separerCompte(texte) {
        const t = String(texte || '').trim().replace(/\s*:$/, '');
        const m = t.match(/^([1-9]\d{1,7}[A-Za-z]*)(?:\s*[-–:]?\s+(.*))?$/);
        if (m) return { num: m[1], nom: (m[2] || '').trim() };
        return { num: '', nom: t };
    }

    // ```comptes : un compte par titre (« 512 Banque »), puis « débit | crédit » ligne par ligne.
    function parseComptesT(code, titre) {
        const comptes = [];
        let cpt = null;
        const nouveau = (entete) => {
            const s = separerCompte(entete);
            cpt = { num: s.num, nom: s.nom || nomCompte(s.num), mvts: [] };
            comptes.push(cpt);
        };
        const cellule = (s, sens) => {
            let t = s.trim();
            if (!t) return;
            let ref = '';
            const r = t.match(/^\((\w{1,4})\)\s+(?=\S)/);
            if (r) { ref = r[1]; t = t.slice(r[0].length); }
            const m = t.match(new RegExp('^(' + NUMP + ')\\s*€?(?:\\s+(.*))?$'));
            const montant = m ? parseMontant(m[1]) : parseMontant(t);
            if (montant == null) return;
            cpt.mvts.push({ sens, montant, ref, libelle: m && m[2] ? m[2] : '' });
        };
        if (titre) nouveau(titre);
        lignesDe(code).forEach((brute) => {
            const l = brute.trim();
            if (!l) return;
            if (!l.includes('|')) { nouveau(l); return; }
            if (!cpt) nouveau('');
            const i = l.indexOf('|');
            cellule(l.slice(0, i), 'D');
            cellule(l.slice(i + 1).replace(/\|\s*$/, ''), 'C');
        });
        return comptes.map((c) => {
            let d = 0, cr = 0;
            c.mvts.forEach((m) => { if (m.sens === 'D') d += cents(m.montant); else cr += cents(m.montant); });
            return Object.assign(c, { totalD: euros(d), totalC: euros(cr), solde: euros(d - cr) });
        });
    }

    // ```journal : « 31/12 Libellé » puis « compte | débit | crédit » (ou « 607 / 401 14000 »).
    function parseJournal(code) {
        const ecritures = [], perso = {};
        let ecr = null;
        lignesDe(code).forEach((brute) => {
            const l = brute.trim();
            if (!l) return;
            if (!l.includes('|')) {
                const rapide = saisieRapide(l);
                if (rapide) {
                    if (ecr && !ecr.lignes.length) {
                        ecr.lignes = rapide.lignes;
                        if (!ecr.libelle) ecr.libelle = rapide.libelle;
                        if (!ecr.date) ecr.date = rapide.date;
                    } else {
                        ecritures.push(rapide);
                    }
                    ecr = null;
                    return;
                }
                const m = l.match(RE_DATE);
                ecr = { date: m ? m[1] : '', libelle: m ? l.slice(m[0].length) : l, lignes: [] };
                ecritures.push(ecr);
                return;
            }
            let cellules = l.split('|').map((c) => c.trim());
            if (cellules[0] === '' && cellules.length >= 4) cellules = cellules.slice(1);
            const s = separerCompte(cellules[0]);
            if (!s.num) { s.num = chercherCompte(s.nom); s.nom = ''; }   // « banque | 45 000 | »
            if (!s.num) return;
            if (s.nom) perso[s.num] = s.nom;
            if (!ecr) { ecr = { date: '', libelle: '', lignes: [] }; ecritures.push(ecr); }
            ecr.lignes.push({ compte: s.num, debit: parseMontant(cellules[1]), credit: parseMontant(cellules[2]) });
        });
        return { ecritures, perso };
    }

    // ```balance : « 512 Banque | 955 000 | 863 500 » (sommes au débit et au crédit).
    function parseBalance(code) {
        const comptes = [];
        lignesDe(code).forEach((brute) => {
            const l = brute.trim();
            if (!l || !l.includes('|')) return;
            let cellules = l.split('|').map((c) => c.trim());
            if (cellules[0] === '' && cellules.length >= 4) cellules = cellules.slice(1);
            const s = separerCompte(cellules[0]);
            if (!s.num && !s.nom) return;
            const d = parseMontant(cellules[1]) || 0, c = parseMontant(cellules[2]) || 0;
            comptes.push({ num: s.num, nom: s.nom || nomCompte(s.num), totalD: d, totalC: c,
                solde: euros(cents(d) - cents(c)) });
        });
        return comptes;
    }

    // Une ligne « Libellé  montant » (ou « Libellé | montant », « Libellé : ? »).
    function parseLigneMontant(l) {
        let libelle = l, montant = null, auto = false;
        if (l.includes('|')) {
            const i = l.lastIndexOf('|');
            libelle = l.slice(0, i).replace(/\|/g, ' ').trim();
            const m = l.slice(i + 1).trim();
            if (m === '?') auto = true; else montant = parseMontant(m);
        } else if (/[\s:=]\?$/.test(l)) {
            auto = true;
            libelle = l.replace(/[\s:=]*\?$/, '');
        } else {
            const m = l.match(RE_MONTANT_FIN);
            if (m) { libelle = m[1]; montant = parseMontant(m[2]); }
        }
        const s = separerCompte(libelle.replace(/[\s:=]+$/, ''));
        return { num: s.num, libelle: s.nom || nomCompte(s.num), montant, auto };
    }

    // ```bilan / ```resultat : « Actif » / « Passif » (ou « Charges » / « Produits »),
    // « # Rubrique », puis une ligne par poste avec son montant à la fin.
    function parseDoc(code, type, titre) {
        const bilanType = type === 'bilan';
        const reG = bilanType ? /^(actif|emplois)$/i : /^charges?$/i;
        const reD = bilanType ? /^(passif|ressources)$/i : /^produits?$/i;
        const doc = { type, titre: titre || (bilanType ? 'Bilan' : 'Compte de résultat'),
            entetes: bilanType ? ['Actif', 'Passif'] : ['Charges', 'Produits'], gauche: [], droite: [] };
        let cote = 'gauche', rub = null;
        lignesDe(code).forEach((brute) => {
            const l = brute.trim();
            if (!l) return;
            const nu = l.replace(/^[#[\s]+|[\]:\s]+$/g, '');
            if (reG.test(nu) || reD.test(nu)) {
                cote = reG.test(nu) ? 'gauche' : 'droite';
                doc.entetes[cote === 'gauche' ? 0 : 1] = nu.charAt(0).toUpperCase() + nu.slice(1).toLowerCase();
                rub = null;
                return;
            }
            if (l.startsWith('#')) {
                rub = { titre: l.replace(/^#+\s*/, ''), lignes: [] };
                doc[cote].push(rub);
                return;
            }
            if (!rub) { rub = { titre: '', lignes: [] }; doc[cote].push(rub); }
            rub.lignes.push(parseLigneMontant(l));
        });
        return completerDoc(doc);
    }

    // Point d'entrée de rendu.js : HTML du bloc, ou null si ce n'est pas un bloc de compta.
    function blocMarkdown(info, code) {
        const morceaux = String(info || '').trim().split(/\s+/);
        const langage = (morceaux.shift() || '').toLowerCase();
        const titre = morceaux.join(' ');
        try {
            switch (langage) {
                case 'comptes': case 'compte': case 't': case 'comptes-t':
                    return htmlComptesT(parseComptesT(code, titre));
                case 'journal': {
                    const j = parseJournal(code);
                    return htmlJournal(j.ecritures, j.perso);
                }
                case 'balance':
                    return htmlBalance(balance(parseBalance(code)));
                case 'bilan':
                    return htmlDoc(parseDoc(code, 'bilan', titre));
                case 'resultat': case 'résultat': case 'compte-de-resultat': case 'cr':
                    return htmlDoc(parseDoc(code, 'resultat', titre));
                default:
                    return null;
            }
        } catch (e) {
            return null;
        }
    }

    // =========================================================
    //  Export d'un dossier en Markdown (note)
    // =========================================================
    function versMarkdown(dossier) {
        const calc = calculer(dossier);
        const perso = dossier.comptes || {};
        const t = fmtTexte;
        const out = ['# ' + (dossier.titre || 'Dossier comptable'), ''];
        if (dossier.entreprise || dossier.cloture) {
            out.push('*' + [dossier.entreprise, dossier.cloture ? 'exercice clos le ' + dossier.cloture : '']
                .filter(Boolean).join(' · ') + '*', '');
        }

        out.push('## Journal', '', '```journal');
        (dossier.ecritures || []).forEach((ecr) => {
            out.push(((ecr.date || '') + ' ' + (ecr.libelle || '')).trim() || 'Écriture');
            (ecr.lignes || []).forEach((l) => {
                const n = normaliserCompte(l.compte);
                if (!n || (!cents(l.debit) && !cents(l.credit))) return;
                out.push(n + ' ' + nomCompte(n, perso) + ' | ' + t(l.debit || null) + ' | ' + t(l.credit || null));
            });
        });
        out.push('```', '', '## Grand livre', '', '```comptes');
        calc.comptes.forEach((c, i) => {
            if (i) out.push('');
            out.push(c.num + ' ' + c.nom);
            const d = c.mvts.filter((m) => m.sens === 'D'), cr = c.mvts.filter((m) => m.sens === 'C');
            for (let k = 0; k < Math.max(d.length, cr.length); k++) {
                out.push((d[k] ? t(d[k].montant) : '') + ' | ' + (cr[k] ? t(cr[k].montant) : ''));
            }
        });
        out.push('```', '', '## Balance', '', '```balance');
        calc.comptes.forEach((c) => out.push(c.num + ' ' + c.nom + ' | ' + t(c.totalD) + ' | ' + t(c.totalC)));
        out.push('```', '');

        const docVersBloc = (doc, langage) => {
            out.push('```' + langage + ' ' + doc.titre);
            [['gauche', 0], ['droite', 1]].forEach(([cote, i]) => {
                out.push(doc.entetes[i]);
                doc[cote].forEach((rub) => {
                    if (rub.titre || doc[cote].length > 1) out.push('# ' + (rub.titre || ''));
                    rub.lignes.forEach((l) => {
                        out.push(((l.num ? l.num + ' ' : '') + l.libelle).trim() + ' ' + t(l.montant));
                    });
                });
            });
            out.push('```', '');
        };
        out.push('## Compte de résultat', '');
        docVersBloc(docResultat(calc.resultat, 'Compte de résultat' + (dossier.cloture ? ' au ' + dossier.cloture : '')), 'resultat');
        out.push('## Bilan', '');
        docVersBloc(docBilan(calc.bilan, 'Bilan au ' + (dossier.cloture || '31/12/N')), 'bilan');

        const a = calc.analyse;
        out.push('## Analyse', '', '| Indicateur | Montant |', '| :--- | ---: |',
            '| Fonds de roulement (FR) | ' + t(a.fr) + ' |',
            '| Besoin en fonds de roulement (BFR) | ' + t(a.bfr) + ' |',
            '| Trésorerie nette (FR − BFR) | ' + t(a.tn) + ' |', '');
        a.sig.filter((l) => !l.niveau).forEach((l, i) => {
            if (!i) out.push('| Solde intermédiaire de gestion | Montant |', '| :--- | ---: |');
            out.push('| ' + l.libelle + ' | ' + t(l.valeur) + ' |');
        });
        out.push('');
        return out.join('\n');
    }

    // =========================================================
    //  Opérations courantes : quel compte débiter / créditer, et pourquoi
    //  parts : m = montant ; ht / tva / ttc pour les opérations avec TVA 20 %
    // =========================================================
    const OPERATIONS = [
        { groupe: 'Achats et charges', libelle: 'Achat de marchandises à crédit', lignes: [['607', 'D', 'm'], ['401', 'C', 'm']],
            pourquoi: 'Les achats de marchandises sont une charge qui augmente → débit 607. On doit de l\'argent au fournisseur : la dette augmente → crédit 401.' },
        { groupe: 'Achats et charges', libelle: 'Achat de marchandises payé par chèque', lignes: [['607', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Charge qui augmente → débit 607. L\'argent sort de la banque (actif qui diminue) → crédit 512.' },
        { groupe: 'Achats et charges', libelle: 'Achat de marchandises payé en espèces', lignes: [['607', 'D', 'm'], ['530', 'C', 'm']],
            pourquoi: 'Charge qui augmente → débit 607. La caisse diminue → crédit 530.' },
        { groupe: 'Achats et charges', libelle: 'Achat de marchandises à crédit, TVA 20 %', tva: true,
            lignes: [['607', 'D', 'ht'], ['44566', 'D', 'tva'], ['401', 'C', 'ttc']],
            pourquoi: 'La charge est enregistrée hors taxe (débit 607). La TVA payée sera récupérée sur l\'État : c\'est une créance (débit 44566). La dette fournisseur est du montant TTC (crédit 401).' },
        { groupe: 'Achats et charges', libelle: 'Achat de fournitures de bureau payé par chèque', lignes: [['6064', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Fournitures administratives = charge → débit 6064. La banque diminue → crédit 512.' },
        { groupe: 'Achats et charges', libelle: 'Achat de carburant / énergie payé en espèces', lignes: [['6061', 'D', 'm'], ['530', 'C', 'm']],
            pourquoi: 'Énergie et carburant (fournitures non stockables) = charge → débit 6061. La caisse diminue → crédit 530.' },
        { groupe: 'Achats et charges', libelle: 'Facture d\'entretien et réparations à payer plus tard', lignes: [['615', 'D', 'm'], ['401', 'C', 'm']],
            pourquoi: 'L\'entretien est un service extérieur (charge) → débit 615. Payable à 30 jours : dette fournisseur → crédit 401.' },
        { groupe: 'Achats et charges', libelle: 'Prime d\'assurance payée par chèque', lignes: [['616', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Assurance = charge → débit 616. La banque diminue → crédit 512.' },
        { groupe: 'Achats et charges', libelle: 'Frais de publicité payés par chèque', lignes: [['623', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Publicité = charge → débit 623. La banque diminue → crédit 512.' },
        { groupe: 'Achats et charges', libelle: 'Loyer payé par virement', lignes: [['613', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Location = charge → débit 613. La banque diminue → crédit 512.' },
        { groupe: 'Achats et charges', libelle: 'Facture de téléphone / internet à payer', lignes: [['626', 'D', 'm'], ['401', 'C', 'm']],
            pourquoi: 'Télécommunications = charge → débit 626. Dette envers l\'opérateur → crédit 401.' },

        { groupe: 'Ventes', libelle: 'Vente de marchandises payée par chèque', lignes: [['512', 'D', 'm'], ['707', 'C', 'm']],
            pourquoi: 'L\'argent entre en banque (actif qui augmente) → débit 512. Les ventes sont un produit qui augmente → crédit 707.' },
        { groupe: 'Ventes', libelle: 'Vente de marchandises payée en espèces', lignes: [['530', 'D', 'm'], ['707', 'C', 'm']],
            pourquoi: 'La caisse augmente → débit 530. Produit qui augmente → crédit 707.' },
        { groupe: 'Ventes', libelle: 'Vente de marchandises à crédit', lignes: [['411', 'D', 'm'], ['707', 'C', 'm']],
            pourquoi: 'Le client nous doit de l\'argent : créance qui augmente → débit 411. Produit qui augmente → crédit 707.' },
        { groupe: 'Ventes', libelle: 'Vente de marchandises à crédit, TVA 20 %', tva: true,
            lignes: [['411', 'D', 'ttc'], ['707', 'C', 'ht'], ['44571', 'C', 'tva']],
            pourquoi: 'Le client doit le TTC → débit 411. La vente est un produit hors taxe → crédit 707. La TVA encaissée est due à l\'État : dette → crédit 44571.' },
        { groupe: 'Ventes', libelle: 'Prestation de services à crédit', lignes: [['411', 'D', 'm'], ['706', 'C', 'm']],
            pourquoi: 'Créance client → débit 411. Prestations de services = produit → crédit 706.' },

        { groupe: 'Règlements et trésorerie', libelle: 'Règlement d\'un fournisseur par chèque', lignes: [['401', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'La dette fournisseur diminue → débit 401. La banque diminue → crédit 512.' },
        { groupe: 'Règlements et trésorerie', libelle: 'Règlement d\'un fournisseur en espèces', lignes: [['401', 'D', 'm'], ['530', 'C', 'm']],
            pourquoi: 'La dette diminue → débit 401. La caisse diminue → crédit 530.' },
        { groupe: 'Règlements et trésorerie', libelle: 'Encaissement d\'un client par chèque', lignes: [['512', 'D', 'm'], ['411', 'C', 'm']],
            pourquoi: 'La banque augmente → débit 512. La créance client diminue → crédit 411.' },
        { groupe: 'Règlements et trésorerie', libelle: 'Dépôt d\'espèces à la banque', lignes: [['512', 'D', 'm'], ['530', 'C', 'm']],
            pourquoi: 'L\'argent passe de la caisse à la banque : la banque augmente → débit 512, la caisse diminue → crédit 530.' },
        { groupe: 'Règlements et trésorerie', libelle: 'Retrait d\'espèces (banque → caisse)', lignes: [['530', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'La caisse augmente → débit 530. La banque diminue → crédit 512.' },

        { groupe: 'Personnel', libelle: 'Paiement des salaires par virement', lignes: [['641', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Les salaires sont une charge de personnel → débit 641. La banque diminue → crédit 512.' },
        { groupe: 'Personnel', libelle: 'Charges sociales patronales à payer', lignes: [['645', 'D', 'm'], ['431', 'C', 'm']],
            pourquoi: 'Cotisations patronales = charge → débit 645. Dette envers l\'URSSAF → crédit 431.' },

        { groupe: 'Financement', libelle: 'Apport en capital déposé en banque', lignes: [['512', 'D', 'm'], ['101', 'C', 'm']],
            pourquoi: 'La banque augmente → débit 512. Les capitaux propres augmentent (ressource) → crédit 101.' },
        { groupe: 'Financement', libelle: 'Obtention d\'un emprunt bancaire', lignes: [['512', 'D', 'm'], ['164', 'C', 'm']],
            pourquoi: 'La banque reçoit les fonds → débit 512. La dette d\'emprunt augmente → crédit 164.' },
        { groupe: 'Financement', libelle: 'Remboursement d\'emprunt (capital)', lignes: [['164', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'La dette d\'emprunt diminue → débit 164. La banque diminue → crédit 512. (Les intérêts vont au 661.)' },
        { groupe: 'Financement', libelle: 'Paiement des intérêts d\'emprunt', lignes: [['661', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Les intérêts sont une charge financière → débit 661. La banque diminue → crédit 512.' },
        { groupe: 'Financement', libelle: 'Intérêts reçus sur un placement', lignes: [['512', 'D', 'm'], ['768', 'C', 'm']],
            pourquoi: 'La banque augmente → débit 512. Produit financier → crédit 768.' },

        { groupe: 'Immobilisations', libelle: 'Achat d\'un véhicule à crédit', lignes: [['2182', 'D', 'm'], ['404', 'C', 'm']],
            pourquoi: 'Le véhicule reste plusieurs années : immobilisation (actif) → débit 2182. Dette envers le fournisseur d\'immobilisations → crédit 404.' },
        { groupe: 'Immobilisations', libelle: 'Achat de matériel informatique payé par chèque', lignes: [['2183', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Immobilisation qui augmente → débit 2183. La banque diminue → crédit 512.' },
        { groupe: 'Immobilisations', libelle: 'Achat d\'un terrain par virement', lignes: [['211', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'Immobilisation → débit 211. La banque diminue → crédit 512. (Un terrain ne s\'amortit pas.)' },
        { groupe: 'Immobilisations', libelle: 'Dotation aux amortissements (fin d\'année)', lignes: [['6811', 'D', 'm'], ['28182', 'C', 'm']],
            pourquoi: 'L\'usure du bien est une charge → débit 6811. L\'amortissement diminue la valeur de l\'immobilisation à l\'actif → crédit 28… (ici matériel de transport).' },

        { groupe: 'Fin d\'exercice', libelle: 'Stocks : annulation du stock initial', lignes: [['6037', 'D', 'm'], ['37', 'C', 'm']],
            pourquoi: 'Le stock du début d\'année est « consommé » : charge → débit 6037, et il sort de l\'actif → crédit 37.' },
        { groupe: 'Fin d\'exercice', libelle: 'Stocks : constatation du stock final', lignes: [['37', 'D', 'm'], ['6037', 'C', 'm']],
            pourquoi: 'Le stock compté à l\'inventaire entre à l\'actif → débit 37 et diminue les charges → crédit 6037.' },
        { groupe: 'Fin d\'exercice', libelle: 'Paiement de la TVA due à l\'État', lignes: [['44551', 'D', 'm'], ['512', 'C', 'm']],
            pourquoi: 'La dette de TVA disparaît → débit 44551. La banque diminue → crédit 512.' },
    ];

    // Écriture d'une opération courante pour un montant (HT si TVA).
    function ecritureOperation(op, montant, date) {
        const m = cents(montant), tva = Math.round(m * 0.2);
        const parts = { m, ht: m, tva, ttc: m + tva };
        return { date: date || '', libelle: op.libelle, lignes: op.lignes.map(([compte, sens, part]) => ({
            compte, debit: sens === 'D' ? euros(parts[part]) : null, credit: sens === 'C' ? euros(parts[part]) : null })) };
    }

    // =========================================================
    //  Exemple : cas Brico Dépôt (grand livre au 30/12 + opérations du 31/12)
    // =========================================================
    function exempleBricoDepot() {
        const L = (compte, debit, credit) => ({ compte, debit: debit || null, credit: credit || null });
        const op = (libelle, d, c, m) => ({ date: '31/12', libelle, lignes: [L(d, m, 0), L(c, 0, m)] });
        return {
            version: 1,
            titre: 'Cas Brico Dépôt',
            entreprise: 'BRICO DÉPÔT',
            cloture: '31/12/N',
            comptes: {
                10: 'Capital', 16: 'Emprunt', 37: 'Stocks', 66: 'Intérêts des emprunts', 76: 'Produits financiers',
                6061: 'Achats énergie', 6064: 'Achats de fournitures', 607: 'Achats de marchandises',
                615: 'Entretien et réparations', 616: 'Assurances', 623: 'Frais de publicité', 641: 'Salaires',
                707: 'Ventes de marchandises', 2183: 'Matériel de bureau',
            },
            ecritures: [
                { date: '30/12', libelle: 'Report des totaux du grand livre au 30/12/N', lignes: [
                    L('10', 0, 1500000), L('16', 100000, 530000), L('211', 300000, 0), L('213', 900000, 0),
                    L('2182', 150000, 0), L('2183', 100000, 0), L('37', 250000, 0), L('401', 200000, 435000),
                    L('411', 650000, 250000), L('530', 235000, 220000), L('512', 900000, 790000),
                    L('6061', 16000, 0), L('6064', 4000, 0), L('607', 540000, 0), L('615', 12000, 0),
                    L('623', 20000, 0), L('641', 510000, 0), L('707', 0, 1162000),
                ] },
                op('Achat de marchandises à crédit', '607', '401', 14000),
                op('Règlement par chèque d\'une dette fournisseur', '401', '512', 25000),
                op('Dépôt d\'espèces à la banque, provenant des caisses', '512', '530', 10000),
                op('Ventes de marchandises payées par chèque', '512', '707', 45000),
                op('Ventes de marchandises payées en espèces', '530', '707', 15000),
                op('Ventes de marchandises à crédit', '411', '707', 35000),
                op('Facture d\'entretien et réparation à payer dans 30 jours', '615', '401', 5000),
                op('Règlement en espèces d\'achat de carburant', '6061', '530', 200),
                op('Règlement des salaires par virement', '641', '512', 48500),
            ],
        };
    }

    const Compta = {
        PCG, CLASSES, OPERATIONS,
        echapper, parseMontant, fmt, fmtTexte, normaliserCompte, nomCompte, chercherCompte, suggestionsComptes,
        grandLivre, ecartEcriture, balance, compteResultat, docResultat, bilan, docBilan, completerDoc,
        analyse, calculer, classerBilan,
        htmlComptesT, htmlBalance, htmlDoc, htmlJournal,
        saisieRapide, parseComptesT, parseJournal, parseBalance, parseDoc, blocMarkdown,
        versMarkdown, ecritureOperation, exempleBricoDepot,
    };
    racine.Compta = Compta;
    if (typeof module !== 'undefined' && module.exports) module.exports = Compta;
})(typeof window !== 'undefined' ? window : globalThis);
