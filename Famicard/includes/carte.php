<?php
// ============================================================
// FAMICARD — LE MODÈLE DE LA CARTE.
//
// C'est le SEUL endroit où l'on décrit ce que contient la carte d'identité
// d'un collaborateur. Tout le reste (affichage, badge imprimé, export Excel,
// registre RGPD) lit cette liste au lieu de redéfinir ses propres colonnes.
//
// POURQUOI CENTRALISER : un champ ajouté ici apparaît partout d'un coup, et —
// surtout — un champ marqué « personnel » ne peut pas se retrouver par
// inadvertance dans un export ou sur un badge affiché en magasin. La règle de
// confidentialité voyage AVEC le champ, elle n'est pas réécrite à chaque page.
//
// ⚠️ ÉTAT : liste de DÉPART, à valider avec Jimmy. Les champs marqués
// 'colonne' => null n'existent PAS encore en base : ils sont affichés comme
// « à définir » et ignorés par les exports. Aucune colonne n'est créée
// automatiquement — ce sera une décision explicite, pas un effet de bord.
// ============================================================

if (!function_exists('famicardChamps')) {
    /**
     * Description de tous les champs de la carte.
     *
     * Clés de chaque champ :
     *   libelle / libelle_nl : intitulé affiché (le site est bilingue FR/NL).
     *   colonne  : colonne de `utilisateurs`, ou null si le champ reste à créer.
     *   groupe   : regroupement d'affichage.
     *   requis   : true = sans lui la carte n'a pas de sens (accès aux services).
     *   nature   : 'service'  → nécessaire au fonctionnement (base légale : contrat)
     *              'personnel'→ donnée personnelle, à ne montrer qu'à qui de droit
     *              'sensible' → ne doit jamais sortir en export ni sur un badge
     *   visible  : qui a le droit de voir la valeur — 'tous', 'soi', 'admin'
     *              ('soi' = le collaborateur concerné, plus les admins)
     *   badge    : true si le champ peut figurer sur le badge imprimé.
     */
    function famicardChamps()
    {
        return [
            // ── IDENTITÉ ───────────────────────────────────────────────────
            'prenom' => [
                'libelle' => 'Prénom', 'libelle_nl' => 'Voornaam',
                'colonne' => 'prenom', 'groupe' => 'identite',
                'requis' => true, 'nature' => 'service', 'visible' => 'tous', 'badge' => true,
            ],
            'nom' => [
                'libelle' => 'Nom', 'libelle_nl' => 'Naam',
                'colonne' => 'nom', 'groupe' => 'identite',
                'requis' => true, 'nature' => 'service', 'visible' => 'tous', 'badge' => true,
            ],
            'photo_profil' => [
                'libelle' => 'Photo', 'libelle_nl' => 'Foto',
                'colonne' => 'photo_profil', 'groupe' => 'identite',
                'requis' => false, 'nature' => 'personnel', 'visible' => 'tous', 'badge' => false,
            ],
            // La date de naissance est DÉJÀ utilisée par le site (thème
            // d'anniversaire). Elle reste facultative et n'apparaît ni sur le
            // badge ni dans un export : une date de naissance affichée en
            // magasin, c'est une donnée personnelle exposée sans raison.
            'date_naissance' => [
                'libelle' => 'Date de naissance', 'libelle_nl' => 'Geboortedatum',
                'colonne' => 'date_naissance', 'groupe' => 'identite',
                'requis' => false, 'nature' => 'personnel', 'visible' => 'soi', 'badge' => false,
            ],

            // ── CONTACT ────────────────────────────────────────────────────
            'email' => [
                'libelle' => 'Adresse e-mail', 'libelle_nl' => 'E-mailadres',
                'colonne' => 'email', 'groupe' => 'contact',
                'requis' => true, 'nature' => 'service', 'visible' => 'soi', 'badge' => false,
            ],
            'ville' => [
                'libelle' => 'Ville', 'libelle_nl' => 'Woonplaats',
                'colonne' => 'ville', 'groupe' => 'contact',
                'requis' => false, 'nature' => 'personnel', 'visible' => 'soi', 'badge' => false,
            ],
            'telephone' => [
                'libelle' => 'Téléphone', 'libelle_nl' => 'Telefoon',
                'colonne' => null, 'groupe' => 'contact',
                'requis' => false, 'nature' => 'personnel', 'visible' => 'soi', 'badge' => false,
            ],

            // ── RATTACHEMENT ───────────────────────────────────────────────
            'site_id' => [
                'libelle' => 'Magasin', 'libelle_nl' => 'Winkel',
                'colonne' => 'site_id', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'tous', 'badge' => true,
            ],
            'departement' => [
                'libelle' => 'Département', 'libelle_nl' => 'Afdeling',
                'colonne' => null, 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'tous', 'badge' => true,
            ],
            'interim' => [
                'libelle' => 'Agence intérim', 'libelle_nl' => 'Interimkantoor',
                'colonne' => 'interim', 'groupe' => 'rattachement',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin', 'badge' => false,
            ],

            // ── COMPTE / ACCÈS AUX SERVICES ────────────────────────────────
            'identifiant' => [
                'libelle' => 'Identifiant', 'libelle_nl' => 'Gebruikersnaam',
                'colonne' => 'identifiant', 'groupe' => 'compte',
                'requis' => true, 'nature' => 'service', 'visible' => 'soi', 'badge' => false,
            ],
            'role' => [
                'libelle' => 'Profil', 'libelle_nl' => 'Profiel',
                'colonne' => 'role', 'groupe' => 'compte',
                'requis' => true, 'nature' => 'service', 'visible' => 'tous', 'badge' => true,
            ],
            'statut' => [
                'libelle' => 'Statut', 'libelle_nl' => 'Status',
                'colonne' => 'statut', 'groupe' => 'compte',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin', 'badge' => false,
            ],
            'derniere_visite' => [
                'libelle' => 'Dernière visite', 'libelle_nl' => 'Laatste bezoek',
                'colonne' => 'derniere_visite', 'groupe' => 'compte',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin', 'badge' => false,
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
        ];
    }
}

if (!function_exists('famicardChampsDisponibles')) {
    /**
     * Les champs réellement adossés à une colonne existante. C'est cette liste
     * que les exports et les écrans doivent parcourir : elle ne peut pas
     * produire de requête SQL sur une colonne absente.
     */
    function famicardChampsDisponibles()
    {
        return array_filter(famicardChamps(), static function ($c) {
            return !empty($c['colonne']);
        });
    }
}

if (!function_exists('famicardChampsManquants')) {
    /** Les champs décidés mais pas encore en base — l'état d'avancement. */
    function famicardChampsManquants()
    {
        return array_filter(famicardChamps(), static function ($c) {
            return empty($c['colonne']);
        });
    }
}

if (!function_exists('famicardPeutVoir')) {
    /**
     * Le regardeur a-t-il le droit de voir ce champ sur cette fiche ?
     *
     * Un seul point de décision, appelé par TOUTES les vues. Le jour où une
     * page oublie de filtrer, elle n'expose rien : elle ne peut afficher que
     * ce que cette fonction autorise.
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

if (!function_exists('famicardValeurAffichee')) {
    /**
     * Valeur lisible d'un champ pour une ligne `utilisateurs`.
     * Les identifiants techniques (magasin) sont traduits en nom lisible :
     * « Famiflora Mouscron » et pas « 1 ».
     */
    function famicardValeurAffichee($cle, array $champ, array $ligne, array $magasins = [])
    {
        $colonne = $champ['colonne'] ?? null;
        if (!$colonne || !array_key_exists($colonne, $ligne)) {
            return '';
        }

        $valeur = $ligne[$colonne];
        if ($valeur === null || $valeur === '') {
            return '';
        }

        if ($cle === 'site_id') {
            return $magasins[(int) $valeur] ?? ('Magasin #' . (int) $valeur);
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

if (!function_exists('famicardMagasins')) {
    /** id => nom des magasins (table widget_sites, déjà utilisée par le widget). */
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
            // Table absente sur une base incomplète : on affichera l'identifiant brut.
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
