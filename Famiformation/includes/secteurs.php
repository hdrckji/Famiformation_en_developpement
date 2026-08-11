<?php
// ============================================================
// secteurs.php — LES SECTEURS, ET LEURS DÉPARTEMENTS.
//
// Jusqu'ici les départements formaient une liste plate de 30 entrées. Ils sont
// désormais rangés sous 9 SECTEURS : choisir un secteur restreint la liste des
// départements proposés.
//
// UN SEUL FICHIER pour les deux applications. FamiJob et le site principal ont
// chacun leur functions.php, avec du code départements DUPLIQUÉ — c'est déjà la
// source d'écarts entre les deux. Tout ce qui touche aux secteurs vit donc ici,
// et les deux côtés l'incluent (voir la recherche de chemin en bas des
// functions.php respectifs).
//
// ⚠️ TROIS TABLES parlent des départements, et deux d'entre elles stockent le
// NOM en clair, pas l'identifiant :
//     departments               (id, department_name)   ← la référence
//     interim_user_department   (department_name)       ← texte
//     interim_shift_requests    (department_name)       ← texte
// Un renommage doit donc être répercuté dans les trois, sinon les affectations
// d'intérimaires et les demandes de créneaux se détachent EN SILENCE : rien ne
// plante, les lignes existent toujours, elles ne correspondent simplement plus
// à aucun département. C'est ce que fait secteursRenommeDepartement().
// ============================================================

if (!function_exists('secteursArbre')) {
    /**
     * L'arbre de référence : secteur => départements, dans l'ordre voulu.
     * L'ordre compte — c'est celui des listes déroulantes.
     *
     * @return array<string, string[]>
     */
    function secteursArbre()
    {
        return [
            'Plantes intérieures' => [
                'Plantes intérieures',
                'Fleurs coupées',
                'Pots intérieur',
            ],
            'Plantes extérieures' => [
                'Arbustes / Arbres',
                'Plantes méditerranéennes',
                'Vivaces / Aquatiques',
                'Fruitiers / Plantes de haie',
                'Plantes de saison',
                'Grimpantes / Bambous / Graminées',
                'Légumes',
                'Polyvalent',
            ],
            'Décoration' => [
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
                'Lumière / Statues saison',
                'Meuble jardin / Métal saison',
                'Piscine saison',
                'Barbecue saison',
                'Saison',
            ],
            'Famizoo' => [
                'Chiens / Chats',
                'Rongeurs / Oiseaux',
                'Poissons / Reptiles',
                'Extérieur / Basse-cour',
                'Étang / Bassins & fontaines',
            ],
            'Famigarden' => [
                'Terreau',
                'Engrais / Matériel de jardin',
                'Pots extérieur',
                'Bois / Outside living',
            ],
            'Food, Accueil & Caisse' => [
                'Abbaye',
                'Leonidas',
                'Lollyland',
                'Kassaplein',
                'Caisse',
                'Accueil',
                'Info prix',
                'Feux d\'artifice',
                'Food',
                'Parking',
            ],
            'Bureau' => [
                'Achats',
                'Marketing & Communication',
                'RH',
                'IT',
                'Finance & Administration',
                'Technique & Facility',
                'Direction',
            ],
            'Logistique' => [
                'Dépôt 1',
                'Dépôt 2',
                'Dépôt FDCM',
                'Magasin / Stock',
                'Transport',
            ],
            'Nuit' => [
                'Réassort de nuit',
            ],
        ];
    }
}

