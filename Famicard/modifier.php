<?php
// ============================================================
// FAMICARD — MODIFIER UNE FICHE.
//
// UN SEUL ÉCRAN pour les deux usages : le collaborateur qui corrige ses
// coordonnées, et l'administrateur qui édite la fiche de quelqu'un
// (« modifier.php?id=N »). Deux formulaires auraient fini par diverger, et
// c'est toujours celui qu'on regarde le moins qui laisse passer une écriture
// qu'il n'aurait pas dû permettre.
//
// C'est famicardPeutModifier() qui décide, champ par champ — pas cette page.
// Le test est fait DEUX FOIS : à l'affichage, pour ne montrer que ce qui est
// éditable, et à l'enregistrement, pour refuser un champ ajouté à la main dans
// la requête. Un formulaire n'est pas une autorisation.
//
// LA PHOTO EST ICI, en tête, et plus sur une page à part : changer sa photo et
// corriger son adresse sont le même geste — « je mets ma fiche à jour ». Deux
// écrans pour ça, c'était un aller-retour pour rien.
//
// Ce que le collaborateur change s'applique TOUT DE SUITE et part en
// validation (voir includes/modifications.php). Ce que l'admin change est
// tracé mais déjà validé : il n'a personne à qui demander.
//
// ⚠️ LA PHOTO ÉCHAPPE À LA VALIDATION, volontairement : elle est libre et
// illimitée (décision Jimmy). Elle est tracée comme déjà validée — l'historique
// la garde, mais elle n'encombre pas l'écran des décisions à prendre.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/modifications.php';

$moi = famicardExigeConnexion($db);
$estAdmin = famicardEstAdmin();

// Cible : une autre fiche (admin) ou la sienne.
$cibleId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $moi['id'];
if (!$estAdmin && $cibleId !== (int) $moi['id']) {
    // Un non-admin qui bricole l'URL est renvoyé sur sa propre fiche, sans
    // message d'erreur : inutile de lui confirmer que la fiche visée existe.
    header('Location: modifier.php');
    exit();
}

$estSaPropreFiche = ($cibleId === (int) $moi['id']);

$st = $db->prepare("SELECT * FROM utilisateurs WHERE id = ? LIMIT 1");
$st->execute([$cibleId]);
$cible = $st->fetch(PDO::FETCH_ASSOC);
if (!$cible) {
    header('Location: ' . ($estAdmin ? 'admin.php' : 'fiche.php'));
    exit();
}

famicardAssureModifications($db);

// Les colonnes d'emploi (employeur, contrat) sont créées par un ADMIN qui
// passe ici : c'est de la DDL sur `utilisateurs`, on ne la fait pas exécuter
// par tout le monde. Tant qu'elles manquent, famicardChamps() retire
// simplement les deux champs — l'écran ne casse pas, il est juste plus court.
if ($estAdmin) {
    famicardAssureEmploi($db);
}

// Secteur et département : posés dans la ligne comme pseudo-colonnes, pour que
// le modèle les lise comme les autres champs (ils vivent en réalité dans
// student_department_links).
$cible = famicardAjouteRattachement($cible, famicardRattachements($db, [$cibleId]));

$champs   = famicardChamps($db);
$magasins = famicardMagasins($db);
$libres   = famicardValeursLibres($db, $cibleId);
$groupes  = famicardGroupes();

// ⚠️ LE RATTACHEMENT EST EN LECTURE SEULE ICI, et c'est un choix, pas un
// manque. Il vit dans `student_department_links` — la table dont le MATCHING
// INTÉRIM de FamiJob se sert, avec plusieurs départements par personne classés
// par priorité. Écrire dedans depuis cet écran, qui ne connaît qu'un
// département à la fois, effacerait les autres et fausserait le matching sans
// que personne ne s'en aperçoive avant de chercher des remplaçants.
//
// Le rattachement se règle donc là où il est maîtrisé (admin_user.php côté
// site, qui gère la liste complète et les priorités). Le ramener dans Famicard
// demande d'abord de décider ce qu'on fait des rattachements multiples.

