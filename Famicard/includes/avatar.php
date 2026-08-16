<?php
// ============================================================
// FAMICARD — L'AVATAR 3D DU COLLABORATEUR.
//
// ── CE QUE C'EST, ET CE QUE CE N'EST PAS ────────────────────────────────────
// L'avatar N'EST PAS une photo d'identité et ne la remplace jamais. Décision de
// Jimmy, et elle est structurante : la photo reste la pièce qui identifie
// vraiment quelqu'un (badge, RH, fiche officielle), l'avatar est l'identité
// « vivante » — l'accueil, demain FamiFormation, les classements, les modules.
// Les deux cohabitent, aucun écran existant ne perd son visage réel.
//
// ── AUCUN FICHIER 3D À HÉBERGER, ET C'EST VOULU ─────────────────────────────
// Le personnage est CONSTRUIT EN CODE (assets/avatar3d.js) à partir de formes
// simples. Pas de .glb, pas de .fbx, pas d'artiste 3D, rien à déposer sur le
// volume, rien qui puisse manquer au déploiement. Le prix à payer est un style
// volontairement « jouet » — c'est le seul style qu'on peut tenir sans budget
// d'assets, et il vieillit bien mieux qu'un modèle réaliste raté.
//
// ── CE QUI EST STOCKÉ : DES SLUGS, PAS DES PIXELS ───────────────────────────
// La base ne garde qu'une PETITE description : « coupe = mi_long, couleur =
// chatain, haut = polo… ». Une ligne de quelques centaines d'octets.
//   • on peut retoucher la palette ou améliorer une coupe SANS migration :
//     l'avatar de tout le monde s'améliore au prochain affichage ;
//   • FamiFormation lira la MÊME ligne et affichera LE MÊME personnage — c'est
//     précisément pour ça que ce fichier vit dans Famicard et pas ailleurs
//     (Famicard est le centre des données de la personne).
//
// Un PNG est enregistré EN PLUS, comme une vignette : les listes, le badge ou
// un e-mail n'ont pas à charger une scène 3D pour montrer une tête de 40 px.
// Le PNG est un DÉRIVÉ, jamais la source : s'il disparaît, on le regénère.
//
// ── LE CATALOGUE EST ICI, ET NULLE PART AILLEURS ────────────────────────────
// Une seule liste d'options, en PHP, envoyée telle quelle au JavaScript. C'est
// ce qui garantit que ce que l'atelier PROPOSE est exactement ce que le serveur
// ACCEPTE. Deux listes, et un jour l'écran offre une coupe que la base refuse.
// ============================================================

