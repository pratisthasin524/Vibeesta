<?php
// register.php
require 'db.php';
require 'mail_helper.php';

$message = '';

// Fetch all clubs to populate the form
$stmt = $pdo->query("SELECT id, name FROM clubs ORDER BY id");
$clubs = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $selected_clubs = $_POST['clubs'] ?? [];

    if (empty($username) || empty($email) || empty($password)) {
        $message = "Please fill in all required fields.";
    } else {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $message = "Username or Email already exists.";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $pdo->beginTransaction();
                
                // Insert User (Note: is_approved_by_admin is 0 by default)
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);
                $user_id = $pdo->lastInsertId();
                
                // Insert Club Memberships (Pending Status)
                if (!empty($selected_clubs)) {
                    $stmt = $pdo->prepare("INSERT INTO club_memberships (user_id, club_id, status) VALUES (?, ?, 'PENDING')");
                    foreach ($selected_clubs as $club_id) {
                        $stmt->execute([$user_id, $club_id]);
                    }
                }
                
                $pdo->commit();
                
                // TODO: Email Verification Logic
                // You mentioned using a "free github api key" for email verification. 
                // Note: GitHub's API does not send emails. If you meant the GitHub Student Developer Pack, 
                // you can use the free SendGrid or Mailgun API keys provided there.
                // Replace the following with your preferred API implementation:
                
                $api_key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIPDj1gX8PJ0g0e/fS/ILq0+YDasue5JhZOO8K7rIz76W pratisthasingh1711@gmail.com';
                $verification_link = "http://localhost/VCS/verify.php?email=" . urlencode($email);
                mail($email, "Verify Your Vibeesta Account", "Click here to verify: " . $verification_link);
                
                
                $message = "Registration successful! Please check your email to verify your account, and wait for Admin approval to login.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Vibeesta</title>
    <!-- Bootstrap CSS for aesthetic UI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #090909; color: #fff; font-family: 'Inter', sans-serif; }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        .form-control { background-color: #1a1a1a; border: 1px solid #333; color: #fff; }
        .form-control:focus { background-color: #222; color: #fff; box-shadow: none; border-color: #7C3AED; }
        .btn-primary { background-color: #7C3AED; border-color: #7C3AED; }
        .btn-primary:hover { background-color: #6D28D9; border-color: #6D28D9; }
        .club-checkboxes { max-height: 200px; overflow-y: auto; padding: 10px; background: #1a1a1a; border-radius: 5px; border: 1px solid #333; }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4 text-primary" style="color: #c084fc !important;">Join Vibeesta</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Select Clubs to Join</label>
                    <div class="club-checkboxes">
                        <?php foreach ($clubs as $club): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="clubs[]" value="<?php echo $club['id']; ?>" id="club_<?php echo $club['id']; ?>">
                                <label class="form-check-label" for="club_<?php echo $club['id']; ?>">
                                    <?php echo htmlspecialchars($club['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">Admin approval required after registration.</small>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Register</button>
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none" style="color: #a78bfa;">Already have an account? Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
