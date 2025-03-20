<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// check if job id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'job id is missing.';
    header('Location: jobs.php');
    exit;
}

$job_id = (int)$_GET['id'];

// fetch job details with company information
try {
    $stmt = $conn->prepare("
        SELECT j.*, c.name as company_name, c.logo as company_logo, c.id as company_id, 
               c.website as company_website, c.description as company_description,
               c.headquarters, c.industry, c.company_size
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        WHERE j.id = ?
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
    
    if (!$job) {
        $_SESSION['error'] = 'job not found.';
        header('Location: jobs.php');
        exit;
    }
    
    // fetch similar jobs from the same company
    $similar_jobs = [];
    if ($job['company_id']) {
        $stmt = $conn->prepare("
            SELECT id, title, location, job_type, created_at
            FROM jobs 
            WHERE company_id = ? AND id != ?
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$job['company_id'], $job_id]);
        $similar_jobs = $stmt->fetchAll();
    }
    
    // Get categories for this job
    $stmt = $conn->prepare("
        SELECT c.name 
        FROM categories c
        JOIN job_categories jc ON c.id = jc.category_id
        WHERE jc.job_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$job_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get tags for this job
    $stmt = $conn->prepare("
        SELECT t.name
        FROM tags t
        JOIN job_tags jt ON t.id = jt.tag_id
        WHERE jt.job_id = ?
        ORDER BY t.name
    ");
    $stmt->execute([$job_id]);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Increment view count
    $stmt = $conn->prepare("UPDATE jobs SET views = views + 1 WHERE id = ?");
    $stmt->execute([$job_id]);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch job details: ' . $e->getMessage();
    header('Location: jobs.php');
    exit;
}

// check if user has already applied for this job
$has_applied = false;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("
            SELECT id FROM applications 
            WHERE job_id = ? AND user_id = ?
        ");
        $stmt->execute([$job_id, $_SESSION['user_id']]);
        $has_applied = (bool)$stmt->fetch();
    } catch (PDOException $e) {
        // silently fail, we'll just assume they haven't applied
    }
}

// check if job is saved by user
$is_saved = false;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("
            SELECT id FROM saved_jobs 
            WHERE job_id = ? AND user_id = ?
        ");
        $stmt->execute([$job_id, $_SESSION['user_id']]);
        $is_saved = (bool)$stmt->fetch();
    } catch (PDOException $e) {
        // silently fail
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="job-details">
            <div class="job-header">
                <div class="job-company-info">
                    <div class="company-logo">
                        <?php if (!empty($job['company_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> logo">
                        <?php else: ?>
                            <div class="logo-placeholder"><?php echo substr(htmlspecialchars($job['company_name'] ?? 'N/A'), 0, 1); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="job-title-company">
                        <h1><?php echo htmlspecialchars($job['title']); ?></h1>
                        <div class="company-name">
                            <?php if (!empty($job['company_id'])): ?>
                                <a href="company-profile.php?id=<?php echo $job['company_id']; ?>">
                                    <?php echo htmlspecialchars($job['company_name']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($job['company_name'] ?? 'N/A'); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="job-meta">
                    <div class="job-meta-item">
                        <i class="icon-location"></i>
                        <span><?php echo htmlspecialchars($job['location']); ?></span>
                    </div>
                    
                    <div class="job-meta-item">
                        <i class="icon-briefcase"></i>
                        <span><?php echo htmlspecialchars($job['job_type']); ?></span>
                    </div>
                    
                    <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                        <div class="job-meta-item">
                            <i class="icon-money"></i>
                            <span>
                                <?php
                                    if (!empty($job['salary_min']) && !empty($job['salary_max'])) {
                                        echo '$' . number_format($job['salary_min']) . ' - $' . number_format($job['salary_max']);
                                    } elseif (!empty($job['salary_min'])) {
                                        echo 'From $' . number_format($job['salary_min']);
                                    } elseif (!empty($job['salary_max'])) {
                                        echo 'Up to $' . number_format($job['salary_max']);
                                    }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="job-meta-item">
                        <i class="icon-calendar"></i>
                        <span>Posted <?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($categories)): ?>
                <div class="job-categories">
                    <h3>Categories:</h3>
                    <div class="category-tags">
                        <?php foreach ($categories as $category): ?>
                            <span class="category-tag"><?php echo htmlspecialchars($category); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($tags)): ?>
                <div class="job-tags">
                    <h3>Skills & Technologies:</h3>
                    <div class="skill-tags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="skill-tag"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="job-actions">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user'): ?>
                        <?php if ($has_applied): ?>
                            <div class="btn btn-success disabled">
                                <i class="icon-check"></i> Applied
                            </div>
                        <?php else: ?>
                            <a href="apply.php?job_id=<?php echo $job_id; ?>" class="btn btn-primary">
                                Apply Now
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($is_saved): ?>
                            <form action="unsave-job.php" method="POST" class="inline-form">
                                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                                <button type="submit" class="btn btn-outline">
                                    <i class="icon-bookmark-filled"></i> Saved
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="save-job.php" method="POST" class="inline-form">
                                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                                <button type="submit" class="btn btn-outline">
                                    <i class="icon-bookmark"></i> Save Job
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php?redirect=job-details.php?id=<?php echo $job_id; ?>" class="btn btn-primary">
                            Login to Apply
                        </a>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'recruiter' && $_SESSION['user_id'] === $job['user_id']): ?>
                        <a href="edit-job.php?id=<?php echo $job_id; ?>" class="btn btn-secondary">
                            Edit Job
                        </a>
                        <a href="view-applications.php?job_id=<?php echo $job_id; ?>" class="btn btn-secondary">
                            View Applications
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="job-content">
                <div class="job-section">
                    <h2>Job Description</h2>
                    <div class="job-description">
                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($job['requirements'])): ?>
                    <div class="job-section">
                        <h2>Requirements</h2>
                        <div class="job-requirements">
                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($job['company_id'])): ?>
                    <div class="job-section">
                        <h2>About the Company</h2>
                        <div class="company-info-card">
                            <div class="company-header">
                                <div class="company-logo">
                                    <?php if (!empty($job['company_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> logo">
                                    <?php else: ?>
                                        <div class="logo-placeholder"><?php echo substr(htmlspecialchars($job['company_name']), 0, 1); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="company-profile-info">
                                    <h3 class="company-profile-name">
                                        <a href="company-profile.php?id=<?php echo $job['company_id']; ?>">
                                            <?php echo htmlspecialchars($job['company_name']); ?>
                                        </a>
                                    </h3>
                                    <div class="company-profile-details">
                                        <?php if (!empty($job['industry'])): ?>
                                            <div class="company-profile-detail">
                                                <i class="icon-tag"></i>
                                                <span><?php echo htmlspecialchars($job['industry']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($job['headquarters'])): ?>
                                            <div class="company-profile-detail">
                                                <i class="icon-building"></i>
                                                <span><?php echo htmlspecialchars($job['headquarters']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($job['company_size'])): ?>
                                            <div class="company-profile-detail">
                                                <i class="icon-users"></i>
                                                <span><?php echo htmlspecialchars($job['company_size']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($job['company_website'])): ?>
                                    <div class="company-actions">
                                        <a href="<?php echo htmlspecialchars($job['company_website']); ?>" target="_blank" class="btn btn-outline">Visit Website</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($job['company_description'])): ?>
                                <div class="company-about">
                                    <?php 
                                        $desc = $job['company_description'];
                                        echo nl2br(htmlspecialchars(substr($desc, 0, 300))) . (strlen($desc) > 300 ? '...' : '');
                                    ?>
                                    <a href="company-profile.php?id=<?php echo $job['company_id']; ?>" class="read-more">Read more</a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($similar_jobs)): ?>
                                <div class="similar-jobs">
                                    <h4>More jobs at <?php echo htmlspecialchars($job['company_name']); ?></h4>
                                    <ul class="similar-jobs-list">
                                        <?php foreach ($similar_jobs as $similar_job): ?>
                                            <li>
                                                <a href="job-details.php?id=<?php echo $similar_job['id']; ?>">
                                                    <?php echo htmlspecialchars($similar_job['title']); ?>
                                                </a>
                                                <span class="job-meta">
                                                    <?php echo htmlspecialchars($similar_job['location']); ?> · <?php echo htmlspecialchars($similar_job['job_type']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="job-footer">
                <a href="jobs.php" class="btn btn-secondary">Back to Jobs</a>
                
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user' && !$has_applied): ?>
                    <a href="apply.php?job_id=<?php echo $job_id; ?>" class="btn btn-primary">Apply Now</a>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php?redirect=job-details.php?id=<?php echo $job_id; ?>" class="btn btn-primary">Login to Apply</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 