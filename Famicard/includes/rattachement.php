<?php
// ============================================================
// FAMICARD — LE RATTACHEMENT RH : DE QUOI LA PERSONNE RELÈVE.
//
// ⚠️ À NE PAS CONFONDRE AVEC `student_department_links`, ET C'EST TOUT L'OBJET
// DE CE FICHIER. Les deux tables relient une personne à des départements, mais
// elles ne répondent PAS à la même question :
//
//   student_department_links  →  « OÙ CET ÉTUDIANT PEUT-IL ÊTRE PLACÉ ? »
//        Table de FamiJob, outil de PLANIFICATION. Plusieurs départements,
//        classés par `priority_rank` : c'est un vivier de candidats, l'ordre
//        dit la préférence de placement.
//
//   famicard_rattachement     →  « DE QUOI CETTE PERSONNE RELÈVE-T-ELLE ? »
//        Table de Famicard, outil RH. Une seule réponse par personne : son
//        secteur, et son département quand il est plus précis que le secteur.
//
// Un étudiant qui peut travailler dans trois rayons NE RELÈVE PAS de trois
// départements — il peut y être placé. Ce sont deux faits différents sur la
// même personne, et les mélanger fabrique des données fausses dans les deux
// sens : un teamcoach écrit dans la table de planification ressemble à un
// candidat à planifier, et un étudiant lu depuis la planification paraît
// appartenir à trois rayons.
//
// LE RÉFÉRENTIEL, LUI, RESTE UNIQUE : `sectors` et `departments`, ceux du dépôt
// live. C'est ça que le README protège — Famicard avait déjà créé ses propres
// tables de secteurs, abandonnées. Deux LIENS de sens différent vers UN SEUL
// référentiel, ce n'est pas la même erreur : c'est la façon d'éviter la
// première.
//
// ── LA FORME : SECTEUR, ET DÉPARTEMENT FACULTATIF ───────────────────────────
// Décision de Jimmy, et elle vient d'un cas réel : un teamcoach Décoration
// couvre un SECTEUR entier (15 départements). Lui demander de cocher ses 15
// rayons serait faux dès le rayon suivant. Un employé de caisse, lui, relève
// d'un département précis. Une seule ligne répond aux deux :
//
//   département renseigné  →  son périmètre est CE département
//   département vide       →  son périmètre est TOUT le secteur
//
// ── À QUOI ÇA SERVIRA ───────────────────────────────────────────────────────
// À restreindre ce qu'une personne voit, sans toucher à ce qu'elle a le droit
// de faire : « un teamcoach Décoration ne voit pas les horaires de la caisse ».
// Le couple est `role` + périmètre — le rôle dit CE QU'ON PEUT FAIRE, le
// rattachement dit SUR QUOI. Un teamcoach reste un teamcoach, il en voit
// simplement moins.
//
// ⚠️ CE FICHIER NE RESTREINT RIEN AUJOURD'HUI. Il enregistre le périmètre et
// sait le lire (famicardPerimetreRh). Brancher un filtrage est une décision de
// l'écran concerné, pas d'ici — et un filtrage posé avant que les fiches soient
// renseignées viderait les écrans de tout le monde.
// ============================================================

if (!function_exists('famicardAssureRattachementRh')) {
    /**
     * Crée la table, et reprend ce qui est déductible — une seule fois.
     *
     * ⚠️ À N'APPELER QUE depuis une page d'administration : c'est de la DDL.
     *
     * PAS DE CLÉ ÉTRANGÈRE vers `sectors` / `departments`, volontairement. Ces
     * tables appartiennent au dépôt live, et son installateur de secteurs
     * supprime des départements en nettoyant lui-même leurs liens. Une
     * contrainte posée d'ici ferait échouer SON ménage, pour une table qu'il ne
     * connaît pas. Un identifiant devenu orphelin est donc possible, et il est
     * traité à la LECTURE (voir famicardRattachementsRh) : un département
     * disparu retombe sur le secteur, qui reste vrai.
     */
    function famicardAssureRattachementRh(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return true;
        }

        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS famicard_rattachement (
                    user_id INT NOT NULL,
                    secteur_id INT NOT NULL,
                    departement_id INT NULL,
                    maj_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    maj_par INT NULL,
                    PRIMARY KEY (user_id),
                    INDEX idx_famicard_ratt_secteur (secteur_id),
                    INDEX idx_famicard_ratt_departement (departement_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            // ── REPRISE ──────────────────────────────────────────────────
            // Ce que FamiJob sait déjà : pour qui a des départements de
            // placement, le PRINCIPAL (priorité la plus haute) est aussi, en
            // pratique, celui dont il relève. On ne recopie que celui-là — les
            // suivants sont des possibilités de placement, pas des
            // appartenances, et les recopier ferait exactement la confusion que
            // ce fichier existe pour éviter.
            //
            // Personne d'autre n'est deviné : ni le rôle ni le lieu de travail
            // ne disent de quel rayon quelqu'un relève.
            $db->exec(
                "INSERT IGNORE INTO famicard_rattachement (user_id, secteur_id, departement_id)
                 SELECT l.student_id, d.sector_id, l.department_id
                   FROM student_department_links l
                   JOIN departments d ON d.id = l.department_id
                  WHERE d.sector_id IS NOT NULL
                    AND l.priority_rank = (
                        SELECT MIN(l2.priority_rank)
                          FROM student_department_links l2
                         WHERE l2.student_id = l.student_id
                    )
                  GROUP BY l.student_id"
            );
        } catch (Exception $e) {
            // Droits insuffisants, ou tables du matching absentes : les écrans
            // qui lisent le rattachement le trouvent simplement vide.
            return false;
        }

        $fait = true;
        return true;
    }
}

