<?php
// ============================================================
// relance_mdp.php — RENVOYER LE LIEN DE CRÉATION DE MOT DE PASSE.
//
// À QUOI ÇA SERT
// Un mail d'activation qui ne s'affiche pas, une adresse changée, un message
// perdu dans les indésirables : la personne ne voit jamais son lien et reste
// bloquée à la porte. Cette page permet de lui en renvoyer un, neuf.
//
// POURQUOI UN PAR UN
// Le lien contient un jeton UNIQUE par personne : il est impossible d'envoyer
// un mail groupé. Chaque destinataire reçoit donc son propre message.
//
// ⚠️ Émettre un nouveau lien INVALIDE l'ancien (une seule colonne de jeton par
// utilisateur). Quelqu'un qui aurait retrouvé le premier mail entre-temps doit
// utiliser le nouveau lien — le mail le précise.
//
// QUI EST CONCERNÉ
// La colonne `account_activation_pending` vaut 1 tant que la personne n'a pas
// défini son mot de passe (remise à 0 par set_password.php). On voit donc d'un
// coup d'œil qui est réellement bloqué, sans déranger ceux qui ont réussi.
//
// PÉRIMÈTRE
// Tous les profils sont sélectionnables (beta, étudiants, magasin, mentors…),
// avec un filtre facultatif sur le domaine de l'adresse.
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/csrf.php';
require_once 'includes/mail_html.php';

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

ensureUserAccountAccessColumns($db);

$JOURS_DEFAUT = 14;
// Aucun profil coché d'avance : l'outil ne sert plus qu'à la bêta, et un profil
// pré-sélectionné se fait envoyer sans qu'on l'ait choisi.
$ROLES_DEFAUT = [];

// Libellés lisibles des profils. La liste réellement proposée vient de la BASE
// (voir plus bas) : si un profil apparaît un jour sans passer par ici, il reste
// sélectionnable, simplement affiché sous son nom technique. Rien ne peut donc
// être oublié en silence.
$LIBELLES_ROLES = [
    'beta'               => 'Beta 🧪',
    'etudiant'           => 'Étudiant',
    'employe_magasin'    => 'Magasin',
    'employe_logistique' => 'Logistique',
    'teamcoach'          => 'Teamcoach',
    'mentor'             => 'Mentor',
    'evaluateur'         => 'Évaluateur',
    'agence_interim'     => 'Agence intérim',
    'admin'              => 'Admin',
];

/** Libellé lisible d'un profil (nom technique s'il est inconnu). */
function libelleRole($r)
{
    global $LIBELLES_ROLES;
    return $LIBELLES_ROLES[$r] ?? $r;
}

