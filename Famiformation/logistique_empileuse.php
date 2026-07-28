<?php
// logistique_empileuse.php
require_once 'config.php';
verifierConnexion($db);

// Securite : Seuls les Admins et les Employes Logistique peuvent entrer
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employe_logistique') {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empileuse - Logistique</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', Arial, Helvetica, sans-serif;
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            color: #333;
        }
        .header { text-align: center; padding: 40px 20px; }
        .logo { max-width: 180px; margin-bottom: 20px; }
        h1 {
            color: #2d5a37;
            background: rgba(255,255,255,0.9);
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1.6rem;
            display: inline-block;
        }
        .pdf-container { display: flex; flex-direction: column; align-items: center; margin-top: 30px; }
        .pdf-frame { width: 90vw; max-width: 900px; height: 80vh; border: 1px solid #b5c9b5; border-radius: 12px; box-shadow: 0 4px 18px rgba(0,0,0,0.08); }
        .back-link { margin-top: 40px; color: #2d5a37; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.9); padding: 12px 25px; border-radius: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: inline-block; }
        .back-link:hover { background: #2d5a37; color: white; }
    </style>
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="FamiFormation" class="logo">
        <br>
        <h1>Empileuse</h1>
    </div>
    <div class="pdf-container">
        <iframe class="pdf-frame" src="empileuse.pdf"></iframe>
    </div>
    <a href="logistique.php" class="back-link">← Retour logistique</a>
    <script src="/pdf-viewer.js" defer></script>
</body>
</html>
