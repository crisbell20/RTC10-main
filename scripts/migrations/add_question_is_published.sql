ALTER TABLE `tbl_question_bank`
  ADD COLUMN `Is_Published` tinyint(1) NOT NULL DEFAULT 1
  COMMENT '0 = exam draft only, promoted to bank on exam publish'
  AFTER `Question_Type`;

UPDATE `tbl_question_bank` SET `Is_Published` = 1 WHERE `Is_Published` IS NULL;
