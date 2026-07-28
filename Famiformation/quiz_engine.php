<?php
// 1. Diagnostic d'erreurs
require_once 'config.php';
verifierConnexion($db);

$theme = $_GET['theme'] ?? 'quiz_caisse';
$user_id = $_SESSION['user_id'];
$message = "";

// 2. RÉCUPÉRATION DES QUESTIONS
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Premier affichage : Tirage au sort de 10 questions
    $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE theme = ? ORDER BY RAND() LIMIT 10");
    $stmt->execute([$theme]);
    $questions = $stmt->fetchAll();

    // 🌱 Quiz onboarding vide ? On l'alimente automatiquement avec les questions
    // (mêmes que la version beta) puis on re-tire. Évite le « Aucune question ».
    if (!$questions) {
        require_once 'includes/onboarding_quiz_seed.php';
        if (seedOnboardingTheme($db, $theme) > 0) {
            $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE theme = ? ORDER BY RAND() LIMIT 10");
            $stmt->execute([$theme]);
            $questions = $stmt->fetchAll();
        }
    }

    if (!$questions) {
        die("Aucune question trouvée pour ce thème.");
    }
} else {
    requireValidCSRF();
    // Validation : On récupère les IDs exacts envoyés par le formulaire
    if (!isset($_POST['quiz_question_ids']) || empty($_POST['quiz_question_ids'])) {
        die("Erreur : Les données du quiz n'ont pas été transmises correctement.");
    }
    
    $ids_envoyes = explode(',', $_POST['quiz_question_ids']);
    $in  = str_repeat('?,', count($ids_envoyes) - 1) . '?';
    $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE id IN ($in)");
    $stmt->execute($ids_envoyes);
    $questions = $stmt->fetchAll();
}

