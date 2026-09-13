-- =====================================================================
-- COOPFLEX / MAYAP CARE AGRICULTURE COOPERATIVE
-- PRODUCTION-READY SQL SCHEMA FOR PHP MVC BACKEND ARCHITECTURE
-- Compatible with MySQL 8.0+ / MariaDB 10.4+ / PostgreSQL (ANSI-compliant)
-- =====================================================================
-- Initial State: Clean Baseline (No sample members, loans, or transactions)
-- Master tables pre-populated with CDA standard Chart of Accounts,
-- Branches, Loan/Savings Products, Settings, Numbering Formats & Roles.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS cooperative_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cooperative_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. COOPERATIVES (Organization Profile)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS cooperatives;
CREATE TABLE cooperatives (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    cda_registration_no VARCHAR(100) NOT NULL,
    tax_identification_no VARCHAR(100) NOT NULL,
    coop_type VARCHAR(100) DEFAULT 'Agricultural',
    address TEXT,
    contact_phone VARCHAR(50),
    contact_email VARCHAR(100),
    fiscal_year_start VARCHAR(10) DEFAULT '01-01',
    base_currency VARCHAR(10) DEFAULT 'PHP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. BRANCHES (Multi-Branch Management)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS branches;
CREATE TABLE branches (
    id VARCHAR(50) PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(50),
    manager_name VARCHAR(100),
    is_main_branch BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. SYSTEM SETTINGS & FEATURE TOGGLES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS system_settings;
CREATE TABLE system_settings (
    id VARCHAR(50) PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_group VARCHAR(50) NOT NULL,
    description TEXT,
    updated_by VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS feature_toggles;
CREATE TABLE feature_toggles (
    id VARCHAR(50) PRIMARY KEY,
    feature_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    enabled BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. CHART OF ACCOUNTS (CDA Standard)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS chart_of_accounts;
CREATE TABLE chart_of_accounts (
    id VARCHAR(50) PRIMARY KEY,
    account_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense') NOT NULL,
    normal_balance ENUM('Debit', 'Credit') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    parent_account_id VARCHAR(50) NULL,
    report_group VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_acc_code (account_code),
    INDEX idx_acc_cat (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. ACCOUNTING PERIODS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS accounting_periods;
CREATE TABLE accounting_periods (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    fiscal_year INT NOT NULL,
    period_number INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Open', 'Closed', 'Locked') DEFAULT 'Open',
    closed_at TIMESTAMP NULL,
    closed_by VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_year_period (fiscal_year, period_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. ACCOUNTING TRANSACTION MAPPINGS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS accounting_mappings;
CREATE TABLE accounting_mappings (
    id VARCHAR(50) PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    debit_account_id VARCHAR(50) NOT NULL,
    credit_account_id VARCHAR(50) NOT NULL,
    is_system BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (debit_account_id) REFERENCES chart_of_accounts(id),
    FOREIGN KEY (credit_account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. NUMBERING FORMATS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS numbering_formats;
CREATE TABLE numbering_formats (
    id VARCHAR(50) PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    prefix VARCHAR(20) NOT NULL,
    branch_specific BOOLEAN DEFAULT TRUE,
    include_year BOOLEAN DEFAULT TRUE,
    padding INT DEFAULT 5,
    next_number INT DEFAULT 1,
    pattern VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. APPROVAL WORKFLOWS & RULES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS approval_workflows;
CREATE TABLE approval_workflows (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS approval_rules;
CREATE TABLE approval_rules (
    id VARCHAR(50) PRIMARY KEY,
    workflow_id VARCHAR(50) NOT NULL,
    step_order INT NOT NULL,
    approver_role VARCHAR(50) NOT NULL,
    min_amount DECIMAL(15,2) DEFAULT 0,
    max_amount DECIMAL(15,2) NULL,
    requires_board_action BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. CUSTOM FIELDS DEFINITION
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS custom_fields;
CREATE TABLE custom_fields (
    id VARCHAR(50) PRIMARY KEY,
    entity_type ENUM('Member', 'Loan', 'Savings', 'ShareCapital') NOT NULL,
    field_key VARCHAR(100) NOT NULL,
    label VARCHAR(150) NOT NULL,
    field_type ENUM('Text', 'Number', 'Date', 'Select', 'Boolean', 'Phone') NOT NULL,
    options JSON NULL,
    is_required BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 1,
    UNIQUE KEY uk_entity_key (entity_type, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. MEMBER TYPES & MEMBERS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS member_types;
CREATE TABLE member_types (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    voting_rights BOOLEAN DEFAULT TRUE,
    min_share_capital DECIMAL(15,2) DEFAULT 1000,
    savings_requirement DECIMAL(15,2) DEFAULT 500,
    loan_eligibility BOOLEAN DEFAULT TRUE,
    required_documents JSON NULL,
    active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS members;
CREATE TABLE members (
    id VARCHAR(50) PRIMARY KEY,
    member_no VARCHAR(50) NOT NULL UNIQUE,
    branch_id VARCHAR(50) NOT NULL,
    member_type_id VARCHAR(50) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    birthdate DATE NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(50) NOT NULL,
    address TEXT NOT NULL,
    status ENUM('Active', 'Pending Approval', 'Inactive', 'Terminated', 'Deceased') DEFAULT 'Pending Approval',
    joined_date DATE NOT NULL,
    custom_field_values JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (member_type_id) REFERENCES member_types(id),
    INDEX idx_member_name (last_name, first_name),
    INDEX idx_member_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 11. CASH ACCOUNTS & CASH TRANSACTIONS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS cash_accounts;
CREATE TABLE cash_accounts (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    bank_name VARCHAR(150),
    branch_id VARCHAR(50) NOT NULL,
    gl_account_id VARCHAR(50) NOT NULL,
    opening_balance DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'PHP',
    active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (gl_account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS cash_transactions;
CREATE TABLE cash_transactions (
    id VARCHAR(50) PRIMARY KEY,
    transaction_no VARCHAR(100) NOT NULL UNIQUE,
    cash_account_id VARCHAR(50) NOT NULL,
    type ENUM('INFLOW', 'OUTFLOW', 'TRANSFER') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_before DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    reference_type VARCHAR(50),
    reference_id VARCHAR(50),
    description TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 12. LOAN PRODUCTS & VERSIONS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS loan_products;
CREATE TABLE loan_products (
    id VARCHAR(50) PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    version INT DEFAULT 1,
    min_amount DECIMAL(15,2) NOT NULL,
    max_amount DECIMAL(15,2) NOT NULL,
    min_term_months INT NOT NULL,
    max_term_months INT NOT NULL,
    annual_interest_rate DECIMAL(5,2) NOT NULL,
    interest_calculation_method ENUM('Diminishing Balance', 'Flat Rate', 'Equal Amortization') NOT NULL,
    payment_frequency ENUM('Monthly', 'Semi-monthly', 'Weekly', 'Lump Sum') NOT NULL,
    grace_period_days INT DEFAULT 0,
    penalty_rate_percentage DECIMAL(5,2) DEFAULT 2.0,
    gl_receivable_account_id VARCHAR(50) NOT NULL,
    gl_interest_income_account_id VARCHAR(50) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (gl_receivable_account_id) REFERENCES chart_of_accounts(id),
    FOREIGN KEY (gl_interest_income_account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS loan_product_versions;
CREATE TABLE loan_product_versions (
    id VARCHAR(50) PRIMARY KEY,
    loan_product_id VARCHAR(50) NOT NULL,
    version_number INT NOT NULL,
    annual_interest_rate DECIMAL(5,2) NOT NULL,
    interest_calculation_method VARCHAR(50) NOT NULL,
    effective_from TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed_by VARCHAR(100),
    reason TEXT,
    FOREIGN KEY (loan_product_id) REFERENCES loan_products(id) ON DELETE CASCADE,
    UNIQUE KEY uk_prod_version (loan_product_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 13. LOAN APPLICATIONS & LOANS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS loan_applications;
CREATE TABLE loan_applications (
    id VARCHAR(50) PRIMARY KEY,
    application_no VARCHAR(50) NOT NULL UNIQUE,
    member_id VARCHAR(50) NOT NULL,
    loan_product_id VARCHAR(50) NOT NULL,
    branch_id VARCHAR(50) NOT NULL,
    applied_amount DECIMAL(15,2) NOT NULL,
    term_months INT NOT NULL,
    purpose TEXT,
    status ENUM('Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected', 'Released') DEFAULT 'Draft',
    submitted_date DATE NULL,
    reviewed_by VARCHAR(100) NULL,
    reviewed_date DATE NULL,
    approved_amount DECIMAL(15,2) NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (loan_product_id) REFERENCES loan_products(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS loans;
CREATE TABLE loans (
    id VARCHAR(50) PRIMARY KEY,
    loan_account_no VARCHAR(50) NOT NULL UNIQUE,
    member_id VARCHAR(50) NOT NULL,
    loan_product_id VARCHAR(50) NOT NULL,
    product_version INT DEFAULT 1,
    branch_id VARCHAR(50) NOT NULL,
    principal_amount DECIMAL(15,2) NOT NULL,
    annual_interest_rate DECIMAL(5,2) NOT NULL,
    interest_calculation_method VARCHAR(50) NOT NULL,
    term_months INT NOT NULL,
    payment_frequency VARCHAR(50) NOT NULL,
    disbursement_date DATE NOT NULL,
    first_due_date DATE NOT NULL,
    maturity_date DATE NOT NULL,
    processing_fee DECIMAL(15,2) DEFAULT 0,
    service_fee DECIMAL(15,2) DEFAULT 0,
    net_disbursed DECIMAL(15,2) NOT NULL,
    disbursed_from_cash_account_id VARCHAR(50) NOT NULL,
    status ENUM('Draft', 'Submitted', 'Approved', 'Released', 'Active', 'Fully Paid', 'Past Due', 'Restructured') DEFAULT 'Active',
    current_balance DECIMAL(15,2) NOT NULL,
    total_principal_paid DECIMAL(15,2) DEFAULT 0,
    total_interest_paid DECIMAL(15,2) DEFAULT 0,
    total_penalty_paid DECIMAL(15,2) DEFAULT 0,
    total_fees_paid DECIMAL(15,2) DEFAULT 0,
    approved_by VARCHAR(100),
    approved_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (loan_product_id) REFERENCES loan_products(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (disbursed_from_cash_account_id) REFERENCES cash_accounts(id),
    INDEX idx_loan_member (member_id),
    INDEX idx_loan_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS loan_amortization_schedules;
CREATE TABLE loan_amortization_schedules (
    id VARCHAR(50) PRIMARY KEY,
    loan_id VARCHAR(50) NOT NULL,
    installment_no INT NOT NULL,
    due_date DATE NOT NULL,
    principal DECIMAL(15,2) NOT NULL,
    interest DECIMAL(15,2) NOT NULL,
    fee DECIMAL(15,2) DEFAULT 0,
    total_installment DECIMAL(15,2) NOT NULL,
    principal_balance DECIMAL(15,2) NOT NULL,
    paid_principal DECIMAL(15,2) DEFAULT 0,
    paid_interest DECIMAL(15,2) DEFAULT 0,
    paid_penalty DECIMAL(15,2) DEFAULT 0,
    paid_date DATE NULL,
    status ENUM('Unpaid', 'Partially Paid', 'Paid', 'Overdue') DEFAULT 'Unpaid',
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    INDEX idx_sched_due (loan_id, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS loan_payments;
CREATE TABLE loan_payments (
    id VARCHAR(50) PRIMARY KEY,
    receipt_no VARCHAR(50) NOT NULL UNIQUE,
    loan_id VARCHAR(50) NOT NULL,
    member_id VARCHAR(50) NOT NULL,
    payment_date DATE NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    cash_account_id VARCHAR(50) NOT NULL,
    received_by VARCHAR(100) NOT NULL,
    journal_entry_id VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id),
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS loan_payment_allocations;
CREATE TABLE loan_payment_allocations (
    id VARCHAR(50) PRIMARY KEY,
    payment_id VARCHAR(50) NOT NULL,
    loan_id VARCHAR(50) NOT NULL,
    penalty_amount DECIMAL(15,2) DEFAULT 0,
    interest_amount DECIMAL(15,2) DEFAULT 0,
    fee_amount DECIMAL(15,2) DEFAULT 0,
    principal_amount DECIMAL(15,2) DEFAULT 0,
    allocation_order_applied JSON NULL,
    FOREIGN KEY (payment_id) REFERENCES loan_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 14. SAVINGS PRODUCTS, ACCOUNTS & TRANSACTIONS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS savings_products;
CREATE TABLE savings_products (
    id VARCHAR(50) PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    min_balance_to_earn_interest DECIMAL(15,2) DEFAULT 1000,
    annual_interest_rate DECIMAL(5,2) DEFAULT 2.0,
    interest_calculation_method VARCHAR(50) DEFAULT 'Average Daily Balance',
    min_opening_deposit DECIMAL(15,2) DEFAULT 500,
    maintaining_balance DECIMAL(15,2) DEFAULT 500,
    gl_liability_account_id VARCHAR(50) NOT NULL,
    gl_interest_expense_account_id VARCHAR(50) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (gl_liability_account_id) REFERENCES chart_of_accounts(id),
    FOREIGN KEY (gl_interest_expense_account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS savings_accounts;
CREATE TABLE savings_accounts (
    id VARCHAR(50) PRIMARY KEY,
    account_number VARCHAR(50) NOT NULL UNIQUE,
    member_id VARCHAR(50) NOT NULL,
    savings_product_id VARCHAR(50) NOT NULL,
    branch_id VARCHAR(50) NOT NULL,
    balance DECIMAL(15,2) DEFAULT 0,
    opened_date DATE NOT NULL,
    status ENUM('Active', 'Dormant', 'Closed') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (savings_product_id) REFERENCES savings_products(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    INDEX idx_sa_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS savings_transactions;
CREATE TABLE savings_transactions (
    id VARCHAR(50) PRIMARY KEY,
    transaction_no VARCHAR(50) NOT NULL UNIQUE,
    savings_account_id VARCHAR(50) NOT NULL,
    member_id VARCHAR(50) NOT NULL,
    type ENUM('DEPOSIT', 'WITHDRAWAL', 'INTEREST_POSTING', 'FEE_DEDUCTION') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    cash_account_id VARCHAR(50) NULL,
    transaction_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (savings_account_id) REFERENCES savings_accounts(id),
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 15. SHARE CAPITAL (CBU) SETTINGS, ACCOUNTS & TRANSACTIONS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS share_capital_settings;
CREATE TABLE share_capital_settings (
    id VARCHAR(50) PRIMARY KEY,
    cooperative_id VARCHAR(50) NOT NULL,
    par_value_per_share DECIMAL(15,2) DEFAULT 100.0,
    min_subscription_shares INT DEFAULT 100,
    min_paid_up_shares INT DEFAULT 25,
    max_share_holding_percentage DECIMAL(5,2) DEFAULT 10.0,
    transfer_fee DECIMAL(15,2) DEFAULT 100.0,
    withdrawal_rule TEXT,
    accounting_account_id VARCHAR(50) NOT NULL,
    FOREIGN KEY (accounting_account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS share_capital_accounts;
CREATE TABLE share_capital_accounts (
    id VARCHAR(50) PRIMARY KEY,
    account_number VARCHAR(50) NOT NULL UNIQUE,
    member_id VARCHAR(50) NOT NULL,
    subscribed_shares INT NOT NULL,
    subscribed_amount DECIMAL(15,2) NOT NULL,
    paid_up_shares INT NOT NULL,
    paid_up_amount DECIMAL(15,2) NOT NULL,
    status ENUM('Active', 'Withdrawn', 'Transferred') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    INDEX idx_sca_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS share_capital_transactions;
CREATE TABLE share_capital_transactions (
    id VARCHAR(50) PRIMARY KEY,
    receipt_no VARCHAR(50) NOT NULL UNIQUE,
    share_account_id VARCHAR(50) NOT NULL,
    member_id VARCHAR(50) NOT NULL,
    type ENUM('SUBSCRIPTION', 'PAYMENT', 'WITHDRAWAL', 'TRANSFER', 'DIVIDEND_PATRONAGE') NOT NULL,
    shares INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    transaction_date DATE NOT NULL,
    cash_account_id VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (share_account_id) REFERENCES share_capital_accounts(id),
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 16. GENERAL LEDGER & JOURNAL ENTRIES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS journal_entries;
CREATE TABLE journal_entries (
    id VARCHAR(50) PRIMARY KEY,
    voucher_number VARCHAR(50) NOT NULL UNIQUE,
    branch_id VARCHAR(50) NOT NULL,
    posting_date DATE NOT NULL,
    reference_type VARCHAR(50) NOT NULL,
    reference_id VARCHAR(50) NULL,
    description TEXT NOT NULL,
    total_debit DECIMAL(15,2) NOT NULL,
    total_credit DECIMAL(15,2) NOT NULL,
    period_id VARCHAR(50) NOT NULL,
    status ENUM('Draft', 'Pending Approval', 'Posted', 'Reversed') DEFAULT 'Posted',
    created_by VARCHAR(100) DEFAULT 'system',
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (period_id) REFERENCES accounting_periods(id),
    INDEX idx_jv_date (posting_date),
    INDEX idx_jv_period (period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS journal_lines;
CREATE TABLE journal_lines (
    id VARCHAR(50) PRIMARY KEY,
    journal_entry_id VARCHAR(50) NOT NULL,
    account_id VARCHAR(50) NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0,
    credit DECIMAL(15,2) DEFAULT 0,
    subsidiary_type VARCHAR(50) NULL,
    subsidiary_id VARCHAR(50) NULL,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id),
    INDEX idx_jl_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS general_ledger;
CREATE TABLE general_ledger (
    id VARCHAR(50) PRIMARY KEY,
    journal_entry_id VARCHAR(50) NOT NULL,
    account_id VARCHAR(50) NOT NULL,
    posting_date DATE NOT NULL,
    period_id VARCHAR(50) NOT NULL,
    branch_id VARCHAR(50) NOT NULL,
    debit DECIMAL(15,2) DEFAULT 0,
    credit DECIMAL(15,2) DEFAULT 0,
    balance_running DECIMAL(15,2) DEFAULT 0,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id),
    FOREIGN KEY (period_id) REFERENCES accounting_periods(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    INDEX idx_gl_acc_date (account_id, posting_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 17. FEES, PENALTIES, ALLOCATIONS & RULES
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS fees;
CREATE TABLE fees (
    id VARCHAR(50) PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    calculation_type ENUM('Fixed', 'Percentage') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    applies_to VARCHAR(50) NOT NULL,
    gl_account_id VARCHAR(50) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (gl_account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS penalty_rules;
CREATE TABLE penalty_rules (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    grace_period_days INT DEFAULT 0,
    penalty_rate_percentage DECIMAL(5,2) NOT NULL,
    calculation_base ENUM('Overdue Principal', 'Total Overdue Installment') DEFAULT 'Overdue Principal',
    compounding_frequency ENUM('None', 'Monthly', 'Daily') DEFAULT 'None',
    gl_income_account_id VARCHAR(50) NOT NULL,
    FOREIGN KEY (gl_income_account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS payment_allocation_rules;
CREATE TABLE payment_allocation_rules (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    priority_order JSON NOT NULL,
    is_default BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS payment_frequencies;
CREATE TABLE payment_frequencies (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    days_interval INT NOT NULL,
    periods_per_year INT NOT NULL,
    active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS document_requirements;
CREATE TABLE document_requirements (
    id VARCHAR(50) PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    is_mandatory BOOLEAN DEFAULT TRUE,
    active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS transaction_types;
CREATE TABLE transaction_types (
    id VARCHAR(50) PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    module VARCHAR(50) NOT NULL,
    requires_approval BOOLEAN DEFAULT FALSE,
    numbering_format_id VARCHAR(50) NULL,
    active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 18. USERS, ROLES & AUDIT TRAIL
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS user_roles;
CREATE TABLE user_roles (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    permissions JSON NOT NULL,
    active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id VARCHAR(50) PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role_id VARCHAR(50) NOT NULL,
    branch_id VARCHAR(50) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS configuration_audit_trails;
CREATE TABLE configuration_audit_trails (
    id VARCHAR(50) PRIMARY KEY,
    setting VARCHAR(150) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_by VARCHAR(100) NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- MASTER DATA SEEDS (CONFIGURATION & CDA CHARTS OF ACCOUNTS)
-- Zero sample operational transactions (No members, loans, or vouchers)
-- =====================================================================

-- Cooperatives
INSERT INTO cooperatives (id, name, cda_registration_no, tax_identification_no, coop_type, address, contact_phone, contact_email, fiscal_year_start, base_currency) VALUES
('coop_01', 'Mayap Care Agriculture Coop.', 'CDA-REG-CAR-2018-09142', '009-881-209-000', 'Agricultural / Multi-Purpose', 'National Highway, San Vicente, Tarlac City, Tarlac', '+63 (045) 982-1144', 'contact@mayapcare.coop', '01-01', 'PHP');

-- Branches
INSERT INTO branches (id, code, name, address, contact_number, manager_name, is_main_branch, active) VALUES
('branch_tar', 'TAR', 'Main Tarlac Central Branch', 'Plaza Mabini Commercial Arcade, Tarlac City, Tarlac', '+63 (045) 982-1144', 'Ricardo P. Manalili', TRUE, TRUE),
('branch_urd', 'URD', 'Urdaneta Pangasinan Branch', 'MacArthur Highway, Nancayasan, Urdaneta City, Pangasinan', '+63 (075) 568-2200', 'Grace L. Tan', FALSE, TRUE),
('branch_sfe', 'SFE', 'San Fernando La Union Branch', 'Quezon Ave, Catbangen, City of San Fernando, La Union', '+63 (072) 888-4321', 'Eduardo M. Bautista', FALSE, TRUE);

-- System Settings
INSERT INTO system_settings (id, setting_key, setting_value, setting_group, description, updated_by) VALUES
('set_cur', 'currency', 'PHP', 'General', 'Primary operating currency symbol', 'System Administrator'),
('set_fy', 'fiscal_year_start', '01-01', 'Accounting', 'Start of fiscal accounting calendar (MM-DD)', 'System Administrator'),
('set_pen_comp', 'penalty_compounding', 'false', 'Loan Policy', 'Whether penalties compound into principal monthly', 'System Administrator'),
('set_grace', 'global_grace_period_days', '5', 'Loan Policy', 'Global grace period before loan delinquency penalties apply', 'System Administrator'),
('set_sc_max', 'max_share_holding_percentage', '10', 'Regulatory', 'CDA maximum percentage of total share capital any single member may own', 'System Administrator');

-- Feature Toggles
INSERT INTO feature_toggles (id, feature_key, name, description, category, enabled) VALUES
('feat_cf', 'feature_custom_fields', 'Custom Member Fields', 'Enable dynamic custom fields for member profiles without code changes', 'Members', TRUE),
('feat_appr', 'feature_approval_workflows', 'Tiered Approval Workflows', 'Multi-step role-based authorization for loans and capital adjustments', 'Security', TRUE),
('feat_sub', 'feature_subsidiary_ledger', 'Subsidiary Ledger Tracking', 'Granular accounting subsidiary breakdown by member, loan, and cash drawers', 'Accounting', TRUE),
('feat_notif', 'feature_notification_rules', 'Automated SMS / Push Triggers', 'Trigger alerts on loan approvals, overdue payments, and scheduled dues', 'Communications', TRUE);

-- CDA Chart of Accounts
INSERT INTO chart_of_accounts (id, account_code, name, category, normal_balance, is_active, parent_account_id, report_group, description) VALUES
('acc_1110', '1110', 'Cash on Hand - Tellers', 'Asset', 'Debit', TRUE, NULL, 'Current Assets', 'Petty cash and daily cashier vault drawers'),
('acc_1120', '1120', 'Cash in Bank - Land Bank of the Philippines', 'Asset', 'Debit', TRUE, NULL, 'Current Assets', 'LBP primary operating clearing depository'),
('acc_1121', '1121', 'Cash in Bank - Development Bank of the Philippines', 'Asset', 'Debit', TRUE, NULL, 'Current Assets', 'DBP high-yield special reserve depository'),
('acc_1210', '1210', 'Loans Receivable - Regular Multi-Purpose', 'Asset', 'Debit', TRUE, NULL, 'Loans and Receivables', 'Principal balance of outstanding member multi-purpose loans'),
('acc_1220', '1220', 'Loans Receivable - Emergency Micro-Loans', 'Asset', 'Debit', TRUE, NULL, 'Loans and Receivables', 'Emergency calamity and express medical credit lines'),
('acc_1230', '1230', 'Loans Receivable - Agricultural Crop Financing', 'Asset', 'Debit', TRUE, NULL, 'Loans and Receivables', 'Seasonal crop inputs, fertilizer, and agricultural financing'),
('acc_1290', '1290', 'Allowance for Probable Loan Losses', 'Asset', 'Credit', TRUE, NULL, 'Contra-Asset', 'Provision for PAR and non-performing loan impairments'),
('acc_1310', '1310', 'Interest Receivable on Loans', 'Asset', 'Debit', TRUE, NULL, 'Receivables', 'Accrued but uncollected loan installment interest'),
('acc_1510', '1510', 'Office & IT Equipment', 'Asset', 'Debit', TRUE, NULL, 'Property, Plant & Equipment', 'Servers, teller terminals, and office workstations'),
('acc_1590', '1590', 'Accumulated Depreciation - Office Equipment', 'Asset', 'Credit', TRUE, NULL, 'Contra-Asset', 'Depreciation reserve on operational equipment'),
('acc_2110', '2110', 'Savings Deposits - Regular', 'Liability', 'Credit', TRUE, NULL, 'Deposit Liabilities', 'Withdrawable member deposit savings balances'),
('acc_2120', '2120', 'Time Deposits - High Yield', 'Liability', 'Credit', TRUE, NULL, 'Deposit Liabilities', 'Fixed-term high-yield member placements'),
('acc_2210', '2210', 'Accounts Payable & Accrued Expenses', 'Liability', 'Credit', TRUE, NULL, 'Current Liabilities', 'Supplier payables and operational vendor balances'),
('acc_2220', '2220', 'Interest Payable on Deposits', 'Liability', 'Credit', TRUE, NULL, 'Current Liabilities', 'Accrued interest payable to member savings deposits'),
('acc_3110', '3110', 'Paid-Up Share Capital - Common (Voting)', 'Equity', 'Credit', TRUE, NULL, 'Share Capital', 'Member common share capital subscribed and paid'),
('acc_3120', '3120', 'Paid-Up Share Capital - Preferred (Non-Voting)', 'Equity', 'Credit', TRUE, NULL, 'Share Capital', 'Associate member preferred non-voting equity'),
('acc_3210', '3210', 'Statutory Reserve Fund (General)', 'Equity', 'Credit', TRUE, NULL, 'Statutory Reserves', 'Mandatory 10% statutory reserve mandated by CDA'),
('acc_3220', '3220', 'Coop Education & Training Fund (CETF)', 'Equity', 'Credit', TRUE, NULL, 'Statutory Reserves', 'Mandatory educational reserve (5% localized, 5% apex federation)'),
('acc_3230', '3230', 'Community Development Fund', 'Equity', 'Credit', TRUE, NULL, 'Statutory Reserves', 'Mandatory 3% social community outreach fund'),
('acc_3240', '3240', 'Optional Reserve Fund', 'Equity', 'Credit', TRUE, NULL, 'Statutory Reserves', 'Discretionary cooperative stability and building fund'),
('acc_3900', '3900', 'Undivided Net Surplus / Retained Earnings', 'Equity', 'Credit', TRUE, NULL, 'Equity Surplus', 'Cumulative operating surplus available for dividend allocation'),
('acc_4110', '4110', 'Interest Income from Loans', 'Revenue', 'Credit', TRUE, NULL, 'Operating Revenue', 'Earned interest collected on member loan disbursements'),
('acc_4120', '4120', 'Service & Processing Fees', 'Revenue', 'Credit', TRUE, NULL, 'Operating Revenue', 'Loan origination, filing, and notarial service fees'),
('acc_4130', '4130', 'Fines & Late Payment Penalties', 'Revenue', 'Credit', TRUE, NULL, 'Operating Revenue', 'Default penalty charges assessed on delinquent installments'),
('acc_4140', '4140', 'Membership & Admission Fees', 'Revenue', 'Credit', TRUE, NULL, 'Operating Revenue', 'Non-refundable membership application and seminar fees'),
('acc_5110', '5110', 'Interest Expense on Savings Deposits', 'Expense', 'Debit', TRUE, NULL, 'Financial Expenses', 'Annual dividend and monthly interest yield distributed on deposits'),
('acc_5210', '5210', 'Salaries, Wages & Employee Benefits', 'Expense', 'Debit', TRUE, NULL, 'Administrative Expenses', 'Staff compensation, 13th month pay, and personnel allowances'),
('acc_5220', '5220', 'Office Supplies, Utilities & Communication', 'Expense', 'Debit', TRUE, NULL, 'Administrative Expenses', 'Electric, water, telecommunications, and stationery expenses'),
('acc_5290', '5290', 'Provision for Loan Losses', 'Expense', 'Debit', TRUE, NULL, 'Credit Losses', 'Expense entry provisioning reserve for doubtful loan accounts');

-- Accounting Periods (Fiscal Year 2026)
INSERT INTO accounting_periods (id, name, fiscal_year, period_number, start_date, end_date, status) VALUES
('period_2026_01', 'January 2026', 2026, 1, '2026-01-01', '2026-01-31', 'Open'),
('period_2026_02', 'February 2026', 2026, 2, '2026-02-01', '2026-02-28', 'Open'),
('period_2026_03', 'March 2026', 2026, 3, '2026-03-01', '2026-03-31', 'Open'),
('period_2026_04', 'April 2026', 2026, 4, '2026-04-01', '2026-04-30', 'Open'),
('period_2026_05', 'May 2026', 2026, 5, '2026-05-01', '2026-05-31', 'Open'),
('period_2026_06', 'June 2026', 2026, 6, '2026-06-01', '2026-06-30', 'Open'),
('period_2026_07', 'July 2026', 2026, 7, '2026-07-01', '2026-07-31', 'Open'),
('period_2026_08', 'August 2026', 2026, 8, '2026-08-01', '2026-08-31', 'Open'),
('period_2026_09', 'September 2026', 2026, 9, '2026-09-01', '2026-09-30', 'Open'),
('period_2026_10', 'October 2026', 2026, 10, '2026-10-01', '2026-10-31', 'Open'),
('period_2026_11', 'November 2026', 2026, 11, '2026-11-01', '2026-11-30', 'Open'),
('period_2026_12', 'December 2026', 2026, 12, '2026-12-01', '2026-12-31', 'Open');

-- Accounting Transaction Mappings
INSERT INTO accounting_mappings (id, event_type, description, debit_account_id, credit_account_id, is_system) VALUES
('map_loan_rel', 'LOAN_RELEASE', 'Standard loan release journal entry', 'acc_1210', 'acc_1110', TRUE),
('map_loan_pmt', 'LOAN_PAYMENT', 'Loan installment repayment entry', 'acc_1110', 'acc_1210', TRUE),
('map_sav_dep', 'SAVINGS_DEPOSIT', 'Member savings cash deposit', 'acc_1110', 'acc_2110', TRUE),
('map_sav_with', 'SAVINGS_WITHDRAWAL', 'Member savings cash withdrawal', 'acc_2110', 'acc_1110', TRUE),
('map_sc_sub', 'SHARE_SUBSCRIPTION_PAYMENT', 'Share capital contribution', 'acc_1110', 'acc_3110', TRUE),
('map_int_inc', 'INTEREST_INCOME_RECOGNITION', 'Loan interest collected', 'acc_1110', 'acc_4110', TRUE),
('map_pen_inc', 'PENALTY_INCOME_RECOGNITION', 'Late payment default penalty', 'acc_1110', 'acc_4130', TRUE);

-- Numbering Formats
INSERT INTO numbering_formats (id, module, prefix, branch_specific, include_year, padding, next_number, pattern) VALUES
('num_mem', 'Members', 'MEM', FALSE, TRUE, 5, 1, 'MEM-{YEAR}-{NUMBER}'),
('num_loan', 'Loans', 'LN', TRUE, TRUE, 5, 1, '{BRANCH}-LN-{YEAR}-{NUMBER}'),
('num_or', 'Receipts', 'OR', TRUE, TRUE, 6, 1, '{BRANCH}-OR-{YEAR}-{NUMBER}'),
('num_jv', 'Journal', 'JV', TRUE, TRUE, 6, 1, '{BRANCH}-JV-{YEAR}-{NUMBER}'),
('num_cd', 'Disbursements', 'CD', TRUE, TRUE, 6, 1, '{BRANCH}-CD-{YEAR}-{NUMBER}'),
('num_sa', 'Savings', 'SA', TRUE, TRUE, 4, 1, '{BRANCH}-SA-{YEAR}-{NUMBER}'),
('num_cbu', 'ShareCapital', 'CBU', TRUE, TRUE, 4, 1, '{BRANCH}-CBU-{YEAR}-{NUMBER}');

-- Approval Workflows & Rules
INSERT INTO approval_workflows (id, name, module, description, active) VALUES
('wf_loan_standard', 'Standard Loan Approval Matrix', 'Loans', 'Tiered approval from Credit Officer up to Board of Directors', TRUE),
('wf_mem_standard', 'Member Admission Workflow', 'Members', 'Branch manager screening and Board validation', TRUE);

INSERT INTO approval_rules (id, workflow_id, step_order, approver_role, min_amount, max_amount, requires_board_action) VALUES
('rule_loan_01', 'wf_loan_standard', 1, 'role_credit_officer', 0, 50000.00, FALSE),
('rule_loan_02', 'wf_loan_standard', 2, 'role_manager', 50000.01, 150000.00, FALSE),
('rule_loan_03', 'wf_loan_standard', 3, 'role_bod', 150000.01, 9999999.00, TRUE);

-- Custom Fields for Members
INSERT INTO custom_fields (id, entity_type, field_key, label, field_type, options, is_required, active, display_order) VALUES
('cf_mem_01', 'Member', 'occupation', 'Primary Occupation / Enterprise', 'Select', '["Farmer / Fisherfolk", "Self-Employed / Entrepreneur", "Government Employee", "Private Sector Employee", "Healthcare Professional", "OFW / Remittance Dependent", "Retired"]', TRUE, TRUE, 1),
('cf_mem_02', 'Member', 'barangay', 'Barangay / Village Residence', 'Text', NULL, TRUE, TRUE, 2),
('cf_mem_03', 'Member', 'monthly_income', 'Estimated Monthly Household Income (PHP)', 'Number', NULL, TRUE, TRUE, 3),
('cf_mem_04', 'Member', 'tin_number', 'Tax Identification Number (TIN)', 'Text', NULL, FALSE, TRUE, 4);

-- Member Types
INSERT INTO member_types (id, name, code, description, voting_rights, min_share_capital, savings_requirement, loan_eligibility, required_documents, active) VALUES
('mt_regular', 'Regular Member', 'REGULAR', 'Full-fledged member with voting rights and dividend participation', TRUE, 10000.00, 1000.00, TRUE, '["Valid Gov ID", "2x2 ID Photo", "Proof of Billing", "PMES Certificate"]', TRUE),
('mt_associate', 'Associate Member', 'ASSOCIATE', 'Non-voting member enjoying savings deposit and credit facilities', FALSE, 5000.00, 500.00, TRUE, '["Valid Gov ID", "2x2 ID Photo", "Proof of Billing"]', TRUE),
('mt_lab', 'Laboratory / Youth Member', 'LAB_YOUTH', 'Minor/Student savings depositor preparing for future cooperative participation', FALSE, 500.00, 200.00, FALSE, '["Birth Certificate", "Parents Consent Form"]', TRUE);

-- Loan Products & Versions
INSERT INTO loan_products (id, code, name, description, version, min_amount, max_amount, min_term_months, max_term_months, annual_interest_rate, interest_calculation_method, payment_frequency, grace_period_days, penalty_rate_percentage, gl_receivable_account_id, gl_interest_income_account_id, active) VALUES
('lp_regular', 'LP-REG', 'Regular Multi-Purpose Loan', 'Standard term loan for regular members with 10% annual diminishing balance', 1, 10000.00, 300000.00, 6, 36, 10.00, 'Diminishing Balance', 'Monthly', 5, 2.00, 'acc_1210', 'acc_4110', TRUE),
('lp_emergency', 'LP-EMERG', 'Emergency Calamity & Health Loan', 'Quick disbursement micro-loan for verified hospitalizations or seasonal emergencies', 1, 5000.00, 30000.00, 3, 12, 6.00, 'Flat Rate', 'Semi-monthly', 3, 1.50, 'acc_1220', 'acc_4110', TRUE),
('lp_agri', 'LP-AGRI', 'Seasonal Agricultural Production Loan', 'Crop input financing tailored for rice, corn, and vegetable planting cycles', 1, 20000.00, 250000.00, 4, 8, 8.00, 'Equal Amortization', 'Lump Sum', 10, 2.00, 'acc_1230', 'acc_4110', TRUE);

INSERT INTO loan_product_versions (id, loan_product_id, version_number, annual_interest_rate, interest_calculation_method, effective_from, changed_by, reason) VALUES
('lpv_reg_v1', 'lp_regular', 1, 10.00, 'Diminishing Balance', '2026-01-01 00:00:00', 'System Initializer', 'Initial baseline configuration'),
('lpv_emerg_v1', 'lp_emergency', 1, 6.00, 'Flat Rate', '2026-01-01 00:00:00', 'System Initializer', 'Initial baseline configuration'),
('lpv_agri_v1', 'lp_agri', 1, 8.00, 'Equal Amortization', '2026-01-01 00:00:00', 'System Initializer', 'Initial baseline configuration');

-- Savings Products
INSERT INTO savings_products (id, code, name, min_balance_to_earn_interest, annual_interest_rate, interest_calculation_method, min_opening_deposit, maintaining_balance, gl_liability_account_id, gl_interest_expense_account_id, active) VALUES
('sp_regular', 'SAV-REG', 'Regular Savings Deposit', 1000.00, 2.00, 'Average Daily Balance', 500.00, 500.00, 'acc_2110', 'acc_5110', TRUE),
('sp_time', 'SAV-TIME', 'High-Yield Time Deposit (1 Year)', 20000.00, 5.50, 'Simple Interest', 20000.00, 20000.00, 'acc_2120', 'acc_5110', TRUE);

-- Share Capital Settings
INSERT INTO share_capital_settings (id, cooperative_id, par_value_per_share, min_subscription_shares, min_paid_up_shares, max_share_holding_percentage, transfer_fee, withdrawal_rule, accounting_account_id) VALUES
('sc_setting_01', 'coop_01', 100.00, 100, 25, 10.00, 100.00, 'Subject to Board approval and 30-day prior written notice', 'acc_3110');

-- Cash Accounts (All balances zeroed out ready for live cashiers)
INSERT INTO cash_accounts (id, name, account_number, bank_name, branch_id, gl_account_id, opening_balance, current_balance, currency, active) VALUES
('cash_01', 'Cash on Hand - Teller 1', 'COH-TAR-01', 'Cash Vault Drawer', 'branch_tar', 'acc_1110', 0.00, 0.00, 'PHP', TRUE),
('cash_02', 'Main Vault Reserve', 'VLT-TAR-00', 'Master Vault Safety Depository', 'branch_tar', 'acc_1110', 0.00, 0.00, 'PHP', TRUE),
('cash_03', 'Land Bank of the Philippines - Operating Checking', 'LBP-0912-3341-99', 'Land Bank of the Philippines', 'branch_tar', 'acc_1120', 0.00, 0.00, 'PHP', TRUE),
('cash_04', 'Development Bank of the Philippines - High Yield', 'DBP-4401-2990-11', 'Development Bank of the Philippines', 'branch_tar', 'acc_1121', 0.00, 0.00, 'PHP', TRUE),
('cash_05', 'Urdaneta Branch Teller Cash', 'COH-URD-01', 'Cash Drawer Urdaneta', 'branch_urd', 'acc_1110', 0.00, 0.00, 'PHP', TRUE),
('cash_06', 'San Fernando Branch Teller Cash', 'COH-SFE-01', 'Cash Drawer San Fernando', 'branch_sfe', 'acc_1110', 0.00, 0.00, 'PHP', TRUE);

-- Standard Fees
INSERT INTO fees (id, code, name, calculation_type, amount, applies_to, gl_account_id, active) VALUES
('fee_proc', 'FEE_PROC', 'Loan Processing Fee', 'Percentage', 2.00, 'Loan', 'acc_4120', TRUE),
('fee_service', 'FEE_SERVICE', 'Documentary & Credit Verification Fee', 'Fixed', 250.00, 'Loan', 'acc_4120', TRUE),
('fee_membership', 'FEE_MEMB', 'Membership Pre-Membership Seminar Fee', 'Fixed', 300.00, 'Member', 'acc_4140', TRUE),
('fee_id_passbook', 'FEE_PASSBOOK', 'Passbook & Identification Card Issuance', 'Fixed', 150.00, 'Member', 'acc_4140', TRUE);

-- Penalty Rules
INSERT INTO penalty_rules (id, name, grace_period_days, penalty_rate_percentage, calculation_base, compounding_frequency, gl_income_account_id) VALUES
('pen_default', 'Standard Default Amortization Penalty', 5, 2.00, 'Overdue Principal', 'None', 'acc_4130');

-- Payment Allocation Rules
INSERT INTO payment_allocation_rules (id, name, description, priority_order, is_default) VALUES
('alloc_cda_std', 'CDA Standard Priority Hierarchy', 'Penalties first, then accrued interest, then service fees, then principal reduction', '["Penalty", "Interest", "Fees", "Principal"]', TRUE);

-- Payment Frequencies
INSERT INTO payment_frequencies (id, name, days_interval, periods_per_year, active) VALUES
('freq_monthly', 'Monthly', 30, 12, TRUE),
('freq_semimonthly', 'Semi-monthly', 15, 24, TRUE),
('freq_weekly', 'Weekly', 7, 52, TRUE),
('freq_lumpsum', 'Lump Sum', 180, 2, TRUE);

-- Document Requirements
INSERT INTO document_requirements (id, module, name, is_mandatory, active) VALUES
('doc_valid_id', 'Members', 'Government Issued Valid ID (Driver License, UMID, Passport)', TRUE, TRUE),
('doc_billing', 'Members', 'Proof of Billing / Residence Certificate', TRUE, TRUE),
('doc_pmes', 'Members', 'Pre-Membership Education Seminar (PMES) Certificate', TRUE, TRUE),
('doc_income', 'Loans', 'Proof of Income / Income Tax Return / Crop Harvest Log', TRUE, TRUE),
('doc_promissory', 'Loans', 'Signed Promissory Note with Co-maker Agreement', TRUE, TRUE);

-- Transaction Types
INSERT INTO transaction_types (id, code, name, module, requires_approval, numbering_format_id, active) VALUES
('tx_loan_rel', 'LOAN_RELEASE', 'Loan Disbursement Voucher', 'Loans', TRUE, 'num_cd', TRUE),
('tx_loan_pmt', 'LOAN_PAYMENT', 'Loan Amortization Collection', 'Loans', FALSE, 'num_or', TRUE),
('tx_sav_dep', 'SAVINGS_DEPOSIT', 'Savings Deposit Slip', 'Savings', FALSE, 'num_or', TRUE),
('tx_sav_with', 'SAVINGS_WITHDRAWAL', 'Savings Cash Withdrawal Voucher', 'Savings', TRUE, 'num_cd', TRUE),
('tx_sc_pay', 'SHARE_CAPITAL_PAYMENT', 'Capital Build-Up OR', 'ShareCapital', FALSE, 'num_or', TRUE),
('tx_open_bal', 'OPENING_BALANCE', 'Opening Balance Journal Entry', 'Accounting', TRUE, 'num_jv', TRUE);

-- User Roles
INSERT INTO user_roles (id, name, description, permissions, active) VALUES
('role_admin', 'System Administrator', 'Full unconstrained platform super-user access', '["manage_system", "manage_configurations", "manage_members", "manage_loans", "manage_savings", "manage_accounting", "approve_transactions", "view_reports"]', TRUE),
('role_manager', 'General / Branch Manager', 'Branch operations supervisor with level 2 approval rights', '["manage_members", "manage_loans", "manage_savings", "approve_transactions", "view_reports"]', TRUE),
('role_accountant', 'Chief / Branch Accountant', 'General ledger management, journal vouchers, and period close', '["manage_accounting", "view_gl", "post_journals", "close_periods", "view_reports"]', TRUE),
('role_loan_officer', 'Credit & Loan Evaluation Officer', 'Loan application review, credit investigation, and schedule creation', '["manage_loans", "view_members", "create_schedules"]', TRUE),
('role_teller', 'Teller / Cashier', 'Over-the-counter payments, deposits, and cash drawer settlements', '["receive_payments", "issue_receipts", "cash_inflow", "cash_outflow"]', TRUE),
('role_bod', 'Board of Directors', 'High-level policy review, macro-reporting, and large-loan approvals', '["approve_tier3_loans", "view_reports", "view_audit_trail"]', TRUE);

-- Default Admin User (Password: "Admin@123456" - bcrypt hash)
INSERT INTO users (id, username, password_hash, full_name, email, role_id, branch_id, active) VALUES
('user_admin', 'admin', '$2y$10$e8PZ7YJ1s7vWc6M79iA2/eqiB4uJtY76yW55N5Q1nLqK9hX2HjB2.', 'System Administrator', 'admin@mayapcare.coop', 'role_admin', 'branch_tar', TRUE);

-- Audit log
INSERT INTO configuration_audit_trails (id, setting, old_value, new_value, changed_by, reason) VALUES
('audit_init_01', 'Cooperative SQL Initialization', 'None', 'Clean Schema & Master Seeds Created', 'System Administrator', 'Deployment of SQL database for PHP MVC backend integration');

-- =====================================================================
-- END OF SQL SCHEMA SCRIPT
-- =====================================================================
