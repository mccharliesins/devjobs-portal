<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// get company id from url
$company_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// fetch company details
try {
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch();

    if (!$company) {
        $_SESSION['error'] = 'company not found.';
        header('Location: companies.php');
        exit;
    }

    // fetch jobs from this company
    $stmt = $conn->prepare("
        SELECT * FROM jobs 
        WHERE company_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$company_id]);
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch company details.';
    header('Location: companies.php');
    exit;
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="company-profile">
            <div class="company-header">
                <div class="company-logo-container">
                    <?php if (!empty($company['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="<?php echo htmlspecialchars($company['name']); ?> logo" class="company-logo">
                    <?php else: ?>
                        <div class="company-logo-placeholder">
                            <?php echo strtoupper(substr($company['name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="company-info">
                    <h1><?php echo htmlspecialchars($company['name']); ?></h1>
                    
                    <?php if (!empty($company['headquarters'])): ?>
                        <div class="company-meta">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($company['headquarters']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="company-meta">
                        <?php if (!empty($company['industry'])): ?>
                            <span><i class="fas fa-industry"></i> <?php echo htmlspecialchars($company['industry']); ?></span>
                        <?php endif; ?>
                        
                        <?php if (!empty($company['company_size'])): ?>
                            <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($company['company_size']); ?> employees</span>
                        <?php endif; ?>
                        
                        <?php if (!empty($company['founded_year'])): ?>
                            <span><i class="fas fa-calendar"></i> Founded <?php echo htmlspecialchars($company['founded_year']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="company-actions">
                        <?php if (!empty($company['website'])): ?>
                            <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" class="btn btn-primary"><i class="fas fa-globe"></i> Visit Website</a>
                        <?php endif; ?>
                        
                        <div class="company-social">
                            <?php if (!empty($company['social_linkedin'])): ?>
                                <a href="<?php echo htmlspecialchars($company['social_linkedin']); ?>" target="_blank" class="social-link linkedin"><i class="fab fa-linkedin"></i></a>
                            <?php endif; ?>
                            
                            <?php if (!empty($company['social_twitter'])): ?>
                                <a href="<?php echo htmlspecialchars($company['social_twitter']); ?>" target="_blank" class="social-link twitter"><i class="fab fa-twitter"></i></a>
                            <?php endif; ?>
                            
                            <?php if (!empty($company['social_facebook'])): ?>
                                <a href="<?php echo htmlspecialchars($company['social_facebook']); ?>" target="_blank" class="social-link facebook"><i class="fab fa-facebook"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="company-about">
                <h2>About <?php echo htmlspecialchars($company['name']); ?></h2>
                <div class="company-description">
                    <?php echo nl2br(htmlspecialchars($company['description'] ?? 'No company description available.')); ?>
                </div>
            </div>
            
            <div class="company-jobs">
                <h2>Open Positions at <?php echo htmlspecialchars($company['name']); ?></h2>
                
                <?php if (empty($jobs)): ?>
                    <div class="no-jobs">
                        <p>No open positions available at this company right now.</p>
                    </div>
                <?php else: ?>
                    <div class="job-grid">
                        <?php foreach ($jobs as $job): ?>
                            <div class="job-card">
                                <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                                
                                <div class="job-meta">
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                    <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($job['job_type']); ?></span>
                                    <?php if (!empty($job['salary_range'])): ?>
                                        <span><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="job-description">
                                    <?php echo htmlspecialchars(substr($job['description'], 0, 150)) . '...'; ?>
                                </div>
                                
                                <div class="job-actions">
                                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">View Details</a>
                                    
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'recruiter'): ?>
                                        <a href="apply.php?id=<?php echo $job['id']; ?>" class="btn btn-secondary">Apply Now</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
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