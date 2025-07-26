--
-- Create database and grant permissions to wiki_admin
--
CREATE DATABASE IF NOT EXISTS gb_api_dump;
GRANT ALL PRIVILEGES ON gb_api_dump.* TO 'wiki_admin'@'%';
FLUSH PRIVILEGES;

-- 
-- Switch to created database for table creations
--
USE gb_api_dump;

SET FOREIGN_KEY_CHECKS = 0;

--
-- Table structure for table `image`
--
DROP TABLE IF EXISTS `image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `image` (
    `id` int NOT NULL AUTO_INCREMENT,
    `assoc_type_id` INT DEFAULT NULL,
    `assoc_id` INT DEFAULT NULL,
    `image` varchar(255) NOT NULL,
    `caption` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `assoc_type_id_assoc_id_image_idx` (`assoc_type_id`,`assoc_id`,`image`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_accessory`
--

DROP TABLE IF EXISTS `wiki_accessory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_accessory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_AA813C383DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_AA813C38E6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_AA813C383DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_AA813C38E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=205 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_company`
--

DROP TABLE IF EXISTS `wiki_assoc_character_company`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_company` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_A9C6F3DC1136BE75` (`character_id`),
  KEY `IDX_A9C6F3DC979B1AD6` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=140881 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_concept`
--

DROP TABLE IF EXISTS `wiki_assoc_character_concept`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_concept` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `concept_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_1339AC31136BE75` (`character_id`),
  KEY `IDX_1339AC3F909284E` (`concept_id`),
  CONSTRAINT `FK_1339AC31136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_1339AC3F909284E` FOREIGN KEY (`concept_id`) REFERENCES `wiki_concept` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=183266 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_enemy`
--

DROP TABLE IF EXISTS `wiki_assoc_character_enemy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_enemy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `enemy_character_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_87D968BD1136BE75` (`character_id`),
  KEY `IDX_87D968BDABE37847` (`enemy_character_id`),
  CONSTRAINT `FK_87D968BD1136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_87D968BDABE37847` FOREIGN KEY (`enemy_character_id`) REFERENCES `wiki_character` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=91461 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_franchise`
--

DROP TABLE IF EXISTS `wiki_assoc_character_franchise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_franchise` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `franchise_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_61F2EA881136BE75` (`character_id`),
  KEY `IDX_61F2EA88523CAB89` (`franchise_id`),
  CONSTRAINT `FK_61F2EA881136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_61F2EA88523CAB89` FOREIGN KEY (`franchise_id`) REFERENCES `wiki_franchise` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=72463 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_friend`
--

DROP TABLE IF EXISTS `wiki_assoc_character_friend`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_friend` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `friend_character_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_4F483E2E1136BE75` (`character_id`),
  KEY `IDX_4F483E2ECF8AE333` (`friend_character_id`),
  CONSTRAINT `FK_4F483E2E1136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_4F483E2ECF8AE333` FOREIGN KEY (`friend_character_id`) REFERENCES `wiki_character` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=141221 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_location`
--

DROP TABLE IF EXISTS `wiki_assoc_character_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_377E32CF1136BE75` (`character_id`),
  KEY `IDX_377E32CF64D218E` (`location_id`),
  CONSTRAINT `FK_377E32CF1136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_377E32CF64D218E` FOREIGN KEY (`location_id`) REFERENCES `wiki_location` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51497 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_person`
--

DROP TABLE IF EXISTS `wiki_assoc_character_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_2E7A43391136BE75` (`character_id`),
  KEY `IDX_2E7A4339217BBB47` (`person_id`),
  CONSTRAINT `FK_2E7A43391136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_2E7A4339217BBB47` FOREIGN KEY (`person_id`) REFERENCES `wiki_person` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14350 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_platform`
--

DROP TABLE IF EXISTS `wiki_assoc_character_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_platform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_50B26BCF1136BE75` (`character_id`),
  KEY `IDX_50B26BCFFFE6496F` (`platform_id`)
) ENGINE=InnoDB AUTO_INCREMENT=163757 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_thing`
--

DROP TABLE IF EXISTS `wiki_assoc_character_thing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_thing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `thing_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_270A1E971136BE75` (`character_id`),
  KEY `IDX_270A1E97C36906A7` (`thing_id`),
  CONSTRAINT `FK_270A1E971136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_270A1E97C36906A7` FOREIGN KEY (`thing_id`) REFERENCES `wiki_thing` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_character_to_enemy`
--

DROP TABLE IF EXISTS `wiki_assoc_character_to_enemy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_character_to_enemy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `character_id` int DEFAULT NULL,
  `enemy_character_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9943D09D1136BE75` (`character_id`),
  KEY `IDX_9943D09DABE37847` (`enemy_character_id`),
  CONSTRAINT `FK_9943D09D1136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_9943D09DABE37847` FOREIGN KEY (`enemy_character_id`) REFERENCES `wiki_character` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_company_concept`
--

DROP TABLE IF EXISTS `wiki_assoc_company_concept`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_company_concept` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `concept_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_459DAFF1979B1AD6` (`company_id`),
  KEY `IDX_459DAFF1F909284E` (`concept_id`)
) ENGINE=InnoDB AUTO_INCREMENT=462340 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_company_location`
--

DROP TABLE IF EXISTS `wiki_assoc_company_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_company_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_FFEDCD7A979B1AD6` (`company_id`),
  KEY `IDX_FFEDCD7A64D218E` (`location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=72257 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_company_person`
--

DROP TABLE IF EXISTS `wiki_assoc_company_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_company_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_84620255979B1AD6` (`company_id`),
  KEY `IDX_84620255217BBB47` (`person_id`)
) ENGINE=InnoDB AUTO_INCREMENT=350139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_company_platform`
--

