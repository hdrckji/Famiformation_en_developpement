<?php
// ============================================================
// creer.php — CRÉER UN COLLABORATEUR.
//
// LA PIÈCE MAÎTRESSE DU TRI. « Famicard possède la personne » (README.md) :
// un compte naît donc ICI, dans le centre de données utilisateur, et pas sur
// la plateforme de formation. C'était la première ligne de « Ce qui reste ».
//
// ⚠️ ÉTAT TRANSITOIRE À CONNAÎTRE. Le formulaire de FamiFormation
// (`admin_collaborateurs.php`) EXISTE TOUJOURS et crée toujours des comptes :
// `Famiformation/` est une copie conforme du live, on n'y touche pas depuis
// Famicard. Deux écrans peuvent donc créer un compte, et c'est acceptable —
// une création est un INSERT, pas deux écritures concurrentes de la même
// colonne (c'est CE cas-là que le README interdit). Le jour où le site perdra
// son formulaire, cette page n'aura rien à changer.
//
// LES RÈGLES DE CRÉATION sont celles du site, volontairement à l'identique :
// on ne réécrit pas la règle métier en même temps qu'on déplace l'écran.
//   • obligatoires : identifiant, nom, prénom, profil (+ agence si étudiant) ;
//   • au moins l'un des deux : email ou mot de passe — sans quoi personne ne
//     peut se connecter au compte qu'on vient de créer ;
//   • identifiant et email UNIQUES (deux comptes sur la même adresse rendent
//     la relance de mot de passe impossible à viser) ; nom + prénom identiques
//     restent autorisés, deux personnes peuvent porter le même nom.
//
// UNE SEULE RÈGLE A CHANGÉ, et c'en est une qui coûtait un compte :
// le site annule la création quand le mail ne part pas — y compris quand un
// MOT DE PASSE a été défini, c'est-à-dire quand le compte est parfaitement
// utilisable sans aucun mail. Ici :
//   • sans mot de passe, le mail d'activation reste INDISPENSABLE (c'est le
//     seul chemin vers une connexion) : s'il ne part pas, rien n'est créé ;
//   • avec un mot de passe, le compte est créé, et un échec d'envoi est
//     signalé sans détruire le travail — le mail se renvoie depuis
//     « Relance mot de passe ».
//
// CE QUE CETTE PAGE ÉCRIT EN PLUS DU COMPTE, parce que c'est le seul moment où
// on a la personne complète sous les yeux :
//   • son rattachement (`student_department_links`) — sans risque ici : la
//     personne vient de naître, il n'y a aucune priorité existante à écraser,
//     ce qui est précisément ce qui bloque l'édition du rattachement ailleurs ;
//   • ses accès aux services (`famicard_acces`) — rien de coché = on ne
//     enregistre RIEN, donc les règles historiques continuent de s'appliquer
//     (voir includes/services.php : une liste vide enregistrée signifierait
//     « aucun accès », ce qui n'est pas la même chose).
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/modifications.php';
require_once __DIR__ . '/includes/services.php';

famicardExigeConnexion($db); // et non verifierConnexion() : voir Famicard/README
// csrf.php est déjà chargé par la configuration du site.
require_once famicardRacineSite() . '/includes/events.php'; // logEvent() : trace des créations

// Créer un compte, c'est ouvrir un accès : réservé à l'administrateur, comme
// la base des collaborateurs et la relance de mot de passe.
if (!famicardEstAdmin()) {
    header('Location: index.php');
    exit();
}

$moiId = (int) ($_SESSION['user_id'] ?? 0);

// Colonnes de jeton et d'activation. C'est une page d'administration, pas un
// chemin chaud : la DDL y est admise (même choix que relance_mdp.php).
ensureUserAccountAccessColumns($db);
famicardAssureModifications($db);
try {
    famicardAssureServices($db);
} catch (Exception $e) {
    // Droits insuffisants : les cases « accès » ne s'afficheront pas, la
    // création reste possible et les règles historiques s'appliquent.
}

