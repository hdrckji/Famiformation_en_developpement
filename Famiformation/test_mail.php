<?php
// fichier de test pour vérifier l'envoi d'emails via la fonction SMTP
require_once 'config.php';

$to = famiGetEnv('MAIL_ADMIN','admin@famiformation.be');
$from = famiGetEnv('MAIL_FROM','admin@famiformation.com');
$from_name = famiGetEnv('MAIL_FROM_NAME','FamiFormation');
$subject = 'Test envoi mail FamiFormation';
$body = "Bonjour,\n\nCeci est un message de test envoyé depuis FamiFormation.\n";

if (sendEmailSMTP($to, $subject, $body, $from, $from_name)) {
    echo "✅ Email de test envoyé à $to. Vérifiez votre boîte et les spams.";
} else {
    echo "❌ Échec de l'envoi du mail de test. Le transport mail() pourrait ne pas fonctionner.";
}
