<?php
// ============================================================
// quiz_acces.php — ENTRER DANS LE QUIZ SANS SE RECONNECTER.
//
// La personne est déjà authentifiée sur FamiFormation : lui redemander son
// identifiant et son mot de passe pour aller au quiz n'a aucun sens. Cette page
// fabrique le jeton de session du quiz et redirige avec.
//
// Le jeton est exactement celui de quiz/api.php (faitJeton) : même format, même
// clé secrète, lue dans le fichier du volume. On ne CRÉE jamais cette clé ici —
// si elle n'existe pas encore, on redirige sans jeton et le quiz demandera les
// identifiants comme avant. Inventer une clé de notre côté produirait des jetons
// que l'API refuserait.
// ============================================================
require_once 'config.php';
verifierConnexion($db);

$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    header('Location: login.php');
    exit();
}

/** Dossier de données du quiz — même détection que quiz/api.php. */
function quizAccesDataDir()
{
    $vol = getenv('RAILWAY_VOLUME_MOUNT_PATH');
    if (!$vol && isset($_SERVER['RAILWAY_VOLUME_MOUNT_PATH'])) {
        $vol = $_SERVER['RAILWAY_VOLUME_MOUNT_PATH'];
    }
    if ($vol && @is_dir($vol)) {
        return rtrim($vol, "/\\") . '/quiz';
    }
    return __DIR__ . '/quiz/data';
}

/** Clé secrète du quiz, ou '' si elle n'existe pas encore. */
function quizAccesSecret()
{
    $f = quizAccesDataDir() . '/secret.txt';
    if (!is_file($f)) { return ''; }
    return trim((string) @file_get_contents($f));
}

