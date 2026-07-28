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

// ============================================================
// 📥 EXTRACTION BRUTE DE LA TABLE quiz_questions (avant tout le reste) :
//   ?format=csv → fichier Excel (toutes les colonnes)
//   ?format=sql → sauvegarde SQL (structure + INSERT, réimportable)
// Rien à installer : le serveur lit la base et te renvoie un fichier à télécharger.
// ============================================================
$format = $_GET['format'] ?? '';
if ($format === 'csv' || $format === 'sql') {
    try {
        $lignes = $db->query("SELECT * FROM quiz_questions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $lignes = []; }
    $stamp = date('Ymd_His');

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="quiz_questions_' . $stamp . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF");   // BOM UTF-8 pour qu'Excel affiche bien les accents
        if ($lignes) {
            fputcsv($out, array_keys($lignes[0]), ';');            // en-têtes (Excel FR = point-virgule)
            foreach ($lignes as $l) { fputcsv($out, $l, ';'); }
        }
        fclose($out);
        exit;
    }

    // format === 'sql'
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="quiz_questions_' . $stamp . '.sql"');
    echo "-- Sauvegarde de la table quiz_questions — " . date('c') . "\n";
    echo "-- " . count($lignes) . " ligne(s)\n\n";
    try {
        $create = $db->query("SHOW CREATE TABLE quiz_questions")->fetch(PDO::FETCH_ASSOC);
        if ($create) {
            $col = isset($create['Create Table']) ? 'Create Table' : (isset($create['create table']) ? 'create table' : null);
            if ($col) { echo "DROP TABLE IF EXISTS `quiz_questions`;\n" . $create[$col] . ";\n\n"; }
        }
    } catch (Exception $e) { /* pas de structure : on garde au moins les données */ }
    foreach ($lignes as $l) {
        $cols = '`' . implode('`, `', array_keys($l)) . '`';
        $vals = implode(', ', array_map(function ($v) use ($db) {
            return $v === null ? 'NULL' : $db->quote((string) $v);
        }, array_values($l)));
        echo "INSERT INTO `quiz_questions` ($cols) VALUES ($vals);\n";
    }
    exit;
}

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
  .dl { display: inline-block; background: #1E7A46; color: #fff; text-decoration: none; border-radius: 999px; padding: 10px 20px; font-weight: 700; margin: 4px 8px 4px 0; }
  .dl.sec { background: #eef6ef; color: #14532d; border: 2px solid #1E7A46; }
  .bloc { background: #fff; border: 1px solid #d7e7db; border-radius: 12px; padding: 14px 18px; margin: 16px 0; }
</style></head><body>
<h1>📤 Export des questions « entreprise »</h1>

<div class="bloc">
  <h2 style="margin:0 0 6px; font-size:1.1rem;">📥 Extraire toute la table <code>quiz_questions</code></h2>
  <p style="margin:0 0 10px;">Télécharge ta table telle quelle, sans passer par la base de données :</p>
  <a class="dl" href="?format=csv">⬇️ Télécharger en CSV (Excel)</a>
  <a class="dl sec" href="?format=sql">⬇️ Télécharger en SQL (sauvegarde)</a>
  <p style="margin:8px 0 0; font-size:.9rem; color:#5a6b60;">Le CSV s'ouvre dans Excel/LibreOffice. Le SQL est une sauvegarde complète (structure + données), réimportable dans n'importe quelle base MySQL.</p>
</div>

<h2 style="font-size:1.1rem;">🎯 …ou charger directement dans le quiz</h2>
<div class="stat">
  <b><?= (int) $stats['gardees'] ?></b> question(s) prêtes à importer (thème <b>entreprise</b>, chacune avec une réponse fausse rigolote en plus).<br>
  Lues dans la base <code>quiz_questions</code> : <?= (int) $stats['lues'] ?> question(s).<br>
  <b>💡 Le plus simple :</b> dans <b>/quiz/admin → Questions</b>, clique sur <b>« 🌱 Charger toutes les questions »</b> — pas besoin de copier-coller.
</div>
<p>Copie tout le contenu ci-dessous, va dans <b>/quiz/admin → onglet Questions → « Importer (JSON) »</b>,
   colle-le et clique sur « Ajouter à la liste », puis « Enregistrer ». (À faire pour chaque magasin.)</p>
<button onclick="const t=document.getElementById('j');t.select();document.execCommand('copy');this.textContent='✅ Copié !';">📋 Tout copier</button>
<textarea id="j" readonly><?= htmlspecialchars($json) ?></textarea>
<p><a href="admin_questions.php">← Retour à la gestion des questions</a></p>
</body></html>
