<?php
// ============================================================
// FAMICARD — GESTION DES LIBELLÉS (administrateur).
//
// Permet d'ajouter des informations à la fiche collaborateur sans toucher au
// code ni à la table `utilisateurs` : on crée un libellé, on dit s'il est
// obligatoire, et il apparaît partout (carte, base, export).
//
// C'EST ICI, ET NULLE PART AILLEURS, que les tables de Famicard sont créées.
// Le site a fait le ménage une fois pour « retirer la DDL du chemin chaud » :
// un CREATE TABLE sur chaque affichage de page coûte une requête à chaque
// visiteur pour un besoin qui ne se présente qu'une fois.
// ============================================================
require_once __DIR__ . '/config.php';

famicardExigeConnexion($db);

if (!famicardEstAdmin()) {
    header('Location: index.php');
    exit();
}

$erreur = '';
$info   = (string) ($_GET['ok'] ?? '');

// Création des tables : ici seulement, et sans faire tomber la page si le
// compte MySQL n'a pas le droit de créer (on affiche alors la raison).
try {
    famicardAssureTables($db);
} catch (Exception $e) {
    $erreur = "Impossible de préparer les tables de Famicard : " . $e->getMessage();
}

$groupes = famicardGroupes();
$natures = [
    'service'   => 'Nécessaire au service',
    'personnel' => 'Donnée personnelle',
];
$visibilites = [
    'tous'  => 'Tout le monde',
    'soi'   => 'Le collaborateur et les admins',
    'admin' => 'Les admins seulement',
];

