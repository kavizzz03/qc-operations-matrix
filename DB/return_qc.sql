-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 29, 2026 at 07:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `return_qc`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_return_status` (IN `p_record_id` INT, IN `p_field` VARCHAR(50), IN `p_value` INT, IN `p_user` VARCHAR(50))   BEGIN
    DECLARE v_old_value INT;
    
    START TRANSACTION;
    
    IF p_field = 'is_informed' THEN
        SELECT is_informed INTO v_old_value FROM qc_damage_main WHERE record_id = p_record_id;
        UPDATE qc_damage_main 
        SET is_informed = p_value, 
            informed_by_user = p_user, 
            informed_datetime = NOW()
        WHERE record_id = p_record_id;
    ELSEIF p_field = 'is_store_received' THEN
        SELECT is_store_received INTO v_old_value FROM qc_damage_main WHERE record_id = p_record_id;
        UPDATE qc_damage_main 
        SET is_store_received = p_value, 
            store_user = p_user, 
            store_datetime = NOW()
        WHERE record_id = p_record_id;
    ELSEIF p_field = 'is_gate_cleared' THEN
        SELECT is_gate_cleared INTO v_old_value FROM qc_damage_main WHERE record_id = p_record_id;
        UPDATE qc_damage_main 
        SET is_gate_cleared = p_value, 
            gate_user = p_user, 
            gate_datetime = NOW()
        WHERE record_id = p_record_id;
    ELSEIF p_field = 'is_handover_complete' THEN
        SELECT is_handover_complete INTO v_old_value FROM qc_damage_main WHERE record_id = p_record_id;
        UPDATE qc_damage_main 
        SET is_handover_complete = p_value, 
            handover_user = p_user, 
            handover_datetime = NOW()
        WHERE record_id = p_record_id;
    END IF;
    
    INSERT INTO qc_audit_log (record_id, action, field_name, old_value, new_value, changed_by)
    VALUES (p_record_id, 'UPDATE_STATUS', p_field, CAST(v_old_value AS CHAR), CAST(p_value AS CHAR), p_user);
    
    COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `branch_id` int(11) NOT NULL,
  `branch_code` varchar(20) NOT NULL,
  `branch_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`branch_id`, `branch_code`, `branch_name`) VALUES
(1, 'BR-COL', 'Colombo Flagship Hub'),
(2, 'BR-KAN', 'Kandy Premium Outlet'),
(3, 'BR-GAL', 'Galle Coastal Digital Center'),
(4, 'BR-NEG', 'Negombo Distribution Warehouse'),
(5, '021', 'Kalutara');

-- --------------------------------------------------------

--
-- Table structure for table `floors`
--

CREATE TABLE `floors` (
  `floor_id` int(11) NOT NULL,
  `floor_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `floors`
--

INSERT INTO `floors` (`floor_id`, `floor_name`) VALUES
(1, 'Gents'),
(2, 'Ladies'),
(3, 'Kids & Infants Section'),
(4, 'Unisex Denim & Accessories');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `invoice_item_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `received_qty` int(11) NOT NULL DEFAULT 0,
  `checked_sample_qty` int(11) NOT NULL DEFAULT 0,
  `defect_qty` int(11) NOT NULL DEFAULT 0,
  `return_qty` int(11) NOT NULL DEFAULT 0,
  `status` enum('PASS','FAIL') NOT NULL DEFAULT 'PASS'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`invoice_item_id`, `invoice_id`, `item_id`, `received_qty`, `checked_sample_qty`, `defect_qty`, `return_qty`, `status`) VALUES
(1, 1, 6, 65, 20, 0, 0, 'PASS'),
(2, 1, 2, 500, 55, 25, 2, 'PASS'),
(3, 1, 1, 500, 50, 50, 500, 'FAIL'),
(4, 2, 1, 555, 55, 0, 0, 'PASS'),
(5, 2, 6, 55, 555, 0, 5, 'PASS'),
(6, 3, 3, 50, 30, 0, 0, 'PASS'),
(7, 3, 4, 500, 30, 112, 0, 'PASS'),
(8, 5, 7, 440, 50, 0, 0, 'PASS'),
(9, 5, 8, 270, 40, 1, 1, 'PASS'),
(10, 5, 9, 380, 50, 2, 2, 'PASS'),
(11, 5, 10, 500, 50, 1, 1, 'PASS'),
(12, 5, 11, 60, 60, 0, 0, 'PASS'),
(13, 5, 12, 522, 80, 1, 1, 'PASS'),
(14, 5, 13, 300, 50, 0, 0, 'PASS'),
(15, 5, 14, 500, 0, 0, 500, 'FAIL'),
(16, 7, 15, 75, 20, 0, 0, 'PASS'),
(17, 7, 16, 69, 20, 0, 0, 'PASS'),
(18, 7, 17, 230, 32, 1, 1, 'PASS'),
(19, 7, 19, 246, 50, 8, 8, 'PASS'),
(20, 7, 18, 105, 20, 7, 105, 'FAIL'),
(21, 8, 20, 24, 24, 24, 24, 'FAIL'),
(22, 9, 7, 100, 50, 20, 20, 'PASS'),
(23, 9, 21, 200, 50, 22, 2, 'PASS'),
(24, 9, 9, 100, 48, 50, 100, 'FAIL'),
(25, 13, 15, 51, 8, 10, 5, 'PASS'),
(26, 13, 15, 52, 25, 20, 52, 'FAIL');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `system_id` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `supplier_id`, `item_name`, `item_code`, `system_id`, `quantity`, `cost_price`, `selling_price`, `created_at`) VALUES
(1, 1, 'Slim Fit Black Cotton T-Shirt', 'TSH-BLK-M', 'SYS-1001', 705, 1200.00, 2450.00, '2026-05-19 15:09:31'),
(2, 1, 'Vintage Blue Comfort Denim Jeans', 'DNM-BLU-32', 'SYS-1002', 583, 2800.00, 4950.00, '2026-05-19 15:09:31'),
(3, 2, 'Premium White Linen Casual Shirt', 'LNX-WHT-L', 'SYS-2041', 110, 1850.00, 3800.00, '2026-05-19 15:09:31'),
(4, 2, 'Classic Floral Summer Dress', 'DRS-FLR-S', NULL, 540, 2200.00, 4200.00, '2026-05-19 15:09:31'),
(5, 3, 'Casual Brown Bomber Unisex Jacket', 'JKT-BRN-XL', 'SYS-3099', 12, 4500.00, 8500.00, '2026-05-19 15:09:31'),
(6, 1, 'test_item_55', 'Item123', '1000255354', 115, 1500.00, 0.00, '2026-05-19 15:21:08'),
(7, 4, 'Ladies Blouse', 'R.B 027', NULL, 520, 0.00, 0.00, '2026-05-20 06:52:06'),
(8, 4, 'Ladies Blouse', 'RB 030', NULL, 269, 0.00, 0.00, '2026-05-20 06:53:21'),
(9, 4, 'Ladies Blouse', 'KB 28', NULL, 378, 0.00, 0.00, '2026-05-20 06:54:21'),
(10, 4, 'Ladies Blouse', 'KB 033', NULL, 499, 0.00, 0.00, '2026-05-20 06:55:30'),
(11, 4, 'Ladies Blouse', 'KB029', NULL, 60, 0.00, 0.00, '2026-05-20 06:56:19'),
(12, 4, 'Ladies Blouse', 'KB032', NULL, 521, 0.00, 0.00, '2026-05-20 06:57:51'),
(13, 4, 'Ladies Frock', 'KUF0024', NULL, 300, 0.00, 0.00, '2026-05-20 06:58:48'),
(14, 4, 'Ladies Blouse', 'KB026', NULL, 0, 0.00, 0.00, '2026-05-20 06:59:27'),
(15, 5, 'Gents Shirt', '2124', NULL, 121, 0.00, 0.00, '2026-05-25 05:39:20'),
(16, 5, 'Gents Shirt', '2130', NULL, 69, 0.00, 0.00, '2026-05-25 05:41:47'),
(17, 5, 'Gents Shirt', '1001', NULL, 229, 0.00, 0.00, '2026-05-25 05:42:14'),
(18, 5, 'Gents Shirt', '2121', NULL, 0, 0.00, 0.00, '2026-05-25 05:44:32'),
(19, 5, 'Gents Shirt', '2128', NULL, 238, 0.00, 0.00, '2026-05-25 05:45:10'),
(20, 5, 'Gents Shirt', '3171', NULL, 0, 0.00, 0.00, '2026-05-26 10:22:49'),
(21, 4, 'Ladies Blouse', '1555', '100026852', 198, 1500.00, 1850.00, '2026-05-28 12:15:49');

