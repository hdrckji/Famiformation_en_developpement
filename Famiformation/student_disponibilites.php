<?php
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/quizz_status.php';

$role = getCurrentRole();
$userId = getCurrentUserId();

if ($role !== 'etudiant') {
    header('Location: index.php');
    exit();
}

if (!hasUnlockedOnboarding($userId) || !hasCompletedOnboarding($userId)) {
    header('Location: index.php');
    exit();
}

ensureStudentAvailabilityTable($db);

$statusLabels = [
    'non_renseigne' => 'Non renseigné',
    'indisponible' => 'Indisponible',
    'apres_midi' => 'Disponible l’après-midi uniquement (à partir de 13h)',
    'journee' => 'Disponible toute la journée',
];

$weekdayLabels = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',
    'Sunday' => 'Dimanche',
];

$message = '';
$today = new DateTimeImmutable('today');
$lockBoundary = $today->modify('+14 days');
$endDate = $today->modify('last day of next month');

$monthNames = [
    '01' => 'janvier',
    '02' => 'fevrier',
    '03' => 'mars',
    '04' => 'avril',
    '05' => 'mai',
    '06' => 'juin',
    '07' => 'juillet',
    '08' => 'aout',
    '09' => 'septembre',
    '10' => 'octobre',
    '11' => 'novembre',
    '12' => 'decembre',
];

