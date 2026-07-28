<?php
require_once 'config.php';
verifierConnexion($db);

$selectedLanguage = ($_GET['lang'] ?? 'fr') === 'nl' ? 'nl' : 'fr';
$pdfFile = $selectedLanguage === 'nl' ? 'gerbeurnl.pdf' : 'gerbeur.pdf';
$role = getCurrentRole();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<title>Stock - Gerbeur</title>
	<style>
		body {
			font-family: 'Open Sans', sans-serif;
			background: url('background.jpg') no-repeat center center fixed;
			background-size: cover;
			margin: 0;
			padding: 0;
		}
		.header {
			text-align: center;
			margin-top: 40px;
		}
		.logo {
			width: 120px;
			margin-bottom: 20px;
		}
		.lang-switch {
			display: flex;
			justify-content: center;
			gap: 12px;
			flex-wrap: wrap;
			margin: 25px auto 0;
		}
		.lang-button {
			display: inline-block;
			padding: 12px 22px;
			border-radius: 999px;
			text-decoration: none;
			font-weight: bold;
			background: rgba(255, 255, 255, 0.92);
			color: #2d5a37;
			box-shadow: 0 4px 10px rgba(0,0,0,0.1);
			border: 2px solid transparent;
		}
		.lang-button.active {
			background: #2d5a37;
			color: #fff;
			border-color: #2d5a37;
		}
		.pdf-frame {
			display: block;
			margin: 40px auto;
			border: 2px solid #2d5a37;
			border-radius: 12px;
			box-shadow: 0 8px 24px rgba(0,0,0,0.12);
			width: 80vw;
			height: 80vh;
			background: white;
		}
		.back-link {
			display: block;
			width: fit-content;
			margin: 30px auto;
			color: #2d5a37;
			text-decoration: none;
			font-weight: bold;
			background: rgba(255, 255, 255, 0.9);
			padding: 12px 25px;
			border-radius: 25px;
			box-shadow: 0 4px 10px rgba(0,0,0,0.1);
		}
		.back-link:hover { background: #2d5a37; color: white; }
	</style>
</head>
<body>
	<div class="header">
		<img src="logo.png" alt="Famiflora" class="logo">
		<h1>Stock - Gerbeur</h1>
		<div class="lang-switch">
			<a href="gerbeur.php?lang=fr" class="lang-button <?php echo $selectedLanguage === 'fr' ? 'active' : ''; ?>">Version française</a>
			<a href="gerbeur.php?lang=nl" class="lang-button <?php echo $selectedLanguage === 'nl' ? 'active' : ''; ?>">Nederlandse versie</a>
		</div>
	</div>
	<iframe class="pdf-frame" src="<?php echo htmlspecialchars($pdfFile, ENT_QUOTES, 'UTF-8'); ?>"></iframe>
	<div style="margin: 40px auto; text-align: center;">
		<a href="quiz_engine.php?theme=quiz_gerbeur" style="display:inline-block; background:#2d5a37; color:#fff; font-weight:bold; padding:18px 40px; border-radius:30px; font-size:1.2rem; text-decoration:none; box-shadow:0 4px 16px rgba(45,90,55,0.12); transition:background 0.3s;">Lancer le quizz Gerbeur</a>
		<?php if ($role === 'admin'): ?>
			<br>
			<a href="checklist_gerbeur.php" style="display:inline-block; margin-top:18px; background:#d6a21a; color:#fff; font-weight:bold; padding:16px 34px; border-radius:30px; font-size:1.05rem; text-decoration:none; box-shadow:0 4px 16px rgba(214,162,26,0.16);">Ouvrir la checklist GERBEUR</a>
		<?php endif; ?>
		<br>
		<a href="magasin.php" class="back-link">← Retour à l'espace magasin</a>
	</div>
</body>
</html>

