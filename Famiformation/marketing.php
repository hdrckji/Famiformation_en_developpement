<?php
require_once 'config.php';
verifierConnexion($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Marketing - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: url('background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .header { text-align: center; padding: 16px 20px 8px; }
        .logo { max-width: 180px; margin-bottom: 20px; }
        h1 { color: #2d5a37; background: rgba(255, 255, 255, 0.9); padding: 12px 30px; border-radius: 30px; font-size: 1.6rem; display: inline-block; }
        .pdf-frame { width: 98vw; max-width: 1400px; height: 86vh; border: none; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); margin: 12px auto; background: #fff; }
        @media (max-width: 768px) {
            .pdf-frame { width: 100vw; height: 78vh; border-radius: 10px; }
        }
        .back-link { display: inline-block; margin: 0 0 30px; background: #2d5a37; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 30px; font-weight: 600; font-size: 1.1rem; transition: all 0.3s; box-shadow: 0 4px 12px rgba(45,90,55,0.13); }
        .back-link:hover { background: #214429; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo"><br>
        <h1>Marketing</h1>
    </div>
    <iframe src="marketing.pdf#zoom=page-width" class="pdf-frame"></iframe>
    <div style="margin: 0 auto 30px; text-align: center;">
        <a href="quiz_engine.php?theme=quiz_marketing" style="display:inline-block; background:#2d5a37; color:#fff; font-weight:bold; padding:18px 40px; border-radius:30px; font-size:1.2rem; text-decoration:none; box-shadow:0 4px 16px rgba(45,90,55,0.12); transition:background 0.3s;">Lancer le quizz Marketing</a>
    </div>
    <a href="deco.php" class="back-link">← Retour Déco</a>
    <script src="/pdf-viewer.js" defer></script>
</body>
</html>