if (!function_exists('secteursRenommages')) {
    /**
     * ANCIEN NOM => NOUVEAU NOM, pour les départements déjà en base dont le
     * nouvel équivalent ne fait aucun doute.
     *
     * ⚠️ Cette table ne contient QUE les cas certains. Les départements
     * ambigus ne sont PAS devinés : « Interieur » peut aussi bien devenir
     * « Déco intérieur », « Plantes intérieures » ou « Pots intérieur », et
     * « Saison » se partage entre « Plantes de saison » et « Déco de saison ».
     * Les trancher au hasard déplacerait des étudiants dans un rayon qui n'est
     * pas le leur, sans que personne ne s'en aperçoive. Ils restent donc en
     * place, sans secteur, et l'administration les présente « à ranger ».
     *
     * Ne sont pas non plus devinés les anciens noms qui portent aujourd'hui un
     * nom de SECTEUR (Food, Garden, Famizoo, Plantes extérieur) : un secteur
     * compte jusqu'à 14 départements, choisir pour l'autre n'a aucun sens.
     */
    function secteursRenommages()
    {
        return [
            'Bois'               => 'Bois / Outside living',
            'Engrais'            => 'Engrais / Matériel de jardin',
            'Jouet'              => 'Jouet & Créa',
            'Menage'             => 'Ménage',
            'Mix'                => 'Mixte',
            'Nuit'               => 'Réassort de nuit',
            'Plantes intérieur'  => 'Plantes intérieures',
            'Pots Interieurs'    => 'Pots intérieur',
            'Stock'              => 'Magasin / Stock',
        ];
    }
}

if (!function_exists('secteursCorrections')) {
    /**
     * RENOMMAGES DE RATTRAPAGE : des erreurs de la version precedente de l'arbre,
     * a defaire sur les bases ou l'installation a deja tourne.
     *
     * « Accueil (+ info prix) » regroupait deux rayons qui sont en realite
     * SEPARES. On rend donc son nom a « Info prix » — ce qui lui rend du meme
     * coup ses liens etudiants, puisque le renommage propage — et « Accueil »
     * est cree a cote par l'installation normale.
     *
     * Ces corrections passent AVANT les renommages ordinaires : sinon on
     * corrigerait pour re-renommer aussitot.
     */
    function secteursCorrections()
    {
        return [
            'Accueil (+ info prix)' => 'Info prix',
            // Le parking passe de « Bureau » à « Food, Accueil & Caisse » et
            // devient un rayon à part entière : son nom n'a plus à le mentionner.
            'Technique & Facility (dont parking)' => 'Technique & Facility',
            // Quatre rayons de la décoration sont saisonniers : le nom le dit.
            'Lumière / Statues'      => 'Lumière / Statues saison',
            'Meuble jardin / Métal'  => 'Meuble jardin / Métal saison',
            'Piscine'                => 'Piscine saison',
            'Barbecue'               => 'Barbecue saison',
        ];
    }
}

if (!function_exists('secteursSupprimes')) {
    /**
     * Départements retirés DÉFINITIVEMENT, sur décision explicite.
     *
     * Ce sont d'anciens intitulés que la nouvelle liste ne reprend pas : trois
     * portaient en fait un nom de SECTEUR (Famizoo, Garden, Plantes extérieur),
     * deux sont couverts autrement (Bassin par « Étang / Bassins & fontaines »,
     * Interieur par les trois rayons « intérieur »).
     *
     * ⚠️ La suppression emporte les liens étudiants qui pointaient dessus, et
     * c'est irréversible. On COMPTE donc ce qui disparaît et on le remonte dans
     * le bilan : supprimer sans dire combien de rattachements partent avec,
     * c'est faire disparaître de l'information sans que personne ne le sache.
     *
     * Les rayons conservés ne sont PAS ici : Parking et Food rejoignent « Food,
     * Accueil & Caisse », Saison la « Décoration », Polyvalent les « Plantes
     * extérieures » — voir secteursArbre().
     */
    function secteursSupprimes()
    {
        return [
            'Bassin',
            'Famizoo',
            'Garden',
            'Interieur',
            'Plantes extérieur',
        ];
    }
}

