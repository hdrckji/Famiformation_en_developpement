<?php
// ============================================================
// mon_equipe.php — LES GENS DE MON SECTEUR.
//
// L'écran qui manquait à un collaborateur non-administrateur : il avait sa
// fiche et sa figurine, mais rien qui parle des AUTRES. Or c'est la première
// question qu'on se pose en arrivant — « qui travaille avec moi ? ».
//
// ── CE QU'IL MONTRE, ET QUI EN DÉCIDE ───────────────────────────────────────
// Pas cet écran : le MODÈLE. Chaque champ passe par famicardPeutVoir() avec
// « je ne suis ni administrateur, ni sur ma propre fiche » — exactement la
// situation d'un collègue. Ce qui est marqué « soi » (email, ville, date
// d'anniversaire) ne sort donc pas, sans que cette page ait à le savoir.
//
// Le jour où un champ change de règle, cet écran suit tout seul. C'est
// précisément pourquoi la règle voyage avec le champ et non avec la page.
//
// ⚠️ LE PÉRIMÈTRE VIENT DU COMPTE, jamais de l'URL. Un secteur passé en
// paramètre laisserait parcourir l'organisation entière en changeant un
// chiffre — voir famicardEquipeDe().
//
// ⚠️ ON MONTRE LE SECTEUR ENTIER, pas seulement son rayon. « Mon équipe », ce
// sont les gens qu'on croise, pas les trois personnes du meuble d'à côté. Le
// tri par département est offert ensuite, à l'écran, pour qui cherche
// quelqu'un de précis.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/agence.php';
require_once __DIR__ . '/includes/photo.php';

$moi = famicardExigeConnexion($db);
$moiId = (int) $moi['id'];

// Une agence n'a pas d'équipe chez nous : elle a ses intérimaires, et c'est un
// autre écran (mes_interimaires.php).
if (famicardEstCompteAgence($moi['role'] ?? '')) {
    header('Location: mes_interimaires.php');
    exit();
}

$equipe = famicardEquipeDe($db, $moiId);
$magasins = famicardMagasins($db);
$champs = famicardChamps($db);

// Ce qu'un COLLÈGUE a le droit de voir : ni admin, ni sur sa propre fiche.
$montreLieu = isset($champs['site_id']) && famicardPeutVoir($champs['site_id'], false, false);
$montreRole = isset($champs['role']) && famicardPeutVoir($champs['role'], false, false);
$montrePhoto = isset($champs['photo_profil']) && famicardPeutVoir($champs['photo_profil'], false, false);

// Les départements présents dans le secteur, pour le tri. Construits depuis ce
// qu'on affiche réellement : proposer un département vide serait proposer un
// filtre qui ne rend rien.
$departements = [];
foreach ($equipe['gens'] as $g) {
    $nom = trim((string) ($g['departement_nom'] ?? ''));
    $cle = $nom !== '' ? $nom : '—';
    $departements[$cle] = ($departements[$cle] ?? 0) + 1;
}
ksort($departements);

$fDep = (string) ($_GET['dep'] ?? '');
if ($fDep !== '' && !isset($departements[$fDep])) {
    $fDep = '';
}

