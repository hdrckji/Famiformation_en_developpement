<?php
require_once 'config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['username'])) {
    echo json_encode(['count' => 0]);
    exit();
}
$nom = $_SESSION['username'];
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) FROM presences WHERE nom = ? AND DATE(heure) = ?");
$stmt->execute([$nom, $today]);
$count = $stmt->fetchColumn();
echo json_encode(['count' => (int)$count]);
