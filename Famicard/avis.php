<?php
// ============================================================
// avis.php — AVIS ET SUGGESTIONS.
//
// « Dans la même idée que FamiJob » (Jimmy) — et littéralement : c'est la MÊME
// table. `interim_feedback` existe déjà côté FamiJob ; en ouvrir une seconde
// aurait donné deux boîtes à idées, dont une que personne ne relève. Une
// colonne `source` dit simplement d'où vient le message, pour qu'un
// administrateur sache de quel écran on lui parle.
//
// OUVERT À TOUT LE MONDE, y compris aux agences. Un module qui recueille la
// parole des équipes et n'est visible que par ceux qui décident ne recueille
// rien.
//
// TROIS ÉTATS, pas plus :
//   • en attente — personne ne l'a encore regardé ;
//   • répondu    — un administrateur a écrit quelque chose ;
//   • traité     — le sujet est clos (et se rouvre d'un clic).
//
// ⚠️ ON N'EFFACE JAMAIS UN MESSAGE. « Traité » le range, ne le supprime pas :
// une boîte à idées qui se vide est une boîte dont on ne peut plus dire ce qui
// a été proposé, ni ce qu'on en a fait.
// ============================================================
require_once __DIR__ . '/config.php';

$moi = famicardExigeConnexion($db);
$moiId = (int) $moi['id'];
$estAdmin = famicardEstAdmin();
$roleMoi = (string) ($moi['role'] ?? '');

$monNom = trim(((string) ($moi['prenom'] ?? '')) . ' ' . ((string) ($moi['nom'] ?? '')));
if ($monNom === '') {
    $monNom = (string) ($moi['identifiant'] ?? '');
}

