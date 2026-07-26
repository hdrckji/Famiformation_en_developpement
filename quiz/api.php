<?php
/* ============================================================
   ⚙️ API DU QUIZ — côté serveur (IONOS ou Railway)
   Stocke les scores et les codes bonus dans des fichiers JSON.

   SIMULTANÉITÉ : plusieurs personnes jouent en même temps (c'est même le cas
   normal le jour de l'événement). Toute opération qui MODIFIE un fichier garde
   donc UN SEUL verrou exclusif du début à la fin — lecture ET écriture comprises.
   Sinon deux joueurs qui valident à la même seconde liraient la même liste et le
   second écraserait le premier : un score perdu, ou un code bonus donné deux fois.
   ============================================================ */

header('Content-Type: application/json; charset=utf-8');

// 🛟 FILET DE SÉCURITÉ : mbstring est présent sur IONOS comme sur l'image Railway,
// mais s'il venait à manquer, mb_strlen() serait « fonction inconnue » → erreur
// fatale renvoyée en HTTP 200, c'est-à-dire un score perdu SANS que le joueur
// voie la moindre erreur. On préfère un repli ASCII (légèrement moins exact sur
// les accents) plutôt qu'une panne muette le jour de l'événement.
if (!function_exists('mb_strlen')) {
  function mb_strlen($s, $enc = null) { return strlen((string)$s); }
}
if (!function_exists('mb_strtolower')) {
  function mb_strtolower($s, $enc = null) { return strtolower((string)$s); }
}
if (!function_exists('mb_substr')) {
  function mb_substr($s, $debut, $len = null, $enc = null) {
    return $len === null ? substr((string)$s, $debut) : substr((string)$s, $debut, $len);
  }
}

// 🔑 Codes bonus à usage unique (les mêmes que sur tes QR codes en magasin).
// 20 codes, chacun rapporte $CODE_GRAINES graines à la PREMIÈRE personne qui le
// récupère. Chaque joueur peut en cumuler au maximum $MAX_CODES.
// ⛳ DEUX MAGASINS, DEUX LOTS DE CODES DISTINCTS. Les QR de Mouscron (préfixe
// FAMI-) et ceux de La Panne (préfixe FAPA-) n'ont aucun code en commun : un
// code trouvé à Mouscron est « inconnu » à La Panne, et réciproquement. Les deux
// événements ne peuvent donc pas se mélanger, même par erreur de manipulation.
$BONUS_CODES_PAR_SITE = [
  'mouscron' => [
    "FAMI-A7K2", "FAMI-B3X9", "FAMI-C5M1", "FAMI-D8R4", "FAMI-E2T7",
    "FAMI-F6H8", "FAMI-G1J3", "FAMI-K9L2", "FAMI-M4N7", "FAMI-P5Q8",
    "FAMI-R3S6", "FAMI-T2U9", "FAMI-V7W1", "FAMI-X8Y4", "FAMI-Z5A2",
    "FAMI-B9C6", "FAMI-D1E3", "FAMI-F4G7", "FAMI-H8J5", "FAMI-K2L9",
  ],
  'lapanne' => [
    "FAPA-N3B7", "FAPA-C8D2", "FAPA-E5F9", "FAPA-G1H4", "FAPA-J6K3",
    "FAPA-L2M8", "FAPA-P7Q1", "FAPA-R4S9", "FAPA-T3U6", "FAPA-V8W2",
    "FAPA-X5Y7", "FAPA-Z1A4", "FAPA-B6C9", "FAPA-D2E5", "FAPA-F8G3",
    "FAPA-H4J7", "FAPA-K9L1", "FAPA-M3N6", "FAPA-P5Q8", "FAPA-R2S4",
  ],
];
// 🧪 Codes de TEST (communs aux deux sites) : le premier marche toujours (jamais
// consommé, re-testable), le second est toujours vu comme « déjà utilisé ».
$CODE_TEST_OK   = "FAMI-TEST-OK";
$CODE_TEST_USED = "FAMI-TEST-USED";
// $BONUS_CODES est fixé plus bas, une fois le site de la requête connu.
// 🧪 COMPTES DE TEST : ces pseudos servent a essayer le jeu EN VRAI (borne,
// telephone, ordi) sans deranger personne. Ils n'apparaissent PAS au classement
// public ni sur la tele, et ils peuvent refaire le quiz autant de fois qu'ils
// veulent. Cree-les comme un compte normal avec ce pseudo.
$COMPTES_TEST = ['testeur', 'admin_'];
function estCompteTest($p) {
  global $COMPTES_TEST;
  return in_array(mb_strtolower((string)(is_array($p) ? ($p['name'] ?? '') : $p)), $COMPTES_TEST, true);
}
$CODE_GRAINES = 10;   // graines par code bonus (comptent dans le classement)
$MAX_CODES    = 2;    // combien de codes une même personne peut cumuler

// ✉️ Combien de temps le lien « Définir mon mot de passe » reste valable pour
// quelqu'un qui s'inscrit depuis le quiz. 72 h (le défaut de l'app) ne suffit
// pas : on doit pouvoir s'inscrire une semaine avant l'événement et n'activer
// son compte que le jour J.
$ACTIVATION_HEURES = 30 * 24;

// 📄 GOOGLE FORM de récolte des mails (partagé avec l'accueil pour les tickets
// glace). Quand quelqu'un s'inscrit depuis la borne/le téléphone, on recopie sa
// réponse dans CE formulaire, pour que le résultat soit le même que s'il l'avait
// rempli à la main : l'accueil garde sa feuille habituelle. Les identifiants des
// champs ont été relevés sur le formulaire (Nom, Prénom, e-mail « emailAddress »).
$FORM_ACTIF   = true;
$FORM_URL     = 'https://docs.google.com/forms/d/e/1FAIpQLSfEq4cwc2P9aDQno8z3ftMRiKAgttI9UaH46-3PVMQJY_5Feg/formResponse';
$FORM_CHAMP_NOM    = 'entry.2040091278';
$FORM_CHAMP_PRENOM = 'entry.969078151';
// L'e-mail est le champ « collecte d'adresse » de Google : il se soumet via
// « emailAddress » (pas un entry.XXX).

// Recopie une inscription dans le Google Form. « Au mieux » : si l'envoi échoue,
// on n'interrompt JAMAIS l'inscription (la personne reste créée et reçoit son
// mail). cURL avec un délai court pour ne pas ralentir la borne.
function pousseVersForm($prenom, $nom, $email) {
  global $FORM_ACTIF, $FORM_URL, $FORM_CHAMP_NOM, $FORM_CHAMP_PRENOM;
  if (!$FORM_ACTIF || !function_exists('curl_init')) { return false; }
  $post = http_build_query([
    $FORM_CHAMP_NOM    => $nom,
    $FORM_CHAMP_PRENOM => $prenom,
    'emailAddress'     => $email,
    'fvv' => '1', 'pageHistory' => '0',
  ]);
  $ch = curl_init($FORM_URL);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 6,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,      // sur Railway le bundle CA est présent
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FamiQuiz/1.0)',
  ]);
  $rep = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $code === 200 && $rep !== false;
}

// 🔐 Identifiants admin (accès au mode admin + réinitialisation des scores)
$ADMIN_ID  = "admin";
$ADMIN_PWD = "a";
$ADMIN_PIN = $ADMIN_PWD;   // compat : ancien lien api.php?action=reset&pin=...

// 🎁 Accès RH (page /quiz/rh) : voir/cocher les récompenses remises. Séparé de
// l'admin, pour confier la remise des récompenses aux RH sans donner l'admin.
$RH_ID  = "rh";
$RH_PWD = "Fami2026";

// 📄 FLUX DU FORMULAIRE GOOGLE (onglet « recolte de mail »).
// Le site lit la feuille via un mini-script Google (Apps Script) déployé en
// « application web », qui renvoie l'onglet en JSON, protégé par un secret.
// On configure l'URL et le secret côté serveur uniquement (variables Railway) :
//   FORM_FEED_URL    = https://script.google.com/macros/s/…/exec
//   FORM_FEED_SECRET = un mot de passe long, identique à celui du script
$FORM_FEED_URL    = getenv('FORM_FEED_URL')    ?: ($_SERVER['FORM_FEED_URL']    ?? '');
$FORM_FEED_SECRET = getenv('FORM_FEED_SECRET') ?: ($_SERVER['FORM_FEED_SECRET'] ?? '');

// 📁 OÙ SONT STOCKÉS LES SCORES.
// Sur Railway, le disque du conteneur est EFFACÉ à chaque déploiement : si on
// écrivait dans quiz/data, tout le classement disparaîtrait au prochain push.
// On utilise donc le volume persistant (le même que les uploads du site).
// En local ou sur IONOS, pas de volume : on retombe sur quiz/data, c'est correct.
$vol = getenv('RAILWAY_VOLUME_MOUNT_PATH') ?: ($_SERVER['RAILWAY_VOLUME_MOUNT_PATH'] ?? '');
$dataDir = ($vol && @is_dir($vol)) ? rtrim($vol, "/\\") . '/quiz' : __DIR__ . '/data';
// @ et re-test : deux visiteurs simultanés peuvent tenter de créer le dossier
// en même temps, le perdant recevrait un warning inutile.
if (!is_dir($dataDir)) { @mkdir($dataDir, 0755, true); }
if (!is_dir($dataDir)) {
  http_response_code(500);
  echo json_encode(['error' => 'Dossier de données inaccessible']);
  exit;
}
// 🏬 LES DEUX MAGASINS. Le nom sert à l'affichage et au tag du compte (site_id).
// Toute donnée du jeu (scores, codes, questions, jardin, dates) est rangée dans
// un fichier PROPRE À CHAQUE SITE — voir plus bas, une fois la requête lue.
$SITES = [
  'mouscron' => ['nom' => 'Famiflora Mouscron', 'ville' => 'Mouscron'],
  'lapanne'  => ['nom' => 'Famiflora La Panne', 'ville' => 'La Panne'],
];
$SITE_DEFAUT = 'mouscron';

// Le site d'une requête vient du champ `site` (corps JSON ou ?site=). S'il est
// absent ou farfelu, on retombe sur le site par défaut plutôt que d'échouer :
// mieux vaut un quiz qui tourne qu'une page cassée. En pratique le client envoie
// toujours son site (il le tient de son URL).
function siteDe($input, $sites, $defaut) {
  $s = strtolower(trim((string)($input['site'] ?? $_GET['site'] ?? '')));
  return isset($sites[$s]) ? $s : $defaut;
}

// ⚠️ Les fichiers de données ($scoresFile, etc.) sont fixés juste avant le switch,
// après lecture de la requête, car ils dépendent du site. Ne pas les utiliser avant.

// ⏱ Dates de l'événement, modifiables depuis l'admin (onglet Compte à rebours).
// Par défaut : lancement le 29/07 à 12h30, clôture le 30/08, annonce des vainqueurs le 31 août à 12h30.
function ladConfig($fichier) {
  $c = is_file($fichier) ? json_decode((string)@file_get_contents($fichier), true) : null;
  if (!is_array($c)) { $c = []; }
  return [
    'lancement' => $c['lancement'] ?? '2026-07-29T12:30',
    'cloture'   => $c['cloture']   ?? '2026-08-30T23:59',
    'resultats' => $c['resultats'] ?? '31 août à 12h30',
    // Zones du magasin où des codes ont été cachés (indice affiché à J-1) :
    // liste de { nom, nb }.
    'zones'     => (isset($c['zones']) && is_array($c['zones'])) ? array_values($c['zones']) : [],
    // 🏆 Récompenses des 3 premiers, saisies dans l'admin. Tant que la liste est
    // vide, la télé annonce simplement « des récompenses à gagner ».
    'recompenses' => (isset($c['recompenses']) && is_array($c['recompenses'])) ? array_values($c['recompenses']) : [],
  ];
}

/* ============================================================
   🌼 LE JARDIN COLLECTIF
   Les joueurs dépensent leurs graines (= leurs points, qu'ils gardent au
   classement : planter ne fait PAS reculer au classement) pour poser des
   plantes sur une grille commune. Chaque case ne se plante qu'une fois.
   ============================================================ */

// Catalogue : clé => [emoji, nom affiché, coût en graines].
// Les coûts sont pensés pour un score max d'environ 340 graines :
// un bon joueur plante 3-5 fois, un joueur moyen 2-3 fois.
$PLANTES = [
  'trefle'     => ['emoji' => '🍀', 'nom' => 'Trèfle',        'cout' => 1],
  'brin'       => ['emoji' => '🌱', 'nom' => 'Brin d\'herbe',  'cout' => 1],
  'arbreglace' => ['emoji' => '🎟️', 'nom' => 'Arbre à tickets glace', 'cout' => 5],
  'paquerette' => ['emoji' => '🌼', 'nom' => 'Pâquerette',    'cout' => 20],
  'tulipe'     => ['emoji' => '🌷', 'nom' => 'Tulipe',        'cout' => 35],
  'lavande'    => ['emoji' => '💜', 'nom' => 'Lavande',       'cout' => 50],
  'tournesol'  => ['emoji' => '🌻', 'nom' => 'Tournesol',     'cout' => 80],
  'rosier'     => ['emoji' => '🌹', 'nom' => 'Rosier',        'cout' => 120],
  'arbre'      => ['emoji' => '🌳', 'nom' => 'Petit arbre',   'cout' => 200],
  // 🏆 Les 3 LOTUS : chers, magnifiques, ils scintillent au jardin. Les avoir
  // plantés TOUS LES TROIS (+ jardin plein) rend éligible à la récompense.
  // Coûts calibrés : jardin plein + ces 3 lotus ≈ 3 000 graines → ~15 quiz à 20/20.
  'bronze'     => ['emoji' => '🏵️', 'nom' => 'Lotus de bronze', 'cout' => 500,  'rare' => 'bronze'],
  'argent'     => ['emoji' => '💮', 'nom' => 'Lotus d\'argent',  'cout' => 1000, 'rare' => 'argent'],
  'or'         => ['emoji' => '🪷', 'nom' => 'Lotus d\'or',      'cout' => 1500, 'rare' => 'or'],
];
// 🎁 Éligibilité à la récompense « jardin » : jardin PLEIN + ces 3 lotus plantés.
$LOTUS_REQUIS = ['or', 'argent', 'bronze'];

// Taille de la grille (8 colonnes × 6 lignes = 48 cases).
$JARDIN_CASES = 48;

// 🌿 MINI-JEU « chasse aux mauvaises herbes » : combien rapporte chaque herbe.
// Le serveur ne fait JAMAIS confiance au total envoyé par la page : il recalcule
// les graines avec CETTE table, à partir du nombre d'herbes de chaque sorte, et
// plafonne le gain par partie (anti-triche raisonnable pour un jeu bon enfant).
// Le mini-jeu des herbes ne rapporte que des MIETTES : c'est la voie « pour le
// plaisir ». La vraie voie pour finir le jardin, c'est le quiz du jardin.
$HERBE_GAIN = ['normale' => 1, 'bronze' => 2, 'argent' => 3, 'or' => 5];
$HERBE_MAX_PAR_HERBE = 300;   // borne le nombre d'herbes d'une sorte par partie
$HERBE_MAX_GAIN = 15;         // gain maximum crédité en une partie (des miettes)

// 🎯 QUIZ DU JARDIN (rejouable) : la voie « efficace » pour alimenter le jardin.
// Chaque bonne réponse rapporte des graines de jardin (bonus, PAS le classement).
// Environ 1 800 graines pour finir le jardin (3 lotus + cases) → ~12 quiz.
$QUIZ_JARDIN_PAR_BONNE = 10;   // graines de jardin par bonne réponse
$QUIZ_JARDIN_MAX_BONNES = 20;  // plafond par partie (anti-triche bon enfant)

