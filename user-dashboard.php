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
    $_SESSION['error'] = 'please login to access your dashboard.';
    header('Location: login.php');
    exit;
}

// check if user is not a recruiter (recruiters have their own dashboard)
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'recruiter') {
    header('Location: recruiter-dashboard.php');
    exit;
}

// get active tab from URL, default to 'applications'
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['applications', 'saved']) ? $_GET['tab'] : 'applications';

// fetch user's applications
$applications = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*, j.title, j.company, j.location, j.job_type 
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $applications = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch applications.';
}

// fetch user's saved jobs
$saved_jobs = [];
try {
    $stmt = $conn->prepare("
        SELECT s.*, j.title, j.company, j.location, j.job_type, j.salary_range, j.created_at, j.description
        FROM saved_jobs s
        JOIN jobs j ON s.job_id = j.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $saved_jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch saved jobs.';
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>My Dashboard</h1>
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
                <div class="stat-value"><?php echo count($applications); ?></div>
                <div class="stat-label">Job Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($saved_jobs); ?></div>
                <div class="stat-label">Saved Jobs</div>
            </div>
        </div>

        <div class="dashboard-tabs">
            <a href="?tab=applications" class="tab-link <?php echo $active_tab === 'applications' ? 'active' : ''; ?>">My Applications</a>
            <a href="?tab=saved" class="tab-link <?php echo $active_tab === 'saved' ? 'active' : ''; ?>">Saved Jobs</a>
        </div>

        <?php if ($active_tab === 'applications'): ?>
            <div class="tab-content" id="applications-tab">
                <h2>My Applications</h2>
                
                <?php if (empty($applications)): ?>
                    <div class="no-applications">
                        <p>You haven't applied for any jobs yet.</p>
                        <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
                    </div>
                <?php else: ?>
                    <div class="applications-list">
                        <?php foreach ($applications as $application): ?>
                            <div class="application-card">
                                <div class="application-header">
                                    <h3><?php echo htmlspecialchars($application['title']); ?></h3>
                                    <span class="application-status <?php echo strtolower($application['status']); ?>">
                                        <?php echo htmlspecialchars($application['status']); ?>
                                    </span>
                                </div>
                                <div class="application-meta">
                                    <span class="company"><?php echo htmlspecialchars($application['company']); ?></span>
                                    <span class="location"><?php echo htmlspecialchars($application['location']); ?></span>
                                    <span class="job-type"><?php echo htmlspecialchars($application['job_type']); ?></span>
                                    <span class="date">Applied on: <?php echo date('M d, Y', strtotime($application['created_at'])); ?></span>
                                </div>
                                <div class="application-actions">
                                    <a href="job-details.php?id=<?php echo $application['job_id']; ?>" class="btn btn-secondary">View Job</a>
                                    <a href="application-details.php?id=<?php echo $application['id']; ?>" class="btn btn-primary">View Application</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="tab-content" id="saved-jobs-tab">
                <h2>Saved Jobs</h2>
                
                <?php if (empty($saved_jobs)): ?>
                    <div class="no-saved-jobs">
                        <p>You haven't saved any jobs yet.</p>
                        <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
                    </div>
                <?php else: ?>
                    <div class="saved-jobs-list">
                        <?php foreach ($saved_jobs as $job): ?>
                            <div class="job-card">
                                <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                                <div class="job-meta">
                                    <span class="company"><?php echo htmlspecialchars($job['company']); ?></span>
                                    <span class="location"><?php echo htmlspecialchars($job['location']); ?></span>
                                    <span class="job-type"><?php echo htmlspecialchars($job['job_type']); ?></span>
                                    <?php if (!empty($job['salary_range'])): ?>
                                        <span class="salary"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php endif; ?>
                                    <span class="date">Saved on: <?php echo date('M d, Y', strtotime($job['created_at'])); ?></span>
                                </div>
                                <div class="job-description">
                                    <?php echo nl2br(htmlspecialchars(substr($job['description'], 0, 150))); ?>...
                                </div>
                                <div class="job-actions">
                                    <a href="job-details.php?id=<?php echo $job['job_id']; ?>" class="btn btn-primary">View Details</a>
                                    <form action="unsave-job.php" method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to remove this job from your saved list?');">
                                        <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                        <button type="submit" class="btn btn-danger">Remove</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 