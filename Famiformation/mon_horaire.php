<?php
require_once 'config.php';
verifierConnexion($db);

$pageLang = famiLang();
if (!function_exists('monHoraireT')) {
    function monHoraireT($fr, $nl = null)
    {
        return famiLang() === 'nl' && $nl !== null ? $nl : $fr;
    }
}

$role = $_SESSION['role'] ?? '';
if ($role !== 'etudiant') {
    header('Location: index.php');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

$db->exec(
    "CREATE TABLE IF NOT EXISTS interim_shift_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        shift_date DATE NOT NULL,
        department_name VARCHAR(120) NOT NULL,
        time_slot VARCHAR(60) NOT NULL,
        seats_required SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        comment TEXT NULL,
        validation_status VARCHAR(20) NOT NULL DEFAULT 'approved',
        validated_by_user_id INT NULL,
        validated_at DATETIME NULL,
        created_by_user_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_shift_request (shift_date, department_name, time_slot),
        INDEX idx_shift_date (shift_date),
        INDEX idx_shift_department (department_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$db->exec(
    "CREATE TABLE IF NOT EXISTS interim_shift_assignments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id INT UNSIGNED NOT NULL,
        seat_number SMALLINT UNSIGNED NOT NULL,
        student_id INT NOT NULL,
        assigned_by_user_id INT NULL,
        agency_name VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_request_seat (request_id, seat_number),
        INDEX idx_request (request_id),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$today = new DateTimeImmutable('today');
$currentWeekStart = $today->modify('monday this week');

$weekStartInput = trim((string) ($_GET['week_start'] ?? ''));
$selectedWeekStart = DateTimeImmutable::createFromFormat('Y-m-d', $weekStartInput);
if (!$selectedWeekStart instanceof DateTimeImmutable) {
    $selectedWeekStart = $currentWeekStart;
}
$selectedWeekStart = $selectedWeekStart->setTime(0, 0, 0)->modify('monday this week');
$selectedWeekEnd = $selectedWeekStart->modify('+6 days');

$prevWeekStart = $selectedWeekStart->modify('-7 days');
$nextWeekStart = $selectedWeekStart->modify('+7 days');

$rows = [];
$stmt = $db->prepare(
    "SELECT r.shift_date, r.department_name, r.time_slot, r.comment,
            a.agency_name, a.created_at
     FROM interim_shift_assignments a
     INNER JOIN interim_shift_requests r ON r.id = a.request_id
     WHERE a.student_id = ?
       AND r.shift_date BETWEEN ? AND ?
     ORDER BY r.shift_date ASC, r.time_slot ASC"
);
$stmt->execute([
    $userId,
    $selectedWeekStart->format('Y-m-d'),
    $selectedWeekEnd->format('Y-m-d'),
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rowsByDate = [];
foreach ($rows as $row) {
    $dateKey = (string) ($row['shift_date'] ?? '');
    if (!isset($rowsByDate[$dateKey])) {
        $rowsByDate[$dateKey] = [];
    }
    $rowsByDate[$dateKey][] = $row;
}

$weekDays = [];
$cursor = $selectedWeekStart;
while ($cursor <= $selectedWeekEnd) {
    $key = $cursor->format('Y-m-d');
    $weekDays[] = [
        'key' => $key,
        'label' => fmtDateFr($key),
        'items' => $rowsByDate[$key] ?? [],
    ];
    $cursor = $cursor->modify('+1 day');
}

$weekLabel = monHoraireT('Semaine du ', 'Week van ') . $selectedWeekStart->format('d/m/Y') . monHoraireT(' au ', ' tot ') . $selectedWeekEnd->format('d/m/Y');
$isCurrentWeek = $selectedWeekStart->format('Y-m-d') === $currentWeekStart->format('Y-m-d');

function fmtDateFr($dateValue)
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', (string) $dateValue);
    if (!$dt) {
        return (string) $dateValue;
    }

    $days = famiLang() === 'nl' ? [
        'Monday' => 'Maandag',
        'Tuesday' => 'Dinsdag',
        'Wednesday' => 'Woensdag',
        'Thursday' => 'Donderdag',
        'Friday' => 'Vrijdag',
        'Saturday' => 'Zaterdag',
        'Sunday' => 'Zondag',
    ] : [
        'Monday' => 'Lundi',
        'Tuesday' => 'Mardi',
        'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi',
        'Friday' => 'Vendredi',
        'Saturday' => 'Samedi',
        'Sunday' => 'Dimanche',
    ];

    $dayName = $days[$dt->format('l')] ?? $dt->format('l');
    return $dayName . ' ' . $dt->format('d/m/Y');
}

function renderScheduleRows(array $items)
{
    if (empty($items)) {
        echo '<tr><td colspan="4" class="empty-row">' . e(monHoraireT('Aucun horaire attribue.', 'Geen rooster toegewezen.')) . '</td></tr>';
        return;
    }

    foreach ($items as $item) {
        echo '<tr>';
        echo '<td>' . e((string) ($item['time_slot'] ?? '')) . '</td>';
        echo '<td>' . e((string) ($item['department_name'] ?? '')) . '</td>';
        echo '<td>' . e((string) ($item['agency_name'] ?? '')) . '</td>';
        echo '<td>' . e((string) ($item['comment'] ?? '')) . '</td>';
        echo '</tr>';
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo e($pageLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(monHoraireT('Mon horaire - FamiFormation', 'Mijn rooster - FamiFormation')); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7f5;
            --card: #ffffff;
            --line: #dbe5de;
            --text: #21362a;
            --muted: #627268;
            --accent: #2d5a37;
            --soft: #edf5ef;
            --shadow: 0 12px 32px rgba(22, 49, 33, 0.10);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 20px;
            background: var(--bg);
            font-family: 'Open Sans', sans-serif;
            color: var(--text);
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
        }

        .hero {
            background: linear-gradient(135deg, #264e35, #3f6b4d);
            color: #fff;
            border-radius: 24px;
            padding: 22px 28px;
            margin-bottom: 18px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero h1 {
            margin: 6px 0 4px;
            font-size: 1.75rem;
        }

        .hero p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.93rem;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .back-link {
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.14);
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 0.88rem;
            white-space: nowrap;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .stat {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
            box-shadow: var(--shadow);
        }

        .stat .label {
            font-size: 0.78rem;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat .value {
            margin-top: 4px;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }

        .week-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .week-nav-center {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            text-align: center;
            flex: 1;
        }

        .week-title {
            font-weight: 700;
            color: var(--text);
            font-size: 1rem;
        }

        .week-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 4px 10px;
            background: var(--soft);
            color: var(--accent);
        }

        .week-arrow {
            text-decoration: none;
            font-weight: 700;
            color: var(--accent);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            min-width: 44px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
        }

        .day-block {
            border-top: 1px solid var(--line);
        }

        .day-block:first-child {
            border-top: none;
        }

        .day-head {
            padding: 11px 16px;
            background: #fbfdfb;
            border-bottom: 1px solid var(--line);
            font-weight: 700;
            color: var(--text);
        }

        .section {
            background: var(--card);
            border-radius: 18px;
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
            margin-bottom: 16px;
            overflow: hidden;
        }

        .section-head {
            padding: 12px 16px;
            background: #f6faf7;
            border-bottom: 1px solid var(--line);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 3px 10px;
            background: var(--soft);
            color: var(--accent);
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        th, td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            font-size: 0.9rem;
            vertical-align: top;
        }

        th {
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            background: #fbfdfb;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .empty-row {
            color: var(--muted);
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="hero">
        <div>
            <a href="index.php" class="back-link">← FamiFormation</a>
            <h1><?php echo e(monHoraireT('Mon horaire', 'Mijn rooster')); ?></h1>
            <p><?php echo e(monHoraireT('Vue consultative de tes horaires attribues, semaine par semaine.', 'Leesweergave van je toegewezen uren, week per week.')); ?></p>
        </div>
    </div>

    <div class="stats">
        <div class="stat">
            <div class="label"><?php echo e(monHoraireT('Semaine affichee', 'Getoonde week')); ?></div>
            <div class="value" style="font-size:1.1rem;"><?= e($selectedWeekStart->format('d/m')) ?> - <?= e($selectedWeekEnd->format('d/m')) ?></div>
        </div>
        <div class="stat">
            <div class="label"><?php echo e(monHoraireT('Creneaux sur la semaine', 'Slots deze week')); ?></div>
            <div class="value"><?= count($rows) ?></div>
        </div>
        <div class="stat">
            <div class="label"><?php echo e(monHoraireT('Position', 'Status')); ?></div>
            <div class="value" style="font-size:1.1rem;"><?= $isCurrentWeek ? monHoraireT('Semaine en cours', 'Huidige week') : monHoraireT('Archive / prevision', 'Archief / vooruitblik') ?></div>
        </div>
    </div>

    <section class="section">
        <div class="section-head">
            <div class="week-nav" style="width:100%;">
                <a class="week-arrow" href="?week_start=<?= e($prevWeekStart->format('Y-m-d')) ?>" title="<?= e(monHoraireT('Semaine precedente', 'Vorige week')); ?>">←</a>
                <div class="week-nav-center">
                    <span class="week-title"><?= e($weekLabel) ?></span>
                    <span class="week-badge"><?= $isCurrentWeek ? monHoraireT('Semaine en cours', 'Huidige week') : monHoraireT('Consultation', 'Raadpleging') ?></span>
                </div>
                <a class="week-arrow" href="?week_start=<?= e($nextWeekStart->format('Y-m-d')) ?>" title="<?= e(monHoraireT('Semaine suivante', 'Volgende week')); ?>">→</a>
            </div>
        </div>
        <?php foreach ($weekDays as $day): ?>
        <div class="day-block">
            <div class="day-head"><?= e($day['label']) ?> <span class="badge" style="margin-left:8px;"><?= count($day['items']) ?></span></div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th><?php echo e(monHoraireT('Creneau', 'Slot')); ?></th>
                        <th><?php echo e(monHoraireT('Departement', 'Afdeling')); ?></th>
                        <th><?php echo e(monHoraireT('Agence', 'Agentschap')); ?></th>
                        <th><?php echo e(monHoraireT('Commentaire', 'Opmerking')); ?></th>
                    </tr>
                    </thead>
                    <tbody><?php renderScheduleRows($day['items']); ?></tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
</div>
</body>
</html>
