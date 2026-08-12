<?php
require 'db.php';

try {
    // Change club_role from ENUM to VARCHAR
    $pdo->exec("ALTER TABLE club_memberships MODIFY COLUMN club_role VARCHAR(100) DEFAULT 'Member'");
    echo "Schema v5 update complete! club_role is now a dynamic VARCHAR.\n";
} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
?>
