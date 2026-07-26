<?php
// ============================================================
// gestion-beta.php — CRÉATION DU CONTENU DE LA VERSION BETA.
// Réservé à l'admin. Crée (si absents) les modules de la beta et donne, pour
// chacun, un bouton « Ajouter le contenu » qui ouvre l'éditeur EXISTANT
// (module.php) où l'upload PDF/vidéo déclenche le moteur IA (mise en page +
// traduction NL + orthographe). On ne réinvente donc pas le moteur.
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/modules.php';
if (function_exists('ensureModulesTable')) { try { ensureModulesTable($db); } catch (Throwable $e) {} }

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

// Structure voulue pour la beta. roles='beta' → visible seulement par la beta.
// a_evaluer reste à 0 (défaut) → quiz non évalués. On crée des coquilles vides ;
// le contenu s'ajoute ensuite via l'éditeur (upload → IA).
$structure = [
    ['nom' => 'Onboarding', 'icon' => '🚀', 'enfants' => [
        ['nom' => 'Guide',  'icon' => '📄'],
        ['nom' => 'Vidéo',  'icon' => '🎬'],
    ]],
    ['nom' => 'Caisse', 'icon' => '💳', 'enfants' => [
        ['nom' => 'Formation Caisse', 'icon' => '📄'],
        ['nom' => 'Module technique',  'icon' => '📄'],
        ['nom' => 'Mes 2 premières semaines en caisse', 'icon' => '📄'],
    ]],
];

// Trouve un module beta par nom + parent, sinon le crée. Renvoie l'id.
function betaModule(PDO $db, $nom, $icon, $parentId, $isContainer)
{
    $sql = "SELECT id FROM modules WHERE nom = ? AND roles = 'beta' AND "
        . ($parentId === null ? "parent_id IS NULL" : "parent_id = ?") . " LIMIT 1";
    $st = $db->prepare($sql);
    $st->execute($parentId === null ? [$nom] : [$nom, $parentId]);
    $id = $st->fetchColumn();
    if ($id !== false) { return (int) $id; }
    $ins = $db->prepare("INSERT INTO modules (nom, description, is_container, parent_id, icon, roles, sort_order) VALUES (?, '', ?, ?, ?, 'beta', 0)");
    $ins->execute([$nom, $isContainer ? 1 : 0, $parentId, $icon]);
    return (int) $db->lastInsertId();
}

// Création / récupération de toute l'arborescence beta.
$arbre = [];
foreach ($structure as $c) {
    $cid = betaModule($db, $c['nom'], $c['icon'], null, true);
    $enfants = [];
    foreach ($c['enfants'] as $e) {
        $eid = betaModule($db, $e['nom'], $e['icon'], $cid, false);
        $mod = getModuleById($db, $eid);
        $aDuContenu = $mod && (!empty($mod['pdf_path']) || !empty($mod['video_path']) || !empty($mod['contenu_ia']));
        $enfants[] = ['id' => $eid, 'nom' => $e['nom'], 'icon' => $e['icon'], 'rempli' => $aDuContenu];
    }
    $arbre[] = ['id' => $cid, 'nom' => $c['nom'], 'icon' => $c['icon'], 'enfants' => $enfants];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Beta - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: #eef4ef; margin: 0; padding: 24px 16px 60px; color: #244230; }
        .wrap { max-width: 820px; margin: 0 auto; }
        h1 { color: #2d5a37; font-size: 1.6rem; margin: 0 0 4px; }
        .sub { color: #5a6b60; margin: 0 0 20px; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; margin-bottom: 18px; box-shadow: 0 6px 20px rgba(14,59,36,.08); border: 1px solid #e6efe8; }
        .card h2 { margin: 0 0 12px; font-size: 1.2rem; color: #2d5a37; }
        .row { display: flex; align-items: center; gap: 14px; padding: 12px 6px; border-top: 1px solid #eef2ee; }
        .row:first-of-type { border-top: none; }
        .row .ico { font-size: 1.6rem; }
        .row .nom { flex: 1; font-weight: 700; }
        .badge { border-radius: 999px; padding: 3px 12px; font-size: .8rem; font-weight: 800; }
        .badge.vide { background: #fdeaea; color: #a12; }
        .badge.ok { background: #e7f6ea; color: #1E7A46; }
        .btn { display: inline-block; border: none; cursor: pointer; background: #2d5a37; color: #fff; font-weight: 700; padding: 10px 18px; border-radius: 999px; text-decoration: none; font-size: .92rem; }
        .btn.ghost { background: #fff; color: #2d5a37; border: 2px solid #2d5a37; }
        .note { background: #fff8e1; border: 1px solid #ffe082; color: #6a5400; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; line-height: 1.5; font-size: .92rem; }
        .top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🧪 Gestion de la version beta</h1>
    <p class="sub">Crée le contenu de la version beta. Chaque module ci-dessous est déjà prêt : clique sur <b>« Ajouter le contenu »</b> pour déposer ton PDF ou ta vidéo — l'IA fait la mise en page, la traduction NL et l'orthographe automatiquement.</p>

    <div class="note">ℹ️ Les modules sont réservés au profil <b>beta</b> et <b>non évalués</b>. Le contenu (PDF/vidéo) s'ajoute via l'éditeur habituel qui s'ouvre quand tu cliques « Ajouter le contenu ».</div>

    <div class="top">
        <a href="beta.php" class="btn ghost">👁 Voir l'accueil beta</a>
        <a href="index.php" class="btn ghost">← Retour</a>
    </div>

    <?php foreach ($arbre as $c): ?>
        <div class="card">
            <h2><?= htmlspecialchars($c['icon'] . ' ' . $c['nom']) ?></h2>
            <?php foreach ($c['enfants'] as $e): ?>
                <div class="row">
                    <span class="ico"><?= htmlspecialchars($e['icon']) ?></span>
                    <span class="nom"><?= htmlspecialchars($e['nom']) ?></span>
                    <span class="badge <?= $e['rempli'] ? 'ok' : 'vide' ?>"><?= $e['rempli'] ? 'Contenu ajouté ✅' : 'À remplir' ?></span>
                    <a class="btn" href="module.php?id=<?= (int) $e['id'] ?>"><?= $e['rempli'] ? '✏️ Modifier' : '➕ Ajouter le contenu' ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
