<?php
require 'db.php';
$pdo->query('INSERT INTO users (username, email, password_hash, is_approved_by_admin) VALUES ("admin", "admin@example.com", "$2y$10$NPb1VAZs0ydZfWDnDjxtPOBt11YZHN88p86.cniaM.x9q5QLqo72K", 1) ON DUPLICATE KEY UPDATE password_hash = "$2y$10$NPb1VAZs0ydZfWDnDjxtPOBt11YZHN88p86.cniaM.x9q5QLqo72K"');
$pdo->query("INSERT IGNORE INTO user_system_roles (user_id, role_id) VALUES ((SELECT id FROM users WHERE username = 'admin'), 1)");
echo 'Admin Created!';
