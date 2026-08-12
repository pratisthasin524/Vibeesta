<?php
// admin.php
session_start();
require 'db.php';
require 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$is_super_admin = hasPermission($pdo, $user_id, 'manage_users');

if (!$is_super_admin && !hasPermission($pdo, $user_id, 'manage_clubs') && !hasPermission($pdo, $user_id, 'post_announcements')) {
    die("Access Denied: You do not have admin permissions.");
}

$message = '';

// Form Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Approve User
    if ($_POST['action'] == 'approve_user' && $is_super_admin) {
        $u_id = (int)$_POST['target_user_id'];
        $pdo->prepare("UPDATE users SET is_approved_by_admin = 1 WHERE id = ?")->execute([$u_id]);
        $message = "User approved successfully!";
    }

    // Assign Role (Global)
    if ($_POST['action'] == 'assign_role' && $is_super_admin) {
        $u_id = (int)$_POST['target_user_id'];
        $role_id = (int)$_POST['role_id'];
        $pdo->prepare("INSERT INTO user_system_roles (user_id, role_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE role_id = ?")->execute([$u_id, $role_id, $role_id]);
        $message = "System role assigned!";
    }

    // Approve Club Member
    if ($_POST['action'] == 'approve_club_member' && hasPermission($pdo, $user_id, 'manage_clubs')) {
        $cm_user_id = (int)$_POST['req_user_id'];
        $cm_club_id = (int)$_POST['req_club_id'];
        $pdo->prepare("UPDATE club_memberships SET status = 'APPROVED' WHERE user_id = ? AND club_id = ?")->execute([$cm_user_id, $cm_club_id]);
        $message = "Club membership approved!";
    }

    // Add/Update Team Member
    if ($_POST['action'] == 'add_team_member' && $is_super_admin) {
        $team_user_id = (int)$_POST['team_user_id'];
        $role_title = $_POST['role_title'];
        $about_role = $_POST['about_role'];
        $role_photo_url = null;
        
        if (isset($_FILES['role_photo']) && $_FILES['role_photo']['error'] == 0) {
            $filename = time() . '_' . basename($_FILES['role_photo']['name']);
            $target = 'uploads/team/' . $filename;
            if (move_uploaded_file($_FILES['role_photo']['tmp_name'], $target)) {
                $role_photo_url = $target;
            }
        }
        
        // Prevent duplicates
        $check = $pdo->prepare("SELECT id FROM team_members WHERE user_id = ?");
        $check->execute([$team_user_id]);
        if ($check->rowCount() > 0) {
            $sql = "UPDATE team_members SET role_title = ?, about_role = ?";
            $params = [$role_title, $about_role];
            if ($role_photo_url) {
                $sql .= ", role_photo_url = ?";
                $params[] = $role_photo_url;
            }
            $sql .= " WHERE user_id = ?";
            $params[] = $team_user_id;
            $pdo->prepare($sql)->execute($params);
            $message = "Team Member updated!";
        } else {
            $pdo->prepare("INSERT INTO team_members (user_id, role_title, about_role, role_photo_url) VALUES (?, ?, ?, ?)")->execute([$team_user_id, $role_title, $about_role, $role_photo_url]);
            $message = "Team Member added!";
        }
    }

    // Update Role Permissions
    if ($_POST['action'] == 'update_matrix' && $is_super_admin) {
        $pdo->exec("DELETE FROM role_permissions"); // Clear old
        if (isset($_POST['perms']) && is_array($_POST['perms'])) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($_POST['perms'] as $role_id => $perm_ids) {
                foreach ($perm_ids as $perm_id) {
                    $stmt->execute([$role_id, $perm_id]);
                }
            }
        }
        $message = "Role Permissions Matrix updated successfully!";
    }

    // Post Announcement
    if ($_POST['action'] == 'post_announcement' && hasPermission($pdo, $user_id, 'post_announcements')) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($title) {
            $pdo->prepare("INSERT INTO announcements (user_id, title, content) VALUES (?, ?, ?)")->execute([$user_id, $title, $content]);
            $message = "Announcement broadcasted!";
        }
    }

    // Upload Gallery
    if ($_POST['action'] == 'upload_gallery' && hasPermission($pdo, $user_id, 'post_announcements')) {
        $title = trim($_POST['title']);
        if (isset($_FILES['gallery_image']) && $_FILES['gallery_image']['error'] == 0) {
            $filename = time() . '_' . basename($_FILES['gallery_image']['name']);
            $target = 'uploads/gallery/' . $filename;
            if (move_uploaded_file($_FILES['gallery_image']['tmp_name'], $target)) {
                $pdo->prepare("INSERT INTO gallery_images (image_path, title) VALUES (?, ?)")->execute([$target, $title]);
                $message = "Image uploaded to Gallery!";
            }
        }
    }
}

