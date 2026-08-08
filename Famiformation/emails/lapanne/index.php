<?php
// ============================================================
// La Panne — page RH : la liste des adresses collectées.
//
// Reprend les colonnes de l'export Excel (Horodateur, Nom, Prénom, Adresse
// e-mail, Ticket remis) et permet de cocher les tickets remis au fur et à
// mesure. Un export CSV est fourni pour continuer à travailler dans Excel.
//
// Accès par mot de passe dédié, indépendant des comptes du site : la page est
// utilisable par le personnel de La Panne sans compte Famiformation.
// ============================================================

require_once __DIR__ . '/_lapanne.php';

// --- Connexion / déconnexion -------------------------------------------------
$erreurConnexion = '';

if (isset($_GET['deconnexion'])) {
    lapanneRhDeconnecter();
    header('Location: index.php');
    exit();
}

if (!lapanneAccesRh() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connexion'])) {
    requireValidCSRF();
    if (lapanneRhVerifier($_POST['motdepasse'] ?? '')) {
        lapanneRhConnecter();
        header('Location: index.php');
        exit();
    }
    lapanneRhFreiner();
    $erreurConnexion = 'Mot de passe incorrect.';
}

if (!lapanneAccesRh()) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La Panne — accès RH</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../../favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
    :root{--green:#2e7d46;--deep:#1f5c34;--mint:#eef6ec;--mint-line:#d7e8d2;--ink:#243027;--muted:#5c6f60;--red:#a8341f;}
    *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif;}
    body{background:#eef4ea;color:var(--ink);min-height:100vh;min-height:100dvh;margin:0;}
    .wrap{display:flex;justify-content:center;min-height:100vh;min-height:100dvh;padding:18px;}
    .card{background:#fff;border-radius:22px;box-shadow:0 14px 44px rgba(20,55,38,.16);
          padding:30px 26px;max-width:400px;width:100%;margin:auto;}
    h1{color:var(--deep);font-weight:900;font-size:21px;text-align:center;}
    p.sub{color:var(--muted);font-size:13.5px;text-align:center;margin-top:7px;line-height:1.5;}
    label{display:block;margin-top:20px;font-weight:700;font-size:13.5px;color:var(--deep);}
    input{width:100%;margin-top:6px;padding:12px 13px;border:1px solid var(--mint-line);
          border-radius:12px;font-size:16px;background:var(--mint);}
    input:focus{outline:2px solid var(--green);outline-offset:1px;background:#fff;}
    button{width:100%;margin-top:20px;padding:13px;border:0;border-radius:12px;background:var(--green);
           color:#fff;font-size:15.5px;font-weight:800;cursor:pointer;}
    button:hover{background:var(--deep);}
    .err{margin-top:16px;background:#fdecea;border:1px solid #f6cdc7;color:var(--red);
         padding:11px 13px;border-radius:12px;font-size:14px;font-weight:600;text-align:center;}
    </style>
    </head>
    <body>
    <div class="wrap">
        <div class="card">
            <h1>🔒 La Panne — accès RH</h1>
            <p class="sub">Liste des adresses collectées et suivi des tickets remis.</p>
            <?php if ($erreurConnexion !== ''): ?>
                <div class="err"><?= e($erreurConnexion) ?></div>
            <?php endif; ?>
            <form method="POST">
                <?= csrfField() ?>
                <label for="motdepasse">Mot de passe</label>
                <input id="motdepasse" name="motdepasse" type="password" required autofocus
                       autocomplete="current-password">
                <button type="submit" name="connexion" value="1">Entrer</button>
            </form>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0 && isset($_POST['basculer_ticket'])) {
        lapanneMarquerTicket($db, $id, (string) ($_POST['remis'] ?? '0') === '1');
    } elseif ($id > 0 && isset($_POST['supprimer'])) {
        lapanneSupprimer($db, $id);
        $message = 'Inscription supprimée.';
    }

    header('Location: index.php' . ($message !== '' ? '?m=' . urlencode($message) : ''));
    exit();
}

$message = trim((string) ($_GET['m'] ?? ''));
$inscriptions = lapanneListe($db);

// Export CSV — séparateur « ; » et BOM UTF-8, les deux attendus par Excel en
// version française. Sans le BOM, Excel affiche « Ã© » à la place des accents.
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lapanne-emails-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $sortie = fopen('php://output', 'w');
    fputcsv($sortie, ['Horodateur', 'Nom / Naam', 'Prénom / Voornaam', 'Adresse e-mail', 'Ticket remis'], ';');
    foreach ($inscriptions as $ligne) {
        fputcsv($sortie, [
            date('d/m/Y H:i:s', strtotime((string) $ligne['created_at'])),
            $ligne['nom'],
            $ligne['prenom'],
            $ligne['email'],
            ((int) $ligne['ticket_remis']) === 1 ? 'Remis' : 'Non remis',
        ], ';');
    }
    fclose($sortie);
    exit();
}

