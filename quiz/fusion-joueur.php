<?php
// ============================================================
// fusion-joueur.php — RENOMMER UN JOUEUR DANS LES FICHIERS DU QUIZ.
//
// Quand on fusionne deux comptes Famiformation, la base MySQL est corrigée
// mais PAS les fichiers du quiz : le jardin, les graines et les codes bonus
// y sont rangés sous l'IDENTIFIANT du joueur, pas sous son id numérique.
// Résultat, la personne se reconnecte sous son nouvel identifiant, le quiz
// ne trouve rien à ce nom et lui ouvre une fiche vierge (api.php ~1856).
// Ce script déplace tout de l'ancien nom vers le nouveau.
//
// UTILISATION (le script ne modifie RIEN sans &go=1) :
//   1) Simulation  : /quiz/fusion-joueur.php?pin=XXX&de=MichaelF&vers=MikaelF&site=mouscron
//   2) Application : la même adresse + &go=1
//
// ⚠️ SUPPRIMEZ CE FICHIER DU SERVEUR UNE FOIS LA FUSION FAITE.
// ============================================================

header('Content-Type: text/plain; charset=utf-8');

// --- Garde-fou : même mot de passe que l'admin du quiz, lu dans la variable
// d'environnement QUIZ_ADMIN_PWD (jamais écrit ici, ce dépôt est public).
// Une variable absente REFUSE l'accès, au lieu de laisser passer un ?pin= vide.
$ADMIN_PWD = (string) (getenv('QUIZ_ADMIN_PWD') ?: ($_SERVER['QUIZ_ADMIN_PWD'] ?? ''));
if ($ADMIN_PWD === '' || !hash_equals($ADMIN_PWD, (string) ($_GET['pin'] ?? ''))) {
    http_response_code(403);
    exit("Accès refusé : ajoutez ?pin=... à l'adresse.\n");
}

$de   = trim((string) ($_GET['de']   ?? ''));
$vers = trim((string) ($_GET['vers'] ?? ''));
$site = strtolower(trim((string) ($_GET['site'] ?? 'mouscron')));
$go   = (($_GET['go'] ?? '') === '1');

if ($de === '' || $vers === '') { exit("Paramètres manquants : de=... vers=...\n"); }
if (!in_array($site, ['mouscron', 'lapanne'], true)) { exit("site doit valoir mouscron ou lapanne.\n"); }
if (mb_strtolower($de) === mb_strtolower($vers)) { exit("L'ancien et le nouveau nom sont identiques.\n"); }

// --- Même détection de dossier que api.php (volume Railway, sinon quiz/data).
$vol = getenv('RAILWAY_VOLUME_MOUNT_PATH') ?: ($_SERVER['RAILWAY_VOLUME_MOUNT_PATH'] ?? '');
$dataDir = ($vol && @is_dir($vol)) ? rtrim($vol, "/\\") . '/quiz' : __DIR__ . '/data';

$scoresFile = "$dataDir/scores-$site.json";
$jardinFile = "$dataDir/jardin-$site.json";
$codesFile  = "$dataDir/codes-$site.json";

echo "Dossier de données : $dataDir\n";
echo "Renommage : '$de'  ->  '$vers'   (magasin : $site)\n";
echo $go ? "MODE : APPLICATION\n" : "MODE : SIMULATION (ajoutez &go=1 pour appliquer)\n";
echo str_repeat('=', 64) . "\n\n";

function lire($f) {
    if (!is_file($f)) { return null; }
    $d = json_decode((string) @file_get_contents($f), true);
    return is_array($d) ? $d : null;
}
function ecrire($f, $data, $go) {
    if (!$go) { return true; }
    // Sauvegarde horodatée avant toute écriture.
    if (is_file($f)) { @copy($f, $f . '.bak-' . date('Ymd-His')); }
    $tmp = $f . '.tmp';
    $ok = @file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $ok !== false && @rename($tmp, $f);
}

$deL   = mb_strtolower($de);
$versL = mb_strtolower($vers);

// ─── 1) LE JARDIN ───────────────────────────────────────────────
// Structure : { "michaelf": { "12": {plante, par, date}, ... }, ... }
echo "[1] jardin-$site.json\n";
$j = lire($jardinFile);
if ($j === null) {
    echo "    fichier absent ou illisible — rien à faire.\n";
} else {
    $src = null;
    foreach (array_keys($j) as $k) { if (mb_strtolower((string) $k) === $deL) { $src = $k; break; } }
    $dst = null;
    foreach (array_keys($j) as $k) { if (mb_strtolower((string) $k) === $versL) { $dst = $k; break; } }

    if ($src === null) {
        echo "    aucune case sous '$de' — rien à déplacer.\n";
    } else {
        $cases = is_array($j[$src]) ? $j[$src] : [];
        echo "    " . count($cases) . " case(s) trouvée(s) sous '$src'.\n";
        // On réécrit le champ « par » pour que l'affichage montre le bon nom.
        foreach ($cases as $i => $c) { if (is_array($c)) { $cases[$i]['par'] = $vers; } }

        if ($dst !== null && $dst !== $src) {
            // Les deux existent : on fusionne SANS jamais écraser une case déjà
            // plantée sous le nouveau nom (une case ne se plante qu'une fois).
            $garde = is_array($j[$dst]) ? $j[$dst] : [];
            $ajoutees = 0; $conflits = 0;
            foreach ($cases as $i => $c) {
                if (isset($garde[$i])) { $conflits++; } else { $garde[$i] = $c; $ajoutees++; }
            }
            $j[$dst] = $garde;
            unset($j[$src]);
            echo "    fusion dans '$dst' : $ajoutees ajoutée(s), $conflits déjà occupée(s) et conservée(s).\n";
        } else {
            unset($j[$src]);
            $j[$versL] = $cases;
            echo "    déplacé sous la clé '$versL'.\n";
        }
        echo ecrire($jardinFile, $j, $go) ? "    OK\n" : "    ECHEC D'ECRITURE\n";
    }
}

