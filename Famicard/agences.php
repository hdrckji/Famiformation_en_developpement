<?php
// ============================================================
// agences.php — LES AGENCES D'INTÉRIM, ET LEURS ACCÈS.
//
// UNE AGENCE N'EST PAS UN COLLABORATEUR. C'est une société extérieure à qui
// l'on ouvre une porte pour qu'elle voie SES intérimaires : leurs horaires,
// leurs disponibilités. Elle n'a ni fiche, ni photo, ni contrat, ni secteur —
// ces questions n'ont aucune réponse pour elle.
//
// D'où cet écran, séparé de « Mes collaborateurs » (décision de Jimmy). Les
// comptes `agence_interim` y étaient mélangés aux gens, et remplissaient la
// base de lignes vides qu'on prenait pour des fiches incomplètes.
//
// ── DEUX CHOSES DIFFÉRENTES, SUR LE MÊME ÉCRAN ──────────────────────────────
//   • L'AGENCE      (`interim_agences`) — un nom, un contact, des adresses.
//     C'est elle qui reçoit les horaires de ses intérimaires.
//   • SON ACCÈS     (un compte `utilisateurs` de profil `agence_interim`) —
//     de quoi se connecter et consulter. Une agence peut n'en avoir aucun.
//
// ⚠️ CE QUI RELIE LES DEUX EST LE NOM, pas un identifiant. FamiJob compare
// `utilisateurs.interim` au nom de l'agence pour décider qui voit quoi. C'est
// fragile, et ça a une conséquence que la page du site ne traitait pas :
// RENOMMER UNE AGENCE COUPAIT TOUT LE MONDE. Le nom changeait dans
// `interim_agences`, les fiches gardaient l'ancien, et l'agence se retrouvait
// avec un écran vide sans que rien ne le signale. Ici, un renommage réécrit
// aussi les fiches concernées — voir la transaction plus bas.
//
// ⚠️ « FAMIFLORA » N'EST PAS UNE AGENCE. Sa ligne existe pour ranger les
// recrutements directs, et le service RH la relève. Elle est affichée à part et
// ne se supprime pas depuis ici (voir includes/emploi.php).
// ============================================================
require_once __DIR__ . '/config.php';

famicardExigeConnexion($db);

if (!famicardEstAdmin()) {
    header('Location: index.php');
    exit();
}

$moiId = (int) ($_SESSION['user_id'] ?? 0);

