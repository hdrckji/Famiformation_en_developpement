<?php
// ============================================================
// FAMICARD — LES MODIFICATIONS À CONFIRMER.
//
// Le contrôle vient APRÈS la modification, pas avant (voir
// includes/modifications.php pour le pourquoi). Cette page est donc l'endroit
// où ce choix tient ses promesses : sans elle, « on validera après » voudrait
// dire « personne ne regardera jamais ».
//
// Deux décisions possibles, et une seule est neutre :
//   • CONFIRMER  — la nouvelle valeur reste, la ligne sort de la liste ;
//   • RÉTABLIR   — l'ANCIENNE valeur est réécrite dans la fiche.
// Rétablir modifie donc réellement la donnée. C'est voulu : marquer une
// modification « refusée » en laissant la valeur refusée en place donnerait
// une fiche fausse ET un registre qui prétend le contraire.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/modifications.php';

$moi = famicardExigeConnexion($db);

if (!famicardEstAdmin()) {
    header('Location: index.php');
    exit();
}

famicardAssureModifications($db);

$message = '';
if (!empty($_SESSION['famicard_valid_flash'])) {
    $message = (string) $_SESSION['famicard_valid_flash'];
    unset($_SESSION['famicard_valid_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    $id = (int) ($_POST['modif_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');

    $ok = famicardTrancheModification($db, $id, (int) $moi['id'], $decision);

    $_SESSION['famicard_valid_flash'] = $ok
        ? ($decision === 'valide' ? '✅ Modification confirmée.' : '↩️ Ancienne valeur rétablie.')
        : "Cette modification a déjà été traitée.";

    header('Location: validations.php');
    exit();
}

$enAttente = famicardModificationsEnAttente($db);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifications à confirmer - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 0 0 50px; color: #333; }
    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .85rem; }
    .wrap { max-width: 1000px; margin: 22px auto 0; padding: 0 16px; }

    .flash { background: #e8f5e9; color: #1e5128; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-weight: 600; font-size: .9rem; }
    .boite { background: #fff; border-radius: 16px; box-shadow: 0 6px 18px rgba(0,0,0,.07); overflow: hidden; }

    .modif { padding: 18px 22px; border-bottom: 1px solid #eee; display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
    .modif:last-child { border-bottom: 0; }
    .modif .qui { flex: 1 1 210px; min-width: 0; }
    .modif .qui .nom { font-weight: 700; color: #2d5a37; }
    .modif .qui .quand { color: #888; font-size: .8rem; margin-top: 3px; }
    .modif .quoi { flex: 2 1 320px; min-width: 0; }
    .modif .champ { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 6px; }
    .valeurs { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: .93rem; }
    .avant { background: #fdecea; color: #a3271c; border-radius: 8px; padding: 5px 11px; text-decoration: line-through; }
    .apres { background: #e8f5e9; color: #1e5128; border-radius: 8px; padding: 5px 11px; font-weight: 700; }
    .vide { color: #999; font-style: italic; text-decoration: none; }
    .decisions { display: flex; gap: 8px; flex-wrap: wrap; }
    .bouton { border: 0; border-radius: 30px; padding: 9px 18px; font-family: inherit; font-weight: 700; font-size: .85rem; cursor: pointer; }
    .bouton-ok { background: #2d5a37; color: #fff; }
    .bouton-non { background: #fff; color: #a83232; border: 1px solid #e8c9c9; }

    .rien { padding: 40px 22px; text-align: center; color: #777; font-size: .95rem; line-height: 1.6; }
</style>
</head>
<body>

<div class="bandeau">
    <h1>Modifications à confirmer</h1>
    <div>
        <a class="pill" href="admin.php">Base des collaborateurs</a>
        <a class="pill" href="index.php">&larr; Accueil</a>
    </div>
</div>

<div class="wrap">

    <?php if ($message !== ''): ?>
        <div class="flash"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="boite">
        <?php if (!$enAttente): ?>
            <div class="rien">
                Rien à confirmer.<br>
                Les corrections faites par les collaborateurs sur leur propre fiche
                apparaissent ici, avec l'ancienne et la nouvelle valeur.
            </div>
        <?php else: ?>
            <?php foreach ($enAttente as $m): ?>
                <?php
                $nom = trim(((string) ($m['prenom'] ?? '')) . ' ' . ((string) ($m['nom'] ?? '')));
                if ($nom === '') {
                    // Compte supprimé depuis : on garde la ligne lisible plutôt
                    // que d'afficher un blanc que personne ne sait interpréter.
                    $nom = (string) ($m['identifiant'] ?? ('Compte #' . (int) $m['user_id']));
                }
                $ts = !empty($m['fait_le']) ? strtotime((string) $m['fait_le']) : false;
                ?>
                <div class="modif">
                    <div class="qui">
                        <div class="nom"><?= e($nom) ?></div>
                        <div class="quand"><?= $ts ? e(date('d/m/Y à H:i', $ts)) : '' ?></div>
                    </div>

                    <div class="quoi">
                        <div class="champ"><?= e((string) $m['libelle']) ?></div>
                        <div class="valeurs">
                            <span class="avant<?= ($m['avant'] === null || $m['avant'] === '') ? ' vide' : '' ?>">
                                <?= ($m['avant'] === null || $m['avant'] === '') ? 'vide' : e((string) $m['avant']) ?>
                            </span>
                            <span>→</span>
                            <span class="apres<?= ($m['apres'] === null || $m['apres'] === '') ? ' vide' : '' ?>">
                                <?= ($m['apres'] === null || $m['apres'] === '') ? 'vidé' : e((string) $m['apres']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="decisions">
                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="modif_id" value="<?= (int) $m['id'] ?>">
                            <input type="hidden" name="decision" value="valide">
                            <button type="submit" class="bouton bouton-ok">✔ Confirmer</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Rétablir l\'ancienne valeur ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="modif_id" value="<?= (int) $m['id'] ?>">
                            <input type="hidden" name="decision" value="retabli">
                            <button type="submit" class="bouton bouton-non">↩ Rétablir</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
