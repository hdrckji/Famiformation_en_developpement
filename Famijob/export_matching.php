<?php
// ============================================================
// export_matching.php — Export du matching de la semaine au format "planning".
//
//   Grille : 7 colonnes-jours (lundi -> dimanche), chacune subdivisee en
//   6 sous-colonnes (horaire | I | EI | EFM | nom | agence).
//   Les affectations sont regroupees par departement (une ligne d'en-tete de
//   departement, puis une ligne par personne, chaque jour dans sa colonne).
//
//   Sortie : CSV (separateur ';', BOM UTF-8) -> s'ouvre directement dans Excel.
// ============================================================

require_once 'config.php';
verifierConnexion($db);

$role = (string) ($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'teamcoach'], true)) {
    header('Location: ' . famijobSiteUrl('index.php'));
    exit();
}
$isAdmin = ($role === 'admin');

// Un teamcoach n'exporte que le planning de SON agence (comme ce qu'il voit dans le matching).
$agencyName = '';
if (!$isAdmin) {
    try {
        $agencyStmt = $db->prepare('SELECT interim FROM utilisateurs WHERE id = ? LIMIT 1');
        $agencyStmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
        $agencyName = trim((string) $agencyStmt->fetchColumn());
    } catch (Exception $e) {}
}

// --- Semaine demandee (on se cale toujours sur le lundi) ---
$today = new DateTimeImmutable('today');
$defaultMonday = $today->modify('monday this week');
$weekParam = (string) ($_GET['week'] ?? $defaultMonday->format('Y-m-d'));
try {
    $weekStart = new DateTimeImmutable($weekParam);
} catch (Exception $e) {
    $weekStart = $defaultMonday;
}
$weekStart = $weekStart->modify('monday this week');
$weekEnd = $weekStart->modify('+6 days');

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = $weekStart->modify('+' . $i . ' days');
}
$dayIndexByKey = [];
foreach ($days as $idx => $d) {
    $dayIndexByKey[$d->format('Y-m-d')] = $idx;
}

// --- Les creneaux de la semaine, pourvus ou non ---
//
// ⚠️ ON PART DES CRENEAUX, PAS DES AFFECTATIONS. Avant, la requete partait de
// `interim_shift_assignments` en INNER JOIN : un creneau que personne n'occupe
// encore n'avait aucune ligne, donc n'existait pas dans le fichier. Or c'est
// justement celui-la qu'on veut voir — le tableau se complete a la main, et on
// ne remplit pas une ligne qui n'est pas imprimee.
//
// D'ou un LEFT JOIN : chaque creneau valide sort au moins une fois, avec ses
// places occupees s'il en a. `assignment_id` sert a distinguer « pas
// d'affectation » d'une affectation vide.
$sql =
    "SELECT r.id AS request_id, r.shift_date, r.department_name, r.time_slot, r.seats_required,
            a.id AS assignment_id, a.seat_number, a.student_id, a.external_name, a.agency_name,
            u.nom AS student_nom, u.prenom AS student_prenom, u.interim AS student_interim
     FROM interim_shift_requests r
     LEFT JOIN interim_shift_assignments a ON a.request_id = r.id
     LEFT JOIN utilisateurs u ON u.id = a.student_id
     WHERE r.shift_date BETWEEN ? AND ?";