if (!function_exists('famicardAvatarCatalogue')) {
    /**
     * TOUT ce qu'on peut choisir. Structure : des onglets, contenant des champs.
     *
     * Chaque champ porte son type :
     *   • 'liste'   → des formes (une coupe, un vêtement) ;
     *   • 'couleur' → une pastille de couleur, les valeurs sont des codes hex.
     *
     * ⚠️ LES CLÉS SONT DES ENGAGEMENTS. Une clé (« mi_long ») est écrite en base
     * pour chaque personne : la renommer efface le choix de tout le monde. Les
     * LIBELLÉS et les CODES COULEUR, eux, se retouchent librement — ils ne sont
     * jamais stockés.
     */
    function famicardAvatarCatalogue()
    {
        static $catalogue = null;
        if ($catalogue !== null) {
            return $catalogue;
        }

        // ── LA PALETTE DES POILS, ÉCRITE UNE FOIS ───────────────────────────
        // Cheveux, sourcils et barbe la partagent. Trois copies auraient
        // divergé au premier ajout de couleur, et on se serait retrouvé avec un
        // « roux » disponible pour les cheveux mais pas pour la barbe.
        $poils = [
            'noir'    => ['libelle' => 'Noir',    'hex' => '#1E1A18'],
            'brun'    => ['libelle' => 'Brun',    'hex' => '#3B2418'],
            'chatain' => ['libelle' => 'Châtain', 'hex' => '#6B4429'],
            'miel'    => ['libelle' => 'Miel',    'hex' => '#9A6B32'],
            'blond'   => ['libelle' => 'Blond',   'hex' => '#D8AE5C'],
            'platine' => ['libelle' => 'Platine', 'hex' => '#E9DCC0'],
            'roux'    => ['libelle' => 'Roux',    'hex' => '#A64B22'],
            'gris'    => ['libelle' => 'Gris',    'hex' => '#8E8C89'],
            'blanc'   => ['libelle' => 'Blanc',   'hex' => '#EDEDEA'],
            'bleu'    => ['libelle' => 'Bleu',    'hex' => '#2F6DB5'],
            'rose'    => ['libelle' => 'Rose',    'hex' => '#D2508B'],
            'vert'    => ['libelle' => 'Vert',    'hex' => '#3E9A63'],
        ];

        // ⚠️ LE CAS « auto » — sourcils et barbe suivent les cheveux par défaut.
        // C'est ce que veut presque tout le monde, et c'est surtout ce qui évite
        // qu'un changement de couleur de cheveux laisse une barbe orpheline de
        // l'ancienne teinte. `hex => 'auto'` est un SIGNAL, pas une couleur :
        // le moteur 3D le reconnaît et va chercher la couleur des cheveux (voir
        // fabriqueMatieres dans assets/avatar3d.js), et l'atelier lui dessine
        // une pastille à part.
        $poilsAuto = ['auto' => ['libelle' => 'Comme les cheveux', 'hex' => 'auto']] + $poils;

        $catalogue = [

            // ── SILHOUETTE ──────────────────────────────────────────────────
            'corps' => [
                'libelle' => 'Silhouette',
                'icone'   => '🧍',
                // ⚠️ L'ORDRE DES CHAMPS EST L'ORDRE DE L'ÉCRAN, et il raconte
                // la façon dont on fabrique quelqu'un : on décide D'ABORD de la
                // silhouette (elle change tout le reste), et la pose EN DERNIER,
                // parce qu'on met en scène un personnage qui existe déjà.
                'champs'  => [
                    // ── LA MORPHOLOGIE, ET PAS « LE SEXE » ──────────────────
                    // EN PREMIER, parce qu'elle commande tout le reste : elle
                    // change les épaules, les hanches et la façon dont chaque
                    // vêtement tombe. La choisir après avoir composé une tenue,
                    // c'est refaire la tenue.
                    //
                    // C'est une FORME DE FIGURINE, pas une donnée d'état civil.
                    // La nuance n'est pas de la coquetterie : Famicard est
                    // soumis au RGPD (voir le README), et le sexe y est une
                    // donnée qu'on n'a aucune raison de collecter pour dessiner
                    // un bonhomme. On demande donc la silhouette qu'on veut
                    // avoir, ce qui est un choix d'apparence — comme la coupe
                    // de cheveux — et non une déclaration sur soi.
                    //
                    // « Neutre » est le défaut et n'est pas un pis-aller : c'est
                    // la silhouette la plus proche du style jouet, et personne
                    // n'est obligé de se ranger dans une case pour commencer.
                    'silhouette' => [
                        'libelle' => 'Morphologie',
                        'type'    => 'liste',
                        'defaut'  => 'neutre',
                        'valeurs' => [
                            'neutre'    => ['libelle' => 'Neutre'],
                            'feminine'  => ['libelle' => 'Féminine'],
                            'masculine' => ['libelle' => 'Masculine'],
                        ],
                    ],
                    'peau' => [
                        'libelle' => 'Teint',
                        'type'    => 'couleur',
                        'defaut'  => 'clair',
                        // Une vraie amplitude de carnations : une palette qui
                        // s'arrête au beige oblige une partie de l'équipe à
                        // choisir un teint qui n'est pas le sien.
                        'valeurs' => [
                            'porcelaine' => ['libelle' => 'Porcelaine', 'hex' => '#F8E0CE'],
                            'clair'      => ['libelle' => 'Clair',      'hex' => '#F1CBA7'],
                            'dore'       => ['libelle' => 'Doré',       'hex' => '#E3B183'],
                            'hale'       => ['libelle' => 'Hâlé',       'hex' => '#C98C5B'],
                            'olive'      => ['libelle' => 'Olive',      'hex' => '#A9713F'],
                            'ambre'      => ['libelle' => 'Ambré',      'hex' => '#8A5430'],
                            'brun'       => ['libelle' => 'Brun',       'hex' => '#66391F'],
                            'ebene'      => ['libelle' => 'Ébène',      'hex' => '#432414'],
                        ],
                    ],
                    'carrure' => [
                        'libelle' => 'Carrure',
                        'type'    => 'liste',
                        'defaut'  => 'standard',
                        'valeurs' => [
                            'fine'     => ['libelle' => 'Fine'],
                            'standard' => ['libelle' => 'Standard'],
                            'large'    => ['libelle' => 'Large'],
                        ],
                    ],
                    'taille' => [
                        'libelle' => 'Taille',
                        'type'    => 'liste',
                        'defaut'  => 'moyenne',
                        'valeurs' => [
                            'petite'  => ['libelle' => 'Petite'],
                            'moyenne' => ['libelle' => 'Moyenne'],
                            'grande'  => ['libelle' => 'Grande'],
                        ],
                    ],
                    // ── LA POSE, EN DERNIER ─────────────────────────────────
                    // On met en scène un personnage qui existe déjà : la pose
                    // est le geste final, pas le point de départ.
                    //
                    // Elle FAIT PARTIE DE L'AVATAR, et pas des réglages d'écran.
                    // C'est ce qui fait qu'elle se retrouve toute seule sur la
                    // vignette de la fiche : la vignette est une photo du
                    // personnage tel qu'il est enregistré. Une pose choisie
                    // ailleurs aurait donné une fiche où tout le monde est au
                    // garde-à-vous.
                    'pose' => [
                        'libelle' => 'Pose',
                        'type'    => 'liste',
                        'defaut'  => 'neutre',
                        'aide'    => "C'est cette pose qu'on verra sur ta fiche.",
                        'valeurs' => [
                            'neutre'       => ['libelle' => 'Debout'],
                            'salut'        => ['libelle' => 'Coucou'],
                            'hanches'      => ['libelle' => 'Mains sur les hanches'],
                            'bras_croises' => ['libelle' => 'Bras croisés'],
                            'victoire'     => ['libelle' => 'Bras levés'],
                            'presente'     => ['libelle' => 'Bras tendu'],
                        ],
                    ],
                ],
            ],

            // ── CHEVEUX ─────────────────────────────────────────────────────
            'cheveux' => [
                'libelle' => 'Cheveux',
                'icone'   => '💇',
                'champs'  => [
                    'coupe' => [
                        'libelle' => 'Coupe',
                        'type'    => 'liste',
                        'defaut'  => 'courte',
                        'valeurs' => [
                            'chauve'  => ['libelle' => 'Rasé'],
                            'courte'  => ['libelle' => 'Courte'],
                            'brosse'  => ['libelle' => 'En brosse'],
                            'degrade' => ['libelle' => 'Dégradé'],
                            'coiffe'  => ['libelle' => 'Coiffée sur le côté'],
                            'carre'   => ['libelle' => 'Carré'],
                            'mi_long' => ['libelle' => 'Mi-long'],
                            'long'    => ['libelle' => 'Long'],
                            'ondule'  => ['libelle' => 'Longue ondulée'],
                            'queue'   => ['libelle' => 'Queue de cheval'],
                            'demi_queue' => ['libelle' => 'Demi-queue'],
                            'chignon' => ['libelle' => 'Chignon'],
                            'tresse'  => ['libelle' => 'Tresse'],
                            'tresses' => ['libelle' => 'Couettes'],
                            'boucle'  => ['libelle' => 'Bouclée'],
                            'afro'    => ['libelle' => 'Afro'],
                            'crete'   => ['libelle' => 'Crête'],
                        ],
                    ],
                    'couleur_cheveux' => [
                        'libelle' => 'Couleur',
                        'type'    => 'couleur',
                        'defaut'  => 'chatain',
                        'valeurs' => $poils,
                    ],
                ],
            ],

            // ── VISAGE ──────────────────────────────────────────────────────
            'visage' => [
                'libelle' => 'Visage',
                'icone'   => '🙂',
                'champs'  => [
                    'yeux' => [
                        'libelle' => 'Yeux',
                        'type'    => 'couleur',
                        'defaut'  => 'brun',
                        'valeurs' => [
                            'brun'    => ['libelle' => 'Brun',    'hex' => '#4A2C17'],
                            'noisette'=> ['libelle' => 'Noisette','hex' => '#8A5A2B'],
                            'vert'    => ['libelle' => 'Vert',    'hex' => '#3F7A4A'],
                            'bleu'    => ['libelle' => 'Bleu',    'hex' => '#3C7BB5'],
                            'gris'    => ['libelle' => 'Gris',    'hex' => '#6E7A82'],
                            'noir'    => ['libelle' => 'Noir',    'hex' => '#20160F'],
                        ],
                    ],
                    'expression' => [
                        'libelle' => 'Expression',
                        'type'    => 'liste',
                        'defaut'  => 'sourire',
                        'valeurs' => [
                            'neutre'   => ['libelle' => 'Neutre'],
                            'sourire'  => ['libelle' => 'Sourire'],
                            'joyeux'   => ['libelle' => 'Joyeux'],
                            'determine'=> ['libelle' => 'Déterminé'],
                        ],
                    ],
                    // Les cils sont un CHAMP À PART, et pas une conséquence
                    // automatique de la morphologie féminine. Les accrocher à
                    // la silhouette aurait gravé un stéréotype dans le modèle ;
                    // séparés, ils sont à la disposition de tout le monde.
                    'cils' => [
                        'libelle' => 'Cils',
                        'type'    => 'liste',
                        'defaut'  => 'aucun',
                        'valeurs' => [
                            'aucun'    => ['libelle' => 'Aucun'],
                            'discrets' => ['libelle' => 'Discrets'],
                            'marques'  => ['libelle' => 'Marqués'],
                        ],
                    ],
                    'sourcils' => [
                        'libelle' => 'Sourcils',
                        'type'    => 'liste',
                        'defaut'  => 'standard',
                        'valeurs' => [
                            'fins'     => ['libelle' => 'Fins'],
                            'standard' => ['libelle' => 'Standard'],
                            'epais'    => ['libelle' => 'Épais'],
                        ],
                    ],
                    'couleur_sourcils' => [
                        'libelle' => 'Couleur des sourcils',
                        'type'    => 'couleur',
                        'defaut'  => 'auto',
                        'valeurs' => $poilsAuto,
                    ],
                    'barbe' => [
                        'libelle' => 'Barbe',
                        'type'    => 'liste',
                        'defaut'  => 'aucune',
                        'valeurs' => [
                            'aucune'    => ['libelle' => 'Aucune'],
                            'moustache' => ['libelle' => 'Moustache'],
                            'bouc'      => ['libelle' => 'Bouc'],
                            'courte'    => ['libelle' => 'Barbe de 3 jours'],
                            'pleine'    => ['libelle' => 'Barbe pleine'],
                        ],
                    ],
                    'couleur_barbe' => [
                        'libelle' => 'Couleur de la barbe',
                        'type'    => 'couleur',
                        'defaut'  => 'auto',
                        'valeurs' => $poilsAuto,
                    ],
                    // Le maquillage est ouvert à tout le monde, comme les cils :
                    // c'est un choix d'apparence, il n'appartient à aucune
                    // morphologie.
                    'maquillage' => [
                        'libelle' => 'Maquillage',
                        'type'    => 'liste',
                        'defaut'  => 'aucun',
                        'valeurs' => [
                            'aucun'   => ['libelle' => 'Aucun'],
                            'discret' => ['libelle' => 'Discret'],
                            'levres'  => ['libelle' => 'Lèvres'],
                            'marque'  => ['libelle' => 'Marqué'],
                        ],
                    ],
                    'couleur_levres' => [
                        'libelle' => 'Couleur des lèvres',
                        'type'    => 'couleur',
                        'defaut'  => 'naturel',
                        'valeurs' => [
                            'naturel'  => ['libelle' => 'Naturel',  'hex' => '#B4655F'],
                            'rose'     => ['libelle' => 'Rosé',     'hex' => '#D4737F'],
                            'corail'   => ['libelle' => 'Corail',   'hex' => '#E0705C'],
                            'rouge'    => ['libelle' => 'Rouge',    'hex' => '#C1272D'],
                            'framboise'=> ['libelle' => 'Framboise','hex' => '#9E2B4E'],
                            'prune'    => ['libelle' => 'Prune',    'hex' => '#6E2B45'],
                        ],
                    ],
                    'lunettes' => [
                        'libelle' => 'Lunettes',
                        'type'    => 'liste',
                        'defaut'  => 'aucune',
                        'valeurs' => [
                            'aucune'  => ['libelle' => 'Aucune'],
                            'fines'   => ['libelle' => 'Fines'],
                            'rondes'  => ['libelle' => 'Rondes'],
                            'carrees' => ['libelle' => 'Carrées'],
                            'soleil'  => ['libelle' => 'Solaires'],
                        ],
                    ],
                ],
            ],

            // ── TENUE ───────────────────────────────────────────────────────
            'tenue' => [
                'libelle' => 'Tenue',
                'icone'   => '👕',
                'champs'  => [
                    // ⚠️ LA TENUE DE TRAVAIL EST LE DÉFAUT, ET LES DEUX PREMIÈRES.
                    // C'est ce que porte réellement l'équipe (photos de Jimmy) :
                    // le gilet vert bordé de vert anis par-dessus un t-shirt, ou
                    // le t-shirt maison seul. Quelqu'un qui ouvre l'atelier se
                    // reconnaît donc immédiatement, avant même d'avoir cliqué.
                    //
                    // Ces deux-là portent LEURS couleurs, celles de la maison :
                    // un gilet Famiflora rose ne serait plus un gilet Famiflora.
                    // Le choix de couleur du haut habille alors le t-shirt
                    // porté DESSOUS — comme en vrai.
                    'haut' => [
                        'libelle' => 'Haut',
                        'type'    => 'liste',
                        'defaut'  => 'gilet_fami',
                        'valeurs' => [
                            'gilet_fami'  => ['libelle' => 'Gilet Famiflora'],
                            'tshirt_fami' => ['libelle' => 'T-shirt Famiflora'],
                            'tshirt'  => ['libelle' => 'T-shirt'],
                            'polo'    => ['libelle' => 'Polo'],
                            'chemise' => ['libelle' => 'Chemise'],
                            'chemisier' => ['libelle' => 'Chemisier'],
                            'pull'    => ['libelle' => 'Pull'],
                            'sweat'   => ['libelle' => 'Sweat à capuche'],
                            'veste'   => ['libelle' => 'Veste'],
                            'debardeur' => ['libelle' => 'Débardeur'],
                        ],
                    ],
                    'couleur_haut' => [
                        'libelle' => 'Couleur du haut',
                        'type'    => 'couleur',
                        'defaut'  => 'noir',
                        'aide'    => 'Sous le gilet Famiflora, cette couleur habille le t-shirt porté dessous. Le gilet et le t-shirt Famiflora, eux, gardent les couleurs de la maison.',
                        'valeurs' => [
                            'vert_fami' => ['libelle' => 'Vert Famiflora', 'hex' => '#2D5A37'],
                            'vert_clair'=> ['libelle' => 'Vert clair',     'hex' => '#4A8B5C'],
                            'blanc'     => ['libelle' => 'Blanc',          'hex' => '#F2F2EF'],
                            'noir'      => ['libelle' => 'Noir',           'hex' => '#232323'],
                            'gris'      => ['libelle' => 'Gris',           'hex' => '#8A8F94'],
                            'bleu'      => ['libelle' => 'Bleu',           'hex' => '#2E5F94'],
                            'bleu_ciel' => ['libelle' => 'Bleu ciel',      'hex' => '#79B4DD'],
                            'rouge'     => ['libelle' => 'Rouge',          'hex' => '#B33A32'],
                            'orange'    => ['libelle' => 'Orange',         'hex' => '#E9A93C'],
                            'jaune'     => ['libelle' => 'Jaune',          'hex' => '#EBD05A'],
                            'violet'    => ['libelle' => 'Violet',         'hex' => '#6A4C93'],
                            'rose'      => ['libelle' => 'Rose',           'hex' => '#D2508B'],
                        ],
                    ],
                    'bas' => [
                        'libelle' => 'Bas',
                        'type'    => 'liste',
                        'defaut'  => 'pantalon',
                        'valeurs' => [
                            'pantalon' => ['libelle' => 'Pantalon'],
                            'jean'     => ['libelle' => 'Jean'],
                            'bermuda'  => ['libelle' => 'Bermuda'],
                            'short'    => ['libelle' => 'Short'],
                            'jupe'     => ['libelle' => 'Jupe'],
                            'jupe_longue' => ['libelle' => 'Jupe longue'],
                            'leggings' => ['libelle' => 'Leggings'],
                        ],
                    ],
                    'couleur_bas' => [
                        'libelle' => 'Couleur du bas',
                        'type'    => 'couleur',
                        'defaut'  => 'denim',
                        'valeurs' => [
                            'denim'    => ['libelle' => 'Denim',      'hex' => '#3F5B7C'],
                            'noir'     => ['libelle' => 'Noir',       'hex' => '#222629'],
                            'gris'     => ['libelle' => 'Gris',       'hex' => '#6E747A'],
                            'beige'    => ['libelle' => 'Beige',      'hex' => '#C4AF8C'],
                            'kaki'     => ['libelle' => 'Kaki',       'hex' => '#6B7248'],
                            'vert_fami'=> ['libelle' => 'Vert Famiflora', 'hex' => '#2D5A37'],
                            'bordeaux' => ['libelle' => 'Bordeaux',   'hex' => '#6E2B33'],
                            'blanc'    => ['libelle' => 'Blanc',      'hex' => '#EDEDE8'],
                        ],
                    ],
                    'chaussures' => [
                        'libelle' => 'Chaussures',
                        'type'    => 'liste',
                        'defaut'  => 'baskets',
                        'valeurs' => [
                            'baskets'  => ['libelle' => 'Baskets'],
                            'securite' => ['libelle' => 'Chaussures de sécurité'],
                            'bottes'   => ['libelle' => 'Bottes'],
                            'ville'    => ['libelle' => 'Chaussures de ville'],
                            'ballerines' => ['libelle' => 'Ballerines'],
                            'sabots'   => ['libelle' => 'Sabots'],
                        ],
                    ],
                    'couleur_chaussures' => [
                        'libelle' => 'Couleur des chaussures',
                        'type'    => 'couleur',
                        'defaut'  => 'noir',
                        'valeurs' => [
                            'noir'   => ['libelle' => 'Noir',   'hex' => '#1F2124'],
                            'blanc'  => ['libelle' => 'Blanc',  'hex' => '#E8E8E4'],
                            'marron' => ['libelle' => 'Marron', 'hex' => '#5A3A22'],
                            'gris'   => ['libelle' => 'Gris',   'hex' => '#787E84'],
                            'vert'   => ['libelle' => 'Vert',   'hex' => '#2D5A37'],
                            'rouge'  => ['libelle' => 'Rouge',  'hex' => '#A83B33'],
                        ],
                    ],
                ],
            ],

            // ── ACCESSOIRES ─────────────────────────────────────────────────
            'accessoires' => [
                'libelle' => 'Accessoires',
                'icone'   => '🎒',
                'champs'  => [
                    'couvre_chef' => [
                        'libelle' => 'Sur la tête',
                        'type'    => 'liste',
                        'defaut'  => 'aucun',
                        'valeurs' => [
                            'aucun'     => ['libelle' => 'Rien'],
                            'casquette' => ['libelle' => 'Casquette'],
                            'bonnet'    => ['libelle' => 'Bonnet'],
                            'bandana'   => ['libelle' => 'Bandana'],
                            'chapeau'   => ['libelle' => 'Chapeau de paille'],
                        ],
                    ],
                    'couleur_couvre_chef' => [
                        'libelle' => 'Sa couleur',
                        'type'    => 'couleur',
                        'defaut'  => 'vert_fami',
                        'valeurs' => [
                            'vert_fami' => ['libelle' => 'Vert Famiflora', 'hex' => '#2D5A37'],
                            'noir'      => ['libelle' => 'Noir',           'hex' => '#232323'],
                            'blanc'     => ['libelle' => 'Blanc',          'hex' => '#F0F0EC'],
                            'gris'      => ['libelle' => 'Gris',           'hex' => '#7E848A'],
                            'bleu'      => ['libelle' => 'Bleu',           'hex' => '#2E5F94'],
                            'rouge'     => ['libelle' => 'Rouge',          'hex' => '#B33A32'],
                            'orange'    => ['libelle' => 'Orange',         'hex' => '#E9A93C'],
                            'paille'    => ['libelle' => 'Paille',         'hex' => '#D9BE7E'],
                        ],
                    ],
                    'accessoire' => [
                        'libelle' => 'Équipement',
                        'type'    => 'liste',
                        'defaut'  => 'aucun',
                        // Ce qu'on porte VRAIMENT dans une jardinerie. Un
                        // catalogue d'accessoires de jeu vidéo n'aurait rien dit
                        // du métier.
                        'valeurs' => [
                            'aucun'      => ['libelle' => 'Rien'],
                            'tablier'    => ['libelle' => 'Tablier'],
                            'gilet_fluo' => ['libelle' => 'Gilet fluo'],
                            'gants'      => ['libelle' => 'Gants de jardinage'],
                            'badge'      => ['libelle' => 'Badge'],
                            'sacoche'    => ['libelle' => 'Sacoche'],
                            'sac_main'   => ['libelle' => 'Sac à main'],
                        ],
                    ],
                    // Les bijoux sont un champ SÉPARÉ de l'équipement : on peut
                    // porter des boucles d'oreilles ET un tablier. Les mettre
                    // dans la même liste aurait forcé à choisir entre les deux.
                    'bijoux' => [
                        'libelle' => 'Bijoux',
                        'type'    => 'liste',
                        'defaut'  => 'aucun',
                        'valeurs' => [
                            'aucun'           => ['libelle' => 'Aucun'],
                            'boucles'         => ['libelle' => "Boucles d'oreilles"],
                            'anneaux'         => ['libelle' => 'Créoles'],
                            'collier'         => ['libelle' => 'Collier'],
                            'boucles_collier' => ['libelle' => 'Boucles + collier'],
                        ],
                    ],
                    'couleur_bijoux' => [
                        'libelle' => 'Métal',
                        'type'    => 'couleur',
                        'defaut'  => 'or',
                        'valeurs' => [
                            'or'     => ['libelle' => 'Or',      'hex' => '#D9B45B'],
                            'argent' => ['libelle' => 'Argent',  'hex' => '#C6CBD1'],
                            'rose'   => ['libelle' => 'Or rose', 'hex' => '#D79A86'],
                            'noir'   => ['libelle' => 'Noir',    'hex' => '#2B2B2E'],
                        ],
                    ],
                ],
            ],
        ];

        return $catalogue;
    }
}

