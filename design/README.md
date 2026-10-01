Mes Cours est l'outil de prise de notes d'un élève ingénieur (CY Tech Pau, Ing 1). Il sert tous les jours, entre deux cours, souvent en 30 secondes. Tout le système vise une chose : **ouvrir le site et savoir en un coup d'œil quoi faire maintenant**, puis le faire en un clic.

## Principes

- **Aujourd'hui d'abord.** L'accueil montre la journée : le cours en cours (`NowCard`), la journée (`DayTimeline`), les tâches, puis les matières. L'agenda semaine vit dans sa page.
- **Une action principale par écran.** Un seul `mc-btn--primary` (couleur `accent`) visible. Sur l'accueil : « Prendre des notes » du cours en cours.
- **Zéro saisie évitable.** Une note créée depuis l'agenda est pré-remplie : titre `<Matière> (<CM|TD|TP>) – jj/mm/aaaa`, matière et UE choisies. Les dates se tapent en langage naturel (« pour vendredi »).
- **Le clavier partout.** `N` nouvelle note, `T` tâche, `Ctrl K` ou `/` recherche, `Échap` ferme. Afficher le raccourci dans le bouton avec `mc-kbd`.
- **Motiver sans infantiliser.** La série de jours (`WeekStreak`) et les cases qui se remplissent suffisent. Pas de confettis, pas d'emoji, pas de message culpabilisant.
- **Montrer les trous.** Matière sans note : point creux. Cours passé sans note : bouton « + Notes » dans la journée.

## Ton et contenu

- Tutoiement, français, phrases courtes. « Rien à rendre cette semaine. » plutôt que « Aucune échéance à venir ».
- Boutons : verbe à l'infinitif + objet (« Prendre des notes », « Ajouter une échéance »).
- Sur-titres en capitales avec le style `label` (`mc-eyebrow`) : « EN COURS · FIN DANS 1 H 18 », « TA SEMAINE ».
- Dates relatives dans les listes (« 2 h », « hier », « dans 3 jours »), absolues ailleurs (« mercredi 30 septembre »). Heures au format `11:30–13:00`.
- Les états vides disent quoi faire et pourquoi, avec le bouton pour le faire (`EmptyState`).
- Pas d'emoji dans l'interface ; les icônes sont des traits (voir Iconographie).

## Couleur

- Fond de page `paper` (papier chaud), cartes `surface`, puits et survols `surface-sunk`, filets `line`.
- Texte `ink` ; secondaire `ink-muted`. `ink-faint` jamais pour du texte : contours de case à cocher et icônes décoratives.
- `accent` (flamme) est rare et veut dire « maintenant » : l'action principale, le cours en cours, la série. Texte sur un fond `accent` : `on-accent`, jamais du blanc en dur.
- Chaque UE a sa couleur : `ue-1` … `ue-4`, `ue-autre`, avec un fond `-soft`. Appliquer via les classes `mc-ue-1` … `mc-ue-autre`, qui posent `--ue` et `--ue-soft` pour les composants. La couleur d'UE n'est jamais seule : code UE ou nom de matière à côté.
- CM / TD / TP ne sont plus codés en rouge / bleu : ils passent dans `mc-tag` (`mc-tag--cm` plein).
- `success` = noté / fait, `warning` = échéance proche, toujours avec un mot ou une coche.
- Deux thèmes, Clair (par défaut) et Sombre ; chaque paire texte/fond tient 4,5:1 dans les deux.

## Typographie

- Trois familles : `display` (Fraunces) pour le salut, le titre du cours en cours et les chiffres ; `sans` (Instrument Sans) pour tout le reste ; `mono` (JetBrains Mono) pour horaires, salles, compteurs, raccourcis et l'éditeur Markdown.
- Styles : `display` 40/44 une fois par écran ; `title` 28/34 ; `numeral` 36/40 ; `heading` 17/24 pour les titres de carte ; `body` 15/22 ; `body-sm` 13/18 ; `label` 12/16 capitales ; `mono` 13/20.
- Les polices viennent de Google Fonts (pas de fichiers locaux).

## Espacement, rayons, ombres

- Échelle `space-1` 4 → `space-7` 48. Padding de carte et gouttière : `space-5`. Marges de la zone principale : `space-7`.
- `radius-sm` badges et créneaux, `radius-md` boutons et champs, `radius-lg` cartes, `radius-pill` barres de progression.
- `shadow-card` sous les cartes, `shadow-pop` pour la palette de commandes. Pas d'autre ombre.

## États et focus

- Survol : fond `surface-sunk`. Actif dans la navigation : `surface` + filet `line`, icône `accent`.
- Focus clavier : anneau plein 2px `focus-ring`, décalé de 2px, sur tous les éléments interactifs.
- Terminé : texte `ink-muted` barré (tâches), coche `success`.

## Iconographie

- Icônes Lucide (open source, ISC), en trait 1,75, 18px (15px avec `mc-ico-sm`), couleur héritée du texte. Les aperçus les intègrent en SVG inline.
- Une icône accompagne un libellé, sauf le bouton thème (avec `aria-label`).
- Pas de logo : la marque est le monogramme « M » en Fraunces sur `accent` (`mc-brand__mark`) suivi de « Mes Cours ».

## Mise en page

- Barre latérale `mc-rail` 232px + zone principale en grille 12 colonnes (`mc-grid`).
- Accueil : salut (`display`) et une phrase-résumé calculée → `NowCard` (8 col.) + `WeekStreak` (4) → `DayTimeline` (7) + À faire / Échéances (5) → Matières (8) + Reprendre (4).
- Navigation : Aujourd'hui, Matières, Agenda, Révision, Échéances ; Favoris ; en pied Corbeille, Réglages, Aide, compte et thème. La recherche devient la palette `Ctrl K`.

## Intégrer dans le projet PHP

- Copier `tokens.css` (généré par cette page) et `components/bundle.css` dans `assets/css/`, les charger dans le `<head>` commun, dans cet ordre.
- Thème : poser `data-theme="light"` ou `"dark"` sur `<html>` (le bouton lune bascule et mémorise).
- Les composants sont des classes CSS `mc-*` sans JavaScript ; reprendre le HTML de chaque aperçu dans les vues PHP.
