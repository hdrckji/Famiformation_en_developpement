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

<h2>🔑 Connexion « identifiants Famiformation »</h2>
<div class="enc">
<?php
// Le quiz n'utilise PAS la connexion du site : il a sa propre fonction famiDb().
// Si elle pointe ailleurs, ou si une variable QUIZ_DB_* traine, les identifiants
// du site ne sont evidemment pas reconnus — sans que rien ne l'explique.
$dsnQuiz = getenv('QUIZ_DB_DSN') ?: ($_SERVER['QUIZ_DB_DSN'] ?? '');
echo '<b>QUIZ_DB_DSN :</b> ' . ($dsnQuiz
    ? '<span class="ko">DÉFINIE — le quiz interroge une AUTRE base que le site !</span> <code>' . htmlspecialchars($dsnQuiz) . '</code>'
    : '<span class="ok">non définie</span> (le quiz utilise la même base que le site)') . '<br>';

$h = getenv('DB_HOST') ?: ($_SERVER['DB_HOST'] ?? '');
$n = getenv('DB_NAME') ?: ($_SERVER['DB_NAME'] ?? '');
echo '<b>DB_HOST :</b> <code>' . htmlspecialchars($h ?: '(vide)') . '</code> · ';
echo '<b>DB_NAME :</b> <code>' . htmlspecialchars($n ?: '(vide)') . '</code><br>';

// Connexion reelle, exactement comme famiDb().
$pdo = null;
$err = '';
try {
    if ($dsnQuiz !== '') {
        $pdo = new PDO($dsnQuiz, (string) (getenv('QUIZ_DB_USER') ?: ''), (string) (getenv('QUIZ_DB_PASS') ?: ''));
    } else {
        $pdo = new PDO('mysql:host=' . $h . ';dbname=' . $n . ';charset=utf8mb4',
            (string) (getenv('DB_USER') ?: ($_SERVER['DB_USER'] ?? '')),
            (string) (getenv('DB_PASSWORD') ?: ($_SERVER['DB_PASSWORD'] ?? '')));
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) { $err = $e->getMessage(); }

echo '<b>Connexion :</b> ' . ($pdo ? '<span class="ok">réussie</span>'
    : '<span class="ko">ÉCHEC</span> — <code>' . htmlspecialchars($err) . '</code>') . '<br>';

if ($pdo) {
    // Les colonnes exigees par login_fami. Une seule manquante fait planter la
    // requete (PDO en mode exception) et le joueur lit « identifiant incorrect ».
    try {
        $cols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM utilisateurs")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cols[strtolower($c['Field'])] = true;
        }
        echo '<b>Table utilisateurs :</b> <span class="ok">présente</span> (' . count($cols) . ' colonnes)<br>';
        $besoin = ['id', 'identifiant', 'prenom', 'nom', 'email', 'mot_de_passe', 'account_activation_pending'];
        $manque = array_values(array_filter($besoin, function ($c) use ($cols) { return !isset($cols[$c]); }));
        echo '<b>Colonnes utiles à la connexion :</b> ' . (empty($manque)
            ? '<span class="ok">toutes présentes</span>'
            : '<span class="ko">MANQUE : ' . htmlspecialchars(implode(', ', $manque)) . '</span>') . '<br>';
        echo '<b>Comptes au total :</b> ' . (int) $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn() . '<br>';

        // Recherche d'un identifiant precis : ?id=EnylsonL
        $cherche = trim((string) ($_GET['id'] ?? ''));
        if ($cherche !== '') {
            $q = $pdo->prepare('SELECT identifiant, email, role, account_activation_pending,
                                       (mot_de_passe IS NULL OR mot_de_passe = "") AS sans_mdp
                                FROM utilisateurs WHERE identifiant = ? OR LOWER(email) = ? LIMIT 1');
            $q->execute([$cherche, strtolower($cherche)]);
            $u = $q->fetch(PDO::FETCH_ASSOC);
            echo '<hr style="border:none;border-top:1px solid #eef2ef;margin:12px 0;">';
            echo '<b>Recherche de « ' . htmlspecialchars($cherche) . ' » :</b><br>';
            if (!$u) {
                echo '<span class="ko">AUCUN compte avec cet identifiant ni cette adresse</span> → le quiz répondra « identifiant incorrect ».';
            } else {
                echo '<span class="ok">trouvé</span> · identifiant <code>' . htmlspecialchars($u['identifiant']) . '</code>'
                   . ' · profil <code>' . htmlspecialchars((string) $u['role']) . '</code><br>';
                echo '<b>Mot de passe défini :</b> ' . ($u['sans_mdp'] ? '<span class="ko">NON</span>' : '<span class="ok">oui</span>') . ' · ';
                echo '<b>En attente d\'activation :</b> ' . (!empty($u['account_activation_pending'])
                    ? '<span class="ko">OUI — la connexion est refusée tant que ce drapeau est levé</span>'
                    : '<span class="ok">non</span>');
            }
        } else {
            echo '<hr style="border:none;border-top:1px solid #eef2ef;margin:12px 0;">';
            echo '<span style="opacity:.7;">Ajoute <code>&amp;id=EnylsonL</code> à l\'adresse pour tester un identifiant précis.</span>';
        }
    } catch (Throwable $e) {
        echo '<span class="ko">Erreur en interrogeant la table : ' . htmlspecialchars($e->getMessage()) . '</span>';
    }
}
?>
</div>

<p style="font-size:.85rem;color:#6a7d72;">PHP <?= PHP_VERSION ?> · fsync <?= function_exists('fsync') ? 'disponible' : 'ABSENT' ?> · <?= date('d/m/Y H:i') ?></p>
