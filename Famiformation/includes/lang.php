<?php
// ============================================================
// Gestion de la langue (FR / NL)
// ============================================================

if (!function_exists('initLang')) {
    /**
     * Initialise la langue depuis ?lang=fr|nl (mémorisée en session).
     * À appeler une fois après session_start().
     */
    function initLang()
    {
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'nl'], true)) {
            $_SESSION['lang'] = $_GET['lang'];
        }
        if (empty($_SESSION['lang'])) {
            $_SESSION['lang'] = 'fr';
        }
    }

    /** Langue courante ('fr' ou 'nl'). */
    function currentLang()
    {
        return $_SESSION['lang'] ?? 'fr';
    }

    /** Renvoie le texte FR ou NL selon la langue courante. */
    function t($fr, $nl)
    {
        return (currentLang() === 'nl') ? $nl : $fr;
    }
}
