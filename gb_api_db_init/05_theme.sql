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
-- Table structure for table `wiki_game_theme`
--

DROP TABLE IF EXISTS `wiki_game_theme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_theme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `image_id` int(11) DEFAULT NULL,
  `deck` varchar(512) DEFAULT NULL,
  `aliases` varchar(4096) DEFAULT NULL,
  `slug` varchar(50) NOT NULL,
  `background_image_id` int(11) DEFAULT NULL,
  `description_new` longtext NOT NULL,
  `description` longtext NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_994E4A4F3DA5256D` (`image_id`),
  KEY `IDX_994E4A4FE6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_994E4A4F3DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_994E4A4FE6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wiki_game_theme`
--

LOCK TABLES `wiki_game_theme` WRITE;
/*!40000 ALTER TABLE `wiki_game_theme` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `wiki_game_theme` VALUES
(1,'0000-00-00 00:00:00',NULL,'Horror',NULL,NULL,NULL,'',NULL,'','',0,0),
(2,'0000-00-00 00:00:00',NULL,'Fantasy',NULL,NULL,NULL,'',NULL,'','',0,0),
(3,'0000-00-00 00:00:00',NULL,'Sci-Fi',NULL,NULL,NULL,'',NULL,'','',0,0),
(4,'0000-00-00 00:00:00',NULL,'Alternate Historical',NULL,NULL,NULL,'',NULL,'','',0,0),
(5,'0000-00-00 00:00:00',NULL,'Dating',NULL,NULL,NULL,'',NULL,'','',0,0),
(6,'0000-00-00 00:00:00',NULL,'Comedy',NULL,NULL,NULL,'',NULL,'','',0,0),
(7,'0000-00-00 00:00:00',NULL,'World War II',NULL,NULL,NULL,'',NULL,'','',0,0),
(8,'0000-00-00 00:00:00',NULL,'Vietnam',NULL,NULL,NULL,'',NULL,'','',0,0),
(9,'0000-00-00 00:00:00',NULL,'Cyberpunk',NULL,NULL,NULL,'',NULL,'','',0,0),
(10,'0000-00-00 00:00:00',NULL,'Steampunk',NULL,NULL,NULL,'',NULL,'','',0,0),
(11,'0000-00-00 00:00:00',NULL,'Espionage',NULL,NULL,NULL,'',NULL,'','',0,0),
(12,'0000-00-00 00:00:00',NULL,'Modern Military',NULL,NULL,NULL,'',NULL,'','',0,0),
(13,'0000-00-00 00:00:00',NULL,'Crime',NULL,NULL,NULL,'',NULL,'','',0,0),
(14,'0000-00-00 00:00:00',NULL,'Mayan',NULL,NULL,NULL,'',NULL,'','',0,0),
(15,'0000-00-00 00:00:00',NULL,'Egyptian',NULL,NULL,NULL,'',NULL,'','',0,0),
(16,'0000-00-00 00:00:00',NULL,'Anime',NULL,NULL,NULL,'',NULL,'','',0,0),
(17,'0000-00-00 00:00:00',NULL,'Martial Arts',NULL,NULL,NULL,'',NULL,'','',0,0),
(18,'0000-00-00 00:00:00',NULL,'Post-Apocalyptic',NULL,NULL,NULL,'',NULL,'','',0,0),
(19,'0000-00-00 00:00:00',NULL,'Superhero',NULL,NULL,NULL,'',NULL,'','',0,0),
(21,'0000-00-00 00:00:00',NULL,'Management',NULL,NULL,NULL,'',NULL,'','',0,0),
(22,'0000-00-00 00:00:00',NULL,'Abstract',NULL,NULL,NULL,'',NULL,'','',0,0),
(23,'0000-00-00 00:00:00',NULL,'Western',NULL,NULL,NULL,'',NULL,'','',0,0),
(24,'0000-00-00 00:00:00',NULL,'Adult',NULL,NULL,NULL,'',NULL,'','',0,0),
(25,'0000-00-00 00:00:00',NULL,'Civil War',NULL,NULL,NULL,'',NULL,'','',0,0),
(26,'0000-00-00 00:00:00',NULL,'Motorsports',NULL,NULL,NULL,'',NULL,'','',0,0),
(27,'0000-00-00 00:00:00',NULL,'Medieval',NULL,NULL,NULL,'',NULL,'','',0,0),
(28,'0000-00-00 00:00:00',NULL,'Prehistoric',NULL,NULL,NULL,'',NULL,'','',0,0),
(29,'0000-00-00 00:00:00',NULL,'Game Show',NULL,NULL,NULL,'',NULL,'','',0,0),
(30,'0000-00-00 00:00:00',NULL,'Comic Book',NULL,NULL,NULL,'',NULL,'','',0,0),
(31,'0000-00-00 00:00:00',NULL,'Aquatic',NULL,NULL,NULL,'',NULL,'','',0,0);
/*!40000 ALTER TABLE `wiki_game_theme` ENABLE KEYS */;
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

-- Dump completed on 2025-07-18 19:44:14
