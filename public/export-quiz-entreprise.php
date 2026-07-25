<?php
// ============================================================
// export-quiz-entreprise.php — OUTIL PONCTUEL (admin).
//   Lit la banque de questions de Famiformation (table quiz_questions) et la
//   convertit au format du QUIZ DE LANCEMENT, prête à coller dans
//   /quiz/admin → onglet Questions → « Importer (JSON) ».
//   Thème « entreprise ». On AJOUTE à chaque question une 4e réponse fausse
//   « rigolote » (elle sera mélangée aux autres dans le quiz).
//   ⚠️ Doit tourner sur le SERVEUR QUI A LA BASE (là où marche admin_questions.php).
// ============================================================
require_once 'config.php';
if (function_exists('verifierConnexion')) { verifierConnexion($db); }
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: login.php'); exit(); }

// 😄 Réponses fausses rigolotes : une piochée par question (jamais la bonne).
$RIGOLOTES = [
    "Rien du tout, on improvise 😅", "Demander à un collègue 🤷", "Appeler Jimmy 📞",
    "42, évidemment", "Ça dépend de la météo ☀️", "Un bon barbecue 🍖", "Comme d'habitude, au feeling 😎",
    "Aucune idée, mais ça sonne bien", "La même chose qu'hier", "Fermer les yeux et espérer 🤞",
    "C'est écrit nulle part, donc non", "Un café d'abord ☕", "Google le sait mieux que moi",
    "On verra ça lundi", "Poser la question à l'accueil 🙋",
];

$sortie = [];
$stats = ['lues' => 0, 'gardees' => 0];
$lettreVersIndex = ['A' => 0, 'B' => 1, 'C' => 2];

try {
    $rows = $db->query("SELECT theme, question_text, option_a, option_b, option_c, reponse_correcte
                        FROM quiz_questions ORDER BY theme ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $rows = []; }

$i = 0;
foreach ($rows as $r) {
    $stats['lues']++;
    $q = trim((string) ($r['question_text'] ?? ''));
    $options = [];
    foreach (['option_a', 'option_b', 'option_c'] as $col) {
        $o = trim((string) ($r[$col] ?? ''));
        if ($o !== '') { $options[] = $o; }
    }
    if ($q === '' || count($options) < 2) { continue; }

    $lettre = strtoupper(trim((string) ($r['reponse_correcte'] ?? 'A')));
    $correct = $lettreVersIndex[$lettre] ?? 0;
    if ($correct >= count($options)) { $correct = 0; }

    // On ajoute une réponse fausse rigolote À LA FIN (n'affecte pas l'index correct).
    $options[] = $RIGOLOTES[$i % count($RIGOLOTES)];
    $i++;

    $sortie[] = ['q' => $q, 'options' => $options, 'correct' => $correct, 'theme' => 'entreprise'];
    $stats['gardees']++;
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
  <b><?= (int) $stats['gardees'] ?></b> question(s) prêtes à importer (thème <b>entreprise</b>, chacune avec une réponse fausse rigolote en plus).<br>
  Lues dans la base <code>quiz_questions</code> : <?= (int) $stats['lues'] ?> question(s).
</div>
<p>Copie tout le contenu ci-dessous, va dans <b>/quiz/admin → onglet Questions → « Importer (JSON) »</b>,
   colle-le et clique sur « Ajouter à la liste », puis « Enregistrer ». (À faire pour chaque magasin.)</p>
<button onclick="const t=document.getElementById('j');t.select();document.execCommand('copy');this.textContent='✅ Copié !';">📋 Tout copier</button>
<textarea id="j" readonly><?= htmlspecialchars($json) ?></textarea>
<p><a href="admin_questions.php">← Retour à la gestion des questions</a></p>
</body></html>
