-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 15, 2026 at 06:04 PM
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
(27, 37, 'Alpha', 40, 0, NULL),
(28, 37, 'Bravo', 40, 0, NULL),
(29, 37, 'Charlie', 40, 1, '2026-07-11 01:54:28'),
(30, 37, 'Charlie', 40, 1, '2026-07-12 17:34:43'),
(31, 37, 'Charlie', 40, 0, NULL),
(32, 47, 'Alpha', 40, 0, NULL);

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
(1, 14, 14, 18, 82, NULL, 1),
(2, 14, 14, 12, 52, NULL, 1),
(3, 14, 14, 21, 96, NULL, 1),
(4, 14, 14, 19, 88, NULL, 1),
(5, 14, 14, 14, 63, NULL, 1),
(6, 14, 14, 20, 92, NULL, 1),
(7, 14, 14, 13, 58, NULL, 1),
(8, 14, 14, 16, 73, NULL, 1),
(9, 14, 14, 15, 67, NULL, 1),
(10, 14, 14, 17, 77, NULL, 1),
(11, 15, 15, 23, 108, NULL, 1),
(12, 15, 15, 22, 103, NULL, 1),
(13, 16, 16, 12, 54, NULL, 0),
(14, 16, 16, 16, 75, NULL, 0),
(15, 16, 16, 19, 89, NULL, 0),
(16, 16, 16, 18, 85, NULL, 0),
(17, 16, 16, 22, 104, NULL, 0),
(18, 16, 16, 14, 62, NULL, 0),
(19, 16, 16, 15, 67, NULL, 1),
(20, 16, 16, 23, 109, NULL, 0),
(21, 16, 16, 21, 97, NULL, 0),
(22, 16, 16, 17, 76, NULL, 0),
(23, 16, 16, 20, 95, NULL, 0),
(24, 16, 16, 13, 57, NULL, 0),
(25, 17, 17, 17, 77, NULL, 1),
(26, 17, 17, 15, 67, NULL, 1),
(27, 17, 17, 13, 58, NULL, 1),
(28, 17, 17, 12, 52, NULL, 1),
(29, 17, 17, 16, 73, NULL, 1),
(30, 17, 17, 20, 92, NULL, 1),
(31, 17, 17, 14, 64, NULL, 0),
(32, 17, 17, 19, 88, NULL, 1),
(33, 17, 17, 18, 82, NULL, 1),
(34, 17, 17, 21, 96, NULL, 1),
(35, 18, 18, 23, 108, NULL, 1),
(36, 18, 18, 22, 103, NULL, 1),
(37, 19, 19, 12, 52, NULL, 1),
(38, 19, 19, 13, 58, NULL, 1),
(39, 19, 19, 14, 65, NULL, 0),
(40, 19, 19, 15, 67, NULL, 1),
(41, 19, 19, 16, 73, NULL, 1),
(42, 21, 21, 22, 103, NULL, 1),
(43, 21, 21, 23, 108, NULL, 1),
(44, 22, 22, 23, 108, NULL, 1),
(45, 23, 23, 12, 52, NULL, 1),
(46, 24, 24, 19, 88, NULL, 1),
(47, 24, 24, 14, 63, NULL, 1),
(48, 24, 24, 21, 96, NULL, 1),
(49, 24, 24, 17, 77, NULL, 1),
(50, 24, 24, 18, 82, NULL, 1),
(51, 24, 24, 13, 58, NULL, 1),
(52, 24, 24, 15, 66, NULL, 0),
(53, 24, 24, 12, 52, NULL, 1),
(54, 24, 24, 20, 92, NULL, 1),
(55, 24, 24, 16, 73, NULL, 1),
(56, 25, 25, 19, 88, NULL, 1),
(57, 25, 25, 12, 52, NULL, 1),
(58, 25, 25, 18, 82, NULL, 1),
(59, 26, 26, 12, 52, NULL, 1),
(60, 26, 26, 13, 58, NULL, 1),
(61, 26, 26, 14, 63, NULL, 1),
(62, 26, 26, 15, 67, NULL, 1),
(63, 26, 26, 16, 73, NULL, 1),
(64, 26, 26, 17, 77, NULL, 1),
(65, 26, 26, 18, 82, NULL, 1),
(66, 26, 26, 19, 88, NULL, 1),
(67, 26, 26, 20, 92, NULL, 1),
(68, 26, 26, 21, 96, NULL, 1),
(69, 27, 28, 21, 96, NULL, 1),
(70, 27, 28, 12, 52, NULL, 1),
(71, 27, 28, 13, 58, NULL, 1),
(72, 27, 28, 15, 70, NULL, 0),
(73, 27, 28, 16, 75, NULL, 0),
(74, 27, 28, 18, 82, NULL, 1),
(75, 27, 28, 14, 62, NULL, 0),
(76, 27, 28, 20, 92, NULL, 1),
(77, 27, 28, 17, 77, NULL, 1),
(78, 27, 28, 19, 88, NULL, 1),
(79, 28, 29, 21, 96, NULL, 1),
(80, 28, 29, 18, 82, NULL, 1),
(81, 28, 29, 15, 67, NULL, 1),
(82, 28, 29, 12, 52, NULL, 1),
(83, 28, 29, 13, 60, NULL, 0),
(84, 28, 29, 14, 63, NULL, 1),
(85, 28, 29, 19, 88, NULL, 1),
(86, 28, 29, 16, 75, NULL, 0),
(87, 28, 29, 20, 95, NULL, 0),
(88, 28, 29, 17, 80, NULL, 0),
(89, 29, 30, 18, 82, NULL, 1),
(90, 29, 30, 13, 58, NULL, 1),
(91, 29, 30, 21, 96, NULL, 1),
(92, 29, 30, 15, 67, NULL, 1),
(93, 29, 30, 20, 95, NULL, 0),
(94, 29, 30, 14, 63, NULL, 1),
(95, 29, 30, 12, 52, NULL, 1),
(96, 29, 30, 17, 77, NULL, 1),
(97, 29, 30, 16, 75, NULL, 0),
(98, 29, 30, 19, 88, NULL, 1),
(99, 31, 31, 21, 96, NULL, 1),
(100, 31, 31, 19, 88, NULL, 1),
(101, 31, 31, 15, 67, NULL, 1),
(102, 31, 31, 20, 92, NULL, 1),
(103, 31, 31, 13, 58, NULL, 1),
(104, 31, 31, 14, 65, NULL, 0),
(105, 31, 31, 16, 73, NULL, 1),
(106, 31, 31, 12, 55, NULL, 0),
(107, 31, 31, 17, 77, NULL, 1),
(108, 31, 31, 18, 82, NULL, 1),
(109, 32, 32, 19, 90, NULL, 0),
(110, 32, 32, 15, 67, NULL, 1),
(111, 32, 32, 14, 63, NULL, 1),
(112, 32, 32, 12, 55, NULL, 0),
(113, 32, 32, 13, 60, NULL, 0),
(114, 32, 32, 18, 84, NULL, 0),
(115, 32, 32, 16, 75, NULL, 0),
(116, 32, 32, 21, 100, NULL, 0),
(117, 32, 32, 20, 95, NULL, 0),
(118, 32, 32, 17, 77, NULL, 1),
(119, 33, 33, 15, 67, NULL, 1),
(120, 33, 33, 18, 82, NULL, 1),
(121, 33, 33, 17, 77, NULL, 1),
(122, 33, 33, 13, 58, NULL, 1),
(123, 33, 33, 14, 63, NULL, 1),
(124, 33, 33, 21, 96, NULL, 1),
(125, 33, 33, 16, 73, NULL, 1),
(126, 33, 33, 12, 52, NULL, 1),
(127, 33, 33, 19, 88, NULL, 1),
(128, 33, 33, 20, 92, NULL, 1);

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
(1, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-09 21:53:35'),
(4, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-09 21:57:55'),
(5, 1, 'DELETE_USER', 'USER', 'Deleted user ID 4', 'SUCCESS', 'user', 4, '::1', 'Admin', '2026-07-09 22:00:44'),
(6, 1, 'DELETE_USER', 'USER', 'Deleted user ID 7', 'SUCCESS', 'user', 7, '::1', 'Admin', '2026-07-09 22:00:49'),
(7, 1, 'CREATE_USER', 'USER', 'Created user Crisbell Ric V. Aboc (ID 8)', 'SUCCESS', 'user', 8, '::1', 'Admin', '2026-07-09 22:01:59'),
(8, 1, 'CREATE_USER', 'USER', 'Created user Rhick Marguo F. Orina (ID 9)', 'SUCCESS', 'user', 9, '::1', 'Admin', '2026-07-09 22:03:49'),
(9, 1, 'CREATE_USER', 'USER', 'Created user Edcel John P. Amper (ID 10)', 'SUCCESS', 'user', 10, '::1', 'Admin', '2026-07-09 22:05:02'),
(10, 1, 'CREATE_SUBJECT', 'SUBJECT', 'Subject Protocol and Social Amenities created (ID 4)', 'SUCCESS', 'subject', 4, '::1', 'Admin', '2026-07-09 22:06:48'),
(11, 1, 'CREATE_SUBJECT', 'SUBJECT', 'Subject Human Rights: A Review with Case Based Approach created (ID 5)', 'SUCCESS', 'subject', 5, '::1', 'Admin', '2026-07-09 22:07:24'),
(12, 1, 'CREATE_SUBJECT', 'SUBJECT', 'Subject Social Psychology and the Sustainable Future created (ID 6)', 'SUCCESS', 'subject', 6, '::1', 'Admin', '2026-07-09 22:09:27'),
(13, 1, 'DELETE_SUBJECT', 'SUBJECT', 'Subject ID 6 deleted', 'SUCCESS', 'subject', 6, '::1', 'Admin', '2026-07-09 22:09:33'),
(14, 1, 'DELETE_SUBJECT', 'SUBJECT', 'Subject ID 4 deleted', 'SUCCESS', 'subject', 4, '::1', 'Admin', '2026-07-09 22:09:38'),
(15, 1, 'CREATE_SUBJECT', 'SUBJECT', 'Subject Protocol and Social Amenities created (ID 7)', 'SUCCESS', 'subject', 7, '::1', 'Admin', '2026-07-09 22:10:00'),
(16, 1, 'CREATE_SUBJECT', 'SUBJECT', 'Subject Social Psychology and the Sustainable Future created (ID 8)', 'SUCCESS', 'subject', 8, '::1', 'Admin', '2026-07-09 22:10:13'),
(17, 1, 'CREATE_SUBJECT', 'SUBJECT', 'Subject Research and Statistics created (ID 9)', 'SUCCESS', 'subject', 9, '::1', 'Admin', '2026-07-09 22:10:59'),
(18, 1, 'CREATE_SUBJECT', 'SUBJECT', 'Subject Statistics created (ID 10)', 'SUCCESS', 'subject', 10, '::1', 'Admin', '2026-07-09 22:11:46'),
(19, 1, 'CREATE_EXAM', 'EXAM', 'Exam 11 created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:15:44'),
(20, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 11', 'SUCCESS', 'exam', 11, '::1', 'Admin', '2026-07-09 22:15:44'),
(21, 1, 'CREATE_EXAM', 'EXAM', 'Exam 12 created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:18:05'),
(22, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 12', 'SUCCESS', 'exam', 12, '::1', 'Admin', '2026-07-09 22:18:05'),
(23, 1, 'CREATE_EXAM', 'EXAM', 'Exam 13 created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:20:48'),
(24, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 13', 'SUCCESS', 'exam', 13, '::1', 'Admin', '2026-07-09 22:20:48'),
(25, 1, 'CREATE_EXAM', 'EXAM', 'Exam 14 created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:22:43'),
(26, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 14', 'SUCCESS', 'exam', 14, '::1', 'Admin', '2026-07-09 22:22:43'),
(27, 1, 'CREATE_EXAM', 'EXAM', 'Exam 15 created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:23:21'),
(28, 1, 'UPDATE_EXAM', 'EXAM', 'Exam 14 updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:23:38'),
(29, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 14', 'SUCCESS', 'exam', 14, '::1', 'Admin', '2026-07-09 22:23:38'),
(30, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-09 22:30:55'),
(31, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-09 22:33:04'),
(32, 1, 'IMPORT_QUESTIONS', 'QUESTION', 'Imported 10 questions (failed: 0)', 'SUCCESS', 'question', NULL, '::1', 'Admin', '2026-07-09 22:35:04'),
(33, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 11', 'SUCCESS', 'exam', 11, '::1', 'Admin', '2026-07-09 22:36:28'),
(34, 1, 'UPDATE_EXAM', 'EXAM', 'Exam 11 updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:36:34'),
(35, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 11', 'SUCCESS', 'exam', 11, '::1', 'Admin', '2026-07-09 22:36:34'),
(36, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 11', 'SUCCESS', 'exam', 11, '::1', 'Admin', '2026-07-09 22:36:35'),
(37, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 15', 'SUCCESS', 'exam', 15, '::1', 'Admin', '2026-07-09 22:37:10'),
(38, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 15', 'SUCCESS', 'exam', 15, '::1', 'Admin', '2026-07-09 22:37:50'),
(39, 1, 'UPDATE_EXAM', 'EXAM', 'Exam 15 updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:38:14'),
(40, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 15', 'SUCCESS', 'exam', 15, '::1', 'Admin', '2026-07-09 22:38:14'),
(41, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 15', 'SUCCESS', 'exam', 15, '::1', 'Admin', '2026-07-09 22:38:14'),
(42, 1, 'UPDATE_EXAM', 'EXAM', 'Exam 15 updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:38:59'),
(43, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 15', 'SUCCESS', 'exam', 15, '::1', 'Admin', '2026-07-09 22:38:59'),
(44, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 15', 'SUCCESS', 'exam', 15, '::1', 'Admin', '2026-07-09 22:38:59'),
(45, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-09 22:40:04'),
(46, 8, 'LOGIN', 'AUTH', 'User Crisbell Ric V. Aboc logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-09 22:40:41'),
(47, 8, 'PASSWORD_CHANGE', 'AUTH', 'User changed password', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-09 22:41:57'),
(48, 8, 'LOGOUT', 'AUTH', 'User Crisbell Ric V. Aboc logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-09 22:42:31'),
(49, 9, 'LOGIN', 'AUTH', 'User Rhick Marguo F. Orina logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-09 22:42:52'),
(50, 9, 'PASSWORD_CHANGE', 'AUTH', 'User changed password', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-09 22:43:42'),
(51, 9, 'LOGOUT', 'AUTH', 'User Rhick Marguo F. Orina logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-09 22:43:47'),
(52, 10, 'LOGIN', 'AUTH', 'User Edcel John P. Amper logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-09 22:44:04'),
(53, 10, 'PASSWORD_CHANGE', 'AUTH', 'User changed password', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-09 22:44:29'),
(54, 10, 'LOGOUT', 'AUTH', 'User Edcel John P. Amper logged out', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-09 22:44:55'),
(55, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-09 22:45:22'),
(56, 1, 'UPDATE_EXAM', 'EXAM', 'Exam 11 updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:46:26'),
(57, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 11', 'SUCCESS', 'exam', 11, '::1', 'Admin', '2026-07-09 22:46:26'),
(58, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 11', 'SUCCESS', 'exam', 11, '::1', 'Admin', '2026-07-09 22:46:26'),
(59, 1, 'UPDATE_EXAM', 'EXAM', 'Exam 15 updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-09 22:46:48'),
(60, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 15', 'SUCCESS', 'exam', 15, '::1', 'Admin', '2026-07-09 22:46:48'),
(61, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 15', 'SUCCESS', 'exam', 15, '::1', 'Admin', '2026-07-09 22:46:48'),
(62, 1, 'UPDATE_USER', 'USER', 'Updated user Edcel John P. Amper (ID 10)', 'SUCCESS', 'user', 10, '::1', 'Admin', '2026-07-09 23:02:20'),
(63, 1, 'UPDATE_USER', 'USER', 'Updated user Edcel John P. Amper (ID 10)', 'SUCCESS', 'user', 10, '::1', 'Admin', '2026-07-09 23:02:38'),
(64, 1, 'UPDATE_USER', 'USER', 'Updated user Edcel John P. Amper (ID 10)', 'SUCCESS', 'user', 10, '::1', 'Admin', '2026-07-09 23:02:49'),
(65, 1, 'REMOVE_USER_BATCH', 'BATCH', 'User 10 removed from batch 33', 'SUCCESS', 'batch', 33, '::1', 'Admin', '2026-07-09 23:17:13'),
(66, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-09 23:36:07'),
(67, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-09 23:37:08'),
(68, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 00:16:21'),
(69, 8, 'LOGIN', 'AUTH', 'User Crisbell Ric V. Aboc logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 00:16:35'),
(70, 8, 'LOGOUT', 'AUTH', 'User Crisbell Ric V. Aboc logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 00:16:45'),
(71, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 00:17:00'),
(72, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam 11 archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 00:23:33'),
(73, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam 15 archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 00:23:40'),
(74, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam 14 archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 00:23:46'),
(75, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam 13 archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 00:23:51'),
(76, 1, 'CREATE_EXAM', 'EXAM', 'Exam 16 created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 00:28:48'),
(77, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam 16', 'SUCCESS', 'exam', 16, '::1', 'Admin', '2026-07-10 00:28:48'),
(78, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam 16', 'SUCCESS', 'exam', 16, '::1', 'Admin', '2026-07-10 00:28:48'),
(79, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 00:28:57'),
(80, NULL, 'LOGIN_FAILED', 'AUTH', 'Failed login attempt for email: crisbellricaboc18@gmail.com', 'FAILED', NULL, NULL, '::1', NULL, '2026-07-10 00:29:09'),
(81, 8, 'LOGIN', 'AUTH', 'User Crisbell Ric V. Aboc logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 00:29:20'),
(82, 8, 'LOGOUT', 'AUTH', 'User Crisbell Ric V. Aboc logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 00:30:51'),
(83, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 00:31:05'),
(84, 1, 'EXPORT_EXAM_RESPONSES', 'REPORT', 'Exported rankings CSV for exam 16', 'SUCCESS', 'exam', 16, '::1', 'Admin', '2026-07-10 00:31:40'),
(85, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Exam 16 result 14 viewed by admin', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 00:32:01'),
(86, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 00:56:15'),
(87, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 01:00:29'),
(88, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 01:06:18'),
(89, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 01:07:44'),
(90, 1, 'LOGOUT', 'AUTH', 'User Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 01:53:27'),
(91, 8, 'LOGIN', 'AUTH', 'User Crisbell Ric V. Aboc logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 01:53:46'),
(92, 8, 'LOGOUT', 'AUTH', 'User Crisbell Ric V. Aboc logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 01:54:21'),
(93, 8, 'LOGIN', 'AUTH', 'User Crisbell Ric V. Aboc logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 01:54:39'),
(94, 8, 'LOGOUT', 'AUTH', 'User Crisbell Ric V. Aboc logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 01:55:01'),
(95, 1, 'LOGIN', 'AUTH', 'Admin/CCMD login completed after CAPTCHA verification', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 01:55:23'),
(96, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:22:29'),
(97, 8, 'LOGIN', 'AUTH', 'Crisbell logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 02:23:12'),
(98, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 02:23:17'),
(99, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:23:33'),
(100, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:27:00'),
(101, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:27:16'),
(102, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:34:53'),
(103, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:36:36'),
(104, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:38:59'),
(105, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:39:12'),
(106, 1, 'IMPORT_QUESTIONS', 'QUESTION', 'Imported 2 questions to Statistics (failed: 0)', 'SUCCESS', 'question', NULL, '::1', 'Admin', '2026-07-10 02:40:59'),
(107, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Sample\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 02:42:02'),
(108, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Sample', 'SUCCESS', 'exam', 17, '::1', 'Admin', '2026-07-10 02:42:03'),
(109, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '2 questions assigned to exam Sample', 'SUCCESS', 'exam', 17, '::1', 'Admin', '2026-07-10 02:42:03'),
(110, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:42:07'),
(111, 8, 'LOGIN', 'AUTH', 'Crisbell logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 02:42:22'),
(112, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 02:43:00'),
(113, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:43:11'),
(114, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:43:38'),
(115, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:44:49'),
(116, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:44:58'),
(117, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:45:50'),
(118, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 02:46:04'),
(119, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 08:57:51'),
(120, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:00:49'),
(121, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:01:19'),
(122, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Comprehensive Examination\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 09:11:12'),
(123, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-10 09:11:12'),
(124, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '12 questions assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-10 09:11:12'),
(125, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:11:17'),
(126, 8, 'LOGIN_FAILED', 'AUTH', 'Invalid password for rivi.aboc.coc@phinmaed.com', 'FAILED', 'user', 8, '::1', 'Examinee', '2026-07-10 09:11:28'),
(127, 8, 'LOGIN_FAILED', 'AUTH', 'Invalid password for rivi.aboc.coc@phinmaed.com', 'FAILED', 'user', 8, '::1', 'Examinee', '2026-07-10 09:11:34'),
(128, 8, 'LOGIN', 'AUTH', 'Crisbell logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 09:11:42'),
(129, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 09:13:25'),
(130, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-10 09:13:42'),
(131, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-10 09:15:20'),
(132, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:15:38'),
(133, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Crisbell exam response viewed for \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 09:21:29'),
(134, 1, 'DELETE_COURSE', 'COURSE', 'Course Public Safety Senior Leadership Course (PSSLC) deleted', 'SUCCESS', 'course', 3, '::1', 'Admin', '2026-07-10 09:24:04'),
(135, 1, 'CREATE_COURSE', 'COURSE', 'Course Public Safety Senior Leadership Course (PSSLC) created', 'SUCCESS', 'course', 6, '::1', 'Admin', '2026-07-10 09:24:55'),
(136, 1, 'DELETE_COURSE', 'COURSE', 'Course Public Safety Senior Leadership Course (PSSLC) deleted', 'SUCCESS', 'course', 6, '::1', 'Admin', '2026-07-10 09:25:03'),
(137, 1, 'CREATE_COURSE', 'COURSE', 'Course Public Safety Junior Leadership Course (PSJLC) created', 'SUCCESS', 'course', 7, '::1', 'Admin', '2026-07-10 09:25:24'),
(138, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:25:36'),
(139, 8, 'LOGIN', 'AUTH', 'Crisbell logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 09:25:59'),
(140, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 09:29:26'),
(141, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:29:46'),
(142, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:31:00'),
(143, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:33:30'),
(144, 1, 'TOGGLE_RESPONSE_REVIEW', 'EXAM', 'Response review enabled for exam \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 09:34:11'),
(145, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:35:58'),
(146, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 09:36:16'),
(147, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 09:36:35'),
(148, 10, 'LOGIN', 'AUTH', 'Edcel logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-10 09:36:44'),
(149, 10, 'LOGOUT', 'AUTH', 'Edcel logged out', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-10 09:37:07'),
(150, NULL, 'LOGIN_FAILED', 'AUTH', 'Failed login attempt for email: crisbellricaboc18@gmail.com', 'FAILED', NULL, NULL, '::1', NULL, '2026-07-10 09:37:35'),
(151, 8, 'LOGIN', 'AUTH', 'Crisbell logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 09:37:56'),
(152, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 09:38:32'),
(153, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-10 09:38:50'),
(154, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-10 09:38:57'),
(155, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:39:15'),
(156, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:40:37'),
(157, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-10 09:40:50'),
(158, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-10 09:41:01'),
(159, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-10 09:41:14'),
(160, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-10 09:43:08'),
(161, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 09:43:28'),
(162, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 09:43:39'),
(163, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:43:56'),
(164, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:44:15'),
(165, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 09:45:10'),
(166, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 09:47:33'),
(167, 10, 'LOGIN', 'AUTH', 'Edcel logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-10 09:49:05'),
(168, 10, 'LOGOUT', 'AUTH', 'Edcel logged out', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-10 09:49:16'),
(169, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:49:28'),
(170, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:49:39'),
(171, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:50:18'),
(172, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:50:33'),
(173, 10, 'LOGIN', 'AUTH', 'Edcel logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-10 09:50:51'),
(174, 10, 'LOGOUT', 'AUTH', 'Edcel logged out', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-10 09:51:37'),
(175, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:51:51'),
(176, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:54:05'),
(177, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 09:54:16'),
(178, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 09:54:39'),
(179, 8, 'LOGIN', 'AUTH', 'Crisbell logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 09:54:53'),
(180, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 09:56:08'),
(181, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:56:20'),
(182, 1, 'EXPORT_EXAM_RESPONSES', 'REPORT', 'Exported rankings CSV for exam \"Comprehensive Examination\"', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-10 09:59:11'),
(183, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 09:59:25'),
(184, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-10 10:00:08'),
(185, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-10 10:00:47'),
(186, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-10 10:08:20'),
(187, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 10:08:35'),
(188, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 10:10:58'),
(189, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 10:11:36'),
(190, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"hgdg\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 10:35:15'),
(191, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '3 batches assigned to exam hgdg', 'SUCCESS', 'exam', 19, '::1', 'Admin', '2026-07-10 10:35:15'),
(192, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '5 questions assigned to exam hgdg', 'SUCCESS', 'exam', 19, '::1', 'Admin', '2026-07-10 10:35:15'),
(193, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"hgdg\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-10 10:35:58'),
(194, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 10:36:25'),
(195, 8, 'LOGIN', 'AUTH', 'Crisbell logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 10:36:39'),
(196, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-10 10:38:09'),
(197, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 10:38:22'),
(198, 1, 'EXPORT_EXAM_RESPONSES', 'REPORT', 'Exported rankings CSV for exam \"hgdg\"', 'SUCCESS', 'exam', 19, '::1', 'Admin', '2026-07-10 10:38:52'),
(199, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 10:46:11'),
(200, 9, 'LOGIN_FAILED', 'AUTH', 'Invalid password for rhfl.orina.coc@phinmaed.com', 'FAILED', 'user', 9, '::1', 'Examinee', '2026-07-10 10:46:28'),
(201, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 10:46:39'),
(202, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-10 10:46:40'),
(203, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 22:18:25'),
(204, 1, 'DELETE_COURSE', 'COURSE', 'Course Public Safety Junior Leadership Course (PSJLC) deleted', 'SUCCESS', 'course', 7, '::1', 'Admin', '2026-07-10 22:19:27'),
(205, 1, 'CREATE_COURSE', 'COURSE', 'Course Public Safety Senior Leadership Course (PSSLC) created', 'SUCCESS', 'course', 8, '::1', 'Admin', '2026-07-10 22:20:05'),
(206, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Edcel removed from batch Bravo Enrollment', 'SUCCESS', 'batch', 39, '::1', 'Admin', '2026-07-10 22:20:35'),
(207, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 22:25:54'),
(208, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 22:30:23'),
(209, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 23:20:47'),
(210, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 23:22:13'),
(211, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-10 23:58:29'),
(212, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 00:07:55'),
(213, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"hgdg\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 00:18:41'),
(214, 1, 'CREATE_MODULE', 'COURSE', 'Module \"Module 2\" created', 'SUCCESS', 'module', 5, '::1', 'Admin', '2026-07-11 00:36:01'),
(215, 1, 'UPDATE_MODULE', 'COURSE', 'Module \"Module 1\" updated', 'SUCCESS', 'module', 1, '::1', 'Admin', '2026-07-11 00:36:13'),
(216, 1, 'EXPORT_REPORT', 'REPORT', 'Exported student-performance report CSV (3 rows)', 'SUCCESS', 'report', NULL, '::1', 'Admin', '2026-07-11 00:42:56'),
(217, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 00:56:32'),
(218, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 00:56:47'),
(219, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 00:57:49'),
(220, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 00:58:00'),
(221, 1, 'UPDATE_SUBJECT', 'SUBJECT', 'Subject Human Rights: A Review with Case Based Approach updated', 'SUCCESS', 'subject', 5, '::1', 'Admin', '2026-07-11 01:33:49'),
(222, 1, 'UPDATE_SUBJECT', 'SUBJECT', 'Subject Human Rights: A Review with Case Based Approach updated', 'SUCCESS', 'subject', 5, '::1', 'Admin', '2026-07-11 01:34:01'),
(223, 1, 'ARCHIVE_MODULE', 'COURSE', 'Module \"Module 1\" archived', 'SUCCESS', 'module', 2, '::1', 'Admin', '2026-07-11 01:53:55'),
(224, 1, 'ARCHIVE_SECTION', 'SECTION', 'Section 29 archived', 'SUCCESS', 'section', 29, '::1', 'Admin', '2026-07-11 01:54:28'),
(225, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:15:21'),
(226, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:15:34'),
(227, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:15:41'),
(228, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:15:52'),
(229, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:15:58'),
(230, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:16:10'),
(231, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:17:06'),
(232, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 02:17:26'),
(233, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 02:19:45'),
(234, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 02:20:03'),
(235, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 02:26:30'),
(236, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 02:26:53'),
(237, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:27:08'),
(238, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:27:30'),
(239, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 02:28:17'),
(240, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 02:32:03'),
(241, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 02:32:15'),
(242, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 02:32:31'),
(243, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:32:44'),
(244, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 02:32:57'),
(245, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 02:33:18'),
(246, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 02:37:02'),
(247, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 02:37:19'),
(248, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 02:43:33'),
(249, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 09:34:50'),
(250, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Pre-Test\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 09:47:32'),
(251, 1, 'CLOSE_EXAM', 'EXAM', 'Exam \"Pre-Test\" closed', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 09:47:44'),
(252, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Pre-Test\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 09:54:46'),
(253, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Pre-Test', 'SUCCESS', 'exam', 20, '::1', 'Admin', '2026-07-11 09:54:46'),
(254, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Pre-Test\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 09:56:11'),
(255, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Pre-Test', 'SUCCESS', 'exam', 21, '::1', 'Admin', '2026-07-11 09:56:11'),
(256, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Pre-Test\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 10:00:09'),
(257, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Pre-Test', 'SUCCESS', 'exam', 22, '::1', 'Admin', '2026-07-11 10:00:09'),
(258, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Pre-Test', 'SUCCESS', 'exam', 22, '::1', 'Admin', '2026-07-11 10:00:09'),
(259, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Pre-Test\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 10:05:07'),
(260, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Pre-Test', 'SUCCESS', 'exam', 23, '::1', 'Admin', '2026-07-11 10:05:08'),
(261, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Pre-Test', 'SUCCESS', 'exam', 23, '::1', 'Admin', '2026-07-11 10:05:08'),
(262, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Exam\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 10:09:59'),
(263, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Exam', 'SUCCESS', 'exam', 24, '::1', 'Admin', '2026-07-11 10:09:59'),
(264, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Exam', 'SUCCESS', 'exam', 24, '::1', 'Admin', '2026-07-11 10:09:59'),
(265, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 10:20:15'),
(266, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 10:20:26'),
(267, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 11:14:20'),
(268, 9, 'LOGIN_FAILED', 'AUTH', 'Invalid password for rhfl.orina.coc@phinmaed.com', 'FAILED', 'user', 9, '::1', 'Examinee', '2026-07-11 11:14:34'),
(269, 9, 'LOGIN_FAILED', 'AUTH', 'Invalid password for rhfl.orina.coc@phinmaed.com', 'FAILED', 'user', 9, '::1', 'Examinee', '2026-07-11 11:14:43'),
(270, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 11:14:51'),
(271, 2, 'LOGIN_FAILED', 'AUTH', 'Invalid password for ccmd@rtc.com', 'FAILED', 'user', 2, '::1', 'CCMD', '2026-07-11 18:45:07'),
(272, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:45:20'),
(273, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:51:11'),
(274, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:51:28'),
(275, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:51:36'),
(276, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:51:48'),
(277, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:51:55'),
(278, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:52:14'),
(279, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:53:18'),
(280, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 18:53:32'),
(281, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 22:13:18'),
(282, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 22:14:38'),
(283, 1, 'CREATE_USER', 'USER', 'Created user Alexis', 'SUCCESS', 'user', 11, '::1', 'Admin', '2026-07-11 22:17:09'),
(284, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 22:17:25'),
(287, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 22:18:43'),
(288, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 22:19:17'),
(290, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 22:20:52'),
(291, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 22:22:02'),
(294, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 22:37:34'),
(295, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 22:38:17'),
(303, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:04:03'),
(304, 1, 'DELETE_USER', 'USER', 'Deleted user Alexis', 'SUCCESS', 'user', 11, '::1', 'Admin', '2026-07-11 23:04:12'),
(305, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:07:22'),
(306, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 23:07:43'),
(307, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 23:13:53'),
(308, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:14:09'),
(309, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:14:27'),
(310, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:19:30'),
(311, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:19:37'),
(312, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 23:19:55'),
(313, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 23:28:11'),
(314, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 23:28:54'),
(315, 2, 'LOGIN_FAILED', 'AUTH', 'Invalid password for ccmd@rtc.com', 'FAILED', 'user', 2, '::1', 'CCMD', '2026-07-11 23:29:51'),
(316, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 23:30:03'),
(317, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 23:33:26'),
(318, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:33:46'),
(319, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:35:01'),
(320, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-11 23:35:21'),
(321, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:43:50'),
(322, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Comprehensive Examination\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 23:44:15'),
(323, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:44:29'),
(324, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 23:44:48'),
(325, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 23:44:56'),
(326, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:45:09'),
(327, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:45:22'),
(328, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 23:45:37'),
(329, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 23:47:35'),
(330, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:50:55'),
(331, 1, 'RESET_EXAM_CODE', 'EXAM', 'Exam code reset for \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-11 23:51:11'),
(332, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:51:16'),
(333, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 23:51:29'),
(334, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 23:57:25'),
(335, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:57:40'),
(336, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-11 23:57:53'),
(337, 9, 'LOGIN', 'AUTH', 'Rhick logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-11 23:58:11'),
(338, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:14:19'),
(339, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"hgdg\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 10:16:24'),
(340, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:16:32'),
(341, 10, 'LOGIN', 'AUTH', 'Edcel logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-12 10:16:47'),
(342, 10, 'LOGOUT', 'AUTH', 'Edcel logged out', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-12 10:16:55'),
(343, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:17:08'),
(344, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:17:20'),
(345, 10, 'LOGIN', 'AUTH', 'Edcel logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-12 10:17:33'),
(346, 2, 'LOGIN_FAILED', 'AUTH', 'Invalid password for ccmd@rtc.com', 'FAILED', 'user', 2, '::1', 'CCMD', '2026-07-12 10:20:59'),
(347, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 10:21:09'),
(348, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 10:23:28'),
(349, 8, 'LOGIN', 'AUTH', 'Crisbell logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 10:23:52'),
(350, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 10:35:02'),
(351, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:35:26'),
(352, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Comprehensive Examinations\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 10:37:58'),
(353, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Comprehensive Examinations', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-12 10:37:58'),
(354, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Comprehensive Examination\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 10:38:44'),
(355, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-12 10:38:44'),
(356, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:43:56'),
(357, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:46:47'),
(358, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Comprehensive Examinations\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 10:47:07'),
(359, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Comprehensive Examination\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 10:47:27'),
(360, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:47:58'),
(361, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 10:48:12'),
(362, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 10:48:46'),
(363, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:48:58'),
(364, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:50:45'),
(365, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 10:51:00'),
(366, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 10:51:36'),
(367, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:53:48'),
(368, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 10:59:09'),
(369, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 11:03:05'),
(370, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 11:03:15'),
(371, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 11:03:32'),
(372, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 11:04:29'),
(373, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 11:04:44'),
(374, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 11:10:28'),
(375, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 11:20:36'),
(376, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 11:23:55'),
(377, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 11:24:14'),
(378, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 11:45:21'),
(379, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 11:47:52'),
(380, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Protocol and Social Amenities: what', 'SUCCESS', 'question', 24, '::1', 'Admin', '2026-07-12 11:48:40'),
(381, 1, 'DELETE_QUESTION', 'QUESTION', 'Question deleted: what', 'SUCCESS', 'question', 24, '::1', 'Admin', '2026-07-12 11:50:47'),
(382, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-12 11:56:10'),
(383, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Protocol and Social Amenities: Hi', 'SUCCESS', 'question', 25, '::1', 'Admin', '2026-07-12 11:56:34'),
(384, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '11 questions assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-12 11:56:35'),
(385, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Protocol and Social Amenities: HEHEHE', 'SUCCESS', 'question', 26, '::1', 'Admin', '2026-07-12 11:58:17'),
(386, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Protocol and Social Amenities: SJNNDCNNB', 'SUCCESS', 'question', 27, '::1', 'Admin', '2026-07-12 11:58:17'),
(387, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '12 questions assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-12 11:58:17'),
(388, 1, 'DELETE_QUESTION', 'QUESTION', 'Question deleted: Hi', 'SUCCESS', 'question', 25, '::1', 'Admin', '2026-07-12 11:58:40'),
(389, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-12 11:59:26'),
(390, 1, 'DELETE_QUESTION', 'QUESTION', 'Question deleted: HEHEHE', 'SUCCESS', 'question', 26, '::1', 'Admin', '2026-07-12 11:59:35'),
(391, 1, 'DELETE_QUESTION', 'QUESTION', 'Question deleted: SJNNDCNNB', 'SUCCESS', 'question', 27, '::1', 'Admin', '2026-07-12 11:59:40'),
(392, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 12:05:52'),
(393, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-12 12:08:06'),
(394, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 12:08:22'),
(395, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Statistics: EXVSS', 'SUCCESS', 'question', 28, '::1', 'Admin', '2026-07-12 12:23:13'),
(396, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Statistics: JDXJVJ', 'SUCCESS', 'question', 29, '::1', 'Admin', '2026-07-12 12:23:13'),
(397, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Pre-Test\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 12:23:45'),
(398, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Pre-Test', 'SUCCESS', 'exam', 25, '::1', 'Admin', '2026-07-12 12:23:45'),
(399, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '4 questions assigned to exam Pre-Test', 'SUCCESS', 'exam', 25, '::1', 'Admin', '2026-07-12 12:23:45'),
(400, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '2 questions assigned to exam Pre-Test', 'SUCCESS', 'exam', 25, '::1', 'Admin', '2026-07-12 12:25:23'),
(401, 1, 'DELETE_QUESTION', 'QUESTION', 'Question deleted: JDXJVJ', 'SUCCESS', 'question', 29, '::1', 'Admin', '2026-07-12 12:25:46'),
(402, 1, 'DELETE_QUESTION', 'QUESTION', 'Question deleted: EXVSS', 'SUCCESS', 'question', 28, '::1', 'Admin', '2026-07-12 12:25:51'),
(403, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Statistics: dgdrd', 'SUCCESS', 'question', 30, '::1', 'Admin', '2026-07-12 12:37:21'),
(404, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Statistics: rrr', 'SUCCESS', 'question', 31, '::1', 'Admin', '2026-07-12 12:37:21'),
(405, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Statistics: rrrrr', 'SUCCESS', 'question', 32, '::1', 'Admin', '2026-07-12 12:37:21'),
(406, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 12:44:17'),
(407, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 12:44:32'),
(408, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 12:45:43'),
(409, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 12:45:57'),
(410, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 12:46:57'),
(411, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 17:18:28'),
(412, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 17:28:08'),
(413, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 17:34:15'),
(414, 1, 'ARCHIVE_SECTION', 'SECTION', 'Section 30 archived', 'SUCCESS', 'section', 30, '::1', 'Admin', '2026-07-12 17:34:43'),
(415, 1, 'CREATE_USER', 'USER', 'Created user Raiden', 'SUCCESS', 'user', 12, '::1', 'Admin', '2026-07-12 17:40:23'),
(416, 1, 'ARCHIVE_USER', 'USER', 'Archived user Raiden', 'SUCCESS', 'user', 12, '::1', 'Admin', '2026-07-12 17:40:41'),
(417, 1, 'ARCHIVE_MODULE', 'COURSE', 'Module \"Module 2\" archived', 'SUCCESS', 'module', 5, '::1', 'Admin', '2026-07-12 17:46:18'),
(418, 1, 'ARCHIVE_MODULE', 'COURSE', 'Module \"Module 1\" archived', 'SUCCESS', 'module', 3, '::1', 'Admin', '2026-07-12 17:46:24'),
(419, 1, 'ARCHIVE_MODULE', 'COURSE', 'Module \"Module 1\" archived', 'SUCCESS', 'module', 4, '::1', 'Admin', '2026-07-12 17:46:30'),
(420, 1, 'ARCHIVE_SECTION', 'SECTION', 'Section 31 archived', 'SUCCESS', 'section', 31, '::1', 'Admin', '2026-07-12 17:47:11'),
(421, 1, 'ARCHIVE_COURSE', 'COURSE', 'Course Public Safety Basic Recruit Course (PSBRC) archived', 'SUCCESS', 'course', 1, '::1', 'Admin', '2026-07-12 17:47:57'),
(422, 1, 'RESTORE_COURSE', 'COURSE', 'Course Public Safety Basic Recruit Course (PSBRC) restored', 'SUCCESS', 'course', 1, '::1', 'Admin', '2026-07-12 17:48:13');
INSERT INTO `tbl_audit_log` (`Log_ID`, `User_ID`, `Action`, `Module`, `Outcome`, `Status`, `Entity_Type`, `Entity_ID`, `IP_Address`, `User_Role`, `Timestamp`) VALUES
(423, 1, 'ARCHIVE_BATCH', 'BATCH', 'Batch 2026 archived', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 17:48:21'),
(424, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 18:02:32'),
(425, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 18:02:32'),
(426, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 18:03:44'),
(427, 1, 'RESTORE_EXAM', 'EXAM', 'Exam \"Modular Examination\" restored', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 18:06:21'),
(428, 1, 'ARCHIVE_COURSE', 'COURSE', 'Course Public Safety Senior Leadership Course (PSSLC) archived', 'SUCCESS', 'course', 8, '::1', 'Admin', '2026-07-12 18:06:53'),
(429, 1, 'RESTORE_COURSE', 'COURSE', 'Course Public Safety Senior Leadership Course (PSSLC) restored', 'SUCCESS', 'course', 8, '::1', 'Admin', '2026-07-12 18:06:59'),
(430, 1, 'ARCHIVE_QUESTION', 'QUESTION', 'Question archived: What is the capital of the Philippines?', 'SUCCESS', 'question', 23, '::1', 'Admin', '2026-07-12 18:07:29'),
(431, 1, 'ARCHIVE_USER', 'USER', 'Archived user Edcel', 'SUCCESS', 'user', 10, '::1', 'Admin', '2026-07-12 18:08:30'),
(432, 1, 'RESTORE_USER', 'USER', 'Restored user Edcel', 'SUCCESS', 'user', 10, '::1', 'Admin', '2026-07-12 18:08:43'),
(433, 1, 'RESTORE_BATCH', 'BATCH', 'Batch 2026 restored', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 18:14:26'),
(434, 1, 'RESTORE_SECTION', 'SECTION', 'Section 31 restored', 'SUCCESS', 'section', 31, '::1', 'Admin', '2026-07-12 18:14:32'),
(435, 1, 'RESTORE_QUESTION', 'QUESTION', 'Question restored: What is the capital of the Philippines?', 'SUCCESS', 'question', 23, '::1', 'Admin', '2026-07-12 18:14:45'),
(436, 1, 'ARCHIVE_BATCH', 'BATCH', 'Batch 2026 archived', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 18:23:24'),
(437, 1, 'RESTORE_BATCH', 'BATCH', 'Batch 2026 restored', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 18:23:39'),
(438, 1, 'ARCHIVE_BATCH', 'BATCH', 'Batch 2027 archived', 'SUCCESS', 'batch', 43, '::1', 'Admin', '2026-07-12 18:26:18'),
(439, 1, 'ARCHIVE_COURSE', 'COURSE', 'Course Public Safety Basic Recruit Course (PSBRC) archived', 'SUCCESS', 'course', 1, '::1', 'Admin', '2026-07-12 18:29:49'),
(440, 1, 'RESTORE_COURSE', 'COURSE', 'Course Public Safety Basic Recruit Course (PSBRC) restored', 'SUCCESS', 'course', 1, '::1', 'Admin', '2026-07-12 18:30:03'),
(441, 1, 'ARCHIVE_BATCH', 'BATCH', 'Batch 2026 archived', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 18:33:06'),
(442, 1, 'RESTORE_SECTION', 'SECTION', 'Section 27 restored', 'SUCCESS', 'section', 27, '::1', 'Admin', '2026-07-12 18:33:33'),
(443, 1, 'RESTORE_SECTION', 'SECTION', 'Section 28 restored', 'SUCCESS', 'section', 28, '::1', 'Admin', '2026-07-12 18:33:56'),
(444, 1, 'RESTORE_SECTION', 'SECTION', 'Section 31 restored', 'SUCCESS', 'section', 31, '::1', 'Admin', '2026-07-12 18:34:31'),
(445, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Crisbell removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 42, '::1', 'Admin', '2026-07-12 18:34:40'),
(446, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Edcel removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 42, '::1', 'Admin', '2026-07-12 18:34:42'),
(447, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Rhick removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 42, '::1', 'Admin', '2026-07-12 18:34:45'),
(448, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 18:39:34'),
(449, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 18:39:55'),
(450, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 18:40:23'),
(451, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 18:41:36'),
(452, 1, 'ARCHIVE_BATCH', 'BATCH', 'Batch 2026 archived', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 18:43:35'),
(453, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 18:51:03'),
(454, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 19:02:52'),
(455, 1, 'RESTORE_BATCH', 'BATCH', 'Batch 2026 restored', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 19:08:38'),
(456, 1, 'RESTORE_SECTION', 'SECTION', 'Section 27 restored', 'SUCCESS', 'section', 27, '::1', 'Admin', '2026-07-12 19:09:04'),
(457, 1, 'RESTORE_SECTION', 'SECTION', 'Section 28 restored', 'SUCCESS', 'section', 28, '::1', 'Admin', '2026-07-12 19:09:07'),
(458, 1, 'ARCHIVE_BATCH', 'BATCH', 'Batch 2026 archived', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 19:09:18'),
(459, 1, 'RESTORE_BATCH', 'BATCH', 'Batch 2026 restored', 'SUCCESS', 'batch', 37, '::1', 'Admin', '2026-07-12 19:09:30'),
(460, 1, 'RESTORE_SECTION', 'SECTION', 'Section 27 restored', 'SUCCESS', 'section', 27, '::1', 'Admin', '2026-07-12 19:09:45'),
(461, 1, 'RESTORE_SECTION', 'SECTION', 'Section 28 restored', 'SUCCESS', 'section', 28, '::1', 'Admin', '2026-07-12 19:09:48'),
(462, 1, 'RESTORE_SECTION', 'SECTION', 'Section 31 restored', 'SUCCESS', 'section', 31, '::1', 'Admin', '2026-07-12 19:09:51'),
(463, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 19:15:34'),
(464, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 19:15:53'),
(465, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 19:16:00'),
(466, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 19:29:21'),
(467, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 19:29:27'),
(468, 2, 'LOGIN_FAILED', 'AUTH', 'Invalid password for ccmd@rtc.com', 'FAILED', 'user', 2, '::1', 'CCMD', '2026-07-12 19:29:38'),
(469, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 19:29:51'),
(470, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 19:29:57'),
(471, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 19:30:12'),
(472, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 19:30:17'),
(473, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 19:30:30'),
(474, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 19:30:46'),
(475, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 19:30:57'),
(476, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 19:40:03'),
(477, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 19:46:43'),
(478, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 19:49:16'),
(479, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 19:49:31'),
(480, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 19:50:52'),
(481, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:15:20'),
(482, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:19:48'),
(483, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:20:11'),
(484, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:27:50'),
(485, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 20:28:05'),
(486, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 20:34:01'),
(487, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:34:14'),
(488, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:35:02'),
(489, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:37:36'),
(490, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:42:51'),
(491, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:45:34'),
(492, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 20:57:29'),
(493, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-12 21:27:42'),
(494, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-12 21:27:52'),
(495, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 21:28:01'),
(496, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 21:29:23'),
(497, 8, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 02-2324-00092', 'FAILED', 'user', 8, '::1', 'Examinee', '2026-07-12 21:29:40'),
(498, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 21:29:47'),
(499, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 21:37:10'),
(500, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 21:41:35'),
(501, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 22:04:07'),
(502, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 22:16:01'),
(503, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Pre-Test\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 22:23:12'),
(504, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '3 batches assigned to exam Pre-Test', 'SUCCESS', 'exam', 26, '::1', 'Admin', '2026-07-12 22:23:12'),
(505, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"HHHH\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 22:27:31'),
(506, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '2 batches assigned to exam HHHH', 'SUCCESS', 'exam', 27, '::1', 'Admin', '2026-07-12 22:27:31'),
(507, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 22:32:49'),
(508, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 22:39:09'),
(509, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Rhick removed from batch Alpha Enrollment', 'SUCCESS', 'batch', 38, '::1', 'Admin', '2026-07-12 22:44:58'),
(510, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Edcel removed from batch Bravo Enrollment', 'SUCCESS', 'batch', 39, '::1', 'Admin', '2026-07-12 22:45:04'),
(511, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Crisbell removed from batch Bravo Enrollment', 'SUCCESS', 'batch', 39, '::1', 'Admin', '2026-07-12 22:45:07'),
(512, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Comprehensive Examination\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-12 22:45:27'),
(513, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '2 batches assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-12 22:45:27'),
(514, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Rhick removed from batch Bravo Enrollment', 'SUCCESS', 'batch', 39, '::1', 'Admin', '2026-07-12 22:46:11'),
(515, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 22:57:18'),
(516, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 22:57:31'),
(517, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 22:57:55'),
(518, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 22:58:16'),
(519, 1, 'CREATE_USER', 'USER', 'Created user austine', 'SUCCESS', 'user', 13, '::1', 'Admin', '2026-07-12 23:00:35'),
(520, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 23:00:46'),
(521, 13, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 13, '::1', 'Admin', '2026-07-12 23:01:12'),
(522, 13, 'LOGOUT', 'AUTH', 'austine logged out', 'SUCCESS', 'user', 13, '::1', 'Admin', '2026-07-12 23:01:43'),
(523, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 23:02:03'),
(524, 1, 'ARCHIVE_USER', 'USER', 'Archived user austine', 'SUCCESS', 'user', 13, '::1', 'Admin', '2026-07-12 23:02:18'),
(525, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Rhick removed from batch Alpha Enrollment', 'SUCCESS', 'batch', 44, '::1', 'Admin', '2026-07-12 23:26:43'),
(526, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Edcel removed from batch Bravo Enrollment', 'SUCCESS', 'batch', 45, '::1', 'Admin', '2026-07-12 23:27:44'),
(527, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Crisbell removed from batch Bravo Enrollment', 'SUCCESS', 'batch', 45, '::1', 'Admin', '2026-07-12 23:27:46'),
(528, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 23:37:52'),
(529, 8, 'LOGIN', 'AUTH', 'Crisbell Ric V. Aboc (02-2324-00092) logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 23:38:30'),
(530, 8, 'LOGOUT', 'AUTH', 'Crisbell Ric V. Aboc (02-2324-00092) logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-12 23:43:35'),
(531, 1, 'LOGIN', 'AUTH', 'Administrator logged in', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 23:52:44'),
(532, 1, 'REMOVE_USER_BATCH', 'SECTION', 'Rhick Marguo F. Orina (02-2324-00093) removed from section Bravo', 'SUCCESS', 'section', 28, '::1', 'Admin', '2026-07-12 23:53:04'),
(533, 1, 'ASSIGN_USER_BATCH', 'SECTION', '1 examinee(s) assigned to section Alpha', 'SUCCESS', 'section', 27, '::1', 'Admin', '2026-07-12 23:53:08'),
(534, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 23:56:16'),
(535, 2, 'LOGIN', 'AUTH', 'CCMD Officer logged in', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 23:57:18'),
(536, 2, 'LOGOUT', 'AUTH', 'CCMD Officer logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-12 23:57:53'),
(537, 1, 'LOGIN', 'AUTH', 'Administrator logged in', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-12 23:59:30'),
(538, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Human Rights: A Review with Case Based Approach: What a nice', 'SUCCESS', 'question', 33, '::1', 'Admin', '2026-07-13 00:01:06'),
(539, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Human Rights: A Review with Case Based Approach: heheheh', 'SUCCESS', 'question', 34, '::1', 'Admin', '2026-07-13 00:01:06'),
(540, 1, 'CREATE_QUESTION', 'QUESTION', 'Question added to Human Rights: A Review with Case Based Approach: KCDJDJCN', 'SUCCESS', 'question', 35, '::1', 'Admin', '2026-07-13 00:01:06'),
(541, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Testing\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 00:01:12'),
(542, 1, 'ASSIGN_EXAM_SECTION', 'EXAM', '1 section assigned to exam Testing: Alpha', 'SUCCESS', 'exam', 28, '::1', 'Admin', '2026-07-13 00:01:12'),
(543, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '3 questions assigned to exam Testing', 'SUCCESS', 'exam', 28, '::1', 'Admin', '2026-07-13 00:01:12'),
(544, 1, 'UPDATE_EXAM_PARTICIPANTS', 'EXAM', '3 of 3 participants selected for exam Testing', 'SUCCESS', 'exam', 28, '::1', 'Admin', '2026-07-13 00:01:15'),
(545, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Testing\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 00:01:16'),
(546, 1, 'ASSIGN_EXAM_SECTION', 'EXAM', '1 section assigned to exam Testing: Alpha', 'SUCCESS', 'exam', 28, '::1', 'Admin', '2026-07-13 00:01:16'),
(547, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '3 questions assigned to exam Testing', 'SUCCESS', 'exam', 28, '::1', 'Admin', '2026-07-13 00:01:16'),
(548, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '3 questions assigned to exam Testing', 'SUCCESS', 'exam', 28, '::1', 'Admin', '2026-07-13 00:05:18'),
(549, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:11:17'),
(550, 8, 'LOGIN', 'AUTH', 'Crisbell Ric V. Aboc (02-2324-00092) logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-13 00:11:26'),
(551, 8, 'LOGOUT', 'AUTH', 'Crisbell Ric V. Aboc (02-2324-00092) logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-13 00:24:25'),
(552, 1, 'LOGIN', 'AUTH', 'Administrator logged in', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:24:37'),
(553, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Crisbell Ric V. Aboc (02-2324-00092) exam response viewed for \"hgdg\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 00:32:03'),
(554, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:37:21'),
(555, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:40:12'),
(556, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:41:54'),
(557, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:42:07'),
(558, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"hgdg\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 00:43:24'),
(559, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam hgdg', 'SUCCESS', 'exam', 19, '::1', 'Admin', '2026-07-13 00:43:24'),
(560, 1, 'UPDATE_EXAM_PARTICIPANTS', 'EXAM', '3 of 3 participants selected for exam hgdg', 'SUCCESS', 'exam', 19, '::1', 'Admin', '2026-07-13 00:43:31'),
(561, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"hgdg\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 00:43:52'),
(562, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:44:01'),
(563, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:44:16'),
(564, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:44:54'),
(565, 10, 'LOGIN', 'AUTH', '02-2324-00094 logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-13 00:45:18'),
(566, 10, 'LOGOUT', 'AUTH', 'Edcel logged out', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-13 00:45:54'),
(567, 9, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 02-2324-00093', 'FAILED', 'user', 9, '::1', 'Examinee', '2026-07-13 00:46:18'),
(568, 9, 'LOGIN', 'AUTH', '02-2324-00093 logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-13 00:46:35'),
(569, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-13 00:46:49'),
(570, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:47:08'),
(571, 2, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"hgdg\"', 'SUCCESS', 'exam', NULL, '::1', 'CCMD', '2026-07-13 00:47:21'),
(572, 2, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"hgdg\"', 'SUCCESS', 'exam', NULL, '::1', 'CCMD', '2026-07-13 00:47:38'),
(573, 2, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Edcel exam response viewed for \"hgdg\"', 'SUCCESS', 'exam', NULL, '::1', 'CCMD', '2026-07-13 00:47:47'),
(574, 2, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"hgdg\"', 'SUCCESS', 'exam', NULL, '::1', 'CCMD', '2026-07-13 00:47:55'),
(575, 2, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Crisbell exam response viewed for \"hgdg\"', 'SUCCESS', 'exam', NULL, '::1', 'CCMD', '2026-07-13 00:49:07'),
(576, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:50:58'),
(577, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:51:14'),
(578, 1, 'LOGIN', 'AUTH', 'Administrator logged in', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:52:28'),
(579, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:53:26'),
(580, 2, 'LOGIN', 'AUTH', 'CCMD Officer logged in', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:53:38'),
(581, 2, 'LOGOUT', 'AUTH', 'CCMD Officer logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:54:53'),
(582, 2, 'LOGIN_FAILED', 'AUTH', 'Invalid password for ccmd@rtc.com', 'FAILED', 'user', 2, '::1', 'CCMD', '2026-07-13 00:55:14'),
(583, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:55:26'),
(584, 2, 'LOGIN', 'AUTH', 'CCMD Officer logged in', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:56:05'),
(585, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 00:57:31'),
(586, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 00:57:46'),
(587, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:02:05'),
(588, 2, 'LOGIN', 'AUTH', 'CCMD Officer logged in', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 01:02:39'),
(589, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:06:40'),
(590, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:08:10'),
(591, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 01:08:29'),
(592, 1, 'LOGIN', 'AUTH', 'Administrator logged in', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:09:25'),
(593, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:10:01'),
(594, 1, 'LOGIN', 'AUTH', 'Administrator logged in', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:10:17'),
(595, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:10:27'),
(596, 2, 'LOGIN', 'AUTH', 'CCMD Officer logged in', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 01:10:46'),
(597, 2, 'LOGOUT', 'AUTH', 'CCMD Officer logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 01:11:13'),
(598, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for Administrator', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-13 01:11:21'),
(599, 1, 'LOGIN', 'AUTH', 'Administrator logged in', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:11:35'),
(600, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Edcel John P. Amper (02-2324-00094) exam response viewed for \"hgdg\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 01:11:51'),
(601, 1, 'LOGOUT', 'AUTH', 'Administrator logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:12:00'),
(602, 2, 'LOGIN', 'AUTH', 'CCMD Officer logged in', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 01:12:17'),
(603, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 01:15:58'),
(604, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:16:12'),
(605, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:23:00'),
(606, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 01:23:24'),
(607, 1, 'LOGIN', 'AUTH', 'Administrator logged in', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:27:26'),
(608, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Sample\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 01:29:49'),
(609, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:30:53'),
(610, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:31:11'),
(611, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"sample1\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 01:33:46'),
(612, 1, 'ASSIGN_EXAM_SECTION', 'EXAM', '1 section assigned to exam sample1: Alpha', 'SUCCESS', 'exam', 29, '::1', 'Admin', '2026-07-13 01:33:46'),
(613, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '2 questions assigned to exam sample1', 'SUCCESS', 'exam', 29, '::1', 'Admin', '2026-07-13 01:33:46'),
(614, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Pre-Test', 'SUCCESS', 'exam', 25, '::1', 'Admin', '2026-07-13 01:48:26'),
(615, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Pre-Test', 'SUCCESS', 'exam', 23, '::1', 'Admin', '2026-07-13 01:48:54'),
(616, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-13 01:49:19'),
(617, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Modular Examination', 'SUCCESS', 'exam', 13, '::1', 'Admin', '2026-07-13 01:53:09'),
(618, 1, 'UPDATE_EXAM_PARTICIPANTS', 'EXAM', '3 of 3 participants selected for exam Modular Examination', 'SUCCESS', 'exam', 13, '::1', 'Admin', '2026-07-13 01:53:11'),
(619, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Modular Examination\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-13 01:53:13'),
(620, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 01:58:36'),
(621, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 01:59:25'),
(622, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 02:07:40'),
(623, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 19:07:07'),
(624, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:20:40'),
(625, 1, 'CREATE_USER', 'USER', 'Created user 7132026', 'SUCCESS', 'user', 14, '::1', 'Admin', '2026-07-13 22:23:22'),
(626, 1, 'ARCHIVE_USER', 'USER', 'Archived user 7132026', 'SUCCESS', 'user', 14, '::1', 'Admin', '2026-07-13 22:23:34'),
(627, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:26:39'),
(628, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 22:26:53'),
(629, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-13 22:27:05'),
(630, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-13 22:27:14'),
(631, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:27:24'),
(632, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:33:23'),
(633, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-13 22:36:51'),
(634, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-13 22:36:56'),
(635, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:37:10'),
(636, 1, 'CREATE_USER', 'USER', 'Created user Austinee', 'SUCCESS', 'user', 15, '::1', 'Admin', '2026-07-13 22:42:30'),
(637, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:46:15'),
(638, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-13 22:46:31'),
(639, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-13 22:46:47'),
(640, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:47:01'),
(641, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:53:44'),
(642, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 22:57:22'),
(643, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-13 23:03:46'),
(644, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:22:14'),
(645, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"sample1\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:05'),
(646, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Pre-Test\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:09'),
(647, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Pre-Test\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:13'),
(648, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Pre-Test\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:18'),
(649, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Pre-Test\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:22'),
(650, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Pre-Test\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:26'),
(651, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Pre-Test\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:31'),
(652, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Modular Examination\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:43'),
(653, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"HHHH\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:50'),
(654, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Exam\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:25:56'),
(655, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Testing\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:26:02'),
(656, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Pre-Test\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:26:07'),
(657, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"hgdg\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:26:11'),
(658, 1, 'RESTORE_EXAM', 'EXAM', 'Exam \"hgdg\" restored', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:26:43'),
(659, 1, 'RESTORE_EXAM', 'EXAM', 'Exam \"Pre-Test\" restored', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:26:46'),
(660, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"hgdg\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:27:05'),
(661, 1, 'ARCHIVE_EXAM', 'EXAM', 'Exam \"Pre-Test\" archived', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:27:15'),
(662, 1, 'ARCHIVE_QUESTION', 'QUESTION', 'Question archived: What is the capital of the Philippines?', 'SUCCESS', 'question', 23, '::1', 'Admin', '2026-07-14 17:30:33'),
(663, 1, 'ARCHIVE_QUESTION', 'QUESTION', 'Question archived: What is the capital of the Philippines?', 'SUCCESS', 'question', 22, '::1', 'Admin', '2026-07-14 17:30:38'),
(664, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Comprehensive Examination\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:33:55'),
(665, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:34:00'),
(666, 9, 'LOGIN', 'AUTH', '02-2324-00093 logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-14 17:34:14'),
(667, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-14 17:34:31'),
(668, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:34:45'),
(669, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:35:00'),
(670, 9, 'LOGIN', 'AUTH', '02-2324-00093 logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-14 17:35:11'),
(671, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-14 17:37:54'),
(672, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:38:13'),
(673, 1, 'UPDATE_EXAM_PARTICIPANTS', 'EXAM', '0 of 4 participants selected for exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-14 17:40:50'),
(674, 1, 'UPDATE_EXAM_PARTICIPANTS', 'EXAM', '4 of 4 participants selected for exam Comprehensive Examination', 'SUCCESS', 'exam', 18, '::1', 'Admin', '2026-07-14 17:40:58'),
(675, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Crisbell exam response viewed for \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:45:19'),
(676, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:45:29'),
(677, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:45:46'),
(678, 9, 'LOGIN', 'AUTH', '02-2324-00093 logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-14 17:45:56'),
(679, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-14 17:46:26'),
(680, 8, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 02-2324-00092', 'FAILED', 'user', 8, '::1', 'Examinee', '2026-07-14 17:46:48'),
(681, 10, 'LOGIN', 'AUTH', '02-2324-00094 logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-14 17:47:00'),
(682, 10, 'LOGOUT', 'AUTH', 'Edcel logged out', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-14 17:48:27'),
(683, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:48:40'),
(684, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:51:25'),
(685, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Crisbell exam response viewed for \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:53:39'),
(686, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-14 17:54:31'),
(687, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:56:14'),
(688, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:56:28'),
(689, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:56:39'),
(690, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 17:56:51'),
(691, 15, 'PASSWORD_CHANGE', 'AUTH', 'Austinee changed password', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 17:57:20'),
(692, 15, 'LOGOUT', 'AUTH', 'Austinee logged out', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 17:57:41'),
(693, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:57:55'),
(694, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:58:10'),
(695, 13, 'LOGIN_FAILED', 'AUTH', 'Invalid password for austinejames@gmail.com', 'FAILED', 'user', 13, '::1', 'Admin', '2026-07-14 17:58:21'),
(696, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:58:44'),
(697, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:58:57'),
(698, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 17:59:05'),
(699, 15, 'LOGOUT', 'AUTH', 'Austinee logged out', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 17:59:10'),
(700, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:59:19'),
(701, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 17:59:30'),
(702, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 17:59:43'),
(703, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:01:32'),
(704, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:01:49'),
(705, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:02:01'),
(706, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:02:13'),
(707, 15, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 7132027', 'FAILED', 'user', 15, '::1', 'Examinee', '2026-07-14 18:02:23'),
(708, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 18:02:29'),
(709, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:03:16'),
(710, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:03:29'),
(711, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:03:42'),
(712, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:04:08'),
(713, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 18:04:19'),
(714, 15, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 7132027', 'FAILED', 'user', 15, '::1', 'Examinee', '2026-07-14 18:05:45'),
(715, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 18:05:54'),
(716, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:10:16'),
(717, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:10:34'),
(718, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 18:10:44'),
(719, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:11:55'),
(720, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:12:18'),
(721, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:12:29'),
(722, 2, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"Comprehensive Examination\"', 'SUCCESS', 'exam', NULL, '::1', 'CCMD', '2026-07-14 18:14:14'),
(723, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:20:26'),
(724, 15, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 7132027', 'FAILED', 'user', 15, '::1', 'Examinee', '2026-07-14 18:20:36'),
(725, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-14 18:20:43'),
(726, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:21:47'),
(727, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:22:35'),
(728, 2, 'LOGIN_FAILED', 'AUTH', 'Invalid password for ccmd@rtc.com', 'FAILED', 'user', 2, '::1', 'CCMD', '2026-07-14 18:22:43'),
(729, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:22:51'),
(730, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-14 18:25:15'),
(731, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:25:31'),
(732, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:30:42'),
(733, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 18:36:53'),
(734, 1, 'ASSIGN_USER_BATCH', 'BATCH', 'Crisbell assigned to batch Charlie Enrollment (bulk import)', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 18:40:22'),
(735, 1, 'ASSIGN_USER_BATCH', 'BATCH', 'Rhick assigned to batch Charlie Enrollment (bulk import)', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 18:40:22'),
(736, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Crisbell removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 18:40:56'),
(737, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Rhick removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 18:40:58'),
(738, 1, 'ASSIGN_USER_BATCH', 'BATCH', 'Crisbell assigned to batch Charlie Enrollment (bulk import)', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 18:41:25'),
(739, 1, 'ASSIGN_USER_BATCH', 'BATCH', 'Rhick assigned to batch Charlie Enrollment (bulk import)', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 18:41:25'),
(740, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 22:17:26'),
(741, 1, 'CREATE_USER', 'USER', 'Bulk import created user James', 'SUCCESS', 'user', 16, '::1', 'Admin', '2026-07-14 22:24:08'),
(742, 1, 'ASSIGN_USER_BATCH', 'BATCH', 'James assigned to batch Charlie Enrollment (bulk import)', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 22:24:08'),
(743, 1, 'CREATE_USER', 'USER', 'Bulk import created user Manny', 'SUCCESS', 'user', 17, '::1', 'Admin', '2026-07-14 22:24:08'),
(744, 1, 'ASSIGN_USER_BATCH', 'BATCH', 'Manny assigned to batch Charlie Enrollment (bulk import)', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 22:24:08'),
(745, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Austinee removed from batch Alpha', 'SUCCESS', 'batch', 44, '::1', 'Admin', '2026-07-14 22:30:37'),
(746, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Crisbell removed from batch Alpha', 'SUCCESS', 'batch', 44, '::1', 'Admin', '2026-07-14 22:30:39'),
(747, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Edcel removed from batch Alpha', 'SUCCESS', 'batch', 44, '::1', 'Admin', '2026-07-14 22:30:41'),
(748, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Rhick removed from batch Alpha', 'SUCCESS', 'batch', 44, '::1', 'Admin', '2026-07-14 22:30:44'),
(749, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Crisbell removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 22:30:50'),
(750, 1, 'REMOVE_USER_BATCH', 'BATCH', 'James removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 22:30:52'),
(751, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Manny removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 22:30:54'),
(752, 1, 'REMOVE_USER_BATCH', 'BATCH', 'Rhick removed from batch Charlie Enrollment', 'SUCCESS', 'batch', 46, '::1', 'Admin', '2026-07-14 22:30:56'),
(753, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 22:36:56'),
(754, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 22:38:32'),
(755, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 22:43:43'),
(756, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-14 22:54:00'),
(757, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 22:54:13'),
(758, 1, 'EXPORT_REPORT', 'REPORT', 'Exported student-performance report CSV (3 rows)', 'SUCCESS', 'report', NULL, '::1', 'Admin', '2026-07-14 22:57:10'),
(759, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:07:32'),
(760, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:07:58'),
(761, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:14:03'),
(762, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:14:20'),
(763, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:23:57'),
(764, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:27:00'),
(765, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:34:31'),
(766, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:34:46'),
(767, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-14 23:44:16'),
(768, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:03:22'),
(769, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:03:50'),
(770, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:07:32'),
(771, 1, 'LOGIN_FAILED', 'AUTH', 'Invalid password for admin@rtc.com', 'FAILED', 'user', 1, '::1', 'Admin', '2026-07-15 00:09:22'),
(772, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:20:18'),
(773, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:20:30'),
(774, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:20:50'),
(775, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:22:10'),
(776, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:22:27'),
(777, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Exam Sample\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 00:24:15'),
(778, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Exam Sample', 'SUCCESS', 'exam', 30, '::1', 'Admin', '2026-07-15 00:24:15'),
(779, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Exam Sample', 'SUCCESS', 'exam', 30, '::1', 'Admin', '2026-07-15 00:24:15'),
(780, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Exam Sample\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 00:24:25'),
(781, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Exam Sample', 'SUCCESS', 'exam', 30, '::1', 'Admin', '2026-07-15 00:24:25'),
(782, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Exam Sample', 'SUCCESS', 'exam', 30, '::1', 'Admin', '2026-07-15 00:24:25'),
(783, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:25:42'),
(784, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:26:05'),
(785, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:26:19'),
(786, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:26:35'),
(787, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:26:57'),
(788, 1, 'UPDATE_EXAM_PARTICIPANTS', 'EXAM', '6 of 6 participants selected for exam Exam Sample', 'SUCCESS', 'exam', 30, '::1', 'Admin', '2026-07-15 00:27:18'),
(789, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:27:23'),
(790, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:27:39'),
(791, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:27:56'),
(792, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:28:11'),
(793, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Exam Sample\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 00:28:28'),
(794, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:28:32'),
(795, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:28:47'),
(796, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:29:04'),
(797, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:29:17'),
(798, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:29:30'),
(799, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:29:46'),
(800, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:37:08'),
(801, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:37:35'),
(802, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:39:20'),
(803, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:39:34'),
(804, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Exam Sample 2\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 00:40:45'),
(805, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Exam Sample 2', 'SUCCESS', 'exam', 31, '::1', 'Admin', '2026-07-15 00:40:45'),
(806, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Exam Sample 2', 'SUCCESS', 'exam', 31, '::1', 'Admin', '2026-07-15 00:40:45'),
(807, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Exam Sample 2\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 00:40:55'),
(808, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Exam Sample 2', 'SUCCESS', 'exam', 31, '::1', 'Admin', '2026-07-15 00:40:55'),
(809, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Exam Sample 2', 'SUCCESS', 'exam', 31, '::1', 'Admin', '2026-07-15 00:40:55'),
(810, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:41:07'),
(811, 8, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 02-2324-00092', 'FAILED', 'user', 8, '::1', 'Examinee', '2026-07-15 00:41:27'),
(812, 8, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 02-2324-00092', 'FAILED', 'user', 8, '::1', 'Examinee', '2026-07-15 00:41:37'),
(813, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:42:01'),
(814, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:42:27'),
(815, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:43:28'),
(816, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:43:44'),
(817, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:44:08'),
(818, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:44:28'),
(819, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:45:00'),
(820, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:45:32'),
(821, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:45:47'),
(822, 1, 'TOGGLE_RESPONSE_REVIEW', 'EXAM', 'Response review enabled for exam \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 00:46:07'),
(823, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Exam Sample 2\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 00:46:38'),
(824, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 00:46:44'),
(825, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:47:08'),
(826, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:47:45'),
(827, 8, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 02-2324-00092', 'FAILED', 'user', 8, '::1', 'Examinee', '2026-07-15 00:49:59');
INSERT INTO `tbl_audit_log` (`Log_ID`, `User_ID`, `Action`, `Module`, `Outcome`, `Status`, `Entity_Type`, `Entity_ID`, `IP_Address`, `User_Role`, `Timestamp`) VALUES
(828, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 00:50:09'),
(829, 2, 'LOGIN_FAILED', 'AUTH', 'Invalid password for ccmd@rtc.com', 'FAILED', 'user', 2, '::1', 'CCMD', '2026-07-15 00:51:44'),
(830, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:51:59'),
(831, 2, 'LOGOUT', 'AUTH', 'ccmd logged out', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 00:52:44'),
(832, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:06:27'),
(833, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:14:21'),
(834, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:14:41'),
(835, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:14:53'),
(836, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:15:25'),
(837, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:15:46'),
(838, 16, 'LOGIN', 'AUTH', '2024-00123 logged in', 'SUCCESS', 'user', 16, '::1', 'Examinee', '2026-07-15 01:15:57'),
(839, 16, 'PASSWORD_CHANGE', 'AUTH', 'James changed password', 'SUCCESS', 'user', 16, '::1', 'Examinee', '2026-07-15 01:16:27'),
(840, 16, 'LOGOUT', 'AUTH', 'James logged out', 'SUCCESS', 'user', 16, '::1', 'Examinee', '2026-07-15 01:17:28'),
(841, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:17:44'),
(842, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:17:56'),
(843, 17, 'LOGIN', 'AUTH', '2024-00124 logged in', 'SUCCESS', 'user', 17, '::1', 'Examinee', '2026-07-15 01:18:05'),
(844, 17, 'PASSWORD_CHANGE', 'AUTH', 'Manny changed password', 'SUCCESS', 'user', 17, '::1', 'Examinee', '2026-07-15 01:18:23'),
(845, 17, 'LOGOUT', 'AUTH', 'Manny logged out', 'SUCCESS', 'user', 17, '::1', 'Examinee', '2026-07-15 01:19:21'),
(846, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:19:49'),
(847, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:20:04'),
(848, 15, 'LOGIN', 'AUTH', '7132027 logged in', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-15 01:20:25'),
(849, 15, 'LOGOUT', 'AUTH', 'Austinee logged out', 'SUCCESS', 'user', 15, '::1', 'Examinee', '2026-07-15 01:21:33'),
(850, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 01:21:47'),
(851, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 01:22:05'),
(852, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:22:23'),
(853, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:23:13'),
(854, 9, 'LOGIN_FAILED', 'AUTH', 'Invalid password for 02-2324-00093', 'FAILED', 'user', 9, '::1', 'Examinee', '2026-07-15 01:23:24'),
(855, 9, 'LOGIN', 'AUTH', '02-2324-00093 logged in', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-15 01:23:31'),
(856, 9, 'LOGOUT', 'AUTH', 'Rhick logged out', 'SUCCESS', 'user', 9, '::1', 'Examinee', '2026-07-15 01:24:24'),
(857, 10, 'LOGIN', 'AUTH', '02-2324-00094 logged in', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-15 01:24:37'),
(858, 10, 'LOGOUT', 'AUTH', 'Edcel logged out', 'SUCCESS', 'user', 10, '::1', 'Examinee', '2026-07-15 01:25:29'),
(859, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:25:45'),
(860, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:27:00'),
(861, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:27:18'),
(862, 1, 'CLOSE_EXAM', 'EXAM', 'Exam \"Exam Sample 2\" closed', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 01:27:31'),
(863, 1, 'REOPEN_EXAM', 'EXAM', 'Exam \"Exam Sample 2\" reopened (0 session(s) reset, 1 void attempt(s) cleared)', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 01:27:38'),
(864, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:27:45'),
(865, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 01:28:00'),
(866, 8, 'LOGOUT', 'AUTH', 'Crisbell logged out', 'SUCCESS', 'user', 8, '::1', 'Examinee', '2026-07-15 01:28:46'),
(867, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 01:29:04'),
(868, 1, 'EXPORT_EXAM_RESPONSES', 'REPORT', 'Exported rankings CSV for exam \"Exam Sample 2\"', 'SUCCESS', 'exam', 31, '::1', 'Admin', '2026-07-15 01:32:40'),
(869, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Edcel exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 01:55:04'),
(870, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Edcel exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 01:55:21'),
(871, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Manny exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 01:58:11'),
(872, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'James exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 01:59:48'),
(873, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:01:20'),
(874, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Austinee exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:02:06'),
(875, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Austinee exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:03:03'),
(876, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Crisbell exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:04:13'),
(877, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Austinee exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:04:22'),
(878, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Crisbell exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:05:06'),
(879, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Austinee exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:05:53'),
(880, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:06:01'),
(881, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Crisbell exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:06:28'),
(882, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Austinee exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:07:23'),
(883, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:08:07'),
(884, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Austinee exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:08:40'),
(885, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Rhick exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:09:07'),
(886, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'James exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:10:02'),
(887, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Manny exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:10:32'),
(888, 1, 'VIEW_EXAM_RESPONSE', 'EXAM', 'Edcel exam response viewed for \"Exam Sample 2\"', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 02:10:57'),
(889, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 03:08:16'),
(890, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 03:27:55'),
(891, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 03:50:52'),
(892, 2, 'LOGIN', 'AUTH', 'CCMD login completed', 'SUCCESS', 'user', 2, '::1', 'CCMD', '2026-07-15 03:51:06'),
(893, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 11:08:18'),
(894, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 11:40:57'),
(895, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 13:05:25'),
(896, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 13:06:13'),
(897, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 22:28:00'),
(898, 1, 'UPDATE_USER', 'USER', 'Updated user James', 'SUCCESS', 'user', 16, '::1', 'Admin', '2026-07-15 22:28:26'),
(899, 1, 'UPDATE_USER', 'USER', 'Updated user Manny', 'SUCCESS', 'user', 17, '::1', 'Admin', '2026-07-15 22:28:37'),
(900, 1, 'UPDATE_USER', 'USER', 'Updated user Austinee', 'SUCCESS', 'user', 15, '::1', 'Admin', '2026-07-15 22:28:46'),
(901, 1, 'UPDATE_USER', 'USER', 'Updated user Crisbell', 'SUCCESS', 'user', 8, '::1', 'Admin', '2026-07-15 22:28:57'),
(902, 1, 'UPDATE_USER', 'USER', 'Updated user Rhick', 'SUCCESS', 'user', 9, '::1', 'Admin', '2026-07-15 22:29:05'),
(903, 1, 'UPDATE_USER', 'USER', 'Updated user Edcel', 'SUCCESS', 'user', 10, '::1', 'Admin', '2026-07-15 22:29:19'),
(904, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 22:35:30'),
(905, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 22:53:19'),
(906, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '172.20.10.14', 'Admin', '2026-07-15 22:57:45'),
(907, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '172.20.10.14', 'Admin', '2026-07-15 22:57:58'),
(908, 1, 'CREATE_EXAM', 'EXAM', 'Exam \"Exam Sample 3\" created', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 22:59:23'),
(909, 1, 'ASSIGN_EXAM_BATCH', 'EXAM', '1 batches assigned to exam Exam Sample 3', 'SUCCESS', 'exam', 32, '::1', 'Admin', '2026-07-15 22:59:24'),
(910, 1, 'ASSIGN_EXAM_QUESTIONS', 'EXAM', '10 questions assigned to exam Exam Sample 3', 'SUCCESS', 'exam', 32, '::1', 'Admin', '2026-07-15 22:59:24'),
(911, 1, 'UPDATE_EXAM_PARTICIPANTS', 'EXAM', '6 of 6 participants selected for exam Exam Sample 3', 'SUCCESS', 'exam', 32, '::1', 'Admin', '2026-07-15 22:59:26'),
(912, 1, 'UPDATE_EXAM', 'EXAM', 'Exam \"Exam Sample 3\" updated', 'SUCCESS', 'exam', NULL, '::1', 'Admin', '2026-07-15 22:59:33'),
(913, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 23:06:27'),
(914, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 23:13:08'),
(915, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '192.168.190.126', 'Admin', '2026-07-15 23:16:22'),
(916, 1, 'LOGOUT', 'AUTH', 'admin logged out', 'SUCCESS', 'user', 1, '192.168.190.126', 'Admin', '2026-07-15 23:16:56'),
(917, 8, 'LOGIN', 'AUTH', '02-2324-00092 logged in', 'SUCCESS', 'user', 8, '192.168.190.126', 'Examinee', '2026-07-15 23:17:13'),
(918, 1, 'LOGIN', 'AUTH', 'Admin login completed', 'SUCCESS', 'user', 1, '::1', 'Admin', '2026-07-15 23:59:24');

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
  `Date_Enrolled` datetime DEFAULT current_timestamp(),
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_batch`
--

INSERT INTO `tbl_batch` (`Batch_ID`, `Course_ID`, `Section_ID`, `User_ID`, `Batch_Name`, `Date_Started`, `Date_Ended`, `Status`, `Date_Enrolled`, `Is_Archived`, `Archived_At`) VALUES
(37, 1, NULL, NULL, '2026', '2026-07-09', '2027-01-09', 'Active', '2026-07-09 23:44:56', 0, NULL),
(43, 1, NULL, NULL, '2027', '2026-07-12', '2027-01-12', 'Active', '2026-07-12 18:26:11', 1, '2026-07-12 18:26:18'),
(44, 1, 27, NULL, 'Alpha', NULL, NULL, 'Active', '2026-07-12 23:26:19', 0, NULL),
(45, 1, 28, NULL, 'Bravo', NULL, NULL, 'Active', '2026-07-12 23:27:40', 0, NULL),
(46, 1, 31, NULL, 'Charlie Enrollment', NULL, NULL, 'Active', '2026-07-14 18:40:01', 0, NULL),
(47, 2, NULL, NULL, '2026', '2026-07-15', '2027-01-15', 'Active', '2026-07-15 11:22:50', 0, NULL),
(48, 2, 32, NULL, 'Alpha Enrollment', NULL, NULL, 'Active', '2026-07-15 11:22:57', 0, NULL);

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

--
-- Dumping data for table `tbl_cheating_incident`
--

INSERT INTO `tbl_cheating_incident` (`Incident_ID`, `Session_ID`, `Reason`, `Timestamp`, `Status`) VALUES
(1, 14, 'Refused or failed to enter fullscreen mode — Initial fullscreen request failed (Total violations: 1)', '2026-07-10 00:29:33', 'Open'),
(2, 15, 'Refused or failed to enter fullscreen mode — Initial fullscreen request failed (Total violations: 1)', '2026-07-10 02:42:29', 'Open'),
(3, 16, 'Exited fullscreen mode during exam — Trainee exited fullscreen during exam (Total violations: 8)', '2026-07-10 09:12:37', 'Open'),
(4, 17, 'Switched tab or minimized window during exam — Exam page hidden — possible tab switch (Total violations: 5)', '2026-07-10 09:45:36', 'Open'),
(5, 18, 'Exited fullscreen mode during exam — Trainee exited fullscreen during exam (Total violations: 5)', '2026-07-10 09:51:19', 'Open'),
(6, 19, 'Refused or failed to enter fullscreen mode — Initial fullscreen request failed (Total violations: 1)', '2026-07-10 10:37:11', 'Open'),
(7, 20, 'Switched tab or minimized window during exam — Exam page hidden — possible tab switch (Total violations: 5)', '2026-07-10 10:51:50', 'Open'),
(8, 21, 'Exited fullscreen mode during exam — Trainee exited fullscreen during exam (Total violations: 28)', '2026-07-11 02:36:29', 'Open'),
(9, 22, 'Switched tab or minimized window during exam — Exam page hidden — possible tab switch (Total violations: 10)', '2026-07-11 23:59:23', 'Open'),
(10, 23, 'Switched tab or minimized window during exam — Leaving the exam tab is not allowed. Return to the exam immediately. (Total violations: 30)', '2026-07-12 10:20:39', 'Open'),
(11, 24, 'Refused or failed to enter fullscreen mode — Initial fullscreen request failed (Total violations: 1)', '2026-07-14 17:47:09', 'Open'),
(12, 25, 'Latest: Switched tab or window — Leaving the exam tab is not allowed. Return to the exam immediately. | Total logged events: 48', '2026-07-14 18:21:32', 'Open'),
(13, 26, 'Latest: Exam page hidden — Leaving the exam page is not allowed. Return to the exam immediately. | Total logged events: 6', '2026-07-15 00:31:53', 'Open');

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
(1, 14, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-10 00:29:33'),
(2, 15, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-10 02:42:29'),
(3, 16, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-10 09:11:50'),
(4, 16, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-10 09:11:55'),
(5, 16, 'WINDOW_BLUR: Exam window lost focus', '2026-07-10 09:12:09'),
(6, 16, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-10 09:12:10'),
(7, 16, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-10 09:12:19'),
(8, 16, 'WINDOW_BLUR: Exam window lost focus', '2026-07-10 09:12:25'),
(9, 16, 'WINDOW_BLUR: Exam window lost focus', '2026-07-10 09:12:30'),
(10, 16, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-10 09:12:37'),
(11, 17, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-10 09:45:20'),
(12, 17, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-10 09:45:24'),
(13, 17, 'WINDOW_BLUR: Exam window lost focus', '2026-07-10 09:45:28'),
(14, 17, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-10 09:45:28'),
(15, 17, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-10 09:45:36'),
(16, 17, 'WINDOW_BLUR: Exam window lost focus', '2026-07-10 09:45:36'),
(17, 18, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-10 09:50:59'),
(18, 18, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-10 09:51:04'),
(19, 18, 'WINDOW_BLUR: Exam window lost focus', '2026-07-10 09:51:06'),
(20, 18, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-10 09:51:06'),
(21, 18, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-10 09:51:19'),
(22, 19, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-10 10:37:11'),
(23, 20, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-10 10:46:59'),
(24, 20, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-10 10:47:07'),
(25, 20, 'WINDOW_BLUR: Exam window lost focus', '2026-07-10 10:47:12'),
(26, 20, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-10 10:47:12'),
(27, 20, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-10 10:51:50'),
(28, 21, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-11 02:33:51'),
(29, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:34:33'),
(30, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:34:37'),
(31, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:34:37'),
(32, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:34:40'),
(33, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:34:45'),
(34, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:34:45'),
(35, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:34:47'),
(36, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:34:55'),
(37, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:34:55'),
(38, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:34:59'),
(39, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:35:05'),
(40, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:35:06'),
(41, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:35:06'),
(42, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:35:36'),
(43, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:35:36'),
(44, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:35:36'),
(45, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:35:48'),
(46, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:35:48'),
(47, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:35:54'),
(48, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:35:54'),
(49, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:35:57'),
(50, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:36:00'),
(51, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:36:00'),
(52, 21, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 02:36:07'),
(53, 21, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 02:36:07'),
(54, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:36:12'),
(55, 21, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 02:36:29'),
(56, 22, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-11 23:58:18'),
(57, 22, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 23:58:59'),
(58, 22, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 23:58:59'),
(59, 22, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 23:58:59'),
(60, 22, 'FULLSCREEN_EXIT: Trainee exited fullscreen during exam', '2026-07-11 23:59:06'),
(61, 22, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 23:59:06'),
(62, 22, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 23:59:06'),
(63, 22, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 23:59:12'),
(64, 22, 'WINDOW_BLUR: Exam window lost focus', '2026-07-11 23:59:23'),
(65, 22, 'TAB_SWITCH: Exam page hidden — possible tab switch', '2026-07-11 23:59:23'),
(66, 23, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-12 10:17:52'),
(67, 23, 'CONTEXT_MENU: Right-click is not allowed during a proctored exam.', '2026-07-12 10:18:11'),
(68, 23, 'CUT_ATTEMPT: Cutting exam content is not permitted during a proctored exam.', '2026-07-12 10:18:18'),
(69, 23, 'COPY_ATTEMPT: Copying exam content is not permitted during a proctored exam.', '2026-07-12 10:18:26'),
(70, 23, 'CUT_ATTEMPT: Cutting exam content is not permitted during a proctored exam.', '2026-07-12 10:18:28'),
(71, 23, 'COPY_ATTEMPT: Copying exam content is not permitted during a proctored exam.', '2026-07-12 10:18:35'),
(72, 23, 'PASTE_ATTEMPT: Pasting text into the exam is not allowed. You must answer without pasted content.', '2026-07-12 10:18:38'),
(73, 23, 'CUT_ATTEMPT: Cutting exam content is not permitted during a proctored exam.', '2026-07-12 10:18:42'),
(74, 23, 'COPY_ATTEMPT: Copying exam content is not permitted during a proctored exam.', '2026-07-12 10:18:48'),
(75, 23, 'PASTE_ATTEMPT: Pasting text into the exam is not allowed. You must answer without pasted content.', '2026-07-12 10:18:55'),
(76, 23, 'COPY_ATTEMPT: Copying exam content is not permitted during a proctored exam.', '2026-07-12 10:19:02'),
(77, 23, 'COPY_ATTEMPT: Copying exam content is not permitted during a proctored exam.', '2026-07-12 10:19:16'),
(78, 23, 'PASTE_ATTEMPT: Pasting text into the exam is not allowed. You must answer without pasted content.', '2026-07-12 10:19:17'),
(79, 23, 'CUT_ATTEMPT: Cutting exam content is not permitted during a proctored exam.', '2026-07-12 10:19:19'),
(80, 23, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-12 10:19:25'),
(81, 23, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-12 10:19:32'),
(82, 23, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-12 10:19:32'),
(83, 23, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-12 10:19:32'),
(84, 23, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-12 10:19:40'),
(85, 23, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-12 10:19:40'),
(86, 23, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-12 10:19:48'),
(87, 23, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-12 10:19:48'),
(88, 23, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-12 10:19:55'),
(89, 23, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-12 10:19:55'),
(90, 23, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-12 10:20:06'),
(91, 23, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-12 10:20:10'),
(92, 23, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-12 10:20:32'),
(93, 23, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-12 10:20:36'),
(94, 23, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-12 10:20:36'),
(95, 23, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-12 10:20:39'),
(96, 24, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-14 17:47:09'),
(97, 25, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-14 17:59:49'),
(98, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:00:10'),
(99, 25, 'COPY_ATTEMPT: Copying exam content is not permitted during a proctored exam.', '2026-07-14 18:00:40'),
(100, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:00:44'),
(101, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:00:47'),
(102, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:00:52'),
(103, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:00:58'),
(104, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:01:00'),
(105, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:01:00'),
(106, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:01:03'),
(107, 25, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-14 18:02:32'),
(108, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:02:45'),
(109, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:02:47'),
(110, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:02:47'),
(111, 25, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-14 18:04:23'),
(112, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:04:28'),
(113, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:04:30'),
(114, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:04:41'),
(115, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:04:43'),
(116, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:05:22'),
(117, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:05:22'),
(118, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:05:28'),
(119, 25, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-14 18:09:41'),
(120, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:09:46'),
(121, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:09:53'),
(122, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:09:53'),
(123, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:09:58'),
(124, 25, 'FULLSCREEN_DENIED: Initial fullscreen request failed', '2026-07-14 18:10:47'),
(125, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:11:12'),
(126, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:11:18'),
(127, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:11:18'),
(128, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:11:39'),
(129, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:11:40'),
(130, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:11:41'),
(131, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:21:02'),
(132, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:21:02'),
(133, 25, 'VISIBILITY_HIDDEN: Leaving the exam page is not allowed. Return to the exam immediately.', '2026-07-14 18:21:08'),
(134, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:21:13'),
(135, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:21:13'),
(136, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:21:15'),
(137, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:21:19'),
(138, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:21:19'),
(139, 25, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-14 18:21:20'),
(140, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:21:23'),
(141, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:21:23'),
(142, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:21:27'),
(143, 25, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-14 18:21:31'),
(144, 25, 'TAB_SWITCH: Leaving the exam tab is not allowed. Return to the exam immediately.', '2026-07-14 18:21:32'),
(145, 26, 'FULLSCREEN_EXIT: You exited fullscreen mode. Return to fullscreen to continue your exam.', '2026-07-15 00:30:18'),
(146, 26, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-15 00:30:32'),
(147, 26, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-15 00:30:40'),
(148, 26, 'WINDOW_BLUR: The exam window lost focus. Keep this exam in the foreground at all times.', '2026-07-15 00:31:13'),
(149, 26, 'VISIBILITY_HIDDEN: Leaving the exam page is not allowed. Return to the exam immediately.', '2026-07-15 00:31:37'),
(150, 26, 'VISIBILITY_HIDDEN: Leaving the exam page is not allowed. Return to the exam immediately.', '2026-07-15 00:31:53');

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
(51, 12, 'To entertain guests', 0),
(52, 12, 'To maintain order, respect, and professionalism', 1),
(53, 12, 'To shorten the program', 0),
(54, 12, 'To impress the audience', 0),
(55, 12, 'To brag', 0),
(56, 13, 'The higher-ranking person', 0),
(57, 13, 'The younger person', 0),
(58, 13, 'The lower-ranking person to the higher-ranking person', 1),
(59, 13, 'The person who arrived first', 0),
(60, 13, 'Doesn\'t matter', 0),
(61, 14, 'Interrupting conversations', 0),
(62, 14, 'Using your mobile phone during speeches', 0),
(63, 14, 'Greeting guests politely and respectfully', 1),
(64, 14, 'Speaking loudly', 0),
(65, 14, 'Sleep', 0),
(66, 15, 'Continue walking', 0),
(67, 15, 'Stand at attention and render the proper salute', 1),
(68, 15, 'Sit quietly', 0),
(69, 15, 'Clap after the anthem', 0),
(70, 15, 'Just act like nothing', 0),
(71, 16, 'By first name', 0),
(72, 16, 'By nickname', 0),
(73, 16, 'By rank and surname', 1),
(74, 16, 'By last name only', 0),
(75, 16, 'Doesn\'t matter', 0),
(76, 17, 'Talking while chewing', 0),
(77, 17, 'Waiting for everyone to be served before eating', 1),
(78, 17, 'Placing elbows on the table while eating', 0),
(79, 17, 'Using your phone throughout the meal', 0),
(80, 17, 'Shouting while in a formal dinner ', 0),
(81, 18, 'Informal language', 0),
(82, 18, 'Proper salutations and professional tone', 1),
(83, 18, 'Slang expressions', 0),
(84, 18, 'Abbreviations only', 0),
(85, 18, 'Physical touch', 0),
(86, 19, 'Ignore the arrival', 0),
(87, 19, 'Continue conversations', 0),
(88, 19, 'Stand and acknowledge the guest respectfully', 1),
(89, 19, 'Leave the room', 0),
(90, 19, 'Doesn\'t matter', 0),
(91, 20, 'Gain personal fame', 0),
(92, 20, 'Build positive relationships and public trust', 1),
(93, 20, 'Avoid responsibilities', 0),
(94, 20, 'Increase social media followers', 0),
(95, 20, 'Got promoted', 0),
(96, 21, 'Being punctual, courteous, and respectful', 1),
(97, 21, 'Arguing with clients', 0),
(98, 21, 'Ignoring complaints', 0),
(99, 21, 'Showing favoritism', 0),
(100, 21, 'Doesn\'t matter', 0),
(101, 22, 'Cebu', 0),
(102, 22, 'Davao', 0),
(103, 22, 'Manila', 1),
(104, 22, 'Iloilo', 0),
(105, 22, 'Baguio', 0),
(106, 23, 'Cebu', 0),
(107, 23, 'Davao', 0),
(108, 23, 'Manila', 1),
(109, 23, 'Iloilo', 0),
(110, 23, 'Baguio', 0),
(156, 33, 'HEHEH', 1),
(157, 33, 'hohoho', 0),
(158, 33, 'jjsjssjsjsjsjs', 0),
(159, 33, 'HAHAHA', 0),
(160, 33, 'hahahaha', 0),
(161, 34, 'JDHDN', 1),
(162, 34, 'JJJJ', 0),
(163, 34, 'NH', 0),
(164, 34, 'H', 0),
(165, 34, 'H', 0),
(166, 35, 'DJJCFNCH', 1),
(167, 35, 'JCJDNDJ', 0),
(168, 35, 'JCNDNDJ', 0),
(169, 35, 'JCJDNDJ', 0),
(170, 35, 'JCJDD', 0);

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
(1, 1, 'Public Safety Basic Recruit Course (PSBRC)', 'The Public Safety Basic Recruit Course (PSBRC) is a mandatory, 6-month intensive pre-service training program for newly appointed police non-commissioned officers (like Patrolmen). Administered by the Philippine Public Safety College (PPSC) through the National Police Training Institute (NPTI), it transitions recruits from a civilian mindset to a regimented, highly disciplined police service.', '6 months', 0, NULL),
(2, 1, 'Public Safety Junior Leadership Course (PSJLC)', 'The Public Safety Junior Leadership Course (PSJLC) is a mandatory 10 to 11-week career program for junior Police Non-Commissioned Officers (PNCOs) in the Philippine National Police (PNP). It is designed to equip officers with the fundamental management, leadership, and operational skills required to handle junior key positions and team-building assignments.', '3 months', 0, NULL),
(5, 1, 'Public Safety Officers Candidate Course (PSOCC)', 'The Public Safety Officers Candidate Course (PSOCC) is a mandatory 14-week career training program for Police Non-Commissioned Officers (PNCOs) within the Philippine National Police (PNP) who are aspiring for promotion to the rank of Police Inspector. Administered by the Philippine Public Safety College (PPSC), the course is designed to equip junior officers with the fundamental leadership skills, specialized operational knowledge, and values necessary for higher management and team-building responsibilities.', '6 months', 0, NULL),
(8, 1, 'Public Safety Senior Leadership Course (PSSLC)', 'The Public Safety Senior Leadership Course (PSSLC) is a mandatory 3.5-month career course for senior non-commissioned officers (SPO3 to SPO4) in the Philippine National Police (PNP). It equips officers with the management and leadership capabilities required for assuming mid-level supervisory and administrative responsibilities.', '6 months', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_exam`
--

CREATE TABLE `tbl_exam` (
  `Exam_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL COMMENT 'Created by',
  `Subject_ID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Exam_Type` enum('PRE_TEST','MODULAR','COMPREHENSIVE','POST_TEST') NOT NULL DEFAULT 'MODULAR',
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
(11, 1, 7, 'Pre-Test', '', '2026-07-09 22:14:00', '2026-07-11 22:14:00', 30, 50.00, 'Closed', 1, NULL, 'FRVZBG', '2026-07-09 22:15:44', 0, 1, '2026-07-10 00:23:33', 0),
(13, 1, 7, 'Modular Examination', '', '2026-07-09 22:20:00', '2026-07-11 22:20:00', 30, 50.00, 'Draft', 1, NULL, 'B63A8B', '2026-07-09 22:20:48', 0, 1, '2026-07-14 17:25:43', 0),
(14, 1, 7, 'Comprehensive Examination', '', '2026-07-09 22:22:00', '2026-07-11 22:22:00', 30, 50.00, 'Draft', 1, NULL, 'RDFJUG', '2026-07-09 22:22:43', 0, 1, '2026-07-10 00:23:46', 0),
(15, 1, 7, 'Post-Test', '', '2026-07-09 22:23:00', '2026-07-11 22:23:00', 30, 50.00, 'Closed', 1, NULL, '5SEPV8', '2026-07-09 22:23:21', 0, 1, '2026-07-10 00:23:40', 0),
(16, 1, 7, 'Pre-Test', '', '2026-07-10 00:28:00', '2026-07-10 16:28:00', 30, 50.00, 'Closed', 1, NULL, 'YGX7G4', '2026-07-10 00:28:48', 0, 1, '2026-07-14 17:27:15', 0),
(17, 1, 10, 'Sample', '', '2026-07-10 02:41:00', '2026-07-11 02:41:00', 10, 50.00, 'Closed', 1, NULL, 'N2WFMM', '2026-07-10 02:42:02', 0, 1, '2026-07-13 01:29:49', 0),
(18, 1, 7, 'Comprehensive Examination', '', '2026-07-14 17:33:00', '2026-07-14 18:10:00', 30, 50.00, 'Closed', 1, NULL, '6MS24N', '2026-07-11 23:51:10', 1, 0, NULL, 1),
(19, 1, 7, 'hgdg', '', '2026-07-12 10:16:00', '2026-07-13 01:28:00', 60, 50.00, 'Closed', 0, NULL, '7EFHD9', '2026-07-10 10:35:15', 0, 1, '2026-07-14 17:27:05', 0),
(20, 1, 7, 'Pre-Test', '', '2026-07-11 09:53:00', '2026-07-12 09:53:00', 30, 50.00, 'Draft', 0, NULL, 'VESQZ6', '2026-07-11 09:54:46', 0, 1, '2026-07-14 17:25:31', 0),
(21, 1, 7, 'Pre-Test', '', '2026-07-11 09:55:00', '2026-07-12 09:55:00', 60, 50.00, 'Draft', 0, NULL, 'XAMT76', '2026-07-11 09:56:11', 0, 1, '2026-07-14 17:25:26', 0),
(22, 1, 7, 'Pre-Test', '', '2026-07-11 09:59:00', '2026-07-12 09:59:00', 10, 50.00, 'Draft', 1, NULL, '8BMQX3', '2026-07-11 10:00:09', 0, 1, '2026-07-14 17:25:22', 0),
(23, 1, 7, 'Pre-Test', '', '2026-07-11 10:03:00', '2026-07-12 10:03:00', 30, 50.00, 'Draft', 0, NULL, 'AGEPSH', '2026-07-11 10:05:07', 0, 1, '2026-07-14 17:25:18', 0),
(24, 1, 7, 'Exam', '', '2026-07-11 10:08:00', '2026-07-12 10:08:00', 30, 50.00, 'Draft', 1, NULL, '6QB2RQ', '2026-07-11 10:09:59', 0, 1, '2026-07-14 17:25:56', 0),
(25, 1, 10, 'Pre-Test', '', '2026-07-12 12:23:00', '2026-07-13 12:23:00', 60, 50.00, 'Draft', 0, NULL, 'MF2A8V', '2026-07-12 12:23:45', 0, 1, '2026-07-14 17:25:13', 0),
(26, 1, 10, 'Pre-Test', '', '2026-07-12 22:21:00', '2026-07-13 22:21:00', 30, 50.00, 'Draft', 1, NULL, 'XZPBJ5', '2026-07-12 22:23:12', 0, 1, '2026-07-14 17:25:09', 0),
(27, 1, 7, 'HHHH', '', '2026-07-12 22:27:00', '2026-07-13 22:27:00', 50, 50.00, 'Draft', 0, NULL, '4UZYF3', '2026-07-12 22:27:31', 0, 1, '2026-07-14 17:25:50', 0),
(28, 1, 5, 'Testing', '', '2026-07-12 23:59:00', '2026-07-13 23:59:00', 30, 50.00, 'Draft', 1, NULL, 'X2G9MR', '2026-07-13 00:01:12', 0, 1, '2026-07-14 17:26:02', 0),
(29, 1, 10, 'sample1', '', '2026-07-13 01:33:00', '2026-07-14 01:33:00', 30, 50.00, 'Draft', 1, NULL, 'NGJWV4', '2026-07-13 01:33:46', 0, 1, '2026-07-14 17:25:05', 0),
(30, 1, 7, 'Exam Sample', '', '2026-07-15 00:23:00', '2026-07-16 00:23:00', 30, 50.00, 'Published', 0, NULL, '8JMS6Z', '2026-07-15 00:24:15', 0, 0, NULL, 0),
(31, 1, 7, 'Exam Sample 2', '', '2026-07-15 01:27:38', '2026-07-16 00:40:00', 30, 50.00, 'Published', 1, NULL, '6Z8JUZ', '2026-07-15 00:40:45', 0, 0, NULL, 1),
(32, 1, 7, 'Exam Sample 3', '', '2026-07-15 22:58:00', '2026-07-16 22:58:00', 30, 50.00, 'Published', 1, NULL, 'BQH38H', '2026-07-15 22:59:23', 0, 0, NULL, 0);

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
(70, 28, 44, '2026-07-13 00:01:16'),
(71, 19, 44, '2026-07-13 00:43:24'),
(72, 29, 44, '2026-07-13 01:33:46'),
(73, 25, 44, '2026-07-13 01:48:26'),
(74, 23, 44, '2026-07-13 01:48:54'),
(75, 18, 44, '2026-07-13 01:49:19'),
(76, 24, 44, '2026-07-13 01:52:22'),
(77, 27, 44, '2026-07-13 01:52:22'),
(78, 16, 44, '2026-07-13 01:52:22'),
(79, 20, 44, '2026-07-13 01:52:22'),
(80, 21, 44, '2026-07-13 01:52:22'),
(81, 22, 44, '2026-07-13 01:52:22'),
(82, 26, 44, '2026-07-13 01:52:22'),
(83, 13, 44, '2026-07-13 01:53:09'),
(85, 30, 44, '2026-07-15 00:24:25'),
(87, 31, 44, '2026-07-15 00:40:55'),
(89, 32, 44, '2026-07-15 22:59:33');

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
(1, 16, 8, '::1', 1, '2026-07-10 00:29:33'),
(2, 17, 8, '::1', 1, '2026-07-10 02:42:29'),
(3, 18, 8, '::1', 1, '2026-07-10 09:11:50'),
(4, 16, 9, '::1', 1, '2026-07-10 09:45:20'),
(5, 17, 10, '::1', 1, '2026-07-10 09:50:59'),
(6, 19, 8, '::1', 1, '2026-07-10 10:37:11'),
(7, 19, 9, '::1', 1, '2026-07-10 10:46:59'),
(8, 17, 9, '::1', 1, '2026-07-11 02:33:51'),
(9, 18, 9, '::1', 1, '2026-07-11 23:58:18'),
(10, 19, 10, '::1', 1, '2026-07-12 10:17:52'),
(11, 18, 10, '::1', 1, '2026-07-14 17:47:09'),
(13, 18, 15, '::1', 1, '2026-07-14 17:59:48'),
(14, 30, 8, '::1', 1, '2026-07-15 00:30:03'),
(15, 31, 8, '::1', 1, '2026-07-15 00:47:14'),
(16, 31, 16, '::1', 1, '2026-07-15 01:16:42'),
(17, 31, 17, '::1', 1, '2026-07-15 01:18:37'),
(18, 31, 15, '::1', 1, '2026-07-15 01:20:37'),
(19, 31, 9, '::1', 1, '2026-07-15 01:23:46'),
(20, 31, 10, '::1', 1, '2026-07-15 01:24:43'),
(21, 31, 8, '::1', 1, '2026-07-15 01:28:10');

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
(1, 16, 8, '2026-07-11 09:45:34'),
(2, 16, 10, '2026-07-11 09:45:34'),
(3, 16, 9, '2026-07-11 09:45:34'),
(4, 17, 8, '2026-07-11 09:45:34'),
(5, 17, 10, '2026-07-11 09:45:34'),
(6, 17, 9, '2026-07-11 09:45:34'),
(13, 20, 8, '2026-07-11 09:54:46'),
(14, 20, 10, '2026-07-11 09:54:46'),
(15, 20, 9, '2026-07-11 09:54:46'),
(16, 21, 8, '2026-07-11 09:56:11'),
(17, 21, 10, '2026-07-11 09:56:11'),
(18, 21, 9, '2026-07-11 09:56:11'),
(19, 22, 8, '2026-07-11 10:00:09'),
(20, 22, 10, '2026-07-11 10:00:09'),
(21, 22, 9, '2026-07-11 10:00:09'),
(22, 23, 8, '2026-07-11 10:05:08'),
(23, 23, 10, '2026-07-11 10:05:08'),
(24, 23, 9, '2026-07-11 10:05:08'),
(25, 24, 8, '2026-07-11 10:09:59'),
(26, 24, 10, '2026-07-11 10:09:59'),
(27, 24, 9, '2026-07-11 10:09:59'),
(28, 25, 8, '2026-07-12 12:23:45'),
(29, 25, 10, '2026-07-12 12:23:45'),
(30, 25, 9, '2026-07-12 12:23:45'),
(31, 26, 8, '2026-07-12 22:23:12'),
(32, 26, 10, '2026-07-12 22:23:12'),
(33, 26, 9, '2026-07-12 22:23:12'),
(34, 27, 8, '2026-07-12 22:27:31'),
(35, 27, 10, '2026-07-12 22:27:31'),
(36, 27, 9, '2026-07-12 22:27:31'),
(40, 28, 8, '2026-07-13 00:01:15'),
(41, 28, 9, '2026-07-13 00:01:15'),
(42, 28, 10, '2026-07-13 00:01:15'),
(43, 19, 8, '2026-07-13 00:43:31'),
(44, 19, 9, '2026-07-13 00:43:31'),
(45, 19, 10, '2026-07-13 00:43:31'),
(46, 29, 8, '2026-07-13 01:33:46'),
(47, 29, 10, '2026-07-13 01:33:46'),
(48, 29, 9, '2026-07-13 01:33:46'),
(52, 13, 8, '2026-07-13 01:53:11'),
(53, 13, 9, '2026-07-13 01:53:11'),
(54, 13, 10, '2026-07-13 01:53:11'),
(59, 18, 8, '2026-07-14 17:40:58'),
(60, 18, 9, '2026-07-14 17:40:58'),
(61, 18, 10, '2026-07-14 17:40:58'),
(62, 18, 15, '2026-07-14 17:40:58'),
(69, 30, 8, '2026-07-15 00:27:18'),
(70, 30, 9, '2026-07-15 00:27:18'),
(71, 30, 10, '2026-07-15 00:27:18'),
(72, 30, 15, '2026-07-15 00:27:18'),
(73, 30, 16, '2026-07-15 00:27:18'),
(74, 30, 17, '2026-07-15 00:27:18'),
(75, 31, 15, '2026-07-15 00:40:45'),
(76, 31, 8, '2026-07-15 00:40:45'),
(77, 31, 10, '2026-07-15 00:40:45'),
(78, 31, 16, '2026-07-15 00:40:45'),
(79, 31, 17, '2026-07-15 00:40:45'),
(80, 31, 9, '2026-07-15 00:40:45'),
(87, 32, 8, '2026-07-15 22:59:26'),
(88, 32, 9, '2026-07-15 22:59:26'),
(89, 32, 10, '2026-07-15 22:59:26'),
(90, 32, 15, '2026-07-15 22:59:26'),
(91, 32, 16, '2026-07-15 22:59:26'),
(92, 32, 17, '2026-07-15 22:59:26');

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
(61, 11, 12, 1, '2026-07-09 22:46:26'),
(62, 11, 13, 2, '2026-07-09 22:46:26'),
(63, 11, 14, 3, '2026-07-09 22:46:26'),
(64, 11, 15, 4, '2026-07-09 22:46:26'),
(65, 11, 16, 5, '2026-07-09 22:46:26'),
(66, 11, 17, 6, '2026-07-09 22:46:26'),
(67, 11, 18, 7, '2026-07-09 22:46:26'),
(68, 11, 19, 8, '2026-07-09 22:46:26'),
(69, 11, 20, 9, '2026-07-09 22:46:26'),
(70, 11, 21, 10, '2026-07-09 22:46:26'),
(71, 15, 12, 1, '2026-07-09 22:46:48'),
(72, 15, 13, 2, '2026-07-09 22:46:48'),
(73, 15, 14, 3, '2026-07-09 22:46:48'),
(74, 15, 15, 4, '2026-07-09 22:46:48'),
(75, 15, 16, 5, '2026-07-09 22:46:48'),
(76, 15, 17, 6, '2026-07-09 22:46:48'),
(77, 15, 18, 7, '2026-07-09 22:46:48'),
(78, 15, 19, 8, '2026-07-09 22:46:48'),
(79, 15, 20, 9, '2026-07-09 22:46:48'),
(80, 15, 21, 10, '2026-07-09 22:46:48'),
(81, 16, 12, 1, '2026-07-10 00:28:48'),
(82, 16, 13, 2, '2026-07-10 00:28:48'),
(83, 16, 14, 3, '2026-07-10 00:28:48'),
(84, 16, 15, 4, '2026-07-10 00:28:48'),
(85, 16, 16, 5, '2026-07-10 00:28:48'),
(86, 16, 17, 6, '2026-07-10 00:28:48'),
(87, 16, 18, 7, '2026-07-10 00:28:48'),
(88, 16, 19, 8, '2026-07-10 00:28:48'),
(89, 16, 20, 9, '2026-07-10 00:28:48'),
(90, 16, 21, 10, '2026-07-10 00:28:48'),
(91, 17, 22, 1, '2026-07-10 02:42:03'),
(92, 17, 23, 2, '2026-07-10 02:42:03'),
(105, 19, 12, 1, '2026-07-10 10:35:15'),
(106, 19, 13, 2, '2026-07-10 10:35:15'),
(107, 19, 14, 3, '2026-07-10 10:35:15'),
(108, 19, 15, 4, '2026-07-10 10:35:15'),
(109, 19, 16, 5, '2026-07-10 10:35:15'),
(110, 22, 12, 1, '2026-07-11 10:00:09'),
(111, 22, 13, 2, '2026-07-11 10:00:09'),
(112, 22, 14, 3, '2026-07-11 10:00:09'),
(113, 22, 15, 4, '2026-07-11 10:00:09'),
(114, 22, 16, 5, '2026-07-11 10:00:09'),
(115, 22, 17, 6, '2026-07-11 10:00:09'),
(116, 22, 18, 7, '2026-07-11 10:00:09'),
(117, 22, 19, 8, '2026-07-11 10:00:09'),
(118, 22, 20, 9, '2026-07-11 10:00:09'),
(119, 22, 21, 10, '2026-07-11 10:00:09'),
(120, 23, 12, 1, '2026-07-11 10:05:08'),
(121, 23, 13, 2, '2026-07-11 10:05:08'),
(122, 23, 14, 3, '2026-07-11 10:05:08'),
(123, 23, 15, 4, '2026-07-11 10:05:08'),
(124, 23, 16, 5, '2026-07-11 10:05:08'),
(125, 23, 17, 6, '2026-07-11 10:05:08'),
(126, 23, 18, 7, '2026-07-11 10:05:08'),
(127, 23, 19, 8, '2026-07-11 10:05:08'),
(128, 23, 20, 9, '2026-07-11 10:05:08'),
(129, 23, 21, 10, '2026-07-11 10:05:08'),
(130, 24, 12, 1, '2026-07-11 10:09:59'),
(131, 24, 13, 2, '2026-07-11 10:09:59'),
(132, 24, 14, 3, '2026-07-11 10:09:59'),
(133, 24, 15, 4, '2026-07-11 10:09:59'),
(134, 24, 16, 5, '2026-07-11 10:09:59'),
(135, 24, 17, 6, '2026-07-11 10:09:59'),
(136, 24, 18, 7, '2026-07-11 10:09:59'),
(137, 24, 19, 8, '2026-07-11 10:09:59'),
(138, 24, 20, 9, '2026-07-11 10:09:59'),
(139, 24, 21, 10, '2026-07-11 10:09:59'),
(173, 18, 12, 1, '2026-07-12 11:59:25'),
(174, 18, 13, 2, '2026-07-12 11:59:26'),
(175, 18, 14, 3, '2026-07-12 11:59:26'),
(176, 18, 15, 4, '2026-07-12 11:59:26'),
(177, 18, 16, 5, '2026-07-12 11:59:26'),
(178, 18, 17, 6, '2026-07-12 11:59:26'),
(179, 18, 18, 7, '2026-07-12 11:59:26'),
(180, 18, 19, 8, '2026-07-12 11:59:26'),
(181, 18, 20, 9, '2026-07-12 11:59:26'),
(182, 18, 21, 10, '2026-07-12 11:59:26'),
(187, 25, 22, 1, '2026-07-12 12:25:22'),
(188, 25, 23, 2, '2026-07-12 12:25:22'),
(195, 28, 33, 1, '2026-07-13 00:05:18'),
(196, 28, 34, 2, '2026-07-13 00:05:18'),
(197, 28, 35, 3, '2026-07-13 00:05:18'),
(198, 29, 22, 1, '2026-07-13 01:33:46'),
(199, 29, 23, 2, '2026-07-13 01:33:46'),
(210, 30, 12, 1, '2026-07-15 00:24:25'),
(211, 30, 13, 2, '2026-07-15 00:24:25'),
(212, 30, 14, 3, '2026-07-15 00:24:25'),
(213, 30, 15, 4, '2026-07-15 00:24:25'),
(214, 30, 16, 5, '2026-07-15 00:24:25'),
(215, 30, 17, 6, '2026-07-15 00:24:25'),
(216, 30, 18, 7, '2026-07-15 00:24:25'),
(217, 30, 19, 8, '2026-07-15 00:24:25'),
(218, 30, 20, 9, '2026-07-15 00:24:25'),
(219, 30, 21, 10, '2026-07-15 00:24:25'),
(230, 31, 12, 1, '2026-07-15 00:40:55'),
(231, 31, 13, 2, '2026-07-15 00:40:55'),
(232, 31, 14, 3, '2026-07-15 00:40:55'),
(233, 31, 15, 4, '2026-07-15 00:40:55'),
(234, 31, 16, 5, '2026-07-15 00:40:55'),
(235, 31, 17, 6, '2026-07-15 00:40:55'),
(236, 31, 18, 7, '2026-07-15 00:40:55'),
(237, 31, 19, 8, '2026-07-15 00:40:55'),
(238, 31, 20, 9, '2026-07-15 00:40:55'),
(239, 31, 21, 10, '2026-07-15 00:40:55'),
(240, 32, 12, 1, '2026-07-15 22:59:24'),
(241, 32, 13, 2, '2026-07-15 22:59:24'),
(242, 32, 14, 3, '2026-07-15 22:59:24'),
(243, 32, 15, 4, '2026-07-15 22:59:24'),
(244, 32, 16, 5, '2026-07-15 22:59:24'),
(245, 32, 17, 6, '2026-07-15 22:59:24'),
(246, 32, 18, 7, '2026-07-15 22:59:24'),
(247, 32, 19, 8, '2026-07-15 22:59:24'),
(248, 32, 20, 9, '2026-07-15 22:59:24'),
(249, 32, 21, 10, '2026-07-15 22:59:24');

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
(14, 16, 8, '2026-07-10 00:29:33', '2026-07-10 00:30:43'),
(15, 17, 8, '2026-07-10 02:42:29', '2026-07-10 02:42:46'),
(16, 18, 8, '2026-07-10 09:11:50', '2026-07-10 09:13:13'),
(17, 16, 9, '2026-07-10 09:45:20', '2026-07-10 09:46:22'),
(18, 17, 10, '2026-07-10 09:50:59', '2026-07-10 09:51:31'),
(19, 19, 8, '2026-07-10 10:37:11', '2026-07-10 10:37:43'),
(20, 19, 9, '2026-07-10 10:46:59', '2026-07-10 12:27:36'),
(21, 17, 9, '2026-07-11 02:33:51', '2026-07-11 02:36:54'),
(22, 18, 9, '2026-07-11 23:58:18', '2026-07-14 17:35:16'),
(23, 19, 10, '2026-07-12 10:17:52', '2026-07-13 00:45:25'),
(24, 18, 10, '2026-07-14 17:47:09', '2026-07-14 17:47:55'),
(25, 18, 15, '2026-07-14 17:59:48', NULL),
(26, 30, 8, '2026-07-15 00:30:03', '2026-07-15 00:36:45'),
(28, 31, 16, '2026-07-15 01:16:42', '2026-07-15 01:17:18'),
(29, 31, 17, '2026-07-15 01:18:37', '2026-07-15 01:19:13'),
(30, 31, 15, '2026-07-15 01:20:37', '2026-07-15 01:21:27'),
(31, 31, 9, '2026-07-15 01:23:46', '2026-07-15 01:24:18'),
(32, 31, 10, '2026-07-15 01:24:43', '2026-07-15 01:25:22'),
(33, 31, 8, '2026-07-15 01:28:10', '2026-07-15 01:28:40');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_module`
--

CREATE TABLE `tbl_module` (
  `Module_ID` int(11) NOT NULL,
  `Course_ID` int(11) NOT NULL,
  `Module_Name` varchar(200) NOT NULL,
  `Module_Code` varchar(50) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Sort_Order` int(11) NOT NULL DEFAULT 0,
  `Date_Created` datetime NOT NULL DEFAULT current_timestamp(),
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_module`
--

INSERT INTO `tbl_module` (`Module_ID`, `Course_ID`, `Module_Name`, `Module_Code`, `Description`, `Sort_Order`, `Date_Created`, `Is_Archived`, `Archived_At`) VALUES
(1, 1, 'Module 1', NULL, '', 1, '2026-07-11 00:02:41', 0, NULL),
(2, 2, 'Module 1', 'MOD1', NULL, 1, '2026-07-11 00:02:41', 1, '2026-07-11 01:53:55'),
(3, 5, 'Module 1', 'MOD1', NULL, 1, '2026-07-11 00:02:41', 1, '2026-07-12 17:46:24'),
(4, 8, 'Module 1', 'MOD1', NULL, 1, '2026-07-11 00:02:41', 1, '2026-07-12 17:46:30'),
(5, 1, 'Module 2', NULL, '', 2, '2026-07-11 00:36:01', 1, '2026-07-12 17:46:18');

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
  `Is_Published` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = exam draft only, promoted to bank on exam publish',
  `Added_Date` datetime NOT NULL DEFAULT current_timestamp(),
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_question_bank`
--

INSERT INTO `tbl_question_bank` (`Question_ID`, `Subject_ID`, `User_ID`, `Question_Text`, `Question_Type`, `Is_Published`, `Added_Date`, `Is_Archived`, `Archived_At`) VALUES
(12, 7, 1, 'What is the primary purpose of protocol during official ceremonies?', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(13, 7, 1, 'When introducing two people, who should generally be introduced first?', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(14, 7, 1, 'Which of the following is considered proper social etiquette during formal events?', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(15, 7, 1, 'During the Philippine National Anthem, a uniformed police officer should:', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(16, 7, 1, 'Which is the most appropriate way to address a senior officer?', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(17, 7, 1, 'Which of the following demonstrates good table manners at a formal dinner?', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(18, 7, 1, 'In official correspondence, courtesy and respect are shown through:', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(19, 7, 1, 'What is the proper action when a guest of honor enters the venue?', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(20, 7, 1, 'Social amenities primarily help an officer to:', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(21, 7, 1, 'Which of the following best reflects professionalism in public service?', 'Multiple Choice', 1, '2026-07-09 22:35:04', 0, NULL),
(22, 10, 1, 'What is the capital of the Philippines?', 'Multiple Choice', 1, '2026-07-10 02:40:59', 1, '2026-07-14 17:30:38'),
(23, 10, 1, 'What is the capital of the Philippines?', 'Multiple Choice', 1, '2026-07-10 02:40:59', 1, '2026-07-14 17:30:33'),
(33, 5, 1, 'What a nice', 'Multiple Choice', 0, '2026-07-13 00:01:06', 0, NULL),
(34, 5, 1, 'heheheh', 'Multiple Choice', 0, '2026-07-13 00:01:06', 0, NULL),
(35, 5, 1, 'KCDJDJCN', 'Multiple Choice', 0, '2026-07-13 00:01:06', 0, NULL);

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
(14, 14, 10.00, 100.00, 'Passed', '2026-07-10 00:30:43'),
(15, 15, 2.00, 100.00, 'Passed', '2026-07-10 02:42:46'),
(16, 16, 1.00, 8.33, 'Failed', '2026-07-10 09:13:13'),
(17, 17, 9.00, 90.00, 'Passed', '2026-07-10 09:46:22'),
(18, 18, 2.00, 100.00, 'Passed', '2026-07-10 09:51:31'),
(19, 19, 4.00, 80.00, 'Passed', '2026-07-10 10:37:43'),
(20, 20, 0.00, 0.00, 'Failed', '2026-07-10 12:27:36'),
(21, 21, 2.00, 100.00, 'Passed', '2026-07-11 02:36:54'),
(22, 22, 1.00, 10.00, 'Failed', '2026-07-14 17:35:16'),
(23, 23, 1.00, 20.00, 'Failed', '2026-07-13 00:45:25'),
(24, 24, 9.00, 90.00, 'Passed', '2026-07-14 17:47:55'),
(25, 25, 0.00, 0.00, NULL, '2026-07-14 18:00:17'),
(26, 26, 10.00, 100.00, 'Passed', '2026-07-15 00:36:45'),
(27, 28, 7.00, 70.00, 'Passed', '2026-07-15 01:17:18'),
(28, 29, 6.00, 60.00, 'Passed', '2026-07-15 01:19:13'),
(29, 30, 8.00, 80.00, 'Passed', '2026-07-15 01:21:27'),
(31, 31, 8.00, 80.00, 'Passed', '2026-07-15 01:24:18'),
(32, 32, 3.00, 30.00, 'Failed', '2026-07-15 01:25:22'),
(33, 33, 10.00, 100.00, 'Passed', '2026-07-15 01:28:40');

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
  `Module_ID` int(11) NOT NULL,
  `Subject_Name` varchar(200) NOT NULL,
  `Subject_Code` varchar(50) NOT NULL,
  `Description` text DEFAULT NULL,
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_subject`
--

INSERT INTO `tbl_subject` (`Subject_ID`, `Course_ID`, `Module_ID`, `Subject_Name`, `Subject_Code`, `Description`, `Is_Archived`, `Archived_At`) VALUES
(5, 1, 1, 'Human Rights: A Review with Case Based Approach', 'HR101', '', 0, NULL),
(7, 1, 1, 'Protocol and Social Amenities', 'PSA101', '', 0, NULL),
(8, 1, 1, 'Social Psychology and the Sustainable Future', 'SPS101', '', 0, NULL),
(9, 1, 1, 'Research and Statistics', 'RAS101', '', 0, NULL),
(10, 1, 1, 'Statistics', 'STS101', '', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user`
--

CREATE TABLE `tbl_user` (
  `User_ID` int(11) NOT NULL,
  `Role_ID` int(11) NOT NULL,
  `Fullname` varchar(200) NOT NULL,
  `Email` varchar(200) NOT NULL,
  `Password_Hash` varchar(255) NOT NULL,
  `Academic_Number` varchar(50) DEFAULT NULL,
  `Personnel_Rank` varchar(50) DEFAULT NULL,
  `Must_Change_Password` tinyint(1) NOT NULL DEFAULT 0,
  `Date_Created` datetime NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0,
  `Archived_At` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_user`
--

INSERT INTO `tbl_user` (`User_ID`, `Role_ID`, `Fullname`, `Email`, `Password_Hash`, `Academic_Number`, `Personnel_Rank`, `Must_Change_Password`, `Date_Created`, `Status`, `Is_Archived`, `Archived_At`) VALUES
(1, 1, 'Administrator', 'admin@rtc.com', '$2y$10$FdwKuCbt5y6mmRHb1T87TeCgLeNwdryRShh/kb9u1VEen4P9QlF86', NULL, NULL, 0, '2026-07-04 04:23:40', 'Active', 0, NULL),
(2, 2, 'CCMD Officer', 'ccmd@rtc.com', '$2y$10$FdwKuCbt5y6mmRHb1T87TeCgLeNwdryRShh/kb9u1VEen4P9QlF86', NULL, NULL, 0, '2026-07-04 04:23:40', 'Active', 0, NULL),
(8, 3, 'Crisbell Ric V. Aboc', 'rivi.aboc.coc@phinmaed.com', '$2y$10$E11TJo2IDrX.ARbaUioe.uMte.ImgY10Rql44B78i4NQyNkdXIJSe', 'A001', 'PBGEN', 0, '2026-07-09 22:01:59', 'Active', 0, NULL),
(9, 3, 'Rhick Marguo F. Orina', 'rhfl.orina.coc@phinmaed.com', '$2y$10$YhJzmIR74W8SII4jlrFFuO6BIJYxU9sycRFRAZM96E4Zebcofleeu', 'A002', 'PLTCOL', 0, '2026-07-09 22:03:49', 'Active', 0, NULL),
(10, 3, 'Edcel John P. Amper', 'edpa.amper.coc@phinmaed.com', '$2y$10$uN3e/clZMKVT.9X/zci57.vx/ish0QGl1J2a8WBATy6mZNm96CbXW', 'A003', 'PAT', 0, '2026-07-09 22:05:02', 'Active', 0, NULL),
(13, 1, 'Austine James', 'austinejames@gmail.com', '$2y$10$uVP9cRiWCFjf66gEUWWGxe0/OB7d3F8FprZBXdiS3h2Lx4b0NQHWi', NULL, NULL, 0, '2026-07-12 23:00:35', 'Inactive', 1, '2026-07-12 23:02:18'),
(14, 3, 'Raiden O. Mendel', 'mendelraiden@gmail.com', '$2y$10$5tmiBZw8vo.fkQ8c1NWhjeBfmn0mDppsLYhkcDf43VRGP.HtAM7Hi', 'A004', 'PGen', 1, '2026-07-13 22:23:22', 'Inactive', 1, '2026-07-13 22:23:34'),
(15, 3, 'Austine James', 'austinejamess@gmail.com', '$2y$10$IiVxel9FeIrGJGo87KF9TO5MkxA.5SSRiZPPKYe.NtdGGj3FbEGuW', 'A005', 'PGEN', 0, '2026-07-13 22:42:30', 'Active', 0, NULL),
(16, 3, 'James Charles', 'james@example.com', '$2y$10$jAc0OmLpCxoHYyNutKoeh.eIXyiAb48JciYmmUkljqHcKY.4AeNSK', 'A006', 'PAT', 0, '2026-07-14 22:24:08', 'Active', 0, NULL),
(17, 3, 'Manny Eipstien', 'manny@example.com', '$2y$10$NeCvJeXy9z9jDMzA7H2oreoO7ErLKPPOVU2Z29FceniX6EKpubz9.', 'A007', 'PCPL', 0, '2026-07-14 22:24:08', 'Active', 0, NULL);

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
(43, 16, 44, 'Active', '2026-07-15 00:26:01'),
(44, 17, 44, 'Active', '2026-07-15 00:26:01'),
(45, 15, 44, 'Active', '2026-07-15 00:26:01'),
(46, 10, 44, 'Active', '2026-07-15 00:26:01'),
(47, 9, 44, 'Active', '2026-07-15 00:26:01'),
(48, 8, 44, 'Active', '2026-07-15 00:26:01');

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
  ADD KEY `idx_session_user` (`User_ID`);

--
-- Indexes for table `tbl_module`
--
ALTER TABLE `tbl_module`
  ADD PRIMARY KEY (`Module_ID`),
  ADD KEY `idx_module_course` (`Course_ID`);

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
  ADD UNIQUE KEY `uq_subject_code_module` (`Module_ID`,`Subject_Code`),
  ADD KEY `idx_subject_course` (`Course_ID`);

--
-- Indexes for table `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `uq_user_email` (`Email`),
  ADD UNIQUE KEY `uq_user_academic_number` (`Academic_Number`),
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
  MODIFY `Section_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tbl_answer`
--
ALTER TABLE `tbl_answer`
  MODIFY `Answer_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  MODIFY `Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=919;

--
-- AUTO_INCREMENT for table `tbl_batch`
--
ALTER TABLE `tbl_batch`
  MODIFY `Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `tbl_cheating_incident`
--
ALTER TABLE `tbl_cheating_incident`
  MODIFY `Incident_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tbl_cheating_log`
--
ALTER TABLE `tbl_cheating_log`
  MODIFY `Cheating_Log_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT for table `tbl_choice`
--
ALTER TABLE `tbl_choice`
  MODIFY `Choice_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- AUTO_INCREMENT for table `tbl_course`
--
ALTER TABLE `tbl_course`
  MODIFY `Course_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tbl_exam`
--
ALTER TABLE `tbl_exam`
  MODIFY `Exam_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tbl_exam_batch`
--
ALTER TABLE `tbl_exam_batch`
  MODIFY `Exam_Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `tbl_exam_code_attempt`
--
ALTER TABLE `tbl_exam_code_attempt`
  MODIFY `Attempt_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `tbl_exam_participant`
--
ALTER TABLE `tbl_exam_participant`
  MODIFY `Exam_Participant_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `tbl_exam_question`
--
ALTER TABLE `tbl_exam_question`
  MODIFY `Exam_Question_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT for table `tbl_exam_session`
--
ALTER TABLE `tbl_exam_session`
  MODIFY `Session_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `tbl_module`
--
ALTER TABLE `tbl_module`
  MODIFY `Module_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `Question_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `tbl_report`
--
ALTER TABLE `tbl_report`
  MODIFY `Report_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_result`
--
ALTER TABLE `tbl_result`
  MODIFY `Result_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `tbl_role`
--
ALTER TABLE `tbl_role`
  MODIFY `Role_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_subject`
--
ALTER TABLE `tbl_subject`
  MODIFY `Subject_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `tbl_user_batch`
--
ALTER TABLE `tbl_user_batch`
  MODIFY `User_Batch_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

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
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`User_ID`) REFERENCES `tbl_user` (`User_ID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_module`
--
ALTER TABLE `tbl_module`
  ADD CONSTRAINT `fk_module_course` FOREIGN KEY (`Course_ID`) REFERENCES `tbl_course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_subject_course` FOREIGN KEY (`Course_ID`) REFERENCES `tbl_course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subject_module` FOREIGN KEY (`Module_ID`) REFERENCES `tbl_module` (`Module_ID`) ON UPDATE CASCADE;

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
