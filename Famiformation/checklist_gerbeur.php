<?php
require_once 'config.php';
verifierConnexion($db);

if (!isAdmin() && (($_SESSION['username'] ?? '') !== 'Accueil')) {
    header('Location: index.php');
    exit();
}

$message = '';
$checklistSections = [
    'methodes' => [
        'title' => 'Méthodes et instructions de travail',
        'items' => [
            'methode_vitesse_distance' => 'Il est important d\'adapter votre vitesse et de garder une distance suffisante entre vos collègues (3 m pendant la conduite)',
            'methode_depassement' => 'Communiquez toujours lorsque vous dépassez un collègue',
            'methode_vigilance' => 'Soyez vigilant aux endroits fréquentés et adaptez toujours votre vitesse ainsi que le sens de circulation',
            'methode_descente' => 'Je ne descends d\'un appareil de manipulation que lorsque celui-ci est totalement à l\'arrêt (si le cas)',
            'methode_zones' => 'Je laisse dégagées et accessibles les zones piétonnes, les zones rouges, les sorties de secours, les portes et portes coupe-feu, les extincteurs et les armoires électriques',
            'methode_fourches' => 'Je ne roule pas vers l\'avant avec les fourches d\'un appareil de manipulation, ni lors des manœuvres ni lorsque je roule sur un plan incliné',
            'methode_liquide' => 'Je contacte immédiatement le responsable logistique si je constate la présence de liquide au sol',
        ],
    ],
    'charge' => [
        'title' => 'Explication de la mise en charge',
        'items' => [
            'charge_branchement' => 'Savoir brancher et débrancher une batterie via le câble de charge',
            'charge_cable_defectueux' => 'Avertir le responsable si le câble de charge est défectueux',
            'charge_poignees' => 'Pour brancher ou débrancher le chargeur, utiliser les poignées prévues à cet effet',
            'charge_fixation' => 'Fixer le câble correctement',
        ],
    ],
    'commandes' => [
        'title' => 'Explication des boutons de commande',
        'items' => [
            'commande_levage' => 'Fonction de levage et d\'abaissement',
            'commande_marche' => 'Marche avant et marche arrière + régulateur',
            'commande_klaxon' => 'Klaxon',
            'commande_arret' => 'Bouton d\'arrêt',
            'commande_arret_manche' => 'Bouton d\'arrêt sur le manche',
        ],
    ],
    'transpalette' => [
        'title' => 'Transpalette - gerbeur',
        'items' => [
            'transpalette_stockage' => 'Le gerbeur reste soit dans le stock, soit dans un endroit isolé',
            'transpalette_distance' => 'Le formateur reste à une distance de sécurité (1,5 m)',
            'transpalette_test_commandes' => 'Le formateur demande au nouveau de montrer et de tester les commandes',
            'transpalette_deux_mains' => 'Tenir le gerbeur à deux mains lorsque vous l\'utilisez',
            'transpalette_verification' => 'Avant la prise en main du gerbeur, vérifier qu\'il fonctionne correctement (freins, défectuosités)',
            'transpalette_defectuosite' => 'Si le gerbeur présente une défectuosité, ne pas l\'utiliser et contacter le responsable direct',
            'palette_filmee' => 'Avant de monter une palette, vérifier qu\'elle soit bien filmée',
            'palette_hauteur' => 'Avant de monter une palette, vérifier qu\'elle ne dépasse pas la hauteur autorisée',
            'palette_poids' => 'Avant de monter une palette, vérifier qu\'elle ne dépasse pas le poids autorisé (voir panneau)',
            'palette_rack' => 'Avant de monter une palette, vérifier que le rack est en bon état visuel',
            'palette_passage' => 'Lors de la montée de la palette dans un rack, bien vérifier de chaque côté que la palette passe dans l\'espace libre',
            'palette_centre' => 'Placer la palette au centre de l\'emplacement, que chaque extrémité se trouve sur la lisse',
        ],
    ],
];

$checklistLabels = [];
foreach ($checklistSections as $section) {
    foreach ($section['items'] as $key => $label) {
        $checklistLabels[$key] = $label;
    }
}