DROP TABLE IF EXISTS `wiki_assoc_company_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_company_platform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9821947A979B1AD6` (`company_id`),
  KEY `IDX_9821947AFFE6496F` (`platform_id`),
  CONSTRAINT `FK_9821947A979B1AD6` FOREIGN KEY (`company_id`) REFERENCES `wiki_company` (`id`),
  CONSTRAINT `FK_9821947AFFE6496F` FOREIGN KEY (`platform_id`) REFERENCES `wiki_platform` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_company_thing`
--

DROP TABLE IF EXISTS `wiki_assoc_company_thing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_company_thing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int DEFAULT NULL,
  `thing_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_35072DF9979B1AD6` (`company_id`),
  KEY `IDX_35072DF9C36906A7` (`thing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=238333 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_concept_franchise`
--

DROP TABLE IF EXISTS `wiki_assoc_concept_franchise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_concept_franchise` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concept_id` int DEFAULT NULL,
  `franchise_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_3F9B54C9F909284E` (`concept_id`),
  KEY `IDX_3F9B54C9523CAB89` (`franchise_id`),
  CONSTRAINT `FK_3F9B54C9523CAB89` FOREIGN KEY (`franchise_id`) REFERENCES `wiki_franchise` (`id`),
  CONSTRAINT `FK_3F9B54C9F909284E` FOREIGN KEY (`concept_id`) REFERENCES `wiki_concept` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=207149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_concept_location`
--

DROP TABLE IF EXISTS `wiki_assoc_concept_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_concept_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concept_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_80397DB7F909284E` (`concept_id`),
  KEY `IDX_80397DB764D218E` (`location_id`),
  CONSTRAINT `FK_80397DB764D218E` FOREIGN KEY (`location_id`) REFERENCES `wiki_location` (`id`),
  CONSTRAINT `FK_80397DB7F909284E` FOREIGN KEY (`concept_id`) REFERENCES `wiki_concept` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39585 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_concept_person`
--

DROP TABLE IF EXISTS `wiki_assoc_concept_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_concept_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concept_id` int DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_32045E27F909284E` (`concept_id`),
  KEY `IDX_32045E27217BBB47` (`person_id`),
  CONSTRAINT `FK_32045E27217BBB47` FOREIGN KEY (`person_id`) REFERENCES `wiki_person` (`id`),
  CONSTRAINT `FK_32045E27F909284E` FOREIGN KEY (`concept_id`) REFERENCES `wiki_concept` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_concept_platform`
--

DROP TABLE IF EXISTS `wiki_assoc_concept_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_concept_platform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concept_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_E7F524B7F909284E` (`concept_id`),
  KEY `IDX_E7F524B7FFE6496F` (`platform_id`)
) ENGINE=InnoDB AUTO_INCREMENT=162351 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_concept_similar`
--

DROP TABLE IF EXISTS `wiki_assoc_concept_similar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_concept_similar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concept_id` int DEFAULT NULL,
  `similar_concept_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_D7D18170F909284E` (`concept_id`),
  KEY `IDX_D7D181701E18A388` (`similar_concept_id`),
  CONSTRAINT `FK_D7D181701E18A388` FOREIGN KEY (`similar_concept_id`) REFERENCES `wiki_concept` (`id`),
  CONSTRAINT `FK_D7D18170F909284E` FOREIGN KEY (`concept_id`) REFERENCES `wiki_concept` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=90198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_concept_thing`
--

DROP TABLE IF EXISTS `wiki_assoc_concept_thing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_concept_thing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concept_id` int DEFAULT NULL,
  `thing_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_357662C6F909284E` (`concept_id`),
  KEY `IDX_357662C6C36906A7` (`thing_id`),
  CONSTRAINT `FK_357662C6C36906A7` FOREIGN KEY (`thing_id`) REFERENCES `wiki_thing` (`id`),
  CONSTRAINT `FK_357662C6F909284E` FOREIGN KEY (`concept_id`) REFERENCES `wiki_concept` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29730 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_franchise_location`
--

DROP TABLE IF EXISTS `wiki_assoc_franchise_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_franchise_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `franchise_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_E04F20A2523CAB89` (`franchise_id`),
  KEY `IDX_E04F20A264D218E` (`location_id`),
  CONSTRAINT `FK_E04F20A2523CAB89` FOREIGN KEY (`franchise_id`) REFERENCES `wiki_franchise` (`id`),
  CONSTRAINT `FK_E04F20A264D218E` FOREIGN KEY (`location_id`) REFERENCES `wiki_location` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29610 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_franchise_person`
--

DROP TABLE IF EXISTS `wiki_assoc_franchise_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_franchise_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `franchise_id` int DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_ED1D731D523CAB89` (`franchise_id`),
  KEY `IDX_ED1D731D217BBB47` (`person_id`),
  CONSTRAINT `FK_ED1D731D217BBB47` FOREIGN KEY (`person_id`) REFERENCES `wiki_person` (`id`),
  CONSTRAINT `FK_ED1D731D523CAB89` FOREIGN KEY (`franchise_id`) REFERENCES `wiki_franchise` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=104002 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_franchise_thing`
--

DROP TABLE IF EXISTS `wiki_assoc_franchise_thing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_franchise_thing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `franchise_id` int DEFAULT NULL,
  `thing_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_4CB49B6A523CAB89` (`franchise_id`),
  KEY `IDX_4CB49B6AC36906A7` (`thing_id`),
  CONSTRAINT `FK_4CB49B6A523CAB89` FOREIGN KEY (`franchise_id`) REFERENCES `wiki_franchise` (`id`),
  CONSTRAINT `FK_4CB49B6AC36906A7` FOREIGN KEY (`thing_id`) REFERENCES `wiki_thing` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=105750 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_character`
--

DROP TABLE IF EXISTS `wiki_assoc_game_character`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_character` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `character_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_CCFDC660E48FD905` (`game_id`),
  KEY `IDX_CCFDC6601136BE75` (`character_id`),
  KEY `game_character_idx` (`game_id`,`character_id`),
  CONSTRAINT `FK_CCFDC6601136BE75` FOREIGN KEY (`character_id`) REFERENCES `wiki_character` (`id`),
  CONSTRAINT `FK_CCFDC660E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=226835 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_concept`
--

DROP TABLE IF EXISTS `wiki_assoc_game_concept`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_concept` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `concept_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_11E29806E48FD905` (`game_id`),
  KEY `IDX_11E29806F909284E` (`concept_id`),
  KEY `game_concept_idx` (`game_id`,`concept_id`),
  CONSTRAINT `FK_11E29806E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`),
  CONSTRAINT `FK_11E29806F909284E` FOREIGN KEY (`concept_id`) REFERENCES `wiki_concept` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=803061 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_developer`
--

DROP TABLE IF EXISTS `wiki_assoc_game_developer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_developer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_3A7CFDCEE48FD905` (`game_id`),
  KEY `IDX_3A7CFDCE979B1AD6` (`company_id`),
  CONSTRAINT `FK_3A7CFDCE979B1AD6` FOREIGN KEY (`company_id`) REFERENCES `wiki_company` (`id`),
  CONSTRAINT `FK_3A7CFDCEE48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=91739 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_franchise`
--

DROP TABLE IF EXISTS `wiki_assoc_game_franchise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_franchise` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `franchise_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_3971B87EE48FD905` (`game_id`),
  KEY `IDX_3971B87E523CAB89` (`franchise_id`),
  CONSTRAINT `FK_3971B87E523CAB89` FOREIGN KEY (`franchise_id`) REFERENCES `wiki_franchise` (`id`),
  CONSTRAINT `FK_3971B87EE48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43720 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_location`
--

DROP TABLE IF EXISTS `wiki_assoc_game_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_DC60D5F2E48FD905` (`game_id`),
  KEY `IDX_DC60D5F264D218E` (`location_id`),
  KEY `game_location_idx` (`game_id`,`location_id`),
  CONSTRAINT `FK_DC60D5F264D218E` FOREIGN KEY (`location_id`) REFERENCES `wiki_location` (`id`),
  CONSTRAINT `FK_DC60D5F2E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=88149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_person`
--

DROP TABLE IF EXISTS `wiki_assoc_game_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_2502DCA3E48FD905` (`game_id`),
  KEY `IDX_2502DCA3217BBB47` (`person_id`),
  KEY `IDX_2502DCA3D60322AC` (`role_id`),
  CONSTRAINT `FK_2502DCA3217BBB47` FOREIGN KEY (`person_id`) REFERENCES `wiki_person` (`id`),
  CONSTRAINT `FK_2502DCA3D60322AC` FOREIGN KEY (`role_id`) REFERENCES `wiki_game_role` (`id`),
  CONSTRAINT `FK_2502DCA3E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=422436 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_platform`
--

DROP TABLE IF EXISTS `wiki_assoc_game_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_platform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_BBAC8CF2E48FD905` (`game_id`),
  KEY `IDX_BBAC8CF2FFE6496F` (`platform_id`),
  CONSTRAINT `FK_BBAC8CF2E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`),
  CONSTRAINT `FK_BBAC8CF2FFE6496F` FOREIGN KEY (`platform_id`) REFERENCES `wiki_platform` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_publisher`
--

DROP TABLE IF EXISTS `wiki_assoc_game_publisher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_publisher` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_C36FA312E48FD905` (`game_id`),
  KEY `IDX_C36FA312979B1AD6` (`company_id`),
  CONSTRAINT `FK_C36FA312979B1AD6` FOREIGN KEY (`company_id`) REFERENCES `wiki_company` (`id`),
  CONSTRAINT `FK_C36FA312E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=98205 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_similar`
