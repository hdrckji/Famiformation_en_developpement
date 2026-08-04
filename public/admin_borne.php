<?php
// ============================================================
// Statistiques d'usage de la borne.
//
// Le quiz enregistre deux moments dans quiz_borne_events : une inscription et
// une participation, avec le magasin et l'écran d'où ils viennent (borne, télé,
// code, téléphone). Cette page les restitue.
//
// La borne est comparée aux autres écrans à dessein : « 40 inscriptions borne »
// ne veut rien dire sans savoir combien sont venues du téléphone le même jour.
// ============================================================

require_once 'config.php';

// Même contrôle que les autres pages d'administration du site.
if (!isset($_SESSION['user_id']) || !in_array((string) ($_SESSION['role'] ?? ''), ['admin'], true)) {
    header('Location: login.php');
    exit();
}

$SITES = ['mouscron' => 'Famiflora Mouscron', 'lapanne' => 'Famiflora La Panne'];
$ECRANS = ['borne' => 'Borne', 'tele' => 'Télé', 'code' => 'Code bonus', 'user' => 'Téléphone'];

// La table est créée par le quiz au premier événement. On la crée aussi ici
// pour que la page s'affiche normalement avant la première participation.
try {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS quiz_borne_events (
           id INT AUTO_INCREMENT PRIMARY KEY,
           created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
           site VARCHAR(20) NOT NULL,
           ecran VARCHAR(10) NOT NULL,
           type VARCHAR(20) NOT NULL,
           joueur VARCHAR(60) NULL,
           score DECIMAL(6,1) NULL,
           INDEX idx_borne_date (created_at),
           INDEX idx_borne_site (site, ecran)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
} catch (Exception $e) {
}

$message = '';

// --- Suppression d'une ligne (test, fausse manip) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer'])) {
    requireValidCSRF();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $db->prepare('DELETE FROM quiz_borne_events WHERE id = ?')->execute([$id]);
            $message = 'Ligne supprimée.';
        } catch (Exception $e) {
            $message = 'Suppression impossible.';
        }
    }
    header('Location: admin_borne.php?' . http_build_query([
        'site' => $_POST['site'] ?? 'all',
        'jours' => $_POST['jours'] ?? '30',
        'm' => $message,
    ]));
    exit();
}

$message = trim((string) ($_GET['m'] ?? ''));
$siteFiltre = (string) ($_GET['site'] ?? 'all');
if (!isset($SITES[$siteFiltre])) {
    $siteFiltre = 'all';
}
$jours = (int) ($_GET['jours'] ?? 30);
if (!in_array($jours, [7, 30, 90, 0], true)) {
    $jours = 30;
}

