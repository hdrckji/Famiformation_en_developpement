<?php
// ============================================================
// LA VALIDATION DU PLANNING DE LA SEMAINE.
//
// Jusqu'ici les horaires partaient au fil de l'eau, un mail par personne au
// moment du matching. Deux problemes : on prevenait quelqu'un d'un creneau qui
// pouvait encore bouger le lendemain, et personne ne savait dire si une semaine
// etait « finie » ou non.
//
// Une semaine a donc maintenant DEUX ETATS :
//   • EN PREPARATION — on remplit, on deplace, on se trompe, ca ne regarde
//     personne d'autre ;
//   • VALIDE — c'est arrete, et c'est A CE MOMENT-LA que tout part.
//
// Trois envois, trois publics, trois contenus :
//   1. CHAQUE ETUDIANT place recoit SES creneaux. Rien d'autre — surtout pas
//      un total d'heures pour la semaine : ce total se lit comme un engagement
//      alors que le texte des creneaux est libre et parfois approximatif.
//   2. CHAQUE AGENCE qui a quelqu'un cette semaine recoit un classeur limite a
//      ses propres creneaux, plus l'avertissement que ces personnes seront
//      probablement redemandees la semaine suivante.
//   3. LE SERVICE INTERNE recoit le classeur complet, sans commentaire.
//
// ⚠️ ON N'ECRIT QU'AUX CONCERNES. Une agence sans personne cette semaine ne
// recoit rien : un mail vide, envoye chaque semaine, cesse d'etre lu en un
// mois — et le jour ou il compte vraiment, il est deja dans la corbeille.
//
// ⚠️ AUCUNE ADRESSE EN DUR ICI. Elles viennent des variables d'environnement
// et de la table des agences (voir includes/horaires.php, qui resout deja
// « a qui envoyer »). Ce fichier decide QUOI envoyer et A QUI, pas OU.
// ============================================================

require_once __DIR__ . '/horaires.php';
require_once __DIR__ . '/export_semaine.php';

if (!function_exists('famijobEtatsPlanning')) {
    /** Les deux etats, et leur libelle. Rien d'autre n'existe. */
    function famijobEtatsPlanning()
    {
        return [
            'preparation' => ['fr' => 'En préparation', 'nl' => 'In voorbereiding'],
            'valide'      => ['fr' => 'Validé',         'nl' => 'Gevalideerd'],
        ];
    }
}

