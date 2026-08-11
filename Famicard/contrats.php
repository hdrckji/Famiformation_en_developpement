<?php
// ============================================================
// contrats.php — INTERNE OU EXTERNE, ET AVEC QUEL CONTRAT.
//
// L'écran qui rend la reprise FINISSABLE. Les deux colonnes `employeur` et
// `contrat` sont créées et déduites automatiquement (voir includes/emploi.php),
// mais le contrat, lui, ne se devine pas : seul un humain sait si tel employé
// est flexi ou fixe. Sans un écran pour le dire en série, la donnée resterait
// vide pour toujours — une colonne ajoutée et jamais remplie est pire que pas
// de colonne, parce qu'elle a l'air de répondre à la question.
//
// TROIS CHOSES, DANS CET ORDRE :
//   1. LA VUE D'ENSEMBLE — le tableau croisé employeur × contrat. C'est la
//      réponse à « c'est le bazar » : une seule image, et on voit où on en est.
//   2. CE QUI RESTE À PRÉCISER — groupé par profil, parce qu'on répond
//      naturellement par paquets (« les 168 employés magasin sont fixes »),
//      pas fiche par fiche.
//   3. CE QUI SE CONTREDIT — les fiches où deux réponses ne peuvent pas être
//      vraies ensemble. Signalées, jamais corrigées toutes seules : une
//      correction automatique sur une donnée RH remplace une erreur visible
//      par une erreur invisible.
//
// ⚠️ ON N'ÉCRIT QUE `contrat` ICI. `role` n'est pas touché (RBAC), et `interim`
// non plus (c'est la vue des agences dans FamiJob qui en dépend). Cocher cent
// personnes et changer leur profil d'un clic serait la fonction la plus
// dangereuse du dépôt ; ce n'est pas ce que fait cette page.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/modifications.php';

$moi = famicardExigeConnexion($db); // et non verifierConnexion() : voir Famicard/README
$moiId = (int) $moi['id'];

if (!famicardEstAdmin()) {
    header('Location: index.php');
    exit();
}

famicardAssureEmploi($db);
famicardAssureModifications($db);

$colonnes = famicardColonnesUtilisateurs($db);
$pretes = isset($colonnes['employeur']) && isset($colonnes['contrat']);

$employeurs = famicardEmployeurs();
$contrats   = famicardOptionsContrat();
$champs     = famicardChamps($db);

$flash = '';
if (!empty($_SESSION['famicard_contrats_flash'])) {
    $flash = (string) $_SESSION['famicard_contrats_flash'];
    unset($_SESSION['famicard_contrats_flash']);
}

