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

// Les comptes AGENCE entrent ici : c'est leur outil de travail, ils y placent
// leurs interimaires. Ce qu'ils peuvent y faire ne change pas d'un pouce — les
// regles « non-admin » qui existaient deja les encadrent : ils ne voient que
// leurs propres etudiants dans la liste, ne peuvent affecter ou retirer que
// leurs gens, et l'auto-matching leur reste ferme.
//
// La seule chose en plus : les noms des AUTRES agences leur sont masques.
// Voir includes/confidentialite.php.
$role = getCurrentRole();
if (!in_array($role, ['admin', 'teamcoach', 'agence_interim'], true)) {
    header('Location: ' . famijobSiteUrl('index.php'));
    exit();
}

require_once __DIR__ . '/includes/confidentialite.php';
require_once __DIR__ . '/includes/validation_planning.php';

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
        UNIQUE KEY uniq_shift_request (shift_date, department_name, time_slot, validation_status),
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
// LE MOT DE L'AGENCE. Elle place quelqu'un et veut parfois dire quelque chose
// avec — « arrive 15 min plus tard », « premiere fois chez vous », « parle mal
// francais ». Sans un endroit pour l'ecrire, ca se dit par telephone, ou pas
// du tout. C'est porte par l'AFFECTATION et non par le creneau : le mot
// concerne la personne placee, il part avec elle si on la retire.
if (!isset($assignmentColumns['agency_comment'])) {
    $db->exec('ALTER TABLE interim_shift_assignments ADD COLUMN agency_comment VARCHAR(500) NULL AFTER agency_name');
    $assignmentColumns['agency_comment'] = true;
}

$agencyName = '';
if (!$isAdmin) {
    $agencyStmt = $db->prepare('SELECT interim FROM utilisateurs WHERE id = ? LIMIT 1');
    $agencyStmt->execute([$currentUserId]);
    $agencyName = trim((string) $agencyStmt->fetchColumn());
}

// Le mot laisse par l'agence au moment d'affecter. Coupe a la taille de la
// colonne : une saisie plus longue serait tronquee par MySQL sans rien dire.
$commentaireAgence = mb_substr(trim((string) ($_POST['agency_comment'] ?? '')), 0, 500);

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

