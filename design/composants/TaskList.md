# TaskList

Liste « À faire » avec ajout rapide.

- `mc-quickadd` en haut, toujours visible, raccourci `T`. Comprend le langage naturel : « pour vendredi » fixe l'échéance, un nom de matière la rattache.
- Chaque `mc-task` : case `mc-check`, texte, puis méta (point UE · matière · quand). `mc-when--soon` (couleur `warning`) si c'est pour aujourd'hui ou demain, avec le mot.
- Cochée : `mc-task--done`, reste visible jusqu'au lendemain puis disparaît.
- Fournir : texte, matière éventuelle, date éventuelle, état.
