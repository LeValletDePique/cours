# WeekStreak

Carte « Ta semaine » : la série de jours avec des notes et l'objectif du jour.

- Une case par jour (L → D). `mc-day--done` = au moins une note créée ou modifiée ce jour ; `mc-day--today` = anneau autour d'aujourd'hui.
- Chiffre en `mc-numeral` (« 3 jours »), phrase courte à côté. Jamais de confettis ni d'emoji : la récompense, c'est la case qui se remplit.
- Objectif : « Cours notés aujourd'hui x / y », calculé depuis l'agenda. Le week-end, montrer « Révision : x fiches » à la place.
- Une série cassée repart à 0 sans message culpabilisant ; afficher plutôt « Meilleure série : n jours ».
