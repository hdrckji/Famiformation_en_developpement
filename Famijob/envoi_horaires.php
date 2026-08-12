<?php
// ============================================================
// envoi_horaires.php — ENVOI DE L'HORAIRE DE LA SEMAINE AUX PERSONNES MATCHÉES.
//
//   Une ligne par personne affectée dans la semaine, avec le destinataire
//   calculé : agence d'intérim si la personne y est rattachée, le contact interne si
//   elle est chez Famiflora.
//
//   TANT QUE LE MODE TEST EST ACTIF, tout part vers l'adresse de test. Le
//   destinataire réel reste affiché et journalisé : on vérifie l'aiguillage
//   AVANT d'ouvrir les vannes, pas après. Le bandeau en haut de page dit
//   toujours dans quel mode on se trouve — voir famijobScheduleMailIsTestMode()
//   dans includes/horaires.php pour basculer.
//
//   Envoi en POST + redirection (PRG) : un rafraîchissement de page ne doit
//   jamais renvoyer une deuxième fois les mêmes mails.
// ============================================================

require_once 'config.php';
require_once __DIR__ . '/includes/horaires.php';
verifierConnexion($db);

$role = (string) ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: ' . famijobSiteUrl('index.php'));
    exit();
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

// --- Semaine sélectionnée ---
$today = new DateTimeImmutable('today');
$startMonday = $today->modify('monday this week');

$weekOptions = [];
for ($offset = -4; $offset < 8; $offset++) {
    $start = $startMonday->modify(($offset >= 0 ? '+' : '') . $offset . ' week');
    $end = $start->modify('+6 days');
    $weekOptions[$start->format('Y-m-d')] = [
        'start' => $start,
        'end' => $end,
        'label' => 'Semaine du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y')
            . ($offset === 0 ? ' (en cours)' : ($offset === 1 ? ' (prochaine)' : '')),
    ];
}

$selectedWeekKey = (string) ($_POST['week'] ?? $_GET['week'] ?? $startMonday->modify('+1 week')->format('Y-m-d'));
if (!isset($weekOptions[$selectedWeekKey])) {
    $selectedWeekKey = $startMonday->format('Y-m-d');
}
$weekStart = $weekOptions[$selectedWeekKey]['start'];
$weekEnd = $weekOptions[$selectedWeekKey]['end'];

$testMode = famijobScheduleMailIsTestMode();
$testRecipient = famijobScheduleMailTestRecipient();

$people = famijobLoadWeekAssignments($db, $weekStart, $weekEnd);

// Destinataires de chaque personne : elle-même + son agence (ou le contact
// interne, voir famijobResolveScheduleRecipient — aucun nom n'est écrit ici).
// Les agences se répètent d'une personne à l'autre, on met leur résolution en
// cache pour ne pas relire interim_agences à chaque ligne.
$agencyCache = [];
foreach ($people as $key => $person) {
    $cacheKey = mb_strtolower(trim((string) $person['agency']), 'UTF-8');
    if (!isset($agencyCache[$cacheKey])) {
        $agencyCache[$cacheKey] = famijobResolveScheduleRecipient($db, $person['agency']);
    }

    $targets = famijobResolveScheduleTargets($db, $person, $agencyCache[$cacheKey]);

    $people[$key]['targets'] = $targets;
    $people[$key]['target_student'] = $targets[0];
    $people[$key]['target_agency'] = $targets[1];
    $people[$key]['sendable'] = famijobScheduleTargetsAreSendable($targets);
}

