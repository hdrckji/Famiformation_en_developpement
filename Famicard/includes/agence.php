<?php
// ============================================================
// FAMICARD — CE QU'UN COMPTE AGENCE A LE DROIT DE VOIR.
//
// Une agence entre dans Famicard, mais elle n'y est pas chez elle. Elle y
// trouve deux choses, et deux seulement (décision de Jimmy) :
//
//   • SA PROPRE FICHE — le compte avec lequel elle se connecte ;
//   • LA LISTE DES PERSONNES QU'ELLE NOUS ENVOIE, réduite à trois
//     informations : nom, prénom, et « étudiant » ou « intérimaire ».
//
// ⚠️ PAS D'EMAIL, PAS DE TÉLÉPHONE, PAS DE PHOTO, PAS DE SECTEUR, PAS DE
// LIEU DE TRAVAIL. Ce n'est pas une omission, c'est la règle : une agence n'a
// aucune raison de connaître l'adresse personnelle ou le rayon de quelqu'un
// qu'elle nous a envoyé. C'est de la minimisation, et c'est ce que le RGPD
// attend d'un partage de données avec un tiers.
//
// ⚠️ LE PÉRIMÈTRE NE VIENT JAMAIS D'UN PARAMÈTRE. Il est lu sur le compte
// connecté lui-même (`utilisateurs.interim`). Un nom d'agence accepté depuis
// l'URL laisserait n'importe quelle agence lire la liste d'une autre en
// changeant un mot dans la barre d'adresse.
// ============================================================

if (!function_exists('famicardEstCompteAgence')) {
    /** Ce compte est-il celui d'une agence, et non d'une personne ? */
    function famicardEstCompteAgence($role)
    {
        return ((string) $role === 'agence_interim');
    }
}

