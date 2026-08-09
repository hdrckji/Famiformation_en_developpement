<?php
// ============================================================
// FAMICARD — LA BASE DES COLLABORATEURS (vue administrateur).
//
// Consultation seule : on liste, on filtre, on compte, on imprime un badge,
// on exporte. La MODIFICATION reste dans admin_user.php, qui la gère déjà ;
// deux façons de changer la même ligne finissent toujours par diverger.
//
// Les filtres de cet écran sont AUSSI ceux de l'export : export.php les relit
// dans l'URL, donc le fichier contient exactement ce qui est à l'écran. C'est
// ce qui évite le classique « le fichier ne dit pas la même chose que la liste ».
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
// FILTRES. Tout ce qui vient de l'URL est contraint à une liste connue ou
// passé en paramètre lié : rien n'est concaténé dans le SQL.
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

// Les filtres, reconduits tels quels vers l'export.
$filtresUrl = http_build_query(array_filter([
    'role' => $fRole,
    'site' => $fSite,
    'q'    => $fTexte,
], static function ($v) { return $v !== ''; }));

// ─────────────────────────────────────────────────────────────────────────────
// LECTURE
// ─────────────────────────────────────────────────────────────────────────────
// Colonnes affichées : celles que l'admin a le droit de voir, sans la photo
// (illisible en tableau). On construit la requête à partir de cette liste, donc
// elle ne peut pas porter sur une colonne qui n'existe pas.
$colonnesTableau = [];
foreach ($champs as $cle => $champ) {
    if ($cle === 'photo_profil') {
        continue;
    }
    if (!famicardPeutVoir($champ, true, false)) {
        continue;
    }
    $colonnesTableau[$cle] = $champ;
}

$colonnesSql = ['id'];
foreach ($colonnesTableau as $champ) {
    if (!empty($champ['colonne'])) {
        $colonnesSql[] = $champ['colonne'];
    }
}
// Le prénom sert au badge même s'il n'est pas dans les colonnes retenues.
$colonnesSql[] = 'prenom';
$colonnesSql = array_values(array_unique($colonnesSql));
$listeSql = '`' . implode('`, `', $colonnesSql) . '`';

$st = $db->prepare("SELECT $listeSql FROM utilisateurs" . $where . " ORDER BY nom ASC, prenom ASC");
$st->execute($params);
$lignes = $st->fetchAll(PDO::FETCH_ASSOC);

// Valeurs des champs libres : une seule requête pour toute la page, pas une
// par collaborateur (sur 400 lignes, la différence se voit).
$libresParUser = [];
$aDesChampsLibres = false;
foreach ($colonnesTableau as $champ) {
    if (!empty($champ['champ_id'])) { $aDesChampsLibres = true; break; }
}
if ($aDesChampsLibres && $lignes) {
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Base des collaborateurs - Famicard</title>
<?php // Favicon du site principal : chemin absolu interdit ici, il serait réécrit
      // vers famicard/ sur le sous-domaine (voir famicardSiteUrl). ?>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 0 0 50px; color: #333; }
    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .85rem; }
    .wrap { max-width: 1200px; margin: 22px auto 0; padding: 0 16px; }

    .filtres { background: #fff; border-radius: 16px; padding: 16px 18px; box-shadow: 0 6px 18px rgba(0,0,0,.07); display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .filtres label { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: #2d5a37; font-weight: 700; margin-bottom: 5px; }
    .filtres select, .filtres input { font-family: inherit; font-size: .92rem; padding: 9px 12px; border: 1px solid #cfd8d2; border-radius: 10px; min-width: 170px; }
    .bouton { border: 0; border-radius: 30px; padding: 10px 22px; font-family: inherit; font-weight: 700; font-size: .9rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-vide { background: #eef3ef; color: #2d5a37; }

    .compte { margin: 18px 2px 10px; font-size: .92rem; color: #555; }
    .compte b { color: #2d5a37; }

    .tableau-boite { background: #fff; border-radius: 16px; box-shadow: 0 6px 18px rgba(0,0,0,.07); overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; font-size: .9rem; }
    th { background: #f5f8f6; text-align: left; padding: 12px 14px; font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; color: #2d5a37; white-space: nowrap; border-bottom: 2px solid #e3ebe6; }
    td { padding: 11px 14px; border-bottom: 1px solid #f0f4f1; white-space: nowrap; }
    tr:last-child td { border-bottom: 0; }
    tr:hover td { background: #fafcfb; }
    .vide { color: #b8b8b8; font-style: italic; }
    .rien { padding: 40px; text-align: center; color: #888; }
    .badge-lien { text-decoration: none; font-size: 1.05rem; }
</style>
</head>
<body>

<div class="bandeau">
    <h1>Base des collaborateurs</h1>
    <div>
        <a class="pill" href="admin_champs.php">⚙️ Libellés</a>
        <a class="pill" href="index.php">Ma carte</a>
        <a class="pill" href="<?= e(famicardSiteUrl('index.php')) ?>">FamiFormation</a>
    </div>
</div>

<div class="wrap">

    <form class="filtres" method="get">
        <div>
            <label for="q">Recherche</label>
            <input type="text" id="q" name="q" value="<?= e($fTexte) ?>" placeholder="nom, identifiant, email">
        </div>
        <div>
            <label for="role">Profil</label>
            <select id="role" name="role">
                <option value="">Tous les profils</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r) ?>"<?= $fRole === $r ? ' selected' : '' ?>><?= e(famicardLibelleRole($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="site">Lieu de travail</label>
            <select id="site" name="site">
                <option value="">Tous les lieux</option>
                <?php foreach ($magasins as $id => $nom): ?>
                    <option value="<?= (int) $id ?>"<?= $fSite === (string) $id ? ' selected' : '' ?>><?= e($nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="bouton bouton-plein" type="submit">Filtrer</button>
        <a class="bouton bouton-vide" href="admin.php">Réinitialiser</a>
        <!-- L'export repart avec les MÊMES filtres : le fichier dira ce que dit l'écran. -->
        <a class="bouton bouton-vide" href="export.php<?= $filtresUrl !== '' ? '?' . e($filtresUrl) : '' ?>">📊 Exporter en Excel</a>
    </form>

    <p class="compte"><b><?= count($lignes) ?></b> collaborateur<?= count($lignes) > 1 ? 's' : '' ?><?= ($fRole !== '' || $fSite !== '' || $fTexte !== '') ? ' pour ces critères' : ' au total' ?>.</p>

    <div class="tableau-boite">
        <?php if (!$lignes): ?>
            <div class="rien">Aucun collaborateur ne correspond à ces critères.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($colonnesTableau as $champ): ?><th><?= e($champ['libelle']) ?></th><?php endforeach; ?>
                        <th>Badge</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lignes as $ligne): ?>
                    <?php $libres = $libresParUser[(int) $ligne['id']] ?? []; ?>
                    <tr>
                        <?php foreach ($colonnesTableau as $cle => $champ): ?>
                            <?php $valeur = famicardValeurAffichee($cle, $champ, $ligne, $magasins, $libres); ?>
                            <td><?= $valeur === '' ? '<span class="vide">—</span>' : e($valeur) ?></td>
                        <?php endforeach; ?>
                        <td>
                            <a class="badge-lien" href="badge.php?id=<?= (int) $ligne['id'] ?>"
                               title="Imprimer le badge de <?= e($ligne['prenom'] ?? '') ?>">🖨️</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
