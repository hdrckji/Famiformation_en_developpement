<?php
// ============================================================
// lapanne.php — ACCUEIL DU PROFIL « betalapanne ».
//
// Les personnes inscrites via le quiz de La Panne. Leur parcours n'a RIEN à voir
// avec la bêta de Mouscron : pas d'Onboarding, pas de module Magasin, et surtout
// pas le bandeau « VERSION BETA » de beta.php, qui annonce le 29/07 aux employés
// fixes et aux flexi — des échéances qui ne les concernent pas.
//
// D'où une page à part plutôt qu'une variante de beta.php : les deux parcours
// évolueront séparément, et rien de Mouscron ne doit fuir ici par accident.
//
// Pour l'instant, une seule porte : « Épreuves & mon espace jardin ».
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/widget.php';   // widget du ruban (météo/date/phrases qui défilent)

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'betalapanne') {
    header('Location: index.php');
    exit();
}
if (function_exists('ensureWidgetTables')) { try { ensureWidgetTables($db); } catch (Throwable $e) {} }

$userNom = trim(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? ''));
$userPhoto = $_SESSION['photo_profil'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= currentLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Accueil', 'Home') ?> - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: url('background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .top-nav { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 16px 0; box-sizing: border-box; }
        .top-nav > * { min-width: 0; }
        .user-info { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.9); padding: 8px 16px; border-radius: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-decoration: none; color: #333; font-weight: 600; font-size: .9rem; }
        .user-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 3px solid #2d5a37; }
        .user-avatar-placeholder { width: 48px; height: 48px; border-radius: 50%; background: #e8f5e9; border: 3px solid #2d5a37; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .btn-logout { background: rgba(255,255,255,0.9); color: #d93025; text-decoration: none; padding: 11px 22px; border-radius: 30px; font-weight: bold; font-size: .9rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .lang-sw { display: inline-flex; background: #fff; border: 2px solid #2d5a37; border-radius: 999px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .lang-sw a { padding: 7px 14px; font-weight: 700; font-size: .85rem; text-decoration: none; color: #2d5a37; }
        .lang-sw a.on { background: #2d5a37; color: #fff; }
        .nav-right { display: flex; align-items: center; gap: 10px; }

        .header { text-align: center; padding: 14px 20px 2px; }
        .logo-main { max-width: 220px; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2)); }

        /* 🌱 UNE SEULE TUILE, donc pas de grille à trois colonnes : elle serait
           perdue dans un tiers de l'écran. On la centre et on la borne en largeur
           pour qu'elle reste à une taille lisible sur un grand écran. */
        .tiles-container { display: flex; justify-content: center; width: 90%; max-width: 460px; margin-top: 10px; padding: 10px 0 40px; }
        .tile { flex: 1; background: rgba(255,255,255,0.96); border-radius: 20px; padding: 44px 30px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.1); transition: all .3s ease; display: flex; flex-direction: column; align-items: center; }
        .tile:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        .tile-icon { font-size: 3.4rem; margin-bottom: 14px; }
        .tile-title { font-size: 1.4rem; font-weight: 700; color: #2d5a37; margin-bottom: 8px; }
        .tile-desc { font-size: .95rem; color: #666; line-height: 1.4; }

        /* 🌱 Mise en avant de la tuile, reprise de l'accueil : classes dédiées et
           animations préfixées « jd ». « position: relative » est indispensable,
           sinon le badge irait se coller au coin de la page. */
        .tile.tile-jardin {
            position: relative;
            border: 2px solid #7bc47f;
            background: linear-gradient(180deg, rgba(255,255,255,0.97) 55%, rgba(232,245,233,0.97));
            animation: jdRespire 2.8s ease-in-out infinite;
        }
        @keyframes jdRespire {
            0%, 100% { box-shadow: 0 10px 25px rgba(0,0,0,0.10), 0 0 0 0 rgba(123,196,127,0.55); }
            50%      { box-shadow: 0 14px 30px rgba(0,0,0,0.14), 0 0 0 12px rgba(123,196,127,0); }
        }
        .tile.tile-jardin:hover { border-color: #2d5a37; animation-play-state: paused; }
        .tile.tile-jardin .tile-icon { animation: jdPousse 3.2s ease-in-out infinite; }
        @keyframes jdPousse {
            0%, 100% { transform: translateY(0) rotate(-5deg); }
            50%      { transform: translateY(-6px) rotate(5deg); }
        }
        .badge-jardin {
            position: absolute; top: -11px; right: -11px; z-index: 10;
            display: inline-flex; align-items: center; gap: 5px;
            background: linear-gradient(135deg, #2d5a37, #7bc47f);
            color: #fff; font-size: 0.74rem; font-weight: 800; letter-spacing: 0.04em;
            padding: 6px 13px; border-radius: 999px;
            box-shadow: 0 4px 12px rgba(45,90,55,0.45);
            animation: jdBadge 2.8s ease-in-out infinite;
        }
        @keyframes jdBadge { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        /* ♿ Réglage « animations réduites » respecté : la tuile reste mise en
           avant par sa bordure et son badge, sans rien qui bouge. */
        @media (prefers-reduced-motion: reduce) {
            .tile.tile-jardin, .tile.tile-jardin .tile-icon, .badge-jardin { animation: none; }
            .tile.tile-jardin { box-shadow: 0 10px 25px rgba(0,0,0,0.10), 0 0 0 4px rgba(123,196,127,0.45); }
        }

        @media (max-width: 560px) {
            .tile { padding: 30px 22px; }
            .logo-main { max-width: 170px; }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <a href="profil.php" class="user-info">
            <?php if ($userPhoto && is_file(__DIR__ . '/' . $userPhoto)): ?>
                <img src="<?= htmlspecialchars($userPhoto) ?>" alt="Photo" class="user-avatar">
            <?php else: ?>
                <span class="user-avatar-placeholder">👤</span>
            <?php endif; ?>
            <span><?= htmlspecialchars($userNom ?: ($_SESSION['username'] ?? '')) ?></span>
        </a>

        <?php
            // 🎠 Widget du ruban (météo/date/phrases) — au CENTRE, dans le même ruban.
            if (function_exists('renderWidget')) { try { echo renderWidget($db); } catch (Throwable $e) {} }
        ?>

        <div class="nav-right">
            <span class="lang-sw">
                <a href="?lang=fr" class="<?= currentLang() === 'fr' ? 'on' : '' ?>">FR</a>
                <a href="?lang=nl" class="<?= currentLang() === 'nl' ? 'on' : '' ?>">NL</a>
            </span>
            <a href="logout.php" class="btn-logout"><?= t('Déconnexion', 'Afmelden') ?></a>
        </div>
    </div>

    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo-main">
    </div>

    <div class="tiles-container">
        <?php // 🌱 quiz_acces.php fabrique le jeton de session du quiz : la personne
              // est déjà connectée ici, on ne lui redemande pas son mot de passe. ?>
        <a href="quiz_acces.php" class="tile tile-jardin">
            <span class="badge-jardin">🎁 <?= t('NOUVEAU', 'NIEUW') ?></span>
            <span class="tile-icon">🌱</span>
            <div class="tile-title"><?= t('Épreuves & mon espace jardin', 'Proeven & mijn tuin') ?></div>
            <div class="tile-desc"><?= t("Réponds aux épreuves, récolte tes graines et fais pousser ton jardin.", "Doe de quiz, oogst je zaadjes en laat je tuin groeien.") ?></div>
        </a>
    </div>
</body>
</html>
