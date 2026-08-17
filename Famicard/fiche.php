<?php
// ============================================================
// FAMICARD — LA CARTE DU COLLABORATEUR.
//
// Deux lectures d'un même écran :
//   • collaborateur : sa carte d'identité Famiflora, en consultation ;
//   • administrateur : la même carte, plus l'entrée vers la base complète.
//
// Aucune donnée n'est affichée « parce qu'elle est dans la table » : chaque
// champ passe par famicardPeutVoir(), qui applique la règle portée par le
// champ lui-même (voir includes/carte.php).
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/agence.php';
// famicardPhotoUrl() : Famicard sert ses photos lui-meme, media.php du site
// exigeant une session que le sous-domaine ne lui transmet pas.
require_once __DIR__ . '/includes/photo.php';
require_once __DIR__ . '/includes/modifications.php';
require_once __DIR__ . '/includes/avatar.php';

$moi = famicardExigeConnexion($db);
$estAdmin = famicardEstAdmin();

// ── CE QUI ATTEND ENCORE UNE DÉCISION ────────────────────────────────────
// Les corrections apparaissaient en couleur sur l'écran d'ÉDITION seulement.
// Or on relit sa carte bien plus souvent qu'on ne l'ouvre pour la modifier :
// on voyait sa nouvelle valeur en place, sans savoir qu'elle pouvait encore
// être rétablie. Elle se signale donc ici aussi.
$enAttente = famicardModificationsEnAttentePour($db, (int) $moi['id']);

// Le compte rendu de l'enregistrement : on arrive maintenant ICI après avoir
// modifié sa fiche, plus sur le formulaire qu'on vient de quitter.
$message = '';
if (!empty($_SESSION['famicard_modif_flash'])) {
    $message = (string) $_SESSION['famicard_modif_flash'];
    unset($_SESSION['famicard_modif_flash']);
}
$enValidation = !empty($_SESSION['famicard_modif_en_validation']);
unset($_SESSION['famicard_modif_en_validation']);

// ⚠️ UNE AGENCE N'A PAS LA MÊME CARTE. Pas de rattachement, pas de photo, pas
// de prénom : à la place, ce qui l'identifie vraiment — son nom, sa personne de
// contact, ses adresses. Voir includes/agence.php pour le détail et le pourquoi.
$estAgence = famicardEstCompteAgence($moi['role'] ?? '');

if ($estAgence) {
    $moi       = famicardAjouteAgence($db, $moi);
    $champs    = famicardChampsAgence();
    $groupes   = famicardGroupesAgence();
    $libres    = [];
    // Aucun champ obligatoire pour une agence : lui annoncer une carte
    // incomplète serait lui reprocher de ne pas être quelqu'un.
    $manquants = [];
} else {
    // Secteur et département : ils ne sont pas dans `utilisateurs`, on les pose
    // dans la ligne pour que le modèle les lise comme les autres champs.
    $moi = famicardAjouteRattachement(
        $moi,
        famicardRattachementsRh($db, [(int) $moi['id']]),   // de quoi il relève
        famicardPlacements($db, [(int) $moi['id']])         // où FamiJob peut le placer
    );
    $champs    = famicardChamps($db);
    $groupes   = famicardGroupes();
    $libres    = famicardValeursLibres($db, (int) $moi['id']);
    $manquants = famicardChampsManquants($champs, $moi, $libres, famicardMagasins($db));
}

$magasins  = famicardMagasins($db);

/**
 * L'ANCIENNE VALEUR, ÉCRITE COMME ON L'AURAIT LUE.
 *
 * Le journal garde la valeur BRUTE — « 3 » pour un magasin, « interim » pour
 * un employeur. L'afficher telle quelle donnerait « avant : 3 », qui n'apprend
 * rien à personne. On la fait donc repasser par le même traducteur que la
 * valeur actuelle, en lui présentant une ligne d'une seule case.
 *
 * Si ce champ ne vit pas dans une colonne (le rattachement, par exemple), le
 * traducteur rend vide : on retombe alors sur le texte du journal, qui pour
 * ces champs-là est déjà lisible.
 */