$gens = $equipe['gens'];
if ($fDep !== '') {
    $gens = array_values(array_filter($gens, static function ($g) use ($fDep) {
        $nom = trim((string) ($g['departement_nom'] ?? ''));
        return ($nom !== '' ? $nom : '—') === $fDep;
    }));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon équipe - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php // Le cadre commun à toutes les pages (fond, largeur, respiration).
      // Chargé AVANT le <style> de la page, qui garde donc le dernier mot. ?>
<link rel="stylesheet" href="assets/famicard.css">
<style>
    :root { --famicard-fond: url('<?= e(famicardSiteUrl('background.jpg')) ?>'); }
</style>
<style>
    body { font-family: 'Open Sans', sans-serif; margin: 0; padding: 0 0 50px; color: #333; }
    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .85rem; }
    .wrap { max-width: 980px; margin: 22px auto 0; padding: 0 16px; }

    .compte { margin: 0 2px 14px; font-size: .95rem; color: #4a5b50; }
    .compte b { color: #2d5a37; }
    .note { background: #fff; border-left: 5px solid #2d5a37; border-radius: 12px; padding: 14px 18px; margin-bottom: 18px; font-size: .9rem; line-height: 1.6; color: #5a6b60; box-shadow: 0 4px 12px rgba(0,0,0,.05); }

    .onglets { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
    .onglet { background: rgba(255,255,255,.94); border: 1px solid #dbe7de; border-radius: 999px; padding: 7px 15px; text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .84rem; }
    .onglet:hover { border-color: #2d5a37; }
    .onglet.actif { background: #2d5a37; color: #fff; border-color: #2d5a37; }
    .onglet .n { opacity: .7; font-weight: 600; }

    .gens { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; }
    .personne { background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 6px 18px rgba(20,50,32,.08); display: flex; gap: 13px; align-items: center; min-width: 0; }
    .visage { width: 54px; height: 54px; border-radius: 50%; object-fit: cover; border: 3px solid #e8f5e9; background: #e8f5e9; flex: 0 0 auto; }
    .visage-vide { display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #2d5a37; }
    .qui { min-width: 0; }
    .qui .nom { font-weight: 800; color: #244230; line-height: 1.25; overflow-wrap: anywhere; }
    .qui .sous { color: #6a7d72; font-size: .82rem; margin-top: 3px; }
    .qui .dep { color: #2d5a37; font-size: .78rem; font-weight: 700; margin-top: 4px; }
    .moi-meme { border: 2px solid #2d5a37; }

    .rien { background: #fff; border-radius: 16px; padding: 36px; text-align: center; color: #7a8a80; line-height: 1.6; box-shadow: 0 6px 18px rgba(20,50,32,.08); }
</style>
</head>
<body class="voile">

<div class="bandeau">
    <h1>👥 Mon équipe<?= $equipe['secteur_nom'] !== '' ? ' — ' . e($equipe['secteur_nom']) : '' ?></h1>
    <div>
        <a class="pill" href="fiche.php">Ma fiche</a>
        <a class="pill" href="index.php">&larr; Accueil</a>
    </div>
</div>

<div class="wrap">

    <?php if ($equipe['secteur_id'] === 0): ?>
        <?php // Sans rattachement, on ne devine pas : montrer « tout le monde »
              // serait ouvrir l'organisation entière à quelqu'un qui n'a rien
              // demandé, et montrer une page vide sans explication laisserait
              // croire à une panne. ?>
        <div class="rien">
            <div style="font-size:2rem;margin-bottom:10px;">🧭</div>
            <b>Ton secteur n'est pas encore renseigné.</b><br>
            C'est lui qui dit avec qui tu travailles. Tu peux l'indiquer toi-même sur
            <a href="modifier.php">ta fiche</a> — un administrateur le confirmera ensuite.
        </div>

    <?php elseif (!$equipe['gens']): ?>
        <div class="rien">
            Personne d'autre n'est encore rattaché au secteur <b><?= e($equipe['secteur_nom']) ?></b>.
        </div>

    <?php else: ?>
        <p class="compte">
            <b><?= count($equipe['gens']) ?></b> personne<?= count($equipe['gens']) > 1 ? 's' : '' ?>
            dans le secteur <b><?= e($equipe['secteur_nom']) ?></b><?php if (count($departements) > 1): ?>,
            réparties sur <?= count($departements) ?> départements<?php endif; ?>.
        </p>

        <?php if (count($departements) > 1): ?>
            <div class="onglets">
                <a class="onglet <?= $fDep === '' ? 'actif' : '' ?>" href="mon_equipe.php">
                    Tout le secteur <span class="n">(<?= count($equipe['gens']) ?>)</span>
                </a>
                <?php foreach ($departements as $nom => $n): ?>
                    <a class="onglet <?= $fDep === $nom ? 'actif' : '' ?>"
                       href="mon_equipe.php?dep=<?= urlencode($nom) ?>">
                        <?= e($nom === '—' ? 'Sans département' : $nom) ?> <span class="n">(<?= (int) $n ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="gens">
            <?php foreach ($gens as $g): ?>
                <?php
                    $id = (int) $g['id'];
                    $nomComplet = trim(((string) $g['prenom']) . ' ' . ((string) $g['nom']));
                    if ($nomComplet === '') { $nomComplet = '—'; }
                    $photo = trim((string) ($g['photo_profil'] ?? ''));
                    $dep = trim((string) ($g['departement_nom'] ?? ''));
                ?>
                <div class="personne<?= $id === $moiId ? ' moi-meme' : '' ?>">
                    <?php if ($montrePhoto && $photo !== ''): ?>
                        <img class="visage" src="<?= e(famicardPhotoUrl($id, $photo)) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <div class="visage visage-vide">👤</div>
                    <?php endif; ?>
                    <div class="qui">
                        <div class="nom"><?= e($nomComplet) ?><?= $id === $moiId ? ' (toi)' : '' ?></div>
                        <?php if ($montreRole): ?>
                            <div class="sous"><?= e(famicardLibelleRole($g['role'])) ?></div>
                        <?php endif; ?>
                        <?php if ($dep !== ''): ?>
                            <div class="dep"><?= e($dep) ?></div>
                        <?php elseif ($montreLieu && !empty($g['site_id'])): ?>
                            <div class="dep"><?= e($magasins[(int) $g['site_id']] ?? '') ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="note" style="margin-top:20px;">
            Cette page montre <b>le nom, la photo, le profil et le rayon</b> de chacun — rien d'autre.
            Les coordonnées personnelles ne sont visibles que par la personne elle-même et par
            l'administration.
        </div>
    <?php endif; ?>

</div>
</body>
</html>
