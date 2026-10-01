/* ============================================================
   Agenda : vues semaine / mois / liste, tâches, fenêtres d'ajout
   et de réglage de l'emploi du temps (lien iCal).
   Données : api/agenda.php
   Avec data-lecture-seule="1" (page d'accueil) : consultation
   seulement ; ajouts et modifications se font dans agenda.php.
   ============================================================ */

(function () {
    const racine = document.getElementById('agenda');
    if (!racine) return;

    const JETON = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const LECTURE_SEULE = racine.dataset.lectureSeule === '1';
    const MATIERES = JSON.parse(document.getElementById('agenda-matieres')?.textContent || '[]');
    const CLE_VUE = LECTURE_SEULE ? 'accueil-agenda-vue' : 'agenda-vue';
    const elVue = document.getElementById('agenda-vue');
    const elPeriode = document.getElementById('agenda-periode');
    const elInfo = document.getElementById('agenda-info');
    const elTaches = document.getElementById('liste-taches');

    const HAUTEUR_HEURE = 48;   // px par heure dans la vue semaine
    const TYPES = { cours: 'Cours', reunion: 'Réunion', tache: 'Tâche', perso: 'Perso', autre: 'Autre' };
    // Comme sur Celcat : CM en rouge, TD en bleu (les autres cours gardent la couleur de l'UE).
    const COULEURS_CATEGORIE = { CM: '#dc2626', TD: '#2563eb' };

    // ---------- Outils dates ----------
    const deux = (n) => String(n).padStart(2, '0');
    const ymd = (d) => d.getFullYear() + '-' + deux(d.getMonth() + 1) + '-' + deux(d.getDate());
    const hm = (d) => deux(d.getHours()) + ':' + deux(d.getMinutes());
    const lire = (s) => (s ? new Date(s.replace(' ', 'T')) : null);
    const jour0 = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const plusJours = (d, n) => { const r = new Date(d); r.setDate(r.getDate() + n); return r; };
    const lundiDe = (d) => plusJours(jour0(d), -((d.getDay() + 6) % 7));
    const memeJour = (a, b) => ymd(a) === ymd(b);
    const fmt = (options) => new Intl.DateTimeFormat('fr-FR', options);
    const fJourCourt = fmt({ weekday: 'short' });
    const fJourLong = fmt({ weekday: 'long', day: 'numeric', month: 'long' });
    const fJourMois = fmt({ day: 'numeric', month: 'short' });
    const fMoisAnnee = fmt({ month: 'long', year: 'numeric' });
    const majuscule = (s) => s.charAt(0).toUpperCase() + s.slice(1);

    const echapper = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---------- Accès à l'API ----------
    async function lireApi(action, params = {}) {
        const q = new URLSearchParams({ action, ...params });
        return (await fetch('api/agenda.php?' + q)).json();
    }
    async function api(action, donnees) {
        const rep = await fetch('api/agenda.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': JETON },
            body: JSON.stringify(donnees),
        });
        return rep.json();
    }

    // ---------- État ----------
    let vue = 'semaine';
    try { vue = localStorage.getItem(CLE_VUE) || (innerWidth < 700 ? 'liste' : 'semaine'); } catch (e) {}
    let reference = new Date();
    let donnees = { evenements: [], echeances: [], taches: [] };

    function periode() {
        if (vue === 'mois') {
            const debut = lundiDe(new Date(reference.getFullYear(), reference.getMonth(), 1));
            return { debut, fin: plusJours(debut, 42) };
        }
        if (vue === 'liste') {
            const debut = jour0(reference);
            return { debut, fin: plusJours(debut, 14) };
        }
        const debut = lundiDe(reference);
        return { debut, fin: plusJours(debut, 7) };
    }

    async function charger() {
        const { debut, fin } = periode();
        try {
            const rep = await lireApi('lister', { debut: ymd(debut), fin: ymd(fin) });
            if (rep.erreur) throw new Error(rep.erreur);
            donnees = rep;
        } catch (e) {
            info('Impossible de charger l\'agenda : ' + e.message, true);
        }
        afficher();
    }

    function info(texte, erreur = false) {
        elInfo.hidden = !texte;
        elInfo.textContent = texte || '';
        elInfo.classList.toggle('erreur', erreur);
    }

    // Tout ce qui s'affiche dans le calendrier, sous une même forme.
    function elements() {
        const liste = donnees.evenements.map((e) => ({
            genre: 'evt', brut: e, id: e.id, type: e.type, titre: e.titre,
            debut: lire(e.debut), fin: lire(e.fin) || lire(e.debut),
            journee: e.journee, fait: e.fait, lieu: e.lieu, matiere: e.matiere,
            categorie: e.categorie, intervenant: e.intervenant,
            couleur: e.type === 'cours'
                ? (COULEURS_CATEGORIE[e.categorie] || e.couleur || '#0891b2') : null,
        }));
        donnees.echeances.forEach((e) => liste.push({
            genre: 'ech', brut: e, id: e.id, type: 'echeance',
            titre: e.categorie.toUpperCase() + ' · ' + e.titre,
            debut: lire(e.debut), fin: lire(e.debut), journee: false,
            fait: e.fait, lieu: '', matiere: e.matiere, couleur: null,
        }));
        return liste.sort((a, b) => a.debut - b.debut);
    }
    // « M. Dupont · CM » (seulement les infos présentes).
    const profType = (x) => [x.intervenant, COULEURS_CATEGORIE[x.categorie] ? x.categorie : '']
        .filter(Boolean).join(' · ');
    // Infobulle : matière · prof · CM/TD · salle
    const resume = (x) => [x.titre, profType(x), x.lieu].filter(Boolean).join(' · ');

    // Les créneaux avec une durée vont dans la grille horaire ; les autres
    // (journée entière, tâche ou échéance à une heure précise) en haut.
    const dansGrille = (x) => !x.journee && x.fin > x.debut;

    // Partie d'un élément qui tombe dans un jour donné (ou null).
    function tranche(x, jour) {
        const d = jour0(jour), f = plusJours(d, 1);
        if (x.journee || !(x.fin > x.debut)) {
            const fin = x.journee ? x.fin : plusJours(jour0(x.debut), 1);
            return x.debut < f && fin > d ? x : null;
        }
        if (x.debut >= f || x.fin <= d) return null;
        return { debut: x.debut < d ? d : x.debut, fin: x.fin > f ? f : x.fin };
    }

    function puce(x, avecHeure = true) {
        const heure = !x.journee && avecHeure ? '<span class="evt-heure">' + hm(x.debut) + '</span> ' : '';
        const coche = x.type === 'tache' || x.genre === 'ech' ? (x.fait ? '☑ ' : '☐ ') : '';
        return '<button type="button" class="evt-puce type-' + x.type + (x.fait ? ' fait' : '') + '"'
            + (x.couleur ? ' style="--c:' + echapper(x.couleur) + '"' : '')
            + ' data-genre="' + x.genre + '" data-id="' + x.id + '" title="' + echapper(resume(x)) + '">'
            + coche + heure + echapper(x.titre) + '</button>';
    }

    // ---------- Affichage ----------
    function afficher() {
        racine.querySelectorAll('.agenda-vues button').forEach((b) =>
            b.classList.toggle('actif', b.dataset.vue === vue));
        const { debut, fin } = periode();
        if (vue === 'mois') {
            elPeriode.textContent = majuscule(fMoisAnnee.format(reference));
        } else {
            elPeriode.textContent = fJourMois.format(debut) + ' – '
                + fJourMois.format(plusJours(fin, -1)) + ' ' + plusJours(fin, -1).getFullYear();
        }
        elVue.className = 'agenda-vue vue-' + vue;
        ({ semaine: vueSemaine, mois: vueMois, liste: vueListe })[vue]();
        afficherTaches();
    }

    function vueSemaine() {
        const { debut } = periode();
        const jours = [...Array(7)].map((_, i) => plusJours(debut, i));
        const tout = elements();
        const aujourdhui = new Date();

        // Plage horaire : 8 h – 19 h, élargie si besoin.
        let hMin = 8, hMax = 19;
        tout.filter(dansGrille).forEach((x) => jours.forEach((j) => {
            const t = tranche(x, j);
            if (!t) return;
            hMin = Math.min(hMin, t.debut.getHours());
            const finH = t.fin.getHours() + (t.fin.getMinutes() ? 1 : 0) || 24;
            hMax = Math.max(hMax, finH);
        }));

        let html = '<div class="sem" style="--hauteur-heure:' + HAUTEUR_HEURE + 'px">';
        html += '<div class="sem-ligne sem-tete"><div class="sem-coin"></div>';
        jours.forEach((j) => {
            html += '<div class="sem-jour' + (memeJour(j, aujourdhui) ? ' aujourdhui' : '') + '">'
                + '<span>' + fJourCourt.format(j) + '</span> <b>' + j.getDate() + '</b></div>';
        });
        html += '</div><div class="sem-ligne sem-haut"><div class="sem-coin">journée</div>';
        jours.forEach((j) => {
            html += '<div class="sem-cellule" data-date="' + ymd(j) + '">'
                + tout.filter((x) => !dansGrille(x) && tranche(x, j)).map((x) => puce(x)).join('')
                + '</div>';
        });
        html += '</div><div class="sem-ligne sem-grille" style="height:' + (hMax - hMin) * HAUTEUR_HEURE + 'px">'
            + '<div class="sem-heures">';
        for (let h = hMin; h < hMax; h++) html += '<span style="top:' + (h - hMin) * HAUTEUR_HEURE + 'px">' + h + ' h</span>';
        html += '</div>';

        jours.forEach((j) => {
            html += '<div class="sem-col' + (memeJour(j, aujourdhui) ? ' aujourdhui' : '')
                + '" data-date="' + ymd(j) + '" data-hmin="' + hMin + '">';
            const morceaux = [];
            tout.filter(dansGrille).forEach((x) => {
                const t = tranche(x, j);
                if (t) morceaux.push({ x, debut: t.debut, fin: t.fin });
            });
            placerColonnes(morceaux).forEach((m) => {
                const haut = ((m.debut - jour0(j)) / 3.6e6 - hMin) * HAUTEUR_HEURE;
                const hauteur = Math.max(18, (m.fin - m.debut) / 3.6e6 * HAUTEUR_HEURE - 2);
                const x = m.x;
                html += '<button type="button" class="evt-bloc type-' + x.type + (x.fait ? ' fait' : '') + '"'
                    + ' data-genre="' + x.genre + '" data-id="' + x.id + '"'
                    + ' style="top:' + haut + 'px;height:' + hauteur + 'px;left:calc(' + m.gauche + '% + 2px);'
                    + 'width:calc(' + m.largeur + '% - 4px);' + (x.couleur ? '--c:' + echapper(x.couleur) : '') + '"'
                    + ' title="' + echapper(hm(x.debut) + '–' + hm(x.fin) + ' ' + resume(x)) + '">'
                    + '<span class="evt-heure">' + hm(m.debut) + '–' + hm(m.fin) + '</span>'
                    + '<span class="evt-titre">' + echapper(x.titre) + '</span>'
                    + (profType(x) ? '<span class="evt-lieu">' + echapper(profType(x)) + '</span>' : '')
                    + (x.lieu ? '<span class="evt-lieu">' + echapper(x.lieu) + '</span>' : '')
                    + '</button>';
            });
            if (memeJour(j, aujourdhui)) {
                const h = aujourdhui.getHours() + aujourdhui.getMinutes() / 60;
                if (h >= hMin && h < hMax) {
                    html += '<div class="sem-maintenant" style="top:' + (h - hMin) * HAUTEUR_HEURE + 'px"></div>';
                }
            }
            html += '</div>';
        });
        html += '</div></div>';
        elVue.innerHTML = html;

        // Fait défiler jusqu'à l'heure actuelle (ou 8 h).
        const grille = elVue.querySelector('.sem-grille');
        const cible = Math.max(0, (Math.min(aujourdhui.getHours(), 20) - 1 - hMin) * HAUTEUR_HEURE);
        if (grille && elVue.scrollHeight > elVue.clientHeight) elVue.scrollTop = cible;
    }

    // Côte à côte quand des créneaux se chevauchent.
    function placerColonnes(morceaux) {
        morceaux.sort((a, b) => a.debut - b.debut || b.fin - a.fin);
        let groupe = [], finGroupe = 0;
        const fermer = () => {
            const n = Math.max(1, ...groupe.map((m) => m.col + 1));
            groupe.forEach((m) => { m.largeur = 100 / n; m.gauche = m.col * 100 / n; });
            groupe = [];
        };
        let colonnes = [];
        morceaux.forEach((m) => {
            if (groupe.length && m.debut >= finGroupe) { fermer(); colonnes = []; }
            let c = colonnes.findIndex((fin) => fin <= m.debut);
            if (c < 0) { c = colonnes.length; colonnes.push(0); }
            colonnes[c] = m.fin;
            m.col = c;
            groupe.push(m);
            finGroupe = Math.max(finGroupe, +m.fin);
        });
        if (groupe.length) fermer();
        return morceaux;
    }

    function vueMois() {
        const { debut } = periode();
        const tout = elements();
        const aujourdhui = new Date();
        let html = '<div class="mois"><div class="mois-tete">';
        for (let i = 0; i < 7; i++) html += '<div>' + fJourCourt.format(plusJours(debut, i)) + '</div>';
        html += '</div><div class="mois-grille">';
        for (let i = 0; i < 42; i++) {
            const j = plusJours(debut, i);
            const du = tout.filter((x) => tranche(x, j));
            const classes = ['mois-cellule'];
            if (j.getMonth() !== reference.getMonth()) classes.push('hors-mois');
            if (memeJour(j, aujourdhui)) classes.push('aujourdhui');
            html += '<div class="' + classes.join(' ') + '" data-date="' + ymd(j) + '">'
                + '<button type="button" class="mois-num" data-voir="' + ymd(j) + '" title="Voir la semaine">'
                + j.getDate() + '</button>'
                + du.slice(0, 3).map((x) => puce(x)).join('')
                + (du.length > 3 ? '<button type="button" class="mois-plus" data-voir="' + ymd(j) + '">+'
                    + (du.length - 3) + ' autre' + (du.length > 4 ? 's' : '') + '</button>' : '')
                + '</div>';
        }
        elVue.innerHTML = html + '</div></div>';
    }

    function vueListe() {
        const { debut } = periode();
        const tout = elements();
        let html = '<div class="liste-agenda">';
        let vide = true;
        for (let i = 0; i < 14; i++) {
            const j = plusJours(debut, i);
            const du = tout.filter((x) => tranche(x, j));
            if (!du.length) continue;
            vide = false;
            html += '<h3' + (memeJour(j, new Date()) ? ' class="aujourdhui"' : '') + '>'
                + majuscule(fJourLong.format(j)) + '</h3><ul>';
            du.forEach((x) => {
                const horaire = x.journee ? 'Journée'
                    : dansGrille(x) ? hm(x.debut) + ' – ' + hm(x.fin) : hm(x.debut);
                html += '<li><span class="liste-heure">' + horaire + '</span>'
                    + puce(x, false)
                    + '<span class="liste-details">'
                    + echapper([profType(x), x.lieu, x.matiere !== x.titre ? x.matiere : ''].filter(Boolean).join(' · '))
                    + '</span></li>';
            });
            html += '</ul>';
        }
        if (vide) html += '<p class="vide">Rien de prévu sur ces deux semaines.</p>';
        elVue.innerHTML = html + '</div>';
    }

    function afficherTaches() {
        const maintenant = new Date();
        if (!donnees.taches.length) {
            elTaches.innerHTML = '<li class="vide">Aucune tâche.'
                + (LECTURE_SEULE ? '' : ' Ajoute-en une ci-dessus.') + '</li>';
            return;
        }
        elTaches.innerHTML = donnees.taches.map((t) => {
            const d = lire(t.debut);
            let quand = '', retard = false;
            if (d) {
                retard = !t.fait && (t.journee ? plusJours(d, 1) : d) < maintenant;
                quand = memeJour(d, maintenant) ? 'Aujourd\'hui'
                    : memeJour(d, plusJours(maintenant, 1)) ? 'Demain' : fJourMois.format(d);
                if (!t.journee) quand += ' ' + hm(d);
            }
            return '<li class="' + (t.fait ? 'fait' : '') + (retard ? ' retard' : '') + '" data-id="' + t.id + '">'
                + '<input type="checkbox" class="tache-fait"' + (t.fait ? ' checked' : '')
                + (LECTURE_SEULE ? ' disabled title="Coche-la dans l\'Agenda"' : ' title="Fait"') + '>'
                + '<button type="button" class="tache-titre">' + echapper(t.titre) + '</button>'
                + (quand ? '<span class="tache-date">' + quand + '</span>' : '')
                + '</li>';
        }).join('');
    }

    // ---------- Fenêtre (modale) ----------
    function ouvrirModale(titre, corps, boutons = []) {
        const fond = document.createElement('div');
        fond.className = 'modale-fond';
        fond.innerHTML = '<div class="modale petite" role="dialog" aria-label="' + echapper(titre) + '">'
            + '<div class="modale-entete"><h3>' + echapper(titre) + '</h3>'
            + '<button type="button" class="modale-x" title="Fermer (Échap)">✕</button></div>'
            + '<div class="modale-corps"></div><div class="modale-pied"></div></div>';
        fond.querySelector('.modale-corps').append(corps);
        const pied = fond.querySelector('.modale-pied');
        boutons.forEach((b) => pied.append(b));
        if (!boutons.length) pied.remove();
        const fermer = () => { fond.remove(); document.removeEventListener('keydown', clavier); };
        const clavier = (e) => { if (e.key === 'Escape') fermer(); };
        fond.addEventListener('mousedown', (e) => { if (e.target === fond) fermer(); });
        fond.querySelector('.modale-x').addEventListener('click', fermer);
        document.addEventListener('keydown', clavier);
        document.body.append(fond);
        return fermer;
    }
    function bouton(texte, classe, action) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = classe;
        b.textContent = texte;
        b.addEventListener('click', action);
        return b;
    }
    function elementHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;
        return div;
    }

    // Formulaire d'ajout / modification d'un événement perso.
    function ouvrirFormulaire(ev = {}) {
        const existant = !!ev.id;
        const debut = lire(ev.debut);
        const fin = lire(ev.fin);
        const journee = !!ev.journee;
        const options = (liste, choix) => liste.map(([v, t]) =>
            '<option value="' + echapper(v) + '"' + (String(v) === String(choix ?? '') ? ' selected' : '') + '>'
            + echapper(t) + '</option>').join('');

        const form = document.createElement('form');
        form.className = 'form-evt';
        form.innerHTML =
            '<label class="large">Titre <input name="titre" required maxlength="255" value="' + echapper(ev.titre) + '"></label>'
            + '<label>Type <select name="type">' + options(Object.entries(TYPES), ev.type || 'reunion') + '</select></label>'
            + '<label>Matière <select name="matiere_id"><option value="">—</option>'
            + options(MATIERES.map((m) => [m.id, m.nom]), ev.matiere_id) + '</select></label>'
            + '<label>Date <input type="date" name="date" value="' + (debut ? ymd(debut) : '') + '"></label>'
            + '<label class="case"><input type="checkbox" name="journee"' + (journee ? ' checked' : '') + '> Journée entière</label>'
            + '<label class="heures">Début <input type="time" name="heure_debut" value="' + (debut && !journee ? hm(debut) : '') + '"></label>'
            + '<label class="heures">Fin <input type="time" name="heure_fin" value="' + (fin && !journee && fin > debut ? hm(fin) : '') + '"></label>'
            + '<label class="jours">Jusqu\'au <input type="date" name="date_fin" value="'
            + (fin && journee ? ymd(plusJours(fin, -1)) : '') + '"></label>'
            + '<label class="large">Lieu <input name="lieu" maxlength="255" value="' + echapper(ev.lieu) + '"></label>'
            + '<label class="large">Description <textarea name="description" rows="3">' + echapper(ev.description) + '</textarea></label>'
            + '<p class="form-aide astuce-mini large"></p>'
            + '<button type="submit" hidden></button>';

        const majChamps = () => {
            const j = form.journee.checked;
            form.querySelectorAll('.heures').forEach((l) => { l.hidden = j; });
            form.querySelector('.jours').hidden = !j;
            form.querySelector('.form-aide').textContent = form.type.value === 'tache'
                ? 'Une tâche peut n\'avoir ni date ni heure : elle reste dans la liste « À faire ».' : '';
        };
        form.journee.addEventListener('change', majChamps);
        form.type.addEventListener('change', majChamps);
        // Changer l'heure de début décale la fin (même durée).
        form.heure_debut.addEventListener('change', () => {
            if (!form.heure_fin.value || form.heure_fin.value <= form.heure_debut.value) {
                const [h, m] = form.heure_debut.value.split(':').map(Number);
                if (!isNaN(h)) form.heure_fin.value = deux(Math.min(23, h + 1)) + ':' + deux(m);
            }
        });
        majChamps();

        let fermer;
        const enregistrer = async () => {
            if (!form.reportValidity()) return;
            const rep = await api(existant ? 'maj' : 'creer', {
                id: ev.id, titre: form.titre.value, type: form.type.value,
                matiere_id: form.matiere_id.value ? parseInt(form.matiere_id.value, 10) : 0,
                date: form.date.value, journee: form.journee.checked,
                heure_debut: form.heure_debut.value, heure_fin: form.heure_fin.value,
                date_fin: form.date_fin.value, lieu: form.lieu.value, description: form.description.value,
            });
            if (rep.ok) { fermer(); charger(); } else alert(rep.erreur || 'Erreur');
        };
        form.addEventListener('submit', (e) => { e.preventDefault(); enregistrer(); });

        const boutons = [];
        if (existant) {
            boutons.push(bouton('Supprimer', 'btn-secondaire danger', async () => {
                if (!confirm('Supprimer « ' + ev.titre + ' » ?')) return;
                if ((await api('supprimer', { id: ev.id })).ok) { fermer(); charger(); }
            }));
        }
        boutons.push(bouton('Annuler', 'btn-secondaire', () => fermer()));
        boutons.push(bouton('Enregistrer', 'btn-primaire', enregistrer));
        fermer = ouvrirModale(existant ? 'Modifier' : 'Nouvel événement', form, boutons);
        form.titre.focus();
    }

    // Détails d'un cours importé (lecture seule) : lien avec les notes.
    function ouvrirCours(ev) {
        const d = lire(ev.debut), f = lire(ev.fin);
        const horaire = majuscule(fJourLong.format(d))
            + (ev.journee ? '' : ', ' + hm(d) + ' – ' + hm(f));
        const corps = elementHtml(
            '<p class="detail-ligne">🕒 ' + echapper(horaire) + '</p>'
            + (ev.intervenant ? '<p class="detail-ligne">👤 ' + echapper(ev.intervenant) + '</p>' : '')
            + (COULEURS_CATEGORIE[ev.categorie] ? '<p class="detail-ligne">🎓 '
                + (ev.categorie === 'CM' ? 'CM (cours magistral)' : 'TD (travaux dirigés)') + '</p>' : '')
            + (ev.lieu ? '<p class="detail-ligne">📍 ' + echapper(ev.lieu) + '</p>' : '')
            + (ev.type !== 'cours' ? '' : '<p class="detail-ligne">📚 ' + (ev.matiere
                ? '<a href="matiere.php?id=' + ev.matiere_id + '">' + echapper(ev.matiere) + '</a>'
                : '<em>Aucune matière reconnue</em> — ajoute un mot-clé dans « 🔗 Emploi du temps ».') + '</p>')
            + (ev.description ? '<p class="detail-desc">' + echapper(ev.description) + '</p>' : '')
            + '<p class="astuce-mini">Importé de « ' + echapper(ev.source) + ' » : modifie-le dans ton emploi du temps d\'origine.</p>'
            + '<div class="notes-seance" hidden><h4>Notes de cette séance</h4><ul></ul></div>');

        if (ev.matiere_id) {
            lireApi('notes_seance', { matiere_id: ev.matiere_id, date: ymd(d) }).then((rep) => {
                if (!rep.notes || !rep.notes.length) return;
                const bloc = corps.querySelector('.notes-seance');
                bloc.hidden = false;
                bloc.querySelector('ul').innerHTML = rep.notes.map((n) =>
                    '<li><a href="note.php?id=' + n.id + '">' + echapper(n.titre) + '</a></li>').join('');
            });
        }
        ouvrirModale(ev.titre, corps, ev.type !== 'cours' ? [] : [
            bouton('📝 Prendre des notes', 'btn-primaire', async () => {
                const rep = await api('nouvelle_note', { id: ev.id });
                if (rep.ok) location.href = 'note.php?id=' + rep.id;
                else alert(rep.erreur || 'Erreur');
            }),
        ]);
    }

    function ouvrirEcheance(e) {
        const corps = elementHtml(
            '<p class="detail-ligne">🕒 ' + echapper(majuscule(fJourLong.format(lire(e.debut))) + ', ' + hm(lire(e.debut))) + '</p>'
            + (e.matiere ? '<p class="detail-ligne">📚 ' + echapper(e.matiere) + '</p>' : '')
            + (e.description ? '<p class="detail-desc">' + echapper(e.description) + '</p>' : '')
            + '<p>' + (e.fait ? '✅ Terminée' : '⏳ À faire') + '</p>');
        const lien = document.createElement('a');
        lien.className = 'btn-secondaire';
        lien.href = 'echeances.php';
        lien.textContent = 'Gérer les échéances';
        ouvrirModale(e.categorie.toUpperCase() + ' · ' + e.titre, corps, [lien]);
    }

    function ouvrirElement(genre, id) {
        if (genre === 'ech') {
            const e = donnees.echeances.find((x) => x.id === id);
            if (e) ouvrirEcheance(e);
            return;
        }
        const ev = donnees.evenements.find((x) => x.id === id) || donnees.taches.find((x) => x.id === id);
        if (!ev) return;
        if (ev.source) ouvrirCours(ev);
        else if (LECTURE_SEULE) ouvrirDetails(ev);
        else ouvrirFormulaire(ev);
    }

    // Consultation d'un événement perso (page d'accueil).
    function ouvrirDetails(ev) {
        const d = lire(ev.debut), f = lire(ev.fin);
        let quand = d ? majuscule(fJourLong.format(d)) : 'Sans date';
        if (d && !ev.journee) quand += ', ' + hm(d) + (f > d ? ' – ' + hm(f) : '');
        const corps = elementHtml(
            '<p class="detail-ligne">🏷️ ' + echapper(TYPES[ev.type] || ev.type)
                + (ev.type === 'tache' ? (ev.fait ? ' · ✅ faite' : ' · ⏳ à faire') : '') + '</p>'
            + '<p class="detail-ligne">🕒 ' + echapper(quand) + '</p>'
            + (ev.lieu ? '<p class="detail-ligne">📍 ' + echapper(ev.lieu) + '</p>' : '')
            + (ev.matiere ? '<p class="detail-ligne">📚 <a href="matiere.php?id=' + ev.matiere_id + '">'
                + echapper(ev.matiere) + '</a></p>' : '')
            + (ev.description ? '<p class="detail-desc">' + echapper(ev.description) + '</p>' : ''));
        const lien = document.createElement('a');
        lien.className = 'btn-secondaire';
        lien.href = 'agenda.php';
        lien.textContent = '✏️ Modifier dans l\'Agenda';
        ouvrirModale(ev.titre, corps, [lien]);
    }

    // Nouvel événement à une date/heure donnée.
    function nouveau(date, heure) {
        const d = date || ymd(new Date());
        let h = heure;
        if (!h) {
            const n = new Date();
            h = deux(Math.min(22, n.getHours() + 1)) + ':00';
        }
        const [hh, mm] = h.split(':').map(Number);
        ouvrirFormulaire({
            type: 'reunion',
            debut: d + ' ' + h,
            fin: d + ' ' + deux(Math.min(23, hh + 1)) + ':' + deux(mm),
        });
    }

    // ---------- Emploi du temps (sources iCal) ----------
    async function ouvrirSources() {
        const rep = await lireApi('sources');
        const corps = document.createElement('div');
        corps.className = 'sources';

        const listeSources = rep.sources.length ? rep.sources.map((s) =>
            '<li data-id="' + s.id + '"><span class="pastille" style="background:' + echapper(s.couleur) + '"></span>'
            + '<div class="source-info"><b>' + echapper(s.nom) + '</b>'
            + '<small>' + s.nb + ' cours' + (s.synchro ? ' · synchronisé le ' + s.synchro : '')
            + (s.url ? '' : ' · fichier importé') + '</small>'
            + (s.message ? '<small class="erreur">⚠️ ' + echapper(s.message) + '</small>' : '') + '</div>'
            + (s.url ? '<button type="button" class="btn-secondaire" data-synchro>↻ Synchroniser</button>' : '')
            + '<button type="button" class="btn-secondaire danger" data-supprimer title="Supprimer cette source et ses cours">✕</button>'
            + '</li>').join('') : '<li class="vide">Aucun emploi du temps connecté.</li>';

        const lignesMatieres = rep.matieres.map((m) =>
            '<tr><td>' + echapper(m.ue + ' · ' + m.nom) + '</td>'
            + '<td><input data-matiere="' + m.id + '" value="' + echapper(m.mots_cles)
            + '" placeholder="ex. BDD, SGBD"></td>'
            + '<td class="nb">' + m.nb_cours + '</td></tr>').join('');

        corps.innerHTML =
            '<h4>Tes emplois du temps</h4><ul class="liste-sources">' + listeSources + '</ul>'
            + '<h4>Connecter avec un lien iCal</h4>'
            + '<form class="form-source">'
            + '<input name="nom" placeholder="Nom (ex. EDT ING1)" maxlength="100">'
            + '<input name="url" type="url" placeholder="https://… ou webcal://… (.ics)" required>'
            + '<input name="couleur" type="color" value="#0891b2" title="Couleur des cours sans matière">'
            + '<button type="submit" class="btn-primaire">Connecter</button></form>'
            + '<details class="aide-ical"><summary>Où trouver ce lien ?</summary>'
            + '<ul><li>Dans ton emploi du temps en ligne (Celcat, HyperPlanning, ADE…), cherche un bouton '
            + '« S\'abonner », « Exporter », « iCal », « ICS » ou « Synchroniser avec mon agenda ».</li>'
            + '<li><b>Google Agenda</b> : Paramètres → ton agenda → « Adresse secrète au format iCal ».</li>'
            + '<li><b>Outlook</b> : Paramètres → Calendrier → Calendriers partagés → Publier → lien ICS.</li>'
            + '<li>Le lien est re-synchronisé automatiquement toutes les 6 h quand tu ouvres l\'agenda.</li>'
            + '<li>Si le site demande de se connecter pour voir le calendrier, le lien ne marchera pas : '
            + 'télécharge le fichier .ics et importe-le ci-dessous.</li></ul></details>'
            + '<h4>… ou importer un fichier .ics</h4>'
            + '<form class="form-fichier"><input type="file" name="fichier" accept=".ics,text/calendar" required>'
            + '<button type="submit" class="btn-secondaire">Importer</button></form>'
            + '<h4>Relier les cours à tes matières</h4>'
            + '<p class="astuce-mini">Un cours est rattaché à une matière quand son intitulé contient le nom de la matière '
            + 'ou un de ses mots-clés (séparés par des virgules, sans tenir compte des accents ni des majuscules). '
            + 'Tu peux alors ouvrir la matière ou prendre des notes depuis le cours.</p>'
            + (rep.non_reconnus.length
                ? '<p class="non-reconnus"><b>Cours sans matière</b> : choisis la matière de chaque code '
                    + '(une suggestion est pré-remplie quand le code y ressemble), puis « Enregistrer ».</p>'
                    + '<table class="table-mots-cles table-codes"><tbody>'
                    + rep.non_reconnus.map((n) => '<tr><td><code>' + echapper(n.titre) + '</code> <small>('
                        + n.nb + ' cours)</small></td><td><select data-code="' + echapper(n.titre) + '">'
                        + '<option value="">— Choisir la matière —</option>'
                        + rep.matieres.map((m) => '<option value="' + m.id + '"' + (m.id === n.suggestion ? ' selected' : '')
                            + '>' + echapper(m.ue + ' · ' + m.nom) + '</option>').join('')
                        + '</select></td></tr>').join('')
                    + '</tbody></table>'
                : '')
            + '<table class="table-mots-cles"><thead><tr><th>Matière</th><th>Mots-clés</th><th>Cours</th></tr></thead>'
            + '<tbody>' + lignesMatieres + '</tbody></table>'
            + '<p class="statut-sources astuce-mini"></p>';

        const statut = corps.querySelector('.statut-sources');
        const occupe = (texte) => { statut.textContent = texte; };
        let fermer;
        const rafraichir = () => { fermer(); ouvrirSources(); charger(); };

        corps.querySelector('.liste-sources').addEventListener('click', async (e) => {
            const li = e.target.closest('li[data-id]');
            if (!li) return;
            const id = parseInt(li.dataset.id, 10);
            if (e.target.closest('[data-synchro]')) {
                occupe('Synchronisation…');
                const r = await api('synchroniser', { id });
                if (r.ok) rafraichir(); else occupe('⚠️ ' + (r.erreur || 'Erreur'));
            } else if (e.target.closest('[data-supprimer]')) {
                if (!confirm('Supprimer cet emploi du temps et tous ses cours de l\'agenda ?')) return;
                if ((await api('source_supprimer', { id })).ok) rafraichir();
            }
        });
        corps.querySelector('.form-source').addEventListener('submit', async (e) => {
            e.preventDefault();
            const f = e.target;
            occupe('Connexion et import en cours…');
            const r = await api('source_ajouter', { nom: f.nom.value, url: f.url.value, couleur: f.couleur.value });
            if (r.ok) { alert(r.nb + ' créneaux importés.'); rafraichir(); } else occupe('⚠️ ' + (r.erreur || 'Erreur'));
        });
        corps.querySelector('.form-fichier').addEventListener('submit', async (e) => {
            e.preventDefault();
            const envoi = new FormData(e.target);
            envoi.append('csrf', JETON);
            occupe('Import en cours…');
            try {
                const r = await (await fetch('api/agenda.php?action=importer', { method: 'POST', body: envoi })).json();
                if (r.ok) { alert(r.nb + ' créneaux importés.'); rafraichir(); } else occupe('⚠️ ' + (r.erreur || 'Erreur'));
            } catch (err) {
                occupe('⚠️ Échec de l\'envoi.');
            }
        });

        const enregistrerMots = async () => {
            // Code choisi pour une matière = ajouté à ses mots-clés.
            corps.querySelectorAll('select[data-code]').forEach((sel) => {
                const champ = sel.value && corps.querySelector('[data-matiere="' + sel.value + '"]');
                if (!champ) return;
                const actuels = champ.value.split(',').map((x) => x.trim()).filter(Boolean);
                if (!actuels.includes(sel.dataset.code)) champ.value = [...actuels, sel.dataset.code].join(', ');
            });
            const mots = {};
            corps.querySelectorAll('[data-matiere]').forEach((i) => { mots[i.dataset.matiere] = i.value; });
            occupe('Enregistrement…');
            if ((await api('mots_cles', { mots_cles: mots })).ok) rafraichir();
            else occupe('⚠️ Erreur');
        };
        fermer = ouvrirModale('🔗 Emploi du temps', corps, [
            bouton('Fermer', 'btn-secondaire', () => fermer()),
            bouton('Enregistrer les mots-clés', 'btn-primaire', enregistrerMots),
        ]);
        return rep;
    }

    // Re-synchronise en arrière-plan les sources trop anciennes.
    async function synchroAuto() {
        let rep;
        try { rep = await lireApi('sources'); } catch (e) { return; }
        const aFaire = (rep.sources || []).filter((s) => s.a_synchroniser);
        if (!aFaire.length) return;
        info('Mise à jour de l\'emploi du temps…');
        const erreurs = [];
        for (const s of aFaire) {
            const r = await api('synchroniser', { id: s.id }).catch(() => ({ erreur: 'réseau' }));
            if (!r.ok) erreurs.push(s.nom + ' : ' + (r.erreur || 'erreur'));
        }
        if (erreurs.length) info('⚠️ ' + erreurs.join(' — '), true); else info('');
        charger();
    }

    // ---------- Événements de la page ----------
    racine.querySelector('.agenda-nav').addEventListener('click', (e) => {
        const b = e.target.closest('[data-nav]');
        if (!b) return;
        naviguer(parseInt(b.dataset.nav, 10));
    });
    function naviguer(sens) {
        if (sens === 0) reference = new Date();
        else if (vue === 'mois') reference = new Date(reference.getFullYear(), reference.getMonth() + sens, 1);
        else reference = plusJours(reference, sens * (vue === 'liste' ? 14 : 7));
        charger();
    }
    racine.querySelector('.agenda-vues').addEventListener('click', (e) => {
        const b = e.target.closest('[data-vue]');
        if (!b) return;
        vue = b.dataset.vue;
        try { localStorage.setItem(CLE_VUE, vue); } catch (err) {}
        charger();
    });
    document.getElementById('btn-ajouter')?.addEventListener('click', () => {
        const auj = new Date();
        const { debut, fin } = periode();
        nouveau(auj >= debut && auj < fin ? ymd(auj) : ymd(debut));
    });
    document.getElementById('btn-sources')?.addEventListener('click', ouvrirSources);

    elVue.addEventListener('click', (e) => {
        const evt = e.target.closest('[data-genre]');
        if (evt) { ouvrirElement(evt.dataset.genre, parseInt(evt.dataset.id, 10)); return; }
        const voir = e.target.closest('[data-voir]');
        if (voir) {
            reference = lire(voir.dataset.voir + ' 12:00');
            vue = 'semaine';
            charger();
            return;
        }
        if (LECTURE_SEULE) return;   // accueil : pas d'ajout
        // Clic dans un créneau vide de la semaine : heure arrondie à la demi-heure.
        const col = e.target.closest('.sem-col');
        if (col) {
            const y = e.clientY - col.getBoundingClientRect().top;
            const minutes = Math.floor(y / HAUTEUR_HEURE * 2) * 30 + parseInt(col.dataset.hmin, 10) * 60;
            nouveau(col.dataset.date, deux(Math.min(23, Math.floor(minutes / 60))) + ':' + deux(minutes % 60));
            return;
        }
        const cellule = e.target.closest('.sem-cellule, .mois-cellule');
        if (cellule) nouveau(cellule.dataset.date, '09:00');
    });

    // Tâches : ajout rapide, cocher, ouvrir.
    document.getElementById('form-tache')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const rep = await api('creer', { titre: f.titre.value, type: 'tache', date: f.date.value, journee: true });
        if (rep.ok) { f.reset(); charger(); } else alert(rep.erreur || 'Erreur');
    });
    elTaches.addEventListener('click', async (e) => {
        const li = e.target.closest('li[data-id]');
        if (!li) return;
        const id = parseInt(li.dataset.id, 10);
        if (e.target.classList.contains('tache-fait')) {
            if (LECTURE_SEULE) return;
            await api('basculer', { id });
            charger();
        } else if (e.target.classList.contains('tache-titre')) {
            ouvrirElement('evt', id);
        }
    });

    // Raccourcis clavier : ← → (période), T (aujourd'hui).
    // (pas sur l'accueil : les flèches y font défiler la page)
    document.addEventListener('keydown', (e) => {
        if (LECTURE_SEULE) return;
        if (document.querySelector('.modale-fond') || e.ctrlKey || e.metaKey || e.altKey) return;
        if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '')) return;
        if (e.key === 'ArrowLeft') naviguer(-1);
        else if (e.key === 'ArrowRight') naviguer(1);
        else if (e.key === 't' || e.key === 'T') naviguer(0);
    });

    charger().then(synchroAuto);
})();
