<?php
// ============================================================
// QUI A LE DROIT DE LIRE UN NOM.
//
// Les comptes AGENCE (`agence_interim`) travaillent dans les memes ecrans que
// Famiflora, sur le meme planning. Ils doivent y voir ce qui est pourvu — sinon
// ils proposeraient quelqu'un sur une place deja prise — mais PAS QUI le
// pourvoit quand la personne vient d'une agence concurrente.
//
// D'ou la distinction entre deux choses qu'on confond vite :
//   • la place est OCCUPEE          → tout le monde le voit ;
//   • la place est occupee PAR X    → seulement Famiflora, et l'agence de X.
//
// ⚠️ MASQUER N'EST PAS VIDER. Une place dont on efface le nom sans rien dire
// ressemble a une place libre : c'est le meilleur moyen de la promettre deux
// fois. Partout ou un nom est masque, l'ecran doit montrer que la place est
// prise — d'ou famijobLibelleOccupe() et une couleur distincte.
//
// Cette regle ne vaut QUE pour les comptes agence. Un admin ou un teamcoach de
// Famiflora voit tout : ce sont eux qui arbitrent.
// ============================================================

if (!function_exists('famijobEstCompteAgence')) {
    /** Ce role est-il un compte d'agence exterieure ? */
    function famijobEstCompteAgence($role)
    {
        return (string) $role === 'agence_interim';
    }
}

if (!function_exists('famijobMemeAgence')) {
    /**
     * Deux noms d'agence designent-ils la meme ?
     *
     * Comparaison souple (espaces, casse) : `utilisateurs.interim` et
     * `interim_shift_assignments.agency_name` sont saisis a deux endroits
     * differents, « Randstad » et « randstad » ne doivent pas separer une
     * agence de ses propres interimaires.
     */
    function famijobMemeAgence($a, $b)
    {
        // strtolower() en repli : mbstring est presente en production, pas
        // partout ailleurs. Un nom d'agence est de l'ASCII dans les faits, et
        // une comparaison ratee masquerait un nom — jamais l'inverse.
        $bas = function ($v) {
            $v = trim((string) $v);
            return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
        };
        $a = $bas($a);
        $b = $bas($b);
        return $a !== '' && $a === $b;
    }
}

if (!function_exists('famijobNomLisible')) {
    /**
     * Le nom a afficher pour une place, du point de vue de celui qui regarde.
     *
     * @param string $nom            nom de la personne affectee ('' si libre)
     * @param string $agenceCreneau  agence qui fournit cette personne
     * @param string $roleLecteur    role de celui qui regarde
     * @param string $agenceLecteur  son agence (vide s'il n'en a pas)
     *
     * @return array ['nom' => string, 'masque' => bool]
     *
     * 'masque' vaut true UNIQUEMENT quand quelqu'un occupe la place et qu'on
     * n'a pas le droit de savoir qui. Une place libre n'est pas masquee : elle
     * est libre, et ca se dit autrement.
     */
    function famijobNomLisible($nom, $agenceCreneau, $roleLecteur, $agenceLecteur)
    {
        $nom = trim((string) $nom);

        if ($nom === '' || !famijobEstCompteAgence($roleLecteur)) {
            return ['nom' => $nom, 'masque' => false];
        }
        if (famijobMemeAgence($agenceCreneau, $agenceLecteur)) {
            return ['nom' => $nom, 'masque' => false];
        }

        return ['nom' => '', 'masque' => true];
    }
}

if (!function_exists('famijobLibelleOccupe')) {
    /** Ce qui s'affiche a la place d'un nom masque. */
    function famijobLibelleOccupe()
    {
        $nl = function_exists('famiLang') && famiLang() === 'nl';
        return $nl ? 'Bezet' : 'Occupé';
    }
}
