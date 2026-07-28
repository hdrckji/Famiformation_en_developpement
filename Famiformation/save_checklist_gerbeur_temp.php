<?php
require_once 'config.php';
verifierConnexion($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    $data = [
        'nom_evalue' => $_POST['nom_evalue'] ?? '',
        'prenom_evalue' => $_POST['prenom_evalue'] ?? '',
        'date_eval' => $_POST['date_eval'] ?? '',
        'checklist' => $_POST['checklist'] ?? [],
        'item_notes' => $_POST['item_notes'] ?? [],
        'remarques_generales' => $_POST['remarques_generales'] ?? '',
        'saved_at' => date('c'),
    ];

    $temp = [];
    if (file_exists('checklist_gerbeur_temp.json')) {
        $temp = json_decode(file_get_contents('checklist_gerbeur_temp.json'), true) ?? [];
    }

    $temp[] = $data;
    file_put_contents('checklist_gerbeur_temp.json', json_encode($temp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo 'OK';
    exit();
}

echo 'Erreur';