// 3. TRAITEMENT DU SCORE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($questions)) {
    $score = 0;
    $incorrectAnswers = [];
    foreach ($questions as $q) {
        $reponse_utilisateur = $_POST['q_' . $q['id']] ?? '';
        $bonneReponse = $q['reponse_correcte'] ?? 'A';
        if ($reponse_utilisateur === $bonneReponse) {
            $score++;
        } else {
            $optionsMap = [
                'A' => $q['option_a'] ?? '',
                'B' => $q['option_b'] ?? '',
                'C' => $q['option_c'] ?? '',
            ];

            $incorrectAnswers[] = [
                'question' => $q['question_text'] ?? '',
                'user_answer' => $reponse_utilisateur !== '' ? ($optionsMap[$reponse_utilisateur] ?? $reponse_utilisateur) : 'Aucune réponse',
                'correct_answer' => $optionsMap[$bonneReponse] ?? $bonneReponse,
            ];
        }
    }

    // Sauvegarde des points (Uniquement au premier essai réussi sur ce thème)
    $check = $db->prepare("SELECT id FROM statistiques WHERE utilisateur_id = ? AND nom_page = ? AND score IS NOT NULL");
    $check->execute([$user_id, $theme]);
    
    if (!$check->fetch()) {
        $points_gagnes = $score * 5;
        $upd = $db->prepare("UPDATE utilisateurs SET points = points + ? WHERE id = ?");
        $upd->execute([$points_gagnes, $user_id]);
    }

    // Enregistrement de la tentative (On retire date_tentative si la colonne n'est pas encore créée)
    try {
        $ins = $db->prepare("INSERT INTO statistiques (utilisateur_id, nom_page, score, date_tentative) VALUES (?, ?, ?, NOW())");
        $ins->execute([$user_id, $theme, $score]);
    } catch (PDOException $e) {
        // Si la colonne date_tentative n'existe pas, on insère sans elle
        $ins = $db->prepare("INSERT INTO statistiques (utilisateur_id, nom_page, score) VALUES (?, ?, ?)");
        $ins->execute([$user_id, $theme, $score]);
    }

    $requiredScore = 8;
    $isCaisseQuiz = in_array($theme, ['quiz_caisse', 'quiz_pdf'], true);
    $caisseProgressMessage = '';
    if ($isCaisseQuiz) {
        require_once 'includes/quizz_status.php';
        $caisseStatus = getCaisseQuizValidationStatus($user_id);
        if ($caisseStatus['video_valid'] && $caisseStatus['pdf_valid']) {
            $caisseProgressMessage = "<p style='color:#1f7a3c;font-weight:700;'>Les deux quiz caisse sont validés. Le planning des formations est maintenant accessible.</p>";
        } else {
            $caisseProgressMessage = "<p style='color:#7a5a11;font-weight:700;'>Pour débloquer le planning, il faut obtenir au moins {$requiredScore}/10 au quiz vidéo et au quiz PDF.</p>";
        }
    }

    $message = "<div class='quiz-result'>
                    <h2>Votre Score : $score / 10</h2>
                    <p>Vos points ont été mis à jour dans le classement général.</p>"
                    . $caisseProgressMessage;

    if (!empty($incorrectAnswers)) {
        $message .= "<div class='quiz-review'>
                        <h3>Questions à revoir</h3>
                        <p class='quiz-review-intro'>Voici les réponses qui n'étaient pas correctes pour t'aider à retravailler le contenu.</p>";

        foreach ($incorrectAnswers as $index => $item) {
            $message .= "<div class='quiz-review-item'>
                            <div class='quiz-review-question'>" . ($index + 1) . ". " . e($item['question']) . "</div>
                            <div class='quiz-review-answer wrong'><strong>Ta réponse :</strong> " . e($item['user_answer']) . "</div>
                            <div class='quiz-review-answer correct'><strong>Bonne réponse :</strong> " . e($item['correct_answer']) . "</div>
                        </div>";
        }

        $message .= "</div>";
    }

    $message .= "<a href='index.php' class='btn-home'>Retour à l'accueil</a>
                </div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - FamiFormation</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Open Sans', sans-serif; 
            background: url('background.jpg') no-repeat center center fixed; 
            background-size: cover; 
            margin: 0; 
            padding: 20px; 
            display: flex; 
            justify-content: center; 
            align-items: flex-start; 
            min-height: 100vh; 
            box-sizing: border-box;
        }
        .quiz-card { 
            background: rgba(255, 255, 255, 0.96); 
            max-width: 800px; 
            width: 100%; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
            margin: 20px auto;
        }
        h1 { color: #2d5a37; text-align: center; margin-bottom: 30px; }
        .question-block { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #ddd; }
        .question-text { font-size: 1.15rem; font-weight: 700; color: #333; display: block; margin-bottom: 15px; }
        .option { 
            display: block; 
            margin: 10px 0; 
            padding: 12px 15px; 
            border: 2px solid #eee; 
            border-radius: 12px; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            background: #fff;
        }
        .option:hover { border-color: #2d5a37; background: #f0f4f1; }
        .option input { margin-right: 10px; }
        .btn-submit { 
            background: #d9a406; 
            color: white; 
            border: none; 
            padding: 18px; 
            border-radius: 50px; 
            width: 100%; 
            font-weight: bold; 
            cursor: pointer; 
            font-size: 1.2rem; 
            transition: 0.3s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .btn-submit:hover { background: #c29205; transform: translateY(-2px); }
        .quiz-result { text-align: center; padding: 20px; }
        .quiz-result h2 { font-size: 2.5rem; color: #2d5a37; margin-bottom: 10px; }
        .quiz-review {
            margin-top: 28px;
            text-align: left;
            background: #f8fbf8;
            border: 1px solid #dce9de;
            border-radius: 18px;
            padding: 24px;
        }
        .quiz-review h3 {
            margin: 0 0 8px;
            color: #2d5a37;
            font-size: 1.35rem;
        }
        .quiz-review-intro {
            margin: 0 0 18px;
            color: #5d6b60;
            line-height: 1.5;
        }
        .quiz-review-item {
            padding: 16px 0;
            border-top: 1px solid #e2ebe3;
        }
        .quiz-review-item:first-of-type {
            border-top: none;
            padding-top: 0;
        }
        .quiz-review-question {
            font-weight: 700;
            color: #243127;
            margin-bottom: 10px;
            line-height: 1.5;
        }
        .quiz-review-answer {
            margin: 6px 0;
            padding: 10px 12px;
            border-radius: 12px;
            line-height: 1.5;
        }
        .quiz-review-answer.wrong {
            background: #fff3f0;
            color: #8b3a2f;
        }
        .quiz-review-answer.correct {
            background: #edf7ef;
            color: #24613a;
        }
        .btn-home { 
            display: inline-block; 
            margin-top: 20px; 
            padding: 15px 40px; 
            background: #2d5a37; 
            color: white; 
            text-decoration: none; 
            border-radius: 50px; 
            font-weight: bold; 
        }
    </style>
</head>
<body>
    <div class="quiz-card">
        <h1>Évaluation : <?php echo htmlspecialchars(str_replace('quiz_', '', $theme)); ?></h1>
        
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php else: ?>
            <form method="POST" action="quiz_engine.php?theme=<?php echo urlencode($theme); ?>">
                <?php echo csrfField(); ?>
                <input type="hidden" name="quiz_question_ids" value="<?php echo implode(',', array_column($questions, 'id')); ?>">
                
                <?php foreach ($questions as $index => $q): 
                    // Préparation et mélange des réponses
                    $opts = [['v' => 'A', 't' => $q['option_a']], ['v' => 'B', 't' => $q['option_b']]];
                    if(!empty($q['option_c'])) $opts[] = ['v' => 'C', 't' => $q['option_c']];
                    shuffle($opts);
                ?>
                <div class="question-block">
                    <span class="question-text"><?php echo ($index + 1) . ". " . htmlspecialchars($q['question_text']); ?></span>
                    <?php foreach ($opts as $o): ?>
                        <label class="option">
                            <input type="radio" name="q_<?php echo $q['id']; ?>" value="<?php echo $o['v']; ?>" required> 
                            <?php echo htmlspecialchars($o['t']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn-submit">Valider mes réponses</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>