<?php
// ============================================================
//  STOCKAGE DES FICHIERS DE MODULE — helpers partagés
//
//  Ces fonctions vivaient dans module_save.php, un script de traitement POST :
//  impossible de les réutiliser ailleurs sans déclencher son traitement.
//  Elles sont ici pour être partagées entre module_save.php et l'import par
//  lot des médias legacy (admin_import_medias.php).
//
//  ⚠️ __DIR__ vaut ici public/includes — alors que le code d'origine vivait
//  dans public/. Tout chemin de repli passe donc par famiPublicDir(), sinon
//  l'ancien dossier « uploads/ » serait cherché dans le mauvais répertoire.
// ============================================================

if (!function_exists('famiPublicDir')) {
    /** Racine web (public/), quelle que soit la profondeur de l'include. */
    function famiPublicDir()
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('famiStorageBase')) {
    /** Base de stockage : volume Railway si monté, sinon public/uploads. */
    function famiStorageBase()
    {
        return defined('FAMI_STORAGE_BASE') ? rtrim(FAMI_STORAGE_BASE, '/') : (famiPublicDir() . '/uploads');
    }
}

if (!function_exists('moduleFileAbsPath')) {
    /** Chemin absolu d'un fichier de module (volume Railway, ou ancien uploads/). */
    function moduleFileAbsPath($rel)
    {
        $rel = (string) $rel;
        if ($rel === '') { return ''; }
        if (strpos($rel, 'uploads/') === 0) { return famiPublicDir() . '/' . $rel; }
        return famiStorageBase() . '/' . $rel;
    }
}

if (!function_exists('moduleFileSlug')) {
    /** Slug lisible à partir du nom du module (pour des noms de fichiers clairs). */
    function moduleFileSlug($nom)
    {
        $s = (string) $nom;
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($t !== false && $t !== '') { $s = $t; }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim((string) $s, '-');
        return $s !== '' ? substr($s, 0, 40) : 'fichier';
    }
}

if (!function_exists('volumeUnlink')) {
    /** Efface un fichier du volume en toute sécurité (confiné à la base de stockage). */
    function volumeUnlink($key)
    {
        $key = (string) $key;
        if ($key === '') { return; }
        $base = famiStorageBase();
        $abs = realpath($base . '/' . $key);
        $baseReal = realpath($base);
        if ($abs !== false && $baseReal !== false && strpos($abs, $baseReal) === 0 && is_file($abs)) { @unlink($abs); }
    }
}

if (!function_exists('famiUploadedExt')) {
    /**
     * Extension du fichier réellement envoyé sur ce champ ('' si aucun).
     * Sert à EXPLIQUER un refus : sans ça, un .pptx déposé disparaissait en silence.
     */
    function famiUploadedExt($field)
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        return strtolower(pathinfo((string) ($_FILES[$field]['name'] ?? ''), PATHINFO_EXTENSION));
    }
}

if (!function_exists('famiStoreUploadedFileAt')) {
    /**
     * Range UN fichier déjà téléversé dans le stockage, à partir de son chemin
     * temporaire explicite. C'est la variante « indexable » de
     * handleModuleFileUpload() : elle accepte $_FILES['x']['tmp_name'][$i], donc
     * elle fonctionne avec un champ <input multiple> — ce que la version d'origine,
     * qui lisait $_FILES[$field] directement, ne pouvait pas faire.
     *
     * @param string $tmpPath   chemin temporaire du fichier reçu
     * @param string $origName  nom d'origine (sert à déduire l'extension)
     * @param array  $allowedMap  mime => extension autorisée
     * @return string|null      clé relative dans le stockage, ou null si refusé
     */
    function famiStoreUploadedFileAt($tmpPath, $origName, array $allowedMap, $maxSize, $subdir, $namePrefix = '')
    {
        $tmpPath = (string) $tmpPath;
        if ($tmpPath === '' || !is_file($tmpPath)) { return null; }
        $size = @filesize($tmpPath);
        if ($size === false || $size <= 0 || $size > $maxSize) { return null; }

        $mime = function_exists('mime_content_type') ? @mime_content_type($tmpPath) : '';
        if (isset($allowedMap[$mime])) {
            $ext = $allowedMap[$mime];
        } else {
            $ext = strtolower(pathinfo((string) $origName, PATHINFO_EXTENSION));
            if (!in_array($ext, array_values($allowedMap), true)) { return null; }
        }

        $dir = famiStorageBase() . '/modules/' . $subdir;
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $prefix = ($namePrefix !== '') ? $namePrefix : $subdir;
        $name = $prefix . '_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $ext;
        $dest = $dir . '/' . $name;

        // is_uploaded_file() distingue un vrai upload HTTP d'un fichier local :
        // le premier DOIT passer par move_uploaded_file (sécurité), le second est
        // simplement déplacé (cas d'un import depuis un fichier déjà sur le disque).
        $ok = is_uploaded_file($tmpPath) ? @move_uploaded_file($tmpPath, $dest) : @rename($tmpPath, $dest);
        if (!$ok) { return null; }

        // Clé relative (servie par media.php) — plus de préfixe « uploads/ ».
        return 'modules/' . $subdir . '/' . $name;
    }
}

if (!function_exists('spawnVideoTranscode')) {
    /**
     * Lance la compression vidéo 720p (ffmpeg) EN TÂCHE DE FOND : l'utilisateur n'attend pas.
     * Le worker video_transcode.php ré-encode la source brute, puis enchaîne la
     * transcription Whisper et met à jour l'état du module.
     */
    function spawnVideoTranscode($rawKey, $moduleId)
    {
        $rawKey = (string) $rawKey;
        $moduleId = (int) $moduleId;
        if ($rawKey === '' || $moduleId <= 0) {
            return;
        }
        // Sous Windows (dév local), on ne lance pas : le worker tourne sur le serveur Linux (Railway/OVH).
        if (stripos(PHP_OS, 'WIN') === 0 || !function_exists('exec')) {
            return;
        }
        $worker = famiPublicDir() . '/video_transcode.php';
        $cmd = 'nohup php ' . escapeshellarg($worker) . ' ' . escapeshellarg($rawKey) . ' ' . $moduleId . ' > /dev/null 2>&1 &';
        @exec($cmd);
    }
}

if (!function_exists('handleModuleFileUpload')) {
    /** Upload générique d'un fichier de module (pdf, vidéo) -> clé relative ou null. */
    function handleModuleFileUpload($field, array $allowedMap, $maxSize, $subdir, $namePrefix = '')
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $f = $_FILES[$field];
        if ($f['error'] !== UPLOAD_ERR_OK) { return null; }
        return famiStoreUploadedFileAt($f['tmp_name'], $f['name'], $allowedMap, $maxSize, $subdir, $namePrefix);
    }
}
