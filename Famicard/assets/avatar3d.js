// ============================================================
// FAMICARD — LE PERSONNAGE 3D.
//
// ── POURQUOI CE FICHIER EXISTE À PART ───────────────────────────────────────
// C'est LA brique réutilisable. Famicard l'utilise pour l'atelier (avatar.php)
// et pour afficher le personnage ; FamiFormation l'utilisera tel quel demain,
// en le chargeant depuis /famicard/assets/avatar3d.js. Un seul fichier qui sait
// dessiner un collaborateur : le jour où on améliore une coupe de cheveux,
// elle s'améliore partout, sans copier une ligne.
//
//   import { creerAvatar, construireLook } from '/famicard/assets/avatar3d.js';
//   const vue = creerAvatar(monDiv, look);
//
// ── AUCUN MODÈLE 3D, QUE DES FORMES ─────────────────────────────────────────
// Tout est bâti à partir de sphères, capsules, boîtes et tores. Pas de fichier
// à héberger, pas de licence d'asset, rien à télécharger : le personnage pèse
// le poids de ce fichier. C'est ce qui rend l'avatar déployable partout sans
// rien préparer — y compris dans un e-mail ou une page de FamiJob.
//
// ── CE QU'IL REÇOIT : UN « LOOK », PAS UNE CONFIG ───────────────────────────
// Les couleurs arrivent DÉJÀ RÉSOLUES en codes hexadécimaux. La palette vit en
// PHP (includes/avatar.php), et elle y vit SEULE : ce fichier n'a aucune idée
// de ce qu'est « châtain ». On peut donc retoucher toute la palette sans
// toucher au JavaScript, et l'écran ne peut pas proposer une couleur que le
// serveur refuserait.
// ============================================================

// ⚠️ LA VERSION EST FIGÉE, VOLONTAIREMENT. Un « @latest » ferait dépendre
// l'apparence de tous les collaborateurs d'une mise à jour qu'on ne contrôle
// pas : un jour la scène ne s'ouvre plus, et rien n'a changé chez nous. Même
// principe que pdfjs dans FamiFormation. Pour passer à un three.js hébergé par
// nous, il n'y a que cette ligne à changer.
import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.module.js';

// ─────────────────────────────────────────────────────────────────────────────
// LE LOOK — passage de la configuration (des mots) aux couleurs (des codes).
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Traduit une configuration en « look » prêt à dessiner.
 *
 * @param {Object} config  ce qui est stocké en base : { coupe:'mi_long', … }
 * @param {Object} champs  le catalogue à plat, tel que PHP l'envoie
 */
export function construireLook(config, champs) {
    const look = {};
    Object.keys(champs || {}).forEach(function (cle) {
        const champ = champs[cle];
        const choisi = (config && config[cle] !== undefined) ? String(config[cle]) : String(champ.defaut);
        const valeur = champ.valeurs[choisi] ? choisi : String(champ.defaut);
        // Une couleur devient son code ; une forme reste son nom.
        look[cle] = (champ.type === 'couleur')
            ? String(champ.valeurs[valeur].hex || '#CCCCCC')
            : valeur;
    });
    return look;
}

// ─────────────────────────────────────────────────────────────────────────────
// PETITE BOÎTE À OUTILS — les formes de base.
// ─────────────────────────────────────────────────────────────────────────────

/** Une matière mate, celle du style « jouet ». */
function matiere(couleur, options) {
    const o = options || {};
    return new THREE.MeshStandardMaterial({
        color: new THREE.Color(couleur),
        roughness: o.roughness !== undefined ? o.roughness : 0.85,
        metalness: o.metalness !== undefined ? o.metalness : 0.02,
        transparent: !!o.transparent,
        opacity: o.opacity !== undefined ? o.opacity : 1,
        side: o.side || THREE.FrontSide,
        flatShading: !!o.flatShading
    });
}

function boite(l, h, p, mat) {
    return new THREE.Mesh(new THREE.BoxGeometry(l, h, p), mat);
}

function capsule(r, longueur, mat) {
    // longueur = partie droite ; hauteur totale = longueur + 2r.
    return new THREE.Mesh(new THREE.CapsuleGeometry(r, Math.max(0.001, longueur), 6, 18), mat);
}

function balle(r, mat, seg) {
    return new THREE.Mesh(new THREE.SphereGeometry(r, seg || 26, seg ? Math.max(8, seg / 2) : 18), mat);
}

/**
 * Un morceau de sphère — LA forme qui fait les cheveux et les barbes.
 *
 * On habille la tête d'une pelure sphérique découpée : en latitude (thetaStart
 * / thetaLength → jusqu'où ça descend) et en longitude (phiStart / phiLength →
 * quelle portion du tour). Dessinée des DEUX CÔTÉS, sinon la calotte disparaît
 * dès qu'on regarde le personnage de dos.
 */
function pelure(r, phiStart, phiLength, thetaStart, thetaLength, mat) {
    const g = new THREE.SphereGeometry(r, 30, 20, phiStart, phiLength, thetaStart, thetaLength);
    return new THREE.Mesh(g, mat);
}

function place(objet, x, y, z) {
    objet.position.set(x || 0, y || 0, z || 0);
    return objet;
}

// ─────────────────────────────────────────────────────────────────────────────
// LES PROPORTIONS.
//
// Un seul endroit qui décide des mesures. Tout le reste s'y réfère : changer la
// taille d'une tête ne demande pas de repositionner trente pièces à la main.
// ─────────────────────────────────────────────────────────────────────────────
function mesures(look) {
    const t = { petite: 0.93, moyenne: 1, grande: 1.07 }[look.taille] || 1;      // hauteur
    const c = { fine: 0.87, standard: 1, large: 1.17 }[look.carrure] || 1;       // largeur

    // ── LA MORPHOLOGIE ──────────────────────────────────────────────────────
    // Trois nombres suffisent à changer une silhouette : la largeur d'épaules,
    // la largeur de hanches, et la présence d'une poitrine. Tout le reste du
    // personnage s'y adapte tout seul, parce que chaque pièce est placée par
    // rapport à ces mesures et non par des coordonnées écrites à la main.
    const forme = {
        neutre:    { epaules: 1.00, hanches: 1.00, buste: 0 },
        feminine:  { epaules: 0.92, hanches: 1.14, buste: 1 },
        masculine: { epaules: 1.08, hanches: 0.94, buste: 0 }
    }[look.silhouette] || { epaules: 1, hanches: 1, buste: 0 };

    const m = {
        t: t,
        c: c,
        forme: forme,
        rTete: 0.225,
        hPied: 0.085,
        hJambe: 0.70 * t,
        hTorse: 0.58 * t,
        lTorse: 0.50 * c * forme.epaules,   // largeur d'épaules
        pTorse: 0.26 * c,                   // profondeur
        rJambe: 0.088 * Math.sqrt(c),
        rBras: 0.066 * Math.sqrt(c),
        hBras: 0.50 * t
    };

    // Les hanches ne suivent PAS les épaules : c'est justement leur écart qui
    // dessine la silhouette. On repart donc de la largeur de base, avant le
    // facteur d'épaules.
    m.lHanche = 0.50 * c * forme.hanches;

    m.yHanche = m.hPied + m.hJambe;
    m.yEpaule = m.yHanche + m.hTorse;
    m.yTete   = m.yEpaule + 0.055 + m.rTete;
    m.ecartJambes = m.lHanche * 0.24;
    m.hauteurTotale = m.yTete + m.rTete * 1.35;   // marge pour cheveux/chapeau

    return m;
}

