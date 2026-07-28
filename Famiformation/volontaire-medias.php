<?php
// ============================================================
// volontaire-medias.php — ESPACE ADMIN pour déposer le PDF + la vidéo d'exemple
// du mini-site /volontaire DANS LE VOLUME Railway persistant. Ainsi ces gros
// fichiers (surtout la vidéo) ne sont plus dans le dépôt/l'image Docker, et tu
// peux les remplacer quand tu veux sans redéployer. Le site public les lit via
// volontaire/fichier.php (volume prioritaire, repli sur l'exemple du dépôt).
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/csrf.php';

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'admin') { header('Location: index.php'); exit(); }

$base = defined('FAMI_STORAGE_BASE') ? FAMI_STORAGE_BASE : (__DIR__ . '/uploads');
$dir  = $base . '/volontaire';
$cibles = [
    'pdf'   => ['nom' => 'exemple-pdf.pdf',   'label' => '📄 PDF d\'exemple',   'accept' => 'application/pdf,.pdf',            'exts' => ['pdf'],        'max' => 30 * 1024 * 1024],
    'video' => ['nom' => 'exemple-video.mp4', 'label' => '🎬 Vidéo d\'exemple', 'accept' => 'video/mp4,.mp4',                 'exts' => ['mp4'],        'max' => 200 * 1024 * 1024],
];

$flash = ''; $erreur = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    requireValidCSRF();
    $quoi = (string) ($_POST['quoi'] ?? '');
    if (!isset($cibles[$quoi])) {
        $erreur = 'Type de fichier inconnu.';
    } elseif (!isset($_FILES['fichier']) || ($_FILES['fichier']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = $_FILES['fichier']['error'] ?? UPLOAD_ERR_NO_FILE;
        $erreur = ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE)
            ? 'Fichier trop volumineux pour le serveur (limite PHP). Compresse-le ou augmente la limite.'
            : 'Aucun fichier reçu (erreur ' . (int) $code . ').';
    } else {
        $c = $cibles[$quoi];
        $tmp = $_FILES['fichier']['tmp_name'];
        $ext = strtolower(pathinfo((string) $_FILES['fichier']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $c['exts'], true)) {
            $erreur = 'Extension non autorisée (attendu : ' . implode(', ', $c['exts']) . ').';
        } elseif (filesize($tmp) > $c['max']) {
            $erreur = 'Fichier trop lourd (max ' . round($c['max'] / 1048576) . ' Mo).';
        } elseif (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $erreur = 'Impossible de créer le dossier sur le volume.';
        } else {
            $dest = $dir . '/' . $c['nom'];
            if (@move_uploaded_file($tmp, $dest)) {
                @chmod($dest, 0664);
                $flash = $c['label'] . ' mis à jour sur le volume ✅';
            } else {
                $erreur = 'Échec de l\'écriture sur le volume.';
            }
        }
    }
}

function volInfo($dir, $nom)
{
    $p = $dir . '/' . $nom;
    if (is_file($p)) { return ['ok' => true, 'taille' => round(filesize($p) / 1048576, 1)]; }
    return ['ok' => false, 'taille' => 0];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Médias volontaire — FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: #eef4ef; margin: 0; padding: 24px 16px 60px; color: #244230; }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { color: #2d5a37; font-size: 1.5rem; margin: 0 0 4px; }
        .sub { color: #5a6b60; margin: 0 0 18px; line-height: 1.5; }
        .flash { background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 600; }
        .err { background: #fdeaea; border: 1px solid #f2b8b8; color: #a12; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 600; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; margin-bottom: 16px; box-shadow: 0 6px 20px rgba(14,59,36,.08); border: 1px solid #e6efe8; }
        .card-h { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .card-h h2 { margin: 0; font-size: 1.15rem; color: #2d5a37; flex: 1; }
        .badge { border-radius: 999px; padding: 3px 12px; font-size: .78rem; font-weight: 800; }
        .badge.ok { background: #e7f6ea; color: #1E7A46; }
        .badge.no { background: #fff4d6; color: #7a5a11; }
        .zone { border: 2px dashed #cfe0d4; border-radius: 12px; padding: 14px; background: #f8fbf9; }
        .zone input[type=file] { width: 100%; font-size: .9rem; }
        .zone small { color: #8a968f; display: block; margin-top: 6px; }
        .btn { display: inline-block; border: none; cursor: pointer; background: #2d5a37; color: #fff; font-weight: 700; padding: 11px 22px; border-radius: 999px; text-decoration: none; font-size: .95rem; margin-top: 12px; }
        .btn.ghost { background: #fff; color: #2d5a37; border: 2px solid #2d5a37; }
        .note { background: #fff8e1; border: 1px solid #ffe082; color: #6a5400; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; line-height: 1.5; font-size: .9rem; }
        .top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🎥 Médias du site « Volontaire »</h1>
    <p class="sub">Dépose ici le <b>PDF</b> et la <b>vidéo</b> d'exemple du site de recrutement. Ils sont stockés sur le <b>volume Railway</b> (persistants, remplaçables sans redéployer). Le site public les affiche automatiquement.</p>

    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="err"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <div class="note">ℹ️ Tant que tu n'as rien déposé, le site montre les <b>exemples par défaut</b> du dépôt. Dès que tu déposes ici, <b>ta version sur le volume prend le relais</b>. Vidéo conseillée : <b>format paysage 16:9</b>, compressée (MP4).</div>

    <div class="top">
        <a href="volontaire/" class="btn ghost" target="_blank">👁 Voir le site volontaire</a>
        <a href="index.php" class="btn ghost">← Retour</a>
    </div>

    <?php foreach ($cibles as $cle => $c): $info = volInfo($dir, $c['nom']); ?>
        <div class="card">
            <div class="card-h">
                <h2><?= htmlspecialchars($c['label']) ?></h2>
                <span class="badge <?= $info['ok'] ? 'ok' : 'no' ?>"><?= $info['ok'] ? ('Sur le volume ✅ (' . $info['taille'] . ' Mo)') : 'Exemple par défaut' ?></span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="quoi" value="<?= htmlspecialchars($cle) ?>">
                <div class="zone">
                    <input type="file" name="fichier" accept="<?= htmlspecialchars($c['accept']) ?>" required>
                    <small><?= strtoupper(implode(', ', $c['exts'])) ?> · jusqu'à <?= round($c['max'] / 1048576) ?> Mo</small>
                </div>
                <button type="submit" class="btn">Déposer sur le volume 🚀</button>
                <?php if ($info['ok']): ?>
                    <a href="volontaire/fichier.php?f=<?= htmlspecialchars($cle) ?>" class="btn ghost" target="_blank">Aperçu</a>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
