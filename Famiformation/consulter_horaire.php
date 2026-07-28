<?php
// consulter_horaire.php
require_once 'config.php';
verifierConnexion($db);

$nomComplet = trim(($_SESSION['nom'] ?? '') . ' ' . ($_SESSION['prenom'] ?? ''));

$pdo = getPlanningDbConnection();
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo "Erreur de connexion à la base planning.";
    exit();
}
// Recherche des horaires dans planning_lignes
$sql = "SELECT jour, departement, horaire FROM planning_lignes WHERE nom = ? ORDER BY jour, horaire";
$stmt = $pdo->prepare($sql);
$stmt->execute([$nomComplet]);
$horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon horaire</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Mon horaire</h1>
    <p>Nom recherché : <b><?= htmlspecialchars($nomComplet) ?></b></p>
    <?php if (count($horaires) === 0): ?>
        <p>Aucun horaire trouvé.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Jour</th>
                <th>Département</th>
                <th>Horaire</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($horaires as $h): ?>
            <tr>
                <td><?= htmlspecialchars($h['jour']) ?></td>
                <td><?= htmlspecialchars($h['departement']) ?></td>
                <td><?= htmlspecialchars($h['horaire']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>
</html>
