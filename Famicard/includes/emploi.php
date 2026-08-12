<?php
// ============================================================
// FAMICARD — L'EMPLOI : CHEZ QUI, COMMENT, ET AVEC QUELS DROITS.
//
// LE PROBLÈME QU'ON RÉSOUT ICI. Une seule colonne, `interim`, portait un nom
// d'agence et servait à répondre à trois questions différentes — et Famiflora,
// qui est l'ENTREPRISE et pas une agence, y était rangée comme une agence.
// Le code s'était fabriqué sa propre définition, recopiée à deux endroits :
//
//     interim non vide  ET  interim != 'famiflora'  ET  role != 'etudiant'
//
// (admin_user.php, interim_fixes.php). Une règle métier écrite dans un WHERE,
// c'est le symptôme : la question n'avait pas de colonne pour la porter.
//
// TROIS QUESTIONS, TROIS COLONNES. Elles ne se recouvrent jamais :
//
//   1. CHEZ QUI travaille-t-elle ?      → `employeur`  interne / intérim / indépendant
//   2. COMMENT est-elle engagée ?       → `contrat`    étudiant / flexi / fixe
//   3. QU'A-T-ELLE LE DROIT D'OUVRIR ?  → `role`       (existant, RBAC)
//
// ⚠️ `role` N'EST PAS TOUCHÉ, et c'est la contrainte de départ (Jimmy) : aucune
// ligne de code qui lit `role` ne change. Les deux nouvelles colonnes sont
// DESCRIPTIVES — elles ne décident d'aucun accès. Le jour où FamiJob voudra
// travailler sur `contrat = 'etudiant'` plutôt que sur `role = 'etudiant'`, la
// donnée sera là et prête ; ce jour-là seulement, et de son côté.
//
// « EXTERNE » NE SE STOCKE PAS. C'est « employeur != interne », donc une
// déduction (famicardEstExterne). Une colonne de plus, et on aurait un jour une
// fiche marquée interne ET externe — l'incohérence qu'aucun écran ne rattrape.
//
// ⚠️ `interim` N'EST PAS TOUCHÉE NON PLUS, et surtout pas vidée. Ce n'est pas
// « est-elle intérimaire » : c'est QUI SUIT SON DOSSIER. Pour un externe c'est
// son agence ; pour un recrutement direct c'est « Famiflora », c'est-à-dire
// le suivi interne — qui a une ligne dans `interim_agences`, un compte `agence_interim`
// à ce nom, et qui reçoit à ce titre les mails des étudiants recrutés en
// direct. Vider cette colonne lui retirerait sa vue et couperait ces envois.
// 443 références y touchent dans FamiFormation et FamiJob : on ajoute à côté,
// on ne déplace rien.
// ============================================================

if (!function_exists('famicardEmployeurs')) {
    /**
     * Chez qui la personne travaille.
     *
     * 'agence' => true : cet employeur suppose une agence dans `interim`.
     * C'est ce qui permet à un écran de savoir quand demander l'agence, sans
     * recopier « si intérim alors… » dans chaque formulaire.
     */
    function famicardEmployeurs()
    {
        return [
            'interne' => [
                'libelle' => 'Interne (Famiflora)', 'libelle_nl' => 'Intern (Famiflora)',
                'court' => 'Interne', 'agence' => false,
            ],
            'interim' => [
                'libelle' => 'Intérim (agence)', 'libelle_nl' => 'Interim (kantoor)',
                'court' => 'Intérim', 'agence' => true,
            ],
            'independant' => [
                'libelle' => 'Indépendant', 'libelle_nl' => 'Zelfstandige',
                'court' => 'Indépendant', 'agence' => false,
            ],
        ];
    }
}

