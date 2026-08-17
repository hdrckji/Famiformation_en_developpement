<?php
// ============================================================
// FAMICARD — LA PHOTO DE PROFIL.
//
// UN SEUL ENDROIT QUI SAIT DÉPOSER UNE PHOTO. Elle se dépose depuis deux
// écrans — la création d'un collaborateur et la modification de sa fiche — et
// ces deux-là doivent écrire au même endroit, avec les mêmes contrôles et la
// même compression. Deux implantations, et c'est celle qu'on regarde le moins
// qui laisse passer un fichier de 40 Mo, ou range l'image là où le site ne la
// trouvera pas.
//
// ⚠️ LE STOCKAGE EST CELUI DU SITE, volontairement : même dossier sur le
// volume, même colonne `utilisateurs.photo_profil`, même compression. Un second
// emplacement, et la photo affichée par FamiFormation ne serait plus celle
// déposée ici. On déplace l'écran, jamais le stockage.
// ============================================================

if (!function_exists('famicardDossierPhotos')) {
    /**
     * Le dossier où vivent les photos, en absolu.
     *
     * Le repli passe par famicardRacineSite() : « __DIR__ » désignerait
     * Famicard, donc un uploads/ qui n'est pas celui du site — les photos
     * déposées deviendraient invisibles pour FamiFormation.
     *
     * @return array [base de stockage, dossier des photos]
     */
    function famicardDossierPhotos()
    {
        $base = defined('FAMI_STORAGE_BASE')
            ? rtrim(FAMI_STORAGE_BASE, '/')
            : (famicardRacineSite() . '/uploads');

        return [$base, $base . '/divers/profils/'];
    }
}

if (!function_exists('famicardCheminPhoto')) {
    /**
     * Chemin absolu d'une photo déjà enregistrée.
     *
     * Deux formes cohabitent en base : la clé du volume
     * (« divers/profils/user_12.jpg ») et l'ancien chemin public
     * (« uploads/... »). On reconnaît la seconde à son préfixe plutôt que de
     * supposer laquelle est là : les deux existent dans les mêmes lignes.
     */
    function famicardCheminPhoto($valeur)
    {
        $valeur = (string) $valeur;
        if ($valeur === '') {
            return '';
        }
        list($base, ) = famicardDossierPhotos();

        return (strpos($valeur, 'uploads/') === 0)
            ? famicardRacineSite() . '/' . $valeur
            : $base . '/' . $valeur;
    }
}

if (!function_exists('famicardEnregistrePhoto')) {
    /**
     * Contrôle, range et compresse une photo envoyée, puis l'écrit sur la fiche.
     *
     * @param array  $fichier  une entrée de $_FILES
     * @param string $ancienne la photo actuelle (pour la remplacer), ou ''
     * @param string $erreur   rempli en cas de refus (par référence)
     * @return string le chemin relatif enregistré, ou '' si rien n'a été écrit
     */
    function famicardEnregistrePhoto(PDO $db, $userId, array $fichier, $ancienne, &$erreur)
    {
        $erreur = '';
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '';
        }

        // Aucun fichier choisi : ce n'est pas une erreur, c'est le cas normal.
        if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        $tailleMax = 5 * 1024 * 1024; // 5 Mo
        $typesAutorises = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (($fichier['error'] ?? 1) !== UPLOAD_ERR_OK) {
            $erreur = "L'envoi de la photo n'a pas abouti.";
            return '';
        }
        if (($fichier['size'] ?? 0) > $tailleMax) {
            $erreur = 'Photo trop lourde (5 Mo maximum).';
            return '';
        }
        if (!in_array((string) ($fichier['type'] ?? ''), $typesAutorises, true)) {
            $erreur = 'Format de photo non accepté : JPEG, PNG, GIF ou WebP.';
            return '';
        }
        // Le type annoncé par le navigateur se falsifie ; le contenu, non.
        if (!@getimagesize($fichier['tmp_name'])) {
            $erreur = "Ce fichier n'est pas une image.";
            return '';
        }

        list(, $dossier) = famicardDossierPhotos();
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }

        $ext = strtolower(pathinfo((string) $fichier['name'], PATHINFO_EXTENSION));
        $nomFichier = 'user_' . $userId . '_' . time() . '.' . $ext;
        $destination = $dossier . $nomFichier;

        if (!move_uploaded_file($fichier['tmp_name'], $destination)) {
            $erreur = "La photo n'a pas pu être enregistrée.";
            return '';
        }

        // L'ancienne part APRÈS que la nouvelle est en place : dans l'autre
        // sens, un échec d'écriture laisserait la fiche sans photo du tout.
        $cheminAncienne = famicardCheminPhoto($ancienne);
        if ($cheminAncienne !== '' && is_file($cheminAncienne) && $cheminAncienne !== $destination) {
            @unlink($cheminAncienne);
        }

        $compress = famicardRacineSite() . '/includes/compress.php';
        if (is_file($compress)) {
            require_once $compress;
            if (function_exists('famiCompressImageFile')) {
                famiCompressImageFile($destination, 600);
            }
        }

        $cheminRelatif = 'divers/profils/' . $nomFichier;
        $db->prepare("UPDATE utilisateurs SET photo_profil = ? WHERE id = ?")
           ->execute([$cheminRelatif, $userId]);

        return $cheminRelatif;
    }
}

if (!function_exists('famicardPhotoUrl')) {
    /**
     * L'ADRESSE DE LA PHOTO DE QUELQU'UN — une seule façon de la construire.
     *
     * ⚠️ ELLE PASSAIT PAR `media.php` DU SITE PRINCIPAL, en URL absolue vers
     * www. Or media.php exige une session, et le cookie de session est
     * host-only : depuis famicard.famiformation.com, le navigateur n'envoie
     * rien à www, media.php répond 403, et la photo ne s'affiche pas. On voyait
     * la silhouette grise en croyant que l'envoi avait échoué.
     *
     * Famicard sert donc ses photos lui-même (photo.php), par une adresse
     * RELATIVE : elle marche sur les deux hôtes, avec la session de l'hôte où
     * l'on se trouve.
     *
     * @param string $repere un marqueur de fraîcheur. Sans lui, le navigateur
     *                       réaffiche l'image qu'il a en cache après un
     *                       changement, et l'on croit que l'envoi n'a rien fait.
     */
    function famicardPhotoUrl($userId, $repere = '')
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '';
        }
        $url = 'photo.php?id=' . $userId;
        if ((string) $repere !== '') {
            $url .= '&v=' . rawurlencode((string) $repere);
        }
        return $url;
    }
}