// Fetch Data for Tables
$unapproved_users = $pdo->query("SELECT * FROM users WHERE is_approved_by_admin = 0")->fetchAll();
$all_users = $pdo->query("SELECT * FROM users ORDER BY username ASC")->fetchAll();
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

$team_members_list = $pdo->query("
    SELECT t.*, u.username FROM team_members t JOIN users u ON t.user_id = u.id ORDER BY t.role_title ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Command Center - Vibeesta</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="style.css" rel="stylesheet">
    <style>
        .admin-sidebar {
            min-height: 100vh;
            border-right: 1px solid var(--border-color);
            background: var(--bg-surface);
            padding: 20px;
        }
        .nav-pills .nav-link {
            color: var(--text-muted);
            border-radius: 12px;
            margin-bottom: 8px;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link:hover {
            background: rgba(192,132,252,0.1);
            color: var(--text-main);
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--accent), var(--accent-secondary));
            color: #fff;
            box-shadow: 0 4px 15px rgba(192,132,252,0.3);
        }
        .table-dark {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-main);
            --bs-table-border-color: var(--border-color);
        }
    </style>
</head>
<body>
    <div class="mesh-bg">
        <div class="mesh-orb mesh-orb-1"></div>
        <div class="mesh-orb mesh-orb-2"></div>
    </div>

    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 admin-sidebar position-fixed">
                <a class="navbar-brand fw-bold fs-4 d-block mb-4 text-center text-dynamic" href="index.php">
                    <span style="color: var(--accent);">Vibe</span>esta <span style="font-size: 0.8rem; color: #f43f5e;">ADMIN</span>
                </a>
                
                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                    <button class="nav-link text-start active" id="v-pills-home-tab" data-bs-toggle="pill" data-bs-target="#v-pills-home" type="button" role="tab"><i class="fa-solid fa-gauge me-2"></i> Dashboard</button>
                    
                    <?php if ($is_super_admin): ?>
                        <button class="nav-link text-start" id="v-pills-users-tab" data-bs-toggle="pill" data-bs-target="#v-pills-users" type="button" role="tab"><i class="fa-solid fa-users-gear me-2"></i> Users</button>
                        <button class="nav-link text-start" id="v-pills-team-tab" data-bs-toggle="pill" data-bs-target="#v-pills-team" type="button" role="tab"><i class="fa-solid fa-sitemap me-2"></i> Team Showcase</button>
                        <button class="nav-link text-start" id="v-pills-matrix-tab" data-bs-toggle="pill" data-bs-target="#v-pills-matrix" type="button" role="tab"><i class="fa-solid fa-unlock-keyhole me-2"></i> Role Matrix</button>
                    <?php endif; ?>

                    <?php if (hasPermission($pdo, $user_id, 'manage_clubs')): ?>
                        <button class="nav-link text-start" id="v-pills-clubs-tab" data-bs-toggle="pill" data-bs-target="#v-pills-clubs" type="button" role="tab"><i class="fa-solid fa-layer-group me-2"></i> Clubs & Approvals</button>
                    <?php endif; ?>

                    <?php if (hasPermission($pdo, $user_id, 'post_announcements')): ?>
                        <button class="nav-link text-start" id="v-pills-broadcast-tab" data-bs-toggle="pill" data-bs-target="#v-pills-broadcast" type="button" role="tab"><i class="fa-solid fa-tower-broadcast me-2"></i> Broadcast & Media</button>
                    <?php endif; ?>
                </div>

                <div class="mt-auto pt-4 border-top" style="border-color: var(--border-color) !important;">
                    <a href="dashboard.php" class="btn btn-outline-secondary w-100 mb-2"><i class="fa-solid fa-arrow-left me-2"></i> User Dashboard</a>
                    <a href="logout.php" class="btn btn-danger w-100"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 offset-md-3 offset-lg-2 p-4 p-lg-5">
                
                <?php if ($message): ?>
                    <div class="alert alert-success glass-card border-0 alert-dismissible fade show mb-4" role="alert" style="background: rgba(22, 163, 74, 0.1); color: #4ade80;">
                        <i class="fa-solid fa-check-circle me-2"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="tab-content" id="v-pills-tabContent">
                    
                    <!-- HOME TAB -->
                    <div class="tab-pane fade show active" id="v-pills-home" role="tabpanel">
                        <h2 class="fw-bold mb-4 text-dynamic">Command Center</h2>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="glass-card p-4 text-center">
                                    <i class="fa-solid fa-users fs-1 mb-3 text-info"></i>
                                    <h3 class="fw-bold text-dynamic"><?php echo count($all_users); ?></h3>
                                    <p class="text-muted mb-0">Total Users</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="glass-card p-4 text-center">
                                    <i class="fa-solid fa-user-clock fs-1 mb-3 text-warning"></i>
                                    <h3 class="fw-bold text-dynamic"><?php echo count($unapproved_users); ?></h3>
                                    <p class="text-muted mb-0">Pending Approvals</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="glass-card p-4 text-center">
                                    <i class="fa-solid fa-layer-group fs-1 mb-3 text-success"></i>
                                    <h3 class="fw-bold text-dynamic"><?php echo count($pending_club_requests); ?></h3>
                                    <p class="text-muted mb-0">Pending Club Requests</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- USERS TAB -->
                    <?php if ($is_super_admin): ?>
                    <div class="tab-pane fade" id="v-pills-users" role="tabpanel">
                        <h3 class="fw-bold mb-4 text-dynamic">User Management</h3>
                        <div class="glass-card p-4">
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_users as $u): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                                <td>
                                                    <?php if ($u['is_approved_by_admin']): ?>
                                                        <span class="badge bg-success rounded-pill">Approved</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark rounded-pill">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$u['is_approved_by_admin']): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to approve this user?');">
                                                            <input type="hidden" name="action" value="approve_user">
                                                            <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Assign Role Modal Trigger -->
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal<?php echo $u['id']; ?>">
                                                        Role
                                                    </button>

                                                    <!-- Role Modal -->
                                                    <div class="modal fade" id="roleModal<?php echo $u['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content glass-card">
                                                                <div class="modal-header border-secondary">
                                                                    <h5 class="modal-title text-dynamic">Assign Role to <?php echo htmlspecialchars($u['username']); ?></h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="action" value="assign_role">
                                                                        <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                                        <select name="role_id" class="form-select bg-dynamic text-dynamic border-secondary" required>
                                                                            <?php foreach ($all_roles as $r): ?>
                                                                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="modal-footer border-secondary">
                                                                        <button type="submit" class="btn btn-primary">Save changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TEAM SHOWCASE TAB -->
                    <div class="tab-pane fade" id="v-pills-team" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="fw-bold text-dynamic mb-0">Team Showcase</h3>
                            <button class="btn btn-vibe" data-bs-toggle="modal" data-bs-target="#addTeamModal"><i class="fa-solid fa-plus me-2"></i>Add Member</button>
                        </div>
                        
                        <!-- Add Team Modal -->
                        <div class="modal fade" id="addTeamModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content glass-card">
                                    <div class="modal-header border-secondary">
                                        <h5 class="modal-title text-dynamic">Add to Team Showcase</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="add_team_member">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">User</label>
                                                <select name="team_user_id" class="form-select bg-dynamic text-dynamic border-secondary" required>
                                                    <option value="" disabled selected>Select User...</option>
                                                    <?php foreach ($all_users as $u): ?>
                                                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Governance Title</label>
                                                <select name="role_title" class="form-select bg-dynamic text-dynamic border-secondary" required>
                                                    <option value="President">President</option>
                                                    <option value="Vice President">Vice President</option>
                                                    <option value="Secretary">Secretary</option>
                                                    <option value="Organizer">Organizer</option>
                                                    <option value="Executive">Executive</option>
                                                    <option value="Faculty Coordinator">Faculty Coordinator</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted">About Role (Optional)</label>
                                                <textarea name="about_role" class="form-control bg-dynamic text-dynamic border-secondary" rows="2" placeholder="Responsibilities..."></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Role Headshot Photo (Optional)</label>
                                                <input type="file" name="role_photo" class="form-control bg-dynamic text-dynamic border-secondary" accept="image/*">
                                                <small class="text-muted">Will override global avatar on Team page.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-secondary">
                                            <button type="submit" class="btn btn-primary">Save Member</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card p-4">
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Username</th>
                                            <th>Photo</th>
                                            <th>About</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($team_members_list)): ?>
                                            <tr><td colspan="4" class="text-center text-muted">No team members added.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($team_members_list as $tm): ?>
                                                <tr>
                                                    <td><span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($tm['role_title']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($tm['username']); ?></td>
                                                    <td>
                                                        <?php if ($tm['role_photo_url']): ?>
                                                            <img src="<?php echo htmlspecialchars($tm['role_photo_url']); ?>" alt="Photo" width="40" height="40" class="rounded-circle" style="object-fit: cover;">
                                                        <?php else: ?>
                                                            <span class="text-muted small">Global Avatar</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="small text-muted" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                        <?php echo htmlspecialchars($tm['about_role']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- MATRIX TAB -->
                    <?php if ($is_super_admin): ?>
                    <div class="tab-pane fade" id="v-pills-matrix" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="fw-bold text-dynamic mb-0">Role-Permission Matrix</h3>
                            <p class="text-muted small mb-0">Check the boxes to assign global permissions to specific roles.</p>
                        </div>
                        
                        <div class="glass-card p-4">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to update the global permissions? This will affect all users.');">
                                <input type="hidden" name="action" value="update_matrix">
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover align-middle table-bordered text-center">
                                        <thead>
                                            <tr>
                                                <th class="text-start">Role</th>
                                                <?php foreach ($all_permissions as $p): ?>
                                                    <th>
                                                        <span style="writing-mode: vertical-rl; transform: rotate(180deg);" class="pb-2">
                                                            <?php echo htmlspecialchars($p['permission_name']); ?>
                                                        </span>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_roles as $r): ?>
                                                <tr>
                                                    <td class="text-start fw-bold"><?php echo htmlspecialchars($r['role_name']); ?></td>
                                                    <?php foreach ($all_permissions as $p): ?>
                                                        <?php 
                                                            $is_checked = isset($role_perms[$r['id']]) && in_array($p['id'], $role_perms[$r['id']]); 
                                                        ?>
                                                        <td>
                                                            <input class="form-check-input bg-dynamic border-secondary" type="checkbox" name="perms[<?php echo $r['id']; ?>][]" value="<?php echo $p['id']; ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-3">
                                    <button type="submit" class="btn btn-primary rounded-pill px-5">Save Matrix</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- CLUBS TAB -->
                    <?php if (hasPermission($pdo, $user_id, 'manage_clubs')): ?>
                    <div class="tab-pane fade" id="v-pills-clubs" role="tabpanel">
                        <h3 class="fw-bold mb-4 text-dynamic">Pending Club Requests</h3>
                        <div class="glass-card p-4">
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Club</th>
                                            <th>Requested Role</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($pending_club_requests)): ?>
                                            <tr><td colspan="4" class="text-center text-muted">No pending requests.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($pending_club_requests as $req): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($req['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($req['club_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($req['club_role']); ?></td>
                                                    <td>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="approve_club_member">
                                                            <input type="hidden" name="req_user_id" value="<?php echo $req['user_id']; ?>">
                                                            <input type="hidden" name="req_club_id" value="<?php echo $req['club_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- BROADCAST & MEDIA TAB -->
                    <?php if (hasPermission($pdo, $user_id, 'post_announcements')): ?>
                    <div class="tab-pane fade" id="v-pills-broadcast" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="glass-card p-4 h-100">
                                    <h3 class="fw-bold mb-4 text-dynamic"><i class="fa-solid fa-bullhorn text-warning me-2"></i> Broadcast Announcement</h3>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="post_announcement">
                                        <div class="mb-3">
                                            <input type="text" name="title" class="form-control bg-dynamic text-dynamic border-secondary" placeholder="Announcement Title" required>
                                        </div>
                                        <div class="mb-4">
                                            <textarea name="content" class="form-control bg-dynamic text-dynamic border-secondary" rows="4" placeholder="Message to entire campus..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn w-100 rounded-pill btn-warning text-dark fw-bold">Post to Campus Feed</button>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="glass-card p-4 h-100">
                                    <h3 class="fw-bold mb-4 text-dynamic"><i class="fa-solid fa-images text-danger me-2"></i> Upload to BTS Gallery</h3>
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="upload_gallery">
                                        <div class="mb-3">
                                            <label class="form-label text-muted">Image Title</label>
                                            <input type="text" name="title" class="form-control bg-dynamic text-dynamic border-secondary" required>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label text-muted">Select Image</label>
                                            <input type="file" name="gallery_image" class="form-control bg-dynamic text-dynamic border-secondary" accept="image/*" required>
                                        </div>
                                        <button type="submit" class="btn w-100 rounded-pill btn-danger fw-bold">Upload to Homepage</button>
                                    </form>
                                </div>
                            </div>
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
