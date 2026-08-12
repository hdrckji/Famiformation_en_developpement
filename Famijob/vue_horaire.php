<?php
require_once 'config.php';
verifierConnexion($db);

$pageLang = famiLang();
if (!function_exists('fjvhT')) {
    function fjvhT($fr, $nl = null)
    {
        return famiLang() === 'nl' && $nl !== null ? $nl : $fr;
    }
}

// La vue horaire est LE tableau commun : l'admin la pilote, le teamcoach la
// consulte pour son secteur, l'étudiant y lit ses créneaux. La restreindre aux
// admins obligeait tout le monde à demander « et moi, je travaille quand ? ».
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if (!in_array($role, ['admin', 'teamcoach', 'etudiant'], true)) {
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
for ($offset = 0; $offset < 8; $offset++) {
    $weekStart = $startMonday->modify('+' . $offset . ' week');
    $weekEnd = $weekStart->modify('+6 days');
    $weekOptions[$weekStart->format('Y-m-d')] = [
        'start' => $weekStart,
        'end' => $weekEnd,
        'label' => fjvhT('Semaine du ', 'Week van ') . $weekStart->format('d/m/Y') . fjvhT(' au ', ' tot ') . $weekEnd->format('d/m/Y'),
    ];
}

$selectedWeekKey = (string) ($_GET['week'] ?? array_key_first($weekOptions));
if (!isset($weekOptions[$selectedWeekKey])) {
    $selectedWeekKey = array_key_first($weekOptions);
}
$selectedWeek = $weekOptions[$selectedWeekKey];

$departmentFilterOptions = [];
$departmentFilterStmt = $db->query(
    'SELECT department_name FROM departments WHERE is_active = 1 ORDER BY department_name ASC'
);
$departmentFilterOptions = $departmentFilterStmt->fetchAll(PDO::FETCH_COLUMN);

$selectedDepartment = trim((string) ($_GET['department'] ?? 'all'));
if ($selectedDepartment !== 'all' && !in_array($selectedDepartment, $departmentFilterOptions, true)) {
    $selectedDepartment = 'all';
}

// ── FILTRE PAR SECTEUR ───────────────────────────────────────────────────────
// Le rangement vient de includes/grille_semaine.php, partagé avec le matching
// et les demandes : un créneau ne peut pas relever du secteur Décoration ici et
// d'un autre là-bas.
//
// ⚠️ Le secteur ne se lit pas dans `interim_shift_requests` : la table ne porte
// qu'un `department_name` en texte libre, qui peut d'ailleurs être un nom de
// secteur. On le RÉSOUT donc créneau par créneau, plutôt que de filtrer en SQL
// sur une colonne qui n'existe pas.
require_once __DIR__ . '/includes/grille_semaine.php';
$vhRangement = grilleSemaineRangement($db);

$vhSecteurs = [];
foreach ($vhRangement['arbre'] as $secArbre) {
    $vhSecteurs[] = (string) $secArbre['nom'];
}

$selectedSecteur = trim((string) ($_GET['secteur'] ?? ''));
if ($selectedSecteur !== '' && !in_array($selectedSecteur, $vhSecteurs, true)) {
    $selectedSecteur = '';
}

// Les départements proposés suivent le secteur choisi : offrir les 58 quand on
// en regarde un seul, c'est proposer 50 filtres qui ne renverront rien.
if ($selectedSecteur !== '') {
    $departmentsDuSecteur = [];
    foreach ($vhRangement['arbre'] as $secArbre) {
        if ((string) $secArbre['nom'] !== $selectedSecteur) { continue; }
        foreach ($secArbre['departements'] as $depArbre) {
            $departmentsDuSecteur[] = (string) $depArbre['nom'];
        }
    }
    $departmentFilterOptions = $departmentsDuSecteur;
    if ($selectedDepartment !== 'all' && !in_array($selectedDepartment, $departmentFilterOptions, true)) {
        $selectedDepartment = 'all';
    }
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

$weekDays = [];
$cursor = $selectedWeek['start'];
while ($cursor <= $selectedWeek['end']) {
    $weekDays[] = [
        'key' => $cursor->format('Y-m-d'),
        'label' => $weekdayMap[$cursor->format('l')] ?? $cursor->format('l'),
        'date' => $cursor->format('d/m/Y'),
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

// Filtre secteur, applique apres coup pour la raison ci-dessus.
if ($selectedSecteur !== '') {
    $requests = array_values(array_filter($requests, static function ($r) use ($vhRangement, $selectedSecteur) {
        $place = grilleSemaineResout((string) $r['department_name'], $vhRangement);
        return $place['secteur'] === $selectedSecteur;
    }));
}

$assignmentsByRequest = [];
$requestIds = array_map(static function ($row) {
    return (int) $row['id'];
}, $requests);

if (!empty($requestIds)) {
    $placeholders = implode(', ', array_fill(0, count($requestIds), '?'));
    $assignmentsStmt = $db->prepare(
        "SELECT a.request_id, a.seat_number, a.external_name, u.nom, u.prenom
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

function famijobParseStartMinutesForView($timeSlot)
{
    $timeSlot = trim((string) $timeSlot);
    if ($timeSlot === '') {
        return null;
    }

    if (preg_match('/(\d{1,2})\s*(?:h|:)?\s*(\d{0,2})/i', $timeSlot, $matches)) {
        $hours = (int) ($matches[1] ?? 0);
        $minutes = trim((string) ($matches[2] ?? '')) === '' ? 0 : (int) $matches[2];
        return ($hours * 60) + $minutes;
    }

    return null;
}

function famijobTimeSlotSortView($a, $b)
{
    $ma = famijobParseStartMinutesForView($a);
    $mb = famijobParseStartMinutesForView($b);
    if ($ma === null && $mb === null) {
        return strcmp((string) $a, (string) $b);
    }
    if ($ma === null) {
        return 1;
    }
    if ($mb === null) {
        return -1;
    }
    return $ma <=> $mb;
}

// Regroupe les demandes par département puis par jour.
// La plage horaire n'est plus une colonne : elle apparaît dans chaque bulle.
$byDeptDay = [];
$departmentsInView = [];
foreach ($requests as $request) {
    $departmentName = (string) $request['department_name'];
    if ($selectedDepartment !== 'all' && $departmentName !== $selectedDepartment) {
        continue;
    }
    $departmentsInView[$departmentName] = true;
    $byDeptDay[$departmentName][(string) $request['shift_date']][] = $request;
}
$departmentsInView = array_keys($departmentsInView);
sort($departmentsInView, SORT_NATURAL | SORT_FLAG_CASE);

// Trie les demandes de chaque cellule (jour) par heure de début.
foreach ($byDeptDay as $departmentName => $byDay) {
    foreach ($byDay as $dateKey => $list) {
        usort($list, static function ($a, $b) {
            return famijobTimeSlotSortView((string) $a['time_slot'], (string) $b['time_slot']);
        });
        $byDeptDay[$departmentName][$dateKey] = $list;
    }
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
            --shadow: 0 14px 34px rgba(22, 49, 33, 0.1);
        }
        body { margin: 0; padding: 20px; background: var(--bg); font-family: 'Open Sans', sans-serif; color: var(--text); }
        .page { max-width: 1600px; margin: 0 auto; }
        .hero { background: linear-gradient(135deg, #264e35, #3f6b4d); color: #fff; border-radius: 24px; padding: 24px 28px; box-shadow: var(--shadow); margin-bottom: 18px; }
        .hero-top { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .hero h1 { margin: 8px 0 6px; font-size: 2rem; }
        .hero p { margin: 0; opacity: 0.95; line-height: 1.6; max-width: 920px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #fff; text-decoration: none; font-weight: 700; background: rgba(255,255,255,0.14); padding: 12px 18px; border-radius: 999px; }
        .toolbar { display: flex; justify-content: space-between; align-items: end; gap: 16px; margin-bottom: 18px; padding: 18px 22px; background: #fff; border-radius: 22px; box-shadow: var(--shadow); flex-wrap: wrap; }
        .toolbar form { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; }
        .btn-export { display:inline-flex; align-items:center; gap:8px; text-decoration:none;
            background:linear-gradient(135deg,#1f7a3d,#2fa757); color:#fff; font-weight:800; font-size:.92rem;
            padding:11px 18px; border-radius:12px; box-shadow:0 6px 16px rgba(31,122,61,.28); }
        .btn-export:hover { transform:translateY(-1px); }
        label { display: block; margin-bottom: 6px; font-size: 0.82rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
        input, select { width: 100%; box-sizing: border-box; border: 1px solid #cfdad3; border-radius: 12px; padding: 10px 11px; font-size: 0.95rem; font-family: inherit; background: #fff; }
        .btn { border: none; border-radius: 12px; padding: 10px 14px; font-weight: 700; cursor: pointer; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-soft { background: var(--accent-soft); color: var(--accent); }
        .legend { color: var(--muted); font-size: 0.88rem; }
        .table-wrap { overflow-x: auto; background: #fff; border-radius: 22px; box-shadow: var(--shadow); }
        table { width: 100%; border-collapse: collapse; min-width: 1300px; }
        th, td { border-bottom: 1px solid var(--line); border-right: 1px solid var(--line); padding: 10px 10px; vertical-align: top; text-align: left; }
        th:last-child, td:last-child { border-right: none; }
        th { background: #f8fbf9; font-size: 0.78rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); position: sticky; top: 0; z-index: 1; }
        .corner { min-width: 180px; background: #f8fbf9; }
        .slot-cell { background: #fbfdfb; width: 180px; }
        .slot-time { font-weight: 700; color: var(--accent); }
        .slot-dept { color: #244132; font-weight: 700; margin-top: 6px; }
        .slot-card { background: var(--accent-soft); border: 1px solid #d9e8dd; border-radius: 14px; padding: 10px 10px 8px; margin-bottom: 8px; }
        .slot-card.warn { background: #fff3e6; border-color: #f2c58e; }
        .slot-card strong { display: block; color: var(--text); margin-bottom: 4px; }
        .slot-card .meta { color: var(--muted); font-size: 0.84rem; line-height: 1.4; }
        .slot-empty { color: #8a9b91; font-size: 0.88rem; }
        .day-head { line-height: 1.35; }
        .day-head .date { color: var(--muted); font-weight: 600; text-transform: none; letter-spacing: 0; display: block; margin-top: 2px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 10px; font-size: 0.78rem; font-weight: 700; margin-top: 8px; background: #e8f2ea; color: #29553a; }
        .empty-state { background: #fff; border-radius: 22px; padding: 28px; box-shadow: var(--shadow); color: var(--muted); }
        .fami-lang-switcher {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.26);
            border-radius: 999px;
            padding: 4px;
        }
        .fami-lang-option {
            display: inline-block;
            text-decoration: none;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.78rem;
            letter-spacing: 0.04em;
            padding: 5px 9px;
            border-radius: 999px;
        }
        .fami-lang-option.is-active {
            background: #ffffff;
            color: var(--accent);
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
                <p><?php echo e(fjvhT('Lecture hebdomadaire du planning intérim. Cette page est volontairement en consultation seule, sans modification de données.', 'Wekelijkse weergave van de interimplanning. Deze pagina is alleen-lezen, zonder gegevenswijziging.')); ?></p>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <form method="get" action="">
            <div>
                <label for="week"><?php echo e(fjvhT('Semaine', 'Week')); ?></label>
                <select id="week" name="week" onchange="this.form.submit();">
                    <?php foreach ($weekOptions as $weekKey => $weekInfo): ?>
                        <option value="<?php echo e($weekKey); ?>" <?php echo $weekKey === $selectedWeekKey ? 'selected' : ''; ?>><?php echo e($weekInfo['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="secteur"><?php echo e(fjvhT('Secteur', 'Sector')); ?></label>
                <?php // Le menu des departements n'existe que si un secteur est
                      // choisi : on ne le remet a zero que s'il est la, sinon
                      // l'erreur JavaScript emporte l'envoi avec elle. ?>
                <select id="secteur" name="secteur"
                        onchange="if (this.form.department) { this.form.department.value = 'all'; } this.form.submit();">
                    <option value=""><?php echo e(fjvhT('Tous les secteurs', 'Alle sectoren')); ?></option>
                    <?php foreach ($vhSecteurs as $nomSecteur): ?>
                        <option value="<?php echo e($nomSecteur); ?>" <?php echo $selectedSecteur === $nomSecteur ? 'selected' : ''; ?>><?php echo e($nomSecteur); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="department"><?php echo e(fjvhT('Département', 'Afdeling')); ?></label>
                <select id="department" name="department" onchange="this.form.submit();">
                    <option value="all" <?php echo $selectedDepartment === 'all' ? 'selected' : ''; ?>><?php echo e($selectedSecteur !== '' ? e(fjvhT('Tout le secteur', 'Hele sector')) : e(fjvhT('Tous les départements', 'Alle afdelingen'))); ?></option>
                    <?php foreach ($departmentFilterOptions as $departmentName): ?>
                        <option value="<?php echo e($departmentName); ?>" <?php echo $selectedDepartment === $departmentName ? 'selected' : ''; ?>><?php echo e($departmentName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php // L'EXPORT EXCEL EST ICI, et plus dans le matching : c'est
              // l'ecran de consultation, celui qu'on imprime ou qu'on envoie.
              // Le matching sert a affecter, pas a diffuser.
              // Il suit les filtres affiches — exporter autre chose que ce
              // qu'on regarde est le meilleur moyen de diffuser un planning
              // faux. ?>
        <a class="btn-export" href="export_matching.php?week=<?php echo e($selectedWeekKey); ?><?php echo $selectedSecteur !== '' ? '&secteur=' . urlencode($selectedSecteur) : ''; ?><?php echo $selectedDepartment !== 'all' ? '&department=' . urlencode($selectedDepartment) : ''; ?>"
           title="<?php echo e(fjvhT('Exporter le planning affiché dans Excel', 'De getoonde planning naar Excel exporteren')); ?>">
            ↓ <?php echo e(fjvhT('Exporter Excel', 'Naar Excel')); ?>
        </a>
        <div class="legend"><?php echo e(fjvhT('Colonnes = jours de la semaine. Lignes = départements. L\'horaire est indiqué dans chaque bulle.', 'Kolommen = weekdagen. Rijen = afdelingen. Het uurrooster staat in elke bubbel.')); ?></div>
    </div>

    <?php if (empty($departmentsInView)): ?>
        <div class="empty-state"><?php echo e(fjvhT('Aucun créneau trouvé pour cette semaine.', 'Geen tijdsblok gevonden voor deze week.')); ?></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="corner"><?php echo e(fjvhT('Département', 'Afdeling')); ?></th>
                        <?php foreach ($weekDays as $day): ?>
                            <th>
                                <div class="day-head"><?php echo e($day['label']); ?><span class="date"><?php echo e($day['date']); ?></span></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departmentsInView as $departmentName): ?>
                        <tr>
                            <td class="slot-cell">
                                <div class="slot-dept"><?php echo e($departmentName); ?></div>
                            </td>
                            <?php foreach ($weekDays as $day): ?>
                                <?php $dayRequests = $byDeptDay[$departmentName][$day['key']] ?? []; ?>
                                <td>
                                    <?php if (empty($dayRequests)): ?>
                                        <div class="slot-empty">—</div>
                                    <?php else: ?>
                                        <?php foreach ($dayRequests as $request): ?>
                                            <?php $requestAssignments = $assignmentsByRequest[(int) $request['id']] ?? []; ?>
                                            <?php if (empty($requestAssignments)): ?>
                                                <div class="slot-card warn">
                                                    <strong>--</strong>
                                                    <div class="meta"><?php echo e(fjvhT('Horaire :', 'Uurrooster:')); ?> <?php echo e($request['time_slot']); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($requestAssignments as $assignment): ?>
                                                    <?php
                                                    $studentName = trim((string) ($assignment['prenom'] ?? '')) . ' ' . trim((string) ($assignment['nom'] ?? ''));
                                                    if (trim($studentName) === '') {
                                                        $studentName = trim((string) ($assignment['external_name'] ?? ''));
                                                    }
                                                    ?>
                                                    <div class="slot-card">
                                                        <strong><?php echo e(trim($studentName) !== '' ? $studentName : '--'); ?></strong>
                                                        <div class="meta"><?php echo e(fjvhT('Horaire :', 'Uurrooster:')); ?> <?php echo e($request['time_slot']); ?></div>
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
