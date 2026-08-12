<?php
// team.php
session_start();
require 'db.php';

$user_id = $_SESSION['user_id'] ?? null;

// Fetch Team Members
$stmt = $pdo->query("
    SELECT t.role_title, t.about_role, t.role_photo_url, u.username, u.bio, u.avatar_url 
    FROM team_members t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.display_order ASC, t.id ASC
");
$team = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet Our Team - Vibeesta</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    
    <nav class="navbar navbar-expand-lg glass-nav fixed-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="index.php">
                <span style="color: var(--accent);">Vibe</span>esta
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fa-solid fa-bars text-dynamic"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="team.php">Meet Our Team</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm border-0"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                    <?php if ($user_id): ?>
                        <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-4">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light btn-sm rounded-pill px-4">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="pt-5 mt-5 text-center fade-in-up">
        <div class="container py-5">
            <span class="badge rounded-pill mb-3 px-3 py-2" style="background: rgba(192, 132, 252, 0.1); color: var(--accent); border: 1px solid var(--accent);">
                Vibeesta Governance
            </span>
            <h1 class="display-3 fw-bold mb-3 text-gradient">Meet Our Team</h1>
            <p class="lead text-muted mx-auto" style="max-width: 600px;">
                The visionaries, organizers, and faculty behind the unified campus club experience.
            </p>
        </div>
    </header>

    <!-- Team Grid -->
    <section class="py-5 fade-in-up">
        <div class="container">
            <?php if (empty($team)): ?>
                <div class="glass-card p-5 text-center text-muted">
                    <i class="fa-solid fa-users-slash fs-1 mb-3"></i>
                    <p class="mb-0">The leadership roster has not been populated yet.</p>
                </div>
            <?php else: ?>
                <div class="row g-5 justify-content-center">
                    <?php foreach ($team as $member): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="glass-card text-center p-5 h-100 theme-card" style="--club-color: #fbbf24;">
                                <div class="theme-glow"></div>
                                <div class="position-relative z-1">
                                    <div class="avatar mx-auto mb-4 bg-dynamic text-dynamic shadow-lg" style="width: 120px; height: 120px; font-size: 3rem; border: 3px solid var(--border-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                        <?php if ($member['role_photo_url']): ?>
                                            <img src="<?php echo htmlspecialchars($member['role_photo_url']); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($member['username'], 0, 2)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="fw-bold text-dynamic mb-1"><?php echo htmlspecialchars($member['username']); ?></h3>
                                    <h6 class="mb-4" style="color: #fbbf24; text-transform: uppercase; letter-spacing: 2px; font-size: 0.8rem;"><?php echo htmlspecialchars($member['role_title']); ?></h6>
                                    
                                    <p class="text-muted small">
                                        <?php echo htmlspecialchars($member['about_role'] ?? $member['bio'] ?? 'Guiding the Vibeesta community towards excellence.'); ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-center gap-3 mt-4">
                                        <a href="#" class="text-muted text-hover-white"><i class="fa-brands fa-linkedin fs-5"></i></a>
                                        <a href="#" class="text-muted text-hover-white"><i class="fa-solid fa-envelope fs-5"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-5 text-center border-top" style="border-color: var(--border-color) !important;">
        <div class="container text-muted small">
            <p class="mb-0">&copy; 2026 Vibeesta Campus Portal. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.add('light-mode');
        }

        // Observer
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        });
        document.querySelectorAll('.fade-in-up').forEach(el => observer.observe(el));
    </script>
</body>
</html>
