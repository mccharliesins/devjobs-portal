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
    $_SESSION['error'] = 'please login to access the recruiter dashboard.';
    header('Location: login.php');
    exit;
}

// check if user is a recruiter
try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'recruiter') {
        $_SESSION['error'] = 'you do not have permission to access this page.';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// fetch recruiter's jobs
try {
    $stmt = $conn->prepare("
        SELECT j.*, 
            (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) as application_count 
        FROM jobs j 
        WHERE j.recruiter_id = ? 
        ORDER BY j.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch job listings.';
    $jobs = [];
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>Recruiter Dashboard</h1>
            <a href="post-job.php" class="btn btn-primary">Post New Job</a>
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
                <div class="stat-value"><?php echo count($jobs); ?></div>
                <div class="stat-label">Active Jobs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                        $total_applications = 0;
                        foreach ($jobs as $job) {
                            $total_applications += $job['application_count'];
                        }
                        echo $total_applications;
                    ?>
                </div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="job-listings">
                <h2>Your Job Listings</h2>
                
                <?php if (empty($jobs)): ?>
                    <div class="no-jobs">
                        <p>You haven't posted any jobs yet.</p>
                        <a href="post-job.php" class="btn btn-primary">Post Your First Job</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="jobs-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Posted</th>
                                    <th>Applications</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                                        <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                        <td>
                                            <a href="view-applications.php?job_id=<?php echo $job['id']; ?>">
                                                <?php echo $job['application_count']; ?> applications
                                            </a>
                                        </td>
                                        <td class="actions">
                                            <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                                            <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-secondary btn-sm">View</a>
                                            <form action="delete-job.php" method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this job?');">
                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 