<?php
require_once 'config.php';
verifierConnexion($db); 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Démontage Chariots Danois - FamiFormation</title>
    <style>
        body { font-family: 'Open Sans', sans-serif; margin: 0; background-color: #f4f7f6; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        nav { background-color: #2d5a37; color: white; padding: 1rem; display: flex; justify-content: space-between; align-items: center; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        nav a { color: white; text-decoration: none; font-weight: bold; }
        .pdf-container { flex-grow: 1; position: relative; padding-bottom: 80px; }
        iframe { width: 100%; height: 100%; border: none; }
        .quiz-action-container { position: fixed; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(255,255,255,0.9) 20%, white); padding: 20px; text-align: center; z-index: 1000; }
        .btn-quiz { display: inline-block; background: #2d5a37; color: white; padding: 15px 40px; border-radius: 50px; text-decoration: none; font-weight: bold; width: 80%; max-width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: transform 0.2s; }
        .btn-quiz:active { transform: scale(0.95); }
    </style>
</head>
<body>
<nav>
    <a href="magasin.php">← Retour</a>
    <span>Procédure : Chariots Danois</span>
    <div style="font-size: 0.8em; opacity: 0.8;">Famiflora</div>
</nav>

<div class="pdf-container">
    <iframe src="PROCEDURE DEMONTAGE CHARIOTS DANOIS.pdf#toolbar=0"></iframe>
</div>

<div class="quiz-action-container">
    <a href="quiz_engine.php?theme=quiz_chariots" class="btn-quiz">📝 Commencer le Quiz Chariots</a>
</div>
    <script src="/pdf-viewer.js" defer></script>
</body>
</html>