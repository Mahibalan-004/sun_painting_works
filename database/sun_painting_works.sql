-- ============================================================
-- SUN PAINTING WORKS DATABASE SCHEMA
-- Location: Kullampalayam Pirivu, Gobichettipalayam, Erode - 638453
-- Phone: 94423 99079 / 98422 99079
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sun_painting_works` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sun_painting_works`;

-- ------------------------------------------------------------
-- 1. USERS TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Admin', 'User') NOT NULL DEFAULT 'User',
  `joining_date` DATE NULL,
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`),
  INDEX `idx_employee_id` (`employee_id`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. CUSTOMERS TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` VARCHAR(50) NOT NULL UNIQUE,
  `customer_no` VARCHAR(20) NOT NULL,
  `alternate_phone` VARCHAR(20) NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `city` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_customer_id` (`customer_id`),
  INDEX `idx_customer_no` (`customer_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. CARS TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cars` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` VARCHAR(50) NOT NULL,
  `car_number` VARCHAR(50) NOT NULL,
  `car_name` VARCHAR(100) NOT NULL,
  `car_color` VARCHAR(50) NOT NULL,
  `estimate_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `final_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('Pending', 'Partial', 'Paid') NOT NULL DEFAULT 'Pending',
  `status` ENUM('New', 'Inspection', 'Denting', 'Painting', 'Polishing', 'Extra Work', 'Completed', 'Delivered') NOT NULL DEFAULT 'New',
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_car_customer_id` (`customer_id`),
  INDEX `idx_car_number` (`car_number`),
  INDEX `idx_car_status` (`status`),
  INDEX `idx_payment_status` (`payment_status`),
  CONSTRAINT `fk_cars_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. CAR PHOTOS TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `car_photos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `car_id` INT NOT NULL,
  `photo_type` ENUM('damage', 'after_paint') NOT NULL,
  `photo_path` VARCHAR(255) NOT NULL,
  `uploaded_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_car_photos_car_id` (`car_id`),
  CONSTRAINT `fk_car_photos_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. EXTRA WORK TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `extra_work` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `car_id` INT NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `added_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_extra_work_car_id` (`car_id`),
  CONSTRAINT `fk_extra_work_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. ATTENDANCE TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `login_time` TIME NULL,
  `logout_time` TIME NULL,
  `status` ENUM('Present', 'Absent', 'Half Day', 'Leave') NOT NULL DEFAULT 'Present',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_attendance_user_id` (`user_id`),
  INDEX `idx_attendance_date` (`attendance_date`),
  UNIQUE KEY `uk_user_date` (`user_id`, `attendance_date`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. WORK HISTORY TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `work_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `car_id` INT NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `updated_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_work_history_car_id` (`car_id`),
  CONSTRAINT `fk_work_history_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SEED INITIAL DATA
-- Default Admin: admin / admin123
-- Default Employee: john / user123
-- ------------------------------------------------------------
INSERT INTO `users` (`id`, `employee_id`, `name`, `phone`, `username`, `password`, `role`, `joining_date`, `status`) VALUES
(1, 'EMP001', 'Sun Owner', '9442399079', 'admin', '$2y$12$C456Y0/MZC3wCkz6DRWEfehowf.vDZXiVY4yo5wyH/KIxxbYXbd4G', 'Admin', '2020-01-01', 'Active'),
(2, 'EMP002', 'John Painter', '9842299079', 'john', '$2y$12$f77D2efJbFzuESn6hxb8/uExBzqWUlHpdiNiXVZy4yVk8aYdjVdHi', 'User', '2022-03-15', 'Active')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Seed Demo Customers
INSERT INTO `customers` (`id`, `customer_id`, `customer_no`, `customer_name`, `created_at`) VALUES
(1, 'SPW000001', '9876543210', 'Ramesh Kumar', '2026-08-01 09:00:00'),
(2, 'SPW000002', '9843210987', 'Karthik Raja', '2026-08-03 10:30:00')
ON DUPLICATE KEY UPDATE `customer_id`=`customer_id`;

-- Seed Demo Cars
INSERT INTO `cars` (`id`, `customer_id`, `car_number`, `car_name`, `car_color`, `estimate_amount`, `total_amount`, `final_amount`, `balance_amount`, `payment_status`, `status`, `created_by`, `created_at`) VALUES
(1, 'SPW000001', 'TN 36 AB 1234', 'Maruti Swift', 'Pearl White', 15000.00, 18500.00, 10000.00, 8500.00, 'Partial', 'Painting', 1, '2026-08-01 09:15:00'),
(2, 'SPW000002', 'TN 33 CD 5678', 'Hyundai i20', 'Midnight Black', 22000.00, 22000.00, 22000.00, 0.00, 'Paid', 'Completed', 1, '2026-08-03 10:45:00')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Demo Extra Work
INSERT INTO `extra_work` (`id`, `car_id`, `description`, `amount`, `added_by`, `created_at`) VALUES
(1, 1, 'Scratch Removal & Polishing', 3500.00, 1, '2026-08-02 14:20:00')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Demo Work History
INSERT INTO `work_history` (`id`, `car_id`, `status`, `description`, `updated_by`, `created_at`) VALUES
(1, 1, 'New', 'Car received for body repair & repainting', 1, '2026-08-01 09:15:00'),
(2, 1, 'Inspection', 'Vehicle damaged panel inspection completed', 1, '2026-08-01 11:30:00'),
(3, 1, 'Denting', 'Front bumper and side door dent pull complete', 2, '2026-08-02 10:00:00'),
(4, 1, 'Painting', 'Base primer applied and wet sanding in progress', 2, '2026-08-03 15:00:00'),
(5, 2, 'New', 'Car brought for metallic black mirror finish painting', 1, '2026-08-03 10:45:00'),
(6, 2, 'Completed', 'Car polished, quality check passed, ready for delivery', 1, '2026-08-06 17:00:00')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Demo Attendance
INSERT INTO `attendance` (`id`, `user_id`, `attendance_date`, `login_time`, `logout_time`, `status`, `remarks`, `created_at`) VALUES
(1, 1, '2026-08-08', '08:30:00', NULL, 'Present', 'System Login', '2026-08-08 08:30:00'),
(2, 2, '2026-08-08', '09:00:00', NULL, 'Present', 'Workshop Punch In', '2026-08-08 09:00:00')
ON DUPLICATE KEY UPDATE `id`=`id`;
