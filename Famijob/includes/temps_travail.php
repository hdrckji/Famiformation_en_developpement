<?php
// ============================================================
// Chargeur du fichier PARTAGE includes/temps_travail.php.
//
// Les regles de temps de travail sont ecrites UNE FOIS, du cote du site. Ce
// fichier ne fait que les trouver : deux arborescences differentes selon qu'on
// soit dans le depot ou dans le conteneur, exactement comme secteurs.php.
//
//   depot       Famijob/includes/  -> ../../Famiformation/includes/
//   deploiement /app/public/famijob/includes/ -> ../../includes/
//
// Si rien n'est trouve, on ne plante pas et on ne devine pas : les fonctions
// existent quand meme, mais renvoient null. Les pages affichent alors ce
// qu'elles affichaient avant qu'on sache calculer — pas un chiffre invente.
// ============================================================

if (!function_exists('tempsTravailEffectif')) {
    $ttPistes = [
        __DIR__ . '/../../Famiformation/includes/temps_travail.php',   // depot dev
        __DIR__ . '/../../includes/temps_travail.php',                 // deploiement
        __DIR__ . '/../../public/includes/temps_travail.php',          // depot live
        __DIR__ . '/temps_travail_regles.php',
    ];
    foreach ($ttPistes as $ttPiste) {
        if (is_file($ttPiste)) {
            require_once $ttPiste;
            break;
        }
    }
    unset($ttPistes, $ttPiste);
}

if (!function_exists('tempsTravailEffectif')) {
    function tempsTravailPause() { return 1.0; }
    function tempsTravailMin() { return 3.0; }
    function tempsTravailMax() { return 9.0; }
    function tempsTravailSansPause() { return [[12.0, 17.0]]; }
    function tempsTravailPaires($texte) { return null; }
    function tempsTravailAmplitude($texte) { return null; }
    function tempsTravailEffectif($texte) { return null; }
    function tempsTravailHorsNormes($texte) { return null; }
    function tempsTravailFormate($heures) { return (string) $heures; }
    function tempsTravailReglesJson() { return '{"pause":1,"min":3,"max":9,"sansPause":[[12,17]]}'; }
}
