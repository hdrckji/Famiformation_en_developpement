<?php
// ============================================================
// L'ORGANISATION DE L'ENTREPRISE — SECTEURS ET DÉPARTEMENTS.
//
// VOCABULAIRE (celui du client, à ne pas improviser) :
//   • SECTEUR — les 9 grands ensembles. Un collaborateur y est rattaché.
//   • DÉPARTEMENT — l'échelon en dessous, dans un secteur. Ex. « Fleurs
//     coupées » est un département du secteur « Plantes intérieures ».
//
// ⚠️ ATTENTION AU MOT « DÉPARTEMENT », qui désigne DEUX choses dans cette base :
//   • `famicard_departements` — l'échelon sous le secteur, ici ;
//   • `departments` (ensureDepartmentsTable(), functions.php) — le matching des
//     étudiants de FamiJob, qui n'a rien à voir et qu'on ne touche pas.
// La première porte le mot français et le préfixe famicard_, la seconde le mot
// anglais. C'est mince comme distinction : au moindre doute, vérifier laquelle
// des deux une requête vise avant de la modifier.
//
// POURQUOI UNE TABLE DE RATTACHEMENT ET PAS UNE COLONNE dans `utilisateurs` :
// la même raison que pour les champs libres de Famicard. `utilisateurs` porte
// FamiFormation, FamiJob et le quiz ; un secteur supprimé y laisserait une
// colonne pleine de valeurs qui ne désignent plus rien. Une table à part se
// vide toute seule (ON DELETE CASCADE) et ne concerne personne d'autre.
//
// Ce fichier vit dans FamiFormation, pas dans Famicard, et c'est volontaire :
// Famicard charge déjà la configuration de FamiFormation, donc il y a accès
// gratuitement. L'inverse — le site principal allant chercher un fichier dans
// famicard/ — mettrait la dépendance à l'envers.
// ============================================================

if (!function_exists('famiOrganisationParDefaut')) {
    /**
     * L'organisation telle que le client l'a donnée : chaque secteur avec sa
     * traduction proposée et ses départements, dans l'ordre voulu.
     *
     * Ce tableau ne sert qu'à GARNIR la base. Ensuite c'est la base qui fait
     * foi : un libellé corrigé en base le reste, ce fichier ne le réécrit pas.
     *
     * Les traductions NL des secteurs sont une proposition à faire confirmer ;
     * celles des départements manquent et restent nulles — mieux vaut un champ
     * vide qu'une traduction inventée qui a l'air officielle.
     */
    function famiOrganisationParDefaut()
    {
        return [
            ['Plantes intérieures', 'Kamerplanten', [
                'Plantes intérieures',
                'Fleurs coupées',
                'Pots intérieur',
            ]],
            ['Plantes extérieures', 'Tuinplanten', [
                'Arbustes / Arbres',
                'Plantes méditerranéennes',
                'Vivaces / Aquatiques',
                'Fruitiers / Plantes de haie',
                'Plantes de saison',
                'Grimpantes / Bambous / Graminées',
                'Légumes',
            ]],
            ['Décoration', 'Decoratie', [
                'Déco intérieur',
                'Fleurs artificielles',
                'Jouet & Créa',
                'Bougies',
                'Cuisine',
                'Bain',
                'Ménage',
                'Festif',
                'Licence',
                'Mixte',
                'Lumière / Statues',
                'Meuble jardin / Métal',
                'Piscine',
                'Barbecue',
            ]],
            ['Famizoo', 'Famizoo', [
                'Chiens / Chats',
                'Rongeurs / Oiseaux',
                'Poissons / Reptiles',
                'Extérieur / Basse-cour',
                'Étang / Bassins & fontaines',
            ]],
            ['Famigarden', 'Famigarden', [
                'Terreau',
                'Engrais / Matériel de jardin',
                'Pots extérieur',
                'Bois / Outside living',
            ]],
            ['Food, Accueil & Caisse', 'Food, Onthaal & Kassa', [
                'Abbaye',
                'Leonidas',
                'Lollyland',
                'Kassaplein',
                'Caisse',
                'Accueil (+ info prix)',
                "Feux d'artifice",
            ]],
            ['Bureau', 'Kantoor', [
                'Achats',
                'Marketing & Communication',
                'RH',
                'IT',
                'Finance & Administration',
                'Technique & Facility (dont parking)',
                'Direction',
            ]],
            ['Logistique', 'Logistiek', [
                'Dépôt 1',
                'Dépôt 2',
                'Dépôt FDCM',
                'Magasin / Stock',
                'Transport',
            ]],
            ['Nuit', 'Nacht', [
                'Réassort de nuit',
            ]],
        ];
    }
}

