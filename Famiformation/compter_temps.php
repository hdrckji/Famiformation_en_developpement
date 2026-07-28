<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) {
    // On ajoute 60 secondes au compteur de l'utilisateur
    $stmt = $db->prepare("UPDATE utilisateurs SET temps_connecte = temps_connecte + 60 WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}