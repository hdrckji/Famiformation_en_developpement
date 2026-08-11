<?php
require_once 'config.php';
require_once __DIR__ . '/includes/notifications.php';
verifierConnexion($db);

$pageLang = famiLang();
if (!function_exists('fjhT')) {
    function fjhT($fr, $nl = null)
    {
        return famiLang() === 'nl' && $nl !== null ? $nl : $fr;
    }
}

$role = getCurrentRole();
if (!in_array($role, ['admin', 'teamcoach'], true)) {
    header('Location: ' . famijobSiteUrl('index.php'));
    exit();
}

$isAdmin = ($role === 'admin');
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

ensureDepartmentsTable($db);
ensureStudentAvailabilityTable($db);

try {
    syncDepartmentsFromPlanningDb($db);
} catch (Exception $e) {
    // On garde la liste locale si la base planning n'est pas disponible.
}

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

$requestColumns = [];
foreach ($db->query('SHOW COLUMNS FROM interim_shift_requests')->fetchAll(PDO::FETCH_ASSOC) as $columnRow) {
    $requestColumns[(string) ($columnRow['Field'] ?? '')] = true;
}
if (!isset($requestColumns['validation_status'])) {
    $db->exec("ALTER TABLE interim_shift_requests ADD COLUMN validation_status VARCHAR(20) NOT NULL DEFAULT 'approved' AFTER comment");
}
if (!isset($requestColumns['validated_by_user_id'])) {
    $db->exec('ALTER TABLE interim_shift_requests ADD COLUMN validated_by_user_id INT NULL AFTER validation_status');
}
if (!isset($requestColumns['validated_at'])) {
    $db->exec('ALTER TABLE interim_shift_requests ADD COLUMN validated_at DATETIME NULL AFTER validated_by_user_id');
}

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

// Migration : supporter l'affectation de personnes non inscrites sur le site (matching par nom, version A).
$assignmentColumns = [];
$assignmentStudentNullable = false;
foreach ($db->query('SHOW COLUMNS FROM interim_shift_assignments')->fetchAll(PDO::FETCH_ASSOC) as $columnRow) {
    $field = (string) ($columnRow['Field'] ?? '');
    $assignmentColumns[$field] = true;
    if ($field === 'student_id' && strtoupper((string) ($columnRow['Null'] ?? '')) === 'YES') {
        $assignmentStudentNullable = true;
    }
}
if (!isset($assignmentColumns['external_name'])) {
    $db->exec("ALTER TABLE interim_shift_assignments ADD COLUMN external_name VARCHAR(255) NULL AFTER student_id");
}
if (!$assignmentStudentNullable) {
    // student_id peut etre NULL pour une personne externe (non inscrite) affectee via son nom.
    $db->exec('ALTER TABLE interim_shift_assignments MODIFY COLUMN student_id INT NULL');
}

$agencyName = '';
if (!$isAdmin) {
    $agencyStmt = $db->prepare('SELECT interim FROM utilisateurs WHERE id = ? LIMIT 1');
    $agencyStmt->execute([$currentUserId]);
    $agencyName = trim((string) $agencyStmt->fetchColumn());
}

$message = '';
$pendingConfirm = null; // Confirmation "modale" en attente (par nom ET par liste) : ['message','request_id','student_name','student_id','matching_mode']

$today = new DateTimeImmutable('today');
$startMonday = $today->modify('monday this week');
$weekOptions = [];
for ($offset = 0; $offset < 8; $offset++) {
    $weekStart = $startMonday->modify('+' . $offset . ' week');
    $weekEnd = $weekStart->modify('+6 days');
    $weekOptions[$weekStart->format('Y-m-d')] = [
        'start' => $weekStart,
        'end' => $weekEnd,
        'label' => fjhT('Semaine du ', 'Week van ') . $weekStart->format('d/m/Y') . fjhT(' au ', ' tot ') . $weekEnd->format('d/m/Y'),
    ];
}

$selectedWeekKey = (string) ($_GET['week'] ?? array_key_first($weekOptions));
if (!isset($weekOptions[$selectedWeekKey])) {
    $selectedWeekKey = array_key_first($weekOptions);
}
$selectedWeek = $weekOptions[$selectedWeekKey];

$departmentFilterOptions = [];
$departmentFilterStmt = $db->query(
    "SELECT department_name
     FROM departments
     WHERE is_active = 1
     ORDER BY department_name ASC"
);
$departmentFilterOptions = $departmentFilterStmt->fetchAll(PDO::FETCH_COLUMN);

$weekdayMap = [
    'Monday' => fjhT('Lundi', 'Maandag'),
    'Tuesday' => fjhT('Mardi', 'Dinsdag'),
    'Wednesday' => fjhT('Mercredi', 'Woensdag'),
    'Thursday' => fjhT('Jeudi', 'Donderdag'),
    'Friday' => fjhT('Vendredi', 'Vrijdag'),
    'Saturday' => fjhT('Samedi', 'Zaterdag'),
    'Sunday' => fjhT('Dimanche', 'Zondag'),
];

$weekDays = [];
$cursor = $selectedWeek['start'];
while ($cursor <= $selectedWeek['end']) {
    $dateKey = $cursor->format('Y-m-d');
    $weekDays[] = [
        'key' => $dateKey,
        'label' => $weekdayMap[$cursor->format('l')] ?? $cursor->format('l'),
        'date' => $cursor->format('d/m/Y'),
    ];
    $cursor = $cursor->modify('+1 day');
}

$validDayFilterValues = array_map(static function ($day) {
    return (string) $day['key'];
}, $weekDays);
$selectedDayFilter = trim((string) ($_GET['day'] ?? 'all'));
if ($selectedDayFilter !== 'all' && !in_array($selectedDayFilter, $validDayFilterValues, true)) {
    $selectedDayFilter = 'all';
}

$selectedDepartmentFilter = trim((string) ($_GET['department'] ?? 'all'));
if ($selectedDepartmentFilter !== 'all' && !in_array($selectedDepartmentFilter, $departmentFilterOptions, true)) {
    $selectedDepartmentFilter = 'all';
}

$selectedVueFilter = trim((string) ($_GET['vue'] ?? 'all'));
if (!in_array($selectedVueFilter, ['all', 'a_pourvoir', 'attribue'], true)) {
    $selectedVueFilter = 'all';
}

$matchingMode = trim((string) ($_GET['matching_mode'] ?? $_POST['matching_mode'] ?? 'name'));
if (!in_array($matchingMode, ['name', 'list'], true)) {
    $matchingMode = 'name';
}

if (!function_exists('interimExtractStartMinutes')) {
    function interimExtractStartMinutes($timeSlot)
    {
        $timeSlot = trim((string) $timeSlot);
        if ($timeSlot === '') {
            return null;
        }

        if (preg_match('/(\d{1,2})\s*(?:h|:)?\s*(\d{0,2})/i', $timeSlot, $matches)) {
            $hours = (int) ($matches[1] ?? 0);
            $minutesRaw = trim((string) ($matches[2] ?? ''));
            $minutes = ($minutesRaw === '') ? 0 : (int) $minutesRaw;

            if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59) {
                return ($hours * 60) + $minutes;
            }
        }

        return null;
    }
}

if (!function_exists('interimAvailabilityCompatible')) {
    function interimAvailabilityCompatible($availabilityStatus, $timeSlot)
    {
        $status = (string) $availabilityStatus;

        if ($status === 'journee') {
            return true;
        }

        if ($status !== 'apres_midi') {
            return false;
        }

        $startMinutes = interimExtractStartMinutes($timeSlot);
        if ($startMinutes === null) {
            return false;
        }

        return $startMinutes >= (13 * 60);
    }
}

if (!function_exists('interimParseTimeSlotDuration')) {
    function interimParseTimeSlotDuration($timeSlot)
    {
        $timeSlot = trim((string) $timeSlot);
        if (!preg_match('/^(\d{1,2})[h:](\d{0,2})\s*-\s*(\d{1,2})[h:](\d{0,2})/i', $timeSlot, $m)) {
            return 0;
        }
        $startMin = (int) $m[1] * 60 + (trim($m[2]) !== '' ? (int) $m[2] : 0);
        $endMin   = (int) $m[3] * 60 + (trim($m[4]) !== '' ? (int) $m[4] : 0);
        if ($endMin <= $startMin) {
            return 0;
        }
        return $endMin - $startMin;
    }
}

