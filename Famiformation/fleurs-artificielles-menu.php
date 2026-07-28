<?php
require_once 'config.php';
verifierConnexion($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleurs Artificielles - Raccourcis</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .header {
            text-align: center;
            padding: 30px 20px 10px;
        }
        .logo {
            max-width: 140px;
            margin-bottom: 14px;
        }
        h1 {
            color: #2d5a37;
            background: rgba(255, 255, 255, 0.92);
            padding: 12px 28px;
            border-radius: 30px;
            font-size: 1.5rem;
            display: inline-block;
            margin: 0;
        }
        .tiles-container {
            width: min(940px, 92vw);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 22px;
            margin: 24px 0 18px;
        }
        .tile {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px 24px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            text-align: center;
        }
        .tile:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 35px rgba(0, 0, 0, 0.18);
        }
        .tile-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
        }
        .tile-title {
            display: block;
            color: #2d5a37;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .tile-desc {
            display: block;
            color: #5b5b5b;
            line-height: 1.4;
            font-size: 0.95rem;
        }
        .btn-back {
            display: inline-block;
            margin: 8px 0 30px;
            background: #2d5a37;
            color: #fff;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.25s ease;
        }
        .btn-back:hover {
            background: #214429;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo"><br>
        <h1>Fleurs Artificielles</h1>
    </div>

    <div class="tiles-container">
        <a href="fleurs-artificielles.php" class="tile">
            <span class="tile-icon">🎬</span>
            <span class="tile-title">Version video</span>
            <span class="tile-desc">Regarder la video de formation puis acceder au quiz.</span>
        </a>

        <a href="fleurs-artificielles-pdf.php" class="tile">
            <span class="tile-icon">📄</span>
            <span class="tile-title">Version guide PDF</span>
            <span class="tile-desc">Consulter le guide fleursarti.pdf puis lancer le quiz.</span>
        </a>
    </div>

    <a href="deco.php" class="btn-back">&#8592; Retour Deco</a>
</body>
</html>