// ─────────────────────────────────────────────────────────────────────────────
// LES TABLES — celles de FamiJob, créées ici si elles manquent (à l'identique).
// La colonne `source` est ajoutée si besoin : elle est facultative pour FamiJob,
// qui ne la lit pas, et ajouter une colonne nullable ne casse personne.
// ─────────────────────────────────────────────────────────────────────────────
$tablesOk = true;
try {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS interim_feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            author_id INT NOT NULL,
            author_name VARCHAR(160) NULL,
            author_role VARCHAR(30) NULL,
            category VARCHAR(20) NOT NULL DEFAULT 'autre',
            subject VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            admin_note TEXT NULL,
            handled_by_user_id INT NULL,
            handled_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_feedback_author (author_id),
            INDEX idx_feedback_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $db->exec(
        "CREATE TABLE IF NOT EXISTS interim_feedback_replies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT UNSIGNED NOT NULL,
            author_id INT NOT NULL,
            author_name VARCHAR(160) NULL,
            author_role VARCHAR(30) NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reply_feedback (feedback_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    if (!$db->query("SHOW COLUMNS FROM interim_feedback LIKE 'source'")->fetch()) {
        $db->exec("ALTER TABLE interim_feedback ADD COLUMN source VARCHAR(20) NULL AFTER category");
    }
} catch (Exception $e) {
    $tablesOk = false;
}

$CATEGORIES = [
    'question'     => '❓ Une question',
    'amelioration' => '💡 Une suggestion',
    'probleme'     => '🐞 Un problème',
    'autre'        => '💬 Autre',
];

$flash = '';
if (!empty($_SESSION['famicard_avis_flash'])) {
    $flash = (string) $_SESSION['famicard_avis_flash'];
    unset($_SESSION['famicard_avis_flash']);
}

function avisRetour($message)
{
    $_SESSION['famicard_avis_flash'] = $message;
    header('Location: avis.php');
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTIONS
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tablesOk) {
    requireValidCSRF();
    $action = (string) ($_POST['action'] ?? '');

    // ── DÉPOSER UN MESSAGE ───────────────────────────────────────────────
    if ($action === 'deposer') {
        $categorie = (string) ($_POST['category'] ?? 'autre');
        $sujet = trim((string) ($_POST['subject'] ?? ''));
        $texte = trim((string) ($_POST['message'] ?? ''));

        if (!isset($CATEGORIES[$categorie])) { $categorie = 'autre'; }
        if ($sujet === '' || $texte === '') {
            avisRetour('❌ Un sujet et un message sont nécessaires.');
        }

        $db->prepare(
            "INSERT INTO interim_feedback
                (author_id, author_name, author_role, category, source, subject, message, status)
             VALUES (?, ?, ?, ?, 'famicard', ?, ?, 'open')"
        )->execute([$moiId, $monNom, $roleMoi, $categorie, mb_substr($sujet, 0, 180), $texte]);

        avisRetour('✅ Merci ! Ton message est transmis. Tu verras la réponse ici même.');
    }

    // ── RÉPONDRE ─────────────────────────────────────────────────────────
    // Ouvert à l'auteur ET aux administrateurs : une suggestion se discute,
    // sinon c'est une boîte aux lettres et pas une conversation.
    if ($action === 'repondre') {
        $id = (int) ($_POST['feedback_id'] ?? 0);
        $texte = trim((string) ($_POST['message'] ?? ''));

        $st = $db->prepare('SELECT author_id FROM interim_feedback WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $auteur = $st->fetchColumn();

        if ($auteur === false) {
            avisRetour('❌ Ce message n\'existe plus.');
        }
        // Le droit est vérifié ICI, côté serveur : l'absence du formulaire à
        // l'écran n'est pas une autorisation.
        if (!$estAdmin && (int) $auteur !== $moiId) {
            avisRetour('❌ Ce message ne te concerne pas.');
        }
        if ($texte === '') {
            avisRetour('❌ Le message est vide.');
        }

        $db->prepare(
            "INSERT INTO interim_feedback_replies (feedback_id, author_id, author_name, author_role, message)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$id, $moiId, $monNom, $roleMoi, $texte]);

        // Une réponse rouvre le sujet : y répondre puis le laisser « traité »
        // ferait disparaître la question de la liste de celui qui la relance.
        $db->prepare("UPDATE interim_feedback SET status = 'open', handled_at = NULL WHERE id = ?")
           ->execute([$id]);

        avisRetour('✅ Réponse envoyée.');
    }

    // ── CLORE / ROUVRIR (administrateurs) ────────────────────────────────
    if (($action === 'clore' || $action === 'rouvrir') && $estAdmin) {
        $id = (int) ($_POST['feedback_id'] ?? 0);
        if ($action === 'clore') {
            $db->prepare(
                "UPDATE interim_feedback SET status = 'resolved', handled_by_user_id = ?, handled_at = NOW()
                 WHERE id = ?"
            )->execute([$moiId, $id]);
            avisRetour('✅ Sujet marqué comme traité. Il reste consultable.');
        }
        $db->prepare("UPDATE interim_feedback SET status = 'open', handled_at = NULL WHERE id = ?")
           ->execute([$id]);
        avisRetour('↩️ Sujet rouvert.');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LECTURE
//
// ⚠️ UN NON-ADMIN NE VOIT QUE SES PROPRES MESSAGES, et la condition est dans le
// SQL. Une boîte à idées où chacun lit les remarques des autres n'en recueille
// plus : les gens s'y censurent.
// ─────────────────────────────────────────────────────────────────────────────
$filtre = (string) ($_GET['etat'] ?? 'open');
if (!in_array($filtre, ['open', 'resolved', 'all'], true)) { $filtre = 'open'; }

$messages = [];
$aTraiter = 0;
if ($tablesOk) {
    try {
        $sql = 'SELECT * FROM interim_feedback';
        $ou = [];
        $params = [];
        if (!$estAdmin) {
            $ou[] = 'author_id = ?';
            $params[] = $moiId;
        } elseif ($filtre !== 'all') {
            $ou[] = 'status = ?';
            $params[] = $filtre;
        }
        if ($ou) { $sql .= ' WHERE ' . implode(' AND ', $ou); }
        $sql .= ' ORDER BY created_at DESC LIMIT 200';

        $st = $db->prepare($sql);
        $st->execute($params);
        $messages = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($estAdmin) {
            $aTraiter = (int) $db->query("SELECT COUNT(*) FROM interim_feedback WHERE status = 'open'")->fetchColumn();
        }
    } catch (Exception $e) {
        $messages = [];
    }
}

// Les réponses, en UNE requête pour toute la page.
$reponses = [];
if ($messages) {
    try {
        $ids = array_map(static function ($m) { return (int) $m['id']; }, $messages);
        $trous = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare(
            "SELECT * FROM interim_feedback_replies WHERE feedback_id IN ($trous) ORDER BY created_at ASC, id ASC"
        );
        $st->execute($ids);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $reponses[(int) $r['feedback_id']][] = $r;
        }
    } catch (Exception $e) {
        $reponses = [];
    }
}

$quand = static function ($v) {
    $t = strtotime((string) $v);
    return $t ? date('d/m/Y à H\hi', $t) : '';
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Avis et suggestions - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 0 0 50px; color: #333; }
    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 8px 18px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .85rem; }
    .wrap { max-width: 820px; margin: 22px auto 0; padding: 0 16px; }

    .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; font-weight: 600; line-height: 1.55; background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; }
    .boite { background: #fff; border-radius: 16px; box-shadow: 0 6px 18px rgba(0,0,0,.07); margin-bottom: 16px; overflow: hidden; }
    .boite-tete { padding: 15px 20px; border-bottom: 1px solid #f0f4f1; }
    .boite-tete b { color: #2d5a37; font-size: 1.02rem; }
    .corps { padding: 16px 20px; }
    .quoi { color: #5a6b60; font-size: .89rem; line-height: 1.6; margin: 0 0 14px; }

    label { display: block; font-size: .78rem; font-weight: 700; color: #5a6b60; margin-bottom: 5px; }
    input[type="text"], select, textarea { width: 100%; padding: 10px 12px; border: 1px solid #cfd8d2; border-radius: 10px; font-family: inherit; font-size: .93rem; background: #fff; }
    textarea { min-height: 96px; resize: vertical; }
    .grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px; }
    .bouton { border: 0; border-radius: 30px; padding: 10px 22px; font-family: inherit; font-weight: 700; font-size: .88rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-vide { background: #eef3ef; color: #2d5a37; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 12px; }

    .onglets { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .onglet { background: #fff; border: 1px solid #dbe7de; border-radius: 999px; padding: 7px 16px; text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .84rem; }
    .onglet.actif { background: #2d5a37; color: #fff; border-color: #2d5a37; }

    .fil { padding: 16px 20px; border-top: 1px solid #f0f4f1; }
    .fil:first-child { border-top: 0; }
    .fil-tete { display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; margin-bottom: 8px; }
    .fil-tete .sujet { font-weight: 800; color: #244230; }
    .fil-tete .meta { color: #8a968f; font-size: .8rem; }
    .etat { border-radius: 999px; padding: 3px 11px; font-size: .72rem; font-weight: 800; }
    .etat.open { background: #fff3cd; color: #856404; }
    .etat.resolved { background: #e7f6ea; color: #1E7A46; }
    .texte { white-space: pre-wrap; line-height: 1.6; font-size: .93rem; }
    .reponse { margin-top: 10px; padding: 11px 14px; border-radius: 12px; background: #f5f8f6; border-left: 3px solid #4a8b5c; }
    .reponse.moi { background: #eef4ef; border-left-color: #b9cfc0; }
    .reponse .meta { color: #8a968f; font-size: .78rem; margin-bottom: 4px; }
    .rien { padding: 34px; text-align: center; color: #888; }
    details > summary { cursor: pointer; color: #2d5a37; font-weight: 700; font-size: .84rem; margin-top: 10px; }
</style>
</head>
<body>

<div class="bandeau">
    <h1>💬 Avis et suggestions</h1>
    <div><a class="pill" href="index.php">&larr; Accueil</a></div>
</div>

<div class="wrap">

    <?php if ($flash !== ''): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>

    <?php if (!$tablesOk): ?>
        <div class="boite"><div class="corps rien">Le module n'a pas pu être initialisé.</div></div>
    <?php else: ?>

    <div class="boite">
        <div class="boite-tete"><b>Dis-nous</b></div>
        <div class="corps">
            <p class="quoi">
                Une idée, une question, quelque chose qui ne va pas ? Écris-le ici.
                Un administrateur le lit et te répond au même endroit.
            </p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="deposer">
                <div class="grille">
                    <div>
                        <label for="category">De quoi s'agit-il ?</label>
                        <select id="category" name="category">
                            <?php foreach ($CATEGORIES as $val => $lib): ?>
                                <option value="<?= e($val) ?>"><?= e($lib) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="subject">En une ligne</label>
                        <input type="text" id="subject" name="subject" maxlength="180" required
                               placeholder="Ex. : le badge sort trop petit">
                    </div>
                </div>
                <label for="message">Explique</label>
                <textarea id="message" name="message" required placeholder="Ce qui se passe, ce que tu proposes…"></textarea>
                <div class="actions"><button type="submit" class="bouton bouton-plein">Envoyer</button></div>
            </form>
        </div>
    </div>

    <?php if ($estAdmin): ?>
        <div class="onglets">
            <a class="onglet <?= $filtre === 'open' ? 'actif' : '' ?>" href="?etat=open">
                À traiter<?= $aTraiter > 0 ? ' (' . (int) $aTraiter . ')' : '' ?>
            </a>
            <a class="onglet <?= $filtre === 'resolved' ? 'actif' : '' ?>" href="?etat=resolved">Traités</a>
            <a class="onglet <?= $filtre === 'all' ? 'actif' : '' ?>" href="?etat=all">Tous</a>
        </div>
    <?php endif; ?>

    <div class="boite">
        <div class="boite-tete">
            <b><?= $estAdmin ? 'Ce que les équipes disent' : 'Mes messages' ?></b>
        </div>

        <?php if (!$messages): ?>
            <div class="rien">
                <?= $estAdmin ? 'Rien dans cette liste.' : "Tu n'as encore rien envoyé." ?>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <?php
                    $id = (int) $m['id'];
                    $etat = (string) $m['status'];
                    $sien = ((int) $m['author_id'] === $moiId);
                ?>
                <div class="fil">
                    <div class="fil-tete">
                        <span class="sujet"><?= e((string) ($CATEGORIES[$m['category']] ?? '💬')) ?> <?= e((string) $m['subject']) ?></span>
                        <span class="etat <?= $etat === 'resolved' ? 'resolved' : 'open' ?>">
                            <?= $etat === 'resolved' ? 'Traité' : 'En attente' ?>
                        </span>
                        <span class="meta">
                            <?php if ($estAdmin && !$sien): ?><?= e((string) ($m['author_name'] ?? '')) ?> · <?php endif; ?>
                            <?= e($quand($m['created_at'])) ?>
                            <?php if (($m['source'] ?? '') !== ''): ?> · depuis <?= e((string) $m['source']) ?><?php endif; ?>
                        </span>
                    </div>
                    <div class="texte"><?= e((string) $m['message']) ?></div>

                    <?php foreach (($reponses[$id] ?? []) as $r): ?>
                        <div class="reponse <?= (int) $r['author_id'] === $moiId ? 'moi' : '' ?>">
                            <div class="meta"><?= e((string) ($r['author_name'] ?? '')) ?> · <?= e($quand($r['created_at'])) ?></div>
                            <div class="texte"><?= e((string) $r['message']) ?></div>
                        </div>
                    <?php endforeach; ?>

                    <details>
                        <summary>Répondre</summary>
                        <form method="POST" style="margin-top:8px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="repondre">
                            <input type="hidden" name="feedback_id" value="<?= $id ?>">
                            <textarea name="message" required placeholder="Ta réponse…"></textarea>
                            <div class="actions"><button type="submit" class="bouton bouton-plein">Envoyer</button></div>
                        </form>
                    </details>

                    <?php if ($estAdmin): ?>
                        <form method="POST" style="margin-top:8px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="<?= $etat === 'resolved' ? 'rouvrir' : 'clore' ?>">
                            <input type="hidden" name="feedback_id" value="<?= $id ?>">
                            <button type="submit" class="bouton bouton-vide">
                                <?= $etat === 'resolved' ? '↩️ Rouvrir' : '✅ Marquer comme traité' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>
</body>
</html>