if (!function_exists('interimGetRankedCandidatesForRequest')) {
    function interimGetRankedCandidatesForRequest(PDO $db, array $requestRow, $isAdmin, $agencyName)
    {
        $requestId = (int) ($requestRow['id'] ?? 0);
        $shiftDate = (string) ($requestRow['shift_date'] ?? '');
        $departmentName = trim((string) ($requestRow['department_name'] ?? ''));
        $timeSlot = (string) ($requestRow['time_slot'] ?? '');

        if ($requestId <= 0 || $shiftDate === '') {
            return [];
        }

        $studentsSql =
            "SELECT u.id, u.nom, u.prenom, u.interim,
                    COALESCE(sa.availability_status, 'non_renseigne') AS availability_status
             FROM utilisateurs u
             LEFT JOIN student_availabilities sa
                ON sa.user_id = u.id
               AND sa.availability_date = ?
             WHERE u.role = 'etudiant'";
        $studentsParams = [$shiftDate];

        if (!$isAdmin) {
            $studentsSql .= ' AND u.interim = ?';
            $studentsParams[] = (string) $agencyName;
        }

        $studentsSql .= ' ORDER BY u.nom ASC, u.prenom ASC';
        $studentsStmt = $db->prepare($studentsSql);
        $studentsStmt->execute($studentsParams);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students)) {
            return [];
        }

        $studentIds = array_map(static function ($row) {
            return (int) $row['id'];
        }, $students);
        $studentIds = array_values(array_unique($studentIds));

        $idPlaceholders = implode(', ', array_fill(0, count($studentIds), '?'));

        $priorityMap = [];
        if ($departmentName !== '') {
            $priorityStmt = $db->prepare(
                "SELECT sdl.student_id, MIN(sdl.priority_rank) AS priority_rank
                 FROM student_department_links sdl
                 INNER JOIN departments d ON d.id = sdl.department_id
                 WHERE d.department_name = ?
                   AND sdl.student_id IN ($idPlaceholders)
                 GROUP BY sdl.student_id"
            );
            $priorityStmt->execute(array_merge([$departmentName], $studentIds));

            foreach ($priorityStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $priorityMap[(int) $row['student_id']] = max(1, (int) $row['priority_rank']);
            }
        }

        $alreadyOnRequest = [];
        $alreadyOnRequestStmt = $db->prepare(
            'SELECT student_id FROM interim_shift_assignments WHERE request_id = ?'
        );
        $alreadyOnRequestStmt->execute([$requestId]);
        foreach ($alreadyOnRequestStmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            $alreadyOnRequest[(int) $sid] = true;
        }

        $sameDayCountMap = [];
        $sameDayStmt = $db->prepare(
            "SELECT a.student_id, COUNT(*) AS total_assignments
             FROM interim_shift_assignments a
             INNER JOIN interim_shift_requests r ON r.id = a.request_id
             WHERE r.shift_date = ?
               AND a.student_id IN ($idPlaceholders)
             GROUP BY a.student_id"
        );
        $sameDayStmt->execute(array_merge([$shiftDate], $studentIds));
        foreach ($sameDayStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sameDayCountMap[(int) $row['student_id']] = (int) $row['total_assignments'];
        }

        // === Règle 45h/semaine ===
        $newShiftDuration = interimParseTimeSlotDuration($timeSlot);
        $weeklyMinutesMap = [];
        if ($newShiftDuration > 0) {
            $weeklyStmt = $db->prepare(
                "SELECT a.student_id, r.time_slot
                 FROM interim_shift_assignments a
                 INNER JOIN interim_shift_requests r ON r.id = a.request_id
                 WHERE a.student_id IN ($idPlaceholders)
                   AND YEARWEEK(r.shift_date, 1) = YEARWEEK(?, 1)"
            );
            $weeklyStmt->execute(array_merge($studentIds, [$shiftDate]));
            foreach ($weeklyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sid = (int) $row['student_id'];
                $weeklyMinutesMap[$sid] = ($weeklyMinutesMap[$sid] ?? 0) + interimParseTimeSlotDuration($row['time_slot']);
            }
        }

        // === Règle 6 jours consécutifs (semaine en cours + semaine précédente) ===
        $assignedDatesMap = [];
        $windowStart = (new DateTimeImmutable($shiftDate))->modify('-13 days')->format('Y-m-d');
        $consecutiveDatesStmt = $db->prepare(
            "SELECT a.student_id, r.shift_date
             FROM interim_shift_assignments a
             INNER JOIN interim_shift_requests r ON r.id = a.request_id
             WHERE a.student_id IN ($idPlaceholders)
               AND r.shift_date BETWEEN ? AND ?
             GROUP BY a.student_id, r.shift_date"
        );
        $consecutiveDatesStmt->execute(array_merge($studentIds, [$windowStart, $shiftDate]));
        foreach ($consecutiveDatesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sid = (int) $row['student_id'];
            $assignedDatesMap[$sid][(string) $row['shift_date']] = true;
        }

        $ranked = [];
        foreach ($students as $student) {
            $studentId = (int) $student['id'];
            $studentName = trim((string) ($student['prenom'] ?? '')) . ' ' . trim((string) ($student['nom'] ?? ''));

            if (isset($alreadyOnRequest[$studentId])) {
                $ranked[] = [
                    'id' => $studentId,
                    'name' => $studentName,
                    'interim' => (string) ($student['interim'] ?? ''),
                    'availability_status' => (string) ($student['availability_status'] ?? 'non_renseigne'),
                    'priority_rank' => $priorityMap[$studentId] ?? 99,
                    'same_day_count' => (int) ($sameDayCountMap[$studentId] ?? 0),
                    'score' => 999999,
                    'manual_eligible' => false,
                    'eligible' => false,
                    'reason' => 'Déjà affecté sur ce créneau',
                    'manual_reason' => 'Déjà affecté sur ce créneau',
                ];
                continue;
            }

            $availabilityStatus = (string) ($student['availability_status'] ?? 'non_renseigne');
            if ($availabilityStatus === 'matin') {
                $availabilityStatus = 'non_renseigne';
            }

            $priorityRank = $priorityMap[$studentId] ?? 99;
            $sameDayCount = (int) ($sameDayCountMap[$studentId] ?? 0);

            if ($sameDayCount > 0) {
                $ranked[] = [
                    'id' => $studentId,
                    'name' => $studentName,
                    'interim' => (string) ($student['interim'] ?? ''),
                    'availability_status' => $availabilityStatus,
                    'priority_rank' => $priorityRank,
                    'same_day_count' => $sameDayCount,
                    'score' => 999998,
                    'manual_eligible' => false,
                    'eligible' => false,
                    'reason' => 'Déjà affecté ce jour',
                    'manual_reason' => 'Déjà affecté ce jour',
                ];
                continue;
            }

            if ($availabilityStatus === 'indisponible') {
                $ranked[] = [
                    'id' => $studentId,
                    'name' => $studentName,
                    'interim' => (string) ($student['interim'] ?? ''),
                    'availability_status' => $availabilityStatus,
                    'priority_rank' => $priorityRank,
                    'same_day_count' => $sameDayCount,
                    'score' => 999997,
                    'manual_eligible' => false,
                    'eligible' => false,
                    'reason' => 'Indisponible',
                    'manual_reason' => 'Indisponible',
                ];
                continue;
            }

            if (!interimAvailabilityCompatible($availabilityStatus, $timeSlot)) {
                $ranked[] = [
                    'id' => $studentId,
                    'name' => $studentName,
                    'interim' => (string) ($student['interim'] ?? ''),
                    'availability_status' => $availabilityStatus,
                    'priority_rank' => $priorityRank,
                    'same_day_count' => $sameDayCount,
                    'score' => 999997,
                    'manual_eligible' => true,
                    'eligible' => false,
                    'reason' => 'Disponibilité non compatible',
                    'manual_reason' => '',
                ];
                continue;
            }

            // === Vérification 45h/semaine ===
            if ($newShiftDuration > 0) {
                $totalMinutesThisWeek = ($weeklyMinutesMap[$studentId] ?? 0) + $newShiftDuration;
                if ($totalMinutesThisWeek > 45 * 60) {
                    $ranked[] = [
                        'id' => $studentId,
                        'name' => $studentName,
                        'interim' => (string) ($student['interim'] ?? ''),
                        'availability_status' => $availabilityStatus,
                        'priority_rank' => $priorityRank,
                        'same_day_count' => $sameDayCount,
                        'score' => 999996,
                        'manual_eligible' => false,
                        'eligible' => false,
                        'reason' => 'Limite 45h/semaine atteinte (' . round($totalMinutesThisWeek / 60, 1) . 'h prévu)',
                        'manual_reason' => 'Limite 45h/semaine atteinte',
                    ];
                    continue;
                }
            }

            // === Vérification 6 jours consécutifs ===
            $datesForStudent = $assignedDatesMap[$studentId] ?? [];
            $datesForStudent[$shiftDate] = true;
            ksort($datesForStudent);
            $dateList = array_keys($datesForStudent);
            $maxConsecutive = 1;
            $streak = 1;
            for ($di = 1, $diMax = count($dateList); $di < $diMax; $di++) {
                $prev = new DateTimeImmutable($dateList[$di - 1]);
                $curr = new DateTimeImmutable($dateList[$di]);
                if ((int) $curr->diff($prev)->days === 1) {
                    $streak++;
                    if ($streak > $maxConsecutive) {
                        $maxConsecutive = $streak;
                    }
                } else {
                    $streak = 1;
                }
            }
            if ($maxConsecutive > 6) {
                $ranked[] = [
                    'id' => $studentId,
                    'name' => $studentName,
                    'interim' => (string) ($student['interim'] ?? ''),
                    'availability_status' => $availabilityStatus,
                    'priority_rank' => $priorityRank,
                    'same_day_count' => $sameDayCount,
                    'score' => 999995,
                    'manual_eligible' => false,
                    'eligible' => false,
                    'reason' => 'Limite 6 jours consécutifs atteinte',
                    'manual_reason' => 'Limite 6 jours consécutifs atteinte',
                ];
                continue;
            }

            $availabilityPenalty = ($availabilityStatus === 'journee') ? 0 : 20;
            $sameDayPenalty = 0;
            $score = ($priorityRank * 100) + $availabilityPenalty + $sameDayPenalty;

            $ranked[] = [
                'id' => $studentId,
                'name' => $studentName,
                'interim' => (string) ($student['interim'] ?? ''),
                'availability_status' => $availabilityStatus,
                'priority_rank' => $priorityRank,
                'same_day_count' => $sameDayCount,
                'score' => $score,
                'manual_eligible' => true,
                'eligible' => true,
                'reason' => '',
                'manual_reason' => '',
            ];
        }

        usort($ranked, static function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcmp((string) $a['name'], (string) $b['name']);
            }
            return ($a['score'] <=> $b['score']);
        });

        return $ranked;
    }
}