$ancienneLisible = static function ($cle, array $champ, $brute) use ($magasins) {
    $brute = (string) $brute;
    if ($brute === '') {
        return '';
    }
    if (!empty($champ['champ_id'])) {
        $lu = famicardValeurAffichee($cle, $champ, [], $magasins, [(int) $champ['champ_id'] => $brute]);
    } else {
        $colonne = (string) ($champ['colonne'] ?? '');
        $lu = $colonne === '' ? '' : famicardValeurAffichee($cle, $champ, [$colonne => $brute], $magasins, []);
    }
    return $lu !== '' ? $lu : $brute;
};

// Le titre de la carte : le nom de l'agence pour une agence, celui de la
// personne sinon. « Prénom Nom » sur un compte de société donne une carte vide.
$nomComplet = $estAgence
    ? trim((string) ($moi['agence_nom'] ?? ''))
    : trim(((string) ($moi['prenom'] ?? '')) . ' ' . ((string) ($moi['nom'] ?? '')));
if ($nomComplet === '') {
    $nomComplet = (string) ($moi['identifiant'] ?? '');
}

// La photo suit le même chemin que sur le reste du site (volume ou uploads).
$photo = (string) ($moi['photo_profil'] ?? '');
$photoUrl = '';
if ($photo !== '') {
    // Servie par Famicard (photo.php) et non par media.php du site : le
    // cookie de session ne franchit pas le sous-domaine, media.php
    // repondait 403, et la photo ne s'affichait pas. Voir
    // includes/photo.php.
    $photoUrl = famicardPhotoUrl((int) $moi['id'], (string) $photo);
}

// ── L'AVATAR, À CÔTÉ DE LA PHOTO ────────────────────────────────────────────
// La photo reste ce qui identifie ; la figurine est ce qu'on choisit. Les deux
// se voient d'un coup d'œil, et aucune ne prend la place de l'autre.
//
// ⚠️ C'est la VIGNETTE PNG qui est affichée ici, pas la scène 3D. Charger un
// moteur 3D sur une page qu'on ouvre pour lire son numéro de téléphone serait
// payer six cents kilo-octets pour une image de cent pixels. La 3D vit dans
// l'atelier, là où elle sert vraiment (avatar.php).
$avatar = $estAgence ? ['existe' => false, 'image' => '', 'maj' => ''] : famicardAvatarDe($db, (int) $moi['id']);
$avatarUrl = ($avatar['existe'] && $avatar['image'] !== '')
    ? famicardAvatarImageUrl((int) $moi['id'], (string) $avatar['maj'])
    : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ma Famicard - Famiflora</title>
<?php // Le favicon et le fond appartiennent au SITE PRINCIPAL. Écrits « /favicon.ico »,
      // ils sont réécrits vers famicard/ sur le sous-domaine et disparaissent :
      // famicardSiteUrl() les fait pointer sur www dans ce cas. ?>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php // Le cadre commun à toutes les pages (fond, largeur, respiration).
      // Chargé AVANT le <style> de la page, qui garde donc le dernier mot. ?>
<link rel="stylesheet" href="assets/famicard.css">
<style>
    :root { --famicard-fond: url('<?= e(famicardSiteUrl('background.jpg')) ?>'); }
