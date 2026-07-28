<?php
// Affiche toutes les erreurs PHP à l'écran pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Vide tout output parasite éventuel
if (ob_get_level()) ob_clean();
require_once 'config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté.']);
    exit();
}
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!isset($data['latitude'], $data['longitude'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Données incomplètes.',
        'debug_raw' => $raw,
        'debug_json' => $data
    ]);
    exit();
}
$nom = $_SESSION['username'];
$latitude = $data['latitude'];
$longitude = $data['longitude'];
$heure = date('Y-m-d H:i:s');
require_once 'config.php'; // Doit contenir la connexion $db
$sql = "INSERT INTO presences (nom, heure, latitude, longitude) VALUES (?, ?, ?, ?)";
$stmt = $db->prepare($sql);
if ($stmt && $stmt->execute([$nom, $heure, $latitude, $longitude])) {
    echo json_encode(['success' => true, 'message' => 'Présence enregistrée !']);
    exit();
} else {
    $errorInfo = $stmt ? $stmt->errorInfo() : $db->errorInfo();
    echo json_encode([
        'success' => false,
        'message' => "Erreur lors de l'enregistrement : " . ($errorInfo[2] ?? 'Erreur inconnue.')
    ]);
    exit();
}
