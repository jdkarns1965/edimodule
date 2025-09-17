-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: edi_processing
-- ------------------------------------------------------
-- Server version	8.0.43-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `edi_processing`
--

/*!40000 DROP DATABASE IF EXISTS `edi_processing`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `edi_processing` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `edi_processing`;

--
-- Table structure for table `delivery_schedules`
--

DROP TABLE IF EXISTS `delivery_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `po_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `release_number` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `po_line` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `line_number` int DEFAULT '1',
  `supplier_item` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_item` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_description` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity_ordered` int NOT NULL,
  `quantity_received` int DEFAULT '0',
  `quantity_shipped` int DEFAULT '0',
  `promised_date` date NOT NULL,
  `need_by_date` date NOT NULL,
  `ship_to_location_id` int DEFAULT NULL,
  `location_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_to_description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'EACH',
  `unit_price` decimal(10,4) DEFAULT NULL,
  `organization` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'NIFCO',
  `supplier` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'GREENFIELD PRECISION PLASTICS LLC',
  `status` enum('active','shipped','received','cancelled','closed') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `edi_transaction_id` int DEFAULT NULL,
  `erp_po_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_po_number` (`po_number`),
  KEY `idx_partner_po` (`partner_id`,`po_number`),
  KEY `idx_supplier_item` (`supplier_item`),
  KEY `idx_promised_date` (`promised_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `delivery_schedules_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `trading_partners` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_schedules`
--

LOCK TABLES `delivery_schedules` WRITE;
/*!40000 ALTER TABLE `delivery_schedules` DISABLE KEYS */;
INSERT INTO `delivery_schedules` VALUES (1,1,'1067055','135','1067055-135',1,'27505','27505','COVER, RR CNR RADAR L',600,0,0,'2025-10-13','2025-10-13',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(2,1,'1067055','135','1067055-135',1,'27503','27503','COVER, RR CNR RADAR R',600,0,0,'2025-10-13','2025-10-13',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(3,1,'1067045','237','1067045-237',1,'23466','23466','PROTECTOR, RR DOOR PANEL, RH',3840,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(4,1,'1067045','237','1067045-237',1,'23467','23467','PROTECTOR, RR DOOR PANEL, LH',3840,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(5,1,'1067045','237','1067045-237',1,'22574','22574','HOLDER, DOOR LOCK CONTROL KNOB',2000,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(6,1,'1067045','237','1067045-237',1,'28204','28204','COVER, FORWARD RECOGNITION',3630,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(7,1,'1067045','237','1067045-237',1,'25146','25146','CLAMP, FUEL TUBE, NO.1',3200,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(8,1,'1067045','237','1067045-237',1,'20638','20638','CUSHION, FUEL TANK ASSY',18000,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(9,1,'1067045','237','1067045-237',1,'27976','27976','COVER, OUTER MIRROR INSTAL HOLR,RH',2592,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(10,1,'1067045','237','1067045-237',1,'26833','26833','SEAL, DOOR DUST PROOF',30000,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:53','2025-09-17 17:46:50'),(11,1,'1067045','237','1067045-237',1,'27840','27840','CLAMP, FUEL TUBE, NO.1',900,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(12,1,'1067045','237','1067045-237',1,'26792','26792','COVER, BACK DOOR LOCK',1440,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(13,1,'1067045','237','1067045-237',1,'27977','27977','COVER, OUTER MIRROR INSTAL HOLR, LH',2592,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(14,1,'1067045','237','1067045-237',1,'24319','24319','RETAINER, FUEL FILLER OPENING LID LOCK',4000,0,0,'2025-10-13','2025-10-13',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(15,1,'1067055','135','1067055-135',1,'27394','27394','PANEL INST DR',960,0,0,'2025-10-10','2025-10-10',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(16,1,'1067055','135','1067055-135',1,'28247','28247','COVER, RR CNR RADAR R',120,0,0,'2025-10-06','2025-10-06',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(17,1,'1067055','135','1067055-135',1,'27505','27505','COVER, RR CNR RADAR L',840,0,0,'2025-10-06','2025-10-06',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(18,1,'1067055','135','1067055-135',1,'27503','27503','COVER, RR CNR RADAR R',840,0,0,'2025-10-06','2025-10-06',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(19,1,'1067055','135','1067055-135',1,'28248','28248','COVER, RR CNR RADAR L',120,0,0,'2025-10-06','2025-10-06',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(20,1,'1067045','236','1067045-236',1,'20638','20638','CUSHION, FUEL TANK ASSY',21600,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(21,1,'1067045','236','1067045-236',1,'26833','26833','SEAL, DOOR DUST PROOF',20000,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(22,1,'1067045','236','1067045-236',1,'22574','22574','HOLDER, DOOR LOCK CONTROL KNOB',2000,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(23,1,'1067045','236','1067045-236',1,'24319','24319','RETAINER, FUEL FILLER OPENING LID LOCK',2000,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(24,1,'1067045','236','1067045-236',1,'23466','23466','PROTECTOR, RR DOOR PANEL, RH',4480,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(25,1,'1067045','236','1067045-236',1,'25146','25146','CLAMP, FUEL TUBE, NO.1',3200,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(26,1,'1067045','236','1067045-236',1,'23467','23467','PROTECTOR, RR DOOR PANEL, LH',4480,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(27,1,'1067045','236','1067045-236',1,'27976','27976','COVER, OUTER MIRROR INSTAL HOLR,RH',1944,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(28,1,'1067045','236','1067045-236',1,'28204','28204','COVER, FORWARD RECOGNITION',4620,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(29,1,'1067045','236','1067045-236',1,'26792','26792','COVER, BACK DOOR LOCK',1440,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(30,1,'1067045','236','1067045-236',1,'27977','27977','COVER, OUTER MIRROR INSTAL HOLR, LH',1944,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(31,1,'1067045','236','1067045-236',1,'27840','27840','CLAMP, FUEL TUBE, NO.1',900,0,0,'2025-10-06','2025-10-06',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(32,1,'1067055','135','1067055-135',1,'27394','27394','PANEL INST DR',960,0,0,'2025-10-03','2025-10-03',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(33,1,'1067045','235','1067045-235',1,'27977','27977','COVER, OUTER MIRROR INSTAL HOLR, LH',1944,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(34,1,'1067045','235','1067045-235',1,'27976','27976','COVER, OUTER MIRROR INSTAL HOLR,RH',1944,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(35,1,'1067045','235','1067045-235',1,'20638','20638','CUSHION, FUEL TANK ASSY',18000,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(36,1,'1067045','235','1067045-235',1,'26833','26833','SEAL, DOOR DUST PROOF',20000,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(37,1,'1067045','235','1067045-235',1,'22574','22574','HOLDER, DOOR LOCK CONTROL KNOB',2000,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(38,1,'1067045','235','1067045-235',1,'24319','24319','RETAINER, FUEL FILLER OPENING LID LOCK',2000,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(39,1,'1067045','235','1067045-235',1,'25146','25146','CLAMP, FUEL TUBE, NO.1',2400,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(40,1,'1067045','235','1067045-235',1,'23467','23467','PROTECTOR, RR DOOR PANEL, LH',3680,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(41,1,'1067045','235','1067045-235',1,'23466','23466','PROTECTOR, RR DOOR PANEL, RH',3680,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(42,1,'1067045','235','1067045-235',1,'26792','26792','COVER, BACK DOOR LOCK',1440,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(43,1,'1067045','235','1067045-235',1,'27840','27840','CLAMP, FUEL TUBE, NO.1',900,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(44,1,'1067045','235','1067045-235',1,'28204','28204','COVER, FORWARD RECOGNITION',3564,0,0,'2025-09-29','2025-09-29',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(45,1,'1067055','134','1067055-134',1,'27394','27394','PANEL INST DR',960,0,0,'2025-09-26','2025-09-26',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(46,1,'1067045','233','1067045-233',1,'28204','28204','COVER, FORWARD RECOGNITION',3762,0,3762,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(47,1,'1067045','233','1067045-233',1,'23466','23466','PROTECTOR, RR DOOR PANEL, RH',4000,0,4000,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(48,1,'1067045','233','1067045-233',1,'26792','26792','COVER, BACK DOOR LOCK',1440,0,1440,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(49,1,'1067045','233','1067045-233',1,'20638','20638','CUSHION, FUEL TANK ASSY',21600,0,21600,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(50,1,'1067045','233','1067045-233',1,'27976','27976','COVER, OUTER MIRROR INSTAL HOLR,RH',2592,0,2592,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(51,1,'1067045','233','1067045-233',1,'27840','27840','CLAMP, FUEL TUBE, NO.1',900,0,900,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(52,1,'1067045','233','1067045-233',1,'27977','27977','COVER, OUTER MIRROR INSTAL HOLR, LH',2592,0,2592,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(53,1,'1067045','233','1067045-233',1,'23467','23467','PROTECTOR, RR DOOR PANEL, LH',4000,0,4000,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(54,1,'1067045','233','1067045-233',1,'25146','25146','CLAMP, FUEL TUBE, NO.1',3200,0,3200,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(55,1,'1067045','233','1067045-233',1,'24319','24319','RETAINER, FUEL FILLER OPENING LID LOCK',2000,0,2000,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(56,1,'1067045','233','1067045-233',1,'26833','26833','SEAL, DOOR DUST PROOF',21000,0,21000,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(57,1,'1067045','233','1067045-233',1,'22574','22574','HOLDER, DOOR LOCK CONTROL KNOB',1600,0,1600,'2025-09-22','2025-09-22',NULL,NULL,'SHELBYVILLE KENTUCKY','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:54:25'),(58,1,'1067055','134','1067055-134',1,'27503','27503','COVER, RR CNR RADAR R',960,0,0,'2025-09-19','2025-09-19',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(59,1,'1067055','134','1067055-134',1,'28247','28247','COVER, RR CNR RADAR R',120,0,0,'2025-09-19','2025-09-19',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(60,1,'1067055','134','1067055-134',1,'27505','27505','COVER, RR CNR RADAR L',960,0,0,'2025-09-19','2025-09-19',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(61,1,'1067055','134','1067055-134',1,'28248','28248','COVER, RR CNR RADAR L',120,0,0,'2025-09-19','2025-09-19',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50'),(62,1,'1067055','134','1067055-134',1,'27394','27394','PANEL INST DR',960,0,0,'2025-09-19','2025-09-19',NULL,NULL,'CWH','EACH',NULL,'NIFCO','GREENFIELD PRECISION PLASTICS LLC','active','normal',NULL,NULL,NULL,'2025-09-17 17:44:54','2025-09-17 17:46:50');
/*!40000 ALTER TABLE `delivery_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `edi_transactions`
--

DROP TABLE IF EXISTS `edi_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `edi_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `transaction_type` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('inbound','outbound') COLLATE utf8mb4_unicode_ci NOT NULL,
  `control_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `raw_content` longtext COLLATE utf8mb4_unicode_ci,
  `parsed_content` json DEFAULT NULL,
  `status` enum('received','processing','processed','error','archived') COLLATE utf8mb4_unicode_ci DEFAULT 'received',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `processing_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_partner_type` (`partner_id`,`transaction_type`),
  KEY `idx_control_number` (`control_number`),
  KEY `idx_status_date` (`status`,`created_at`),
  KEY `idx_filename` (`filename`),
  CONSTRAINT `edi_transactions_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `trading_partners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `edi_transactions`
--

LOCK TABLES `edi_transactions` WRITE;
/*!40000 ALTER TABLE `edi_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `edi_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `part_location_mapping`
--

DROP TABLE IF EXISTS `part_location_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `part_location_mapping` (
  `id` int NOT NULL AUTO_INCREMENT,
  `part_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_part_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qpc` int DEFAULT NULL COMMENT 'Location-specific QPC override',
  `active` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_part_location` (`part_number`,`location_code`),
  KEY `idx_part_number` (`part_number`),
  KEY `idx_location_code` (`location_code`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `part_location_mapping`
--

LOCK TABLES `part_location_mapping` WRITE;
/*!40000 ALTER TABLE `part_location_mapping` DISABLE KEYS */;
/*!40000 ALTER TABLE `part_location_mapping` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `part_master`
--

DROP TABLE IF EXISTS `part_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `part_master` (
  `id` int NOT NULL AUTO_INCREMENT,
  `part_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_part_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qpc` int NOT NULL DEFAULT '1' COMMENT 'Quantity Per Container - for container calculations',
  `uom` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'EACH',
  `weight` decimal(10,4) DEFAULT NULL COMMENT 'Part weight for shipping calculations',
  `dimensions` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Length x Width x Height',
  `material` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_family` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `auto_detected` tinyint(1) DEFAULT '0' COMMENT 'TRUE if part was auto-detected from EDI processing',
  `first_detected_date` datetime DEFAULT NULL COMMENT 'Date when part was first seen in EDI',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `part_number` (`part_number`),
  KEY `idx_part_number` (`part_number`),
  KEY `idx_customer_part` (`customer_part_number`),
  KEY `idx_active` (`active`),
  KEY `idx_auto_detected` (`auto_detected`),
  KEY `idx_product_family` (`product_family`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `part_master`
--

LOCK TABLES `part_master` WRITE;
/*!40000 ALTER TABLE `part_master` DISABLE KEYS */;
/*!40000 ALTER TABLE `part_master` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ship_to_locations`
--

DROP TABLE IF EXISTS `ship_to_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ship_to_locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `partner_id` int NOT NULL,
  `location_description` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'US',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_location_code` (`location_code`),
  KEY `idx_partner_location` (`partner_id`,`location_code`),
  CONSTRAINT `ship_to_locations_ibfk_1` FOREIGN KEY (`partner_id`) REFERENCES `trading_partners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ship_to_locations`
--

LOCK TABLES `ship_to_locations` WRITE;
/*!40000 ALTER TABLE `ship_to_locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `ship_to_locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipment_items`
--

DROP TABLE IF EXISTS `shipment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipment_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `delivery_schedule_id` int DEFAULT NULL,
  `supplier_item` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_item` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_description` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `po_line` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity_shipped` int NOT NULL,
  `container_count` int DEFAULT '1',
  `lot_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uom` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'EACH',
  `unit_price` decimal(10,4) DEFAULT NULL,
  `line_total` decimal(12,2) DEFAULT NULL,
  `package_number` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipment_item` (`shipment_id`,`supplier_item`),
  CONSTRAINT `shipment_items_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipment_items`
--

LOCK TABLES `shipment_items` WRITE;
/*!40000 ALTER TABLE `shipment_items` DISABLE KEYS */;
INSERT INTO `shipment_items` VALUES (1,2,49,'20638','20638','CUSHION, FUEL TANK ASSY','1067045-233',21600,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(2,2,57,'22574','22574','HOLDER, DOOR LOCK CONTROL KNOB','1067045-233',1600,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(3,2,47,'23466','23466','PROTECTOR, RR DOOR PANEL, RH','1067045-233',4000,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(4,2,53,'23467','23467','PROTECTOR, RR DOOR PANEL, LH','1067045-233',4000,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(5,2,55,'24319','24319','RETAINER, FUEL FILLER OPENING LID LOCK','1067045-233',2000,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(6,2,54,'25146','25146','CLAMP, FUEL TUBE, NO.1','1067045-233',3200,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(7,2,48,'26792','26792','COVER, BACK DOOR LOCK','1067045-233',1440,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(8,2,56,'26833','26833','SEAL, DOOR DUST PROOF','1067045-233',21000,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(9,2,51,'27840','27840','CLAMP, FUEL TUBE, NO.1','1067045-233',900,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(10,2,50,'27976','27976','COVER, OUTER MIRROR INSTAL HOLR,RH','1067045-233',2592,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(11,2,52,'27977','27977','COVER, OUTER MIRROR INSTAL HOLR, LH','1067045-233',2592,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25'),(12,2,46,'28204','28204','COVER, FORWARD RECOGNITION','1067045-233',3762,1,'','EACH',NULL,NULL,1,'2025-09-17 17:54:25');
/*!40000 ALTER TABLE `shipment_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipments`
--

DROP TABLE IF EXISTS `shipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_id` int NOT NULL,
  `po_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_scac` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bol_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ship_date` date NOT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `actual_delivery` date DEFAULT NULL,
  `ship_from_address` text COLLATE utf8mb4_unicode_ci,
  `ship_to_address` text COLLATE utf8mb4_unicode_ci,
  `ship_to_location_id` int DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `total_weight` decimal(10,2) DEFAULT NULL,
  `weight_uom` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'LB',
  `package_count` int DEFAULT '1',
  `total_packages` int DEFAULT NULL,
  `package_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'CTN',
  `status` enum('planned','shipped','delivered','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'planned',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edi_transaction_id` int DEFAULT NULL,
  `edi_856_sent` tinyint(1) DEFAULT '0',
  `edi_856_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `tracking_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actual_ship_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipment_number` (`shipment_number`),
  KEY `idx_shipment_number` (`shipment_number`),
  KEY `idx_partner_po` (`partner_id`,`po_number`),
  KEY `idx_ship_date` (`ship_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipments`
--

LOCK TABLES `shipments` WRITE;
/*!40000 ALTER TABLE `shipments` DISABLE KEYS */;
INSERT INTO `shipments` VALUES (2,'SH20250917-528',1,'1067045','King of Freight','RYDD',NULL,'1067045','2025-09-17',NULL,NULL,NULL,NULL,NULL,NULL,6000.00,'LB',1,22,'CTN','planned','Web User',NULL,0,NULL,NULL,NULL,NULL,'2025-09-17 17:54:25','2025-09-17 17:54:25');
/*!40000 ALTER TABLE `shipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `trading_partners`
--

DROP TABLE IF EXISTS `trading_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trading_partners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `partner_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `edi_id` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `edi_standard` enum('X12','EDIFACT','TRADACOMS','CUSTOM') COLLATE utf8mb4_unicode_ci DEFAULT 'X12',
  `edi_version` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '004010',
  `date_format` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'MM/DD/YYYY',
  `po_number_format` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'NNNNNN-NNN',
  `default_organization` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'NIFCO',
  `default_supplier` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'GREENFIELD PRECISION PLASTICS LLC',
  `default_uom` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'EACH',
  `field_mappings` json DEFAULT NULL COMMENT 'Customer-specific field name mappings',
  `business_rules` json DEFAULT NULL COMMENT 'Customer-specific business rules and calculations',
  `communication_config` json DEFAULT NULL COMMENT 'SFTP, AS2, API connection details',
  `template_config` json DEFAULT NULL COMMENT 'Import/Export template configurations',
  `connection_type` enum('AS2','SFTP','FTP','VAN','API') COLLATE utf8mb4_unicode_ci DEFAULT 'SFTP',
  `status` enum('active','inactive','testing') COLLATE utf8mb4_unicode_ci DEFAULT 'testing',
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `partner_code` (`partner_code`),
  KEY `idx_partner_code` (`partner_code`),
  KEY `idx_edi_id` (`edi_id`),
  KEY `idx_edi_standard` (`edi_standard`),
  KEY `idx_status_standard` (`status`,`edi_standard`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `trading_partners`
--

LOCK TABLES `trading_partners` WRITE;
/*!40000 ALTER TABLE `trading_partners` DISABLE KEYS */;
INSERT INTO `trading_partners` VALUES (1,'NIFCO','Nifco Inc.','6148363808','X12','004010','MM/DD/YYYY','NNNNNN-NNN','NIFCO','GREENFIELD PRECISION PLASTICS LLC','EACH','{\"uom_field\": \"UOM\", \"ship_to_field\": \"Ship-To Location\", \"quantity_field\": \"Quantity Ordered\", \"supplier_field\": \"Supplier\", \"po_number_field\": \"PO Number\", \"description_field\": \"Item Description\", \"organization_field\": \"Organization\", \"customer_item_field\": \"Item Number\", \"promised_date_field\": \"Promised Date\", \"supplier_item_field\": \"Supplier Item\"}','{\"po_parsing_rule\": \"split_on_dash\", \"date_parsing_formats\": [\"M/D/YYYY\", \"MM/DD/YYYY\", \"M/D/YY\"], \"location_mapping_type\": \"description_based\", \"default_lead_time_days\": 0, \"container_calculation_rule\": \"round_up\"}','{\"host\": \"edi.nifco.com\", \"port\": 22, \"protocol\": \"SFTP\", \"username\": \"greenfield_plastics_edi\", \"inbox_path\": \"/inbox\", \"outbox_path\": \"/outbox\", \"retry_attempts\": 3, \"timeout_seconds\": 30, \"file_naming_convention\": \"EDI862_{YYYYMMDD}_{HHMMSS}.edi\"}','{\"optional_headers\": [\"Quantity Received\", \"Need-By Date\", \"UOM\", \"Organization\", \"Supplier\", \"Item Number\"], \"required_headers\": [\"PO Number\", \"Supplier Item\", \"Item Description\", \"Quantity Ordered\", \"Promised Date\", \"Ship-To Location\"], \"export_template_name\": \"nifco_856_export.edi\", \"import_template_name\": \"nifco_862_import.csv\"}','SFTP','active','fieldsc@us.nifco.com','2025-09-17 17:40:09','2025-09-17 17:40:09'),(2,'FORD','Ford Motor Company','1234567890','X12','005010','YYYY-MM-DD','NNNNNNNNNN','FORD','GREENFIELD PRECISION PLASTICS LLC','EA','{\"uom_field\": \"Unit_Of_Measure\", \"ship_to_field\": \"Plant_Code\", \"quantity_field\": \"Order_Quantity\", \"supplier_field\": \"Vendor\", \"po_number_field\": \"Purchase_Order\", \"description_field\": \"Part_Description\", \"organization_field\": \"Customer\", \"customer_item_field\": \"Ford_Part_Number\", \"promised_date_field\": \"Delivery_Date\", \"supplier_item_field\": \"Part_Number\"}','{\"po_parsing_rule\": \"no_split\", \"date_parsing_formats\": [\"YYYY-MM-DD\", \"YYYYMMDD\"], \"location_mapping_type\": \"code_based\", \"default_lead_time_days\": 1, \"container_calculation_rule\": \"exact\"}','{\"host\": \"edi.ford.com\", \"protocol\": \"AS2\", \"inbox_path\": \"/ford/inbox\", \"outbox_path\": \"/ford/outbox\", \"as2_identifier\": \"GREENFIELD_EDI\", \"retry_attempts\": 5, \"timeout_seconds\": 60, \"certificate_path\": \"/certificates/ford.p12\", \"file_naming_convention\": \"FORD_{transaction}_{YYYYMMDD}.edi\"}','{\"optional_headers\": [\"Received_Quantity\", \"Required_Date\", \"Unit_Of_Measure\", \"Customer\", \"Vendor\", \"Ford_Part_Number\"], \"required_headers\": [\"Purchase_Order\", \"Part_Number\", \"Part_Description\", \"Order_Quantity\", \"Delivery_Date\", \"Plant_Code\"], \"export_template_name\": \"ford_856_export.edi\", \"import_template_name\": \"ford_830_import.csv\"}','AS2','testing',NULL,'2025-09-17 17:40:09','2025-09-17 17:40:09');
/*!40000 ALTER TABLE `trading_partners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'edi_processing'
--

--
-- Dumping routines for database 'edi_processing'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-17 15:12:35