/**
 * Solde de graines DISPONIBLES pour planter :
 *   récoltées au quiz (score) + gagnées au mini-jeu (bonus) − déjà dépensées.
 * Le « bonus » n'entre PAS dans le classement (score) : le classement, et donc
 * les prix, restent basés sur le quiz uniquement.
 */
function soldeDe($p) {
  // Le score du quiz est un nombre à virgule (bonus rapidité continu) : float.
  $solde = max(0, round(floatval($p['score'] ?? 0) + intval($p['bonus'] ?? 0) - intval($p['depensees'] ?? 0), 1));
  // 🧪 Compte de test (préview) : graines quasi illimitées (plancher 10000) pour
  // pouvoir explorer/remplir tout le jardin sans jamais être bloqué. Exclu du classement.
  if (estCompteTest($p)) { $solde = max($solde, 10000); }
  return $solde;
}

// ❓ QUESTIONS PAR DÉFAUT (secours). Elles ne servent QUE si aucune question n'a
// encore été chargée pour le magasin. Dès que tu cliques « Charger toutes les
// questions » (ou que tu enregistres depuis /quiz/admin), c'est questions.json
// qui fait foi et cette liste n'est plus jamais consultée. Ce sont de vraies
// questions de jardinage/plantes, pour ne jamais laisser un joueur devant un
// placeholder même en cas d'incident.
$QUESTIONS_DEFAUT = [
  ['q' => "En quelle année Famiflora a-t-elle été créée ?", 'options' => ["2012", "2005", "2018", "1999"], 'correct' => 0, 'theme' => 'entreprise'],
  ['q' => "Que faut-il donner à une plante pour qu'elle pousse ?", 'options' => ["De l'eau et de la lumière", "Uniquement de l'ombre", "Du sel", "Rien du tout"], 'correct' => 0, 'theme' => 'culture'],
  ['q' => "À quelle saison plante-t-on généralement les bulbes de tulipes ?", 'options' => ["À l'automne", "En plein été", "En hiver sous la neige", "Elles ne se plantent pas"], 'correct' => 0, 'theme' => 'culture'],
  ['q' => "Un cactus a besoin…", 'options' => ["De très peu d'eau", "D'un arrosage quotidien", "D'être immergé", "De rester dans le noir"], 'correct' => 0, 'theme' => 'culture'],
  ['q' => "À quoi servent les abeilles au jardin ?", 'options' => ["À polliniser les fleurs", "À manger les fruits", "À tondre la pelouse", "À rien d'utile"], 'correct' => 0, 'theme' => 'culture'],
];

/** Nettoie une question venant du navigateur (on ne fait jamais confiance à l'envoi). */
function nettoieQuestion($item) {
  $q = trim((string)($item['q'] ?? ''));
  // 🌍 Bilingue : on garde une traduction NL alignée par index avec les options FR.
  // Une case NL vide = pas encore traduite → le rendu retombe sur le FR.
  $qNl  = trim((string)($item['q_nl'] ?? ''));
  $rawFr = (array)($item['options'] ?? []);
  $rawNl = (array)($item['options_nl'] ?? []);
  $opts = [];
  $optsNl = [];
  foreach ($rawFr as $i => $o) {
    $o = trim((string)$o);
    if ($o === '') { continue; }                 // on saute la même position en NL pour rester alignés
    $opts[] = mb_substr($o, 0, 120);
    $n = trim((string)($rawNl[$i] ?? ''));
    $optsNl[] = ($n !== '') ? mb_substr($n, 0, 120) : '';
  }
  $correct = (int)($item['correct'] ?? 0);
  if ($q === '' || count($opts) < 2) { return null; }          // inutilisable
  if ($correct < 0 || $correct >= count($opts)) { $correct = 0; } // index hors liste
  // 🎯 Thème de la question : sert à composer le quiz (10 entreprise, 5 culture
  // générale, 5 « fun »). Valeur inconnue ou absente → « entreprise ».
  $theme = strtolower(trim((string)($item['theme'] ?? '')));
  if ($theme === 'anecdote') { $theme = 'fun'; }                  // ancien nom → nouveau
  if (!in_array($theme, ['entreprise', 'culture', 'fun'], true)) { $theme = 'entreprise'; }
  // ⭐ Favorite : question qui apparaîtra plus souvent. Ne concerne QUE entreprise
  // et fun (la culture n'a pas de favoris). On la conserve à l'enregistrement.
  $fav = !empty($item['fav']) && in_array($theme, ['entreprise', 'fun'], true);
  $out = ['q' => mb_substr($q, 0, 300), 'options' => $opts, 'correct' => $correct, 'theme' => $theme, 'fav' => $fav];
  // On n'ajoute les champs NL que s'il existe vraiment une traduction (fichiers plus légers).
  $aDuNl = ($qNl !== '') || count(array_filter($optsNl, static function ($x) { return $x !== ''; })) > 0;
  if ($aDuNl) {
    $out['q_nl'] = mb_substr($qNl, 0, 300);
    $out['options_nl'] = $optsNl;
  }
  return $out;
}

/** Les questions en vigueur (fichier si présent, sinon la liste par défaut). */
function lesQuestions($fichier, $defaut) {
  $d = readJson($fichier);
  $out = [];
  foreach ($d as $item) {
    $c = nettoieQuestion($item);
    if ($c) { $out[] = $c; }
  }
  return $out ?: $defaut;
}

/**
 * Porte d'entrée des actions d'administration.
 * Les identifiants sont revérifiés À CHAQUE appel : le « mode admin » du
 * navigateur n'est qu'un affichage, il ne protège rien. Sans cette vérification
 * ici, n'importe qui pourrait appeler l'action directement et vider le classement.
 */
function exigeAdmin($input) {
  global $ADMIN_ID, $ADMIN_PWD;
  $id  = trim($input['id'] ?? '');
  $pwd = (string)($input['pwd'] ?? '');
  if (!hash_equals($ADMIN_ID, $id) || !hash_equals($ADMIN_PWD, $pwd)) {
    http_response_code(401);
    echo json_encode(['error' => 'Acces refuse']);
    exit;
  }
}
// 🎁 Garde de la page RH. L'admin a AUSSI le droit (pratique pour toi).
function exigeRh($input) {
  global $RH_ID, $RH_PWD, $ADMIN_ID, $ADMIN_PWD;
  $id  = trim($input['id'] ?? '');
  $pwd = (string)($input['pwd'] ?? '');
  $okRh    = hash_equals($RH_ID, $id) && hash_equals($RH_PWD, $pwd);
  $okAdmin = hash_equals($ADMIN_ID, $id) && hash_equals($ADMIN_PWD, $pwd);
  if (!$okRh && !$okAdmin) {
    http_response_code(401);
    echo json_encode(['error' => 'Acces refuse']);
    exit;
  }
}

/**
 * LECTURE SEULE (verrou partagé : plusieurs lecteurs en même temps, c'est permis).
 * À n'utiliser que quand on ne compte PAS réécrire derrière.
 */
