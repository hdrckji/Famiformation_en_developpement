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
     * ⚠️ L'AGENCE CORRIGE SES PROPRES INFORMATIONS (décision de Jimmy) :
     * personne de contact et adresses. Elle sait avant nous que son contact a
     * changé, et le contrôle existe — la correction s'applique, puis l'admin
     * confirme ou rétablit, exactement comme pour un collaborateur.
     *
     * ⚠️ SAUF LE NOM DE L'AGENCE, et pour une raison précise : c'est la CLÉ
     * qui relie l'agence à ses gens (FamiJob compare `utilisateurs.interim` à
     * ce nom). Le changer sans réécrire toutes les fiches rattachées couperait
     * l'agence de ses propres intérimaires. Ce report existe — il est fait dans
     * agences.php, dans une transaction — et il n'a pas à être refait ici, ni
     * confié à un tiers.
     *
     * ⚠️ 'saisie' => 'agence' : ces champs ne vivent PAS dans `utilisateurs`.
     * L'écriture générique les refuse (famicardEcritValeur), et modifier.php
     * les envoie à famicardEcritChampAgence().
     */
    function famicardChampsAgence()
    {
        return [
            'agence_nom' => [
                'libelle' => "Nom de l'agence", 'libelle_nl' => 'Naam van het kantoor',
                'colonne' => 'agence_nom', 'groupe' => 'agence',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'jamais', 'saisie' => 'agence', 'badge' => false,
                'aide' => 'Ce nom relie l\'agence à ses intérimaires : il se change côté administration, qui reporte le changement sur les fiches.',
            ],
            'agence_contact' => [
                'libelle' => 'Personne de contact', 'libelle_nl' => 'Contactpersoon',
                'colonne' => 'agence_contact', 'groupe' => 'agence',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'soi', 'saisie' => 'agence', 'badge' => false,
            ],
            'agence_email1' => [
                'libelle' => 'Email principal', 'libelle_nl' => 'Hoofdmailadres',
                'colonne' => 'agence_email1', 'groupe' => 'agence',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'soi', 'saisie' => 'agence', 'badge' => false,
                'aide' => 'C\'est là que partent les horaires de vos intérimaires.',
            ],
            'agence_email2' => [
                'libelle' => 'Second email', 'libelle_nl' => 'Tweede mailadres',
                'colonne' => 'agence_email2', 'groupe' => 'agence',
                'requis' => false, 'nature' => 'service', 'visible' => 'soi',
                'modifiable' => 'soi', 'saisie' => 'agence', 'badge' => false,
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

if (!function_exists('famicardColonneAgence')) {
    /**
     * Le champ de la fiche → la colonne d'`interim_agences`.
     *
     * Une table de correspondance, et une seule : l'écriture et le
     * rétablissement la lisent tous les deux. Deux listes finiraient par ne
     * plus dire la même chose, et l'une des deux écrirait dans le vide.
     *
     * Le NOM n'y figure pas : il ne se modifie pas depuis la fiche.
     */
    function famicardColonneAgence($cle)
    {
        $table = [
            'agence_contact' => 'nom_contact',
            'agence_email1'  => 'email_1',
            'agence_email2'  => 'email_2',
        ];
        return $table[(string) $cle] ?? '';
    }
}

if (!function_exists('famicardEcritChampAgence')) {
    /**
     * Écrit UN champ d'agence, dans `interim_agences`.
     *
     * ⚠️ Ne contrôle NI les droits NI le format : c'est fait en amont
     * (famicardPeutModifier, et la validation des adresses dans modifier.php).
     * Cette fonction écrit — comme famicardEcritValeur pour `utilisateurs`.
     *
     * On vise la ligne PAR SON NOM, celui inscrit sur le compte : c'est le même
     * chemin que partout ailleurs, et il évite de faire circuler un identifiant
     * de table dans un formulaire.
     */
    function famicardEcritChampAgence(PDO $db, $nomAgence, $cle, $valeur)
    {
        $nomAgence = trim((string) $nomAgence);
        $colonne = famicardColonneAgence($cle);
        if ($nomAgence === '' || $colonne === '') {
            return false;
        }
        try {
            // Le nom de colonne vient de la table ci-dessus, jamais d'une
            // requête : rien à échapper, mais le test reste une ceinture.
            if (!preg_match('~^[a-z_0-9]+$~', $colonne)) {
                return false;
            }
            $db->prepare("UPDATE interim_agences SET `$colonne` = ? WHERE nom_agence = ?")
               ->execute([($valeur === '' ? null : $valeur), $nomAgence]);
            return true;
        } catch (Exception $e) {
            return false;
        }
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
