<?php
// ============================================================
// FAMICARD — ÉCRITURE DES FICHES ET SUIVI DES MODIFICATIONS.
//
// LE CHOIX, pris avec Jimmy : le collaborateur modifie ses informations
// IMMÉDIATEMENT, et l'administrateur voit ensuite ce qui a changé pour
// confirmer ou rétablir.
//
// L'alternative envisagée était un circuit en quatre temps — demande avec
// motif, autorisation, modification, validation. Elle a été écartée pour une
// raison simple : deux allers-retours administratifs pour corriger une adresse,
// c'est une fiche que personne ne corrige. Ici, la donnée est juste tout de
// suite, et le contrôle existe quand même — il vient après au lieu d'avant.
//
// Une modification faite PAR L'ADMIN n'a rien à valider : elle est tracée
// directement comme validée. On garde la trace quand même, parce que « qui a
// changé ce champ, et quand » est la première question posée devant une donnée
// surprenante.
// ============================================================

if (!function_exists('famicardAssureModifications')) {
    /**
     * Crée la table de suivi.
     *
     * Appelée depuis les écrans d'ÉCRITURE (modifier.php) et de validation, pas
     * depuis les pages de consultation : ce n'est pas un chemin chaud, on y
     * passe rarement et volontairement. La règle du dépôt vise les pages vues
     * par tout le monde à chaque affichage, ce qui n'est pas le cas ici.
     */
    function famicardAssureModifications(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return true;
        }

        // `libelle` est recopié au moment de la modification, exprès : un champ
        // libre supprimé entre-temps laisserait sinon une ligne illisible
        // (« libre_7 a changé »), impossible à valider en connaissance de cause.
        $db->exec(
            "CREATE TABLE IF NOT EXISTS famicard_modifications (
                id INT NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                champ VARCHAR(60) NOT NULL,
                libelle VARCHAR(120) NOT NULL,
                avant TEXT NULL,
                apres TEXT NULL,
                par_user_id INT NOT NULL,
                fait_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                statut VARCHAR(20) NOT NULL DEFAULT 'a_valider',
                decide_par INT NULL,
                decide_le DATETIME NULL,
                PRIMARY KEY (id),
                INDEX idx_famicard_modif_statut (statut),
                INDEX idx_famicard_modif_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $fait = true;
        return true;
    }
}

if (!function_exists('famicardEcritValeur')) {
    /**
     * Écrit un champ, qu'il soit du socle (colonne de `utilisateurs`) ou libre
     * (table de valeurs). Un seul point d'écriture, utilisé aussi bien par
     * l'écran d'édition que par le rétablissement d'une modification refusée —
     * sinon les deux chemins finiraient par ne plus écrire de la même façon.
     *
     * ⚠️ Ne contrôle NI les droits NI le format : c'est fait en amont par
     * famicardPeutModifier() et la validation de saisie. Cette fonction écrit.
     */
    function famicardEcritValeur(PDO $db, $userId, array $champ, $valeur)
    {
        $userId = (int) $userId;

        // ⚠️ LE RATTACHEMENT NE PASSE PAS PAR ICI. Ses « colonnes »
        // (secteur_nom, departement_nom, placement_nom) sont des PSEUDO-colonnes :
        // elles n'existent pas dans `utilisateurs`, elles sont posées dans la
        // ligne par famicardAjouteRattachement(). Sans ce refus, un chemin
        // d'écriture générique — le « rétablir » de validations.php, par
        // exemple — lancerait un UPDATE sur une colonne inexistante et
        // tomberait sur une erreur SQL brute. Il s'écrit avec
        // famicardEcritRattachementRh() (includes/rattachement.php).
        if (($champ['saisie'] ?? '') === 'rattachement') {
            return false;
        }

        // ⚠️ MÊME RAISON POUR LES CHAMPS D'AGENCE. Ils vivent dans
        // `interim_agences`, pas dans `utilisateurs` : un UPDATE générique
        // chercherait une colonne « agence_contact » qui n'existe pas.
        // Ils s'écrivent avec famicardEcritChampAgence() (includes/agence.php).
        if (($champ['saisie'] ?? '') === 'agence') {
            return false;
        }

        // Champ libre : sa valeur ne vit pas dans `utilisateurs`.
        if (!empty($champ['champ_id'])) {
            $champId = (int) $champ['champ_id'];
            if ($valeur === null || $valeur === '') {
                // Vider, c'est effacer la ligne : garder une chaîne vide
                // laisserait croire à une réponse donnée.
                $db->prepare("DELETE FROM famicard_valeurs WHERE user_id = ? AND champ_id = ?")
                   ->execute([$userId, $champId]);
                return true;
            }
            $db->prepare(
                "INSERT INTO famicard_valeurs (user_id, champ_id, valeur) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
            )->execute([$userId, $champId, (string) $valeur]);
            return true;
        }

        $colonne = (string) ($champ['colonne'] ?? '');
        // Le nom de colonne vient du modèle, jamais d'une requête ; ce test est
        // une ceinture de sécurité au cas où le modèle évoluerait mal.
        if ($colonne === '' || !preg_match('~^[a-z_]+$~', $colonne)) {
            return false;
        }

        $db->prepare("UPDATE utilisateurs SET `$colonne` = ? WHERE id = ?")
           ->execute([($valeur === '' ? null : $valeur), $userId]);
        return true;
    }
}

