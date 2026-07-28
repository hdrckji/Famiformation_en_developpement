<?php
// Test d'envoi d'email avec l'adresse configurée dans .env
require_once 'config.php';

$to = famiGetEnv('MAIL_ADMIN', 'admin@famiformation.com');
$from = 'admin@famiformation.com';
$from_name = 'FamiFormation';
$subject = 'Test d\'envoi depuis FamiFormation';
$body = "Bonjour,\n\nCeci est un test d'envoi d'email depuis le site FamiFormation.\n\nSi vous recevez ce message, la configuration d'expéditeur fonctionne !\n\nCordialement,\nLe système FamiFormation.";

$headers = "From: {$from_name} <{$from}>\r\n";
$headers .= "Reply-To: {$from}\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

if (mail($to, $subject, $body, $headers)) {
    echo "✅ Email envoyé avec succès à $to depuis $from.";
} else {
    echo "❌ Échec de l'envoi du mail. Vérifiez la configuration serveur et l'adresse d'expéditeur.";
}
