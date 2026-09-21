-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 19, 2026 at 01:55 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cooperative_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounting_mappings`
--

CREATE TABLE `accounting_mappings` (
  `id` varchar(50) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `debit_account_id` varchar(50) NOT NULL,
  `credit_account_id` varchar(50) NOT NULL,
  `is_system` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `accounting_mappings`
--

INSERT INTO `accounting_mappings` (`id`, `event_type`, `description`, `debit_account_id`, `credit_account_id`, `is_system`) VALUES
('map_241d41ac4f77', 'SHARE_CAPITAL_PAYMENT', 'Member adds capital build-up', 'coa_6d6a02085527', 'coa_fffe59b422da', 0),
('map_3ef07510cd67', 'SAVINGS_WITHDRAWAL', 'Member withdraws cash from savings account', 'coa_45dd7932537a', 'coa_6d6a02085527', 0),
('map_6a0ffa4aea57', 'TESTING_DEBIT_CREDIT', 'test if Cash and Cash Equivalents will credited and Cash on hand tellers will be debited', 'coa_6d6a02085527', 'coa_76ca285ad3ae', 0),
('map_7b2f31f31de2', 'LOAN_PAYMENT', 'Collection of loan installment principal', 'coa_6d6a02085527', 'coa_c2fe2e2c751d', 0),
('map_e537df71452f', 'SAVINGS_DEPOSIT', 'Member deposits cash into savings account', 'coa_6d6a02085527', 'coa_45dd7932537a', 0);

-- --------------------------------------------------------

--
-- Table structure for table `accounting_periods`
--

CREATE TABLE `accounting_periods` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `fiscal_year` int NOT NULL,
  `period_number` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Open','Closed','Locked') DEFAULT 'Open',
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `accounting_periods`
--

INSERT INTO `accounting_periods` (`id`, `name`, `fiscal_year`, `period_number`, `start_date`, `end_date`, `status`, `closed_at`, `closed_by`, `created_at`) VALUES
('period_2026_01', 'January 2026', 2026, 1, '2026-01-01', '2026-01-31', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_02', 'February 2026', 2026, 2, '2026-02-01', '2026-02-28', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_03', 'March 2026', 2026, 3, '2026-03-01', '2026-03-31', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_04', 'April 2026', 2026, 4, '2026-04-01', '2026-04-30', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_05', 'May 2026', 2026, 5, '2026-05-01', '2026-05-31', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_06', 'June 2026', 2026, 6, '2026-06-01', '2026-06-30', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_07', 'July 2026', 2026, 7, '2026-07-01', '2026-07-31', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_08', 'August 2026', 2026, 8, '2026-08-01', '2026-08-31', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_09', 'September 2026', 2026, 9, '2026-09-01', '2026-09-30', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_10', 'October 2026', 2026, 10, '2026-10-01', '2026-10-31', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_11', 'November 2026', 2026, 11, '2026-11-01', '2026-11-30', 'Open', NULL, NULL, '2026-09-12 08:07:46'),
('period_2026_12', 'December 2026', 2026, 12, '2026-12-01', '2026-12-31', 'Open', NULL, NULL, '2026-09-12 08:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `approval_rules`
--

CREATE TABLE `approval_rules` (
  `id` varchar(50) NOT NULL,
  `workflow_id` varchar(50) NOT NULL,
  `step_order` int NOT NULL,
  `approver_role` varchar(50) NOT NULL,
  `min_amount` decimal(15,2) DEFAULT '0.00',
  `max_amount` decimal(15,2) DEFAULT NULL,
  `requires_board_action` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `approval_rules`
--

INSERT INTO `approval_rules` (`id`, `workflow_id`, `step_order`, `approver_role`, `min_amount`, `max_amount`, `requires_board_action`) VALUES
('rule_loan_01', 'wf_loan_standard', 1, 'role_credit_officer', 0.00, 50000.00, 0),
('rule_loan_02', 'wf_loan_standard', 2, 'role_manager', 50000.01, 150000.00, 0),
('rule_loan_03', 'wf_loan_standard', 3, 'role_bod', 150000.01, 9999999.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `approval_workflows`
--

CREATE TABLE `approval_workflows` (
  `id` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` text,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `approval_workflows`
--

