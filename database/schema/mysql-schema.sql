-- Reference structure only. Install with Laravel migrations, not by importing this snapshot.

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
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `access_authorizations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned NOT NULL,
  `status` enum('active','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `valid_from` datetime(6) NOT NULL,
  `valid_until` datetime(6) DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `granted_by` bigint unsigned NOT NULL,
  `revoked_by` bigint unsigned DEFAULT NULL,
  `revoked_at` timestamp(6) NULL DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `access_authorizations_granted_by_foreign` (`granted_by`),
  KEY `access_authorizations_revoked_by_foreign` (`revoked_by`),
  KEY `authorizations_effective_index` (`person_id`,`status`,`valid_from`,`valid_until`),
  CONSTRAINT `access_authorizations_granted_by_foreign` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `access_authorizations_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `access_authorizations_revoked_by_foreign` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `authorization_valid_interval` CHECK (((`valid_until` is null) or (`valid_until` > `valid_from`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `access_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scan_attempt_id` bigint unsigned NOT NULL,
  `person_id` bigint unsigned NOT NULL,
  `credential_assignment_id` bigint unsigned NOT NULL,
  `guard_id` bigint unsigned NOT NULL,
  `gate_id` bigint unsigned NOT NULL,
  `direction` enum('entry','exit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('physical','simulated') COLLATE utf8mb4_unicode_ci NOT NULL,
  `policy_version` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identity_method` enum('photo','campus_document','nfc_scan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence_notice` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmed_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_events_public_id_unique` (`public_id`),
  UNIQUE KEY `access_events_scan_attempt_id_unique` (`scan_attempt_id`),
  KEY `access_events_credential_assignment_id_foreign` (`credential_assignment_id`),
  KEY `access_events_guard_id_foreign` (`guard_id`),
  KEY `access_events_gate_id_foreign` (`gate_id`),
  KEY `event_attempt_identity_fk` (`scan_attempt_id`,`person_id`,`credential_assignment_id`,`guard_id`,`gate_id`,`direction`,`source`),
  KEY `passage_duplicate_lookup` (`person_id`,`gate_id`,`direction`,`source`,`confirmed_at`),
  KEY `access_events_confirmed_at_id_index` (`confirmed_at`,`id`),
  KEY `event_source_clock` (`source`,`confirmed_at`,`id`),
  KEY `event_presence_lookup` (`source`,`person_id`,`confirmed_at`,`id`),
  KEY `event_source_gate_clock` (`source`,`gate_id`,`confirmed_at`,`id`),
  CONSTRAINT `access_events_credential_assignment_id_foreign` FOREIGN KEY (`credential_assignment_id`) REFERENCES `credential_assignments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `access_events_gate_id_foreign` FOREIGN KEY (`gate_id`) REFERENCES `gates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `access_events_guard_id_foreign` FOREIGN KEY (`guard_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `access_events_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `access_events_scan_attempt_id_foreign` FOREIGN KEY (`scan_attempt_id`) REFERENCES `scan_attempts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `event_attempt_identity_fk` FOREIGN KEY (`scan_attempt_id`, `person_id`, `credential_assignment_id`, `guard_id`, `gate_id`, `direction`, `source`) REFERENCES `scan_attempts` (`id`, `person_id`, `credential_assignment_id`, `guard_id`, `gate_id`, `direction`, `source`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anomaly_evidence` (
  `anomaly_flag_id` bigint unsigned NOT NULL,
  `scan_attempt_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`anomaly_flag_id`,`scan_attempt_id`),
  KEY `anomaly_evidence_scan_attempt_id_foreign` (`scan_attempt_id`),
  CONSTRAINT `anomaly_evidence_anomaly_flag_id_foreign` FOREIGN KEY (`anomaly_flag_id`) REFERENCES `anomaly_flags` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `anomaly_evidence_scan_attempt_id_foreign` FOREIGN KEY (`scan_attempt_id`) REFERENCES `scan_attempts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anomaly_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('physical','simulated') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_code` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_version` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `policy` json NOT NULL,
  `severity` enum('low','medium') COLLATE utf8mb4_unicode_ci NOT NULL,
  `anchor_attempt_id` bigint unsigned NOT NULL,
  `explanation` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `occurred_at` datetime(6) NOT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `anomaly_detection_identity` (`source`,`rule_code`,`rule_version`,`anchor_attempt_id`),
  UNIQUE KEY `anomaly_flags_public_id_unique` (`public_id`),
  KEY `anomaly_flags_anchor_attempt_id_foreign` (`anchor_attempt_id`),
  KEY `anomaly_source_clock` (`source`,`occurred_at`,`id`),
  CONSTRAINT `anomaly_flags_anchor_attempt_id_foreign` FOREIGN KEY (`anchor_attempt_id`) REFERENCES `scan_attempts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anomaly_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `anomaly_flag_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `status` enum('open','in_review','resolved','false_alarm') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `anomaly_reviews_actor_id_foreign` (`actor_id`),
  KEY `anomaly_reviews_anomaly_flag_id_id_index` (`anomaly_flag_id`,`id`),
  CONSTRAINT `anomaly_reviews_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `anomaly_reviews_anomaly_flag_id_foreign` FOREIGN KEY (`anomaly_flag_id`) REFERENCES `anomaly_flags` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` bigint unsigned DEFAULT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` bigint unsigned DEFAULT NULL,
  `outcome` enum('success','failure','denied') COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_id_created_at_index` (`actor_id`,`created_at`),
  KEY `audit_logs_target_type_target_id_index` (`target_type`,`target_id`),
  KEY `audit_logs_created_at_index` (`created_at`),
  KEY `audit_logs_action_index` (`action`),
  CONSTRAINT `audit_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campus_departments` (
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campus_programs` (
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `max_year_level` tinyint unsigned NOT NULL DEFAULT '4',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credential_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned NOT NULL,
  `credential_id` bigint unsigned NOT NULL,
  `assigned_by` bigint unsigned NOT NULL,
  `assigned_at` datetime(6) NOT NULL,
  `ended_at` datetime(6) DEFAULT NULL,
  `ended_by` bigint unsigned DEFAULT NULL,
  `end_reason` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_assignments_person_id_id_unique` (`person_id`,`id`),
  UNIQUE KEY `credential_assignments_credential_id_unique` (`credential_id`),
  KEY `credential_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `credential_assignments_ended_by_foreign` (`ended_by`),
  KEY `credential_assignments_person_id_assigned_at_index` (`person_id`,`assigned_at`),
  CONSTRAINT `credential_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `credential_assignments_credential_id_foreign` FOREIGN KEY (`credential_id`) REFERENCES `nfc_credentials` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `credential_assignments_ended_by_foreign` FOREIGN KEY (`ended_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `credential_assignments_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `credential_assignment_end_order` CHECK (((`ended_at` is null) or (`ended_at` >= `assigned_at`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credential_counters` (
  `kind` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_number` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`kind`),
  CONSTRAINT `credential_counter_kind` CHECK ((`kind` in (_utf8mb4'student',_utf8mb4'employee'))),
  CONSTRAINT `credential_counter_nonnegative` CHECK ((`last_number` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_profiles` (
  `person_id` bigint unsigned NOT NULL,
  `department` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`person_id`),
  CONSTRAINT `employee_profiles_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `archived_at` timestamp(6) NULL DEFAULT NULL,
  `record_version` bigint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `gates_code_unique` (`code`),
  KEY `gates_status_index` (`status`),
  KEY `gates_archived_at_status_index` (`archived_at`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guard_gate_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `guard_id` bigint unsigned NOT NULL,
  `gate_id` bigint unsigned NOT NULL,
  `starts_at` datetime(6) NOT NULL,
  `ends_at` datetime(6) DEFAULT NULL,
  `assigned_by` bigint unsigned NOT NULL,
  `revoked_by` bigint unsigned DEFAULT NULL,
  `revoked_at` timestamp(6) NULL DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guard_gate_assignments_gate_id_foreign` (`gate_id`),
  KEY `guard_gate_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `guard_gate_assignments_revoked_by_foreign` (`revoked_by`),
  KEY `guard_gate_effective_index` (`guard_id`,`gate_id`,`starts_at`,`ends_at`),
  CONSTRAINT `guard_gate_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `guard_gate_assignments_gate_id_foreign` FOREIGN KEY (`gate_id`) REFERENCES `gates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `guard_gate_assignments_guard_id_foreign` FOREIGN KEY (`guard_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `guard_gate_assignments_revoked_by_foreign` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assignment_valid_interval` CHECK (((`ends_at` is null) or (`ends_at` > `starts_at`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfc_credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_digest` binary(32) NOT NULL,
  `status` enum('pending','active','revoked','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `source` enum('physical','simulated') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `issuer_auth_version` bigint unsigned NOT NULL,
  `issuance_request_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `completion_digest` binary(32) NOT NULL,
  `issuer_session_digest` binary(32) NOT NULL,
  `person_version` bigint unsigned NOT NULL,
  `issuance_mode` enum('new','replace','lost') COLLATE utf8mb4_unicode_ci NOT NULL,
  `replaces_credential_id` bigint unsigned DEFAULT NULL,
  `pending_expires_at` datetime(6) NOT NULL,
  `read_back_at` datetime(6) DEFAULT NULL,
  `activated_at` datetime(6) DEFAULT NULL,
  `valid_until` datetime(6) DEFAULT NULL,
  `closed_at` datetime(6) DEFAULT NULL,
  `closed_by` bigint unsigned DEFAULT NULL,
  `close_reason` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfc_credentials_created_by_issuance_request_id_unique` (`created_by`,`issuance_request_id`),
  UNIQUE KEY `nfc_credentials_public_id_unique` (`public_id`),
  UNIQUE KEY `nfc_credentials_label_unique` (`label`),
  UNIQUE KEY `nfc_credentials_token_digest_unique` (`token_digest`),
  KEY `nfc_credentials_replaces_credential_id_foreign` (`replaces_credential_id`),
  KEY `nfc_credentials_closed_by_foreign` (`closed_by`),
  KEY `nfc_credentials_status_pending_expires_at_index` (`status`,`pending_expires_at`),
  KEY `nfc_credentials_source_status_created_at_index` (`source`,`status`,`created_at`),
  CONSTRAINT `nfc_credentials_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `nfc_credentials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `nfc_credentials_replaces_credential_id_foreign` FOREIGN KEY (`replaces_credential_id`) REFERENCES `nfc_credentials` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `credential_expiry_after_creation` CHECK (((`valid_until` is null) or (`valid_until` > `created_at`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `passage_corrections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_event_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `action` enum('note','void','reinstate') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passage_corrections_public_id_unique` (`public_id`),
  KEY `passage_corrections_actor_id_foreign` (`actor_id`),
  KEY `correction_decision_lookup` (`access_event_id`,`action`,`id`),
  CONSTRAINT `passage_corrections_access_event_id_foreign` FOREIGN KEY (`access_event_id`) REFERENCES `access_events` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `passage_corrections_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `persons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_type` enum('student','employee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `campus_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `archived_at` timestamp(6) NULL DEFAULT NULL,
  `record_version` bigint unsigned NOT NULL DEFAULT '1',
  `active_credential_assignment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `persons_person_type_campus_number_unique` (`person_type`,`campus_number`),
  UNIQUE KEY `persons_active_credential_assignment_id_unique` (`active_credential_assignment_id`),
  KEY `persons_person_type_status_index` (`person_type`,`status`),
  KEY `persons_archived_at_status_index` (`archived_at`,`status`),
  KEY `person_active_credential_owner_fk` (`id`,`active_credential_assignment_id`),
  CONSTRAINT `person_active_credential_owner_fk` FOREIGN KEY (`id`, `active_credential_assignment_id`) REFERENCES `credential_assignments` (`person_id`, `id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scan_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_id` bigint unsigned NOT NULL,
  `guard_auth_version` bigint unsigned NOT NULL,
  `session_digest` binary(32) NOT NULL,
  `gate_id` bigint unsigned NOT NULL,
  `gate_assignment_id` bigint unsigned DEFAULT NULL,
  `payload_fingerprint` binary(32) NOT NULL,
  `direction` enum('entry','exit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('physical','simulated') COLLATE utf8mb4_unicode_ci NOT NULL,
  `person_id` bigint unsigned DEFAULT NULL,
  `person_version` bigint unsigned DEFAULT NULL,
  `credential_id` bigint unsigned DEFAULT NULL,
  `credential_assignment_id` bigint unsigned DEFAULT NULL,
  `authorization_id` bigint unsigned DEFAULT NULL,
  `outcome` enum('allowed','denied') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `policy_version` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duplicate_window_seconds` smallint unsigned NOT NULL,
  `received_at` datetime(6) NOT NULL,
  `review_expires_at` datetime(6) DEFAULT NULL,
  `review_status` enum('pending','not_applicable','confirmed','identity_mismatch','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reviewed_at` datetime(6) DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `review_reason` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duplicate_of_event_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scan_attempts_guard_id_request_id_unique` (`guard_id`,`request_id`),
  UNIQUE KEY `scan_attempts_public_id_unique` (`public_id`),
  UNIQUE KEY `attempt_event_identity_unique` (`id`,`person_id`,`credential_assignment_id`,`guard_id`,`gate_id`,`direction`,`source`),
  KEY `scan_attempts_gate_assignment_id_foreign` (`gate_assignment_id`),
  KEY `scan_attempts_person_id_foreign` (`person_id`),
  KEY `scan_attempts_credential_id_foreign` (`credential_id`),
  KEY `scan_attempts_credential_assignment_id_foreign` (`credential_assignment_id`),
  KEY `scan_attempts_authorization_id_foreign` (`authorization_id`),
  KEY `scan_attempts_reviewed_by_foreign` (`reviewed_by`),
  KEY `scan_attempts_gate_id_received_at_id_index` (`gate_id`,`received_at`,`id`),
  KEY `scan_attempts_review_status_review_expires_at_index` (`review_status`,`review_expires_at`),
  KEY `scan_attempts_duplicate_of_event_id_foreign` (`duplicate_of_event_id`),
  KEY `attempt_source_clock` (`source`,`received_at`,`id`),
  CONSTRAINT `scan_attempts_authorization_id_foreign` FOREIGN KEY (`authorization_id`) REFERENCES `access_authorizations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_attempts_credential_assignment_id_foreign` FOREIGN KEY (`credential_assignment_id`) REFERENCES `credential_assignments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_attempts_credential_id_foreign` FOREIGN KEY (`credential_id`) REFERENCES `nfc_credentials` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_attempts_duplicate_of_event_id_foreign` FOREIGN KEY (`duplicate_of_event_id`) REFERENCES `access_events` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_attempts_gate_assignment_id_foreign` FOREIGN KEY (`gate_assignment_id`) REFERENCES `guard_gate_assignments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_attempts_gate_id_foreign` FOREIGN KEY (`gate_id`) REFERENCES `gates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_attempts_guard_id_foreign` FOREIGN KEY (`guard_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_attempts_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scan_attempts_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `attempt_duplicate_reason_present` CHECK (((`duplicate_of_event_id` is null) or (`review_reason` is not null))),
  CONSTRAINT `attempt_duplicate_review` CHECK (((`duplicate_of_event_id` is null) or ((`review_status` = _utf8mb4'cancelled') and (`review_reason` = _utf8mb4'duplicate_passage')))),
  CONSTRAINT `attempt_outcome_review` CHECK ((((`outcome` = _utf8mb4'denied') and (`review_status` = _utf8mb4'not_applicable') and (`review_expires_at` is null)) or ((`outcome` = _utf8mb4'allowed') and (`review_status` <> _utf8mb4'not_applicable') and (`person_id` is not null) and (`credential_id` is not null) and (`credential_assignment_id` is not null) and (`person_version` is not null) and ((((`policy_version` = _utf8mb4'gate-auto-v2') or (`policy_version` like _utf8mb4'gate-state-v1-%')) and (`review_status` in (_utf8mb4'confirmed',_utf8mb4'cancelled')) and (`review_expires_at` is null)) or (((`policy_version` <> _utf8mb4'gate-auto-v2') and (not((`policy_version` like _utf8mb4'gate-state-v1-%')))) and (`authorization_id` is not null) and (`review_expires_at` is not null) and (`review_expires_at` > `received_at`)))))),
  CONSTRAINT `attempt_review_clock` CHECK ((((`review_status` in (_utf8mb4'pending',_utf8mb4'not_applicable')) and (`reviewed_at` is null)) or ((`review_status` in (_utf8mb4'confirmed',_utf8mb4'identity_mismatch',_utf8mb4'cancelled',_utf8mb4'expired')) and (`reviewed_at` is not null)))),
  CONSTRAINT `attempt_reviewer_owner` CHECK ((((`reviewed_by` is null) or (`reviewed_by` = `guard_id`)) and ((`review_status` not in (_utf8mb4'confirmed',_utf8mb4'identity_mismatch',_utf8mb4'cancelled')) or (`reviewed_by` is not null))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  CONSTRAINT `sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_profiles` (
  `person_id` bigint unsigned NOT NULL,
  `program` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year_level` tinyint unsigned DEFAULT NULL,
  `academic_term` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `academic_year` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `semester` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`person_id`),
  CONSTRAINT `student_profiles_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `student_year_positive` CHECK (((`year_level` is null) or (`year_level` > 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `summary_controls` (
  `id` tinyint unsigned NOT NULL,
  `quota_day` date NOT NULL,
  `claims` int unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `summary_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_key` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `actor_auth_version` int unsigned NOT NULL,
  `source` enum('physical','simulated') COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `snapshot_at` datetime(6) NOT NULL,
  `snapshot` json NOT NULL,
  `snapshot_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_version` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `summary_requests_actor_id_request_key_unique` (`actor_id`,`request_key`),
  UNIQUE KEY `summary_requests_public_id_unique` (`public_id`),
  KEY `summary_requests_source_created_at_id_index` (`source`,`created_at`,`id`),
  CONSTRAINT `summary_requests_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `summary_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `summary_request_id` bigint unsigned NOT NULL,
  `status` enum('ready','failed','unavailable','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `selected_facts` json DEFAULT NULL,
  `sentences` json DEFAULT NULL,
  `error_code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input_tokens` int unsigned DEFAULT NULL,
  `output_tokens` int unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `summary_results_summary_request_id_unique` (`summary_request_id`),
  CONSTRAINT `summary_results_summary_request_id_foreign` FOREIGN KEY (`summary_request_id`) REFERENCES `summary_requests` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `summary_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `summary_request_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `decision` enum('approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `summary_reviews_actor_id_foreign` (`actor_id`),
  KEY `summary_reviews_summary_request_id_id_index` (`summary_request_id`,`id`),
  CONSTRAINT `summary_reviews_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `summary_reviews_summary_request_id_foreign` FOREIGN KEY (`summary_request_id`) REFERENCES `summary_requests` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `summary_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `summary_request_id` bigint unsigned NOT NULL,
  `claimed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `summary_tasks_summary_request_id_unique` (`summary_request_id`),
  CONSTRAINT `summary_tasks_summary_request_id_foreign` FOREIGN KEY (`summary_request_id`) REFERENCES `summary_requests` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint unsigned DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','guard') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `auth_version` bigint unsigned NOT NULL DEFAULT '1',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `last_login_at` timestamp(6) NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp(6) NULL DEFAULT NULL,
  `updated_at` timestamp(6) NULL DEFAULT NULL,
  `archived_at` timestamp(6) NULL DEFAULT NULL,
  `record_version` bigint unsigned NOT NULL DEFAULT '1',
  `username` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gate_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_person_id_unique` (`person_id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_gate_id_unique` (`gate_id`),
  KEY `users_role_status_index` (`role`,`status`),
  KEY `users_archived_at_status_index` (`archived_at`,`status`),
  CONSTRAINT `users_gate_id_foreign` FOREIGN KEY (`gate_id`) REFERENCES `gates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `users_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
