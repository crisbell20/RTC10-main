-- ============================================================================
-- Exam Deployment System Migration
-- ============================================================================
-- This migration creates the necessary tables for the Exam Deployment System:
-- 1. tbl_exam_question - Links exams to questions with ordering
-- 2. tbl_exam_batch - Links exams to batches for deployment
--
-- NOTE: User enrollment is handled via tbl_user_batch junction table.
-- If that table is missing, import scripts/migrations/restore_user_batch.sql via the MySQL CLI.
--
-- Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9, 2.10
-- ============================================================================

-- --------------------------------------------------------
--
-- Table structure for table `tbl_exam_question`
-- Links exams to questions from the question bank
--

CREATE TABLE IF NOT EXISTS `tbl_exam_question` (
  `Exam_Question_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Exam_ID` int(11) NOT NULL,
  `Question_ID` int(11) NOT NULL,
  `Question_Order` int(11) NOT NULL DEFAULT 0,
  `Date_Added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Exam_Question_ID`),
  UNIQUE KEY `uq_exam_question` (`Exam_ID`, `Question_ID`),
  KEY `idx_exam_question_order` (`Exam_ID`, `Question_Order`),
  CONSTRAINT `fk_eq_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam`(`Exam_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_eq_question` FOREIGN KEY (`Question_ID`) REFERENCES `tbl_question_bank`(`Question_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
--
-- Table structure for table `tbl_exam_batch`
-- Links exams to batches for deployment
--

CREATE TABLE IF NOT EXISTS `tbl_exam_batch` (
  `Exam_Batch_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Exam_ID` int(11) NOT NULL,
  `Batch_ID` int(11) NOT NULL,
  `Date_Assigned` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Exam_Batch_ID`),
  UNIQUE KEY `uq_exam_batch` (`Exam_ID`, `Batch_ID`),
  KEY `idx_exam_batch_batch` (`Batch_ID`),
  CONSTRAINT `fk_eb_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam`(`Exam_ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_eb_batch` FOREIGN KEY (`Batch_ID`) REFERENCES `tbl_batch`(`Batch_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
