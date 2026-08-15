<?php
// ============================================================
// FAMICARD — LES SERVICES ET LES ACCÈS.
//
// « À quels services ce collaborateur a-t-il droit ? » Aujourd'hui deux
// réponses possibles, FamiFormation et FamiJob. Demain d'autres — c'est une
// certitude, pas une hypothèse, et c'est elle qui a dicté toute la structure.
//
// D'OÙ UNE TABLE, PAS UNE LISTE EN DUR. Ajouter un service doit coûter une
// ligne en base, pas une modification dans chaque écran qui affiche des accès.
// La version précédente écrivait FamiJob en dur dans l'accueil, avec sa règle
// « admin ou teamcoach » recopiée à côté : au troisième service, ce genre de
// code se recopie une fois de trop et diverge.
//
// POURQUOI PAS LE RÔLE. `utilisateurs.role` répond à « qui est cette personne »
// (mentor, teamcoach, étudiant), pas à « où a-t-elle le droit d'aller ». Deux
// admins peuvent avoir des accès différents ; un mentor et un teamcoach peuvent
// avoir les mêmes. Confondre les deux, c'est devoir créer un rôle par
// combinaison le jour où il y aura cinq services.
//
// ⚠️ MIGRATION DOUCE. Tant qu'aucun accès explicite n'est enregistré pour
// quelqu'un, on applique les règles historiques (FamiFormation pour tous,
// FamiJob pour admins et teamcoachs). Dès qu'un accès explicite existe, il fait
// foi. Sans ça, la simple création de cette table retirerait FamiJob à tout le
// monde du jour au lendemain.
// ============================================================

if (!function_exists('famicardServicesParDefaut')) {
    /**
     * Les services connus au démarrage. Ils ne servent qu'à garnir la table la
     * première fois : ensuite c'est la base qui fait foi, un service renommé ou
     * désactivé le reste.
     *
     * 'code'  : identifiant stable, utilisé par les règles historiques. Ne pas
     *           le changer une fois posé, contrairement au nom affiché.
     * 'url'   : relative à la RACINE DU SITE (pas à Famicard) — elle passera
     *           par famicardSiteUrl(), qui la rend absolue sur le sous-domaine.
     */
    function famicardServicesParDefaut()
    {
        return [
            [
                'code' => 'famiformation',
                'nom' => 'FamiFormation',
                'description' => 'Formations, quiz, onboarding.',
                'url' => 'index.php',
                'icone' => '🎓',
            ],
            [
                'code' => 'famijob',
                'nom' => 'FamiJob',
                'description' => 'Horaires et matching des étudiants.',
                'url' => 'famijob/index.php',
                'icone' => '🗓️',
            ],
        ];
    }
}

