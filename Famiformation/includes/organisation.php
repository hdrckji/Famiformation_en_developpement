<?php
// ============================================================
// L'ORGANISATION DE L'ENTREPRISE — SECTEURS.
//
// VOCABULAIRE (celui du client, à ne pas improviser) :
//   • SECTEUR — les 8 grands ensembles ci-dessous. C'est le niveau auquel on
//     rattache un collaborateur aujourd'hui.
//   • DÉPARTEMENT — l'échelon en dessous, à venir. Il n'existe pas encore : sa
//     table n'est donc pas créée. On ne pose pas une table vide « pour plus tard ».
//
// ⚠️ ATTENTION AU MOT « DÉPARTEMENT », qui désignera bientôt DEUX choses
// différentes dans cette base :
//   • `famicard_departements` (à venir) — l'échelon sous le secteur, ici ;
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

if (!function_exists('famiSecteursParDefaut')) {
    /**
     * Les 8 secteurs, dans l'ordre d'affichage voulu.
     *
     * Ils ne sont PAS écrits en dur dans les écrans : ils servent uniquement à
     * garnir la table la première fois. Ensuite, c'est la base qui fait foi —
     * un secteur renommé ou désactivé le reste.
     *
     * Les traductions NL sont une proposition de départ, à faire confirmer.
     */
    function famiSecteursParDefaut()
    {
        return [
            ['Plantes intérieures', 'Kamerplanten'],
            ['Plantes extérieures', 'Tuinplanten'],
            ['Décoration',          'Decoratie'],
            ['Famizoo',             'Famizoo'],
            ['Food/Accueil/Caisse', 'Food/Onthaal/Kassa'],
            ['Famigarden',          'Famigarden'],
            ['Bureau',              'Kantoor'],
            ['Logistique',          'Logistiek'],
        ];
    }
}

if (!function_exists('famiAssureSecteurs')) {
    /**
     * Crée les deux tables si elles manquent, et garnit les secteurs la
     * PREMIÈRE fois seulement.
     *
     * ⚠️ À N'APPELER QUE depuis une page d'administration. Le site a déjà fait
     * le ménage une fois pour retirer la DDL du chemin chaud : pas de
     * CREATE TABLE à chaque affichage de page.
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

        // Le rattachement d'un collaborateur. Clé primaire sur user_id : un
        // collaborateur a UN secteur, la structure l'impose plutôt que de faire
        // confiance aux écrans. Le jour où il en faudra plusieurs, c'est un
        // choix explicite à poser ici, pas un doublon qui s'installe.
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

        // Garnissage initial. Le test porte sur « la table est vide », pas sur
        // « la table vient d'être créée » : si un administrateur supprime tout,
        // on ne lui réimpose pas la liste au rechargement suivant... mais on ne
        // la laisse pas vide au premier démarrage non plus.
        $dejaLa = (int) $db->query("SELECT COUNT(*) FROM famicard_secteurs")->fetchColumn();
        if ($dejaLa === 0) {
            $ins = $db->prepare("INSERT INTO famicard_secteurs (nom, nom_nl, ordre) VALUES (?, ?, ?)");
            $ordre = 10;
            foreach (famiSecteursParDefaut() as $s) {
                $ins->execute([$s[0], $s[1], $ordre]);
                $ordre += 10;
            }
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

if (!function_exists('famiAffecteSecteur')) {
    /**
     * Rattache un collaborateur à un secteur, ou le détache si l'id est vide.
     * Un seul appel pour les deux cas : les écrans n'ont pas à savoir s'il
     * s'agit d'une première affectation ou d'un changement.
     */
    function famiAffecteSecteur(PDO $db, $userId, $secteurId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        if (empty($secteurId)) {
            $db->prepare("DELETE FROM famicard_affectations WHERE user_id = ?")->execute([$userId]);
            return true;
        }

        // Le doublon de clé primaire est le cas NORMAL (changement de secteur),
        // pas une erreur : on écrase.
        $st = $db->prepare(
            "INSERT INTO famicard_affectations (user_id, secteur_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE secteur_id = VALUES(secteur_id)"
        );
        $st->execute([$userId, (int) $secteurId]);
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
