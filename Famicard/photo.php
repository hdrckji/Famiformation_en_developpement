<?php
// ============================================================
// photo.php — LA PHOTO D'UN COLLABORATEUR, SERVIE PAR FAMICARD.
//
// ⚠️ POURQUOI CE FICHIER EXISTE. Les photos passaient par `media.php` du SITE
// PRINCIPAL, via une URL absolue vers www. Or :
//
//   • media.php exige une session (`$_SESSION['user_id']`), et le cookie de
//     session est HOST-ONLY (Famiformation/config.php : 'domain' => '') ;
//   • sur famicard.famiformation.com, le navigateur n'envoie donc AUCUN cookie
//     à www.famiformation.com.
//
// Résultat : media.php répondait 403, et la photo ne s'affichait pas. Pas
// « parfois » — jamais, sur ce sous-domaine. Un utilisateur voyait la silhouette
// grise en croyant que sa photo n'avait pas été enregistrée, alors qu'elle
// l'était.
//
// Servie d'ici, l'adresse est RELATIVE à Famicard : elle marche sur les deux
// hôtes, avec la session de l'hôte où l'on se trouve.
//
// ── CE QU'ON NE FAIT PAS ────────────────────────────────────────────────────
// On ne prend PAS un chemin de fichier en paramètre, mais un IDENTIFIANT de
// personne. Un chemin ouvert, même contrôlé, transforme cette page en lecteur
// de fichiers du serveur ; ici la seule chose qu'on puisse demander est « la
// photo de quelqu'un », et c'est la base qui dit où elle est.
// ============================================================
require_once __DIR__ . '/config.php';

// Connexion obligatoire. La photo d'un collaborateur n'est pas publique — le
// modèle la déclare visible de « tous », c'est-à-dire de tous les CONNECTÉS.
famicardExigeConnexion($db);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Requête invalide.');
}

try {
    $st = $db->prepare('SELECT photo_profil FROM utilisateurs WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $chemin = trim((string) $st->fetchColumn());
} catch (Exception $e) {
    http_response_code(500);
    exit('Erreur.');
}

if ($chemin === '') {
    http_response_code(404);
    exit('Pas de photo.');
}

// ⚠️ LE CHEMIN VIENT DE LA BASE, mais on le contrôle quand même : une valeur
// bricolée par un autre écran ne doit pas permettre de remonter l'arborescence.
// Ceinture ET bretelles — c'est ce qui sépare une fuite d'un 404.
if (strpos($chemin, '..') !== false || strpos($chemin, "\0") !== false
    || preg_match('#^[A-Za-z0-9_./-]+$#', $chemin) !== 1) {
    http_response_code(404);
    exit('Chemin invalide.');
}

// Deux emplacements possibles, comme partout dans le dépôt : les anciens
// fichiers sous public/uploads, les nouveaux sur le volume.
$base = (strpos($chemin, 'uploads/') === 0)
    ? famicardRacineSite()
    : (defined('FAMI_STORAGE_BASE') ? rtrim(FAMI_STORAGE_BASE, '/') : (famicardRacineSite() . '/uploads'));

$reel = realpath($base . '/' . $chemin);
$baseReel = realpath($base);
if ($reel === false || $baseReel === false || strpos($reel, $baseReel) !== 0 || !is_file($reel)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Le type réel du fichier, pas celui déduit de son extension : un « .jpg » qui
// n'en est pas serait servi comme une image et interprété autrement.
$type = 'application/octet-stream';
$info = @getimagesize($reel);
if ($info && !empty($info['mime'])) {
    $type = (string) $info['mime'];
}
if (strpos($type, 'image/') !== 0) {
    http_response_code(404);
    exit('Ce fichier n\'est pas une image.');
}

// On coupe les tampons de sortie (thème, ruban) : on envoie du binaire.
while (ob_get_level() > 0) {
    ob_end_clean();
}

$maj = (int) @filemtime($reel);
$etag = '"' . md5($chemin . '|' . $maj) . '"';

header('Content-Type: ' . $type);
header('Content-Length: ' . (string) filesize($reel));
// Privé : une photo de collaborateur n'a rien à faire dans le cache d'un
// intermédiaire. Mais le navigateur, lui, peut la garder — sinon chaque page
// la retélécharge, et sur une liste de 400 lignes ça se voit.
header('Cache-Control: private, max-age=3600');
header('ETag: ' . $etag);
header('Content-Disposition: inline');
// Le navigateur ne doit pas deviner le type : c'est nous qui l'affirmons.
header('X-Content-Type-Options: nosniff');

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit();
}

readfile($reel);
