<?php
// ============================================================
// LE CLASSEUR DE LA SEMAINE, EN FICHIER — une seule fabrique.
//
// Le meme tableau part par deux chemins : le bouton « Export Excel » d'un
// ecran, et les pieces jointes envoyees a la validation du planning. Ecrire
// deux fois la construction du fichier, c'est se garantir qu'un jour l'un des
// deux dira autre chose que l'autre — on l'a deja paye assez cher avec les
// pages jumelles du site.
//
// Ce fichier ne decide RIEN : ni qui a le droit d'exporter, ni ce qu'on masque.
// Il recoit des filtres et rend des octets. Les regles d'acces restent dans les
// pages, les regles de confidentialite dans includes/confidentialite.php.
// ============================================================

require_once __DIR__ . '/confidentialite.php';

if (!function_exists('famijobSemaineJours')) {
    /** Les sept jours de la semaine, du lundi au dimanche. */
    function famijobSemaineJours(DateTimeImmutable $weekStart)
    {
        $jours = [];
        for ($i = 0; $i < 7; $i++) {
            $jours[] = $weekStart->modify('+' . $i . ' days');
        }
        return $jours;
    }
}

if (!function_exists('famijobSemaineDateLisible')) {
    /** « lundi 10 août 2026 », sans dependre de l'extension intl. */
    function famijobSemaineDateLisible(DateTimeImmutable $d)
    {
        $jours = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
        $mois  = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
                  7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];
        return $jours[(int) $d->format('N')] . ' ' . $d->format('j') . ' ' . $mois[(int) $d->format('n')] . ' ' . $d->format('Y');
    }
}

