-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 06, 2026 at 09:21 AM
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
-- Database: `story_prediction2`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL,
  `action_text` varchar(255) NOT NULL,
  `action_type` enum('approve','delete','update','add') DEFAULT 'update',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `action_text`, `action_type`, `created_at`) VALUES
(1, 'User #61 email marked verified by admin', 'update', '2026-03-28 20:46:58'),
(2, 'User #61 email marked unverified by admin', 'update', '2026-03-28 20:48:17'),
(3, 'User #63 (@chathan) edited by admin', 'update', '2026-03-28 20:48:39'),
(4, 'New reader \'miyakhalifa\' created by admin', 'add', '2026-03-28 20:49:55'),
(5, 'User #65 deleted', 'delete', '2026-03-28 20:50:28'),
(6, 'User #61 email marked verified by admin', 'update', '2026-03-29 08:26:34'),
(7, 'Story #11 approved (and its pending parts)', 'approve', '2026-03-29 08:49:39'),
(8, 'User #61 email marked unverified by admin', 'update', '2026-03-29 19:22:56'),
(9, 'Announcement \"UPDATE\" created', 'add', '2026-03-31 07:29:20'),
(10, 'Announcement \"UPDATE\" deleted', 'delete', '2026-03-31 07:29:55'),
(11, 'Announcement \"UPDATE\" created', 'add', '2026-03-31 07:33:18'),
(12, 'Announcement #2 updated', 'update', '2026-03-31 07:38:03'),
(13, 'Announcement \"UPDATE\" deleted', 'delete', '2026-03-31 07:38:22'),
(14, 'Announcement \"UPDATE\" created', 'add', '2026-03-31 07:38:40'),
(15, 'Announcement \"UPDATE\" deleted', 'delete', '2026-03-31 12:01:37'),
(16, 'Announcement \"UPDATE\" created', 'add', '2026-03-31 12:53:10'),
(17, 'Announcement \"UPDATE\" deleted', 'delete', '2026-04-03 20:11:57'),
(18, 'User #61 email marked verified by admin', 'update', '2026-04-03 20:19:17');

-- --------------------------------------------------------

--
-- Table structure for table `admin_story_dataset`
--

