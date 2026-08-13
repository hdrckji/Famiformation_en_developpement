<?php
// ============================================================
// recap.php — « VOICI CE QU'ON SAIT DE TOI. C'EST JUSTE ? »
//
// L'écran que voit un collaborateur à sa PREMIÈRE CONNEXION, sur n'importe
// quelle plateforme, puis une fois par an. C'est le seul moment où quelqu'un
// relit vraiment sa fiche : ni l'admin qui l'a créée, ni personne d'autre ne
// peut savoir que la ville a changé ou que le prénom est mal orthographié.
//
// TROIS RÉPONSES POSSIBLES, et une seule est un cul-de-sac (aucune) :
//   • « tout est juste »            → validé, on passe
//   • « il y a une erreur »         → vers sa fiche, où il corrige lui-même ;
//                                     l'admin voit ensuite la correction
//                                     (validations.php, mécanique existante)
//   • un mot pour l'administrateur  → remonté tel quel, marqué non lu
//
// ⚠️ RIEN N'EST BLOQUANT. « Plus tard » est toujours possible, et ce n'est pas
// une faiblesse : une plateforme qui prend quelqu'un en otage pour une photo se
// fait contourner, pas obéir. Ce qui manque est rappelé ensuite par un bandeau,
// sur toutes les plateformes (voir includes/validation.php).
//
// ⚠️ LA PHOTO NE SE FORCE PAS. Elle relève du consentement (décision de Jimmy,
// et c'est le droit) : d'où un bouton « je ne souhaite pas mettre de photo »,
// dont le refus est enregistré et daté. L'EMAIL, lui, fait fonctionner le
// compte — on insiste, sans bloquer davantage.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/modifications.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/photo.php';

$moi = famicardExigeConnexion($db);
$moiId = (int) $moi['id'];

famicardAssureValidation($db);
famicardAssureModifications($db);

// Où renvoyer une fois validé : la plateforme d'où l'on vient.
//
// ⚠️ On n'accepte QU'UN CHEMIN RELATIF, ou une adresse de la maison. Sans ce
// filtre, un lien « recap.php?retour=https://ailleurs » renverrait quelqu'un
// hors du site depuis une page de Famiflora — c'est exactement ce qu'on ne veut
// pas offrir. Le saut de ligne est refusé aussi : il permet d'injecter un
// second en-tête dans la redirection.
$retour = (string) ($_GET['retour'] ?? $_POST['retour'] ?? '');
$retourOk = ($retour !== '' && strpos($retour, "\n") === false && strpos($retour, "\r") === false);
if ($retourOk && preg_match('~^(https?:)?//~i', $retour)) {
    // Une adresse complète : elle doit rester chez nous. FamiJob en envoie une
    // depuis son sous-domaine, où le relatif ne veut plus rien dire.
    // strlen() plutôt qu'un nombre écrit à la main : « -18 » compté de tête est
    // faux une fois sur deux, et l'erreur ne se voit pas — le filtre refuse
    // simplement tout, et l'on retombe sur l'accueil sans comprendre pourquoi.
    $hote = strtolower((string) parse_url($retour, PHP_URL_HOST));
    $suffixe = '.famiformation.com';
    $retourOk = ($hote === 'famiformation.com'
              || substr($hote, -strlen($suffixe)) === $suffixe);
}
if (!$retourOk) {
    $retour = 'index.php';
}

$champs    = famicardChamps($db);
$magasins  = famicardMagasins($db);
$groupes   = famicardGroupes();
$flash     = '';
$erreurs   = [];