// ⚠️ AUCUNE FONCTION ICI N'ÉCRIT DANS `student_department_links`, ET C'EST
// VOLONTAIRE. Cette table appartient à FamiJob : elle dit où le PLANNING peut
// placer quelqu'un, avec un ordre de préférence. Famicard a longtemps voulu
// y écrire « le » département d'une personne — c'est une autre question, elle a
// désormais sa table (`famicard_rattachement`, voir includes/rattachement.php).
// Une fonction d'écriture vers la planification a existé ici ; elle a été
// retirée plutôt que laissée inutilisée, parce que son nom ressemblait à s'y
// méprendre à celui du rattachement RH.

if (!function_exists('famicardTraceModification')) {
    /**
     * Enregistre un changement.
     *
     * @param bool $aValider false quand c'est l'admin qui modifie : il n'a
     *                       personne à qui demander confirmation.
     */
    function famicardTraceModification(PDO $db, $userId, $cle, array $champ, $avant, $apres, $parUserId, $aValider)
    {
        try {
            $db->prepare(
                "INSERT INTO famicard_modifications
                    (user_id, champ, libelle, avant, apres, par_user_id, statut)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                (int) $userId,
                (string) $cle,
                (string) ($champ['libelle'] ?? $cle),
                ($avant === '' ? null : $avant),
                ($apres === '' ? null : $apres),
                (int) $parUserId,
                $aValider ? 'a_valider' : 'valide',
            ]);
        } catch (Exception $e) {
            // La trace ne doit jamais empêcher la modification elle-même :
            // le collaborateur a corrigé sa donnée, c'est le principal.
        }
    }
}

if (!function_exists('famicardModificationsEnAttente')) {
    /** Les modifications que l'administrateur n'a pas encore tranchées. */
    function famicardModificationsEnAttente(PDO $db, $limite = 100)
    {
        try {
            $st = $db->prepare(
                "SELECT m.*, u.nom, u.prenom, u.identifiant
                 FROM famicard_modifications m
                 LEFT JOIN utilisateurs u ON u.id = m.user_id
                 WHERE m.statut = 'a_valider'
                 ORDER BY m.fait_le DESC
                 LIMIT " . (int) $limite
            );
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return []; // table pas encore créée
        }
    }
}

