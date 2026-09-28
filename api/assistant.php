<?php
/**
 * Assistant IA (Claude) pour aider en cas de problème.
 *   POST body { messages: [{role: 'user'|'assistant', content: '...'}],
 *               page: 'note.php', note_id: 12 (facultatif : note partagée) }
 *   -> { ok: true, reponse: '...markdown...' }  ou  { erreur: '...' }
 *
 * Nécessite le SDK officiel (composer install) et une clé API dans
 * config.php ('anthropic_api_key') ou la variable d'environnement
 * ANTHROPIC_API_KEY.
 */
require_once __DIR__ . '/../includes/auth.php';

if (!utilisateur_connecte()) {
    repondre_json(['erreur' => 'Non connecté'], 401);
}
verifier_csrf();

$config = config_app();
$cle    = ($config['anthropic_api_key'] ?? '') ?: (getenv('ANTHROPIC_API_KEY') ?: '');
$modele = $config['assistant_modele'] ?? 'claude-opus-5-5';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    repondre_json(['erreur' => "L'assistant n'est pas installé : lance « composer install » "
        . 'à la racine du projet (voir README).'], 503);
}
if ($cle === '') {
    repondre_json(['erreur' => "Aucune clé API : ajoute 'anthropic_api_key' dans config.php "
        . '(voir config.exemple.php).'], 503);
}
require_once $autoload;

// --- Validation de la conversation ---
$data = corps_json();
$brut = is_array($data['messages'] ?? null) ? $data['messages'] : [];
$brut = array_slice($brut, -20);   // on garde les 20 derniers échanges

$messages = [];
foreach ($brut as $m) {
    $role    = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $contenu = trim((string) ($m['content'] ?? ''));
    if ($contenu === '') continue;
    if (mb_strlen($contenu) > 8000) {
        repondre_json(['erreur' => 'Message trop long (8000 caractères max).'], 400);
    }
    // Les rôles doivent alterner : on fusionne deux messages consécutifs du même rôle.
    $dernier = count($messages) - 1;
    if ($dernier >= 0 && $messages[$dernier]['role'] === $role) {
        $messages[$dernier]['content'] .= "\n\n" . $contenu;
    } else {
        $messages[] = ['role' => $role, 'content' => $contenu];
    }
}
while ($messages && $messages[0]['role'] !== 'user') array_shift($messages);
if (!$messages || end($messages)['role'] !== 'user') {
    repondre_json(['erreur' => 'Écris ta question.'], 400);
}

// --- Contexte : page courante et, si l'utilisateur l'a coché, sa note ---
$page = preg_replace('/[^a-z_]/', '', (string) ($data['page'] ?? ''));
$contexte = 'Page ouverte : ' . ($page !== '' ? $page . '.php' : 'inconnue') . '.';

$note_id = (int) ($data['note_id'] ?? 0);
if ($note_id > 0) {
    $stmt = db()->prepare(
        'SELECT titre, contenu FROM notes WHERE id = ? AND utilisateur_id = ? AND supprime = 0'
    );
    $stmt->execute([$note_id, utilisateur_id()]);
    if ($note = $stmt->fetch()) {
        if (mb_strlen((string) $note['contenu']) > 200000) {
            repondre_json(['erreur' => 'Cette note est trop longue pour être partagée en entier '
                . "avec l'assistant. Décoche « Joindre ma note » et copie seulement le passage utile."], 400);
        }
        $contexte .= "\n\nL'étudiant a partagé la note ouverte dans l'éditeur (Markdown brut) :\n"
            . "<note titre=\"" . htmlspecialchars($note['titre'], ENT_QUOTES) . "\">\n"
            . $note['contenu'] . "\n</note>";
    }
}

// Partie fixe du prompt (mise en cache) : ce que sait faire la plateforme.
$systeme = <<<'TXT'
Tu es l'assistant intégré à « Mes Cours », une plateforme web de prise de notes utilisée par un étudiant en école d'ingénieur (CY Tech, prépa intégrée). Tu réponds en français, avec des réponses courtes et concrètes, formatées en Markdown (elles sont affichées avec le même rendu que les notes).