$total = count($inscriptions);
$remis = 0;
foreach ($inscriptions as $ligne) {
    if ((int) $ligne['ticket_remis'] === 1) {
        $remis++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>La Panne — adresses collectées</title>
<link rel="shortcut icon" type="image/x-icon" href="../../../favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--green:#2e7d46;--deep:#1f5c34;--leaf:#7cb342;--mint:#eef6ec;--mint-line:#d7e8d2;--ink:#243027;--muted:#5c6f60;--red:#a8341f;}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',system-ui,sans-serif;}
body{background:#eef4ea;color:var(--ink);min-height:100vh;min-height:100dvh;padding:18px 14px 46px;}
.wrap{max-width:1000px;margin:0 auto;}
.top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px;}
h1{color:var(--deep);font-weight:900;font-size:23px;}
.actions{display:flex;gap:8px;flex-wrap:wrap;}
.btn{display:inline-block;padding:9px 15px;border-radius:999px;border:0;background:#fff;color:var(--deep);
     font-weight:700;font-size:13.5px;text-decoration:none;box-shadow:0 3px 10px rgba(0,0,0,.09);cursor:pointer;}
.btn.primary{background:var(--green);color:#fff;}
.stats{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;}
.stat{background:#fff;border-radius:16px;padding:13px 18px;box-shadow:0 4px 14px rgba(20,55,38,.08);min-width:120px;}
.stat b{display:block;font-size:24px;color:var(--deep);line-height:1.1;}
.stat span{font-size:12px;color:var(--muted);font-weight:600;}
.flash{background:var(--mint);border:1px solid var(--mint-line);color:var(--deep);
       padding:11px 14px;border-radius:12px;margin-bottom:14px;font-size:14px;font-weight:600;}
.tablebox{background:#fff;border-radius:18px;box-shadow:0 8px 28px rgba(20,55,38,.10);overflow-x:auto;}
table{width:100%;border-collapse:collapse;min-width:720px;}
th,td{padding:12px 14px;text-align:left;font-size:14px;border-bottom:1px solid #eef2ee;}
th{background:var(--mint);color:var(--deep);font-weight:800;font-size:12.5px;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;}
tr:last-child td{border-bottom:0;}
td.date{color:var(--muted);font-size:13px;white-space:nowrap;}
td.mail{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;}
.tick{display:inline-flex;align-items:center;gap:7px;border:0;background:transparent;cursor:pointer;font-weight:700;font-size:13.5px;}
.tick .box{width:21px;height:21px;border-radius:6px;border:2px solid var(--mint-line);display:inline-flex;
           align-items:center;justify-content:center;color:#fff;font-size:13px;background:#fff;}
.tick.on .box{background:var(--green);border-color:var(--green);}
.tick.on{color:var(--green);}
.tick.off{color:var(--muted);}
.del{border:0;background:transparent;color:#c7cdc8;cursor:pointer;font-size:16px;padding:2px 6px;border-radius:6px;}
.del:hover{color:var(--red);background:#fdecea;}
.vide{padding:40px 20px;text-align:center;color:var(--muted);font-size:14.5px;line-height:1.6;}
.vide code{background:var(--mint);padding:2px 7px;border-radius:6px;font-size:13px;}
@media(max-width:640px){h1{font-size:20px;}}
</style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <h1>📧 La Panne — adresses collectées</h1>
        <div class="actions">
            <a class="btn primary" href="?csv=1">Exporter en Excel (CSV)</a>
            <a class="btn" href="?deconnexion=1">Se déconnecter</a>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="flash"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat"><b><?= (int) $total ?></b><span>inscription<?= $total > 1 ? 's' : '' ?></span></div>
        <div class="stat"><b><?= (int) $remis ?></b><span>ticket<?= $remis > 1 ? 's' : '' ?> remis</span></div>
        <div class="stat"><b><?= (int) ($total - $remis) ?></b><span>reste à remettre</span></div>
    </div>

    <div class="tablebox">
        <?php if (empty($inscriptions)): ?>
            <div class="vide">
                Aucune inscription pour l'instant.<br>
                Les adresses saisies sur <code>/emails/lapanne/</code> apparaîtront ici.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Horodateur</th>
                        <th>Nom / Naam</th>
                        <th>Prénom / Voornaam</th>
                        <th>Adresse e-mail</th>
                        <th>Ticket remis</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($inscriptions as $ligne): ?>
                    <?php $coche = ((int) $ligne['ticket_remis']) === 1; ?>
                    <tr>
                        <td class="date"><?= e(date('d/m/Y H:i:s', strtotime((string) $ligne['created_at']))) ?></td>
                        <td><?= e($ligne['nom']) ?></td>
                        <td><?= e($ligne['prenom']) ?></td>
                        <td class="mail"><?= e($ligne['email']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $ligne['id'] ?>">
                                <input type="hidden" name="remis" value="<?= $coche ? '0' : '1' ?>">
                                <button class="tick <?= $coche ? 'on' : 'off' ?>" type="submit" name="basculer_ticket" value="1">
                                    <span class="box"><?= $coche ? '✓' : '' ?></span>
                                    <?= $coche ? 'Remis' : 'Non remis' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Supprimer l\'inscription de <?= e($ligne['prenom'] . ' ' . $ligne['nom']) ?> ?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $ligne['id'] ?>">
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
