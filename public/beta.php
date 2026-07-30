<?php
// ============================================================
// beta.php — ACCUEIL de la VERSION BETA (profil « beta »).
// Vue volontairement minimale : 2 modules seulement (Onboarding + Magasin),
// nouvelle mise en page. Réservé au rôle « beta » : les autres repartent à
// l'accueil normal. Le parcours complet sera défini après le 29/07.
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/widget.php';   // widget du ruban (météo/date/phrases qui défilent)

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'beta') {
    header('Location: index.php');
    exit();
}
if (function_exists('ensureWidgetTables')) { try { ensureWidgetTables($db); } catch (Throwable $e) {} }

// Id d'un module beta (conteneur racine) par son nom — pour ouvrir le VRAI
// contenu uploadé (module.php), pas une vieille page codée en dur.
function betaModId(PDO $db, $nom) {
    try {
        $st = $db->prepare("SELECT id FROM modules WHERE nom = ? AND roles = 'beta' AND parent_id IS NULL LIMIT 1");
        $st->execute([$nom]);
        $v = $st->fetchColumn();
        return $v !== false ? (int) $v : 0;
    } catch (Throwable $e) { return 0; }
}
$idOnboarding = betaModId($db, 'Onboarding');

$userNom = trim(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? ''));
$userPhoto = $_SESSION['photo_profil'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil (BETA) - FamiFormation</title>
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

        /* 🧪 Bandeau BETA */
        .beta-banner { width: 92%; max-width: 900px; margin: 10px auto 0; background: linear-gradient(135deg,#fff4d6,#ffe4a3);
            border: 2px solid #E9A93C; color: #7a4a11; border-radius: 16px; padding: 14px 20px; text-align: center;
            box-shadow: 0 6px 18px rgba(233,169,60,.25); font-size: .98rem; line-height: 1.5; }
        .beta-banner b { color: #7a4a11; }
        .beta-tag { display: inline-block; background: #E9A93C; color: #fff; font-weight: 800; border-radius: 999px; padding: 2px 12px; font-size: .8rem; letter-spacing: .04em; margin-bottom: 6px; }

        .header { text-align: center; padding: 6px 20px 2px; }
        .logo-main { max-width: 220px; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2)); }

        .tiles-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; width: 90%; max-width: 760px; margin-top: 6px; padding: 10px 0 40px; }
        .tile { background: rgba(255,255,255,0.96); border-radius: 20px; padding: 44px 30px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.1); transition: all .3s ease; display: flex; flex-direction: column; align-items: center; }
        .tile:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        .tile-icon { font-size: 3.4rem; margin-bottom: 14px; }
        .tile-title { font-size: 1.4rem; font-weight: 700; color: #2d5a37; margin-bottom: 8px; }
        .tile-desc { font-size: .95rem; color: #666; line-height: 1.4; }

        /* 🌱 MISE EN AVANT DE LA TUILE « Quiz & mon espace jardin ».
           Classes DÉDIÉES et animations préfixées « jd » : les deux autres tuiles
           de cette page ne changent pas d'un pixel. Le « position: relative » est
           ici INDISPENSABLE — contrairement à l'accueil normal, le .tile de cette
           page ne l'a pas, et le badge irait se coller au coin de la page. */
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
        @media (prefers-reduced-motion: reduce) {
            .tile.tile-jardin, .tile.tile-jardin .tile-icon, .badge-jardin { animation: none; }
            .tile.tile-jardin { box-shadow: 0 10px 25px rgba(0,0,0,0.10), 0 0 0 4px rgba(123,196,127,0.45); }
        }

        @media (max-width: 560px) {
            .tiles-container { grid-template-columns: 1fr; gap: 16px; }
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

    <div class="beta-banner">
        <div class="beta-tag">VERSION BETA</div><br>
        <?= t(
            "Tu utilises une <b>version bêta</b> de Famiformation : tu peux déjà découvrir les <b>formations</b> et faire les <b>quiz</b>. Le <b>29/07</b>, les <b>employés fixes</b> recevront la <b>vraie version</b> avec leur parcours complet. Les <b>flexi</b> et les <b>étudiants</b> gardent la version bêta jusque <b>mi-août</b>.",
            "Je gebruikt een <b>bètaversie</b> van Famiformation: je kan de <b>opleidingen</b> al ontdekken en de <b>quiz</b> doen. Op <b>29/07</b> krijgen de <b>vaste medewerkers</b> de <b>echte versie</b> met hun volledig traject. De <b>flexi's</b> en de <b>studenten</b> houden de bètaversie tot <b>midden augustus</b>."
        ) ?>
    </div>

    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo-main">
    </div>

    <div class="tiles-container">
        <a href="<?= $idOnboarding ? 'module.php?id=' . $idOnboarding : 'gestion-beta.php' ?>" class="tile">
            <span class="tile-icon">🚀</span>
            <div class="tile-title"><?= t('Onboarding', 'Onboarding') ?></div>
            <div class="tile-desc"><?= t("Bienvenue chez Famiflora ! Découvre notre univers.", "Welkom bij Famiflora! Ontdek onze wereld.") ?></div>
        </a>

        <a href="beta-magasin.php" class="tile">
            <span class="tile-icon">🛒</span>
            <div class="tile-title"><?= t('Magasin', 'Winkel') ?></div>
            <div class="tile-desc"><?= t("Les rayons du magasin — commence par la Caisse.", "De afdelingen van de winkel — begin bij de kassa.") ?></div>
        </a>

        <?php // 🌱 Le jeu est ouvert à TOUT LE MONDE, profil beta compris : les
              // beta ne passent jamais par index.php, la tuile doit donc être ici
              // aussi. quiz_acces.php fabrique le jeton : aucun mot de passe à
              // resaisir, on est déjà connecté. ?>
        <a href="quiz_acces.php" class="tile tile-jardin">
            <span class="badge-jardin">🎁 <?= t('NOUVEAU', 'NIEUW') ?></span>
            <span class="tile-icon">🌱</span>
            <div class="tile-title"><?= t('Quiz & mon espace jardin', 'Quiz & mijn tuin') ?></div>
            <div class="tile-desc"><?= t("Réponds au quiz, récolte tes graines et fais pousser ton jardin.", "Doe de quiz, oogst je zaadjes en laat je tuin groeien.") ?></div>
        </a>
    </div>
</body>
</html>
