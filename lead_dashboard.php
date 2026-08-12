<?php
// lead_dashboard.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Find clubs where the user is a LEAD or CO-LEAD
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.description, c.theme_color, cm.club_role 
    FROM club_memberships cm
    JOIN clubs c ON cm.club_id = c.id
    WHERE cm.user_id = ? AND cm.status = 'APPROVED' AND cm.club_role IN ('LEAD', 'CO-LEAD')
");
$stmt->execute([$user_id]);
$lead_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($lead_clubs)) {
    die("Access Denied: You are not a Lead or Co-Lead of any club.");
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $club_id = (int)($_POST['club_id'] ?? 0);
    
    // Verify user is actually a lead of the submitted club
    $is_valid_lead = false;
    foreach ($lead_clubs as $lc) {
        if ($lc['id'] == $club_id) {
            $is_valid_lead = true;
            break;
        }
    }
    
    if ($is_valid_lead) {
        if ($action == 'approve_member') {
            $target_uid = (int)$_POST['target_user_id'];
            $pdo->prepare("UPDATE club_memberships SET status = 'APPROVED' WHERE user_id = ? AND club_id = ?")->execute([$target_uid, $club_id]);
            $message = "Member approved!";
        } elseif ($action == 'reject_member') {
            $target_uid = (int)$_POST['target_user_id'];
            $pdo->prepare("UPDATE club_memberships SET status = 'REJECTED' WHERE user_id = ? AND club_id = ?")->execute([$target_uid, $club_id]);
            $message = "Member rejected.";
        } elseif ($action == 'update_club') {
            $desc = $_POST['description'];
            $theme = $_POST['theme_color'];
            $pdo->prepare("UPDATE clubs SET description = ?, theme_color = ? WHERE id = ?")->execute([$desc, $theme, $club_id]);
            $message = "Club details updated successfully!";
            // Refresh lead_clubs data
            foreach ($lead_clubs as &$lc) {
                if ($lc['id'] == $club_id) {
                    $lc['description'] = $desc;
                    $lc['theme_color'] = $theme;
                }
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
    <title>Lead Dashboard - Vibeesta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #090909; color: #fff; font-family: 'Inter', sans-serif; }
        .navbar { background: rgba(9, 9, 9, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,0.05); }
        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 15px; }
        .form-control, .form-control:focus { background-color: #1a1a1a; border-color: #333; color: #fff; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="index.php">
                <span style="color: #c084fc;">Vibe</span>esta
            </a>
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-sm btn-outline-light me-3">My Dashboard</a>
                <span class="navbar-text me-3 text-dynamic">Lead: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h2 class="mb-4 text-warning">Club Management Panel</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php foreach ($lead_clubs as $club): ?>
            <?php
            $cid = $club['id'];
            // Fetch pending requests for this club
            $pending_stmt = $pdo->prepare("
                SELECT u.id, u.username, cm.created_at
                FROM club_memberships cm
                JOIN users u ON cm.user_id = u.id
                WHERE cm.club_id = ? AND cm.status = 'PENDING'
            ");
            $pending_stmt->execute([$cid]);
            $pending_reqs = $pending_stmt->fetchAll();
            ?>
            <div class="card mb-5" style="border-top: 4px solid <?php echo htmlspecialchars($club['theme_color']); ?>;">
                <div class="card-body p-4">
                    <h3 class="mb-3" style="color: <?php echo htmlspecialchars($club['theme_color']); ?>;"><?php echo htmlspecialchars($club['name']); ?></h3>
                    
                    <div class="row mt-4">
                        <!-- Pending Requests -->
                        <div class="col-md-7 mb-4">
                            <h5>Pending Join Requests</h5>
                            <?php if (empty($pending_reqs)): ?>
                                <p class="text-muted small">No pending requests.</p>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($pending_reqs as $req): ?>
                                        <li class="list-group-item bg-transparent text-dynamic border-secondary d-flex justify-content-between align-items-center">
                                            <span><?php echo htmlspecialchars($req['username']); ?></span>
                                            <div>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="approve_member">
                                                    <input type="hidden" name="club_id" value="<?php echo $cid; ?>">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="reject_member">
                                                    <input type="hidden" name="club_id" value="<?php echo $cid; ?>">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $req['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <!-- Update Club Details -->
                        <div class="col-md-5">
                            <h5>Update Club Theme & Info</h5>
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="update_club">
                                <input type="hidden" name="club_id" value="<?php echo $cid; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Description</label>
                                    <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($club['description']); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Theme Color (Hex)</label>
                                    <div class="d-flex align-items-center">
                                        <input type="color" name="theme_color" class="form-control form-control-color bg-transparent border-0 p-0 me-2" value="<?php echo htmlspecialchars($club['theme_color']); ?>" title="Choose your color" required>
                                        <span class="small text-muted">Used for your club's dedicated page</span>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-light w-100" style="background-color: <?php echo htmlspecialchars($club['theme_color']); ?>; border-color: <?php echo htmlspecialchars($club['theme_color']); ?>; color: #fff;">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
</body>
</html>
