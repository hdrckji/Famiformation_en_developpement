<?php
// ============================================================
// inscription.php — Reçoit "prénom + nom" et prévient l'équipe FamiFormation
// qu'une personne est intéressée pour créer du contenu.
//
// Envoi en SMTP AUTHENTIFIÉ (compte admin@famiformation.com) pour que l'e-mail
// parte bien de cette adresse — et non de l'expéditeur par défaut du webspace
// IONOS (sh-...@eu.hosting-webspace.io) que la fonction mail() imposait.
//
// Les identifiants sont lus dans le fichier .env (à remplir), jamais en dur.
// ============================================================

// ---------- Chargement du .env local ----------
function fvEnv($cle, $defaut = '')
{
    // 1) Variable d'environnement (Railway) en PRIORITÉ : pas de secret dans le
    //    dépôt, on configure SMTP_HOST/USER/PASS… dans les variables du service.
    $env = getenv($cle);
    if ($env !== false && $env !== '') { return $env; }
    if (isset($_ENV[$cle]) && $_ENV[$cle] !== '') { return $_ENV[$cle]; }
    if (isset($_SERVER[$cle]) && $_SERVER[$cle] !== '') { return $_SERVER[$cle]; }

    // 2) Sinon, fichier .env local (puis .env.example en secours).
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        // Lit .env en priorité, sinon .env.example (secours si l'utilisateur n'a rempli que l'exemple).
        $chemin = is_file(__DIR__ . '/.env') ? __DIR__ . '/.env' : __DIR__ . '/.env.example';
        if (is_file($chemin)) {
            foreach (file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
                $ligne = trim($ligne);
                if ($ligne === '' || $ligne[0] === '#' || strpos($ligne, '=') === false) { continue; }
                list($k, $v) = explode('=', $ligne, 2);
                $k = trim($k); $v = trim($v);
                if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
                    $v = substr($v, 1, -1);
                }
                $vars[$k] = $v;
            }
        }
    }
    return isset($vars[$cle]) && $vars[$cle] !== '' ? $vars[$cle] : $defaut;
}

// ---------- Envoi SMTP (socket SSL/TLS, sans dépendance) ----------
function fvLireSmtp($fp)
{
    $donnees = '';
    while (($ligne = fgets($fp, 515)) !== false) {
        $donnees .= $ligne;
        // La dernière ligne d'une réponse a un espace en 4e position (ex "250 OK").
        if (isset($ligne[3]) && $ligne[3] === ' ') { break; }
    }
    return $donnees;
}