if (!function_exists('interimAutoAssignRequest')) {
    function interimAutoAssignRequest(PDO $db, $requestId, $currentUserId, $isAdmin, $agencyName)
    {
        $requestId = (int) $requestId;
        if ($requestId <= 0) {
            return ['assigned' => 0, 'reason' => 'Demande invalide'];
        }

        $db->beginTransaction();
        try {
            $requestStmt = $db->prepare(
                'SELECT id, shift_date, department_name, time_slot, seats_required FROM interim_shift_requests WHERE id = ? LIMIT 1 FOR UPDATE'
            );
            $requestStmt->execute([$requestId]);
            $requestRow = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$requestRow) {
                throw new RuntimeException('Demande introuvable');
            }

            $assignedSeatsStmt = $db->prepare(
                'SELECT seat_number FROM interim_shift_assignments WHERE request_id = ? ORDER BY seat_number ASC FOR UPDATE'
            );
            $assignedSeatsStmt->execute([$requestId]);
            $assignedSeats = array_map('intval', $assignedSeatsStmt->fetchAll(PDO::FETCH_COLUMN));

            $seatsRequired = (int) $requestRow['seats_required'];
            $availableSeats = [];
            for ($i = 1; $i <= $seatsRequired; $i++) {
                if (!in_array($i, $assignedSeats, true)) {
                    $availableSeats[] = $i;
                }
            }

            if (empty($availableSeats)) {
                $db->commit();
                return ['assigned' => 0, 'reason' => 'Créneau déjà complet'];
            }

            $rankedCandidates = interimGetRankedCandidatesForRequest($db, $requestRow, $isAdmin, $agencyName);
            $eligibleCandidates = array_values(array_filter($rankedCandidates, static function ($candidate) {
                return !empty($candidate['eligible']);
            }));

            if (empty($eligibleCandidates)) {
                $db->commit();
                return ['assigned' => 0, 'reason' => 'Aucun candidat compatible'];
            }

            $insertAssignStmt = $db->prepare(
                'INSERT INTO interim_shift_assignments (request_id, seat_number, student_id, assigned_by_user_id, agency_name) VALUES (?, ?, ?, ?, ?)'
            );

            $assignedCount = 0;
            foreach ($eligibleCandidates as $candidate) {
                if (empty($availableSeats)) {
                    break;
                }

                $seatNumber = array_shift($availableSeats);
                $candidateAgency = trim((string) ($candidate['interim'] ?? ''));
                $insertAssignStmt->execute([
                    $requestId,
                    $seatNumber,
                    (int) $candidate['id'],
                    (int) $currentUserId,
                    $isAdmin ? $candidateAgency : (string) $agencyName,
                ]);
                $assignedCount++;
            }

            $db->commit();
            if ($assignedCount > 0) {
                famijobNotifyRequestMatched($db, $requestId, $currentUserId, '', $assignedCount);
            }
            return ['assigned' => $assignedCount, 'reason' => ''];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['assigned' => 0, 'reason' => $e->getMessage()];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    if ($isAdmin && isset($_POST['auto_match_request'])) {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $result = interimAutoAssignRequest($db, $requestId, $currentUserId, true, $agencyName);
        if ((int) $result['assigned'] > 0) {
            $message = "<div class='alert success'>Auto-matching terminé : " . (int) $result['assigned'] . " place(s) affectée(s) sur ce créneau.</div>";
        } else {
            $reason = trim((string) ($result['reason'] ?? ''));
            $suffix = $reason !== '' ? ' (' . e($reason) . ')' : '';
            $message = "<div class='alert error'>Auto-matching : aucune affectation réalisée{$suffix}.</div>";
        }
    }

    if ($isAdmin && isset($_POST['auto_match_week'])) {
        $weekKey = (string) ($_POST['week'] ?? $selectedWeekKey);
        if (!isset($weekOptions[$weekKey])) {
            $weekKey = $selectedWeekKey;
        }

        $weekToMatch = $weekOptions[$weekKey];
        $weekRequestStmt = $db->prepare(
            "SELECT id
             FROM interim_shift_requests
             WHERE shift_date BETWEEN ? AND ?
               AND validation_status = 'approved'
             ORDER BY shift_date ASC, department_name ASC, time_slot ASC"
        );
        $weekRequestStmt->execute([
            $weekToMatch['start']->format('Y-m-d'),
            $weekToMatch['end']->format('Y-m-d'),
        ]);

        $totalAssigned = 0;
        $processed = 0;
        foreach ($weekRequestStmt->fetchAll(PDO::FETCH_COLUMN) as $requestId) {
            $processed++;
            $result = interimAutoAssignRequest($db, (int) $requestId, $currentUserId, true, $agencyName);
            $totalAssigned += (int) ($result['assigned'] ?? 0);
        }

        if ($totalAssigned > 0) {
            $message = "<div class='alert success'>Auto-matching semaine terminé : {$totalAssigned} place(s) affectée(s) sur {$processed} créneau(x).</div>";
        } else {
            $message = "<div class='alert error'>Auto-matching semaine : aucune nouvelle affectation.</div>";
        }
    }

    if (isset($_POST['assign_student'])) {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $studentId = (int) ($_POST['student_id'] ?? 0);   // choix dans la liste (prioritaire)
        $studentName = trim((string) ($_POST['student_name'] ?? ''));
        $externalName = '';
        $isExternal = false;
        $ambiguousName = false;

        // Fusion "2 en 1" : si aucun étudiant n'est choisi dans la liste mais qu'un nom est
        // tapé, on recherche l'inscrit ; à défaut on l'affecte en texte libre (externe).
        if ($studentId <= 0 && $studentName !== '') {
            $studentSearchStmt = $db->prepare(
                "SELECT id, nom, prenom, interim
                 FROM utilisateurs
                 WHERE role = 'etudiant'
                   AND (
                        LOWER(CONCAT(TRIM(prenom), ' ', TRIM(nom))) = LOWER(?)
                     OR LOWER(CONCAT(TRIM(nom), ' ', TRIM(prenom))) = LOWER(?)
                     OR LOWER(CONCAT(TRIM(prenom), ' ', TRIM(nom))) LIKE LOWER(?)
                     OR LOWER(CONCAT(TRIM(nom), ' ', TRIM(prenom))) LIKE LOWER(?)
                   )
                 ORDER BY nom ASC, prenom ASC
                 LIMIT 5"
            );
            $likeTerm = $studentName . '%';
            $studentSearchStmt->execute([$studentName, $studentName, $likeTerm, $likeTerm]);
            $candidateRows = $studentSearchStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($candidateRows) === 1) {
                $studentId = (int) $candidateRows[0]['id'];
            } elseif (count($candidateRows) > 1) {
                $exactMatches = array_filter($candidateRows, static function ($row) use ($studentName) {
                    $full1 = trim((string) $row['prenom'] . ' ' . $row['nom']);
                    $full2 = trim((string) $row['nom'] . ' ' . $row['prenom']);
                    return strcasecmp($full1, $studentName) === 0 || strcasecmp($full2, $studentName) === 0;
                });
                if (count($exactMatches) === 1) {
                    $studentId = (int) array_values($exactMatches)[0]['id'];
                } else {
                    // Plusieurs inscrits correspondent sans nom complet unique : on demande de preciser.
                    $ambiguousName = true;
                }
            }

            // Aucun inscrit ne correspond : on affecte la personne en texte libre.
            if ($studentId <= 0 && !$ambiguousName) {
                $isExternal = true;
                $externalName = $studentName;
            }
        }

        $confirmAssign = isset($_POST['confirm_assign']);

        // === Avertissements "soft" (par nom ET par liste) : on demande confirmation (modale Oui/Non) au lieu de bloquer/affecter ===
        if (!$ambiguousName && $requestId > 0 && ($studentId > 0 || $isExternal) && !$confirmAssign) {
            $confInfoStmt = $db->prepare('SELECT shift_date FROM interim_shift_requests WHERE id = ? LIMIT 1');
            $confInfoStmt->execute([$requestId]);
            $confShiftDate = (string) $confInfoStmt->fetchColumn();
            $confReasons = [];

            if ($confShiftDate !== '') {
                if ($isExternal) {
                    // Personne non inscrite deja affectee a un autre creneau ce jour-la
                    $confExtDayStmt = $db->prepare(
                        "SELECT COUNT(*)
                         FROM interim_shift_assignments a
                         INNER JOIN interim_shift_requests r ON r.id = a.request_id
                         WHERE r.shift_date = ?
                           AND a.request_id <> ?
                           AND a.student_id IS NULL
                           AND LOWER(TRIM(a.external_name)) = LOWER(?)"
                    );
                    $confExtDayStmt->execute([$confShiftDate, $requestId, $externalName]);
                    if ((int) $confExtDayStmt->fetchColumn() > 0) {
                        $confReasons[] = fjhT(
                            'Cette personne est déjà affectée à un autre créneau ce jour-là.',
                            'Deze persoon is die dag al aan een ander tijdsblok toegewezen.'
                        );
                    }
                } else {
                    // Inscrit : disponibilites non renseignees ?
                    $confAvailStmt = $db->prepare(
                        'SELECT availability_status FROM student_availabilities WHERE user_id = ? AND availability_date = ? LIMIT 1'
                    );
                    $confAvailStmt->execute([$studentId, $confShiftDate]);
                    $confAvail = (string) $confAvailStmt->fetchColumn();
                    if ($confAvail === 'matin') {
                        $confAvail = 'non_renseigne';
                    }
                    if ($confAvail === '' || $confAvail === 'non_renseigne') {
                        $confReasons[] = fjhT(
                            "Cette personne n'a pas renseigné ses disponibilités pour ce jour.",
                            'Deze persoon heeft zijn/haar beschikbaarheid voor deze dag niet doorgegeven.'
                        );
                    }
                    // Inscrit : deja affecte a un autre creneau ce jour-la ?
                    $confDayStmt = $db->prepare(
                        "SELECT COUNT(*)
                         FROM interim_shift_assignments a
                         INNER JOIN interim_shift_requests r ON r.id = a.request_id
                         WHERE a.student_id = ?
                           AND r.shift_date = ?
                           AND a.request_id <> ?"
                    );
                    $confDayStmt->execute([$studentId, $confShiftDate, $requestId]);
                    if ((int) $confDayStmt->fetchColumn() > 0) {
                        $confReasons[] = fjhT(
                            'Cette personne est déjà affectée à un autre créneau ce jour-là.',
                            'Deze persoon is die dag al aan een ander tijdsblok toegewezen.'
                        );
                    }
                }
            }

            if (!empty($confReasons)) {
                $pendingConfirm = [
                    'message' => implode(' ', $confReasons) . ' ' . fjhT(
                        "Êtes-vous sûr de vouloir l'affecter quand même ?",
                        'Weet u zeker dat u deze persoon toch wilt toewijzen?'
                    ),
                    'request_id' => $requestId,
                    'student_name' => $studentName,
                    'student_id' => $studentId,
                    'matching_mode' => ($studentId > 0 ? 'list' : 'name'),
                ];
            }
        }

        if ($pendingConfirm !== null) {
            // Confirmation requise : la modale sera affichee, on n'affecte pas encore.
        } elseif ($ambiguousName) {
            $message = "<div class='alert error'>Plusieurs étudiants correspondent à ce nom. Précisez le nom complet, ou choisissez la personne dans la liste déroulante.</div>";
        } elseif ($requestId <= 0 || ($studentId <= 0 && !$isExternal)) {
            $message = "<div class='alert error'>Sélection étudiant invalide.</div>";
        } elseif ($isExternal) {
            // === Affectation d'une personne non inscrite sur le site ===
            try {
                $db->beginTransaction();

                $requestLockStmt = $db->prepare(
                    'SELECT id, seats_required FROM interim_shift_requests WHERE id = ? LIMIT 1 FOR UPDATE'
                );
                $requestLockStmt->execute([$requestId]);
                $requestRow = $requestLockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$requestRow) {
                    throw new RuntimeException('Demande introuvable.');
                }

                $assignedSeatsStmt = $db->prepare(
                    'SELECT seat_number FROM interim_shift_assignments WHERE request_id = ? ORDER BY seat_number ASC FOR UPDATE'
                );
                $assignedSeatsStmt->execute([$requestId]);
                $assignedSeats = array_map('intval', $assignedSeatsStmt->fetchAll(PDO::FETCH_COLUMN));

                $seatsRequired = (int) $requestRow['seats_required'];
                $nextSeat = null;
                for ($i = 1; $i <= $seatsRequired; $i++) {
                    if (!in_array($i, $assignedSeats, true)) {
                        $nextSeat = $i;
                        break;
                    }
                }

                if ($nextSeat === null) {
                    throw new RuntimeException('Ce créneau est déjà complet.');
                }

                // Eviter d'ajouter deux fois la meme personne externe sur ce creneau.
                $dupExternalStmt = $db->prepare(
                    "SELECT COUNT(*) FROM interim_shift_assignments
                     WHERE request_id = ? AND student_id IS NULL AND LOWER(TRIM(external_name)) = LOWER(?)"
                );
                $dupExternalStmt->execute([$requestId, $externalName]);
                if ((int) $dupExternalStmt->fetchColumn() > 0) {
                    throw new RuntimeException('Cette personne est déjà assignée sur ce créneau.');
                }

                $insertAssignStmt = $db->prepare(
                    'INSERT INTO interim_shift_assignments (request_id, seat_number, student_id, external_name, assigned_by_user_id, agency_name) VALUES (?, ?, NULL, ?, ?, ?)'
                );
                $insertAssignStmt->execute([
                    $requestId,
                    $nextSeat,
                    $externalName,
                    $currentUserId,
                    $isAdmin ? '' : $agencyName,
                ]);

                $db->commit();
                famijobNotifyRequestMatched($db, $requestId, $currentUserId, $externalName, 1);
                $message = "<div class='alert success'>Personne (non inscrite) assignée avec succès.</div>";
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $message = "<div class='alert error'>" . e($e->getMessage()) . "</div>";
            }
        } else {
            $rhBlockMessage = fjhT(
                "Ce n'est pas possible : cette personne n'est pas disponible pour ce créneau et l'ajouter poserait problème au niveau du planning. Si vous avez vraiment besoin d'elle, merci de voir directement avec les RH.",
                'Dit is niet mogelijk: deze persoon is niet beschikbaar voor dit tijdsblok en toevoegen zou voor problemen in de planning zorgen. Heeft u deze persoon echt nodig, neem dan rechtstreeks contact op met HR.'
            );
            try {
                $db->beginTransaction();

                $requestLockStmt = $db->prepare(
                    'SELECT id, seats_required, shift_date, time_slot FROM interim_shift_requests WHERE id = ? LIMIT 1 FOR UPDATE'
                );
                $requestLockStmt->execute([$requestId]);
                $requestRow = $requestLockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$requestRow) {
                    throw new RuntimeException('Demande introuvable.');
                }

                $studentStmt = $db->prepare(
                    "SELECT id, nom, prenom, interim
                     FROM utilisateurs
                     WHERE id = ? AND role = 'etudiant'
                     LIMIT 1"
                );
                $studentStmt->execute([$studentId]);
                $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    throw new RuntimeException('Étudiant invalide.');
                }

                $studentAvailabilityStmt = $db->prepare(
                    'SELECT availability_status FROM student_availabilities WHERE user_id = ? AND availability_date = ? LIMIT 1'
                );
                $studentAvailabilityStmt->execute([
                    $studentId,
                    (string) ($requestRow['shift_date'] ?? ''),
                ]);
                $studentAvailabilityStatus = (string) $studentAvailabilityStmt->fetchColumn();
                if ($studentAvailabilityStatus === 'matin') {
                    $studentAvailabilityStatus = 'non_renseigne';
                }

                if ($studentAvailabilityStatus === 'indisponible') {
                    throw new RuntimeException($rhBlockMessage);
                }

                $studentInterim = trim((string) ($student['interim'] ?? ''));
                if (!$isAdmin && ($studentInterim === '' || $studentInterim !== $agencyName)) {
                    throw new RuntimeException('Cet étudiant ne fait pas partie de votre agence.');
                }

                $sameDayAssignmentStmt = $db->prepare(
                    "SELECT COUNT(*)
                     FROM interim_shift_assignments a
                     INNER JOIN interim_shift_requests r ON r.id = a.request_id
                     WHERE a.student_id = ?
                       AND r.shift_date = ?"
                );
                $sameDayAssignmentStmt->execute([
                    $studentId,
                    (string) ($requestRow['shift_date'] ?? ''),
                ]);
                // "Deja affecte ce jour" est gere en amont par une confirmation (modale) dans les deux modes ;
                // une fois confirme, on autorise l'affectation.
                if (!$confirmAssign && (int) $sameDayAssignmentStmt->fetchColumn() > 0) {
                    throw new RuntimeException($rhBlockMessage);
                }

                // Vérification 45h/semaine
                $manualShiftDuration = interimParseTimeSlotDuration((string) ($requestRow['time_slot'] ?? ''));
                if ($manualShiftDuration > 0) {
                    $manualWeeklyStmt = $db->prepare(
                        "SELECT COALESCE(SUM(r2.time_slot), '') AS slots
                         FROM interim_shift_assignments a2
                         INNER JOIN interim_shift_requests r2 ON r2.id = a2.request_id
                         WHERE a2.student_id = ?
                           AND YEARWEEK(r2.shift_date, 1) = YEARWEEK(?, 1)"
                    );
                    $manualWeeklyStmt->execute([$studentId, (string) ($requestRow['shift_date'] ?? '')]);
                    $manualWeeklySlots = $db->prepare(
                        "SELECT r2.time_slot
                         FROM interim_shift_assignments a2
                         INNER JOIN interim_shift_requests r2 ON r2.id = a2.request_id
                         WHERE a2.student_id = ?
                           AND YEARWEEK(r2.shift_date, 1) = YEARWEEK(?, 1)"
                    );
                    $manualWeeklySlots->execute([$studentId, (string) ($requestRow['shift_date'] ?? '')]);
                    $manualTotalMinutes = $manualShiftDuration;
                    foreach ($manualWeeklySlots->fetchAll(PDO::FETCH_COLUMN) as $slot) {
                        $manualTotalMinutes += interimParseTimeSlotDuration((string) $slot);
                    }
                    if ($manualTotalMinutes > 45 * 60) {
                        throw new RuntimeException($rhBlockMessage);
                    }
                }

                // Vérification 6 jours consécutifs
                $manualShiftDate = (string) ($requestRow['shift_date'] ?? '');
                $manualWindowStart = (new DateTimeImmutable($manualShiftDate))->modify('-13 days')->format('Y-m-d');
                $manualDatesStmt = $db->prepare(
                    "SELECT r2.shift_date
                     FROM interim_shift_assignments a2
                     INNER JOIN interim_shift_requests r2 ON r2.id = a2.request_id
                     WHERE a2.student_id = ?
                       AND r2.shift_date BETWEEN ? AND ?
                     GROUP BY r2.shift_date"
                );
                $manualDatesStmt->execute([$studentId, $manualWindowStart, $manualShiftDate]);
                $manualDates = [];
                foreach ($manualDatesStmt->fetchAll(PDO::FETCH_COLUMN) as $d) {
                    $manualDates[(string) $d] = true;
                }
                $manualDates[$manualShiftDate] = true;
                ksort($manualDates);
                $manualDateList = array_keys($manualDates);
                $manualMax = 1;
                $manualStreak = 1;
                for ($mdi = 1, $mdiMax = count($manualDateList); $mdi < $mdiMax; $mdi++) {
                    $mPrev = new DateTimeImmutable($manualDateList[$mdi - 1]);
                    $mCurr = new DateTimeImmutable($manualDateList[$mdi]);
                    if ((int) $mCurr->diff($mPrev)->days === 1) {
                        $manualStreak++;
                        if ($manualStreak > $manualMax) {
                            $manualMax = $manualStreak;
                        }
                    } else {
                        $manualStreak = 1;
                    }
                }
                if ($manualMax > 6) {
                    throw new RuntimeException($rhBlockMessage);
                }

                $assignedSeatsStmt = $db->prepare(
                    'SELECT seat_number FROM interim_shift_assignments WHERE request_id = ? ORDER BY seat_number ASC FOR UPDATE'
                );
                $assignedSeatsStmt->execute([$requestId]);
                $assignedSeats = array_map('intval', $assignedSeatsStmt->fetchAll(PDO::FETCH_COLUMN));

                $seatsRequired = (int) $requestRow['seats_required'];
                $nextSeat = null;
                for ($i = 1; $i <= $seatsRequired; $i++) {
                    if (!in_array($i, $assignedSeats, true)) {
                        $nextSeat = $i;
                        break;
                    }
                }

                if ($nextSeat === null) {
                    throw new RuntimeException('Ce créneau est déjà complet.');
                }

                $alreadyAssignedStmt = $db->prepare(
                    'SELECT COUNT(*) FROM interim_shift_assignments WHERE request_id = ? AND student_id = ?'
                );
                $alreadyAssignedStmt->execute([$requestId, $studentId]);
                if ((int) $alreadyAssignedStmt->fetchColumn() > 0) {
                    throw new RuntimeException('Cet étudiant est déjà assigné sur ce créneau.');
                }

                $insertAssignStmt = $db->prepare(
                    'INSERT INTO interim_shift_assignments (request_id, seat_number, student_id, assigned_by_user_id, agency_name) VALUES (?, ?, ?, ?, ?)'
                );
                $insertAssignStmt->execute([
                    $requestId,
                    $nextSeat,
                    $studentId,
                    $currentUserId,
                    $isAdmin ? $studentInterim : $agencyName,
                ]);

                $db->commit();
                famijobNotifyRequestMatched($db, $requestId, $currentUserId, (string) ($studentName ?? ''), 1);
                $message = "<div class='alert success'>Étudiant assigné avec succès.</div>";
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $message = "<div class='alert error'>" . e($e->getMessage()) . "</div>";
            }
        }
    }

    if (isset($_POST['unassign_student'])) {
        $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($assignmentId <= 0 || $requestId <= 0) {
            $message = "<div class='alert error'>Désaffectation invalide.</div>";
        } else {
            try {
                $db->beginTransaction();
                $assignmentStmt = $db->prepare(
                    "SELECT a.id, a.request_id,
                            CASE WHEN a.student_id IS NULL
                                 THEN TRIM(a.agency_name)
                                 ELSE COALESCE(NULLIF(TRIM(a.agency_name), ''), TRIM(u.interim))
                            END AS owner_agency
                     FROM interim_shift_assignments a
                     LEFT JOIN utilisateurs u ON u.id = a.student_id
                     WHERE a.id = ? AND a.request_id = ?
                     LIMIT 1
                     FOR UPDATE"
                );
                $assignmentStmt->execute([$assignmentId, $requestId]);
                $assignmentRow = $assignmentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$assignmentRow) {
                    throw new RuntimeException('Affectation introuvable.');
                }

                $ownerAgency = trim((string) ($assignmentRow['owner_agency'] ?? ''));
                if (!$isAdmin && ($ownerAgency === '' || $ownerAgency !== $agencyName)) {
                    throw new RuntimeException('Vous ne pouvez retirer que vos propres affectations.');
                }

                $deleteStmt = $db->prepare('DELETE FROM interim_shift_assignments WHERE id = ? AND request_id = ?');
                $deleteStmt->execute([$assignmentId, $requestId]);
                if ($deleteStmt->rowCount() <= 0) {
                    throw new RuntimeException('Désaffectation non effectuée.');
                }

                $db->commit();
                $message = "<div class='alert success'>Étudiant désaffecté du créneau.</div>";
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $message = "<div class='alert error'>" . e($e->getMessage()) . "</div>";
            }
        }
    }
}

