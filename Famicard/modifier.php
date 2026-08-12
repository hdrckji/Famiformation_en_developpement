<?php
// ============================================================
// FAMICARD — MODIFIER UNE FICHE.
//
// UN SEUL ÉCRAN pour les deux usages : le collaborateur qui corrige ses
// coordonnées, et l'administrateur qui édite la fiche de quelqu'un
// (« modifier.php?id=N »). Deux formulaires auraient fini par diverger, et
// c'est toujours celui qu'on regarde le moins qui laisse passer une écriture
// qu'il n'aurait pas dû permettre.
//
// C'est famicardPeutModifier() qui décide, champ par champ — pas cette page.
// Le test est fait DEUX FOIS : à l'affichage, pour ne montrer que ce qui est
// éditable, et à l'enregistrement, pour refuser un champ ajouté à la main dans
// la requête. Un formulaire n'est pas une autorisation.
//
// LA PHOTO EST ICI, en tête, et plus sur une page à part : changer sa photo et
// corriger son adresse sont le même geste — « je mets ma fiche à jour ». Deux
// écrans pour ça, c'était un aller-retour pour rien.
//
// Ce que le collaborateur change s'applique TOUT DE SUITE et part en
// validation (voir includes/modifications.php). Ce que l'admin change est
// tracé mais déjà validé : il n'a personne à qui demander.
//
// ⚠️ LA PHOTO ÉCHAPPE À LA VALIDATION, volontairement : elle est libre et
// illimitée (décision Jimmy). Elle est tracée comme déjà validée — l'historique
// la garde, mais elle n'encombre pas l'écran des décisions à prendre.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/modifications.php';

$moi = famicardExigeConnexion($db);
$estAdmin = famicardEstAdmin();

// Cible : une autre fiche (admin) ou la sienne.
$cibleId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $moi['id'];
if (!$estAdmin && $cibleId !== (int) $moi['id']) {
    // Un non-admin qui bricole l'URL est renvoyé sur sa propre fiche, sans
    // message d'erreur : inutile de lui confirmer que la fiche visée existe.
    header('Location: modifier.php');
    exit();
}

$estSaPropreFiche = ($cibleId === (int) $moi['id']);

$st = $db->prepare("SELECT * FROM utilisateurs WHERE id = ? LIMIT 1");
$st->execute([$cibleId]);
$cible = $st->fetch(PDO::FETCH_ASSOC);
if (!$cible) {
    header('Location: ' . ($estAdmin ? 'admin.php' : 'fiche.php'));
    exit();
}

famicardAssureModifications($db);

// Les colonnes d'emploi (employeur, contrat) sont créées par un ADMIN qui
// passe ici : c'est de la DDL sur `utilisateurs`, on ne la fait pas exécuter
// par tout le monde. Tant qu'elles manquent, famicardChamps() retire
// simplement les deux champs — l'écran ne casse pas, il est juste plus court.
if ($estAdmin) {
    famicardAssureEmploi($db);
}

// Secteur et département : posés dans la ligne comme pseudo-colonnes, pour que
// le modèle les lise comme les autres champs (ils vivent en réalité dans
// student_department_links).
$cible = famicardAjouteRattachement($cible, famicardRattachements($db, [$cibleId]));

$champs   = famicardChamps($db);
$magasins = famicardMagasins($db);
$libres   = famicardValeursLibres($db, $cibleId);
$groupes  = famicardGroupes();

// ─────────────────────────────────────────────────────────────────────────────
// LE RATTACHEMENT, MODIFIABLE — mais par la LISTE ENTIÈRE.
//
// Il est resté en lecture seule longtemps, pour une raison réelle :
// `student_department_links` porte PLUSIEURS départements par personne, classés
// par priorité, et c'est ce dont le matching intérim de FamiJob se sert. Un
// écran qui n'en connaît qu'un et écrit « le » département efface les autres —
// sans message, sans trace, et on ne s'en aperçoit qu'en cherchant un
// remplaçant qui n'apparaît plus.
//
// La sortie n'est pas de compter les cas, c'est de changer ce qu'on envoie :
// le formulaire porte TOUJOURS la liste complète, une case par rattachement
// plus une vide. On écrit donc un ÉTAT FINAL (famicardEcritRattachement), et
// plus rien ne peut disparaître par omission.
//
// L'ORDRE EST LA PRIORITÉ : la première case est le rattachement principal.
// Un numéro de priorité saisi à côté aurait fini par ne plus correspondre à
// l'ordre affiché, et deux départements auraient partagé le même rang.
// ─────────────────────────────────────────────────────────────────────────────

// Les départements réellement proposables, secteur par secteur — la même liste
// que le menu en cascade, y compris les « à ranger » : un département sans
// secteur reste un rattachement valable, et l'omettre l'effacerait de la fiche
// de ceux qui l'ont.
$departementsProposes = [];
if (function_exists('secteursListe')) {
    try {
        foreach (secteursListe($db) as $s) {
            foreach ($s['departements'] as $d) {
                $departementsProposes[(int) $d['id']] = (string) $d['nom'];
            }
        }
        foreach (secteursSansSecteur($db) as $o) {
            $departementsProposes[(int) $o['id']] = (string) $o['department_name'];
        }
    } catch (Exception $e) {
        $departementsProposes = [];
    }
}

$champRattachement = $champs['departement'] ?? null;
$rattachementEditable = ($champRattachement !== null)
    && $departementsProposes
    && function_exists('secteursChampsHtml')
    && famicardPeutModifier($champRattachement, $estAdmin, $estSaPropreFiche);

