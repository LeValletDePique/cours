# 📚 Mes Cours — plateforme de prise de notes

Plateforme web pour prendre, organiser, rechercher et exporter ses notes de
cours (Pré-Ing 2, CY Tech Pau). Back-end **PHP + PDO**, base **MySQL/MariaDB**,
front **HTML/CSS/JS** sans framework lourd.

Organisation : **UE → Matière → Note**, avec des tags transversaux.

> Ce guide part de zéro : il explique quoi installer, comment récupérer le
> projet, le lancer, et récapitule tous les raccourcis utiles (terminal + éditeur).

---

## Sommaire
1. [Ce qu'il faut installer](#1-ce-quil-faut-installer)
2. [Récupérer le projet](#2-récupérer-le-projet)
3. [Installer la base de données](#3-installer-la-base-de-données)
4. [Configurer le projet](#4-configurer-le-projet)
5. [Lancer le site](#5-lancer-le-site)
6. [(Facultatif) Activer l'assistant IA](#6-facultatif-activer-lassistant-ia)
7. [Mettre à jour le projet](#7-mettre-à-jour-le-projet)
8. [Commandes utiles dans le terminal](#8-commandes-utiles-dans-le-terminal)
9. [Raccourcis clavier du terminal](#9-raccourcis-clavier-du-terminal)
10. [Raccourcis de l'éditeur de notes](#10-raccourcis-de-léditeur-de-notes)
11. [Dépannage](#11-dépannage)
12. [Fonctionnalités, sécurité, structure](#12-fonctionnalités)

---

## 1. Ce qu'il faut installer

| Outil | Obligatoire ? | Pourquoi | Lien |
|-------|:-------------:|----------|------|
| **Git** | ✅ | Récupérer le projet et ses mises à jour | <https://git-scm.com/downloads> |
| **PHP ≥ 8.1** | ✅ | Faire tourner le site | inclus dans XAMPP / MAMP |
| **MySQL ou MariaDB** | ✅ | Stocker les notes | inclus dans XAMPP / MAMP |
| **Un serveur web** (Apache) | ✅* | Servir les pages | inclus dans XAMPP / MAMP (*ou `php -S`, voir §5) |
| **Composer** | ➖ | Seulement pour l'assistant IA | <https://getcomposer.org/download/> |
| **Un navigateur récent** | ✅ | Chrome, Firefox, Edge, Safari… | — |
| **VS Code** | ➖ | Conseillé pour lire / modifier le code | <https://code.visualstudio.com/> |

Extensions PHP utilisées (déjà activées dans XAMPP/MAMP) : `pdo_mysql`,
`mbstring`, `fileinfo`, et `curl` + `openssl` pour l'assistant IA.

> 🌐 Le site charge quelques bibliothèques depuis Internet (rendu Markdown,
> MathJax, coloration du code) : il faut une connexion pour l'affichage complet.

### 🪟 Windows (le plus simple : XAMPP)
1. Télécharge et installe **XAMPP** : <https://www.apachefriends.org/fr/>
   (PHP 8.1 ou plus récent). Garde le dossier par défaut `C:\xampp`.
2. Installe **Git for Windows** : <https://git-scm.com/download/win>
   (options par défaut). Tu obtiens le terminal **Git Bash**.
3. (Facultatif) Installe **Composer** avec l'installeur Windows
   `Composer-Setup.exe` ; quand il demande PHP, indique `C:\xampp\php\php.exe`.

> Alternative : **WAMP** (<https://www.wampserver.com/>), dossier web `C:\wamp64\www`.

### 🍎 macOS
- **Option A — MAMP** (<https://www.mamp.info/fr/>) : installe-le, le dossier
  web est `/Applications/MAMP/htdocs`. ⚠️ Par défaut, MySQL de MAMP utilise
  `root` / `root` et le port `8889`.
- **Option B — Homebrew** (dans le Terminal) :
  ```bash
  /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
  brew install git php mysql composer
  brew services start mysql
  ```

### 🐧 Linux (Ubuntu / Debian)
```bash
sudo apt update
sudo apt install git php php-mysql php-mbstring php-curl php-xml mariadb-server unzip
sudo systemctl start mariadb
# Composer (facultatif)
sudo apt install composer
```

Vérifier que tout est bien installé :
```bash
git --version
php -v          # doit afficher 8.1 ou plus
mysql --version
composer -V     # facultatif
```
> Sous Windows, si `php` n'est pas reconnu dans le terminal, utilise
> `C:\xampp\php\php.exe` ou ajoute `C:\xampp\php` à la variable d'environnement `PATH`.

---

## 2. Récupérer le projet

Ouvre un terminal (Git Bash sous Windows, Terminal sous macOS/Linux), va dans
le dossier web de ton serveur puis clone le dépôt :

```bash
# Windows + XAMPP
cd /c/xampp/htdocs
# Windows + WAMP :  cd /c/wamp64/www
# macOS + MAMP   :  cd /Applications/MAMP/htdocs
# Linux / php -S :  n'importe quel dossier, par ex.  cd ~

git clone https://github.com/LeValletDePique/cours.git
cd cours
```

> Pas envie d'utiliser Git ? Sur la page GitHub : **Code → Download ZIP**, puis
> décompresse le dossier dans `htdocs` en le renommant `cours`.

---

## 3. Installer la base de données

1. Démarre **Apache** et **MySQL** (panneau XAMPP / WAMP / MAMP).
2. **Avec phpMyAdmin** (le plus simple) :
   - ouvre <http://localhost/phpmyadmin> (MAMP : <http://localhost:8888/phpMyAdmin>) ;
   - onglet **Importer** → choisis le fichier [`schema.sql`](schema.sql) → **Exécuter**.
3. **Ou en ligne de commande**, depuis le dossier du projet :
   ```bash
   mysql -u root -p < schema.sql
   # (appuie sur Entrée si le mot de passe est vide, cas de XAMPP)
   # Windows/XAMPP si "mysql" est introuvable :
   /c/xampp/mysql/bin/mysql -u root -p < schema.sql
   ```

La base **`cours_db`** est créée avec toutes les tables et un compte de démo.

> ⚠️ Réimporter `schema.sql` **supprime et recrée** toutes les tables :
> toutes les notes existantes sont effacées. À ne faire qu'à l'installation.

---

## 4. Configurer le projet

Copie le modèle de configuration :

```bash
cp config.exemple.php config.php        # Git Bash / macOS / Linux
# copy config.exemple.php config.php    # invite de commandes Windows (cmd)
```

Puis ouvre `config.php` et adapte si besoin :

| Clé | XAMPP / WAMP | MAMP | Linux (MariaDB) |
|-----|--------------|------|-----------------|
| `db_host` | `127.0.0.1` | `127.0.0.1` | `127.0.0.1` |
| `db_user` | `root` | `root` | ton utilisateur MySQL |
| `db_pass` | `''` (vide) | `'root'` | ton mot de passe |
| `db_port` | `3306` | `8889` | `3306` |

> `config.php` n'est **pas** envoyé sur GitHub (il est dans `.gitignore`) :
> chacun garde ses propres identifiants et sa clé API.

<details>
<summary>🐧 Linux : créer un utilisateur MySQL (root n'a souvent pas de mot de passe utilisable)</summary>

```bash
sudo mysql
```
```sql
CREATE USER 'cours'@'localhost' IDENTIFIED BY 'motdepasse';
GRANT ALL PRIVILEGES ON cours_db.* TO 'cours'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
```bash
sudo mysql < schema.sql
```
Puis dans `config.php` : `'db_user' => 'cours'`, `'db_pass' => 'motdepasse'`.
</details>

---

## 5. Lancer le site

**Avec XAMPP / WAMP / MAMP** : Apache et MySQL démarrés, ouvre
- <http://localhost/cours/> (XAMPP / WAMP)
- <http://localhost:8888/cours/> (MAMP)

**Sans Apache (serveur intégré de PHP)** — MySQL doit tourner :
```bash
php -S localhost:8000
```
puis ouvre <http://localhost:8000>. `Ctrl + C` dans le terminal pour arrêter.

**Se connecter**
- **Compte de démo** : identifiant `demo` / mot de passe `demo1234`
- Ou **Inscription** : tes UE / matières sont pré-remplies automatiquement.

---

## 6. (Facultatif) Activer l'assistant IA

1. Dans le dossier du projet :
   ```bash
   composer install
   ```
   (installe le SDK Anthropic dans `vendor/`).
2. Crée une clé API sur <https://console.anthropic.com> et colle-la dans
   `config.php` : `'anthropic_api_key' => 'sk-ant-...'`
   (ou définis la variable d'environnement `ANTHROPIC_API_KEY`).
3. Le bouton **🤖 Aide IA** (en bas à droite) répond alors aux questions.

Sans clé, le reste du site fonctionne normalement.
> 🔒 Ne partage **jamais** ta clé API (ni sur GitHub, ni sur Discord…).

---

## 7. Mettre à jour le projet

```bash
cd chemin/vers/cours
git pull                 # récupère les dernières modifications
composer install         # seulement si tu utilises l'assistant IA
```
Tes notes (dans la base) et ton `config.php` ne sont pas touchés.

---

## 8. Commandes utiles dans le terminal

### Se déplacer
| Commande | Effet |
|----------|-------|
| `pwd` | Affiche le dossier courant |
| `ls` (`dir` sous cmd) | Liste les fichiers |
| `cd dossier` | Entre dans un dossier |
| `cd ..` | Remonte d'un dossier |
| `cd ~` | Retourne au dossier personnel |
| `clear` (`cls` sous cmd) | Nettoie l'écran |
| `code .` | Ouvre le dossier courant dans VS Code |

### Git
| Commande | Effet |
|----------|-------|
| `git clone <url>` | Télécharge un dépôt |
| `git status` | Montre les fichiers modifiés |
| `git pull` | Récupère les mises à jour |
| `git add .` | Prépare toutes les modifications |
| `git commit -m "message"` | Enregistre une version |
| `git push` | Envoie ses commits sur GitHub |
| `git log --oneline` | Historique compact |
| `git checkout -- fichier` | Annule les modifs locales d'un fichier |
| `git branch` / `git switch nom` | Voir / changer de branche |

### PHP, MySQL, Composer
| Commande | Effet |
|----------|-------|
| `php -v` | Version de PHP |
| `php -S localhost:8000` | Lance le site sans Apache |
| `php -l fichier.php` | Vérifie la syntaxe d'un fichier PHP |
| `mysql -u root -p` | Ouvre la console MySQL |
| `mysql -u root -p < schema.sql` | Importe la base (⚠️ efface les données) |
| `mysqldump -u root -p cours_db > sauvegarde.sql` | **Sauvegarde** toutes ses notes |
| `mysql -u root -p cours_db < sauvegarde.sql` | Restaure une sauvegarde |
| `composer install` | Installe les dépendances (assistant IA) |

Dans la console MySQL : `SHOW DATABASES;`, `USE cours_db;`, `SHOW TABLES;`, `EXIT;`.

---

## 9. Raccourcis clavier du terminal

(Git Bash, Terminal macOS, Linux — la plupart marchent aussi dans PowerShell.)

| Raccourci | Effet |
|-----------|-------|
| <kbd>Tab</kbd> | **Autocomplète** un nom de fichier / dossier / commande (2× = liste des choix) |
| <kbd>↑</kbd> / <kbd>↓</kbd> | Commandes précédentes / suivantes |
| <kbd>Ctrl</kbd> + <kbd>C</kbd> | **Arrête** la commande en cours (ex. `php -S`) |
| <kbd>Ctrl</kbd> + <kbd>L</kbd> | Efface l'écran (comme `clear`) |
| <kbd>Ctrl</kbd> + <kbd>R</kbd> | Recherche dans l'historique des commandes |
| <kbd>Ctrl</kbd> + <kbd>A</kbd> / <kbd>Ctrl</kbd> + <kbd>E</kbd> | Début / fin de ligne |
| <kbd>Ctrl</kbd> + <kbd>U</kbd> | Efface tout avant le curseur |
| <kbd>Ctrl</kbd> + <kbd>W</kbd> | Efface le mot précédent |
| <kbd>Alt</kbd> + <kbd>←</kbd> / <kbd>→</kbd> | Mot précédent / suivant |
| <kbd>Ctrl</kbd> + <kbd>D</kbd> | Quitte le terminal (ou la console MySQL) |
| <kbd>Ctrl</kbd> + <kbd>Maj</kbd> + <kbd>C</kbd> / <kbd>V</kbd> | Copier / coller (Linux, Git Bash) — <kbd>⌘</kbd> + <kbd>C</kbd> / <kbd>V</kbd> sur macOS |
| <kbd>Maj</kbd> + <kbd>Inser</kbd> | Coller (Git Bash / Windows) |
| <kbd>q</kbd> | Quitte l'affichage paginé (`git log`, `git diff`…) |

Dans **VS Code** : <kbd>Ctrl</kbd> + <kbd>ù</kbd> (clavier AZERTY) ou
<kbd>Ctrl</kbd> + <kbd>`</kbd> ouvre / ferme le terminal intégré.

---

## 10. Raccourcis de l'éditeur de notes

Sur macOS, remplace <kbd>Ctrl</kbd> par <kbd>⌘</kbd>. La page **Aide** du site
détaille tout (syntaxe Markdown, maths, tableaux).

| Raccourci | Effet |
|-----------|-------|
| <kbd>Ctrl</kbd> + <kbd>S</kbd> | Enregistrer tout de suite (l'auto-save continue aussi) |
| <kbd>Ctrl</kbd> + <kbd>Z</kbd> | Annuler |
| <kbd>Ctrl</kbd> + <kbd>Y</kbd> ou <kbd>Ctrl</kbd> + <kbd>Maj</kbd> + <kbd>Z</kbd> | Rétablir |
| <kbd>Ctrl</kbd> + <kbd>B</kbd> / <kbd>Ctrl</kbd> + <kbd>I</kbd> | Gras / italique |
| <kbd>Ctrl</kbd> + <kbd>M</kbd> | Nouvelle formule `$…$` |
| <kbd>Tab</kbd> / <kbd>Maj</kbd> + <kbd>Tab</kbd> | Décaler / recaler les lignes — dans un tableau : case suivante / précédente |
| <kbd>Entrée</kbd> dans une liste | Nouvelle puce (sur une puce vide = fin de la liste) |
| <kbd>Entrée</kbd> dans un tableau | Nouvelle ligne (sur une ligne vide = sortie du tableau) |
| <kbd>\\</kbd> + lettres dans une formule | Autocomplétion LaTeX (`\pour` → ∀, `\int` → ∫…) ; <kbd>↑</kbd>/<kbd>↓</kbd> pour choisir, <kbd>Entrée</kbd>/<kbd>Tab</kbd> pour valider |
| <kbd>Tab</kbd> dans une formule | Champ suivant à remplir, puis sortie de la formule |
| <kbd>Échap</kbd> | Ferme l'autocomplétion / la fenêtre ouverte |

**Éditeur visuel de tableau** : <kbd>Tab</kbd> case suivante, <kbd>Entrée</kbd>
case du dessous, <kbd>Maj</kbd> + <kbd>Entrée</kbd> case du dessus,
<kbd>Ctrl</kbd> + <kbd>Entrée</kbd> insérer le tableau.

**Assistant IA** : <kbd>Entrée</kbd> envoie la question, <kbd>Maj</kbd> + <kbd>Entrée</kbd> va à la ligne.

**Syntaxe pratique**
- Tableau rapide : tape `Titre 1 | Titre 2` puis <kbd>Entrée</kbd>.
- Texte en couleur : `[texte]{rouge}` — surlignage : `==texte==`.
- Code coloré : ```` ```c ````, ```` ```python ````, ```` ```sql ````, ```` ```pseudo ````…
- Maths : `$x^2$` dans le texte, `$$…$$` en bloc ; raccourcis `\R \N \Z \Q \C`,
  `\abs{x}`, `\norm{u}`, `\ens{1, 2, 3}`.

---

## 11. Dépannage

| Problème | Solution |
|----------|----------|
| « Erreur de connexion à la base de données » | MySQL est-il démarré ? `config.php` existe-t-il et contient-il les bons identifiants / port (MAMP : `root`/`root`, port `8889`) ? `schema.sql` a-t-il été importé ? |
| Page blanche ou code PHP affiché | Apache n'est pas lancé, ou tu as ouvert le fichier directement au lieu de passer par `http://localhost/...` |
| Apache ne démarre pas (XAMPP) | Le port 80 est pris (Skype, IIS…) : ferme l'appli ou change le port dans *Config → httpd.conf* |
| MySQL ne démarre pas | Un autre MySQL tourne déjà (port 3306) : arrête-le ou change le port |
| `php` / `mysql` / `composer` introuvable | Ajoute `C:\xampp\php` et `C:\xampp\mysql\bin` au `PATH`, puis rouvre le terminal |
| Import de fichier refusé | 20 Mo max et types autorisés (PDF, images, .txt, .docx…). Vérifie aussi `upload_max_filesize` et `post_max_size` dans `php.ini` |
| Formules / Markdown mal affichés | Il faut une connexion Internet (bibliothèques chargées en ligne) |
| Le bouton 🤖 Aide IA ne répond pas | `composer install` fait ? Clé API dans `config.php` ? |
| `git pull` refuse (modifications locales) | `git stash`, puis `git pull`, puis `git stash pop` |

---

## 12. Fonctionnalités
- **Comptes** sécurisés (inscription/connexion, mots de passe hachés) — chacun ne voit que ses notes.
- **Éditeur Markdown** avec aperçu en direct, **auto-save**, horodatage,
  **barre d'outils** (titres, gras, couleurs, listes, tableaux, maths, code).
- **Notes indentées** acceptées : le gras et les puces marchent même décalés.
- **Texte en couleur** `[texte]{rouge}` et **surlignage** `==texte==`.
- **Tableaux faciles** : création rapide, alignement auto, éditeur visuel type
  tableur, collage depuis Excel / Google Sheets.
- **Coloration du code** (C, Python, SQL, JS…, et **pseudo-code**) et
  **formules LaTeX** (MathJax) : palette de ~280 symboles avec recherche,
  autocomplétion, champs à remplir, aperçu de la formule sous le curseur.
- **Assistant IA** (Claude) pour dépanner ou expliquer un cours, avec la note jointe.
- **Tags** transversaux, **favoris** (⭐), **corbeille** (restauration possible).
- **Import de fichiers** (PDF, images, .txt, .docx… 20 Mo max) rattachés aux notes.
- **Export** d'une note ou d'une matière entière en **Markdown** et **PDF**.
- **Recherche** plein texte (titre + contenu) avec filtre par tag.
- **Échéances** (DS, rendus, examens) avec rappels sur le tableau de bord.
- **Révision** par flashcards (question/réponse, mode révision mélangée).
- **Tableau de bord** : dernières notes, favoris, prochaines échéances, stats.
- **Mode sombre** + **interface responsive** (PC en amphi / téléphone).

### Sécurité mise en place
- Requêtes **préparées PDO** partout (aucune concaténation SQL).
- Mots de passe **hachés** (`password_hash` / `password_verify`).
- Sessions durcies (`httponly`, `samesite`) + **jetons CSRF** sur les écritures.
- Échappement HTML en sortie + **DOMPurify** sur le Markdown rendu (anti-XSS).
- Uploads validés (extension + type MIME + taille), noms de stockage aléatoires.
- Dossier `uploads/` inaccessible en direct : les fichiers passent par
  `telecharger.php` qui vérifie le propriétaire.

### Structure du projet
```
config.exemple.php    modèle de configuration (à copier en config.php)
config.php            identifiants BDD + clé API (non versionné)
schema.sql            structure + données de démo
includes/             connexion PDO, auth, fonctions, gabarits (header/footer)
api/                  points d'entrée AJAX JSON (notes, tags, structure,
                      echeances, flashcards, upload, preferences, assistant)
assets/css, assets/js CSS + JavaScript (app, editeur, outils-editeur,
                      rendu, assistant, maths-symboles, mathjax-config)
composer.json         dépendance du SDK Anthropic (assistant IA)
uploads/              fichiers importés (accès via telecharger.php)
index.php             tableau de bord            recherche.php   recherche
note.php              éditeur de note            corbeille.php   corbeille
matiere.php           notes d'une matière        echeances.php   échéances
inscription/connexion revision.php  flashcards   reglages.php    réglages
aide.php              aide (syntaxe, maths, raccourcis)
export.php / imprimer.php   exports Markdown / PDF
```

### Réalisé
- [x] Socle : base, config, auth, thème, tableau de bord
- [x] Éditeur de notes (Markdown, code, LaTeX, auto-save)
- [x] Structure (CRUD UE/matières), recherche, tags, favoris, corbeille
- [x] Import de fichiers, export PDF / Markdown
- [x] Échéances, statistiques, flashcards
- [x] Assistant IA
