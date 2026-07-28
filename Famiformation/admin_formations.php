<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
requireAdminOrTeamcoach();

$message = "";

// 1. SUPPRESSION

// Migration colonne is_evaluation_target
try {
    $colCheck = $db->query("SHOW COLUMNS FROM formations_sessions LIKE 'is_evaluation_target'");
    if (!$colCheck->fetch()) {
        $db->exec("ALTER TABLE formations_sessions ADD COLUMN is_evaluation_target TINYINT(1) NOT NULL DEFAULT 0");
    }
} catch (Exception $e) {}

// 1. SUPPRESSION
if (isset($_GET['delete_f'])) { 
    requireValidCSRF();
    $db->prepare("DELETE FROM formations_sessions WHERE id = ?")->execute([$_GET['delete_f']]); 
    header("Location: admin_formations.php"); exit(); 
}
if (isset($_GET['delete_c'])) { 
    requireValidCSRF();
    $db->prepare("DELETE FROM formations_creneaux WHERE id = ?")->execute([$_GET['delete_c']]); 
    header("Location: admin_formations.php"); exit(); 
}
if (isset($_GET['delete_inscription'])) {
    requireValidCSRF();
    $inscriptionId = (int) ($_GET['delete_inscription'] ?? 0);
    $inscriptionStmt = $db->prepare("SELECT i.id, i.formation_id, i.creneau_id, i.utilisateur_id, u.role FROM formations_inscriptions i JOIN utilisateurs u ON u.id = i.utilisateur_id WHERE i.id = ? LIMIT 1");
    $inscriptionStmt->execute([$inscriptionId]);
    $inscription = $inscriptionStmt->fetch(PDO::FETCH_ASSOC);

    $agencyMailSent = false;
    if ($inscription && $inscription['role'] === 'etudiant' && !empty($inscription['creneau_id'])) {
        $agencyMailSent = sendInterimAgencyCancellationEmail(
            $db,
            (int) $inscription['utilisateur_id'],
            (int) $inscription['formation_id'],
            (int) $inscription['creneau_id']
        );
    }

    $stmt = $db->prepare("DELETE FROM formations_inscriptions WHERE id = ?");
    $stmt->execute([$inscriptionId]);
    $deleted = $stmt->rowCount();
    if ($deleted > 0) {
        $message = "<div class='alert success'>✅ Inscription supprimée avec succès.";
        if ($agencyMailSent) {
            $message .= "<br>📨 L'agence intérim a été informée du changement de planning.</div>";
        } else {
            $message .= "</div>";
        }
    } else {
        $message = "<div class='alert error'>❌ Échec de la suppression (inscription introuvable).</div>";
    }
    // Ne pas rediriger, afficher le message
}
if (isset($_GET['delete_suggestion'])) {
    requireValidCSRF();
    $suggestionId = (int) ($_GET['delete_suggestion'] ?? 0);
    $stmt = $db->prepare("DELETE FROM formation_suggestions WHERE id = ?");
    $stmt->execute([$suggestionId]);
    header("Location: admin_formations.php");
    exit();
}