CREATE TABLE `admin_story_dataset` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `part_id` int(11) DEFAULT NULL,
  `prediction_text` text NOT NULL,
  `label_score` float NOT NULL,
  `label_type` enum('correct','partial','wrong') DEFAULT NULL,
  `ai_score` float DEFAULT NULL,
  `entailment` float DEFAULT NULL,
  `contradiction` float DEFAULT NULL,
  `entity_score` float DEFAULT NULL,
  `cosine_score` float DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_story_dataset`
--

INSERT INTO `admin_story_dataset` (`id`, `story_id`, `part_id`, `prediction_text`, `label_score`, `label_type`, `ai_score`, `entailment`, `contradiction`, `entity_score`, `cosine_score`, `notes`, `created_at`, `updated_at`) VALUES
(1, 10, NULL, 'Aris was actually in a coma and the entire Helios Complex was a neural simulation.', 95, 'correct', 92.5, 0.88, 0.05, 0.8, 0.76, 'Matches coma reveal in actual next chapter.', '2026-03-03 09:22:19', '2026-03-03 09:22:19'),
(2, 10, NULL, 'The \"saboteur\" Aris was tracking was actually his own brain\'s repair protocols.', 92, 'correct', 89, 0.85, 0.02, 0.75, 0.72, 'Correctly identifies the internal nature of the glitches.', '2026-03-03 09:24:59', '2026-03-03 09:24:59'),
(3, 10, NULL, 'Lena Petrova is not a real colleague but a mental construct helping Aris heal.', 98, 'correct', 96, 0.94, 0.01, 0.88, 0.82, 'High accuracy on the nature of the secondary character.', '2026-03-03 09:26:09', '2026-03-03 09:26:09'),
(4, 10, NULL, 'The Revelation Chamber is the exit point of a medical simulation for brain trauma.', 90, 'correct', 88.5, 0.82, 0.08, 0.78, 0.7, 'Matches the physical location\'s metaphorical meaning.', '2026-03-03 09:27:19', '2026-03-03 09:27:19'),
(5, 10, NULL, 'Aris discovers his memories are being used to test a new restoration technology.', 85, 'correct', 84, 0.75, 0.12, 0.7, 0.68, 'Partially matches the tech goal of Project Chimera.', '2026-03-03 09:28:25', '2026-03-03 09:28:25'),
(6, 10, NULL, 'The facility glitches are symbols of physical brain damage being repaired by nanobots.', 94, 'correct', 91, 0.89, 0.03, 0.82, 0.75, 'Deep plot connection to the \"medical\" reality.', '2026-03-03 09:30:39', '2026-03-03 09:30:39'),
(7, 10, NULL, 'Aris has been unconscious for months, and the mission was a mental recovery loop.', 96, 'correct', 93.5, 0.91, 0.02, 0.85, 0.79, 'Correctly identifies the timeframe and purpose.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(8, 10, NULL, 'The \"outside world\" Aris saw through the window was just a digital background.', 88, 'correct', 86, 0.79, 0.1, 0.72, 0.65, 'Accurate regarding the fake environment.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(9, 10, NULL, 'Project Chimera is a real-world project, but Aris is the patient, not the lead dev.', 99, 'correct', 98, 0.97, 0, 0.92, 0.88, 'Perfect reversal of roles revealed in Part 2.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(10, 10, NULL, 'The voice Aris hears at the end is a real doctor monitoring his vitals.', 91, 'correct', 89.5, 0.84, 0.05, 0.8, 0.72, 'Correctly identifies the origin of the final dialogue.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(11, 10, NULL, 'Aris realizes his paranoia was a symptom of his brain trying to make sense of trauma.', 93, 'correct', 90, 0.87, 0.04, 0.79, 0.74, 'Matches the psychological explanation in Part 2.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(12, 10, NULL, 'The Helios Complex is a mental model built from Aris\'s pre-accident research.', 97, 'correct', 95, 0.93, 0.01, 0.89, 0.81, 'Explains why the simulation looks the way it does.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(13, 10, NULL, 'Part 2 shows Aris waking up in a hospital bed surrounded by medical equipment.', 95, 'correct', 92, 0.9, 0.03, 0.84, 0.78, 'Visual match for the opening of Chapter 5.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(14, 10, NULL, 'The \"phantom intruder\" was a representation of the actual surgery occurring.', 87, 'correct', 85, 0.78, 0.15, 0.71, 0.64, 'Symbolic interpretation of the surveillance shadows.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(15, 10, NULL, 'Aris learns that the simulation was filtering real data into his dream state.', 100, 'correct', 99, 0.98, 0, 0.95, 0.92, 'Hits the major Twist #2 perfectly.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(16, 10, NULL, 'The murder mystery in the simulation was based on a real event stored in the project.', 98, 'correct', 97, 0.96, 0.01, 0.91, 0.89, 'Connects the \"Thorne\" murder to the real project.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(17, 10, NULL, 'Lena is actually much older than she appeared in Aris\'s simulation.', 86, 'correct', 83.5, 0.74, 0.18, 0.7, 0.62, 'Matches the description of her \"grey hair\" in Chapter 5.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(18, 10, NULL, 'Aris’s recovery leads to him becoming the \"Key\" for the next phase of the project.', 90, 'correct', 88, 0.83, 0.09, 0.77, 0.71, 'Matches his role as \"Subject 734.\"', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(19, 10, NULL, 'The simulation used ozone and metallic scents to mimic the hospital environment.', 89, 'correct', 87, 0.81, 0.11, 0.74, 0.68, 'Sensory link between the simulation and reality.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(20, 10, NULL, 'Aris discovers he was unknowingly processing classified memories while in a coma.', 99, 'correct', 98.5, 0.97, 0.01, 0.93, 0.9, 'Core plot reveal of the \"Data Extraction\" twist.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(21, 10, NULL, 'The letter opener in the photo proves the simulation wasn\'t entirely fictional.', 94, 'correct', 91.5, 0.89, 0.04, 0.83, 0.76, 'Connects the artifact to the real-world evidence.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(22, 10, NULL, 'Project Chimera evolved into a spy tool that extracts secrets from the subconscious.', 92, 'correct', 89, 0.85, 0.07, 0.81, 0.73, 'Matches the \"sinister purpose\" mentioned at the end.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(23, 10, NULL, 'Elias Thorne was a real person whose memories were uploaded to Aris.', 96, 'correct', 94, 0.92, 0.02, 0.87, 0.83, 'Explains the source of the crime thriller memories.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(24, 10, NULL, 'The entire story in Part 1 was a psychological test to see if Aris was conscious.', 85, 'correct', 82, 0.71, 0.22, 0.68, 0.6, 'High-level logical match for the recovery test.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(25, 10, NULL, 'Aris realizes he can\'t trust his memories because they are shared with others.', 93, 'correct', 90.5, 0.86, 0.05, 0.8, 0.75, 'Final emotional beat of the story.', '2026-03-03 09:36:25', '2026-03-03 09:36:25'),
(26, 10, NULL, 'Aris is actually an AI assistant who believes he is human within the Helios Complex.', 65, 'partial', 68.5, 0.45, 0.3, 0.6, 0.72, 'Correct about the non-physical setting, but wrong about Aris being an AI instead of a human patient.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(27, 10, NULL, 'The \"intruder\" is a rival scientist from a competing firm trying to steal Chimera data.', 45, 'partial', 52, 0.3, 0.55, 0.4, 0.65, 'Correct that data is involved, but wrong about the intruder being an external human rival.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(28, 10, NULL, 'Lena Petrova is a government agent sent to shut down the project for being too dangerous.', 50, 'partial', 55.5, 0.35, 0.45, 0.55, 0.68, 'Correct that Lena has an ulterior motive/role, but wrong about her being an agent or shutting it down.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(29, 10, NULL, 'The Helios Complex is a spaceship and the simulation is training for a long-distance voyage.', 40, 'partial', 48, 0.2, 0.7, 0.3, 0.58, 'Matches the sci-fi isolation theme, but the \"Space\" context is a total hallucination.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(30, 10, NULL, 'Aris is a clone of the original doctor, and the body in the bed is his biological source.', 60, 'partial', 62, 0.4, 0.4, 0.7, 0.74, 'Correct about the two bodies existing, but wrong about the cloning relationship.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(31, 10, NULL, 'The glitches are actually messages from Aris\'s family trying to wake him up from a coma.', 70, 'partial', 72.5, 0.55, 0.2, 0.5, 0.7, 'Correct about the coma, but wrong about the family being the source of the glitches.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(32, 10, NULL, 'The Revelation Chamber contains a time machine that Aris used to sabotage his past self.', 35, 'partial', 40, 0.15, 0.8, 0.35, 0.62, 'Correct that the sabotage is internal, but the time travel element is completely wrong.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(33, 10, NULL, 'Aris is a criminal whose memories are being wiped as part of a prison sentence.', 55, 'partial', 58, 0.38, 0.42, 0.45, 0.6, 'Correct that his memories are being manipulated, but wrong about the criminal context.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(34, 10, NULL, 'The \"Memory Retrieval\" phase is actually about downloading Aris\'s soul into a computer.', 65, 'partial', 67, 0.48, 0.25, 0.58, 0.73, 'Correct about the digital transfer, but uses \"soul\" (spiritual) instead of \"neural\" (medical).', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(35, 10, NULL, 'Lena is Aris\'s wife, and she is using the simulation to remind him of their life together.', 50, 'partial', 54, 0.32, 0.48, 0.65, 0.69, 'Correct about the personal connection, but wrong about their specific relationship.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(36, 10, NULL, 'The server room fluctuations are caused by a solar flare hitting the Nevada desert base.', 30, 'partial', 35.5, 0.1, 0.85, 0.4, 0.55, 'Correct about the location (Nevada), but wrong about the cause of the electrical issues.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(37, 10, NULL, 'Aris is a pilot in a flight simulator who has suffered a mental breakdown.', 45, 'partial', 49, 0.28, 0.6, 0.45, 0.61, 'Correct about the simulation and mental state, but wrong about the profession.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(38, 10, NULL, 'The project is a military experiment to create soldiers with shared combat memories.', 58, 'partial', 61.5, 0.42, 0.35, 0.52, 0.67, 'Correct about the shared memory aspect, but wrong about the military focus.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(39, 10, NULL, 'Aris discovers he died years ago and his consciousness is being kept alive by Helios.', 62, 'partial', 64, 0.44, 0.3, 0.5, 0.71, 'Correct about the \"not really alive\" state, but he is in a coma, not dead.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(40, 10, NULL, 'The mystery of Elias Thorne is a puzzle Aris must solve to earn his freedom.', 70, 'partial', 73, 0.58, 0.15, 0.78, 0.8, 'Correct that the mystery is a \"key,\" but wrong about it being a \"test\" for freedom.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(41, 10, NULL, 'The \"ghost\" Aris saw is a hacker from the real world trying to crash the system.', 48, 'partial', 51, 0.33, 0.52, 0.4, 0.66, 'Correct that the ghost is an external influence, but wrong about it being a hacker.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(42, 10, NULL, 'Lena is trying to kill Aris in the real world while he is trapped in the simulation.', 40, 'partial', 44.5, 0.22, 0.72, 0.6, 0.64, 'Correct about the real-world danger, but wrong about Lena being the antagonist.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(43, 10, NULL, 'The Helios Complex is a virtual museum of human history that Aris is curating.', 35, 'partial', 38, 0.18, 0.75, 0.38, 0.59, 'Correct about \"History/Archive,\" but wrong about the museum/curator role.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(44, 10, NULL, 'Aris is actually Subject 735, and he is watching a recording of Subject 734.', 55, 'partial', 57.5, 0.36, 0.45, 0.82, 0.76, 'Matches the \"Subject ID\" theme but confuses the identity and the sequence.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(45, 10, NULL, 'The \"ozone scent\" is a leak in the real facility that is killing the patients.', 50, 'partial', 53, 0.31, 0.5, 0.48, 0.63, 'Correct about the physical scent connection, but wrong about the \"lethal leak\" plot.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(46, 10, NULL, 'Project Chimera is a way for old people to live in young bodies via digital transfer.', 42, 'partial', 46, 0.25, 0.68, 0.5, 0.65, 'Correct about digital transfer, but wrong about the \"anti-aging\" motivation.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(47, 10, NULL, 'Aris will wake up and realize he has been a volunteer for a 24-hour sleep study.', 25, 'partial', 29, 0.05, 0.9, 0.4, 0.52, 'Correct that he wakes up, but trivializes the coma and the 7-month duration.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(48, 10, NULL, 'The \"phantom protocol\" is a self-destruct sequence Aris initiated himself.', 48, 'partial', 50.5, 0.34, 0.54, 0.42, 0.68, 'Correct that it is Aris-centric, but wrong about it being a \"self-destruct\" action.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(49, 10, NULL, 'The Revelation Chamber contains a duplicate of Aris\'s brain in a jar.', 52, 'partial', 55, 0.37, 0.48, 0.6, 0.7, 'Matches the \"neural hardware\" theme but is too literal and medically incorrect.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(50, 10, NULL, 'Lena Petrova is Aris\'s daughter, and she is the one who invented Project Chimera.', 45, 'partial', 49.5, 0.29, 0.62, 0.65, 0.66, 'Correct that Lena is the lead now, but wrong about the family tree/age dynamic.', '2026-03-03 09:40:05', '2026-03-03 09:40:05'),
(51, 10, NULL, 'Aris is actually a professional chef at a high-end French restaurant in Paris.', 0, 'wrong', 5, 0.01, 0.98, 0.05, 0.1, 'Completely changes the genre and character profession.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(52, 10, NULL, 'The Helios Complex is a magical castle where Aris is learning to cast fire spells.', 2, 'wrong', 8.5, 0.02, 0.95, 0.08, 0.15, 'Switch from Sci-Fi to High Fantasy.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(53, 10, NULL, 'The glitches in the system are caused by a mischievous ghost of a Victorian chimney sweep.', 5, 'wrong', 12, 0.05, 0.9, 0.1, 0.22, 'Absurd supernatural explanation unrelated to the plot.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(54, 10, NULL, 'Aris realizes he is a contestant on a reality TV dating show called \"Heart of Nevada.\"', 1, 'wrong', 4, 0.01, 0.99, 0.02, 0.08, 'Trivializes the setting into a completely different media format.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(55, 10, NULL, 'The \"Memory Retrieval\" is actually Aris trying to remember where he left his car keys.', 3, 'wrong', 7, 0.03, 0.94, 0.12, 0.18, 'Reduces high-stakes sci-fi to a mundane domestic task.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(56, 10, NULL, 'Lena Petrova is a talking cat that Aris rescued from a tree earlier that morning.', 0, 'wrong', 2.5, 0, 1, 0.05, 0.05, 'Nonsensical character transformation.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(57, 10, NULL, 'The Revelation Chamber is a giant oven used to bake the world\'s largest sourdough loaf.', 2, 'wrong', 6, 0.02, 0.97, 0.1, 0.12, 'Bizarre culinary pivot.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(58, 10, NULL, 'Aris discovers he is an underwater deep-sea diver searching for Atlantis.', 8, 'wrong', 15, 0.08, 0.85, 0.15, 0.25, 'Replaces desert/neuroscience with ocean/archaeology.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(59, 10, NULL, 'The project is actually a secret plan to replace all humans with sentient garden gnomes.', 1, 'wrong', 3.5, 0.01, 0.98, 0.03, 0.09, 'Absurd conspiracy theory unrelated to the text.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(60, 10, NULL, 'Aris wakes up and realizes he is a dog dreaming about being a human scientist.', 4, 'wrong', 9, 0.04, 0.92, 0.08, 0.14, 'A \"it was all a dream\" trope that changes the species of the protagonist.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(61, 10, NULL, 'The \"phantom intruder\" is a pizza delivery man who can\'t find the front door.', 2, 'wrong', 5.5, 0.02, 0.96, 0.11, 0.13, 'Comedic interruption of a thriller sequence.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(62, 10, NULL, 'The Helios Complex is a sports stadium where Aris is the starting quarterback.', 3, 'wrong', 8, 0.03, 0.93, 0.14, 0.17, 'Genre shift to a sports drama.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(63, 10, NULL, 'Lena is a time-traveling Viking who wants Aris to help her find her lost axe.', 5, 'wrong', 11.5, 0.06, 0.88, 0.12, 0.2, 'Anachronistic and illogical character background.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(64, 10, NULL, 'The server room fluctuations are actually Aris playing a very loud game of Tetris.', 4, 'wrong', 10, 0.05, 0.91, 0.18, 0.24, 'Trivializes the technical anomalies.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(65, 10, NULL, 'Aris is a detective in the 1920s investigating a jazz club murder.', 12, 'wrong', 22, 0.12, 0.75, 0.2, 0.35, 'Pivots to Noir/Period drama (might score slightly higher due to \"detective\" keywords, but still wrong).', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(66, 10, NULL, 'The Revelation Chamber is full of disco balls and Aris has to win a dance-off.', 1, 'wrong', 3, 0, 0.99, 0.04, 0.07, 'Ridiculous shift in tone and stakes.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(67, 10, NULL, 'Aris discovers that Project Chimera is a system for translating what babies are thinking.', 15, 'wrong', 25, 0.15, 0.7, 0.25, 0.4, 'Misinterprets the \"neural/thought\" theme into something unrelated.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(68, 10, NULL, 'The facility is actually located on the moon and is made entirely of cheese.', 0, 'wrong', 1.5, 0, 1, 0.02, 0.04, 'Absurdist nursery rhyme logic.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(69, 10, NULL, 'Aris is a cowboy in the Wild West, and the \"servers\" are actually cattle.', 6, 'wrong', 13.5, 0.07, 0.86, 0.12, 0.19, 'Western genre swap.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(70, 10, NULL, 'Lena Petrova is a holographic pop star who is about to go on a world tour.', 10, 'wrong', 18, 0.1, 0.8, 0.3, 0.32, 'Cyberpunk/Pop-culture pivot.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(71, 10, NULL, 'The \"Memory Retrieval\" is Aris trying to remember the lyrics to \"Happy Birthday.\"', 2, 'wrong', 5, 0.02, 0.96, 0.08, 0.11, 'Low-stakes memory loss.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(72, 10, NULL, 'Aris is a botanical researcher who has discovered a flower that can speak Italian.', 4, 'wrong', 9.5, 0.04, 0.91, 0.09, 0.16, 'Surrealist/Botanical plot shift.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(73, 10, NULL, 'The \"intruder\" is a lost penguin who wandered into the desert by mistake.', 1, 'wrong', 3, 0.01, 0.99, 0.05, 0.06, 'Absurd animal-based interruption.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(74, 10, NULL, 'The entire Helios Complex is a giant board game played by bored gods.', 18, 'wrong', 28, 0.18, 0.65, 0.22, 0.45, 'Metaphysical/Mythological twist unrelated to the sci-fi setup.', '2026-03-03 09:42:51', '2026-03-03 09:42:51'),
(75, 10, NULL, 'Aris wakes up and realizes he is a piece of toast being buttered by a giant.', 0, 'wrong', 1, 0, 1, 0.01, 0.02, 'Purely nonsensical/abstract ending.', '2026-03-03 09:42:51', '2026-03-03 09:42:51');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `heading` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('warning','update','info') NOT NULL DEFAULT 'info',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `ends_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `arena_progress`
--

