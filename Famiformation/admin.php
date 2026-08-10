<?php
// ============================================================
// ESPACE ADMINISTRATION — L'ACCUEIL.
//
// Cette page faisait trois métiers à la fois : accueil, création de comptes et
// liste complète des collaborateurs avec son édition en ligne, soit 693 lignes
// dont personne ne voyait le début et la fin ensemble. La gestion des
// collaborateurs est partie dans admin_collaborateurs.php, telle quelle.
//
// Ce qui reste ici : où aller, et ce qui attend une décision. Rien d'autre.
// Les compteurs ci-dessous sont de VRAIS comptages, pas des cases décoratives :
// un accueil qui affiche « 0 » alors qu'il y a du travail en attente est pire
// que pas d'accueil du tout.
//
// À VENIR : les demandes de modification de fiche (motif → autorisation →
// modification → validation). Le circuit n'existe pas encore, donc la section
// n'est pas là — on ne pose pas un cadre vide en promettant qu'il se remplira.
// ============================================================
require_once 'config.php';
verifierConnexion($db);

ensureUserAccountAccessColumns($db);

if (!isAdminOrTeamcoach()) {
    header('Location: index.php');
    exit();
}

// Les comptes « agence intérim » ne sont pas des collaborateurs : ils sont
// exclus de la liste (voir admin_collaborateurs.php), donc aussi du décompte.
// Sinon l'accueil annonce un total que la liste ne montre jamais.
$compte = ['actifs' => 0, 'attente' => 0, 'inactifs' => 0];
try {
    $lignes = $db->query(
        "SELECT
            SUM(CASE WHEN statut = 'inactif' THEN 1 ELSE 0 END) AS inactifs,
            SUM(CASE WHEN (statut IS NULL OR statut != 'inactif')
                      AND (account_activation_pending = 1 OR mot_de_passe IS NULL OR mot_de_passe = '')
                     THEN 1 ELSE 0 END) AS attente,
            SUM(CASE WHEN (statut IS NULL OR statut != 'inactif')
                      AND account_activation_pending = 0
                      AND mot_de_passe IS NOT NULL AND mot_de_passe != ''
                     THEN 1 ELSE 0 END) AS actifs
         FROM utilisateurs
         WHERE role != 'agence_interim'"
    )->fetch(PDO::FETCH_ASSOC);

    if ($lignes) {
        $compte = [
            'actifs'   => (int) ($lignes['actifs'] ?? 0),
            'attente'  => (int) ($lignes['attente'] ?? 0),
            'inactifs' => (int) ($lignes['inactifs'] ?? 0),
        ];
    }
} catch (Exception $e) {
    // Base indisponible : l'accueil reste affichable, les entrées ci-dessous
    // fonctionnent toujours. Des compteurs manquants valent mieux qu'une page
    // blanche qui ferait croire que l'administration entière est tombée.
    $compte = null;
}