if (!function_exists('famijobSemaineDonnees')) {
    /**
     * Le contenu du classeur : departement -> [jour0..jour6] -> une ligne par PLACE.
     *
     * @param array $opt
     *   'secteurs'      array  ne garder que ces secteurs (vide = tous)
     *   'departement'   string ne garder que ce departement (vide = tous)
     *   'agence'        string point de vue : masque les noms des autres agences
     *   'role'          string role de celui qui regarde (voir confidentialite.php)
     *   'seulementAgence' bool  ne garder QUE les creneaux tenus par cette agence
     *
     * @return array ['deptNames' => string[], 'byDept' => array]
     *
     * ⚠️ ON PART DES CRENEAUX, PAS DES AFFECTATIONS. Un LEFT JOIN, pour qu'un
     * creneau que personne n'occupe encore sorte quand meme : c'est la ligne
     * qu'on remplit a la main.
     */
    function famijobSemaineDonnees(PDO $db, DateTimeImmutable $weekStart, array $opt = [])
    {
        $weekEnd = $weekStart->modify('+6 days');

        $secteurs        = isset($opt['secteurs']) && is_array($opt['secteurs']) ? $opt['secteurs'] : [];
        $departement     = trim((string) ($opt['departement'] ?? ''));
        $agence          = trim((string) ($opt['agence'] ?? ''));
        $roleLecteur     = (string) ($opt['role'] ?? 'admin');
        $seulementAgence = !empty($opt['seulementAgence']);

        $indexParDate = [];
        foreach (famijobSemaineJours($weekStart) as $i => $d) {
            $indexParDate[$d->format('Y-m-d')] = $i;
        }

        $sql =
            "SELECT r.id AS request_id, r.shift_date, r.department_name, r.time_slot, r.seats_required,
                    a.id AS assignment_id, a.seat_number, a.student_id, a.external_name, a.agency_name,
                    u.nom AS student_nom, u.prenom AS student_prenom, u.interim AS student_interim
             FROM interim_shift_requests r
             LEFT JOIN interim_shift_assignments a ON a.request_id = r.id
             LEFT JOIN utilisateurs u ON u.id = a.student_id
             WHERE r.shift_date BETWEEN ? AND ?";

        // Uniquement les creneaux VALIDES : un planning qu'on diffuse ne doit pas
        // annoncer une place que personne n'a accordee.
        $colonnes = [];
        foreach ($db->query('SHOW COLUMNS FROM interim_shift_requests')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $colonnes[(string) $c['Field']] = true;
        }
        if (isset($colonnes['validation_status'])) {
            $sql .= " AND r.validation_status = 'approved'";
        }
        $sql .= ' ORDER BY r.department_name ASC, r.time_slot ASC, r.id ASC, a.seat_number ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute([$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
        $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Secteur et departement se RESOLVENT : `department_name` est du texte
        // libre qui peut porter l'un comme l'autre.
        if ($secteurs || $departement !== '') {
            require_once __DIR__ . '/grille_semaine.php';
            $rangement = grilleSemaineRangement($db);
            $cible = $departement !== '' ? grilleSemaineCle($departement) : '';
            $lignes = array_values(array_filter($lignes, static function ($r) use ($rangement, $secteurs, $cible) {
                $place = grilleSemaineResout((string) $r['department_name'], $rangement);
                if ($secteurs && !in_array($place['secteur'], $secteurs, true)) {
                    return false;
                }
                if ($cible !== '') {
                    return $place['sous'] !== '' && grilleSemaineCle($place['sous']) === $cible;
                }
                return true;
            }));
        }

        // Un creneau, ses places, ceux qui les occupent.
        $creneaux = [];
        foreach ($lignes as $r) {
            $rid = (int) $r['request_id'];
            if (!isset($creneaux[$rid])) {
                $creneaux[$rid] = [
                    'dept'    => trim((string) $r['department_name']),
                    'date'    => (string) $r['shift_date'],
                    'horaire' => trim((string) $r['time_slot']),
                    'places'  => max(1, (int) $r['seats_required']),
                    'gens'    => [],
                    'aNous'   => false,   // au moins une place tenue par l'agence demandee
                ];
            }
            if ($r['assignment_id'] === null) {
                continue;
            }

            if (empty($r['student_id'])) {
                $nom    = trim((string) ($r['external_name'] ?? ''));
                $agenceLigne = trim((string) ($r['agency_name'] ?? ''));
            } else {
                $nom = trim(trim((string) ($r['student_nom'] ?? '')) . ' ' . trim((string) ($r['student_prenom'] ?? '')));
                $agenceLigne = trim((string) ($r['student_interim'] ?? ''));
                if ($agenceLigne === '') {
                    $agenceLigne = trim((string) ($r['agency_name'] ?? ''));
                }
            }

            if (famijobMemeAgence($agenceLigne, $agence)) {
                $creneaux[$rid]['aNous'] = true;
            }

            $lecture = famijobNomLisible($nom, $agenceLigne, $roleLecteur, $agence);
            if ($lecture['masque']) {
                $nom = famijobLibelleOccupe() . ' (autre agence)';
                $agenceLigne = '';
            }

            $creneaux[$rid]['gens'][] = ['nom' => $nom, 'agence' => $agenceLigne];
        }

        $byDept = [];
        foreach ($creneaux as $c) {
            // Fichier destine a UNE agence : on ne garde que les creneaux ou elle
            // a quelqu'un. Lui envoyer la semaine entiere de Famiflora serait lui
            // demander de chercher ses gens dans le tas.
            if ($seulementAgence && !$c['aNous']) {
                continue;
            }
            if (!isset($indexParDate[$c['date']])) {
                continue;
            }
            $dept = $c['dept'] !== '' ? $c['dept'] : '(sans département)';
            $jour = $indexParDate[$c['date']];
            if (!isset($byDept[$dept])) {
                $byDept[$dept] = array_fill(0, 7, []);
            }

            $nbLignes = max($c['places'], count($c['gens']));
            for ($i = 0; $i < $nbLignes; $i++) {
                $g = $c['gens'][$i] ?? ['nom' => '', 'agence' => ''];
                $byDept[$dept][$jour][] = [
                    'horaire' => $c['horaire'],
                    'nom'     => $g['nom'],
                    'agence'  => $g['agence'],
                ];
            }
        }

        $deptNames = array_keys($byDept);
        usort($deptNames, static function ($a, $b) { return strcasecmp($a, $b); });

        return ['deptNames' => $deptNames, 'byDept' => $byDept];
    }
}

if (!function_exists('fjxCol')) {
    /** Lettre de colonne Excel a partir d'un index 1-based (1 -> A, 27 -> AA). */
    function fjxCol($n)
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26);
        }
        return $s;
    }
}

