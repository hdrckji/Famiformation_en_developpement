$nom = $_SESSION['nom'] ?? '';
$prenom = $_SESSION['prenom'] ?? '';
if (!$nom || !$prenom) {
	echo '<div class="msg">Connexion requise.</div>';
} else {
	$found = false;
	$sheet = $spreadsheet->getActiveSheet();
	$rows = $sheet->toArray();
	foreach ($rows as $row) {
		// Recherche nom/prénom dans chaque ligne
		if (isset($row[0], $row[1]) && strtolower(trim($row[0])) == strtolower(trim($nom)) && strtolower(trim($row[1])) == strtolower(trim($prenom))) {
			$horaire = $row[2] ?? '';
			$departement = $row[3] ?? '';
			echo '<div class="info">Horaire : ' . htmlspecialchars($horaire) . '</div>';
			echo '<div class="info">Département : ' . htmlspecialchars($departement) . '</div>';
			$found = true;
			break;
		}
	}
	if (!$found) {
		echo '<div class="msg">Aucune donnée trouvée pour ' . htmlspecialchars($nom) . ' ' . htmlspecialchars($prenom) . '.</div>';
	}
}
<?php
require_once 'config.php';
verifierConnexion($db);
require 'vendor/autoload.php';
echo '<div class="info">autoload OK</div>';
use PhpOffice\PhpSpreadsheet\IOFactory;
$file = 'test2.xlsx';
try {
	$spreadsheet = IOFactory::load($file);
	echo '<div class="info">Excel chargé avec succès</div>';
} catch (Exception $e) {
	echo '<div class="msg">Erreur lors du chargement Excel : ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<title>Mon horaire & département</title>
	<link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
	<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
	<style>
		body { font-family: 'Open Sans', sans-serif; background: #f6f6f6; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
		.container { background: white; border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); padding: 40px 30px; margin-top: 60px; max-width: 420px; width: 100%; }
		h1 { color: #2d5a37; font-size: 1.5rem; margin-bottom: 24px; }
		.info { color: #2d5a37; font-size: 1.15rem; margin-top: 18px; }
		.msg { color: #d32f2f; margin-top: 18px; }
	</style>
</head>
<body>
	<div class="container">
		<h1>Mon horaire & département</h1>
		<div class="info">Test HTML OK</div>
	</div>
</body>
</html>