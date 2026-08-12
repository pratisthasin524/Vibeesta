<?php
// index.php
session_start();
require 'db.php';

$logged_in = isset($_SESSION['user_id']);
$username = $logged_in ? ($_SESSION['username'] ?? 'User') : null;
$initials = $logged_in ? strtoupper(substr($username, 0, 2)) : '';

// Fetch Clubs and Members for the 3D Cards
$clubs_stmt = $pdo->query("SELECT * FROM clubs ORDER BY id");
$clubs = $clubs_stmt->fetchAll();

$club_members_stmt = $pdo->query("
    SELECT cm.club_id, u.username, cm.club_role
    FROM club_memberships cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.status = 'APPROVED'
");
$members_raw = $club_members_stmt->fetchAll();
$club_members = [];
foreach ($members_raw as $m) {
    $club_members[$m['club_id']][$m['club_role']][] = $m['username'];
}

// Fetch Team Members for Showcase
$stmt_team = $pdo->query("
    SELECT t.role_title, t.about_role, t.role_photo_url, u.username, u.bio, u.avatar_url 
    FROM team_members t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.display_order ASC, t.id ASC
");
$team = $stmt_team->fetchAll();

// Map Clubs to FontAwesome Icons (Hardcoded for demo aesthetic)
$club_icons = [
    1 => 'fa-solid fa-film',         // Film making
    2 => 'fa-solid fa-music',        // Music
    3 => 'fa-solid fa-briefcase',    // Management
    4 => 'fa-solid fa-camera-retro', // Fashion
    5 => 'fa-solid fa-bullhorn',     // PR
    6 => 'fa-solid fa-pen-nib',      // Design
    7 => 'fa-solid fa-book-open',    // Editorial
    8 => 'fa-solid fa-hashtag',      // Social media
    9 => 'fa-solid fa-palette'       // Arts
];

// Fetch Gallery Images
$gallery = $pdo->query("SELECT * FROM gallery_images ORDER BY created_at DESC LIMIT 6")->fetchAll();
if (empty($gallery)) {
    // Fallback if empty
    $gallery = [
        ['image_path' => 'assets/bts1.jpg', 'title' => 'Hackathons', 'description' => 'Building the future, late into the night.'],
        ['image_path' => 'assets/bts2.jpg', 'title' => 'Project Showcases', 'description' => 'Hardware meets software.']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vibeesta - Discover Your Vibe</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Extreme Custom UI -->
    <link href="style.css" rel="stylesheet">
</head>
<body class="dark-mode">

    <!-- Animated Gradient Mesh -->
    <div class="mesh-bg">
        <div class="mesh-orb mesh-orb-1"></div>
        <div class="mesh-orb mesh-orb-2"></div>
    </div>

    <!-- Premium Navbar -->
    <nav class="navbar navbar-expand-lg glass-nav fixed-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="index.php">
                <i class="fa-solid fa-bolt text-gradient me-2"></i><span class="text-gradient">Vibe</span>esta
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fa-solid fa-bars" style="color: var(--text-main);"></i>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-4">
                    <li class="nav-item"><a class="nav-link" href="#why-us">Why Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#clubs">Clubs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
                    <li class="nav-item"><a class="nav-link" href="#bts">Gallery</a></li>
                    
                    <!-- Theme Toggle -->
                    <li class="nav-item">
                        <button id="theme-toggle" class="btn btn-link nav-link p-0 m-0">
                            <i class="fa-solid fa-moon fs-5"></i>
                        </button>
                    </li>
                    
                    <div class="d-none d-lg-block" style="width:1px; height:24px; background:var(--border-color);"></div>
                    
                    <div class="d-flex align-items-center gap-3">
                    <?php if ($logged_in): ?>
                        <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-4">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light btn-sm rounded-pill px-4">Sign In</a>
                        <a href="register.php" class="btn btn-vibe px-4">Join Us</a>
                    <?php endif; ?>
                    </div>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="text-center" style="padding: 200px 20px 100px;">
        <div class="container fade-in-up">
            <span class="badge rounded-pill mb-4 px-3 py-2" style="background: rgba(192, 132, 252, 0.1); color: var(--accent); border: 1px solid rgba(192, 132, 252, 0.2);">
                <i class="fa-solid fa-rocket me-2"></i> The #1 Campus Hub Platform
            </span>
            <h1 class="display-1 fw-bold mb-4" style="letter-spacing: -0.05em;">
                Discover Your <span class="text-gradient">Vibe.</span>
            </h1>
            <p class="lead mx-auto mb-5" style="max-width: 650px; font-size: 1.3rem; color: var(--text-muted);">
                Step up as a leader, join a thriving community, and showcase your absolute best talent under one beautifully unified platform.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="#clubs" class="btn btn-vibe btn-lg px-5 shadow-lg"><i class="fa-solid fa-compass me-2"></i> Explore Clubs</a>
                <a href="#why-us" class="btn btn-outline-vibe btn-lg px-5 d-none d-sm-inline-block"><i class="fa-solid fa-play me-2"></i> See Why</a>
            </div>
        </div>
    </section>

    <!-- Infinite Scrolling Marquee -->
    <div class="marquee-container mb-5">
        <div class="marquee-content">
            <!-- Repeated text for infinite scroll illusion -->
            <?php for ($i=0; $i<4; $i++): ?>
                <span><i class="fa-solid fa-code"></i> Tech Club</span>
                <span><i class="fa-solid fa-film"></i> Film Making</span>
                <span><i class="fa-solid fa-music"></i> Music</span>
                <span><i class="fa-solid fa-camera"></i> Fashion</span>
                <span><i class="fa-solid fa-pen-nib"></i> Design</span>
                <span><i class="fa-solid fa-bullhorn"></i> PR</span>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Why Clubs Matter Info Section -->
    <section id="why-us" class="py-5 my-5">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 fade-in-up">
                    <h2 class="display-4 fw-bold mb-4">Why Campus Communities <span class="text-gradient">Matter.</span></h2>
                    <p class="fs-5" style="color: var(--text-muted); line-height: 1.6;">
                        College isn't just about lectures and exams. It's about the connections you make and the passions you pursue. 
                        Vibeesta integrates every club on campus into a single, seamless ecosystem. 
                    </p>
                    <ul class="list-unstyled mt-4 fs-5" style="color: var(--text-muted);">
                        <li class="mb-3"><i class="fa-solid fa-check-circle text-gradient me-3"></i> Network with like-minded peers</li>
                        <li class="mb-3"><i class="fa-solid fa-check-circle text-gradient me-3"></i> Build your leadership portfolio</li>
                        <li class="mb-3"><i class="fa-solid fa-check-circle text-gradient me-3"></i> Attend exclusive events and workshops</li>
                    </ul>
                </div>
                <div class="col-lg-6 fade-in-up" style="transition-delay: 0.2s;">
                    <div class="glass-card p-5 text-center" style="border: 2px solid var(--accent); box-shadow: 0 0 40px rgba(192, 132, 252, 0.2);">
                        <i class="fa-solid fa-users text-gradient mb-4" style="font-size: 5rem;"></i>
                        <h3 class="fw-bold mb-3">1,000+ Students Connected</h3>
                        <p class="text-muted">Join the movement and start collaborating today.</p>
                        <?php if (!$logged_in): ?>
                            <a href="register.php" class="btn btn-vibe mt-3 w-100">Create Free Account</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3D Flip Clubs Section -->
    <section id="clubs" class="py-5 mb-5 bg-transparent">
        <div class="container">
            <div class="text-center mb-5 pb-3 fade-in-up">
                <span class="badge rounded-pill mb-3 px-3 py-2" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-secondary); border: 1px solid rgba(59, 130, 246, 0.2);">
                    <i class="fa-solid fa-layer-group me-2"></i> Directory
                </span>
                <h2 class="display-4 fw-bold">Find Your <span class="text-gradient">Passion</span></h2>
                <p class="fs-5 text-muted">Hover over any club card to flip it and reveal the details.</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($clubs as $index => $club): ?>
                    <?php 
                        $cid = $club['id'];
                        $theme = $club['theme_color'] ?: '#c084fc'; 
                        $icon = $club_icons[$cid] ?? 'fa-solid fa-star';
                        $delay = ($index % 3) * 0.1; // Stagger animation
                    ?>
                    <div class="col-lg-4 col-md-6 fade-in-up" style="transition-delay: <?php echo $delay; ?>s;">
                        <div class="flip-card-container" style="--club-color: <?php echo $theme; ?>;">
                            <div class="flip-card">
                                <!-- FRONT -->
                                <div class="flip-card-front">
                                    <i class="<?php echo $icon; ?>"></i>
                                    <h3 class="fw-bold mb-0" style="color: var(--text-main);"><?php echo htmlspecialchars($club['name']); ?></h3>
                                    <p class="mt-3 text-muted small"><i class="fa-solid fa-hand-pointer me-1"></i> Hover to flip</p>
                                </div>
                                
                                <!-- BACK -->
                                <div class="flip-card-back">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3" style="border-color: var(--border-color) !important;">
                                            <h4 class="fw-bold mb-0"><i class="<?php echo $icon; ?> me-2"></i><?php echo htmlspecialchars($club['name']); ?></h4>
                                        </div>
                                        <p class="mb-4">
                                            <?php echo htmlspecialchars($club['description'] ?: 'No description provided yet.'); ?>
                                        </p>
                                        
                                        <!-- Quick Stats -->
                                        <div class="d-flex gap-3 mb-4">
                                            <div class="text-center px-3 py-2 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color);">
                                                <div class="fw-bold fs-5 text-dynamic"><?php echo count($club_members[$cid]['LEAD'] ?? []); ?></div>
                                                <small class="text-muted" style="font-size: 0.7rem; text-transform:uppercase;">Leads</small>
                                            </div>
                                            <div class="text-center px-3 py-2 rounded" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color);">
                                                <div class="fw-bold fs-5 text-dynamic"><?php echo count($club_members[$cid]['MEMBER'] ?? []); ?></div>
                                                <small class="text-muted" style="font-size: 0.7rem; text-transform:uppercase;">Members</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <a href="club.php?id=<?php echo $cid; ?>" class="btn w-100 fw-bold" style="background: var(--club-color); color: #fff;">
                                        View Full Page <i class="fa-solid fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Meet Our Team Section -->
    <section id="team" class="py-5 bg-transparent">
        <div class="container fade-in-up">
            <div class="text-center mb-5 pb-3">
                <span class="badge rounded-pill mb-3 px-3 py-2" style="background: rgba(192, 132, 252, 0.1); color: var(--accent); border: 1px solid var(--accent);">
                    Vibeesta Governance
                </span>
                <h2 class="display-4 fw-bold">Meet Our <span class="text-gradient">Team</span></h2>
                <p class="fs-5 text-muted">The visionaries, organizers, and faculty behind the unified campus club experience.</p>
            </div>
            
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

    <!-- BTS Gallery -->
    <section id="bts" class="py-5 my-5">
        <div class="container fade-in-up">
            <div class="text-center mb-5 pb-3">
                <span class="badge rounded-pill mb-3 px-3 py-2" style="background: rgba(244, 63, 94, 0.1); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.2);">
                    <i class="fa-solid fa-camera me-2"></i> Gallery
                </span>
                <h2 class="display-4 fw-bold">Behind The <span class="text-gradient">Scenes</span></h2>
                <p class="fs-5 text-muted">A glimpse into our vibrant community in action</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($gallery as $g): ?>
                <div class="col-md-6">
                    <div class="glass-card overflow-hidden" style="border-radius: 20px; position: relative;">
                        <img src="<?php echo htmlspecialchars($g['image_path']); ?>" alt="Gallery Image" class="img-fluid w-100" style="transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275); height: 350px; object-fit: cover;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(0deg, rgba(0,0,0,0.9), transparent); padding: 40px 20px 20px;">
                            <h4 class="text-dynamic fw-bold mb-1"><i class="fa-solid fa-camera text-gradient me-2"></i> <?php echo htmlspecialchars($g['title']); ?></h4>
                            <p class="text-light opacity-75 small mb-0"><?php echo htmlspecialchars($g['description'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center py-5 border-top" style="border-color: var(--border-color) !important; background: var(--bg-surface);">
        <div class="container fade-in-up">
            <span class="fw-bold fs-3"><i class="fa-solid fa-bolt text-gradient me-2"></i><span class="text-gradient">Vibe</span>esta</span>
            <p class="text-muted mt-3 mb-0">&copy; 2026 Vibeesta Campus Platform. Developed with precision.</p>
            <div class="d-flex justify-content-center gap-4 mt-4">
                <a href="#" class="text-muted"><i class="fa-brands fa-github fs-4"></i></a>
                <a href="#" class="text-muted"><i class="fa-brands fa-discord fs-4"></i></a>
                <a href="#" class="text-muted"><i class="fa-brands fa-twitter fs-4"></i></a>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Extreme UI Scripts -->
    <script>
        // 1. Light/Dark Mode Toggle
        const themeToggle = document.getElementById('theme-toggle');
        const body = document.body;
        const icon = themeToggle.querySelector('i');
        
        // Check local storage for preference
        if (localStorage.getItem('theme') === 'light') {
            body.classList.add('light-mode');
            icon.classList.replace('fa-moon', 'fa-sun');
        }
        
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('light-mode');
            if (body.classList.contains('light-mode')) {
                localStorage.setItem('theme', 'light');
                icon.classList.replace('fa-moon', 'fa-sun');
            } else {
                localStorage.setItem('theme', 'dark');
                icon.classList.replace('fa-sun', 'fa-moon');
            }
        });

        // 2. Intersection Observer for Scroll Fade-in Animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: "0px 0px -50px 0px"
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.fade-in-up').forEach(el => observer.observe(el));
    </script>
</body>
</html>