if (!function_exists('secteursNormalise')) {
    /** Clé de comparaison d'un nom : sans accent, sans casse, sans ponctuation. */
    function secteursNormalise($s)
    {
        $s = (string) $s;
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        $s = strtr($s, [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n','ý'=>'y','ÿ'=>'y','œ'=>'oe','æ'=>'ae',
        ]);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}

if (!function_exists('secteursAssureSchema')) {
    /**
     * Crée la table des secteurs et les colonnes qui manquent sur departments.
     * Idempotent : appelable à chaque requête sans rien casser ni ralentir.
     */
    function secteursAssureSchema(PDO $db)
    {
        // Le cache est indexe sur la CONNEXION, pas global : avec un simple
        // booleen, une deuxieme connexion dans le meme processus repartait en
        // croyant le schema deja pose, et travaillait sur une base sans la table
        // des secteurs.
        static $faits = [];
        $cle = spl_object_id($db);
        if (isset($faits[$cle])) { return true; }

        $db->exec(
            "CREATE TABLE IF NOT EXISTS sectors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sector_name VARCHAR(120) NOT NULL,
                position INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_sector_name (sector_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // Colonnes ajoutées à `departments`. On regarde ce qui existe déjà : un
        // ALTER sur une colonne présente échoue, et cette fonction tourne à
        // chaque page.
        //
        // sector_id est VOLONTAIREMENT NULLABLE et SANS clé étrangère : un
        // département sans secteur est un état normal (c'est le « à ranger »),
        // et une contrainte empêcherait de supprimer un secteur sans toucher
        // d'abord à tous ses départements.
        $colonnes = [];
        foreach ($db->query('SHOW COLUMNS FROM departments')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $colonnes[strtolower((string) $c['Field'])] = true;
        }
        $aAjouter = [
            'sector_id' => "ALTER TABLE departments ADD COLUMN sector_id INT NULL",
            'position'  => "ALTER TABLE departments ADD COLUMN position INT NOT NULL DEFAULT 0",
        ];
        foreach ($aAjouter as $nom => $sql) {
            if (!isset($colonnes[$nom])) {
                try { $db->exec($sql); } catch (Exception $e) { /* course entre deux requêtes */ }
            }
        }

        $faits[$cle] = true;
        return true;
    }
}

if (!function_exists('secteursRenommeDepartement')) {
    /**
     * Renomme un département PARTOUT : la table de référence et les deux tables
     * qui gardent le nom en clair. Sans ce dernier point, une affectation
     * d'intérimaire ou une demande de créneau resterait accrochée à un nom qui
     * n'existe plus — sans erreur, sans trace.
     *
     * Si le nouveau nom existe DÉJÀ comme département, on ne crée pas de
     * doublon : les liens étudiants de l'ancien sont reportés sur le nouveau,
     * puis l'ancien est supprimé. C'est une FUSION, et c'est voulu.
     *
     * @return string '' si rien à faire, sinon un mot décrivant ce qui a eu lieu
     */
    function secteursRenommeDepartement(PDO $db, $ancien, $nouveau)
    {
        $ancien = trim((string) $ancien);
        $nouveau = trim((string) $nouveau);
        if ($ancien === '' || $nouveau === '' || $ancien === $nouveau) { return ''; }

        $q = $db->prepare("SELECT id FROM departments WHERE department_name = ? LIMIT 1");
        $q->execute([$ancien]);
        $idAncien = (int) $q->fetchColumn();
        if ($idAncien <= 0) { return ''; }               // plus rien à renommer

        $q->execute([$nouveau]);
        $idNouveau = (int) $q->fetchColumn();

        if ($idNouveau > 0 && $idNouveau !== $idAncien) {
            // FUSION. Les liens étudiants d'abord : la clé (student_id,
            // department_id) est unique, donc on ignore ceux qui existent déjà
            // des deux côtés plutôt que de faire échouer toute la requête.
            $db->prepare(
                "UPDATE IGNORE student_department_links SET department_id = ? WHERE department_id = ?"
            )->execute([$idNouveau, $idAncien]);
            $db->prepare("DELETE FROM student_department_links WHERE department_id = ?")->execute([$idAncien]);
            $db->prepare("DELETE FROM departments WHERE id = ?")->execute([$idAncien]);
            $resultat = 'fusion';
        } else {
            $db->prepare("UPDATE departments SET department_name = ? WHERE id = ?")
               ->execute([$nouveau, $idAncien]);
            $resultat = 'renomme';
        }

        // Les deux tables qui stockent le nom en texte.
        foreach (['interim_user_department', 'interim_shift_requests'] as $table) {
            try {
                $db->prepare("UPDATE IGNORE $table SET department_name = ? WHERE department_name = ?")
                   ->execute([$nouveau, $ancien]);
            } catch (Exception $e) {
                // La table peut ne pas exister encore sur une base neuve : ce
                // n'est pas une raison d'abandonner le renommage.
            }
        }

        return $resultat;
    }
}

if (!function_exists('secteursInstalle')) {
    /**
     * Met la base en conformité avec l'arbre ci-dessus. REJOUABLE : on peut
     * l'appeler autant de fois qu'on veut, elle ne fait que ce qui manque.
     *
     * Ce qu'elle fait :
     *   1. crée les secteurs manquants, dans l'ordre de l'arbre ;
     *   2. applique les renommages certains (et les propage — voir plus haut) ;
     *   3. crée les départements de l'arbre qui n'existent pas ;
     *   4. rattache chaque département de l'arbre à son secteur.
     *
     * Ce qu'elle NE fait PAS : toucher aux départements absents de l'arbre. Ils
     * gardent leurs liens étudiants et apparaissent « à ranger ». Rien n'est
     * jamais supprimé ni désactivé automatiquement.
     *
     * @return array{secteurs:int, renommes:int, fusions:int, crees:int, ranges:int, aRanger:string[]}
     */
    function secteursInstalle(PDO $db)
    {
        secteursAssureSchema($db);
        $bilan = ['secteurs' => 0, 'renommes' => 0, 'fusions' => 0, 'crees' => 0, 'ranges' => 0,
                  'supprimes' => 0, 'liensPerdus' => 0, 'supprimesAvecLiens' => [], 'aRanger' => []];

        // 1. Les secteurs.
        $insSecteur = $db->prepare(
            "INSERT INTO sectors (sector_name, position, is_active)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE position = VALUES(position), is_active = 1, updated_at = CURRENT_TIMESTAMP"
        );
        $rang = 0;
        foreach (secteursArbre() as $secteur => $departements) {
            $insSecteur->execute([$secteur, ++$rang]);
            $bilan['secteurs']++;
        }
        $idsSecteurs = [];
        foreach ($db->query("SELECT id, sector_name FROM sectors")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $idsSecteurs[$r['sector_name']] = (int) $r['id'];
        }

        // 1 bis. Les corrections de rattrapage, AVANT tout le reste.
        foreach (secteursCorrections() as $ancien => $nouveau) {
            secteursRenommeDepartement($db, $ancien, $nouveau);
        }

        // 1 ter. Les suppressions décidées. APRÈS les corrections — pour ne pas
        //        supprimer un nom qu'on vient de rétablir — et avant le reste.
        $qLiens = $db->prepare("SELECT COUNT(*) FROM student_department_links WHERE department_id = ?");
        $qId    = $db->prepare("SELECT id FROM departments WHERE department_name = ? LIMIT 1");
        foreach (secteursSupprimes() as $nomSup) {
            $qId->execute([$nomSup]);
            $idSup = (int) $qId->fetchColumn();
            if ($idSup <= 0) { continue; }
            $qLiens->execute([$idSup]);
            $nLiens = (int) $qLiens->fetchColumn();
            if ($nLiens > 0) {
                $bilan['liensPerdus'] += $nLiens;
                $bilan['supprimesAvecLiens'][] = $nomSup . ' (' . $nLiens . ')';
            }
            try { $db->prepare("DELETE FROM student_department_links WHERE department_id = ?")->execute([$idSup]); } catch (Exception $e) {}
            $db->prepare("DELETE FROM departments WHERE id = ?")->execute([$idSup]);
            $bilan['supprimes']++;
        }

        // 2. Les renommages certains, AVANT la création : sinon on créerait
        //    « Mixte » à côté de « Mix » au lieu de renommer « Mix ».
        foreach (secteursRenommages() as $ancien => $nouveau) {
            $r = secteursRenommeDepartement($db, $ancien, $nouveau);
            if ($r === 'renomme') { $bilan['renommes']++; }
            elseif ($r === 'fusion') { $bilan['fusions']++; }
        }

        // 3 & 4. Les départements de l'arbre : créés s'ils manquent, rattachés
        //        à leur secteur dans tous les cas.
        $existants = [];
        foreach ($db->query("SELECT id, department_name FROM departments")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $existants[secteursNormalise($r['department_name'])] = (int) $r['id'];
        }
        $insDep = $db->prepare(
            "INSERT INTO departments (department_name, is_active) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE is_active = 1, updated_at = CURRENT_TIMESTAMP"
        );
        $majDep = $db->prepare("UPDATE departments SET sector_id = ?, position = ?, is_active = 1 WHERE id = ?");
        $dansArbre = [];
        foreach (secteursArbre() as $secteur => $departements) {
            $idSecteur = $idsSecteurs[$secteur] ?? 0;
            $ordre = 0;
            foreach ($departements as $nom) {
                $ordre++;
                $cle = secteursNormalise($nom);
                $dansArbre[$cle] = true;
                if (!isset($existants[$cle])) {
                    $insDep->execute([$nom]);
                    $q = $db->prepare("SELECT id FROM departments WHERE department_name = ? LIMIT 1");
                    $q->execute([$nom]);
                    $existants[$cle] = (int) $q->fetchColumn();
                    $bilan['crees']++;
                }
                if (!empty($existants[$cle]) && $idSecteur > 0) {
                    $majDep->execute([$idSecteur, $ordre, $existants[$cle]]);
                    $bilan['ranges']++;
                }
            }
        }

        // Ce qui reste sans secteur : on le NOMME, pour que ce soit une liste à
        // traiter et pas une zone d'ombre.
        foreach ($db->query(
            "SELECT department_name FROM departments WHERE sector_id IS NULL ORDER BY department_name"
        )->fetchAll(PDO::FETCH_COLUMN) as $nom) {
            $bilan['aRanger'][] = (string) $nom;
        }

        return $bilan;
    }
}

if (!function_exists('secteursListe')) {
    /**
     * Les secteurs actifs et leurs départements, prêts pour un menu en cascade.
     *
     * @return array<int, array{id:int, nom:string, departements:array<int, array{id:int,nom:string}>}>
     */
    function secteursListe(PDO $db, $inclureVides = false)
    {
        secteursAssureSchema($db);
        $secteurs = [];
        foreach ($db->query(
            "SELECT id, sector_name FROM sectors WHERE is_active = 1 ORDER BY position ASC, sector_name ASC"
        )->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $secteurs[(int) $r['id']] = ['id' => (int) $r['id'], 'nom' => (string) $r['sector_name'], 'departements' => []];
        }
        foreach ($db->query(
            "SELECT id, department_name, sector_id FROM departments
              WHERE is_active = 1 AND sector_id IS NOT NULL
              ORDER BY position ASC, department_name ASC"
        )->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sid = (int) $r['sector_id'];
            if (isset($secteurs[$sid])) {
                $secteurs[$sid]['departements'][] = ['id' => (int) $r['id'], 'nom' => (string) $r['department_name']];
            }
        }
        if (!$inclureVides) {
            $secteurs = array_filter($secteurs, static function ($s) { return !empty($s['departements']); });
        }
        return array_values($secteurs);
    }
}

if (!function_exists('secteursSansSecteur')) {
    /** Les départements actifs qui n'ont pas encore de secteur (« à ranger »). */
    function secteursSansSecteur(PDO $db)
    {
        secteursAssureSchema($db);
        // ⚠️ « Sans secteur » ne suffit pas : un département rattaché à un
        // secteur SUPPRIMÉ ou désactivé n'apparaîtrait dans aucune des deux
        // listes — ni rangé, ni à ranger. Il deviendrait invisible dans
        // l'administration tout en restant lié à des étudiants. On le rattrape
        // ici, avec ceux qui n'ont jamais eu de secteur.
        return $db->query(
            "SELECT d.id, d.department_name FROM departments d
              WHERE d.is_active = 1
                AND (d.sector_id IS NULL
                     OR d.sector_id NOT IN (SELECT s.id FROM sectors s WHERE s.is_active = 1))
              ORDER BY d.department_name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('secteursRessemblances')) {
    /**
     * Ce à quoi un nom RESSEMBLE, sans être la même chose.
     *
     * Sert au bloc « à ranger » : on lit « Famizoo » dans la liste des
     * départements non rangés alors qu'un SECTEUR s'appelle Famizoo, et on en
     * conclut que le rangement n'a pas marché. Ce sont deux lignes distinctes —
     * l'une est un rayon qui porte des liens étudiants, l'autre un intitulé de
     * regroupement. Le dire à l'écran évite d'avoir à le deviner.
     *
     * @return array{secteurs: string[], departements: array<int, array{nom:string, secteur:string}>}
     */
    function secteursRessemblances(PDO $db, $nom, $exclureId = 0)
    {
        $n = secteursNormalise($nom);
        $sortie = ['secteurs' => [], 'departements' => []];
        if ($n === '') { return $sortie; }
        $proche = static function ($a, $b) {
            // Un nom est « proche » s'il est contenu dans l'autre — « Bassin »
            // dans « Étang / Bassins & fontaines », « Parking » dans « Technique
            // & Facility (dont parking) ». Bornée à 4 caractères : en dessous,
            // n'importe quoi ressemble à n'importe quoi.
            if ($a === $b) { return true; }
            if (strlen($a) < 4 || strlen($b) < 4) { return false; }
            return strpos($a, $b) !== false || strpos($b, $a) !== false;
        };
        foreach ($db->query("SELECT sector_name FROM sectors WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN) as $sn) {
            if ($proche($n, secteursNormalise($sn))) { $sortie['secteurs'][] = (string) $sn; }
        }
        $q = $db->query(
            "SELECT d.id, d.department_name, s.sector_name
               FROM departments d
               LEFT JOIN sectors s ON s.id = d.sector_id
              WHERE d.is_active = 1 AND d.sector_id IS NOT NULL"
        );
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ((int) $r['id'] === (int) $exclureId) { continue; }
            if ($proche($n, secteursNormalise($r['department_name']))) {
                $sortie['departements'][] = ['nom' => (string) $r['department_name'],
                                             'secteur' => (string) ($r['sector_name'] ?? '')];
            }
        }
        return $sortie;
    }
}

if (!function_exists('secteursOptionsHtml')) {
    /**
     * Les <option> des départements, AVEC leur secteur en data-secteur.
     *
     * Pensé pour se glisser dans les listes déroulantes qui existent déjà : on
     * remplace la boucle interne, sans toucher au <select> ni à son id ni à son
     * name. Les pages concernées ont leur propre JavaScript accroché à ces
     * identifiants — les renommer aurait cassé des choses très loin d'ici.
     *
     * @param string $par 'nom' (valeur = le nom, ce que stockent les tables
     *                    texte) ou 'id' (valeur = l'identifiant)
     */
    function secteursOptionsHtml(PDO $db, $valeur = '', $par = 'nom', $avecSansSecteur = true)
    {
        $par = ($par === 'id') ? 'id' : 'nom';
        $esc = static function ($x) { return htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8'); };
        $groupes = secteursListe($db);
        if ($avecSansSecteur) {
            $orphelins = secteursSansSecteur($db);
            if ($orphelins) {
                $groupes[] = ['id' => 0, 'nom' => 'À ranger', 'departements' => array_map(
                    static function ($o) { return ['id' => (int) $o['id'], 'nom' => (string) $o['department_name']]; },
                    $orphelins
                )];
            }
        }
        // <optgroup> EN PLUS du filtre : même sans toucher au menu secteur, on
        // voit à quel rayon appartient chaque ligne. C'est aussi ce qui reste
        // affiché si le script ne s'exécute pas.
        $html = '';
        foreach ($groupes as $g) {
            if (empty($g['departements'])) { continue; }
            $html .= '<optgroup label="' . $esc($g['nom']) . '">';
            foreach ($g['departements'] as $d) {
                $v = (string) $d[$par];
                $sel = ((string) $valeur !== '' && (string) $valeur === $v) ? ' selected' : '';
                $html .= '<option value="' . $esc($v) . '" data-secteur="' . $esc($g['id']) . '"' . $sel . '>'
                       . $esc($d['nom']) . '</option>';
            }
            $html .= '</optgroup>';
        }
        return $html;
    }
}

if (!function_exists('secteursFiltreHtml')) {
    /**
     * Le menu « secteur » seul, qui filtre un <select> de départements existant
     * désigné par son id. À poser juste avant lui.
     */
    function secteursFiltreHtml(PDO $db, $cibleId, $libelle = 'Secteur')
    {
        $esc = static function ($x) { return htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8'); };
        $groupes = secteursListe($db);
        $html = '<select class="secteur-select" data-cible="' . $esc($cibleId) . '" aria-label="' . $esc($libelle) . '">';
        $html .= '<option value="">— Tous les secteurs —</option>';
        foreach ($groupes as $g) {
            $html .= '<option value="' . $esc($g['id']) . '">' . $esc($g['nom']) . '</option>';
        }
        if (secteursSansSecteur($db)) {
            $html .= '<option value="0">À ranger</option>';
        }
        $html .= '</select>';
        return $html;
    }
}

if (!function_exists('secteursChampsHtml')) {
    /**
     * LE MENU EN CASCADE, réutilisable : un menu « secteur » qui filtre un menu
     * « département ». Rendu par une seule fonction pour que les six pages qui
     * choisissent un département se comportent EXACTEMENT pareil.
     *
     * Fonctionne sans JavaScript : le menu des départements contient tout, et
     * le filtrage n'est qu'un confort. Sans ce garde-fou, un navigateur qui
     * bloque le script rendrait la page inutilisable.
     *
     * @param string $nomChamp     nom du champ envoyé (celui du DÉPARTEMENT)
     * @param string $valeur       département déjà choisi (son nom), ou ''
     * @param array  $options      'par' => 'nom'|'id' (ce que vaut l'option),
     *                             'vide' => libellé de l'entrée vide,
     *                             'requis' => bool, 'sansSecteur' => bool
     */
    function secteursChampsHtml(PDO $db, $nomChamp, $valeur = '', array $options = [])
    {
        $par = ($options['par'] ?? 'nom') === 'id' ? 'id' : 'nom';
        $vide = (string) ($options['vide'] ?? '— Choisir —');
        $requis = !empty($options['requis']);
        $avecSansSecteur = !empty($options['sansSecteur']);

        $secteurs = secteursListe($db);
        if ($avecSansSecteur) {
            $orphelins = secteursSansSecteur($db);
            if ($orphelins) {
                $secteurs[] = ['id' => 0, 'nom' => 'À ranger', 'departements' => array_map(
                    static function ($o) { return ['id' => (int) $o['id'], 'nom' => (string) $o['department_name']]; },
                    $orphelins
                )];
            }
        }

        $uid = 'sc' . substr(md5($nomChamp . microtime(true)), 0, 8);
        $esc = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

        // Quel secteur pré-sélectionner : celui du département déjà choisi.
        $secteurChoisi = '';
        foreach ($secteurs as $s) {
            foreach ($s['departements'] as $d) {
                if ((string) $valeur !== '' && (string) $valeur === (string) $d[$par]) { $secteurChoisi = (string) $s['id']; }
            }
        }

        $html = '<select class="secteur-select" id="' . $uid . '-sect" data-cible="' . $uid . '-dep" aria-label="Secteur">';
        $html .= '<option value="">— Tous les secteurs —</option>';
        foreach ($secteurs as $s) {
            $sel = ((string) $s['id'] === $secteurChoisi) ? ' selected' : '';
            $html .= '<option value="' . $esc($s['id']) . '"' . $sel . '>' . $esc($s['nom']) . '</option>';
        }
        $html .= '</select>';

        $html .= '<select name="' . $esc($nomChamp) . '" id="' . $uid . '-dep" class="departement-select"'
               . ($requis ? ' required' : '') . ' aria-label="Département">';
        $html .= '<option value="">' . $esc($vide) . '</option>';
        foreach ($secteurs as $s) {
            foreach ($s['departements'] as $d) {
                $v = (string) $d[$par];
                $sel = ((string) $valeur !== '' && (string) $valeur === $v) ? ' selected' : '';
                $html .= '<option value="' . $esc($v) . '" data-secteur="' . $esc($s['id']) . '"' . $sel . '>'
                       . $esc($d['nom']) . '</option>';
            }
        }
        $html .= '</select>';

        return $html;
    }
}

if (!function_exists('secteursScript')) {
    /**
     * Le script du filtrage, à poser UNE fois par page (il gère tous les menus).
     *
     * Les options sont retirées du DOM et remises, plutôt que simplement
     * masquées : `display:none` sur une <option> est ignoré par Safari et par
     * plusieurs navigateurs mobiles, où la liste serait restée entière.
     */
    function secteursScript()
    {
        return <<<'HTML'
<script>
(function () {
  function filtre(sel) {
    var dep = document.getElementById(sel.getAttribute('data-cible'));
    if (!dep) { return; }
    // On memorise la liste complete UNE fois, avec le groupe d'origine de
    // chaque option : elle sera reconstruite a chaque changement.
    if (!dep._toutes) {
      dep._toutes = Array.prototype.slice.call(dep.querySelectorAll('option')).map(function (o) {
        var g = o.parentNode && o.parentNode.tagName === 'OPTGROUP' ? o.parentNode.getAttribute('label') : '';
        return { el: o, groupe: g, secteur: o.getAttribute('data-secteur') };
      });
    }
    var voulu = sel.value;
    var garde = dep.value;
    while (dep.firstChild) { dep.removeChild(dep.firstChild); }
    var groupeCourant = null, nomGroupe = null;
    dep._toutes.forEach(function (o) {
      if (o.secteur !== null && voulu !== '' && o.secteur !== voulu) { return; }
      if (!o.groupe) { dep.appendChild(o.el); return; }      // l'option vide
      if (o.groupe !== nomGroupe) {
        nomGroupe = o.groupe;
        groupeCourant = document.createElement('optgroup');
        groupeCourant.setAttribute('label', nomGroupe);
        dep.appendChild(groupeCourant);
      }
      groupeCourant.appendChild(o.el);
    });
    // On ne garde la sélection que si elle est encore proposée : sinon le champ
    // enverrait un département qui n'est plus dans la liste affichée.
    dep.value = garde;
    if (dep.selectedIndex < 0 || dep.value !== garde) { dep.selectedIndex = 0; }
  }
  function branche() {
    Array.prototype.forEach.call(document.querySelectorAll('.secteur-select'), function (sel) {
      if (sel._branche) { return; }
      sel._branche = true;
      sel.addEventListener('change', function () { filtre(sel); });
      if (sel.value) { filtre(sel); }   // arrivée sur un département déjà choisi
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', branche); }
  else { branche(); }
})();
</script>
HTML;
    }
}