function readJson($file) {
  if (!file_exists($file)) return [];
  $fp = @fopen($file, 'r');
  if (!$fp) return [];
  flock($fp, LOCK_SH);
  $content = stream_get_contents($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  $d = json_decode($content, true);
  return is_array($d) ? $d : [];
}

/**
 * LECTURE + MODIFICATION + ÉCRITURE, sous UN SEUL verrou exclusif.
 *
 * C'est le cœur de la protection contre les accès simultanés : tant que $fn
 * travaille, personne d'autre ne peut ni lire ni écrire ce fichier. On relit
 * DANS le verrou (pas avant), donc $fn voit toujours l'état le plus à jour.
 *
 * $fn reçoit ($data, $write) PAR RÉFÉRENCE : modifie $data et mets $write = true
 * pour que le fichier soit réécrit. Ce que $fn retourne est renvoyé tel quel.
 */
function withLock($file, callable $fn) {
  $fp = @fopen($file, 'c+');            // 'c+' : crée si absent, ne tronque pas
  if (!$fp) {
    http_response_code(500);
    return ['error' => 'Fichier de données verrouillé'];
  }
  flock($fp, LOCK_EX);                  // ⬅ attente ici si quelqu'un d'autre écrit

  rewind($fp);
  $content = stream_get_contents($fp);
  $data = json_decode($content, true);
  if (!is_array($data)) { $data = []; }

  $write = false;
  $reponse = $fn($data, $write);

  if ($write) {
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
  }
  flock($fp, LOCK_UN);
  fclose($fp);
  return $reponse;
}

/** Écriture simple (sans lecture préalable) : uniquement pour la remise à zéro. */
function writeJson($file, $data) {
  $fp = @fopen($file, 'c');
  if (!$fp) return false;
  flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  rewind($fp);
  fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return true;
}

function sortBoard(&$board) {
  usort($board, function ($a, $b) {
    // Score DÉCROISSANT, décimales incluses (la rapidité départage). On utilise
    // l'opérateur <=> : un retour flottant serait tronqué en int et perdrait la
    // virgule. En cas d'égalité stricte, le plus rapide (time) passe devant.
    $sa = floatval($a['score'] ?? 0); $sb = floatval($b['score'] ?? 0);
    if ($sa !== $sb) return $sb <=> $sa;
    return intval($a['time'] ?? 0) <=> intval($b['time'] ?? 0);
  });
}

/* ============================================================
   🔗 LES COMPTES VIENNENT DE FAMIFORMATION
   Le quiz ne gère plus ses propres comptes : pour jouer, il faut un compte
   Famiformation (le même que pour se connecter au site). Le quiz tourne dans le
   MÊME conteneur que l'app, donc il lit la même base.

   On ne charge surtout PAS config.php : il ouvre une session, fait des
   redirections (header Location) et injecte du HTML dans la sortie — trois
   choses qui casseraient des réponses JSON. includes/functions.php, lui, se
   suffit à lui-même : famiGetEnv(), sendMail(), sendAccountActivationEmail()...
   ============================================================ */
function famiDb() {
  static $db = null, $deja = false;
  if ($deja) { return $db; }
  $deja = true;
  // Deux dispositions : dans le conteneur, quiz/ est DANS public/ (Dockerfile) ;
  // dans le dépôt, quiz/ et public/ sont côte à côte.
  $lib = null;
  foreach ([__DIR__ . '/../includes/functions.php', __DIR__ . '/../public/includes/functions.php'] as $piste) {
    if (is_file($piste)) { $lib = $piste; break; }
  }
  if ($lib === null) { return null; }
  // Ceinture et bretelles : si ce fichier émettait quoi que ce soit (avertissement,
  // espace avant <?php), ça se collerait dans notre JSON et le joueur verrait une
  // erreur incompréhensible. On avale tout ce qui pourrait sortir.
  ob_start();
  require_once $lib;
  ob_end_clean();
  try {
    // QUIZ_DB_DSN sert aux essais hors ligne (SQLite) ; en production, ce sont
    // les mêmes variables que l'app.
    $dsn = (string) famiGetEnv('QUIZ_DB_DSN', '');
    if ($dsn !== '') {
      $db = new PDO($dsn, (string) famiGetEnv('QUIZ_DB_USER', ''), (string) famiGetEnv('QUIZ_DB_PASS', ''));
    } else {
      $db = new PDO(
        'mysql:host=' . famiGetEnv('DB_HOST', 'localhost') . ';dbname=' . famiGetEnv('DB_NAME', '') . ';charset=utf8mb4',
        (string) famiGetEnv('DB_USER', ''), (string) famiGetEnv('DB_PASSWORD', '')
      );
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $db = null;
  }
  return $db;
}

// 🔐 Le jeton de session du joueur. On ne garde JAMAIS son mot de passe côté
// navigateur : à la connexion on lui remet un jeton signé, qu'il renvoie ensuite.
// Signature HMAC avec un secret tiré une fois pour toutes dans le dossier de
// données — donc rien à stocker par joueur, et un jeton bricolé est rejeté.
function quizSecret() {
  global $dataDir;
  static $secret = null;
  if ($secret !== null) { return $secret; }
  $f = $dataDir . '/secret.txt';
  if (is_file($f)) { $secret = trim((string) @file_get_contents($f)); }
  if (empty($secret)) {
    $secret = bin2hex(random_bytes(32));
    @file_put_contents($f, $secret);
    @chmod($f, 0600);
  }
  return $secret;
}
// Séparateur « | » et identifiant encodé : les identifiants contiennent un POINT
// (prenom.nom), donc découper sur le point casserait la relecture du jeton.
function faitJeton($uid, $identifiant, $jours = 60) {
  $exp = time() + $jours * 86400;
  $corps = ((int) $uid) . '|' . ($exp) . '|' . rawurlencode((string) $identifiant);
  return $corps . '|' . hash_hmac('sha256', $corps, quizSecret());
}
function litJeton($jeton) {
  $p = explode('|', (string) $jeton);
  if (count($p) !== 4) { return null; }
  [$uid, $exp, $ident, $sig] = $p;
  $corps = $uid . '|' . $exp . '|' . $ident;
  if (!hash_equals(hash_hmac('sha256', $corps, quizSecret()), $sig)) { return null; }
  if ((int) $exp < time()) { return null; }
  return ['uid' => (int) $uid, 'identifiant' => rawurldecode($ident)];
}

// 🔐 Un joueur a-t-il le droit d'agir sous le nom `$name` ? Deux preuves possibles :
//   • un JETON de session valide (compte Famiformation) dont l'identifiant = $name ;
//   • sinon, l'ancien code jardinier à 4 chiffres qui correspond à la fiche.
// C'est ce qui empêche quelqu'un de soumettre un score au nom d'un autre : les
// comptes Famiformation n'ont pas de code, seul leur jeton signé les authentifie.
function joueurAutorise($input, $name, $ficheCode) {
  $auth = litJeton($input['jeton'] ?? '');
  if ($auth && mb_strtolower($auth['identifiant']) === mb_strtolower((string) $name)) {
    return true;                                  // jeton Famiformation valide
  }
  $code4 = preg_replace('/\D/', '', (string)($input['code'] ?? ''));
  $ficheCode = (string) $ficheCode;
  return $ficheCode !== '' && $ficheCode === $code4;   // ancien compte pseudo + code
}

// Un identifiant libre au format « PrénomN » : le prénom (1re lettre en majuscule)
// suivi de la 1re lettre du nom en MAJ. Ex. : Jean Dupont → « JeanD ». En cas de
// DOUBLON, on n'ajoute PAS de numéro : on ALLONGE le nom lettre par lettre →
// « JeanDu », « JeanDup », etc., jusqu'à trouver un identifiant libre. (Un numéro
// n'arrive qu'en dernier recours, si même le nom entier est déjà pris.)
// C'est ce que la personne tapera pour se connecter — l'e-mail marche aussi.
function identifiantLibre(PDO $db, $prenom, $nom) {
  $sansAccent = function ($s) {
    $s = (string) $s;
    $tr = ['à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
           'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
           'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y','œ'=>'oe','æ'=>'ae'];
    return strtr(mb_strtolower($s), $tr);
  };
  // On ne garde que les lettres/chiffres (les espaces, tirets, etc. sautent).
  $p = preg_replace('/[^a-z0-9]+/', '', $sansAccent($prenom));
  $n = preg_replace('/[^a-z0-9]+/', '', $sansAccent($nom));
  if ($p === '') { $p = 'joueur'; }
  $prefixe = ucfirst($p);   // « Jean »
  $stmt = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE identifiant = ?');
  $libre = function ($id) use ($stmt) { $stmt->execute([$id]); return (int) $stmt->fetchColumn() === 0; };

  $lenN = strlen($n);
  if ($lenN > 0) {
    // On allonge le nom : JeanD, JeanDu, JeanDup… jusqu'au 1er identifiant libre.
    for ($k = 1; $k <= $lenN; $k++) {
      $essai = substr($prefixe . ucfirst(substr($n, 0, $k)), 0, 40);
      if ($libre($essai)) { return $essai; }
    }
    // Nom entier épuisé et toujours pris → dernier recours : un numéro.
    $complet = substr($prefixe . ucfirst($n), 0, 37);
    for ($i = 2; $i < 500; $i++) { if ($libre($complet . $i)) { return $complet . $i; } }
    return $complet . bin2hex(random_bytes(2));
  }
  // Pas de nom : prénom seul, puis un numéro si déjà pris.
  for ($i = 0; $i < 500; $i++) {
    $essai = $i === 0 ? $prefixe : $prefixe . ($i + 1);
    if ($libre($essai)) { return $essai; }
  }
  return $prefixe . bin2hex(random_bytes(3));
}

// 🎁 Mail « fun » d'invitation à créer son compte AVANT le lancement (envoi groupé).
// Même lien d'activation que d'habitude (set_password.php + jeton), mais habillage
// festif « Noël avant l'heure ». Renvoie true si le mail est parti.
function envoiFunActivation(PDO $db, $userId, $heures = 336) {
  if (!function_exists('issueUserAccountAccessToken') || !function_exists('sendMail') || !function_exists('famiBuildAppUrl')) {
    return false;
  }
  if (function_exists('ensureUserAccountAccessColumns')) { ensureUserAccountAccessColumns($db); }
  $stmt = $db->prepare('SELECT id, identifiant, prenom, email FROM utilisateurs WHERE id = ? LIMIT 1');
  $stmt->execute([(int) $userId]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$u || empty($u['email'])) { return false; }

  $heures = max(1, (int) $heures);
  $token = issueUserAccountAccessToken($db, $u['id'], 'activation', $heures);
  $url   = famiBuildAppUrl('set_password.php', ['token' => $token]);
  $prenom = trim((string) ($u['prenom'] ?? ''));
  $bonjour = $prenom !== '' ? $prenom : trim((string) ($u['identifiant'] ?? ''));
  $validite = $heures >= 48 ? ((int) round($heures / 24) . ' jours') : ((int) $heures . ' heures');
  $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

  $subject = '🌱 Prends de l\'avance : crée déjà ton compte Famiformation !';
  $body = '<div style="margin:0;padding:32px;background:#eef4ef;font-family:Open Sans,Arial,sans-serif;color:#244230;">'
    . '<div style="max-width:680px;margin:0 auto;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 18px 38px rgba(27,54,36,.12);">'
    . '<div style="padding:30px 32px;background:linear-gradient(135deg,#2d5a37 0%,#4a7b55 100%);color:#fff;">'
    . '<div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.85;">Famiformation · Famiflora</div>'
    . '<h1 style="margin:10px 0 8px;font-size:28px;line-height:1.2;">🌱 Prends de l\'avance&nbsp;!</h1>'
    . '<p style="margin:0;font-size:15px;line-height:1.6;opacity:.95;">Tu peux déjà créer ton compte Famiformation, avant le grand lancement du 29/07.</p>'
    . '</div>'
    . '<div style="padding:32px;">'
    . '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Bonjour ' . $e($bonjour) . ',</p>'
    . '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;">Bonne nouvelle&nbsp;! 🎉 On t\'avait dit que tu recevrais ton lien le 29/07… mais on te l\'envoie <b>en avance</b>. '
    . 'Tu peux <b>dès maintenant créer ton compte Famiformation</b> (choisir ton mot de passe) pour être fin prêt(e) le jour du lancement — le <b>quiz</b> et ton <b>espace jardin</b> t\'attendront&nbsp;! 🌿</p>'
    . '<div style="margin:22px 0;padding:20px;border-radius:18px;background:#f6faf7;border:1px solid #dde9df;">'
    . '<div style="font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:#6a7d72;margin-bottom:10px;">Ton identifiant</div>'
    . '<p style="margin:0;font-size:16px;"><b>' . $e($u['identifiant']) . '</b></p>'
    . '</div>'
    . '<p style="margin:0 0 22px;"><a href="' . $e($url) . '" style="display:inline-block;padding:14px 24px;border-radius:999px;background:#d6a21a;color:#fff;font-weight:700;text-decoration:none;">🌱 Créer mon compte</a></p>'
    . '<p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#3a5443;">Ce lien est valable ' . $e($validite) . '. Une fois connecté(e), tu pourras déjà découvrir Famiformation. 🌿</p>'
    . '<p style="margin:0;font-size:14px;line-height:1.7;color:#617268;">Tu n\'es pas concerné(e) par ce message&nbsp;? Ignore-le simplement. Une question&nbsp;? Écris à admin@famiformation.com.</p>'
    . '</div>'
    . '<div style="padding:18px 32px;background:#f5f8f6;color:#617268;font-size:13px;">🌱 Message envoyé par Famiformation — Famiflora.</div>'
    . '</div></div>';

  return sendMail($u['email'], $subject, $body, true);
}

// 📄 Lit l'onglet « recolte de mail » du formulaire via le script Google.
// Renvoie ['ok'=>true,'lignes'=>[['prenom','nom','email'],…]] ou un tableau
// ['ok'=>false,'reason'=>…]. La liste n'est JAMAIS exposée au navigateur : seul
// le serveur connaît l'URL + le secret.
function litFluxFormulaire() {
  global $FORM_FEED_URL, $FORM_FEED_SECRET;
  if ($FORM_FEED_URL === '' || $FORM_FEED_SECRET === '') {
    return ['ok' => false, 'reason' => 'non_configure'];
  }
  $url = $FORM_FEED_URL . (strpos($FORM_FEED_URL, '?') === false ? '?' : '&')
       . 'secret=' . rawurlencode($FORM_FEED_SECRET);
  $brut = null;
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,   // Google redirige vers googleusercontent
      CURLOPT_TIMEOUT        => 20,
      CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $brut = curl_exec($ch);
    curl_close($ch);
  } else {
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'follow_location' => 1]]);
    $brut = @file_get_contents($url, false, $ctx);
  }
  if ($brut === false || $brut === null || $brut === '') {
    return ['ok' => false, 'reason' => 'injoignable'];
  }
  $j = json_decode($brut, true);
  if (!is_array($j) || empty($j['ok']) || !isset($j['lignes']) || !is_array($j['lignes'])) {
    return ['ok' => false, 'reason' => 'reponse_invalide'];
  }
  $out = [];
  foreach ($j['lignes'] as $l) {
    $email = mb_strtolower(trim((string) ($l['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { continue; }
    $out[] = [
      'prenom' => trim(mb_substr((string) ($l['prenom'] ?? ''), 0, 40)),
      'nom'    => trim(mb_substr((string) ($l['nom'] ?? ''), 0, 60)),
      'email'  => $email,
    ];
  }
  return ['ok' => true, 'lignes' => $out];
}

// 🔑 Clé « prénom|nom » normalisée (minuscule, sans espaces superflus) pour
// comparer deux personnes par leur nom.
function clePrenomNom($prenom, $nom) {
  return mb_strtolower(trim((string) $prenom)) . '|' . mb_strtolower(trim((string) $nom));
}

// 📇 Contenu du formulaire mis en cache court sur disque (l'inscription publique
// peut arriver souvent : on n'appelle donc pas le script Google à chaque fois).
// Renvoie ['emails' => ['x@y'=>true,…], 'noms' => ['prenom|nom'=>true,…]].
// En cas d'échec réseau, on garde l'ancien cache.
function fluxCache($ttl = 300) {
  global $dataDir, $FORM_FEED_URL, $FORM_FEED_SECRET;
  $vide = ['emails' => [], 'noms' => []];
  if ($FORM_FEED_URL === '' || $FORM_FEED_SECRET === '') { return $vide; }
  $cache = $dataDir . '/form-cache.json';
  $lire = function () use ($cache) {
    if (!is_file($cache)) { return null; }
    $c = json_decode((string) @file_get_contents($cache), true);
    return (is_array($c) && isset($c['emails'], $c['noms'])) ? $c : null;
  };
  if (is_file($cache) && (time() - (int) @filemtime($cache) < $ttl)) {
    $c = $lire();
    if ($c !== null) { return $c; }
  }
  $flux = litFluxFormulaire();
  if (!$flux['ok']) { $c = $lire(); return $c !== null ? $c : $vide; }
  $sets = ['emails' => [], 'noms' => []];
  foreach ($flux['lignes'] as $l) {
    $sets['emails'][$l['email']] = true;
    if (trim($l['prenom']) !== '' && trim($l['nom']) !== '') {
      $sets['noms'][clePrenomNom($l['prenom'], $l['nom'])] = true;
    }
  }
  @file_put_contents($cache, json_encode($sets), LOCK_EX);
  return $sets;
}

// 🔒 Interrupteur de l'envoi AUTOMATIQUE (temps réel) à chaque nouvelle réponse
// du formulaire. DÉSACTIVÉ par défaut : aucun mail ne part automatiquement tant
// que l'admin ne l'a pas allumé depuis l'onglet « Envoi groupé ». (Le bouton
// « Envoyer à tous » reste manuel et n'est PAS concerné par cet interrupteur.)
function autoEnvoiActif() {
  global $dataDir;
  $f = $dataDir . '/form-auto.json';
  $c = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
  return is_array($c) && !empty($c['actif']);
}
function autoEnvoiDefinir($actif) {
  global $dataDir;
  @file_put_contents($dataDir . '/form-auto.json', json_encode(['actif' => (bool) $actif]), LOCK_EX);
}

// 👤 Traite UNE personne pour l'envoi groupé : crée le compte si besoin puis
// envoie le mail d'invitation, ou renvoie le lien, ou l'ignore si déjà présente.
// $parNom = true → on considère « déjà dans le site » un compte au même
// prénom+nom (contrôle demandé pour la liste du formulaire) ; sinon par e-mail.
// Renvoie l'un de : cree | renvoye | deja_present | mail_ko | erreur.
function traiteInscritGroupe(PDO $db, array $p, $siteId, $heures, $parNom = false) {
  $email  = mb_strtolower(trim((string) ($p['email'] ?? '')));
  $prenom = trim(mb_substr((string) ($p['prenom'] ?? ''), 0, 40));
  $nom    = trim(mb_substr((string) ($p['nom'] ?? ''), 0, 60));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return 'erreur'; }
  try {
    if (function_exists('ensureUserAccountAccessColumns')) { ensureUserAccountAccessColumns($db); }
    // 1) Déjà présent ? Par prénom+nom (si demandé) OU par e-mail dans tous les cas.
    if ($parNom && $prenom !== '' && $nom !== '') {
      $q = $db->prepare('SELECT id FROM utilisateurs WHERE LOWER(TRIM(prenom)) = ? AND LOWER(TRIM(nom)) = ? LIMIT 1');
      $q->execute([mb_strtolower($prenom), mb_strtolower($nom)]);
      if ($q->fetchColumn() !== false) { return 'deja_present'; }
    }
    $st = $db->prepare('SELECT id, mot_de_passe, account_activation_pending FROM utilisateurs WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u) {
      // Compte actif (mot de passe choisi) → on ne redérange pas.
      if (empty($u['account_activation_pending']) && !empty($u['mot_de_passe'])) { return 'deja_present'; }
      return envoiFunActivation($db, (int) $u['id'], $heures) ? 'renvoye' : 'mail_ko';
    }
    // 2) Aucun compte : on le crée (comme à l'inscription) puis mail.
    $identifiant = identifiantLibre($db, $prenom !== '' ? $prenom : 'compte', $nom);
    $ins = $db->prepare('INSERT INTO utilisateurs (identifiant, nom, prenom, email, mot_de_passe, role, account_activation_pending, site_id, statut_date)
                         VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)');
    $ins->execute([$identifiant, $nom, $prenom, $email,
      password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'etudiant', $siteId, date('Y-m-d H:i:s')]);
    $uid = (int) $db->lastInsertId();
    if (envoiFunActivation($db, $uid, $heures)) { return 'cree'; }
    try { $db->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$uid]); } catch (Throwable $e) {}
    return 'mail_ko';
  } catch (Throwable $e) {
    return 'erreur';
  }
}

// 🏬 Renvoie le site_id (widget_sites.ville) du magasin courant, ou null.
function siteIdCourant(PDO $db) {
  global $SITES, $SITE;
  try {
    $qs = $db->prepare('SELECT id FROM widget_sites WHERE ville = ? LIMIT 1');
    $qs->execute([$SITES[$SITE]['ville']]);
    $v = $qs->fetchColumn();
    return $v !== false ? (int) $v : null;
  } catch (Throwable $e) { return null; }
}

