<?php
// ============================================================
// FAMICARD — L'ACCUEIL DU PORTAIL.
//
// Famicard n'est pas une page de FamiFormation : c'est le point d'entrée du
// collaborateur. Sa carte d'abord, et les plateformes comme des portes qu'elle
// ouvre — pas l'inverse. C'est ce que le README annonce depuis le début
// (« demain elle ouvre les portes ») ; cette page le rend vrai à l'écran.
//
// Quatre tuiles, pas une de plus :
//   • Ma fiche          — la carte (fiche.php, qui était index.php jusqu'ici) ;
//   • Mes collaborateurs — la base des fiches, pour les administrateurs ;
//   • FamiFormation      — le site principal ;
//   • FamiJob            — pour les profils qui y ont accès.
//
// ⚠️ Les deux dernières suivent les règles d'accès DÉJÀ en place sur l'accueil
// du site : on reflète les portes existantes, on n'en ouvre pas de nouvelles
// depuis ici. Une tuile qui mène à un refus est pire que pas de tuile.
// ============================================================
require_once __DIR__ . '/config.php';

$moi = famicardExigeConnexion($db);
$estAdmin = famicardEstAdmin();
$roleMoi = (string) ($moi['role'] ?? '');

// La carte est-elle complète ? On le dit ICI, sur l'accueil, plutôt que
// d'attendre que le collaborateur ouvre sa fiche : c'est la seule façon qu'un
// champ obligatoire vide soit vu par quelqu'un.
$champs    = famicardChamps($db);
$magasins  = famicardMagasins($db);
$libres    = famicardValeursLibres($db, (int) $moi['id']);
$manquants = famicardChampsManquants($champs, $moi, $libres, $magasins);

$prenom = trim((string) ($moi['prenom'] ?? ''));
if ($prenom === '') {
    $prenom = (string) ($moi['identifiant'] ?? '');
}