</style>
<style>
    body { font-family: 'Open Sans', sans-serif; margin: 0; padding: 0 0 40px; color: #333; }
    .top-nav { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; }
    .pill { background: rgba(255,255,255,.92); padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 10px rgba(0,0,0,.1); text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .9rem; }
    .wrap { max-width: 860px; margin: 0 auto; padding: 0 16px; }

    .carte { background: rgba(255,255,255,.95); border-radius: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; }
    .carte-tete { display: flex; align-items: center; gap: 20px; padding: 26px; background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; flex-wrap: wrap; }
    .avatar { width: 92px; height: 92px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,.85); background: #e8f5e9; }
    .avatar-vide { display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: #2d5a37; border-style: dashed; }
    .carte-tete h1 { margin: 0 0 6px; font-size: 1.6rem; font-weight: 800; }
    .etiquette { display: inline-block; background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.5); border-radius: 999px; padding: 3px 14px; font-size: .82rem; font-weight: 700; }

    /* La figurine, en bout de bandeau. Pas de rond ni de cadre : un personnage
       entier se pose, il ne se recadre pas. `margin-left:auto` la pousse à
       l'opposé de la photo — les deux identités, chacune de son côté. */
    .figurine { margin-left: auto; height: 108px; width: auto; object-fit: contain; filter: drop-shadow(0 4px 8px rgba(0,0,0,.25)); }
    .figurine-vide { margin-left: auto; display: flex; flex-direction: column; align-items: center; gap: 4px; color: #fff; text-decoration: none; font-size: .74rem; font-weight: 700; opacity: .9; border: 1px dashed rgba(255,255,255,.6); border-radius: 14px; padding: 12px 14px; }
    .figurine-vide span { font-size: 1.6rem; }
    @media (max-width: 560px) { .figurine, .figurine-vide { margin-left: 0; } }

    .rappel { background: #fff8e6; border-bottom: 1px solid #f0dfb5; color: #7a4a11; padding: 14px 26px; font-size: .92rem; line-height: 1.55; }
    .rappel a { color: #7a4a11; font-weight: 700; }

    .groupe { padding: 20px 26px; border-top: 1px solid #eee; }
    .groupe h2 { margin: 0 0 14px; font-size: .82rem; text-transform: uppercase; letter-spacing: .08em; color: #2d5a37; }
    .ligne { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px dashed #eee; font-size: .95rem; }
    .ligne:last-child { border-bottom: 0; }
    .ligne .cle { color: #666; }
    .ligne .obl { color: #c0392b; font-weight: 700; }
    .ligne .val { font-weight: 600; text-align: right; word-break: break-word; }
    .val-bloc { text-align: right; min-width: 0; }
    .val-bloc .avant { display: block; color: #8a5a10; font-size: .76rem; font-weight: 600; margin-top: 2px; }

    /* ── UNE CORRECTION QUI ATTEND ─────────────────────────────────────
       Même ambre que sur l'écran d'édition et dans la file de relecture :
       on reconnaît l'état d'un écran à l'autre sans avoir à lire. */
    .ligne.en-attente { background: #fffaf0; border-radius: 10px; padding: 9px 12px; margin: 0 -12px; border-bottom-color: #f0dbac; }
    .ligne.en-attente .cle { color: #8a5a10; }
    .marque { display: inline-block; background: #E9A93C; color: #fff; border-radius: 999px; padding: 1px 9px; font-size: .68rem; font-weight: 800; margin-left: 6px; white-space: nowrap; }
    .vide { color: #b0b0b0; font-weight: 400; font-style: italic; }

    .actions { display: flex; gap: 12px; flex-wrap: wrap; padding: 22px 26px; background: #f7faf8; border-top: 1px solid #eee; }
    .bouton { border: 0; border-radius: 30px; padding: 12px 24px; font-family: inherit; font-weight: 700; font-size: .92rem; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-vide { background: #fff; color: #2d5a37; border: 1px solid #d3e0d7; }

    .encart { background: rgba(255,255,255,.95); border-left: 5px solid #2d5a37; border-radius: 14px; padding: 16px 20px; margin-top: 22px; font-size: .9rem; line-height: 1.55; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
    .encart h3 { margin: 0 0 8px; font-size: .95rem; color: #2d5a37; }

    .flash { border-radius: 12px; padding: 12px 16px; margin: 0 0 16px; font-size: .9rem; font-weight: 600; background: #e8f5e9; color: #1e5128; box-shadow: 0 4px 12px rgba(0,0,0,.06); }
    .fenetre { position: fixed; inset: 0; background: rgba(20,40,28,.55); display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 50; }
    .fenetre[hidden] { display: none; }
    .fenetre-boite { background: #fff; border-radius: 20px; padding: 26px; max-width: 430px; width: 100%; box-shadow: 0 24px 60px rgba(14,40,24,.35); }
    .fenetre-boite h2 { margin: 0 0 10px; font-size: 1.2rem; color: #2d5a37; }
    .fenetre-quoi { margin: 0 0 16px; font-size: .9rem; line-height: 1.6; color: #5a6b60; }
    .fenetre-actions { display: flex; gap: 10px; margin-top: 18px; }
</style>
</head>
<body>

<?php // Le retour va vers l'accueil de FAMICARD, jamais vers FamiFormation :
      // une flèche « ← FamiFormation » dirait au collaborateur qu'il est dans
      // une annexe et que la maison est ailleurs. C'est l'inverse. Les autres
      // plateformes se rejoignent depuis l'accueil (index.php), comme des
      // portes que la carte ouvre. ?>
<?php // ── « C'EST ENREGISTRÉ, UN ADMIN VA LE RELIRE » ─────────────────────
      // Elle s'ouvrait sur l'écran d'édition, là où l'on venait d'appuyer sur
      // « Enregistrer ». Mais on y restait, devant le même formulaire, sans
      // voir le résultat. On revient maintenant SUR SA FICHE — l'endroit où
      // l'on constate ce qu'on a changé — et la fenêtre a suivi.
      //
      // Rendue par le serveur et non par du JavaScript : elle s'affiche même
      // si un script ne part pas, et le bouton n'est qu'un lien. ?>
<?php if ($enValidation): ?>
    <div class="fenetre" id="fenetreEnvoye" role="dialog" aria-modal="true" aria-labelledby="titreEnvoye">
        <div class="fenetre-boite">
            <div style="font-size:2.4rem;line-height:1;margin-bottom:10px;">✅</div>
            <h2 id="titreEnvoye">C'est enregistré</h2>
            <p class="fenetre-quoi">
                Tes corrections sont <b>déjà en place</b> sur ta fiche.
                Un administrateur va les relire et les confirmer — c'est la marche normale,
                tu n'as rien d'autre à faire.
            </p>
            <p class="fenetre-quoi">
                En attendant, les champs concernés sont <b style="color:#8a5a10;">marqués en orange</b>
                ci-dessous. S'il y avait une erreur, tu peux encore les corriger.
            </p>
            <div class="fenetre-actions">
                <button type="button" class="bouton bouton-plein" id="fermeEnvoye">J'ai compris</button>
            </div>
        </div>
    </div>
    <script>
        // Le bouton ferme la fenêtre. Sans script elle reste ouverte, mais la
        // page est dessous et reste lisible — on ne bloque personne.
        (function () {
            var f = document.getElementById('fenetreEnvoye');
            var b = document.getElementById('fermeEnvoye');
            if (!f || !b) { return; }
            function ferme() { f.setAttribute('hidden', ''); }
            b.addEventListener('click', ferme);
            f.addEventListener('click', function (e) { if (e.target === f) { ferme(); } });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { ferme(); } });
            b.focus();
        }());
    </script>
<?php endif; ?>

<div class="top-nav">
    <a class="pill" href="index.php">&larr; Accueil</a>
    <?php if ($estAdmin): ?>
        <a class="pill" href="admin.php">Base des collaborateurs</a>
    <?php endif; ?>
</div>

<div class="wrap">

    <?php if ($message !== ''): ?>
        <div class="flash"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="carte">
        <div class="carte-tete">
            <?php if ($photoUrl !== ''): ?>
                <img class="avatar" src="<?= e($photoUrl) ?>" alt="">
            <?php else: ?>
                <div class="avatar avatar-vide"><?= $estAgence ? '🏢' : '👤' ?></div>
            <?php endif; ?>
            <div>
                <h1><?= e($nomComplet) ?></h1>
                <span class="etiquette"><?= e(famicardLibelleRole($moi['role'] ?? '')) ?></span>
            </div>
            <?php if (!$estAgence): ?>
                <?php if ($avatarUrl !== ''): ?>
                    <a href="avatar.php" title="Modifier mon avatar"><img class="figurine" src="<?= e($avatarUrl) ?>" alt="Mon avatar"></a>
                <?php else: ?>
                    <a class="figurine-vide" href="avatar.php"><span>🧍</span>Créer mon avatar</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($manquants): ?>
            <div class="rappel">
                <b>Ta carte est incomplète.</b>
                Il manque : <?= e(implode(', ', array_map(static function ($c) { return $c['libelle']; }, $manquants))) ?>.
                <?php if (isset($manquants['photo_profil'])): ?>
                    <br>Elle se dépose en haut de <a href="modifier.php">Modifier mes informations</a>.
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
                        $valeur = famicardValeurAffichee($cle, $champ, $moi, $magasins, $libres);
                        $attend = $enAttente[$cle] ?? null;
                    ?>
                    <div class="ligne<?= $attend ? ' en-attente' : '' ?>">
                        <span class="cle">
                            <?= e($champ['libelle']) ?><?php if (!empty($champ['requis'])): ?> <span class="obl" title="Obligatoire">*</span><?php endif; ?>
                            <?php if ($attend): ?>
                                <span class="marque" title="Un administrateur doit encore confirmer cette correction">⏳ à confirmer</span>
                            <?php endif; ?>
                        </span>
                        <span class="val-bloc">
                            <?php if ($valeur === ''): ?>
                                <span class="val vide">non renseigné</span>
                            <?php else: ?>
                                <span class="val"><?= e($valeur) ?></span>
                            <?php endif; ?>
                            <?php // L'ancienne valeur : c'est elle qui reviendrait si la
                                  // correction était rétablie. La taire laisserait croire
                                  // que la nouvelle est déjà acquise. ?>
                            <?php if ($attend): ?>
                                <?php $avant = $ancienneLisible($cle, $champ, $attend['avant']); ?>
                                <?php if ($avant !== ''): ?>
                                    <span class="avant">avant : <?= e($avant) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="actions">
            <?php // Deux boutons, pas trois : la photo se change dans le même écran
                  // que le reste (en haut de modifier.php). Un bouton de plus pour
                  // un seul champ, c'était un aller-retour pour rien. ?>
            <a class="bouton bouton-plein" href="modifier.php">✏️ Modifier mes informations</a>
            <?php if (!$estAgence): ?>
                <a class="bouton bouton-vide" href="avatar.php">🧍 <?= $avatar['existe'] ? 'Mon avatar' : 'Créer mon avatar' ?></a>
            <?php endif; ?>
            <?php // Pas de badge pour une agence : un badge se porte, et une
                  // société extérieure n'a rien à porter (voir badge.php, qui
                  // refuse aussi l'adresse tapée à la main). ?>
            <?php if (!$estAgence): ?>
                <a class="bouton bouton-vide" href="badge.php">🖨️ Imprimer mon badge</a>
            <?php endif; ?>
            <?php if ($estAdmin): ?>
                <a class="bouton bouton-vide" href="export.php">📊 Exporter en Excel</a>
                <a class="bouton bouton-vide" href="admin_champs.php">⚙️ Libellés de la fiche</a>
            <?php endif; ?>
        </div>
    </div>

    <?php // Les accès aux autres plateformes sont sur l'accueil (index.php),
          // pas ici : cette page montre la carte, rien d'autre. ?>
    <div class="encart">
        <h3>🔒 <?= $estAgence ? 'Vos données' : 'Tes données' ?></h3>
        Famicard n'ouvre <b>aucune nouvelle base</b> :
        <?php if ($estAgence): ?>
            cette carte affiche ce que Famiflora sait de votre agence. Le nom du contact et les
            adresses se règlent avec l'administration ; la ville, vous pouvez la corriger vous-même.
        <?php else: ?>
            ta carte affiche des informations qui existent déjà et que les services de la maison
            utilisent. Ton badge ne porte que ton <b>prénom</b> et la mention bilingue — rien d'autre.
        <?php endif; ?>
    </div>

</div>
</body>
</html>
