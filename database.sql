-- Rental Shop Management System Database
-- Run this SQL to set up the database

-- CREATE DATABASE IF NOT EXISTS rental_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE rental_shop;

-- Users (shop owners / admins)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Shops
CREATE TABLE IF NOT EXISTS shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_number VARCHAR(20) NOT NULL UNIQUE,
    description VARCHAR(255),
    location VARCHAR(150),
    size_sqft DECIMAL(8,2),
    base_rent DECIMAL(10,2) NOT NULL,
    status ENUM('occupied','vacant','maintenance') DEFAULT 'vacant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tenants
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    cnic VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(150),
    address TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Leases (tenant-shop assignments)
CREATE TABLE IF NOT EXISTS leases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    shop_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    monthly_rent DECIMAL(10,2) NOT NULL,
    security_deposit DECIMAL(10,2) DEFAULT 0,
    status ENUM('active','terminated','expired') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
    FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE RESTRICT
);

-- Bills (monthly rent invoices)
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lease_id INT NOT NULL,
    tenant_id INT NOT NULL,
    shop_id INT NOT NULL,
    bill_month DATE NOT NULL,           -- First day of billing month
    rent_amount DECIMAL(10,2) NOT NULL,
    previous_dues DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    outstanding DECIMAL(10,2) GENERATED ALWAYS AS (total_amount - amount_paid) STORED,
    status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lease_id) REFERENCES leases(id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (shop_id) REFERENCES shops(id)
);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    tenant_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash','bank_transfer','cheque','online') DEFAULT 'cash',
    reference_no VARCHAR(100),
    notes TEXT,
    receipt_no VARCHAR(50) UNIQUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES
('Shop Owner', 'admin@rentalshop.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Sample shops
INSERT INTO shops (shop_number, description, location, size_sqft, base_rent, status) VALUES
('A-01', 'Corner shop with extra space', 'Block A, Ground Floor', 450.00, 15000.00, 'occupied'),
('A-02', 'Standard shop unit', 'Block A, Ground Floor', 350.00, 12000.00, 'occupied'),
('A-03', 'Standard shop unit', 'Block A, Ground Floor', 350.00, 12000.00, 'vacant'),
('B-01', 'Large shop with storage', 'Block B, Ground Floor', 600.00, 20000.00, 'occupied'),
('B-02', 'Standard shop unit', 'Block B, First Floor', 350.00, 10000.00, 'vacant'),
('C-01', 'Premium corner shop', 'Block C, Ground Floor', 500.00, 18000.00, 'occupied')
ON DUPLICATE KEY UPDATE id=id;