// ─────────────────────────────────────────────────────────────────────────────
// STOCKAGE DE LA PHOTO — celui du SITE, volontairement : même dossier sur le
// volume, même colonne `utilisateurs.photo_profil`, même compression. Un second
// emplacement, et la photo affichée par FamiFormation ne serait plus celle
// déposée ici. Le repli passe par famicardRacineSite() : « __DIR__ »
// désignerait Famicard, donc un uploads/ qui n'est pas celui du site.
// ─────────────────────────────────────────────────────────────────────────────
$storeBase = defined('FAMI_STORAGE_BASE') ? rtrim(FAMI_STORAGE_BASE, '/') : (famicardRacineSite() . '/uploads');
$uploadDir = $storeBase . '/divers/profils/';

/** Chemin absolu d'une photo déjà enregistrée (clé volume OU ancien public/uploads). */
function famicardCheminPhoto($valeur, $storeBase)
{
    $valeur = (string) $valeur;
    if ($valeur === '') {
        return '';
    }
    return (strpos($valeur, 'uploads/') === 0)
        ? famicardRacineSite() . '/' . $valeur
        : $storeBase . '/' . $valeur;
}

// Le champ photo est-il modifiable par le regardeur ? La zone d'envoi n'est
// affichée que si oui — et le POST est refusé dans le cas contraire.
$champPhoto = $champs['photo_profil'] ?? null;
$photoEditable = $champPhoto ? famicardPeutModifier($champPhoto, $estAdmin, $estSaPropreFiche) : false;

$erreurs = [];
$message = '';
if (!empty($_SESSION['famicard_modif_flash'])) {
    $message = (string) $_SESSION['famicard_modif_flash'];
    unset($_SESSION['famicard_modif_flash']);
}

/** Valeur brute actuelle d'un champ (celle qui est en base, pas celle affichée). */
function famicardValeurBrute($cle, array $champ, array $ligne, array $libres)
{
    if (!empty($champ['champ_id'])) {
        return (string) ($libres[(int) $champ['champ_id']] ?? '');
    }
    $colonne = (string) ($champ['colonne'] ?? '');
    if ($colonne === '' || !array_key_exists($colonne, $ligne)) {
        return '';
    }
    return (string) ($ligne[$colonne] ?? '');
}

