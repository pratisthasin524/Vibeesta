<?php
// update_schema_v2.php
require 'db.php';

echo "Updating database schema for Phase 6...<br>";

try {
    // Add background_image to clubs
    $pdo->exec("ALTER TABLE clubs ADD COLUMN background_image VARCHAR(255) DEFAULT NULL;");
    echo "Added background_image to clubs.<br>";
} catch (PDOException $e) {
    echo "Column background_image might already exist.<br>";
}

try {
    // Create gallery_images table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gallery_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            title VARCHAR(150),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "Created gallery_images table.<br>";
} catch (PDOException $e) {
    echo "Error creating gallery_images: " . $e->getMessage() . "<br>";
}

try {
    // Create forum_topics table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS forum_topics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            club_id INT NOT NULL,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    echo "Created forum_topics table.<br>";
} catch (PDOException $e) {
    echo "Error creating forum_topics: " . $e->getMessage() . "<br>";
}

try {
    // Create forum_comments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS forum_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            topic_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    echo "Created forum_comments table.<br>";
} catch (PDOException $e) {
    echo "Error creating forum_comments: " . $e->getMessage() . "<br>";
}

try {
    // Create event_rsvps table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_rsvps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            status ENUM('GOING', 'MAYBE', 'CANT_GO') DEFAULT 'GOING',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_rsvp (event_id, user_id),
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    echo "Created event_rsvps table.<br>";
} catch (PDOException $e) {
    echo "Error creating event_rsvps: " . $e->getMessage() . "<br>";
}

echo "Phase 6 Schema Update Complete!";
?>