$requestsStmt = $db->prepare(
    "SELECT id, shift_date, department_name, time_slot, seats_required, comment
     FROM interim_shift_requests
     WHERE shift_date BETWEEN ? AND ?
    AND validation_status = 'approved'
     ORDER BY shift_date ASC, department_name ASC, time_slot ASC"
);
$requestsStmt->execute([
    $selectedWeek['start']->format('Y-m-d'),
    $selectedWeek['end']->format('Y-m-d'),
]);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);

$requestIds = array_map(static function ($row) {
    return (int) $row['id'];
}, $requests);

$assignmentsByRequest = [];
if (!empty($requestIds)) {
    $placeholders = implode(', ', array_fill(0, count($requestIds), '?'));
    $assignmentsStmt = $db->prepare(
        "SELECT a.id AS assignment_id, a.request_id, a.seat_number, a.agency_name, a.external_name,
                u.id AS student_id, u.nom, u.prenom, u.interim
         FROM interim_shift_assignments a
         LEFT JOIN utilisateurs u ON u.id = a.student_id
         WHERE a.request_id IN ($placeholders)
         ORDER BY a.request_id ASC, a.seat_number ASC"
    );
    $assignmentsStmt->execute($requestIds);

    foreach ($assignmentsStmt->fetchAll(PDO::FETCH_ASSOC) as $assignment) {
        $requestId = (int) $assignment['request_id'];
        if (!isset($assignmentsByRequest[$requestId])) {
            $assignmentsByRequest[$requestId] = [];
        }
        $assignmentsByRequest[$requestId][] = $assignment;
    }
}