// ─────────────────────────────────────────────────────────────────────────────
// CE QUE LA BASE ACCEPTE VRAIMENT
//
// Famicard ne possède PAS le schéma de `utilisateurs` : cette table est celle
// de FamiFormation et de FamiJob. On lit donc ses colonnes au lieu de les
// supposer — une colonne absente fait sauter le champ correspondant, pas
// l'enregistrement tout entier. Sans ça, une base un peu ancienne renverrait
// « colonne inconnue » au moment précis où l'on crée un collaborateur.
// ─────────────────────────────────────────────────────────────────────────────
$colonnes = [];
try {
    foreach ($db->query("SHOW COLUMNS FROM utilisateurs")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $colonnes[(string) $c['Field']] = true;
    }
} catch (Exception $e) {
    $colonnes = [];
}
$aInterim    = isset($colonnes['interim']);
$aSiteId     = isset($colonnes['site_id']);
$aStatutDate = isset($colonnes['statut_date']);

// ─────────────────────────────────────────────────────────────────────────────
// LES LISTES PROPOSÉES
// ─────────────────────────────────────────────────────────────────────────────

// Profils, dans l'ordre de la liste du site. `agence_interim` n'y figure pas :
// ce n'est pas un collaborateur, ces comptes se créent dans la page des agences.
$ROLES_CREATION = [
    'beta', 'betalapanne', 'etudiant', 'employe_magasin',
    'employe_logistique', 'teamcoach', 'mentor', 'evaluateur', 'admin',
];

