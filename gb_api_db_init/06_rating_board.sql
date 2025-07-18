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
-- Dumping data for table `wiki_game_release_rating_board`
--

LOCK TABLES `wiki_game_release_rating_board` WRITE;
/*!40000 ALTER TABLE `wiki_game_release_rating_board` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `wiki_game_release_rating_board` VALUES
(1,'2008-04-03 11:22:23','2008-05-10 15:48:41','ESRB','','',271,NULL,'North American Rating Board','',0,NULL,NULL,0),
(2,'2008-04-03 11:22:53','2008-05-10 15:48:41','PEGI','','',272,NULL,'European Rating Board','',0,NULL,NULL,0),
(3,'2008-04-03 11:24:31','2008-05-10 15:48:41','CERO','','',273,NULL,'Japanese Rating Board','',0,NULL,NULL,0),
(4,'2008-04-03 11:24:51','2008-05-10 15:48:41','USK','','',274,NULL,'German Rating Board','',0,NULL,NULL,0),
(5,'2008-04-03 11:25:28','2008-05-10 15:48:41','OFLC','','',275,NULL,'Australian and New Zealand Rating Board','',0,NULL,NULL,0),
(6,'2008-04-03 11:25:54','2008-05-10 15:48:41','BBFC','','',276,NULL,'UK Rating Board','',0,NULL,NULL,0);
/*!40000 ALTER TABLE `wiki_game_release_rating_board` ENABLE KEYS */;
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

-- Dump completed on 2025-07-18 21:04:59
