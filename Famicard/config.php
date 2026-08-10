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
// DEUX ADRESSES POUR LES MÊMES PAGES
// Famicard se visite de deux façons, et les deux doivent marcher :
//   • www.famiformation.com/famicard/ — Famicard est un sous-dossier du site ;
//   • famicard.famiformation.com      — le Caddyfile réécrit tout vers famicard/,
//     qui devient donc LA RACINE de ce sous-domaine.
//
// Toute la difficulté tient dans cette bascule. Sur le sous-domaine, « / » n'est
// plus le site principal : un lien écrit « /index.php » ou « /favicon.ico » est
// réécrit vers famicard/ et tombe dans le vide. C'est le piège dans lequel
// FamiJob était déjà tombé sur student.famiformation.com (voir famijobSiteUrl).
//
// D'où deux fonctions, à ne pas confondre :
//   • famicardSiteUrl()  → une page ou un fichier du SITE PRINCIPAL
//     (login du site, profil.php, favicon, photos de profil). Sur le
//     sous-domaine, c'est forcément une URL absolue vers www.
//   • les liens INTERNES à Famicard restent écrits en relatif (« badge.php »,
//     « login.php ») : Famicard est un dossier plat, donc le relatif vise juste
//     dans les deux dispositions, sans rien calculer.
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('famicardRacineSite')) {
    /**
     * Le DOSSIER de FamiFormation sur le disque — celui qui contient config.php,
     * media.php, includes/ et uploads/.
     *
     * À ne pas confondre avec famicardSiteUrl(), qui rend une URL. Ici c'est un
     * chemin de fichiers : il sert à lire des briques du site (compression
     * d'image) et à retomber sur son dossier uploads/ quand le volume n'est pas
     * monté. On retient la disposition RÉELLEMENT trouvée au chargement plutôt
     * que d'en supposer une : le dépôt et le conteneur ne rangent pas Famicard
     * au même endroit (voir en tête de fichier).
     */
    function famicardRacineSite()
    {
        static $racine = null;
        if ($racine === null) {
            $racine = dirname((string) ($GLOBALS['__famicardConfig'] ?? __DIR__ . '/../config.php'));
        }
        return $racine;
    }
}

if (!function_exists('famicardSurSousDomaine')) {
    function famicardSurSousDomaine()
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = explode(':', $host)[0];

        return ($host === 'famicard.famiformation.com');
    }
}

if (!function_exists('famicardSiteUrl')) {
    function famicardSiteUrl($chemin = '')
    {
        $chemin = ltrim((string) $chemin, '/');

        if (famicardSurSousDomaine()) {
            return 'https://www.famiformation.com/' . $chemin;
        }

        return '/' . $chemin;
    }
}

if (!function_exists('famicardPageDemandee')) {
    /**
     * Page Famicard visée par la requête en cours, en relatif (« badge.php?id=3 »),
     * pour y revenir une fois la connexion faite.
     *
     * Liste blanche volontaire : cette valeur finit dans un en-tête Location. Si
     * on renvoyait l'URL demandée telle quelle, un lien fabriqué transformerait
     * la page de connexion en tremplin vers n'importe quel site — un visiteur
     * verrait le vrai formulaire Famiflora, puis atterrirait ailleurs.
     */
    function famicardPageDemandee()
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $page = basename((string) parse_url($uri, PHP_URL_PATH));

        if (!in_array($page, ['index.php', 'fiche.php', 'photo.php', 'modifier.php', 'validations.php', 'admin.php', 'admin_champs.php', 'badge.php', 'export.php'], true)) {
            return '';
        }

        $query = (string) parse_url($uri, PHP_URL_QUERY);
        // Caractères d'URL ordinaires uniquement : ni « : » ni « / », donc pas
        // moyen de reconstruire une adresse absolue, et pas de saut de ligne.
        if ($query !== '' && preg_match('~^[A-Za-z0-9_=&%.+-]+$~', $query)) {
            return $page . '?' . $query;
        }

        return $page;
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
            // Vers le login DE FAMICARD (login.php, en relatif : il est dans le
            // même dossier). Pas celui du site principal : sur le sous-domaine,
            // la session de www n'existe pas ici — s'y connecter ne changerait
            // rien au retour, et la page tournerait en rond.
            $_SESSION['famicard_apres_login'] = famicardPageDemandee();
            header('Location: login.php');
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
            header('Location: login.php');
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
