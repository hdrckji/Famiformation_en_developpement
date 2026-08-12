<!DOCTYPE html>
<html lang="<?php echo e($pageLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(fjhT('Horaires Intérim', 'Interim uurroosters')); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="famijob_.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7f6;
            --card: #ffffff;
            --line: #e6ece8;
            --text: #21362a;
            --muted: #63756a;
            --accent: #2d5a37;
            --accent-soft: #edf5ef;
            --warn: #a13e35;
            --ok: #1d6a39;
            --shadow: 0 14px 34px rgba(22, 49, 33, 0.1);
        }

        body {
            margin: 0;
            padding: 24px;
            background: var(--bg);
            font-family: 'Open Sans', sans-serif;
            color: var(--text);
        }

        .page {
            max-width: 1500px;
            margin: 0 auto;
        }

        .hero {
            background: linear-gradient(135deg, #264e35, #3f6b4d);
            color: #fff;
            border-radius: 20px;
            box-shadow: var(--shadow);
            padding: 22px 24px;
            margin-bottom: 20px;
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
        }

        .hero h1 {
            margin: 8px 0 6px;
            font-size: 1.8rem;
        }

        .hero p {
            margin: 0;
            opacity: 0.95;
            line-height: 1.5;
            max-width: 980px;
        }

        .hero-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .link-pill {
            text-decoration: none;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 14px;
            background: var(--card);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 16px;
            margin-bottom: 18px;
        }

        .toolbar form {
            display: flex;
            gap: 12px;
            align-items: end;
            flex-wrap: wrap;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            font-weight: 700;
        }

        input,
        select,
        textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #cfdad3;
            border-radius: 12px;
            padding: 10px 11px;
            font-size: 0.95rem;
            font-family: inherit;
            background: #fff;
        }

        textarea {
            min-height: 96px;
            resize: vertical;
        }

        .btn {
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-soft {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .btn-danger {
            background: #fae4e1;
            color: var(--warn);
        }

        .alert {
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .alert.success {
            background: #dff3e3;
            color: var(--ok);
        }

        .alert.error {
            background: #fae4e1;
            color: var(--warn);
        }

        .layout {
            display: block;
        }

        .card {
            background: var(--card);
            border-radius: 18px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-head {
            background: #f7fbf8;
            border-bottom: 1px solid var(--line);
            padding: 14px 16px;
            font-weight: 700;
        }

        .card-body {
            padding: 16px;
        }

        .helper {
            margin-top: 10px;
            color: var(--muted);
            font-size: 0.86rem;
            line-height: 1.5;
        }

        .day-card {
            border: 1px solid var(--line);
            border-radius: 14px;
            margin-bottom: 12px;
            overflow: hidden;
            background: #fff;
        }

        .day-head {
            background: #f7fbf8;
            border-bottom: 1px solid var(--line);
            padding: 10px 12px;
            font-weight: 700;
            color: #2b4f38;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .day-count {
            background: #e8f2ea;
            color: #2d5a37;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 820px;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 10px 10px;
            text-align: left;
            vertical-align: top;
            font-size: 0.9rem;
        }

        th {
            background: #fbfdfb;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            font-size: 0.76rem;
        }

        .slot-meta {
            color: var(--muted);
            font-size: 0.82rem;
            margin-top: 3px;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .badge-open {
            background: #fff2d8;
            color: #8b6400;
        }

        .badge-full {
            background: #dff3e3;
            color: #1d6a39;
        }

        .fill-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
        }

        .assigned-list {
            margin: 0;
            padding-left: 18px;
        }

        .assigned-list li {
            margin-bottom: 4px;
        }

        .suggestion-list {
            margin: 8px 0 0;
            padding-left: 18px;
            color: var(--muted);
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .suggestion-list li {
            margin-bottom: 4px;
        }

        .recap-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .recap-card {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px;
            background: #fbfdfb;
        }

        .recap-title {
            font-weight: 700;
            color: #2b4f38;
            margin-bottom: 8px;
        }

        .recap-chevron { float:right; transition:transform .2s; }
        .recap-toggle.open .recap-chevron { transform:rotate(180deg); }
        .recap-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.88rem;
            margin-bottom: 4px;
        }

        .unassign-form {
            display: inline-block;
            margin-left: 8px;
        }

        .btn-mini {
            border: none;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            background: #ffe9d8;
            color: #8b4f00;
        }

        .empty {
            padding: 16px;
            color: var(--muted);
        }

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

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .tab {
            text-decoration: none;
            border-radius: 12px 12px 0 0;
            padding: 12px 20px;
            font-weight: 700;
            font-size: 0.92rem;
            color: var(--muted);
            background: #eef3f0;
            border: 1px solid var(--line);
            border-bottom: none;
        }

        .tab.is-active {
            background: var(--card);
            color: var(--accent);
            box-shadow: 0 -3px 0 var(--accent) inset;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(20, 40, 28, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-box {
            background: var(--card);
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(15, 40, 25, 0.35);
            max-width: 460px;
            width: 100%;
            padding: 24px;
        }

        .modal-title {
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .modal-text {
            color: var(--text);
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        @media (max-width: 1200px) {
            .layout {
                display: block;
            }
        }
    </style>
</head>
<body>
<?php // ⚠️ Cette vue vit dans includes/ : « __DIR__ . "/includes/topbar.php" »
      // pointerait sur includes/includes/. Le chemin perd donc son segment. ?>
<?php require_once __DIR__ . "/topbar.php"; famijobRibbon($db); ?>
    <div class="page">
        <section class="hero">
            <div class="hero-top">
                <div>
                    <div style="text-transform:uppercase;letter-spacing:.08em;font-size:.78rem;opacity:.86;"><?php echo $isAdmin ? e(fjhT('Administration', 'Administratie')) : e(fjhT('Agence intérim', 'Interimkantoor')); ?></div>
                    <h1><?php echo e(fjhT('Horaires à pourvoir', 'In te vullen uurroosters')); ?></h1>
                    <?php // Retour au classeur, en gardant la semaine affichée. ?>
                    <a href="<?php echo e($lienVue('excel')); ?>"
                       style="display:inline-block; margin-top:6px; padding:6px 14px; border-radius:20px; background:#2d5a37; color:#fff; text-decoration:none; font-weight:700; font-size:.82rem;">
                        ▦ <?php echo e(fjhT('Vue classeur', 'Werkboekweergave')); ?>
                    </a>
                </div>
                <div class="hero-actions">
                    <?php if ($isAdmin): ?>
                        <a href="interim_horaires_demandes.php" class="link-pill"><?php echo e(fjhT('Demandes horaires', 'Uurroosteraanvragen')); ?></a>
                        <a href="validation_demandes_horaires.php" class="link-pill"><?php echo e(fjhT('Validation demandes', 'Aanvragen valideren')); ?></a>
                    <?php endif; ?>
                    <a href="admin_disponibilites_etudiants.php" class="link-pill"><?php echo e(fjhT('Disponibilités étudiants', 'Beschikbaarheden studenten')); ?></a>
                    <a href="<?php echo $isAdmin ? 'index.php' : 'logout.php'; ?>" class="link-pill"><?php echo $isAdmin ? e(fjhT('Retour accueil', 'Terug naar start')) : e(fjhT('Se déconnecter', 'Uitloggen')); ?></a>
                    <?php echo famiRenderLanguageSwitcher(); ?>
                </div>
            </div>
            <p>
                <?php if ($isAdmin): ?>
                    <?php echo e(fjhT('Crée les besoins horaires en quelques lignes. Les agences voient tous les créneaux, mais ne peuvent compléter que les places encore libres.', 'Maak uurbehoeften in enkele regels. Kantoren zien alle tijdsblokken, maar kunnen alleen vrije plaatsen invullen.')); ?>
                <?php else: ?>
                    <?php echo e(fjhT('Tous les horaires à pourvoir sont visibles. Une place déjà complétée par une autre agence est verrouillée, avec anonymisation des étudiants externes à votre agence.', 'Alle in te vullen uurroosters zijn zichtbaar. Een plaats die al werd ingevuld door een ander kantoor is vergrendeld; studenten van andere kantoren worden geanonimiseerd.')); ?>
                <?php endif; ?>
            </p>
        </section>

        <?php echo $message; ?>

        <?php if ($pendingConfirm !== null): ?>
            <div class="modal-overlay" id="confirmModal">
                <div class="modal-box">
                    <div class="modal-title"><?php echo e(fjhT('Confirmation', 'Bevestiging')); ?></div>
                    <div class="modal-text"><?php echo e($pendingConfirm['message']); ?></div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-soft" onclick="document.getElementById('confirmModal').style.display='none';"><?php echo e(fjhT('Non', 'Nee')); ?></button>
                        <form method="POST" style="display:inline;">
                            <?php echo csrfField(); ?>
                            <?php $confirmMode = (string) ($pendingConfirm['matching_mode'] ?? 'name'); ?>
                            <input type="hidden" name="assign_student" value="1">
                            <input type="hidden" name="request_id" value="<?php echo (int) $pendingConfirm['request_id']; ?>">
                            <input type="hidden" name="matching_mode" value="<?php echo e($confirmMode); ?>">
                            <?php if ($confirmMode === 'list'): ?>
                                <input type="hidden" name="student_id" value="<?php echo (int) ($pendingConfirm['student_id'] ?? 0); ?>">
                            <?php else: ?>
                                <input type="hidden" name="student_name" value="<?php echo e($pendingConfirm['student_name']); ?>">
                            <?php endif; ?>
                            <input type="hidden" name="confirm_assign" value="1">
                            <button type="submit" class="btn btn-primary"><?php echo e(fjhT('Oui, affecter', 'Ja, toewijzen')); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <section class="toolbar">
            <form method="GET">
                <?php // Sans ce champ, changer de semaine renverrait sur la vue
                      // classeur — on perdrait la vue choisie a chaque filtre. ?>
                <input type="hidden" name="vue" value="liste">
                <div>
                    <label for="week"><?php echo e(fjhT('Semaine', 'Week')); ?></label>
                    <select id="week" name="week">
                        <?php foreach ($weekOptions as $weekKey => $weekOption): ?>
                            <option value="<?php echo e($weekKey); ?>" <?php echo $selectedWeekKey === $weekKey ? 'selected' : ''; ?>><?php echo e($weekOption['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="day"><?php echo e(fjhT('Jour', 'Dag')); ?></label>
                    <select id="day" name="day">
                        <option value="all" <?php echo $selectedDayFilter === 'all' ? 'selected' : ''; ?>><?php echo e(fjhT('Tous les jours', 'Alle dagen')); ?></option>
                        <?php foreach ($weekDays as $weekDay): ?>
                            <option value="<?php echo e($weekDay['key']); ?>" <?php echo $selectedDayFilter === $weekDay['key'] ? 'selected' : ''; ?>>
                                <?php echo e($weekDay['label']); ?> (<?php echo e($weekDay['date']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="department"><?php echo e(fjhT('Département', 'Afdeling')); ?></label>
                    <select id="department" name="department">
                        <option value="all" <?php echo $selectedDepartmentFilter === 'all' ? 'selected' : ''; ?>><?php echo e(fjhT('Tous les départements', 'Alle afdelingen')); ?></option>
                        <?php foreach ($departmentFilterOptions as $departmentFilterName): ?>
                            <option value="<?php echo e($departmentFilterName); ?>" <?php echo $selectedDepartmentFilter === $departmentFilterName ? 'selected' : ''; ?>>
                                <?php echo e($departmentFilterName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="vue"><?php echo e(fjhT('Vue', 'Weergave')); ?></label>
                    <select id="vue" name="vue">
                        <option value="all" <?php echo $selectedVueFilter === 'all' ? 'selected' : ''; ?>><?php echo e(fjhT('Tous les horaires', 'Alle uurroosters')); ?></option>
                        <option value="a_pourvoir" <?php echo $selectedVueFilter === 'a_pourvoir' ? 'selected' : ''; ?>><?php echo e(fjhT('Encore à pourvoir', 'Nog in te vullen')); ?></option>
                        <option value="attribue" <?php echo $selectedVueFilter === 'attribue' ? 'selected' : ''; ?>><?php echo e(fjhT('Déjà attribués', 'Reeds toegewezen')); ?></option>
                    </select>
                </div>
                <input type="hidden" name="matching_mode" value="<?php echo e($matchingMode); ?>">
                <button type="submit" class="btn btn-soft"><?php echo e(fjhT('Afficher', 'Tonen')); ?></button>
            </form>
            <?php if ($isAdmin): ?>
                <form method="POST" style="display:flex;align-items:end;gap:10px;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="auto_match_week" value="1">
                    <input type="hidden" name="week" value="<?php echo e($selectedWeekKey); ?>">
                    <button type="submit" class="btn btn-primary"><?php echo e(fjhT('Auto-matching semaine', 'Automatische matching week')); ?></button>
                </form>
            <?php endif; ?>
            <?php // L'export Excel a rejoint la VUE HORAIRE : c'est l'ecran de
                  // consultation, celui qu'on imprime ou qu'on envoie. Le
                  // matching sert a affecter, pas a diffuser. ?>
            <div style="text-align:right;color:var(--muted);line-height:1.5;">
                <strong><?php echo e(fjhT('Période', 'Periode')); ?></strong><br>
                <?php echo $selectedWeek['start']->format('d/m/Y'); ?> - <?php echo $selectedWeek['end']->format('d/m/Y'); ?>
            </div>
        </section>

        <section class="layout">
            <div>
                <?php if (!empty($remainingByDayDept)): ?>
                    <section class="card" style="margin-bottom:16px;">
                        <div class="card-head recap-toggle" onclick="toggleRecap(this)" style="cursor:pointer;user-select:none;"><?php echo e(fjhT('Récap des horaires à pourvoir (reste à couvrir)', 'Overzicht van in te vullen uurroosters (nog te dekken)')); ?> <span class="recap-chevron">&#9660;</span></div>
                        <div class="card-body recap-body" style="display:none;">
                            <div class="recap-grid">
                                <?php foreach ($weekDays as $weekDay): ?>
                                    <?php $dayRecap = $remainingByDayDept[$weekDay['key']] ?? []; ?>
                                    <?php if (empty($dayRecap)): ?>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <div class="recap-card">
                                        <div class="recap-title"><?php echo e($weekDay['label']); ?> <?php echo e($weekDay['date']); ?></div>
                                        <?php foreach ($dayRecap as $deptName => $remainingTotal): ?>
                                            <div class="recap-row">
                                                <span><?php echo e($deptName); ?></span>
                                                <strong><?php echo (int) $remainingTotal; ?> <?php echo e(fjhT('poste(s)', 'plaats(en)')); ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (empty($visibleWeekDays)): ?>
                    <section class="day-card">
                        <div class="empty"><?php echo e(fjhT('Aucun créneau à afficher pour les filtres sélectionnés.', 'Geen tijdsblok om te tonen voor de geselecteerde filters.')); ?></div>
                    </section>
                <?php endif; ?>

                <?php foreach ($visibleWeekDays as $weekDay): ?>
                    <?php
                    $dayRequests = $requestsByDate[$weekDay['key']] ?? [];
                    ?>
                    <section class="day-card">
                        <div class="day-head">
                            <span><?php echo e($weekDay['label']); ?> <?php echo e($weekDay['date']); ?></span>
                            <span class="day-count"><?php echo count($dayRequests); ?> <?php echo e(fjhT('demande(s)', 'aanvraag/aanvragen')); ?></span>
                        </div>

                        <?php if (!empty($dayRequests)): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th><?php echo e(fjhT('Département / Horaire', 'Afdeling / Uurrooster')); ?></th>
                                            <th><?php echo e(fjhT('État', 'Status')); ?></th>
                                            <th><?php echo e(fjhT('Affectations', 'Toewijzingen')); ?></th>
                                            <th><?php echo e(fjhT('Action', 'Actie')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dayRequests as $request): ?>
                                            <?php
                                            $requestId = (int) $request['id'];
                                            $seatsRequired = (int) $request['seats_required'];
                                            $assignments = $assignmentsByRequest[$requestId] ?? [];
                                            $filledSeats = count($assignments);
                                            $remainingSeats = max(0, $seatsRequired - $filledSeats);
                                            $isFull = ($remainingSeats === 0);
                                            $rankedCandidates = !$isFull
                                                ? interimGetRankedCandidatesForRequest($db, $request, $isAdmin, $agencyName)
                                                : [];
                                            $eligibleCandidates = array_values(array_filter($rankedCandidates, static function ($candidate) {
                                                return !empty($candidate['eligible']);
                                            }));
                                            $manualEligibleCandidates = array_values(array_filter($rankedCandidates, static function ($candidate) {
                                                return !empty($candidate['manual_eligible']);
                                            }));
                                            $topSuggestions = array_slice($eligibleCandidates, 0, 3);
                                            $hasManualEligibleCandidates = !empty($manualEligibleCandidates);
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo e($request['department_name']); ?></strong>
                                                    <div class="slot-meta"><?php echo e($request['time_slot']); ?></div>
                                                    <?php if (!empty($request['comment'])): ?>
                                                        <div class="slot-meta"><?php echo e($request['comment']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="badges">
                                                        <span class="badge <?php echo $isFull ? 'badge-full' : 'badge-open'; ?>">
                                                            <?php echo $filledSeats; ?> / <?php echo $seatsRequired; ?> pourvu(s)
                                                        </span>
                                                        <?php if (!$isFull): ?>
                                                            <span class="badge badge-open"><?php echo $remainingSeats; ?> place(s) libre(s)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (empty($assignments)): ?>
                                                        <span class="slot-meta">Aucun étudiant assigné</span>
                                                    <?php else: ?>
                                                        <ul class="assigned-list">
                                                            <?php foreach ($assignments as $assignment): ?>
                                                                <?php
                                                                $isExternalAssign = empty($assignment['student_id']);
                                                                if ($isExternalAssign) {
                                                                    $studentName = trim((string) ($assignment['external_name'] ?? ''));
                                                                    $studentAgency = trim((string) ($assignment['agency_name'] ?? ''));
                                                                } else {
                                                                    $studentName = trim((string) ($assignment['prenom'] ?? '')) . ' ' . trim((string) ($assignment['nom'] ?? ''));
                                                                    $studentAgency = trim((string) ($assignment['interim'] ?? ''));
                                                                }
                                                                $canSeeIdentity = $isAdmin || ($studentAgency !== '' && $studentAgency === $agencyName);
                                                                $canUnassign = $canSeeIdentity;
                                                                ?>
                                                                <li>
                                                                    <?php if ($canSeeIdentity): ?>
                                                                        <?php echo e($studentName); ?>
                                                                        <?php if ($isExternalAssign): ?>
                                                                            <span class="badge badge-open" style="margin-left:4px;"><?php echo e(fjhT('externe', 'extern')); ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if ($isAdmin): ?>
                                                                            <span class="slot-meta">(<?php echo e($studentAgency !== '' ? $studentAgency : ($isExternalAssign ? 'Non inscrit' : 'Sans agence')); ?>)</span>
                                                                        <?php endif; ?>
                                                                        <?php if ($canUnassign): ?>
                                                                            <form method="POST" class="unassign-form" onsubmit="return confirm('Retirer cet étudiant de ce créneau ?');">
                                                                                <?php echo csrfField(); ?>
                                                                                <input type="hidden" name="unassign_student" value="1">
                                                                                <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                                                                                <input type="hidden" name="assignment_id" value="<?php echo (int) ($assignment['assignment_id'] ?? 0); ?>">
                                                                                <button type="submit" class="btn-mini">Désaffecter</button>
                                                                            </form>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        Pourvu (autre agence)
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$isFull): ?>
                                                        <?php if (!empty($topSuggestions)): ?>
                                                            <ul class="suggestion-list">
                                                                <?php foreach ($topSuggestions as $suggestion): ?>
                                                                    <?php $availabilityLabel = $statusLabels[$suggestion['availability_status']] ?? $suggestion['availability_status']; ?>
                                                                    <li>
                                                                        <?php echo e($suggestion['name']); ?>
                                                                        <?php if ($isAdmin): ?>(P<?php echo (int) $suggestion['priority_rank']; ?> - <?php echo e($availabilityLabel); ?>)<?php else: ?>(P<?php echo (int) $suggestion['priority_rank']; ?>)<?php endif; ?>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>

                                                        <form method="POST" class="fill-form">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="assign_student" value="1">
                                                            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">

                                                            <label style="display:block;font-size:.74rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#5c6f67;margin-bottom:5px;"><?php echo e(fjhT('Rechercher un étudiant ou taper un nom', 'Zoek een student of typ een naam')); ?></label>
                                                            <input type="text" name="student_name" list="fjm-cand-<?php echo $requestId; ?>" placeholder="<?php echo e(fjhT('Tapez pour rechercher, ou un nom libre…', 'Typ om te zoeken, of een vrije naam…')); ?>" autocomplete="off" style="width:100%;padding:13px 14px;font-size:1.05rem;border:1px solid #cfdad3;border-radius:10px;">
                                                            <datalist id="fjm-cand-<?php echo $requestId; ?>">
                                                                <?php foreach ($rankedCandidates as $candidate): ?>
                                                                <?php
                                                                $candidateStatusLabel = $statusLabels[$candidate['availability_status']] ?? $candidate['availability_status'];
                                                                $optLabel = 'P' . (int) $candidate['priority_rank'] . ' · ' . $candidateStatusLabel;
                                                                if (!empty($candidate['manual_eligible']) && empty($candidate['eligible'])) { $optLabel .= ' · manuel'; }
                                                                ?>
                                                                <option value="<?php echo e((string) $candidate['name']); ?>"><?php echo e($optLabel); ?></option>
                                                                <?php endforeach; ?>
                                                            </datalist>
                                                            <div class="slot-meta" style="margin-top:6px;"><?php echo e(fjhT('La liste se filtre pendant la saisie. Un nom absent de la liste sera ajouté en externe.', 'De lijst filtert tijdens het typen. Een naam die niet in de lijst staat, wordt als extern toegevoegd.')); ?></div>

                                                            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px;">Affecter</button>
                                                        </form>

                                                        <?php if ($isAdmin): ?>
                                                            <form method="POST" style="margin-top:8px;">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="auto_match_request" value="1">
                                                                <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                                                                <button type="submit" class="btn btn-soft" style="width:100%;">Auto-matching créneau</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="slot-meta">Créneau verrouillé (complet)</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
<script>
function toggleRecap(el) {
    el.classList.toggle('open');
    var body = el.nextElementSibling;
    body.style.display = body.style.display === 'none' ? '' : 'none';
}
</script>
</body>
</html>
