<?php
// dashboard.php
session_start();
require 'db.php';
require 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_super_admin = hasPermission($pdo, $user_id, 'manage_roles'); // Or check if role_id = 1
$has_manage_users = hasPermission($pdo, $user_id, 'manage_users');
$has_manage_clubs = hasPermission($pdo, $user_id, 'manage_clubs');
$has_post_announcements = hasPermission($pdo, $user_id, 'post_announcements');

// Fetch User Info
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$message = '';

// Handle Delete Club
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_club' && $is_super_admin) {
    $del_club_id = (int)$_POST['delete_club_id'];
    $pdo->prepare("DELETE FROM clubs WHERE id = ?")->execute([$del_club_id]);
    $message = "Club successfully deleted.";
}

// Fetch My Clubs
$my_clubs_stmt = $pdo->prepare("
    SELECT c.*, cm.club_role, cm.status 
    FROM club_memberships cm 
    JOIN clubs c ON cm.club_id = c.id 
    WHERE cm.user_id = ?
");
$my_clubs_stmt->execute([$user_id]);
$my_clubs = $my_clubs_stmt->fetchAll();

// Fetch All Other Clubs
$all_clubs = $pdo->query("SELECT * FROM clubs")->fetchAll();

// Fetch Role Counts for Overview
// We join user_system_roles and roles. For users without a system role, we count them as "Members"
$role_counts = $pdo->query("
    SELECT r.role_name, COUNT(usr.user_id) as count 
    FROM roles r 
    LEFT JOIN user_system_roles usr ON r.id = usr.role_id 
    GROUP BY r.id
")->fetchAll(PDO::FETCH_KEY_PAIR);

$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$users_with_roles = array_sum($role_counts);
$basic_members_count = $total_users - $users_with_roles;

// Handle URL Filters for Users Tab
$active_tab = $_GET['tab'] ?? 'overview';
$role_filter = $_GET['role'] ?? '';

// Fetch Users with Roles
$users_query = "
    SELECT u.*, GROUP_CONCAT(r.role_name SEPARATOR ', ') as assigned_roles 
    FROM users u 
    LEFT JOIN user_system_roles usr ON u.id = usr.user_id 
    LEFT JOIN roles r ON usr.role_id = r.id
";

if ($role_filter === 'Member') {
    // Basic members have no roles
    $users_query .= " WHERE u.id NOT IN (SELECT user_id FROM user_system_roles)";
} elseif ($role_filter) {
    $users_query .= " WHERE r.role_name = " . $pdo->quote($role_filter);
}

$users_query .= " GROUP BY u.id ORDER BY u.username ASC";
$all_users = $pdo->query($users_query)->fetchAll();

// Fetch Admin Specific Data
$all_roles = $pdo->query("SELECT * FROM roles")->fetchAll();
$all_permissions = $pdo->query("SELECT * FROM permissions")->fetchAll();

$role_perms_raw = $pdo->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll();
$role_perms = [];
foreach ($role_perms_raw as $rp) {
    $role_perms[$rp['role_id']][] = $rp['permission_id'];
}

$pending_club_requests = $pdo->query("
    SELECT cm.user_id, cm.club_id, u.username, c.name as club_name, cm.club_role 
    FROM club_memberships cm 
    JOIN users u ON cm.user_id = u.id 
    JOIN clubs c ON cm.club_id = c.id 
    WHERE cm.status = 'PENDING'
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Dashboard - Vibeesta</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="style.css" rel="stylesheet">
    <style>
        .admin-sidebar {
            min-height: calc(100vh - 76px);
            border-right: 1px solid var(--border-color);
        }
        .nav-pills .nav-link {
            color: var(--text-color);
            border-radius: 8px;
            padding: 12px 20px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link:hover {
            background: rgba(124, 58, 237, 0.1);
            color: var(--accent);
        }
        .nav-pills .nav-link.active {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg glass-nav sticky-top py-3">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold fs-4 text-dynamic" href="index.php">
                <span style="color: var(--accent);">Vibe</span>esta
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm border-0"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm border-0"><i class="fa-solid fa-globe me-1"></i> View Public Website</a>
                <span class="badge bg-dynamic border border-secondary text-dynamic py-2 px-3"><i class="fa-solid fa-user me-2"></i><?php echo htmlspecialchars($user['username']); ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-3">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            
            <!-- Dynamic Sidebar -->
            <div class="col-md-3 col-lg-2 p-4 admin-sidebar glass-card border-0 rounded-0">
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    
                    <button class="nav-link text-start <?php echo $active_tab == 'overview' ? 'active' : ''; ?>" onclick="window.location.href='dashboard.php?tab=overview'"><i class="fa-solid fa-chart-pie me-2"></i> Overview</button>
                    
                    <button class="nav-link text-start <?php echo $active_tab == 'my_clubs' ? 'active' : ''; ?>" onclick="window.location.href='dashboard.php?tab=my_clubs'"><i class="fa-solid fa-layer-group me-2"></i> My Clubs</button>
                    
                    <?php if ($has_manage_users): ?>
                        <button class="nav-link text-start <?php echo $active_tab == 'users' ? 'active' : ''; ?>" onclick="window.location.href='dashboard.php?tab=users'"><i class="fa-solid fa-users-gear me-2"></i> User Management</button>
                    <?php endif; ?>

                    <?php if ($is_super_admin): ?>
                        <button class="nav-link text-start <?php echo $active_tab == 'matrix' ? 'active' : ''; ?>" onclick="window.location.href='dashboard.php?tab=matrix'"><i class="fa-solid fa-unlock-keyhole me-2"></i> Role Matrix</button>
                    <?php endif; ?>

                    <?php if ($has_manage_clubs): ?>
                        <button class="nav-link text-start <?php echo $active_tab == 'clubs' ? 'active' : ''; ?>" onclick="window.location.href='dashboard.php?tab=clubs'"><i class="fa-solid fa-building-flag me-2"></i> Club Approvals</button>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Content Area -->
            <div class="col-md-9 col-lg-10 p-5">
                
                <?php if ($message): ?>
                    <div class="alert alert-success border-0 bg-success text-white mb-4">
                        <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="tab-content" id="v-pills-tabContent">
                    
                    <!-- OVERVIEW TAB -->
                    <div class="tab-pane fade <?php echo $active_tab == 'overview' ? 'show active' : ''; ?>">
                        <h2 class="fw-bold mb-4 text-dynamic">Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h2>
                        
                        <?php if ($is_super_admin): ?>
                            <h5 class="text-muted mb-3">Community Composition</h5>
                            <div class="row g-4 mb-5">
                                <!-- President Card -->
                                <div class="col-md-3">
                                    <a href="dashboard.php?tab=users&role=President" class="text-decoration-none">
                                        <div class="glass-card p-4 text-center h-100 theme-card border-accent" style="--club-color: #fbbf24; cursor: pointer;">
                                            <i class="fa-solid fa-crown fs-1 mb-3" style="color: #fbbf24;"></i>
                                            <h3 class="fw-bold text-dynamic mb-0"><?php echo $role_counts['President'] ?? 0; ?></h3>
                                            <p class="text-muted small text-uppercase mb-0">Presidents</p>
                                        </div>
                                    </a>
                                </div>
                                <!-- Club Leads Card -->
                                <div class="col-md-3">
                                    <a href="dashboard.php?tab=users&role=Club Lead" class="text-decoration-none">
                                        <div class="glass-card p-4 text-center h-100 theme-card" style="--club-color: #10b981; cursor: pointer;">
                                            <i class="fa-solid fa-user-tie fs-1 mb-3" style="color: #10b981;"></i>
                                            <h3 class="fw-bold text-dynamic mb-0"><?php echo $role_counts['Club Lead'] ?? 0; ?></h3>
                                            <p class="text-muted small text-uppercase mb-0">Club Leads</p>
                                        </div>
                                    </a>
                                </div>
                                <!-- Admins Card -->
                                <div class="col-md-3">
                                    <a href="dashboard.php?tab=users&role=Admin" class="text-decoration-none">
                                        <div class="glass-card p-4 text-center h-100 theme-card" style="--club-color: #f43f5e; cursor: pointer;">
                                            <i class="fa-solid fa-shield fs-1 mb-3" style="color: #f43f5e;"></i>
                                            <h3 class="fw-bold text-dynamic mb-0"><?php echo $role_counts['Admin'] ?? 0; ?></h3>
                                            <p class="text-muted small text-uppercase mb-0">Admins</p>
                                        </div>
                                    </a>
                                </div>
                                <!-- Basic Members Card -->
                                <div class="col-md-3">
                                    <a href="dashboard.php?tab=users&role=Member" class="text-decoration-none">
                                        <div class="glass-card p-4 text-center h-100 theme-card" style="--club-color: #3b82f6; cursor: pointer;">
                                            <i class="fa-solid fa-users fs-1 mb-3" style="color: #3b82f6;"></i>
                                            <h3 class="fw-bold text-dynamic mb-0"><?php echo $basic_members_count; ?></h3>
                                            <p class="text-muted small text-uppercase mb-0">Basic Members</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- MY CLUBS TAB -->
                    <div class="tab-pane fade <?php echo $active_tab == 'my_clubs' ? 'show active' : ''; ?>">
                        <div class="row g-5">
                            <div class="col-lg-6">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h3 class="fw-bold text-dynamic m-0">My Clubs</h3>
                                    <span class="badge bg-secondary rounded-pill"><?php echo count($my_clubs); ?></span>
                                </div>
                                
                                <?php if (empty($my_clubs)): ?>
                                    <div class="glass-card p-5 text-center text-muted h-100">
                                        <i class="fa-solid fa-ghost fs-1 mb-3"></i>
                                        <p class="mb-0">You haven't joined any clubs yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex flex-column gap-3">
                                        <?php foreach ($my_clubs as $club): ?>
                                            <div class="glass-card p-4 d-flex justify-content-between align-items-center" style="border-left: 4px solid <?php echo htmlspecialchars($club['theme_color']); ?>;">
                                                <div>
                                                    <h5 class="fw-bold mb-1">
                                                        <a href="club.php?id=<?php echo $club['id']; ?>" class="text-decoration-none" style="color: <?php echo htmlspecialchars($club['theme_color']); ?>;">
                                                            <?php echo htmlspecialchars($club['name']); ?>
                                                        </a>
                                                    </h5>
                                                    <span class="badge bg-secondary rounded-pill me-2">Role: <?php echo htmlspecialchars($club['club_role']); ?></span>
                                                </div>
                                                <div>
                                                    <?php if ($is_super_admin): ?>
                                                        <div class="d-flex gap-2">
                                                            <a href="club.php?id=<?php echo $club['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Manage</a>
                                                        </div>
                                                    <?php elseif ($club['status'] == 'PENDING'): ?>
                                                        <span class="badge bg-warning text-dark rounded-pill">Pending Approval</span>
                                                    <?php else: ?>
                                                        <a href="club.php?id=<?php echo $club['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Enter Portal</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-lg-6">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h3 class="fw-bold text-dynamic m-0">Discover More</h3>
                                    <a href="index.php#clubs" class="text-muted small text-decoration-none">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
                                </div>
                                <div class="row g-3">
                                    <?php foreach ($all_clubs as $c): ?>
                                        <div class="col-md-6">
                                            <div class="glass-card p-4 h-100 theme-card text-center" style="--club-color: <?php echo htmlspecialchars($c['theme_color']); ?>;">
                                                <div class="theme-glow"></div>
                                                <div class="position-relative z-1">
                                                    <h5 class="fw-bold mb-2" style="color: <?php echo htmlspecialchars($c['theme_color']); ?>;">
                                                        <?php echo htmlspecialchars($c['name']); ?>
                                                    </h5>
                                                    <a href="club.php?id=<?php echo $c['id']; ?>" class="btn btn-sm w-100 rounded-pill text-dynamic mt-3" style="border: 1px solid <?php echo htmlspecialchars($c['theme_color']); ?>;">
                                                        <?php echo $is_super_admin ? "Manage" : "View"; ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- USERS TAB -->
                    <?php if ($has_manage_users): ?>
                    <div class="tab-pane fade <?php echo $active_tab == 'users' ? 'show active' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="fw-bold text-dynamic m-0">
                                User Management 
                                <?php if($role_filter): ?>
                                    <span class="badge bg-accent ms-2 fs-6">Filter: <?php echo htmlspecialchars($role_filter); ?></span>
                                    <a href="dashboard.php?tab=users" class="btn btn-sm btn-outline-secondary ms-2"><i class="fa-solid fa-xmark"></i> Clear Filter</a>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="glass-card p-4">
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Roles</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_users as $u): ?>
                                            <tr>
                                                <td><?php echo $u['id']; ?></td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($u['username']); ?></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($u['email']); ?></td>
                                                <td>
                                                    <?php 
                                                        $roles_str = $u['assigned_roles'] ?? 'Member';
                                                        $roles_arr = explode(', ', $roles_str);
                                                        foreach ($roles_arr as $r) {
                                                            echo "<span class='badge bg-secondary me-1'>" . htmlspecialchars($r) . "</span>";
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-light"><i class="fa-solid fa-pen-to-square"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ROLE MATRIX TAB -->
                    <?php if ($is_super_admin): ?>
                    <div class="tab-pane fade <?php echo $active_tab == 'matrix' ? 'show active' : ''; ?>">
                        <h3 class="fw-bold text-dynamic mb-4">Role-Permission Matrix</h3>
                        <div class="glass-card p-4">
                            <p class="text-muted">Matrix configuration goes here (migrated from admin.php)</p>
                            <!-- Keeping this simple for the rewrite to ensure functionality works first -->
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.add('light-mode');
        }
    </script>
</body>
</html>
