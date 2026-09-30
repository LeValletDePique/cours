# Plan de travail — prise de notes rapide + révisions automatiques

> Outil personnel, mono-utilisateur, en local (WSL Debian, `php -S localhost:8080`).
> Règle n°1 : **zéro effort de saisie**. Règle n°2 : **ne rien casser** (notes intactes,
> migrations incrémentales, jamais de `DROP TABLE`).

## État des lieux (ce que j'ai lu)

| Élément | Constat utile pour la suite |
|---|---|
| `schema.sql` | Commence par des `DROP TABLE` : **ne plus jamais le réimporter** sur la vraie base. Les évolutions passeront par `migrations/`. |
| `api/notes.php` (`maj`) | `UPDATE` aveugle : la dernière requête gagne, aucun historique → risque d'écrasement (deux onglets, `beforeunload` tardif). |
| `assets/js/editeur.js` | Auto-save 1 s après la frappe. Hors ligne : affiche « non enregistré » et **rien n'est gardé** si l'onglet se ferme. |
| `note.php`, `aide.php`, `imprimer.php` | marked 4.3.0, DOMPurify 3.0.9, highlight.js 11.9.0 (+ 2 thèmes CSS), MathJax 3.2.2 chargés depuis cdnjs → éditeur cassé sans wifi. MathJax charge aussi ses extensions (`mathtools`, `cancel`, `color`) et ses polices depuis le CDN. |
| `assets/js/outils-editeur.js` | Gère déjà Ctrl+S/Z/Y/B/I/M, Tab. Les nouveaux raccourcis doivent éviter ces touches. |
| Création de note | Seulement via `matiere.php` (formulaire) : 3 clics + choix de la matière. |
| `revision.php`, `api/flashcards.php` | Flashcards manuelles, sans planification (Phase 2). |
| `api/assistant.php` | SDK PHP `anthropic-ai/sdk`, modèle `claude-opus-5-5`, API *beta* avec `fallbacks`. À vérifier en Phase 2. |
| Racine du dépôt | Deux fichiers de notes brutes (`BDD-1`, `algorithmique procédurale`) versionnés : je n'y touche pas. |

## Mécanique commune

- `migrations/000_suivi.sql` : table `schema_migrations` (nom du fichier + date).
- `migrations/appliquer.php` (CLI : `php migrations/appliquer.php`) : applique dans l'ordre les
  fichiers `NNN_*.sql` absents de `schema_migrations`, **refuse** tout fichier contenant
  `DROP TABLE`, et lance d'abord une sauvegarde.
- `outils/sauvegarder.sh` : `mysqldump --single-transaction --routines cours_db > sauvegardes/cours_db_AAAA-MM-JJ_HHMM.sql`
  (dossier `sauvegardes/` ignoré par Git). **À exécuter à la main avant la première migration.**
- Toutes les migrations sont additives (`CREATE TABLE IF NOT EXISTS`, `ALTER TABLE … ADD COLUMN`).

---

## PHASE 1 — Prise de notes sans friction

### 1.1 Migrations

| Fichier | Contenu |
|---|---|
| `001_versions_notes.sql` | `notes.version INT UNSIGNED NOT NULL DEFAULT 1` (verrou optimiste) + table `note_versions` (id, note_id, utilisateur_id, titre, contenu, date_version, origine `auto`/`conflit`), FK `ON DELETE CASCADE`. |
| `002_emploi_du_temps.sql` | `edt_cours` (utilisateur_id, debut, fin, intitule, lieu, matiere_id NULL) + `edt_correspondances` (utilisateur_id, intitule_normalise, matiere_id) pour **apprendre** automatiquement « intitulé ENT → matière ». |
| `003_marqueurs.sql` | `note_marqueurs` (note_id, utilisateur_id, matiere_id, type `retenir`/`question`/`todo`, texte, empreinte, resolu, date_creation). Remplie automatiquement à chaque sauvegarde. |

### 1.2 Bibliothèques en local (hors ligne)

