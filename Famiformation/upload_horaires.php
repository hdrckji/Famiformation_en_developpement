<?php
require_once 'config.php';
verifierConnexion($db);
// Page d'upload d'un fichier Excel contenant horaires et départements
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Upload horaires Excel</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: #f6f6f6; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .container { background: white; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); padding: 40px 30px; margin-top: 60px; max-width: 420px; width: 100%; }
        h1 { color: #2d5a37; font-size: 1.5rem; margin-bottom: 24px; }
        label { font-weight: 600; color: #333; }
        input[type="file"] { margin: 18px 0 28px 0; }
        .btn { background: #2d5a37; color: #fff; border: none; border-radius: 30px; padding: 13px 32px; font-weight: 600; font-size: 1.1rem; cursor: pointer; transition: all 0.3s; }
        .btn:hover { background: #214429; }
        .msg { color: #2d5a37; margin-top: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Importer horaires & départements</h1>
        <form method="post" enctype="multipart/form-data">
            <label for="excel">Fichier Excel (.xlsx) :</label><br>
            <input type="file" name="excel" id="excel" accept=".xlsx" required><br>
            <button type="submit" class="btn">Uploader</button>
        </form>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel'])) {
            $file = $_FILES['excel'];
            if ($file['error'] === 0 && pathinfo($file['name'], PATHINFO_EXTENSION) === 'xlsx') {
                $target = 'horaires.xlsx';
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    echo '<div class="msg">Fichier importé avec succès !</div>';
                } else {
                    echo '<div class="msg">Erreur lors de l\'importation.</div>';
                }
            } else {
                echo '<div class="msg">Fichier invalide.</div>';
            }
        }
        ?>
    </div>
</body>
</html>
