# Mes Cours — plateforme de prise de notes

Plateforme web pour prendre, organiser, rechercher et exporter mes notes de
cours.

Organisation : **UE → Matière → Note**, avec tags transversaux.

---

## Installation locale (XAMPP / WAMP)

### 1. Placer les fichiers
Copie le dossier du projet dans le répertoire web de ton serveur :
- **XAMPP** : `C:\xampp\htdocs\cours`
- **WAMP**  : `C:\wamp64\www\cours`

### 2. Démarrer les services
Lance **Apache** et **MySQL** depuis le panneau XAMPP/WAMP.

### 3. Importer la base de données
1. Ouvre **phpMyAdmin** : <http://localhost/phpmyadmin>
2. Onglet **Importer** → choisis [`schema.sql`](schema.sql) → **Exécuter**.
3. La base `cours_db` est créée avec toutes les tables et les données de démo.

### 4. Configurer la connexion
Copie `config.exemple.php` en **`config.php`** et adapte si besoin
(valeurs XAMPP par défaut : `root` / mot de passe vide).

### 5. Lancer
Ouvre <http://localhost/cours/>.
- **Compte de démo** : identifiant `demo` / mot de passe `demo1234`
- Ou **Inscription** : tes UE/matières sont pré-remplies automatiquement.

---

## Fonctionnalités
- **Comptes** sécurisés (inscription/connexion, mots de passe hachés) — chacun ne voit que ses notes.
- **Éditeur Markdown** avec aperçu en direct, **auto-save**, horodatage,
  **barre d'outils** (titres, gras, couleurs, listes, tableaux, maths, code).
- **Raccourcis** : Ctrl+S (enregistrer), Ctrl+Z / Ctrl+Y (annuler / rétablir),
  Ctrl+B / Ctrl+I, Tab / Maj+Tab, Entrée continue une liste.
- **Notes indentées** acceptées : le gras et les puces marchent même décalés.
- **Texte en couleur** `[texte]{rouge}`, **surlignage** `==texte==` et **souligné**
  `++texte++` (bouton U ou Ctrl+U).
- **Tableaux faciles** : `Titre 1 | Titre 2` + Entrée crée le tableau, Entrée ajoute
  une ligne, Tab passe de case en case (alignement auto), éditeur visuel type
  tableur (création et modification), collage depuis Excel / Google Sheets.