// ─────────────────────────────────────────────────────────────────────────────
// LA TÊTE ET SON VISAGE.
// ─────────────────────────────────────────────────────────────────────────────
function construireTete(look, m, mats) {
    const tete = new THREE.Group();
    const R = m.rTete;

    const crane = balle(R, mats.peau, 32);
    crane.scale.set(1, 1.06, 0.96);   // un crâne, pas une bille
    tete.add(crane);

    // Oreilles — cachées par certaines coupes, mais toujours là : sans elles, un
    // personnage rasé n'a plus de profil.
    [-1, 1].forEach(function (s) {
        const o = balle(R * 0.20, mats.peau, 16);
        o.scale.set(0.5, 1, 0.85);
        tete.add(place(o, s * R * 0.98, -R * 0.05, -R * 0.02));
    });

    // Nez
    const nez = balle(R * 0.15, mats.peauOmbre, 16);
    nez.scale.set(0.8, 0.85, 1.25);
    tete.add(place(nez, 0, -R * 0.06, R * 0.90));

    // ⚠️ TOUT LE VISAGE EST POSÉ EN SAILLIE, PAS À FLEUR DE CRÂNE. Un œil placé
    // au rayon de la tête est mathématiquement « sur » la sphère et
    // visuellement DEDANS : la surface passe devant lui dès qu'on tourne un
    // peu. Chaque pièce est donc avancée de quelques centièmes — c'est ce qui
    // fait qu'un visage reste lisible sous tous les angles.
    // Et quand une barbe habille le bas du visage, la bouche est avancée
    // ENCORE : la barbe est une pelure posée plus loin que la peau, elle
    // passerait sinon par-dessus.
    const avanceBouche = (look.barbe === 'pleine' || look.barbe === 'courte') ? R * 0.14 : 0;

    // ── LES YEUX ────────────────────────────────────────────────────────────
    // Un groupe par œil pour que le clignement (une mise à plat en Y) porte sur
    // le blanc, l'iris et la pupille d'un coup.
    const yeux = [];
    [-1, 1].forEach(function (s) {
        const oeil = new THREE.Group();
        const blanc = balle(R * 0.155, mats.blanc, 20);
        blanc.scale.set(1, 1.12, 0.6);
        oeil.add(blanc);

        const iris = balle(R * 0.082, mats.iris, 16);
        iris.scale.set(1, 1, 0.6);
        oeil.add(place(iris, 0, 0, R * 0.10));

        const pupille = balle(R * 0.042, mats.noir, 12);
        pupille.scale.set(1, 1, 0.6);
        oeil.add(place(pupille, 0, 0, R * 0.135));

        // Les cils sont accrochés À L'ŒIL, pas à la tête : ils se ferment donc
        // avec lui au clignement, sans une ligne de plus dans l'animation.
        if (look.cils && look.cils !== 'aucun') {
            const fort = (look.cils === 'marques');
            const trait = boite(R * 0.34, R * (fort ? 0.055 : 0.038), R * 0.10, mats.noir);
            trait.rotation.z = s * 0.12;
            oeil.add(place(trait, 0, R * 0.14, R * 0.06));
            if (fort) {
                // Deux pointes aux coins extérieurs : c'est ce qui distingue des
                // cils « marqués » d'un simple trait plus épais.
                const pointe = boite(R * 0.11, R * 0.035, R * 0.08, mats.noir);
                pointe.rotation.z = s * 0.75;
                oeil.add(place(pointe, s * R * 0.17, R * 0.18, R * 0.05));
            }
        }

        place(oeil, s * R * 0.36, R * 0.10, R * 0.86);
        tete.add(oeil);
        yeux.push(oeil);
    });

    // ── LES SOURCILS ────────────────────────────────────────────────────────
    const epaisseur = { fins: 0.030, standard: 0.042, epais: 0.058 }[look.sourcils] || 0.042;
    const penche = (look.expression === 'determine') ? 0.28
        : (look.expression === 'joyeux' ? -0.16 : -0.05);
    [-1, 1].forEach(function (s) {
        const sourcil = boite(R * 0.42, epaisseur, R * 0.10, mats.sourcils);
        sourcil.rotation.z = s * penche;
        tete.add(place(sourcil, s * R * 0.36, R * (look.expression === 'determine' ? 0.31 : 0.35), R * 0.86));
    });

    // ── LA BOUCHE ───────────────────────────────────────────────────────────
    // Le sourire est un arc de tore : c'est la seule forme simple qui donne une
    // courbe propre, et elle se retourne pour dire autre chose.
    const yBouche = -R * 0.42;
    const zBouche = R * 0.90 + avanceBouche;
    if (look.expression === 'neutre') {
        tete.add(place(boite(R * 0.36, R * 0.05, R * 0.06, mats.levres), 0, yBouche, zBouche));
    } else if (look.expression === 'determine') {
        const trait = boite(R * 0.34, R * 0.055, R * 0.06, mats.levres);
        trait.rotation.z = 0.10;
        tete.add(place(trait, 0, yBouche, zBouche));
    } else if (look.expression === 'sourire') {
        const arc = new THREE.Mesh(
            new THREE.TorusGeometry(R * 0.24, R * 0.035, 8, 20, Math.PI * 0.9),
            mats.levres
        );
        arc.rotation.z = Math.PI + Math.PI * 0.05;   // ouverture vers le haut
        tete.add(place(arc, 0, yBouche + R * 0.10, zBouche));
    } else {
        // Joyeux : bouche ouverte — une demi-sphère creusée, plus une langue.
        const ouverte = balle(R * 0.22, mats.levres, 20);
        ouverte.scale.set(1.05, 0.75, 0.45);
        tete.add(place(ouverte, 0, yBouche, zBouche - R * 0.04));
        const langue = balle(R * 0.11, mats.langue, 14);
        langue.scale.set(1, 0.6, 0.6);
        tete.add(place(langue, 0, yBouche - R * 0.07, zBouche));
    }

    // ── LE FARD À JOUES ─────────────────────────────────────────────────────
    // Deux disques très transparents, PLAQUÉS sur la joue et non posés devant :
    // d'où la rotation, qui les couche le long de la surface. Un maquillage
    // « lèvres » seul ne les pose pas — c'est justement ce qui le distingue.
    if (look.maquillage === 'discret' || look.maquillage === 'marque') {
        const force = (look.maquillage === 'marque') ? 1 : 0.62;
        [-1, 1].forEach(function (s) {
            const joue = new THREE.Mesh(new THREE.CircleGeometry(R * 0.20 * force, 18), mats.fard);
            joue.rotation.y = s * 0.85;
            tete.add(place(joue, s * R * 0.62, -R * 0.14, R * 0.62));
        });
    }
    // Les paupières fardées : une pelure fine posée sur le haut de l'œil.
    if (look.maquillage === 'marque') {
        [-1, 1].forEach(function (s) {
            const paupiere = boite(R * 0.30, R * 0.09, R * 0.05, mats.fard);
            paupiere.rotation.z = s * 0.14;
            tete.add(place(paupiere, s * R * 0.36, R * 0.22, R * 0.88));
        });
    }

    // ── LES BIJOUX ──────────────────────────────────────────────────────────
    const bijoux = look.bijoux || 'aucun';
    if (bijoux === 'boucles' || bijoux === 'boucles_collier') {
        [-1, 1].forEach(function (s) {
            tete.add(place(balle(R * 0.075, mats.bijou, 14), s * R * 1.02, -R * 0.24, R * 0.02));
        });
    }
    if (bijoux === 'anneaux') {
        [-1, 1].forEach(function (s) {
            const creole = new THREE.Mesh(new THREE.TorusGeometry(R * 0.15, R * 0.024, 8, 20), mats.bijou);
            creole.rotation.y = Math.PI / 2;
            tete.add(place(creole, s * R * 1.00, -R * 0.30, R * 0.02));
        });
    }

    return { groupe: tete, yeux: yeux };
}

// ─────────────────────────────────────────────────────────────────────────────
// LES CHEVEUX — treize coupes, toutes bâties sur la même pelure.
//
// `masqueCalotte` : quand un bonnet ou une casquette couvre le crâne, on saute
// la calotte mais on GARDE le volume (queue, tresses, chignon, longueur). Sans
// ça, mettre une casquette rendait chauve.
// ─────────────────────────────────────────────────────────────────────────────
function construireCheveux(look, m, mats, masqueCalotte) {
    const g = new THREE.Group();
    const R = m.rTete;
    const coupe = look.coupe;
    if (coupe === 'chauve') {
        return g;
    }
    const H = mats.cheveux;

    // ── POURQUOI DEUX COUCHES, ET PAS UNE ───────────────────────────────────
    // Une calotte est découpée à HAUTEUR CONSTANTE : elle descend pareil devant
    // et derrière. Descendue assez bas pour couvrir la nuque, elle recouvre les
    // sourcils ; remontée pour dégager le front, elle laisse l'arrière du crâne
    // chauve. Un vrai cheveu ne fait ni l'un ni l'autre.
    //
    // D'où deux pelures :
    //   • la BASSE couvre l'arrière et les côtés, et descend bas ;
    //   • la HAUTE fait tout le tour mais s'arrête au-dessus des sourcils.
    // Leur rencontre dessine exactement une ligne de cheveux.
    //
    // (Les angles de three.js : phi = π/2 regarde le VISAGE. La couche basse
    // part donc de l'autre côté.)
    const descenteBas = {
        courte: 0.60, brosse: 0.54, degrade: 0.48, coiffe: 0.60,
        carre: 0.66, mi_long: 0.66, long: 0.66, ondule: 0.66,
        queue: 0.62, demi_queue: 0.64, chignon: 0.60, tresse: 0.64,
        tresses: 0.64, boucle: 0.62, afro: 0.60, crete: 0.52
    }[coupe] || 0.60;
    const HAUTE = 0.34;                      // la ligne de cheveux, au-dessus des sourcils
    const ECART = Math.PI * 0.30;            // la portion de front laissée nue

    if (!masqueCalotte && coupe !== 'crete' && coupe !== 'afro') {
        const basse = pelure(R * 1.045, Math.PI * 0.5 + ECART, Math.PI * 2 - ECART * 2, 0, Math.PI * descenteBas, H);
        basse.scale.set(1, 1.06, 0.99);
        g.add(place(basse, 0, 0, -R * 0.02));

        const haute = pelure(R * 1.05, 0, Math.PI * 2, 0, Math.PI * HAUTE, H);
        haute.scale.set(1, 1.06, 1.0);
        g.add(place(haute, 0, 0, -R * 0.02));

        // Le bord de la couche haute, fermé par un anneau : une pelure n'a pas
        // d'épaisseur, et sa tranche se voit de profil comme une découpe au
        // cutter.
        const bord = new THREE.Mesh(
            new THREE.TorusGeometry(Math.sin(Math.PI * HAUTE) * R * 1.05, R * 0.030, 6, 30), H
        );
        bord.rotation.x = Math.PI / 2;
        g.add(place(bord, 0, Math.cos(Math.PI * HAUTE) * R * 1.05 * 1.06, -R * 0.02));
    }

    // ── LES VOLUMES PROPRES À CHAQUE COUPE ──────────────────────────────────
    if (coupe === 'brosse' && !masqueCalotte) {
        const plat = boite(R * 1.30, R * 0.22, R * 1.20, H);
        g.add(place(plat, 0, R * 1.02, -R * 0.04));
    }

    if (coupe === 'coiffe' && !masqueCalotte) {
        // Une mèche balayée sur le côté, posée sur la ligne de cheveux.
        const meche = balle(R * 0.44, H, 20);
        meche.scale.set(1.5, 0.45, 1.0);
        meche.rotation.z = -0.42;
        g.add(place(meche, R * 0.24, R * 0.68, R * 0.56));
    }

    if (coupe === 'carre') {
        // Le carré : une masse COURTE et FRANCHE qui s'arrête à la mâchoire, et
        // dont le bas est droit — c'est cette coupe nette qui le distingue d'un
        // mi-long, pas sa longueur.
        const longueur = R * 1.25;
        const nuque = boite(R * 1.62, longueur, R * 0.40, H);
        g.add(place(nuque, 0, -longueur / 2 + R * 0.34, -R * 0.70));
        [-1, 1].forEach(function (s) {
            const cote = boite(R * 0.38, longueur, R * 0.95, H);
            g.add(place(cote, s * R * 0.88, -longueur / 2 + R * 0.34, -R * 0.04));
        });
    }

    if (coupe === 'mi_long' || coupe === 'long' || coupe === 'ondule') {
        const longueur = (coupe === 'mi_long') ? R * 1.5 : R * 3.0;
        // La masse arrière : une boîte affinée qui tombe dans la nuque.
        const nuque = boite(R * 1.55, longueur, R * 0.35, H);
        g.add(place(nuque, 0, -longueur / 2 + R * 0.30, -R * 0.72));
        // Les deux mèches qui encadrent le visage.
        [-1, 1].forEach(function (s) {
            const cote = boite(R * 0.34, longueur * 0.78, R * 0.85, H);
            g.add(place(cote, s * R * 0.86, -longueur * 0.39 + R * 0.34, -R * 0.06));
        });
        // Le bas arrondi, pour que la chevelure ne finisse pas en planche.
        const bas = balle(R * 0.70, H, 18);
        bas.scale.set(1.1, 0.45, 0.35);
        g.add(place(bas, 0, -longueur + R * 0.32, -R * 0.72));

        // Les ondulations : des renflements réguliers le long de la masse. Une
        // vraie ondulation demanderait de déformer la géométrie ; trois boules
        // par côté donnent la même lecture pour un centième du travail.
        if (coupe === 'ondule') {
            for (let i = 0; i < 3; i++) {
                const y = -R * (0.55 + i * 0.85);
                [-1, 1].forEach(function (s) {
                    const vague = balle(R * 0.46, H, 14);
                    vague.scale.set(0.85, 0.75, 0.60);
                    g.add(place(vague, s * R * 0.84, y, -R * (0.22 + (i % 2) * 0.20)));
                });
            }
        }
    }

    if (coupe === 'queue') {
        const attache = balle(R * 0.26, H, 16);
        g.add(place(attache, 0, R * 0.30, -R * 1.02));
        const queue = capsule(R * 0.20, R * 1.05, H);
        queue.rotation.x = -0.55;
        g.add(place(queue, 0, -R * 0.28, -R * 1.28));
    }

    if (coupe === 'demi_queue') {
        // Le haut attaché, le bas lâché : deux volumes, pas un.
        const longueur = R * 1.9;
        const nuque = boite(R * 1.45, longueur, R * 0.34, H);
        g.add(place(nuque, 0, -longueur / 2 + R * 0.30, -R * 0.72));
        [-1, 1].forEach(function (s) {
            g.add(place(boite(R * 0.32, longueur * 0.70, R * 0.80, H), s * R * 0.86, -longueur * 0.35 + R * 0.32, -R * 0.06));
        });
        const attache = balle(R * 0.20, mats.ruban, 14);
        g.add(place(attache, 0, R * 0.52, -R * 0.94));
        const meche = capsule(R * 0.17, R * 0.60, H);
        meche.rotation.x = -0.30;
        g.add(place(meche, 0, R * 0.10, -R * 1.10));
    }

    if (coupe === 'tresse') {
        // Une seule natte dans le dos : des boules qui décroissent, décalées en
        // quinconce — c'est le décalage qui fait lire « tressé » plutôt que
        // « chapelet ».
        const attache = balle(R * 0.22, H, 14);
        g.add(place(attache, 0, R * 0.20, -R * 1.00));
        for (let i = 0; i < 6; i++) {
            const noeud = balle(R * (0.23 - i * 0.018), H, 14);
            noeud.scale.set(1, 0.85, 1);
            g.add(place(noeud, (i % 2 ? 1 : -1) * R * 0.05, -R * (0.10 + i * 0.34), -R * (1.08 - i * 0.03)));
        }
        const ruban = new THREE.Mesh(new THREE.TorusGeometry(R * 0.12, R * 0.035, 6, 14), mats.ruban);
        ruban.rotation.x = Math.PI / 2;
        g.add(place(ruban, 0, -R * 2.10, -R * 0.93));
    }

    if (coupe === 'chignon') {
        const chignon = balle(R * 0.40, H, 20);
        chignon.scale.set(1, 0.92, 0.92);
        g.add(place(chignon, 0, R * 0.86, -R * 0.78));
    }

    if (coupe === 'tresses') {
        [-1, 1].forEach(function (s) {
            const attache = balle(R * 0.17, mats.ruban, 14);
            g.add(place(attache, s * R * 0.92, R * 0.18, -R * 0.20));
            // Trois boules par couette : c'est ce qui donne l'aspect tressé.
            for (let i = 0; i < 3; i++) {
                const noeud = balle(R * 0.21 - i * R * 0.02, H, 16);
                g.add(place(noeud, s * R * 1.00, -R * (0.10 + i * 0.34), -R * 0.24));
            }
        });
    }

    if (coupe === 'boucle' || coupe === 'afro') {
        // ── DU VOLUME SANS AVALER LE VISAGE ─────────────────────────────────
        // Une grosse boule autour de la tête donnerait bien le volume… et
        // enterrerait les yeux. On sème donc des touffes SUR la sphère du
        // crâne, en sautant la fenêtre du visage : la coiffure a du relief, le
        // regard reste dégagé.
        //
        // Les positions sont CALCULÉES, jamais tirées au sort : deux affichages
        // du même collaborateur doivent donner exactement la même tête.
        const gros = (coupe === 'afro');
        const rTouffe = gros ? R * 0.36 : R * 0.26;
        const distance = gros ? R * 1.10 : R * 0.98;
        const anneaux = gros
            ? [{ y: 0.95, n: 5 }, { y: 0.62, n: 10 }, { y: 0.20, n: 12 }, { y: -0.18, n: 12 }]
            : [{ y: 0.92, n: 4 }, { y: 0.60, n: 9 }, { y: 0.24, n: 10 }];

        anneaux.forEach(function (anneau) {
            // Rayon du cercle à cette hauteur : les touffes épousent le crâne
            // au lieu de flotter en couronne.
            const k = Math.max(0.25, Math.sqrt(Math.max(0, 1 - (anneau.y / 1.12) * (anneau.y / 1.12))));
            for (let i = 0; i < anneau.n; i++) {
                const a = (i / anneau.n) * Math.PI * 2;
                const z = Math.cos(a);
                // La fenêtre du visage : devant (z > 0) et sous la ligne de
                // cheveux, on ne pose rien.
                if (z > 0.45 && anneau.y < 0.62) { continue; }
                const touffe = balle(rTouffe, H, 14);
                g.add(place(
                    touffe,
                    Math.sin(a) * distance * k,
                    R * anneau.y,
                    z * distance * k - R * 0.05
                ));
            }
        });
        const dessus = balle(gros ? R * 0.80 : R * 0.62, H, 18);
        g.add(place(dessus, 0, R * (gros ? 0.92 : 0.82), -R * 0.06));
    }

    if (coupe === 'crete') {
        // Le rasé sur les côtés, en plus foncé, pour que la crête se détache.
        if (!masqueCalotte) {
            const rase = pelure(R * 1.03, 0, Math.PI * 2, 0, Math.PI * 0.58, mats.cheveuxOmbre);
            rase.scale.set(1, 1.06, 0.99);
            g.add(place(rase, 0, 0, -R * 0.03));
        }
        for (let i = 0; i < 6; i++) {
            const h = R * (0.42 + Math.sin((i / 5) * Math.PI) * 0.42);
            const pic = boite(R * 0.14, h, R * 0.26, H);
            g.add(place(pic, 0, R * 0.98 + h / 2 - R * 0.18, R * (0.55 - i * 0.28)));
        }
    }

    return g;
}

