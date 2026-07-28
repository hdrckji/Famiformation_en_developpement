<?php
// ============================================================
// relance_mdp_beta.php — RENVOYER LE LIEN DE CRÉATION DE MOT DE PASSE.
//
// À QUOI ÇA SERT
// Le mail d'activation d'origine ne s'affichait pas correctement chez tous les
// destinataires : une partie des utilisateurs beta n'a donc jamais vu son lien
// et n'a pas pu créer son mot de passe. Cette page permet de leur renvoyer, à
// chacun, un lien neuf.
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
// défini son mot de passe (remise à 0 par set_password.php). On peut donc viser
// exactement ceux qui sont bloqués, sans déranger ceux qui ont réussi.
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

$DOMAINE_DEFAUT = '@famiflora.be';
$ROLE_CIBLE     = 'beta';
$JOURS_DEFAUT   = 14;

// Domaine de filtrage, modifiable depuis le formulaire. On n'accepte que des
// caractères plausibles pour un domaine mail (pas de joker maison, pas de %).
$domaine = trim((string) ($_REQUEST['domaine'] ?? $DOMAINE_DEFAUT));
if ($domaine === '' || !preg_match('/^@?[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $domaine)) {
    $domaine = $DOMAINE_DEFAUT;
}
if ($domaine[0] !== '@') {
    $domaine = '@' . $domaine;
}

$jours = (int) ($_REQUEST['jours'] ?? $JOURS_DEFAUT);
if (!in_array($jours, [7, 14, 21], true)) {
    $jours = $JOURS_DEFAUT;
}

// ─────────────────────────────────────────────────────────────────────────────
// APERÇU DU MAIL — aucun envoi, aucun jeton consommé : le lien est factice.
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['apercu'])) {
    $corps = famiBetaPasswordReminderBody(
        famiBuildAppUrl('set_password.php', ['token' => 'APERCU-LIEN-DE-DEMONSTRATION']),
        'Prénom',
        'identifiant',
        $jours
    );
    header('Content-Type: text/html; charset=UTF-8');
    echo famiMailOutlookSafe($corps, 'Ton mot de passe FamiFormation / Jouw wachtwoord FamiFormation');
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// LISTE DES DESTINATAIRES POSSIBLES
// ─────────────────────────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT id, identifiant, prenom, nom, email, account_activation_pending
     FROM utilisateurs
     WHERE role = ?
       AND email IS NOT NULL
       AND email <> ''
       AND email LIKE ?
     ORDER BY account_activation_pending DESC, nom ASC, prenom ASC"
);
$stmt->execute([$ROLE_CIBLE, '%' . $domaine]);
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
// ENVOI DE TEST — vers l'adresse de l'admin connecté, avec un lien factice :
// on vérifie l'affichage et la bonne réception SANS toucher au compte de
// personne ni griller de jeton.
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
        $corps = famiBetaPasswordReminderBody(
            famiBuildAppUrl('set_password.php', ['token' => 'TEST-LIEN-DE-DEMONSTRATION']),
            trim((string) ($admin['prenom'] ?? '')) ?: 'Prénom',
            (string) ($admin['identifiant'] ?? 'identifiant'),
            $jours
        );
        $ok = sendMail(
            $adresseTest,
            '[TEST] Ton mot de passe FamiFormation / Jouw wachtwoord FamiFormation',
            $corps,
            true
        );
        $flash = $ok
            ? "<div class='flash ok'>✅ Mail de test envoyé à <b>" . e($adresseTest) . "</b>. Le lien qu'il contient est factice : c'est normal.</div>"
            : "<div class='flash err'>❌ Le test n'est pas parti. Détail : " . e(getLastMailError()) . "</div>";
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
            $envoye = sendBetaPasswordReminderEmail($db, (int) $u['id'], $jours * 24);
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
        $stmt->execute([$ROLE_CIBLE, '%' . $domaine]);
        $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        body { font-family: 'Open Sans', sans-serif; background: #eef4ef; margin: 0; padding: 24px 16px 60px; color: #244230; }
        .wrap { max-width: 900px; margin: 0 auto; }
        h1 { color: #2d5a37; font-size: 1.6rem; margin: 0 0 4px; }
        .sub { color: #5a6b60; margin: 0 0 18px; line-height: 1.5; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; margin-bottom: 16px; box-shadow: 0 6px 20px rgba(14,59,36,.08); border: 1px solid #e6efe8; }
        .card h2 { margin: 0 0 14px; font-size: 1.15rem; color: #2d5a37; }
        .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 600; line-height: 1.5; }
        .flash.ok { background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; }
        .flash.err { background: #fdeaea; border: 1px solid #f3c2c2; color: #a12; }
        .note { background: #fff8e1; border: 1px solid #ffe082; color: #6a5400; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; line-height: 1.55; font-size: .92rem; }
        .btn { display: inline-block; border: none; cursor: pointer; background: #2d5a37; color: #fff; font-weight: 700; padding: 11px 22px; border-radius: 999px; text-decoration: none; font-size: .95rem; }
        .btn.ghost { background: #fff; color: #2d5a37; border: 2px solid #2d5a37; }
        .btn.gold { background: #d6a21a; }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid #eef2ef; }
        th { color: #6a7d72; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
        tr.faite td { color: #8a968f; }
        .tag { border-radius: 999px; padding: 3px 11px; font-size: .76rem; font-weight: 800; white-space: nowrap; }
        .tag.bloque { background: #fdeaea; color: #a12; }
        .tag.ok { background: #e7f6ea; color: #1E7A46; }
        .reglages { display: flex; gap: 18px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 6px; }
        .reglages label { display: block; font-weight: 700; font-size: .86rem; margin-bottom: 5px; }
        .reglages input, .reglages select { padding: 9px 12px; border: 1px solid #cfe0d4; border-radius: 10px; font-family: inherit; font-size: .92rem; }
        .compte { font-size: .9rem; color: #5a6b60; margin: 12px 0 0; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 16px; }
        .lien-mini { font-size: .86rem; color: #2d5a37; font-weight: 700; cursor: pointer; text-decoration: underline; background: none; border: none; padding: 0; font-family: inherit; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🔑 Relancer la création de mot de passe</h1>
    <p class="sub">Renvoie son lien personnel à chaque utilisateur <b>beta</b> qui n'a pas pu créer son mot de passe.</p>

    <div class="top">
        <a href="index.php" class="btn ghost">← Retour</a>
        <a href="relance_mdp_beta.php?apercu=1&amp;jours=<?= (int) $jours ?>" target="_blank" class="btn ghost">👁 Voir l'aperçu du mail</a>
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
                <div class="flash ok"><?= count($resultats['ok']) ?> mail(s) envoyé(s) avec succès.</div>
                <table>
                    <tr><th>Nom</th><th>Adresse</th></tr>
                    <?php foreach ($resultats['ok'] as $u): ?>
                        <tr>
                            <td><?= e(trim($u['prenom'] . ' ' . $u['nom'])) ?></td>
                            <td><?= e($u['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
            <?php if (!empty($resultats['ko'])): ?>
                <div class="flash err" style="margin-top:16px;"><?= count($resultats['ko']) ?> échec(s) — ces personnes n'ont rien reçu, tu peux réessayer.</div>
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
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>⚙️ Périmètre</h2>
        <form method="GET" action="relance_mdp_beta.php">
            <div class="reglages">
                <div>
                    <label for="domaine">Domaine des adresses</label>
                    <input type="text" id="domaine" name="domaine" value="<?= e($domaine) ?>" size="22">
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
                Profil <b>beta</b> + adresse en <b><?= e($domaine) ?></b> :
                <b><?= $nbTotal ?></b> compte(s), dont <b><?= $nbBloques ?></b> sans mot de passe défini.
            </p>
        </form>
    </div>

    <div class="card">
        <h2>✉️ Vérifier le rendu avant d'envoyer</h2>
        <p class="sub" style="margin-bottom:14px;">Reçois toi-même le mail pour contrôler son affichage. Le lien qu'il contient est factice, aucun compte n'est modifié.</p>
        <form method="POST" action="relance_mdp_beta.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="test">
            <input type="hidden" name="domaine" value="<?= e($domaine) ?>">
            <input type="hidden" name="jours" value="<?= (int) $jours ?>">
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
        <?php if ($nbTotal === 0): ?>
            <p class="sub">Aucun compte beta avec une adresse en <b><?= e($domaine) ?></b>.</p>
        <?php else: ?>
            <form method="POST" action="relance_mdp_beta.php" id="formEnvoi">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="envoyer">
                <input type="hidden" name="domaine" value="<?= e($domaine) ?>">
                <input type="hidden" name="jours" value="<?= (int) $jours ?>">

                <p style="margin:0 0 12px;">
                    <button type="button" class="lien-mini" onclick="cocher('bloques')">Cocher uniquement ceux qui sont bloqués</button> ·
                    <button type="button" class="lien-mini" onclick="cocher('tous')">Tout cocher</button> ·
                    <button type="button" class="lien-mini" onclick="cocher('rien')">Tout décocher</button>
                </p>

                <table>
                    <tr>
                        <th style="width:34px;"></th>
                        <th>Nom</th>
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
