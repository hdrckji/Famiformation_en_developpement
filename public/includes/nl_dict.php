<?php
// ============================================================
// nl_dict.php — DICTIONNAIRE d'interface FR → NL (néerlandais de Belgique).
//
//   L'INTERFACE (boutons, menus, paramètres…) est fixe : elle vit dans le code.
//   On la traduit UNE fois, ici, dans un fichier figé (généré à la main, corrigeable).
//   À l'exécution, pour un utilisateur en NL, on remplace chaque libellé FR par son NL.
//   -> aucune API, aucun coût, instantané, fiable.
//
//   ⚠️ Ce dictionnaire ne touche JAMAIS au contenu (guide, quiz) : celui-là est
//      traduit dynamiquement par Claude (voir i18n_nl.php). Ici : seulement l'interface.
//
//   Comment ça marche (nlDictApply) :
//     - on protège <script>/<style>/<textarea>/<pre> (on n'y touche pas) ;
//     - on remplace un texte entre balises SEULEMENT si, une fois « trimé », il est
//       EXACTEMENT une clé du dictionnaire (pas de remplacement partiel → pas de casse) ;
//     - on traduit aussi quelques attributs visibles (placeholder, title, alt, aria-label),
//       toujours en correspondance EXACTE.
// ============================================================

if (!function_exists('nlDict')) {
    /** Le dictionnaire FR => NL de l'interface. Ajoute/corrige librement une entrée. */
    function nlDict()
    {
        static $d = null;
        if ($d !== null) { return $d; }
        $d = [
            // — Navigation / actions générales —
            'Accueil' => 'Startpagina',
            'Retour' => 'Terug',
            'Quitter' => 'Verlaten',
            'Annuler' => 'Annuleren',
            'Valider' => 'Bevestigen',
            'Enregistrer' => 'Opslaan',
            'Modifier' => 'Bewerken',
            'Supprimer' => 'Verwijderen',
            'Ouvrir' => 'Openen',
            'Fermer' => 'Sluiten',
            'Créer' => 'Aanmaken',
            'Confirmer' => 'Bevestigen',
            'Continuer' => 'Doorgaan',
            'Rechercher' => 'Zoeken',
            'Oui' => 'Ja',
            'Non' => 'Nee',
            'Oui, continuer' => 'Ja, doorgaan',
            'J\'ai compris' => 'Begrepen',
            'Déconnexion' => 'Afmelden',
            'Notifications' => 'Meldingen',
            'Langue' => 'Taal',
            'Chargement…' => 'Laden…',
            'Chargement du document…' => 'Document laden…',

            // — Paramètres : onglets & sections —
            'Paramètres' => 'Instellingen',
            'Paramètres utilisateur' => 'Gebruikersinstellingen',
            'Gestion des modules' => 'Modulebeheer',
            'Gestion des utilisateurs' => 'Gebruikersbeheer',
            'Gestion des profils' => 'Profielbeheer',
            'Gestion des agences' => 'Kantorenbeheer',
            'Widget' => 'Widget',
            'Stockage' => 'Opslag',
            'Outils' => 'Hulpmiddelen',
            'Préférences' => 'Voorkeuren',
            'Modules' => 'Modules',
            'Profils' => 'Profielen',
            'Utilisateurs' => 'Gebruikers',
            'Agences intérim' => 'Uitzendkantoren',
            'Widget d\'accueil' => 'Startwidget',
            'Phrases qui défilent' => 'Doorlopende zinnen',
            'Créer un module' => 'Een module aanmaken',
            'Ce module contient d\'autres modules' => 'Deze module bevat andere modules',
            'Accès' => 'Toegang',
            'Choisir les profils…' => 'Profielen kiezen…',
            'Nom' => 'Naam',
            'Description' => 'Beschrijving',
            'Mot de passe' => 'Wachtwoord',
            'Identifiant' => 'Gebruikersnaam',

            // — Page module / contenu —
            'Ajout de contenu' => 'Inhoud toevoegen',
            'Le guide' => 'De gids',
            'Vidéo' => 'Video',
            'Modifier le contenu' => 'Inhoud bewerken',
            'Modifier le guide' => 'De gids bewerken',
            'Contrôler le quiz' => 'De quiz nakijken',
            'Modifier et uniformiser' => 'Bewerken en uniformiseren',
            'Valider et uniformiser' => 'Bevestigen en uniformiseren',
            'Passer le quiz' => 'Naar de quiz',
            'Ce module n\'a pas encore de contenu.' => 'Deze module heeft nog geen inhoud.',
            'Aucun sous-module pour l\'instant.' => 'Nog geen submodule.',
            'Fichier manquant' => 'Ontbrekend bestand',
            'Document actuel' => 'Huidig document',
            'Vidéo actuelle' => 'Huidige video',
            'Vidéo en préparation…' => 'Video wordt voorbereid…',
            'Sous-titres' => 'Ondertitels',
            'à évaluer' => 'te evalueren',
            'À évaluer' => 'Te evalueren',
            'Obligatoire' => 'Verplicht',

            // — Éditeur / relecture —
            'Relecture' => 'Nalezen',
            'Gras' => 'Vet',
            'Ajouter un bloc…' => 'Een blok toevoegen…',
            'Titre de section' => 'Sectietitel',
            'Paragraphe' => 'Paragraaf',
            'Liste' => 'Lijst',
            'Étapes' => 'Stappen',
            'Encadré' => 'Kadertekst',
            'Chiffres clés' => 'Kerncijfers',
            'Citation' => 'Citaat',
            'Aligner à gauche' => 'Links uitlijnen',
            'Centrer' => 'Centreren',
            'Aligner à droite' => 'Rechts uitlijnen',
            'Terminer' => 'Beëindigen',
            'Petite' => 'Klein',
            'Moyenne' => 'Gemiddeld',
            'Grande' => 'Groot',
            'Pivoter' => 'Draaien',

            // — Quiz —
            'Contrôle du quiz' => 'Quizcontrole',
            'Valider le quiz' => 'Quiz bevestigen',
            'Unique' => 'Enkel',
            'Multiple' => 'Meervoudig',
            'Ajouter une question' => 'Een vraag toevoegen',
            'Résultat immédiat' => 'Onmiddellijk resultaat',

            // — Divers —
            'Bienvenue sur Famiformation' => 'Welkom op Famiformation',
            'Formation terminée' => 'Opleiding voltooid',
            'NOUVEAU' => 'NIEUW',
            'Aucun résultat.' => 'Geen resultaat.',
            'Chargement de la vidéo…' => 'Video laden…',

            // ============================================================
            // 2e VAGUE — releve des phrases reellement affichees par le site
            // (FamiFormation et FamiJob). Une phrase absente d'ici reste en
            // francais : on peut donc completer sans jamais rien casser.
            // ============================================================
            '← Retour' => '← Terug',
            '⬅ Retour' => '⬅ Terug',
            '&larr; Retour' => '&larr; Terug',
            '&#8592; Retour' => '&#8592; Terug',
            '← Retour à l\'accueil' => '← Terug naar de startpagina',
            '← Retour Accueil' => '← Terug naar start',
            '⬅ Retour Accueil' => '⬅ Terug naar start',
            '&#8592; Retour accueil' => '&#8592; Terug naar start',
            '⬅ Retour Management' => '⬅ Terug naar Management',
            '⬅ Retour FamiJob' => '⬅ Terug naar FamiJob',
            '← FamiFormation' => '← FamiFormation',
            '← Retour à l\'animalerie' => '← Terug naar de dierenafdeling',
            '← Retour à l\'espace magasin' => '← Terug naar de winkelruimte',
            '← Retour à la logistique' => '← Terug naar de logistiek',
            '← Retour logistique' => '← Terug naar logistiek',
            '← Retour à Food' => '← Terug naar Food',
            '&#8592; Retour Déco' => '&#8592; Terug naar Deco',
            '⬅ Quitter' => '⬅ Verlaten',
            'Suivant' => 'Volgende',
            'Précédent' => 'Vorige',
            'Continuer mon parcours' => 'Mijn traject verderzetten',
            '➕ Créer' => '➕ Aanmaken',
            '➕ Ajouter' => '➕ Toevoegen',
            'Affecter' => 'Toewijzen',
            'Retirer' => 'Verwijderen',
            '🗑️ Retirer' => '🗑️ Verwijderen',
            '🗑 Supprimer' => '🗑 Verwijderen',
            'Suppr.' => 'Verw.',
            'Sauvegarder' => 'Opslaan',
            '✅ Valider le quiz' => '✅ Quiz bevestigen',
            '✅ Valider la relecture' => '✅ Nalezing bevestigen',
            '✏️ Modifier' => '✏️ Bewerken',
            '✓ Appliquer' => '✓ Toepassen',
            '✗ Ignorer' => '✗ Negeren',
            '☑️ Sélectionner' => '☑️ Selecteren',
            'Tout cocher' => 'Alles aanvinken',
            'Tout décocher' => 'Alles uitvinken',
            'Tout sélectionner' => 'Alles selecteren',
            'S\'inscrire' => 'Inschrijven',
            'Créer le compte' => 'Account aanmaken',
            'Se connecter' => 'Aanmelden',
            'Aperçu' => 'Voorbeeld',
            'Donner du feedback' => 'Feedback geven',
            '▶ Lancer la formation' => '▶ Opleiding starten',
            '▶ Lancer la vidéo' => '▶ Video starten',
            '📝 Passer le Quiz de validation' => '📝 De validatiequiz afleggen',
            '📝 Commencer le Quiz' => '📝 De quiz starten',
            '📝 Générer un quiz' => '📝 Een quiz genereren',
            '✍️ Créer un guide' => '✍️ Een gids maken',
            'Support de formation :' => 'Opleidingsmateriaal:',
            'Formation :' => 'Opleiding:',
            'Formation' => 'Opleiding',
            'Module' => 'Module',
            'Module technique' => 'Technische module',
            'Félicitations !' => 'Proficiat!',
            'La vidéo doit être vue entièrement pour valider l\'étape.' => 'De video moet volledig bekeken worden om de stap te bevestigen.',
            'La vidéo doit être vue entièrement.' => 'De video moet volledig bekeken worden.',
            'Veuillez regarder la vidéo ci-dessous.' => 'Bekijk de video hieronder.',
            'Cette formation est désormais validée. Tu peux passer à la suite de ton parcours.' => 'Deze opleiding is nu bevestigd. Je kan verder met je traject.',
            'Tu as terminé cette formation avec succès. Elle est désormais validée dans ton parcours.' => 'Je hebt deze opleiding met succes afgerond. Ze is nu bevestigd in je traject.',
            '🕘 Historique des versions' => '🕘 Versiegeschiedenis',
            'cliquer pour télécharger ou supprimer' => 'klik om te downloaden of te verwijderen',
            'Description :' => 'Beschrijving:',
            'Durée prévue :' => 'Voorziene duur:',
            'Durée' => 'Duur',
            'Places' => 'Plaatsen',
            'Prénom' => 'Voornaam',
            'Prénom / Nom' => 'Voornaam / Naam',
            'Nom (compte)' => 'Naam (account)',
            'Nom d\'utilisateur' => 'Gebruikersnaam',
            'Identifiant :' => 'Gebruikersnaam:',
            'Nouveau mot de passe' => 'Nieuw wachtwoord',
            'Mot de passe admin' => 'Beheerderswachtwoord',
            'Mot de passe administrateur' => 'Beheerderswachtwoord',
            'Mot de passe de verrouillage' => 'Vergrendelwachtwoord',
            'Définir mon mot de passe' => 'Mijn wachtwoord instellen',
            'Email' => 'E-mail',
            'Adresse email' => 'E-mailadres',
            'Adresse' => 'Adres',
            'Email principal' => 'Hoofd-e-mailadres',
            'Email secondaire' => 'Tweede e-mailadres',
            'Ville' => 'Stad',
            'Date' => 'Datum',
            'Date :' => 'Datum:',
            'Heure :' => 'Uur:',
            'Jour' => 'Dag',
            'Photo' => 'Foto',
            'Commentaire' => 'Opmerking',
            'Titre' => 'Titel',
            'Texte' => 'Tekst',
            'Sujet' => 'Onderwerp',
            'Détail' => 'Detail',
            'Priorité' => 'Prioriteit',
            'Score' => 'Score',
            'Total' => 'Totaal',
            'Type' => 'Type',
            'Statut' => 'Status',
            'Profil' => 'Profiel',
            'Action' => 'Actie',
            'Actions' => 'Acties',
            'Contact' => 'Contact',
            'Site' => 'Vestiging',
            'Écran' => 'Scherm',
            'Créneau' => 'Tijdslot',
            'Disponibilité' => 'Beschikbaarheid',
            'Département' => 'Afdeling',
            'Agence' => 'Kantoor',
            'Nom de l\'agence' => 'Naam van het kantoor',
            'Collaborateur' => 'Medewerker',
            'Collaborateur :' => 'Medewerker:',
            'Précision optionnelle' => 'Optionele toelichting',
            '(Ctrl/Cmd+clic pour sélectionner plusieurs)' => '(Ctrl/Cmd+klik om er meerdere te kiezen)',
            'Étudiant' => 'Student',
            '🎓 Étudiant' => '🎓 Student',
            'Magasin' => 'Winkel',
            '👔 Employé Magasin' => '👔 Winkelmedewerker',
            '🚚 Employé Logistique' => '🚚 Logistiek medewerker',
            'Logistique' => 'Logistiek',
            'Mentor' => 'Mentor',
            '🧑‍🎓 Mentor' => '🧑‍🎓 Mentor',
            'Teamcoach' => 'Teamcoach',
            '🧑‍🏫 TeamCoach' => '🧑‍🏫 Teamcoach',
            'Admin' => 'Beheerder',
            '🛠 Admin' => '🛠 Beheerder',
            'Évaluateur' => 'Beoordelaar',
            'Tous les profils' => 'Alle profielen',
            '🌍 Tous' => '🌍 Alle',
            'Tous' => 'Alle',
            'Actif' => 'Actief',
            'Inactif' => 'Inactief',
            'Activé' => 'Ingeschakeld',
            'Désactivé' => 'Uitgeschakeld',
            'En attente' => 'In afwachting',
            'Indisponible' => 'Niet beschikbaar',
            'à venir' => 'binnenkort',
            '— Aucun —' => '— Geen —',
            'Verrou' => 'Vergrendeling',
            'Verrouillé — cliquer pour déverrouiller (mot de passe requis)' => 'Vergrendeld — klik om te ontgrendelen (wachtwoord vereist)',
            'Déverrouillé — cliquer pour verrouiller (mot de passe requis)' => 'Ontgrendeld — klik om te vergrendelen (wachtwoord vereist)',
            'Zones autorisées' => 'Toegestane zones',
            'Mon horaire' => 'Mijn uurrooster',
            'Mon horaire & département' => 'Mijn uurrooster & afdeling',
            'Enregistrer mes disponibilités' => 'Mijn beschikbaarheden opslaan',
            'Gestion de la présence' => 'Aanwezigheidsbeheer',
            '⚙️ Paramètres' => '⚙️ Instellingen',
            '🎨 Créateur' => '🎨 Ontwerper',
            '📁 Conteneur' => '📁 Map',
            '⚙️ Élément' => '⚙️ Element',
            '📄 Élément' => '📄 Element',
            'Info' => 'Info',
            'Chaussure de sécurité' => 'Veiligheidsschoenen',
            'Formation secourisme' => 'Opleiding eerste hulp',
            'Formation Parrain/Marraine' => 'Opleiding peter/meter',
            'Sécurité au travail' => 'Veiligheid op het werk',
            'Bien-être animalier' => 'Dierenwelzijn',
            'Rongeur' => 'Knaagdier',
            'Oiseaux' => 'Vogels',
            'SAV Animalerie' => 'Dienst na verkoop dierenafdeling',
            'Barbecue' => 'Barbecue',
            'Piscine' => 'Zwembad',
            'Spa' => 'Spa',
            'Aménager votre pelouse' => 'Je gazon aanleggen',
            'Les Chrysanthèmes' => 'De chrysanten',
            'Cultiver des légumes' => 'Groenten kweken',
            'Changement de saison' => 'Seizoenswissel',
            'Fleurs Artificielles' => 'Kunstbloemen',
            'Chariots Danois' => 'Deense karren',
            'Gerbeur' => 'Stapelaar',
            'Empileuse' => 'Stapelaar',
            'Checklist GERBEUR' => 'Checklist STAPELAAR',
            'Stock - Gerbeur' => 'Stock - Stapelaar',
            'Préparation de commande' => 'Ordervoorbereiding',
            'Marketing' => 'Marketing',
            'Leadership' => 'Leiderschap',
            'Judo verbal' => 'Verbaal judo',
            'Gestion du stress' => 'Omgaan met stress',
            'Méthode de travail' => 'Werkmethode',
            'Formation Ressources Humaines' => 'Opleiding Human Resources',
            'Présentation équipe mix' => 'Voorstelling team mix',
            'Accompagnement et transmission des savoirs.' => 'Begeleiding en kennisoverdracht.',
            'Techniques pour gérer les situations difficiles.' => 'Technieken om moeilijke situaties aan te pakken.',
            'Nom de la personne évaluée' => 'Naam van de beoordeelde persoon',
            'Prénom de la personne évaluée' => 'Voornaam van de beoordeelde persoon',
            'Date de l\'évaluation' => 'Datum van de beoordeling',
            'Évaluations orphelines' => 'Niet-toegewezen beoordelingen',
            'Moyenne quiz caisse :' => 'Gemiddelde kassaquiz:',
            'Inscription enregistrée' => 'Inschrijving geregistreerd',
            'Votre inscription pour la formation' => 'Je inschrijving voor de opleiding',
            'a bien été prise en compte.' => 'is goed geregistreerd.',
            'Vous pouvez retrouver ces informations dans votre espace FamiFormation.' => 'Je vindt deze informatie terug in je FamiFormation-ruimte.',
            'Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer ce message.' => 'Als je deze aanvraag niet hebt gedaan, mag je dit bericht negeren.',
            'Message automatique envoyé par FamiFormation.' => 'Automatisch bericht verzonden door FamiFormation.',
            'Rappel de ton rendez-vous' => 'Herinnering aan je afspraak',
            'À bientôt en magasin,' => 'Tot binnenkort in de winkel,',
            'Si tu as une question avant le rendez-vous, rapproche-toi de ton contact habituel.' => 'Heb je een vraag voor de afspraak? Neem contact op met je gebruikelijke contactpersoon.',
            'apparaîtront ici.' => 'verschijnen hier.',
            'Notez-le bien.' => 'Noteer het goed.',
            'Confirmez, puis' => 'Bevestig, daarna',
            'Puis' => 'Daarna',
            'C\'est quoi Famiformation ?' => 'Wat is Famiformation?',
        ];
        return $d;
    }
}