// ─────────────────────────────────────────────────────────────────────────────
// LES TABLES. Ce sont celles du site (`interim_agences`,
// `interim_agence_users`) : Famicard n'en ouvre pas de nouvelles, il gère
// celles qui tournent. Créées ici si elles manquent, exactement comme le fait
// admin_agences_interim.php — c'est une page d'administration, la DDL y est
// admise.
// ─────────────────────────────────────────────────────────────────────────────
$tablesOk = true;
try {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS interim_agences (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nom_agence VARCHAR(255) NOT NULL,
            nom_contact VARCHAR(255) NOT NULL,
            email_1 VARCHAR(255) DEFAULT NULL,
            email_2 VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS interim_agence_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agence_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_interim_agence_user_user (user_id),
            KEY idx_interim_agence_users_agence (agence_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
} catch (Exception $e) {
    $tablesOk = false;
}

$flash = '';
if (!empty($_SESSION['famicard_agences_flash'])) {
    $flash = (string) $_SESSION['famicard_agences_flash'];
    unset($_SESSION['famicard_agences_flash']);
}

/** Post/Redirect/Get : un rafraîchissement ne doit pas rejouer une création. */
function agencesRetour($message)
{
    $_SESSION['famicard_agences_flash'] = $message;
    header('Location: agences.php');
    exit();
}

/** Les deux adresses d'une agence, contrôlées. '' si aucune n'est valide. */
function agencesLitEmails(&$erreur)
{
    $erreur = '';
    $emails = [];
    foreach (['email_1', 'email_2'] as $cle) {
        $valeur = trim((string) ($_POST[$cle] ?? ''));
        if ($valeur === '') {
            $emails[$cle] = null;
            continue;
        }
        if (!filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
            $erreur = "L'adresse « " . $valeur . " » n'est pas valide.";
            return $emails;
        }
        $emails[$cle] = $valeur;
    }
    return $emails;
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTIONS
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tablesOk) {
    requireValidCSRF();
    $action = (string) ($_POST['action'] ?? '');

    // ── CRÉER UNE AGENCE ─────────────────────────────────────────────────
    if ($action === 'creer_agence') {
        $nom = trim((string) ($_POST['nom_agence'] ?? ''));
        $contact = trim((string) ($_POST['nom_contact'] ?? ''));
        $emails = agencesLitEmails($erreurMail);

        if ($nom === '' || $contact === '') {
            agencesRetour("❌ Le nom de l'agence et celui du contact sont obligatoires.");
        }
        if ($erreurMail !== '') {
            agencesRetour('❌ ' . $erreurMail);
        }
        // Deux agences du même nom rendraient l'aiguillage des horaires
        // indécidable : `utilisateurs.interim` ne porte qu'un nom.
        $q = $db->prepare('SELECT COUNT(*) FROM interim_agences WHERE nom_agence = ?');
        $q->execute([$nom]);
        if ((int) $q->fetchColumn() > 0) {
            agencesRetour('❌ Une agence porte déjà ce nom.');
        }

        $db->prepare('INSERT INTO interim_agences (nom_agence, nom_contact, email_1, email_2) VALUES (?, ?, ?, ?)')
           ->execute([$nom, $contact, $emails['email_1'], $emails['email_2']]);
        agencesRetour('✅ Agence « ' . e($nom) . ' » ajoutée.');
    }

    // ── MODIFIER UNE AGENCE ──────────────────────────────────────────────
    if ($action === 'modifier_agence') {
        $id = (int) ($_POST['agence_id'] ?? 0);
        $nom = trim((string) ($_POST['nom_agence'] ?? ''));
        $contact = trim((string) ($_POST['nom_contact'] ?? ''));
        $emails = agencesLitEmails($erreurMail);

        if ($id <= 0 || $nom === '' || $contact === '') {
            agencesRetour("❌ Le nom de l'agence et celui du contact sont obligatoires.");
        }
        if ($erreurMail !== '') {
            agencesRetour('❌ ' . $erreurMail);
        }

        $q = $db->prepare('SELECT nom_agence FROM interim_agences WHERE id = ? LIMIT 1');
        $q->execute([$id]);
        $ancienNom = (string) $q->fetchColumn();
        if ($ancienNom === '') {
            agencesRetour('❌ Agence introuvable.');
        }

        $q = $db->prepare('SELECT COUNT(*) FROM interim_agences WHERE nom_agence = ? AND id != ?');
        $q->execute([$nom, $id]);
        if ((int) $q->fetchColumn() > 0) {
            agencesRetour('❌ Une autre agence porte déjà ce nom.');
        }

        try {
            $db->beginTransaction();
            $db->prepare('UPDATE interim_agences SET nom_agence = ?, nom_contact = ?, email_1 = ?, email_2 = ? WHERE id = ?')
               ->execute([$nom, $contact, $emails['email_1'], $emails['email_2'], $id]);

            // ⚠️ LE RENOMMAGE SUIT DANS LES FICHES. C'est ce que la page du
            // site ne fait pas : le nom change ici, les fiches gardent
            // l'ancien, et l'agence perd d'un coup la vue sur tous ses
            // intérimaires — sans message, sans erreur, juste un écran vide.
            $suivis = 0;
            if ($nom !== $ancienNom) {
                $maj = $db->prepare('UPDATE utilisateurs SET interim = ? WHERE interim = ?');
                $maj->execute([$nom, $ancienNom]);
                $suivis = $maj->rowCount();
            }
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            agencesRetour('❌ La modification a échoué : ' . e($e->getMessage()));
        }

        agencesRetour('✅ Agence modifiée.' . ($suivis > 0
            ? ' ' . $suivis . ' fiche(s) suivent le nouveau nom — sans ça, l\'agence aurait perdu sa vue.'
            : ''));
    }

    // ── SUPPRIMER UNE AGENCE ─────────────────────────────────────────────
    if ($action === 'supprimer_agence') {
        $id = (int) ($_POST['agence_id'] ?? 0);
        $q = $db->prepare('SELECT nom_agence FROM interim_agences WHERE id = ? LIMIT 1');
        $q->execute([$id]);
        $nom = (string) $q->fetchColumn();
        if ($nom === '') {
            agencesRetour('❌ Agence introuvable.');
        }
        if (famicardEstAgenceInterne($nom)) {
            agencesRetour("❌ « " . e($nom) . " » n'est pas une agence : c'est le suivi interne."
                        . ' Le supprimer couperait les horaires des collaborateurs recrutés en direct.');
        }

        // On refuse tant que quelqu'un y est rattaché — comptes d'accès ET
        // collaborateurs. Supprimer l'agence laisserait des fiches pointant
        // sur un nom qui n'existe plus, donc des gens que personne ne voit.
        $q = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE interim = ?");
        $q->execute([$nom]);
        $rattaches = (int) $q->fetchColumn();
        if ($rattaches > 0) {
            agencesRetour('❌ ' . $rattaches . ' compte(s) ou collaborateur(s) sont encore rattachés à « '
                        . e($nom) . ' ». Déplace-les d\'abord.');
        }

        $db->prepare('DELETE FROM interim_agence_users WHERE agence_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM interim_agences WHERE id = ?')->execute([$id]);
        agencesRetour('✅ Agence « ' . e($nom) . ' » supprimée.');
    }

    // ── CRÉER UN ACCÈS POUR UNE AGENCE ───────────────────────────────────
    if ($action === 'creer_acces') {
        $id = (int) ($_POST['agence_id'] ?? 0);
        $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $mdp = (string) ($_POST['mot_de_passe'] ?? '');

        $q = $db->prepare('SELECT nom_agence FROM interim_agences WHERE id = ? LIMIT 1');
        $q->execute([$id]);
        $nom = (string) $q->fetchColumn();

        if ($nom === '') {
            agencesRetour('❌ Agence introuvable.');
        }
        if ($identifiant === '' || $mdp === '') {
            agencesRetour('❌ Un identifiant et un mot de passe sont nécessaires pour ouvrir un accès.');
        }
        if (preg_match('/\s/u', $identifiant)) {
            agencesRetour("❌ L'identifiant ne peut pas contenir d'espace : il se tape à la connexion.");
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            agencesRetour("❌ L'adresse email n'est pas valide.");
        }
        $q = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE identifiant = ?');
        $q->execute([$identifiant]);
        if ((int) $q->fetchColumn() > 0) {
            agencesRetour('❌ Cet identifiant est déjà utilisé.');
        }

        try {
            $db->beginTransaction();
            // `interim` porte le nom de l'agence : c'est CE champ que FamiJob
            // compare pour décider ce que ce compte a le droit de voir.
            $db->prepare(
                "INSERT INTO utilisateurs (identifiant, email, interim, mot_de_passe, role)
                 VALUES (?, ?, ?, ?, 'agence_interim')"
            )->execute([
                $identifiant,
                $email !== '' ? $email : null,
                $nom,
                password_hash($mdp, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $db->lastInsertId();
            // La table de liaison sert à la page du site : on la tient à jour
            // pour que les deux écrans racontent la même chose.
            $db->prepare('INSERT INTO interim_agence_users (agence_id, user_id) VALUES (?, ?)')
               ->execute([$id, $userId]);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            agencesRetour("❌ L'accès n'a pas pu être créé : " . e($e->getMessage()));
        }

        agencesRetour('✅ Accès « ' . e($identifiant) . ' » ouvert pour ' . e($nom) . '.');
    }

    // ── CHANGER LE MOT DE PASSE D'UN ACCÈS ───────────────────────────────
    if ($action === 'mdp_acces') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $mdp = (string) ($_POST['mot_de_passe'] ?? '');
        if ($userId <= 0 || $mdp === '') {
            agencesRetour('❌ Aucun mot de passe saisi.');
        }
        // La condition sur le rôle est dans le SQL : sans elle, un identifiant
        // bricolé dans le formulaire changerait le mot de passe de n'importe qui.
        $st = $db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ? AND role = 'agence_interim'");
        $st->execute([password_hash($mdp, PASSWORD_DEFAULT), $userId]);
        agencesRetour($st->rowCount() > 0
            ? '✅ Mot de passe changé.'
            : "❌ Ce compte n'est pas un accès d'agence.");
    }

    // ── FERMER UN ACCÈS ──────────────────────────────────────────────────
    if ($action === 'supprimer_acces') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $st = $db->prepare("DELETE FROM utilisateurs WHERE id = ? AND role = 'agence_interim'");
        $st->execute([$userId]);
        if ($st->rowCount() > 0) {
            $db->prepare('DELETE FROM interim_agence_users WHERE user_id = ?')->execute([$userId]);
            // Les accès aux services de Famicard ne sont rattachés par aucune
            // clé étrangère : sans ce ménage, un futur compte réutilisant le
            // même identifiant hériterait des accès de celui-ci.
            if (function_exists('famicardOublieAcces')) {
                famicardOublieAcces($db, $userId);
            }
        }
        agencesRetour($st->rowCount() > 0 ? '✅ Accès fermé.' : "❌ Ce compte n'est pas un accès d'agence.");
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LECTURE
// ─────────────────────────────────────────────────────────────────────────────
$agences = [];
if ($tablesOk) {
    try {
        $agences = $db->query('SELECT * FROM interim_agences ORDER BY nom_agence ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $agences = [];
    }
}

// Les accès et les rattachements, en DEUX requêtes pour toute la page — pas
// deux par agence.
//
// ⚠️ On lit les accès par le NOM (`utilisateurs.interim`), pas par la table de
// liaison : c'est le nom qui donne réellement la vue dans FamiJob, et un compte
// créé ailleurs (creer.php, ou la page du site) peut très bien n'avoir aucune
// ligne de liaison. Afficher la liaison montrerait un écran faux.
$accesParAgence = [];
$comptesParAgence = [];
try {
    foreach ($db->query(
        "SELECT id, identifiant, email, interim FROM utilisateurs
          WHERE role = 'agence_interim' ORDER BY identifiant ASC"
    )->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $accesParAgence[trim((string) $u['interim'])][] = $u;
    }
    foreach ($db->query(
        "SELECT interim, COUNT(*) AS n FROM utilisateurs
          WHERE role <> 'agence_interim' AND interim IS NOT NULL AND TRIM(interim) <> ''
          GROUP BY interim"
    )->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $comptesParAgence[trim((string) $c['interim'])] = (int) $c['n'];
    }
} catch (Exception $e) {
    // Colonne absente : la page reste utilisable, sans les compteurs.
}

// Des accès qui pointent sur un nom d'agence inexistant : c'est exactement ce
// que produisait un renommage côté site. On les montre plutôt que de les
// laisser invisibles.
$nomsConnus = array_map(static function ($a) { return trim((string) $a['nom_agence']); }, $agences);
$orphelins = [];
foreach ($accesParAgence as $nom => $liste) {
    if (!in_array($nom, $nomsConnus, true)) {
        $orphelins[$nom] = $liste;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agences intérim - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 0 0 50px; color: #333; }
    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .85rem; }
    .wrap { max-width: 1000px; margin: 22px auto 0; padding: 0 16px; }

    .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; font-weight: 600; line-height: 1.55; background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; }
    .note { background: #fff8e1; border: 1px solid #ffe082; color: #6a5400; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; line-height: 1.6; font-size: .92rem; }
    .boite { background: #fff; border-radius: 16px; box-shadow: 0 6px 18px rgba(0,0,0,.07); margin-bottom: 16px; overflow: hidden; }
    .boite-tete { padding: 16px 20px; border-bottom: 1px solid #f0f4f1; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .boite-tete .nom { font-weight: 800; color: #2d5a37; font-size: 1.05rem; }
    .etiquette { border-radius: 999px; padding: 3px 11px; font-size: .74rem; font-weight: 800; background: #eef4ef; color: #3d6b48; }
    .etiquette.interne { background: #e7f6ea; color: #1E7A46; }
    .etiquette.vide { background: #f3f0ea; color: #8a7f68; }
    .corps { padding: 16px 20px; }
    .corps h3 { margin: 0 0 10px; font-size: .78rem; text-transform: uppercase; letter-spacing: .07em; color: #2d5a37; }

    .grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
    label { display: block; font-size: .78rem; font-weight: 700; color: #5a6b60; margin-bottom: 5px; }
    input { width: 100%; padding: 9px 11px; border: 1px solid #cfd8d2; border-radius: 10px; font-family: inherit; font-size: .92rem; }
    .bouton { border: 0; border-radius: 30px; padding: 9px 20px; font-family: inherit; font-weight: 700; font-size: .86rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-vide { background: #eef3ef; color: #2d5a37; }
    .bouton-rouge { background: #fdeaea; color: #a3271c; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 12px; }

    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #f0f4f1; }
    th { color: #6a7d72; font-size: .74rem; text-transform: uppercase; letter-spacing: .06em; }
    .rien { color: #8a968f; font-size: .9rem; font-style: italic; }
    details > summary { cursor: pointer; color: #2d5a37; font-weight: 700; font-size: .86rem; padding: 6px 0; }
</style>
</head>
<body>

<div class="bandeau">
    <h1>🏢 Agences intérim</h1>
    <div>
        <a class="pill" href="admin.php">📇 Mes collaborateurs</a>
        <a class="pill" href="index.php">&larr; Accueil</a>
    </div>
</div>

<div class="wrap">

    <?php if ($flash !== ''): ?><div class="flash"><?= $flash ?></div><?php endif; ?>

    <?php if (!$tablesOk): ?>
        <div class="note">❌ Les tables des agences n'ont pas pu être créées. La page est en lecture seule.</div>
    <?php endif; ?>

    <div class="note">
        Une agence n'est <b>pas un collaborateur</b> : c'est une société extérieure à qui l'on ouvre une porte
        pour qu'elle voie <b>ses</b> intérimaires. Elle n'a ni fiche, ni photo, ni contrat.<br>
        ⚠️ <b>C'est le NOM qui relie tout.</b> FamiJob compare le nom de l'agence à celui inscrit sur chaque fiche.
        Renommer une agence ici met donc aussi à jour les fiches concernées — sans quoi l'agence perdrait
        d'un coup la vue sur tous ses intérimaires.
    </div>

    <?php if ($orphelins): ?>
        <div class="note" style="background:#fdeaea;border-color:#f3c2c2;color:#a3271c;">
            ⚠️ <b>Des accès pointent sur une agence qui n'existe pas.</b>
            Ils ne voient rien, et personne ne s'en aperçoit tant qu'on ne regarde pas ici.
            <?php foreach ($orphelins as $nom => $liste): ?>
                <div style="margin-top:6px;">
                    « <b><?= e($nom !== '' ? $nom : '(vide)') ?></b> » :
                    <?= e(implode(', ', array_map(static function ($u) { return (string) $u['identifiant']; }, $liste))) ?>
                </div>
            <?php endforeach; ?>
            <div style="margin-top:6px;">Recrée une agence de ce nom, ou modifie ces comptes.</div>
        </div>
    <?php endif; ?>

    <?php // ── AJOUTER UNE AGENCE ────────────────────────────────────────── ?>
    <div class="boite">
        <div class="boite-tete"><span class="nom">➕ Ajouter une agence</span></div>
        <div class="corps">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="creer_agence">
                <div class="grille">
                    <div><label for="n_nom">Nom de l'agence *</label>
                        <input type="text" id="n_nom" name="nom_agence" required></div>
                    <div><label for="n_contact">Personne de contact *</label>
                        <input type="text" id="n_contact" name="nom_contact" required></div>
                    <div><label for="n_m1">Email principal</label>
                        <input type="email" id="n_m1" name="email_1"></div>
                    <div><label for="n_m2">Second email</label>
                        <input type="email" id="n_m2" name="email_2"></div>
                </div>
                <div class="actions"><button type="submit" class="bouton bouton-plein">Ajouter</button></div>
            </form>
        </div>
    </div>

    <?php // ── LA LISTE ──────────────────────────────────────────────────── ?>
    <?php if (!$agences): ?>
        <div class="boite"><div class="corps rien">Aucune agence enregistrée.</div></div>
    <?php endif; ?>

    <?php foreach ($agences as $ag): ?>
        <?php
            $nom = trim((string) $ag['nom_agence']);
            $interne = famicardEstAgenceInterne($nom);
            $acces = $accesParAgence[$nom] ?? [];
            $combien = $comptesParAgence[$nom] ?? 0;
        ?>
        <div class="boite">
            <div class="boite-tete">
                <span class="nom"><?= e($nom) ?></span>
                <?php if ($interne): ?>
                    <span class="etiquette interne">Suivi interne — pas une agence</span>
                <?php endif; ?>
                <span class="etiquette<?= $combien === 0 ? ' vide' : '' ?>">
                    <?= (int) $combien ?> collaborateur<?= $combien > 1 ? 's' : '' ?>
                </span>
                <span class="etiquette<?= !$acces ? ' vide' : '' ?>">
                    <?= count($acces) ?> accès
                </span>
                <?php if ($combien > 0): ?>
                    <a class="bouton bouton-vide" href="admin.php?agence=<?= urlencode($nom) ?>">Voir ses collaborateurs</a>
                <?php endif; ?>
            </div>

            <div class="corps">
                <details>
                    <summary>✏️ Modifier ses informations</summary>
                    <form method="POST" style="margin-top:10px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="modifier_agence">
                        <input type="hidden" name="agence_id" value="<?= (int) $ag['id'] ?>">
                        <div class="grille">
                            <div><label>Nom de l'agence *</label>
                                <input type="text" name="nom_agence" value="<?= e($nom) ?>" required></div>
                            <div><label>Personne de contact *</label>
                                <input type="text" name="nom_contact" value="<?= e((string) $ag['nom_contact']) ?>" required></div>
                            <div><label>Email principal</label>
                                <input type="email" name="email_1" value="<?= e((string) ($ag['email_1'] ?? '')) ?>"></div>
                            <div><label>Second email</label>
                                <input type="email" name="email_2" value="<?= e((string) ($ag['email_2'] ?? '')) ?>"></div>
                        </div>
                        <div class="actions">
                            <button type="submit" class="bouton bouton-plein"
                                    onclick="return confirm('Enregistrer ?\n\nSi tu changes le nom, les fiches des collaborateurs rattachés suivront automatiquement.');">
                                Enregistrer
                            </button>
                        </div>
                    </form>

                    <?php // La suppression a SON formulaire, et ce n'est pas un détail :
                          // un bouton « action » posé dans le formulaire de modification
                          // ne l'emporterait sur le champ caché que parce qu'il vient
                          // après lui dans la page. Une réorganisation du HTML, et l'on
                          // supprimerait en croyant enregistrer. ?>
                    <?php if (!$interne): ?>
                        <form method="POST" style="margin-top:8px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="supprimer_agence">
                            <input type="hidden" name="agence_id" value="<?= (int) $ag['id'] ?>">
                            <button type="submit" class="bouton bouton-rouge"
                                    onclick="return confirm('Supprimer l\'agence « <?= e($nom) ?> » ?\n\nRefusé si des collaborateurs ou des accès y sont encore rattachés.');">
                                Supprimer l'agence
                            </button>
                        </form>
                    <?php endif; ?>
                </details>

                <h3 style="margin-top:16px;">Accès à FamiJob</h3>
                <?php if ($acces): ?>
                    <table>
                        <tr><th>Identifiant</th><th>Email</th><th>Mot de passe</th><th></th></tr>
                        <?php foreach ($acces as $u): ?>
                            <tr>
                                <td><b><?= e((string) $u['identifiant']) ?></b></td>
                                <td><?= e((string) ($u['email'] ?? '')) ?: '—' ?></td>
                                <td>
                                    <form method="POST" style="display:flex;gap:6px;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="mdp_acces">
                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <input type="password" name="mot_de_passe" placeholder="nouveau" autocomplete="new-password" required>
                                        <button type="submit" class="bouton bouton-vide">Changer</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="supprimer_acces">
                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="bouton bouton-rouge"
                                                onclick="return confirm('Fermer cet accès ? L\'agence ne pourra plus se connecter.');">Fermer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p class="rien">Aucun accès : cette agence ne peut pas se connecter.</p>
                <?php endif; ?>

                <details style="margin-top:10px;">
                    <summary>🔑 Ouvrir un accès</summary>
                    <form method="POST" style="margin-top:10px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="creer_acces">
                        <input type="hidden" name="agence_id" value="<?= (int) $ag['id'] ?>">
                        <div class="grille">
                            <div><label>Identifiant *</label>
                                <input type="text" name="identifiant" autocomplete="off" required></div>
                            <div><label>Mot de passe *</label>
                                <input type="password" name="mot_de_passe" autocomplete="new-password" required></div>
                            <div><label>Email</label>
                                <input type="email" name="email" autocomplete="off"></div>
                        </div>
                        <div class="actions"><button type="submit" class="bouton bouton-plein">Ouvrir l'accès</button></div>
                    </form>
                </details>
            </div>
        </div>
    <?php endforeach; ?>

</div>
</body>
</html>