if (!function_exists('famicardContrats')) {
    /**
     * Comment elle est engagée. Trois valeurs, décidées par Jimmy.
     *
     * ⚠️ « Intérim » n'est PAS un contrat ici : c'est un employeur. Un
     * intérimaire peut être étudiant, flexi ou fixe — c'est même exactement ce
     * que gère `interim_fixes.php` côté FamiJob (les intérimaires à horaire
     * fixe). Les mélanger, c'est se condamner à ne plus pouvoir répondre à
     * « combien de flexis chez Konvert ».
     */
    function famicardContrats()
    {
        return [
            'etudiant' => ['libelle' => 'Étudiant', 'libelle_nl' => 'Student'],
            'flexi'    => ['libelle' => 'Flexi',    'libelle_nl' => 'Flexi'],
            'fixe'     => ['libelle' => 'Fixe',     'libelle_nl' => 'Vast'],
        ];
    }
}

if (!function_exists('famicardOptionsEmployeur')) {
    /** valeur => libellé, prêt pour une liste déroulante. */
    function famicardOptionsEmployeur()
    {
        $options = [];
        foreach (famicardEmployeurs() as $cle => $e) {
            $options[$cle] = $e['libelle'];
        }
        return $options;
    }
}

if (!function_exists('famicardOptionsContrat')) {
    function famicardOptionsContrat()
    {
        $options = [];
        foreach (famicardContrats() as $cle => $c) {
            $options[$cle] = $c['libelle'];
        }
        return $options;
    }
}

if (!function_exists('famicardAgences')) {
    /**
     * Les agences connues, `interim_agences` faisant foi — c'est la table que
     * la page « Agences Intérim » du site alimente. Famicard n'en tient pas une
     * seconde liste : deux listes d'agences, et c'est celle qu'on ne met pas à
     * jour qui finit dans les fiches.
     *
     * ⚠️ « Famiflora » en fait partie et y RESTE. Ce n'est pas une agence, mais
     * c'est le dossier suivi en interne, qui a un compte à ce nom.
     * On ne la propose simplement pas comme agence d'intérim : voir
     * famicardAgencesInterim().
     */
    function famicardAgences(PDO $db)
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            if ($db->query("SHOW TABLES LIKE 'interim_agences'")->fetch()) {
                $cache = $db->query("SELECT nom_agence FROM interim_agences ORDER BY nom_agence ASC")
                            ->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Exception $e) {
            $cache = [];
        }
        return $cache;
    }
}

if (!function_exists('famicardAgenceInterne')) {
    /** Le nom sous lequel le recrutement direct est enregistré dans `interim`. */
    function famicardAgenceInterne()
    {
        return 'Famiflora';
    }
}

if (!function_exists('famicardEstAgenceInterne')) {
    /** « Famiflora » n'est pas une agence : c'est le dossier suivi en interne. */
    function famicardEstAgenceInterne($nom)
    {
        return (strtolower(trim((string) $nom)) === strtolower(famicardAgenceInterne()));
    }
}

if (!function_exists('famicardAgencesInterim')) {
    /** Les VRAIES agences — celles qu'on propose quand l'employeur est l'intérim. */
    function famicardAgencesInterim(PDO $db)
    {
        return array_values(array_filter(famicardAgences($db), static function ($a) {
            return !famicardEstAgenceInterne($a);
        }));
    }
}

if (!function_exists('famicardEstExterne')) {
    /**
     * DÉDUIT, jamais stocké. « Externe » n'est pas une donnée de plus : c'est
     * « son employeur n'est pas Famiflora ».
     *
     * Renvoie null quand l'employeur n'est pas encore renseigné : « on ne sait
     * pas » n'est pas « interne », et un écran qui confond les deux affiche une
     * certitude qu'il n'a pas.
     */
    function famicardEstExterne(array $ligne)
    {
        $e = (string) ($ligne['employeur'] ?? '');
        if ($e === '') {
            return null;
        }
        return ($e !== 'interne');
    }
}

