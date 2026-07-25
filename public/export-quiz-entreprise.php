<?php
// ============================================================
// export-quiz-entreprise.php — OUTIL PONCTUEL (admin).
//   Lit tous les QCM des modules Famiformation (colonne modules.quiz_json) et
//   les convertit au format du QUIZ DE LANCEMENT, prêts à coller dans
//   /quiz/admin → onglet Questions → « Importer (JSON) ».
//   On ne garde que les questions à UNE seule bonne réponse (le quiz de
//   lancement ne gère pas le choix multiple), thème « entreprise ».
//   ⚠️ À SUPPRIMER après usage (ou laisser : il est réservé à l'admin).
// ============================================================
require_once 'config.php';
if (function_exists('verifierConnexion')) { verifierConnexion($db); }
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: login.php'); exit(); }

$sortie = [];
$stats = ['modules' => 0, 'questions_total' => 0, 'gardees' => 0, 'multi_ignorees' => 0];

try {
    $rows = $db->query("SELECT quiz_json FROM modules WHERE quiz_json IS NOT NULL AND quiz_json <> ''")
               ->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $rows = []; }

foreach ($rows as $r) {
    $q = json_decode((string) $r['quiz_json'], true);
    $questions = (is_array($q) && !empty($q['questions']) && is_array($q['questions'])) ? $q['questions'] : [];
    if (!$questions) { continue; }
    $stats['modules']++;
    foreach ($questions as $qq) {
        $stats['questions_total']++;
        $texte = trim((string) ($qq['q'] ?? ''));
        $options = array_values(array_filter(array_map(function ($o) {
            return trim((string) $o);
        }, (array) ($qq['options'] ?? [])), function ($o) { return $o !== ''; }));
        $correct = array_map('intval', (array) ($qq['correct'] ?? []));
        $type = ($qq['type'] ?? 'single') === 'multiple' ? 'multiple' : 'single';

        // On ne garde que les questions à UNE bonne réponse, exploitables telles quelles.
        if ($type === 'multiple' || count($correct) !== 1) { $stats['multi_ignorees']++; continue; }
        if ($texte === '' || count($options) < 2) { continue; }
        $idx = $correct[0];
        if ($idx < 0 || $idx >= count($options)) { continue; }

        $sortie[] = ['q' => $texte, 'options' => $options, 'correct' => $idx, 'theme' => 'entreprise'];
        $stats['gardees']++;
    }
}

$json = json_encode($sortie, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Export des questions entreprise</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 900px; margin: 24px auto; padding: 0 16px; color: #14532d; }
  h1 { font-size: 1.5rem; } .stat { background: #eef6ef; border-radius: 10px; padding: 12px 16px; margin: 12px 0; line-height: 1.6; }
  textarea { width: 100%; height: 55vh; font-family: monospace; font-size: 12px; border: 2px solid #cfe3d3; border-radius: 10px; padding: 10px; }
  button { background: #1E7A46; color: #fff; border: 0; border-radius: 999px; padding: 10px 20px; font-weight: 700; cursor: pointer; margin: 10px 0; }
  a { color: #1E7A46; }
</style></head><body>
<h1>📤 Export des questions « entreprise »</h1>
<div class="stat">
  <b><?= (int) $stats['gardees'] ?></b> question(s) prêtes à importer (thème <b>entreprise</b>).<br>
  Lues : <?= (int) $stats['questions_total'] ?> question(s) dans <?= (int) $stats['modules'] ?> module(s).
  Ignorées car à choix multiple : <?= (int) $stats['multi_ignorees'] ?>.
</div>
<p>Copie tout le contenu ci-dessous, va dans <b>/quiz/admin → onglet Questions → « Importer (JSON) »</b>,
   colle-le et clique sur « Ajouter à la liste », puis « Enregistrer ». (À faire pour chaque magasin.)</p>
<button onclick="const t=document.getElementById('j');t.select();document.execCommand('copy');this.textContent='✅ Copié !';">📋 Tout copier</button>
<textarea id="j" readonly><?= htmlspecialchars($json) ?></textarea>
<p><a href="gestion_quiz.php">← Retour à la gestion des quiz</a></p>
</body></html>
