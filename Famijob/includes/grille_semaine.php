<?php
// ============================================================
// LA GRILLE DE LA SEMAINE — le rangement des créneaux sous leur secteur.
//
// Deux écrans montrent la même image, celle du classeur Excel dont vient toute
// l'équipe : le MATCHING (qui affecte les gens) et les DEMANDES D'HORAIRES (qui
// les crée). Ce fichier porte ce qu'ils ont en commun — répondre à « sous quel
// bandeau va ce créneau » — pour que la réponse ne puisse pas différer d'un
// écran à l'autre.
//
// ⚠️ POURQUOI CE N'EST PAS TRIVIAL. `interim_shift_requests.department_name`
// est du TEXTE LIBRE saisi au fil des années. Il peut contenir :
//   • un nom de département      → « Caisse », « Terreau » ;
//   • un nom de SECTEUR          → « Famizoo », quand la demande ne précise pas
//     le département ; ces créneaux vont directement sous le bandeau vert ;
//   • une orthographe abandonnée → « Plantes extérieur » au singulier (81
//     créneaux), « Garden » (45).
//
// D'où deux niveaux de rattrapage : une comparaison NORMALISÉE (sans accent,
// sans casse, sans ponctuation), puis une table d'alias pour ce qu'elle ne peut
// pas deviner.
//
// Ces alias LISENT la base, ils ne la corrigent pas. Le jour où
// `interim_shift_requests` sera nettoyée, ils deviendront inutiles sans rien
// casser.
// ============================================================

if (!function_exists('grilleSemaineAlias')) {
    /**
     * Noms qui ne correspondent à rien en base, et le secteur auquel ils se
     * rattachent. À compléter à la main quand un nom orphelin apparaît : c'est
     * plus honnête qu'un rapprochement approximatif qui rangerait au hasard.
     */
    function grilleSemaineAlias()
    {
        return [
            'plantes exterieur' => 'Plantes extérieures',   // singulier
        ];
    }
}

if (!function_exists('grilleSemaineCle')) {
    /** Forme comparable d'un libellé : sans accent, sans casse, sans ponctuation. */
    function grilleSemaineCle($s)
    {
        if (function_exists('secteursNormalise')) {
            return secteursNormalise($s);
        }
        return strtolower(trim((string) $s));
    }
}

if (!function_exists('grilleSemaineRangement')) {
    /**
     * Prépare de quoi ranger n'importe quel `department_name`.
     *
     * secteursCharge() est appelée ici : sans elle, secteursListe() n'est même
     * pas définie et tout se retrouverait sous « Sans secteur » sans qu'aucun
     * message ne le signale.
     */
    function grilleSemaineRangement(PDO $db)
    {
        if (function_exists('secteursCharge')) {
            secteursCharge();
        }
        // ⚠️ « true » : INCLURE les secteurs sans departement. Par defaut
        // secteursListe() les ecarte — utile pour un menu, faux ici : un
        // secteur sans departement doit quand meme pouvoir recevoir des
        // horaires, puisqu'on a justement le droit de ne pas preciser.
        $arbre = function_exists('secteursListe') ? secteursListe($db, true) : [];

        $rangement = [
            'arbre'    => $arbre,
            'secteurs' => [],   // clé normalisée => libellé du secteur
            'depts'    => [],   // clé normalisée => libellé du secteur parent
            'libelles' => [],   // clé normalisée => libellé propre du département
            'alias'    => grilleSemaineAlias(),
            'ordre'    => [],   // les secteurs, dans l'ordre de la base
        ];

        foreach ($arbre as $sec) {
            $nom = (string) $sec['nom'];
            $rangement['secteurs'][grilleSemaineCle($nom)] = $nom;
            $rangement['ordre'][] = $nom;
            foreach ($sec['departements'] as $dep) {
                $k = grilleSemaineCle($dep['nom']);
                $rangement['depts'][$k]    = $nom;
                $rangement['libelles'][$k] = (string) $dep['nom'];
            }
        }

        return $rangement;
    }
}

if (!function_exists('grilleSemaineResout')) {
    /**
     * Sous quel bandeau va ce créneau ?
     *
     * @return array ['secteur' => libellé vert, 'sous' => libellé jaune ou '']
     *
     * Un « sous » vide signifie : pas de bandeau jaune, les lignes suivent
     * directement le secteur. C'est le cas quand la demande porte le nom d'un
     * secteur, et quand un département porte le même nom que son secteur —
     * répéter « Plantes intérieures » en jaune sous « Plantes intérieures » en
     * vert n'apprend rien à personne.
     */
    function grilleSemaineResout($departmentName, array $rangement, $libelleSansSecteur = 'Sans secteur')
    {
        $dept = (string) $departmentName;
        $k = grilleSemaineCle($dept);

        if (isset($rangement['alias'][$k])) {
            return ['secteur' => $rangement['alias'][$k], 'sous' => ''];
        }
        if (isset($rangement['secteurs'][$k])) {
            // Libellé propre de la base, pas celui saisi dans la demande.
            return ['secteur' => $rangement['secteurs'][$k], 'sous' => ''];
        }
        if (isset($rangement['depts'][$k])) {
            $secteur = $rangement['depts'][$k];
            $propre  = $rangement['libelles'][$k] ?? $dept;
            $sous    = (grilleSemaineCle($propre) === grilleSemaineCle($secteur)) ? '' : $propre;
            return ['secteur' => $secteur, 'sous' => $sous];
        }

        // Inconnu : visible sous « Sans secteur » plutôt que disparu de la
        // semaine. Un créneau qu'on ne voit pas est un créneau que personne ne
        // pourvoit.
        return ['secteur' => $libelleSansSecteur, 'sous' => $dept];
    }
}

if (!function_exists('grilleSemaineOrdonne')) {
    /**
     * Les secteurs dans l'ordre de la base, « Sans secteur » en dernier, et
     * dans chaque secteur les créneaux SANS département d'abord — ils
     * appartiennent au secteur lui-même, ils suivent donc son bandeau au lieu
     * d'arriver après la liste des départements.
     */
    function grilleSemaineOrdonne(array $grille, array $rangement, $libelleSansSecteur = 'Sans secteur')
    {
        $ordonnee = [];
        $noms = $rangement['ordre'];
        $noms[] = $libelleSansSecteur;

        foreach ($noms as $secteur) {
            if (empty($grille[$secteur])) {
                continue;
            }
            $blocs = $grille[$secteur];
            $sansDept = [];
            if (isset($blocs[''])) {
                $sansDept[''] = $blocs[''];
                unset($blocs['']);
            }
            $ordonnee[$secteur] = $sansDept + $blocs;
        }

        return $ordonnee;
    }
}