$studentOptions = [];
$studentsSql =
    "SELECT id, nom, prenom, interim
     FROM utilisateurs
     WHERE role = 'etudiant'";
$studentParams = [];

if (!$isAdmin) {
    $studentsSql .= ' AND interim = ?';
    $studentParams[] = $agencyName;
}

$studentsSql .= ' ORDER BY nom ASC, prenom ASC';
$studentsStmt = $db->prepare($studentsSql);
$studentsStmt->execute($studentParams);
foreach ($studentsStmt->fetchAll(PDO::FETCH_ASSOC) as $studentRow) {
    $sid = (int) $studentRow['id'];
    $studentOptions[$sid] = [
        'id' => $sid,
        'label' => trim((string) $studentRow['prenom']) . ' ' . trim((string) $studentRow['nom']),
        'interim' => (string) ($studentRow['interim'] ?? ''),
    ];
}

$studentAvailabilityMap = [];
if (!empty($studentOptions)) {
    $studentIds = array_keys($studentOptions);
    $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
    $params = array_merge(
        [$selectedWeek['start']->format('Y-m-d'), $selectedWeek['end']->format('Y-m-d')],
        $studentIds
    );

    $availabilityStmt = $db->prepare(
        "SELECT user_id, availability_date, availability_status
         FROM student_availabilities
         WHERE availability_date BETWEEN ? AND ?
           AND user_id IN ($placeholders)"
    );
    $availabilityStmt->execute($params);

    foreach ($availabilityStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (int) $row['user_id'];
        $day = (string) $row['availability_date'];
        $status = (string) ($row['availability_status'] ?? 'non_renseigne');
        if ($status === 'matin') {
            $status = 'non_renseigne';
        }
        if (!isset($studentAvailabilityMap[$sid])) {
            $studentAvailabilityMap[$sid] = [];
        }
        $studentAvailabilityMap[$sid][$day] = $status;
    }
}