// 🎁 ─── RÉCOMPENSES : mails automatiques « viens voir les RH » ───────────────
// Un jardin est-il TERMINÉ ? (grille pleine + les 3 lotus)
function jardinEstComplet($cases) {
  global $LOTUS_REQUIS, $JARDIN_CASES;
  if (!is_array($cases) || count($cases) < $JARDIN_CASES) { return false; }
  $lotus = [];
  foreach ($cases as $c) { $pl = is_array($c) ? (string) ($c['plante'] ?? '') : ''; if (in_array($pl, $LOTUS_REQUIS, true)) { $lotus[$pl] = true; } }
  return count($lotus) >= count($LOTUS_REQUIS);
}
// A-t-on déjà prévenu cette personne (identifiant minuscule) ? / marquer prévenu.
function dejaPrevenu($cle) {
  global $rhFile;
  $d = readJson($rhFile);
  return is_array($d) && !empty($d['prevenu'][$cle]);
}
function marquePrevenu($cle) {
  global $rhFile;
  withLock($rhFile, function (&$data, &$write) use ($cle) {
    if (!is_array($data)) { $data = []; }
    if (!isset($data['prevenu']) || !is_array($data['prevenu'])) { $data['prevenu'] = []; }
    $data['prevenu'][$cle] = 1; $write = true;
  });
}
// ✉️ Envoie le mail « viens voir les RH » à une personne (par identifiant
// minuscule). $info = ['type'=>'podium'|'jardin','rang'=>?]. true si parti.
function mailRecompense(PDO $db, $cle, $info) {
  try {
    $q = $db->prepare('SELECT email, prenom FROM utilisateurs WHERE LOWER(identifiant) = ? LIMIT 1');
    $q->execute([$cle]);
    $u = $q->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { return false; }
  $email = $u ? trim((string) ($u['email'] ?? '')) : '';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { return false; }
  $prenom = trim((string) ($u['prenom'] ?? ''));
  $bonjour = $prenom !== '' ? $prenom : 'à toi';
  if (($info['type'] ?? '') === 'podium') {
    $rang = (int) ($info['rang'] ?? 0);
    $sujet = '🏆 Bravo — ta récompense t\'attend chez Famiflora !';
    $intro = 'Félicitations, tu termines <b>' . ($rang === 1 ? '1er' : $rang . 'e') . '</b> du grand quiz Famiformation&nbsp;! 🎉';
    $quand = ' (à partir du <b>01/09</b>)';   // podium : remise à partir du 01/09
  } else {
    $sujet = '🎁 Bravo — ta récompense t\'attend chez Famiflora !';
    $intro = 'Bravo, tu as <b>terminé ton jardin</b> — tu fais partie des gagnants&nbsp;! 🌼';
    $quand = '';                              // jardin : récompense dispo dès maintenant (pas d\'attente)
  }
  $body = '<div style="font-family:Arial,sans-serif;color:#244230;max-width:560px;margin:0 auto;padding:24px;">'
    . '<p style="font-size:16px;">Bonjour ' . htmlspecialchars($bonjour, ENT_QUOTES, 'UTF-8') . ',</p>'
    . '<p style="font-size:16px;line-height:1.6;">' . $intro . '</p>'
    . '<p style="font-size:16px;line-height:1.6;">Pour <b>récupérer ta récompense</b>, présente-toi <b>auprès des RH</b> du magasin' . $quand . '. '
    . 'Une question&nbsp;? Écris à <a href="mailto:admin@famiformation.com">admin@famiformation.com</a>.</p>'
    . '<p style="font-size:15px;color:#617268;">Merci d\'avoir joué, et à bientôt&nbsp;! 🌱<br>L\'équipe Famiflora · Famiformation</p></div>';
  return function_exists('sendMail') ? sendMail($email, $sujet, $body, true) : false;
}
// 🏆 Envoi AUTOMATIQUE aux 3 vainqueurs, une fois l'heure des résultats passée
// (31/08 12h30, heure belge). Ne part qu'UNE fois (drapeau dans le fichier RH).
// Appelé paresseusement depuis une action fréquente (board) : le test d'heure est
// gratuit tant qu'on est avant, et une fois envoyé le drapeau court-circuite tout.
function verifieVainqueursAuto() {
  global $scoresFile, $rhFile, $COMPTES_TEST;
  static $faitCeTour = false;
  if ($faitCeTour) { return; }
  try { $heure = (new DateTime('2026-08-31 12:30:00', new DateTimeZone('Europe/Brussels')))->getTimestamp(); }
  catch (Throwable $e) { return; }
  if (time() < $heure) { return; }
  $d = readJson($rhFile);
  if (is_array($d) && !empty($d['vainqueurs_envoye'])) { return; }
  $faitCeTour = true;
  $db = famiDb();
  if (!$db) { return; }
  $joueurs = [];
  foreach ((is_array(readJson($scoresFile)) ? readJson($scoresFile) : []) as $p) {
    if (!is_array($p) || ($p['quiz_fait'] ?? true) === false) { continue; }
    if (in_array(mb_strtolower((string) ($p['name'] ?? '')), $COMPTES_TEST, true)) { continue; }
    $joueurs[] = $p;
  }
  usort($joueurs, function ($a, $b) {
    $x = floatval($b['score'] ?? 0) - floatval($a['score'] ?? 0);
    if ($x > 0) { return 1; } if ($x < 0) { return -1; }
    return intval($a['time'] ?? 0) - intval($b['time'] ?? 0);
  });
  $rang = 1;
  foreach (array_slice($joueurs, 0, 3) as $p) {
    $cle = mb_strtolower((string) $p['name']);
    if (!dejaPrevenu($cle) && mailRecompense($db, $cle, ['type' => 'podium', 'rang' => $rang])) { marquePrevenu($cle); }
    $rang++;
  }
  withLock($rhFile, function (&$data, &$write) { if (!is_array($data)) { $data = []; } $data['vainqueurs_envoye'] = 1; $write = true; });
}

// 🛡️ GARDE-FOU sur les actions qui ENVOIENT UN MAIL ou CRÉENT UN COMPTE.
// Sans ça, n'importe qui peut marteler l'inscription : boîte mail d'un tiers
// inondée, table des utilisateurs remplie de faux comptes.
//
// ⚠️ ATTENTION au réglage : à la borne du magasin, TOUS les visiteurs sortent
// par la MÊME adresse IP. Une limite serrée par IP bloquerait donc de vrais
// clients le jour de l'événement. On protège donc surtout PAR ADRESSE MAIL
// (3 envois/heure : personne ne peut inonder la boîte de quelqu'un), et on garde
// un plafond par IP très large, uniquement contre un script automatisé.
function tropDEnvois($cle, $max, $fenetre = 3600) {
  global $dataDir;
  $trop = false;
  withLock($dataDir . '/envois.json', function (&$journal, &$write) use ($cle, $max, $fenetre, &$trop) {
    $maintenant = time();
    if (!is_array($journal)) { $journal = []; }
    // On nettoie au passage tout ce qui est sorti de la fenêtre (le fichier ne
    // grossit donc jamais).
    foreach ($journal as $k => $dates) {
      $journal[$k] = array_values(array_filter((array) $dates, fn($t) => $maintenant - (int) $t < $fenetre));
      if (!$journal[$k]) { unset($journal[$k]); }
    }
    $miens = $journal[$cle] ?? [];
    if (count($miens) >= $max) { $trop = true; }
    else { $miens[] = $maintenant; $journal[$cle] = $miens; }
    $write = true;
    return null;
  });
  return $trop;
}

// Les deux garde-fous d'un envoi de mail : l'adresse visée d'abord (le vrai
// risque), l'IP ensuite (très large, pour ne pas gêner la borne du magasin).
function envoiRefuse($email) {
  $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnu');
  return tropDEnvois('mail:' . mb_strtolower($email), 3) || tropDEnvois('ip:' . $ip, 120);
}

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: [];

// 🏬 ON CLOISONNE PAR SITE. Chaque magasin a ses propres fichiers : un joueur, un
// score, un code, une question de Mouscron ne touchent JAMAIS ceux de La Panne.
// Le suffixe « -<site> » sur chaque fichier suffit à garantir qu'ils ne se
// croisent pas. Les jetons de session et le journal anti-abus restent communs.
$SITE = siteDe($input, $SITES, $SITE_DEFAUT);
$scoresFile    = $dataDir . "/scores-$SITE.json";
$codesFile     = $dataDir . "/codes-$SITE.json";
$questionsFile = $dataDir . "/questions-$SITE.json";
$jardinFile    = $dataDir . "/jardin-$SITE.json";
$configFile    = $dataDir . "/config-$SITE.json";
$rhFile        = $dataDir . "/rh-$SITE.json";   // récompenses remises (coché par les RH)
$BONUS_CODES   = array_merge($BONUS_CODES_PAR_SITE[$SITE], [$CODE_TEST_OK, $CODE_TEST_USED]);

switch ($action) {

  // 📊 Récupérer le classement (lecture seule)
  case 'board': {
    // 🏆 Déclencheur paresseux : passé le 31/08 12h30, envoie (une fois) les mails
    // aux 3 vainqueurs. Le board est appelé très souvent (télé, joueurs) → fiable.
    verifieVainqueursAuto();
    $board = readJson($scoresFile);
    // Au classement, on ne montre QUE ceux qui ont réellement joué : un compte
    // créé (réservé) mais pas encore joué (quiz_fait=false) ne pollue pas la liste.
    $board = array_values(array_filter($board, fn($p) => ($p['quiz_fait'] ?? true) && !estCompteTest($p)));
    sortBoard($board);
    echo json_encode($board, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🔢 Combien de codes bonus restent à trouver en magasin (hors codes de test).
  case 'codes_restants': {
    $claimed = readJson($codesFile);
    $reels = array_values(array_filter($BONUS_CODES, fn($c) => $c !== $CODE_TEST_OK && $c !== $CODE_TEST_USED));
    $total = count($reels);
    $pris = 0;
    foreach ($reels as $c) { if (isset($claimed[$c])) $pris++; }
    echo json_encode(['total' => $total, 'restants' => max(0, $total - $pris), 'pris' => $pris]);
    break;
  }

  // 🏁 Enregistrer un score.
  // La vérification du doublon et l'ajout se font DANS le même verrou : c'est ce
  // qui garantit qu'un seul « Marie » entre dans la liste, même si deux Marie
  // valident exactement au même instant.
  case 'submit': {
    $name = trim($input['name'] ?? '');
    // Jusqu'à 60 : un identifiant Famiformation (prenom.nom) peut dépasser 24.
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
      http_response_code(400);
      echo json_encode(['error' => 'Prénom invalide']);
      break;
    }
    // « code » = le code jardinier à 4 chiffres (secret rigolo qui sert à
    // récupérer son compte sur un autre téléphone). « nom » = Nom Prénom, saisi
    // facultativement, utile pour remettre les prix aux vrais gagnants.
    $codeJard = preg_replace('/\D/', '', (string)($input['code'] ?? ''));
    $entree = [
      'name'      => $name,                                   // clé du compte (= identifiant Famiformation)
      'pseudo'    => trim(mb_substr((string)($input['pseudo'] ?? ''), 0, 24)),  // nom AFFICHÉ au classement (choisi par le joueur)
      'code'      => substr($codeJard, 0, 4),                 // code secret à 4 chiffres
      'nom'       => trim(mb_substr((string)($input['nom'] ?? ''), 0, 60)),
      'score'     => max(0, round(floatval($input['score'] ?? 0), 1)),   // récolte (nombre à virgule)
      'bonus'     => 0,                                       // graines gagnées au mini-jeu
      'depensees' => 0,                                       // graines déjà plantées au jardin
      'correct'   => max(0, intval($input['correct'] ?? 0)),
      'codes'     => 0,                                       // nombre de codes bonus récupérés
      'codes_pris' => [],                                     // quels codes bonus ont été pris
      'time'      => max(0, intval($input['time'] ?? 0)),
      'date'      => date('c'),
    ];

    $res = withLock($scoresFile, function (&$board, &$write) use ($name, $entree, $input) {
      for ($i = 0; $i < count($board); $i++) {
        if (mb_strtolower($board[$i]['name'] ?? '') === mb_strtolower($name)) {
          // Compte existant. Il faut prouver que c'est bien SON compte : jeton
          // Famiformation valide, ou ancien code jardinier. Sinon, nom pris par
          // un autre (on refuse d'écraser sa récolte).
          if (!joueurAutorise($input, $name, $board[$i]['code'] ?? '')) {
            return ['conflit' => true];
          }
          // Quiz déjà fait : on ne réécrase pas la récolte (on renvoie l'état actuel).
          if (!empty($board[$i]['quiz_fait']) && !estCompteTest($board[$i])) {
            sortBoard($board);
            return ['deja' => true, 'board' => $board];
          }
          // Compte créé AVANT de jouer : on inscrit maintenant sa récolte du quiz.
          $board[$i]['score']    = $entree['score'];
          $board[$i]['correct']  = $entree['correct'];
          $board[$i]['time']     = $entree['time'];
          $board[$i]['quiz_fait'] = true;
          if ($entree['nom'] !== '') $board[$i]['nom'] = $entree['nom'];
          if ($entree['pseudo'] !== '') $board[$i]['pseudo'] = $entree['pseudo'];
          sortBoard($board);
          $write = true;
          return ['board' => $board];
        }
      }
      // Aucun compte à ce nom : création directe (joué sans passer par « créer un compte »).
      $entree['quiz_fait'] = true;
      $board[] = $entree;
      sortBoard($board);
      $write = true;
      return ['board' => $board];
    });

    if (!empty($res['conflit'])) {
      http_response_code(409);
      echo json_encode(['error' => 'nom_pris']);
      break;
    }
    echo json_encode($res['board'], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 👤 Vérifier si un prénom a déjà joué (avant de démarrer le quiz).
  // Simple confort : la vraie garantie est dans 'submit', sous verrou.
  case 'check': {
    $name = mb_strtolower(trim($input['name'] ?? ''));
    $board = readJson($scoresFile);
    foreach ($board as $p) {
      if (mb_strtolower($p['name'] ?? '') === $name) {
        echo json_encode(['exists' => true]);
        exit;
      }
    }
    echo json_encode(['exists' => false]);
    break;
  }

  // ✨ CRÉER (réserver) un compte AVANT de jouer : pseudo + code à 4 chiffres.
  // Le compte entre dans les données avec score 0 et quiz_fait=false (donc pas au
  // classement tant qu'il n'a pas joué). Si le nom existe déjà : c'est TOI si le
  // code correspond (reconnexion), sinon le nom est pris.
  case 'register': {
    $name = trim($input['name'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 24) {
      http_response_code(400); echo json_encode(['error' => 'Pseudo invalide']); break;
    }
    $code4 = substr(preg_replace('/\D/', '', (string)($input['code'] ?? '')), 0, 4);
    if (strlen($code4) !== 4) {
      http_response_code(400); echo json_encode(['error' => 'code_invalide']); break;
    }
    $nom = trim(mb_substr((string)($input['nom'] ?? ''), 0, 60));
    $prenom = trim(mb_substr((string)($input['prenom'] ?? ''), 0, 40));
    $res = withLock($scoresFile, function (&$board, &$write) use ($name, $code4, $nom, $prenom) {
      foreach ($board as $p) {
        if (mb_strtolower($p['name'] ?? '') === mb_strtolower($name)) {
          if ((string)($p['code'] ?? '') === $code4) {
            return ['ok' => true, 'exist' => true, 'name' => $p['name'],
                    'quiz_fait' => ($p['quiz_fait'] ?? true), 'recoltees' => round(floatval($p['score'] ?? 0), 1),
                    'solde' => soldeDe($p), 'nbCodes' => intval($p['codes'] ?? 0)];
          }
          return ['pris' => true];
        }
      }
      $board[] = ['name' => $name, 'code' => $code4, 'nom' => $nom, 'prenom' => $prenom, 'score' => 0, 'bonus' => 0,
        'depensees' => 0, 'correct' => 0, 'codes' => 0, 'codes_pris' => [], 'time' => 0,
        'quiz_fait' => false, 'date' => date('c')];
      $write = true;
      return ['ok' => true, 'exist' => false, 'name' => $name, 'quiz_fait' => false,
              'recoltees' => 0, 'solde' => 0, 'nbCodes' => 0];
    });
    if (!empty($res['pris'])) {
      http_response_code(409); echo json_encode(['error' => 'nom_pris']); break;
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  /* ---------- CONNEXION AVEC SON COMPTE FAMIFORMATION ---------- */

  // 🔑 Se connecter : identifiant OU email + mot de passe Famiformation.
  // Le quiz ne connaît aucun mot de passe : il demande à la base de l'app.
  case 'login_fami': {
    $ident = trim((string) ($input['identifiant'] ?? ''));
    $mdp   = (string) ($input['mdp'] ?? '');
    if ($ident === '' || $mdp === '') { echo json_encode(['ok' => false, 'reason' => 'vide']); break; }
    $db = famiDb();
    if (!$db) { http_response_code(503); echo json_encode(['ok' => false, 'reason' => 'base_indisponible']); break; }
    $stmt = $db->prepare('SELECT id, identifiant, prenom, nom, email, mot_de_passe, account_activation_pending
                          FROM utilisateurs WHERE identifiant = ? OR email = ? LIMIT 1');
    $stmt->execute([$ident, mb_strtolower($ident)]);
    $u = $stmt->fetch();
    if (!$u) { echo json_encode(['ok' => false, 'reason' => 'inconnu']); break; }
    // Compte créé mais jamais activé : inutile de parler de mot de passe, il n'en
    // a pas encore — on le renvoie vers son mail.
    if (!empty($u['account_activation_pending']) || empty($u['mot_de_passe'])) {
      echo json_encode(['ok' => false, 'reason' => 'a_activer']); break;
    }
    if (!password_verify($mdp, (string) $u['mot_de_passe'])) {
      echo json_encode(['ok' => false, 'reason' => 'mauvais_mdp']); break;
    }
    // Première partie ? On lui ouvre sa fiche de joueur. La clé reste le champ
    // `name` (= son identifiant Famiformation), ce qui garde tout le reste du
    // quiz inchangé : codes bonus, jardin, classement.
    $fiche = withLock($scoresFile, function (&$board, &$write) use ($u) {
      foreach ($board as &$p) {
        if (mb_strtolower((string) ($p['name'] ?? '')) === mb_strtolower((string) $u['identifiant'])) {
          $p['uid'] = (int) $u['id'];
          $p['prenom'] = $u['prenom']; $p['nom'] = $u['nom'];
          $write = true;
          return ['quiz_fait' => ($p['quiz_fait'] ?? true), 'recoltees' => round(floatval($p['score'] ?? 0), 1),
                  'solde' => soldeDe($p), 'nbCodes' => intval($p['codes'] ?? 0), 'pseudo' => ($p['pseudo'] ?? '')];
        }
      }
      unset($p);
      $board[] = ['name' => $u['identifiant'], 'uid' => (int) $u['id'], 'nom' => $u['nom'], 'prenom' => $u['prenom'],
        'score' => 0, 'bonus' => 0, 'depensees' => 0, 'correct' => 0, 'codes' => 0, 'codes_pris' => [],
        'time' => 0, 'quiz_fait' => false, 'date' => date('c')];
      $write = true;
      return ['quiz_fait' => false, 'recoltees' => 0, 'solde' => 0, 'nbCodes' => 0, 'pseudo' => ''];
    });
    echo json_encode(['ok' => true, 'jeton' => faitJeton($u['id'], $u['identifiant']),
      'joueur' => ['name' => $u['identifiant'], 'uid' => (int) $u['id'],
                   'prenom' => $u['prenom'], 'nom' => $u['nom']] + $fiche], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 👤 « C'est bien moi » : au chargement de la page, le navigateur renvoie son
  // jeton et on lui rend son état de jeu. Un jeton trafiqué ou périmé est rejeté
  // — c'est ça qui empêche de se faire passer pour quelqu'un d'autre.
  case 'moi': {
    $j = litJeton($input['jeton'] ?? '');
    if (!$j) { echo json_encode(['ok' => false, 'reason' => 'jeton_invalide']); break; }
    $board = readJson($scoresFile);
    foreach ($board as $p) {
      if (mb_strtolower((string) ($p['name'] ?? '')) === mb_strtolower($j['identifiant'])) {
        echo json_encode(['ok' => true, 'joueur' => [
          'name' => $p['name'], 'uid' => intval($p['uid'] ?? 0),
          'prenom' => $p['prenom'] ?? '', 'nom' => $p['nom'] ?? '', 'pseudo' => $p['pseudo'] ?? '',
          'quiz_fait' => ($p['quiz_fait'] ?? true), 'recoltees' => round(floatval($p['score'] ?? 0), 1),
          'solde' => soldeDe($p), 'nbCodes' => intval($p['codes'] ?? 0),
        ]], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
    echo json_encode(['ok' => false, 'reason' => 'inconnu']);
    break;
  }

  // ✉️ Pas encore de compte : prénom + nom + email. On crée le compte
  // Famiformation et le mail « Définir mon mot de passe » part DANS LA SECONDE.
  // Le lien reste valable $ACTIVATION_HEURES (assez pour s'inscrire bien avant
  // l'événement et n'activer que le jour J).
  case 'inscription_fami': {
    $prenom = trim(mb_substr((string) ($input['prenom'] ?? ''), 0, 40));
    $nom    = trim(mb_substr((string) ($input['nom'] ?? ''), 0, 60));
    $email  = mb_strtolower(trim((string) ($input['email'] ?? '')));
    if (mb_strlen($prenom) < 2 || mb_strlen($nom) < 2) {
      echo json_encode(['ok' => false, 'reason' => 'nom_manquant']); break;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      echo json_encode(['ok' => false, 'reason' => 'email_invalide']); break;
    }
    if (envoiRefuse($email)) { http_response_code(429); echo json_encode(['ok' => false, 'reason' => 'trop_dessais']); break; }

    $db = famiDb();
    if (!$db) { http_response_code(503); echo json_encode(['ok' => false, 'reason' => 'base_indisponible']); break; }

    $stmt = $db->prepare('SELECT id, mot_de_passe, account_activation_pending FROM utilisateurs WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $deja = $stmt->fetch();
    if ($deja) {
      // Compte déjà là : soit il est actif (qu'il se connecte), soit il attend
      // toujours son activation (on lui renvoie le mail).
      if (empty($deja['account_activation_pending']) && !empty($deja['mot_de_passe'])) {
        echo json_encode(['ok' => false, 'reason' => 'deja_inscrit']); break;
      }
      $envoye = sendAccountActivationEmail($db, (int) $deja['id'], $ACTIVATION_HEURES);
      echo json_encode(['ok' => (bool) $envoye, 'renvoye' => true,
        'reason' => $envoye ? null : 'mail_impossible'], JSON_UNESCAPED_UNICODE);
      break;
    }

    // 🎟️ Le Google Form (onglet « recolte de mail ») ne sert PLUS à décider si on
    // envoie le mail : SEULE la base de données compte (ci-dessus). La feuille
    // sert juste au contrôle des tickets glace. On la complète donc pour cette
    // personne, MAIS sans créer de doublon : on n'ajoute pas une ligne si son
    // prénom+nom (ou son e-mail) y est déjà, ou si elle est déjà en base.
    $fc = fluxCache();
    $cleNom = clePrenomNom($prenom, $nom);
    $connuNomBase = false;
    try {
      $qn = $db->prepare('SELECT 1 FROM utilisateurs WHERE LOWER(TRIM(prenom)) = ? AND LOWER(TRIM(nom)) = ? LIMIT 1');
      $qn->execute([mb_strtolower($prenom), mb_strtolower($nom)]);
      $connuNomBase = ($qn->fetchColumn() !== false);
    } catch (Throwable $e) { /* colonnes/table absentes en test */ }
    $dansFeuille = isset($fc['emails'][$email]) || isset($fc['noms'][$cleNom]);
    if (!$dansFeuille && !$connuNomBase) {
      pousseVersForm($prenom, $nom, $email);
    }

    // 🏬 Le magasin où la personne s'inscrit → son `site_id` dans la base, pour
    // pouvoir distinguer les inscrits de Mouscron de ceux de La Panne. On lit
    // l'id réel dans la table des sites de l'app (plutôt que de le supposer).
    $siteId = null;
    try {
      $qs = $db->prepare('SELECT id FROM widget_sites WHERE ville = ? LIMIT 1');
      $qs->execute([$SITES[$SITE]['ville']]);
      $trouve = $qs->fetchColumn();
      if ($trouve !== false) { $siteId = (int) $trouve; }
    } catch (Throwable $e) { /* table absente en test : on laissera site_id à NULL */ }

    try {
      ensureUserAccountAccessColumns($db);
      $identifiant = identifiantLibre($db, $prenom, $nom);
      $ins = $db->prepare('INSERT INTO utilisateurs (identifiant, nom, prenom, email, mot_de_passe, role, account_activation_pending, site_id, statut_date)
                           VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)');
      $ins->execute([$identifiant, $nom, $prenom, $email,
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'etudiant', $siteId, date('Y-m-d H:i:s')]);
      $uid = (int) $db->lastInsertId();
    } catch (Throwable $e) {
      http_response_code(500); echo json_encode(['ok' => false, 'reason' => 'creation_impossible']); break;
    }

    // Comme dans l'admin de l'app : un compte sans mail parti ne sert à rien, on
    // ne laisse pas de compte fantôme derrière nous.
    if (!sendAccountActivationEmail($db, $uid, $ACTIVATION_HEURES)) {
      try { $db->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$uid]); } catch (Throwable $e) {}
      http_response_code(500); echo json_encode(['ok' => false, 'reason' => 'mail_impossible']); break;
    }
    // Compte créé + mail envoyé (le seul cas « déjà donné ton mail » vient de la
    // base : compte en attente → on renvoie le lien, géré plus haut).
    echo json_encode(['ok' => true, 'identifiant' => $identifiant], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🔁 « Je n'ai pas reçu le mail » : on renvoie le lien d'activation.
  case 'renvoyer_activation': {
    $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      echo json_encode(['ok' => false, 'reason' => 'email_invalide']); break;
    }
    if (envoiRefuse($email)) { http_response_code(429); echo json_encode(['ok' => false, 'reason' => 'trop_dessais']); break; }
    $db = famiDb();
    if (!$db) { http_response_code(503); echo json_encode(['ok' => false, 'reason' => 'base_indisponible']); break; }
    $stmt = $db->prepare('SELECT id, mot_de_passe, account_activation_pending FROM utilisateurs WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    // On ne dit jamais si l'adresse est connue ou non : ça éviterait de pouvoir
    // deviner qui a un compte. Le message affiché est le même dans tous les cas.
    if ($u && (!empty($u['account_activation_pending']) || empty($u['mot_de_passe']))) {
      sendAccountActivationEmail($db, (int) $u['id'], $ACTIVATION_HEURES);
    }
    echo json_encode(['ok' => true]);
    break;
  }

  // 📣 ENVOI GROUPÉ (admin) : pour les gens déjà inscrits sur le formulaire mais
  // qui n'ont pas encore de compte. On leur crée le compte (si besoin) et on leur
  // envoie le mail « fun » d'invitation à créer leur compte AVANT le lancement.
  // On traite un LOT (max 25) par appel : l'admin envoie par tranches (pas de
  // timeout SMTP) et voit la progression.
  case 'envoi_groupe': {
    exigeAdmin($input);
    $db = famiDb();
    if (!$db) { http_response_code(503); echo json_encode(['ok' => false, 'reason' => 'base_indisponible']); break; }
    $siteId = siteIdCourant($db);

    $liste = is_array($input['liste'] ?? null) ? $input['liste'] : [];
    $liste = array_slice($liste, 0, 25);   // un lot à la fois
    $res = ['cree' => 0, 'renvoye' => 0, 'deja_present' => 0, 'echec' => 0];
    foreach ($liste as $p) {
      $etat = traiteInscritGroupe($db, $p, $siteId, $ACTIVATION_HEURES, false);
      if (isset($res[$etat])) { $res[$etat]++; } else { $res['echec']++; }
    }
    echo json_encode(['ok' => true] + $res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 👁️ APERÇU du formulaire (admin) : combien de personnes dans l'onglet
  // « recolte de mail », combien sont déjà dans le site (prénom+nom), combien
  // restent à contacter. Ne renvoie AUCUNE adresse au navigateur.
  case 'form_apercu': {
    exigeAdmin($input);
    $flux = litFluxFormulaire();
    if (!$flux['ok']) { echo json_encode(['ok' => false, 'reason' => $flux['reason']]); break; }
    $db = famiDb();
    if (!$db) { http_response_code(503); echo json_encode(['ok' => false, 'reason' => 'base_indisponible']); break; }
    if (function_exists('ensureUserAccountAccessColumns')) { ensureUserAccountAccessColumns($db); }
    $total = count($flux['lignes']);
    $dejaSite = 0;
    $qn = $db->prepare('SELECT id FROM utilisateurs WHERE (LOWER(TRIM(prenom)) = ? AND LOWER(TRIM(nom)) = ?) OR email = ? LIMIT 1');
    foreach ($flux['lignes'] as $p) {
      $qn->execute([mb_strtolower($p['prenom']), mb_strtolower($p['nom']), $p['email']]);
      if ($qn->fetchColumn() !== false) { $dejaSite++; }
    }
    echo json_encode(['ok' => true, 'total' => $total, 'deja' => $dejaSite, 'a_contacter' => max(0, $total - $dejaSite)], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 📣 ENVOI GROUPÉ DEPUIS LE FORMULAIRE (admin) : lit l'onglet « recolte de
  // mail », écarte les gens déjà dans le site (prénom+nom), et crée le compte +
  // envoie l'invitation aux autres. Par tranches : le client rappelle avec
  // ?debut=… jusqu'à ce que 'fini' soit vrai (pas de timeout SMTP).
  case 'envoi_groupe_form': {
    exigeAdmin($input);
    $flux = litFluxFormulaire();
    if (!$flux['ok']) { echo json_encode(['ok' => false, 'reason' => $flux['reason']]); break; }
    $db = famiDb();
    if (!$db) { http_response_code(503); echo json_encode(['ok' => false, 'reason' => 'base_indisponible']); break; }
    $siteId = siteIdCourant($db);

    $LOT = 20;
    $total = count($flux['lignes']);
    $debut = max(0, (int) ($input['debut'] ?? 0));
    $tranche = array_slice($flux['lignes'], $debut, $LOT);
    $res = ['cree' => 0, 'renvoye' => 0, 'deja_present' => 0, 'echec' => 0];
    foreach ($tranche as $p) {
      $etat = traiteInscritGroupe($db, $p, $siteId, $ACTIVATION_HEURES, true);  // contrôle par prénom+nom
      if (isset($res[$etat])) { $res[$etat]++; } else { $res['echec']++; }
    }
    $suivant = $debut + count($tranche);
    echo json_encode(['ok' => true, 'total' => $total, 'suivant' => $suivant, 'fini' => ($suivant >= $total)] + $res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // ⚡ NOUVELLE RÉPONSE AU FORMULAIRE (temps réel) : appelé par le déclencheur
  // onFormSubmit du script Google à CHAQUE envoi. Le script transmet prénom/nom/
  // e-mail + le secret. Si la personne n'a pas encore de compte (contrôle
  // prénom+nom), on lui crée son compte Mouscron et on envoie son lien.
  // Protégé par le secret (FORM_FEED_SECRET) et limité par adresse (anti-abus).
  case 'form_nouveau': {
    $secret = (string) ($input['secret'] ?? $_GET['secret'] ?? '');
    if ($FORM_FEED_SECRET === '' || !hash_equals($FORM_FEED_SECRET, $secret)) {
      http_response_code(401); echo json_encode(['ok' => false, 'reason' => 'secret']); break;
    }
    // 🔒 Interrupteur : tant que l'admin n'a pas activé l'envoi automatique, on
    // ne fait RIEN (aucun compte créé, aucun mail envoyé).
    if (!autoEnvoiActif()) { echo json_encode(['ok' => true, 'etat' => 'desactive']); break; }
    $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      echo json_encode(['ok' => false, 'reason' => 'email_invalide']); break;
    }
    // Garde-fou anti-abus : même avec un secret deviné, impossible de marteler
    // une adresse (le journal d'envois bloque au-delà du seuil).
    if (envoiRefuse($email)) { http_response_code(429); echo json_encode(['ok' => false, 'reason' => 'trop_dessais']); break; }
    $db = famiDb();
    if (!$db) { http_response_code(503); echo json_encode(['ok' => false, 'reason' => 'base_indisponible']); break; }

    // Le formulaire ne concerne que Mouscron → site_id de Mouscron.
    $siteId = null;
    try {
      $qs = $db->prepare('SELECT id FROM widget_sites WHERE ville = ? LIMIT 1');
      $qs->execute([$SITES['mouscron']['ville']]);
      $v = $qs->fetchColumn();
      if ($v !== false) { $siteId = (int) $v; }
    } catch (Throwable $e) { /* table absente en test */ }

    $etat = traiteInscritGroupe($db, [
      'prenom' => (string) ($input['prenom'] ?? ''),
      'nom'    => (string) ($input['nom'] ?? ''),
      'email'  => $email,
    ], $siteId, $ACTIVATION_HEURES, true);   // contrôle par prénom+nom

    echo json_encode(['ok' => in_array($etat, ['cree', 'renvoye', 'deja_present'], true), 'etat' => $etat], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🔒 Lire / changer l'interrupteur de l'envoi AUTOMATIQUE (admin). Sans champ
  // 'actif' → simple lecture de l'état. Avec 'actif' → on l'allume/éteint.
  case 'form_auto': {
    exigeAdmin($input);
    if (array_key_exists('actif', $input)) { autoEnvoiDefinir((bool) $input['actif']); }
    echo json_encode(['ok' => true, 'actif' => autoEnvoiActif()], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🎁 PAGE RH — LISTE DES RÉCOMPENSES à remettre (pour le magasin courant).
  // Deux sections : le PODIUM (top 3 par graines) et le JARDIN TERMINÉ (grille
  // pleine + les 3 lotus). Chaque personne a un état « remis » (coché par les RH).
  case 'rh_liste': {
    exigeRh($input);
    $board   = readJson($scoresFile);
    $jardins = readJson($jardinFile);
    $remises = readJson($rhFile);
    $remisPodium = (is_array($remises) && isset($remises['podium']) && is_array($remises['podium'])) ? $remises['podium'] : [];
    $remisJardin = (is_array($remises) && isset($remises['jardin']) && is_array($remises['jardin'])) ? $remises['jardin'] : [];

    $parNom = [];
    foreach ((is_array($board) ? $board : []) as $p) {
      if (is_array($p)) { $parNom[mb_strtolower((string) ($p['name'] ?? ''))] = $p; }
    }
    $ligne = function ($p, $cle) {
      return [
        'name'   => (string) ($p['name'] ?? $cle),
        'pseudo' => (string) ($p['pseudo'] ?? ''),
        'prenom' => (string) ($p['prenom'] ?? ''),
        'nom'    => (string) ($p['nom'] ?? ''),
        'score'  => round(floatval($p['score'] ?? 0), 1),
      ];
    };

    // 🏆 Podium : top 3 par score (quiz fait, hors comptes de test).
    $joueurs = [];
    foreach ((is_array($board) ? $board : []) as $p) {
      if (!is_array($p)) { continue; }
      if (($p['quiz_fait'] ?? true) === false) { continue; }
      if (in_array(mb_strtolower((string) ($p['name'] ?? '')), $COMPTES_TEST, true)) { continue; }
      $joueurs[] = $p;
    }
    usort($joueurs, function ($a, $b) {
      $d = floatval($b['score'] ?? 0) - floatval($a['score'] ?? 0);
      if ($d > 0) { return 1; } if ($d < 0) { return -1; }
      return intval($a['time'] ?? 0) - intval($b['time'] ?? 0);
    });
    $podium = [];
    $rang = 1;
    foreach (array_slice($joueurs, 0, 3) as $p) {
      $cle = mb_strtolower((string) ($p['name'] ?? ''));
      $podium[] = $ligne($p, $cle) + ['rang' => $rang, 'remis' => !empty($remisPodium[$cle])];
      $rang++;
    }

    // 🎁 Jardin terminé : grille pleine + les 3 lotus (or, argent, bronze).
    $jardin = [];
    if (is_array($jardins)) {
      foreach ($jardins as $cle => $cases) {
        if (!is_array($cases)) { continue; }
        $nb = count($cases);
        $lotus = [];
        foreach ($cases as $c) {
          $pl = is_array($c) ? (string) ($c['plante'] ?? '') : '';
          if (in_array($pl, $LOTUS_REQUIS, true)) { $lotus[$pl] = true; }
        }
        if ($nb >= $JARDIN_CASES && count($lotus) >= count($LOTUS_REQUIS)) {
          $p = $parNom[$cle] ?? ['name' => $cle];
          $jardin[] = $ligne($p, $cle) + ['remis' => !empty($remisJardin[$cle])];
        }
      }
    }
    usort($jardin, function ($a, $b) {
      $na = trim($a['prenom'] . ' ' . $a['nom']); if ($na === '') { $na = $a['name']; }
      $nb = trim($b['prenom'] . ' ' . $b['nom']); if ($nb === '') { $nb = $b['name']; }
      return strcasecmp($na, $nb);
    });

    echo json_encode(['ok' => true, 'podium' => $podium, 'jardin' => $jardin], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🎁 Cocher / décocher « récompense remise » pour une personne (RH).
  case 'rh_cocher': {
    exigeRh($input);
    $type = (($input['type'] ?? '') === 'podium') ? 'podium' : 'jardin';
    $cle  = mb_strtolower(trim((string) ($input['name'] ?? '')));
    if ($cle === '') { echo json_encode(['ok' => false, 'reason' => 'nom_manquant']); break; }
    $remis = !empty($input['remis']);
    withLock($rhFile, function (&$data, &$write) use ($type, $cle, $remis) {
      if (!is_array($data)) { $data = []; }
      if (!isset($data[$type]) || !is_array($data[$type])) { $data[$type] = []; }
      if ($remis) { $data[$type][$cle] = 1; } else { unset($data[$type][$cle]); }
      $write = true;
    });
    echo json_encode(['ok' => true, 'remis' => $remis]);
    break;
  }

  // ✉️ Prévenir par MAIL les vainqueurs (podium) + jardins terminés : message
  // simple « viens récupérer ta récompense auprès des RH ». On ne renvoie qu'une
  // fois par personne (suivi dans rh-<site>.json['prevenu']).
  case 'rh_mail': {
    exigeRh($input);
    $db = famiDb();
    if (!$db) { http_response_code(503); echo json_encode(['ok' => false, 'reason' => 'base_indisponible']); break; }
    $board   = readJson($scoresFile);
    $jardins = readJson($jardinFile);

    // Cibles : identifiant (minuscule) => ['type'=>podium|jardin, 'rang'=>?]
    $cibles = [];
    $joueurs = [];
    foreach ((is_array($board) ? $board : []) as $p) {
      if (!is_array($p)) { continue; }
      if (($p['quiz_fait'] ?? true) === false) { continue; }
      if (in_array(mb_strtolower((string) ($p['name'] ?? '')), $COMPTES_TEST, true)) { continue; }
      $joueurs[] = $p;
    }
    usort($joueurs, function ($a, $b) {
      $d = floatval($b['score'] ?? 0) - floatval($a['score'] ?? 0);
      if ($d > 0) { return 1; } if ($d < 0) { return -1; }
      return intval($a['time'] ?? 0) - intval($b['time'] ?? 0);
    });
    $rang = 1;
    foreach (array_slice($joueurs, 0, 3) as $p) { $cibles[mb_strtolower((string) $p['name'])] = ['type' => 'podium', 'rang' => $rang]; $rang++; }
    if (is_array($jardins)) {
      foreach ($jardins as $cle => $cases) {
        if (!is_array($cases)) { continue; }
        $nb = count($cases); $lotus = [];
        foreach ($cases as $c) { $pl = is_array($c) ? (string) ($c['plante'] ?? '') : ''; if (in_array($pl, $LOTUS_REQUIS, true)) { $lotus[$pl] = true; } }
        if ($nb >= $JARDIN_CASES && count($lotus) >= count($LOTUS_REQUIS) && !isset($cibles[$cle])) {
          $cibles[$cle] = ['type' => 'jardin'];
        }
      }
    }

    $deja = readJson($rhFile);
    $prevenus = (is_array($deja) && isset($deja['prevenu']) && is_array($deja['prevenu'])) ? $deja['prevenu'] : [];
    $res = ['envoye' => 0, 'deja' => 0, 'sans_mail' => 0, 'echec' => 0];
    $faits = [];
    foreach ($cibles as $cle => $info) {
      if (!empty($prevenus[$cle])) { $res['deja']++; continue; }
      try {
        $q = $db->prepare('SELECT email, prenom FROM utilisateurs WHERE LOWER(identifiant) = ? LIMIT 1');
        $q->execute([$cle]);
        $u = $q->fetch(PDO::FETCH_ASSOC);
      } catch (Throwable $e) { $u = null; }
      $email = $u ? trim((string) ($u['email'] ?? '')) : '';
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $res['sans_mail']++; continue; }
      $prenom = trim((string) ($u['prenom'] ?? ''));
      $bonjour = $prenom !== '' ? $prenom : 'à toi';
      if ($info['type'] === 'podium') {
        $sujet = '🏆 Bravo — ta récompense t\'attend chez Famiflora !';
        $intro = 'Félicitations, tu termines <b>' . ($info['rang'] == 1 ? '1er' : $info['rang'] . 'e') . '</b> du grand quiz Famiformation&nbsp;! 🎉';
        $quand = ' (à partir du <b>01/09</b>)';   // podium : remise à partir du 01/09
      } else {
        $sujet = '🎁 Bravo — ta récompense t\'attend chez Famiflora !';
        $intro = 'Bravo, tu as <b>terminé ton jardin</b> — tu fais partie des gagnants&nbsp;! 🌼';
        $quand = '';                              // jardin : récompense dispo dès maintenant
      }
      $body = '<div style="font-family:Arial,sans-serif;color:#244230;max-width:560px;margin:0 auto;padding:24px;">'
        . '<p style="font-size:16px;">Bonjour ' . htmlspecialchars($bonjour, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p style="font-size:16px;line-height:1.6;">' . $intro . '</p>'
        . '<p style="font-size:16px;line-height:1.6;">Pour <b>récupérer ta récompense</b>, présente-toi <b>auprès des RH</b> du magasin' . $quand . '. '
        . 'Une question&nbsp;? Écris à <a href="mailto:admin@famiformation.com">admin@famiformation.com</a>.</p>'
        . '<p style="font-size:15px;color:#617268;">Merci d\'avoir joué, et à bientôt&nbsp;! 🌱<br>L\'équipe Famiflora · Famiformation</p></div>';
      $ok = function_exists('sendMail') ? sendMail($email, $sujet, $body, true) : false;
      if ($ok) { $res['envoye']++; $faits[] = $cle; } else { $res['echec']++; }
    }
    if ($faits) {
      withLock($rhFile, function (&$data, &$write) use ($faits) {
        if (!is_array($data)) { $data = []; }
        if (!isset($data['prevenu']) || !is_array($data['prevenu'])) { $data['prevenu'] = []; }
        foreach ($faits as $c) { $data['prevenu'][$c] = 1; }
        $write = true;
      });
    }
    echo json_encode(['ok' => true] + $res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🎁 Valider un code bonus (usage unique, premier arrivé premier servi).
  // « Premier arrivé premier servi » n'a de sens que si le test et la prise du
  // code sont indissociables : deux personnes qui scannent le MÊME QR code au
  // même moment doivent départager, pas gagner toutes les deux.
  case 'claim': {
    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    if (!in_array($code, $BONUS_CODES, true)) {
      echo json_encode(['ok' => false, 'reason' => 'inconnu']);
      break;
    }

    $res = withLock($codesFile, function (&$claimed, &$write) use ($code, $name) {
      if (isset($claimed[$code])) {
        return ['ok' => false, 'reason' => 'deja_pris'];
      }
      $claimed[$code] = ['par' => $name, 'date' => date('c')];
      $write = true;
      return ['ok' => true];
    });

    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🌼 Le catalogue des plantes (public : affiché sur la page du jardin).
  case 'plantes': {
    echo json_encode(['plantes' => $PLANTES, 'cases' => $JARDIN_CASES], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🌳 La grille du jardin PERSONNEL d'un joueur (lecture seule). Chacun a la
  // sienne : le fichier est un dictionnaire pseudo(min) → { case → plante }.
  case 'jardin': {
    $name = mb_strtolower(trim($input['name'] ?? ''));
    $j = readJson($jardinFile);
    $cases = ($name !== '' && isset($j[$name]) && is_array($j[$name])) ? $j[$name] : [];
    echo json_encode(['cases' => (object)$cases], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 💰 Le solde d'un joueur qui revient (rechargement de page, autre appareil).
  case 'solde': {
    $name = mb_strtolower(trim($input['name'] ?? ''));
    $board = readJson($scoresFile);
    foreach ($board as $p) {
      if (mb_strtolower($p['name'] ?? '') === $name) {
        echo json_encode([
          'exists'    => true,
          'name'      => $p['name'],
          'recoltees' => round(floatval($p['score'] ?? 0), 1),
          'depensees' => intval($p['depensees'] ?? 0),
          'solde'     => soldeDe($p),
          'quiz_fait' => ($p['quiz_fait'] ?? true),
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
    echo json_encode(['exists' => false]);
    break;
  }

  // 🌱 Planter : débiter les graines PUIS poser la plante.
  // Deux fichiers sont touchés (scores + jardin) : on prend les verrous l'un
  // APRÈS l'autre, jamais imbriqués (deux verrous imbriqués pris dans des ordres
  // différents par deux requêtes = blocage mutuel). Si la case est prise entre
  // les deux étapes, on rembourse — le joueur ne perd jamais de graines pour rien.
  case 'planter': {
    $name   = trim($input['name'] ?? '');
    $idx    = intval($input['case'] ?? -1);
    $plante = trim($input['plante'] ?? '');

    if (!isset($PLANTES[$plante])) { echo json_encode(['ok' => false, 'reason' => 'plante_inconnue']); break; }
    if ($idx < 0 || $idx >= $JARDIN_CASES) { echo json_encode(['ok' => false, 'reason' => 'case_invalide']); break; }
    $cout = $PLANTES[$plante]['cout'];

    // Étape 1 : débit des graines, sous verrou des scores.
    $debit = withLock($scoresFile, function (&$board, &$write) use ($name, $cout) {
      foreach ($board as &$p) {
        if (mb_strtolower($p['name'] ?? '') === mb_strtolower($name)) {
          $solde = soldeDe($p);
          if ($solde < $cout) { return ['ok' => false, 'reason' => 'solde_insuffisant', 'solde' => $solde]; }
          $p['depensees'] = intval($p['depensees'] ?? 0) + $cout;
          $write = true;
          return ['ok' => true, 'solde' => $solde - $cout];
        }
      }
      return ['ok' => false, 'reason' => 'joueur_inconnu'];
    });
    if (empty($debit['ok'])) { echo json_encode($debit, JSON_UNESCAPED_UNICODE); break; }

    // Étape 2 : pose de la plante dans MON jardin (grille personnelle), sous verrou.
    $pose = withLock($jardinFile, function (&$j, &$write) use ($idx, $plante, $name) {
      $key = mb_strtolower($name);
      if (!isset($j[$key]) || !is_array($j[$key])) { $j[$key] = []; }
      if (isset($j[$key][$idx])) { return ['ok' => false, 'reason' => 'case_prise']; }
      $j[$key][$idx] = ['plante' => $plante, 'par' => $name, 'date' => date('c')];
      $write = true;
      return ['ok' => true];
    });

    if (empty($pose['ok'])) {
      // La case a été prise entre-temps : on rembourse le débit de l'étape 1.
      withLock($scoresFile, function (&$board, &$write) use ($name, $cout) {
        foreach ($board as &$p) {
          if (mb_strtolower($p['name'] ?? '') === mb_strtolower($name)) {
            $p['depensees'] = max(0, intval($p['depensees'] ?? 0) - $cout);
            $write = true;
            break;
          }
        }
        return null;
      });
      echo json_encode($pose, JSON_UNESCAPED_UNICODE);
      break;
    }

    // 🎁 Le jardin vient-il d'être TERMINÉ (grille pleine + 3 lotus) ? Si oui et
    // pas déjà prévenu, on envoie AUTOMATIQUEMENT le mail « viens voir les RH ».
    // Les comptes de test (testeur/admin_) reçoivent AUSSI ce mail : c'est ce qui
    // permet à l'organisateur de tester l'envoi en jouant avec admin_. (Le mail
    // part vers l'adresse du compte, donc la sienne.) On ne bloque jamais la
    // plantation là-dessus.
    $cleNom = mb_strtolower($name);
    if (!dejaPrevenu($cleNom)) {
      $jTout = readJson($jardinFile);
      if (jardinEstComplet($jTout[$cleNom] ?? [])) {
        try {
          $dbR = famiDb();
          if ($dbR && mailRecompense($dbR, $cleNom, ['type' => 'jardin'])) { marquePrevenu($cleNom); }
        } catch (Throwable $e) { /* le mail est « au mieux », jamais bloquant */ }
      }
    }
    echo json_encode(['ok' => true, 'solde' => $debit['solde']], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 💸 REVENDRE une plante que J'AI plantée : la case se libère et je récupère
  // exactement ce que j'avais payé. On ne peut revendre QUE ses propres plantes
  // (vérifié par le prénom), pas celles des autres.
  case 'revendre': {
    $name = trim($input['name'] ?? '');
    $idx  = intval($input['case'] ?? -1);

    // Étape 1 : retirer la plante de MON jardin (c'est forcément la mienne), sous verrou.
    $retiree = withLock($jardinFile, function (&$j, &$write) use ($idx, $name) {
      $key = mb_strtolower($name);
      if (!isset($j[$key][$idx])) { return ['ok' => false, 'reason' => 'case_vide']; }
      $c = $j[$key][$idx];
      unset($j[$key][$idx]);
      $write = true;
      return ['ok' => true, 'plante' => $c['plante']];
    });
    if (empty($retiree['ok'])) { echo json_encode($retiree, JSON_UNESCAPED_UNICODE); break; }

    // Étape 2 : rembourser le coût (on diminue les graines dépensées), sous
    // verrou des scores. On renvoie le nouveau solde disponible.
    $cout = $PLANTES[$retiree['plante']]['cout'] ?? 0;
    $res = withLock($scoresFile, function (&$board, &$write) use ($name, $cout) {
      foreach ($board as &$p) {
        if (mb_strtolower($p['name'] ?? '') === mb_strtolower($name)) {
          $p['depensees'] = max(0, intval($p['depensees'] ?? 0) - $cout);
          $write = true;
          return ['ok' => true, 'solde' => soldeDe($p), 'rendu' => $cout];
        }
      }
      return ['ok' => true, 'solde' => null, 'rendu' => $cout];  // plante retirée quand même
    });
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🌿 RÉCOLTE DES MAUVAISES HERBES (mini-jeu) : la page envoie combien d'herbes
  // de chaque sorte ont été tapées ; le serveur RECALCULE les graines avec sa
  // propre table et plafonne le total, puis les crédite au « bonus » du joueur
  // (graines à planter, sans impact sur le classement).
  case 'recolte_herbes': {
    $name = trim($input['name'] ?? '');
    $h = is_array($input['herbes'] ?? null) ? $input['herbes'] : [];

    // Score du mini-jeu = herbes attrapées × leur valeur (normale 1, bronze 2,
    // argent 3, or 5). Ce score est ensuite CONVERTI en graines : 1 graine par
    // tranche de 10 points, plafonné à 10 graines par partie (ex. 10→1, 20→2,
    // 100→10, plus de 100 → 10).
    $score = 0;
    foreach ($HERBE_GAIN as $sorte => $valeur) {
      $n = max(0, min($HERBE_MAX_PAR_HERBE, intval($h[$sorte] ?? 0)));
      $score += $n * $valeur;
    }
    $gain = min($HERBE_MAX_GAIN, intdiv($score, 10));
    if ($gain <= 0) { echo json_encode(['ok' => false, 'reason' => 'rien']); break; }

    $res = withLock($scoresFile, function (&$board, &$write) use ($name, $gain) {
      foreach ($board as &$p) {
        if (mb_strtolower($p['name'] ?? '') === mb_strtolower($name)) {
          $p['bonus'] = intval($p['bonus'] ?? 0) + $gain;
          $write = true;
          return ['ok' => true, 'gain' => $gain, 'solde' => soldeDe($p)];
        }
      }
      return ['ok' => false, 'reason' => 'joueur_inconnu'];
    });
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🎯 QUIZ DU JARDIN : on a rejoué un quiz pour alimenter le jardin. Chaque bonne
  // réponse rapporte des graines de JARDIN (bonus), JAMAIS le classement. Authentifié
  // par le JETON (le nom vient du jeton, pas du client), plafonné par partie.
  case 'quiz_jardin': {
    $auth = litJeton($input['jeton'] ?? '');
    if (!$auth) { http_response_code(401); echo json_encode(['ok' => false, 'reason' => 'auth']); break; }
    $correct = max(0, min((int)($input['correct'] ?? 0), $QUIZ_JARDIN_MAX_BONNES));
    $gain = $correct * $QUIZ_JARDIN_PAR_BONNE;
    if ($gain <= 0) { echo json_encode(['ok' => false, 'reason' => 'rien']); break; }
    $name = $auth['identifiant'];
    $res = withLock($scoresFile, function (&$board, &$write) use ($name, $gain) {
      foreach ($board as &$p) {
        if (mb_strtolower($p['name'] ?? '') === mb_strtolower($name)) {
          $p['bonus'] = intval($p['bonus'] ?? 0) + $gain;
          $write = true;
          return ['ok' => true, 'gain' => $gain, 'solde' => soldeDe($p),
                  'recoltees' => round(floatval($p['score'] ?? 0), 1), 'nbCodes' => intval($p['codes'] ?? 0)];
        }
      }
      return ['ok' => false, 'reason' => 'inconnu'];
    });
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // ⏱ Les dates de l'événement (lues par la page joueur pour le compte à rebours).
  // On y joint la « version » du site (date du dernier déploiement de la page) :
  // la télé, qui reste allumée des jours entiers, s'en sert pour se recharger
  // toute seule après une mise en ligne au lieu de garder l'ancienne page.
  case 'config_get': {
    $conf = ladConfig($configFile);
    $conf['version'] = (string) (@filemtime(__DIR__ . '/index.html') ?: 0);
    echo json_encode($conf, JSON_UNESCAPED_UNICODE);
    break;
  }

  // ⏱ Enregistrer les dates de l'événement (admin).
  case 'config_set': {
    exigeAdmin($input);
    $lancement = trim($input['lancement'] ?? '');
    $cloture   = trim($input['cloture'] ?? '');
    $resultats = trim(mb_substr((string)($input['resultats'] ?? ''), 0, 40));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $lancement)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'reason' => 'date_lancement_invalide']);
      break;
    }
    if ($cloture !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $cloture)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'reason' => 'date_cloture_invalide']);
      break;
    }
    $actuel = ladConfig($configFile);
    // Zones (facultatif) : liste { nom, nb }. On nettoie et on plafonne à 30.
    $zones = $actuel['zones'];
    if (isset($input['zones']) && is_array($input['zones'])) {
      $zones = [];
      foreach (array_slice($input['zones'], 0, 30) as $z) {
        $nom = trim(mb_substr((string)($z['nom'] ?? ''), 0, 60));
        $nb  = max(0, intval($z['nb'] ?? 0));
        if ($nom !== '') { $zones[] = ['nom' => $nom, 'nb' => $nb]; }
      }
    }
    $recompenses = $actuel['recompenses'];
    if (isset($input['recompenses']) && is_array($input['recompenses'])) {
      $recompenses = [];
      foreach (array_slice($input['recompenses'], 0, 5) as $r) {
        $t = trim(mb_substr((string)$r, 0, 120));
        if ($t !== '') { $recompenses[] = $t; }
      }
    }
    writeJson($configFile, [
      'lancement' => $lancement,
      'cloture'   => $cloture !== '' ? $cloture : $actuel['cloture'],
      'resultats' => $resultats !== '' ? $resultats : $actuel['resultats'],
      'zones'     => $zones,
      'recompenses' => $recompenses,
    ]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🌱 RÉCUPÉRER SON COMPTE sur un autre téléphone : pseudo + code à 4 chiffres.
  // (Au quotidien, le téléphone reconnaît le joueur tout seul via son stockage
  // local ; cette action ne sert qu'au rattrapage.)
  case 'login_joueur': {
    // On se connecte avec SON PSEUDO **ou** SON NOM (l'un ou l'autre), + le mot de passe.
    $name  = trim($input['name'] ?? '');
    $code4 = preg_replace('/\D/', '', (string)($input['code'] ?? ''));
    $board = readJson($scoresFile);
    foreach ($board as $p) {
      // Les comptes Famiformation (sans code jardinier) ne se récupèrent PAS par
      // ce chemin : ils passent par la connexion Famiformation (login_fami).
      if ((string)($p['code'] ?? '') === '') { continue; }
      $parPseudo = mb_strtolower($p['name'] ?? '') === mb_strtolower($name);
      $complet   = trim(trim((string)($p['prenom'] ?? '')) . ' ' . trim((string)($p['nom'] ?? '')));
      $parNom    = $complet !== '' && mb_strtolower($complet) === mb_strtolower($name);
      if ($parPseudo || $parNom) {
        if ((string)($p['code'] ?? '') !== $code4) {
          echo json_encode(['exists' => true, 'mauvais_code' => true]);
          exit;
        }
        echo json_encode([
          'exists'    => true,
          'name'      => $p['name'],
          'recoltees' => round(floatval($p['score'] ?? 0), 1),
          'solde'     => soldeDe($p),
          'nbCodes'   => intval($p['codes'] ?? 0),
          'quiz_fait' => ($p['quiz_fait'] ?? true),
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }
    }
    echo json_encode(['exists' => false]);
    break;
  }

  // 🎁 STATUT d'un code bonus (quand on scanne son QR) : existe-t-il, est-il pris,
  // et — si on donne le pseudo — est-ce MOI qui l'ai déjà (pour un message adapté) ?
  case 'code_status': {
    $bonus = strtoupper(trim($input['bonuscode'] ?? ''));
    $name  = trim($input['name'] ?? '');
    // 🧪 Codes de test : réponse fixe (dispo / déjà utilisé), sans toucher aux données.
    if ($bonus === $CODE_TEST_USED) { echo json_encode(['connu' => true, 'pris' => true, 'parMoi' => false]); break; }
    if ($bonus === $CODE_TEST_OK)   { echo json_encode(['connu' => true, 'pris' => false, 'parMoi' => false]); break; }
    $connu = in_array($bonus, $BONUS_CODES, true);
    $pris  = false; $parMoi = false;
    if ($connu) {
      $claimed = readJson($codesFile);
      if (isset($claimed[$bonus])) {
        $pris = true;
        $parMoi = ($name !== '' && mb_strtolower($claimed[$bonus]['par'] ?? '') === mb_strtolower($name));
      }
    }
    echo json_encode(['connu' => $connu, 'pris' => $pris, 'parMoi' => $parMoi], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🎁 RÉCUPÉRER un code bonus et l'associer à son compte (+ graines).
  // Authentifié par pseudo + code à 4 chiffres. Usage unique (premier servi),
  // et maximum $MAX_CODES par personne. Les graines comptent dans le classement.
  case 'code_claim': {
    $name  = trim($input['name'] ?? '');
    $code4 = preg_replace('/\D/', '', (string)($input['code'] ?? ''));
    $bonus = strtoupper(trim($input['bonuscode'] ?? ''));

    if (!in_array($bonus, $BONUS_CODES, true)) { echo json_encode(['ok' => false, 'reason' => 'inconnu']); break; }
    // 🧪 Le code de test « déjà utilisé » refuse toujours.
    if ($bonus === $CODE_TEST_USED) { echo json_encode(['ok' => false, 'reason' => 'deja_pris']); break; }

    // Étape 1 : authentifier (jeton Famiformation ou code jardinier) + vérifier
    // qu'il peut encore prendre un code.
    $chk = withLock($scoresFile, function (&$board, &$write) use ($name, $bonus, $MAX_CODES, $input) {
      foreach ($board as $p) {
        if (mb_strtolower($p['name'] ?? '') === mb_strtolower($name)) {
          if (!joueurAutorise($input, $name, $p['code'] ?? '')) { return ['ok' => false, 'reason' => 'auth']; }
          $pris = $p['codes_pris'] ?? [];
          if (in_array($bonus, $pris, true)) { return ['ok' => false, 'reason' => 'deja_a_toi']; }
          if (count($pris) >= $MAX_CODES) { return ['ok' => false, 'reason' => 'max_atteint', 'max' => $MAX_CODES]; }
          return ['ok' => true];
        }
      }
      return ['ok' => false, 'reason' => 'joueur_inconnu'];
    });
    if (empty($chk['ok'])) {
      if (($chk['reason'] ?? '') === 'auth' || ($chk['reason'] ?? '') === 'joueur_inconnu') { http_response_code(401); }
      echo json_encode($chk, JSON_UNESCAPED_UNICODE);
      break;
    }

    // Étape 2 : réserver le code globalement (premier arrivé, premier servi).
    // Le code de test « qui marche » n'est JAMAIS consommé : on saute cette étape
    // pour qu'il reste disponible et re-testable.
    if ($bonus !== $CODE_TEST_OK) {
      $prise = withLock($codesFile, function (&$claimed, &$write) use ($bonus, $name) {
        if (isset($claimed[$bonus])) { return ['ok' => false, 'reason' => 'deja_pris']; }
        $claimed[$bonus] = ['par' => $name, 'date' => date('c')];
        $write = true;
        return ['ok' => true];
      });
      if (empty($prise['ok'])) { echo json_encode($prise, JSON_UNESCAPED_UNICODE); break; }
    }

    // Étape 3 : créditer les graines (elles comptent dans le classement).
    $res = withLock($scoresFile, function (&$board, &$write) use ($name, $bonus, $CODE_GRAINES) {
      foreach ($board as &$p) {
        if (mb_strtolower($p['name'] ?? '') === mb_strtolower($name)) {
          $p['score'] = round(floatval($p['score'] ?? 0) + $CODE_GRAINES, 1);
          $p['codes_pris'] = array_values(array_merge($p['codes_pris'] ?? [], [$bonus]));
          $p['codes'] = count($p['codes_pris']);
          $write = true;
          return ['ok' => true, 'gagne' => $CODE_GRAINES, 'recoltees' => round(floatval($p['score']), 1), 'solde' => soldeDe($p), 'nbCodes' => $p['codes']];
        }
      }
      return ['ok' => true, 'gagne' => $CODE_GRAINES];
    });
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🚫 BLOQUER un code (admin) : il devient indisponible pour tout le monde, sans
  // etre attribue a un joueur (code perdu, carte abimee, retiree du magasin...).
  case 'code_bloquer': {
    exigeAdmin($input);
    $bonus = strtoupper(trim($input['code'] ?? ''));
    if (!in_array($bonus, $BONUS_CODES, true)) { echo json_encode(['ok' => false, 'reason' => 'inconnu']); break; }
    $res = withLock($codesFile, function (&$claimed, &$write) use ($bonus) {
      if (isset($claimed[$bonus])) { return ['ok' => false, 'reason' => 'deja_pris']; }
      $claimed[$bonus] = ['par' => 'Organisateur', 'date' => date('c'), 'bloque' => true];
      $write = true;
      return ['ok' => true];
    });
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🔓 LIBÉRER un code déjà attribué (admin) : le code redevient disponible pour
  // tout le monde, et la personne qui l'avait perd les graines correspondantes.
  // Deux verrous pris l'un APRÈS l'autre (jamais imbriqués).
  case 'code_liberer': {
    exigeAdmin($input);
    $bonus = strtoupper(trim($input['code'] ?? ''));
    if (!in_array($bonus, $BONUS_CODES, true)) {
      echo json_encode(['ok' => false, 'reason' => 'inconnu']); break;
    }
    // Étape 1 : retirer le code du registre (et retenir à qui il appartenait).
    $lib = withLock($codesFile, function (&$claimed, &$write) use ($bonus) {
      if (!isset($claimed[$bonus])) return ['ok' => false, 'reason' => 'pas_pris'];
      $par = $claimed[$bonus]['par'] ?? '';
      unset($claimed[$bonus]);
      $write = true;
      return ['ok' => true, 'par' => $par];
    });
    if (empty($lib['ok'])) { echo json_encode($lib, JSON_UNESCAPED_UNICODE); break; }

    // Étape 2 : le retirer au joueur et lui reprendre les graines du code.
    $par = (string)($lib['par'] ?? '');
    if ($par !== '') {
      withLock($scoresFile, function (&$board, &$write) use ($par, $bonus, $CODE_GRAINES) {
        for ($i = 0; $i < count($board); $i++) {
          if (mb_strtolower($board[$i]['name'] ?? '') === mb_strtolower($par)) {
            $pris = array_values(array_filter($board[$i]['codes_pris'] ?? [],
              fn($c) => strtoupper((string)$c) !== $bonus));
            $board[$i]['codes_pris'] = $pris;
            $board[$i]['codes'] = count($pris);
            $board[$i]['score'] = max(0, round(floatval($board[$i]['score'] ?? 0) - $CODE_GRAINES, 1));
            sortBoard($board);
            $write = true;
            break;
          }
        }
        return null;
      });
    }
    echo json_encode(['ok' => true, 'par' => $par, 'graines' => $CODE_GRAINES], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🧪 Créer (ou remettre à zéro) le COMPTE DE TEST sur le VRAI site, pour pouvoir
  // essayer le jeu depuis la borne, un téléphone ou un ordi. Pseudo « Testeur »,
  // mot de passe « 0000 ». Il n'apparaît jamais au classement public.
  case 'compte_test_creer': {
    exigeAdmin($input);
    $nomTest = 'Testeur'; $mdpTest = '0000';
    $res = withLock($scoresFile, function (&$board, &$write) use ($nomTest, $mdpTest) {
      for ($i = 0; $i < count($board); $i++) {
        if (mb_strtolower($board[$i]['name'] ?? '') === mb_strtolower($nomTest)) {
          $board[$i]['code'] = $mdpTest;
          $board[$i]['score'] = 0; $board[$i]['bonus'] = 0; $board[$i]['depensees'] = 0;
          $board[$i]['codes'] = 0; $board[$i]['codes_pris'] = []; $board[$i]['quiz_fait'] = false;
          $write = true;
          return ['ok' => true, 'remis' => true];
        }
      }
      $board[] = ['name' => $nomTest, 'code' => $mdpTest, 'nom' => 'Test', 'prenom' => 'Compte',
        'score' => 0, 'bonus' => 0, 'depensees' => 0, 'correct' => 0, 'codes' => 0,
        'codes_pris' => [], 'time' => 0, 'quiz_fait' => false, 'date' => date('c')];
      $write = true;
      return ['ok' => true, 'remis' => false];
    });
    echo json_encode(array_merge($res, ['pseudo' => $nomTest, 'mdp' => $mdpTest]), JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🔐 Connexion admin. La vérification se fait ICI, côté serveur : ainsi le mot
  // de passe n'apparaît PAS dans le code source de la page (contrairement à
  // l'ancien PIN, que n'importe qui pouvait lire avec « afficher la source »).
  case 'login': {
    $id  = trim($input['id'] ?? '');
    $pwd = (string)($input['pwd'] ?? '');
    $ok = hash_equals($ADMIN_ID, $id) && hash_equals($ADMIN_PWD, $pwd);
    if (!$ok) {
      http_response_code(401);
      echo json_encode(['ok' => false]);
      break;
    }
    echo json_encode(['ok' => true]);
    break;
  }

  // ❓ Les questions du quiz (appelé par la page joueur au chargement).
  case 'questions': {
    echo json_encode(lesQuestions($questionsFile, $QUESTIONS_DEFAUT), JSON_UNESCAPED_UNICODE);
    break;
  }

  // ✏️ Enregistrer les questions (admin). Remplace la liste entière.
  case 'questions_save': {
    exigeAdmin($input);
    $propres = [];
    foreach ((array)($input['questions'] ?? []) as $item) {
      $c = nettoieQuestion($item);
      if ($c) { $propres[] = $c; }
    }
    if (!$propres) {
      http_response_code(400);
      echo json_encode(['error' => 'Il faut au moins une question valide (un intitulé et deux réponses).']);
      break;
    }
    writeJson($questionsFile, $propres);
    echo json_encode(['ok' => true, 'total' => count($propres)]);
    break;
  }

  // 🌱 CHARGER TOUTES LES QUESTIONS en un clic pour le magasin courant :
  //   • entreprise → les ~613 questions de la base Famiformation (table quiz_questions),
  //     + un petit fichier d'extras (ex. année de création), avec une réponse
  //     fausse rigolote ajoutée à chacune ;
  //   • culture   → seed/culture.json (jardinage) ;
  //   • fun       → seed/fun.json.
  // Écrit dans questions-<site>.json (à relancer pour chaque magasin).
  case 'questions_seed': {
    exigeAdmin($input);
    $RIGOLOTES = [
      "Rien du tout, on improvise 😅", "Demander à un collègue 🤷", "Appeler Jimmy 📞",
      "42, évidemment", "Ça dépend de la météo ☀️", "Un bon barbecue 🍖", "Comme d'habitude, au feeling 😎",
      "Aucune idée, mais ça sonne bien", "La même chose qu'hier", "Fermer les yeux et espérer 🤞",
      "C'est écrit nulle part, donc non", "Un café d'abord ☕", "Google le sait mieux que moi",
      "On verra ça lundi", "Poser la question à l'accueil 🙋",
    ];
    // Version NL des mauvaises réponses rigolotes (même ordre que $RIGOLOTES).
    $RIGOLOTES_NL = [
      "Niets, we improviseren 😅", "Aan een collega vragen 🤷", "Jimmy bellen 📞",
      "42, uiteraard", "Hangt af van het weer ☀️", "Een goeie barbecue 🍖", "Zoals altijd, op gevoel 😎",
      "Geen idee, maar het klinkt goed", "Hetzelfde als gisteren", "Ogen dicht en hopen 🤞",
      "Staat nergens, dus nee", "Eerst een koffie ☕", "Google weet het beter dan ik",
      "We zien wel maandag", "Vraag het aan het onthaal 🙋",
    ];
    $lettreVersIndex = ['A' => 0, 'B' => 1, 'C' => 2];
    $tout = [];

    // 1) Entreprise : base Famiformation (quiz_questions) + réponse rigolote.
    $db = famiDb();
    $nbEntreprise = 0;
    if ($db) {
      try {
        // SELECT * : robuste si la table possède (ou non) des colonnes NL
        // (question_text_nl, option_a_nl…). Le jour où elles sont remplies dans
        // la base Famiformation, le NL des questions entreprise remonte tout seul.
        $rows = $db->query("SELECT * FROM quiz_questions ORDER BY theme ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
      } catch (Exception $e) { $rows = []; }
      $i = 0;
      foreach ($rows as $r) {
        $q = trim((string)($r['question_text'] ?? ''));
        $qNl = trim((string)($r['question_text_nl'] ?? ''));   // vide si la colonne n'existe pas
        $opts = [];
        $optsNl = [];
        foreach (['option_a', 'option_b', 'option_c'] as $col) {
          $o = trim((string)($r[$col] ?? ''));
          if ($o === '') { continue; }
          $opts[] = $o;
          $optsNl[] = trim((string)($r[$col . '_nl'] ?? ''));   // NL aligné (vide → fallback FR)
        }
        if ($q === '' || count($opts) < 2) { continue; }
        $lettre = strtoupper(trim((string)($r['reponse_correcte'] ?? 'A')));
        $correct = $lettreVersIndex[$lettre] ?? 0;
        if ($correct >= count($opts)) { $correct = 0; }
        $opts[] = $RIGOLOTES[$i % count($RIGOLOTES)];         // réponse fausse rigolote à la fin
        $optsNl[] = $RIGOLOTES_NL[$i % count($RIGOLOTES_NL)]; // sa version NL (alignée)
        $i++;
        $item = ['q' => $q, 'options' => $opts, 'correct' => $correct, 'theme' => 'entreprise'];
        // On ne pose les champs NL que s'il y a au moins une traduction réelle.
        if ($qNl !== '' || count(array_filter($optsNl, static function ($x) { return $x !== ''; })) > 0) {
          $item['q_nl'] = $qNl;
          $item['options_nl'] = $optsNl;
        }
        $tout[] = $item;
        $nbEntreprise++;
      }
    }

    // 2) Extras entreprise + culture + fun : fichiers livrés avec le code.
    foreach (['entreprise-extra.json' => 'entreprise', 'culture.json' => 'culture', 'fun.json' => 'fun'] as $fichier => $themeDefaut) {
      $chemin = __DIR__ . '/seed/' . $fichier;
      if (!is_file($chemin)) { continue; }
      $lus = json_decode((string)@file_get_contents($chemin), true);
      if (!is_array($lus)) { continue; }
      foreach ($lus as $item) {
        if (empty($item['theme'])) { $item['theme'] = $themeDefaut; }
        $tout[] = $item;
      }
    }

    // Nettoyage/validation avec les mêmes règles que l'enregistrement manuel.
    $propres = [];
    foreach ($tout as $item) {
      $c = nettoieQuestion($item);
      if ($c) { $propres[] = $c; }
    }
    if (!$propres) {
      http_response_code(500);
      echo json_encode(['error' => 'Aucune question à charger (base indisponible et aucun fichier seed ?).']);
      break;
    }
    writeJson($questionsFile, $propres);
    $parTheme = ['entreprise' => 0, 'culture' => 0, 'fun' => 0];
    foreach ($propres as $p) { $t = $p['theme'] ?? 'entreprise'; if (isset($parTheme[$t])) $parTheme[$t]++; }
    echo json_encode(['ok' => true, 'total' => count($propres), 'entreprise' => $parTheme['entreprise'],
      'culture' => $parTheme['culture'], 'fun' => $parTheme['fun'], 'base_ok' => (bool)$db], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 📋 Tableau de bord admin : classement détaillé + état des codes + questions.
  case 'admin_data': {
    exigeAdmin($input);
    $board = readJson($scoresFile);
    sortBoard($board);
    $pris = readJson($codesFile);
    $codes = [];
    foreach ($BONUS_CODES as $c) {
      if ($c === $CODE_TEST_OK || $c === $CODE_TEST_USED) { continue; }   // codes de test : hors liste
      $codes[] = [
        'code' => $c,
        'pris' => isset($pris[$c]),
        'par'  => $pris[$c]['par'] ?? null,
        'date' => $pris[$c]['date'] ?? null,
      ];
    }
    $j = readJson($jardinFile);
    echo json_encode([
      'board'     => $board,
      'codes'     => $codes,
      'questions' => lesQuestions($questionsFile, $QUESTIONS_DEFAUT),
      'jardin'    => ['cases' => (object)($j['cases'] ?? []), 'total' => $JARDIN_CASES],
      'plantes'   => $PLANTES,
      'config'    => ladConfig($configFile),
    ], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🧹 Vider une case du jardin (admin) : la plante disparaît, le planteur est
  // remboursé de son coût — c'est une correction, pas une punition.
  case 'jardin_vider': {
    exigeAdmin($input);
    global $PLANTES;
    $idx = intval($input['case'] ?? -1);

    $retiree = withLock($jardinFile, function (&$j, &$write) use ($idx) {
      if (!isset($j['cases'][$idx])) { return null; }
      $c = $j['cases'][$idx];
      unset($j['cases'][$idx]);
      $write = true;
      return $c;
    });

    if (!$retiree) { echo json_encode(['ok' => false, 'reason' => 'case_vide']); break; }
    $cout = $PLANTES[$retiree['plante']]['cout'] ?? 0;
    withLock($scoresFile, function (&$board, &$write) use ($retiree, $cout) {
      foreach ($board as &$p) {
        if (mb_strtolower($p['name'] ?? '') === mb_strtolower($retiree['par'] ?? '')) {
          $p['depensees'] = max(0, intval($p['depensees'] ?? 0) - $cout);
          $write = true;
          break;
        }
      }
      return null;
    });
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🌟 PLANTER librement (admin) : l'organisateur pose la plante qu'il veut, où
  // il veut, gratuitement. Attribuée à « Famiflora 🌟 » (hors classement joueurs).
  case 'jardin_planter': {
    exigeAdmin($input);
    global $PLANTES, $JARDIN_CASES;
    $idx    = intval($input['case'] ?? -1);
    $plante = trim($input['plante'] ?? '');
    if (!isset($PLANTES[$plante])) { echo json_encode(['ok' => false, 'reason' => 'plante_inconnue']); break; }
    if ($idx < 0 || $idx >= $JARDIN_CASES) { echo json_encode(['ok' => false, 'reason' => 'case_invalide']); break; }
    $res = withLock($jardinFile, function (&$j, &$write) use ($idx, $plante) {
      if (!isset($j['cases']) || !is_array($j['cases'])) { $j['cases'] = []; }
      $j['cases'][$idx] = ['plante' => $plante, 'par' => 'Famiflora 🌟', 'date' => date('c')];
      $write = true;
      return ['ok' => true];
    });
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🧹 Réinitialiser tout le jardin (admin) : grille vidée, tout le monde
  // récupère l'intégralité de ses graines (depensees remis à zéro).
  case 'jardin_reset': {
    exigeAdmin($input);
    writeJson($jardinFile, ['cases' => (object)[]]);
    withLock($scoresFile, function (&$board, &$write) {
      foreach ($board as &$p) { $p['depensees'] = 0; }
      $write = count($board) > 0;
      return null;
    });
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🗑️ Retirer un participant du classement (erreur de prénom, test, doublon…).
  case 'player_delete': {
    exigeAdmin($input);
    $nom = trim($input['name'] ?? '');
    $res = withLock($scoresFile, function (&$board, &$write) use ($nom) {
      $avant = count($board);
      $board = array_values(array_filter($board, function ($p) use ($nom) {
        return mb_strtolower($p['name'] ?? '') !== mb_strtolower($nom);
      }));
      $write = count($board) !== $avant;
      return ['ok' => $write, 'board' => $board];
    });
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🌱 RESET du jardin d'UN joueur (admin, pour re-tester) : on vide ses cases,
  // on lui rembourse ses graines dépensées (depensees=0), et on efface son
  // marqueur « prévenu » pour que le mail récompense puisse repartir à la
  // prochaine complétion. Le classement (score) n'est PAS touché.
  case 'reset_jardin_joueur': {
    exigeAdmin($input);
    $cle = mb_strtolower(trim((string) ($input['name'] ?? '')));
    if ($cle === '') { echo json_encode(['ok' => false, 'reason' => 'nom_manquant']); break; }
    $vide = withLock($jardinFile, function (&$j, &$write) use ($cle) {
      if (is_array($j) && isset($j[$cle])) { unset($j[$cle]); $write = true; return true; }
      return false;
    });
    withLock($scoresFile, function (&$board, &$write) use ($cle) {
      foreach ($board as &$p) {
        if (mb_strtolower($p['name'] ?? '') === $cle) { $p['depensees'] = 0; $write = true; break; }
      }
      return null;
    });
    withLock($rhFile, function (&$data, &$write) use ($cle) {
      if (is_array($data) && isset($data['prevenu'][$cle])) { unset($data['prevenu'][$cle]); $write = true; }
    });
    echo json_encode(['ok' => true, 'vide' => (bool) $vide], JSON_UNESCAPED_UNICODE);
    break;
  }

  // 🧹 Réinitialiser (tests) : api.php?action=reset&pin=XXXX
  case 'reset': {
    if (!hash_equals($ADMIN_PIN, (string)($_GET['pin'] ?? ''))) {
      http_response_code(403);
      echo json_encode(['error' => 'PIN incorrect']);
      break;
    }
    writeJson($scoresFile, []);
    writeJson($codesFile, (object)[]);
    writeJson($jardinFile, ['cases' => (object)[]]);
    echo json_encode(['ok' => true, 'message' => 'Scores, codes et jardin remis à zéro']);
    break;
  }

  default: {
    http_response_code(400);
    echo json_encode(['error' => 'Action inconnue']);
  }
}
