# AgendaEvent

Un créneau dans la vue semaine de l'Agenda.

- Fond `--ue-soft`, nom en `--ue` : la couleur dit l'UE. Le type de cours passe dans `mc-tag` (`mc-tag--cm` plein pour un CM), plus de rouge/bleu.
- `mc-event--past` : fond `surface-sunk`, nom atténué. `mc-event--now` : anneau `accent` 2px.
- `mc-event--perso` : Zénith, réunions, sport : fond `surface`, bordure pointillée. Visible mais en retrait des cours.
- Hauteur proportionnelle à la durée ; le contenu se coupe, jamais de texte qui déborde.
