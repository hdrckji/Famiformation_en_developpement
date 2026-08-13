<?php
require_once 'config.php';
require_once __DIR__ . '/includes/notifications.php';
verifierConnexion($db);

// Contrôle d'accès. Les ÉTUDIANTS entrent désormais : ils y consultent la vue
// horaire et leurs disponibilités. Ce qu'ils voient est décidé plus bas, tuile
// par tuile — la porte s'ouvre, pas les droits.
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if (!in_array($role, ['admin', 'teamcoach', 'etudiant'], true)) {
    header('Location: ' . famijobSiteUrl('index.php'));
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// LE RÉCAP DE FAMICARD — première connexion, puis une fois par an.
//
// Même question que sur FamiFormation, même code : Famicard sait si la personne
// a relu sa fiche, on ne fait que rediriger. Rien n'est dupliqué ici.
//
// ⚠️ L'URL passe par famijobSiteUrl() : sur student.famiformation.com, famijob/
// EST la racine, et un « famicard/recap.php » écrit tel quel serait réécrit en
// famijob/famicard/recap.php — donc introuvable.
//
// ⚠️ Si la table de Famicard manque, la fonction répond FALSE : FamiJob reste
// accessible. Un service rendu, pas un péage.
foreach ([__DIR__ . '/../famicard/includes/validation.php',
          __DIR__ . '/../Famicard/includes/validation.php'] as $__fc) {
    if (is_file($__fc)) { require_once $__fc; break; }
}
// ⚠️ PAS DE REDIRECTION DEPUIS student.famiformation.com, et ce n'est pas un
// oubli. Le cookie de session est posé HOST-ONLY (config.php : 'domain' => '') :
// quelqu'un connecté sur le sous-domaine n'est PAS connecté sur www. L'envoyer
// vers www/famicard/recap.php le ferait atterrir sur un écran de connexion,
// sans comprendre pourquoi on lui redemande son mot de passe.
//
// Sur ce sous-domaine, le récap attendra donc sa visite sur www ou sur
// Famicard. C'est une limite connue de la coexistence des trois hôtes, pas
// quelque chose qui se règle ici.
$famijobSurSousDomaine = (strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0])
    === 'student.famiformation.com');

if (!$famijobSurSousDomaine && function_exists('famicardDoitValiderFiche')
    && !empty($_SESSION['user_id'])
    && famicardDoitValiderFiche($db, (int) $_SESSION['user_id'])) {
    header('Location: ' . famijobSiteUrl('famicard/recap.php')
        . '?retour=' . urlencode(famijobSiteUrl('famijob/index.php')));
    exit();
}

// Récupérer les infos de l'utilisateur
$user_id = $_SESSION['user_id'];
// Le « sinon » valait teamcoach quand seuls ces deux roles entraient ici. Un
// etudiant y est desormais chez lui, et se voyait annoncer « Profil : TeamCoach ».
$libellesRole = [
    'admin'     => 'role.admin',
    'teamcoach' => 'role.teamcoach',
    'etudiant'  => 'role.etudiant',
];
$roleLabel = famiT($libellesRole[$role] ?? 'role.teamcoach');
$unreadNotifications = famijobNotifUnreadCount($db, (int) $user_id);

if (!function_exists('fjT')) {
    function fjT($fr, $nl = null)
    {
        return famiLang() === 'nl' && $nl !== null ? $nl : $fr;
    }
}

