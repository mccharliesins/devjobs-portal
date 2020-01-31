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
    $_SESSION['error'] = 'please login to view application details.';
    header('Location: login.php');
    exit;
}

// get application id from url
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// fetch application details
try {
    $stmt = $conn->prepare("
        SELECT a.*, j.title, j.company, j.location, j.job_type, j.description, j.requirements
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$application_id, $_SESSION['user_id']]);
    $application = $stmt->fetch();

    if (!$application) {
        $_SESSION['error'] = 'application not found or you do not have permission to view it.';
        header('Location: my-applications.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch application details.';
    header('Location: my-applications.php');
    exit;
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="application-details">
            <div class="application-header">
                <h1>Application Details</h1>
                <span class="application-status <?php echo strtolower($application['status']); ?>">
                    <?php echo htmlspecialchars($application['status']); ?>
                </span>
            </div>

            <div class="application-section">
                <h2>Job Information</h2>
                <div class="job-details-summary">
                    <h3><?php echo htmlspecialchars($application['title']); ?></h3>
                    <div class="job-meta">
                        <span class="company"><?php echo htmlspecialchars($application['company']); ?></span>
                        <span class="location"><?php echo htmlspecialchars($application['location']); ?></span>
                        <span class="job-type"><?php echo htmlspecialchars($application['job_type']); ?></span>
                    </div>
                    <div class="job-description-preview">
                        <?php echo nl2br(htmlspecialchars(substr($application['description'], 0, 200))); ?>...
                    </div>
                    <div class="job-actions">
                        <a href="job-details.php?id=<?php echo $application['job_id']; ?>" class="btn btn-secondary">View Full Job Details</a>
                    </div>
                </div>
            </div>

            <div class="application-section">
                <h2>Your Application</h2>
                <div class="application-meta">
                    <p><strong>Applied on:</strong> <?php echo date('M d, Y', strtotime($application['created_at'])); ?></p>
                    <?php if (!empty($application['updated_at'])): ?>
                        <p><strong>Last updated:</strong> <?php echo date('M d, Y', strtotime($application['updated_at'])); ?></p>
                    <?php endif; ?>
                </div>

                <div class="application-content">
                    <div class="resume-section">
                        <h3>Resume/CV</h3>
                        <?php if (!empty($application['resume'])): ?>
                            <div class="resume-preview">
                                <p>Your resume has been uploaded.</p>
                                <a href="<?php echo htmlspecialchars($application['resume']); ?>" class="btn btn-secondary" target="_blank">View Resume</a>
                            </div>
                        <?php else: ?>
                            <p>No resume provided.</p>
                        <?php endif; ?>
                    </div>

                    <div class="cover-letter-section">
                        <h3>Cover Letter</h3>
                        <div class="cover-letter-content">
                            <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($application['status'] === 'pending'): ?>
                <div class="application-actions">
                    <form action="withdraw-application.php" method="POST" onsubmit="return confirm('Are you sure you want to withdraw this application?');">
                        <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                        <button type="submit" class="btn btn-danger">Withdraw Application</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="back-link">
                <a href="my-applications.php">← Back to My Applications</a>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 