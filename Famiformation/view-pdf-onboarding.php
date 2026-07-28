<?php
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/quizz_status.php';

if (getCurrentRole() === 'etudiant' && !hasUnlockedOnboarding(getCurrentUserId())) {
    header('Location: index.php');
    exit();
}

// Récupération du rôle utilisateur
$role = getCurrentRole();

// On récupère les paramètres, avec des valeurs par défaut pour le Onboarding PDF
$file = isset($_GET['file']) ? trim($_GET['file']) : 'op.pdf';
$title = isset($_GET['title']) ? $_GET['title'] : 'Livret d\'accueil';

// Si étudiant, on force le PDF OS
if ($role === 'etudiant') {
    $file = 'os.pdf'; // Remplace par le nom exact de ton PDF OS si besoin
    $title = "Livret d'accueil - OS";
}

// On sert le PDF via pdf-onboarding.php (en-tête application/pdf garanti) au lieu
// du fichier statique, qui s'affichait parfois en « texte bizarre » sur Railway.
$pdfSrc = 'pdf-onboarding.php?f=' . ($role === 'etudiant' ? 'os' : 'op');

// CORRECTION : On définit le lien direct vers le quiz onboarding PDF
$quiz_link = "quiz_engine.php?theme=quiz_onboarding_pdf";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Open Sans', sans-serif; 
            margin: 0; 
            background-color: #f4f7f6; 
            display: flex; 
            flex-direction: column; 
            height: 100vh; 
            overflow: hidden; 
        }
        
        nav { 
            background-color: #2d5a37; 
            color: white; 
            padding: 1rem; 
            display: flex; 
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        nav a { color: white; text-decoration: none; font-weight: bold; }

        .pdf-container { 
            flex-grow: 1; 
            width: 100%; 
            position: relative;
            padding-bottom: 80px; /* Espace pour le bouton en bas */
        }

        iframe { 
            width: 100%; 
            height: 100%; 
            border: none; 
        }

        .quiz-action-container { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            background: rgba(255, 255, 255, 0.9);
            padding: 20px; 
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
    </style>
</head>
<body>

<nav>
    <a href="onboarding.php">⬅ Retour</a>
    <span style="font-weight: 600;"><?php echo htmlspecialchars($title); ?></span>
    <div style="width: 60px;"></div> </nav>

<div class="pdf-container">
    <iframe src="<?php echo htmlspecialchars($pdfSrc); ?>#toolbar=0" type="application/pdf"></iframe>
</div>

<div class="quiz-action-container">
    <a href="<?php echo $quiz_link; ?>" class="btn-quiz">📝 Passer le Quiz de validation</a>
</div>

</body>
</html>