// Identifiant du compte : c'est la clé du joueur dans le quiz, et donc la seule
// chose vraiment indispensable ici.
//
// ⚠️ REQUÊTE VOLONTAIREMENT SEULE. Elle demandait aussi `site_id` — une colonne
// ajoutée à part par ensureWidgetTables() et qui n'existe donc pas partout. Si
// elle manque, MySQL rejette toute la requête : on perdait l'identifiant avec
// elle, aucun jeton n'était fabriqué, et la personne se retrouvait devant un
// écran qui lui redemandait ses identifiants — exactement ce que cette page est
// censée éviter. Le magasin, lui, n'est qu'un confort : il se cherche plus bas,
// séparément, et son échec ne coûte rien.
$identifiant = '';
$erreurs = [];
try {
    $st = $db->prepare('SELECT identifiant FROM utilisateurs WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $identifiant = trim((string) ($st->fetchColumn() ?: ''));
} catch (Throwable $e) {
    $erreurs[] = 'identifiant : ' . $e->getMessage();
}
// Dernier recours : l'identifiant est déjà en session (posé à la connexion).
// Mieux vaut s'en servir que de renvoyer la personne vers un formulaire.
if ($identifiant === '') {
    $identifiant = trim((string) ($_SESSION['username'] ?? ''));
    if ($identifiant !== '') { $erreurs[] = 'identifiant repris de la session'; }
}

// 🏬 Magasin : on essaie de deviner celui de la personne pour l'emmener sur le
// bon classement. En cas de doute, on n'impose rien — le quiz appliquera son
// magasin par défaut plutôt que de se tromper. Requête séparée et sans
// conséquence : si la colonne ou la table manque, on continue sans magasin.
$site = '';
try {
    $sq = $db->prepare('SELECT s.nom, s.ville FROM utilisateurs u
                        JOIN widget_sites s ON s.id = u.site_id
                        WHERE u.id = ? LIMIT 1');
    $sq->execute([$uid]);
    $s = $sq->fetch(PDO::FETCH_ASSOC);
    if ($s) {
        $texte = mb_strtolower(($s['ville'] ?? '') . ' ' . ($s['nom'] ?? ''));
        if (strpos($texte, 'mouscron') !== false || strpos($texte, 'moeskroen') !== false) { $site = 'mouscron'; }
        elseif (strpos($texte, 'panne') !== false) { $site = 'lapanne'; }
    }
} catch (Throwable $e) {
    $erreurs[] = 'magasin : ' . $e->getMessage();
}

// Construction du jeton, à l'identique de faitJeton().
$secret = quizAccesSecret();
$params = ['espace' => '1'];   // on entre DIRECTEMENT dans l'espace jardin
if ($secret !== '' && $identifiant !== '') {
    $exp = time() + 60 * 86400;
    $corps = $uid . '|' . $exp . '|' . rawurlencode($identifiant);
    $params['jeton'] = $corps . '|' . hash_hmac('sha256', $corps, $secret);
}

$base = '/quiz/' . ($site !== '' ? $site : '');
$cible = $base . '?' . http_build_query($params);

// ============================================================
// 🔍 DIAGNOSTIC — /quiz_acces.php?diag=1, RÉSERVÉ AUX ADMINS.
//
// Quand la tuile n'emmène pas au bon endroit, il n'y a que quatre maillons
// possibles : la session, l'identifiant, la clé secrète, le magasin. Deviner
// lequel fait perdre un temps fou ; cette page le DIT. Elle n'affiche jamais la
// clé ni le jeton en entier, seulement de quoi savoir s'ils existent.
// ============================================================
if (isset($_GET['diag'])) {
    $role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
    if ($role !== 'admin') {
        header('Location: ' . $cible);
        exit();
    }
    $dir = quizAccesDataDir();
    $vol = getenv('RAILWAY_VOLUME_MOUNT_PATH') ?: ($_SERVER['RAILWAY_VOLUME_MOUNT_PATH'] ?? '');
    // Les deux emplacements possibles de la clé, pour vérifier que cette page et
    // quiz/api.php regardent bien le MÊME fichier.
    $pistes = [
        'volume  ' => ($vol !== '' ? rtrim($vol, "/\\") . '/quiz/secret.txt' : '(RAILWAY_VOLUME_MOUNT_PATH non defini)'),
        'dans app' => __DIR__ . '/quiz/data/secret.txt',
    ];
    $ok = static fn($b) => $b ? '✅' : '❌';
    header('Content-Type: text/plain; charset=utf-8');
    echo "DIAGNOSTIC — acces au quiz depuis FamiFormation\n";
    echo str_repeat('=', 58) . "\n\n";
    echo "SESSION\n";
    echo "  user_id                 : {$uid} " . $ok($uid > 0) . "\n";
    echo "  role                    : " . ($role ?: '(vide)') . "\n";
    echo "  username en session     : " . (($_SESSION['username'] ?? '') ?: '(vide)') . "\n\n";
    echo "IDENTIFIANT (cle du joueur dans le quiz)\n";
    echo "  trouve                  : " . ($identifiant !== '' ? $identifiant . ' ✅' : '(VIDE) ❌  <- sans lui, aucun jeton') . "\n\n";
    echo "CLE SECRETE (doit etre le MEME fichier que pour quiz/api.php)\n";
    echo "  dossier de donnees      : {$dir}\n";
    foreach ($pistes as $nom => $chemin) {
        $existe = @is_file($chemin);
        echo "  {$nom}              : " . $ok($existe) . " {$chemin}"
            . ($existe ? ' (' . strlen(trim((string) @file_get_contents($chemin))) . ' caracteres)' : '') . "\n";
    }
    echo "  clef lue par cette page : " . ($secret !== '' ? strlen($secret) . " caracteres ✅" : "(AUCUNE) ❌  <- aucun jeton") . "\n\n";
    echo "MAGASIN (confort seulement)\n";
    echo "  detecte                 : " . ($site !== '' ? $site : '(aucun, le quiz mettra son magasin par defaut)') . "\n\n";
    echo "RESULTAT\n";
    echo "  jeton fabrique          : " . (isset($params['jeton']) ? '✅ oui' : '❌ NON — le quiz redemandera les identifiants') . "\n";
    echo "  redirection vers        : " . $base . '?espace=1' . (isset($params['jeton']) ? '&jeton=…' : '') . "\n";
    if ($erreurs) {
        echo "\nINCIDENTS RENCONTRES\n";
        foreach ($erreurs as $x) { echo "  - {$x}\n"; }
    }
    echo "\n" . (isset($params['jeton'])
        ? "Tout est en place ici. Si l'espace redemande quand meme les identifiants,\n"
        . "c'est que quiz/api.php lit une AUTRE cle que celle ci-dessus : comparer\n"
        . "les deux chemins « volume » et « dans app », un seul doit exister.\n"
        : "C'est la ligne marquee ❌ ci-dessus qui bloque.\n");
    exit();
}

header('Location: ' . $cible);
exit();