function famicardRetourModif($flash, $estSaPropreFiche, $cibleId)
{
    $_SESSION['famicard_modif_flash'] = $flash;
    header('Location: modifier.php' . ($estSaPropreFiche ? '' : '?id=' . (int) $cibleId));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    // ── RETRAIT DE LA PHOTO ──────────────────────────────────────────────
    if (isset($_POST['supprimer_photo'])) {
        if (!$photoEditable) {
            famicardRetourModif("Tu n'as pas la main sur cette photo.", $estSaPropreFiche, $cibleId);
        }

        $ancienne = famicardCheminPhoto((string) ($cible['photo_profil'] ?? ''), $storeBase);
        if ($ancienne !== '' && is_file($ancienne)) {
            @unlink($ancienne);
        }
        $db->prepare("UPDATE utilisateurs SET photo_profil = NULL WHERE id = ?")->execute([$cibleId]);
        if ($estSaPropreFiche) {
            $_SESSION['photo_profil'] = null;
        }
        famicardTraceModification($db, $cibleId, 'photo_profil', $champPhoto ?: ['libelle' => 'Photo'], 'photo', '', (int) $moi['id'], false);

        famicardRetourModif('✅ Photo supprimée.', $estSaPropreFiche, $cibleId);
    }

    // ── DÉPÔT D'UNE PHOTO ────────────────────────────────────────────────
    // Traité avant les champs : si l'image est refusée, on le dit tout de
    // suite plutôt que d'enregistrer le reste en laissant croire que tout
    // est passé.
    $photoDeposee = false;
    if ($photoEditable && isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo_profil'];
        $typesAutorises = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $tailleMax = 5 * 1024 * 1024; // 5 Mo

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = "L'envoi de la photo n'a pas abouti.";
        } elseif ($file['size'] > $tailleMax) {
            $erreurs[] = 'Photo trop lourde (5 Mo maximum).';
        } elseif (!in_array($file['type'], $typesAutorises, true)) {
            $erreurs[] = 'Format de photo non accepté : JPEG, PNG, GIF ou WebP.';
        } elseif (!@getimagesize($file['tmp_name'])) {
            // Le type annoncé par le navigateur se falsifie ; le contenu, non.
            $erreurs[] = "Ce fichier n'est pas une image.";
        } else {
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $nomFichier = 'user_' . $cibleId . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $nomFichier;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // L'ancienne part APRÈS que la nouvelle est en place : dans
                // l'autre sens, un échec d'écriture laisserait la fiche sans
                // photo du tout.
                $ancienne = famicardCheminPhoto((string) ($cible['photo_profil'] ?? ''), $storeBase);
                if ($ancienne !== '' && is_file($ancienne) && $ancienne !== $destination) {
                    @unlink($ancienne);
                }

                $compress = famicardRacineSite() . '/includes/compress.php';
                if (is_file($compress)) {
                    require_once $compress;
                    if (function_exists('famiCompressImageFile')) {
                        famiCompressImageFile($destination, 600);
                    }
                }

                $cheminRelatif = 'divers/profils/' . $nomFichier;
                $db->prepare("UPDATE utilisateurs SET photo_profil = ? WHERE id = ?")
                   ->execute([$cheminRelatif, $cibleId]);
                if ($estSaPropreFiche) {
                    // Le ruban du site lit la photo dans la session : sans ça,
                    // l'ancienne resterait affichée jusqu'à la reconnexion.
                    $_SESSION['photo_profil'] = $cheminRelatif;
                }
                famicardTraceModification($db, $cibleId, 'photo_profil', $champPhoto ?: ['libelle' => 'Photo'], '', 'photo', (int) $moi['id'], false);
                $photoDeposee = true;
            } else {
                $erreurs[] = "La photo n'a pas pu être enregistrée.";
            }
        }
    }

    // Le rattachement ne s'écrit pas depuis cet écran (voir plus haut).
    $rattachementChange = false;

    // ── LES AUTRES CHAMPS ────────────────────────────────────────────────
    $aEcrire = [];

    foreach ($champs as $cle => $champ) {
        // Le droit d'écrire est revérifié ici : c'est le seul contrôle qui
        // compte, l'absence du champ dans le formulaire n'en est pas un.
        if (!famicardPeutModifier($champ, $estAdmin, $estSaPropreFiche)) {
            continue;
        }
        // La photo (fichier) et le rattachement (liste unique à deux niveaux)
        // sont traités plus haut, chacun avec sa mécanique.
        if (in_array($champ['saisie'] ?? 'texte', ['photo', 'rattachement'], true)) {
            continue;
        }

        $nomChamp = 'champ_' . $cle;
        if (!array_key_exists($nomChamp, $_POST)) {
            continue;
        }

        $nouvelle = trim((string) $_POST[$nomChamp]);
        $ancienne = famicardValeurBrute($cle, $champ, $cible, $libres);

        // Rien n'a bougé : ni écriture, ni ligne de validation. Sinon
        // l'administrateur croulerait sous des « modifications » où l'ancienne
        // et la nouvelle valeur sont identiques.
        if ($nouvelle === $ancienne) {
            continue;
        }

        // ── Contrôles de saisie, par type ────────────────────────────────
        if ($cle === 'email') {
            if ($nouvelle !== '' && !filter_var($nouvelle, FILTER_VALIDATE_EMAIL)) {
                $erreurs[] = "L'adresse email n'est pas valide.";
                continue;
            }
            if ($nouvelle !== '') {
                // Le site refuse deux comptes avec la même adresse : sans ce
                // test, on créerait ici le doublon qu'il interdit ailleurs.
                $q = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id != ?");
                $q->execute([$nouvelle, $cibleId]);
                if ((int) $q->fetchColumn() > 0) {
                    $erreurs[] = 'Cette adresse email est déjà utilisée par un autre compte.';
                    continue;
                }
            }
        } elseif (($champ['saisie'] ?? '') === 'date') {
            if ($nouvelle !== '') {
                $d = date_create($nouvelle);
                if (!$d) {
                    $erreurs[] = 'La date saisie est incompréhensible.';
                    continue;
                }
                $nouvelle = $d->format('Y-m-d');
            }
        } elseif ($cle === 'site_id') {
            if ($nouvelle !== '' && !isset($magasins[(int) $nouvelle])) {
                $erreurs[] = "Ce lieu de travail n'existe pas.";
                continue;
            }
        } elseif ($cle === 'role') {
            if (!in_array($nouvelle, famicardRolesProposes($cible), true)) {
                $erreurs[] = "Ce profil n'existe pas.";
                continue;
            }
        } elseif ($cle === 'statut') {
            if (!in_array($nouvelle, ['', 'inactif'], true)) {
                $erreurs[] = 'Statut inconnu.';
                continue;
            }
        } elseif (!empty($champ['options'])) {
            // Champ à liste (employeur, contrat, agence) : on n'accepte que ce
            // que la liste proposait — plus la valeur DÉJÀ en place, qu'une
            // agence retirée de `interim_agences` rendrait sinon impossible à
            // conserver, et donc effacée au premier enregistrement.
            $permises = array_keys($champ['options']);
            if ($ancienne !== '') {
                $permises[] = $ancienne;
            }
            if ($nouvelle !== '' && !in_array($nouvelle, $permises, true)) {
                $erreurs[] = 'Valeur inconnue pour « ' . $champ['libelle'] . ' ».';
                continue;
            }
        }

        $aEcrire[$cle] = ['champ' => $champ, 'avant' => $ancienne, 'apres' => $nouvelle];
    }

    if (!$erreurs) {
        // L'admin ne se valide pas lui-même : ce qu'il écrit est tracé, mais
        // déjà tranché.
        $aValider = !$estAdmin;

        foreach ($aEcrire as $cle => $op) {
            famicardEcritValeur($db, $cibleId, $op['champ'], $op['apres']);
            famicardTraceModification($db, $cibleId, $cle, $op['champ'], $op['avant'], $op['apres'], (int) $moi['id'], $aValider);
        }

        $combien = count($aEcrire);
        if ($combien === 0 && !$photoDeposee && !$rattachementChange) {
            famicardRetourModif("Rien n'a changé.", $estSaPropreFiche, $cibleId);
        }

        $bouts = [];
        if ($photoDeposee) {
            $bouts[] = 'photo mise à jour';
        }
        if ($rattachementChange) {
            $bouts[] = 'rattachement modifié';
        }
        if ($combien > 0) {
            $bouts[] = $combien . ' champ' . ($combien > 1 ? 's' : '') . ' modifié' . ($combien > 1 ? 's' : '');
        }
        $texte = '✅ ' . ucfirst(implode(', ', $bouts)) . '.';
        if ($aValider && $combien > 0) {
            $texte .= ' Un administrateur confirmera.';
        }

        famicardRetourModif($texte, $estSaPropreFiche, $cibleId);
    }

    // En cas d'erreur, on réaffiche ce que la personne a saisi plutôt que de
    // lui rendre l'ancienne valeur : elle perdrait sa correction.
    foreach ($aEcrire as $cle => $op) {
        $colonne = (string) ($champs[$cle]['colonne'] ?? '');
        if ($colonne !== '') {
            $cible[$colonne] = $op['apres'];
        } elseif (!empty($champs[$cle]['champ_id'])) {
            $libres[(int) $champs[$cle]['champ_id']] = $op['apres'];
        }
    }
}

