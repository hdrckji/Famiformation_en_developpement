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
