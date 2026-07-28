<?php
require_once 'config.php';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'etudiant';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Management - FamiFormation</title>
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
        h1 { color: #2d5a37; text-align: center; margin-bottom: 30px; }
        .tiles-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; width: 90%; max-width: 900px; margin: 0 auto; }
        .tile { background: rgba(255,255,255,0.97); border-radius: 20px; padding: 30px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.08); transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; position: relative; }
        .tile:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.13); }
        .tile-icon { font-size: 3.2rem; margin-bottom: 15px; }
        .tile-title { font-size: 1.3rem; font-weight: 700; color: #2d5a37; margin-bottom: 10px; }
        .tile-desc { font-size: 0.97rem; color: #666; line-height: 1.4; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" style="text-decoration:none; color:#2d5a37; font-weight:bold; background:rgba(255,255,255,0.92); padding:10px 22px; border-radius:14px; box-shadow:0 2px 8px rgba(45,90,55,0.08); display:inline-block; margin-bottom:18px;">⬅ Retour Accueil</a>
    <img src="logo.png" alt="Famiflora" style="max-width:180px; display:block; margin:0 auto 18px auto; filter:drop-shadow(0 3px 6px rgba(0,0,0,0.15));">
    <h1>Espace Management</h1>
    <div class="tiles-container">
        <?php if ($role === 'mentor'): ?>
            <a href="mentor.php" class="tile">
                <div class="tile-icon">🤝</div>
                <div class="tile-title">Formation Parrain/Marraine</div>
                <div class="tile-desc">Accompagnement et transmission des savoirs.</div>
            </a>
        <?php else: ?>
            <a href="feedback.php" class="tile">
                <div class="tile-icon">💬</div>
                <div class="tile-title">Donner du feedback</div>
                <div class="tile-desc">Outils et conseils pour le feedback constructif.</div>
            </a>
            <a href="mentor.php" class="tile">
                <div class="tile-icon">🤝</div>
                <div class="tile-title">Formation Parrain/Marraine</div>
                <div class="tile-desc">Accompagnement et transmission des savoirs.</div>
            </a>
            <a href="leadership.php" class="tile">
                <div class="tile-icon">🦸‍♂️</div>
                <div class="tile-title">Leadership</div>
                <div class="tile-desc">Développer ses compétences de leader et inspirer son équipe.</div>
            </a>
            <a href="presence_view.php" class="tile">
                <div class="tile-icon">🗓️</div>
                <div class="tile-title">Gestion de la présence</div>
                <div class="tile-desc">Suivi et gestion des présences.</div>
            </a>
            <a href="entretien.php" class="tile">
                <div class="tile-icon">🗣️</div>
                <div class="tile-title">Entretiens de collaboration</div>
                <div class="tile-desc">Préparer et réussir les entretiens de suivi et d’évolution.</div>
            </a>
            <a href="judo.php" class="tile">
                <div class="tile-icon">🥋</div>
                <div class="tile-title">Judo verbal</div>
                <div class="tile-desc">Techniques pour gérer les situations difficiles.</div>
            </a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
