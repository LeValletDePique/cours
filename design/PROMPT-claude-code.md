Tu vas appliquer une nouvelle interface à « Mes Cours », ma plateforme de notes de cours (PHP + PDO, MySQL/MariaDB, HTML/CSS/JS sans framework, organisation UE → Matière → Note).

## Le design de référence

Tout est dans le dossier `design/` à la racine du dépôt :
- `design/README.md` : les règles du design system (couleurs, typo, ton, principes). Lis-le EN ENTIER avant de commencer.
- `design/tokens.css` : les variables CSS (couleurs clair/sombre, espacements, rayons, ombres, polices).
- `design/mes-cours.css` : toutes les classes des composants (`mc-*`).
- `design/maquette-accueil.html` : la page d'accueil cible, ouvrable dans un navigateur.
- `design/captures/accueil-clair.png` et `accueil-sombre.png` : le rendu attendu.
- `design/composants/<Nom>.html` + `<Nom>.md` : chaque composant, son HTML et ses règles d'usage.

Ces fichiers sont la source de vérité : reprends les classes, valeurs et structures HTML telles quelles, n'invente pas d'autres couleurs ni d'autres tailles.

## Règles

- Avant de modifier quoi que ce soit, explore le dépôt (pages, includes, CSS existant, JS, schéma SQL, gestion du thème, agenda, tâches, échéances) et donne-moi un plan court, page par page. Attends mon accord.
- Travaille sur une branche `refonte-ui`, avec un commit par étape.
- Ne casse aucune fonctionnalité existante (éditeur Markdown, tags, fichiers joints, export, recherche, corbeille, agenda, échéances, réglages, connexion).
- Pas de framework, pas de build : PHP + CSS + JS vanilla.
- Pas de nouvelle table sans me le demander ; si un calcul a besoin d'une donnée qui n'existe pas, dis-le.
- Copie `tokens.css` et `mes-cours.css` dans le dossier CSS du projet et charge-les dans le `<head>` commun, dans cet ordre, à la place de l'ancien style (garde l'ancien fichier jusqu'à la fin, puis supprime ce qui ne sert plus).
- Thème : `data-theme="light"` ou `"dark"` sur `<html>`, mémorisé comme le thème actuel ; le bouton lune en bas de la barre latérale bascule.
- Icônes : Lucide en SVG inline (pas d'emoji dans l'interface).
- Tutoiement, phrases courtes, comme dans le README.

## Étapes

1. **Socle** : tokens, CSS, polices Google Fonts, thème clair/sombre.
2. **Structure** : remplace la barre du haut par la barre latérale `Navigation` (Aujourd'hui, Matières, Agenda, Révision, Échéances, Favoris ; en pied Corbeille, Réglages, Aide, compte, thème) et la barre du haut par la recherche + bouton « Nouvelle note ». Applique-la à toutes les pages.
3. **Accueil** en suivant `maquette-accueil.html`, avec les vraies données :
   - salut + date + une phrase-résumé calculée (nombre de cours du jour, tâches ouvertes, une suggestion) ;
   - `NowCard` : le cours en cours d'après l'agenda (sinon le prochain, sinon « Journée terminée »), temps restant, salle, barre d'avancement, cours suivant ;
   - bouton « Prendre des notes » : crée une note pré-remplie avec le titre `<Matière> (<CM|TD|TP>) – jj/mm/aaaa`, la matière déjà choisie, et ouvre l'éditeur. Associe le créneau à la matière par le nom ; si aucune matière ne correspond, ouvre l'éditeur sans matière ;
   - `WeekStreak` : jours de la semaine avec au moins une note créée ou modifiée, série de jours consécutifs, « Cours notés aujourd'hui x / y » ;
   - `DayTimeline` : les créneaux du jour ; un cours passé avec une note ce jour-là dans sa matière affiche « ✓ n note », sinon un bouton « + Notes » qui fait la même création pré-remplie ;
   - `TaskList` (sur les tâches existantes) avec ajout rapide, `Deadline` / `EmptyState` pour les échéances des 7 prochains jours ;
   - `SubjectList` groupée par UE (couleur par UE, matières sans note en point creux) et « Reprendre » (`NoteRow`, 5 dernières notes, temps relatif, lien « Classer » pour les non classées).
4. **Raccourcis clavier** (hors champs de saisie) : `N` nouvelle note (pré-remplie si un cours est en cours), `T` focus sur l'ajout de tâche, `Ctrl K` et `/` ouvrent la `CommandPalette` (recherche plein texte existante + actions), `Échap` ferme.
5. **Les autres pages** : Agenda (créneaux `AgendaEvent` colorés par UE, CM/TD en étiquette, évènements perso en pointillé), Matières, Échéances, Révision, Recherche, Corbeille, Réglages, Aide, éditeur de note, connexion : passe-les aux composants et aux tokens, sans changer leur fonctionnement.
6. **Vérification** : parcours chaque page en clair et en sombre, compare l'accueil aux captures, vérifie le focus clavier visible et l'absence d'erreurs PHP/JS. Fais-moi un résumé de ce qui a changé et de ce qui reste.

Commence par l'exploration et le plan.
