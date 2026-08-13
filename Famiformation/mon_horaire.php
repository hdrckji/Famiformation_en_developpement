<?php
require_once 'config.php';
verifierConnexion($db);

// famiLang() vient de FamiJob ; côté FamiFormation la langue passe par
// currentLang() / t(). Cette page appelait famiLang() sans l'avoir : erreur
// fatale dès la ligne suivante, donc page blanche au lieu du planning.
// Même définition gardée que interim_horaires_demandes.php,
// validation_demandes_horaires.php et admin_disponibilites_etudiants.php,
// qui avaient déjà rencontré le problème.
if (!function_exists('famiLang')) {
    function famiLang()
    {
        $lang = strtolower(trim((string) ($_SESSION['fami_lang'] ?? 'fr')));
        return in_array($lang, ['fr', 'nl'], true) ? $lang : 'fr';
    }
}

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

// ─────────────────────────────────────────────────────────────────────────────
// LA SEMAINE EN GRILLE : les 7 jours en COLONNES, un département par LIGNE.
//
// L'affichage précédent empilait 7 tableaux de 4 colonnes, un par jour. Sur une
// semaine à 2 créneaux, ça faisait 5 blocs « Aucun horaire attribué » à faire
// défiler pour trouver les 2 qui comptent. Une grille montre la semaine entière
// d'un coup d'œil, et les trous se lisent comme des trous.
//
// Une ligne par département (et non une seule ligne fourre-tout) : un étudiant
// qui fait Caisse le lundi et Garden le samedi voit deux métiers distincts, pas
// une bouillie dans la même bande.
// ─────────────────────────────────────────────────────────────────────────────
$joursSemaine = [];
$cursor = $selectedWeekStart;
$todayKey = $today->format('Y-m-d');
while ($cursor <= $selectedWeekEnd) {
    $key = $cursor->format('Y-m-d');
    $joursSemaine[] = [
        'key'      => $key,
        'nom'      => nomDuJour($key),
        'chiffre'  => $cursor->format('d/m'),
        'estAuj'   => ($key === $todayKey),
        'weekend'  => in_array($cursor->format('N'), ['6', '7'], true),
    ];
    $cursor = $cursor->modify('+1 day');
}

// [département][date] => créneaux
$grille = [];
foreach ($rows as $row) {
    $dept = trim((string) ($row['department_name'] ?? ''));
    if ($dept === '') {
        $dept = monHoraireT('Sans département', 'Zonder afdeling');
    }
    $grille[$dept][(string) ($row['shift_date'] ?? '')][] = $row;
}
ksort($grille);

// Total d'heures de la semaine, quand les créneaux sont écrits « 9h-17h ».
// Purement indicatif : le texte est libre en base, on ne prétend pas le
// comprendre à tous les coups — d'où l'affichage « ~ » et le silence si on
// n'a rien su lire.
$heuresSemaine = 0.0;
$heuresLues = 0;
foreach ($rows as $row) {
    $d = dureeCreneau((string) ($row['time_slot'] ?? ''));
    if ($d !== null) {
        $heuresSemaine += $d;
        $heuresLues++;
    }
}

$weekLabel = monHoraireT('Semaine du ', 'Week van ') . $selectedWeekStart->format('d/m/Y') . monHoraireT(' au ', ' tot ') . $selectedWeekEnd->format('d/m/Y');
$isCurrentWeek = $selectedWeekStart->format('Y-m-d') === $currentWeekStart->format('Y-m-d');

/** Juste le nom du jour, sans la date : elle est affichée en dessous. */
function nomDuJour($dateValue)
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', (string) $dateValue);
    if (!$dt) {
        return (string) $dateValue;
    }

    $jours = famiLang() === 'nl'
        ? ['Monday' => 'Maandag', 'Tuesday' => 'Dinsdag', 'Wednesday' => 'Woensdag',
           'Thursday' => 'Donderdag', 'Friday' => 'Vrijdag', 'Saturday' => 'Zaterdag', 'Sunday' => 'Zondag']
        : ['Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
           'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 'Sunday' => 'Dimanche'];

    return $jours[$dt->format('l')] ?? $dt->format('l');
}

