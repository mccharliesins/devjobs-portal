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

    // fetch related jobs
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_type = ? AND id != ? LIMIT 3");
    $stmt->execute([$job['job_type'], $job_id]);
    $related_jobs = $stmt->fetchAll();
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
                <div class="job-actions">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="apply.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">Apply Now</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary">Login to Apply</a>
                    <?php endif; ?>
                    <a href="jobs.php" class="btn btn-secondary">Back to Jobs</a>
                    <div class="share-buttons">
                        <button onclick="shareJob('facebook')" class="btn btn-facebook">Share on Facebook</button>
                        <button onclick="shareJob('twitter')" class="btn btn-twitter">Share on Twitter</button>
                        <button onclick="shareJob('linkedin')" class="btn btn-linkedin">Share on LinkedIn</button>
                    </div>
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

                <div class="job-section">
                    <h2>About the Company</h2>
                    <div class="company-info">
                        <h3><?php echo htmlspecialchars($job['company']); ?></h3>
                        <p>We are looking for talented individuals to join our team. If you're passionate about technology and want to make a difference, we'd love to hear from you.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($related_jobs)): ?>
            <div class="related-jobs">
                <h2>Related Jobs</h2>
                <div class="job-grid">
                    <?php foreach ($related_jobs as $related_job): ?>
                        <div class="job-card">
                            <h3><?php echo htmlspecialchars($related_job['title']); ?></h3>
                            <div class="job-meta">
                                <span class="company"><?php echo htmlspecialchars($related_job['company']); ?></span>
                                <span class="location"><?php echo htmlspecialchars($related_job['location']); ?></span>
                                <span class="job-type"><?php echo htmlspecialchars($related_job['job_type']); ?></span>
                                <?php if (!empty($related_job['salary_range'])): ?>
                                    <span class="salary"><?php echo htmlspecialchars($related_job['salary_range']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="job-description">
                                <?php echo nl2br(htmlspecialchars(substr($related_job['description'], 0, 150))); ?>...
                            </div>
                            <div class="job-actions">
                                <a href="job-details.php?id=<?php echo $related_job['id']; ?>" class="btn btn-primary">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
function shareJob(platform) {
    const url = encodeURIComponent(window.location.href);
    const title = encodeURIComponent(document.title);
    let shareUrl = '';
    
    switch (platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
            break;
        case 'linkedin':
            shareUrl = `https://www.linkedin.com/shareArticle?mini=true&url=${url}&title=${title}`;
            break;
    }
    
    window.open(shareUrl, '_blank', 'width=600,height=400');
}
</script>

<?php
// include footer
require_once 'includes/footer.php';
?> 