--

DROP TABLE IF EXISTS `wiki_assoc_game_similar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_similar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `similar_game_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_3D13C0CBE48FD905` (`game_id`),
  KEY `IDX_3D13C0CB51064811` (`similar_game_id`),
  CONSTRAINT `FK_3D13C0CB51064811` FOREIGN KEY (`similar_game_id`) REFERENCES `wiki_game` (`id`),
  CONSTRAINT `FK_3D13C0CBE48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=260122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_game_thing`
--

DROP TABLE IF EXISTS `wiki_assoc_game_thing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_game_thing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `thing_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_844AA558E48FD905` (`game_id`),
  KEY `IDX_844AA558C36906A7` (`thing_id`),
  KEY `game_thing_idx` (`game_id`,`thing_id`),
  CONSTRAINT `FK_844AA558C36906A7` FOREIGN KEY (`thing_id`) REFERENCES `wiki_thing` (`id`),
  CONSTRAINT `FK_844AA558E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=282161 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_location_person`
--

DROP TABLE IF EXISTS `wiki_assoc_location_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_location_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int DEFAULT NULL,
  `person_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_FD2B8C0264D218E` (`location_id`),
  KEY `IDX_FD2B8C02217BBB47` (`person_id`),
  CONSTRAINT `FK_FD2B8C02217BBB47` FOREIGN KEY (`person_id`) REFERENCES `wiki_person` (`id`),
  CONSTRAINT `FK_FD2B8C0264D218E` FOREIGN KEY (`location_id`) REFERENCES `wiki_location` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2436 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_location_platform`
--