/**
 * Durée d'un créneau écrit à la main, en heures — ou null si on n'a pas su lire.
 *
 * Le créneau est du TEXTE LIBRE en base : « 9h-17h », « 09:00 - 17:30 »,
 * « 9h à 17h »... On reconnaît les formes courantes et on renonce proprement
 * pour le reste, plutôt que d'annoncer un total faux. Une nuit qui passe
 * minuit (22h-6h) est comptée comme telle.
 */
function dureeCreneau($texte)
{
    if (!preg_match_all('/(\d{1,2})\s*[h:]\s*(\d{2})?/i', (string) $texte, $m, PREG_SET_ORDER) || count($m) < 2) {
        return null;
    }

    // Les heures sont lues DEUX PAR DEUX : « 8h-12h / 13h-17h » compte les deux
    // demi-journées. Ne prendre que la première et la dernière donnerait 9 h au
    // lieu de 8 — la pause de midi comptée comme du travail.
    $total = 0.0;
    $paires = 0;

    for ($i = 0; $i + 1 < count($m); $i += 2) {
        $debut = (int) $m[$i][1]     + (isset($m[$i][2])     && $m[$i][2]     !== '' ? ((int) $m[$i][2]) / 60 : 0);
        $fin   = (int) $m[$i + 1][1] + (isset($m[$i + 1][2]) && $m[$i + 1][2] !== '' ? ((int) $m[$i + 1][2]) / 60 : 0);

        if ($debut > 24 || $fin > 24) {
            return null;
        }
        if ($fin <= $debut) {
            $fin += 24; // le créneau passe minuit
        }

        $duree = $fin - $debut;
        if ($duree <= 0 || $duree > 16) {
            return null;
        }

        $total += $duree;
        $paires++;
    }

    return ($paires > 0 && $total <= 16) ? $total : null;
}

