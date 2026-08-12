<?php
// ============================================================
// FAMICARD — LE MODÈLE DE LA CARTE.
//
// Seul endroit qui décrit ce que contient la carte d'identité d'un
// collaborateur. Tout le reste (carte, base admin, badge imprimé, export Excel)
// lit cette description au lieu de refaire ses propres colonnes.
//
// DEUX SOURCES DE CHAMPS, volontairement distinctes :
//
//   1. les champs SOCLE, adossés aux colonnes de `utilisateurs`. Ils existent
//      déjà, FamiFormation et FamiJob s'en servent, et les LIBELLÉS SONT CEUX
//      DE FAMIFORMATION (« Ville de résidence », « Lieu de travail »...) pour
//      qu'un même champ ne porte pas deux noms selon l'écran ;
//
//   2. les champs LIBRES, créés par un administrateur depuis Famicard. Ils
//      vivent dans deux tables à part (famicard_champs / famicard_valeurs).
//
// POURQUOI LES CHAMPS LIBRES NE SONT PAS DES COLONNES : ajouter une colonne à
// `utilisateurs` à chaque libellé créé, c'est modifier la table dont dépendent
// FamiFormation, FamiJob et le quiz — pour un besoin d'affichage. Une table de
// valeurs ne peut casser personne, et un libellé supprimé ne laisse pas une
// colonne morte derrière lui.
//
// La règle de confidentialité voyage AVEC le champ : un champ marqué
// « personnel » ne peut pas atterrir par distraction dans un export ou sur un
// badge. Les vues ne décident pas, elles demandent à famicardPeutVoir().
// ============================================================