/**
 * Les profils proposables. Volontairement sans « agence intérim » : ces comptes
 * ne sont pas des collaborateurs et basculer quelqu'un dedans l'enfermerait
 * hors de Famicard (voir login.php). Le profil actuel est ajouté s'il manque,
 * sinon l'éditer ferait glisser en silence vers le premier de la liste.
 */
function famicardRolesProposes(array $cible)
{
    $roles = ['beta', 'betalapanne', 'etudiant', 'employe_magasin', 'employe_logistique', 'teamcoach', 'mentor', 'evaluateur', 'admin'];
    $actuel = (string) ($cible['role'] ?? '');
    if ($actuel !== '' && !in_array($actuel, $roles, true)) {
        $roles[] = $actuel;
    }
    return $roles;
}

$rolesProposes = famicardRolesProposes($cible);

$nomCible = trim(((string) ($cible['prenom'] ?? '')) . ' ' . ((string) ($cible['nom'] ?? '')));
if ($nomCible === '') {
    $nomCible = (string) ($cible['identifiant'] ?? '');
}

// Aperçu de la photo actuelle. Le paramètre anti-cache est indispensable :
// sans lui, le navigateur réaffiche l'ancienne image et on croit que l'envoi
// a échoué.
$photo = (string) ($cible['photo_profil'] ?? '');
$photoUrl = '';
if ($photo !== '') {
    $photoUrl = function_exists('moduleFileUrl') ? moduleFileUrl($photo) : $photo;
    if ($photoUrl !== '' && !preg_match('#^(https?:)?//#i', $photoUrl)) {
        $photoUrl = famicardSiteUrl($photoUrl);
    }
    $photoUrl .= (strpos($photoUrl, '?') === false ? '?' : '&') . 'v=' . time();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $estSaPropreFiche ? 'Modifier mes informations' : 'Modifier une fiche' ?> - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: url('<?= e(famicardSiteUrl('background.jpg')) ?>') no-repeat center center fixed; background-size: cover; margin: 0; padding: 0 0 40px; color: #333; }
    .top-nav { display: flex; gap: 12px; flex-wrap: wrap; padding: 12px 16px; }
    .pill { background: rgba(255,255,255,.92); padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 10px rgba(0,0,0,.1); text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .9rem; }
    .wrap { max-width: 700px; margin: 0 auto; padding: 0 16px; }

    .boite { background: rgba(255,255,255,.96); border-radius: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; }
    .boite-tete { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 22px 26px; }
    .boite-tete h1 { margin: 0 0 4px; font-size: 1.3rem; font-weight: 800; }
    .boite-tete .sous { font-size: .86rem; opacity: .92; }

    /* ── ZONE PHOTO ─────────────────────────────────────────────────── */
    .zone-photo { padding: 28px 26px; text-align: center; background: #f7faf8; border-bottom: 1px solid #eee; }
    .depose { display: block; cursor: pointer; border: 2px dashed #b9cfc0; border-radius: 18px; padding: 22px; background: #fff; transition: border-color .15s, background .15s; }
    .depose:hover { border-color: #2d5a37; background: #fbfefc; }
    .depose input[type="file"] { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
    .apercu { width: 132px; height: 132px; border-radius: 50%; object-fit: cover; border: 5px solid #2d5a37; margin: 0 auto 14px; display: block; background: #e8f5e9; }
    .apercu-vide { display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #2d5a37; border-style: dashed; }
    .depose .invite { color: #2d5a37; font-weight: 700; font-size: .95rem; }
    .depose .format { color: #8a988f; font-size: .78rem; margin-top: 5px; line-height: 1.5; }
    .depose .choisi { color: #1e5128; font-weight: 700; font-size: .86rem; margin-top: 9px; word-break: break-all; }
    .retirer { background: none; border: 0; color: #a83232; font-family: inherit; font-size: .84rem; font-weight: 700; cursor: pointer; margin-top: 14px; text-decoration: underline; }
    .photo-figee { color: #777; font-size: .88rem; margin-top: 12px; }

    .groupe { padding: 20px 26px; border-top: 1px solid #eee; }
    .groupe h2 { margin: 0 0 14px; font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; color: #2d5a37; }
    .ligne { margin-bottom: 15px; }
    .ligne label { display: block; font-weight: 600; font-size: .86rem; color: #444; margin-bottom: 5px; }
    .ligne .obl { color: #c0392b; }
    input[type="text"], input[type="email"], input[type="date"], select { width: 100%; padding: 10px 12px; border: 1px solid #ccd6cf; border-radius: 10px; font-family: inherit; font-size: .95rem; background: #fff; }
    .aide { color: #888; font-size: .78rem; margin-top: 4px; line-height: 1.45; }
    .fige { background: #f5f7f6; border-radius: 10px; padding: 10px 12px; color: #777; font-size: .92rem; }

    .actions { display: flex; gap: 12px; flex-wrap: wrap; padding: 22px 26px; background: #f7faf8; border-top: 1px solid #eee; }
    .bouton { border: 0; border-radius: 30px; padding: 12px 26px; font-family: inherit; font-weight: 700; font-size: .92rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-vide { background: #fff; color: #2d5a37; border: 1px solid #d3e0d7; }

    .flash { border-radius: 12px; padding: 12px 16px; margin: 16px 0; font-size: .9rem; font-weight: 600; background: #e8f5e9; color: #1e5128; }
    .erreurs { border-radius: 12px; padding: 12px 16px; margin: 16px 0; font-size: .9rem; background: #fdecea; color: #a3271c; }
    .erreurs ul { margin: 6px 0 0; padding-left: 20px; }
    .note { background: rgba(255,255,255,.95); border-left: 5px solid #E9A93C; border-radius: 14px; padding: 14px 18px; margin-top: 20px; font-size: .87rem; line-height: 1.55; color: #7a4a11; }
</style>
</head>
<body>

<div class="top-nav">
    <?php if ($estSaPropreFiche): ?>
        <a class="pill" href="fiche.php">&larr; Ma fiche</a>
        <a class="pill" href="index.php">Accueil</a>
    <?php else: ?>
        <a class="pill" href="admin.php">&larr; Base des collaborateurs</a>
    <?php endif; ?>
</div>

<div class="wrap">

    <?php if ($message !== ''): ?>
        <div class="flash"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($erreurs): ?>
        <div class="erreurs">
            <b>À corriger :</b>
            <ul><?php foreach (array_unique($erreurs) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php // enctype indispensable : sans lui le fichier n'arrive jamais au
          // serveur, et le formulaire semble marcher tout en perdant la photo. ?>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="boite">
            <div class="boite-tete">
                <h1><?= $estSaPropreFiche ? 'Mes informations' : e($nomCible) ?></h1>
                <div class="sous">
                    <?php if ($estSaPropreFiche && !$estAdmin): ?>
                        Tes corrections s'appliquent tout de suite ; un administrateur les confirme ensuite.
                    <?php else: ?>
                        Les modifications s'appliquent immédiatement.
                    <?php endif; ?>
                </div>
            </div>

            <div class="zone-photo">
                <?php if ($photoEditable): ?>
                    <label class="depose">
                        <?php if ($photoUrl !== ''): ?>
                            <img class="apercu" id="apercuPhoto" src="<?= e($photoUrl) ?>" alt="">
                        <?php else: ?>
                            <div class="apercu apercu-vide" id="apercuVide">👤</div>
                            <img class="apercu" id="apercuPhoto" src="" alt="" style="display:none;">
                        <?php endif; ?>
                        <div class="invite">Choisir une photo</div>
                        <div class="format">JPEG, PNG, GIF ou WebP — 5 Mo maximum.<br>Elle est réduite automatiquement.</div>
                        <div class="choisi" id="nomFichier"></div>
                        <input type="file" name="photo_profil" id="champPhoto"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                    </label>

                    <?php if ($photo !== ''): ?>
                        <button type="submit" name="supprimer_photo" value="1" class="retirer"
                                onclick="return confirm('Supprimer la photo ?');">Supprimer la photo</button>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($photoUrl !== ''): ?>
                        <img class="apercu" src="<?= e($photoUrl) ?>" alt="">
                    <?php else: ?>
                        <div class="apercu apercu-vide">👤</div>
                    <?php endif; ?>
                    <div class="photo-figee">Seul le collaborateur dépose sa photo.</div>
                <?php endif; ?>
            </div>

            <?php foreach ($groupes as $cleGroupe => $groupe): ?>
                <?php
                // Un groupe n'est dessiné que s'il contient au moins une ligne
                // à montrer — titre suivi de rien = écran qui a l'air cassé.
                // La photo est exclue : elle a sa zone, en haut.
                $lignes = [];
                foreach ($champs as $cle => $champ) {
                    if (($champ['groupe'] ?? '') !== $cleGroupe) {
                        continue;
                    }
                    if (($champ['saisie'] ?? 'texte') === 'photo') {
                        continue;
                    }
                    if (!famicardPeutVoir($champ, $estAdmin, $estSaPropreFiche)) {
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
                        $editable = famicardPeutModifier($champ, $estAdmin, $estSaPropreFiche);
                        $saisie   = (string) ($champ['saisie'] ?? 'texte');
                        $brute    = famicardValeurBrute($cle, $champ, $cible, $libres);
                        $affichee = famicardValeurAffichee($cle, $champ, $cible, $magasins, $libres);
                        ?>
                        <div class="ligne">
                            <label for="champ_<?= e($cle) ?>">
                                <?= e($champ['libelle']) ?><?php if (!empty($champ['requis'])): ?> <span class="obl">*</span><?php endif; ?>
                            </label>

                            <?php // Le rattachement passe TOUJOURS par ce bloc, même quand il
                                  // n'est pas modifiable : sans ce test en premier, un admin
                                  // se verrait proposer un champ texte libre sur une
                                  // pseudo-colonne, qui n'existe pas dans `utilisateurs`. ?>
                            <?php if ($saisie === 'rattachement'): ?>
                                <div class="fige"><?= $affichee !== '' ? e($affichee) : '—' ?></div>
                                <?php // Le rattachement vit dans la table du matching intérim,
                                      // avec plusieurs départements possibles par personne : il
                                      // ne se règle pas ici, où l'on n'en verrait qu'un. ?>
                                <div class="aide">Se règle avec les départements du collaborateur.</div>

                            <?php elseif (!$editable): ?>
                                <?php // Montré mais figé : le collaborateur voit la valeur et
                                      // comprend qu'elle existe, sans croire qu'il l'a oubliée. ?>
                                <div class="fige"><?= $affichee !== '' ? e($affichee) : '—' ?></div>
                                <div class="aide">Modifiable par un administrateur.</div>

                            <?php elseif ($cle === 'site_id'): ?>
                                <select id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>">
                                    <option value="">— Aucun —</option>
                                    <?php foreach ($magasins as $mid => $mnom): ?>
                                        <option value="<?= (int) $mid ?>" <?= ((string) $brute === (string) $mid) ? 'selected' : '' ?>><?= e($mnom) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($cle === 'role'): ?>
                                <select id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>">
                                    <?php foreach ($rolesProposes as $r): ?>
                                        <option value="<?= e($r) ?>" <?= ($brute === $r) ? 'selected' : '' ?>><?= e(famicardLibelleRole($r)) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($cle === 'statut'): ?>
                                <select id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>">
                                    <option value="" <?= ($brute !== 'inactif') ? 'selected' : '' ?>>Actif</option>
                                    <option value="inactif" <?= ($brute === 'inactif') ? 'selected' : '' ?>>Inactif</option>
                                </select>

                            <?php elseif (!empty($champ['options'])): ?>
                                <?php // Champ à liste posé par le modèle (employeur, contrat,
                                      // agence). Aucun cas particulier ici : le jour où un
                                      // champ à liste s'ajoute, il s'affiche tout seul. ?>
                                <select id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>">
                                    <option value="">— À préciser —</option>
                                    <?php foreach ($champ['options'] as $val => $lib): ?>
                                        <option value="<?= e((string) $val) ?>" <?= ($brute === (string) $val) ? 'selected' : '' ?>><?= e((string) $lib) ?></option>
                                    <?php endforeach; ?>
                                    <?php // La valeur actuelle si la liste ne la contient plus
                                          // (agence supprimée) : sans elle, ouvrir la fiche et
                                          // enregistrer effacerait une donnée qu'on n'a pas touchée. ?>
                                    <?php if ($brute !== '' && !isset($champ['options'][$brute])): ?>
                                        <option value="<?= e($brute) ?>" selected><?= e($brute) ?> (n'est plus dans la liste)</option>
                                    <?php endif; ?>
                                </select>

                            <?php elseif ($saisie === 'date'): ?>
                                <?php // 0000-00-00 traîne dans les vieilles lignes : ce n'est pas
                                      // une date, et le champ HTML la refuserait de toute façon. ?>
                                <input type="date" id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>"
                                       value="<?= ($brute !== '' && $brute !== '0000-00-00') ? e(substr($brute, 0, 10)) : '' ?>">

                            <?php elseif ($saisie === 'email'): ?>
                                <input type="email" id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>" value="<?= e($brute) ?>">

                            <?php else: ?>
                                <input type="text" id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>" value="<?= e($brute) ?>" maxlength="255">
                            <?php endif; ?>

                            <?php // L'explication voyage AVEC le champ, dans le modèle.
                                  // Elle n'est montrée qu'à qui peut écrire : figée, la ligne
                                  // dit déjà « modifiable par un administrateur ». ?>
                            <?php if ($editable && $saisie !== 'rattachement' && !empty($champ['aide'])): ?>
                                <div class="aide"><?= e((string) $champ['aide']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="actions">
                <button type="submit" class="bouton bouton-plein">Enregistrer</button>
                <a class="bouton bouton-vide" href="<?= $estSaPropreFiche ? 'fiche.php' : 'admin.php' ?>">Annuler</a>
            </div>
        </div>
    </form>

    <?php if ($estSaPropreFiche && !$estAdmin): ?>
        <div class="note">
            Ce que tu corriges est visible immédiatement. Un administrateur voit ensuite
            l'ancienne et la nouvelle valeur, et confirme — ou rétablit si c'était une erreur.
            Ta photo, elle, est libre : personne n'a à la valider.
        </div>
    <?php endif; ?>

</div>


<?php if ($photoEditable): ?>
<script>
// Aperçu immédiat de l'image choisie. Sans lui, on ne sait pas si le bon
// fichier a été pris avant d'avoir enregistré — et sur mobile, le nom seul ne
// dit rien (« IMG_4821.jpg »).
(function () {
    var champ = document.getElementById('champPhoto');
    if (!champ) { return; }

    champ.addEventListener('change', function () {
        var f = champ.files && champ.files[0];
        if (!f) { return; }

        var nom = document.getElementById('nomFichier');
        if (nom) { nom.textContent = f.name; }

        var img = document.getElementById('apercuPhoto');
        var vide = document.getElementById('apercuVide');
        if (img && window.FileReader) {
            var lecteur = new FileReader();
            lecteur.onload = function (e) {
                img.src = e.target.result;
                img.style.display = 'block';
                if (vide) { vide.style.display = 'none'; }
            };
            lecteur.readAsDataURL(f);
        }
    });
}());
</script>
<?php endif; ?>

</body>
</html>
