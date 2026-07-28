<?php
require_once 'config.php';
verifierConnexion($db);

$role = getCurrentRole();
if ($role === 'etudiant') {
    header('Location: formation-caisse.php');
    exit();
}

$title = 'Mes 2 premières semaines en caisse';
$allowedExtensions = ['pdf', 'txt', 'md', 'html'];
$defaultCandidates = [
    'parraincaisse.pdf',
    'mes-2-premieres-semaines-en-caisse.pdf',
    'mes_2_premieres_semaines_en_caisse.pdf',
    'Mes 2 premières semaines en caisse.pdf',
    'Mes 2 premieres semaines en caisse.pdf',
    'Mes 2er semaines en caisse.pdf'
];

$file = null;

if (!empty($_GET['file'])) {
    $requested = basename((string) $_GET['file']);
    $extension = strtolower(pathinfo($requested, PATHINFO_EXTENSION));
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $requested;

    if (in_array($extension, $allowedExtensions, true) && is_file($fullPath)) {
        $file = $requested;
    }
}

if ($file === null) {
    foreach ($defaultCandidates as $candidate) {
        if (is_file(__DIR__ . DIRECTORY_SEPARATOR . $candidate)) {
            $file = $candidate;
            break;
        }
    }
}

$isPdf = $file !== null && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf';
$textContent = null;

if ($file !== null && !$isPdf) {
    $textContent = @file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $file);
}
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
            background: url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            min-height: 100vh;
            color: #1f2d1f;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .back-link {
            color: #2d5a37;
            text-decoration: none;
            font-weight: 700;
            background: rgba(255,255,255,0.92);
            padding: 10px 18px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .hero {
            text-align: center;
            background: rgba(255,255,255,0.94);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.10);
            margin-bottom: 20px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        h1 {
            margin: 10px 0;
            color: #2d5a37;
        }
        .subtitle {
            color: #5d6b5d;
            margin: 0;
        }
        .viewer {
            background: rgba(255,255,255,0.96);
            border-radius: 22px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.10);
        }
        iframe {
            width: 100%;
            height: 80vh;
            min-height: 500px;
            border: 1px solid #e6e6e6;
            border-radius: 14px;
            background: #fff;
        }
        .file-note {
            margin-bottom: 16px;
            font-size: 0.95rem;
            color: #4e5d4e;
        }
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #425442;
        }
        .empty-state strong {
            color: #2d5a37;
        }
        .text-preview {
            white-space: pre-wrap;
            background: #fafafa;
            border: 1px solid #e8e8e8;
            border-radius: 14px;
            padding: 18px;
            line-height: 1.5;
            max-height: 75vh;
            overflow: auto;
        }
        .hint-list {
            text-align: left;
            display: inline-block;
            margin-top: 12px;
        }
        @media (max-width: 768px) {
            iframe {
                height: 65vh;
                min-height: 320px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <a href="formation-caisse.php" class="back-link">⬅ Retour à la formation caisse</a>
        </div>

        <div class="hero">
            <img src="logo.png" alt="Famiflora" class="logo">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p class="subtitle">Page réservée au personnel non étudiant.</p>
        </div>

        <div class="viewer">
            <?php if ($file === null): ?>
                <div class="empty-state">
                    <p><strong>Aucun fichier n'est encore disponible pour cette page.</strong></p>
                    <p>Déposez le document dans le dossier public avec l'un de ces noms :</p>
                    <div class="hint-list">
                        • parraincaisse.pdf<br>
                        • mes-2-premieres-semaines-en-caisse.pdf<br>
                        • mes_2_premieres_semaines_en_caisse.pdf
                    </div>
                </div>
            <?php elseif ($isPdf): ?>
                <div class="file-note">Document affiché : <strong><?php echo htmlspecialchars($file); ?></strong></div>
                <iframe src="<?php echo htmlspecialchars($file); ?>#toolbar=1" type="application/pdf"></iframe>
            <?php else: ?>
                <div class="file-note">Fichier affiché : <strong><?php echo htmlspecialchars($file); ?></strong></div>
                <div class="text-preview"><?php echo htmlspecialchars((string) $textContent); ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
