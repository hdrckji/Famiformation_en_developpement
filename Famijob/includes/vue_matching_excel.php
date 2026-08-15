<!DOCTYPE html>
<html lang="<?php echo e($pageLang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e(fjhT('Matching intérim', 'Matching interim')); ?></title>
<link rel="shortcut icon" type="image/x-icon" href="famijob_.ico">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: #eef3ef; margin: 0; padding: 0 0 40px; color: #222; }

    .bandeau { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
    .bandeau h1 { margin: 0; font-size: 1.15rem; font-weight: 800; }
    .pill { background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.45); padding: 7px 16px; border-radius: 30px; text-decoration: none; color: #fff; font-weight: 700; font-size: .82rem; }
    .barre { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 12px 20px; background: #fff; border-bottom: 1px solid #dde5e0; }
    .barre select, .barre button { font-family: inherit; font-size: .88rem; padding: 7px 12px; border-radius: 8px; border: 1px solid #ccd6cf; background: #fff; }
    .barre button { background: #2d5a37; color: #fff; border: 0; font-weight: 700; cursor: pointer; }
    .alert { margin: 12px 20px; padding: 11px 16px; border-radius: 10px; font-weight: 600; font-size: .9rem; }
    .alert.success { background: #e7f6ea; color: #1e7a46; }
    .alert.error { background: #fdecea; color: #a3271c; }

    /* ── LA GRILLE ───────────────────────────────────────────────────────
       Reprise du classeur : une colonne par jour, chacune découpée en
       horaire / nom / agence.

       ⚠️ ELLE TIENT DANS LA PAGE, et ça a demandé de renoncer à trois choses.
       Avant : « width: max-content », « white-space: nowrap » et des
       min-width sur les colonnes nom (130 px) et agence (62 px). Vingt et une
       colonnes qui refusent de se replier, ça fait un tableau de deux mètres
       qu'on lit en poussant une barre de défilement — et une semaine qu'on ne
       voit jamais entière, ce qui est pourtant tout l'intérêt du classeur.

       Maintenant : « table-layout: fixed » avec des largeurs déclarées en
       pourcentage (voir le <colgroup>), et le texte qui passe à la ligne. Un
       nom long tient sur deux lignes au lieu d'élargir sa colonne pour les
       sept jours.

       Le min-width de 900 px garde le défilement sur un écran étroit : en
       dessous, sept jours ne rentrent de toute façon pas, et des colonnes de
       30 px seraient pires qu'une barre de défilement. */
    .cadre { overflow-x: auto; padding: 0 12px; }
    table.semaine { border-collapse: collapse; font-size: .7rem; table-layout: fixed;
        width: 100%; min-width: 900px; }
    table.semaine th, table.semaine td { border: 1px solid #c8d3cc; padding: 2px 4px;
        white-space: normal; overflow-wrap: anywhere; }

    .jour-tete { background: #2d5a37; color: #fff; font-size: .78rem; font-weight: 800; text-align: center; padding: 6px 4px; }
    .sous-tete { background: #f0f4f1; color: #55665c; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; text-align: center; }

    /* Les couleurs du classeur : secteur en vert, département en jaune. */
    .l-secteur td, .l-departement td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .l-secteur td { background: #7ed321; color: #1d3d12; font-weight: 800; text-align: center; font-size: .78rem; padding: 3px; }
    .l-departement td { background: #ffff66; color: #4a4a00; font-weight: 700; text-align: center; font-size: .74rem; padding: 2px; }

    td.horaire { background: #fbfdfb; color: #33443a; text-align: center; font-variant-numeric: tabular-nums;
        font-size: .68rem; line-height: 1.25; }
    /* Plus de min-width : c'est le <colgroup> qui repartit la largeur, et une
       min-width aurait repris la main sur lui. */
    td.nom { line-height: 1.25; }
    td.agence { color: #55665c; text-align: center; font-size: .64rem; line-height: 1.2; }
    /* SÉPARATION DES JOURS. Une bordure fine se noyait dans le quadrillage :
       sur sept colonnes triples, l'oeil perdait la limite entre mardi et
       mercredi. D'où une barre épaisse ET un fond alterné — deux repères
       valent mieux qu'un quand le tableau est dense. */
    th.fin-jour, td.fin-jour { border-right: 6px solid #1d3d24 !important; }
    td.jour-pair, th.jour-pair { background: #e8efe9; }
    td.jour-pair.horaire { background: #dfe8e1; }
    td.vide-jour { background: #f7f9f8; }

    .place-libre { display: block; width: 100%; border: 1px dashed #b9cfc0; background: #fff; color: #2d5a37; border-radius: 5px; padding: 2px 4px; font-family: inherit; font-size: .66rem; font-weight: 700; cursor: pointer; text-align: center; overflow-wrap: anywhere; }
    .place-libre:hover { background: #eef7f0; border-color: #2d5a37; }
    /* La meme place, planning arrete : toujours lisible comme « non pourvue »,
       mais sans le pointille ni le « + » qui invitent a cliquer. */
    .place-fermee { display: block; text-align: center; color: #9aa8a0; font-size: .66rem;
        font-style: italic; padding: 2px 4px; }
    .occupe { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
    /* Place prise dont le nom ne nous regarde pas. Elle doit se distinguer AU
       PREMIER COUP D'OEIL d'une place libre : c'est toute la difference entre
       « je peux proposer quelqu'un » et « c'est deja pourvu ». Fond plein, pas
       de pointille — le pointille dit « vide » partout ailleurs dans ce
       tableau. */
    .occupe-masque { display: block; background: #e3e8ea; color: #55636b; border-radius: 5px;
        padding: 2px 6px; font-size: .68rem; font-weight: 700; font-style: italic; text-align: center; }
    .retirer { border: 0; background: none; color: #b23; cursor: pointer; font-size: .78rem; padding: 0 2px; line-height: 1; }
    .retirer:hover { color: #7d1616; }

    .rien { padding: 30px 20px; text-align: center; color: #667; }

    /* Une case horaire qui porte un mot de l'agence. Ambre plein et souligne
       pointille : ca ne ressemble a rien d'autre dans ce tableau, et c'est le
       but — un commentaire qu'on ne repere pas n'existe pas. */
    .horaire.a-mot { background: #fff3d6 !important; color: #8a5a00; cursor: pointer;
        text-decoration: underline dotted #d19a00; font-weight: 800; }
    .horaire.a-mot::after { content: ' 💬'; font-size: .7em; }
    .horaire.a-mot:hover { background: #ffe9b3 !important; }

    /* Les deux pastilles d'action se distinguent de la navigation : l'une sort
       un fichier, l'autre envoie un message. Ce ne sont pas des liens vers une
       page de plus. */
    .bandeau-nav { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    /* La sortie ne se confond pas avec la navigation : c'est la seule pastille
       qui ne mene pas a un autre ecran du meme travail. */
    .pill-sortie { background: rgba(0,0,0,.18); border-color: rgba(255,255,255,.3); }
    .pill-sortie:hover { background: rgba(0,0,0,.3); }

    /* ── LES DEUX ACTIONS DE LA BARRE BLANCHE ─────────────────────────────
       Meme hauteur que les menus deroulants a cote (34 px) : deux boutons qui
       depassent d'un cheveu se remarquent plus que s'ils etaient franchement
       differents. Meme forme, meme graisse, seule la couleur separe l'action
       principale de la secondaire. */
    .barre-actions { margin-left: auto; display: flex; gap: 8px; align-items: center; }
    /* « .barre .act » et non « .act » : la regle generique « .barre button »
       plus haut est plus specifique qu'une simple classe, et reprenait la main
       sur le rembourrage et la bordure du bouton d'auto-matching. */
    .barre .act { display: inline-flex; align-items: center; gap: 7px; padding: 7px 15px;
        border-radius: 8px; text-decoration: none; font-weight: 700; font-size: .84rem; line-height: 1.35;
        white-space: nowrap; border: 1px solid transparent; transition: background .15s, box-shadow .15s; }
    .barre .act-ic { font-size: .95em; line-height: 1; }
    .barre .act-export { background: #1f7a3d; color: #fff; box-shadow: 0 2px 6px rgba(31,122,61,.28); }
    .barre .act-export:hover { background: #19632f; box-shadow: 0 4px 10px rgba(31,122,61,.34); }
    .barre .act-avis { background: #fff; color: #8a5a00; border-color: #e8d3a6; }
    .barre .act-avis:hover { background: #fff8ec; border-color: #d8b976; }
    /* La bascule de vue prend la meme forme : trois boutons de trois formes
       differentes dans la meme barre, c'est ce qui faisait desordre. */
    .barre .act-vue { background: #fff; color: #2d5a37; border-color: #cfdad3; }
    .barre .act-vue:hover { background: #f2f8f4; border-color: #2d5a37; }
    .barre .act-auto { background: #2d5a37; color: #fff; cursor: pointer; font-family: inherit; }
    .barre .act-auto:hover { background: #24492c; }
    /* Valider est l'action la plus lourde de l'ecran : elle envoie des mails a
       des dizaines de personnes. Elle se distingue donc de l'auto-matching, qui
       ne fait que remplir des cases. */
    .barre .act-valider { background: #1f7a3d; color: #fff; cursor: pointer; font-family: inherit;
        box-shadow: 0 2px 6px rgba(31,122,61,.28); }
    .barre .act-valider:hover { background: #19632f; }
    /* Rouvrir n'est pas une action anodine mais ce n'est pas un envoi : ton plus
       sobre que « Valider », pour qu'on ne confonde pas les deux au clic. */
    .barre .act-modifier { background: #fff; color: #8a5a00; border-color: #e8d3a6;
        cursor: pointer; font-family: inherit; }
    .barre .act-modifier:hover { background: #fff8ec; border-color: #d8b976; }
    .etat-semaine { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;
        border-radius: 999px; font-size: .78rem; font-weight: 800; white-space: nowrap; }
    .etat-prepa { background: #fff3d6; color: #8a5a00; border: 1px solid #f0d5a8; }
    .etat-valide { background: #e7f6ea; color: #1d6a39; border: 1px solid #b7e0c1; }

    /* Sur un ecran etroit, les actions passent sous les filtres et s'etalent
       plutot que de se tasser dans un coin. */
    @media (max-width: 900px) {
        .barre-actions { margin-left: 0; width: 100%; }
        .barre-actions .act { flex: 1; justify-content: center; }
    }

    /* ── FENÊTRE D'AFFECTATION ───────────────────────────────────────────
       UNE seule liste d'étudiants pour toute la page. Un menu déroulant par
       case, sur 7 jours et des dizaines de départements, aurait produit un
       document de plusieurs mégaoctets. */
    .voile { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: none; align-items: center; justify-content: center; z-index: 50; padding: 16px; }
    .voile.ouvert { display: flex; }
    .fenetre { background: #fff; border-radius: 16px; padding: 22px 24px; width: 100%; max-width: 430px; box-shadow: 0 20px 50px rgba(0,0,0,.3); }
    .fenetre h2 { margin: 0 0 4px; font-size: 1.05rem; color: #2d5a37; }
    .fenetre .ou { color: #667; font-size: .84rem; margin-bottom: 16px; }
    .fenetre label { display: block; font-weight: 700; font-size: .8rem; margin-bottom: 5px; color: #444; }
    .fenetre select, .fenetre input[type="text"] { width: 100%; padding: 9px 11px; border: 1px solid #ccd6cf; border-radius: 9px; font-family: inherit; font-size: .9rem; margin-bottom: 13px; }
    .fenetre .actions { display: flex; gap: 9px; justify-content: flex-end; }
    .btn { border: 0; border-radius: 22px; padding: 9px 18px; font-family: inherit; font-weight: 700; font-size: .85rem; cursor: pointer; }
    .btn-ok { background: #2d5a37; color: #fff; }
    .btn-non { background: #eef2ef; color: #445; }
</style>
</head>
<body>

<div class="bandeau">
    <h1><?php echo e(fjhT('Matching intérim — la semaine', 'Matching interim — de week')); ?></h1>
    <?php // Le bandeau vert ne porte QUE la navigation, et seulement pour
          // Famiflora : une agence n'a acces ni aux demandes ni a l'accueil,
          // ces deux pastilles ne menaient chez elle qu'a une redirection.
          //
          // Exporter et ecrire a l'equipe sont des ACTIONS : elles sont dans la
          // barre blanche, avec les filtres, calees a droite au-dessus de
          // dimanche. Les mettre ici les noyait dans le degrade vert — un
          // bouton vert sur fond vert ne se voit pas. ?>
    <div class="bandeau-nav">
        <?php // « Demandes » est parti : l'accueil FamiJob y mene deja, et une
              // pastille de plus dans un bandeau n'est pas une navigation, c'est
              // un raccourci de plus a lire.
              //
              // « Accueil » sans la fleche : elle disait « retour », alors que
              // c'est un aller vers un autre ecran.
              //
              // Rien de tout ca pour une agence : elle n'a pas d'accueil. ?>
        <?php if (!famijobEstCompteAgence($role)): ?>
            <a class="pill" href="index.php"><?php echo e(fjhT('Accueil', 'Onthaal')); ?></a>
        <?php endif; ?>

        <?php // ⚠️ LA DECONNEXION MANQUAIT A TOUT LE MONDE ICI. La vue detaillee
              // porte le ruban FamiJob, qui en contient une ; ce classeur, non.
              // Un admin s'en sortait en passant par l'accueil — une agence,
              // elle, n'a pas d'accueil : cette page etait la seule qu'elle
              // voyait, et il n'y avait aucun moyen d'en sortir. ?>
        <a class="pill pill-sortie" href="logout.php">
            ⏻ <?php echo e(fjhT('Se déconnecter', 'Uitloggen')); ?>
        </a>
    </div>
</div>

<div class="barre">
    <?php // Semaine, secteur et departement dans le MEME formulaire : changer
          // l'un doit conserver les autres. Trois formulaires separes se
          // seraient effaces mutuellement a chaque selection. ?>
    <form method="GET" style="display:flex; gap:9px; align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="affichage" value="excel">

        <label for="week" style="font-weight:700; font-size:.85rem;"><?php echo e(fjhT('Semaine', 'Week')); ?></label>
        <select name="week" id="week" onchange="this.form.submit()">
            <?php foreach ($weekOptions as $key => $option): ?>
                <option value="<?php echo e($key); ?>" <?php echo $key === $selectedWeekKey ? 'selected' : ''; ?>>
                    <?php echo e($option['label'] ?? $key); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="m_secteur" style="font-weight:700; font-size:.85rem;"><?php echo e(fjhT('Secteur', 'Sector')); ?></label>
        <?php // Le menu des departements n'existe QUE si un secteur est choisi :
              // on ne le remet a zero que s'il est la, sinon l'erreur JavaScript
              // emporte l'envoi avec elle. ?>
        <select name="m_secteur" id="m_secteur"
                onchange="if (this.form.m_dept) { this.form.m_dept.value = ''; } this.form.submit();">
            <option value=""><?php echo e(fjhT('Tous', 'Alle')); ?></option>
            <?php foreach ($ordreSecteurs as $nomSec): ?>
                <?php if ($nomSec === fjhT('Sans secteur', 'Zonder sector')) { continue; } ?>
                <option value="<?php echo e($nomSec); ?>" <?php echo $mSecteur === $nomSec ? 'selected' : ''; ?>><?php echo e($nomSec); ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($mDeptsProposes): ?>
            <label for="m_dept" style="font-weight:700; font-size:.85rem;"><?php echo e(fjhT('Département', 'Afdeling')); ?></label>
            <select name="m_dept" id="m_dept" onchange="this.form.submit()">
                <option value=""><?php echo e(fjhT('Tout le secteur', 'Hele sector')); ?></option>
                <?php foreach ($mDeptsProposes as $nomDep): ?>
                    <option value="<?php echo e($nomDep); ?>" <?php echo $mDept === $nomDep ? 'selected' : ''; ?>><?php echo e($nomDep); ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </form>

    <?php // Changer de vue sans perdre la semaine ni le filtre.
          // Pas propose aux agences : la vue detaillee ne leur est pas ouverte,
          // un bouton qui ramene sur place est pire que pas de bouton. ?>
    <?php if ($peutChangerDeVue): ?>
        <a class="act act-vue" href="<?php echo e($lienVue('liste')); ?>">
            <span class="act-ic">☰</span><?php echo e(fjhT('Vue détaillée', 'Gedetailleerde weergave')); ?>
        </a>
    <?php endif; ?>

    <?php // L'auto-matching existe toujours dans le traitement : sans ce bouton,
          // la fonction serait devenue inatteignable en changeant d'écran. ?>
    <?php if ($isAdmin && !$planningVerrouille): ?>
    <form method="POST" onsubmit="return confirm('<?php echo e(fjhT('Lancer l\'auto-matching sur toute la semaine ?', 'Auto-matching voor de hele week starten?')); ?>');">
        <?php echo csrfField(); ?>
        <input type="hidden" name="week" value="<?php echo e($selectedWeekKey); ?>">
        <button type="submit" name="auto_match_week" value="1" class="act act-auto">
            <span class="act-ic">⚡</span><?php echo e(fjhT('Auto-matching de la semaine', 'Auto-matching van de week')); ?>
        </button>
    </form>
    <?php endif; ?>

    <?php // ── TOUT A DROITE : L'ETAT DE LA SEMAINE, PUIS LES ACTIONS ───────
          // Un seul bloc, cale au bout de la barre par « margin-left:auto » —
          // les filtres s'allongent ou raccourcissent selon qu'un secteur est
          // choisi, une position fixe aurait fait danser les boutons d'un ecran
          // a l'autre. Regroupes pour passer a la ligne ENSEMBLE sur un ecran
          // etroit, au lieu de se separer.
          //
          // ⚠️ LA PASTILLE D'ETAT EST POUR TOUT LE MONDE, AGENCES COMPRISES.
          // C'est l'information la plus utile de la page pour un fournisseur :
          // « en préparation » veut dire que ce qu'il lit peut encore bouger et
          // qu'aucun mail n'est parti ; « validé » veut dire que c'est arrete et
          // que ses gens ont ete prevenus. Sans elle, une agence ne peut pas
          // savoir si le tableau qu'elle regarde est un brouillon.
          //
          // Le BOUTON, lui, reste aux admins : une agence ne decide pas que la
          // semaine de Famiflora est finie. ?>
    <?php
    $etatValide = ($etatSemaine['statut'] === 'valide');
    if ($etatValide) {
        $infoEtat = 'Planning arrêté';
        if ($etatSemaine['le'] !== '') {
            $infoEtat .= ' le ' . date('d/m/Y à H:i', strtotime($etatSemaine['le']));
        }
        if ($isAdmin) {
            if ($etatSemaine['par'] !== '') { $infoEtat .= ' par ' . $etatSemaine['par']; }
            $infoEtat .= ' — ' . $etatSemaine['envois_ok'] . ' envoi(s) partis';
            if ($etatSemaine['envois_ko'] > 0) { $infoEtat .= ', ' . $etatSemaine['envois_ko'] . ' en échec'; }
        } else {
            $infoEtat .= ' — les personnes concernées ont été prévenues';
        }
    } else {
        $infoEtat = 'Le planning de cette semaine peut encore changer. Aucun horaire n\'a été envoyé.';
    }
    ?>
    <div class="barre-actions">
        <span class="etat-semaine etat-<?php echo $etatValide ? 'valide' : 'prepa'; ?>"
              title="<?php echo e($infoEtat); ?>">
            <?php echo $etatValide ? '✔' : '✎'; ?>
            <?php echo e(famijobLibelleStatutSemaine($etatSemaine['statut'])); ?>
        </span>

        <?php if ($isAdmin): ?>
            <?php // UN SEUL BOUTON, DEUX SENS. Valide, il devient « Modifier » —
                  // c'est le meme geste dans l'autre sens, et le mettre au meme
                  // endroit evite de chercher.
                  //
                  // Confirmation explicite dans les deux cas : on n'envoie pas
                  // des horaires a toute une semaine d'etudiants, et on ne
                  // rouvre pas un planning deja parti, par un clic distrait. Le
                  // texte annonce ce qui va SE PASSER. ?>
            <?php if ($etatValide): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo e(
                        'Rouvrir ce planning ? Les horaires sont déjà partis. À la prochaine validation, '
                        . 'seules les personnes concernées par un changement seront prévenues.'); ?>');">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="week" value="<?php echo e($selectedWeekKey); ?>">
                    <button type="submit" name="rouvrir_planning" value="1" class="act act-modifier">
                        <span class="act-ic">✎</span><?php echo e(fjhT('Modifier', 'Wijzigen')); ?>
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo e(
                        'Valider le planning de la semaine ? Chaque étudiant concerné recevra son horaire, '
                        . 'et chaque agence son fichier.'); ?>');">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="week" value="<?php echo e($selectedWeekKey); ?>">
                    <button type="submit" name="valider_planning" value="1" class="act act-valider">
                        <span class="act-ic">✔</span><?php echo e(fjhT('Valider le planning', 'Planning valideren')); ?>
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php // Export et avis : cette page est le SEUL ecran d'une agence, sans
              // eux elle n'aurait aucun moyen d'emporter son planning ni d'ecrire
              // a l'equipe. Famiflora les a deja ailleurs et mieux places —
              // l'export dans la vue horaire, les avis sur l'accueil FamiJob. ?>
        <?php if (famijobEstCompteAgence($role)): ?>
        <?php // L'export suit les filtres affiches : exporter autre chose que ce
              // qu'on regarde est le meilleur moyen de diffuser un planning faux. ?>
        <a class="act act-export" href="export_matching.php?week=<?php echo e($selectedWeekKey); ?><?php
                echo $mSecteur !== '' ? '&secteur=' . urlencode($mSecteur) : '';
                echo $mDept !== '' ? '&department=' . urlencode($mDept) : ''; ?>"
           title="<?php echo e(fjhT('Télécharger la semaine affichée au format Excel', 'De getoonde week downloaden in Excel-formaat')); ?>">
            <span class="act-ic">⤓</span><?php echo e(fjhT('Export Excel', 'Export Excel')); ?>
        </a>

        <a class="act act-avis" href="avis.php"
           title="<?php echo e(fjhT('Écrire à l\'équipe : question, idée, souci', 'Schrijf het team: vraag, idee, probleem')); ?>">
            <span class="act-ic">💬</span><?php echo e(fjhT('Avis & suggestions', 'Feedback')); ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($message)) { echo $message; } ?>

<?php // ⚠️ LA QUESTION AVANT D'AFFECTER. Elle n'etait posee que dans la vue
      // detaillee : depuis ce classeur, une affectation qui declenchait le
      // moindre avertissement ne produisait rien du tout. Meme code des deux
      // cotes desormais. ?>
<?php require __DIR__ . '/confirmation_affectation.php'; ?>

<?php if (!$grille): ?>
    <div class="rien"><?php echo e(fjhT('Aucun créneau demandé cette semaine.', 'Geen aangevraagde tijdslots deze week.')); ?></div>
<?php else: ?>
<div class="cadre">
<table class="semaine">
    <?php // ⚠️ CE COLGROUP EST CE QUI FAIT TENIR LE TABLEAU DANS LA PAGE.
          // Avec « table-layout: fixed », le navigateur ne regarde plus le
          // contenu : il applique ces largeurs, point. Sept jours a 14,28 %,
          // repartis entre horaire / nom / agence — le nom prend la plus
          // grosse part parce que c'est le seul texte vraiment variable. ?>
    <colgroup>
        <?php for ($iCol = 0; $iCol < count($weekDays); $iCol++): ?>
            <col style="width: 4.8%;">
            <col style="width: 6.4%;">
            <col style="width: 3.08%;">
        <?php endfor; ?>
    </colgroup>
    <thead>
        <tr>
            <?php foreach ($weekDays as $iJour => $jour): ?>
                <th class="jour-tete fin-jour" colspan="3"><?php echo e($jour['label']); ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($weekDays as $iJour => $jour): ?>
                <?php $pair = ($iJour % 2 === 1) ? ' jour-pair' : ''; ?>
                <th class="sous-tete<?php echo $pair; ?>"><?php echo e(fjhT('horaire', 'uren')); ?></th>
                <th class="sous-tete<?php echo $pair; ?>"><?php echo e(fjhT('nom', 'naam')); ?></th>
                <th class="sous-tete fin-jour<?php echo $pair; ?>"><?php echo e(fjhT('agence', 'kantoor')); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php $colonnes = count($weekDays) * 3; ?>
    <?php // Un secteur choisi mais vide se DIT. Sans cette ligne, le tableau
          // n'affichait que ses en-tetes de jours : on ne savait pas s'il n'y
          // avait aucun creneau ou si le filtre avait echoue. ?>
    <?php if ($mSecteur !== '' && empty($grille[$mSecteur])): ?>
        <tr><td colspan="<?php echo (int) $colonnes; ?>" style="padding:18px; text-align:center; color:#6b7d72; font-style:italic;">
            <?php echo e(fjhT('Aucun créneau cette semaine pour ', 'Geen tijdsblok deze week voor ') . $mSecteur
                . ($mDept !== '' ? ' — ' . $mDept : '') . '.'); ?>
        </td></tr>
    <?php endif; ?>
    <?php foreach ($ordreSecteurs as $secteur): ?>
        <?php if (empty($grille[$secteur])) { continue; } ?>

        <tr class="l-secteur"><td colspan="<?php echo (int) $colonnes; ?>"><?php echo e($secteur); ?></td></tr>

        <?php
        // Les créneaux SANS département (clé vide) passent en premier : ils
        // appartiennent au secteur lui-même et doivent suivre son bandeau vert,
        // pas se retrouver après la liste des départements.
        $blocs = $grille[$secteur];
        $sansDept = [];
        if (isset($blocs[''])) {
            $sansDept[''] = $blocs[''];
            unset($blocs['']);
        }
        $blocs = $sansDept + $blocs;   // l'ordre des départements reste celui de la base
        ?>

        <?php foreach ($blocs as $dept => $parJour): ?>
            <?php if ($dept !== ''): ?>
            <tr class="l-departement"><td colspan="<?php echo (int) $colonnes; ?>"><?php echo e($dept); ?></td></tr>
            <?php endif; ?>

            <?php
            // Autant de lignes que le jour le plus chargé : les colonnes ne
            // sont pas alignées entre elles, exactement comme dans le classeur.
            $hauteur = 0;
            foreach ($weekDays as $jour) {
                $n = isset($parJour[$jour['key']]) ? count($parJour[$jour['key']]) : 0;
                if ($n > $hauteur) { $hauteur = $n; }
            }
            ?>

            <?php for ($ligne = 0; $ligne < $hauteur; $ligne++): ?>
                <tr>
                    <?php foreach ($weekDays as $iJour => $jour): ?>
                        <?php $place = $parJour[$jour['key']][$ligne] ?? null; ?>
                        <?php $pair = ($iJour % 2 === 1) ? ' jour-pair' : ''; ?>
                        <?php if (!$place): ?>
                            <td class="vide-jour<?php echo $pair; ?>"></td>
                            <td class="vide-jour<?php echo $pair; ?>"></td>
                            <td class="vide-jour fin-jour<?php echo $pair; ?>"></td>
                        <?php else: ?>
                            <?php // ⚠️ L'HORAIRE PORTE LE MOT DE L'AGENCE. Colore quand il
                                  // y en a un, et cliquable pour le lire. Sans cette
                                  // couleur, un commentaire laisse dans un tableau de
                                  // 400 cases n'aurait jamais ete lu — donc jamais
                                  // ecrit une seconde fois. ?>
                            <?php $mot = (string) ($place['mot'] ?? ''); ?>
                            <td class="horaire<?php echo $pair; ?><?php echo $mot !== '' ? ' a-mot' : ''; ?>"
                                <?php if ($mot !== ''): ?>data-mot="<?php echo e($mot); ?>"
                                data-ou="<?php echo e($dept . ' · ' . $jour['label'] . ' · ' . $place['horaire']); ?>"
                                title="<?php echo e(fjhT('Un mot de l\'agence — cliquez', 'Een woordje van het kantoor — klik')); ?>"<?php endif; ?>><?php echo e($place['horaire']); ?></td>
                            <td class="nom<?php echo $pair; ?>">
                                <?php if ($place['nom'] !== ''): ?>
                                    <span class="occupe">
                                        <span><?php echo e($place['nom']); ?></span>
                                        <?php if (!empty($place['peutRetirer'])): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo e(fjhT('Retirer cette personne ?', 'Deze persoon verwijderen?')); ?>');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="assignment_id" value="<?php echo (int) $place['assignment_id']; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $place['request_id']; ?>">
                                            <button type="submit" name="unassign_student" value="1" class="retirer" title="<?php echo e(fjhT('Retirer', 'Verwijderen')); ?>">×</button>
                                        </form>
                                        <?php endif; ?>
                                    </span>
                                <?php elseif (!empty($place['masque'])): ?>
                                    <?php // ⚠️ PRISE, MAIS PAR QUELQU'UN QU'ON NE NOMME PAS.
                                          // Un compte agence ne lit pas les noms des autres
                                          // agences. Ce n'est PAS une case libre : l'afficher
                                          // comme telle ferait proposer la place une seconde
                                          // fois. D'ou le libelle et la couleur. ?>
                                    <span class="occupe-masque"><?php echo e(famijobLibelleOccupe()); ?></span>
                                <?php elseif ($planningVerrouille): ?>
                                    <?php // Planning arrete : la place reste visible comme non
                                          // pourvue — c'est une information — mais elle ne
                                          // s'ouvre plus. Un bouton qui repond « non » est pire
                                          // qu'un bouton absent. ?>
                                    <span class="place-fermee"><?php echo e(fjhT('à pourvoir', 'in te vullen')); ?></span>
                                <?php else: ?>
                                    <?php // La case vide EST le bouton : c'est le geste du classeur,
                                          // on clique là où le nom doit apparaître.
                                          //
                                          // Ouverte aux AGENCES aussi : c'est leur seul ecran, et le
                                          // traitement refuse deja un candidat qui n'est pas des leurs.
                                          // Le bouton ne decide de rien, il ouvre une fenetre. ?>
                                    <button type="button" class="place-libre"
                                            data-request="<?php echo (int) $place['request_id']; ?>"
                                            data-ou="<?php echo e($dept . ' · ' . $jour['label'] . ' · ' . $place['horaire']); ?>">
                                        + <?php echo e(fjhT('à pourvoir', 'in te vullen')); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="agence fin-jour<?php echo $pair; ?>"><?php echo e($place['agence']); ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<div class="voile" id="voile">
    <div class="fenetre">
        <h2><?php echo e(fjhT('Affecter quelqu\'un', 'Iemand toewijzen')); ?></h2>
        <div class="ou" id="fenetreOu"></div>

        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="request_id" id="fenetreRequest" value="">

            <?php // UN SEUL CHAMP, deux usages : on tape et la liste se filtre,
                  // ou on écrit un nom qui n'y figure pas. Le traitement sait
                  // déjà retrouver un inscrit par son nom et, à défaut,
                  // l'affecter en texte libre — c'est ce qui permet d'inscrire
                  // quelqu'un sans compte, comme dans le classeur.
                  //
                  // Le <datalist> propose sans imposer : à la différence d'un
                  // menu déroulant, il n'empêche pas d'écrire autre chose. ?>
            <label for="student_name"><?php echo e(fjhT('Qui ?', 'Wie?')); ?></label>
            <input type="text" name="student_name" id="student_name" list="listeEtudiants"
                   autocomplete="off" placeholder="<?php echo e(fjhT('Taper un nom, ou choisir dans la liste', 'Een naam typen of kiezen uit de lijst')); ?>">
            <datalist id="listeEtudiants">
                <?php // $studentOptions porte 'label' (prénom + nom assemblés). ?>
                <?php foreach ($studentOptions as $etu): ?>
                    <option value="<?php echo e(trim((string) $etu['label'])); ?>"><?php echo !empty($etu['interim']) ? e($etu['interim']) : ''; ?></option>
                <?php endforeach; ?>
            </datalist>

            <?php // LE MOT DE L'AGENCE. Elle sait des choses sur la personne
                  // qu'elle place et que Famiflora ne saura pas autrement :
                  // « premiere mission chez vous », « arrive a 9h15 ».
                  // Facultatif — un champ obligatoire se remplirait de « ras ».
                  //
                  // Reserve aux agences : quand Famiflora affecte quelqu'un,
                  // elle n'a personne a qui laisser un mot, elle EST le
                  // destinataire. ?>
            <?php if (famijobEstCompteAgence($role)): ?>
                <label for="agency_comment"><?php echo e(fjhT('Un mot pour Famiflora (facultatif)', 'Een woordje voor Famiflora (optioneel)')); ?></label>
                <input type="text" name="agency_comment" id="agency_comment" maxlength="500"
                       autocomplete="off"
                       placeholder="<?php echo e(fjhT('Ex : première mission chez vous, arrive à 9h15…', 'Bijv.: eerste opdracht bij u, komt om 9u15…')); ?>">
            <?php endif; ?>

            <div class="actions">
                <button type="button" class="btn btn-non" id="fermer"><?php echo e(fjhT('Annuler', 'Annuleren')); ?></button>
                <button type="submit" name="assign_student" value="1" class="btn btn-ok"><?php echo e(fjhT('Affecter', 'Toewijzen')); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var voile = document.getElementById('voile');
    var champRequest = document.getElementById('fenetreRequest');
    var ou = document.getElementById('fenetreOu');
    if (!voile) { return; }

    function ouvrir(bouton) {
        champRequest.value = bouton.getAttribute('data-request');
        ou.textContent = bouton.getAttribute('data-ou') || '';
        document.getElementById('student_name').value = '';
        var mot = document.getElementById('agency_comment');
        if (mot) { mot.value = ''; }
        voile.classList.add('ouvert');
        document.getElementById('student_name').focus();
    }

    function fermer() { voile.classList.remove('ouvert'); }

    // Délégation : les cases se comptent par centaines, on ne pose pas un
    // écouteur sur chacune.
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('.place-libre') : null;
        if (b) { ouvrir(b); }
    });

    document.getElementById('fermer').addEventListener('click', fermer);
    voile.addEventListener('click', function (e) { if (e.target === voile) { fermer(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fermer(); } });
}());
</script>

<?php // LIRE LE MOT DE L'AGENCE. Une fenetre plutot qu'une info-bulle : une
      // info-bulle disparait des qu'on bouge la souris, ne se lit pas au doigt
      // sur une tablette, et coupe les phrases longues. ?>
<div class="voile" id="voileMot">
    <div class="fenetre">
        <h2>💬 <?php echo e(fjhT('Mot de l\'agence', 'Woordje van het kantoor')); ?></h2>
        <div class="ou" id="motOu"></div>
        <p id="motTexte" style="margin:0 0 16px; color:#33413a; font-size:.95rem; line-height:1.5; white-space:pre-wrap;"></p>
        <div class="actions">
            <button type="button" class="btn btn-non" id="motFermer"><?php echo e(fjhT('Fermer', 'Sluiten')); ?></button>
        </div>
    </div>
</div>
<script>
(function () {
    var voile = document.getElementById('voileMot');
    if (!voile) { return; }
    var texte = document.getElementById('motTexte');
    var ou = document.getElementById('motOu');

    function fermer() { voile.classList.remove('ouvert'); }

    // Delegation : les cases se comptent par centaines.
    document.addEventListener('click', function (e) {
        var c = e.target.closest ? e.target.closest('.horaire.a-mot') : null;
        if (!c) { return; }
        texte.textContent = c.getAttribute('data-mot') || '';
        ou.textContent = c.getAttribute('data-ou') || '';
        voile.classList.add('ouvert');
    });

    document.getElementById('motFermer').addEventListener('click', fermer);
    voile.addEventListener('click', function (e) { if (e.target === voile) { fermer(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fermer(); } });
}());
</script>

</body>
</html>
