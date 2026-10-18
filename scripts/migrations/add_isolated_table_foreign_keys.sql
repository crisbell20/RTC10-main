-- Connect leftover tables to the ERD (exam code attempts, settings editor, login lockout).
-- Safe: no row deletes except orphan code-attempt ids that cannot satisfy a foreign key.

-- tbl_exam_code_attempt -> tbl_exam / tbl_user
DELETE FROM tbl_exam_code_attempt
WHERE Exam_ID NOT IN (SELECT Exam_ID FROM tbl_exam)
   OR User_ID NOT IN (SELECT User_ID FROM tbl_user);

ALTER TABLE tbl_exam_code_attempt
  ADD CONSTRAINT fk_code_attempt_exam
    FOREIGN KEY (Exam_ID) REFERENCES tbl_exam (Exam_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_code_attempt_user
    FOREIGN KEY (User_ID) REFERENCES tbl_user (User_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- tbl_system_setting.Updated_By -> tbl_user
UPDATE tbl_system_setting
SET Updated_By = NULL
WHERE Updated_By IS NOT NULL
  AND Updated_By NOT IN (SELECT User_ID FROM tbl_user);

ALTER TABLE tbl_system_setting
  ADD CONSTRAINT fk_setting_updated_by
    FOREIGN KEY (Updated_By) REFERENCES tbl_user (User_ID) ON DELETE SET NULL ON UPDATE CASCADE;

-- tbl_login_attempt.User_ID -> tbl_user (nullable: IP / unknown identifier rows stay NULL)
ALTER TABLE tbl_login_attempt
  ADD COLUMN User_ID INT NULL AFTER Attempt_ID;

ALTER TABLE tbl_login_attempt
  ADD CONSTRAINT fk_login_attempt_user
    FOREIGN KEY (User_ID) REFERENCES tbl_user (User_ID) ON DELETE SET NULL ON UPDATE CASCADE;