// $jours = 0 signifie « depuis le début » : pas de borne basse.
$conditions = [];
$params = [];
if ($jours > 0) {
    $conditions[] = 'created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $params[] = $jours;
}
if ($siteFiltre !== 'all') {
    $conditions[] = 'site = ?';
    $params[] = $siteFiltre;
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

function bornRequete(PDO $db, $sql, array $params)
{
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Répartition site × écran × type — le cœur de la page.
$repartition = bornRequete(
    $db,
    "SELECT site, ecran, type, COUNT(*) n FROM quiz_borne_events$where GROUP BY site, ecran, type",
    $params
);
$grille = [];
foreach ($repartition as $r) {
    $grille[$r['site']][$r['ecran']][$r['type']] = (int) $r['n'];
}

// Activité par jour, pour voir les pics.
$parJour = bornRequete(
    $db,
    "SELECT DATE(created_at) j, ecran, COUNT(*) n FROM quiz_borne_events$where GROUP BY j, ecran ORDER BY j DESC LIMIT 60",
    $params
);
$jours_ = [];
foreach ($parJour as $r) {
    $jours_[$r['j']][$r['ecran']] = (int) $r['n'];
}

$detail = bornRequete(
    $db,
    "SELECT id, created_at, site, ecran, type, joueur, score FROM quiz_borne_events$where ORDER BY created_at DESC, id DESC LIMIT 300",
    $params
);

// Export CSV — point-virgule et BOM, les deux attendus par Excel en français.
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="borne-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Magasin', 'Écran', 'Type', 'Joueur', 'Score'], ';');
    foreach ($detail as $l) {
        fputcsv($out, [
            date('d/m/Y H:i', strtotime((string) $l['created_at'])),
            $SITES[$l['site']] ?? $l['site'],
            $ECRANS[$l['ecran']] ?? $l['ecran'],
            $l['type'],
            $l['joueur'],
            $l['score'],
        ], ';');
    }
    fclose($out);
    exit();
}

function totalEcran(array $grille, $site, $ecran, $type)
{
    if ($site !== 'all') {
        return (int) ($grille[$site][$ecran][$type] ?? 0);
    }
    $n = 0;
    foreach ($grille as $parEcran) {
        $n += (int) ($parEcran[$ecran][$type] ?? 0);
    }
    return $n;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statistiques borne</title>
<link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--green:#2e7d46;--deep:#1f5c34;--mint:#eef6ec;--mint-line:#d7e8d2;--ink:#243027;--muted:#5c6f60;--red:#a8341f;}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif;}
body{background:#eef4ea;color:var(--ink);padding:18px 14px 46px;}
.wrap{max-width:1060px;margin:0 auto;}
.top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px;}
h1{color:var(--deep);font-weight:900;font-size:23px;}
.btn{display:inline-block;padding:9px 15px;border-radius:999px;border:0;background:#fff;color:var(--deep);
     font-weight:700;font-size:13.5px;text-decoration:none;box-shadow:0 3px 10px rgba(0,0,0,.09);cursor:pointer;}
.btn.primary{background:var(--green);color:#fff;}
.filtres{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
.filtres a{padding:8px 14px;border-radius:999px;background:#fff;color:var(--muted);text-decoration:none;
           font-weight:700;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.06);}
.filtres a.on{background:var(--deep);color:#fff;}
.flash{background:var(--mint);border:1px solid var(--mint-line);color:var(--deep);padding:11px 14px;
       border-radius:12px;margin-bottom:14px;font-size:14px;font-weight:600;}
.cartes{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:18px;}
.carte{background:#fff;border-radius:18px;padding:16px 18px;box-shadow:0 5px 18px rgba(20,55,38,.08);}
.carte .t{font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;}
.carte .n{font-size:30px;font-weight:900;color:var(--deep);line-height:1.15;margin-top:6px;}
.carte .d{font-size:12.5px;color:var(--muted);margin-top:3px;}
.carte.fort{background:linear-gradient(150deg,#3f9d57,#1f5c34);}
.carte.fort .t,.carte.fort .d{color:#d7f0dd;} .carte.fort .n{color:#fff;}
.bloc{background:#fff;border-radius:18px;box-shadow:0 6px 22px rgba(20,55,38,.09);margin-bottom:18px;overflow-x:auto;}
.bloc h2{font-size:15px;color:var(--deep);padding:16px 18px 0;font-weight:800;}
table{width:100%;border-collapse:collapse;min-width:640px;}
th,td{padding:11px 14px;text-align:left;font-size:13.5px;border-bottom:1px solid #eef2ee;}
th{background:var(--mint);color:var(--deep);font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;}
tr:last-child td{border-bottom:0;}
td.num{font-weight:800;color:var(--deep);}
td.date{color:var(--muted);font-size:12.5px;white-space:nowrap;}
.pill{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:800;}
.pill.borne{background:#e3f2e7;color:var(--deep);}
.pill.tele{background:#fff3d6;color:#8a5f2a;}
.pill.code{background:#e8eef7;color:#2f4f7a;}
.pill.user{background:#f2f0ee;color:var(--muted);}
.del{border:0;background:transparent;color:#c7cdc8;cursor:pointer;font-size:15px;padding:2px 6px;border-radius:6px;}
.del:hover{color:var(--red);background:#fdecea;}
.vide{padding:38px 20px;text-align:center;color:var(--muted);font-size:14px;line-height:1.6;}
.vide code{background:var(--mint);padding:2px 7px;border-radius:6px;font-size:12.5px;}
.barre{height:8px;border-radius:999px;background:var(--mint-line);overflow:hidden;min-width:90px;}
.barre i{display:block;height:100%;background:var(--green);}
</style>
</head>
<body>
<div class="wrap">

  <div class="top">
    <h1>📊 Statistiques de la borne</h1>
    <div>
      <a class="btn primary" href="?<?= http_build_query(['site' => $siteFiltre, 'jours' => $jours, 'csv' => 1]) ?>">Exporter (CSV)</a>
      <a class="btn" href="admin.php">Retour à l'administration</a>
    </div>
  </div>

  <?php if ($message !== ''): ?><div class="flash"><?= e($message) ?></div><?php endif; ?>

  <div class="filtres">
    <?php foreach (['all' => 'Les deux magasins'] + $SITES as $cle => $lib): ?>
      <a class="<?= $siteFiltre === $cle ? 'on' : '' ?>"
         href="?<?= http_build_query(['site' => $cle, 'jours' => $jours]) ?>"><?= e($lib) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="filtres">
    <?php foreach ([7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 0 => 'Depuis le début'] as $j => $lib): ?>
      <a class="<?= $jours === $j ? 'on' : '' ?>"
         href="?<?= http_build_query(['site' => $siteFiltre, 'jours' => $j]) ?>"><?= e($lib) ?></a>
    <?php endforeach; ?>
  </div>

  <?php
  $bInsc = totalEcran($grille, $siteFiltre, 'borne', 'inscription');
  $bPart = totalEcran($grille, $siteFiltre, 'borne', 'participation');
  $tousInsc = 0; $tousPart = 0;
  foreach (array_keys($ECRANS) as $ec) {
      $tousInsc += totalEcran($grille, $siteFiltre, $ec, 'inscription');
      $tousPart += totalEcran($grille, $siteFiltre, $ec, 'participation');
  }
  $partBorne = ($tousInsc + $tousPart) > 0
      ? round((($bInsc + $bPart) / ($tousInsc + $tousPart)) * 100) : 0;
  ?>
  <div class="cartes">
    <div class="carte fort"><div class="t">Borne — inscriptions</div><div class="n"><?= (int) $bInsc ?></div><div class="d">sur <?= (int) $tousInsc ?> au total</div></div>
    <div class="carte fort"><div class="t">Borne — participations</div><div class="n"><?= (int) $bPart ?></div><div class="d">sur <?= (int) $tousPart ?> au total</div></div>
    <div class="carte"><div class="t">Part de la borne</div><div class="n"><?= (int) $partBorne ?> %</div><div class="d">de toute l'activité</div></div>
    <div class="carte"><div class="t">Total enregistré</div><div class="n"><?= (int) ($tousInsc + $tousPart) ?></div><div class="d">sur la période</div></div>
  </div>

  <div class="bloc">
    <h2>Répartition par écran</h2>
    <table>
      <thead><tr><th>Écran</th><th>Inscriptions</th><th>Participations</th><th>Total</th><th style="width:34%">Poids</th></tr></thead>
      <tbody>
      <?php
      $grandTotal = $tousInsc + $tousPart;
      foreach ($ECRANS as $cle => $lib):
          $i = totalEcran($grille, $siteFiltre, $cle, 'inscription');
          $p = totalEcran($grille, $siteFiltre, $cle, 'participation');
          $t = $i + $p;
          $pct = $grandTotal > 0 ? round(($t / $grandTotal) * 100) : 0;
      ?>
        <tr>
          <td><span class="pill <?= e($cle) ?>"><?= e($lib) ?></span></td>
          <td class="num"><?= (int) $i ?></td>
          <td class="num"><?= (int) $p ?></td>
          <td class="num"><?= (int) $t ?></td>
          <td><div class="barre"><i style="width:<?= (int) $pct ?>%"></i></div><span style="font-size:11.5px;color:var(--muted);"><?= (int) $pct ?> %</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($jours_)): ?>
  <div class="bloc">
    <h2>Activité par jour</h2>
    <table>
      <thead><tr><th>Jour</th><?php foreach ($ECRANS as $lib): ?><th><?= e($lib) ?></th><?php endforeach; ?><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($jours_ as $j => $parEcran): $tj = array_sum($parEcran); ?>
        <tr>
          <td class="date"><?= e(date('d/m/Y', strtotime($j))) ?></td>
          <?php foreach (array_keys($ECRANS) as $cle): ?>
            <td class="num"><?= (int) ($parEcran[$cle] ?? 0) ?></td>
          <?php endforeach; ?>
          <td class="num"><?= (int) $tj ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="bloc">
    <h2>Détail (300 dernières lignes)</h2>
    <?php if (empty($detail)): ?>
      <div class="vide">
        Aucun événement sur cette période.<br>
        Les inscriptions et participations faites depuis <code>/quiz/mouscron/borne</code>
        ou <code>/quiz/lapanne/borne</code> apparaîtront ici.
      </div>
    <?php else: ?>
      <table>
        <thead><tr><th>Date</th><th>Magasin</th><th>Écran</th><th>Type</th><th>Joueur</th><th>Score</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($detail as $l): ?>
          <tr>
            <td class="date"><?= e(date('d/m/Y H:i', strtotime((string) $l['created_at']))) ?></td>
            <td><?= e($SITES[$l['site']] ?? $l['site']) ?></td>
            <td><span class="pill <?= e($l['ecran']) ?>"><?= e($ECRANS[$l['ecran']] ?? $l['ecran']) ?></span></td>
            <td><?= $l['type'] === 'inscription' ? '✍️ Inscription' : '🎮 Participation' ?></td>
            <td><?= e((string) $l['joueur']) ?></td>
            <td class="num"><?= $l['score'] !== null ? e((string) (float) $l['score']) : '' ?></td>
            <td>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Supprimer cette ligne ? Les totaux seront recalculés.');">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                <input type="hidden" name="site" value="<?= e($siteFiltre) ?>">
                <input type="hidden" name="jours" value="<?= (int) $jours ?>">
                <button class="del" type="submit" name="supprimer" value="1" title="Supprimer">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