// ─────────────────────────────────────────────────────────────────────────────
// BARBE ET LUNETTES.
// ─────────────────────────────────────────────────────────────────────────────
function construireBarbe(look, m, mats) {
    const g = new THREE.Group();
    const R = m.rTete;
    const B = mats.barbe;   // sa propre couleur — « auto » suit les cheveux
    const style = look.barbe;
    if (!style || style === 'aucune') {
        return g;
    }

    // La moustache passe devant la pelure quand il y a une barbe pleine :
    // sinon elle se retrouve dessous, donc invisible.
    if (style === 'moustache' || style === 'bouc' || style === 'pleine') {
        const mo = boite(R * 0.44, R * 0.11, R * 0.14, B);
        g.add(place(mo, 0, -R * 0.26, (style === 'pleine') ? R * 1.02 : R * 0.90));
    }
    if (style === 'bouc') {
        const bouc = balle(R * 0.20, B, 16);
        bouc.scale.set(1, 1.1, 0.7);
        g.add(place(bouc, 0, -R * 0.62, R * 0.78));
    }
    if (style === 'courte' || style === 'pleine') {
        // La pelure basse : elle épouse la mâchoire, ce qu'aucune boîte ne fait.
        //
        // ⚠️ phi = π/2 REGARDE LE VISAGE (voir les cheveux). Une barbe centrée
        // sur phi = π serait posée sur la joue droite. Et `bas` dit où elle
        // COMMENCE en descendant : plus il est petit, plus elle monte haut sur
        // les joues — jamais assez haut pour couvrir le nez.
        const bas = (style === 'pleine') ? 0.57 : 0.59;
        const barbe = pelure(R * (style === 'pleine' ? 1.09 : 1.045),
            Math.PI * 0.02, Math.PI * 0.96,
            Math.PI * bas, Math.PI * (1 - bas),
            style === 'courte' ? mats.barbeOmbre : B);
        barbe.scale.set(1, 1.06, 0.99);
        g.add(place(barbe, 0, 0, -R * 0.02));
    }
    if (style === 'pleine') {
        const menton = balle(R * 0.34, B, 18);
        menton.scale.set(1, 1.15, 0.8);
        g.add(place(menton, 0, -R * 0.86, R * 0.34));
    }

    return g;
}

function construireLunettes(look, m, mats) {
    const g = new THREE.Group();
    const style = look.lunettes;
    if (!style || style === 'aucune') {
        return g;
    }
    const R = m.rTete;
    const monture = (style === 'soleil') ? mats.noir : mats.monture;
    const verre = (style === 'soleil') ? mats.verreSombre : mats.verre;

    const taille = { fines: 0.155, rondes: 0.185, carrees: 0.175, soleil: 0.185 }[style] || 0.17;
    const tube = (style === 'fines') ? 0.016 : 0.026;

    // Les verres passent DEVANT les yeux, qui sont eux-mêmes en saillie : d'où
    // un rayon plus grand que celui de la tête.
    [-1, 1].forEach(function (s) {
        if (style === 'carrees') {
            const cadre = boite(R * taille * 2.2, R * taille * 1.7, R * 0.05, monture);
            g.add(place(cadre, s * R * 0.36, R * 0.10, R * 1.00));
            const v = boite(R * taille * 1.9, R * taille * 1.4, R * 0.03, verre);
            g.add(place(v, s * R * 0.36, R * 0.10, R * 1.03));
        } else {
            const cercle = new THREE.Mesh(
                new THREE.TorusGeometry(R * taille, R * tube, 8, 22), monture
            );
            g.add(place(cercle, s * R * 0.36, R * 0.10, R * 1.01));
            const v = new THREE.Mesh(new THREE.CircleGeometry(R * taille, 20), verre);
            g.add(place(v, s * R * 0.36, R * 0.10, R * 1.005));
        }
        // Les branches, qui filent vers les oreilles.
        const branche = boite(R * 0.05, R * 0.05, R * 0.70, monture);
        g.add(place(branche, s * R * 0.64, R * 0.10, R * 0.62));
    });

    // Le pont
    g.add(place(boite(R * 0.24, R * 0.045, R * 0.05, monture), 0, R * 0.12, R * 1.00));

    return g;
}

