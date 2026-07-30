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

// Identifiant du compte : c'est la clé du joueur dans le quiz.
$identifiant = '';
try {
    $st = $db->prepare('SELECT identifiant, site_id FROM utilisateurs WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    $identifiant = trim((string) ($u['identifiant'] ?? ''));
} catch (Exception $e) {
    $u = null;
}

// 🏬 Magasin : on essaie de deviner celui de la personne pour l'emmener sur le
// bon classement. En cas de doute, on n'impose rien — le quiz appliquera son
// magasin par défaut plutôt que de se tromper.
$site = '';
try {
    if (!empty($u['site_id'])) {
        $sq = $db->prepare('SELECT nom, ville FROM widget_sites WHERE id = ? LIMIT 1');
        $sq->execute([(int) $u['site_id']]);
        $s = $sq->fetch(PDO::FETCH_ASSOC);
        $texte = strtolower(($s['ville'] ?? '') . ' ' . ($s['nom'] ?? ''));
        if (strpos($texte, 'mouscron') !== false || strpos($texte, 'moeskroen') !== false) { $site = 'mouscron'; }
        elseif (strpos($texte, 'panne') !== false) { $site = 'lapanne'; }
    }
} catch (Exception $e) { /* table absente : pas de magasin impose */ }

// Construction du jeton, à l'identique de faitJeton().
$secret = quizAccesSecret();
$params = ['espace' => '1'];   // on entre DIRECTEMENT dans l'espace jardin
if ($secret !== '' && $identifiant !== '') {
    $exp = time() + 60 * 86400;
    $corps = $uid . '|' . $exp . '|' . rawurlencode($identifiant);
    $params['jeton'] = $corps . '|' . hash_hmac('sha256', $corps, $secret);
}

$base = '/quiz/' . ($site !== '' ? $site : '');
header('Location: ' . $base . '?' . http_build_query($params));
exit();
