<?php
// ============================================================
// FAMICARD — LA VIGNETTE DE L'AVATAR.
//
// Un PNG, et rien d'autre. Existe pour que les écrans qui affichent une TÊTE DE
// 40 PIXELS (l'accueil, une liste, un badge, demain un e-mail) n'aient pas à
// charger un moteur 3D pour ça.
//
// ⚠️ POURQUOI PAS media.php DU SITE. media.php vit sur www ; depuis
// famicard.famiformation.com la session de www n'existe pas (cookie host-only,
// voir config.php), et l'image reviendrait en 403 — donc un avatar cassé selon
// l'adresse par laquelle on est entré. Famicard sert SA vignette avec SA
// session. C'est trente lignes, et ça marche sur les deux adresses.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/avatar.php';

// Connexion obligatoire — sans redirection : une balise <img> ne sait pas
// suivre un formulaire de connexion, elle afficherait une page HTML cassée.
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$cible = isset($_GET['u']) ? (int) $_GET['u'] : (int) $_SESSION['user_id'];
if ($cible <= 0) {
    http_response_code(400);
    exit;
}

$avatar = famicardAvatarDe($db, $cible);
$chemin = famicardCheminAvatarImage($avatar['image']);

// Pas d'avatar, ou vignette disparue du volume : 404. L'appelant affiche alors
// son propre repli (la photo, ou le rond « 👤 »). On ne fabrique pas une image
// de remplacement ici : ce serait une image qu'aucun écran n'a demandée.
if ($chemin === '' || !is_file($chemin)) {
    http_response_code(404);
    exit;
}

while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($chemin));
// PRIVÉ : la vignette d'un collaborateur n'a rien à faire dans un cache
// partagé. Une heure suffit ; l'adresse porte un jeton de version qui change
// à chaque modification, donc une nouvelle tête s'affiche immédiatement.
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($chemin);
