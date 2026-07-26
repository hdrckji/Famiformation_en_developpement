<?php
require_once 'config.php';
verifierConnexion($db);

$role = getCurrentRole();
$allowedRoles = ['beta', 'etudiant', 'admin', 'teamcoach', 'mentor', 'employe_magasin'];

if (!in_array($role, $allowedRoles, true)) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Options de Formation - FamiFormation</title>
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

        .header { text-align: center; padding: 40px 20px; }
        .logo { max-width: 150px; margin-bottom: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        
        h1 {
            color: #2d5a37;
            background: rgba(255, 255, 255, 0.8);
            padding: 10px 25px;
            border-radius: 30px;
            font-size: 1.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .tiles-container {
            display: flex;
            justify-content: center;
            align-items: stretch;
            gap: 30px;
            width: 90%;
            max-width: 1200px;
            padding: 20px;
            flex-wrap: wrap;
        }

        .tile {
            background: rgba(255,255,255,0.97);
            box-shadow: 0 8px 24px rgba(45,90,55,0.08);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            width: 280px;
            min-height: 240px;
            box-sizing: border-box;
            position: relative;
        }

        .tile:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(45,90,55,0.14);
        }

        .tile-icon { font-size: 60px; margin-bottom: 20px; }
        .tile-title { font-size: 1.3rem; font-weight: 700; color: #2d5a37; min-height: 3.2rem; display: flex; align-items: center; justify-content: center; }
        .tile-desc { margin-top: auto; font-size: 0.95rem; color: #666; }

        .back-link {
            margin-top: 50px;
            color: #2d5a37;
            text-decoration: none;
            font-weight: bold;
            background: white;
            padding: 12px 25px;
            border-radius: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <div class="header">
        <img src="logo.png" alt="FamiFormation" class="logo">
        <h1>Formation : Caisses</h1>
    </div>

    <div class="tiles-container">
        <a href="view-pdf.php" class="tile">
            <span class="tile-icon">📄</span>
            <div class="tile-title">Support PDF</div>
            <div class="tile-desc">Lire le manuel de formation</div>
        </a>

        <a href="caisse.php" class="tile">
            <span class="tile-icon">🎬</span>
            <div class="tile-title">Vidéo Tutoriel</div>
            <div class="tile-desc">Visionner la démonstration</div>
        </a>

        <a href="module-technique.php" class="tile">
            <span class="tile-icon">🛠️</span>
            <div class="tile-title">Module technique</div>
            <div class="tile-desc">Voir la vidéo technique</div>
        </a>

        <?php if ($role !== 'etudiant'): ?>
        <a href="mes-2-premieres-semaines-caisse.php" class="tile">
            <span class="tile-icon">🗂️</span>
            <div class="tile-title">Mes 2 premieres semaines en caisse</div>
            <div class="tile-desc">Consulter le document d'accompagnement</div>
        </a>
        <?php endif; ?>
    </div>

    <a href="<?php echo ($role === 'etudiant') ? 'index.php' : 'magasin.php'; ?>" class="back-link">← <?php echo ($role === 'etudiant') ? 'Retour à l\'accueil' : 'Retour au Magasin'; ?></a>

</body>
</html>