if (!function_exists('famicardArbreSecteurs')) {
    /**
     * Le référentiel prêt pour deux listes en cascade :
     *   [secteur_id => ['nom' => …, 'departements' => [dep_id => nom]]]
     *
     * Vient de secteursListe() — celui du dépôt LIVE, jamais d'une copie. Les
     * départements « à ranger » (sans secteur) n'y figurent pas : on ne peut
     * pas relever d'un rayon qui n'appartient à aucun secteur, et les proposer
     * inviterait à ranger quelqu'un là où l'organisation, elle, n'a rien rangé.
     */
    function famicardArbreSecteurs(PDO $db)
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        if (!function_exists('secteursListe')) {
            return $cache;
        }
        try {
            foreach (secteursListe($db) as $s) {
                $departements = [];
                foreach ($s['departements'] as $d) {
                    $departements[(int) $d['id']] = (string) $d['nom'];
                }
                $cache[(int) $s['id']] = ['nom' => (string) $s['nom'], 'departements' => $departements];
            }
        } catch (Exception $e) {
            $cache = [];
        }
        return $cache;
    }
}

if (!function_exists('famicardRattachementsRh')) {
    /**
     * Le rattachement RH de plusieurs personnes, en UNE requête :
     *   [user_id => ['secteur_id','secteur_nom','departement_id','departement_nom']]
     *
     * Une requête par ligne serait invisible sur une fiche et catastrophique
     * sur la base des collaborateurs, qui en affiche des centaines.
     *
     * ⚠️ LEFT JOIN sur `departments`, pas INNER : un département supprimé
     * depuis (le référentiel vit sa vie côté live) ne doit pas faire disparaître
     * le rattachement ENTIER. Le secteur, lui, reste vrai — on retombe donc
     * proprement sur « tout le secteur » plutôt que sur « aucun rattachement ».
     */
    function famicardRattachementsRh(PDO $db, array $userIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) {
            return [];
        }
        // Des entiers castés, jamais du texte : rien à échapper ici.
        $in = implode(',', $ids);

        $res = [];
        try {
            $sql = "SELECT r.user_id, r.secteur_id, r.departement_id,
                           s.sector_name AS secteur_nom,
                           d.department_name AS departement_nom
                      FROM famicard_rattachement r
                      LEFT JOIN sectors s ON s.id = r.secteur_id
                      LEFT JOIN departments d ON d.id = r.departement_id
                     WHERE r.user_id IN ($in)";
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $res[(int) $r['user_id']] = [
                    'secteur_id'      => (int) $r['secteur_id'],
                    'secteur_nom'     => (string) ($r['secteur_nom'] ?? ''),
                    'departement_id'  => $r['departement_id'] !== null ? (int) $r['departement_id'] : null,
                    'departement_nom' => (string) ($r['departement_nom'] ?? ''),
                ];
            }
        } catch (Exception $e) {
            // Table pas encore créée : aucune fiche ne casse, elles sont
            // simplement sans rattachement.
            return [];
        }

        return $res;
    }
}

