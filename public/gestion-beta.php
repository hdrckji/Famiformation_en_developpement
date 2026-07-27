<?php
// ============================================================
// gestion-beta.php — TON espace pour déposer le contenu de la version beta.
// Les modules créés ici sont en roles='beta' : ils N'APPARAISSENT PAS sur
// l'accueil admin (filtrés dans index.php), ils ne servent qu'à la beta.
// Chaque dépôt PDF/vidéo passe par le moteur existant (module_save.php,
// action "content") qui uniformise + traduit NL (Claude + whisper).
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/modules.php';
require_once 'includes/csrf.php';
require_once 'includes/beta_quiz_data.php';   // banque de questions des quiz beta
if (function_exists('ensureModulesTable')) { try { ensureModulesTable($db); } catch (Throwable $e) {} }

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

// 🧹 NETTOYAGE : on retire UNIQUEMENT les sous-modules « Guide / Vidéo » VIDES
// (parasites créés par erreur avant l'upload réel), dans l'arbre beta — sans
// jamais toucher aux modules structurels (Onboarding, Caisse, Formation Caisse…).
try {
    $db->exec(
        "DELETE c FROM modules c
         LEFT JOIN modules p ON c.parent_id = p.id
         LEFT JOIN modules gp ON p.parent_id = gp.id
         WHERE c.nom IN ('Guide', 'Vidéo', 'Video', 'Gids')
           AND (c.pdf_path IS NULL OR c.pdf_path = '')
           AND (c.video_path IS NULL OR c.video_path = '')
           AND (c.contenu_ia IS NULL OR c.contenu_ia = '')
           AND (p.roles = 'beta' OR gp.roles = 'beta')"
    );
} catch (Throwable $e) { /* multi-delete non supporté : sans gravité */ }

// 🔧 On REMET À PLAT côté base : les 4 modules beta restent au niveau racine
// (comme tu les gères). Si un essai précédent avait rangé les modules Caisse
// sous un conteneur « Caisse », on les remonte au racine et on supprime ce
// conteneur — le regroupement « Caisse » se fait UNIQUEMENT à l'affichage beta.
try {
    $cc = $db->query("SELECT id FROM modules WHERE nom = 'Caisse' AND roles = 'beta' AND parent_id IS NULL LIMIT 1");
    $ccId = $cc ? $cc->fetchColumn() : false;
    if ($ccId !== false) {
        $db->prepare("UPDATE modules SET parent_id = NULL WHERE parent_id = ?")->execute([(int) $ccId]);
        $db->prepare("DELETE FROM modules WHERE id = ? AND roles = 'beta'")->execute([(int) $ccId]);
    }
} catch (Throwable $e) {}

// Trouve/crée un module beta racine par son nom. Le remonte au racine si besoin.
function betaModule(PDO $db, $nom, $icon)
{
    $st = $db->prepare("SELECT id FROM modules WHERE nom = ? AND roles = 'beta' AND parent_id IS NULL LIMIT 1");
    $st->execute([$nom]);
    $id = $st->fetchColumn();
    if ($id !== false) { return (int) $id; }
    // Existe ailleurs (rangé par erreur) ? On le remonte au racine.
    $o = $db->prepare("SELECT id FROM modules WHERE nom = ? AND roles = 'beta' LIMIT 1");
    $o->execute([$nom]);
    $ex = $o->fetchColumn();
    if ($ex !== false) {
        $db->prepare("UPDATE modules SET parent_id = NULL, icon = ? WHERE id = ?")->execute([$icon, (int) $ex]);
        return (int) $ex;
    }
    $ins = $db->prepare("INSERT INTO modules (nom, description, is_container, parent_id, icon, roles, sort_order) VALUES (?, '', 1, NULL, ?, 'beta', 0)");
    $ins->execute([$nom, $icon]);
    return (int) $db->lastInsertId();
}
function betaRempli(PDO $db, $id)
{
    try {
        $c = $db->prepare("SELECT COUNT(*) FROM modules WHERE (id = ? OR parent_id = ?) AND (pdf_path IS NOT NULL OR video_path IS NOT NULL OR contenu_ia IS NOT NULL)");
        $c->execute([(int) $id, (int) $id]);
        return ((int) $c->fetchColumn()) > 0;
    } catch (Throwable $e) { return false; }
}

// Les 4 modules beta, TOUS au niveau racine (base à plat).
$sections = [
    ['nom' => 'Onboarding',                         'icon' => '🚀'],
    ['nom' => 'Formation Caisse',                   'icon' => '💳'],
    ['nom' => 'Module technique',                   'icon' => '🔧'],
    ['nom' => 'Mes 2 premières semaines en caisse', 'icon' => '🗓️'],
];
function betaSection(PDO $db, $nom, $icon)
{
    $id = betaModule($db, $nom, $icon);
    return ['id' => $id, 'rempli' => betaRempli($db, $id)];
}

