-- Create the database
CREATE DATABASE IF NOT EXISTS blood_donation CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE blood_donation;

-- Remove old tables (children first) so the script can be re-run
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS donation_pledges, blood_requests, blood_stock, users;
SET FOREIGN_KEY_CHECKS = 1;

-- One table for all three roles.
-- For a hospital, "name" is the hospital name and blood_type stays empty.
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient','donor','hospital') NOT NULL,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Units of each blood type held by each hospital (a missing row = "not set")
CREATE TABLE blood_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    units INT NOT NULL DEFAULT 0,
    UNIQUE KEY one_row_per_type (hospital_id, blood_type),
    FOREIGN KEY (hospital_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Formal blood requests from patients to hospitals
CREATE TABLE blood_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    units INT NOT NULL,
    urgency ENUM('Normal','Urgent','Emergency') NOT NULL DEFAULT 'Normal',
    note VARCHAR(255) NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Donor pledges. A Confirmed pledge is a real donation (donated_on = the day it was confirmed).
CREATE TABLE donation_pledges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    hospital_id INT NOT NULL,
    pledge_date DATE NOT NULL,
    status ENUM('Pending','Confirmed','Declined') NOT NULL DEFAULT 'Pending',
    donated_on DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===== SAMPLE DATA (all passwords: password123) =====
INSERT INTO users (id, name, email, password, role, blood_type) VALUES
(1, 'City General Hospital', 'city@hospital.com',     '$2y$10$i29kiuBzivrH0/aIpB5yKuHd6QWEz6rvyJB4UAhjYVxZma6NPAXQa', 'hospital', NULL),
(2, 'Green Valley Hospital', 'green@hospital.com',    '$2y$10$i29kiuBzivrH0/aIpB5yKuHd6QWEz6rvyJB4UAhjYVxZma6NPAXQa', 'hospital', NULL),
(3, 'Saman Fernando',        'patient1@mail.com',     '$2y$10$i29kiuBzivrH0/aIpB5yKuHd6QWEz6rvyJB4UAhjYVxZma6NPAXQa', 'patient',  'A+'),
(4, 'Nadee Jayasuriya',      'patient2@mail.com',     '$2y$10$i29kiuBzivrH0/aIpB5yKuHd6QWEz6rvyJB4UAhjYVxZma6NPAXQa', 'patient',  'B+'),
(5, 'Nimal Perera',          'donor1@mail.com',       '$2y$10$i29kiuBzivrH0/aIpB5yKuHd6QWEz6rvyJB4UAhjYVxZma6NPAXQa', 'donor',    'O+'),
(6, 'Kamal Silva',           'donor2@mail.com',       '$2y$10$i29kiuBzivrH0/aIpB5yKuHd6QWEz6rvyJB4UAhjYVxZma6NPAXQa', 'donor',    'A-'),
(7, 'Amali Rodrigo',         'donor3@mail.com',       '$2y$10$i29kiuBzivrH0/aIpB5yKuHd6QWEz6rvyJB4UAhjYVxZma6NPAXQa', 'donor',    'B+'),
-- A brand-new hospital: no stock rows yet, so it creates no alerts
(8, 'Riverside Clinic',      'riverside@hospital.com','$2y$10$i29kiuBzivrH0/aIpB5yKuHd6QWEz6rvyJB4UAhjYVxZma6NPAXQa', 'hospital', NULL);

-- City General: A- 8 (LOW), B- 3 (CRITICAL), AB- 6 (LOW), O- 4 (CRITICAL)
-- Green Valley: B+ 9 (LOW), O+ 7 (LOW)
INSERT INTO blood_stock (hospital_id, blood_type, units) VALUES
(1,'A+',25),(1,'A-',8),(1,'B+',15),(1,'B-',3),(1,'AB+',12),(1,'AB-',6),(1,'O+',20),(1,'O-',4),
(2,'A+',14),(2,'A-',12),(2,'B+',9),(2,'B-',11),(2,'AB+',18),(2,'AB-',10),(2,'O+',7),(2,'O-',15);

-- Past donations (dates are relative to today so the demo always works):
-- donor1 donated 200 days ago (can donate now), donor2 donated 30 days ago (blocked for 4 months)
INSERT INTO donation_pledges (donor_id, hospital_id, pledge_date, status, donated_on) VALUES
(5, 1, DATE_SUB(CURDATE(), INTERVAL 200 DAY), 'Confirmed', DATE_SUB(CURDATE(), INTERVAL 200 DAY)),
(6, 2, DATE_SUB(CURDATE(), INTERVAL 30 DAY),  'Confirmed', DATE_SUB(CURDATE(), INTERVAL 30 DAY));

-- One pending pledge and two pending requests
INSERT INTO donation_pledges (donor_id, hospital_id, pledge_date, status) VALUES
(7, 1, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Pending');

INSERT INTO blood_requests (patient_id, hospital_id, blood_type, units, urgency, note) VALUES
(3, 1, 'A+', 2, 'Urgent', 'Needed for surgery this week'),
(4, 2, 'B+', 3, 'Normal', 'Scheduled treatment');
