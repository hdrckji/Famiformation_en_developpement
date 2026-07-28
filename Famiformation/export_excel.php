<?php
require_once 'config.php';

// On empêche tout affichage d'erreur qui corromprait le fichier Excel
ini_set('display_errors', 0);
error_reporting(0);

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'employe_magasin' && $_SESSION['role'] !== 'teamcoach' && $_SESSION['role'] !== 'mentor')) { 
    die("Accès refusé."); 
}

// Nettoyage de sortie pour éviter les espaces parasites
if (ob_get_length()) ob_clean();

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Rapport_Formations_Famiflora.xls");

// Requête SQL des utilisateurs
$query = "SELECT u.nom, u.prenom, u.identifiant, u.id as uid, COALESCE(u.temps_connecte, 0) as temps_connecte 
          FROM utilisateurs u 
          ORDER BY u.nom ASC";
$users = $db->query($query)->fetchAll();

$themes = [
    'quiz_onboarding_video' => 'Onb. Vidéo',
    'quiz_onboarding_pdf' => 'Onb. PDF',
    'quiz_caisse' => 'Caisse Vidéo',
    'quiz_pdf' => 'Caisse PDF',
    'quiz_barbecue_weber' => 'Bbq Weber',
    'quiz_barbecue2' => 'Bbq B/N',
    'quiz_spa' => 'Spa',
    'quiz_marketing' => 'Marketing',
    'quiz_mentor' => 'Parrain',
    'quiz_becosoft' => 'Beco Rech.',
    'quiz_gazon' => 'Beco Gazon',
    'quiz_bon_vente' => 'Beco Bon',
    'quiz_vente_flash' => 'Beco Flash',
    'quiz_plantes_hiver' => 'Plantes Hiver',
    'quiz_fleurs_artificielles' => 'Fleurs Artif. Vid',
    'quiz_fleurs_artificielles_pdf' => 'Fleurs Artif. PDF',
    'quiz_garden' => 'Garden',
    'quiz_rongeur' => 'Rongeur',
    'quiz_changement_saison' => 'Ch. Saison'
];

echo '<table border="1">';
echo '  <tr>
            <th rowspan="2" style="background:#2d5a37; color:white;">Nom</th>
            <th rowspan="2" style="background:#2d5a37; color:white;">Prénom</th>
            <th rowspan="2" style="background:#2d5a37; color:white; border-right:3px solid #1d6f42;">Temps Passé</th>'; 
            foreach($themes as $t) { echo "<th colspan='2' style='background:#f4f7f6;'>$t</th>"; }
echo '  </tr><tr>';
            foreach($themes as $t) { echo "<th style='font-size:10px;'>Note</th><th style='font-size:10px;'>Date</th>"; }
echo '  </tr>';

foreach ($users as $u) {
    $sec = (int)$u['temps_connecte'];
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    $temps_formate = ($h > 0) ? $h."h ".$m."m" : $m." min";

    echo '<tr>';
    echo '<td style="text-align:left;">' . htmlspecialchars($u['nom'] ?? '') . '</td>';
    echo '<td style="text-align:left;">' . htmlspecialchars($u['prenom'] ?? '') . '</td>';
    echo '<td style="font-weight:bold; background:#f0f7f1; border-right:2px solid #2d5a37;">' . $temps_formate . '</td>';
    
    foreach($themes as $slug => $label) {
        $stmt = $db->prepare("SELECT score, date_tentative, created_at FROM statistiques WHERE utilisateur_id = ? AND nom_page = ? AND score IS NOT NULL ORDER BY id ASC LIMIT 1");
        $stmt->execute([$u['uid'], $slug]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($res) {
            $date_brute = !empty($res['date_tentative']) ? $res['date_tentative'] : $res['created_at'];
            $date = ($date_brute) ? date('d/m/y', strtotime($date_brute)) : '-';
            
            $score = $res['score'];
            $color = ($score >= 8) ? '#2d5a37' : (($score >= 5) ? '#d9a406' : '#a83232');
            
            // MODIFICATION ICI : mso-number-format:"\@" force Excel à traiter la cellule comme du texte
            // On ajoute aussi un espace devant pour être sûr qu'Excel ne convertisse rien
            echo '<td style="text-align:center; font-weight:bold; color:'.$color.'; mso-number-format:\'\@\';">' . $score . ' / 10</td>';
            echo '<td style="text-align:center; font-size:10px; color:#666;">' . $date . '</td>';
        } else {
            echo '<td style="text-align:center; color:#ccc;">-</td>';
            echo '<td style="text-align:center; color:#ccc;">-</td>';
        }
    }
    echo '</tr>';
}
echo '</table>';