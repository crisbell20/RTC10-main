-- Structured exam types for RTC10 Online Examination System
ALTER TABLE `tbl_exam`
  ADD COLUMN `Exam_Type` ENUM('PRE_TEST','MODULAR','COMPREHENSIVE','POST_TEST') NOT NULL DEFAULT 'MODULAR'
  AFTER `Title`;