if (!function_exists('famicardModificationsParPersonne')) {
    /**
     * LES MODIFICATIONS EN ATTENTE, REGROUPÉES PAR PERSONNE.
     *
     * ⚠️ UNE LIGNE PAR PERSONNE, PAS PAR CHAMP (décision de Jimmy). Quelqu'un
     * qui corrige quatre informations produisait quatre lignes : on tranchait
     * son adresse sans voir qu'il avait aussi changé de secteur, et la liste
     * donnait l'impression d'un raz-de-marée là où il n'y avait que deux
     * personnes consciencieuses.
     *
     * @return array [user_id => ['personne' => …, 'modifs' => […], 'dernier' => …]]
     */
    function famicardModificationsParPersonne(PDO $db, $limite = 400)
    {
        $lignes = famicardModificationsEnAttente($db, $limite);

        $par = [];
        foreach ($lignes as $m) {
            $uid = (int) $m['user_id'];
            if (!isset($par[$uid])) {
                $nom = trim(((string) ($m['prenom'] ?? '')) . ' ' . ((string) ($m['nom'] ?? '')));
                $par[$uid] = [
                    'user_id'  => $uid,
                    'personne' => $nom !== '' ? $nom : (string) ($m['identifiant'] ?? ('#' . $uid)),
                    'modifs'   => [],
                    // La plus RÉCENTE : c'est elle qui dit depuis quand ça
                    // attend. La liste est déjà triée du plus récent au plus
                    // ancien, donc la première rencontrée est la bonne.
                    'dernier'  => (string) $m['fait_le'],
                ];
            }
            $par[$uid]['modifs'][] = $m;
        }

        return $par;
    }
}

if (!function_exists('famicardModificationsEnAttentePour')) {
    /**
     * Ce qui attend une décision SUR UNE FICHE, indexé par champ.
     *
     * Sert à deux choses : la vue détaillée (qui met en évidence ce qui a
     * changé), et l'écran d'édition — un champ dont la correction n'est pas
     * encore tranchée s'y affiche d'une autre couleur, pour qu'on ne le
     * recorrige pas en croyant que rien n'est parti.
     *
     * @return array [clé du champ => ligne de famicard_modifications]
     */
    function famicardModificationsEnAttentePour(PDO $db, $userId)
    {
        $par = [];
        try {
            $st = $db->prepare(
                "SELECT * FROM famicard_modifications
                  WHERE user_id = ? AND statut = 'a_valider'
                  ORDER BY fait_le ASC"
            );
            $st->execute([(int) $userId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
                // Deux corrections du même champ : on garde la DERNIÈRE, c'est
                // elle qui est dans la fiche. Mais le « avant » doit rester
                // celui de la PREMIÈRE, sinon rétablir ne remonterait que d'un
                // cran et laisserait une valeur intermédiaire que personne n'a
                // jamais validée.
                $cle = (string) $m['champ'];
                if (isset($par[$cle])) {
                    $m['avant'] = $par[$cle]['avant'];
                }
                $par[$cle] = $m;
            }
        } catch (Exception $e) {
            return [];
        }
        return $par;
    }
}

if (!function_exists('famicardIdsEnAttente')) {
    /**
     * QUI attend une décision, en UNE requête : [user_id => nombre de champs].
     *
     * Sert à colorer une ligne dans une liste — la base des collaborateurs, les
     * agences — sans poser une requête par ligne. Sur 400 collaborateurs, la
     * différence n'est pas theorique.
     *
     * @param string $population 'tous' | 'collaborateurs' | 'agences'
     */
    function famicardIdsEnAttente(PDO $db, $population = 'tous')
    {
        $filtre = '';
        if ($population === 'agences') {
            $filtre = " AND u.role = 'agence_interim'";
        } elseif ($population === 'collaborateurs') {
            $filtre = " AND u.role <> 'agence_interim'";
        }

        $par = [];
        try {
            $sql = "SELECT m.user_id, COUNT(*) AS n
                      FROM famicard_modifications m
                      JOIN utilisateurs u ON u.id = m.user_id
                     WHERE m.statut = 'a_valider'" . $filtre . "
                     GROUP BY m.user_id";
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $par[(int) $l['user_id']] = (int) $l['n'];
            }
        } catch (Exception $e) {
            return [];
        }
        return $par;
    }
}

