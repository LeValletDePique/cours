/* ============================================================
   Accueil : ajout rapide de tâche (langage naturel) et cases à cocher.
   Données : api/agenda.php (tâches = événements de type « tache »).
   ============================================================ */
(function () {
    const form = document.getElementById('form-ajout-tache');
    const liste = document.getElementById('liste-taches-accueil');
    if (!form || !liste) return;
    const champ = document.getElementById('ajout-tache');
    const compteur = document.getElementById('taches-compteur');
    const MATIERES = JSON.parse(document.getElementById('matieres-taches')?.textContent || '[]');
    const JOURS_COURTS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    const echapper = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const deux = (n) => String(n).padStart(2, '0');

    function majCompteur() {
        const n = liste.querySelectorAll('.mc-task:not(.mc-task--done)').length;
        compteur.textContent = n ? n + (n > 1 ? ' ouvertes' : ' ouverte') : 'tout est fait';
    }

    // « demain » (bientôt), « ven. 2 »…
    function quand(date) {
        if (!date) return '';
        const d = new Date(date + 'T00:00:00');
        const auj = new Date(); auj.setHours(0, 0, 0, 0);
        const ecart = Math.round((d - auj) / 864e5);
        if (ecart === 0) return '<span class="mc-when mc-when--soon">aujourd\'hui</span>';
        if (ecart === 1) return '<span class="mc-when mc-when--soon">demain</span>';
        return '<span class="mc-when">' + JOURS_COURTS[d.getDay()] + ' ' + d.getDate() + '</span>';
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const texte = champ.value.trim();
        if (!texte) return;
        const t = analyserTache(texte, MATIERES);
        champ.disabled = true;
        try {
            const rep = await apiJson('api/agenda.php?action=creer', 'POST', {
                titre: t.titre, type: 'tache', date: t.date, journee: true,
                matiere_id: t.matiere ? t.matiere.id : 0,
            });
            if (!rep.ok) { alert(rep.erreur || 'Impossible d\'ajouter la tâche.'); return; }
            const meta = (t.matiere ? '<span class="mc-dot"></span><span class="mc-task__matiere">'
                + echapper(t.matiere.nom) + '</span>' : '')
                + (t.matiere && t.date ? '<span aria-hidden="true">·</span>' : '') + quand(t.date);
            const li = document.createElement('li');
            li.className = 'mc-task ' + (t.matiere ? t.matiere.classe : 'mc-ue-autre');
            li.dataset.id = rep.id;
            li.dataset.meta = meta;
            li.innerHTML = '<input type="checkbox" class="mc-check" aria-label="Terminer">'
                + '<div><div class="mc-task__text">' + echapper(t.titre) + '</div>'
                + '<div class="mc-task__meta">' + meta + '</div></div>';
            liste.prepend(li);
            document.getElementById('taches-vide')?.remove();
            champ.value = '';
            majCompteur();
        } finally {
            champ.disabled = false;
            champ.focus();
        }
    });

    liste.addEventListener('change', async (e) => {
        if (!e.target.classList.contains('mc-check')) return;
        const li = e.target.closest('.mc-task');
        const rep = await apiJson('api/agenda.php?action=basculer', 'POST', { id: parseInt(li.dataset.id, 10) });
        if (!rep.ok) { e.target.checked = !e.target.checked; return; }
        li.classList.toggle('mc-task--done', rep.fait);
        e.target.checked = rep.fait;
        e.target.setAttribute('aria-label', rep.fait ? 'Rouvrir' : 'Terminer');
        const d = rep.date_fait ? new Date(rep.date_fait.replace(' ', 'T')) : null;
        li.querySelector('.mc-task__meta').innerHTML = rep.fait
            ? (d ? 'Fait à ' + deux(d.getHours()) + ':' + deux(d.getMinutes()) : 'Fait')
            : li.dataset.meta;
        majCompteur();
    });
})();
