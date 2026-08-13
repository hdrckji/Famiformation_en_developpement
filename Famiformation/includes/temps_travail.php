<?php
// ============================================================
// LE TEMPS DE TRAVAIL EFFECTIF — ce qui est reellement preste dans un creneau.
//
// « 9h-19h » n'est pas dix heures de travail : la journee contient UNE HEURE DE
// PAUSE. Compter l'amplitude revenait a payer la pause et a annoncer a
// l'etudiant un total qu'il ne fera pas.
//
// TROIS REGLES, et une seule source pour toutes les pages :
//
//   1. UNE HEURE DE PAUSE se retire d'un creneau d'un seul tenant.
//
//   2. SAUF s'il est deja ecrit en deux morceaux. « 8h-12h / 13h-17h » dit
//      explicitement ou est la coupure : retirer une heure de plus la
//      compterait deux fois.
//
//   3. SAUF les creneaux listes dans tempsTravailSansPause(). 12h-17h se fait
//      d'une traite, sans pause — c'est le seul cas connu a ce jour. Ajouter
//      une paire a cette liste suffit a en declarer un autre.
//
// Et deux bornes : entre 3 h et 9 h de travail effectif par jour. En dehors, la
// saisie n'est pas refusee — elle est signalee, puis marquee. On ne bloque pas
// quelqu'un qui sait ce qu'il fait ; on l'empeche de le faire sans le voir.
//
// ⚠️ Fichier PARTAGE : FamiJob le charge par le meme chemin que secteurs.php.
// Les pages FamiJob rejouent ces regles en JavaScript pour prevenir AVANT
// l'enregistrement ; tempsTravailReglesJson() leur donne les memes constantes,
// pour que les deux versions ne puissent pas diverger.
// ============================================================

if (!function_exists('tempsTravailPause')) {
    /** Heures de pause retirees d'un creneau d'un seul tenant. */
    function tempsTravailPause()
    {
        return 1.0;
    }
}

if (!function_exists('tempsTravailMin')) {
    /** Minimum legal de travail effectif sur une journee, en heures. */
    function tempsTravailMin()
    {
        return 3.0;
    }
}

if (!function_exists('tempsTravailMax')) {
    /** Maximum legal de travail effectif sur une journee, en heures. */
    function tempsTravailMax()
    {
        return 9.0;
    }
}

if (!function_exists('tempsTravailSansPause')) {
    /**
     * Les creneaux qui se font d'une traite : [heure de debut, heure de fin].
     * Rien n'en est retire.
     */
    function tempsTravailSansPause()
    {
        return [
            [12.0, 17.0],
        ];
    }
}

if (!function_exists('tempsTravailPaires')) {
    /**
     * Les couples debut/fin lus dans un horaire ecrit a la main.
     *
     * Le champ est du TEXTE LIBRE : « 9h-17h », « 09:00 - 17:30 », « 9h a 17h »,
     * « 8h-12h / 13h-17h ». On reconnait les formes courantes et on renonce
     * proprement pour le reste — annoncer un total faux est pire que ne rien
     * annoncer.
     *
     * @return array|null liste de [debut, fin] en heures decimales, ou null
     */
    function tempsTravailPaires($texte)
    {
        if (!preg_match_all('/(\d{1,2})\s*[h:]\s*(\d{2})?/i', (string) $texte, $m, PREG_SET_ORDER)
            || count($m) < 2) {
            return null;
        }

        $paires = [];
        for ($i = 0; $i + 1 < count($m); $i += 2) {
            $debut = (int) $m[$i][1]     + (isset($m[$i][2])     && $m[$i][2]     !== '' ? ((int) $m[$i][2]) / 60 : 0);
            $fin   = (int) $m[$i + 1][1] + (isset($m[$i + 1][2]) && $m[$i + 1][2] !== '' ? ((int) $m[$i + 1][2]) / 60 : 0);

            if ($debut > 24 || $fin > 24) {
                return null;
            }
            if ($fin <= $debut) {
                $fin += 24;   // le creneau passe minuit
            }
            $duree = $fin - $debut;
            if ($duree <= 0 || $duree > 16) {
                return null;
            }
            $paires[] = [$debut, $fin];
        }

        return $paires ? $paires : null;
    }
}

if (!function_exists('tempsTravailAmplitude')) {
    /** Du debut a la fin, pause comprise. Null si l'horaire n'a pas ete compris. */
    function tempsTravailAmplitude($texte)
    {
        $paires = tempsTravailPaires($texte);
        if ($paires === null) {
            return null;
        }
        $total = 0.0;
        foreach ($paires as $p) {
            $total += $p[1] - $p[0];
        }
        return $total <= 16 ? $total : null;
    }
}

if (!function_exists('tempsTravailEffectif')) {
    /**
     * Ce qui est reellement preste : l'amplitude, moins la pause quand elle
     * s'applique. Null si l'horaire n'a pas ete compris.
     */
    function tempsTravailEffectif($texte)
    {
        $paires = tempsTravailPaires($texte);
        if ($paires === null) {
            return null;
        }

        $total = 0.0;
        foreach ($paires as $p) {
            $total += $p[1] - $p[0];
        }

        // Ecrit en deux morceaux : la coupure est deja dite, on n'en retire pas
        // une seconde.
        if (count($paires) > 1) {
            return $total;
        }

        foreach (tempsTravailSansPause() as $exception) {
            if (abs($paires[0][0] - $exception[0]) < 0.001 && abs($paires[0][1] - $exception[1]) < 0.001) {
                return $total;
            }
        }

        return max(0.0, $total - tempsTravailPause());
    }
}

if (!function_exists('tempsTravailHorsNormes')) {
    /**
     * Ce creneau sort-il des bornes legales ?
     *
     * @return array|null ['heures' => float, 'sens' => 'court'|'long'] ou null
     *
     * Null quand tout va bien — et null aussi quand l'horaire n'a pas ete
     * compris : on ne signale pas ce qu'on n'a pas su lire, une alerte sur un
     * texte mal interprete se ferait ignorer en trois jours.
     */
    function tempsTravailHorsNormes($texte)
    {
        $h = tempsTravailEffectif($texte);
        if ($h === null) {
            return null;
        }
        if ($h < tempsTravailMin() - 0.001) {
            return ['heures' => $h, 'sens' => 'court'];
        }
        if ($h > tempsTravailMax() + 0.001) {
            return ['heures' => $h, 'sens' => 'long'];
        }
        return null;
    }
}

if (!function_exists('tempsTravailFormate')) {
    /** « 8h30 » plutot que « 8.5 ». */
    function tempsTravailFormate($heures)
    {
        $heures = (float) $heures;
        $h = (int) floor($heures);
        $min = (int) round(($heures - $h) * 60);
        if ($min === 60) {
            $h++;
            $min = 0;
        }
        return $min === 0 ? ($h . 'h') : ($h . 'h' . str_pad((string) $min, 2, '0', STR_PAD_LEFT));
    }
}

if (!function_exists('tempsTravailReglesJson')) {
    /**
     * Les memes constantes, pour le JavaScript qui previent avant
     * l'enregistrement. Les regles ne sont ecrites qu'ICI ; le navigateur les
     * recoit, il ne les redefinit pas.
     */
    function tempsTravailReglesJson()
    {
        return json_encode([
            'pause'      => tempsTravailPause(),
            'min'        => tempsTravailMin(),
            'max'        => tempsTravailMax(),
            'sansPause'  => tempsTravailSansPause(),
        ]);
    }
}
