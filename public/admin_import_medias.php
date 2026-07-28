<?php
// ============================================================
//  IMPORT PAR LOT DES MÉDIAS LEGACY  (admin)
//
//  Migre les PDF servis en statique depuis public/ et les vidéos hébergées
//  chez YouTube vers le volume, puis les rattache au bon module.
//
//  Le routage est AUTOMATIQUE : chaque fichier déposé est reconnu par son nom
//  (l'identifiant YouTube entre crochets pour les vidéos), croisé avec la table
//  includes/medias_legacy_map.php, puis relié au module via `modules.link`.
//  Aucune sélection de module à faire à la main.
//
//  Deux temps, volontairement séparés :
//    1. TÉLÉVERSER  — rapide, borné par la bande passante.
//    2. TRAITER     — un module par requête (extraction Claude, orthographe,
//                     traduction NL). Long : le découpage évite le time-out.
// ============================================================

require_once 'config.php';
verifierConnexion($db);
require_once 'includes/csrf.php';
require_once 'includes/modules.php';
require_once 'includes/module_storage.php';
require_once 'includes/medias_legacy_map.php';
require_once 'includes/i18n_nl.php';

if ((string) ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

ensureModulesTable($db);
if (function_exists('nlEnsureColumns')) { nlEnsureColumns($db); }

// Colonne du PDF néerlandais fourni par l'humain (cf. gerbeur : le même document
// existe déjà en NL, on ne le fait donc pas retraduire). Nommée d'après la
// convention du projet (sub_nl_path). Migration idempotente, comme ailleurs.
try {
    if (!$db->query("SHOW COLUMNS FROM modules LIKE 'pdf_nl_path'")->fetch()) {
        $db->exec("ALTER TABLE modules ADD COLUMN pdf_nl_path VARCHAR(255) NULL");
    }
} catch (Exception $e) { /* migration non bloquante */ }

// PDF volontairement laissés de côté : ils ne sont référencés par aucune page.
// Listés ici pour qu'ils apparaissent comme un choix, pas comme un oubli.
const FAMI_IMPORT_IGNORES = ['engrais.pdf', 'zoobase.pdf'];

/**
 * Index des modules par page cible.
 * `modules.link` peut porter une query ('formation.php?vue=presentiel') : on
 * compare sur le nom de fichier seul, sinon aucune correspondance ne sortirait.
 */
function famiImportModulesByPage(PDO $db)
{
    $out = [];
    try {
        $rows = $db->query("SELECT id, nom, link, roles, parent_id FROM modules WHERE link IS NOT NULL AND link <> ''")
                   ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return $out;
    }
    foreach ($rows as $r) {
        $page = strtok((string) $r['link'], '?');
        $page = basename((string) $page);
        if ($page === '') { continue; }
        $out[$page][] = $r;
    }
    return $out;
}

/** Sous-module d'un type donné ('ecrit' | 'video') sous un parent. */
function famiImportChild(PDO $db, $parentId, $kind)
{
    try {
        $st = $db->prepare("SELECT * FROM modules WHERE parent_id = ? AND content_kind = ? LIMIT 1");
        $st->execute([(int) $parentId, (string) $kind]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * MODULES DÉDIÉS — cas où une page porte plusieurs médias du même type.
 * Un module ne peut porter qu'UN sous-module 'ecrit' et UN 'video' : sans cette
 * table, le second fichier écraserait le premier. Chaque entrée décrit le
 * sous-module à créer, identifié par le nom du PDF ou l'identifiant de la vidéo.
 *
 * `roles` : liste de profils, ou '@others:a,b' = tous les profils SAUF a et b.
 */
function famiImportSpecialTargets()
{
    return [
        // Livret d'accueil : deux versions du document selon le public.
        'os.pdf' => [
            'page' => 'view-pdf-onboarding.php', 'kind' => 'pdf',
            'nom' => "Livret d'accueil — Étudiant", 'nom_nl' => "Onthaalbrochure — Student",
            'roles' => ['etudiant', 'beta'],
        ],
        'op.pdf' => [
            'page' => 'view-pdf-onboarding.php', 'kind' => 'pdf',
            'nom' => "Livret d'accueil", 'nom_nl' => "Onthaalbrochure",
            'roles' => '@others:etudiant,beta',
        ],
        // Gerbeur : le MÊME document en deux langues, pas deux contenus. Un seul
        // module ; le NL humain remplit contenu_ia_nl et aucune traduction machine
        // n'est lancée. `nom` absent = sous-module générique du parent.
        'gerbeurnl.pdf' => [
            'page' => 'gerbeur.php', 'kind' => 'pdf_nl',
        ],
        // Lollyland : deux vidéos distinctes, donc deux modules vidéo.
        'iqxs2hie510' => [
            'page' => 'lollyland_methode_travail.php', 'kind' => 'video',
            'nom' => 'Méthode de travail', 'nom_nl' => 'Werkmethode', 'roles' => [],
        ],
        'xDIs21sERCg' => [
            'page' => 'lollyland_methode_travail.php', 'kind' => 'video',
            'nom' => 'Règles Lollyland', 'nom_nl' => 'Lollyland-regels', 'roles' => [],
        ],
    ];
}

/** Traduit la clause `roles` d'un module dédié en valeur pour la colonne. */
function famiImportResolveRoles(PDO $db, $spec, $fallback = '')
{
    $r = $spec['roles'] ?? [];
    if (is_string($r) && strpos($r, '@others:') === 0) {
        $excl = array_map('trim', explode(',', substr($r, 8)));
        $all = array_keys(moduleProfiles($db));
        return implode(',', array_values(array_diff($all, $excl)));
    }
    if (is_array($r) && $r) { return implode(',', $r); }
    return (string) $fallback; // vide = tous les profils
}

/** Sous-module dédié repéré par son nom — lecture seule (pour l'affichage du plan). */
function famiImportChildByName(PDO $db, $parentId, $nom)
{
    try {
        $st = $db->prepare("SELECT * FROM modules WHERE parent_id = ? AND nom = ? LIMIT 1");
        $st->execute([(int) $parentId, (string) $nom]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/** Sous-module dédié (repéré par son nom sous le parent) : le trouve ou le crée. */
function famiImportFindOrCreateTarget(PDO $db, $parentId, array $spec, $parentRoles)
{
    $parentId = (int) $parentId;
    try {
        $st = $db->prepare("SELECT * FROM modules WHERE parent_id = ? AND nom = ? LIMIT 1");
        $st->execute([$parentId, (string) $spec['nom']]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) { return $row; }
    } catch (Exception $e) {
        return null;
    }
    $roles = famiImportResolveRoles($db, $spec, $parentRoles);
    $icon = ($spec['kind'] === 'video') ? '🎬' : '📄';
    $db->prepare("INSERT INTO modules (nom, nom_nl, is_container, parent_id, icon, roles, is_active, contenu_by, content_kind)
                  VALUES (?, ?, 0, ?, ?, ?, 1, ?, ?)")
       ->execute([
           (string) $spec['nom'], (string) ($spec['nom_nl'] ?? $spec['nom']), $parentId, $icon, $roles,
           ((int) ($_SESSION['user_id'] ?? 0)) ?: null, ($spec['kind'] === 'video') ? 'video' : 'ecrit',
       ]);
    $st = $db->prepare("SELECT * FROM modules WHERE id = ? LIMIT 1");
    $st->execute([(int) $db->lastInsertId()]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Reconnaît le média visé par un fichier déposé, d'après son seul nom.
 * @return array{page:string,kind:string,ref:string}|null
 */
function famiImportRecognize($filename)
{
    $base = basename((string) $filename);
    $map = famiLegacyMediaMap();
    $specials = famiImportSpecialTargets();

    // Un module dédié FIXE la page cible, et prime sur le balayage de la table.
    // Sans ça, os.pdf/op.pdf — présents à la fois sous pdf-onboarding.php (le
    // script qui sert le fichier) et view-pdf-onboarding.php (la page du module)
    // — renverraient la mauvaise page, le module dédié ne correspondrait plus et
    // le fichier finirait refusé en « rattachement manuel ».
    $vidId = preg_match('/\[([A-Za-z0-9_-]{6,})\]/', $base, $mm) ? $mm[1] : null;
    foreach ([$base, $vidId] as $ref) {
        if ($ref !== null && isset($specials[$ref])) {
            return ['page' => $specials[$ref]['page'], 'kind' => $specials[$ref]['kind'], 'ref' => $ref];
        }
    }

    // Vidéo : l'identifiant YouTube entre crochets est la clé fiable — le reste
    // du nom peut avoir été renommé sans conséquence.
    if (preg_match('/\[([A-Za-z0-9_-]{6,})\]/', $base, $m)) {
        foreach ($map as $page => $entry) {
            foreach ($entry['videos'] as $v) {
                if ($v['id'] === $m[1]) {
                    return ['page' => $page, 'kind' => 'video', 'ref' => $v['id']];
                }
            }
        }
    }

    // PDF : correspondance sur le nom exact (insensible à la casse).
    foreach ($map as $page => $entry) {
        foreach ($entry['pdfs'] as $p) {
            if (strcasecmp($p, $base) === 0) {
                return ['page' => $page, 'kind' => 'pdf', 'ref' => $p];
            }
        }
    }
    return null;
}

$flash = [];

// ------------------------------------------------------------------
//  ACTION 1 — TÉLÉVERSEMENT : range les fichiers et les rattache.
// ------------------------------------------------------------------
if (($_POST['action'] ?? '') === 'upload') {
    requireValidCSRF();
    @set_time_limit(0);

    $byPage = famiImportModulesByPage($db);
    $files = $_FILES['medias'] ?? null;
    $count = $files ? count((array) $files['name']) : 0;

    // La version NÉERLANDAISE d'un document se greffe sur le sous-module que crée
    // sa version FR : elle doit donc passer APRÈS lui. On ne peut pas compter sur
    // l'ordre d'envoi du navigateur (il suit l'ordre de sélection, pas forcément
    // l'alphabet), alors on force le classement ici.
    $order = ($count > 0) ? range(0, $count - 1) : [];
    usort($order, function ($a, $b) use ($files) {
        $rang = function ($i) use ($files) {
            $hit = famiImportRecognize((string) $files['name'][$i]);
            return (($hit['kind'] ?? '') === 'pdf_nl') ? 1 : 0;
        };
        $ra = $rang($a);
        $rb = $rang($b);
        return ($ra === $rb) ? ($a <=> $b) : ($ra <=> $rb);
    });

    foreach ($order as $i) {
        $name = (string) $files['name'][$i];
        $tmp  = (string) $files['tmp_name'][$i];
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $flash[] = ['ko', $name, "téléversement interrompu (code " . (int) $files['error'][$i] . ")"];
            continue;
        }
        if (in_array(basename($name), FAMI_IMPORT_IGNORES, true)) {
            $flash[] = ['skip', $name, "écarté volontairement (rattaché à aucune page)"];
            continue;
        }

        $hit = famiImportRecognize($name);
        if (!$hit) {
            $flash[] = ['ko', $name, "inconnu de la table de correspondance"];
            continue;
        }
        $mods = $byPage[$hit['page']] ?? [];
        if (!$mods) {
            $flash[] = ['ko', $name, "aucun module ne pointe vers " . $hit['page']];
            continue;
        }
        $mod = $mods[0];
        $parentId = (int) $mod['id'];
        $slug = moduleFileSlug($mod['nom'] ?? $hit['page']);

        // Un module ne porte qu'UN sous-module 'ecrit' et UN 'video'. Quand une page
        // référence plusieurs médias du même type, le fichier va dans un sous-module
        // DÉDIÉ (livret Étudiant / autres profils, les 2 vidéos Lollyland) — sinon le
        // second écraserait le premier en silence.
        $spec = famiImportSpecialTargets()[$hit['ref']] ?? null;
        $target = null;
        if ($spec && $spec['page'] === $hit['page'] && !empty($spec['nom'])) {
            $target = famiImportFindOrCreateTarget($db, $parentId, $spec, (string) ($mod['roles'] ?? ''));
            if (!$target) {
                $flash[] = ['ko', $name, "création du module dédié « " . $spec['nom'] . " » impossible"];
                continue;
            }
        } elseif (!$spec) {
            $entry = famiLegacyMediaMap()[$hit['page']] ?? ['pdfs' => [], 'videos' => []];
            $sameKind = $hit['kind'] === 'pdf' ? count($entry['pdfs']) : count($entry['videos']);
            if ($sameKind > 1) {
                $flash[] = ['warn', $name, $hit['page'] . " porte " . $sameKind . " médias de ce type sans module dédié — à rattacher à la main"];
                continue;
            }
        }

        if ($hit['kind'] === 'pdf_nl') {
            // Version NÉERLANDAISE humaine du même document : elle se range à côté du
            // FR, sur le sous-module existant. Aucune traduction machine ne sera faite.
            $key = famiStoreUploadedFileAt($tmp, $name, ['application/pdf' => 'pdf'], 60 * 1024 * 1024, 'pdf', $slug . '-guide-nl');
            if ($key === null) {
                $flash[] = ['ko', $name, "refusé (format ou taille)"];
                continue;
            }
            $child = famiImportChild($db, $parentId, 'ecrit');
            if (!$child) {
                $flash[] = ['warn', $name, "dépose d'abord la version FR : le sous-module n'existe pas encore"];
                volumeUnlink($key);
                continue;
            }
            if (!empty($child['pdf_nl_path'])) { volumeUnlink((string) $child['pdf_nl_path']); }
            $db->prepare("UPDATE modules SET pdf_nl_path = ?, contenu_ia_nl = NULL, nl_hash = NULL WHERE id = ?")
               ->execute([$key, (int) $child['id']]);
            $flash[] = ['ok', $name, "version NL rattachée à « " . (string) $mod['nom'] . " » (pas de traduction machine)"];

        } elseif ($hit['kind'] === 'pdf') {
            $key = famiStoreUploadedFileAt($tmp, $name, ['application/pdf' => 'pdf'], 60 * 1024 * 1024, 'pdf', $slug . '-guide');
            if ($key === null) {
                $flash[] = ['ko', $name, "refusé (format ou taille)"];
                continue;
            }
            $child = $target ?: famiImportChild($db, $parentId, 'ecrit');
            if ($child) {
                if (!empty($child['pdf_path'])) { volumeUnlink((string) $child['pdf_path']); }
                $db->prepare("UPDATE modules SET pdf_path = ?, uniformized = 0, contenu_ia = NULL,
                                contenu_ia_nl = NULL, quiz_json_nl = NULL, nl_hash = NULL WHERE id = ?")
                   ->execute([$key, (int) $child['id']]);
            } else {
                $db->prepare("INSERT INTO modules (nom, nom_nl, is_container, parent_id, icon, roles, is_active, pdf_path, uniformized, contenu_by, content_kind)
                              VALUES (?, ?, 0, ?, '📄', ?, 1, ?, 0, ?, 'ecrit')")
                   ->execute(['Guide', 'Gids', $parentId, (string) ($mod['roles'] ?? ''), $key, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
            }
            $db->prepare("UPDATE modules SET is_container = 1 WHERE id = ?")->execute([$parentId]);
            $flash[] = ['ok', $name, "rattaché à « " . (string) $mod['nom'] . " » → "
                . ($target ? "module dédié « " . (string) $target['nom'] . " » (" . (((string) $target['roles']) !== '' ? (string) $target['roles'] : 'tous profils') . ")" : "guide")];

        } else {
            $key = famiStoreUploadedFileAt($tmp, $name, ['video/mp4' => 'mp4', 'video/quicktime' => 'mov'], 1024 * 1024 * 1024, 'video_raw', $slug . '-video');
            if ($key === null) {
                $flash[] = ['ko', $name, "refusé (format ou taille)"];
                continue;
            }
            $child = $target ?: famiImportChild($db, $parentId, 'video');
            if ($child) {
                foreach (['video_path', 'video_src_path'] as $c) {
                    if (!empty($child[$c])) { volumeUnlink((string) $child[$c]); }
                }
                $vidId = (int) $child['id'];
                $db->prepare("UPDATE modules SET video_src_path = ?, video_path = NULL, video_status = 'processing' WHERE id = ?")
                   ->execute([$key, $vidId]);
            } else {
                $db->prepare("INSERT INTO modules (nom, nom_nl, is_container, parent_id, icon, roles, is_active, video_src_path, video_status, contenu_by, content_kind)
                              VALUES (?, ?, 0, ?, '🎬', ?, 1, ?, 'processing', ?, 'video')")
                   ->execute(['Vidéo', 'Video', $parentId, (string) ($mod['roles'] ?? ''), $key, (int) ($_SESSION['user_id'] ?? 0) ?: null]);
                $vidId = (int) $db->lastInsertId();
            }
            $db->prepare("UPDATE modules SET is_container = 1 WHERE id = ?")->execute([$parentId]);
            // Transcodage 720p + transcription Whisper, en tâche de fond.
            spawnVideoTranscode($key, $vidId);
            $flash[] = ['ok', $name, "rattaché à « " . (string) $mod['nom'] . " » → "
                . ($target ? "module dédié « " . (string) $target['nom'] . " »" : "vidéo") . ", transcodage lancé"];
        }

        if (count($mods) > 1) {
            $flash[] = ['warn', $name, count($mods) . " modules pointent vers " . $hit['page'] . " — seul « " . (string) $mod['nom'] . " » a reçu le fichier"];
        }
    }
}

// ------------------------------------------------------------------
//  ACTION 2 — TRAITEMENT IA d'UN module (une requête = un module).
//  Extraction Claude -> orthographe -> traduction NL, sans relecture.
// ------------------------------------------------------------------
if (($_POST['action'] ?? '') === 'process') {
    requireValidCSRF();
    @set_time_limit(0);
    $guideId = (int) ($_POST['guide_id'] ?? 0);

    $st = $db->prepare("SELECT id, parent_id, nom, description, pdf_path, pdf_nl_path, quiz_json FROM modules WHERE id = ? LIMIT 1");
    $st->execute([$guideId]);
    $g = $st->fetch(PDO::FETCH_ASSOC);

    if (!$g || empty($g['pdf_path'])) {
        $flash[] = ['ko', 'module #' . $guideId, "aucun PDF rattaché"];
    } else {
        require_once 'includes/ia_settings.php';
        require_once 'includes/ai_uniformise.php';
        $res = aiUniformisePdf($db, moduleFileAbsPath($g['pdf_path']), (string) $g['pdf_path']);
        if (empty($res['ok'])) {
            $flash[] = ['ko', 'module #' . $guideId, "extraction IA échouée : " . (string) ($res['error'] ?? '?')];
        } else {
            $lang = (($res['lang'] ?? 'fr') === 'nl') ? 'nl' : 'fr';
            $db->prepare("UPDATE modules SET contenu_ia = ?, contenu_images = ?, source_lang = ?, uniformized = 1 WHERE id = ?")
               ->execute([
                   $res['text'],
                   !empty($res['images']) ? json_encode($res['images']) : null,
                   $lang,
                   $guideId,
               ]);
            require_once 'includes/ia_usage.php';
            iaLogUsage($db, (int) ($_SESSION['user_id'] ?? 0), 'uniformise', $res['model'], $res['in'], $res['out'], $res['cost_eur'], $guideId);

            $cout = (float) $res['cost_eur'];

            if (!empty($g['pdf_nl_path'])) {
                // PAIRE BILINGUE (gerbeur) : le NL est un document humain, pas une
                // traduction machine. On extrait les DEUX et on corrige l'orthographe
                // dans chaque langue — surtout PAS famiFinalValidation(), qui appelle
                // nlSyncModule() en force et écraserait le NL humain.
                $msg = '';
                $prFr = function_exists('nlProofreadBlocksJson') ? nlProofreadBlocksJson($db, (string) $res['text'], $lang) : null;
                $texteFr = (!empty($prFr['ok']) && trim((string) $prFr['json']) !== '') ? (string) $prFr['json'] : (string) $res['text'];
                if ($texteFr !== (string) $res['text']) {
                    $db->prepare("UPDATE modules SET contenu_ia = ? WHERE id = ?")->execute([$texteFr, $guideId]);
                    $msg .= " ✍️ Orthographe " . $lang . " vérifiée.";
                }

                $resNl = aiUniformisePdf($db, moduleFileAbsPath($g['pdf_nl_path']), (string) $g['pdf_nl_path']);
                if (empty($resNl['ok'])) {
                    $msg .= " ⚠️ Extraction du PDF néerlandais échouée : " . (string) ($resNl['error'] ?? '?');
                } else {
                    $cout += (float) $resNl['cost_eur'];
                    iaLogUsage($db, (int) ($_SESSION['user_id'] ?? 0), 'uniformise', $resNl['model'], $resNl['in'], $resNl['out'], $resNl['cost_eur'], $guideId);
                    $prNl = function_exists('nlProofreadBlocksJson') ? nlProofreadBlocksJson($db, (string) $resNl['text'], 'nl') : null;
                    $texteNl = (!empty($prNl['ok']) && trim((string) $prNl['json']) !== '') ? (string) $prNl['json'] : (string) $resNl['text'];

                    // nl_hash calé sur l'ORIGINAL tel qu'il vient d'être écrit : le NL est
                    // considéré à jour, donc aucune synchro ultérieure ne le retraduira.
                    $hash = hash('sha256', trim((string) ($g['nom'] ?? '')) . '|' . trim((string) ($g['description'] ?? ''))
                        . '|' . $texteFr . '|' . (string) ($g['quiz_json'] ?? ''));
                    $db->prepare("UPDATE modules SET contenu_ia_nl = ?, nl_hash = ? WHERE id = ?")
                       ->execute([$texteNl, $hash, $guideId]);
                    $msg .= " 🇳🇱 Version néerlandaise extraite du document humain et relue (aucune traduction machine).";
                }
            } else {
                // Orthographe + traduction vers l'autre langue, en direct (pas de relecture).
                $msg = function_exists('famiFinalValidation')
                    ? famiFinalValidation($db, $guideId, (int) ($_SESSION['user_id'] ?? 0), true)
                    : '';
            }
            $flash[] = ['ok', 'module #' . $guideId, "extrait en " . $lang . " (≈ " . number_format($cout, 3) . " €)." . $msg];
        }
    }
}

// ------------------------------------------------------------------
//  PLAN : ce qui est attendu, ce qui est déjà en place.
// ------------------------------------------------------------------
$map = famiLegacyMediaMap();
$byPage = famiImportModulesByPage($db);
$plan = [];
$stat = ['pages' => 0, 'sansModule' => 0, 'pdfOk' => 0, 'pdfTodo' => 0, 'vidOk' => 0, 'vidTodo' => 0, 'iaTodo' => 0, 'manuel' => 0];

foreach ($map as $page => $entry) {
    $mods = $byPage[$page] ?? [];
    $row = ['page' => $page, 'mods' => $mods, 'items' => []];
    $stat['pages']++;
    if (!$mods) { $stat['sansModule']++; }

    $parentId = $mods ? (int) $mods[0]['id'] : 0;
    $guide = $parentId ? famiImportChild($db, $parentId, 'ecrit') : null;
    $video = $parentId ? famiImportChild($db, $parentId, 'video') : null;

    // Un média couvert par un module DÉDIÉ lit l'état de ce module-là. Les autres
    // lisent le sous-module générique ; s'ils sont plusieurs du même type sans
    // module dédié, ils restent en rattachement manuel (cf. garde-fou à l'upload).
    $specials = famiImportSpecialTargets();
    $manualPdf = count($entry['pdfs']) > 1;
    $manualVid = count($entry['videos']) > 1;

    foreach ($entry['pdfs'] as $p) {
        $spec = ($specials[$p] ?? null);
        // Même fichier référencé par plusieurs pages (le script qui le sert ET la
        // page du module) : seule la page désignée par le module dédié compte.
        if ($spec && $spec['page'] !== $page) { continue; }
        $estNl = ($spec && ($spec['kind'] ?? '') === 'pdf_nl'); // version NL humaine
        $dedie = ($spec && !empty($spec['nom']) && $parentId) ? famiImportChildByName($db, $parentId, $spec['nom']) : null;
        $cible = (!empty($spec['nom'])) ? $dedie : $guide;

        // La version NL vit sur la MÊME ligne que le FR, dans sa propre colonne :
        // son état se lit donc sur pdf_nl_path / contenu_ia_nl, pas sur pdf_path.
        $done = $cible && !empty($cible[$estNl ? 'pdf_nl_path' : 'pdf_path']);
        $ia = $cible && !empty($cible[$estNl ? 'contenu_ia_nl' : 'contenu_ia']);
        $nl = $estNl ? $ia : ($cible && !empty($cible['contenu_ia_nl']));
        $row['items'][] = ['type' => 'pdf', 'nom' => $p, 'done' => $done, 'ia' => $ia, 'nl' => $nl,
                           'cible' => $spec['nom'] ?? ($estNl ? 'version néerlandaise (même module)' : null),
                           'roles' => (!empty($spec['nom'])) ? famiImportResolveRoles($db, $spec) : null,
                           'manual' => ($manualPdf && !$spec),
                           // Le bouton « Traiter » du FR couvre déjà l'extraction du NL.
                           'guide_id' => ($estNl || !$cible) ? 0 : (int) $cible['id']];
        $done ? $stat['pdfOk']++ : $stat['pdfTodo']++;
        if ($done && !$ia) { $stat['iaTodo']++; }
        if ($manualPdf && !$spec) { $stat['manuel']++; }
    }
    foreach ($entry['videos'] as $v) {
        $spec = ($specials[$v['id']] ?? null);
        if ($spec && $spec['page'] !== $page) { continue; }
        $dedie = ($spec && !empty($spec['nom']) && $parentId) ? famiImportChildByName($db, $parentId, $spec['nom']) : null;
        $cible = (!empty($spec['nom'])) ? $dedie : $video;
        $done = $cible && (!empty($cible['video_path']) || !empty($cible['video_src_path']));
        $row['items'][] = ['type' => 'video', 'nom' => $v['file'], 'id' => $v['id'], 'done' => $done,
                           'cible' => $spec['nom'] ?? null,
                           'manual' => ($manualVid && !$spec), 'statut' => $cible['video_status'] ?? null];
        $done ? $stat['vidOk']++ : $stat['vidTodo']++;
        if ($manualVid && !$spec) { $stat['manuel']++; }
    }
    $plan[] = $row;
}

$pageTitle = 'Import des médias legacy';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; margin: 0; background: #f5f6f8; color: #1c1e21; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 60px; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        .sub { color: #65676b; margin: 0 0 24px; }
        .card { background: #fff; border: 1px solid #dfe1e5; border-radius: 12px; padding: 18px; margin-bottom: 18px; }
        .stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .stat { background: #fff; border: 1px solid #dfe1e5; border-radius: 10px; padding: 10px 14px; min-width: 120px; }
        .stat b { display: block; font-size: 1.4rem; }
        .stat span { color: #65676b; font-size: .8rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eceef1; vertical-align: top; }
        th { background: #f0f2f5; font-weight: 600; }
        .tag { display: inline-block; padding: 1px 7px; border-radius: 20px; font-size: .75rem; font-weight: 600; }
        .t-ok { background: #e3f5e8; color: #1a7f37; }
        .t-todo { background: #fdf0e3; color: #9a5b00; }
        .t-ko { background: #fdeaea; color: #b42318; }
        .t-warn { background: #fff8e1; color: #8a6d00; }
        .t-skip { background: #eef0f3; color: #65676b; }
        .drop { border: 2px dashed #b9bec4; border-radius: 12px; padding: 26px; text-align: center; background: #fafbfc; }
        .btn { border: 0; border-radius: 8px; padding: 9px 16px; font-weight: 600; cursor: pointer; font-size: .9rem; }
        .btn-go { background: #1877f2; color: #fff; }
        .btn-sm { background: #eceef1; color: #1c1e21; padding: 4px 10px; font-size: .8rem; }
        .muted { color: #65676b; font-size: .82rem; }
        code { background: #f0f2f5; padding: 1px 5px; border-radius: 4px; font-size: .85em; }
        .flash li { margin-bottom: 4px; list-style: none; }
        .flash ul { padding: 0; margin: 0; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>📦 <?= htmlspecialchars($pageTitle) ?></h1>
    <p class="sub">
        Dépose les fichiers en vrac : chacun est reconnu par son nom et rattaché tout seul au bon module.
        Les vidéos sont identifiées par leur code YouTube entre crochets.
    </p>

    <?php if ($flash): ?>
        <div class="card flash">
            <ul>
                <?php foreach ($flash as [$kind, $name, $msg]): ?>
                    <li>
                        <span class="tag t-<?= $kind === 'ok' ? 'ok' : ($kind === 'skip' ? 'skip' : ($kind === 'warn' ? 'warn' : 'ko')) ?>">
                            <?= $kind === 'ok' ? '✓' : ($kind === 'skip' ? '–' : ($kind === 'warn' ? '!' : '×')) ?>
                        </span>
                        <code><?= htmlspecialchars($name) ?></code> — <?= htmlspecialchars($msg) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat"><b><?= $stat['pdfOk'] ?>/<?= $stat['pdfOk'] + $stat['pdfTodo'] ?></b><span>PDF sur le volume</span></div>
        <div class="stat"><b><?= $stat['vidOk'] ?>/<?= $stat['vidOk'] + $stat['vidTodo'] ?></b><span>vidéos sur le volume</span></div>
        <div class="stat"><b><?= $stat['iaTodo'] ?></b><span>guides à extraire</span></div>
        <div class="stat"><b><?= $stat['sansModule'] ?></b><span>pages sans module</span></div>
        <div class="stat"><b><?= $stat['manuel'] ?></b><span>à rattacher à la main</span></div>
    </div>

    <form class="card" method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">
        <div class="drop">
            <p><strong>1. Téléverser</strong></p>
            <input type="file" name="medias[]" multiple accept=".pdf,.mp4,.mov">
            <p class="muted">
                Procède par lots : les PDF d'abord (~118 Mo), puis les vidéos par paquets de 3 ou 4.
                Un envoi trop gros risque de dépasser la limite du serveur.
            </p>
            <button class="btn btn-go" type="submit">Téléverser et rattacher</button>
        </div>
    </form>

    <div class="card">
        <p><strong>2. Traiter</strong> — extraction Claude, correction orthographique, traduction NL.
            Un module à la fois : le bouton relance la page à chaque fois, sans risque de time-out.</p>
        <table>
            <thead>
            <tr><th>Page d'origine</th><th>Module</th><th>Média attendu</th><th>État</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($plan as $row): ?>
                <?php foreach ($row['items'] as $it): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($row['page']) ?></code></td>
                        <td>
                            <?php if ($row['mods']): ?>
                                <?= htmlspecialchars((string) $row['mods'][0]['nom']) ?>
                                <?php if (count($row['mods']) > 1): ?>
                                    <span class="tag t-warn"><?= count($row['mods']) ?> modules</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="tag t-ko">aucun module</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $it['type'] === 'video' ? '🎬' : '📄' ?>
                            <?= htmlspecialchars($it['nom']) ?>
                            <?php if (!empty($it['cible'])): ?>
                                <div class="muted">→ module dédié « <?= htmlspecialchars($it['cible']) ?> »<?php
                                    if (!empty($it['roles'])): ?> · <?= htmlspecialchars($it['roles']) ?><?php
                                    elseif ($it['roles'] === '') : ?> · tous profils<?php endif; ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($it['manual'])): ?>
                                <span class="tag t-warn">rattachement manuel</span>
                            <?php elseif (!$it['done']): ?>
                                <span class="tag t-todo">à téléverser</span>
                            <?php elseif ($it['type'] === 'video'): ?>
                                <span class="tag t-ok">sur le volume</span>
                                <?php if (($it['statut'] ?? '') === 'processing'): ?>
                                    <span class="tag t-todo">transcodage…</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="tag t-ok">sur le volume</span>
                                <span class="tag <?= !empty($it['ia']) ? 't-ok' : 't-todo' ?>">
                                    <?= !empty($it['ia']) ? 'extrait' : 'à extraire' ?></span>
                                <span class="tag <?= !empty($it['nl']) ? 't-ok' : 't-todo' ?>">
                                    <?= !empty($it['nl']) ? 'NL' : 'NL manquant' ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($it['type'] === 'pdf' && !empty($it['done']) && !empty($it['guide_id'])): ?>
                                <form method="post" style="margin:0">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="process">
                                    <input type="hidden" name="guide_id" value="<?= (int) $it['guide_id'] ?>">
                                    <button class="btn btn-sm" type="submit">
                                        <?= !empty($it['ia']) ? '↻ Refaire' : '▶ Traiter' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="muted">
        Écartés volontairement (rattachés à aucune page) :
        <?php foreach (FAMI_IMPORT_IGNORES as $i): ?><code><?= htmlspecialchars($i) ?></code> <?php endforeach; ?>
        — ils restent en local.
    </p>
</div>
</body>
</html>
