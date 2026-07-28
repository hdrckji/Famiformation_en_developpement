<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support de Formation PDF - FamiFormation</title>
  <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            margin: 0;
            background-color: #f4f7f6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        nav {
            background-color: #2d5a37;
            color: white;
            padding: 0.5rem 1.2rem;
            min-height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .logo-nav {
            max-height: 38px;
            margin-left: 18px;
        }
        nav a { color: white; text-decoration: none; font-weight: bold; }
        .pdf-container {
            flex-grow: 1;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            padding-bottom: 64px;
            background: transparent;
            min-height: 60vh;
            box-sizing: border-box;
        }
        .pdf-frame {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.13);
            border: 1px solid #e2e2e2;
            overflow: hidden;
            width: 90vw;
            max-width: 900px;
            height: 80vh;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pdf-frame iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }
        .quiz-action-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.92);
            padding: 8px 0 10px 0;
            text-align: center;
            z-index: 1000;
            border-top: 1px solid #ddd;
        }
        .btn-quiz {
            display: inline-block;
            background: #d9a406;
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            width: 80%;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        .btn-quiz:hover { transform: scale(1.05); background: #b88a05; }
        .btn-quiz:hover { transform: scale(1.05); background: #b88a05; }
    </style>
</head>
<body>

<nav>
    <a href="formation-caisse.php">← Retour aux options</a>
    <img src="logo.png" alt="FamiFormation" class="logo-nav">
</nav>

    <div class="pdf-container">
        <div class="pdf-frame">
            <iframe src="foc.pdf#toolbar=0" type="application/pdf"></iframe>
        </div>
    </div>
    <div class="quiz-action-container">
        <a href="quiz_engine.php?theme=quiz_pdf" class="btn-quiz">📝 J'ai fini la lecture, passer le Quiz</a>
    </div>

    <script src="/pdf-viewer.js" defer></script>
</body>
</html>