// 2. ACTIONS (AJOUT/EDIT)
if (isset($_POST['edit_formation'])) {
    requireValidCSRF();
    // public_vise peut être un tableau (multi-profil) ou une chaîne
    $public = '';
    if (isset($_POST['public_vise'])) {
        if (is_array($_POST['public_vise'])) {
            // si "tous" est sélectionné, on ignore les autres
            if (in_array('tous', $_POST['public_vise'])) {
                $public = 'tous';
            } else {
                $public = implode(',', $_POST['public_vise']);
            }
        } else {
            $public = $_POST['public_vise'];
        }
    }
    $db->prepare("UPDATE formations_sessions SET titre = ?, description = ?, public_vise = ? WHERE id = ?")
       ->execute([$_POST['titre'] ?? '', $_POST['desc'] ?? '', $public, $_POST['f_id'] ?? '']);
    $message = "<div class='alert success'>✅ Thématique mise à jour.</div>";
    $isEvalTarget = isset($_POST['is_evaluation_target']) ? 1 : 0;
    $db->prepare("UPDATE formations_sessions SET is_evaluation_target = ? WHERE id = ?")
       ->execute([$isEvalTarget, $_POST['f_id'] ?? '']);
}
if (isset($_POST['add_formation'])) {
    requireValidCSRF();
    $public = '';
    if (isset($_POST['public_vise'])) {
        if (is_array($_POST['public_vise'])) {
            if (in_array('tous', $_POST['public_vise'])) {
                $public = 'tous';
            } else {
                $public = implode(',', $_POST['public_vise']);
            }
        } else {
            $public = $_POST['public_vise'];
        }
    }
    $db->prepare("INSERT INTO formations_sessions (titre, description, public_vise) VALUES (?, ?, ?)")
       ->execute([$_POST['titre'] ?? '', $_POST['desc'] ?? '', $public]);
    $message = "<div class='alert success'>✅ Thématique créée.</div>";
    $isEvalTargetNew = isset($_POST['is_evaluation_target']) ? 1 : 0;
    if ($isEvalTargetNew) {
        $newId = $db->lastInsertId();
        $db->prepare("UPDATE formations_sessions SET is_evaluation_target = 1 WHERE id = ?")->execute([$newId]);
    }
}
if (isset($_POST['add_creneau'])) { 
    requireValidCSRF();
    $formation_id = $_POST['f_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $duree = $_POST['duree'] ?? '';
    $places_max = $_POST['max'] ?? '';
    
    $stmt = $db->prepare("INSERT INTO formations_creneaux (formation_id, date_heure, duree, places_max) VALUES (?, ?, ?, ?)");
    $stmt->execute([$formation_id, $date, $duree, $places_max]);

    // Récupérer l'ID du créneau qui vient d'être créé
    $creneau_id = $db->lastInsertId();

    if ($creneau_id) {
        // Notifier uniquement les étudiants ayant manifesté leur intérêt
        $stmtInt = $db->prepare("SELECT u.email, u.nom, u.prenom FROM utilisateurs u JOIN formations_inscriptions i ON u.id = i.utilisateur_id WHERE i.formation_id = ? AND i.creneau_id IS NULL AND u.role = 'etudiant' AND u.email IS NOT NULL AND u.email != ''");
        $stmtInt->execute([$formation_id]);
        $interesses = $stmtInt->fetchAll();
        foreach ($interesses as $u) {
            $subject = "🎓 Nouvelle date de formation disponible !";
            $body = '<div style="background:#f4f7f6;padding:32px 24px;border-radius:18px;font-family:Open Sans,sans-serif;color:#2d5a37;max-width:480px;margin:auto;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                <h2 style="color:#1976d2;margin-bottom:18px;">Nouvelle date de formation</h2>
                <p style="font-size:1.1em;margin-bottom:18px;">Bonjour <strong>' . htmlspecialchars($u['prenom']) . '</strong>,</p>
                <p style="margin-bottom:18px;">Une date vient d&#39;être programmée pour la formation à laquelle vous avez manifesté votre intérêt.</p>
                <p style="margin-bottom:18px;">Vous pouvez vous inscrire directement depuis votre espace <a href="https://famiformation.fr" style="color:#1976d2;text-decoration:underline;">FamiFormation</a>.</p>
                <div style="margin:28px 0 0 0;font-size:0.95em;color:#888;">À bientôt,<br>L&#39;équipe Famiflora</div>
            </div>';
            sendMail($u['email'], $subject, $body);
        }
    }
}
if (isset($_POST['edit_creneau'])) { 
    requireValidCSRF();
    $creneau_id = $_POST['c_id'] ?? '';
    $date = $_POST['date'] ?? '';
    $duree = $_POST['duree'] ?? '';
    $places_max = $_POST['max'] ?? '';
    
    $db->prepare("UPDATE formations_creneaux SET date_heure = ?, duree = ?, places_max = ? WHERE id = ?")
       ->execute([$date, $duree, $places_max, $creneau_id]);
    
    if ($creneau_id) {
        $formStmt = $db->prepare("SELECT formation_id FROM formations_creneaux WHERE id = ?");
        $formStmt->execute([$creneau_id]);
        $formation_id = $formStmt->fetchColumn();
    }
}

// Filtre formations par date des créneaux
$filter = $_GET['date_filter'] ?? '';
$sql = "SELECT fs.* FROM formations_sessions fs ";
if ($filter === 'future') {
    $sql .= "WHERE EXISTS (
                SELECT 1
                FROM formations_creneaux fc
                WHERE fc.formation_id = fs.id
                  AND fc.date_heure >= NOW()
            ) ";
} elseif ($filter === 'past') {
    $sql .= "WHERE EXISTS (
                SELECT 1
                FROM formations_creneaux fc
                WHERE fc.formation_id = fs.id
                  AND fc.date_heure < NOW()
            ) ";
}
$sql .= "ORDER BY fs.id DESC";
$formations = $db->query($sql)->fetchAll();

