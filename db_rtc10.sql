-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 08, 2026 at 03:04 PM
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
  `Capacity` int(11) NOT NULL DEFAULT 40
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_academic_section`
--

INSERT INTO `tbl_academic_section` (`Section_ID`, `Batch_ID`, `Section_Name`, `Capacity`) VALUES
(3, 5, 'Alpha', 40),
(4, 5, 'Bravo', 40),
(5, 8, 'Alpha', 40),
(6, 8, 'Bravo', 40),
(9, 14, 'Alpha', 40),
(10, 14, 'Bravo', 40),
(15, 4, 'Alpha', 40),
(18, 4, 'Sample', 40);

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
(36, 5, 5, 10, 39, NULL, 0),
(37, 5, 5, 7, 28, NULL, 0),
(38, 5, 5, 6, 24, NULL, 0),
(39, 5, 5, 5, 19, NULL, 1),
(40, 5, 5, 1, NULL, NULL, 0),
(41, 5, 5, 3, 12, NULL, 0),
(42, 5, 5, 9, 34, NULL, 1),
(43, 5, 5, 8, 32, NULL, 0),
(44, 5, 5, 4, 15, NULL, 0),
(45, 5, 5, 2, 8, NULL, 0),
(46, 6, 6, 4, 14, NULL, 1),
(47, 6, 6, 3, 11, NULL, 1),
(48, 6, 6, 5, 19, NULL, 1),
(49, 6, 6, 2, 7, NULL, 1),
(50, 6, 6, 1, NULL, NULL, 1),
(51, 7, 7, 2, 7, NULL, 1),
(52, 7, 7, 3, 10, NULL, 0),
(53, 7, 7, 1, NULL, NULL, 0),
(54, 8, 8, 2, 6, NULL, 0),
(55, 8, 8, 3, 11, NULL, 1),
(56, 9, 9, 3, 11, NULL, 1),
(57, 9, 9, 1, NULL, NULL, 1),
(58, 9, 9, 2, 7, NULL, 1);

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
  `Entity_Type` varchar(50) DEFAULT NULL,
  `Entity_ID` int(11) DEFAULT NULL,
  `IP_Address` varchar(45) DEFAULT NULL,
  `User_Role` varchar(50) DEFAULT NULL,
  `Timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_audit_log`
--

INSERT INTO `tbl_audit_log` (`Log_ID`, `User_ID`, `Action`, `Module`, `Outcome`, `Status`, `Entity_Type`, `Entity_ID`, `IP_Address`, `User_Role`, `Timestamp`) VALUES
(1, 1, 'CLOSE_EXAM', NULL, 'Exam 3 closed', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:12:27'),
(2, 1, 'RESET_EXAM_CODE', NULL, 'Exam 3 code reset', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:12:51'),
(3, 1, 'RESET_EXAM_CODE', NULL, 'Exam 2 code reset', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:13:04'),
(4, 1, 'RESET_EXAM_CODE', NULL, 'Exam 4 code reset', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:13:13'),
(5, 1, 'RESET_EXAM_CODE', NULL, 'Exam 1 code reset', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:13:21'),
(6, 1, 'ARCHIVE_EXAM', NULL, 'Exam 3 archived', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:13:58'),
(7, 1, 'CREATE_EXAM', NULL, 'Exam 5 created', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:18:22'),
(8, 1, 'CREATE_EXAM', NULL, 'Exam 6 created', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:25:54'),
(9, 1, 'TOGGLE_RESPONSE_REVIEW', NULL, 'Exam 5 response review enabled', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:43:19'),
(10, 1, 'TOGGLE_RESPONSE_REVIEW', NULL, 'Exam 5 response review disabled', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:43:32'),
(11, 1, 'TOGGLE_RESPONSE_REVIEW', NULL, 'Exam 5 response review enabled', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:44:48'),
(12, 1, 'TOGGLE_RESPONSE_REVIEW', NULL, 'Exam 6 response review enabled', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 22:44:52'),
(13, 1, 'VIEW_EXAM_RESPONSE', NULL, 'Exam 5 result 7 viewed by admin', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 23:14:50'),
(14, 1, 'VIEW_EXAM_RESPONSE', NULL, 'Exam 6 result 9 viewed by admin', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 23:20:47'),
(15, 1, 'VIEW_EXAM_RESPONSE', NULL, 'Exam 5 result 7 viewed by admin', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-07 23:26:32'),
(16, 1, 'VIEW_EXAM_RESPONSE', NULL, 'Exam 5 result 7 viewed by admin', 'SUCCESS', NULL, NULL, NULL, NULL, '2026-07-08 00:34:59'),
(17, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 02:09:35'),
(18, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 02:20:44'),
(19, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 02:24:39'),
(20, 4, 'LOGIN', 'AUTH', 'User Crisbell Ric V. Aboc logged in', 'SUCCESS', 'user', 4, '::1', 'Examinee', '2026-07-08 02:24:52'),
(21, 4, 'LOGOUT', 'AUTH', 'User Crisbell Ric V. Aboc logged out', 'SUCCESS', 'user', 4, '::1', 'Examinee', '2026-07-08 02:24:59'),
(22, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 02:25:21'),
(23, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 02:32:47'),
(24, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 07:04:07'),
(25, 1, 'DELETE_QUESTION', 'QUESTION', 'Question 11 deleted', 'SUCCESS', 'question', 11, '::1', 'Admin', '2026-07-08 07:08:19'),
(26, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 07:10:34'),
(27, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 07:11:06'),
(28, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 07:11:13'),
(29, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 07:11:43'),
(30, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 07:11:52'),
(31, 4, 'LOGIN', 'AUTH', 'User Crisbell Ric V. Aboc logged in', 'SUCCESS', 'user', 4, '::1', 'Examinee', '2026-07-08 07:12:02'),
(32, 4, 'LOGOUT', 'AUTH', 'User Crisbell Ric V. Aboc logged out', 'SUCCESS', 'user', 4, '::1', 'Examinee', '2026-07-08 07:13:14'),
(33, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 19:24:59'),
(34, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 19:25:06'),
(35, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 20:11:50'),
(36, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-08 20:12:14'),
(37, 4, 'LOGIN', 'AUTH', 'User Crisbell Ric V. Aboc logged in', 'SUCCESS', 'user', 4, '::1', 'Examinee', '2026-07-08 20:12:35'),
(38, 4, 'LOGOUT', 'AUTH', 'User Crisbell Ric V. Aboc logged out', 'SUCCESS', 'user', 4, '::1', 'Examinee', '2026-07-08 20:12:42');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_batch`
--

