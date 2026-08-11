<?php
// ============================================================
// FAMICARD — EXPORT EXCEL DE LA BASE DES COLLABORATEURS.
//
// Deux temps dans un seul fichier :
//   • sans ?go=1  → l'écran de choix des colonnes (toute la fiche est proposée) ;
//   • avec ?go=1  → le fichier .xlsx est produit et envoyé.
//
// LES FILTRES SONT CEUX DE admin.php, repris tels quels dans l'URL. C'est ce
// qui garantit que le fichier contient exactement ce qui était à l'écran :
// sans ça, on retombe sur le classique « le fichier ne dit pas la même chose
// que la liste », et personne ne sait laquelle des deux a raison.
//
// Les colonnes proposées sont celles que l'administrateur a le droit de voir
// (famicardPeutVoir) : un champ réservé ne peut pas sortir par l'export.
// ============================================================
require_once __DIR__ . '/config.php';

famicardExigeConnexion($db);

if (!famicardEstAdmin()) {
    header('Location: index.php');
    exit();
}

$champs   = famicardChamps($db);
$magasins = famicardMagasins($db);

// ─────────────────────────────────────────────────────────────────────────────
// FILTRES — mêmes règles qu'à l'écran : liste connue ou paramètre lié.
// ─────────────────────────────────────────────────────────────────────────────
$roles = [];
try {
    $roles = $db->query("SELECT DISTINCT role FROM utilisateurs WHERE role IS NOT NULL AND role <> '' ORDER BY role")
                ->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $roles = [];
}

$fRole  = (string) ($_GET['role'] ?? '');
$fRole  = in_array($fRole, $roles, true) ? $fRole : '';
$fSite  = (string) ($_GET['site'] ?? '');
$fSite  = isset($magasins[(int) $fSite]) ? (string) (int) $fSite : '';
$fTexte = trim((string) ($_GET['q'] ?? ''));

$conditions = [];
$params     = [];
if ($fRole !== '')  { $conditions[] = 'role = ?';    $params[] = $fRole; }
if ($fSite !== '')  { $conditions[] = 'site_id = ?'; $params[] = (int) $fSite; }
if ($fTexte !== '') {
    $conditions[] = '(nom LIKE ? OR prenom LIKE ? OR identifiant LIKE ? OR email LIKE ?)';
    $motif = '%' . $fTexte . '%';
    array_push($params, $motif, $motif, $motif, $motif);
}
$where = $conditions ? (' WHERE ' . implode(' AND ', $conditions)) : '';

// ─────────────────────────────────────────────────────────────────────────────
// COLONNES CHOISIES
// ─────────────────────────────────────────────────────────────────────────────
$proposables = [];
foreach ($champs as $cle => $champ) {
    if ($cle === 'photo_profil') {
        continue; // une image n'a pas de sens dans une cellule
    }
    if (!famicardPeutVoir($champ, true, false)) {
        continue;
    }
    $proposables[$cle] = $champ;
}

$defaut = ['nom', 'prenom', 'email', 'role', 'site_id'];

$choisies = (array) ($_GET['cols'] ?? []);
$choisies = array_values(array_intersect(array_map('strval', $choisies), array_keys($proposables)));

$genere = (isset($_GET['go']) && $_GET['go'] === '1' && $choisies);

