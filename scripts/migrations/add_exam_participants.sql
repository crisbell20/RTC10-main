-- Per-exam participant allowlist (Google Classroom style)
CREATE TABLE IF NOT EXISTS `tbl_exam_participant` (
  `Exam_Participant_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Exam_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Date_Assigned` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Exam_Participant_ID`),
  UNIQUE KEY `uq_exam_participant` (`Exam_ID`, `User_ID`),
  KEY `idx_exam_participant_user` (`User_ID`),
  CONSTRAINT `fk_exam_participant_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam` (`Exam_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_participant_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
