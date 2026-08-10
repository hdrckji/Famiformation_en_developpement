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
            'interim' => [
                'libelle' => 'Agence intérim', 'libelle_nl' => 'Interimkantoor',
                'colonne' => 'interim', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin',
                'modifiable' => 'admin', 'saisie' => 'texte', 'badge' => false,
            ],
            // ── SECTEUR ET DÉPARTEMENT ─────────────────────────────────────
            // Ces deux-là ne sont NI une colonne de `utilisateurs` NI un champ
            // libre : ils vivent dans famicard_affectations. Les « colonnes »
            // ci-dessous sont donc des pseudo-colonnes, remplies dans la ligne
            // par famicardAjouteRattachement() avant tout affichage.
            //
            // 'saisie' => 'rattachement' : ils ne s'éditent pas dans le
            // formulaire champ par champ (les deux se choisissent ENSEMBLE, un
            // département impliquant son secteur), mais dans un bloc dédié de
            // modifier.php — comme la photo.
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
            ],

            // ── COMPTE / ACCÈS AUX SERVICES ────────────────────────────────
            // L'identifiant sert à se connecter : le changer ici couperait
            // l'accès sans prévenir personne. Tant que la gestion des comptes
            // n'a pas rejoint Famicard, il se règle avec le mot de passe, dans
            // l'écran qui gère les deux.
            'identifiant' => [
                'libelle' => 'Identifiant', 'libelle_nl' => 'Gebruikersnaam',
                'colonne' => 'identifiant', 'groupe' => 'compte',
                'requis' => true, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'jamais', 'saisie' => 'texte', 'badge' => false,
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

if (!function_exists('famicardChamps')) {
    /** Socle + libres. La liste complète, dans l'ordre d'affichage. */
    function famicardChamps(PDO $db = null)
    {
        $champs = famicardChampsSocle();
        if ($db instanceof PDO) {
            $champs += famicardChampsLibres($db);
        }
        return $champs;
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

if (!function_exists('famicardRattachements')) {
    /**
     * Le rattachement de plusieurs collaborateurs, en UNE requête :
     *   user_id => ['secteur_id', 'secteur_nom', 'departement_id', 'departement_nom']
     *
     * Une requête par ligne serait invisible sur une fiche et catastrophique
     * sur la base des collaborateurs, qui en affiche des centaines.
     *
     * Le secteur et le département ne sont pas des colonnes de `utilisateurs` :
     * ils vivent dans famicard_affectations, que l'administrateur règle depuis
     * Famicard (modifier.php). La définition des secteurs et de leurs
     * départements est dans Famiformation/includes/organisation.php — un
     * emplacement de fichier, pas un partage des rôles.
     */
    function famicardRattachements(PDO $db, array $userIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) {
            return [];
        }
        // Des entiers castés, jamais du texte : rien à échapper ici.
        $in = implode(',', $ids);

        $complet = "SELECT a.user_id, a.secteur_id, s.nom AS secteur_nom,
                           a.departement_id, d.nom AS departement_nom
                    FROM famicard_affectations a
                    LEFT JOIN famicard_secteurs s ON s.id = a.secteur_id
                    LEFT JOIN famicard_departements d ON d.id = a.departement_id
                    WHERE a.user_id IN ($in)";

        // Repli sans le département : la colonne a été ajoutée après coup, et
        // son ALTER peut avoir échoué. Le secteur doit rester affiché dans ce
        // cas plutôt que de disparaître avec lui.
        $simple = "SELECT a.user_id, a.secteur_id, s.nom AS secteur_nom,
                          NULL AS departement_id, NULL AS departement_nom
                   FROM famicard_affectations a
                   LEFT JOIN famicard_secteurs s ON s.id = a.secteur_id
                   WHERE a.user_id IN ($in)";

        foreach ([$complet, $simple] as $sql) {
            try {
                $res = [];
                foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $res[(int) $r['user_id']] = [
                        'secteur_id'       => $r['secteur_id'] !== null ? (int) $r['secteur_id'] : null,
                        'secteur_nom'      => (string) ($r['secteur_nom'] ?? ''),
                        'departement_id'   => $r['departement_id'] !== null ? (int) $r['departement_id'] : null,
                        'departement_nom'  => (string) ($r['departement_nom'] ?? ''),
                    ];
                }
                return $res;
            } catch (Exception $e) {
                // On tente le repli ; si les deux échouent, les tables n'existent
                // pas encore et la fiche s'affiche simplement sans rattachement.
            }
        }

        return [];
    }
}

if (!function_exists('famicardAjouteRattachement')) {
    /**
     * Pose les pseudo-colonnes de rattachement dans une ligne `utilisateurs`,
     * pour que le modèle les lise comme n'importe quel autre champ.
     *
     * Les clés sont TOUJOURS posées, même vides : sans ça,
     * famicardValeurAffichee() ne trouverait pas la colonne et renverrait ''
     * sans qu'on puisse distinguer « pas rattaché » de « colonne oubliée ».
     */
    function famicardAjouteRattachement(array $ligne, array $rattachements)
    {
        $r = $rattachements[(int) ($ligne['id'] ?? 0)] ?? [];

        $ligne['secteur_id']      = $r['secteur_id'] ?? null;
        $ligne['secteur_nom']     = $r['secteur_nom'] ?? '';
        $ligne['departement_id']  = $r['departement_id'] ?? null;
        $ligne['departement_nom'] = $r['departement_nom'] ?? '';

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