if (!function_exists('famicardAvatarChamps')) {
    /**
     * Le catalogue à plat : clé du champ → sa définition. Sert à la
     * normalisation, qui se moque des onglets.
     */
    function famicardAvatarChamps()
    {
        static $plat = null;
        if ($plat !== null) {
            return $plat;
        }
        $plat = [];
        foreach (famicardAvatarCatalogue() as $onglet) {
            foreach ($onglet['champs'] as $cle => $champ) {
                $plat[$cle] = $champ;
            }
        }
        return $plat;
    }
}

if (!function_exists('famicardAvatarDefaut')) {
    /** L'avatar de quelqu'un qui n'en a jamais fait : le catalogue par défaut. */
    function famicardAvatarDefaut()
    {
        $config = [];
        foreach (famicardAvatarChamps() as $cle => $champ) {
            $config[$cle] = (string) $champ['defaut'];
        }
        return $config;
    }
}

if (!function_exists('famicardAvatarNormalise')) {
    /**
     * Ne garde QUE ce que le catalogue reconnaît, et complète le reste.
     *
     * ⚠️ C'EST LA SEULE PORTE D'ENTRÉE. Ce qui arrive du navigateur est du texte
     * envoyé par n'importe qui : sans ce filtre, on stockerait des clés
     * inventées, et un jour l'écran d'à côté afficherait « coupe = <script> ».
     * Une valeur inconnue ne fait pas échouer l'enregistrement — elle retombe
     * sur le défaut, parce qu'un avatar refusé en bloc pour une coupe supprimée
     * du catalogue serait une régression pour la personne.
     */
    function famicardAvatarNormalise($config)
    {
        if (is_string($config)) {
            $decode = json_decode($config, true);
            $config = is_array($decode) ? $decode : [];
        }
        if (!is_array($config)) {
            $config = [];
        }

        $propre = [];
        foreach (famicardAvatarChamps() as $cle => $champ) {
            $valeur = isset($config[$cle]) && is_scalar($config[$cle]) ? (string) $config[$cle] : '';
            $propre[$cle] = isset($champ['valeurs'][$valeur]) ? $valeur : (string) $champ['defaut'];
        }
        return $propre;
    }
}

