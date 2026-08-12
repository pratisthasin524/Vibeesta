CREATE DATABASE IF NOT EXISTS vibeesta_db;
USE vibeesta_db;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_email_verified BOOLEAN DEFAULT FALSE,
    is_approved_by_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Roles Table (President, Vice President, etc.)
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

-- Permissions Table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(50) NOT NULL UNIQUE
);

-- Role_Permissions Table
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT,
    permission_id INT,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Clubs Table
CREATE TABLE IF NOT EXISTS clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    theme_color VARCHAR(20) DEFAULT '#000000',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Club Memberships Table
CREATE TABLE IF NOT EXISTS club_memberships (
    user_id INT,
    club_id INT,
    club_role ENUM('LEAD', 'CO-LEAD', 'MEMBER') DEFAULT 'MEMBER',
    status ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    PRIMARY KEY (user_id, club_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
);

-- User System Roles Table (Mapping Users to overall System Roles like Admin, President)
CREATE TABLE IF NOT EXISTS user_system_roles (
    user_id INT,
    role_id INT,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Insert Default Roles
INSERT IGNORE INTO roles (role_name) VALUES 
('Admin'), 
('President'), 
('Vice President'), 
('Executive'), 
('Operational Head'), 
('Organizer'), 
('Secretary');

-- Insert Initial Clubs (Based on image provided)
INSERT IGNORE INTO clubs (name, description, theme_color) VALUES 
('Film making - Vibeesta', 'The official Film making club.', '#0a4275'),
('Music - Vibeesta', 'The official Music club.', '#b17b2b'),
('Management - Vibeesta', 'The official Management club.', '#4a235a'),
('Fashion and modeling - Vibeesta', 'The official Fashion and modeling club.', '#145a32'),
('PR - Vibeesta', 'Public Relations club.', '#784212'),
('Design and media - Vibeesta', 'Design and media club.', '#4a235a'),
('Editorial - Vibeesta', 'Editorial club.', '#1b4f72'),
('Social media - Vibeesta', 'Social media club.', '#7b241c'),
('Arts - Vibeesta', 'Arts club.', '#641e16');

-- Note: A default admin user should be created manually or via a secure installation script.
