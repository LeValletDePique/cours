</main>
<?php if (utilisateur_connecte()): ?>
<!-- Assistant IA (voir assets/js/assistant.js et api/assistant.php) -->
<button type="button" id="ia-bouton" class="ia-bouton" title="Un problème ? Demande à l'assistant">🤖 Aide IA</button>
<section id="ia-panneau" class="ia-panneau" aria-label="Assistant IA">
    <div class="ia-entete">
        <h3>🤖 Assistant</h3>
        <div>
            <button type="button" class="ia-effacer" title="Nouvelle conversation">Effacer</button>
            <button type="button" class="ia-fermer" title="Fermer">✕</button>
        </div>
    </div>
    <div class="ia-fil"></div>
    <div class="ia-saisie">
        <textarea rows="2" placeholder="Pose ta question… (Entrée pour envoyer)"></textarea>
        <div class="ia-saisie-bas">
            <label hidden><input type="checkbox" id="ia-joindre"> Joindre ma note</label>
            <span></span>
            <button type="button" class="ia-envoyer">Envoyer</button>
        </div>
    </div>
</section>
<script defer src="assets/js/assistant.js"></script>
<?php endif; ?>
<footer class="pied">
    <p>Plateforme de notes de cours · Ing 1· CY Tech Pau</p>
</footer>
</body>
</html>
