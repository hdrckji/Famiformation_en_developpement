<?php
// ============================================================
// FAMICARD — L'AVATAR, EN JSON.
//
// C'EST LA PORTE POUR LES AUTRES PLATEFORMES. FamiFormation (et demain FamiJob)
// n'ont pas à connaître la table `famicard_avatars`, ni la palette, ni la façon
// dont on range les vignettes. Ils demandent ici, ils reçoivent de quoi
// afficher :
//
//   fetch('/famicard/avatar_data.php?u=12')
//     .then(r => r.json())
//     .then(d => creerAvatar(monDiv, d.look));
//
// `look` arrive avec les COULEURS DÉJÀ RÉSOLUES : le module 3D est le même
// partout et n'a besoin de rien d'autre. C'est ce qui fait que « réutiliser
// l'avatar dans FamiFormation » sera une page à écrire, pas un système à
// reconstruire.
//
// ⚠️ CE N'EST PAS UNE API PUBLIQUE. Connexion obligatoire, lecture seule.
// L'écriture passe par l'atelier (avatar.php), qui exige le jeton CSRF.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/avatar.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['erreur' => 'connexion requise']);
    exit;
}

// Sans `u`, c'est le sien. Un collaborateur peut lire l'avatar d'un autre —
// c'est le but : un classement, une liste d'équipe, un module qui affiche qui
// l'a suivi. Un avatar est une figurine choisie pour être vue, pas une donnée
// personnelle qu'on protège (contrairement à la date de naissance ou à
// l'adresse, qui elles ne sortent pas d'ici).
$cible = isset($_GET['u']) ? (int) $_GET['u'] : (int) $_SESSION['user_id'];
if ($cible <= 0) {
    http_response_code(400);
    echo json_encode(['erreur' => 'utilisateur invalide']);
    exit;
}

$avatar = famicardAvatarDe($db, $cible);

// `existe` = false signifie « cette personne n'a pas encore fait son avatar ».
// La configuration renvoyée est alors celle par défaut : l'appelant peut
// afficher un personnage neutre plutôt qu'un trou, s'il le souhaite. C'est LUI
// qui décide, pas nous.
echo json_encode([
    'user_id'   => $cible,
    'existe'    => $avatar['existe'],
    'config'    => $avatar['config'],
    'look'      => famicardAvatarLook($avatar['config']),
    'image_url' => ($avatar['image'] !== '')
        ? famicardAvatarImageUrl($cible, $avatar['maj'])
        : '',
    'maj'       => $avatar['maj'],
], JSON_UNESCAPED_UNICODE);