// Même chemin de photo que la fiche (volume ou uploads), et même correction :
// le chemin rendu est relatif à la racine du SITE, pas à Famicard.
$photo = (string) ($moi['photo_profil'] ?? '');
$photoUrl = '';
if ($photo !== '') {
    $photoUrl = function_exists('moduleFileUrl') ? moduleFileUrl($photo) : $photo;
    if ($photoUrl !== '' && !preg_match('#^(https?:)?//#i', $photoUrl)) {
        $photoUrl = famicardSiteUrl($photoUrl);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Famicard - Famiflora</title>
<?php // Assets du SITE PRINCIPAL : sur le sous-domaine, « /favicon.ico » serait
      // réécrit vers famicard/ et introuvable. D'où famicardSiteUrl(). ?>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: url('<?= e(famicardSiteUrl('background.jpg')) ?>') no-repeat center center fixed; background-size: cover; margin: 0; padding: 0 0 40px; color: #333; }
    .wrap { max-width: 900px; margin: 0 auto; padding: 0 16px; }

    .tete { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 22px 0 18px; }
    .moi { display: flex; align-items: center; gap: 14px; }
    .avatar { width: 58px; height: 58px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,.9); background: #e8f5e9; }
    .avatar-vide { display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #2d5a37; background: rgba(255,255,255,.9); }
    .bonjour { color: #fff; text-shadow: 0 2px 8px rgba(0,0,0,.35); }
    .bonjour .salut { font-size: 1.35rem; font-weight: 800; line-height: 1.2; }
    .bonjour .sous { font-size: .85rem; opacity: .95; }
    .deco { background: rgba(255,255,255,.92); border-radius: 30px; padding: 9px 18px; text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .85rem; box-shadow: 0 4px 10px rgba(0,0,0,.1); }

    .rappel { background: #fff8e6; border-left: 5px solid #E9A93C; border-radius: 12px; color: #7a4a11; padding: 14px 18px; font-size: .9rem; line-height: 1.55; margin-bottom: 20px; }
    .rappel a { color: #7a4a11; font-weight: 700; }

    .tuiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; }
    .tuile { position: relative; display: block; background: rgba(255,255,255,.96); border-radius: 18px; padding: 26px 24px; text-decoration: none; color: inherit; box-shadow: 0 8px 24px rgba(0,0,0,.12); border: 2px solid transparent; transition: transform .15s, border-color .15s, box-shadow .15s; }
    .tuile:hover { transform: translateY(-3px); border-color: #2d5a37; box-shadow: 0 12px 30px rgba(0,0,0,.18); }
    .tuile .ico { font-size: 2.1rem; line-height: 1; display: block; margin-bottom: 12px; }
    .tuile .nom { color: #2d5a37; font-weight: 800; font-size: 1.15rem; }
    .tuile .quoi { color: #666; font-size: .87rem; margin-top: 7px; line-height: 1.5; }
    .pastille { position: absolute; top: 14px; right: 14px; background: #E9A93C; color: #fff; border-radius: 999px; padding: 3px 11px; font-size: .72rem; font-weight: 800; }

    .titre-groupe { color: #fff; text-shadow: 0 2px 8px rgba(0,0,0,.35); font-size: .8rem; text-transform: uppercase; letter-spacing: .09em; font-weight: 700; margin: 30px 0 12px; }
</style>
</head>
<body>

<div class="wrap">

    <div class="tete">
        <div class="moi">
            <?php if ($photoUrl !== ''): ?>
                <img class="avatar" src="<?= e($photoUrl) ?>" alt="">
            <?php else: ?>
                <div class="avatar avatar-vide">👤</div>
            <?php endif; ?>
            <div class="bonjour">
                <div class="salut">Bonjour <?= e($prenom) ?></div>
                <div class="sous"><?= e(famicardLibelleRole($roleMoi)) ?></div>
            </div>
        </div>
        <a class="deco" href="logout.php">Se déconnecter</a>
    </div>

    <?php if ($manquants): ?>
        <div class="rappel">
            <b>Ta carte est incomplète.</b>
            Il manque : <?= e(implode(', ', array_map(static function ($c) { return $c['libelle']; }, $manquants))) ?>.
            <a href="fiche.php">Compléter ma fiche</a>
        </div>
    <?php endif; ?>

    <div class="tuiles">
        <a class="tuile" href="fiche.php">
            <?php if ($manquants): ?><span class="pastille">à compléter</span><?php endif; ?>
            <span class="ico">🪪</span>
            <div class="nom">Ma fiche</div>
            <div class="quoi">Ta carte d'identité Famiflora, tes informations et ton badge.</div>
        </a>

        <?php if ($estAdmin): ?>
        <a class="tuile" href="admin.php">
            <span class="ico">📇</span>
            <div class="nom">Mes collaborateurs</div>
            <div class="quoi">Les fiches de l'équipe, leur badge et l'export.</div>
        </a>
        <?php endif; ?>
    </div>

    <div class="titre-groupe">Mes accès</div>

    <div class="tuiles">
        <a class="tuile" href="<?= e(famicardSiteUrl('index.php')) ?>">
            <span class="ico">🎓</span>
            <div class="nom">FamiFormation</div>
            <div class="quoi">Formations, quiz, onboarding.</div>
        </a>

        <?php // Même règle que l'accueil du site (admin et teamcoach). Les
              // étudiants passent par student.famiformation.com, qui a son
              // propre login : leur proposer ce lien-ci les enverrait sur un mur. ?>
        <?php if (in_array($roleMoi, ['admin', 'teamcoach'], true)): ?>
        <a class="tuile" href="<?= e(famicardSiteUrl('famijob/index.php')) ?>">
            <span class="ico">🗓️</span>
            <div class="nom">FamiJob</div>
            <div class="quoi">Horaires et matching des étudiants.</div>
        </a>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