// ─────────────────────────────────────────────────────────────────────────────
// LE COUVRE-CHEF.
// ─────────────────────────────────────────────────────────────────────────────
function construireCouvreChef(look, m, mats) {
    const g = new THREE.Group();
    const style = look.couvre_chef;
    if (!style || style === 'aucun') {
        return g;
    }
    const R = m.rTete;
    const C = mats.couvreChef;

    // ⚠️ MÊME PIÈGE QUE POUR LES CHEVEUX : une demi-sphère (thetaLength = π/2)
    // descend jusqu'au niveau des yeux et les couvre. Le bord d'un couvre-chef
    // doit s'arrêter AU-DESSUS des sourcils, qui sont à 0,35 rayon de tête.
    if (style === 'casquette') {
        const THETA = Math.PI * 0.40;
        const dome = pelure(R * 1.10, 0, Math.PI * 2, 0, THETA, C);
        dome.scale.set(1, 1.02, 1);
        g.add(place(dome, 0, R * 0.06, -R * 0.02));
        const bord = new THREE.Mesh(
            new THREE.TorusGeometry(Math.sin(THETA) * R * 1.10, R * 0.05, 6, 28), C
        );
        bord.rotation.x = Math.PI / 2;
        g.add(place(bord, 0, R * 0.06 + Math.cos(THETA) * R * 1.12, -R * 0.02));
        // La visière : un disque aplati, coupé en demi-lune.
        const visiere = new THREE.Mesh(
            new THREE.CylinderGeometry(R * 0.86, R * 0.86, R * 0.06, 22, 1, false, -Math.PI / 2, Math.PI), C
        );
        visiere.rotation.x = -0.16;
        g.add(place(visiere, 0, R * 0.44, R * 0.56));
    }

    if (style === 'bonnet') {
        const THETA = Math.PI * 0.40;
        const dome = pelure(R * 1.12, 0, Math.PI * 2, 0, THETA, C);
        dome.scale.set(1, 1.10, 1);
        g.add(place(dome, 0, R * 0.02, -R * 0.02));
        const revers = new THREE.Mesh(
            new THREE.TorusGeometry(Math.sin(THETA) * R * 1.12, R * 0.11, 8, 30), C
        );
        revers.rotation.x = Math.PI / 2;
        g.add(place(revers, 0, R * 0.02 + Math.cos(THETA) * R * 1.12 * 1.10, -R * 0.02));
        const pompon = balle(R * 0.22, C, 16);
        g.add(place(pompon, 0, R * 1.32, -R * 0.02));
    }

    if (style === 'bandana') {
        // Lui n'a pas de dôme : il ceint la tête, il ne la couvre pas — c'est
        // pourquoi il ne masque pas la coiffure (voir construirePersonnage).
        const bande = new THREE.Mesh(new THREE.TorusGeometry(R * 0.94, R * 0.09, 8, 30), C);
        bande.rotation.x = Math.PI / 2;
        g.add(place(bande, 0, R * 0.50, -R * 0.02));
        const noeud = balle(R * 0.16, C, 14);
        g.add(place(noeud, 0, R * 0.46, -R * 1.00));
        [-1, 1].forEach(function (s) {
            const pan = boite(R * 0.10, R * 0.50, R * 0.06, C);
            pan.rotation.x = -0.35;
            g.add(place(pan, s * R * 0.12, R * 0.18, -R * 1.04));
        });
    }

    if (style === 'chapeau') {
        const THETA = Math.PI * 0.42;
        const dome = pelure(R * 1.08, 0, Math.PI * 2, 0, THETA, C);
        g.add(place(dome, 0, R * 0.14, -R * 0.02));
        const bord = new THREE.Mesh(new THREE.CylinderGeometry(R * 1.85, R * 1.85, R * 0.06, 30), C);
        g.add(place(bord, 0, R * 0.14 + Math.cos(THETA) * R * 1.08, -R * 0.02));
        const ruban = new THREE.Mesh(new THREE.TorusGeometry(R * 1.00, R * 0.07, 8, 28), mats.ruban);
        ruban.rotation.x = Math.PI / 2;
        g.add(place(ruban, 0, R * 0.14 + Math.cos(THETA) * R * 1.08 + R * 0.10, -R * 0.02));
    }

    return g;
}

// ─────────────────────────────────────────────────────────────────────────────
// LE HAUT — le torse EST le vêtement, les manches habillent les bras.
// ─────────────────────────────────────────────────────────────────────────────
function construireTorse(look, m, mats) {
    const g = new THREE.Group();
    const rBase = 0.20;
    const buste = capsule(rBase, Math.max(0.05, m.hTorse - rBase * 2), mats.haut);
    buste.scale.set((m.lTorse / 2) / rBase, 1, (m.pTorse / 2) / rBase);
    g.add(place(buste, 0, m.yHanche + m.hTorse / 2, 0));

    const yEp = m.yEpaule;
    const demiL = m.lTorse / 2;
    const demiP = m.pTorse / 2;

    // ── CE QUE LA MORPHOLOGIE AJOUTE ────────────────────────────────────────
    // La largeur d'épaules est déjà prise en compte dans les mesures ; il reste
    // ce qui ne se règle pas par un simple facteur.
    //
    // La poitrine est posée EN PREMIER, donc sous le col, la patte de
    // boutonnage et le tablier : c'est le vêtement qui doit passer par-dessus,
    // pas l'inverse.
    if (m.forme.buste) {
        [-1, 1].forEach(function (s) {
            const sein = balle(demiL * 0.34, mats.haut, 18);
            sein.scale.set(1, 0.92, 0.80);
            g.add(place(sein, s * demiL * 0.34, yEp - m.hTorse * 0.30, demiP * 0.66));
        });
    }
    if (m.forme.epaules > 1.02) {
        // Un buste qui s'élargit vers le haut, plutôt qu'un tube. Sans lui, une
        // morphologie masculine n'est qu'un personnage un peu plus large.
        const carrure = balle(demiL * 0.98, mats.haut, 20);
        carrure.scale.set(1, 0.34, (m.pTorse / m.lTorse) * 1.10);
        g.add(place(carrure, 0, yEp - m.hTorse * 0.16, 0));
    }

    // Le cou : sa couleur dépend du vêtement — un col roulé ne laisse pas voir
    // la peau, un débardeur si.
    const cou = new THREE.Mesh(new THREE.CylinderGeometry(0.075, 0.082, 0.11, 16), mats.peau);
    g.add(place(cou, 0, yEp + 0.02, 0));

    // ── LE GILET FAMIFLORA ──────────────────────────────────────────────────
    // La pièce de la maison, relevée sur les photos : vert, SANS MANCHES, bordé
    // de vert anis au col, aux emmanchures et en bas, fermeture éclair anis au
    // milieu, deux poches basses et une fenêtre porte-badge à gauche.
    //
    // Il est bâti PAR-DESSUS le t-shirt (déjà en place au-dessus) : c'est ce
    // qui fait qu'on voit les manches courtes dépasser aux épaules, comme en
    // vrai. Le t-shirt garde donc la couleur choisie — le gilet, non : un gilet
    // Famiflora rose ne serait plus un gilet Famiflora.
    if (look.haut === 'gilet_fami') {
        const rG = rBase * 1.05;
        const hG = m.hTorse * 0.90;
        const yG = m.yHanche + hG / 2 + 0.01;
        const eL = ((m.lTorse / 2) / rBase) * 0.97;   // il épouse le buste
        const eP = ((m.pTorse / 2) / rBase) * 1.08;

        const corps = capsule(rG, Math.max(0.02, hG - rG * 2), mats.gilet);
        corps.scale.set(eL, 1, eP);
        g.add(place(corps, 0, yG, 0));

        const demiGL = m.lTorse / 2 * 0.97;
        const demiGP = m.pTorse / 2 * 1.10;

        // La fermeture éclair, du col à l'ourlet.
        g.add(place(boite(0.026, hG * 0.92, 0.02, mats.anis), 0, yG, demiGP * 1.02));
        // Le passepoil : col, ourlet, et les deux emmanchures.
        const col = new THREE.Mesh(new THREE.TorusGeometry(demiGL * 0.52, 0.020, 6, 24), mats.anis);
        col.rotation.x = Math.PI / 2;
        col.scale.set(1, (demiGP / demiGL) * 1.35, 1);
        g.add(place(col, 0, yEp - 0.045, 0));

        const ourlet = new THREE.Mesh(new THREE.TorusGeometry(demiGL * 0.98, 0.020, 6, 28), mats.anis);
        ourlet.rotation.x = Math.PI / 2;
        ourlet.scale.set(1, (demiGP / demiGL) * 1.02, 1);
        g.add(place(ourlet, 0, yG - hG / 2, 0));

        [-1, 1].forEach(function (s) {
            const emmanchure = new THREE.Mesh(new THREE.TorusGeometry(0.075, 0.018, 6, 18), mats.anis);
            emmanchure.rotation.y = Math.PI / 2;
            emmanchure.scale.set(1.5, 1, 1);
            g.add(place(emmanchure, s * demiGL * 1.00, yEp - 0.085, 0));
            // Les deux poches basses.
            g.add(place(boite(demiGL * 0.62, 0.11, 0.02, mats.giletOmbre),
                s * demiGL * 0.48, m.yHanche + m.hTorse * 0.20, demiGP * 1.02));
        });

        // La fenêtre porte-badge, sur la poitrine.
        g.add(place(boite(0.13, 0.085, 0.012, mats.fenetre), demiGL * 0.44, yEp - m.hTorse * 0.28, demiGP * 1.03));
        g.add(place(boite(0.14, 0.095, 0.010, mats.giletOmbre), demiGL * 0.44, yEp - m.hTorse * 0.28, demiGP * 1.01));
    }

    // Le t-shirt de la maison : un liseré anis à l'encolure suffit à le
    // distinguer d'un t-shirt vert quelconque.
    if (look.haut === 'tshirt_fami') {
        const encolure = new THREE.Mesh(new THREE.TorusGeometry(0.098, 0.018, 6, 22), mats.anis);
        encolure.rotation.x = Math.PI / 2;
        g.add(place(encolure, 0, yEp - 0.020, 0.010));
        g.add(place(boite(0.115, 0.030, 0.014, mats.anis), demiL * 0.42, yEp - m.hTorse * 0.26, demiP * 1.03));
    }

    if (look.haut === 'polo' || look.haut === 'chemise' || look.haut === 'chemisier' || look.haut === 'veste') {
        // Un col : deux pans posés à plat, légèrement écartés.
        [-1, 1].forEach(function (s) {
            const pan = boite(demiL * 0.62, 0.035, demiP * 1.5, mats.hautClair);
            pan.rotation.y = s * 0.35;
            pan.rotation.z = s * 0.22;
            g.add(place(pan, s * demiL * 0.30, yEp - 0.035, demiP * 0.62));
        });
    }
    if (look.haut === 'chemise' || look.haut === 'chemisier') {
        // La patte de boutonnage, plus claire, et ses boutons.
        g.add(place(boite(0.055, m.hTorse * 0.80, 0.02, mats.hautClair),
            0, m.yHanche + m.hTorse * 0.48, demiP * 0.99));
        for (let i = 0; i < 4; i++) {
            const b = balle(0.017, mats.blanc, 10);
            g.add(place(b, 0, yEp - 0.11 - i * 0.10, demiP * 1.02));
        }
    }
    if (look.haut === 'chemisier') {
        // Ce qui fait le chemisier plutôt que la chemise : un col plus fin, un
        // volant à l'encolure, et une taille marquée par une couture.
        const volant = new THREE.Mesh(new THREE.TorusGeometry(0.105, 0.024, 8, 24), mats.hautClair);
        volant.rotation.x = Math.PI / 2;
        volant.scale.set(1, 1.2, 1);
        g.add(place(volant, 0, yEp - 0.055, 0.015));
        const couture = new THREE.Mesh(new THREE.TorusGeometry(demiL * 0.86, 0.016, 6, 26), mats.hautOmbre);
        couture.rotation.x = Math.PI / 2;
        couture.scale.set(1, (demiP / demiL) * 1.05, 1);
        g.add(place(couture, 0, m.yHanche + m.hTorse * 0.22, 0));
    }
    if (look.haut === 'pull') {
        // Les côtes du bas : c'est ce détail qui fait lire « pull » plutôt que
        // « t-shirt un peu épais ».
        const cotes = new THREE.Mesh(new THREE.TorusGeometry(demiL * 0.92, 0.035, 8, 28), mats.hautOmbre);
        cotes.rotation.x = Math.PI / 2;
        cotes.scale.set(1, (demiP / demiL) * 1.05, 1);
        g.add(place(cotes, 0, m.yHanche + 0.045, 0));
        const encolure = new THREE.Mesh(new THREE.TorusGeometry(0.10, 0.026, 8, 22), mats.hautOmbre);
        encolure.rotation.x = Math.PI / 2;
        g.add(place(encolure, 0, yEp - 0.015, 0.012));
    }
    if (look.haut === 'sweat') {
        // La capuche, derrière la nuque.
        const capuche = balle(0.19, mats.hautOmbre, 20);
        capuche.scale.set(1.25, 0.95, 0.75);
        g.add(place(capuche, 0, yEp + 0.02, -demiP * 1.05));
        // La poche kangourou et les cordons.
        g.add(place(boite(demiL * 1.05, 0.16, 0.03, mats.hautOmbre),
            0, m.yHanche + m.hTorse * 0.26, demiP * 1.00));
        [-1, 1].forEach(function (s) {
            g.add(place(boite(0.022, 0.16, 0.022, mats.blanc),
                s * 0.055, yEp - 0.11, demiP * 0.94));
        });
    }
    if (look.haut === 'veste') {
        // Une veste ouverte : on voit un tee-shirt clair dessous.
        g.add(place(boite(demiL * 0.55, m.hTorse * 0.80, 0.03, mats.blanc),
            0, m.yHanche + m.hTorse * 0.48, demiP * 0.99));
        [-1, 1].forEach(function (s) {
            const revers = boite(demiL * 0.34, m.hTorse * 0.42, 0.035, mats.hautOmbre);
            revers.rotation.z = s * 0.12;
            g.add(place(revers, s * demiL * 0.46, yEp - m.hTorse * 0.22, demiP * 1.00));
        });
    }
    if (look.haut === 'debardeur') {
        // Deux bretelles, et des épaules nues. La peau est une CALOTTE posée sur
        // le haut du buste, pas un second buste : repeindre tout le torse
        // laisserait un débardeur qui commence au nombril.
        const epaules = balle(rBase, mats.peau, 22);
        epaules.scale.set((m.lTorse / 2) / rBase, 0.55, (m.pTorse / 2) / rBase * 1.02);
        g.add(place(epaules, 0, yEp - 0.05, 0));
        [-1, 1].forEach(function (s) {
            g.add(place(boite(0.052, 0.14, demiP * 1.9, mats.haut),
                s * demiL * 0.52, yEp - 0.055, 0));
        });
    }

    return g;
}

