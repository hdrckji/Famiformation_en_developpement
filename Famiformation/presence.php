<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la présence - FamiFormation</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 20px;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        .btn-retour { text-decoration:none; color:#2d5a37; font-weight:bold; background:rgba(255,255,255,0.92); padding:10px 22px; border-radius:14px; box-shadow:0 2px 8px rgba(45,90,55,0.08); display:inline-block; margin-bottom:18px; }
        h1 { color: #2d5a37; text-align: center; margin-bottom: 30px; }
        .tile { background: rgba(255,255,255,0.97); border-radius: 20px; padding: 30px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.08); transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; position: relative; margin: 0 auto; max-width: 400px; }
        .tile-icon { font-size: 3.2rem; margin-bottom: 15px; }
        .tile-title { font-size: 1.3rem; font-weight: 700; color: #2d5a37; margin-bottom: 10px; }
        .tile-desc { font-size: 0.97rem; color: #666; line-height: 1.4; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="btn-retour">⬅ Retour Accueil</a>
    <img src="logo.png" alt="Famiflora" style="max-width:180px; display:block; margin:0 auto 18px auto; filter:drop-shadow(0 3px 6px rgba(0,0,0,0.15));">
    <h1>Gestion de la présence</h1>
    <div class="tile">
        <div class="tile-icon">🗓️</div>
        <div class="tile-title">Suivi des présences</div>
        <div class="tile-desc">Retrouvez ici le suivi et la gestion des présences aux différentes formations. (Fonctionnalité à venir)</div>
    </div>
</div>
</body>
</html>