// ─────────────────────────────────────────────────────────────────────────────
// QUI EST CONCERNÉ. La même définition que le compteur de l'accueil, sinon la
// pastille annoncerait un travail que la page ne montre pas.
//
// Les comptes INACTIFS et les comptes d'agence sont hors sujet : préciser le
// contrat de quelqu'un qui est parti n'apporte rien, et les compter rendrait le
// compteur impossible à ramener à zéro — donc inutile.
// ─────────────────────────────────────────────────────────────────────────────
function famicardListeAPreciser(PDO $db)
{
    try {
        return $db->query(
            "SELECT id, identifiant, nom, prenom, role, employeur, contrat, interim
             FROM utilisateurs
             WHERE (contrat IS NULL OR contrat = '')
               AND (statut IS NULL OR statut <> 'inactif')
               AND role <> 'agence_interim'
             ORDER BY role ASC, nom ASC, prenom ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$aPreciser = $pretes ? famicardListeAPreciser($db) : [];

// ─────────────────────────────────────────────────────────────────────────────
// APPLICATION EN SÉRIE.
//
// On ne fait confiance qu'à la liste calculée ICI : un identifiant bricolé dans
// le formulaire ne peut pas viser quelqu'un qui a déjà un contrat. Et la
// condition est AUSSI dans le SQL (`contrat IS NULL`) — deux administrateurs qui
// travaillent en même temps ne doivent pas s'écraser l'un l'autre.
// ─────────────────────────────────────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'appliquer') {
    requireValidCSRF();

    $choisi = (string) ($_POST['contrat'] ?? '');
    $ids = array_map('intval', (array) ($_POST['user_ids'] ?? []));
    $ids = array_values(array_unique(array_filter($ids)));

    $autorises = [];
    foreach ($aPreciser as $u) {
        $autorises[(int) $u['id']] = $u;
    }

    if (!isset($contrats[$choisi])) {
        $flash = "<div class='flash err'>❌ Choisis d'abord un type de contrat.</div>";
    } elseif (!$ids) {
        $flash = "<div class='flash err'>❌ Personne de coché.</div>";
    } else {
        $upd = $db->prepare(
            "UPDATE utilisateurs SET contrat = ? WHERE id = ? AND (contrat IS NULL OR contrat = '')"
        );
        $champContrat = $champs['contrat'] ?? ['libelle' => 'Type de contrat', 'colonne' => 'contrat'];

        $faits = 0;
        $ignores = 0;
        foreach ($ids as $id) {
            if (!isset($autorises[$id])) {
                $ignores++;
                continue;
            }
            $upd->execute([$choisi, $id]);
            if ($upd->rowCount() > 0) {
                $faits++;
                // Tracé comme déjà validé : c'est un admin qui écrit, il n'a
                // personne à qui demander confirmation. Mais « qui a mis ce
                // contrat, et quand » reste une question qu'on se posera.
                famicardTraceModification(
                    $db, $id, 'contrat', $champContrat, '', $contrats[$choisi], $moiId, false
                );
            } else {
                $ignores++; // quelqu'un d'autre vient de le renseigner
            }
        }

        $texte = "<div class='flash ok'>✅ <b>$faits</b> contrat" . ($faits > 1 ? 's' : '')
               . ' passé' . ($faits > 1 ? 's' : '') . ' en « ' . e($contrats[$choisi]) . ' ».';
        if ($ignores > 0) {
            $texte .= ' ' . $ignores . ' ignoré' . ($ignores > 1 ? 's' : '')
                    . ' (déjà renseigné entre-temps).';
        }
        $_SESSION['famicard_contrats_flash'] = $texte . '</div>';
        header('Location: contrats.php');
        exit();
    }

    $aPreciser = famicardListeAPreciser($db);
}

// ─────────────────────────────────────────────────────────────────────────────
// LA VUE D'ENSEMBLE — un seul passage sur la base, compté en PHP. Un GROUP BY
// par croisement rendrait des trous (les cases vides n'ont pas de ligne), et
// c'est justement les trous qu'on veut voir.
// ─────────────────────────────────────────────────────────────────────────────
$tous = [];
if ($pretes) {
    try {
        $tous = $db->query(
            "SELECT id, identifiant, nom, prenom, role, employeur, contrat, interim, statut
             FROM utilisateurs
             WHERE role <> 'agence_interim'
             ORDER BY nom ASC, prenom ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $tous = [];
    }
}

$grille = [];
$totalLigne = [];
$totalColonne = [];
$actifs = 0;
foreach ($tous as $u) {
    if (($u['statut'] ?? '') === 'inactif') {
        continue;
    }
    $actifs++;
    $e = (string) ($u['employeur'] ?? '');
    $c = (string) ($u['contrat'] ?? '');
    if (!isset($employeurs[$e])) { $e = '?'; }
    if (!isset($contrats[$c]))   { $c = '?'; }
    $grille[$e][$c] = ($grille[$e][$c] ?? 0) + 1;
    $totalLigne[$e] = ($totalLigne[$e] ?? 0) + 1;
    $totalColonne[$c] = ($totalColonne[$c] ?? 0) + 1;
}

// Les fiches qui se contredisent. Calculé sur TOUT LE MONDE, actifs compris ou
// non : une incohérence sur un compte inactif reste une incohérence le jour où
// on le réactive.
$incoherentes = [];
foreach ($tous as $u) {
    $dits = famicardIncoherencesEmploi($u);
    if ($dits) {
        $incoherentes[] = ['u' => $u, 'dits' => $dits];
    }
}

// Regroupement de la liste « à préciser » par profil : on répond par paquets.
$parProfil = [];
foreach ($aPreciser as $u) {
    $parProfil[(string) $u['role']][] = $u;
}

$titreLigne = static function ($u) {
    $nom = trim(((string) ($u['prenom'] ?? '')) . ' ' . ((string) ($u['nom'] ?? '')));
    return $nom !== '' ? $nom : (string) $u['identifiant'];
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrats et employeurs - Famicard</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Open Sans', sans-serif; background: #eef4ef; margin: 0; padding: 24px 16px 60px; color: #244230; }
        .wrap { max-width: 1040px; margin: 0 auto; }
        h1 { color: #2d5a37; font-size: 1.6rem; margin: 0 0 4px; }
        .sub { color: #5a6b60; margin: 0 0 18px; line-height: 1.55; }
        .card { background: #fff; border-radius: 16px; padding: 20px 22px; margin-bottom: 16px; box-shadow: 0 6px 20px rgba(14,59,36,.08); border: 1px solid #e6efe8; }
        .card h2 { margin: 0 0 6px; font-size: 1.15rem; color: #2d5a37; }
        .card .quoi { color: #5a6b60; font-size: .9rem; line-height: 1.55; margin: 0 0 16px; }
        .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 600; line-height: 1.6; }
        .flash.ok { background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; }
        .flash.err { background: #fdeaea; border: 1px solid #f3c2c2; color: #a12; }
        .note { background: #fff8e1; border: 1px solid #ffe082; color: #6a5400; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; line-height: 1.6; font-size: .92rem; }
        .btn { display: inline-block; border: none; cursor: pointer; background: #2d5a37; color: #fff; font-weight: 700; padding: 11px 22px; border-radius: 999px; text-decoration: none; font-size: .95rem; font-family: inherit; }
        .btn.ghost { background: #fff; color: #2d5a37; border: 2px solid #2d5a37; }
        .top { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }

        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid #eef2ef; }
        th { color: #6a7d72; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
        .croix td, .croix th { text-align: center; }
        .croix td:first-child, .croix th:first-child { text-align: left; font-weight: 700; color: #2d5a37; }
        .croix .total { background: #f6faf7; font-weight: 800; }
        .croix .zero { color: #c3cec7; }
        .croix .trou { background: #fff8e1; color: #6a5400; font-weight: 800; }

        .tag { border-radius: 999px; padding: 3px 11px; font-size: .76rem; font-weight: 800; white-space: nowrap; display: inline-block; }
        .tag.role { background: #eef4ef; color: #3d6b48; }
        .tag.interne { background: #e7f6ea; color: #1E7A46; }
        .tag.interim { background: #fff3cd; color: #856404; }
        .tag.independant { background: #e8eefb; color: #2f4f8f; }
        .tag.inconnu { background: #f3f0ea; color: #8a7f68; }

        .paquet { border: 1px solid #e6efe8; border-radius: 14px; padding: 14px 16px; margin-bottom: 12px; background: #fbfdfc; }
        .paquet-tete { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
        .paquet-tete b { color: #2d5a37; font-size: 1rem; }
        .gens { display: flex; flex-wrap: wrap; gap: 8px; }
        .gens label { display: flex; align-items: center; gap: 8px; border: 1px solid #dbe7de; border-radius: 10px; padding: 7px 11px; background: #fff; font-size: .88rem; cursor: pointer; }
        .gens label:hover { border-color: #2d5a37; }
        .lien-mini { font-size: .84rem; color: #2d5a37; font-weight: 700; cursor: pointer; text-decoration: underline; background: none; border: none; padding: 0; font-family: inherit; }
        .barre { position: sticky; bottom: 0; background: #fff; border-top: 2px solid #e6efe8; padding: 14px 0 4px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 8px; }
        .barre select { padding: 10px 12px; border: 1px solid #cfe0d4; border-radius: 10px; font-family: inherit; font-size: .95rem; }
        .compte { font-size: .9rem; color: #5a6b60; }
        .lien-fiche { color: #2d5a37; font-weight: 700; text-decoration: none; }
        .lien-fiche:hover { text-decoration: underline; }
        .dits { margin: 4px 0 0; padding-left: 18px; color: #a12; font-size: .86rem; line-height: 1.5; }
        .scroll { overflow-x: auto; }
        .rien { color: #5a6b60; font-size: .95rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🧩 Contrats et employeurs</h1>
    <p class="sub">Chez qui la personne travaille, et comment elle est engagée. Deux questions distinctes — et aucune des deux n'ouvre le moindre accès.</p>

    <div class="top">
        <a href="index.php" class="btn ghost">← Accueil</a>
        <a href="admin.php" class="btn ghost">📇 Base des collaborateurs</a>
        <a href="creer.php" class="btn ghost">➕ Nouveau collaborateur</a>
    </div>

    <?= $flash ?>

    <?php if (!$pretes): ?>
        <div class="note">
            Les colonnes <b>employeur</b> et <b>contrat</b> n'ont pas pu être créées dans la base.
            Rien n'est cassé — les deux champs n'apparaissent simplement nulle part tant que c'est le cas.
        </div>
    <?php else: ?>

    <div class="note">
        <b>Famiflora n'est pas une agence, c'est l'entreprise.</b> Qui travaille pour elle est <b>interne</b> ;
        qui vient de Konvert, Ago, Tempo Team… est en <b>intérim</b> ; le reste est <b>indépendant</b>.<br>
        Le <b>contrat</b> est une question à part : un intérimaire peut être étudiant, flexi ou fixe, et un interne aussi.<br>
        Le <b>profil</b> (Admin, Teamcoach, Étudiant…) ne bouge pas d'ici : c'est lui, et lui seul, qui donne des droits.
    </div>

    <div class="card">
        <h2>Où on en est</h2>
        <p class="quoi"><?= (int) $actifs ?> collaborateur<?= $actifs > 1 ? 's' : '' ?> actif<?= $actifs > 1 ? 's' : '' ?>. Les cases <b style="color:#6a5400;">en jaune</b> sont ce qui reste à préciser.</p>
        <div class="scroll">
            <table class="croix">
                <thead>
                    <tr>
                        <th>Employeur</th>
                        <?php foreach ($contrats as $lib): ?><th><?= e($lib) ?></th><?php endforeach; ?>
                        <th>À préciser</th>
                        <th class="total">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_merge(array_keys($employeurs), ['?']) as $cleE): ?>
                    <?php if (empty($totalLigne[$cleE])) { continue; } ?>
                    <tr>
                        <td><?= e($cleE === '?' ? 'À préciser' : $employeurs[$cleE]['court']) ?></td>
                        <?php foreach (array_keys($contrats) as $cleC): ?>
                            <?php $n = (int) ($grille[$cleE][$cleC] ?? 0); ?>
                            <td class="<?= $n === 0 ? 'zero' : '' ?>"><?= $n === 0 ? '—' : $n ?></td>
                        <?php endforeach; ?>
                        <?php $trou = (int) ($grille[$cleE]['?'] ?? 0); ?>
                        <td class="<?= $trou > 0 ? 'trou' : 'zero' ?>"><?= $trou === 0 ? '—' : $trou ?></td>
                        <td class="total"><?= (int) $totalLigne[$cleE] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total">
                        <td>Total</td>
                        <?php foreach (array_keys($contrats) as $cleC): ?>
                            <td class="total"><?= (int) ($totalColonne[$cleC] ?? 0) ?></td>
                        <?php endforeach; ?>
                        <td class="total"><?= (int) ($totalColonne['?'] ?? 0) ?></td>
                        <td class="total"><?= (int) $actifs ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Contrats à préciser</h2>
        <?php if (!$aPreciser): ?>
            <p class="rien">✅ Rien à faire : tous les collaborateurs actifs ont un type de contrat.</p>
        <?php else: ?>
            <p class="quoi">
                Groupés par profil, parce qu'on répond par paquets : coche un groupe entier, choisis son contrat,
                applique. Rien n'est deviné à ta place — c'est bien pour ça que ces fiches sont vides.
            </p>
            <form method="POST" action="contrats.php" id="formContrats">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="appliquer">

                <?php foreach ($parProfil as $roleCle => $gens): ?>
                    <div class="paquet">
                        <div class="paquet-tete">
                            <b><?= e(famicardLibelleRole($roleCle)) ?></b>
                            <span class="compte">
                                <?= count($gens) ?> à préciser ·
                                <button type="button" class="lien-mini" onclick="cocher('<?= e($roleCle) ?>', true)">tout cocher</button> ·
                                <button type="button" class="lien-mini" onclick="cocher('<?= e($roleCle) ?>', false)">tout décocher</button>
                            </span>
                        </div>
                        <div class="gens">
                            <?php foreach ($gens as $u): ?>
                                <?php
                                    $emp = (string) ($u['employeur'] ?? '');
                                    $classe = isset($employeurs[$emp]) ? $emp : 'inconnu';
                                    $libEmp = $employeurs[$emp]['court'] ?? '?';
                                ?>
                                <label>
                                    <input type="checkbox" name="user_ids[]" value="<?= (int) $u['id'] ?>"
                                           class="case" data-role="<?= e($roleCle) ?>">
                                    <span><?= e($titreLigne($u)) ?></span>
                                    <span class="tag <?= e($classe) ?>"><?= e($libEmp) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="barre">
                    <select name="contrat" required>
                        <option value="">— Quel contrat ? —</option>
                        <?php foreach ($contrats as $val => $lib): ?>
                            <option value="<?= e($val) ?>"><?= e($lib) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn">Appliquer aux cochés</button>
                    <span class="compte" id="compteur"></span>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Ce qui se contredit</h2>
        <?php if (!$incoherentes): ?>
            <p class="rien">✅ Aucune fiche ne se contredit.</p>
        <?php else: ?>
            <p class="quoi">
                Deux réponses qui ne peuvent pas être vraies ensemble. Rien n'est corrigé automatiquement :
                seul un humain sait laquelle des deux est fausse.
            </p>
            <div class="scroll">
                <table>
                    <tr><th>Collaborateur</th><th>Profil</th><th>Ce qui cloche</th><th></th></tr>
                    <?php foreach ($incoherentes as $ligne): ?>
                        <tr>
                            <td><?= e($titreLigne($ligne['u'])) ?></td>
                            <td><span class="tag role"><?= e(famicardLibelleRole($ligne['u']['role'])) ?></span></td>
                            <td>
                                <ul class="dits">
                                    <?php foreach ($ligne['dits'] as $d): ?><li><?= e($d) ?></li><?php endforeach; ?>
                                </ul>
                            </td>
                            <td><a class="lien-fiche" href="modifier.php?id=<?= (int) $ligne['u']['id'] ?>">Corriger →</a></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<?php if ($pretes && $aPreciser): ?>
<script>
// Cocher un profil entier : c'est le geste attendu (« tous les employés magasin
// sont fixes »), et le faire à la main sur 168 cases ne se produirait jamais.
function cocher(role, etat) {
    document.querySelectorAll('.case[data-role="' + role + '"]').forEach(function (c) { c.checked = etat; });
    majCompteur();
}
function majCompteur() {
    var n = document.querySelectorAll('.case:checked').length;
    document.getElementById('compteur').textContent =
        n === 0 ? 'Personne de coché.' : n + ' personne(s) cochée(s).';
}
document.querySelectorAll('.case').forEach(function (c) { c.addEventListener('change', majCompteur); });
majCompteur();
</script>
<?php endif; ?>
</body>
</html>
