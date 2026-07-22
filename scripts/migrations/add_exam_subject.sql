-- Multi-subject scope for modular exams (questions from multiple subjects/modules).
CREATE TABLE IF NOT EXISTS `tbl_exam_subject` (
  `Exam_Subject_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Exam_ID` int(11) NOT NULL,
  `Subject_ID` int(11) NOT NULL,
  `Is_Primary` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Exam_Subject_ID`),
  UNIQUE KEY `uq_exam_subject` (`Exam_ID`, `Subject_ID`),
  KEY `fk_exam_subject_exam` (`Exam_ID`),
  KEY `fk_exam_subject_subject` (`Subject_ID`),
  CONSTRAINT `fk_exam_subject_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam` (`Exam_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_subject_subject` FOREIGN KEY (`Subject_ID`) REFERENCES `tbl_subject` (`Subject_ID`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
