# Mes Cours — plateforme de prise de notes

Plateforme web pour prendre, organiser, rechercher et exporter mes notes de
cours (Pré-Ing 2, CY Tech Pau). Back-end **PHP + PDO**, base **MySQL/MariaDB**,
front **HTML/CSS/JS** sans framework lourd.

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
- **Éditeur Markdown** avec aperçu en direct, **auto-save**, horodatage.
- **Coloration du code** (C, Python, SQL, JS…) et **formules LaTeX** (MathJax).
- **Tags** transversaux, **favoris** (⭐), **corbeille** (restauration possible).
- **Import de fichiers** (PDF, images, .txt, .docx… 20 Mo max) rattachés aux notes.
- **Export** d'une note ou d'une matière entière en **Markdown** et **PDF**.
- **Recherche** plein texte (titre + contenu) avec filtre par tag.
- **Échéances** (DS, rendus, examens) avec rappels sur le tableau de bord.
- **Révision** par flashcards (question/réponse, mode révision mélangée).
- **Tableau de bord** : dernières notes, favoris, prochaines échéances, stats.
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
schema.sql            structure + données de démo
includes/             connexion PDO, auth, fonctions, gabarits (header/footer)
api/                  points d'entrée AJAX JSON (notes, tags, structure,
                      echeances, flashcards, upload, preferences)
assets/css, assets/js CSS + JavaScript (app, editeur, rendu)
uploads/              fichiers importés (accès via telecharger.php)
index.php             tableau de bord            recherche.php   recherche
note.php              éditeur de note            corbeille.php   corbeille
matiere.php           notes d'une matière        echeances.php   échéances
inscription/connexion revision.php  flashcards   reglages.php    réglages
export.php / imprimer.php   exports Markdown / PDF
```

## Réalisé
- [x] Socle : base, config, auth, thème, tableau de bord
- [x] Éditeur de notes (Markdown, code, LaTeX, auto-save)
- [x] Structure (CRUD UE/matières), recherche, tags, favoris, corbeille
- [x] Import de fichiers, export PDF / Markdown
- [x] Échéances, statistiques, flashcards
