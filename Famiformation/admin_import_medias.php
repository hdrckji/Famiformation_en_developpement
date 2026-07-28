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

// Colonnes de sous-titres : normalement créées par la chaîne vidéo, mais
// l'import manuel peut arriver avant qu'une seule vidéo ait été transcodée.
foreach ([
    'sub_fr_path' => "ALTER TABLE modules ADD COLUMN sub_fr_path VARCHAR(255) NULL",
    'sub_nl_path' => "ALTER TABLE modules ADD COLUMN sub_nl_path VARCHAR(255) NULL",
    'sub_src_path' => "ALTER TABLE modules ADD COLUMN sub_src_path VARCHAR(255) NULL",
    'sub_status'  => "ALTER TABLE modules ADD COLUMN sub_status VARCHAR(16) NULL",
    'transcript'  => "ALTER TABLE modules ADD COLUMN transcript MEDIUMTEXT NULL",
] as $col => $ddl) {
    try {
        if (!$db->query("SHOW COLUMNS FROM modules LIKE " . $db->quote($col))->fetch()) { $db->exec($ddl); }
    } catch (Exception $e) { /* migration non bloquante */ }
}

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
 * MODULE QUI DOIT PORTER LE CONTENU.
 *
 * Règle du site : un module est soit CONTENEUR (il contient des modules), soit
 * ÉLÉMENT (il porte un contenu — « élément C » — ou une fonction — « élément S »).
 *
 * À la création normale, le parent n'existe pas encore : d'où la création
 * automatique d'un sous-module « Guide » par module_save.php. Ici c'est
 * l'inverse — le module ciblé par `link` EXISTE DÉJÀ et c'est lui l'élément.
 * Lui greffer un enfant unique le transformerait en conteneur et ajouterait un
 * niveau de navigation qui ne porte rien.
 *
 * On ne crée donc un sous-module que là où la page porte VRAIMENT plusieurs
 * contenus distincts : livret op/os séparé par profil, deux vidéos Lollyland.
 * Ces cas-là sont déclarés dans famiImportSpecialTargets() avec un `nom`.
 *
 * @return array|null la ligne `modules` à remplir, ou null si elle est introuvable
 */
