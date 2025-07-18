/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.2-MariaDB, for debian-linux-gnu (aarch64)
--
-- Host: localhost    Database: gb_api_dump
-- ------------------------------------------------------
-- Server version	11.8.2-MariaDB-ubu2404

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

-- 
-- Switch to gb_api_dump database
--
USE gb_api_dump;

--
-- Dumping data for table `wiki_game_genre`
--

LOCK TABLES `wiki_game_genre` WRITE;
/*!40000 ALTER TABLE `wiki_game_genre` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `wiki_game_genre` VALUES
(1,'2008-04-01 01:32:48','2010-11-26 13:29:37','Action',12,NULL,'The \"action\" genre serves as a catch-all for many games from the 1970s and 1980s, before gaming diversified into distinct sub-genres.','','','',0,NULL,0),
(2,'2008-04-01 01:32:49','2008-05-10 15:42:53','Strategy',13,NULL,NULL,'','','',0,NULL,0),
(3,'2008-04-01 01:32:50','2008-05-10 15:42:53','Sports',14,NULL,NULL,'','','',0,NULL,0),
(4,'2008-04-01 01:32:50','2010-11-26 13:42:29','Adventure',15,NULL,'Adventure games often don\'t run in real-time and focus primarily on puzzle-solving over manual dexterity.','','','<p>This is typically going to apply to games that are adventure games in the \"traditional\" sense, meaning games such as Monkey Island, Maniac Mansion, Space Quest, Neuromancer, and Shadowgate.</p>',0,NULL,0),
(5,'2008-04-01 01:32:51','2009-06-21 16:10:08','Role-Playing',16,NULL,NULL,'','','',0,NULL,0),
(6,'2008-04-01 01:32:51','2010-11-26 13:46:44','Driving/Racing',17,NULL,'Whether you\'re running on-foot or driving an exotic super-car, Driving/Racing is all about getting from one place to another, usually as quickly as possible.','','','<p>The reason we call this \"Driving/Racing\" instead of just \"Driving\" is that non-vehicle-based racing games, such as Mad Dash Racing and Sonic R, still apply to this category.</p>',0,NULL,0),
(7,'2008-04-01 01:32:52','2008-05-10 15:42:53','Simulation',18,NULL,NULL,'','','',0,NULL,0),
(8,'2008-04-01 01:32:55','2008-05-10 15:42:53','Educational',19,NULL,NULL,'','','',0,NULL,0),
(9,'2008-04-21 17:08:01','2010-11-26 13:53:39','Fighting',20,NULL,'Though earlier examples certainly exist, the fighting genre really broke out on its own with the release of Capcom\'s Street Fighter II.','','','<p>With the potentially-lone exception of WWF Wrestlemania: The Arcade Game, wrestling games are NOT fighting games.    Games that break out of the standard one-on-one format, such as Power Stone or Super Smash Bros., still technically qualify for the fighting genre.</p>',0,NULL,0),
(10,'2008-04-21 22:13:56','2008-05-10 15:42:53','Wrestling',21,NULL,NULL,'','','',0,NULL,0),
(11,'2008-04-21 22:14:18','2010-11-26 14:07:34','Shooter',22,NULL,'Shooter is a genre that serves as a top-level catch-all for games that primarily feature shooting but don\'t break out into first-person, light-gun, or other shooting sub-genres.','','','<p>Until someone comes up with a better idea, third-person shooters such as Gears of War and kill.switch fall into this category.</p>',0,NULL,0),
(12,'2008-04-21 22:14:53','2008-05-10 15:42:53','Real-Time Strategy',23,NULL,NULL,'','','',0,NULL,0),
(13,'2008-04-21 22:16:10','2008-05-10 15:42:53','Card Game',24,NULL,NULL,'','','',0,NULL,0),
(14,'2008-04-21 22:16:23','2008-05-10 15:42:53','Trivia/Board Game',25,NULL,NULL,'','','',0,NULL,0),
(15,'2008-04-21 22:16:39','2008-05-10 15:42:53','Compilation',26,NULL,NULL,'','','',0,NULL,0),
(16,'2008-04-21 22:18:31','2010-11-26 14:04:47','MMORPG',27,NULL,'The \"Massively Multiplayer Online Role-Playing Game\" genre typically allows hundres of players to commune in the same space for social events as well as combat. Many of these games require players to pay a regular fee for continued access.','','','<p>Truly-massive only, please, no instanced free online games like Guild Wars.</p>',0,NULL,0),
(17,'2008-04-21 22:23:23','2008-05-10 15:42:53','Minigame Collection',28,NULL,NULL,'','','',0,NULL,0),
(18,'2008-04-21 22:34:16','2008-05-10 15:42:53','Puzzle',29,NULL,NULL,'','','',0,NULL,0),
(19,'2008-04-21 22:40:16','2010-11-26 14:04:13','Music/Rhythm',30,NULL,NULL,'','','<p>Though this is primarily here for games like Rock Band and Dance Dance Revolution, music creation products such as MTV Music Generator and Beaterator fit into this category, as well.</p>',0,NULL,0),
(20,'2008-05-10 18:05:54','2008-05-10 18:05:55','Boxing',31,NULL,NULL,'','','',0,NULL,0),
(21,'2008-05-11 15:04:56','2010-11-26 13:59:11','Football',32,NULL,'From handheld Mattel toys to the latest Madden-endorsed extravaganza, American football has been a favorite for sports game developers for decades.','','','<p>Note that we really mean \"American football\" by this, so please file your footy games under \"Soccer.\" Also, futuristic takes on the sport, such as Cyberball, or other specialized styles, like Mutant League Football, still apply to this genre.</p>',0,NULL,0),
(22,'2008-05-16 14:09:53','2008-05-16 14:09:55','Basketball',33,NULL,NULL,'','','',0,NULL,0),
(23,'2008-05-16 14:10:09','2008-05-16 14:10:11','Skateboarding',34,NULL,NULL,'','','',0,NULL,0),
(24,'2008-05-16 14:10:28','2008-05-16 14:10:29','Flight Simulator',35,NULL,NULL,'','','',0,NULL,0),
(25,'2008-05-16 14:12:14','2008-05-16 14:12:15','Tennis',36,NULL,NULL,'','','',0,NULL,0),
(26,'2008-05-16 15:14:00','2010-11-26 13:45:13','Billiards',37,NULL,NULL,'','','<p>Any form of pool-like table game, from snooker to eight-ball, counts here. Futuristic games such as Lunar Pool also qualify, though be sure to apply the appropriate sci-fi theme to such products.</p>',0,NULL,0),
(27,'2008-05-16 15:35:02','2008-05-16 15:35:11','Fishing',38,NULL,NULL,'','','',0,NULL,0),
(28,'2008-05-17 13:54:29','2008-05-17 13:54:31','Golf',39,NULL,NULL,'','','',0,NULL,0),
(29,'2008-05-17 14:28:50','2008-05-17 14:28:52','Bowling',40,NULL,NULL,'','','',0,NULL,0),
(30,'2008-05-17 15:45:14','2010-11-26 13:59:41','Pinball',41,NULL,NULL,'','','<p>Virtual pinball as well as actual pinball machines are welcome here.</p>',0,NULL,0),
(31,'2008-05-18 19:10:22','2010-11-26 13:47:36','Dual-Joystick Shooter',42,NULL,'Popularized early-on by games like Robotron: 2084, the \"one stick moves, one stick shoots\" genre saw a surge in interest as consoles became able to run smaller, downloadable games.','','','<p>Despite their lack of a joystick, touch-screen-enabled platforms also have games that fit this category.</p>',0,NULL,0),
(32,'2008-05-19 16:56:30','2010-11-26 13:50:10','First-Person Shooter',43,NULL,'Since the genre was popularized by Wolfenstein 3D, first-person shooting has served as a primary genre for close to 20 years.','','','<p>Though thematically similar, third-person shooters such as Gears of War should not be tagged as First-Person Shooter. Games that pull out of the perspective on occasion, such as Quantum of Solace, are fine, though.</p>',0,NULL,0),
(33,'2008-05-19 17:05:36','2008-05-19 17:05:37','Snowboarding/Skiing',44,NULL,NULL,'','','',0,NULL,0),
(34,'2008-05-23 14:47:46','2010-11-26 13:44:08','Baseball',45,NULL,'Traditionally known as \"America\'s Pastime,\" baseball has been converted into video game form for years.','','','<p>Futuristic baseball, such as Base Wars, still counts for this genre, though you should also be sure to apply the sci-fi theme to such games.</p>',0,NULL,0),
(35,'2008-06-07 10:05:41','2008-06-07 10:05:47','Light-Gun Shooter',46,NULL,NULL,'','','',0,NULL,0),
(36,'2008-06-20 21:56:37','2010-11-26 13:55:20','Text Adventure',47,NULL,NULL,'','','<p>There is a leaflet bearing a genre description here.    - READ DESCRIPTION    The leaflet reads: \"The text adventure sub-genre applies to text-only games. Games with text-based entry and graphical elements should be filed under the Adventure genre.\"</p>',0,NULL,0),
(37,'2008-06-21 18:09:30','2010-11-26 14:02:39','Brawler',48,NULL,'Sometimes called \"walk-and-punch\" or \"beat-\'em-up,\" the brawler genre covers side-scrolling melee combat games like Double Dragon, Final Fight, and Streets of Rage.','','','<p>Games with character building and other RPG-light elements still qualify for this genre.    Modern character action games such as Devil May Cry, however, belong in Action.</p>',0,NULL,0),
(38,'2008-06-21 18:32:30','2008-06-21 18:33:04','Vehicular Combat',49,NULL,NULL,'','','',0,NULL,0),
(39,'2008-06-23 21:48:35','2008-06-23 21:48:36','Hockey',50,NULL,NULL,'','','',0,NULL,0),
(40,'2008-07-07 14:30:59','2008-07-07 14:30:59','Soccer',51,NULL,NULL,'','','',0,NULL,0),
(41,'2008-07-24 12:52:42','2008-07-24 12:52:42','Platformer',52,NULL,NULL,'','','',0,NULL,0),
(42,'2009-02-14 13:56:08','2009-02-14 13:56:08','Track & Field',53,NULL,NULL,'','','',0,NULL,0),
(43,'2009-02-21 19:06:29','2010-11-26 13:40:35','Action-Adventure',54,NULL,'Action-adventure games are often noted by the presence of expansive worlds, character building, and puzzle-solving while still requiring you to run, shoot, or fight like an action game.','','','<p>- Mission-based open-world games such as the Grand Theft Auto series.</p>',0,NULL,0),
(44,'2009-05-20 12:15:08','2010-11-26 14:00:40','Fitness',55,NULL,NULL,'','','<p>This is for games specifically designed and sold for the purpose of fitness, such as Wii Fit and Your Shape. Games that happen to have basic \"workout\" modes, like Dance Dance Revolution and Dance Central, do not apply.</p>',0,NULL,0),
(45,'2009-07-27 15:03:26','2010-11-26 13:48:35','Block-Breaking',56,NULL,'Block-breaking started with Breakout, a single-player iteration on the multiplayer classic, Pong.','','','<p>Power-up-enabled games such as Arkanoid still qualify for this category.</p>',0,NULL,0),
(46,'2009-08-07 10:29:25','2009-08-07 10:29:25','Cricket',57,NULL,NULL,'','','',0,NULL,0),
(47,'2009-12-01 21:36:06','2009-12-01 21:36:06','Surfing',58,NULL,NULL,'','','',0,NULL,0),
(48,'2010-01-23 11:46:02','2010-11-26 13:56:43','Shoot \'Em Up',59,NULL,'Though it\'s a less-than-ideal name, the Shoot \'Em Up genre applies to a wide variety of arcade style games involving shooting, such as Gradius, Gun.Smoke, Commando, Moon Cresta, Galaxian, and more.','','','<p>Modern takes on the classic form, such as Einhander, completely fit into this sub-genre.</p>',0,NULL,0),
(49,'2011-11-02 10:39:42','2011-11-02 10:39:42','Gambling',60,NULL,'Games like poker, slot machines, and pachinko that attempt to virtually simulate the act of throwing money into a toilet.','','','',0,NULL,0),
(50,'2014-08-21 13:07:13','2014-08-21 13:07:13','MOBA',61,NULL,'Multiplayer Online Battle Arena','','','',0,NULL,0);
/*!40000 ALTER TABLE `wiki_game_genre` ENABLE KEYS */;
UNLOCK TABLES;
commit;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-07-18 18:56:16