if (!function_exists('nlDictApply')) {
    /**
     * Applique le dictionnaire au HTML final (pour un utilisateur en NL).
     * Remplacement par correspondance EXACTE d'un nœud de texte (ou d'un attribut visible),
     * jamais partiel → aucune casse possible.
     */
    function nlDictApply($html)
    {
        $dict = nlDict();
        if (empty($dict) || $html === '' || $html === null) { return $html; }

        // 1) Protéger les zones à ne pas toucher (JS, CSS, champs multi-lignes, préformaté).
        $stash = [];
        $n = 0;
        $html = preg_replace_callback('#<(script|style|textarea|pre)\b[^>]*>.*?</\1>#is', function ($m) use (&$stash, &$n) {
            $key = "\x02FMDICT" . ($n++) . "\x03";
            $stash[$key] = $m[0];
            return $key;
        }, (string) $html);

        // 2) Texte entre deux balises : on remplace si le nœud (trimé) est EXACTEMENT une clé.
        $html = preg_replace_callback('#>([^<>]+)<#u', function ($m) use ($dict) {
            $raw = $m[1];
            $t = trim($raw);
            if ($t === '' || !isset($dict[$t])) { return $m[0]; }
            $lead = substr($raw, 0, strlen($raw) - strlen(ltrim($raw)));
            $trail = substr($raw, strlen(rtrim($raw)));
            return '>' . $lead . $dict[$t] . $trail . '<';
        }, $html);

        // 3) Attributs VISIBLES uniquement (jamais « value », qui peut être envoyé au serveur).
        $html = preg_replace_callback('#\b(placeholder|title|alt|aria-label)="([^"]*)"#u', function ($m) use ($dict) {
            $t = trim($m[2]);
            if ($t === '' || !isset($dict[$t])) { return $m[0]; }
            return $m[1] . '="' . htmlspecialchars($dict[$t], ENT_QUOTES) . '"';
        }, $html);

        // 4) Restaurer les zones protégées.
        if (!empty($stash)) { $html = strtr($html, $stash); }
        return $html;
    }
}
