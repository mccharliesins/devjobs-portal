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

// get application id from url
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($application_id <= 0) {
    $_SESSION['error'] = 'invalid application id.';
    header('Location: recruiter-dashboard.php');
    exit;
}

// fetch application details, ensure job belongs to the recruiter
try {
    $stmt = $conn->prepare("
        SELECT a.*, j.title as job_title, j.company, j.location, j.job_type, j.id as job_id, u.name, u.email
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND j.recruiter_id = ?
    ");
    $stmt->execute([$application_id, $_SESSION['user_id']]);
    $application = $stmt->fetch();

    if (!$application) {
        $_SESSION['error'] = 'application not found or you do not have permission to view it.';
        header('Location: recruiter-dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: recruiter-dashboard.php');
    exit;
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="application-details-recruiter">
            <div class="dashboard-header">
                <h1>Application Details</h1>
                <a href="view-applications.php?job_id=<?php echo $application['job_id']; ?>" class="btn btn-secondary">Back to Applications</a>
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

            <div class="application-status-bar">
                <span class="application-status <?php echo strtolower($application['status']); ?>">
                    <?php echo htmlspecialchars($application['status']); ?>
                </span>
                
                <?php if ($application['status'] === 'pending'): ?>
                    <div class="status-actions">
                        <form action="update-application-status.php" method="POST" class="inline-form">
                            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                            <input type="hidden" name="job_id" value="<?php echo $application['job_id']; ?>">
                            <input type="hidden" name="status" value="accepted">
                            <button type="submit" class="btn btn-success">Accept Application</button>
                        </form>
                        <form action="update-application-status.php" method="POST" class="inline-form">
                            <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                            <input type="hidden" name="job_id" value="<?php echo $application['job_id']; ?>">
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="btn btn-danger">Reject Application</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="application-section">
                <h2>Job Information</h2>
                <div class="job-details-card">
                    <h3><?php echo htmlspecialchars($application['job_title']); ?></h3>
                    <div class="job-meta">
                        <span class="company"><?php echo htmlspecialchars($application['company']); ?></span>
                        <span class="location"><?php echo htmlspecialchars($application['location']); ?></span>
                        <span class="job-type"><?php echo htmlspecialchars($application['job_type']); ?></span>
                    </div>
                    <div class="job-actions">
                        <a href="job-details.php?id=<?php echo $application['job_id']; ?>" class="btn btn-secondary">View Full Job Details</a>
                    </div>
                </div>
            </div>

            <div class="application-section">
                <h2>Applicant Information</h2>
                <div class="applicant-info">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($application['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?></p>
                    <p><strong>Applied on:</strong> <?php echo date('M d, Y', strtotime($application['created_at'])); ?></p>
                    <?php if (!empty($application['updated_at'])): ?>
                        <p><strong>Last updated:</strong> <?php echo date('M d, Y', strtotime($application['updated_at'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="application-section">
                <h2>Resume</h2>
                <?php if (!empty($application['resume'])): ?>
                    <div class="resume-section">
                        <a href="<?php echo htmlspecialchars($application['resume']); ?>" class="btn btn-primary" target="_blank">Download Resume</a>
                    </div>
                <?php else: ?>
                    <p>No resume provided.</p>
                <?php endif; ?>
            </div>

            <div class="application-section">
                <h2>Cover Letter</h2>
                <div class="cover-letter-content">
                    <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                </div>
            </div>

            <div class="application-actions">
                <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>" class="btn btn-primary">Contact Applicant</a>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 