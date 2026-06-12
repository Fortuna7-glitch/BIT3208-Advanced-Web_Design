-- Database: saloon_management_system

CREATE DATABASE IF NOT EXISTS saloon_management_system;
USE saloon_management_system;

-- Table: users (customers + staff + admin combined with role)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'customer') DEFAULT 'customer',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: staff_details (extra info for staff)
CREATE TABLE IF NOT EXISTS staff_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialty VARCHAR(100),
    experience_years INT DEFAULT 0,
    bio TEXT,
    profile_image VARCHAR(255) DEFAULT 'default.jpg',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table: services
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_minutes INT DEFAULT 30,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: appointments
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT,
    service_id INT NOT NULL,
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

-- Table: payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'mpesa') NOT NULL,
    transaction_code VARCHAR(100),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id)
);

-- Table: notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT,
    type ENUM('email', 'sms') DEFAULT 'email',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table: salon_settings (for individual salon settings)
CREATE TABLE IF NOT EXISTS salon_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin account (password: admin123)
INSERT INTO users (full_name, email, phone, password, role) VALUES 
('Admin', 'admin@salonpro.com', '0712345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample staff
INSERT INTO users (full_name, email, phone, password, role) VALUES 
('Jane Smith', 'jane@salonpro.com', '0723456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff'),
('Mary Johnson', 'mary@salonpro.com', '0734567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');

-- Insert staff details
INSERT INTO staff_details (user_id, specialty, experience_years, bio) VALUES 
(2, 'Hair Stylist & Colorist', 5, 'Expert in modern haircuts and coloring techniques'),
(3, 'Makeup Artist', 4, 'Specializes in bridal and event makeup');

-- Insert sample services
INSERT INTO services (service_name, description, price, duration_minutes) VALUES 
('Haircut', 'Professional haircut and styling', 800.00, 30),
('Braiding', 'Beautiful braids of your choice', 2500.00, 120),
('Facial', 'Deep cleansing and moisturizing facial', 1500.00, 45),
('Makeup', 'Professional makeup application', 3000.00, 60),
('Manicure', 'Nail care and polish', 1200.00, 30),
('Pedicure', 'Foot care and polish', 1500.00, 45),
('Hair Coloring', 'Full hair coloring', 3500.00, 90),
('Waxing', 'Full body waxing', 2000.00, 60);

-- Insert salon settings
INSERT INTO salon_settings (setting_key, setting_value) VALUES 
('salon_name', 'Salon Pro'),
('salon_slogan', 'Where Beauty Meets Excellence'),
('salon_email', 'info@salonpro.com'),
('salon_phone', '0712345678'),
('salon_address', '123 Luxury Mall, Nairobi, Kenya');