$statusLabels = [
    'non_renseigne' => 'Non renseigné',
    'indisponible' => 'Indisponible',
    'apres_midi' => 'Après-midi',
    'journee' => 'Journée',
];

$requestsByDate = [];
foreach ($requests as $request) {
    if ($selectedDepartmentFilter !== 'all' && (string) $request['department_name'] !== $selectedDepartmentFilter) {
        continue;
    }

    if ($selectedVueFilter !== 'all') {
        $rid = (int) $request['id'];
        $filledCount = isset($assignmentsByRequest[$rid]) ? count($assignmentsByRequest[$rid]) : 0;
        $isFull = $filledCount >= (int) $request['seats_required'];
        if ($selectedVueFilter === 'a_pourvoir' && $isFull) {
            continue;
        }
        if ($selectedVueFilter === 'attribue' && !$isFull) {
            continue;
        }
    }

    $dateKey = (string) $request['shift_date'];
    if (!isset($requestsByDate[$dateKey])) {
        $requestsByDate[$dateKey] = [];
    }
    $requestsByDate[$dateKey][] = $request;
}

$requestById = [];
foreach ($requests as $request) {
    $requestById[(int) $request['id']] = $request;
}

$remainingByDayDept = [];
foreach ($assignmentsByRequest as $rid => $assignedRows) {
    if (!isset($requestById[(int) $rid])) {
        continue;
    }
    $request = $requestById[(int) $rid];
    $dayKey = (string) $request['shift_date'];
    $deptName = (string) $request['department_name'];
    $remaining = max(0, (int) $request['seats_required'] - count($assignedRows));
    if (!isset($remainingByDayDept[$dayKey])) {
        $remainingByDayDept[$dayKey] = [];
    }
    if (!isset($remainingByDayDept[$dayKey][$deptName])) {
        $remainingByDayDept[$dayKey][$deptName] = 0;
    }
    $remainingByDayDept[$dayKey][$deptName] += $remaining;
}
foreach ($requests as $request) {
    $rid = (int) $request['id'];
    if (isset($assignmentsByRequest[$rid])) {
        continue;
    }
    $dayKey = (string) $request['shift_date'];
    $deptName = (string) $request['department_name'];
    $remaining = max(0, (int) $request['seats_required']);
    if (!isset($remainingByDayDept[$dayKey])) {
        $remainingByDayDept[$dayKey] = [];
    }
    if (!isset($remainingByDayDept[$dayKey][$deptName])) {
        $remainingByDayDept[$dayKey][$deptName] = 0;
    }
    $remainingByDayDept[$dayKey][$deptName] += $remaining;
}