// Ce qu'il a aujourd'hui, dans l'ordre de priorité.
$rattachementActuel = array_values(array_map('intval', (array) ($cible['departement_ids'] ?? [])));

// ─────────────────────────────────────────────────────────────────────────────
// STOCKAGE DE LA PHOTO — celui du SITE, volontairement : même dossier sur le
// volume, même colonne `utilisateurs.photo_profil`, même compression. Un second
// emplacement, et la photo affichée par FamiFormation ne serait plus celle
// déposée ici. Le repli passe par famicardRacineSite() : « __DIR__ »
// désignerait Famicard, donc un uploads/ qui n'est pas celui du site.
// ─────────────────────────────────────────────────────────────────────────────
$storeBase = defined('FAMI_STORAGE_BASE') ? rtrim(FAMI_STORAGE_BASE, '/') : (famicardRacineSite() . '/uploads');
$uploadDir = $storeBase . '/divers/profils/';

/** Chemin absolu d'une photo déjà enregistrée (clé volume OU ancien public/uploads). */
function famicardCheminPhoto($valeur, $storeBase)
{
    $valeur = (string) $valeur;
    if ($valeur === '') {
        return '';
    }
    return (strpos($valeur, 'uploads/') === 0)
        ? famicardRacineSite() . '/' . $valeur
        : $storeBase . '/' . $valeur;
}

// Le champ photo est-il modifiable par le regardeur ? La zone d'envoi n'est
// affichée que si oui — et le POST est refusé dans le cas contraire.
$champPhoto = $champs['photo_profil'] ?? null;
$photoEditable = $champPhoto ? famicardPeutModifier($champPhoto, $estAdmin, $estSaPropreFiche) : false;

$erreurs = [];
// Ce qui n'empêche pas d'enregistrer mais qu'il faut dire quand même. Un
// avertissement rangé dans les erreurs bloquerait la fiche entière ; rangé
// nulle part, il ne serait jamais lu.
$avertissements = [];
$message = '';
if (!empty($_SESSION['famicard_modif_flash'])) {
    $message = (string) $_SESSION['famicard_modif_flash'];
    unset($_SESSION['famicard_modif_flash']);
}

/** Valeur brute actuelle d'un champ (celle qui est en base, pas celle affichée). */
function famicardValeurBrute($cle, array $champ, array $ligne, array $libres)
{
    if (!empty($champ['champ_id'])) {
        return (string) ($libres[(int) $champ['champ_id']] ?? '');
    }
    $colonne = (string) ($champ['colonne'] ?? '');
    if ($colonne === '' || !array_key_exists($colonne, $ligne)) {
        return '';
    }
    return (string) ($ligne[$colonne] ?? '');
}