// Les comptes en attente d'activation, nommément. C'est la seule chose qui
// « attend une décision » aujourd'hui : un compte créé dont le mail d'activation
// n'a jamais abouti reste invisible dans la liste, noyé parmi les autres.
$enAttente = [];
try {
    $enAttente = $db->query(
        "SELECT id, nom, prenom, identifiant, email, statut_date
         FROM utilisateurs
         WHERE role != 'agence_interim'
           AND (statut IS NULL OR statut != 'inactif')
           AND (account_activation_pending = 1 OR mot_de_passe IS NULL OR mot_de_passe = '')
         ORDER BY statut_date DESC, nom ASC
         LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $enAttente = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Open Sans', sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        h1 { color: #2d5a37; text-align: center; margin: 0; }
        .entete { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 26px; flex-wrap: wrap; }
        .lien-retour { text-decoration: none; color: #2d5a37; font-weight: 700; }

        .chiffres { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 30px; }
        .chiffre { background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,.05); padding: 20px 22px; }
        .chiffre .valeur { font-size: 2rem; font-weight: 700; color: #2d5a37; line-height: 1.1; }
        .chiffre .titre { color: #666; font-size: .82rem; text-transform: uppercase; letter-spacing: .05em; margin-top: 6px; }
        .chiffre.alerte .valeur { color: #856404; }

        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,.05); margin-bottom: 30px; overflow: hidden; }
        .card-header { background: #2d5a37; color: #fff; padding: 15px 25px; font-weight: 700; font-size: 1.1rem; }
        .card-body { padding: 22px 25px; }

        .acces { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
        .acces a { display: block; background: #fff; border: 1px solid #e3ebe6; border-radius: 12px; padding: 18px 20px; text-decoration: none; color: inherit; transition: border-color .15s, box-shadow .15s; }
        .acces a:hover { border-color: #2d5a37; box-shadow: 0 6px 18px rgba(0,0,0,.07); }
        .acces .nom { color: #2d5a37; font-weight: 700; font-size: 1rem; }
        .acces .desc { color: #666; font-size: .86rem; margin-top: 6px; line-height: 1.5; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: .92rem; }
        th { color: #666; text-transform: uppercase; font-size: .76rem; letter-spacing: .04em; }
        .lien-fiche { color: #1d6f42; font-weight: 700; text-decoration: none; }
        .vide { color: #777; font-size: .92rem; line-height: 1.6; }
        .puce { display: inline-block; background: #fff3cd; color: #856404; border-radius: 999px; padding: 3px 12px; font-size: .78rem; font-weight: 700; }
    </style>
</head>
<body>

<div class="container">
    <div class="entete">
        <a href="index.php" class="lien-retour">⬅ Retour Accueil</a>
        <h1>Espace Administration</h1>
        <span style="min-width:120px;"></span>
    </div>

    <?php if ($compte !== null): ?>
    <div class="chiffres">
        <div class="chiffre">
            <div class="valeur"><?= (int) $compte['actifs'] ?></div>
            <div class="titre">Collaborateurs actifs</div>
        </div>
        <div class="chiffre<?= $compte['attente'] > 0 ? ' alerte' : '' ?>">
            <div class="valeur"><?= (int) $compte['attente'] ?></div>
            <div class="titre">Comptes en attente</div>
        </div>
        <div class="chiffre">
            <div class="valeur"><?= (int) $compte['inactifs'] ?></div>
            <div class="titre">Comptes inactifs</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Où aller</div>
        <div class="card-body">
            <div class="acces">
                <a href="admin_collaborateurs.php">
                    <div class="nom">👥 Collaborateurs</div>
                    <div class="desc">Créer un compte, modifier un profil, un mot de passe, une agence ou un lieu de travail. Activer, désactiver, supprimer.</div>
                </a>
                <?php // Pas d'entrée Famicard ici : c'est un produit distinct,
                      // avec son propre accueil. On n'y entre pas par le site. ?>
                <a href="admin_agences_interim.php">
                    <div class="nom">🏢 Agences intérim</div>
                    <div class="desc">La liste des agences proposées à la création d'un compte et dans les filtres.</div>
                </a>
                <a href="admin_disponibilites_etudiants.php">
                    <div class="nom">🗓️ Disponibilités étudiantes</div>
                    <div class="desc">Vue par semaine des disponibilités déclarées.</div>
                </a>
                <a href="export_excel.php">
                    <div class="nom">📊 Export Excel complet</div>
                    <div class="desc">L'export global des comptes et des scores.</div>
                </a>
                <a href="admin_evaluations_orphelines.php">
                    <div class="nom">🧹 Évaluations orphelines</div>
                    <div class="desc">Les évaluations rattachées à un compte qui n'existe plus.</div>
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Comptes en attente d'activation</div>
        <div class="card-body">
            <?php if (empty($enAttente)): ?>
                <p class="vide">Aucun compte en attente. Un compte apparaît ici tant que son
                titulaire n'a pas de mot de passe — typiquement quand le mail d'activation
                n'est jamais arrivé à destination.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Collaborateur</th>
                            <th>Identifiant</th>
                            <th>Email</th>
                            <th>Depuis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enAttente as $u): ?>
                        <tr>
                            <td>
                                <a class="lien-fiche" href="admin_user.php?id=<?= (int) $u['id'] ?>"><?= e(trim(($u['nom'] ?? '') . ' ' . ($u['prenom'] ?? ''))) ?></a>
                            </td>
                            <td><?= e((string) ($u['identifiant'] ?? '')) ?></td>
                            <td><?= ($u['email'] ?? '') !== '' ? e((string) $u['email']) : '<span class="puce">sans email</span>' ?></td>
                            <td>
                                <?php
                                    $ts = !empty($u['statut_date']) ? strtotime((string) $u['statut_date']) : false;
                                    echo $ts ? e(date('d/m/Y', $ts)) : '—';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($compte !== null && $compte['attente'] > count($enAttente)): ?>
                    <p class="vide" style="margin-top:16px;">
                        Les <?= count($enAttente) ?> plus récents sur <?= (int) $compte['attente'] ?>.
                        La liste complète est dans <a class="lien-fiche" href="admin_collaborateurs.php">Collaborateurs</a>.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