if (!function_exists('famijobAssurePlanningSemaine')) {
    /**
     * La table des etats. Une ligne par semaine, et seulement quand elle a ete
     * validee : l'absence de ligne VEUT DIRE « en preparation », ce qui evite
     * d'avoir a creer une ligne pour chaque semaine de l'annee a l'avance.
     */
    function famijobAssurePlanningSemaine(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return;
        }
        $db->exec(
            "CREATE TABLE IF NOT EXISTS interim_planning_semaine (
                week_start DATE NOT NULL,
                statut VARCHAR(20) NOT NULL DEFAULT 'valide',
                valide_par_user_id INT NULL,
                valide_le DATETIME NULL,
                envois_ok INT NOT NULL DEFAULT 0,
                envois_ko INT NOT NULL DEFAULT 0,
                PRIMARY KEY (week_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $fait = true;
    }
}

if (!function_exists('famijobAssureEnvoisPlanning')) {
    /**
     * Ce qui a DEJA ete envoye, et sous quelle forme.
     *
     * ⚠️ C'EST CETTE TABLE QUI PERMET DE NE PAS SPAMMER. A la revalidation, on
     * recalcule l'empreinte de ce que chacun devrait recevoir et on la compare a
     * celle enregistree : identique, on n'ecrit pas. Sans elle, corriger un
     * seul creneau le jeudi renverrait son horaire a toute l'entreprise — et au
     * troisieme envoi, plus personne ne les ouvre.
     *
     * `cible` est une cle stable : « etu:12 », « ext:jean dupont »,
     * « ag:randstad », « interne ».
     */
    function famijobAssureEnvoisPlanning(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return;
        }
        $db->exec(
            "CREATE TABLE IF NOT EXISTS interim_planning_envois (
                week_start DATE NOT NULL,
                cible VARCHAR(120) NOT NULL,
                empreinte CHAR(40) NOT NULL,
                envoye_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (week_start, cible)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $fait = true;
    }
}

if (!function_exists('famijobEmpreintesEnvoyees')) {
    /** Les empreintes deja envoyees pour cette semaine : [cible => empreinte]. */
    function famijobEmpreintesEnvoyees(PDO $db, DateTimeImmutable $weekStart)
    {
        famijobAssureEnvoisPlanning($db);
        $out = [];
        try {
            $st = $db->prepare('SELECT cible, empreinte FROM interim_planning_envois WHERE week_start = ?');
            $st->execute([$weekStart->format('Y-m-d')]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
                $out[(string) $l['cible']] = (string) $l['empreinte'];
            }
        } catch (Exception $e) {
            // Table absente : on considere que rien n'a ete envoye. Un envoi de
            // trop vaut mieux qu'un horaire que personne ne recoit.
        }
        return $out;
    }
}

if (!function_exists('famijobNoteEnvoi')) {
    /** Enregistre ce qui vient de partir, pour ne pas le renvoyer a l'identique. */
    function famijobNoteEnvoi(PDO $db, DateTimeImmutable $weekStart, $cible, $empreinte)
    {
        famijobAssureEnvoisPlanning($db);
        try {
            $db->prepare(
                'INSERT INTO interim_planning_envois (week_start, cible, empreinte, envoye_le)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE empreinte = VALUES(empreinte), envoye_le = NOW()'
            )->execute([$weekStart->format('Y-m-d'), (string) $cible, (string) $empreinte]);
        } catch (Exception $e) {
            // Sans trace, le prochain envoi repartira : c'est le bon sens de
            // l'erreur.
        }
    }
}

if (!function_exists('famijobRouvrePlanningSemaine')) {
    /**
     * Rouvre une semaine validee.
     *
     * ⚠️ ON NE TOUCHE PAS AUX EMPREINTES. C'est tout l'interet : apres
     * modification, seuls ceux dont l'horaire a CHANGE recevront un nouveau
     * mail. Les effacer ici reviendrait a tout renvoyer a la prochaine
     * validation, ce qu'on cherche precisement a eviter.
     */
    function famijobRouvrePlanningSemaine(PDO $db, DateTimeImmutable $weekStart, $userId)
    {
        famijobAssurePlanningSemaine($db);
        $db->prepare(
            "INSERT INTO interim_planning_semaine (week_start, statut, valide_par_user_id, valide_le)
             VALUES (?, 'preparation', ?, NULL)
             ON DUPLICATE KEY UPDATE statut = 'preparation'"
        )->execute([$weekStart->format('Y-m-d'), (int) $userId > 0 ? (int) $userId : null]);
        return true;
    }
}

if (!function_exists('famijobStatutSemaine')) {
    /**
     * @return array ['statut' => 'preparation'|'valide', 'le' => string, 'par' => string]
     */
    function famijobStatutSemaine(PDO $db, DateTimeImmutable $weekStart)
    {
        famijobAssurePlanningSemaine($db);
        $vide = ['statut' => 'preparation', 'le' => '', 'par' => '', 'envois_ok' => 0, 'envois_ko' => 0];

        try {
            $st = $db->prepare(
                'SELECT p.statut, p.valide_le, p.envois_ok, p.envois_ko,
                        TRIM(CONCAT(COALESCE(u.prenom, ""), " ", COALESCE(u.nom, ""))) AS par
                 FROM interim_planning_semaine p
                 LEFT JOIN utilisateurs u ON u.id = p.valide_par_user_id
                 WHERE p.week_start = ? LIMIT 1'
            );
            $st->execute([$weekStart->format('Y-m-d')]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return $vide;
        }

        if (!$ligne) {
            return $vide;
        }
        return [
            'statut'    => (string) $ligne['statut'] === 'valide' ? 'valide' : 'preparation',
            'le'        => (string) ($ligne['valide_le'] ?? ''),
            'par'       => trim((string) ($ligne['par'] ?? '')),
            'envois_ok' => (int) ($ligne['envois_ok'] ?? 0),
            'envois_ko' => (int) ($ligne['envois_ko'] ?? 0),
        ];
    }
}

if (!function_exists('famijobLibelleStatutSemaine')) {
    function famijobLibelleStatutSemaine($statut)
    {
        $etats = famijobEtatsPlanning();
        $cle = isset($etats[$statut]) ? $statut : 'preparation';
        $nl = function_exists('famiLang') && famiLang() === 'nl';
        return $etats[$cle][$nl ? 'nl' : 'fr'];
    }
}

if (!function_exists('famijobCreneauxParEtudiant')) {
    /**
     * Les creneaux de la semaine, ranges par personne affectee.
     *
     * @return array [ ['nom','email','agence','student_id','shifts'=>[...]], ... ]
     *
     * Les personnes SANS compte (affectees en texte libre) sont incluses : leur
     * agence doit les voir dans son classeur. Elles n'ont pas d'adresse, donc
     * pas de mail individuel — ce n'est pas une erreur, c'est leur situation.
     */
    function famijobCreneauxParEtudiant(PDO $db, DateTimeImmutable $weekStart)
    {
        $weekEnd = $weekStart->modify('+6 days');

        $colonnes = [];
        foreach ($db->query('SHOW COLUMNS FROM interim_shift_requests')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $colonnes[(string) $c['Field']] = true;
        }
        $filtreValide = isset($colonnes['validation_status']) ? " AND r.validation_status = 'approved'" : '';

        $st = $db->prepare(
            "SELECT a.student_id, a.external_name, a.agency_name,
                    u.nom, u.prenom, u.email, u.interim,
                    r.shift_date, r.department_name, r.time_slot
             FROM interim_shift_assignments a
             INNER JOIN interim_shift_requests r ON r.id = a.request_id
             LEFT JOIN utilisateurs u ON u.id = a.student_id
             WHERE r.shift_date BETWEEN ? AND ?" . $filtreValide . "
             ORDER BY r.shift_date ASC, r.time_slot ASC"
        );
        $st->execute([$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);

        $gens = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $studentId = (int) ($l['student_id'] ?? 0);
            $cle = $studentId > 0 ? ('u' . $studentId) : ('x' . mb_strtolower(trim((string) $l['external_name'])));

            if (!isset($gens[$cle])) {
                $nom = $studentId > 0
                    ? trim(trim((string) $l['prenom']) . ' ' . trim((string) $l['nom']))
                    : trim((string) $l['external_name']);
                $agence = $studentId > 0 ? trim((string) $l['interim']) : trim((string) $l['agency_name']);
                if ($agence === '') {
                    $agence = trim((string) $l['agency_name']);
                }
                $gens[$cle] = [
                    'student_id' => $studentId,
                    'nom'        => $nom !== '' ? $nom : '(sans nom)',
                    'email'      => $studentId > 0 ? trim((string) $l['email']) : '',
                    'agence'     => $agence,
                    'shifts'     => [],
                ];
            }

            $gens[$cle]['shifts'][] = [
                'date'       => (string) $l['shift_date'],
                'departement'=> trim((string) $l['department_name']),
                'horaire'    => trim((string) $l['time_slot']),
            ];
        }

        return array_values($gens);
    }
}

if (!function_exists('famijobMailEtudiantCorps')) {
    /**
     * Le mail d'un etudiant : ses creneaux, et rien de plus.
     *
     * ⚠️ PAS DE TOTAL D'HEURES — demande explicite. Le texte des creneaux est
     * libre en base (« 9h-17h », « du matin »…) : un total calcule dessus se
     * lirait comme un engagement alors qu'il n'est qu'une lecture approximative.
     */
    function famijobMailEtudiantCorps(array $personne, DateTimeImmutable $weekStart)
    {
        $weekEnd = $weekStart->modify('+6 days');

        $html = '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;">Bonjour '
              . e($personne['nom']) . ',</p>'
              . '<p style="margin:0 0 18px;font-size:16px;line-height:1.6;">Voici ton horaire pour la semaine du '
              . e($weekStart->format('d/m/Y')) . ' au ' . e($weekEnd->format('d/m/Y')) . '.</p>';

        $html .= '<table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;font-size:15px;">';
        foreach ($personne['shifts'] as $s) {
            $jour = famijobWeekdayLabelFr($s['date'], true);
            $d = date_create($s['date']);
            $html .= '<tr>'
                   . '<td style="border-bottom:1px solid #e3ebe6;font-weight:700;white-space:nowrap;">'
                   . e($jour) . ' ' . e($d ? $d->format('d/m') : $s['date']) . '</td>'
                   . '<td style="border-bottom:1px solid #e3ebe6;white-space:nowrap;">' . e($s['horaire']) . '</td>'
                   . '<td style="border-bottom:1px solid #e3ebe6;color:#55665c;">' . e($s['departement']) . '</td>'
                   . '</tr>';
        }
        $html .= '</table>';

        $html .= '<p style="margin:18px 0 0;font-size:14px;line-height:1.6;color:#55665c;">'
               . 'En cas d\'empêchement, préviens ton agence ou ton contact Famiflora le plus tôt possible.</p>';

        return $html;
    }
}

if (!function_exists('famijobMailAgenceCorps')) {
    /** Le mail d'une agence : le classeur est en piece jointe, le texte est court. */
    function famijobMailAgenceCorps($nomAgence, array $noms, DateTimeImmutable $weekStart)
    {
        $weekEnd = $weekStart->modify('+6 days');

        $html = '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;">Bonjour,</p>'
              . '<p style="margin:0 0 18px;font-size:16px;line-height:1.6;">Le planning de la semaine du '
              . e($weekStart->format('d/m/Y')) . ' au ' . e($weekEnd->format('d/m/Y'))
              . ' est validé. Vous trouverez en pièce jointe le fichier reprenant '
              . 'les créneaux couverts par votre agence.</p>';

        if ($noms) {
            $html .= '<p style="margin:0 0 8px;font-size:15px;font-weight:700;">Personnes concernées</p>'
                   . '<ul style="margin:0 0 18px;padding-left:20px;font-size:15px;line-height:1.7;">';
            foreach ($noms as $n) {
                $html .= '<li>' . e($n) . '</li>';
            }
            $html .= '</ul>';
        }

        // La phrase demandee. Encadree pour qu'elle ne se perde pas dans le
        // texte : c'est l'information qui sert a l'agence pour anticiper.
        $html .= '<div style="margin:0 0 6px;padding:14px 16px;background:#fff8e8;border-left:4px solid #e0a83c;'
               . 'font-size:15px;line-height:1.6;">'
               . '<strong>À noter :</strong> les personnes inscrites sur ce planning sont susceptibles d\'être '
               . 'redemandées la semaine suivante.'
               . '</div>';

        return $html;
    }
}

if (!function_exists('famijobValidePlanningSemaine')) {
    /**
     * Valide la semaine et envoie les trois series de mails.
     *
     * @return array ['ok' => string[], 'ko' => string[], 'ignores' => string[]]
     *
     * ⚠️ L'ETAT EST ECRIT AVANT LES ENVOIS. Si un serveur mail tombe au milieu,
     * la semaine reste validee et le rapport dit ce qui n'est pas parti : c'est
     * rattrapable. L'inverse — des mails partis sur une semaine marquee « en
     * preparation » — ne l'est pas.
     */
    function famijobValidePlanningSemaine(PDO $db, DateTimeImmutable $weekStart, $userId)
    {
        famijobAssurePlanningSemaine($db);
        $weekEnd = $weekStart->modify('+6 days');
        $rapport = ['ok' => [], 'ko' => [], 'ignores' => []];

        $db->prepare(
            "INSERT INTO interim_planning_semaine (week_start, statut, valide_par_user_id, valide_le)
             VALUES (?, 'valide', ?, NOW())
             ON DUPLICATE KEY UPDATE statut = 'valide', valide_par_user_id = VALUES(valide_par_user_id),
                                     valide_le = VALUES(valide_le)"
        )->execute([$weekStart->format('Y-m-d'), (int) $userId > 0 ? (int) $userId : null]);

        $gens = famijobCreneauxParEtudiant($db, $weekStart);
        if (!$gens) {
            $rapport['ignores'][] = 'Aucune personne affectée cette semaine : aucun mail envoyé.';
            return $rapport;
        }

        // Ce qui est DEJA parti, et sous quelle forme. Une empreinte identique
        // = rien n'a change pour cette personne = on ne lui reecrit pas.
        $dejaEnvoye = famijobEmpreintesEnvoyees($db, $weekStart);
        $inchange = static function ($cible, $empreinte) use ($dejaEnvoye) {
            return isset($dejaEnvoye[$cible]) && $dejaEnvoye[$cible] === $empreinte;
        };

        $modeTest = famijobScheduleMailIsTestMode();
        $adresseTest = famijobScheduleMailTestRecipient();

        // Un seul endroit qui decide de l'adresse reellement utilisee : en mode
        // test tout est deroute, et le sujet le DIT — un mail de test qu'on
        // prend pour un vrai fait plus de degats qu'un envoi rate.
        $envoie = static function ($destinataires, $sujet, $corps, array $pieces = []) use ($modeTest, $adresseTest) {
            $reels = array_values(array_filter(array_map('trim', (array) $destinataires), static function ($m) {
                return $m !== '' && filter_var($m, FILTER_VALIDATE_EMAIL);
            }));
            if (!$reels) {
                return [false, 'aucune adresse valide'];
            }
            $vises = $modeTest ? array_values(array_filter([$adresseTest])) : $reels;
            if (!$vises) {
                return [false, 'mode test actif mais FAMIJOB_HORAIRE_MAIL_TEST_TO n\'est pas renseignée'];
            }
            if ($modeTest) {
                $sujet = '[TEST -> ' . implode(', ', $reels) . '] ' . $sujet;
            }

            $unEnvoye = false;
            $erreurs = [];
            foreach ($vises as $adresse) {
                if (sendMail($adresse, $sujet, $corps, true, $pieces)) {
                    $unEnvoye = true;
                } else {
                    $err = function_exists('getLastMailError') ? getLastMailError() : '';
                    $erreurs[] = $adresse . ' : ' . ($err !== '' ? $err : 'échec inconnu');
                }
            }
            return [$unEnvoye, implode(' | ', $erreurs)];
        };

        $periode = $weekStart->format('d/m/Y') . ' au ' . $weekEnd->format('d/m/Y');

        // ── 1. LES ETUDIANTS ────────────────────────────────────────────────
        foreach ($gens as $p) {
            if ($p['student_id'] <= 0 || $p['email'] === '') {
                $rapport['ignores'][] = $p['nom'] . ' : pas d\'adresse e-mail';
                continue;
            }

            // L'empreinte porte sur CE QU'IL VA LIRE : ses creneaux, tries. Si
            // on lui a deja envoye exactement ca, il n'apprendrait rien.
            $cible = 'etu:' . $p['student_id'];
            $lignes = [];
            foreach ($p['shifts'] as $s) {
                $lignes[] = $s['date'] . '|' . $s['horaire'] . '|' . $s['departement'];
            }
            sort($lignes);
            $empreinte = sha1(implode("\n", $lignes));

            if ($inchange($cible, $empreinte)) {
                $rapport['ignores'][] = $p['nom'] . ' : horaire inchangé';
                continue;
            }

            list($ok, $err) = $envoie(
                [$p['email']],
                'Ton horaire — semaine du ' . $periode,
                famijobMailEtudiantCorps($p, $weekStart)
            );
            if ($ok) {
                $rapport['ok'][] = $p['nom'];
                famijobNoteEnvoi($db, $weekStart, $cible, $empreinte);
            } else {
                $rapport['ko'][] = $p['nom'] . ' — ' . $err;
            }
        }

        // ── 2. LES AGENCES QUI ONT QUELQU'UN ────────────────────────────────
        // Regroupement par agence NORMALISEE : « Randstad » et « randstad »
        // sont la meme, et il ne faut pas lui ecrire deux fois.
        $parAgence = [];
        foreach ($gens as $p) {
            $agence = trim((string) $p['agence']);
            if ($agence === '' || famijobIsFamifloraAgency($agence)) {
                continue;   // interne : traite au point 3
            }
            $cle = mb_strtolower($agence);
            if (!isset($parAgence[$cle])) {
                $parAgence[$cle] = ['nom' => $agence, 'gens' => []];
            }
            $parAgence[$cle]['gens'][] = $p['nom'];
        }

        foreach ($parAgence as $bloc) {
            $destinataire = famijobResolveScheduleRecipient($db, $bloc['nom']);
            if (empty($destinataire['emails'])) {
                $rapport['ko'][] = 'Agence ' . $bloc['nom'] . ' — '
                    . ($destinataire['error'] !== '' ? $destinataire['error'] : 'aucune adresse connue');
                continue;
            }

            $donnees = famijobSemaineDonnees($db, $weekStart, [
                'agence'          => $bloc['nom'],
                'role'            => 'agence_interim',   // masque les noms des autres agences
                'seulementAgence' => true,               // et ne garde que ses creneaux
            ]);
            // L'empreinte de l'agence porte sur le CONTENU DU FICHIER qu'elle
            // recevrait. C'est plus juste qu'une liste de noms : un horaire
            // deplace d'une heure ne change pas la liste, mais change le fichier.
            $cibleAg = 'ag:' . mb_strtolower($bloc['nom']);
            $empreinteAg = sha1(json_encode($donnees));
            if ($inchange($cibleAg, $empreinteAg)) {
                $rapport['ignores'][] = 'Agence ' . $bloc['nom'] . ' : planning inchangé';
                continue;
            }

            $fichier = famijobSemaineXlsx($weekStart, $donnees['deptNames'], $donnees['byDept']);
            $pieces = [];
            if (is_string($fichier) && $fichier !== '') {
                $pieces[] = [
                    'nom'     => 'planning_' . $weekStart->format('Y-m-d') . '.xlsx',
                    'contenu' => $fichier,
                    'type'    => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];
            } else {
                $pieces[] = [
                    'nom'     => 'planning_' . $weekStart->format('Y-m-d') . '.csv',
                    'contenu' => famijobSemaineCsv($weekStart, $donnees['deptNames'], $donnees['byDept']),
                    'type'    => 'text/csv; charset=UTF-8',
                ];
            }

            list($ok, $err) = $envoie(
                $destinataire['emails'],
                'Planning validé — semaine du ' . $periode,
                famijobMailAgenceCorps($bloc['nom'], $bloc['gens'], $weekStart),
                $pieces
            );
            if ($ok) {
                $rapport['ok'][] = 'Agence ' . $bloc['nom'] . ' (' . count($bloc['gens']) . ')';
                famijobNoteEnvoi($db, $weekStart, $cibleAg, $empreinteAg);
            } else {
                $rapport['ko'][] = 'Agence ' . $bloc['nom'] . ' — ' . $err;
            }
        }

        // ── 3. LE SERVICE INTERNE : le classeur complet, sans commentaire ────
        $interne = famijobFamifloraFallbackEmail();
        if ($interne === '') {
            $rapport['ignores'][] = 'Envoi interne : FAMIJOB_HORAIRE_MAIL_FAMIFLORA n\'est pas renseignée';
        }

        // Variable dediee : « $donnees » servait deja dans la boucle des agences,
        // et la reutiliser ici rendait le code faux a la premiere relecture.
        $donneesInternes = null;
        $empreinteInterne = '';
        if ($interne !== '') {
            $donneesInternes = famijobSemaineDonnees($db, $weekStart, ['role' => 'admin']);
            $empreinteInterne = sha1(json_encode($donneesInternes));
            if ($inchange('interne', $empreinteInterne)) {
                $rapport['ignores'][] = 'Service interne : planning inchangé';
                $donneesInternes = null;
            }
        }

        if ($donneesInternes !== null) {
            $donnees = $donneesInternes;
            $fichier = famijobSemaineXlsx($weekStart, $donnees['deptNames'], $donnees['byDept']);
            $pieces = [[
                'nom'     => 'planning_complet_' . $weekStart->format('Y-m-d')
                             . (is_string($fichier) && $fichier !== '' ? '.xlsx' : '.csv'),
                'contenu' => (is_string($fichier) && $fichier !== '')
                             ? $fichier
                             : famijobSemaineCsv($weekStart, $donnees['deptNames'], $donnees['byDept']),
                'type'    => (is_string($fichier) && $fichier !== '')
                             ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                             : 'text/csv; charset=UTF-8',
            ]];

            $corps = '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;">Bonjour,</p>'
                   . '<p style="margin:0;font-size:16px;line-height:1.6;">Planning complet de la semaine du '
                   . e($periode) . ', en pièce jointe.</p>';

            list($ok, $err) = $envoie([$interne], 'Planning complet — semaine du ' . $periode, $corps, $pieces);
            if ($ok) {
                $rapport['ok'][] = 'Service interne';
                famijobNoteEnvoi($db, $weekStart, 'interne', $empreinteInterne);
            } else {
                $rapport['ko'][] = 'Service interne — ' . $err;
            }
        }

        try {
            $db->prepare('UPDATE interim_planning_semaine SET envois_ok = ?, envois_ko = ? WHERE week_start = ?')
               ->execute([count($rapport['ok']), count($rapport['ko']), $weekStart->format('Y-m-d')]);
        } catch (Exception $e) {
            // Le compteur n'est qu'un indicateur : son echec ne remet pas en
            // cause la validation ni les envois.
        }

        return $rapport;
    }
}
