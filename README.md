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

### 5. (Facultatif) Activer l'assistant IA
1. Installe [Composer](https://getcomposer.org/) puis, dans le dossier du projet :
   `composer install` (installe le SDK Anthropic dans `vendor/`).
2. Crée une clé API sur <https://console.anthropic.com> et colle-la dans
   `config.php` : `'anthropic_api_key' => 'sk-ant-...'`.
3. Le bouton **🤖 Aide IA** (en bas à droite) répond alors aux questions.
   Sans clé, le reste du site fonctionne normalement.

### 6. Mettre la base à jour (migrations)
À faire après chaque mise à jour du code, à la racine du projet :
```
php migrations/appliquer.php
```
Le script fait d'abord une sauvegarde (`mysqldump`) dans `sauvegardes/`, puis
applique seulement les fichiers `migrations/NNN_*.sql` pas encore appliqués.
Sauvegarde manuelle à tout moment : `php outils/sauvegarder.php`.
⚠ Ne réimporte jamais `schema.sql` sur une base qui contient tes notes : il
efface les tables.

### 7. Lancer
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
- **Assistant IA** (Claude) pour dépanner ou expliquer un cours, avec la note jointe.
- **Tags** transversaux, **favoris** (⭐), **corbeille** (restauration possible).
- **Import de fichiers** (PDF, images, .txt, .docx… 20 Mo max) rattachés aux notes.
- **Export** d'une note ou d'une matière entière en **Markdown** et **PDF**.
- **Recherche** plein texte (titre + contenu) avec filtre par tag.
- **Échéances** (DS, rendus, examens) avec rappels sur le tableau de bord.
- **Révision** par flashcards (question/réponse, mode révision mélangée).
- **Emploi du temps** : lien d'abonnement `.ics` de l'ENT (resynchronisé tout seul,
  au plus toutes les 3 h) ou import du fichier `.ics` ; vue semaine, cours en ce
  moment, cours du jour sur l'accueil ; matière reconnue automatiquement.
- **Tableau de bord** : cours du jour, dernières notes, favoris, prochaines échéances, stats.
- **Mode sombre** + **interface responsive** (PC en amphi / téléphone).

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
schema.sql            structure + données de démo (installation neuve uniquement)
migrations/           évolutions de la base (php migrations/appliquer.php)
outils/               sauvegarde de la base (php outils/sauvegarder.php)
includes/             connexion PDO, auth, fonctions, gabarits (header/footer)
api/                  points d'entrée AJAX JSON (notes, tags, structure,
                      echeances, flashcards, upload, preferences, assistant, edt)
assets/css, assets/js CSS + JavaScript (app, editeur, outils-editeur,
                      rendu, assistant, maths-symboles, mathjax-config)
composer.json         dépendance du SDK Anthropic (assistant IA)
uploads/              fichiers importés (accès via telecharger.php)
index.php             tableau de bord            recherche.php   recherche
note.php              éditeur de note            corbeille.php   corbeille
matiere.php           notes d'une matière        echeances.php   échéances
emploi-du-temps.php   emploi du temps (.ics)
inscription/connexion revision.php  flashcards   reglages.php    réglages
export.php / imprimer.php   exports Markdown / PDF
```

## Réalisé
- [x] Socle : base, config, auth, thème, tableau de bord
- [x] Éditeur de notes (Markdown, code, LaTeX, auto-save)
- [x] Structure (CRUD UE/matières), recherche, tags, favoris, corbeille
- [x] Import de fichiers, export PDF / Markdown
- [x] Échéances, statistiques, flashcards