if (!function_exists('famicardAvatarCouleur')) {
    /**
     * Le code couleur derrière un choix. Utile côté PHP (vignette de secours,
     * futurs exports) ; le JavaScript, lui, reçoit tout le catalogue.
     */
    function famicardAvatarCouleur(array $config, $champ, $repli = '#CCCCCC')
    {
        $champs = famicardAvatarChamps();
        $valeur = (string) ($config[$champ] ?? '');
        return (string) ($champs[$champ]['valeurs'][$valeur]['hex'] ?? $repli);
    }
}

if (!function_exists('famicardAvatarLook')) {
    /**
     * La configuration, couleurs RÉSOLUES — ce que le JavaScript sait dessiner.
     *
     * C'est la frontière entre les deux mondes : la base parle en mots
     * (« châtain »), le moteur 3D parle en codes (« #6B4429»). La traduction se
     * fait ICI, et donc une seule fois. avatar3d.js n'a aucune idée de ce
     * qu'est un châtain, et c'est ce qui permet de retoucher toute la palette
     * sans ouvrir une ligne de JavaScript.
     */
    function famicardAvatarLook(array $config)
    {
        $config = famicardAvatarNormalise($config);
        $look = [];
        foreach (famicardAvatarChamps() as $cle => $champ) {
            $look[$cle] = ($champ['type'] === 'couleur')
                ? (string) $champ['valeurs'][$config[$cle]]['hex']
                : $config[$cle];
        }
        return $look;
    }
}

