<?php
// ============================================================
// La Panne — page publique de collecte des adresses e-mail.
//
// Même rôle que emails/index.html, mais la saisie est enregistrée chez nous
// au lieu de partir dans un Google Form. Reprend la charte de emails/ :
// même palette, même typographie, bilingue FR / NL.
// ============================================================

require_once __DIR__ . '/_lapanne.php';

$etat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();
    $resultat = lapanneEnregistrer(
        $db,
        $_POST['nom'] ?? '',
        $_POST['prenom'] ?? '',
        $_POST['email'] ?? ''
    );
    $etat = $resultat['etat'];

    // Redirection après enregistrement : sans elle, un rafraîchissement de page
    // renverrait le formulaire une seconde fois.
    if ($resultat['ok']) {
        header('Location: index.php?ok=' . urlencode($etat));
        exit();
    }
}

if ($etat === '' && isset($_GET['ok'])) {
    $etat = (string) $_GET['ok'];
}

$messages = [
    'ajoute' => [
        'ton' => 'ok',
        'titre' => t('C\'est enregistré, merci !', 'Geregistreerd, bedankt!'),
        'texte' => t(
            'Votre adresse est bien notée. Vous recevrez vos accès à Famiformation par e-mail.',
            'Uw adres is genoteerd. U ontvangt uw toegang tot Famiformation per e-mail.'
        ),
    ],
    'deja_inscrit' => [
        'ton' => 'ok',
        'titre' => t('Vous étiez déjà inscrit', 'U was al ingeschreven'),
        'texte' => t(
            'Cette adresse figure déjà dans la liste — rien à refaire.',
            'Dit adres staat al in de lijst — u hoeft niets te doen.'
        ),
    ],
    'nom_manquant' => [
        'ton' => 'err',
        'titre' => t('Nom ou prénom manquant', 'Naam of voornaam ontbreekt'),
        'texte' => t('Merci de remplir les deux champs.', 'Gelieve beide velden in te vullen.'),
    ],
    'email_invalide' => [
        'ton' => 'err',
        'titre' => t('Adresse e-mail incorrecte', 'Ongeldig e-mailadres'),
        'texte' => t('Vérifiez la saisie, par exemple prenom@famiflora.be.', 'Controleer het adres, bijvoorbeeld voornaam@famiflora.be.'),
    ],
    'trop_long' => [
        'ton' => 'err',
        'titre' => t('Saisie trop longue', 'Invoer te lang'),
        'texte' => t('Merci de raccourcir.', 'Gelieve in te korten.'),
    ],
    'erreur_base' => [
        'ton' => 'err',
        'titre' => t('Enregistrement impossible', 'Registratie mislukt'),
        'texte' => t('Réessayez dans un instant.', 'Probeer het zo dadelijk opnieuw.'),
    ],
];
$message = $messages[$etat] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= e(currentLang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(t('Famiformation — La Panne', 'Famiformation — De Panne')) ?></title>
<link rel="shortcut icon" type="image/x-icon" href="../../favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* Palette reprise de emails/index.html, pour que les deux pages se ressemblent. */
:root{--green:#2e7d46;--deep:#1f5c34;--leaf:#7cb342;--mint:#eef6ec;--mint-line:#d7e8d2;--ink:#243027;--muted:#5c6f60;--red:#a8341f;}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif;}
body{background:#eef4ea;color:var(--ink);min-height:100vh;min-height:100dvh;}
.wrap{max-width:600px;margin:0 auto;padding:26px 16px 48px;}
.langtoggle{position:fixed;top:14px;right:14px;z-index:80;background:#fff;border-radius:22px;box-shadow:0 3px 12px rgba(0,0,0,.14);display:flex;overflow:hidden;}
.langtoggle a{padding:8px 15px;text-decoration:none;color:var(--muted);font-weight:700;font-size:13px;}
.langtoggle a.active{background:var(--deep);color:#fff;}
.card{background:#fff;border-radius:22px;box-shadow:0 14px 44px rgba(20,55,38,.16);padding:28px 24px;}
h1{color:var(--deep);font-weight:900;font-size:25px;line-height:1.15;}
.sub{margin-top:6px;font-size:13.5px;font-weight:700;color:var(--green);letter-spacing:.02em;}
p.lead{color:#3f4a41;font-size:15px;line-height:1.6;margin-top:14px;}
p.lead b{color:var(--deep);}
label{display:block;margin-top:16px;font-weight:700;font-size:13.5px;color:var(--deep);}
input{width:100%;margin-top:6px;padding:12px 13px;border:1px solid var(--mint-line);border-radius:12px;font-size:16px;background:var(--mint);}
input:focus{outline:2px solid var(--leaf);outline-offset:1px;background:#fff;}
button{width:100%;margin-top:22px;padding:14px;border:0;border-radius:12px;background:var(--green);color:#fff;font-size:16px;font-weight:800;cursor:pointer;}
button:hover{background:var(--deep);}
.flash{margin-bottom:18px;padding:14px 16px;border-radius:14px;font-size:14.5px;line-height:1.5;}
.flash b{display:block;font-size:15.5px;margin-bottom:3px;}
.flash.ok{background:var(--mint);border:1px solid var(--mint-line);color:var(--deep);}
.flash.err{background:#fdecea;border:1px solid #f6cdc7;color:var(--red);}
.note{margin-top:18px;font-size:12.5px;color:var(--muted);line-height:1.55;text-align:center;}
@media(max-width:480px){.card{padding:22px 18px;}h1{font-size:22px;}}
</style>
</head>
<body>

<div class="langtoggle">
    <a href="?lang=fr" class="<?= currentLang() === 'fr' ? 'active' : '' ?>">FR</a>
    <a href="?lang=nl" class="<?= currentLang() === 'nl' ? 'active' : '' ?>">NL</a>
</div>

<div class="wrap">
    <div class="card">
        <?php if ($message !== null): ?>
            <div class="flash <?= e($message['ton']) ?>">
                <b><?= e($message['titre']) ?></b>
                <?= e($message['texte']) ?>
            </div>
        <?php endif; ?>

        <h1><?= e(t('Bienvenue chez Famiflora La Panne', 'Welkom bij Famiflora De Panne')) ?></h1>
        <div class="sub"><?= e(t('Votre accès à Famiformation', 'Uw toegang tot Famiformation')) ?></div>

        <p class="lead">
            <?= e(t(
                'Famiformation est la plateforme de formation de Famiflora : vos modules, vos horaires et vos documents, au même endroit.',
                'Famiformation is het opleidingsplatform van Famiflora: uw modules, uw uurroosters en uw documenten, op één plaats.'
            )) ?>
        </p>
        <p class="lead">
            <?= e(t(
                'Laissez votre adresse e-mail ci-dessous : nous vous enverrons vos identifiants.',
                'Laat hieronder uw e-mailadres achter: wij sturen u uw inloggegevens.'
            )) ?>
        </p>

        <form method="POST" autocomplete="on">
            <?= csrfField() ?>

            <label for="nom"><?= e(t('Nom / Naam', 'Naam / Nom')) ?></label>
            <input id="nom" name="nom" type="text" maxlength="120" required
                   autocomplete="family-name" value="<?= e($_POST['nom'] ?? '') ?>">

            <label for="prenom"><?= e(t('Prénom / Voornaam', 'Voornaam / Prénom')) ?></label>
            <input id="prenom" name="prenom" type="text" maxlength="120" required
                   autocomplete="given-name" value="<?= e($_POST['prenom'] ?? '') ?>">

            <label for="email"><?= e(t('Adresse e-mail', 'E-mailadres')) ?></label>
            <input id="email" name="email" type="email" maxlength="190" required
                   autocomplete="email" placeholder="prenom@famiflora.be"
                   value="<?= e($_POST['email'] ?? '') ?>">

            <button type="submit"><?= e(t('Je m\'inscris', 'Ik schrijf mij in')) ?></button>
        </form>

        <div class="note">
            <?= e(t(
                'Votre adresse sert uniquement à créer votre accès Famiformation. Elle n\'est transmise à personne.',
                'Uw adres dient enkel om uw Famiformation-toegang aan te maken. Het wordt aan niemand doorgegeven.'
            )) ?>
        </div>
    </div>
</div>

</body>
</html>