/** Le contenu d'une case : les créneaux d'un jour, pour un département. */
function renderCase(array $items)
{
    if (empty($items)) {
        echo '<span class="rien">·</span>';
        return;
    }

    foreach ($items as $item) {
        $agence     = trim((string) ($item['agency_name'] ?? ''));
        $commentaire = trim((string) ($item['comment'] ?? ''));

        echo '<div class="creneau">';
        echo '<span class="heure">' . e((string) ($item['time_slot'] ?? '')) . '</span>';
        if ($agence !== '') {
            echo '<span class="agence">' . e($agence) . '</span>';
        }
        // Le commentaire est le seul champ vraiment libre : il peut être long,
        // donc il passe en info-bulle plutôt que d'étirer la colonne.
        if ($commentaire !== '') {
            echo '<span class="mot" title="' . e($commentaire) . '">' . e($commentaire) . '</span>';
        }
        echo '</div>';
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

        /* ── LA GRILLE DE LA SEMAINE ─────────────────────────────────────────
           7 colonnes de jours + 1 colonne de département. Les colonnes de jours
           ont toutes la MÊME largeur (table-layout: fixed) : sans ça, un
           commentaire un peu long élargit son jour et la semaine devient
           bancale, ce qui trompe l'œil sur la charge réelle. */
        table.grille {
            table-layout: fixed;
            min-width: 940px;
            border-collapse: separate;
            border-spacing: 0;
        }

        table.grille th,
        table.grille td {
            border-bottom: 1px solid var(--line);
            border-right: 1px solid var(--line);
            vertical-align: top;
            padding: 10px 9px;
        }
        table.grille tr > *:last-child { border-right: none; }
        table.grille tbody tr:last-child > * { border-bottom: none; }

        /* Colonne des départements : figée à gauche pour rester lisible quand
           la grille défile horizontalement sur un téléphone. */
        table.grille .col-dept {
            width: 150px;
            position: sticky;
            left: 0;
            z-index: 2;
            background: #fbfdfb;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--accent);
            text-transform: none;
            letter-spacing: 0;
            border-right: 2px solid var(--line);
        }

        table.grille thead th {
            text-align: center;
            background: #fbfdfb;
            padding: 9px 6px;
        }
        table.grille .jour-nom {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text);
        }
        table.grille .jour-date {
            display: block;
            font-size: 0.72rem;
            color: var(--muted);
            margin-top: 2px;
            font-weight: 400;
        }

        /* Le jour du jour : une colonne teintée sur toute sa hauteur. C'est le
           repère qu'on cherche en premier en ouvrant la page. */
        table.grille .auj { background: var(--soft); }
        table.grille thead th.auj .jour-nom { color: var(--accent); }
        table.grille .we { background: #fafbfa; }
        table.grille .auj.we { background: var(--soft); }
        table.grille td.creux { text-align: center; }

        .creneau {
            background: #fff;
            border: 1px solid var(--line);
            border-left: 3px solid var(--accent);
            border-radius: 8px;
            padding: 7px 9px;
            margin-bottom: 6px;
            box-shadow: 0 1px 3px rgba(22, 49, 33, 0.06);
        }
        .creneau:last-child { margin-bottom: 0; }
        .creneau .heure {
            display: block;
            font-weight: 700;
            font-size: 0.86rem;
            color: var(--text);
        }
        .creneau .agence,
        .creneau .mot {
            display: block;
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 2px;
        }
        /* Un commentaire long ne doit pas étirer la case : il est coupé et le
           texte complet reste disponible en info-bulle. */
        .creneau .mot {
            font-style: italic;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rien { color: #cbd6cf; }

        .semaine-vide {
            text-align: center;
            padding: 46px 20px;
            color: var(--muted);
        }
        .semaine-vide-icone { font-size: 2.4rem; margin-bottom: 8px; }
        .semaine-vide p { margin: 4px 0; }
        .semaine-vide .petit { font-size: 0.85rem; opacity: 0.8; }

        @media (max-width: 720px) {
            body { padding: 12px; }
            table.grille .col-dept { width: 110px; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="hero">
        <div>
            <?php // Le retour ramene la ou on etait. Cette page est hebergee par
                  // FamiFormation mais les etudiants y arrivent maintenant par la
                  // tuile FamiJob, qui pose ?from=famijob : sans ca, le lien les
                  // deposerait sur un site qu'ils n'avaient pas ouvert. ?>
            <?php $retourFamijob = (($_GET['from'] ?? '') === 'famijob'); ?>
            <a href="<?php echo $retourFamijob ? 'famijob/index.php' : 'index.php'; ?>" class="back-link">←
                <?php echo e($retourFamijob ? 'FamiJob' : 'FamiFormation'); ?></a>
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
            <div class="label"><?php echo e(monHoraireT('Heures estimees', 'Geschatte uren')); ?></div>
            <div class="value">
                <?php
                // Silence si aucun créneau n'a pu être lu : mieux vaut ne rien
                // annoncer qu'un total faux. Le « ~ » rappelle que le créneau
                // est du texte libre, pas une heure de début et de fin en base.
                if ($heuresLues === 0) {
                    echo '—';
                } else {
                    echo '~' . e(rtrim(rtrim(number_format($heuresSemaine, 1, ',', ''), '0'), ',')) . 'h';
                }
                ?>
            </div>
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
        <?php if (empty($grille)): ?>
            <div class="semaine-vide">
                <div class="semaine-vide-icone">🗓️</div>
                <p><?php echo e(monHoraireT('Aucun horaire attribue cette semaine.', 'Geen rooster toegewezen deze week.')); ?></p>
                <p class="petit"><?php echo e(monHoraireT('Utilise les fleches ci-dessus pour voir une autre semaine.', 'Gebruik de pijlen hierboven om een andere week te bekijken.')); ?></p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="grille">
                    <thead>
                        <tr>
                            <th class="col-dept"><?php echo e(monHoraireT('Departement', 'Afdeling')); ?></th>
                            <?php foreach ($joursSemaine as $j): ?>
                                <th class="<?= $j['estAuj'] ? 'auj' : '' ?><?= $j['weekend'] ? ' we' : '' ?>">
                                    <span class="jour-nom"><?= e($j['nom']) ?></span>
                                    <span class="jour-date"><?= e($j['chiffre']) ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($grille as $dept => $parJour): ?>
                        <tr>
                            <th class="col-dept" scope="row"><?= e($dept) ?></th>
                            <?php foreach ($joursSemaine as $j): ?>
                                <td class="<?= $j['estAuj'] ? 'auj' : '' ?><?= $j['weekend'] ? ' we' : '' ?><?= empty($parJour[$j['key']]) ? ' creux' : '' ?>">
                                    <?php renderCase($parJour[$j['key']] ?? []); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