if (!function_exists('famicardAssureAvatars')) {
    /**
     * Crée la table si elle manque.
     *
     * Appelée depuis l'atelier (donc par n'importe qui), volontairement : un
     * collaborateur ne doit pas attendre qu'un administrateur passe pour que sa
     * page fonctionne. C'est le même choix que pour les avis. Si les droits ne
     * suffisent pas, on renvoie false et l'appelant affiche un message — la
     * page ne blanchit pas.
     *
     * PAS DE CLÉ ÉTRANGÈRE vers `utilisateurs` : même raison qu'ailleurs dans
     * Famicard, les tables de ce dépôt ne posent pas de contrainte sur des
     * tables que d'autres outils nettoient eux-mêmes. Une ligne orpheline est
     * traitée à la lecture (jointure), pas par la base.
     */
    function famicardAssureAvatars(PDO $db)
    {
        static $fait = null;
        if ($fait !== null) {
            return $fait;
        }
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS famicard_avatars (
                    user_id INT NOT NULL,
                    config TEXT NOT NULL,
                    image VARCHAR(255) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $fait = true;
        } catch (Exception $e) {
            $fait = false;
        }
        return $fait;
    }
}

if (!function_exists('famicardAvatarDe')) {
    /**
     * L'avatar d'une personne.
     *
     * Renvoie TOUJOURS une configuration utilisable : si la personne n'a rien
     * créé, c'est le personnage par défaut, avec `existe` à false. Un écran
     * n'a donc jamais à gérer le cas « pas d'avatar » autrement qu'en décidant
     * s'il l'affiche ou non.
     */
    function famicardAvatarDe(PDO $db, $userId)
    {
        $userId = (int) $userId;
        $ligne = null;
        if ($userId > 0) {
            try {
                $st = $db->prepare("SELECT config, image, updated_at FROM famicard_avatars WHERE user_id = ? LIMIT 1");
                $st->execute([$userId]);
                $ligne = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Exception $e) {
                // Table absente : on retombe sur le personnage par défaut.
                $ligne = null;
            }
        }

        return [
            'existe'   => (bool) $ligne,
            'config'   => famicardAvatarNormalise($ligne['config'] ?? []),
            'image'    => (string) ($ligne['image'] ?? ''),
            'maj'      => (string) ($ligne['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('famicardAvatarsDe')) {
    /**
     * Les avatars de PLUSIEURS personnes, en une requête.
     *
     * Existe pour les listes (la base des collaborateurs, un classement) : une
     * requête par ligne affichée, c'est ce qui rend une page de 200 fiches
     * lente sans qu'on comprenne pourquoi.
     *
     * @return array user_id → ['config' => …, 'image' => …]  (uniquement ceux
     *               qui en ont un : l'appelant décide du repli)
     */
    function famicardAvatarsDe(PDO $db, array $userIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$ids) {
            return [];
        }
        $trous = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = $db->prepare("SELECT user_id, config, image FROM famicard_avatars WHERE user_id IN ($trous)");
            $st->execute($ids);
        } catch (Exception $e) {
            return [];
        }

        $par = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $par[(int) $l['user_id']] = [
                'config' => famicardAvatarNormalise($l['config']),
                'image'  => (string) ($l['image'] ?? ''),
            ];
        }
        return $par;
    }
}

if (!function_exists('famicardDossierAvatars')) {
    /**
     * Où vivent les vignettes PNG.
     *
     * MÊME BASE DE STOCKAGE QUE LES PHOTOS (le volume Railway), pour la même
     * raison : ce qui est écrit dans le conteneur disparaît au redéploiement.
     * Dossier distinct, en revanche — une vignette générée n'a rien à faire au
     * milieu des photos d'identité, ne serait-ce que pour pouvoir la vider sans
     * risque.
     *
     * @return array [base de stockage, dossier des vignettes]
     */
    function famicardDossierAvatars()
    {
        $base = defined('FAMI_STORAGE_BASE')
            ? rtrim(FAMI_STORAGE_BASE, '/')
            : (famicardRacineSite() . '/uploads');

        return [$base, $base . '/divers/avatars/'];
    }
}

if (!function_exists('famicardCheminAvatarImage')) {
    /** Chemin absolu d'une vignette déjà enregistrée (clé volume). */
    function famicardCheminAvatarImage($valeur)
    {
        $valeur = (string) $valeur;
        if ($valeur === '' || strpos($valeur, '..') !== false) {
            return '';
        }
        list($base, ) = famicardDossierAvatars();
        return $base . '/' . $valeur;
    }
}

if (!function_exists('famicardAvatarImageUrl')) {
    /**
     * L'adresse de la vignette de quelqu'un.
     *
     * ⚠️ ON NE PASSE PAS PAR media.php DU SITE, ET C'EST DÉLIBÉRÉ. media.php vit
     * sur www ; depuis famicard.famiformation.com, la session de www n'existe
     * pas (cookie host-only, voir config.php) et l'image reviendrait en 403 —
     * un avatar cassé une visite sur deux, selon l'adresse utilisée. Famicard
     * sert donc SA vignette, avec SA session, en relatif : juste sur les deux
     * adresses, sans rien calculer.
     *
     * L'horodatage force le navigateur à recharger après une modification :
     * sans lui, on retrouve son ancien personnage pendant des heures.
     */
    function famicardAvatarImageUrl($userId, $version = '')
    {
        $url = 'avatar_image.php?u=' . (int) $userId;
        if ($version !== '') {
            $url .= '&v=' . urlencode(substr(sha1((string) $version), 0, 10));
        }
        return $url;
    }
}

if (!function_exists('famicardEnregistreAvatar')) {
    /**
     * Enregistre la configuration, et la vignette qui va avec.
     *
     * La CONFIGURATION est la source ; la VIGNETTE est un dérivé, envoyée par
     * le navigateur (c'est lui qui a la scène 3D sous la main — la refabriquer
     * côté serveur demanderait un moteur 3D dans PHP pour un résultat moins
     * fidèle). Elle est donc traitée comme n'importe quel envoi : taille bornée,
     * contenu vérifié, et surtout RE-ENCODÉE par GD. Un PNG déposé tel quel peut
     * transporter autre chose que des pixels ; réécrit par GD, il ne contient
     * plus que l'image.
     *
     * Si la vignette est refusée, la configuration est QUAND MÊME enregistrée :
     * elle seule compte, la vignette se regénère au prochain passage.
     *
     * @param string $imageData une donnée « data:image/png;base64,… », ou ''
     * @return bool
     */
    function famicardEnregistreAvatar(PDO $db, $userId, $config, $imageData, &$erreur)
    {
        $erreur = '';
        $userId = (int) $userId;
        if ($userId <= 0) {
            $erreur = "Utilisateur inconnu.";
            return false;
        }
        if (!famicardAssureAvatars($db)) {
            $erreur = "L'avatar n'a pas pu être enregistré (table indisponible).";
            return false;
        }

        $config = famicardAvatarNormalise($config);
        $ancienne = (string) (famicardAvatarDe($db, $userId)['image'] ?? '');
        $cleImage = famicardEcritVignetteAvatar($userId, $imageData, $ancienne);

        try {
            // ON NE PERD PAS LA VIGNETTE PRÉCÉDENTE si celle-ci n'a pas pu être
            // écrite : mieux vaut une image d'un instant d'avant que plus
            // d'image du tout dans les listes.
            $st = $db->prepare(
                "INSERT INTO famicard_avatars (user_id, config, image)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE config = VALUES(config),
                                         image  = COALESCE(VALUES(image), image)"
            );
            $st->execute([
                $userId,
                json_encode($config, JSON_UNESCAPED_UNICODE),
                ($cleImage !== '' ? $cleImage : null),
            ]);
        } catch (Exception $e) {
            $erreur = "L'avatar n'a pas pu être enregistré.";
            return false;
        }

        return true;
    }
}

if (!function_exists('famicardEcritVignetteAvatar')) {
    /**
     * Écrit le PNG envoyé par le navigateur. Renvoie la clé volume, ou ''.
     *
     * Volontairement silencieuse : un échec ici ne doit jamais empêcher
     * d'enregistrer son avatar (voir famicardEnregistreAvatar).
     */
    function famicardEcritVignetteAvatar($userId, $imageData, $ancienne = '')
    {
        $imageData = (string) $imageData;
        if ($imageData === '') {
            return '';
        }
        if (!preg_match('#^data:image/png;base64,#', $imageData)) {
            return '';
        }
        // ~1,3 caractère de base64 par octet : 900 Ko de texte plafonnent le
        // PNG bien au-dessus de ce qu'une vignette 512 px peut peser, et bien
        // en dessous de ce qui remplirait la mémoire.
        if (strlen($imageData) > 900 * 1024) {
            return '';
        }

        $binaire = base64_decode(substr($imageData, strlen('data:image/png;base64,')), true);
        if ($binaire === false || $binaire === '') {
            return '';
        }
        $info = @getimagesizefromstring($binaire);
        if (!$info || ($info[2] ?? 0) !== IMAGETYPE_PNG || $info[0] > 1024 || $info[1] > 1024) {
            return '';
        }

        list(, $dossier) = famicardDossierAvatars();
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return '';
        }

        $nom = 'user_' . (int) $userId . '_' . time() . '.png';
        $destination = $dossier . $nom;

        // RÉ-ENCODAGE PAR GD : ce qui est écrit sur le volume est une image
        // fabriquée ici, pas un fichier reçu. La transparence est conservée —
        // sans ces deux lignes, le fond du personnage vire au noir.
        $ok = false;
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($binaire);
            if ($img) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $ok = @imagepng($img, $destination, 6);
                imagedestroy($img);
            }
        }
        if (!$ok) {
            // GD absent (il ne l'est pas dans notre image Docker, mais un
            // environnement local peut l'être) : on écrit l'original vérifié.
            $ok = (@file_put_contents($destination, $binaire) !== false);
        }
        if (!$ok) {
            return '';
        }

        // L'ancienne part APRÈS : même règle que pour la photo de profil.
        $cheminAncien = famicardCheminAvatarImage($ancienne);
        if ($cheminAncien !== '' && is_file($cheminAncien) && $cheminAncien !== $destination) {
            @unlink($cheminAncien);
        }

        return 'divers/avatars/' . $nom;
    }
}

if (!function_exists('famicardSupprimeAvatar')) {
    /**
     * Efface l'avatar de quelqu'un — la ligne ET la vignette.
     *
     * Existe parce qu'un avatar est une donnée qu'on a le droit de retirer :
     * on ne laisse pas un personnage traîner sur le volume après que son
     * propriétaire a demandé sa suppression (voir le chantier RGPD du README).
     */
    function famicardSupprimeAvatar(PDO $db, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }
        $chemin = famicardCheminAvatarImage(famicardAvatarDe($db, $userId)['image'] ?? '');
        try {
            $db->prepare("DELETE FROM famicard_avatars WHERE user_id = ?")->execute([$userId]);
        } catch (Exception $e) {
            return false;
        }
        if ($chemin !== '' && is_file($chemin)) {
            @unlink($chemin);
        }
        return true;
    }
}
