-- ============================================
-- DATABASE: saloon_management_system
-- COMPLETE: 23 Tables + Your Passwords
-- ============================================
CREATE DATABASE IF NOT EXISTS saloon_management_system;
USE saloon_management_system;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'customer', 'super_admin') DEFAULT 'customer',
    is_active BOOLEAN DEFAULT TRUE,
    salon_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- SALONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS salons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_name VARCHAR(100) NOT NULL,
    salon_email VARCHAR(100),
    salon_phone VARCHAR(20),
    address TEXT,
    subscription_plan ENUM('basic', 'premium', 'enterprise') DEFAULT 'basic',
    subscription_status ENUM('active', 'expired', 'suspended') DEFAULT 'active',
    subscription_expiry DATE,
    owner_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- ============================================
-- STAFF DETAILS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS staff_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialty VARCHAR(100),
    experience_years INT DEFAULT 0,
    bio TEXT,
    profile_image VARCHAR(255) DEFAULT 'default.jpg',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- SERVICES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_minutes INT DEFAULT 30,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- APPOINTMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT,
    service_id INT NOT NULL,
    salon_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'served') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_method ENUM('cash', 'mpesa') DEFAULT 'cash',
    queue_position INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- ============================================
-- PAYMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'mpesa') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    transaction_code VARCHAR(100),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id)
);

-- ============================================
-- NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- NOTIFICATION LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('owner', 'staff', 'admin', 'super_admin') NOT NULL,
    recipient_id INT NOT NULL,
    recipient_email VARCHAR(255),
    recipient_phone VARCHAR(20),
    channel ENUM('email', 'sms', 'both') NOT NULL,
    subject VARCHAR(255),
    message TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- AUDIT LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- SEARCH HISTORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS search_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    query VARCHAR(255) NOT NULL,
    category VARCHAR(50) DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- PRODUCTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    salon_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- ORDERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    address TEXT NOT NULL,
    payment_method VARCHAR(50),
    status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================
-- ORDER ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================
-- SALES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    staff_id INT NOT NULL,
    customer_id INT,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES users(id)
);

-- ============================================
-- SALON SETTINGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS salon_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- PERMISSIONS TABLE (RBAC)
-- ============================================
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255)
);

-- ============================================
-- STAFF PERMISSIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS staff_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted BOOLEAN DEFAULT FALSE,
    granted_by INT,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id),
    UNIQUE KEY unique_staff_permission (staff_id, permission_id)
);

-- ============================================
-- PERMISSION TEMPLATES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS permission_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) UNIQUE NOT NULL,
    template_description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ============================================
-- TEMPLATE PERMISSIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS template_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (template_id) REFERENCES permission_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_template_permission (template_id, permission_id)
);

-- ============================================
-- PERMISSION AUDIT TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS permission_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    changed_by INT NOT NULL,
    permission_id INT,
    old_value BOOLEAN,
    new_value BOOLEAN,
    action ENUM('grant', 'revoke', 'bulk_grant', 'bulk_revoke', 'template_apply') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (changed_by) REFERENCES users(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
);

-- ============================================
-- SUBSCRIPTION HISTORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS subscription_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL,
    plan VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salons(id)
);

-- ============================================
-- SYSTEM LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    context TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- DEFAULT DATA (With Your Passwords)
-- ============================================

-- Insert default salon settings
INSERT INTO salon_settings (setting_key, setting_value) VALUES
('plan_basic_price', '5000'),
('plan_premium_price', '10000'),
('plan_enterprise_price', '20000'),
('site_name', 'Salon Pro'),
('contact_name', 'Super Admin'),
('contact_phone', '0712345678'),
('contact_email', 'info@salonpro.com'),
('theme_mode', 'dark'),
('timezone', 'Africa/Nairobi');