- Nouveau dossier `assets/vendor/` : marked, DOMPurify, highlight.js (+ `github`/`github-dark`),
  MathJax 3.2.2 es5 **avec** `input/tex/extensions/{mathtools,cancel,color}.js` et
  `output/chtml/fonts/woff-v2/` (sinon les formules s'affichent sans polices hors ligne).
- Mêmes versions exactes qu'aujourd'hui → rendu identique.
- Fichiers touchés : `note.php`, `aide.php`, `imprimer.php` (remplacement des URL CDN).
- Un `includes/librairies.php` centralise les balises pour ne plus dupliquer les URL.

### 1.3 Nouvelle note en 1 clic

- **Bouton flottant ＋** sur toutes les pages (dans `includes/footer.php`, au-dessus du bouton IA)
  et raccourci. ⚠ Chrome/Firefox **n'autorisent pas** une page à intercepter `Ctrl+N`
  (ouvre une fenêtre, impossible à bloquer). Proposition : **`Alt+N`** partout (et `Ctrl+N`
  capté seulement là où le navigateur le permet).
- Nouvelle action `api/notes.php?action=creer_rapide` (le serveur décide tout) :
  1. matière = cours de l'EDT en cours (tolérance −15 min / +10 min avant la fin) ;
  2. sinon matière de la dernière note modifiée ;
  3. titre `Matière — mer. 30/09/2026` (+ « CM/TD » si l'intitulé ENT le dit) ;
  4. contenu = gabarit `## Chapitre / ## Notions clés / ## Exemples / ## Questions / ## À retenir` ;
  5. **anti-doublon** : si une note créée ainsi il y a moins de 2 h pour la même matière est
     restée identique au gabarit, on la rouvre au lieu d'en créer une nouvelle.
- Redirection vers `note.php?id=…` en mode amphi.
- Si je change la matière dans la note, la correspondance intitulé ENT → matière est
  mémorisée (`edt_correspondances`) : la détection s'améliore toute seule, sans réglage.

### 1.4 Emploi du temps (`.ics`)

- Page `emploi-du-temps.php` : **un seul champ fichier** + résumé (« 142 cours importés,
  du … au … ; 3 intitulés non reconnus ») et le cours du moment.
- `includes/ics.php` : lecteur ICS minimal (lignes repliées, `DTSTART`/`DTEND` en `Z` ou
  `TZID=Europe/Paris`, `SUMMARY`, `LOCATION`, `RRULE` hebdomadaire simple).
- Réimport = remplacement des cours **futurs** uniquement (le passé et les correspondances
  apprises sont conservés).
- Association intitulé → matière : normalisation (minuscules, sans accents ni ponctuation,
  sans « CM/TD/TP ») puis recouvrement de mots avec les noms de matières ; correspondances
  apprises prioritaires.
- Lien ajouté dans la barre de navigation (`includes/header.php`).

### 1.5 Mode « amphi » + sauvegarde locale

- `assets/js/stockage-local.js` : petite file IndexedDB (`mes-cours` → `brouillons`), clé = id de la note,
  valeur = {titre, contenu, matiere_id, version_base, horodatage}.
- `editeur.js` : chaque frappe écrit **immédiatement** (anti-rebond 300 ms) dans IndexedDB, puis
  l'envoi serveur se fait comme aujourd'hui. En cas d'échec : réessai automatique (`online`,
  toutes les 15 s, au retour sur l'onglet). Le brouillon n'est effacé qu'après confirmation serveur.
- À l'ouverture d'une note : si un brouillon local plus récent que la version serveur existe,
  il est rechargé et renvoyé automatiquement (bandeau discret « brouillon local restauré »).
- Indicateur discret : `● enregistré` / `● en attente (n)` / `● conflit`.
- Mode amphi (`Alt+A`, mémorisé dans `localStorage`, activé d'office après « nouvelle note ») :
  barre de navigation, pied de page, panneau tags/fichiers/export et aperçu masqués ; barre
  d'outils réduite ; éditeur plein écran. CSS dans `style.css` (`body.mode-amphi`).
- Limite honnête : si `php -S` est arrêté, la **page** ne peut pas s'ouvrir ; ce qui est garanti,
  c'est qu'une note déjà ouverte continue d'être tapée et gardée, puis envoyée au redémarrage
  (serveur relancé, MariaDB relancée, session expirée → reconnexion).

### 1.6 Historique + verrouillage optimiste

- `api/notes.php?action=maj` reçoit `version`. `UPDATE … SET version = version + 1 WHERE id=? AND version=?`.
  - 0 ligne modifiée → **409 conflit** : le texte envoyé est archivé dans `note_versions`
    (origine `conflit`), rien n'est écrasé ; l'éditeur propose « recharger la version serveur » ou
    « garder la mienne » (qui repart de la nouvelle version).
- Avant une mise à jour, l'ancienne version est copiée dans `note_versions` **si** la dernière copie
  a plus de 5 min (sinon l'auto-save chaque seconde remplirait les 10 places en 10 secondes).
  Purge au-delà des 10 plus récentes par note.
- Transaction PDO pour « copie + update + purge ».
- `note.php` : petit menu « 🕘 Versions » (liste date + taille, aperçu, restaurer = créer une
  nouvelle version, jamais de suppression).
- La sauvegarde `beforeunload` (keepalive) envoie aussi la version.

### 1.7 Marqueurs `!!`, `??`, `TODO`

- Reconnus **uniquement en début de ligne** (après l'indentation / puce éventuelle), hors blocs de
  code et de maths : `!! texte`, `?? texte`, `TODO texte`. Évite les faux positifs (`??` en PHP,
  « attention !! » en fin de phrase).
- `includes/marqueurs.php` : extraction côté serveur à chaque `maj` ; resynchronisation de
  `note_marqueurs` en conservant l'état `resolu` via l'empreinte du texte.
- Rendu (`rendu.js`) : pastilles visuelles (📌 à retenir, ❓ question, ☐ à faire) dans l'aperçu,
  le PDF et l'aide.
- Page `questions.php` : questions à poser au prof groupées par matière, case « posée » en 1 clic ;
  lien depuis `matiere.php` (compteur) et encart « À faire » (TODO) sur `index.php`.
- Script de rattrapage lancé une fois par la migration pour indexer les notes existantes
  (lecture seule sur `notes`).

### 1.8 Raccourcis + aide

`assets/js/raccourcis.js` (chargé partout via `header.php`) :

| Raccourci | Action |
|---|---|
| `Alt+N` (et `Ctrl+N` si possible) | Nouvelle note |
| `Alt+M` | Changer de matière (ouvre le sélecteur de la note) |
| `Ctrl+K` ou `/` hors champ | Rechercher |
| `Alt+P` | Basculer édition / aperçu |
| `Alt+A` | Mode amphi |
| `Alt+Q` | Liste des questions à poser |
| `?` hors champ | Aide-mémoire des raccourcis |

Section « Raccourcis et marqueurs » ajoutée dans `aide.php`.

### 1.9 Risques de régression Phase 1

1. **Données** : migrations additives, sauvegarde préalable ; `notes.version` a une valeur par défaut → les anciennes notes fonctionnent.
2. **Auto-save** : un client ancien (onglet ouvert avant la mise à jour) n'envoie pas `version` → le serveur l'accepte en mode compatible (sans verrou) pendant la transition.
3. **Rendu** : la conversion `!!`/`??`/`TODO` en début de ligne peut changer l'affichage d'une note existante qui commence une ligne ainsi → je compte ces cas avant (requête SQL) et te les liste.
4. **MathJax local** : chemin des extensions/polices ; je vérifie formules, `\R`, `cancel`, `color` hors ligne.
5. **Raccourcis** : conflits Alt+lettre avec les menus Firefox sous Linux ; testés sur Chrome + Firefox.
6. **IndexedDB** : brouillon restauré par erreur → la version serveur n'est jamais écrasée sans passer par `version` + historique.
7. **Taille du dépôt** : MathJax local ≈ 4–5 Mo (seulement le nécessaire, pas tout le paquet).

### 1.10 Tests prévus

`php -l` sur tous les fichiers, MariaDB installée dans mon conteneur pour jouer les migrations sur
un `schema.sql` de démo + une note existante, appels `curl` à l'API (création rapide, maj, conflit
409, historique ≤ 10), test de l'import `.ics` sur un fichier d'exemple, et navigateur Chromium
sans réseau pour l'éditeur/hors ligne. Puis une liste de vérifications manuelles pour toi.

---

## PHASE 2 — Réviser sans effort (aperçu, détaillé après validation de la Phase 1)

- **Migrations** : `004_flashcards_sm2.sql` (colonnes `facilite`, `intervalle`, `repetitions`,
  `prochaine_revision`, `statut` `proposee`/`active`/`ecartee`, `origine`), `005_quiz.sql`,
  `006_plans_revision.sql` (plan par échéance + items cochables), `007_usage_ia.sql` (jetons et coût par appel).
- **Génération IA** (`api/generation.php`) : cartes/quiz proposés depuis une note (+ blocs `!!`
  comme candidats) ; valider/écarter en 1 clic ; sortie JSON structurée.
- **SM-2** dans `includes/sm2.php` ; file du jour plafonnée (20 par défaut dans `config.php`).
- **Mode avant DS** : plan réparti sur les jours restants (soirées 2–3 h), liste du jour à cocher.
- **Sélecteur 30 min / 1 h / 2 h** sur `revision.php` : cartes dues + notes à relire + questions du prof.
- **Fiche de synthèse** par matière/chapitre, export PDF via `imprimer.php`.
- **API** : vérification du modèle, des paramètres (`beta`, `fallbacks`, `outputConfig`, `cacheControl`)
  contre la doc actuelle du SDK ; plafond mensuel de coût (`config.php`) + compteur visible.
- **Risques** : coût API (plafond bloquant), cartes de mauvaise qualité (jamais activées sans validation),
  conservation des cartes manuelles existantes (statut `active` par défaut).