function fvSmtpEnvoyer($to, $sujet, $corps, &$erreur)
{
    $host   = fvEnv('SMTP_HOST');
    $port   = (int) fvEnv('SMTP_PORT', '465');
    $user   = fvEnv('SMTP_USER');
    $pass   = fvEnv('SMTP_PASS');
    $secure = strtolower(fvEnv('SMTP_SECURE', 'ssl'));
    $from   = fvEnv('MAIL_FROM', $user);
    $fromNm = fvEnv('MAIL_FROM_NAME', 'FamiFormation');

    if ($host === '' || $user === '' || $pass === '') {
        $erreur = 'Configuration SMTP incomplète (.env).';
        return false;
    }

    $transport = ($secure === 'ssl' || $secure === 'smtps') ? 'ssl://' : 'tcp://';
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        $erreur = 'Connexion SMTP impossible : ' . trim($errstr ?: 'erreur inconnue');
        return false;
    }
    stream_set_timeout($fp, 20);

    $cmd = function ($commande, $attendu) use ($fp, &$erreur) {
        if ($commande !== null) { fwrite($fp, $commande . "\r\n"); }
        $rep = fvLireSmtp($fp);
        $code = (int) substr($rep, 0, 3);
        if ($code !== $attendu) {
            $erreur = 'SMTP: réponse ' . $code . ' (' . trim($rep) . ')';
            return false;
        }
        return true;
    };

    $ok = true;
    $ok = $ok && $cmd(null, 220);
    $ok = $ok && $cmd('EHLO famiformation.com', 250);
    if ($ok && $secure === 'tls') {
        $ok = $ok && $cmd('STARTTLS', 220);
        if ($ok) { @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $ok = $cmd('EHLO famiformation.com', 250); }
    }
    $ok = $ok && $cmd('AUTH LOGIN', 334);
    $ok = $ok && $cmd(base64_encode($user), 334);
    $ok = $ok && $cmd(base64_encode($pass), 235);
    $ok = $ok && $cmd('MAIL FROM:<' . $from . '>', 250);
    $ok = $ok && $cmd('RCPT TO:<' . $to . '>', 250);
    $ok = $ok && $cmd('DATA', 354);

    if ($ok) {
        $entetes =
            'From: ' . '=?UTF-8?B?' . base64_encode($fromNm) . '?=' . ' <' . $from . '>' . "\r\n" .
            'To: <' . $to . '>' . "\r\n" .
            'Reply-To: <' . $from . '>' . "\r\n" .
            'Subject: ' . '=?UTF-8?B?' . base64_encode($sujet) . '?=' . "\r\n" .
            'MIME-Version: 1.0' . "\r\n" .
            'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
            'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
        // Un point seul en début de ligne doit être doublé (règle SMTP).
        $corpsSafe = preg_replace('/^\./m', '..', $corps);
        fwrite($fp, $entetes . $corpsSafe . "\r\n.\r\n");
        $ok = $cmd(null, 250);
    }

    @fwrite($fp, "QUIT\r\n");
    @fclose($fp);
    return $ok;
}

// ---------- Récupération + nettoyage des champs ----------
function champ($cle)
{
    $v = isset($_POST[$cle]) ? (string) $_POST[$cle] : '';
    $v = trim(preg_replace('/[\r\n]+/', ' ', $v));   // pas de retour à la ligne
    return mb_substr($v, 0, 80);
}

$prenom = champ('prenom');
$nom = champ('nom');
$L = (isset($_POST['lang']) && $_POST['lang'] === 'nl') ? 'nl' : 'fr';   // langue d'affichage

// ⚠️ Aucune adresse de personne en dur : elle vit dans les variables Railway
// (MAIL_TO). Sans elle, on ne fait pas semblant d'enregistrer — voir plus bas.
$destinataire = trim((string) fvEnv('MAIL_TO', ''));

