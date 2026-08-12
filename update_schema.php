<?php
require 'db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL;");
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL;");
} catch (PDOException $e) {
    // Columns might already exist
}

$pdo->exec("
CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    event_date DATETIME,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(150) NOT NULL,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
");

// Insert default permissions
$pdo->exec("
INSERT IGNORE INTO permissions (permission_name) VALUES 
('manage_users'),
('manage_clubs'),
('approve_members'),
('post_announcements'),
('manage_roles');
");

// Give all permissions to Role 1 (Admin)
$pdo->exec("
INSERT IGNORE INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions;
");

echo "Database schema updated successfully!\n";
?>
