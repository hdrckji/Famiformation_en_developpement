<?php
// ============================================================
// FAMICARD — LES MODIFICATIONS À CONFIRMER.
//
// Le contrôle vient APRÈS la modification, pas avant (voir
// includes/modifications.php pour le pourquoi). Cette page est donc l'endroit
// où ce choix tient ses promesses : sans elle, « on validera après » voudrait
// dire « personne ne regardera jamais ».
//
// ── UNE LIGNE PAR PERSONNE, PAS PAR CHAMP (décision de Jimmy) ───────────────
// La liste montrait une ligne par correction : quelqu'un qui corrige quatre
// informations en produisait quatre. On tranchait son adresse sans voir qu'il
// avait aussi changé de secteur, et l'écran donnait l'impression d'un
// raz-de-marée là où il n'y avait que deux personnes consciencieuses.
//
// On clique donc sur une personne, et on voit SA FICHE ENTIÈRE avec ce qui a
// changé mis en évidence. Trancher sans le reste de la fiche sous les yeux,
// c'est trancher à l'aveugle : « Bougies » n'a pas le même sens selon que la
// personne est étudiante ou teamcoach.
//
// COLLABORATEURS ET AGENCES ENSEMBLE : ce sont les mêmes corrections, elles
// suivent le même chemin, et les séparer obligerait à regarder à deux endroits
// pour être sûr de n'avoir rien laissé passer.
//
// Deux décisions, et une seule est neutre :
//   • CONFIRMER  — la nouvelle valeur reste, la ligne sort de la liste ;
//   • RÉTABLIR   — l'ANCIENNE valeur est réécrite dans la fiche.
// Rétablir modifie donc réellement la donnée. C'est voulu : marquer une
// modification « refusée » en laissant la valeur refusée en place donnerait
// une fiche fausse ET un registre qui prétend le contraire.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/modifications.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/agence.php';

$moi = famicardExigeConnexion($db);

if (!famicardEstAdmin()) {
    header('Location: index.php');
    exit();
}

famicardAssureModifications($db);
famicardAssureValidation($db);

$message = '';
if (!empty($_SESSION['famicard_valid_flash'])) {
    $message = (string) $_SESSION['famicard_valid_flash'];
    unset($_SESSION['famicard_valid_flash']);
}

