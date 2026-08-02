<?php
// ============================================================
// FAMICARD — LE BADGE IMPRIMABLE.
//
// Format arrêté : 75 mm de LONGUEUR × 36 mm de hauteur (paysage).
// Contenu arrêté : le PRÉNOM, et dessous la mention en français et en
// néerlandais. Rien d'autre. Un badge se perd, se photographie et traîne sur
// un comptoir : moins il en dit, mieux c'est. C'est aussi pour ça que le
// modèle marque 'badge' => true sur le seul prénom (voir includes/carte.php).
//
// Mention : « À votre disposition / Tot uw dienst », remplacée par
// « Étudiant / Student » pour les étudiants.
//
// TOUT EST EN MILLIMÈTRES, jamais en pixels : un badge coté en px sort à une
// taille qui dépend de l'écran et du navigateur, donc jamais à 75 × 36.
// ============================================================
require_once __DIR__ . '/config.php';

$moi = famicardExigeConnexion($db);
$estAdmin = famicardEstAdmin();

// Un administrateur peut imprimer le badge d'un autre collaborateur (?id=).
// Tout le monde imprime le sien par défaut. Un non-admin qui passerait un id
// ne l'obtient pas : il retombe sur sa propre fiche, sans message d'erreur —
// il n'a pas à savoir si l'id existe.
$cible = $moi;
if ($estAdmin && isset($_GET['id']) && (int) $_GET['id'] > 0 && (int) $_GET['id'] !== (int) $moi['id']) {
    $st = $db->prepare("SELECT * FROM utilisateurs WHERE id = ? LIMIT 1");
    $st->execute([(int) $_GET['id']]);
    $trouve = $st->fetch(PDO::FETCH_ASSOC);
    if ($trouve) {
        $cible = $trouve;
    }
}

$prenom  = trim((string) ($cible['prenom'] ?? ''));
if ($prenom === '') {
    $prenom = trim((string) ($cible['identifiant'] ?? ''));
}
$mention = famicardMentionBadge($cible['role'] ?? '');

// Un prénom long ne doit pas déborder du carton ni être coupé : on descend
// d'un cran, puis de deux. Mesuré sur « Marie-Christine », le pire cas courant.
$tailleP = 'p-normal';
$n = function_exists('mb_strlen') ? mb_strlen($prenom, 'UTF-8') : strlen($prenom);
if ($n > 10) { $tailleP = 'p-moyen'; }
if ($n > 15) { $tailleP = 'p-petit'; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Badge - <?= e($prenom) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    /* ── LE BADGE ────────────────────────────────────────────────────────────
       Les dimensions ci-dessous sont les vraies : 75 mm × 36 mm. Elles servent
       aussi bien à l'aperçu écran qu'à l'impression, pour que ce qu'on voit
       soit exactement ce qui sort. */
    .badge {
        width: 75mm; height: 36mm;
        box-sizing: border-box;
        padding: 3mm 4mm;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        text-align: center;
        font-family: 'Open Sans', Arial, sans-serif;
        color: #1f3d27;
        background: #fff;
        overflow: hidden;
    }
    .badge .prenom { font-weight: 800; line-height: 1.05; letter-spacing: .2mm; }
    .badge .p-normal { font-size: 11mm; }
    .badge .p-moyen  { font-size: 8.5mm; }
    .badge .p-petit  { font-size: 6.5mm; }

    /* Filet vert : repère visuel qui sépare le prénom de la mention. */
    .badge .filet { width: 22mm; height: .7mm; background: #2d5a37; margin: 2mm 0 1.8mm; border-radius: 1mm; }

    .badge .mention { font-size: 3.2mm; font-weight: 600; line-height: 1.35; }
    .badge .mention .nl { color: #5c7a64; font-weight: 400; }

    /* ── APERÇU À L'ÉCRAN ────────────────────────────────────────────────── */
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 30px 16px 60px; color: #333; }
    .zone { max-width: 640px; margin: 0 auto; }
    h1 { font-size: 1.2rem; color: #2d5a37; margin: 0 0 4px; }
    .sous { color: #666; font-size: .9rem; margin: 0 0 22px; }
    .cadre { display: inline-block; padding: 10px; background: #fff; border-radius: 10px; box-shadow: 0 6px 18px rgba(0,0,0,.1); }
    .cadre .badge { border: 1px dashed #cfd8d2; }
    .barre { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
    .bouton { border: 0; border-radius: 30px; padding: 12px 26px; font-family: inherit; font-weight: 700; font-size: .92rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-vide { background: #fff; color: #2d5a37; }
    .note { margin-top: 22px; font-size: .86rem; color: #666; line-height: 1.6; background: #fff; border-left: 4px solid #E9A93C; border-radius: 10px; padding: 12px 16px; }

    /* ── IMPRESSION ──────────────────────────────────────────────────────────
       La page fait exactement la taille du badge, sans marge : l'imprimante
       sort le carton seul, pas un badge perdu au milieu d'une feuille A4. */
    @page { size: 75mm 36mm; margin: 0; }
    @media print {
        body { background: #fff; padding: 0; margin: 0; }
        .zone > *:not(.cadre) { display: none !important; }
        .cadre { padding: 0; box-shadow: none; border-radius: 0; display: block; }
        .cadre .badge { border: 0; }
    }
</style>
</head>
<body>
<div class="zone">

    <h1>Badge de <?= e($prenom) ?></h1>
    <p class="sous">75 × 36 mm — taille réelle ci-dessous.</p>

    <div class="cadre">
        <div class="badge">
            <div class="prenom <?= $tailleP ?>"><?= e($prenom) ?></div>
            <div class="filet"></div>
            <div class="mention">
                <div class="fr"><?= e($mention['fr']) ?></div>
                <div class="nl"><?= e($mention['nl']) ?></div>
            </div>
        </div>
    </div>

    <div class="barre">
        <button class="bouton bouton-plein" onclick="window.print()">🖨️ Imprimer</button>
        <a class="bouton bouton-vide" href="index.php">Retour à ma carte</a>
        <?php if ($estAdmin): ?>
            <a class="bouton bouton-vide" href="admin.php">Base des collaborateurs</a>
        <?php endif; ?>
    </div>

    <p class="note">
        Dans la fenêtre d'impression, choisis le format <b>75 × 36 mm</b> et mets les marges
        à <b>zéro</b>. Si l'imprimante impose de l'A4, décoche « ajuster à la page » :
        sans ça le badge sort agrandi et ne rentre plus dans le porte-badge.
    </p>

</div>
</body>
</html>
