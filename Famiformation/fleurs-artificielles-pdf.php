<?php
require_once 'config.php';
verifierConnexion($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleurs Artificielles PDF - FamiFormation</title>
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
        }
        .header {
            text-align: center;
            padding: 16px 20px 8px;
        }
        .logo {
            max-width: 140px;
            margin-bottom: 12px;
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
        .pdf-frame {
            width: 98vw;
            max-width: 1400px;
            height: 86vh;
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.14);
            margin: 12px auto;
            background: #fff;
        }
        @media (max-width: 768px) {
            .pdf-frame {
                width: 100vw;
                height: 78vh;
                border-radius: 10px;
            }
        }
        .actions {
            margin: 8px auto 35px;
            text-align: center;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn {
            display: inline-block;
            text-decoration: none;
            border-radius: 30px;
            padding: 14px 28px;
            font-weight: 700;
            transition: background 0.25s ease;
        }
        .btn-quiz {
            background: #2d5a37;
            color: #fff;
        }
        .btn-quiz:hover {
            background: #214429;
        }
        .btn-back {
            background: #ffffff;
            color: #2d5a37;
            border: 2px solid #2d5a37;
        }
        .btn-back:hover {
            background: #f4f8f5;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo"><br>
        <h1>Fleurs Artificielles - Guide PDF</h1>
    </div>

    <iframe src="fleursarti.pdf#zoom=page-width" class="pdf-frame" title="Guide fleurs artificielles"></iframe>

    <div class="actions">
        <a href="quiz_engine.php?theme=quiz_fleurs_artificielles_pdf" class="btn btn-quiz">Lancer le quizz Fleurs Artificielles (PDF)</a>
        <a href="fleurs-artificielles-menu.php" class="btn btn-back">&#8592; Retour raccourcis</a>
    </div>
    <script src="/pdf-viewer.js" defer></script>
</body>
</html>