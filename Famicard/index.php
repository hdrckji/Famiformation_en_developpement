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
require_once __DIR__ . '/includes/modifications.php';
require_once __DIR__ . '/includes/services.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/agence.php';
require_once __DIR__ . '/includes/avatar.php';

$moi = famicardExigeConnexion($db);

// ─────────────────────────────────────────────────────────────────────────────
// LE RÉCAP — première connexion, puis une fois par an.
//
// On l'impose ICI, à l'accueil, et pas ailleurs : c'est le passage obligé, et
// une redirection posée sur chaque page rendrait le site impraticable pour qui
// veut juste consulter quelque chose. La page de récap, elle, laisse toujours
// une porte de sortie (« plus tard ») — voir recap.php.
//
// ⚠️ famicardDoitValiderFiche() renvoie FALSE si sa table n'existe pas : une
// plateforme ne doit pas devenir inaccessible parce qu'une table manque.
if (famicardDoitValiderFiche($db, (int) $moi['id'])) {
    header('Location: recap.php?retour=' . urlencode('index.php'));
    exit();
}

// Le mot laissé après validation, affiché une fois.
$flashRecap = '';
if (!empty($_SESSION['famicard_recap_flash'])) {
    $flashRecap = (string) $_SESSION['famicard_recap_flash'];
    unset($_SESSION['famicard_recap_flash']);
}
$estAdmin = famicardEstAdmin();
$roleMoi = (string) ($moi['role'] ?? '');

// Les services sont installés par un ADMIN qui passe ici : c'est de la DDL, on
// ne la fait pas exécuter par tout le monde à chaque affichage.
if ($estAdmin) {
    try {
        famicardAssureServices($db);
    } catch (Exception $e) {
        // Droits insuffisants : on retombe sur les règles historiques.
    }
    // Les colonnes `employeur` et `contrat`, et la reprise des fiches
    // existantes — une seule fois, à la création des colonnes. Voir
    // includes/emploi.php : rien n'est deviné, tout ce qui n'est pas déductible
    // reste vide et se voit dans « Contrats et employeurs ».
    famicardAssureEmploi($db);
    // La table du rattachement RH — de quoi chaque personne relève. À ne pas
    // confondre avec les départements de PLACEMENT de FamiJob : voir
    // includes/rattachement.php.
    famicardAssureRattachementRh($db);
}

// ⚠️ Les accès ne sont plus écrits en dur ici. Ils viennent de la base, et
// tant que rien n'y est enregistré pour ce collaborateur, des règles
// historiques prennent le relais (voir includes/services.php). Ajouter un
// service demande donc une ligne en base, pas une modification de cette page.
$mesServices = famicardServicesDuCollaborateur($db, (int) $moi['id'], $roleMoi);

// Corrections en attente de décision (admins). Affichées sur la tuile plutôt
// que sur une cinquième : l'accueil doit rester à quatre entrées, et une
// pastille se voit sans rien ajouter.
$aValider = $estAdmin ? famicardCompteModificationsEnAttente($db) : 0;

// Combien de fiches n'ont pas encore de type de contrat. Le chiffre disparaît
// quand le travail est fini : c'est la seule façon qu'une reprise se termine.
$sansContrat = $estAdmin ? famicardCompteContratsAPreciser($db) : 0;

// Les avis en attente. Compté seulement pour les admins : c'est à eux d'y
// répondre, et une pastille sur la tuile de tout le monde n'aurait aucun sens.
// La table peut ne pas exister encore — la pastille vaut alors zéro.
$avisATraiter = 0;
if ($estAdmin) {
    try {
        $avisATraiter = (int) $db->query("SELECT COUNT(*) FROM interim_feedback WHERE status = 'open'")->fetchColumn();
    } catch (Exception $e) {
        $avisATraiter = 0;
    }
}