if (!function_exists('famiSecteursParDefaut')) {
    /** Les secteurs seuls (nom, nom_nl), pour qui n'a pas besoin des départements. */
    function famiSecteursParDefaut()
    {
        $secteurs = [];
        foreach (famiOrganisationParDefaut() as $s) {
            $secteurs[] = [$s[0], $s[1]];
        }
        return $secteurs;
    }
}

if (!function_exists('famiAssureSecteurs')) {
    /**
     * Crée les tables si elles manquent, puis garnit ce qui manque.
     *
     * ⚠️ À N'APPELER QUE depuis une page d'administration. Le site a déjà fait
     * le ménage une fois pour retirer la DDL du chemin chaud : pas de
     * CREATE TABLE à chaque affichage de page.
     *
     * GARNISSAGE IDEMPOTENT (« insérer ce qui manque »), et non « insérer si la
     * table est vide » : la table a été créée avant que la liste complète ne
     * soit connue, avec 8 secteurs sur 9 et un libellé différent. Un garnissage
     * conditionné au vide n'aurait jamais rattrapé cet écart, et la base serait
     * restée incomplète en silence.
     *
     * Contrepartie assumée : aucun écran ne permet aujourd'hui de SUPPRIMER un
     * secteur. Le jour où il en existera un, ce garnissage recréerait au
     * rechargement ce qu'on vient d'effacer — il faudra alors le remplacer par
     * une bascule « organisation déjà installée » plutôt que par un test de
     * présence ligne à ligne.
     */
    function famiAssureSecteurs(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return true;
        }

        $db->exec(
            "CREATE TABLE IF NOT EXISTS famicard_secteurs (
                id INT NOT NULL AUTO_INCREMENT,
                nom VARCHAR(120) NOT NULL,
                nom_nl VARCHAR(120) NULL,
                ordre INT NOT NULL DEFAULT 0,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_famicard_secteur (nom)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Les départements. L'unicité porte sur (secteur, nom) et NON sur le nom
        // seul : deux secteurs ont le droit d'avoir un département homonyme, et
        // « Plantes intérieures » est déjà à la fois un secteur et l'un de ses
        // propres départements.
        $db->exec(
            "CREATE TABLE IF NOT EXISTS famicard_departements (
                id INT NOT NULL AUTO_INCREMENT,
                secteur_id INT NOT NULL,
                nom VARCHAR(160) NOT NULL,
                nom_nl VARCHAR(160) NULL,
                ordre INT NOT NULL DEFAULT 0,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_famicard_departement (secteur_id, nom),
                INDEX idx_famicard_departement_secteur (secteur_id),
                CONSTRAINT fk_famicard_departement_secteur FOREIGN KEY (secteur_id)
                    REFERENCES famicard_secteurs (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Le rattachement d'un collaborateur. Clé primaire sur user_id : un
        // collaborateur a UN rattachement. Le département est facultatif — on
        // peut être « du secteur Bureau » sans précision.
        $db->exec(
            "CREATE TABLE IF NOT EXISTS famicard_affectations (
                user_id INT NOT NULL,
                secteur_id INT NOT NULL,
                maj_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id),
                INDEX idx_famicard_affectation_secteur (secteur_id),
                CONSTRAINT fk_famicard_affectation_secteur FOREIGN KEY (secteur_id)
                    REFERENCES famicard_secteurs (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Colonne ajoutée après coup : la table existe peut-être déjà en
        // production, créée quand seul le secteur était connu.
        try {
            $aColonne = $db->query("SHOW COLUMNS FROM famicard_affectations LIKE 'departement_id'")->fetch();
            if (!$aColonne) {
                $db->exec("ALTER TABLE famicard_affectations ADD COLUMN departement_id INT NULL AFTER secteur_id");
                $db->exec("ALTER TABLE famicard_affectations ADD INDEX idx_famicard_affectation_dep (departement_id)");
            }
        } catch (Exception $e) {
            // Colonne déjà là, ou droits insuffisants : le rattachement au
            // secteur continue de fonctionner sans elle.
        }

        // Reprise du libellé qui a changé. Fait AVANT le garnissage, sinon on
        // insérerait le nouveau nom à côté de l'ancien et le même secteur
        // apparaîtrait deux fois dans les listes.
        try {
            $ancien = $db->prepare("SELECT id FROM famicard_secteurs WHERE nom = ?");
            $ancien->execute(['Food/Accueil/Caisse']);
            $idAncien = (int) $ancien->fetchColumn();
            if ($idAncien > 0) {
                $nouveau = $db->prepare("SELECT COUNT(*) FROM famicard_secteurs WHERE nom = ?");
                $nouveau->execute(['Food, Accueil & Caisse']);
                if ((int) $nouveau->fetchColumn() === 0) {
                    // Renommer plutôt que recréer : les collaborateurs déjà
                    // rattachés à ce secteur gardent leur affectation.
                    $db->prepare("UPDATE famicard_secteurs SET nom = ?, nom_nl = ? WHERE id = ?")
                       ->execute(['Food, Accueil & Caisse', 'Food, Onthaal & Kassa', $idAncien]);
                }
            }
        } catch (Exception $e) {
            // Sans importance : le garnissage ci-dessous ajoutera le nouveau nom.
        }

        // ── GARNISSAGE ───────────────────────────────────────────────────
        $trouveSecteur = $db->prepare("SELECT id FROM famicard_secteurs WHERE nom = ?");
        $insSecteur    = $db->prepare("INSERT INTO famicard_secteurs (nom, nom_nl, ordre) VALUES (?, ?, ?)");
        $majOrdre      = $db->prepare("UPDATE famicard_secteurs SET ordre = ? WHERE id = ?");
        $trouveDep     = $db->prepare("SELECT COUNT(*) FROM famicard_departements WHERE secteur_id = ? AND nom = ?");
        $insDep        = $db->prepare("INSERT INTO famicard_departements (secteur_id, nom, ordre) VALUES (?, ?, ?)");

        $ordreSecteur = 10;
        foreach (famiOrganisationParDefaut() as $s) {
            [$nom, $nomNl, $departements] = $s;

            $trouveSecteur->execute([$nom]);
            $secteurId = (int) $trouveSecteur->fetchColumn();

            if ($secteurId === 0) {
                $insSecteur->execute([$nom, $nomNl, $ordreSecteur]);
                $secteurId = (int) $db->lastInsertId();
            } else {
                // L'ordre, lui, est réaligné : c'est un détail d'affichage, pas
                // une donnée que quelqu'un aurait réglée à la main.
                $majOrdre->execute([$ordreSecteur, $secteurId]);
            }

            $ordreDep = 10;
            foreach ($departements as $dep) {
                $trouveDep->execute([$secteurId, $dep]);
                if ((int) $trouveDep->fetchColumn() === 0) {
                    $insDep->execute([$secteurId, $dep, $ordreDep]);
                }
                $ordreDep += 10;
            }

            $ordreSecteur += 10;
        }

        $fait = true;
        return true;
    }
}

if (!function_exists('famiSecteurs')) {
    /**
     * id => nom des secteurs actifs, dans l'ordre.
     * Table absente = tableau vide : aucun écran ne casse, la colonne s'affiche
     * simplement vide le temps qu'un administrateur ouvre la page qui la crée.
     */
    function famiSecteurs(PDO $db, $inclureInactifs = false)
    {
        $sql = "SELECT id, nom FROM famicard_secteurs";
        if (!$inclureInactifs) {
            $sql .= " WHERE actif = 1";
        }
        $sql .= " ORDER BY ordre ASC, nom ASC";

        $liste = [];
        try {
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $liste[(int) $s['id']] = (string) $s['nom'];
            }
        } catch (Exception $e) {
            // tables pas encore créées
        }
        return $liste;
    }
}

if (!function_exists('famiDepartements')) {
    /**
     * Les départements actifs, groupés par secteur :
     *   [secteur_id => [dep_id => nom, ...], ...]
     *
     * Groupés et non à plat, parce que c'est ainsi qu'ils s'affichent partout —
     * une liste de 53 départements sans leur secteur n'est pas utilisable.
     */
    function famiDepartementsParSecteur(PDO $db, $inclureInactifs = false)
    {
        $sql = "SELECT id, secteur_id, nom FROM famicard_departements";
        if (!$inclureInactifs) {
            $sql .= " WHERE actif = 1";
        }
        $sql .= " ORDER BY secteur_id ASC, ordre ASC, nom ASC";

        $parSecteur = [];
        try {
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $d) {
                $parSecteur[(int) $d['secteur_id']][(int) $d['id']] = (string) $d['nom'];
            }
        } catch (Exception $e) {
            // table pas encore créée
        }
        return $parSecteur;
    }

    /** id => nom de TOUS les départements, à plat (pour traduire un id en libellé). */
    function famiDepartements(PDO $db, $inclureInactifs = false)
    {
        $liste = [];
        foreach (famiDepartementsParSecteur($db, $inclureInactifs) as $deps) {
            foreach ($deps as $id => $nom) {
                $liste[$id] = $nom;
            }
        }
        return $liste;
    }
}

if (!function_exists('famiAffecteSecteur')) {
    /**
     * Rattache un collaborateur, ou le détache si le secteur est vide.
     *
     * Le département est facultatif mais CONTRÔLÉ : s'il est fourni, il doit
     * appartenir au secteur donné. Sans ce test, on pourrait enregistrer
     * « secteur Bureau, département Barbecue » — une ligne que plus aucun
     * écran ne saurait afficher correctement.
     */
    function famiAffecteSecteur(PDO $db, $userId, $secteurId, $departementId = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        if (empty($secteurId)) {
            $db->prepare("DELETE FROM famicard_affectations WHERE user_id = ?")->execute([$userId]);
            return true;
        }

        $secteurId = (int) $secteurId;
        $departementId = empty($departementId) ? null : (int) $departementId;

        if ($departementId !== null) {
            $ok = $db->prepare("SELECT COUNT(*) FROM famicard_departements WHERE id = ? AND secteur_id = ?");
            $ok->execute([$departementId, $secteurId]);
            if ((int) $ok->fetchColumn() === 0) {
                return false;
            }
        }

        // Le doublon de clé primaire est le cas NORMAL (changement
        // d'affectation), pas une erreur : on écrase.
        $st = $db->prepare(
            "INSERT INTO famicard_affectations (user_id, secteur_id, departement_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE secteur_id = VALUES(secteur_id), departement_id = VALUES(departement_id)"
        );
        $st->execute([$userId, $secteurId, $departementId]);
        return true;
    }
}

if (!function_exists('famiOublieAffectation')) {
    /**
     * À appeler quand un compte est supprimé. `famicard_affectations` n'a pas de
     * clé étrangère vers `utilisateurs` (aucune table du projet n'en pose), donc
     * rien ne nettoie tout seul de ce côté : sans cet appel, la ligne survit au
     * collaborateur et un futur compte réutilisant le même id hériterait de son
     * secteur.
     */
    function famiOublieAffectation(PDO $db, $userId)
    {
        try {
            $db->prepare("DELETE FROM famicard_affectations WHERE user_id = ?")->execute([(int) $userId]);
        } catch (Exception $e) {
            // table absente : rien à oublier
        }
    }
}