$visibleWeekDays = [];
foreach ($weekDays as $weekDay) {
    $dayKey = (string) $weekDay['key'];
    if ($selectedDayFilter !== 'all' && $dayKey !== $selectedDayFilter) {
        continue;
    }

    if (!isset($requestsByDate[$dayKey]) || empty($requestsByDate[$dayKey])) {
        continue;
    }

    $visibleWeekDays[] = $weekDay;
}
// (le PHP reste ouvert : la vue qui suit est du code, pas du texte)
// ─────────────────────────────────────────────────────────────────────────────
// VUE SEMAINE — la grille du fichier Excel, reprise à l'écran.
//
// POURQUOI CETTE FORME. La collaboratrice qui fait le matching travaillait
// depuis des années sur un classeur : une colonne par jour, les départements en
// lignes, groupés par secteur, et dans chaque case l'horaire, le nom et
// l'agence. L'ancienne présentation (une carte par créneau) l'obligeait à
// reconstruire cette image de tête à chaque fois. On change l'écran, pas sa
// méthode.
//
// ⚠️ SEUL L'AFFICHAGE EST NEUF. Tout ce qui écrit en base — affecter, retirer,
// auto-matching — reste le traitement d'origine, plus haut dans ce fichier. Une
// seconde implantation de l'affectation aurait fini par diverger de la
// première, et c'est toujours celle qu'on regarde le moins qui se trompe.
//
// La hiérarchie secteur > département vient de `sectors` / `departments`
// (secteursListe), la même que partout ailleurs.
// ─────────────────────────────────────────────────────────────────────────────

// ── Départements rangés sous leur secteur ────────────────────────────────────
// secteursCharge() D'ABORD : c'est elle qui va chercher includes/secteurs.php.
// Sans cet appel, secteursListe() n'est même pas définie et le test
// function_exists() échouait en silence — toute la semaine s'affichait alors
// sous « Sans secteur », sans que rien ne signale pourquoi.
if (function_exists('secteursCharge')) {
    secteursCharge();
}
$arbreSecteurs = function_exists('secteursListe') ? secteursListe($db) : [];

$secteurParDept = [];   // nom de département => nom de secteur
foreach ($arbreSecteurs as $sec) {
    foreach ($sec['departements'] as $dep) {
        $secteurParDept[(string) $dep['nom']] = (string) $sec['nom'];
    }
}

// ── La grille : secteur > département > jour > lignes ────────────────────────
// Une « ligne » = une place sur un créneau : soit quelqu'un d'affecté, soit un
// siège libre. C'est exactement ce que le classeur montrait.
$grille = [];
foreach ($requests as $request) {
    $dept = (string) $request['department_name'];
    $jour = (string) $request['shift_date'];
    $rid  = (int) $request['id'];

    // Un département inconnu du rangement n'est pas caché : il tombe dans
    // « Sans secteur », visible, plutôt que de disparaître de la semaine.
    $secteur = $secteurParDept[$dept] ?? fjhT('Sans secteur', 'Zonder sector');

    $affectations = $assignmentsByRequest[$rid] ?? [];
    $places = max((int) $request['seats_required'], count($affectations));

    for ($i = 0; $i < $places; $i++) {
        $a = $affectations[$i] ?? null;
        $nom = '';
        $agence = '';
        if ($a) {
            $nom = trim((string) ($a['prenom'] ?? '') . ' ' . (string) ($a['nom'] ?? ''));
            if ($nom === '') {
                $nom = (string) ($a['external_name'] ?? '');
            }
            $agence = (string) ($a['agency_name'] ?? ($a['interim'] ?? ''));
        }

        $grille[$secteur][$dept][$jour][] = [
            'horaire'       => (string) $request['time_slot'],
            'nom'           => $nom,
            'agence'        => $agence,
            'request_id'    => $rid,
            'seat'          => $i + 1,
            'assignment_id' => $a ? (int) $a['assignment_id'] : 0,
            'commentaire'   => (string) ($request['comment'] ?? ''),
        ];
    }
}

// Ordre d'affichage : celui des secteurs en base, tel qu'il y est rangé.
$ordreSecteurs = [];
foreach ($arbreSecteurs as $sec) {
    $ordreSecteurs[] = (string) $sec['nom'];
}
$ordreSecteurs[] = fjhT('Sans secteur', 'Zonder sector');

