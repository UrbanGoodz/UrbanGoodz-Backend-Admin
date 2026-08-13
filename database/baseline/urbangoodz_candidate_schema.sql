-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: urbakkej_urbangoodzdelivery
-- ------------------------------------------------------
-- Server version	8.4.3

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
-- Table structure for table `account_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `from_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_id` bigint NOT NULL,
  `current_balance` decimal(24,2) NOT NULL,
  `amount` decimal(24,2) NOT NULL,
  `method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'collected',
  `created_by` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `add_ons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `add_ons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(24,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `store_id` bigint unsigned NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `addon_category_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `addon_categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addon_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `addon_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addon_settings` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `live_values` longtext COLLATE utf8mb4_unicode_ci,
  `test_values` longtext COLLATE utf8mb4_unicode_ci,
  `settings_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'live',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  PRIMARY KEY (`id`),
  KEY `payment_settings_id_index` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_features`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_features` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_promotional_banners`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_promotional_banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modules` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_special_criterias`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_special_criterias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_testimonials`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_testimonials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review` text COLLATE utf8mb4_unicode_ci,
  `reviewer_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_wallets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned NOT NULL,
  `total_commission_earning` decimal(24,2) NOT NULL DEFAULT '0.00',
  `digital_received` decimal(24,2) NOT NULL DEFAULT '0.00',
  `manual_received` decimal(24,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `delivery_charge` decimal(24,3) NOT NULL DEFAULT '0.000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admins`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `f_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role_id` bigint unsigned NOT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  `is_logged_in` tinyint(1) NOT NULL DEFAULT '1',
  `login_remember_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `advertisements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `advertisements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  `module_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `add_type` enum('video_promotion','store_promotion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'store_promotion',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `pause_note` text COLLATE utf8mb4_unicode_ci,
  `cancellation_note` text COLLATE utf8mb4_unicode_ci,
  `cover_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` int DEFAULT NULL,
  `is_rating_active` tinyint(1) NOT NULL DEFAULT '0',
  `is_review_active` tinyint(1) NOT NULL DEFAULT '0',
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_updated` tinyint(1) NOT NULL DEFAULT '0',
  `created_by_id` bigint unsigned NOT NULL,
  `created_by_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','running','approved','expired','denied','paused') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_action_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_action_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recommendation_id` bigint unsigned DEFAULT NULL,
  `action_taken` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `affected_user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `affected_user_id` bigint unsigned DEFAULT NULL,
  `before_value` text COLLATE utf8mb4_unicode_ci,
  `after_value` text COLLATE utf8mb4_unicode_ci,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `automation_mode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `rollback_available` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_action_logs_recommendation_id_foreign` (`recommendation_id`),
  KEY `ai_action_logs_approved_by_foreign` (`approved_by`),
  KEY `ai_action_logs_affected_user_type_affected_user_id_index` (`affected_user_type`,`affected_user_id`),
  KEY `ai_action_logs_action_taken_index` (`action_taken`),
  KEY `ai_action_logs_module_index` (`module`),
  KEY `ai_action_logs_created_at_index` (`created_at`),
  CONSTRAINT `ai_action_logs_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ai_action_logs_recommendation_id_foreign` FOREIGN KEY (`recommendation_id`) REFERENCES `ai_copilot_recommendations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_copilot_recommendations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_copilot_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recommendation_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recommendation_subtype` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relatable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relatable_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `package_id` bigint unsigned DEFAULT NULL,
  `route_id` bigint unsigned DEFAULT NULL,
  `request_id` bigint unsigned DEFAULT NULL,
  `suggested_action` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_copilot_recommendations_relatable_type_relatable_id_index` (`relatable_type`,`relatable_id`),
  KEY `ai_copilot_recommendations_reviewed_by_foreign` (`reviewed_by`),
  KEY `ai_copilot_recommendations_recommendation_type_index` (`recommendation_type`),
  KEY `ai_copilot_recommendations_status_index` (`status`),
  KEY `ai_copilot_recommendations_created_at_index` (`created_at`),
  KEY `ai_copilot_recommendations_order_id_index` (`order_id`),
  KEY `ai_copilot_recommendations_package_id_index` (`package_id`),
  KEY `ai_copilot_recommendations_route_id_index` (`route_id`),
  KEY `ai_copilot_recommendations_request_id_index` (`request_id`),
  CONSTRAINT `ai_copilot_recommendations_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_copilot_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_copilot_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_copilot_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_module_automation_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_module_automation_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `automation_mode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_confidence_score` decimal(5,2) NOT NULL DEFAULT '0.70',
  `max_auto_action_amount` decimal(12,2) DEFAULT NULL,
  `allowed_zones` json DEFAULT NULL,
  `allowed_categories` json DEFAULT NULL,
  `max_risk_level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `escalation_rules` json DEFAULT NULL,
  `approval_required_rules` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_module_automation_settings_module_unique` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_risk_rules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_risk_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_operator` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risk_level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '1',
  `escalation_action` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `allergies`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `allergies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `allergy` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `allergy_item`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `allergy_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `allergy_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `allergy_item_campaign`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `allergy_item_campaign` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_campaign_id` bigint unsigned NOT NULL,
  `allergy_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `analytic_scripts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `analytic_scripts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `script_id` text COLLATE utf8mb4_unicode_ci,
  `script` longtext COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attributes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attributes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `automated_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automated_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question_for` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `banners`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `data` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `zone_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `default_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`id`),
  KEY `banners_module_id_foreign` (`module_id`),
  CONSTRAINT `banners_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `brands`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `business_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaign_store`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_store` (
  `campaign_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `campaign_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaigns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `admin_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `module_id` bigint unsigned NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaigns_module_id_foreign` (`module_id`),
  CONSTRAINT `campaigns_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `carts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `carts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `is_guest` tinyint(1) NOT NULL DEFAULT '0',
  `add_on_ids` text COLLATE utf8mb4_unicode_ci,
  `add_on_qtys` text COLLATE utf8mb4_unicode_ci,
  `item_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` double(24,3) NOT NULL,
  `quantity` int NOT NULL,
  `variation` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cash_back_histories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_back_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cash_back_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `cashback_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculated_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `cashback_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `min_purchase` double(23,3) NOT NULL DEFAULT '0.000',
  `max_discount` double(23,3) NOT NULL DEFAULT '0.000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `trip_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cash_backs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_backs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '["all"]',
  `cashback_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `same_user_limit` int NOT NULL DEFAULT '1',
  `total_used` int NOT NULL DEFAULT '0',
  `cashback_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `min_purchase` double(23,3) NOT NULL DEFAULT '0.000',
  `max_discount` double(23,3) NOT NULL DEFAULT '0.000',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_rental` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'def.png',
  `parent_id` int NOT NULL,
  `position` int NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '0',
  `module_id` bigint unsigned NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `categories_module_id_foreign` (`module_id`),
  KEY `categories_parent_id_index` (`parent_id`),
  KEY `categories_name_index` (`name`),
  CONSTRAINT `categories_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `common_conditions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `common_conditions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contacts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `seen` tinyint NOT NULL DEFAULT '0',
  `feedback` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `reply` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint unsigned NOT NULL,
  `sender_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receiver_id` bigint unsigned NOT NULL,
  `receiver_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_message_id` bigint unsigned DEFAULT NULL,
  `last_message_time` timestamp NULL DEFAULT NULL,
  `unread_message_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `coupons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expire_date` date DEFAULT NULL,
  `min_purchase` decimal(24,2) NOT NULL DEFAULT '0.00',
  `max_discount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `discount_type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `coupon_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `limit` int DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `data` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_uses` bigint DEFAULT '0',
  `module_id` bigint unsigned NOT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `customer_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '["all"]',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_code_unique` (`code`),
  KEY `coupons_module_id_foreign` (`module_id`),
  CONSTRAINT `coupons_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `currencies`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_symbol` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exchange_rate` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customer_addresses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `address_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `contact_person_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `zone_id` bigint unsigned NOT NULL,
  `floor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `road` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `house` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `d_m_reviews`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `d_m_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_man_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `comment` mediumtext COLLATE utf8mb4_unicode_ci,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `d_m_vehicles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `d_m_vehicles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `starting_coverage_area` double NOT NULL DEFAULT '0',
  `maximum_coverage_area` double NOT NULL DEFAULT '0',
  `extra_charges` double NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_delivery` tinyint(1) NOT NULL DEFAULT '1',
  `is_ride` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `data_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `delivery_histories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `delivery_man_wallets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_man_wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_man_id` bigint unsigned NOT NULL,
  `collected_cash` decimal(24,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `total_earning` decimal(24,2) NOT NULL DEFAULT '0.00',
  `total_withdrawn` decimal(24,2) NOT NULL DEFAULT '0.00',
  `pending_withdraw` decimal(24,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `delivery_men`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_men` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `f_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_image` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fcm_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `earning` tinyint(1) DEFAULT NULL,
  `current_orders` int NOT NULL DEFAULT '0',
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'zone_wise',
  `store_id` bigint DEFAULT NULL,
  `application_status` enum('approved','denied','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `order_count` int unsigned NOT NULL DEFAULT '0',
  `assigned_order_count` int unsigned NOT NULL DEFAULT '0',
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `loyalty_point` double DEFAULT '0',
  `ref_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_by` bigint unsigned DEFAULT NULL,
  `is_delivery` tinyint(1) NOT NULL DEFAULT '1',
  `is_ride` tinyint(1) NOT NULL DEFAULT '0',
  `vehicle_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_capacity_notes` text COLLATE utf8mb4_unicode_ci,
  `max_package_count` int unsigned DEFAULT NULL,
  `max_weight_lbs` decimal(10,2) DEFAULT NULL,
  `has_cargo_space` tinyint(1) NOT NULL DEFAULT '0',
  `has_cooler_bag` tinyint(1) NOT NULL DEFAULT '0',
  `has_medical_courier_training` tinyint(1) NOT NULL DEFAULT '0',
  `has_liftgate` tinyint(1) NOT NULL DEFAULT '0',
  `preferred_zones` json DEFAULT NULL,
  `preferred_work_types` json DEFAULT NULL,
  `capability_tags` json DEFAULT NULL,
  `availability_preference` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `available_for_business_courier` tinyint(1) NOT NULL DEFAULT '0',
  `available_for_package_routes` tinyint(1) NOT NULL DEFAULT '0',
  `available_for_order_anywhere` tinyint(1) NOT NULL DEFAULT '0',
  `available_for_medical_courier` tinyint(1) NOT NULL DEFAULT '0',
  `has_trailer` tinyint(1) NOT NULL DEFAULT '0',
  `trailer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_length_feet` decimal(6,2) DEFAULT NULL,
  `trailer_width_feet` decimal(6,2) DEFAULT NULL,
  `trailer_capacity_lbs` decimal(10,2) DEFAULT NULL,
  `hitch_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_plate_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_registration_expiration` date DEFAULT NULL,
  `trailer_insurance_expiration` date DEFAULT NULL,
  `cdl_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdl_class` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdl_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dot_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mc_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_pallet_jack` tinyint(1) NOT NULL DEFAULT '0',
  `has_hazmat` tinyint(1) NOT NULL DEFAULT '0',
  `has_cargo_insurance` tinyint(1) NOT NULL DEFAULT '0',
  `cargo_insurance_expiration` date DEFAULT NULL,
  `max_payload_lbs` decimal(10,2) DEFAULT NULL,
  `cargo_length_inches` decimal(8,2) DEFAULT NULL,
  `cargo_width_inches` decimal(8,2) DEFAULT NULL,
  `cargo_height_inches` decimal(8,2) DEFAULT NULL,
  `vehicle_photos` json DEFAULT NULL,
  `registration_expiration` date DEFAULT NULL,
  `insurance_expiration` date DEFAULT NULL,
  `inspection_expiration` date DEFAULT NULL,
  `vehicle_make` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_year` int DEFAULT NULL,
  `vehicle_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_vin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_plate` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_vin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_make` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trailer_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdl_state` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cdl_expiration` date DEFAULT NULL,
  `usdot_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_policy` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_carrier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `load_board_eligible` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `delivery_men_phone_unique` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deliveryman_loyalty_point_histories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliveryman_loyalty_point_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_man_id` bigint unsigned NOT NULL,
  `transaction_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `point_conversion_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `point` decimal(24,3) NOT NULL DEFAULT '0.000',
  `converted_amount` decimal(24,3) NOT NULL DEFAULT '0.000',
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deliveryman_loyalty_point_histories_delivery_man_id_index` (`delivery_man_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deliveryman_referral_histories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliveryman_referral_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_man_id` bigint unsigned NOT NULL,
  `referrer_id` bigint unsigned DEFAULT NULL,
  `transaction_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refer_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'referral',
  `amount` decimal(24,3) NOT NULL DEFAULT '0.000',
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deliveryman_referral_histories_transaction_id_unique` (`transaction_id`),
  KEY `deliveryman_referral_histories_delivery_man_id_index` (`delivery_man_id`),
  KEY `deliveryman_referral_histories_referrer_id_index` (`referrer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disbursement_details`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disbursement_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `disbursement_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `disbursement_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `payment_method` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disbursement_withdrawal_methods`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disbursement_withdrawal_methods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `withdrawal_method_id` bigint unsigned NOT NULL,
  `method_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_fields` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `disbursements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `disbursements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `total_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_for` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `discounts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `min_purchase` decimal(24,2) NOT NULL DEFAULT '0.00',
  `max_discount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `discount_type` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `store_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ecommerce_item_details`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ecommerce_item_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned DEFAULT NULL,
  `brand_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `temp_product_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `background_image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `button_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `button_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `footer_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `privacy` tinyint(1) NOT NULL DEFAULT '0',
  `refund` tinyint(1) NOT NULL DEFAULT '0',
  `cancelation` tinyint(1) NOT NULL DEFAULT '0',
  `contact` tinyint(1) NOT NULL DEFAULT '0',
  `facebook` tinyint(1) NOT NULL DEFAULT '0',
  `instagram` tinyint(1) NOT NULL DEFAULT '0',
  `twitter` tinyint(1) NOT NULL DEFAULT '0',
  `linkedin` tinyint(1) NOT NULL DEFAULT '0',
  `pinterest` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `body_2` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_verifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `otp_hit_count` tinyint NOT NULL DEFAULT '0',
  `is_temp_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `temp_block_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employee_roles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modules` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `amount` decimal(23,3) NOT NULL DEFAULT '0.000',
  `order_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `store_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `trip_id` bigint unsigned DEFAULT NULL,
  `ride_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `external_configurations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `external_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `f_a_q_s`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `f_a_q_s` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flash_sale_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sale_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `flash_sale_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `stock` int NOT NULL,
  `sold` int NOT NULL DEFAULT '0',
  `available_stock` int NOT NULL,
  `discount_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount` double(23,3) NOT NULL DEFAULT '0.000',
  `discount_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `price` double(23,3) NOT NULL DEFAULT '0.000',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flash_sales`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flash_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_publish` tinyint(1) NOT NULL DEFAULT '1',
  `admin_discount_percentage` double(24,3) NOT NULL,
  `vendor_discount_percentage` double(24,3) NOT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flutter_special_criterias`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flutter_special_criterias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `generic_names`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `generic_names` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `generic_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fcm_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_campaign_generic_names`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_campaign_generic_names` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_campaign_id` bigint unsigned NOT NULL,
  `generic_name_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_campaign_nutrition`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_campaign_nutrition` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_campaign_id` bigint unsigned NOT NULL,
  `nutrition_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_campaigns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `admin_id` bigint unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variations` text COLLATE utf8mb4_unicode_ci,
  `add_ons` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attributes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `choice_options` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(24,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(24,2) NOT NULL DEFAULT '0.00',
  `tax_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `discount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `discount_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `store_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `veg` tinyint(1) NOT NULL DEFAULT '0',
  `module_id` bigint unsigned NOT NULL,
  `stock` int DEFAULT '0',
  `unit_id` bigint unsigned DEFAULT NULL,
  `food_variations` text COLLATE utf8mb4_unicode_ci,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maximum_cart_quantity` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_campaigns_module_id_foreign` (`module_id`),
  CONSTRAINT `item_campaigns_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_generic_names`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_generic_names` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `generic_name_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_nutrition`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_nutrition` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `nutrition_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_seo_data`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_seo_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned DEFAULT NULL,
  `temp_item_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_seo_data_item_id_index` (`item_id`),
  KEY `item_seo_data_temp_item_id_index` (`temp_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_tag`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_tag` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_link` text COLLATE utf8mb4_unicode_ci,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variations` text COLLATE utf8mb4_unicode_ci,
  `add_ons` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attributes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `choice_options` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(24,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(24,2) NOT NULL DEFAULT '0.00',
  `tax_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `discount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `discount_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `available_time_starts` time DEFAULT NULL,
  `available_time_ends` time DEFAULT NULL,
  `veg` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `store_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `order_count` int NOT NULL DEFAULT '0',
  `avg_rating` double(16,14) NOT NULL DEFAULT '0.00000000000000',
  `rating_count` int NOT NULL DEFAULT '0',
  `rating` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_id` bigint unsigned NOT NULL,
  `stock` int DEFAULT '0',
  `unit_id` bigint unsigned DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `food_variations` text COLLATE utf8mb4_unicode_ci,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recommended` tinyint(1) NOT NULL DEFAULT '0',
  `organic` tinyint(1) NOT NULL DEFAULT '0',
  `maximum_cart_quantity` int DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '1',
  `is_halal` tinyint(1) NOT NULL DEFAULT '0',
  `age_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `age_restricted_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `items_module_id_foreign` (`module_id`),
  KEY `items_category_id_index` (`category_id`),
  KEY `items_store_id_index` (`store_id`),
  KEY `items_name_index` (`name`),
  KEY `items_slug_index` (`slug`),
  KEY `items_price_index` (`price`),
  KEY `items_created_at_index` (`created_at`),
  KEY `items_order_count_index` (`order_count`),
  KEY `items_avg_rating_index` (`avg_rating`),
  CONSTRAINT `items_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `loyalty_point_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_point_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `transaction_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credit` decimal(24,3) NOT NULL DEFAULT '0.000',
  `debit` decimal(24,3) NOT NULL DEFAULT '0.000',
  `balance` decimal(24,3) NOT NULL DEFAULT '0.000',
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `sender_id` bigint unsigned DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `file` text COLLATE utf8mb4_unicode_ci,
  `is_seen` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `module_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `module_wise_banners`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_wise_banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `module_wise_why_chooses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_wise_why_chooses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `module_zone`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_zone` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `zone_id` bigint unsigned NOT NULL,
  `per_km_shipping_charge` double(23,2) DEFAULT NULL,
  `minimum_shipping_charge` double(23,2) DEFAULT NULL,
  `maximum_cod_order_amount` double(23,2) DEFAULT NULL,
  `maximum_shipping_charge` double(23,2) DEFAULT NULL,
  `delivery_charge_type` enum('fixed','distance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'distance',
  `fixed_shipping_charge` double(23,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `modules`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `stores_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `icon` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme_id` int NOT NULL DEFAULT '1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `all_zone_service` tinyint(1) NOT NULL DEFAULT '0',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `newsletters`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Subscribers email',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `newsletters_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_title` text COLLATE utf8mb4_unicode_ci,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('admin','customer','store','deliveryman','provider') COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `mail_status` enum('active','inactive','disable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disable',
  `sms_status` enum('active','inactive','disable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disable',
  `push_notification_status` enum('active','inactive','disable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disable',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `module_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tergat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nutritions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nutritions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nutrition` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_access_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_auth_codes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_auth_codes_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_clients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_personal_access_clients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_personal_access_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oauth_refresh_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `offline_payment_methods`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offline_payment_methods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `method_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_fields` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_informations` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `offline_payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offline_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `payment_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `note` text COLLATE utf8mb4_unicode_ci,
  `customer_note` text COLLATE utf8mb4_unicode_ci,
  `method_fields` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_anywhere_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_anywhere_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_vendor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_vendor_address_or_website` text COLLATE utf8mb4_unicode_ci,
  `request_details` text COLLATE utf8mb4_unicode_ci,
  `item_details` text COLLATE utf8mb4_unicode_ci,
  `quantity` int DEFAULT NULL,
  `budget_estimate` decimal(12,2) DEFAULT NULL,
  `item_subtotal` decimal(10,2) DEFAULT NULL,
  `service_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tip` decimal(10,2) NOT NULL DEFAULT '0.00',
  `quote_amount` decimal(12,2) DEFAULT NULL,
  `authorized_amount` decimal(12,2) DEFAULT NULL,
  `final_amount` decimal(12,2) DEFAULT NULL,
  `captured_amount` decimal(12,2) DEFAULT NULL,
  `refunded_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_review',
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unquoted',
  `payment_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_payment_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `connect_account_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_pending',
  `transfer_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_fee` decimal(12,2) DEFAULT NULL,
  `vendor_payout_amount` decimal(12,2) DEFAULT NULL,
  `driver_payout_amount` decimal(12,2) DEFAULT NULL,
  `payment_mode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_payment_link_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_payment_url` text COLLATE utf8mb4_unicode_ci,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authorization_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capture_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `psp_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `merchant_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_link_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_url` text COLLATE utf8mb4_unicode_ci,
  `receipt_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_authorized_at` timestamp NULL DEFAULT NULL,
  `payment_captured_at` timestamp NULL DEFAULT NULL,
  `payment_refunded_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `vendor_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_notes` text COLLATE utf8mb4_unicode_ci,
  `vendor_quote_amount` decimal(12,2) DEFAULT NULL,
  `assigned_delivery_man_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `driver_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'unassigned',
  `assigned_at` timestamp NULL DEFAULT NULL,
  `driver_accepted_at` timestamp NULL DEFAULT NULL,
  `arrived_at_pickup_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `out_for_delivery_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `driver_task_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_notes` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `business_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `cart_items` json DEFAULT NULL,
  `source_urls` json DEFAULT NULL,
  `selected_options` json DEFAULT NULL,
  `customer_visible_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirming_availability',
  `fulfillment_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'order_anywhere_backend',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_anywhere_requests_request_number_unique` (`request_number`),
  KEY `order_anywhere_requests_customer_id_index` (`customer_id`),
  KEY `order_anywhere_requests_status_index` (`status`),
  KEY `order_anywhere_requests_vendor_id_index` (`vendor_id`),
  KEY `order_anywhere_requests_vendor_status_index` (`vendor_status`),
  KEY `order_anywhere_requests_assigned_delivery_man_id_index` (`assigned_delivery_man_id`),
  KEY `order_anywhere_requests_reviewed_by_index` (`reviewed_by`),
  KEY `order_anywhere_requests_payment_status_index` (`payment_status`),
  KEY `order_anywhere_requests_authorization_reference_index` (`authorization_reference`),
  KEY `order_anywhere_requests_capture_reference_index` (`capture_reference`),
  KEY `order_anywhere_requests_refund_reference_index` (`refund_reference`),
  KEY `order_anywhere_requests_delivery_man_id_index` (`delivery_man_id`),
  KEY `order_anywhere_requests_driver_status_index` (`driver_status`),
  KEY `order_anywhere_requests_payment_provider_index` (`payment_provider`),
  KEY `order_anywhere_requests_provider_reference_index` (`provider_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_cancel_reasons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_cancel_reasons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_delivery_histories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_delivery_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `start_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_details`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `price` decimal(24,2) NOT NULL DEFAULT '0.00',
  `item_details` text COLLATE utf8mb4_unicode_ci,
  `variation` text COLLATE utf8mb4_unicode_ci,
  `add_ons` text COLLATE utf8mb4_unicode_ci,
  `discount_on_item` decimal(24,2) DEFAULT NULL,
  `discount_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'amount',
  `quantity` int NOT NULL DEFAULT '1',
  `tax_amount` decimal(24,2) NOT NULL DEFAULT '1.00',
  `variant` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_campaign_id` bigint unsigned DEFAULT NULL,
  `total_add_on_price` decimal(24,2) NOT NULL DEFAULT '0.00',
  `category_id` bigint unsigned DEFAULT NULL,
  `discount_on_product_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_percentage` double(23,8) DEFAULT '0.00000000',
  `addon_discount` double(23,8) DEFAULT '0.00000000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `transaction_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `payment_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_references`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_references` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `is_reviewed` tinyint(1) NOT NULL DEFAULT '0',
  `is_review_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_taxes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_taxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_on` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_rate` double(23,8) NOT NULL DEFAULT '0.00000000',
  `tax_amount` double(23,8) NOT NULL DEFAULT '0.00000000',
  `before_tax_amount` double(23,8) NOT NULL DEFAULT '0.00000000',
  `after_tax_amount` double(23,8) NOT NULL DEFAULT '0.00000000',
  `tax_payer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `order_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int DEFAULT '1',
  `tax_id` bigint unsigned NOT NULL,
  `taxable_id` bigint unsigned DEFAULT NULL,
  `taxable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `store_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `system_tax_setup_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_taxes_country_code_index` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned NOT NULL,
  `order_amount` decimal(24,2) NOT NULL,
  `store_amount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `admin_commission` decimal(24,2) NOT NULL,
  `received_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `delivery_charge` decimal(24,2) NOT NULL DEFAULT '0.00',
  `original_delivery_charge` decimal(24,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(24,2) NOT NULL DEFAULT '0.00',
  `zone_id` bigint unsigned DEFAULT NULL,
  `module_id` bigint unsigned NOT NULL,
  `parcel_catgory_id` bigint unsigned DEFAULT NULL,
  `dm_tips` double(24,2) NOT NULL DEFAULT '0.00',
  `delivery_fee_comission` double(24,2) NOT NULL DEFAULT '0.00',
  `admin_expense` decimal(23,3) DEFAULT '0.000',
  `store_expense` double(23,3) DEFAULT '0.000',
  `discount_amount_by_store` double(23,3) DEFAULT '0.000',
  `additional_charge` double(23,3) NOT NULL DEFAULT '0.000',
  `extra_packaging_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `ref_bonus_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `commission_percentage` double(16,3) DEFAULT '0.000',
  `is_subscribed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `order_transactions_zone_id_index` (`zone_id`),
  KEY `order_transactions_module_id_foreign` (`module_id`),
  CONSTRAINT `order_transactions_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `orders`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `order_amount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `coupon_discount_amount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `coupon_discount_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `order_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_tax_amount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_reference` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address_id` bigint DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `coupon_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_note` text COLLATE utf8mb4_unicode_ci,
  `order_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'delivery',
  `checked` tinyint(1) NOT NULL DEFAULT '0',
  `store_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `delivery_charge` decimal(24,2) NOT NULL DEFAULT '0.00',
  `schedule_at` timestamp NULL DEFAULT NULL,
  `callback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pending` timestamp NULL DEFAULT NULL,
  `accepted` timestamp NULL DEFAULT NULL,
  `confirmed` timestamp NULL DEFAULT NULL,
  `processing` timestamp NULL DEFAULT NULL,
  `handover` timestamp NULL DEFAULT NULL,
  `picked_up` timestamp NULL DEFAULT NULL,
  `delivered` timestamp NULL DEFAULT NULL,
  `canceled` timestamp NULL DEFAULT NULL,
  `refund_requested` timestamp NULL DEFAULT NULL,
  `refunded` timestamp NULL DEFAULT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci,
  `scheduled` tinyint(1) NOT NULL DEFAULT '0',
  `store_discount_amount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `original_delivery_charge` decimal(24,2) NOT NULL DEFAULT '0.00',
  `failed` timestamp NULL DEFAULT NULL,
  `adjusment` decimal(24,2) NOT NULL DEFAULT '0.00',
  `edited` tinyint(1) NOT NULL DEFAULT '0',
  `delivery_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  `module_id` bigint unsigned NOT NULL,
  `order_attachment` text COLLATE utf8mb4_unicode_ci,
  `parcel_category_id` bigint unsigned DEFAULT NULL,
  `receiver_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `charge_payer` enum('sender','receiver') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distance` double(16,3) NOT NULL DEFAULT '0.000',
  `dm_tips` double(24,2) NOT NULL DEFAULT '0.00',
  `free_delivery_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_request_canceled` timestamp NULL DEFAULT NULL,
  `prescription_order` tinyint(1) NOT NULL DEFAULT '0',
  `age_restricted_order` tinyint(1) NOT NULL DEFAULT '0',
  `age_verification_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_age_confirmed_at` timestamp NULL DEFAULT NULL,
  `tax_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dm_vehicle_id` bigint unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canceled_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coupon_created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_on_product_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vendor',
  `processing_time` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unavailable_item_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cutlery` tinyint(1) NOT NULL DEFAULT '0',
  `delivery_instruction` text COLLATE utf8mb4_unicode_ci,
  `tax_percentage` double(24,3) DEFAULT NULL,
  `additional_charge` double(23,3) NOT NULL DEFAULT '0.000',
  `order_proof` text COLLATE utf8mb4_unicode_ci,
  `partially_paid_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `is_guest` tinyint(1) NOT NULL DEFAULT '0',
  `flash_admin_discount_amount` double(24,3) NOT NULL DEFAULT '0.000',
  `flash_store_discount_amount` double(24,3) NOT NULL DEFAULT '0.000',
  `cash_back_id` bigint unsigned DEFAULT NULL,
  `extra_packaging_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `ref_bonus_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `tax_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bring_change_amount` int DEFAULT '0',
  `cancellation_note` text COLLATE utf8mb4_unicode_ci,
  `extra_discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `zone_id` (`zone_id`),
  KEY `orders_module_id_foreign` (`module_id`),
  CONSTRAINT `orders_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `page_seo_data`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_seo_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_data` json DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_seo_data_page_name_unique` (`page_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parcel_cancellation_reasons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_cancellation_reasons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` enum('customer','admin','deliveryman','vendor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `cancellation_type` enum('before_pickup','after_pickup') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'before_pickup',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1=active,0=inactive',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parcel_cancellations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_cancellations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `cancel_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `return_otp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_fee` double DEFAULT '0',
  `return_fee_payment_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `return_date` datetime DEFAULT NULL,
  `dm_penalty_fee` double DEFAULT '0',
  `before_pickup` tinyint(1) NOT NULL DEFAULT '1',
  `set_return_date` tinyint(1) NOT NULL DEFAULT '0',
  `is_delivery_charge_refundable` tinyint(1) NOT NULL DEFAULT '0',
  `is_refunded` tinyint(1) NOT NULL DEFAULT '0',
  `refund_amount` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parcel_cancellations_order_id_index` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parcel_categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `image` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `orders_count` int NOT NULL DEFAULT '0',
  `module_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `parcel_per_km_shipping_charge` double(23,2) DEFAULT NULL,
  `parcel_minimum_shipping_charge` double(23,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parcel_categories_name_unique` (`name`),
  KEY `parcel_categories_module_id_foreign` (`module_id`),
  CONSTRAINT `parcel_categories_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parcel_delivery_instructions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_delivery_instructions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instruction` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parcel_penalty_fees`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_penalty_fees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_man_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `amount` decimal(10,5) NOT NULL DEFAULT '0.00000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parcel_penalty_fees_transaction_id_index` (`transaction_id`),
  KEY `parcel_penalty_fees_delivery_man_id_index` (`delivery_man_id`),
  KEY `parcel_penalty_fees_order_id_index` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parcel_return_fees`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcel_return_fees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `amount` decimal(10,5) NOT NULL DEFAULT '0.00000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parcel_return_fees_transaction_id_index` (`transaction_id`),
  KEY `parcel_return_fees_delivery_man_id_index` (`delivery_man_id`),
  KEY `parcel_return_fees_user_id_index` (`user_id`),
  KEY `parcel_return_fees_order_id_index` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partial_payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partial_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` bigint unsigned DEFAULT NULL,
  `transaction_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` double NOT NULL DEFAULT '0',
  `payment_status` enum('paid','unpaid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- [REDACTED SENSITIVE COMMENT]
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `otp_hit_count` tinyint NOT NULL DEFAULT '0',
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `is_temp_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `temp_block_time` timestamp NULL DEFAULT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payer_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_amount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `gateway_callback_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `success_hook` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_hook` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payer_information` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `external_redirect_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiver_information` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `attribute_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_platform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pharmacy_item_details`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pharmacy_item_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned DEFAULT NULL,
  `common_condition_id` bigint unsigned DEFAULT NULL,
  `is_basic` tinyint(1) NOT NULL DEFAULT '0',
  `temp_product_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_prescription_required` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `phone_verifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phone_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `otp_hit_count` tinyint NOT NULL DEFAULT '0',
  `is_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `is_temp_blocked` tinyint(1) NOT NULL DEFAULT '0',
  `temp_block_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_verifications_phone_unique` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `priority_lists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `priority_lists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provide_d_m_earnings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provide_d_m_earnings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_man_id` bigint unsigned NOT NULL,
  `amount` decimal(24,2) NOT NULL DEFAULT '0.00',
  `method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `react_promotional_banners`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `react_promotional_banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `react_testimonials`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `react_testimonials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review` text COLLATE utf8mb4_unicode_ci,
  `reviewer_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recent_searches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recent_searches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_uri` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_full_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keyword` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reel_engagements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reel_engagements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reel_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `guest_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reel_user_type_unique` (`reel_id`,`user_id`,`type`),
  UNIQUE KEY `reel_guest_type_unique` (`reel_id`,`guest_id`,`type`),
  KEY `reel_engagement_lookup_index` (`reel_id`,`user_id`,`guest_id`,`type`),
  CONSTRAINT `reel_engagements_reel_id_foreign` FOREIGN KEY (`reel_id`) REFERENCES `reels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reels`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `module_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_always_visible` tinyint(1) NOT NULL DEFAULT '0',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `total_views` bigint unsigned NOT NULL DEFAULT '0',
  `total_likes` bigint unsigned NOT NULL DEFAULT '0',
  `total_store_visits` bigint unsigned NOT NULL DEFAULT '0',
  `created_by_id` bigint unsigned DEFAULT NULL,
  `created_by_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reels_store_id_foreign` (`store_id`),
  KEY `reels_module_id_index` (`module_id`),
  KEY `reels_module_type_index` (`module_type`),
  CONSTRAINT `reels_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `refund_reasons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refund_reasons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `refunds`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refunds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `order_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_note` text COLLATE utf8mb4_unicode_ci,
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `refund_amount` decimal(23,3) NOT NULL DEFAULT '0.000',
  `refund_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refund_method` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rental_cart_user_data`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rental_cart_user_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `pickup_location` text COLLATE utf8mb4_unicode_ci,
  `destination_location` text COLLATE utf8mb4_unicode_ci,
  `pickup_time` datetime DEFAULT NULL,
  `rental_type` enum('hourly','distance_wise','day_wise') COLLATE utf8mb4_unicode_ci DEFAULT 'hourly',
  `estimated_hours` double NOT NULL DEFAULT '0',
  `distance` double NOT NULL DEFAULT '0',
  `total_cart_price` double NOT NULL DEFAULT '0',
  `destination_time` double NOT NULL DEFAULT '0',
  `is_guest` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rental_carts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rental_carts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `is_guest` tinyint(1) NOT NULL DEFAULT '0',
  `price` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rental_email_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rental_email_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `body_2` text COLLATE utf8mb4_unicode_ci,
  `background_image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `button_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `button_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `footer_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `privacy` tinyint(1) NOT NULL DEFAULT '0',
  `refund` tinyint(1) NOT NULL DEFAULT '0',
  `cancelation` tinyint(1) NOT NULL DEFAULT '0',
  `contact` tinyint(1) NOT NULL DEFAULT '0',
  `facebook` tinyint(1) NOT NULL DEFAULT '0',
  `instagram` tinyint(1) NOT NULL DEFAULT '0',
  `twitter` tinyint(1) NOT NULL DEFAULT '0',
  `linkedin` tinyint(1) NOT NULL DEFAULT '0',
  `pinterest` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rental_wishlishes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rental_wishlishes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `provider_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reviews`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `comment` mediumtext COLLATE utf8mb4_unicode_ci,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` int NOT NULL DEFAULT '0',
  `order_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `item_campaign_id` bigint unsigned DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `module_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `reply` text COLLATE utf8mb4_unicode_ci,
  `review_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reviews_module_id_foreign` (`module_id`),
  KEY `reviews_item_id_index` (`item_id`),
  KEY `reviews_item_campaign_id_index` (`item_campaign_id`),
  KEY `reviews_user_id_index` (`user_id`),
  KEY `reviews_order_id_index` (`order_id`),
  KEY `reviews_store_id_index` (`store_id`),
  KEY `reviews_review_id_index` (`review_id`),
  CONSTRAINT `reviews_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `social_media`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `soft_credentials`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `soft_credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `storages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `storages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `data_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `storages_data_id_index` (`data_id`),
  KEY `storages_value_index` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_configs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `is_recommended` tinyint(1) NOT NULL DEFAULT '0',
  `is_recommended_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `halal_tag_status` tinyint(1) NOT NULL DEFAULT '0',
  `extra_packaging_status` tinyint(1) NOT NULL DEFAULT '0',
  `extra_packaging_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `minimum_stock_for_warning` int NOT NULL DEFAULT '0',
  `show_low_stock_count` tinyint(1) NOT NULL DEFAULT '1',
  `section_wise_ai_use_count` int NOT NULL DEFAULT '0',
  `image_wise_ai_use_count` int NOT NULL DEFAULT '0',
  `verified_seller` tinyint(1) NOT NULL DEFAULT '0',
  `has_seen_verified_badge_popup` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_notification_settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_notification_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_title` text COLLATE utf8mb4_unicode_ci,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_id` bigint unsigned NOT NULL,
  `mail_status` enum('active','inactive','disable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disable',
  `sms_status` enum('active','inactive','disable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disable',
  `push_notification_status` enum('active','inactive','disable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disable',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `module_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_schedule`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_schedule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `day` int NOT NULL,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_subscriptions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `expiry_date` date NOT NULL,
  `validity` int NOT NULL DEFAULT '0',
  `max_order` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_product` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pos` tinyint(1) NOT NULL DEFAULT '0',
  `mobile_app` tinyint(1) NOT NULL DEFAULT '0',
  `chat` tinyint(1) NOT NULL DEFAULT '0',
  `review` tinyint(1) NOT NULL DEFAULT '0',
  `self_delivery` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `is_trial` tinyint(1) NOT NULL DEFAULT '0',
  `total_package_renewed` tinyint NOT NULL DEFAULT '0',
  `renewed_at` datetime DEFAULT NULL,
  `is_canceled` tinyint(1) NOT NULL DEFAULT '0',
  `canceled_by` enum('none','admin','store') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `store_wallets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_wallets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vendor_id` bigint unsigned NOT NULL,
  `total_earning` decimal(24,2) NOT NULL DEFAULT '0.00',
  `total_withdrawn` decimal(24,2) NOT NULL DEFAULT '0.00',
  `pending_withdraw` decimal(24,2) NOT NULL DEFAULT '0.00',
  `collected_cash` decimal(24,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stores`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `footer_text` text COLLATE utf8mb4_unicode_ci,
  `minimum_order` decimal(24,2) NOT NULL DEFAULT '0.00',
  `comission` decimal(24,2) DEFAULT NULL,
  `schedule_order` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `vendor_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `free_delivery` tinyint(1) NOT NULL DEFAULT '0',
  `rating` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery` tinyint(1) NOT NULL DEFAULT '1',
  `take_away` tinyint(1) NOT NULL DEFAULT '1',
  `item_section` tinyint(1) NOT NULL DEFAULT '1',
  `tax` decimal(24,2) NOT NULL DEFAULT '0.00',
  `zone_id` bigint unsigned DEFAULT NULL,
  `reviews_section` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `off_day` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ' ',
  `gst` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_delivery_system` tinyint(1) NOT NULL DEFAULT '0',
  `pos_system` tinyint(1) NOT NULL DEFAULT '0',
  `minimum_shipping_charge` decimal(24,2) NOT NULL DEFAULT '0.00',
  `delivery_time` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '30-40',
  `veg` tinyint(1) NOT NULL DEFAULT '1',
  `non_veg` tinyint(1) NOT NULL DEFAULT '1',
  `order_count` int unsigned NOT NULL DEFAULT '0',
  `total_order` int unsigned NOT NULL DEFAULT '0',
  `module_id` bigint unsigned NOT NULL,
  `business_type_slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_place_to_schedule_interval` int DEFAULT '0',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `per_km_shipping_charge` double(16,3) unsigned NOT NULL DEFAULT '0.000',
  `prescription_order` tinyint(1) NOT NULL DEFAULT '0',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maximum_shipping_charge` double(23,3) DEFAULT NULL,
  `cutlery` tinyint(1) NOT NULL DEFAULT '0',
  `meta_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_data` json DEFAULT NULL,
  `announcement` tinyint(1) NOT NULL DEFAULT '0',
  `announcement_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_business_model` enum('none','commission','subscription','unsubscribed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'commission',
  `package_id` bigint unsigned DEFAULT NULL,
  `pickup_zone_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `tin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tin_expire_date` date DEFAULT NULL,
  `tin_certificate_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active_partner',
  `contract_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contracted',
  `vendor_admin_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `banking_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `subscription_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `admin_approval_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `badge_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'urban_goodz_partner',
  `fulfillment_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct_vendor_order',
  `is_public_sourced` tinyint(1) NOT NULL DEFAULT '0',
  `is_claimed` tinyint(1) NOT NULL DEFAULT '1',
  `is_partner` tinyint(1) NOT NULL DEFAULT '1',
  `can_direct_checkout` tinyint(1) NOT NULL DEFAULT '1',
  `requires_admin_quote` tinyint(1) NOT NULL DEFAULT '0',
  `vendor_admin_account_created` tinyint(1) NOT NULL DEFAULT '1',
  `vendor_has_logged_in` tinyint(1) NOT NULL DEFAULT '1',
  `partner_badge_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `order_anywhere_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `invited_at` timestamp NULL DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `vendor_panel_activated_at` timestamp NULL DEFAULT NULL,
  `banking_submitted_at` timestamp NULL DEFAULT NULL,
  `banking_verified_at` timestamp NULL DEFAULT NULL,
  `contracted_at` timestamp NULL DEFAULT NULL,
  `subscription_activated_at` timestamp NULL DEFAULT NULL,
  `admin_approved_at` timestamp NULL DEFAULT NULL,
  `partner_badge_enabled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restaurants_phone_unique` (`phone`),
  KEY `stores_module_id_foreign` (`module_id`),
  KEY `stores_business_type_slug_index` (`business_type_slug`),
  CONSTRAINT `stores_business_type_slug_foreign` FOREIGN KEY (`business_type_slug`) REFERENCES `urban_goodz_business_types` (`slug`) ON DELETE SET NULL,
  CONSTRAINT `stores_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscription_billing_and_refund_histories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_billing_and_refund_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `subscription_id` bigint unsigned NOT NULL,
  `package_id` bigint unsigned DEFAULT NULL,
  `transaction_type` enum('pending_bill','refund') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_bill',
  `amount` double(24,3) NOT NULL,
  `is_success` tinyint(1) NOT NULL DEFAULT '0',
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscription_packages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` double(24,3) NOT NULL,
  `validity` int NOT NULL,
  `max_order` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unlimited',
  `max_product` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unlimited',
  `pos` tinyint(1) NOT NULL DEFAULT '0',
  `mobile_app` tinyint(1) NOT NULL DEFAULT '0',
  `chat` tinyint(1) NOT NULL DEFAULT '0',
  `review` tinyint(1) NOT NULL DEFAULT '0',
  `self_delivery` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `default` tinyint(1) NOT NULL DEFAULT '0',
  `colour` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `module_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscription_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `store_subscription_id` bigint unsigned DEFAULT NULL,
  `price` double(24,3) NOT NULL DEFAULT '0.000',
  `previous_due` double(24,3) NOT NULL DEFAULT '0.000',
  `validity` int NOT NULL DEFAULT '0',
  `payment_method` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` double(24,2) NOT NULL,
  `discount` int NOT NULL DEFAULT '0',
  `package_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_trial` tinyint(1) NOT NULL DEFAULT '0',
  `transaction_status` tinyint(1) NOT NULL DEFAULT '1',
  `plan_type` enum('renew','new_plan','first_purchased','free_trial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'first_purchased',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `surge_price_dates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `surge_price_dates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `surge_price_id` bigint unsigned NOT NULL,
  `zone_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `applicable_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `surge_prices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `surge_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `surge_price_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_note` text COLLATE utf8mb4_unicode_ci,
  `customer_note_status` tinyint(1) NOT NULL DEFAULT '1',
  `module_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `zone_id` bigint unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_type` enum('amount','percent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'amount',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `is_permanent` tinyint(1) NOT NULL DEFAULT '0',
  `duration_type` enum('daily','weekly','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `weekly_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `custom_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `custom_times` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `surge_prices_chk_1` CHECK (json_valid(`module_ids`)),
  CONSTRAINT `surge_prices_chk_2` CHECK (json_valid(`weekly_days`)),
  CONSTRAINT `surge_prices_chk_3` CHECK (json_valid(`custom_days`)),
  CONSTRAINT `surge_prices_chk_4` CHECK (json_valid(`custom_times`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_tax_setups`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_tax_setups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tax_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'order_wise',
  `country_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_payer` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'vendor',
  `tax_ids` tinytext COLLATE utf8mb4_unicode_ci,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_included` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_tax_setups_country_code_index` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tax_additional_setups`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_additional_setups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `system_tax_setup_id` bigint unsigned DEFAULT NULL,
  `tax_ids` tinytext COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_additional_setups_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `taxables`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `taxables` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `taxable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `taxable_id` bigint unsigned NOT NULL,
  `tax_id` bigint unsigned NOT NULL,
  `system_tax_setup_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `taxes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `taxes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_rate` double(23,8) NOT NULL DEFAULT '0.00000000',
  `country_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `taxes_country_code_index` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `temp_products`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `temp_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_link` text COLLATE utf8mb4_unicode_ci,
  `images` longtext COLLATE utf8mb4_unicode_ci,
  `store_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variations` text COLLATE utf8mb4_unicode_ci,
  `food_variations` text COLLATE utf8mb4_unicode_ci,
  `add_ons` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attributes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `choice_options` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(24,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(24,2) NOT NULL DEFAULT '0.00',
  `tax_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `discount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `discount_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `veg` tinyint(1) NOT NULL DEFAULT '0',
  `recommended` tinyint(1) NOT NULL DEFAULT '0',
  `organic` tinyint(1) NOT NULL DEFAULT '0',
  `common_condition_id` bigint unsigned DEFAULT NULL,
  `basic` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `stock` int DEFAULT '0',
  `maximum_cart_quantity` int DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `is_rejected` tinyint(1) NOT NULL DEFAULT '0',
  `available_time_ends` time DEFAULT NULL,
  `available_time_starts` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_halal` tinyint(1) NOT NULL DEFAULT '0',
  `brand_id` tinyint(1) NOT NULL DEFAULT '0',
  `is_prescription_required` tinyint(1) NOT NULL DEFAULT '0',
  `nutrition_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allergy_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generic_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `track_deliverymen`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `track_deliverymen` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `longitude` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `translations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `translationable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `translationable_id` bigint unsigned NOT NULL,
  `locale` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `translations_translationable_id_index` (`translationable_id`),
  KEY `translations_locale_index` (`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trip_details`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trip_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned NOT NULL,
  `quantity` smallint NOT NULL DEFAULT '1',
  `price` double NOT NULL DEFAULT '0',
  `original_price` double NOT NULL DEFAULT '0',
  `calculated_price` double NOT NULL DEFAULT '0',
  `discount_on_trip` double NOT NULL DEFAULT '0',
  `tax_amount` double NOT NULL DEFAULT '0',
  `tax_status` enum('included','excluded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'excluded',
  `tax_percentage` double NOT NULL DEFAULT '0',
  `discount_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_on_trip_by` enum('admin','vendor','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `discount_percentage` double NOT NULL DEFAULT '0',
  `vehicle_details` text COLLATE utf8mb4_unicode_ci,
  `rental_type` enum('hourly','distance_wise','day_wise') COLLATE utf8mb4_unicode_ci DEFAULT 'hourly',
  `estimated_hours` double NOT NULL DEFAULT '0',
  `distance` double NOT NULL DEFAULT '0',
  `scheduled` tinyint(1) NOT NULL DEFAULT '0',
  `schedule_at` datetime DEFAULT NULL,
  `estimated_trip_end_time` datetime DEFAULT NULL,
  `is_edited` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trip_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trip_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` bigint unsigned DEFAULT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `trip_id` bigint unsigned DEFAULT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  `trip_amount` double NOT NULL DEFAULT '0',
  `store_amount` double NOT NULL DEFAULT '0',
  `admin_commission` double NOT NULL DEFAULT '0',
  `tax` double NOT NULL DEFAULT '0',
  `received_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_expense` double NOT NULL DEFAULT '0',
  `store_expense` double NOT NULL DEFAULT '0',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount_by_store` double NOT NULL DEFAULT '0',
  `additional_charge` double NOT NULL DEFAULT '0',
  `ref_bonus_amount` double NOT NULL DEFAULT '0',
  `is_subscribed` tinyint(1) NOT NULL DEFAULT '0',
  `commission_percentage` double NOT NULL DEFAULT '0',
  `admin_net_income` double NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trip_vehicle_details`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trip_vehicle_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned NOT NULL,
  `trip_details_id` bigint unsigned DEFAULT NULL,
  `vehicle_identity_id` bigint unsigned DEFAULT NULL,
  `vehicle_driver_id` bigint unsigned DEFAULT NULL,
  `estimated_trip_end_time` datetime DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trips`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trips` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `provider_id` bigint unsigned NOT NULL,
  `zone_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `pickup_zone_id` bigint unsigned DEFAULT NULL,
  `cash_back_id` bigint unsigned DEFAULT NULL,
  `trip_amount` double NOT NULL DEFAULT '0',
  `discount_on_trip` double NOT NULL DEFAULT '0',
  `discount_on_trip_by` enum('admin','vendor','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `coupon_discount_amount` double NOT NULL DEFAULT '0',
  `coupon_discount_by` enum('admin','vendor','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `coupon_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trip_status` enum('pending','confirmed','ongoing','completed','canceled','payment_failed','processing','waiting') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status` enum('paid','unpaid','partially_paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount` double NOT NULL DEFAULT '0',
  `tax_status` enum('included','excluded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'excluded',
  `tax_percentage` double NOT NULL DEFAULT '0',
  `trip_type` enum('hourly','distance_wise','day_wise') COLLATE utf8mb4_unicode_ci DEFAULT 'hourly',
  `additional_charge` double NOT NULL DEFAULT '0',
  `partially_paid_amount` double NOT NULL DEFAULT '0',
  `distance` double NOT NULL DEFAULT '0',
  `estimated_hours` double NOT NULL DEFAULT '0',
  `ref_bonus_amount` double NOT NULL DEFAULT '0',
  `canceled_by` enum('admin','vendor','user','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_location` text COLLATE utf8mb4_unicode_ci,
  `destination_location` text COLLATE utf8mb4_unicode_ci,
  `user_info` text COLLATE utf8mb4_unicode_ci,
  `trip_note` text COLLATE utf8mb4_unicode_ci,
  `callback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_guest` tinyint(1) NOT NULL DEFAULT '0',
  `edited` tinyint(1) NOT NULL DEFAULT '0',
  `checked` tinyint(1) NOT NULL DEFAULT '0',
  `scheduled` tinyint(1) NOT NULL DEFAULT '0',
  `schedule_at` datetime DEFAULT NULL,
  `pending` datetime DEFAULT NULL,
  `confirmed` datetime DEFAULT NULL,
  `ongoing` datetime DEFAULT NULL,
  `completed` datetime DEFAULT NULL,
  `canceled` datetime DEFAULT NULL,
  `payment_failed` datetime DEFAULT NULL,
  `quantity` smallint NOT NULL DEFAULT '1',
  `estimated_trip_end_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `units`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unit` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_activity_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loggable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `loggable_id` bigint unsigned NOT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_activity_logs_loggable_type_loggable_id_index` (`loggable_type`,`loggable_id`),
  KEY `urban_goodz_activity_logs_causer_type_causer_id_index` (`causer_type`,`causer_id`),
  KEY `urban_goodz_activity_logs_event_index` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_age_verifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_age_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint unsigned DEFAULT NULL,
  `route_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `driver_id` bigint unsigned DEFAULT NULL,
  `verification_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `refusal_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_notes` text COLLATE utf8mb4_unicode_ci,
  `id_type_checked` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_name_verified` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_dob_verified` date DEFAULT NULL,
  `recipient_age_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `verification_attempted_at` timestamp NULL DEFAULT NULL,
  `signature_captured` tinyint(1) NOT NULL DEFAULT '0',
  `proof_photo_captured` tinyint(1) NOT NULL DEFAULT '0',
  `admin_review_required` tinyint(1) NOT NULL DEFAULT '0',
  `admin_review_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_reviewed_by` bigint unsigned DEFAULT NULL,
  `admin_reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_age_ver_route_fk` (`route_id`),
  KEY `ug_age_ver_driver_fk` (`driver_id`),
  KEY `ug_age_ver_admin_fk` (`admin_reviewed_by`),
  KEY `ug_age_ver_pkg_status_idx` (`package_id`,`verification_status`),
  KEY `ug_age_ver_order_status_idx` (`order_id`,`verification_status`),
  KEY `ug_age_ver_review_req_idx` (`admin_review_required`),
  CONSTRAINT `ug_age_ver_admin_fk` FOREIGN KEY (`admin_reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_age_ver_driver_fk` FOREIGN KEY (`driver_id`) REFERENCES `delivery_men` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_age_ver_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_age_ver_pkg_fk` FOREIGN KEY (`package_id`) REFERENCES `urban_goodz_route_packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_age_ver_route_fk` FOREIGN KEY (`route_id`) REFERENCES `urban_goodz_dedicated_routes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_ai_conversations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_ai_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned DEFAULT NULL,
  `query_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detected_intent_id` bigint unsigned DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL,
  `response_text` text COLLATE utf8mb4_unicode_ci,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer_api',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_ai_conversations_detected_intent_id_foreign` (`detected_intent_id`),
  KEY `urban_goodz_ai_conversations_customer_id_index` (`customer_id`),
  KEY `urban_goodz_ai_conversations_status_index` (`status`),
  KEY `urban_goodz_ai_conversations_source_index` (`source`),
  CONSTRAINT `urban_goodz_ai_conversations_detected_intent_id_foreign` FOREIGN KEY (`detected_intent_id`) REFERENCES `urban_goodz_ai_intents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_ai_intents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_ai_intents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `keywords` json DEFAULT NULL,
  `response_template` text COLLATE utf8mb4_unicode_ci,
  `capability_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_section_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_ai_intents_slug_unique` (`slug`),
  KEY `urban_goodz_ai_intents_capability_slug_index` (`capability_slug`),
  KEY `urban_goodz_ai_intents_admin_section_key_index` (`admin_section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_appointments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_request_id` bigint unsigned DEFAULT NULL,
  `service_provider_id` bigint unsigned DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_appointments_service_request_id_foreign` (`service_request_id`),
  KEY `urban_goodz_appointments_service_provider_id_foreign` (`service_provider_id`),
  CONSTRAINT `urban_goodz_appointments_service_provider_id_foreign` FOREIGN KEY (`service_provider_id`) REFERENCES `urban_goodz_service_providers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `urban_goodz_appointments_service_request_id_foreign` FOREIGN KEY (`service_request_id`) REFERENCES `urban_goodz_service_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_book_anywhere_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_book_anywhere_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `service_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `budget_amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_provider_id` bigint unsigned DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_book_anywhere_requests_request_number_unique` (`request_number`),
  KEY `urban_goodz_book_anywhere_requests_status_index` (`status`),
  KEY `urban_goodz_book_anywhere_requests_customer_id_index` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_business_capabilities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_business_capabilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` bigint unsigned NOT NULL,
  `capability_id` bigint unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `settings` json DEFAULT NULL,
  `enabled_at` timestamp NULL DEFAULT NULL,
  `disabled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_business_capabilities_store_id_capability_id_unique` (`store_id`,`capability_id`),
  KEY `urban_goodz_business_capabilities_capability_id_foreign` (`capability_id`),
  CONSTRAINT `urban_goodz_business_capabilities_capability_id_foreign` FOREIGN KEY (`capability_id`) REFERENCES `urban_goodz_capabilities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `urban_goodz_business_capabilities_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_business_client_documents`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_business_client_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_client_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `expires_at` date DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_biz_docs_client_fk` (`business_client_id`),
  KEY `urban_goodz_business_client_documents_uploaded_by_index` (`uploaded_by`),
  KEY `urban_goodz_business_client_documents_verified_by_index` (`verified_by`),
  CONSTRAINT `ug_biz_docs_client_fk` FOREIGN KEY (`business_client_id`) REFERENCES `urban_goodz_business_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_business_client_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_business_client_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_client_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `job_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `description` text COLLATE utf8mb4_unicode_ci,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `po_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_location_id` bigint unsigned DEFAULT NULL,
  `pickup_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_earliest` datetime DEFAULT NULL,
  `pickup_latest` datetime DEFAULT NULL,
  `dropoff_location_id` bigint unsigned DEFAULT NULL,
  `dropoff_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dropoff_contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_deadline` datetime DEFAULT NULL,
  `load_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lbs',
  `dimensions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pallet_count` int DEFAULT NULL,
  `vehicle_type_needed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `needs_liftgate` tinyint(1) NOT NULL DEFAULT '0',
  `needs_dock` tinyint(1) NOT NULL DEFAULT '0',
  `special_handling` text COLLATE utf8mb4_unicode_ci,
  `specimen_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temperature_requirement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urgency_level` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chain_of_custody_required` tinyint(1) NOT NULL DEFAULT '0',
  `sealed_package_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `courier_certification_required` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate_offered` decimal(12,2) DEFAULT NULL,
  `quoted_amount` decimal(12,2) DEFAULT NULL,
  `final_amount` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `assigned_delivery_man_id` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `driver_accepted_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `proof_of_pickup` text COLLATE utf8mb4_unicode_ci,
  `proof_of_delivery` text COLLATE utf8mb4_unicode_ci,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `driver_notes` text COLLATE utf8mb4_unicode_ci,
  `exception_reason` text COLLATE utf8mb4_unicode_ci,
  `exception_reported_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoiced_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_business_client_jobs_job_number_unique` (`job_number`),
  UNIQUE KEY `urban_goodz_business_client_jobs_invoice_number_unique` (`invoice_number`),
  KEY `ug_jobs_client_status_idx` (`business_client_id`,`status`),
  KEY `ug_jobs_type_status_idx` (`job_type`,`status`),
  KEY `urban_goodz_business_client_jobs_created_by_index` (`created_by`),
  KEY `urban_goodz_business_client_jobs_assigned_delivery_man_id_index` (`assigned_delivery_man_id`),
  KEY `urban_goodz_business_client_jobs_reviewed_by_index` (`reviewed_by`),
  CONSTRAINT `ug_biz_jobs_client_fk` FOREIGN KEY (`business_client_id`) REFERENCES `urban_goodz_business_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_business_client_locations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_business_client_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_client_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'primary',
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `postal_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'US',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operating_hours` text COLLATE utf8mb4_unicode_ci,
  `pickup_instructions` text COLLATE utf8mb4_unicode_ci,
  `delivery_instructions` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_biz_locs_client_fk` (`business_client_id`),
  CONSTRAINT `ug_biz_locs_client_fk` FOREIGN KEY (`business_client_id`) REFERENCES `urban_goodz_business_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_business_client_users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_business_client_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_client_id` bigint unsigned NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dispatcher',
  `permissions` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ug_biz_users_email_unique` (`business_client_id`,`email`),
  CONSTRAINT `ug_biz_users_client_fk` FOREIGN KEY (`business_client_id`) REFERENCES `urban_goodz_business_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_business_clients`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_business_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'business',
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'US',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `billing_terms` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'due_on_receipt',
  `credit_limit` decimal(12,2) DEFAULT NULL,
  `payment_method_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_added',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `territory_states` json DEFAULT NULL,
  `territory_corridors` json DEFAULT NULL,
  `dispatch_default_commission_rate` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_business_clients_email_unique` (`email`),
  KEY `urban_goodz_business_clients_approved_by_index` (`approved_by`),
  KEY `urban_goodz_business_clients_account_type_index` (`account_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_business_type_default_capabilities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_business_type_default_capabilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_type_id` bigint unsigned NOT NULL,
  `capability_id` bigint unsigned NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ugz_bt_pivot_unique` (`business_type_id`,`capability_id`),
  KEY `ugz_bt_pivot_cap_fk` (`capability_id`),
  CONSTRAINT `ugz_bt_pivot_bus_fk` FOREIGN KEY (`business_type_id`) REFERENCES `urban_goodz_business_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ugz_bt_pivot_cap_fk` FOREIGN KEY (`capability_id`) REFERENCES `urban_goodz_capabilities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_business_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_business_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_business_types_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_capabilities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_capabilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `admin_section_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_core` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_capabilities_slug_unique` (`slug`),
  KEY `urban_goodz_capabilities_admin_section_key_index` (`admin_section_key`),
  KEY `urban_goodz_capabilities_group_index` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_client_invoices`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_client_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_client_id` bigint unsigned NOT NULL,
  `dedicated_route_id` bigint unsigned DEFAULT NULL,
  `invoice_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'route',
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_client_invoices_invoice_number_unique` (`invoice_number`),
  KEY `ug_inv_route_fk` (`dedicated_route_id`),
  KEY `ug_inv_client_status_idx` (`business_client_id`,`status`),
  KEY `urban_goodz_client_invoices_created_by_index` (`created_by`),
  CONSTRAINT `ug_inv_client_fk` FOREIGN KEY (`business_client_id`) REFERENCES `urban_goodz_business_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ug_inv_route_fk` FOREIGN KEY (`dedicated_route_id`) REFERENCES `urban_goodz_dedicated_routes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_community_comments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_community_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_community_comments_post_id_foreign` (`post_id`),
  CONSTRAINT `urban_goodz_community_comments_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `urban_goodz_community_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_community_marketplace_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_community_marketplace_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `condition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seller_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seller_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_community_posts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_community_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_applications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creator_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `follower_count` int DEFAULT NULL,
  `niche` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `market` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_links` json DEFAULT NULL,
  `content_samples` json DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_business_leads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_business_leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creator_profile_id` bigint unsigned DEFAULT NULL,
  `creator_application_id` bigint unsigned DEFAULT NULL,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `video_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `suggested_module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_creator_leads_profile_fk` (`creator_profile_id`),
  KEY `ug_creator_leads_app_fk` (`creator_application_id`),
  CONSTRAINT `ug_creator_leads_app_fk` FOREIGN KEY (`creator_application_id`) REFERENCES `urban_goodz_creator_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_leads_profile_fk` FOREIGN KEY (`creator_profile_id`) REFERENCES `urban_goodz_creator_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_campaign_assignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_campaign_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` bigint unsigned NOT NULL,
  `creator_profile_id` bigint unsigned DEFAULT NULL,
  `creator_application_id` bigint unsigned DEFAULT NULL,
  `approval_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `creator_notes` text COLLATE utf8mb4_unicode_ci,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_creator_assign_camp_fk` (`campaign_id`),
  KEY `ug_creator_assign_profile_fk` (`creator_profile_id`),
  KEY `ug_creator_assign_app_fk` (`creator_application_id`),
  CONSTRAINT `ug_creator_assign_app_fk` FOREIGN KEY (`creator_application_id`) REFERENCES `urban_goodz_creator_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_assign_camp_fk` FOREIGN KEY (`campaign_id`) REFERENCES `urban_goodz_creator_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ug_creator_assign_profile_fk` FOREIGN KEY (`creator_profile_id`) REFERENCES `urban_goodz_creator_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_campaigns`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'flat',
  `flat_payout` decimal(12,2) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `deliverables` text COLLATE utf8mb4_unicode_ci,
  `brief` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_creator_campaigns_vendor_fk` (`vendor_id`),
  CONSTRAINT `ug_creator_campaigns_vendor_fk` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_content`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_content` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creator_profile_id` bigint unsigned DEFAULT NULL,
  `creator_application_id` bigint unsigned DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `content_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'video',
  `media_urls` json DEFAULT NULL,
  `linked_vendor_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linked_vendor_id` bigint unsigned DEFAULT NULL,
  `linked_vendor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `likes_count` int NOT NULL DEFAULT '0',
  `shares_count` int NOT NULL DEFAULT '0',
  `saves_count` int NOT NULL DEFAULT '0',
  `clicks_count` int NOT NULL DEFAULT '0',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `is_shoppable` tinyint(1) NOT NULL DEFAULT '0',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_creator_content_profile_fk` (`creator_profile_id`),
  KEY `ug_creator_content_app_fk` (`creator_application_id`),
  KEY `ug_creator_content_camp_fk` (`campaign_id`),
  CONSTRAINT `ug_creator_content_app_fk` FOREIGN KEY (`creator_application_id`) REFERENCES `urban_goodz_creator_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_content_camp_fk` FOREIGN KEY (`campaign_id`) REFERENCES `urban_goodz_creator_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_content_profile_fk` FOREIGN KEY (`creator_profile_id`) REFERENCES `urban_goodz_creator_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_earnings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_earnings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creator_profile_id` bigint unsigned DEFAULT NULL,
  `creator_application_id` bigint unsigned DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `content_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'commission',
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_creator_earnings_profile_fk` (`creator_profile_id`),
  KEY `ug_creator_earnings_app_fk` (`creator_application_id`),
  KEY `ug_creator_earnings_camp_fk` (`campaign_id`),
  KEY `ug_creator_earnings_content_fk` (`content_id`),
  CONSTRAINT `ug_creator_earnings_app_fk` FOREIGN KEY (`creator_application_id`) REFERENCES `urban_goodz_creator_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_earnings_camp_fk` FOREIGN KEY (`campaign_id`) REFERENCES `urban_goodz_creator_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_earnings_content_fk` FOREIGN KEY (`content_id`) REFERENCES `urban_goodz_creator_content` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_earnings_profile_fk` FOREIGN KEY (`creator_profile_id`) REFERENCES `urban_goodz_creator_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_event_promotions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_event_promotions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creator_profile_id` bigint unsigned DEFAULT NULL,
  `creator_application_id` bigint unsigned DEFAULT NULL,
  `event_id` bigint unsigned NOT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `promo_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'social_post',
  `promo_content` text COLLATE utf8mb4_unicode_ci,
  `ticket_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reservation_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_booth_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `commission_earned` decimal(12,2) NOT NULL DEFAULT '0.00',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_creator_events_profile_fk` (`creator_profile_id`),
  KEY `ug_creator_events_app_fk` (`creator_application_id`),
  KEY `ug_creator_events_event_fk` (`event_id`),
  KEY `ug_creator_events_camp_fk` (`campaign_id`),
  CONSTRAINT `ug_creator_events_app_fk` FOREIGN KEY (`creator_application_id`) REFERENCES `urban_goodz_creator_applications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_events_camp_fk` FOREIGN KEY (`campaign_id`) REFERENCES `urban_goodz_creator_campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_creator_events_event_fk` FOREIGN KEY (`event_id`) REFERENCES `urban_goodz_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ug_creator_events_profile_fk` FOREIGN KEY (`creator_profile_id`) REFERENCES `urban_goodz_creator_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_products`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creator_application_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `media_urls` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_creator_products_creator_application_id_foreign` (`creator_application_id`),
  CONSTRAINT `urban_goodz_creator_products_creator_application_id_foreign` FOREIGN KEY (`creator_application_id`) REFERENCES `urban_goodz_creator_applications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_creator_profiles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_creator_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creator_application_id` bigint unsigned DEFAULT NULL,
  `handle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `avatar_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `niches` json DEFAULT NULL,
  `social_links` json DEFAULT NULL,
  `content_samples` json DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `approved_at` timestamp NULL DEFAULT NULL,
  `featured_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_creator_profiles_handle_unique` (`handle`),
  KEY `ug_creator_profiles_app_fk` (`creator_application_id`),
  CONSTRAINT `ug_creator_profiles_app_fk` FOREIGN KEY (`creator_application_id`) REFERENCES `urban_goodz_creator_applications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_dedicated_routes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_dedicated_routes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_client_id` bigint unsigned NOT NULL,
  `route_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_location` text COLLATE utf8mb4_unicode_ci,
  `end_location` text COLLATE utf8mb4_unicode_ci,
  `end_lat` decimal(10,7) DEFAULT NULL,
  `end_lng` decimal(10,7) DEFAULT NULL,
  `pickup_lat` decimal(10,7) DEFAULT NULL,
  `pickup_lng` decimal(10,7) DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `recurring_rule` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_packages_per_batch` int NOT NULL DEFAULT '50',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_driver_id` bigint unsigned DEFAULT NULL,
  `vehicle_type_required` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_packages` int NOT NULL DEFAULT '0',
  `completed_packages` int NOT NULL DEFAULT '0',
  `failed_packages` int NOT NULL DEFAULT '0',
  `returned_packages` int NOT NULL DEFAULT '0',
  `driver_pay_per_package` decimal(12,2) NOT NULL DEFAULT '0.00',
  `business_charge_per_package` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pickup_bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `route_completion_bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `priority_package_bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `failed_delivery_partial_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `return_to_sender_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `instant_payout_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `weekly_payout_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `payout_model` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_offer_amount` decimal(12,2) DEFAULT NULL,
  `estimated_miles` decimal(10,2) DEFAULT NULL,
  `estimated_duration` int DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `route_started_at` timestamp NULL DEFAULT NULL,
  `route_completed_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `contains_age_restricted_items` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_dr_client_status_idx` (`business_client_id`,`status`),
  KEY `ug_dr_driver_status_idx` (`assigned_driver_id`,`status`),
  KEY `urban_goodz_dedicated_routes_assigned_driver_id_index` (`assigned_driver_id`),
  KEY `urban_goodz_dedicated_routes_created_by_index` (`created_by`),
  CONSTRAINT `ug_rt_client_fk` FOREIGN KEY (`business_client_id`) REFERENCES `urban_goodz_business_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_demand_signals`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_demand_signals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned DEFAULT NULL,
  `query_text` text COLLATE utf8mb4_unicode_ci,
  `requested_item` text COLLATE utf8mb4_unicode_ci,
  `requested_vendor` text COLLATE utf8mb4_unicode_ci,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'search',
  `matched_entity_id` bigint unsigned DEFAULT NULL,
  `matched_product_id` bigint unsigned DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  `demand_count` int NOT NULL DEFAULT '1',
  `opportunity_score` int NOT NULL DEFAULT '0',
  `converted_to_business_id` bigint unsigned DEFAULT NULL,
  `converted_to_product_id` bigint unsigned DEFAULT NULL,
  `converted_to_order_anywhere_request_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_discovery_searches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_discovery_searches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `query` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_count` int DEFAULT NULL,
  `was_fulfilled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_dispatch_commissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_dispatch_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dispatch_company_id` bigint unsigned NOT NULL,
  `dispatcher_id` bigint unsigned DEFAULT NULL,
  `load_id` bigint unsigned NOT NULL,
  `load_payout` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_disp_comm_company_status_idx` (`dispatch_company_id`,`status`),
  KEY `ug_disp_comm_dispatcher_status_idx` (`dispatcher_id`,`status`),
  KEY `urban_goodz_dispatch_commissions_dispatch_company_id_index` (`dispatch_company_id`),
  KEY `urban_goodz_dispatch_commissions_dispatcher_id_index` (`dispatcher_id`),
  KEY `urban_goodz_dispatch_commissions_load_id_index` (`load_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_driver_earnings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_driver_earnings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_man_id` bigint unsigned NOT NULL,
  `business_client_job_id` bigint unsigned DEFAULT NULL,
  `package_id` bigint unsigned DEFAULT NULL,
  `dedicated_route_id` bigint unsigned DEFAULT NULL,
  `earning_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `description` text COLLATE utf8mb4_unicode_ci,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_earn_pkg_fk` (`package_id`),
  KEY `ug_earn_dm_status_idx` (`delivery_man_id`,`status`),
  KEY `ug_earn_route_type_idx` (`dedicated_route_id`,`earning_type`),
  KEY `urban_goodz_driver_earnings_delivery_man_id_index` (`delivery_man_id`),
  KEY `urban_goodz_driver_earnings_approved_by_index` (`approved_by`),
  KEY `ug_earn_bcj_idx` (`business_client_job_id`),
  KEY `ug_earn_bcj_idem_idx` (`delivery_man_id`,`business_client_job_id`,`earning_type`),
  CONSTRAINT `ug_earn_bcj_fk` FOREIGN KEY (`business_client_job_id`) REFERENCES `urban_goodz_business_client_jobs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_earn_pkg_fk` FOREIGN KEY (`package_id`) REFERENCES `urban_goodz_route_packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_earn_route_fk` FOREIGN KEY (`dedicated_route_id`) REFERENCES `urban_goodz_dedicated_routes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_driver_payout_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_driver_payout_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_man_id` bigint unsigned NOT NULL,
  `payout_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_amount` decimal(12,2) NOT NULL,
  `instant_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `driver_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_payout_dm_status_idx` (`delivery_man_id`,`status`),
  KEY `urban_goodz_driver_payout_requests_delivery_man_id_index` (`delivery_man_id`),
  KEY `urban_goodz_driver_payout_requests_approved_by_index` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_earn_money_applications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_earn_money_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `opportunity_id` bigint unsigned NOT NULL,
  `applicant_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applicant_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_earn_money_applications_opportunity_id_foreign` (`opportunity_id`),
  CONSTRAINT `urban_goodz_earn_money_applications_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `urban_goodz_earn_money_opportunities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_earn_money_opportunities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_earn_money_opportunities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'referral',
  `reward_amount` decimal(12,2) DEFAULT NULL,
  `reward_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `terms` text COLLATE utf8mb4_unicode_ci,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_events`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `organizer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organizer_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_price` decimal(12,2) DEFAULT NULL,
  `capacity` int DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_files`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint unsigned DEFAULT NULL,
  `owner_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `visibility` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer_private',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_files_owner_type_owner_id_index` (`owner_type`,`owner_id`),
  KEY `urban_goodz_files_owner_id_index` (`owner_id`),
  KEY `urban_goodz_files_owner_type_index` (`owner_type`),
  KEY `urban_goodz_files_file_category_index` (`file_category`),
  KEY `urban_goodz_files_uploaded_by_index` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_import_batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_import_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_query` text COLLATE utf8mb4_unicode_ci,
  `source_platforms` text COLLATE utf8mb4_unicode_ci,
  `total_found` int NOT NULL DEFAULT '0',
  `total_imported` int NOT NULL DEFAULT '0',
  `total_needs_review` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_id` bigint unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_load_board_loads`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_load_board_loads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `load_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `origin_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_lat` decimal(10,7) DEFAULT NULL,
  `origin_lng` decimal(10,7) DEFAULT NULL,
  `origin_ready_at` timestamp NULL DEFAULT NULL,
  `destination_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_zip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_lat` decimal(10,7) DEFAULT NULL,
  `destination_lng` decimal(10,7) DEFAULT NULL,
  `destination_due_at` timestamp NULL DEFAULT NULL,
  `distance_miles` decimal(10,2) DEFAULT NULL,
  `estimated_duration_minutes` int DEFAULT NULL,
  `payout_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payout_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'flat',
  `rate_per_mile` decimal(8,2) DEFAULT NULL,
  `load_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight_lbs` decimal(10,2) DEFAULT NULL,
  `length_ft` decimal(8,2) DEFAULT NULL,
  `pieces` int DEFAULT NULL,
  `commodity_description` text COLLATE utf8mb4_unicode_ci,
  `special_requirements` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_hazmat` tinyint(1) NOT NULL DEFAULT '0',
  `is_temperature_controlled` tinyint(1) NOT NULL DEFAULT '0',
  `temperature_min_f` decimal(6,2) DEFAULT NULL,
  `temperature_max_f` decimal(6,2) DEFAULT NULL,
  `requires_liftgate` tinyint(1) NOT NULL DEFAULT '0',
  `requires_pallet_jack` tinyint(1) NOT NULL DEFAULT '0',
  `is_team_load` tinyint(1) NOT NULL DEFAULT '0',
  `is_expedited` tinyint(1) NOT NULL DEFAULT '0',
  `shipper_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipper_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consignee_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consignee_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_driver_id` bigint unsigned DEFAULT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `delivery_proof` text COLLATE utf8mb4_unicode_ci,
  `business_client_id` bigint unsigned DEFAULT NULL,
  `dispatch_company_id` bigint unsigned DEFAULT NULL,
  `dispatcher_id` bigint unsigned DEFAULT NULL,
  `dispatch_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unassigned',
  `commission_amount` decimal(10,2) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_load_board_loads_status_origin_state_index` (`status`,`origin_state`),
  KEY `urban_goodz_load_board_loads_status_destination_state_index` (`status`,`destination_state`),
  KEY `urban_goodz_load_board_loads_provider_external_id_index` (`provider`,`external_id`),
  KEY `urban_goodz_load_board_loads_assigned_driver_id_index` (`assigned_driver_id`),
  KEY `urban_goodz_load_board_loads_external_id_index` (`external_id`),
  KEY `urban_goodz_load_board_loads_dispatch_company_id_index` (`dispatch_company_id`),
  KEY `urban_goodz_load_board_loads_dispatcher_id_index` (`dispatcher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_logistics_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_logistics_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_by` datetime DEFAULT NULL,
  `deliver_by` datetime DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_driver_id` bigint unsigned DEFAULT NULL,
  `offer_amount` decimal(12,2) DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_logistics_jobs_job_number_unique` (`job_number`),
  KEY `urban_goodz_logistics_jobs_assigned_driver_id_foreign` (`assigned_driver_id`),
  CONSTRAINT `urban_goodz_logistics_jobs_assigned_driver_id_foreign` FOREIGN KEY (`assigned_driver_id`) REFERENCES `delivery_men` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_manifests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_manifests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_client_id` bigint unsigned NOT NULL,
  `manifest_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manifest_session_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_location_id` bigint unsigned DEFAULT NULL,
  `pickup_location_text` text COLLATE utf8mb4_unicode_ci,
  `service_date` date DEFAULT NULL,
  `service_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `total_packages` int NOT NULL DEFAULT '0',
  `scanned_packages` int NOT NULL DEFAULT '0',
  `valid_packages` int NOT NULL DEFAULT '0',
  `invalid_packages` int NOT NULL DEFAULT '0',
  `generated_routes_count` int NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_manifests_manifest_session_id_unique` (`manifest_session_id`),
  KEY `ug_mnf_location_fk` (`pickup_location_id`),
  KEY `ug_mnf_client_status_idx` (`business_client_id`,`status`),
  KEY `urban_goodz_manifests_created_by_index` (`created_by`),
  KEY `urban_goodz_manifests_approved_by_index` (`approved_by`),
  CONSTRAINT `ug_mnf_client_fk` FOREIGN KEY (`business_client_id`) REFERENCES `urban_goodz_business_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ug_mnf_location_fk` FOREIGN KEY (`pickup_location_id`) REFERENCES `urban_goodz_business_client_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_measurement_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_measurement_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned DEFAULT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `tailor_id` bigint unsigned DEFAULT NULL,
  `measurement_profile_id` bigint unsigned DEFAULT NULL,
  `preferred_fit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `height` decimal(8,2) DEFAULT NULL,
  `chest_bust` decimal(8,2) DEFAULT NULL,
  `waist` decimal(8,2) DEFAULT NULL,
  `hips` decimal(8,2) DEFAULT NULL,
  `inseam` decimal(8,2) DEFAULT NULL,
  `sleeve_length` decimal(8,2) DEFAULT NULL,
  `shoulder_width` decimal(8,2) DEFAULT NULL,
  `neck` decimal(8,2) DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `front_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `front_photo_file_id` bigint unsigned DEFAULT NULL,
  `side_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `side_photo_file_id` bigint unsigned DEFAULT NULL,
  `back_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `back_photo_file_id` bigint unsigned DEFAULT NULL,
  `face_blur_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `face_blur_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `privacy_review_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform_measurement_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vendor_review_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_measurement_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `payment_required` tinyint(1) NOT NULL DEFAULT '0',
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waived',
  `free_tester_mode` tinyint(1) NOT NULL DEFAULT '1',
  `measurement_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `review_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `tailor_notes` text COLLATE utf8mb4_unicode_ci,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `quote_amount` decimal(10,2) DEFAULT NULL,
  `mockup_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `corrected_measurements` text COLLATE utf8mb4_unicode_ci,
  `item_wanted` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `consent_to_share_photos` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_measurement_requests_customer_id_index` (`customer_id`),
  KEY `urban_goodz_measurement_requests_vendor_id_index` (`vendor_id`),
  KEY `urban_goodz_measurement_requests_tailor_id_index` (`tailor_id`),
  KEY `urban_goodz_measurement_requests_measurement_profile_id_index` (`measurement_profile_id`),
  KEY `urban_goodz_measurement_requests_front_photo_file_id_foreign` (`front_photo_file_id`),
  KEY `urban_goodz_measurement_requests_side_photo_file_id_foreign` (`side_photo_file_id`),
  KEY `urban_goodz_measurement_requests_back_photo_file_id_foreign` (`back_photo_file_id`),
  CONSTRAINT `urban_goodz_measurement_requests_back_photo_file_id_foreign` FOREIGN KEY (`back_photo_file_id`) REFERENCES `urban_goodz_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `urban_goodz_measurement_requests_front_photo_file_id_foreign` FOREIGN KEY (`front_photo_file_id`) REFERENCES `urban_goodz_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `urban_goodz_measurement_requests_side_photo_file_id_foreign` FOREIGN KEY (`side_photo_file_id`) REFERENCES `urban_goodz_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_medical_courier_custody_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_medical_courier_custody_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_id` bigint unsigned NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `handler_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `logged_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_medical_courier_custody_logs_job_id_foreign` (`job_id`),
  CONSTRAINT `urban_goodz_medical_courier_custody_logs_job_id_foreign` FOREIGN KEY (`job_id`) REFERENCES `urban_goodz_medical_courier_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_medical_courier_jobs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_medical_courier_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_facility_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_lat` decimal(10,7) DEFAULT NULL,
  `pickup_lng` decimal(10,7) DEFAULT NULL,
  `delivery_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_facility_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_lat` decimal(10,7) DEFAULT NULL,
  `delivery_lng` decimal(10,7) DEFAULT NULL,
  `distance_miles` double DEFAULT NULL,
  `payout_amount` decimal(10,2) DEFAULT NULL,
  `payout_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'flat',
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `specimen_count` int NOT NULL DEFAULT '1',
  `temperature_min_f` double DEFAULT NULL,
  `temperature_max_f` double DEFAULT NULL,
  `pickup_window_start` timestamp NULL DEFAULT NULL,
  `pickup_window_end` timestamp NULL DEFAULT NULL,
  `delivery_window_start` timestamp NULL DEFAULT NULL,
  `delivery_window_end` timestamp NULL DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `specimen_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_refrigeration` tinyint(1) NOT NULL DEFAULT '0',
  `is_biological_hazard` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_driver_id` bigint unsigned DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_medical_courier_jobs_job_number_unique` (`job_number`),
  KEY `urban_goodz_medical_courier_jobs_assigned_driver_id_foreign` (`assigned_driver_id`),
  CONSTRAINT `urban_goodz_medical_courier_jobs_assigned_driver_id_foreign` FOREIGN KEY (`assigned_driver_id`) REFERENCES `delivery_men` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_medical_custody_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_medical_custody_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint unsigned NOT NULL,
  `custody_event` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_user_id` bigint unsigned DEFAULT NULL,
  `from_user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_user_id` bigint unsigned DEFAULT NULL,
  `to_user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temperature` decimal(6,2) DEFAULT NULL,
  `seal_intact` tinyint(1) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_cust_pkg_event_idx` (`package_id`,`custody_event`),
  KEY `urban_goodz_medical_custody_logs_from_user_id_index` (`from_user_id`),
  KEY `urban_goodz_medical_custody_logs_to_user_id_index` (`to_user_id`),
  CONSTRAINT `ug_cust_pkg_fk` FOREIGN KEY (`package_id`) REFERENCES `urban_goodz_route_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_order_anywhere_card_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_order_anywhere_card_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_anywhere_request_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_card_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_cardholder_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requested',
  `card_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'virtual',
  `last4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spending_limit` decimal(12,2) NOT NULL,
  `buffer_amount` decimal(12,2) DEFAULT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `authorized_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `captured_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `refunded_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `merchant_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `merchant_category_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allowed_merchant` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allowed_mccs` json DEFAULT NULL,
  `single_use` tinyint(1) NOT NULL DEFAULT '1',
  `usable_from` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `frozen_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `card_req_oa_status_idx` (`order_anywhere_request_id`,`card_status`),
  KEY `card_req_driver_status_idx` (`delivery_man_id`,`card_status`),
  KEY `car_oa_req_idx` (`order_anywhere_request_id`),
  KEY `car_driver_idx` (`delivery_man_id`),
  KEY `car_provider_idx` (`provider`),
  KEY `car_prov_ref_idx` (`provider_reference`),
  KEY `car_status_idx` (`card_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_package_scans`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_package_scans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint unsigned NOT NULL,
  `scan_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scanned_by` bigint unsigned DEFAULT NULL,
  `scanner_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'driver',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `photo` text COLLATE utf8mb4_unicode_ci,
  `signature` text COLLATE utf8mb4_unicode_ci,
  `exception_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_scan_pkg_type_idx` (`package_id`,`scan_type`),
  KEY `urban_goodz_package_scans_scanned_by_index` (`scanned_by`),
  CONSTRAINT `ug_scan_pkg_fk` FOREIGN KEY (`package_id`) REFERENCES `urban_goodz_route_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_payment_ledgers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_payment_ledgers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ledger_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `feature` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payable_id` bigint unsigned NOT NULL,
  `event_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'credit',
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `created_by_admin_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_payment_ledgers_ledger_number_unique` (`ledger_number`),
  UNIQUE KEY `urban_goodz_payment_ledgers_idempotency_key_unique` (`idempotency_key`),
  KEY `urban_goodz_payment_ledgers_payable_type_payable_id_index` (`payable_type`,`payable_id`),
  KEY `urban_goodz_payment_ledgers_feature_index` (`feature`),
  KEY `urban_goodz_payment_ledgers_event_type_index` (`event_type`),
  KEY `urban_goodz_payment_ledgers_payment_status_index` (`payment_status`),
  KEY `urban_goodz_payment_ledgers_reference_index` (`reference`),
  KEY `urban_goodz_payment_ledgers_customer_id_index` (`customer_id`),
  KEY `urban_goodz_payment_ledgers_vendor_id_index` (`vendor_id`),
  KEY `urban_goodz_payment_ledgers_delivery_man_id_index` (`delivery_man_id`),
  KEY `urban_goodz_payment_ledgers_created_by_admin_id_index` (`created_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_payment_splits`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_payment_splits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ledger_id` bigint unsigned NOT NULL,
  `feature` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payable_id` bigint unsigned NOT NULL,
  `recipient_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_id` bigint unsigned DEFAULT NULL,
  `split_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_payment_splits_idempotency_key_unique` (`idempotency_key`),
  KEY `urban_goodz_payment_splits_payable_type_payable_id_index` (`payable_type`,`payable_id`),
  KEY `urban_goodz_payment_splits_ledger_id_index` (`ledger_id`),
  KEY `urban_goodz_payment_splits_feature_index` (`feature`),
  KEY `urban_goodz_payment_splits_recipient_type_index` (`recipient_type`),
  KEY `urban_goodz_payment_splits_recipient_id_index` (`recipient_id`),
  KEY `urban_goodz_payment_splits_split_type_index` (`split_type`),
  KEY `urban_goodz_payment_splits_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_payment_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_payment_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payable_id` bigint unsigned NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `environment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_minor` decimal(12,0) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `merchant_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_payment_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_payment_link_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_transaction_id` bigint unsigned DEFAULT NULL,
  `request_payload_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_summary` json DEFAULT NULL,
  `failure_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_message` text COLLATE utf8mb4_unicode_ci,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_payment_transactions_idempotency_key_unique` (`idempotency_key`),
  KEY `urban_goodz_payment_transactions_payable_type_payable_id_index` (`payable_type`,`payable_id`),
  KEY `ug_pay_tx_provider_ref_idx` (`provider`,`provider_reference`),
  KEY `urban_goodz_payment_transactions_provider_index` (`provider`),
  KEY `urban_goodz_payment_transactions_environment_index` (`environment`),
  KEY `urban_goodz_payment_transactions_transaction_type_index` (`transaction_type`),
  KEY `urban_goodz_payment_transactions_internal_status_index` (`internal_status`),
  KEY `urban_goodz_payment_transactions_merchant_reference_index` (`merchant_reference`),
  KEY `urban_goodz_payment_transactions_provider_reference_index` (`provider_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_plus_memberships`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_plus_memberships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'basic',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `monthly_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subscribed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `benefits` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_rental_assets`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_rental_assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_type_slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `asset_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `make` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plate_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photos` json DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `daily_rate` decimal(12,2) DEFAULT NULL,
  `hourly_rate` decimal(12,2) DEFAULT NULL,
  `deposit_amount` decimal(12,2) DEFAULT NULL,
  `mileage_limit` int DEFAULT NULL,
  `pickup_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_rental_assets_store_id_foreign` (`store_id`),
  KEY `urban_goodz_rental_assets_vendor_id_foreign` (`vendor_id`),
  CONSTRAINT `urban_goodz_rental_assets_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `urban_goodz_rental_assets_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_rental_bookings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_rental_bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rental_asset_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `deposit_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verification_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_amount` decimal(12,2) DEFAULT NULL,
  `deposit_amount` decimal(12,2) DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `customer_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_rental_bookings_rental_asset_id_foreign` (`rental_asset_id`),
  KEY `urban_goodz_rental_bookings_customer_id_foreign` (`customer_id`),
  CONSTRAINT `urban_goodz_rental_bookings_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `urban_goodz_rental_bookings_rental_asset_id_foreign` FOREIGN KEY (`rental_asset_id`) REFERENCES `urban_goodz_rental_assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_rental_inspections`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_rental_inspections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rental_booking_id` bigint unsigned NOT NULL,
  `inspection_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photos` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `damage_found` tinyint(1) NOT NULL DEFAULT '0',
  `damage_amount` decimal(12,2) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `inspected_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_rental_inspections_rental_booking_id_foreign` (`rental_booking_id`),
  CONSTRAINT `urban_goodz_rental_inspections_rental_booking_id_foreign` FOREIGN KEY (`rental_booking_id`) REFERENCES `urban_goodz_rental_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_route_assignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_route_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dedicated_route_id` bigint unsigned NOT NULL,
  `delivery_man_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned',
  `assigned_by` bigint unsigned DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `route_started_at` timestamp NULL DEFAULT NULL,
  `route_completed_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ug_assign_route_dm_idx` (`dedicated_route_id`,`delivery_man_id`),
  KEY `urban_goodz_route_assignments_delivery_man_id_index` (`delivery_man_id`),
  KEY `urban_goodz_route_assignments_assigned_by_index` (`assigned_by`),
  CONSTRAINT `ug_assign_route_fk` FOREIGN KEY (`dedicated_route_id`) REFERENCES `urban_goodz_dedicated_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_route_batches`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_route_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dedicated_route_id` bigint unsigned NOT NULL,
  `batch_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `package_count` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_driver_id` bigint unsigned DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ug_batch_route_num_uniq` (`dedicated_route_id`,`batch_number`),
  KEY `urban_goodz_route_batches_assigned_driver_id_index` (`assigned_driver_id`),
  CONSTRAINT `ug_batch_route_fk` FOREIGN KEY (`dedicated_route_id`) REFERENCES `urban_goodz_dedicated_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_route_optimization_stops`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_route_optimization_stops` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dedicated_route_id` bigint unsigned NOT NULL,
  `package_id` bigint unsigned NOT NULL,
  `stop_order` int NOT NULL DEFAULT '0',
  `estimated_distance_from_prev` decimal(10,2) DEFAULT NULL,
  `estimated_duration_from_prev` int DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ug_opt_route_order_uniq` (`dedicated_route_id`,`stop_order`),
  KEY `ug_opt_pkg_fk` (`package_id`),
  KEY `ug_opt_route_pkg_idx` (`dedicated_route_id`,`package_id`),
  CONSTRAINT `ug_opt_pkg_fk` FOREIGN KEY (`package_id`) REFERENCES `urban_goodz_route_packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ug_opt_route_fk` FOREIGN KEY (`dedicated_route_id`) REFERENCES `urban_goodz_dedicated_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_route_packages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_route_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dedicated_route_id` bigint unsigned DEFAULT NULL,
  `route_batch_id` bigint unsigned DEFAULT NULL,
  `business_client_id` bigint unsigned NOT NULL,
  `manifest_id` bigint unsigned DEFAULT NULL,
  `manifest_session_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scanned_by` bigint unsigned DEFAULT NULL,
  `scanned_at` timestamp NULL DEFAULT NULL,
  `tracking_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_location_id` bigint unsigned DEFAULT NULL,
  `pickup_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_contact_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_address` text COLLATE utf8mb4_unicode_ci,
  `dropoff_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dropoff_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `dropoff_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dropoff_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dropoff_zip` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stop_order` int NOT NULL DEFAULT '0',
  `dropoff_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dropoff_lat` decimal(10,7) DEFAULT NULL,
  `dropoff_lng` decimal(10,7) DEFAULT NULL,
  `delivery_window_start` datetime DEFAULT NULL,
  `delivery_window_end` datetime DEFAULT NULL,
  `package_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'parcel',
  `weight` decimal(10,2) DEFAULT NULL,
  `weight_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lbs',
  `dimensions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `requires_signature` tinyint(1) NOT NULL DEFAULT '0',
  `requires_photo` tinyint(1) NOT NULL DEFAULT '0',
  `requires_custody` tinyint(1) NOT NULL DEFAULT '0',
  `temperature_requirement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age_restricted` tinyint(1) NOT NULL DEFAULT '0',
  `requires_id_verification` tinyint(1) NOT NULL DEFAULT '0',
  `no_contactless_delivery` tinyint(1) NOT NULL DEFAULT '0',
  `delivery_completion_locked_until_verified` tinyint(1) NOT NULL DEFAULT '0',
  `admin_review_required_on_failure` tinyint(1) NOT NULL DEFAULT '0',
  `age_verification_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age_verification_refusal_reason` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age_verification_driver_notes` text COLLATE utf8mb4_unicode_ci,
  `age_verified_at` timestamp NULL DEFAULT NULL,
  `age_verified_by_driver_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `delivery_result` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivered_to_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivered_location_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `return_required` tinyint(1) NOT NULL DEFAULT '0',
  `returned_at` timestamp NULL DEFAULT NULL,
  `return_location` text COLLATE utf8mb4_unicode_ci,
  `payout_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payout_eligible` tinyint(1) NOT NULL DEFAULT '0',
  `geocode_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `geocode_confidence` decimal(5,2) DEFAULT NULL,
  `pickup_scanned_at` timestamp NULL DEFAULT NULL,
  `pickup_scanned_by` bigint unsigned DEFAULT NULL,
  `pickup_lat` decimal(10,7) DEFAULT NULL,
  `pickup_lng` decimal(10,7) DEFAULT NULL,
  `dropoff_scanned_at` timestamp NULL DEFAULT NULL,
  `dropoff_scanned_by` bigint unsigned DEFAULT NULL,
  `proof_photo` text COLLATE utf8mb4_unicode_ci,
  `recipient_signature` text COLLATE utf8mb4_unicode_ci,
  `exception_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_route_packages_tracking_id_unique` (`tracking_id`),
  UNIQUE KEY `urban_goodz_route_packages_barcode_unique` (`barcode`),
  UNIQUE KEY `urban_goodz_route_packages_qr_code_unique` (`qr_code`),
  KEY `ug_pkg_route_status_idx` (`dedicated_route_id`,`status`),
  KEY `ug_pkg_client_status_idx` (`business_client_id`,`status`),
  KEY `ug_pkg_batch_status_idx` (`route_batch_id`,`status`),
  KEY `urban_goodz_route_packages_route_batch_id_index` (`route_batch_id`),
  KEY `urban_goodz_route_packages_pickup_location_id_index` (`pickup_location_id`),
  KEY `urban_goodz_route_packages_pickup_scanned_by_index` (`pickup_scanned_by`),
  KEY `urban_goodz_route_packages_dropoff_scanned_by_index` (`dropoff_scanned_by`),
  KEY `ug_pkg_manifest_status_idx` (`manifest_id`,`status`),
  CONSTRAINT `ug_pkg_client_fk` FOREIGN KEY (`business_client_id`) REFERENCES `urban_goodz_business_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ug_pkg_manifest_fk` FOREIGN KEY (`manifest_id`) REFERENCES `urban_goodz_manifests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ug_pkg_route_fk` FOREIGN KEY (`dedicated_route_id`) REFERENCES `urban_goodz_dedicated_routes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_service_providers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_service_providers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `service_areas` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urban_goodz_service_providers_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_service_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_service_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_vendor_id` bigint unsigned DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `preferred_dates` json DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_service_requests_assigned_vendor_id_foreign` (`assigned_vendor_id`),
  CONSTRAINT `urban_goodz_service_requests_assigned_vendor_id_foreign` FOREIGN KEY (`assigned_vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_sourced_businesses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_sourced_businesses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `business_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  `module_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_ids` text COLLATE utf8mb4_unicode_ci,
  `tags` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_links` text COLLATE utf8mb4_unicode_ci,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  `zone_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_launch_market` tinyint(1) NOT NULL DEFAULT '0',
  `is_nationwide` tinyint(1) NOT NULL DEFAULT '0',
  `is_worldwide` tinyint(1) NOT NULL DEFAULT '0',
  `is_black_owned` tinyint(1) DEFAULT NULL,
  `is_woman_owned` tinyint(1) DEFAULT NULL,
  `is_local_business` tinyint(1) NOT NULL DEFAULT '1',
  `fulfillment_modes` text COLLATE utf8mb4_unicode_ci,
  `onboarding_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public_sourced',
  `source_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ai_sourced',
  `source_urls` text COLLATE utf8mb4_unicode_ci,
  `data_confidence_score` int NOT NULL DEFAULT '0',
  `demand_score` int NOT NULL DEFAULT '0',
  `last_verified_at` timestamp NULL DEFAULT NULL,
  `admin_review_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_by_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_sourced_businesses_slug_index` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_sourced_images`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_sourced_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `image_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `local_path` text COLLATE utf8mb4_unicode_ci,
  `source_url` text COLLATE utf8mb4_unicode_ci,
  `source_platform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text` text COLLATE utf8mb4_unicode_ci,
  `caption` text COLLATE utf8mb4_unicode_ci,
  `rights_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown_review_required',
  `review_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_sourced_products`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_sourced_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sourced_business_id` bigint unsigned DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `module_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `subcategory_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `full_description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(24,2) DEFAULT NULL,
  `price_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `stock_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `item_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'product',
  `images` text COLLATE utf8mb4_unicode_ci,
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_url` text COLLATE utf8mb4_unicode_ci,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_confidence` int NOT NULL DEFAULT '0',
  `fulfillment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_quote` tinyint(1) NOT NULL DEFAULT '0',
  `requires_admin_review` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `import_batch_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urban_goodz_spotlight_businesses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urban_goodz_spotlight_businesses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `featured_until` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `urban_goodz_spotlight_businesses_vendor_id_foreign` (`vendor_id`),
  CONSTRAINT `urban_goodz_spotlight_businesses_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_files`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_files_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_infos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_infos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `f_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `deliveryman_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `data` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `f_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_phone_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `interest` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cm_firebase_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `order_count` int NOT NULL DEFAULT '0',
  `login_medium` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone_id` bigint unsigned DEFAULT NULL,
  `wallet_balance` decimal(24,3) NOT NULL DEFAULT '0.000',
  `loyalty_point` decimal(24,3) NOT NULL DEFAULT '0.000',
  `ref_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_language_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `ref_by` bigint unsigned DEFAULT NULL,
  `temp_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_ids` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `is_from_pos` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  UNIQUE KEY `users_ref_code_unique` (`ref_code`),
  KEY `users_zone_id_index` (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle_brands`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle_categories`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle_drivers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_drivers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` bigint unsigned DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_image` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle_identities`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_identities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_id` bigint unsigned DEFAULT NULL,
  `provider_id` bigint unsigned DEFAULT NULL,
  `vin_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_plate_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle_reviews`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `trip_id` bigint unsigned NOT NULL,
  `vehicle_id` bigint unsigned NOT NULL,
  `vehicle_identity_id` bigint unsigned DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `comment` mediumtext COLLATE utf8mb4_unicode_ci,
  `attachment` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `reply` mediumtext COLLATE utf8mb4_unicode_ci,
  `review_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicles`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `thumbnail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images` text COLLATE utf8mb4_unicode_ci,
  `zone_id` bigint unsigned DEFAULT NULL,
  `provider_id` bigint unsigned DEFAULT NULL,
  `brand_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `engine_capacity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `engine_power` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seating_capacity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `air_condition` tinyint(1) NOT NULL DEFAULT '0',
  `fuel_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transmission_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `multiple_vehicles` tinyint(1) NOT NULL DEFAULT '0',
  `trip_hourly` tinyint(1) NOT NULL DEFAULT '0',
  `trip_distance` tinyint(1) NOT NULL DEFAULT '0',
  `trip_day_wise` double NOT NULL DEFAULT '0',
  `day_wise_price` decimal(23,8) NOT NULL DEFAULT '0.00000000',
  `hourly_price` decimal(23,8) NOT NULL DEFAULT '0.00000000',
  `distance_price` decimal(23,8) NOT NULL DEFAULT '0.00000000',
  `discount_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_price` decimal(23,8) NOT NULL DEFAULT '0.00000000',
  `tag` text COLLATE utf8mb4_unicode_ci,
  `documents` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `new_tag` tinyint(1) NOT NULL DEFAULT '1',
  `total_trip` int NOT NULL DEFAULT '0',
  `avg_rating` decimal(8,2) NOT NULL DEFAULT '0.00',
  `rating` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_reviews` int NOT NULL DEFAULT '0',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendor_employees`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendor_employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `f_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `l_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_role_id` bigint unsigned NOT NULL,
  `vendor_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firebase_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_logged_in` tinyint(1) NOT NULL DEFAULT '1',
  `login_remember_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_employees_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vendors`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `f_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `l_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rejection_note` text COLLATE utf8mb4_unicode_ci,
  `branch` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `holder_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `firebase_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_remember_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendors_phone_unique` (`phone`),
  UNIQUE KEY `vendors_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `visitor_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `visitor_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visitor_log_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `visitor_log_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `visit_count` int NOT NULL DEFAULT '0',
  `order_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_bonuses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_bonuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `bonus_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonus_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `minimum_add_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `maximum_bonus_amount` double(23,3) NOT NULL DEFAULT '0.000',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `transaction_ref` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(8,2) NOT NULL DEFAULT '0.00',
  `payment_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallet_transactions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallet_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `transaction_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credit` decimal(24,3) NOT NULL DEFAULT '0.000',
  `debit` decimal(24,3) NOT NULL DEFAULT '0.000',
  `admin_bonus` decimal(24,3) NOT NULL DEFAULT '0.000',
  `balance` decimal(24,3) NOT NULL DEFAULT '0.000',
  `transaction_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `websockets_statistics_entries`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `websockets_statistics_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `peak_connection_count` int NOT NULL,
  `websocket_message_count` int NOT NULL,
  `api_message_count` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wishlists`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wishlists_user_id_index` (`user_id`),
  KEY `wishlists_item_id_index` (`item_id`),
  KEY `wishlists_store_id_index` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `withdraw_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdraw_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vendor_id` bigint unsigned DEFAULT NULL,
  `admin_id` bigint unsigned DEFAULT NULL,
  `transaction_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(23,3) NOT NULL DEFAULT '0.000',
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `delivery_man_id` bigint unsigned DEFAULT NULL,
  `withdrawal_method_id` bigint unsigned DEFAULT NULL,
  `withdrawal_method_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `sender_note` text COLLATE utf8mb4_unicode_ci,
  `user_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `withdrawal_methods`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `withdrawal_methods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `method_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_fields` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint NOT NULL DEFAULT '0',
  `is_active` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `zones`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `coordinates` polygon NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `store_wise_topic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_wise_topic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deliveryman_wise_topic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rider_wise_topic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cash_on_delivery` tinyint(1) NOT NULL DEFAULT '0',
  `digital_payment` tinyint(1) NOT NULL DEFAULT '0',
  `increased_delivery_fee` double(8,2) NOT NULL DEFAULT '0.00',
  `increased_delivery_fee_status` tinyint(1) NOT NULL DEFAULT '0',
  `increase_delivery_charge_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offline_payment` tinyint(1) NOT NULL DEFAULT '0',
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `zones_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'urbakkej_urbangoodzdelivery'
--

--
-- Dumping routines for database 'urbakkej_urbangoodzdelivery'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-23  7:07:08
