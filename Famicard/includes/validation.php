<?php
// ============================================================
// FAMICARD — LA VALIDATION DE SA FICHE PAR LE COLLABORATEUR.
//
// À sa PREMIÈRE CONNEXION — sur n'importe quelle plateforme, FamiFormation,
// FamiJob ou celle d'après — on montre à la personne ce qu'on sait d'elle, et
// on lui demande de le confirmer. C'est le seul moment où quelqu'un relit
// vraiment sa fiche : ni l'admin qui l'a créée, ni personne d'autre, ne peut
// savoir que la ville a changé ou que le prénom est mal orthographié.
//
// Puis UNE FOIS PAR AN. Une base juste ne le reste pas toute seule : sans
// rendez-vous, une adresse fausse le reste jusqu'au jour où elle sert. Et
// côté RGPD, c'est ce qui prouve que les données sont tenues à jour.
//
// ⚠️ CE FICHIER NE DÉPEND PAS DE FAMICARD. Il ne demande qu'un PDO, parce
// qu'il est appelé depuis les AUTRES plateformes : leur accueil vérifie si la
// personne doit valider, et affiche le rappel. Y introduire une fonction de
// Famicard obligerait chacune à charger toute sa configuration.
//
// ── PHOTO ET EMAIL : DEUX RÉGIMES DIFFÉRENTS ────────────────────────────────
//
// L'EMAIL sert au fonctionnement du compte : sans lui, pas de lien
// d'activation, pas de relance de mot de passe. On peut donc insister.
//
// LA PHOTO relève du CONSENTEMENT (décision de Jimmy, et c'est le droit) : on
// ne force personne à donner son image. D'où un bouton « je ne souhaite pas
// mettre de photo », dont le refus est ENREGISTRÉ — un consentement, ça se
// prouve, et un refus aussi. Les rappels cessent alors : insister après un
// refus, c'est transformer un consentement libre en consentement arraché.
// Il reste révocable à tout moment.
//
// ⚠️ RIEN N'EST BLOQUANT, jamais. Le rappel est un bandeau qu'on ferme. La
// personne peut travailler sans avoir rien complété : une plateforme qui prend
// quelqu'un en otage pour une photo se fait contourner, pas obéir.
// ============================================================

if (!function_exists('famicardAssureValidation')) {
    /**
     * Crée la table de suivi.
     *
     * ⚠️ À N'APPELER QUE depuis une page d'administration ou l'écran de récap :
     * c'est de la DDL, elle n'a rien à faire dans le chemin d'un accueil que
     * tout le monde ouvre à chaque visite.
     */
    function famicardAssureValidation(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return true;
        }
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS famicard_validation (
                    user_id INT NOT NULL,
                    valide_le DATETIME NULL,
                    commentaire TEXT NULL,
                    commentaire_le DATETIME NULL,
                    commentaire_lu_le DATETIME NULL,
                    commentaire_lu_par INT NULL,
                    photo_refus_le DATETIME NULL,
                    PRIMARY KEY (user_id),
                    INDEX idx_famicard_validation_le (valide_le)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Exception $e) {
            return false;
        }
        $fait = true;
        return true;
    }
}