$semaineUrl = 'interim_horaires.php?week=' . urlencode($selectedWeekKey);
?>
<!DOCTYPE html>
<html lang="<?php echo e($pageLang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e(fjhT('Matching intérim', 'Matching interim')); ?></title>
<link rel="shortcut icon" type="image/x-icon" href="famijob_.ico">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 0 0 40px; color: #222; }

    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.15rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 7px 16px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .82rem; }
    .barre { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 12px 20px; background: #fff; border-bottom: 1px solid #dde5e0; }
    .barre select, .barre button { font-family: inherit; font-size: .88rem; padding: 7px 12px; border-radius: 8px; border: 1px solid #ccd6cf; background: #fff; }
    .barre button { background: #2d5a37; color: #fff; border: 0; font-weight: 700; cursor: pointer; }
    .alert { margin: 12px 20px; padding: 11px 16px; border-radius: 10px; font-weight: 600; font-size: .9rem; }
    .alert.success { background: #e7f6ea; color: #1e7a46; }
    .alert.error { background: #fdecea; color: #a3271c; }

    /* ── LA GRILLE ───────────────────────────────────────────────────────
       Reprise du classeur : une colonne par jour, chacune découpée en
       horaire / nom / agence. Le tableau défile horizontalement plutôt que
       de comprimer les colonnes jusqu'à l'illisible. */
    .cadre { overflow-x: auto; padding: 0 12px; }
    table.semaine { border-collapse: collapse; font-size: .72rem; width: max-content; min-width: 100%; }
    table.semaine th, table.semaine td { border: 1px solid #c8d3cc; padding: 2px 5px; white-space: nowrap; }

    .jour-tete { background: #2d5a37; color: #fff; font-size: .78rem; font-weight: 800; text-align: center; padding: 6px 4px; }
    .sous-tete { background: #f0f4f1; color: #55665c; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; text-align: center; }

    /* Les couleurs du classeur : secteur en vert, département en jaune. */
    .l-secteur td { background: #7ed321; color: #1d3d12; font-weight: 800; text-align: center; font-size: .78rem; padding: 3px; }
    .l-departement td { background: #ffff66; color: #4a4a00; font-weight: 700; text-align: center; font-size: .74rem; padding: 2px; }

    td.horaire { background: #fbfdfb; color: #33443a; text-align: center; font-variant-numeric: tabular-nums; }
    td.nom { min-width: 130px; }
    td.agence { min-width: 62px; color: #55665c; text-align: center; }
    td.vide-jour { background: #f7f9f8; }

    .place-libre { display: block; width: 100%; border: 1px dashed #b9cfc0; background: #fff; color: #2d5a37; border-radius: 5px; padding: 2px 6px; font-family: inherit; font-size: .7rem; font-weight: 700; cursor: pointer; text-align: left; }
    .place-libre:hover { background: #eef7f0; border-color: #2d5a37; }
    .occupe { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
    .retirer { border: 0; background: none; color: #b23; cursor: pointer; font-size: .78rem; padding: 0 2px; line-height: 1; }
    .retirer:hover { color: #7d1616; }

    .rien { padding: 30px 20px; text-align: center; color: #667; }

    /* ── FENÊTRE D'AFFECTATION ───────────────────────────────────────────
       UNE seule liste d'étudiants pour toute la page. Un menu déroulant par
       case, sur 7 jours et des dizaines de départements, aurait produit un
       document de plusieurs mégaoctets. */
    .voile { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: none; align-items: center; justify-content: center; z-index: 50; padding: 16px; }
    .voile.ouvert { display: flex; }
    .fenetre { background: #fff; border-radius: 16px; padding: 22px 24px; width: 100%; max-width: 430px; box-shadow: 0 20px 50px rgba(0,0,0,.3); }
    .fenetre h2 { margin: 0 0 4px; font-size: 1.05rem; color: #2d5a37; }
    .fenetre .ou { color: #667; font-size: .84rem; margin-bottom: 16px; }
    .fenetre label { display: block; font-weight: 700; font-size: .8rem; margin-bottom: 5px; color: #444; }
    .fenetre select, .fenetre input[type="text"] { width: 100%; padding: 9px 11px; border: 1px solid #ccd6cf; border-radius: 9px; font-family: inherit; font-size: .9rem; margin-bottom: 13px; }
    .fenetre .actions { display: flex; gap: 9px; justify-content: flex-end; }
    .btn { border: 0; border-radius: 22px; padding: 9px 18px; font-family: inherit; font-weight: 700; font-size: .85rem; cursor: pointer; }
    .btn-ok { background: #2d5a37; color: #fff; }
    .btn-non { background: #eef2ef; color: #445; }
</style>
</head>
<body>

<div class="bandeau">
    <h1><?php echo e(fjhT('Matching intérim — la semaine', 'Matching interim — de week')); ?></h1>
    <div>
        <a class="pill" href="interim_horaires_demandes.php"><?php echo e(fjhT('Demandes', 'Aanvragen')); ?></a>
        <a class="pill" href="index.php">&larr; <?php echo e(fjhT('Accueil', 'Onthaal')); ?></a>
    </div>
</div>

<div class="barre">
    <form method="GET" style="display:flex; gap:9px; align-items:center;">
        <label for="week" style="font-weight:700; font-size:.85rem;"><?php echo e(fjhT('Semaine', 'Week')); ?></label>
        <select name="week" id="week" onchange="this.form.submit()">
            <?php foreach ($weekOptions as $key => $option): ?>
                <option value="<?php echo e($key); ?>" <?php echo $key === $selectedWeekKey ? 'selected' : ''; ?>>
                    <?php echo e($option['label'] ?? $key); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php // L'auto-matching existe toujours dans le traitement : sans ce bouton,
          // la fonction serait devenue inatteignable en changeant d'écran. ?>
    <?php if ($isAdmin): ?>
    <form method="POST" onsubmit="return confirm('<?php echo e(fjhT('Lancer l\'auto-matching sur toute la semaine ?', 'Auto-matching voor de hele week starten?')); ?>');">
        <?php echo csrfField(); ?>
        <input type="hidden" name="week" value="<?php echo e($selectedWeekKey); ?>">
        <button type="submit" name="auto_match_week" value="1">⚡ <?php echo e(fjhT('Auto-matching de la semaine', 'Auto-matching van de week')); ?></button>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($message)) { echo $message; } ?>

<?php if (!$grille): ?>
    <div class="rien"><?php echo e(fjhT('Aucun créneau demandé cette semaine.', 'Geen aangevraagde tijdslots deze week.')); ?></div>
<?php else: ?>
<div class="cadre">
<table class="semaine">
    <thead>
        <tr>
            <?php foreach ($weekDays as $jour): ?>
                <th class="jour-tete" colspan="3"><?php echo e($jour['label']); ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($weekDays as $jour): ?>
                <th class="sous-tete"><?php echo e(fjhT('horaire', 'uren')); ?></th>
                <th class="sous-tete"><?php echo e(fjhT('nom', 'naam')); ?></th>
                <th class="sous-tete"><?php echo e(fjhT('agence', 'kantoor')); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php $colonnes = count($weekDays) * 3; ?>
    <?php foreach ($ordreSecteurs as $secteur): ?>
        <?php if (empty($grille[$secteur])) { continue; } ?>

        <tr class="l-secteur"><td colspan="<?php echo (int) $colonnes; ?>"><?php echo e($secteur); ?></td></tr>

        <?php foreach ($grille[$secteur] as $dept => $parJour): ?>
            <tr class="l-departement"><td colspan="<?php echo (int) $colonnes; ?>"><?php echo e($dept); ?></td></tr>

            <?php
            // Autant de lignes que le jour le plus chargé : les colonnes ne
            // sont pas alignées entre elles, exactement comme dans le classeur.
            $hauteur = 0;
            foreach ($weekDays as $jour) {
                $n = isset($parJour[$jour['key']]) ? count($parJour[$jour['key']]) : 0;
                if ($n > $hauteur) { $hauteur = $n; }
            }
            ?>

            <?php for ($ligne = 0; $ligne < $hauteur; $ligne++): ?>
                <tr>
                    <?php foreach ($weekDays as $jour): ?>
                        <?php $place = $parJour[$jour['key']][$ligne] ?? null; ?>
                        <?php if (!$place): ?>
                            <td class="vide-jour"></td>
                            <td class="vide-jour"></td>
                            <td class="vide-jour"></td>
                        <?php else: ?>
                            <td class="horaire"><?php echo e($place['horaire']); ?></td>
                            <td class="nom">
                                <?php if ($place['nom'] !== ''): ?>
                                    <span class="occupe">
                                        <span><?php echo e($place['nom']); ?></span>
                                        <?php if ($isAdmin): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo e(fjhT('Retirer cette personne ?', 'Deze persoon verwijderen?')); ?>');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="assignment_id" value="<?php echo (int) $place['assignment_id']; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $place['request_id']; ?>">
                                            <button type="submit" name="unassign_student" value="1" class="retirer" title="<?php echo e(fjhT('Retirer', 'Verwijderen')); ?>">×</button>
                                        </form>
                                        <?php endif; ?>
                                    </span>
                                <?php elseif ($isAdmin): ?>
                                    <?php // La case vide EST le bouton : c'est le geste du classeur,
                                          // on clique là où le nom doit apparaître. ?>
                                    <button type="button" class="place-libre"
                                            data-request="<?php echo (int) $place['request_id']; ?>"
                                            data-ou="<?php echo e($dept . ' · ' . $jour['label'] . ' · ' . $place['horaire']); ?>">
                                        + <?php echo e(fjhT('à pourvoir', 'in te vullen')); ?>
                                    </button>
                                <?php else: ?>
                                    <span style="color:#aab;">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="agence"><?php echo e($place['agence']); ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<div class="voile" id="voile">
    <div class="fenetre">
        <h2><?php echo e(fjhT('Affecter quelqu\'un', 'Iemand toewijzen')); ?></h2>
        <div class="ou" id="fenetreOu"></div>

        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="request_id" id="fenetreRequest" value="">

            <label for="student_id"><?php echo e(fjhT('Dans la liste', 'Uit de lijst')); ?></label>
            <select name="student_id" id="student_id">
                <option value="0">— <?php echo e(fjhT('choisir', 'kiezen')); ?> —</option>
                <?php // $studentOptions porte 'label' (prénom + nom déjà assemblés),
                      // pas 'prenom'/'nom' séparés. ?>
                <?php foreach ($studentOptions as $etu): ?>
                    <option value="<?php echo (int) $etu['id']; ?>">
                        <?php echo e(trim((string) $etu['label'])); ?><?php echo !empty($etu['interim']) ? ' — ' . e($etu['interim']) : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php // Champ libre conservé : le traitement sait déjà retrouver un
                  // inscrit par son nom, et affecter en texte libre s'il n'en est
                  // pas un. C'est ce qui permet d'écrire quelqu'un qui n'a pas
                  // encore de compte, comme dans le classeur. ?>
            <label for="student_name"><?php echo e(fjhT('Ou taper un nom', 'Of een naam typen')); ?></label>
            <input type="text" name="student_name" id="student_name" autocomplete="off" placeholder="<?php echo e(fjhT('Prénom Nom', 'Voornaam Naam')); ?>">

            <div class="actions">
                <button type="button" class="btn btn-non" id="fermer"><?php echo e(fjhT('Annuler', 'Annuleren')); ?></button>
                <button type="submit" name="assign_student" value="1" class="btn btn-ok"><?php echo e(fjhT('Affecter', 'Toewijzen')); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var voile = document.getElementById('voile');
    var champRequest = document.getElementById('fenetreRequest');
    var ou = document.getElementById('fenetreOu');
    if (!voile) { return; }

    function ouvrir(bouton) {
        champRequest.value = bouton.getAttribute('data-request');
        ou.textContent = bouton.getAttribute('data-ou') || '';
        document.getElementById('student_id').value = '0';
        document.getElementById('student_name').value = '';
        voile.classList.add('ouvert');
        document.getElementById('student_id').focus();
    }

    function fermer() { voile.classList.remove('ouvert'); }

    // Délégation : les cases se comptent par centaines, on ne pose pas un
    // écouteur sur chacune.
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('.place-libre') : null;
        if (b) { ouvrir(b); }
    });

    document.getElementById('fermer').addEventListener('click', fermer);
    voile.addEventListener('click', function (e) { if (e.target === voile) { fermer(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fermer(); } });
}());
</script>
<?php endif; ?>

</body>
</html>
