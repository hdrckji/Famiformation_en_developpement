<?php
// ============================================================
// export_matching.php — telecharger le classeur de la semaine.
//
// Cette page ne construit PLUS le fichier : elle lit les filtres, verifie qui
// demande, et delegue a includes/export_semaine.php. La meme fabrique sert aux
// pieces jointes envoyees a la validation du planning — deux constructions
// separees auraient fini par ne plus dire la meme chose, et c'est un fichier
// qu'on diffuse sans le relire.
// ============================================================

require_once 'config.php';
verifierConnexion($db);

require_once __DIR__ . '/includes/confidentialite.php';
require_once __DIR__ . '/includes/export_semaine.php';

$role = (string) ($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'teamcoach', 'agence_interim'], true)) {
    header('Location: ' . famijobSiteUrl('index.php'));
    exit();
}
$isAdmin = ($role === 'admin');

// L'agence de celui qui demande : elle decide des noms qu'il a le droit de lire.
$agencyName = '';
if (!$isAdmin) {
    try {
        $agencyStmt = $db->prepare('SELECT interim FROM utilisateurs WHERE id = ? LIMIT 1');
        $agencyStmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
        $agencyName = trim((string) $agencyStmt->fetchColumn());
    } catch (Exception $e) {}
}

// --- Semaine demandee (on se cale toujours sur le lundi) ---
$today = new DateTimeImmutable('today');
$defaultMonday = $today->modify('monday this week');
try {
    $weekStart = new DateTimeImmutable((string) ($_GET['week'] ?? $defaultMonday->format('Y-m-d')));
} catch (Exception $e) {
    $weekStart = $defaultMonday;
}
$weekStart = $weekStart->modify('monday this week');

// --- Filtres : un seul secteur (?secteur=) ou plusieurs (?secteurs[]=) ---
// Le premier vient du bouton qui suit les filtres a l'ecran, le second de la
// fenetre de selection. Les deux se rejoignent ici.
$filtreSecteurs = [];
if (!empty($_GET['secteurs']) && is_array($_GET['secteurs'])) {
    $filtreSecteurs = array_values(array_filter(array_map('strval', $_GET['secteurs'])));
} elseif (trim((string) ($_GET['secteur'] ?? '')) !== '') {
    $filtreSecteurs = [trim((string) $_GET['secteur'])];
}

$filtreDept = trim((string) ($_GET['department'] ?? ''));
if ($filtreDept === 'all') {
    $filtreDept = '';
}

$donnees = famijobSemaineDonnees($db, $weekStart, [
    'secteurs'    => $filtreSecteurs,
    'departement' => $filtreDept,
    'agence'      => $agencyName,
    'role'        => $role,
    // Une agence telecharge la semaine ENTIERE, avec les noms des autres
    // masques : elle a besoin de voir ce qui est deja pris pour savoir ou
    // proposer quelqu'un. C'est la piece jointe du mail de validation, elle,
    // qui se limite a ses propres creneaux.
    'seulementAgence' => false,
]);

$xlsx = famijobSemaineXlsx($weekStart, $donnees['deptNames'], $donnees['byDept']);

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (is_string($xlsx) && $xlsx !== '') {
    $nom = 'matching_semaine_' . $weekStart->format('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nom . '"');
    header('Content-Length: ' . strlen($xlsx));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $xlsx;
    exit();
}

// Repli CSV : PhpSpreadsheet indisponible.
$csv = famijobSemaineCsv($weekStart, $donnees['deptNames'], $donnees['byDept']);
$nom = 'matching_semaine_' . $weekStart->format('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nom . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $csv;
exit();
