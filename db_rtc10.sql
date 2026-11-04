-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 04, 2026 at 05:04 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_rtc10`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_academic_section`
--

CREATE TABLE `tbl_academic_section` (
  `Section_ID` int(11) NOT NULL,
  `Batch_ID` int(11) NOT NULL,
  `Section_Name` varchar(100) NOT NULL,
  `Capacity` int(11) NOT NULL DEFAULT 40,
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_academic_section`
--

INSERT INTO `tbl_academic_section` (`Section_ID`, `Batch_ID`, `Section_Name`, `Capacity`, `Is_Archived`, `Archived_At`) VALUES
(56, 87, 'Alpha', 50, 0, NULL),
(57, 90, 'Alpha', 50, 0, NULL),
(58, 87, 'Bravo', 50, 0, NULL),
(59, 87, 'Charlie', 50, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_answer`
--

CREATE TABLE `tbl_answer` (
  `Answer_ID` int(11) NOT NULL,
  `Result_ID` int(11) NOT NULL,
  `Session_ID` int(11) NOT NULL,
  `Question_ID` int(11) NOT NULL,
  `Choice_ID` int(11) DEFAULT NULL COMMENT 'NULL for open-ended questions',
  `Answer_Correct` text DEFAULT NULL COMMENT 'Free-text answer for non-choice types',
  `Is_Correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_answer`
--

INSERT INTO `tbl_answer` (`Answer_ID`, `Result_ID`, `Session_ID`, `Question_ID`, `Choice_ID`, `Answer_Correct`, `Is_Correct`) VALUES
(4819, 293, 302, 237, 1130, NULL, 1),
(4820, 293, 302, 238, 1131, NULL, 1),
(4821, 293, 302, 239, 1139, NULL, 1),
(4822, 293, 302, 240, 1142, NULL, 1),
(4823, 293, 302, 241, 1148, NULL, 1),
(4824, 293, 302, 242, 1155, NULL, 1),
(4825, 293, 302, 233, 1106, NULL, 1),
(4826, 293, 302, 234, 1112, NULL, 1),
(4827, 293, 302, 235, 1118, NULL, 1),
(4828, 293, 302, 236, 1124, NULL, 1),
(4829, 294, 303, 237, 1130, NULL, 1),
(4830, 294, 303, 238, 1131, NULL, 1),
(4831, 294, 303, 239, 1139, NULL, 1),
(4832, 294, 303, 240, 1142, NULL, 1),
(4833, 294, 303, 241, 1148, NULL, 1),
(4834, 294, 303, 242, 1155, NULL, 1),
(4835, 294, 303, 233, 1106, NULL, 1),
(4836, 294, 303, 234, 1112, NULL, 1),
(4837, 294, 303, 235, 1118, NULL, 1),
(4838, 294, 303, 236, 1124, NULL, 1),
(4839, 295, 304, 237, 1130, NULL, 1),
(4840, 295, 304, 238, 1131, NULL, 1),
(4841, 295, 304, 239, 1139, NULL, 1),
(4842, 295, 304, 240, 1143, NULL, 0),
(4843, 295, 304, 241, 1149, NULL, 0),
(4844, 295, 304, 242, 1152, NULL, 0),
(4845, 295, 304, 233, 1110, NULL, 0),
(4846, 295, 304, 234, 1114, NULL, 0),
(4847, 295, 304, 235, 1119, NULL, 0),
(4848, 295, 304, 236, 1122, NULL, 0),
(4849, 296, 305, 237, 1129, NULL, 0),
(4850, 296, 305, 238, 1131, NULL, 1),
(4851, 296, 305, 239, 1138, NULL, 0),
(4852, 296, 305, 240, 1143, NULL, 0),
(4853, 296, 305, 241, 1147, NULL, 0),
(4854, 296, 305, 242, 1155, NULL, 1),
(4855, 296, 305, 233, 1106, NULL, 1),
(4856, 296, 305, 234, 1113, NULL, 0),
(4857, 296, 305, 235, 1118, NULL, 1),
(4858, 296, 305, 236, 1125, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_log`
--

CREATE TABLE `tbl_audit_log` (
  `Log_ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Action` varchar(255) NOT NULL,
  `Module` varchar(50) DEFAULT NULL,
  `Outcome` text DEFAULT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'SUCCESS',
  `IP_Address` varchar(45) DEFAULT NULL,
  `User_Role` varchar(50) DEFAULT NULL,
  `Timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_batch`
--

CREATE TABLE `tbl_batch` (
  `Batch_ID` int(11) NOT NULL,
  `Course_ID` int(11) NOT NULL,
  `Section_ID` int(11) DEFAULT NULL,
  `Batch_Name` varchar(150) NOT NULL,
  `Date_Started` date DEFAULT NULL,
  `Date_Ended` date DEFAULT NULL,
  `Status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `Date_Enrolled` datetime DEFAULT current_timestamp(),
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_batch`
--

INSERT INTO `tbl_batch` (`Batch_ID`, `Course_ID`, `Section_ID`, `Batch_Name`, `Date_Started`, `Date_Ended`, `Status`, `Date_Enrolled`, `Is_Archived`, `Archived_At`) VALUES
(87, 1, NULL, 'PSBRC 2026-01', '2026-10-13', '2027-04-13', 'Active', '2026-10-13 10:51:34', 0, NULL),
(88, 1, 56, 'Alpha Enrollment', NULL, NULL, 'Active', '2026-10-13 10:51:41', 0, NULL),
(89, 8, NULL, 'PSSLC 2026-01', NULL, NULL, 'Active', '2026-10-13 11:07:27', 0, NULL),
(90, 8, NULL, 'PSSLC 2026-02', NULL, NULL, 'Active', '2026-10-13 11:07:33', 0, NULL),
(91, 8, 57, 'Alpha Enrollment', NULL, NULL, 'Active', '2026-10-13 11:07:48', 0, NULL),
(92, 1, 58, 'Bravo Enrollment', NULL, NULL, 'Active', '2026-11-04 00:54:03', 0, NULL),
(93, 1, 59, 'Charlie Enrollment', NULL, NULL, 'Active', '2026-11-04 00:54:09', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cheating_incident`
--

CREATE TABLE `tbl_cheating_incident` (
  `Incident_ID` int(11) NOT NULL,
  `Session_ID` int(11) NOT NULL,
  `Reason` text NOT NULL,
  `Timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Open') NOT NULL DEFAULT 'Open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_cheating_incident`
--

INSERT INTO `tbl_cheating_incident` (`Incident_ID`, `Session_ID`, `Reason`, `Timestamp`, `Status`) VALUES
(112, 304, 'Latest: Exited fullscreen | Total logged events: 1', '2026-10-13 11:00:29', 'Open'),
(113, 305, 'Latest: Exited fullscreen | Total logged events: 1', '2026-10-13 11:06:12', 'Open');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cheating_log`
--

CREATE TABLE `tbl_cheating_log` (
  `Cheating_Log_ID` int(11) NOT NULL,
  `Session_ID` int(11) NOT NULL,
  `Type` varchar(100) NOT NULL,
  `Timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_cheating_log`
--

INSERT INTO `tbl_cheating_log` (`Cheating_Log_ID`, `Session_ID`, `Type`, `Timestamp`) VALUES
(775, 304, 'FULLSCREEN_EXIT', '2026-10-13 11:00:29'),
(776, 305, 'VISIBILITY_HIDDEN', '2026-10-13 11:04:41'),
(777, 305, 'FULLSCREEN_EXIT', '2026-10-13 11:06:12');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_choice`
--

CREATE TABLE `tbl_choice` (
  `Choice_ID` int(11) NOT NULL,
  `Question_ID` int(11) NOT NULL,
  `Choice_Text` text NOT NULL,
  `Is_Correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_choice`
--

INSERT INTO `tbl_choice` (`Choice_ID`, `Question_ID`, `Choice_Text`, `Is_Correct`) VALUES
(1106, 233, 'To guide police officers in determining and doing what is right in the performance of their duties', 1),
(1107, 233, 'To give police officers greater authority over civilians', 0),
(1108, 233, 'To increase the personal benefits of police officers', 0),
(1109, 233, 'To allow officers to make decisions without restrictions', 0),
(1110, 233, 'To ensure that officers avoid difficult assignments', 0),
(1111, 234, 'Being loyal to friends regardless of their actions', 0),
(1112, 234, 'Doing what is right, honest, and morally correct even when no one is watching', 1),
(1113, 234, 'Following orders without considering whether they are proper', 0),
(1114, 234, 'Protecting one\'s personal interests at all times', 0),
(1115, 234, 'Avoiding responsibility for mistakes', 0),
(1116, 235, 'Accept the money if the amount is small', 0),
(1117, 235, 'Accept the money and give the person a warning', 0),
(1118, 235, 'Refuse the money and enforce the law according to proper procedures', 1),
(1119, 235, 'Accept the money and give it to another officer', 0),
(1120, 235, 'Ask the person to offer something else instead', 0),
(1121, 236, 'It allows police officers to give special treatment to important people', 0),
(1122, 236, 'It helps officers avoid dealing with difficult individuals', 0),
(1123, 236, 'It allows officers to prioritize their personal relationships', 0),
(1124, 236, 'It ensures that people are treated fairly without favoritism or discrimination', 1),
(1125, 236, 'It allows officers to choose which laws they want to enforce', 0),
(1126, 237, 'Hide the relationship from the supervisor', 0),
(1127, 237, 'Give the friend information about the investigation', 0),
(1128, 237, 'Handle the case secretly to protect the friendship', 0),
(1129, 237, 'Ask the friend to cooperate only if there is a personal benefit', 0),
(1130, 237, 'Disclose the potential conflict of interest and follow the proper procedure', 1),
(1131, 238, 'Being responsible for one\'s actions and being willing to explain and accept their consequences', 1),
(1132, 238, 'Protecting fellow officers from all forms of investigation', 0),
(1133, 238, 'Avoiding responsibility when an error occurs', 0),
(1134, 238, 'Following only instructions from senior officers', 0),
(1135, 238, 'Keeping mistakes confidential regardless of their seriousness', 0),
(1136, 239, 'Delete the report and create a new one without informing anyone', 0),
(1137, 239, 'Blame another officer for the error', 0),
(1138, 239, 'Ignore the mistake if it does not immediately cause a problem', 0),
(1139, 239, 'Correct the error properly and inform the appropriate authority when necessary', 1),
(1140, 239, 'Ask a colleague to hide the mistake', 0),
(1141, 240, 'Using one\'s position to obtain personal favors', 0),
(1142, 240, 'Performing assigned duties competently, responsibly, and respectfully', 1),
(1143, 240, 'Giving preferential treatment to friends', 0),
(1144, 240, 'Ignoring established procedures when convenient', 0),
(1145, 240, 'Sharing confidential information with civilians', 0),
(1146, 241, 'To prevent supervisors from reviewing police operations', 0),
(1147, 241, 'To give officers greater control over investigations', 0),
(1148, 241, 'To protect sensitive information from unauthorized disclosure and possible harm or misuse', 1),
(1149, 241, 'To prevent the public from knowing about police activities', 0),
(1150, 241, 'To allow officers to use information for personal purposes', 0),
(1151, 242, 'Follow the instruction immediately because it came from a superior', 0),
(1152, 242, 'Perform the instruction but keep it secret', 0),
(1153, 242, 'Ask a friend outside the organization to decide', 0),
(1154, 242, 'Ignore the instruction without explaining the reason', 0),
(1155, 242, 'Decline to perform the unlawful act and seek appropriate guidance through proper channels', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_course`
--

CREATE TABLE `tbl_course` (
  `Course_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL COMMENT 'Creator',
  `Course_Name` varchar(200) NOT NULL,
  `Details` text DEFAULT NULL,
  `Duration` varchar(80) DEFAULT NULL,
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_course`
--

INSERT INTO `tbl_course` (`Course_ID`, `User_ID`, `Course_Name`, `Details`, `Duration`, `Is_Archived`, `Archived_At`) VALUES
(1, 1, 'PSBRC', '', '6 months', 0, NULL),
(2, 1, 'PSJLC', '', '3 months', 0, NULL),
(5, 1, 'PSOCC', '', '6 months', 0, NULL),
(8, 1, 'PSSLC', '', '6 months', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam`
--

CREATE TABLE `tbl_exam` (
  `Exam_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL COMMENT 'Created by',
  `Subject_ID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `Schedule_Date` datetime DEFAULT NULL,
  `Deadline` datetime DEFAULT NULL COMMENT 'Deadline to start the exam',
  `Duration` int(11) DEFAULT NULL COMMENT 'Total exam duration in minutes',
  `Passing_Score` decimal(5,2) DEFAULT NULL,
  `Status` enum('Draft','Published','Closed') NOT NULL DEFAULT 'Draft',
  `Is_Randomized` tinyint(1) NOT NULL DEFAULT 0,
  `Exam_Code` varchar(12) DEFAULT NULL,
  `Exam_Code_Generated_At` datetime DEFAULT NULL,
  `Exam_Code_Reset_Count` int(11) NOT NULL DEFAULT 0,
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL,
  `Allow_Response_Review` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_exam`
--

INSERT INTO `tbl_exam` (`Exam_ID`, `User_ID`, `Subject_ID`, `Title`, `Description`, `Schedule_Date`, `Deadline`, `Duration`, `Passing_Score`, `Status`, `Is_Randomized`, `Exam_Code`, `Exam_Code_Generated_At`, `Exam_Code_Reset_Count`, `Is_Archived`, `Archived_At`, `Allow_Response_Review`) VALUES
(97, 1, 40, 'PSBRC Pre-Test ', '', '2026-11-04 03:20:41', '2026-11-04 05:20:00', 60, 50.00, 'Closed', 0, '344UKC', '2026-10-13 10:54:37', 0, 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam_batch`
--

CREATE TABLE `tbl_exam_batch` (
  `Exam_Batch_ID` int(11) NOT NULL,
  `Exam_ID` int(11) NOT NULL,
  `Batch_ID` int(11) NOT NULL,
  `Date_Assigned` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_exam_batch`
--

INSERT INTO `tbl_exam_batch` (`Exam_Batch_ID`, `Exam_ID`, `Batch_ID`, `Date_Assigned`) VALUES
(174, 97, 88, '2026-10-13 10:54:37');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam_code_attempt`
--

CREATE TABLE `tbl_exam_code_attempt` (
  `Attempt_ID` int(11) NOT NULL,
  `Exam_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `IP_Address` varchar(45) DEFAULT NULL,
  `Success` tinyint(1) NOT NULL DEFAULT 0,
  `Attempted_At` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_exam_code_attempt`
--

INSERT INTO `tbl_exam_code_attempt` (`Attempt_ID`, `Exam_ID`, `User_ID`, `IP_Address`, `Success`, `Attempted_At`) VALUES
(226, 97, 8, '::1', 1, '2026-10-13 10:55:09'),
(227, 97, 9, '::1', 1, '2026-10-13 10:58:24'),
(228, 97, 10, '::1', 1, '2026-10-13 10:59:48'),
(229, 97, 10, '::1', 1, '2026-10-13 11:04:15');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam_participant`
--

CREATE TABLE `tbl_exam_participant` (
  `Exam_Participant_ID` int(11) NOT NULL,
  `Exam_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Date_Assigned` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_exam_participant`
--

INSERT INTO `tbl_exam_participant` (`Exam_Participant_ID`, `Exam_ID`, `User_ID`, `Date_Assigned`) VALUES
(911, 97, 8, '2026-10-13 10:54:38'),
(912, 97, 10, '2026-10-13 10:54:38'),
(913, 97, 9, '2026-10-13 10:54:38');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam_question`
--

CREATE TABLE `tbl_exam_question` (
  `Exam_Question_ID` int(11) NOT NULL,
  `Exam_ID` int(11) NOT NULL,
  `Question_ID` int(11) NOT NULL,
  `Question_Order` int(11) NOT NULL DEFAULT 0,
  `Date_Added` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_exam_question`
--

INSERT INTO `tbl_exam_question` (`Exam_Question_ID`, `Exam_ID`, `Question_ID`, `Question_Order`, `Date_Added`) VALUES
(1252, 97, 237, 1, '2026-10-13 10:54:37'),
(1253, 97, 238, 2, '2026-10-13 10:54:37'),
(1254, 97, 239, 3, '2026-10-13 10:54:37'),
(1255, 97, 240, 4, '2026-10-13 10:54:37'),
(1256, 97, 241, 5, '2026-10-13 10:54:37'),
(1257, 97, 242, 6, '2026-10-13 10:54:37'),
(1258, 97, 233, 7, '2026-10-13 10:54:37'),
(1259, 97, 234, 8, '2026-10-13 10:54:37'),
(1260, 97, 235, 9, '2026-10-13 10:54:37'),
(1261, 97, 236, 10, '2026-10-13 10:54:37');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam_session`
--

CREATE TABLE `tbl_exam_session` (
  `Session_ID` int(11) NOT NULL,
  `Exam_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL COMMENT 'Student taking the exam',
  `Time_Started` datetime NOT NULL DEFAULT current_timestamp(),
  `Time_Ended` datetime DEFAULT NULL,
  `Occupancy_Token` varchar(64) DEFAULT NULL,
  `Occupancy_At` datetime DEFAULT NULL,
  `Cheating_Locked` tinyint(1) NOT NULL DEFAULT 0,
  `Cheating_Locked_At` datetime DEFAULT NULL,
  `Cheating_Unlocked_At` datetime DEFAULT NULL,
  `Cheating_Unlocked_By` int(11) DEFAULT NULL,
  `Retake_Granted` tinyint(1) NOT NULL DEFAULT 0,
  `Is_Retake` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_exam_session`
--

INSERT INTO `tbl_exam_session` (`Session_ID`, `Exam_ID`, `User_ID`, `Time_Started`, `Time_Ended`, `Occupancy_Token`, `Occupancy_At`, `Cheating_Locked`, `Cheating_Locked_At`, `Cheating_Unlocked_At`, `Cheating_Unlocked_By`, `Retake_Granted`, `Is_Retake`) VALUES
(302, 97, 8, '2026-10-13 10:55:09', '2026-10-13 10:57:52', NULL, NULL, 0, NULL, NULL, NULL, 0, 0),
(303, 97, 9, '2026-10-13 10:58:24', '2026-10-13 10:59:15', NULL, NULL, 0, NULL, NULL, NULL, 0, 0),
(304, 97, 10, '2026-10-13 10:59:48', '2026-10-13 11:01:53', NULL, NULL, 0, '2026-10-13 11:00:29', '2026-10-13 11:01:09', 1, 0, 0),
(305, 97, 10, '2026-10-13 11:04:15', '2026-10-13 11:09:12', NULL, NULL, 0, '2026-10-13 11:06:12', '2026-10-13 11:08:38', 1, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_login_attempt`
--

CREATE TABLE `tbl_login_attempt` (
  `Attempt_ID` int(11) NOT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Scope` varchar(20) NOT NULL,
  `Scope_Value` varchar(128) NOT NULL,
  `Failed_Count` int(11) NOT NULL DEFAULT 0,
  `Locked_Until` datetime DEFAULT NULL,
  `Last_Failed_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_login_attempt`
--

INSERT INTO `tbl_login_attempt` (`Attempt_ID`, `User_ID`, `Scope`, `Scope_Value`, `Failed_Count`, `Locked_Until`, `Last_Failed_At`) VALUES
(49, NULL, 'identifier', 'admin@123', 1, NULL, '2026-09-15 21:22:23'),
(50, NULL, 'identifier', 'a003`', 1, NULL, '2026-09-16 11:38:57'),
(52, NULL, 'identifier', 'a005', 1, NULL, '2026-09-30 23:58:42'),
(56, NULL, 'identifier', '1004', 3, NULL, '2026-10-07 14:50:01'),
(57, NULL, 'identifier', '10003', 2, NULL, '2026-10-07 14:49:46');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_module`
--

CREATE TABLE `tbl_module` (
  `Module_ID` int(11) NOT NULL,
  `Course_ID` int(11) NOT NULL,
  `Module_Name` varchar(200) NOT NULL,
  `Description` text DEFAULT NULL,
  `Sort_Order` int(11) NOT NULL DEFAULT 0,
  `Date_Created` datetime NOT NULL DEFAULT current_timestamp(),
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_module`
--

INSERT INTO `tbl_module` (`Module_ID`, `Course_ID`, `Module_Name`, `Description`, `Sort_Order`, `Date_Created`, `Is_Archived`, `Archived_At`) VALUES
(17, 1, 'MODULE I: Breaking Period', NULL, 4, '2026-10-07 20:10:43', 0, NULL),
(18, 1, 'MODULE II: General Subjects', NULL, 5, '2026-10-07 20:11:40', 0, NULL),
(19, 1, 'MODULE III: Effective Communication', NULL, 6, '2026-10-07 20:13:24', 0, NULL),
(20, 1, 'MODULE IV: Law', NULL, 7, '2026-10-07 20:13:32', 0, NULL),
(21, 1, 'MODULE V: Law Enforcement Operations', NULL, 8, '2026-10-07 20:13:40', 0, NULL),
(22, 1, 'MODULE VIII: Academic Integration', NULL, 9, '2026-10-07 20:13:47', 0, NULL),
(23, 2, 'MODULE I: General Subjects', NULL, 3, '2026-10-07 20:17:20', 0, NULL),
(24, 2, 'MODULE II: Police Administration', NULL, 4, '2026-10-07 20:17:29', 0, NULL),
(25, 2, 'MODULE III: Leadership', NULL, 5, '2026-10-07 20:17:45', 0, NULL),
(26, 2, 'MODULE IV: Laws Subjects', NULL, 6, '2026-10-07 20:17:55', 0, NULL),
(27, 2, 'MODULE V: Police Operations', NULL, 7, '2026-10-07 20:18:05', 0, NULL),
(28, 5, 'MODULE I: General Subjects', NULL, 2, '2026-10-07 20:22:30', 0, NULL),
(29, 5, 'MODULE II: Police Administration', NULL, 3, '2026-10-07 20:22:52', 0, NULL),
(30, 5, 'MODULE III: Management', NULL, 4, '2026-10-07 20:23:03', 0, NULL),
(31, 5, 'MODULE IV: Law', NULL, 5, '2026-10-07 20:23:11', 0, NULL),
(32, 5, 'MODULE V: Police Operations', NULL, 6, '2026-10-07 20:23:24', 0, NULL),
(33, 8, 'MODULE I: General Subjects', NULL, 1, '2026-10-07 20:31:22', 0, NULL),
(34, 8, 'MODULE II: Police Administration', NULL, 2, '2026-10-07 20:31:31', 0, NULL),
(35, 8, 'MODULE III: Supervision', NULL, 3, '2026-10-07 20:31:39', 0, NULL),
(36, 8, 'MODULE IV: Laws and Jurisprudence', NULL, 4, '2026-10-07 20:31:51', 0, NULL),
(37, 8, 'MODULE V: Police Operations', NULL, 5, '2026-10-07 20:32:00', 0, NULL),
(38, 8, 'MODULE VI: Academic Modular Integration', NULL, 6, '2026-10-07 20:32:12', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_question_bank`
--

CREATE TABLE `tbl_question_bank` (
  `Question_ID` int(11) NOT NULL,
  `Subject_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL COMMENT 'Created by',
  `Question_Text` text NOT NULL,
  `Question_Type` enum('Multiple Choice') NOT NULL DEFAULT 'Multiple Choice',
  `Is_Published` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = exam draft only, promoted to bank on exam publish',
  `Added_Date` datetime NOT NULL DEFAULT current_timestamp(),
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_question_bank`
--

INSERT INTO `tbl_question_bank` (`Question_ID`, `Subject_ID`, `User_ID`, `Question_Text`, `Question_Type`, `Is_Published`, `Added_Date`, `Is_Archived`, `Archived_At`) VALUES
(233, 40, 1, 'What is the primary purpose of ethics in police service?', 'Multiple Choice', 1, '2026-10-12 00:26:20', 0, NULL),
(234, 40, 1, 'Which statement best describes integrity?', 'Multiple Choice', 1, '2026-10-12 00:26:20', 0, NULL),
(235, 40, 1, 'A police officer is offered money by a person who wants a traffic violation ignored. What should the officer do?', 'Multiple Choice', 1, '2026-10-12 00:26:20', 0, NULL),
(236, 40, 1, 'Why is impartiality important in police service?', 'Multiple Choice', 1, '2026-10-12 00:26:20', 0, NULL),
(237, 40, 1, 'A police officer discovers that a close friend is involved in a case assigned to the officer. What is the most ethical action?', 'Multiple Choice', 1, '2026-10-12 00:26:21', 0, NULL),
(238, 40, 1, 'What does accountability mean in the context of police service?', 'Multiple Choice', 1, '2026-10-12 00:26:21', 0, NULL),
(239, 40, 1, 'A police officer accidentally makes an error in an official report. What should the officer do?', 'Multiple Choice', 1, '2026-10-12 00:26:21', 0, NULL),
(240, 40, 1, 'Which action best demonstrates professionalism?', 'Multiple Choice', 1, '2026-10-12 00:26:21', 0, NULL),
(241, 40, 1, 'Why should police officers protect confidential information?', 'Multiple Choice', 1, '2026-10-12 00:26:21', 0, NULL),
(242, 40, 1, 'A superior gives a police officer an instruction that appears to be unlawful. What is the most appropriate response?', 'Multiple Choice', 1, '2026-10-12 00:26:21', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_result`
--

CREATE TABLE `tbl_result` (
  `Result_ID` int(11) NOT NULL,
  `Session_ID` int(11) NOT NULL,
  `Score` decimal(7,2) NOT NULL DEFAULT 0.00,
  `Percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `Remarks` varchar(200) DEFAULT NULL,
  `Submission_Date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_result`
--

INSERT INTO `tbl_result` (`Result_ID`, `Session_ID`, `Score`, `Percentage`, `Remarks`, `Submission_Date`) VALUES
(293, 302, 10.00, 100.00, 'Passed', '2026-10-13 10:57:52'),
(294, 303, 10.00, 100.00, 'Passed', '2026-10-13 10:59:15'),
(295, 304, 3.00, 30.00, 'Failed', '2026-10-13 11:01:53'),
(296, 305, 4.00, 40.00, 'Failed', '2026-10-13 11:09:12');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_role`
--

CREATE TABLE `tbl_role` (
  `Role_ID` int(11) NOT NULL,
  `Role_Name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_role`
--

INSERT INTO `tbl_role` (`Role_ID`, `Role_Name`) VALUES
(1, 'Admin'),
(2, 'CCMD'),
(3, 'Examinee');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subject`
--

CREATE TABLE `tbl_subject` (
  `Subject_ID` int(11) NOT NULL,
  `Course_ID` int(11) NOT NULL,
  `Module_ID` int(11) NOT NULL,
  `Subject_Name` varchar(200) NOT NULL,
  `Description` text DEFAULT NULL,
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_subject`
--

INSERT INTO `tbl_subject` (`Subject_ID`, `Course_ID`, `Module_ID`, `Subject_Name`, `Description`, `Is_Archived`, `Archived_At`) VALUES
(40, 1, 17, 'Ethics', NULL, 0, NULL),
(41, 1, 17, 'Family as an Institution', NULL, 0, NULL),
(42, 1, 17, 'Love  of Country', NULL, 0, NULL),
(43, 1, 17, 'The Police Officer', NULL, 0, NULL),
(44, 1, 17, 'Values', NULL, 0, NULL),
(45, 1, 18, 'Gender and Development', NULL, 0, NULL),
(46, 1, 18, 'Human Rights', NULL, 0, NULL),
(47, 1, 18, 'Philippine Criminal Justice System', NULL, 0, NULL),
(48, 1, 18, 'Public Safety Services', NULL, 0, NULL),
(49, 1, 18, 'Stress Management in Law Enforcement (SMILE)', NULL, 0, NULL),
(50, 1, 19, 'Correspondence', NULL, 0, NULL),
(51, 1, 19, 'Intro to Communication', NULL, 0, NULL),
(52, 1, 19, 'Media Relations', NULL, 0, NULL),
(53, 1, 19, 'Police Report Writing', NULL, 0, NULL),
(54, 1, 19, 'Public Speaking', NULL, 0, NULL),
(55, 1, 20, 'Criminal Law I: Revised Penal Code Book I', NULL, 0, NULL),
(56, 1, 20, 'Criminal Law II', NULL, 0, NULL),
(57, 1, 20, 'Philippine Constitution', NULL, 0, NULL),
(58, 1, 20, 'Rules of Criminal Procedure', NULL, 0, NULL),
(59, 1, 20, 'Rules on Evidence (Evidence Handling)', NULL, 0, NULL),
(60, 1, 21, 'Crime Scene Investigation', NULL, 0, NULL),
(61, 1, 21, 'Criminal Investigation', NULL, 0, NULL),
(62, 1, 21, 'Intro to Counterintelligence', NULL, 0, NULL),
(63, 1, 21, 'Investigation by Territorial Police Unit after an Armed Confrontation', NULL, 0, NULL),
(64, 1, 21, 'Police Intelligence', NULL, 0, NULL),
(65, 1, 22, 'Basic Radio and Telephone procedures, Operations and Preventive Maintenance', NULL, 0, NULL),
(66, 1, 22, 'PNPTI Training Guide', NULL, 0, NULL),
(67, 2, 23, 'Ecology and Environment Protection: A Review', NULL, 0, NULL),
(68, 2, 23, 'Human Rights and International Humanitarian Law', NULL, 0, NULL),
(69, 2, 23, 'Management Information System (MIS)', NULL, 0, NULL),
(70, 2, 23, 'PNP Customs and Tradition', NULL, 0, NULL),
(71, 2, 23, 'PNP P.A.T.R.O.L Plan 2030', NULL, 0, NULL),
(72, 2, 24, 'Financial Management and Literacy', NULL, 0, NULL),
(73, 2, 24, 'Human Resource Management (HRM)', NULL, 0, NULL),
(74, 2, 24, 'Logistics Management', NULL, 0, NULL),
(75, 2, 24, 'PNP Administrative Disciplinary Bodies and its Functions: A Review', NULL, 0, NULL),
(76, 2, 24, 'Records Management', NULL, 0, NULL),
(77, 2, 25, 'Communicating Effectively', NULL, 0, NULL),
(78, 2, 25, 'Doctrine of Command Responsibility', NULL, 0, NULL),
(79, 2, 25, 'Human Relations', NULL, 0, NULL),
(80, 2, 25, 'Leadership', NULL, 0, NULL),
(81, 2, 25, 'Overcoming Cross-Cultural Communication Barrier', NULL, 0, NULL),
(82, 2, 26, 'Criminal Law I', NULL, 0, NULL),
(83, 2, 26, 'Criminal Law Il', NULL, 0, NULL),
(84, 2, 26, 'Family Code', NULL, 0, NULL),
(85, 2, 26, 'Rules of Criminal Procedure (Rule 110-127)', NULL, 0, NULL),
(86, 2, 26, 'Rules of Evidence', NULL, 0, NULL),
(87, 2, 27, 'Counter Intelligence and Security', NULL, 0, NULL),
(88, 2, 27, 'Four Phases of Intelligence Cycle', NULL, 0, NULL),
(89, 2, 27, 'Intelligence Community', NULL, 0, NULL),
(90, 2, 27, 'Intelligence Cycle', NULL, 0, NULL),
(91, 2, 27, 'Intelligence Operation', NULL, 0, NULL),
(92, 5, 30, 'Best Practices', NULL, 0, NULL),
(93, 5, 30, 'Budgeting', NULL, 0, NULL),
(94, 5, 30, 'Conflict Management', NULL, 0, NULL),
(95, 5, 30, 'Controlling', NULL, 0, NULL),
(96, 5, 30, 'Financial Management', NULL, 0, NULL),
(97, 5, 30, 'Leading', NULL, 0, NULL),
(98, 5, 30, 'Management Concepts and Influences', NULL, 0, NULL),
(99, 5, 30, 'Management Process and Effectiveness', NULL, 0, NULL),
(100, 5, 30, 'Managing Grey Decision Making', NULL, 0, NULL),
(101, 5, 30, 'Organizational Behavior', NULL, 0, NULL),
(102, 5, 30, 'Organizing', NULL, 0, NULL),
(103, 5, 30, 'Planning', NULL, 0, NULL),
(104, 5, 30, 'Principles of Leadership', NULL, 0, NULL),
(105, 5, 30, 'Principles of Management', NULL, 0, NULL),
(106, 5, 30, 'Problem Solving and Decision Making', NULL, 0, NULL),
(107, 5, 30, 'The Quality Revolution', NULL, 0, NULL),
(108, 5, 30, 'Total Quality Strategies: A Global View', NULL, 0, NULL),
(109, 5, 31, 'Laws on Person and Family Relation', NULL, 0, NULL),
(110, 5, 31, 'Pertinent Special Laws', NULL, 0, NULL),
(111, 5, 31, 'Review of the Philippine Constitution', NULL, 0, NULL),
(112, 5, 31, 'Review on Criminal Law I', NULL, 0, NULL),
(113, 5, 31, 'Review on Criminal Law II', NULL, 0, NULL),
(114, 5, 31, 'Rules of Criminal Procedures', NULL, 0, NULL),
(115, 5, 31, 'Rules of Evidence', NULL, 0, NULL),
(116, 5, 31, 'The Local Government Code', NULL, 0, NULL),
(117, 5, 28, 'Concepts and Principles of Teaching and Learning', NULL, 0, NULL),
(118, 5, 28, 'Ecology and Environmental Protection', NULL, 0, NULL),
(119, 5, 28, 'Effective Instructional Planning', NULL, 0, NULL),
(120, 5, 28, 'Gender Awareness and Development: Mentoring and Implementation', NULL, 0, NULL),
(121, 5, 28, 'Human Rights: A Review with Case Based Approach', NULL, 0, NULL),
(122, 5, 28, 'Outcome-Based Education (OBE)', NULL, 0, NULL),
(123, 5, 28, 'Protocol and Social Amenities', NULL, 0, NULL),
(124, 5, 28, 'Research and Statistics', NULL, 0, NULL),
(125, 5, 28, 'Social Psychology and the Sustainable Future', NULL, 0, NULL),
(126, 5, 28, 'Statistics', NULL, 0, NULL),
(127, 5, 28, 'Stress Management', NULL, 0, NULL),
(128, 5, 29, 'Conference and Briefing Techniques', NULL, 0, NULL),
(129, 5, 29, 'Human Resources Management: A Review', NULL, 0, NULL),
(130, 5, 29, 'Integrated Area Community Public Safety Plan', NULL, 0, NULL),
(131, 5, 29, 'PNP Administrative Disciplinary Machinery: A Review', NULL, 0, NULL),
(132, 5, 29, 'Small Unit Administration', NULL, 0, NULL),
(133, 5, 29, 'Staff Study and Staff Function', NULL, 0, NULL),
(134, 5, 32, 'Counter Intelligence Operations', NULL, 0, NULL),
(135, 5, 32, 'Current Threats to National Security and Public Safety', NULL, 0, NULL),
(136, 5, 32, 'Intro to Public Safety and National Security', NULL, 0, NULL),
(137, 5, 32, 'Review of Intelligence Cycle', NULL, 0, NULL),
(138, 8, 33, 'Anti-Graft and Corrupt Practices Act (RA 3019)', NULL, 0, NULL),
(139, 8, 33, 'Code of Conduct and Ethical Standard for Government Officials and Employee (RA 6713)', NULL, 0, NULL),
(140, 8, 33, 'Community Environmental Policing', NULL, 0, NULL),
(141, 8, 33, 'Conflict Sensitive and Peace Promoting (CSPP) Paradigm', NULL, 0, NULL),
(142, 8, 33, 'Gender Awareness Development ll: Advocacy', NULL, 0, NULL),
(143, 8, 33, 'Human Rights in Emergency Situations and the Protection of persons in internal armed conflict', NULL, 0, NULL),
(144, 8, 33, 'Information Technology: A Managerial Overview', NULL, 0, NULL),
(145, 8, 34, 'Financial Unit Supervision', NULL, 0, NULL),
(146, 8, 34, 'Human Resource Management : A Review Based on Current Personnel Policies of the PNP', NULL, 0, NULL),
(147, 8, 34, 'Logistics Unit Supervision', NULL, 0, NULL),
(148, 8, 34, 'PNP Administrative Disciplinary Machinery', NULL, 0, NULL),
(149, 8, 34, 'Records Unit Supervision', NULL, 0, NULL),
(150, 8, 34, 'Small Unit Supervision', NULL, 0, NULL),
(151, 8, 35, 'Best Practices', NULL, 0, NULL),
(152, 8, 35, 'Communication: The Secret to Organizational Effectiveness', NULL, 0, NULL),
(153, 8, 35, 'Critical Thinking Analysis', NULL, 0, NULL),
(154, 8, 35, 'Effective Police Communication', NULL, 0, NULL),
(155, 8, 35, 'Frontline Supervisory Style', NULL, 0, NULL),
(156, 8, 35, 'Introduction to Completed Staff work', NULL, 0, NULL),
(157, 8, 35, 'Managing problem Emphasis', NULL, 0, NULL),
(158, 8, 35, 'Nature of Supervision', NULL, 0, NULL),
(159, 8, 35, 'Police Media Relations', NULL, 0, NULL),
(160, 8, 35, 'Role of Supervisor', NULL, 0, NULL),
(161, 8, 35, 'Supervisory Development', NULL, 0, NULL),
(162, 8, 36, 'Chain of Custody Rule', NULL, 0, NULL),
(163, 8, 36, 'Court Preparation and Testimony (Moot Court)', NULL, 0, NULL),
(164, 8, 36, 'Rules of Criminal Procedure (Rule 110-127)', NULL, 0, NULL),
(165, 8, 36, 'Rules of Evidence', NULL, 0, NULL),
(166, 8, 36, 'Special Laws', NULL, 0, NULL),
(167, 8, 36, 'Updates in Criminal Law with Case Study', NULL, 0, NULL),
(168, 8, 37, 'Agent Handling', NULL, 0, NULL),
(169, 8, 37, 'Casing and Surveillance Techniques', NULL, 0, NULL),
(170, 8, 37, 'Current Trends on Organized Crime and Criminal Groups in the Philippines', NULL, 0, NULL),
(171, 8, 37, 'First Responders Training', NULL, 0, NULL),
(172, 8, 37, 'Intelligence: Processing', NULL, 0, NULL),
(173, 8, 38, 'Basic Radio and Telephone Procedure, Operations and Preventive Maintenance', NULL, 0, NULL),
(174, 8, 38, 'EO 70 NTF ELCAC', NULL, 0, NULL),
(175, 8, 38, 'Financial Literacy', NULL, 0, NULL),
(176, 8, 38, 'RA 11188 and RA 9851', NULL, 0, NULL),
(177, 8, 38, 'Terrorist Incident First Response (TIFR)', NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_system_setting`
--

CREATE TABLE `tbl_system_setting` (
  `Setting_Key` varchar(64) NOT NULL,
  `Setting_Value` varchar(255) NOT NULL,
  `Updated_At` datetime DEFAULT NULL,
  `Updated_By` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_system_setting`
--

INSERT INTO `tbl_system_setting` (`Setting_Key`, `Setting_Value`, `Updated_At`, `Updated_By`) VALUES
('archive_retention_days', '730', '2026-07-31 14:12:43', 1),
('audit_log_retention_days', '730', '2026-07-31 14:12:43', 1),
('cheating_attempt_limit', '1', '2026-10-04 01:49:16', 1),
('idle_timeout_minutes', '5', '2026-10-04 01:49:16', 1),
('login_lockout_minutes', '10', '2026-10-04 01:49:16', 1),
('login_max_attempts', '5', '2026-10-04 01:49:16', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user`
--

CREATE TABLE `tbl_user` (
  `User_ID` int(11) NOT NULL,
  `Role_ID` int(11) NOT NULL,
  `Firstname` varchar(100) NOT NULL DEFAULT '',
  `Middlename` varchar(100) DEFAULT NULL,
  `Lastname` varchar(100) NOT NULL DEFAULT '',
  `Email` varchar(200) NOT NULL,
  `Password_Hash` varchar(255) NOT NULL,
  `Badge_Number` varchar(50) DEFAULT NULL,
  `Personnel_Rank` varchar(50) DEFAULT NULL,
  `Must_Change_Password` tinyint(1) NOT NULL DEFAULT 0,
  `Date_Created` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_user`
--

INSERT INTO `tbl_user` (`User_ID`, `Role_ID`, `Firstname`, `Middlename`, `Lastname`, `Email`, `Password_Hash`, `Badge_Number`, `Personnel_Rank`, `Must_Change_Password`, `Date_Created`, `Status`, `Is_Archived`, `Archived_At`) VALUES
(1, 1, 'Administrator', NULL, '', 'admin@rtc.com', '$2y$10$Ggd7QidefhYngDkCWGetGuP8gJ91kZRBJPKN/55ByWeYeNcS083NS', NULL, NULL, 0, '2026-07-04 04:23:40', 'Active', 0, NULL),
(2, 2, 'CCMD Officer', NULL, '', 'ccmd@rtc.com', '$2y$10$gnMmspWTigCvlHmQLptFBuGIWn8bK24zkxDHuS33NL414jXm/zuz.', NULL, NULL, 0, '2026-07-04 04:23:40', 'Active', 0, NULL),
(8, 3, 'Crisbell Ric V. Aboc', NULL, '', 'rivi.aboc.coc@phinmaed.com', '$2y$10$7bA5MrMjRj0yGJCd/KkCn.vRR5RbeL9i7fcIBZ5s4/TxNOtzbW7ry', '100000', 'PBGEN', 0, '2026-07-09 22:01:59', 'Active', 0, NULL),
(9, 3, 'Rhick Marguo F. Orina', NULL, '', 'rhfl.orina.coc@phinmaed.com', '$2y$10$YhJzmIR74W8SII4jlrFFuO6BIJYxU9sycRFRAZM96E4Zebcofleeu', '100001', 'PLTCOL', 0, '2026-07-09 22:03:49', 'Active', 0, NULL),
(10, 3, 'Edcel John P. Amper', NULL, '', 'edpa.amper.coc@phinmaed.com', '$2y$10$uN3e/clZMKVT.9X/zci57.vx/ish0QGl1J2a8WBATy6mZNm96CbXW', '100002', 'PAT', 0, '2026-07-09 22:05:02', 'Active', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user_batch`
--

CREATE TABLE `tbl_user_batch` (
  `User_Batch_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Batch_ID` int(11) NOT NULL,
  `Status` enum('Active','Completed','Inactive') DEFAULT 'Active',
  `Date_Enrolled` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_user_batch`
--

INSERT INTO `tbl_user_batch` (`User_Batch_ID`, `User_ID`, `Batch_ID`, `Status`, `Date_Enrolled`) VALUES
(310, 8, 88, 'Active', '2026-10-13 10:52:22'),
(311, 10, 88, 'Active', '2026-10-13 10:52:22'),
(312, 9, 88, 'Active', '2026-10-13 10:52:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_academic_section`
--
ALTER TABLE `tbl_academic_section`
  ADD PRIMARY KEY (`Section_ID`),
  ADD KEY `idx_section_batch` (`Batch_ID`);

--
-- Indexes for table `tbl_answer`
--
ALTER TABLE `tbl_answer`
  ADD PRIMARY KEY (`Answer_ID`),
  ADD KEY `idx_answer_result` (`Result_ID`),
  ADD KEY `idx_answer_session` (`Session_ID`),
  ADD KEY `idx_answer_question` (`Question_ID`),
  ADD KEY `idx_answer_choice` (`Choice_ID`);

--
-- Indexes for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  ADD PRIMARY KEY (`Log_ID`),
  ADD KEY `idx_audit_user` (`User_ID`),
  ADD KEY `idx_audit_timestamp` (`Timestamp`),
  ADD KEY `idx_audit_module` (`Module`),
  ADD KEY `idx_audit_action` (`Action`),
  ADD KEY `idx_audit_status` (`Status`);

--
-- Indexes for table `tbl_batch`
--
ALTER TABLE `tbl_batch`
  ADD PRIMARY KEY (`Batch_ID`),
  ADD KEY `idx_batch_course` (`Course_ID`),
  ADD KEY `idx_batch_section` (`Section_ID`);

--
-- Indexes for table `tbl_cheating_incident`
--
ALTER TABLE `tbl_cheating_incident`
  ADD PRIMARY KEY (`Incident_ID`),
  ADD KEY `idx_cinc_session` (`Session_ID`);

--
-- Indexes for table `tbl_cheating_log`
--
ALTER TABLE `tbl_cheating_log`
  ADD PRIMARY KEY (`Cheating_Log_ID`),
  ADD KEY `idx_clog_session` (`Session_ID`);

--
-- Indexes for table `tbl_choice`
--
ALTER TABLE `tbl_choice`
  ADD PRIMARY KEY (`Choice_ID`),
  ADD KEY `idx_choice_question` (`Question_ID`);

--
-- Indexes for table `tbl_course`
--
ALTER TABLE `tbl_course`
  ADD PRIMARY KEY (`Course_ID`),
  ADD KEY `idx_course_user` (`User_ID`);

--
-- Indexes for table `tbl_exam`
--
ALTER TABLE `tbl_exam`
  ADD PRIMARY KEY (`Exam_ID`),
  ADD KEY `idx_exam_user` (`User_ID`),
  ADD KEY `idx_exam_subject` (`Subject_ID`);

--
-- Indexes for table `tbl_exam_batch`
--
ALTER TABLE `tbl_exam_batch`
  ADD PRIMARY KEY (`Exam_Batch_ID`),
  ADD UNIQUE KEY `uq_exam_batch` (`Exam_ID`,`Batch_ID`),
  ADD KEY `idx_exam_batch_batch` (`Batch_ID`);

--
-- Indexes for table `tbl_exam_code_attempt`
--
ALTER TABLE `tbl_exam_code_attempt`
  ADD PRIMARY KEY (`Attempt_ID`),
  ADD KEY `idx_attempt_lookup` (`Exam_ID`,`User_ID`,`Attempted_At`),
  ADD KEY `fk_code_attempt_user` (`User_ID`);

--
-- Indexes for table `tbl_exam_participant`
--
ALTER TABLE `tbl_exam_participant`
  ADD PRIMARY KEY (`Exam_Participant_ID`),
  ADD UNIQUE KEY `uq_exam_participant` (`Exam_ID`,`User_ID`),
  ADD KEY `idx_exam_participant_user` (`User_ID`);

--
-- Indexes for table `tbl_exam_question`
--
ALTER TABLE `tbl_exam_question`
  ADD PRIMARY KEY (`Exam_Question_ID`),
  ADD UNIQUE KEY `uq_exam_question` (`Exam_ID`,`Question_ID`),
  ADD KEY `idx_exam_question_order` (`Exam_ID`,`Question_Order`),
  ADD KEY `fk_eq_question` (`Question_ID`);

--
-- Indexes for table `tbl_exam_session`
--
ALTER TABLE `tbl_exam_session`
  ADD PRIMARY KEY (`Session_ID`),
  ADD KEY `idx_session_exam` (`Exam_ID`),
  ADD KEY `idx_session_user` (`User_ID`),
  ADD KEY `idx_session_unlocked_by` (`Cheating_Unlocked_By`);

--
-- Indexes for table `tbl_login_attempt`
--
ALTER TABLE `tbl_login_attempt`
  ADD PRIMARY KEY (`Attempt_ID`),
  ADD UNIQUE KEY `uq_login_attempt_scope` (`Scope`,`Scope_Value`),
  ADD KEY `idx_login_attempt_locked` (`Locked_Until`),
  ADD KEY `fk_login_attempt_user` (`User_ID`);

--
-- Indexes for table `tbl_module`
--
ALTER TABLE `tbl_module`
  ADD PRIMARY KEY (`Module_ID`),
  ADD KEY `idx_module_course` (`Course_ID`);

--
-- Indexes for table `tbl_question_bank`
--
ALTER TABLE `tbl_question_bank`
  ADD PRIMARY KEY (`Question_ID`),
  ADD KEY `idx_qbank_subject` (`Subject_ID`),
  ADD KEY `idx_qbank_user` (`User_ID`);

--
-- Indexes for table `tbl_result`
--
ALTER TABLE `tbl_result`
  ADD PRIMARY KEY (`Result_ID`),
  ADD UNIQUE KEY `uq_result_session` (`Session_ID`);

--
-- Indexes for table `tbl_role`
--
ALTER TABLE `tbl_role`
  ADD PRIMARY KEY (`Role_ID`),
  ADD UNIQUE KEY `uq_role_name` (`Role_Name`);

--
-- Indexes for table `tbl_subject`
--
ALTER TABLE `tbl_subject`
  ADD PRIMARY KEY (`Subject_ID`),
  ADD KEY `idx_subject_course` (`Course_ID`),
  ADD KEY `idx_subject_module` (`Module_ID`);

--
-- Indexes for table `tbl_system_setting`
--
ALTER TABLE `tbl_system_setting`
  ADD PRIMARY KEY (`Setting_Key`),
  ADD KEY `fk_setting_updated_by` (`Updated_By`);

--
-- Indexes for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `uq_user_email` (`Email`),
  ADD UNIQUE KEY `uq_user_badge_number` (`Badge_Number`),
  ADD KEY `fk_user_role` (`Role_ID`);

--
-- Indexes for table `tbl_user_batch`
--
ALTER TABLE `tbl_user_batch`
  ADD PRIMARY KEY (`User_Batch_ID`),
  ADD UNIQUE KEY `uq_user_batch` (`User_ID`,`Batch_ID`),
  ADD KEY `fk_user_batch_batch` (`Batch_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_academic_section`
--
ALTER TABLE `tbl_academic_section`
  MODIFY `Section_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `tbl_answer`
--
ALTER TABLE `tbl_answer`
  MODIFY `Answer_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4862;

--
-- AUTO_INCREMENT for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3744;

--
-- AUTO_INCREMENT for table `tbl_batch`
--
ALTER TABLE `tbl_batch`
  MODIFY `Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `tbl_cheating_incident`
--
ALTER TABLE `tbl_cheating_incident`
  MODIFY `Incident_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `tbl_cheating_log`
--
ALTER TABLE `tbl_cheating_log`
  MODIFY `Cheating_Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=780;

--
-- AUTO_INCREMENT for table `tbl_choice`
--
ALTER TABLE `tbl_choice`
  MODIFY `Choice_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1156;

--
-- AUTO_INCREMENT for table `tbl_course`
--
ALTER TABLE `tbl_course`
  MODIFY `Course_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tbl_exam`
--
ALTER TABLE `tbl_exam`
  MODIFY `Exam_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `tbl_exam_batch`
--
ALTER TABLE `tbl_exam_batch`
  MODIFY `Exam_Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- AUTO_INCREMENT for table `tbl_exam_code_attempt`
--
ALTER TABLE `tbl_exam_code_attempt`
  MODIFY `Attempt_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=231;

--
-- AUTO_INCREMENT for table `tbl_exam_participant`
--
ALTER TABLE `tbl_exam_participant`
  MODIFY `Exam_Participant_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=918;

--
-- AUTO_INCREMENT for table `tbl_exam_question`
--
ALTER TABLE `tbl_exam_question`
  MODIFY `Exam_Question_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1267;

--
-- AUTO_INCREMENT for table `tbl_exam_session`
--
ALTER TABLE `tbl_exam_session`
  MODIFY `Session_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=307;

--
-- AUTO_INCREMENT for table `tbl_login_attempt`
--
ALTER TABLE `tbl_login_attempt`
  MODIFY `Attempt_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `tbl_module`
--
ALTER TABLE `tbl_module`
  MODIFY `Module_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `tbl_question_bank`
--
ALTER TABLE `tbl_question_bank`
  MODIFY `Question_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=243;

--
-- AUTO_INCREMENT for table `tbl_result`
--
ALTER TABLE `tbl_result`
  MODIFY `Result_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=298;

--
-- AUTO_INCREMENT for table `tbl_role`
--
ALTER TABLE `tbl_role`
  MODIFY `Role_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_subject`
--
ALTER TABLE `tbl_subject`
  MODIFY `Subject_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=317;

--
-- AUTO_INCREMENT for table `tbl_user_batch`
--
ALTER TABLE `tbl_user_batch`
  MODIFY `User_Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=315;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_academic_section`
--
ALTER TABLE `tbl_academic_section`
  ADD CONSTRAINT `fk_section_batch` FOREIGN KEY (`Batch_ID`) REFERENCES `tbl_batch` (`Batch_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_answer`
--
ALTER TABLE `tbl_answer`
  ADD CONSTRAINT `fk_answer_choice` FOREIGN KEY (`Choice_ID`) REFERENCES `tbl_choice` (`Choice_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_answer_question` FOREIGN KEY (`Question_ID`) REFERENCES `tbl_question_bank` (`Question_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_answer_result` FOREIGN KEY (`Result_ID`) REFERENCES `tbl_result` (`Result_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_answer_session` FOREIGN KEY (`Session_ID`) REFERENCES `tbl_exam_session` (`Session_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_batch`
--
ALTER TABLE `tbl_batch`
  ADD CONSTRAINT `fk_batch_course` FOREIGN KEY (`Course_ID`) REFERENCES `tbl_course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_batch_section` FOREIGN KEY (`Section_ID`) REFERENCES `tbl_academic_section` (`Section_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_cheating_incident`
--
ALTER TABLE `tbl_cheating_incident`
  ADD CONSTRAINT `fk_cinc_session` FOREIGN KEY (`Session_ID`) REFERENCES `tbl_exam_session` (`Session_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_cheating_log`
--
ALTER TABLE `tbl_cheating_log`
  ADD CONSTRAINT `fk_clog_session` FOREIGN KEY (`Session_ID`) REFERENCES `tbl_exam_session` (`Session_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_choice`
--
ALTER TABLE `tbl_choice`
  ADD CONSTRAINT `fk_choice_question` FOREIGN KEY (`Question_ID`) REFERENCES `tbl_question_bank` (`Question_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_course`
--
ALTER TABLE `tbl_course`
  ADD CONSTRAINT `fk_course_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_exam`
--
ALTER TABLE `tbl_exam`
  ADD CONSTRAINT `fk_exam_subject` FOREIGN KEY (`Subject_ID`) REFERENCES `tbl_subject` (`Subject_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exam_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_exam_batch`
--
ALTER TABLE `tbl_exam_batch`
  ADD CONSTRAINT `fk_eb_batch` FOREIGN KEY (`Batch_ID`) REFERENCES `tbl_batch` (`Batch_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eb_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam` (`Exam_ID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_exam_code_attempt`
--
ALTER TABLE `tbl_exam_code_attempt`
  ADD CONSTRAINT `fk_code_attempt_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam` (`Exam_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_code_attempt_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_exam_participant`
--
ALTER TABLE `tbl_exam_participant`
  ADD CONSTRAINT `fk_exam_participant_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam` (`Exam_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exam_participant_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_exam_question`
--
ALTER TABLE `tbl_exam_question`
  ADD CONSTRAINT `fk_eq_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam` (`Exam_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eq_question` FOREIGN KEY (`Question_ID`) REFERENCES `tbl_question_bank` (`Question_ID`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_exam_session`
--
ALTER TABLE `tbl_exam_session`
  ADD CONSTRAINT `fk_session_exam` FOREIGN KEY (`Exam_ID`) REFERENCES `tbl_exam` (`Exam_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_session_unlocked_by` FOREIGN KEY (`Cheating_Unlocked_By`) REFERENCES `tbl_user` (`User_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_login_attempt`
--
ALTER TABLE `tbl_login_attempt`
  ADD CONSTRAINT `fk_login_attempt_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_module`
--
ALTER TABLE `tbl_module`
  ADD CONSTRAINT `fk_module_course` FOREIGN KEY (`Course_ID`) REFERENCES `tbl_course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_question_bank`
--
ALTER TABLE `tbl_question_bank`
  ADD CONSTRAINT `fk_qbank_subject` FOREIGN KEY (`Subject_ID`) REFERENCES `tbl_subject` (`Subject_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_qbank_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_result`
--
ALTER TABLE `tbl_result`
  ADD CONSTRAINT `fk_result_session` FOREIGN KEY (`Session_ID`) REFERENCES `tbl_exam_session` (`Session_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_subject`
--
ALTER TABLE `tbl_subject`
  ADD CONSTRAINT `fk_subject_course` FOREIGN KEY (`Course_ID`) REFERENCES `tbl_course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subject_module` FOREIGN KEY (`Module_ID`) REFERENCES `tbl_module` (`Module_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_system_setting`
--
ALTER TABLE `tbl_system_setting`
  ADD CONSTRAINT `fk_setting_updated_by_new` FOREIGN KEY (`Updated_By`) REFERENCES `tbl_user` (`User_ID`);

--
-- Constraints for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`Role_ID`) REFERENCES `tbl_role` (`Role_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_user_batch`
--
ALTER TABLE `tbl_user_batch`
  ADD CONSTRAINT `fk_user_batch_batch` FOREIGN KEY (`Batch_ID`) REFERENCES `tbl_batch` (`Batch_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_batch_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