if (!function_exists('famicardEcritRattachementRh')) {
    /**
     * Pose (ou retire) le rattachement d'une personne.
     *
     * Secteur vide = plus de rattachement du tout : la ligne est SUPPRIMÉE,
     * plutôt que gardée avec des colonnes nulles. Une ligne vide et pas de
     * ligne se liraient pareil, et il faudrait deviner laquelle veut dire quoi.
     *
     * ⚠️ Ne contrôle pas les droits (famicardPeutModifier s'en charge en
     * amont), mais VÉRIFIE la cohérence : un département doit appartenir au
     * secteur choisi. Sans ce test, « Décoration > Caisse » serait
     * enregistrable, et le périmètre calculé plus tard n'aurait aucun sens.
     *
     * @return bool false si le couple est incohérent ou l'écriture impossible.
     */
    function famicardEcritRattachementRh(PDO $db, $userId, $secteurId, $departementId, $parUserId = null)
    {
        $userId = (int) $userId;
        $secteurId = (int) $secteurId;
        $departementId = (int) $departementId;
        if ($userId <= 0) {
            return false;
        }

        try {
            if ($secteurId <= 0) {
                $db->prepare("DELETE FROM famicard_rattachement WHERE user_id = ?")->execute([$userId]);
                return true;
            }

            $arbre = famicardArbreSecteurs($db);
            if (!isset($arbre[$secteurId])) {
                return false;
            }
            if ($departementId > 0 && !isset($arbre[$secteurId]['departements'][$departementId])) {
                return false; // le département n'est pas dans ce secteur
            }

            $db->prepare(
                "INSERT INTO famicard_rattachement (user_id, secteur_id, departement_id, maj_par)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE secteur_id = VALUES(secteur_id),
                                         departement_id = VALUES(departement_id),
                                         maj_par = VALUES(maj_par)"
            )->execute([
                $userId,
                $secteurId,
                $departementId > 0 ? $departementId : null,
                $parUserId !== null ? (int) $parUserId : null,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('famicardRattachementResume')) {
    /** « Décoration > Bougies », ou « Décoration » quand il couvre le secteur. */
    function famicardRattachementResume(array $r)
    {
        $secteur = trim((string) ($r['secteur_nom'] ?? ''));
        if ($secteur === '') {
            return '';
        }
        $departement = trim((string) ($r['departement_nom'] ?? ''));
        return $departement !== '' ? ($secteur . ' > ' . $departement) : $secteur;
    }
}

if (!function_exists('famicardPerimetreRh')) {
    /**
     * ⭐ LE POINT D'ENTRÉE DU FILTRAGE À VENIR : « que cette personne a-t-elle
     * le droit de VOIR ? », exprimé en identifiants de départements.
     *
     *   département renseigné → [ce département]
     *   département vide      → TOUS les départements de son secteur
     *   aucun rattachement    → null
     *
     * ⚠️ `null` N'EST PAS UN TABLEAU VIDE, et la différence est tout sauf un
     * détail. Vide voudrait dire « ne voit rien » ; null veut dire « aucun
     * périmètre enregistré ». Un écran qui confondrait les deux viderait
     * l'affichage de tous ceux dont la fiche n'est pas encore renseignée — le
     * jour de la mise en service, c'est-à-dire tout le monde.
     *
     * @return array|null identifiants de départements, ou null
     */
    function famicardPerimetreRh(PDO $db, $userId)
    {
        $r = famicardRattachementsRh($db, [(int) $userId]);
        $r = $r[(int) $userId] ?? null;
        if (!$r || empty($r['secteur_id'])) {
            return null;
        }

        if (!empty($r['departement_id'])) {
            return [(int) $r['departement_id']];
        }

        $arbre = famicardArbreSecteurs($db);
        $secteur = $arbre[(int) $r['secteur_id']] ?? null;
        if (!$secteur) {
            return null; // secteur disparu du référentiel : pas de périmètre sûr
        }
        return array_map('intval', array_keys($secteur['departements']));
    }
}

if (!function_exists('famicardCompteRattachementsManquants')) {
    /**
     * Combien de collaborateurs ACTIFS n'ont pas encore de rattachement.
     *
     * Les comptes inactifs et les comptes d'agence sont hors sujet : les
     * compter rendrait le compteur impossible à ramener à zéro, donc inutile.
     */
    function famicardCompteRattachementsManquants(PDO $db)
    {
        try {
            return (int) $db->query(
                "SELECT COUNT(*) FROM utilisateurs u
                  WHERE NOT EXISTS (SELECT 1 FROM famicard_rattachement r WHERE r.user_id = u.id)
                    AND (u.statut IS NULL OR u.statut <> 'inactif')
                    AND u.role <> 'agence_interim'"
            )->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}