if ($genere) {
    // ── LECTURE DES LIGNES ───────────────────────────────────────────────────
    // On ne lit que les colonnes nécessaires : une requête qui ramène tout,
    // c'est une fuite en puissance le jour où on ajoute un champ sensible.
    $colonnesSql = ['id'];
    foreach ($choisies as $cle) {
        // Secteur et département portent une PSEUDO-colonne (student_department_links,
        // pas `utilisateurs`) : dans le SELECT, elle ferait tomber la requête.
        // Ils sont ajoutés plus bas, par jointure séparée.
        if (($proposables[$cle]['saisie'] ?? '') === 'rattachement') {
            continue;
        }
        if (!empty($proposables[$cle]['colonne'])) {
            $colonnesSql[] = $proposables[$cle]['colonne'];
        }
    }
    $colonnesSql = array_values(array_unique($colonnesSql));
    $listeSql = '`' . implode('`, `', $colonnesSql) . '`';

    $st = $db->prepare("SELECT $listeSql FROM utilisateurs" . $where . " ORDER BY nom ASC, prenom ASC");
    $st->execute($params);
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);

    // Secteur et département, en une requête pour toute la liste.
    if ($lignes) {
        $rattachements = famicardRattachements($db, array_map(static function ($l) {
            return (int) $l['id'];
        }, $lignes));
        foreach ($lignes as $i => $l) {
            $lignes[$i] = famicardAjouteRattachement($l, $rattachements);
        }
    }

    // Les champs libres ne sont pas dans `utilisateurs` : une seule requête
    // pour tout le monde, plutôt qu'une par collaborateur.
    $besoinLibres = false;
    foreach ($choisies as $cle) {
        if (!empty($proposables[$cle]['champ_id'])) { $besoinLibres = true; break; }
    }
    $libresParUser = [];
    if ($besoinLibres && $lignes) {
        try {
            $ids = array_map(static function ($l) { return (int) $l['id']; }, $lignes);
            $trous = implode(',', array_fill(0, count($ids), '?'));
            $sv = $db->prepare("SELECT user_id, champ_id, valeur FROM famicard_valeurs WHERE user_id IN ($trous)");
            $sv->execute($ids);
            foreach ($sv->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $libresParUser[(int) $v['user_id']][(int) $v['champ_id']] = (string) $v['valeur'];
            }
        } catch (Exception $e) {
            $libresParUser = [];
        }
    }

    // ── CONSTRUCTION DU TABLEAU ──────────────────────────────────────────────
    $entetes = [];
    foreach ($choisies as $cle) {
        $entetes[] = $proposables[$cle]['libelle'];
    }

    $corps = [];
    foreach ($lignes as $ligne) {
        $libres = $libresParUser[(int) $ligne['id']] ?? [];
        $rangee = [];
        foreach ($choisies as $cle) {
            $rangee[] = famicardValeurAffichee($cle, $proposables[$cle], $ligne, $magasins, $libres);
        }
        $corps[] = $rangee;
    }

    // ── FICHIER ──────────────────────────────────────────────────────────────
    // PhpSpreadsheet vit dans le vendor/ de FamiFormation. Selon la disposition
    // (conteneur ou dépôt) il n'est pas au même endroit : on essaie les trois.
    foreach ([
        dirname(__DIR__) . '/vendor/autoload.php',
        dirname(__DIR__) . '/Famiformation/vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
    ] as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            break;
        }
    }

    $nomFichier = 'collaborateurs_' . date('Y-m-d');
    $donnees = null;

    if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        try {
            $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $f = $ss->getActiveSheet();
            $f->setTitle('Collaborateurs');

            $f->fromArray($entetes, null, 'A1');
            $derniere = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($entetes));

            $style = $f->getStyle('A1:' . $derniere . '1');
            $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $style->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setARGB('FF2D5A37');

            if ($corps) {
                $f->fromArray($corps, null, 'A2');
            }

            // Volet figé : sur 400 lignes, sans ça on ne sait plus quelle
            // colonne on lit dès le premier défilement.
            $f->freezePane('A2');
            $f->setAutoFilter('A1:' . $derniere . max(1, count($corps) + 1));

            for ($i = 1; $i <= count($entetes); $i++) {
                $f->getColumnDimensionByColumn($i)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
            ob_start();
            $writer->save('php://output');
            $donnees = ob_get_clean();
        } catch (\Throwable $e) {
            $donnees = null;
        }
    }

    // ⚠️ config.php ouvre un tampon de sortie (injection du thème). Sans ce
    // vidage, le HTML du thème se colle devant le fichier et Excel refuse de
    // l'ouvrir. C'est la même précaution que Famijob/export_matching.php.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (is_string($donnees) && $donnees !== '') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nomFichier . '.xlsx"');
        header('Content-Length: ' . strlen($donnees));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $donnees;
        exit();
    }

    // Repli CSV : mieux vaut un fichier ouvrable qu'une page d'erreur.
    // Le BOM UTF-8 est indispensable, sinon Excel affiche « Ã© » partout.
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomFichier . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";
    $sortie = fopen('php://output', 'w');
    fputcsv($sortie, $entetes, ';');
    foreach ($corps as $rangee) {
        fputcsv($sortie, $rangee, ';');
    }
    fclose($sortie);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉCRAN DE CHOIX DES COLONNES
// ─────────────────────────────────────────────────────────────────────────────
$stc = $db->prepare("SELECT COUNT(*) FROM utilisateurs" . $where);
$stc->execute($params);
$combien = (int) $stc->fetchColumn();