if (!function_exists('famicardChampsSocle')) {
    /**
     * Champs adossés à `utilisateurs`.
     *
     * Clés :
     *   libelle / libelle_nl : intitulé affiché (le site est bilingue).
     *   colonne  : colonne de `utilisateurs`.
     *   groupe   : regroupement d'affichage.
     *   requis   : true = la fiche est incomplète sans lui.
     *   nature   : 'service'   → nécessaire au fonctionnement (base légale : contrat)
     *              'personnel' → donnée personnelle, réservée à qui de droit
     *   visible  : 'tous' | 'soi' (le collaborateur + les admins) | 'admin'
     *   modifiable : QUI a le droit d'écrire ce champ.
     *              'soi'    → le collaborateur sur sa propre fiche (et l'admin)
     *              'admin'  → l'administrateur seulement
     *              'jamais' → personne depuis Famicard
     *   saisie   : comment on l'édite ('texte', 'email', 'date', 'liste', 'photo').
     *   badge    : le champ a-t-il le droit de figurer sur le badge imprimé.
     *
     * ⚠️ « visible » et « modifiable » sont DEUX questions distinctes, et il
     * faut les deux : un champ peut se voir sans se modifier (le profil), et
     * l'inverse n'a pas de sens — on ne modifie pas ce qu'on ne voit pas, ce
     * que famicardPeutModifier() vérifie explicitement.
     */
    function famicardChampsSocle()
    {
        return [
            // ── IDENTITÉ ───────────────────────────────────────────────────
            // Nom et prénom restent à l'admin : ils sont sur le badge, dans
            // toutes les listes et dans les exports. Une faute de frappe se
            // corrige en le demandant, ce n'est pas un champ du quotidien.
            'prenom' => [
                'libelle' => 'Prénom', 'libelle_nl' => 'Voornaam',
                'colonne' => 'prenom', 'groupe' => 'identite',
                'requis' => true, 'nature' => 'service', 'visible' => 'tous',
                'modifiable' => 'admin', 'saisie' => 'texte', 'badge' => true,
            ],
            'nom' => [
                'libelle' => 'Nom', 'libelle_nl' => 'Naam',
                'colonne' => 'nom', 'groupe' => 'identite',
                'requis' => true, 'nature' => 'service', 'visible' => 'tous',
                'modifiable' => 'admin', 'saisie' => 'texte', 'badge' => false,
            ],
            // OBLIGATOIRE (décision Jimmy). Le seul champ requis que le
            // collaborateur dépose lui-même. Il ne s'édite pas dans le
            // formulaire standard mais dans la zone d'envoi en tête de
            // modifier.php, d'où 'saisie' => 'photo'.
            'photo_profil' => [
                'libelle' => 'Photo', 'libelle_nl' => 'Foto',
                'colonne' => 'photo_profil', 'groupe' => 'identite',
                'requis' => true, 'nature' => 'personnel', 'visible' => 'tous',
                'modifiable' => 'soi', 'saisie' => 'photo', 'badge' => false,
            ],
            // Libellé repris de admin_user.php (« Date d'anniversaire ») : le
            // site s'en sert pour le thème d'anniversaire, pas pour l'état civil.
            'date_naissance' => [
                'libelle' => "Date d'anniversaire", 'libelle_nl' => 'Verjaardag',
                'colonne' => 'date_naissance', 'groupe' => 'identite',
                'requis' => false, 'nature' => 'personnel', 'visible' => 'soi',
                'modifiable' => 'soi', 'saisie' => 'date', 'badge' => false,
            ],

            // ── CONTACT ────────────────────────────────────────────────────
            'email' => [
                'libelle' => 'Email', 'libelle_nl' => 'E-mail',
                'colonne' => 'email', 'groupe' => 'contact',
                'requis' => true, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'soi', 'saisie' => 'email', 'badge' => false,
            ],
            'ville' => [
                'libelle' => 'Ville de résidence', 'libelle_nl' => 'Woonplaats',
                'colonne' => 'ville', 'groupe' => 'contact',
                'requis' => false, 'nature' => 'personnel', 'visible' => 'soi',
                'modifiable' => 'soi', 'saisie' => 'texte', 'badge' => false,
            ],

            // ── RATTACHEMENT ───────────────────────────────────────────────
            // Données de GESTION : où la personne travaille et pour quelle
            // agence. Ce n'est pas au collaborateur de se réaffecter.
            'site_id' => [
                'libelle' => 'Lieu de travail', 'libelle_nl' => 'Werkplaats',
                'colonne' => 'site_id', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'tous',
                'modifiable' => 'admin', 'saisie' => 'liste', 'badge' => false,
            ],
            // ── EMPLOI : CHEZ QUI, ET COMMENT ──────────────────────────────
            // Trois questions distinctes, trois champs, et aucun qui décide
            // d'un accès — c'est `role` et lui seul qui ouvre des portes.
            // Voir includes/emploi.php pour le raisonnement complet.
            'employeur' => [
                'libelle' => 'Employeur', 'libelle_nl' => 'Werkgever',
                'colonne' => 'employeur', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'admin', 'saisie' => 'liste', 'badge' => false,
                'aide' => 'Chez qui elle travaille. « Externe » n\'est pas une valeur : c\'est tout ce qui n\'est pas interne.',
            ],
            'contrat' => [
                'libelle' => 'Type de contrat', 'libelle_nl' => 'Contracttype',
                'colonne' => 'contrat', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'admin', 'saisie' => 'liste', 'badge' => false,
                'aide' => 'Comment elle est engagée. Indépendant de l\'employeur : un intérimaire peut être étudiant, flexi ou fixe.',
            ],
            // ⚠️ CE CHAMP NE DIT PAS « est-elle intérimaire ». Il dit QUI SUIT
            // SON DOSSIER : son agence si elle est externe, « Famiflora »
            // (c'est-à-dire Honorine, qui a une ligne dans `interim_agences` et
            // un compte à ce nom) si elle a été recrutée en direct. C'est
            // `employeur` qui répond à la question du statut.
            'interim' => [
                'libelle' => 'Dossier suivi par', 'libelle_nl' => 'Dossier beheerd door',
                'colonne' => 'interim', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin',
                'modifiable' => 'admin', 'saisie' => 'liste', 'badge' => false,
                'aide' => 'Son agence si elle est en intérim, Famiflora (Honorine) si elle a été recrutée en direct.',
            ],
            // ── SECTEUR ET DÉPARTEMENT : DE QUOI ELLE RELÈVE ───────────────
            // Ces deux-là ne sont NI une colonne de `utilisateurs` NI un champ
            // libre : ils vivent dans `famicard_rattachement`. Les « colonnes »
            // ci-dessous sont donc des pseudo-colonnes, remplies dans la ligne
            // par famicardAjouteRattachement() avant tout affichage.
            //
            // ⚠️ CE N'EST PAS `student_department_links`. Cette table-là répond
            // à « où cet étudiant peut-il être PLACÉ » (planification FamiJob,
            // plusieurs rayons ordonnés par préférence). Ici on répond à « de
            // quoi cette personne RELÈVE » — une seule réponse, pour tout le
            // monde, teamcoachs compris. Voir includes/rattachement.php.
            //
            // 'saisie' => 'rattachement' : ils ne s'éditent pas dans le
            // formulaire champ par champ (les deux se choisissent ENSEMBLE, le
            // département devant appartenir au secteur), mais dans un bloc
            // dédié de modifier.php — comme la photo.
            'secteur' => [
                'libelle' => 'Secteur', 'libelle_nl' => 'Sector',
                'colonne' => 'secteur_nom', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'tous',
                'modifiable' => 'admin', 'saisie' => 'rattachement', 'badge' => false,
            ],
            'departement' => [
                'libelle' => 'Département', 'libelle_nl' => 'Afdeling',
                'colonne' => 'departement_nom', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'tous',
                'modifiable' => 'admin', 'saisie' => 'rattachement', 'badge' => false,
                'aide' => 'Vide = elle relève de tout le secteur. C\'est le cas d\'un teamcoach.',
            ],
            // Ce que FamiJob sait, montré pour qu'on ne se demande pas pourquoi
            // la fiche dit « Bougies » alors que le planning propose trois
            // rayons. En LECTURE SEULE : ça se règle dans FamiJob, qui connaît
            // les priorités de placement. Vide pour tous ceux qu'on ne planifie
            // pas, ce qui est normal et non un oubli.
            'placement' => [
                'libelle' => 'Placement FamiJob', 'libelle_nl' => 'Inzet FamiJob',
                'colonne' => 'placement_nom', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin',
                'modifiable' => 'jamais', 'saisie' => 'rattachement', 'badge' => false,
                'aide' => 'Les rayons où le planning peut le placer, par ordre de préférence. Se règle dans FamiJob.',
            ],

            // ── COMPTE / ACCÈS AUX SERVICES ────────────────────────────────
            // L'identifiant sert à SE CONNECTER : le changer coupe l'accès de
            // quelqu'un tant qu'il n'a pas été prévenu. Réservé à l'admin, et
            // l'écran d'édition lui redemande SON mot de passe avant d'écrire
            // (voir modifier.php) — c'est la seule modification de la fiche qui
            // exige une preuve d'identité, parce que c'est la seule qui puisse
            // mettre quelqu'un dehors.
            'identifiant' => [
                'libelle' => 'Identifiant', 'libelle_nl' => 'Gebruikersnaam',
                'colonne' => 'identifiant', 'groupe' => 'compte',
                'requis' => true, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'admin', 'saisie' => 'texte', 'badge' => false,
                'aide' => 'C\'est avec ça qu\'il se connecte : le changer rend son ancien identifiant inutilisable.',
            ],
            'role' => [
                'libelle' => 'Profil', 'libelle_nl' => 'Profiel',
                'colonne' => 'role', 'groupe' => 'compte',
                'requis' => true, 'nature' => 'service', 'visible' => 'tous',
                'modifiable' => 'admin', 'saisie' => 'liste', 'badge' => false,
            ],
            'statut' => [
                'libelle' => 'Statut', 'libelle_nl' => 'Status',
                'colonne' => 'statut', 'groupe' => 'compte',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin',
                'modifiable' => 'admin', 'saisie' => 'liste', 'badge' => false,
            ],
            // Calculée par le site à chaque connexion : l'écrire à la main
            // n'aurait aucun sens, la prochaine visite l'écraserait.
            'derniere_visite' => [
                'libelle' => 'Dernière visite', 'libelle_nl' => 'Laatste bezoek',
                'colonne' => 'derniere_visite', 'groupe' => 'compte',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin',
                'modifiable' => 'jamais', 'saisie' => 'texte', 'badge' => false,
            ],
        ];
    }
}

