ALTER TABLE `tbl_question_bank`
  ADD COLUMN `Is_Archived` tinyint(1) NOT NULL DEFAULT 0 AFTER `Question_Type`,
  ADD COLUMN `Archived_At` datetime DEFAULT NULL AFTER `Is_Archived`;

ALTER TABLE `tbl_user`
  ADD COLUMN `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN `Archived_At` datetime DEFAULT NULL;
