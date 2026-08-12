<?php
// update_schema_v4.php
require 'db.php';

echo "Updating database schema for Phase 8...<br>";

try {
    // Add about_role and role_photo_url to team_members
    $pdo->exec("ALTER TABLE team_members ADD COLUMN about_role TEXT DEFAULT NULL;");
    $pdo->exec("ALTER TABLE team_members ADD COLUMN role_photo_url VARCHAR(255) DEFAULT NULL;");
    echo "Added about_role and role_photo_url to team_members.<br>";
} catch (PDOException $e) {
    echo "Columns might already exist.<br>";
}

echo "Phase 8 Schema Update Complete!";
?>