if (!function_exists('famicardEmploiResume')) {
    /**
     * Une ligne lisible : « Intérim · Konvert », « Interne », « Indépendant ».
     * Un seul endroit qui compose cette phrase, pour que la fiche, la base et
     * l'export la disent pareil.
     */
    function famicardEmploiResume(array $ligne)
    {
        $employeurs = famicardEmployeurs();
        $cle = (string) ($ligne['employeur'] ?? '');
        if ($cle === '' || !isset($employeurs[$cle])) {
            return '';
        }

        $texte = $employeurs[$cle]['court'];
        $agence = trim((string) ($ligne['interim'] ?? ''));
        if (!empty($employeurs[$cle]['agence']) && $agence !== '') {
            $texte .= ' · ' . $agence;
        }
        return $texte;
    }
}

if (!function_exists('famicardIncoherencesEmploi')) {
    /**
     * Ce qui ne peut pas être vrai en même temps, sur UNE fiche.
     *
     * On SIGNALE, on ne corrige pas tout seul : une correction automatique sur
     * une donnée RH remplacerait une erreur visible par une erreur invisible.
     *
     * @return array liste de phrases, vide si tout est cohérent
     */
    function famicardIncoherencesEmploi(array $ligne)
    {
        $dits = [];
        $employeur = (string) ($ligne['employeur'] ?? '');
        $agence    = trim((string) ($ligne['interim'] ?? ''));
        $contrat   = (string) ($ligne['contrat'] ?? '');
        $role      = (string) ($ligne['role'] ?? '');

        if ($employeur === 'interim' && $agence === '') {
            $dits[] = "Employeur « intérim » mais aucune agence : on ne sait pas qui suit son dossier.";
        }
        if ($employeur === 'interim' && famicardEstAgenceInterne($agence)) {
            $dits[] = "Employeur « intérim » avec l'agence Famiflora : Famiflora est l'entreprise, pas une agence.";
        }
        if ($employeur === 'interne' && $agence !== '' && !famicardEstAgenceInterne($agence)) {
            $dits[] = 'Interne, mais son dossier est suivi par ' . $agence . ' : l\'un des deux est faux.';
        }
        if ($employeur === 'independant' && $agence !== '' && !famicardEstAgenceInterne($agence)) {
            $dits[] = 'Indépendant, mais rattaché à l\'agence ' . $agence . '.';
        }
        // ⚠️ LA PANNE LA PLUS DIFFICILE À VOIR. FamiJob donne à une agence la
        // vue sur « ses » gens en comparant le nom de son dossier au sien, et
        // le suivi interne reçoit les mails des étudiants recrutés en direct par le
        // même chemin. Un étudiant sans dossier n'apparaît donc chez PERSONNE :
        // il n'y a pas de message d'erreur, il est simplement absent des
        // listes. La base en comptait 10 au moment de l'écriture.
        if ($role === 'etudiant' && $agence === '') {
            $dits[] = "Étudiant sans dossier : ni agence ni Famiflora, donc invisible dans les listes"
                    . ' de FamiJob et dans le suivi interne.';
        }

        // Le profil reste maître des accès : on ne « corrige » pas le rôle
        // depuis le contrat, on signale que les deux ne racontent pas la même
        // histoire — c'est au regard humain de trancher.
        if ($contrat === 'etudiant' && $role !== 'etudiant' && $role !== '') {
            $dits[] = "Contrat étudiant mais profil « " . famicardLibelleRole($role) . " » :"
                    . ' FamiJob ne le verra pas dans ses étudiants.';
        }
        if ($contrat !== '' && $contrat !== 'etudiant' && $role === 'etudiant') {
            $dits[] = "Profil étudiant mais contrat « " . (famicardOptionsContrat()[$contrat] ?? $contrat) . " » :"
                    . ' FamiJob continue de le traiter comme un étudiant.';
        }

        return $dits;
    }
}

