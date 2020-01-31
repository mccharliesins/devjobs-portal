<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// get job id from url
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// fetch job details
try {
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        $_SESSION['error'] = 'job not found.';
        header('Location: jobs.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch job details.';
    header('Location: jobs.php');
    exit;
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="job-details">
            <div class="job-header">
                <h1><?php echo htmlspecialchars($job['title']); ?></h1>
                <div class="job-meta">
                    <span class="company"><?php echo htmlspecialchars($job['company']); ?></span>
                    <span class="location"><?php echo htmlspecialchars($job['location']); ?></span>
                    <span class="job-type"><?php echo htmlspecialchars($job['job_type']); ?></span>
                    <?php if (!empty($job['salary_range'])): ?>
                        <span class="salary"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="job-content">
                <div class="job-section">
                    <h2>Job Description</h2>
                    <div class="description">
                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                    </div>
                </div>

                <?php if (!empty($job['requirements'])): ?>
                    <div class="job-section">
                        <h2>Requirements</h2>
                        <div class="requirements">
                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="job-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="apply.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">Apply Now</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">Login to Apply</a>
                    <?php endif; ?>
                    <a href="jobs.php" class="btn btn-secondary">Back to Jobs</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 