if (!function_exists('famicardAssureServices')) {
    /**
     * Crée les deux tables et garnit les services connus.
     *
     * ⚠️ À N'APPELER QUE depuis une page d'administration : c'est de la DDL.
     * Garnissage idempotent (insérer ce qui manque), pour la même raison que
     * les secteurs : la liste s'allongera, et un test « si la table est vide »
     * ne rattraperait jamais l'écart.
     */
    function famicardAssureServices(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return true;
        }

        $db->exec(
            "CREATE TABLE IF NOT EXISTS famicard_services (
                id INT NOT NULL AUTO_INCREMENT,
                code VARCHAR(40) NOT NULL,
                nom VARCHAR(120) NOT NULL,
                description VARCHAR(255) NULL,
                url VARCHAR(255) NOT NULL,
                icone VARCHAR(16) NULL,
                ordre INT NOT NULL DEFAULT 0,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_famicard_service (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // ON DELETE CASCADE : un service supprimé emporte ses accès. Sans ça,
        // on garderait des autorisations vers un service qui n'existe plus —
        // invisibles, donc impossibles à retirer.
        $db->exec(
            "CREATE TABLE IF NOT EXISTS famicard_acces (
                user_id INT NOT NULL,
                service_id INT NOT NULL,
                accorde_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                accorde_par INT NULL,
                PRIMARY KEY (user_id, service_id),
                INDEX idx_famicard_acces_service (service_id),
                CONSTRAINT fk_famicard_acces_service FOREIGN KEY (service_id)
                    REFERENCES famicard_services (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $cherche = $db->prepare("SELECT COUNT(*) FROM famicard_services WHERE code = ?");
        $insere  = $db->prepare(
            "INSERT INTO famicard_services (code, nom, description, url, icone, ordre)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $ordre = 10;
        foreach (famicardServicesParDefaut() as $s) {
            $cherche->execute([$s['code']]);
            if ((int) $cherche->fetchColumn() === 0) {
                $insere->execute([$s['code'], $s['nom'], $s['description'], $s['url'], $s['icone'], $ordre]);
            }
            $ordre += 10;
        }

        $fait = true;
        return true;
    }
}

if (!function_exists('famicardServices')) {
    /** Tous les services actifs, dans l'ordre : [id => ligne complète]. */
    function famicardServices(PDO $db)
    {
        $liste = [];
        try {
            $sql = "SELECT id, code, nom, description, url, icone
                    FROM famicard_services WHERE actif = 1
                    ORDER BY ordre ASC, nom ASC";
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $liste[(int) $s['id']] = $s;
            }
        } catch (Exception $e) {
            // Table pas encore créée : l'appelant retombe sur les règles
            // historiques (voir famicardServicesDuCollaborateur).
        }
        return $liste;
    }
}

if (!function_exists('famicardAccesExplicites')) {
    /**
     * Les service_id explicitement accordés à un collaborateur.
     * Tableau VIDE = aucun accès enregistré, ce qui n'est pas la même chose que
     * « aucun accès » : voir famicardServicesDuCollaborateur.
     */
    function famicardAccesExplicites(PDO $db, $userId)
    {
        $ids = [];
        try {
            $st = $db->prepare("SELECT service_id FROM famicard_acces WHERE user_id = ?");
            $st->execute([(int) $userId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ids[] = (int) $id;
            }
        } catch (Exception $e) {
            // table absente
        }
        return $ids;
    }
}

if (!function_exists('famicardAccesHistorique')) {
    /**
     * Les règles qui s'appliquaient AVANT cette table, reproduites à
     * l'identique : FamiFormation pour tout le monde, FamiJob pour les admins
     * et les teamcoachs (c'est la règle de l'accueil du site).
     *
     * Elles ne servent que de repli, le temps que les accès soient renseignés.
     * Le jour où tout le monde a des accès explicites, cette fonction devient
     * inutile — et ce jour-là seulement, on pourra la retirer.
     */
    function famicardAccesHistorique($role)
    {
        $role = (string) $role;

        // ⚠️ LE COMPTE AGENCE EST L'EXCEPTION : FamiJob, et RIEN d'autre. Ce
        // n'est pas un collaborateur — il ne suit pas de formation, n'a pas de
        // fiche a tenir, et n'a aucune raison de se promener sur le site. Lui
        // laisser FamiFormation « par defaut » lui ouvrirait un service entier
        // par simple oubli.
        if ($role === 'agence_interim') {
            return ['famijob'];
        }

        $codes = ['famiformation'];

        if (in_array($role, ['admin', 'teamcoach'], true)) {
            $codes[] = 'famijob';
        }

        return $codes;
    }
}

if (!function_exists('famicardServicesDuCollaborateur')) {
    /**
     * Les services auxquels ce collaborateur a droit, prêts à afficher.
     *
     * @return array liste de lignes de `famicard_services`
     */
    function famicardServicesDuCollaborateur(PDO $db, $userId, $role)
    {
        $services = famicardServices($db);
        if (!$services) {
            return [];
        }

        $explicites = famicardAccesExplicites($db, $userId);

        // Aucun accès enregistré : on applique les règles historiques plutôt
        // que de n'afficher AUCUN service. La différence est cruciale — une
        // table fraîchement créée est vide pour tout le monde.
        if (!$explicites) {
            $codes = famicardAccesHistorique($role);
            return array_values(array_filter($services, static function ($s) use ($codes) {
                return in_array($s['code'], $codes, true);
            }));
        }

        return array_values(array_filter($services, static function ($s) use ($explicites) {
            return in_array((int) $s['id'], $explicites, true);
        }));
    }
}

if (!function_exists('famicardDefinitAcces')) {
    /**
     * Remplace la liste des accès d'un collaborateur.
     *
     * Tout est réécrit d'un bloc plutôt que comparé ligne à ligne : c'est plus
     * court, et surtout ça rend l'état final indépendant de l'état précédent.
     *
     * ⚠️ Enregistrer une liste VIDE n'est pas neutre : ça signifie « aucun
     * accès », et le repli historique ne s'applique plus. C'est voulu — sinon
     * il serait impossible de retirer à quelqu'un un accès qu'il tenait de la
     * règle par défaut.
     */
    function famicardDefinitAcces(PDO $db, $userId, array $serviceIds, $parUserId = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        try {
            $db->prepare("DELETE FROM famicard_acces WHERE user_id = ?")->execute([$userId]);

            if ($serviceIds) {
                $ins = $db->prepare(
                    "INSERT INTO famicard_acces (user_id, service_id, accorde_par) VALUES (?, ?, ?)"
                );
                foreach (array_unique(array_map('intval', $serviceIds)) as $sid) {
                    if ($sid > 0) {
                        $ins->execute([$userId, $sid, $parUserId !== null ? (int) $parUserId : null]);
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('famicardOublieAcces')) {
    /**
     * À appeler quand un compte est supprimé : `famicard_acces` n'a pas de clé
     * étrangère vers `utilisateurs` (aucune table du projet n'en pose), donc
     * rien ne nettoie de ce côté. Sans ça, un futur compte réutilisant le même
     * id hériterait des accès du précédent.
     */
    function famicardOublieAcces(PDO $db, $userId)
    {
        try {
            $db->prepare("DELETE FROM famicard_acces WHERE user_id = ?")->execute([(int) $userId]);
        } catch (Exception $e) {
            // table absente : rien à oublier
        }
    }
}
