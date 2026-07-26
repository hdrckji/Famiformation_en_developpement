<?php
// ============================================================
// beta-magasin.php — Module MAGASIN de la version beta.
// Un seul rayon ACTIF pour l'instant : la Caisse (avec du contenu). Les autres
// rayons (Green, Déco, Animalerie, Food, Logistique) sont affichés GRISÉS/
// inactifs, juste pour visualiser le concept. Réservé au rôle « beta ».
// ============================================================
require_once 'config.php';
verifierConnexion($db);

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'beta') {
    header('Location: index.php');
    exit();
}

// Id d'un module beta par nom + parent (les modules Caisse sont SOUS le conteneur « Caisse »).
function betaModId(PDO $db, $nom, $parentId = null) {
    try {
        $sql = "SELECT id FROM modules WHERE nom = ? AND roles = 'beta' AND "
            . ($parentId === null ? "parent_id IS NULL" : "parent_id = ?") . " LIMIT 1";
        $st = $db->prepare($sql);
        $st->execute($parentId === null ? [$nom] : [$nom, (int) $parentId]);
        $v = $st->fetchColumn();
        return $v !== false ? (int) $v : 0;
    } catch (Throwable $e) { return 0; }
}
// Lien vers le CONTENU : si le module a UN seul sous-module de contenu, on ouvre
// directement ce sous-module (affichage direct, pas de liste intermédiaire).
function betaLien(PDO $db, $id) {
    if (!$id) { return 'gestion-beta.php'; }
    try {
        $self = $db->prepare("SELECT (pdf_path IS NOT NULL OR video_path IS NOT NULL OR contenu_ia IS NOT NULL) FROM modules WHERE id = ?");
        $self->execute([(int) $id]);
        if ((int) $self->fetchColumn()) { return 'module.php?id=' . (int) $id; }
        $st = $db->prepare("SELECT id FROM modules WHERE parent_id = ? AND (pdf_path IS NOT NULL OR video_path IS NOT NULL OR contenu_ia IS NOT NULL)");
        $st->execute([(int) $id]);
        $enfants = $st->fetchAll(PDO::FETCH_COLUMN);
        if (count($enfants) === 1) { return 'module.php?id=' . (int) $enfants[0]; }
        return 'module.php?id=' . (int) $id;
    } catch (Throwable $e) { return 'module.php?id=' . (int) $id; }
}

$idCaisse    = betaModId($db, 'Caisse', null);
$idFormation = betaModId($db, 'Formation Caisse', $idCaisse);
$idTechnique = betaModId($db, 'Module technique', $idCaisse);
$id2Sem      = betaModId($db, 'Mes 2 premières semaines en caisse', $idCaisse);

