<?php
require_once 'config.php';

// Une session peut encore etre ouverte dans ce profil de navigateur. config.php
// installe alors le buffer d'injection, qui pose le ruban « connecte » (retour,
// notifications, deconnexion...) sur TOUTES les pages. Sur la page de creation de
// mot de passe ce ruban n'a aucun sens, et il cassait la mise en page. On declare
// donc le ruban « deja fait » : le buffer ne l'ajoutera pas.
// (Meme correction que sur login.php.)
$GLOBALS['__fami_topbar_done'] = true;

ensureUserAccountAccessColumns($db);

// Colonne "date de statut" : créée si absente
try {
    $colStatutDate = $db->query("SHOW COLUMNS FROM utilisateurs LIKE 'statut_date'");
    if (!$colStatutDate->fetch()) {
        $db->exec("ALTER TABLE utilisateurs ADD COLUMN statut_date DATETIME NULL");
    }
} catch (Exception $e) { /* colonne déjà présente ou indisponible */ }

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$user = $token !== '' ? findUserByAccountAccessToken($db, $token) : null;
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $user = $token !== '' ? findUserByAccountAccessToken($db, $token) : null;

    if (!$user) {
        $message = "<div class='alert error'>❌ Ce lien n'est plus valide ou a expiré.</div>";
    } elseif ($password === '' || $passwordConfirm === '') {
        $message = "<div class='alert error'>❌ Merci de compléter les deux champs mot de passe.</div>";
    } elseif ($password !== $passwordConfirm) {
        $message = "<div class='alert error'>❌ Les deux mots de passe ne correspondent pas.</div>";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            "UPDATE utilisateurs
             SET mot_de_passe = ?,
                 account_activation_pending = 0,
                 account_access_token_hash = NULL,
                 account_access_expires_at = NULL,
                 account_access_type = NULL,
                 statut_date = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$hash, (int) $user['id']]);

        $success = true;
        $message = "<div class='alert success'>✅ Votre mot de passe a bien été défini. Vous pouvez maintenant vous connecter.</div>";
        $user = null;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Définir mon mot de passe - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Sans box-sizing, .container (width:100% + 68px de padding) et les champs
           debordaient de l'ecran sur telephone : la page arrivait « en desordre »
           quand on ouvrait le lien du mail depuis son mobile. */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: url('background.jpg') center/cover no-repeat #f6f6f6;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }
        /* Le centrage se fait dans .pw-wrap, jamais sur body lui-meme. Quand une
           session est encore ouverte dans le navigateur, config.php injecte du
           contenu en tete de page (ruban, fond de theme, fee) ; avec un body en
           flex, ce contenu devenait un element de la meme ligne et poussait la
           boite hors de l'ecran. La meme URL s'affichait donc correctement dans un
           profil de navigateur et en desordre dans un autre. */
        .pw-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            min-height: 100dvh; /* barre d'adresse mobile */
            padding: 18px;
        }
        .container {
            background: rgba(255,255,255,0.96);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            padding: 38px 34px;
            max-width: 560px;
            width: 100%;
        }
        @media (max-width: 480px) {
            .container { padding: 30px 20px; }
        }
        h1 {
            color: #2d5a37;
            font-size: 1.5rem;
            margin: 0 0 20px;
            text-align: center;
        }
        p {
            color: #4a5b50;
            line-height: 1.65;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 700;
        }
        .success {
            background: #dff3e3;
            color: #1d6a39;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            color: #223028;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid #ccd8cf;
            border-radius: 10px;
            margin-bottom: 16px;
            font: inherit;
        }
        button, .btn-link {
            width: 100%;
            background: #2d5a37;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            box-sizing: border-box;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="pw-wrap">
    <div class="container">
        <h1>Définir mon mot de passe</h1>
        <?php echo $message; ?>

        <?php if ($success): ?>
            <a href="login.php" class="btn-link">Aller à la connexion</a>
        <?php elseif ($user): ?>
            <p>Compte concerné : <strong><?php echo htmlspecialchars($user['identifiant']); ?></strong></p>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <label for="password">Nouveau mot de passe</label>
                <input type="password" id="password" name="password" required>

                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm" required>

                <button type="submit">Enregistrer mon mot de passe</button>
            </form>
        <?php else: ?>
            <div class="alert error">❌ Ce lien n'est plus valide ou a expiré.</div>
            <a href="account_help.php?mode=password" class="btn-link">Demander un nouveau lien</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>