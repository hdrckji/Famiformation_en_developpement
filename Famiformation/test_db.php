<?php
// test_db.php - test indépendant (ne requiert pas includes/)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Lecture simple du .env (si présent)
$envPath = __DIR__ . '/.env';
$env = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim(trim($v), \"'\\\"\");
    }
}

echo '<pre>';
echo "Fichier .env " . (file_exists($envPath) ? 'trouvé' : 'NON trouvé') . PHP_EOL;
echo "DB_HOST = " . ($env['DB_HOST'] ?? '(vide)') . PHP_EOL;
echo "DB_NAME = " . ($env['DB_NAME'] ?? '(vide)') . PHP_EOL;
echo "DB_USER = " . ($env['DB_USER'] ?? '(vide)') . PHP_EOL;
echo "DB_PASSWORD = " . (isset($env['DB_PASSWORD']) && $env['DB_PASSWORD'] !== '' ? '***' : '(vide)') . PHP_EOL;
echo "</pre>";

$host = $env['DB_HOST'] ?? 'localhost';
$dbname = $env['DB_NAME'] ?? 'test';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';

function try_pdo($dsn, $user, $pass) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo \"<p style='color:green'>OK : connexion via DSN: $dsn</p>\";
        return true;
    } catch (PDOException $e) {
        echo \"<p style='color:red'>ERREUR DSN: $dsn -> \" . htmlspecialchars($e->getMessage()) . \"</p>\";
        return false;
    }
}

echo \"<h3>Tests de connexion</h3>\";
// Test 1 : host tel quel
$dsn1 = \"mysql:host={$host};dbname={$dbname};charset=utf8\";
if (try_pdo($dsn1, $user, $pass)) exit;

// Test 2 : si host == 'localhost' on force 127.0.0.1 (TCP)
if ($host === 'localhost') {
    $dsn2 = \"mysql:host=127.0.0.1;dbname={$dbname};charset=utf8\";
    if (try_pdo($dsn2, $user, $pass)) exit;
}

// Test 3 : ajouter port 3306 si absent
$hostWithPort = strpos($host, ':') === false ? $host . ':3306' : $host;
$dsn3 = \"mysql:host={$hostWithPort};dbname={$dbname};charset=utf8\";
try_pdo($dsn3, $user, $pass);