DROP TABLE IF EXISTS `wiki_assoc_location_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_location_platform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_67DB888764D218E` (`location_id`),
  KEY `IDX_67DB8887FFE6496F` (`platform_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40787 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_location_similar`
--

DROP TABLE IF EXISTS `wiki_assoc_location_similar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_location_similar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int DEFAULT NULL,
  `similar_location_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9C1A7AE564D218E` (`location_id`),
  KEY `IDX_9C1A7AE574AD5780` (`similar_location_id`),
  CONSTRAINT `FK_9C1A7AE564D218E` FOREIGN KEY (`location_id`) REFERENCES `wiki_location` (`id`),
  CONSTRAINT `FK_9C1A7AE574AD5780` FOREIGN KEY (`similar_location_id`) REFERENCES `wiki_location` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_location_thing`
--

DROP TABLE IF EXISTS `wiki_assoc_location_thing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_location_thing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` int DEFAULT NULL,
  `thing_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_A031DEF064D218E` (`location_id`),
  KEY `IDX_A031DEF0C36906A7` (`thing_id`),
  CONSTRAINT `FK_A031DEF064D218E` FOREIGN KEY (`location_id`) REFERENCES `wiki_location` (`id`),
  CONSTRAINT `FK_A031DEF0C36906A7` FOREIGN KEY (`thing_id`) REFERENCES `wiki_thing` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_person_platform`
--

DROP TABLE IF EXISTS `wiki_assoc_person_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_person_platform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `person_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_2F91A08A217BBB47` (`person_id`),
  KEY `IDX_2F91A08AFFE6496F` (`platform_id`)
) ENGINE=InnoDB AUTO_INCREMENT=242313 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_person_similar`
--

DROP TABLE IF EXISTS `wiki_assoc_person_similar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_person_similar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `person_id` int DEFAULT NULL,
  `similar_person_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_64043C42217BBB47` (`person_id`),
  KEY `IDX_64043C4288DD7A49` (`similar_person_id`),
  CONSTRAINT `FK_64043C42217BBB47` FOREIGN KEY (`person_id`) REFERENCES `wiki_person` (`id`),
  CONSTRAINT `FK_64043C4288DD7A49` FOREIGN KEY (`similar_person_id`) REFERENCES `wiki_person` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_person_thing`
--

DROP TABLE IF EXISTS `wiki_assoc_person_thing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_person_thing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `person_id` int DEFAULT NULL,
  `thing_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9410B43E217BBB47` (`person_id`),
  KEY `IDX_9410B43EC36906A7` (`thing_id`),
  CONSTRAINT `FK_9410B43E217BBB47` FOREIGN KEY (`person_id`) REFERENCES `wiki_person` (`id`),
  CONSTRAINT `FK_9410B43EC36906A7` FOREIGN KEY (`thing_id`) REFERENCES `wiki_thing` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1638 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_thing_platform`
--

DROP TABLE IF EXISTS `wiki_assoc_thing_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_thing_platform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `thing_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_8E508E57C36906A7` (`thing_id`),
  KEY `IDX_8E508E57FFE6496F` (`platform_id`)
) ENGINE=InnoDB AUTO_INCREMENT=85985 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_assoc_thing_similar`
--

DROP TABLE IF EXISTS `wiki_assoc_thing_similar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_assoc_thing_similar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `thing_id` int DEFAULT NULL,
  `similar_thing_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_74B99FE3C36906A7` (`thing_id`),
  KEY `IDX_74B99FE3D9065B4B` (`similar_thing_id`),
  CONSTRAINT `FK_74B99FE3C36906A7` FOREIGN KEY (`thing_id`) REFERENCES `wiki_thing` (`id`),
  CONSTRAINT `FK_74B99FE3D9065B4B` FOREIGN KEY (`similar_thing_id`) REFERENCES `wiki_thing` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_change`
--

DROP TABLE IF EXISTS `wiki_change`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_change` (
  `id` int NOT NULL AUTO_INCREMENT,
  `changeset_id` int DEFAULT NULL,
  `submitter_id` int DEFAULT NULL,
  `moderator_id` int DEFAULT NULL,
  `parent_change_id` int DEFAULT NULL,
  `assoc_type_id` int DEFAULT NULL,
  `assoc_id` int DEFAULT NULL,
  `action` smallint NOT NULL,
  `status` smallint NOT NULL DEFAULT '0',
  `fieldType` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `fieldName` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `originalValue` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `newValue` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `points` smallint DEFAULT NULL,
  `potential_points` smallint DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_moderated` datetime DEFAULT NULL,
  `target_assoc_type_id` int DEFAULT NULL,
  `target_assoc_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_A4F6BB12C6135CC9` (`changeset_id`),
  KEY `assoc_type_id_assoc_id_idx` (`assoc_type_id`,`assoc_id`),
  KEY `submitter_id_idx` (`submitter_id`),
  KEY `moderator_idx` (`moderator_id`),
  KEY `parent_change_idx` (`parent_change_id`),
  KEY `status_idx` (`status`),
  KEY `action` (`action`),
  KEY `status` (`status`),
  KEY `date_created` (`date_created`),
  KEY `target_assoc_type_id` (`target_assoc_type_id`),
  KEY `target_assoc_id` (`target_assoc_id`),
  CONSTRAINT `FK_A4F6BB12919E5513` FOREIGN KEY (`submitter_id`) REFERENCES `user` (`id`),
  CONSTRAINT `FK_A4F6BB12C6135CC9` FOREIGN KEY (`changeset_id`) REFERENCES `wiki_changeset` (`id`),
  CONSTRAINT `FK_A4F6BB12D077A41A` FOREIGN KEY (`parent_change_id`) REFERENCES `wiki_change` (`id`) ON DELETE SET NULL,
  CONSTRAINT `FK_A4F6BB12D0AFA354` FOREIGN KEY (`moderator_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1792580 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_changeset`
--

DROP TABLE IF EXISTS `wiki_changeset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_changeset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submitter_id` int DEFAULT NULL,
  `moderator_id` int DEFAULT NULL,
  `assoc_type_id` int DEFAULT NULL,
  `assoc_id` int DEFAULT NULL,
  `entity_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `entity_slug` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `status` smallint NOT NULL DEFAULT '0',
  `rating` int DEFAULT NULL,
  `points_modifier` smallint NOT NULL DEFAULT '0',
  `total_points` smallint NOT NULL DEFAULT '0',
  `total_potential_points` smallint DEFAULT NULL,
  `submitter_comment` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `moderator_comment` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `date_moderated` datetime DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `assoc_type_id_assoc_id_idx` (`assoc_type_id`,`assoc_id`),
  KEY `submitter_id_idx` (`submitter_id`),
  KEY `moderator_idx` (`moderator_id`),
  KEY `status_idx` (`status`),
  CONSTRAINT `FK_AA59723C919E5513` FOREIGN KEY (`submitter_id`) REFERENCES `user` (`id`),
  CONSTRAINT `FK_AA59723CD0AFA354` FOREIGN KEY (`moderator_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=318352 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_character`
--

DROP TABLE IF EXISTS `wiki_character`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_character` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `real_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` smallint DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `death` date DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_984AA91070AA3482` (`stats_id`),
  KEY `IDX_984AA9103DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_984AA910E6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_984AA9103DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_984AA91070AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_character_stat` (`id`),
  CONSTRAINT `FK_984AA910E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=57947 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_character_stat`
--

DROP TABLE IF EXISTS `wiki_character_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_character_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `total_friends` int NOT NULL,
  `total_enemies` int NOT NULL,
  `total_games` int NOT NULL,
  `total_companies` int NOT NULL,
  `total_franchises` int NOT NULL,
  `total_platforms` int NOT NULL,
  `total_things` int NOT NULL,
  `total_locations` int NOT NULL,
  `total_people` int NOT NULL,
  `total_concepts` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=57945 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_company`
--

DROP TABLE IF EXISTS `wiki_company`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_company` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `abbreviation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `founded_date` date DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_878CF98A70AA3482` (`stats_id`),
  KEY `IDX_878CF98A3DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_878CF98AE6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_878CF98A3DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_878CF98A70AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_company_stat` (`id`),
  CONSTRAINT `FK_878CF98AE6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_company_stat`
--

DROP TABLE IF EXISTS `wiki_company_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_company_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `total_characters` int NOT NULL,
  `total_developed_games` int NOT NULL,
  `total_published_games` int NOT NULL,
  `total_concepts` int NOT NULL,
  `total_things` int NOT NULL,
  `total_locations` int NOT NULL,
  `total_people` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_concept`
--

DROP TABLE IF EXISTS `wiki_concept`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_concept` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_2F79909570AA3482` (`stats_id`),
  KEY `IDX_2F7990953DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_2F799095E6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_2F7990953DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_2F79909570AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_concept_stat` (`id`),
  CONSTRAINT `FK_2F799095E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13470 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_concept_stat`
--

DROP TABLE IF EXISTS `wiki_concept_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_concept_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `total_characters` int NOT NULL,
  `total_games` int NOT NULL,
  `total_companies` int NOT NULL,
  `total_similar` int NOT NULL,
  `total_franchises` int NOT NULL,
  `total_platforms` int NOT NULL,
  `total_things` int NOT NULL,
  `total_locations` int NOT NULL,
  `total_people` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13471 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_franchise`
--

DROP TABLE IF EXISTS `wiki_franchise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_franchise` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_6DC6D70E70AA3482` (`stats_id`),
  KEY `IDX_6DC6D70E3DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_6DC6D70EE6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_6DC6D70E3DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_6DC6D70E70AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_franchise_stat` (`id`),
  CONSTRAINT `FK_6DC6D70EE6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6066 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_franchise_stat`
--

DROP TABLE IF EXISTS `wiki_franchise_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_franchise_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `total_characters` int NOT NULL,
  `total_games` int NOT NULL,
  `total_concepts` int NOT NULL,
  `total_things` int NOT NULL,
  `total_locations` int NOT NULL,
  `total_people` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6069 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game`
--

DROP TABLE IF EXISTS `wiki_game`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `release_date_type` smallint NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `guideBody` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_A309F12570AA3482` (`stats_id`),
  KEY `IDX_A309F1253DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_A309F125E6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_A309F1253DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_A309F12570AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_game_stat` (`id`),
  CONSTRAINT `FK_A309F125E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=93046 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_dlc`
--

DROP TABLE IF EXISTS `wiki_game_dlc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_dlc` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `image_id` int DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `release_date_type` smallint NOT NULL,
  `launch_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_5DF0B8ADE48FD905` (`game_id`),
  KEY `IDX_5DF0B8ADFFE6496F` (`platform_id`),
  KEY `IDX_5DF0B8AD3DA5256D` (`image_id`),
  KEY `IDX_5DF0B8ADE6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_5DF0B8AD3DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_5DF0B8ADE48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`),
  CONSTRAINT `FK_5DF0B8ADE6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_5DF0B8ADFFE6496F` FOREIGN KEY (`platform_id`) REFERENCES `wiki_platform` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8415 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_dlc_to_developer`
--

DROP TABLE IF EXISTS `wiki_game_dlc_to_developer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_dlc_to_developer` (
  `dlc_id` int NOT NULL,
  `company_id` int NOT NULL,
  PRIMARY KEY (`dlc_id`,`company_id`),
  KEY `IDX_EDF07550CEF6326C` (`dlc_id`),
  KEY `IDX_EDF07550979B1AD6` (`company_id`),
  CONSTRAINT `FK_EDF07550979B1AD6` FOREIGN KEY (`company_id`) REFERENCES `wiki_company` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_EDF07550CEF6326C` FOREIGN KEY (`dlc_id`) REFERENCES `wiki_game_dlc` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_dlc_to_publisher`
--

DROP TABLE IF EXISTS `wiki_game_dlc_to_publisher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_dlc_to_publisher` (
  `dlc_id` int NOT NULL,
  `company_id` int NOT NULL,
  PRIMARY KEY (`dlc_id`,`company_id`),
  KEY `IDX_14E32B8CCEF6326C` (`dlc_id`),
  KEY `IDX_14E32B8C979B1AD6` (`company_id`),
  CONSTRAINT `FK_14E32B8C979B1AD6` FOREIGN KEY (`company_id`) REFERENCES `wiki_company` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_14E32B8CCEF6326C` FOREIGN KEY (`dlc_id`) REFERENCES `wiki_game_dlc` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_dlc_to_type`
--

DROP TABLE IF EXISTS `wiki_game_dlc_to_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_dlc_to_type` (
  `dlc_id` int NOT NULL,
  `type_id` int NOT NULL,
  PRIMARY KEY (`dlc_id`,`type_id`),
  KEY `IDX_E8BB4662CEF6326C` (`dlc_id`),
  KEY `IDX_E8BB4662C54C8C93` (`type_id`),
  CONSTRAINT `FK_E8BB4662C54C8C93` FOREIGN KEY (`type_id`) REFERENCES `wiki_game_dlc_type` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_E8BB4662CEF6326C` FOREIGN KEY (`dlc_id`) REFERENCES `wiki_game_dlc` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_dlc_type`
--

DROP TABLE IF EXISTS `wiki_game_dlc_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_dlc_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_genre`
--

DROP TABLE IF EXISTS `wiki_game_genre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_genre` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_id` int DEFAULT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `FK_8D6B9EBF3DA5256D` (`image_id`),
  KEY `IDX_8D6B9EBFE6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_8D6B9EBF3DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_8D6B9EBFE6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release`
--

DROP TABLE IF EXISTS `wiki_game_release`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release` (
  `id` int NOT NULL AUTO_INCREMENT,
  `game_id` int DEFAULT NULL,
  `platform_id` int DEFAULT NULL,
  `region_id` int DEFAULT NULL,
  `product_code_type` int DEFAULT NULL,
  `company_code_type` int DEFAULT NULL,
  `rating_id` int DEFAULT NULL,
  `image_id` int DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `release_date_type` smallint NOT NULL,
  `product_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `widescreen_support` tinyint(1) DEFAULT NULL,
  `minimum_players` int NOT NULL,
  `maximum_players` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_89180BB170AA3482` (`stats_id`),
  KEY `IDX_89180BB1E48FD905` (`game_id`),
  KEY `IDX_89180BB1FFE6496F` (`platform_id`),
  KEY `IDX_89180BB198260155` (`region_id`),
  KEY `IDX_89180BB1D4958B65` (`product_code_type`),
  KEY `IDX_89180BB1892EB718` (`company_code_type`),
  KEY `IDX_89180BB1A32EFC6` (`rating_id`),
  KEY `IDX_89180BB13DA5256D` (`image_id`),
  KEY `IDX_89180BB1E6DA28AA` (`background_image_id`),
  KEY `wiki_game_release_release_date_idx` (`release_date`),
  CONSTRAINT `FK_89180BB13DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_89180BB170AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_game_release_stat` (`id`),
  CONSTRAINT `FK_89180BB1892EB718` FOREIGN KEY (`company_code_type`) REFERENCES `wiki_game_release_company_code_type` (`id`),
  CONSTRAINT `FK_89180BB198260155` FOREIGN KEY (`region_id`) REFERENCES `wiki_game_release_region` (`id`),
  CONSTRAINT `FK_89180BB1A32EFC6` FOREIGN KEY (`rating_id`) REFERENCES `wiki_game_release_rating` (`id`),
  CONSTRAINT `FK_89180BB1D4958B65` FOREIGN KEY (`product_code_type`) REFERENCES `wiki_game_release_product_code_type` (`id`),
  CONSTRAINT `FK_89180BB1E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`),
  CONSTRAINT `FK_89180BB1E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_89180BB1FFE6496F` FOREIGN KEY (`platform_id`) REFERENCES `wiki_platform` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=193733 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_company_code_type`
--

DROP TABLE IF EXISTS `wiki_game_release_company_code_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_company_code_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_feature`
--

DROP TABLE IF EXISTS `wiki_game_release_feature`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_feature` (
  `id` int NOT NULL AUTO_INCREMENT,
  `multiplayer` tinyint(1) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_product_code_type`
--

DROP TABLE IF EXISTS `wiki_game_release_product_code_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_product_code_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_rating`
--

DROP TABLE IF EXISTS `wiki_game_release_rating`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_rating` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ratingBoard_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_DBE5084899E2DD6` (`ratingBoard_id`),
  CONSTRAINT `FK_DBE5084899E2DD6` FOREIGN KEY (`ratingBoard_id`) REFERENCES `wiki_game_release_rating_board` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_rating_board`
--

DROP TABLE IF EXISTS `wiki_game_release_rating_board`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_rating_board` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_id` int DEFAULT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `region_id` int DEFAULT NULL,
  `background_image_id` int DEFAULT NULL,
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
-- Table structure for table `wiki_game_release_region`
--

DROP TABLE IF EXISTS `wiki_game_release_region`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_region` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbreviation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_id` int DEFAULT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_DA5587D03DA5256D` (`image_id`),
  KEY `IDX_DA5587D0E6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_DA5587D03DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_DA5587D0E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_region_to_rating_board`
--

DROP TABLE IF EXISTS `wiki_game_release_region_to_rating_board`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_region_to_rating_board` (
  `rating_board_id` int NOT NULL,
  `region_id` int NOT NULL,
  PRIMARY KEY (`region_id`,`rating_board_id`),
  KEY `IDX_1D3B6BCA98260155` (`region_id`),
  KEY `IDX_1D3B6BCAB9D1E272` (`rating_board_id`),
  CONSTRAINT `FK_1D3B6BCA98260155` FOREIGN KEY (`region_id`) REFERENCES `wiki_game_release_region` (`id`),
  CONSTRAINT `FK_1D3B6BCAB9D1E272` FOREIGN KEY (`rating_board_id`) REFERENCES `wiki_game_release_rating_board` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_region_to_rating_board2`
--

DROP TABLE IF EXISTS `wiki_game_release_region_to_rating_board2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_region_to_rating_board2` (
  `region_id` int NOT NULL,
  `ratingboard_id` int NOT NULL,
  PRIMARY KEY (`region_id`,`ratingboard_id`),
  KEY `IDX_6179AEC898260155` (`region_id`),
  KEY `IDX_6179AEC870E84F80` (`ratingboard_id`),
  CONSTRAINT `FK_6179AEC870E84F80` FOREIGN KEY (`ratingboard_id`) REFERENCES `wiki_game_release_rating_board` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_6179AEC898260155` FOREIGN KEY (`region_id`) REFERENCES `wiki_game_release_region` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_resolution`
--

DROP TABLE IF EXISTS `wiki_game_release_resolution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_resolution` (
  `id` int NOT NULL AUTO_INCREMENT,
  `x_resolution` int DEFAULT NULL,
  `y_resolution` int DEFAULT NULL,
  `short_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `position` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_sound_system`
--

DROP TABLE IF EXISTS `wiki_game_release_sound_system`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_sound_system` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_stat`
--

DROP TABLE IF EXISTS `wiki_game_release_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `total_user_reviews` int NOT NULL,
  `average_user_rating` double NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81494 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_to_developer`
--

DROP TABLE IF EXISTS `wiki_game_release_to_developer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_to_developer` (
  `release_id` int NOT NULL,
  `company_id` int NOT NULL,
  PRIMARY KEY (`release_id`,`company_id`),
  KEY `IDX_B318CB4B12A727D` (`release_id`),
  KEY `IDX_B318CB4979B1AD6` (`company_id`),
  CONSTRAINT `FK_B318CB4979B1AD6` FOREIGN KEY (`company_id`) REFERENCES `wiki_company` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_B318CB4B12A727D` FOREIGN KEY (`release_id`) REFERENCES `wiki_game_release` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_to_multiplayer_feature`
--

DROP TABLE IF EXISTS `wiki_game_release_to_multiplayer_feature`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_to_multiplayer_feature` (
  `release_id` int NOT NULL,
  `feature_id` int NOT NULL,
  PRIMARY KEY (`release_id`,`feature_id`),
  KEY `IDX_D8596587B12A727D` (`release_id`),
  KEY `IDX_D859658760E4B879` (`feature_id`),
  CONSTRAINT `FK_D859658760E4B879` FOREIGN KEY (`feature_id`) REFERENCES `wiki_game_release_feature` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_D8596587B12A727D` FOREIGN KEY (`release_id`) REFERENCES `wiki_game_release` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_to_publisher`
--

DROP TABLE IF EXISTS `wiki_game_release_to_publisher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_to_publisher` (
  `release_id` int NOT NULL,
  `company_id` int NOT NULL,
  PRIMARY KEY (`release_id`,`company_id`),
  KEY `IDX_F222D268B12A727D` (`release_id`),
  KEY `IDX_F222D268979B1AD6` (`company_id`),
  CONSTRAINT `FK_F222D268979B1AD6` FOREIGN KEY (`company_id`) REFERENCES `wiki_company` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_F222D268B12A727D` FOREIGN KEY (`release_id`) REFERENCES `wiki_game_release` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_to_resolution`
--

DROP TABLE IF EXISTS `wiki_game_release_to_resolution`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_to_resolution` (
  `release_id` int NOT NULL,
  `resolution_id` int NOT NULL,
  PRIMARY KEY (`release_id`,`resolution_id`),
  KEY `IDX_216BC842B12A727D` (`release_id`),
  KEY `IDX_216BC84212A1C43A` (`resolution_id`),
  CONSTRAINT `FK_216BC84212A1C43A` FOREIGN KEY (`resolution_id`) REFERENCES `wiki_game_release_resolution` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_216BC842B12A727D` FOREIGN KEY (`release_id`) REFERENCES `wiki_game_release` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_to_singleplayer_feature`
--

DROP TABLE IF EXISTS `wiki_game_release_to_singleplayer_feature`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_to_singleplayer_feature` (
  `release_id` int NOT NULL,
  `feature_id` int NOT NULL,
  PRIMARY KEY (`release_id`,`feature_id`),
  KEY `IDX_E91CA936B12A727D` (`release_id`),
  KEY `IDX_E91CA93660E4B879` (`feature_id`),
  CONSTRAINT `FK_E91CA93660E4B879` FOREIGN KEY (`feature_id`) REFERENCES `wiki_game_release_feature` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_E91CA936B12A727D` FOREIGN KEY (`release_id`) REFERENCES `wiki_game_release` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_release_to_sound_system`
--

DROP TABLE IF EXISTS `wiki_game_release_to_sound_system`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_release_to_sound_system` (
  `release_id` int NOT NULL,
  `soundsystem_id` int NOT NULL,
  PRIMARY KEY (`release_id`,`soundsystem_id`),
  KEY `IDX_907CB2A5B12A727D` (`release_id`),
  KEY `IDX_907CB2A53EED34E9` (`soundsystem_id`),
  CONSTRAINT `FK_907CB2A53EED34E9` FOREIGN KEY (`soundsystem_id`) REFERENCES `wiki_game_release_sound_system` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_907CB2A5B12A727D` FOREIGN KEY (`release_id`) REFERENCES `wiki_game_release` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_role`
--

DROP TABLE IF EXISTS `wiki_game_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_role` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_stat`
--

DROP TABLE IF EXISTS `wiki_game_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `total_user_reviews` int NOT NULL,
  `average_user_rating` double NOT NULL,
  `total_releases` int NOT NULL,
  `total_dlc` int NOT NULL,
  `total_genres` int NOT NULL,
  `total_themes` int NOT NULL,
  `total_platforms` int NOT NULL,
  `total_characters` int NOT NULL,
  `total_similar` int NOT NULL,
  `total_concepts` int NOT NULL,
  `total_developers` int NOT NULL,
  `total_publishes` int NOT NULL,
  `total_franchises` int NOT NULL,
  `total_things` int NOT NULL,
  `total_locations` int NOT NULL,
  `total_people` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `sum_user_reviews` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=93068 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_theme`
--

DROP TABLE IF EXISTS `wiki_game_theme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_theme` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_id` int DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
-- Table structure for table `wiki_game_to_genre`
--

DROP TABLE IF EXISTS `wiki_game_to_genre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_to_genre` (
  `game_id` int NOT NULL,
  `genre_id` int NOT NULL,
  PRIMARY KEY (`game_id`,`genre_id`),
  KEY `IDX_571BB203E48FD905` (`game_id`),
  KEY `IDX_571BB2034296D31F` (`genre_id`),
  CONSTRAINT `FK_571BB2034296D31F` FOREIGN KEY (`genre_id`) REFERENCES `wiki_game_genre` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_571BB203E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_to_platform`
--

DROP TABLE IF EXISTS `wiki_game_to_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_to_platform` (
  `game_id` int NOT NULL,
  `platform_id` int NOT NULL,
  PRIMARY KEY (`game_id`,`platform_id`),
  KEY `IDX_43557D3CE48FD905` (`game_id`),
  KEY `IDX_43557D3CFFE6496F` (`platform_id`),
  CONSTRAINT `FK_43557D3CE48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_43557D3CFFE6496F` FOREIGN KEY (`platform_id`) REFERENCES `wiki_platform` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_game_to_theme`
--

DROP TABLE IF EXISTS `wiki_game_to_theme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_game_to_theme` (
  `game_id` int NOT NULL,
  `theme_id` int NOT NULL,
  PRIMARY KEY (`game_id`,`theme_id`),
  KEY `IDX_433E66F3E48FD905` (`game_id`),
  KEY `IDX_433E66F359027487` (`theme_id`),
  CONSTRAINT `FK_433E66F359027487` FOREIGN KEY (`theme_id`) REFERENCES `wiki_game_theme` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_433E66F3E48FD905` FOREIGN KEY (`game_id`) REFERENCES `wiki_game` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_location`
--

DROP TABLE IF EXISTS `wiki_location`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_B5588C0470AA3482` (`stats_id`),
  KEY `IDX_B5588C043DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_B5588C04E6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_B5588C043DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_B5588C0470AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_location_stat` (`id`),
  CONSTRAINT `FK_B5588C04E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8367 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_location_stat`
--

DROP TABLE IF EXISTS `wiki_location_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_location_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `total_characters` int NOT NULL,
  `total_games` int NOT NULL,
  `total_companies` int NOT NULL,
  `total_similar` int NOT NULL,
  `total_franchises` int NOT NULL,
  `total_platforms` int NOT NULL,
  `total_things` int NOT NULL,
  `total_concepts` int NOT NULL,
  `total_people` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8366 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_person`
--

DROP TABLE IF EXISTS `wiki_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_person` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` smallint DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `death` date DEFAULT NULL,
  `hometown` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `twitter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_D07D944470AA3482` (`stats_id`),
  KEY `IDX_D07D94443DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_D07D9444E6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_D07D94443DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_D07D944470AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_person_stat` (`id`),
  CONSTRAINT `FK_D07D9444E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=193138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_person_stat`
--

DROP TABLE IF EXISTS `wiki_person_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_person_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `total_characters` int NOT NULL,
  `total_games` int NOT NULL,
  `total_companies` int NOT NULL,
  `total_similar` int NOT NULL,
  `total_franchises` int NOT NULL,
  `total_platforms` int NOT NULL,
  `total_things` int NOT NULL,
  `total_concepts` int NOT NULL,
  `total_locations` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=217602 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_platform`
--

DROP TABLE IF EXISTS `wiki_platform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_platform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `short_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `release_date_type` smallint NOT NULL,
  `install_base` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `online_support` tinyint(1) NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `background_image_id` int DEFAULT NULL,
  `manufacturer_id` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_D294D50470AA3482` (`stats_id`),
  KEY `IDX_D294D5043DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_D294D504E6DA28AA` (`background_image_id`),
  KEY `IDX_D294D504A23B42D` (`manufacturer_id`),
  CONSTRAINT `FK_D294D5043DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_D294D50470AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_platform_stat` (`id`),
  CONSTRAINT `FK_D294D504A23B42D` FOREIGN KEY (`manufacturer_id`) REFERENCES `wiki_company` (`id`),
  CONSTRAINT `FK_D294D504E6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=212 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_platform_stat`
--

DROP TABLE IF EXISTS `wiki_platform_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_platform_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `total_characters` int NOT NULL,
  `total_games` int NOT NULL,
  `total_concepts` int NOT NULL,
  `total_things` int NOT NULL,
  `total_locations` int NOT NULL,
  `total_people` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=212 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_points`
--

DROP TABLE IF EXISTS `wiki_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_points` (
  `assoc_type_id` int NOT NULL,
  `assoc_id` int NOT NULL,
  `user_id` int NOT NULL,
  `legacy_points` int DEFAULT NULL,
  `points` int DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `total_edits` int NOT NULL,
  PRIMARY KEY (`assoc_type_id`,`assoc_id`,`user_id`),
  KEY `assoc_type_id_source_id_idx` (`assoc_type_id`,`assoc_id`),
  KEY `user_id_idx` (`user_id`),
  CONSTRAINT `FK_C31BCB1BA76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_points_rule`
--

DROP TABLE IF EXISTS `wiki_points_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_points_rule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `assoc_type_id` int DEFAULT NULL,
  `assoc_id` int DEFAULT NULL,
  `change_type` smallint DEFAULT NULL,
  `field_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `first_value` tinyint(1) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `points` int DEFAULT NULL,
  `points_multiplyer` int DEFAULT NULL,
  `num_characters` int DEFAULT NULL,
  `flat_bonus` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `date_created` datetime NOT NULL,
  `date_modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assoc_type_id` (`assoc_type_id`),
  KEY `assoc_id` (`assoc_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_submission`
--

DROP TABLE IF EXISTS `wiki_submission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_submission` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submitter_id` int DEFAULT NULL,
  `moderator_id` int DEFAULT NULL,
  `source_type_id` int NOT NULL,
  `source_id` int NOT NULL,
  `submitter_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `moderator_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_submitted` datetime NOT NULL,
  `date_moderated` datetime DEFAULT NULL,
  `page_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` int DEFAULT NULL,
  `points` int DEFAULT NULL,
  `state` int NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `parent_entity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_entity_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_entity_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_E70D8E3B919E5513` (`submitter_id`),
  KEY `IDX_E70D8E3BD0AFA354` (`moderator_id`),
  KEY `source_type_id_source_id_idx` (`source_type_id`,`source_id`),
  KEY `parent_entity_idx` (`parent_entity`(191)),
  KEY `state_idx` (`state`),
  KEY `date_submitted_idx` (`date_submitted`),
  KEY `date_created_idx` (`date_created`),
  CONSTRAINT `FK_E70D8E3B919E5513` FOREIGN KEY (`submitter_id`) REFERENCES `user` (`id`),
  CONSTRAINT `FK_E70D8E3BD0AFA354` FOREIGN KEY (`moderator_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=360500 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_submission_change`
--

DROP TABLE IF EXISTS `wiki_submission_change`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_submission_change` (
  `id` int NOT NULL AUTO_INCREMENT,
  `changeset_id` int DEFAULT NULL,
  `type` int NOT NULL,
  `field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` int NOT NULL,
  `submittedValue` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `originalValue` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `IDX_84822093C6135CC9` (`changeset_id`),
  CONSTRAINT `FK_84822093C6135CC9` FOREIGN KEY (`changeset_id`) REFERENCES `wiki_submission_changeset` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1033239 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_submission_changeset`
--

DROP TABLE IF EXISTS `wiki_submission_changeset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_submission_changeset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int DEFAULT NULL,
  `type` int NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_8F73E55FE1FD4933` (`submission_id`),
  CONSTRAINT `FK_8F73E55FE1FD4933` FOREIGN KEY (`submission_id`) REFERENCES `wiki_submission` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=523149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_submission_stats`
--

DROP TABLE IF EXISTS `wiki_submission_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_submission_stats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_count` int NOT NULL,
  `type_id` int NOT NULL,
  `object_id` int DEFAULT NULL,
  `object_name` int NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_thing`
--

DROP TABLE IF EXISTS `wiki_thing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_thing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image_id` int DEFAULT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliases` varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deck` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_new` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_important` tinyint(1) NOT NULL,
  `background_image_id` int DEFAULT NULL,
  `stats_id` int DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_F4C6150F70AA3482` (`stats_id`),
  KEY `IDX_F4C6150F3DA5256D` (`image_id`),
  KEY `name_idx` (`name`(191),`id`),
  KEY `IDX_F4C6150FE6DA28AA` (`background_image_id`),
  CONSTRAINT `FK_F4C6150F3DA5256D` FOREIGN KEY (`image_id`) REFERENCES `image` (`id`),
  CONSTRAINT `FK_F4C6150F70AA3482` FOREIGN KEY (`stats_id`) REFERENCES `wiki_thing_stat` (`id`),
  CONSTRAINT `FK_F4C6150FE6DA28AA` FOREIGN KEY (`background_image_id`) REFERENCES `image` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7712 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_thing_stat`
--

DROP TABLE IF EXISTS `wiki_thing_stat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_thing_stat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `total_characters` int NOT NULL,
  `total_games` int NOT NULL,
  `total_companies` int NOT NULL,
  `total_similar` int NOT NULL,
  `total_franchises` int NOT NULL,
  `total_platforms` int NOT NULL,
  `total_people` int NOT NULL,
  `total_concepts` int NOT NULL,
  `total_locations` int NOT NULL,
  `first_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_letter_is_num` tinyint(1) NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7712 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_triage`
--

DROP TABLE IF EXISTS `wiki_triage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_triage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `assoc_type_id` int NOT NULL,
  `assoc_id` int NOT NULL,
  `tally` int NOT NULL,
  `factor` int NOT NULL,
  `date_updated` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `assoc_type_id_assoc_id_idx` (`assoc_type_id`,`assoc_id`),
  KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiki_view_stats`
--

DROP TABLE IF EXISTS `wiki_view_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiki_view_stats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `views` int NOT NULL,
  `type_id` int NOT NULL,
  `object_id` int NOT NULL,
  `object_name` int NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET FOREIGN_KEY_CHECKS = 1;