- **Coloration du code** (C, Python, SQL, JS…, et **pseudo-code** ```` ```pseudo ````)
  et **formules LaTeX** (MathJax) : palette de ~280 symboles et modèles avec
  recherche, autocomplétion en tapant `\`, champs à remplir (Tab), aperçu de la
  formule sous le curseur, raccourcis `\R`, `\abs{x}`… et référence complète
  dans la page Aide.
- **Tags** transversaux, **favoris** (étoile de l'éditeur, page Favoris), **corbeille** (restauration possible).
- **Import de fichiers** (PDF, images, .txt, .docx… 20 Mo max) rattachés aux notes ;
  les **images** (ex. MCD exportés de draw.io en PNG) s'affichent dans la note
  (bouton Insérer, Ctrl+V ou glisser-déposer) et s'agrandissent au clic.
- **Pseudo-code** : `<-` s'affiche `←` dans l'aperçu.
- **Export** d'une note ou d'une matière entière en **Markdown** et **PDF**.
- **Recherche** plein texte (titre + contenu) avec filtre par tag, et **palette**
  `Ctrl K` (ou `/`) : notes, matières et actions au clavier.
- **Agenda** (semaine / mois / liste) : emploi du temps importé, réunions, tâches à
  faire, événements perso et échéances au même endroit ; depuis un cours, « Prendre
  des notes » crée la note dans la bonne matière.
- **Accueil « Aujourd'hui »** : le cours en cours (ou le prochain) avec « Prendre des
  notes » pré-rempli (`Matière (TD) – jj/mm/aaaa`), la journée (cours notés ou à
  rattraper), la série de jours avec des notes, les tâches (ajout rapide en langage
  naturel : « Finir le TD de BDD pour vendredi »), les échéances des 7 prochains
  jours, les matières par UE, les dernières notes et une **citation du jour**
  (`includes/citations.php`, une par jour).
- **Raccourcis** du site : `N` nouvelle note, `T` ajouter une tâche, `Ctrl K` ou `/`
  recherche, `Échap` ferme.
- **Comptabilité (Gestion de l'entreprise)** : voir ci-dessous.
- **Échéances** (DS, rendus, examens) avec rappels sur l'accueil.
- **Révision** par flashcards (question/réponse, mode révision mélangée).
- **Thème clair / sombre** + **interface responsive** (PC en amphi / téléphone).

---

## Comptabilité (Gestion de l'entreprise)

**Atelier comptable** (`compta.php`, menu « Comptabilité ») : un dossier par exercice
(TD, cas d'entreprise), enregistré automatiquement.
0. **Bilan de départ** (facultatif) : actif et passif de l'énoncé, comptes par numéro ou par
   nom (« dettes fournisseurs » → 401), ou tout le bilan collé d'un coup. Chaque poste ouvre
   son compte dans le grand livre (à-nouveau **AN** : actif au débit, passif au crédit).
1. **Journal** : saisie rapide `31/12 607 / 401 14000 Achat de marchandises`
   (les comptes peuvent aussi s'écrire en mots : `banque / ventes 45000`),
   **opérations courantes** expliquées (quel compte débiter / créditer et pourquoi, TVA
   20 % calculée), ou écritures à plusieurs lignes. Taper un numéro de compte remplit
   l'intitulé ; une nouvelle ligne reçoit le montant qui équilibre l'écriture.
2. **Grand livre** (comptes en T), 3. **Balance**, 4. **Compte de résultat**, 5. **Bilan** :
   calculés à partir du journal, avec vérification (balance et bilan équilibrés).
- **Analyse** : fonds de roulement, BFR, trésorerie nette (avec commentaire), soldes
  intermédiaires de gestion (marge, VA, EBE…) et ratios (marge, rentabilités, autonomie,
  liquidité, délais clients / fournisseurs).
- **Aide-mémoire** : méthode, débit / crédit, classes du plan comptable, structure du bilan
  et du compte de résultat, formules, pièges fréquents, opérations courantes, plan
  comptable avec recherche.
- **Bilan** : comparaison avec le bilan de départ, et « Exercice suivant » qui crée un dossier
  dont le bilan de départ est ce bilan de fin (résultat en 120 / 129).
- **Exemples** : « du bilan de départ au bilan de fin » et cas Brico Dépôt (grand livre au
  30/12 + opérations du 31/12) pour vérifier son corrigé.
- **Créer une note** : copie tout le dossier dans une note (rangée dans la matière de gestion).

**Dans les notes** (menu **Compta** de l'éditeur) : blocs ```` ```comptes ```` (comptes en T),
```` ```journal ````, ```` ```balance ````, ```` ```resultat ```` et ```` ```bilan ```` ; totaux,
soldes et résultat sont calculés à l'affichage (syntaxe dans la page Aide).
**Le numéro suffit** : `512` s'affiche « 512 Banque » ; dans l'éditeur, `@banque` ou `@512`
insère « 512 Banque », et dans un tableau ou un bloc de compta le numéro seul propose
l'intitulé (Entrée / Tab). Le menu Compta a aussi une recherche de compte.

Calculs : `assets/js/compta-moteur.js` (aussi utilisé par l'aide et l'impression).
La table `compta_dossiers` se crée toute seule au premier passage.

---

## Agenda : connecter son emploi du temps

Les tables de l'agenda se créent toutes seules au premier passage (pas besoin de
réimporter `schema.sql`).

1. **Trouver le lien iCal** de l'emploi du temps : dans le logiciel de l'école
   (Celcat, HyperPlanning, ADE…), chercher « S'abonner », « Exporter », « iCal »,
   « ICS » ou « Synchroniser avec mon agenda ». Le lien finit souvent par `.ics`
   ou commence par `webcal://`. Ça marche aussi avec Google Agenda (« Adresse
   secrète au format iCal ») ou Outlook (« Publier un calendrier » → lien ICS).
