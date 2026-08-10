<?php
// ============================================================
// FAMICARD — CONNEXION.
//
// POURQUOI une page de connexion ici, alors que le site principal en a une ?
// À cause du sous-domaine. famicard.famiformation.com et www.famiformation.com
// sont deux hôtes différents pour le navigateur, et le cookie de session est
// posé host-only (Famiformation/config.php : 'domain' => ''). Une session
// ouverte sur www n'existe donc PAS ici. Sans page de connexion propre, le
// sous-domaine renverrait vers un login dont il ne verrait jamais le résultat :
// connecté sur www, toujours déconnecté sur famicard — en boucle. C'est déjà
// pour cette raison que FamiJob a son propre login sur student.
//
// « Liées », les deux connexions le restent au sens fort : même table
// `utilisateurs`, mêmes identifiants, et surtout les MÊMES clés de session que
// login.php du site (user_id, username, role, nom, prenom, photo_profil). Se
// connecter ici depuis www.famiformation.com/famicard/ ouvre donc une session
// valable pour tout le site — c'est une seule et même session, pas une seconde.
//
// ⚠️ La vérification du mot de passe est volontairement identique à celle de
// Famiformation/login.php (colonne `mot_de_passe`, password_verify,
// account_activation_pending). Si elle change là-bas, elle doit changer ici.
// ============================================================
require_once __DIR__ . '/config.php';

// Le ruban « connecté » (retour, notifications, déconnexion) est posé
// automatiquement sur toutes les pages par le buffer de config.php. Sur une page
// de connexion il n'a rien à y faire, et si une session traîne encore dans ce
// navigateur il s'afficherait quand même. On le déclare déjà fait — même
// correction que login.php et account_help.php du site.
$GLOBALS['__fami_topbar_done'] = true;

initCSRF();
ensureUserAccountAccessColumns($db);

// Déjà connecté : le formulaire n'a plus rien à demander.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$erreur = '';

// APRÈS CONNEXION : TOUJOURS l'accueil. Jamais la page qu'on avait demandée
// avant d'être arrêté ici.
//
// Ce « retour à la page demandée » existait et paraissait aimable ; en pratique
// il faisait atterrir sur sa fiche quiconque avait ouvert un lien de fiche
// avant de se connecter, alors que l'accueil est le point de départ voulu.
// Une commodité qui contredit le parcours n'est pas une commodité.
$apres = 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    $identifiant = (string) ($_POST['username'] ?? '');
    $mdp = (string) ($_POST['password'] ?? '');

    $st = $db->prepare("SELECT * FROM utilisateurs WHERE identifiant = ?");
    $st->execute([$identifiant]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    $motDePasseValide = $user
        && empty($user['account_activation_pending'])
        && !empty($user['mot_de_passe'])
        && password_verify($mdp, $user['mot_de_passe']);

    if ($motDePasseValide && (string) ($user['role'] ?? '') === 'agence_interim') {
        // Refus explicite plutôt que connexion suivie d'un mur. config.php
        // enferme ces comptes sur les pages de planning intérim : connectés ici,
        // ils seraient renvoyés vers interim_horaires.php, qui n'existe pas dans
        // Famicard — donc une 404 en boucle, sans explication.
        $erreur = "Ce compte agence n'a pas accès à Famicard.";
    } elseif ($motDePasseValide) {
        // Mêmes clés que login.php du site : la session est interchangeable.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['identifiant'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['prenom'] = $user['prenom'];
        $_SESSION['photo_profil'] = $user['photo_profil'] ?? null;

        if (function_exists('villeLangueDefaut')) {
            $_SESSION['lang'] = villeLangueDefaut($user['ville'] ?? '');
        }

        // Jeton renouvelé après connexion (comme sur le site).
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Nettoyage d'une session ouverte avant le retrait de ce mécanisme :
        // sans ça, la clé traînerait indéfiniment sans que rien ne la lise.
        unset($_SESSION['famicard_apres_login']);

        header('Location: ' . $apres);
        exit();
    } else {
        $erreur = "Identifiant ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion Famicard - Famiflora</title>
<?php // Assets du SITE PRINCIPAL : sur le sous-domaine, « /favicon.ico » serait
      // réécrit vers famicard/ et introuvable. D'où famicardSiteUrl(). ?>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: url('<?= e(famicardSiteUrl('background.jpg')) ?>') no-repeat center center fixed; background-size: cover; margin: 0; color: #333; }
    .enveloppe { display: flex; justify-content: center; min-height: 100vh; min-height: 100dvh; padding: 18px; }
    .boite { background: rgba(255,255,255,.96); border-radius: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.18); padding: 38px 34px; max-width: 430px; width: 100%; margin: auto; }
    .logo { display: flex; justify-content: center; margin-bottom: 16px; }
    .logo img { max-width: 110px; height: auto; }
    <?php // Marge basse reprise du sous-titre retiré : sans elle, le titre
          // collait au premier champ. ?>
    h1 { color: #2d5a37; font-size: 1.25rem; font-weight: 800; margin: 0 0 24px; text-align: center; }
    label { display: block; color: #222; font-weight: 600; font-size: .85rem; margin-bottom: 6px; }
    input[type="text"], input[type="password"] { width: 100%; padding: 11px 12px; border: 1px solid #ccd6cf; border-radius: 10px; margin-bottom: 16px; font-family: inherit; font-size: 1rem; }
    button { width: 100%; background: #2d5a37; color: #fff; border: 0; border-radius: 30px; padding: 13px; font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .2s; }
    button:hover { background: #388e3c; }
    .erreur { background: #fdecea; border-left: 4px solid #d93025; color: #a3271c; border-radius: 8px; padding: 10px 14px; margin-bottom: 18px; font-size: .88rem; }
    .liens { margin-top: 20px; display: flex; flex-direction: column; gap: 8px; text-align: center; }
    .liens a { color: #2d5a37; text-decoration: none; font-weight: 700; font-size: .86rem; }
</style>
</head>
<body>

<div class="enveloppe">
    <div class="boite">
        <div class="logo">
            <img src="<?= e(famicardSiteUrl('logo.png')) ?>" alt="Famiflora">
        </div>
        <h1>🪪 Ma Famicard</h1>

        <?php if ($erreur !== ''): ?>
            <div class="erreur"><?= e($erreur) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <label for="username">Identifiant</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <button type="submit">Se connecter</button>
        </form>

        <?php // Récupération d'identifiant / mot de passe : une seule page pour tout
              // le site, on ne la duplique pas ici. ?>
        <div class="liens">
            <a href="<?= e(famicardSiteUrl('account_help.php?mode=password')) ?>">Mot de passe oublié ?</a>
            <a href="<?= e(famicardSiteUrl('account_help.php?mode=login')) ?>">Identifiant oublié ?</a>
        </div>

    </div>
</div>

</body>
</html>
