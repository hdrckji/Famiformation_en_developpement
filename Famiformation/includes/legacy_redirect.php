<?php
// ============================================================
//  REDIRECTION DES PAGES MIGRÉES
//
//  Vider `modules.link` ne suffit pas à faire apparaître le contenu importé :
//  plusieurs anciennes pages sont atteintes par des liens ÉCRITS EN DUR dans
//  d'autres pages (index.php → onboarding.php → view-pdf-onboarding.php), donc
//  sans jamais passer par une tuile pilotée par la base.
//
//  On redirige donc à la source : dès qu'une page a été basculée (son module
//  porte link_legacy et n'a plus de link), la page elle-même renvoie vers
//  module.php. Ça rattrape d'un coup les tuiles en dur, les favoris et les
//  liens déjà partagés par mail.
//
//  Échappatoire : ?legacy=1 affiche quand même l'ancienne page (vérification
//  avant de supprimer les fichiers).
// ============================================================

if (!function_exists('famiLegacyRedirect')) {
    function famiLegacyRedirect($db)
    {
        if (!($db instanceof PDO) || PHP_SAPI === 'cli') {
            return;
        }
        if (!empty($_GET['legacy'])) {
            return; // consultation volontaire de l'ancienne page
        }
        $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($page === '' || $page === 'module.php') {
            return;
        }
        // Pages qui SERVENT un fichier (en-tête application/pdf, etc.) : les
        // rediriger renverrait du HTML à un <iframe> ou à un téléchargement.
        // Aucun module ne les cible aujourd'hui, mais un mauvais `link` saisi
        // à la main suffirait à casser l'affichage des documents.
        if (in_array($page, ['pdf-onboarding.php', 'media.php', 'video_download.php'], true)) {
            return;
        }

        // Filtre statique AVANT toute requête : la quasi-totalité des pages du
        // site n'est pas concernée, et config.php est chargé partout.
        static $map = null;
        if ($map === null) {
            $f = __DIR__ . '/medias_legacy_map.php';
            if (is_file($f)) { require_once $f; }
            $map = function_exists('famiLegacyMediaMap') ? famiLegacyMediaMap() : [];
        }
        if (!isset($map[$page])) {
            return;
        }

        try {
            $st = $db->prepare("SELECT id FROM modules
                                 WHERE link_legacy = ? AND (link IS NULL OR link = '')
                                 LIMIT 1");
            $st->execute([$page]);
            $id = (int) $st->fetchColumn();
        } catch (Exception $e) {
            return; // colonne link_legacy absente : rien n'a encore été basculé
        }

        if ($id > 0) {
            header('Location: module.php?id=' . $id, true, 302);
            exit;
        }
    }
}
