<?php
// ============================================================
// classement.php — CLASSEMENT des points CUMULÉS.
//
// Les points viennent de deux endroits : quiz_engine.php (score des quiz) et
// valider_vue.php (+10 par contenu terminé). C'est donc un CUMUL, pas le score
// d'un seul quiz — l'affichage le dit maintenant clairement, c'était la source
// de confusion.
//
// Le classement est propre à chaque PROFIL : un étudiant se compare aux
// étudiants, pas aux admins qui ont parcouru tous les contenus.
// ============================================================

// Pas de cache : les points doivent apparaître en temps réel.
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once 'config.php';
verifierConnexion($db);

$role  = $_SESSION['role'] ?? 'etudiant';
$moiId = (int) (getCurrentUserId() ?? 0);
$TOP   = 10;

$stmt = $db->prepare(
    "SELECT id, nom, prenom, points
     FROM utilisateurs
     WHERE points > 0 AND role = ?
     ORDER BY points DESC, nom ASC, prenom ASC
     LIMIT " . (int) $TOP
);
$stmt->execute([$role]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// RANGS AVEC EX AEQUO : deux personnes à 120 points sont toutes deux 2e, et la
// suivante est 4e. Avant, la simple position dans la liste laissait croire à un
// écart qui n'existait pas.
$rangs = [];
$rangCourant = 0;
$pointsPrec = null;
foreach ($users as $i => $u) {
    if ($pointsPrec === null || (int) $u['points'] !== $pointsPrec) {
        $rangCourant = $i + 1;
        $pointsPrec = (int) $u['points'];
    }
    $rangs[$i] = $rangCourant;
}

// MA POSITION, même hors du top : sans ça, quelqu'un de 12e ne voyait rien qui
// le concerne et la page ne lui servait à rien.
$moiPoints = 0;
$moiRang = 0;
$moiDansTop = false;
foreach ($users as $i => $u) {
    if ((int) $u['id'] === $moiId) {
        $moiDansTop = true;
        $moiRang = $rangs[$i];
        $moiPoints = (int) $u['points'];
    }
}
if (!$moiDansTop && $moiId > 0) {
    $q = $db->prepare("SELECT points FROM utilisateurs WHERE id = ? LIMIT 1");
    $q->execute([$moiId]);
    $moiPoints = (int) $q->fetchColumn();
    if ($moiPoints > 0) {
        $q = $db->prepare("SELECT COUNT(*) + 1 FROM utilisateurs WHERE role = ? AND points > ?");
        $q->execute([$role, $moiPoints]);
        $moiRang = (int) $q->fetchColumn();
    }
}

// Référence des barres de progression : le score du premier.
$pointsMax = !empty($users) ? max(1, (int) $users[0]['points']) : 1;

/** Nom affiché — jamais une ligne vide. */
function formatNom($u)
{
    $nom = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
    return $nom !== '' ? htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') : '—';
}

/** Initiales pour la pastille (2 lettres maximum). */
function initiales($u)
{
    $p = trim((string) ($u['prenom'] ?? ''));
    $n = trim((string) ($u['nom'] ?? ''));
    $i = mb_substr($p, 0, 1, 'UTF-8') . mb_substr($n, 0, 1, 'UTF-8');
    return htmlspecialchars(mb_strtoupper(trim($i) !== '' ? $i : '?', 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

$MEDAILLES = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('Classement', 'Klassement') ?> — FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Open Sans', sans-serif;
            background: url('background.jpg') no-repeat center center fixed; background-size: cover;
            margin: 0; padding: 20px 16px 50px; color: #244230;
            display: flex; flex-direction: column; align-items: center;
        }
        .container {
            background: rgba(255,255,255,0.96); max-width: 660px; width: 100%;
            padding: 28px 26px 34px; border-radius: 22px;
            box-shadow: 0 12px 34px rgba(0,0,0,0.18);
        }
        h1 { color: #2d5a37; margin: 0 0 8px; font-size: 1.5rem; text-align: center; }
        .subtitle { color: #6a7d72; margin: 0 0 6px; text-align: center; font-size: .93rem; line-height: 1.55; }
        .rappel { color: #8a968f; text-align: center; font-size: .84rem; margin: 0 0 24px; }

        /* ── PODIUM ────────────────────────────────────────────────────────────
           Chaque marche est une COLONNE complète : médaille, pastille, nom,
           points, socle. L'ancien podium posait les noms en position absolue
           au-dessus des blocs — dès qu'un nom était un peu long, il chevauchait
           celui d'à côté et le classement devenait illisible. */
        .podium { display: flex; align-items: flex-end; justify-content: center; gap: 10px; margin: 6px 0 26px; }
        .marche { flex: 1 1 0; max-width: 180px; display: flex; flex-direction: column; align-items: center; }
        .marche .medaille { font-size: 1.7rem; line-height: 1; margin-bottom: 6px; }
        .pastille {
            width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: .95rem; color: #fff; background: #4a7b55; margin-bottom: 8px;
            border: 3px solid #fff; box-shadow: 0 3px 10px rgba(0,0,0,.16);
        }
        .marche .nom { font-weight: 700; font-size: .88rem; line-height: 1.25; text-align: center; margin-bottom: 3px; word-break: break-word; }
        .marche .pts { color: #2d5a37; font-weight: 800; font-size: .84rem; margin-bottom: 8px; }
        .socle { width: 100%; border-radius: 12px 12px 0 0; color: #fff; font-weight: 800; font-size: .95rem;
                 display: flex; align-items: flex-end; justify-content: center; padding-bottom: 9px; }
        .or     { height: 104px; background: linear-gradient(180deg, #f5cf4a 0%, #d9a406 100%); box-shadow: 0 0 22px rgba(217,164,6,.35); }
        .argent { height:  78px; background: linear-gradient(180deg, #d6d9dc 0%, #a8adb2 100%); }
        .bronze { height:  60px; background: linear-gradient(180deg, #dda06a 0%, #b9723a 100%); }
        .marche.p1 { order: 2; } .marche.p2 { order: 1; } .marche.p3 { order: 3; }
        .marche.p1 .pastille { width: 56px; height: 56px; font-size: 1.05rem; background: #d9a406; }

        /* ── SUITE DU CLASSEMENT ───────────────────────────────────────────── */
        .liste { width: 100%; border-collapse: collapse; }
        .liste td { padding: 11px 8px; border-bottom: 1px solid #eef2ef; vertical-align: middle; }
        .liste tr:last-child td { border-bottom: none; }
        .rang { width: 44px; color: #8a968f; font-weight: 800; font-size: .9rem; text-align: center; }
        .cell-nom { font-weight: 600; }
        .barre { height: 6px; border-radius: 999px; background: #eef2ef; margin-top: 5px; overflow: hidden; }
        .barre span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #4a7b55, #2d5a37); }
        .cell-pts { width: 86px; text-align: right; font-weight: 800; color: #2d5a37; white-space: nowrap; }
        .moi { background: #f2f9f4; }
        .moi .cell-nom::after { content: ' (toi)'; color: #4a7b55; font-weight: 700; font-size: .82rem; }

        .ma-place {
            margin-top: 22px; padding: 15px 18px; border-radius: 16px;
            background: #f6faf7; border: 1px solid #dde9df;
            display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
        }
        .ma-place .txt { font-size: .93rem; line-height: 1.5; }
        .ma-place .gros { color: #2d5a37; font-weight: 800; font-size: 1.15rem; }

        .vide { padding: 34px 10px; color: #8a968f; text-align: center; line-height: 1.65; }
        .vide .ico { font-size: 2.6rem; display: block; margin-bottom: 10px; }
        .btn-back { display: block; width: fit-content; margin: 28px auto 0; padding: 12px 30px;
                    background: #2d5a37; color: #fff; text-decoration: none; border-radius: 999px; font-weight: 700; }

        @media (max-width: 480px) {
            .container { padding: 22px 16px 28px; }
            .podium { gap: 6px; }
            .marche .nom { font-size: .8rem; }
            .marche .medaille { font-size: 1.45rem; }
            .pastille { width: 40px; height: 40px; font-size: .85rem; }
            .marche.p1 .pastille { width: 48px; height: 48px; }
        }
    </style>
</head>
<body>
<?php
    require_once __DIR__ . '/includes/topbar.php';
    famiTopbar($db, ['back' => 'index.php', 'title' => '🏆 ' . t('Classement', 'Klassement')]);
?>
    <div class="container">
        <h1>🏆 <?= t('Classement FamiFlora', 'FamiFlora-klassement') ?></h1>
        <p class="subtitle">
            <?= t('Total des points gagnés sur les quiz et les formations terminées.',
                  'Totaal van de punten verdiend met quizzen en afgewerkte opleidingen.') ?>
        </p>
        <p class="rappel">
            <?= t('Tu es comparé aux personnes de ton profil.', 'Je wordt vergeleken met mensen van jouw profiel.') ?>
        </p>

        <?php if (count($users) > 0): ?>

            <?php // Podium : les 3 premiers, chacun dans sa propre colonne. ?>
            <div class="podium">
                <?php
                $marches = [1 => ['p1', 'or'], 2 => ['p2', 'argent'], 3 => ['p3', 'bronze']];
                foreach ($marches as $place => $cls):
                    $idx = $place - 1;
                    if (!isset($users[$idx])) { continue; }
                    $u = $users[$idx];
                ?>
                    <div class="marche <?= $cls[0] ?>">
                        <div class="medaille"><?= $MEDAILLES[$place] ?></div>
                        <div class="pastille"><?= initiales($u) ?></div>
                        <div class="nom"><?= formatNom($u) ?></div>
                        <div class="pts"><?= (int) $u['points'] ?> <?= t('pts', 'ptn') ?></div>
                        <div class="socle <?= $cls[1] ?>"><?= (int) $rangs[$idx] ?><?= $rangs[$idx] === 1 ? 'er' : 'e' ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($users) > 3): ?>
                <table class="liste">
                    <?php for ($i = 3; $i < count($users); $i++):
                        $u = $users[$i];
                        $part = (int) round(((int) $u['points'] / $pointsMax) * 100);
                    ?>
                        <tr class="<?= ((int) $u['id'] === $moiId) ? 'moi' : '' ?>">
                            <td class="rang">#<?= (int) $rangs[$i] ?></td>
                            <td>
                                <div class="cell-nom"><?= formatNom($u) ?></div>
                                <div class="barre"><span style="width: <?= max(4, min(100, $part)) ?>%;"></span></div>
                            </td>
                            <td class="cell-pts"><?= (int) $u['points'] ?> <?= t('pts', 'ptn') ?></td>
                        </tr>
                    <?php endfor; ?>
                </table>
            <?php endif; ?>

            <?php // Ma position : utile surtout quand on n'est PAS dans le top. ?>
            <?php if ($moiRang > 0 && !$moiDansTop): ?>
                <div class="ma-place">
                    <div class="txt">
                        <?= t('Ta position', 'Jouw positie') ?> :
                        <span class="gros">#<?= (int) $moiRang ?></span>
                        <?= t('avec', 'met') ?> <span class="gros"><?= (int) $moiPoints ?></span> <?= t('pts', 'ptn') ?>
                    </div>
                    <div class="txt" style="color:#6a7d72;">
                        <?= t('Encore un quiz et tu grimpes 🌱', 'Nog een quiz en je klimt 🌱') ?>
                    </div>
                </div>
            <?php elseif ($moiRang === 0): ?>
                <div class="ma-place">
                    <div class="txt" style="color:#6a7d72;">
                        <?= t('Tu n\'as pas encore de points : termine une formation ou un quiz pour entrer au classement 🌱',
                              'Je hebt nog geen punten: rond een opleiding of quiz af om in het klassement te komen 🌱') ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="vide">
                <span class="ico">🌱</span>
                <?= t('Le classement est encore vide.', 'Het klassement is nog leeg.') ?><br>
                <?= t('Termine une formation ou un quiz et prends la première place !',
                      'Rond een opleiding of quiz af en pak de eerste plaats!') ?>
            </div>
        <?php endif; ?>

        <a href="index.php" class="btn-back"><?= t("Retour à l'accueil", 'Terug naar de startpagina') ?></a>
    </div>
</body>
</html>
