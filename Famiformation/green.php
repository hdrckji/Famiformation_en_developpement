<?php
require_once 'config.php';
verifierConnexion($db);
$user_id = $_SESSION['user_id'];

// Liste exacte des identifiants de pages pour le calcul du quiz
$pages_du_theme = ['plantes_hiver', 'sapin_noel', 'protection_hiver', 'plantes_automne_hiver', 'chrysanthemes', 'legumes']; 

$validees = 0;
foreach ($pages_du_theme as $p) {
    $stmt = $db->prepare("SELECT id FROM progression_pages WHERE utilisateur_id = ? AND nom_page = ?");
    $stmt->execute([$user_id, $p]);
    if ($stmt->fetch()) { $validees++; }
}
$quiz_debloque = ($validees === count($pages_du_theme));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Green - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: url('background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .header { text-align: center; padding: 40px 20px; }
        .logo { max-width: 180px; margin-bottom: 20px; }
        h1 { color: #2d5a37; background: rgba(255, 255, 255, 0.9); padding: 12px 30px; border-radius: 30px; font-size: 1.6rem; display: inline-block; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; width: 90%; max-width: 1100px; margin-bottom: 50px; }
        .tile { background: white; border-radius: 20px; padding: 30px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.1); transition: 0.3s; display: flex; flex-direction: column; align-items: center; }
        .tile:hover { transform: translateY(-5px); }
        .tile-icon { font-size: 3rem; margin-bottom: 10px; }
        .tile-title { font-weight: bold; color: #2d5a37; }
        .quiz-box { grid-column: 1 / -1; margin-top: 30px; padding: 30px; background: rgba(255,255,255,0.95); border-radius: 20px; text-align: center; border: 2px dashed #2d5a37; }
        .btn-quiz { background: #2d5a37; color: white; padding: 15px 40px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 1.2rem; display: inline-block; }
        .locked { color: #888; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo"><br>
        <h1>Thème : Green</h1>
    </div>

    <div class="grid">
        <a href="plantes-interieur-hiver.php" class="tile"><span class="tile-icon">🪴</span><span class="tile-title">Plantes intérieur Hiver</span></a>
        <a href="sapin-noel.php" class="tile"><span class="tile-icon">🎄</span><span class="tile-title">Le sapin de Noël</span></a>
        <a href="protection-hiver.php" class="tile"><span class="tile-icon">❄️</span><span class="tile-title">Protection des plantes</span></a>
        <a href="plantes-automne-hiver.php" class="tile"><span class="tile-icon">🍂</span><span class="tile-title">Plantes Automne/Hiver</span></a>
        <a href="chrysanthemes.php" class="tile"><span class="tile-icon">🌸</span><span class="tile-title">Les Chrysanthèmes</span></a>
        <a href="legumes.php" class="tile"><span class="tile-icon">🥦</span><span class="tile-title">Cultiver des légumes</span></a>

        <div class="quiz-box">
            <?php if ($quiz_debloque): ?>
                <h3>🎓 Quiz débloqué !</h3>
                <a href="quiz_engine.php?theme=quiz_plantes_hiver" class="btn-quiz">📝 Passer le Quiz Final Green</a>
            <?php else: ?>
                <p class="locked">🔒 Complétez les 6 formations (<?php echo $validees; ?>/6) pour passer le quiz.</p>
            <?php endif; ?>
        </div>
    </div>
    <a href="index.php" style="margin-bottom:40px; color:white; font-weight:bold; text-decoration:none;">← Retour Accueil</a>
</body>
</html>