$sqlParams = [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')];
// Le filtre d'agence d'un teamcoach ne peut PLUS etre en SQL : il ecarterait le
// creneau entier, places libres comprises. Il s'applique plus bas, sur les noms.
// ⚠️ Uniquement les creneaux VALIDES, comme a l'ecran. Un planning exporte se
// diffuse : y glisser une demande en attente, c'est annoncer une place que
// personne n'a accordee.
$colonnesReq = [];
foreach ($db->query('SHOW COLUMNS FROM interim_shift_requests')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $colonnesReq[(string) $c['Field']] = true;
}
if (isset($colonnesReq['validation_status'])) {
    $sql .= " AND r.validation_status = 'approved'";
}

$sql .= " ORDER BY r.department_name ASC, r.time_slot ASC, r.id ASC, a.seat_number ASC";
$stmt = $db->prepare($sql);
$stmt->execute($sqlParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ⚠️ LE SECTEUR, LUI, N'EST PAS UNE COLONNE. `department_name` est du texte
// libre qui peut porter un departement ou un secteur : on le RESOUT, avec le
// meme rangement que les ecrans (includes/grille_semaine.php), plutot que
// d'inventer ici une seconde facon de ranger.
//
// Sans ce filtre, l'export ne dirait pas la meme chose que l'ecran d'ou on
// vient — et c'est exactement le genre de fichier qu'on diffuse sans le
// relire.
// Un seul secteur (?secteur=) ou plusieurs (?secteurs[]=), au choix : le premier
// vient du bouton qui suit les filtres a l'ecran, le second de la fenetre de
// selection. Les deux se rejoignent ici.
$filtreSecteurs = [];
if (!empty($_GET['secteurs']) && is_array($_GET['secteurs'])) {
    $filtreSecteurs = array_values(array_filter(array_map('strval', $_GET['secteurs'])));
} elseif (trim((string) ($_GET['secteur'] ?? '')) !== '') {
    $filtreSecteurs = [trim((string) $_GET['secteur'])];
}

// Le departement se resout de la meme facon, et pas par un `=` en SQL : « Plantes
// exterieur » et « Plantes extérieures » sont le meme endroit, un `=` en laisserait
// la moitie dehors.
$filtreDept = trim((string) ($_GET['department'] ?? ''));
if ($filtreDept === 'all') {
    $filtreDept = '';
}

if ($filtreSecteurs || $filtreDept !== '') {
    require_once __DIR__ . '/includes/grille_semaine.php';
    $rangementExport = grilleSemaineRangement($db);
    $cibleDept = $filtreDept !== '' ? grilleSemaineCle($filtreDept) : '';
    $rows = array_values(array_filter($rows, static function ($r) use ($rangementExport, $filtreSecteurs, $cibleDept) {
        $place = grilleSemaineResout((string) $r['department_name'], $rangementExport);
        if ($filtreSecteurs && !in_array($place['secteur'], $filtreSecteurs, true)) {
            return false;
        }
        if ($cibleDept !== '') {
            return $place['sous'] !== '' && grilleSemaineCle($place['sous']) === $cibleDept;
        }
        return true;
    }));
}

// --- Un creneau, ses places, ceux qui les occupent ---
// Le LEFT JOIN renvoie une ligne par place occupee, et UNE ligne a vide quand le
// creneau n'est pourvu par personne. On rassemble d'abord par creneau pour savoir
// combien de places il compte.
$creneaux = [];
foreach ($rows as $r) {
    $rid = (int) $r['request_id'];
    if (!isset($creneaux[$rid])) {
        $creneaux[$rid] = [
            'dept'    => trim((string) $r['department_name']),
            'date'    => (string) $r['shift_date'],
            'horaire' => trim((string) $r['time_slot']),
            'places'  => max(1, (int) $r['seats_required']),
            'gens'    => [],
        ];
    }
    if ($r['assignment_id'] === null) {
        continue;   // creneau sans aucune affectation : rien a ajouter
    }

    $isExternal = empty($r['student_id']);
    if ($isExternal) {
        $nom = trim((string) ($r['external_name'] ?? ''));
        $agence = trim((string) ($r['agency_name'] ?? ''));
    } else {
        $nom = trim(trim((string) ($r['student_nom'] ?? '')) . ' ' . trim((string) ($r['student_prenom'] ?? '')));
        $agence = trim((string) ($r['student_interim'] ?? ''));
        if ($agence === '') {
            $agence = trim((string) ($r['agency_name'] ?? ''));
        }
    }

    // Un teamcoach ne lit que les noms de SON agence. La place reste occupee et
    // se voit occupee : la laisser vide reviendrait a l'offrir deux fois.
    if (!$isAdmin && $agencyName !== '' && $agence !== $agencyName) {
        $nom = '(autre agence)';
        $agence = '';
    }

    $creneaux[$rid]['gens'][] = ['nom' => $nom, 'agence' => $agence];
}

// --- Regroupement : departement -> [jour0..jour6] -> une ligne par PLACE ---
// Une place libre sort avec son horaire et deux cases vides : c'est la ligne
// qu'on remplit au stylo.
$byDept = [];
foreach ($creneaux as $c) {
    $dept = $c['dept'] !== '' ? $c['dept'] : '(sans département)';
    if (!isset($dayIndexByKey[$c['date']])) {
        continue;
    }
    $dayIdx = $dayIndexByKey[$c['date']];
    if (!isset($byDept[$dept])) {
        $byDept[$dept] = array_fill(0, 7, []);
    }

    // Plus d'affectations que de places demandees ? On montre tout le monde :
    // un nom qu'on cache est un nom qu'on oublie de prevenir.
    $lignes = max($c['places'], count($c['gens']));
    for ($i = 0; $i < $lignes; $i++) {
        $g = $c['gens'][$i] ?? ['nom' => '', 'agence' => ''];
        $byDept[$dept][$dayIdx][] = [
            'horaire' => $c['horaire'],
            'nom'     => $g['nom'],
            'agence'  => $g['agence'],
        ];
    }
}

// Ordre des departements : alphabetique (gerable ensuite depuis Paramètres).
$deptNames = array_keys($byDept);
usort($deptNames, static function ($a, $b) {
    return strcasecmp($a, $b);
});

// --- Libelles de dates en francais (sans dependance a l'ext intl) ---
$joursFr = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
$moisFr = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];
$dateLabel = static function (DateTimeImmutable $d) use ($joursFr, $moisFr) {
    return $joursFr[(int) $d->format('N')] . ' ' . $d->format('j') . ' ' . $moisFr[(int) $d->format('n')] . ' ' . $d->format('Y');
};

// --- Construction des lignes CSV (chaque ligne = 42 cellules : 7 jours x 6 colonnes) ---
$csvRows = [];

// Ligne 1 : dates (dans la 1re sous-colonne de chaque jour)
$line = [];
foreach ($days as $d) {
    $line[] = $dateLabel($d);
    $line[] = ''; $line[] = ''; $line[] = ''; $line[] = ''; $line[] = '';
}
$csvRows[] = $line;

// Ligne 2 : en-tetes de colonnes, repetees pour chaque jour
$line = [];
for ($i = 0; $i < 7; $i++) {
    $line[] = 'horaire';
    $line[] = 'I';
    $line[] = 'EI';
    $line[] = 'EFM';
    $line[] = 'nom';
    $line[] = 'agence';
}
$csvRows[] = $line;

// Blocs par departement
foreach ($deptNames as $dept) {
    // Ligne d'en-tete du departement (repetee sur chaque jour)
    $line = [];
    for ($i = 0; $i < 7; $i++) {
        $line[] = $dept;
        $line[] = ''; $line[] = ''; $line[] = ''; $line[] = ''; $line[] = '';
    }
    $csvRows[] = $line;

    // Nombre de lignes = max d'affectations sur un jour pour ce departement
    $maxRows = 0;
    foreach ($byDept[$dept] as $dayList) {
        $maxRows = max($maxRows, count($dayList));
    }

    for ($rowIdx = 0; $rowIdx < $maxRows; $rowIdx++) {
        $line = [];
        for ($dayIdx = 0; $dayIdx < 7; $dayIdx++) {
            $a = $byDept[$dept][$dayIdx][$rowIdx] ?? null;
            if ($a === null) {
                $line[] = ''; $line[] = ''; $line[] = ''; $line[] = ''; $line[] = ''; $line[] = '';
            } else {
                $line[] = $a['horaire'];
                $line[] = ''; // I
                $line[] = ''; // EI
                $line[] = ''; // EFM
                $line[] = $a['nom'];
                $line[] = $a['agence'];
            }
        }
        $csvRows[] = $line;
    }

    // Ligne vide de separation
    $csvRows[] = array_fill(0, 42, '');
}

// ============================================================
// SORTIE : on tente un vrai fichier Excel (.xlsx) mis en forme (couleurs,
// fusions, bordures) via PhpSpreadsheet. En cas d'indisponibilite -> CSV.
// ============================================================

/** Lettre de colonne Excel a partir d'un index 1-based (1 -> A, 27 -> AA). */
function fjxCol($n)
{
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr(65 + ($n % 26)) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

/** Construit le classeur .xlsx et renvoie ses octets (ou lance une exception). */
function fjxBuildXlsx(array $days, array $deptNames, array $byDept, callable $dateLabel)
{
    $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle('Matching');

    $fillSolid = \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID;
    $hCenter = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;
    $vCenter = \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER;
    $thin = \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN;

    // Largeurs de colonnes (motif repete pour chaque jour : horaire,I,EI,EFM,nom,agence).
    $widths = [10, 4, 4, 5, 22, 14];
    for ($d = 0; $d < 7; $d++) {
        for ($c = 0; $c < 6; $c++) {
            $sheet->getColumnDimension(fjxCol($d * 6 + $c + 1))->setWidth($widths[$c]);
        }
    }

    $r = 1;

    // Ligne 1 : dates (fusionnees sur les 6 colonnes de chaque jour).
    foreach ($days as $i => $d) {
        $c1 = $i * 6 + 1;
        $sheet->setCellValue(fjxCol($c1) . $r, ucfirst($dateLabel($d)));
        $sheet->mergeCells(fjxCol($c1) . $r . ':' . fjxCol($c1 + 5) . $r);
    }
    $sheet->getStyle('A' . $r . ':' . fjxCol(42) . $r)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => $fillSolid, 'startColor' => ['rgb' => '264E35']],
        'alignment' => ['horizontal' => $hCenter, 'vertical' => $vCenter],
    ]);
    $sheet->getRowDimension($r)->setRowHeight(22);
    $r++;

    // Ligne 2 : en-tetes de colonnes.
    $heads = ['horaire', 'I', 'EI', 'EFM', 'nom', 'agence'];
    for ($d = 0; $d < 7; $d++) {
        foreach ($heads as $c => $h) {
            $sheet->setCellValue(fjxCol($d * 6 + $c + 1) . $r, $h);
        }
    }
    $sheet->getStyle('A' . $r . ':' . fjxCol(42) . $r)->applyFromArray([
        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '21362A']],
        'fill' => ['fillType' => $fillSolid, 'startColor' => ['rgb' => 'D9E7DD']],
        'alignment' => ['horizontal' => $hCenter, 'vertical' => $vCenter],
    ]);
    $r++;

    $headerRows = $r - 1; // fige les 2 lignes d'en-tete

    foreach ($deptNames as $dept) {
        // Bande de departement (fusionnee sur toute la largeur).
        $sheet->setCellValue('A' . $r, $dept);
        $sheet->mergeCells('A' . $r . ':' . fjxCol(42) . $r);
        $sheet->getStyle('A' . $r . ':' . fjxCol(42) . $r)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '6B4E00']],
            'fill' => ['fillType' => $fillSolid, 'startColor' => ['rgb' => 'FDE9A9']],
            'alignment' => ['vertical' => $vCenter],
        ]);
        $sheet->getStyle('A' . $r)->getAlignment()->setIndent(1);
        $sheet->getRowDimension($r)->setRowHeight(18);
        $r++;

        $maxRows = 0;
        foreach ($byDept[$dept] as $dayList) {
            $maxRows = max($maxRows, count($dayList));
        }

        $firstDataRow = $r;
        for ($rowIdx = 0; $rowIdx < $maxRows; $rowIdx++) {
            for ($dayIdx = 0; $dayIdx < 7; $dayIdx++) {
                $a = $byDept[$dept][$dayIdx][$rowIdx] ?? null;
                if ($a === null) {
                    continue;
                }
                $base = $dayIdx * 6 + 1;
                $sheet->setCellValue(fjxCol($base) . $r, $a['horaire']);       // horaire
                $sheet->setCellValue(fjxCol($base + 4) . $r, $a['nom']);       // nom
                $sheet->setCellValue(fjxCol($base + 5) . $r, $a['agence']);    // agence
            }
            $r++;
        }

        // Bordures fines sur la zone de donnees du departement.
        if ($maxRows > 0) {
            $sheet->getStyle('A' . $firstDataRow . ':' . fjxCol(42) . ($r - 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => $thin, 'color' => ['rgb' => 'DDE6DF']]],
                'font' => ['size' => 10],
                // Centre, horizontalement et verticalement. Des horaires et des
                // noms calés à gauche dans des colonnes larges laissent un vide
                // qui casse la lecture d'une ligne à l'autre.
                'alignment' => ['horizontal' => $hCenter, 'vertical' => $vCenter],
            ]);
            // Le nom du département reste à gauche : c'est un libellé de ligne,
            // pas une donnée du tableau, et l'œil le suit mieux aligné.
            $sheet->getStyle('A' . $firstDataRow . ':A' . ($r - 1))
                  ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
            // Couleur alternee : on teinte les jours impairs (mardi, jeudi, samedi) pour
            // distinguer visuellement les colonnes de jours (comme dans le planning).
            for ($dayIdx = 1; $dayIdx < 7; $dayIdx += 2) {
                $c1 = fjxCol($dayIdx * 6 + 1);
                $c2 = fjxCol($dayIdx * 6 + 6);
                $sheet->getStyle($c1 . $firstDataRow . ':' . $c2 . ($r - 1))
                    ->getFill()->setFillType($fillSolid)->getStartColor()->setRGB('FBF1E8');
            }
        }

        $r++; // ligne vide de separation
    }

    $lastRow = max($headerRows, $r - 1);

    // Separateurs verticaux entre les jours (bordure moyenne a gauche de chaque jour).
    for ($d = 1; $d < 7; $d++) {
        $col = fjxCol($d * 6 + 1);
        $sheet->getStyle($col . '1:' . $col . $lastRow)->getBorders()->getLeft()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
            ->getColor()->setRGB('9BB0A3');
    }
    // Cadre exterieur du tableau.
    $sheet->getStyle('A1:' . fjxCol(42) . $lastRow)->getBorders()->getOutline()
        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
        ->getColor()->setRGB('264E35');

    // Fige les colonnes/lignes d'en-tete.
    $sheet->freezePane('A' . ($headerRows + 1));

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
    ob_start();
    $writer->save('php://output');
    $bytes = ob_get_clean();
    $ss->disconnectWorksheets();
    unset($ss);
    return $bytes;
}

// Tentative de chargement de PhpSpreadsheet (present dans public/vendor sur Railway).
$xlsxData = null;
foreach ([
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__) . '/public/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        break;
    }
}
if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    try {
        $xlsxData = fjxBuildXlsx($days, $deptNames, $byDept, $dateLabel);
    } catch (\Throwable $e) {
        $xlsxData = null;
    }
}

if (is_string($xlsxData) && $xlsxData !== '') {
    $filename = 'matching_semaine_' . $weekStart->format('Y-m-d') . '.xlsx';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsxData));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $xlsxData;
    exit();
}

// --- Repli CSV (si PhpSpreadsheet indisponible) ---
$escapeCsv = static function ($value) {
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    if (strpbrk($value, ";\"\r\n") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
};

$filename = 'matching_semaine_' . $weekStart->format('Y-m-d') . '.csv';

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
foreach ($csvRows as $r) {
    $cells = array_map($escapeCsv, $r);
    fwrite($out, implode(';', $cells) . "\r\n");
}
fclose($out);
exit();