function famicardRetourModif($flash, $estSaPropreFiche, $cibleId)
{
    $_SESSION['famicard_modif_flash'] = $flash;
    header('Location: modifier.php' . ($estSaPropreFiche ? '' : '?id=' . (int) $cibleId));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRF();

    // ── RETRAIT DE LA PHOTO ──────────────────────────────────────────────
    if (isset($_POST['supprimer_photo'])) {
        if (!$photoEditable) {
            famicardRetourModif("Tu n'as pas la main sur cette photo.", $estSaPropreFiche, $cibleId);
        }

        $ancienne = famicardCheminPhoto((string) ($cible['photo_profil'] ?? ''), $storeBase);
        if ($ancienne !== '' && is_file($ancienne)) {
            @unlink($ancienne);
        }
        $db->prepare("UPDATE utilisateurs SET photo_profil = NULL WHERE id = ?")->execute([$cibleId]);
        if ($estSaPropreFiche) {
            $_SESSION['photo_profil'] = null;
        }
        famicardTraceModification($db, $cibleId, 'photo_profil', $champPhoto ?: ['libelle' => 'Photo'], 'photo', '', (int) $moi['id'], false);

        famicardRetourModif('✅ Photo supprimée.', $estSaPropreFiche, $cibleId);
    }

    // ── DÉPÔT D'UNE PHOTO ────────────────────────────────────────────────
    // Traité avant les champs : si l'image est refusée, on le dit tout de
    // suite plutôt que d'enregistrer le reste en laissant croire que tout
    // est passé.
    $photoDeposee = false;
    if ($photoEditable && isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo_profil'];
        $typesAutorises = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $tailleMax = 5 * 1024 * 1024; // 5 Mo

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = "L'envoi de la photo n'a pas abouti.";
        } elseif ($file['size'] > $tailleMax) {
            $erreurs[] = 'Photo trop lourde (5 Mo maximum).';
        } elseif (!in_array($file['type'], $typesAutorises, true)) {
            $erreurs[] = 'Format de photo non accepté : JPEG, PNG, GIF ou WebP.';
        } elseif (!@getimagesize($file['tmp_name'])) {
            // Le type annoncé par le navigateur se falsifie ; le contenu, non.
            $erreurs[] = "Ce fichier n'est pas une image.";
        } else {
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $nomFichier = 'user_' . $cibleId . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $nomFichier;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // L'ancienne part APRÈS que la nouvelle est en place : dans
                // l'autre sens, un échec d'écriture laisserait la fiche sans
                // photo du tout.
                $ancienne = famicardCheminPhoto((string) ($cible['photo_profil'] ?? ''), $storeBase);
                if ($ancienne !== '' && is_file($ancienne) && $ancienne !== $destination) {
                    @unlink($ancienne);
                }

                $compress = famicardRacineSite() . '/includes/compress.php';
                if (is_file($compress)) {
                    require_once $compress;
                    if (function_exists('famiCompressImageFile')) {
                        famiCompressImageFile($destination, 600);
                    }
                }

                $cheminRelatif = 'divers/profils/' . $nomFichier;
                $db->prepare("UPDATE utilisateurs SET photo_profil = ? WHERE id = ?")
                   ->execute([$cheminRelatif, $cibleId]);
                if ($estSaPropreFiche) {
                    // Le ruban du site lit la photo dans la session : sans ça,
                    // l'ancienne resterait affichée jusqu'à la reconnexion.
                    $_SESSION['photo_profil'] = $cheminRelatif;
                }
                famicardTraceModification($db, $cibleId, 'photo_profil', $champPhoto ?: ['libelle' => 'Photo'], '', 'photo', (int) $moi['id'], false);
                $photoDeposee = true;
            } else {
                $erreurs[] = "La photo n'a pas pu être enregistrée.";
            }
        }
    }

    // ── LE RATTACHEMENT ──────────────────────────────────────────────────
    // Traité à part des autres champs : ce n'est pas une colonne de
    // `utilisateurs` mais une LISTE dans une autre table, et elle s'écrit
    // entière (voir famicardEcritRattachement).
    $rattachementChange = false;
    if ($rattachementEditable && array_key_exists('rattachement', $_POST)) {
        $voulus = [];
        foreach ((array) $_POST['rattachement'] as $id) {
            $id = (int) $id;
            // Seulement ce que la page a réellement proposé : un identifiant
            // bricolé dans le formulaire ne peut pas rattacher quelqu'un à un
            // département inconnu du matching.
            if ($id > 0 && isset($departementsProposes[$id]) && !in_array($id, $voulus, true)) {
                $voulus[] = $id;
            }
        }

        // ⚠️ GARDE-FOU. Si un département qu'il a DÉJÀ n'est plus dans la liste
        // proposée (désactivé, ou rattaché à un secteur supprimé), la case
        // correspondante est vide et l'enregistrement l'effacerait en silence.
        // On refuse alors d'écrire le rattachement — le reste de la fiche
        // s'enregistre — plutôt que de perdre une donnée qu'on n'a pas touchée.
        $perdus = array_diff($rattachementActuel, array_keys($departementsProposes));
        if ($perdus) {
            // Signalé, pas bloquant : le reste de la fiche doit pouvoir
            // s'enregistrer. Bloquer sur un département disparu ferait d'une
            // vieille donnée un mur devant une correction d'adresse.
            $avertissements[] = 'Un de ses départements ne figure plus dans la liste des départements actifs :'
                              . ' son rattachement n\'a pas été touché, pour ne rien effacer par accident.'
                              . ' Il se corrige depuis FamiJob.';
        } elseif ($voulus !== $rattachementActuel) {
            $avant = [];
            foreach ($rattachementActuel as $id) {
                $avant[] = $departementsProposes[$id] ?? ('#' . $id);
            }
            $apres = [];
            foreach ($voulus as $id) {
                $apres[] = $departementsProposes[$id];
            }

            if (famicardEcritRattachement($db, $cibleId, $voulus)) {
                $rattachementChange = true;
                // Tracé comme le reste : « qui a changé ce rattachement » est
                // la première question posée devant un matching surprenant.
                //
                // Tracé DÉJÀ VALIDÉ, et pas « à confirmer » : le champ est
                // réservé à l'admin (famicardPeutModifier vient de le vérifier),
                // donc il n'y a personne à qui demander. Surtout, « rétablir »
                // depuis validations.php réécrit une COLONNE — ce que le
                // rattachement n'est pas. Une ligne en attente ici serait une
                // décision impossible à appliquer.
                famicardTraceModification(
                    $db, $cibleId, 'departement',
                    $champRattachement,
                    implode(' · ', $avant),
                    implode(' · ', $apres),
                    (int) $moi['id'],
                    false
                );
                $rattachementActuel = $voulus;
            } else {
                $erreurs[] = "Le rattachement n'a pas pu être enregistré.";
            }
        }
    }

    // ── LES AUTRES CHAMPS ────────────────────────────────────────────────
    $aEcrire = [];

    foreach ($champs as $cle => $champ) {
        // Le droit d'écrire est revérifié ici : c'est le seul contrôle qui
        // compte, l'absence du champ dans le formulaire n'en est pas un.
        if (!famicardPeutModifier($champ, $estAdmin, $estSaPropreFiche)) {
            continue;
        }
        // La photo (fichier) et le rattachement (liste unique à deux niveaux)
        // sont traités plus haut, chacun avec sa mécanique.
        if (in_array($champ['saisie'] ?? 'texte', ['photo', 'rattachement'], true)) {
            continue;
        }

        $nomChamp = 'champ_' . $cle;
        if (!array_key_exists($nomChamp, $_POST)) {
            continue;
        }

        $nouvelle = trim((string) $_POST[$nomChamp]);
        $ancienne = famicardValeurBrute($cle, $champ, $cible, $libres);

        // Rien n'a bougé : ni écriture, ni ligne de validation. Sinon
        // l'administrateur croulerait sous des « modifications » où l'ancienne
        // et la nouvelle valeur sont identiques.
        if ($nouvelle === $ancienne) {
            continue;
        }

        // ── Contrôles de saisie, par type ────────────────────────────────
        if ($cle === 'email') {
            if ($nouvelle !== '' && !filter_var($nouvelle, FILTER_VALIDATE_EMAIL)) {
                $erreurs[] = "L'adresse email n'est pas valide.";
                continue;
            }
            if ($nouvelle !== '') {
                // Le site refuse deux comptes avec la même adresse : sans ce
                // test, on créerait ici le doublon qu'il interdit ailleurs.
                $q = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id != ?");
                $q->execute([$nouvelle, $cibleId]);
                if ((int) $q->fetchColumn() > 0) {
                    $erreurs[] = 'Cette adresse email est déjà utilisée par un autre compte.';
                    continue;
                }
            }
        } elseif ($cle === 'identifiant') {
            // ── LA SEULE MODIFICATION QUI PEUT METTRE QUELQU'UN DEHORS ────
            // L'identifiant est ce avec quoi on se connecte. Le changer par
            // erreur, ou depuis une session laissée ouverte sur un poste, et
            // la personne se retrouve à la porte sans comprendre. D'où une
            // preuve d'identité — le mot de passe de CELUI QUI MODIFIE, pas
            // celui de la personne : c'est lui qu'on veut authentifier.
            $preuve = (string) ($_POST['confirmation_mdp'] ?? '');
            if ($preuve === '') {
                $erreurs[] = 'Pour changer un identifiant, saisis ton propre mot de passe en bas du formulaire.';
                continue;
            }
            if (!password_verify($preuve, (string) ($moi['mot_de_passe'] ?? ''))) {
                $erreurs[] = "Ce mot de passe n'est pas le tien : l'identifiant n'a pas été changé.";
                continue;
            }
            if ($nouvelle === '') {
                $erreurs[] = "L'identifiant ne peut pas être vide : personne ne pourrait plus se connecter à ce compte.";
                continue;
            }
            // La colonne est un VARCHAR(50) : au-delà, MySQL tronque en
            // silence et l'identifiant enregistré n'est pas celui saisi.
            if (mb_strlen($nouvelle) > 50) {
                $erreurs[] = 'Identifiant trop long (50 caractères au maximum).';
                continue;
            }
            if (preg_match('/\s/u', $nouvelle)) {
                $erreurs[] = "L'identifiant ne peut pas contenir d'espace : il se tape à la connexion.";
                continue;
            }
            // Verrous : ces noms-là sont des clés dans le code, pas des noms.
            if (famicardIdentifiantVerrouille($ancienne)) {
                $erreurs[] = '« ' . $ancienne . ' » ne peut pas être renommé : ce nom ouvre des droits'
                           . ' écrits en dur dans le site, et le changer les retirerait sans prévenir.';
                continue;
            }
            if (famicardIdentifiantVerrouille($nouvelle)) {
                $erreurs[] = '« ' . $nouvelle . ' » est réservé à un compte de service.';
                continue;
            }
            // Unicité : la colonne porte une clé UNIQUE, donc sans ce test
            // l'enregistrement tomberait sur une erreur SQL brute.
            $q = $db->prepare("SELECT COUNT(*) FROM utilisateurs WHERE identifiant = ? AND id != ?");
            $q->execute([$nouvelle, $cibleId]);
            if ((int) $q->fetchColumn() > 0) {
                $erreurs[] = 'Cet identifiant est déjà utilisé par un autre compte.';
                continue;
            }

        } elseif (($champ['saisie'] ?? '') === 'date') {
            if ($nouvelle !== '') {
                $d = date_create($nouvelle);
                if (!$d) {
                    $erreurs[] = 'La date saisie est incompréhensible.';
                    continue;
                }
                $nouvelle = $d->format('Y-m-d');
            }
        } elseif ($cle === 'site_id') {
            if ($nouvelle !== '' && !isset($magasins[(int) $nouvelle])) {
                $erreurs[] = "Ce lieu de travail n'existe pas.";
                continue;
            }
        } elseif ($cle === 'role') {
            if (!in_array($nouvelle, famicardRolesProposes($cible), true)) {
                $erreurs[] = "Ce profil n'existe pas.";
                continue;
            }
        } elseif ($cle === 'statut') {
            if (!in_array($nouvelle, ['', 'inactif'], true)) {
                $erreurs[] = 'Statut inconnu.';
                continue;
            }
        } elseif (!empty($champ['options'])) {
            // Champ à liste (employeur, contrat, agence) : on n'accepte que ce
            // que la liste proposait — plus la valeur DÉJÀ en place, qu'une
            // agence retirée de `interim_agences` rendrait sinon impossible à
            // conserver, et donc effacée au premier enregistrement.
            $permises = array_keys($champ['options']);
            if ($ancienne !== '') {
                $permises[] = $ancienne;
            }
            if ($nouvelle !== '' && !in_array($nouvelle, $permises, true)) {
                $erreurs[] = 'Valeur inconnue pour « ' . $champ['libelle'] . ' ».';
                continue;
            }
        }

        $aEcrire[$cle] = ['champ' => $champ, 'avant' => $ancienne, 'apres' => $nouvelle];
    }

    if (!$erreurs) {
        // L'admin ne se valide pas lui-même : ce qu'il écrit est tracé, mais
        // déjà tranché.
        $aValider = !$estAdmin;

        foreach ($aEcrire as $cle => $op) {
            famicardEcritValeur($db, $cibleId, $op['champ'], $op['apres']);
            famicardTraceModification($db, $cibleId, $cle, $op['champ'], $op['avant'], $op['apres'], (int) $moi['id'], $aValider);
        }

        // ── APRÈS UN CHANGEMENT D'IDENTIFIANT ────────────────────────────
        if (isset($aEcrire['identifiant'])) {
            // Le site entier lit le nom de connexion dans la session
            // (`$_SESSION['username']`). Un admin qui se renomme lui-même
            // continuerait sinon à circuler sous son ancien nom jusqu'à la
            // prochaine connexion — et les pages qui comparent ce nom se
            // tromperaient de personne.
            if ($estSaPropreFiche) {
                $_SESSION['username'] = $aEcrire['identifiant']['apres'];
            }
            // ⚠️ Deux conséquences qu'on ne peut PAS rattraper depuis ici, et
            // qu'il faut donc dire tout de suite.
            $avertissements[] = 'Son ancien identifiant (« ' . $aEcrire['identifiant']['avant'] .' ») ne'
                             . ' fonctionne plus : préviens-le, sinon il se retrouvera devant une porte fermée'
                             . ' sans comprendre pourquoi. Les présences déjà enregistrées, elles, restent'
                             . " sous l'ancien nom : elles sont stockées en texte, pas par numéro de compte.";
        }

        $combien = count($aEcrire);
        $suffixe = $avertissements ? ' ⚠️ ' . implode(' ', $avertissements) : '';

        if ($combien === 0 && !$photoDeposee && !$rattachementChange) {
            famicardRetourModif("Rien n'a changé." . $suffixe, $estSaPropreFiche, $cibleId);
        }

        $bouts = [];
        if ($photoDeposee) {
            $bouts[] = 'photo mise à jour';
        }
        if ($rattachementChange) {
            $bouts[] = 'rattachement modifié';
        }
        if ($combien > 0) {
            $bouts[] = $combien . ' champ' . ($combien > 1 ? 's' : '') . ' modifié' . ($combien > 1 ? 's' : '');
        }
        $texte = '✅ ' . ucfirst(implode(', ', $bouts)) . '.';
        if ($aValider && $combien > 0) {
            $texte .= ' Un administrateur confirmera.';
        }

        famicardRetourModif($texte . $suffixe, $estSaPropreFiche, $cibleId);
    }

    // En cas d'erreur, on réaffiche ce que la personne a saisi plutôt que de
    // lui rendre l'ancienne valeur : elle perdrait sa correction.
    foreach ($aEcrire as $cle => $op) {
        $colonne = (string) ($champs[$cle]['colonne'] ?? '');
        if ($colonne !== '') {
            $cible[$colonne] = $op['apres'];
        } elseif (!empty($champs[$cle]['champ_id'])) {
            $libres[(int) $champs[$cle]['champ_id']] = $op['apres'];
        }
    }
}

