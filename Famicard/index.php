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
                <a class="tuile" href="<?= e(famicardSiteUrl((string) $service['url'])) ?>">
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
