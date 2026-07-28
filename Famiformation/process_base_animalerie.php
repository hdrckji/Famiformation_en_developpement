<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Process de base de l'animalerie</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
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
        <img src="logo.png" alt="Famiflora" class="logo">
        <h1>Process de base de l'animalerie</h1>
    </div>
    <iframe class="pdf-frame" src="processanimalerie.pdf"></iframe>
    <a href="animalerie.php" class="back-link">← Retour à l'animalerie</a>
    <script src="/pdf-viewer.js" defer></script>
</body>
</html>