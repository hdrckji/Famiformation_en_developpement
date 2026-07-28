<?php
require_once 'config.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { die("Accès refusé."); }

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Liste_Emargement_Formations.xls");

// Récupération des formations et de leurs créneaux
$formations = $db->query("SELECT * FROM formations_sessions ORDER BY titre ASC")->fetchAll();

echo '<style> .title { background: #2d5a37; color: white; font-weight: bold; } .date-row { background: #f2f2f2; font-weight: bold; } </style>';
echo '<table border="1">';

foreach ($formations as $f) {
    // 1. Titre de la thématique
    echo '<tr><th colspan="3" class="title">' . strtoupper(htmlspecialchars($f['titre'])) . '</th></tr>';
    
    $creneaux = $db->prepare("SELECT * FROM formations_creneaux WHERE formation_id = ? ORDER BY date_heure ASC");
    $creneaux->execute([$f['id']]);
    
    while ($c = $creneaux->fetch()) {
        // 2. Ligne de la date
        echo '<tr><td colspan="3" class="date-row">DATE : ' . date('d/m/Y à H:i', strtotime($c['date_heure'])) . ' (Durée : ' . $c['duree'] . ')</td></tr>';
        echo '<tr><th>NOM</th><th>PRENOM</th><th>SIGNATURE</th></tr>';
        
        // 3. Participants
        $ins = $db->prepare("SELECT u.nom, u.prenom FROM formations_inscriptions fi JOIN utilisateurs u ON fi.utilisateur_id = u.id WHERE fi.creneau_id = ?");
        $ins->execute([$c['id']]);
        $participants = $ins->fetchAll();
        
        if (empty($participants)) {
            echo '<tr><td colspan="3" style="color:gray;">Aucun inscrit</td></tr>';
        } else {
            foreach ($participants as $p) {
                echo '<tr><td>' . htmlspecialchars($p['nom']) . '</td><td>' . htmlspecialchars($p['prenom']) . '</td><td></td></tr>';
            }
        }
        echo '<tr><td colspan="3" style="border:none; height:20px;"></td></tr>'; // Espace entre les dates
    }
}
echo '</table>';
exit();