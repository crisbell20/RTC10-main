-- Course → Module → Subject curriculum hierarchy

CREATE TABLE IF NOT EXISTS `tbl_module` (
  `Module_ID` int(11) NOT NULL AUTO_INCREMENT,
  `Course_ID` int(11) NOT NULL,
  `Module_Name` varchar(200) NOT NULL,
  `Module_Code` varchar(50) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Sort_Order` int(11) NOT NULL DEFAULT 0,
  `Date_Created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Module_ID`),
  KEY `idx_module_course` (`Course_ID`),
  UNIQUE KEY `uq_module_code_course` (`Course_ID`, `Module_Code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Module_ID on subjects (run migration script to backfill before NOT NULL)
ALTER TABLE `tbl_subject` ADD COLUMN IF NOT EXISTS `Module_ID` int(11) DEFAULT NULL AFTER `Course_ID`;