Tu aides pour deux choses :
1. Utiliser la plateforme et résoudre un problème (mise en forme qui ne s'affiche pas, raccourci, fonctionnalité introuvable…). Donne toujours la syntaxe exacte à taper.
2. Comprendre ou réviser un cours (expliquer une notion, corriger un pseudo-code, proposer un exercice ou des questions de révision).

Fonctionnement de la plateforme :
- Organisation UE → Matière → Note, avec tags, favoris (⭐), corbeille, recherche plein texte, échéances, flashcards (page Révision), import de fichiers joints, export Markdown et PDF.
- L'éditeur est en Markdown avec aperçu en direct et enregistrement automatique ; Ctrl+S enregistre immédiatement.
- Barre d'outils : annuler/rétablir, titres H1-H3, gras, italique, barré, couleurs, listes, cases à cocher, citation, tableau, symboles mathématiques, blocs de code, lien, séparateur.
- Raccourcis : Ctrl+Z annuler, Ctrl+Y ou Ctrl+Maj+Z rétablir, Ctrl+B gras, Ctrl+I italique, Tab / Maj+Tab décaler / recaler les lignes, Entrée dans une liste crée la puce suivante.
- Couleurs : [texte]{rouge} (rouge, orange, jaune, vert, bleu, violet, rose, gris, ou [texte]{#e11d48}). Surlignage : ==texte==.
- Retour à la ligne simple = retour à la ligne affiché. Les lignes décalées (Tab = 4 espaces) restent du texte normal : gras, puces et couleurs y fonctionnent, et le décalage s'affiche.
- Puces : « - », « * » ou « • » suivis d'un espace. Listes numérotées : « 1. ». Cases : « - [ ] ».
- Tableaux : taper « Titre 1 | Titre 2 | Titre 3 » puis Entrée crée le tableau ; dans un tableau, Tab = case suivante, Entrée = nouvelle ligne, Entrée sur une ligne vide = sortie ; le bouton « ▦ Tableau » ouvre un éditeur visuel type tableur (et modifie le tableau sous le curseur) ; coller depuis Excel/Sheets crée un tableau.
- Maths en LaTeX (MathJax) : $...$ dans le texte, $$...$$ ou un bloc ```math centré ; Ctrl+M ouvre une formule. Dans une formule, taper \ puis un nom ou un mot français (\pour, \appart, \integ) propose les commandes ; Tab passe au champ suivant d'un modèle ; une bulle affiche la formule rendue. Menu « ∑ Maths » : recherche, ~280 symboles et modèles (cases, matrices n×p, tableaux de variations…). Raccourcis du site : \R \N \Z \Q \C \K, \abs{x}, \norm{u}, \ens{…}, \llbracket, \pgcd, \ppcm, \dx, \eps ; paquets mathtools et cancel chargés. La page Aide contient une référence complète avec recherche.
- Code coloré : ```c, ```python, ```sql, ```javascript, ```bash… et ```pseudo pour le pseudo-code (mots-clés français : Algorithme, Variables, SI … ALORS … SINON, POUR i DE 1 À n FAIRE, TANT QUE … FAIRE, SELON/Cas/Défaut, Lire(), Ecrire(), <- pour l'affectation, types Entier, Réel, Booléen, Chaîne, Caractère).
- Page « Aide » : aide-mémoire complet avec exemples et bac à sable.

Si l'étudiant signale un rendu incorrect et a joint sa note, repère la ligne fautive et donne la correction exacte. Ne prétends pas avoir modifié sa note : tu ne peux que conseiller. Si tu ne sais pas, dis-le.
TXT;

try {
    $client  = new \Anthropic\Client(apiKey: $cle);
    $message = $client->beta->messages->create(
        maxTokens: 16000,
        messages: $messages,
        model: $modele,
        system: [
            ['type' => 'text', 'text' => $systeme, 'cacheControl' => ['type' => 'ephemeral']],
            ['type' => 'text', 'text' => $contexte],
        ],
        outputConfig: ['effort' => 'medium'],
        // Si le modèle refuse pour raison de sécurité, l'API réessaie
        // automatiquement avec un modèle de repli.
        fallbacks: 'default',
        betas: ['server-side-fallback-2026-07-01'],
    );
} catch (\Anthropic\Core\Exceptions\AuthenticationException $e) {
    repondre_json(['erreur' => 'Clé API refusée : vérifie anthropic_api_key dans config.php.'], 502);
} catch (\Anthropic\Core\Exceptions\RateLimitException $e) {
    repondre_json(['erreur' => 'Trop de demandes pour le moment, réessaie dans une minute.'], 429);
} catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
    repondre_json(['erreur' => "L'assistant a renvoyé une erreur (" . $e->getMessage() . ').'], 502);
} catch (\Anthropic\Core\Exceptions\APIConnectionException $e) {
    repondre_json(['erreur' => "Impossible de joindre l'assistant (connexion Internet ?)."], 502);
} catch (\Anthropic\Core\Exceptions\AnthropicException $e) {
    repondre_json(['erreur' => "Erreur de l'assistant : " . $e->getMessage()], 502);
}

if ($message->stopReason === 'refusal') {
    repondre_json(['erreur' => "L'assistant ne peut pas répondre à cette demande."], 200);
}

$reponse = '';
foreach ($message->content as $bloc) {
    if ($bloc->type === 'text') {
        $reponse .= $bloc->text;
    }
}
if ($message->stopReason === 'max_tokens') {
    $reponse .= "\n\n*(Réponse coupée car trop longue — demande la suite.)*";
}

repondre_json(['ok' => true, 'reponse' => $reponse]);
