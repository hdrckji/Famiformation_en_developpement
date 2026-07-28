<?php

require_once 'config.php';
require_once __DIR__ . '/includes/theme.php'; // famiDefaultVectorBg() : fond vectoriel net

// Une session peut encore etre active dans ce profil de navigateur (on n'a pas ferme
// sa session avant de revenir ici). config.php installe alors le buffer d'injection,
// qui pose le ruban « connecte » (retour, notifications, deconnexion...) sur TOUTES les
// pages. Sur une page de connexion ce ruban n'a aucun sens, et il cassait la mise en
// page. On declare donc le ruban « deja fait » : le buffer ne l'ajoutera pas.
$GLOBALS['__fami_topbar_done'] = true;
// Correction : forcer l'initialisation du token CSRF dès la première visite
initCSRF();

ensureUserAccountAccessColumns($db);

$erreur = "";

$requestedRedirect = trim((string) ($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = explode(':', $requestHost)[0];
// famiformation.com redirige vers www.famiformation.com : sans ça, tous les visiteurs
// tombaient dans le cas par défaut et voyaient le titre générique « Connexion ».
$requestHost = preg_replace('~^www\.~', '', $requestHost);
$allowedRedirects = [
    '../Famijob/index.php',
    '/Famijob/index.php',
    'Famijob/index.php',
    '../famijob/index.php',
    '/famijob/index.php',
    'famijob/index.php',
];
$postLoginRedirect = in_array($requestedRedirect, $allowedRedirects, true) ? $requestedRedirect : '';
$isFamijobHost = ($requestHost === 'student.famiformation.com');
$isFamijobLogin = $postLoginRedirect !== '' || $isFamijobHost;
$loginBackgroundImage = $isFamijobLogin ? '/background-famijob.png' : 'background.jpg';

function resolvePublicAssetUrl(array $absoluteCandidates, string $fallbackUrl): string {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;

    foreach ($absoluteCandidates as $candidate) {
        $realPath = realpath($candidate);
        if ($realPath === false || !is_file($realPath)) {
            continue;
        }

        $version = @filemtime($realPath) ?: time();

        if ($docRoot !== false) {
            $normalizedDocRoot = str_replace('\\', '/', $docRoot);
            $normalizedRealPath = str_replace('\\', '/', $realPath);

            if (strpos($normalizedRealPath, $normalizedDocRoot) === 0) {
                $publicPart = ltrim(substr($normalizedRealPath, strlen($normalizedDocRoot)), '/');
                return '/' . $publicPart . '?v=' . $version;
            }
        }

        return $fallbackUrl . '?v=' . $version;
    }

    return $fallbackUrl . '?v=' . time();
}

$loginBackgroundUrl = resolvePublicAssetUrl(
    [
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'background-famijob.png',
        __DIR__ . DIRECTORY_SEPARATOR . ltrim($loginBackgroundImage, '/'),
        dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($loginBackgroundImage, '/'),
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'famijob' . DIRECTORY_SEPARATOR . 'background-famijob.png',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Famijob' . DIRECTORY_SEPARATOR . 'background-famijob.png',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim($loginBackgroundImage, '/'),
    ],
    $loginBackgroundImage
);

// Deja connecte : la page de connexion n'a plus rien a demander. On renvoie a l'accueil
// plutot que d'afficher un formulaire inutile. Se deconnecter reste possible depuis le
// ruban (logout.php detruit la session avant de revenir ici, donc pas de boucle).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    $roleConnecte = (string) ($_SESSION['role'] ?? '');
    if ($postLoginRedirect !== '' && in_array($roleConnecte, ['admin', 'teamcoach'], true)) {
        $cibleConnecte = $postLoginRedirect;
    } elseif ($roleConnecte === 'agence_interim') {
        $cibleConnecte = 'interim_horaires.php';
    } else {
        $cibleConnecte = 'index.php';
    }
    header('Location: ' . $cibleConnecte);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation CSRF
    requireValidCSRF();
    
    $identifiant = $_POST['username'] ?? '';
    $mdp = $_POST['password'] ?? '';

    // On récupère l'utilisateur ET son rôle
    $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE identifiant = ?");
    $stmt->execute([$identifiant]);
    $user = $stmt->fetch();

    if ($user && empty($user['account_activation_pending']) && !empty($user['mot_de_passe']) && password_verify($mdp, $user['mot_de_passe'])) {
        // --- CRUCIAL : On enregistre les infos en SESSION ---
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['identifiant'];
        $_SESSION['role'] = $user['role']; // Permet le filtrage des tuiles
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['prenom'] = $user['prenom'];
        $_SESSION['photo_profil'] = $user['photo_profil'] ?? null;

        // Langue par défaut selon la ville de résidence (modifiable ensuite via le sélecteur)
        if (function_exists('villeLangueDefaut')) {
            $_SESSION['lang'] = villeLangueDefaut($user['ville'] ?? '');
        }

        // Renouvelle le token CSRF après connexion
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        if ($postLoginRedirect !== '' && in_array((string) ($user['role'] ?? ''), ['admin', 'teamcoach'], true)) {
            $redirectTarget = $postLoginRedirect;
        } else {
            $redirectTarget = (($user['role'] ?? '') === 'agence_interim')
                ? 'interim_horaires.php'
                : 'index.php';
        }

        header('Location: ' . $redirectTarget);
        exit();
    } else {
        $erreur = "Identifiant ou mot de passe incorrect.";
    }
}

// login.php : version dynamique selon le domaine
$host = $requestHost;
$title = '';
$label = '';
if ($host === 'famiformation.com') {
    $title = 'Centre de formations de Famiflora';
    $label = 'Identifiant formation';
} elseif ($host === 'famistudent.com') {
    $title = 'Connexion Étudiant';
    $label = 'Identifiant étudiant';
} elseif ($host === 'student.famiformation.com') {
    $title = 'Connexion Famijob';
    $label = 'Identifiant';
} else {
    $title = 'Connexion';
    $label = 'Identifiant';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Sans box-sizing, .container (width:100% + 80px de padding) et les champs
           debordaient de l'ecran sur telephone : la page arrivait « en desordre »
           quand on ouvrait le lien du mail depuis son mobile. */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: url('<?php echo e($loginBackgroundUrl); ?>') center/cover no-repeat #f6f6f6;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }
        /* Le centrage se fait dans .login-wrap, jamais sur l'element body lui-meme.
           Quand une session est encore ouverte dans le navigateur, config.php injecte
           du contenu en tete de page (ruban, fond de theme, fee) ; si body etait en
           flex, ce contenu devenait un element de la meme ligne et poussait la boite de
           connexion hors de l'ecran. D'un profil de navigateur a l'autre, la page ne
           s'affichait donc pas pareil.
           NB : ne jamais ecrire la balise body en toutes lettres dans cette page.
           famiInjectPageTheme() cherche son point d'injection par recherche de texte ;
           une balise citee dans un commentaire l'enverrait injecter ici, en plein CSS. */
        .login-wrap {
            display: flex;
            justify-content: center;
            min-height: 100vh;
            min-height: 100dvh; /* barre d'adresse mobile */
            padding: 18px;
        }
        .container {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            /* margin:auto plutot que align-items:center : centre verticalement sans
               couper le haut de la boite quand elle depasse la hauteur de l'ecran. */
            margin: auto;
        }
        @media (max-width: 480px) {
            .container { padding: 32px 22px; }
        }
        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 18px;
        }
        .logo img {
            max-width: 120px;
            height: auto;
        }
        h2 {
            color: #2d5a37;
            font-size: 1.4rem;
            margin-bottom: 24px;
            text-align: center;
        }
        label {
            color: #222;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-bottom: 18px;
            font-size: 1rem;
        }
        button {
            width: 100%;
            background: #2d5a37;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: #388e3c; }
        .error { color: #d93025; margin-bottom: 15px; font-size: 0.9rem; }
    </style>
</head>
<body>
<?php // Fond vectoriel net (sauf sur la page de connexion Famijob, qui garde son visuel de marque).
if (empty($isFamijobLogin) && function_exists('famiDefaultVectorBg')): ?>
<style id="fami-vec-bg"><?= famiDefaultVectorBg() ?></style>
<?php endif; ?>

<div class="login-wrap">
<div class="container">
    <div class="logo">
        <img src="logo.png" alt="Logo">
    </div>
    <h2><?php echo $title; ?></h2>
    
    <?php if($erreur): ?>
        <div class="error"><?php echo $erreur; ?></div>
    <?php endif; ?>

    <form method="POST" data-fee>
        <?php echo csrfField(); ?>
        <?php if ($postLoginRedirect !== ''): ?>
            <input type="hidden" name="redirect" value="<?php echo e($postLoginRedirect); ?>">
        <?php endif; ?>
        <input type="text" name="username" placeholder="Identifiant" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button type="submit">Se connecter</button>
    </form>

    <div style="margin-top:18px; text-align:center; display:flex; flex-direction:column; gap:8px;">
        <a href="account_help.php?mode=password" style="color:#2d5a37; text-decoration:none; font-weight:700;">Mot de passe oublié ?</a>
        <a href="account_help.php?mode=login" style="color:#2d5a37; text-decoration:none; font-weight:700;">Identifiant oublié ?</a>
    </div>
</div>
</div>

<?php
// La FÉE FAMIFLORA. Sur les pages connectées elle est injectée automatiquement par le
// buffer de config.php ; ici on n'est en général pas connecté, donc ce buffer ne tourne
// pas et on la pose à la main. Le formulaire porte data-fee → la fée sort au clic sur
// « Se connecter », le temps de vérifier le mot de passe.
// Le test sur la session est indispensable : si une session est restée ouverte dans ce
// navigateur, le buffer tourne quand même et la fée serait présente DEUX fois (mêmes
// id="feeBack"/"feeTxt" en double, que le JS ne sait plus départager).
if (empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/fee.php';
    echo feeOverlay();
}
?>
</body>
</html>