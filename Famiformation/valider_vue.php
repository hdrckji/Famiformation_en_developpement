<?php
require_once 'config.php';
if (isset($_SESSION['user_id']) && isset($_POST['nom_page'])) {
    $nom_page = trim($_POST['nom_page']); 
    $user_id = $_SESSION['user_id'];
    
    // On vérifie si la vidéo a déjà été vue par cet utilisateur
    $check = $db->prepare("SELECT id FROM progression_pages WHERE utilisateur_id = ? AND nom_page = ?");
    $check->execute([$user_id, $nom_page]);
    
    if (!$check->fetch()) {
        // C'est la première fois : on insère et on donne 10 points
        $stmt = $db->prepare("INSERT INTO progression_pages (utilisateur_id, nom_page) VALUES (?, ?)");
        $stmt->execute([$user_id, $nom_page]);

        $stmt_pts = $db->prepare("UPDATE utilisateurs SET points = points + 10 WHERE id = ?");
        $stmt_pts->execute([$user_id]);
    } else {
        // Déjà vu : on met juste à jour la date sans donner de points
        $stmt = $db->prepare("UPDATE progression_pages SET date_vue = CURRENT_TIMESTAMP WHERE utilisateur_id = ? AND nom_page = ?");
        $stmt->execute([$user_id, $nom_page]);
    }
    echo "Succès";
}
?>