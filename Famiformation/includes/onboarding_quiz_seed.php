<?php
// ============================================================
// onboarding_quiz_seed.php — alimente AUTOMATIQUEMENT les quiz de l'onboarding
// « classique » (non beta), qui passent par quiz_engine.php + la table
// quiz_questions. On réutilise EXACTEMENT les mêmes questions que la version
// beta (beta_quiz_data.php), réparties par thème :
//   • source 'pdf'   → thème 'quiz_onboarding_pdf'
//   • source 'video' → thème 'quiz_onboarding_video'
// Idempotent : on n'insère QUE si le thème est encore vide (aucun doublon).
// ============================================================

if (!function_exists('seedOnboardingTheme')) {
    /**
     * @return int Nombre de questions insérées (0 si le thème avait déjà des
     *             questions ou si le thème n'est pas un thème onboarding géré).
     */
    function seedOnboardingTheme(PDO $db, $theme)
    {
        $map = ['quiz_onboarding_pdf' => 'pdf', 'quiz_onboarding_video' => 'video'];
        if (!isset($map[$theme])) { return 0; }
        try {
            $c = $db->prepare('SELECT COUNT(*) FROM quiz_questions WHERE theme = ?');
            $c->execute([$theme]);
            if ((int) $c->fetchColumn() > 0) { return 0; } // déjà alimenté → on ne touche pas
        } catch (Throwable $e) { return 0; }

        require_once __DIR__ . '/beta_quiz_data.php';
        if (!function_exists('betaQuizBank')) { return 0; }
        $bank = betaQuizBank();
        $questions = $bank['Onboarding']['questions'] ?? [];
        $src = $map[$theme];

        // Insert avec les colonnes NL (présentes en base) laissées vides : quiz_engine
        // n'utilise que les colonnes FR.
        try {
            $ins = $db->prepare('INSERT INTO quiz_questions (theme, question_text, option_a, option_b, option_c, reponse_correcte, question_text_nl, option_a_nl, option_b_nl, option_c_nl) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        } catch (Throwable $e) { return 0; }

        $n = 0;
        foreach ($questions as $q) {
            if ((string) ($q['source'] ?? '') !== $src) { continue; }
            $o = $q['options'] ?? [];
            // La bonne réponse est toujours la 1re option → reponse_correcte = 'A'.
            try {
                $ins->execute([
                    $theme, (string) ($q['q'] ?? ''),
                    (string) ($o[0] ?? ''), (string) ($o[1] ?? ''), (string) ($o[2] ?? ''),
                    'A', '', '', '', '',
                ]);
                $n++;
            } catch (Throwable $e) { /* on continue le reste */ }
        }
        return $n;
    }
}