CREATE TABLE `tbl_batch` (
  `Batch_ID` int(11) NOT NULL,
  `Course_ID` int(11) NOT NULL,
  `Section_ID` int(11) DEFAULT NULL,
  `User_ID` int(11) DEFAULT NULL,
  `Batch_Name` varchar(150) NOT NULL,
  `Date_Started` date DEFAULT NULL,
  `Date_Ended` date DEFAULT NULL,
  `Status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `Date_Enrolled` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_batch`
--

INSERT INTO `tbl_batch` (`Batch_ID`, `Course_ID`, `Section_ID`, `User_ID`, `Batch_Name`, `Date_Started`, `Date_Ended`, `Status`, `Date_Enrolled`) VALUES
(4, 1, NULL, 7, 'PSBRC 2026', '2026-07-04', '2027-01-04', 'Active', '2026-07-07 16:11:03'),
(5, 2, NULL, NULL, 'Default Batch', NULL, NULL, 'Active', '2026-07-04 14:40:12'),
(8, 3, NULL, NULL, 'Default Batch', NULL, NULL, 'Active', '2026-07-04 14:41:49'),
(9, 3, 5, NULL, 'PSLLC 2026', '2026-07-04', '2027-01-04', 'Active', '2026-07-04 14:42:33'),
(10, 3, 6, NULL, 'PSLLC 2026', '2026-07-04', '2027-01-04', 'Active', '2026-07-04 14:42:58'),
(11, 2, 3, NULL, 'PSJLC 2026', '2026-07-04', '2026-10-04', 'Active', '2026-07-04 14:43:32'),
(12, 2, 4, NULL, 'PSJLC 2026', '2026-07-04', '2026-10-04', 'Active', '2026-07-04 14:44:00'),
(14, 5, NULL, NULL, 'Default Batch', NULL, NULL, 'Active', '2026-07-04 14:45:21'),
(15, 5, 9, NULL, 'PSOCC 2026', '2026-07-04', '2027-01-04', 'Active', '2026-07-04 14:45:51'),
(16, 5, 10, NULL, 'PSOCC 2026', '2026-07-04', '2027-01-04', 'Active', '2026-07-04 14:46:11'),
(22, 1, 15, NULL, 'Alpha Enrollment', NULL, NULL, 'Active', '2026-07-07 20:47:08'),
(25, 1, 18, NULL, 'Sample Enrollment', NULL, NULL, 'Active', '2026-07-07 22:17:16'),
(26, 1, NULL, NULL, 'Charlie Enrollment', NULL, NULL, 'Active', '2026-07-08 02:17:01');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cheating_incident`
--

CREATE TABLE `tbl_cheating_incident` (
  `Incident_ID` int(11) NOT NULL,
  `Session_ID` int(11) NOT NULL,
  `Reason` text NOT NULL,
  `Timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Open','Reviewed','Dismissed','Escalated') NOT NULL DEFAULT 'Open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(5, 2, 'The higher-ranking person', 0),
(6, 2, 'The younger person', 0),
(7, 2, 'The lower-ranking person to the higher-ranking person', 1),
(8, 2, 'The person who arrived first', 0),
(9, 3, 'Interrupting conversations', 0),
(10, 3, 'Using your mobile phone during speeches', 0),
(11, 3, 'Greeting guests politely and respectfully', 1),
(12, 3, 'Speaking loudly', 0),
(13, 4, 'Continue walking', 0),
(14, 4, 'Stand at attention and render the proper salute', 1),
(15, 4, 'Sit quietly', 0),
(16, 4, 'Clap after the anthem', 0),
(17, 5, 'By first name', 0),
(18, 5, 'By nickname', 0),
(19, 5, 'By rank and surname', 1),
(20, 5, 'By last name only', 0),
(21, 6, 'Talking while chewing', 0),
(22, 6, 'Waiting for everyone to be served before eating', 1),
(23, 6, 'Placing elbows on the table while eating', 0),
(24, 6, 'Using your phone throughout the meal', 0),
(25, 7, 'Informal language', 0),
(26, 7, 'Proper salutations and professional tone', 1),
(27, 7, 'Slang expressions', 0),
(28, 7, 'Abbreviations only', 0),
(29, 8, 'Ignore the arrival', 0),
(30, 8, 'Continue conversations', 0),
(31, 8, 'Stand and acknowledge the guest respectfully', 1),
(32, 8, 'Leave the room', 0),
(33, 9, 'Gain personal fame', 0),
(34, 9, 'Build positive relationships and public trust', 1),
(35, 9, 'Avoid responsibilities', 0),
(36, 9, 'Increase social media followers', 0),
(37, 10, 'Being punctual, courteous, and respectful', 1),
(38, 10, 'Arguing with clients', 0),
(39, 10, 'Ignoring complaints', 0),
(40, 10, 'Showing favoritism', 0),
(41, 1, 'To entertain guests', 0),
(42, 1, 'To maintain order, respect, and professionalism', 1),
(43, 1, 'To shorten the program', 0),
(44, 1, 'To impress the audience', 0),
(45, 1, 'hatdog', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_course`
--

CREATE TABLE `tbl_course` (
  `Course_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL COMMENT 'Creator',
  `Course_Name` varchar(200) NOT NULL,
  `Details` text DEFAULT NULL,
  `Duration` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_course`
--

INSERT INTO `tbl_course` (`Course_ID`, `User_ID`, `Course_Name`, `Details`, `Duration`) VALUES
(1, 1, 'Public Safety Basic Recruit Course (PSBRC)', 'The Public Safety Basic Recruit Course (PSBRC) is a mandatory, 6-month intensive pre-service training program for newly appointed police non-commissioned officers (like Patrolmen). Administered by the Philippine Public Safety College (PPSC) through the National Police Training Institute (NPTI), it transitions recruits from a civilian mindset to a regimented, highly disciplined police service.', '6 months'),
(2, 1, 'Public Safety Junior Leadership Course (PSJLC)', 'The Public Safety Junior Leadership Course (PSJLC) is a mandatory 10 to 11-week career program for junior Police Non-Commissioned Officers (PNCOs) in the Philippine National Police (PNP). It is designed to equip officers with the fundamental management, leadership, and operational skills required to handle junior key positions and team-building assignments.', '3 months'),
(3, 1, 'Public Safety Senior Leadership Course (PSSLC)', 'The Public Safety Senior Leadership Course (PSSLC) is a mandatory 3.5-month career course for senior non-commissioned officers (SPO3 to SPO4) in the Philippine National Police (PNP). It equips officers with the management and leadership capabilities required for assuming mid-level supervisory and administrative responsibilities.', '6 months'),
(5, 1, 'Public Safety Officers Candidate Course (PSOCC)', 'The Public Safety Officers Candidate Course (PSOCC) is a mandatory 14-week career training program for Police Non-Commissioned Officers (PNCOs) within the Philippine National Police (PNP) who are aspiring for promotion to the rank of Police Inspector. Administered by the Philippine Public Safety College (PPSC), the course is designed to equip junior officers with the fundamental leadership skills, specialized operational knowledge, and values necessary for higher management and team-building responsibilities.', '6 months');

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
  `Time_Limit` int(11) DEFAULT NULL COMMENT 'Per-question time limit in seconds',
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

INSERT INTO `tbl_exam` (`Exam_ID`, `User_ID`, `Subject_ID`, `Title`, `Description`, `Schedule_Date`, `Deadline`, `Duration`, `Passing_Score`, `Status`, `Is_Randomized`, `Time_Limit`, `Exam_Code`, `Exam_Code_Generated_At`, `Exam_Code_Reset_Count`, `Is_Archived`, `Archived_At`, `Allow_Response_Review`) VALUES
(1, 1, 1, 'Pre-Test', 'Pre-Test ', '2026-07-04 14:57:00', '2026-07-08 22:57:00', 60, 50.00, 'Published', 1, NULL, 'KJSFKR', '2026-07-07 22:13:21', 1, 0, NULL, 0),
(2, 1, 1, 'Modular', '', '2026-07-04 19:14:00', '2026-07-08 03:14:00', 30, 50.00, 'Closed', 0, NULL, '6UT44B', '2026-07-07 22:13:04', 1, 0, NULL, 0),
(3, 1, 1, 'Exam', '', '2026-07-07 16:27:00', '2026-07-08 08:27:00', 30, 50.00, 'Closed', 1, NULL, 'K64VEG', '2026-07-07 22:12:51', 1, 1, '2026-07-07 22:13:58', 0),
(4, 1, 1, 'Modular', '', '2026-07-07 17:20:00', '2026-07-08 17:20:00', 10, 50.00, 'Closed', 1, NULL, 'J7C7ZQ', '2026-07-07 22:13:13', 1, 0, NULL, 0),
(5, 1, 3, 'Exam', '', '2026-07-07 22:18:00', '2026-07-08 22:18:00', 30, 50.00, 'Published', 1, NULL, 'NGT3D9', '2026-07-07 22:18:22', 0, 0, NULL, 1),
(6, 1, 3, 'Modular', '', '2026-07-07 22:25:00', '2026-07-08 22:25:00', 1, 50.00, 'Published', 1, NULL, 'YWMRCP', '2026-07-07 22:25:54', 0, 0, NULL, 1);

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
(7, 1, 4, '2026-07-04 17:16:38'),
(12, 2, 4, '2026-07-04 19:15:28'),
(14, 3, 4, '2026-07-07 16:29:42'),
(18, 4, 4, '2026-07-07 17:22:17'),
(24, 6, 25, '2026-07-07 22:25:54'),
(32, 5, 25, '2026-07-08 01:55:22');

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
(1, 5, 4, '::1', 1, '2026-07-07 22:23:01'),
(3, 6, 4, '::1', 1, '2026-07-07 22:27:42'),
(4, 6, 7, '::1', 1, '2026-07-07 23:19:29');

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
(31, 1, 1, 1, '2026-07-04 17:16:38'),
(32, 1, 2, 2, '2026-07-04 17:16:38'),
(33, 1, 3, 3, '2026-07-04 17:16:38'),
(34, 1, 4, 4, '2026-07-04 17:16:38'),
(35, 1, 5, 5, '2026-07-04 17:16:38'),
(36, 1, 6, 6, '2026-07-04 17:16:38'),
(37, 1, 7, 7, '2026-07-04 17:16:38'),
(38, 1, 8, 8, '2026-07-04 17:16:38'),
(39, 1, 9, 9, '2026-07-04 17:16:38'),
(40, 1, 10, 10, '2026-07-04 17:16:38'),
(41, 2, 1, 1, '2026-07-04 19:15:28'),
(42, 2, 2, 2, '2026-07-04 19:15:28'),
(43, 2, 3, 3, '2026-07-04 19:15:28'),
(44, 2, 4, 4, '2026-07-04 19:15:28'),
(45, 2, 5, 5, '2026-07-04 19:15:28'),
(56, 3, 1, 1, '2026-07-07 16:29:42'),
(57, 3, 2, 2, '2026-07-07 16:29:42'),
(58, 3, 3, 3, '2026-07-07 16:29:42'),
(59, 3, 4, 4, '2026-07-07 16:29:42'),
(60, 3, 5, 5, '2026-07-07 16:29:42'),
(61, 3, 6, 6, '2026-07-07 16:29:42'),
(62, 3, 7, 7, '2026-07-07 16:29:42'),
(63, 3, 8, 8, '2026-07-07 16:29:42'),
(64, 3, 9, 9, '2026-07-07 16:29:42'),
(65, 3, 10, 10, '2026-07-07 16:29:42'),
(71, 4, 1, 1, '2026-07-07 17:22:17'),
(72, 4, 2, 2, '2026-07-07 17:22:17'),
(73, 4, 3, 3, '2026-07-07 17:22:17'),
(74, 4, 4, 4, '2026-07-07 17:22:17'),
(75, 4, 5, 5, '2026-07-07 17:22:17'),
(85, 6, 1, 1, '2026-07-07 22:25:54'),
(86, 6, 2, 2, '2026-07-07 22:25:54'),
(87, 6, 3, 3, '2026-07-07 22:25:54'),
(109, 5, 1, 1, '2026-07-08 01:55:22'),
(110, 5, 2, 2, '2026-07-08 01:55:22'),
(111, 5, 3, 3, '2026-07-08 01:55:22');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam_session`
--

CREATE TABLE `tbl_exam_session` (
  `Session_ID` int(11) NOT NULL,
  `Exam_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL COMMENT 'Student taking the exam',
  `Time_Started` datetime NOT NULL DEFAULT current_timestamp(),
  `Time_Ended` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_exam_session`
--

INSERT INTO `tbl_exam_session` (`Session_ID`, `Exam_ID`, `User_ID`, `Time_Started`, `Time_Ended`) VALUES
(5, 3, 4, '2026-07-07 16:30:01', '2026-07-07 16:31:04'),
(6, 4, 4, '2026-07-07 17:22:38', '2026-07-07 17:23:06'),
(7, 5, 4, '2026-07-07 22:23:02', '2026-07-07 22:23:18'),
(8, 6, 4, '2026-07-07 22:27:42', '2026-07-07 22:28:44'),
(9, 6, 7, '2026-07-07 23:19:29', '2026-07-07 23:19:52');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_notification`
--

CREATE TABLE `tbl_notification` (
  `Notification_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Message` text NOT NULL,
  `Type` varchar(80) NOT NULL,
  `Date_Sent` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Sent','Failed') NOT NULL DEFAULT 'Sent',
  `Target_Role` varchar(100) DEFAULT NULL,
  `Is_Read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_permission`
--

CREATE TABLE `tbl_permission` (
  `Permission_ID` int(11) NOT NULL,
  `Permission_Name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `Added_Date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_question_bank`
--

INSERT INTO `tbl_question_bank` (`Question_ID`, `Subject_ID`, `User_ID`, `Question_Text`, `Question_Type`, `Added_Date`) VALUES
(1, 1, 1, 'What is the primary purpose of protocol during official ceremonies?', 'Multiple Choice', '2026-07-04 15:21:13'),
(2, 1, 1, 'When introducing two people, who should generally be introduced first?', 'Multiple Choice', '2026-07-04 15:21:13'),
(3, 1, 1, 'Which of the following is considered proper social etiquette during formal events?', 'Multiple Choice', '2026-07-04 15:21:13'),
(4, 1, 1, 'During the Philippine National Anthem, a uniformed police officer should:', 'Multiple Choice', '2026-07-04 15:21:13'),
(5, 1, 1, 'Which is the most appropriate way to address a senior officer?', 'Multiple Choice', '2026-07-04 15:21:13'),
(6, 1, 1, 'Which of the following demonstrates good table manners at a formal dinner?', 'Multiple Choice', '2026-07-04 15:21:13'),
(7, 1, 1, 'In official correspondence, courtesy and respect are shown through:', 'Multiple Choice', '2026-07-04 15:21:13'),
(8, 1, 1, 'What is the proper action when a guest of honor enters the venue?', 'Multiple Choice', '2026-07-04 15:21:13'),
(9, 1, 1, 'Social amenities primarily help an officer to:', 'Multiple Choice', '2026-07-04 15:21:13'),
(10, 1, 1, 'Which of the following best reflects professionalism in public service?', 'Multiple Choice', '2026-07-04 15:21:13');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_report`
--

CREATE TABLE `tbl_report` (
  `Report_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Report_Type` varchar(100) NOT NULL,
  `Generated_By` varchar(200) NOT NULL,
  `Generated_On` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(5, 5, 2.00, 20.00, 'Failed', '2026-07-07 16:31:04'),
(6, 6, 5.00, 100.00, 'Passed', '2026-07-07 17:23:06'),
(7, 7, 1.00, 33.33, 'Failed', '2026-07-07 22:23:18'),
(8, 8, 1.00, 33.33, 'Failed', '2026-07-07 22:28:44'),
(9, 9, 3.00, 100.00, 'Passed', '2026-07-07 23:19:52');

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
-- Table structure for table `tbl_role_permission`
--

CREATE TABLE `tbl_role_permission` (
  `Role_ID` int(11) NOT NULL,
  `Permission_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subject`
--

CREATE TABLE `tbl_subject` (
  `Subject_ID` int(11) NOT NULL,
  `Course_ID` int(11) NOT NULL,
  `Subject_Name` varchar(200) NOT NULL,
  `Subject_Code` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_subject`
--

INSERT INTO `tbl_subject` (`Subject_ID`, `Course_ID`, `Subject_Name`, `Subject_Code`, `Description`) VALUES
(1, 1, 'Protocol and Social Amenities', 'PSA101', ''),
(2, 1, 'Human Rights: A Review with Case Based Approach', 'HR101', ''),
(3, 1, 'Protocol and Social Amenities', 'PSA102', '');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user`
--

CREATE TABLE `tbl_user` (
  `User_ID` int(11) NOT NULL,
  `Role_ID` int(11) NOT NULL,
  `Fullname` varchar(200) NOT NULL,
  `Email` varchar(200) NOT NULL,
  `Username` varchar(100) NOT NULL,
  `Password_Hash` varchar(255) NOT NULL,
  `Academic_Number` varchar(50) DEFAULT NULL,
  `Personnel_Rank` varchar(50) DEFAULT NULL,
  `Must_Change_Password` tinyint(1) NOT NULL DEFAULT 0,
  `Date_Created` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_user`
--

INSERT INTO `tbl_user` (`User_ID`, `Role_ID`, `Fullname`, `Email`, `Username`, `Password_Hash`, `Academic_Number`, `Personnel_Rank`, `Must_Change_Password`, `Date_Created`, `Status`) VALUES
(1, 1, 'Administrator', 'admin@rtc.com', 'admin', '$2y$10$FdwKuCbt5y6mmRHb1T87TeCgLeNwdryRShh/kb9u1VEen4P9QlF86', NULL, NULL, 0, '2026-07-04 04:23:40', 'Active'),
(2, 2, 'CCMD Officer', 'ccmd@rtc.com', 'ccmd', '$2y$10$FdwKuCbt5y6mmRHb1T87TeCgLeNwdryRShh/kb9u1VEen4P9QlF86', NULL, NULL, 0, '2026-07-04 04:23:40', 'Active'),
(4, 3, 'Crisbell Ric V. Aboc', 'crisbellricaboc18@gmail.com', 'crisbell', '$2y$10$FdwKuCbt5y6mmRHb1T87TeCgLeNwdryRShh/kb9u1VEen4P9QlF86', '02-2324-00092', NULL, 0, '2026-07-04 15:17:20', 'Active'),
(7, 3, 'Crisbell Ric Visto Aboc', 'rivi.aboc.coc@phinmaed.com', 'Cris', '$2y$10$QGh4kBi57DFY/8h39/cW4OPbz.BywJhbKfjAw5Z3.lka62IwPb8J6', '123', NULL, 0, '2026-07-07 16:01:11', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user_batch`
--

CREATE TABLE `tbl_user_batch` (
  `User_Batch_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Batch_ID` int(11) NOT NULL,
  `Status` enum('Active','Completed','Dropped') DEFAULT 'Active',
  `Date_Enrolled` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_user_batch`
--

INSERT INTO `tbl_user_batch` (`User_Batch_ID`, `User_ID`, `Batch_ID`, `Status`, `Date_Enrolled`) VALUES
(6, 7, 4, 'Active', '2026-07-07 19:39:48'),
(9, 7, 11, 'Active', '2026-07-07 22:16:47'),
(10, 4, 11, 'Active', '2026-07-07 22:16:47'),
(11, 7, 25, 'Active', '2026-07-07 22:17:24'),
(12, 4, 25, 'Active', '2026-07-07 22:17:24');

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
  ADD KEY `idx_batch_user` (`User_ID`),
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
  ADD KEY `idx_attempt_lookup` (`Exam_ID`,`User_ID`,`Attempted_At`);

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
  ADD KEY `idx_session_user` (`User_ID`);

--
-- Indexes for table `tbl_notification`
--
ALTER TABLE `tbl_notification`
  ADD PRIMARY KEY (`Notification_ID`),
  ADD KEY `idx_notif_user` (`User_ID`);

--
-- Indexes for table `tbl_permission`
--
ALTER TABLE `tbl_permission`
  ADD PRIMARY KEY (`Permission_ID`),
  ADD UNIQUE KEY `uq_permission_name` (`Permission_Name`);

--
-- Indexes for table `tbl_question_bank`
--
ALTER TABLE `tbl_question_bank`
  ADD PRIMARY KEY (`Question_ID`),
  ADD KEY `idx_qbank_subject` (`Subject_ID`),
  ADD KEY `idx_qbank_user` (`User_ID`);

--
-- Indexes for table `tbl_report`
--
ALTER TABLE `tbl_report`
  ADD PRIMARY KEY (`Report_ID`),
  ADD KEY `idx_report_user` (`User_ID`);

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
-- Indexes for table `tbl_role_permission`
--
ALTER TABLE `tbl_role_permission`
  ADD PRIMARY KEY (`Role_ID`,`Permission_ID`),
  ADD KEY `fk_rp_permission` (`Permission_ID`);

--
-- Indexes for table `tbl_subject`
--
ALTER TABLE `tbl_subject`
  ADD PRIMARY KEY (`Subject_ID`),
  ADD UNIQUE KEY `uq_subject_code` (`Subject_Code`),
  ADD KEY `idx_subject_course` (`Course_ID`);

--
-- Indexes for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `uq_user_email` (`Email`),
  ADD UNIQUE KEY `uq_user_username` (`Username`),
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
  MODIFY `Section_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `tbl_answer`
--
ALTER TABLE `tbl_answer`
  MODIFY `Answer_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `tbl_batch`
--
ALTER TABLE `tbl_batch`
  MODIFY `Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `tbl_cheating_incident`
--
ALTER TABLE `tbl_cheating_incident`
  MODIFY `Incident_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_cheating_log`
--
ALTER TABLE `tbl_cheating_log`
  MODIFY `Cheating_Log_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_choice`
--
ALTER TABLE `tbl_choice`
  MODIFY `Choice_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `tbl_course`
--
ALTER TABLE `tbl_course`
  MODIFY `Course_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_exam`
--
ALTER TABLE `tbl_exam`
  MODIFY `Exam_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_exam_batch`
--
ALTER TABLE `tbl_exam_batch`
  MODIFY `Exam_Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tbl_exam_code_attempt`
--
ALTER TABLE `tbl_exam_code_attempt`
  MODIFY `Attempt_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_exam_question`
--
ALTER TABLE `tbl_exam_question`
  MODIFY `Exam_Question_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `tbl_exam_session`
--
ALTER TABLE `tbl_exam_session`
  MODIFY `Session_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_notification`
--
ALTER TABLE `tbl_notification`
  MODIFY `Notification_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_permission`
--
ALTER TABLE `tbl_permission`
  MODIFY `Permission_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_question_bank`
--
ALTER TABLE `tbl_question_bank`
  MODIFY `Question_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_report`
--
ALTER TABLE `tbl_report`
  MODIFY `Report_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_result`
--
ALTER TABLE `tbl_result`
  MODIFY `Result_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_role`
--
ALTER TABLE `tbl_role`
  MODIFY `Role_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_subject`
--
ALTER TABLE `tbl_subject`
  MODIFY `Subject_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_user_batch`
--
ALTER TABLE `tbl_user_batch`
  MODIFY `User_Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  ADD CONSTRAINT `fk_batch_section` FOREIGN KEY (`Section_ID`) REFERENCES `tbl_academic_section` (`Section_ID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_batch_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_notification`
--
ALTER TABLE `tbl_notification`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_question_bank`
--
ALTER TABLE `tbl_question_bank`
  ADD CONSTRAINT `fk_qbank_subject` FOREIGN KEY (`Subject_ID`) REFERENCES `tbl_subject` (`Subject_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_qbank_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_report`
--
ALTER TABLE `tbl_report`
  ADD CONSTRAINT `fk_report_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_result`
--
ALTER TABLE `tbl_result`
  ADD CONSTRAINT `fk_result_session` FOREIGN KEY (`Session_ID`) REFERENCES `tbl_exam_session` (`Session_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_role_permission`
--
ALTER TABLE `tbl_role_permission`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`Permission_ID`) REFERENCES `tbl_permission` (`Permission_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`Role_ID`) REFERENCES `tbl_role` (`Role_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_subject`
--
ALTER TABLE `tbl_subject`
  ADD CONSTRAINT `fk_subject_course` FOREIGN KEY (`Course_ID`) REFERENCES `tbl_course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

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