// --- Aperçu d'un mail (nouvel onglet) ---
$previewKey = (string) ($_GET['preview'] ?? '');
if ($previewKey !== '') {
    if (!isset($people[$previewKey])) {
        http_response_code(404);
        exit('Personne introuvable pour cette semaine.');
    }

    $person = $people[$previewKey];
    $previewAs = ((string) ($_GET['as'] ?? 'student')) === 'agency' ? 'agency' : 'student';
    $target = $previewAs === 'agency' ? $person['target_agency'] : $person['target_student'];

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
        . '<title>Aperçu — ' . e($person['name']) . '</title></head><body style="margin:0;background:#eef4ef;">'
        . '<div style="padding:14px 20px;background:#21362a;color:#fff;font-family:Arial,sans-serif;font-size:13px;line-height:1.6;">'
        . 'Aperçu du mail envoyé à <strong>' . e($previewAs === 'agency' ? ('l\'agence ' . $target['label']) : 'la personne') . '</strong><br>'
        . 'Objet : <strong>' . e(famijobBuildScheduleMailSubject($person, $weekStart, $weekEnd, $target['kind'])) . '</strong><br>'
        . 'Destinataire réel : ' . e(implode(', ', $target['emails']) ?: '— aucun (' . $target['error'] . ')')
        . ($testMode ? ' &nbsp;|&nbsp; <span style="color:#f2b85a;">MODE TEST : l\'envoi partira vers ' . e($testRecipient) . '</span>' : '')
        . ' &nbsp;|&nbsp; <a style="color:#9fd6b0;" href="?week=' . e($selectedWeekKey) . '&preview=' . urlencode($previewKey) . '&as=' . ($previewAs === 'agency' ? 'student' : 'agency') . '">'
        . 'voir la version ' . ($previewAs === 'agency' ? 'personne' : 'agence') . ' ↗</a>'
        . '</div>'
        . famijobBuildScheduleMailBody($person, $weekStart, $weekEnd, $target)
        . '</body></html>';
    exit();
}

