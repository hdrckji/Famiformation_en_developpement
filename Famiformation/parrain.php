<?php
require_once 'config.php';
verifierConnexion($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formation Parrain/Marraine - FamiFormation</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 10px;
        }
        .container { width: 98vw; max-width: 1400px; margin: 0 auto; }
        .btn-retour { text-decoration:none; color:#2d5a37; font-weight:bold; background:rgba(255,255,255,0.92); padding:10px 22px; border-radius:14px; box-shadow:0 2px 8px rgba(45,90,55,0.08); display:inline-block; margin-bottom:18px; }
        h1 { color: #2d5a37; text-align: center; margin-bottom: 30px; }
        .pdf-viewer { background: white; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 12px; margin-bottom: 30px; text-align: center; }
        .quiz-action { margin: 0 auto 30px; text-align: center; }
        .btn-quiz { display:inline-block; background:#2d5a37; color:#fff; font-weight:bold; padding:18px 40px; border-radius:30px; font-size:1.1rem; text-decoration:none; box-shadow:0 4px 16px rgba(45,90,55,0.12); transition:background 0.3s; }
    </style>
</head>
<body>
<div class="container">
    <a href="deco.php" class="btn-retour">⬅ Retour Déco</a>
    <img src="logo.png" alt="Famiflora" style="max-width:180px; display:block; margin:0 auto 18px auto; filter:drop-shadow(0 3px 6px rgba(0,0,0,0.15));">
    <h1>Formation Parrain/Marraine</h1>
    <div class="pdf-viewer">
        <p><strong>Support de formation :</strong></p>
        <iframe src="parrain.pdf#zoom=page-width" type="application/pdf" style="width:100%;height:86vh;min-height:400px;max-height:1200px;border-radius:12px;border:1px solid #eee;background:white;"></iframe>
    </div>
    <div class="quiz-action">
        <a href="quiz_engine.php?theme=quiz_mentor" class="btn-quiz">Lancer le quizz Parrain/Marraine</a>
    </div>
    <style>
    @media (max-width: 700px) {
        .container { width: 100vw; }
        .pdf-viewer iframe { height: 78vh !important; min-height: 220px !important; }
    }
    </style>
</div>
    <script src="/pdf-viewer.js" defer></script>
</body>
</html>