/* ============================================================
   Éditeur de note : rendu Markdown en direct, auto-save,
   favoris, tags et fichiers joints.
   ============================================================ */

(function () {
    const editeur = document.querySelector('.editeur');
    if (!editeur) return;

    const noteId    = parseInt(editeur.dataset.noteId, 10);
    const elTitre   = document.getElementById('note-titre');
    const elMatiere = document.getElementById('note-matiere');
    const elContenu = document.getElementById('note-contenu');
    const elApercu  = document.getElementById('apercu');
    const elStatut  = document.getElementById('statut-save');
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ---------- Client AJAX générique (jeton CSRF inclus) ----------
    async function api(action, donnees, ressource = 'notes') {
        const rep = await fetch('api/' + ressource + '.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify(donnees),
        });
        return rep.json();
    }

    // ---------- Rendu Markdown (logique partagée : voir rendu.js) ----------
    function rendreApercu() {
        window.rendreMarkdown(elContenu.value, elApercu);
    }

    // ---------- Auto-save (anti-rebond) ----------
    let minuteurSave = null, minuteurRendu = null, modifie = false;

    function planifierRendu() {
        clearTimeout(minuteurRendu);
        minuteurRendu = setTimeout(rendreApercu, 250);
    }
    function marquerModifie() {
        modifie = true;
        elStatut.textContent = 'Modifié…';
        elStatut.className = 'statut-save modifie';
        clearTimeout(minuteurSave);
        minuteurSave = setTimeout(sauvegarder, 1000);
    }
    // force = true : enregistre même sans modification (Ctrl+S).
    async function sauvegarder(force = false) {
        if (!modifie && !force) return;
        clearTimeout(minuteurSave);
        elStatut.textContent = 'Enregistrement…';
        elStatut.className = 'statut-save';
        try {
            const rep = await api('maj', {
                id: noteId,
                titre: elTitre.value,
                contenu: elContenu.value,
                matiere_id: elMatiere.value ? parseInt(elMatiere.value, 10) : 0,
            });
            if (rep.ok) {
                modifie = false;
                elStatut.textContent = 'Enregistré à ' + rep.date_modification;
                elStatut.className = 'statut-save ok';
                document.title = (elTitre.value || 'Sans titre') + ' · Mes Cours';
            } else {
                elStatut.textContent = 'Erreur d\'enregistrement';
                elStatut.className = 'statut-save erreur';
            }
        } catch (e) {
            elStatut.textContent = 'Hors ligne — non enregistré';
            elStatut.className = 'statut-save erreur';
        }
    }

    elContenu.addEventListener('input', () => { marquerModifie(); planifierRendu(); });
    elTitre.addEventListener('input', marquerModifie);
    elMatiere.addEventListener('change', () => { marquerModifie(); sauvegarder(); });

    // Barre d'outils, raccourcis (Ctrl+S, Ctrl+Z…), tableaux : voir outils-editeur.js
    const outils = window.installerOutilsEditeur({
        zone: elContenu,
        barre: document.getElementById('barre-outils'),
        auChangement: () => { marquerModifie(); planifierRendu(); },
        auEnregistrement: () => sauvegarder(true),
    });

    // Sauvegarde de secours en quittant la page.
    window.addEventListener('beforeunload', () => {
        if (!modifie) return;
        fetch('api/notes.php?action=maj', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF },
            body: JSON.stringify({
                id: noteId, titre: elTitre.value, contenu: elContenu.value,
                matiere_id: elMatiere.value ? parseInt(elMatiere.value, 10) : 0,
            }),
            keepalive: true,
        });
    });

    // ---------- Favori (épingle) ----------
    const btnEpingle = document.getElementById('btn-epingler');
    btnEpingle.addEventListener('click', async () => {
        const rep = await api('epingler', { id: noteId });
        if (rep.ok) {
            btnEpingle.dataset.epingle = rep.epingle;
            btnEpingle.textContent = rep.epingle ? '⭐' : '☆';
        }
    });

    // ---------- Suppression (corbeille) ----------
    document.getElementById('btn-supprimer').addEventListener('click', async () => {
        if (!confirm('Envoyer cette note à la corbeille ?')) return;
        const rep = await api('supprimer', { id: noteId });
        if (rep.ok) {
            window.location.href = elMatiere.value
                ? 'matiere.php?id=' + elMatiere.value : 'index.php';
        }
    });

    // ---------- Tags ----------
    const listeTags = document.getElementById('liste-tags');
    const inputTag  = document.getElementById('tag-nom');

    async function ajouterTag() {
        const nom = inputTag.value.trim();
        if (!nom) return;
        const rep = await api('attacher', { note_id: noteId, nom }, 'tags');
        if (rep.ok) {
            // Évite le doublon d'affichage.
            if (!listeTags.querySelector('[data-tag-id="' + rep.tag_id + '"]')) {
                const chip = document.createElement('span');
                chip.className = 'chip';
                chip.dataset.tagId = rep.tag_id;
                chip.style.background = rep.couleur;
                chip.innerHTML = rep.nom.replace(/[&<>]/g, (c) =>
                    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]))
                    + ' <button type="button" class="chip-x" title="Retirer">✕</button>';
                listeTags.appendChild(chip);
            }
            inputTag.value = '';
        } else if (rep.erreur) {
            alert(rep.erreur);
        }
    }
    document.getElementById('tag-ajouter').addEventListener('click', ajouterTag);
    inputTag.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); ajouterTag(); }
    });
    listeTags.addEventListener('click', async (e) => {
        if (!e.target.classList.contains('chip-x')) return;
        const chip = e.target.closest('.chip');
        const rep = await api('detacher',
            { note_id: noteId, tag_id: parseInt(chip.dataset.tagId, 10) }, 'tags');
        if (rep.ok) chip.remove();
    });

    // ---------- Fichiers joints ----------
    const inputFichier = document.getElementById('fichier-input');
    const listeFichiers = document.getElementById('liste-fichiers');
    const statutFichier = document.getElementById('fichier-statut');
    const echapper = (t) => t.replace(/[&<>"]/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    // Envoie un fichier ; une image est aussi affichée dans la note.
    async function envoyerFichier(fichier, insererImage) {
        const donnees = new FormData();
        donnees.append('fichier', fichier);
        donnees.append('note_id', noteId);
        donnees.append('csrf', CSRF);

        statutFichier.textContent = 'Envoi…';
        try {
            const rep = await (await fetch('api/upload.php', {
                method: 'POST', body: donnees,
            })).json();

            if (!rep.ok) { statutFichier.textContent = rep.erreur || 'Échec.'; return; }

            const image = /^image\//.test(fichier.type);
            const lien = 'telecharger.php?id=' + rep.id;
            const li = document.createElement('li');
            li.dataset.fichierId = rep.id;
            li.dataset.nom = rep.nom;
            if (image) li.dataset.image = '1';
            li.innerHTML = (image ? '<img class="miniature" src="' + lien + '" alt="" title="Agrandir">' : '')
                + '<a href="' + lien + '" target="_blank">' + echapper(rep.nom) + '</a>'
                + ' <span class="taille">' + Math.round(rep.taille / 1024) + ' Ko</span>'
                + (image ? ' <button type="button" class="fichier-inserer btn-secondaire"'
                    + ' title="Afficher l\'image dans la note, à l\'endroit du curseur">Insérer</button>' : '')
                + ' <button type="button" class="fichier-x" title="Supprimer">✕</button>';
            listeFichiers.appendChild(li);
            statutFichier.textContent = '';
            if (image && insererImage) insererImageNote(rep.id, rep.nom);
        } catch (e) {
            statutFichier.textContent = 'Échec de l\'envoi.';
        }
    }

    // Ajoute ![nom](telecharger.php?id=…) à l'endroit du curseur.
    function insererImageNote(id, nom) {
        const texte = nom.replace(/\.[^.]+$/, '').replace(/[\[\]\\]/g, '') || 'image';
        const md = '![' + texte + '](telecharger.php?id=' + id + ')';
        outils.insererBloc(md, md.length);
    }

    document.getElementById('fichier-envoyer').addEventListener('click', async () => {
        const fichier = inputFichier.files[0];
        if (!fichier) { statutFichier.textContent = 'Choisis un fichier.'; return; }
        await envoyerFichier(fichier, true);
        inputFichier.value = '';
    });

    // Coller (Ctrl+V) ou glisser une image dans la zone de texte.
    function imagesDe(transfert) {
        return transfert ? [...transfert.files].filter((f) => /^image\//.test(f.type)) : [];
    }
    elContenu.addEventListener('paste', (e) => {
        // Un copier depuis Excel contient aussi une image : le texte prime.
        if (e.clipboardData.getData('text/plain')) return;
        const images = imagesDe(e.clipboardData);
        if (!images.length) return;
        e.preventDefault();
        images.forEach((f) => envoyerFichier(f, true));
    });
    elContenu.addEventListener('dragover', (e) => {
        if ([...e.dataTransfer.types].includes('Files')) e.preventDefault();
    });
    elContenu.addEventListener('drop', (e) => {
        const images = imagesDe(e.dataTransfer);
        if (!images.length) return;
        e.preventDefault();
        images.forEach((f) => envoyerFichier(f, true));
    });

    listeFichiers.addEventListener('click', async (e) => {
        const li = e.target.closest('li');
        if (!li) return;
        if (e.target.classList.contains('miniature')) {
            ouvrirVisionneuse(e.target.src);
        } else if (e.target.classList.contains('fichier-inserer')) {
            insererImageNote(li.dataset.fichierId, li.dataset.nom || '');
        } else if (e.target.classList.contains('fichier-x')) {
            if (!confirm('Supprimer ce fichier ?')) return;
            const rep = await api('supprimer',
                { id: parseInt(li.dataset.fichierId, 10) }, 'upload');
            if (rep.ok) li.remove();
        }
    });

    // ---------- Visionneuse : clic sur une image = plein écran ----------
    // Clic sur l'image : bascule ajusté à l'écran / taille réelle (zoom).
    function ouvrirVisionneuse(src) {
        const fond = document.createElement('div');
        fond.className = 'visionneuse';
        fond.innerHTML = '<button type="button" class="visionneuse-x" title="Fermer (Échap)">✕</button>';
        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        fond.appendChild(img);
        const fermer = () => { fond.remove(); document.removeEventListener('keydown', clavier); };
        const clavier = (e) => { if (e.key === 'Escape') fermer(); };
        fond.addEventListener('click', (e) => {
            if (e.target === img) img.classList.toggle('taille-reelle');
            else fermer();
        });
        document.addEventListener('keydown', clavier);
        document.body.appendChild(fond);
    }
    elApercu.addEventListener('click', (e) => {
        if (e.target.tagName === 'IMG' && !e.target.closest('a')) ouvrirVisionneuse(e.target.src);
    });

    // ---------- Bascule des vues ----------
    editeur.classList.add('vue-deux');
    document.querySelectorAll('.editeur-onglets button').forEach((btn) => {
        btn.addEventListener('click', () => {
            editeur.classList.remove('vue-deux', 'vue-edition', 'vue-apercu');
            editeur.classList.add('vue-' + btn.dataset.vue);
            document.querySelectorAll('.editeur-onglets button')
                .forEach((b) => b.classList.remove('actif'));
            btn.classList.add('actif');
        });
    });

    // ---------- Thème de coloration du code ----------
    function majThemeCode() {
        const sombre = document.documentElement.getAttribute('data-theme') === 'sombre';
        const clair = document.getElementById('hljs-clair');
        const noir  = document.getElementById('hljs-sombre');
        if (clair) clair.disabled = sombre;
        if (noir)  noir.disabled = !sombre;
    }
    majThemeCode();
    new MutationObserver(majThemeCode).observe(document.documentElement,
        { attributes: true, attributeFilter: ['data-theme'] });

    // ---------- Premier rendu ----------
    rendreApercu();
    // MathJax se charge en asynchrone : on re-rend une fois la page complète
    // pour que les formules de la note s'affichent dès l'ouverture.
    window.addEventListener('load', rendreApercu);
})();
