<?php
echo "✅ PHP fonctionne<br>";

// Affiche les erreurs même en production
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Tentative de chargement de config.php...<br>";
require_once 'config.php';
echo "✅ config.php chargé<br>";
echo "✅ Base de données connectée<br>";
?>