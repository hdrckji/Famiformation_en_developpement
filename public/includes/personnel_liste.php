<?php
// ============================================================
// personnel_liste.php — LISTE DU PERSONNEL FAMIFLORA.
//
// Sert à distinguer un vrai membre du personnel d'un simple visiteur :
// un compte beta dont le nom et le prénom figurent ici doit passer en
// profil employé (voir tri_profils.php et l'inscription depuis le quiz).
//
// Généré depuis « 161c - Liste du personnel coordonnées de contact.xlsx ».
// Fichier PHP et non JSON À DESSEIN : posé sous public/, un .json serait
// téléchargeable par n'importe qui ; ce .php n'affiche rien s'il est
// appelé directement dans un navigateur.
//
// 270 personnes. Pour mettre à jour : réexporter l'Excel et régénérer.
// ============================================================

if (!function_exists('personnelListe')) {
    /** @return array<int, array{0:string,1:string,2:string}> [nom, prénom, dossier] */
    function personnelListe()
    {
        return [
            ['Amabou', 'Alexandrine', 'Famiresto BV'],
            ['Anciaux', 'Elise', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Anderson', 'Bryan', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Andrieu', 'Alison', 'FAMILOGISTICS NV'],
            ['Assili', 'Kamal', 'Famiresto BV'],
            ['BOULANGER', 'CLOTILDE', 'MIX'],
            ['BRAHIMI', 'BOUCHRA', 'Famiresto BV'],
            ['BULCOURT', 'MARTINE', 'Famiresto BV'],
            ['Battendier', 'Iona', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Bayens', 'Nicolas', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Becque', 'Samantha', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Bennhass', 'Mohammed', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Besnard', 'Anthony', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Bil', 'Marleen', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Blanco', 'Paolo', 'FAMIFLORA NV'],
            ['Bocklant', 'Caroline', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Boguniowski', 'Mariusz', 'FAMILOGISTICS NV'],
            ['Borgies', 'Sébastien', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Bossuyt', 'Geraldine', 'Famiresto BV'],
            ['Bossuyt', 'Lieve', 'FAMIFLOR NV'],
            ['Bostyn', 'Laurence', 'FAMIFLORA NV'],
            ['Boudesocque', 'Olivia', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Boudry', 'Sylvain', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Brandt', 'Carel', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Bredeche', 'Fanny', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Broidioi', 'Elodie', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Bucholtz', 'Virginie', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Buidin', 'Adeline', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Butez', 'Ludivine', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Buyle', 'Céline', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Béal', 'Nathalie', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Carlier', 'Isabelle', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Carne', 'Christelle', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Carvalho', 'David', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Castelain', 'Sarah', 'Famiresto BV'],
            ['Casteleyn', 'Julie', 'FAMILOGISTICS NV'],
            ['Catoire', 'Bruno', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Chamerois', 'Calista', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Chombeau', 'Rémi', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Clusman', 'Gilles', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Coisne', 'Michaël', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Corbanie', 'Steve', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Coupez', 'Alexandre', 'FAMITRANS BV'],
            ['Coussement', 'Marte', 'FAMIFLORA NV'],
            ['Csala', 'Coraline', 'Famiresto BV'],
            ['D\'Haene', 'Jenny', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['D\'Herbomez', 'François', 'FAMIFLORA MOUSCRON DECO NV'],
            ['DAPPRIMEE', 'SEVERINE', 'Famiresto BV'],
            ['DEFERT', 'OLIVIER', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['DEGAVRE', 'MARIE-HELENE', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['DEJAEGHERE', 'ANNEKE', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['DELATTRE', 'ALICIA', 'FLEURS COUPEES'],
            ['DELEPAUT', 'CELINE', 'Famiresto BV'],
            ['DELEPAUT', 'CHLOE', 'Famiresto BV'],
            ['DER KINDEREN', 'NICOLAS', 'NUIT'],
            ['DESTEMBER', 'SEVERINE', 'DECO INTERIEUR'],
            ['DESTREBECQ', 'ALISON', 'Famiresto BV'],
            ['DUMONT', 'LEANDRE', 'Famiresto BV'],
            ['DUQUENNOY', 'MARJORIE', 'DECO'],
            ['Daelemans', 'Jean François', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Daene', 'Gauthier', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Dalida', 'Aira Rose', 'Famiresto BV'],
            ['De Bouvere', 'Aurélie', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['De Smet', 'Bart', 'Indépendant'],
            ['De Weerdt', 'Bryan', 'FAMILOGISTICS NV'],
            ['Debeir', 'Hugo', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Debuyck', 'Thomas', 'FAMIFLORA NV'],
            ['Deceuninck', 'Tine', 'Indépendant'],
            ['Declercq', 'Maxime', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Declercq', 'Remi', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Deconinck', 'Kim', 'Indépendant'],
            ['Deforche', 'Dimitri', 'FAMIFLORA NV'],
            ['Deforche', 'Ludivine', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Defrère', 'Jenny', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Dejas', 'Timéon', 'Famiresto BV'],
            ['Dekaezemacker', 'Davy', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Dekeyser', 'Kevin', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Del Vento', 'Amandine', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Delacroix', 'Sabine', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Delarue', 'Camille', 'FAMIFLORA NV'],
            ['Delavallée', 'Charlotte', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Delporte', 'Kevin', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Delrive', 'Jennifer', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Demeester', 'Vanessa', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Depontieu', 'Audrey', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Dericq', 'David', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Deruwez', 'Nathalie', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Derynck', 'Severine', 'Famiresto BV'],
            ['Descamps', 'Jordan', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Desmet', 'Clément', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Desneulin', 'Corentin', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Despeer', 'Florian', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Deunynck', 'Lenny', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Dewaele', 'Stéphane', 'FAMILOGISTICS NV'],
            ['Dewaele', 'Theo', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Dewattines', 'Philippe', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Dhavelons', 'Cindy', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Dhont', 'Philippe', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Dhulst', 'Honorine', 'FAMIFLORA NV'],
            ['Di Stefano', 'Giuliano', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Dieudonné', 'Quentin', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Dillies', 'Nathan', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Diop', 'Babacar-Malick', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Doignon', 'Pascal', 'FAMILOGISTICS NV'],
            ['Dopchie', 'David', 'FAMIFLORA NV'],
            ['Dubois', 'Leila', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Ducatillon', 'Jean-Guillaume', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Dufossé', 'Marie', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Dumont', 'Claire', 'FAMIFLORA NV'],
            ['Duponcheel', 'Lola', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Duytschaever', 'Francis', 'FAMIFLOR NV'],
            ['El Hamouchi', 'Solaïman', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Esser', 'Jean-Christophe', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['FARRIS', 'FABRIZIO', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['FLAMME', 'TRECY', 'Famiresto BV'],
            ['Faihy', 'Michael', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Fares', 'M\'Bark', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Fauconnier', 'Cammie', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Faveau', 'Mikaël', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Favier', 'Jérome', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Felicio Da Nave', 'Julien', 'FAMIFLORA NV'],
            ['Ferret', 'Dany', 'Famiresto BV'],
            ['Flagothier', 'Séverine', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Florence', 'Delphine', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Fretin', 'Dorian', 'FAMIFLORA MOUSCRON DECO NV'],
            ['GENTY', 'FABIEN', 'FAMILOGISTICS NV'],
            ['GORUS', 'CINDY', 'FAMIFLORA MOUSCRON DECO NV'],
            ['GRIMONPONT', 'MYRIAM', 'Famiresto BV'],
            ['Garbe', 'Clémence', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Geeraert', 'Kevin', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Gellynck', 'Lien', 'FAMIFLORA NV'],
            ['Gesquiere', 'Gaël', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Ghisse', 'Matthieu', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Girault', 'Arthur', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Glorieux', 'Adrien', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Gyselinck', 'Claudine', 'FAMIFLORA NV'],
            ['Gévar', 'Emeric', 'FAMIFLORA MOUSCRON DECO NV'],
            ['HESPEL', 'THIBAUT', 'ACHAT'],
            ['Hamrouni', 'Sofiane', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Hanoune', 'Samir', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Harrachif', 'Naël', 'FAMITRANS BV'],
            ['Hendrickx', 'Jimmy', 'FAMIFLORA NV'],
            ['Hermez', 'Kevin', 'Famiresto BV'],
            ['Herpoel', 'Olivier', 'FAMIFLORA NV'],
            ['Heytens', 'Patrick', 'Indépendant'],
            ['Himpe', 'Steve', 'FAMILOGISTICS NV'],
            ['Hostens', 'Grégoire', 'FAMIFLORA NV'],
            ['Houzé', 'Christine', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Huchez', 'Natacha', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Hurteux', 'Rachel', 'FAMIFLORA NV'],
            ['Israël', 'Anaïs', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Jacquet', 'Laure', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Janssens', 'Anne-Lise', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Jouen', 'Gaetan', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Kahamba', 'Feza', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Kama', 'Abdoulaye', 'Famiresto BV'],
            ['Kaur', 'Parvinder', 'FAMIFLORA NV'],
            ['Kesteloot', 'Lien', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Kesteloot', 'Lies', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Knockaert', 'Hugo', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Kocheida', 'Rayan', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Kubat', 'Charlotte', 'FAMIFLORA MOUSCRON DECO NV'],
            ['LARGEAIS', 'SABRINA', 'Famiresto BV'],
            ['LAROUCHE', 'CECILIA', 'FAMIZOO'],
            ['LEMAIRE', 'ALEXANDRE', 'NUIT'],
            ['LOISEAU', 'FABRICE', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Lahousse', 'Laurent', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Lebrun', 'Mathieu', 'FAMIFLOR NV'],
            ['Lecoeur', 'Marine', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Leduc', 'François', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Lefranc', 'Lucas', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Legrain', 'Suzie', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Lejeune', 'Samantha', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Leman', 'Arthur', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Lenglet', 'Laouen', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Lestoquoy', 'Aurélie', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Liagre', 'Clement', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Lilouche', 'Karim', 'FAMIFLORA NV'],
            ['Locquet', 'Davinia', 'FAMIFLORA NV'],
            ['Loiseau', 'Cyril', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Lucidarme', 'Eloïse', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Léger', 'Vianney', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Maertens', 'Veerle', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Marginet', 'Quentin', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Martins', 'Audrey', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Mauro', 'Sylvio', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Merstdag', 'Nicole', 'Famiresto BV'],
            ['Messelis', 'Niels', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Morel', 'Fanny', 'FAMIFLORA NV'],
            ['Mullebrouck', 'Dirk', 'FAMIFLORA NV'],
            ['Nasri', 'Mohammed', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Nieuwenburg', 'René', 'FAMIFLORA NV'],
            ['OUASSAIDI', 'RAFIK', 'Parking'],
            ['Oosterlinck', 'Emmy', 'Famiresto BV'],
            ['PANNECOUCQUE', 'CLARA', 'FAMIFLORA NV'],
            ['PIERPONT', 'MARINE', 'LEONIDAS'],
            ['Paclawski', 'Mathys', 'Famiresto BV'],
            ['Pannier', 'Geraldine', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Pascual', 'Daniel', 'FAMIFLORA NV'],
            ['Penot', 'Nicolas', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Perraudin', 'Anais', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Perrin', 'Alexandre', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Philalome', 'Sarah', 'Famiresto BV'],
            ['Pittellioen', 'Nancy', 'FAMILOGISTICS NV'],
            ['Pomothy', 'Tony', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Quint', 'Peggy', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['RIGOLE', 'JORDAN', 'BOIS'],
            ['ROGE', 'JEREMY', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Ramboer', 'Aline', 'FAMIFLORA NV'],
            ['Renaud', 'Vincent', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Ringeval', 'Noémie', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Roos', 'Melinda', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Rossel', 'Thomas', 'Indépendant'],
            ['Rousseaux', 'Charles', 'FAMIFLORA NV'],
            ['Ruicu', 'Daniel', 'FAMITRANS BV'],
            ['Rys', 'Lukas', 'FAMIFLORA MOUSCRON DECO NV'],
            ['SIX', 'CORINNE', 'Famiresto BV'],
            ['Samyn', 'Anne-Laure', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Savary', 'Maéva', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Schamp', 'Michaël', 'FAMILOGISTICS NV'],
            ['Schoeps', 'Matthieu', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Schoore', 'Emilie', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Seggio', 'Josephine', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Seys', 'William', 'FAMIFLORA NV'],
            ['Six', 'Davy', 'FAMIFLORA NV'],
            ['Sobiecki', 'Fabien', 'FAMIFLORA NV'],
            ['Souaikeur', 'Saïd', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Stievenaert', 'Julien', 'FAMIFLORA MOUSCRON DECO NV'],
            ['TENKEU KWAYEP', 'JOSELYNE', 'Famiresto BV'],
            ['Taelman', 'Jennifer', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Thery', 'Philippe', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Tiston', 'Maxime', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Tonneaux', 'Isabelle', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Turck', 'Sebastien', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Van Calemont', 'Muriel', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Vandamme', 'Marilyn', 'FAMIFLORA NV'],
            ['Vanden Bulcke', 'Veronique', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Vandenberghe', 'Jan', 'FAMIFLORA NV'],
            ['Vandenbussche', 'Koen', 'Indépendant'],
            ['Vandenbussche', 'Pieterjan', 'Indépendant'],
            ['Vandenbussche', 'Roselinde', 'FAMIFLORA NV'],
            ['Vandenhove', 'Alex', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Vander Beken', 'Brecht', 'FAMIFLORA NV'],
            ['Vanderhaeghe', 'Elodie', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Vandevelde', 'Margaux', 'FAMIFLORA NV'],
            ['Vandorpe', 'Sylvie', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Vanhandsaeme', 'Annemie', 'FAMIFLORA NV'],
            ['Vanhecke', 'David', 'FAMIFLORA NV'],
            ['Vanmellaert', 'Julien', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Vanstraeselle', 'Margot', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Varrasse', 'Céline', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Vercaempst', 'Sylvia', 'FAMIFLORA MOUSCRON GREEN NV'],
            ['Verdonck', 'Alexia', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Verschoot', 'Matthias', 'FAMIFLORA NV'],
            ['Verset', 'Isabelle', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Verstaevel', 'Perrine', 'FAMIFLORA MOUSCRON GARDEN NV'],
            ['Verstraeten', 'Guy', 'FAMILOGISTICS NV'],
            ['Vervisch', 'Gudrun', 'FAMIFLOR NV'],
            ['Verwee', 'Josette', 'Famiresto BV'],
            ['Viane', 'Marine', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Villette', 'Emilie', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Vos', 'Katty', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Vossaert', 'Koen', 'FAMIFLORA NV'],
            ['Vérelle', 'Patricia', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Vérenne', 'Caroline', 'FAMIFLORA MOUSCRON DECO NV'],
            ['Watteel', 'Mattisse', 'FAMIFLORA NV'],
            ['Werquin', 'Laurent', 'FAMIFLORA MOUSCRON SERVICES NV'],
            ['Wybo', 'Christophe', 'FAMIFLOR NV'],
            ['Ysebaert', 'Sarah', 'FAMIFLORA MOUSCRON FAMIZOO NV'],
            ['Zajac', 'Constant', 'FAMIFLORA MOUSCRON DECO NV'],
            // Ajouts manuels, hors export Excel. À reporter dans l'export lors de
            // la prochaine mise à jour, sinon ils disparaîtront à la régénération.
            // Orthographe donnée deux fois différemment (« Louv » puis « Louvi ») :
            // on inscrit les deux, un doublon ne coûte rien alors qu'un nom raté
            // laisse quelqu'un bloqué en profil beta.
            ['Louv', 'Jean', 'AJOUT MANUEL'],
            ['Louvi', 'Jean', 'AJOUT MANUEL'],
        ];
    }
}

if (!function_exists('personnelNormalise')) {
    /**
     * Met un nom sous une forme comparable : minuscules, sans accents, sans
     * ponctuation ni espaces multiples. « De Smet », « DE SMET » et « de-smet »
     * deviennent la même chose — indispensable, l'export Excel mélange les
     * majuscules et les saisies des utilisateurs sont libres.
     */
    function personnelNormalise($s)
    {
        $s = (string) $s;
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        $s = strtr($s, [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n','ý'=>'y','ÿ'=>'y','œ'=>'oe','æ'=>'ae',
        ]);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}

if (!function_exists('personnelCle')) {
    /**
     * Clé de comparaison d'une personne, INSENSIBLE à l'ordre nom/prénom :
     * beaucoup de gens saisissent l'un pour l'autre à l'inscription, et une
     * inversion ne doit pas faire rater quelqu'un du personnel.
     */
    function personnelCle($nom, $prenom)
    {
        $a = personnelNormalise($nom);
        $b = personnelNormalise($prenom);
        if ($a === '' || $b === '') { return ''; }
        $p = [$a, $b];
        sort($p);
        return $p[0] . '|' . $p[1];
    }
}

if (!function_exists('personnelTrouve')) {
    /**
     * Cette personne fait-elle partie du personnel ?
     * @return array{nom:string,prenom:string,dossier:string}|null
     */
    function personnelTrouve($nom, $prenom)
    {
        static $index = null;
        if ($index === null) {
            $index = [];
            foreach (personnelListe() as $g) {
                $c = personnelCle($g[0], $g[1]);
                if ($c !== '') { $index[$c] = ['nom' => $g[0], 'prenom' => $g[1], 'dossier' => $g[2]]; }
            }
        }
        $c = personnelCle($nom, $prenom);
        return ($c !== '' && isset($index[$c])) ? $index[$c] : null;
    }
}

if (!function_exists('personnelRoleCible')) {
    /**
     * Profil attribué à un membre du personnel reconnu.
     * Un seul endroit à changer si le profil retenu doit évoluer.
     */
    function personnelRoleCible()
    {
        return 'employe_magasin';
    }
}

if (!function_exists('personnelRegleActiveDepuis')) {
    /**
     * La règle « le personnel reconnu entre en profil employé » ne vaut qu'à
     * partir du 29/07/2026 12h30. Avant cette date, les comptes créés restaient
     * en beta comme prévu : on ne réécrit pas le passé, on borne explicitement
     * le moment où le comportement change.
     */
    function personnelRegleActiveDepuis()
    {
        return '2026-07-29 12:30:00';
    }

    /** La règle s'applique-t-elle maintenant ? */
    function personnelRegleActive()
    {
        return time() >= strtotime(personnelRegleActiveDepuis());
    }
}