$agences = [];
try {
    if ($db->query("SHOW TABLES LIKE 'interim_agences'")->fetch()) {
        $agences = $db->query("SELECT nom_agence FROM interim_agences ORDER BY nom_agence ASC")
                      ->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $agences = [];
}

$magasins = famicardMagasins($db);
$services = famicardServices($db);

// ─────────────────────────────────────────────────────────────────────────────
// VALEURS RÉAFFICHÉES. Une erreur ne doit pas vider le formulaire : quelqu'un
// qui vient de saisir huit champs et à qui on répond « identifiant déjà pris »
// ne doit pas tout retaper.
//
// 🧪 Un nouvel arrivant est en BETA par défaut, comme les inscriptions faites
// depuis le quiz. Choisir un autre profil dans la liste suffit à passer outre.
// ─────────────────────────────────────────────────────────────────────────────
$saisie = [
    'identifiant' => '', 'nom' => '', 'prenom' => '', 'email' => '',
    'role' => 'beta', 'interim' => '', 'site_id' => '', 'departement' => '',
    'acces' => [], 'envoyer_mail' => true,
];

$flash = '';
// Message survivant à la redirection (motif Post/Redirect/Get) : sans lui, un
// rafraîchissement après création recréerait un second compte.
if (!empty($_SESSION['famicard_creation_flash'])) {
    $flash = (string) $_SESSION['famicard_creation_flash'];
    unset($_SESSION['famicard_creation_flash']);
}

// ─────────────────────────────────────────────────────────────────────────────
// CRÉATION
// ─────────────────────────────────────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'creer') {
    requireValidCSRF();

    $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
    $nom         = trim((string) ($_POST['nom'] ?? ''));
    $prenom      = trim((string) ($_POST['prenom'] ?? ''));
    $email       = trim((string) ($_POST['email'] ?? ''));
    $mdp         = (string) ($_POST['mot_de_passe'] ?? '');
    $role        = (string) ($_POST['role'] ?? '');
    $interim     = trim((string) ($_POST['interim'] ?? ''));
    $siteId      = trim((string) ($_POST['site_id'] ?? ''));
    $departement = (int) ($_POST['departement'] ?? 0);
    $accesChoisi = array_map('intval', (array) ($_POST['acces'] ?? []));
    $envoyerMail = !empty($_POST['envoyer_mail']);

    $saisie = [
        'identifiant' => $identifiant, 'nom' => $nom, 'prenom' => $prenom,
        'email' => $email, 'role' => $role !== '' ? $role : 'beta',
        'interim' => $interim, 'site_id' => $siteId,
        'departement' => $departement > 0 ? (string) $departement : '',
        'acces' => $accesChoisi, 'envoyer_mail' => $envoyerMail,
    ];

    // Les listes sont contraintes à ce que la page a réellement proposé : un
    // champ bricolé dans le formulaire ne peut pas poser un profil inventé ni
    // un lieu de travail qui n'existe pas.
    $erreurs = [];
    if ($identifiant === '') { $erreurs[] = "l'identifiant"; }
    if ($nom === '')         { $erreurs[] = 'le nom'; }
    if ($prenom === '')      { $erreurs[] = 'le prénom'; }
    if (!in_array($role, $ROLES_CREATION, true)) { $erreurs[] = 'le profil'; }
    // L'agence n'est exigée que si la colonne existe : sans elle, le champ n'est
    // même pas proposé, et l'exiger rendrait tout étudiant impossible à créer.
    if ($aInterim && $role === 'etudiant' && $interim === '') {
        $erreurs[] = "l'agence intérim (obligatoire pour un étudiant)";
    }

    $siteStore = ($siteId !== '' && isset($magasins[(int) $siteId])) ? (int) $siteId : null;
    $interimStore = ($interim === '') ? null : $interim;

    if ($erreurs) {
        $flash = "<div class='flash err'>❌ Il manque " . e(implode(', ', $erreurs)) . '.</div>';
    } elseif ($email === '' && $mdp === '') {
        $flash = "<div class='flash err'>❌ Renseigne au moins un <b>email</b> ou un <b>mot de passe</b> :"
               . ' sans l\'un des deux, personne ne peut se connecter à ce compte.</div>';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash = "<div class='flash err'>❌ Cette adresse email n'est pas valide.</div>";
    } else {
        $dejaId = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE identifiant = ?");
        $dejaId->execute([$identifiant]);
        $identifiantPris = ((int) $dejaId->fetchColumn() > 0);

        $emailPris = false;
        if ($email !== '') {
            $dejaMail = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
            $dejaMail->execute([$email]);
            $emailPris = ((int) $dejaMail->fetchColumn() > 0);
        }

        if ($identifiantPris) {
            $flash = "<div class='flash err'>❌ Cet identifiant est déjà utilisé. Choisis-en un autre.</div>";
        } elseif ($emailPris) {
            $flash = "<div class='flash err'>❌ Cette adresse est déjà celle d'un autre compte."
                   . ' Deux comptes sur la même adresse rendraient toute relance de mot de passe impossible à viser.</div>';
        } else {
            // Toujours un hash, même sans mot de passe choisi : une colonne vide
            // se comparerait à n'importe quoi le jour où un test de connexion
            // serait écrit à la légère. Un secret aléatoire ne s'ouvre pas, et
            // c'est le lien d'activation qui le remplacera.
            $hash = ($mdp !== '')
                ? password_hash($mdp, PASSWORD_DEFAULT)
                : password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

            $donnees = [
                'identifiant'                => $identifiant,
                'nom'                        => $nom,
                'prenom'                     => $prenom,
                'email'                      => ($email === '') ? null : $email,
                'mot_de_passe'               => $hash,
                'role'                       => $role,
                'account_activation_pending' => ($mdp === '') ? 1 : 0,
            ];
            if ($aInterim)    { $donnees['interim'] = $interimStore; }
            if ($aSiteId)     { $donnees['site_id'] = $siteStore; }
            // NOW() serait équivalent, mais une valeur liée garde la requête
            // construite d'une seule façon — colonnes connues, valeurs liées.
            if ($aStatutDate) { $donnees['statut_date'] = date('Y-m-d H:i:s'); }

            $userId = 0;
            $creationOk = false;
            $envoi = null; // true/false quand un mail a été tenté

            try {
                $db->beginTransaction();

                $noms = array_keys($donnees); // littéraux de ce fichier, jamais de l'URL
                $sql = 'INSERT INTO utilisateurs (`' . implode('`, `', $noms) . '`) VALUES ('
                     . implode(', ', array_fill(0, count($noms), '?')) . ')';
                $db->prepare($sql)->execute(array_values($donnees));
                $userId = (int) $db->lastInsertId();

                // Rattachement. Le compte vient de naître : aucune priorité
                // existante ne peut être écrasée, contrairement à une édition.
                if ($userId > 0 && $departement > 0) {
                    try {
                        $db->prepare(
                            "INSERT INTO student_department_links (student_id, department_id, priority_rank)
                             VALUES (?, ?, 1)
                             ON DUPLICATE KEY UPDATE priority_rank = VALUES(priority_rank)"
                        )->execute([$userId, $departement]);
                    } catch (Exception $e) {
                        // Table du matching absente : le compte reste valable,
                        // le rattachement se posera depuis FamiJob.
                    }
                }

                // Accès explicites — seulement si quelque chose a été coché.
                if ($userId > 0 && $accesChoisi) {
                    $valides = array_values(array_filter($accesChoisi, static function ($id) use ($services) {
                        return isset($services[(int) $id]);
                    }));
                    if ($valides) {
                        famicardDefinitAcces($db, $userId, $valides, $moiId);
                    }
                }

                if ($email === '') {
                    // Aucune adresse : aucun envoi, et donc aucun échec possible.
                    $db->commit();
                    $creationOk = true;
                } elseif ($mdp === '') {
                    // Sans mot de passe, le mail d'activation EST le compte :
                    // s'il ne part pas, on ne laisse pas derrière nous un
                    // identifiant que personne ne peut ouvrir.
                    $envoi = sendAccountActivationEmail($db, $userId);
                    if ($envoi) {
                        $db->commit();
                        $creationOk = true;
                    } else {
                        $db->rollBack();
                        $detail = trim((string) getLastMailError());
                        $flash = "<div class='flash err'>❌ Le mail d'activation n'est pas parti :"
                               . " le compte n'a donc <b>pas été créé</b> (sans ce mail et sans mot de passe,"
                               . ' il serait resté impossible à ouvrir). Réessaie, ou donne-lui un mot de passe.'
                               . ($detail !== '' ? '<br><small>Détail technique : ' . e($detail) . '</small>' : '')
                               . '</div>';
                    }
                } else {
                    // Mot de passe défini : le compte fonctionne, mail ou pas.
                    $db->commit();
                    $creationOk = true;
                    if ($envoyerMail) {
                        $envoi = sendStudentWelcomeEmail($email, $identifiant, null);
                    }
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) { $db->rollBack(); }
                $flash = "<div class='flash err'>❌ La création a échoué : " . e($e->getMessage()) . '</div>';
            }

            if ($creationOk && $userId > 0) {
                // Traces APRÈS le commit : enregistrées dans la transaction,
                // elles disparaîtraient avec elle en cas de retour arrière.
                famicardTraceModification(
                    $db, $userId, 'compte',
                    ['libelle' => 'Création du compte'],
                    null,
                    $identifiant . ' · ' . famicardLibelleRole($role),
                    $moiId,
                    false // c'est l'admin qui crée : rien à faire confirmer
                );
                if (function_exists('logEvent')) {
                    @logEvent($db, 'user_created', $moiId, 0,
                        'Compte créé depuis Famicard : ' . $identifiant . ' (' . $role . ')');
                }

                $qui = e(trim($prenom . ' ' . $nom));
                $lien = ' <a href="modifier.php?id=' . (int) $userId . '">Ouvrir sa fiche</a>';
                if ($email === '') {
                    $note = ($mdp === '')
                        ? " ⚠️ Ni email ni mot de passe : ce compte ne peut pas encore être ouvert."
                        : ' Aucun email : préviens-le de son identifiant et de son mot de passe.';
                    $msg = "<div class='flash ok'>✅ <b>$qui</b> est créé." . $note . $lien . '</div>';
                } elseif ($mdp === '') {
                    $msg = "<div class='flash ok'>✅ <b>$qui</b> est créé."
                         . ' 📨 Son mail d\'activation est parti à <b>' . e($email) . '</b>.'
                         . ' Il reste « en attente » tant qu\'il n\'a pas choisi son mot de passe.'
                         . $lien . '</div>';
                } elseif ($envoi === true) {
                    $msg = "<div class='flash ok'>✅ <b>$qui</b> est créé et peut se connecter."
                         . ' 📨 Le mail de bienvenue est parti à <b>' . e($email) . '</b>.' . $lien . '</div>';
                } elseif ($envoi === false) {
                    $detail = trim((string) getLastMailError());
                    $msg = "<div class='flash ok'>✅ <b>$qui</b> est créé et <b>peut se connecter</b>"
                         . ' (son mot de passe est défini).'
                         . ' ⚠️ En revanche le mail de bienvenue n\'est pas parti'
                         . ($detail !== '' ? ' — ' . e($detail) : '')
                         . '. Tu peux le relancer depuis « Relance mot de passe ».' . $lien . '</div>';
                } else {
                    $msg = "<div class='flash ok'>✅ <b>$qui</b> est créé et peut se connecter."
                         . ' Aucun mail n\'a été envoyé, comme demandé.' . $lien . '</div>';
                }

                $_SESSION['famicard_creation_flash'] = $msg;
                header('Location: creer.php');
                exit();
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LES DERNIERS COMPTES CRÉÉS. Une création est invisible : cette liste est la
// preuve que le compte existe, et le chemin le plus court vers sa fiche.
// ─────────────────────────────────────────────────────────────────────────────
$derniers = [];
try {
    $derniers = $db->query(
        "SELECT id, identifiant, nom, prenom, email, role, account_activation_pending
         FROM utilisateurs ORDER BY id DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $derniers = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau collaborateur - Famicard</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Open Sans', sans-serif; background: #eef4ef; margin: 0; padding: 24px 16px 60px; color: #244230; }
        .wrap { max-width: 960px; margin: 0 auto; }
        h1 { color: #2d5a37; font-size: 1.6rem; margin: 0 0 4px; }
        .sub { color: #5a6b60; margin: 0 0 18px; line-height: 1.55; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; margin-bottom: 16px; box-shadow: 0 6px 20px rgba(14,59,36,.08); border: 1px solid #e6efe8; }
        .card h2 { margin: 0 0 14px; font-size: 1.15rem; color: #2d5a37; }
        .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 600; line-height: 1.6; }
        .flash.ok { background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; }
        .flash.err { background: #fdeaea; border: 1px solid #f3c2c2; color: #a12; }
        .flash a { color: inherit; font-weight: 800; }
        .note { background: #fff8e1; border: 1px solid #ffe082; color: #6a5400; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; line-height: 1.55; font-size: .92rem; }
        .btn { display: inline-block; border: none; cursor: pointer; background: #2d5a37; color: #fff; font-weight: 700; padding: 11px 22px; border-radius: 999px; text-decoration: none; font-size: .95rem; font-family: inherit; }
        .btn.ghost { background: #fff; color: #2d5a37; border: 2px solid #2d5a37; }
        .top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }

        .grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; }
        .champ { display: flex; flex-direction: column; }
        .champ label { font-weight: 700; font-size: .86rem; margin-bottom: 6px; }
        .champ .aide { font-weight: 400; color: #8a968f; }
        .champ input, .champ select { padding: 10px 12px; border: 1px solid #cfe0d4; border-radius: 10px; font-family: inherit; font-size: .95rem; background: #fff; color: inherit; width: 100%; }
        .champ input:focus, .champ select:focus { outline: 2px solid #b7e0c1; border-color: #2d5a37; }
        .requis { color: #a12; }
        .duo { display: flex; gap: 8px; flex-wrap: wrap; }
        .duo select { flex: 1 1 140px; min-width: 0; }
        .secteur-select, .departement-select { padding: 10px 12px; border: 1px solid #cfe0d4; border-radius: 10px; font-family: inherit; font-size: .95rem; background: #fff; }
        .cases { display: flex; flex-wrap: wrap; gap: 9px; }
        .case { display: flex; align-items: center; gap: 9px; border: 1px solid #cfe0d4; border-radius: 12px; padding: 9px 13px; cursor: pointer; background: #f8fbf9; font-size: .9rem; }
        .case:hover { border-color: #2d5a37; }
        .case input { margin: 0; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-top: 20px; }
        .rappel { font-size: .88rem; color: #5a6b60; line-height: 1.6; margin: 14px 0 0; }

        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid #eef2ef; }
        th { color: #6a7d72; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
        .tag { border-radius: 999px; padding: 3px 11px; font-size: .76rem; font-weight: 800; white-space: nowrap; }
        .tag.role { background: #eef4ef; color: #3d6b48; }
        .tag.attente { background: #fff3cd; color: #856404; }
        .tag.ok { background: #e7f6ea; color: #1E7A46; }
        .lien-fiche { color: #2d5a37; font-weight: 700; text-decoration: none; }
        .lien-fiche:hover { text-decoration: underline; }
        .scroll { overflow-x: auto; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>➕ Nouveau collaborateur</h1>
    <p class="sub">Créer le compte d'un arrivant : son identité, son profil, et ce à quoi il a accès.</p>

    <div class="top">
        <a href="index.php" class="btn ghost">← Accueil</a>
        <a href="admin.php" class="btn ghost">📇 Base des collaborateurs</a>
    </div>

    <?= $flash ?>

    <div class="note">
        ℹ️ Il faut <b>au moins un email ou un mot de passe</b> : c'est ce qui rend le compte ouvrable.<br>
        📨 <b>Email seul</b> → un mail d'activation part, la personne choisit son mot de passe elle-même.
        S'il ne part pas, rien n'est créé — un compte que personne ne peut ouvrir ne rend service à personne.<br>
        🔑 <b>Mot de passe</b> → le compte est utilisable tout de suite ; le mail n'est alors qu'une politesse,
        et son échec n'annule pas la création.
    </div>

    <form method="POST" action="creer.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="creer">

        <div class="card">
            <h2>Qui est-ce ?</h2>
            <div class="grille">
                <div class="champ">
                    <label for="prenom">Prénom <span class="requis">*</span></label>
                    <input type="text" id="prenom" name="prenom" value="<?= e($saisie['prenom']) ?>" required>
                </div>
                <div class="champ">
                    <label for="nom">Nom <span class="requis">*</span></label>
                    <input type="text" id="nom" name="nom" value="<?= e($saisie['nom']) ?>" required>
                </div>
                <div class="champ">
                    <label for="identifiant">Identifiant <span class="requis">*</span>
                        <span class="aide">— il sert à se connecter</span></label>
                    <input type="text" id="identifiant" name="identifiant" value="<?= e($saisie['identifiant']) ?>"
                           autocomplete="off" required>
                </div>
                <div class="champ">
                    <label for="email">Email <span class="aide">— pour le mail d'activation</span></label>
                    <input type="email" id="email" name="email" value="<?= e($saisie['email']) ?>" autocomplete="off">
                </div>
            </div>
            <p class="rappel">
                Le reste de la fiche — photo, ville, date d'anniversaire — se complète ensuite,
                par le collaborateur lui-même ou depuis sa fiche.
            </p>
        </div>

        <div class="card">
            <h2>Son compte</h2>
            <div class="grille">
                <div class="champ">
                    <label for="role">Profil <span class="requis">*</span></label>
                    <select id="role" name="role" required>
                        <?php foreach ($ROLES_CREATION as $r): ?>
                            <option value="<?= e($r) ?>"<?= $saisie['role'] === $r ? ' selected' : '' ?>>
                                <?= e(famicardLibelleRole($r)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ">
                    <label for="mot_de_passe">Mot de passe <span class="aide">— facultatif</span></label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="new-password"
                           placeholder="laisser vide = mail d'activation">
                </div>
            </div>
            <?php // La case ne concerne que le cas « mot de passe défini » : sans
                  // mot de passe, le mail d'activation part de toute façon, c'est
                  // le seul chemin vers une connexion. Le texte le dit. ?>
            <div class="cases" style="margin-top:16px;">
                <label class="case">
                    <input type="checkbox" name="envoyer_mail" value="1"<?= $saisie['envoyer_mail'] ? ' checked' : '' ?>>
                    <span>Envoyer un mail de bienvenue</span>
                </label>
            </div>
            <p class="rappel">
                Sans mot de passe, le mail d'<b>activation</b> est envoyé quoi qu'il arrive : c'est lui qui permet
                d'en choisir un. Cette case ne concerne donc que les comptes créés <b>avec</b> un mot de passe.
            </p>
        </div>

        <div class="card">
            <h2>Où travaille-t-il ?</h2>
            <div class="grille">
                <?php if ($aInterim): ?>
                <div class="champ">
                    <label for="interim">Agence intérim
                        <span class="aide">— obligatoire pour un étudiant</span></label>
                    <select id="interim" name="interim">
                        <option value="">— Aucune agence —</option>
                        <?php foreach ($agences as $a): ?>
                            <option value="<?= e($a) ?>"<?= $saisie['interim'] === $a ? ' selected' : '' ?>><?= e($a) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($aSiteId && $magasins): ?>
                <div class="champ">
                    <label for="site_id">Lieu de travail</label>
                    <select id="site_id" name="site_id">
                        <option value="">— Non défini —</option>
                        <?php foreach ($magasins as $id => $nomSite): ?>
                            <option value="<?= (int) $id ?>"<?= $saisie['site_id'] === (string) $id ? ' selected' : '' ?>><?= e($nomSite) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (function_exists('secteursChampsHtml')): ?>
                <div class="champ">
                    <label>Secteur et département
                        <span class="aide">— facultatif</span></label>
                    <div class="duo">
                        <?= secteursChampsHtml($db, 'departement', $saisie['departement'], [
                                'par' => 'id', 'vide' => '— Aucun département —',
                            ]) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php if (function_exists('secteursChampsHtml')): ?>
            <p class="rappel">
                Le rattachement peut être <b>multiple</b> et classé par priorité — c'est le matching intérim qui
                l'impose. On en pose <b>un</b> ici, le principal ; les suivants s'ajoutent depuis FamiJob.
            </p>
            <?php endif; ?>
        </div>

        <?php if ($services): ?>
        <div class="card">
            <h2>À quoi a-t-il accès ?</h2>
            <div class="cases">
                <?php foreach ($services as $sid => $s): ?>
                    <label class="case">
                        <input type="checkbox" name="acces[]" value="<?= (int) $sid ?>"
                               <?= in_array((int) $sid, (array) $saisie['acces'], true) ? 'checked' : '' ?>>
                        <span><?= e((string) ($s['icone'] ?: '🔗')) ?> <?= e((string) $s['nom']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="rappel">
                <b>Rien de coché = les règles habituelles s'appliquent</b> (FamiFormation pour tout le monde,
                FamiJob pour les admins et les teamcoachs). Cocher quelque chose fige la liste : elle fait alors
                foi, et rien d'autre ne lui sera ouvert automatiquement.
            </p>
        </div>
        <?php endif; ?>

        <div class="actions">
            <button type="submit" class="btn">Créer le collaborateur</button>
            <span class="rappel" style="margin:0;">Les champs marqués <span class="requis">*</span> sont obligatoires.</span>
        </div>
    </form>

    <?php if ($derniers): ?>
    <div class="card" style="margin-top:24px;">
        <h2>🕘 Derniers comptes créés</h2>
        <div class="scroll">
            <table>
                <tr><th>Nom</th><th>Identifiant</th><th>Profil</th><th>Adresse</th><th>Mot de passe</th><th></th></tr>
                <?php foreach ($derniers as $u): ?>
                    <tr>
                        <td><?= e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?: '—' ?></td>
                        <td><?= e((string) $u['identifiant']) ?></td>
                        <td><span class="tag role"><?= e(famicardLibelleRole($u['role'])) ?></span></td>
                        <td><?= e((string) ($u['email'] ?? '')) ?: '—' ?></td>
                        <td>
                            <?php if (!empty($u['account_activation_pending'])): ?>
                                <span class="tag attente">Pas encore créé</span>
                            <?php else: ?>
                                <span class="tag ok">Défini</span>
                            <?php endif; ?>
                        </td>
                        <td><a class="lien-fiche" href="modifier.php?id=<?= (int) $u['id'] ?>">Sa fiche →</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php // Le filtrage secteur → département. Une seule fois par page, il gère
      // tous les menus, et la page reste utilisable si le script ne part pas. ?>
<?= function_exists('secteursScript') ? secteursScript() : '' ?>
</body>
</html>
