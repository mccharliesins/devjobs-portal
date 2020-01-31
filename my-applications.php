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
    $_SESSION['error'] = 'please login to view your applications.';
    header('Location: login.php');
    exit;
}

// fetch user's applications
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
    $applications = [];
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <h1>My Applications</h1>

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
                            <h2><?php echo htmlspecialchars($application['title']); ?></h2>
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
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 