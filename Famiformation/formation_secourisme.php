<?php
session_start();
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
    <title>Formation secourisme</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f6f6f6; font-family: 'Open Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { background: white; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); padding: 36px 28px; max-width: 520px; width: 100%; }
        h2 { color: #2d5a37; font-size: 1.4rem; margin-bottom: 24px; text-align: center; }
        p { font-size: 1.1rem; color: #222; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Formation secourisme</h2>
        <p>La formation secourisme permet d'acquérir les gestes qui sauvent et est recommandée pour tous les collaborateurs. Renseignez-vous sur les prochaines sessions.</p>
    </div>
</body>
</html>