$roleLabels = [
    'tous' => 'Tous',
    'employe_magasin' => 'Employé Magasin',
    'employe_logistique' => 'Employé Logistique',
    'etudiant' => 'Étudiant',
    'admin' => 'Admin',
    'teamcoach' => 'TeamCoach',
    'mentor' => 'Mentor',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Formations - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: auto; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; }
        .input-edit { padding: 8px; border: 1px solid #ddd; border-radius: 5px; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; background: #eee; padding: 10px; font-size: 0.85rem; }
        td { padding: 10px; border-bottom: 1px solid #eee; font-size: 0.85rem; }
        .badge-user { background: #e8f5e9; color: #2d5a37; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-right: 3px; display: inline-block; position: relative; }
        .badge-user .btn-delete-inscription { background: rgba(217, 48, 37, 0.8); color: white; border: none; border-radius: 50%; width: 16px; height: 16px; padding: 0; font-size: 0.7rem; cursor: pointer; margin-left: 4px; vertical-align: text-bottom; transition: background 0.3s; }
        .badge-user .btn-delete-inscription:hover { background: #d9302d; }
        .badge-interest { background: #fff3e0; color: #e65100; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-right: 3px; display: inline-block; position: relative; }
        .btn-excel { background: #1d6f42; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; float: right; }
        .search-tools { display:flex; gap:10px; align-items:end; flex-wrap:wrap; }
        .search-tools .field { display:flex; flex-direction:column; gap:6px; }
        .search-tools label { font-size:0.8rem; color:#2d5a37; font-weight:700; }
        .search-tools input[type="text"], .search-tools select { min-width:220px; padding:9px 10px; border:1px solid #d8d8d8; border-radius:8px; }
        .search-tools .check-wrap { display:flex; align-items:center; gap:8px; padding-bottom:8px; }
        .search-tools .result-count { margin-left:auto; font-weight:700; color:#2d5a37; }
        .formation-item { padding: 0; overflow: hidden; }
        .formation-details { border-radius: 12px; }
        .formation-summary { list-style: none; cursor: pointer; padding: 16px 20px; display:flex; justify-content:space-between; align-items:flex-start; gap:14px; background:#f8fbf9; }
        .formation-summary::-webkit-details-marker { display:none; }
        .formation-main { display:flex; flex-direction:column; gap:6px; }
        .formation-main h3 { margin:0; color:#214b35; font-size:1.08rem; }
        .formation-sub { color:#5f6b63; font-size:0.88rem; }
        .role-tags { display:flex; flex-wrap:wrap; gap:6px; }
        .role-tag { background:#e8f5e9; color:#2d5a37; border-radius:999px; padding:3px 9px; font-size:0.74rem; font-weight:700; }
        .summary-metas { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .summary-pill { background:#fff; border:1px solid #dbe7dd; border-radius:999px; padding:5px 10px; font-size:0.76rem; color:#355444; font-weight:700; white-space:nowrap; }
        .formation-body { padding: 16px 20px 20px; }
        .empty-search { display:none; background:#fff3e0; color:#8c5a00; padding:12px 14px; border-radius:10px; font-weight:700; margin-bottom:16px; }
    </style>
</head>
<body>
<div class="container">
    <a href="export_formations.php" class="btn-excel">📥 Export Excel Complet</a>
    <a href="formation.php" style="text-decoration:none;">⬅ Retour</a>
    <h1>🛠 Gestion des Formations</h1>

    <?php echo $message; ?>
    <form method="GET" style="margin-bottom:20px;">
        <label for="date_filter"><strong>Filtrer par date :</strong></label>
        <select name="date_filter" id="date_filter" onchange="this.form.submit()" style="margin-left:10px;">
            <option value="" <?php if(empty($_GET['date_filter'])) echo 'selected'; ?>>Toutes</option>
            <option value="future" <?php if(isset($_GET['date_filter']) && $_GET['date_filter']==='future') echo 'selected'; ?>>Formations futures</option>
            <option value="past" <?php if(isset($_GET['date_filter']) && $_GET['date_filter']==='past') echo 'selected'; ?>>Formations passées</option>
        </select>
    </form>

    <div class="card">
        <h2>➕ Nouvelle thématique</h2>
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="text" name="titre" placeholder="Titre" required class="input-edit" style="width:30%;">
            <input type="text" name="desc" placeholder="Description" class="input-edit" style="width:40%;">
            <select name="public_vise[]" class="input-edit" multiple size="3" style="width:20%;">
                <option value="tous">🌍 Tous</option>
                <option value="employe_magasin">👔 Employé Magasin</option>
                <option value="employe_logistique">🚚 Employé Logistique</option>
                <option value="etudiant">🎓 Étudiant</option>
                <option value="admin">🛠 Admin</option>
                <option value="teamcoach">🧑‍🏫 TeamCoach</option>
                <option value="mentor">🧑‍🎓 Mentor</option>
            </select>
            <button type="submit" name="add_formation" class="input-edit" style="background:#2d5a37; color:white; cursor:pointer;">Créer</button>
            <div style="font-size:0.8rem; color:#555; margin-top:5px;">(Ctrl/Cmd+clic pour sélectionner plusieurs)</div>
                    <label style="display:flex;align-items:center;gap:6px;margin-top:8px;font-size:0.9em;color:#214b35;font-weight:700;cursor:pointer;">
                        <input type="checkbox" name="is_evaluation_target" value="1">
                        Formation d'évaluation caisse (bloque la ré-inscription quand évalué)
                    </label>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">🔎 Rechercher une formation existante</h2>
        <div class="search-tools">
            <div class="field">
                <label for="searchFormation">Texte (titre, description, profil)</label>
                <input type="text" id="searchFormation" placeholder="Ex: caisse, mentor, onboarding...">
            </div>
            <div class="field">
                <label for="profileFilter">Profil ciblé</label>
                <select id="profileFilter">
                    <option value="">Tous les profils</option>
                    <option value="tous">Tous</option>
                    <option value="employe_magasin">Employé Magasin</option>
                    <option value="employe_logistique">Employé Logistique</option>
                    <option value="etudiant">Étudiant</option>
                    <option value="admin">Admin</option>
                    <option value="teamcoach">TeamCoach</option>
                    <option value="mentor">Mentor</option>
                </select>
            </div>
            <label class="check-wrap">
                <input type="checkbox" id="waitingFilter">
                Afficher uniquement les formations avec intéressés
            </label>
            <div class="result-count" id="resultCount"></div>
        </div>
    </div>

    <div class="empty-search" id="emptySearch">Aucune formation ne correspond aux filtres en cours.</div>

    <?php foreach ($formations as $f): ?>
    <?php
        $currentRoles = array_values(array_filter(explode(',', (string) ($f['public_vise'] ?? ''))));
        $searchBlob = mb_strtolower(trim(implode(' ', [$f['titre'] ?? '', $f['description'] ?? '', implode(' ', $currentRoles)])));

        $waitingCountStmt = $db->prepare("SELECT COUNT(*) FROM formations_inscriptions WHERE formation_id = ? AND creneau_id IS NULL");
        $waitingCountStmt->execute([$f['id']]);
        $waitingCount = (int) $waitingCountStmt->fetchColumn();

        $summaryStmt = $db->prepare("SELECT COUNT(*) AS creneaux_total, MIN(CASE WHEN date_heure >= NOW() THEN date_heure END) AS next_date FROM formations_creneaux WHERE formation_id = ?");
        $summaryStmt->execute([$f['id']]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['creneaux_total' => 0, 'next_date' => null];
        $nextDateLabel = !empty($summary['next_date']) ? date('d/m/Y H:i', strtotime($summary['next_date'])) : 'Aucune date planifiée';
    ?>
    <div class="card formation-item" data-search="<?php echo htmlspecialchars($searchBlob); ?>" data-roles="<?php echo htmlspecialchars(implode(',', $currentRoles)); ?>" data-waiting="<?php echo $waitingCount > 0 ? '1' : '0'; ?>">
        <details class="formation-details">
            <summary class="formation-summary">
                <div class="formation-main">
                    <h3><?php echo htmlspecialchars($f['titre']); ?></h3>
                    <div class="formation-sub"><?php echo htmlspecialchars($f['description'] ?? 'Sans description'); ?></div>
                    <div class="role-tags">
                        <?php if (!empty($currentRoles)): ?>
                            <?php foreach ($currentRoles as $r): ?>
                                <span class="role-tag"><?php echo htmlspecialchars($roleLabels[$r] ?? $r); ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="role-tag">Public non défini</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="summary-metas">
                    <span class="summary-pill"><?php echo (int) ($summary['creneaux_total'] ?? 0); ?> créneau(x)</span>
                    <span class="summary-pill">Intéressés: <?php echo $waitingCount; ?></span>
                    <span class="summary-pill">Prochaine date: <?php echo htmlspecialchars($nextDateLabel); ?></span>
                </div>
            </summary>
            <div class="formation-body">
        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="f_id" value="<?php echo $f['id']; ?>">
            <input type="text" name="titre" value="<?php echo htmlspecialchars($f['titre']); ?>" class="input-edit" style="font-weight:bold; width:200px;">
            <?php
                // prepare array of selected roles
                $selected = explode(',', $f['public_vise']);
            ?>
            <select name="public_vise[]" class="input-edit" multiple size="4" style="width:22%;">
                <option value="tous" <?php if(in_array('tous',$selected)) echo 'selected'; ?>>🌍 Tous</option>
                <option value="employe_magasin" <?php if(in_array('employe_magasin',$selected)) echo 'selected'; ?>>👔 Employé Magasin</option>
                <option value="employe_logistique" <?php if(in_array('employe_logistique',$selected)) echo 'selected'; ?>>🚚 Employé Logistique</option>
                <option value="etudiant" <?php if(in_array('etudiant',$selected)) echo 'selected'; ?>>🎓 Étudiant</option>
                <option value="admin" <?php if(in_array('admin',$selected)) echo 'selected'; ?>>🛠 Admin</option>
                <option value="teamcoach" <?php if(in_array('teamcoach',$selected)) echo 'selected'; ?>>🧑‍🏫 TeamCoach</option>
                <option value="mentor" <?php if(in_array('mentor',$selected)) echo 'selected'; ?>>🧑‍🎓 Mentor</option>
            </select>
            <div style="font-size:0.8rem; color:#555; margin-top:5px;">(Ctrl/Cmd+clic pour sélectionner plusieurs)</div>
            <button type="submit" name="edit_formation" class="input-edit">💾 Sauver</button>
                        <label style="display:flex;align-items:center;gap:6px;margin-top:8px;font-size:0.9em;color:#214b35;font-weight:700;cursor:pointer;">
                            <input type="checkbox" name="is_evaluation_target" value="1" <?php echo !empty($f['is_evaluation_target']) ? 'checked' : ''; ?>>
                            Formation d'évaluation caisse
                        </label>
            <a href="?delete_f=<?php echo $f['id']; ?>&csrf=<?php echo getCSRFToken(); ?>" onclick="return confirm('Supprimer ?')">🗑️</a>
        </form>

        <!-- formulaire d'ajout rapide de date pour cette thématique -->
        <form method="POST" style="margin-top:10px;">
            <?php echo csrfField(); ?>
            <input type="hidden" name="f_id" value="<?php echo $f['id']; ?>">
            <input type="datetime-local" name="date" required class="input-edit" style="width:190px;">
            <input type="text" name="duree" placeholder="Durée" class="input-edit" style="width:60px;">
            <input type="number" name="max" placeholder="Places" class="input-edit" style="width:50px;">
            <button type="submit" name="add_creneau" class="input-edit" style="background:#1976d2;color:white;cursor:pointer;">➕ Ajouter date</button>
        </form>

        <?php 
        $st_int = $db->prepare("SELECT i.id as inscription_id, u.nom, u.prenom FROM utilisateurs u JOIN formations_inscriptions i ON u.id = i.utilisateur_id WHERE i.formation_id = ? AND i.creneau_id IS NULL");
        $st_int->execute([$f['id']]);
        $interesses = $st_int->fetchAll();
        if($interesses): ?>
            <div style="margin: 10px 0; padding: 10px; background: #fff8e1; border-radius: 8px;">
                <strong>💡 Intéressés (en attente de date) :</strong> 
                <?php foreach($interesses as $i): ?>
                    <span class="badge-interest">
                        <?php echo htmlspecialchars($i['prenom']." ".$i['nom']); ?>
                        <a href="?delete_inscription=<?php echo $i['inscription_id']; ?>&csrf=<?php echo getCSRFToken(); ?>" onclick="return confirm('Retirer cette personne ?')" style="text-decoration:none;">
                            <button type="button" class="btn-delete-inscription" title="Retirer" style="background: rgba(230, 81, 0, 0.8);">×</button>
                        </a>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <table>
            <thead><tr><th>Date</th><th>Durée</th><th>Places</th><th>Inscrits</th><th>Action</th></tr></thead>
            <tbody>
                <?php 
                $creneauxSql = "SELECT * FROM formations_creneaux WHERE formation_id = ?";
                if ($filter === 'future') {
                    $creneauxSql .= " AND date_heure >= NOW()";
                } elseif ($filter === 'past') {
                    $creneauxSql .= " AND date_heure < NOW()";
                }
                $creneauxSql .= " ORDER BY date_heure ASC";

                $st = $db->prepare($creneauxSql);
                $st->execute([$f['id']]);
                while($c = $st->fetch()): 
                    $st_u = $db->prepare("SELECT i.id as inscription_id, u.nom, u.prenom FROM utilisateurs u JOIN formations_inscriptions i ON u.id = i.utilisateur_id WHERE i.creneau_id = ?");
                    $st_u->execute([$c['id']]);
                    $users = $st_u->fetchAll();
                ?>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <tr>
                        <input type="hidden" name="c_id" value="<?php echo $c['id']; ?>">
                        <td><input type="datetime-local" name="date" value="<?php echo date('Y-m-d\TH:i', strtotime($c['date_heure'])); ?>" class="input-edit"></td>
                        <td><input type="text" name="duree" value="<?php echo $c['duree']; ?>" class="input-edit" style="width:50px;"></td>
                        <td><input type="number" name="max" value="<?php echo $c['places_max']; ?>" class="input-edit" style="width:40px;"></td>
                        <td>
                            <?php foreach($users as $u): ?>
                                <span class="badge-user">
                                    <?php echo htmlspecialchars($u['prenom']." ".$u['nom']); ?>
                                    <a href="?delete_inscription=<?php echo $u['inscription_id']; ?>&csrf=<?php echo getCSRFToken(); ?>" onclick="return confirm('Retirer cette personne ?')" style="text-decoration:none;">
                                        <button type="button" class="btn-delete-inscription" title="Retirer">×</button>
                                    </a>
                                </span>
                            <?php endforeach; ?>
                        </td>
                        <td><button type="submit" name="edit_creneau">💾</button> <a href="?delete_c=<?php echo $c['id']; ?>&csrf=<?php echo getCSRFToken(); ?>">🗑️</a></td>
                    </tr>
                </form>
                <?php endwhile; ?>
            </tbody>
        </table>
            </div>
        </details>
    </div>
    <?php endforeach; ?>

    <script>
    (function () {
        const searchInput = document.getElementById('searchFormation');
        const profileFilter = document.getElementById('profileFilter');
        const waitingFilter = document.getElementById('waitingFilter');
        const resultCount = document.getElementById('resultCount');
        const emptySearch = document.getElementById('emptySearch');
        const items = Array.from(document.querySelectorAll('.formation-item'));

        function normalize(text) {
            return (text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function applyFilters() {
            const textNeedle = normalize(searchInput ? searchInput.value.trim() : '');
            const profileNeedle = profileFilter ? profileFilter.value : '';
            const mustHaveWaiting = waitingFilter ? waitingFilter.checked : false;
            let visibleCount = 0;

            items.forEach((item) => {
                const haystack = normalize(item.getAttribute('data-search') || '');
                const roles = (item.getAttribute('data-roles') || '').split(',').filter(Boolean);
                const waiting = item.getAttribute('data-waiting') === '1';

                const textOk = textNeedle === '' || haystack.includes(textNeedle);
                const profileOk = profileNeedle === '' || roles.includes(profileNeedle) || roles.includes('tous');
                const waitingOk = !mustHaveWaiting || waiting;

                const visible = textOk && profileOk && waitingOk;
                item.style.display = visible ? '' : 'none';
                if (visible) {
                    visibleCount += 1;
                }
            });

            if (resultCount) {
                resultCount.textContent = visibleCount + ' résultat(s)';
            }
            if (emptySearch) {
                emptySearch.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (profileFilter) profileFilter.addEventListener('change', applyFilters);
        if (waitingFilter) waitingFilter.addEventListener('change', applyFilters);
        applyFilters();
    })();
    </script>

    <?php
    try {
        $suggCount = (int) $db->query("SELECT COUNT(*) FROM formation_suggestions")->fetchColumn();
    } catch (Exception $e) {
        $suggCount = 0;
    }
    if ($suggCount > 0):
        $suggestions = $db->query("SELECT fs.*, u.nom, u.prenom, u.identifiant FROM formation_suggestions fs LEFT JOIN utilisateurs u ON u.id = fs.utilisateur_id ORDER BY fs.date_soumission DESC")->fetchAll();
    ?>
    <div class="card">
        <h2>💡 Suggestions de formations des collaborateurs (<?php echo $suggCount; ?>)</h2>
        <table>
            <thead><tr><th>Date</th><th>Collaborateur</th><th>Sujet</th><th>Détail</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach($suggestions as $sug): ?>
                <tr>
                    <td style="white-space:nowrap;"><?php echo date('d/m/Y', strtotime($sug['date_soumission'])); ?></td>
                    <td><?php echo htmlspecialchars(trim(($sug['prenom'] ?? '').' '.($sug['nom'] ?? '')) ?: ($sug['identifiant'] ?? '?')); ?></td>
                    <td><strong><?php echo htmlspecialchars($sug['sujet']); ?></strong></td>
                    <td style="color:#555;"><?php echo htmlspecialchars($sug['description'] ?? '—'); ?></td>
                    <td>
                        <a href="?delete_suggestion=<?php echo (int) $sug['id']; ?>&csrf=<?php echo getCSRFToken(); ?>" onclick="return confirm('Supprimer cette suggestion ?');">🗑️</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>