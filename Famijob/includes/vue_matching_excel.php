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
       horaire / nom / agence. Le tableau défile horizontalement plutôt que
       de comprimer les colonnes jusqu'à l'illisible. */
    .cadre { overflow-x: auto; padding: 0 12px; }
    table.semaine { border-collapse: collapse; font-size: .72rem; width: max-content; min-width: 100%; }
    table.semaine th, table.semaine td { border: 1px solid #c8d3cc; padding: 2px 5px; white-space: nowrap; }

    .jour-tete { background: #2d5a37; color: #fff; font-size: .78rem; font-weight: 800; text-align: center; padding: 6px 4px; }
    .sous-tete { background: #f0f4f1; color: #55665c; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; text-align: center; }

    /* Les couleurs du classeur : secteur en vert, département en jaune. */
    .l-secteur td { background: #7ed321; color: #1d3d12; font-weight: 800; text-align: center; font-size: .78rem; padding: 3px; }
    .l-departement td { background: #ffff66; color: #4a4a00; font-weight: 700; text-align: center; font-size: .74rem; padding: 2px; }

    td.horaire { background: #fbfdfb; color: #33443a; text-align: center; font-variant-numeric: tabular-nums; }
    td.nom { min-width: 130px; }
    td.agence { min-width: 62px; color: #55665c; text-align: center; }
    /* SÉPARATION DES JOURS. Une bordure fine se noyait dans le quadrillage :
       sur sept colonnes triples, l'oeil perdait la limite entre mardi et
       mercredi. D'où une barre épaisse ET un fond alterné — deux repères
       valent mieux qu'un quand le tableau est dense. */
    th.fin-jour, td.fin-jour { border-right: 6px solid #1d3d24 !important; }
    td.jour-pair, th.jour-pair { background: #e8efe9; }
    td.jour-pair.horaire { background: #dfe8e1; }
    td.vide-jour { background: #f7f9f8; }

    .place-libre { display: block; width: 100%; border: 1px dashed #b9cfc0; background: #fff; color: #2d5a37; border-radius: 5px; padding: 2px 6px; font-family: inherit; font-size: .7rem; font-weight: 700; cursor: pointer; text-align: left; }
    .place-libre:hover { background: #eef7f0; border-color: #2d5a37; }
    .occupe { display: flex; align-items: center; justify-content: space-between; gap: 5px; }
    .retirer { border: 0; background: none; color: #b23; cursor: pointer; font-size: .78rem; padding: 0 2px; line-height: 1; }
    .retirer:hover { color: #7d1616; }

    .rien { padding: 30px 20px; text-align: center; color: #667; }

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
    <div>
        <a class="pill" href="interim_horaires_demandes.php"><?php echo e(fjhT('Demandes', 'Aanvragen')); ?></a>
        <a class="pill" href="index.php">&larr; <?php echo e(fjhT('Accueil', 'Onthaal')); ?></a>
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

    <?php // Changer de vue sans perdre la semaine ni le filtre. ?>
    <a class="pill" style="background:#fff; color:#2d5a37; border:1px solid #cfdad3;"
       href="<?php echo e($lienVue('liste')); ?>">☰ <?php echo e(fjhT('Vue détaillée', 'Gedetailleerde weergave')); ?></a>

    <?php // L'auto-matching existe toujours dans le traitement : sans ce bouton,
          // la fonction serait devenue inatteignable en changeant d'écran. ?>
    <?php if ($isAdmin): ?>
    <form method="POST" onsubmit="return confirm('<?php echo e(fjhT('Lancer l\'auto-matching sur toute la semaine ?', 'Auto-matching voor de hele week starten?')); ?>');">
        <?php echo csrfField(); ?>
        <input type="hidden" name="week" value="<?php echo e($selectedWeekKey); ?>">
        <button type="submit" name="auto_match_week" value="1">⚡ <?php echo e(fjhT('Auto-matching de la semaine', 'Auto-matching van de week')); ?></button>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($message)) { echo $message; } ?>

<?php if (!$grille): ?>
    <div class="rien"><?php echo e(fjhT('Aucun créneau demandé cette semaine.', 'Geen aangevraagde tijdslots deze week.')); ?></div>
<?php else: ?>
<div class="cadre">
<table class="semaine">
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
                            <td class="horaire<?php echo $pair; ?>"><?php echo e($place['horaire']); ?></td>
                            <td class="nom<?php echo $pair; ?>">
                                <?php if ($place['nom'] !== ''): ?>
                                    <span class="occupe">
                                        <span><?php echo e($place['nom']); ?></span>
                                        <?php if ($isAdmin): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo e(fjhT('Retirer cette personne ?', 'Deze persoon verwijderen?')); ?>');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="assignment_id" value="<?php echo (int) $place['assignment_id']; ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $place['request_id']; ?>">
                                            <button type="submit" name="unassign_student" value="1" class="retirer" title="<?php echo e(fjhT('Retirer', 'Verwijderen')); ?>">×</button>
                                        </form>
                                        <?php endif; ?>
                                    </span>
                                <?php elseif ($isAdmin): ?>
                                    <?php // La case vide EST le bouton : c'est le geste du classeur,
                                          // on clique là où le nom doit apparaître. ?>
                                    <button type="button" class="place-libre"
                                            data-request="<?php echo (int) $place['request_id']; ?>"
                                            data-ou="<?php echo e($dept . ' · ' . $jour['label'] . ' · ' . $place['horaire']); ?>">
                                        + <?php echo e(fjhT('à pourvoir', 'in te vullen')); ?>
                                    </button>
                                <?php else: ?>
                                    <span style="color:#aab;">—</span>
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

<?php if ($isAdmin): ?>
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
<?php endif; ?>

</body>
</html>
