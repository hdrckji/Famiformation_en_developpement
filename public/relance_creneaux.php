<?php
// ============================================================
// relance_creneaux.php — PRÉVENIR LES ÉTUDIANTS QUE DES CRÉNEAUX SONT OUVERTS.
//
// À QUOI ÇA SERT
// Quand on ajoute des créneaux un par un depuis admin_formations.php, un mail
// part À CHAQUE ajout. Ouvrir 44 dates d'un coup enverrait donc 44 mails à la
// même personne. Et si les créneaux sont importés directement en base (fichier
// SQL), personne n'est prévenu du tout.
//
// Cette page envoie UN SEUL mail par personne, qui liste TOUTES les dates
// ouvertes.
//
// QUI EST CONCERNÉ
// Les étudiants « intéressés » : ceux qui ont une inscription à la formation
// SANS créneau choisi (formations_inscriptions.creneau_id IS NULL). C'est la
// même définition que celle utilisée par admin_formations.php — on ne réinvente
// pas une règle qui existe.
//
// Ceux qui ont DÉJÀ choisi un créneau ne sont pas recontactés : ils ont leur
// date, un mail « de nouvelles dates sont disponibles » ne ferait que semer le
// doute. Ils sont comptés à l'écran pour qu'on sache qu'ils existent.
//
// ⚠️ RIEN NE PART SANS APERÇU. La page montre d'abord les destinataires et le
// mail exact, bouton d'envoi séparé.
// ============================================================
require_once 'config.php';
verifierConnexion($db);
require_once 'includes/csrf.php';
require_once 'includes/mail_html.php';

$role = function_exists('getCurrentRole') ? getCurrentRole() : ($_SESSION['role'] ?? '');
if ($role !== 'admin') {
    header('Location: index.php');
    exit();
}

$e = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$flash = '';

