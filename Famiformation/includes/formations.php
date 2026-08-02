<?php
// ============================================================
// formations.php — RATTRAPAGES SUR LE CATALOGUE DES FORMATIONS.
//
// Le catalogue vit dans formations_sessions, et formation.php le filtre ainsi :
//
//     WHERE public_vise = 'tous' OR FIND_IN_SET(?, public_vise)
//     -- ? = le rôle courant, par exemple 'employe_magasin'
//
// FIND_IN_SET compare des jetons ENTIERS, pas des morceaux de chaîne. Toute
// valeur de public_vise qui n'est pas EXACTEMENT un nom de rôle ne correspond
// donc à personne, et la formation devient invisible sans que rien ne le
// signale — ni erreur, ni ligne vide : elle manque simplement à la liste.
// ============================================================

if (!function_exists('famiFixFormationPublics')) {
    /**
     * 🏷️ PUBLIC VISÉ — rattrapage unique du catalogue.
     *
     * Deux corrections, toutes deux passées une seule fois (drapeau en base,
     * comme famiFixLegacyDrafts() pour les modules) :
     *
     *  1. ÉTIQUETTES HISTORIQUES. « magasin » et « logistique » désignent
     *     exactement les mêmes personnes que « employe_magasin » et
     *     « employe_logistique » — ce sont les anciens noms des mêmes rôles.
     *     Mais pour FIND_IN_SET ce sont des chaînes différentes : 16 formations
     *     sur 18 étaient invisibles aux employés concernés, et seuls les admins
     *     les voyaient (« admin » étant le seul jeton de la liste à correspondre
     *     encore à un rôle réel). On renomme donc les anciens jetons.
     *
     *  2. FORMATION CAISSE. Elle n'était proposée qu'aux étudiants. Elle doit
     *     l'être aussi aux employés magasin. On AJOUTE le rôle sans retirer les
     *     étudiants, qui doivent continuer à la voir.
     *
     * Volontairement non bloquant : si quoi que ce soit échoue ici, la page des
     * formations doit continuer à s'afficher. Le drapeau n'est posé qu'après un
     * passage complet, donc un échec en cours de route sera rejoué.
     */
    function famiFixFormationPublics(PDO $db)
    {
        if (!function_exists('widgetGet') || !function_exists('widgetSet')) { return; }
        if (widgetGet($db, 'formations_publics_fixed', '0') === '1') { return; }

        // Ancien nom => nom actuel du MÊME rôle.
        $alias = [
            'magasin'    => 'employe_magasin',
            'logistique' => 'employe_logistique',
        ];

        try {
            $rows = $db->query("SELECT id, titre, public_vise FROM formations_sessions")
                       ->fetchAll(PDO::FETCH_ASSOC);
            $up = $db->prepare("UPDATE formations_sessions SET public_vise = ? WHERE id = ?");

            foreach ($rows as $r) {
                $avant = trim((string) $r['public_vise']);
                $titre = (string) $r['titre'];

                // 'tous' est déjà le cas le plus ouvert : rien à corriger.
                if ($avant === '' || $avant === 'tous') { continue; }

                $jetons = [];
                foreach (explode(',', $avant) as $j) {
                    $j = trim($j);
                    if ($j === '') { continue; }
                    if (isset($alias[$j])) { $j = $alias[$j]; }
                    if (!in_array($j, $jetons, true)) { $jetons[] = $j; }   // le renommage peut créer un doublon
                }

                // 🛒 Formation caisse : ouverte aussi aux employés magasin.
                if (stripos($titre, 'formation caisse') === 0
                    && !in_array('employe_magasin', $jetons, true)) {
                    $jetons[] = 'employe_magasin';
                }

                $apres = implode(',', $jetons);
                if ($apres !== $avant) { $up->execute([$apres, (int) $r['id']]); }
            }

            widgetSet($db, 'formations_publics_fixed', '1');
        } catch (Exception $e) {
            // non bloquant : on réessaiera au prochain affichage
        }
    }
}
