<?php
// ============================================================
// horaires.php — SOCLE COMMUN DES HORAIRES ATTRIBUÉS (FamiJob).
//
//   Deux besoins partagent exactement la même matière première : la vue
//   hebdomadaire (vue_horaire.php) et l'envoi des horaires par mail
//   (envoi_horaires.php). Les deux doivent lire un créneau écrit à la main —
//   « 9h30-19h30 », « 08h00/18h00 », « 10h-19h /1 », « 9h19h durand » — et en
//   tirer une heure de début, une heure de fin et une durée.
//
//   D'où ce fichier : un seul analyseur, une seule mise en forme. Si la vue et
//   le mail divergeaient, un intérimaire recevrait un horaire différent de
//   celui affiché à l'écran — c'est le genre d'écart qu'on ne détecte qu'une
//   fois la personne devant la porte.
// ============================================================

if (!function_exists('famijobParseTimeSlot')) {
    /**
     * Analyse un libellé de créneau saisi librement.
     *
     * Le champ time_slot est du texte libre : la base contient aussi bien
     * « 12h-17h » que « 9h30/19h30 fatima », « 10h-19h /1 » ou « 11h19h30 »
     * (séparateur oublié). On extrait donc les repères horaires plutôt que de
     * faire confiance à un format.
     *
     * Subtilité de l'expression régulière : les minutes ne sont acceptées que
     * si elles ne sont pas elles-mêmes suivies d'une heure. Sans cette
     * précaution, « 11h19h30 » serait lu « 11h19 » puis « h30 », soit un
     * créneau de 11h19 à… rien. Avec, il se lit « 11h » puis « 19h30 ».
     *
     * @return array{start:?int,end:?int,duration:?int,raw:string,label:string,is_parsed:bool,note:string}
     *         start/end/duration en minutes depuis minuit, null si illisible.
     */
    function famijobParseTimeSlot($slot)
    {
        $raw = trim(preg_replace('/\s+/', ' ', (string) $slot));
        $result = [
            'start' => null,
            'end' => null,
            'duration' => null,
            'raw' => $raw,
            'label' => $raw,
            'is_parsed' => false,
            'note' => '',
        ];

        if ($raw === '') {
            return $result;
        }

        $motif = '/(\d{1,2})\s*[h:]\s*(\d{2})(?!\s*\d*\s*[h:])|(\d{1,2})\s*[h:]/i';
        if (!preg_match_all($motif, $raw, $matches, PREG_SET_ORDER)) {
            return $result;
        }

        $times = [];
        foreach ($matches as $m) {
            if (isset($m[3]) && $m[3] !== '') {
                $hours = (int) $m[3];
                $minutes = 0;
            } else {
                $hours = (int) $m[1];
                $minutes = (int) $m[2];
            }

            if ($hours > 24 || $minutes > 59) {
                continue;
            }

            $times[] = ($hours * 60) + $minutes;
            if (count($times) >= 2) {
                break;
            }
        }

        if (empty($times)) {
            return $result;
        }

        $result['start'] = $times[0];
        if (isset($times[1]) && $times[1] > $times[0]) {
            $result['end'] = $times[1];
            $result['duration'] = $times[1] - $times[0];
            $result['is_parsed'] = true;
            $result['label'] = famijobFormatMinutes($times[0]) . ' – ' . famijobFormatMinutes($times[1]);
        }

        // Ce qui reste après les heures (« /1 », un prénom…) : on ne l'invente
        // pas, mais on ne le perd pas non plus, il porte souvent une consigne.
        $note = trim(preg_replace($motif, ' ', $raw));
        $note = trim($note, " \t-/–—,;");
        $note = trim(preg_replace('/\s+/', ' ', $note));
        $result['note'] = $note;

        return $result;
    }
}