/**
 * Un bras complet, EN DEUX SEGMENTS ARTICULÉS.
 *
 * ⚠️ LE COUDE EST CE QUI REND LES POSES POSSIBLES. Un bras d'une seule pièce ne
 * sait faire que « le long du corps » ou « tendu » : impossible de croiser les
 * bras, de poser les mains sur les hanches ou de faire un signe. D'où deux
 * groupes emboîtés — l'épaule, et le coude qui pend dedans. Une pose n'est plus
 * alors qu'une paire d'angles par bras (voir POSES).
 *
 * @returns {Object} { epaule, coude } — les deux pivots, que l'animation et les
 *                   poses font tourner. Le reste y est simplement accroché.
 */
function construireBras(cote, look, m, mats) {
    const rB = m.rBras;
    const lHaut = m.hBras * 0.46;   // épaule → coude
    const lBas  = m.hBras * 0.54;   // coude → poignet

    const epaule = new THREE.Group();
    const haut = capsule(rB, Math.max(0.01, lHaut - rB * 2), mats.peau);
    epaule.add(place(haut, 0, -lHaut / 2, 0));

    const coude = new THREE.Group();
    place(coude, 0, -lHaut, 0);
    epaule.add(coude);

    const avant = capsule(rB * 0.94, Math.max(0.01, lBas - rB * 2), mats.peau);
    coude.add(place(avant, 0, -lBas / 2, 0));

    const main = balle(rB * 1.18, look.accessoire === 'gants' ? mats.gant : mats.peau, 16);
    main.scale.set(1, 1.15, 0.85);
    coude.add(place(main, 0, -lBas - rB * 0.10, 0));

    if (look.accessoire === 'gants') {
        const poignet = new THREE.Mesh(new THREE.TorusGeometry(rB * 1.02, rB * 0.30, 8, 16), mats.gantOmbre);
        poignet.rotation.x = Math.PI / 2;
        coude.add(place(poignet, 0, -lBas + rB * 0.30, 0));
    }

    // La manche : courte pour un t-shirt, longue pour un pull. Le débardeur n'en
    // a pas — et c'est exactement ce qui le distingue à l'écran. Une manche
    // longue est en DEUX morceaux, un par segment : d'un seul tenant, elle
    // resterait droite pendant que le bras se plie.
    const longues = (look.haut === 'chemise' || look.haut === 'pull' || look.haut === 'sweat' || look.haut === 'veste');
    if (look.haut !== 'debardeur') {
        const lm = longues ? lHaut : lHaut * 0.62;
        const manche = capsule(rB * 1.22, Math.max(0.01, lm - rB * 1.2), mats.haut);
        epaule.add(place(manche, 0, -lm / 2 + rB * 0.10, 0));

        if (longues) {
            const bas = lBas * 0.80;
            const manchette = capsule(rB * 1.16, Math.max(0.01, bas - rB * 1.2), mats.haut);
            coude.add(place(manchette, 0, -bas / 2 + rB * 0.16, 0));
            const bord = new THREE.Mesh(new THREE.TorusGeometry(rB * 1.10, rB * 0.22, 8, 16), mats.hautOmbre);
            bord.rotation.x = Math.PI / 2;
            coude.add(place(bord, 0, -bas + rB * 0.16, 0));
        }
    }

    place(epaule, cote * (m.lTorse / 2 + rB * 0.35), m.yEpaule - 0.075, 0);
    return { epaule: epaule, coude: coude };
}

// ─────────────────────────────────────────────────────────────────────────────
// LES POSES.
//
// Une pose = deux angles par bras (l'épaule et le coude), plus un mouvement de
// tête. Rien d'autre : c'est ce qui la rend lisible en silhouette, et surtout
// ce qui permet d'en ajouter une en trois lignes.
//
// Repères, parce qu'ils ne sont pas devinables :
//   • le bras pend vers -Y ; une rotation Z POSITIVE emmène la main vers +X.
//     Donc pour lever le bras de droite (côté +X), on tourne vers +π ; pour
//     celui de gauche, vers -π. D'où les signes opposés partout.
//   • une rotation X NÉGATIVE amène l'avant-bras vers l'avant (+Z).
//
// `balance` dit combien du balancement d'attente il reste : un personnage qui
// croise les bras ne doit pas continuer à les balancer, mais un personnage
// debout, si. `agite` désigne le bras qui fait coucou.
// ─────────────────────────────────────────────────────────────────────────────
const POSES = {
    neutre: {
        g: { ep: [0, 0, -0.13], co: [0, 0, 0] },
        d: { ep: [0, 0, 0.13], co: [0, 0, 0] },
        tete: [0, 0, 0], balance: 1
    },
    salut: {
        g: { ep: [0, 0, -0.16], co: [0, 0, 0] },
        d: { ep: [0, 0, 2.30], co: [0, 0, -0.25] },
        tete: [0, -0.12, 0.08], balance: 0.30, agite: 'd'
    },
    hanches: {
        g: { ep: [0, 0, -0.60], co: [-0.25, 0, 1.45] },
        d: { ep: [0, 0, 0.60], co: [-0.25, 0, -1.45] },
        tete: [0, 0, 0], balance: 0.12
    },
    bras_croises: {
        g: { ep: [-0.30, 0, -0.30], co: [-0.55, 0, 1.60] },
        d: { ep: [-0.30, 0, 0.30], co: [-0.55, 0, -1.60] },
        tete: [0, 0, 0], balance: 0.10
    },
    victoire: {
        g: { ep: [0, 0, -2.55], co: [0, 0, 0.18] },
        d: { ep: [0, 0, 2.55], co: [0, 0, -0.18] },
        tete: [-0.10, 0, 0], balance: 0.35
    },
    presente: {
        g: { ep: [0, 0, -0.16], co: [0, 0, 0] },
        d: { ep: [0, 0, 1.55], co: [0, 0, 0] },
        tete: [0, -0.22, 0], balance: 0.20
    }
};

function poseDe(look) {
    return POSES[look.pose] || POSES.neutre;
}

// ─────────────────────────────────────────────────────────────────────────────
// LE BAS ET LES CHAUSSURES.
// ─────────────────────────────────────────────────────────────────────────────
function construireJambes(look, m, mats) {
    const g = new THREE.Group();
    const rJ = m.rJambe;
    const bas = look.bas;

    // Jusqu'où le tissu descend. En dessous, c'est la peau.
    const couverture = {
        pantalon: 1.0, jean: 1.0, leggings: 1.0,
        bermuda: 0.52, short: 0.34, jupe: 0.0, jupe_longue: 0.0
    }[bas];
    const cv = (couverture === undefined) ? 1.0 : couverture;
    // Un legging colle à la jambe : c'est son épaisseur, et rien d'autre, qui
    // le distingue d'un pantalon.
    const ampleur = (bas === 'leggings') ? 1.03 : 1.14;
    const jupe = (bas === 'jupe' || bas === 'jupe_longue');

    [-1, 1].forEach(function (s) {
        const x = s * m.ecartJambes;

        // La jambe nue d'abord, sur toute la longueur : le tissu se pose dessus.
        const jambe = capsule(rJ, m.hJambe - rJ * 2, mats.peau);
        g.add(place(jambe, x, m.hPied + m.hJambe / 2, 0));

        if (cv > 0) {
            const hT = m.hJambe * cv;
            const tissu = capsule(rJ * ampleur, hT - rJ * 1.1, mats.bas);
            g.add(place(tissu, x, m.yHanche - hT / 2 + rJ * 0.05, 0));
            if (bas === 'jean') {
                // Une couture claire : le jean se reconnaît à ça.
                g.add(place(boite(0.012, hT * 0.9, 0.012, mats.basClair),
                    x + rJ * 1.05, m.yHanche - hT / 2, 0));
            }
            if (cv < 1) {
                const ourlet = new THREE.Mesh(new THREE.TorusGeometry(rJ * 1.10, rJ * 0.16, 8, 16), mats.basOmbre);
                ourlet.rotation.x = Math.PI / 2;
                g.add(place(ourlet, x, m.yHanche - hT, 0));
            }
        }

        // ── LA CHAUSSURE ────────────────────────────────────────────────────
        g.add(construireChaussure(look, m, mats, x));
    });

    // ── LE BASSIN ───────────────────────────────────────────────────────────
    // Il ne suit PAS la largeur d'épaules : c'est l'écart entre les deux qui
    // dessine une silhouette. D'où `m.lHanche`, calculé à part (voir mesures()).
    // Il vient aussi combler la jonction torse / jambes, qui sans lui se voit
    // comme une coupure nette.
    const bassin = balle(m.lHanche * 0.42, (cv > 0 || jupe) ? mats.bas : mats.peau, 20);
    bassin.scale.set(1, 0.62, (m.pTorse / m.lHanche) * 1.05);
    g.add(place(bassin, 0, m.yHanche - 0.03, 0));

    if (jupe) {
        // Un tronc de cône : la seule forme qui tombe correctement. La jupe
        // longue s'évase davantage — une jupe deux fois plus longue et aussi
        // étroite ressemblerait à un fourreau.
        const longue = (bas === 'jupe_longue');
        const hauteur = m.hJambe * (longue ? 0.82 : 0.46);
        const etoffe = new THREE.Mesh(
            new THREE.CylinderGeometry(m.lHanche * 0.46, m.lHanche * (longue ? 0.92 : 0.74), hauteur, 26, 1, true),
            mats.basDouble
        );
        g.add(place(etoffe, 0, m.yHanche - hauteur / 2 + 0.02, 0));
        const ceinture = new THREE.Mesh(new THREE.TorusGeometry(m.lHanche * 0.45, 0.028, 8, 26), mats.basOmbre);
        ceinture.rotation.x = Math.PI / 2;
        g.add(place(ceinture, 0, m.yHanche + 0.02, 0));
    } else {
        // La ceinture de taille.
        const ceinture = new THREE.Mesh(new THREE.TorusGeometry(m.lHanche * 0.41, 0.032, 8, 26), mats.basOmbre);
        ceinture.rotation.x = Math.PI / 2;
        ceinture.scale.set(1, (m.pTorse / m.lHanche) * 1.15, 1);
        g.add(place(ceinture, 0, m.yHanche + 0.01, 0));
    }

    return g;
}