if (!function_exists('famicardIdentifiantsVerrouilles')) {
    /**
     * Les identifiants qui NE PEUVENT PAS être renommés, parce qu'ils sont
     * écrits en dur ailleurs — ce ne sont pas des noms, ce sont des clés.
     *
     *   'admin'   — protégé contre la suppression dans admin_collaborateurs.php
     *               (« DELETE ... AND identifiant != 'admin' »).
     *   'Accueil' — OUVRE UN DROIT : checklist_gerbeur.php laisse entrer qui a
     *               `$_SESSION['username'] === 'Accueil'`. Le renommer
     *               retirerait cet accès sans le moindre message. Le compte est
     *               en plus recréé automatiquement sous ce nom s'il disparaît,
     *               ce qui donnerait deux comptes pour une seule fonction.
     *
     * ⚠️ Un identifiant utilisé comme condition dans du code est un piège connu
     * du dépôt. Tant qu'il en reste, on refuse de les renommer plutôt que de
     * découvrir la panne trois semaines plus tard.
     */
    function famicardIdentifiantsVerrouilles()
    {
        return ['admin', 'Accueil'];
    }
}

if (!function_exists('famicardIdentifiantVerrouille')) {
    function famicardIdentifiantVerrouille($identifiant)
    {
        foreach (famicardIdentifiantsVerrouilles() as $verrou) {
            if (strcasecmp(trim((string) $identifiant), $verrou) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('famicardGroupes')) {
    function famicardGroupes()
    {
        return [
            'identite'     => ['libelle' => 'Identité',     'libelle_nl' => 'Identiteit'],
            'contact'      => ['libelle' => 'Contact',      'libelle_nl' => 'Contact'],
            'rattachement' => ['libelle' => 'Rattachement', 'libelle_nl' => 'Toewijzing'],
            'compte'       => ['libelle' => 'Compte',       'libelle_nl' => 'Account'],
            'libre'        => ['libelle' => 'Informations complémentaires', 'libelle_nl' => 'Aanvullende informatie'],
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CHAMPS LIBRES (créés par un administrateur)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('famicardAssureTables')) {
    /**
     * Crée les deux tables si elles manquent.
     *
     * ⚠️ À N'APPELER QUE depuis la page d'administration des libellés. Le site
     * a déjà fait le ménage une fois pour « retirer la DDL du chemin chaud » :
     * on ne remet pas un CREATE TABLE sur chaque affichage de page.
     */
    function famicardAssureTables(PDO $db)
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS famicard_champs (
                id INT NOT NULL AUTO_INCREMENT,
                libelle VARCHAR(120) NOT NULL,
                libelle_nl VARCHAR(120) NULL,
                groupe VARCHAR(30) NOT NULL DEFAULT 'libre',
                requis TINYINT(1) NOT NULL DEFAULT 0,
                nature VARCHAR(20) NOT NULL DEFAULT 'personnel',
                visible VARCHAR(10) NOT NULL DEFAULT 'soi',
                ordre INT NOT NULL DEFAULT 0,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // ON DELETE CASCADE : un libellé supprimé emporte ses valeurs. Sans ça,
        // on garderait des données personnelles rattachées à un champ qui
        // n'existe plus — invisibles, donc impossibles à corriger ou à effacer.
        $db->exec(
            "CREATE TABLE IF NOT EXISTS famicard_valeurs (
                user_id INT NOT NULL,
                champ_id INT NOT NULL,
                valeur TEXT NULL,
                maj_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, champ_id),
                CONSTRAINT fk_famicard_valeur_champ FOREIGN KEY (champ_id)
                    REFERENCES famicard_champs (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}

if (!function_exists('famicardChampsLibres')) {
    /**
     * Les libellés créés par les administrateurs, sous la même forme que les
     * champs socle — pour que les écrans n'aient qu'un seul type d'objet à
     * manipuler. Table absente = tableau vide, aucune page ne casse.
     */
    function famicardChampsLibres(PDO $db)
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        try {
            $lignes = $db->query(
                "SELECT id, libelle, libelle_nl, groupe, requis, nature, visible
                 FROM famicard_champs WHERE actif = 1 ORDER BY ordre ASC, id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return $cache; // tables pas encore créées
        }

        foreach ($lignes as $l) {
            $visible = (string) $l['visible'];
            $cache['libre_' . (int) $l['id']] = [
                'libelle'    => (string) $l['libelle'],
                'libelle_nl' => (string) ($l['libelle_nl'] ?? ''),
                'colonne'    => null,
                'champ_id'   => (int) $l['id'],
                'groupe'     => (string) ($l['groupe'] ?: 'libre'),
                'requis'     => (bool) $l['requis'],
                'nature'     => (string) $l['nature'],
                'visible'    => $visible,
                // Un champ libre se remplit par la personne concernée — c'est
                // sa raison d'être. Sauf s'il est réservé aux admins à la
                // lecture : on ne modifie pas ce qu'on ne peut pas voir.
                'modifiable' => ($visible === 'admin') ? 'admin' : 'soi',
                'saisie'     => 'texte',
                // Le badge ne porte que le prénom et une mention : aucun champ
                // libre n'y a sa place. Voir badge.php.
                'badge'      => false,
            ];
        }

        return $cache;
    }
}

if (!function_exists('famicardColonnesUtilisateurs')) {
    /**
     * Les colonnes que `utilisateurs` porte VRAIMENT, en cache.
     *
     * Famicard ne possède pas cette table : elle est celle de FamiFormation et
     * de FamiJob, et toutes les bases ne sont pas au même point. On lit donc
     * son état au lieu de le supposer.
     *
     * @param bool $rafraichir à passer après un ALTER : sans ça, le cache
     *                         continuerait de nier une colonne qui vient
     *                         d'être créée, pour toute la durée de la requête.
     */
    function famicardColonnesUtilisateurs(PDO $db, $rafraichir = false)
    {
        static $cache = null;
        if ($cache !== null && !$rafraichir) {
            return $cache;
        }
        $cache = [];
        try {
            foreach ($db->query("SHOW COLUMNS FROM utilisateurs")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cache[(string) $c['Field']] = true;
            }
        } catch (Exception $e) {
            // Base indisponible : on ne filtre rien plutôt que de tout masquer.
            $cache = [];
        }
        return $cache;
    }
}

if (!function_exists('famicardChamps')) {
    /**
     * Socle + libres. La liste complète, dans l'ordre d'affichage.
     *
     * ⚠️ UN CHAMP DONT LA COLONNE N'EXISTE PAS EST RETIRÉ ICI, et c'est un
     * garde-fou, pas un détail : `admin.php` et `export.php` CONSTRUISENT leur
     * SELECT à partir de cette liste. Un champ déclaré avant que sa colonne
     * n'existe (le temps qu'un admin passe par l'accueil, où la migration se
     * fait) ferait tomber la base des collaborateurs entière sur « Unknown
     * column ». Le champ réapparaît tout seul dès que la colonne est là.
     */
    function famicardChamps(PDO $db = null)
    {
        $champs = famicardChampsSocle();

        if (!($db instanceof PDO)) {
            return $champs;
        }

        $colonnes = famicardColonnesUtilisateurs($db);
        if ($colonnes) {
            foreach ($champs as $cle => $champ) {
                // Les pseudo-colonnes (secteur, département) ne sont pas dans
                // `utilisateurs` par construction : elles ne se testent pas.
                if (($champ['saisie'] ?? '') === 'rattachement') {
                    continue;
                }
                $colonne = (string) ($champ['colonne'] ?? '');
                if ($colonne !== '' && !isset($colonnes[$colonne])) {
                    unset($champs[$cle]);
                }
            }
        }

        // Les listes déroulantes dont le contenu vit en base. Elles sont
        // posées ICI plutôt que dans chaque écran : la fiche, la création et
        // l'export doivent proposer exactement les mêmes valeurs, sinon un
        // écran finit par accepter ce qu'un autre refuse.
        if (isset($champs['employeur']) && function_exists('famicardOptionsEmployeur')) {
            $champs['employeur']['options'] = famicardOptionsEmployeur();
        }
        if (isset($champs['contrat']) && function_exists('famicardOptionsContrat')) {
            $champs['contrat']['options'] = famicardOptionsContrat();
        }
        if (isset($champs['interim']) && function_exists('famicardAgences')) {
            $options = [];
            foreach (famicardAgences($db) as $agence) {
                // Le nom NU, tel qu'il est en base : c'est cette chaîne exacte
                // que FamiJob compare pour donner à une agence la vue sur ses
                // gens. Un libellé enjolivé ici finirait recopié dans la
                // colonne, et la comparaison ne tomberait plus juste.
                $options[$agence] = $agence;
            }
            $champs['interim']['options'] = $options;
            // Une agence disparue de la table ne doit pas s'effacer en silence
            // de la fiche de quelqu'un : l'écran d'édition rajoute la valeur
            // courante si elle manque (voir modifier.php).
        }

        return $champs + famicardChampsLibres($db);
    }
}

if (!function_exists('famicardValeursLibres')) {
    /** champ_id => valeur, pour un collaborateur. */
    function famicardValeursLibres(PDO $db, $userId)
    {
        $valeurs = [];
        try {
            $st = $db->prepare("SELECT champ_id, valeur FROM famicard_valeurs WHERE user_id = ?");
            $st->execute([(int) $userId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $valeurs[(int) $v['champ_id']] = (string) $v['valeur'];
            }
        } catch (Exception $e) {
            // tables pas encore créées
        }
        return $valeurs;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LECTURE
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('famicardPeutVoir')) {
    /**
     * Le regardeur a-t-il le droit de voir ce champ sur cette fiche ?
     * Un seul point de décision, appelé par TOUTES les vues : une page qui
     * oublierait de filtrer ne peut afficher que ce qu'on l'autorise à afficher.
     */
    function famicardPeutVoir(array $champ, $estAdmin, $estSaPropreFiche)
    {
        switch ($champ['visible'] ?? 'admin') {
            case 'tous':
                return true;
            case 'soi':
                return $estAdmin || $estSaPropreFiche;
            case 'admin':
            default:
                return (bool) $estAdmin;
        }
    }
}

if (!function_exists('famicardPeutModifier')) {
    /**
     * Le regardeur a-t-il le droit d'ÉCRIRE ce champ sur cette fiche ?
     *
     * Un seul point de décision, comme famicardPeutVoir() pour la lecture.
     * L'écran d'édition ne décide de rien : il demande, pour l'affichage ET
     * au moment d'enregistrer. Un champ absent du formulaire mais posté à la
     * main est refusé par le même test.
     */
    function famicardPeutModifier(array $champ, $estAdmin, $estSaPropreFiche)
    {
        // On ne modifie jamais ce qu'on n'a pas le droit de voir : sans ça, un
        // champ réservé aux admins deviendrait modifiable à l'aveugle par son
        // titulaire, qui découvrirait son contenu en l'écrasant.
        if (!famicardPeutVoir($champ, $estAdmin, $estSaPropreFiche)) {
            return false;
        }

        switch ($champ['modifiable'] ?? 'admin') {
            case 'soi':
                return $estAdmin || $estSaPropreFiche;
            case 'admin':
                return (bool) $estAdmin;
            case 'jamais':
            default:
                return false;
        }
    }
}

if (!function_exists('famicardValeurAffichee')) {
    /**
     * Valeur lisible d'un champ pour une ligne `utilisateurs`.
     * Les identifiants techniques sont traduits : « Famiflora Mouscron », pas « 1 ».
     *
     * @param array $libres champ_id => valeur (voir famicardValeursLibres)
     */
    function famicardValeurAffichee($cle, array $champ, array $ligne, array $magasins = [], array $libres = [])
    {
        // Champ libre : la valeur ne vient pas de `utilisateurs`.
        if (!empty($champ['champ_id'])) {
            return (string) ($libres[(int) $champ['champ_id']] ?? '');
        }

        $colonne = $champ['colonne'] ?? null;
        if (!$colonne || !array_key_exists($colonne, $ligne)) {
            return '';
        }

        $valeur = $ligne[$colonne];
        if ($valeur === null || $valeur === '') {
            return '';
        }

        // Un champ à liste montre son LIBELLÉ, pas son code : « Intérim
        // (agence) » et non « interim ». Règle générale plutôt qu'un cas par
        // champ — sinon le prochain champ à liste s'affichera en brut, et
        // personne ne saura pourquoi celui-là seulement.
        if (!empty($champ['options']) && isset($champ['options'][(string) $valeur])) {
            return (string) $champ['options'][(string) $valeur];
        }

        if ($cle === 'role') {
            return famicardLibelleRole($valeur);
        }

        if ($cle === 'site_id') {
            return $magasins[(int) $valeur] ?? ('Lieu #' . (int) $valeur);
        }

        if ($cle === 'date_naissance') {
            // 0000-00-00 traîne dans les vieilles lignes : ce n'est pas une date.
            if ($valeur === '0000-00-00') {
                return '';
            }
            $d = date_create((string) $valeur);
            return $d ? $d->format('d/m/Y') : (string) $valeur;
        }

        if ($cle === 'derniere_visite') {
            $d = date_create((string) $valeur);
            return $d ? $d->format('d/m/Y H:i') : (string) $valeur;
        }

        return (string) $valeur;
    }
}

if (!function_exists('famicardChampsManquants')) {
    /**
     * Les champs OBLIGATOIRES encore vides sur une fiche. C'est ce qui permet
     * de dire « ta carte est incomplète » sans lister à la main, dans chaque
     * écran, ce qui compte comme complet.
     */
    function famicardChampsManquants(array $champs, array $ligne, array $libres = [], array $magasins = [])
    {
        $manquants = [];
        foreach ($champs as $cle => $champ) {
            if (empty($champ['requis'])) {
                continue;
            }
            if (famicardValeurAffichee($cle, $champ, $ligne, $magasins, $libres) === '') {
                $manquants[$cle] = $champ;
            }
        }
        return $manquants;
    }
}

if (!function_exists('famicardPlacements')) {
    /**
     * ⚠️ CECI EST LA PLANIFICATION, PAS LE RATTACHEMENT RH.
     *
     * Les départements où le planning de FamiJob peut PLACER quelqu'un, en UNE
     * requête (une par ligne serait catastrophique sur la base des
     * collaborateurs, qui en affiche des centaines) :
     *   user_id => ['secteur_id', 'secteur_nom', 'departement_id',
     *               'departement_nom', 'tous', 'ids']
     *
     * Ça vient de `student_department_links` : plusieurs départements par
     * personne, classés par `priority_rank`, et c'est un VIVIER — « il peut
     * travailler ici, de préférence là ». « De quoi relève cette personne » est
     * une autre question, une autre table, un autre fichier
     * (includes/rattachement.php). Confondre les deux fabrique des données
     * fausses dans les deux sens.
     *
     * Le référentiel, lui, est commun et unique : `sectors` et `departments`,
     * définis dans Famiformation/includes/secteurs.php, repris du dépôt live.
     */
    function famicardPlacements(PDO $db, array $userIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) {
            return [];
        }
        // Des entiers castés, jamais du texte : rien à échapper ici.
        $in = implode(',', $ids);

        // Le rattachement est MULTIPLE : `student_department_links` porte
        // plusieurs départements par personne, classés par priority_rank —
        // c'est ce dont le matching intérim se sert. On lit tout, et on garde
        // le premier comme rattachement principal pour l'affichage de la fiche,
        // sans perdre les autres.
        $sql = "SELECT l.student_id AS user_id, l.priority_rank,
                       d.id AS departement_id, d.department_name AS departement_nom,
                       s.id AS secteur_id, s.sector_name AS secteur_nom
                FROM student_department_links l
                JOIN departments d ON d.id = l.department_id
                LEFT JOIN sectors s ON s.id = d.sector_id
                WHERE l.student_id IN ($in)
                ORDER BY l.student_id ASC, l.priority_rank ASC, d.department_name ASC";

        $res = [];
        try {
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $uid = (int) $r['user_id'];

                // Le premier de la liste (priorité la plus haute) devient le
                // rattachement affiché ; les suivants s'ajoutent à la liste.
                if (!isset($res[$uid])) {
                    $res[$uid] = [
                        'secteur_id'      => $r['secteur_id'] !== null ? (int) $r['secteur_id'] : null,
                        'secteur_nom'     => (string) ($r['secteur_nom'] ?? ''),
                        'departement_id'  => (int) $r['departement_id'],
                        'departement_nom' => (string) $r['departement_nom'],
                        'tous'            => [],
                        'ids'             => [],
                    ];
                }
                $res[$uid]['tous'][] = (string) $r['departement_nom'];
                // Les identifiants DANS L'ORDRE DE PRIORITÉ (le ORDER BY
                // ci-dessus) : c'est ce que l'écran d'édition renvoie, et c'est
                // l'ordre lui-même qui porte la priorité — pas un numéro saisi
                // à côté, qui finirait par ne plus correspondre à la liste.
                $res[$uid]['ids'][] = (int) $r['departement_id'];
            }
        } catch (Exception $e) {
            // Tables absentes (base pas encore à jour) : la fiche s'affiche
            // simplement sans rattachement, plutôt que de ne pas s'afficher.
            return [];
        }

        return $res;
    }
}

if (!function_exists('famicardAjouteRattachement')) {
    /**
     * Pose les pseudo-colonnes de rattachement dans une ligne `utilisateurs`,
     * pour que le modèle les lise comme n'importe quel autre champ.
     *
     * DEUX SOURCES, DEUX SENS, et c'est pour ça qu'elles arrivent par deux
     * paramètres distincts plutôt que fondues en un seul :
     *   $rhs        → de quoi la personne relève (famicard_rattachement)
     *   $placements → où le planning peut la placer (student_department_links)
     *
     * Les clés sont TOUJOURS posées, même vides : sans ça,
     * famicardValeurAffichee() ne trouverait pas la colonne et renverrait ''
     * sans qu'on puisse distinguer « pas rattaché » de « colonne oubliée ».
     */
    function famicardAjouteRattachement(array $ligne, array $rhs, array $placements = [])
    {
        $id = (int) ($ligne['id'] ?? 0);
        $r = $rhs[$id] ?? [];

        $ligne['secteur_id']      = $r['secteur_id'] ?? null;
        $ligne['secteur_nom']     = $r['secteur_nom'] ?? '';
        $ligne['departement_id']  = $r['departement_id'] ?? null;
        $ligne['departement_nom'] = $r['departement_nom'] ?? '';

        // ── LA PLANIFICATION, à part et en lecture seule ─────────────────
        // Tous les rayons, pas seulement le principal : une fiche qui montre
        // « Bougies » alors que le planning peut aussi l'envoyer en « Festif »
        // est fausse à moitié, ce qui est pire que muette.
        $p = $placements[$id] ?? [];
        $tous = $p['tous'] ?? [];
        $ligne['placement_nom'] = $tous ? implode(' · ', $tous) : '';

        return $ligne;
    }
}

if (!function_exists('famicardMagasins')) {
    /** id => nom des lieux de travail (widget_sites, déjà utilisée par le widget). */
    function famicardMagasins(PDO $db)
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            foreach ($db->query("SELECT id, nom FROM widget_sites ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $cache[(int) $s['id']] = (string) $s['nom'];
            }
        } catch (Exception $e) {
            // Table absente : on affichera l'identifiant brut.
        }
        return $cache;
    }
}

if (!function_exists('famicardLibelleRole')) {
    /** Mêmes libellés que relance_mdp.php, pour ne pas inventer un 2e vocabulaire. */
    function famicardLibelleRole($role)
    {
        $libelles = [
            'beta'               => 'Beta',
            // 🏬 Sans cette ligne, une fiche « betalapanne » affichait son nom
            // technique — et l'écran de création aurait proposé un profil
            // illisible dans une liste où tous les autres ont un vrai libellé.
            'betalapanne'        => 'Beta La Panne',
            'etudiant'           => 'Étudiant',
            'employe_magasin'    => 'Magasin',
            'employe_logistique' => 'Logistique',
            'teamcoach'          => 'Teamcoach',
            'mentor'             => 'Mentor',
            'evaluateur'         => 'Évaluateur',
            'agence_interim'     => 'Agence intérim',
            'admin'              => 'Admin',
        ];
        $role = (string) $role;
        return $libelles[$role] ?? $role;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// BADGE
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('famicardMentionBadge')) {
    /**
     * La mention imprimée sous le prénom, en français ET en néerlandais.
     *
     * Un étudiant porte « Étudiant / Student » : il n'est pas là pour renseigner
     * le client de la même façon, et l'annoncer évite qu'on lui pose des
     * questions auxquelles il n'a pas à répondre. Tout le reste du personnel
     * porte « À votre disposition / Tot uw dienst ».
     */
    function famicardMentionBadge($role)
    {
        if ((string) $role === 'etudiant') {
            return ['fr' => 'Étudiant', 'nl' => 'Student'];
        }
        return ['fr' => 'À votre disposition', 'nl' => 'Tot uw dienst'];
    }
}