if (!function_exists('famijobSemaineChargePhpSpreadsheet')) {
    /** PhpSpreadsheet vit dans vendor/, dont l'emplacement change selon le deploiement. */
    function famijobSemaineChargePhpSpreadsheet()
    {
        if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            return true;
        }
        foreach ([
            dirname(__DIR__, 2) . '/vendor/autoload.php',
            dirname(__DIR__, 2) . '/public/vendor/autoload.php',
            dirname(__DIR__) . '/vendor/autoload.php',
        ] as $autoload) {
            if (is_file($autoload)) {
                require_once $autoload;
                break;
            }
        }
        return class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet');
    }
}

if (!function_exists('famijobSemaineXlsx')) {
    /**
     * Le classeur mis en forme, en octets. Null si PhpSpreadsheet manque —
     * l'appelant decide alors de se rabattre sur du CSV ou de renoncer.
     */
    function famijobSemaineXlsx(DateTimeImmutable $weekStart, array $deptNames, array $byDept)
    {
        if (!famijobSemaineChargePhpSpreadsheet()) {
            return null;
        }

        try {
            $days = famijobSemaineJours($weekStart);

            $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $ss->getActiveSheet();
            $sheet->setTitle('Matching');

            $fillSolid = \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID;
            $hCenter = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;
            $vCenter = \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER;
            $thin = \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN;

            // Motif repete pour chaque jour : horaire, I, EI, EFM, nom, agence.
            $widths = [10, 4, 4, 5, 22, 14];
            for ($d = 0; $d < 7; $d++) {
                for ($c = 0; $c < 6; $c++) {
                    $sheet->getColumnDimension(fjxCol($d * 6 + $c + 1))->setWidth($widths[$c]);
                }
            }

            $r = 1;

            foreach ($days as $i => $d) {
                $c1 = $i * 6 + 1;
                $sheet->setCellValue(fjxCol($c1) . $r, ucfirst(famijobSemaineDateLisible($d)));
                $sheet->mergeCells(fjxCol($c1) . $r . ':' . fjxCol($c1 + 5) . $r);
            }
            $sheet->getStyle('A' . $r . ':' . fjxCol(42) . $r)->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => $fillSolid, 'startColor' => ['rgb' => '264E35']],
                'alignment' => ['horizontal' => $hCenter, 'vertical' => $vCenter],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(22);
            $r++;

            $heads = ['horaire', 'I', 'EI', 'EFM', 'nom', 'agence'];
            for ($d = 0; $d < 7; $d++) {
                foreach ($heads as $c => $h) {
                    $sheet->setCellValue(fjxCol($d * 6 + $c + 1) . $r, $h);
                }
            }
            $sheet->getStyle('A' . $r . ':' . fjxCol(42) . $r)->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '21362A']],
                'fill' => ['fillType' => $fillSolid, 'startColor' => ['rgb' => 'D9E7DD']],
                'alignment' => ['horizontal' => $hCenter, 'vertical' => $vCenter],
            ]);
            $r++;

            $headerRows = $r - 1;

            foreach ($deptNames as $dept) {
                $sheet->setCellValue('A' . $r, $dept);
                $sheet->mergeCells('A' . $r . ':' . fjxCol(42) . $r);
                $sheet->getStyle('A' . $r . ':' . fjxCol(42) . $r)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '6B4E00']],
                    'fill' => ['fillType' => $fillSolid, 'startColor' => ['rgb' => 'FDE9A9']],
                    'alignment' => ['vertical' => $vCenter],
                ]);
                $sheet->getStyle('A' . $r)->getAlignment()->setIndent(1);
                $sheet->getRowDimension($r)->setRowHeight(18);
                $r++;

                $maxRows = 0;
                foreach ($byDept[$dept] as $dayList) {
                    $maxRows = max($maxRows, count($dayList));
                }

                $firstDataRow = $r;
                for ($rowIdx = 0; $rowIdx < $maxRows; $rowIdx++) {
                    for ($dayIdx = 0; $dayIdx < 7; $dayIdx++) {
                        $a = $byDept[$dept][$dayIdx][$rowIdx] ?? null;
                        if ($a === null) {
                            continue;
                        }
                        $base = $dayIdx * 6 + 1;
                        $sheet->setCellValue(fjxCol($base) . $r, $a['horaire']);
                        $sheet->setCellValue(fjxCol($base + 4) . $r, $a['nom']);
                        $sheet->setCellValue(fjxCol($base + 5) . $r, $a['agence']);
                    }
                    $r++;
                }

                if ($maxRows > 0) {
                    $sheet->getStyle('A' . $firstDataRow . ':' . fjxCol(42) . ($r - 1))->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => $thin, 'color' => ['rgb' => 'DDE6DF']]],
                        'font' => ['size' => 10],
                        // Centre : des horaires et des noms cales a gauche dans des
                        // colonnes larges laissent un vide qui casse la lecture.
                        'alignment' => ['horizontal' => $hCenter, 'vertical' => $vCenter],
                    ]);
                    // Le nom du departement reste a gauche : c'est un libelle de
                    // ligne, pas une donnee du tableau.
                    $sheet->getStyle('A' . $firstDataRow . ':A' . ($r - 1))
                          ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    for ($dayIdx = 1; $dayIdx < 7; $dayIdx += 2) {
                        $c1 = fjxCol($dayIdx * 6 + 1);
                        $c2 = fjxCol($dayIdx * 6 + 6);
                        $sheet->getStyle($c1 . $firstDataRow . ':' . $c2 . ($r - 1))
                            ->getFill()->setFillType($fillSolid)->getStartColor()->setRGB('FBF1E8');
                    }
                }

                $r++;
            }

            $lastRow = max($headerRows, $r - 1);

            for ($d = 1; $d < 7; $d++) {
                $col = fjxCol($d * 6 + 1);
                $sheet->getStyle($col . '1:' . $col . $lastRow)->getBorders()->getLeft()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('9BB0A3');
            }
            $sheet->getStyle('A1:' . fjxCol(42) . $lastRow)->getBorders()->getOutline()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
                ->getColor()->setRGB('264E35');

            $sheet->freezePane('A' . ($headerRows + 1));

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
            ob_start();
            $writer->save('php://output');
            $bytes = ob_get_clean();
            $ss->disconnectWorksheets();
            unset($ss);
            return $bytes;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('famijobSemaineCsv')) {
    /**
     * Le repli quand PhpSpreadsheet manque : CSV point-virgule, BOM UTF-8 —
     * Excel l'ouvre directement. Sans mise en forme, mais avec les memes
     * donnees, ce qui est le seul point qui compte quand rien d'autre ne marche.
     */
    function famijobSemaineCsv(DateTimeImmutable $weekStart, array $deptNames, array $byDept)
    {
        $days = famijobSemaineJours($weekStart);
        $rows = [];

        $ligne = [];
        foreach ($days as $d) {
            $ligne[] = famijobSemaineDateLisible($d);
            for ($i = 0; $i < 5; $i++) { $ligne[] = ''; }
        }
        $rows[] = $ligne;

        $ligne = [];
        for ($i = 0; $i < 7; $i++) {
            foreach (['horaire', 'I', 'EI', 'EFM', 'nom', 'agence'] as $h) { $ligne[] = $h; }
        }
        $rows[] = $ligne;

        foreach ($deptNames as $dept) {
            $ligne = [];
            for ($i = 0; $i < 7; $i++) {
                $ligne[] = $dept;
                for ($j = 0; $j < 5; $j++) { $ligne[] = ''; }
            }
            $rows[] = $ligne;

            $maxRows = 0;
            foreach ($byDept[$dept] as $dayList) {
                $maxRows = max($maxRows, count($dayList));
            }

            for ($rowIdx = 0; $rowIdx < $maxRows; $rowIdx++) {
                $ligne = [];
                for ($dayIdx = 0; $dayIdx < 7; $dayIdx++) {
                    $a = $byDept[$dept][$dayIdx][$rowIdx] ?? null;
                    if ($a === null) {
                        for ($j = 0; $j < 6; $j++) { $ligne[] = ''; }
                    } else {
                        $ligne[] = $a['horaire'];
                        $ligne[] = ''; $ligne[] = ''; $ligne[] = '';
                        $ligne[] = $a['nom'];
                        $ligne[] = $a['agence'];
                    }
                }
                $rows[] = $ligne;
            }

            $rows[] = array_fill(0, 42, '');
        }

        $echappe = static function ($v) {
            $v = (string) $v;
            if ($v === '') { return ''; }
            return (strpbrk($v, ";\"\r\n") !== false) ? '"' . str_replace('"', '""', $v) . '"' : $v;
        };

        $sortie = "\xEF\xBB\xBF";
        foreach ($rows as $ligne) {
            $sortie .= implode(';', array_map($echappe, $ligne)) . "\r\n";
        }
        return $sortie;
    }
}