if (!function_exists('famicardAssureEmploi')) {
    /**
     * Crée les deux colonnes et fait la REPRISE des fiches existantes.
     *
     * ⚠️ À N'APPELER QUE depuis une page d'administration : c'est de la DDL sur
     * `utilisateurs`, la table que FamiFormation et FamiJob partagent. Ajouter
     * une colonne ne casse personne (aucun écran ne fait d'INSERT sans nommer
     * ses colonnes), mais ça ne se fait pas à chaque affichage de page.
     *
     * LA REPRISE NE S'EXÉCUTE QU'UNE FOIS, juste après la création de la
     * colonne : elle déduit ce qui est déductible et ne devine RIEN d'autre.
     *
     *   • agence vide          → interne   (231 fiches au moment de l'écriture)
     *   • agence « Famiflora » → interne   (47 : recrutement direct, pas une agence)
     *   • vraie agence         → intérim   (72)
     *   • profil « étudiant »  → contrat étudiant (109)
     *
     * Le reste des contrats reste VIDE, volontairement. Deviner « fixe » pour
     * les 168 employés magasin remplirait l'écran d'une donnée RH que personne
     * n'a vérifiée, et qui aurait ensuite l'air d'avoir été saisie. « À
     * préciser » se voit ; une valeur fausse, non.
     */
    function famicardAssureEmploi(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return true;
        }

        try {
            $colonnes = famicardColonnesUtilisateurs($db, true);

            if (!isset($colonnes['employeur'])) {
                $db->exec("ALTER TABLE utilisateurs ADD COLUMN employeur VARCHAR(20) NULL AFTER interim");

                // Reprise. `interim` n'est jamais modifiée : on la LIT pour
                // déduire, c'est tout.
                $interne = famicardAgenceInterne();
                $db->prepare(
                    "UPDATE utilisateurs SET employeur = 'interne'
                     WHERE employeur IS NULL
                       AND (interim IS NULL OR TRIM(interim) = '' OR LOWER(TRIM(interim)) = LOWER(?))"
                )->execute([$interne]);

                $db->prepare(
                    "UPDATE utilisateurs SET employeur = 'interim'
                     WHERE employeur IS NULL
                       AND TRIM(COALESCE(interim, '')) <> ''
                       AND LOWER(TRIM(interim)) <> LOWER(?)"
                )->execute([$interne]);
            }

            if (!isset($colonnes['contrat'])) {
                $db->exec("ALTER TABLE utilisateurs ADD COLUMN contrat VARCHAR(20) NULL AFTER employeur");

                // Le SEUL contrat déductible : `role = 'etudiant'` EST
                // l'information « contrat étudiant », c'est ce qu'il veut dire
                // aujourd'hui et ce sur quoi FamiJob travaille. Ce n'est pas
                // une devinette, c'est la même donnée recopiée à sa place.
                $db->exec("UPDATE utilisateurs SET contrat = 'etudiant' WHERE contrat IS NULL AND role = 'etudiant'");
            }

            famicardColonnesUtilisateurs($db, true);
        } catch (Exception $e) {
            // Droits insuffisants ou base indisponible : les écrans qui lisent
            // ces champs les ignorent tant que les colonnes n'existent pas
            // (voir famicardChamps). Rien ne casse, rien ne s'affiche.
            return false;
        }

        $fait = true;
        return true;
    }
}

if (!function_exists('famicardCompteContratsAPreciser')) {
    /**
     * Combien de fiches ACTIVES n'ont pas encore de contrat. Sert la pastille
     * de l'accueil : le chiffre disparaît quand le travail est fini, ce qui
     * est la seule façon qu'une reprise se termine un jour.
     *
     * Les comptes inactifs sont exclus : préciser le contrat de quelqu'un qui
     * est parti n'apporte rien, et gonfler le compteur avec eux le rendrait
     * impossible à ramener à zéro.
     */
    function famicardCompteContratsAPreciser(PDO $db)
    {
        try {
            return (int) $db->query(
                "SELECT COUNT(*) FROM utilisateurs
                 WHERE (contrat IS NULL OR contrat = '')
                   AND (statut IS NULL OR statut <> 'inactif')
                   AND role <> 'agence_interim'"
            )->fetchColumn();
        } catch (Exception $e) {
            return 0; // colonne pas encore créée
        }
    }
}
