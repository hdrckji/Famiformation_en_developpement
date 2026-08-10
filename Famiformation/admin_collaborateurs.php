<?php
// ============================================================
// GESTION DES COLLABORATEURS.
//
// Cette page était le coeur de admin.php, qui faisait trois métiers à la fois :
// l'accueil de l'administration, la création de comptes et la liste complète
// avec son édition en ligne. admin.php ne garde que le premier ; tout ce qui
// touche aux collaborateurs vit ici.
//
// Le code a été DÉPLACÉ, pas réécrit : mêmes requêtes, mêmes contrôles, mêmes
// libellés. Une refonte au moment du déplacement aurait mélangé deux risques —
// « la page a bougé » et « la page ne fait plus pareil » — dans un seul écran
// que personne ne peut relire d'un coup.
//
// ⚠️ La fiche d'un collaborateur, elle, n'est PAS ici : elle vit dans Famicard
// (/famicard/), que FamiFormation et FamiJob consultent. Ce qui se règle ici,
// c'est le COMPTE (identifiant, profil, mot de passe, statut) — pas la carte.
// ============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/widget.php';
require_once 'includes/organisation.php'; // secteurs (vocabulaire client)

ensureUserAccountAccessColumns($db);
ensureWidgetTables($db); // garantit la colonne utilisateurs.site_id + la table des sites
// Page d'administration : c'est ici qu'on a le droit de créer les tables des
// secteurs (jamais sur une page consultée par tout le monde).
//
// Sous garde : si la création échoue (droits refusés, base en lecture seule),
// la page doit continuer à faire son métier historique — gérer les comptes —
// au lieu de tomber en blanc pour une colonne d'appoint. Le drapeau évite
// ensuite de joindre une table qui n'existe pas.
$secteursActifs = true;
try {
    famiAssureSecteurs($db);
} catch (Exception $e) {
    $secteursActifs = false;
}
$secteursListe = $secteursActifs ? famiSecteurs($db) : [];

// La colonne departement_id a été ajoutée après coup à une table qui existait
// déjà en production. On vérifie sa présence RÉELLE au lieu de supposer que
// l'ALTER a réussi : la requête principale la lit, et une colonne absente
// ferait tomber toute la page pour un affichage d'appoint.
$departementsActifs = false;
if ($secteursActifs) {
    try {
        $departementsActifs = (bool) $db->query("SHOW COLUMNS FROM famicard_affectations LIKE 'departement_id'")->fetch();
    } catch (Exception $e) {
        $departementsActifs = false;
    }
}
$departementsParSecteur = $departementsActifs ? famiDepartementsParSecteur($db) : [];

if (!isAdminOrTeamcoach()) {
    header('Location: index.php');
    exit();
}

$widgetSitesList = widgetSites($db); // lieux de travail (météo)

$hasInterimColumn = false;
try {
    $columnStmt = $db->query("SHOW COLUMNS FROM utilisateurs LIKE 'interim'");
    $hasInterimColumn = (bool) $columnStmt->fetch();
    if (!$hasInterimColumn) {
        $db->exec("ALTER TABLE utilisateurs ADD COLUMN interim VARCHAR(255) NULL AFTER email");
        $columnStmt = $db->query("SHOW COLUMNS FROM utilisateurs LIKE 'interim'");
        $hasInterimColumn = (bool) $columnStmt->fetch();
    }
} catch (Exception $e) {
    $hasInterimColumn = false;
}