if (!function_exists('famicardComptePersonnesEnAttente')) {
    /**
     * Combien de PERSONNES attendent une decision — pas combien de champs.
     *
     * SEPARABLE PAR POPULATION (decision de Jimmy). Une agence qui corrige son
     * contact n'a rien a faire dans le compteur des collaborateurs : on
     * cherchait la correction dans la base des fiches, elle n'y etait pas —
     * puisque cet ecran exclut justement les comptes d'agence.
     */
    function famicardComptePersonnesEnAttente(PDO $db, $population = 'tous')
    {
        return count(famicardIdsEnAttente($db, $population));
    }
}

if (!function_exists('famicardCompteModificationsEnAttente')) {
    /** Combien de modifications attendent une décision. 0 si la table manque. */
    function famicardCompteModificationsEnAttente(PDO $db)
    {
        try {
            return (int) $db->query(
                "SELECT COUNT(*) FROM famicard_modifications WHERE statut = 'a_valider'"
            )->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('famicardTrancheModification')) {
    /**
     * L'administrateur confirme ou rétablit.
     *
     * Rétablir REMET l'ancienne valeur : c'est tout l'intérêt d'avoir gardé
     * « avant ». Sans ça, refuser une modification ne ferait que la marquer
     * refusée en laissant la nouvelle valeur en place — le pire des deux
     * mondes, une fiche fausse et un registre qui prétend le contraire.
     *
     * @param string $decision 'valide' ou 'retabli'
     */
    function famicardTrancheModification(PDO $db, $modifId, $adminId, $decision)
    {
        if (!in_array($decision, ['valide', 'retabli'], true)) {
            return false;
        }

        $st = $db->prepare("SELECT * FROM famicard_modifications WHERE id = ? AND statut = 'a_valider'");
        $st->execute([(int) $modifId]);
        $modif = $st->fetch(PDO::FETCH_ASSOC);
        if (!$modif) {
            return false; // déjà tranchée, ou inexistante
        }

        if ($decision === 'retabli') {
            $cle = (string) $modif['champ'];

            // ⚠️ LE RATTACHEMENT NE SE RÉTABLIT PAS COMME UNE COLONNE. Il vit
            // dans `famicard_rattachement`, et le registre n'en garde que le
            // LIBELLÉ (« Décoration > Bougies ») : il faut le retraduire en
            // identifiants. Si le libellé ne correspond plus à rien — secteur
            // renommé, département supprimé — on REFUSE de trancher plutôt que
            // de marquer la ligne réglée sans rien remettre en place.
            // Un champ d'agence se rétablit dans `interim_agences`, en
            // retrouvant l'agence par le nom inscrit sur le compte.
            if (function_exists('famicardColonneAgence') && famicardColonneAgence($cle) !== '') {
                $q = $db->prepare('SELECT interim FROM utilisateurs WHERE id = ? LIMIT 1');
                $q->execute([(int) $modif['user_id']]);
                famicardEcritChampAgence($db, (string) $q->fetchColumn(), $cle, (string) $modif['avant']);
            } elseif ($cle === 'departement' || $cle === 'secteur') {
                if (!function_exists('famicardRetrouveRattachementParNom')) {
                    return false;
                }
                $vise = famicardRetrouveRattachementParNom($db, (string) $modif['avant']);
                if ($vise === null) {
                    return false;
                }
                famicardEcritRattachementRh(
                    $db, (int) $modif['user_id'], $vise['secteur'], $vise['departement'], (int) $adminId
                );
            } else {
                $champs = famicardChamps($db);
                if (isset($champs[$cle])) {
                    famicardEcritValeur($db, (int) $modif['user_id'], $champs[$cle], $modif['avant']);
                }
            }
        }

        $db->prepare(
            "UPDATE famicard_modifications
             SET statut = ?, decide_par = ?, decide_le = NOW()
             WHERE id = ?"
        )->execute([$decision, (int) $adminId, (int) $modifId]);

        return true;
    }
}