$exerciseFields = [
    'exercice_1' => 'Exercice 1 : avancer et reculer avec le transpalette de chargement',
    'exercice_2' => 'Exercice 2 : passer entre quelques palettes avec le transpalette',
    'exercice_3' => 'Exercice 3 : conduite, freinage et utilisation du bouton d\'arrêt',
    'exercice_4' => 'Exercice 4 : prendre une palette dans un rack',
    'test_conduite' => 'Résultat du test de conduite (exercices avec et sans palettes)',
    'feedback' => 'Feed-back',
    'autres' => 'Autres',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    $nomEvalue = ucfirst(strtolower(trim((string) ($_POST['nom_evalue'] ?? ''))));
    $prenomEvalue = ucfirst(strtolower(trim((string) ($_POST['prenom_evalue'] ?? ''))));
    $dateEval = trim((string) ($_POST['date_eval'] ?? ''));
    $nomFormateur = trim((string) ($_POST['nom_formateur'] ?? ''));
    $checklist = $_POST['checklist'] ?? [];
    $itemNotes = $_POST['item_notes'] ?? [];
    $remarquesGenerales = trim((string) ($_POST['remarques_generales'] ?? ''));
    $exercises = $_POST['exercises'] ?? [];

    $payload = [
        'type' => 'checklist_gerbeur',
        'nom_evalue' => $nomEvalue,
        'prenom_evalue' => $prenomEvalue,
        'date_eval' => $dateEval,
        'nom_formateur' => $nomFormateur,
        'checklist' => [],
        'exercises' => [],
        'remarques_generales' => $remarquesGenerales,
        'created_at' => date('c'),
    ];

    foreach ($checklistLabels as $key => $label) {
        $status = trim((string) ($checklist[$key] ?? 'non_verifie'));
        if (!in_array($status, ['conforme', 'non_conforme', 'non_verifie'], true)) {
            $status = 'non_verifie';
        }

        $payload['checklist'][$key] = [
            'label' => $label,
            'status' => $status,
            'note' => trim((string) ($itemNotes[$key] ?? '')),
        ];
    }

    foreach ($exerciseFields as $key => $label) {
        $payload['exercises'][$key] = [
            'label' => $label,
            'value' => trim((string) ($exercises[$key] ?? '')),
        ];
    }

    $userStmt = $db->prepare("SELECT id FROM utilisateurs WHERE LOWER(nom) = ? AND LOWER(prenom) = ? LIMIT 1");
    $userStmt->execute([strtolower($nomEvalue), strtolower($prenomEvalue)]);
    $linkedUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    $stockFile = 'checklist_gerbeur_stock.php';
    $orphanFile = 'checklist_gerbeur_orphelines.php';

    if (!file_exists($stockFile)) {
        file_put_contents($stockFile, "<?php\nreturn [];\n", LOCK_EX);
    }
    if (!file_exists($orphanFile)) {
        file_put_contents($orphanFile, "<?php\nreturn [];\n", LOCK_EX);
    }

    if ($linkedUser) {
        $entries = include $stockFile;
        if (!is_array($entries)) {
            $entries = [];
        }
        $entries[] = $payload;
        file_put_contents($stockFile, '<?php return ' . var_export($entries, true) . ';', LOCK_EX);
        $message = '<div class="alert success">✅ Checklist GERBEUR enregistrée.</div>';
    } else {
        $entries = include $orphanFile;
        if (!is_array($entries)) {
            $entries = [];
        }
        $entries[] = $payload;
        file_put_contents($orphanFile, '<?php return unserialize(' . var_export(serialize($entries), true) . ');', LOCK_EX);
        clearstatcache(true, $orphanFile);
        $message = '<div class="alert success">✅ Checklist GERBEUR enregistrée.<br>⚠️ Le collaborateur n\'a pas été trouvé, la checklist a été rangée dans les entrées orphelines.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Checklist GERBEUR</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .container { max-width: 1180px; margin: 40px auto; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.07); padding: 30px; }
        h1 { color: #2d5a37; text-align: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 18px; }
        label { font-weight: bold; color: #2d5a37; margin-bottom: 6px; display: block; }
        input, textarea { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ddd; font-size: 1rem; box-sizing: border-box; }
        textarea { min-height: 80px; resize: vertical; }
        .alert.success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 18px; text-align: center; font-weight: bold; }
        .btn-submit { background: #2d5a37; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 1rem; margin-top: 10px; }
        .btn-secondary { background: #e0e0e0; color: #2d5a37; }
        .back-link { display: block; width: fit-content; margin: 30px auto 0 auto; color: #2d5a37; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.9); padding: 12px 25px; border-radius: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .back-link:hover { background: #2d5a37; color: white; }
        .checklist-card { border: 1px solid #e7ece8; border-radius: 12px; overflow: hidden; margin: 28px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #edf1ee; padding: 14px 16px; text-align: left; vertical-align: top; }
        th { background: #f8fbf9; color: #617268; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.04em; }
        .radio-group { display: flex; gap: 14px; flex-wrap: wrap; }
        .radio-pill { display: inline-flex; align-items: center; gap: 6px; background: #f4f7f6; border-radius: 999px; padding: 8px 12px; }
        .radio-pill input { width: auto; margin: 0; }
        .action-row { display: flex; gap: 18px; margin-top: 24px; }
        .action-row .btn-submit { margin-top: 0; }
        .item-title { font-weight: 700; color: #2d5a37; }
        .section-title { margin: 0; padding: 16px 18px; background: #eef5ef; color: #214b35; font-size: 1.05rem; font-weight: 700; border-top: 1px solid #edf1ee; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Checklist GERBEUR</h1>
        <?php echo $message; ?>

        <form method="POST" id="gerbeurChecklistForm" onsubmit="return verifierNomPrenomChecklist();">
            <?php echo csrfField(); ?>

            <div class="form-group">
                <label for="nom_evalue">Nom de la personne évaluée</label>
                <input type="text" name="nom_evalue" id="nom_evalue" required>
            </div>
            <div class="form-group">
                <label for="prenom_evalue">Prénom de la personne évaluée</label>
                <input type="text" name="prenom_evalue" id="prenom_evalue" required>
            </div>
            <div class="form-group">
                <label for="date_eval">Date de l'évaluation</label>
                <input type="date" name="date_eval" id="date_eval" required>
            </div>
            <div class="form-group">
                <label for="nom_formateur">Nom du formateur</label>
                <input type="text" name="nom_formateur" id="nom_formateur">
            </div>

            <div class="checklist-card">
                <table>
                    <thead>
                        <tr>
                            <th style="width:34%;">Point de contrôle</th>
                            <th style="width:33%;">Résultat</th>
                            <th>Commentaire</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checklistSections as $section): ?>
                            <tr>
                                <td colspan="3" class="section-title"><?php echo htmlspecialchars($section['title']); ?></td>
                            </tr>
                            <?php foreach ($section['items'] as $key => $label): ?>
                                <tr>
                                    <td><span class="item-title"><?php echo htmlspecialchars($label); ?></span></td>
                                    <td>
                                        <div class="radio-group">
                                            <label class="radio-pill"><input type="radio" name="checklist[<?php echo htmlspecialchars($key); ?>]" value="conforme"> Conforme</label>
                                            <label class="radio-pill"><input type="radio" name="checklist[<?php echo htmlspecialchars($key); ?>]" value="non_conforme"> Non conforme</label>
                                            <label class="radio-pill"><input type="radio" name="checklist[<?php echo htmlspecialchars($key); ?>]" value="non_verifie" checked> Non vérifié</label>
                                        </div>
                                    </td>
                                    <td>
                                        <textarea name="item_notes[<?php echo htmlspecialchars($key); ?>]" placeholder="Observation éventuelle pour ce point..."></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($exerciseFields as $key => $label): ?>
                <div class="form-group">
                    <label for="exercises_<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></label>
                    <textarea name="exercises[<?php echo htmlspecialchars($key); ?>]" id="exercises_<?php echo htmlspecialchars($key); ?>" placeholder="Compléter ici..."></textarea>
                </div>
            <?php endforeach; ?>

            <div class="form-group">
                <label for="remarques_generales">Remarques générales</label>
                <textarea name="remarques_generales" id="remarques_generales" placeholder="Compléter ici..."></textarea>
            </div>

            <div class="action-row">
                <button type="button" class="btn-submit btn-secondary" onclick="sauvegarderChecklistGerbeur()">Sauvegarder</button>
                <button type="submit" class="btn-submit">Valider la checklist</button>
            </div>
            <div id="saveMessage" style="margin-top:18px; text-align:center; color:#2d5a37; font-weight:bold;"></div>
        </form>

        <a href="stock.php" class="back-link">← Retour à l'espace stock</a>
    </div>

    <script>
    const utilisateursChecklist = <?php
        $us = $db->query("SELECT nom, prenom FROM utilisateurs");
        $arr = [];
        foreach ($us as $u) {
            $arr[] = [
                'nom' => strtolower((string) $u['nom']),
                'prenom' => strtolower((string) $u['prenom']),
            ];
        }
        echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?>;

    function verifierNomPrenomChecklist() {
        const nom = document.getElementById('nom_evalue').value.trim().toLowerCase();
        const prenom = document.getElementById('prenom_evalue').value.trim().toLowerCase();
        const trouve = utilisateursChecklist.some(u => u.nom === nom && u.prenom === prenom);
        if (!trouve) {
            return confirm('⚠️ Attention : ce nom/prénom ne correspond à aucun collaborateur connu. Voulez-vous quand même enregistrer la checklist ?');
        }
        return true;
    }

    function sauvegarderChecklistGerbeur() {
        const form = document.getElementById('gerbeurChecklistForm');
        const formData = new FormData(form);
        fetch('save_checklist_gerbeur_temp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            document.getElementById('saveMessage').innerText = 'Checklist sauvegardée temporairement.';
        })
        .catch(() => {
            document.getElementById('saveMessage').innerText = 'Erreur lors de la sauvegarde.';
        });
    }
    </script>
</body>
</html>
