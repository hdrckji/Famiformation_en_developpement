<?php
require_once 'config.php';
verifierConnexion($db);

$role = (string) ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: ' . famijobSiteUrl('index.php'));
    exit();
}

ensureDepartmentsTable($db);
ensureStudentDepartmentLinksTable($db);

$message = '';
$error = '';

// Onglet actif du hub Paramètres.
// 🗂️ Le module des secteurs, chargé AVANT le traitement du formulaire : les
// actions « renommer », « ranger » et « réinstaller » s'en servent, et ce bloc
// redirige avant d'arriver plus bas dans la page.
secteursCharge();

$section = (string) ($_GET['section'] ?? 'departements');
$allowedSections = ['departements'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'departements';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    // -------- Départements --------
    if (isset($_POST['add_department'])) {
        $name = famijobCapitalizeDepartmentName((string) ($_POST['department_name'] ?? ''));
        if ($name === '') {
            $error = 'Indiquez un nom de département.';
        } elseif (mb_strlen($name) > 120) {
            $error = 'Nom de département trop long (120 caractères max).';
        } else {
            try {
                $target = famijobNormalizeDepartmentName($name);
                $existing = null;
                foreach ($db->query("SELECT id, department_name, is_active FROM departments")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (famijobNormalizeDepartmentName($row['department_name']) === $target) {
                        $existing = $row;
                        break;
                    }
                }
                if ($existing !== null) {
                    if ((int) $existing['is_active'] === 1) {
                        $error = 'Le département « ' . $existing['department_name'] . ' » existe déjà.';
                    } else {
                        $sid = (int) ($_POST['sector_id'] ?? 0);
                        if ($sid > 0) {
                            $db->prepare("UPDATE departments SET is_active = 1, sector_id = ?, updated_at = NOW() WHERE id = ?")
                               ->execute([$sid, (int) $existing['id']]);
                        } else {
                            $db->prepare("UPDATE departments SET is_active = 1, updated_at = NOW() WHERE id = ?")->execute([(int) $existing['id']]);
                        }
                        $message = 'Département « ' . $existing['department_name'] . ' » réactivé.';
                    }
                } else {
                    $sid = (int) ($_POST['sector_id'] ?? 0);
                    $db->prepare("INSERT INTO departments (department_name, is_active, sector_id) VALUES (?, 1, ?)")
                       ->execute([$name, $sid > 0 ? $sid : null]);
                    $message = 'Département « ' . $name . ' » ajouté'
                             . ($sid > 0 ? '.' : ' — il est « à ranger », pense à lui choisir un secteur.');
                }
            } catch (Exception $e) {
                $error = "Impossible d'enregistrer le département : " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['deactivate_department'])) {
        $id = (int) ($_POST['department_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE departments SET is_active = 0, updated_at = NOW() WHERE id = ?")->execute([$id]);
            $message = 'Département retiré. Il n\'apparaît plus dans les demandes, le matching et les disponibilités.';
        }
    } elseif (isset($_POST['activate_department'])) {
        $id = (int) ($_POST['department_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE departments SET is_active = 1, updated_at = NOW() WHERE id = ?")->execute([$id]);
            $message = 'Département réactivé.';
        }
    } elseif (isset($_POST['delete_department'])) {
        $id = (int) ($_POST['department_id'] ?? 0);
        if ($id > 0) {
            try { $db->prepare("DELETE FROM student_department_links WHERE department_id = ?")->execute([$id]); } catch (Exception $e) {}
            $db->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
            $message = 'Département supprimé définitivement.';
        }
    } elseif (isset($_POST['purge_inactive'])) {
        $n = famijobPurgeInactiveDepartments($db);
        $message = $n > 0
            ? ($n . ' département(s) retiré(s) supprimé(s) définitivement.')
            : 'Aucun département retiré à nettoyer.';
    } elseif (isset($_POST['capitalize_all'])) {
        $n = famijobCapitalizeAllDepartments($db);
        $message = 'Casse normalisée (' . $n . ' département(s) modifié(s)).';

    // -------- Secteurs --------
    } elseif (isset($_POST['set_sector'])) {
        // Ranger un département sous un secteur — ou l'en sortir (secteur vide).
        $id = (int) ($_POST['department_id'] ?? 0);
        $sid = (int) ($_POST['sector_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE departments SET sector_id = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$sid > 0 ? $sid : null, $id]);
            $message = $sid > 0 ? 'Département rangé.' : 'Département sorti de son secteur.';
        }
    } elseif (isset($_POST['rename_department'])) {
        // ⚠️ Passe par secteursRenommeDepartement() et JAMAIS par un simple
        // UPDATE : deux autres tables stockent le nom en clair (affectations
        // d'intérimaires, demandes de créneaux). Un UPDATE direct les
        // détacherait sans erreur et sans trace.
        $id = (int) ($_POST['department_id'] ?? 0);
        $nouveau = famijobCapitalizeDepartmentName((string) ($_POST['new_name'] ?? ''));
        if ($id > 0 && $nouveau !== '') {
            $q = $db->prepare("SELECT department_name FROM departments WHERE id = ? LIMIT 1");
            $q->execute([$id]);
            $ancien = (string) $q->fetchColumn();
            if ($ancien === '') {
                $error = 'Département introuvable.';
            } elseif ($ancien === $nouveau) {
                $message = 'Aucun changement.';
            } else {
                $r = secteursRenommeDepartement($db, $ancien, $nouveau);
                $message = $r === 'fusion'
                    ? '« ' . $ancien . ' » fusionné dans « ' . $nouveau . ' » (liens étudiants reportés).'
                    : '« ' . $ancien . ' » renommé en « ' . $nouveau . ' ».';
            }
        }
    } elseif (isset($_POST['add_sector'])) {
        $nom = trim((string) ($_POST['sector_name'] ?? ''));
        if ($nom === '') {
            $error = 'Indiquez un nom de secteur.';
        } else {
            try {
                $pos = (int) $db->query("SELECT COALESCE(MAX(position), 0) + 1 FROM sectors")->fetchColumn();
                $db->prepare("INSERT INTO sectors (sector_name, position, is_active) VALUES (?, ?, 1)
                              ON DUPLICATE KEY UPDATE is_active = 1")->execute([$nom, $pos]);
                $message = 'Secteur « ' . $nom . ' » ajouté.';
            } catch (Exception $e) {
                $error = "Impossible d'ajouter le secteur : " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_sector'])) {
        // On ne supprime QUE le secteur : ses départements repassent « à
        // ranger », avec tous leurs liens étudiants. Supprimer un secteur ne
        // doit jamais faire disparaître un département par ricochet.
        $sid = (int) ($_POST['sector_id'] ?? 0);
        if ($sid > 0) {
            $db->prepare("UPDATE departments SET sector_id = NULL WHERE sector_id = ?")->execute([$sid]);
            $db->prepare("DELETE FROM sectors WHERE id = ?")->execute([$sid]);
            $message = 'Secteur supprimé. Ses départements sont « à ranger ».';
        }
    } elseif (isset($_POST['install_sectors'])) {
        try {
            $b = secteursInstalle($db);
            $message = 'Arbre réinstallé : ' . $b['secteurs'] . ' secteurs, ' . $b['crees'] . ' département(s) créé(s), '
                     . $b['renommes'] . ' renommé(s), ' . $b['fusions'] . ' fusionné(s), '
                     . $b['supprimes'] . ' supprimé(s), ' . count($b['aRanger']) . ' à ranger.';
            // Ce qui a été perdu se dit, ça ne se devine pas.
            if (!empty($b['supprimesAvecLiens'])) {
                $message .= ' ⚠️ ' . $b['liensPerdus'] . ' rattachement(s) étudiant supprimé(s) avec : '
                          . implode(', ', $b['supprimesAvecLiens']) . '.';
            }
        } catch (Exception $e) {
            $error = "Réinstallation impossible : " . $e->getMessage();
        }
    }

    $params = ['section' => 'departements'];
    if ($message !== '') { $params['m'] = $message; }
    if ($error !== '') { $params['e'] = $error; }
    header('Location: parametres.php?' . http_build_query($params));
    exit();
}

if (isset($_GET['m'])) { $message = (string) $_GET['m']; }
if (isset($_GET['e'])) { $error = (string) $_GET['e']; }

$activeDepartments = $db->query(
    "SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$inactiveDepartments = $db->query(
    "SELECT id, department_name FROM departments WHERE is_active = 0 ORDER BY department_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// 🗂️ L'arbre : les secteurs et ce qu'ils contiennent, plus ce qui n'est rangé
// nulle part. On demande les secteurs VIDES aussi : un secteur fraîchement créé
// doit apparaître, sinon on ne peut rien y mettre.
$secteurs = secteursListe($db, true);
$aRanger = secteursSansSecteur($db);
$nbRanges = 0;
foreach ($secteurs as $sct) { $nbRanges += count($sct['departements']); }
?>
<!DOCTYPE html>
<html lang="<?= e(famiLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - FamiJob</title>
    <link rel="shortcut icon" type="image/x-icon" href="famijob_.ico">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#eef3f0; --card:#fff; --line:#dde6df; --text:#1b2c25; --muted:#5c6f67; --accent:#2d5a37; --shadow:0 14px 34px rgba(22,49,33,.1); }
        * { box-sizing:border-box; }
        body { margin:0; padding:24px 16px 60px; background:var(--bg); font-family:'Manrope',sans-serif; color:var(--text); }
        .page { max-width:900px; margin:0 auto; }
        .hero { background:linear-gradient(135deg,#264e35,#3f6b4d); color:#fff; border-radius:22px; padding:22px 24px; box-shadow:var(--shadow); margin-bottom:18px; }
        .hero h1 { margin:6px 0 4px; font-size:1.5rem; }
        .hero p { margin:0; opacity:.9; font-size:.92rem; }
        .hero a.back { color:#fff; text-decoration:none; font-weight:700; background:rgba(255,255,255,.16); padding:8px 14px; border-radius:999px; display:inline-block; }
        .layout { display:grid; grid-template-columns:220px 1fr; gap:18px; align-items:start; }
        .nav { background:var(--card); border-radius:16px; box-shadow:var(--shadow); padding:10px; position:sticky; top:16px; }
        .nav a { display:flex; align-items:center; gap:10px; text-decoration:none; color:var(--text); font-weight:700; padding:11px 12px; border-radius:12px; font-size:.92rem; }
        .nav a.active { background:#edf5ef; color:var(--accent); }
        .nav a:hover { background:#f3f8f5; }
        .nav .soon { color:#9bb0a3; font-weight:600; font-size:.82rem; padding:11px 12px; }
        .card { background:var(--card); border-radius:16px; box-shadow:var(--shadow); padding:18px; margin-bottom:18px; }
        .card h2 { margin:0 0 6px; font-size:1.12rem; }
        .card .sub { color:var(--muted); font-size:.9rem; margin:0 0 14px; }
        .add-row { display:flex; gap:10px; flex-wrap:wrap; }
        input[type=text] { flex:1; min-width:220px; border:1px solid #cfdad3; border-radius:10px; padding:11px 12px; font-family:inherit; font-size:.95rem; }
        .btn { border:none; border-radius:10px; padding:11px 16px; font-weight:800; cursor:pointer; font-family:inherit; font-size:.88rem; }
        .btn-primary { background:var(--accent); color:#fff; }
        .btn-soft { background:#edf5ef; color:var(--accent); }
        .btn-warn { background:#fdf0dd; color:#9a6a15; }
        .btn-ko { background:#fae4e1; color:#a13e35; }
        .alert { padding:11px 14px; border-radius:12px; font-weight:700; margin-bottom:16px; background:#dff3e3; color:#1d6a39; }
        .alert.err { background:#fae4e1; color:#a13e35; }
        /* 🗂️ Arbre secteurs > départements */
        .secteur-bloc { border:1px solid var(--line); border-radius:12px; padding:10px 12px; margin-top:12px; background:#fbfdfc; }
        .secteur-tete { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
        .secteur-nb { background:#e6efe9; color:#3d6b48; border-radius:999px; padding:1px 8px; font-size:.78rem; font-weight:700; }
        .secteur-tete .sect-x { margin-left:auto; background:none; border:none; color:#b3554b; cursor:pointer; font-size:.95rem; }
        .chip-sel { border:none; background:transparent; font-size:.72rem; color:var(--muted); max-width:104px; cursor:pointer; }
        .dep-liste { display:flex; flex-direction:column; gap:8px; margin-top:10px; }
        .dep-ligne { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:8px 10px; background:#fff; border:1px solid var(--line); border-radius:10px; }
        .dep-nom { font-weight:700; min-width:190px; display:flex; flex-direction:column; gap:2px; }
        .dep-echo { font-weight:500; font-size:.74rem; color:#8a5a12; }
        .dep-form { display:inline-flex; align-items:center; gap:6px; margin:0; }
        .chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:4px; }
        .chip { display:inline-flex; align-items:center; gap:8px; background:#f3f8f5; border:1px solid #e0ebe3; border-radius:999px; padding:6px 6px 6px 14px; font-weight:700; font-size:.9rem; }
        .chip.off { opacity:.7; }
        .chip.off .cn { color:#93a29a; text-decoration:line-through; }
        .chip form { display:inline; }
        .chip button { border:none; background:transparent; cursor:pointer; font-size:.95rem; padding:4px 6px; border-radius:999px; color:#a13e35; }
        .chip button.act { color:#2d5a37; }
        .chip button:hover { background:#fff; }
        .muted { color:var(--muted); font-size:.86rem; }
        .empty { color:var(--muted); padding:6px 2px; }
        @media (max-width:720px){ .layout{ grid-template-columns:1fr; } .nav{ position:static; display:flex; gap:6px; } }
    </style>
</head>
<body>
<div class="page">
    <div class="hero">
        <a class="back" href="index.php">⬅ Retour FamiJob</a>
        <h1>⚙️ Paramètres</h1>
        <p>Configurez l'application sans toucher au code : tout est enregistré en base.</p>
    </div>

    <?php if ($message !== ''): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

    <div class="layout">
        <nav class="nav">
            <a class="<?= $section === 'departements' ? 'active' : '' ?>" href="?section=departements">🏷️ Départements</a>
            <span class="soon">Plus de réglages à venir…</span>
        </nav>

        <div class="content">
            <?php if ($section === 'departements'): ?>
                <div class="card">
                    <h2>Ajouter un département</h2>
                    <p class="sub">S'applique partout : demandes, matching, disponibilités, vue horaire.</p>
                    <form method="post" class="add-row">
                        <?= csrfField() ?>
                        <input type="text" name="department_name" maxlength="120" placeholder="Nom du département (ex. Abbaye)" required>
                        <select name="sector_id">
                            <option value="">— Sans secteur —</option>
                            <?php foreach ($secteurs as $sct): ?>
                                <option value="<?= (int) $sct['id'] ?>"><?= e($sct['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary" type="submit" name="add_department" value="1">Ajouter</button>
                    </form>
                </div>

                <?php if (!empty($aRanger)): ?>
                <div class="card" style="border:2px solid #e0a33c; background:#fffaf0;">
                    <h2 style="margin:0 0 4px;">⚠️ À ranger (<?= count($aRanger) ?>)</h2>
                    <p class="sub">
                        Ces départements existaient avant les secteurs et <b>n'ont pas d'équivalent évident</b> dans
                        la nouvelle liste — les ranger au hasard déplacerait des étudiants dans un rayon qui n'est pas
                        le leur. Ils fonctionnent normalement et gardent leurs liens étudiants&nbsp;: choisis leur
                        secteur, ou renomme-les vers un département existant pour les fusionner.
                    </p>
                    <div class="dep-liste">
                        <?php foreach ($aRanger as $d): ?>
                            <div class="dep-ligne">
                                <span class="dep-nom">
                                    <?= e((string) $d['department_name']) ?>
                                    <?php $res = secteursRessemblances($db, (string) $d['department_name'], (int) $d['id']);
                                          $echos = [];
                                          foreach ($res['secteurs'] as $sn) { $echos[] = 'secteur « ' . $sn . ' »'; }
                                          foreach ($res['departements'] as $dn) { $echos[] = '« ' . $dn['nom'] . ' »'; }
                                          if ($echos): ?>
                                        <small class="dep-echo">ressemble à <?= e(implode(', ', array_slice($echos, 0, 3))) ?><?= count($echos) > 3 ? '…' : '' ?> — mais c'est une autre ligne</small>
                                    <?php endif; ?>
                                </span>
                                <form method="post" class="dep-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="department_id" value="<?= (int) $d['id'] ?>">
                                    <select name="sector_id" onchange="this.form.submit()">
                                        <option value="">Ranger dans…</option>
                                        <?php foreach ($secteurs as $sct): ?>
                                            <option value="<?= (int) $sct['id'] ?>"><?= e($sct['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="set_sector" value="1">
                                </form>
                                <form method="post" class="dep-form"
                                      onsubmit="return this.new_name.value === '' || confirm('Fusionner « <?= e((string) $d['department_name']) ?> » dans « ' + this.new_name.value + ' » ?

Ses étudiants, affectations et demandes de créneaux basculent sur ce rayon.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="department_id" value="<?= (int) $d['id'] ?>">
                                    <select name="new_name">
                                        <option value="">Fusionner dans…</option>
                                        <?php foreach ($secteurs as $s2): ?>
                                            <?php if (empty($s2['departements'])) { continue; } ?>
                                            <optgroup label="<?= e($s2['nom']) ?>">
                                                <?php foreach ($s2['departements'] as $dd): ?>
                                                    <option value="<?= e($dd['nom']) ?>"><?= e($dd['nom']) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-soft" type="submit" name="rename_department" value="1">OK</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                        <h2 style="margin:0;">Secteurs (<?= count($secteurs) ?>) et départements (<?= $nbRanges ?>)</h2>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <form method="post">
                                <?= csrfField() ?>
                                <button class="btn btn-soft" type="submit" name="capitalize_all" value="1" title="Mettre une majuscule initiale à tous">Aa Normaliser la casse</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Réinstaller l\'arbre de référence ?\n\nLes secteurs et départements manquants sont (re)créés et rattachés. Rien n\'est supprimé, et les départements « à ranger » ne bougent pas.');">
                                <?= csrfField() ?>
                                <button class="btn btn-soft" type="submit" name="install_sectors" value="1" title="Recréer secteurs et départements manquants">↻ Réinstaller l'arbre</button>
                            </form>
                        </div>
                    </div>
                    <p class="sub">Dans les formulaires, choisir un secteur restreint la liste des départements proposés.</p>

                    <?php foreach ($secteurs as $sct): ?>
                        <div class="secteur-bloc">
                            <div class="secteur-tete">
                                <b><?= e($sct['nom']) ?></b>
                                <span class="secteur-nb"><?= count($sct['departements']) ?></span>
                                <form method="post" class="dep-form"
                                      onsubmit="return confirm('Supprimer le secteur « <?= e($sct['nom']) ?> » ?\n\nSes <?= count($sct['departements']) ?> département(s) ne sont PAS supprimés : ils repassent « à ranger » avec leurs liens étudiants.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="sector_id" value="<?= (int) $sct['id'] ?>">
                                    <button type="submit" name="delete_sector" value="1" class="sect-x" title="Supprimer le secteur">✕</button>
                                </form>
                            </div>
                            <?php if (empty($sct['departements'])): ?>
                                <div class="empty" style="margin:6px 0 0;">Aucun département dans ce secteur.</div>
                            <?php else: ?>
                                <div class="chips">
                                    <?php foreach ($sct['departements'] as $d): ?>
                                        <span class="chip">
                                            <span class="cn"><?= e($d['nom']) ?></span>
                                            <form method="post" class="dep-form" title="Déplacer vers un autre secteur">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="department_id" value="<?= (int) $d['id'] ?>">
                                                <input type="hidden" name="set_sector" value="1">
                                                <select name="sector_id" onchange="this.form.submit()" class="chip-sel">
                                                    <?php foreach ($secteurs as $s2): ?>
                                                        <option value="<?= (int) $s2['id'] ?>"<?= $s2['id'] === $sct['id'] ? ' selected' : '' ?>><?= e($s2['nom']) ?></option>
                                                    <?php endforeach; ?>
                                                    <option value="">— Sortir du secteur —</option>
                                                </select>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Retirer « <?= e($d['nom']) ?> » ? (réactivable ensuite)');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="department_id" value="<?= (int) $d['id'] ?>">
                                                <button type="submit" name="deactivate_department" value="1" title="Retirer">✕</button>
                                            </form>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <form method="post" class="add-row" style="margin-top:14px;">
                        <?= csrfField() ?>
                        <input type="text" name="sector_name" maxlength="120" placeholder="Nouveau secteur (ex. Logistique)" required>
                        <button class="btn btn-soft" type="submit" name="add_sector" value="1">+ Secteur</button>
                    </form>
                </div>

                <?php if (!empty($inactiveDepartments)): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                        <h2 style="margin:0;">Départements retirés (<?= count($inactiveDepartments) ?>)</h2>
                        <form method="post" onsubmit="return confirm('Supprimer DÉFINITIVEMENT tous les départements retirés ?');">
                            <?= csrfField() ?>
                            <button class="btn btn-ko" type="submit" name="purge_inactive" value="1">🧹 Nettoyer tous les retirés</button>
                        </form>
                    </div>
                    <p class="sub">Ils n'apparaissent nulle part. Réactivez-les, ou supprimez-les définitivement.</p>
                    <div class="chips">
                        <?php foreach ($inactiveDepartments as $dept): ?>
                            <span class="chip off">
                                <span class="cn"><?= e((string) $dept['department_name']) ?></span>
                                <form method="post" title="Réactiver">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="department_id" value="<?= (int) $dept['id'] ?>">
                                    <button type="submit" name="activate_department" value="1" class="act" title="Réactiver">↩</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Supprimer DÉFINITIVEMENT ce département ?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="department_id" value="<?= (int) $dept['id'] ?>">
                                    <button type="submit" name="delete_department" value="1" title="Supprimer définitivement">🗑</button>
                                </form>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . "/includes/topbar.php"; echo famijobScrollKeeperHtml(); ?>
</body>
</html>
