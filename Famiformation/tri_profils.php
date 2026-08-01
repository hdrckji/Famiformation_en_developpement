<?php
// ============================================================
// tri_profils.php — FAIRE LE TRI ENTRE VISITEURS ET PERSONNEL.
//
// Tous les nouveaux comptes entrent en « beta ». Or une partie de ces gens
// travaille déjà chez Famiflora : ils n'ont rien à faire en beta, ils doivent
// avoir le profil employé et l'accès qui va avec.
//
// On les reconnaît grâce à la liste du personnel (includes/personnel_liste.php,
// générée depuis l'export Excel). Un compte beta dont le nom ET le prénom
// figurent dans cette liste bascule en profil employé.
//
// SEULS LES COMPTES BETA sont concernés : on ne touche jamais à un profil déjà
// choisi à la main (étudiant, mentor, teamcoach…), sinon un tri automatique
// écraserait une décision humaine.
//
// Les inscriptions faites depuis le quiz reçoivent, elles, le bon profil dès la
// création (voir roleInscription() dans quiz/api.php). Cette page sert donc à
// rattraper les comptes créés AVANT la mise en place de la règle.
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/csrf.php';
require_once 'includes/personnel_liste.php';
require_once 'includes/events.php';   // logEvent() : trace des changements de profil

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

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
$ROLES_EMPLOYE = ['employe_magasin', 'employe_logistique'];

// Profil visé. Par défaut celui de personnelRoleCible(), le même que celui
// donné automatiquement aux nouvelles inscriptions : les deux chemins doivent
// aboutir au même profil, sans quoi le tri deviendrait incohérent.
$roleCible = (string) ($_REQUEST['role_cible'] ?? personnelRoleCible());
if (!in_array($roleCible, $ROLES_EMPLOYE, true)) { $roleCible = personnelRoleCible(); }

// ─────────────────────────────────────────────────────────────────────────────
// QUI EST CONCERNÉ : les comptes BETA dont le nom + prénom sont dans la liste.
// ─────────────────────────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT id, identifiant, prenom, nom, email, role, statut_date
     FROM utilisateurs
     WHERE role = 'beta'
     ORDER BY nom ASC, prenom ASC"
);
$stmt->execute();
$betas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$reconnus = [];   // comptes beta présents dans la liste du personnel
$inconnus = [];   // comptes beta absents de la liste (ils restent en beta)
foreach ($betas as $u) {
    $m = personnelTrouve($u['nom'] ?? '', $u['prenom'] ?? '');
    if ($m) {
        $u['dossier'] = $m['dossier'];
        $reconnus[] = $u;
    } else {
        $inconnus[] = $u;
    }
}

$flash = '';
$resultat = null;

// ─────────────────────────────────────────────────────────────────────────────
// APPLICATION — uniquement sur les comptes cochés, et uniquement s'ils sont
// encore beta au moment de l'écriture (la condition est aussi dans le SQL :
// deux admins qui travaillent en même temps ne doivent pas se marcher dessus).
// ─────────────────────────────────────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'appliquer') {
    requireValidCSRF();

    $choisis = array_map('intval', (array) ($_POST['user_ids'] ?? []));
    $choisis = array_values(array_unique(array_filter($choisis)));

    // On ne fait confiance qu'à la liste calculée côté serveur.
    $autorises = [];
    foreach ($reconnus as $u) { $autorises[(int) $u['id']] = $u; }

    $faits = [];
    $ignores = 0;
    if (empty($choisis)) {
        $flash = "<div class='flash err'>❌ Aucun compte sélectionné.</div>";
    } else {
        // 🌿 REJOUER L'ACCUEIL DE BIENVENUE.
        // L'animation de bienvenue ne se déclenche qu'une fois, quand la colonne
        // welcome_seen vaut 0 ; elle passe à 1 dès le premier affichage et le
        // changement de profil n'y touche pas. Quelqu'un qui a déjà ouvert le site
        // en beta ne la reverrait donc jamais. Or passer en employé, c'est bien sa
        // première visite du « vrai » site : on remet le compteur à zéro pour qu'il
        // soit accueilli comme il se doit.
        $rejouerAccueil = !empty($_POST['rejouer_accueil']);

        $upd = $db->prepare("UPDATE utilisateurs SET role = ? WHERE id = ? AND role = 'beta'");
        $updAccueil = $db->prepare("UPDATE utilisateurs SET welcome_seen = 0 WHERE id = ?");
        foreach ($choisis as $id) {
            if (!isset($autorises[$id])) { $ignores++; continue; }
            $upd->execute([$roleCible, $id]);
            if ($rejouerAccueil && $upd->rowCount() > 0) {
                try { $updAccueil->execute([$id]); } catch (Exception $e) { /* colonne absente : sans gravité */ }
            }
            if ($upd->rowCount() > 0) {
                $faits[] = $autorises[$id];
                // Trace : un changement de profil doit rester retrouvable.
                // (Ne PAS utiliser famiLogChange : il est défini dans module_save.php,
                // que cette page ne charge pas — l'appel ne partait jamais.)
                if (function_exists('logEvent')) {
                    @logEvent($db, 'user_updated', (int) ($_SESSION['user_id'] ?? 0), 0,
                        '👤 Profil passé en ' . ($LIBELLES_ROLES[$roleCible] ?? $roleCible)
                        . ' (personnel reconnu) : ' . trim(($autorises[$id]['prenom'] ?? '') . ' ' . ($autorises[$id]['nom'] ?? '')));
                }
            } else {
                $ignores++;
            }
        }
        $resultat = ['faits' => $faits, 'ignores' => $ignores];

        // On recalcule : les comptes basculés ne sont plus beta.
        $stmt->execute();
        $betas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reconnus = [];
        $inconnus = [];
        foreach ($betas as $u) {
            $m = personnelTrouve($u['nom'] ?? '', $u['prenom'] ?? '');
            if ($m) { $u['dossier'] = $m['dossier']; $reconnus[] = $u; } else { $inconnus[] = $u; }
        }
    }
}