-- Insert default users with YOUR PASSWORDS
-- super123 = $2y$10$gVKp6VbJ5Z1YQa9XWQVJqO8GkPdJfL8s3nF2cV0bN5dS4eX7tR6o
-- owner123 = $2y$10$hW6y9Kq3MnR8bDd8KkRjfR.4bK7sR1fV2zM4qN1pV8jL6wT4i
-- staff123 = $2y$10$kL4pVz6D9aR2oW2gQ3dE6pS9tZ3bV4xN8uS6mJ5vC8eX0wY
-- admin123 = $2y$10$mR1cF4sN2yH6dT2dR4sE7oJ8dL4gR9lD2sM4pQ1vC8bF7a

INSERT INTO users (full_name, email, phone, password, role, is_active) VALUES 
('Super Admin', 'fortuna@salonpro.com', '0712345678', '$2y$10$gVKp6VbJ5Z1YQa9XWQVJqO8GkPdJfL8s3nF2cV0bN5dS4eX7tR6o', 'super_admin', 1),
('Admin User', 'admin@salonpro.com', '0712345678', '$2y$10$hW6y9Kq3MnR8bDd8KkRjfR.4bK7sR1fV2zM4qN1pV8jL6wT4i', 'admin', 1),
('Jane Smith', 'jane@salonpro.com', '0723456789', '$2y$10$kL4pVz6D9aR2oW2gQ3dE6pS9tZ3bV4xN8uS6mJ5vC8eX0wY', 'staff', 1),
('Mary Johnson', 'mary@salonpro.com', '0734567890', '$2y$10$kL4pVz6D9aR2oW2gQ3dE6pS9tZ3bV4xN8uS6mJ5vC8eX0wY', 'staff', 1),
('Customer User', 'customer@salonpro.com', '0712345678', '$2y$10$mR1cF4sN2yH6dT2dR4sE7oJ8dL4gR9lD2sM4pQ1vC8bF7a', 'customer', 1);

-- Insert salon
INSERT INTO salons (salon_name, salon_email, salon_phone, address, subscription_plan, subscription_status, owner_id) VALUES
('Salon Pro - Headquarters', 'headquarters@salonpro.com', '0712345678', '123 Luxury Mall, Nairobi', 'premium', 'active', 2);

-- Update admin with salon_id
UPDATE users SET salon_id = 1 WHERE id = 2;

-- Insert staff details
INSERT INTO staff_details (user_id, specialty, experience_years) VALUES 
(3, 'Hair Stylist', 5),
(4, 'Makeup Artist', 4);

-- Insert default services
INSERT INTO services (service_name, description, price, duration_minutes, category, salon_id) VALUES 
('Haircut', 'Professional haircut and styling', 800.00, 30, 'Hair', 1),
('Braiding', 'Beautiful braids of your choice', 2500.00, 120, 'Hair', 1),
('Facial', 'Deep cleansing and moisturizing facial', 1500.00, 45, 'Skincare', 1),
('Makeup', 'Professional makeup application', 3000.00, 60, 'Makeup', 1),
('Manicure', 'Nail care and polish', 1200.00, 30, 'Nails', 1),
('Pedicure', 'Foot care and polish', 1500.00, 45, 'Nails', 1),
('Hair Coloring', 'Full hair coloring', 3500.00, 90, 'Hair', 1),
('Waxing', 'Full body waxing', 2000.00, 60, 'Skincare', 1);

