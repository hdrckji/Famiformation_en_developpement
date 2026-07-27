<?php
// ============================================================
// beta_quiz_data.php — BANQUE de questions des quiz de la version BETA.
//   Deux quiz : « Onboarding » et « Formation Caisse ». Chaque question est
//   taguée par sa SOURCE ('pdf' ou 'video'). À chaque passage, quiz.php tire
//   10 questions : 6 du PDF + 4 de la vidéo (voir la clé 'pick').
//   La bonne réponse est TOUJOURS la 1re option (index 0).
//   Ces quiz sont installés sur les modules beta par gestion-beta.php.
// ============================================================

if (!function_exists('betaQuizBank')) {
    // Petit helper : question à réponse unique, bonne réponse = 1re option.
    function bq($source, $q, $a, $b, $c)
    {
        return ['q' => $q, 'options' => [$a, $b, $c], 'correct' => [0], 'type' => 'single', 'source' => $source];
    }

    /**
     * @return array{Onboarding: array, 'Formation Caisse': array}
     *   Chaque quiz : ['pick' => ['pdf'=>6,'video'=>4], 'questions' => [...]]
     */
    function betaQuizBank()
    {
        $onboarding = ['pick' => ['pdf' => 6, 'video' => 4], 'questions' => [
            // ---- ONBOARDING · PDF ----
            bq('pdf', "Où pouvez-vous déposer votre vélo ou votre trottinette au travail ?", "Dans l'abri pour vélos", "Sur le parking", "Dans le magasin"),
            bq('pdf', "Qui est votre supérieur hiérarchique direct pour les questions de planning ou de travail ?", "Teamcoach", "Ressource humaine", "Responsable de rayon"),
            bq('pdf', "Que faut-il faire en cas d'évacuation ?", "Restez calme", "Paniquer", "Continuer à travailler"),
            bq('pdf', "Si vous avez un problème de contrat ou de prestation, à qui devez-vous vous adresser ?", "Ressource humaine", "Responsable du magasin", "Teamcoach"),
            bq('pdf', "Combien y a-t-il de valeurs dans l'entreprise ?", "6", "3", "1"),
            bq('pdf', "Quel est le bon code vestimentaire à avoir ?", "Veste Famiflora avec prénom et chaussures fermées", "Pyjama et pantoufles", "Tenue de vacances et sandales"),
            bq('pdf', "Quelle est la bonne attitude à avoir ?", "Positif", "Négatif", "Neutre"),
            bq('pdf', "Comment procéder pour une remise sur un article défectueux ?", "Sélectionner l'article et cliquer sur « remise article »", "Modifier le prix directement", "Supprimer l'article"),
            bq('pdf', "Où se trouve la liste des secouristes (premiers secours) ?", "À l'accueil du magasin", "Au réfectoire", "Sur le site internet de Famiflora"),
            bq('pdf', "Que signifie un signal acoustique « continu » dans le magasin ?", "Une alarme pour l'évacuation totale du site", "Une annonce micro importante", "Le début de la pause déjeuner"),
            bq('pdf', "Que devez-vous toujours conserver après un achat personnel ?", "Votre ticket de caisse comme preuve", "L'emballage du produit", "L'étiquette de prix"),
            bq('pdf', "Quand pouvez-vous effectuer vos achats personnels ?", "Pendant vos pauses ou après votre journée de travail", "À tout moment si vous n'avez pas de clients", "Uniquement le samedi matin avant l'ouverture"),
            bq('pdf', "Quels chariots faut-il utiliser pour déplacer des objets en interne ?", "Les chariots sans cadenas", "Les chariots avec cadenas", "N'importe quel chariot client vide"),
            bq('pdf', "Où se trouve la presse à carton sur le site de Mouscron ?", "À l'extérieur au niveau du quai 5", "Dans le réfectoire", "Sur le parking personnel"),
            bq('pdf', "Où doit impérativement rester votre téléphone portable au travail ?", "Dans votre casier au vestiaire", "Dans votre poche en mode silencieux", "Sur le comptoir de votre rayon"),
            bq('pdf', "En cas d'absence, qui devez-vous prévenir en premier ?", "Votre teamcoach avant le début de votre travail", "La direction générale par email", "Un collègue de votre rayon par SMS"),
            bq('pdf', "Quelle est la durée totale d'une pause complète journalière ?", "60 minutes", "30 minutes", "90 minutes"),
            bq('pdf', "Quelle est la règle concernant le prêt de votre badge ?", "C'est une faute grave strictement interdite", "C'est autorisé pour dépanner un collègue", "C'est autorisé uniquement pour les étudiants"),
            bq('pdf', "Quand devez-vous pointer votre début de travail ?", "Une fois en tenue et les effets personnels au casier", "Dès votre arrivée sur le parking", "Avant de mettre votre tenue de travail"),
            bq('pdf', "Où les collaborateurs doivent-ils obligatoirement garer leur voiture ?", "Sur le parking « personnel » à gauche de l'entrée", "Sur le parking client devant l'entrée", "Dans les rues adjacentes au magasin"),
            // ---- ONBOARDING · VIDÉO ----
            bq('video', "Quel sentiment la vidéo cherche-t-elle à transmettre aux futurs collègues ?", "La fierté de travailler dans un univers vert et familial", "La peur de la foule", "L'ennui du travail de bureau"),
            bq('video', "Famiflora est situé dans quelle zone géographique ?", "À la frontière franco-belge", "À la frontière belgo-allemande", "À la frontière belgo-luxembourgeoise"),
            bq('video', "Comment appelle-t-on le secteur technique du jardin (pelles, terreaux) ?", "Le secteur Jardin ou Outillage", "Le secteur Cosmétique", "Le secteur Jouets"),
            bq('video', "Que trouve-t-on au rayon « Mobilier de jardin » ?", "Des salons de jardin, des parasols et des barbecues", "Des lits superposés", "Des bureaux d'ordinateur"),
            bq('video', "Quelle attitude attend-on du personnel envers les clients ?", "Accueil, sourire et conseil expert", "Ignorer les clients", "Rester uniquement derrière la caisse"),
            bq('video', "Pourquoi les serres immenses sont-elles un avantage ?", "Offrir une luminosité naturelle optimale pour les plantes", "Cacher le stock aux yeux des clients", "Stocker l'eau de pluie pour la ville"),
            bq('video', "Quel est l'un des points forts de l'expérience client chez Famiflora ?", "Pouvoir faire toutes ses courses en un seul lieu", "Devoir changer de magasin pour chaque article", "L'absence totale de personnel en rayon"),
            bq('video', "Famiflora propose de quoi se restaurer, comment s'appelle cet espace ?", "Le Resto ou l'espace cafétéria", "La Cantine scolaire", "Le snack mobile à l'entrée"),
            bq('video', "Que trouve-t-on dans le secteur « Pépinière » extérieur ?", "Des arbres, des arbustes et des plantes vivaces", "Des poissons tropicaux", "Des meubles de cuisine"),
            bq('video', "Quel est l'événement majeur de fin d'année chez Famiflora ?", "Le Marché de Noël", "La foire aux pneus d'hiver", "La fête des moissons"),
            bq('video', "Comment Famiflora aide-t-il les clients pour les achats volumineux ?", "En mettant à disposition des chariots adaptés", "En demandant aux clients de porter leurs sacs", "En interdisant les gros achats le samedi"),
            bq('video', "Quelle est la particularité du secteur fleurs coupées ?", "Des arrivages quotidiens pour garantir la fraîcheur", "Des fleurs en plastique uniquement", "Des fleurs qui ne durent qu'une journée"),
            bq('video', "Quel type de produits trouve-t-on dans le secteur Animalerie ?", "De l'alimentation et des accessoires pour animaux", "Uniquement des chevaux de course", "Des produits de nettoyage pour voitures"),
            bq('video', "Famiflora possède un rayon dédié aux animaux, comment s'appelle-t-il ?", "L'Animalerie", "Le Zoo-Parc", "La Ferme Famiflora"),
            bq('video', "Quel est l'engagement de Famiflora concernant les prix ?", "Garantir des prix bas et attractifs toute l'année", "Augmenter les prix durant les week-ends", "Ne jamais afficher les prix en rayon"),
            bq('video', "Que peut-on trouver dans le secteur « Terroir » de Famiflora ?", "Des produits artisanaux et des spécialités locales", "Du matériel de bureau et de la papeterie", "Des outils motorisés uniquement"),
            bq('video', "Outre les plantes, quel univers est présent pour la décoration ?", "L'univers de la décoration intérieure et des senteurs", "L'univers de la construction lourde", "L'univers de l'automobile"),
            bq('video', "Quel secteur occupe une place centrale dès l'entrée du magasin ?", "Le secteur des plantes et du marché aux fleurs", "Le rayon de l'électronique", "Le secteur des vêtements de sport"),
            bq('video', "Famiflora se définit comme un Garden Center, mais aussi comme…", "Une destination de loisirs et de promenade pour la famille", "Un simple entrepôt de stockage", "Un centre de recherche scientifique"),
            bq('video', "Quelle est la mission principale de Famiflora présentée dans la vidéo ?", "Proposer le plus grand choix de plantes et de déco au meilleur prix", "Vendre exclusivement des produits de luxe", "Devenir une chaîne de restauration rapide"),
            bq('video', "Où dois-je me présenter quand je commence mon service ?", "Au département indiqué sur le contrat de travail", "À l'accueil", "Au stock"),
        ]];

        $caisse = ['pick' => ['pdf' => 6, 'video' => 4], 'questions' => [
            // ---- FORMATION CAISSE · PDF ----
            bq('pdf', "Que faut-il faire après avoir appliqué une remise ?", "Agrafer la copie du ticket au bon de réduction et la remettre à l'accueil", "Jeter la copie", "Garder la copie en caisse"),
            bq('pdf', "Que faut-il vérifier pour un bac de bières ?", "Que toutes les bières sont identiques", "Seulement la marque du dessus", "Rien, elles ont toutes le même prix"),
            bq('pdf', "Que faut-il faire en cas d'articles empilés ?", "Compter correctement et vérifier qu'aucun article n'est caché", "Scanner uniquement celui du dessus", "Faire confiance sans vérifier"),
            bq('pdf', "Comment sont vendus les arbres à racines nues ?", "Par lot de 10 ou 25 pièces", "À l'unité", "Au kilo"),
            bq('pdf', "Que doit-on vérifier pour les pastilles parfumées ?", "Que le nombre de pastilles dans la boîte est correct", "Que la boîte est fermée", "Que la couleur correspond au parfum"),
            bq('pdf', "Comment faut-il procéder pour les articles lourds restés dans le caddie ?", "Les contrôler soigneusement avant de scanner", "Ne pas vérifier", "Les scanner uniquement si le client le demande"),
            bq('pdf', "Via l'application, le client peut :", "Scanner les articles pour connaître le prix", "Payer sans passer en caisse", "Modifier les prix en caisse"),
            bq('pdf', "Si un article n'a pas de code-barres, il faut :", "Appuyer sur « Assistance »", "Inventer le prix", "Le laisser passer"),
            bq('pdf', "Les pots et les plantes sont vendus :", "Séparément", "Toujours ensemble", "Selon la taille du pot"),
            bq('pdf', "Dans quel ordre faut-il scanner les articles ?", "Bas du caddie - haut du caddie - tapis de caisse", "Tapis - bas du caddie - haut du caddie", "Haut du caddie - tapis - bas du caddie"),
            bq('pdf', "Que faire lorsqu'un client présente une carte de réduction à la caisse ?", "Vérifier sa carte d'identité", "Demander sa carte bancaire", "Lui faire confiance sans vérification"),
            bq('pdf', "Sous quelle forme est proposée la carte de fidélité chez Famiflora ?", "Carte traditionnelle et application smartphone", "Carte papier uniquement", "Application uniquement pour tous les clients"),
            bq('pdf', "Quel document est remis au client après un paiement ?", "Un reçu ou ticket", "Une fiche de stock", "Un bon interne"),
            bq('pdf', "Quel est le rôle principal d'une caisse dans un magasin ?", "Encaisser les paiements", "Gérer les stocks", "Organiser les horaires"),
            // ---- FORMATION CAISSE · VIDÉO ----
            bq('video', "Quelle attitude du client peut être constatée visuellement ?", "Il semble pressé", "Il compare les produits", "Il a faim"),
            bq('video', "Peut-on appliquer une remise manuelle sur un article ?", "Oui, via le champ « Remise % » (Assistance)", "Non, les prix sont bloqués", "Oui, en barrant le prix avec un marqueur"),
            bq('video', "Lors du passage en caisse, quel fait est observable ?", "Le client oublie volontairement un article", "Tous les articles sont posés sur le tapis", "Le client n'aime pas faire la queue"),
            bq('video', "Quel comportement peut poser problème en caisse ?", "Un article reste dans un sac", "Le client dit bonjour", "Le client paie en carte"),
            bq('video', "Que faire si le client dit avoir été débité deux fois ?", "Contrôler le ticket et alerter le responsable", "Dire que c'est impossible", "Refuser de vérifier"),
            bq('video', "Le ticket ne s'imprime pas, que faire ?", "Vérifier le rouleau de papier", "Dire au client que ce n'est pas grave", "Annuler le paiement"),
            bq('video', "Que faire si un client est insatisfait sans raison claire ?", "Rester calme et à l'écoute", "Mettre fin à l'échange", "L'ignorer"),
            bq('video', "La caissière pense avoir oublié un article. Quelle est la bonne réaction ?", "Vérifier et re-scanner si nécessaire", "Continuer normalement", "Demander au client de revenir plus tard"),
            bq('video', "Quel comportement peut évoquer une tentative de fraude ?", "Le client cache un article", "Le client demande un prix", "Le client sourit"),
            bq('video', "Que faire si le TPE est lent ?", "Patienter et vérifier la connexion", "S'énerver", "L'éteindre brusquement"),
            bq('video', "Que faire si le client retire sa carte trop tôt ?", "Vérifier si la transaction est passée", "Refuser le paiement", "Annuler directement"),
            bq('video', "Que faire si un client devient agressif en caisse ?", "Rester calme et prévenir un responsable (touche Assistance)", "Ignorer complètement", "Répondre sur le même ton"),
            bq('video', "Pourquoi manipuler certains produits avant le scan ?", "Pour vérifier qu'aucun article n'est caché", "Pour les ranger", "Pour gagner du temps"),
            bq('video', "Un petit article est coincé dans un gros. Que faire ?", "Vérifier et séparer les produits", "Scanner le gros uniquement", "Ignorer"),
            bq('video', "Un client garde un objet dans sa main (canette de coca-cola). Que faire ?", "Lui demander de le poser sur le tapis ou une preuve d'achat", "Ne rien dire", "Attendre qu'il ait fini de la boire"),
            bq('video', "Pourquoi contrôler les articles volumineux ? (barbecue, piscines)", "Pour vérifier le prix et la dénomination du produit", "Pour les déplacer", "Pour les vendre plus cher"),
            bq('video', "Pourquoi regarder à la fois le tapis et le caddie ?", "Pour avoir une vision complète des articles", "Pour aller plus vite", "Pour surveiller le client"),
            bq('video', "Un client pose ses articles en plusieurs fois. Quel risque ?", "Oublier un article entre deux passages", "Perdre du temps", "Aucun"),
            bq('video', "Que faire si un article passe sans bip du scanner ?", "Repasser l'article", "Laisser", "Continuer"),
            bq('video', "Que faire face à un client qui a plusieurs sacs de course fermés posés sur le chariot ?", "Demander au client d'ouvrir les sacs", "L'ignorer", "Scanner les sacs"),
        ]];

        return ['Onboarding' => $onboarding, 'Formation Caisse' => $caisse];
    }
}