-- --------------------------------------------------------

--
-- Table structure for table `item_return_reasons`
--

CREATE TABLE `item_return_reasons` (
  `id` int(11) NOT NULL,
  `invoice_item_id` int(11) NOT NULL,
  `reason_id` int(11) NOT NULL,
  `return_qty` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_return_reasons`
--

INSERT INTO `item_return_reasons` (`id`, `invoice_item_id`, `reason_id`, `return_qty`) VALUES
(1, 1, 1, 3),
(2, 1, 2, 2),
(3, 2, 3, 10),
(4, 2, 4, 15),
(5, 3, 1, 25),
(6, 3, 2, 25),
(7, 18, 9, 1),
(8, 19, 10, 8),
(9, 20, 6, 105),
(10, 21, 6, 24),
(11, 22, 11, 10),
(12, 22, 1, 10),
(13, 23, 8, 1),
(14, 23, 9, 1),
(15, 24, 1, 100),
(16, 25, 8, 1),
(17, 25, 9, 1),
(18, 25, 11, 1),
(19, 25, 1, 1),
(20, 25, 4, 1),
(21, 26, 11, 50),
(22, 26, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `master_tabs`
--

CREATE TABLE `master_tabs` (
  `tab_id` int(11) NOT NULL,
  `tab_name` varchar(100) NOT NULL,
  `show_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `tab_url` varchar(255) NOT NULL,
  `tab_icon` varchar(50) DEFAULT 'fas fa-link',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_tabs`
--

INSERT INTO `master_tabs` (`tab_id`, `tab_name`, `show_name`, `description`, `tab_url`, `tab_icon`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'dashboard', 'Dashboard', 'Main dashboard overview', 'dashboard.php', 'fas fa-tachometer-alt', 1, 1, '2026-05-25 07:27:37'),
(2, 'users', 'User Management', 'Manage system users', 'users.php', 'fas fa-users', 2, 1, '2026-05-25 07:27:37'),
(3, 'tab_assignments', 'Tab Assignments', 'Assign tabs to users', 'tab_assignments.php', 'fas fa-tasks', 3, 1, '2026-05-25 07:27:37'),
(4, 'suppliers', 'Suppliers', 'Manage supplier information', 'suppliers.php', 'fas fa-truck', 4, 1, '2026-05-25 07:27:37'),
(5, 'return_reasons', 'Return Reasons', 'Manage return reasons', 'return_reasons.php', 'fas fa-exchange-alt', 5, 1, '2026-05-25 07:27:37'),
(7, 'add_aql', 'ADD New AQL', 'Create New AQL Form and This Saved To Db Saved To Supplier_invoice.php', 'qc_return_management.php', 'fas fa-chart-line', 9, 1, '2026-05-25 07:44:13'),
(8, 'update_flag', 'Flag Update', 'update flags and can get print updated also', 'flag.php', 'fas fa-flag', 10, 1, '2026-05-25 07:49:01'),
(9, 'add_aql_to_return', 'AQL Assign To Return', 'AQL Repoet Return Added to New Return Note', 'qc_damage.php', 'fas fa-link', 12, 1, '2026-05-25 09:10:36'),
(10, 'supplier_inform', 'Supplier Inform', 'Inform Supplier to about Return items Via Sms and Email', 'supplier_invoices.php', 'fas fa-envelope', 16, 1, '2026-05-25 09:16:16'),
(11, 'aql_edit_manager', 'AQL Manager', 'AQL Rpeort Edit and Print', 'qc_invoice_manager.php', 'fas fa-project-diagram', 14, 1, '2026-05-25 09:38:53'),
(12, 'add_new_return_not', 'Add New Return Note', 'Add new Qc Return Note Without Aql Report', 'qc_entry.php', 'fas fa-check-circle', 99, 1, '2026-05-25 10:00:16'),
(13, 'qc_mode_management', 'QC Modes', 'QC Modes Add Edit View Delete Management', 'qc_modes.php', 'fas fa-microscope', 15, 1, '2026-05-25 10:14:37'),
(14, 'floor_management', 'Floors', 'Floors Add Edit Update And Delete Management', 'floors.php', 'fas fa-layer-group', 17, 1, '2026-05-25 10:26:32'),
(15, 'qc_return_audit_report', 'Return Audit Report', 'Print Qc Return Audit Report', 'qc_return_report.php', 'fas fa-file-invoice-dollar', 15, 1, '2026-05-25 11:28:06'),
(16, 'sticker_print', 'Print Stickers', 'According to Return Note to Print Stickers', 'print_records.php', 'fas fa-unlink', 19, 1, '2026-05-26 10:49:27');

-- --------------------------------------------------------

--
-- Table structure for table `qc_audit_log`
--

CREATE TABLE `qc_audit_log` (
  `log_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `field_name` varchar(50) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` varchar(50) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_audit_log`
--

INSERT INTO `qc_audit_log` (`log_id`, `record_id`, `action`, `field_name`, `old_value`, `new_value`, `changed_by`, `changed_at`, `ip_address`) VALUES
(1, 5, 'FLAG_UPDATE', 'is_informed', NULL, '1', 'Super Administrator', '2026-05-21 09:15:45', NULL),
(2, 5, 'FLAG_UPDATE', 'is_store_received', NULL, '1', 'Super Administrator', '2026-05-21 09:16:22', NULL),
(3, 2, 'FLAG_UPDATE', 'is_informed', NULL, '1', 'Super Administrator', '2026-05-21 09:37:50', NULL),
(4, 2, 'FLAG_UPDATE', 'is_gate_cleared', NULL, '1', 'Super Administrator', '2026-05-21 09:37:53', NULL),
(5, 2, 'FLAG_UPDATE', 'is_store_received', NULL, '1', 'Super Administrator', '2026-05-21 09:37:57', NULL),
(6, 2, 'FLAG_UPDATE', 'is_handover_complete', NULL, '1', 'Super Administrator', '2026-05-21 09:38:00', NULL),
(7, 4, 'SMS_SENT', 'contact_number', NULL, '94740890730', 'Super Administrator', '2026-05-24 18:15:37', NULL),
(8, 6, 'FLAG_UPDATE', 'is_store_received', NULL, '1', 'Super Administrator', '2026-05-25 05:56:27', '::1'),
(9, 7, 'FLAG_UPDATE', 'is_informed', NULL, '1', 'admin', '2026-05-26 09:08:51', '::1'),
(10, 9, 'FLAG_UPDATE', 'is_informed', NULL, '1', 'admin', '2026-05-26 09:20:22', '::1'),
(11, 9, 'FLAG_UPDATE', 'is_store_received', NULL, '1', 'inform', '2026-05-26 09:21:10', '::1'),
(12, 2, 'FLAG_UPDATE', 'is_informed', NULL, '1', 'inform', '2026-05-26 09:37:04', '::1'),
(13, 10, 'FLAG_UPDATE', 'is_informed', NULL, '1', 'admin', '2026-05-26 10:50:32', '::1'),
(14, 10, 'FLAG_UPDATE', 'is_store_received', NULL, '1', 'admin', '2026-05-26 10:50:35', '::1'),
(15, 10, 'FLAG_UPDATE', 'is_gate_cleared', NULL, '1', 'admin', '2026-05-26 10:50:37', '::1'),
(16, 10, 'FLAG_UPDATE', 'is_handover_complete', NULL, '1', 'admin', '2026-05-26 10:50:40', '::1'),
(17, 8, 'SMS_SENT', 'contact_number', NULL, '94740890730', 'admin', '2026-05-28 12:50:15', NULL),
(18, 8, 'SMS_SENT', 'contact_number', NULL, NULL, 'admin', '2026-05-28 12:50:20', NULL),
(19, 8, 'EMAIL', 'communication', NULL, 'kavindumalshan2003@gmail.com', 'admin', '2026-05-28 12:58:51', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `qc_damage_items`
--

CREATE TABLE `qc_damage_items` (
  `item_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(200) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `defect_type` varchar(100) DEFAULT NULL,
  `defect_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_damage_items`
--

INSERT INTO `qc_damage_items` (`item_id`, `record_id`, `item_code`, `item_name`, `quantity`, `unit_cost`, `defect_type`, `defect_description`, `created_at`) VALUES
(1, 1, 'RB 030', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 09:55:17'),
(2, 1, 'KB 28', 'Ladies Blouse', 2, 0.00, NULL, NULL, '2026-05-20 09:55:17'),
(3, 1, 'KB 033', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 09:55:17'),
(4, 1, 'KB032', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 09:55:17'),
(5, 1, 'KB026', 'Ladies Blouse', 500, 0.00, NULL, NULL, '2026-05-20 09:55:17'),
(6, 2, 'RB 030', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 10:29:03'),
(7, 2, 'KB 28', 'Ladies Blouse', 2, 0.00, NULL, NULL, '2026-05-20 10:29:03'),
(8, 2, 'KB 033', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 10:29:03'),
(9, 2, 'KB032', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 10:29:03'),
(10, 2, 'KB026', 'Ladies Blouse', 500, 0.00, NULL, NULL, '2026-05-20 10:29:03'),
(11, 3, 'RB 030', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 10:47:54'),
(12, 3, 'KB 28', 'Ladies Blouse', 2, 0.00, NULL, NULL, '2026-05-20 10:47:54'),
(13, 3, 'KB 033', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 10:47:54'),
(14, 3, 'KB032', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-20 10:47:54'),
(15, 3, 'KB026', 'Ladies Blouse', 455, 0.00, NULL, NULL, '2026-05-20 10:47:54'),
(16, 4, 'DNM-BLU-32', 'Vintage Blue Comfort Denim Jeans', 2, 2800.00, NULL, NULL, '2026-05-20 10:49:33'),
(17, 4, 'TSH-BLK-M', 'Slim Fit Black Cotton T-Shirt', 50, 1200.00, NULL, NULL, '2026-05-20 10:49:33'),
(18, 5, 'KT 123', 'ITEM_1', 1, 520.00, NULL, NULL, '2026-05-21 08:43:03'),
(19, 5, 'KT 456', 'ITEM_2', 52, 3500.00, NULL, NULL, '2026-05-21 08:43:03'),
(20, 5, 'KT 566', 'ITEM 3', 1, 850.00, NULL, NULL, '2026-05-21 08:43:03'),
(21, 6, '1001', 'Gents Shirt', 1, 0.00, NULL, NULL, '2026-05-25 05:48:48'),
(22, 6, '2128', 'Gents Shirt', 8, 0.00, NULL, NULL, '2026-05-25 05:48:48'),
(23, 6, '2121', 'Gents Shirt', 105, 0.00, NULL, NULL, '2026-05-25 05:48:48'),
(24, 7, 'RB 030', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-25 10:44:11'),
(25, 7, 'KB 28', 'Ladies Blouse', 2, 0.00, NULL, NULL, '2026-05-25 10:44:11'),
(26, 7, 'KB 033', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-25 10:44:11'),
(27, 7, 'KB032', 'Ladies Blouse', 1, 0.00, NULL, NULL, '2026-05-25 10:44:11'),
(28, 7, 'KB026', 'Ladies Blouse', 500, 0.00, NULL, NULL, '2026-05-25 10:44:11'),
(29, 8, 'RB 030', 'Ladies Blouse', 1, 500.00, NULL, NULL, '2026-05-25 11:12:01'),
(30, 8, 'KB 28', 'Ladies Blouse', 2, 650.00, NULL, NULL, '2026-05-25 11:12:01'),
(31, 8, 'KB 033', 'Ladies Blouse', 1, 755.00, NULL, NULL, '2026-05-25 11:12:01'),
(32, 8, 'KB032', 'Ladies Blouse', 1, 800.00, NULL, NULL, '2026-05-25 11:12:01'),
(33, 8, 'KB026', 'Ladies Blouse', 500, 1000.00, NULL, NULL, '2026-05-25 11:12:01'),
(34, 9, 'test_item_1', 'rfwerfrw', 1, 5885.00, NULL, NULL, '2026-05-25 11:43:27'),
(35, 9, 'test_item_1354', 'fgf', 5, 5555.00, NULL, NULL, '2026-05-25 11:43:27'),
(36, 10, '3171', 'Gents Shirt', 24, 1500.00, NULL, NULL, '2026-05-26 10:27:53'),
(37, 11, '1001', 'Gents Shirt', 1, 1500.00, NULL, NULL, '2026-05-27 10:35:19'),
(38, 11, '2128', 'Gents Shirt', 8, 500.00, NULL, NULL, '2026-05-27 10:35:19'),
(39, 11, '2121', 'Gents Shirt', 105, 1200.00, NULL, NULL, '2026-05-27 10:35:19'),
(40, 12, 'R.B 027', 'Ladies Blouse', 20, 1500.00, NULL, NULL, '2026-05-28 12:20:09'),
(41, 12, '1555', 'Ladies Blouse', 2, 1500.00, NULL, NULL, '2026-05-28 12:20:09'),
(42, 12, 'KB 28', 'Ladies Blouse', 100, 2500.00, NULL, NULL, '2026-05-28 12:20:09'),
(43, 13, '2124', 'Gents Shirt', 5, 500.00, NULL, NULL, '2026-05-28 13:04:44'),
(44, 13, '2124', 'Gents Shirt', 52, 722.00, NULL, NULL, '2026-05-28 13:04:44');

--
-- Triggers `qc_damage_items`
--
DELIMITER $$
CREATE TRIGGER `trg_calc_total_amount` AFTER INSERT ON `qc_damage_items` FOR EACH ROW BEGIN
    DECLARE v_total DECIMAL(15,2);
    
    SELECT SUM(total_cost) INTO v_total 
    FROM qc_damage_items 
    WHERE record_id = NEW.record_id;
    
    UPDATE qc_damage_main 
    SET total_amount = IFNULL(v_total, 0)
    WHERE record_id = NEW.record_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_update_total_amount` AFTER DELETE ON `qc_damage_items` FOR EACH ROW BEGIN
    DECLARE v_total DECIMAL(15,2);
    
    SELECT SUM(total_cost) INTO v_total 
    FROM qc_damage_items 
    WHERE record_id = OLD.record_id;
    
    UPDATE qc_damage_main 
    SET total_amount = IFNULL(v_total, 0)
    WHERE record_id = OLD.record_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `qc_damage_main`
--

CREATE TABLE `qc_damage_main` (
  `record_id` int(11) NOT NULL,
  `record_date` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `doc_number` varchar(50) DEFAULT NULL,
  `mode_id` int(11) DEFAULT NULL,
  `reason_id` int(11) DEFAULT NULL,
  `added_by_user` varchar(50) DEFAULT NULL,
  `added_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_informed` tinyint(1) DEFAULT 0,
  `informed_by_user` varchar(50) DEFAULT NULL,
  `informed_datetime` datetime DEFAULT NULL,
  `is_store_received` tinyint(1) DEFAULT 0,
  `store_user` varchar(50) DEFAULT NULL,
  `store_datetime` datetime DEFAULT NULL,
  `is_gate_cleared` tinyint(1) DEFAULT 0,
  `gate_user` varchar(50) DEFAULT NULL,
  `gate_datetime` datetime DEFAULT NULL,
  `is_handover_complete` tinyint(1) DEFAULT 0,
  `number_of_bags` int(11) NOT NULL DEFAULT 0,
  `handover_user` varchar(50) DEFAULT NULL,
  `handover_datetime` datetime DEFAULT NULL,
  `is_active_cart` tinyint(1) DEFAULT 1,
  `print_count` int(11) DEFAULT 0,
  `assigned_task_id` int(11) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_damage_main`
--

INSERT INTO `qc_damage_main` (`record_id`, `record_date`, `supplier_id`, `invoice_number`, `reference_number`, `doc_number`, `mode_id`, `reason_id`, `added_by_user`, `added_time`, `is_informed`, `informed_by_user`, `informed_datetime`, `is_store_received`, `store_user`, `store_datetime`, `is_gate_cleared`, `gate_user`, `gate_datetime`, `is_handover_complete`, `number_of_bags`, `handover_user`, `handover_datetime`, `is_active_cart`, `print_count`, `assigned_task_id`, `total_amount`, `remarks`) VALUES
(1, '2026-05-20', 4, '1643', '', '', 5, 1, 'QC Officer', '2026-05-20 09:55:17', 1, 'Super Administrator', '2026-05-24 23:08:56', 0, NULL, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 1, 0, NULL, 0.00, NULL),
(2, '2026-05-20', 4, '1643', '2026052000001', '2026052000001', 5, 1, 'QC Officer', '2026-05-20 10:29:03', 1, 'inform', '2026-05-26 15:07:04', 1, 'Super Administrator', '2026-05-21 15:07:57', 1, 'Super Administrator', '2026-05-21 15:07:53', 1, 0, 'Super Administrator', '2026-05-21 15:08:00', 1, 3, NULL, 0.00, NULL),
(3, '2026-05-20', 4, '1643', '2026052000002', '134/2026/2243/df 4544', 5, 6, 'Kasun', '2026-05-20 10:47:54', 1, 'Super Administrator', '2026-05-25 10:50:58', 0, NULL, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 1, 6, NULL, 0.00, NULL),
(4, '2026-05-20', 1, 'INV-910', '2026052000003', '54674656', 5, 6, 'QC Officer', '2026-05-20 10:49:33', 1, 'Super Administrator', '2026-05-25 10:18:00', 0, NULL, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 1, 6, NULL, 65600.00, NULL),
(5, '2026-05-21', 1, '584255412', '2026052100001', NULL, 4, 1, 'Super Administrator', '2026-05-21 08:43:03', 1, 'Super Administrator', '2026-05-25 10:41:58', 1, 'Super Administrator', '2026-05-21 14:46:22', 0, NULL, NULL, 0, 0, NULL, NULL, 1, 5, NULL, 183370.00, NULL),
(6, '2026-05-25', 5, '002/003', '2026052500001', '2563', 4, 6, 'Gents Floor', '2026-05-25 05:48:48', 1, 'admin', '2026-05-25 16:02:26', 1, 'Super Administrator', '2026-05-25 11:26:27', 0, NULL, NULL, 0, 0, NULL, NULL, 1, 5, NULL, 0.00, NULL),
(7, '2026-05-25', 4, '1643', '2026052500002', '', 3, 2, 'QC Officer', '2026-05-25 10:44:11', 1, 'admin', '2026-05-26 14:38:51', 0, NULL, NULL, 0, NULL, NULL, 0, 5, NULL, NULL, 1, 1, NULL, 0.00, NULL),
(8, '2026-05-25', 4, '1643', '2026052500003', 'HO/GM/62/2026 ', 3, 10, 'QC Officer', '2026-05-25 11:12:01', 1, 'admin', '2026-05-28 18:28:51', 0, NULL, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 1, 7, NULL, 3355.00, NULL),
(9, '2026-05-25', 1, '584255412', '2026052500004', 'ergwr', 2, 5, 'admin', '2026-05-25 11:43:27', 1, 'admin', '2026-05-26 14:50:22', 1, 'inform', '2026-05-26 14:51:10', 0, NULL, NULL, 0, 3, NULL, NULL, 1, 1, NULL, 33660.00, NULL),
(10, '2026-05-26', 5, '010', '2026052600001', '2582', 3, 6, 'QC Officer', '2026-05-26 10:27:53', 1, 'admin', '2026-05-26 16:20:32', 1, 'admin', '2026-05-26 16:20:35', 1, 'admin', '2026-05-26 16:20:37', 1, 3, 'admin', '2026-05-26 16:20:40', 1, 1, NULL, 36000.00, NULL),
(11, '2026-05-27', 5, '002/003', '2026052700001', '5852', 1, 7, 'Gents Floor', '2026-05-27 10:35:19', 0, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 1, 11, NULL, 131500.00, 'Nothing to say'),
(12, '2026-05-28', 4, 'Test1234', '2026052800001', '5200', 2, 6, 'Ladies Floor', '2026-05-28 12:20:09', 0, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, 0, 1, NULL, NULL, 1, 0, NULL, 283000.00, 'Nothing '),
(13, '2026-05-28', 5, '2544', '2026052800002', '5855', 3, 6, 'Gents Floor', '2026-05-28 13:04:44', 0, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 1, 3, NULL, 40044.00, 'adqe');

-- --------------------------------------------------------

--
-- Table structure for table `qc_item_images`
--

CREATE TABLE `qc_item_images` (
  `image_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_name` varchar(200) DEFAULT NULL,
  `image_size` int(11) DEFAULT NULL,
  `image_type` varchar(50) DEFAULT NULL,
  `uploaded_by` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_item_images`
--

INSERT INTO `qc_item_images` (`image_id`, `record_id`, `image_path`, `image_name`, `image_size`, `image_type`, `uploaded_by`, `uploaded_at`, `is_primary`) VALUES
(1, 5, 'uploads/qc_returns/QC_08814adc_1779352983.jpg', NULL, NULL, NULL, 'Super Administrator', '2026-05-21 08:43:03', 0);

-- --------------------------------------------------------

--
-- Table structure for table `qc_modes`
--

CREATE TABLE `qc_modes` (
  `mode_id` int(11) NOT NULL,
  `mode_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_modes`
--

INSERT INTO `qc_modes` (`mode_id`, `mode_name`, `description`, `is_active`, `created_at`) VALUES
(1, 'Inward QC', 'Quality check for incoming goods', 1, '2026-05-20 09:40:53'),
(2, 'Outward QC', 'Quality check for outgoing goods', 1, '2026-05-20 09:40:53'),
(3, 'RTV - Return to Vendor', 'Return defective items to vendor', 1, '2026-05-20 09:40:53'),
(4, 'RTS - Return to Supplier', 'Return items to supplier for replacement', 1, '2026-05-20 09:40:53'),
(5, 'Damage Claim', 'Claim for damaged goods', 1, '2026-05-20 09:40:53'),
(6, 'Shortage Claim', 'Claim for missing items', 1, '2026-05-20 09:40:53');

-- --------------------------------------------------------

--
-- Table structure for table `qc_return_reasons`
--

CREATE TABLE `qc_return_reasons` (
  `reason_id` int(11) NOT NULL,
  `reason_code` varchar(20) DEFAULT NULL,
  `reason_text` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_return_reasons`
--

INSERT INTO `qc_return_reasons` (`reason_id`, `reason_code`, `reason_text`, `category`, `is_active`, `sort_order`) VALUES
(1, 'DEF-001', 'No Reason', 'Manufacturing Defect', 1, 0),
(2, 'DEF-002', 'Oil Stain / Discoloration Patches', 'Quality Issue', 1, 0),
(3, 'DEF-003', 'Incorrect Sizing Label Deviation', 'Labeling Error', 1, 0),
(4, 'DEF-004', 'Missing Buttons / Broken Zippers', 'Hardware Defect', 1, 0),
(5, 'DEF-005', 'Fabric Shade Variation Variance', 'Color Issue', 1, 0),
(6, 'DEF-006', 'Wrong Item Shipped', 'Shipping Error', 1, 0),
(7, 'DEF-007', 'Quantity Mismatch', 'Quantity Issue', 1, 0),
(8, 'DEF-008', 'Damaged During Transit', 'Transit Damage', 1, 0),
(9, 'DEF-009', 'Expired Product', 'Expiry Issue', 1, 0),
(10, 'DEF-010', 'Packaging Damage', 'Packaging Issue', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `qc_tasks`
--

CREATE TABLE `qc_tasks` (
  `task_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `assigned_to` varchar(50) DEFAULT NULL,
  `assigned_by` varchar(50) DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `completed_date` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_reasons`
--

CREATE TABLE `return_reasons` (
  `reason_id` int(11) NOT NULL,
  `reason_text` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `return_reasons`
--

INSERT INTO `return_reasons` (`reason_id`, `reason_text`) VALUES
(1, 'Cut Damage'),
(2, 'Sleeve Piping Open'),
(3, 'Pen Mark'),
(4, 'Dirty Mark'),
(5, 'Tiyer'),
(6, 'Multiple Reason'),
(7, 'Fabric Damage'),
(8, 'Arm Hole Open'),
(9, 'Back Side Mark'),
(10, 'Dirty Mark at Body'),
(11, 'Button Missing');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `system_id` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `system_id`, `contact_number`, `email`, `address`) VALUES
(1, 'Apex Apparel Manufacturing', 'VND-APX-99', '94740890730', 'kavizzn@gmail.com', '125, Baseline Road, Colombo 05'),
(2, 'TexStyles Wholesale Distributors', 'VND-TEX-04', '+94819876543', 'orders@texstyles.com', '45/A, Katugastota Industrial Zone, Kandy'),
(3, 'Elite Fabrics & Trim Co.', 'VND-ELI-77', '+94914445556', 'supply@elitefabrics.lk', 'Galle Road, Ambalangoda'),
(4, 'City Line', '3110', '94740890730', 'kavindumalshan2003@gmail.com', 'Kandy Road, Kadawatha'),
(5, 'Focus Men', 'Test12', '94740890730', 'colombomc09@gmail.com', 'Galle Road, Kalutara'),
(6, 'Focus Men', 'Test12', '94740890730', 'colombomc09@gmail.com', 'Galle Road, Kalutara');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_invoices`
--

CREATE TABLE `supplier_invoices` (
  `invoice_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `floor_id` int(11) NOT NULL,
  `checked_date` date NOT NULL,
  `checker_name` varchar(100) NOT NULL,
  `added_by` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_invoices`
--

INSERT INTO `supplier_invoices` (`invoice_id`, `supplier_id`, `invoice_number`, `invoice_date`, `branch_id`, `floor_id`, `checked_date`, `checker_name`, `added_by`, `created_at`) VALUES
(1, 1, 'INV-910', '2026-05-01', 1, 1, '2026-05-07', 'Kasun', 'System Architect', '2026-05-19 15:20:01'),
(2, 1, 'INV-910 434', '2026-04-29', NULL, 1, '2026-05-13', 'Kasun Kasun', 'System Architect', '2026-05-19 16:07:48'),
(3, 2, 'INV-910 56522', '2026-05-07', NULL, 1, '2026-05-06', 'Kasun Edeedwerr', 'System Architect', '2026-05-19 17:24:50'),
(5, 4, '1643', '2026-05-18', NULL, 2, '2026-05-19', 'QC Tester', 'System Architect', '2026-05-20 07:00:02'),
(6, 1, '584255412', '2026-05-12', NULL, 2, '2026-05-21', 'Super Administrator', 'Super Administrator', '2026-05-21 08:40:40'),
(7, 5, '002/003', '2026-03-06', 5, 1, '2026-03-17', 'Kamal', 'System Architect', '2026-05-25 05:46:36'),
(8, 5, '010', '2026-03-06', 5, 1, '2026-03-16', 'Qc Gents', 'System Architect', '2026-05-26 10:25:47'),
(9, 4, 'Test1234', '2026-05-06', 5, 1, '2026-05-28', 'Kavindu Bogahawatte', 'admin', '2026-05-28 12:17:29'),
(13, 5, '2544', '2026-05-28', 4, 1, '2026-05-28', 'Kasun', 'admin', '2026-05-28 12:54:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','manager','user') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role`, `is_active`, `created_at`) VALUES
(1, 'admin', 'admin123', 'Administrator', 'vexelit.sl@gmail.com', 'admin', 1, '2026-05-25 07:09:16'),
(2, 'inform', 'inform', 'Supplier Informer', 'manager@asbfashion.com', 'user', 1, '2026-05-25 07:09:16'),
(3, 'user1', 'user123', 'Sarah User', 'user@asbfashion.com', 'user', 1, '2026-05-25 07:09:16'),
(4, 'quality_head', 'quality123', 'Michael Quality', 'quality@asbfashion.com', 'manager', 1, '2026-05-25 07:09:16'),
(5, 'inspector1', 'inspect123', 'David Inspector', 'inspector@asbfashion.com', 'user', 1, '2026-05-25 07:09:16'),
(6, 'return_supervisor', 'return123', 'Lisa Returns', 'returns@asbfashion.com', 'manager', 1, '2026-05-25 07:09:16'),
(7, 'procurement', 'procure123', 'Alex Procurement', 'procurement@asbfashion.com', 'user', 1, '2026-05-25 07:09:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_tabs`
--

CREATE TABLE `user_tabs` (
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tab_id` int(11) DEFAULT NULL,
  `tab_name` varchar(100) NOT NULL,
  `show_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `tab_url` varchar(255) NOT NULL,
  `tab_icon` varchar(50) DEFAULT 'fas fa-link',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_tabs`
--

INSERT INTO `user_tabs` (`assignment_id`, `user_id`, `tab_id`, `tab_name`, `show_name`, `description`, `tab_url`, `tab_icon`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 1, 1, 'dashboard', 'Dashboard', 'Main dashboard overview', 'dashboard.php', 'fas fa-tachometer-alt', 1, 1, '2026-05-25 07:27:37'),
(2, 1, 2, 'users', 'User Management', 'Manage system users', 'users.php', 'fas fa-users', 2, 1, '2026-05-25 07:27:37'),
(3, 1, 3, 'tab_assignments', 'Tab Assignments', 'Assign tabs to users', 'tab_assignments.php', 'fas fa-tasks', 3, 1, '2026-05-25 07:27:37'),
(4, 1, 4, 'suppliers', 'Suppliers', 'Manage supplier information', 'suppliers.php', 'fas fa-truck', 4, 1, '2026-05-25 07:27:37'),
(5, 1, 5, 'return_reasons', 'Return Reasons', 'Manage return reasons', 'return_reasons.php', 'fas fa-exchange-alt', 5, 1, '2026-05-25 07:27:37'),
(18, 1, 7, 'add_aql', 'ADD New AQL', 'Create New AQL Form and This Saved To Db Saved To Supplier_invoice.php', 'add_aql.php', 'fas fa-chart-line', 6, 1, '2026-05-25 07:44:22'),
(19, 1, 8, 'update_flag', 'Flag Update', 'update flags and can get print updated also', 'flag.php', 'fas fa-flag', 7, 1, '2026-05-25 07:49:28'),
(20, 1, 9, 'add_aql_to_return', 'AQL Assign To Return', 'AQL Repoet Return Added to New Return Note', 'qc_damage.php', 'fas fa-link', 8, 1, '2026-05-25 09:10:43'),
(21, 1, 10, 'supplier_inform', 'Supplier Inform', 'Inform Supplier to about Return items Via Sms and Email', 'supplier_invoices.php', 'fas fa-envelope', 9, 1, '2026-05-25 09:16:23'),
(22, 1, 12, 'add_new_return_not', 'Add New Return Note', 'Add new Qc Return Note Without Aql Report', 'qc_entry.php', 'fas fa-l', 99, 1, '2026-05-25 10:00:29'),
(23, 2, 8, 'update_flag', 'Flag Update', 'update flags and can get print updated also', 'flag.php', 'fas fa-flag', 10, 1, '2026-05-25 10:03:50'),
(24, 2, 10, 'supplier_inform', 'Supplier Inform', 'Inform Supplier to about Return items Via Sms and Email', 'supplier_invoices.php', 'fas fa-envelope', 16, 1, '2026-05-25 10:03:58'),
(25, 1, 13, 'qc_mode_management', 'QC Modes', 'QC Modes Add Edit View Delete Management', 'qc_modes.php', 'fas fa-microscope', 15, 1, '2026-05-25 10:14:59'),
(26, 1, 14, 'floor_management', 'Floors', 'Floors Add Edit Update And Delete Management', 'floors.php', 'fas fa-layer-group', 17, 1, '2026-05-25 10:26:41'),
(27, 1, 15, 'qc_return_audit_report', 'Return Audit Report', 'Print Qc Return Audit Report', 'qc_return_report.php', 'fas fa-file-invoice-dollar', 15, 1, '2026-05-25 11:28:16'),
(28, 1, 16, 'sticker_print', 'Print Stickers', 'According to Return Note to Print Stickers', 'print_records.php', 'fas fa-unlink', 19, 1, '2026-05-26 10:49:35');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_invoice_returns`
-- (See below for the actual view)
--
CREATE TABLE `view_invoice_returns` (
`invoice_id` int(11)
,`invoice_number` varchar(50)
,`invoice_date` date
,`supplier_name` varchar(100)
,`supplier_id` int(11)
,`total_items` bigint(21)
,`total_received` decimal(32,0)
,`total_defects` decimal(32,0)
,`total_returns` decimal(32,0)
,`failed_items` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_qc_returns_summary`
-- (See below for the actual view)
--
CREATE TABLE `view_qc_returns_summary` (
`record_id` int(11)
,`record_date` date
,`supplier_id` int(11)
,`supplier_name` varchar(100)
,`supplier_system_id` varchar(50)
,`invoice_number` varchar(50)
,`reference_number` varchar(50)
,`doc_number` varchar(50)
,`mode_name` varchar(50)
,`total_items` bigint(21)
,`total_quantity` decimal(32,0)
,`total_value` decimal(37,2)
,`total_images` bigint(21)
,`is_informed` tinyint(1)
,`is_store_received` tinyint(1)
,`is_gate_cleared` tinyint(1)
,`is_handover_complete` tinyint(1)
,`current_status` varchar(14)
,`added_by_user` varchar(50)
,`added_time` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `view_invoice_returns`
--
DROP TABLE IF EXISTS `view_invoice_returns`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_invoice_returns`  AS SELECT `si`.`invoice_id` AS `invoice_id`, `si`.`invoice_number` AS `invoice_number`, `si`.`invoice_date` AS `invoice_date`, `s`.`supplier_name` AS `supplier_name`, `s`.`supplier_id` AS `supplier_id`, count(`ii`.`invoice_item_id`) AS `total_items`, sum(`ii`.`received_qty`) AS `total_received`, sum(`ii`.`defect_qty`) AS `total_defects`, sum(`ii`.`return_qty`) AS `total_returns`, count(distinct case when `ii`.`status` = 'FAIL' then `ii`.`invoice_item_id` end) AS `failed_items` FROM ((`supplier_invoices` `si` left join `suppliers` `s` on(`si`.`supplier_id` = `s`.`supplier_id`)) left join `invoice_items` `ii` on(`si`.`invoice_id` = `ii`.`invoice_id`)) GROUP BY `si`.`invoice_id` ;

-- --------------------------------------------------------

--
-- Structure for view `view_qc_returns_summary`
--
DROP TABLE IF EXISTS `view_qc_returns_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_qc_returns_summary`  AS SELECT `dm`.`record_id` AS `record_id`, `dm`.`record_date` AS `record_date`, `dm`.`supplier_id` AS `supplier_id`, `s`.`supplier_name` AS `supplier_name`, `s`.`system_id` AS `supplier_system_id`, `dm`.`invoice_number` AS `invoice_number`, `dm`.`reference_number` AS `reference_number`, `dm`.`doc_number` AS `doc_number`, `m`.`mode_name` AS `mode_name`, count(distinct `di`.`item_id`) AS `total_items`, sum(`di`.`quantity`) AS `total_quantity`, sum(`di`.`total_cost`) AS `total_value`, count(distinct `img`.`image_id`) AS `total_images`, `dm`.`is_informed` AS `is_informed`, `dm`.`is_store_received` AS `is_store_received`, `dm`.`is_gate_cleared` AS `is_gate_cleared`, `dm`.`is_handover_complete` AS `is_handover_complete`, CASE WHEN `dm`.`is_handover_complete` = 1 THEN 'Completed' WHEN `dm`.`is_gate_cleared` = 1 THEN 'Gate Cleared' WHEN `dm`.`is_store_received` = 1 THEN 'Store Received' WHEN `dm`.`is_informed` = 1 THEN 'Informed' ELSE 'New' END AS `current_status`, `dm`.`added_by_user` AS `added_by_user`, `dm`.`added_time` AS `added_time` FROM ((((`qc_damage_main` `dm` left join `suppliers` `s` on(`dm`.`supplier_id` = `s`.`supplier_id`)) left join `qc_modes` `m` on(`dm`.`mode_id` = `m`.`mode_id`)) left join `qc_damage_items` `di` on(`dm`.`record_id` = `di`.`record_id`)) left join `qc_item_images` `img` on(`dm`.`record_id` = `img`.`record_id`)) GROUP BY `dm`.`record_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`branch_id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`);

--
-- Indexes for table `floors`
--
ALTER TABLE `floors`
  ADD PRIMARY KEY (`floor_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`invoice_item_id`),
  ADD KEY `fk_ii_invoice` (`invoice_id`),
  ADD KEY `fk_ii_item` (`item_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `unique_item_code` (`item_code`),
  ADD KEY `fk_items_supplier` (`supplier_id`);

--
-- Indexes for table `item_return_reasons`
--
ALTER TABLE `item_return_reasons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_reason` (`invoice_item_id`,`reason_id`),
  ADD KEY `fk_irr_reason` (`reason_id`);

--
-- Indexes for table `master_tabs`
--
ALTER TABLE `master_tabs`
  ADD PRIMARY KEY (`tab_id`),
  ADD UNIQUE KEY `tab_name` (`tab_name`);

--
-- Indexes for table `qc_audit_log`
--
ALTER TABLE `qc_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_record_id` (`record_id`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `qc_damage_items`
--
ALTER TABLE `qc_damage_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_record_id` (`record_id`);

--
-- Indexes for table `qc_damage_main`
--
ALTER TABLE `qc_damage_main`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_mode_id` (`mode_id`),
  ADD KEY `idx_reason_id` (`reason_id`),
  ADD KEY `idx_record_date` (`record_date`),
  ADD KEY `idx_is_informed` (`is_informed`),
  ADD KEY `idx_is_store_received` (`is_store_received`),
  ADD KEY `idx_is_gate_cleared` (`is_gate_cleared`),
  ADD KEY `idx_is_handover_complete` (`is_handover_complete`),
  ADD KEY `idx_invoice_supplier` (`invoice_number`,`supplier_id`),
  ADD KEY `idx_flag_portal_main` (`record_date`,`is_handover_complete`,`supplier_id`,`record_id`),
  ADD KEY `idx_search_reference` (`reference_number`,`invoice_number`),
  ADD KEY `idx_pending_flag_count` (`is_handover_complete`),
  ADD KEY `idx_record_id_desc` (`record_id`),
  ADD KEY `idx_covering_flag_portal` (`record_date`,`supplier_id`,`is_handover_complete`,`record_id`,`reference_number`,`invoice_number`,`doc_number`,`mode_id`,`is_informed`,`is_store_received`,`is_gate_cleared`),
  ADD KEY `idx_search_all` (`reference_number`,`invoice_number`,`doc_number`),
  ADD KEY `idx_stats_date_status` (`record_date`,`is_handover_complete`),
  ADD KEY `idx_where_pattern` (`record_date`,`is_handover_complete`,`supplier_id`),
  ADD KEY `idx_join_supplier` (`supplier_id`,`record_date`,`is_handover_complete`);

--
-- Indexes for table `qc_item_images`
--
ALTER TABLE `qc_item_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `idx_record_id` (`record_id`);

--
-- Indexes for table `qc_modes`
--
ALTER TABLE `qc_modes`
  ADD PRIMARY KEY (`mode_id`);

--
-- Indexes for table `qc_return_reasons`
--
ALTER TABLE `qc_return_reasons`
  ADD PRIMARY KEY (`reason_id`);

--
-- Indexes for table `qc_tasks`
--
ALTER TABLE `qc_tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `idx_record_id` (`record_id`),
  ADD KEY `idx_assigned_to` (`assigned_to`);

--
-- Indexes for table `return_reasons`
--
ALTER TABLE `return_reasons`
  ADD PRIMARY KEY (`reason_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `unique_invoice_num` (`invoice_number`),
  ADD KEY `fk_si_branch` (`branch_id`),
  ADD KEY `fk_si_floor` (`floor_id`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_supplier_id` (`supplier_id`),
  ADD KEY `idx_invoice_date` (`invoice_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_tabs`
--
ALTER TABLE `user_tabs`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tab_id` (`tab_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `branch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `floors`
--
ALTER TABLE `floors`
  MODIFY `floor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `invoice_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `item_return_reasons`
--
ALTER TABLE `item_return_reasons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `master_tabs`
--
ALTER TABLE `master_tabs`
  MODIFY `tab_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `qc_audit_log`
--
ALTER TABLE `qc_audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `qc_damage_items`
--
ALTER TABLE `qc_damage_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `qc_damage_main`
--
ALTER TABLE `qc_damage_main`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `qc_item_images`
--
ALTER TABLE `qc_item_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qc_modes`
--
ALTER TABLE `qc_modes`
  MODIFY `mode_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `qc_return_reasons`
--
ALTER TABLE `qc_return_reasons`
  MODIFY `reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `qc_tasks`
--
ALTER TABLE `qc_tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_reasons`
--
ALTER TABLE `return_reasons`
  MODIFY `reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_tabs`
--
ALTER TABLE `user_tabs`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `supplier_invoices` (`invoice_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ii_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON UPDATE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON UPDATE CASCADE;

--
-- Constraints for table `item_return_reasons`
--
ALTER TABLE `item_return_reasons`
  ADD CONSTRAINT `fk_irr_invoice_item` FOREIGN KEY (`invoice_item_id`) REFERENCES `invoice_items` (`invoice_item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_irr_reason` FOREIGN KEY (`reason_id`) REFERENCES `return_reasons` (`reason_id`) ON UPDATE CASCADE;

--
-- Constraints for table `qc_damage_items`
--
ALTER TABLE `qc_damage_items`
  ADD CONSTRAINT `fk_qc_damage_items_record` FOREIGN KEY (`record_id`) REFERENCES `qc_damage_main` (`record_id`) ON DELETE CASCADE;

--
-- Constraints for table `qc_damage_main`
--
ALTER TABLE `qc_damage_main`
  ADD CONSTRAINT `fk_qc_damage_main_mode` FOREIGN KEY (`mode_id`) REFERENCES `qc_modes` (`mode_id`) ON DELETE SET NULL;

--
-- Constraints for table `qc_item_images`
--
ALTER TABLE `qc_item_images`
  ADD CONSTRAINT `fk_qc_item_images_record` FOREIGN KEY (`record_id`) REFERENCES `qc_damage_main` (`record_id`) ON DELETE CASCADE;

--
-- Constraints for table `qc_tasks`
--
ALTER TABLE `qc_tasks`
  ADD CONSTRAINT `fk_qc_tasks_record` FOREIGN KEY (`record_id`) REFERENCES `qc_damage_main` (`record_id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD CONSTRAINT `fk_si_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_si_floor` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`floor_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_si_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON UPDATE CASCADE;

--
-- Constraints for table `user_tabs`
--
ALTER TABLE `user_tabs`
  ADD CONSTRAINT `user_tabs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_tabs_ibfk_2` FOREIGN KEY (`tab_id`) REFERENCES `master_tabs` (`tab_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
