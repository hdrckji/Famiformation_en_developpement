<?php
// ============================================================
// FAMICARD — L'ATELIER : ON FABRIQUE SON PERSONNAGE.
//
// ── CE QUE CET ÉCRAN DOIT RÉUSSIR ───────────────────────────────────────────
// Qu'on s'y reconnaisse en moins d'une minute, sans mode d'emploi. D'où trois
// partis pris :
//   • le personnage change À L'INSTANT où l'on clique — aucun bouton
//     « aperçu », aucune attente : on essaie, on voit, on garde ou non ;
//   • rien n'est enregistré tant qu'on n'a pas dit « Enregistrer ». On peut
//     donc tout essayer sans rien casser, et repartir comme on est arrivé ;
//   • « Au hasard » existe parce que la page blanche décourage : un premier
//     personnage tiré au sort donne quelque chose à corriger plutôt que
//     quelque chose à inventer.
//
// ── L'ENREGISTREMENT ────────────────────────────────────────────────────────
// On envoie DEUX choses : la configuration (la source de vérité) et une
// vignette PNG rendue par le navigateur (le dérivé, pour les petits affichages
// — voir includes/avatar.php). Le serveur ne fait confiance ni à l'une ni à
// l'autre : la configuration est repassée au catalogue, la vignette est
// re-encodée.
//
// ⚠️ UNE AGENCE N'A PAS D'AVATAR. Une société extérieure n'a ni coupe de
// cheveux ni tenue de travail : lui ouvrir cet atelier n'aurait pas de sens.
// Même règle que pour la carte (voir includes/agence.php).
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/agence.php';
require_once __DIR__ . '/includes/avatar.php';

$moi = famicardExigeConnexion($db);
$moiId = (int) $moi['id'];
$estAgence = famicardEstCompteAgence($moi['role'] ?? '');

// La table est créée à la première visite de qui que ce soit : un collaborateur
// ne doit pas attendre qu'un administrateur passe pour que sa page marche.
$tablePrete = $estAgence ? true : famicardAssureAvatars($db);

// ─────────────────────────────────────────────────────────────────────────────
// L'ENREGISTREMENT (appelé en arrière-plan par la page, jamais à la main).
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    requireValidCSRF();

    if ($estAgence) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => "Un compte agence n'a pas d'avatar."]);
        exit;
    }

    $action = (string) ($_POST['action'] ?? 'enregistrer');

    if ($action === 'supprimer') {
        $ok = famicardSupprimeAvatar($db, $moiId);
        echo json_encode([
            'ok' => $ok,
            'message' => $ok ? 'Ton avatar a été supprimé.' : "La suppression n'a pas abouti.",
        ]);
        exit;
    }

    $config = json_decode((string) ($_POST['config'] ?? ''), true);
    $erreur = '';
    $ok = famicardEnregistreAvatar($db, $moiId, is_array($config) ? $config : [], (string) ($_POST['image'] ?? ''), $erreur);

    echo json_encode([
        'ok'      => $ok,
        'message' => $ok ? 'Ton avatar est enregistré.' : ($erreur ?: "L'enregistrement n'a pas abouti."),
        // On renvoie l'adresse de la vignette avec son jeton de version : la
        // page peut ainsi rafraîchir les petits ronds sans être rechargée.
        'image_url' => $ok ? famicardAvatarImageUrl($moiId, (string) time()) : '',
    ]);
    exit;
}

$avatar    = famicardAvatarDe($db, $moiId);
$catalogue = famicardAvatarCatalogue();
$champs    = famicardAvatarChamps();

