<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'please login to access the admin dashboard.';
    header('Location: login.php');
    exit;
}

// check if user is an admin
try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        $_SESSION['error'] = 'you do not have permission to access this page.';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// fetch stats
try {
    // get total users
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $total_users = $stmt->fetch()['count'];
    
    // get total recruiters
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'recruiter'");
    $stmt->execute();
    $total_recruiters = $stmt->fetch()['count'];
    
    // get total job seekers
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $stmt->execute();
    $total_job_seekers = $stmt->fetch()['count'];
    
    // get total jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs");
    $stmt->execute();
    $total_jobs = $stmt->fetch()['count'];
    
    // get total applications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications");
    $stmt->execute();
    $total_applications = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch stats: ' . $e->getMessage();
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <p><?php echo htmlspecialchars($_SESSION['success']); ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <p><?php echo htmlspecialchars($_SESSION['error']); ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo isset($total_users) ? $total_users : 0; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo isset($total_recruiters) ? $total_recruiters : 0; ?></div>
                <div class="stat-label">Recruiters</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo isset($total_job_seekers) ? $total_job_seekers : 0; ?></div>
                <div class="stat-label">Job Seekers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo isset($total_jobs) ? $total_jobs : 0; ?></div>
                <div class="stat-label">Jobs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo isset($total_applications) ? $total_applications : 0; ?></div>
                <div class="stat-label">Applications</div>
            </div>
        </div>

        <div class="admin-actions">
            <h2>Admin Actions</h2>
            <div class="action-buttons">
                <a href="email-logs.php" class="btn btn-primary">View Email Logs</a>
                <a href="manage-users.php" class="btn btn-primary">Manage Users</a>
                <a href="manage-jobs.php" class="btn btn-primary">Manage Jobs</a>
                <a href="system-settings.php" class="btn btn-primary">System Settings</a>
            </div>
        </div>

        <div class="recent-activities">
            <h2>Recent Activities</h2>
            <p>This section will display recent activities in the system.</p>
            <!-- Placeholder for actual activity data -->
            <div class="activity-placeholder">
                <p>Activity logging feature coming soon.</p>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 