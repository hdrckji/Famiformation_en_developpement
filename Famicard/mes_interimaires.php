<?php
// ============================================================
// mes_interimaires.php — CE QU'UNE AGENCE VOIT DE SES GENS.
//
// Le premier écran de Famicard réservé à un compte qui n'est PAS un
// collaborateur. Une agence y consulte la liste des personnes qu'elle nous
// envoie, et rien de plus : nom, prénom, et « étudiant » ou « intérimaire ».
//
// ⚠️ TROIS COLONNES, C'EST TOUT (décision de Jimmy). Pas d'email, pas de
// téléphone, pas de photo, pas de secteur, pas de lieu de travail. Une agence
// n'a aucune raison de connaître l'adresse personnelle ou le rayon de quelqu'un
// qu'elle nous a envoyé. La restriction est dans la REQUÊTE, pas dans ce
// gabarit : ce qui n'est pas lu ne peut pas fuiter (voir includes/agence.php).
//
// ⚠️ LE PÉRIMÈTRE VIENT DU COMPTE CONNECTÉ, jamais de l'URL. Sans ça, une
// agence lirait la liste d'une autre en changeant un mot dans la barre
// d'adresse — et personne ne s'en apercevrait.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/agence.php';

$moi = famicardExigeConnexion($db);
$moiId = (int) $moi['id'];

// Réservé aux comptes agence. Un administrateur qui veut la même liste l'a
// déjà, en mieux, dans « Mes collaborateurs » filtré par agence — deux écrans
// pour la même question finissent par ne plus dire la même chose.
if (!famicardEstCompteAgence($moi['role'] ?? '')) {
    header('Location: index.php');
    exit();
}

$agence = famicardAgenceDuCompte($db, $moiId);
$gens = famicardPersonnesDeLAgence($db, $agence);

$etudiants = 0;
foreach ($gens as $g) {
    if ($g['type'] === 'Étudiant') { $etudiants++; }
}
$interimaires = count($gens) - $etudiants;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes intérimaires - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php // Le cadre commun à toutes les pages (fond, largeur, respiration).
      // Chargé AVANT le <style> de la page, qui garde donc le dernier mot. ?>
<link rel="stylesheet" href="assets/famicard.css">
<style>
    :root { --famicard-fond: url('<?= e(famicardSiteUrl('background.jpg')) ?>'); }
</style>
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; margin: 0; padding: 0 0 50px; color: #333; }
    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .85rem; }
    .wrap { max-width: 780px; margin: 22px auto 0; padding: 0 16px; }

    .compte { margin: 0 2px 14px; font-size: .95rem; color: #555; }
    .compte b { color: #2d5a37; }
    .note { background: #fff; border-left: 5px solid #2d5a37; border-radius: 12px; padding: 13px 17px; margin-bottom: 18px; font-size: .89rem; line-height: 1.6; color: #5a6b60; box-shadow: 0 4px 12px rgba(0,0,0,.05); }

    .tableau-boite { background: #fff; border-radius: 16px; box-shadow: 0 6px 18px rgba(0,0,0,.07); overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; font-size: .93rem; }
    th { background: #f5f8f6; text-align: left; padding: 12px 16px; font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; color: #2d5a37; border-bottom: 2px solid #e3ebe6; }
    td { padding: 11px 16px; border-bottom: 1px solid #f0f4f1; }
    tr:last-child td { border-bottom: 0; }
    tr:hover td { background: #fafcfb; }
    .etiquette { border-radius: 999px; padding: 3px 12px; font-size: .76rem; font-weight: 800; white-space: nowrap; }
    .etiquette.etudiant { background: #e8eefb; color: #2f4f8f; }
    .etiquette.interimaire { background: #fff3cd; color: #856404; }
    .rien { padding: 40px; text-align: center; color: #888; }
</style>
</head>
<body class="voile">

<div class="bandeau">
    <h1>👥 Mes intérimaires<?= $agence !== '' ? ' — ' . e($agence) : '' ?></h1>
    <div>
        <a class="pill" href="fiche.php">Ma fiche</a>
        <a class="pill" href="index.php">&larr; Accueil</a>
    </div>
</div>

<div class="wrap">

    <?php if ($agence === ''): ?>
        <div class="note">
            Ce compte n'est rattaché à aucune agence : il n'y a donc personne à afficher.
            Signale-le à l'administration de Famiflora.
        </div>
    <?php else: ?>
        <p class="compte">
            <b><?= count($gens) ?></b> personne<?= count($gens) > 1 ? 's' : '' ?> chez Famiflora via
            <b><?= e($agence) ?></b><?php if ($gens): ?> —
            <?= (int) $etudiants ?> étudiant<?= $etudiants > 1 ? 's' : '' ?>,
            <?= (int) $interimaires ?> intérimaire<?= $interimaires > 1 ? 's' : '' ?><?php endif; ?>.
        </p>

        <div class="note">
            Cette liste ne montre que <b>le nom, le prénom et le type</b> de chaque personne.
            C'est volontaire : le reste de leur fiche — coordonnées, rayon, horaires — ne nous
            appartient pas plus qu'à vous de le partager ici.
            Pour les <b>horaires et les disponibilités</b>, c'est FamiJob.
        </div>

        <div class="tableau-boite">
            <?php if (!$gens): ?>
                <div class="rien">Personne n'est rattaché à cette agence pour le moment.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Nom</th><th>Prénom</th><th>Type</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gens as $g): ?>
                        <tr>
                            <td><?= e($g['nom']) !== '' ? e($g['nom']) : '—' ?></td>
                            <td><?= e($g['prenom']) !== '' ? e($g['prenom']) : '—' ?></td>
                            <td>
                                <span class="etiquette <?= $g['type'] === 'Étudiant' ? 'etudiant' : 'interimaire' ?>">
                                    <?= e($g['type']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