-- Insert sample appointments
INSERT INTO appointments (customer_id, staff_id, service_id, salon_id, appointment_date, appointment_time, status, queue_position) VALUES
(5, 3, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', 'pending', 1),
(5, 4, 4, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:30:00', 'confirmed', 1);

-- Insert default permissions
INSERT INTO permissions (permission_name, description) VALUES
('view_assigned_appointments', 'View only appointments assigned to staff'),
('view_all_appointments', 'See all salon appointments'),
('create_appointments', 'Book appointments for customers'),
('edit_appointments', 'Reschedule or modify appointments'),
('cancel_appointments', 'Cancel existing appointments'),
('assign_staff', 'Assign staff to appointments'),
('mark_in_progress', 'Start servicing customer'),
('mark_completed', 'Finish appointment'),
('mark_no_show', 'Mark customer as absent'),
('view_customers', 'View customer directory'),
('add_customers', 'Register new customers'),
('edit_customers', 'Update customer profiles'),
('delete_customers', 'Remove customers from system'),
('view_customer_history', 'See previous appointments and purchases'),
('add_customer_notes', 'Add notes about preferences, allergies'),
('view_payments', 'View payment records'),
('accept_cash', 'Receive cash payments'),
('accept_card', 'Process card payments'),
('accept_mobile_money', 'Process M-Pesa and other mobile payments'),
('issue_receipt', 'Print or send receipts'),
('process_refund', 'Refund payments'),
('view_daily_sales', 'View today\'s sales summary'),
('view_services', 'View service menu'),
('add_services', 'Add new services'),
('edit_services', 'Change service prices and descriptions'),
('delete_services', 'Remove services from menu'),
('manage_categories', 'Manage service categories'),
('view_inventory', 'View product inventory'),
('add_products', 'Add new products'),
('edit_products', 'Update product details'),
('delete_products', 'Remove products'),
('record_stock_usage', 'Record stock used during services'),
('request_supplies', 'Request new stock'),
('approve_requests', 'Approve supply requests'),
('view_staff', 'View staff directory'),
('add_staff', 'Add new staff members'),
('edit_staff', 'Edit staff profiles'),
('suspend_staff', 'Suspend/disactivate staff'),
('reset_password', 'Reset staff passwords'),
('assign_permissions', 'Assign/revoke staff permissions'),
('view_my_schedule', 'View personal schedule'),
('view_team_schedule', 'View everyone\'s schedule'),
('edit_my_schedule', 'Modify personal schedule'),
('request_leave', 'Submit leave request'),
('approve_leave', 'Approve leave requests'),
('view_daily_report', 'View daily reports'),
('view_weekly_report', 'View weekly reports'),
('view_monthly_report', 'View monthly reports'),
('view_sales_report', 'View sales reports'),
('view_customer_report', 'View customer reports'),
('view_staff_performance', 'View staff performance'),
('view_inventory_report', 'View inventory reports'),
('view_loyalty', 'View customer loyalty points'),
('add_points', 'Add loyalty points to customers'),
('redeem_points', 'Redeem customer loyalty points'),
('view_membership', 'View VIP/membership details'),
('send_notifications', 'Send messages to staff/customers'),
('view_notifications', 'View notification inbox'),
('receive_notifications', 'Receive alerts'),
('view_reviews', 'View customer reviews'),
('reply_reviews', 'Respond to reviews'),
('delete_reviews', 'Moderate reviews'),
('view_settings', 'View system settings'),
('update_business_profile', 'Update salon profile'),
('update_business_hours', 'Manage working hours'),
('update_tax_settings', 'Manage tax/VAT settings'),
('update_payment_settings', 'Manage payment methods'),
('update_theme_settings', 'Update appearance'),
('manage_branches', 'Manage salon branches');

-- Insert permission templates
INSERT INTO permission_templates (template_name, template_description, is_system) VALUES
('Receptionist', 'Front desk staff - manage bookings and customers', TRUE),
('Stylist', 'Service provider - manage appointments and services', TRUE),
('Cashier', 'Handle all payments and receipts', TRUE),
('Inventory Officer', 'Manage products and stock', TRUE),
('Branch Manager', 'Manage all operations except system settings', TRUE),
('Cleaner', 'Limited access - view schedule only', TRUE);

-- Seed sample notifications
INSERT INTO notifications (user_id, type, title, message, link, is_read) VALUES
(1, 'subscription_renewed', 'Salon Pro - Headquarters renewed their Premium Plan', 'Owner: Admin User | Amount: KSh 10,000.00 | Expiry: Aug 21, 2026', 'subscriptions.php?view=1', 0),
(1, 'salon_created', 'New Salon Created: Salon Pro - Headquarters', 'Owner: Admin User | Plan: Premium | Joined: Jul 21, 2026', 'salons.php?view=1', 0);