2. **Agenda → Emploi du temps** → coller le lien → **Connecter**.
   Les cours sont importés (répétitions hebdomadaires comprises) et re-synchronisés
   automatiquement toutes les 6 h quand on ouvre l'agenda (ou via « ↻ Synchroniser »).
3. **Relier les cours aux matières** (même fenêtre) : un cours est rattaché à une
   matière si son intitulé contient le nom de la matière ou un de ses **mots-clés**
   (ex. `algo, algorithmique` pour « Algorithmique procédurale »). La fenêtre liste
   les intitulés non reconnus pour savoir quoi ajouter. Un cours relié prend la couleur
   de son UE, s'affiche sur la page de la matière (« Prochains cours ») et le bouton
   **Prendre des notes** crée une note pré-remplie dans cette matière.

### Import Celcat (`edt-celcat.ics`)

Le fichier généré par `outils/celcat-vers-ics.js` s'importe avec
**Agenda → Emploi du temps → « … ou importer un fichier .ics »**. Réimporter
un fichier du **même nom** remplace les créneaux de l'import précédent ; les
réunions, tâches et événements perso créés à la main ne sont **jamais** touchés.

Chaque cours s'affiche ainsi : **matière**, **prof · CM/TD**, **salle**
(ex. « Base de données / DUPONT Jean · CM / A001 »).

**Celcat ne donne qu'un code de module** (`DIDB1BDD(DI01C1-260) (TD)`) : le nom
affiché est celui de la **matière reliée**. Après le premier import, ouvre
Emploi du temps → « Cours sans matière » : chaque code y est listé avec une
matière **pré-sélectionnée** quand le code y ressemble (`DIDB1BDD` → Base de
données, `DIDB1OPL` → Optimisation linéaire…). Vérifie, complète (ex.
`DIDANG1D` → TOEIC) puis **Enregistrer les mots-clés** : le code devient un
mot-clé de la matière, pour cet import et les suivants.

Champs lus dans le `.ics` :

