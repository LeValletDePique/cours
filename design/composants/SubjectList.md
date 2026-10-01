# SubjectList

Les matières groupées par UE, en liste compacte au lieu de grandes cartes.

- Un `mc-ue-block` par UE : badge + nom court de l'UE, puis une ligne `mc-subj` par matière (point, nom, nombre de notes en mono).
- Matière sans note : `mc-subj--empty` (point creux, texte `ink-muted`) : on voit d'un coup d'œil où il manque des notes.
- Ordre : les UE de la journée en premier, puis par numéro ; « Autre » en dernier.
- Clic = la matière ; survol = `surface-sunk`.
