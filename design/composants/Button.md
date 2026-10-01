# Button

Bouton d'action : `mc-btn`, avec les variantes `mc-btn--primary`, `mc-btn--ghost`, et les tailles `mc-btn--sm` / `mc-btn--lg`.

- **Un seul `mc-btn--primary` visible par écran.** Sur l'accueil, c'est « Prendre des notes » du cours en cours ; ailleurs, l'action qui fait avancer (Enregistrer, Nouvelle note).
- Secondaire (`mc-btn`) pour les alternatives, `mc-btn--ghost` pour Annuler et les actions de ligne.
- `mc-btn--sm` dans les listes (ligne de timeline, état vide), `mc-btn--lg` seulement dans la carte « En cours ».
- Le raccourci clavier se met dans le bouton avec `<span class="mc-kbd">N</span>` : ça apprend les raccourcis sans tutoriel.
- Libellé = verbe à l'infinitif + objet, 1 à 3 mots (« Prendre des notes », « Ajouter une échéance »). Icône Lucide à gauche, 15px (`mc-ico-sm`).
- Fournir : le libellé, l'icône éventuelle, le `kbd` éventuel.
