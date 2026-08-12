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
        // (departement_nom, secteur_nom) sont des PSEUDO-colonnes : elles
        // n'existent pas dans `utilisateurs`, elles sont posées dans la ligne
        // par famicardAjouteRattachement(). Sans ce refus, un chemin d'écriture
        // générique — le « rétablir » de validations.php, par exemple —
        // lancerait un UPDATE sur une colonne inexistante et tomberait sur une
        // erreur SQL brute. Il s'écrit avec famicardEcritRattachement().
        if (($champ['saisie'] ?? '') === 'rattachement') {
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

if (!function_exists('famicardEcritRattachement')) {
    /**
     * Réécrit le rattachement d'un collaborateur : la LISTE COMPLÈTE, ordonnée.
     *
     * ⚠️ C'EST TOUTE LA DIFFICULTÉ DE CETTE TABLE. `student_department_links`
     * porte PLUSIEURS départements par personne, classés par `priority_rank`,
     * et le matching intérim de FamiJob s'en sert pour proposer des
     * remplaçants. Un écran qui n'en connaîtrait qu'un et écrirait « le »
     * département effacerait les autres sans que personne ne s'en aperçoive
     * avant d'aller chercher quelqu'un. C'est pour ça que le rattachement est
     * resté en lecture seule si longtemps.
     *
     * La sortie de ce piège n'est pas de compter les cas : c'est que
     * l'appelant envoie TOUJOURS la liste entière. On écrit alors un ÉTAT
     * FINAL, jamais une différence — comme famicardDefinitAcces() pour les
     * services. Rien ne peut disparaître par omission, puisque rien n'est
     * déduit de ce qui était là avant.
     *
     * L'ORDRE PORTE LA PRIORITÉ : premier = rang 1. Un numéro saisi à part
     * finirait par ne plus correspondre à la liste affichée, et deux
     * départements se retrouveraient au même rang.
     *
     * ⚠️ Ne contrôle NI les droits NI l'existence des départements : c'est fait
     * en amont (famicardPeutModifier, et la liste réellement proposée).
     *
     * @param array $departementIds identifiants, dans l'ordre de priorité
     * @return bool false si la table est absente — le reste de la fiche
     *              s'enregistre quand même, on ne perd pas la saisie.
     */
    function famicardEcritRattachement(PDO $db, $userId, array $departementIds)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        // Dédoublonnage en gardant la PREMIÈRE occurrence : deux fois le même
        // département, c'est la priorité la plus haute qui compte. La clé
        // unique (student_id, department_id) le refuserait de toute façon, mais
        // le rang serait déjà faussé.
        $ids = [];
        foreach ($departementIds as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        try {
            if (!$ids) {
                $db->prepare("DELETE FROM student_department_links WHERE student_id = ?")->execute([$userId]);
                return true;
            }

            // Ce qui n'est plus dans la liste s'en va. Fait AVANT les insertions
            // pour ne jamais laisser, même un instant, un rang en double.
            $trous = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare(
                "DELETE FROM student_department_links
                 WHERE student_id = ? AND department_id NOT IN ($trous)"
            )->execute(array_merge([$userId], $ids));

            // ON DUPLICATE KEY plutôt que DELETE + INSERT : un rattachement qui
            // ne change que de rang garde son `created_at`, donc « depuis
            // quand » reste vrai.
            $ins = $db->prepare(
                "INSERT INTO student_department_links (student_id, department_id, priority_rank)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE priority_rank = VALUES(priority_rank)"
            );
            $rang = 1;
            foreach ($ids as $id) {
                $ins->execute([$userId, $id, $rang]);
                $rang++;
            }
            return true;
        } catch (Exception $e) {
            // Table absente (base pas encore à jour côté matching) : la fiche
            // s'enregistre, le rattachement attendra.
            return false;
        }
    }
}

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
            $champs = famicardChamps($db);
            $cle = (string) $modif['champ'];
            if (isset($champs[$cle])) {
                famicardEcritValeur($db, (int) $modif['user_id'], $champs[$cle], $modif['avant']);
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
