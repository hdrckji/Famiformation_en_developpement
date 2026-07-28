<?php
require_once 'config.php';
verifierConnexion($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Barbecue Barbecook et Napoleon - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: url('background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .header { text-align: center; padding: 40px 20px; }
        .logo { max-width: 180px; margin-bottom: 20px; }
        h1 { color: #2d5a37; background: rgba(255, 255, 255, 0.9); padding: 12px 30px; border-radius: 30px; font-size: 1.6rem; display: inline-block; }
        .pdf-frame { width: 90vw; max-width: 900px; height: 80vh; border: none; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); margin: 40px auto; background: #fff; }
    </style>
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo"><br>
        <h1>Barbecue Barbecook et Napoleon</h1>
    </div>
    <iframe src="barbecue2.pdf" class="pdf-frame"></iframe>
    <div style="margin: 0 auto 40px; text-align: center;">
        <a href="quiz_engine.php?theme=quiz_barbecue2" style="display:inline-block; background:#2d5a37; color:#fff; font-weight:bold; padding:18px 40px; border-radius:30px; font-size:1.2rem; text-decoration:none; box-shadow:0 4px 16px rgba(45,90,55,0.12); transition:background 0.3s;">Lancer le quizz Barbecue Barbecook et Napoleon</a>
    </div>
    <script src="/pdf-viewer.js" defer></script>
</body>
</html>