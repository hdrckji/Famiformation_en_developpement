<?php
require_once __DIR__ . '/../public/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.ionos.fr';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USER') ?: 'admin@famiformation.com';
    $mail->Password = getenv('SMTP_PASS') ?: '';
    $mail->SMTPSecure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = getenv('SMTP_PORT') ?: 465;
    $mail->setFrom(getenv('MAIL_FROM') ?: 'admin@famiformation.com', getenv('MAIL_FROM_NAME') ?: 'FamiFormation');
    $mail->addAddress(getenv('MAIL_ADMIN') ?: 'jimmy.hendrickx@famiflora.be');
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Test SMTP FamiFormation';
    $mail->Body = '<b>Test SMTP réussi !</b><br>Si vous voyez ce message, la connexion SMTP fonctionne.';
    $mail->AltBody = 'Test SMTP réussi !';
    $mail->send();
    echo '✅ Mail envoyé avec succès.';
} catch (Exception $e) {
    echo '❌ Erreur lors de l\'envoi : ' . $mail->ErrorInfo;
}