/**
 * Les profils proposables. Volontairement sans « agence intérim » : ces comptes
 * ne sont pas des collaborateurs et basculer quelqu'un dedans l'enfermerait
 * hors de Famicard (voir login.php). Le profil actuel est ajouté s'il manque,
 * sinon l'éditer ferait glisser en silence vers le premier de la liste.
 */
function famicardRolesProposes(array $cible)
{
    $roles = ['beta', 'betalapanne', 'etudiant', 'employe_magasin', 'employe_logistique', 'teamcoach', 'mentor', 'evaluateur', 'admin'];
    $actuel = (string) ($cible['role'] ?? '');
    if ($actuel !== '' && !in_array($actuel, $roles, true)) {
        $roles[] = $actuel;
    }
    return $roles;
}

$rolesProposes = famicardRolesProposes($cible);

$nomCible = trim(((string) ($cible['prenom'] ?? '')) . ' ' . ((string) ($cible['nom'] ?? '')));
if ($nomCible === '') {
    $nomCible = (string) ($cible['identifiant'] ?? '');
}

// Aperçu de la photo actuelle. Le paramètre anti-cache est indispensable :
// sans lui, le navigateur réaffiche l'ancienne image et on croit que l'envoi
// a échoué.
$photo = (string) ($cible['photo_profil'] ?? '');
$photoUrl = '';
if ($photo !== '') {
    $photoUrl = function_exists('moduleFileUrl') ? moduleFileUrl($photo) : $photo;
    if ($photoUrl !== '' && !preg_match('#^(https?:)?//#i', $photoUrl)) {
        $photoUrl = famicardSiteUrl($photoUrl);
    }
    $photoUrl .= (strpos($photoUrl, '?') === false ? '?' : '&') . 'v=' . time();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $estSaPropreFiche ? 'Modifier mes informations' : 'Modifier une fiche' ?> - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; background: url('<?= e(famicardSiteUrl('background.jpg')) ?>') no-repeat center center fixed; background-size: cover; margin: 0; padding: 0 0 40px; color: #333; }
    .top-nav { display: flex; gap: 12px; flex-wrap: wrap; padding: 12px 16px; }
    .pill { background: rgba(255,255,255,.92); padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 10px rgba(0,0,0,.1); text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .9rem; }
    .wrap { max-width: 700px; margin: 0 auto; padding: 0 16px; }

    .boite { background: rgba(255,255,255,.96); border-radius: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; }
    .boite-tete { background: linear-gradient(135deg, #2d5a37, #4a8b5c); color: #fff; padding: 22px 26px; }
    .boite-tete h1 { margin: 0 0 4px; font-size: 1.3rem; font-weight: 800; }
    .boite-tete .sous { font-size: .86rem; opacity: .92; }

    /* ── ZONE PHOTO ─────────────────────────────────────────────────── */
    .zone-photo { padding: 28px 26px; text-align: center; background: #f7faf8; border-bottom: 1px solid #eee; }
    .depose { display: block; cursor: pointer; border: 2px dashed #b9cfc0; border-radius: 18px; padding: 22px; background: #fff; transition: border-color .15s, background .15s; }
    .depose:hover { border-color: #2d5a37; background: #fbfefc; }
    .depose input[type="file"] { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
    .apercu { width: 132px; height: 132px; border-radius: 50%; object-fit: cover; border: 5px solid #2d5a37; margin: 0 auto 14px; display: block; background: #e8f5e9; }
    .apercu-vide { display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #2d5a37; border-style: dashed; }
    .depose .invite { color: #2d5a37; font-weight: 700; font-size: .95rem; }
    .depose .format { color: #8a988f; font-size: .78rem; margin-top: 5px; line-height: 1.5; }
    .depose .choisi { color: #1e5128; font-weight: 700; font-size: .86rem; margin-top: 9px; word-break: break-all; }
    .retirer { background: none; border: 0; color: #a83232; font-family: inherit; font-size: .84rem; font-weight: 700; cursor: pointer; margin-top: 14px; text-decoration: underline; }
    .photo-figee { color: #777; font-size: .88rem; margin-top: 12px; }

    .groupe { padding: 20px 26px; border-top: 1px solid #eee; }
    .groupe h2 { margin: 0 0 14px; font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; color: #2d5a37; }
    .ligne { margin-bottom: 15px; }
    .ligne label { display: block; font-weight: 600; font-size: .86rem; color: #444; margin-bottom: 5px; }
    .ligne .obl { color: #c0392b; }
    input[type="text"], input[type="email"], input[type="date"], input[type="password"], select { width: 100%; padding: 10px 12px; border: 1px solid #ccd6cf; border-radius: 10px; font-family: inherit; font-size: .95rem; background: #fff; }
    .aide { color: #888; font-size: .78rem; margin-top: 4px; line-height: 1.45; }
    .fige { background: #f5f7f6; border-radius: 10px; padding: 10px 12px; color: #777; font-size: .92rem; }

    /* ── RATTACHEMENT : une ligne par département, l'ordre fait la priorité ── */
    .rattachements { display: flex; flex-direction: column; gap: 9px; }
    .ratt-ligne { display: flex; align-items: center; gap: 10px; }
    .ratt-rang { flex: 0 0 66px; font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #2d5a37; }
    .duo { display: flex; gap: 8px; flex: 1; min-width: 0; flex-wrap: wrap; }
    .duo select { flex: 1 1 45%; min-width: 0; width: auto; }

    .verrou { background: #fffaf0; }
    .verrou h2 { color: #8a5a10; }

    .actions { display: flex; gap: 12px; flex-wrap: wrap; padding: 22px 26px; background: #f7faf8; border-top: 1px solid #eee; }
    .bouton { border: 0; border-radius: 30px; padding: 12px 26px; font-family: inherit; font-weight: 700; font-size: .92rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-vide { background: #fff; color: #2d5a37; border: 1px solid #d3e0d7; }

    .flash { border-radius: 12px; padding: 12px 16px; margin: 16px 0; font-size: .9rem; font-weight: 600; background: #e8f5e9; color: #1e5128; }
    .erreurs { border-radius: 12px; padding: 12px 16px; margin: 16px 0; font-size: .9rem; background: #fdecea; color: #a3271c; }
    .erreurs ul { margin: 6px 0 0; padding-left: 20px; }
    .note { background: rgba(255,255,255,.95); border-left: 5px solid #E9A93C; border-radius: 14px; padding: 14px 18px; margin-top: 20px; font-size: .87rem; line-height: 1.55; color: #7a4a11; }
</style>
</head>
<body>

<div class="top-nav">
    <?php if ($estSaPropreFiche): ?>
        <a class="pill" href="fiche.php">&larr; Ma fiche</a>
        <a class="pill" href="index.php">Accueil</a>
    <?php else: ?>
        <a class="pill" href="admin.php">&larr; Base des collaborateurs</a>
    <?php endif; ?>
</div>

<div class="wrap">

    <?php if ($message !== ''): ?>
        <div class="flash"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($erreurs): ?>
        <div class="erreurs">
            <b>À corriger :</b>
            <ul><?php foreach (array_unique($erreurs) as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php // enctype indispensable : sans lui le fichier n'arrive jamais au
          // serveur, et le formulaire semble marcher tout en perdant la photo. ?>
    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="boite">
            <div class="boite-tete">
                <h1><?= $estSaPropreFiche ? 'Mes informations' : e($nomCible) ?></h1>
                <div class="sous">
                    <?php if ($estSaPropreFiche && !$estAdmin): ?>
                        Tes corrections s'appliquent tout de suite ; un administrateur les confirme ensuite.
                    <?php else: ?>
                        Les modifications s'appliquent immédiatement.
                    <?php endif; ?>
                </div>
            </div>

            <div class="zone-photo">
                <?php if ($photoEditable): ?>
                    <label class="depose">
                        <?php if ($photoUrl !== ''): ?>
                            <img class="apercu" id="apercuPhoto" src="<?= e($photoUrl) ?>" alt="">
                        <?php else: ?>
                            <div class="apercu apercu-vide" id="apercuVide">👤</div>
                            <img class="apercu" id="apercuPhoto" src="" alt="" style="display:none;">
                        <?php endif; ?>
                        <div class="invite">Choisir une photo</div>
                        <div class="format">JPEG, PNG, GIF ou WebP — 5 Mo maximum.<br>Elle est réduite automatiquement.</div>
                        <div class="choisi" id="nomFichier"></div>
                        <input type="file" name="photo_profil" id="champPhoto"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                    </label>

                    <?php if ($photo !== ''): ?>
                        <button type="submit" name="supprimer_photo" value="1" class="retirer"
                                onclick="return confirm('Supprimer la photo ?');">Supprimer la photo</button>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($photoUrl !== ''): ?>
                        <img class="apercu" src="<?= e($photoUrl) ?>" alt="">
                    <?php else: ?>
                        <div class="apercu apercu-vide">👤</div>
                    <?php endif; ?>
                    <div class="photo-figee">Seul le collaborateur dépose sa photo.</div>
                <?php endif; ?>
            </div>

            <?php foreach ($groupes as $cleGroupe => $groupe): ?>
                <?php
                // Un groupe n'est dessiné que s'il contient au moins une ligne
                // à montrer — titre suivi de rien = écran qui a l'air cassé.
                // La photo est exclue : elle a sa zone, en haut.
                $lignes = [];
                foreach ($champs as $cle => $champ) {
                    if (($champ['groupe'] ?? '') !== $cleGroupe) {
                        continue;
                    }
                    if (($champ['saisie'] ?? 'texte') === 'photo') {
                        continue;
                    }
                    if (!famicardPeutVoir($champ, $estAdmin, $estSaPropreFiche)) {
                        continue;
                    }
                    $lignes[$cle] = $champ;
                }
                if (!$lignes) {
                    continue;
                }
                ?>
                <div class="groupe">
                    <h2><?= e($groupe['libelle']) ?></h2>

                    <?php foreach ($lignes as $cle => $champ): ?>
                        <?php
                        $editable = famicardPeutModifier($champ, $estAdmin, $estSaPropreFiche);
                        $saisie   = (string) ($champ['saisie'] ?? 'texte');
                        $brute    = famicardValeurBrute($cle, $champ, $cible, $libres);
                        $affichee = famicardValeurAffichee($cle, $champ, $cible, $magasins, $libres);
                        ?>
                        <div class="ligne">
                            <label for="champ_<?= e($cle) ?>">
                                <?= e($champ['libelle']) ?><?php if (!empty($champ['requis'])): ?> <span class="obl">*</span><?php endif; ?>
                            </label>

                            <?php // Le rattachement passe TOUJOURS par ce bloc, même quand il
                                  // n'est pas modifiable : sans ce test en premier, un admin
                                  // se verrait proposer un champ texte libre sur une
                                  // pseudo-colonne, qui n'existe pas dans `utilisateurs`. ?>
                            <?php if ($saisie === 'rattachement'): ?>
                                <?php if ($cle === 'departement' && $rattachementEditable): ?>
                                    <?php // UNE CASE PAR RATTACHEMENT, PLUS UNE VIDE. C'est ce
                                          // qui rend l'écriture sûre : le formulaire porte la
                                          // liste ENTIÈRE, donc enregistrer ne peut rien
                                          // effacer qui n'ait été retiré exprès. ?>
                                    <div class="rattachements">
                                        <?php $slots = $rattachementActuel; $slots[] = 0; ?>
                                        <?php foreach ($slots as $i => $idActuel): ?>
                                            <div class="ratt-ligne">
                                                <span class="ratt-rang"><?= $i === 0 ? 'Principal' : ($i + 1) . 'e' ?></span>
                                                <div class="duo">
                                                    <?= secteursChampsHtml($db, 'rattachement[]', $idActuel > 0 ? (string) $idActuel : '', [
                                                            'par' => 'id',
                                                            'vide' => $i === 0 ? '— Aucun —' : '— Ajouter —',
                                                            'sansSecteur' => true,
                                                        ]) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="aide">
                                        L'ordre est la priorité : le premier est son rattachement principal, et c'est
                                        celui-là que le matching de FamiJob regarde en premier.
                                        Vider une ligne retire le rattachement ; une nouvelle case vide apparaît
                                        après l'enregistrement.
                                    </div>
                                <?php else: ?>
                                    <div class="fige"><?= $affichee !== '' ? e($affichee) : '—' ?></div>
                                    <?php // Le secteur se DÉDUIT du département : il n'a pas de
                                          // case à lui, sinon on pourrait enregistrer un secteur
                                          // qui contredit le département choisi juste en dessous. ?>
                                    <div class="aide">
                                        <?= $cle === 'secteur'
                                            ? 'Découle du département : choisis le département, le secteur suit.'
                                            : 'Modifiable par un administrateur.' ?>
                                    </div>
                                <?php endif; ?>

                            <?php elseif (!$editable): ?>
                                <?php // Montré mais figé : le collaborateur voit la valeur et
                                      // comprend qu'elle existe, sans croire qu'il l'a oubliée. ?>
                                <div class="fige"><?= $affichee !== '' ? e($affichee) : '—' ?></div>
                                <div class="aide">Modifiable par un administrateur.</div>

                            <?php elseif ($cle === 'site_id'): ?>
                                <select id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>">
                                    <option value="">— Aucun —</option>
                                    <?php foreach ($magasins as $mid => $mnom): ?>
                                        <option value="<?= (int) $mid ?>" <?= ((string) $brute === (string) $mid) ? 'selected' : '' ?>><?= e($mnom) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($cle === 'role'): ?>
                                <select id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>">
                                    <?php foreach ($rolesProposes as $r): ?>
                                        <option value="<?= e($r) ?>" <?= ($brute === $r) ? 'selected' : '' ?>><?= e(famicardLibelleRole($r)) ?></option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($cle === 'statut'): ?>
                                <select id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>">
                                    <option value="" <?= ($brute !== 'inactif') ? 'selected' : '' ?>>Actif</option>
                                    <option value="inactif" <?= ($brute === 'inactif') ? 'selected' : '' ?>>Inactif</option>
                                </select>

                            <?php elseif (!empty($champ['options'])): ?>
                                <?php // Champ à liste posé par le modèle (employeur, contrat,
                                      // agence). Aucun cas particulier ici : le jour où un
                                      // champ à liste s'ajoute, il s'affiche tout seul. ?>
                                <select id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>">
                                    <option value="">— À préciser —</option>
                                    <?php foreach ($champ['options'] as $val => $lib): ?>
                                        <option value="<?= e((string) $val) ?>" <?= ($brute === (string) $val) ? 'selected' : '' ?>><?= e((string) $lib) ?></option>
                                    <?php endforeach; ?>
                                    <?php // La valeur actuelle si la liste ne la contient plus
                                          // (agence supprimée) : sans elle, ouvrir la fiche et
                                          // enregistrer effacerait une donnée qu'on n'a pas touchée. ?>
                                    <?php if ($brute !== '' && !isset($champ['options'][$brute])): ?>
                                        <option value="<?= e($brute) ?>" selected><?= e($brute) ?> (n'est plus dans la liste)</option>
                                    <?php endif; ?>
                                </select>

                            <?php elseif ($saisie === 'date'): ?>
                                <?php // 0000-00-00 traîne dans les vieilles lignes : ce n'est pas
                                      // une date, et le champ HTML la refuserait de toute façon. ?>
                                <input type="date" id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>"
                                       value="<?= ($brute !== '' && $brute !== '0000-00-00') ? e(substr($brute, 0, 10)) : '' ?>">

                            <?php elseif ($saisie === 'email'): ?>
                                <input type="email" id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>" value="<?= e($brute) ?>">

                            <?php else: ?>
                                <input type="text" id="champ_<?= e($cle) ?>" name="champ_<?= e($cle) ?>" value="<?= e($brute) ?>" maxlength="255">
                            <?php endif; ?>

                            <?php // L'explication voyage AVEC le champ, dans le modèle.
                                  // Elle n'est montrée qu'à qui peut écrire : figée, la ligne
                                  // dit déjà « modifiable par un administrateur ». ?>
                            <?php if ($editable && $saisie !== 'rattachement' && !empty($champ['aide'])): ?>
                                <div class="aide"><?= e((string) $champ['aide']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php // ── PREUVE D'IDENTITÉ ───────────────────────────────────────
                  // Demandée seulement à qui peut changer un identifiant, et
                  // n'est vérifiée QUE si l'identifiant a réellement changé :
                  // un champ obligatoire à chaque enregistrement d'adresse
                  // serait contourné en trois jours (mot de passe collé dans un
                  // gestionnaire, ou plus personne ne corrige sa fiche). ?>
            <?php $identifiantEditable = isset($champs['identifiant'])
                    && famicardPeutModifier($champs['identifiant'], $estAdmin, $estSaPropreFiche); ?>
            <?php if ($identifiantEditable): ?>
                <div class="groupe verrou">
                    <h2>🔒 Changement d'identifiant</h2>
                    <div class="ligne">
                        <label for="confirmation_mdp">Ton mot de passe</label>
                        <input type="password" id="confirmation_mdp" name="confirmation_mdp"
                               autocomplete="current-password" placeholder="à remplir seulement si tu changes l'identifiant">
                        <div class="aide">
                            Changer un identifiant, c'est changer la façon dont quelqu'un se connecte : tant que la
                            personne n'est pas prévenue, elle ne peut plus entrer. On te redemande donc <b>ton</b>
                            mot de passe — celui de ton compte — pour être sûr que c'est bien toi.
                            Le reste de la fiche s'enregistre sans.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="actions">
                <button type="submit" class="bouton bouton-plein">Enregistrer</button>
                <a class="bouton bouton-vide" href="<?= $estSaPropreFiche ? 'fiche.php' : 'admin.php' ?>">Annuler</a>
            </div>
        </div>
    </form>

    <?php if ($estSaPropreFiche && !$estAdmin): ?>
        <div class="note">
            Ce que tu corriges est visible immédiatement. Un administrateur voit ensuite
            l'ancienne et la nouvelle valeur, et confirme — ou rétablit si c'était une erreur.
            Ta photo, elle, est libre : personne n'a à la valider.
        </div>
    <?php endif; ?>

</div>


<?php // Le filtrage secteur → département, une seule fois pour toutes les cases.
      // Sans lui, les listes restent complètes et restent utilisables : le
      // script est un confort, pas une condition. ?>
<?= ($rattachementEditable && function_exists('secteursScript')) ? secteursScript() : '' ?>

<?php if ($photoEditable): ?>
<script>
// Aperçu immédiat de l'image choisie. Sans lui, on ne sait pas si le bon
// fichier a été pris avant d'avoir enregistré — et sur mobile, le nom seul ne
// dit rien (« IMG_4821.jpg »).
(function () {
    var champ = document.getElementById('champPhoto');
    if (!champ) { return; }

    champ.addEventListener('change', function () {
        var f = champ.files && champ.files[0];
        if (!f) { return; }

        var nom = document.getElementById('nomFichier');
        if (nom) { nom.textContent = f.name; }

        var img = document.getElementById('apercuPhoto');
        var vide = document.getElementById('apercuVide');
        if (img && window.FileReader) {
            var lecteur = new FileReader();
            lecteur.onload = function (e) {
                img.src = e.target.result;
                img.style.display = 'block';
                if (vide) { vide.style.display = 'none'; }
            };
            lecteur.readAsDataURL(f);
        }
    });
}());
</script>
<?php endif; ?>

</body>
</html>
