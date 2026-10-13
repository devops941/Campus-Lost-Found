
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `claimant_id` int(11) NOT NULL,
  `answer_given` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pending_claim` (`claimant_id`,`match_id`),
  KEY `match_id` (`match_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `claims_ibfk_2` FOREIGN KEY (`claimant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `claims_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `claims` WRITE;
/*!40000 ALTER TABLE `claims` DISABLE KEYS */;
INSERT INTO `claims` VALUES (1,1,2,'john','pending',NULL,NULL,NULL,'2026-10-12 10:14:27'),(2,5,2,'JD','approved',1,'2026-10-07 05:30:00','Verification answer matched. Item returned.','2026-10-12 10:14:27'),(3,6,5,'blue','rejected',1,'2026-10-07 07:00:00','Verification answer did not match.','2026-10-12 10:14:27'),(4,7,6,'1','pending',NULL,NULL,NULL,'2026-10-12 10:47:30'),(5,8,1,'yellow small size','approved',1,'2026-10-12 07:43:42','APPROVE','2026-10-12 11:00:33'),(6,11,9,'Chemistry','approved',1,'2026-10-13 01:47:52','verified','2026-10-13 05:17:13');
/*!40000 ALTER TABLE `claims` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('lost','found') NOT NULL,
  `category` enum('id_card','electronics','books','bottle','bag','keys','other') NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `item_date` date DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('open','claimed','returned') NOT NULL DEFAULT 'open',
  `verify_question` varchar(255) DEFAULT NULL,
  `verify_answer` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_type_status` (`type`,`status`),
  KEY `idx_category` (`category`),
  KEY `idx_item_date` (`item_date`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,2,'lost','electronics','Lost black Casio calculator','Black Casio scientific calculator, model fx-991 series, lost near the exam hall.','Exam Hall Block B','2026-10-01',NULL,'open',NULL,NULL,'2026-10-12 10:14:27'),(2,3,'lost','bottle','Lost blue water bottle','Blue 1-litre stainless steel water bottle with college sticker on the side.','Canteen','2026-10-03',NULL,'open',NULL,NULL,'2026-10-12 10:14:27'),(3,4,'lost','id_card','Lost student ID card','College ID card with name Mike Jones, roll number 2023CS045.','Library','2026-10-04',NULL,'open',NULL,NULL,'2026-10-12 10:14:27'),(4,5,'lost','books','Lost physics textbook','HC Verma Concepts of Physics Part 1, with yellow highlighter markings throughout.','Reading Room','2026-10-05',NULL,'open',NULL,NULL,'2026-10-12 10:14:27'),(5,2,'lost','bag','Lost black laptop bag','HP branded black laptop bag with a red zipper pull and initials JD on the front pocket.','Computer Lab','2026-10-06',NULL,'open',NULL,NULL,'2026-10-12 10:14:27'),(6,3,'lost','keys','Lost key bunch with hostel tag','Three keys on a metal ring with a pink hostel tag marked H-204.','Girls Hostel Gate','2026-10-07',NULL,'open',NULL,NULL,'2026-10-12 10:14:27'),(7,4,'lost','electronics','Lost Sony earphones','Sony WH-1000XM4 wired earphones, black, left earcup has a small scratch.','Sports Ground','2026-10-08',NULL,'open',NULL,NULL,'2026-10-12 10:14:27'),(8,5,'found','electronics','Found Casio fx-991 calculator near exam hall','Found a Casio scientific calculator fx-991 ES plus near exam hall entrance, black colour.','Exam Hall Entrance','2026-10-02',NULL,'open','What is written on the back of the calculator?','john','2026-10-12 10:14:27'),(9,2,'found','bottle','Found blue water bottle in canteen','Found a blue stainless steel water bottle in the canteen, has a sticker on it.','Canteen','2026-10-03',NULL,'open','What sticker is on the bottle?','college logo','2026-10-12 10:14:27'),(10,3,'found','id_card','Found college ID card in library','Found a college student ID card near the periodicals section in the library.','Library Periodicals','2026-10-04',NULL,'open','What is the roll number on the card?','2023CS045','2026-10-12 10:14:27'),(11,5,'found','keys','Found keys near hostel gate','Found three keys on a ring near the main gate, tag attached to it.','Main Gate','2026-10-07',NULL,'open','What colour is the tag on the keys?','pink','2026-10-12 10:14:27'),(12,4,'found','bag','Found laptop bag near computer lab','Found a black laptop bag with initials on the front pocket, left outside computer lab.','Computer Lab Corridor','2026-10-06',NULL,'claimed','What initials are on the front pocket of the bag?','JD','2026-10-12 10:14:27'),(13,2,'found','books','Found physics book in reading room','Found HC Verma part 1 on reading room table, has yellow highlighting throughout.','Reading Room Table 3','2026-10-05',NULL,'returned','What colour highlighter was used inside the book?','yellow','2026-10-12 10:14:27'),(14,6,'lost','id_card','Scholl id card','Blue color badge added','exam hall','2026-10-09','img_6accba8aad18b1.36879637.png','claimed',NULL,NULL,'2026-10-12 10:46:34'),(15,1,'lost','bottle','yellow color water bottle','plastic yellow bottle','Exam hall','2026-10-12','img_6accbc1ddd5ae8.45944412.png','returned',NULL,NULL,'2026-10-12 10:53:17'),(16,6,'found','bottle','water bottle','yellow','exam hall','2026-10-12','img_6accbdb7001c34.42725890.png','returned','which color of the water bottle and size','yellow small size','2026-10-12 11:00:07'),(17,2,'lost','keys','Bike key','Bike key with tom and cherry keychain','library','2026-10-12','img_6accc492adc368.48851271.png','open',NULL,NULL,'2026-10-12 11:29:22'),(18,5,'found','keys','bike key with cartoon keychain',NULL,'library','2026-10-12',NULL,'open','what is keychian','tom','2026-10-12 11:32:21'),(19,7,'lost','electronics','ear buds','ear buds oneplus green','library','2026-10-12',NULL,'open',NULL,NULL,'2026-10-12 12:13:59'),(20,8,'found','electronics','ear buds','oneplus ear buds',NULL,'2026-10-12',NULL,'open','what company of the earbuds','oneplus','2026-10-12 12:18:03'),(21,9,'lost','books','12th chemistry book','12th chemistry book backside label added with my name','class room','2026-10-13','img_6acdbbd1269e72.35500274.png','returned',NULL,NULL,'2026-10-13 05:04:17'),(22,10,'found','books','chemistry book missing','12th chemistry book','class room','2026-10-13','img_6acdbd19506c19.80830739.png','returned','which subject book is missing','Chemistry','2026-10-13 05:09:45');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lost_item_id` int(11) NOT NULL,
  `found_item_id` int(11) NOT NULL,
  `score` decimal(4,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_match` (`lost_item_id`,`found_item_id`),
  KEY `found_item_id` (`found_item_id`),
  CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`lost_item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`found_item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `matches` WRITE;
/*!40000 ALTER TABLE `matches` DISABLE KEYS */;
INSERT INTO `matches` VALUES (1,1,8,0.85,'2026-10-12 10:14:27'),(2,2,9,0.72,'2026-10-12 10:14:27'),(3,3,10,0.90,'2026-10-12 10:14:27'),(4,6,11,0.68,'2026-10-12 10:14:27'),(5,5,12,0.80,'2026-10-12 10:14:27'),(6,4,13,0.75,'2026-10-12 10:14:27'),(7,14,10,0.51,'2026-10-12 10:46:34'),(8,15,16,0.76,'2026-10-12 11:00:07'),(9,17,18,0.70,'2026-10-12 11:32:21'),(10,19,20,0.85,'2026-10-12 12:18:03'),(11,21,22,0.63,'2026-10-13 05:09:45');
/*!40000 ALTER TABLE `matches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin User','admin@college.edu','$2y$10$1TmPkaidURSPxlAEUPtCZuuVXUrP0gOEbXG1coJBPSTsDmID3UIAm','9000000001','admin',0,'2026-10-12 10:14:27'),(2,'John Doe','john.doe@college.edu','$2y$10$gHvBnhzv/X7VqNIlxyy3r.adrOMxYU5zzROncOJfn/FUpOzeIvxaK','9000000002','user',0,'2026-10-12 10:14:27'),(3,'Jane Smith','jane.smith@college.edu','$2y$10$5hTkYguy77xjBs6WRFePF.nvgNfiSYZfJ4vUtZYjJFKCm8gdLQApq','9000000003','user',0,'2026-10-12 10:14:27'),(4,'Mike Jones','mike.jones@college.edu','$2y$10$stpfDoyzOtVFKmjva6akJO0ktQcLba65gBuSMQ5t097.q.4ZYREee','9000000004','user',0,'2026-10-12 10:14:27'),(5,'Sara Wilson','sara.wilson@college.edu','$2y$10$2./mR4us/atMHICP3J4FJeDkqLSzhaIjBjgO/LuzGfTmJi6bbcjwm','9000000005','user',0,'2026-10-12 10:14:27'),(6,'Bharath','14598bharath@college.edu','$2y$10$8UnZCMzJ4mbYsfzCbsWt6ucJFp1e38Ne.AMd1QV.NArzqBXHFK1Cy','9500847250','user',0,'2026-10-12 10:44:06'),(7,'Vasanth','vasanth@college.edu','$2y$10$DzqTrResVxmkX1qr0RNLqOSbJ0/YvALhvJXobRpNvV78/91MVQSAy','9500872250','user',0,'2026-10-12 12:06:56'),(8,'kumar','kumar@college.edu','$2y$10$em8T8lGpnYfmIKCEE08yJeHCceQgE2blbG7J8lWl1XgfayZlXMf26',NULL,'user',0,'2026-10-12 12:16:55'),(9,'Tharun','tharun@college.edu','$2y$10$tB2qLIRhkwJ1DycmOmFZ3uu5NZ97RgA1YjV91/ljoqF8YmCZ.ed4O',NULL,'user',0,'2026-10-13 05:01:02'),(10,'Varun','varun@college.edu','$2y$10$fRd1o54B3tx04z9XF8aiteC2Ie.tVk/dP2v/ApF2Eyzgn7Sm6CE7O',NULL,'user',0,'2026-10-13 05:05:27');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

