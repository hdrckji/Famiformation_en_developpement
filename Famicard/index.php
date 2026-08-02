<?php
// ============================================================
// FAMICARD — LA CARTE DU COLLABORATEUR.
//
// Deux lectures d'un même écran, comme convenu :
//   • collaborateur : sa carte d'identité Famiflora, en consultation ;
//   • administrateur : la même carte, plus l'entrée vers la base complète.
//
// Aucune donnée n'est affichée « parce qu'elle est dans la table » : chaque
// champ passe par famicardPeutVoir(), qui applique la règle portée par le
// champ lui-même (voir includes/carte.php).
// ============================================================
require_once __DIR__ . '/config.php';

$moi = famicardExigeConnexion($db);
$estAdmin = famicardEstAdmin();

$champs    = famicardChamps();
$groupes   = famicardGroupes();
$magasins  = famicardMagasins($db);
$manquants = famicardChampsManquants();

$nomComplet = trim(((string) ($moi['prenom'] ?? '')) . ' ' . ((string) ($moi['nom'] ?? '')));
if ($nomComplet === '') {
    $nomComplet = (string) ($moi['identifiant'] ?? '');
}

// La photo suit le même chemin que sur le reste du site (volume ou uploads).
$photo = (string) ($moi['photo_profil'] ?? '');
$photoUrl = '';
if ($photo !== '') {
    $photoUrl = function_exists('moduleFileUrl') ? moduleFileUrl($photo) : $photo;
    // moduleFileUrl() rend un chemin relatif à la racine du site ; depuis
    // /famicard/ il faut le rendre absolu, sinon l'image est cherchée ici.
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
<title>Ma Famicard - Famiflora</title>
<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Open Sans', sans-serif; background: url('/background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; padding: 0 0 40px; color: #333; }
    .top-nav { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; }
    .pill { background: rgba(255,255,255,.92); padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 10px rgba(0,0,0,.1); text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .9rem; }
    .wrap { max-width: 860px; margin: 0 auto; padding: 0 16px; }

    .carte { background: rgba(255,255,255,.95); border-radius: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; }
    .carte-tete { display: flex; align-items: center; gap: 20px; padding: 26px; background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; flex-wrap: wrap; }
    .avatar { width: 92px; height: 92px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,.85); background: #e8f5e9; }
    .avatar-vide { display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: #2d5a37; }
    .carte-tete h1 { margin: 0 0 6px; font-size: 1.6rem; font-weight: 800; }
    .etiquette { display: inline-block; background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.5); border-radius: 999px; padding: 3px 14px; font-size: .82rem; font-weight: 700; }

    .groupe { padding: 20px 26px; border-top: 1px solid #eee; }
    .groupe h2 { margin: 0 0 14px; font-size: .82rem; text-transform: uppercase; letter-spacing: .08em; color: #2d5a37; }
    .ligne { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px dashed #eee; font-size: .95rem; }
    .ligne:last-child { border-bottom: 0; }
    .ligne .cle { color: #666; }
    .ligne .val { font-weight: 600; text-align: right; word-break: break-word; }
    .vide { color: #b0b0b0; font-weight: 400; font-style: italic; }

    .actions { display: flex; gap: 12px; flex-wrap: wrap; padding: 22px 26px; background: #f7faf8; border-top: 1px solid #eee; }
    .bouton { border: 0; border-radius: 30px; padding: 12px 24px; font-family: inherit; font-weight: 700; font-size: .92rem; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-attente { background: #eee; color: #999; cursor: not-allowed; }

    .encart { background: rgba(255,255,255,.95); border-left: 5px solid #E9A93C; border-radius: 14px; padding: 16px 20px; margin-top: 22px; font-size: .9rem; line-height: 1.55; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
    .encart h3 { margin: 0 0 8px; font-size: .95rem; color: #7a4a11; }
    .encart ul { margin: 8px 0 0; padding-left: 20px; }
</style>
</head>
<body>

<div class="top-nav">
    <a class="pill" href="<?= e(famicardSiteUrl('index.php')) ?>">&larr; FamiFormation</a>
    <?php if ($estAdmin): ?>
        <a class="pill" href="admin.php">Base des collaborateurs</a>
    <?php endif; ?>
</div>

<div class="wrap">

    <div class="carte">
        <div class="carte-tete">
            <?php if ($photoUrl !== ''): ?>
                <img class="avatar" src="<?= e($photoUrl) ?>" alt="">
            <?php else: ?>
                <div class="avatar avatar-vide">👤</div>
            <?php endif; ?>
            <div>
                <h1><?= e($nomComplet) ?></h1>
                <span class="etiquette"><?= e(famicardLibelleRole($moi['role'] ?? '')) ?></span>
            </div>
        </div>

        <?php foreach ($groupes as $cleGroupe => $groupe): ?>
            <?php
            // On ne dessine un groupe que s'il a au moins une ligne visible :
            // un titre suivi de rien donne l'impression d'un écran cassé.
            $lignes = [];
            foreach ($champs as $cle => $champ) {
                if (($champ['groupe'] ?? '') !== $cleGroupe) {
                    continue;
                }
                if ($cle === 'photo_profil') {
                    continue; // déjà montrée en tête de carte
                }
                if (!famicardPeutVoir($champ, $estAdmin, true)) {
                    continue;
                }
                $lignes[$cle] = $champ;
            }
            if (!$lignes) {
                continue;
            }
            ?>
            <div class="groupe">
                <h2><?= e($groupe['libelle']) ?></h2>
                <?php foreach ($lignes as $cle => $champ): ?>
                    <?php
                    $valeur = famicardValeurAffichee($cle, $champ, $moi, $magasins);
                    if ($cle === 'role') {
                        $valeur = famicardLibelleRole($moi['role'] ?? '');
                    }
                    $aCreer = empty($champ['colonne']);
                    ?>
                    <div class="ligne">
                        <span class="cle"><?= e($champ['libelle']) ?></span>
                        <?php if ($aCreer): ?>
                            <span class="val vide">à définir</span>
                        <?php elseif ($valeur === ''): ?>
                            <span class="val vide">non renseigné</span>
                        <?php else: ?>
                            <span class="val"><?= e($valeur) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="actions">
            <!-- Les deux fonctionnalités décidées. Elles sont volontairement
                 inactives tant que leur contenu n'est pas arrêté : un bouton
                 qui imprime un badge à moitié faux est pire que pas de bouton. -->
            <span class="bouton bouton-attente" title="Étape suivante">🖨️ Imprimer mon badge (36 × 75 mm)</span>
            <?php if ($estAdmin): ?>
                <span class="bouton bouton-attente" title="Étape suivante">📊 Exporter en Excel</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="encart">
        <h3>🔒 Tes données</h3>
        Famicard n'ouvre <b>aucune nouvelle base</b> : ta carte affiche les informations que
        FamiFormation et FamiJob utilisent déjà pour te donner accès aux services.
        <?php if ($manquants): ?>
            <br><br>Champs prévus mais <b>pas encore en place</b> :
            <?= e(implode(', ', array_map(static function ($c) { return $c['libelle']; }, $manquants))) ?>.
        <?php endif; ?>
    </div>

</div>
</body>
</html>
