-- =============================================================================
-- CargoTrace · Global Shipment Visibility & Tracking Platform
-- MySQL 8.0+ Relational Database Schema & Seed Data (UAS Web Programming 2)
-- =============================================================================
-- Core Tables (Slide 11 Data Model Minimum):
--   1. users               (Accounts & RBAC roles: ADMIN, LOGISTICS_OPERATOR, CUSTOMER)
--   2. carriers            (Logistics operators: SEA, AIR, ROAD)
--   3. locations           (Origin/Destination ports & hubs with coordinates)
--   4. shipments           (Primary shipment headers + soft deletes)
--   5. shipment_items      (Manifest line items per shipment)
--   6. tracking_events     (Append-only chronological tracking timeline)
--   7. shipment_exceptions (Operational disruptions: OPEN -> INVESTIGATING -> RESOLVED)
--   8. notifications       (In-app user notifications)
--   9. audit_logs          (Immutable audit trail of actor, action, timestamp, entity)
--  10. personal_access_tokens (Laravel Sanctum API tokens - Slide 12 & 14)
--  11. scenario_configs    (Configurable group delay thresholds - Slide 8 & 23)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `cargotrace`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `cargotrace`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `scenario_configs`;
DROP TABLE IF EXISTS `personal_access_tokens`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `shipment_exceptions`;
DROP TABLE IF EXISTS `tracking_events`;
DROP TABLE IF EXISTS `shipment_items`;
DROP TABLE IF EXISTS `shipments`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `carriers`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. Table: users
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('ADMIN', 'LOGISTICS_OPERATOR', 'CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: carriers
-- -----------------------------------------------------------------------------
CREATE TABLE `carriers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(16) NOT NULL,
  `service_type` ENUM('SEA', 'AIR', 'ROAD') NOT NULL DEFAULT 'SEA',
  `contact_email` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `carriers_code_unique` (`code`),
  KEY `carriers_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: locations
-- -----------------------------------------------------------------------------
CREATE TABLE `locations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(16) NOT NULL,
  `city` VARCHAR(255) NOT NULL,
  `province` VARCHAR(255) NULL,
  `country` VARCHAR(255) NOT NULL,
  `latitude` DECIMAL(10, 6) NOT NULL,
  `longitude` DECIMAL(10, 6) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `locations_code_unique` (`code`),
  KEY `locations_city_index` (`city`),
  KEY `locations_province_index` (`province`),
  KEY `locations_country_index` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table: shipments
-- -----------------------------------------------------------------------------
CREATE TABLE `shipments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shipment_number` VARCHAR(32) NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `carrier_id` BIGINT UNSIGNED NOT NULL,
  `origin_location_id` BIGINT UNSIGNED NOT NULL,
  `destination_location_id` BIGINT UNSIGNED NOT NULL,
  `current_status` ENUM('CREATED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED') NOT NULL DEFAULT 'CREATED',
  `cargo_type` VARCHAR(255) NOT NULL,
  `weight_kg` DECIMAL(12, 2) NOT NULL,
  `volume_m3` DECIMAL(10, 2) NOT NULL,
  `expected_departure_at` TIMESTAMP NULL,
  `expected_arrival_at` TIMESTAMP NOT NULL,
  `estimated_arrival_at` TIMESTAMP NULL,
  `archived_at` TIMESTAMP NULL,
  `deleted_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipments_shipment_number_unique` (`shipment_number`),
  KEY `shipments_current_status_index` (`current_status`),
  KEY `shipments_expected_arrival_at_index` (`expected_arrival_at`),
  KEY `shipments_estimated_arrival_at_index` (`estimated_arrival_at`),
  KEY `shipments_customer_status_index` (`customer_id`, `current_status`),
  KEY `shipments_carrier_status_index` (`carrier_id`, `current_status`),
  KEY `shipments_corridor_index` (`origin_location_id`, `destination_location_id`),
  CONSTRAINT `shipments_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `shipments_carrier_id_foreign` FOREIGN KEY (`carrier_id`) REFERENCES `carriers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `shipments_origin_location_id_foreign` FOREIGN KEY (`origin_location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `shipments_destination_location_id_foreign` FOREIGN KEY (`destination_location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table: shipment_items
-- -----------------------------------------------------------------------------
CREATE TABLE `shipment_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shipment_id` BIGINT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `weight_kg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `volume_m3` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `shipment_items_shipment_id_foreign` (`shipment_id`),
  CONSTRAINT `shipment_items_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Table: tracking_events (Append-only historical timeline - Slide 7)
-- -----------------------------------------------------------------------------
CREATE TABLE `tracking_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shipment_id` BIGINT UNSIGNED NOT NULL,
  `event_type` VARCHAR(32) NOT NULL,
  `occurred_at` TIMESTAMP NOT NULL,
  `location_name` VARCHAR(255) NOT NULL,
  `latitude` DECIMAL(10, 6) NULL,
  `longitude` DECIMAL(10, 6) NULL,
  `description` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tracking_events_event_type_index` (`event_type`),
  KEY `tracking_events_occurred_at_index` (`occurred_at`),
  KEY `tracking_events_shipment_occurred_index` (`shipment_id`, `occurred_at`),
  CONSTRAINT `tracking_events_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tracking_events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table: shipment_exceptions (Slide 8 & Slide 11)
-- -----------------------------------------------------------------------------
CREATE TABLE `shipment_exceptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shipment_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('CUSTOMS_HOLD', 'WEATHER_DELAY', 'PORT_CONGESTION', 'DAMAGED', 'MISROUTED', 'DOCUMENTATION_ISSUE', 'CARRIER_DELAY', 'DAMAGED_CARGO', 'ROUTE_DEVIATION') NOT NULL,
  `severity` ENUM('LOW', 'MEDIUM', 'HIGH') NOT NULL DEFAULT 'MEDIUM',
  `status` ENUM('OPEN', 'INVESTIGATING', 'RESOLVED') NOT NULL DEFAULT 'OPEN',
  `detected_at` TIMESTAMP NOT NULL,
  `resolved_at` TIMESTAMP NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `shipment_exceptions_type_index` (`type`),
  KEY `shipment_exceptions_status_index` (`status`),
  KEY `shipment_exceptions_shipment_status_index` (`shipment_id`, `status`),
  CONSTRAINT `shipment_exceptions_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table: notifications
-- -----------------------------------------------------------------------------
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `shipment_id` BIGINT UNSIGNED NULL,
  `type` VARCHAR(48) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notifications_user_read_index` (`user_id`, `read_at`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_shipment_id_foreign` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Table: audit_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `changes` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_entity_index` (`entity_type`, `entity_id`),
  KEY `audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `audit_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Table: personal_access_tokens (Laravel Sanctum)
-- -----------------------------------------------------------------------------
CREATE TABLE `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL,
  `expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Table: scenario_configs
-- -----------------------------------------------------------------------------
CREATE TABLE `scenario_configs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_code` VARCHAR(24) NOT NULL DEFAULT 'DEFAULT',
  `route_corridor` VARCHAR(255) NOT NULL DEFAULT 'Southeast Asia → Europe',
  `low_threshold_hours` INT UNSIGNED NOT NULL DEFAULT 6,
  `high_threshold_hours` INT UNSIGNED NOT NULL DEFAULT 24,
  `primary_exception` VARCHAR(48) NOT NULL DEFAULT 'CUSTOMS_HOLD',
  `kpi_focus` VARCHAR(255) NOT NULL DEFAULT 'On-Time Delivery & Exception Rate',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA (Users, Carriers, Locations, Scenario Config)
-- =============================================================================

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'Aria Wibowo', 'admin@cargotrace.io', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN'),
(2, 'Bimo Santoso', 'operator@cargotrace.io', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'LOGISTICS_OPERATOR'),
(3, 'Citra Hapsari', 'customer@cargotrace.io', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CUSTOMER');

INSERT INTO `carriers` (`id`, `name`, `code`, `service_type`, `contact_email`, `is_active`) VALUES
(1, 'Nusantara Freight', 'NFR', 'SEA', 'ops@nusantarafreight.id', 1),
(2, 'Aurora Container Line', 'ACL', 'SEA', 'dispatch@auroracontainers.com', 1),
(3, 'Meridian Air Cargo', 'MAC', 'AIR', 'desk@meridianair.aero', 1),
(4, 'Java Express Haulage', 'JEH', 'ROAD', 'fleet@javaexpress.co.id', 0);

INSERT INTO `locations` (`id`, `name`, `code`, `city`, `province`, `country`, `latitude`, `longitude`) VALUES
(1, 'Singapore Changi Air Cargo', 'SIN', 'Singapore', NULL, 'Singapore', 1.364400, 103.991500),
(2, 'Tanjung Priok Port', 'JKT', 'Jakarta', 'DKI Jakarta', 'Indonesia', -6.105000, 106.880000),
(3, 'Juanda International', 'SUB', 'Surabaya', 'Jawa Timur', 'Indonesia', -7.379800, 112.787600),
(4, 'Suvarnabhumi Cargo', 'BKK', 'Bangkok', NULL, 'Thailand', 13.690000, 100.750100),
(5, 'Port of Rotterdam', 'RTM', 'Rotterdam', NULL, 'Netherlands', 51.949600, 4.142500),
(6, 'Hamburg Airport Cargo', 'HAM', 'Hamburg', NULL, 'Germany', 53.630400, 9.988200),
(7, 'Pelabuhan Krueng Geukuh (Pelindo)', 'KRG', 'Krueng Geukuh, Kab. Aceh Utara', 'Aceh', 'Indonesia', 5.241700, 97.026400),
(8, 'Pelabuhan Lhokseumawe & KEK Arun', 'LSW', 'Kota Lhokseumawe', 'Aceh', 'Indonesia', 5.180100, 97.150700),
(9, 'Bireuen Logistics & Cargo Terminal', 'BIR', 'Kab. Bireuen', 'Aceh', 'Indonesia', 5.203000, 96.700900),
(10, 'Pelabuhan Malahayati Krueng Raya', 'MLH', 'Banda Aceh / Aceh Besar', 'Aceh', 'Indonesia', 5.596700, 95.512200),
(11, 'Pelabuhan Bebas Sabang (CT-3 BPKS)', 'SBG', 'Kota Sabang (Pulau Weh)', 'Aceh', 'Indonesia', 5.889700, 95.316400),
(12, 'Pelabuhan Belawan International Container Terminal', 'BLW', 'Kota Medan / Belawan', 'Sumatera Utara', 'Indonesia', 3.784400, 98.683600);

INSERT INTO `scenario_configs` (`id`, `group_code`, `route_corridor`, `low_threshold_hours`, `high_threshold_hours`, `primary_exception`, `kpi_focus`) VALUES
(1, 'DEFAULT', 'Southeast Asia → Europe', 6, 24, 'CUSTOMS_HOLD', 'On-Time Delivery & Exception Rate');
