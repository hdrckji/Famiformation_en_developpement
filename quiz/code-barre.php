<?php
// ============================================================
// Code-barres PNG d'un code récompense.
//
// Appelé depuis le mail « ta récompense est prête » : les clients mail
// n'affichent pas de SVG, il faut donc une vraie image. Symbologie Code 128 B,
// qui accepte les lettres, les chiffres et le tiret de nos codes
// (« P44X58982539-23128 »).
//
// SÉCURITÉ : on ne dessine QUE des codes présents dans recompense_codes. Sans
// ce contrôle, l'adresse deviendrait un générateur de codes-barres public, et
// n'importe qui pourrait fabriquer l'image d'un bon qui n'existe pas.
// ============================================================

// Connexion : même chemin que famiDb() dans api.php. On ne peut pas inclure
// api.php, qui exécuterait toute l'API et répondrait du JSON à notre place.
// Les deux dispositions de dossiers sont gérées : dans le conteneur quiz/ est
// DANS public/, dans le dépôt les deux sont côte à côte.
function codeBarreDb() {
    foreach ([__DIR__ . '/../includes/functions.php', __DIR__ . '/../public/includes/functions.php'] as $piste) {
        if (is_file($piste)) {
            // On avale tout ce que le fichier pourrait émettre : un simple espace
            // avant <?php corromprait l'en-tête PNG et l'image serait illisible.
            ob_start();
            require_once $piste;
            ob_end_clean();
            break;
        }
    }
    if (!function_exists('famiGetEnv')) { return null; }
    try {
        $dsn = (string) famiGetEnv('QUIZ_DB_DSN', '');
        if ($dsn !== '') {
            return new PDO($dsn, (string) famiGetEnv('QUIZ_DB_USER', ''), (string) famiGetEnv('QUIZ_DB_PASS', ''));
        }
        return new PDO(
            'mysql:host=' . famiGetEnv('DB_HOST', 'localhost') . ';dbname=' . famiGetEnv('DB_NAME', '') . ';charset=utf8mb4',
            (string) famiGetEnv('DB_USER', ''),
            (string) famiGetEnv('DB_PASSWORD', '')
        );
    } catch (Throwable $e) {
        return null;
    }
}

$code = trim((string) ($_GET['c'] ?? ''));

function refuse($message) {
    header('Content-Type: image/png');
    $im = imagecreatetruecolor(300, 60);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagestring($im, 3, 8, 22, $message, imagecolorallocate($im, 170, 60, 40));
    imagepng($im);
    imagedestroy($im);
    exit();
}

if ($code === '' || !preg_match('/^[\x20-\x7E]{1,60}$/', $code)) {
    refuse('Code invalide');
}

// Le code doit exister dans le stock.
$connu = false;
try {
    $db = codeBarreDb();
    if ($db instanceof PDO) {
        $st = $db->prepare('SELECT 1 FROM recompense_codes WHERE barcode = ? LIMIT 1');
        $st->execute([$code]);
        $connu = (bool) $st->fetchColumn();
    }
} catch (Throwable $e) {
    $connu = false;
}
if (!$connu) {
    refuse('Code inconnu');
}

// --- Code 128 : les 107 motifs, en largeurs de barres/espaces ---------------
$MOTIFS = [
    '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
    '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
    '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
    '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
    '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
    '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
    '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
    '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
    '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
    '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
    '114131','311141','411131','211412','211214','211232',
    // ⚠️ Le motif d'ARRÊT (106) est le seul à faire 13 modules et 7 éléments,
    // tous les autres en font 11 sur 6. Un lecteur de caisse refuse le code si
    // cette terminaison n'est pas exacte — et rien ne se voit à l'œil nu.
    '2331112',
];

// Code 128 B : la valeur d'un caractère est son code ASCII moins 32.
$valeurs = [104]; // START B
$somme = 104;
$longueur = strlen($code);
for ($i = 0; $i < $longueur; $i++) {
    $v = ord($code[$i]) - 32;
    $valeurs[] = $v;
    $somme += $v * ($i + 1);   // pondération par la position, 1 pour le premier
}
$valeurs[] = $somme % 103;      // clé de contrôle
$valeurs[] = 106;               // STOP

// Suite des largeurs : barre, espace, barre, espace…
$largeurs = [];
foreach ($valeurs as $v) {
    foreach (str_split($MOTIFS[$v]) as $l) {
        $largeurs[] = (int) $l;
    }
}
// Rien à ajouter ici : le motif d'arrêt contient déjà sa barre finale.

$MODULE = 2;                                   // largeur d'un module, en pixels
$MARGE = 14;                                   // zone calme, exigée par les lecteurs
$HAUTEUR = 70;
$BAS = 20;                                     // place pour le texte sous les barres
$total = array_sum($largeurs) * $MODULE + $MARGE * 2;

$im = imagecreatetruecolor($total, $HAUTEUR + $BAS);
$blanc = imagecolorallocate($im, 255, 255, 255);
$noir = imagecolorallocate($im, 0, 0, 0);
imagefill($im, 0, 0, $blanc);

$x = $MARGE;
$barre = true;                                 // on commence toujours par une barre
foreach ($largeurs as $l) {
    $w = $l * $MODULE;
    if ($barre) {
        imagefilledrectangle($im, $x, 6, $x + $w - 1, $HAUTEUR, $noir);
    }
    $x += $w;
    $barre = !$barre;
}

// Le code en clair sous les barres : si un lecteur refuse de scanner, la caisse
// peut toujours le saisir.
$largeurTexte = imagefontwidth(3) * strlen($code);
imagestring($im, 3, max(0, (int) (($total - $largeurTexte) / 2)), $HAUTEUR + 4, $code, $noir);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($im);
imagedestroy($im);
