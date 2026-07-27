<?php
// ============================================================
// beta_quiz_data.php — BANQUE de questions des quiz de la version BETA,
// BILINGUE (FR + NL). Deux quiz : « Onboarding » et « Formation Caisse ».
//   • Chaque question est taguée par sa SOURCE ('pdf' ou 'video').
//   • À chaque passage, quiz.php tire 10 questions : 6 du PDF + 4 de la vidéo.
//   • La bonne réponse est TOUJOURS la 1re option (index 0), FR comme NL.
// Les quiz sont installés sur les modules beta (quiz_json + quiz_json_nl) par
// gestion-beta.php, et ré-installés automatiquement à l'affichage si besoin.
// ============================================================

if (!function_exists('betaQuizBank')) {
    // Question à réponse unique, bilingue. La 1re option est la bonne réponse.
    // bq(source, Q_fr, Q_nl, a_fr, a_nl, b_fr, b_nl, c_fr, c_nl)
    function bq($source, $q, $qnl, $a, $anl, $b, $bnl, $c, $cnl)
    {
        return [
            'q' => $q, 'q_nl' => $qnl,
            'options' => [$a, $b, $c], 'options_nl' => [$anl, $bnl, $cnl],
            'correct' => [0], 'type' => 'single', 'source' => $source,
        ];
    }

    function betaQuizBank()
    {
        $onboarding = ['pick' => ['pdf' => 6, 'video' => 4], 'questions' => [
            // ---- ONBOARDING · PDF ----
            bq('pdf', "Où pouvez-vous déposer votre vélo ou votre trottinette au travail ?", "Waar kan je je fiets of step kwijt op het werk?",
                "Dans l'abri pour vélos", "In de fietsenstalling", "Sur le parking", "Op de parking", "Dans le magasin", "In de winkel"),
            bq('pdf', "Qui est votre supérieur hiérarchique direct pour les questions de planning ou de travail ?", "Wie is je directe leidinggevende voor vragen over planning of werk?",
                "Teamcoach", "Teamcoach", "Ressource humaine", "Human resources", "Responsable de rayon", "Afdelingsverantwoordelijke"),
            bq('pdf', "Que faut-il faire en cas d'évacuation ?", "Wat moet je doen bij een evacuatie?",
                "Rester calme", "Kalm blijven", "Paniquer", "Panikeren", "Continuer à travailler", "Blijven doorwerken"),
            bq('pdf', "Si vous avez un problème de contrat ou de prestation, à qui devez-vous vous adresser ?", "Bij een probleem met je contract of prestaties, tot wie moet je je richten?",
                "Ressource humaine", "Human resources", "Responsable du magasin", "De winkelverantwoordelijke", "Teamcoach", "Teamcoach"),
            bq('pdf', "Combien y a-t-il de valeurs dans l'entreprise ?", "Hoeveel waarden telt het bedrijf?",
                "6", "6", "3", "3", "1", "1"),
            bq('pdf', "Quel est le bon code vestimentaire à avoir ?", "Wat is de juiste kledingcode?",
                "Veste Famiflora avec prénom et chaussures fermées", "Famiflora-vest met voornaam en gesloten schoenen", "Pyjama et pantoufles", "Pyjama en pantoffels", "Tenue de vacances et sandales", "Vakantiekleding en sandalen"),
            bq('pdf', "Quelle est la bonne attitude à avoir ?", "Welke houding moet je aannemen?",
                "Positif", "Positief", "Négatif", "Negatief", "Neutre", "Neutraal"),
            bq('pdf', "Comment procéder pour une remise sur un article défectueux ?", "Hoe geef je een korting op een defect artikel?",
                "Sélectionner l'article et cliquer sur « remise article »", "Het artikel selecteren en op « artikelkorting » klikken", "Modifier le prix directement", "De prijs rechtstreeks wijzigen", "Supprimer l'article", "Het artikel verwijderen"),
            bq('pdf', "Où se trouve la liste des secouristes (premiers secours) ?", "Waar vind je de lijst met hulpverleners (EHBO)?",
                "À l'accueil du magasin", "Aan het onthaal van de winkel", "Au réfectoire", "In de refter", "Sur le site internet de Famiflora", "Op de website van Famiflora"),
            bq('pdf', "Que signifie un signal acoustique « continu » dans le magasin ?", "Wat betekent een « continu » geluidssignaal in de winkel?",
                "Une alarme pour l'évacuation totale du site", "Een alarm voor de volledige evacuatie van de site", "Une annonce micro importante", "Een belangrijke omroepboodschap", "Le début de la pause déjeuner", "Het begin van de middagpauze"),
            bq('pdf', "Que devez-vous toujours conserver après un achat personnel ?", "Wat moet je altijd bewaren na een persoonlijke aankoop?",
                "Votre ticket de caisse comme preuve", "Je kassaticket als bewijs", "L'emballage du produit", "De verpakking van het product", "L'étiquette de prix", "Het prijsetiket"),
            bq('pdf', "Quand pouvez-vous effectuer vos achats personnels ?", "Wanneer mag je persoonlijke aankopen doen?",
                "Pendant vos pauses ou après votre journée de travail", "Tijdens je pauzes of na je werkdag", "À tout moment si vous n'avez pas de clients", "Op elk moment als je geen klanten hebt", "Uniquement le samedi matin avant l'ouverture", "Enkel op zaterdagochtend voor de opening"),
            bq('pdf', "Quels chariots faut-il utiliser pour déplacer des objets en interne ?", "Welke karren gebruik je om intern spullen te verplaatsen?",
                "Les chariots sans cadenas", "De karren zonder slot", "Les chariots avec cadenas", "De karren met slot", "N'importe quel chariot client vide", "Om het even welke lege klantenkar"),
            bq('pdf', "Où se trouve la presse à carton sur le site de Mouscron ?", "Waar staat de kartonpers op de site van Moeskroen?",
                "À l'extérieur au niveau du quai 5", "Buiten ter hoogte van kade 5", "Dans le réfectoire", "In de refter", "Sur le parking personnel", "Op de personeelsparking"),
            bq('pdf', "Où doit impérativement rester votre téléphone portable au travail ?", "Waar moet je gsm verplicht blijven tijdens het werk?",
                "Dans votre casier au vestiaire", "In je locker in de kleedkamer", "Dans votre poche en mode silencieux", "In je zak in stille modus", "Sur le comptoir de votre rayon", "Op de toonbank van je afdeling"),
            bq('pdf', "En cas d'absence, qui devez-vous prévenir en premier ?", "Bij afwezigheid, wie moet je eerst verwittigen?",
                "Votre teamcoach avant le début de votre travail", "Je teamcoach voor het begin van je werk", "La direction générale par email", "De algemene directie via e-mail", "Un collègue de votre rayon par SMS", "Een collega van je afdeling via sms"),
            bq('pdf', "Quelle est la durée totale d'une pause complète journalière ?", "Hoe lang duurt een volledige dagelijkse pauze in totaal?",
                "60 minutes", "60 minuten", "30 minutes", "30 minuten", "90 minutes", "90 minuten"),
            bq('pdf', "Quelle est la règle concernant le prêt de votre badge ?", "Wat is de regel over het uitlenen van je badge?",
                "C'est une faute grave strictement interdite", "Het is een zware fout, strikt verboden", "C'est autorisé pour dépanner un collègue", "Het mag om een collega uit de nood te helpen", "C'est autorisé uniquement pour les étudiants", "Het mag enkel voor studenten"),
            bq('pdf', "Quand devez-vous pointer votre début de travail ?", "Wanneer moet je inklokken bij het begin van je werk?",
                "Une fois en tenue et les effets personnels au casier", "Eenmaal in werkkledij en persoonlijke spullen in de locker", "Dès votre arrivée sur le parking", "Zodra je op de parking aankomt", "Avant de mettre votre tenue de travail", "Voor je je werkkledij aantrekt"),
            bq('pdf', "Où les collaborateurs doivent-ils obligatoirement garer leur voiture ?", "Waar moeten medewerkers verplicht parkeren?",
                "Sur le parking « personnel » à gauche de l'entrée", "Op de « personeels »-parking links van de ingang", "Sur le parking client devant l'entrée", "Op de klantenparking voor de ingang", "Dans les rues adjacentes au magasin", "In de straten naast de winkel"),
            // ---- ONBOARDING · VIDÉO ----
            bq('video', "Quel sentiment la vidéo cherche-t-elle à transmettre aux futurs collègues ?", "Welk gevoel wil de video overbrengen op de toekomstige collega's?",
                "La fierté de travailler dans un univers vert et familial", "De trots om te werken in een groene en familiale wereld", "La peur de la foule", "Angst voor de menigte", "L'ennui du travail de bureau", "De verveling van kantoorwerk"),
            bq('video', "Famiflora est situé dans quelle zone géographique ?", "In welke geografische zone ligt Famiflora?",
                "À la frontière franco-belge", "Aan de Frans-Belgische grens", "À la frontière belgo-allemande", "Aan de Belgisch-Duitse grens", "À la frontière belgo-luxembourgeoise", "Aan de Belgisch-Luxemburgse grens"),
            bq('video', "Comment appelle-t-on le secteur technique du jardin (pelles, terreaux) ?", "Hoe heet de technische tuinsector (scheppen, potgrond)?",
                "Le secteur Jardin ou Outillage", "De sector Tuin of Gereedschap", "Le secteur Cosmétique", "De sector Cosmetica", "Le secteur Jouets", "De sector Speelgoed"),
            bq('video', "Que trouve-t-on au rayon « Mobilier de jardin » ?", "Wat vind je in de afdeling « Tuinmeubelen »?",
                "Des salons de jardin, des parasols et des barbecues", "Tuinsets, parasols en barbecues", "Des lits superposés", "Stapelbedden", "Des bureaux d'ordinateur", "Computerbureaus"),
            bq('video', "Quelle attitude attend-on du personnel envers les clients ?", "Welke houding verwacht men van het personeel tegenover de klanten?",
                "Accueil, sourire et conseil expert", "Onthaal, glimlach en deskundig advies", "Ignorer les clients", "De klanten negeren", "Rester uniquement derrière la caisse", "Enkel achter de kassa blijven"),
            bq('video', "Pourquoi les serres immenses sont-elles un avantage ?", "Waarom zijn de immense serres een voordeel?",
                "Offrir une luminosité naturelle optimale pour les plantes", "Optimaal natuurlijk licht bieden voor de planten", "Cacher le stock aux yeux des clients", "De stock verbergen voor de klanten", "Stocker l'eau de pluie pour la ville", "Regenwater opslaan voor de stad"),
            bq('video', "Quel est l'un des points forts de l'expérience client chez Famiflora ?", "Wat is een van de sterke punten van de klantenervaring bij Famiflora?",
                "Pouvoir faire toutes ses courses en un seul lieu", "Alle boodschappen op één plek kunnen doen", "Devoir changer de magasin pour chaque article", "Voor elk artikel van winkel moeten veranderen", "L'absence totale de personnel en rayon", "De volledige afwezigheid van personeel in de afdeling"),
            bq('video', "Famiflora propose de quoi se restaurer, comment s'appelle cet espace ?", "Famiflora biedt eetgelegenheid, hoe heet die ruimte?",
                "Le Resto ou l'espace cafétéria", "Het Resto of de cafetaria", "La Cantine scolaire", "De schoolkantine", "Le snack mobile à l'entrée", "De mobiele snack aan de ingang"),
            bq('video', "Que trouve-t-on dans le secteur « Pépinière » extérieur ?", "Wat vind je in de buitensector « Boomkwekerij »?",
                "Des arbres, des arbustes et des plantes vivaces", "Bomen, struiken en vaste planten", "Des poissons tropicaux", "Tropische vissen", "Des meubles de cuisine", "Keukenmeubelen"),
            bq('video', "Quel est l'événement majeur de fin d'année chez Famiflora ?", "Wat is het grote eindejaarsevenement bij Famiflora?",
                "Le Marché de Noël", "De Kerstmarkt", "La foire aux pneus d'hiver", "De winterbandenbeurs", "La fête des moissons", "Het oogstfeest"),
            bq('video', "Comment Famiflora aide-t-il les clients pour les achats volumineux ?", "Hoe helpt Famiflora klanten bij volumineuze aankopen?",
                "En mettant à disposition des chariots adaptés", "Door aangepaste karren ter beschikking te stellen", "En demandant aux clients de porter leurs sacs", "Door klanten te vragen hun zakken te dragen", "En interdisant les gros achats le samedi", "Door grote aankopen op zaterdag te verbieden"),
            bq('video', "Quelle est la particularité du secteur fleurs coupées ?", "Wat is het bijzondere aan de sector snijbloemen?",
                "Des arrivages quotidiens pour garantir la fraîcheur", "Dagelijkse leveringen om de versheid te garanderen", "Des fleurs en plastique uniquement", "Enkel plastic bloemen", "Des fleurs qui ne durent qu'une journée", "Bloemen die maar één dag meegaan"),
            bq('video', "Quel type de produits trouve-t-on dans le secteur Animalerie ?", "Welk soort producten vind je in de sector Dierenwinkel?",
                "De l'alimentation et des accessoires pour animaux", "Voeding en accessoires voor dieren", "Uniquement des chevaux de course", "Enkel renpaarden", "Des produits de nettoyage pour voitures", "Reinigingsproducten voor auto's"),
            bq('video', "Famiflora possède un rayon dédié aux animaux, comment s'appelle-t-il ?", "Famiflora heeft een afdeling voor dieren, hoe heet die?",
                "L'Animalerie", "De Dierenwinkel", "Le Zoo-Parc", "Het Zoo-Park", "La Ferme Famiflora", "De Boerderij Famiflora"),
            bq('video', "Quel est l'engagement de Famiflora concernant les prix ?", "Wat is de belofte van Famiflora over de prijzen?",
                "Garantir des prix bas et attractifs toute l'année", "Het hele jaar lage en aantrekkelijke prijzen garanderen", "Augmenter les prix durant les week-ends", "De prijzen verhogen tijdens de weekends", "Ne jamais afficher les prix en rayon", "Nooit prijzen tonen in de afdeling"),
            bq('video', "Que peut-on trouver dans le secteur « Terroir » de Famiflora ?", "Wat vind je in de sector « Terroir » van Famiflora?",
                "Des produits artisanaux et des spécialités locales", "Ambachtelijke producten en lokale specialiteiten", "Du matériel de bureau et de la papeterie", "Kantoormateriaal en papierwaren", "Des outils motorisés uniquement", "Enkel gemotoriseerd gereedschap"),
            bq('video', "Outre les plantes, quel univers est présent pour la décoration ?", "Naast de planten, welke wereld is er voor decoratie?",
                "L'univers de la décoration intérieure et des senteurs", "De wereld van de binnendecoratie en geuren", "L'univers de la construction lourde", "De wereld van de zware bouw", "L'univers de l'automobile", "De wereld van de auto"),
            bq('video', "Quel secteur occupe une place centrale dès l'entrée du magasin ?", "Welke sector staat centraal meteen aan de ingang van de winkel?",
                "Le secteur des plantes et du marché aux fleurs", "De sector van de planten en de bloemenmarkt", "Le rayon de l'électronique", "De afdeling elektronica", "Le secteur des vêtements de sport", "De sector sportkledij"),
            bq('video', "Famiflora se définit comme un Garden Center, mais aussi comme…", "Famiflora omschrijft zich als een Garden Center, maar ook als…",
                "Une destination de loisirs et de promenade pour la famille", "Een uitstap- en wandelbestemming voor het gezin", "Un simple entrepôt de stockage", "Een gewoon opslagmagazijn", "Un centre de recherche scientifique", "Een wetenschappelijk onderzoekscentrum"),
            bq('video', "Quelle est la mission principale de Famiflora présentée dans la vidéo ?", "Wat is de hoofdmissie van Famiflora in de video?",
                "Proposer le plus grand choix de plantes et de déco au meilleur prix", "De grootste keuze aan planten en deco aanbieden aan de beste prijs", "Vendre exclusivement des produits de luxe", "Uitsluitend luxeproducten verkopen", "Devenir une chaîne de restauration rapide", "Een fastfoodketen worden"),
            bq('video', "Où dois-je me présenter quand je commence mon service ?", "Waar moet ik me aanmelden wanneer ik mijn dienst begin?",
                "Au département indiqué sur le contrat de travail", "Op de afdeling vermeld op het arbeidscontract", "À l'accueil", "Aan het onthaal", "Au stock", "In het magazijn"),
        ]];

        $caisse = ['pick' => ['pdf' => 6, 'video' => 4], 'questions' => [
            // ---- FORMATION CAISSE · PDF ----
            bq('pdf', "Que faut-il faire après avoir appliqué une remise ?", "Wat moet je doen na het toepassen van een korting?",
                "Agrafer la copie du ticket au bon de réduction et la remettre à l'accueil", "De kopie van het ticket aan de kortingsbon nieten en aan het onthaal afgeven", "Jeter la copie", "De kopie weggooien", "Garder la copie en caisse", "De kopie aan de kassa houden"),
            bq('pdf', "Que faut-il vérifier pour un bac de bières ?", "Wat moet je controleren bij een bak bier?",
                "Que toutes les bières sont identiques", "Dat alle bieren identiek zijn", "Seulement la marque du dessus", "Enkel het merk bovenaan", "Rien, elles ont toutes le même prix", "Niets, ze hebben allemaal dezelfde prijs"),
            bq('pdf', "Que faut-il faire en cas d'articles empilés ?", "Wat moet je doen bij gestapelde artikelen?",
                "Compter correctement et vérifier qu'aucun article n'est caché", "Correct tellen en controleren dat geen artikel verborgen is", "Scanner uniquement celui du dessus", "Enkel het bovenste scannen", "Faire confiance sans vérifier", "Vertrouwen zonder te controleren"),
            bq('pdf', "Comment sont vendus les arbres à racines nues ?", "Hoe worden bomen met blote wortel verkocht?",
                "Par lot de 10 ou 25 pièces", "Per lot van 10 of 25 stuks", "À l'unité", "Per stuk", "Au kilo", "Per kilo"),
            bq('pdf', "Que doit-on vérifier pour les pastilles parfumées ?", "Wat moet je controleren bij geurtabletten?",
                "Que le nombre de pastilles dans la boîte est correct", "Dat het aantal tabletten in de doos correct is", "Que la boîte est fermée", "Dat de doos gesloten is", "Que la couleur correspond au parfum", "Dat de kleur overeenkomt met de geur"),
            bq('pdf', "Comment faut-il procéder pour les articles lourds restés dans le caddie ?", "Hoe ga je om met zware artikelen die in de winkelkar blijven?",
                "Les contrôler soigneusement avant de scanner", "Ze zorgvuldig controleren voor het scannen", "Ne pas vérifier", "Niet controleren", "Les scanner uniquement si le client le demande", "Ze enkel scannen als de klant het vraagt"),
            bq('pdf', "Via l'application, le client peut :", "Via de app kan de klant:",
                "Scanner les articles pour connaître le prix", "De artikelen scannen om de prijs te kennen", "Payer sans passer en caisse", "Betalen zonder langs de kassa te gaan", "Modifier les prix en caisse", "De prijzen aan de kassa wijzigen"),
            bq('pdf', "Si un article n'a pas de code-barres, il faut :", "Als een artikel geen barcode heeft, moet je:",
                "Appuyer sur « Assistance »", "Op « Assistentie » drukken", "Inventer le prix", "De prijs verzinnen", "Le laisser passer", "Het laten passeren"),
            bq('pdf', "Les pots et les plantes sont vendus :", "Potten en planten worden verkocht:",
                "Séparément", "Apart", "Toujours ensemble", "Altijd samen", "Selon la taille du pot", "Volgens de grootte van de pot"),
            bq('pdf', "Dans quel ordre faut-il scanner les articles ?", "In welke volgorde moet je de artikelen scannen?",
                "Bas du caddie - haut du caddie - tapis de caisse", "Onderkant kar - bovenkant kar - kassaband", "Tapis - bas du caddie - haut du caddie", "Kassaband - onderkant kar - bovenkant kar", "Haut du caddie - tapis - bas du caddie", "Bovenkant kar - kassaband - onderkant kar"),
            bq('pdf', "Que faire lorsqu'un client présente une carte de réduction à la caisse ?", "Wat doe je wanneer een klant een kortingskaart toont aan de kassa?",
                "Vérifier sa carte d'identité", "Zijn identiteitskaart controleren", "Demander sa carte bancaire", "Zijn bankkaart vragen", "Lui faire confiance sans vérification", "Hem vertrouwen zonder controle"),
            bq('pdf', "Sous quelle forme est proposée la carte de fidélité chez Famiflora ?", "In welke vorm wordt de getrouwheidskaart bij Famiflora aangeboden?",
                "Carte traditionnelle et application smartphone", "Traditionele kaart en smartphone-app", "Carte papier uniquement", "Enkel papieren kaart", "Application uniquement pour tous les clients", "Enkel de app voor alle klanten"),
            bq('pdf', "Quel document est remis au client après un paiement ?", "Welk document krijgt de klant na een betaling?",
                "Un reçu ou ticket", "Een bon of ticket", "Une fiche de stock", "Een voorraadfiche", "Un bon interne", "Een interne bon"),
            bq('pdf', "Quel est le rôle principal d'une caisse dans un magasin ?", "Wat is de hoofdrol van een kassa in een winkel?",
                "Encaisser les paiements", "De betalingen innen", "Gérer les stocks", "De voorraad beheren", "Organiser les horaires", "De uurroosters organiseren"),
            // ---- FORMATION CAISSE · VIDÉO ----
            bq('video', "Quelle attitude du client peut être constatée visuellement ?", "Welke houding van de klant kan je visueel vaststellen?",
                "Il semble pressé", "Hij lijkt gehaast", "Il compare les produits", "Hij vergelijkt de producten", "Il a faim", "Hij heeft honger"),
            bq('video', "Peut-on appliquer une remise manuelle sur un article ?", "Kan je een manuele korting toepassen op een artikel?",
                "Oui, via le champ « Remise % » (Assistance)", "Ja, via het veld « Korting % » (Assistentie)", "Non, les prix sont bloqués", "Nee, de prijzen zijn geblokkeerd", "Oui, en barrant le prix avec un marqueur", "Ja, door de prijs door te strepen met een stift"),
            bq('video', "Lors du passage en caisse, quel fait est observable ?", "Wat is waarneembaar bij het passeren aan de kassa?",
                "Le client oublie volontairement un article", "De klant vergeet bewust een artikel", "Tous les articles sont posés sur le tapis", "Alle artikelen liggen op de band", "Le client n'aime pas faire la queue", "De klant staat niet graag in de rij"),
            bq('video', "Quel comportement peut poser problème en caisse ?", "Welk gedrag kan een probleem vormen aan de kassa?",
                "Un article reste dans un sac", "Een artikel blijft in een zak", "Le client dit bonjour", "De klant zegt goedendag", "Le client paie en carte", "De klant betaalt met kaart"),
            bq('video', "Que faire si le client dit avoir été débité deux fois ?", "Wat doe je als de klant zegt twee keer te zijn gedebiteerd?",
                "Contrôler le ticket et alerter le responsable", "Het ticket controleren en de verantwoordelijke verwittigen", "Dire que c'est impossible", "Zeggen dat het onmogelijk is", "Refuser de vérifier", "Weigeren te controleren"),
            bq('video', "Le ticket ne s'imprime pas, que faire ?", "Het ticket wordt niet afgedrukt, wat doe je?",
                "Vérifier le rouleau de papier", "De papierrol controleren", "Dire au client que ce n'est pas grave", "De klant zeggen dat het niet erg is", "Annuler le paiement", "De betaling annuleren"),
            bq('video', "Que faire si un client est insatisfait sans raison claire ?", "Wat doe je als een klant ontevreden is zonder duidelijke reden?",
                "Rester calme et à l'écoute", "Kalm blijven en luisteren", "Mettre fin à l'échange", "Het gesprek beëindigen", "L'ignorer", "Hem negeren"),
            bq('video', "La caissière pense avoir oublié un article. Quelle est la bonne réaction ?", "De caissière denkt een artikel vergeten te zijn. Wat is de juiste reactie?",
                "Vérifier et re-scanner si nécessaire", "Controleren en opnieuw scannen indien nodig", "Continuer normalement", "Gewoon doorgaan", "Demander au client de revenir plus tard", "De klant vragen later terug te komen"),
            bq('video', "Quel comportement peut évoquer une tentative de fraude ?", "Welk gedrag kan wijzen op een poging tot fraude?",
                "Le client cache un article", "De klant verbergt een artikel", "Le client demande un prix", "De klant vraagt een prijs", "Le client sourit", "De klant glimlacht"),
            bq('video', "Que faire si le TPE est lent ?", "Wat doe je als de betaalterminal traag is?",
                "Patienter et vérifier la connexion", "Geduld hebben en de verbinding controleren", "S'énerver", "Boos worden", "L'éteindre brusquement", "Hem bruusk uitzetten"),
            bq('video', "Que faire si le client retire sa carte trop tôt ?", "Wat doe je als de klant zijn kaart te vroeg terugtrekt?",
                "Vérifier si la transaction est passée", "Controleren of de transactie is doorgegaan", "Refuser le paiement", "De betaling weigeren", "Annuler directement", "Meteen annuleren"),
            bq('video', "Que faire si un client devient agressif en caisse ?", "Wat doe je als een klant agressief wordt aan de kassa?",
                "Rester calme et prévenir un responsable (touche Assistance)", "Kalm blijven en een verantwoordelijke verwittigen (toets Assistentie)", "Ignorer complètement", "Volledig negeren", "Répondre sur le même ton", "Op dezelfde toon antwoorden"),
            bq('video', "Pourquoi manipuler certains produits avant le scan ?", "Waarom sommige producten manipuleren voor het scannen?",
                "Pour vérifier qu'aucun article n'est caché", "Om te controleren dat geen artikel verborgen is", "Pour les ranger", "Om ze op te bergen", "Pour gagner du temps", "Om tijd te winnen"),
            bq('video', "Un petit article est coincé dans un gros. Que faire ?", "Een klein artikel zit vast in een groot. Wat doe je?",
                "Vérifier et séparer les produits", "De producten controleren en scheiden", "Scanner le gros uniquement", "Enkel het grote scannen", "Ignorer", "Negeren"),
            bq('video', "Un client garde un objet dans sa main (canette de coca-cola). Que faire ?", "Een klant houdt een voorwerp in zijn hand (blikje cola). Wat doe je?",
                "Lui demander de le poser sur le tapis ou une preuve d'achat", "Hem vragen het op de band te leggen of een aankoopbewijs te tonen", "Ne rien dire", "Niets zeggen", "Attendre qu'il ait fini de la boire", "Wachten tot hij het opgedronken heeft"),
            bq('video', "Pourquoi contrôler les articles volumineux ? (barbecue, piscines)", "Waarom volumineuze artikelen controleren? (barbecue, zwembaden)",
                "Pour vérifier le prix et la dénomination du produit", "Om de prijs en de benaming van het product te controleren", "Pour les déplacer", "Om ze te verplaatsen", "Pour les vendre plus cher", "Om ze duurder te verkopen"),
            bq('video', "Pourquoi regarder à la fois le tapis et le caddie ?", "Waarom zowel de band als de kar bekijken?",
                "Pour avoir une vision complète des articles", "Om een volledig zicht op de artikelen te hebben", "Pour aller plus vite", "Om sneller te gaan", "Pour surveiller le client", "Om de klant in de gaten te houden"),
            bq('video', "Un client pose ses articles en plusieurs fois. Quel risque ?", "Een klant legt zijn artikelen in meerdere keren. Welk risico?",
                "Oublier un article entre deux passages", "Een artikel vergeten tussen twee keer", "Perdre du temps", "Tijd verliezen", "Aucun", "Geen"),
            bq('video', "Que faire si un article passe sans bip du scanner ?", "Wat doe je als een artikel passeert zonder piep van de scanner?",
                "Repasser l'article", "Het artikel opnieuw scannen", "Laisser", "Laten", "Continuer", "Doorgaan"),
            bq('video', "Que faire face à un client qui a plusieurs sacs de course fermés posés sur le chariot ?", "Wat doe je bij een klant met meerdere gesloten boodschappentassen op de kar?",
                "Demander au client d'ouvrir les sacs", "De klant vragen de tassen te openen", "L'ignorer", "Hem negeren", "Scanner les sacs", "De tassen scannen"),
        ]];

        return ['Onboarding' => $onboarding, 'Formation Caisse' => $caisse];
    }

    // Construit le quiz_json (FR ou NL) d'un quiz beta à partir de la banque.
    function betaQuizJson($nom, $lang)
    {
        $bank = betaQuizBank();
        if (!isset($bank[$nom])) { return ''; }
        $nl = ($lang === 'nl');
        $out = ['pick' => $bank[$nom]['pick'], 'questions' => []];
        foreach ($bank[$nom]['questions'] as $q) {
            $out['questions'][] = [
                'q'       => $nl ? $q['q_nl'] : $q['q'],
                'options' => $nl ? $q['options_nl'] : $q['options'],
                'correct' => $q['correct'],
                'type'    => $q['type'],
                'source'  => $q['source'],
            ];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    // Installe (écrase) le quiz FR + NL sur un module beta. Renvoie true si OK.
    function betaQuizInstall(PDO $db, $moduleId, $nom)
    {
        $fr = betaQuizJson($nom, 'fr');
        if ($fr === '') { return false; }
        $nl = betaQuizJson($nom, 'nl');
        try {
            $db->prepare('UPDATE modules SET quiz_json = ?, quiz_json_nl = ? WHERE id = ?')
               ->execute([$fr, $nl, (int) $moduleId]);
            return true;
        } catch (Throwable $e) {
            // Colonne NL absente ? On installe au moins le FR.
            try { $db->prepare('UPDATE modules SET quiz_json = ? WHERE id = ?')->execute([$fr, (int) $moduleId]); return true; }
            catch (Throwable $e2) { return false; }
        }
    }

    // À l'AFFICHAGE : garantit que le module beta a bien son quiz (FR + NL). Si le
    // quiz a été effacé (ex. ré-upload de contenu), on le ré-installe. Renvoie le
    // module éventuellement complété (quiz_json / quiz_json_nl à jour).
    function betaQuizEnsure(PDO $db, array $module)
    {
        if ((string) ($module['roles'] ?? '') !== 'beta') { return $module; }
        $nom = (string) ($module['nom'] ?? '');
        $bank = betaQuizBank();
        if (!isset($bank[$nom])) { return $module; }
        $frVide = trim((string) ($module['quiz_json'] ?? '')) === '';
        $nlVide = trim((string) ($module['quiz_json_nl'] ?? '')) === '';
        if ($frVide || $nlVide) {
            if (betaQuizInstall($db, (int) $module['id'], $nom)) {
                $module['quiz_json']    = betaQuizJson($nom, 'fr');
                $module['quiz_json_nl'] = betaQuizJson($nom, 'nl');
            }
        }
        return $module;
    }
}
