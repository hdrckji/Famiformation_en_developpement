<?php
require_once 'config.php';
verifierConnexion($db); 
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'etudiant';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Becosoft - FamiFormation</title>
  <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .top-nav {
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 20px 40px;
            box-sizing: border-box;
        }

        .btn-nav {
            background: rgba(255, 255, 255, 0.9);
            color: #2d5a37;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .header-logo { margin-top: 10px; text-align: center; color: white; text-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        .header-logo img { width: 200px; margin-bottom: 10px; }

        .tiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            max-width: 1100px;
            width: 95%;
            margin-top: 40px;
            padding-bottom: 50px;
        }

        .tile {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center; 
            min-height: 250px;
            justify-content: center;
        }

        .tile:hover { transform: translateY(-10px); box-shadow: 0 15px 35px rgba(0,0,0,0.3); }

        .tile-media {
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .tile-icon { font-size: 3.5rem; }
        .tile-title { font-size: 1.2rem; font-weight: 700; color: #2d5a37; margin-bottom: 8px; }
        .tile-desc { font-size: 0.9rem; color: #666; line-height: 1.4; max-width: 180px; margin: 0 auto; }
    </style>
</head>
<body>

    <div class="top-nav">
        <a href="index.php" class="btn-nav">⬅ Retour</a>
        <a href="logout.php" class="btn-nav" style="color:#d93025;" onclick="return confirm('<?= t('Êtes-vous sûr de vouloir vous déconnecter ?', 'Weet je zeker dat je je wilt afmelden?') ?>');"><?= t('Déconnexion', 'Afmelden') ?></a>
    </div>

    <div class="header-logo">
        <img src="beco.png" alt="Becosoft Logo">
        <h1>Modules Becosoft</h1>
    </div>

    <div class="tiles-grid">
        <a href="becosoft.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🔍</span></div>
            <div class="tile-title">Recherche Article</div>
            <div class="tile-desc">Consulter les prix, stocks et emplacements.</div>
        </a>

        <a href="beco_gazon.php" class="tile">
            <div class="tile-media"><span class="tile-icon">🌱</span></div>
            <div class="tile-title">Commande Gazon</div>
            <div class="tile-desc">Gérer les réservations et enlèvements.</div>
        </a>

        <a href="beco_bon.php" class="tile">
            <div class="tile-media"><span class="tile-icon">📜</span></div>
            <div class="tile-title">Bon de Commande</div>
            <div class="tile-desc">Créer et valider une commande client.</div>
        </a>

      <?php if ($role !== 'etudiant'): ?>
        <a href="beco_flash.php" class="tile">
            <div class="tile-media"><span class="tile-icon">⚡</span></div>
            <div class="tile-title">Vente Flash</div>
            <div class="tile-desc">Procédures pour les ventes flash et promotions.</div>
        </a>
        <?php endif; ?>
        </a>
    </div>
</body>
</html>