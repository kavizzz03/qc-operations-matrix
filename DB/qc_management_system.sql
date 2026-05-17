-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2026 at 07:45 PM
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
-- Database: `qc_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Floor 01 - Cutting'),
(2, 'Floor 02 - Finishing');

-- --------------------------------------------------------

--
-- Table structure for table `floor_assignments`
--

CREATE TABLE `floor_assignments` (
  `assignment_id` int(11) NOT NULL,
  `floor_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `floor_assignments`
--

INSERT INTO `floor_assignments` (`assignment_id`, `floor_id`, `user_id`) VALUES
(1, 1, 2),
(2, 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `qc_admins`
--

CREATE TABLE `qc_admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `pass_key` varchar(255) NOT NULL,
  `admin_level` enum('master','staff') DEFAULT 'staff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_admins`
--

INSERT INTO `qc_admins` (`admin_id`, `username`, `pass_key`, `admin_level`) VALUES
(1, 'kavindu', 'admin123', 'master');

-- --------------------------------------------------------

--
-- Table structure for table `qc_damage_items`
--

CREATE TABLE `qc_damage_items` (
  `item_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `item_code` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_damage_items`
--

INSERT INTO `qc_damage_items` (`item_id`, `record_id`, `item_code`, `quantity`, `unit_cost`) VALUES
(4, 2, 'FAB-DENIM-01', 12, 0.00),
(5, 3, '33erfdddvdc', 2, 0.00),
(6, 3, 'eterfwedfdw', 4, 0.00),
(7, 3, 'ffwerffd', 33, 0.00),
(8, 2, 'TSH-BLK-001', 5, 0.00),
(9, 2, 'TSH-WHT-002', 12, 0.00),
(10, 2, 'DEN-BLU-M', 8, 0.00),
(11, 2, 'SHR-LIN-S', 15, 0.00),
(12, 2, 'PNT-CRG-32', 4, 0.00),
(13, 2, 'JCK-LTH-L', 2, 0.00),
(14, 2, 'SOCKS-GRY-PK', 24, 0.00),
(15, 2, 'BELT-BRN-XL', 3, 0.00),
(16, 2, 'CAP-RED-UNI', 10, 0.00),
(17, 2, 'SHOE-SNEAK-42', 6, 0.00),
(18, 2, 'HOOD-OVR-GRN', 7, 0.00),
(19, 2, 'V-NECK-YEL', 9, 0.00),
(20, 2, 'POLO-NAV-M', 11, 0.00),
(21, 2, 'SHORT-COT-S', 20, 0.00),
(22, 2, 'TIE-SLK-RED', 5, 0.00),
(38, 4, 'ITEM_CODE_1', 10, 1500.00),
(39, 4, 'ITEM_CODE_2', 5, 2200.00),
(40, 4, 'ITEM_CODE_3', 12, 950.00),
(41, 4, 'ITEM_CODE_4', 8, 3100.00),
(42, 4, 'ITEM_CODE_5', 20, 450.00),
(43, 4, 'ITEM_CODE_6', 15, 1200.00),
(44, 4, 'ITEM_CODE_7', 7, 5000.00),
(45, 4, 'ITEM_CODE_8', 3, 1800.00),
(46, 4, 'ITEM_CODE_9', 11, 2500.00),
(47, 4, 'ITEM_CODE_10', 9, 1350.00),
(48, 4, 'ITEM_CODE_11', 14, 800.00),
(49, 4, 'ITEM_CODE_12', 6, 4200.00),
(50, 4, 'ITEM_CODE_13', 2, 6000.00),
(51, 4, 'ITEM_CODE_14', 18, 750.00),
(52, 4, 'ITEM_CODE_15', 4, 3300.00),
(53, 4, 'ITEM_CODE_16', 13, 1100.00),
(54, 4, 'ITEM_CODE_17', 25, 200.00),
(55, 5, 'test_item_1', 2, 100.00),
(56, 5, 'test_item_2', 2, 200.00),
(57, 5, 'test_item_3', 2, 400.00),
(58, 5, 'test_item_4', 1, 500.00),
(59, 5, 'test_item_5', 3, 100.00),
(60, 5, 'test_item_6', 2, 1000.00),
(61, 5, 'test_item_7', 20, 50.00),
(62, 5, 'test_item_8', 4, 100.00),
(63, 5, 'test_item_9', 2, 100.00),
(64, 5, 'test_item_10', 1, 10000.00),
(65, 5, 'test_item_11', 60, 5000.00),
(66, 5, 'test_item_12', 12, 100.00),
(67, 5, 'test_item_13', 1, 100.00),
(68, 5, 'test_item_14', 2, 399.97),
(69, 5, 'test_item_15', 3, 444.00),
(70, 5, 'test_item_16', 2, 422.00),
(71, 5, 'test_item_17', 1, 2554.85),
(72, 5, 'test_item_18', 4, 2555.00),
(73, 5, 'test_item_19', 45, 4745.00),
(74, 5, 'test_item_20', 56, 555.00);

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
  `handover_user` varchar(50) DEFAULT NULL,
  `handover_datetime` datetime DEFAULT NULL,
  `is_active_cart` tinyint(1) DEFAULT 1,
  `print_count` int(11) DEFAULT 0,
  `assigned_task_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_damage_main`
--

INSERT INTO `qc_damage_main` (`record_id`, `record_date`, `supplier_id`, `invoice_number`, `reference_number`, `doc_number`, `mode_id`, `reason_id`, `added_by_user`, `added_time`, `is_informed`, `informed_by_user`, `informed_datetime`, `is_store_received`, `store_user`, `store_datetime`, `is_gate_cleared`, `gate_user`, `gate_datetime`, `is_handover_complete`, `handover_user`, `handover_datetime`, `is_active_cart`, `print_count`, `assigned_task_id`) VALUES
(2, '2026-05-15', 2, 'INV-910', 'ASB-RET-002', NULL, NULL, NULL, 'nimmi_qc', '2026-05-15 04:17:58', 1, 'kavindu_admin', '2026-05-15 12:34:22', 1, 'kavindu_admin', '2026-05-15 12:34:42', 1, 'kavindu_admin', '2026-05-15 12:13:12', 1, 'kavindu_admin', '2026-05-15 12:37:05', 1, 3, NULL),
(3, '2026-05-15', 1, '3444448r8r48', 'ASB-20260515-0002', NULL, NULL, NULL, 'kavindu_admin', '2026-05-15 05:15:25', 1, 'kavindu_admin', '2026-05-15 12:16:28', 1, 'kavindu_admin', '2026-05-15 12:18:59', 1, 'kavindu_admin', '2026-05-15 12:37:12', 1, 'Super Administrator', '2026-05-17 00:22:26', 1, 4, NULL),
(4, '2026-05-15', 1, '1005554555', '202605150003', NULL, NULL, NULL, 'kavindu_admin', '2026-05-15 07:50:43', 1, 'kavindu_admin', '2026-05-15 15:46:49', 1, 'Super Administrator', '2026-05-17 22:42:46', 1, 'Super Administrator', '2026-05-17 22:41:52', 1, 'Super Administrator', '2026-05-17 22:46:26', 1, 9, NULL),
(5, '2026-05-16', 4, 'INV_Test_1223', '202605160001', 'DOC_Test_123', 3, 5, 'kavindu_admin', '2026-05-16 18:03:31', 1, 'asbit', '2026-05-17 23:04:43', 1, 'asbit', '2026-05-17 23:04:47', 1, 'asbit', '2026-05-17 23:04:51', 1, 'asbit', '2026-05-17 23:04:55', 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `qc_item_images`
--

CREATE TABLE `qc_item_images` (
  `image_id` int(11) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_item_images`
--

INSERT INTO `qc_item_images` (`image_id`, `record_id`, `image_path`) VALUES
(1, 3, 'uploads/qc_returns/QC_1778822125_0.png'),
(2, 4, 'uploads/qc_returns/QC_09cb92f9_1778831443.png'),
(3, 4, 'uploads/qc_returns/QC_8f755e86_1778831443.png'),
(4, 4, 'uploads/qc_returns/QC_97301069_1778831443.jpg'),
(5, 5, 'uploads/qc_returns/QC_8081528c_1778954611.jpg'),
(6, 5, 'uploads/qc_returns/QC_86bff709_1778954611.jpg'),
(7, 5, 'uploads/qc_returns/QC_b2f7a89c_1778954611.png');

-- --------------------------------------------------------

--
-- Table structure for table `qc_modes`
--

CREATE TABLE `qc_modes` (
  `mode_id` int(11) NOT NULL,
  `mode_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_modes`
--

INSERT INTO `qc_modes` (`mode_id`, `mode_name`) VALUES
(1, 'QC Return'),
(2, 'Shop Return'),
(3, 'Test mode');

-- --------------------------------------------------------

--
-- Table structure for table `qc_reasons`
--

CREATE TABLE `qc_reasons` (
  `reason_id` int(11) NOT NULL,
  `reason_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qc_reasons`
--

INSERT INTO `qc_reasons` (`reason_id`, `reason_name`) VALUES
(1, 'Damage'),
(2, 'Quantity Minor'),
(3, 'Quantity Higher'),
(4, 'Cost Price Change'),
(5, 'Test Reason');

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
(1, 'Star Textiles', 'ST-9920', '0112345678', 'info@startex.lk', 'Industrial Zone, Ratmalana'),
(2, 'Elegant Buttons', 'EB-4412', '0771234567', 'sales@elegant.com', 'Galle Road, Colombo 03'),
(4, 'Vexel IT (PVT) LTD', '5200', '94740890730', 'kavizzn@gmail.com', 'High Level Road, Thunnnana, Hanwella');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `task_name` varchar(100) NOT NULL,
  `task_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `task_name`, `task_code`, `description`, `is_active`, `created_at`) VALUES
(1, 'Quality Control Entry', 'QC_ENTRY', 'Create new QC damage entries and manage quality control records', 1, '2026-05-16 18:27:32'),
(2, 'Quality Control Queue', 'QC_QUEUE', 'View and manage live QC queue, process pending items', 1, '2026-05-16 18:27:32'),
(3, 'Report Engine', 'REPORT_ENGINE', 'Access, generate, and export system reports', 1, '2026-05-16 18:27:32'),
(4, 'Supplier Management', 'SUPPLIER_MGMT', 'Manage supplier information, contacts, and profiles', 1, '2026-05-16 18:27:32'),
(5, 'Workflow Mode Management', 'MODE_MGMT', 'Add, edit, and delete workflow modes for QC process', 1, '2026-05-16 18:27:32'),
(6, 'Damage Reason Management', 'REASON_MGMT', 'Manage damage reason codes and descriptions', 1, '2026-05-16 18:27:32'),
(7, 'User Management', 'USER_MGMT', 'Create, edit, delete system users and assign tasks', 1, '2026-05-16 18:27:32'),
(8, 'Password Management', 'PWD_MGMT', 'Change user passwords and manage credentials', 1, '2026-05-16 18:27:32'),
(9, 'Super Admin Panel', 'SUPER_ADMIN', 'Access super administrator control panel', 1, '2026-05-16 18:38:53'),
(10, 'System Backup', 'SYS_BACKUP', 'Perform system backups and maintenance', 1, '2026-05-16 18:38:53'),
(11, 'Audit Logs', 'AUDIT_LOGS', 'View system audit trails and user activities', 1, '2026-05-16 18:38:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `total_logins` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `name`, `role`, `is_active`, `last_login`, `last_login_ip`, `total_logins`) VALUES
(1, 'admin', 'Asb123', 'Kavindu_Dev', 'Super Admin', 1, '2026-05-17 22:30:44', '::1', 1),
(2, 'saman_qc', 'qc123', 'Saman Kumara', 'QC', 1, NULL, NULL, 0),
(3, 'nimmi_qc', 'qc456', 'Nimmi Perera', 'QC', 1, '2026-05-17 23:06:10', '::1', 2),
(5, 'asbit', 'asb123', 'ASB IT DEPT', 'Admin', 1, '2026-05-17 23:02:58', '::1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_login_logs`
--

CREATE TABLE `user_login_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_status` enum('success','failed') DEFAULT 'success',
  `session_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_login_logs`
--

INSERT INTO `user_login_logs` (`log_id`, `user_id`, `login_time`, `ip_address`, `user_agent`, `login_status`, `session_id`) VALUES
(1, 3, '2026-05-17 22:28:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'p0ru7h29jauolj0637r5uept9m'),
(2, 1, '2026-05-17 22:30:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'srf5vfdfrvpmin9rs3b60g6iv0'),
(3, 5, '2026-05-17 23:02:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', '4eh04lf8f7e146qtl7mopsveul'),
(4, 3, '2026-05-17 23:06:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'success', 'pps2qdut06amsdklrlm0p0kksh');

-- --------------------------------------------------------

--
-- Table structure for table `user_tasks`
--

CREATE TABLE `user_tasks` (
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_tasks`
--

INSERT INTO `user_tasks` (`assignment_id`, `user_id`, `task_id`, `assigned_by`, `assigned_at`) VALUES
(4, 3, 1, 1, '2026-05-17 16:50:30'),
(5, 3, 2, 1, '2026-05-17 16:50:30'),
(6, 3, 3, 1, '2026-05-17 16:50:30'),
(7, 3, 4, 1, '2026-05-17 16:50:30'),
(8, 5, 1, 1, '2026-05-17 17:32:24'),
(9, 5, 2, 1, '2026-05-17 17:32:24'),
(10, 5, 3, 1, '2026-05-17 17:32:24'),
(11, 5, 4, 1, '2026-05-17 17:32:24'),
(12, 5, 5, 1, '2026-05-17 17:32:24'),
(13, 5, 6, 1, '2026-05-17 17:32:24'),
(14, 5, 7, 1, '2026-05-17 17:32:24'),
(15, 5, 8, 1, '2026-05-17 17:32:24'),
(16, 5, 9, 1, '2026-05-17 17:32:24'),
(17, 5, 10, 1, '2026-05-17 17:32:24'),
(18, 5, 11, 1, '2026-05-17 17:32:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `floors`
--
ALTER TABLE `floors`
  ADD PRIMARY KEY (`floor_id`);

--
-- Indexes for table `floor_assignments`
--
ALTER TABLE `floor_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `floor_id` (`floor_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `qc_admins`
--
ALTER TABLE `qc_admins`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `qc_damage_items`
--
ALTER TABLE `qc_damage_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `record_id` (`record_id`);

--
-- Indexes for table `qc_damage_main`
--
ALTER TABLE `qc_damage_main`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `reference_number` (`reference_number`),
  ADD KEY `invoice_number` (`invoice_number`),
  ADD KEY `record_date` (`record_date`),
  ADD KEY `idx_ref` (`reference_number`),
  ADD KEY `idx_inv` (`invoice_number`),
  ADD KEY `idx_date` (`record_date`),
  ADD KEY `fk_qc_main_mode` (`mode_id`),
  ADD KEY `fk_qc_main_reason` (`reason_id`),
  ADD KEY `assigned_task_id` (`assigned_task_id`);

--
-- Indexes for table `qc_item_images`
--
ALTER TABLE `qc_item_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `record_id` (`record_id`);

--
-- Indexes for table `qc_modes`
--
ALTER TABLE `qc_modes`
  ADD PRIMARY KEY (`mode_id`);

--
-- Indexes for table `qc_reasons`
--
ALTER TABLE `qc_reasons`
  ADD PRIMARY KEY (`reason_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD UNIQUE KEY `task_name` (`task_name`),
  ADD UNIQUE KEY `task_code` (`task_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_login_logs`
--
ALTER TABLE `user_login_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `login_time` (`login_time`);

--
-- Indexes for table `user_tasks`
--
ALTER TABLE `user_tasks`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `unique_user_task` (`user_id`,`task_id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `floors`
--
ALTER TABLE `floors`
  MODIFY `floor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `floor_assignments`
--
ALTER TABLE `floor_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `qc_admins`
--
ALTER TABLE `qc_admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `qc_damage_items`
--
ALTER TABLE `qc_damage_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `qc_damage_main`
--
ALTER TABLE `qc_damage_main`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `qc_item_images`
--
ALTER TABLE `qc_item_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `qc_modes`
--
ALTER TABLE `qc_modes`
  MODIFY `mode_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `qc_reasons`
--
ALTER TABLE `qc_reasons`
  MODIFY `reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_login_logs`
--
ALTER TABLE `user_login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_tasks`
--
ALTER TABLE `user_tasks`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `floor_assignments`
--
ALTER TABLE `floor_assignments`
  ADD CONSTRAINT `floor_assignments_ibfk_1` FOREIGN KEY (`floor_id`) REFERENCES `floors` (`floor_id`),
  ADD CONSTRAINT `floor_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `qc_damage_items`
--
ALTER TABLE `qc_damage_items`
  ADD CONSTRAINT `qc_damage_items_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `qc_damage_main` (`record_id`) ON DELETE CASCADE;

--
-- Constraints for table `qc_damage_main`
--
ALTER TABLE `qc_damage_main`
  ADD CONSTRAINT `fk_qc_main_mode` FOREIGN KEY (`mode_id`) REFERENCES `qc_modes` (`mode_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_qc_main_reason` FOREIGN KEY (`reason_id`) REFERENCES `qc_reasons` (`reason_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `qc_damage_main_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  ADD CONSTRAINT `qc_damage_main_ibfk_2` FOREIGN KEY (`assigned_task_id`) REFERENCES `tasks` (`task_id`);

--
-- Constraints for table `qc_item_images`
--
ALTER TABLE `qc_item_images`
  ADD CONSTRAINT `qc_item_images_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `qc_damage_main` (`record_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_login_logs`
--
ALTER TABLE `user_login_logs`
  ADD CONSTRAINT `user_login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_tasks`
--
ALTER TABLE `user_tasks`
  ADD CONSTRAINT `user_tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_tasks_ibfk_2` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_tasks_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
