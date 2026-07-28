<?php
require_once 'config.php';
require_once 'includes/quizz_status.php';

if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== famiGetEnv('CRON_TOKEN', ''))) {
    die('Accès non autorisé.');
}

ensureStudentAvailabilityTable($db);

$today = new DateTimeImmutable('today');
$nextMonday = $today->modify('next monday');
$nextSunday = $nextMonday->modify('+6 days');

$studentsStmt = $db->query("SELECT id, nom, prenom, identifiant, email FROM utilisateurs WHERE role = 'etudiant' ORDER BY nom ASC, prenom ASC");
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$availStmt = $db->prepare(
    "SELECT availability_date, availability_status, note
     FROM student_availabilities
     WHERE user_id = ? AND availability_date BETWEEN ? AND ?
     ORDER BY availability_date ASC"
);

$statusLabels = [
    'non_renseigne' => 'Non renseigné',
    'indisponible' => 'Indisponible',
    'matin' => 'Disponible le matin',
    'apres_midi' => 'Disponible l’après-midi',
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

$sections = [];
foreach ($students as $student) {
    if (!hasUnlockedOnboarding((int) $student['id']) || !hasCompletedOnboarding((int) $student['id'])) {
        continue;
    }

    $availStmt->execute([
        (int) $student['id'],
        $nextMonday->format('Y-m-d'),
        $nextSunday->format('Y-m-d'),
    ]);
    $rows = $availStmt->fetchAll(PDO::FETCH_ASSOC);

    $sections[] = [
        'student' => $student,
        'rows' => $rows,
    ];
}

if (empty($sections)) {
    echo 'Aucune disponibilité étudiante à envoyer.';
    exit();
}

$subject = 'Disponibilités étudiants - semaine du ' . $nextMonday->format('d/m/Y');
$body = '<div style="font-family:Open Sans,Arial,sans-serif;background:#f4f7f6;padding:24px;color:#2d5a37">'
    . '<div style="max-width:900px;margin:auto;background:#fff;border-radius:18px;padding:30px;box-shadow:0 10px 30px rgba(45,90,55,0.09)">'
    . '<h2 style="margin-top:0;color:#2d5a37">Disponibilités étudiants</h2>'
    . '<p>Compilation automatique des disponibilités pour la semaine prochaine.</p>'
    . '<p><strong>Période :</strong> du ' . $nextMonday->format('d/m/Y') . ' au ' . $nextSunday->format('d/m/Y') . '</p>';

foreach ($sections as $section) {
    $student = $section['student'];
    $rows = $section['rows'];
    $body .= '<div style="margin-top:24px;padding:18px;border:1px solid #dbe7dd;border-radius:16px;background:#fbfdfb">';
    $body .= '<div style="font-size:18px;font-weight:700;color:#214b35;margin-bottom:10px;">' . htmlspecialchars($student['prenom'] . ' ' . $student['nom']) . '</div>';
    $body .= '<div style="font-size:14px;color:#617268;margin-bottom:12px;">Identifiant : ' . htmlspecialchars($student['identifiant']) . '</div>';

    if (empty($rows)) {
        $body .= '<div style="padding:12px 14px;border-radius:12px;background:#fff8e7;color:#8c6410;font-weight:700;">Aucune disponibilité renseignée pour cette semaine.</div>';
    } else {
        $body .= '<table style="width:100%;border-collapse:collapse;margin-top:12px;">';
        $body .= '<thead><tr>'
            . '<th style="text-align:left;padding:10px;background:#eef5ef;border-bottom:1px solid #dbe7dd;">Date</th>'
            . '<th style="text-align:left;padding:10px;background:#eef5ef;border-bottom:1px solid #dbe7dd;">Disponibilité</th>'
            . '<th style="text-align:left;padding:10px;background:#eef5ef;border-bottom:1px solid #dbe7dd;">Précision</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $status = $statusLabels[$row['availability_status']] ?? $row['availability_status'];
            $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $row['availability_date']);
            $formattedDate = $date ? (($weekdayLabels[$date->format('l')] ?? $date->format('l')) . ' ' . $date->format('d/m/Y')) : (string) $row['availability_date'];
            $body .= '<tr>'
                . '<td style="padding:10px;border-bottom:1px solid #edf1ee;">' . htmlspecialchars($formattedDate) . '</td>'
                . '<td style="padding:10px;border-bottom:1px solid #edf1ee;font-weight:700;color:#214b35;">' . htmlspecialchars($status) . '</td>'
                . '<td style="padding:10px;border-bottom:1px solid #edf1ee;">' . htmlspecialchars((string) ($row['note'] ?? '')) . '</td>'
                . '</tr>';
        }

        $body .= '</tbody></table>';
    }

    $body .= '</div>';
}

$body .= '<div style="margin-top:24px;font-size:13px;color:#617268;">Message automatique envoyé par FamiFormation.</div>';
$body .= '</div></div>';

$sent = sendMail(famiGetEnv('MAIL_ADMIN', 'jimmy.hendrickx@famiflora.be'), $subject, $body, true);

if ($sent) {
    echo 'Rapport des disponibilités envoyé avec succès.';
} else {
    echo 'Échec de l\'envoi du rapport des disponibilités : ' . getLastMailError();
}