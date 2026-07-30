<?php
// ============================================================
// diag.php — ÉTAT DES DONNÉES DU QUIZ.
//
// Quand « ça ne marche plus » sans message d'erreur, il est impossible de savoir
// si le problème vient des fichiers, des droits d'écriture, du volume ou du code.
// Cette page le dit, pour chaque fichier et pour chaque magasin.
//
// Accès : ?pwd=<mot de passe admin du quiz>. À supprimer quand tout va bien.
// ============================================================
header('Content-Type: text/html; charset=UTF-8');

$ADMIN_PWD = 'a';   // même valeur que dans api.php
if ((string) ($_GET['pwd'] ?? '') !== $ADMIN_PWD) {
    http_response_code(403);
    exit('Acces refuse. Ajoute ?pwd=... a l\'adresse.');
}

// Même détection de dossier que api.php : volume Railway, sinon quiz/data.
$vol = getenv('RAILWAY_VOLUME_MOUNT_PATH') ?: ($_SERVER['RAILWAY_VOLUME_MOUNT_PATH'] ?? '');
$dataDir = ($vol && @is_dir($vol)) ? rtrim($vol, "/\\") . '/quiz' : __DIR__ . '/data';

$sites = ['mouscron', 'lapanne'];
$modeles = ['scores', 'codes', 'questions', 'jardin', 'rh'];

function etat($f)
{
    if (!is_file($f)) { return ['-', 'absent', '']; }
    $c = @file_get_contents($f);
    if ($c === false) { return ['✗', 'ILLISIBLE', '']; }
    $t = trim($c);
    if ($t === '') { return ['✗', 'VIDE', '0 o']; }
    $d = json_decode($t, true);
    if (!is_array($d)) { return ['✗', 'JSON INVALIDE', strlen($c) . ' o']; }
    return ['✓', count($d) . ' entrée(s)', strlen($c) . ' o'];
}
?>
<style>
body{font-family:system-ui,sans-serif;background:#eef4ef;color:#244230;padding:22px;line-height:1.5}
h1{color:#2d5a37;font-size:1.3rem;margin:0 0 4px}h2{color:#2d5a37;font-size:1.05rem;margin:22px 0 8px}
table{border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;font-size:.9rem;margin-bottom:6px}
td,th{padding:7px 12px;border-bottom:1px solid #eef2ef;text-align:left}
th{background:#f6faf7;font-size:.78rem;text-transform:uppercase;color:#6a7d72}
.ok{color:#1E7A46;font-weight:800}.ko{color:#a12;font-weight:800}
code{background:#f2f6f3;padding:2px 6px;border-radius:5px;font-size:.86rem}
.enc{background:#fff;border-radius:12px;padding:14px 18px;margin-bottom:14px;border:1px solid #e6efe8}
</style>

<h1>🔎 État des données du quiz</h1>

<div class="enc">
  <b>Volume détecté :</b> <?= $vol ? '<span class="ok">oui</span> — <code>' . htmlspecialchars($vol) . '</code>'
      : '<span class="ko">NON</span> — on écrit dans quiz/data, effacé à chaque déploiement !' ?><br>
  <b>Dossier de données :</b> <code><?= htmlspecialchars($dataDir) ?></code><br>
  <b>Existe :</b> <?= is_dir($dataDir) ? '<span class="ok">oui</span>' : '<span class="ko">NON</span>' ?> ·
  <b>Accessible en écriture :</b> <?= is_writable($dataDir) ? '<span class="ok">oui</span>' : '<span class="ko">NON — c\'est la cause</span>' ?>
</div>

<?php foreach ($sites as $site): ?>
  <h2>🏬 <?= htmlspecialchars($site) ?></h2>
  <table>
    <tr><th></th><th>Fichier</th><th>Contenu</th><th>Taille</th><th>Sauvegarde</th><th>.tmp orphelin</th><th>.lock</th></tr>
    <?php foreach ($modeles as $m):
        $f = $dataDir . "/$m-$site.json";
        list($ico, $txt, $taille) = etat($f);
        list($ib, $tb) = etat($f . '.bak');
    ?>
    <tr>
      <td class="<?= $ico === '✓' ? 'ok' : ($ico === '-' ? '' : 'ko') ?>"><?= $ico ?></td>
      <td><code><?= htmlspecialchars($m . '-' . $site . '.json') ?></code></td>
      <td class="<?= $ico === '✓' ? '' : 'ko' ?>"><?= htmlspecialchars($txt) ?></td>
      <td><?= htmlspecialchars($taille) ?></td>
      <td><?= $ib === '✓' ? '<span class="ok">✓</span> ' . htmlspecialchars($tb) : '<span style="opacity:.5">aucune</span>' ?></td>
      <td><?= is_file($f . '.tmp') ? '<span class="ko">OUI — écriture interrompue</span>' : '<span style="opacity:.5">non</span>' ?></td>
      <td><?= is_file($f . '.lock') ? '<span class="ok">✓</span>' : '<span style="opacity:.5">non créé</span>' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
<?php endforeach; ?>

<h2>✍️ Test d'écriture réel</h2>
<div class="enc">
<?php
$essai = $dataDir . '/diag-essai.json';
$etapes = [];
$etapes[] = ['créer un fichier', (bool) @file_put_contents($essai, '{"test":1}')];
$etapes[] = ['le relire', is_file($essai) && json_decode((string) @file_get_contents($essai), true) !== null];
$etapes[] = ['créer un .tmp', (bool) @file_put_contents($essai . '.tmp', 'x')];
$etapes[] = ['renommer (écriture atomique)', @rename($essai . '.tmp', $essai . '.tmp2')];
$verrou = @fopen($essai . '.lock', 'c');
$etapes[] = ['créer un fichier de verrou', (bool) $verrou];
if ($verrou) { @flock($verrou, LOCK_EX); @flock($verrou, LOCK_UN); @fclose($verrou); }
foreach ([$essai, $essai . '.tmp', $essai . '.tmp2', $essai . '.lock'] as $x) { @unlink($x); }
foreach ($etapes as $e) {
    echo ($e[1] ? '<span class="ok">✓</span> ' : '<span class="ko">✗ ÉCHEC</span> ') . htmlspecialchars($e[0]) . '<br>';
}
$tout = array_reduce($etapes, function ($c, $e) { return $c && $e[1]; }, true);
echo '<br><b>' . ($tout
    ? '<span class="ok">Tout fonctionne : le problème n\'est pas dans les fichiers.</span>'
    : '<span class="ko">Une étape échoue — c\'est là qu\'il faut chercher.</span>') . '</b>';
?>
</div>

<p style="font-size:.85rem;color:#6a7d72;">PHP <?= PHP_VERSION ?> · fsync <?= function_exists('fsync') ? 'disponible' : 'ABSENT' ?> · <?= date('d/m/Y H:i') ?></p>
