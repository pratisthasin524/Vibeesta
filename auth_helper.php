<?php
// auth_helper.php

/**
 * Checks if a user has a specific permission dynamically based on their roles.
 *
 * @param PDO $pdo The PDO database connection
 * @param int $user_id The user's ID
 * @param string $permission_name The name of the permission to check (e.g., 'manage_users')
 * @return bool True if the user has the permission, false otherwise
 */
function hasPermission($pdo, $user_id, $permission_name) {
    if (!$user_id) return false;
    
    $stmt = $pdo->prepare("
        SELECT p.permission_name
        FROM user_system_roles usr
        JOIN role_permissions rp ON usr.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE usr.user_id = ? AND p.permission_name = ?
    ");
    $stmt->execute([$user_id, $permission_name]);
    
    return $stmt->rowCount() > 0;
}
?>