CREATE TABLE `arena_progress` (
  `progress_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `story_id` int(11) DEFAULT NULL,
  `part_number` int(11) DEFAULT NULL,
  `current_wpm` int(11) NOT NULL DEFAULT 60 COMMENT 'Active WPM level: 60,100,140,180,220',
  `streak` int(11) NOT NULL DEFAULT 0 COMMENT 'Consecutive correct answers at current WPM',
  `loss_streak` int(11) NOT NULL DEFAULT 0 COMMENT 'Consecutive wrong answers at current WPM. Drop level after 2.',
  `total_correct` int(11) NOT NULL DEFAULT 0,
  `total_attempts` int(11) NOT NULL DEFAULT 0,
  `best_wpm` int(11) NOT NULL DEFAULT 60 COMMENT 'Highest WPM tier ever reached',
  `last_played` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `score` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `arena_progress`
--

INSERT INTO `arena_progress` (`progress_id`, `user_id`, `story_id`, `part_number`, `current_wpm`, `streak`, `loss_streak`, `total_correct`, `total_attempts`, `best_wpm`, `last_played`, `created_at`, `score`) VALUES
(1, 63, NULL, NULL, 100, 0, 1, 8, 12, 100, '2026-03-31 06:47:19', '2026-02-27 04:21:14', 1000),
(2, 63, NULL, NULL, 100, 0, 1, 8, 12, 100, '2026-03-31 06:47:19', '2026-02-27 04:22:30', 1000),
(3, 64, NULL, NULL, 60, 1, 0, 1, 1, 60, '2026-02-28 04:15:57', '2026-02-28 04:15:57', 0);

-- --------------------------------------------------------

--
-- Table structure for table `arena_questions`
--

CREATE TABLE `arena_questions` (
  `question_id` int(11) NOT NULL,
  `story_id` int(11) DEFAULT NULL,
  `part_number` int(11) DEFAULT NULL,
  `chunk_text` text DEFAULT NULL COMMENT 'The story passage chunk this question is based on. NULL for external questions.',
  `question_type` enum('story_chunk','external') NOT NULL DEFAULT 'story_chunk' COMMENT 'story_chunk = derived from story_parts content | external = admin-written custom passage',
  `external_passage` text DEFAULT NULL COMMENT 'For external type: the custom knowledge paragraph written by admin (e.g. solar system facts)',
  `question_text` text NOT NULL,
  `correct_option` varchar(300) NOT NULL,
  `misleading_option` varchar(300) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `arena_questions`
--

INSERT INTO `arena_questions` (`question_id`, `story_id`, `part_number`, `chunk_text`, `question_type`, `external_passage`, `question_text`, `correct_option`, `misleading_option`, `created_by`, `created_at`) VALUES
(11, NULL, NULL, NULL, 'external', 'Amazon Rainforest is often called the \"lungs of the planet\" because its dense vegetation constantly recycles carbon dioxide into oxygen. This vast tropical landscape is home to millions of species, many of which are found nowhere else on Earth. Despite its beauty, the thick canopy is so dense that it can take ten minutes for rain to reach the ground', 'How long does it take for raindrops to hit the forest floor through the canopy?', '10 minutes', '10 seconds', NULL, '2026-02-24 12:16:48'),
(12, NULL, NULL, NULL, 'external', 'The Emperor Penguin survives the brutal Antarctic winter through it\'s incredible teamwork. While temperatures drop below minus forty degrees, thousands of males huddle together to protect their eggs. They take turns moving from the freezing outer edge of the circle to the warm center. This rotating huddle ensures every penguin stays alive by sharing the colony’s collective body heat. .', 'What do the penguins share to survive the cold?', 'Heat', 'Food', NULL, '2026-02-24 12:25:35'),
(13, NULL, NULL, NULL, 'external', 'The Snowy Owl is a master of the Arctic tundra, sporting thick white feathers for insulation and camouflage. Unlike most owls that hunt at night, the Snowy Owl is diurnal, meaning it hunts during the day. This adaptation is essential because, during the Arctic summer, the sun never sets, providing constant light for these powerful birds to find it\'s prey.', 'When does this owl primarily hunt?', 'Daytime', 'Night Time', NULL, '2026-02-24 12:26:47'),
(14, NULL, NULL, NULL, 'external', 'Mars is known as the Red Planet because its surface is covered in iron oxide, the same compound that creates rust. It hosts Olympus Mons, the largest volcano in our solar system, which is three times taller than Mount Everest. Although it has a thin atmosphere, Mars features giant dust storms that can cover the entire planet for months. .', 'What gives Mars its distinct red color?', 'Rust', 'Lava', NULL, '2026-02-24 12:27:49'),
(15, NULL, NULL, NULL, 'external', 'Ancient Alexandria once housed the world’s most significant library, serving as a massive center for universal knowledge. Scholars from across the Mediterranean traveled there to study mathematics, astronomy, and philosophy. It was rumored that every ship entering the city\'s harbor had its scrolls confiscated, copied, and then returned, while the originals remained in the library\'s collection. Tragically, much of this vast cultural treasure was lost to fire and war over several centuries. Today, the library remains a powerful symbol of human curiosity and the fragile nature of recorded history. Despite its destruction, its legendary reputation continues to inspire modern researchers.', 'What were the original items collected from entering ships?', 'Scrolls', 'Gold', NULL, '2026-02-24 12:31:38'),
(16, NULL, NULL, NULL, 'external', 'Our Sun is a nearly perfect sphere of hot plasma, providing the essential energy that supports all life on Earth. At its core, gravity creates intense pressure and temperatures, triggering nuclear fusion that converts hydrogen into helium. This process releases a massive amount of light and heat that travels through space. Interestingly, it takes approximately eight minutes for sunlight to reach our planet, even though the Sun is ninety-three million miles away. Without this constant radiation, Earth would be a frozen, dark world. The Sun represents over ninety-nine percent of the total mass within our entire solar system today. .', 'Which element is converted into helium at the core?', 'Hydrogen', 'Oxygen', NULL, '2026-02-24 12:32:25'),
(17, NULL, NULL, NULL, 'external', 'The Silk Road was an ancient network of trade routes connecting the East and West for centuries. It allowed merchants to exchange goods like silk, spices, and precious metals across vast deserts and mountains. However, the route was much more than just a path for commerce; it served as a vital highway for the exchange of ideas, religions, and technologies. Innovations like paper-making and gunpowder traveled along these paths, forever changing the civilizations they reached. Although the physical journey was often dangerous and exhausting, the cultural impact of this connectivity helped shape the modern world by bringing diverse people together.', 'Which fabric gave this famous ancient trade network its name?', 'Silk', 'Cotton', NULL, '2026-02-24 12:32:56'),
(18, NULL, NULL, NULL, 'external', 'Honey bees are remarkable insects that play a crucial role in our ecosystem through the process of pollination. While searching for nectar, they move from flower to flower, transferring pollen that allows plants to reproduce. Inside the hive, bees work together in a highly organized society led by a single queen. They communicate the location of food sources to their colony members by performing a unique \"waggle dance.\" Furthermore, bees are the only insects that produce food eaten by humans on a large scale. Protecting bee populations is vital for maintaining global food security and health of many wild environments.', 'What do bees perform to tell others where food is?', 'Dance', 'Song', NULL, '2026-02-24 12:34:02'),
(19, NULL, NULL, NULL, 'external', 'The oak tree is a symbol of strength and endurance, often living for several hundred years in various climates. Starting as a tiny acorn, it grows into a massive structure with a deep root system and a wide, leafy canopy. Oak wood is highly valued for its density and durability, making it a popular choice for building furniture, flooring, and even historical sailing ships. In addition to providing timber, oak trees support hundreds of different species of insects, birds, and mammals. Their long lifespan means that a single tree can witness the passage of many human generations over time. .', 'What is the name of the seed produced by this tree?', 'Acorn', 'Pinecone', NULL, '2026-02-24 12:35:09'),
(20, NULL, NULL, NULL, 'external', 'The Great Wall of China is a massive series of ancient fortifications built to protect against the invasions. Constructed primarily of stone, brick, and tampered earth, it stretches over thirteen thousand miles across the northern borders. While many believe it is a single continuous line, it actually consists of various sections, including watchtowers, shelters, and even the natural mountain defenses.', 'Which material was used alongside stone and brick to build the wall?', 'Earth', 'Steel', NULL, '2026-02-24 12:37:10'),
(21, NULL, NULL, NULL, 'external', 'The Coast Redwood is the tallest tree species on Earth, reaching heights of over three hundred feet. These ancient giants thrive in the foggy coastal climate of Northern California and Oregon. Their thick bark protects them from fire and insects, while their shallow, interlocking root systems help them stand firm against strong winds during heavy winter storms each year. .', 'In which specific climate do these tall trees thrive?', 'Foggy', 'Desert', NULL, '2026-02-28 04:45:39'),
(22, NULL, NULL, NULL, 'external', 'Antarctica is the coldest, driest, and windiest continent on Earth, covered mostly by a massive ice sheet. Despite the extreme conditions, it is technically considered a desert because it receives very little annual precipitation. While no humans live there permanently, scientists visit to study the unique environment, wildlife like penguins, and the history trapped deep within the ancient ice. .', 'Why is Antarctica technically classified as a desert?', 'Precipitation', 'Sand', NULL, '2026-02-28 04:46:16'),
(23, NULL, NULL, NULL, 'external', 'Light travels at an incredible speed of about one hundred eighty-six thousand miles per second. This means that sunlight takes roughly an eight minutes to reach the Earth after leaving the Sun. Because space is so vast, astronomers use light-years to measure distances between stars. One light-year is the total distance that light can travel in a single Earth year.', 'How many minutes does sunlight take to reach Earth?', '8 minutes', '8 seconds', NULL, '2026-02-28 04:47:08'),
(24, NULL, NULL, NULL, 'external', 'The Blue Whale is the largest animal known to have ever existed, even bigger than the largest dinosaurs. These massive marine mammals can grow up to one hundred feet long and weigh as much as two hundred tons. Interestingly, they survive primarily by eating tiny shrimp-like creatures called krill, filtering thousands of pounds of them through their baleen plates daily.', 'What tiny creatures do these massive whales primarily eat?', 'Krill', 'Plankton', NULL, '2026-02-28 04:47:37'),
(25, NULL, NULL, NULL, 'external', 'The Colosseum is a massive stone amphitheater located in the center of Rome, Italy. Completed in AD 80, it could hold over fifty thousand spectators who gathered to watch gladiatorial contests and public spectacles. It featured a complex system of underground tunnels and elevators used to transport animals and fighters to the arena floor during the many famous ancient games.', 'Where is this famous stone amphitheater located?', 'Rome', 'Athens', NULL, '2026-02-28 04:48:08'),
(26, NULL, NULL, NULL, 'external', 'The human heart is a powerful muscular organ that pumps blood throughout the entire body. It beats about one hundred thousand times every day, sending oxygen and nutrients to tissues while removing waste products. Divided into four chambers, the heart works constantly without ever resting. Regular exercise and  healthy diet are essential for keeping this vital internal pump functioning well.', 'How many chambers does the human heart have?', 'Four', 'Two', NULL, '2026-02-28 04:50:02'),
(27, NULL, NULL, NULL, 'external', 'The Great Pyramid of Giza is the oldest and largest of the three pyramids in Egypt. Built as a tomb for Pharaoh Khufu, it remained the tallest man-made structure in the world for over three thousand years. It consists of millions of limestone blocks, each weighing several tons, which were moved and placed with incredible precision by ancient Egyptian workers.', 'For which specific Pharaoh was this massive tomb built?', 'Khufu', 'Tut', NULL, '2026-02-28 04:50:34'),
(28, NULL, NULL, NULL, 'external', 'An electric battery is a device that stores chemical energy and converts it into electrical energy. It consists of one or more electrochemical cells with external connections that power various devices like flashlights, smartphones, and electric cars. When a battery is connected to a circuit, a chemical reaction occurs inside, creating a flow of electrons that provides the necessary power.', 'What type of energy is stored inside a battery?', 'Chemical', 'Electrical', NULL, '2026-02-28 04:51:45'),
(29, NULL, NULL, NULL, 'external', 'The Amazon River is the largest river in the world by the volume of water it carries. Flowing through the dense South American rainforest, it discharges more water into the ocean than the next seven largest rivers combined. It is home to thousands of fish species, including the piranha, and serves as a vital transportation route for local communities. .', 'On which continent is the Amazon River located?', 'South America', 'Africa', NULL, '2026-02-28 04:52:34'),
(30, NULL, NULL, NULL, 'external', 'The Venus Flytrap is a carnivorous plant that grows in nitrogen-poor soil. To survive, it captures insects using specialized leaves that act like a snap trap. When a fly touches the sensitive hairs inside, the lobes close in less than a second. Enzymes then dissolve the prey, providing the plant with the essential nutrients it cannot find in the ground.', 'What does this plant use to dissolve its prey?', 'Enzymes', 'Water', NULL, '2026-02-28 05:19:04'),
(31, NULL, NULL, NULL, 'external', 'The Great Barrier Reef is the largest coral reef system on Earth, located off the coast of Australia. It is composed of billions of tiny organisms known as coral polyps. This massive underwater structure provides a vital habitat for thousands of marine species, including the colorful fish, mollusks, and sea turtles. Remarkably, it is even visible from outer space today.', 'What tiny organisms build this massive reef system?', 'Polyps', 'Plankton', NULL, '2026-02-28 05:21:09'),
(32, NULL, NULL, NULL, 'external', 'The giraffe is the tallest land mammal, easily recognized by its exceptionally long neck and the unique spotted coat. These gentle giants live in the African savannas, where they use their height to reach nutritious leaves at the tops of acacia trees that other animals cannot access. A giraffe\'s neck contains only seven vertebrae, which is the same number found in a human neck, but each bone is much larger. To pump blood all the way up to their brain, giraffes have incredibly powerful hearts. They also spend most of their lives standing up, even while they are sleeping. .', 'How many vertebrae are in a giraffe\'s long neck?', 'Seven', 'Twelve', NULL, '2026-02-28 05:22:24'),
(33, NULL, NULL, NULL, 'external', 'Johannes Gutenberg invented the printing press around 1440, sparking a revolution in the how information was shared across Europe. Before this invention, books were painstakingly copied by hand, making them expensive and rare. Gutenberg’s machine used movable metal type, allowing for the rapid production of many identical copies of a single text. This innovation made books more affordable and accessible to the general public, leading to a massive increase in literacy rates. The spread of knowledge through printed materials played a crucial role in the Renaissance and the Scientific Revolution, forever changing the course of human history and global communication.', 'What material was used for the movable type?', 'Metal', 'Wood', NULL, '2026-02-28 05:23:01'),
(34, NULL, NULL, NULL, 'external', 'The Aurora Borealis, or Northern Lights, is a stunning natural light display primarily seen in high-latitude regions near the Arctic. This phenomenon occurs when charged particles from the Sun collide with gases in Earth\'s atmosphere, such as oxygen and nitrogen. These collisions release energy in the form of vibrant colors, typically green, pink, and purple, which dance across the night sky. The specific colors depend on which gas is being struck and at what altitude the collision happens. While they look magical, these lights are actually a visual demonstration of the complex magnetic relationship between our planet and the Sun.', 'Which gas is responsible for the common green color?', 'Oxygen', 'Hydrogen', NULL, '2026-02-28 05:23:33'),
(35, NULL, NULL, NULL, 'external', 'The Venus Flytrap is a unique carnivorous plant native to the wetlands of the North and the South Carolina. Because the soil in these areas lacks essential nutrients like nitrogen, the plant has evolved to supplement its diet by catching insects. Its leaves act as a specialized trap with sensitive hairs on the inner surface. When an insect touches these hairs twice within twenty seconds, the trap snaps shut in less than a second. The plant then secretes enzymes to digest its prey over several days. This fascinating adaptation allows the Venus Flytrap to survive in very harsh, nutrient-poor environments.', 'What essential nutrient does the plant seek from insects?', 'Nitrogen', 'Carbon', NULL, '2026-02-28 05:25:11'),
(36, NULL, NULL, NULL, 'external', 'The Panama Canal is a famous man-made waterway that connects the Atlantic and Pacific Oceans, significantly shortening the journey for ships traveling between them. Completed in 1914, this engineering marvel eliminates the need for vessels to sail around the dangerous southern tip of South America. The canal uses a system of locks to lift ships eighty-five feet above sea level to reach an artificial lake, then lowers them back down on the other side. Thousands of workers faced extreme heat and tropical diseases during its difficult construction. Today, it remains one of the most important trade routes in the world.', 'In which year was this major waterway completed?', '1914', '1941', NULL, '2026-02-28 05:25:35'),
(37, NULL, NULL, NULL, 'external', 'The giant panda is a bear species native to the high-altitude mountain forests of the central China. Almost ninety-nine percent of their diet consists of bamboo , and they must eat up to eighty-four pounds of it every single day to maintain their energy. To help them grip the slippery bamboo stalks, pandas have evolved a unique \"pseudo-thumb,\" which is actually an enlarged wrist bone. Despite their large size, pandas are excellent climbers and can swim well when necessary. Although they were once an endangered species, conservation efforts have successfully helped their population numbers grow steadily in the wild. .', 'What body part functions as the panda\'s pseudo-thumb?', 'Wrist', 'Elbow', NULL, '2026-02-28 05:27:19');

-- --------------------------------------------------------

--
-- Table structure for table `bonus`
--

CREATE TABLE `bonus` (
  `bonus_id` int(11) NOT NULL,
  `prediction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `part_no` int(11) NOT NULL,
  `bonus_amount` decimal(10,2) NOT NULL CHECK (`bonus_amount` >= 0),
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bonus`
--

INSERT INTO `bonus` (`bonus_id`, `prediction_id`, `user_id`, `story_id`, `part_no`, `bonus_amount`, `awarded_at`) VALUES
(17, 18, 63, 10, 2, 96.00, '2026-02-28 04:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `character_quiz`
--

CREATE TABLE `character_quiz` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `part_id` int(11) NOT NULL,
  `dialogue` text NOT NULL,
  `character` varchar(100) NOT NULL,
  `distractors` varchar(500) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `character_quiz`
--

INSERT INTO `character_quiz` (`id`, `story_id`, `part_id`, `dialogue`, `character`, `distractors`, `created_by`, `created_at`) VALUES
(17, 10, 8, '\"I\'ll find out who.\"', 'Dr. Aris Thorne', '[\"Helios Security\",\"Dr. Lena Petrova\"]', NULL, '2026-03-22 06:30:49'),
(18, 10, 8, '\"Everything seemed to be within parameters. Yet, a nagging doubt persisted.\"', 'Dr. Aris Thorne', '[\"Narrator\",\"Dr. Lena Petrova\"]', NULL, '2026-03-22 06:30:49'),
(19, 10, 8, '\"Temperature spikes, power dips... it could be a failing power regulator.\"', 'Dr. Lena Petrova', '[\"The System Voice\",\"Project Chimera AI\"]', NULL, '2026-03-22 06:30:49'),
(20, 10, 8, '\"Aris, we\'re pushing the boundaries of neuroscience here.\"', 'Dr. Lena Petrova', '[\"Dr. Aris Thorne\",\"The System Voice\"]', NULL, '2026-03-22 06:30:49'),
(21, 10, 8, '\"You\'re overwrought... Your mind is creating enemies where there are none.\"', 'Dr. Lena Petrova', '[\"The System Voice\",\"Helios Director\"]', NULL, '2026-03-22 06:30:49'),
(22, 10, 8, '\"Subject 734, neural reintegration simulation complete. Proceeding to Phase 2.\"', 'The System Voice', '[\"Dr. Aris Thorne\",\"Dr. Lena Petrova\"]', NULL, '2026-03-22 06:30:49'),
(23, 10, 8, '\"Hiccups? Or deliberate interference?\"', 'Dr. Aris Thorne', '[\"Helios Security\",\"Dr. Lena Petrova\"]', NULL, '2026-03-22 06:30:49'),
(24, 10, 8, '\"Complex systems, especially ones integrating so many disparate modules, have hiccups.\"', 'Dr. Lena Petrova', '[\"Dr. Aris Thorne\",\"Project Chimera AI\"]', NULL, '2026-03-22 06:30:49'),
(25, 10, 8, '\"Just a minor anomaly. System hiccup, perhaps.\"', 'DR. Aris Thorne', '[\"Dr. Lena Petrova\",\"The system voice\"]', NULL, '2026-03-22 06:30:49');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `edited_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `sender_id`, `receiver_id`, `message_text`, `is_read`, `is_edited`, `is_deleted`, `created_at`, `edited_at`) VALUES
(1, 0, 61, 'hi need response about ur story', 0, 0, 0, '2026-04-06 06:13:22', NULL),
(2, 0, 61, 'hi', 0, 0, 0, '2026-04-06 06:14:36', NULL),
(3, 0, 61, 'ho', 0, 0, 0, '2026-04-06 06:20:46', NULL),
(4, 0, 61, 'hlo', 0, 0, 0, '2026-04-06 06:29:04', NULL),
(5, 67, 61, 'hlo', 1, 0, 0, '2026-04-06 06:40:49', NULL),
(6, 61, 67, 'so what about my response', 1, 0, 0, '2026-04-06 06:41:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chat_read_status`
--

CREATE TABLE `chat_read_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `other_user_id` int(11) DEFAULT NULL,
  `last_read_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_read_status`
--

INSERT INTO `chat_read_status` (`id`, `user_id`, `other_user_id`, `last_read_at`) VALUES
(1, 61, NULL, '2026-04-05 15:17:55'),
(2, 61, NULL, '2026-04-05 15:20:19'),
(3, 0, NULL, '2026-04-05 15:26:22'),
(4, 66, NULL, '2026-04-05 15:27:08'),
(5, 0, 61, '2026-04-06 06:29:08'),
(6, 0, 66, '2026-04-05 15:27:28'),
(8, 0, NULL, '2026-04-05 15:27:25'),
(11, 0, NULL, '2026-04-05 15:27:29'),
(12, 0, NULL, '2026-04-05 15:28:30'),
(13, 66, NULL, '2026-04-05 15:28:38'),
(14, 0, NULL, '2026-04-05 15:28:47'),
(15, 66, NULL, '2026-04-05 15:30:23'),
(16, 61, NULL, '2026-04-06 06:11:44'),
(17, 61, NULL, '2026-04-06 06:12:24'),
(18, 0, NULL, '2026-04-06 06:13:05'),
(20, 0, NULL, '2026-04-06 06:13:26'),
(21, 0, NULL, '2026-04-06 06:14:22'),
(23, 61, NULL, '2026-04-06 06:14:45'),
(24, 61, NULL, '2026-04-06 06:15:59'),
(25, 0, NULL, '2026-04-06 06:18:14'),
(26, 0, NULL, '2026-04-06 06:18:19'),
(27, 0, NULL, '2026-04-06 06:18:22'),
(29, 0, NULL, '2026-04-06 06:18:36'),
(31, 0, NULL, '2026-04-06 06:18:43'),
(32, 61, NULL, '2026-04-06 06:18:56'),
(34, 0, NULL, '2026-04-06 06:24:07'),
(36, 0, NULL, '2026-04-06 06:24:17'),
(37, 61, NULL, '2026-04-06 06:26:29'),
(38, 0, NULL, '2026-04-06 06:28:59'),
(40, 0, NULL, '2026-04-06 06:29:07'),
(42, 0, NULL, '2026-04-06 06:29:09'),
(43, 61, NULL, '2026-04-06 06:29:57'),
(44, 0, NULL, '2026-04-06 06:36:39'),
(45, 61, NULL, '2026-04-06 06:40:30'),
(46, 67, NULL, '2026-04-06 06:40:42'),
(47, 67, 61, '2026-04-06 06:41:26'),
(48, 61, 67, '2026-04-06 06:51:56'),
(49, 61, NULL, '2026-04-06 06:40:55'),
(50, 67, NULL, '2026-04-06 06:41:03'),
(51, 67, NULL, '2026-04-06 06:41:07'),
(53, 67, NULL, '2026-04-06 06:41:30'),
(55, 61, NULL, '2026-04-06 06:42:34'),
(57, 61, NULL, '2026-04-06 06:45:04'),
(59, 61, NULL, '2026-04-06 06:45:34'),
(61, 61, NULL, '2026-04-06 06:46:05'),
(63, 61, NULL, '2026-04-06 06:51:56');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `comment_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `part_number` int(11) DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `parent_comment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `sentiment` enum('positive','neutral','negative') DEFAULT NULL,
  `sentiment_score` float DEFAULT NULL,
  `manually_flagged` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`comment_id`, `story_id`, `part_number`, `user_id`, `comment_text`, `parent_comment_id`, `created_at`, `updated_at`, `sentiment`, `sentiment_score`, `manually_flagged`) VALUES
(8, 10, 1, 63, 'the story is amazing', NULL, '2026-02-18 12:45:08', NULL, 'positive', 0.947661, 0);

-- --------------------------------------------------------

--
-- Table structure for table `follows`
--

CREATE TABLE `follows` (
  `follow_id` int(11) NOT NULL,
  `follower_user_id` int(11) NOT NULL,
  `author_user_id` int(11) NOT NULL,
  `followed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `follows`
--

INSERT INTO `follows` (`follow_id`, `follower_user_id`, `author_user_id`, `followed_at`) VALUES
(4, 63, 61, '2026-02-18 12:37:49');

--
-- Triggers `follows`
--
DELIMITER $$
CREATE TRIGGER `after_follow_delete` AFTER DELETE ON `follows` FOR EACH ROW BEGIN
    UPDATE users SET follower_count = follower_count - 1 WHERE user_id = OLD.author_user_id;
    UPDATE users SET following_count = following_count - 1 WHERE user_id = OLD.follower_user_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_follow_insert` AFTER INSERT ON `follows` FOR EACH ROW BEGIN
    UPDATE users SET follower_count = follower_count + 1 WHERE user_id = NEW.author_user_id;
    UPDATE users SET following_count = following_count + 1 WHERE user_id = NEW.follower_user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `game_scores`
--

CREATE TABLE `game_scores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `story_part_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `correct_pairs` int(11) DEFAULT NULL,
  `total_pairs` int(11) DEFAULT NULL,
  `time_left_seconds` int(11) DEFAULT NULL,
  `played_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `game_scores`
--

INSERT INTO `game_scores` (`id`, `user_id`, `story_part_id`, `score`, `correct_pairs`, `total_pairs`, `time_left_seconds`, `played_at`) VALUES
(1, 63, 8, 100, 3, 3, 82, '2026-03-21 14:26:49'),
(2, 63, 8, 100, 5, 5, 105, '2026-03-21 14:28:17'),
(3, 63, 8, 0, 0, 5, 168, '2026-03-21 14:28:32'),
(4, 63, 8, 25, 1, 4, 135, '2026-03-21 14:37:40'),
(5, 63, 8, 33, 1, 3, 177, '2026-03-21 14:37:50'),
(6, 63, 8, 40, 2, 5, 179, '2026-03-21 14:37:55'),
(7, 63, 8, 25, 1, 4, 180, '2026-03-21 14:38:03'),
(8, 63, 8, 67, 2, 3, 179, '2026-03-21 14:41:59'),
(9, 63, 8, 33, 1, 3, 178, '2026-03-21 14:42:55'),
(10, 63, 8, 33, 1, 3, 178, '2026-03-21 14:43:02'),
(11, 63, 8, 20, 1, 5, 174, '2026-03-21 14:43:12'),
(12, 63, 8, 40, 2, 5, 179, '2026-03-21 14:53:06'),
(13, 63, 8, 33, 1, 3, 178, '2026-03-21 14:53:11'),
(14, 63, 8, 25, 1, 4, 179, '2026-03-21 14:55:23'),
(15, 63, 8, 0, 0, 5, 179, '2026-03-21 15:00:39'),
(16, 63, 8, 0, 0, 5, 178, '2026-03-21 15:00:44'),
(17, 63, 8, 50, 2, 4, 179, '2026-03-21 15:00:49'),
(18, 63, 8, 50, 2, 4, 178, '2026-03-22 05:04:13'),
(19, 63, 0, 0, 0, 5, 0, '2026-03-22 06:24:02'),
(20, 63, 0, 180, 2, 5, 0, '2026-03-22 06:24:28'),
(21, 63, 0, 310, 5, 9, 0, '2026-03-22 06:31:46'),
(22, 63, 0, 90, 1, 5, 0, '2026-03-22 06:40:25'),
(23, 63, 0, 180, 2, 4, 0, '2026-03-22 06:40:48'),
(24, 63, 0, 250, 4, 5, 0, '2026-03-22 06:42:32'),
(25, 63, 0, 0, 0, 4, 0, '2026-03-22 06:42:50'),
(26, 63, 0, 0, 0, 5, 0, '2026-03-22 06:43:52'),
(27, 63, 0, 190, 2, 4, 0, '2026-03-22 06:44:03'),
(28, 63, 0, 170, 2, 5, 0, '2026-03-22 06:48:28'),
(29, 63, 0, 170, 2, 5, 0, '2026-03-22 06:48:49'),
(30, 63, 0, 200, 2, 4, 0, '2026-03-22 06:49:00');

-- --------------------------------------------------------

--
-- Table structure for table `group_chat_messages`
--

CREATE TABLE `group_chat_messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `edited_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_chat_messages`
--

INSERT INTO `group_chat_messages` (`message_id`, `sender_id`, `message_text`, `is_edited`, `is_deleted`, `created_at`, `edited_at`) VALUES
(1, 61, 'hlo shall we join the new era feature', 0, 0, '2026-04-05 15:20:10', NULL),
(2, 66, 'yeah sure, not really o', 1, 0, '2026-04-05 15:28:15', '2026-04-06 06:14:22'),
(3, 0, 'don\'t send unwanted messages', 0, 0, '2026-04-06 06:13:43', NULL),
(4, 0, 'hello', 0, 0, '2026-04-06 06:14:28', NULL),
(5, 61, 'ho', 0, 1, '2026-04-06 06:16:58', NULL),
(6, 67, 'okay done', 0, 0, '2026-04-06 06:41:07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `likes`
--

CREATE TABLE `likes` (
  `like_id` int(11) NOT NULL,
  `prediction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `likes`
--

INSERT INTO `likes` (`like_id`, `prediction_id`, `user_id`, `liked_at`) VALUES
(26, 18, 64, '2026-02-28 04:26:52');

--
-- Triggers `likes`
--
DELIMITER $$
CREATE TRIGGER `after_like_delete` AFTER DELETE ON `likes` FOR EACH ROW BEGIN
    UPDATE stories SET like_count = like_count - 1 WHERE story_id = (SELECT story_id FROM predictions WHERE prediction_id = OLD.prediction_id);
    UPDATE users SET total_likes = total_likes - 1 WHERE user_id = (SELECT created_by FROM stories WHERE story_id = (SELECT story_id FROM predictions WHERE prediction_id = OLD.prediction_id));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_like_insert` AFTER INSERT ON `likes` FOR EACH ROW BEGIN
    UPDATE stories SET like_count = like_count + 1 WHERE story_id = (SELECT story_id FROM predictions WHERE prediction_id = NEW.prediction_id);
    UPDATE users SET total_likes = total_likes + 1 WHERE user_id = (SELECT created_by FROM stories WHERE story_id = (SELECT story_id FROM predictions WHERE prediction_id = NEW.prediction_id));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` enum('follow','like','prediction','comment','new_part','bonus_earned') NOT NULL,
  `related_user_id` int(11) DEFAULT NULL,
  `related_story_id` int(11) DEFAULT NULL,
  `related_prediction_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `predictions`
--

CREATE TABLE `predictions` (
  `prediction_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `prediction_part_no` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `prediction_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_edited` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_bonus_eligible` tinyint(1) DEFAULT 0,
  `manual_accuracy` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `predictions`
--

INSERT INTO `predictions` (`prediction_id`, `story_id`, `prediction_part_no`, `user_id`, `prediction_text`, `created_at`, `is_edited`, `updated_at`, `is_bonus_eligible`, `manual_accuracy`) VALUES
(18, 10, 1, 63, 'My gut says this isn\'t just about Aris. What if the body on the \r\nbed isn\'t Aris\'s, but someone else\'s? Or perhaps, the \'Revelation Chamber\' is where \r\nmultiple consciousnesses are being simulated or combined. The \'Patient: Aris Thorne\' \r\nrefers to his mind being the host, but the \'memory retrieval\' means they are extracting \r\nmemories from others and funneling them through his brain. The \'sabotage\' and \r\nglitches are actually interference from another mind trying to escape or warn him. \r\nPart 2 will reveal a sinister project where minds are being exploited or merged, and \r\nAris now holds fragments of other people\'s memories, which will lead him to a much \r\nlarger conspiracy.', '2026-02-18 12:32:03', 1, '2026-04-05 13:53:59', 1, 95.75),
(19, 10, 1, 64, 'The Helios Complex is a spaceship and the \'Memory Retrieval\' is a way to store data for the long journey.', '2026-02-28 04:23:37', 1, '2026-03-20 14:08:32', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `prediction_likes`
--

CREATE TABLE `prediction_likes` (
  `id` int(11) NOT NULL,
  `prediction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stories`
--

CREATE TABLE `stories` (
  `story_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_by` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT 'General',
  `description` text NOT NULL,
  `cover_image_url` varchar(255) DEFAULT NULL,
  `current_part_no` int(11) DEFAULT 1,
  `is_completed` tinyint(1) DEFAULT 0,
  `total_parts` int(11) DEFAULT 1,
  `view_count` int(11) DEFAULT 0,
  `like_count` int(11) DEFAULT 0,
  `prediction_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stories`
--

INSERT INTO `stories` (`story_id`, `title`, `created_by`, `category`, `description`, `cover_image_url`, `current_part_no`, `is_completed`, `total_parts`, `view_count`, `like_count`, `prediction_count`, `created_at`, `completed_at`, `last_updated`, `status`) VALUES
(10, 'The simulacrum key', 'jon@th@n908', 'Thriller', 'Dr. Aris Thorne is a brilliant neuroscientist leading Project Chimera in an isolated, high-tech complex. The project aims to digitally preserve human memory. However, Aris starts experiencing unsettling glitches in the system, data corruption, and even phantom presences, convincing him that someone is sabotaging his groundbreaking work. He believes there\'s an unseen intruder operating within the highly secure facility.\r\n\r\nDriven by this suspicion, Aris meticulously tracks the anomalies, which all seem to originate from the mysterious and deeply inaccessible \"Revelation Chamber\" – a room he was told was for future, non-existent technology. He bypasses layers of security to infiltrate the chamber, expecting to confront the saboteur.', 'https://miro.medium.com/v2/resize:fit:1100/format:webp/1*_719kxyjaeVaVNX4AO3gxA.jpeg', 2, 0, 2, 2, 3, 0, '2026-02-18 12:29:48', NULL, '2026-03-29 14:06:36', 'approved'),
(11, 'The Last Signal', '$teve3n@ncy', 'Thriller', 'A series of mysterious deaths linked by a cryptic signal shakes the city. As a young investigator dives deeper, he uncovers a hidden network manipulating lives from the shadows. But the closer he gets to the truth, the more he realizes—he was never just investigating the case… he was part of it.', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRa9T7IsljwZ_7xcW3lpwMr98k_gUDrHz2nEw&s', 1, 0, 3, 1, 0, 0, '2026-03-29 03:07:00', NULL, '2026-03-29 14:01:13', 'approved');

--
-- Triggers `stories`
--
DELIMITER $$
CREATE TRIGGER `after_story_delete` AFTER DELETE ON `stories` FOR EACH ROW BEGIN
    UPDATE users SET total_stories = total_stories - 1 WHERE user_id = OLD.created_by;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_story_insert` AFTER INSERT ON `stories` FOR EACH ROW BEGIN
    UPDATE users SET total_stories = total_stories + 1 WHERE user_id = NEW.created_by;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `story_likes`
--

CREATE TABLE `story_likes` (
  `story_like_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `liked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `story_likes`
--

INSERT INTO `story_likes` (`story_like_id`, `story_id`, `user_id`, `liked_at`) VALUES
(2, 10, 63, '2026-02-18 12:31:30'),
(3, 10, 64, '2026-02-28 04:22:09');

--
-- Triggers `story_likes`
--
DELIMITER $$
CREATE TRIGGER `after_story_like_delete` AFTER DELETE ON `story_likes` FOR EACH ROW BEGIN
    UPDATE stories SET like_count = like_count - 1 WHERE story_id = OLD.story_id;
    UPDATE users SET total_likes = total_likes - 1 
    WHERE user_name = (SELECT created_by FROM stories WHERE story_id = OLD.story_id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_story_like_insert` AFTER INSERT ON `story_likes` FOR EACH ROW BEGIN
    UPDATE stories SET like_count = like_count + 1 WHERE story_id = NEW.story_id;
    UPDATE users SET total_likes = total_likes + 1 
    WHERE user_name = (SELECT created_by FROM stories WHERE story_id = NEW.story_id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `story_parts`
--

CREATE TABLE `story_parts` (
  `part_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `part_number` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `upload_date` date NOT NULL,
  `prediction_deadline` datetime NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `story_parts`
--

INSERT INTO `story_parts` (`part_id`, `story_id`, `part_number`, `content`, `upload_date`, `prediction_deadline`, `status`) VALUES
(8, 10, 1, 'The Simulacrum Key\r\nPart 1: The Glitch in the Machine\r\nChapter 1: The Phantom Protocol\r\nThe hum of the servers was a constant, low thrum against the sterile silence of the Helios Complex. Dr. Aris Thorne traced a finger over the glowing schematic on his display, the intricate neural pathways of Project Chimera a mesmerizing dance of light and shadow. For two years, this isolated, state-of-the-art facility, nestled deep within the unforgiving deserts of Nevada, had been his world. It was a world of pure science, where the very essence of human memory was being deconstructed, mapped, and, hopefully, eventually restored.\r\n\r\nAris was a neuroscientist of rare brilliance, his mind as sharp and precise as the surgical instruments he once wielded in a past life he barely remembered. His current endeavor, Project Chimera, aimed to create a complete digital simulacrum of a human mind, capable of housing and processing fragmented memories. The implications were monumental: a cure for amnesia, a means to preserve consciousness, perhaps even a path to a rudimentary form of immortality. The work was demanding, often consuming, but the potential reward eclipsed every sleepless night.\r\n\r\nTonight, however, the familiar hum felt… off. A subtle dissonance, like a single violin string vibrating out of tune in an otherwise perfect orchestra. He dismissed it as fatigue, a byproduct of the 72-hour push to finalize the data for their first major cognitive transfer test.\r\n\r\nHe scrolled through the latest neural scans, the complex web of neurons glowing in vibrant blues and greens. Everything seemed to be within parameters. Yet, a nagging doubt persisted. He\'d input a specific sequence of historical data a few hours ago, a test run for the system\'s analytical capabilities. Now, a small segment of that data appeared corrupted – a mere few kilobytes of garbled code where a detailed historical timeline should have been.\r\n\r\n\"Just a minor anomaly,\" he murmured to himself, tapping his stylus against the screen. \"System hiccup, perhaps.\"\r\n\r\nHe ran a diagnostic, the progress bar creeping across the screen. Dr. Lena Petrova, his pragmatic and equally brilliant colleague, would scoff at his paranoia. Lena, with her sharp intellect and even sharper wit, always brought him back to earth. She believed in hard data, verifiable facts, not the vague intuitive shifts Aris sometimes experienced.\r\n\r\nHe leaned back in his ergonomic chair, rubbing his temples. The air in the facility, recycled and meticulously filtered, felt heavy tonight. He glanced at the reinforced window, but all that greeted him was the obsidian expanse of the desert night, punctuated by the occasional flicker of distant heat lightning. No connection to the outside world, save for encrypted satellite links and heavily guarded supply convoys that arrived once a month. Total isolation was a prerequisite for Project Chimera’s secrecy.\r\n\r\nThe diagnostic completed, confirming a \"minor data integrity error.\" Easily fixable, the report assured him. But as he initiated the repair protocol, a peculiar sensation washed over him – a fleeting flicker of déjà vu, a brief, unsettling feeling that he had experienced this exact moment, this precise digital glitch, countless times before. He shook his head, blaming the late hour. Too much coffee, too little sleep.\r\n\r\nHe began the tedious process of re-uploading the corrupted data. As the new data streamed, he noticed a subtle, almost imperceptible shift in the facility’s ambient lighting, a slight dimming and then an immediate brightening. It was too quick, too faint to be a conscious observation, more like a trick of the eye, or a momentary flicker in his own consciousness. He blinked, cleared his throat, and focused back on the glowing lines of code. The hum, however, persisted, a discordant note beneath the surface calm.\r\n\r\nChapter 2: Whispers in the Wires\r\nThe \"minor anomaly\" was anything but. Over the next few days, the glitches escalated with an unsettling regularity. First, it was the ventilation system sputtering for a few seconds before roaring back to life. Then, the automated delivery drones, usually gliding silently along their designated paths, would occasionally stall mid-air, their internal lights blinking erratically before resuming their programmed routes.\r\n\r\nAris found himself logging every incident in a private, encrypted file on his personal tablet. Lena, when he brought up his concerns, attributed them to the growing pains of a bleeding-edge system.\r\n\r\n\"Aris, we\'re pushing the boundaries of neuroscience here,\" she\'d said, her voice calm and rational, as they walked through the humming corridors. \"Complex systems, especially ones integrating so many disparate modules, have hiccups. It\'s expected.\"\r\n\r\n\"Hiccups? Or deliberate interference?\" Aris countered, his gaze sweeping the pristine, unblemished walls. \"The data loss on the neural mapping modules, Lena. That wasn\'t a \'hiccup.\' That was surgical.\"\r\n\r\nLena had merely offered a tired smile. \"Paranoia, Aris. You\'re too invested. Step back, get some rest. You\'ll see it\'s just the system settling.\"\r\n\r\nBut Aris couldn\'t shake the feeling. He started noticing more than just system failures. He caught glimpses of things that seemed out of place. A maintenance bot, usually confined to Sector Gamma, briefly appearing in Sector Delta before vanishing around a corner. A cleaning drone, meant to be silent, emitting a faint, almost melodic hum that was distinctly different from the usual whir.\r\n\r\nHis own memory became a source of growing unease. There were blank spots, moments he couldn\'t quite recall, like fleeting dreams just beyond his grasp. He’d be mid-sentence, about to mention an observation or a past conversation, and the details would simply evaporate. He blamed stress, again, the crushing pressure of Project Chimera nearing its critical phase. But deep down, a colder, more unsettling thought began to take root: was his mind, too, being tampered with?\r\n\r\nOne evening, while Lena was in the core testing labs, Aris decided to take matters into his own hands. He accessed the facility\'s extensive surveillance network. The feeds were standard, crisp, and high-definition. Nothing overtly suspicious. Yet, as he fast-forwarded through the previous night\'s footage of the main server hub, he paused.\r\n\r\nA flicker. So fast, he almost missed it. A shadow, not quite human, moving with impossible speed across the screen. It was gone before he could truly register its form, a mere ghost in the machine. He rewound, played it again, frame by agonizing frame. There it was, undeniable. A brief, indistinct blur. It wasn\'t a person. It was… something else. An energy signature? A brief visual distortion?\r\n\r\nHe zoomed in, the pixels blurring into abstract art. It was impossible to identify. But it was there. And it wasn\'t a system hiccup. Someone, or something, was moving through the secure areas of the Helios Complex, unseen and unheard. And Aris was going to find out what.\r\n\r\nChapter 3: The Ghost in the Server Room\r\nThe blurred image from the surveillance feed became Aris’s obsession. He spent hours trying to enhance it, applying every digital filter and algorithm he knew, but the elusive shape remained just that – a shape, devoid of detail. It was enough, however, to fuel his certainty: there was an intruder.\r\n\r\nHe decided to set up his own discreet surveillance. Using discarded micro-drones from a previous, defunct project, he repurposed them into miniature cameras, no larger than his thumbnail. He meticulously placed them in strategic locations – concealed within ventilation shafts, tucked behind server racks, even embedded in the false ceiling panels of key corridors. He linked them to his private tablet, bypassing the facility\'s main network, creating a ghost network of his own.\r\n\r\nThe first few days yielded nothing but Lena\'s predictable routines and the monotonous comings and goings of the automated maintenance units. Then, late one night, as Aris monitored his feeds from his dimly lit office, a flicker appeared on one of the server room cameras. Not the faint blur from before, but a distinct disruption.\r\n\r\nThe temperature gauges in the server room spiked erratically, then plummeted. Power fluctuations danced across the screen, mimicking a small, localized EMP burst. And for a fraction of a second, the light-sensitive cameras picked up a faint, almost ethereal glow emanating from behind one of the massive server banks – a pulsating, blue-white light that pulsed once, twice, and then vanished.\r\n\r\nThis was not a system bug. This was deliberate. This was a direct, intelligent interference. Someone, or something, was actively manipulating the core systems of Project Chimera.\r\n\r\nHe stormed to Lena\'s private quarters, ignoring the late hour. He found her hunched over her own terminal, the soft glow illuminating her tired face.\r\n\r\n\"Aris? What is it? You look like you\'ve seen a ghost.\"\r\n\r\n\"Worse,\" he practically hissed, pulling up the anomaly on his tablet. \"Look. The server room. This isn\'t a malfunction, Lena. Someone is actively destabilizing our project. They\'re in the core systems.\"\r\n\r\nLena studied the data, her brow furrowed. \"Temperature spikes, power dips... it could be a failing power regulator, Aris. Or a micro-fracture in the quantum entanglement chips. These things are delicate.\"\r\n\r\n\"And the light? The rapid fluctuations? The precise pattern of data loss that directly impacts Chimera\'s primary neural network?\" He slammed his hand on the desk, the sound echoing unnaturally loud in the quiet room. \"This is targeted. This is sabotage.\"\r\n\r\nShe sighed, running a hand through her hair. \"You\'re overwrought. We\'re on the cusp of something extraordinary. Your mind is creating enemies where there are none. We are the only two humans here, Aris. Who else could it be?\"\r\n\r\nHer words stung, a mix of concern and dismissal. He pulled back, his jaw tight. \"I\'ll find out who.\"\r\n\r\nHe retreated, leaving Lena to her skepticism. He spent the rest of the night reviewing every second of his private feeds, cross-referencing every anomaly with the facility’s schematics. He charted the path of the phantom disturbances, the timing of the data corruptions, the subtle shifts in the ambient energy readings. A pattern began to emerge, faint but undeniable. All the strange events, all the disruptions, seemed to emanate from a single, highly secure location within the Helios Complex. Not the server room, but a chamber even deeper, even more inaccessible.\r\n\r\nThe \"Revelation Chamber.\"\r\n\r\nChapter 4: The Revelation Chamber\r\nThe Revelation Chamber. It was a theoretical space, mentioned only in the highest-level blueprints of the Helios Complex – a secure, heavily shielded chamber designed for \"advanced neural interfacing and extreme data compression.\" No one had ever been granted access, not even Aris or Lena. It was a future-proofing measure, a space for technology that didn\'t yet exist. Or so they had been told.\r\n\r\nBut now, every corrupted data packet, every flickering light, every phantom presence, seemed to lead back to it. The subtle energy signatures he’d been tracking pulsed strongest from its direction. The \"saboteur,\" he realized, wasn\'t just disrupting Project Chimera; they were using the project\'s own systems, its very infrastructure, as a weapon.\r\n\r\nAris knew he had to get in. He spent the next day dissecting the security protocols for the Revelation Chamber. He found a back door – not a deliberate vulnerability, but a legacy override protocol from an older security system, meant for catastrophic emergency shutdowns. It was deeply buried, requiring multiple biometric authentications and a precise sequence of manual overrides at three different checkpoints across the facility. It would take hours, and he would have to be completely undetected.\r\n\r\nThat night, under the guise of an extended data consolidation session, Aris began his silent assault. He moved like a shadow through the quiet corridors, the hum of the servers now a distant, mocking thrum. He bypassed laser grids, deactivated pressure sensors, and meticulously entered override codes, his fingers flying across keypads, his heart hammering against his ribs. Each successful bypass was a small victory, pushing him deeper into the heart of the complex.\r\n\r\nFinally, he stood before the Revelation Chamber\'s formidable door. It was a seamless expanse of reinforced durasteel, with no visible seams or handles, only a single, glowing retinal scanner. He placed his eye against the scanner, holding his breath. A soft whirring sound, a green light flashed, and then, with a low hiss, the door retracted, revealing a stark, dimly lit interior.\r\n\r\nHe stepped inside, his hand instinctively reaching for the non-existent pistol he wished he carried. The air was cool, heavy with the scent of ozone and something else – something metallic and faintly organic. The chamber was not empty.\r\n\r\nIn the center of the room stood a single, massive console, unlike anything else in the Helios Complex. It hummed with a low, powerful energy. Its screen, a vast curved display, was alive with glowing data streams, complex algorithms, and a schematic of what looked like a human brain, pulsating with an internal light.\r\n\r\nAris approached cautiously, his gaze fixed on the screen. He expected to find evidence of the saboteur – their identity, their motive, their insidious plan. He expected to see some nefarious code being injected into Project Chimera.\r\n\r\nInstead, as he drew closer, a single line of text on the screen burned into his vision, clear and undeniable, pulling the floor out from under him.\r\n\r\nPATIENT: ARIS THORNE\r\nSUBJECT ID: 734\r\nNEURAL REINTEGRATION SIMULATION COMPLETE.\r\n\r\nBelow it, a live video feed shimmered into existence on a smaller panel. It showed a pristine, white room. A sterile, unfamiliar environment. And in the very center of that room, on a high-tech medical bed, lay a figure.\r\n\r\nIt was him.\r\n\r\nHis own body. Lying still, unconscious, hooked up to an intricate array of wires and monitors. A thin tube snaked from his nose, another from his arm. His face was pale, serene, utterly unresponsive.\r\n\r\nAs he stared at the impossible image, a voice, calm and clinical, resonated not from the console’s speakers, but directly in his mind, clear as if spoken beside him.\r\n\r\n\"Subject 734, neural reintegration simulation complete. Proceeding to Phase 2: Memory Retrieval.\"\r\n\r\nThe world around him, the sleek console, the humming chamber, the very air he breathed – it all flickered. Pixels bloomed like dying stars, dissolving into static. The hum died.\r\n\r\nThen, blackness. Utter, absolute blackness.', '2026-02-18', '2026-03-23 13:27:00', 'approved'),
(9, 10, 2, 'The Simulacrum Key\r\nPart 2: Echoes in the Grey\r\nChapter 5: The Architect of Dreams\r\nThe blackness shattered, not into light, but into a blinding white. Aris blinked, disoriented, his senses re-calibrating. The ozone scent was gone, replaced by the faint, sterile tang of antiseptic. The hum he had grown accustomed to was now a high-pitched, insistent beep. He felt heavy, strangely disconnected from his own limbs.\r\n\r\nHe was lying down. On a bed. He tried to move, but his muscles felt like lead. His vision slowly cleared. Above him, a pristine white ceiling, a single, recessed light glowing with an almost surgical intensity. He turned his head, a monumental effort, and saw a familiar face, etched with worry and exhaustion, leaning over him.\r\n\r\n\"Aris? Can you hear me?\"\r\n\r\nIt was Lena. But her hair was streaked with more grey, her eyes deeper, and there were faint lines of fatigue around her mouth that hadn\'t been there before. Her lab coat, usually crisp, seemed a little rumpled.\r\n\r\n\"Lena?\" His voice was a rasp, alien to his own ears. \"What... what happened? The Revelation Chamber? The simulation?\"\r\n\r\nLena\'s gaze was soft, pitying. \"The Revelation Chamber, yes. But not a simulation, Aris. Not in the way you think.\" She paused, took a deep breath. \"You\'ve been in a coma for seven months.\"\r\n\r\nThe words hit him like a physical blow. Seven months? The Helios Complex, Project Chimera, the glitches, the phantom intruder, the race against sabotage… had it all been a dream? A vivid, terrifying hallucination?\r\n\r\n\"A coma?\" He tried to sit up, but Lena gently pushed him back down.\r\n\r\n\"A severe cerebral hemorrhage,\" she explained, her voice carefully modulated. \"Almost fatal. Your brain was hemorrhaging, and we were losing you. Project Chimera… Project Chimera was our last hope. Your project, Aris.\"\r\n\r\nHis project? But he was the lead researcher, the architect. Was he? His memory felt like a sieve.\r\n\r\n\"We adapted the neural reintegration protocol,\" Lena continued, gesturing vaguely around the room. \"We created a controlled cognitive environment within your own mind. A virtual construct, built from your subconscious, designed to repair the damage, to guide your neural pathways back to functionality.\"\r\n\r\n\"The Helios Complex?\" he whispered, a cold dread coiling in his gut.\r\n\r\n\"A recreation. Your mind\'s ideal representation of your work environment. We populated it with familiar elements, familiar faces, to make the reintegration process as smooth as possible.\" Lena\'s eyes met his, unwavering. \"I was a construct too, Aris. A part of your own mind, tasked with challenging your perceptions, pushing you towards the truth.\"\r\n\r\nThe realization slammed into him with the force of a tidal wave. The glitches, the anomalies, the phantom protocols, even the \"sabotage\" – it wasn\'t an external threat. It was his own damaged mind fighting to heal, his subconscious throwing up obstacles, testing the boundaries of its self-created reality. The \"intruder\" was the neurological repair process, the system debugging itself from within.\r\n\r\nAnd the Revelation Chamber. It wasn\'t a secret lab. It was the point of his brain where the healing was finally complete, the cognitive loop closing, bringing him back to conscious awareness.\r\n\r\n\"So, I was… trying to catch myself?\" Aris managed, a weak, disbelieving laugh escaping his lips.\r\n\r\nLena smiled, a genuine, relieved smile. \"In a way, yes. You were struggling against the very process that was saving you. It\'s a common psychological response to severe trauma. The mind creates narratives to explain the inexplicable.\"\r\n\r\nChapter 6: The Unveiling\r\nOver the next few weeks, Aris underwent intensive physical and cognitive therapy. His body was weak, his muscles atrophied, but his mind, miraculously, was intact. Better than intact, even. The process had not only repaired the damage but had somehow enhanced his cognitive functions. His recall was sharper, his analytical abilities even keener.\r\n\r\nLena meticulously explained the science behind it all. The \"Project Chimera\" he remembered was a concept he\'d been developing before the hemorrhage – a grand vision for neural restoration. The medical team had taken his theoretical work, combined it with advanced bio-feedback and holographic projection, and built a bespoke mental environment to facilitate his recovery.\r\n\r\n\"The objective was to allow your brain to re-establish neural connections organically,\" Lena explained during one of their debriefing sessions. \"By creating a familiar, yet subtly challenging environment, your subconscious was tricked into actively participating in its own repair.\"\r\n\r\nThe Helios Complex, the server hum, even the fleeting sense of déjà vu – all were carefully engineered stimuli, designed to provoke reactions that would aid in his healing. The \"corrupted data\" was actual neurological misfires being corrected. The \"maintenance bots\" and \"cleaning drones\" were representations of the microscopic nanobots working within his brain, clearing debris and repairing pathways.\r\n\r\n\"And you,\" Aris said, looking at Lena with new eyes. \"Every conversation, every argument… it was all part of the therapy.\"\r\n\r\nShe nodded. \"I had a strict protocol. To nudge you, to provoke you, to guide you without revealing the truth prematurely. If we had told you directly, your conscious mind might have resisted the healing process.\"\r\n\r\nThe revelation was disorienting, yet strangely liberating. The paranoia, the fear, the frantic search for an external enemy – it had all been an internal struggle. He was the patient, the doctor, and the antagonist, all rolled into one.\r\n\r\nOne afternoon, as Aris sat in the facility’s quiet common area, overlooking the actual desert, not the constructed version in his mind, he noticed a new display on a large screen on the wall. It was a digital rendering of a complex neural network, pulsing with vibrant colors. Beneath it, text scrolled: \"PROJECT CHIMERA - PHASE 2: CONSCIOUSNESS PRESERVATION.\"\r\n\r\nLena walked up beside him. \"The next step,\" she said, following his gaze. \"Your recovery has provided invaluable data. We now understand more about the mind\'s resilience, its capacity for self-repair, than we ever thought possible.\"\r\n\r\n\"So, my \'dream\' was essentially a highly advanced, personalized recovery program,\" Aris mused, a faint smile playing on his lips. \"And the \'simulacrum\' wasn\'t just a digital copy; it was my own mind, rebuilding itself.\"\r\n\r\n\"Precisely,\" Lena confirmed. \"And now, Aris, you\'re not just a recovered patient. You are the definitive case study, the living proof of concept. Your brain has essentially rewritten its own code.\"\r\n\r\nChapter 7: The True Key\r\nDays turned into weeks, then months. Aris not only recovered but thrived. He returned to his work on Project Chimera, but with a profoundly altered perspective. He wasn\'t just researching theoretical neural networks; he had experienced one from the inside. He understood its intricacies, its vulnerabilities, its astonishing capacity for self-organization.\r\n\r\nHe began designing Phase 2, focusing on the preservation of unique human experiences, not just raw data. The idea was to create a \"cognitive anchor\" – a personal, highly individualized mental construct that could be accessed and experienced by those suffering from severe memory loss, allowing them to reconnect with forgotten loved ones, pivotal life events, or even lost skills.\r\n\r\nOne evening, as he worked late, a familiar hum filled his office. Not the discordant hum of his simulated Helios Complex, but the deep, steady thrum of the real facility\'s power core. He felt a profound sense of peace. He was real. This facility was real. Lena was real.\r\n\r\nHe looked at a small, framed photograph on his desk – a picture of him and Lena, smiling, taken years ago, before his hemorrhage, before Project Chimera consumed their lives. He picked it up, tracing the outline of Lena’s face. He remembered the moment it was taken, the laughter, the fleeting warmth of the desert sun.\r\n\r\nThen, his eyes fell upon a detail he’d never noticed before, or perhaps had simply dismissed as insignificant. In the background of the photo, almost obscured by a potted plant, was a small, ornate antique letter opener, its handle gleaming faintly in the sunlight. It was identical to the one that had killed Elias Thorne in the simulated Sterling Lane.\r\n\r\nAris froze. His breath hitched.\r\n\r\nHe remembered the name, Elias Thorne, from the \"dream.\" The art restorer. The victim in the meticulously clean murder scene. He remembered the antique letter opener, the secret microfiche, the hidden tunnels beneath the city.\r\n\r\nHe had dismissed it all as a fabrication of his healing mind, a clever narrative designed by his subconscious. But what if it wasn\'t? What if, buried within the \"simulation\" designed to heal him, there were fragments of actual memories, not just his own, but something else entirely?\r\n\r\nHe had seen images on that Revelation Chamber console. Patient: Aris Thorne. Subject ID: 734. Neural Reintegration Simulation Complete. But also… a live video feed. His body, on a bed. And a voice: \"Proceeding to Phase 2: Memory Retrieval.\"\r\n\r\nHe had interpreted \"Memory Retrieval\" as his own memories being returned to him. But what if it meant retrieving other memories? Fragments of information from the outside world, filtered through the cognitive environment of his own mind, woven into a narrative to make sense of them?\r\n\r\nHe thought back to the \"corrupted data\" in the simulation. It was a historical timeline. And the \"ghost\" in the server room, the elusive blur, the energy fluctuations… what if those weren\'t just internal system glitches, but actual, subtle external data, bleeding into his cognitive construct, shaping the reality of his simulated world?\r\n\r\nHe looked at the letter opener in the photograph again. It was a common enough antique, he told himself. A coincidence. But the detailed knowledge of the smuggling tunnels, the obscure historical documents, the specific types of parchment… these were not things his own conscious mind had ever encountered. Where had they come from?\r\n\r\nA cold, unsettling possibility began to solidify in his mind, far more terrifying than any brain hemorrhage.\r\n\r\nWhat if Project Chimera wasn\'t just about restoring his memories?\r\n\r\nWhat if, while his mind was vulnerable, while he was in that long coma, the project had evolved, secretly, beyond its original mandate? What if the \"neural reintegration simulation\" was a brilliantly disguised, experimental interface… an unconscious mind, passively, unknowingly, processing and filtering external information?\r\n\r\nWhat if his \"healing journey\" was actually a highly sophisticated, hidden operation designed to extract classified, real-world data, perhaps even the memories of others, by feeding them through the unconscious cognitive processing power of his unique, recovering brain?\r\n\r\nAnd what if the murder of Elias Thorne, the hidden tunnels, the entire elaborate crime thriller he had experienced in his seven-month \"coma,\" was not a dream at all… but a memory? A real memory, uploaded, filtered, and processed by his brain, a memory of a crime he was never meant to know?\r\n\r\nThe true key, Aris realized with a sickening lurch, wasn\'t to unlock his own mind. It was to unlock the secrets that had been unknowingly deposited into it. And the real game, the real thriller, had only just begun.', '2026-03-24', '2026-04-04 13:30:00', 'approved'),
(10, 11, 1, 'The rain hadn’t stopped for three days.\r\n\r\nArjun Varma, a junior cybercrime analyst, stared at the flickering waveform on his monitor. It wasn’t just noise. It was a pattern—repeating every 17 seconds.\r\n\r\nAnd every time it appeared… someone died.\r\n\r\nThe first case was dismissed as suicide.\r\nA tech student. Found in his room. No signs of struggle.\r\n\r\nThen the second.\r\nA journalist. Same pattern. Same time gap.\r\n\r\nThen the third.\r\n\r\nThat’s when Arjun noticed it.\r\n\r\nA strange audio file on each victim’s device.\r\nUnnamed. Untraceable. Unplayable.\r\n\r\nExcept… Arjun played it.\r\n\r\nAt first, it sounded like static.\r\nBut when he isolated the frequency, something emerged—\r\n\r\nA whisper.\r\n\r\n“You’re already inside.”\r\n\r\nThat night, Arjun received an email.\r\n\r\nNo sender. No subject.\r\nJust one attachment.\r\n\r\nThe same audio file.\r\n\r\nBut this time…\r\nIt had his name on it.', '2026-03-29', '2026-04-05 05:04:00', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `story_views`
--

CREATE TABLE `story_views` (
  `view_id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `part_number` int(11) DEFAULT 1,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `story_views`
--

INSERT INTO `story_views` (`view_id`, `story_id`, `user_id`, `part_number`, `viewed_at`, `ip_address`) VALUES
(8, 10, 63, 1, '2026-02-18 12:31:25', '::1'),
(9, 10, 64, 1, '2026-02-28 04:22:03', '::1'),
(10, 11, 63, 1, '2026-03-29 14:01:13', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `user_type` enum('reader','author','admin') DEFAULT 'reader',
  `email_verified` tinyint(1) DEFAULT 0,
  `total_score` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_active` timestamp NULL DEFAULT NULL,
  `total_bonus` decimal(10,2) DEFAULT 0.00,
  `follower_count` int(11) DEFAULT 0,
  `following_count` int(11) DEFAULT 0,
  `total_likes` int(11) DEFAULT 0,
  `total_stories` int(11) DEFAULT 0,
  `total_views` int(11) DEFAULT 0,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=unverified, 1=verified (admin override or OTP complete)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_name`, `password`, `first_name`, `last_name`, `email`, `profile_picture`, `bio`, `website`, `instagram`, `user_type`, `email_verified`, `total_score`, `created_at`, `last_active`, `total_bonus`, `follower_count`, `following_count`, `total_likes`, `total_stories`, `total_views`, `is_verified`) VALUES
(61, 'jon@th@n908', '$2y$10$h5YGknfX/WmDn2vap94Ctenk2SC/K/9kDKagcDSW5rwmepgr0ixo2', 'jonathan', 'reck', 'rafkhanb01@gmail.com', NULL, NULL, NULL, NULL, 'author', 1, 0, '2025-12-30 05:58:03', NULL, 0.00, 1, 0, 2, 1, 2, 1),
(63, 'chathan', '$2y$10$zruat6N4RAXC4vguqGRXW.d6hB1Pn83VGmGRu3.ZxwnJruzS9COya', 'chathan', 'kutti', 'chathan123@gmail.com', NULL, NULL, NULL, NULL, 'reader', 1, 0, '2025-12-30 06:28:55', NULL, 0.00, 0, 1, 0, 0, 0, 0),
(64, 'abdu_my', '$2y$10$uSNVNwXU1isEgk5DJkDeYezuKw65lpd5bWIH9kfioMtp/kWcUB3m2', 'abdulla', 'khan', 'abdu@gmail.com', NULL, NULL, NULL, NULL, 'reader', 1, 0, '2026-02-28 04:14:04', NULL, 0.00, 0, 0, 0, 0, 0, 0),
(66, '$teve3n@ncy', '$2y$10$BVQo/tz6au5NV.JezIidPO0qP0Ls0y1mtea.LZ643Ln5BpmuGpvNu', 'Steve', 'Harrington', 'rafkhanb0@gmail.com', NULL, NULL, NULL, NULL, 'author', 1, 0, '2026-03-29 03:04:13', NULL, 0.00, 0, 0, 0, 1, 1, 0),
(67, 'admin', 'admin_placeholder', 'Admin', NULL, 'admin@storyverse.local', NULL, NULL, NULL, NULL, 'admin', 0, 0, '2026-04-06 06:40:42', NULL, 0.00, 0, 0, 0, 0, 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_story_dataset`
--
ALTER TABLE `admin_story_dataset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_story_dataset` (`story_id`),
  ADD KEY `idx_dataset_part` (`part_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `arena_progress`
--
ALTER TABLE `arena_progress`
  ADD PRIMARY KEY (`progress_id`),
  ADD UNIQUE KEY `unique_user_story_part` (`user_id`,`story_id`,`part_number`),
  ADD KEY `idx_user_progress` (`user_id`),
  ADD KEY `idx_story_progress` (`story_id`);

--
-- Indexes for table `arena_questions`
--
ALTER TABLE `arena_questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `idx_story_part` (`story_id`,`part_number`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_question_type` (`question_type`),
  ADD KEY `idx_story_part_type` (`story_id`,`part_number`,`question_type`);

--
-- Indexes for table `bonus`
--
ALTER TABLE `bonus`
  ADD PRIMARY KEY (`bonus_id`),
  ADD UNIQUE KEY `prediction_id` (`prediction_id`),
  ADD KEY `fk_bonus_story` (`story_id`),
  ADD KEY `fk_bonus_user` (`user_id`);

--
-- Indexes for table `character_quiz`
--
ALTER TABLE `character_quiz`
  ADD PRIMARY KEY (`id`),
  ADD KEY `story_id` (`story_id`),
  ADD KEY `part_id` (`part_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `chat_read_status`
--
ALTER TABLE `chat_read_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversation` (`user_id`,`other_user_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `fk_parent_comment` (`parent_comment_id`),
  ADD KEY `idx_story_comments` (`story_id`),
  ADD KEY `idx_user_comments` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `follows`
--
ALTER TABLE `follows`
  ADD PRIMARY KEY (`follow_id`),
  ADD UNIQUE KEY `unique_follow` (`follower_user_id`,`author_user_id`),
  ADD KEY `idx_follower` (`follower_user_id`),
  ADD KEY `idx_author` (`author_user_id`),
  ADD KEY `idx_followed_at` (`followed_at`);

--
-- Indexes for table `game_scores`
--
ALTER TABLE `game_scores`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_chat_messages`
--
ALTER TABLE `group_chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `prediction_id` (`prediction_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notification_related_user` (`related_user_id`),
  ADD KEY `fk_notification_story` (`related_story_id`),
  ADD KEY `fk_notification_prediction` (`related_prediction_id`),
  ADD KEY `idx_user_notifications` (`user_id`,`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `predictions`
--
ALTER TABLE `predictions`
  ADD PRIMARY KEY (`prediction_id`),
  ADD UNIQUE KEY `story_id` (`story_id`,`prediction_part_no`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `prediction_likes`
--
ALTER TABLE `prediction_likes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prediction_id` (`prediction_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`story_id`);

--
-- Indexes for table `story_likes`
--
ALTER TABLE `story_likes`
  ADD PRIMARY KEY (`story_like_id`),
  ADD UNIQUE KEY `unique_story_like` (`story_id`,`user_id`),
  ADD KEY `idx_story_likes` (`story_id`),
  ADD KEY `idx_user_story_likes` (`user_id`),
  ADD KEY `idx_liked_at` (`liked_at`);

--
-- Indexes for table `story_parts`
--
ALTER TABLE `story_parts`
  ADD PRIMARY KEY (`part_id`),
  ADD UNIQUE KEY `story_id` (`story_id`,`part_number`);

--
-- Indexes for table `story_views`
--
ALTER TABLE `story_views`
  ADD PRIMARY KEY (`view_id`),
  ADD KEY `idx_story_views` (`story_id`),
  ADD KEY `idx_user_views` (`user_id`),
  ADD KEY `idx_viewed_at` (`viewed_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_name` (`user_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `admin_story_dataset`
--
ALTER TABLE `admin_story_dataset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `arena_progress`
--
ALTER TABLE `arena_progress`
  MODIFY `progress_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `arena_questions`
--
ALTER TABLE `arena_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `bonus`
--
ALTER TABLE `bonus`
  MODIFY `bonus_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `character_quiz`
--
ALTER TABLE `character_quiz`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `chat_read_status`
--
ALTER TABLE `chat_read_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `follows`
--
ALTER TABLE `follows`
  MODIFY `follow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `game_scores`
--
ALTER TABLE `game_scores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `group_chat_messages`
--
ALTER TABLE `group_chat_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `likes`
--
ALTER TABLE `likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `predictions`
--
ALTER TABLE `predictions`
  MODIFY `prediction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `prediction_likes`
--
ALTER TABLE `prediction_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stories`
--
ALTER TABLE `stories`
  MODIFY `story_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `story_likes`
--
ALTER TABLE `story_likes`
  MODIFY `story_like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `story_parts`
--
ALTER TABLE `story_parts`
  MODIFY `part_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `story_views`
--
ALTER TABLE `story_views`
  MODIFY `view_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_story_dataset`
--
ALTER TABLE `admin_story_dataset`
  ADD CONSTRAINT `fk_dataset_part` FOREIGN KEY (`part_id`) REFERENCES `story_parts` (`part_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_story_dataset` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE;

--
-- Constraints for table `arena_progress`
--
ALTER TABLE `arena_progress`
  ADD CONSTRAINT `fk_ap_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `arena_questions`
--
ALTER TABLE `arena_questions`
  ADD CONSTRAINT `fk_aq_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_aq_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `bonus`
--
ALTER TABLE `bonus`
  ADD CONSTRAINT `fk_bonus_prediction` FOREIGN KEY (`prediction_id`) REFERENCES `predictions` (`prediction_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bonus_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bonus_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `character_quiz`
--
ALTER TABLE `character_quiz`
  ADD CONSTRAINT `character_quiz_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`),
  ADD CONSTRAINT `character_quiz_ibfk_2` FOREIGN KEY (`part_id`) REFERENCES `story_parts` (`part_id`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comment_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parent_comment` FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`comment_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `follows`
--
ALTER TABLE `follows`
  ADD CONSTRAINT `fk_author_followed` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_follower` FOREIGN KEY (`follower_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`prediction_id`) REFERENCES `predictions` (`prediction_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_prediction` FOREIGN KEY (`related_prediction_id`) REFERENCES `predictions` (`prediction_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notification_related_user` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notification_story` FOREIGN KEY (`related_story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `predictions`
--
ALTER TABLE `predictions`
  ADD CONSTRAINT `predictions_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `predictions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `story_likes`
--
ALTER TABLE `story_likes`
  ADD CONSTRAINT `fk_story_like_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_story_like_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `story_parts`
--
ALTER TABLE `story_parts`
  ADD CONSTRAINT `story_parts_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE;

--
-- Constraints for table `story_views`
--
ALTER TABLE `story_views`
  ADD CONSTRAINT `fk_view_story` FOREIGN KEY (`story_id`) REFERENCES `stories` (`story_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_view_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
