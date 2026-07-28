<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magasin - FamiFormation</title>
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
            color: #333;
        }

        .header {
            text-align: center;
            padding: 40px 20px;
        }

        .logo {
            max-width: 180px;
            margin-bottom: 20px;
            filter: drop-shadow(0 3px 6px rgba(0,0,0,0.15));
        }

        h1 {
            color: #2d5a37;
            background: rgba(255, 255, 255, 0.9);
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1.6rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: inline-block;
        }

        .tiles-container {
            display: grid;
            /* Grille adaptative : 3 colonnes sur PC, 1 sur Mobile */
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            width: 95%;
            max-width: 1100px;
            padding: 20px;
        }

        .tile {
            background: linear-gradient(145deg, #ffffff, #f9fff9);
            border-radius: 20px;
            padding: 35px 20px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
            transition: all 0.4s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid rgba(165, 195, 140, 0.2);
        }

        .tile:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.18);
            border-color: #7fa563;
        }

        .tile-icon {
            font-size: 50px;
            margin-bottom: 15px;
            display: block;
        }

        .tile-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d5a37;
        }

        .back-link {
            margin-top: 40px;
            margin-bottom: 40px;
            color: #2d5a37;
            text-decoration: none;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.9);
            padding: 12px 25px;
            border-radius: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .back-link:hover {
            background: #2d5a37;
            color: white;
            transform: scale(1.05);
        }

        @media (max-width: 600px) {
            .tiles-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="header">
        <img src="logo.png" alt="FamiFormation" class="logo">
        <br>
        <h1>Thème : Magasin</h1>
    </div>

    <div class="tiles-container">
        <a href="formation-caisse.php" class="tile">
            <span class="tile-icon">🛒</span>
            <span class="tile-title">Caisses</span>
        </a>
        <a href="ressources_humaines.php" class="tile">
            <span class="tile-icon">👥</span>
            <span class="tile-title">Formation ressources humaines</span>
        </a>

        <a href="deco.php" class="tile">
            <span class="tile-icon">🛋️</span>
            <span class="tile-title">Déco</span>
        </a>

        <a href="green.php" class="tile">
            <span class="tile-icon">🌿</span>
            <span class="tile-title">Green</span>
        </a>

        <a href="animalerie.php" class="tile">
            <span class="tile-icon">🐾</span>
            <span class="tile-title">Animalerie</span>
        </a>

        <a href="garden.php" class="tile">
            <span class="tile-icon">🏡</span>
            <span class="tile-title">Garden</span>
        </a>

        <a href="food.php" class="tile">
            <span class="tile-icon">🍫</span>
            <span class="tile-title">Food</span>
        </a>

        <a href="stock.php" class="tile" style="grid-column: span 1;">
            <span class="tile-icon">📦</span>
            <span class="tile-title">Stock</span>
        </a>
    </div>

    <a href="index.php" class="back-link">← Retour à l'accueil</a>

</body>
</html>