function famiImportCible(PDO $db, array $mod, array $hit, $creer = true)
{
    $parentId = (int) $mod['id'];
    $spec = famiImportSpecialTargets()[$hit['ref']] ?? null;

    // Cas conteneur : sous-module dédié, explicitement déclaré.
    if ($spec && $spec['page'] === $hit['page'] && !empty($spec['nom'])) {
        return $creer
            ? famiImportFindOrCreateTarget($db, $parentId, $spec, (string) ($mod['roles'] ?? ''))
            : famiImportChildByName($db, $parentId, $spec['nom']);
    }

    // Cas élément : le module lui-même. On le relit pour disposer de toutes ses
    // colonnes (contenu_ia, pdf_path…), l'index des modules n'en portant qu'une partie.
    try {
        $st = $db->prepare("SELECT * FROM modules WHERE id = ? LIMIT 1");
        $st->execute([$parentId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
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

        // Le module ciblé porte le contenu LUI-MÊME (élément). Un sous-module n'est
        // créé que pour les pages qui portent plusieurs contenus distincts.
        $cible = famiImportCible($db, $mod, $hit);
        if (!$cible) {
            $flash[] = ['ko', $name, "module cible introuvable"];
            continue;
        }
        $dedie = ((int) $cible['id'] !== $parentId); // vrai = sous-module dédié
        $ou = $dedie ? ("« " . (string) $mod['nom'] . " » → « " . (string) $cible['nom'] . " »")
                     : ("« " . (string) $mod['nom'] . " »");

        if ($hit['kind'] === 'pdf_nl') {
            // Version NÉERLANDAISE humaine du même document : elle se range à côté
            // du FR, sur le MÊME module. Aucune traduction machine ne sera faite.
            $key = famiStoreUploadedFileAt($tmp, $name, ['application/pdf' => 'pdf'], 60 * 1024 * 1024, 'pdf', $slug . '-guide-nl');
            if ($key === null) {
                $flash[] = ['ko', $name, "refusé (format ou taille)"];
                continue;
            }
            if (!empty($cible['pdf_nl_path'])) { volumeUnlink((string) $cible['pdf_nl_path']); }
            $db->prepare("UPDATE modules SET pdf_nl_path = ?, contenu_ia_nl = NULL, nl_hash = NULL WHERE id = ?")
               ->execute([$key, (int) $cible['id']]);
            $flash[] = ['ok', $name, "version NL rattachée à " . $ou];

        } elseif ($hit['kind'] === 'pdf') {
            $key = famiStoreUploadedFileAt($tmp, $name, ['application/pdf' => 'pdf'], 60 * 1024 * 1024, 'pdf', $slug . '-guide');
            if ($key === null) {
                $flash[] = ['ko', $name, "refusé (format ou taille)"];
                continue;
            }
            if (!empty($cible['pdf_path'])) { volumeUnlink((string) $cible['pdf_path']); }
            $db->prepare("UPDATE modules SET pdf_path = ?, content_kind = 'ecrit', is_container = 0,
                            uniformized = 0, contenu_ia = NULL, contenu_ia_nl = NULL,
                            quiz_json_nl = NULL, nl_hash = NULL WHERE id = ?")
               ->execute([$key, (int) $cible['id']]);
            // Un parent ne devient conteneur QUE s'il a réellement un enfant.
            if ($dedie) { $db->prepare("UPDATE modules SET is_container = 1 WHERE id = ?")->execute([$parentId]); }
            $flash[] = ['ok', $name, "rattaché à " . $ou];

        } else {
            $key = famiStoreUploadedFileAt($tmp, $name, ['video/mp4' => 'mp4', 'video/quicktime' => 'mov'], 1024 * 1024 * 1024, 'video_raw', $slug . '-video');
            if ($key === null) {
                $flash[] = ['ko', $name, "refusé (format ou taille)"];
                continue;
            }
            foreach (['video_path', 'video_src_path'] as $c) {
                if (!empty($cible[$c])) { volumeUnlink((string) $cible[$c]); }
            }
            $vidId = (int) $cible['id'];
            $db->prepare("UPDATE modules SET video_src_path = ?, video_path = NULL, video_status = 'processing',
                            content_kind = 'video', is_container = 0 WHERE id = ?")
               ->execute([$key, $vidId]);
            if ($dedie) { $db->prepare("UPDATE modules SET is_container = 1 WHERE id = ?")->execute([$parentId]); }
            // Transcodage 720p + transcription Whisper, en tâche de fond.
            spawnVideoTranscode($key, $vidId);
            $flash[] = ['ok', $name, "rattaché à " . $ou . ", transcodage lancé"];
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
//  ACTION 2 bis — EXTRACTION DES IMAGES, sans IA et donc SANS COÛT.
//  pdfimages (poppler) est un binaire local : il tourne sur le volume et
//  n'appelle aucune API. Il faut le lancer AVANT de rédiger les fiches
//  ailleurs, car son filtrage (petites images, bandeaux, doublons écartés)
//  décide de la numérotation que les blocs `image` référencent.
// ------------------------------------------------------------------
if (($_POST['action'] ?? '') === 'extract_images') {
    requireValidCSRF();
    @set_time_limit(0);
    require_once 'includes/ai_uniformise.php'; // aiExtractPdfImages()

    $st = $db->query("SELECT id, nom, pdf_path FROM modules WHERE content_kind = 'ecrit' AND pdf_path IS NOT NULL AND pdf_path <> ''");
    $n = 0;
    foreach (($st ? $st->fetchAll(PDO::FETCH_ASSOC) : []) as $g) {
        $diag = null;
        $imgs = aiExtractPdfImages(moduleFileAbsPath($g['pdf_path']), (string) $g['pdf_path'], $diag);
        $db->prepare("UPDATE modules SET contenu_images = ? WHERE id = ?")
           ->execute([$imgs ? json_encode($imgs) : null, (int) $g['id']]);

        // On dit TOUJOURS ce qui s'est passé : un « aucune image » muet ne permet
        // pas de distinguer un outil absent d'un document sans photo.
        $rejets = [];
        foreach (['rejet_illisible' => 'illisibles', 'rejet_petite' => 'trop petites',
                  'rejet_bandeau' => 'bandeaux', 'rejet_repetee' => 'répétées (habillage)'] as $k => $lib) {
            if (!empty($diag[$k])) { $rejets[] = $diag[$k] . ' ' . $lib; }
        }
        $detail = $diag['brut'] . " extraite(s), " . $diag['gardees'] . " gardée(s)"
            . ($rejets ? ' — écartées : ' . implode(', ', $rejets) : '')
            . (!empty($diag['erreur']) ? ' — ' . $diag['erreur'] : '');
        $flash[] = [$imgs ? 'ok' : 'warn', (string) $g['nom'], $detail];
        $n++;
    }
    if ($n === 0) {
        $flash[] = ['warn', 'extraction', "aucun PDF sur le volume — commence par l'étape 1"];
    }
}

// ------------------------------------------------------------------
//  ACTION 2 ter — IMPORT DE SOUS-TITRES rédigés hors API.
//  Whisper tourne chez toi (OpenAI), la traduction NL vient de Claude web :
//  aucun appel facturé ici. On dépose des .srt (ou .vtt) nommés avec l'ID
//  YouTube de la vidéo ; le suffixe _fr / _nl donne la langue.
//
//  ⚠️ À faire APRÈS le transcodage : le worker video_transcode.php appelle
//  famiBuildSubtitles(), qui réécrit ces mêmes colonnes. Importer pendant
//  qu'une vidéo est en « processing » ferait écraser tes pistes.
// ------------------------------------------------------------------
if (($_POST['action'] ?? '') === 'import_subs') {
    requireValidCSRF();
    @set_time_limit(0);
    require_once 'includes/transcription.php'; // famiSrtParse(), famiSrtToVtt()

    $base = famiStorageBase();
    $dir = $base . '/modules/subs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

    $byPage = famiImportModulesByPage($db);
    $files = $_FILES['subs'] ?? null;
    $count = $files ? count((array) $files['name']) : 0;

    for ($i = 0; $i < $count; $i++) {
        $name = (string) $files['name'][$i];
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $flash[] = ['ko', $name, "téléversement interrompu"];
            continue;
        }
        $hit = famiImportRecognize($name);
        if (!$hit || $hit['kind'] !== 'video') {
            $flash[] = ['ko', $name, "aucun identifiant de vidéo reconnu dans le nom (il faut le [code] entre crochets)"];
            continue;
        }
        $mods = $byPage[$hit['page']] ?? [];
        if (!$mods) {
            $flash[] = ['ko', $name, "aucun module ne pointe vers " . $hit['page']];
            continue;
        }
        $child = famiImportCible($db, $mods[0], $hit);
        if (!$child) {
            $flash[] = ['warn', $name, "module cible introuvable — téléverse d'abord la vidéo"];
            continue;
        }
        if (($child['video_status'] ?? '') === 'processing') {
            $flash[] = ['warn', $name, "transcodage en cours — attends qu'il finisse, sinon le worker écrasera ces pistes"];
            continue;
        }

        // Langue : suffixe _nl / .nl / -nl dans le nom, sinon français par défaut.
        $isNl = (bool) preg_match('/[._-]nl\b/i', pathinfo($name, PATHINFO_FILENAME));
        $raw = (string) @file_get_contents($files['tmp_name'][$i]);
        if (trim($raw) === '') {
            $flash[] = ['ko', $name, "fichier vide"];
            continue;
        }
        // Un .vtt commence par WEBVTT ; sinon on suppose du SRT et on convertit.
        $isVtt = (stripos(ltrim($raw), 'WEBVTT') === 0);
        $cues = famiSrtParse($isVtt ? preg_replace('/^WEBVTT.*?\R\R/s', '', $raw) : $raw);
        if (!$cues) {
            $flash[] = ['ko', $name, "aucun sous-titre lisible (format SRT ou VTT attendu)"];
            continue;
        }
        $vtt = $isVtt ? $raw : famiSrtToVtt($raw);

        $col = $isNl ? 'sub_nl_path' : 'sub_fr_path';
        if (!empty($child[$col])) { volumeUnlink((string) $child[$col]); }

        $stem = 'sub_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($isNl ? '_nl' : '_fr') . '.vtt';
        if (@file_put_contents($dir . '/' . $stem, $vtt) === false) {
            $flash[] = ['ko', $name, "écriture impossible sur le volume"];
            continue;
        }
        $key = 'modules/subs/' . $stem;

        // Le transcript en texte brut ne vient que de la piste d'origine (FR) :
        // c'est lui qui sert à enrichir le quiz, pas la traduction.
        if ($isNl) {
            $db->prepare("UPDATE modules SET sub_nl_path = ?, sub_status = 'ready' WHERE id = ?")
               ->execute([$key, (int) $child['id']]);
        } else {
            $texte = trim(implode("\n", array_map(function ($c) { return (string) ($c['text'] ?? ''); }, $cues)));
            $db->prepare("UPDATE modules SET sub_fr_path = ?, transcript = ?, sub_status = 'ready' WHERE id = ?")
               ->execute([$key, ($texte !== '' ? $texte : null), (int) $child['id']]);
        }
        $flash[] = ['ok', $name, count($cues) . " sous-titres en " . ($isNl ? 'NL' : 'FR')
            . " → « " . (string) $child['nom'] . " »"];
    }
}

// ------------------------------------------------------------------
//  ACTION 3 — IMPORT D'UN JSON produit hors API (Claude web).
//  Le contenu des fiches est rédigé ailleurs et déposé ici : aucun appel
//  facturé. Le JSON porte les DEUX langues, donc ni extraction ni traduction
//  ne sont relancées — on écrit directement contenu_ia et contenu_ia_nl.
// ------------------------------------------------------------------
if (($_POST['action'] ?? '') === 'import_json') {
    requireValidCSRF();
    @set_time_limit(0);
    require_once 'includes/ai_uniformise.php'; // aiSanitizeBlocks()

    $f = $_FILES['contenu_json'] ?? null;
    $raw = ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ? @file_get_contents($f['tmp_name']) : '';
    $data = json_decode((string) $raw, true);

    if (!is_array($data) || !isset($data['documents']) || !is_array($data['documents'])) {
        $flash[] = ['ko', (string) ($f['name'] ?? 'fichier'), "JSON illisible ou clé « documents » absente"];
    } else {
        $byPage = famiImportModulesByPage($db);
        foreach ($data['documents'] as $doc) {
            $nom = basename((string) ($doc['file'] ?? ''));
            if ($nom === '') { continue; }

            $hit = famiImportRecognize($nom);
            if (!$hit || $hit['kind'] === 'video') {
                $flash[] = ['ko', $nom, "inconnu de la table de correspondance"];
                continue;
            }
            // La version NL humaine n'a pas d'entrée propre ici : le JSON de la
            // version FR porte déjà les deux langues dans blocks/blocks_nl.
            if ($hit['kind'] === 'pdf_nl') {
                $flash[] = ['skip', $nom, "inutile : la fiche FR du même document porte déjà le néerlandais"];
                continue;
            }

            $mods = $byPage[$hit['page']] ?? [];
            if (!$mods) {
                $flash[] = ['ko', $nom, "aucun module ne pointe vers " . $hit['page']];
                continue;
            }
            $mod = $mods[0];
            $parentId = (int) $mod['id'];

            $child = famiImportCible($db, $mod, $hit);
            if (!$child) {
                $flash[] = ['warn', $nom, "module cible introuvable — téléverse d'abord le PDF"];
                continue;
            }

            // Les blocs passent par le même validateur que la sortie de l'IA :
            // un bloc non conforme est écarté ici plutôt que de casser l'affichage.
            $blocs = aiSanitizeBlocks($doc['blocks'] ?? []);
            $blocsNl = aiSanitizeBlocks($doc['blocks_nl'] ?? []);
            if (!$blocs) {
                $flash[] = ['ko', $nom, "aucun bloc valide (vérifie les types autorisés)"];
                continue;
            }
            $lang = ((string) ($doc['lang'] ?? 'fr') === 'nl') ? 'nl' : 'fr';
            $jsonFr = json_encode(['lang' => $lang, 'blocks' => $blocs], JSON_UNESCAPED_UNICODE);
            $jsonNl = $blocsNl ? json_encode(['lang' => ($lang === 'nl' ? 'fr' : 'nl'), 'blocks' => $blocsNl], JSON_UNESCAPED_UNICODE) : null;

            // nl_hash calé sur le FR écrit : la traduction fournie est considérée
            // à jour, donc nlSyncModule ne la remplacera pas par une version machine.
            $hash = $jsonNl ? hash('sha256', trim((string) ($child['nom'] ?? '')) . '|' . trim((string) ($child['description'] ?? ''))
                . '|' . $jsonFr . '|' . (string) ($child['quiz_json'] ?? '')) : null;

            // content_status = 'pending' masque la tuile en attendant une relecture
            // (module.php). Le contenu importé ici est déjà relu hors du site, donc
            // on le publie directement — sinon la fiche resterait invisible.
            $db->prepare("UPDATE modules SET contenu_ia = ?, source_lang = ?, uniformized = 1,
                            contenu_ia_nl = ?, nl_hash = ?, content_status = NULL, is_active = 1 WHERE id = ?")
               ->execute([$jsonFr, $lang, $jsonNl, $hash, (int) $child['id']]);

            $flash[] = ['ok', $nom, count($blocs) . " blocs en " . $lang
                . ($jsonNl ? " + " . count($blocsNl) . " blocs traduits" : " (pas de traduction fournie)")
                . " → « " . (string) $child['nom'] . " »"];
        }
    }
}

// ------------------------------------------------------------------
//  ACTION 3 bis — RÉPARATION DE STRUCTURE.
//
//  Les premiers imports appliquaient la règle « contenu ⇒ créer un sous-module
//  Guide ». Elle vaut à la CRÉATION, quand le parent n'existe pas encore. Ici le
//  module ciblé existait déjà et était l'élément : il a été transformé en
//  conteneur avec un enfant unique, soit un niveau de navigation qui ne porte rien.
//
//  On remonte donc le contenu de cet enfant sur le parent, puis on le supprime.
//  Prudence : on ne touche QUE les parents du périmètre de migration qui ont
//  exactement UN enfant, portant l'un des noms créés par l'import.
// ------------------------------------------------------------------
if (($_POST['action'] ?? '') === 'repair_structure') {
    requireValidCSRF();
    @set_time_limit(0);

    $colonnes = ['pdf_path', 'pdf_nl_path', 'video_path', 'video_src_path', 'video_status',
                 'contenu_ia', 'contenu_ia_nl', 'contenu_images', 'contenu_by', 'quiz_json',
                 'quiz_json_nl', 'nl_hash', 'source_lang', 'uniformized', 'a_evaluer',
                 'content_kind', 'sub_fr_path', 'sub_nl_path', 'sub_src_path', 'sub_status', 'transcript'];

    // Périmètre : les modules dont link (ou link_legacy) désigne une page migrée.
    $pages = array_keys(famiLegacyMediaMap());
    $rows = [];
    try {
        // Un module du périmètre porte forcément un lien : `link` s'il n'a pas
        // encore été basculé, `link_legacy` s'il l'a été.
        $rows = $db->query("SELECT id, nom, link, link_legacy FROM modules
                             WHERE (link IS NOT NULL AND link <> '')
                                OR (link_legacy IS NOT NULL AND link_legacy <> '')")
                   ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // link_legacy peut ne pas exister si aucune bascule n'a encore eu lieu.
        try {
            $rows = $db->query("SELECT id, nom, link, NULL AS link_legacy FROM modules
                                 WHERE link IS NOT NULL AND link <> ''")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) { $rows = []; }
    }

    $n = 0;
    foreach ($rows as $m) {
        $cle = basename(strtok((string) ($m['link'] ?: $m['link_legacy']), '?'));
        if ($cle === '' || !in_array($cle, $pages, true)) { continue; }
        // Les pages à contenus multiples gardent légitimement leurs sous-modules.
        $entry = famiLegacyMediaMap()[$cle];
        if (count($entry['pdfs']) + count($entry['videos']) > 1) { continue; }

        $enfants = $db->prepare("SELECT * FROM modules WHERE parent_id = ?");
        $enfants->execute([(int) $m['id']]);
        $liste = $enfants->fetchAll(PDO::FETCH_ASSOC);
        if (!$liste) { continue; } // déjà un élément : rien à faire
        if (count($liste) > 1) {
            $flash[] = ['skip', (string) $m['nom'], count($liste) . " sous-modules — structure voulue, laissée telle quelle"];
            continue;
        }
        $e = $liste[0];

        // Critère indépendant du NOM de l'enfant : on remonte dès que l'enfant
        // unique porte le contenu et que le parent n'en porte aucun. C'est
        // exactement la situation créée par les premiers imports, quel que soit
        // le libellé donné au sous-module.
        $porte = function ($r) {
            foreach (['pdf_path', 'video_path', 'video_src_path', 'contenu_ia'] as $c) {
                if (!empty($r[$c])) { return true; }
            }
            return false;
        };
        $selfQ = $db->prepare("SELECT * FROM modules WHERE id = ? LIMIT 1");
        $selfQ->execute([(int) $m['id']]);
        $self = $selfQ->fetch(PDO::FETCH_ASSOC) ?: [];

        // Enfant vide : il n'apporte rien, on le supprime sans rien remonter.
        if (!$porte($e)) {
            $db->prepare("DELETE FROM modules WHERE id = ?")->execute([(int) $e['id']]);
            $db->prepare("UPDATE modules SET is_container = 0 WHERE id = ?")->execute([(int) $m['id']]);
            $flash[] = ['ok', (string) $m['nom'], "sous-module vide « " . (string) $e['nom'] . " » supprimé"];
            $n++;
            continue;
        }
        // Contenu des DEUX côtés : le module a été réimporté depuis la correction,
        // son contenu est donc le plus récent. L'enfant est un reliquat.
        if ($porte($self)) {
            $db->prepare("DELETE FROM modules WHERE id = ?")->execute([(int) $e['id']]);
            $db->prepare("UPDATE modules SET is_container = 0 WHERE id = ?")->execute([(int) $m['id']]);
            $flash[] = ['ok', (string) $m['nom'], "reliquat « " . (string) $e['nom'] . " » supprimé (le module portait déjà le contenu)"];
            $n++;
            continue;
        }

        // Remontée : on ne copie que les colonnes réellement présentes.
        $set = [];
        $val = [];
        foreach ($colonnes as $c) {
            if (array_key_exists($c, $e)) { $set[] = "`$c` = ?"; $val[] = $e[$c]; }
        }
        if (!$set) { continue; }
        $val[] = (int) $m['id'];
        try {
            $db->prepare("UPDATE modules SET " . implode(', ', $set) . ", is_container = 0 WHERE id = ?")->execute($val);
            $db->prepare("DELETE FROM modules WHERE id = ?")->execute([(int) $e['id']]);
            $flash[] = ['ok', (string) $m['nom'], "contenu remonté depuis « " . (string) $e['nom'] . " », sous-module supprimé"];
            $n++;
        } catch (Exception $ex) {
            $flash[] = ['ko', (string) $m['nom'], "remontée impossible : " . $ex->getMessage()];
        }
    }
    if ($n === 0) {
        $flash[] = ['skip', 'structure', count($rows) . " module(s) porteurs d'un lien examinés — aucun sous-module à remonter"];
    } else {
        $flash[] = ['ok', 'structure', $n . " module(s) remis à plat sur " . count($rows) . " examinés"];
    }
}

// ------------------------------------------------------------------
//  ACTION 4 — BASCULE D'AFFICHAGE, réversible.
//
//  Tant que `modules.link` pointe vers l'ancienne page, la tuile y mène
//  directement (module.php) et le contenu importé n'est JAMAIS affiché.
//  Vider `link` fait passer le module par le moteur générique.
//
//  L'ancien lien est RANGÉ dans link_legacy, jamais perdu : la bascule se
//  défait tant qu'on n'a pas supprimé les pages statiques.
// ------------------------------------------------------------------
if (in_array(($_POST['action'] ?? ''), ['switch_on', 'switch_off'], true)) {
    requireValidCSRF();
    try {
        if (!$db->query("SHOW COLUMNS FROM modules LIKE 'link_legacy'")->fetch()) {
            $db->exec("ALTER TABLE modules ADD COLUMN link_legacy VARCHAR(255) NULL");
        }
    } catch (Exception $e) { /* migration non bloquante */ }

    $vers = (string) ($_POST['page'] ?? '');   // vide = toutes les pages prêtes
    $on = (($_POST['action'] ?? '') === 'switch_on');
    $byPage = famiImportModulesByPage($db);

    foreach ($byPage as $page => $mods) {
        if ($vers !== '' && $page !== $vers) { continue; }
        if (!isset(famiLegacyMediaMap()[$page])) { continue; } // hors périmètre migration
        foreach ($mods as $m) {
            $id = (int) $m['id'];
            if ($on) {
                // On ne bascule que si du contenu est réellement en place, sinon
                // on remplacerait une page qui marche par un module vide.
                // Le contenu est porté par le module LUI-MÊME (élément) ou, pour les
                // pages à contenus multiples, par l'un de ses sous-modules.
                $porte = function ($r) {
                    return $r && (!empty($r['contenu_ia']) || !empty($r['video_path']) || !empty($r['video_src_path']));
                };
                $self = null;
                try {
                    $q = $db->prepare("SELECT * FROM modules WHERE id = ? LIMIT 1");
                    $q->execute([$id]);
                    $self = $q->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (Exception $e) { $self = null; }

                $pret = $porte($self);
                if (!$pret) {
                    $q = $db->prepare("SELECT * FROM modules WHERE parent_id = ?");
                    $q->execute([$id]);
                    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $enf) {
                        if ($porte($enf)) { $pret = true; break; }
                    }
                }
                if (!$pret) {
                    $flash[] = ['skip', $page, "pas encore de contenu — bascule ignorée"];
                    continue;
                }
                $db->prepare("UPDATE modules SET link_legacy = COALESCE(link_legacy, link), link = NULL WHERE id = ?")
                   ->execute([$id]);
                $flash[] = ['ok', $page, "affiché par le moteur générique (ancien lien conservé)"];
            } else {
                $db->prepare("UPDATE modules SET link = COALESCE(link_legacy, link) WHERE id = ? AND link_legacy IS NOT NULL")
                   ->execute([$id]);
                $flash[] = ['ok', $page, "retour à l'ancienne page"];
            }
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
$manifest = []; // fichier PDF => nombre d'images retenues (à coller dans le prompt)

foreach ($map as $page => $entry) {
    $mods = $byPage[$page] ?? [];
    $row = ['page' => $page, 'mods' => $mods, 'items' => []];
    $stat['pages']++;
    if (!$mods) { $stat['sansModule']++; }

    $parentId = $mods ? (int) $mods[0]['id'] : 0;
    // Résolution en LECTURE SEULE : l'affichage du plan ne doit rien créer.
    $resoudre = function ($ref, $kind) use ($db, $mods, $page, $parentId) {
        if (!$parentId) { return null; }
        return famiImportCible($db, $mods[0], ['page' => $page, 'kind' => $kind, 'ref' => $ref], false);
    };

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
        $cible = $resoudre($p, $estNl ? 'pdf_nl' : 'pdf');

        // La version NL vit sur la MÊME ligne que le FR, dans sa propre colonne :
        // son état se lit donc sur pdf_nl_path / contenu_ia_nl, pas sur pdf_path.
        $done = $cible && !empty($cible[$estNl ? 'pdf_nl_path' : 'pdf_path']);
        $ia = $cible && !empty($cible[$estNl ? 'contenu_ia_nl' : 'contenu_ia']);
        $nl = $estNl ? $ia : ($cible && !empty($cible['contenu_ia_nl']));

        // Nombre d'images RETENUES après filtrage : c'est ce chiffre, pas le
        // nombre d'images visibles dans le PDF, qui numérote les blocs `image`.
        $nbImg = 0;
        if ($cible && !empty($cible['contenu_images'])) {
            $nbImg = count((array) json_decode((string) $cible['contenu_images'], true));
        }
        if (!$estNl && $nbImg > 0) { $manifest[$p] = $nbImg; }
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
        $cible = $resoudre($v['id'], 'video');
        $done = $cible && (!empty($cible['video_path']) || !empty($cible['video_src_path']));
        $row['items'][] = ['type' => 'video', 'nom' => $v['file'], 'id' => $v['id'], 'done' => $done,
                           'cible' => $spec['nom'] ?? null,
                           'manual' => ($manualVid && !$spec), 'statut' => $cible['video_status'] ?? null,
                           // Pistes de sous-titres : la FR se télécharge pour être traduite
                           // ailleurs, la NL se ré-importe ensuite par l'étape 2 ter.
                           'sub_fr' => (string) ($cible['sub_fr_path'] ?? ''),
                           'sub_nl' => (string) ($cible['sub_nl_path'] ?? ''),
                           'sub_statut' => (string) ($cible['sub_status'] ?? '')];
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
        <form method="post" style="margin:0 0 12px">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="extract_images">
            <p style="margin-top:0"><strong>2 bis-a. Extraire les images</strong> — gratuit, aucun appel IA</p>
            <p class="muted">
                <code>pdfimages</code> tourne sur le serveur. Il écarte les images de moins de
                160 px, les bandeaux, et toute image répétée (logos, en-têtes) —
                <strong>c'est la numérotation d'APRÈS ce filtrage</strong> que les blocs
                <code>image</code> référencent. Lance-le avant de rédiger les fiches ailleurs.
            </p>
            <button class="btn btn-sm" type="submit">Extraire les images de tous les PDF</button>
        </form>

        <?php if ($manifest): ?>
            <p style="margin-bottom:4px"><strong>Manifeste à coller dans le prompt</strong> —
                dit à Claude combien d'images il a à placer, et sous quels numéros :</p>
            <textarea readonly onclick="this.select()" style="width:100%; height:120px; font-family:ui-monospace,monospace; font-size:.8rem; border:1px solid #dfe1e5; border-radius:8px; padding:8px;"><?php
                foreach ($manifest as $f => $c) {
                    echo htmlspecialchars($f) . ' : ' . (int) $c . " image(s), numerotees 1 a " . (int) $c . "\n";
                } ?></textarea>
        <?php else: ?>
            <p class="muted">Aucune image extraite pour l'instant — le manifeste apparaîtra ici après l'extraction.</p>
        <?php endif; ?>
    </div>

    <form class="card" method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="import_json">
        <div class="drop">
            <p><strong>2 bis-b. Importer un JSON</strong> — contenu rédigé hors API (Claude web)</p>
            <input type="file" name="contenu_json" accept=".json,application/json">
            <p class="muted">
                Alternative gratuite au bouton « Traiter » : le JSON porte les deux langues,
                donc ni extraction ni traduction ne sont relancées — aucun appel facturé.
                Le prompt à utiliser est dans <code>quiz/seed/PROMPT_EXTRACTION_PDF.md</code>.
                Les fiches importées ainsi sont <strong>en texte seul</strong> (les images du PDF
                ne sont extraites que par la voie API).
            </p>
            <button class="btn btn-go" type="submit">Importer le contenu</button>
        </div>
    </form>

    <form class="card" method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="import_subs">
        <div class="drop">
            <p><strong>2 ter. Importer des sous-titres</strong> — Whisper chez toi, traduction par Claude web</p>
            <input type="file" name="subs[]" multiple accept=".srt,.vtt">
            <p class="muted">
                Le nom du fichier doit contenir <strong>le code de la vidéo entre crochets</strong>
                (ex. <code>Caisse [0v6VW-TlFfs].srt</code>) — c'est lui qui fait le routage.
                Ajoute <code>_nl</code> pour la piste néerlandaise
                (<code>Caisse [0v6VW-TlFfs]_nl.srt</code>) ; sans suffixe, la piste est traitée
                comme la langue d'origine. SRT et VTT acceptés.
            </p>
            <p class="muted">
                ⚠️ <strong>Attends la fin du transcodage</strong> avant d'importer : le worker
                génère lui-même les pistes et écraserait les tiennes. Les lignes marquées
                « transcodage… » dans le tableau ne sont pas prêtes.
            </p>
            <button class="btn btn-go" type="submit">Importer les sous-titres</button>
        </div>
    </form>

    <div class="card">
        <p style="margin-top:0"><strong>Arborescence réelle</strong> — ce que la base contient vraiment</p>
        <p class="muted">
            Un module du périmètre doit apparaître <strong>sans enfant</strong> et marqué
            « élément ». S'il affiche encore un sous-module, un clic mène au sous-module
            au lieu du contenu.
        </p>
        <table>
            <thead><tr><th>Module</th><th>Page d'origine</th><th>Type</th><th>Porte le contenu</th><th>Sous-modules</th></tr></thead>
            <tbody>
            <?php
            $pagesMig = array_keys(famiLegacyMediaMap());
            $arbre = [];
            try {
                $arbre = $db->query("SELECT id, nom, link, link_legacy, is_container, content_kind,
                                            pdf_path, video_path, video_src_path, contenu_ia
                                       FROM modules
                                      WHERE (link IS NOT NULL AND link <> '')
                                         OR (link_legacy IS NOT NULL AND link_legacy <> '')
                                      ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) { $arbre = []; }
            foreach ($arbre as $a) {
                $cle = basename(strtok((string) ($a['link'] ?: $a['link_legacy']), '?'));
                if (!in_array($cle, $pagesMig, true)) { continue; }
                $enf = $db->prepare("SELECT nom FROM modules WHERE parent_id = ?");
                $enf->execute([(int) $a['id']]);
                $noms = array_column($enf->fetchAll(PDO::FETCH_ASSOC), 'nom');
                $aDuContenu = !empty($a['pdf_path']) || !empty($a['video_path'])
                    || !empty($a['video_src_path']) || !empty($a['contenu_ia']);
                $multi = (count(famiLegacyMediaMap()[$cle]['pdfs']) + count(famiLegacyMediaMap()[$cle]['videos'])) > 1;
                $souci = (!$multi && $noms) || (!$aDuContenu && !$noms);
            ?>
                <tr<?= $souci ? ' style="background:#fdeaea"' : '' ?>>
                    <td><?= htmlspecialchars((string) $a['nom']) ?></td>
                    <td><code><?= htmlspecialchars($cle) ?></code></td>
                    <td><?= !empty($a['is_container']) ? 'conteneur' : 'élément' ?>
                        <?= $a['content_kind'] ? '<span class="muted">(' . htmlspecialchars((string) $a['content_kind']) . ')</span>' : '' ?></td>
                    <td><?= $aDuContenu ? '<span class="tag t-ok">oui</span>' : '<span class="tag t-todo">non</span>' ?></td>
                    <td><?= $noms
                            ? '<span class="tag ' . ($multi ? 't-ok' : 't-ko') . '">' . htmlspecialchars(implode(' · ', $noms)) . '</span>'
                            : '<span class="muted">aucun</span>' ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <p class="muted">En rouge : un sous-module là où il ne devrait pas y en avoir, ou un module sans contenu ni enfant.</p>
    </div>

    <div class="card">
        <p style="margin-top:0"><strong>2 quater. Réparer la structure</strong></p>
        <p class="muted">
            Un module est soit <strong>conteneur</strong> (il contient des modules), soit
            <strong>élément</strong> (il porte un contenu). Les premiers imports créaient
            systématiquement un sous-module « Guide » — logique à la création, faux ici où
            le module ciblé existait déjà et <em>était</em> l'élément. Résultat : un niveau
            de navigation vide. Ce bouton remonte le contenu sur le module et supprime
            l'enfant inutile. Les pages à contenus multiples (livret op/os, vidéos
            Lollyland) gardent leurs sous-modules.
        </p>
        <form method="post" style="margin:0">
            <?= csrfField() ?><input type="hidden" name="action" value="repair_structure">
            <button class="btn btn-go" type="submit">Remonter les contenus et supprimer les sous-modules inutiles</button>
        </form>
    </div>

    <div class="card">
        <p style="margin-top:0"><strong>3. Basculer l'affichage</strong></p>
        <p class="muted">
            Tant qu'un module garde son ancien lien, la tuile mène à la page statique
            d'origine et <strong>le contenu importé n'est jamais affiché</strong>. C'est
            l'explication d'une fiche importée qui « ne change rien » à l'écran.
            La bascule ne touche que les modules qui ont réellement du contenu, et
            l'ancien lien est conservé — donc c'est réversible.
        </p>
        <form method="post" style="display:inline">
            <?= csrfField() ?><input type="hidden" name="action" value="switch_on">
            <button class="btn btn-go" type="submit">Basculer tout ce qui est prêt</button>
        </form>
        <form method="post" style="display:inline; margin-left:6px">
            <?= csrfField() ?><input type="hidden" name="action" value="switch_off">
            <button class="btn btn-sm" type="submit">↩ Revenir aux anciennes pages</button>
        </form>
    </div>

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
                                <?php elseif (($it['sub_statut'] ?? '') === 'processing'): ?>
                                    <span class="tag t-todo">transcription…</span>
                                <?php endif; ?>
                                <?php if (!empty($it['sub_fr'])): ?>
                                    <span class="tag t-ok">sous-titres</span>
                                    <span class="tag <?= !empty($it['sub_nl']) ? 't-ok' : 't-todo' ?>">
                                        <?= !empty($it['sub_nl']) ? 'NL' : 'NL manquant' ?></span>
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
                            <?php if ($it['type'] === 'video' && !empty($it['sub_fr'])): ?>
                                <?php
                                    // Nom déjà prêt pour le ré-import : il porte le code de la
                                    // vidéo entre crochets, que l'étape 2 ter utilise pour router.
                                    $stem = pathinfo((string) $it['nom'], PATHINFO_FILENAME);
                                ?>
                                <a class="btn btn-sm" style="text-decoration:none; display:inline-block"
                                   href="media.php?dl=1&amp;f=<?= rawurlencode((string) $it['sub_fr']) ?>&amp;as=<?= rawurlencode($stem . '.vtt') ?>">⬇ VTT</a>
                            <?php endif; ?>
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
