<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Formation Ressources Humaines</title>
    <style>
                .tiles-container {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                    gap: 25px;
                    width: 90%;
                    max-width: 900px;
                    margin: 0 auto;
                }
                .tile {
                    background: rgba(255,255,255,0.97);
                    border-radius: 20px;
                    padding: 30px;
                    text-align: center;
                    text-decoration: none;
                    color: #333;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
                    transition: all 0.3s ease;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    position: relative;
                }
                .tile:hover {
                    transform: translateY(-8px);
                    box-shadow: 0 15px 35px rgba(0,0,0,0.13);
                }
                .tile-icon {
                    font-size: 3.2rem;
                    margin-bottom: 15px;
                }
                .tile-title {
                    font-size: 1.3rem;
                    font-weight: 700;
                    color: #2d5a37;
                    margin-bottom: 10px;
                }
                .tile-desc {
                    font-size: 0.97rem;
                    color: #666;
                    line-height: 1.4;
                }
        body {
            font-family: 'Open Sans', sans-serif;
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-top: 40px;
        }
        .logo {
            width: 120px;
            margin-bottom: 20px;
        }
        .pdf-frame {
            display: block;
            margin: 40px auto;
            border: 2px solid #2d5a37;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            width: 80vw;
            height: 80vh;
            background: white;
        }
        .back-link {
            display: block;
            width: fit-content;
            margin: 30px auto;
            color: #2d5a37;
            text-decoration: none;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.9);
            padding: 12px 25px;
            border-radius: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .back-link:hover { background: #2d5a37; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="FamiFormation" class="logo">
        <h1>Formation Ressources Humaines</h1>
    </div>
    <div class="tiles-container" style="margin-top:40px;">
        <a href="judo.php" class="tile">
            <span class="tile-icon">🥋</span>
            <span class="tile-title">Judo verbal</span>
            <span class="tile-desc">Techniques pour gérer les situations difficiles.</span>
        </a>
        <a href="stress.php" class="tile">
            <span class="tile-icon">🧘‍♂️</span>
            <span class="tile-title">Gestion du stress</span>
            <span class="tile-desc">Outils et conseils pour mieux gérer le stress au travail.</span>
        </a>
    </div>
    <a href="magasin.php" class="back-link">← Retour au magasin</a>
</body>
</html>