// ── LE PLANNING VALIDE EST VERROUILLE ───────────────────────────────────────
// Une semaine validee est une semaine dont les horaires sont PARTIS : chez les
// etudiants, chez les agences. La modifier en douce ferait travailler des gens
// sur un planning qui n'est plus celui qu'ils ont recu.
//
// On ne l'interdit pas pour autant — la vie change les plannings. Il faut
// seulement le vouloir : « Modifier » rouvre la semaine, et la validation
// suivante ne previendra QUE ceux dont l'horaire a change.
//
// ⚠️ ICI ET PAS PLUS HAUT : $selectedWeek n'existe qu'a partir de cette ligne.
// Place avant, l'appel recevait null et la page mourait avant d'afficher quoi
// que ce soit.
//
// ⚠️ Le verrou est teste dans le TRAITEMENT, pas seulement a l'affichage. Un
// bouton cache n'empeche pas un POST.
$etatSemaine = famijobStatutSemaine($db, $selectedWeek['start']);
$planningVerrouille = ($etatSemaine['statut'] === 'valide');

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

    // ── ROUVRIR UNE SEMAINE VALIDEE ──────────────────────────────────────
    // Le meme emplacement que « Valider » : c'est le meme geste, dans l'autre
    // sens. Les empreintes d'envoi ne sont PAS effacees — c'est ce qui permet
    // au prochain envoi de ne toucher que les gens reellement impactes.
    if ($isAdmin && isset($_POST['rouvrir_planning'])) {
        famijobRouvrePlanningSemaine($db, $selectedWeek['start'], $currentUserId);
        $etatSemaine = famijobStatutSemaine($db, $selectedWeek['start']);
        $planningVerrouille = false;
        $message .= "<div class='alert success'>Planning rouvert. Vous pouvez le modifier ;"
                  . ' à la prochaine validation, seules les personnes concernées par un changement seront prévenues.</div>';
    }

    // ⚠️ TOUTE ECRITURE EST REFUSEE TANT QUE LA SEMAINE EST VALIDEE. Le test
    // est ici, en amont des trois traitements (affecter, retirer, auto-matching)
    // plutot que recopie dans chacun : un quatrieme traitement ajoute demain
    // serait protege sans que personne ait a y penser.
    $ecritureDemandee = isset($_POST['assign_student']) || isset($_POST['unassign_student'])
        || isset($_POST['auto_match_request']) || isset($_POST['auto_match_week']);
    if ($planningVerrouille && $ecritureDemandee) {
        $message .= "<div class='alert error'>Ce planning est validé : les horaires sont déjà partis."
                  . ' Cliquez sur <strong>Modifier</strong> pour le rouvrir.</div>';
        $_POST = ['week' => $_POST['week'] ?? ''];
    }

    // ── VALIDER LE PLANNING DE LA SEMAINE ────────────────────────────────
    // Le geste qui dit « c'est arrete ». Reserve aux admins : une agence ne
    // decide pas que la semaine de Famiflora est finie.
    if ($isAdmin && isset($_POST['valider_planning'])) {
        $rapport = famijobValidePlanningSemaine($db, $selectedWeek['start'], $currentUserId);

        $lignes = [];
        if ($rapport['ok']) {
            $lignes[] = '<strong>' . count($rapport['ok']) . ' envoi(s) partis :</strong> '
                      . e(implode(', ', $rapport['ok']));
        }
        if ($rapport['ignores']) {
            $lignes[] = '<strong>Non concernés :</strong> ' . e(implode(' · ', $rapport['ignores']));
        }
        if ($rapport['ko']) {
            // Les echecs en ROUGE et separes : un rapport qui melange les deux
            // se lit « c'est parti », et on ne rappelle jamais les manquants.
            $message .= "<div class='alert error'><strong>Échecs :</strong> "
                      . e(implode(' | ', $rapport['ko'])) . '</div>';
        }
        $message .= "<div class='alert success'>Planning de la semaine validé."
                  . ($lignes ? '<br>' . implode('<br>', $lignes) : '') . '</div>';
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
                    // Sans ca, le mot de l'agence serait perdu des qu'une
                    // question est posee — et personne ne le retape.
                    'agency_comment' => $commentaireAgence,
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
                    'INSERT INTO interim_shift_assignments (request_id, seat_number, student_id, external_name, assigned_by_user_id, agency_name, agency_comment) VALUES (?, ?, NULL, ?, ?, ?, ?)'
                );
                $insertAssignStmt->execute([
                    $requestId,
                    $nextSeat,
                    $externalName,
                    $currentUserId,
                    $isAdmin ? '' : $agencyName,
                    $commentaireAgence !== '' ? $commentaireAgence : null,
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

                // Meme remarque que pour le retrait : une seule facon de comparer
                // deux noms d'agence dans toute l'application.
                $studentInterim = trim((string) ($student['interim'] ?? ''));
                if (!$isAdmin && !famijobMemeAgence($studentInterim, $agencyName)) {
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
                    'INSERT INTO interim_shift_assignments (request_id, seat_number, student_id, assigned_by_user_id, agency_name, agency_comment) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insertAssignStmt->execute([
                    $requestId,
                    $nextSeat,
                    $studentId,
                    $currentUserId,
                    $isAdmin ? $studentInterim : $agencyName,
                    $commentaireAgence !== '' ? $commentaireAgence : null,
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

                // ⚠️ MEME COMPARAISON QU'A L'AFFICHAGE. Un `!==` strict ici et une
                // comparaison souple pour decider d'afficher la croix, et
                // « Randstad » face a « randstad » donnait un bouton qui refuse
                // de fonctionner — le pire des deux mondes.
                $ownerAgency = trim((string) ($assignmentRow['owner_agency'] ?? ''));
                if (!$isAdmin && !famijobMemeAgence($ownerAgency, $agencyName)) {
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
                a.agency_comment,
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
// ⚠️ « true » : INCLURE LES SECTEURS SANS DEPARTEMENT. Par defaut
// secteursListe() les ecarte — utile pour un menu de departements, faux ici.
// Un secteur sans departement recoit quand meme des horaires (on a le droit de
// ne pas preciser), et il etait absent de $arbreSecteurs : donc absent du menu
// des secteurs, et ses creneaux ranges sous « Sans secteur ». Choisir ce
// secteur dans le filtre etait litteralement impossible.
$arbreSecteurs = function_exists('secteursListe') ? secteursListe($db, true) : [];

// ⚠️ LES NOMS DANS LES DEMANDES NE SONT PAS PROPRES. `department_name` est du
// texte libre saisi au fil des années : « Plantes extérieur » au singulier pour
// le secteur « Plantes extérieures », « Garden » pour un rayon qui a changé de
// nom. Une comparaison stricte laissait 126 créneaux sur le carreau, rangés en
// « Sans secteur ».
//
// On compare donc sur une forme NORMALISÉE (sans accent, sans casse, sans
// ponctuation) — secteursNormalise() fait déjà ce travail pour le reste du
// site, on ne réinvente pas la sienne.
$secteurParDept = [];   // clé normalisée => nom de secteur
$nomsSecteurs   = [];   // clé normalisée => nom de secteur
$libelleDept    = [];   // clé normalisée => libellé propre du département

$cle = function ($s) {
    return function_exists('secteursNormalise') ? secteursNormalise($s) : strtolower(trim((string) $s));
};

foreach ($arbreSecteurs as $sec) {
    $nomsSecteurs[$cle($sec['nom'])] = (string) $sec['nom'];
    foreach ($sec['departements'] as $dep) {
        $secteurParDept[$cle($dep['nom'])] = (string) $sec['nom'];
        $libelleDept[$cle($dep['nom'])]    = (string) $dep['nom'];
    }
}

// Ce que la normalisation ne rattrape pas : un pluriel manquant, un nom
// abandonné. Ces cas-là s'écrivent à la main, une fois, plutôt que de laisser
// des créneaux invisibles.
//
// ⚠️ Ces alias ne CORRIGENT PAS la base : ils la lisent telle qu'elle est. Le
// jour où `interim_shift_requests` sera nettoyée, ils deviendront inutiles sans
// rien casser.
$aliasSecteurs = [
    'plantes exterieur' => 'Plantes extérieures',   // singulier, 81 créneaux
];

// ── La grille : secteur > département > jour > lignes ────────────────────────
// Une « ligne » = une place sur un créneau : soit quelqu'un d'affecté, soit un
// siège libre. C'est exactement ce que le classeur montrait.
$grille = [];
foreach ($requests as $request) {
    $dept = (string) $request['department_name'];
    $jour = (string) $request['shift_date'];
    $rid  = (int) $request['id'];

    // ⚠️ LA RÈGLE, et elle vaut la peine d'être comprise : `department_name`
    // ne porte PAS toujours un département. Quand une demande d'horaire ne
    // précise pas le département, elle porte le nom du SECTEUR — « Famizoo »,
    // « Plantes extérieures ». Ces créneaux-là se rangent directement sous le
    // bandeau vert du secteur, sans bandeau jaune.
    //
    // Trois cas, dans cet ordre :
    //   1. c'est un SECTEUR          → sous le vert, pas de jaune ;
    //   2. c'est un DÉPARTEMENT      → sous le vert de son secteur, puis jaune ;
    //   3. inconnu                   → « Sans secteur », visible plutôt que
    //      disparu de la semaine.
    //
    // Cas 2 particulier : un département qui porte le MÊME nom que son secteur
    // (« Plantes intérieures ») ne redonne pas un bandeau jaune identique au
    // vert juste au-dessus — ses lignes rejoignent celles du secteur.
    $k = $cle($dept);
    if (isset($aliasSecteurs[$k])) {
        // Nom abandonné ou mal orthographié, rattaché à la main à son secteur.
        $secteur = $aliasSecteurs[$k];
        $sousTitre = '';
    } elseif (isset($nomsSecteurs[$k])) {
        $secteur = $nomsSecteurs[$k];       // libellé propre, pas celui de la demande
        $sousTitre = '';                    // rien à afficher en jaune
    } elseif (isset($secteurParDept[$k])) {
        $secteur = $secteurParDept[$k];
        $propre = $libelleDept[$k] ?? $dept;
        $sousTitre = ($cle($propre) === $cle($secteur)) ? '' : $propre;
    } else {
        $secteur = fjhT('Sans secteur', 'Zonder sector');
        $sousTitre = $dept;
    }

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
            $agence = trim((string) ($a['agency_name'] ?? ''));
            if ($agence === '') { $agence = trim((string) ($a['interim'] ?? '')); }
        }

        // Un compte agence ne lit pas les noms des autres agences. La place
        // reste montree comme PRISE — l'effacer sans rien dire reviendrait a la
        // proposer une seconde fois.
        $lecture = famijobNomLisible($nom, $agence, $role, $agencyName);

        // Le mot de l'agence suit sa place — et disparait avec le nom quand la
        // place appartient a une autre agence. Il est adresse a Famiflora, pas
        // aux concurrents : « premiere mission chez vous » en dit deja long sur
        // qui a ete place.
        $motAgence = ($a && !$lecture['masque']) ? trim((string) ($a['agency_comment'] ?? '')) : '';

        $grille[$secteur][$sousTitre][$jour][] = [
            'horaire'       => (string) $request['time_slot'],
            'nom'           => $lecture['nom'],
            'masque'        => $lecture['masque'],
            'agence'        => $lecture['masque'] ? '' : $agence,
            'mot'           => $motAgence,
            // Retirer quelqu'un obeit a la meme regle que dans la vue
            // detaillee : ses propres gens, ou tout le monde pour un admin.
            // Le classeur ne montrait la croix qu'aux admins — une agence
            // pouvait placer quelqu'un sans pouvoir corriger son erreur.
            // Verrouille = on ne retire plus personne. La croix disparait pour
            // tout le monde, y compris l'admin : c'est lui qui a valide.
            'peutRetirer'   => ($a !== null) && !$planningVerrouille
                               && ($isAdmin || famijobMemeAgence($agence, $agencyName)),
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

// ── FILTRE D'AFFICHAGE : SECTEUR, PUIS DÉPARTEMENT ───────────────────────────
// 9 secteurs et 58 départements font des centaines de lignes ; on travaille sur
// une partie à la fois. Le filtre ne touche QUE l'affichage — aucun créneau
// n'est masqué en base, et l'auto-matching continue de porter sur la semaine
// entière.
$mSecteur = trim((string) ($_GET['m_secteur'] ?? ''));
$mDept    = trim((string) ($_GET['m_dept'] ?? ''));

// ⚠️ LE SECTEUR SE VALIDE SUR LA LISTE DES SECTEURS, PAS SUR LA GRILLE.
// $grille ne contient que les secteurs qui ont des creneaux CETTE SEMAINE.
// Valider dessus faisait qu'un secteur sans creneau retombait dans le « sinon »
// et remettait le filtre a zero : on choisissait « Famizoo », tout le tableau
// revenait, et le menu reaffichait « Tous ». Le filtre avait l'air casse alors
// qu'il n'y avait simplement rien a montrer.
//
// Maintenant le choix TIENT : la grille se vide, le menu garde le secteur, et
// la vue dit qu'il n'y a pas de creneau plutot que d'en montrer 300 autres.
if ($mSecteur !== '' && !in_array($mSecteur, $ordreSecteurs, true)) {
    $mSecteur = '';   // secteur inconnu (lien trafique, secteur supprime)
}
if ($mSecteur === '') {
    $mDept = '';      // un departement sans son secteur ne veut rien dire
}

if ($mSecteur !== '') {
    $grille = [$mSecteur => $grille[$mSecteur] ?? []];

    if ($mDept !== '') {
        // La clé vide — les créneaux du secteur lui-même — reste toujours
        // visible : sinon choisir un département ferait disparaître les
        // demandes « tout le secteur » sans que rien ne l'explique.
        $garde = [];
        if (isset($grille[$mSecteur][''])) { $garde[''] = $grille[$mSecteur]['']; }
        if (isset($grille[$mSecteur][$mDept])) { $garde[$mDept] = $grille[$mSecteur][$mDept]; }
        $grille[$mSecteur] = $garde;
    }
}

// ⚠️ LA VUE LISTE OBEIT AU MEME FILTRE. Elle part de $requestsByDate, pas de
// $grille : le filtre secteur ne l'atteignait pas du tout. On choisissait un
// secteur, on basculait sur la vue detaillee, et les 300 creneaux de la semaine
// revenaient — le filtre semblait ne marcher qu'une fois sur deux.
//
// Le secteur se RESOUT ici comme ailleurs, il ne se lit pas dans une colonne :
// `department_name` est du texte libre qui peut porter un departement comme un
// secteur. Une seule fonction pour les trois usages ci-dessous, sinon ils
// finiraient par ne plus ranger pareil.
$placeDe = function ($departmentName) use ($cle, $aliasSecteurs, $nomsSecteurs, $secteurParDept, $libelleDept) {
    $k = $cle((string) $departmentName);
    if (isset($aliasSecteurs[$k]))      { return ['secteur' => $aliasSecteurs[$k], 'sous' => '']; }
    if (isset($nomsSecteurs[$k]))       { return ['secteur' => $nomsSecteurs[$k],  'sous' => '']; }
    if (isset($secteurParDept[$k])) {
        $sec    = $secteurParDept[$k];
        $propre = $libelleDept[$k] ?? (string) $departmentName;
        return ['secteur' => $sec, 'sous' => ($cle($propre) === $cle($sec)) ? '' : $propre];
    }
    return null;   // sans secteur connu : hors du filtre par definition
};

// Un creneau porte au nom du secteur (« sous » vide) reste visible quel que
// soit le departement choisi : il appartient a tout le secteur.
$retenu = function ($departmentName) use ($placeDe, $mSecteur, $mDept) {
    $p = $placeDe($departmentName);
    if ($p === null || $p['secteur'] !== $mSecteur) { return false; }
    return !($mDept !== '' && $p['sous'] !== '' && $p['sous'] !== $mDept);
};

if ($mSecteur !== '') {
    foreach ($requestsByDate as $jour => $liste) {
        $garde = [];
        foreach ($liste as $r) {
            if ($retenu((string) $r['department_name'])) { $garde[] = $r; }
        }
        if ($garde) { $requestsByDate[$jour] = $garde; }
        else        { unset($requestsByDate[$jour]); }
    }

    // Le recap « reste a pourvoir » compte par departement : sans ce passage il
    // annoncerait des departements que la liste ne montre plus.
    foreach ($remainingByDayDept as $jour => $parDept) {
        $garde = [];
        foreach ($parDept as $nomDept => $reste) {
            if ($retenu((string) $nomDept)) { $garde[$nomDept] = $reste; }
        }
        $remainingByDayDept[$jour] = $garde;
    }

    // $visibleWeekDays a ete calcule AVANT ce filtre : un jour vide serait
    // reste affiche, avec son titre et rien dessous.
    $visibleWeekDays = [];
    foreach ($weekDays as $weekDay) {
        $dayKey = (string) $weekDay['key'];
        if ($selectedDayFilter !== 'all' && $dayKey !== $selectedDayFilter) { continue; }
        if (empty($requestsByDate[$dayKey])) { continue; }
        $visibleWeekDays[] = $weekDay;
    }
}

// Les départements proposés au second menu, pour le secteur choisi.
$mDeptsProposes = [];
if ($mSecteur !== '') {
    foreach ($arbreSecteurs as $sec) {
        if ((string) $sec['nom'] !== $mSecteur) { continue; }
        foreach ($sec['departements'] as $dep) {
            if ((string) $dep['nom'] === $mSecteur) { continue; }
            $mDeptsProposes[] = (string) $dep['nom'];
        }
    }
}

$semaineUrl = 'interim_horaires.php?week=' . urlencode($selectedWeekKey);
// (le PHP reste ouvert : l aiguillage vers la vue est du code, pas du texte)
// ─────────────────────────────────────────────────────────────────────────────
// DEUX VUES, UN SEUL TRAITEMENT.
//
// Le classeur ne convient pas à tout le monde : la vue d'origine, une carte par
// créneau, montre des choses que la grille ne peut pas tenir — disponibilités,
// avertissements, remplissage détaillé. On garde donc les deux, et c'est
// l'utilisateur qui choisit.
//
// Chaque vue est un fichier à part, et toutes deux lisent les MÊMES variables
// préparées ci-dessus. Rien de ce qui écrit en base n'est dupliqué : changer de
// vue ne change que ce qu'on regarde.
// ─────────────────────────────────────────────────────────────────────────────
// ⚠️ LE PARAMETRE S'APPELLE « affichage », PLUS « vue ». Le nom etait DEJA
// PRIS par le filtre de remplissage de la vue liste (tous / a pourvoir /
// attribues). Deux champs du meme nom dans le meme formulaire : le second
// ecrasait le premier, si bien que filtrer depuis la vue liste renvoyait
// « vue=a_pourvoir », donc ni « liste » ni « excel » — et on repartait sur la
// vue classeur a chaque filtre. C'est ce qui donnait l'impression que les
// filtres ne marchaient pas.
//
// L'ancien nom reste accepte pour ne pas casser les liens deja envoyes, mais
// seulement quand il porte une valeur de VUE.
$vueMode = (string) ($_GET['affichage'] ?? '');
if ($vueMode === '' && in_array((string) ($_GET['vue'] ?? ''), ['excel', 'liste'], true)) {
    $vueMode = (string) $_GET['vue'];
}
if (!in_array($vueMode, ['excel', 'liste'], true)) {
    $vueMode = 'excel';
}

// ⚠️ UNE AGENCE N'A QUE LE CLASSEUR. La vue detaillee affiche les
// disponibilites, les suggestions et le remplissage de chaque creneau : c'est
// l'outil d'arbitrage de Famiflora, pas celui d'un fournisseur. Le verrou est
// ici et pas seulement sur le bouton — une bascule cachee laisse l'URL ouverte.
$peutChangerDeVue = !famijobEstCompteAgence($role);

// ⚠️ RELECTURE DE L'ETAT. Il a ete lu avant les traitements, mais valider ou
// rouvrir vient peut-etre de le changer : l'affichage doit montrer l'etat
// d'APRES, pas celui d'avant le clic.
$etatSemaine = famijobStatutSemaine($db, $selectedWeek['start']);
$planningVerrouille = ($etatSemaine['statut'] === 'valide');
if (!$peutChangerDeVue) {
    $vueMode = 'excel';
}

// Conserve la semaine ET LES FILTRES dans les liens : changer de vue ne doit
// pas ramener sur la semaine courante, tous secteurs confondus. Un filtre qui
// se vide quand on change de fenetre passe pour un filtre qui ne marche pas.
$lienVue = static function ($mode) use ($selectedWeekKey, $mSecteur, $mDept,
                                        $selectedDayFilter, $selectedVueFilter, $matchingMode) {
    $p = ['week' => $selectedWeekKey, 'affichage' => $mode];
    if ($mSecteur !== '')                 { $p['m_secteur'] = $mSecteur; }
    if ($mDept !== '')                    { $p['m_dept'] = $mDept; }
    if ($selectedDayFilter !== 'all')     { $p['day'] = $selectedDayFilter; }
    if ($selectedVueFilter !== 'all')     { $p['vue'] = $selectedVueFilter; }
    if ($matchingMode !== '')             { $p['matching_mode'] = $matchingMode; }
    return 'interim_horaires.php?' . http_build_query($p);
};

require __DIR__ . ($vueMode === 'liste'
    ? '/includes/vue_matching_liste.php'
    : '/includes/vue_matching_excel.php');
