<?php
// ============================================================
// FAMICARD — amorçage.
//
// CHOIX STRUCTURANT : on NE duplique PAS la configuration du site.
// FamiJob a recopié les ~500 lignes de config.php (session, .env, PDO,
// fuseau horaire) ; résultat, chaque correction doit être faite deux fois et
// les deux copies ont déjà divergé. Famicard réutilise la configuration de
// FamiFormation telle quelle : même session, même base, même fuseau, même CSRF.
//
// C'est aussi ce qui rend la « carte d'identité » cohérente par construction :
// Famicard lit la MÊME table `utilisateurs` que FamiFormation et FamiJob. Ce
// n'est pas une base parallèle, c'est une vue sur la base existante.
// ============================================================

// ─────────────────────────────────────────────────────────────────────────────
// LOCALISATION DU CONFIG PRINCIPAL
// Deux dispositions coexistent et doivent marcher toutes les deux :
//   • dans le CONTENEUR, FamiFormation est déployé à la racine servie
//     (/app/public) et Famicard dans /app/public/famicard → « ../config.php » ;
//   • dans le DÉPÔT, Famicard et Famiformation sont deux dossiers frères
//     → « ../Famiformation/config.php ».
// On essaie les deux plutôt que de supposer : sans ça, le site marche en prod
// et casse en local (ou l'inverse), pour une raison invisible.
// ─────────────────────────────────────────────────────────────────────────────
$__famicardConfig = null;
foreach ([__DIR__ . '/../config.php', __DIR__ . '/../Famiformation/config.php'] as $__candidat) {
    if (is_file($__candidat)) {
        $__famicardConfig = $__candidat;
        break;
    }
}

if ($__famicardConfig === null) {
    http_response_code(500);
    die('Famicard : configuration du site introuvable.');
}

require_once $__famicardConfig;
require_once __DIR__ . '/includes/carte.php';

// ─────────────────────────────────────────────────────────────────────────────
// RACINE DU SITE
// Famicard vit dans un sous-dossier : tout lien relatif vers le site principal
// (« login.php », « index.php ») tomberait dans /famicard/ et donc dans le vide.
// C'est exactement le piège dans lequel verifierConnexion() tombe : il fait
// header('Location: login.php'), ce qui depuis ici vise /famicard/login.php.
// On passe donc par des chemins ABSOLUS, calculés une fois.
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('famicardSiteUrl')) {
    function famicardSiteUrl($chemin = '')
    {
        return '/' . ltrim((string) $chemin, '/');
    }
}

if (!function_exists('famicardExigeConnexion')) {
    /**
     * Garde-fou d'accès. Remplace verifierConnexion() pour la raison ci-dessus.
     * Renvoie la ligne `utilisateurs` du collaborateur connecté.
     */
    function famicardExigeConnexion(PDO $db)
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . famicardSiteUrl('login.php'));
            exit();
        }

        $st = $db->prepare("SELECT * FROM utilisateurs WHERE id = ? LIMIT 1");
        $st->execute([(int) $_SESSION['user_id']]);
        $moi = $st->fetch(PDO::FETCH_ASSOC);

        // Session valide mais compte disparu (supprimé pendant la session) :
        // on ne laisse pas une page à moitié vide, on renvoie à la connexion.
        if (!$moi) {
            session_unset();
            session_destroy();
            header('Location: ' . famicardSiteUrl('login.php'));
            exit();
        }

        return $moi;
    }
}

if (!function_exists('famicardEstAdmin')) {
    function famicardEstAdmin()
    {
        return (($_SESSION['role'] ?? '') === 'admin');
    }
}
