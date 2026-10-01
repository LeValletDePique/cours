# NowCard

La carte héros de l'accueil : le cours en cours (ou le prochain) avec, en un clic, sa note du jour.

- Toujours en premier, en haut à gauche. Hors cours : titre = prochain cours, sur-titre « Dans 25 min » ; après le dernier cours : « Journée terminée » et l'action devient « Relire les notes du jour ».
- L'action principale `mc-btn--primary` « Prendre des notes » crée la note **pré-remplie** : titre `<Matière> (<CM|TD|TP>) – jj/mm/aaaa`, matière et UE déjà choisies. Afficher ce titre dessous (`mc-now__hint`) pour que ce soit prévisible.
- Action secondaire : reprendre la dernière note de cette matière.
- `mc-progress` = avancement du créneau ; `mc-live` = statut en capitales avec le temps restant.
- Pied `mc-next` : le cours suivant, une ligne.
- Fournir : le créneau (matière, UE, type, salle, début, fin), le nombre de notes de la matière, la dernière note.
