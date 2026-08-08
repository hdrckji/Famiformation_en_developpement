<?php
/**
 * ⬅️ BANDEAU « RETOUR », commun à toutes les pages.
 *
 * Plusieurs pages n'offraient AUCUN moyen de revenir en arrière : une fois
 * dedans, il fallait connaître le bouton « précédent » du navigateur, ou
 * retaper l'adresse. Sur une borne ou un téléphone en magasin, c'est un
 * cul-de-sac.
 *
 * Le lien retombe toujours sur ses pieds, dans cet ordre :
 *   1. la page d'où l'on vient, si elle appartient au site (Referer) ;
 *   2. sinon l'accueil, qui dépend du profil (les bêta ont le leur).
 * Le clic tente d'abord history.back() : c'est ce à quoi on s'attend quand on
 * a ouvert trois pages à la suite. Le href reste une vraie adresse, donc ça
 * fonctionne aussi sans JavaScript et au clic-droit « ouvrir dans un onglet ».
 *
 * Usage, juste après <body> :  <?php require_once __DIR__ . '/includes/retour.php'; echo barreRetour(); ?>
 */

if (!function_exists('retourAccueilSelonProfil')) {
    /** L'accueil qui correspond au profil connecté. */
    function retourAccueilSelonProfil()
    {
        $role = (string) ($_SESSION['role'] ?? '');
        if ($role === 'beta') { return 'beta.php'; }
        if ($role === 'betalapanne') { return 'lapanne.php'; }
        if ($role === 'agence_interim') { return 'interim_horaires.php'; }
        if ($role === 'evaluateur') { return 'evaluation.php'; }
        return 'index.php';
    }
}

if (!function_exists('barreRetour')) {
    /**
     * @param string $cible  Adresse forcée (facultatif). Sinon on déduit.
     * @param string $texte  Libellé forcé (facultatif). Sinon « Retour ».
     */
    function barreRetour($cible = '', $texte = '')
    {
        if ($cible === '') {
            // La page précédente, mais seulement si elle vient de CHEZ NOUS :
            // un Referer externe renverrait la personne hors du site.
            $ref  = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            $hote = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $ok = $ref !== '' && $hote !== '' && stripos((string) parse_url($ref, PHP_URL_HOST), $hote) === 0;
            // On ne revient pas sur la page où l'on est déjà : ça ne ferait rien.
            $memePage = $ok && basename((string) parse_url($ref, PHP_URL_PATH)) === basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $cible = ($ok && !$memePage) ? $ref : retourAccueilSelonProfil();
        }
        if ($texte === '') {
            $texte = function_exists('t') ? t('Retour', 'Terug') : 'Retour';
        }
        $e = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

        return '<style>'
            . '.fami-retour{max-width:1100px;margin:0 auto 14px;padding:0 4px;}'
            . '.fami-retour a{display:inline-flex;align-items:center;gap:8px;background:#fff;'
            . 'color:#2d5a37;text-decoration:none;font-weight:700;font-size:.92rem;'
            . 'padding:9px 18px;border-radius:30px;box-shadow:0 3px 10px rgba(0,0,0,.08);'
            . 'border:2px solid #d8e6dc;transition:all .2s ease;}'
            . '.fami-retour a:hover{background:#2d5a37;color:#fff;border-color:#2d5a37;}'
            . '@media print{.fami-retour{display:none;}}'
            . '</style>'
            . '<div class="fami-retour"><a href="' . $e($cible) . '" '
            . 'onclick="if(history.length>1){history.back();return false;}">← ' . $e($texte) . '</a></div>';
    }
}
