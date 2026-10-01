# UEBadge

Repère de couleur d'une UE : badge `mc-ue`, point `mc-dot`, et étiquette de type de cours `mc-tag`.

- La couleur vient d'une classe de portée sur l'élément ou un parent : `mc-ue-1` … `mc-ue-4`, `mc-ue-autre`. Elle définit `--ue` et `--ue-soft`, que tous les composants lisent.
- Badge (`mc-ue`) : en-tête d'un groupe de matières et carte « En cours ». Texte = code court (« UE2 »), jamais le nom complet.
- Point (`mc-dot`) : devant une matière, une note, un créneau. Matière sans note : point creux (géré par `mc-subj--empty`).
- `mc-tag--cm` (plein, encre) pour un cours magistral, `mc-tag` (contour) pour TD/TP. Remplace l'ancien code rouge/bleu : la couleur est réservée à l'UE.
- La couleur ne porte jamais seule l'info : le code UE ou le nom de la matière est toujours à côté.
