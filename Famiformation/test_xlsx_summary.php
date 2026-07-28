<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$spreadsheet = IOFactory::load('test2.xlsx');
$sheet = $spreadsheet->getActiveSheet();

// Extraction de la première ligne (noms de colonnes)
$header = [];
foreach ($sheet->getRowIterator(1, 1) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    foreach ($cellIterator as $cell) {
        $header[] = $cell->getValue();
    }
}

// Extraction d'une ligne d'exemple (2e ligne)
$example = [];
foreach ($sheet->getRowIterator(2, 2) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    foreach ($cellIterator as $cell) {
        $example[] = $cell->getValue();
    }
}

// Résumé des colonnes
$horaires = [];
$departements_jours = [];
$identite = [];

foreach ($header as $i => $col) {
    $col_lower = strtolower($col);
    if (preg_match('/horaire|heure|début|fin|shift|planning/', $col_lower)) {
        $horaires[] = $col;
    } elseif (preg_match('/département|service|jour|secteur|zone|magasin|rayon|green/', $col_lower)) {
        $departements_jours[] = $col;
    } elseif (preg_match('/nom|prénom|name|surname|identité/', $col_lower)) {
        $identite[] = $col;
    }
}

// Affichage
function print_summary($title, $cols, $color = null) {
    echo ($color ? "<span style='color:$color'>" : "") . $title . ": " . implode(', ', $cols) . ($color ? "</span>" : "") . "\n";
}

print_summary('Colonnes horaire de travail', $horaires);
print_summary('Colonnes département/jour', $departements_jours, 'green');
print_summary('Colonnes identité', $identite);

// Affichage de la première ligne et exemple
print_summary('Noms de colonnes', $header);
print_summary('Exemple de ligne', $example);
