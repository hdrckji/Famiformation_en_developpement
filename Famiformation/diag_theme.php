<?php
// ============================================================
// diag_theme.php — POURQUOI LE THÈME NE S'AFFICHE PAS.
//
// Un thème ne s'affiche que si TOUTE une chaîne de conditions est vraie. Quand
// l'une lâche, l'écran reste normal et rien n'explique pourquoi : impossible de
// savoir laquelle sans ouvrir la base.
//
// Cette page déroule la chaîne, maillon par maillon, et pointe le premier qui
// casse. Admin uniquement.
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/widget.php';
require_once 'includes/theme.php';

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

$moiId = (int) ($_SESSION['user_id'] ?? 0);

// État brut du compte, lu directement.
$compte = ['welcome_seen' => null, 'welcome_day' => null];
try {
    $st = $db->prepare("SELECT welcome_seen, welcome_day FROM utilisateurs WHERE id = ? LIMIT 1");
    $st->execute([$moiId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r) { $compte = $r; }
} catch (Exception $e) {
    $compte['erreur'] = $e->getMessage();
}

$maitre     = widgetGet($db, 'perso_enabled', '1') === '1';
$themesOn   = widgetGet($db, 'themes_enabled', '1') === '1';
$bienvOn    = widgetGet($db, 'theme_bienvenue_on', '1') === '1';
$animOn     = widgetGet($db, 'anim_enabled', '1') === '1';
$introOn    = widgetGet($db, 'theme_bienvenue_intro', '1') === '1';
$choixPerso = function_exists('famiThemeChoisi') ? famiThemeChoisi($db) : '';
$premiere   = famiPremiereVisite($db);
$actif      = activePageTheme($db);

// La chaîne, dans l'ordre où elle est évaluée par le code.
$chaine = [
    ['Tu n\'es pas en profil « beta »', $role !== 'beta',
     'Le profil beta est volontairement tenu à l\'écart de tous les thèmes.'],
    ['Interrupteur MAÎTRE « Personnalisation »', $maitre,
     'Paramètres → Personnalisation. S\'il est coupé, PLUS AUCUN thème ni animation ne s\'affiche, nulle part.'],
    ['Catégorie « Thèmes » activée', $themesOn,
     'Paramètres → Personnalisation → Thèmes.'],
    ['Thème « Bienvenue » activé', $bienvOn,
     'Paramètres → Personnalisation → événement Bienvenue.'],
    ['Aucun thème personnel forçant « aucun décor »', $choixPerso !== 'aucun',
     'Préférences → Thème. Tu as choisi « Aucun décor », ce qui coupe tout pour toi seul.'],
    ['Tu es reconnu comme « jour de bienvenue »', $premiere,
     'Ton compte a déjà été accueilli. Ajoute ?welcome=preview à l\'adresse pour rejouer, ou remets welcome_seen à 0.'],
];

$premierCasse = null;
foreach ($chaine as $c) { if (!$c[1]) { $premierCasse = $c; break; } }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnostic du thème</title>
    <style>
        *,*::before,*::after{box-sizing:border-box}
        body{font-family:'Open Sans',system-ui,sans-serif;background:#eef4ef;margin:0;padding:24px 16px 60px;color:#244230}
        .wrap{max-width:760px;margin:0 auto}
        h1{color:#2d5a37;font-size:1.5rem;margin:0 0 6px}
        .sub{color:#5a6b60;margin:0 0 18px;line-height:1.55}
        .card{background:#fff;border-radius:16px;padding:20px 22px;margin-bottom:16px;box-shadow:0 6px 20px rgba(14,59,36,.08);border:1px solid #e6efe8}
        .verdict{border-radius:14px;padding:16px 18px;margin-bottom:18px;line-height:1.6;font-size:1rem}
        .verdict.ok{background:#e7f6ea;border:1px solid #b7e0c1;color:#1E7A46}
        .verdict.ko{background:#fdeaea;border:1px solid #f3c2c2;color:#a12}
        .ligne{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid #eef2ef}
        .ligne:last-child{border-bottom:none}
        .etat{flex:none;font-weight:800;width:26px;font-size:1.1rem}
        .txt strong{display:block}
        .txt span{color:#7a8a80;font-size:.88rem;line-height:1.5}
        code{background:#f2f6f3;padding:2px 7px;border-radius:6px;font-size:.88rem}
        .btn{display:inline-block;background:#2d5a37;color:#fff;font-weight:700;padding:11px 22px;border-radius:999px;text-decoration:none;margin-right:8px}
        .btn.ghost{background:#fff;color:#2d5a37;border:2px solid #2d5a37}
        table{width:100%;border-collapse:collapse;font-size:.9rem;margin-top:6px}
        td{padding:7px 6px;border-bottom:1px solid #eef2ef}
        td:first-child{color:#6a7d72}
    </style>
</head>
<body>
<div class="wrap">
    <h1>🔎 Pourquoi le thème ne s'affiche pas</h1>
    <p class="sub">La chaîne est déroulée dans l'ordre où le code l'évalue. Le premier ✗ est la cause.</p>

    <?php if ($premierCasse === null): ?>
        <div class="verdict ok">
            ✅ <strong>Toutes les conditions sont réunies.</strong><br>
            Le thème calculé est : <code><?= e($actif ? (is_array($actif['nom'] ?? null) ? $actif['nom'][0] : ($actif['key'] ?? '?')) : 'aucun') ?></code>.
            <?php if (!$actif): ?><br>Mais <b>aucun thème n'est retourné</b> : c'est alors un vrai bug, préviens-moi.<?php endif; ?>
        </div>
    <?php else: ?>
        <div class="verdict ko">
            ✗ <strong>Ça bloque ici : <?= e($premierCasse[0]) ?></strong><br>
            <?= e($premierCasse[2]) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php foreach ($chaine as $c): ?>
            <div class="ligne">
                <div class="etat" style="color:<?= $c[1] ? '#1E7A46' : '#a12' ?>;"><?= $c[1] ? '✓' : '✗' ?></div>
                <div class="txt"><strong><?= e($c[0]) ?></strong><span><?= e($c[2]) ?></span></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2 style="margin:0 0 10px;font-size:1.05rem;color:#2d5a37;">État brut</h2>
        <table>
            <tr><td>Ton profil</td><td><code><?= e($role) ?></code></td></tr>
            <tr><td>welcome_seen</td><td><code><?= e(var_export($compte['welcome_seen'], true)) ?></code></td></tr>
            <tr><td>welcome_day</td><td><code><?= e(var_export($compte['welcome_day'], true)) ?></code> (aujourd'hui : <?= date('Y-m-d') ?>)</td></tr>
            <tr><td>Thème choisi (préférences)</td><td><code><?= e($choixPerso === '' ? 'automatique' : $choixPerso) ?></code></td></tr>
            <tr><td>Animation de bienvenue</td><td><code><?= ($animOn && $introOn) ? 'activée' : 'coupée' ?></code></td></tr>
            <tr><td>Thème calculé maintenant</td><td><code><?= e($actif ? ($actif['key'] ?? '?') : 'aucun') ?></code></td></tr>
            <?php if (!empty($compte['erreur'])): ?>
            <tr><td>Erreur base</td><td><code><?= e($compte['erreur']) ?></code></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <a href="index.php?welcome=preview" class="btn">👁 Voir l'aperçu de bienvenue</a>
    <a href="index.php" class="btn ghost">← Retour</a>
</div>
</body>
</html>
