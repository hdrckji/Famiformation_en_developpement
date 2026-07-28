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
    <title>Formation secourisme - PDF</title>
    <style>
        body { font-family: 'Open Sans', sans-serif; background: url('background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .container { background: rgba(255,255,255,0.95); border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 40px 32px; max-width: 900px; width: 100%; margin-top: 60px; }
        h2 { color: #2d5a37; font-size: 1.7rem; margin-bottom: 32px; text-align: center; font-weight: 700; }
        .pdf-frame { width: 100%; height: 700px; border: none; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="container">
        <a href="/securite_travail.php" style="display:inline-block;margin-bottom:18px;padding:8px 18px;background:#2d5a37;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;box-shadow:0 2px 8px rgba(44,90,55,0.08);transition:background 0.2s;">&larr; Retour</a>
        <h2>Formation secourisme</h2>
        <iframe src="https://famiformation.com/1secours.pdf" class="pdf-frame"></iframe>
    </div>
    <script src="/pdf-viewer.js" defer></script>
</body>
</html>