/** Post/Redirect/Get : un rafraîchissement ne doit pas rejouer une décision. */
function validRetour($message, $vers = 'validations.php')
{
    $_SESSION['famicard_valid_flash'] = $message;
    header('Location: ' . $vers);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    // ── UN MOT LAISSÉ EN ENREGISTRANT ────────────────────────────────────
    // Ce n'est pas une modification à trancher : c'est un message. On le
    // marque LU, on ne l'efface pas — savoir ce qui a été signalé, et quand,
    // vaut mieux qu'une boîte qui se vide.
    if (isset($_POST['commentaire_lu'])) {
        try {
            $db->prepare(
                "UPDATE famicard_validation SET commentaire_lu_le = NOW(), commentaire_lu_par = ?
                 WHERE user_id = ?"
            )->execute([(int) $moi['id'], (int) $_POST['commentaire_lu']]);
            validRetour('✅ Message marqué comme lu.');
        } catch (Exception $e) {
            validRetour("Ce message n'a pas pu être marqué comme lu.");
        }
    }

    // ── TOUT TRANCHER D'UN COUP, POUR UNE PERSONNE ───────────────────────
    // Le geste courant : on a lu la fiche, tout se tient, on confirme. Le
    // faire champ par champ sur six corrections, c'est six clics pour une
    // seule décision — et au sixième on ne lit plus.
    if (isset($_POST['tout'])) {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $decision = ((string) $_POST['tout'] === 'retabli') ? 'retabli' : 'valide';

        $faits = 0;
        $bloques = 0;
        foreach (famicardModificationsEnAttentePour($db, $uid) as $m) {
            if (famicardTrancheModification($db, (int) $m['id'], (int) $moi['id'], $decision)) {
                $faits++;
            } else {
                $bloques++;
            }
        }

        $texte = $decision === 'valide'
            ? "✅ $faits modification(s) confirmée(s)."
            : "↩️ $faits ancienne(s) valeur(s) rétablie(s).";
        if ($bloques > 0) {
            // Un rétablissement peut être refusé : un rattachement dont le
            // libellé ne correspond plus à rien, par exemple. On le DIT plutôt
            // que de laisser croire que tout est réglé.
            $texte .= " ⚠️ $bloques n'a pas pu être traitée — ouvre la fiche pour voir laquelle.";
        }
        validRetour($texte, $bloques > 0 ? 'validations.php?id=' . $uid : 'validations.php');
    }

    // ── UNE SEULE MODIFICATION ───────────────────────────────────────────
    $id = (int) ($_POST['modif_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $retour = (int) ($_POST['retour_id'] ?? 0);

    $ok = famicardTrancheModification($db, $id, (int) $moi['id'], $decision);

    validRetour(
        $ok
            ? ($decision === 'valide' ? '✅ Modification confirmée.' : '↩️ Ancienne valeur rétablie.')
            : "Cette modification n'a pas pu être traitée (déjà tranchée, ou valeur introuvable).",
        $retour > 0 ? 'validations.php?id=' . $retour : 'validations.php'
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// DEUX VUES DANS UNE PAGE : la liste, ou une fiche.
// ─────────────────────────────────────────────────────────────────────────────
$cibleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$parPersonne = famicardModificationsParPersonne($db);

// Les mots laissés en enregistrant, par personne : ils expliquent souvent la
// correction d'à côté, et se lisent donc AVEC elle.
$mots = [];
try {
    foreach ($db->query(
        "SELECT user_id, commentaire, commentaire_le FROM famicard_validation
          WHERE commentaire IS NOT NULL AND commentaire <> '' AND commentaire_lu_le IS NULL"
    )->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $mots[(int) $m['user_id']] = $m;
    }
} catch (Exception $e) {
    $mots = [];
}

$quand = static function ($v) {
    $t = strtotime((string) $v);
    return $t ? date('d/m/Y à H\hi', $t) : '';
};

// ── LA VUE DÉTAILLÉE ────────────────────────────────────────────────────────
$cible = null;
$champs = [];
$groupes = [];
$magasins = [];
$libres = [];
$enAttente = [];
if ($cibleId > 0) {
    $st = $db->prepare('SELECT * FROM utilisateurs WHERE id = ? LIMIT 1');
    $st->execute([$cibleId]);
    $cible = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($cible) {
    $cibleEstAgence = famicardEstCompteAgence($cible['role'] ?? '');
    if ($cibleEstAgence) {
        $cible   = famicardAjouteAgence($db, $cible);
        $champs  = famicardChampsAgence();
        $groupes = famicardGroupesAgence();
    } else {
        $cible = famicardAjouteRattachement(
            $cible,
            famicardRattachementsRh($db, [$cibleId]),
            famicardPlacements($db, [$cibleId])
        );
        $champs  = famicardChamps($db);
        $groupes = famicardGroupes();
        $libres  = famicardValeursLibres($db, $cibleId);
    }
    $magasins  = famicardMagasins($db);
    $enAttente = famicardModificationsEnAttentePour($db, $cibleId);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifications à confirmer - Famicard</title>
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
    .wrap { max-width: 1000px; margin: 22px auto 0; padding: 0 16px; }

    .flash { background: #e8f5e9; color: #1e5128; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-weight: 600; font-size: .9rem; line-height: 1.55; }
    .boite { background: #fff; border-radius: 16px; box-shadow: 0 6px 18px rgba(0,0,0,.07); overflow: hidden; margin-bottom: 18px; }
    .rien { padding: 40px 22px; text-align: center; color: #777; font-size: .95rem; line-height: 1.6; }

    /* ── LA LISTE : une personne par ligne ─────────────────────────────── */
    .personne { display: flex; gap: 16px; align-items: center; padding: 16px 22px; border-bottom: 1px solid #f0f4f1; text-decoration: none; color: inherit; }
    .personne:last-child { border-bottom: 0; }
    .personne:hover { background: #f7faf8; }
    .personne .qui { flex: 1 1 220px; min-width: 0; }
    .personne .nom { font-weight: 800; color: #2d5a37; font-size: 1.02rem; }
    .personne .quand { color: #8a968f; font-size: .8rem; margin-top: 3px; }
    .personne .apercu { flex: 2 1 300px; color: #5a6b60; font-size: .87rem; line-height: 1.5; min-width: 0; }
    .compteur { background: #E9A93C; color: #fff; border-radius: 999px; padding: 4px 13px; font-size: .78rem; font-weight: 800; white-space: nowrap; }
    .fleche { color: #2d5a37; font-weight: 800; }
    .mot { background: #fff8e1; border-left: 4px solid #E9A93C; color: #6a5400; border-radius: 10px; padding: 9px 13px; margin-top: 9px; font-size: .86rem; line-height: 1.55; white-space: pre-wrap; }

    /* ── LE DÉTAIL : la fiche entière, le changé en évidence ───────────── */
    .groupe-titre { font-size: .76rem; text-transform: uppercase; letter-spacing: .07em; color: #2d5a37; font-weight: 800; padding: 16px 22px 6px; }
    .ligne { display: flex; gap: 16px; padding: 11px 22px; border-bottom: 1px solid #f4f7f5; align-items: flex-start; flex-wrap: wrap; }
    .ligne:last-child { border-bottom: 0; }
    .ligne .libelle { flex: 0 0 200px; color: #6a7d72; font-size: .88rem; padding-top: 3px; }
    .ligne .valeur { flex: 1 1 260px; font-size: .93rem; font-weight: 600; min-width: 0; }
    .ligne .valeur .vide { color: #b8b8b8; font-style: italic; font-weight: 400; }

    /* La ligne qui a changé : fond ambré, la même couleur que partout
       ailleurs pour « en attente ». On reconnaît l'état sans lire. */
    .ligne.change { background: #fffaf0; }
    .valeurs { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
    .avant { background: #fdecea; color: #a3271c; border-radius: 8px; padding: 4px 10px; text-decoration: line-through; }
    .apres { background: #e8f5e9; color: #1e5128; border-radius: 8px; padding: 4px 10px; font-weight: 700; }
    .par-qui { color: #8a968f; font-size: .78rem; margin-top: 5px; }
    .decisions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }

    .bouton { border: 0; border-radius: 30px; padding: 9px 18px; font-family: inherit; font-weight: 700; font-size: .85rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-ok { background: #2d5a37; color: #fff; }
    .bouton-non { background: #fff; color: #a83232; border: 1px solid #e8c9c9; }
    .bouton-vide { background: #eef3ef; color: #2d5a37; }
    .barre { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; padding: 16px 22px; background: #f7faf8; border-top: 1px solid #eef2ef; }
</style>
</head>
<body class="voile">

<div class="bandeau">
    <h1><?= $cible ? 'Modifications de ' . e(trim(((string) ($cible['prenom'] ?? '')) . ' ' . ((string) ($cible['nom'] ?? '')))) ?: e((string) $cible['identifiant']) : 'Modifications à confirmer' ?></h1>
    <div>
        <?php if ($cible): ?>
            <a class="pill" href="modifier.php?id=<?= (int) $cibleId ?>">Ouvrir sa fiche</a>
            <a class="pill" href="validations.php">&larr; Toutes les modifications</a>
        <?php else: ?>
            <a class="pill" href="admin.php">Base des collaborateurs</a>
            <a class="pill" href="index.php">&larr; Accueil</a>
        <?php endif; ?>
    </div>
</div>

<div class="wrap">

    <?php if ($message !== ''): ?>
        <div class="flash"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if (!$cible): ?>
        <?php // ── LA LISTE ────────────────────────────────────────────── ?>
        <div class="boite">
            <?php if (!$parPersonne): ?>
                <div class="rien">
                    Rien à confirmer.<br>
                    Les corrections faites par les collaborateurs <b>et les agences</b> sur leur propre
                    fiche apparaissent ici, regroupées par personne.
                </div>
            <?php else: ?>
                <?php foreach ($parPersonne as $uid => $bloc): ?>
                    <?php
                        // L'aperçu : les libellés touchés, sans les valeurs. On
                        // décide d'ouvrir ou non, on ne tranche pas d'ici.
                        $libelles = [];
                        foreach ($bloc['modifs'] as $m) {
                            $l = (string) $m['libelle'];
                            if (!in_array($l, $libelles, true)) { $libelles[] = $l; }
                        }
                    ?>
                    <a class="personne" href="validations.php?id=<?= (int) $uid ?>">
                        <div class="qui">
                            <div class="nom"><?= e($bloc['personne']) ?></div>
                            <div class="quand">Dernière correction le <?= e($quand($bloc['dernier'])) ?></div>
                        </div>
                        <div class="apercu">
                            <?= e(implode(' · ', $libelles)) ?>
                            <?php if (isset($mots[$uid])): ?>
                                <div class="mot">💬 <?= e((string) $mots[$uid]['commentaire']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="compteur"><?= count($bloc['modifs']) ?></span>
                        <span class="fleche">→</span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <?php // ── LE DÉTAIL : la fiche entière ────────────────────────────
              // Tous les champs, pas seulement ceux qui ont changé : trancher
              // sans le reste sous les yeux, c'est trancher à l'aveugle. ?>

        <?php if (isset($mots[$cibleId])): ?>
            <div class="boite">
                <div class="groupe-titre">💬 Le mot laissé avec ces corrections</div>
                <div class="ligne" style="display:block;">
                    <div style="white-space:pre-wrap;line-height:1.6;"><?= e((string) $mots[$cibleId]['commentaire']) ?></div>
                    <form method="POST" style="margin-top:10px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="commentaire_lu" value="<?= (int) $cibleId ?>">
                        <button type="submit" class="bouton bouton-vide">Marquer comme lu</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="boite">
            <?php if (!$enAttente): ?>
                <div class="rien">Plus rien à confirmer sur cette fiche.</div>
            <?php endif; ?>

            <?php foreach ($groupes as $cleGroupe => $groupe): ?>
                <?php
                $lignes = [];
                foreach ($champs as $cle => $champ) {
                    if (($champ['groupe'] ?? '') !== $cleGroupe) { continue; }
                    if (($champ['saisie'] ?? '') === 'photo') { continue; }
                    // On regarde une fiche en administrateur : famicardPeutVoir
                    // décide, comme partout ailleurs.
                    if (!famicardPeutVoir($champ, true, false)) { continue; }
                    $lignes[$cle] = $champ;
                }
                if (!$lignes) { continue; }
                ?>
                <div class="groupe-titre"><?= e($groupe['libelle']) ?></div>
                <?php foreach ($lignes as $cle => $champ): ?>
                    <?php
                        $modif = $enAttente[$cle] ?? null;
                        $valeur = famicardValeurAffichee($cle, $champ, $cible, $magasins, $libres);
                    ?>
                    <div class="ligne<?= $modif ? ' change' : '' ?>">
                        <div class="libelle"><?= e($champ['libelle']) ?></div>
                        <div class="valeur">
                            <?php if ($modif): ?>
                                <div class="valeurs">
                                    <span class="avant"><?= ((string) $modif['avant'] !== '') ? e((string) $modif['avant']) : '<span class="vide">vide</span>' ?></span>
                                    <span>→</span>
                                    <span class="apres"><?= ((string) $modif['apres'] !== '') ? e((string) $modif['apres']) : '<span class="vide">vidé</span>' ?></span>
                                </div>
                                <div class="par-qui">Corrigé le <?= e($quand($modif['fait_le'])) ?></div>
                                <div class="decisions">
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="modif_id" value="<?= (int) $modif['id'] ?>">
                                        <input type="hidden" name="retour_id" value="<?= (int) $cibleId ?>">
                                        <input type="hidden" name="decision" value="valide">
                                        <button type="submit" class="bouton bouton-ok">Confirmer</button>
                                    </form>
                                    <form method="POST">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="modif_id" value="<?= (int) $modif['id'] ?>">
                                        <input type="hidden" name="retour_id" value="<?= (int) $cibleId ?>">
                                        <input type="hidden" name="decision" value="retabli">
                                        <button type="submit" class="bouton bouton-non"
                                                onclick="return confirm('Rétablir l\'ancienne valeur ?');">Rétablir</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <?= $valeur !== '' ? e($valeur) : '<span class="vide">non renseigné</span>' ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if ($enAttente): ?>
                <div class="barre">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $cibleId ?>">
                        <input type="hidden" name="tout" value="valide">
                        <button type="submit" class="bouton bouton-ok">
                            ✅ Tout confirmer (<?= count($enAttente) ?>)
                        </button>
                    </form>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $cibleId ?>">
                        <input type="hidden" name="tout" value="retabli">
                        <button type="submit" class="bouton bouton-non"
                                onclick="return confirm('Rétablir TOUTES les anciennes valeurs de cette fiche ?');">
                            ↩️ Tout rétablir
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
