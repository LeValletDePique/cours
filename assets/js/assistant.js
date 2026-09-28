/* ============================================================
   Assistant IA : bouton « 🤖 Aide IA » en bas à droite de chaque page.
   La conversation est gardée pour l'onglet (sessionStorage) et envoyée
   à api/assistant.php. Sur une note, on peut joindre la note ouverte.
   ============================================================ */

(function () {
    const bouton  = document.getElementById('ia-bouton');
    const panneau = document.getElementById('ia-panneau');
    if (!bouton || !panneau) return;

    const fil      = panneau.querySelector('.ia-fil');
    const saisie   = panneau.querySelector('textarea');
    const envoyer  = panneau.querySelector('.ia-envoyer');
    const joindre  = panneau.querySelector('#ia-joindre');
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const editeur  = document.querySelector('.editeur');
    const noteId   = editeur ? parseInt(editeur.dataset.noteId, 10) : 0;
    const page     = (location.pathname.split('/').pop() || 'index.php').replace(/\.php$/, '');
    const CLE      = 'ia-conversation';

    // Sur une note : case « joindre ma note » visible et cochée par défaut.
    if (noteId && joindre) {
        joindre.closest('label').hidden = false;
        joindre.checked = true;
    }

    let conversation = [];
    try { conversation = JSON.parse(sessionStorage.getItem(CLE) || '[]'); } catch (e) {}
    function memoriser() {
        try { sessionStorage.setItem(CLE, JSON.stringify(conversation.slice(-30))); } catch (e) {}
    }

    function echapper(s) {
        return s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
    }

    function afficher(role, texte) {
        const div = document.createElement('div');
        div.className = 'ia-msg ' + role;
        if (role === 'assistant' && window.rendreMarkdown && window.marked) {
            div.classList.add('markdown');
            window.rendreMarkdown(texte, div);
        } else if (role === 'assistant' && window.marked && window.DOMPurify) {
            div.innerHTML = DOMPurify.sanitize(marked.parse(texte));
        } else if (role === 'assistant') {
            div.innerHTML = echapper(texte).replace(/\n/g, '<br>');
        } else {
            div.textContent = texte;
        }
        fil.appendChild(div);
        fil.scrollTop = fil.scrollHeight;
        return div;
    }

    const SUGGESTIONS = [
        'Mon gras ou mes puces ne s\'affichent pas',
        'Comment écrire ∀ et ∃ ?',
        'Comment faire un tableau ?',
        'Explique-moi une notion de ma note',
    ];
    function accueil() {
        fil.innerHTML = '';
        afficher('assistant', 'Salut ! Je peux t\'aider à **utiliser la plateforme** (mise en forme, '
            + 'maths, tableaux, pseudo-code…) ou à **comprendre ton cours**. Pose ta question 🙂');
        const sug = document.createElement('div');
        sug.className = 'ia-suggestions';
        SUGGESTIONS.forEach((s) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = s;
            b.addEventListener('click', () => { saisie.value = s; envoyerQuestion(); });
            sug.appendChild(b);
        });
        fil.appendChild(sug);
        conversation.forEach((m) => afficher(m.role, m.content));
    }

    async function envoyerQuestion() {
        const question = saisie.value.trim();
        if (!question || envoyer.disabled) return;
        saisie.value = '';
        conversation.push({ role: 'user', content: question });
        memoriser();
        afficher('user', question);
        const attente = afficher('assistant', '…');
        envoyer.disabled = true;

        try {
            const rep = await fetch('api/assistant.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
                body: JSON.stringify({
                    messages: conversation,
                    page,
                    note_id: noteId && joindre && joindre.checked ? noteId : 0,
                }),
            });
            const donnees = await rep.json().catch(() => ({ erreur: 'Réponse illisible du serveur.' }));
            attente.remove();
            if (donnees.ok) {
                conversation.push({ role: 'assistant', content: donnees.reponse });
                memoriser();
                afficher('assistant', donnees.reponse);
            } else {
                // La question non traitée est retirée pour pouvoir la renvoyer.
                conversation.pop();
                memoriser();
                afficher('erreur', donnees.erreur || 'Erreur inconnue.');
                saisie.value = question;
            }
        } catch (e) {
            attente.remove();
            conversation.pop();
            memoriser();
            afficher('erreur', 'Impossible de contacter le serveur.');
            saisie.value = question;
        } finally {
            envoyer.disabled = false;
            saisie.focus();
        }
    }

    bouton.addEventListener('click', () => {
        panneau.classList.toggle('ouvert');
        if (panneau.classList.contains('ouvert')) {
            if (!fil.children.length) accueil();
            saisie.focus();
        }
    });
    panneau.querySelector('.ia-fermer').addEventListener('click', () => panneau.classList.remove('ouvert'));
    panneau.querySelector('.ia-effacer').addEventListener('click', () => {
        conversation = [];
        memoriser();
        accueil();
    });
    envoyer.addEventListener('click', envoyerQuestion);
    saisie.addEventListener('keydown', (e) => {
        // Entrée = envoyer, Maj+Entrée = retour à la ligne.
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); envoyerQuestion(); }
    });
})();
