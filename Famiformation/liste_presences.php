<?php
require_once 'config.php';
// Vérifier si l'utilisateur est connecté (optionnel)
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
// Récupérer les présences
$stmt = $db->query("SELECT nom, heure, latitude, longitude FROM presences ORDER BY heure DESC");
$presences = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des présences</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Présences enregistrées</h1>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Date/Heure</th>
                <th>Latitude</th>
                <th>Longitude</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($presences as $presence): ?>
            <tr>
                <td><?= htmlspecialchars($presence['nom']) ?></td>
                <td><?= htmlspecialchars($presence['heure']) ?></td>
                <td><?= htmlspecialchars($presence['latitude']) ?></td>
                <td><?= htmlspecialchars($presence['longitude']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
