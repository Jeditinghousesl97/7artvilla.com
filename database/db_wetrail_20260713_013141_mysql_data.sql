/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.10-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: wetrail
-- ------------------------------------------------------
-- Server version	10.11.10-MariaDB-log

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

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES
(1,'wetrail-admin','$2y$10$9dS3vRP88Ob5nCZtBJ3gz.hafQkKmCZpGH96LzwiNDBbKweK47KRG','Administrator','2026-06-07 17:36:47','2026-04-07 18:16:16');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `destination_categories`
--

DROP TABLE IF EXISTS `destination_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `destination_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `destination_categories`
--

LOCK TABLES `destination_categories` WRITE;
/*!40000 ALTER TABLE `destination_categories` DISABLE KEYS */;
INSERT INTO `destination_categories` VALUES
(1,'Adventure','category','',0,1,'2026-05-04 15:07:12','2026-05-04 15:07:12'),
(5,'Nature Trails','nature-trails','',0,1,'2026-05-04 16:38:31','2026-05-04 16:38:31');
/*!40000 ALTER TABLE `destination_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `destination_category_map`
--

DROP TABLE IF EXISTS `destination_category_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `destination_category_map` (
  `destination_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`destination_id`,`category_id`),
  KEY `idx_dcm_category` (`category_id`),
  CONSTRAINT `fk_dcm_category` FOREIGN KEY (`category_id`) REFERENCES `destination_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dcm_destination` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `destination_category_map`
--

LOCK TABLES `destination_category_map` WRITE;
/*!40000 ALTER TABLE `destination_category_map` DISABLE KEYS */;
INSERT INTO `destination_category_map` VALUES
(10,1,'2026-06-07 22:36:15'),
(11,5,'2026-06-07 22:38:30'),
(12,5,'2026-06-07 22:39:59');
/*!40000 ALTER TABLE `destination_category_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `destination_gallery_images`
--

DROP TABLE IF EXISTS `destination_gallery_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `destination_gallery_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `destination_id` int(10) unsigned NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_destination_gallery` (`destination_id`),
  CONSTRAINT `fk_destination_gallery_destination` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `destination_gallery_images`
--

LOCK TABLES `destination_gallery_images` WRITE;
/*!40000 ALTER TABLE `destination_gallery_images` DISABLE KEYS */;
INSERT INTO `destination_gallery_images` VALUES
(33,10,'assets/images/destinations/gallery/destination_gallery_1780851925_53e21b09.jpg','',1,'2026-06-07 22:35:25','2026-06-07 22:35:25'),
(34,10,'assets/images/destinations/gallery/destination_gallery_1780851925_062edec3.jpg','',2,'2026-06-07 22:35:25','2026-06-07 22:35:25'),
(35,10,'assets/images/destinations/gallery/destination_gallery_1780851925_7a71c721.jpg','',3,'2026-06-07 22:35:25','2026-06-07 22:35:25'),
(36,10,'assets/images/destinations/gallery/destination_gallery_1780851925_bd5d1915.jpg','',4,'2026-06-07 22:35:25','2026-06-07 22:35:25'),
(37,12,'assets/images/destinations/gallery/destination_gallery_1780852199_cceba896.jpg','',1,'2026-06-07 22:39:59','2026-06-07 22:39:59'),
(38,12,'assets/images/destinations/gallery/destination_gallery_1780852199_406c6cb5.jpg','',2,'2026-06-07 22:39:59','2026-06-07 22:39:59'),
(39,12,'assets/images/destinations/gallery/destination_gallery_1780852199_7aa1e97c.jpg','',3,'2026-06-07 22:39:59','2026-06-07 22:39:59'),
(40,12,'assets/images/destinations/gallery/destination_gallery_1780852199_3058e0de.jpg','',4,'2026-06-07 22:39:59','2026-06-07 22:39:59');
/*!40000 ALTER TABLE `destination_gallery_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `destinations`
--

DROP TABLE IF EXISTS `destinations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `destinations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_summary` text DEFAULT NULL,
  `description` longtext NOT NULL,
  `map_embed_html` longtext DEFAULT NULL,
  `distance_from_villa` varchar(120) DEFAULT NULL,
  `travel_time_from_villa` varchar(120) DEFAULT NULL,
  `best_time_to_visit` varchar(160) DEFAULT NULL,
  `things_to_do` longtext DEFAULT NULL,
  `featured_image_path` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_homepage` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `destinations`
--

LOCK TABLES `destinations` WRITE;
/*!40000 ALTER TABLE `destinations` DISABLE KEYS */;
INSERT INTO `destinations` VALUES
(10,'Arugam Bay Beach Escape','destination','A world-famous coastal destination known for surfing, golden beaches, beach cafés, relaxed nightlife, and a vibrant international travel atmosphere.','Arugam Bay is one of Sri Lanka’s most loved beach destinations, especially popular among surfers, nature lovers, and relaxed coastal travelers. Located a short drive from Panama, it offers a beautiful mix of ocean views, surf culture, seafood restaurants, sunset walks, and laid-back beach experiences. It is an ideal nearby destination for guests who want both adventure and relaxation during their stay at We Trail Villa. Arugam Bay is about 12.4 km from Panama, with taxi travel commonly listed at around 11 minutes, depending on the exact location and road conditions.','<iframe src=\"https://www.google.com/maps?q=Arugam%20Bay%2C%20Sri%20Lanka&output=embed\" width=\"100%\" height=\"450\" style=\"border:0;\" allowfullscreen=\"\" loading=\"lazy\" referrerpolicy=\"no-referrer-when-downgrade\"></iframe>','12–15 km','15–20 minutes','May to September is ideal for surfing and beach activities on the east coast. Early morning and evening are best for beach walks and photography. Sri Lanka’s no','[\"Surfing at Arugam Bay\",\"Relax on the beach\",\"Enjoy seafood restaurants\",\"Watch the sunset\",\"Visit beach caf\\u00e9s\",\"Photography near the coastline\",\"Experience local nightlife\"]','assets/images/destinations/destination_1780851975_29e952fe.webp',1,1,1,1,'2026-06-07 22:35:25','2026-06-07 22:36:15'),
(11,'Paanama Village Experience','paanama-village-experience','A peaceful coastal village surrounded by nature, lagoons, beaches, wildlife routes, and authentic Sri Lankan village charm.','Paanama is a calm and beautiful village area near the southeastern coast of Sri Lanka. It is ideal for guests who enjoy quiet surroundings, traditional village life, nature walks, birdwatching, and peaceful beaches away from heavy crowds. As We Trail Villa is located in Panama, guests can easily explore the local lifestyle, nearby coastal scenery, and natural surroundings without long travel. Panama is also known as the last populated settlement in the southernmost part of the Eastern Province, with Kumana Bird Sanctuary and heritage areas beginning southwards from the village.','<iframe src=\"https://www.google.com/maps?q=Panama%2C%20Sri%20Lanka&output=embed\" width=\"100%\" height=\"450\" style=\"border:0;\" allowfullscreen=\"\" loading=\"lazy\" referrerpolicy=\"no-referrer-when-downgrade\"></iframe>','1-3 km','5–10 minutes','Early morning and late afternoon are best for village walks, photography, birdwatching, and peaceful outdoor experiences.','[\"Village walking experience\",\"Explore Panama Beach\",\"Birdwatching\",\"Photography\",\"Enjoy local lifestyle\",\"Nature walks\",\"Visit nearby lagoons\"]','assets/images/destinations/destination_1780852110_080381ae.jpg',1,1,1,2,'2026-06-07 22:38:30','2026-06-07 22:38:30'),
(12,'Kudumbigala Ancient Monastery','kudumbigala-ancient-monastery','A historic forest monastery surrounded by rocks, jungle, meditation caves, wildlife landscapes, and peaceful spiritual scenery.','Kudumbigala is one of the most remarkable historical and spiritual sites near Panama. Hidden within a forest environment close to Kumana National Park, this ancient rock monastery is known for its peaceful atmosphere, archaeological ruins, meditation caves, and panoramic views from the rocky summit. It is a meaningful destination for travelers who love history, culture, nature, and quiet exploration. Kudumbigala is described as a remote forest hermitage near Kumana, with a history of more than 2,000 years and many rock shelters once used by monks.','<iframe src=\"https://www.google.com/maps?q=Kudumbigala%20Monastery%2C%20Sri%20Lanka&output=embed\" width=\"100%\" height=\"450\" style=\"border:0;\" allowfullscreen=\"\" loading=\"lazy\" referrerpolicy=\"no-referrer-when-downgrade\"></iframe>','18–22 km','30–45 minutes','Early morning is the best time to visit, climb, and explore comfortably before the midday heat. Dry-season months are generally better for access and outdoor ex','[\"Visit ancient monastery ruins\",\"Climb the rock viewpoint\",\"Explore meditation caves\",\"Enjoy peaceful forest scenery\",\"Photography\",\"Learn local history\",\"Experience spiritual calm\"]','assets/images/destinations/destination_1780852199_c7ce5be7.jpg',1,1,1,3,'2026-06-07 22:39:59','2026-06-07 22:39:59');
/*!40000 ALTER TABLE `destinations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gallery_images`
--

DROP TABLE IF EXISTS `gallery_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gallery_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `caption` varchar(255) DEFAULT NULL,
  `category` enum('villa','pool','views','nature','dining') NOT NULL DEFAULT 'villa',
  `image_path` varchar(255) NOT NULL,
  `show_on_home` tinyint(1) NOT NULL DEFAULT 0,
  `span_col` tinyint(1) NOT NULL DEFAULT 0,
  `span_row` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gallery_images`
--

LOCK TABLES `gallery_images` WRITE;
/*!40000 ALTER TABLE `gallery_images` DISABLE KEYS */;
INSERT INTO `gallery_images` VALUES
(43,'','villa','assets/images/gallery/gal_1780848058_d35e730b.jpg',1,0,0,1,0,'2026-06-07 21:30:58','2026-06-07 21:30:58'),
(44,'','villa','assets/images/gallery/gal_1780848058_ade2df8e.jpg',1,0,0,1,0,'2026-06-07 21:30:58','2026-06-07 21:30:58'),
(45,'','villa','assets/images/gallery/gal_1780848058_48aedd76.jpg',1,0,0,1,0,'2026-06-07 21:30:58','2026-06-07 21:30:58'),
(46,'','villa','assets/images/gallery/gal_1780848058_a67530cf.webp',1,0,0,1,0,'2026-06-07 21:30:58','2026-06-07 21:30:58'),
(47,'','villa','assets/images/gallery/gal_1780848058_0bbf3249.jpg',1,0,0,1,0,'2026-06-07 21:30:58','2026-06-07 21:30:58'),
(48,'','villa','assets/images/gallery/gal_1780848058_a4aa21fc.webp',1,0,0,1,0,'2026-06-07 21:30:58','2026-06-07 21:30:58'),
(49,'','views','assets/images/gallery/gal_1780848084_c40c02f5.jpg',1,0,0,1,0,'2026-06-07 21:31:24','2026-06-07 21:31:24'),
(50,'','views','assets/images/gallery/gal_1780848084_33b13c40.jpg',1,0,0,1,0,'2026-06-07 21:31:24','2026-06-07 21:31:24');
/*!40000 ALTER TABLE `gallery_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inquiries`
--

DROP TABLE IF EXISTS `inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inquiries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `checkin` date DEFAULT NULL,
  `checkout` date DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied') NOT NULL DEFAULT 'unread',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inquiries`
--

LOCK TABLES `inquiries` WRITE;
/*!40000 ALTER TABLE `inquiries` DISABLE KEYS */;
/*!40000 ALTER TABLE `inquiries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(160) NOT NULL,
  `description` text NOT NULL,
  `category` enum('included','request','extra') NOT NULL DEFAULT 'included',
  `icon` varchar(60) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES
(4,'Butler Service','A dedicated butler attends to every request — from room setup to arranging special surprises for your loved ones.','included','fa-concierge-bell','[]',1,1,'assets/images/services/svc_1778255403_a3fb2fbc.webp','2026-05-08 15:50:03','2026-06-07 21:18:07'),
(5,'Private Dining','Enjoy authentic Sri Lankan and international cuisine, prepared fresh and served privately in your villa.','included','fa-utensils','[]',1,2,'assets/images/services/svc_1778255527_bd2a5b38.jpeg','2026-05-08 15:52:07','2026-06-07 21:18:13'),
(6,'Tour Assistance','Curated local excursions and transport arrangements to help you explore the highlands with ease.','request','fa-route','[]',0,3,'assets/images/services/svc_1778255638_cb54eed1.jpg','2026-05-08 15:53:58','2026-06-07 18:17:23'),
(7,'Airport Transfer','Comfortable and reliable private transfers arranged from and to the airport at your convenience.','request','fa-car','[]',1,4,'assets/images/services/svc_1778255814_5f310247.webp','2026-05-08 15:56:54','2026-06-07 21:18:31'),
(8,'4x4 Journeys & Adventures','Ready to experience adventurous journeys through breathtaking destinations around the country','request','fa-binoculars','[]',1,5,'assets/images/services/svc_1778256104_89ff5bfc.webp','2026-05-08 16:01:44','2026-06-07 21:18:24'),
(9,'Celebration Planning','Mark a birthday, anniversary, proposal, or family milestone with thoughtful decorations, private dining arrangements, custom cakes, and photography support.','included','fa-wine-glass-alt','[]',1,6,'assets/images/services/svc_1778256313_69e02121.jpg','2026-05-08 16:05:13','2026-06-07 21:18:28'),
(10,'Villa Accommodation','Relax in a peaceful nature and beach villa designed for comfort, privacy, and a calming stay near Panama Beach.','included','fa-bed','[\"Comfortable private rooms\",\"Peaceful natural surroundings\",\"Ideal for couples and families\",\"Relaxing coastal atmosphere\"]',1,1,'assets/images/services/svc_1780847355_6d5b11bd.jpg','2026-06-07 21:10:28','2026-06-07 21:19:15'),
(11,'Beach Experiences','Enjoy refreshing beach moments near Panama Beach with beautiful coastal views, sea breeze, and relaxing outdoor time.','included','fa-umbrella-beach','[\"Close to Panama Beach\",\"Relaxing beach walks\",\"Beautiful sunset views\",\"Perfect for nature lovers\"]',1,2,'assets/images/services/svc_1780847664_a16374f9.jpg','2026-06-07 21:13:00','2026-06-07 21:24:24'),
(12,'Nature Retreat','Immerse yourself in a calm natural environment surrounded by greenery, fresh air, and the quiet beauty of Panama.','included','fa-leaf','[\"Peaceful green environment\",\"Fresh tropical atmosphere\",\"Ideal for relaxation\",\"Nature-inspired villa stay\"]',1,3,'assets/images/services/svc_1780847749_2935d969.jpg','2026-06-07 21:14:20','2026-06-07 21:25:49'),
(13,'Local Dining','Taste simple and authentic Sri Lankan flavours prepared with local inspiration for a homely and memorable dining experience.','included','fa-utensils','[\"Sri Lankan food options\",\"Fresh local flavours\",\"Homely dining experience\",\"Guest-friendly meal arrangements\"]',1,4,'assets/images/services/svc_1780847824_3e5ceac5.jpg','2026-06-07 21:15:09','2026-06-07 21:27:04'),
(14,'Wildlife & Village Tours','Explore the natural charm, village lifestyle, and nearby attractions around Panama with guided travel experiences','request','fa-binoculars','[\"Nearby nature explorations\",\"Village lifestyle experiences\",\"Guided local visits\",\"Memorable outdoor activities\"]',1,5,'assets/images/services/svc_1780847957_1e9d7461.jpg','2026-06-07 21:16:02','2026-06-07 21:29:17'),
(15,'Guest Assistance','Receive friendly support throughout your stay, including travel guidance, local information, and helpful arrangements.','included','fa-concierge-bell','[\"Friendly guest support\",\"Local travel guidance\",\"Flexible stay assistance\",\"Warm Sri Lankan hospitality\"]',1,6,'assets/images/services/svc_1780848130_83a9bf00.jpg','2026-06-07 21:16:41','2026-06-07 21:32:10');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_val` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES
('about_heading','Experience Serenity by Panama Beach','2026-06-07 21:00:05'),
('about_image_accent','assets/images/about/about_image_accent_1777627989.jpg','2026-05-01 09:33:09'),
('about_image_main','assets/images/about/about_image_main_1777627989.jpg','2026-05-01 09:33:09'),
('about_label','Nature Beach Escape','2026-06-07 21:00:05'),
('about_paragraph1','We Trail (Pvt) Ltd welcomes you to a peaceful nature and beach villa near Panama Beach, Sri Lanka, where tropical beauty, coastal comfort, and authentic island charm come together for a truly relaxing getaway.','2026-06-07 21:00:05'),
('about_paragraph2','Surrounded by nature and ocean breeze, our resort offers guests a calm stay with warm hospitality and unforgettable Sri Lankan experiences.','2026-06-07 21:00:05'),
('about_stat1_label','Private','2026-04-07 18:10:00'),
('about_stat1_number','100%','2026-04-07 18:10:00'),
('about_stat2_label','Exclusive Villa','2026-04-07 18:10:00'),
('about_stat2_number','3','2026-06-07 21:00:05'),
('about_stat3_label','Butler Service','2026-04-07 18:10:00'),
('about_stat3_number','24/7','2026-04-07 18:10:00'),
('checkin_time','2:00 PM','2026-04-07 18:10:00'),
('checkout_time','12:00 PM','2026-05-08 16:27:18'),
('email','info@wetrail.lk','2026-06-07 21:01:56'),
('extra_guest_charge','Can be Discussed','2026-05-08 16:27:18'),
('facebook','','2026-06-07 21:01:56'),
('fb_pixel_id','','2026-04-07 18:10:00'),
('ga_id','','2026-04-07 18:10:00'),
('hero_image','assets/images/hero/hero_1780845504.jpg','2026-06-07 20:48:24'),
('instagram','','2026-04-07 18:10:00'),
('maintenance_message','We\'re currently performing scheduled maintenance. We\'ll be back shortly. Thank you for your patience.','2026-04-07 18:10:00'),
('maintenance_mode','0','2026-04-07 18:10:00'),
('maps_embed_url','https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15848.48230652646!2d81.8082617!3d6.7551474!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae5be954a69c309%3A0x8e7056aff4957096!2sPanama!5e0!3m2!1sen!2slk!4v1780846379742!5m2!1sen!2slk','2026-06-07 21:03:20'),
('maps_url','https://maps.app.goo.gl/kf36Bm8iMdyxNRJH8','2026-06-07 21:03:20'),
('min_stay','1 Night','2026-04-07 18:10:00'),
('phone','+94 777 388810','2026-06-07 21:01:56'),
('pricing_note','All rates are subject to change. Prices are per villa per night and inclusive of applicable taxes. Additional guests (beyond 2) are charged per person per night. Contact us for long-stay discounts and group arrangements.','2026-05-08 16:27:18'),
('site_meta_description','Discover We Trail (Pvt) Ltd, a peaceful nature and beach villa near Panama Beach, Sri Lanka, offering relaxing stays, coastal beauty, warm hospitality, and authentic Sri Lankan experiences.','2026-06-07 21:03:50'),
('smtp_encryption','ssl','2026-05-04 09:54:08'),
('smtp_from_email','admin@asseminate.com','2026-05-04 09:54:08'),
('smtp_from_name','We Trail','2026-06-07 21:04:51'),
('smtp_host','smtp.zoho.com','2026-05-04 09:54:08'),
('smtp_notify_email','jeditinghousesl@gmail.com','2026-06-07 21:04:51'),
('smtp_pass','Student1@ipmceylon','2026-05-04 09:54:08'),
('smtp_port','465','2026-05-04 09:54:08'),
('smtp_user','admin@asseminate.com','2026-05-04 09:54:08'),
('sticky_header_bg','#004961','2026-06-07 20:53:12'),
('sticky_header_button_bg','#C8961E','2026-06-07 20:48:24'),
('sticky_header_button_text','#1A1A1A','2026-06-07 20:48:24'),
('sticky_menu_item_color','#FFFFFF','2026-06-07 20:48:24'),
('theme_brown','#8A6A4A','2026-06-07 20:43:56'),
('theme_brown_dark','#6A4F36','2026-06-07 20:43:56'),
('theme_cream','#FAF7F0','2026-06-07 20:43:56'),
('theme_cream_alt','#EDE4D3','2026-06-07 20:43:56'),
('theme_dark','#00567A','2026-06-07 22:26:48'),
('theme_dark_alt','#0F2F38','2026-06-07 20:43:56'),
('theme_dark_soft','#1E4E57','2026-06-07 20:43:56'),
('theme_gold','#D6BE8A','2026-06-07 20:43:56'),
('theme_gold_dark','#B79B63','2026-06-07 20:43:56'),
('theme_green','#4E7A57','2026-06-07 20:43:56'),
('theme_green_dark','#35563C','2026-06-07 20:43:56'),
('theme_text','#2B2B2B','2026-06-07 20:43:56'),
('theme_text_light','#5F6368','2026-06-07 20:43:56'),
('theme_text_muted','#8B8F94','2026-06-07 20:43:56'),
('tiktok','','2026-04-07 18:10:00'),
('tripadvisor','','2026-04-07 18:10:00'),
('turnstile_enabled','0','2026-06-07 20:49:02'),
('turnstile_secret_key','','2026-06-07 20:23:11'),
('turnstile_site_key','0x4AAAAAACzgnaLRNWQnyeaN','2026-05-04 14:15:12'),
('twitter','','2026-04-07 18:10:00'),
('villa_bedrooms','2 Bedroom','2026-05-08 16:27:34'),
('villa_capacity','2 – 4 Guests','2026-04-07 18:10:00'),
('villa_pool','Private','2026-04-07 18:10:00'),
('whatsapp','94 777 388810','2026-06-07 21:01:56'),
('youtube','','2026-06-07 21:01:56');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tour_gallery_images`
--

DROP TABLE IF EXISTS `tour_gallery_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tour_gallery_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` int(10) unsigned NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tour_gallery_tour` (`tour_id`),
  CONSTRAINT `fk_tour_gallery_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tour_gallery_images`
--

LOCK TABLES `tour_gallery_images` WRITE;
/*!40000 ALTER TABLE `tour_gallery_images` DISABLE KEYS */;
INSERT INTO `tour_gallery_images` VALUES
(18,6,'assets/images/tours/album/tour_album_1780852595_6f963840.jpg','',1,'2026-06-07 22:46:35','2026-06-07 22:46:35'),
(19,6,'assets/images/tours/album/tour_album_1780852595_829d4129.jpg','',2,'2026-06-07 22:46:35','2026-06-07 22:46:35'),
(20,6,'assets/images/tours/album/tour_album_1780852595_f165b874.jpg','',3,'2026-06-07 22:46:35','2026-06-07 22:46:35'),
(21,6,'assets/images/tours/album/tour_album_1780852595_ac121078.jpg','',4,'2026-06-07 22:46:35','2026-06-07 22:46:35'),
(22,6,'assets/images/tours/album/tour_album_1780852595_5ddbcda2.jpg','',5,'2026-06-07 22:46:35','2026-06-07 22:46:35');
/*!40000 ALTER TABLE `tour_gallery_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tour_itinerary_items`
--

DROP TABLE IF EXISTS `tour_itinerary_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tour_itinerary_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tour_id` int(10) unsigned NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `image_1_path` varchar(255) DEFAULT NULL,
  `image_2_path` varchar(255) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tour_itinerary_tour` (`tour_id`),
  CONSTRAINT `fk_tour_itinerary_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tour_itinerary_items`
--

LOCK TABLES `tour_itinerary_items` WRITE;
/*!40000 ALTER TABLE `tour_itinerary_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `tour_itinerary_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tours`
--

DROP TABLE IF EXISTS `tours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tours` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(160) NOT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `category` enum('half-day','full-day','sunrise') NOT NULL DEFAULT 'half-day',
  `duration` varchar(60) DEFAULT NULL,
  `difficulty` varchar(60) DEFAULT NULL,
  `max_guests` varchar(60) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_lkr` decimal(10,2) DEFAULT NULL,
  `price_usd` decimal(10,2) DEFAULT NULL,
  `highlights` text DEFAULT NULL,
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  `is_must_do` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tours`
--

LOCK TABLES `tours` WRITE;
/*!40000 ALTER TABLE `tours` DISABLE KEYS */;
INSERT INTO `tours` VALUES
(4,'Panama Nature & Village Trail','Discover the peaceful village charm of Panama.','Experience the calm beauty of Panama with a relaxing village and nature tour designed for guests who love authentic local surroundings. This tour offers peaceful landscapes, village roads, coastal views, birdwatching spots, and a closer look at the simple lifestyle around We Trail Villa.','half-day','3 - 4 Hours','Easy','',0.00,0.00,NULL,'[\"Panama village sightseeing\",\"Nature and lagoon views\",\"Birdwatching experience\",\"Peaceful coastal surroundings\",\"Local lifestyle exploration\",\"Photography opportunities\",\"Short and relaxing travel route\"]',0,0,1,0,'assets/images/tours/tour_1780852472_c84f1ebb.jpg','2026-06-07 22:44:32','2026-06-07 22:50:14'),
(5,'Arugam Bay Beach & Surf Escape','Feel the coastal energy of Arugam Bay.','Enjoy a refreshing beach tour to the famous Arugam Bay, one of Sri Lanka’s most loved east coast destinations. This tour is perfect for guests who want beach relaxation, surf culture, ocean views, seafood cafés, sunset moments, and a lively coastal atmosphere close to the villa.','full-day','6 - 7 Hours','Easy','',0.00,0.00,NULL,'[\"Visit Arugam Bay Beach\",\"Explore surfing spots\",\"Relax by the ocean\",\"Enjoy beach caf\\u00e9s\",\"Seafood dining experience\",\"Sunset photography\",\"Coastal sightseeing\"]',1,0,1,0,'assets/images/tours/tour_1780852654_fea330d0.jpg','2026-06-07 22:45:37','2026-06-07 22:50:26'),
(6,'Kudumbigala Sunrise Heritage Tour','Begin your day above ancient forest views.','Start the morning with a peaceful journey to Kudumbigala, an ancient rock monastery surrounded by forest and wildlife scenery. This tour combines history, nature, spirituality, and adventure, offering guests a memorable sunrise experience with beautiful views from the rocky landscape.','sunrise','4 - 5 Hours','Moderate','',0.00,0.00,NULL,'[\"Early morning departure\",\"Kudumbigala ancient monastery visit\",\"Sunrise viewpoint experience\",\"Rock climbing and nature walk\",\"Meditation caves and ruins\",\"Forest landscape views\",\"Peaceful spiritual atmosphere\"]',0,1,1,3,'assets/images/tours/tour_1780852595_60ca28a7.jpg','2026-06-07 22:46:35','2026-06-07 22:50:23');
/*!40000 ALTER TABLE `tours` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `villa_pricing`
--

DROP TABLE IF EXISTS `villa_pricing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `villa_pricing` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(80) NOT NULL,
  `days` varchar(120) NOT NULL,
  `price_lkr` decimal(10,2) NOT NULL,
  `price_usd` decimal(10,2) NOT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `features` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `villa_pricing`
--

LOCK TABLES `villa_pricing` WRITE;
/*!40000 ALTER TABLE `villa_pricing` DISABLE KEYS */;
INSERT INTO `villa_pricing` VALUES
(1,'Label 01','Days / Period',27927.00,87.00,0,'[\"A\\/C bedroom\",\"Wi-Fi free\",\"Privet Pool\",\"Breakfast free\",\"Hot water\",\"Complete privet Cabana\",\"free parking\",\"Main road to Cabana transport free\",\"Smart Tv\"]',0,'2026-06-07 21:21:48'),
(3,'GOLD PACKAGE','Days / Period',45000.00,150.00,1,'[\"A\\/C bedroom\",\"Wi-Fi free\",\"Privet Pool\",\"Breakfast free\",\"Hot water\",\"Complete privet Cabana\",\"free parking\",\"Main road to Cabana transport free\",\"Smart Tv\",\"4x4 Journey\",\"Beach Tour\",\"Airport Pickup or drop\",\"24x7 Meals\",\"Liquor\"]',1,'2026-06-07 21:21:46'),
(4,'Mini Package','Days / Period',15000.00,50.00,0,'[\"A\\/C bedroom\",\"Wi-Fi free\",\"Privet Pool\",\"Breakfast free\",\"Hot water\",\"Complete privet Cabana\",\"free parking\"]',3,'2026-06-07 21:22:43');
/*!40000 ALTER TABLE `villa_pricing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'wetrail'
--

--
-- Dumping routines for database 'wetrail'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-13  1:31:41