function construireChaussure(look, m, mats, x) {
    const g = new THREE.Group();
    const style = look.chaussures;
    const h = m.hPied;
    const S = mats.chaussure;

    const longueur = (style === 'ville') ? 0.30 : (style === 'sabots' ? 0.28 : 0.29);
    const largeur = (style === 'securite' || style === 'sabots') ? 0.20 : 0.175;
    const hauteur = (style === 'securite') ? h * 1.5 : h;

    const pied = boite(largeur, hauteur, longueur, S);
    g.add(place(pied, x, hauteur / 2, longueur * 0.20));

    const bout = balle(largeur * 0.52, style === 'securite' ? mats.metal : S, 16);
    bout.scale.set(1, hauteur / (largeur * 0.9), 0.85);
    g.add(place(bout, x, hauteur / 2, longueur * 0.68));

    if (style === 'baskets') {
        const semelle = boite(largeur * 1.06, h * 0.32, longueur * 1.02, mats.blanc);
        g.add(place(semelle, x, h * 0.16, longueur * 0.20));
        [-1, 1].forEach(function (s) {
            g.add(place(boite(0.008, h * 0.5, longueur * 0.5, mats.blanc),
                x + s * largeur * 0.5, hauteur * 0.62, longueur * 0.28));
        });
    }
    if (style === 'ville') {
        const talon = boite(largeur * 0.9, h * 0.5, longueur * 0.28, mats.noir);
        g.add(place(talon, x, h * 0.25, -longueur * 0.28));
    }
    if (style === 'bottes') {
        const tige = new THREE.Mesh(new THREE.CylinderGeometry(m.rJambe * 1.30, m.rJambe * 1.45, m.hJambe * 0.42, 18), S);
        g.add(place(tige, x, h + m.hJambe * 0.21, 0));
        const rebord = new THREE.Mesh(new THREE.TorusGeometry(m.rJambe * 1.32, 0.022, 8, 18), mats.chaussureOmbre);
        rebord.rotation.x = Math.PI / 2;
        g.add(place(rebord, x, h + m.hJambe * 0.42, 0));
    }
    if (style === 'sabots') {
        const dessus = balle(largeur * 0.62, S, 16);
        dessus.scale.set(1, 0.85, 1.5);
        g.add(place(dessus, x, hauteur * 0.95, longueur * 0.30));
    }
    if (style === 'ballerines') {
        // Basse et échancrée : c'est l'ÉCHANCRURE qui la fait reconnaître. On
        // la creuse en reposant un morceau de peau sur le dessus du pied.
        const echancrure = balle(largeur * 0.42, mats.peau, 14);
        echancrure.scale.set(1, 0.7, 1.4);
        g.add(place(echancrure, x, hauteur * 1.05, longueur * 0.22));
        const bordure = new THREE.Mesh(new THREE.TorusGeometry(largeur * 0.40, 0.010, 6, 18), S);
        bordure.rotation.x = Math.PI / 2;
        bordure.scale.set(1, 1.45, 1);
        g.add(place(bordure, x, hauteur * 0.98, longueur * 0.22));
    }

    return g;
}

// ─────────────────────────────────────────────────────────────────────────────
// L'ÉQUIPEMENT — ce qu'on porte VRAIMENT en jardinerie.
// ─────────────────────────────────────────────────────────────────────────────
function construireEquipement(look, m, mats) {
    const g = new THREE.Group();
    const style = look.accessoire;
    const demiL = m.lTorse / 2;
    const demiP = m.pTorse / 2;

    if (style === 'tablier') {
        const panneau = boite(m.lTorse * 0.82, m.hTorse * 0.72 + m.hJambe * 0.22, 0.03, mats.tablier);
        g.add(place(panneau, 0, m.yHanche + m.hTorse * 0.28, demiP * 1.05));
        // La bavette, plus étroite, et les bretelles qui montent au cou.
        g.add(place(boite(m.lTorse * 0.50, m.hTorse * 0.30, 0.03, mats.tablier),
            0, m.yEpaule - m.hTorse * 0.20, demiP * 1.05));
        [-1, 1].forEach(function (s) {
            const bretelle = boite(0.045, m.hTorse * 0.34, 0.03, mats.tablier);
            bretelle.rotation.z = s * 0.30;
            g.add(place(bretelle, s * demiL * 0.36, m.yEpaule - m.hTorse * 0.10, demiP * 1.03));
        });
        // La poche ventrale.
        g.add(place(boite(m.lTorse * 0.52, 0.13, 0.035, mats.tablierOmbre),
            0, m.yHanche + m.hTorse * 0.18, demiP * 1.09));
    }

    if (style === 'gilet_fluo') {
        [[0, demiP * 1.10], [0, -demiP * 1.10]].forEach(function (p) {
            g.add(place(boite(m.lTorse * 0.92, m.hTorse * 0.74, 0.03, mats.fluo),
                p[0], m.yHanche + m.hTorse * 0.42, p[1]));
        });
        [-1, 1].forEach(function (s) {
            g.add(place(boite(0.05, m.hTorse * 0.74, m.pTorse * 1.1, mats.fluo),
                s * demiL * 0.92, m.yHanche + m.hTorse * 0.42, 0));
        });
        // Les bandes réfléchissantes.
        [0.28, 0.52].forEach(function (k) {
            [demiP * 1.13, -demiP * 1.13].forEach(function (z) {
                g.add(place(boite(m.lTorse * 0.94, 0.045, 0.01, mats.reflechissant),
                    0, m.yHanche + m.hTorse * k, z));
            });
        });
    }

    if (style === 'badge') {
        const badge = boite(0.11, 0.07, 0.012, mats.blanc);
        g.add(place(badge, demiL * 0.45, m.yEpaule - m.hTorse * 0.30, demiP * 1.06));
        g.add(place(boite(0.11, 0.016, 0.014, mats.vertFami),
            demiL * 0.45, m.yEpaule - m.hTorse * 0.30 - 0.024, demiP * 1.07));
        g.add(place(boite(0.03, 0.03, 0.02, mats.metal),
            demiL * 0.45, m.yEpaule - m.hTorse * 0.30 + 0.045, demiP * 1.05));
    }

    // Le collier est ici et non sur la tête : il repose sur le buste, il doit
    // donc bouger avec lui et non avec le regard.
    const bijoux = look.bijoux || 'aucun';
    if (bijoux === 'collier' || bijoux === 'boucles_collier') {
        const chaine = new THREE.Mesh(new THREE.TorusGeometry(0.088, 0.010, 6, 26), mats.bijou);
        chaine.rotation.x = Math.PI / 2 - 0.22;
        g.add(place(chaine, 0, m.yEpaule - 0.035, demiP * 0.30));
        g.add(place(balle(0.022, mats.bijou, 12), 0, m.yEpaule - 0.105, demiP * 0.92));
    }

    if (style === 'sac_main') {
        // Porté à l'épaule, pendant le long du corps : une anse fine et un
        // corps de sac franchement plus petit qu'une sacoche de travail.
        const anse = new THREE.Mesh(new THREE.TorusGeometry(0.085, 0.011, 6, 20), mats.cuir);
        anse.rotation.y = Math.PI / 2;
        anse.scale.set(1, 1.9, 1);
        g.add(place(anse, demiL * 1.02, m.yEpaule - 0.135, 0));
        const sac = boite(0.075, 0.13, 0.16, mats.cuir);
        g.add(place(sac, demiL * 1.10, m.yHanche + m.hTorse * 0.22, 0));
        g.add(place(boite(0.078, 0.035, 0.165, mats.cuirOmbre),
            demiL * 1.10, m.yHanche + m.hTorse * 0.22 + 0.078, 0));
        g.add(place(balle(0.015, mats.bijou, 10), demiL * 1.14, m.yHanche + m.hTorse * 0.22 + 0.030, 0.082));
    }

    if (style === 'sacoche') {
        const sangle = boite(0.05, m.hTorse * 1.05, 0.035, mats.cuir);
        sangle.rotation.z = 0.42;
        g.add(place(sangle, -demiL * 0.10, m.yHanche + m.hTorse * 0.50, demiP * 1.04));
        const sac = boite(0.20, 0.15, 0.09, mats.cuir);
        g.add(place(sac, demiL * 0.90, m.yHanche + 0.06, demiP * 0.55));
        g.add(place(boite(0.20, 0.05, 0.10, mats.cuirOmbre),
            demiL * 0.90, m.yHanche + 0.125, demiP * 0.57));
    }

    return g;
}

// ─────────────────────────────────────────────────────────────────────────────
// LA PALETTE DE MATIÈRES — créée une fois par personnage, et détruite avec lui.
// ─────────────────────────────────────────────────────────────────────────────
function melange(hex, versBlanc) {
    const c = new THREE.Color(hex);
    const cible = new THREE.Color(versBlanc > 0 ? 0xffffff : 0x000000);
    return c.lerp(cible, Math.abs(versBlanc)).getHex();
}

// ── LES COULEURS DE LA MAISON ───────────────────────────────────────────────
// Relevées sur les photos de la tenue réelle : le gilet vert bordé de vert anis,
// et le t-shirt d'équipe. Elles sont ÉCRITES ICI et pas dans le catalogue parce
// que ce ne sont pas des choix — personne ne choisit la couleur de l'uniforme.
const TENUE = {
    gilet:      '#1B7A3F',
    giletOmbre: '#14602F',
    anis:       '#A9D02B',   // le passepoil et la fermeture éclair
    tshirt:     '#1E9E52'
};

