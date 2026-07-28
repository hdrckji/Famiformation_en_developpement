<?php
// ============================================================
// mail_html.php — REND LES MAILS LISIBLES PARTOUT (surtout Outlook).
//
// POURQUOI CE FICHIER EXISTE
// Nos mails sont écrits en HTML moderne (dégradés, coins arrondis, flexbox de
// mise en page…). Outlook pour Windows n'utilise PAS un moteur de navigateur :
// il rend le HTML avec le moteur de Microsoft Word. Résultat, sans traitement :
//   • les dégradés (linear-gradient) sont IGNORÉS → l'en-tête vert devient
//     transparent, et le titre écrit en BLANC devient INVISIBLE sur fond blanc.
//     C'est la cause n°1 des mails « vides » chez les destinataires Outlook.
//   • max-width est ignoré → la carte s'étale sur toute la largeur de l'écran.
//   • les boutons (padding sur un <a>) sont écrasés → texte collé, illisible.
//   • une image sans attribut width s'affiche à sa taille réelle (logo géant).
//
// CE QU'ON FAIT
// On ne réécrit AUCUN template : on post-traite le HTML juste avant l'envoi,
// dans sendMail(). Un seul endroit à maintenir, et tous les mails du site
// (plateforme, FamiJob, quiz, crons) en profitent, y compris les futurs.
//
// On produit aussi une VRAIE version texte : un mail HTML sans alternative
// texte est un signal de spam classique, et certains clients/serveurs très
// stricts n'affichent que celle-là.
// ============================================================

if (!function_exists('famiMailFirstColor')) {
    /**
     * Première couleur d'un bout de CSS, toujours rendue en hexadécimal.
     * On prend la première dans l'ORDRE D'ÉCRITURE (hexa ou rgb/rgba confondus),
     * et on convertit rgb()/rgba() en hexa : Outlook ne comprend pas davantage
     * rgba() que les dégradés, une couleur unie hexa est la seule valeur sûre.
     * L'éventuelle transparence est perdue — c'est justement ce qu'on veut.
     * Renvoie '' si aucune couleur exploitable.
     */
    function famiMailFirstColor($css)
    {
        $motif = '/#[0-9a-f]{3,8}\b|rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+[^)]*\)/i';
        if (!preg_match($motif, (string) $css, $m)) {
            return '';
        }

        $couleur = $m[0];
        if ($couleur[0] === '#') {
            return $couleur;
        }
        if (preg_match('/(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $couleur, $c)) {
            return sprintf(
                '#%02x%02x%02x',
                min(255, (int) $c[1]),
                min(255, (int) $c[2]),
                min(255, (int) $c[3])
            );
        }
        return '';
    }
}

if (!function_exists('famiMailFixGradients')) {
    /**
     * Dégradés : Outlook ne sait pas les afficher et n'affiche alors AUCUN fond.
     * On garde le dégradé pour les autres clients, mais on pose d'abord une
     * couleur unie (la première du dégradé) qu'Outlook, lui, comprend.
     * `background:linear-gradient(...)` devient
     * `background-color:#2d5a37;background-image:linear-gradient(...)`.
     */
    function famiMailFixGradients($html)
    {
        return preg_replace_callback(
            '/background(?:-image)?\s*:\s*(linear-gradient\((?:[^()]|\([^()]*\))*\))/i',
            function ($m) {
                $solid = famiMailFirstColor($m[1]);
                if ($solid === '') {
                    return 'background-image:' . $m[1];
                }
                return 'background-color:' . $solid . ';background-image:' . $m[1];
            },
            (string) $html
        );
    }
}

if (!function_exists('famiMailFixButtons')) {
    /**
     * Boutons : Outlook ignore le padding d'un <a>. La technique fiable est de
     * remplacer ce padding par une BORDURE de la même couleur que le fond —
     * visuellement identique partout, et Word sait afficher une bordure.
     * On ne touche qu'aux <a> qui sont clairement des boutons (inline-block +
     * padding + couleur de fond) ; au moindre doute on laisse tel quel.
     */
    function famiMailFixButtons($html)
    {
        return preg_replace_callback(
            '/<a\b([^>]*?)style\s*=\s*"([^"]*)"([^>]*)>/i',
            function ($m) {
                $style = $m[2];

                if (stripos($style, 'inline-block') === false) {
                    return $m[0];
                }
                if (!preg_match('/(?:^|;)\s*padding\s*:\s*([^;]+)/i', $style, $p)) {
                    return $m[0];
                }
                if (!preg_match('/(?:^|;)\s*background(?:-color)?\s*:\s*([^;]+)/i', $style, $b)) {
                    return $m[0];
                }

                $bg = famiMailFirstColor($b[1]);
                if ($bg === '') {
                    return $m[0];
                }

                // padding: 1 à 4 valeurs, toutes en px, sinon on renonce.
                $parts = preg_split('/\s+/', trim($p[1]));
                foreach ($parts as $part) {
                    if (!preg_match('/^\d+px$/i', $part)) {
                        return $m[0];
                    }
                }
                switch (count($parts)) {
                    case 1: $v = $h = (int) $parts[0]; break;
                    case 2: $v = (int) $parts[0]; $h = (int) $parts[1]; break;
                    case 3: $v = (int) $parts[0]; $h = (int) $parts[1]; break;
                    case 4: $v = (int) $parts[0]; $h = (int) $parts[1]; break;
                    default: return $m[0];
                }

                // On retire le padding et on pose la bordure équivalente.
                $style = preg_replace('/(?:^|;)\s*padding\s*:[^;]+;?/i', ';', $style);
                $style = rtrim($style, '; ') . ';'
                    . 'border-top:' . $v . 'px solid ' . $bg . ';'
                    . 'border-bottom:' . $v . 'px solid ' . $bg . ';'
                    . 'border-left:' . $h . 'px solid ' . $bg . ';'
                    . 'border-right:' . $h . 'px solid ' . $bg . ';'
                    . 'background-color:' . $bg . ';'
                    . 'mso-line-height-rule:exactly;';
                $style = preg_replace('/;{2,}/', ';', ltrim($style, ';'));

                return '<a' . $m[1] . 'style="' . $style . '"' . $m[3] . '>';
            },
            (string) $html
        );
    }
}

if (!function_exists('famiMailFixImages')) {
    /**
     * Images : Outlook ignore max-width et affiche le fichier à sa taille
     * réelle (un logo de 800 px déforme tout le mail). On ajoute l'attribut
     * width HTML, que tous les clients respectent, quand il manque.
     */
    function famiMailFixImages($html)
    {
        return preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function ($m) {
                $tag = $m[1];
                if (preg_match('/\swidth\s*=/i', $tag)) {
                    return $m[0];
                }
                if (!preg_match('/max-width\s*:\s*(\d+)px/i', $tag, $w)) {
                    return $m[0];
                }
                return '<img width="' . (int) $w[1] . '"' . $tag . '>';
            },
            (string) $html
        );
    }
}

