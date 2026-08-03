<?php
// ============================================================
// La Panne — collecte des adresses e-mail du personnel.
//
// Remplace le Google Form utilisé par emails/index.html : la saisie se fait
// désormais sur le site, et les données restent chez nous.
//
// POURQUOI LA BASE DE DONNÉES ET PAS UN FICHIER JSON — le disque du conteneur
// Railway est éphémère : tout fichier écrit à l'exécution disparaît au
// redéploiement suivant. Un fichier .json perdrait donc les adresses collectées
// à chaque mise en ligne. La table survit, elle.
//
// Les colonnes reprennent exactement celles de l'export Excel :
//   created_at    -> Horodateur
//   nom / prenom  -> Nom / Naam, Prénom / Voornaam
//   email         -> Adresse e-mail
//   ticket_remis  -> Ticket remis (FALSE / TRUE)
// ============================================================

require_once __DIR__ . '/../../config.php';

if (!function_exists('lapanneEnsureTable')) {
    function lapanneEnsureTable(PDO $db)
    {
        static $fait = false;
        if ($fait) {
            return;
        }
        $fait = true;
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS lapanne_emails (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nom VARCHAR(120) NOT NULL,
                    prenom VARCHAR(120) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    ticket_remis TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_lapanne_email (email),
                    INDEX idx_lapanne_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
            );
        } catch (Exception $e) {
            // Base indisponible : les pages afficheront un message, pas une erreur brute.
        }
    }
}

if (!function_exists('lapanneNettoyerNom')) {
    /** Espaces multiples réduits, majuscule initiale — « sobiecki » devient « Sobiecki ». */
    function lapanneNettoyerNom($valeur)
    {
        $valeur = trim(preg_replace('/\s+/u', ' ', (string) $valeur));
        if ($valeur === '') {
            return '';
        }
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper(mb_substr($valeur, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($valeur, 1, null, 'UTF-8');
        }
        return ucfirst($valeur);
    }
}

if (!function_exists('lapanneEnregistrer')) {
    /**
     * Enregistre une adresse. Renvoie ['ok' => bool, 'etat' => string].
     *
     * « etat » vaut 'ajoute', 'deja_inscrit', ou un code d'erreur de saisie.
     * Une adresse déjà présente n'est pas une erreur : la personne a sans doute
     * rempli le formulaire deux fois. On le lui dit sans la bloquer.
     */
    function lapanneEnregistrer(PDO $db, $nom, $prenom, $email)
    {
        lapanneEnsureTable($db);

        $nom = lapanneNettoyerNom($nom);
        $prenom = lapanneNettoyerNom($prenom);
        $email = trim(mb_strtolower((string) $email, 'UTF-8'));

        if ($nom === '' || $prenom === '') {
            return ['ok' => false, 'etat' => 'nom_manquant'];
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'etat' => 'email_invalide'];
        }
        if (mb_strlen($nom) > 120 || mb_strlen($prenom) > 120 || mb_strlen($email) > 190) {
            return ['ok' => false, 'etat' => 'trop_long'];
        }

        try {
            $stmt = $db->prepare(
                'INSERT INTO lapanne_emails (nom, prenom, email) VALUES (?, ?, ?)'
            );
            $stmt->execute([$nom, $prenom, $email]);
            return ['ok' => true, 'etat' => 'ajoute'];
        } catch (PDOException $e) {
            // 23000 = violation de contrainte : l'adresse est déjà enregistrée.
            if ($e->getCode() === '23000') {
                return ['ok' => true, 'etat' => 'deja_inscrit'];
            }
            return ['ok' => false, 'etat' => 'erreur_base'];
        }
    }
}

if (!function_exists('lapanneListe')) {
    /** Toutes les inscriptions, la plus récente d'abord. */
    function lapanneListe(PDO $db)
    {
        lapanneEnsureTable($db);
        try {
            return $db->query(
                'SELECT id, nom, prenom, email, ticket_remis, created_at
                   FROM lapanne_emails
                  ORDER BY created_at DESC, id DESC'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('lapanneMarquerTicket')) {
    /** Coche ou décoche « Ticket remis » pour une inscription. */
    function lapanneMarquerTicket(PDO $db, $id, $remis)
    {
        lapanneEnsureTable($db);
        try {
            $stmt = $db->prepare('UPDATE lapanne_emails SET ticket_remis = ? WHERE id = ?');
            return $stmt->execute([$remis ? 1 : 0, (int) $id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('lapanneSupprimer')) {
    /** Retire une inscription (doublon de saisie, erreur de frappe). */
    function lapanneSupprimer(PDO $db, $id)
    {
        lapanneEnsureTable($db);
        try {
            return $db->prepare('DELETE FROM lapanne_emails WHERE id = ?')->execute([(int) $id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('lapanneAccesRh')) {
    /** Seuls l'administration et les teamcoachs voient la liste des adresses. */
    function lapanneAccesRh()
    {
        return isset($_SESSION['user_id'])
            && in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'teamcoach'], true);
    }
}