/**
 * Une couleur de poil : « auto » n'est pas une teinte, c'est un renvoi vers les
 * cheveux (voir le catalogue, includes/avatar.php). On le résout ici, au seul
 * endroit qui connaît les deux.
 */
function poil(valeur, cheveux) {
    return (valeur === 'auto' || !valeur) ? cheveux : valeur;
}

function fabriqueMatieres(look) {
    const cheveux = look.couleur_cheveux;
    // Le haut porté : l'uniforme impose sa couleur, les autres vêtements
    // prennent celle qu'on a choisie. Sous le gilet, ce choix habille le
    // t-shirt de dessous — donc `haut` reste la couleur choisie.
    const couleurHaut = (look.haut === 'tshirt_fami') ? TENUE.tshirt : look.couleur_haut;
    // Le maquillage ne repeint les lèvres que s'il est demandé.
    const levres = (look.maquillage && look.maquillage !== 'aucun')
        ? (look.couleur_levres || '#B4655F')
        : '#7E3B3B';

    return {
        peau: matiere(look.peau),
        peauOmbre: matiere(melange(look.peau, -0.12)),
        cheveux: matiere(cheveux, { roughness: 0.95, side: THREE.DoubleSide }),
        cheveuxOmbre: matiere(melange(cheveux, -0.25), { roughness: 0.95, side: THREE.DoubleSide }),
        sourcils: matiere(poil(look.couleur_sourcils, cheveux), { roughness: 0.95 }),
        barbe: matiere(poil(look.couleur_barbe, cheveux), { roughness: 0.95, side: THREE.DoubleSide }),
        barbeOmbre: matiere(melange(poil(look.couleur_barbe, cheveux), -0.25), { roughness: 0.95, side: THREE.DoubleSide }),
        haut: matiere(couleurHaut, { roughness: 0.92 }),
        hautClair: matiere(melange(couleurHaut, 0.22), { roughness: 0.92 }),
        hautOmbre: matiere(melange(couleurHaut, -0.20), { roughness: 0.92 }),

        gilet: matiere(TENUE.gilet, { roughness: 0.93 }),
        giletOmbre: matiere(TENUE.giletOmbre, { roughness: 0.93 }),
        anis: matiere(TENUE.anis, { roughness: 0.75 }),
        // La fenêtre porte-badge du gilet : un plastique translucide.
        fenetre: matiere('#E8EEF0', { roughness: 0.25, metalness: 0.05, transparent: true, opacity: 0.55 }),

        levres: matiere(levres, { roughness: 0.45 }),
        // Le fard : très transparent, sinon on obtient deux taches et non des
        // pommettes.
        fard: matiere(melange(levres, 0.35), { roughness: 0.8, transparent: true, opacity: 0.42 }),
        bijou: matiere(look.couleur_bijoux || '#D9B45B', { roughness: 0.25, metalness: 0.85 }),
        bas: matiere(look.couleur_bas, { roughness: 0.94 }),
        basClair: matiere(melange(look.couleur_bas, 0.30), { roughness: 0.94 }),
        basOmbre: matiere(melange(look.couleur_bas, -0.22), { roughness: 0.94 }),
        basDouble: matiere(look.couleur_bas, { roughness: 0.94, side: THREE.DoubleSide }),
        chaussure: matiere(look.couleur_chaussures, { roughness: 0.75 }),
        chaussureOmbre: matiere(melange(look.couleur_chaussures, -0.25), { roughness: 0.75 }),
        couvreChef: matiere(look.couleur_couvre_chef, { roughness: 0.9, side: THREE.DoubleSide }),
        iris: matiere(look.yeux, { roughness: 0.35 }),

        blanc: matiere('#F5F5F2', { roughness: 0.6 }),
        noir: matiere('#1A1A1A', { roughness: 0.5 }),
        langue: matiere('#C96A6A', { roughness: 0.6 }),
        monture: matiere('#3A3A3E', { roughness: 0.4, metalness: 0.35 }),
        verre: matiere('#BFD8EA', { roughness: 0.15, metalness: 0.1, transparent: true, opacity: 0.35 }),
        verreSombre: matiere('#20242A', { roughness: 0.1, metalness: 0.3, transparent: true, opacity: 0.72 }),
        metal: matiere('#B9BEC4', { roughness: 0.3, metalness: 0.7 }),
        ruban: matiere('#3A3A3E', { roughness: 0.8 }),
        gant: matiere('#4A8B5C', { roughness: 0.9 }),
        gantOmbre: matiere('#2D5A37', { roughness: 0.9 }),
        tablier: matiere('#3E4A55', { roughness: 0.95, side: THREE.DoubleSide }),
        tablierOmbre: matiere('#2C353D', { roughness: 0.95 }),
        fluo: matiere('#D8E830', { roughness: 0.85, side: THREE.DoubleSide }),
        reflechissant: matiere('#DDE3E8', { roughness: 0.2, metalness: 0.5 }),
        cuir: matiere('#6B4A2E', { roughness: 0.8 }),
        cuirOmbre: matiere('#54381F', { roughness: 0.8 }),
        vertFami: matiere('#2D5A37', { roughness: 0.8 })
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// LE PERSONNAGE COMPLET.
// ─────────────────────────────────────────────────────────────────────────────
function construirePersonnage(look) {
    const m = mesures(look);
    const mats = fabriqueMatieres(look);
    const racine = new THREE.Group();

    racine.add(construireJambes(look, m, mats));
    racine.add(construireTorse(look, m, mats));

    const brasG = construireBras(-1, look, m, mats);
    const brasD = construireBras(1, look, m, mats);
    racine.add(brasG.epaule);
    racine.add(brasD.epaule);

    // La pose est posée TOUT DE SUITE, avant même la première image : sans ça,
    // la vignette prise dans la foulée attraperait un personnage encore au
    // garde-à-vous.
    const pose = poseDe(look);
    brasG.epaule.rotation.set(pose.g.ep[0], pose.g.ep[1], pose.g.ep[2]);
    brasG.coude.rotation.set(pose.g.co[0], pose.g.co[1], pose.g.co[2]);
    brasD.epaule.rotation.set(pose.d.ep[0], pose.d.ep[1], pose.d.ep[2]);
    brasD.coude.rotation.set(pose.d.co[0], pose.d.co[1], pose.d.co[2]);

    racine.add(construireEquipement(look, m, mats));

    // La tête est un groupe à part : c'est elle qui bouge (respiration, regard,
    // clignement), et tout ce qui est posé dessus doit la suivre.
    const tete = construireTete(look, m, mats);
    const couvreLeCrane = (look.couvre_chef === 'casquette' || look.couvre_chef === 'bonnet' || look.couvre_chef === 'chapeau');
    tete.groupe.add(construireCheveux(look, m, mats, couvreLeCrane));
    tete.groupe.add(construireBarbe(look, m, mats));
    tete.groupe.add(construireLunettes(look, m, mats));
    tete.groupe.add(construireCouvreChef(look, m, mats));
    place(tete.groupe, 0, m.yTete, 0);
    tete.groupe.rotation.set(pose.tete[0], pose.tete[1], pose.tete[2]);
    racine.add(tete.groupe);

    return {
        racine: racine,
        mesures: m,
        matieres: mats,
        tete: tete.groupe,
        yeux: tete.yeux,
        brasG: brasG,
        brasD: brasD,
        pose: pose
    };
}

/** Rend au ramasse-miettes tout ce qu'une scène 3D ne libère pas seule. */
function detruireObjet(objet) {
    objet.traverse(function (n) {
        if (n.geometry) { n.geometry.dispose(); }
        if (n.material) {
            (Array.isArray(n.material) ? n.material : [n.material]).forEach(function (mt) { mt.dispose(); });
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
// LA VUE — la scène, la lumière, la souris, la boucle d'animation.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Installe un avatar dans un élément de la page.
 *
 * @param {HTMLElement} hote     le conteneur (il est vidé et rempli d'un canvas)
 * @param {Object}      look     couleurs déjà résolues (voir construireLook)
 * @param {Object}      options  { interactif, rotationAuto, cadrage, surErreur }
 * @returns {Object} { applique, instantane, detruit, canvas }  ou null si la 3D
 *                   n'est pas disponible.
 */
export function creerAvatar(hote, look, options) {
    const o = Object.assign({
        interactif: true,     // on peut le faire tourner à la souris
        rotationAuto: true,   // il tourne doucement tout seul quand on le laisse
        cadrage: 'entier',    // 'entier' ou 'buste'
        anime: true,
        surErreur: null
    }, options || {});

    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({
            antialias: true,
            alpha: true,
            // ⚠️ INDISPENSABLE POUR LA VIGNETTE. Sans ça, lire le canvas après
            // le rendu renvoie une image vide sur une partie des machines : le
            // navigateur a le droit de jeter le tampon dès qu'il a affiché.
            preserveDrawingBuffer: true
        });
    } catch (err) {
        if (o.surErreur) { o.surErreur(err); }
        return null;
    }

    renderer.setClearColor(0x000000, 0);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.domElement.style.width = '100%';
    renderer.domElement.style.height = '100%';
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.touchAction = 'none';
    hote.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 100);

    // ── LA LUMIÈRE ──────────────────────────────────────────────────────────
    // Trois sources, et c'est le minimum pour qu'un personnage mat ne ressemble
    // pas à un carton : une lumière de ciel (le volume), une principale (les
    // ombres propres), une de dos (le contour, qui le décolle du fond).
    scene.add(new THREE.HemisphereLight(0xffffff, 0x8fa89a, 2.0));
    const principale = new THREE.DirectionalLight(0xffffff, 2.3);
    principale.position.set(2.5, 4, 3.5);
    scene.add(principale);
    const contre = new THREE.DirectionalLight(0xd8ecdd, 1.0);
    contre.position.set(-3, 2, -3);
    scene.add(contre);

    // ── LE SOL ──────────────────────────────────────────────────────────────
    // Pas une vraie ombre portée (coûteuse, et inutile ici) : un disque dégradé
    // sous les pieds. C'est lui qui empêche le personnage de « flotter ».
    const ombre = new THREE.Mesh(
        new THREE.CircleGeometry(0.42, 32),
        new THREE.MeshBasicMaterial({
            map: textureOmbre(), transparent: true, opacity: 0.34, depthWrite: false
        })
    );
    ombre.rotation.x = -Math.PI / 2;
    ombre.position.y = 0.002;
    scene.add(ombre);

    const pivot = new THREE.Group();     // ce qui tourne
    scene.add(pivot);

    let personnage = null;
    let hauteur = 2;

    function monte(nouveauLook) {
        if (personnage) {
            pivot.remove(personnage.racine);
            detruireObjet(personnage.racine);
            Object.keys(personnage.matieres).forEach(function (k) { personnage.matieres[k].dispose(); });
        }
        personnage = construirePersonnage(nouveauLook);
        pivot.add(personnage.racine);
        hauteur = personnage.mesures.hauteurTotale;
        ombre.scale.setScalar(personnage.mesures.c);
        cadre();
    }

    /**
     * Place la caméra pour que le personnage tienne dans le cadre.
     *
     * ⚠️ ON CALCULE LA DISTANCE SUR LES DEUX AXES, pas seulement sur la
     * hauteur. Le champ de vision de three.js est VERTICAL : ne caler que la
     * hauteur marche sur un écran large et coupe les bras dès que le cadre
     * devient étroit — exactement ce qui arrive sur un téléphone. On prend la
     * plus grande des deux distances, donc celle qui laisse tout entrer.
     */
    function cadre() {
        const buste = (o.cadrage === 'buste');
        const cible = buste ? (personnage ? personnage.mesures.yTete - 0.06 : 1.6) : hauteur * 0.50;
        const hautACouvrir = buste ? 0.78 : hauteur * 1.06;
        const largeACouvrir = (buste ? 0.78 : Math.max(0.90, (personnage ? personnage.mesures.lTorse : 0.5) * 2.6));
        const demiAngle = Math.tan((camera.fov * Math.PI / 180) / 2);
        const distHaut = (hautACouvrir / 2) / demiAngle;
        const distLarge = (largeACouvrir / 2) / (demiAngle * Math.max(0.2, camera.aspect));
        const dist = Math.max(distHaut, distLarge);
        camera.position.set(0, cible, dist * (buste ? 1.05 : 1.0) * zoom);
        camera.lookAt(0, cible, 0);
    }

    // ── LA TAILLE ───────────────────────────────────────────────────────────
    function redimensionne() {
        const l = Math.max(1, hote.clientWidth);
        const h = Math.max(1, hote.clientHeight);
        renderer.setSize(l, h, false);
        camera.aspect = l / h;
        camera.updateProjectionMatrix();
        cadre();   // c'est lui qui tient compte de la largeur (voir plus haut)
    }

    const observateur = (typeof ResizeObserver !== 'undefined')
        ? new ResizeObserver(redimensionne) : null;
    if (observateur) { observateur.observe(hote); }
    window.addEventListener('resize', redimensionne);

    // ── LA SOURIS ───────────────────────────────────────────────────────────
    let zoom = 1;
    let rotationCible = 0;
    let inclinaison = 0;
    let attrape = false;
    let dernierX = 0, dernierY = 0;
    let reposDepuis = 0;

    function surAppui(ev) {
        attrape = true;
        dernierX = ev.clientX;
        dernierY = ev.clientY;
        renderer.domElement.setPointerCapture && renderer.domElement.setPointerCapture(ev.pointerId);
        renderer.domElement.style.cursor = 'grabbing';
    }
    function surGlisse(ev) {
        if (!attrape) { return; }
        rotationCible += (ev.clientX - dernierX) * 0.011;
        inclinaison = Math.max(-0.35, Math.min(0.35, inclinaison + (ev.clientY - dernierY) * 0.004));
        dernierX = ev.clientX;
        dernierY = ev.clientY;
        reposDepuis = 0;
    }
    function surLache(ev) {
        attrape = false;
        renderer.domElement.releasePointerCapture && ev.pointerId !== undefined
            && renderer.domElement.releasePointerCapture(ev.pointerId);
        renderer.domElement.style.cursor = 'grab';
    }
    if (o.interactif) {
        renderer.domElement.style.cursor = 'grab';
        renderer.domElement.addEventListener('pointerdown', surAppui);
        window.addEventListener('pointermove', surGlisse);
        window.addEventListener('pointerup', surLache);
        renderer.domElement.addEventListener('wheel', function (ev) {
            ev.preventDefault();
            zoom = Math.max(0.55, Math.min(1.6, zoom + (ev.deltaY > 0 ? 0.08 : -0.08)));
            cadre();
        }, { passive: false });
    }

    // ── LA BOUCLE ───────────────────────────────────────────────────────────
    let vivant = true;
    let horloge = new THREE.Clock();
    let prochainClignement = 2.5;
    let tempsClignement = -1;

    function boucle() {
        if (!vivant) { return; }
        requestAnimationFrame(boucle);
        const dt = Math.min(0.05, horloge.getDelta());
        const t = horloge.elapsedTime;

        if (o.rotationAuto && !attrape) {
            reposDepuis += dt;
            // On ne repart pas à tourner à la seconde où l'on lâche : c'est
            // désagréable quand on cherche à regarder un détail.
            if (reposDepuis > 1.5) { rotationCible += dt * 0.30; }
        }
        pivot.rotation.y += (rotationCible - pivot.rotation.y) * Math.min(1, dt * 9);
        pivot.rotation.x += (inclinaison * 0.35 - pivot.rotation.x) * Math.min(1, dt * 9);

        if (personnage && o.anime) {
            // Respiration + balancement : trois lignes qui font toute la
            // différence entre « une statue » et « quelqu'un ».
            //
            // ⚠️ TOUT S'AJOUTE À LA POSE, rien ne la remplace. Écrire
            // `rotation.z = …` écraserait des bras croisés à la première image :
            // le balancement est une petite variation AUTOUR de la position
            // choisie, et il s'efface presque complètement quand la pose est
            // tenue (voir `balance`).
            const souffle = Math.sin(t * 1.6);
            const P = personnage.pose;
            const b = P.balance;

            personnage.tete.position.y = personnage.mesures.yTete + souffle * 0.008;
            personnage.tete.rotation.x = P.tete[0] + Math.sin(t * 0.9 + 1) * 0.025;
            personnage.tete.rotation.y = P.tete[1];
            personnage.tete.rotation.z = P.tete[2] + Math.sin(t * 0.7) * 0.03;

            personnage.brasG.epaule.rotation.x = P.g.ep[0] + Math.sin(t * 1.1) * 0.10 * b;
            personnage.brasD.epaule.rotation.x = P.d.ep[0] + Math.sin(t * 1.1 + Math.PI) * 0.10 * b;
            personnage.brasG.epaule.rotation.z = P.g.ep[2] - Math.abs(souffle) * 0.02 * b;
            personnage.brasD.epaule.rotation.z = P.d.ep[2] + Math.abs(souffle) * 0.02 * b;

            // Le coucou : c'est l'AVANT-BRAS qui va et vient, pas l'épaule —
            // agiter tout le bras donnerait un moulin, pas un salut.
            if (P.agite) {
                const bras = (P.agite === 'd') ? personnage.brasD : personnage.brasG;
                const base = (P.agite === 'd') ? P.d.co[2] : P.g.co[2];
                bras.coude.rotation.z = base + Math.sin(t * 4.2) * 0.34;
            }

            // Le clignement, à intervalle irrégulier mais calculé : un rythme
            // parfaitement régulier se remarque et met mal à l'aise.
            if (tempsClignement < 0 && t > prochainClignement) {
                tempsClignement = 0;
            }
            if (tempsClignement >= 0) {
                tempsClignement += dt;
                const k = tempsClignement / 0.13;
                const ferme = (k < 1) ? Math.sin(k * Math.PI) : 0;
                personnage.yeux.forEach(function (oeil) { oeil.scale.y = 1 - ferme * 0.92; });
                if (k >= 1) {
                    tempsClignement = -1;
                    prochainClignement = t + 2.2 + (Math.sin(t * 7.3) + 1) * 1.4;
                    personnage.yeux.forEach(function (oeil) { oeil.scale.y = 1; });
                }
            }
        }

        renderer.render(scene, camera);
    }

    monte(look);
    redimensionne();
    boucle();

    return {
        canvas: renderer.domElement,

        /** Change la tenue sans rien casser : on refabrique, on ne bricole pas. */
        applique: function (nouveauLook) { monte(nouveauLook); },

        /** Remet le personnage de face (utilisé avant une capture). */
        recentre: function () { rotationCible = 0; inclinaison = 0; },

        /**
         * Arrête (ou relance) la rotation automatique.
         *
         * ⚠️ ELLE NE BLOQUE PAS LA SOURIS, et c'est voulu : « bloquer » veut
         * dire « arrête de tourner tout seul pendant que je regarde », pas
         * « je ne peux plus le tourner ». On garde donc la main, on perd
         * seulement le manège.
         *
         * @param {boolean} actif
         * @returns {boolean} l'état après l'appel
         */
        rotationAuto: function (actif) {
            o.rotationAuto = !!actif;
            // On repart du repos : sans ça, relancer la rotation la fait
            // démarrer en sursaut, le compteur d'inactivité ayant couru pendant
            // tout le temps où elle était figée.
            reposDepuis = 0;
            return o.rotationAuto;
        },

        /**
         * La vignette PNG, fond transparent.
         *
         * On rend hors-écran, de face, à la taille demandée, puis on remet la
         * vue comme elle était : l'utilisateur ne doit pas voir son personnage
         * sauter pendant l'enregistrement.
         */
        instantane: function (taille) {
            const px = taille || 512;
            const memeL = renderer.domElement.width;
            const memeH = renderer.domElement.height;
            const memeAspect = camera.aspect;
            const memeRotation = pivot.rotation.y;
            const memeInclinaison = pivot.rotation.x;
            const memeZoom = zoom;
            const memePixel = renderer.getPixelRatio();

            pivot.rotation.y = 0;
            pivot.rotation.x = 0;
            zoom = 1;
            renderer.setPixelRatio(1);
            renderer.setSize(px, px, false);
            camera.aspect = 1;
            camera.updateProjectionMatrix();
            cadre();
            renderer.render(scene, camera);
            const donnee = renderer.domElement.toDataURL('image/png');

            pivot.rotation.y = memeRotation;
            pivot.rotation.x = memeInclinaison;
            zoom = memeZoom;
            renderer.setPixelRatio(memePixel);
            renderer.setSize(memeL / memePixel, memeH / memePixel, false);
            camera.aspect = memeAspect;
            camera.updateProjectionMatrix();
            cadre();
            renderer.render(scene, camera);

            return donnee;
        },

        detruit: function () {
            vivant = false;
            if (observateur) { observateur.disconnect(); }
            window.removeEventListener('resize', redimensionne);
            window.removeEventListener('pointermove', surGlisse);
            window.removeEventListener('pointerup', surLache);
            if (personnage) { detruireObjet(personnage.racine); }
            detruireObjet(ombre);
            renderer.dispose();
            if (renderer.domElement.parentNode) {
                renderer.domElement.parentNode.removeChild(renderer.domElement);
            }
        }
    };
}

/** Le dégradé radial qui sert d'ombre au sol. Fabriqué une fois. */
let _textureOmbre = null;
function textureOmbre() {
    if (_textureOmbre) { return _textureOmbre; }
    const c = document.createElement('canvas');
    c.width = c.height = 128;
    const ctx = c.getContext('2d');
    const grad = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
    grad.addColorStop(0, 'rgba(20,40,25,0.85)');
    grad.addColorStop(0.55, 'rgba(20,40,25,0.35)');
    grad.addColorStop(1, 'rgba(20,40,25,0)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, 128, 128);
    _textureOmbre = new THREE.CanvasTexture(c);
    return _textureOmbre;
}