// ── Les formations qui ont au moins un créneau à venir ──────────────────
$formations = [];
try {
    $formations = $db->query(
        "SELECT fs.id, fs.titre,
                COUNT(fc.id) AS creneaux_a_venir,
                MIN(fc.date_heure) AS premier,
                MAX(fc.date_heure) AS dernier
           FROM formations_sessions fs
           JOIN formations_creneaux fc ON fc.formation_id = fs.id
          WHERE fc.date_heure >= NOW()
       GROUP BY fs.id, fs.titre
       ORDER BY fs.titre"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    $flash = "<div class='alert err'>Impossible de lire les formations : " . $e($ex->getMessage()) . "</div>";
}

$formationId = (int) ($_REQUEST['formation_id'] ?? 0);
// 👥 Le public visé. Par défaut le plus étroit : on n'élargit qu'en le
// choisissant sciemment, jamais par inadvertance.
$public = (($_REQUEST['public'] ?? '') === 'sans_date') ? 'sans_date' : 'inscrits';
$formation = null;
foreach ($formations as $f) { if ((int) $f['id'] === $formationId) { $formation = $f; } }

$creneaux = [];
$interesses = [];
$dejaInscrits = 0;
$sansMail = 0;

if ($formation) {
    // Les créneaux à venir, dans l'ordre.
    $st = $db->prepare("SELECT date_heure, duree, places_max FROM formations_creneaux
                        WHERE formation_id = ? AND date_heure >= NOW() ORDER BY date_heure");
    $st->execute([$formationId]);
    $creneaux = $st->fetchAll(PDO::FETCH_ASSOC);

    // 👥 LE PUBLIC. Le message est générique — il annonce des créneaux, sans
    // rien supposer de la personne — donc il peut viser plus large que les
    // seuls inscrits. Trois périmètres, du plus étroit au plus large.
    //
    // Dans TOUS les cas, ceux qui ont DÉJÀ réservé une date sont exclus : leur
    // annoncer des créneaux à réserver serait au mieux inutile, au pire
    // inquiétant (« ma réservation a sauté ? »).
    if ($public === 'sans_date') {
        // TOUS les étudiants qui n'ont pas de date pour cette formation, qu'ils
        // se soient inscrits ou non. C'est le public d'un message générique :
        // on annonce des créneaux, celui qui est concerné réserve.
        $sql = "SELECT u.id, u.prenom, u.nom, u.email
                  FROM utilisateurs u
                 WHERE u.role = 'etudiant'
                   AND u.id NOT IN (SELECT utilisateur_id FROM formations_inscriptions
                                     WHERE formation_id = ? AND creneau_id IS NOT NULL)
              ORDER BY u.prenom, u.nom";
    } else {
        // 'inscrits' — ceux qui se sont inscrits à la formation SANS choisir de
        // date. Le public le plus étroit, et le choix par défaut.
        $sql = "SELECT DISTINCT u.id, u.prenom, u.nom, u.email
                  FROM utilisateurs u
                  JOIN formations_inscriptions i ON i.utilisateur_id = u.id
                 WHERE i.formation_id = ? AND i.creneau_id IS NULL
                   AND u.role = 'etudiant'
              ORDER BY u.prenom, u.nom";
    }
    $st = $db->prepare($sql);
    $st->execute([$formationId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) {
        if (filter_var(trim((string) $u['email']), FILTER_VALIDATE_EMAIL)) { $interesses[] = $u; }
        else { $sansMail++; }
    }

    // Ceux qui ont déjà une date : on ne leur écrit pas, mais on le DIT.
    $st = $db->prepare("SELECT COUNT(DISTINCT i.utilisateur_id) FROM formations_inscriptions i
                        WHERE i.formation_id = ? AND i.creneau_id IS NOT NULL");
    $st->execute([$formationId]);
    $dejaInscrits = (int) $st->fetchColumn();
}

// ── Le mail ─────────────────────────────────────────────────────────────
$JOURS = ['Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi', 'Thursday' => 'jeudi',
          'Friday' => 'vendredi', 'Saturday' => 'samedi', 'Sunday' => 'dimanche'];
$MOIS  = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
          'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/** Une date lisible : « lundi 10 août à 14h00 ». */
function creneauLisible($dateHeure, array $JOURS, array $MOIS)
{
    $d = date_create($dateHeure);
    if (!$d) { return (string) $dateHeure; }
    return $JOURS[$d->format('l')] . ' ' . (int) $d->format('j') . ' '
         . $MOIS[(int) $d->format('n')] . ' à ' . $d->format('H\hi');
}

function corpsMailCreneaux($prenom, $titreFormation, array $creneaux, array $JOURS, array $MOIS)
{
    $e = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $bonjour = trim((string) $prenom) !== '' ? $e($prenom) : 'à toi';

    // 🗓️ La liste des dates. On n'en met pas 44 dans un mail : au-delà de 12 on
    // annonce la période et on renvoie vers le site, sinon le message devient
    // un mur illisible que personne ne lit jusqu'au bout.
    $liste = '';
    $max = 12;
    foreach (array_slice($creneaux, 0, $max) as $c) {
        $duree = trim((string) ($c['duree'] ?? ''));
        $liste .= '<li style="margin-bottom:6px;">' . $e(creneauLisible($c['date_heure'], $JOURS, $MOIS))
                . ($duree !== '' ? ' <span style="color:#6b7d70;">(' . $e($duree) . ')</span>' : '')
                . '</li>';
    }
    $reste = count($creneaux) - $max;
    $suite = $reste > 0
        ? '<p style="margin:0 0 18px;font-size:15px;color:#4a5a50;">…et <b>' . (int) $reste
          . ' autres dates</b>. La liste complète est sur ton espace.</p>'
        : '';

    $lien = function_exists('famiBuildAppUrl') ? famiBuildAppUrl('formation.php') : 'formation.php';

    // ✉️ MESSAGE VOLONTAIREMENT COURT. On annonce, on donne les dates, on donne
    // le bouton. Rien de plus : un mail long est un mail qu'on ne lit pas.
    //
    // ⚠️ On ne dit PAS « à laquelle vous avez manifesté votre intérêt ». C'est
    // la façon dont on choisit les destinataires, pas une information utile à
    // la personne — et ça oblige à se souvenir de ce qu'on a coché il y a des
    // semaines pour comprendre pourquoi on reçoit ce mail.
    return '<div style="font-family:Arial,sans-serif;color:#244230;max-width:560px;margin:0 auto;padding:24px;">'
        . '<p style="font-size:16px;">Bonjour ' . $bonjour . ',</p>'
        . '<p style="font-size:16px;line-height:1.6;">De <b>nouveaux créneaux</b> ont été ajoutés pour la formation '
        . '<b>' . $e($titreFormation) . '</b>.</p>'
        . '<ul style="font-size:16px;line-height:1.5;padding-left:20px;margin:0 0 18px;">' . $liste . '</ul>'
        . $suite
        . '<p style="font-size:16px;line-height:1.6;">Rends-toi sur <b>FamiFormation</b> pour réserver ton créneau.</p>'
        . '<p style="margin:24px 0;"><a href="' . $e($lien) . '" '
        . 'style="background:#2d5a37;color:#ffffff;text-decoration:none;font-weight:bold;'
        . 'padding:13px 26px;border-radius:6px;display:inline-block;">Réserver mon créneau</a></p>'
        . '<p style="font-size:15px;line-height:1.6;">Si le bouton ne fonctionne pas, copie ce lien dans ton navigateur :<br>'
        . '<span style="color:#2d5a37;">' . $e($lien) . '</span></p>'
        . '<p style="font-size:15px;color:#617268;">Une question ? Écris à '
        . '<a href="mailto:admin@famiformation.com">admin@famiformation.com</a>.<br>'
        . 'À bientôt,<br>L\'équipe Famiflora · FamiFormation</p></div>';
}

// ── ENVOI ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer']) && $formation) {
    requireValidCSRF();
    $choisis = array_map('intval', (array) ($_POST['destinataires'] ?? []));
    $envoye = 0; $echecs = 0; $detail = [];
    $sujet = '🎓 Nouveaux créneaux — ' . $formation['titre'];

    foreach ($interesses as $u) {
        if (!in_array((int) $u['id'], $choisis, true)) { continue; }
        $corps = corpsMailCreneaux($u['prenom'], $formation['titre'], $creneaux, $JOURS, $MOIS);
        $ok = function_exists('sendMail') ? sendMail($u['email'], $sujet, $corps, true) : false;
        if ($ok) { $envoye++; } else { $echecs++; }
        $detail[] = ['nom' => trim($u['prenom'] . ' ' . $u['nom']), 'mail' => $u['email'], 'ok' => $ok];
    }
    if (!$detail) {
        $flash = "<div class='alert err'>Aucun destinataire coché : rien n'a été envoyé.</div>";
    } else {
        $lignes = '';
        foreach ($detail as $d) {
            $lignes .= '<li>' . ($d['ok'] ? '✅' : '❌') . ' ' . $e($d['nom']) . ' — ' . $e($d['mail']) . '</li>';
        }
        $flash = "<div class='alert ok'><b>{$envoye} mail(s) envoyé(s)</b>"
               . ($echecs ? " · <b>{$echecs} échec(s)</b>" : '')
               . "<ul style='margin:8px 0 0;padding-left:20px;'>{$lignes}</ul></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Prévenir des nouveaux créneaux — FamiFormation</title>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family:'Open Sans',sans-serif; background:#f4f7f6; margin:0; padding:20px; color:#2d3a32; }
  .wrap { max-width:940px; margin:0 auto; }
  h1 { color:#2d5a37; font-size:1.5rem; margin:0 0 4px; }
  .sub { color:#6b7d70; margin:0 0 18px; }
  .card { background:#fff; border-radius:14px; padding:20px 22px; margin-bottom:16px;
          box-shadow:0 4px 16px rgba(0,0,0,.07); }
  .card h2 { font-size:1.08rem; color:#2d5a37; margin:0 0 12px; }
  .alert { padding:12px 16px; border-radius:10px; margin-bottom:16px; font-weight:600; }
  .alert.ok { background:#eaf7ec; color:#1E7A46; } .alert.err { background:#fdecec; color:#a12; }
  select, .btn { font:inherit; }
  select { padding:10px 12px; border:2px solid #cfe0d4; border-radius:10px; min-width:280px; }
  .btn { background:#2d5a37; color:#fff; border:none; padding:12px 22px; border-radius:999px;
         font-weight:700; cursor:pointer; }
  .btn.ghost { background:#fff; color:#2d5a37; border:2px solid #2d5a37; }
  .btn:disabled { opacity:.45; cursor:not-allowed; }
  .chips { display:flex; gap:10px; flex-wrap:wrap; margin:0 0 14px; }
  .chip { background:#eef4ef; border-radius:999px; padding:6px 14px; font-size:.88rem; font-weight:600; }
  .chip.warn { background:#fdf0d5; color:#8a5b00; }
  .pers { display:flex; align-items:center; gap:10px; padding:8px 4px; border-top:1px solid #eef2ee; }
  .pers:first-of-type { border-top:none; }
  .pers input { width:18px; height:18px; }
  .pers .mail { color:#6b7d70; font-size:.88rem; }
  .dates { columns:2; column-gap:26px; font-size:.92rem; line-height:1.7; }
  @media (max-width:640px){ .dates{ columns:1; } }
  .apercu { border:2px dashed #cfe0d4; border-radius:12px; padding:6px; background:#fafcfa; }
  .vide { color:#8a988f; font-style:italic; }
</style>
</head>
<body>
<div class="wrap">
<?php require_once __DIR__ . '/includes/retour.php'; echo barreRetour('admin_formations.php', 'Retour aux formations'); ?>

  <h1>🎓 Prévenir des nouveaux créneaux</h1>
  <p class="sub">Un <b>seul</b> mail par personne, qui liste toutes les dates ouvertes.</p>
  <?= $flash ?>

  <div class="card">
    <h2>1. Quelle formation ?</h2>
    <form method="GET">
      <select name="formation_id" onchange="this.form.submit()">
        <option value="0">— Choisir une formation —</option>
        <?php foreach ($formations as $f): ?>
          <option value="<?= (int) $f['id'] ?>" <?= $formationId === (int) $f['id'] ? 'selected' : '' ?>>
            <?= $e($f['titre']) ?> (<?= (int) $f['creneaux_a_venir'] ?> créneaux à venir)
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn" type="submit">Afficher</button></noscript>
    </form>
    <?php if (!$formations): ?>
      <p class="vide">Aucune formation n'a de créneau à venir. Ajoute d'abord des dates.</p>
    <?php endif; ?>
  </div>

<?php if ($formation): ?>
  <div class="card">
    <h2>2. Les dates qui seront annoncées <span style="color:#6b7d70;font-weight:400;">(<?= count($creneaux) ?>)</span></h2>
    <div class="dates">
      <?php foreach ($creneaux as $c): ?>
        <div>• <?= $e(creneauLisible($c['date_heure'], $JOURS, $MOIS)) ?>
          <?php if (trim((string) $c['duree']) !== ''): ?>
            <span style="color:#6b7d70;">(<?= $e($c['duree']) ?>)</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (count($creneaux) > 12): ?>
      <p class="sub" style="margin-top:12px;">Le mail détaillera les <b>12 premières</b> dates puis renverra vers le site
      pour les <?= count($creneaux) - 12 ?> autres — au-delà, le message devient un mur que personne ne lit.</p>
    <?php endif; ?>
  </div>

  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="formation_id" value="<?= (int) $formationId ?>">
    <?php // Le public doit suivre l'envoi : sans ça, valider rebasculerait sur
          // le public par défaut et la liste cochée ne correspondrait plus à
          // celle qu'on vient de relire à l'écran. ?>
    <input type="hidden" name="public" value="<?= $e($public) ?>">

    <div class="card">
      <h2>3. Qui sera prévenu ?</h2>

      <form method="GET" style="margin-bottom:14px;">
        <input type="hidden" name="formation_id" value="<?= (int) $formationId ?>">
        <label class="pers" style="border:none;padding:6px 4px;">
          <input type="radio" name="public" value="inscrits" <?= $public === 'inscrits' ? 'checked' : '' ?>
                 onchange="this.form.submit()">
          <span><b>Ceux qui se sont inscrits sans choisir de date</b>
            <span class="mail">— le public le plus étroit</span></span>
        </label>
        <label class="pers" style="border:none;padding:6px 4px;">
          <input type="radio" name="public" value="sans_date" <?= $public === 'sans_date' ? 'checked' : '' ?>
                 onchange="this.form.submit()">
          <span><b>Tous les étudiants sans date pour cette formation</b>
            <span class="mail">— pour un message générique, envoyé largement</span></span>
        </label>
        <noscript><button class="btn ghost" type="submit">Appliquer</button></noscript>
      </form>

      <div class="chips">
        <span class="chip"><b><?= count($interesses) ?></b> destinataire(s) joignable(s)</span>
        <?php if ($sansMail): ?><span class="chip warn"><b><?= $sansMail ?></b> sans adresse e-mail</span><?php endif; ?>
        <?php if ($dejaInscrits): ?><span class="chip"><b><?= $dejaInscrits ?></b> ont déjà leur date — jamais contactés</span><?php endif; ?>
      </div>
      <p class="sub">Quel que soit le public choisi, <b>ceux qui ont déjà réservé une date sont toujours exclus</b> :
      leur annoncer des créneaux à réserver serait au mieux inutile, au pire inquiétant.</p>

      <?php if (!$interesses): ?>
        <p class="vide">Personne à prévenir pour cette formation.</p>
      <?php else: ?>
        <div style="margin-bottom:10px;">
          <button type="button" class="btn ghost" onclick="document.querySelectorAll('.dest').forEach(c=>c.checked=true);maj()">Tout cocher</button>
          <button type="button" class="btn ghost" onclick="document.querySelectorAll('.dest').forEach(c=>c.checked=false);maj()">Tout décocher</button>
          <span id="nb" style="margin-left:10px;color:#6b7d70;font-weight:600;"></span>
        </div>
        <?php foreach ($interesses as $u): ?>
          <label class="pers">
            <input type="checkbox" class="dest" name="destinataires[]" value="<?= (int) $u['id'] ?>" checked onchange="maj()">
            <span><b><?= $e(trim($u['prenom'] . ' ' . $u['nom'])) ?></b>
              <span class="mail">— <?= $e($u['email']) ?></span></span>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>4. Le mail, exactement tel qu'il partira</h2>
      <p class="sub"><b>Objet :</b> 🎓 Nouveaux créneaux — <?= $e($formation['titre']) ?></p>
      <div class="apercu">
        <?= corpsMailCreneaux('Prénom', $formation['titre'], $creneaux, $JOURS, $MOIS) ?>
      </div>
    </div>

    <?php if ($interesses): ?>
      <div class="card">
        <button class="btn" name="envoyer" value="1" id="envoi"
          onclick="return confirm('Envoyer ce mail aux personnes cochées ?');">
          ✉️ Envoyer
        </button>
        <span class="sub" style="margin-left:10px;">Rien n'est envoyé avant ce clic.</span>
      </div>
    <?php endif; ?>
  </form>
<?php endif; ?>
</div>
<script>
  function maj() {
    var n = document.querySelectorAll('.dest:checked').length;
    var s = document.getElementById('nb');
    if (s) { s.textContent = n + ' personne(s) sélectionnée(s)'; }
    var b = document.getElementById('envoi');
    if (b) { b.disabled = (n === 0); }
  }
  maj();
</script>
</body>
</html>