$fetchExistingByDate = static function (PDO $db, int $userId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array {
    $stmt = $db->prepare("SELECT availability_date, availability_status, note FROM student_availabilities WHERE user_id = ? AND availability_date BETWEEN ? AND ? ORDER BY availability_date ASC");
    $stmt->execute([$userId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $existingByDate = [];
    foreach ($rows as $row) {
        if (($row['availability_status'] ?? '') === 'matin') {
            $row['availability_status'] = 'non_renseigne';
        }
        $existingByDate[$row['availability_date']] = $row;
    }

    return $existingByDate;
};

$existingByDate = $fetchExistingByDate($db, $userId, $today, $endDate);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_availabilities'])) {
    requireValidCSRF();

    $dates = $_POST['dates'] ?? [];
    $statuses = $_POST['availability'] ?? [];
    $notes = $_POST['notes'] ?? [];
    $lockedChangesIgnored = false;

    $allowedStatuses = array_keys($statusLabels);
    $upsertStmt = $db->prepare(
        "INSERT INTO student_availabilities (user_id, availability_date, availability_status, note)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE availability_status = VALUES(availability_status), note = VALUES(note), updated_at = CURRENT_TIMESTAMP"
    );
    $deleteStmt = $db->prepare("DELETE FROM student_availabilities WHERE user_id = ? AND availability_date = ?");

    foreach ($dates as $date) {
        $date = trim((string) $date);
        $selectedStatus = trim((string) ($statuses[$date] ?? 'non_renseigne'));
        $note = trim((string) ($notes[$date] ?? ''));

        $dateObject = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dateObject) {
            continue;
        }

        if ($dateObject < $today || $dateObject > $endDate) {
            continue;
        }

        $existingRow = $existingByDate[$date] ?? null;
        $hasLockedContent = $existingRow
            && (
                (($existingRow['availability_status'] ?? 'non_renseigne') !== 'non_renseigne')
                || trim((string) ($existingRow['note'] ?? '')) !== ''
            );
        $isLockedDate = $dateObject <= $lockBoundary && $hasLockedContent;

        if ($isLockedDate) {
            if (array_key_exists($date, $statuses) || array_key_exists($date, $notes)) {
                $lockedChangesIgnored = true;
            }
            continue;
        }

        if (!in_array($selectedStatus, $allowedStatuses, true)) {
            $selectedStatus = 'non_renseigne';
        }

        if ($selectedStatus === 'non_renseigne' && $note === '') {
            $deleteStmt->execute([$userId, $date]);
            continue;
        }

        $upsertStmt->execute([$userId, $date, $selectedStatus, $note !== '' ? substr($note, 0, 255) : null]);
    }

    $message = "<div class='alert success'>✅ Tes disponibilités ont été enregistrées.</div>";
    if ($lockedChangesIgnored) {
        $message .= "<div class='alert warning'>⚠️ Certaines dates déjà renseignées dans les 15 prochains jours sont verrouillées et n'ont pas été modifiées. Pour un changement de planning sur cette période, il faut envoyer un mail à Honorine.</div>";
    }

    $existingByDate = $fetchExistingByDate($db, $userId, $today, $endDate);
}

$monthSections = [];
$currentDate = $today;
while ($currentDate <= $endDate) {
    $dateKey = $currentDate->format('Y-m-d');
    $monthKey = $currentDate->format('Y-m');
    $monthNumber = $currentDate->format('m');
    $monthTitle = ucfirst($monthNames[$monthNumber] ?? $currentDate->format('F')) . ' ' . $currentDate->format('Y');
    $existingRow = $existingByDate[$dateKey] ?? ['availability_status' => 'non_renseigne', 'note' => ''];
    $hasLockedContent = ($existingRow['availability_status'] ?? 'non_renseigne') !== 'non_renseigne'
        || trim((string) ($existingRow['note'] ?? '')) !== '';

    if (!isset($monthSections[$monthKey])) {
        $monthSections[$monthKey] = [
            'title' => $monthTitle,
            'short_title' => $currentDate->format('m/Y'),
            'days' => [],
        ];
    }

    $monthSections[$monthKey]['days'][] = [
        'date' => $dateKey,
        'label' => $currentDate->format('d/m/Y'),
        'weekday' => $weekdayLabels[$currentDate->format('l')] ?? $currentDate->format('l'),
        'status' => $existingRow['availability_status'] ?? 'non_renseigne',
        'note' => $existingRow['note'] ?? '',
        'is_locked' => $currentDate <= $lockBoundary && $hasLockedContent,
    ];
    $currentDate = $currentDate->modify('+1 day');
}

$defaultMonthKey = array_key_first($monthSections);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes disponibilités - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-dark: #214b35;
            --green-main: #2d5a37;
            --green-soft: #edf6ef;
            --gold: #d6a21a;
            --bg: #f4f7f6;
            --border: #d8e3db;
            --text-soft: #5f6e63;
        }
        body {
            margin: 0;
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(180deg, rgba(244,247,246,0.92), rgba(244,247,246,0.98)), url('background.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #223028;
        }
        .page {
            max-width: 1280px;
            margin: 0 auto;
            padding: 28px 28px 40px;
        }
        .hero {
            background: linear-gradient(135deg, rgba(33,75,53,0.95), rgba(74,123,85,0.92));
            color: #fff;
            border-radius: 26px;
            padding: 28px 34px;
            box-shadow: 0 18px 40px rgba(31,56,40,0.18);
            margin-bottom: 24px;
        }
        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .hero h1 {
            margin: 10px 0 8px;
            font-size: 2rem;
        }
        .hero p {
            margin: 0;
            max-width: 780px;
            line-height: 1.65;
            opacity: 0.94;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            background: rgba(255,255,255,0.14);
            padding: 12px 18px;
            border-radius: 999px;
        }
        .toolbar {
            background: #fff;
            border-radius: 22px;
            padding: 18px 22px;
            box-shadow: 0 10px 28px rgba(35,57,43,0.08);
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
        }
        .toolbar strong {
            color: var(--green-dark);
        }
        .legend {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .legend span {
            background: var(--green-soft);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 0.88rem;
            color: var(--green-main);
        }
        .alert {
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 18px;
            font-weight: 700;
        }
        .success {
            background: #dff3e3;
            color: #1d6a39;
        }
        .warning {
            background: #fff4db;
            color: #7a5b08;
        }
        .sheet-switcher {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .sheet-tab {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--green-main);
            border-radius: 999px;
            padding: 11px 18px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(35,57,43,0.06);
        }
        .sheet-tab.active {
            background: var(--green-main);
            color: #fff;
            border-color: var(--green-main);
        }
        .card {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 12px 34px rgba(35,57,43,0.08);
            overflow: hidden;
            margin-bottom: 18px;
        }
        .sheet-panel {
            display: none;
        }
        .sheet-panel.active {
            display: block;
        }
        .sheet-head {
            padding: 18px 22px;
            border-bottom: 1px solid #edf1ee;
            background: #f8fbf9;
        }
        .sheet-title {
            margin: 0;
            color: var(--green-dark);
            font-size: 1.15rem;
        }
        .sheet-subtitle {
            margin-top: 6px;
            color: var(--text-soft);
            font-size: 0.92rem;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
        }
        thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fbf9;
            color: var(--text-soft);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.04em;
            padding: 16px 18px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 15px 18px;
            border-bottom: 1px solid #edf1ee;
            vertical-align: middle;
        }
        tbody tr:hover {
            background: #fbfdfb;
        }
        .sticky-date {
            position: sticky;
            left: 0;
            background: inherit;
            z-index: 1;
            min-width: 180px;
        }
        thead .sticky-date {
            z-index: 3;
            background: #f8fbf9;
        }
        .date-main {
            font-weight: 700;
            color: var(--green-dark);
        }
        .date-sub {
            display: block;
            font-size: 0.9rem;
            color: var(--text-soft);
            margin-top: 4px;
        }
        select, input[type="text"] {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 11px 12px;
            font: inherit;
            background: #fff;
        }
        .status-select.non_renseigne { border-color: #d8e3db; background: #fff; }
        .status-select.indisponible { border-color: #e7c7c4; background: #fff5f4; color: #a03e34; }
        .status-select.apres_midi { border-color: #ecdca9; background: #fff9ea; color: #8b6400; }
        .status-select.journee { border-color: #cfe2d3; background: #f0f8f2; color: #1d6a39; }
        .locked-row {
            background: #faf7ef;
        }
        .locked-row:hover {
            background: #faf7ef;
        }
        .locked-badge {
            display: inline-flex;
            align-items: center;
            margin-top: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #fff0c8;
            color: #7a5b08;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .readonly-note {
            background: #f7f4ec;
            color: #6d6555;
        }
        .save-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 18px 22px;
            background: #fcfdfc;
            border-top: 1px solid #edf1ee;
        }
        .save-note {
            color: var(--text-soft);
            line-height: 1.55;
        }
        .btn-save {
            border: none;
            background: linear-gradient(135deg, var(--gold), #bd8f11);
            color: #fff;
            font-weight: 700;
            padding: 14px 22px;
            border-radius: 999px;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(189,143,17,0.18);
        }
        @media (max-width: 900px) {
            .page { padding: 18px 14px 28px; }
            .hero {
                padding: 22px 18px;
                border-radius: 20px;
            }
            .hero h1 {
                font-size: 1.65rem;
            }
            .hero-top, .toolbar, .save-bar { flex-direction: column; align-items: flex-start; }
            .toolbar,
            .save-bar,
            .sheet-head {
                padding: 16px;
            }
            .sheet-switcher {
                padding: 0 12px 12px;
                margin-bottom: 0;
            }
            .sheet-tab {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
        }
        @media (max-width: 700px) {
            .page {
                padding: 12px 10px 22px;
            }
            .hero h1 {
                font-size: 1.45rem;
            }
            .hero p,
            .save-note,
            .sheet-subtitle,
            .toolbar div,
            .legend span {
                font-size: 0.92rem;
            }
            .back-link,
            .btn-save {
                width: 100%;
                box-sizing: border-box;
                justify-content: center;
                text-align: center;
            }
            .legend {
                width: 100%;
            }
            .legend span {
                width: 100%;
                box-sizing: border-box;
                text-align: center;
            }
            .table-wrap {
                overflow: visible;
            }
            table,
            thead,
            tbody,
            tr,
            th,
            td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
            table {
                min-width: 0;
            }
            thead {
                display: none;
            }
            tbody {
                padding: 12px;
            }
            tbody tr {
                background: #fff;
                border: 1px solid #edf1ee;
                border-radius: 16px;
                padding: 14px;
                margin-bottom: 12px;
                box-shadow: 0 6px 18px rgba(35,57,43,0.05);
            }
            tbody tr:hover {
                background: #fff;
            }
            tbody td {
                padding: 0;
                border-bottom: none;
            }
            .sticky-date {
                position: static;
                min-width: 0;
                margin-bottom: 12px;
            }
            .mobile-field {
                margin-top: 12px;
            }
            .mobile-field-label {
                display: block;
                font-size: 0.78rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: var(--text-soft);
                margin-bottom: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="hero">
            <div class="hero-top">
                <div>
                    <div style="text-transform:uppercase;letter-spacing:.08em;font-size:.82rem;opacity:.85;">Espace étudiant</div>
                    <h1>Mes disponibilités de travail</h1>
                </div>
                <a href="index.php" class="back-link">⬅ Retour à l’accueil</a>
            </div>
            <p>Renseigne ici tes disponibilités pour le mois en cours et le mois suivant. Ces informations serviront à préparer l’organisation des prochaines semaines. Tu peux préciser si tu es disponible toute la journée, uniquement l’après-midi à partir de 13h, ou indisponible.</p>
        </div>

        <div class="toolbar">
            <div>
                <strong>Période couverte :</strong> du <?php echo $today->format('d/m/Y'); ?> au <?php echo $endDate->format('d/m/Y'); ?><br>
                <span style="color: var(--text-soft);">2 feuilles disponibles : mois en cours et mois +1.</span>
            </div>
            <div class="legend">
                <span>Journée complète</span>
                <span>Après-midi uniquement à partir de 13h</span>
                <span>Indisponible</span>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="alert warning">⚠️ Une date déjà renseignée dans les 15 prochains jours devient verrouillée. Si un changement de planning doit être effectué sur cette période, il ne peut se faire qu’avec l’envoi d’un mail à Honorine.</div>

        <form method="POST" class="card">
            <?php echo csrfField(); ?>
            <div class="save-bar" style="justify-content: center; padding: 20px 22px; border-bottom: 1px solid #edf1ee;">
                <button type="submit" name="save_availabilities" class="btn-save" style="margin: 0;">Enregistrer mes disponibilités</button>
            </div>
            <div class="sheet-switcher">
                <?php foreach ($monthSections as $monthKey => $section): ?>
                    <button type="button" class="sheet-tab <?php echo $monthKey === $defaultMonthKey ? 'active' : ''; ?>" data-sheet-target="sheet-<?php echo htmlspecialchars($monthKey); ?>"><?php echo htmlspecialchars($section['title']); ?></button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($monthSections as $monthKey => $section): ?>
                <div class="sheet-panel <?php echo $monthKey === $defaultMonthKey ? 'active' : ''; ?>" id="sheet-<?php echo htmlspecialchars($monthKey); ?>">
                    <div class="sheet-head">
                        <h2 class="sheet-title"><?php echo htmlspecialchars($section['title']); ?></h2>
                        <div class="sheet-subtitle"><?php echo $monthKey === $defaultMonthKey ? 'Feuille active par défaut.' : 'Feuille du mois suivant.'; ?></div>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th class="sticky-date">Date</th>
                                    <th style="width:280px;">Disponibilité</th>
                                    <th>Précision optionnelle</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($section['days'] as $day): ?>
                                <tr class="<?php echo $day['is_locked'] ? 'locked-row' : ''; ?>">
                                    <td class="sticky-date">
                                        <span class="date-main"><?php echo htmlspecialchars($day['label']); ?></span>
                                        <span class="date-sub"><?php echo htmlspecialchars($day['weekday']); ?></span>
                                        <?php if ($day['is_locked']): ?>
                                            <span class="locked-badge">Verrouillée</span>
                                        <?php endif; ?>
                                        <input type="hidden" name="dates[]" value="<?php echo htmlspecialchars($day['date']); ?>">
                                    </td>
                                    <td class="mobile-field">
                                        <span class="mobile-field-label">Disponibilité</span>
                                        <select name="availability[<?php echo htmlspecialchars($day['date']); ?>]" class="status-select <?php echo htmlspecialchars($day['status']); ?>" onchange="this.className='status-select ' + this.value;" <?php echo $day['is_locked'] ? 'disabled' : ''; ?>>
                                            <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
                                                <option value="<?php echo htmlspecialchars($statusKey); ?>" <?php echo $day['status'] === $statusKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="mobile-field">
                                        <span class="mobile-field-label">Précision optionnelle</span>
                                        <input type="text" name="notes[<?php echo htmlspecialchars($day['date']); ?>]" value="<?php echo htmlspecialchars($day['note']); ?>" maxlength="255" placeholder="Exemple : disponible après 14h, indisponible jusque 13h, etc." <?php echo $day['is_locked'] ? 'readonly class="readonly-note"' : ''; ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="save-bar">
                <div class="save-note">Les disponibilités des semaines à venir seront compilées automatiquement et transmises à l’équipe RH. Les dates déjà renseignées dans les 15 prochains jours restent verrouillées depuis cette page.</div>
                <button type="submit" name="save_availabilities" class="btn-save">Enregistrer mes disponibilités</button>
            </div>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var tabs = document.querySelectorAll('.sheet-tab');
        var panels = document.querySelectorAll('.sheet-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var targetId = tab.getAttribute('data-sheet-target');

                tabs.forEach(function (item) {
                    item.classList.remove('active');
                });
                panels.forEach(function (panel) {
                    panel.classList.remove('active');
                });

                tab.classList.add('active');
                var target = document.getElementById(targetId);
                if (target) {
                    target.classList.add('active');
                }
            });
        });
    });
    </script>
</body>
</html>