| Champ iCal | Utilisation |
|---|---|
| `SUMMARY` | Intitulé. Retirés : `(TD)`/`(CM)` (→ catégorie), le groupe `(DI01C1-260)`, les codes (`P1INF05 - …`, `… [P1INF05]`) et le nom du prof. S'il ne reste qu'un code, il sert de mot-clé pour relier la matière (voir ci-dessus). |
| `CATEGORIES` | `CM` / « Cours magistral » → **CM** ; `TD` / « Travaux dirigés » → **TD** ; `TP` / « Travaux pratiques » → **TP** (étiquette sur le créneau, la couleur reste celle de l'UE) ; autre (examen…) → sans étiquette ; « Indisponibilité » → importé comme **réunion** avec son titre. À défaut, un `CM`/`TD`/`TP` présent dans `SUMMARY` est utilisé. |
| `DESCRIPTION` | Prof : ligne `Prof : …` (`Enseignant`, `Intervenant`, `Staff`), sinon une ligne au format nom de personne (`DUPONT Jean`, `Jean DUPONT`, `M. Dupont`). Le HTML de Celcat (`<br />`, `&#201;`) est décodé. |
| `ORGANIZER;CN=…` | Prof, si la description n'en contient pas. |
| `LOCATION` | Numéro de salle seulement : `PAU E201 SALLE POLYVALENTE (TD ET INFO) 30p` → `E201`, `PAU A001 AMPHITHÉÂTRE 150p` → `A001`. |

Ces informations sont stockées dans les colonnes `evenements.categorie` (CM/TD/TP/autre)
et `evenements.intervenant`, ajoutées automatiquement à une base existante (comme
`evenements.date_fait`, l'heure où une tâche a été cochée).

**Si le lien ne marche pas :**
- *« il faut être connecté » / erreur 401-403* : le calendrier n'est visible
  qu'après connexion à l'ENT. Télécharger le fichier `.ics` et l'importer avec
  « … ou importer un fichier .ics » (à refaire quand l'emploi du temps change).
- *« Certificat HTTPS non vérifié »* (fréquent sous XAMPP/Windows) : télécharger
  <https://curl.se/ca/cacert.pem>, le placer par ex. dans `C:\xampp\php\extras\ssl\`,
  puis dans `php.ini` mettre `curl.cainfo = "C:\xampp\php\extras\ssl\cacert.pem"`
  et `openssl.cafile` = la même valeur, et redémarrer Apache.
- Les heures sont affichées dans le fuseau `'fuseau'` de `config.php`
  (`Europe/Paris` par défaut).

---

## Sécurité mise en place
- Requêtes **préparées PDO** partout (aucune concaténation SQL).
- Mots de passe **hachés** (`password_hash` / `password_verify`).
- Sessions durcies (`httponly`, `samesite`) + **jetons CSRF** sur les écritures.
- Échappement HTML en sortie + **DOMPurify** sur le Markdown rendu (anti-XSS).
- Uploads validés (extension + type MIME + taille), noms de stockage aléatoires.
- Dossier `uploads/` inaccessible en direct : les fichiers passent par
  `telecharger.php` qui vérifie le propriétaire.

---

## Interface (design system)

Les règles, couleurs, typographies et composants sont dans [`design/`](design/README.md)
(maquette de l'accueil et captures attendues comprises). Dans le projet :
- `assets/css/tokens.css` et `assets/css/mes-cours.css` : copies du design system
  (variables et classes `mc-*`), chargées en premier ;
- `assets/css/app.css` : ce que les composants ne couvrent pas (mobile, champs,
  fenêtres, éditeur, agenda), avec les seuls tokens ;
- police : **Plus Jakarta Sans** pour les titres et le texte (variables
  `--font-display` / `--font-sans` de `tokens.css`), JetBrains Mono pour le code ;
- les fichiers CSS / JS sont chargés avec `?v=<date de modification>` : le navigateur
  prend la nouvelle version dès qu'un fichier change ;
- thème : `data-theme="light"` / `"dark"` sur `<html>` (bouton lune de la barre
  latérale) ; la couleur d'une UE suit son ordre (UE1 à UE4, puis « autre ») ;
- icônes Lucide en SVG inline (`includes/icones.php`, aussi exposées au JavaScript).

## Structure du projet
```
config.php            identifiants BDD (non versionné)
schema.sql            structure + données de démo
design/               design system de référence (README, tokens, composants)
includes/             connexion PDO, auth, fonctions, gabarits (header/footer),
                      ui.php (dates, couleurs d'UE), icones.php, composants.php
api/                  points d'entrée AJAX JSON (notes, tags, structure, recherche,
                      echeances, flashcards, upload, preferences, agenda)
assets/css            tokens.css, mes-cours.css (design system), app.css
assets/js             app (thème, raccourcis, palette, nouvelle note), accueil,
                      editeur, outils-editeur, rendu, agenda, maths-symboles,
                      mathjax-config
includes/agenda.php   lecture iCal (.ics), synchro, rattachement aux matières
uploads/              fichiers importés (accès via telecharger.php)
index.php             accueil « Aujourd'hui »    recherche.php   recherche
note.php              éditeur de note            corbeille.php   corbeille
matieres.php          matières par UE            matiere.php     notes d'une matière
favoris.php           notes épinglées            echeances.php   échéances
agenda.php            agenda (cours, réunions, tâches)
compta.php            atelier comptable (journal → bilan, analyse, aide-mémoire)
inscription/connexion revision.php  flashcards   reglages.php    réglages
export.php / imprimer.php   exports Markdown / PDF
```

## Réalisé
- [x] Socle : base, config, auth, thème, tableau de bord
- [x] Éditeur de notes (Markdown, code, LaTeX, auto-save)
- [x] Structure (CRUD UE/matières), recherche, tags, favoris, corbeille
- [x] Import de fichiers, export PDF / Markdown
- [x] Échéances, statistiques, flashcards
- [x] Agenda : emploi du temps iCal, réunions, tâches
- [x] Nouvelle interface : design system, accueil « Aujourd'hui », palette Ctrl K
- [x] Comptabilité : atelier (journal, grand livre, balance, résultat, bilan, analyse), blocs dans les notes, souligné