// Caisse = 3 modules ACTIFS. Les autres rayons = aperçu grisé.
$rayons = [
    ['icon' => '💳', 'href' => $idFormation ? betaLien($db, $idFormation) : 'gestion-beta.php', 'actif' => true,
     'titre' => t('Formation Caisse', 'Kassaopleiding'), 'desc' => t('Le parcours pour bien démarrer.', 'Het traject om goed te starten.')],
    ['icon' => '🔧', 'href' => $idTechnique ? betaLien($db, $idTechnique) : 'gestion-beta.php', 'actif' => true,
     'titre' => t('Module technique', 'Technische module'), 'desc' => t('La partie technique de la caisse.', 'Het technische deel van de kassa.')],
    ['icon' => '🗓️', 'href' => $id2Sem ? betaLien($db, $id2Sem) : 'gestion-beta.php', 'actif' => true,
     'titre' => t('Mes 2 premières semaines', 'Mijn eerste 2 weken'), 'desc' => t('Ce qui t\'attend au début.', 'Wat je aan het begin te wachten staat.')],
    ['icon' => '🌿', 'href' => null, 'actif' => false,
     'titre' => t('Green', 'Green'),             'desc' => t('Plantes & jardin.', 'Planten & tuin.')],
    ['icon' => '🖼️', 'href' => null, 'actif' => false,
     'titre' => t('Déco', 'Deco'),               'desc' => t('Décoration & intérieur.', 'Decoratie & interieur.')],
    ['icon' => '🐾', 'href' => null, 'actif' => false,
     'titre' => t('Animalerie', 'Dierenwinkel'), 'desc' => t('Bien-être animal.', 'Dierenwelzijn.')],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Magasin', 'Winkel') ?> (BETA) - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: url('background.jpg') no-repeat center center fixed; background-size: cover; margin: 0; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .header { text-align: center; padding: 34px 20px 6px; }
        .logo { max-width: 140px; margin-bottom: 12px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        h1 { color: #2d5a37; background: rgba(255,255,255,0.85); padding: 10px 26px; border-radius: 30px; font-size: 1.5rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1); display: inline-block; margin: 0; }
        .soon-note { color: #5a6b60; background: rgba(255,255,255,.7); border-radius: 12px; padding: 8px 16px; margin: 14px auto 0; max-width: 560px; font-size: .9rem; }
        .tiles-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 22px; width: 90%; max-width: 1000px; padding: 22px 0 40px; }
        .tile { background: rgba(255,255,255,0.96); border-radius: 20px; padding: 34px 26px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.1); transition: all .3s ease; display: flex; flex-direction: column; align-items: center; position: relative; }
        .tile.actif:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
        .tile.inactif { opacity: .5; filter: grayscale(.7); cursor: default; }
        .tile-icon { font-size: 3rem; margin-bottom: 12px; }
        .tile-title { font-size: 1.3rem; font-weight: 700; color: #2d5a37; margin-bottom: 6px; }
        .tile-desc { font-size: .92rem; color: #666; line-height: 1.4; }
        .badge-soon { position: absolute; top: -10px; right: -8px; background: #8a8f95; color: #fff; font-size: .72rem; font-weight: 800; padding: 4px 11px; border-radius: 20px; box-shadow: 0 3px 8px rgba(0,0,0,.18); }
        .back-link { margin: 6px 0 40px; color: #2d5a37; text-decoration: none; font-weight: bold; background: #fff; padding: 12px 25px; border-radius: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .back-link:hover { background: #2d5a37; color: #fff; }
        .lang-sw { position: fixed; top: 12px; right: 12px; z-index: 50; display: inline-flex; background: #fff; border: 2px solid #2d5a37; border-radius: 999px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .lang-sw a { padding: 7px 14px; font-weight: 700; font-size: .85rem; text-decoration: none; color: #2d5a37; }
        .lang-sw a.on { background: #2d5a37; color: #fff; }
        @media (max-width: 560px) { .tiles-container { grid-template-columns: 1fr; } h1 { font-size: 1.25rem; } }
    </style>
</head>
<body>
    <span class="lang-sw">
        <a href="?lang=fr" class="<?= currentLang() === 'fr' ? 'on' : '' ?>">FR</a>
        <a href="?lang=nl" class="<?= currentLang() === 'nl' ? 'on' : '' ?>">NL</a>
    </span>
    <div class="header">
        <img src="logo.png" alt="Famiflora" class="logo">
        <h1>🛒 <?= t('Le magasin', 'De winkel') ?></h1>
        <div class="soon-note"><?= t("Pour l'instant, seule la <b>Caisse</b> est ouverte (ses 3 modules). Les autres rayons arrivent bientôt.", "Voorlopig is enkel de <b>Kassa</b> open (haar 3 modules). De andere afdelingen komen binnenkort.") ?></div>
    </div>

    <div class="tiles-container">
        <?php foreach ($rayons as $r): ?>
            <?php if ($r['actif']): ?>
                <a href="<?= htmlspecialchars($r['href']) ?>" class="tile actif">
                    <span class="tile-icon"><?= $r['icon'] ?></span>
                    <div class="tile-title"><?= htmlspecialchars($r['titre']) ?></div>
                    <div class="tile-desc"><?= htmlspecialchars($r['desc']) ?></div>
                </a>
            <?php else: ?>
                <div class="tile inactif" aria-disabled="true">
                    <span class="badge-soon"><?= t('Bientôt', 'Binnenkort') ?></span>
                    <span class="tile-icon"><?= $r['icon'] ?></span>
                    <div class="tile-title"><?= htmlspecialchars($r['titre']) ?></div>
                    <div class="tile-desc"><?= htmlspecialchars($r['desc']) ?></div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <a href="beta.php" class="back-link">← <?= t("Retour à l'accueil", 'Terug naar het beginscherm') ?></a>
</body>
</html>