function resolvePublicAssetUrl(array $absoluteCandidates, string $fallbackUrl): string {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;

    foreach ($absoluteCandidates as $candidate) {
        $realPath = realpath($candidate);
        if ($realPath === false || !is_file($realPath)) {
            continue;
        }

        $version = @filemtime($realPath) ?: time();

        // L'asset est dans le dossier FamiJob, à côté de cette page : on renvoie
        // une URL RELATIVE, valable sur les deux domaines. Une URL absolue
        // /famijob/... serait fausse sur student.famiformation.com, où famijob/
        // est déjà la racine : la règle rewrite du Caddyfile la préfixerait une
        // seconde fois (/famijob/famijob/...) et le fond ne s'afficherait pas.
        $famijobDir = str_replace('\\', '/', (string) realpath(__DIR__));
        $normalizedRealPath = str_replace('\\', '/', $realPath);
        if ($famijobDir !== '' && strpos($normalizedRealPath, $famijobDir . '/') === 0) {
            return substr($normalizedRealPath, strlen($famijobDir) + 1) . '?v=' . $version;
        }

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

$famijobBackgroundUrl = resolvePublicAssetUrl(
    [
        __DIR__ . '/font.png',
        dirname(__DIR__) . '/famijob/font.png',
        dirname(__DIR__) . '/Famijob/font.png',
        dirname(__DIR__) . '/font.png',
        __DIR__ . '/background-famijob.png',
        __DIR__ . '/background-famijob.jpg',
        __DIR__ . '/background-famijob.jpeg',
        __DIR__ . '/background-famijob.webp',
        dirname(__DIR__) . '/famijob/background-famijob.png',
        dirname(__DIR__) . '/famijob/background-famijob.jpg',
        dirname(__DIR__) . '/famijob/background-famijob.jpeg',
        dirname(__DIR__) . '/famijob/background-famijob.webp',
        dirname(__DIR__) . '/Famijob/background-famijob.png',
        dirname(__DIR__) . '/Famijob/background-famijob.jpg',
        dirname(__DIR__) . '/Famijob/background-famijob.jpeg',
        dirname(__DIR__) . '/Famijob/background-famijob.webp',
        dirname(__DIR__) . '/background-famijob.png',
        dirname(__DIR__) . '/background-famijob.jpg',
        dirname(__DIR__) . '/background-famijob.jpeg',
        dirname(__DIR__) . '/background-famijob.webp',
    ],
    '/font.png'
);
?>
<!DOCTYPE html>
<html lang="<?= e(famiLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(famiT('index.title')) ?> - <?= e(famiT('tile.matching.title')) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="famijob_.ico">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --fj-ink-900: #0f241d;
            --fj-ink-800: #17372d;
            --fj-ink-600: #2e5e4f;
            --fj-mint-100: #e8f4ef;
            --fj-mint-200: #d3ebdf;
            --fj-card: rgba(255, 255, 255, 0.97);
            --fj-card-border: rgba(18, 49, 39, 0.12);
            --fj-shadow-lg: 0 18px 44px rgba(8, 22, 17, 0.24);
            --fj-shadow-md: 0 10px 24px rgba(8, 22, 17, 0.15);
            --fj-text: #1b2c25;
            --fj-muted: #5c6f67;
            --fj-highlight: #f2b85a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background:
                linear-gradient(140deg, rgba(9, 31, 24, 0.78), rgba(16, 46, 37, 0.56)),
                url('<?php echo e($famijobBackgroundUrl); ?>') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            min-height: 100vh;
            color: var(--fj-text);
        }

        .top-nav {
            width: min(1240px, calc(100% - 32px));
            margin: 14px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 18px;
            background: rgba(8, 28, 22, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .top-nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-bell {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.94);
            text-decoration: none;
            font-size: 1.05rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
            transition: transform 0.2s ease;
        }

        .nav-bell:hover { transform: translateY(-1px) scale(1.05); }

        .nav-bell-dot {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            box-sizing: border-box;
            background: #c0392b;
            color: #fff;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 2px #fff;
        }

        .top-nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.94);
            color: var(--fj-ink-900);
            border-radius: 999px;
            padding: 8px 13px;
            font-size: 0.84rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .brand-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--fj-highlight);
            box-shadow: 0 0 0 5px rgba(242, 184, 90, 0.2);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.95);
            color: #8d2e2e;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.84rem;
            letter-spacing: 0.01em;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-back:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(9, 28, 22, 0.18);
        }

        .container {
            max-width: 1240px;
            width: calc(100% - 32px);
            margin: 16px auto 26px;
        }

        .hero {
            position: relative;
            background: linear-gradient(132deg, rgba(255, 255, 255, 0.98), rgba(243, 248, 245, 0.95));
            border-radius: 26px;
            padding: 30px 30px 24px;
            box-shadow: var(--fj-shadow-lg);
            margin-bottom: 18px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            overflow: hidden;
        }

        .hero::after {
            content: '';
            position: absolute;
            top: -120px;
            right: -70px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(46, 94, 79, 0.24), rgba(46, 94, 79, 0));
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--fj-mint-100);
            border: 1px solid var(--fj-mint-200);
            border-radius: 999px;
            color: var(--fj-ink-800);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            padding: 7px 11px;
            margin-bottom: 10px;
        }

        .hero h1 {
            color: var(--fj-ink-900);
            font-size: clamp(1.9rem, 3.8vw, 2.8rem);
            line-height: 1.06;
            margin: 0 0 8px;
            letter-spacing: -0.02em;
        }

        .hero p {
            color: var(--fj-muted);
            font-size: 0.98rem;
            margin: 0 0 12px;
            line-height: 1.5;
            max-width: 780px;
        }

        .hero-role {
            display: inline-block;
            background: rgba(23, 55, 45, 0.09);
            color: var(--fj-ink-900);
            border: 1px solid rgba(23, 55, 45, 0.18);
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .tiles-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(265px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .tile {
            position: relative;
            background: var(--fj-card);
            border-radius: 16px;
            padding: 18px 18px 16px;
            text-align: left;
            text-decoration: none;
            color: var(--fj-text);
            box-shadow: var(--fj-shadow-md);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-height: 184px;
            border: 1px solid var(--fj-card-border);
        }

        .tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 30px rgba(7, 24, 19, 0.2);
            border-color: rgba(46, 94, 79, 0.34);
        }

        .tile::after {
            content: '<?= e(famiT('tile.open')) ?>';
            margin-top: auto;
            padding-top: 12px;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: var(--fj-ink-600);
        }

        .tile-icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: linear-gradient(145deg, rgba(46, 94, 79, 0.16), rgba(46, 94, 79, 0.08));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 11px;
        }

        .tile-title {
            font-size: 1.01rem;
            font-weight: 800;
            color: var(--fj-ink-900);
            margin-bottom: 6px;
            line-height: 1.28;
        }

        .tile-desc {
            font-size: 0.88rem;
            color: var(--fj-muted);
            line-height: 1.42;
            margin: 0;
        }

        .fami-lang-switcher {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.26);
            border-radius: 999px;
            padding: 4px;
            backdrop-filter: blur(4px);
        }

        .fami-lang-option {
            display: inline-block;
            text-decoration: none;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.78rem;
            letter-spacing: 0.04em;
            padding: 5px 9px;
            border-radius: 999px;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .fami-lang-option.is-active {
            background: #ffffff;
            color: var(--fj-ink-900);
        }

        @media (max-width: 768px) {
            .top-nav {
                width: calc(100% - 20px);
                margin-top: 10px;
                padding: 8px 10px;
                gap: 8px;
                flex-wrap: wrap;
            }

            .top-nav-left {
                width: 100%;
                justify-content: space-between;
            }

            .brand-pill {
                font-size: 0.74rem;
                padding: 7px 10px;
            }

            .btn-back {
                font-size: 0.82rem;
                padding: 9px 12px;
            }

            .container {
                width: calc(100% - 20px);
                margin-top: 12px;
            }

            .hero {
                padding: 22px 16px 17px;
            }

            .hero p {
                font-size: 0.94rem;
            }

            .tiles-container {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .tile {
                min-height: 0;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="top-nav-left">
            <span class="brand-pill"><span class="brand-dot"></span> <?= e(famiT('index.workspace')) ?></span>
            <a href="logout.php" class="btn-back"><?= e(famiT('index.logout')) ?></a>
        </div>
        <div class="top-nav-right">
            <a href="notifications.php" class="nav-bell" title="Notifications">
                🔔<?php if ($unreadNotifications > 0): ?><span class="nav-bell-dot"><?= (int) $unreadNotifications ?></span><?php endif; ?>
            </a>
            <?php if ($role === 'admin'): ?>
            <a href="parametres.php" class="nav-bell" title="<?= e(fjT('Paramètres', 'Instellingen')) ?>">⚙️</a>
            <?php endif; ?>
            <?= famiRenderLanguageSwitcher() ?>
        </div>
    </div>

    <div class="container">
        <div class="hero">
            <span class="hero-kicker"><?= e(famiT('index.kicker')) ?></span>
            <h1><?= e(famiT('index.title')) ?></h1>
            <p><?= e(famiT('index.subtitle')) ?></p>
            <span class="hero-role"><?= e(famiT('index.role_prefix')) ?>: <?= e($roleLabel) ?></span>
        </div>

        <div class="tiles-container">
            <?php if ($role === 'admin'): ?>
            <a href="interim_horaires_demandes.php" class="tile">
                <div class="tile-icon">📝</div>
                <div class="tile-title"><?= e(famiT('tile.demands.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.demands.desc.admin')) ?></div>
            </a>

            <a href="interim_horaires.php" class="tile">
                <div class="tile-icon">🤝</div>
                <div class="tile-title"><?= e(famiT('tile.matching.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.matching.desc.admin')) ?></div>
            </a>

            <a href="validation_demandes_horaires.php" class="tile">
                <div class="tile-icon">✅</div>
                <div class="tile-title"><?= e(famiT('tile.validation.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.validation.desc')) ?></div>
            </a>

            <a href="vue_horaire.php" class="tile">
                <div class="tile-icon">📅</div>
                <div class="tile-title"><?= e(famiT('tile.schedule.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.schedule.desc')) ?></div>
            </a>

            <a href="envoi_horaires.php" class="tile">
                <div class="tile-icon">📨</div>
                <div class="tile-title"><?= e(famiT('tile.schedule_mail.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.schedule_mail.desc')) ?></div>
            </a>

            <a href="relance_etudiant.php" class="tile">
                <div class="tile-icon">✉️</div>
                <div class="tile-title"><?= e(famiT('tile.relaunch.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.relaunch.desc')) ?></div>
            </a>

            <a href="admin_disponibilites_etudiants.php" class="tile">
                <div class="tile-icon">🗓️</div>
                <div class="tile-title"><?= e(famiT('tile.availability.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.availability.desc')) ?></div>
            </a>

            <a href="interim_fixes.php" class="tile">
                <div class="tile-icon">🔧</div>
                <div class="tile-title"><?= e(famiT('tile.interim_fixes.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.interim_fixes.desc')) ?></div>
            </a>

            <a href="avis.php" class="tile">
                <div class="tile-icon">💬</div>
                <div class="tile-title"><?= e(fjT('Avis & suggestions', 'Feedback & suggesties')) ?></div>
                <div class="tile-desc"><?= e(fjT('Consulter tous les avis, questions et suggestions envoyés par les utilisateurs.', 'Alle feedback, vragen en suggesties van gebruikers bekijken.')) ?></div>
            </a>
            <?php elseif ($role === 'teamcoach'): ?>
            <?php // TEAMCOACH : demander des horaires, et voir le planning. Le
                  // matching ne lui appartient pas — c'est le metier de l'admin,
                  // et deux personnes qui affectent le meme creneau sans se voir,
                  // c'est une place promise deux fois. ?>
            <a href="interim_horaires_demandes.php" class="tile">
                <div class="tile-icon">📝</div>
                <div class="tile-title"><?= e(famiT('tile.demands.title')) ?></div>
                <div class="tile-desc"><?= e(famiT('tile.demands.desc.teamcoach')) ?></div>
            </a>

            <a href="vue_horaire.php" class="tile">
                <div class="tile-icon">📅</div>
                <div class="tile-title"><?= e(fjT('Vue horaire', 'Uurroosterweergave')) ?></div>
                <div class="tile-desc"><?= e(fjT('Le planning de la semaine, par secteur et par departement.', 'De weekplanning, per sector en per afdeling.')) ?></div>
            </a>

            <a href="avis.php" class="tile">
                <div class="tile-icon">💬</div>
                <div class="tile-title"><?= e(fjT('Avis & suggestions', 'Feedback & suggesties')) ?></div>
                <div class="tile-desc"><?= e(fjT('Une question, une idée, un souci ? Envoyez votre avis à l\'équipe.', 'Een vraag, een idee, een probleem? Stuur je feedback naar het team.')) ?></div>
            </a>
            <?php else: ?>
            <?php // ETUDIANT. Ses modules sont TOUS ici desormais : l'accueil de
                  // FamiFormation ne porte plus qu'une tuile « FamiJob » qui
                  // mene a cet ecran. Une porte, et derriere elle ce qui
                  // correspond a chacun.
                  //
                  // « Mes disponibilites » pointe encore vers une page du site :
                  // elle y est hebergee et n'a pas ete deplacee. Ce n'est plus
                  // un doublon — c'est le seul chemin. ?>
            <a href="<?= e(famijobSiteUrl('student_disponibilites.php')) ?>" class="tile">
                <div class="tile-icon">🗓️</div>
                <div class="tile-title"><?= e(fjT('Mes disponibilités', 'Mijn beschikbaarheden')) ?></div>
                <div class="tile-desc"><?= e(fjT('Indique tes jours de disponibilité sur les 30 prochains jours.', 'Geef je beschikbare dagen voor de komende 30 dagen aan.')) ?></div>
            </a>

            <?php // Vers mon_horaire.php sur le site : c'est LA page, la semaine
                  // en grille. Il en existait une seconde ici, en trois listes
                  // empilees ; elle ne fait plus que rediriger. Une seule vue,
                  // pour qu'elle cesse de changer selon la porte empruntee. ?>
            <a href="<?= e(famijobSiteUrl('mon_horaire.php?from=famijob')) ?>" class="tile">
                <div class="tile-icon">🕒</div>
                <div class="tile-title"><?= e(fjT('Mes horaires attribués', 'Mijn toegewezen uren')) ?></div>
                <div class="tile-desc"><?= e(fjT('Tes créneaux passés, du jour et à venir, en lecture seule.', 'Je vroegere, huidige en komende tijdsblokken, alleen lezen.')) ?></div>
            </a>

            <?php // PAS de « Vue horaire » ici : c'est le planning de TOUT LE
                  // MONDE, avec les noms et les agences de chacun. Un etudiant y
                  // lit ses propres creneaux dans « Mes horaires attribues »,
                  // qui ne montre que les siens. ?>

            <a href="avis.php" class="tile">
                <div class="tile-icon">💬</div>
                <div class="tile-title"><?= e(fjT('Avis & suggestions', 'Feedback & suggesties')) ?></div>
                <div class="tile-desc"><?= e(fjT('Une question, une idée, un souci ? Envoyez votre avis à l\'équipe.', 'Een vraag, een idee, een probleem? Stuur je feedback naar het team.')) ?></div>
            </a>
            <?php endif; ?>

            <?php // LA PORTE DU RETOUR, pour tous les roles.
                  //
                  // FamiFormation porte une tuile « FamiJob » ; l'inverse
                  // n'existait pas. Depuis que les etudiants se connectent
                  // directement ici, ils se retrouvaient sans aucun chemin vers
                  // le site — il fallait connaitre l'adresse. Une tuile symetrique
                  // ferme la boucle : les deux services se repondent.
                  //
                  // Hors du bloc par role : tout le monde vient du meme site. ?>
            <a href="<?= e(famijobSiteUrl('index.php')) ?>" class="tile">
                <div class="tile-icon">🎓</div>
                <div class="tile-title">FamiFormation</div>
                <div class="tile-desc"><?= e(fjT('Retour au site : formations, onboarding, quiz et modules du magasin.', 'Terug naar de site: opleidingen, onboarding, quizzen en winkelmodules.')) ?></div>
            </a>
        </div>
    </div>
<?php require_once __DIR__ . '/includes/topbar.php'; echo famijobScrollKeeperHtml(); ?>
</body>
</html>
