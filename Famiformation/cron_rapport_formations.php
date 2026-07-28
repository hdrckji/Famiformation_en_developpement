<?php
require_once 'config.php';

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== famiGetEnv('CRON_TOKEN', ''))) {
    die("Accès non autorisé.");
}

exit("Envoi désactivé : ce rapport ne concerne pas directement les étudiants.\n");

// 1. Récupération des créneaux de formations futures
$query = "SELECT fc.id as creneau_id, fs.titre as formation, fc.date_heure, fc.places_max, fs.description FROM formations_creneaux fc JOIN formations_sessions fs ON fc.formation_id = fs.id WHERE fc.date_heure >= NOW() ORDER BY fc.date_heure ASC";
$stmt = $db->query($query);
$creneaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Récupérer les inscrits pour chaque créneau
foreach ($creneaux as &$creneau) {
    $inscStmt = $db->prepare("SELECT u.nom, u.prenom, u.identifiant FROM formations_inscriptions fi JOIN utilisateurs u ON fi.utilisateur_id = u.id WHERE fi.creneau_id = ? ORDER BY u.nom ASC");
    $inscStmt->execute([$creneau['creneau_id']]);
    $creneau['inscrits'] = $inscStmt->fetchAll(PDO::FETCH_ASSOC);
    $creneau['nb_inscrits'] = count($creneau['inscrits']);
    $creneau['places_restantes'] = $creneau['places_max'] - $creneau['nb_inscrits'];
}

// 3. Préparer le mail HTML
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.ionos.fr';
    $mail->SMTPAuth = true;
    $mail->Username = famiGetEnv('MAIL_FROM', 'admin@famiformation.com');
    $mail->Password = famiGetEnv('SMTP_PASS', '');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom(famiGetEnv('MAIL_FROM', 'admin@famiformation.com'), 'FamiFormation');
    $mail->addAddress(famiGetEnv('MAIL_ADMIN', 'admin@famiformation.com'));
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = '📋 Rapport des formations futures - ' . date('d/m/Y');

    $body = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
        . '<style>'
        . 'body { font-family: Open Sans, Arial, sans-serif; background: #f4f7f6; color: #2d5a37; margin:0; padding:0; }'
        . '.mail-container { background: #fff; border-radius: 18px; box-shadow: 0 8px 32px rgba(45,90,55,0.10); max-width: 700px; margin: 40px auto; padding: 36px; }'
        . '.mail-header { text-align:center; margin-bottom: 28px; }'
        . '.mail-title { font-size: 1.7rem; font-weight: bold; color: #2d5a37; margin-bottom: 10px; }'
        . '.mail-section { margin-bottom: 20px; font-size:1.08em; }'
        . '.formation-card { background: #e8f5e9; border-radius: 12px; padding: 18px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(45,90,55,0.07); }'
        . '.formation-title { font-size: 1.15em; font-weight: bold; color: #1d6f42; margin-bottom: 6px; }'
        . '.formation-info { margin-bottom: 8px; }'
        . '.participants-table { width: 100%; border-collapse: collapse; margin-top: 10px; }'
        . '.participants-table th, .participants-table td { padding: 8px 10px; border-bottom: 1px solid #c8e6c9; font-size: 0.97em; }'
        . '.participants-table th { background: #c8e6c9; color: #1d6f42; text-align: left; }'
        . '.no-participant { color: #d9302d; font-weight: bold; }'
        . '.mail-footer { margin-top: 36px; font-size: 0.97rem; color: #666; text-align:center; }'
        . '</style></head><body>'
        . '<div class="mail-container">'
        . '<div class="mail-header">'
        . '<img src="https://famiformation.com/logo.png" alt="Logo FamiFormation" style="max-width:120px; margin-bottom:12px;">'
        . '<div class="mail-title">Rapport des formations futures</div>'
        . '<div class="mail-section">Voici la liste des prochaines formations planifiées, avec les inscrits et les places restantes :</div>'
        . '</div>';

    foreach ($creneaux as $creneau) {
        $body .= '<div class="formation-card">';
        $body .= '<div class="formation-title">' . htmlspecialchars($creneau['formation']) . '</div>';
        $body .= '<div class="formation-info"><strong>Date :</strong> ' . date('d/m/Y H:i', strtotime($creneau['date_heure'])) . ' | <strong>Places totales :</strong> ' . $creneau['places_max'] . ' | <strong>Places restantes :</strong> ' . $creneau['places_restantes'] . '</div>';
        $body .= '<div class="formation-info"><strong>Description :</strong> ' . htmlspecialchars($creneau['description']) . '</div>';
        $body .= '<div class="formation-info"><strong>Inscrits (' . $creneau['nb_inscrits'] . ') :</strong></div>';
        if ($creneau['nb_inscrits'] > 0) {
            $body .= '<table class="participants-table"><thead><tr><th>Nom/Prénom</th><th>Identifiant</th></tr></thead><tbody>';
            foreach ($creneau['inscrits'] as $inscrit) {
                $body .= '<tr><td>' . htmlspecialchars($inscrit['prenom']) . ' ' . htmlspecialchars($inscrit['nom']) . '</td><td>' . htmlspecialchars($inscrit['identifiant']) . '</td></tr>';
            }
            $body .= '</tbody></table>';
        } else {
            $body .= '<div class="no-participant">⚠️ Aucun participant inscrit.</div>';
        }
        $body .= '</div>';
    }

    $body .= '<div class="mail-footer">Report généré : ' . date('d/m/Y à H:i:s') . '<br>Plateforme FamiFormation</div>';
    $body .= '</div></body></html>';

    $mail->Body = $body;
    $mail->send();
    echo "Rapport envoyé avec succès";
} catch (Exception $e) {
    echo "Erreur lors de l'envoi : {$mail->ErrorInfo}";
}
?>