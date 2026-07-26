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
if (function_exists('ensureModulesTable')) { try { ensureModulesTable($db); } catch (Throwable $e) {} }

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

// 🧹 NETTOYAGE : on retire les sous-modules PARASITES (vides, sans aucun contenu)
// sous les sections beta — ceux créés par erreur avant l'upload réel. On garde
// UNIQUEMENT les sous-modules qui ont vraiment du contenu (Guide / Vidéo).
try {
    $db->exec(
        "DELETE c FROM modules c
         JOIN modules p ON c.parent_id = p.id
         WHERE p.roles = 'beta' AND p.parent_id IS NULL
           AND (c.pdf_path IS NULL OR c.pdf_path = '')
           AND (c.video_path IS NULL OR c.video_path = '')
           AND (c.contenu_ia IS NULL OR c.contenu_ia = '')"
    );
} catch (Throwable $e) { /* multi-delete non supporté : sans gravité */ }

// Les sections de la beta (modules conteneurs roles=beta, non évalués).
$sections = [
    ['nom' => 'Onboarding',                        'icon' => '🚀'],
    ['nom' => 'Formation Caisse',                  'icon' => '💳'],
    ['nom' => 'Module technique',                  'icon' => '🔧'],
    ['nom' => 'Mes 2 premières semaines en caisse', 'icon' => '🗓️'],
];

function betaSection(PDO $db, $nom, $icon)
{
    $st = $db->prepare("SELECT id FROM modules WHERE nom = ? AND roles = 'beta' AND parent_id IS NULL LIMIT 1");
    $st->execute([$nom]);
    $id = $st->fetchColumn();
    if ($id === false) {
        $ins = $db->prepare("INSERT INTO modules (nom, description, is_container, parent_id, icon, roles, sort_order) VALUES (?, '', 1, NULL, ?, 'beta', 0)");
        $ins->execute([$nom, $icon]);
        $id = (int) $db->lastInsertId();
    }
    $rempli = false;
    try {
        $c = $db->prepare("SELECT COUNT(*) FROM modules WHERE parent_id = ? AND (pdf_path IS NOT NULL OR video_path IS NOT NULL OR contenu_ia IS NOT NULL)");
        $c->execute([(int) $id]);
        $rempli = ((int) $c->fetchColumn()) > 0;
    } catch (Throwable $e) {}
    return ['id' => (int) $id, 'rempli' => $rempli];
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

    <?php foreach ($sections as $s): $sec = betaSection($db, $s['nom'], $s['icon']); ?>
        <div class="card">
            <div class="card-h">
                <span class="ico"><?= htmlspecialchars($s['icon']) ?></span>
                <h2><?= htmlspecialchars($s['nom']) ?></h2>
                <span class="badge <?= $sec['rempli'] ? 'ok' : 'vide' ?>"><?= $sec['rempli'] ? 'Contenu ajouté ✅' : 'Vide' ?></span>
            </div>
            <form method="POST" action="module_save.php" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="content">
                <input type="hidden" name="id" value="<?= (int) $sec['id'] ?>">
                <input type="hidden" name="return" value="gestion-beta.php">
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
