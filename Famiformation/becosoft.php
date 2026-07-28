<?php
require_once 'config.php';
verifierConnexion($db); 
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formation Becosoft - FamiFormation</title>
  <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            margin: 0; 
            background-color: #f4f7f6; 
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
            overflow: hidden;
        }
        
        nav { 
            background-color: #2e7d32; 
            color: white; 
            padding: 1rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 10;
        }
        nav a { color: white; text-decoration: none; font-weight: bold; }

        .pdf-container { 
            flex-grow: 1; 
            position: relative;
            /* On laisse de la place en bas pour le bouton sur mobile */
            padding-bottom: 80px; 
        }
        iframe { width: 100%; height: 100%; border: none; }

        /* Conteneur du bouton fixe en bas */
        .quiz-action-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(255,255,255,0.9) 20%, white);
            padding: 20px;
            text-align: center;
            z-index: 1000;
        }

        .btn-quiz {
            display: inline-block;
            background: #2e7d32;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            width: 80%; /* Largeur adaptée au mobile */
            max-width: 400px;
            transition: transform 0.2s;
        }

        .btn-quiz:active {
            transform: scale(0.95);
        }

        @media (max-width: 600px) {
            nav span { font-size: 0.8rem; }
            .btn-quiz { font-size: 1rem; padding: 12px 20px; }
        }
    </style>
</head>
<body>

<nav>
    <a href="formation_becosoft.php">← Retour</a>
    <span>Formation : Recherche Article</span>
    <div style="font-size: 0.8em; opacity: 0.8;">Famiflora</div>
</nav>

<div class="pdf-container">
    <iframe src="Becosoftarticle.pdf#toolbar=0"></iframe>
</div>

<div class="quiz-action-container">
    <a href="quiz_engine.php?theme=quiz_becosoft" class="btn-quiz">
        📝 Commencer le Quiz
    </a>
</div>

    <script src="/pdf-viewer.js" defer></script>
</body>
</html>