if (!function_exists('famicardValidation')) {
    /** La ligne de suivi d'une personne, ou un tableau vide si elle n'a jamais validé. */
    function famicardValidation(PDO $db, $userId)
    {
        try {
            $st = $db->prepare("SELECT * FROM famicard_validation WHERE user_id = ? LIMIT 1");
            $st->execute([(int) $userId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);
            return $ligne ?: [];
        } catch (Exception $e) {
            // Table pas encore créée : personne n'a validé, ce qui est vrai.
            return [];
        }
    }
}

if (!function_exists('famicardDoitValiderFiche')) {
    /**
     * ⭐ LE POINT D'ENTRÉE DES AUTRES PLATEFORMES.
     *
     * « Cette personne doit-elle (re)voir sa fiche ? » Oui si elle ne l'a
     * jamais validée, ou si sa dernière validation date de plus d'un an.
     *
     * ⚠️ Renvoie FALSE si la table n'existe pas encore ou si la base ne répond
     * pas. Une plateforme ne doit pas devenir inaccessible parce qu'une table
     * de Famicard manque : le récap est un service rendu, pas un péage.
     */
    function famicardDoitValiderFiche(PDO $db, $userId, $mois = 12)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }
        try {
            // ⚠️ UNE AGENCE N'A PAS DE FICHE À RELIRE. Lui demander de
            // confirmer son prénom et de déposer une photo n'aurait aucun sens :
            // ce n'est pas quelqu'un, c'est l'accès d'une société.
            $st = $db->prepare("SELECT role FROM utilisateurs WHERE id = ? LIMIT 1");
            $st->execute([$userId]);
            if ((string) $st->fetchColumn() === 'agence_interim') {
                return false;
            }

            $st = $db->prepare(
                "SELECT valide_le FROM famicard_validation WHERE user_id = ? LIMIT 1"
            );
            $st->execute([$userId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }

        if (!$ligne || empty($ligne['valide_le'])) {
            return true; // jamais validée
        }
        $quand = strtotime((string) $ligne['valide_le']);
        if (!$quand) {
            return true;
        }
        return ($quand < strtotime('-' . max(1, (int) $mois) . ' months'));
    }
}

if (!function_exists('famicardEnregistreValidationFiche')) {
    /**
     * La personne confirme sa fiche, avec un mot facultatif.
     *
     * Le commentaire est daté et marqué NON LU : c'est ce qui le fait remonter
     * à l'administrateur. Sans ça, un « ma ville a changé mais je ne sais pas
     * comment l'écrire » serait enregistré et jamais vu.
     */
    function famicardEnregistreValidationFiche(PDO $db, $userId, $commentaire = '')
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }
        $commentaire = trim((string) $commentaire);

        try {
            famicardAssureValidation($db);
            $db->prepare(
                "INSERT INTO famicard_validation (user_id, valide_le, commentaire, commentaire_le)
                 VALUES (?, NOW(), ?, ?)
                 ON DUPLICATE KEY UPDATE
                    valide_le = NOW(),
                    commentaire = VALUES(commentaire),
                    commentaire_le = VALUES(commentaire_le),
                    commentaire_lu_le = NULL,
                    commentaire_lu_par = NULL"
            )->execute([
                $userId,
                $commentaire !== '' ? $commentaire : null,
                $commentaire !== '' ? date('Y-m-d H:i:s') : null,
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('famicardRefusePhoto')) {
    /**
     * Enregistre — ou retire — le refus de déposer une photo.
     *
     * ⚠️ ON GARDE LA DATE, pas un simple « oui ». Un consentement (et son
     * refus) se prouve : « depuis quand » est la première question qu'on posera
     * le jour où quelqu'un demandera des comptes.
     */
    function famicardRefusePhoto(PDO $db, $userId, $refuse = true)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }
        try {
            famicardAssureValidation($db);
            $db->prepare(
                "INSERT INTO famicard_validation (user_id, photo_refus_le)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE photo_refus_le = VALUES(photo_refus_le)"
            )->execute([$userId, $refuse ? date('Y-m-d H:i:s') : null]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('famicardManquesPersonnels')) {
    /**
     * Ce qui manque et que SEULE la personne peut fournir.
     *
     * @param array $ligne      la ligne `utilisateurs`
     * @param array $validation la ligne famicard_validation (voir famicardValidation)
     * @return array ['photo' => bool, 'email' => bool] — true = il manque
     */
    function famicardManquesPersonnels(array $ligne, array $validation = [])
    {
        $photoRefusee = !empty($validation['photo_refus_le']);

        return [
            // Une photo refusée n'est plus « manquante » : c'est une décision.
            // Continuer à la compter comme un trou ferait revenir les rappels
            // et transformerait un choix en oubli.
            'photo' => (trim((string) ($ligne['photo_profil'] ?? '')) === '') && !$photoRefusee,
            'email' => (trim((string) ($ligne['email'] ?? '')) === ''),
        ];
    }
}

if (!function_exists('famicardRappelHtml')) {
    /**
     * LE BANDEAU DE RAPPEL, prêt à afficher sur n'importe quelle plateforme.
     *
     * Styles en ligne et aucune dépendance : il s'affiche pareil sur
     * FamiFormation, FamiJob et Famicard, sans que chacune ait à copier du CSS
     * qui divergera. Chaîne vide s'il n'y a rien à rappeler.
     *
     * ⚠️ IL SE FERME, et il revient à la connexion suivante — pas à chaque
     * page. Un bandeau qu'on ne peut pas faire taire devient un bandeau qu'on
     * ne lit plus.
     *
     * @param string $urlFiche où envoyer la personne pour compléter
     */
    function famicardRappelHtml(PDO $db, $userId, $urlFiche)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '';
        }
        if (!empty($_SESSION['famicard_rappel_ferme'])) {
            return '';
        }

        try {
            $st = $db->prepare("SELECT photo_profil, email, role FROM utilisateurs WHERE id = ? LIMIT 1");
            $st->execute([$userId]);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return '';
        }
        // Même raison que ci-dessus : on ne réclame pas sa photo à une société.
        if (!$ligne || (string) ($ligne['role'] ?? '') === 'agence_interim') {
            return '';
        }

        $manques = famicardManquesPersonnels($ligne, famicardValidation($db, $userId));
        $quoi = [];
        if ($manques['email']) { $quoi[] = 'ton adresse email'; }
        if ($manques['photo']) { $quoi[] = 'ta photo'; }
        if (!$quoi) {
            return '';
        }

        $texte = 'Il manque ' . implode(' et ', $quoi) . ' sur ta carte.';
        $ech = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

        // La fermeture repasse par le SERVEUR (la page courante, avec un
        // paramètre) plutôt que par du JavaScript : sans ça, le bandeau
        // reviendrait à la page suivante et le « ✕ » ne servirait à rien.
        $ici = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $ferme = $ici . (strpos($ici, '?') === false ? '?' : '&') . 'famicard_rappel=ferme';

        return '<div id="famicard-rappel" style="background:#fff8e1;border-bottom:2px solid #ffd54f;'
             . 'color:#6a5400;padding:11px 16px;font-family:Open Sans,Arial,sans-serif;font-size:.92rem;'
             . 'display:flex;align-items:center;gap:12px;flex-wrap:wrap;">'
             . '<span style="flex:1;min-width:200px;">⚠️ ' . $ech($texte) . '</span>'
             . '<a href="' . $ech($urlFiche) . '" style="background:#2d5a37;color:#fff;text-decoration:none;'
             . 'padding:7px 16px;border-radius:999px;font-weight:700;">Compléter</a>'
             . '<a href="' . $ech($ferme) . '" title="Masquer jusqu\'à la prochaine connexion" '
             . 'style="color:#6a5400;text-decoration:none;font-size:1.1rem;padding:0 6px;">✕</a>'
             . '</div>';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// LA FERMETURE DU BANDEAU, traitée au chargement du fichier.
//
// Posée ici et pas dans un appel séparé : ce fichier est inclus par TROIS
// plateformes, et une étape supplémentaire à réclamer à chacune est une étape
// que l'une des trois oubliera. Le drapeau vit en SESSION — donc jusqu'à la
// prochaine connexion, ce qui est exactement la promesse faite au « ✕ ».
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['famicard_rappel']) && $_GET['famicard_rappel'] === 'ferme'
    && session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['famicard_rappel_ferme'] = 1;
}
