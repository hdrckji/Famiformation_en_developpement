<?php
/**
 * CRON : Envoie un rapport des formations du jour
 * À exécuter tous les jours (via CRON IONOS)
 * Commande CRON : 0 8 * * * php /chemin/vers/cron_formations_du_jour.php
 * (S'exécute à 08:00 chaque jour)
 */

require_once 'config.php';

// Sécurité CRON : vérifier qu'on appelle depuis CLI ou avec token
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== getenv('CRON_TOKEN'))) {
    die("Accès non autorisé.");
}

exit("Envoi désactivé : ce rapport ne concerne pas les étudiants.\n");

try {
    // 1. Récupérer les formations du jour (date_heure BETWEEN TODAY 00:00 ET TODAY 23:59)
    $query = "SELECT 
                fs.id as formation_id,
                fs.titre as formation,
                fs.public_vise,
                fc.id as creneau_id,
                fc.date_heure as date,
                fc.duree,
                fc.places_max,
                COUNT(fi.id) as nb_inscrits
              FROM formations_creneaux fc
              JOIN formations_sessions fs ON fc.formation_id = fs.id
              LEFT JOIN formations_inscriptions fi ON fc.id = fi.creneau_id
              WHERE DATE(fc.date_heure) = CURDATE()
              GROUP BY fc.id
              ORDER BY fc.date_heure ASC";

    $stmt = $db->query($query);
    $formations_du_jour = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Si pas de formations, ne rien faire
    if (empty($formations_du_jour)) {
        exit("Aucune formation prévue aujourd'hui.\n");
    }

    // 3. Filtrer uniquement les formations NON-ÉTUDIANT (sauf "etudiant")
    $formations_filtered = array_filter($formations_du_jour, function($f) {
        return $f['public_vise'] !== 'etudiant';
    });

    if (empty($formations_filtered)) {
        exit("Aucune formation non-étudiant prévue aujourd'hui.\n");
    }

    // 4. Récupérer les infos de tous les inscrits pour chaque créneau
    $formations_detail = [];
    foreach ($formations_filtered as $form) {
        $inscStmt = $db->prepare(
            "SELECT u.nom, u.prenom, u.identifiant 
             FROM formations_inscriptions fi 
             JOIN utilisateurs u ON fi.utilisateur_id = u.id 
             WHERE fi.creneau_id = ? 
             ORDER BY u.nom ASC"
        );
        $inscStmt->execute([$form['creneau_id']]);
        $form['inscrits'] = $inscStmt->fetchAll();
        $formations_detail[] = $form;
    }

    // 5. Préparer le contenu de l'email (HTML stylé)
    $to = famiGetEnv('MAIL_ADMIN', 'admin@famiformation.be');
    $from = famiGetEnv('MAIL_FROM', 'admin@famiformation.com');
    $from_name = famiGetEnv('MAIL_FROM_NAME', 'FamiFormation');

    $subject = "📋 Rapport des formations du jour - " . date('d/m/Y');

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
        . '<div class="mail-title">Rapport des formations du jour</div>'
        . '<div class="mail-section">Voici le résumé des formations prévues aujourd&#39;hui (' . date('d/m/Y') . ') :</div>'
        . '</div>';

    foreach ($formations_detail as $idx => $form) {
        $body .= '<div class="formation-card">';
        $body .= '<div class="formation-title">' . htmlspecialchars($form['formation']) . '</div>';
        $body .= '<div class="formation-info"><strong>Heure :</strong> ' . date('H:i', strtotime($form['date'])) . ' | <strong>Durée :</strong> ' . $form['duree'] . ' min | <strong>Profils ciblés :</strong> ' . htmlspecialchars($form['public_vise']) . ' | <strong>Places totales :</strong> ' . $form['places_max'] . '</div>';
        $body .= '<div class="formation-info"><strong>Inscrits :</strong> ' . count($form['inscrits']) . '</div>';
        if (!empty($form['inscrits'])) {
            $body .= '<table class="participants-table"><thead><tr><th>Nom/Prénom</th><th>Identifiant</th></tr></thead><tbody>';
            foreach ($form['inscrits'] as $inscrit) {
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

    // 6. Envoyer l'email (HTML)
    require_once __DIR__ . '/vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = famiGetEnv('SMTP_HOST', 'smtp.ionos.fr');
        $mail->SMTPAuth = true;
        $mail->Username = $from;
        $mail->Password = famiGetEnv('SMTP_PASS', '');
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = famiGetEnv('SMTP_PORT', 465);

        $mail->setFrom($from, $from_name);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        echo "✅ Email envoyé avec succès à $to\n";
        echo "Formations du jour : " . count($formations_detail) . "\n";
        exit(0);
    } catch (Exception $e) {
        error_log("[FamiFormation] Erreur envoi rapport du jour à $to : " . $e->getMessage());
        echo "❌ Erreur lors de l'envoi de l'email\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
?>