if (!function_exists('famicardAgenceDuCompte')) {
    /**
     * Le nom de l'agence à laquelle ce compte donne accès.
     *
     * ⚠️ LU SUR LE COMPTE, jamais reçu de l'extérieur. C'est le seul point où
     * se décide « quelle agence », et il n'a aucun paramètre.
     *
     * @return string '' si ce n'est pas un compte agence, ou s'il n'est
     *                rattaché à rien — auquel cas il ne verra personne.
     */
    function famicardAgenceDuCompte(PDO $db, $userId)
    {
        try {
            $st = $db->prepare("SELECT role, interim FROM utilisateurs WHERE id = ? LIMIT 1");
            $st->execute([(int) $userId]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return '';
        }
        if (!$u || !famicardEstCompteAgence($u['role'] ?? '')) {
            return '';
        }
        return trim((string) ($u['interim'] ?? ''));
    }
}

if (!function_exists('famicardTypeInterimaire')) {
    /**
     * « Étudiant » ou « Intérimaire » — la seule qualification qu'une agence
     * voit sur les gens qu'elle nous envoie.
     *
     * Le profil fait foi : `role = 'etudiant'` est ce sur quoi FamiJob
     * travaille pour le planning, c'est donc la même distinction que celle qui
     * a un effet réel. On ne descend pas au type de contrat (flexi, fixe) :
     * c'est une donnée d'employeur, pas quelque chose à partager.
     */
    function famicardTypeInterimaire($role)
    {
        return ((string) $role === 'etudiant') ? 'Étudiant' : 'Intérimaire';
    }
}

if (!function_exists('famicardChampsAgence')) {
    /**
     * LA FICHE D'UN COMPTE AGENCE — et elle n'a presque rien à voir avec celle
     * d'un collaborateur (décision de Jimmy).
     *
     * CE QU'ON RETIRE, et pourquoi :
     *   • tout le RATTACHEMENT (secteur, département, lieu de travail,
     *     employeur, contrat, placement) — une agence ne relève d'aucun rayon,
     *     ne travaille dans aucun magasin et n'a pas de contrat chez nous.
     *     Ces lignes n'étaient pas seulement vides : elles laissaient croire
     *     qu'il manquait quelque chose ;
     *   • le PRÉNOM, la PHOTO, la DATE D'ANNIVERSAIRE — ce n'est pas quelqu'un ;
     *   • du CONTACT, il ne reste que la VILLE.
     *
     * CE QU'ON MET À LA PLACE : ce qui identifie vraiment une agence — son nom,
     * sa personne de contact, ses adresses. Ces quatre-là vivent dans
     * `interim_agences` et non dans `utilisateurs` : ce sont des PSEUDO-colonnes,
     * posées dans la ligne par famicardAjouteAgence().
     *
     * ⚠️ EN LECTURE SEULE. Ces informations décident où partent les horaires
     * (voir Famijob/includes/horaires.php) : elles se règlent dans agences.php,
     * par un administrateur, et à un seul endroit. Deux écrans qui écrivent la
     * même adresse finissent toujours par diverger.
     */
    function famicardChampsAgence()
    {
        return [
            'agence_nom' => [
                'libelle' => "Nom de l'agence", 'libelle_nl' => 'Naam van het kantoor',
                'colonne' => 'agence_nom', 'groupe' => 'agence',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'jamais', 'saisie' => 'texte', 'badge' => false,
            ],
            'agence_contact' => [
                'libelle' => 'Personne de contact', 'libelle_nl' => 'Contactpersoon',
                'colonne' => 'agence_contact', 'groupe' => 'agence',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'jamais', 'saisie' => 'texte', 'badge' => false,
                'aide' => 'Se modifie dans « Agences », côté administration.',
            ],
            'agence_email1' => [
                'libelle' => 'Email principal', 'libelle_nl' => 'Hoofdmailadres',
                'colonne' => 'agence_email1', 'groupe' => 'agence',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'jamais', 'saisie' => 'texte', 'badge' => false,
                'aide' => 'C\'est là que partent les horaires de vos intérimaires.',
            ],
            'agence_email2' => [
                'libelle' => 'Second email', 'libelle_nl' => 'Tweede mailadres',
                'colonne' => 'agence_email2', 'groupe' => 'agence',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'jamais', 'saisie' => 'texte', 'badge' => false,
            ],

            // Du contact, la ville et rien d'autre.
            'ville' => [
                'libelle' => 'Ville', 'libelle_nl' => 'Stad',
                'colonne' => 'ville', 'groupe' => 'contact',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'soi', 'saisie' => 'texte', 'badge' => false,
            ],

            // Le compte, inchangé : c'est avec ça qu'on se connecte.
            'identifiant' => [
                'libelle' => 'Identifiant', 'libelle_nl' => 'Gebruikersnaam',
                'colonne' => 'identifiant', 'groupe' => 'compte',
                'requis' => true, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'admin', 'saisie' => 'texte', 'badge' => false,
            ],
            'role' => [
                'libelle' => 'Profil', 'libelle_nl' => 'Profiel',
                'colonne' => 'role', 'groupe' => 'compte',
                'requis' => true, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'admin', 'saisie' => 'liste', 'badge' => false,
            ],
            'statut' => [
                'libelle' => 'Statut', 'libelle_nl' => 'Status',
                'colonne' => 'statut', 'groupe' => 'compte',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin',
                'modifiable' => 'admin', 'saisie' => 'liste', 'badge' => false,
            ],
            'derniere_visite' => [
                'libelle' => 'Dernière visite', 'libelle_nl' => 'Laatste bezoek',
                'colonne' => 'derniere_visite', 'groupe' => 'compte',
                'requis' => false, 'nature' => 'service', 'visible' => 'admin',
                'modifiable' => 'jamais', 'saisie' => 'texte', 'badge' => false,
            ],
        ];
    }
}

if (!function_exists('famicardGroupesAgence')) {
    /** Les mêmes groupes, plus celui qui n'existe que pour une agence. */
    function famicardGroupesAgence()
    {
        $groupes = ['agence' => ['libelle' => "L'agence", 'libelle_nl' => 'Het kantoor']];
        foreach (famicardGroupes() as $cle => $g) {
            // On ne garde que les groupes réellement utilisés par la fiche
            // d'une agence : un titre suivi de rien fait un écran cassé.
            if (in_array($cle, ['contact', 'compte'], true)) {
                $groupes[$cle] = $g;
            }
        }
        return $groupes;
    }
}

if (!function_exists('famicardAjouteAgence')) {
    /**
     * Pose les pseudo-colonnes de l'agence dans une ligne `utilisateurs`.
     *
     * Les clés sont TOUJOURS posées, même vides : sans ça,
     * famicardValeurAffichee() ne trouverait pas la colonne et renverrait ''
     * sans qu'on puisse distinguer « pas renseigné » de « colonne oubliée ».
     */
    function famicardAjouteAgence(PDO $db, array $ligne)
    {
        $ligne['agence_nom'] = trim((string) ($ligne['interim'] ?? ''));
        $ligne['agence_contact'] = '';
        $ligne['agence_email1'] = '';
        $ligne['agence_email2'] = '';

        if ($ligne['agence_nom'] === '') {
            return $ligne;
        }

        try {
            $st = $db->prepare(
                'SELECT nom_contact, email_1, email_2 FROM interim_agences WHERE nom_agence = ? LIMIT 1'
            );
            $st->execute([$ligne['agence_nom']]);
            $a = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return $ligne;
        }
        if ($a) {
            $ligne['agence_contact'] = (string) ($a['nom_contact'] ?? '');
            $ligne['agence_email1']  = (string) ($a['email_1'] ?? '');
            $ligne['agence_email2']  = (string) ($a['email_2'] ?? '');
        }
        return $ligne;
    }
}

if (!function_exists('famicardPersonnesDeLAgence')) {
    /**
     * Les personnes rattachées à cette agence, RÉDUITES À CE QU'ELLE PEUT VOIR.
     *
     * Le SELECT ne lit que trois colonnes, et c'est volontaire : ce qui n'est
     * pas lu ne peut pas fuiter par une page qui afficherait une ligne entière
     * un jour de distraction. La minimisation se fait dans la requête, pas dans
     * le gabarit.
     *
     * Les comptes d'agence sont exclus : une agence n'a pas à se voir
     * elle-même, ni à voir les accès d'une consœur qui porterait le même nom.
     *
     * @return array liste de ['nom', 'prenom', 'type']
     */
    function famicardPersonnesDeLAgence(PDO $db, $nomAgence)
    {
        $nomAgence = trim((string) $nomAgence);
        if ($nomAgence === '') {
            return [];
        }

        try {
            $st = $db->prepare(
                "SELECT nom, prenom, role
                   FROM utilisateurs
                  WHERE interim = ?
                    AND role <> 'agence_interim'
                    AND (statut IS NULL OR statut <> 'inactif')
                  ORDER BY nom ASC, prenom ASC"
            );
            $st->execute([$nomAgence]);
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }

        $gens = [];
        foreach ($lignes as $l) {
            $gens[] = [
                'nom'    => (string) ($l['nom'] ?? ''),
                'prenom' => (string) ($l['prenom'] ?? ''),
                'type'   => famicardTypeInterimaire($l['role'] ?? ''),
            ];
        }
        return $gens;
    }
}