// À qui la page invite à s'adresser. Réglage, jamais une constante du code :
// vide = la phrase n'est pas affichée du tout.
$relaisAffiche = trim((string) fvEnv('CONTACT_VOLONTAIRE', ''));
$envoye = false;
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($prenom === '' || $nom === '') {
        $erreur = $L === 'nl' ? 'Vul je voornaam en je naam in.' : 'Merci d\'indiquer ton prénom et ton nom.';
    } elseif ($destinataire === '') {
        // Sans adresse configurée, on NE FAIT PAS SEMBLANT : dire « merci, on
        // te recontacte » alors que le message ne part nulle part est la pire
        // des réponses possibles pour quelqu'un qui se propose.
        $erreur = $L === 'nl'
            ? 'De inschrijving is tijdelijk niet beschikbaar. Probeer het later opnieuw.'
            : "L'inscription est momentanément indisponible. Réessaie plus tard.";
    } else {
        // ⚠️ Aucun nom de personne dans ce message : à qui transmettre est un
        // réglage (CONTACT_VOLONTAIRE), pas une constante du code. La
        // phrase disparaît si la variable n'est pas posée.
        $relais = $relaisAffiche;

        $sujet = 'Nouvelle personne intéressée — créateur de contenu FamiFormation';
        $corps =
            "Bonjour,\r\n\r\n" .
            $prenom . ' ' . $nom . " est intéressé(e) pour créer du contenu (fiches PDF / vidéos) pour FamiFormation.\r\n\r\n" .
            'Merci de le/la recontacter' . ($relais !== '' ? ' (ou de le transmettre à ' . $relais . ')' : '') . ".\r\n\r\n" .
            "— Envoyé automatiquement depuis le site /volontaire";
        $envoye = fvSmtpEnvoyer($destinataire, $sujet, $corps, $erreur);
        if (!$envoye && $erreur === '') {
            $erreur = "L'envoi n'a pas fonctionné.";
        }
    }
}

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="<?php echo $L; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $L === 'nl' ? 'Inschrijving' : 'Inscription'; ?> — FamiFormation</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--green:#2e7d46;--deep:#1f5c34;--mint:#eef6ec;--mint-line:#d7e8d2;--ink:#243027;--muted:#5c6f60;--bg:#eef4ea;}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif;}
body{background:var(--bg);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.card{background:#fff;border:1px solid var(--mint-line);border-radius:24px;padding:40px 32px;max-width:460px;width:100%;text-align:center;box-shadow:0 24px 60px rgba(20,55,38,.16);}
.logo{height:44px;margin:0 auto 18px;}
.ic{width:74px;height:74px;margin:0 auto 18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.2rem;}
.ic.ok{background:#e4f4dc;color:var(--green);}
.ic.ko{background:#fbeee6;color:#b05a2b;}
h1{color:var(--deep);font-size:1.5rem;font-weight:900;line-height:1.15;}
p{margin-top:12px;color:#3f4a41;font-size:1.02rem;line-height:1.55;}
p b{color:var(--deep);}
.back{display:inline-flex;align-items:center;gap:8px;margin-top:26px;text-decoration:none;font-weight:800;background:linear-gradient(135deg,var(--green),var(--deep));color:#fff;padding:14px 24px;border-radius:14px;box-shadow:0 12px 26px rgba(31,92,52,.28);}
</style>
</head>
<body>
  <div class="card">
    <img class="logo" src="famiflora-logo.png" alt="Famiflora">
    <?php if ($envoye): ?>
      <div class="ic ok">✅</div>
      <h1><?php echo $L === 'nl' ? 'Bedankt' : 'Merci'; ?> <?php echo h($prenom); ?>&nbsp;!</h1>
      <p><?php echo $L === 'nl'
        ? 'Je interesse is doorgegeven aan het <b>FamiFormation</b>-team. We nemen binnenkort contact met je op.'
        : 'Ton intérêt a bien été transmis à l\'équipe <b>FamiFormation</b>. On te recontactera bientôt.'; ?></p>
      <?php // ⚠️ Aucun nom de personne écrit dans la page : à qui s'adresser est
            // un réglage (CONTACT_VOLONTAIRE). Non renseigné, la phrase
            // disparaît — mieux vaut ne rien dire qu'envoyer quelqu'un voir une
            // personne qui a changé de poste. ?>
      <?php if ($relaisAffiche !== ''): ?>
        <p style="font-size:.94rem;color:var(--muted);"><?php echo $L === 'nl'
          ? 'Je kan ook langsgaan bij <b>' . h($relaisAffiche) . '</b> wanneer je wil.'
          : 'Tu peux aussi passer voir <b>' . h($relaisAffiche) . '</b> quand tu veux.'; ?></p>
      <?php endif; ?>
    <?php else: ?>
      <div class="ic ko">✋</div>
      <h1><?php echo $L === 'nl' ? 'Oeps…' : 'Oups…'; ?></h1>
      <p><?php echo h($erreur !== '' ? $erreur : ($L === 'nl' ? 'Er ontbreekt informatie.' : 'Une information manque.')); ?></p>
      <?php if ($relaisAffiche !== ''): ?>
        <p style="font-size:.94rem;color:var(--muted);"><?php echo $L === 'nl'
          ? 'Geen paniek: ga rechtstreeks langs bij <b>' . h($relaisAffiche) . '</b>.'
          : 'Pas de panique : passe directement voir <b>' . h($relaisAffiche) . '</b>.'; ?></p>
      <?php endif; ?>
    <?php endif; ?>
    <a class="back" href="index.html">← <?php echo $L === 'nl' ? 'Terug naar de site' : 'Retour au site'; ?></a>
  </div>
</body>
</html>
