<?php
// club.php
session_start();
require 'db.php';
require 'auth_helper.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$club_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'] ?? null;
$message = '';

// Fetch Club Info
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch();
if (!$club) {
    die("Club not found.");
}
$theme_color = $club['theme_color'] ?: '#c084fc';
$bg_image = $club['background_image'] ?: '';

// Handle Image Upload (Admins or Leads)
$is_admin = hasPermission($pdo, $user_id, 'manage_clubs');
$is_lead = false;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT club_role FROM club_memberships WHERE user_id = ? AND club_id = ? AND status = 'APPROVED'");
    $stmt->execute([$user_id, $club_id]);
    $role = $stmt->fetchColumn();
    if ($role === 'LEAD' || $role === 'CO-LEAD') {
        $is_lead = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'upload_bg' && ($is_admin || $is_lead)) {
        if (isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] == 0) {
            $filename = time() . '_' . basename($_FILES['bg_image']['name']);
            $target = 'uploads/backgrounds/' . $filename;
            if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $target)) {
                $pdo->prepare("UPDATE clubs SET background_image = ? WHERE id = ?")->execute([$target, $club_id]);
                $bg_image = $target;
                $message = "Background image updated!";
            }
        }
    }

    // Post to Forum
    if ($_POST['action'] == 'post_topic' && $user_id && ($is_admin || $role)) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($title) {
            $pdo->prepare("INSERT INTO forum_topics (club_id, user_id, title, content) VALUES (?, ?, ?, ?)")->execute([$club_id, $user_id, $title, $content]);
            $message = "Forum topic posted!";
        }
    }

    // Create Event
    if ($_POST['action'] == 'create_event' && ($is_admin || $is_lead)) {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $date = $_POST['event_date'];
        $loc = trim($_POST['location']);
        $pdo->prepare("INSERT INTO events (club_id, name, description, event_date, location) VALUES (?, ?, ?, ?, ?)")->execute([$club_id, $name, $desc, $date, $loc]);
        $message = "Event created successfully!";
    }

    // RSVP Event
    if ($_POST['action'] == 'rsvp_event' && $user_id && ($is_admin || $role)) {
        $event_id = (int)$_POST['event_id'];
        $status = $_POST['rsvp_status']; // GOING, MAYBE, CANT_GO
        $pdo->prepare("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?")->execute([$event_id, $user_id, $status, $status]);
        $message = "RSVP updated!";
    }
}

// Fetch Leads & Members
$leads = $pdo->prepare("SELECT u.username, cm.club_role FROM club_memberships cm JOIN users u ON cm.user_id = u.id WHERE cm.club_id = ? AND cm.status = 'APPROVED' AND cm.club_role IN ('LEAD', 'CO-LEAD')");
$leads->execute([$club_id]);
$club_leads = $leads->fetchAll();

$members = $pdo->prepare("SELECT u.username FROM club_memberships cm JOIN users u ON cm.user_id = u.id WHERE cm.club_id = ? AND cm.status = 'APPROVED' AND cm.club_role = 'MEMBER'");
$members->execute([$club_id]);
$club_members = $members->fetchAll();

// Fetch Forum Topics
$topics = $pdo->prepare("SELECT t.*, u.username FROM forum_topics t JOIN users u ON t.user_id = u.id WHERE t.club_id = ? ORDER BY t.created_at DESC");
$topics->execute([$club_id]);
$forum_topics = $topics->fetchAll();

// Fetch Events
$events_stmt = $pdo->prepare("SELECT * FROM events WHERE club_id = ? ORDER BY event_date ASC");
$events_stmt->execute([$club_id]);
$club_events = $events_stmt->fetchAll();

