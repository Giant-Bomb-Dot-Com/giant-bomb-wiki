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
-- Table structure for table `wiki_game_release_rating_board`
--

DROP TABLE IF EXISTS `wiki_game_release_rating_board`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_rating_board` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description_new` longtext NOT NULL,
  `description` longtext NOT NULL,
  `image_id` int(11) DEFAULT NULL,
  `aliases` varchar(4096) DEFAULT NULL,
  `deck` varchar(512) DEFAULT NULL,
  `slug` varchar(50) NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `region_id` int(11) DEFAULT NULL,
  `background_image_id` int(11) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_9A29F4243DA5256D` (`image_id`),
  KEY `IDX_9A29F42498260155` (`region_id`),
  KEY `IDX_9A29F424E6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_9A29F4243DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_9A29F42498260155` FOREIGN KEY (`region_id`) REFERENCES `wiki_game_release_region` (`id`),
  CONSTRAINT `FK_9A29F424E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