// ─────────────────────────────────────────────────────────────────────────────
// PROFILS DISPONIBLES, avec le nombre de comptes bloqués — c'est ce chiffre-là
// qui compte : il dit tout de suite où il y a du travail.
// ─────────────────────────────────────────────────────────────────────────────
$dispo = $db->query(
    "SELECT role,
            COUNT(*) AS total,
            SUM(CASE WHEN account_activation_pending = 1 THEN 1 ELSE 0 END) AS bloques
     FROM utilisateurs
     WHERE email IS NOT NULL AND email <> ''
     GROUP BY role
     ORDER BY role ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$rolesConnus = array_column($dispo, 'role');

// Profils cochés. Au premier affichage : beta (le besoin d'origine).
$rolesChoisis = (array) ($_REQUEST['roles'] ?? $ROLES_DEFAUT);
$rolesChoisis = array_values(array_intersect(array_map('strval', $rolesChoisis), $rolesConnus));
if (empty($rolesChoisis)) {
    $rolesChoisis = array_values(array_intersect($ROLES_DEFAUT, $rolesConnus));
}

// Domaine : FACULTATIF (vide = toutes les adresses). On n'accepte qu'un domaine
// plausible, jamais un motif SQL bricolé depuis l'URL.
// Pas de domaine par défaut : pré-remplir @famiflora.be masquait silencieusement
// tous les comptes en adresse personnelle. Vide = toutes les adresses.
$domaine = trim((string) ($_REQUEST['domaine'] ?? ''));
if ($domaine !== '') {
    if (!preg_match('/^@?[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $domaine)) {
        $domaine = '';
    } elseif ($domaine[0] !== '@') {
        $domaine = '@' . $domaine;
    }
}

$jours = (int) ($_REQUEST['jours'] ?? $JOURS_DEFAUT);
if (!in_array($jours, [7, 14, 21], true)) {
    $jours = $JOURS_DEFAUT;
}

// ─────────────────────────────────────────────────────────────────────────────
// APERÇU DU MAIL — aucun envoi, aucun jeton consommé : le lien est factice.
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['apercu'])) {
    $corps = famiPasswordReminderBody(
        famiBuildAppUrl('set_password.php', ['token' => 'APERCU-LIEN-DE-DEMONSTRATION']),
        'Prénom',
        'identifiant',
        $jours,
        '',
        in_array('beta', $rolesChoisis, true)
    );
    header('Content-Type: text/html; charset=UTF-8');
    echo famiMailOutlookSafe($corps, 'Ton mot de passe FamiFormation / Jouw wachtwoord FamiFormation');
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// LISTE DES DESTINATAIRES POSSIBLES
// ─────────────────────────────────────────────────────────────────────────────
$utilisateurs = [];
$stmt = null;
$params = [];
if (!empty($rolesChoisis)) {
    $trous = implode(', ', array_fill(0, count($rolesChoisis), '?'));
    $sql = "SELECT id, identifiant, prenom, nom, email, role, account_activation_pending
            FROM utilisateurs
            WHERE role IN ($trous)
              AND email IS NOT NULL
              AND email <> ''";
    $params = $rolesChoisis;
    if ($domaine !== '') {
        $sql .= ' AND email LIKE ?';
        $params[] = '%' . $domaine;
    }
    $sql .= ' ORDER BY account_activation_pending DESC, nom ASC, prenom ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$nbTotal   = count($utilisateurs);
$nbBloques = 0;
foreach ($utilisateurs as $u) {
    if (!empty($u['account_activation_pending'])) {
        $nbBloques++;
    }
}

$flash = '';
$resultats = null;

// ─────────────────────────────────────────────────────────────────────────────
// ENVOI DE TEST.
//
// Si l'adresse correspond à un compte existant, on émet un VRAI jeton pour ce
// compte : le lien est alors réellement cliquable et le test valide toute la
// chaîne (envoi -> affichage -> clic -> page de mot de passe). C'est le seul
// test qui prouve vraiment que ça marche.
//
// Si l'adresse ne correspond à aucun compte, aucun jeton ne peut exister : le
// lien est forcément mort. Le mail le dit alors NOIR SUR BLANC, en haut, pour
// qu'un « lien expiré » ne soit pas pris pour une panne.
// ─────────────────────────────────────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'test') {
    requireValidCSRF();

    $moi = $db->prepare('SELECT email, prenom, identifiant FROM utilisateurs WHERE id = ? LIMIT 1');
    $moi->execute([(int) ($_SESSION['user_id'] ?? 0)]);
    $admin = $moi->fetch(PDO::FETCH_ASSOC);
    $adresseTest = trim((string) ($_POST['email_test'] ?? ($admin['email'] ?? '')));

    if ($adresseTest === '' || !filter_var($adresseTest, FILTER_VALIDATE_EMAIL)) {
        $flash = "<div class='flash err'>❌ Adresse de test invalide.</div>";
    } else {
        $rech = $db->prepare('SELECT id FROM utilisateurs WHERE email = ? LIMIT 1');
        $rech->execute([$adresseTest]);
        $compte = $rech->fetch(PDO::FETCH_ASSOC);

        if ($compte) {
            // Vrai jeton (type « reset » : ne bloque aucune connexion existante).
            $ok = sendPasswordReminderEmail($db, (int) $compte['id'], $jours * 24, true);
            $flash = $ok
                ? "<div class='flash ok'>✅ Mail de test envoyé à <b>" . e($adresseTest) . "</b>."
                    . " Le lien est <b>réellement fonctionnel</b> : clique-le pour vérifier toute la chaîne."
                    . " La page de création de mot de passe doit s'ouvrir — ne valide le formulaire que si tu veux vraiment changer ce mot de passe.</div>"
                : "<div class='flash err'>❌ Le test n'est pas parti. Détail : " . e(getLastMailError()) . "</div>";
        } else {
            // Adresse hors base : impossible de fabriquer un lien valide.
            $corps = famiPasswordReminderBody(
                famiBuildAppUrl('set_password.php', ['token' => 'LIEN-DE-DEMONSTRATION-SANS-COMPTE']),
                trim((string) ($admin['prenom'] ?? '')) ?: 'Prénom',
                (string) ($admin['identifiant'] ?? 'identifiant'),
                $jours,
                'demo',
                in_array('beta', $rolesChoisis, true)
            );
            $ok = sendMail(
                $adresseTest,
                '[TEST] Ton mot de passe FamiFormation / Jouw wachtwoord FamiFormation',
                $corps,
                true
            );
            $flash = $ok
                ? "<div class='flash ok'>✅ Mail de test envoyé à <b>" . e($adresseTest) . "</b>."
                    . " ⚠️ Cette adresse ne correspond à <b>aucun compte</b> : le lien du mail ne peut pas fonctionner"
                    . " (il affichera « lien expiré », c'est normal et le mail le précise). Pour tester un vrai lien,"
                    . " utilise l'adresse d'un compte existant.</div>"
                : "<div class='flash err'>❌ Le test n'est pas parti. Détail : " . e(getLastMailError()) . "</div>";
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ENVOI RÉEL — un mail par personne cochée, chacun avec son propre lien.
// ─────────────────────────────────────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'envoyer') {
    requireValidCSRF();

    $choisis = array_map('intval', (array) ($_POST['user_ids'] ?? []));
    $choisis = array_values(array_unique(array_filter($choisis)));

    // On ne fait confiance qu'à la liste calculée côté serveur : un identifiant
    // bricolé dans le formulaire ne peut pas viser quelqu'un hors périmètre.
    $autorises = [];
    foreach ($utilisateurs as $u) {
        $autorises[(int) $u['id']] = $u;
    }
    $cibles = [];
    foreach ($choisis as $id) {
        if (isset($autorises[$id])) {
            $cibles[] = $autorises[$id];
        }
    }

    if (empty($cibles)) {
        $flash = "<div class='flash err'>❌ Aucun destinataire sélectionné.</div>";
    } else {
        // Un envoi SMTP prend ~1 s : sans ça, une liste un peu longue tombe sur
        // le temps d'exécution maximal et s'arrête au milieu.
        @set_time_limit(0);

        $resultats = ['ok' => [], 'ko' => []];
        foreach ($cibles as $u) {
            $envoye = sendPasswordReminderEmail($db, (int) $u['id'], $jours * 24);
            if ($envoye) {
                $resultats['ok'][] = $u;
            } else {
                $u['erreur'] = getLastMailError();
                $resultats['ko'][] = $u;
            }
            // Petite pause : les serveurs SMTP limitent souvent la cadence, et un
            // envoi refusé pour « trop rapide » serait un mail perdu.
            usleep(400000);
        }

        // La liste affichée doit refléter les nouveaux statuts.
        if ($stmt !== null) {
            $stmt->execute($params);
            $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relancer la création de mot de passe - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Open Sans', sans-serif; background: #eef4ef; margin: 0; padding: 24px 16px 60px; color: #244230; }
        .wrap { max-width: 960px; margin: 0 auto; }
        h1 { color: #2d5a37; font-size: 1.6rem; margin: 0 0 4px; }
        .sub { color: #5a6b60; margin: 0 0 18px; line-height: 1.55; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; margin-bottom: 16px; box-shadow: 0 6px 20px rgba(14,59,36,.08); border: 1px solid #e6efe8; }
        .card h2 { margin: 0 0 14px; font-size: 1.15rem; color: #2d5a37; }
        .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 600; line-height: 1.5; }
        .flash.ok { background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; }
        .flash.err { background: #fdeaea; border: 1px solid #f3c2c2; color: #a12; }
        .note { background: #fff8e1; border: 1px solid #ffe082; color: #6a5400; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; line-height: 1.55; font-size: .92rem; }
        .btn { display: inline-block; border: none; cursor: pointer; background: #2d5a37; color: #fff; font-weight: 700; padding: 11px 22px; border-radius: 999px; text-decoration: none; font-size: .95rem; }
        .btn.ghost { background: #fff; color: #2d5a37; border: 2px solid #2d5a37; }
        .btn.gold { background: #d6a21a; }
        .top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid #eef2ef; }
        th { color: #6a7d72; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
        tr.faite td { color: #8a968f; }
        .tag { border-radius: 999px; padding: 3px 11px; font-size: .76rem; font-weight: 800; white-space: nowrap; }
        .tag.bloque { background: #fdeaea; color: #a12; }
        .tag.ok { background: #e7f6ea; color: #1E7A46; }
        .tag.role { background: #eef4ef; color: #3d6b48; font-weight: 700; }
        .reglages { display: flex; gap: 18px; flex-wrap: wrap; align-items: flex-end; }
        .reglages label { display: block; font-weight: 700; font-size: .86rem; margin-bottom: 5px; }
        .reglages input, .reglages select { padding: 9px 12px; border: 1px solid #cfe0d4; border-radius: 10px; font-family: inherit; font-size: .92rem; }
        .profils { display: flex; flex-wrap: wrap; gap: 9px; margin-bottom: 18px; }
        .profil { display: flex; align-items: center; gap: 8px; border: 1px solid #cfe0d4; border-radius: 12px; padding: 9px 13px; cursor: pointer; background: #f8fbf9; font-size: .9rem; }
        .profil:hover { border-color: #2d5a37; }
        .profil input { margin: 0; }
        .profil .nb { color: #8a968f; font-size: .82rem; }
        .profil .nb b { color: #a12; }
        .compte { font-size: .9rem; color: #5a6b60; margin: 14px 0 0; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 16px; }
        .lien-mini { font-size: .86rem; color: #2d5a37; font-weight: 700; cursor: pointer; text-decoration: underline; background: none; border: none; padding: 0; font-family: inherit; }
        .scroll { overflow-x: auto; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🔑 Relancer la création de mot de passe</h1>
    <p class="sub">Renvoie son lien personnel à toute personne qui n'a pas pu créer son mot de passe, quel que soit son profil.</p>

    <div class="top">
        <a href="index.php" class="btn ghost">← Retour</a>
        <a href="relance_mdp.php?apercu=1&amp;jours=<?= (int) $jours ?><?php foreach ($rolesChoisis as $r) { echo '&amp;roles%5B%5D=' . urlencode($r); } ?>"
           target="_blank" class="btn ghost">👁 Voir l'aperçu du mail</a>
    </div>

    <?= $flash ?>

    <div class="note">
        ℹ️ Le lien est <b>unique par personne</b> : impossible d'envoyer un mail groupé, chacun reçoit le sien.<br>
        ⚠️ Envoyer un nouveau lien <b>annule le précédent</b>. Si quelqu'un avait retrouvé l'ancien mail, il devra utiliser le nouveau.<br>
        ✅ Le mail dit clairement, en <b>FR et en NL</b>, que les personnes ayant déjà créé leur mot de passe ne sont pas concernées.
    </div>

    <?php if ($resultats !== null): ?>
        <div class="card">
            <h2>📬 Résultat de l'envoi</h2>
            <?php if (!empty($resultats['ok'])): ?>
                <div class="flash ok">
                    <?= count($resultats['ok']) ?> mail(s) envoyé(s) avec succès.
                    Les liens restent valables jusqu'au
                    <b><?= date('d/m/Y à H\hi', strtotime('+' . ($jours * 24) . ' hours')) ?></b>.
                </div>
                <div class="scroll">
                    <table>
                        <tr><th>Nom</th><th>Profil</th><th>Adresse</th></tr>
                        <?php foreach ($resultats['ok'] as $u): ?>
                            <tr>
                                <td><?= e(trim($u['prenom'] . ' ' . $u['nom'])) ?></td>
                                <td><span class="tag role"><?= e(libelleRole($u['role'])) ?></span></td>
                                <td><?= e($u['email']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($resultats['ko'])): ?>
                <div class="flash err" style="margin-top:16px;"><?= count($resultats['ko']) ?> échec(s) — ces personnes n'ont rien reçu, tu peux réessayer.</div>
                <div class="scroll">
                    <table>
                        <tr><th>Nom</th><th>Adresse</th><th>Motif</th></tr>
                        <?php foreach ($resultats['ko'] as $u): ?>
                            <tr>
                                <td><?= e(trim($u['prenom'] . ' ' . $u['nom'])) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= e($u['erreur'] ?: 'Erreur inconnue') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>⚙️ Périmètre</h2>
        <form method="GET" action="relance_mdp.php">
            <p class="sub" style="margin-bottom:12px;">
                Coche les profils à examiner. Le chiffre en <b style="color:#a12;">rouge</b> est le nombre de comptes
                <b>sans mot de passe défini</b> : c'est là qu'il y a du travail.
            </p>
            <div class="profils">
                <?php foreach ($dispo as $d): $coche = in_array($d['role'], $rolesChoisis, true); ?>
                    <label class="profil">
                        <input type="checkbox" name="roles[]" value="<?= e($d['role']) ?>" <?= $coche ? 'checked' : '' ?>>
                        <span><?= e(libelleRole($d['role'])) ?></span>
                        <span class="nb">
                            <?php if ((int) $d['bloques'] > 0): ?>
                                <b><?= (int) $d['bloques'] ?> bloqué(s)</b> / <?= (int) $d['total'] ?>
                            <?php else: ?>
                                <?= (int) $d['total'] ?> compte(s)
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="reglages">
                <div>
                    <label for="domaine">Domaine des adresses <span style="font-weight:400;color:#8a968f;">(vide = toutes)</span></label>
                    <input type="text" id="domaine" name="domaine" value="<?= e($domaine) ?>" size="22" placeholder="toutes les adresses">
                </div>
                <div>
                    <label for="jours">Validité du lien</label>
                    <select id="jours" name="jours">
                        <?php foreach ([7, 14, 21] as $j): ?>
                            <option value="<?= $j ?>" <?= $j === $jours ? 'selected' : '' ?>><?= $j ?> jours</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><button type="submit" class="btn ghost">Actualiser la liste</button></div>
            </div>

            <p class="compte">
                Sélection actuelle : <b><?= $nbTotal ?></b> compte(s)<?= $domaine !== '' ? ' en <b>' . e($domaine) . '</b>' : ' (toutes adresses)' ?>,
                dont <b><?= $nbBloques ?></b> sans mot de passe défini.
            </p>
        </form>
    </div>

    <div class="card">
        <h2>✉️ Vérifier avant d'envoyer</h2>
        <p class="sub" style="margin-bottom:14px;">
            Si l'adresse correspond à un <b>compte existant</b>, le lien reçu est un <b>vrai lien qui fonctionne</b> :
            tu peux le cliquer pour valider toute la chaîne. La page de création de mot de passe doit s'ouvrir
            (ne valide le formulaire que si tu veux vraiment changer ce mot de passe).<br>
            Si l'adresse ne correspond à aucun compte, aucun lien valide ne peut exister : le mail est alors
            marqué comme non fonctionnel, et afficher « lien expiré » est normal.
        </p>
        <form method="POST" action="relance_mdp.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="test">
            <input type="hidden" name="domaine" value="<?= e($domaine) ?>">
            <input type="hidden" name="jours" value="<?= (int) $jours ?>">
            <?php foreach ($rolesChoisis as $r): ?>
                <input type="hidden" name="roles[]" value="<?= e($r) ?>">
            <?php endforeach; ?>
            <div class="reglages">
                <div>
                    <label for="email_test">Adresse de test</label>
                    <input type="email" id="email_test" name="email_test" size="34"
                           value="<?= e(famiGetEnv('MAIL_ADMIN', 'jimmy.hendrickx@famiflora.be')) ?>">
                </div>
                <div><button type="submit" class="btn gold">Envoyer un test</button></div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>👥 Destinataires</h2>
        <?php if (empty($rolesChoisis)): ?>
            <p class="sub">Coche au moins un profil ci-dessus.</p>
        <?php elseif ($nbTotal === 0): ?>
            <p class="sub">Aucun compte ne correspond à cette sélection.</p>
        <?php else: ?>
            <form method="POST" action="relance_mdp.php" id="formEnvoi">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="envoyer">
                <input type="hidden" name="domaine" value="<?= e($domaine) ?>">
                <input type="hidden" name="jours" value="<?= (int) $jours ?>">
                <?php foreach ($rolesChoisis as $r): ?>
                    <input type="hidden" name="roles[]" value="<?= e($r) ?>">
                <?php endforeach; ?>

                <p style="margin:0 0 12px;">
                    <button type="button" class="lien-mini" onclick="cocher('bloques')">Cocher uniquement ceux qui sont bloqués</button> ·
                    <button type="button" class="lien-mini" onclick="cocher('tous')">Tout cocher</button> ·
                    <button type="button" class="lien-mini" onclick="cocher('rien')">Tout décocher</button>
                </p>

                <div class="scroll">
                    <table>
                        <tr>
                            <th style="width:34px;"></th>
                            <th>Nom</th>
                            <th>Profil</th>
                            <th>Identifiant</th>
                            <th>Adresse</th>
                            <th>Mot de passe</th>
                        </tr>
                        <?php foreach ($utilisateurs as $u): $bloque = !empty($u['account_activation_pending']); ?>
                            <tr class="<?= $bloque ? '' : 'faite' ?>">
                                <td>
                                    <input type="checkbox" name="user_ids[]" value="<?= (int) $u['id'] ?>"
                                           class="case" data-bloque="<?= $bloque ? '1' : '0' ?>" <?= $bloque ? 'checked' : '' ?>>
                                </td>
                                <td><?= e(trim($u['prenom'] . ' ' . $u['nom'])) ?></td>
                                <td><span class="tag role"><?= e(libelleRole($u['role'])) ?></span></td>
                                <td><?= e($u['identifiant']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td>
                                    <?php if ($bloque): ?>
                                        <span class="tag bloque">Pas encore créé</span>
                                    <?php else: ?>
                                        <span class="tag ok">Déjà créé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="actions">
                    <button type="submit" class="btn"
                            onclick="return confirm('Envoyer le lien de création de mot de passe aux personnes cochées ?\n\nChaque personne reçoit son propre lien, et son lien précédent cesse de fonctionner.');">
                        📨 Envoyer aux personnes cochées
                    </button>
                    <span class="compte" id="compteur"></span>
                </div>
            </form>

            <script>
                function cocher(quoi) {
                    document.querySelectorAll('.case').forEach(function (c) {
                        if (quoi === 'tous')        { c.checked = true; }
                        else if (quoi === 'rien')   { c.checked = false; }
                        else                        { c.checked = c.dataset.bloque === '1'; }
                    });
                    majCompteur();
                }
                function majCompteur() {
                    var n = document.querySelectorAll('.case:checked').length;
                    document.getElementById('compteur').textContent =
                        n === 0 ? 'Personne de sélectionné.' : n + ' personne(s) sélectionnée(s).';
                }
                document.querySelectorAll('.case').forEach(function (c) {
                    c.addEventListener('change', majCompteur);
                });
                majCompteur();
            </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
