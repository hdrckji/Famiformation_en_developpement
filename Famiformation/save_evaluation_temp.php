<?php
// save_evaluation_temp.php
require_once 'config.php';
verifierConnexion($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();
    $data = [
        'nom' => $_POST['nom_evalue'] ?? '',
        'prenom' => $_POST['prenom_evalue'] ?? '',
        'date' => $_POST['date_eval'] ?? '',
        'score_chariot_test' => $_POST['score_chariot_test'] ?? '',
        'score' => $_POST['score'] ?? '',
        'remarques1' => $_POST['remarques1'] ?? '',
        'remarques2' => $_POST['remarques2'] ?? '',
        'remarques3' => $_POST['remarques3'] ?? '',
        'encart1' => $_POST['encart1'] ?? '',
        'encart2' => $_POST['encart2'] ?? '',
        'commentaire' => $_POST['commentaire'] ?? ''
    ];
    $tempFilePath = __DIR__ . '/evaluation_temp.json';
    $temp = [];
    if (file_exists($tempFilePath)) {
        $raw = file_get_contents($tempFilePath);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $temp = $decoded;
            }
        }
    }

    $temp[] = $data;

    $json = json_encode($temp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo 'Erreur JSON';
        exit();
    }

    $writeResult = file_put_contents($tempFilePath, $json, LOCK_EX);
    if ($writeResult === false) {
        http_response_code(500);
        echo 'Erreur écriture';
        exit();
    }

    echo 'OK';
} else {
    echo 'Erreur';
}