$flash = $_SESSION['module_flash'] ?? '';
unset($_SESSION['module_flash']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Beta - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: #eef4ef; margin: 0; padding: 24px 16px 60px; color: #244230; }
        .wrap { max-width: 760px; margin: 0 auto; }
        h1 { color: #2d5a37; font-size: 1.6rem; margin: 0 0 4px; }
        .sub { color: #5a6b60; margin: 0 0 18px; line-height: 1.5; }
        .flash { background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 600; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; margin-bottom: 16px; box-shadow: 0 6px 20px rgba(14,59,36,.08); border: 1px solid #e6efe8; }
        .card-h { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
        .card-h .ico { font-size: 1.7rem; }
        .card-h h2 { margin: 0; font-size: 1.2rem; color: #2d5a37; flex: 1; }
        .badge { border-radius: 999px; padding: 3px 12px; font-size: .78rem; font-weight: 800; }
        .badge.vide { background: #fdeaea; color: #a12; }
        .badge.ok { background: #e7f6ea; color: #1E7A46; }
        .zones { display: flex; gap: 14px; flex-wrap: wrap; }
        .zone { flex: 1; min-width: 220px; border: 2px dashed #cfe0d4; border-radius: 12px; padding: 14px; background: #f8fbf9; }
        .zone label { display: block; font-weight: 700; margin-bottom: 6px; }
        .zone input[type=file] { width: 100%; font-size: .9rem; }
        .zone small { color: #8a968f; }
        .btn { display: inline-block; border: none; cursor: pointer; background: #2d5a37; color: #fff; font-weight: 700; padding: 11px 22px; border-radius: 999px; text-decoration: none; font-size: .95rem; margin-top: 12px; }
        .btn.ghost { background: #fff; color: #2d5a37; border: 2px solid #2d5a37; }
        .btn-edit { font-size: .85rem; padding: 8px 14px; margin-left: 8px; }
        .note { background: #fff8e1; border: 1px solid #ffe082; color: #6a5400; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; line-height: 1.5; font-size: .9rem; }
        .top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🧪 Espace Beta — ajouter le contenu</h1>
    <p class="sub">Ton espace à toi pour déposer le contenu de la version beta. Pour chaque section : dépose ton <b>PDF (guide)</b> et/ou ta <b>vidéo</b>, puis « Envoyer ». L'IA uniformise et traduit en NL automatiquement. Tu peux ne mettre qu'un des deux.</p>

    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <div class="note">ℹ️ Ces contenus sont <b>réservés au profil beta</b> et n'apparaissent <b>pas</b> sur l'accueil admin normal. Le traitement de la vidéo (compression, sous-titres) se fait en arrière-plan après l'envoi.</div>

    <div class="top">
        <a href="beta.php" class="btn ghost">👁 Voir l'accueil beta</a>
        <a href="index.php" class="btn ghost">← Retour</a>
    </div>

    <?php $bank = betaQuizBank(); ?>
    <?php foreach ($sections as $s): $sec = betaSection($db, $s['nom'], $s['icon']);
        // 🧪 Installe/rafraîchit le quiz beta (Onboarding, Formation Caisse) sur le
        // module — jouable en beta, non noté. Idempotent : on écrase à chaque visite
        // pour toujours avoir la dernière version de la banque.
        $quizNb = 0;
        if (isset($bank[$s['nom']])) {
            try {
                $db->prepare('UPDATE modules SET quiz_json = ? WHERE id = ?')
                   ->execute([json_encode($bank[$s['nom']], JSON_UNESCAPED_UNICODE), (int) $sec['id']]);
                $quizNb = count($bank[$s['nom']]['questions']);
            } catch (Throwable $e) { /* colonne quiz_json absente en test : sans gravité */ }
        }
    ?>
        <div class="card">
            <div class="card-h">
                <span class="ico"><?= htmlspecialchars($s['icon']) ?></span>
                <h2><?= htmlspecialchars($s['nom']) ?></h2>
                <span class="badge <?= $sec['rempli'] ? 'ok' : 'vide' ?>"><?= $sec['rempli'] ? 'Contenu ajouté ✅' : 'Vide' ?></span>
            </div>
            <?php if ($quizNb > 0): ?>
                <div class="note" style="margin:0 0 12px; background:#eef6ef; border-color:#cfe3d5; color:#1E7A46;">
                    📝 Quiz installé : <b><?= (int) $quizNb ?> questions</b> en banque, tirage <b>10 = 6 PDF + 4 vidéo</b> (jouable en beta, non noté).
                    <a href="quiz.php?id=<?= (int) $sec['id'] ?>" style="color:#1E7A46; font-weight:700;">Tester le quiz →</a>
                </div>
            <?php endif; ?>
            <form method="POST" action="module_save.php" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="content">
                <input type="hidden" name="id" value="<?= (int) $sec['id'] ?>">
                <input type="hidden" name="return" value="gestion-beta.php">
                <!-- 🤖 Déclenche l'IA (extraction PDF + mise en page). PAS de a_evaluer -->
                <!-- => pas de génération de quiz. -->
                <input type="hidden" name="uniformize" value="1">
                <div class="zones">
                    <div class="zone">
                        <label>📄 Guide (PDF)</label>
                        <input type="file" name="pdf_file" accept="application/pdf,.pdf">
                        <small>PDF uniquement · jusqu'à 30 Mo</small>
                    </div>
                    <div class="zone">
                        <label>🎬 Vidéo</label>
                        <input type="file" name="video_file" accept="video/mp4,video/quicktime,.mp4,.mov">
                        <small>MP4 ou MOV · 16:9 conseillé</small>
                    </div>
                </div>
                <button type="submit" class="btn">Envoyer 🚀</button>
                <?php if ($sec['rempli']): ?>
                    <a href="module.php?id=<?= (int) $sec['id'] ?>" class="btn ghost btn-edit">Voir / éditer</a>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
