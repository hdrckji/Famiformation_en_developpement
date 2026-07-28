<?php
require_once 'config.php';

// Une session peut encore etre ouverte dans ce profil de navigateur : config.php
// installerait alors le ruban « connecte » sur cette page, qui n'a rien a y faire
// et cassait la mise en page. On le declare deja pose. (Meme correction que login.php.)
$GLOBALS['__fami_topbar_done'] = true;

ensureUserAccountAccessColumns($db);

$mode = trim((string) ($_GET['mode'] ?? $_POST['mode'] ?? 'password'));
if (!in_array($mode, ['password', 'login'], true)) {
    $mode = 'password';
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    if ($mode === 'password') {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($identifier !== '' && $email !== '') {
            $stmt = $db->prepare('SELECT id FROM utilisateurs WHERE identifiant = ? AND email = ? LIMIT 1');
            $stmt->execute([$identifier, $email]);
            $userId = $stmt->fetchColumn();

            if ($userId) {
                sendPasswordResetEmail($db, (int) $userId);
            }
        }

        $message = "<div class='alert success'>✅ Si les informations correspondent à un compte, un email de réinitialisation a été envoyé.</div>";
    }

    if ($mode === 'login') {
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email !== '') {
            $stmt = $db->prepare('SELECT identifiant, prenom, nom FROM utilisateurs WHERE email = ? ORDER BY identifiant ASC');
            $stmt->execute([$email]);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($accounts)) {
                sendUsernameReminderEmail($email, $accounts);
            }
        }

        $message = "<div class='alert success'>✅ Si cette adresse email est liée à un compte, un rappel d’identifiant a été envoyé.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aide à la connexion - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Sans box-sizing, .container (width:100% + 68px de padding) debordait de
           l'ecran sur telephone : la page arrivait « en desordre » sur mobile. */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: url('background.jpg') center/cover no-repeat #f6f6f6;
            font-family: 'Open Sans', sans-serif;
            margin: 0;
        }
        /* Le centrage se fait dans .aide-wrap, jamais sur body lui-meme : si une
           session est encore ouverte, config.php injecte du contenu en tete de page
           (ruban, fond de theme, fee) et un body en flex le placerait sur la meme
           ligne, poussant la boite hors de l'ecran. (Meme correction que login.php.) */
        .aide-wrap {
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
        .tabs {
            display: flex;
            gap: 10px;
            margin: 24px 0 20px;
            flex-wrap: wrap;
        }
        .tab-link {
            text-decoration: none;
            border-radius: 999px;
            padding: 10px 16px;
            border: 1px solid #d8e3db;
            color: #2d5a37;
            font-weight: 700;
            background: #fff;
        }
        .tab-link.active {
            background: #2d5a37;
            color: #fff;
            border-color: #2d5a37;
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
        button {
            width: 100%;
            background: #2d5a37;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
        }
        .footer-links {
            margin-top: 18px;
            text-align: center;
        }
        .footer-links a {
            color: #2d5a37;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="aide-wrap">
    <div class="container">
        <h1>Aide à la connexion</h1>
        <p>Choisis le type d’aide souhaité. Si les informations correspondent à un compte, un email sera envoyé à l’adresse enregistrée.</p>

        <div class="tabs">
            <a href="account_help.php?mode=password" class="tab-link <?php echo $mode === 'password' ? 'active' : ''; ?>">Mot de passe oublié</a>
            <a href="account_help.php?mode=login" class="tab-link <?php echo $mode === 'login' ? 'active' : ''; ?>">Identifiant oublié</a>
        </div>

        <?php echo $message; ?>

        <?php if ($mode === 'password'): ?>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="mode" value="password">
                <label for="identifier">Identifiant</label>
                <input type="text" id="identifier" name="identifier" required>

                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" required>

                <button type="submit">Envoyer le lien de réinitialisation</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="mode" value="login">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" required>

                <button type="submit">Recevoir mon identifiant</button>
            </form>
        <?php endif; ?>

        <div class="footer-links">
            <a href="login.php">⬅ Retour à la connexion</a>
        </div>
    </div>
</div>
</body>
</html>