-- Database: vitaguard_db
CREATE DATABASE IF NOT EXISTS vitaguard_db;
USE vitaguard_db;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('patient', 'doctor', 'pharmacist', 'admin') NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    status ENUM('Pending', 'Approved', 'Cancelled') DEFAULT 'Pending',
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 3. Health_Records Table
CREATE TABLE IF NOT EXISTS health_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    blood_pressure VARCHAR(20),
    blood_sugar VARCHAR(20),
    pulse_rate VARCHAR(20),
    temperature VARCHAR(20),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 4. Medicines (Inventory) Table
CREATE TABLE IF NOT EXISTS medicines (
    medicine_id INT AUTO_INCREMENT PRIMARY KEY,
    trade_name VARCHAR(100) NOT NULL,
    generic_name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    unit_price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Prescriptions Table
CREATE TABLE IF NOT EXISTS prescriptions (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    instructions TEXT,
    status ENUM('Active', 'Completed') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- 6. Prescription_Items Table
CREATE TABLE IF NOT EXISTS prescription_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(50),
    duration_days INT NOT NULL,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE CASCADE
);

-- 7. Medication_Tracker Table
CREATE TABLE IF NOT EXISTS medication_tracker (
    tracker_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medicine_id INT NOT NULL,
    intake_time TIME NOT NULL,
    status ENUM('Taken', 'Skipped') DEFAULT 'Skipped',
    log_date DATE NOT NULL,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE CASCADE
);

-- 8. System_Notices Table
CREATE TABLE IF NOT EXISTS system_notices (
    notice_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    target_role ENUM('all', 'patient', 'doctor', 'pharmacist') DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Dummy Data for Users (Password for all accounts: 123456)
-- Hash generated using PHP password_hash('123456', PASSWORD_DEFAULT)
INSERT INTO users (name, email, password_hash, role, phone) VALUES
('System Admin', 'admin@vitaguard.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeNkJLvhJ3M4kP3wFk/XoK5WbYyYlIe.u', 'admin', '01711000000'),
('Dr. Sadman Sakib', 'doctor@vitaguard.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeNkJLvhJ3M4kP3wFk/XoK5WbYyYlIe.u', 'doctor', '01711000001'),
('Tanvir Patient', 'patient@vitaguard.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeNkJLvhJ3M4kP3wFk/XoK5WbYyYlIe.u', 'patient', '01711000002'),
('Nahid Pharmacist', 'pharmacist@vitaguard.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeNkJLvhJ3M4kP3wFk/XoK5WbYyYlIe.u', 'pharmacist', '01711000003');