INSERT INTO `approval_workflows` (`id`, `name`, `module`, `description`, `active`, `created_at`) VALUES
('wf_loan_standard', 'Standard Loan Approval Matrix', 'Loans', 'Tiered approval from Credit Officer up to Board of Directors', 1, '2026-09-12 08:07:46'),
('wf_mem_standard', 'Member Admission Workflow', 'Members', 'Branch manager screening and Board validation', 1, '2026-09-12 08:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `is_main_branch` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `code`, `name`, `address`, `contact_number`, `manager_name`, `is_main_branch`, `active`, `created_at`) VALUES
('branch_sfe', 'SFE', 'San Fernando La Union Branch', 'Quezon Ave, Catbangen, City of San Fernando, La Union', '+63 (072) 888-4321', 'Eduardo M. Bautista', 0, 1, '2026-09-12 08:07:46'),
('branch_tar', 'TAR', 'Main Tarlac Central Branch', 'Plaza Mabini Commercial Arcade, Tarlac City, Tarlac', '+63 (045) 982-1144', 'Ricardo P. Manalili', 1, 1, '2026-09-12 08:07:46'),
('branch_urd', 'URD', 'Urdaneta Pangasinan Branch', 'MacArthur Highway, Nancayasan, Urdaneta City, Pangasinan', '+63 (075) 568-2200', 'Grace L. Tan', 0, 1, '2026-09-12 08:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `cash_accounts`
--

CREATE TABLE `cash_accounts` (
  `id` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `branch_id` varchar(50) NOT NULL,
  `gl_account_id` varchar(50) NOT NULL,
  `opening_balance` decimal(15,2) DEFAULT '0.00',
  `current_balance` decimal(15,2) DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'PHP',
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cash_accounts`
--

INSERT INTO `cash_accounts` (`id`, `name`, `account_number`, `bank_name`, `branch_id`, `gl_account_id`, `opening_balance`, `current_balance`, `currency`, `active`) VALUES
('cash_01', 'Cash on Hand - Teller 1', 'COH-TAR-01', 'Cash Vault Drawer', 'branch_tar', 'coa_6d6a02085527', 0.00, 0.00, 'PHP', 1),
('cash_914fea60b47f', 'Maya', 'MYA-0988-1122', 'Maya Philippines, Inc.', 'branch_sfe', 'coa_0d59b6f2ac06', 500000.00, 500000.00, 'PHP', 1),
('cash_a97d265a3533', 'Main Source', 'COH-SFE-02', 'Cash Drawer Float', 'branch_sfe', 'coa_76ca285ad3ae', 5000000.00, 5000000.00, 'PHP', 1),
('cash_c23cc6202fa4', 'Main Vault Reserve', 'VLT-SFE-01', 'San Fernando La Union Branch Vault Safety Depository', 'branch_tar', 'coa_91a73de6ef12', 50000.00, 50000.00, 'PHP', 1);

-- --------------------------------------------------------

--
-- Table structure for table `cash_transactions`
--

CREATE TABLE `cash_transactions` (
  `id` varchar(50) NOT NULL,
  `transaction_no` varchar(100) NOT NULL,
  `cash_account_id` varchar(50) NOT NULL,
  `type` enum('INFLOW','OUTFLOW','TRANSFER') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` varchar(50) DEFAULT NULL,
  `description` text,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chart_of_accounts`
--

CREATE TABLE `chart_of_accounts` (
  `id` varchar(50) NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
  `normal_balance` enum('Debit','Credit') NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `parent_account_id` varchar(50) DEFAULT NULL,
  `report_group` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `chart_of_accounts`
--

INSERT INTO `chart_of_accounts` (`id`, `account_code`, `name`, `category`, `normal_balance`, `is_active`, `parent_account_id`, `report_group`, `description`, `created_at`) VALUES
('coa_0d59b6f2ac06', '30100', 'Paid-Up Share Capital', 'Equity', 'Credit', 1, NULL, 'Share Capital', 'Member common share capital subscribed and paid', '2026-09-16 09:46:56'),
('coa_2b565835d13e', '5110', 'Interest Expense on Savings', 'Expense', 'Debit', 1, NULL, 'Financial Expenses', 'Annual dividend and monthly interest yield distributed on deposits', '2026-09-17 10:02:08'),
('coa_34551d1f0572', '5220', 'Office Supplies & Stationeries', 'Expense', 'Debit', 1, NULL, 'Administrative Expenses', 'Electric, water, telecommunications, and stationery expenses', '2026-09-17 10:06:46'),
('coa_45dd7932537a', '2110', 'Regular Savings Deposits', 'Liability', 'Credit', 1, 'coa_9f6aeb96d752', 'Deposit Liabilities', 'Withdrawable member deposit savings balances', '2026-09-17 10:00:12'),
('coa_6d6a02085527', '1110', 'Cash on Hand - Tellers', 'Asset', 'Debit', 1, NULL, 'Current Assets', 'Petty cash and daily cashier vault drawers', '2026-09-16 09:36:32'),
('coa_76ca285ad3ae', '1100', 'Cash and Cash Equivalents', 'Asset', 'Debit', 1, NULL, 'Current Assets', 'Summary control account for all cash drawers, vaults, and bank repositories', '2026-09-17 09:24:42'),
('coa_86dc268d5b31', '1200', 'Loans Receivable - Control', 'Asset', 'Debit', 1, NULL, 'Loans and Receivables', 'Aggregate control account for all active member loan facilities', '2026-09-17 08:41:20'),
('coa_91a73de6ef12', '1112', 'Cash in Vault - Reserve Safes', 'Asset', 'Debit', 1, NULL, 'Current Assets', 'Branch heavy depository cash vault reserve funds', '2026-09-17 09:26:25'),
('coa_9f6aeb96d752', '2100', 'Deposit Liabilities', 'Liability', 'Credit', 1, NULL, 'Deposit Liabilities', 'Control account for all member demand and term deposit liabilities', '2026-09-17 09:59:40'),
('coa_a01cbabc3ece', '5210', 'Salaries and Employee Benefits', 'Expense', 'Debit', 1, NULL, 'Administrative Expenses', 'Staff compensation, 13th month pay, and personnel allowances', '2026-09-17 10:06:13'),
('coa_a70a6840f62d', '5230', 'Rent, Power & Utilities', 'Expense', 'Debit', 1, NULL, 'Administrative Expenses', 'Branch premise lease rentals and utility services', '2026-09-17 10:07:12'),
('coa_b97ba3b58150', '4120', 'Service and Processing Fees', 'Revenue', 'Credit', 1, NULL, 'Operating Revenue', 'Loan origination, filing, and notarial service fees', '2026-09-17 10:08:17'),
('coa_bb87e425dddf', '5290', 'Provision for Loan Losses', 'Expense', 'Debit', 1, NULL, 'Administrative Expenses', 'Expense entry provisioning reserve for doubtful loan accounts', '2026-09-17 10:07:32'),
('coa_c10384c181aa', '4110', 'Interest Income on Loans', 'Revenue', 'Credit', 1, NULL, 'Operating Revenue', 'Earned interest collected on member loan disbursements', '2026-09-17 04:06:17'),
('coa_c2fe2e2c751d', '1210', 'Loans Receivable - Regular', 'Asset', 'Debit', 1, 'coa_86dc268d5b31', 'Loans and Receivables', 'Principal balance of outstanding member multi-purpose loans', '2026-09-17 08:44:49'),
('coa_d085c331d4d1', '40420', 'Membership & Admission Fees', 'Revenue', 'Credit', 1, NULL, 'Operating Revenue', 'Non-refundable membership application and seminar fees', '2026-09-16 09:46:03'),
('coa_da16bfbd520e', '3100', 'Members Equity & Share Capital', 'Equity', 'Credit', 1, NULL, 'Share Capital', 'Summary control account for subscribed and paid-up share capital', '2026-09-17 13:53:11'),
('coa_fffe59b422da', '3110', 'Common Share Capital', 'Equity', 'Credit', 1, 'coa_da16bfbd520e', 'Share Capital', 'Member common share capital subscribed and paid', '2026-09-17 13:53:37');

-- --------------------------------------------------------

--
-- Table structure for table `configuration_audit_trails`
--

CREATE TABLE `configuration_audit_trails` (
  `id` varchar(50) NOT NULL,
  `setting` varchar(150) NOT NULL,
  `old_value` text,
  `new_value` text,
  `changed_by` varchar(100) NOT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `configuration_audit_trails`
--

INSERT INTO `configuration_audit_trails` (`id`, `setting`, `old_value`, `new_value`, `changed_by`, `reason`, `created_at`) VALUES
('audit_init_01', 'Cooperative SQL Initialization', 'None', 'Clean Schema & Master Seeds Created', 'System Administrator', 'Deployment of SQL database for PHP MVC backend integration', '2026-09-12 08:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `cooperatives`
--

CREATE TABLE `cooperatives` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `cda_registration_no` varchar(100) NOT NULL,
  `tax_identification_no` varchar(100) NOT NULL,
  `coop_type` varchar(100) DEFAULT 'Agricultural',
  `address` text,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `fiscal_year_start` varchar(10) DEFAULT '01-01',
  `base_currency` varchar(10) DEFAULT 'PHP',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `cooperatives`
--

INSERT INTO `cooperatives` (`id`, `name`, `cda_registration_no`, `tax_identification_no`, `coop_type`, `address`, `contact_phone`, `contact_email`, `fiscal_year_start`, `base_currency`, `created_at`, `updated_at`) VALUES
('coop_01', 'Multipurpose Cooperative System', 'CDA-REG-CAR-2018-09142', '009-881-209-000', 'Agricultural / Multi-Purpose', 'National Highway, San Vicente, Tarlac City, Tarlac', '+63 (045) 982-1144', 'contact@mayapcare.coop', '01-01', 'PHP', '2026-09-12 08:07:46', '2026-09-12 08:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `custom_fields`
--

CREATE TABLE `custom_fields` (
  `id` varchar(50) NOT NULL,
  `entity_type` enum('Member','Loan','Savings','ShareCapital') NOT NULL,
  `field_key` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `field_type` enum('Text','Number','Date','Select','Boolean','Phone') NOT NULL,
  `options` json DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `custom_fields`
--

INSERT INTO `custom_fields` (`id`, `entity_type`, `field_key`, `label`, `field_type`, `options`, `is_required`, `active`, `display_order`) VALUES
('cf_mem_01', 'Member', 'occupation', 'Primary Occupation / Enterprise', 'Select', '[\"Farmer / Fisherfolk\", \"Self-Employed / Entrepreneur\", \"Government Employee\", \"Private Sector Employee\", \"Healthcare Professional\", \"OFW / Remittance Dependent\", \"Retired\"]', 1, 1, 1),
('cf_mem_02', 'Member', 'barangay', 'Barangay / Village Residence', 'Text', NULL, 1, 1, 2),
('cf_mem_03', 'Member', 'monthly_income', 'Estimated Monthly Household Income (PHP)', 'Number', NULL, 1, 1, 3),
('cf_mem_04', 'Member', 'tin_number', 'Tax Identification Number (TIN)', 'Text', NULL, 0, 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `document_requirements`
--

CREATE TABLE `document_requirements` (
  `id` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT '1',
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `document_requirements`
--

INSERT INTO `document_requirements` (`id`, `module`, `name`, `is_mandatory`, `active`) VALUES
('doc_billing', 'Members', 'Proof of Billing / Residence Certificate', 1, 1),
('doc_income', 'Loans', 'Proof of Income / Income Tax Return / Crop Harvest Log', 1, 1),
('doc_pmes', 'Members', 'Pre-Membership Education Seminar (PMES) Certificate', 1, 1),
('doc_promissory', 'Loans', 'Signed Promissory Note with Co-maker Agreement', 1, 1),
('doc_valid_id', 'Members', 'Government Issued Valid ID (Driver License, UMID, Passport)', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `feature_toggles`
--

CREATE TABLE `feature_toggles` (
  `id` varchar(50) NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `category` varchar(50) DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `feature_toggles`
--

INSERT INTO `feature_toggles` (`id`, `feature_key`, `name`, `description`, `category`, `enabled`, `updated_at`) VALUES
('feat_appr', 'feature_approval_workflows', 'Tiered Approval Workflows', 'Multi-step role-based authorization for loans and capital adjustments', 'Security', 1, '2026-09-12 08:07:46'),
('feat_cf', 'feature_custom_fields', 'Custom Member Fields', 'Enable dynamic custom fields for member profiles without code changes', 'Members', 1, '2026-09-12 08:07:46'),
('feat_notif', 'feature_notification_rules', 'Automated SMS / Push Triggers', 'Trigger alerts on loan approvals, overdue payments, and scheduled dues', 'Communications', 1, '2026-09-12 08:07:46'),
('feat_sub', 'feature_subsidiary_ledger', 'Subsidiary Ledger Tracking', 'Granular accounting subsidiary breakdown by member, loan, and cash drawers', 'Accounting', 1, '2026-09-12 08:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

CREATE TABLE `fees` (
  `id` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `calculation_type` enum('Fixed','Percentage','Percentage of Principal') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `applies_to` varchar(50) NOT NULL,
  `percentage` int NOT NULL,
  `gl_account_id` varchar(50) NOT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `fees`
--

INSERT INTO `fees` (`id`, `code`, `name`, `calculation_type`, `amount`, `applies_to`, `percentage`, `gl_account_id`, `active`) VALUES
('fee_05a86a81', 'FEE-724', 'Loan Processing Fee', 'Percentage', 0.00, 'loan', 2, 'coa_c10384c181aa', 1),
('fee_c0f73b0e', 'FEE-MEMB', 'Cooperative Membership Entrance Fee', 'Fixed', 500.00, 'Loans', 0, 'coa_d085c331d4d1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `general_ledger`
--

CREATE TABLE `general_ledger` (
  `id` varchar(50) NOT NULL,
  `journal_entry_id` varchar(50) NOT NULL,
  `account_id` varchar(50) NOT NULL,
  `posting_date` date NOT NULL,
  `period_id` varchar(50) NOT NULL,
  `branch_id` varchar(50) NOT NULL,
  `debit` decimal(15,2) DEFAULT '0.00',
  `credit` decimal(15,2) DEFAULT '0.00',
  `balance_running` decimal(15,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` varchar(50) NOT NULL,
  `voucher_number` varchar(50) NOT NULL,
  `branch_id` varchar(50) NOT NULL,
  `posting_date` date NOT NULL,
  `reference_type` varchar(50) NOT NULL,
  `reference_id` varchar(50) DEFAULT NULL,
  `description` text NOT NULL,
  `total_debit` decimal(15,2) NOT NULL,
  `total_credit` decimal(15,2) NOT NULL,
  `period_id` varchar(50) NOT NULL,
  `status` enum('Draft','Pending Approval','Posted','Reversed') DEFAULT 'Posted',
  `created_by` varchar(100) NOT NULL,
  `posted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `journal_entries`
--

INSERT INTO `journal_entries` (`id`, `voucher_number`, `branch_id`, `posting_date`, `reference_type`, `reference_id`, `description`, `total_debit`, `total_credit`, `period_id`, `status`, `created_by`, `posted_at`) VALUES
('je_12e7b33428de', 'JV-20260917-8427', 'branch_sfe', '2026-09-17', 'Manual JV', NULL, 'Rent September', 1500.00, 1500.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-17 22:46:24'),
('je_176de8c0b63e', 'JV-20260916-9260', 'branch_sfe', '2026-09-16', 'Manual JV', NULL, 'Payment received from Juan – Membership Fee and Share Capital', 1500.00, 1500.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-16 10:23:23'),
('je_461425771d59', 'JV-20260919-0609', 'branch_sfe', '2026-09-16', 'Manual JV', NULL, 'Payment received from Juan – Membership Fee and Share Capital', 500.00, 500.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-19 12:21:11'),
('je_70b80901ae15', 'JV-20260919-2675', 'branch_sfe', '2026-09-16', 'Manual JV', NULL, 'Payment received from Juan – Membership Fee and Share Capital', 500.00, 500.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-19 13:47:24'),
('je_7125d0945c48', 'JV-20260919-9979', 'branch_sfe', '2026-09-19', 'Manual JV', NULL, 'Cash on Hand Funded', 50000.00, 50000.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-19 12:33:04'),
('je_7aef4367396d', 'JV-20260919-9049', 'branch_sfe', '2026-09-19', 'Manual JV', NULL, 'Funded', 1000000.00, 1000000.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-19 13:22:09'),
('je_a6b9daeff7ce', 'JV-20260919-3303', 'branch_sfe', '2026-09-19', 'Manual JV', NULL, 'Pedro Reyes paid membership and share capital', 5000.00, 5000.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-19 12:23:55'),
('je_ac6765f163c6', 'JV-20260919-3952', 'branch_sfe', '2026-09-19', 'Manual JV', NULL, 'Jhomelfds Ignacio paid membership and share capital', 1500.00, 1500.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-19 12:46:56'),
('je_bf2e08f2de33', 'JV-20260916-6547', 'branch_sfe', '2026-09-16', 'Manual JV', NULL, 'Payment received from Juan – Membership Fee and Share Capital', 500.00, 500.00, 'period_2026_09', 'Posted', 'Administrator', '2026-09-16 10:22:14');

-- --------------------------------------------------------

--
-- Table structure for table `journal_lines`
--

CREATE TABLE `journal_lines` (
  `id` varchar(50) NOT NULL,
  `journal_entry_id` varchar(50) NOT NULL,
  `account_id` varchar(50) NOT NULL,
  `debit` decimal(15,2) DEFAULT '0.00',
  `credit` decimal(15,2) DEFAULT '0.00',
  `subsidiary_type` varchar(50) DEFAULT NULL,
  `subsidiary_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `journal_lines`
--

INSERT INTO `journal_lines` (`id`, `journal_entry_id`, `account_id`, `debit`, `credit`, `subsidiary_type`, `subsidiary_id`) VALUES
('jl_253ddb63fa1e', 'je_7aef4367396d', 'coa_76ca285ad3ae', 0.00, 1000000.00, '', ''),
('jl_4a37df13ffb9', 'je_7125d0945c48', 'coa_76ca285ad3ae', 0.00, 50000.00, '', ''),
('jl_4b4c462caecb', 'je_70b80901ae15', 'coa_d085c331d4d1', 0.00, 500.00, NULL, NULL),
('jl_581d5c3dc44b', 'je_7125d0945c48', 'coa_6d6a02085527', 50000.00, 0.00, '', ''),
('jl_5a11aaf1520b', 'je_bf2e08f2de33', 'coa_d085c331d4d1', 0.00, 500.00, NULL, NULL),
('jl_7582d4ab4036', 'je_ac6765f163c6', 'coa_0d59b6f2ac06', 1500.00, 0.00, '', ''),
('jl_78ca6e326d31', 'je_12e7b33428de', 'coa_6d6a02085527', 0.00, 1500.00, '', ''),
('jl_8fb3d1c7bba7', 'je_12e7b33428de', 'coa_a70a6840f62d', 1500.00, 0.00, '', ''),
('jl_9e5cf2d9c645', 'je_70b80901ae15', 'coa_6d6a02085527', 500.00, 0.00, NULL, NULL),
('jl_a4ae01dc7a84', 'je_461425771d59', 'coa_d085c331d4d1', 0.00, 500.00, NULL, NULL),
('jl_ae9808c1fe4c', 'je_176de8c0b63e', 'coa_0d59b6f2ac06', 0.00, 1000.00, NULL, NULL),
('jl_b0d4c0bf9d96', 'je_176de8c0b63e', 'coa_d085c331d4d1', 0.00, 500.00, NULL, NULL),
('jl_bb105b4373f8', 'je_bf2e08f2de33', 'coa_6d6a02085527', 500.00, 0.00, NULL, NULL),
('jl_c1b3553af3d2', 'je_461425771d59', 'coa_6d6a02085527', 500.00, 0.00, NULL, NULL),
('jl_ce774fc11d48', 'je_a6b9daeff7ce', 'coa_6d6a02085527', 0.00, 5000.00, '', ''),
('jl_da8de04532aa', 'je_7aef4367396d', 'coa_6d6a02085527', 1000000.00, 0.00, '', ''),
('jl_dbd86c466a9c', 'je_ac6765f163c6', 'coa_76ca285ad3ae', 0.00, 1500.00, '', ''),
('jl_e34730f767c3', 'je_a6b9daeff7ce', 'coa_0d59b6f2ac06', 5000.00, 0.00, '', ''),
('jl_e8ed84a0e160', 'je_176de8c0b63e', 'coa_6d6a02085527', 1500.00, 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` varchar(50) NOT NULL,
  `loan_account_no` varchar(50) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `loan_product_id` varchar(50) NOT NULL,
  `product_version` int DEFAULT '1',
  `branch_id` varchar(50) NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL,
  `annual_interest_rate` decimal(5,2) NOT NULL,
  `interest_calculation_method` varchar(50) NOT NULL,
  `term_months` int NOT NULL,
  `payment_frequency` varchar(50) NOT NULL,
  `disbursement_date` date NOT NULL,
  `first_due_date` date NOT NULL,
  `maturity_date` date NOT NULL,
  `processing_fee` decimal(15,2) DEFAULT '0.00',
  `service_fee` decimal(15,2) DEFAULT '0.00',
  `net_disbursed` decimal(15,2) NOT NULL,
  `disbursed_from_cash_account_id` varchar(50) NOT NULL,
  `status` enum('Draft','Submitted','Approved','Released','Active','Fully Paid','Past Due','Restructured') DEFAULT 'Active',
  `current_balance` decimal(15,2) NOT NULL,
  `total_principal_paid` decimal(15,2) DEFAULT '0.00',
  `total_interest_paid` decimal(15,2) DEFAULT '0.00',
  `total_penalty_paid` decimal(15,2) DEFAULT '0.00',
  `total_fees_paid` decimal(15,2) DEFAULT '0.00',
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `loan_account_no`, `member_id`, `loan_product_id`, `product_version`, `branch_id`, `principal_amount`, `annual_interest_rate`, `interest_calculation_method`, `term_months`, `payment_frequency`, `disbursement_date`, `first_due_date`, `maturity_date`, `processing_fee`, `service_fee`, `net_disbursed`, `disbursed_from_cash_account_id`, `status`, `current_balance`, `total_principal_paid`, `total_interest_paid`, `total_penalty_paid`, `total_fees_paid`, `approved_by`, `approved_date`, `created_at`) VALUES
('ln_0c825dffbc1f', 'LN-2026-47490', 'mem_2db09967d517', 'lp_0120445f', 1, 'branch_tar', 10000.00, 10.00, 'Diminishing Balance', 12, 'Monthly', '2026-09-17', '2026-10-17', '2027-09-17', 0.00, 0.00, 10000.00, 'cash_01', 'Fully Paid', 0.04, 9999.96, 541.66, 0.00, 0.00, 'Administrator', '2026-09-17', '2026-09-17 07:18:31'),
('ln_0fd68bfdfb3e', 'LN-2026-75019', 'mem_000002', 'lp_0120445f', 1, 'branch_tar', 10000.00, 10.00, 'Diminishing Balance', 12, 'Monthly', '2026-09-17', '2026-10-17', '2027-09-17', 0.00, 0.00, 10000.00, 'cash_01', 'Active', 10000.00, 0.00, 0.00, 0.00, 0.00, 'Administrator', '2026-09-17', '2026-09-17 11:52:39'),
('ln_d1233c4d087f', 'LN-2026-23644', 'mem_000002', 'lp_0120445f', 1, 'branch_tar', 10000.00, 10.00, 'Diminishing Balance', 13, 'Monthly', '2026-09-17', '2026-10-17', '2027-10-17', 0.00, 0.00, 10000.00, 'cash_01', 'Active', 9159.25, 840.75, 160.25, 0.00, 0.00, 'Administrator', '2026-09-17', '2026-09-17 07:25:51');

-- --------------------------------------------------------

--
-- Table structure for table `loan_amortization_schedules`
--

CREATE TABLE `loan_amortization_schedules` (
  `id` varchar(50) NOT NULL,
  `loan_id` varchar(50) NOT NULL,
  `installment_no` int NOT NULL,
  `due_date` date NOT NULL,
  `principal` decimal(15,2) NOT NULL,
  `interest` decimal(15,2) NOT NULL,
  `fee` decimal(15,2) DEFAULT '0.00',
  `total_installment` decimal(15,2) NOT NULL,
  `principal_balance` decimal(15,2) NOT NULL,
  `paid_principal` decimal(15,2) DEFAULT '0.00',
  `paid_interest` decimal(15,2) DEFAULT '0.00',
  `paid_penalty` decimal(15,2) DEFAULT '0.00',
  `paid_date` date DEFAULT NULL,
  `status` enum('Unpaid','Partially Paid','Paid','Overdue') DEFAULT 'Unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loan_amortization_schedules`
--

INSERT INTO `loan_amortization_schedules` (`id`, `loan_id`, `installment_no`, `due_date`, `principal`, `interest`, `fee`, `total_installment`, `principal_balance`, `paid_principal`, `paid_interest`, `paid_penalty`, `paid_date`, `status`) VALUES
('las_069e01bb1011', 'ln_0c825dffbc1f', 12, '2027-09-17', 833.33, 6.94, 0.00, 840.28, 0.00, 833.33, 6.94, 0.00, NULL, 'Paid'),
('las_16d65cc81603', 'ln_d1233c4d087f', 12, '2027-09-17', 769.23, 12.82, 0.00, 782.05, 769.23, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_1abdae1874b1', 'ln_d1233c4d087f', 6, '2027-03-17', 769.23, 51.28, 0.00, 820.51, 5384.62, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_2135845b10da', 'ln_0c825dffbc1f', 8, '2027-05-17', 833.33, 34.72, 0.00, 868.06, 3333.33, 833.33, 34.72, 0.00, NULL, 'Paid'),
('las_27cc25e91372', 'ln_0fd68bfdfb3e', 11, '2027-08-17', 833.33, 13.89, 0.00, 847.22, 833.33, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_28bdcbbd1bf4', 'ln_d1233c4d087f', 9, '2027-06-17', 769.23, 32.05, 0.00, 801.28, 3076.92, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_2b5ff10eb842', 'ln_0c825dffbc1f', 11, '2027-08-17', 833.33, 13.89, 0.00, 847.22, 833.33, 833.33, 13.89, 0.00, NULL, 'Paid'),
('las_38de4df93515', 'ln_0c825dffbc1f', 2, '2026-11-17', 833.33, 76.39, 0.00, 909.72, 8333.33, 833.33, 76.39, 0.00, NULL, 'Paid'),
('las_40c8b1ca5d7f', 'ln_0fd68bfdfb3e', 2, '2026-11-17', 833.33, 76.39, 0.00, 909.72, 8333.33, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_4936fd94ccad', 'ln_0c825dffbc1f', 4, '2027-01-17', 833.33, 62.50, 0.00, 895.83, 6666.67, 833.33, 62.50, 0.00, NULL, 'Paid'),
('las_56ec39652064', 'ln_0c825dffbc1f', 7, '2027-04-17', 833.33, 41.67, 0.00, 875.00, 4166.67, 833.33, 41.67, 0.00, NULL, 'Paid'),
('las_58b2f5c33d15', 'ln_0fd68bfdfb3e', 7, '2027-04-17', 833.33, 41.67, 0.00, 875.00, 4166.67, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_5e8bf1fd0bfe', 'ln_0fd68bfdfb3e', 3, '2026-12-17', 833.33, 69.44, 0.00, 902.78, 7500.00, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_5e9593ab127d', 'ln_d1233c4d087f', 5, '2027-02-17', 769.23, 57.69, 0.00, 826.92, 6153.85, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_5fa318a0e9d1', 'ln_0fd68bfdfb3e', 5, '2027-02-17', 833.33, 55.56, 0.00, 888.89, 5833.33, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_6154cec04d33', 'ln_d1233c4d087f', 10, '2027-07-17', 769.23, 25.64, 0.00, 794.87, 2307.69, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_63996a08a4a1', 'ln_0c825dffbc1f', 9, '2027-06-17', 833.33, 27.78, 0.00, 861.11, 2500.00, 833.33, 27.78, 0.00, NULL, 'Paid'),
('las_6b3cab90f27e', 'ln_0fd68bfdfb3e', 12, '2027-09-17', 833.33, 6.94, 0.00, 840.28, 0.00, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_6bafd3245e4c', 'ln_0fd68bfdfb3e', 10, '2027-07-17', 833.33, 20.83, 0.00, 854.17, 1666.67, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_738ef81a552a', 'ln_0c825dffbc1f', 1, '2026-10-17', 833.33, 83.33, 0.00, 916.67, 9166.67, 833.33, 83.33, 0.00, NULL, 'Paid'),
('las_7a6158cd6eaa', 'ln_d1233c4d087f', 11, '2027-08-17', 769.23, 19.23, 0.00, 788.46, 1538.46, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_7c35f91be42e', 'ln_0fd68bfdfb3e', 9, '2027-06-17', 833.33, 27.78, 0.00, 861.11, 2500.00, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_8763295192e1', 'ln_d1233c4d087f', 8, '2027-05-17', 769.23, 38.46, 0.00, 807.69, 3846.15, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_89833ead0bd1', 'ln_0fd68bfdfb3e', 1, '2026-10-17', 833.33, 83.33, 0.00, 916.67, 9166.67, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_93bd011e8e24', 'ln_d1233c4d087f', 7, '2027-04-17', 769.23, 44.87, 0.00, 814.10, 4615.38, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_97048ce885c8', 'ln_d1233c4d087f', 2, '2026-11-17', 769.23, 76.92, 0.00, 846.15, 8461.54, 71.52, 76.92, 0.00, NULL, 'Partially Paid'),
('las_b25e7a7b5015', 'ln_d1233c4d087f', 1, '2026-10-17', 769.23, 83.33, 0.00, 852.56, 9230.77, 769.23, 83.33, 0.00, NULL, 'Paid'),
('las_b30f90e14918', 'ln_0fd68bfdfb3e', 4, '2027-01-17', 833.33, 62.50, 0.00, 895.83, 6666.67, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_babfc73f67a7', 'ln_0fd68bfdfb3e', 6, '2027-03-17', 833.33, 48.61, 0.00, 881.94, 5000.00, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_bbe3e06d7b25', 'ln_0fd68bfdfb3e', 8, '2027-05-17', 833.33, 34.72, 0.00, 868.06, 3333.33, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_cff0c3930874', 'ln_d1233c4d087f', 4, '2027-01-17', 769.23, 64.10, 0.00, 833.33, 6923.08, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_d0261ec09f7f', 'ln_d1233c4d087f', 3, '2026-12-17', 769.23, 70.51, 0.00, 839.74, 7692.31, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_db5a4e32a92e', 'ln_0c825dffbc1f', 3, '2026-12-17', 833.33, 69.44, 0.00, 902.78, 7500.00, 833.33, 69.44, 0.00, NULL, 'Paid'),
('las_dd4e97558778', 'ln_0c825dffbc1f', 10, '2027-07-17', 833.33, 20.83, 0.00, 854.17, 1666.67, 833.33, 20.83, 0.00, NULL, 'Paid'),
('las_e7271b1400e9', 'ln_d1233c4d087f', 13, '2027-10-17', 769.23, 6.41, 0.00, 775.64, 0.00, 0.00, 0.00, 0.00, NULL, 'Unpaid'),
('las_ee9ac2af59ed', 'ln_0c825dffbc1f', 6, '2027-03-17', 833.33, 48.61, 0.00, 881.94, 5000.00, 833.33, 48.61, 0.00, NULL, 'Paid'),
('las_ef14abf2e9fb', 'ln_0c825dffbc1f', 5, '2027-02-17', 833.33, 55.56, 0.00, 888.89, 5833.33, 833.33, 55.56, 0.00, NULL, 'Paid');

-- --------------------------------------------------------

--
-- Table structure for table `loan_applications`
--

CREATE TABLE `loan_applications` (
  `id` varchar(50) NOT NULL,
  `application_no` varchar(50) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `loan_product_id` varchar(50) NOT NULL,
  `branch_id` varchar(50) NOT NULL,
  `applied_amount` decimal(15,2) NOT NULL,
  `term_months` int NOT NULL,
  `purpose` text,
  `status` enum('Draft','Submitted','Under Review','Approved','Rejected','Released') DEFAULT 'Draft',
  `submitted_date` date DEFAULT NULL,
  `reviewed_by` varchar(100) DEFAULT NULL,
  `reviewed_date` date DEFAULT NULL,
  `approved_amount` decimal(15,2) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_payments`
--

CREATE TABLE `loan_payments` (
  `id` varchar(50) NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `loan_id` varchar(50) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `cash_account_id` varchar(50) NOT NULL,
  `received_by` varchar(100) NOT NULL,
  `journal_entry_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loan_payments`
--

INSERT INTO `loan_payments` (`id`, `receipt_no`, `loan_id`, `member_id`, `payment_date`, `total_amount`, `cash_account_id`, `received_by`, `journal_entry_id`, `created_at`) VALUES
('lp_41713c1dbd6a', 'OR-20260917-5835', 'ln_0c825dffbc1f', 'mem_2db09967d517', '2026-09-17', 901.00, 'cash_01', 'Administrator', NULL, '2026-09-17 12:57:24'),
('lp_5a94206901db', 'OR-20260917-1799', 'ln_d1233c4d087f', 'mem_000002', '2026-09-17', 1001.00, 'cash_01', 'Administrator', NULL, '2026-09-17 09:04:33'),
('lp_5ca105f56305', 'OR-20260917-2702', 'ln_0c825dffbc1f', 'mem_2db09967d517', '2026-09-17', 7401.00, 'cash_01', 'Administrator', NULL, '2026-09-17 13:02:18'),
('lp_76d71d95c18e', 'OR-20260917-1134', 'ln_0c825dffbc1f', 'mem_2db09967d517', '2026-09-17', 1001.00, 'cash_01', 'Administrator', NULL, '2026-09-17 11:49:13'),
('lp_9d20de7e365e', 'OR-20260917-3998', 'ln_0c825dffbc1f', 'mem_2db09967d517', '2026-09-17', 1001.00, 'cash_01', 'Administrator', NULL, '2026-09-17 08:28:57'),
('lp_c11a53d28a29', 'OR-20260917-8467', 'ln_0c825dffbc1f', 'mem_2db09967d517', '2026-09-17', 1.00, 'cash_01', 'Administrator', NULL, '2026-09-17 13:03:03'),
('lp_d5bcf87f0255', 'OR-20260917-5818', 'ln_0c825dffbc1f', 'mem_2db09967d517', '2026-09-17', 1.00, 'cash_01', 'Administrator', NULL, '2026-09-17 13:05:02'),
('lp_fcf09b6c7527', 'OR-20260917-8440', 'ln_0c825dffbc1f', 'mem_2db09967d517', '2026-09-17', 251.00, 'cash_01', 'Administrator', NULL, '2026-09-17 13:02:45');

-- --------------------------------------------------------

--
-- Table structure for table `loan_payment_allocations`
--

CREATE TABLE `loan_payment_allocations` (
  `id` varchar(50) NOT NULL,
  `payment_id` varchar(50) NOT NULL,
  `loan_id` varchar(50) NOT NULL,
  `penalty_amount` decimal(15,2) DEFAULT '0.00',
  `interest_amount` decimal(15,2) DEFAULT '0.00',
  `fee_amount` decimal(15,2) DEFAULT '0.00',
  `principal_amount` decimal(15,2) DEFAULT '0.00',
  `allocation_order_applied` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_products`
--

CREATE TABLE `loan_products` (
  `id` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `version` int DEFAULT '1',
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) NOT NULL,
  `min_term_months` int NOT NULL,
  `max_term_months` int NOT NULL,
  `annual_interest_rate` decimal(5,2) NOT NULL,
  `interest_calculation_method` enum('Diminishing Balance','Flat Rate','Equal Amortization') NOT NULL,
  `payment_frequency` enum('Monthly','Semi-monthly','Weekly','Lump Sum') NOT NULL,
  `grace_period_days` int DEFAULT '0',
  `penalty_rate_percentage` decimal(5,2) DEFAULT '2.00',
  `gl_receivable_account_id` varchar(50) NOT NULL,
  `gl_interest_income_account_id` varchar(50) NOT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loan_products`
--

INSERT INTO `loan_products` (`id`, `code`, `name`, `description`, `version`, `min_amount`, `max_amount`, `min_term_months`, `max_term_months`, `annual_interest_rate`, `interest_calculation_method`, `payment_frequency`, `grace_period_days`, `penalty_rate_percentage`, `gl_receivable_account_id`, `gl_interest_income_account_id`, `active`) VALUES
('lp_0120445f', 'LP-895', 'Regular Multi-Purpose Loan', '', 1, 10000.00, 250000.00, 1, 12, 10.00, 'Diminishing Balance', 'Monthly', 5, 2.00, 'coa_c10384c181aa', 'coa_6d6a02085527', 1);

-- --------------------------------------------------------

--
-- Table structure for table `loan_product_versions`
--

CREATE TABLE `loan_product_versions` (
  `id` varchar(50) NOT NULL,
  `loan_product_id` varchar(50) NOT NULL,
  `version_number` int NOT NULL,
  `annual_interest_rate` decimal(5,2) NOT NULL,
  `interest_calculation_method` varchar(50) NOT NULL,
  `effective_from` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `changed_by` varchar(100) DEFAULT NULL,
  `reason` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` varchar(50) NOT NULL,
  `member_no` varchar(50) NOT NULL,
  `branch_id` varchar(50) NOT NULL,
  `member_type_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `birthdate` date NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `status` enum('Active','Pending Approval','Inactive','Terminated','Deceased') DEFAULT 'Pending Approval',
  `joined_date` date NOT NULL,
  `custom_field_values` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `member_no`, `branch_id`, `member_type_id`, `first_name`, `last_name`, `middle_name`, `gender`, `birthdate`, `email`, `phone`, `address`, `status`, `joined_date`, `custom_field_values`, `created_at`, `updated_at`) VALUES
('mem_000002', 'MEM-2026-0002', 'branch_tar', 'mt_regular', 'Maria', 'Santos', 'Garcia', 'Female', '1990-07-22', 'maria.santos@example.com', '09181234567', 'Brgy. San Jose, Tarlac City, Tarlac', 'Active', '2026-02-10', '{\"occupation\": \"Teacher\", \"civil_status\": \"Married\", \"monthly_income\": 32000}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_000003', 'MEM-2026-0003', 'branch_urd', 'mt_regular', 'Pedro', 'Reyes', 'Cruz', 'Male', '1978-11-05', 'pedro.reyes@example.com', '09191234567', 'Brgy. Nancayasan, Urdaneta City, Pangasinan', 'Active', '2026-02-20', '{\"occupation\": \"Business Owner\", \"civil_status\": \"Married\", \"monthly_income\": 45000}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_000004', 'MEM-2026-0004', 'branch_sfe', 'mt_regular', 'Ana', 'Garcia', 'Lopez', 'Female', '1995-01-18', 'ana.garcia@example.com', '09201234567', 'Brgy. Catbangen, City of San Fernando, La Union', 'Pending Approval', '2026-08-05', '{\"occupation\": \"Accountant\", \"civil_status\": \"Single\", \"monthly_income\": 38000}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_000005', 'MEM-2026-0005', 'branch_tar', 'mt_associate', 'Roberto', 'Mendoza', 'Dizon', 'Male', '1982-09-30', 'roberto.mendoza@example.com', '09211234567', 'Brgy. Matatalaib, Tarlac City, Tarlac', 'Active', '2026-03-12', '{\"occupation\": \"Carpenter\", \"civil_status\": \"Married\", \"monthly_income\": 28000}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_000006', 'MEM-2026-0006', 'branch_urd', 'mt_regular', 'Catherine', 'Navarro', 'Reyes', 'Female', '1988-05-11', 'catherine.navarro@example.com', '09221234567', 'Brgy. San Vicente, Urdaneta City, Pangasinan', 'Active', '2026-04-18', '{\"occupation\": \"Nurse\", \"civil_status\": \"Married\", \"monthly_income\": 40000}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_000007', 'MEM-2026-0007', 'branch_sfe', 'mt_associate', 'Fernando', 'Torres', 'Ramos', 'Male', '1975-12-25', 'fernando.torres@example.com', '09231234567', 'Brgy. Catbangen, City of San Fernando, La Union', 'Inactive', '2025-06-10', '{\"occupation\": \"Entrepreneur\", \"civil_status\": \"Married\", \"monthly_income\": 55000}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_000008', 'MEM-2026-0008', 'branch_tar', 'mt_lab', 'Sofia', 'Aquino', 'Morales', 'Female', '2008-10-08', 'sofia.aquino@example.com', '09241234567', 'Brgy. Tibag, Tarlac City, Tarlac', 'Active', '2026-09-01', '{\"occupation\": \"Student\", \"civil_status\": \"Single\", \"monthly_income\": 0}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_000009', 'MEM-2026-0009', 'branch_urd', 'mt_regular', 'Michael', 'Fernandez', 'Castro', 'Male', '1992-02-14', 'michael.fernandez@example.com', '09251234567', 'Brgy. San Vicente, Urdaneta City, Pangasinan', 'Active', '2026-05-22', '{\"occupation\": \"Driver\", \"civil_status\": \"Single\", \"monthly_income\": 26000}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_000010', 'MEM-2026-0010', 'branch_sfe', 'mt_regular', 'Elena', 'Villanueva', 'Diaz', 'Female', '1986-06-19', 'elena.villanueva@example.com', '09261234567', 'Brgy. Catbangen, City of San Fernando, La Union', 'Active', '2026-06-30', '{\"occupation\": \"Store Owner\", \"civil_status\": \"Married\", \"monthly_income\": 35000}', '2026-09-12 09:57:16', '2026-09-12 09:57:16'),
('mem_001', 'MEM-2026-0001', 'branch_tar', 'mt_regular', 'Juan', 'Dela Cruz', 'Santos', 'Male', '1985-03-15', 'juan.delacruz@example.com', '09171234567', 'Brgy. San Vicente, Tarlac City, Tarlac', 'Active', '2026-01-15', '{\"occupation\": \"Farmer\", \"civil_status\": \"Married\", \"monthly_income\": 25000}', '2026-09-12 09:57:16', '2026-09-13 08:01:20'),
('mem_2db09967d517', 'MEM-2026-75517', 'branch_sfe', 'mt_associate', 'Jhomelfds', 'Ignacio', 'test', 'Female', '1992-05-15', 'jhomignacio08@gmail.com', '', 'zone 5', 'Active', '2026-09-13', '[]', '2026-09-13 11:20:19', '2026-09-13 11:20:19'),
('mem_4d43137b17f4', 'MEM-2026-51106', 'branch_sfe', 'mt_associate', 'Jhomel', 'Ignacio', '', 'Male', '1992-05-15', 'jhomignacio08@gmail.com', '', 'zone 5', 'Active', '2026-09-13', '[]', '2026-09-13 10:12:19', '2026-09-13 10:12:19');

-- --------------------------------------------------------

--
-- Table structure for table `member_types`
--

CREATE TABLE `member_types` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text,
  `voting_rights` tinyint(1) DEFAULT '1',
  `min_share_capital` decimal(15,2) DEFAULT '1000.00',
  `savings_requirement` decimal(15,2) DEFAULT '500.00',
  `loan_eligibility` tinyint(1) DEFAULT '1',
  `required_documents` json DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `member_types`
--

INSERT INTO `member_types` (`id`, `name`, `code`, `description`, `voting_rights`, `min_share_capital`, `savings_requirement`, `loan_eligibility`, `required_documents`, `active`) VALUES
('mt_associate', 'Associate Member', 'ASSOCIATE', 'Non-voting member enjoying savings deposit and credit facilities', 0, 5000.00, 500.00, 1, '[\"Valid Gov ID\", \"2x2 ID Photo\", \"Proof of Billing\"]', 1),
('mt_lab', 'Laboratory / Youth Member', 'LAB_YOUTH', 'Minor/Student savings depositor preparing for future cooperative participation', 0, 500.00, 200.00, 0, '[\"Birth Certificate\", \"Parents Consent Form\"]', 1),
('mt_regular', 'Regular Member', 'REGULAR', 'Full-fledged member with voting rights and dividend participation', 1, 10000.00, 1000.00, 1, '[\"Valid Gov ID\", \"2x2 ID Photo\", \"Proof of Billing\", \"PMES Certificate\"]', 1);

-- --------------------------------------------------------

--
-- Table structure for table `numbering_formats`
--

CREATE TABLE `numbering_formats` (
  `id` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `prefix` varchar(20) NOT NULL,
  `branch_specific` tinyint(1) DEFAULT '1',
  `include_year` tinyint(1) DEFAULT '1',
  `padding` int DEFAULT '5',
  `next_number` int DEFAULT '1',
  `pattern` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `numbering_formats`
--

INSERT INTO `numbering_formats` (`id`, `module`, `prefix`, `branch_specific`, `include_year`, `padding`, `next_number`, `pattern`, `created_at`) VALUES
('num_cbu', 'ShareCapital', 'CBU', 1, 1, 4, 1, '{BRANCH}-CBU-{YEAR}-{NUMBER}', '2026-09-12 08:07:46'),
('num_cd', 'Disbursements', 'CD', 1, 1, 6, 1, '{BRANCH}-CD-{YEAR}-{NUMBER}', '2026-09-12 08:07:46'),
('num_jv', 'Journal', 'JV', 1, 1, 6, 1, '{BRANCH}-JV-{YEAR}-{NUMBER}', '2026-09-12 08:07:46'),
('num_loan', 'Loans', 'LN', 1, 1, 5, 1, '{BRANCH}-LN-{YEAR}-{NUMBER}', '2026-09-12 08:07:46'),
('num_mem', 'Members', 'MEM', 0, 1, 5, 1, 'MEM-{YEAR}-{NUMBER}', '2026-09-12 08:07:46'),
('num_or', 'Receipts', 'OR', 1, 1, 6, 1, '{BRANCH}-OR-{YEAR}-{NUMBER}', '2026-09-12 08:07:46'),
('num_sa', 'Savings', 'SA', 1, 1, 4, 1, '{BRANCH}-SA-{YEAR}-{NUMBER}', '2026-09-12 08:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `payment_allocation_rules`
--

CREATE TABLE `payment_allocation_rules` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `priority_order` json NOT NULL,
  `is_default` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_allocation_rules`
--

INSERT INTO `payment_allocation_rules` (`id`, `name`, `description`, `priority_order`, `is_default`) VALUES
('alloc_cda_std', 'CDA Standard Priority Hierarchy', 'Penalties first, then accrued interest, then service fees, then principal reduction', '[\"Penalty\", \"Interest\", \"Fees\", \"Principal\"]', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payment_frequencies`
--

CREATE TABLE `payment_frequencies` (
  `id` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `days_interval` int NOT NULL,
  `periods_per_year` int NOT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payment_frequencies`
--

INSERT INTO `payment_frequencies` (`id`, `name`, `days_interval`, `periods_per_year`, `active`) VALUES
('freq_lumpsum', 'Lump Sum', 180, 2, 1),
('freq_monthly', 'Monthly', 30, 12, 1),
('freq_semimonthly', 'Semi-monthly', 15, 24, 1),
('freq_weekly', 'Weekly', 7, 52, 1);

-- --------------------------------------------------------

--
-- Table structure for table `penalty_rules`
--

CREATE TABLE `penalty_rules` (
  `id` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `grace_period_days` int DEFAULT '0',
  `penalty_rate_percentage` decimal(5,2) NOT NULL,
  `calculation_base` enum('Overdue Principal','Total Overdue Installment') DEFAULT 'Overdue Principal',
  `compounding_frequency` enum('None','Monthly','Daily') DEFAULT 'None',
  `gl_income_account_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `savings_accounts`
--

CREATE TABLE `savings_accounts` (
  `id` varchar(50) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `savings_product_id` varchar(50) NOT NULL,
  `branch_id` varchar(50) NOT NULL,
  `balance` decimal(15,2) DEFAULT '0.00',
  `opened_date` date NOT NULL,
  `status` enum('Active','Dormant','Closed') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `savings_accounts`
--

INSERT INTO `savings_accounts` (`id`, `account_number`, `member_id`, `savings_product_id`, `branch_id`, `balance`, `opened_date`, `status`, `created_at`) VALUES
('sa_8b3222088fd8', 'SA-2026-98049', 'mem_2db09967d517', 'sp_regular', 'branch_sfe', 11000.00, '2026-09-17', 'Active', '2026-09-17 10:09:21'),
('sa_e73c563a9f4b', 'SA-2026-63450', 'mem_000003', 'sp_regular', 'branch_tar', 12000.00, '2026-09-17', 'Active', '2026-09-17 11:08:18');

-- --------------------------------------------------------

--
-- Table structure for table `savings_products`
--

CREATE TABLE `savings_products` (
  `id` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `min_balance_to_earn_interest` decimal(15,2) DEFAULT '1000.00',
  `annual_interest_rate` decimal(5,2) DEFAULT '2.00',
  `interest_calculation_method` varchar(50) DEFAULT 'Average Daily Balance',
  `min_opening_deposit` decimal(15,2) DEFAULT '500.00',
  `maintaining_balance` decimal(15,2) DEFAULT '500.00',
  `gl_liability_account_id` varchar(50) NOT NULL,
  `gl_interest_expense_account_id` varchar(50) NOT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `savings_products`
--

INSERT INTO `savings_products` (`id`, `code`, `name`, `min_balance_to_earn_interest`, `annual_interest_rate`, `interest_calculation_method`, `min_opening_deposit`, `maintaining_balance`, `gl_liability_account_id`, `gl_interest_expense_account_id`, `active`) VALUES
('sp_regular', 'SAV-REG', 'Regular Savings Deposit', 1000.00, 2.00, 'Average Daily Balance', 500.00, 500.00, 'coa_45dd7932537a', 'coa_2b565835d13e', 1);

-- --------------------------------------------------------

--
-- Table structure for table `savings_transactions`
--

CREATE TABLE `savings_transactions` (
  `id` varchar(50) NOT NULL,
  `transaction_no` varchar(50) NOT NULL,
  `savings_account_id` varchar(50) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `type` enum('DEPOSIT','WITHDRAWAL','INTEREST_POSTING','FEE_DEDUCTION') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `cash_account_id` varchar(50) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `savings_transactions`
--

INSERT INTO `savings_transactions` (`id`, `transaction_no`, `savings_account_id`, `member_id`, `type`, `amount`, `balance_after`, `cash_account_id`, `transaction_date`, `notes`, `created_at`) VALUES
('stx_3d2b47fc6384', 'TX-20260917-5076', 'sa_e73c563a9f4b', 'mem_000003', 'DEPOSIT', 10000.00, 11000.00, 'cash_01', '2026-09-17', 'DEPOSIT processed by Administrator', '2026-09-17 13:43:11'),
('stx_a02f4ffbe670', 'TX-20260917-3861', 'sa_8b3222088fd8', 'mem_2db09967d517', 'DEPOSIT', 1000.00, 11000.00, 'cash_01', '2026-09-17', 'DEPOSIT processed by Administrator', '2026-09-17 11:10:46'),
('stx_b11b26a24a15', 'TX-20260917-8737', 'sa_e73c563a9f4b', 'mem_000003', 'DEPOSIT', 1000.00, 12000.00, 'cash_01', '2026-09-17', 'DEPOSIT processed by Administrator', '2026-09-17 13:47:30'),
('stx_b16d92c9e256', 'TX-20260917-8069', 'sa_8b3222088fd8', 'mem_2db09967d517', 'DEPOSIT', 10000.00, 10000.00, 'cash_01', '2026-09-17', 'DEPOSIT processed by Administrator', '2026-09-17 11:04:20'),
('stx_f320edfec14f', 'TX-20260917-4275', 'sa_e73c563a9f4b', 'mem_000003', 'DEPOSIT', 1000.00, 1000.00, 'cash_01', '2026-09-17', 'DEPOSIT processed by Administrator', '2026-09-17 11:10:54');

-- --------------------------------------------------------

--
-- Table structure for table `share_capital_accounts`
--

CREATE TABLE `share_capital_accounts` (
  `id` varchar(50) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `branch_id` varchar(50) DEFAULT NULL,
  `subscribed_shares` int NOT NULL,
  `subscribed_amount` decimal(15,2) NOT NULL,
  `paid_up_shares` int NOT NULL,
  `paid_up_amount` decimal(15,2) NOT NULL,
  `status` enum('Active','Withdrawn','Transferred') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `share_capital_accounts`
--

INSERT INTO `share_capital_accounts` (`id`, `account_number`, `member_id`, `branch_id`, `subscribed_shares`, `subscribed_amount`, `paid_up_shares`, `paid_up_amount`, `status`, `created_at`) VALUES
('sc_1862e6143e4f', 'SC-2026-51167', 'mem_000004', NULL, 50, 5000.00, 110, 11000.00, 'Active', '2026-09-19 10:32:52'),
('sc_5d3ccbdeb46d', 'SC-2026-49526', 'mem_000003', 'branch_tar', 50, 5000.00, 50, 5000.00, 'Active', '2026-09-17 11:45:18'),
('sc_eba878e8c416', 'SC-2026-23594', 'mem_2db09967d517', NULL, 50, 5000.00, 120, 12000.00, 'Active', '2026-09-19 10:59:45'),
('sc_f7020b9e1339', 'SC-2026-62451', 'mem_001', 'branch_urd', 50, 5000.00, 10, 1000.00, 'Active', '2026-09-17 13:56:30');

-- --------------------------------------------------------

--
-- Table structure for table `share_capital_settings`
--

CREATE TABLE `share_capital_settings` (
  `id` varchar(50) NOT NULL,
  `cooperative_id` varchar(50) NOT NULL,
  `par_value_per_share` decimal(15,2) DEFAULT '100.00',
  `min_subscription_shares` int DEFAULT '100',
  `min_paid_up_shares` int DEFAULT '25',
  `max_share_holding_percentage` decimal(5,2) DEFAULT '10.00',
  `transfer_fee` decimal(15,2) DEFAULT '100.00',
  `withdrawal_rule` text,
  `accounting_account_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `share_capital_transactions`
--

CREATE TABLE `share_capital_transactions` (
  `id` varchar(50) NOT NULL,
  `receipt_no` varchar(50) NOT NULL,
  `share_account_id` varchar(50) NOT NULL,
  `member_id` varchar(50) NOT NULL,
  `type` enum('SUBSCRIPTION','PAYMENT','WITHDRAWAL','TRANSFER','DIVIDEND_PATRONAGE') NOT NULL,
  `shares` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `cash_account_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `share_capital_transactions`
--

INSERT INTO `share_capital_transactions` (`id`, `receipt_no`, `share_account_id`, `member_id`, `type`, `shares`, `amount`, `transaction_date`, `cash_account_id`, `created_at`) VALUES
('sctx_18ac83c62c15', 'SC-OR-20260917-639', 'sc_5d3ccbdeb46d', 'mem_000003', 'PAYMENT', 10, 1000.00, '2026-09-17', NULL, '2026-09-17 11:45:18'),
('sctx_24b03fb04640', 'SC-OR-20260919-826', 'sc_eba878e8c416', 'mem_2db09967d517', 'PAYMENT', 10, 1000.00, '2026-09-19', NULL, '2026-09-19 10:59:45'),
('sctx_3b00c35654e9', 'SC-OR-20260917-973', 'sc_f7020b9e1339', 'mem_001', 'PAYMENT', 10, 1000.00, '2026-09-17', NULL, '2026-09-17 13:56:30'),
('sctx_3fc72774e91b', 'SC-OR-20260919-657', 'sc_1862e6143e4f', 'mem_000004', 'PAYMENT', 10, 1000.00, '2026-09-19', NULL, '2026-09-19 10:32:52'),
('sctx_430859fe9729', 'SC-OR-20260917-4537', 'sc_5d3ccbdeb46d', 'mem_000003', 'PAYMENT', 40, 4000.00, '2026-09-17', 'cash_01', '2026-09-17 13:42:11'),
('sctx_6386bf7e9ce6', 'SC-OR-20260919-4220', 'sc_1862e6143e4f', 'mem_000004', 'PAYMENT', 100, 10000.00, '2026-09-19', 'cash_a97d265a3533', '2026-09-19 12:38:01'),
('sctx_a2a6172f8b57', 'SC-OR-20260919-4579', 'sc_eba878e8c416', 'mem_2db09967d517', 'PAYMENT', 10, 1000.00, '2026-09-19', 'cash_01', '2026-09-19 11:24:30'),
('sctx_fe7339ef5d07', 'SC-OR-20260919-8251', 'sc_eba878e8c416', 'mem_2db09967d517', 'PAYMENT', 100, 10000.00, '2026-09-19', 'cash_a97d265a3533', '2026-09-19 12:37:11');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` varchar(50) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_group` varchar(50) NOT NULL,
  `description` text,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `updated_by`, `updated_at`) VALUES
('set_cur', 'currency', 'PHP', 'General', 'Primary operating currency symbol', 'System Administrator', '2026-09-12 08:07:46'),
('set_fy', 'fiscal_year_start', '01-01', 'Accounting', 'Start of fiscal accounting calendar (MM-DD)', 'System Administrator', '2026-09-12 08:07:46'),
('set_grace', 'global_grace_period_days', '5', 'Loan Policy', 'Global grace period before loan delinquency penalties apply', 'System Administrator', '2026-09-12 08:07:46'),
('set_pen_comp', 'penalty_compounding', 'false', 'Loan Policy', 'Whether penalties compound into principal monthly', 'System Administrator', '2026-09-12 08:07:46'),
('set_sc_max', 'max_share_holding_percentage', '10', 'Regulatory', 'CDA maximum percentage of total share capital any single member may own', 'System Administrator', '2026-09-12 08:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_types`
--

CREATE TABLE `transaction_types` (
  `id` varchar(50) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `module` varchar(50) NOT NULL,
  `requires_approval` tinyint(1) DEFAULT '0',
  `numbering_format_id` varchar(50) DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transaction_types`
--

INSERT INTO `transaction_types` (`id`, `code`, `name`, `module`, `requires_approval`, `numbering_format_id`, `active`) VALUES
('tx_loan_pmt', 'LOAN_PAYMENT', 'Loan Amortization Collection', 'Loans', 0, 'num_or', 1),
('tx_loan_rel', 'LOAN_RELEASE', 'Loan Disbursement Voucher', 'Loans', 1, 'num_cd', 1),
('tx_open_bal', 'OPENING_BALANCE', 'Opening Balance Journal Entry', 'Accounting', 1, 'num_jv', 1),
('tx_sav_dep', 'SAVINGS_DEPOSIT', 'Savings Deposit Slip', 'Savings', 0, 'num_or', 1),
('tx_sav_with', 'SAVINGS_WITHDRAWAL', 'Savings Cash Withdrawal Voucher', 'Savings', 1, 'num_cd', 1),
('tx_sc_pay', 'SHARE_CAPITAL_PAYMENT', 'Capital Build-Up OR', 'ShareCapital', 0, 'num_or', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `role_id` varchar(50) NOT NULL,
  `branch_id` varchar(50) NOT NULL,
  `active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role_id`, `branch_id`, `active`, `last_login`, `created_at`) VALUES
('user_admin', 'admin', '$2y$10$e8PZ7YJ1s7vWc6M79iA2/eqiB4uJtY76yW55N5Q1nLqK9hX2HjB2.', 'System Administrator', 'admin@mayapcare.coop', 'role_admin', 'branch_tar', 1, NULL, '2026-09-12 08:07:46'),
('user_admin1', 'superadmin', '$2y$10$CKXeclgN2CJz3ZrZ.lLMbe7xr.sYENkYvYDsiZy21.qw5YYoXOWtK', 'System Administrator', 'jhomignacio08@gmail.com', 'role_admin', 'branch_tar', 1, NULL, '2026-09-13 08:35:53'),
('usr_688248a9e500', 'testuser01', '$2y$10$8fwpwsBWxNdd718coaEaXOO/HFdcNrFd/vb.10zgr/frkKdLoIe0e', 'Test User', 'testuser01@example.com', 'role_loan_officer', 'branch_tar', 1, NULL, '2026-09-13 09:02:09');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `permissions` json NOT NULL,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `name`, `description`, `permissions`, `active`) VALUES
('role_accountant', 'Chief / Branch Accountant', 'General ledger management, journal vouchers, and period close', '[\"manage_accounting\", \"view_gl\", \"post_journals\", \"close_periods\", \"view_reports\"]', 1),
('role_admin', 'System Administrator', 'Full unconstrained platform super-user access', '[\"manage_system\", \"manage_configurations\", \"manage_members\", \"manage_loans\", \"manage_savings\", \"manage_accounting\", \"approve_transactions\", \"view_reports\"]', 1),
('role_bod', 'Board of Directors', 'High-level policy review, macro-reporting, and large-loan approvals', '[\"approve_tier3_loans\", \"view_reports\", \"view_audit_trail\"]', 1),
('role_loan_officer', 'Credit & Loan Evaluation Officer', 'Loan application review, credit investigation, and schedule creation', '[\"manage_loans\", \"view_members\", \"create_schedules\"]', 1),
('role_manager', 'General / Branch Manager', 'Branch operations supervisor with level 2 approval rights', '[\"manage_members\", \"manage_loans\", \"manage_savings\", \"approve_transactions\", \"view_reports\"]', 1),
('role_teller', 'Teller / Cashier', 'Over-the-counter payments, deposits, and cash drawer settlements', '[\"receive_payments\", \"issue_receipts\", \"cash_inflow\", \"cash_outflow\"]', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounting_mappings`
--
ALTER TABLE `accounting_mappings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_type` (`event_type`),
  ADD KEY `debit_account_id` (`debit_account_id`),
  ADD KEY `credit_account_id` (`credit_account_id`);

--
-- Indexes for table `accounting_periods`
--
ALTER TABLE `accounting_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_year_period` (`fiscal_year`,`period_number`);

--
-- Indexes for table `approval_rules`
--
ALTER TABLE `approval_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workflow_id` (`workflow_id`);

--
-- Indexes for table `approval_workflows`
--
ALTER TABLE `approval_workflows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `cash_accounts`
--
ALTER TABLE `cash_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `gl_account_id` (`gl_account_id`);

--
-- Indexes for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_no` (`transaction_no`),
  ADD KEY `cash_account_id` (`cash_account_id`);

--
-- Indexes for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_code` (`account_code`),
  ADD KEY `idx_acc_code` (`account_code`),
  ADD KEY `idx_acc_cat` (`category`);

--
-- Indexes for table `configuration_audit_trails`
--
ALTER TABLE `configuration_audit_trails`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cooperatives`
--
ALTER TABLE `cooperatives`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `custom_fields`
--
ALTER TABLE `custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_entity_key` (`entity_type`,`field_key`);

--
-- Indexes for table `document_requirements`
--
ALTER TABLE `document_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feature_toggles`
--
ALTER TABLE `feature_toggles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `feature_key` (`feature_key`);

--
-- Indexes for table `fees`
--
ALTER TABLE `fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `gl_account_id` (`gl_account_id`);

--
-- Indexes for table `general_ledger`
--
ALTER TABLE `general_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_entry_id` (`journal_entry_id`),
  ADD KEY `period_id` (`period_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_gl_acc_date` (`account_id`,`posting_date`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_number` (`voucher_number`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_jv_date` (`posting_date`),
  ADD KEY `idx_jv_period` (`period_id`);

--
-- Indexes for table `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_entry_id` (`journal_entry_id`),
  ADD KEY `idx_jl_account` (`account_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loan_account_no` (`loan_account_no`),
  ADD KEY `loan_product_id` (`loan_product_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `disbursed_from_cash_account_id` (`disbursed_from_cash_account_id`),
  ADD KEY `idx_loan_member` (`member_id`),
  ADD KEY `idx_loan_status` (`status`);

--
-- Indexes for table `loan_amortization_schedules`
--
ALTER TABLE `loan_amortization_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sched_due` (`loan_id`,`due_date`);

--
-- Indexes for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_no` (`application_no`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `loan_product_id` (`loan_product_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `cash_account_id` (`cash_account_id`);

--
-- Indexes for table `loan_payment_allocations`
--
ALTER TABLE `loan_payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `loan_id` (`loan_id`);

--
-- Indexes for table `loan_products`
--
ALTER TABLE `loan_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `gl_receivable_account_id` (`gl_receivable_account_id`),
  ADD KEY `gl_interest_income_account_id` (`gl_interest_income_account_id`);

--
-- Indexes for table `loan_product_versions`
--
ALTER TABLE `loan_product_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prod_version` (`loan_product_id`,`version_number`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_no` (`member_no`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `member_type_id` (`member_type_id`),
  ADD KEY `idx_member_name` (`last_name`,`first_name`),
  ADD KEY `idx_member_status` (`status`);

--
-- Indexes for table `member_types`
--
ALTER TABLE `member_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `numbering_formats`
--
ALTER TABLE `numbering_formats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_allocation_rules`
--
ALTER TABLE `payment_allocation_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_frequencies`
--
ALTER TABLE `payment_frequencies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `penalty_rules`
--
ALTER TABLE `penalty_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gl_income_account_id` (`gl_income_account_id`);

--
-- Indexes for table `savings_accounts`
--
ALTER TABLE `savings_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_number` (`account_number`),
  ADD KEY `savings_product_id` (`savings_product_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_sa_member` (`member_id`);

--
-- Indexes for table `savings_products`
--
ALTER TABLE `savings_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `gl_liability_account_id` (`gl_liability_account_id`),
  ADD KEY `gl_interest_expense_account_id` (`gl_interest_expense_account_id`);

--
-- Indexes for table `savings_transactions`
--
ALTER TABLE `savings_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_no` (`transaction_no`),
  ADD KEY `savings_account_id` (`savings_account_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `cash_account_id` (`cash_account_id`);

--
-- Indexes for table `share_capital_accounts`
--
ALTER TABLE `share_capital_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_number` (`account_number`),
  ADD KEY `idx_sca_member` (`member_id`),
  ADD KEY `idx_share_capital_branch_id` (`branch_id`);

--
-- Indexes for table `share_capital_settings`
--
ALTER TABLE `share_capital_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `accounting_account_id` (`accounting_account_id`);

--
-- Indexes for table `share_capital_transactions`
--
ALTER TABLE `share_capital_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `receipt_no` (`receipt_no`),
  ADD KEY `share_account_id` (`share_account_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `cash_account_id` (`cash_account_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `transaction_types`
--
ALTER TABLE `transaction_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounting_mappings`
--
ALTER TABLE `accounting_mappings`
  ADD CONSTRAINT `accounting_mappings_ibfk_1` FOREIGN KEY (`debit_account_id`) REFERENCES `chart_of_accounts` (`id`),
  ADD CONSTRAINT `accounting_mappings_ibfk_2` FOREIGN KEY (`credit_account_id`) REFERENCES `chart_of_accounts` (`id`);

--
-- Constraints for table `approval_rules`
--
ALTER TABLE `approval_rules`
  ADD CONSTRAINT `approval_rules_ibfk_1` FOREIGN KEY (`workflow_id`) REFERENCES `approval_workflows` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cash_accounts`
--
ALTER TABLE `cash_accounts`
  ADD CONSTRAINT `cash_accounts_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `cash_accounts_ibfk_2` FOREIGN KEY (`gl_account_id`) REFERENCES `chart_of_accounts` (`id`);

--
-- Constraints for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD CONSTRAINT `cash_transactions_ibfk_1` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`);

--
-- Constraints for table `fees`
--
ALTER TABLE `fees`
  ADD CONSTRAINT `fees_ibfk_1` FOREIGN KEY (`gl_account_id`) REFERENCES `chart_of_accounts` (`id`);

--
-- Constraints for table `general_ledger`
--
ALTER TABLE `general_ledger`
  ADD CONSTRAINT `general_ledger_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `general_ledger_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`),
  ADD CONSTRAINT `general_ledger_ibfk_3` FOREIGN KEY (`period_id`) REFERENCES `accounting_periods` (`id`),
  ADD CONSTRAINT `general_ledger_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `journal_entries_ibfk_2` FOREIGN KEY (`period_id`) REFERENCES `accounting_periods` (`id`);

--
-- Constraints for table `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD CONSTRAINT `journal_lines_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `journal_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`);

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`loan_product_id`) REFERENCES `loan_products` (`id`),
  ADD CONSTRAINT `loans_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `loans_ibfk_4` FOREIGN KEY (`disbursed_from_cash_account_id`) REFERENCES `cash_accounts` (`id`);

--
-- Constraints for table `loan_amortization_schedules`
--
ALTER TABLE `loan_amortization_schedules`
  ADD CONSTRAINT `loan_amortization_schedules_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_applications`
--
ALTER TABLE `loan_applications`
  ADD CONSTRAINT `loan_applications_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `loan_applications_ibfk_2` FOREIGN KEY (`loan_product_id`) REFERENCES `loan_products` (`id`),
  ADD CONSTRAINT `loan_applications_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `loan_payments`
--
ALTER TABLE `loan_payments`
  ADD CONSTRAINT `loan_payments_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`),
  ADD CONSTRAINT `loan_payments_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `loan_payments_ibfk_3` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`);

--
-- Constraints for table `loan_payment_allocations`
--
ALTER TABLE `loan_payment_allocations`
  ADD CONSTRAINT `loan_payment_allocations_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `loan_payments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loan_payment_allocations_ibfk_2` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`);

--
-- Constraints for table `loan_products`
--
ALTER TABLE `loan_products`
  ADD CONSTRAINT `loan_products_ibfk_1` FOREIGN KEY (`gl_receivable_account_id`) REFERENCES `chart_of_accounts` (`id`),
  ADD CONSTRAINT `loan_products_ibfk_2` FOREIGN KEY (`gl_interest_income_account_id`) REFERENCES `chart_of_accounts` (`id`);

--
-- Constraints for table `loan_product_versions`
--
ALTER TABLE `loan_product_versions`
  ADD CONSTRAINT `loan_product_versions_ibfk_1` FOREIGN KEY (`loan_product_id`) REFERENCES `loan_products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `members_ibfk_2` FOREIGN KEY (`member_type_id`) REFERENCES `member_types` (`id`);

--
-- Constraints for table `penalty_rules`
--
ALTER TABLE `penalty_rules`
  ADD CONSTRAINT `penalty_rules_ibfk_1` FOREIGN KEY (`gl_income_account_id`) REFERENCES `chart_of_accounts` (`id`);

--
-- Constraints for table `savings_accounts`
--
ALTER TABLE `savings_accounts`
  ADD CONSTRAINT `savings_accounts_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `savings_accounts_ibfk_2` FOREIGN KEY (`savings_product_id`) REFERENCES `savings_products` (`id`),
  ADD CONSTRAINT `savings_accounts_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `savings_products`
--
ALTER TABLE `savings_products`
  ADD CONSTRAINT `savings_products_ibfk_1` FOREIGN KEY (`gl_liability_account_id`) REFERENCES `chart_of_accounts` (`id`),
  ADD CONSTRAINT `savings_products_ibfk_2` FOREIGN KEY (`gl_interest_expense_account_id`) REFERENCES `chart_of_accounts` (`id`);

--
-- Constraints for table `savings_transactions`
--
ALTER TABLE `savings_transactions`
  ADD CONSTRAINT `savings_transactions_ibfk_1` FOREIGN KEY (`savings_account_id`) REFERENCES `savings_accounts` (`id`),
  ADD CONSTRAINT `savings_transactions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `savings_transactions_ibfk_3` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`);

--
-- Constraints for table `share_capital_accounts`
--
ALTER TABLE `share_capital_accounts`
  ADD CONSTRAINT `fk_share_capital_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `share_capital_accounts_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`);

--
-- Constraints for table `share_capital_settings`
--
ALTER TABLE `share_capital_settings`
  ADD CONSTRAINT `share_capital_settings_ibfk_1` FOREIGN KEY (`accounting_account_id`) REFERENCES `chart_of_accounts` (`id`);

--
-- Constraints for table `share_capital_transactions`
--
ALTER TABLE `share_capital_transactions`
  ADD CONSTRAINT `share_capital_transactions_ibfk_1` FOREIGN KEY (`share_account_id`) REFERENCES `share_capital_accounts` (`id`),
  ADD CONSTRAINT `share_capital_transactions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `share_capital_transactions_ibfk_3` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
