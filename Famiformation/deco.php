<?php
require_once 'config.php';
verifierConnexion($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Déco - FamiFormation</title>
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
        .header {
            text-align: center;
            padding: 40px 20px 10px;
        }
        .logo {
            max-width: 180px;
            margin-bottom: 20px;
        }
        h1 {
            color: #2d5a37;
            background: rgba(255, 255, 255, 0.9);
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1.6rem;
            display: inline-block;
        }
        .tiles-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            width: 90%;
            max-width: 1200px;
            margin-top: 0;
            padding-bottom: 0;
        }
        .tile {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .tile:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        .tile-icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
        }
        .tile-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2d5a37;
            margin-bottom: 10px;
        }
        .tile-desc {
            font-size: 0.95rem;
            color: #666;
            line-height: 1.4;
        }
        .btn-back {
            display: inline-block;
            margin: 24px 0 30px;
            background: #2d5a37;
            color: #fff;
            text-decoration: none;
            padding: 13px 32px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(45,90,55,0.13);
        }
        .btn-back:hover {
            background: #214429;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo"><br>
        <h1>Thème : Déco</h1>
    </div>

    <div class="tiles-container">
        <a href="barbecue_menu.php" class="tile">
            <span class="tile-icon">🔥</span>
            <span class="tile-title">Barbecue</span>
            <span class="tile-desc">Cook'in Garden, Weber, Barbecook et Napoleon</span>
        </a>
        <a href="piscine_spa.php" class="tile">
            <span class="tile-icon">🏊‍♂️</span>
            <span class="tile-title">Piscine &amp; Spa</span>
            <span class="tile-desc">Guide et conseils pour la piscine et les spas</span>
        </a>
        <a href="changement_saison.php" class="tile">
            <span class="tile-icon">🍂</span>
            <span class="tile-title">Changement de saison</span>
            <span class="tile-desc">Vidéo et quiz sur le changement de saison</span>
        </a>
        <a href="mix.php" class="tile">
            <span class="tile-icon">🧑‍🤝‍🧑</span>
            <span class="tile-title">Présentation équipe mix</span>
            <span class="tile-desc">Découvrez l'équipe mix et son rôle</span>
        </a>
        <a href="marketing.php" class="tile">
            <span class="tile-icon">📣</span>
            <span class="tile-title">Marketing</span>
            <span class="tile-desc">Support de formation marketing</span>
        </a>
        <a href="parrain.php" class="tile">
            <span class="tile-icon">🤝</span>
            <span class="tile-title">Parrain/Marraine</span>
            <span class="tile-desc">Support de formation pour le rôle de parrain ou marraine</span>
        </a>
        <a href="fleurs-artificielles-menu.php" class="tile">
            <span class="tile-icon">💐</span>
            <span class="tile-title">Fleurs artificielles</span>
            <span class="tile-desc">Choisissez la video ou le guide PDF puis lancez le quiz</span>
        </a>
    </div>

    <a href="index.php" class="btn-back">&#8592; Retour accueil</a>
</body>
</html>