// ─── 2) LES SCORES / GRAINES ────────────────────────────────────
// Structure : [ {name, uid, nom, prenom, score, bonus, depensees, codes, ...}, ... ]
echo "\n[2] scores-$site.json\n";
$b = lire($scoresFile);
if ($b === null) {
    echo "    fichier absent ou illisible — rien à faire.\n";
} else {
    $iSrc = $iDst = -1;
    foreach ($b as $i => $p) {
        $n = mb_strtolower((string) ($p['name'] ?? ''));
        if ($n === $deL)   { $iSrc = $i; }
        if ($n === $versL) { $iDst = $i; }
    }
    if ($iSrc < 0) {
        echo "    aucune fiche au nom de '$de' — rien à déplacer.\n";
    } elseif ($iDst < 0) {
        $b[$iSrc]['name'] = $vers;
        echo "    fiche renommée (score=" . ($b[$iSrc]['score'] ?? 0)
           . ", bonus=" . ($b[$iSrc]['bonus'] ?? 0)
           . ", dépensées=" . ($b[$iSrc]['depensees'] ?? 0)
           . ", codes=" . ($b[$iSrc]['codes'] ?? 0) . ").\n";
        echo ecrire($scoresFile, $b, $go) ? "    OK\n" : "    ECHEC D'ECRITURE\n";
    } else {
        // Deux fiches : on additionne. Les graines dépensées suivent, sinon le
        // solde deviendrait faux par rapport aux cases déjà plantées.
        $s = $b[$iSrc]; $d = $b[$iDst];
        $somme = function ($k) use ($s, $d) { return floatval($s[$k] ?? 0) + floatval($d[$k] ?? 0); };
        $d['score']     = $somme('score');
        $d['bonus']     = $somme('bonus');
        $d['depensees'] = $somme('depensees');
        $d['correct']   = $somme('correct');
        $d['codes']     = $somme('codes');
        $d['time']      = max(floatval($s['time'] ?? 0), floatval($d['time'] ?? 0));
        $d['quiz_fait'] = !empty($s['quiz_fait']) || !empty($d['quiz_fait']);
        $pris = array_merge(
            is_array($s['codes_pris'] ?? null) ? $s['codes_pris'] : [],
            is_array($d['codes_pris'] ?? null) ? $d['codes_pris'] : []
        );
        $d['codes_pris'] = array_values(array_unique($pris));
        if (trim((string) ($d['pseudo'] ?? '')) === '') { $d['pseudo'] = $s['pseudo'] ?? ''; }
        $b[$iDst] = $d;
        array_splice($b, $iSrc, 1);
        echo "    deux fiches fusionnées -> score=" . $d['score']
           . ", bonus=" . $d['bonus'] . ", dépensées=" . $d['depensees']
           . ", codes=" . $d['codes'] . ".\n";
        echo ecrire($scoresFile, $b, $go) ? "    OK\n" : "    ECHEC D'ECRITURE\n";
    }
}

// ─── 3) LES CODES BONUS ─────────────────────────────────────────
// Structure : { "1234": {par, date}, ... }
echo "\n[3] codes-$site.json\n";
$c = lire($codesFile);
if ($c === null) {
    echo "    fichier absent ou illisible — rien à faire.\n";
} else {
    $n = 0;
    foreach ($c as $code => $info) {
        if (is_array($info) && mb_strtolower((string) ($info['par'] ?? '')) === $deL) {
            $c[$code]['par'] = $vers; $n++;
        }
    }
    echo "    $n code(s) réattribué(s).\n";
    if ($n > 0) { echo ecrire($codesFile, $c, $go) ? "    OK\n" : "    ECHEC D'ECRITURE\n"; }
}

echo "\n" . str_repeat('=', 64) . "\n";
echo $go
    ? "Terminé. Sauvegardes .bak-* déposées à côté des fichiers.\nSUPPRIMEZ CE SCRIPT DU SERVEUR.\n"
    : "Simulation terminée — aucun fichier modifié. Ajoutez &go=1 pour appliquer.\n";
