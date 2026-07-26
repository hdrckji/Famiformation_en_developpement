-- ============================================================
-- Ajoute les colonnes néerlandaises à la table quiz_questions.
-- À importer UNE fois dans phpMyAdmin (base Famiformation) AVANT
-- d'importer le fichier des traductions (UPDATE ... _nl).
-- Le quiz (questions_seed) lit ces colonnes automatiquement :
--   question_text_nl, option_a_nl, option_b_nl, option_c_nl
-- Une colonne vide (NULL) => on retombe sur le FR au rendu.
-- ============================================================

ALTER TABLE `quiz_questions`
  ADD COLUMN `question_text_nl` TEXT NULL AFTER `question_text`,
  ADD COLUMN `option_a_nl` VARCHAR(255) NULL AFTER `option_a`,
  ADD COLUMN `option_b_nl` VARCHAR(255) NULL AFTER `option_b`,
  ADD COLUMN `option_c_nl` VARCHAR(255) NULL AFTER `option_c`;
