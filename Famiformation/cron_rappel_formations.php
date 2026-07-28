<?php
/**
 * CRON : Envoie un rappel aux inscrits la veille de leur formation
 * À exécuter tous les jours (via CRON IONOS)
 * Commande CRON : 0 8 * * * php /chemin/vers/cron_rappel_formations.php
 * (S'exécute à 08:00 chaque jour)
 */

require_once 'config.php';
require_once 'includes/functions.php';

// Sécurité CRON : vérifier qu'on appelle depuis CLI ou avec token
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== getenv('CRON_TOKEN'))) {
    die("Accès non autorisé.");
}

try {
    // 1. Récupérer les formations du lendemain (date_heure BETWEEN TOMORROW 00:00 ET TOMORROW 23:59)
    $query = "SELECT 
                fs.titre as formation,
                fs.description,
                fc.id as creneau_id,
                fc.date_heure as date,
                u.id as user_id,
                u.nom,
                u.prenom,
                u.email
              FROM formations_creneaux fc
              JOIN formations_sessions fs ON fc.formation_id = fs.id
              JOIN formations_inscriptions fi ON fc.id = fi.creneau_id
              JOIN utilisateurs u ON fi.utilisateur_id = u.id
              WHERE DATE(fc.date_heure) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                                AND u.role = 'etudiant'
                AND u.email IS NOT NULL AND u.email != ''
              ORDER BY fc.date_heure ASC";

    $stmt = $db->query($query);
    $inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Si pas d'inscriptions, ne rien faire
    if (empty($inscriptions)) {
        exit("Aucun rappel à envoyer pour demain.\n");
    }

    // 3. Envoyer un mail à chaque inscrit
    $from = famiGetEnv('MAIL_FROM', 'admin@famiformation.com');
    $from_name = famiGetEnv('MAIL_FROM_NAME', 'FamiFormation');

    $sent = 0;
    foreach ($inscriptions as $insc) {
        $to = $insc['email'];
        $subject = "⏰ Rappel : Formation demain - " . $insc['formation'];
        $body = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
            . '<style>'
            . 'body { font-family: Open Sans, Arial, sans-serif; background: #f4f7f6; color: #2d5a37; margin:0; padding:0; }'
            . '.mail-container { background: #fff; border-radius: 18px; box-shadow: 0 8px 32px rgba(45,90,55,0.10); max-width: 600px; margin: 40px auto; padding: 36px; }'
            . '.mail-header { text-align:center; margin-bottom: 28px; }'
            . '.mail-title { font-size: 1.7rem; font-weight: bold; color: #2d5a37; margin-bottom: 10px; }'
            . '.mail-section { margin-bottom: 20px; font-size:1.08em; }'
            . '.mail-label { font-weight: bold; color: #1d6f42; }'
            . '.mail-footer { margin-top: 36px; font-size: 0.97rem; color: #666; text-align:center; }'
            . '</style></head><body>'
            . '<div class="mail-container">'
            . '<div class="mail-header">'
            . '<img src="https://famiformation.com/logo.png" alt="Logo FamiFormation" style="max-width:120px; margin-bottom:12px;">'
            . '<div class="mail-title">Rappel : Formation prévue demain</div>'
            . '</div>'
            . '<div class="mail-section">Bonjour ' . htmlspecialchars($insc['prenom']) . ' ' . htmlspecialchars($insc['nom']) . ',</div>'
            . '<div class="mail-section">Vous êtes inscrit à la formation <span class="mail-label">' . htmlspecialchars($insc['formation']) . '</span>.</div>'
            . '<div class="mail-section"><span class="mail-label">Description :</span> ' . htmlspecialchars($insc['description']) . '</div>'
            . '<div class="mail-section"><span class="mail-label">Date et heure :</span> ' . date('d/m/Y H:i', strtotime($insc['date'])) . '</div>'
            . '<div class="mail-footer">Ceci est un rappel automatique du système FamiFormation.<br>Pour toute question, contactez <a href="mailto:admin@famiformation.com" style="color:#2d5a37;">admin@famiformation.com</a></div>'
            . '</div></body></html>';

        // ENVOI VIA PHPMailer SMTP IONOS
        require_once __DIR__ . '/vendor/autoload.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.ionos.fr';
            $mail->SMTPAuth = true;
            $mail->Username = $from;
            $mail->Password = famiGetEnv('SMTP_PASS', '');
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom($from, $from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            $sent++;
        } catch (Exception $e) {
            error_log("[FamiFormation] Erreur envoi rappel à $to : " . $e->getMessage());
        }
    }
    echo "✅ Rappels envoyés : $sent\n";
    exit(0);

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
?>