if (!function_exists('famijobFormatMinutes')) {
    /** 570 -> « 9h30 », 540 -> « 9h ». */
    function famijobFormatMinutes($minutes)
    {
        $minutes = max(0, (int) $minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? $h . 'h' : $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('famijobFormatDuration')) {
    /** 600 -> « 10h », 570 -> « 9h30 », null -> « — ». */
    function famijobFormatDuration($minutes)
    {
        if ($minutes === null) {
            return '—';
        }

        $minutes = max(0, (int) $minutes);
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h === 0) {
            return $m === 0 ? '0h' : $m . 'min';
        }

        return $m === 0 ? $h . 'h' : $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('famijobWeekdayLabelFr')) {
    /** « lundi », sans dépendre de l'extension intl (absente du conteneur). */
    function famijobWeekdayLabelFr($dateValue, $capitalize = false)
    {
        $jours = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

        try {
            $date = $dateValue instanceof DateTimeInterface
                ? $dateValue
                : new DateTimeImmutable((string) $dateValue);
        } catch (Exception $e) {
            return (string) $dateValue;
        }

        $label = $jours[(int) $date->format('N')] ?? $date->format('l');

        return $capitalize ? mb_strtoupper(mb_substr($label, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($label, 1, null, 'UTF-8') : $label;
    }
}

if (!function_exists('famijobScheduleMailIsTestMode')) {
    /**
     * MODE TEST DES ENVOIS D'HORAIRE.
     *
     * Tant qu'il est actif, AUCUN mail ne part vers une agence d'intérim ni
     * vers Honorine : tout est redirigé vers l'adresse de test. Le destinataire
     * réel reste calculé et affiché à l'écran, pour qu'on vérifie l'aiguillage
     * avant de l'ouvrir en vrai.
     *
     * POUR BASCULER EN RÉEL : poser FAMIJOB_HORAIRE_MAIL_TEST=0 dans les
     * variables d'environnement (Railway), ou passer le défaut ci-dessous à
     * false. Rien d'autre à toucher.
     */
    function famijobScheduleMailIsTestMode()
    {
        return famiEnvFlag('FAMIJOB_HORAIRE_MAIL_TEST', true);
    }
}

if (!function_exists('famijobScheduleMailTestRecipient')) {
    function famijobScheduleMailTestRecipient()
    {
        return trim((string) famiGetEnv('FAMIJOB_HORAIRE_MAIL_TEST_TO', 'enylson.laine@famiflora.be'));
    }
}

if (!function_exists('famijobFamifloraFallbackEmail')) {
    /** Destinataire des collaborateurs Famiflora (pas d'agence d'intérim). */
    function famijobFamifloraFallbackEmail()
    {
        return trim((string) famiGetEnv('FAMIJOB_HORAIRE_MAIL_FAMIFLORA', 'honorine.dhulst@famiflora.be'));
    }
}

if (!function_exists('famijobIsFamifloraAgency')) {
    function famijobIsFamifloraAgency($agencyName)
    {
        $normalized = famijobNormalizeDepartmentName($agencyName); // minuscules, sans accents

        return $normalized === '' || $normalized === 'famiflora';
    }
}

if (!function_exists('famijobResolveScheduleRecipient')) {
    /**
     * À qui part l'horaire de cette personne ?
     *
     *   • rattachée à une agence d'intérim -> les adresses de l'agence ;
     *   • Famiflora (ou champ « interim » vide) -> Honorine.
     *
     * La table interim_agences contient déjà une ligne « Famiflora » pointant
     * sur Honorine : on l'utilise en priorité, et l'adresse en dur ne sert que
     * de filet si la ligne venait à disparaître.
     *
     * @return array{kind:string,agency:string,contact:string,emails:string[],error:string}
     */
    function famijobResolveScheduleRecipient(PDO $db, $agencyName)
    {
        $agencyName = trim((string) $agencyName);
        $isFamiflora = famijobIsFamifloraAgency($agencyName);

        $out = [
            'kind' => $isFamiflora ? 'famiflora' : 'interim',
            'agency' => $isFamiflora ? 'Famiflora' : $agencyName,
            'contact' => '',
            'emails' => [],
            'error' => '',
        ];

        $lookup = $agencyName !== '' ? $agencyName : 'Famiflora';

        try {
            $stmt = $db->prepare('SELECT nom_agence, nom_contact, email_1, email_2 FROM interim_agences WHERE nom_agence = ? LIMIT 1');
            $stmt->execute([$lookup]);
            $agency = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $agency = false;
        }

        if ($agency) {
            $out['agency'] = trim((string) $agency['nom_agence']);
            $out['contact'] = trim((string) $agency['nom_contact']);
            foreach (['email_1', 'email_2'] as $key) {
                $email = trim((string) ($agency[$key] ?? ''));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array($email, $out['emails'], true)) {
                    $out['emails'][] = $email;
                }
            }
        }

        if (empty($out['emails']) && $isFamiflora) {
            $fallback = famijobFamifloraFallbackEmail();
            if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                $out['emails'][] = $fallback;
                if ($out['contact'] === '') {
                    $out['contact'] = 'Honorine';
                }
            }
        }

        if (empty($out['emails'])) {
            $out['error'] = $agency
                ? 'Aucune adresse valide pour l\'agence « ' . $out['agency'] . ' ».'
                : 'Agence « ' . $agencyName . ' » introuvable dans la liste des agences intérim.';
        }

        return $out;
    }
}

if (!function_exists('famijobResolveScheduleTargets')) {
    /**
     * Les destinataires d'un horaire : DEUX envois distincts.
     *
     *   1. la personne elle-même, sur son adresse de compte ;
     *   2. son agence d'intérim, ou Honorine si elle est chez Famiflora.
     *
     * Deux mails plutôt qu'un seul en copie : le texte n'est pas le même. La
     * personne lit « voici ton horaire », l'agence lit « voici l'horaire de
     * X, rattaché(e) à votre agence ». Un mail unique obligerait à un phrasé
     * bâtard qui parle mal aux deux.
     *
     * Un intérimaire encodé à la main dans le matching n'a pas de compte, donc
     * pas d'adresse : seule l'agence est servie, et on le dit à l'écran plutôt
     * que de laisser croire que la personne a été prévenue.
     *
     * @param array|null $resolvedAgency Résolution d'agence déjà faite par
     *        l'appelant (les agences se répètent d'une personne à l'autre :
     *        la page d'envoi les met en cache plutôt que de relire la table
     *        interim_agences à chaque ligne).
     *
     * @return array<int, array{kind:string,label:string,contact:string,emails:string[],error:string,agency:string}>
     */
    function famijobResolveScheduleTargets(PDO $db, array $person, array $resolvedAgency = null)
    {
        $targets = [];

        $studentEmail = trim((string) ($person['student_email'] ?? ''));
        $isValidStudentEmail = $studentEmail !== '' && filter_var($studentEmail, FILTER_VALIDATE_EMAIL);

        $targets[] = [
            'kind' => 'student',
            'label' => (string) $person['name'],
            'contact' => trim((string) ($person['first_name'] ?? '')) !== ''
                ? trim((string) $person['first_name'])
                : (string) $person['name'],
            'agency' => (string) ($person['agency'] ?? ''),
            'emails' => $isValidStudentEmail ? [$studentEmail] : [],
            'error' => $isValidStudentEmail
                ? ''
                : ($person['student_id'] === null
                    ? 'Pas de compte sur la plateforme : aucune adresse personnelle.'
                    : 'Aucune adresse e-mail sur la fiche de la personne.'),
        ];

        $agency = $resolvedAgency !== null
            ? $resolvedAgency
            : famijobResolveScheduleRecipient($db, $person['agency'] ?? '');

        $targets[] = [
            'kind' => $agency['kind'],
            'label' => $agency['agency'] !== '' ? $agency['agency'] : 'Famiflora',
            'contact' => $agency['contact'],
            'agency' => $agency['agency'],
            'emails' => $agency['emails'],
            'error' => $agency['error'],
        ];

        return $targets;
    }
}

if (!function_exists('famijobScheduleTargetsAreSendable')) {
    /** Au moins un des deux destinataires est joignable ? */
    function famijobScheduleTargetsAreSendable(array $targets)
    {
        foreach ($targets as $target) {
            if (!empty($target['emails'])) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('famijobEnsureScheduleMailLog')) {
    /** Journal des envois : évite les doublons et garde une trace vérifiable. */
    function famijobEnsureScheduleMailLog(PDO $db)
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS interim_schedule_mail_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                week_start DATE NOT NULL,
                person_key VARCHAR(190) NOT NULL,
                person_name VARCHAR(190) NOT NULL,
                student_id INT NULL,
                agency_name VARCHAR(190) NULL,
                target_kind VARCHAR(20) NOT NULL DEFAULT '',
                recipients VARCHAR(500) NOT NULL,
                test_mode TINYINT(1) NOT NULL DEFAULT 0,
                success TINYINT(1) NOT NULL DEFAULT 0,
                error_message VARCHAR(500) NULL,
                sent_by_user_id INT NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_schedule_mail_week (week_start),
                INDEX idx_schedule_mail_person (person_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Le journal a d'abord existé sans distinction de destinataire : on
        // ajoute la colonne aux bases déjà créées, sans casser l'historique.
        try {
            $columns = $db->query('SHOW COLUMNS FROM interim_schedule_mail_log')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('target_kind', $columns, true)) {
                $db->exec("ALTER TABLE interim_schedule_mail_log ADD COLUMN target_kind VARCHAR(20) NOT NULL DEFAULT '' AFTER agency_name");
            }
        } catch (Exception $e) {
            // Table lisible mais non modifiable : le reste fonctionne sans.
        }
    }
}

if (!function_exists('famijobLoadWeekAssignments')) {
    /**
     * Toutes les affectations d'une semaine, regroupées par personne.
     *
     * Une « personne » est soit un compte étudiant (clé « u:<id> »), soit un
     * intérimaire encodé à la main dans le matching sans compte sur la
     * plateforme (clé « x:<nom>|<agence> »). Les deux reçoivent un horaire.
     *
     * @return array<string, array> indexé par clé de personne
     */
    function famijobLoadWeekAssignments(PDO $db, DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd)
    {
        $stmt = $db->prepare(
            "SELECT a.id AS assignment_id, a.student_id, a.external_name, a.agency_name,
                    r.shift_date, r.department_name, r.time_slot, r.comment, r.validation_status,
                    u.nom AS student_nom, u.prenom AS student_prenom, u.email AS student_email,
                    u.interim AS student_interim
             FROM interim_shift_assignments a
             INNER JOIN interim_shift_requests r ON r.id = a.request_id
             LEFT JOIN utilisateurs u ON u.id = a.student_id
             WHERE r.shift_date BETWEEN ? AND ?
             ORDER BY r.shift_date ASC, r.time_slot ASC, a.seat_number ASC"
        );
        $stmt->execute([$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);

        $people = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $studentId = (int) ($row['student_id'] ?? 0);

            if ($studentId > 0) {
                $firstName = trim((string) ($row['student_prenom'] ?? ''));
                $name = trim($firstName . ' ' . trim((string) ($row['student_nom'] ?? '')));
                if ($name === '') {
                    $name = 'Étudiant #' . $studentId;
                }
                $agency = trim((string) ($row['student_interim'] ?? ''));
                if ($agency === '') {
                    $agency = trim((string) ($row['agency_name'] ?? ''));
                }
                $key = 'u:' . $studentId;
            } else {
                $name = trim((string) ($row['external_name'] ?? ''));
                if ($name === '') {
                    continue; // ligne d'affectation vide : rien à envoyer
                }
                $firstName = '';
                $agency = trim((string) ($row['agency_name'] ?? ''));
                $key = 'x:' . mb_strtolower($name, 'UTF-8') . '|' . mb_strtolower($agency, 'UTF-8');
            }

            if (!isset($people[$key])) {
                $people[$key] = [
                    'key' => $key,
                    'name' => $name,
                    'first_name' => $firstName,
                    'student_id' => $studentId > 0 ? $studentId : null,
                    'student_email' => trim((string) ($row['student_email'] ?? '')),
                    'agency' => $agency,
                    'shifts' => [],
                    'total_minutes' => 0,
                    'has_unparsed' => false,
                ];
            }

            $slot = famijobParseTimeSlot($row['time_slot']);
            $people[$key]['shifts'][] = [
                'date' => (string) $row['shift_date'],
                'department' => trim((string) $row['department_name']),
                'slot' => $slot,
                'comment' => trim((string) ($row['comment'] ?? '')),
                'validation_status' => (string) ($row['validation_status'] ?? ''),
            ];

            if ($slot['duration'] !== null) {
                $people[$key]['total_minutes'] += $slot['duration'];
            } else {
                $people[$key]['has_unparsed'] = true;
            }
        }

        foreach (array_keys($people) as $key) {
            usort($people[$key]['shifts'], static function ($a, $b) {
                if ($a['date'] !== $b['date']) {
                    return strcmp($a['date'], $b['date']);
                }
                return ($a['slot']['start'] ?? 0) <=> ($b['slot']['start'] ?? 0);
            });
        }

        uasort($people, static function ($a, $b) {
            $agency = strcasecmp((string) $a['agency'], (string) $b['agency']);
            return $agency !== 0 ? $agency : strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return $people;
    }
}

if (!function_exists('famijobBuildScheduleMailBody')) {
    /**
     * Corps HTML du mail d'horaire d'une personne.
     *
     * Écrit en tableaux et en styles en ligne : sendMail() le fait passer par
     * famiMailOutlookSafe(), qui neutralise ce qu'Outlook ne sait pas rendre,
     * mais un <div> en flexbox resterait cassé chez lui. Les sept jours sont
     * listés, y compris ceux sans prestation : un planning se lit d'un coup
     * d'œil, et un jour absent laisse un doute sur un oubli d'encodage.
     */
    function famijobBuildScheduleMailBody(array $person, DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, array $recipient)
    {
        $shiftsByDate = [];
        foreach ($person['shifts'] as $shift) {
            $shiftsByDate[$shift['date']][] = $shift;
        }

        $rows = '';
        $cursor = $weekStart;
        while ($cursor <= $weekEnd) {
            $dateKey = $cursor->format('Y-m-d');
            $dayLabel = famijobWeekdayLabelFr($cursor, true) . ' ' . $cursor->format('d/m');
            $dayShifts = $shiftsByDate[$dateKey] ?? [];

            if (empty($dayShifts)) {
                $rows .= '<tr>'
                    . '<td style="padding:9px 12px;border-bottom:1px solid #e4ece7;font-size:14px;color:#9aa8a0;">' . e($dayLabel) . '</td>'
                    . '<td style="padding:9px 12px;border-bottom:1px solid #e4ece7;font-size:14px;color:#9aa8a0;" colspan="3">Pas de prestation prévue</td>'
                    . '</tr>';
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $first = true;
            foreach ($dayShifts as $shift) {
                $slot = $shift['slot'];
                // Le libellé normalisé est construit par nos soins, le brut vient
                // d'une saisie libre : il ne part pas dans le mail sans échappement.
                $hours = $slot['is_parsed'] ? e($slot['label']) : e($slot['raw']);
                if ($slot['note'] !== '' && $slot['is_parsed']) {
                    $hours .= ' <span style="color:#7b8b82;">(' . e($slot['note']) . ')</span>';
                }

                $rows .= '<tr>'
                    . '<td style="padding:9px 12px;border-bottom:1px solid #e4ece7;font-size:14px;color:#244230;font-weight:' . ($first ? '700' : '400') . ';">'
                    . ($first ? e($dayLabel) : '') . '</td>'
                    . '<td style="padding:9px 12px;border-bottom:1px solid #e4ece7;font-size:14px;color:#244230;font-weight:700;">' . $hours . '</td>'
                    . '<td style="padding:9px 12px;border-bottom:1px solid #e4ece7;font-size:14px;color:#244230;">' . e($shift['department']) . '</td>'
                    . '<td style="padding:9px 12px;border-bottom:1px solid #e4ece7;font-size:14px;color:#5f7369;text-align:right;">' . e(famijobFormatDuration($slot['duration'])) . '</td>'
                    . '</tr>';
                $first = false;
            }

            $cursor = $cursor->modify('+1 day');
        }

        $greetName = trim((string) ($recipient['contact'] ?? ''));
        $greeting = $greetName !== '' ? 'Bonjour ' . e($greetName) . ',' : 'Bonjour,';

        // La personne est tutoyée — c'est le ton de toute la plateforme côté
        // étudiant. L'agence et Honorine sont vouvoyées.
        $isForPerson = ($recipient['kind'] ?? '') === 'student';

        if ($isForPerson) {
            $intro = 'Voici <strong>ton horaire</strong> pour la semaine à venir.';
            $closing = 'En cas d\'empêchement, préviens l\'équipe FamiJob le plus tôt possible.';
        } elseif (($recipient['kind'] ?? '') === 'interim') {
            $intro = 'Voici l\'horaire de <strong>' . e($person['name']) . '</strong>, rattaché(e) à votre agence <strong>' . e($recipient['agency']) . '</strong>, pour la semaine à venir.';
            $closing = 'La personne concernée a reçu ce même horaire de son côté. En cas de changement, merci de prévenir l\'équipe FamiJob.';
        } else {
            $intro = 'Voici l\'horaire de <strong>' . e($person['name']) . '</strong> pour la semaine à venir.';
            $closing = 'La personne concernée a reçu ce même horaire de son côté. En cas de changement, merci de prévenir l\'équipe FamiJob.';
        }

        $totalLine = ($isForPerson ? 'Total de ta semaine : ' : 'Total de la semaine : ')
            . '<strong>' . e(famijobFormatDuration($person['total_minutes'])) . '</strong>'
            . (!empty($person['has_unparsed']) ? ' <span style="color:#a8712a;">(un créneau n\'a pas pu être totalisé, voir le détail)</span>' : '');

        return '<div style="margin:0;padding:28px;background:#eef4ef;font-family:Open Sans,Arial,sans-serif;color:#244230;">'
            . '<div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 16px 34px rgba(27,54,36,0.12);">'
            . '<div style="padding:26px 30px;background:linear-gradient(135deg,#2d5a37 0%,#416e4b 100%);color:#ffffff;">'
            . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.85;">FamiJob</div>'
            . '<h1 style="margin:9px 0 0;font-size:25px;line-height:1.25;color:#ffffff;">Horaire de la semaine</h1>'
            . '<div style="margin-top:6px;font-size:15px;opacity:.92;">Du ' . e($weekStart->format('d/m/Y')) . ' au ' . e($weekEnd->format('d/m/Y')) . '</div>'
            . '</div>'
            . '<div style="padding:26px 30px;">'
            . '<p style="margin:0 0 16px;font-size:16px;line-height:1.6;">' . $greeting . '</p>'
            . '<p style="margin:0 0 20px;font-size:16px;line-height:1.6;">' . $intro . '</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;background:#f8fbf9;border:1px solid #dde9df;border-radius:14px;overflow:hidden;">'
            . '<tr style="background:#e8f2ea;">'
            . '<th align="left" style="padding:10px 12px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#4c6455;">Jour</th>'
            . '<th align="left" style="padding:10px 12px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#4c6455;">Horaire</th>'
            . '<th align="left" style="padding:10px 12px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#4c6455;">Département</th>'
            . '<th align="right" style="padding:10px 12px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#4c6455;">Durée</th>'
            . '</tr>'
            . $rows
            . '</table>'
            . '<p style="margin:18px 0 0;font-size:15px;line-height:1.6;">' . $totalLine . '</p>'
            . '<p style="margin:20px 0 0;font-size:14px;line-height:1.6;color:#617268;">' . $closing . '</p>'
            . '</div>'
            . '<div style="padding:16px 30px;background:#f5f8f6;color:#617268;font-size:13px;">Message automatique envoyé par FamiJob — Famiflora.</div>'
            . '</div></div>';
    }
}

if (!function_exists('famijobBuildScheduleMailSubject')) {
    function famijobBuildScheduleMailSubject(array $person, DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, $targetKind = 'agency')
    {
        $week = 'semaine du ' . $weekStart->format('d/m') . ' au ' . $weekEnd->format('d/m/Y');

        // La personne reçoit « ton horaire » : dans sa boîte, lire son propre
        // nom dans l'objet serait déroutant.
        return $targetKind === 'student'
            ? 'Ton horaire Famiflora — ' . $week
            : 'Horaire Famiflora — ' . $person['name'] . ' — ' . $week;
    }
}

if (!function_exists('famijobSendScheduleMail')) {
    /**
     * Envoie l'horaire d'UNE personne à TOUS ses destinataires.
     *
     * Deux mails distincts : un pour la personne, un pour son agence (ou
     * Honorine). Ils sont indépendants — si l'un échoue, l'autre part quand
     * même, et le rapport dit lequel a manqué. Un destinataire injoignable
     * (personne sans adresse, agence sans mail) est signalé, pas ignoré.
     *
     * En mode test, les destinataires réels restent calculés et journalisés,
     * mais l'envoi part vers l'adresse de test : le rapport montre donc
     * exactement ce qui partira une fois le mode coupé.
     *
     * @return array{success:bool,sent:array,failed:array,skipped:array}
     */
    function famijobSendScheduleMail(PDO $db, array $person, DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, array $targets, $sentByUserId = null)
    {
        $result = ['success' => false, 'sent' => [], 'failed' => [], 'skipped' => []];

        $testMode = famijobScheduleMailIsTestMode();
        $testRecipient = famijobScheduleMailTestRecipient();

        foreach ($targets as $target) {
            $label = $target['kind'] === 'student' ? 'la personne' : ('l\'agence ' . $target['label']);

            if (empty($target['emails'])) {
                $reason = $target['error'] !== '' ? $target['error'] : 'aucune adresse';
                $result['skipped'][] = $label . ' : ' . $reason;
                famijobLogScheduleMail($db, $person, $weekStart, $target['kind'], [], false, $reason, $sentByUserId);
                continue;
            }

            $realRecipients = $target['emails'];
            $actualRecipients = $testMode ? [$testRecipient] : $realRecipients;
            $actualRecipients = array_values(array_filter($actualRecipients, static function ($email) {
                return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
            }));

            if (empty($actualRecipients)) {
                $reason = 'Adresse d\'envoi invalide (mode test mal configuré ?).';
                $result['failed'][] = $label . ' : ' . $reason;
                famijobLogScheduleMail($db, $person, $weekStart, $target['kind'], $realRecipients, false, $reason, $sentByUserId);
                continue;
            }

            $subject = famijobBuildScheduleMailSubject($person, $weekStart, $weekEnd, $target['kind']);
            if ($testMode) {
                $subject = '[TEST -> ' . implode(', ', $realRecipients) . '] ' . $subject;
            }
            $body = famijobBuildScheduleMailBody($person, $weekStart, $weekEnd, $target);

            $sent = false;
            $errors = [];
            foreach ($actualRecipients as $email) {
                if (sendMail($email, $subject, $body, true)) {
                    $sent = true;
                } else {
                    $lastError = getLastMailError();
                    $errors[] = $email . ' : ' . ($lastError !== '' ? $lastError : 'échec inconnu');
                }
            }

            if ($sent) {
                $result['sent'][] = $label . ' → ' . implode(', ', $actualRecipients);
                $result['success'] = true;
            } else {
                $result['failed'][] = $label . ' : ' . implode(' | ', $errors);
            }

            famijobLogScheduleMail($db, $person, $weekStart, $target['kind'], $realRecipients, $sent, implode(' | ', $errors), $sentByUserId);
        }

        return $result;
    }
}

if (!function_exists('famijobLogScheduleMail')) {
    function famijobLogScheduleMail(PDO $db, array $person, DateTimeImmutable $weekStart, $targetKind, array $recipients, $success, $errorMessage, $sentByUserId = null)
    {
        try {
            famijobEnsureScheduleMailLog($db);
            $stmt = $db->prepare(
                'INSERT INTO interim_schedule_mail_log
                    (week_start, person_key, person_name, student_id, agency_name, target_kind, recipients, test_mode, success, error_message, sent_by_user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $weekStart->format('Y-m-d'),
                mb_substr((string) $person['key'], 0, 190, 'UTF-8'),
                mb_substr((string) $person['name'], 0, 190, 'UTF-8'),
                $person['student_id'] !== null ? (int) $person['student_id'] : null,
                mb_substr((string) $person['agency'], 0, 190, 'UTF-8'),
                mb_substr((string) $targetKind, 0, 20, 'UTF-8'),
                mb_substr(implode(', ', $recipients), 0, 500, 'UTF-8'),
                famijobScheduleMailIsTestMode() ? 1 : 0,
                $success ? 1 : 0,
                ((string) $errorMessage) === '' ? null : mb_substr((string) $errorMessage, 0, 500, 'UTF-8'),
                $sentByUserId !== null ? (int) $sentByUserId : null,
            ]);
        } catch (Exception $e) {
            error_log('[FamiJob] journal envoi horaire impossible : ' . $e->getMessage());
        }
    }
}

if (!function_exists('famijobLastScheduleMails')) {
    /** Dernier envoi réussi par personne pour une semaine donnée. */
    function famijobLastScheduleMails(PDO $db, DateTimeImmutable $weekStart)
    {
        try {
            famijobEnsureScheduleMailLog($db);
            // Détaillé par destinataire : « envoyé » ne veut rien dire si
            // l'agence a reçu et pas la personne.
            $stmt = $db->prepare(
                'SELECT person_key, target_kind, MAX(sent_at) AS last_sent, MAX(test_mode) AS test_mode
                 FROM interim_schedule_mail_log
                 WHERE week_start = ? AND success = 1
                 GROUP BY person_key, target_kind'
            );
            $stmt->execute([$weekStart->format('Y-m-d')]);

            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $personKey = (string) $row['person_key'];
                $slot = ((string) $row['target_kind']) === 'student' ? 'student' : 'agency';

                if (!isset($out[$personKey])) {
                    $out[$personKey] = ['student' => null, 'agency' => null, 'last_sent' => ''];
                }

                $out[$personKey][$slot] = [
                    'last_sent' => (string) $row['last_sent'],
                    'test_mode' => (int) $row['test_mode'] === 1,
                ];

                if ((string) $row['last_sent'] > $out[$personKey]['last_sent']) {
                    $out[$personKey]['last_sent'] = (string) $row['last_sent'];
                }
            }

            return $out;
        } catch (Exception $e) {
            return [];
        }
    }
}
