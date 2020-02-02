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
    $_SESSION['error'] = 'please login to view applications.';
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

// get job_id from url
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($job_id <= 0) {
    $_SESSION['error'] = 'invalid job id.';
    header('Location: recruiter-dashboard.php');
    exit;
}

// check if job belongs to this recruiter
try {
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $job = $stmt->fetch();

    if (!$job) {
        $_SESSION['error'] = 'job not found or you do not have permission to view its applications.';
        header('Location: recruiter-dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: recruiter-dashboard.php');
    exit;
}

// fetch applications for this job
try {
    $stmt = $conn->prepare("
        SELECT a.*, u.name, u.email
        FROM applications a
        JOIN users u ON a.user_id = u.id
        WHERE a.job_id = ?
        ORDER BY 
            CASE a.status 
                WHEN 'pending' THEN 1 
                WHEN 'accepted' THEN 2 
                WHEN 'rejected' THEN 3 
                WHEN 'withdrawn' THEN 4 
            END,
            a.created_at DESC
    ");
    $stmt->execute([$job_id]);
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
        <div class="dashboard-header">
            <h1>Applications for <?php echo htmlspecialchars($job['title']); ?></h1>
            <a href="recruiter-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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

        <div class="job-summary">
            <h2>Job Summary</h2>
            <div class="job-details-card">
                <div class="job-meta">
                    <span class="company"><?php echo htmlspecialchars($job['company']); ?></span>
                    <span class="location"><?php echo htmlspecialchars($job['location']); ?></span>
                    <span class="job-type"><?php echo htmlspecialchars($job['job_type']); ?></span>
                    <?php if (!empty($job['salary_range'])): ?>
                        <span class="salary"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="job-description-preview">
                    <?php echo nl2br(htmlspecialchars(substr($job['description'], 0, 200))); ?>...
                </div>
                <div class="job-actions">
                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-secondary">View Full Job Details</a>
                </div>
            </div>
        </div>

        <div class="applications-section">
            <h2>Applications (<?php echo count($applications); ?>)</h2>
            
            <?php if (empty($applications)): ?>
                <div class="no-applications">
                    <p>No applications have been submitted for this job yet.</p>
                </div>
            <?php else: ?>
                <div class="applications-tabs">
                    <button class="tab-btn active" data-status="all">All</button>
                    <button class="tab-btn" data-status="pending">Pending</button>
                    <button class="tab-btn" data-status="reviewing">Reviewing</button>
                    <button class="tab-btn" data-status="interview">Interview</button>
                    <button class="tab-btn" data-status="accepted">Accepted</button>
                    <button class="tab-btn" data-status="rejected">Rejected</button>
                    <button class="tab-btn" data-status="withdrawn">Withdrawn</button>
                </div>

                <div class="applications-list">
                    <?php foreach ($applications as $application): ?>
                        <div class="application-card application-status-<?php echo strtolower($application['status']); ?>">
                            <div class="application-header">
                                <h3><?php echo htmlspecialchars($application['name']); ?></h3>
                                <span class="application-status <?php echo strtolower($application['status']); ?>">
                                    <?php echo htmlspecialchars($application['status']); ?>
                                </span>
                            </div>
                            <div class="application-meta">
                                <span class="email"><?php echo htmlspecialchars($application['email']); ?></span>
                                <span class="date">Applied on: <?php echo date('M d, Y', strtotime($application['created_at'])); ?></span>
                            </div>
                            <div class="application-preview">
                                <h4>Cover Letter Preview</h4>
                                <div class="cover-letter-preview">
                                    <?php echo nl2br(htmlspecialchars(substr($application['cover_letter'], 0, 200))); ?>...
                                </div>
                            </div>
                            <div class="application-actions">
                                <a href="application-details-recruiter.php?id=<?php echo $application['id']; ?>" class="btn btn-secondary">View Full Application</a>
                                
                                <?php if ($application['status'] !== 'withdrawn'): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-primary dropdown-toggle">Update Status</button>
                                        <div class="dropdown-content">
                                            <form action="update-application-status.php" method="POST">
                                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                                                
                                                <div class="status-options">
                                                    <label>
                                                        <input type="radio" name="status" value="pending" <?php echo $application['status'] === 'pending' ? 'checked' : ''; ?>>
                                                        Pending
                                                    </label>
                                                    <label>
                                                        <input type="radio" name="status" value="reviewing" <?php echo $application['status'] === 'reviewing' ? 'checked' : ''; ?>>
                                                        Reviewing
                                                    </label>
                                                    <label>
                                                        <input type="radio" name="status" value="interview" <?php echo $application['status'] === 'interview' ? 'checked' : ''; ?>>
                                                        Interview
                                                    </label>
                                                    <label>
                                                        <input type="radio" name="status" value="accepted" <?php echo $application['status'] === 'accepted' ? 'checked' : ''; ?>>
                                                        Accepted
                                                    </label>
                                                    <label>
                                                        <input type="radio" name="status" value="rejected" <?php echo $application['status'] === 'rejected' ? 'checked' : ''; ?>>
                                                        Rejected
                                                    </label>
                                                </div>
                                                
                                                <div class="form-actions">
                                                    <button type="submit" class="btn btn-success">Update Status</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter applications by status
    const tabButtons = document.querySelectorAll('.tab-btn');
    const applicationCards = document.querySelectorAll('.application-card');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Get selected status
            const selectedStatus = this.getAttribute('data-status');
            
            // Filter cards
            applicationCards.forEach(card => {
                if (selectedStatus === 'all' || card.classList.contains('application-status-' + selectedStatus)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php
// include footer
require_once 'includes/footer.php';
?> 