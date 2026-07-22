-- Store section on user/exam enrollments and remove section-linked tbl_batch rows.
-- Run via: php scripts/migrations/run_section_enrollment_migration.php

ALTER TABLE `tbl_user_batch`
  ADD COLUMN `Section_ID` int(11) DEFAULT NULL AFTER `Batch_ID`;

ALTER TABLE `tbl_exam_batch`
  ADD COLUMN `Section_ID` int(11) DEFAULT NULL AFTER `Batch_ID`;

ALTER TABLE `tbl_exam_batch`
  DROP INDEX `uq_exam_batch`;

ALTER TABLE `tbl_exam_batch`
  ADD UNIQUE KEY `uq_exam_batch_section` (`Exam_ID`, `Batch_ID`, `Section_ID`);

ALTER TABLE `tbl_user_batch`
  ADD KEY `idx_user_batch_section` (`Section_ID`);

ALTER TABLE `tbl_user_batch`
  ADD CONSTRAINT `fk_user_batch_section` FOREIGN KEY (`Section_ID`) REFERENCES `tbl_academic_section` (`Section_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `tbl_exam_batch`
  ADD CONSTRAINT `fk_exam_batch_section` FOREIGN KEY (`Section_ID`) REFERENCES `tbl_academic_section` (`Section_ID`) ON DELETE CASCADE ON UPDATE CASCADE;
