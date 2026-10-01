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
- **Texte en couleur** `[texte]{rouge}` et **surlignage** `==texte==`.
- **Tableaux faciles** : `Titre 1 | Titre 2` + Entrée crée le tableau, Entrée ajoute
  une ligne, Tab passe de case en case (alignement auto), éditeur visuel type
  tableur (création et modification), collage depuis Excel / Google Sheets.
- **Coloration du code** (C, Python, SQL, JS…, et **pseudo-code** ```` ```pseudo ````)
  et **formules LaTeX** (MathJax) : palette de ~280 symboles et modèles avec
  recherche, autocomplétion en tapant `\`, champs à remplir (Tab), aperçu de la
  formule sous le curseur, raccourcis `\R`, `\abs{x}`… et référence complète
  dans la page Aide.
- **Tags** transversaux, **favoris** (⭐), **corbeille** (restauration possible).
- **Import de fichiers** (PDF, images, .txt, .docx… 20 Mo max) rattachés aux notes ;
  les **images** (ex. MCD exportés de draw.io en PNG) s'affichent dans la note
  (bouton Insérer, Ctrl+V ou glisser-déposer) et s'agrandissent au clic.
- **Pseudo-code** : `<-` s'affiche `←` dans l'aperçu.
- **Export** d'une note ou d'une matière entière en **Markdown** et **PDF**.
- **Recherche** plein texte (titre + contenu) avec filtre par tag.
- **Agenda** (semaine / mois / liste) : emploi du temps importé, réunions, tâches à
  faire, événements perso et échéances au même endroit ; bloc « Aujourd'hui » sur
  l'accueil ; depuis un cours, « Prendre des notes » crée la note dans la bonne matière.
- **Échéances** (DS, rendus, examens) avec rappels sur le tableau de bord.
- **Révision** par flashcards (question/réponse, mode révision mélangée).
- **Tableau de bord** : dernières notes, favoris, prochaines échéances, stats.
- **Mode sombre** + **interface responsive** (PC en amphi / téléphone).

---

## Agenda : connecter son emploi du temps

Les tables de l'agenda se créent toutes seules au premier passage (pas besoin de
réimporter `schema.sql`).

1. **Trouver le lien iCal** de l'emploi du temps : dans le logiciel de l'école
   (Celcat, HyperPlanning, ADE…), chercher « S'abonner », « Exporter », « iCal »,
   « ICS » ou « Synchroniser avec mon agenda ». Le lien finit souvent par `.ics`
   ou commence par `webcal://`. Ça marche aussi avec Google Agenda (« Adresse
   secrète au format iCal ») ou Outlook (« Publier un calendrier » → lien ICS).
2. **Agenda → 🔗 Emploi du temps** → coller le lien → **Connecter**.
   Les cours sont importés (répétitions hebdomadaires comprises) et re-synchronisés
   automatiquement toutes les 6 h quand on ouvre l'agenda (ou via « ↻ Synchroniser »).
3. **Relier les cours aux matières** (même fenêtre) : un cours est rattaché à une
   matière si son intitulé contient le nom de la matière ou un de ses **mots-clés**
   (ex. `algo, algorithmique` pour « Algorithmique procédurale »). La fenêtre liste
   les intitulés non reconnus pour savoir quoi ajouter. Un cours relié prend la couleur
   de son UE, s'affiche sur la page de la matière (« Prochains cours ») et le bouton
   **📝 Prendre des notes** crée une note pré-remplie dans cette matière.

### Import Celcat (`edt-celcat.ics`)

Le fichier généré par `outils/celcat-vers-ics.js` s'importe avec
**Agenda → 🔗 Emploi du temps → « … ou importer un fichier .ics »**. Réimporter
un fichier du **même nom** remplace les créneaux de l'import précédent ; les
réunions, tâches et événements perso créés à la main ne sont **jamais** touchés.

À l'import, chaque créneau est mis en forme ainsi : **matière** (titre),
**prof**, **CM / TD**, **salle**. Champs lus dans le `.ics` :

| Champ iCal | Utilisation |
|---|---|
| `SUMMARY` | Nom de la matière. Les codes (`P1INF05 - …`, `… [P1INF05]`, `P1INF05 : …`), un `CM`/`TD`/`TP` isolé et le nom du prof sont retirés. L'intitulé d'origine reste en tête de la description, donc un code peut servir de mot-clé de rattachement. |
| `CATEGORIES` | `CM` / « Cours magistral » → **CM (rouge)** ; `TD` / « Travaux dirigés » → **TD (bleu)** ; autre (TP, examen…) → couleur de l'UE ; « Indisponibilité » → importé comme **réunion** avec son titre. À défaut, un `CM`/`TD` présent dans `SUMMARY` est utilisé. |
| `DESCRIPTION` | Prof : ligne `Prof : …`, `Enseignant : …`, `Intervenant : …` ou `Staff : …`. Le reste est affiché dans le détail du cours. |
| `ORGANIZER;CN=…` | Prof, si la description n'en contient pas. |
| `LOCATION` | Salle. |

Ces informations sont stockées dans les colonnes `evenements.categorie` (CM/TD/autre)
et `evenements.intervenant`, ajoutées automatiquement à une base existante.

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

## Structure du projet
```
config.php            identifiants BDD (non versionné)
schema.sql            structure + données de démo
includes/             connexion PDO, auth, fonctions, gabarits (header/footer)
api/                  points d'entrée AJAX JSON (notes, tags, structure,
                      echeances, flashcards, upload, preferences, agenda)
assets/css, assets/js CSS + JavaScript (app, editeur, outils-editeur,
                      rendu, agenda, maths-symboles, mathjax-config)
includes/agenda.php   lecture iCal (.ics), synchro, rattachement aux matières
uploads/              fichiers importés (accès via telecharger.php)
index.php             tableau de bord            recherche.php   recherche
note.php              éditeur de note            corbeille.php   corbeille
matiere.php           notes d'une matière        echeances.php   échéances
agenda.php            agenda (cours, réunions, tâches)
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