// Fetch user's RSVPs if logged in
$my_rsvps = [];
if ($user_id) {
    $rsvp_stmt = $pdo->prepare("SELECT event_id, status FROM event_rsvps WHERE user_id = ?");
    $rsvp_stmt->execute([$user_id]);
    foreach ($rsvp_stmt->fetchAll() as $r) {
        $my_rsvps[$r['event_id']] = $r['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($club['name']); ?> - Vibeesta</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="style.css" rel="stylesheet">
    <style>
        .club-hero {
            position: relative;
            padding: 150px 20px 100px;
            overflow: hidden;
            border-bottom: 1px solid var(--border-color);
        }
        .club-hero-bg {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-size: cover;
            background-position: center;
            z-index: -2;
            <?php if ($bg_image): ?>
                background-image: url('<?php echo htmlspecialchars($bg_image); ?>');
            <?php else: ?>
                background: linear-gradient(135deg, <?php echo $theme_color; ?> 0%, var(--bg-main) 100%);
                opacity: 0.2;
            <?php endif; ?>
        }
        .club-hero-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--bg-main);
            opacity: 0.85; /* Transparent type background */
            z-index: -1;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg glass-nav fixed-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="index.php">
                <span style="color: var(--accent);">Vibe</span>esta
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="team.php" class="btn btn-outline-light btn-sm text-muted border-0">Meet Our Team</a>
                <a href="<?php echo $user_id ? 'dashboard.php' : 'index.php'; ?>" class="btn btn-outline-light btn-sm text-muted border-0">&larr; Back</a>
            </div>
        </div>
    </nav>

    <!-- Club Hero -->
    <header class="club-hero text-center text-dynamic">
        <div class="club-hero-bg"></div>
        <div class="club-hero-overlay"></div>
        <div class="container z-1 position-relative fade-in-up">
            <span class="badge rounded-pill mb-3 px-3 py-2" style="background: <?php echo $theme_color; ?>22; color: <?php echo $theme_color; ?>; border: 1px solid <?php echo $theme_color; ?>;">
                Official Club Page
            </span>
            <h1 class="display-3 fw-bold mb-3" style="color: <?php echo $theme_color; ?>; filter: drop-shadow(0 0 20px <?php echo $theme_color; ?>88);">
                <i class="fa-solid fa-users me-2"></i><?php echo htmlspecialchars($club['name']); ?>
            </h1>
            <p class="lead mx-auto" style="max-width: 600px; color: rgba(255,255,255,0.8);">
                <?php echo htmlspecialchars($club['description']); ?>
            </p>

            <?php if ($is_admin || $is_lead): ?>
                <form method="POST" enctype="multipart/form-data" class="d-flex justify-content-center align-items-center gap-2 mt-4 fade-in-up">
                    <input type="hidden" name="action" value="upload_bg">
                    <div class="input-group" style="max-width: 400px; background: var(--bg-surface-solid); border-radius: 30px; padding: 5px; border: 1px solid var(--border-color);">
                        <input type="file" name="bg_image" class="form-control border-0 bg-transparent text-dynamic" accept="image/*" required style="box-shadow: none;">
                        <button type="submit" class="btn btn-vibe rounded-pill px-4">Update Cover</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success glass-card border-0 alert-dismissible fade show mt-4 mx-auto" role="alert" style="max-width: 500px; background: rgba(22, 163, 74, 0.1); color: #4ade80;">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container py-5 my-4 fade-in-up">
        <div class="row g-5">
            <!-- Left Col: Leads & Members -->
            <div class="col-lg-4">
                <div class="glass-card p-4 mb-4" style="border-top: 4px solid <?php echo $theme_color; ?>;">
                    <h4 class="fw-bold mb-4">Leadership</h4>
                    <?php if (empty($club_leads)): ?>
                        <p class="text-muted small mb-0">No leads assigned.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($club_leads as $lead): ?>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar bg-dynamic text-dynamic" style="border: 1px solid <?php echo $theme_color; ?>;">
                                        <?php echo strtoupper(substr($lead['username'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($lead['username']); ?></div>
                                        <small style="color: <?php echo $theme_color; ?>;"><?php echo htmlspecialchars($lead['club_role']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="glass-card p-4">
                    <h4 class="fw-bold mb-4">Members (<?php echo count($club_members); ?>)</h4>
                    <?php if (empty($club_members)): ?>
                        <p class="text-muted small mb-0">No members yet.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($club_members as $mem): ?>
                                <span class="badge bg-transparent border text-dynamic" style="border-color: var(--border-color) !important;">
                                    <?php echo htmlspecialchars($mem['username']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Col: Events & Forums -->
            <div class="col-lg-8">
                
                <!-- EVENTS SECTION -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold"><i class="fa-solid fa-calendar-check me-2 text-gradient"></i> Upcoming Events</h2>
                </div>

                <?php if ($is_admin || $is_lead): ?>
                    <div class="glass-card p-4 mb-5" style="border-left: 4px solid var(--accent);">
                        <h5 class="fw-bold mb-3 text-dynamic"><i class="fa-solid fa-plus-circle me-2" style="color: var(--accent);"></i> Host an Event</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_event">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">Event Name</label>
                                    <input type="text" name="name" class="form-control bg-dynamic text-dynamic border-secondary" placeholder="e.g. Annual Tech Fest" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted mb-1">Date & Time</label>
                                    <input type="datetime-local" name="event_date" class="form-control bg-dynamic text-dynamic border-secondary" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-1">Location</label>
                                <input type="text" name="location" class="form-control bg-dynamic text-dynamic border-secondary" placeholder="e.g. Main Auditorium" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small text-muted mb-1">Description</label>
                                <textarea name="description" class="form-control bg-dynamic text-dynamic border-secondary" rows="2" placeholder="Tell members what to expect..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-vibe rounded-pill w-100">Schedule Event</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (empty($club_events)): ?>
                    <div class="glass-card p-5 text-center text-muted mb-5">
                        <i class="fa-regular fa-calendar-xmark fs-1 mb-3 opacity-50"></i>
                        <p class="mb-0">No upcoming events scheduled at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-4 mb-5">
                        <?php foreach ($club_events as $event): ?>
                            <?php 
                                $rsvp_c_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_rsvps WHERE event_id = ? AND status = 'GOING'");
                                $rsvp_c_stmt->execute([$event['event_id']]);
                                $going_count = $rsvp_c_stmt->fetchColumn();
                                $my_status = $my_rsvps[$event['event_id']] ?? null;
                            ?>
                            <div class="glass-card p-4 position-relative overflow-hidden">
                                <!-- Decorative accent glow -->
                                <div style="position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: var(--accent); filter: blur(40px); opacity: 0.3;"></div>
                                
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                                    <div>
                                        <h4 class="fw-bold text-dynamic mb-1"><?php echo htmlspecialchars($event['name']); ?></h4>
                                        <div class="text-muted small d-flex gap-3 align-items-center">
                                            <span><i class="fa-regular fa-clock me-1 text-primary"></i> <?php echo date('M d, Y - h:i A', strtotime($event['event_date'])); ?></span>
                                            <span><i class="fa-solid fa-location-dot me-1 text-danger"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                                        </div>
                                    </div>
                                    <div class="mt-3 mt-md-0 text-end">
                                        <div class="badge bg-dynamic border border-secondary rounded-pill px-3 py-2 shadow-sm">
                                            <span style="color: var(--accent);" class="fw-bold fs-6"><?php echo $going_count; ?></span> 
                                            <span class="text-muted fw-normal ms-1">Attending</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="text-muted mb-4"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                                
                                <?php if ($user_id && ($is_admin || $role)): ?>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="rsvp_event">
                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                        
                                        <button type="submit" name="rsvp_status" value="GOING" class="btn btn-sm rounded-pill px-4 fw-bold <?php echo $my_status=='GOING'?'btn-success shadow':'btn-outline-success'; ?>">
                                            <?php echo $my_status=='GOING' ? '<i class="fa-solid fa-check me-1"></i> Going' : 'Going'; ?>
                                        </button>
                                        <button type="submit" name="rsvp_status" value="MAYBE" class="btn btn-sm rounded-pill px-4 fw-bold <?php echo $my_status=='MAYBE'?'btn-warning shadow text-dark':'btn-outline-warning'; ?>">
                                            Maybe
                                        </button>
                                        <button type="submit" name="rsvp_status" value="CANT_GO" class="btn btn-sm rounded-pill px-4 fw-bold <?php echo $my_status=='CANT_GO'?'btn-danger shadow':'btn-outline-danger'; ?>">
                                            Can't Go
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>


                <!-- FORUMS SECTION -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold"><i class="fa-solid fa-comments me-2 text-gradient"></i> Community Forum</h2>
                </div>

                <?php if ($user_id && ($is_admin || $role)): ?>
                    <div class="glass-card p-4 mb-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="post_topic">
                            <div class="mb-3">
                                <input type="text" name="title" class="form-control bg-dynamic text-dynamic border-secondary" placeholder="Start a new discussion topic..." required>
                            </div>
                            <div class="mb-3">
                                <textarea name="content" class="form-control bg-dynamic text-dynamic border-secondary" rows="2" placeholder="Details..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-vibe rounded-pill">Post Topic</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (empty($forum_topics)): ?>
                    <div class="glass-card p-5 text-center text-muted">
                        <i class="fa-solid fa-ghost fs-1 mb-3"></i>
                        <p class="mb-0">It's quiet in here. Start a discussion!</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($forum_topics as $topic): ?>
                            <div class="glass-card p-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <h5 class="fw-bold mb-0 text-dynamic"><?php echo htmlspecialchars($topic['title']); ?></h5>
                                    <small class="text-muted"><?php echo date('M d', strtotime($topic['created_at'])); ?></small>
                                </div>
                                <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($topic['content'])); ?></p>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar" style="width: 20px; height: 20px; font-size: 8px;"><?php echo strtoupper(substr($topic['username'], 0, 2)); ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;">Posted by <?php echo htmlspecialchars($topic['username']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
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
