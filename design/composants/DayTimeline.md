# DayTimeline

La journée en liste : chaque créneau de l'agenda avec son état de prise de notes.

- Une ligne `mc-tl__item` par créneau, dans l'ordre : horaire (mono), point UE, matière, `mc-tag`, salle, puis l'état à droite.
- États : `--past` (matière en `ink-muted`) avec soit `mc-ok` « 1 note » si noté, soit un bouton `mc-btn--sm` « + Notes » pour rattraper ; `--now` (fond `accent-soft`, libellé « Maintenant ») ; à venir : « dans 2 h 33 ».
- `--perso` pour ce qui n'est pas un cours (Zénith, réunion d'asso) : poids normal, mention « perso ».
- Remplace la grille semaine sur l'accueil ; la semaine complète reste dans Agenda.