// ─────────────────────────────────────────────────────────────────────────────
// ACTIONS. Redirection après écriture (PRG) : sans ça, un rafraîchissement
// recrée le libellé qu'on vient d'ajouter.
// ─────────────────────────────────────────────────────────────────────────────
if ($erreur === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'creer') {
            $libelle = trim((string) ($_POST['libelle'] ?? ''));
            if ($libelle === '') {
                $erreur = "Le libellé ne peut pas être vide.";
            } else {
                $groupe  = isset($groupes[$_POST['groupe'] ?? '']) ? (string) $_POST['groupe'] : 'libre';
                $nature  = isset($natures[$_POST['nature'] ?? '']) ? (string) $_POST['nature'] : 'personnel';
                $visible = isset($visibilites[$_POST['visible'] ?? '']) ? (string) $_POST['visible'] : 'soi';

                $st = $db->prepare(
                    "INSERT INTO famicard_champs (libelle, libelle_nl, groupe, requis, nature, visible, ordre)
                     VALUES (?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(o.ordre), 0) + 1 FROM (SELECT ordre FROM famicard_champs) o))"
                );
                $st->execute([
                    $libelle,
                    trim((string) ($_POST['libelle_nl'] ?? '')),
                    $groupe,
                    (($_POST['requis'] ?? '') === '1') ? 1 : 0,
                    $nature,
                    $visible,
                ]);
                header('Location: admin_champs.php?ok=cree');
                exit();
            }
        } elseif ($action === 'requis') {
            // Bascule obligatoire / facultatif.
            $st = $db->prepare("UPDATE famicard_champs SET requis = 1 - requis WHERE id = ?");
            $st->execute([(int) ($_POST['id'] ?? 0)]);
            header('Location: admin_champs.php?ok=maj');
            exit();
        } elseif ($action === 'supprimer') {
            // Les valeurs partent avec (ON DELETE CASCADE) : on ne garde pas
            // des données personnelles rattachées à un libellé disparu.
            $st = $db->prepare("DELETE FROM famicard_champs WHERE id = ?");
            $st->execute([(int) ($_POST['id'] ?? 0)]);
            header('Location: admin_champs.php?ok=supprime');
            exit();
        }
    } catch (Exception $e) {
        $erreur = "Enregistrement impossible : " . $e->getMessage();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LECTURE
// ─────────────────────────────────────────────────────────────────────────────
$existants = [];
if ($erreur === '' || strpos($erreur, 'Enregistrement') === 0) {
    try {
        $existants = $db->query(
            "SELECT c.*, (SELECT COUNT(*) FROM famicard_valeurs v WHERE v.champ_id = c.id AND v.valeur <> '') AS remplis
             FROM famicard_champs c WHERE c.actif = 1 ORDER BY c.ordre ASC, c.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $existants = [];
    }
}

$socle = famicardChampsSocle();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Libellés de la fiche - Famicard</title>
<?php // Favicon du site principal : chemin absolu interdit ici, il serait réécrit
      // vers famicard/ sur le sous-domaine (voir famicardSiteUrl). ?>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 0 0 60px; color: #333; }
    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .85rem; }
    .wrap { max-width: 1000px; margin: 22px auto 0; padding: 0 16px; }
    .boite { background: #fff; border-radius: 16px; padding: 22px 24px; box-shadow: 0 6px 18px rgba(0,0,0,.07); margin-bottom: 22px; }
    h2 { font-size: .82rem; text-transform: uppercase; letter-spacing: .07em; color: #2d5a37; margin: 0 0 14px; }
    .champs-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; align-items: end; }
    label { display: block; font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; color: #2d5a37; font-weight: 700; margin-bottom: 5px; }
    input[type=text], select { width: 100%; box-sizing: border-box; font-family: inherit; font-size: .92rem; padding: 9px 12px; border: 1px solid #cfd8d2; border-radius: 10px; }
    .coche { display: flex; align-items: center; gap: 9px; font-size: .92rem; padding-bottom: 9px; }
    .coche input { width: 17px; height: 17px; accent-color: #2d5a37; }
    .bouton { border: 0; border-radius: 30px; padding: 11px 24px; font-family: inherit; font-weight: 700; font-size: .9rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-mini { background: #eef3ef; color: #2d5a37; padding: 6px 14px; font-size: .82rem; border-radius: 20px; }
    .bouton-danger { background: #fdecea; color: #c0392b; padding: 6px 14px; font-size: .82rem; border-radius: 20px; }
    table { border-collapse: collapse; width: 100%; font-size: .91rem; }
    th { background: #f5f8f6; text-align: left; padding: 11px 13px; font-size: .73rem; text-transform: uppercase; letter-spacing: .05em; color: #2d5a37; border-bottom: 2px solid #e3ebe6; }
    td { padding: 10px 13px; border-bottom: 1px solid #f0f4f1; vertical-align: middle; }
    .etiq { display: inline-block; border-radius: 999px; padding: 2px 11px; font-size: .76rem; font-weight: 700; }
    .etiq-oui { background: #e8f5e9; color: #2d5a37; }
    .etiq-non { background: #f2f2f2; color: #888; }
    .alerte { border-radius: 12px; padding: 13px 18px; margin-bottom: 18px; font-size: .92rem; }
    .alerte-ok { background: #e8f5e9; color: #1f5c2e; }
    .alerte-ko { background: #fdecea; color: #a5281b; }
    .aide { font-size: .87rem; color: #666; line-height: 1.6; background: #f5f8f6; border-radius: 10px; padding: 12px 16px; margin-top: 16px; }
    .socle { color: #777; font-size: .88rem; line-height: 1.7; }
</style>
</head>
<body>

<div class="bandeau">
    <h1>Libellés de la fiche collaborateur</h1>
    <div>
        <a class="pill" href="admin.php">Base des collaborateurs</a>
        <a class="pill" href="index.php">Ma carte</a>
    </div>
</div>

<div class="wrap">

    <?php if ($erreur !== ''): ?>
        <div class="alerte alerte-ko"><?= e($erreur) ?></div>
    <?php elseif ($info !== ''): ?>
        <div class="alerte alerte-ok">
            <?= $info === 'cree' ? 'Libellé ajouté.' : ($info === 'supprime' ? 'Libellé supprimé, avec les réponses qui y étaient rattachées.' : 'Modification enregistrée.') ?>
        </div>
    <?php endif; ?>

    <div class="boite">
        <h2>Ajouter un libellé</h2>
        <form method="post" class="champs-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="creer">
            <div>
                <label for="libelle">Libellé (FR)</label>
                <input type="text" id="libelle" name="libelle" placeholder="Ex : Taille de vêtement" required>
            </div>
            <div>
                <label for="libelle_nl">Libellé (NL)</label>
                <input type="text" id="libelle_nl" name="libelle_nl" placeholder="Ex : Kledingmaat">
            </div>
            <div>
                <label for="groupe">Section</label>
                <select id="groupe" name="groupe">
                    <?php foreach ($groupes as $cle => $g): ?>
                        <option value="<?= e($cle) ?>"<?= $cle === 'libre' ? ' selected' : '' ?>><?= e($g['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="nature">Nature</label>
                <select id="nature" name="nature">
                    <?php foreach ($natures as $cle => $lib): ?>
                        <option value="<?= e($cle) ?>"><?= e($lib) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="visible">Qui peut le voir</label>
                <select id="visible" name="visible">
                    <?php foreach ($visibilites as $cle => $lib): ?>
                        <option value="<?= e($cle) ?>"<?= $cle === 'soi' ? ' selected' : '' ?>><?= e($lib) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="coche">
                <input type="checkbox" id="requis" name="requis" value="1">
                <label for="requis" style="margin:0;text-transform:none;letter-spacing:0;font-size:.92rem;">Obligatoire</label>
            </div>
            <div><button class="bouton bouton-plein" type="submit">Ajouter</button></div>
        </form>

        <p class="aide">
            Un libellé ajouté ici apparaît sur la carte, dans la base et dans les colonnes proposées à l'export.
            Il n'est <b>jamais</b> imprimé sur le badge : le badge ne porte que le prénom et la mention.
            « Obligatoire » signale une fiche incomplète — ça ne bloque aucun accès.
        </p>
    </div>

    <div class="boite">
        <h2>Libellés existants</h2>
        <?php if (!$existants): ?>
            <p class="socle">Aucun libellé personnalisé pour l'instant.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Libellé</th><th>NL</th><th>Section</th><th>Qui le voit</th>
                        <th>Obligatoire</th><th>Rempli</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($existants as $c): ?>
                    <tr>
                        <td><b><?= e($c['libelle']) ?></b></td>
                        <td><?= $c['libelle_nl'] !== '' ? e($c['libelle_nl']) : '<span style="color:#bbb">—</span>' ?></td>
                        <td><?= e($groupes[$c['groupe']]['libelle'] ?? $c['groupe']) ?></td>
                        <td><?= e($visibilites[$c['visible']] ?? $c['visible']) ?></td>
                        <td>
                            <span class="etiq <?= $c['requis'] ? 'etiq-oui' : 'etiq-non' ?>">
                                <?= $c['requis'] ? 'Oui' : 'Non' ?>
                            </span>
                        </td>
                        <td><?= (int) $c['remplis'] ?> fiche<?= ((int) $c['remplis']) > 1 ? 's' : '' ?></td>
                        <td style="white-space:nowrap;">
                            <form method="post" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="requis">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="bouton bouton-mini" type="submit"><?= $c['requis'] ? 'Rendre facultatif' : 'Rendre obligatoire' ?></button>
                            </form>
                            <form method="post" style="display:inline;"
                                  onsubmit="return confirm('Supprimer « <?= e($c['libelle']) ?> » ? Les <?= (int) $c['remplis'] ?> réponse(s) déjà saisies seront effacées.');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="bouton bouton-danger" type="submit">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="boite">
        <h2>Champs de base (non modifiables ici)</h2>
        <p class="socle">
            Ils viennent de la fiche que FamiFormation et FamiJob utilisent déjà, et portent leurs libellés :
            <?php
            $noms = [];
            foreach ($socle as $champ) {
                $noms[] = $champ['libelle'] . (!empty($champ['requis']) ? ' (obligatoire)' : '');
            }
            echo e(implode(' · ', $noms));
            ?>.
            <br><br>
            Ils ne se modifient pas depuis Famicard : ce sont les mêmes colonnes que celles des deux autres
            plateformes, les renommer ici ferait diverger le vocabulaire d'un écran à l'autre.
        </p>
    </div>

</div>
</body>
</html>