if (!function_exists('famiMailPlainText')) {
    /**
     * Version texte lisible d'un mail HTML (et non un strip_tags brutal, qui
     * recollait tout en un seul pavé illisible). Les liens sont conservés en
     * clair : c'est le filet de sécurité si le HTML ne s'affiche pas du tout.
     */
    function famiMailPlainText($html)
    {
        $t = (string) $html;

        $t = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $t);

        // Liens : « libellé : https://… » (une seule fois si les deux sont identiques).
        $t = preg_replace_callback(
            '#<a\b[^>]*href\s*=\s*["\']([^"\']*)["\'][^>]*>(.*?)</a>#is',
            function ($m) {
                $url = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
                $txt = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8'));
                if ($txt === '' || strcasecmp($txt, $url) === 0) {
                    return $url;
                }
                if (stripos($url, 'mailto:') === 0) {
                    return $txt;
                }
                return $txt . ' : ' . $url;
            },
            $t
        );

        $t = preg_replace('#<br\s*/?>#i', "\n", $t);
        $t = preg_replace('#<li\b[^>]*>#i', "\n- ", $t);
        $t = preg_replace('#</(p|div|tr|li|h1|h2|h3|h4|h5|table|blockquote)>#i', "\n\n", $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
        $t = str_replace("\xC2\xA0", ' ', $t);            // espace insécable
        $t = str_replace(["\r\n", "\r"], "\n", $t);
        $t = preg_replace('/[ \t]+/', ' ', $t);
        $t = preg_replace('/ *\n */', "\n", $t);
        $t = preg_replace('/\n{3,}/', "\n\n", $t);

        return trim($t);
    }
}

