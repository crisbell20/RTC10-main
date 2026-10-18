-- Exam access code and archive support (Google Classroom style)

ALTER TABLE `tbl_exam`
  ADD COLUMN IF NOT EXISTS `Exam_Code` varchar(12) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Exam_Code_Generated_At` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `Exam_Code_Reset_Count` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `Archived_At` datetime DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `tbl_exam_code_attempt` (
  `Attempt_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Exam_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `IP_Address` varchar(45) DEFAULT NULL,
  `Success` tinyint(1) NOT NULL DEFAULT 0,
  `Attempted_At` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Attempt_ID`),
  KEY `idx_attempt_lookup` (`Exam_ID`,`User_ID`,`Attempted_At`),
  CONSTRAINT `fk_code_attempt_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam` (`Exam_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_code_attempt_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
