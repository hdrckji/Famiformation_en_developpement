<?php
// ============================================================
// FAMICARD — LA PHOTO DE PROFIL.
//
// POURQUOI ELLE EST ICI et plus dans profil.php du site : Famicard est le
// centre de données du collaborateur. Toute information qui le concerne se
// consulte et se modifie depuis sa carte — sortir vers FamiFormation pour
// déposer sa propre photo contredisait exactement ça, et c'est ce qui se
// passait jusqu'ici (« Modifier ma photo » quittait Famicard).
//
// RÈGLE DE NAVIGATION : la SEULE porte de Famicard vers FamiFormation est la
// tuile « FamiFormation » de l'accueil. Aucune autre page ne doit y renvoyer.
//
// Pas de validation, pas de quota : le collaborateur change sa photo autant de
// fois qu'il veut, sans demander l'autorisation à personne. C'est sa photo.
//
// ⚠️ LE STOCKAGE EST CELUI DU SITE, volontairement : même dossier sur le volume
// (`divers/profils/`), même colonne `utilisateurs.photo_profil`, même
// compression. Un second emplacement, et la photo affichée par FamiFormation ne
// serait plus celle déposée ici. On déplace l'ÉCRAN, pas les données.
// ============================================================
require_once __DIR__ . '/config.php';

$moi = famicardExigeConnexion($db);
$userId = (int) $moi['id'];

// Même base que profil.php. Le repli passe par famicardRacineSite() : « __DIR__ »
// désignerait Famicard, donc un dossier uploads/ qui n'est pas celui du site.
$storeBase = defined('FAMI_STORAGE_BASE') ? rtrim(FAMI_STORAGE_BASE, '/') : (famicardRacineSite() . '/uploads');
$uploadDir = $storeBase . '/divers/profils/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

/** Chemin absolu d'une photo déjà enregistrée (clé volume OU ancien public/uploads). */
function famicardCheminPhoto($valeur, $storeBase)
{
    $valeur = (string) $valeur;
    if ($valeur === '') {
        return '';
    }
    return (strpos($valeur, 'uploads/') === 0)
        ? famicardRacineSite() . '/' . $valeur
        : $storeBase . '/' . $valeur;
}

// Message qui survit à la redirection (motif Post/Redirect/Get) : sans ça, un
// rafraîchissement après envoi renverrait le fichier une seconde fois.
$message = '';
if (!empty($_SESSION['famicard_photo_flash'])) {
    $message = (string) $_SESSION['famicard_photo_flash'];
    unset($_SESSION['famicard_photo_flash']);
}

function famicardPhotoRetour($flash)
{
    $_SESSION['famicard_photo_flash'] = $flash;
    header('Location: photo.php');
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// DÉPÔT
// Les contrôles sont ceux de profil.php, sans allègement : taille, type
// déclaré, ET getimagesize() — le type annoncé par le navigateur se falsifie,
// pas le contenu d'une image.
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo_profil'])) {
    requireValidCSRF();

    $file = $_FILES['photo_profil'];
    $typesAutorises = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $tailleMax = 5 * 1024 * 1024; // 5 Mo

    if ($file['error'] !== UPLOAD_ERR_OK) {
        famicardPhotoRetour("❌ L'envoi n'a pas abouti. Réessaie.");
    } elseif ($file['size'] > $tailleMax) {
        famicardPhotoRetour('❌ Image trop grande (5 Mo maximum).');
    } elseif (!in_array($file['type'], $typesAutorises, true)) {
        famicardPhotoRetour('❌ Format non accepté : JPEG, PNG, GIF ou WebP.');
    } elseif (!@getimagesize($file['tmp_name'])) {
        famicardPhotoRetour("❌ Ce fichier n'est pas une image.");
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nomFichier = 'user_' . $userId . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $nomFichier;

        // L'ancienne photo part AVANT d'écrire la nouvelle : sinon chaque
        // changement laisse un fichier orphelin sur le volume, que plus rien
        // ne référence et que personne ne viendra supprimer.
        $st = $db->prepare("SELECT photo_profil FROM utilisateurs WHERE id = ?");
        $st->execute([$userId]);
        $ancienne = famicardCheminPhoto((string) $st->fetchColumn(), $storeBase);
        if ($ancienne !== '' && is_file($ancienne)) {
            @unlink($ancienne);
        }

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Même compression que le site (600 px de large) : une photo de
            // téléphone pèse plusieurs Mo pour un affichage de 92 px.
            $compress = famicardRacineSite() . '/includes/compress.php';
            if (is_file($compress)) {
                require_once $compress;
                if (function_exists('famiCompressImageFile')) {
                    famiCompressImageFile($destination, 600);
                }
            }

            $cheminRelatif = 'divers/profils/' . $nomFichier;
            $db->prepare("UPDATE utilisateurs SET photo_profil = ? WHERE id = ?")
               ->execute([$cheminRelatif, $userId]);
            // Le ruban du site lit la photo dans la session : sans cette ligne,
            // l'ancienne image resterait affichée jusqu'à la reconnexion.
            $_SESSION['photo_profil'] = $cheminRelatif;

            famicardPhotoRetour('✅ Photo mise à jour.');
        }

        famicardPhotoRetour("❌ La photo n'a pas pu être enregistrée.");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// RETRAIT
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_photo'])) {
    requireValidCSRF();

    $st = $db->prepare("SELECT photo_profil FROM utilisateurs WHERE id = ?");
    $st->execute([$userId]);
    $ancienne = famicardCheminPhoto((string) $st->fetchColumn(), $storeBase);
    if ($ancienne !== '' && is_file($ancienne)) {
        @unlink($ancienne);
    }

    $db->prepare("UPDATE utilisateurs SET photo_profil = NULL WHERE id = ?")->execute([$userId]);
    $_SESSION['photo_profil'] = null;

    famicardPhotoRetour('✅ Photo supprimée.');
}

