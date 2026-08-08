<?php
// ============================================================
// vue_horaire.php — PLANNING HEBDOMADAIRE DES PERSONNES AFFECTÉES.
//
//   Colonnes = les 7 jours. Lignes = les départements.
//   Dans chaque case, une barre par personne affectée, DESSINÉE À L'ÉCHELLE :
//   toutes les cases partagent la même règle horaire (du plus tôt au plus tard
//   de la semaine affichée), donc un 8h-18h est visiblement plus long qu'un
//   12h-17h, et deux barres qui commencent au même moment sont alignées.
//
//   L'analyse des créneaux (texte libre en base) vit dans includes/horaires.php,
//   partagée avec l'envoi des horaires par mail : la barre affichée et l'heure
//   envoyée par mail viennent du même calcul.
// ============================================================

require_once 'config.php';
require_once __DIR__ . '/includes/horaires.php';
verifierConnexion($db);

$pageLang = famiLang();
if (!function_exists('fjvhT')) {
    function fjvhT($fr, $nl = null)
    {
        return famiLang() === 'nl' && $nl !== null ? $nl : $fr;
    }
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if (!in_array($role, ['admin'], true)) {
    header('Location: ' . famijobSiteUrl('index.php'));
    exit();
}

ensureDepartmentsTable($db);
try {
    syncDepartmentsFromPlanningDb($db);
} catch (Exception $e) {
    // La vue fonctionne aussi avec la liste locale des départements.
}

$today = new DateTimeImmutable('today');
$startMonday = $today->modify('monday this week');
$weekOptions = [];
for ($offset = -4; $offset < 8; $offset++) {
    $weekStart = $startMonday->modify(($offset >= 0 ? '+' : '') . $offset . ' week');
    $weekEnd = $weekStart->modify('+6 days');
    $weekOptions[$weekStart->format('Y-m-d')] = [
        'start' => $weekStart,
        'end' => $weekEnd,
        'label' => fjvhT('Semaine du ', 'Week van ') . $weekStart->format('d/m/Y') . fjvhT(' au ', ' tot ') . $weekEnd->format('d/m/Y')
            . ($offset === 0 ? fjvhT(' (en cours)', ' (lopend)') : ''),
    ];
}

$selectedWeekKey = (string) ($_GET['week'] ?? $startMonday->format('Y-m-d'));
if (!isset($weekOptions[$selectedWeekKey])) {
    $selectedWeekKey = $startMonday->format('Y-m-d');
}
$selectedWeek = $weekOptions[$selectedWeekKey];

$departmentFilterStmt = $db->query(
    'SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC'
);
$departmentFilterOptions = $departmentFilterStmt->fetchAll(PDO::FETCH_COLUMN);

$selectedDepartment = trim((string) ($_GET['department'] ?? 'all'));
if ($selectedDepartment !== 'all' && !in_array($selectedDepartment, $departmentFilterOptions, true)) {
    $selectedDepartment = 'all';
}

$weekdayMap = [
    'Monday' => fjvhT('Lundi', 'Maandag'),
    'Tuesday' => fjvhT('Mardi', 'Dinsdag'),
    'Wednesday' => fjvhT('Mercredi', 'Woensdag'),
    'Thursday' => fjvhT('Jeudi', 'Donderdag'),
    'Friday' => fjvhT('Vendredi', 'Vrijdag'),
    'Saturday' => fjvhT('Samedi', 'Zaterdag'),
    'Sunday' => fjvhT('Dimanche', 'Zondag'),
];

$todayKey = $today->format('Y-m-d');
$weekDays = [];
$cursor = $selectedWeek['start'];
while ($cursor <= $selectedWeek['end']) {
    $weekDays[] = [
        'key' => $cursor->format('Y-m-d'),
        'label' => $weekdayMap[$cursor->format('l')] ?? $cursor->format('l'),
        'date' => $cursor->format('d/m/Y'),
        'is_today' => $cursor->format('Y-m-d') === $todayKey,
    ];
    $cursor = $cursor->modify('+1 day');
}

$requestsStmt = $db->prepare(
    "SELECT id, shift_date, department_name, time_slot, seats_required, comment
     FROM interim_shift_requests
     WHERE shift_date BETWEEN ? AND ?
     ORDER BY shift_date ASC, department_name ASC, time_slot ASC"
);
$requestsStmt->execute([
    $selectedWeek['start']->format('Y-m-d'),
    $selectedWeek['end']->format('Y-m-d'),
]);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

$assignmentsByRequest = [];
$requestIds = array_map(static function ($row) {
    return (int) $row['id'];
}, $requests);

if (!empty($requestIds)) {
    $placeholders = implode(', ', array_fill(0, count($requestIds), '?'));
    $assignmentsStmt = $db->prepare(
        "SELECT a.request_id, a.seat_number, a.student_id, a.external_name, a.agency_name,
                u.nom, u.prenom, u.interim
         FROM interim_shift_assignments a
         LEFT JOIN utilisateurs u ON u.id = a.student_id
         WHERE a.request_id IN ($placeholders)
         ORDER BY a.request_id ASC, a.seat_number ASC"
    );
    $assignmentsStmt->execute($requestIds);

    foreach ($assignmentsStmt->fetchAll(PDO::FETCH_ASSOC) as $assignment) {
        $name = trim(trim((string) ($assignment['prenom'] ?? '')) . ' ' . trim((string) ($assignment['nom'] ?? '')));
        if ($name === '') {
            $name = trim((string) ($assignment['external_name'] ?? ''));
        }

        $agency = trim((string) ($assignment['interim'] ?? ''));
        if ($agency === '') {
            $agency = trim((string) ($assignment['agency_name'] ?? ''));
        }

        $assignmentsByRequest[(int) $assignment['request_id']][] = [
            'name' => $name !== '' ? $name : fjvhT('Sans nom', 'Naamloos'),
            'agency' => $agency,
            'is_famiflora' => famijobIsFamifloraAgency($agency),
            'is_account' => (int) ($assignment['student_id'] ?? 0) > 0,
        ];
    }
}

// --- Regroupement département -> jour, et créneaux analysés une fois pour toutes ---
$byDeptDay = [];
$departmentsInView = [];
$deptStats = [];
$scaleMin = null;
$scaleMax = null;
$assignedCount = 0;
$openCount = 0;

foreach ($requests as $request) {
    $departmentName = (string) $request['department_name'];
    if ($selectedDepartment !== 'all' && $departmentName !== $selectedDepartment) {
        continue;
    }

    $slot = famijobParseTimeSlot($request['time_slot']);
    $request['slot'] = $slot;

    if ($slot['is_parsed']) {
        $scaleMin = $scaleMin === null ? $slot['start'] : min($scaleMin, $slot['start']);
        $scaleMax = $scaleMax === null ? $slot['end'] : max($scaleMax, $slot['end']);
    }

    $departmentsInView[$departmentName] = true;
    $byDeptDay[$departmentName][(string) $request['shift_date']][] = $request;

    if (!isset($deptStats[$departmentName])) {
        $deptStats[$departmentName] = ['people' => 0, 'minutes' => 0, 'open' => 0];
    }

    $people = count($assignmentsByRequest[(int) $request['id']] ?? []);
    if ($people === 0) {
        $deptStats[$departmentName]['open']++;
        $openCount++;
    } else {
        $deptStats[$departmentName]['people'] += $people;
        $assignedCount += $people;
        if ($slot['duration'] !== null) {
            $deptStats[$departmentName]['minutes'] += $slot['duration'] * $people;
        }
    }
}

$departmentsInView = array_keys($departmentsInView);
sort($departmentsInView, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($byDeptDay as $departmentName => $byDay) {
    foreach ($byDay as $dateKey => $list) {
        usort($list, static function ($a, $b) {
            $sa = $a['slot']['start'];
            $sb = $b['slot']['start'];
            if ($sa === null && $sb === null) {
                return strcmp((string) $a['time_slot'], (string) $b['time_slot']);
            }
            if ($sa === null) {
                return 1;
            }
            if ($sb === null) {
                return -1;
            }
            return $sa <=> $sb;
        });
        $byDeptDay[$departmentName][$dateKey] = $list;
    }
}

// --- Règle horaire commune à toute la grille ---
// Arrondie à l'heure pleine pour que les graduations tombent juste, et large
// d'au moins 4 h pour qu'une semaine à créneau unique ne donne pas une barre
// pleine largeur (visuellement fausse : elle suggérerait une journée entière).
if ($scaleMin === null || $scaleMax === null || $scaleMax <= $scaleMin) {
    $scaleMin = 8 * 60;
    $scaleMax = 20 * 60;
}
$scaleMin = intdiv($scaleMin, 60) * 60;
$scaleMax = (int) (ceil($scaleMax / 60) * 60);
if ($scaleMax - $scaleMin < 240) {
    $scaleMax = $scaleMin + 240;
}
$scaleRange = max(1, $scaleMax - $scaleMin);
$gridStepPercent = round((120 / $scaleRange) * 100, 4); // une graduation toutes les 2 h
$scaleMidpoint = $scaleMin + intdiv($scaleRange, 2);

/**
 * Position et largeur d'une barre, en pourcentage de la règle commune.
 * Un créneau illisible occupe toute la largeur en pointillés : mieux vaut
 * signaler « on ne sait pas » que dessiner une durée inventée.
 */
function fjvhBarGeometry(array $slot, $scaleMin, $scaleRange)
{
    if (!$slot['is_parsed']) {
        return ['left' => 0.0, 'width' => 100.0, 'unknown' => true];
    }

    $left = (($slot['start'] - $scaleMin) / $scaleRange) * 100;
    $width = ($slot['duration'] / $scaleRange) * 100;

    $left = max(0.0, min(100.0, $left));
    $width = max(3.0, min(100.0 - $left, $width)); // 3 % : reste visible même sur 1 h

    return ['left' => round($left, 3), 'width' => round($width, 3), 'unknown' => false];
}

$totalMinutes = 0;
foreach ($deptStats as $stat) {
    $totalMinutes += $stat['minutes'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo e($pageLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(fjvhT('Vue horaire - FamiJob', 'Uurroosterweergave - FamiJob')); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="famijob_.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7f6;
            --card: #ffffff;
            --line: #dbe5de;
            --text: #21362a;
            --muted: #64756a;
            --accent: #2d5a37;
            --accent-soft: #edf5ef;
            --interim: #2f6f7d;
            --interim-soft: #e9f3f5;
            --open: #b9762a;
            --open-soft: #fdf3e6;
            --shadow: 0 14px 34px rgba(22, 49, 33, 0.1);
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px; background: var(--bg); font-family: 'Open Sans', sans-serif; color: var(--text); }
        .page { max-width: 1720px; margin: 0 auto; }
        .hero { background: linear-gradient(135deg, #264e35, #3f6b4d); color: #fff; border-radius: 24px; padding: 24px 28px; box-shadow: var(--shadow); margin-bottom: 18px; }
        .hero-top { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .hero h1 { margin: 8px 0 6px; font-size: 2rem; }
        .hero p { margin: 0; opacity: 0.95; line-height: 1.6; max-width: 920px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #fff; text-decoration: none; font-weight: 700; background: rgba(255,255,255,0.14); padding: 12px 18px; border-radius: 999px; }
        .toolbar { display: flex; justify-content: space-between; align-items: end; gap: 16px; margin-bottom: 14px; padding: 18px 22px; background: #fff; border-radius: 22px; box-shadow: var(--shadow); flex-wrap: wrap; }
        .toolbar form { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; }
        label { display: block; margin-bottom: 6px; font-size: 0.82rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #cfdad3; border-radius: 12px; padding: 10px 11px; font-size: 0.95rem; font-family: inherit; background: #fff; }
        .btn { border: none; border-radius: 12px; padding: 10px 14px; font-weight: 700; cursor: pointer; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 7px; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-soft { background: var(--accent-soft); color: var(--accent); border: 1px solid #d3e5d9; }
        .legend { color: var(--muted); font-size: 0.86rem; }

        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .summary .chip { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 9px 14px; box-shadow: 0 6px 16px rgba(22,49,33,.06); }
        .summary .chip b { display: block; font-size: 1.15rem; color: var(--accent); line-height: 1.2; }
        .summary .chip span { font-size: 0.74rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }

        .keys { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; font-size: 0.82rem; color: var(--muted); }
        .key { display: inline-flex; align-items: center; gap: 7px; }
        .key i { width: 26px; height: 11px; border-radius: 6px; display: inline-block; }
        .key i.k-fami { background: linear-gradient(90deg, #2d5a37, #4d8a5e); }
        .key i.k-interim { background: linear-gradient(90deg, #2f6f7d, #4d9dad); }
        .key i.k-open { background: repeating-linear-gradient(45deg, #f0c489, #f0c489 4px, #fbe6cb 4px, #fbe6cb 8px); }

        .table-wrap { overflow-x: auto; background: #fff; border-radius: 22px; box-shadow: var(--shadow); }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1500px; }
        th, td { border-bottom: 1px solid var(--line); border-right: 1px solid var(--line); padding: 10px; vertical-align: top; text-align: left; }
        th:last-child, td:last-child { border-right: none; }
        th { background: #f8fbf9; font-size: 0.78rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); position: sticky; top: 0; z-index: 2; }
        th.is-today { background: #eef7f0; color: var(--accent); }
        td.is-today { background: #fbfefc; }

        .corner { min-width: 210px; width: 210px; background: #f8fbf9; position: sticky; left: 0; z-index: 3; }
        .dept-cell { background: #fbfdfb; position: sticky; left: 0; z-index: 1; min-width: 210px; width: 210px; box-shadow: 6px 0 10px -8px rgba(22,49,33,.28); }
        .dept-name { color: #244132; font-weight: 700; font-size: 0.98rem; line-height: 1.3; }
        .dept-meta { margin-top: 5px; color: var(--muted); font-size: 0.78rem; line-height: 1.5; }
        .dept-meta b { color: var(--accent); }

        .day-head { line-height: 1.35; }
        .day-head .date { color: var(--muted); font-weight: 600; text-transform: none; letter-spacing: 0; display: block; margin-top: 2px; }
        .ruler { display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.68rem; font-weight: 700; color: #93a49a; letter-spacing: 0; text-transform: none; }
        .ruler span:nth-child(2) { opacity: .75; }

        .slot { margin-bottom: 9px; }
        .slot:last-child { margin-bottom: 0; }
        .slot-head { display: flex; align-items: baseline; gap: 8px; margin-bottom: 3px; }
        .slot-name { font-weight: 700; font-size: 0.84rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1 1 auto; min-width: 0; }
        .slot-hours { font-size: 0.72rem; font-weight: 700; color: var(--muted); white-space: nowrap; flex: none; }
        .slot-sub { font-size: 0.7rem; color: #8d9d94; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .track {
            position: relative; height: 15px; border-radius: 8px; background: #eef3f0;
            background-image: repeating-linear-gradient(to right, rgba(45,90,55,.16) 0, rgba(45,90,55,.16) 1px, transparent 1px, transparent <?php echo $gridStepPercent; ?>%);
            overflow: hidden;
        }
        .bar { position: absolute; top: 0; bottom: 0; border-radius: 8px; box-shadow: inset 0 -1px 0 rgba(0,0,0,.08); }
        .bar.b-fami { background: linear-gradient(90deg, #2d5a37, #4d8a5e); }
        .bar.b-interim { background: linear-gradient(90deg, #2f6f7d, #4d9dad); }
        .bar.b-open { background: repeating-linear-gradient(45deg, #f0c489, #f0c489 4px, #fbe6cb 4px, #fbe6cb 8px); border: 1px dashed #d79a4d; }
        .bar.b-unknown { background: repeating-linear-gradient(45deg, #dfe6e2, #dfe6e2 4px, #eef2f0 4px, #eef2f0 8px); border: 1px dashed #b9c6bf; }
        .bar-label { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 0.62rem; font-weight: 800; color: #fff; letter-spacing: .02em; white-space: nowrap; overflow: hidden; }
        .bar.b-open .bar-label, .bar.b-unknown .bar-label { color: #8a5b1e; }

        .slot-empty { color: #b3c0b8; font-size: 0.9rem; padding-top: 4px; }
        .empty-state { background: #fff; border-radius: 22px; padding: 28px; box-shadow: var(--shadow); color: var(--muted); }

        .fami-lang-switcher { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.16); border: 1px solid rgba(255,255,255,0.26); border-radius: 999px; padding: 4px; }
        .fami-lang-option { display: inline-block; text-decoration: none; color: #ffffff; font-weight: 800; font-size: 0.78rem; letter-spacing: 0.04em; padding: 5px 9px; border-radius: 999px; }
        .fami-lang-option.is-active { background: #ffffff; color: var(--accent); }

        @media (max-width: 860px) {
            body { padding: 12px; }
            .hero { padding: 18px; }
            .corner, .dept-cell { position: static; box-shadow: none; }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . "/includes/topbar.php"; famijobRibbon($db); ?>
<div class="page">
    <div class="hero">
        <div class="hero-top">
            <div>
                <a href="index.php" class="back-link">← <?php echo e(fjvhT('Retour FamiJob', 'Terug naar FamiJob')); ?></a>
                <?php echo famiRenderLanguageSwitcher(); ?>
                <h1><?php echo e(fjvhT('Vue horaire', 'Uurroosterweergave')); ?></h1>
                <p><?php echo e(fjvhT('Le planning de la semaine, à l\'échelle : chaque barre a la longueur de sa prestation. Page en consultation seule.', 'De weekplanning op schaal: elke balk is zo lang als de prestatie duurt. Alleen-lezen pagina.')); ?></p>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <form method="get" action="">
            <div>
                <label for="week"><?php echo e(fjvhT('Semaine', 'Week')); ?></label>
                <select id="week" name="week">
                    <?php foreach ($weekOptions as $weekKey => $weekInfo): ?>
                        <option value="<?php echo e($weekKey); ?>" <?php echo $weekKey === $selectedWeekKey ? 'selected' : ''; ?>><?php echo e($weekInfo['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="department"><?php echo e(fjvhT('Département', 'Afdeling')); ?></label>
                <select id="department" name="department">
                    <option value="all" <?php echo $selectedDepartment === 'all' ? 'selected' : ''; ?>><?php echo e(fjvhT('Tous les départements', 'Alle afdelingen')); ?></option>
                    <?php foreach ($departmentFilterOptions as $departmentName): ?>
                        <option value="<?php echo e($departmentName); ?>" <?php echo $selectedDepartment === $departmentName ? 'selected' : ''; ?>><?php echo e($departmentName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit"><?php echo e(fjvhT('Afficher', 'Tonen')); ?></button>
            <a class="btn btn-soft" href="envoi_horaires.php?week=<?php echo e($selectedWeekKey); ?>">✉️ <?php echo e(fjvhT('Envoyer les horaires', 'Roosters versturen')); ?></a>
            <a class="btn btn-soft" href="export_matching.php?week=<?php echo e($selectedWeekKey); ?>">⬇️ <?php echo e(fjvhT('Export Excel', 'Excel-export')); ?></a>
        </form>
        <div class="keys">
            <span class="key"><i class="k-fami"></i> <?php echo e(fjvhT('Famiflora', 'Famiflora')); ?></span>
            <span class="key"><i class="k-interim"></i> <?php echo e(fjvhT('Intérim', 'Interim')); ?></span>
            <span class="key"><i class="k-open"></i> <?php echo e(fjvhT('Non pourvu', 'Niet ingevuld')); ?></span>
            <span class="legend"><?php echo e(fjvhT('Règle commune : ', 'Gemeenschappelijke schaal: ')); ?><?php echo e(famijobFormatMinutes($scaleMin)); ?> → <?php echo e(famijobFormatMinutes($scaleMax)); ?><?php echo e(fjvhT(' · graduation toutes les 2 h', ' · streepje elke 2 u')); ?></span>
        </div>
    </div>

    <?php if (!empty($departmentsInView)): ?>
    <div class="summary">
        <div class="chip"><b><?php echo (int) $assignedCount; ?></b><span><?php echo e(fjvhT('Affectations', 'Toewijzingen')); ?></span></div>
        <div class="chip"><b><?php echo e(famijobFormatDuration($totalMinutes)); ?></b><span><?php echo e(fjvhT('Heures planifiées', 'Geplande uren')); ?></span></div>
        <div class="chip"><b><?php echo (int) $openCount; ?></b><span><?php echo e(fjvhT('Créneaux non pourvus', 'Niet ingevulde slots')); ?></span></div>
        <div class="chip"><b><?php echo count($departmentsInView); ?></b><span><?php echo e(fjvhT('Départements', 'Afdelingen')); ?></span></div>
    </div>
    <?php endif; ?>

    <?php if (empty($departmentsInView)): ?>
        <div class="empty-state"><?php echo e(fjvhT('Aucun créneau trouvé pour cette semaine.', 'Geen tijdsblok gevonden voor deze week.')); ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="corner"><?php echo e(fjvhT('Département', 'Afdeling')); ?></th>
                        <?php foreach ($weekDays as $day): ?>
                            <th class="<?php echo $day['is_today'] ? 'is-today' : ''; ?>">
                                <div class="day-head"><?php echo e($day['label']); ?><span class="date"><?php echo e($day['date']); ?></span></div>
                                <div class="ruler">
                                    <span><?php echo e(famijobFormatMinutes($scaleMin)); ?></span>
                                    <span><?php echo e(famijobFormatMinutes($scaleMidpoint)); ?></span>
                                    <span><?php echo e(famijobFormatMinutes($scaleMax)); ?></span>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departmentsInView as $departmentName): ?>
                        <?php $stat = $deptStats[$departmentName] ?? ['people' => 0, 'minutes' => 0, 'open' => 0]; ?>
                        <tr>
                            <td class="dept-cell">
                                <div class="dept-name"><?php echo e($departmentName); ?></div>
                                <div class="dept-meta">
                                    <b><?php echo (int) $stat['people']; ?></b> <?php echo e(fjvhT('affectations', 'toewijzingen')); ?> ·
                                    <b><?php echo e(famijobFormatDuration($stat['minutes'])); ?></b><br>
                                    <?php if ($stat['open'] > 0): ?>
                                        <span style="color:#b9762a;font-weight:700;"><?php echo (int) $stat['open']; ?> <?php echo e(fjvhT('non pourvu(s)', 'niet ingevuld')); ?></span>
                                    <?php else: ?>
                                        <span style="color:#4d8a5e;font-weight:700;"><?php echo e(fjvhT('Complet', 'Volledig')); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php foreach ($weekDays as $day): ?>
                                <?php $dayRequests = $byDeptDay[$departmentName][$day['key']] ?? []; ?>
                                <td class="<?php echo $day['is_today'] ? 'is-today' : ''; ?>">
                                    <?php if (empty($dayRequests)): ?>
                                        <div class="slot-empty">—</div>
                                    <?php else: ?>
                                        <?php foreach ($dayRequests as $request): ?>
                                            <?php
                                            $slot = $request['slot'];
                                            $geometry = fjvhBarGeometry($slot, $scaleMin, $scaleRange);
                                            $hoursLabel = $slot['is_parsed'] ? $slot['label'] : ($slot['raw'] !== '' ? $slot['raw'] : '?');
                                            $durationLabel = famijobFormatDuration($slot['duration']);
                                            $requestAssignments = $assignmentsByRequest[(int) $request['id']] ?? [];
                                            $comment = trim((string) ($request['comment'] ?? ''));
                                            ?>
                                            <?php if (empty($requestAssignments)): ?>
                                                <?php
                                                $tooltip = $departmentName . ' — ' . $hoursLabel . ' (' . $durationLabel . ') — '
                                                    . fjvhT('créneau non pourvu', 'niet ingevuld slot')
                                                    . ($comment !== '' ? ' — ' . $comment : '');
                                                ?>
                                                <div class="slot" title="<?php echo e($tooltip); ?>">
                                                    <div class="slot-head">
                                                        <span class="slot-name" style="color:#b9762a;"><?php echo e(fjvhT('À pourvoir', 'In te vullen')); ?></span>
                                                        <span class="slot-hours"><?php echo e($hoursLabel); ?></span>
                                                    </div>
                                                    <div class="track">
                                                        <div class="bar <?php echo $geometry['unknown'] ? 'b-unknown' : 'b-open'; ?>" style="left:<?php echo $geometry['left']; ?>%;width:<?php echo $geometry['width']; ?>%;">
                                                            <span class="bar-label"><?php echo e($geometry['unknown'] ? '?' : $durationLabel); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($requestAssignments as $assignment): ?>
                                                    <?php
                                                    $agencyLabel = $assignment['agency'] !== '' ? $assignment['agency'] : 'Famiflora';
                                                    $tooltip = $assignment['name'] . ' — ' . $agencyLabel . "\n"
                                                        . $departmentName . ' — ' . $hoursLabel . ' (' . $durationLabel . ')'
                                                        . ($slot['note'] !== '' ? "\n" . fjvhT('Note du créneau : ', 'Notitie: ') . $slot['note'] : '')
                                                        . ($comment !== '' ? "\n" . $comment : '');
                                                    $barClass = $assignment['is_famiflora'] ? 'b-fami' : 'b-interim';
                                                    if ($geometry['unknown']) {
                                                        $barClass = 'b-unknown';
                                                    }
                                                    ?>
                                                    <div class="slot" title="<?php echo e($tooltip); ?>">
                                                        <div class="slot-head">
                                                            <span class="slot-name"><?php echo e($assignment['name']); ?></span>
                                                            <span class="slot-hours"><?php echo e($hoursLabel); ?></span>
                                                        </div>
                                                        <div class="track">
                                                            <div class="bar <?php echo $barClass; ?>" style="left:<?php echo $geometry['left']; ?>%;width:<?php echo $geometry['width']; ?>%;">
                                                                <span class="bar-label"><?php echo e($geometry['unknown'] ? '?' : $durationLabel); ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="slot-sub"><?php echo e($agencyLabel); ?><?php echo $slot['note'] !== '' ? ' · ' . e($slot['note']) : ''; ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
