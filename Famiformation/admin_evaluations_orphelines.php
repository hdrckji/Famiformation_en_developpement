
<?php
require_once 'config.php';
requireAdminOrTeamcoach();

$evals = [];
if (file_exists('evaluations_orphelines.php')) {
    clearstatcache(true, 'evaluations_orphelines.php');
    $evals = include('evaluations_orphelines.php');
    if (!is_array($evals)) $evals = [];
}

$evals_changed = false;
// Suppression
if (isset($_POST['delete_idx'])) {
    requireValidCSRF();
    $idx = intval($_POST['delete_idx']);
    if (isset($evals[$idx])) {
        array_splice($evals, $idx, 1);
        $evals_changed = true;
    }
}
// Rattachement à un profil existant
if (isset($_POST['relier_idx'], $_POST['relier_nom'], $_POST['relier_prenom'])) {
    requireValidCSRF();
    $idx = intval($_POST['relier_idx']);
    $nom = trim($_POST['relier_nom']);
    $prenom = trim($_POST['relier_prenom']);
    if (isset($evals[$idx]) && $nom && $prenom) {
        // Charger la base utilisateurs
        $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE LOWER(nom) = ? AND LOWER(prenom) = ? LIMIT 1");
        $stmt->execute([strtolower($nom), strtolower($prenom)]);
        $user = $stmt->fetch();
        if ($user) {
            // Charger les évaluations stock
            $evals_stock = file_exists('evaluations_stock.php') ? include('evaluations_stock.php') : [];
            $eval = $evals[$idx];
            $eval['nom'] = $nom;
            $eval['prenom'] = $prenom;
            $evals_stock[] = $eval;
            file_put_contents('evaluations_stock.php', '<?php return ' . var_export($evals_stock, true) . ';', LOCK_EX);
            // Supprimer de orphelines
            array_splice($evals, $idx, 1);
            $evals_changed = true;
        }
    }
}
if ($evals_changed) {
    file_put_contents('evaluations_orphelines.php', '<?php return ' . var_export($evals, true) . ';', LOCK_EX);
    header('Location: admin_evaluations_orphelines.php');
    exit;
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Évaluations orphelines</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Évaluations orphelines (non associées à un collaborateur)</h1>
    <a href="admin.php">← Retour admin</a>
    <?php if (!empty($evals)) : ?>
    <table border="1" cellpadding="6" style="margin-top:20px;">
        <tr>
            <?php foreach(array_keys($evals[0]) as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>
        <?php foreach ($evals as $idx => $e) : ?>
        <tr>
            <?php foreach($e as $val): ?>
                <td><?= nl2br(htmlspecialchars($val)) ?></td>
            <?php endforeach; ?>
            <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette évaluation ?');">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="delete_idx" value="<?= $idx ?>">
                    <button type="submit">🗑️</button>
                </form>
                <form method="post" style="display:inline;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="relier_idx" value="<?= $idx ?>">
                    <input type="text" name="relier_nom" placeholder="Nom">
                    <input type="text" name="relier_prenom" placeholder="Prénom">
                    <button type="submit">Relier</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php if (empty($evals)) : ?>
        <p>Aucune évaluation orpheline enregistrée.</p>
    <?php endif; ?>
</body>
</html>