$nbListe = count(personnelListe());
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tri des profils - FamiFormation</title>
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
        .top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid #eef2ef; }
        th { color: #6a7d72; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
        .tag { border-radius: 999px; padding: 3px 11px; font-size: .76rem; font-weight: 800; white-space: nowrap; }
        .tag.ok { background: #e7f6ea; color: #1E7A46; }
        .tag.dossier { background: #eef4ef; color: #3d6b48; }
        .reglages { display: flex; gap: 18px; flex-wrap: wrap; align-items: flex-end; }
        .reglages label { display: block; font-weight: 700; font-size: .86rem; margin-bottom: 5px; }
        .reglages select { padding: 9px 12px; border: 1px solid #cfe0d4; border-radius: 10px; font-family: inherit; font-size: .92rem; }
        .compte { font-size: .9rem; color: #5a6b60; margin: 14px 0 0; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 16px; }
        .lien-mini { font-size: .86rem; color: #2d5a37; font-weight: 700; cursor: pointer; text-decoration: underline; background: none; border: none; padding: 0; font-family: inherit; }
        .scroll { overflow-x: auto; }
        details { margin-top: 10px; } summary { cursor: pointer; font-weight: 700; color: #2d5a37; font-size: .92rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>👥 Tri des profils</h1>
    <p class="sub">Fait passer en profil employé les comptes <b>beta</b> qui figurent dans la liste du personnel.</p>

    <div class="top"><a href="index.php" class="btn ghost">← Retour</a></div>

    <?= $flash ?>

    <div class="note">
        ℹ️ La liste du personnel compte <b><?= (int) $nbListe ?></b> personnes.
        La comparaison ignore les accents, la casse et une éventuelle <b>inversion nom / prénom</b>.<br>
        ✅ <b>Seuls les comptes beta</b> sont proposés : un profil déjà choisi à la main (étudiant, mentor, teamcoach…) n'est jamais touché.<br>
        🆕 Les inscriptions faites depuis le quiz reçoivent déjà le bon profil <b>dès leur création</b>
        (règle active depuis le <b><?= date('d/m/Y à H\hi', strtotime(personnelRegleActiveDepuis())) ?></b><?= personnelRegleActive() ? '' : ' — <b>pas encore active</b>' ?>).
        Cette page sert à rattraper les comptes créés avant.
    </div>

    <?php if ($resultat !== null): ?>
        <div class="card">
            <h2>✅ Résultat</h2>
            <?php if (!empty($resultat['faits'])): ?>
                <div class="flash ok">
                    <?= count($resultat['faits']) ?> compte(s) passé(s) en
                    <b><?= e($LIBELLES_ROLES[$roleCible] ?? $roleCible) ?></b>.
                </div>
                <div class="scroll">
                    <table>
                        <tr><th>Nom</th><th>Identifiant</th><th>Adresse</th></tr>
                        <?php foreach ($resultat['faits'] as $u): ?>
                            <tr>
                                <td><?= e(trim($u['prenom'] . ' ' . $u['nom'])) ?></td>
                                <td><?= e($u['identifiant']) ?></td>
                                <td><?= e($u['email']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!empty($resultat['ignores'])): ?>
                <div class="flash err" style="margin-top:14px;">
                    <?= (int) $resultat['ignores'] ?> compte(s) ignoré(s) : ils n'étaient plus en beta au moment de l'écriture
                    (déjà modifiés entre-temps).
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>🎯 Comptes beta reconnus comme personnel</h2>
        <?php if (empty($reconnus)): ?>
            <p class="sub">Aucun compte beta ne correspond à la liste du personnel. Rien à trier.</p>
        <?php else: ?>
            <form method="POST" action="tri_profils.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="appliquer">

                <div class="reglages" style="margin-bottom:16px;">
                    <div>
                        <label for="role_cible">Profil à attribuer</label>
                        <select id="role_cible" name="role_cible">
                            <?php foreach ($ROLES_EMPLOYE as $r): ?>
                                <option value="<?= e($r) ?>" <?= $r === $roleCible ? 'selected' : '' ?>><?= e($LIBELLES_ROLES[$r]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label style="display:flex; align-items:flex-start; gap:9px; background:#f6faf7; border:1px solid #dde9df; border-radius:12px; padding:12px 14px; margin-bottom:16px; cursor:pointer;">
                    <input type="checkbox" name="rejouer_accueil" value="1" checked style="margin-top:3px;">
                    <span style="font-size:.92rem; line-height:1.5;">
                        <strong>Leur rejouer l'accueil de bienvenue</strong><br>
                        <span class="sub" style="margin:0;">L'animation de bienvenue ne se déclenche qu'une fois et le changement de profil n'y touche pas :
                        sans cette case, quelqu'un qui a déjà ouvert le site en beta ne la reverrait jamais. Or passer en employé,
                        c'est sa première visite du vrai site.</span>
                    </span>
                </label>

                <p style="margin:0 0 12px;">
                    <button type="button" class="lien-mini" onclick="cocher(true)">Tout cocher</button> ·
                    <button type="button" class="lien-mini" onclick="cocher(false)">Tout décocher</button>
                </p>

                <div class="scroll">
                    <table>
                        <tr>
                            <th style="width:34px;"></th>
                            <th>Nom (compte)</th>
                            <th>Identifiant</th>
                            <th>Adresse</th>
                            <th>Dossier</th>
                        </tr>
                        <?php foreach ($reconnus as $u): ?>
                            <tr>
                                <td><input type="checkbox" name="user_ids[]" value="<?= (int) $u['id'] ?>" class="case" checked></td>
                                <td><?= e(trim($u['prenom'] . ' ' . $u['nom'])) ?></td>
                                <td><?= e($u['identifiant']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><span class="tag dossier"><?= e($u['dossier']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="actions">
                    <button type="submit" class="btn"
                            onclick="return confirm('Changer le profil des comptes coches ?\n\nIls passeront de beta au profil choisi et verront immediatement le contenu correspondant.');">
                        👥 Appliquer aux comptes cochés
                    </button>
                    <span class="compte" id="compteur"></span>
                </div>
            </form>

            <script>
                function cocher(v) { document.querySelectorAll('.case').forEach((c) => { c.checked = v; }); maj(); }
                function maj() {
                    const n = document.querySelectorAll('.case:checked').length;
                    document.getElementById('compteur').textContent = n + ' compte(s) sélectionné(s).';
                }
                document.querySelectorAll('.case').forEach((c) => c.addEventListener('change', maj));
                maj();
            </script>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>🧪 Comptes beta non reconnus</h2>
        <p class="sub" style="margin-bottom:8px;">
            Ces comptes restent en beta : leur nom ne figure pas dans la liste du personnel.
            Vérifie s'il s'agit bien de visiteurs — une faute de frappe dans le nom suffit à faire rater la correspondance.
        </p>
        <?php if (empty($inconnus)): ?>
            <p class="sub">Aucun.</p>
        <?php else: ?>
            <details>
                <summary><?= count($inconnus) ?> compte(s) — afficher</summary>
                <div class="scroll" style="margin-top:12px;">
                    <table>
                        <tr><th>Nom (compte)</th><th>Identifiant</th><th>Adresse</th></tr>
                        <?php foreach ($inconnus as $u): ?>
                            <tr>
                                <td><?= e(trim($u['prenom'] . ' ' . $u['nom'])) ?></td>
                                <td><?= e($u['identifiant']) ?></td>
                                <td><?= e($u['email']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