if (!function_exists('famiMailOutlookSafe')) {
    /**
     * Emballe le corps d'un mail dans un document HTML complet et compatible.
     * Idempotent : un corps déjà complet (<html>) est renvoyé tel quel.
     *
     * @param string $html   Corps HTML du mail (tel qu'écrit dans les templates)
     * @param string $subject Sujet, utilisé comme <title>
     */
    function famiMailOutlookSafe($html, $subject = '')
    {
        $html = (string) $html;

        if (stripos($html, '<html') !== false) {
            return $html;
        }

        $html = famiMailFixGradients($html);
        $html = famiMailFixButtons($html);
        $html = famiMailFixImages($html);

        // Police avec espace : sans guillemets, la déclaration est invalide.
        $html = preg_replace('/font-family\s*:\s*Open Sans\b/i', "font-family:'Open Sans'", $html);

        // Largeur de la carte : on reprend celle du template pour qu'Outlook,
        // qui ignore max-width, cadre exactement pareil que les autres clients.
        $width = 680;
        if (preg_match('/max-width\s*:\s*(\d+)px/i', $html, $m)) {
            $width = max(320, min(900, (int) $m[1]));
        }

        // Couleur de fond de la page : celle du premier bloc du template.
        $pageBg = '#f4f6f5';
        if (preg_match('/background(?:-color)?\s*:\s*(#[0-9a-f]{3,6})\b/i', $html, $m)) {
            $pageBg = $m[1];
        }

        $title = htmlspecialchars((string) $subject, ENT_QUOTES, 'UTF-8');

        // Pré-en-tête : le texte d'aperçu affiché dans la liste des mails.
        $preheader = famiMailPlainText($html);
        $preheader = preg_replace('/\s+/', ' ', $preheader);
        if (function_exists('mb_substr')) {
            $preheader = mb_substr($preheader, 0, 120, 'UTF-8');
        } else {
            $preheader = substr($preheader, 0, 120);
        }
        $preheader = htmlspecialchars(trim($preheader), ENT_QUOTES, 'UTF-8');

        $doc = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="fr">' . "\n"
            . '<head>' . "\n"
            . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n"
            . '<meta name="x-apple-disable-message-reformatting" />' . "\n"
            . '<meta name="color-scheme" content="light" />' . "\n"
            . '<meta name="supported-color-schemes" content="light" />' . "\n"
            . '<title>' . $title . '</title>' . "\n"
            // Sans ce bloc, Outlook agrandit tout le mail sur les écrans à forte densité.
            . '<!--[if mso]>' . "\n"
            . '<xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>' . "\n"
            . '<![endif]-->' . "\n"
            . '<style type="text/css">' . "\n"
            . 'body{margin:0 !important;padding:0 !important;width:100% !important;}' . "\n"
            . 'table,td{mso-table-lspace:0pt;mso-table-rspace:0pt;border-collapse:collapse;}' . "\n"
            . 'img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;max-width:100%;height:auto;}' . "\n"
            . 'body,table,td,div,p,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}' . "\n"
            . 'a{text-decoration:none;}' . "\n"
            . '</style>' . "\n"
            // Word ne connaît que quelques polices : on impose une valeur sûre.
            . '<!--[if mso]>' . "\n"
            . '<style type="text/css">' . "\n"
            . 'body,table,td,div,p,span,a,h1,h2,h3,strong{font-family:Arial,Helvetica,sans-serif !important;}' . "\n"
            . '</style>' . "\n"
            . '<![endif]-->' . "\n"
            . '</head>' . "\n"
            . '<body style="margin:0;padding:0;background-color:' . $pageBg . ';">' . "\n"
            . '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;color:' . $pageBg . ';">' . $preheader . '</div>' . "\n"
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:' . $pageBg . ';">' . "\n"
            . '<tr><td align="center" style="padding:0;">' . "\n"
            // Outlook ignore max-width : ce tableau, lu par lui seul, fixe la largeur.
            . '<!--[if mso]><table role="presentation" width="' . $width . '" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:0;"><![endif]-->' . "\n"
            . $html . "\n"
            . '<!--[if mso]></td></tr></table><![endif]-->' . "\n"
            . '</td></tr></table>' . "\n"
            . '</body></html>';

        return $doc;
    }
}