// --- Envoi ---
$flash = $_SESSION['famijob_envoi_horaires_flash'] ?? null;
unset($_SESSION['famijob_envoi_horaires_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    requireValidCSRF();

    $selectedKeys = $_POST['people'] ?? [];
    if (!is_array($selectedKeys)) {
        $selectedKeys = [];
    }

    // On compte les MAILS, pas les personnes : chaque personne en génère deux
    // (elle + son agence), et dire « 12 envoyés » alors que 12 mails sur 24
    // sont partis serait un rapport faux.
    $sent = 0;
    $failed = 0;
    $skipped = 0;
    $details = [];

    foreach ($selectedKeys as $key) {
        $key = (string) $key;
        if (!isset($people[$key])) {
            continue;
        }

        $person = $people[$key];
        $result = famijobSendScheduleMail($db, $person, $weekStart, $weekEnd, $person['targets'], $currentUserId);

        foreach ($result['sent'] as $line) {
            $sent++;
            $details[] = ['level' => 'ok', 'text' => $person['name'] . ' → ' . $line];
        }
        foreach ($result['failed'] as $line) {
            $failed++;
            $details[] = ['level' => 'ko', 'text' => $person['name'] . ' → ' . $line];
        }
        foreach ($result['skipped'] as $line) {
            $skipped++;
            $details[] = ['level' => 'warn', 'text' => $person['name'] . ' → non envoyé à ' . $line];
        }
    }

    $_SESSION['famijob_envoi_horaires_flash'] = [
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
        'test_mode' => $testMode,
        'details' => $details,
    ];

    header('Location: envoi_horaires.php?week=' . urlencode($selectedWeekKey));
    exit();
}

$lastSent = famijobLastScheduleMails($db, $weekStart);

$mailCount = 0;       // nombre de mails que produirait un envoi complet
$missingPerson = 0;   // personnes sans adresse perso : seule l'agence sera servie
$missingAgency = 0;   // agences sans adresse : seule la personne sera servie
$blockedCount = 0;    // ni l'un ni l'autre : rien ne peut partir
foreach ($people as $person) {
    $hasPerson = !empty($person['target_student']['emails']);
    $hasAgency = !empty($person['target_agency']['emails']);

    $mailCount += ($hasPerson ? 1 : 0) + ($hasAgency ? 1 : 0);
    if (!$hasPerson) {
        $missingPerson++;
    }
    if (!$hasAgency) {
        $missingAgency++;
    }
    if (!$hasPerson && !$hasAgency) {
        $blockedCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoi des horaires - FamiJob</title>
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
            --warn: #b9762a;
            --warn-soft: #fdf3e6;
            --danger: #a63a3a;
            --shadow: 0 14px 34px rgba(22, 49, 33, 0.1);
        }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px; background: var(--bg); font-family: 'Open Sans', sans-serif; color: var(--text); }
        .page { max-width: 1420px; margin: 0 auto; }
        .hero { background: linear-gradient(135deg, #264e35, #3f6b4d); color: #fff; border-radius: 24px; padding: 24px 28px; box-shadow: var(--shadow); margin-bottom: 16px; }
        .hero h1 { margin: 8px 0 6px; font-size: 2rem; }
        .hero p { margin: 0; opacity: 0.95; line-height: 1.6; max-width: 940px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #fff; text-decoration: none; font-weight: 700; background: rgba(255,255,255,0.14); padding: 12px 18px; border-radius: 999px; }

        .banner { border-radius: 18px; padding: 16px 20px; margin-bottom: 16px; font-size: 0.95rem; line-height: 1.6; border: 1px solid; }
        .banner strong { display: block; font-size: 1.02rem; margin-bottom: 4px; }
        .banner.test { background: var(--warn-soft); border-color: #f0d3a6; color: #7a4d12; }
        .banner.live { background: #fdeaea; border-color: #efc3c3; color: #7e2626; }
        .banner code { background: rgba(0,0,0,.06); padding: 2px 6px; border-radius: 6px; font-size: 0.9em; }

        .flash { border-radius: 18px; padding: 16px 20px; margin-bottom: 16px; background: var(--accent-soft); border: 1px solid #cfe3d5; color: #22452f; }
        .flash.has-error { background: #fdeaea; border-color: #efc3c3; color: #7e2626; }
        .flash ul { margin: 10px 0 0; padding-left: 20px; font-size: 0.9rem; line-height: 1.7; }
        .flash li.ko { color: #a63a3a; }

        .toolbar { display: flex; justify-content: space-between; align-items: end; gap: 16px; margin-bottom: 14px; padding: 18px 22px; background: #fff; border-radius: 22px; box-shadow: var(--shadow); flex-wrap: wrap; }
        .toolbar form { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; }
        label { display: block; margin-bottom: 6px; font-size: 0.82rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
        select { border: 1px solid #cfdad3; border-radius: 12px; padding: 10px 11px; font-size: 0.95rem; font-family: inherit; background: #fff; min-width: 300px; }
        .btn { border: none; border-radius: 12px; padding: 11px 16px; font-weight: 700; cursor: pointer; font-size: 0.92rem; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 7px; font-family: inherit; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:disabled { background: #a9bdaf; cursor: not-allowed; }
        .btn-soft { background: var(--accent-soft); color: var(--accent); border: 1px solid #d3e5d9; }
        .legend { color: var(--muted); font-size: 0.86rem; max-width: 460px; line-height: 1.5; }

        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .summary .chip { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 9px 14px; box-shadow: 0 6px 16px rgba(22,49,33,.06); }
        .summary .chip b { display: block; font-size: 1.15rem; color: var(--accent); line-height: 1.2; }
        .summary .chip span { font-size: 0.74rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }

        .table-wrap { background: #fff; border-radius: 22px; box-shadow: var(--shadow); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1080px; }
        th, td { padding: 12px 14px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; font-size: 0.9rem; }
        th { background: #f8fbf9; font-size: 0.75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); position: sticky; top: 0; z-index: 1; }
        tr:last-child td { border-bottom: none; }
        tr.is-blocked td { background: #fffaf3; }

        .person { font-weight: 700; font-size: 0.96rem; }
        .person-sub { color: var(--muted); font-size: 0.78rem; margin-top: 3px; }
        .tag { display: inline-block; border-radius: 999px; padding: 3px 9px; font-size: 0.72rem; font-weight: 700; }
        .tag.fami { background: var(--accent-soft); color: var(--accent); }
        .tag.interim { background: #e9f3f5; color: #2f6f7d; }
        .tag.ext { background: #f1eef7; color: #5c4b8a; }

        .shifts { margin: 0; padding: 0; list-style: none; font-size: 0.84rem; line-height: 1.6; }
        .shifts li { white-space: nowrap; }
        .shifts .d { display: inline-block; min-width: 66px; color: var(--muted); }
        .shifts .h { font-weight: 700; }
        .shifts .dept { color: var(--muted); }

        .mailto { font-size: 0.84rem; line-height: 1.5; word-break: break-word; }
        .mailto .ko { color: var(--danger); font-weight: 700; }
        .target { padding: 7px 0; border-bottom: 1px dashed #e3ebe6; }
        .target:last-child { border-bottom: none; padding-bottom: 0; }
        .target:first-child { padding-top: 0; }
        .target-who { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); display: block; margin-bottom: 2px; }
        .target-mail { font-weight: 600; }
        .redirected { display: block; margin-top: 4px; font-size: 0.74rem; color: var(--warn); font-weight: 700; }
        .sent-info { font-size: 0.8rem; color: var(--muted); line-height: 1.6; }
        .sent-info b { color: var(--accent); }
        .sent-info .never { color: #9aa8a0; }

        .empty-state { background: #fff; border-radius: 22px; padding: 28px; box-shadow: var(--shadow); color: var(--muted); }
        .actions-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 16px; padding: 16px 20px; background: #fff; border-radius: 18px; box-shadow: var(--shadow); }
        input[type="checkbox"] { width: 17px; height: 17px; accent-color: var(--accent); cursor: pointer; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; famijobRibbon($db, ['home' => true]); ?>
<div class="page">
    <div class="hero">
        <div>
            <a href="index.php" class="back-link">← Retour FamiJob</a>
            <h1>Envoi des horaires</h1>
            <p>Chaque personne matchée sur la semaine reçoit son horaire <strong>en direct</strong>, et son agence d'intérim — ou le contact interne pour les collaborateurs Famiflora — reçoit le même horaire de son côté. Deux mails par personne, avec un texte adapté à chacun.</p>
        </div>
    </div>

    <?php if ($testMode): ?>
        <div class="banner test">
            <strong>🧪 Mode test actif — aucun mail ne part vers les personnes, les agences ni le contact interne.</strong>
            <?php if ($testRecipient !== ''): ?>
                Tous les envois sont redirigés vers <code><?php echo e($testRecipient); ?></code>, avec le destinataire réel rappelé dans l'objet.
            <?php else: ?>
                <?php // Sans adresse de redirection, le mode test ne redirige pas : il BLOQUE
                      // tout. Le dire ici, sinon on croit envoyer et rien ne part. ?>
                ⚠️ <strong>Et aucune adresse de redirection n'est configurée : rien ne partira du tout.</strong>
                Renseigne <code>FAMIJOB_HORAIRE_MAIL_TEST_TO</code> dans les variables Railway.
            <?php endif; ?>
            La colonne « Destinataires réels » ci-dessous montre exactement ce qui partira une fois le mode coupé.
            Pour basculer en réel : variable d'environnement <code>FAMIJOB_HORAIRE_MAIL_TEST=0</code>.
        </div>
    <?php else: ?>
        <div class="banner live">
            <strong>🔴 Mode réel — les mails partent aux personnes, aux agences d'intérim et au contact interne.</strong>
            Vérifiez la semaine et la colonne « Destinataires réels » avant d'envoyer.
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="flash <?php echo ((int) $flash['failed'] > 0) ? 'has-error' : ''; ?>">
            <strong>
                <?php echo (int) $flash['sent']; ?> mail(s) envoyé(s)<?php
                    echo ((int) $flash['failed'] > 0) ? ', ' . (int) $flash['failed'] . ' échec(s)' : '';
                    echo ((int) $flash['skipped'] > 0) ? ', ' . (int) $flash['skipped'] . ' destinataire(s) sans adresse' : '';
                    echo !empty($flash['test_mode']) ? ' — en mode test' : '';
                ?>.
            </strong>
            <?php if (!empty($flash['details'])): ?>
                <ul>
                    <?php foreach ($flash['details'] as $detail): ?>
                        <li class="<?php echo ($detail['level'] ?? 'ok') === 'ok' ? '' : 'ko'; ?>"><?php echo e($detail['text']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <form method="get" action="">
            <div>
                <label for="week">Semaine</label>
                <select id="week" name="week" onchange="this.form.submit()">
                    <?php foreach ($weekOptions as $weekKey => $weekInfo): ?>
                        <option value="<?php echo e($weekKey); ?>" <?php echo $weekKey === $selectedWeekKey ? 'selected' : ''; ?>><?php echo e($weekInfo['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-soft" type="submit">Afficher</button>
            <a class="btn btn-soft" href="vue_horaire.php?week=<?php echo e($selectedWeekKey); ?>">📅 Voir le planning</a>
        </form>
        <div class="legend">
            L'horaire envoyé est celui affiché dans la vue horaire : mêmes créneaux, mêmes durées.
            Un renvoi est possible à tout moment — la date du dernier envoi réussi est indiquée, séparément pour la personne et pour l'agence.
        </div>
    </div>

    <div class="summary">
        <div class="chip"><b><?php echo count($people); ?></b><span>Personnes matchées</span></div>
        <div class="chip"><b><?php echo (int) $mailCount; ?></b><span>Mails à envoyer</span></div>
        <div class="chip"><b><?php echo (int) $missingPerson; ?></b><span>Sans adresse perso</span></div>
        <div class="chip"><b><?php echo (int) $missingAgency; ?></b><span>Sans adresse agence</span></div>
        <div class="chip"><b><?php echo (int) $blockedCount; ?></b><span>Injoignables</span></div>
    </div>

    <?php if (empty($people)): ?>
        <div class="empty-state">Aucune personne matchée sur cette semaine. Rien à envoyer.</div>
    <?php else: ?>
        <form method="post" action="" onsubmit="return confirmEnvoi(this);">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="week" value="<?php echo e($selectedWeekKey); ?>">

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:44px;"><input type="checkbox" id="checkAll" title="Tout sélectionner"></th>
                            <th style="width:230px;">Personne</th>
                            <th>Horaire de la semaine</th>
                            <th style="width:110px;">Total</th>
                            <th style="width:300px;">Destinataires réels</th>
                            <th style="width:190px;">Dernier envoi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($people as $key => $person): ?>
                            <?php
                            $targetStudent = $person['target_student'];
                            $targetAgency = $person['target_agency'];
                            $sendable = !empty($person['sendable']);
                            $previous = $lastSent[$key] ?? null;
                            $tagClass = $targetAgency['kind'] === 'famiflora' ? 'fami' : 'interim';
                            ?>
                            <tr class="<?php echo $sendable ? '' : 'is-blocked'; ?>">
                                <td>
                                    <input type="checkbox" name="people[]" value="<?php echo e($key); ?>"
                                           class="row-check" <?php echo $sendable ? '' : 'disabled'; ?>
                                           data-mails="<?php echo (empty($targetStudent['emails']) ? 0 : 1) + (empty($targetAgency['emails']) ? 0 : 1); ?>"
                                           <?php echo ($sendable && $previous === null) ? 'checked' : ''; ?>>
                                </td>
                                <td>
                                    <div class="person"><?php echo e($person['name']); ?></div>
                                    <div class="person-sub">
                                        <span class="tag <?php echo $tagClass; ?>"><?php echo e($targetAgency['label']); ?></span>
                                        <?php if ($person['student_id'] === null): ?>
                                            <span class="tag ext">Sans compte</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <ul class="shifts">
                                        <?php foreach ($person['shifts'] as $shift): ?>
                                            <li>
                                                <span class="d"><?php echo e(famijobWeekdayLabelFr($shift['date'], true)); ?></span>
                                                <span class="h"><?php echo e($shift['slot']['is_parsed'] ? $shift['slot']['label'] : $shift['slot']['raw']); ?></span>
                                                <span class="dept">· <?php echo e($shift['department']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td>
                                    <strong><?php echo e(famijobFormatDuration($person['total_minutes'])); ?></strong>
                                    <?php if (!empty($person['has_unparsed'])): ?>
                                        <div class="person-sub" style="color:#b9762a;">Un créneau illisible n'est pas compté</div>
                                    <?php endif; ?>
                                </td>
                                <td class="mailto">
                                    <div class="target">
                                        <span class="target-who">👤 La personne</span>
                                        <?php if (!empty($targetStudent['emails'])): ?>
                                            <span class="target-mail"><?php echo e(implode(', ', $targetStudent['emails'])); ?></span>
                                            <?php if ($testMode): ?>
                                                <span class="redirected">↳ redirigé vers <?php echo e($testRecipient); ?></span>
                                            <?php endif; ?>
                                            <div class="person-sub">
                                                <a href="?week=<?php echo e($selectedWeekKey); ?>&preview=<?php echo urlencode($key); ?>&as=student" target="_blank" rel="noopener">Aperçu ↗</a>
                                            </div>
                                        <?php else: ?>
                                            <span class="ko"><?php echo e($targetStudent['error']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="target">
                                        <span class="target-who">🏢 <?php echo e($targetAgency['kind'] === 'famiflora' ? 'Famiflora' : 'Agence ' . $targetAgency['label']); ?></span>
                                        <?php if (!empty($targetAgency['emails'])): ?>
                                            <span class="target-mail"><?php echo e(implode(', ', $targetAgency['emails'])); ?></span>
                                            <?php if ($targetAgency['contact'] !== ''): ?>
                                                <div class="person-sub">Contact : <?php echo e($targetAgency['contact']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($testMode): ?>
                                                <span class="redirected">↳ redirigé vers <?php echo e($testRecipient); ?></span>
                                            <?php endif; ?>
                                            <div class="person-sub">
                                                <a href="?week=<?php echo e($selectedWeekKey); ?>&preview=<?php echo urlencode($key); ?>&as=agency" target="_blank" rel="noopener">Aperçu ↗</a>
                                            </div>
                                        <?php else: ?>
                                            <span class="ko"><?php echo e($targetAgency['error']); ?></span>
                                            <div class="person-sub">À corriger dans « Agences intérim » côté plateforme.</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="sent-info">
                                    <?php if ($previous === null): ?>
                                        <span class="never">Jamais envoyé</span>
                                    <?php else: ?>
                                        <?php foreach (['student' => '👤 Personne', 'agency' => '🏢 Agence'] as $slotKey => $slotLabel): ?>
                                            <div>
                                                <?php echo $slotLabel; ?> :
                                                <?php if (empty($previous[$slotKey])): ?>
                                                    <span class="never">jamais</span>
                                                <?php else: ?>
                                                    <b><?php echo e(date('d/m à H:i', strtotime($previous[$slotKey]['last_sent']))); ?></b>
                                                    <?php echo !empty($previous[$slotKey]['test_mode']) ? ' <span class="never">(test)</span>' : ''; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="actions-bar">
                <button class="btn btn-primary" type="submit">
                    ✉️ Envoyer les horaires sélectionnés
                </button>
                <span class="legend" id="selectionInfo"></span>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
(function () {
    var checkAll = document.getElementById('checkAll');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.row-check:not([disabled])'));
    var info = document.getElementById('selectionInfo');
    var testMode = <?php echo $testMode ? 'true' : 'false'; ?>;
    var testTo = <?php echo json_encode($testRecipient); ?>;

    // Chaque personne cochee vaut potentiellement deux mails : le sien et celui
    // de son agence. On annonce le nombre de MAILS, pas de lignes cochees.
    function mailsFor(checkbox) {
        return parseInt(checkbox.getAttribute('data-mails'), 10) || 0;
    }

    function refresh() {
        var checked = rows.filter(function (c) { return c.checked; });
        var n = checked.length;
        var mails = checked.reduce(function (total, c) { return total + mailsFor(c); }, 0);
        if (info) {
            info.textContent = n === 0
                ? 'Aucune personne sélectionnée.'
                : n + ' personne(s) — ' + mails + ' mail(s)'
                    + (testMode ? ', tous vers ' + testTo + ' (test).' : ', aux personnes et à leur agence / au contact interne.');
        }
        if (checkAll) {
            checkAll.checked = n > 0 && n === rows.length;
            checkAll.indeterminate = n > 0 && n < rows.length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rows.forEach(function (c) { c.checked = checkAll.checked; });
            refresh();
        });
    }
    rows.forEach(function (c) { c.addEventListener('change', refresh); });
    refresh();

    window.confirmEnvoi = function (form) {
        var checked = Array.prototype.slice.call(form.querySelectorAll('.row-check:checked'));
        if (checked.length === 0) {
            alert('Sélectionnez au moins une personne.');
            return false;
        }
        var mails = checked.reduce(function (total, c) { return total + mailsFor(c); }, 0);
        var message = testMode
            ? 'MODE TEST : ' + mails + ' mail(s) pour ' + checked.length + ' personne(s), tous vers ' + testTo + '. Confirmer ?'
            : 'ENVOI RÉEL : ' + mails + ' mail(s) vont partir — aux personnes elles-mêmes ET à leur agence d\'intérim / au contact interne. Confirmer ?';
        return window.confirm(message);
    };
})();
</script>
</body>
</html>
