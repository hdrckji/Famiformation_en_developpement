<?php
require_once 'config.php';
require_once __DIR__.'/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] === 'etudiant') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Sécurité au travail</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: url('background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .container { background: rgba(255,255,255,0.95); border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 40px 32px; max-width: 600px; width: 100%; margin-top: 60px; }
        h2 { color: #2d5a37; font-size: 1.7rem; margin-bottom: 32px; text-align: center; font-weight: 700; }
        .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 32px; margin-top: 24px; }
        .tile { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 30px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.1); transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; position: relative; font-size: 1.2rem; font-weight: 600; }
        .tile:hover { transform: translateY(-10px); box-shadow: 0 15px 35px rgba(44,90,55,0.18); background: #e8f5e9; }
        .tile-icon { font-size: 2.7rem; margin-bottom: 12px; }
        a { text-decoration: none; color: inherit; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/index.php" style="display:inline-block;margin-bottom:18px;padding:8px 18px;background:#2d5a37;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;box-shadow:0 2px 8px rgba(44,90,55,0.08);transition:background 0.2s;">&larr; Retour accueil</a>
        <h2>Sécurité au travail</h2>
        <div class="tiles">
            <a href="chaussure_securite_pdf.php" class="tile">
                <span class="tile-icon">👟</span>
                Chaussure de sécurité
            </a>
            <a href="/formation_secourisme_pdf.php" class="tile">
                <span class="tile-icon">⛑️</span>
                Formation secourisme
            </a>
        </div>
    </div>
</body>
</html>