$preselection = $choisies ?: $defaut;
$groupes = famicardGroupes();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Export Excel - Famicard</title>
<?php // Favicon du site principal : chemin absolu interdit ici, il serait réécrit
      // vers famicard/ sur le sous-domaine (voir famicardSiteUrl). ?>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 0 0 60px; color: #333; }
    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .85rem; }
    .wrap { max-width: 900px; margin: 22px auto 0; padding: 0 16px; }
    .boite { background: #fff; border-radius: 16px; padding: 22px 24px; box-shadow: 0 6px 18px rgba(0,0,0,.07); }
    .perimetre { background: #f5f8f6; border-radius: 12px; padding: 14px 18px; font-size: .93rem; margin-bottom: 22px; line-height: 1.6; }
    .perimetre b { color: #2d5a37; }
    h2 { font-size: .8rem; text-transform: uppercase; letter-spacing: .07em; color: #2d5a37; margin: 22px 0 10px; }
    h2:first-of-type { margin-top: 0; }
    .cases { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 8px; }
    .case { display: flex; align-items: center; gap: 9px; padding: 9px 12px; border: 1px solid #e3ebe6; border-radius: 10px; font-size: .92rem; cursor: pointer; }
    .case:hover { background: #f7faf8; }
    .case input { width: 17px; height: 17px; accent-color: #2d5a37; }
    .barre { margin-top: 26px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .bouton { border: 0; border-radius: 30px; padding: 12px 26px; font-family: inherit; font-weight: 700; font-size: .92rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-vide { background: #eef3ef; color: #2d5a37; }
    .lien-mini { background: none; border: 0; color: #2d5a37; font-family: inherit; font-size: .86rem; text-decoration: underline; cursor: pointer; padding: 0; }
</style>
</head>
<body>

<div class="bandeau">
    <h1>Export Excel</h1>
    <div>
        <a class="pill" href="admin.php">Retour à la base</a>
    </div>
</div>

<div class="wrap">
    <div class="boite">

        <div class="perimetre">
            <b><?= $combien ?></b> collaborateur<?= $combien > 1 ? 's' : '' ?> dans l'export.
            <?php if ($fRole !== '' || $fSite !== '' || $fTexte !== ''): ?>
                <br>Filtres repris de la liste :
                <?php
                $morceaux = [];
                if ($fRole !== '')  { $morceaux[] = 'profil « ' . famicardLibelleRole($fRole) . ' »'; }
                if ($fSite !== '')  { $morceaux[] = 'lieu « ' . ($magasins[(int) $fSite] ?? $fSite) . ' »'; }
                if ($fTexte !== '') { $morceaux[] = 'recherche « ' . $fTexte . ' »'; }
                echo e(implode(', ', $morceaux));
                ?>
            <?php else: ?>
                <br>Aucun filtre : c'est <b>toute</b> la base.
            <?php endif; ?>
        </div>

        <form method="get">
            <input type="hidden" name="go" value="1">
            <input type="hidden" name="role" value="<?= e($fRole) ?>">
            <input type="hidden" name="site" value="<?= e($fSite) ?>">
            <input type="hidden" name="q" value="<?= e($fTexte) ?>">

            <?php foreach ($groupes as $cleGroupe => $groupe): ?>
                <?php
                $duGroupe = array_filter($proposables, static function ($c) use ($cleGroupe) {
                    return ($c['groupe'] ?? '') === $cleGroupe;
                });
                if (!$duGroupe) {
                    continue;
                }
                ?>
                <h2><?= e($groupe['libelle']) ?></h2>
                <div class="cases">
                    <?php foreach ($duGroupe as $cle => $champ): ?>
                        <label class="case">
                            <input type="checkbox" name="cols[]" value="<?= e($cle) ?>"
                                   <?= in_array($cle, $preselection, true) ? 'checked' : '' ?>>
                            <span><?= e($champ['libelle']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="barre">
                <button class="bouton bouton-plein" type="submit">📊 Télécharger le fichier</button>
                <a class="bouton bouton-vide" href="admin.php">Annuler</a>
                <button class="lien-mini" type="button" onclick="tout(true)">Tout cocher</button>
                <button class="lien-mini" type="button" onclick="tout(false)">Tout décocher</button>
            </div>
        </form>

    </div>
</div>

<script>
function tout(etat) {
    document.querySelectorAll('input[name="cols[]"]').forEach(function (c) { c.checked = etat; });
}
</script>
</body>
</html>