$agencesInterim = [];
try {
    $agenceTableStmt = $db->query("SHOW TABLES LIKE 'interim_agences'");
    if ($agenceTableStmt->fetch()) {
        $agencesInterim = $db->query("SELECT nom_agence FROM interim_agences ORDER BY nom_agence ASC")->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $agencesInterim = [];
}

// Colonne "date de statut" : créée si absente (renseigne la date du dernier changement d'état)
try {
    $colStatutDate = $db->query("SHOW COLUMNS FROM utilisateurs LIKE 'statut_date'");
    if (!$colStatutDate->fetch()) {
        $db->exec("ALTER TABLE utilisateurs ADD COLUMN statut_date DATETIME NULL");
        $db->exec("UPDATE utilisateurs SET statut_date = COALESCE(derniere_visite, NOW()) WHERE statut_date IS NULL");
    }
} catch (Exception $e) { /* colonne déjà présente ou indisponible */ }

// Normalise les anciens "Pas d'agence" en "aucune agence" (NULL) -> évite l'option en double
try {
    $db->prepare("UPDATE utilisateurs SET interim = NULL WHERE interim = ?")->execute(["Pas d'agence"]);
} catch (Exception $e) { /* ignore */ }

$message = "";
// Message "flash" survivant à une redirection (motif Post/Redirect/Get)
if (!empty($_SESSION['admin_flash'])) {
    $message = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

// Valeurs réaffichées dans le formulaire de création (en cas d'erreur ou de confirmation)
$createForm = [
    'new_username' => '', 'new_nom' => '', 'new_prenom' => '',
    // 🧪 RÈGLE : un nouvel arrivant est en BETA par défaut (comme les inscriptions
    // faites depuis le quiz, qui créent déjà des comptes beta). Il suffit de
    // choisir un autre profil dans la liste pour que ce choix soit respecté.
    'new_email' => '', 'new_interim' => '', 'new_role' => 'beta',
];
$showDuplicateModal = false;
$showNoEmailModal = false;
$confirmDuplicate = false;
$confirmNoEmail = false;

// Querystring des filtres mémorisés -> permet de revenir à la même vue après une action
function adminFilterQuery() {
    $f = $_SESSION['admin_filters'] ?? [];
    $params = [];
    foreach (['filter_role', 'filter_agence', 'filter_secteur', 'search_nom'] as $k) {
        if (!empty($f[$k])) { $params[$k] = $f[$k]; }
    }
    if (!empty($f['show_inactif'])) { $params['show_inactif'] = '1'; }
    return $params ? ('?' . http_build_query($params)) : '';
}

// Redirige vers la liste en conservant le filtre courant (et un message flash optionnel)
function adminRedirect($flash = null) {
    if ($flash !== null) { $_SESSION['admin_flash'] = $flash; }
    header('Location: admin_collaborateurs.php' . adminFilterQuery());
    exit();
}

// Suppression d'utilisateur (GET)
if (isset($_GET['delete'])) {
    requireValidCSRF();
    $uid = $_GET['delete'];
    // Supprimer d'abord les statistiques liées
    $db->prepare("DELETE FROM statistiques WHERE utilisateur_id = ?")->execute([$uid]);
    // ... et le rattachement au secteur : rien ne le nettoie tout seul, et
    // un futur compte réutilisant cet id hériterait sinon de son secteur.
    famiOublieAffectation($db, $uid);
    // On ne supprime pas l'admin principal
    $stmt = $db->prepare("DELETE FROM utilisateurs WHERE id = ? AND identifiant != 'admin'");
    $stmt->execute([$uid]);
    $deleted = $stmt->rowCount();
    adminRedirect($deleted > 0
        ? "<div class='alert success'>✅ Utilisateur supprimé avec succès.</div>"
        : "<div class='alert error'>❌ Échec de la suppression (utilisateur introuvable ou admin principal protégé).</div>");
}

// Création de l'utilisateur spécial "Accueil" avec le rôle 'evaluateur' si non existant
try {
    $stmt = $db->prepare("SELECT id FROM utilisateurs WHERE identifiant = :id");
    $stmt->execute(['id' => 'Accueil']);
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO utilisateurs (identifiant, mot_de_passe, role) VALUES (:id, :pass, :role)");
        $stmt->execute([
            'id' => 'Accueil',
            'pass' => password_hash('1234', PASSWORD_DEFAULT),
            'role' => 'evaluateur'
        ]);
    } else {
        // Mise à jour du rôle si besoin
        $stmt = $db->prepare("UPDATE utilisateurs SET role = 'evaluateur' WHERE identifiant = :id");
        $stmt->execute(['id' => 'Accueil']);
    }
} catch (Exception $e) { /* déjà existant ou erreur */ }

// 1. GESTION DES ACTIONS
if (isset($_POST['creer_user'])) {
    requireValidCSRF();

    $id      = trim($_POST['new_username'] ?? '');
    $nom     = trim($_POST['new_nom'] ?? '');
    $prenom  = trim($_POST['new_prenom'] ?? '');
    $email   = trim($_POST['new_email'] ?? '');
    $interim = trim($_POST['new_interim'] ?? '');
    $mdp     = $_POST['new_password'] ?? '';
    $role    = $_POST['new_role'] ?? '';
    $confirmDuplicate = (($_POST['confirm_duplicate'] ?? '') === '1');
    $confirmNoEmail   = (($_POST['confirm_no_email'] ?? '') === '1');

    // Conserver les valeurs saisies pour les réafficher
    $createForm = [
        'new_username' => $id, 'new_nom' => $nom, 'new_prenom' => $prenom,
        'new_email' => $email, 'new_interim' => $interim,
        'new_role' => $role !== '' ? $role : 'beta',
    ];

    // Obligatoires : nom d'utilisateur, nom, prénom, profil (+ agence pour un étudiant).
    $manquants = [];
    if ($id === '')     { $manquants[] = "Nom d'utilisateur"; }
    if ($nom === '')    { $manquants[] = 'Nom'; }
    if ($prenom === '') { $manquants[] = 'Prénom'; }
    if ($role === '')   { $manquants[] = 'Profil'; }
    if ($role === 'etudiant' && $interim === '') { $manquants[] = 'Agence intérim'; }

    if (!$hasInterimColumn) {
        $message = "<div class='alert error'>❌ La colonne Intérim n'est pas disponible en base de données.</div>";
    } elseif (!empty($manquants)) {
        $message = "<div class='alert error'>❌ Champs obligatoires manquant(s) : " . e(implode(', ', $manquants)) . ".</div>";
    } elseif ($email === '' && $mdp === '') {
        $message = "<div class='alert error'>❌ Renseignez au moins un <strong>email</strong> ou un <strong>mot de passe</strong> (l'un des deux suffit pour permettre la connexion).</div>";
    } else {
        // Blocages nets (pas de "confirmer") : identifiant OU email déjà utilisé.
        // Les doublons nom + prénom sont autorisés (des personnes peuvent avoir le même nom).
        $checkId = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE identifiant = ?");
        $checkId->execute([$id]);
        $identifiantExiste = ((int) $checkId->fetchColumn() > 0);

        $emailExiste = false;
        if ($email !== '') {
            $checkEmail = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
            $checkEmail->execute([$email]);
            $emailExiste = ((int) $checkEmail->fetchColumn() > 0);
        }

        if ($identifiantExiste) {
            $message = "<div class='alert error'>❌ Ce nom d'utilisateur (identifiant) est déjà utilisé. Choisissez-en un autre.</div>";
        } elseif ($emailExiste) {
            $message = "<div class='alert error'>❌ Cet email est déjà utilisé par un autre compte : impossible de créer un second compte avec le même email.</div>";
        } else {
            {
                // Le compte n'est réellement créé QUE si le mail part correctement
                ensureUserAccountAccessColumns($db); // hors transaction (évite un ALTER pendant la transaction)
                $hash = $mdp !== ''
                    ? password_hash($mdp, PASSWORD_DEFAULT)
                    : password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $activationPending = ($mdp === '') ? 1 : 0;

                try {
                    $db->beginTransaction();
                    $ins = $db->prepare("INSERT INTO utilisateurs (identifiant, nom, prenom, email, interim, mot_de_passe, role, account_activation_pending, statut_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $interimStore = ($interim === '' || $interim === '__none__') ? null : $interim;
                    $emailStore = ($email === '') ? null : $email;
                    $ins->execute([$id, $nom, $prenom, $emailStore, $interimStore, $hash, $role, $activationPending]);
                    $userId = (int) $db->lastInsertId();

                    if ($email === '') {
                        // Aucun email : on crée le compte sans aucun envoi de mail
                        $db->commit();
                        $note = ($mdp === '')
                            ? " ⚠️ Aucun mot de passe ni email : définissez un mot de passe manuellement pour permettre la connexion."
                            : '';
                        adminRedirect("<div class='alert success'>✅ Collaborateur créé sans email." . $note . "</div>");
                    } else {
                        $mailSent = ($mdp === '')
                            ? sendAccountActivationEmail($db, $userId)
                            : sendStudentWelcomeEmail($email, $id, null);

                        if ($mailSent) {
                            $db->commit();
                            adminRedirect("<div class='alert success'>✅ Collaborateur créé. 📨 Le mail a bien été envoyé à " . e($email) . ".</div>");
                        } else {
                            $db->rollBack();
                            $mailError = trim((string) getLastMailError());
                            $details = $mailError !== '' ? '<br><small>Détail technique : ' . e($mailError) . '</small>' : '';
                            $message = "<div class='alert error'>❌ Le mail n'a pas pu être envoyé : le compte n'a donc <strong>pas été créé</strong>. Réessayez plus tard ou contactez le service IT.{$details}</div>";
                        }
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) { $db->rollBack(); }
                    $message = "<div class='alert error'>❌ Erreur lors de la création du compte : " . e($e->getMessage()) . "</div>";
                }
            }
        }
    }
}

if (isset($_POST['update_user'])) {
    requireValidCSRF();
    $uid = $_POST['user_id'] ?? '';
    $nouveau_role = $_POST['nouveau_role'] ?? '';
    $nouveau_mdp = $_POST['nouveau_mdp'] ?? '';
    $nouveau_interim = trim($_POST['nouveau_interim'] ?? '');
    // "Aucune agence" (__none__) ou vide -> NULL en base
    $interimVal = ($nouveau_interim === '' || $nouveau_interim === '__none__') ? null : $nouveau_interim;
    if (!$hasInterimColumn) {
        adminRedirect("<div class='alert error'>❌ La colonne Intérim n'est pas disponible en base de données.</div>");
    } elseif (!empty($nouveau_mdp)) {
        $hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE utilisateurs SET role = ?, interim = ?, mot_de_passe = ?, account_activation_pending = 0, account_access_token_hash = NULL, account_access_expires_at = NULL, account_access_type = NULL, statut_date = NOW() WHERE id = ?");
        $stmt->execute([$nouveau_role, $interimVal, $hash, $uid]);
        adminRedirect("<div class='alert success'>✅ Modifications enregistrées.</div>");
    } else {
        $stmt = $db->prepare("UPDATE utilisateurs SET role = ?, interim = ? WHERE id = ?");
        $stmt->execute([$nouveau_role, $interimVal, $uid]);
        adminRedirect("<div class='alert success'>✅ Modifications enregistrées.</div>");
    }
}

if (isset($_POST['update_email']) && isset($_POST['user_id'])) {
    requireValidCSRF();
    $uid = $_POST['user_id'];
    $nouveau_email = trim($_POST['nouveau_email'] ?? '');
    $stmt = $db->prepare("UPDATE utilisateurs SET email = ? WHERE id = ?");
    $stmt->execute([$nouveau_email, $uid]);
    adminRedirect("<div class='alert success'>✅ Email modifié.</div>");
}

if (isset($_POST['update_site']) && isset($_POST['user_id'])) {
    requireValidCSRF();
    $uid = (int) $_POST['user_id'];
    $siteVal = (($_POST['nouveau_site'] ?? '') === '') ? null : (int) $_POST['nouveau_site'];
    $stmt = $db->prepare("UPDATE utilisateurs SET site_id = ? WHERE id = ?");
    $stmt->execute([$siteVal, $uid]);
    adminRedirect("<div class='alert success'>✅ Lieu de travail modifié.</div>");
}

// Secteur : le rattachement ne vit pas dans `utilisateurs` mais dans sa
// propre table (voir includes/organisation.php), d'où l'appel dédié plutôt
// qu'un UPDATE de plus.
if (isset($_POST['update_secteur']) && isset($_POST['user_id'])) {
    requireValidCSRF();
    $uid = (int) $_POST['user_id'];

    // Une seule liste déroulante porte les deux niveaux, d'où ce petit codage :
    //   ''      → aucun rattachement
    //   's:12'  → tout le secteur 12, sans préciser le département
    //   'd:37'  → le département 37 (son secteur est déduit)
    // Deux listes liées auraient demandé du JavaScript pour filtrer la seconde
    // selon la première, dans chaque ligne du tableau. Une seule liste avec des
    // groupes dit la même chose et marche sans script.
    $choix = trim((string) ($_POST['nouveau_secteur'] ?? ''));

    try {
        if ($choix === '') {
            famiAffecteSecteur($db, $uid, null);
            adminRedirect("<div class='alert success'>✅ Rattachement retiré.</div>");
        }

        $type = substr($choix, 0, 2);
        $id   = (int) substr($choix, 2);

        if ($type === 's:') {
            famiAffecteSecteur($db, $uid, $id, null);
            adminRedirect("<div class='alert success'>✅ Secteur modifié.</div>");
        } elseif ($type === 'd:') {
            // Le secteur du département choisi : on ne le demande pas à
            // l'utilisateur, la base le sait déjà.
            $q = $db->prepare("SELECT secteur_id FROM famicard_departements WHERE id = ?");
            $q->execute([$id]);
            $secteurId = (int) $q->fetchColumn();
            if ($secteurId > 0 && famiAffecteSecteur($db, $uid, $secteurId, $id)) {
                adminRedirect("<div class='alert success'>✅ Département modifié.</div>");
            }
            adminRedirect("<div class='alert error'>❌ Ce département n'existe pas.</div>");
        }

        adminRedirect("<div class='alert error'>❌ Choix de rattachement invalide.</div>");
    } catch (Exception $e) {
        adminRedirect("<div class='alert error'>❌ Rattachement non enregistré : " . e($e->getMessage()) . "</div>");
    }
}

if (isset($_POST['set_inactif']) && isset($_POST['user_id'])) {
    requireValidCSRF();
    $uid = intval($_POST['user_id']);
    $stmt = $db->prepare("UPDATE utilisateurs SET statut = 'inactif', statut_date = NOW() WHERE id = ?");
    $stmt->execute([$uid]);
    adminRedirect("<div class='alert success'>✅ Utilisateur passé en inactif.</div>");
}
if (isset($_POST['set_actif']) && isset($_POST['user_id'])) {
    requireValidCSRF();
    $uid = intval($_POST['user_id']);
    $stmt = $db->prepare("UPDATE utilisateurs SET statut = NULL, statut_date = NOW() WHERE id = ?");
    $stmt->execute([$uid]);
    adminRedirect("<div class='alert success'>✅ Utilisateur réactivé.</div>");
}

// 2. RÉCUPÉRATION DES DONNÉES AVEC FILTRE
$role_filter = isset($_GET['filter_role']) ? $_GET['filter_role'] : '';
$agence_filter = isset($_GET['filter_agence']) ? trim($_GET['filter_agence']) : '';
$secteur_filter = isset($_GET['filter_secteur']) ? trim($_GET['filter_secteur']) : '';
$search_nom = trim($_GET['search_nom'] ?? '');

// Récupérer dynamiquement tous les thèmes de quiz
$themes_stmt = $db->query("SELECT DISTINCT theme FROM quiz_questions ORDER BY theme ASC");
$all_quiz_themes = $themes_stmt->fetchAll(PDO::FETCH_COLUMN);

// Liste dynamique des thèmes quiz
$liste_quiz_array = array_map('addslashes', $all_quiz_themes);
$liste_quiz = "'" . implode("','", $liste_quiz_array) . "'";
$total_quiz = count($liste_quiz_array);

$show_inactif = isset($_GET['show_inactif']) ? ($_GET['show_inactif'] === '1') : false;

// Mémorise la vue courante pour y revenir après une action (suppression, modif...)
$_SESSION['admin_filters'] = [
    'filter_role'   => $role_filter,
    'filter_agence' => $agence_filter,
    'filter_secteur' => $secteur_filter,
    'search_nom'    => $search_nom,
    'show_inactif'  => $show_inactif ? 1 : 0,
];

// Requête principale avec calcul dynamique du nombre de quiz réalisés.
// Le secteur arrive par jointure : il ne vit pas dans `utilisateurs`, mais
// la ligne obtenue se lit comme s'il y était (`secteur_id`, `secteur`),
// ce qui évite une seconde requête par collaborateur à l'affichage.
$selectSecteur = $secteursActifs
    ? ", aff.secteur_id AS secteur_id, sec.nom AS secteur"
    : '';
$joinSecteur = $secteursActifs
    ? " LEFT JOIN famicard_affectations aff ON aff.user_id = u.id
        LEFT JOIN famicard_secteurs sec ON sec.id = aff.secteur_id"
    : '';

if ($departementsActifs) {
    $selectSecteur .= ", aff.departement_id AS departement_id, dep.nom AS departement";
    $joinSecteur   .= " LEFT JOIN famicard_departements dep ON dep.id = aff.departement_id";
}

$query_str = "SELECT u.*,
      (SELECT COUNT(DISTINCT nom_page)
       FROM statistiques
       WHERE utilisateur_id = u.id
       AND nom_page IN ($liste_quiz)
       AND score IS NOT NULL) as nb_tests
      $selectSecteur
      FROM utilisateurs u$joinSecteur";

$where = [];
if ($role_filter != '') {
    $where[] = "u.role = " . $db->quote($role_filter);
}
if ($search_nom !== '') {
    $search = $db->quote('%' . $search_nom . '%');
    $where[] = "(u.nom LIKE $search OR u.prenom LIKE $search OR u.identifiant LIKE $search OR u.email LIKE $search)";
}
if ($agence_filter === '__none__') {
    $where[] = "(u.interim IS NULL OR u.interim = '')";
} elseif ($agence_filter !== '') {
    $where[] = "u.interim = " . $db->quote($agence_filter);
}
// Le filtre par secteur n'a de sens que si la jointure est là : sans elle,
// « aff » n'existe pas dans la requête et le SQL serait invalide.
if ($secteursActifs && $secteur_filter === '__none__') {
    $where[] = "aff.secteur_id IS NULL";
} elseif ($secteursActifs && $secteur_filter !== '') {
    $where[] = "aff.secteur_id = " . (int) $secteur_filter;
}
if ($show_inactif) {
    $where[] = "u.statut = 'inactif'";
} else {
    $where[] = "(u.statut IS NULL OR u.statut != 'inactif')";
}
$where[] = "u.role != 'agence_interim'";
if (count($where) > 0) {
    $query_str .= " WHERE " . implode(' AND ', $where);
}
$query_str .= " ORDER BY u.nom ASC";
$users = $db->query($query_str)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Collaborateurs - FamiFormation</title>
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: #f4f7f6; margin: 0; padding: 20px; }
        .container { max-width: 1520px; margin: 0 auto; }
        h1 { color: #2d5a37; text-align: center; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .warning { background: #fff3cd; color: #856404; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .card-header { background: #2d5a37; color: white; padding: 15px 25px; font-weight: bold; font-size: 1.1rem; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 25px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; table-layout: fixed; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; }
        th { background: #f8f9fa; color: #666; text-transform: uppercase; font-size: 0.8rem; position: sticky; top: 0; z-index: 1; }
        .input-mini { padding: 6px; border-radius: 4px; border: 1px solid #ccc; font-size: 0.9rem; }
        .btn-save { background: #2d5a37; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; }
        .btn-export { background: #1d6f42; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; display: inline-block; margin-bottom: 20px; }
        .filter-select { padding: 5px; border-radius: 4px; border: none; font-size: 0.85rem; cursor: pointer; }
        .hint { color: #666; font-size: 0.85rem; margin-top: 10px; }
        .table-wrap { overflow-x: auto; padding: 0 0 8px 0; }
        .col-name { width: 15%; }
        .col-login { width: 9%; }
        .col-email { width: 12%; }
        .col-interim { width: 12%; }
        .col-secteur { width: 15%; }
        .col-quiz { width: 8%; text-align: center; }
        .col-role { width: 17%; }
        .col-status { width: 10%; }
        .col-actions { width: 12%; }
        .status-date-cell { text-align: center; white-space: nowrap; font-size: 0.85rem; }
        .name-link { color: #1d6f42; text-decoration: none; font-weight: 700; }
        .name-link:hover { text-decoration: underline; }
        .muted-code { display: inline-block; background: #f4f7f6; border-radius: 6px; padding: 4px 8px; font-size: 0.85rem; }
        .stack-form { display: flex; align-items: center; gap: 8px; }
        .role-form { display: flex; flex-direction: column; gap: 6px; }
        .role-form-row { display: flex; gap: 6px; align-items: center; }
        .cell-note { font-size: 0.78rem; color: #777; margin-top: 6px; }
        .quiz-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 88px; padding: 8px 10px; border-radius: 999px; background: #f0f7f1; color: #2d5a37; font-size: 0.85rem; font-weight: 700; }
        .status-cell { text-align: center; }
        .status-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 92px; padding: 8px 10px; border-radius: 999px; font-size: 0.82rem; font-weight: 700; }
        .status-active { background: #e8f5e9; color: #2d5a37; }
        .status-inactive { background: #f9e1e1; color: #a83232; }
        .status-pending { background: #fff3cd; color: #856404; }
        .modal-backdrop { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 2000; }
        .modal-card { background: #fff; border-radius: 14px; padding: 28px; max-width: 460px; width: 90%; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
        .btn-cancel { background: #e9ecef; color: #333; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .btn-cancel:hover { background: #dde1e5; }
        .action-stack { display: flex; flex-direction: column; align-items: stretch; gap: 8px; min-width: 130px; }
        .action-stack form,
        .action-stack a { margin: 0; }
        .btn-delete-link { display: inline-flex; align-items: center; justify-content: center; background: #f9e1e1; color: #a83232; text-decoration: none; padding: 7px 10px; border-radius: 8px; font-size: 0.82rem; font-weight: 700; }
        .btn-delete-link:hover { background: #f4caca; }
        .sticky-name { position: sticky; left: 0; background: #fff; z-index: 2; box-shadow: 8px 0 14px rgba(0,0,0,0.03); }
        th.sticky-name { background: #f8f9fa; z-index: 3; }
        .stack-form .input-mini,
        .stack-form select { width: 100% !important; min-width: 0; }
        .role-form > select.input-mini { width: 100%; min-width: 0; }
        .role-form-row .input-mini { flex: 1; min-width: 0; }
        .role-form-row .btn-save { width: 42px; padding: 6px 0; flex-shrink: 0; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <a href="admin.php" style="text-decoration:none; color:#2d5a37; font-weight:bold;">⬅ Espace Administration</a>
        <h1>Collaborateurs</h1>
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="admin_agences_interim.php" class="btn-export" style="background:#2d5a37;">🏢 Agences Intérim</a>
            <a href="export_excel.php" class="btn-export">📊 Export Excel Complet</a>
        </div>
    </div>

    <?php echo $message; ?>

    <div class="card">
        <div class="card-header" style="font-size:1.35rem;">Ajouter un nouveau collaborateur</div>
        <div class="card-body">
            <form method="POST" action="admin_collaborateurs.php" id="createUserForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <?php echo csrfField(); ?>
                <input type="text" name="new_username" value="<?php echo htmlspecialchars($createForm['new_username']); ?>" placeholder="Identifiant" required class="input-mini">
                <input type="text" name="new_nom" value="<?php echo htmlspecialchars($createForm['new_nom']); ?>" placeholder="Nom" required class="input-mini">
                <input type="text" name="new_prenom" value="<?php echo htmlspecialchars($createForm['new_prenom']); ?>" placeholder="Prénom" required class="input-mini">
                <input type="email" name="new_email" value="<?php echo htmlspecialchars($createForm['new_email']); ?>" placeholder="Email (optionnel)" class="input-mini">
                <select name="new_interim" id="new_interim" class="input-mini" required>
                    <option value="" <?php if ($createForm['new_interim'] === '') echo 'selected'; ?>>Sélectionner une agence intérim</option>
                    <?php foreach ($agencesInterim as $agenceNom): ?>
                        <option value="<?php echo htmlspecialchars($agenceNom); ?>" <?php if ($createForm['new_interim'] === $agenceNom) echo 'selected'; ?>><?php echo htmlspecialchars($agenceNom); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="password" name="new_password" placeholder="Mot de passe (optionnel)" class="input-mini">
                <select name="new_role" id="new_role" class="input-mini" required>
                    <option value="beta" <?php if ($createForm['new_role'] === 'beta') echo 'selected'; ?>>Beta 🧪</option>
                    <option value="betalapanne" <?php if ($createForm['new_role'] === 'betalapanne') echo 'selected'; ?>>Beta La Panne 🌊</option>
                    <option value="etudiant" <?php if ($createForm['new_role'] === 'etudiant') echo 'selected'; ?>>Étudiant</option>
                    <option value="employe_magasin" <?php if ($createForm['new_role'] === 'employe_magasin') echo 'selected'; ?>>Magasin</option>
                    <option value="teamcoach" <?php if ($createForm['new_role'] === 'teamcoach') echo 'selected'; ?>>Teamcoach</option>
                    <option value="mentor" <?php if ($createForm['new_role'] === 'mentor') echo 'selected'; ?>>Mentor</option>
                    <option value="employe_logistique" <?php if ($createForm['new_role'] === 'employe_logistique') echo 'selected'; ?>>Logistique</option>
                    <option value="admin" <?php if ($createForm['new_role'] === 'admin') echo 'selected'; ?>>Admin</option>
                    <option value="evaluateur" <?php if ($createForm['new_role'] === 'evaluateur') echo 'selected'; ?>>Évaluateur</option>
                </select>
                <button type="submit" name="creer_user" class="btn-save" style="height: 40px;">Créer le compte</button>
            </form>
            <div class="hint">Obligatoires : nom d'utilisateur, nom, prénom, et <strong>au moins l'un des deux</strong> : email ou mot de passe. Avec un mot de passe, le compte est créé directement. Avec un email seul (sans mot de passe), un mail d'activation est envoyé et le compte n'est créé que si ce mail part correctement. L'identifiant et l'email ne peuvent pas être en double (nom + prénom identiques sont autorisés). La liste des agences vient de la page Agences Intérim.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Collaborateurs</span>
            <div style="background:#2d5a37; padding:4px 8px; border-radius:8px; margin-bottom:14px; display:flex; align-items:center; gap:0; height:28px; min-height:28px; max-height:28px;">
                <form method="GET" id="filterForm" style="display:flex; flex:1; align-items:center; gap:0; width:100%;">
                    <div style="flex:2; display:flex; align-items:center;">
                        <input id="search_nom" type="text" name="search_nom" value="<?php echo htmlspecialchars($_GET['search_nom'] ?? ''); ?>" placeholder="Rechercher un collaborateur..." class="input-mini" style="height:36px; width:220px; margin-right:18px; border:1px solid #ccc; font-size:1em;" />
                        <button type="submit" class="btn-save" style="height:32px; min-width:32px; font-size:1.1em; padding:0 8px; margin-right:18px;">🔍</button>
                    </div>
                    <div style="flex:1; display:flex; align-items:center; justify-content:center;">
                        <select id="filter_agence" name="filter_agence" class="filter-select" style="height:32px; min-width:120px; font-size:1em; margin-right:18px;" onchange="document.getElementById('filterForm').submit()">
                            <option value="">Toutes les agences</option>
                            <?php foreach ($agencesInterim as $agenceNom): ?>
                                <option value="<?php echo htmlspecialchars($agenceNom); ?>" <?php if($agence_filter === $agenceNom) echo 'selected'; ?>><?php echo htmlspecialchars($agenceNom); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($secteursActifs): ?>
                    <div style="flex:1; display:flex; align-items:center; justify-content:center;">
                        <select id="filter_secteur" name="filter_secteur" class="filter-select" style="height:32px; min-width:120px; font-size:1em; margin-right:18px;" onchange="document.getElementById('filterForm').submit()">
                            <option value="">Tous les secteurs</option>
                            <option value="__none__" <?php if ($secteur_filter === '__none__') echo 'selected'; ?>>Sans secteur</option>
                            <?php foreach ($secteursListe as $secId => $secNom): ?>
                                <option value="<?php echo (int) $secId; ?>" <?php if ($secteur_filter !== '' && (int) $secteur_filter === (int) $secId) echo 'selected'; ?>><?php echo htmlspecialchars($secNom); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div style="flex:1; display:flex; align-items:center; justify-content:center;">
                        <select id="filter_role" name="filter_role" class="filter-select" style="height:32px; min-width:120px; font-size:1em; margin-right:18px;" onchange="document.getElementById('filterForm').submit()">
                            <option value="">Tous les profils</option>
                            <option value="beta" <?php if($role_filter == 'beta') echo 'selected'; ?>>Beta 🧪</option>
                            <option value="betalapanne" <?php if($role_filter == 'betalapanne') echo 'selected'; ?>>Beta La Panne 🌊</option>
                            <option value="etudiant" <?php if($role_filter == 'etudiant') echo 'selected'; ?>>Étudiant</option>
                            <option value="employe_magasin" <?php if($role_filter == 'employe_magasin') echo 'selected'; ?>>Magasin</option>
                            <option value="teamcoach" <?php if($role_filter == 'teamcoach') echo 'selected'; ?>>Teamcoach</option>
                            <option value="mentor" <?php if($role_filter == 'mentor') echo 'selected'; ?>>Mentor</option>
                            <option value="employe_logistique" <?php if($role_filter == 'employe_logistique') echo 'selected'; ?>>Logistique</option>
                            <option value="admin" <?php if($role_filter == 'admin') echo 'selected'; ?>>Admin</option>
                            <option value="evaluateur" <?php if($role_filter == 'evaluateur') echo 'selected'; ?>>Évaluateur</option>
                        </select>
                    </div>
                    <div style="flex:1; display:flex; align-items:center; justify-content:flex-end;">
                        <input id="show_inactif" type="checkbox" name="show_inactif" value="1" <?php if($show_inactif) echo 'checked'; ?> style="height:22px; width:22px; margin-right:8px;" onchange="document.getElementById('filterForm').submit()">
                        <label for="show_inactif" style="color:white; font-size:1em; margin-right:8px;">Inactif</label>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <div style="padding:16px 25px 0 25px;">
                <a href="admin_evaluations_orphelines.php" style="color:#b00;font-weight:bold;text-decoration:none;">Évaluations orphelines</a>
            </div>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="col-name sticky-name">Collaborateur</th>
                        <th class="col-login">Identifiant</th>
                        <th class="col-email">Email</th>
                        <th class="col-interim">Intérim</th>
                        <th class="col-site">Lieu de travail</th>
                        <?php if ($secteursActifs): ?><th class="col-secteur"><?= $departementsActifs ? 'Secteur / Département' : 'Secteur' ?></th><?php endif; ?>
                        <th class="col-quiz">Quiz</th>
                        <th class="col-role">Profil</th>
                        <th class="col-status">Statut</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="sticky-name">
                            <a href="admin_user.php?id=<?php echo (int) $u['id']; ?>" class="name-link"><?php echo htmlspecialchars($u['nom'] . " " . $u['prenom']); ?></a>
                            <div class="cell-note">
                                <?php echo htmlspecialchars($u['role']); ?>
                                <?php /* Second accès à la fiche, volontairement distinct du nom :
                                         le clic sur le nom reste sans effet et la cause n'a pas pu
                                         être identifiée dans le code. Ce lien-ci ne partage ni sa
                                         position ni son empilement, il aboutit donc même si le
                                         premier est recouvert par un élément. */ ?>
                                · <a href="admin_user.php?id=<?php echo (int) $u['id']; ?>" class="fiche-link">fiche ↗</a>
                            </div>
                        </td>
                        <td><span class="muted-code"><?php echo htmlspecialchars($u['identifiant']); ?></span></td>
                        <td>
                            <form method="POST" class="stack-form" onsubmit="return confirmEmailForm(this);">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <input type="email" name="nouveau_email" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" data-original="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" placeholder="Email" class="input-mini" style="width:140px;">
                                <button type="submit" name="update_email" class="btn-save" style="padding:2px 8px; font-size:0.9em;">✉️</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" class="stack-form" onsubmit="return confirm('Modifier l\'agence de ce collaborateur ?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <select name="nouveau_interim" class="input-mini" style="width:180px;">
                                    <option value="" <?php if (empty($u['interim'])) echo 'selected'; ?>>— Sélectionner —</option>
                                    <?php foreach ($agencesInterim as $agenceNom): ?>
                                        <option value="<?php echo htmlspecialchars($agenceNom); ?>" <?php if (($u['interim'] ?? '') === $agenceNom) echo 'selected'; ?>><?php echo htmlspecialchars($agenceNom); ?></option>
                                    <?php endforeach; ?>
                                    <?php if (!empty($u['interim']) && !in_array($u['interim'], $agencesInterim, true)): ?>
                                        <option value="<?php echo htmlspecialchars($u['interim']); ?>" selected><?php echo htmlspecialchars($u['interim']); ?></option>
                                    <?php endif; ?>
                                </select>
                                <input type="hidden" name="nouveau_role" value="<?php echo htmlspecialchars($u['role']); ?>">
                                <button type="submit" name="update_user" class="btn-save" style="padding:2px 8px; font-size:0.9em;">🏢</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" class="stack-form" onsubmit="return confirm('Modifier le lieu de travail de ce collaborateur ?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <select name="nouveau_site" class="input-mini" style="width:160px;">
                                    <option value="" <?php if (empty($u['site_id'])) echo 'selected'; ?>>— Aucun —</option>
                                    <?php foreach ($widgetSitesList as $wSite): ?>
                                        <option value="<?php echo (int) $wSite['id']; ?>" <?php if ((string) ($u['site_id'] ?? '') === (string) $wSite['id']) echo 'selected'; ?>><?php echo htmlspecialchars($wSite['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_site" class="btn-save" style="padding:2px 8px; font-size:0.9em;">📍</button>
                            </form>
                        </td>
                        <?php if ($secteursActifs): ?>
                        <td>
                            <?php // Secteur : les 8 ensembles de l'entreprise. Le département (l'échelon
                                  // en dessous) n'existe pas encore — voir includes/organisation.php. ?>
                            <form method="POST" class="stack-form" onsubmit="return confirm('Modifier le rattachement de ce collaborateur ?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <?php
                                    // Sélection courante, dans le codage décrit plus haut :
                                    // un département s'il y en a un, sinon le secteur seul.
                                    $choixCourant = '';
                                    if (!empty($u['departement_id'])) {
                                        $choixCourant = 'd:' . (int) $u['departement_id'];
                                    } elseif (!empty($u['secteur_id'])) {
                                        $choixCourant = 's:' . (int) $u['secteur_id'];
                                    }
                                ?>
                                <select name="nouveau_secteur" class="input-mini" style="width:210px;">
                                    <option value="" <?php if ($choixCourant === '') echo 'selected'; ?>>— Aucun —</option>
                                    <?php foreach ($secteursListe as $secId => $secNom): ?>
                                        <?php $deps = $departementsParSecteur[(int) $secId] ?? []; ?>
                                        <?php if (!$deps): ?>
                                            <option value="s:<?php echo (int) $secId; ?>" <?php if ($choixCourant === 's:' . (int) $secId) echo 'selected'; ?>><?php echo htmlspecialchars($secNom); ?></option>
                                        <?php else: ?>
                                            <optgroup label="<?php echo htmlspecialchars($secNom); ?>">
                                                <?php // « Tout le secteur » d'abord : on peut appartenir à un
                                                      // secteur sans département précis (Bureau, par exemple). ?>
                                                <option value="s:<?php echo (int) $secId; ?>" <?php if ($choixCourant === 's:' . (int) $secId) echo 'selected'; ?>>— tout le secteur —</option>
                                                <?php foreach ($deps as $depId => $depNom): ?>
                                                    <option value="d:<?php echo (int) $depId; ?>" <?php if ($choixCourant === 'd:' . (int) $depId) echo 'selected'; ?>><?php echo htmlspecialchars($depNom); ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_secteur" class="btn-save" style="padding:2px 8px; font-size:0.9em;">🏬</button>
                            </form>
                        </td>
                        <?php endif; ?>
                        <td class="col-quiz">
                            <span class="quiz-badge">
                                <?php echo $u['nb_tests']; ?> / <?php echo $total_quiz; ?> quiz
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="role-form" onsubmit="return confirmRoleForm(this);">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <input type="hidden" name="nouveau_interim" value="<?php echo htmlspecialchars($u['interim'] ?? ''); ?>">
                                <select name="nouveau_role" class="input-mini" data-original="<?php echo htmlspecialchars($u['role']); ?>">
                                    <option value="beta" <?php if($u['role']=='beta') echo 'selected'; ?>>Beta 🧪</option>
                                    <?php // 🏬 Sans cette option, éditer un compte « betalapanne » le ferait
                                          // basculer en silence sur le premier rôle de la liste. ?>
                                    <option value="betalapanne" <?php if($u['role']=='betalapanne') echo 'selected'; ?>>Beta La Panne 🌊</option>
                                    <option value="etudiant" <?php if($u['role']=='etudiant') echo 'selected'; ?>>Étudiant</option>
                                    <option value="employe_magasin" <?php if($u['role']=='employe_magasin') echo 'selected'; ?>>Magasin</option>
                                    <option value="teamcoach" <?php if($u['role']=='teamcoach') echo 'selected'; ?>>Teamcoach</option>
                                    <option value="mentor" <?php if($u['role']=='mentor') echo 'selected'; ?>>Mentor</option>
                                    <option value="employe_logistique" <?php if($u['role']=='employe_logistique') echo 'selected'; ?>>Logistique</option>
                                    <option value="admin" <?php if($u['role']=='admin') echo 'selected'; ?>>Admin</option>
                                    <option value="evaluateur" <?php if($u['role']=='evaluateur') echo 'selected'; ?>>Évaluateur</option>
                                </select>
                                <div class="role-form-row">
                                    <input type="password" name="nouveau_mdp" placeholder="Nouveau mot de passe" class="input-mini">
                                    <button type="submit" name="update_user" class="btn-save">💾</button>
                                </div>
                            </form>
                            <div class="cell-note">Le mot de passe peut rester vide si tu ne veux pas le changer.</div>
                        </td>
                        <td class="status-cell">
                            <?php if (($u['statut'] ?? '') === 'inactif'): ?>
                                <span class="status-badge status-inactive">Inactif</span>
                            <?php elseif (!empty($u['account_activation_pending']) || empty($u['mot_de_passe'])): ?>
                                <span class="status-badge status-pending">En attente</span>
                            <?php else: ?>
                                <span class="status-badge status-active">Actif</span>
                            <?php endif; ?>
                            <?php
                                $statutDate = $u['statut_date'] ?? null;
                                $tsStatut = $statutDate ? strtotime((string) $statutDate) : false;
                            ?>
                            <div style="margin-top:6px; font-size:0.76rem; color:#888;"><?= $tsStatut ? htmlspecialchars(date('d/m/Y', $tsStatut)) : '—' ?></div>
                        </td>
                        <td>
                            <?php if ($u['identifiant'] !== 'admin'): ?>
                                <div class="action-stack">
                                    <?php if (($u['statut'] ?? '') === 'inactif'): ?>
                                        <form method="POST">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="set_actif" value="1">
                                            <button type="submit" class="btn-save" style="width:100%;background:#2d5a37;color:white;">✔ Activer</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="set_inactif" value="1">
                                            <button type="submit" class="btn-save" style="width:100%;background:#a83232;color:white;">✖ Désactiver</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $u['id']; ?>&csrf=<?php echo getCSRFToken(); ?>" class="btn-delete-link" onclick="return confirm('Supprimer définitivement cet utilisateur ?');">🗑 Supprimer</a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
<script>
// Confirmation seulement si l'adresse email est réellement modifiée
function confirmEmailForm(form) {
    var mail = form.querySelector('input[name="nouveau_email"]');
    if (mail && mail.value.trim() !== (mail.getAttribute('data-original') || '')) {
        return confirm('Modifier l\'adresse email de ce collaborateur ?');
    }
    return true;
}

// Confirmation si le profil change et/ou si un nouveau mot de passe est saisi
function confirmRoleForm(form) {
    var changes = [];
    var role = form.querySelector('select[name="nouveau_role"]');
    if (role && role.value !== role.getAttribute('data-original')) {
        changes.push('le profil');
    }
    var pw = form.querySelector('input[name="nouveau_mdp"]');
    if (pw && pw.value.trim() !== '') {
        changes.push('le mot de passe');
    }
    if (changes.length === 0) {
        return true;
    }
    return confirm('Modifier ' + changes.join(' et ') + ' de ce collaborateur ?');
}

const roleField = document.getElementById('new_role');
const interimField = document.getElementById('new_interim');

function syncInterimRequirement() {
    if (!roleField || !interimField) {
        return;
    }

    const isStudent = roleField.value === 'etudiant';
    interimField.required = isStudent;
}

syncInterimRequirement();
if (roleField) {
    roleField.addEventListener('change', syncInterimRequirement);
}
</script>
</body>
</html>