// ─────────────────────────────────────────────────────────────────────────────
// ACTIONS
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();
    $action = (string) ($_POST['action'] ?? '');

    // ── LA PHOTO ─────────────────────────────────────────────────────────
    if ($action === 'photo' && isset($_FILES['photo_profil'])) {
        $erreurPhoto = '';
        $chemin = famicardEnregistrePhoto(
            $db, $moiId, $_FILES['photo_profil'],
            (string) ($moi['photo_profil'] ?? ''), $erreurPhoto
        );
        if ($erreurPhoto !== '') {
            $erreurs[] = $erreurPhoto;
        } elseif ($chemin !== '') {
            // Le ruban du site lit la photo dans la session : sans ça,
            // l'ancienne resterait affichée jusqu'à la reconnexion.
            $_SESSION['photo_profil'] = $chemin;
            $moi['photo_profil'] = $chemin;
            // Déposer sa photo annule un refus précédent : c'est un
            // consentement qui revient, il doit s'exprimer aussi simplement
            // qu'il s'est retiré.
            famicardRefusePhoto($db, $moiId, false);
            famicardTraceModification($db, $moiId, 'photo_profil', ['libelle' => 'Photo'], '', 'photo', $moiId, false);
            $flash = '✅ Merci, ta photo est enregistrée.';
        }
    }

    // ── LE REFUS DE PHOTO ────────────────────────────────────────────────
    if ($action === 'refus_photo') {
        famicardRefusePhoto($db, $moiId, true);
        $flash = "C'est noté : on ne te redemandera plus de photo. Tu peux changer d'avis quand tu veux.";
    }

    // ── L'EMAIL ──────────────────────────────────────────────────────────
    if ($action === 'email') {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = "Cette adresse email n'est pas valide.";
        } else {
            // Le site refuse deux comptes sur la même adresse : sans ce test,
            // on créerait ici le doublon qu'il interdit ailleurs.
            $q = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id != ?");
            $q->execute([$email, $moiId]);
            if ((int) $q->fetchColumn() > 0) {
                $erreurs[] = 'Cette adresse est déjà utilisée par un autre compte.';
            } else {
                $avant = (string) ($moi['email'] ?? '');
                $db->prepare("UPDATE utilisateurs SET email = ? WHERE id = ?")->execute([$email, $moiId]);
                $moi['email'] = $email;
                // Tracé comme n'importe quelle correction : l'administrateur
                // le verra passer dans validations.php.
                famicardTraceModification($db, $moiId, 'email', $champs['email'] ?? ['libelle' => 'Email'], $avant, $email, $moiId, true);
                $flash = '✅ Merci, ton adresse est enregistrée.';
            }
        }
    }

    // ── LA VALIDATION ────────────────────────────────────────────────────
    if ($action === 'valider') {
        $commentaire = trim((string) ($_POST['commentaire'] ?? ''));
        famicardEnregistreValidationFiche($db, $moiId, $commentaire);
        $_SESSION['famicard_recap_flash'] = $commentaire !== ''
            ? '✅ Merci ! Ta fiche est confirmée, et ton message est transmis à l\'administration.'
            : '✅ Merci ! Ta fiche est confirmée.';
        header('Location: ' . $retour);
        exit();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉTAT DE LA FICHE
// ─────────────────────────────────────────────────────────────────────────────
$moi = famicardAjouteRattachement(
    $moi,
    famicardRattachementsRh($db, [$moiId]),
    famicardPlacements($db, [$moiId])
);
$libres     = famicardValeursLibres($db, $moiId);
$validation = famicardValidation($db, $moiId);
$manques    = famicardManquesPersonnels($moi, $validation);
$premiere   = empty($validation['valide_le']);

$photo = (string) ($moi['photo_profil'] ?? '');
$photoUrl = '';
if ($photo !== '') {
    $photoUrl = function_exists('moduleFileUrl') ? moduleFileUrl($photo) : $photo;
    if ($photoUrl !== '' && !preg_match('#^(https?:)?//#i', $photoUrl)) {
        $photoUrl = famicardSiteUrl($photoUrl);
    }
    $photoUrl .= (strpos($photoUrl, '?') === false ? '?' : '&') . 'v=' . time();
}

$prenom = trim((string) ($moi['prenom'] ?? '')) ?: (string) ($moi['identifiant'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes informations - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: #eef4ef; margin: 0; padding: 24px 16px 60px; color: #244230; }
    .wrap { max-width: 720px; margin: 0 auto; }
    h1 { color: #2d5a37; font-size: 1.5rem; margin: 0 0 6px; }
    .sous { color: #5a6b60; margin: 0 0 20px; line-height: 1.6; }
    .card { background: #fff; border-radius: 18px; padding: 22px; margin-bottom: 16px; box-shadow: 0 6px 20px rgba(14,59,36,.08); border: 1px solid #e6efe8; }
    .card h2 { margin: 0 0 4px; font-size: 1.05rem; color: #2d5a37; }
    .card .quoi { color: #5a6b60; font-size: .89rem; line-height: 1.55; margin: 0 0 14px; }
    .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 600; line-height: 1.55; background: #e7f6ea; border: 1px solid #b7e0c1; color: #1E7A46; }
    .err { background: #fdeaea; border-color: #f3c2c2; color: #a3271c; }

    .ligne { display: flex; gap: 14px; padding: 9px 0; border-bottom: 1px solid #f0f4f1; font-size: .93rem; }
    .ligne:last-child { border-bottom: 0; }
    .ligne .quoi-libelle { flex: 0 0 42%; color: #6a7d72; }
    .ligne .valeur { flex: 1; font-weight: 600; }
    .ligne .vide { color: #b8b8b8; font-style: italic; font-weight: 400; }
    .groupe-titre { font-size: .76rem; text-transform: uppercase; letter-spacing: .07em; color: #2d5a37; font-weight: 800; margin: 18px 0 2px; }
    .groupe-titre:first-child { margin-top: 0; }

    .manque { background: #fff8e1; border: 1px solid #ffe082; }
    .manque h2 { color: #8a5a10; }
    .avatar { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 4px solid #2d5a37; background: #e8f5e9; display: block; margin-bottom: 12px; }
    .avatar-vide { display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: #2d5a37; border-style: dashed; }
    input[type="email"], input[type="file"], textarea { width: 100%; padding: 10px 12px; border: 1px solid #cfe0d4; border-radius: 10px; font-family: inherit; font-size: .95rem; background: #fff; }
    textarea { min-height: 82px; resize: vertical; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 14px; }
    .btn { display: inline-block; border: none; cursor: pointer; background: #2d5a37; color: #fff; font-weight: 700; padding: 11px 22px; border-radius: 999px; text-decoration: none; font-size: .95rem; font-family: inherit; }
    .btn.ghost { background: #fff; color: #2d5a37; border: 2px solid #2d5a37; }
    .btn.discret { background: none; color: #6a7d72; text-decoration: underline; padding: 11px 4px; font-weight: 600; font-size: .88rem; }
    .final { position: sticky; bottom: 0; background: #eef4ef; padding: 14px 0 4px; }
</style>
</head>
<body>
<div class="wrap">

    <h1>Bonjour <?= e($prenom) ?> 👋</h1>
    <p class="sous">
        <?php if ($premiere): ?>
            Voici ce que Famiflora sait de toi. <b>Prends un instant pour vérifier</b> :
            c'est avec ces informations qu'on te contacte et qu'on t'organise.
        <?php else: ?>
            Cela fait un an que tu n'as pas relu ta fiche. <b>Est-elle toujours juste ?</b>
        <?php endif; ?>
    </p>

    <?php if ($flash !== ''): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($erreurs): ?>
        <div class="flash err">
            <?php foreach (array_unique($erreurs) as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php // ── CE QUI MANQUE, EN PREMIER ────────────────────────────────────
          // Avant le récapitulatif : ce qu'on demande passe avant ce qu'on
          // montre, sinon on le lit après avoir décidé de valider. ?>

    <?php if ($manques['email']): ?>
        <div class="card manque">
            <h2>📧 Il nous manque ton adresse email</h2>
            <p class="quoi">
                C'est par là que passent ton lien de connexion et la récupération de ton mot de passe.
                Sans elle, il faut passer par quelqu'un à chaque fois.
            </p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="email">
                <input type="hidden" name="retour" value="<?= e($retour) ?>">
                <input type="email" name="email" placeholder="prenom.nom@exemple.be" required>
                <div class="actions"><button type="submit" class="btn">Enregistrer mon adresse</button></div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($manques['photo']): ?>
        <div class="card manque">
            <h2>📷 Une photo pour ta carte ?</h2>
            <p class="quoi">
                Elle sert à te reconnaître sur ta carte Famiflora. <b>Elle est facultative</b> :
                tu as le droit de refuser, et on ne te le redemandera pas.
            </p>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="photo">
                <input type="hidden" name="retour" value="<?= e($retour) ?>">
                <input type="file" name="photo_profil" accept="image/jpeg,image/png,image/gif,image/webp" required>
                <div class="actions">
                    <button type="submit" class="btn">Envoyer ma photo</button>
                </div>
            </form>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="refus_photo">
                <input type="hidden" name="retour" value="<?= e($retour) ?>">
                <button type="submit" class="btn discret">Je ne souhaite pas mettre de photo</button>
            </form>
        </div>
    <?php elseif (!empty($validation['photo_refus_le'])): ?>
        <div class="card">
            <h2>📷 Photo</h2>
            <p class="quoi" style="margin:0;">
                Tu as choisi de ne pas mettre de photo, et c'est respecté — on ne te le redemande plus.
                Si tu changes d'avis, tu peux en déposer une depuis <a href="modifier.php">ta fiche</a>.
            </p>
        </div>
    <?php endif; ?>

    <?php // ── LE RÉCAPITULATIF ─────────────────────────────────────────────
          // On n'affiche QUE ce que la personne a le droit de voir sur sa
          // propre fiche : famicardPeutVoir tranche, pas cet écran. ?>
    <div class="card">
        <h2>Ce qu'on sait de toi</h2>
        <p class="quoi">Si quelque chose est faux, tu peux le corriger toi-même juste en dessous.</p>

        <?php if ($photoUrl !== ''): ?>
            <img class="avatar" src="<?= e($photoUrl) ?>" alt="">
        <?php endif; ?>

        <?php foreach ($groupes as $cleGroupe => $groupe): ?>
            <?php
            $lignes = [];
            foreach ($champs as $cle => $champ) {
                if (($champ['groupe'] ?? '') !== $cleGroupe) { continue; }
                if (($champ['saisie'] ?? '') === 'photo') { continue; }
                if (!famicardPeutVoir($champ, false, true)) { continue; }
                $lignes[$cle] = $champ;
            }
            if (!$lignes) { continue; }
            ?>
            <div class="groupe-titre"><?= e($groupe['libelle']) ?></div>
            <?php foreach ($lignes as $cle => $champ): ?>
                <?php $valeur = famicardValeurAffichee($cle, $champ, $moi, $magasins, $libres); ?>
                <div class="ligne">
                    <div class="quoi-libelle"><?= e($champ['libelle']) ?></div>
                    <div class="valeur"><?= $valeur !== '' ? e($valeur) : '<span class="vide">non renseigné</span>' ?></div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <div class="actions">
            <a class="btn ghost" href="modifier.php">✏️ Corriger une information</a>
        </div>
    </div>

    <?php // ── LA VALIDATION ────────────────────────────────────────────────
          // Le mot est FACULTATIF : imposer une justification pour valider,
          // c'est se garantir des « rien à signaler » tapés au hasard. ?>
    <div class="card">
        <h2>C'est bon pour toi ?</h2>
        <p class="quoi">
            Tu peux laisser un mot à l'administration — une information qu'on n'a pas,
            quelque chose que tu ne peux pas corriger toi-même. Ce n'est pas obligatoire.
        </p>
        <form method="POST" class="final">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="valider">
            <input type="hidden" name="retour" value="<?= e($retour) ?>">
            <textarea name="commentaire" placeholder="Un mot pour l'administration (facultatif)"></textarea>
            <div class="actions">
                <button type="submit" class="btn">✅ Tout est juste, je valide</button>
                <a class="btn discret" href="<?= e($retour) ?>">Plus tard</a>
            </div>
        </form>
    </div>

</div>
</body>
</html>