// Relecture après coup : on affiche ce qui est réellement en base, pas ce qu'on
// croit y avoir mis.
$st = $db->prepare("SELECT photo_profil FROM utilisateurs WHERE id = ?");
$st->execute([$userId]);
$photo = (string) $st->fetchColumn();

$photoUrl = '';
if ($photo !== '') {
    $photoUrl = function_exists('moduleFileUrl') ? moduleFileUrl($photo) : $photo;
    if ($photoUrl !== '' && !preg_match('#^(https?:)?//#i', $photoUrl)) {
        $photoUrl = famicardSiteUrl($photoUrl);
    }
    // Anti-cache : sans ça, le navigateur réaffiche l'ancienne image, et le
    // collaborateur croit que son envoi n'a pas marché.
    $photoUrl .= (strpos($photoUrl, '?') === false ? '?' : '&') . 'v=' . time();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ma photo - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: url('<?= e(famicardSiteUrl('background.jpg')) ?>') no-repeat center center fixed; background-size: cover; margin: 0; padding: 0 0 40px; color: #333; }
    .top-nav { display: flex; gap: 12px; flex-wrap: wrap; padding: 12px 16px; }
    .pill { background: rgba(255,255,255,.92); padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 10px rgba(0,0,0,.1); text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .9rem; }
    .wrap { max-width: 560px; margin: 0 auto; padding: 0 16px; }
    .boite { background: rgba(255,255,255,.96); border-radius: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.15); padding: 30px 28px; text-align: center; }
    h1 { color: #2d5a37; font-size: 1.3rem; font-weight: 800; margin: 0 0 6px; }
    .intro { color: #666; font-size: .88rem; line-height: 1.55; margin: 0 0 22px; }
    .apercu { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 5px solid #2d5a37; margin: 0 auto 22px; display: block; background: #e8f5e9; }
    .apercu-vide { display: flex; align-items: center; justify-content: center; font-size: 3.4rem; color: #2d5a37; border-style: dashed; }
    input[type="file"] { width: 100%; padding: 12px; border: 1px dashed #b9cfc0; border-radius: 12px; background: #f7faf8; font-family: inherit; font-size: .9rem; margin-bottom: 16px; }
    .bouton { border: 0; border-radius: 30px; padding: 13px 24px; font-family: inherit; font-weight: 700; font-size: .95rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; width: 100%; }
    .bouton-plein:hover { background: #388e3c; }
    .bouton-danger { background: #fff; color: #a83232; border: 1px solid #e8c9c9; margin-top: 12px; width: 100%; }
    .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-size: .9rem; font-weight: 600; }
    .flash.ok { background: #e8f5e9; color: #1e5128; }
    .flash.ko { background: #fdecea; color: #a3271c; }
    .note { color: #777; font-size: .8rem; line-height: 1.55; margin-top: 18px; }
</style>
</head>
<body>

<div class="top-nav">
    <a class="pill" href="fiche.php">&larr; Ma fiche</a>
    <a class="pill" href="index.php">Accueil</a>
</div>

<div class="wrap">
    <div class="boite">
        <h1>📷 Ma photo</h1>
        <p class="intro">Elle apparaît sur ta carte. Tu peux la changer autant de fois que tu veux.</p>

        <?php if ($message !== ''): ?>
            <div class="flash <?= strpos($message, '✅') === 0 ? 'ok' : 'ko' ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($photoUrl !== ''): ?>
            <img class="apercu" src="<?= e($photoUrl) ?>" alt="">
        <?php else: ?>
            <div class="apercu apercu-vide">👤</div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="file" name="photo_profil" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <button type="submit" class="bouton bouton-plein">Enregistrer cette photo</button>
        </form>

        <?php if ($photo !== ''): ?>
        <form method="POST" onsubmit="return confirm('Supprimer ta photo ?');">
            <?= csrfField() ?>
            <input type="hidden" name="supprimer_photo" value="1">
            <button type="submit" class="bouton bouton-danger">🗑️ Supprimer ma photo</button>
        </form>
        <?php endif; ?>

        <p class="note">JPEG, PNG, GIF ou WebP, 5 Mo maximum. L'image est réduite automatiquement à 600 px de large.</p>
    </div>
</div>

</body>
</html>
