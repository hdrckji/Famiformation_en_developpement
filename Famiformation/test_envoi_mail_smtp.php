ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
<?php
// Script de test d'envoi d'email via SMTP IONOS avec PHPMailer
require 'vendor/autoload.php'; // Nécessite PHPMailer installé via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Config SMTP IONOS
    $mail->isSMTP();
    $mail->Host = famiGetEnv('SMTP_HOST', 'smtp.example.com');
    $mail->SMTPAuth = true;
    $mail->Username = famiGetEnv('SMTP_USER', 'utilisateur_smtp');
    $mail->Password = famiGetEnv('SMTP_PASS', '');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    // Expéditeur et destinataire
    $mail->setFrom(famiGetEnv('MAIL_FROM', 'no-reply@example.com'), famiGetEnv('MAIL_FROM_NAME', 'FamiFormation'));
    $mail->addAddress('jimmy.hendrickx@famiflora.be'); // Destinataire de test

    // Contenu
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Test SMTP IONOS depuis FamiFormation';
    $mail->Body = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
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
        . '<div class="mail-title">Test d\'envoi SMTP IONOS</div>'
        . '</div>'
        . '<div class="mail-section">Ceci est un test d\'envoi SMTP via IONOS avec PHPMailer.</div>'
        . '<div class="mail-footer">Ceci est un message automatique du système FamiFormation.<br>Pour toute question, contactez <a href="mailto:admin@famiformation.com" style="color:#2d5a37;">admin@famiformation.com</a></div>'
        . '</div></body></html>';

    $mail->send();
    echo '✅ Email envoyé avec succès via SMTP IONOS.';
} catch (Exception $e) {
    echo "❌ L'envoi a échoué. Erreur : {$mail->ErrorInfo}";
}