$prenom = trim((string) ($moi['prenom'] ?? ''));
if ($prenom === '') {
    $prenom = (string) ($moi['identifiant'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon avatar - Famicard</title>
<link rel="shortcut icon" type="image/x-icon" href="<?= e(famicardSiteUrl('favicon.ico')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; }

    /* ⚠️ L'ATTRIBUT `hidden` NE MASQUE RIEN dès qu'une règle d'auteur pose un
       `display` sur l'élément : `[hidden] { display: none }` vient de la feuille
       du NAVIGATEUR, et n'importe quelle règle écrite ici la bat.
       C'est exactement ce qui a envoyé la scène 3D SOUS la carte : le message de
       secours « pas de 3D », jamais masqué malgré son `hidden`, occupait la
       hauteur entière du cadre et poussait le canvas dehors. On remet donc la
       règle de notre côté, une fois pour toute la page. */
    [hidden] { display: none !important; }

    body { font-family: 'Open Sans', sans-serif; background: url('<?= e(famicardSiteUrl('background.jpg')) ?>') no-repeat center center fixed; background-size: cover; margin: 0; padding: 0 0 120px; color: #333; }
    .top-nav { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; }
    .pill { background: rgba(255,255,255,.92); padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 10px rgba(0,0,0,.1); text-decoration: none; color: #2d5a37; font-weight: 700; font-size: .9rem; }
    .wrap { max-width: 1060px; margin: 0 auto; padding: 0 16px; }

    .atelier { display: grid; grid-template-columns: minmax(280px, 420px) 1fr; gap: 20px; align-items: start; }
    @media (max-width: 860px) { .atelier { grid-template-columns: 1fr; } }

    /* LA SCÈNE — collante sur grand écran : on garde son personnage sous les
       yeux pendant qu'on fait défiler les options. */
    .scene-carte { background: rgba(255,255,255,.95); border-radius: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; position: sticky; top: 14px; }
    @media (max-width: 860px) { .scene-carte { position: static; } }
    .scene-tete { padding: 16px 20px 6px; }
    .scene-tete h1 { margin: 0; font-size: 1.25rem; font-weight: 800; color: #2d5a37; }
    .scene-tete p { margin: 4px 0 0; font-size: .84rem; color: #666; }
    /* La scène est un CADRE FERMÉ : le canvas et le message de secours y sont
       posés l'un SUR l'autre (position absolue), pas l'un APRÈS l'autre. Deux
       éléments qui s'empilent dans le flux additionnent leurs hauteurs, et le
       second déborde — c'est ce qui s'était produit. `overflow: hidden` est la
       deuxième ceinture : quoi qu'on ajoute ici demain, rien ne sortira du
       cadre. */
    .scene { height: 420px; background: linear-gradient(170deg, #eaf4ec 0%, #d6e8db 60%, #c7ded0 100%); position: relative; overflow: hidden; }
    @media (max-width: 860px) { .scene { height: 340px; } }
    .scene > canvas { position: absolute; inset: 0; }
    .scene-vide { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 24px; text-align: center; color: #6a6a6a; font-size: .9rem; line-height: 1.6; }
    .scene-outils { display: flex; gap: 8px; flex-wrap: wrap; padding: 12px 16px; background: #f7faf8; border-top: 1px solid #eee; }

    .mini { border: 1px solid #d3e0d7; background: #fff; color: #2d5a37; border-radius: 30px; padding: 8px 15px; font-family: inherit; font-weight: 700; font-size: .82rem; cursor: pointer; }
    .mini:hover { background: #eef6f0; }
    /* Le verrou enclenché se voit : un bouton bascule qui garde l'air d'un
       bouton ordinaire laisse deviner son état, donc le fait oublier. */
    .mini[aria-pressed="true"] { background: #2d5a37; color: #fff; border-color: #2d5a37; }

    /* LES OPTIONS */
    .options { background: rgba(255,255,255,.95); border-radius: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.15); overflow: hidden; }
    .onglets { display: flex; gap: 4px; overflow-x: auto; padding: 10px 12px; background: linear-gradient(135deg, #2d5a37, #4a8b5c); }
    .onglet { flex: 0 0 auto; border: 0; background: rgba(255,255,255,.16); color: #fff; border-radius: 30px; padding: 9px 16px; font-family: inherit; font-weight: 700; font-size: .85rem; cursor: pointer; white-space: nowrap; }
    .onglet[aria-selected="true"] { background: #fff; color: #2d5a37; }

    .panneau { padding: 20px 22px; }
    .panneau[hidden] { display: none; }
    .champ { margin-bottom: 22px; }
    .champ:last-child { margin-bottom: 4px; }
    .champ h3 { margin: 0 0 10px; font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; color: #2d5a37; }

    .choix { display: flex; flex-wrap: wrap; gap: 8px; }
    .choix button { border: 2px solid #e2eae4; background: #fff; color: #444; border-radius: 12px; padding: 9px 14px; font-family: inherit; font-size: .87rem; font-weight: 600; cursor: pointer; transition: border-color .12s, background .12s; }
    .choix button:hover { border-color: #9dc4a8; }
    .choix button[aria-pressed="true"] { border-color: #2d5a37; background: #eef6f0; color: #2d5a37; font-weight: 800; }

    /* Une pastille de couleur se choisit à l'œil : le nom vient en infobulle. */
    .palette { display: flex; flex-wrap: wrap; gap: 10px; }
    .palette button { width: 40px; height: 40px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 1px #d8ded9, 0 3px 6px rgba(0,0,0,.12); cursor: pointer; padding: 0; }
    .palette button[aria-pressed="true"] { box-shadow: 0 0 0 3px #2d5a37, 0 3px 8px rgba(0,0,0,.2); }

    /* LA BARRE D'ENREGISTREMENT — toujours visible : le geste qui compte ne
       doit pas se mériter en faisant défiler la page. */
    .barre { position: fixed; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,.97); border-top: 1px solid #e0e6e2; box-shadow: 0 -6px 20px rgba(0,0,0,.10); padding: 12px 16px; z-index: 40; }
    .barre-in { max-width: 1060px; margin: 0 auto; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .bouton { border: 0; border-radius: 30px; padding: 13px 26px; font-family: inherit; font-weight: 800; font-size: .93rem; cursor: pointer; text-decoration: none; display: inline-block; }
    .bouton-plein { background: #2d5a37; color: #fff; }
    .bouton-plein:disabled { background: #a9bfae; cursor: default; }
    .bouton-vide { background: #fff; color: #2d5a37; border: 1px solid #d3e0d7; }
    .bouton-danger { background: #fff; color: #a83b33; border: 1px solid #e8cfcd; }
    .etat { font-size: .87rem; color: #666; margin-left: auto; }
    .etat.ok { color: #1E7A46; font-weight: 700; }
    .etat.ko { color: #a83b33; font-weight: 700; }

    .encart { background: rgba(255,255,255,.95); border-left: 5px solid #2d5a37; border-radius: 14px; padding: 16px 20px; margin-top: 22px; font-size: .9rem; line-height: 1.55; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
    .encart h3 { margin: 0 0 8px; font-size: .95rem; color: #2d5a37; }
    .alerte { background: #fff8e6; border-left: 5px solid #E9A93C; border-radius: 12px; color: #7a4a11; padding: 14px 18px; font-size: .9rem; line-height: 1.55; margin-bottom: 18px; }
</style>
</head>
<body>

<div class="top-nav">
    <a class="pill" href="index.php">&larr; Accueil</a>
    <a class="pill" href="fiche.php">Ma fiche</a>
</div>

<div class="wrap">

<?php if ($estAgence): ?>

    <?php // Une société n'a pas de coupe de cheveux. On le dit simplement,
          // plutôt que d'afficher un atelier qui n'enregistrerait rien. ?>
    <div class="alerte">
        <b>L'avatar est réservé aux collaborateurs.</b><br>
        Un compte agence représente une société, pas une personne : il n'a ni tenue ni visage à choisir.
        <a href="index.php">Retour à l'accueil</a>
    </div>

<?php elseif (!$tablePrete): ?>

    <div class="alerte">
        <b>L'atelier n'est pas encore disponible.</b><br>
        Le stockage des avatars n'a pas pu être préparé sur ce serveur. Signale-le, c'est un réglage de base de données —
        rien n'est perdu de ton côté.
    </div>

<?php else: ?>

    <div class="atelier">

        <div class="scene-carte">
            <div class="scene-tete">
                <h1>Ton avatar</h1>
                <p>Fais-le tourner avec la souris. Rien n'est gardé tant que tu n'as pas enregistré.</p>
            </div>
            <div class="scene" id="scene">
                <div class="scene-vide" id="sceneVide" hidden>
                    Ton navigateur n'affiche pas la 3D.<br>
                    Tu peux quand même choisir ta tenue : elle sera visible depuis un autre appareil.
                </div>
            </div>
            <div class="scene-outils">
                <button type="button" class="mini" id="btnFace">🎯 De face</button>
                <?php // Le verrou ARRÊTE le manège, il ne bloque pas la souris :
                      // on veut pouvoir regarder un détail sans que le
                      // personnage s'en aille, pas s'interdire de le tourner. ?>
                <button type="button" class="mini" id="btnFiger" aria-pressed="false">⏸️ Figer la rotation</button>
                <button type="button" class="mini" id="btnHasard">🎲 Au hasard</button>
                <button type="button" class="mini" id="btnAnnuler">↩️ Repartir de zéro</button>
            </div>
        </div>

        <div class="options">
            <div class="onglets" role="tablist">
                <?php $premier = true; foreach ($catalogue as $cle => $onglet): ?>
                    <button type="button" class="onglet" role="tab"
                            data-onglet="<?= e($cle) ?>"
                            aria-selected="<?= $premier ? 'true' : 'false' ?>">
                        <?= e($onglet['icone']) ?> <?= e($onglet['libelle']) ?>
                    </button>
                <?php $premier = false; endforeach; ?>
            </div>

            <?php $premier = true; foreach ($catalogue as $cleOnglet => $onglet): ?>
                <div class="panneau" data-panneau="<?= e($cleOnglet) ?>" <?= $premier ? '' : 'hidden' ?>>
                    <?php foreach ($onglet['champs'] as $cleChamp => $champ): ?>
                        <div class="champ">
                            <h3><?= e($champ['libelle']) ?></h3>
                            <div class="<?= $champ['type'] === 'couleur' ? 'palette' : 'choix' ?>" data-champ="<?= e($cleChamp) ?>">
                                <?php foreach ($champ['valeurs'] as $cleValeur => $valeur): ?>
                                    <?php if ($champ['type'] === 'couleur'): ?>
                                        <button type="button" data-valeur="<?= e($cleValeur) ?>"
                                                aria-pressed="false"
                                                title="<?= e($valeur['libelle']) ?>"
                                                aria-label="<?= e($valeur['libelle']) ?>"
                                                style="background: <?= e($valeur['hex']) ?>;"></button>
                                    <?php else: ?>
                                        <button type="button" data-valeur="<?= e($cleValeur) ?>" aria-pressed="false">
                                            <?= e($valeur['libelle']) ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php $premier = false; endforeach; ?>
        </div>

    </div>

    <div class="encart">
        <h3>🪪 Ton avatar et ta photo</h3>
        L'avatar <b>ne remplace pas ta photo</b> : ta photo reste ce qui t'identifie sur ta fiche et sur ton badge.
        L'avatar, lui, est ta figurine — il t'accompagnera bientôt dans FamiFormation.
        Tu peux le supprimer quand tu veux, sans rien perdre d'autre.
    </div>

    <div class="barre">
        <div class="barre-in">
            <button type="button" class="bouton bouton-plein" id="btnEnregistrer" disabled>💾 Enregistrer</button>
            <?php if ($avatar['existe']): ?>
                <button type="button" class="bouton bouton-danger" id="btnSupprimer">🗑️ Supprimer mon avatar</button>
            <?php endif; ?>
            <a class="bouton bouton-vide" href="index.php">Retour</a>
            <span class="etat" id="etat"></span>
        </div>
    </div>

<?php endif; ?>

</div>

<?php if (!$estAgence && $tablePrete): ?>
<script type="module">
// ─────────────────────────────────────────────────────────────────────────────
// L'ATELIER, CÔTÉ NAVIGATEUR.
//
// Le CATALOGUE arrive tel quel depuis PHP : les libellés, les couleurs et les
// valeurs autorisées ne sont écrits qu'une fois, en PHP. Ce script ne connaît
// aucun nom de coupe ni aucun code couleur — il ne fait que relayer des clés.
// ─────────────────────────────────────────────────────────────────────────────
import { creerAvatar, construireLook } from './assets/avatar3d.js';

const CHAMPS   = <?= json_encode($champs, JSON_UNESCAPED_UNICODE) ?>;
const DEPART   = <?= json_encode($avatar['config'], JSON_UNESCAPED_UNICODE) ?>;
const JETON    = <?= json_encode(getCSRFToken()) ?>;
const AVAIT_UN = <?= $avatar['existe'] ? 'true' : 'false' ?>;

let config = Object.assign({}, DEPART);
let enregistre = JSON.stringify(config);

const scene   = document.getElementById('scene');
const etat    = document.getElementById('etat');
const btnSave = document.getElementById('btnEnregistrer');

// ── LA VUE 3D ───────────────────────────────────────────────────────────────
// Elle peut ne pas s'ouvrir (vieux poste, 3D désactivée). Ce n'est pas une
// erreur bloquante : on garde les boutons de choix, la personne peut composer
// sa tenue et la verra depuis un autre appareil. Un écran vide sans explication
// serait bien pire.
let vue = creerAvatar(scene, construireLook(config, CHAMPS), {
    interactif: true,
    rotationAuto: true,
    surErreur: function () {
        document.getElementById('sceneVide').removeAttribute('hidden');
    }
});
if (!vue) {
    document.getElementById('sceneVide').removeAttribute('hidden');
}

function redessine() {
    if (vue) { vue.applique(construireLook(config, CHAMPS)); }
    marqueChoix();
    const change = (JSON.stringify(config) !== enregistre);
    btnSave.disabled = !change;
    if (change) { dis('', ''); }
}

/** Fait ressortir, dans chaque liste, ce qui est actuellement porté. */
function marqueChoix() {
    document.querySelectorAll('[data-champ]').forEach(function (groupe) {
        const champ = groupe.getAttribute('data-champ');
        groupe.querySelectorAll('button[data-valeur]').forEach(function (b) {
            b.setAttribute('aria-pressed', (config[champ] === b.getAttribute('data-valeur')) ? 'true' : 'false');
        });
    });
}

function dis(texte, genre) {
    etat.textContent = texte;
    etat.className = 'etat' + (genre ? ' ' + genre : '');
}

// ── LES CHOIX ───────────────────────────────────────────────────────────────
document.querySelectorAll('[data-champ]').forEach(function (groupe) {
    const champ = groupe.getAttribute('data-champ');
    groupe.addEventListener('click', function (ev) {
        const b = ev.target.closest('button[data-valeur]');
        if (!b) { return; }
        config[champ] = b.getAttribute('data-valeur');
        redessine();
    });
});

// ── LES ONGLETS ─────────────────────────────────────────────────────────────
document.querySelectorAll('.onglet').forEach(function (onglet) {
    onglet.addEventListener('click', function () {
        const cible = onglet.getAttribute('data-onglet');
        document.querySelectorAll('.onglet').forEach(function (o) {
            o.setAttribute('aria-selected', o === onglet ? 'true' : 'false');
        });
        document.querySelectorAll('[data-panneau]').forEach(function (p) {
            if (p.getAttribute('data-panneau') === cible) { p.removeAttribute('hidden'); }
            else { p.setAttribute('hidden', ''); }
        });
    });
});

// ── LES OUTILS DE LA SCÈNE ──────────────────────────────────────────────────
document.getElementById('btnFace').addEventListener('click', function () {
    if (vue) { vue.recentre(); }
});

// ── LE VERROU DE ROTATION ───────────────────────────────────────────────────
// Le choix est RETENU d'une visite à l'autre : quelqu'un que le manège gêne est
// gêné à chaque fois, et redemander le verrou à chaque ouverture serait le lui
// faire redire. C'est une préférence d'affichage, pas une donnée de la personne
// — elle reste donc dans le navigateur, pas en base.
const btnFiger = document.getElementById('btnFiger');
let fige = false;
try { fige = (window.localStorage.getItem('famicard_avatar_fige') === '1'); } catch (e) { fige = false; }

function appliqueVerrou() {
    if (vue) { vue.rotationAuto(!fige); }
    btnFiger.textContent = fige ? '▶️ Relancer la rotation' : '⏸️ Figer la rotation';
    btnFiger.setAttribute('aria-pressed', fige ? 'true' : 'false');
}
btnFiger.addEventListener('click', function () {
    fige = !fige;
    try { window.localStorage.setItem('famicard_avatar_fige', fige ? '1' : '0'); } catch (e) { /* navigation privée */ }
    appliqueVerrou();
});
appliqueVerrou();

document.getElementById('btnHasard').addEventListener('click', function () {
    // Un tirage complet, y compris les couleurs : le but est de donner un point
    // de départ surprenant, pas une variation timide de ce qu'on a déjà.
    Object.keys(CHAMPS).forEach(function (cle) {
        const valeurs = Object.keys(CHAMPS[cle].valeurs);
        config[cle] = valeurs[Math.floor(Math.random() * valeurs.length)];
    });
    redessine();
});

document.getElementById('btnAnnuler').addEventListener('click', function () {
    config = Object.assign({}, DEPART);
    redessine();
    dis(AVAIT_UN ? 'Revenu à ton avatar enregistré.' : 'Revenu au personnage de départ.', '');
});

// ── ENREGISTRER ─────────────────────────────────────────────────────────────
btnSave.addEventListener('click', function () {
    btnSave.disabled = true;
    dis('Enregistrement…', '');

    // La vignette est prise MAINTENANT, de face : c'est elle qu'on verra dans
    // les petits ronds. Si la 3D n'a pas pu s'ouvrir, on envoie la seule chose
    // qui compte vraiment — la configuration.
    let image = '';
    try {
        if (vue) { image = vue.instantane(512); }
    } catch (e) {
        image = '';
    }

    const corps = new FormData();
    corps.append('csrf_token', JETON);
    corps.append('action', 'enregistrer');
    corps.append('config', JSON.stringify(config));
    corps.append('image', image);

    fetch('avatar.php', { method: 'POST', body: corps, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) {
                enregistre = JSON.stringify(config);
                dis(d.message, 'ok');
                btnSave.disabled = true;
            } else {
                dis(d.message || "L'enregistrement n'a pas abouti.", 'ko');
                btnSave.disabled = false;
            }
        })
        .catch(function () {
            dis("L'enregistrement n'a pas abouti. Vérifie ta connexion.", 'ko');
            btnSave.disabled = false;
        });
});

const btnSupp = document.getElementById('btnSupprimer');
if (btnSupp) {
    btnSupp.addEventListener('click', function () {
        if (!window.confirm('Supprimer ton avatar ? Ta photo et ta fiche ne sont pas touchées.')) { return; }
        const corps = new FormData();
        corps.append('csrf_token', JETON);
        corps.append('action', 'supprimer');
        fetch('avatar.php', { method: 'POST', body: corps, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                dis(d.message, d.ok ? 'ok' : 'ko');
                if (d.ok) { window.setTimeout(function () { window.location.href = 'index.php'; }, 900); }
            })
            .catch(function () { dis("La suppression n'a pas abouti.", 'ko'); });
    });
}

// ── LE FILET DE SÉCURITÉ ────────────────────────────────────────────────────
// Quitter la page en emportant un travail non enregistré est la frustration la
// plus bête qui soit : on prévient.
window.addEventListener('beforeunload', function (ev) {
    if (JSON.stringify(config) !== enregistre) {
        ev.preventDefault();
        ev.returnValue = '';
    }
});

marqueChoix();
</script>
<?php endif; ?>

</body>
</html>
