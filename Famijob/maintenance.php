<?php
// ============================================================
// maintenance.php — MODE MAINTENANCE DE FAMIJOB.
//
// ⚠️ POURQUOI CE FICHIER EXISTE, ET PAS UN .htaccess
// FamiJob ne tourne PAS sous Apache. Le service Railway lance FrankenPHP
// (voir le Dockerfile), c'est-à-dire Caddy : les fichiers .htaccess n'y sont
// jamais lus. Un .htaccess déposé ici ne ferait donc absolument rien — il
// serait même renvoyé en 403 par la règle @dotfiles du Caddyfile.
// La coupure doit être faite en PHP : c'est ce que fait ce fichier.
//
// COMMENT IL S'ACTIVE
// Il est chargé par config.php, que TOUTES les pages de FamiJob incluent
// (login, index, horaires, notifications…). Une seule ligne à ajouter dans
// config.php et l'ensemble du dossier est couvert, sans toucher aux pages.
// ============================================================

// ─── 1. L'INTERRUPTEUR ──────────────────────────────────────────────────────
// Deux façons de l'allumer, au choix :
//
//   • VARIABLE RAILWAY   FAMIJOB_MAINTENANCE = 1
//     La plus pratique : bascule immédiate depuis le tableau de bord, sans
//     rien pousser. Pour rouvrir le site, on supprime la variable.
//
//   • FICHIER            maintenance-on   (déposé à côté de ce script)
//     Utile si on préfère tout piloter par le dépôt. Pour rouvrir, on
//     supprime le fichier et on repousse.
//
// Tant qu'aucun des deux n'est présent, ce fichier ne fait RIEN : le site
// fonctionne normalement. C'est volontaire — un fichier de maintenance ne
// doit jamais pouvoir couper le site par simple présence dans le dossier.
$maintenanceParVariable = in_array(
    strtolower((string) (getenv('FAMIJOB_MAINTENANCE') ?: ($_SERVER['FAMIJOB_MAINTENANCE'] ?? ''))),
    ['1', 'on', 'true', 'oui'],
    true
);
$maintenanceParFichier = is_file(__DIR__ . '/maintenance-on');

if (!$maintenanceParVariable && !$maintenanceParFichier) {
    return;   // rien à faire : on rend la main à config.php
}

// ─── 2. LAISSEZ-PASSER ──────────────────────────────────────────────────────
// Sans ça, vous seriez bloqué dehors comme tout le monde et ne pourriez pas
// vérifier votre travail pendant la coupure.
//
// On n'utilise PAS de filtrage par adresse IP (c'était l'approche du .htaccess
// d'origine) : sur Railway le trafic passe par un proxy, donc REMOTE_ADDR est
// l'adresse du proxy, pas la vôtre. Le filtre ne marcherait pas, ou bloquerait
// tout le monde.
//
// À la place : ajoutez ?passe=VOTRE_MOT_DE_PASSE à l'adresse. Un cookie de 6 h
// est posé, vous naviguez ensuite normalement.
// Le mot de passe vient de la variable Railway FAMIJOB_MAINTENANCE_PASSE — il
// n'est jamais écrit ici, ce dépôt étant public.
$passe = (string) (getenv('FAMIJOB_MAINTENANCE_PASSE') ?: ($_SERVER['FAMIJOB_MAINTENANCE_PASSE'] ?? ''));
if ($passe !== '') {
    if (isset($_GET['passe']) && hash_equals($passe, (string) $_GET['passe'])) {
        setcookie('fj_maintenance_passe', $passe, time() + 6 * 3600, '/');
        return;
    }
    if (isset($_COOKIE['fj_maintenance_passe'])
        && hash_equals($passe, (string) $_COOKIE['fj_maintenance_passe'])) {
        return;
    }
}

// ─── 3. LA COUPURE ──────────────────────────────────────────────────────────
// 503 « Service Unavailable », et surtout PAS 200.
// Un 200 dirait aux moteurs de recherche « voici le nouveau contenu du site » :
// ils remplaceraient les vraies pages de FamiJob par la page de maintenance
// dans leur index. Le 503 dit « c'est temporaire, repassez ».
// Retry-After annonce 5 minutes, en accord avec le message de la page.
http_response_code(503);
header('Retry-After: 300');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Certaines pages sont appelées en arrière-plan par le JavaScript et attendent
// du JSON (notif_count.php par exemple). Leur renvoyer du HTML remplirait la
// console d'erreurs de parsing : on leur répond dans leur langue.
$fichierAppele = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$attendJson = in_array($fichierAppele, ['notif_count.php'], true)
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($attendJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'maintenance' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
$page = __DIR__ . '/maintenance.html';
if (is_file($page)) {
    readfile($page);
} else {
    // Repli minimal : mieux vaut une phrase nette qu'une page blanche si le
    // fichier HTML n'a pas été déposé.
    echo '<!doctype html><html lang="fr"><meta charset="utf-8">'
       . '<title>FamiJob — Maintenance</title>'
       . '<p style="font-family:system-ui,sans-serif;text-align:center;margin-top:20vh">'
       . 'Intervention de courte durée. Le site sera de nouveau accessible très prochainement.</p>';
}
exit;
