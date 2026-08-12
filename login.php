<?php
// login.php
session_start();
require 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, is_approved_by_admin, is_email_verified FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if admin has approved
            if (!$user['is_approved_by_admin']) {
                $message = "Your account is pending admin approval. You cannot login yet.";
            } else {
                // Check email verification (optional strictly, but good practice)
                // if (!$user['is_email_verified']) {
                //     $message = "Please verify your email address first.";
                // } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: dashboard.php");
                    exit;
                // }
            }
        } else {
            $message = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Vibeesta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #090909; color: #fff; font-family: 'Inter', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container {
            width: 100%;
            max-width: 400px;
            background: rgba(255, 255, 255, 0.05);
            padding: 40px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .form-control { background-color: #1a1a1a; border: 1px solid #333; color: #fff; }
        .form-control:focus { background-color: #222; color: #fff; box-shadow: none; border-color: #7C3AED; }
        .btn-primary { background-color: #7C3AED; border-color: #7C3AED; }
        .btn-primary:hover { background-color: #6D28D9; border-color: #6D28D9; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <h2 style="color: #c084fc; font-weight: 700;">Vibeesta</h2>
            <p class="text-muted small">Campus connection hub</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-warning text-center" style="font-size: 0.9rem;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label text-muted small text-uppercase fw-bold">Username</label>
                <input type="text" name="username" class="form-control form-control-lg" required>
            </div>
            <div class="mb-4">
                <label class="form-label text-muted small text-uppercase fw-bold">Password</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Sign In</button>
            <div class="text-center mt-4">
                <a href="register.php" class="text-decoration-none" style="color: #a78bfa;">Apply for membership</a>
            </div>
        </form>
    </div>
</body>
</html>
