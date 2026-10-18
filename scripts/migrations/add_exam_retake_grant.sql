-- One authorized retake per trainee per exam.
-- Existing sessions stay as original attempts (both flags default to 0).
-- No rows are updated or deleted.

ALTER TABLE tbl_exam_session
  ADD COLUMN Retake_Granted tinyint(1) NOT NULL DEFAULT 0 AFTER Cheating_Unlocked_By,
  ADD COLUMN Is_Retake tinyint(1) NOT NULL DEFAULT 0 AFTER Retake_Granted;
