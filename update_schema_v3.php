<?php
// update_schema_v3.php
require 'db.php';

echo "Updating database schema for Phase 7...<br>";

try {
    // Create team_members table for the Global Showcase
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role_title VARCHAR(150) NOT NULL,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    echo "Created team_members table.<br>";
} catch (PDOException $e) {
    echo "Error creating team_members: " . $e->getMessage() . "<br>";
}

echo "Phase 7 Schema Update Complete!";
?>
