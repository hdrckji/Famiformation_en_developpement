<?php
// ============================================================
// module_intro.php — LE BANDEAU « À QUOI SERT CE MODULE ».
//
// Posé en haut de chaque module, il répond à la question qu'on se pose en
// arrivant : qu'est-ce que je fais ici, et qu'est-ce qui m'attend ?
//
// Il sert aussi à annoncer franchement qu'un module se remplit encore, plutôt
// que de laisser tomber quelqu'un sur une page vide sans explication.
//
// Styles EN LIGNE à dessein : ces pages ont chacune leur feuille de style, et
// certaines n'ont même pas de conteneur commun. Un bloc autonome s'y insère
// sans rien casser ni dépendre de classes qui n'existent pas partout.
// ============================================================

if (!function_exists('moduleIntro')) {
    /**
     * @param string $fr    Texte français.
     * @param string $nl    Texte néerlandais.
     * @param string $icone Emoji d'en-tête (celui de la tuile, pour faire le lien).
     */
    function moduleIntro($fr, $nl, $icone = '💡')
    {
        $txt = function_exists('t') ? t($fr, $nl) : $fr;
        $titre = function_exists('t') ? t('À quoi sert ce module ?', 'Waarvoor dient deze module?') : 'À quoi sert ce module ?';

        return '<div style="max-width:860px;margin:22px auto 26px;padding:20px 24px;'
            . 'background:linear-gradient(135deg,#2d5a37 0%,#4a7b55 100%);color:#fff;'
            . 'border-radius:20px;box-shadow:0 12px 30px rgba(27,54,36,.24);'
            . 'display:flex;align-items:flex-start;gap:16px;box-sizing:border-box;'
            . 'font-family:\'Open Sans\',Arial,sans-serif;">'
            . '<span style="font-size:2.1rem;line-height:1;flex:none;">' . $icone . '</span>'
            . '<div style="line-height:1.6;">'
            . '<div style="font-size:.74rem;text-transform:uppercase;letter-spacing:.1em;opacity:.82;margin-bottom:5px;">'
            . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="font-size:1.02rem;font-weight:600;">'
            . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div></div>';
    }
}

if (!function_exists('moduleIntroTextes')) {
    /**
     * Les textes, réunis ici pour qu'ils se corrigent en UN seul endroit —
     * et non éparpillés dans six pages qui divergeraient à la première retouche.
     * [clé => [icône, FR, NL]]
     */
    function moduleIntroTextes()
    {
        return [
            'onboarding' => ['🚀',
                "La présentation de l'entreprise : qui on est, nos valeurs.",
                'De voorstelling van het bedrijf: wie we zijn, onze waarden.'],
            'formation' => ['📅',
                "Réserve ton créneau et viens te former pour de vrai. De nouvelles dates arrivent très bientôt 👀",
                'Reserveer je moment en kom je echt bijscholen. Nieuwe data komen heel binnenkort 👀'],
            'magasin' => ['🛒',
                "Le savoir-faire de chaque rayon, réuni au même endroit. Du contenu arrive bientôt 🌱",
                'De knowhow van elke afdeling, op één plek verzameld. Er komt binnenkort inhoud aan 🌱'],
            'becosoft' => ['💻',
                "Maîtriser notre base de données : la retrouver, la lire et la faire parler.",
                'Onze database onder de knie krijgen: terugvinden, lezen en laten spreken.'],
            'securite' => ['🦺',
                "Tout le nécessaire pour travailler en sécurité. Ici, rien n'est optionnel.",
                'Alles wat je nodig hebt om veilig te werken. Hier is niets optioneel.'],
            'classement' => ['🏆',
                "Ta place face aux collègues, points à l'appui. Et ça se prépare dans l'ombre… 👀",
                'Jouw plaats tegenover de collega\'s, punten inbegrepen. En er wordt iets voorbereid… 👀'],
        ];
    }

    /** Rend le bandeau d'un module donné (rien si la clé est inconnue). */
    function moduleIntroDe($cle)
    {
        $tous = moduleIntroTextes();
        if (!isset($tous[$cle])) { return ''; }
        [$ico, $fr, $nl] = $tous[$cle];
        return moduleIntro($fr, $nl, $ico);
    }
}