// La carte est-elle complète ? On le dit ICI, sur l'accueil, plutôt que
// d'attendre que le collaborateur ouvre sa fiche : c'est la seule façon qu'un
// champ obligatoire vide soit vu par quelqu'un.
// ⚠️ UNE AGENCE N'EST PAS UNE PERSONNE. Elle n'a ni photo, ni date de
// naissance, ni secteur : lui annoncer « ta carte est incomplète » serait lui
// reprocher de ne pas être quelqu'un. On saute donc tout ce bloc pour elle.
$estAgence = famicardEstCompteAgence($roleMoi);
// Le nom de l'agence, pour l'accueillir par son nom plutôt que par
// l'identifiant technique de son compte.
$nomAgence = $estAgence ? trim((string) ($moi['interim'] ?? '')) : '';

$champs    = famicardChamps($db);
$magasins  = famicardMagasins($db);
$libres    = famicardValeursLibres($db, (int) $moi['id']);
$manquants = $estAgence ? [] : famicardChampsManquants($champs, $moi, $libres, $magasins);

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

// L'AVATAR — sa figurine, à côté de sa photo et jamais à sa place. On ne fait
// que LIRE ici : la table est créée par l'atelier (avatar.php), et si elle
// n'existe pas encore, la lecture rend simplement « pas d'avatar ».
// Une agence n'en a pas : ce n'est pas quelqu'un (voir avatar.php).
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
<title>Famicard - Famiflora</title>
<?php // Assets du SITE PRINCIPAL : sur le sous-domaine, « /favicon.ico » serait
      // réécrit vers famicard/ et introuvable. D'où famicardSiteUrl(). ?>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }

    /* ⚠️ LE FOND EST SUR <html>, PAS SUR <body>, et c'est ce qui corrige la
       cassure nette en bas de page. Posé sur le body, il s'arrête où le contenu
       s'arrête : sur un écran haut avec peu de tuiles, la moitié basse virait
       à une autre teinte. Sur <html> il habille toute la fenêtre, quelle que
       soit la hauteur du contenu. */
    html {
        min-height: 100%;
        background: #dfe9e0 url('<?= e(famicardSiteUrl('background.jpg')) ?>') no-repeat center center fixed;
        background-size: cover;
    }
    body {
        font-family: 'Open Sans', sans-serif;
        margin: 0;
        padding: 0 0 56px;
        color: #333;
        min-height: 100vh;
        min-height: 100dvh;   /* mobile : la barre d'adresse ne laisse plus de bande */
        background: transparent;
    }
    .wrap { max-width: 980px; margin: 0 auto; padding: 0 20px; }

    /* ── L'EN-TÊTE, DANS UNE CARTE ──────────────────────────────────────
       Le nom et le bouton flottaient sur l'image, avec une ombre portée pour
       rester lisibles. Sur un fond clair, ça restait pâle. Une carte, et le
       texte redevient net quel que soit le fond. */
    .tete {
        display: flex; align-items: center; justify-content: space-between;
        gap: 18px; flex-wrap: wrap;
        background: rgba(255,255,255,.94);
        border-radius: 20px;
        padding: 18px 22px;
        margin: 26px 0 24px;
        box-shadow: 0 8px 26px rgba(20,50,32,.14);
    }
    .moi { display: flex; align-items: center; gap: 15px; min-width: 0; }
    .avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #e8f5e9; background: #e8f5e9; flex: 0 0 auto; }
    .avatar-vide { display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: #2d5a37; }
    .bonjour { min-width: 0; }
    .bonjour .salut { font-size: 1.4rem; font-weight: 800; line-height: 1.2; color: #1f4a2c; overflow-wrap: anywhere; }
    .bonjour .sous { font-size: .84rem; color: #6a7d72; margin-top: 3px; }
    .deco { background: #eef4ef; border-radius: 30px; padding: 10px 20px; text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .85rem; border: 1px solid #d8e6dc; white-space: nowrap; }
    .deco:hover { background: #2d5a37; color: #fff; border-color: #2d5a37; }

    .rappel { background: #fff8e6; border-left: 5px solid #E9A93C; border-radius: 12px; color: #7a4a11; padding: 14px 18px; font-size: .9rem; line-height: 1.55; margin-bottom: 20px; }
    .rappel a { color: #7a4a11; font-weight: 700; }

    /* ⚠️ « auto-fill » ET NON « auto-fit ». Avec auto-fit, une rangée qui ne
       contient qu'une tuile l'étire sur toute la largeur : le bloc « Mes accès »
       affichait un FamiJob de 900 px de large, seul sur sa ligne, et ça faisait
       tout de suite bricolé. auto-fill garde la largeur d'une colonne. */
    .tuiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 18px; align-items: stretch; }
    .tuile { position: relative; display: flex; flex-direction: column; background: rgba(255,255,255,.97); border-radius: 18px; padding: 24px 22px; text-decoration: none; color: inherit; box-shadow: 0 8px 24px rgba(20,50,32,.12); border: 2px solid transparent; transition: transform .15s, border-color .15s, box-shadow .15s; }
    .tuile:hover { transform: translateY(-3px); border-color: #2d5a37; box-shadow: 0 12px 30px rgba(0,0,0,.18); }
    .tuile .ico { font-size: 2.1rem; line-height: 1; display: block; margin-bottom: 12px; }
    .tuile .nom { color: #2d5a37; font-weight: 800; font-size: 1.15rem; }
    .tuile .quoi { color: #666; font-size: .87rem; margin-top: 7px; line-height: 1.5; }
    .pastille { position: absolute; top: 14px; right: 14px; background: #E9A93C; color: #fff; border-radius: 999px; padding: 3px 11px; font-size: .72rem; font-weight: 800; }

    /* La figurine sur sa tuile : c'est elle l'icône, quand elle existe. Un
       personnage entier ne se recadre pas en rond — il se pose sur le fond. */
    .tuile .figurine { display: block; height: 78px; width: auto; margin: -4px 0 8px; object-fit: contain; }

    /* Le titre de section était en blanc sur l'image : illisible dès que le
       fond s'éclaircit. Une pastille le tient sur tous les fonds. */
    .titre-groupe {
        display: inline-block;
        background: rgba(31,74,44,.88);
        color: #fff;
        font-size: .74rem; text-transform: uppercase; letter-spacing: .1em; font-weight: 800;
        padding: 6px 14px; border-radius: 999px;
        margin: 32px 0 14px;
    }
</style>
</head>
<body>

<?php // Le rappel de ce qui manque (photo, email). Même bandeau que sur les
      // autres plateformes : une seule fonction le dessine, pour qu'il dise et
      // montre la même chose partout. ?>
<?= famicardRappelHtml($db, (int) $moi['id'], 'recap.php') ?>

<div class="wrap">

    <?php if ($flashRecap !== ''): ?>
        <div class="rappel" style="background:#e7f6ea;border-left-color:#1E7A46;color:#1E7A46;margin-top:18px;">
            <?= e($flashRecap) ?>
        </div>
    <?php endif; ?>

    <div class="tete">
        <div class="moi">
            <?php if ($photoUrl !== ''): ?>
                <img class="avatar" src="<?= e($photoUrl) ?>" alt="">
            <?php else: ?>
                <div class="avatar avatar-vide"><?= $estAgence ? '🏢' : '👤' ?></div>
            <?php endif; ?>
            <div class="bonjour">
                <?php // Une agence n'a pas de prénom : on l'accueille par SON NOM,
                      // celui de l'agence, et pas par l'identifiant technique du
                      // compte (« Bonjour asup » ne veut rien dire pour personne). ?>
                <div class="salut">Bonjour <?= e($estAgence && $nomAgence !== '' ? $nomAgence : $prenom) ?></div>
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
            <div class="quoi"><?= $estAgence
                ? 'Les informations de votre agence : contact et adresses.'
                : "Ta carte d'identité Famiflora, tes informations et ton badge." ?></div>
        </a>

        <?php // ── L'AVATAR ─────────────────────────────────────────────────
              // Sur la même rangée que la fiche, et pas dans un coin : c'est
              // l'autre moitié de « qui je suis ici ». La photo dit qui on est,
              // la figurine dit comment on se présente.
              //
              // ⚠️ Pas de tuile pour une agence : une société n'a pas de coupe
              // de cheveux (même règle que la carte). ?>
        <?php if (!$estAgence): ?>
        <a class="tuile" href="avatar.php">
            <?php if (!$avatar['existe']): ?><span class="pastille">nouveau</span><?php endif; ?>
            <?php if ($avatarUrl !== ''): ?>
                <img class="figurine" src="<?= e($avatarUrl) ?>" alt="">
            <?php else: ?>
                <span class="ico">🧍</span>
            <?php endif; ?>
            <div class="nom">Mon avatar</div>
            <div class="quoi">
                <?= $avatar['existe']
                    ? "Ta figurine 3D : change de coupe, de tenue, d'équipement."
                    : "Crée ton personnage en 3D : coupe, teint, tenue, équipement." ?>
            </div>
        </a>
        <?php endif; ?>

        <?php // ── AVIS ET SUGGESTIONS, POUR TOUT LE MONDE ─────────────────
              // Y compris les agences. Un module qui recueille la parole des
              // équipes et n'est ouvert qu'à ceux qui décident ne recueille
              // rien. C'est la même boîte que celle de FamiJob — une seconde
              // aurait fait deux boîtes, dont une que personne ne relève. ?>
        <a class="tuile" href="avis.php">
            <?php if ($avisATraiter > 0): ?><span class="pastille"><?= (int) $avisATraiter ?> à lire</span><?php endif; ?>
            <span class="ico">💬</span>
            <div class="nom">Avis et suggestions</div>
            <div class="quoi">Une idée, une question, quelque chose qui ne va pas ? Dis-le, on te répond ici.</div>
        </a>

        <?php // ── L'AGENCE VOIT SES GENS, ET RIEN D'AUTRE ─────────────────
              // Nom, prénom, « étudiant » ou « intérimaire ». Pas d'email, pas
              // de rayon : ce qui n'est pas nécessaire ne se partage pas avec
              // un tiers (voir includes/agence.php). ?>
        <?php if ($estAgence): ?>
        <a class="tuile" href="mes_interimaires.php">
            <span class="ico">👥</span>
            <div class="nom">Mes intérimaires</div>
            <div class="quoi">Les personnes que vous nous envoyez : leur nom, et s'il s'agit d'un étudiant.</div>
        </a>
        <?php endif; ?>

        <?php if ($estAdmin): ?>
        <a class="tuile" href="admin.php">
            <?php if ($aValider > 0): ?><span class="pastille"><?= (int) $aValider ?> à confirmer</span><?php endif; ?>
            <span class="ico">📇</span>
            <div class="nom">Mes collaborateurs</div>
            <div class="quoi">Les fiches de l'équipe, leur badge et l'export.</div>
        </a>

        <?php // ── LES AGENCES, À PART DES COLLABORATEURS ──────────────────
              // Une agence n'est pas quelqu'un de la maison : c'est une société
              // extérieure à qui l'on ouvre une porte pour qu'elle voie SES
              // intérimaires. Ses comptes n'ont ni fiche, ni photo, ni contrat
              // — les mélanger aux gens remplissait la base de lignes vides
              // qu'on prenait pour des fiches incomplètes. ?>
        <a class="tuile" href="agences.php">
            <span class="ico">🏢</span>
            <div class="nom">Agences</div>
            <div class="quoi">Les agences d'intérim, leurs contacts, et les accès qu'on leur ouvre.</div>
        </a>
        <?php endif; ?>
    </div>

    <?php if ($mesServices): ?>
        <div class="titre-groupe">Mes accès</div>

        <div class="tuiles">
            <?php foreach ($mesServices as $service): ?>
                <?php
                // ⚠️ UN COMPTE AGENCE ENTRE PAR SON ECRAN, PAS PAR L'ACCUEIL.
                // FamiJob le redirigerait de toute facon, mais un aller-retour
                // visible donne l'impression d'avoir clique au mauvais endroit —
                // et laisse croire que l'accueil existe pour elle.
                $urlService = (string) $service['url'];
                if ((string) $service['code'] === 'famijob' && $roleMoi === 'agence_interim') {
                    $urlService = 'famijob/interim_horaires.php';
                }
                ?>
                <a class="tuile" href="<?= e(famicardSiteUrl($urlService)) ?>">
                    <span class="ico"><?= e((string) ($service['icone'] ?: '🔗')) ?></span>
                    <div class="nom"><?= e((string) $service['nom']) ?></div>
                    <div class="quoi"><?= e((string) ($service['description'] ?? '')) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php // ── ADMINISTRATION ─────────────────────────────────────────────
          // Les outils qui agissent sur les PERSONNES : leur profil, leur accès,
          // leur fiche. Ils étaient dispersés sur l'accueil de FamiFormation ;
          // ils appartiennent ici (voir README.md, « LE TRI »). ?>
    <?php if ($estAdmin): ?>
        <div class="titre-groupe">Administration</div>

        <div class="tuiles">
            <?php // En PREMIER, et ce n'est pas un hasard : « c'est depuis Famicard
                  // qu'on crée un utilisateur » (README.md, « LE TRI »). Un compte
                  // naît ici, le reste de cette section ne fait que l'entretenir. ?>
            <a class="tuile" href="creer.php">
                <span class="ico">➕</span>
                <div class="nom">Nouveau collaborateur</div>
                <div class="quoi">Créer le compte d'un arrivant : identifiant, profil, accès, et son mail d'activation.</div>
            </a>

            <a class="tuile" href="validations.php">
                <?php if ($aValider > 0): ?><span class="pastille"><?= (int) $aValider ?></span><?php endif; ?>
                <span class="ico">✅</span>
                <div class="nom">Modifications à confirmer</div>
                <div class="quoi">Les corrections faites par les collaborateurs sur leur propre fiche.</div>
            </a>

            <?php // Interne / intérim / indépendant, et le type de contrat. Deux
                  // questions que le PROFIL ne sait pas poser — et qui n'ouvrent
                  // aucun accès, contrairement à lui. ?>
            <a class="tuile" href="contrats.php">
                <?php if ($sansContrat > 0): ?><span class="pastille"><?= (int) $sansContrat ?> à préciser</span><?php endif; ?>
                <span class="ico">🧩</span>
                <div class="nom">Contrats et employeurs</div>
                <div class="quoi">Qui est interne, qui vient d'une agence, et avec quel contrat : étudiant, flexi ou fixe.</div>
            </a>

            <a class="tuile" href="tri_profils.php">
                <span class="ico">👥</span>
                <div class="nom">Tri des profils</div>
                <div class="quoi">Passer en profil employé les comptes beta qui figurent dans la liste du personnel.</div>
            </a>

            <a class="tuile" href="relance_mdp.php">
                <span class="ico">🔑</span>
                <div class="nom">Relance mot de passe</div>
                <div class="quoi">Renvoyer son lien de création de mot de passe, pour n'importe quel profil.</div>
            </a>

            <a class="tuile" href="admin_champs.php">
                <span class="ico">⚙️</span>
                <div class="nom">Libellés de la fiche</div>
                <div class="quoi">Créer les champs que porte la carte, obligatoires ou non.</div>
            </a>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
