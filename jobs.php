<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // ensure page is at least 1
$per_page = 10;
$offset = ($page - 1) * $per_page;

// handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// handle filters
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$job_type = isset($_GET['job_type']) ? trim($_GET['job_type']) : '';

try {
    // build query
    $params = [];
    $where_conditions = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(j.title LIKE ? OR j.description LIKE ? OR c.name LIKE ?)";
        $search_param = "%" . $search . "%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($location)) {
        $where_conditions[] = "j.location LIKE ?";
        $params[] = "%" . $location . "%";
    }
    
    if (!empty($job_type)) {
        $where_conditions[] = "j.job_type = ?";
        $params[] = $job_type;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // count total jobs
    $count_sql = "
        SELECT COUNT(j.id) as total 
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        $where_clause
    ";
    
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute($params);
    $row = $count_stmt->fetch();
    $total_jobs = $row['total'];
    $total_pages = ceil($total_jobs / $per_page);
    
    // fetch jobs with company info
    $sql = "
        SELECT j.*, c.name as company_name, c.logo as company_logo, c.id as company_id
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        $where_clause
        ORDER BY j.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
    
    // fetch unique locations for filter
    $locations_stmt = $conn->query("SELECT DISTINCT location FROM jobs ORDER BY location");
    $locations = $locations_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // fetch unique job types for filter
    $job_types_stmt = $conn->query("SELECT DISTINCT job_type FROM jobs ORDER BY job_type");
    $job_types = $job_types_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    $jobs = [];
    $total_pages = 0;
    $total_jobs = 0;
    $locations = [];
    $job_types = [];
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1>Browse Jobs</h1>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'recruiter'): ?>
                <a href="create-job.php" class="btn btn-primary">Post a Job</a>
            <?php endif; ?>
        </div>
        
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
        
        <div class="search-filter-container">
            <form method="GET" class="job-search-form">
                <div class="search-row">
                    <div class="search-input">
                        <input type="text" name="search" placeholder="Search jobs, companies, or keywords" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="location">Location</label>
                        <select name="location" id="location">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $loc === $location ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="job_type">Job Type</label>
                        <select name="job_type" id="job_type">
                            <option value="">All Types</option>
                            <?php foreach ($job_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $type === $job_type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($search) || !empty($location) || !empty($job_type)): ?>
                        <a href="jobs.php" class="btn btn-secondary">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="job-results">
            <p class="result-count">
                <?php echo $total_jobs; ?> job<?php echo $total_jobs !== 1 ? 's' : ''; ?> found
                <?php if (!empty($search) || !empty($location) || !empty($job_type)): ?>
                    with the selected filters
                <?php endif; ?>
            </p>
            
            <?php if (!empty($jobs)): ?>
                <div class="job-list">
                    <?php foreach ($jobs as $job): ?>
                        <div class="job-card">
                            <div class="job-card-header">
                                <div class="company-logo">
                                    <?php if (!empty($job['company_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?> logo">
                                    <?php else: ?>
                                        <div class="logo-placeholder"><?php echo substr(htmlspecialchars($job['company_name'] ?? 'N/A'), 0, 1); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="job-info">
                                    <h2 class="job-title">
                                        <a href="job-details.php?id=<?php echo $job['id']; ?>">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </a>
                                    </h2>
                                    <div class="company-name">
                                        <?php if (!empty($job['company_id'])): ?>
                                            <a href="company-profile.php?id=<?php echo $job['company_id']; ?>">
                                                <?php echo htmlspecialchars($job['company_name'] ?? 'N/A'); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($job['company_name'] ?? 'N/A'); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="job-card-content">
                                <div class="job-details">
                                    <span class="job-location">
                                        <i class="icon-location"></i> <?php echo htmlspecialchars($job['location']); ?>
                                    </span>
                                    <span class="job-type">
                                        <i class="icon-briefcase"></i> <?php echo htmlspecialchars($job['job_type']); ?>
                                    </span>
                                    <?php if (!empty($job['salary_min']) || !empty($job['salary_max'])): ?>
                                        <span class="job-salary">
                                            <i class="icon-money"></i>
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
                                    <?php endif; ?>
                                </div>
                                
                                <div class="job-description-preview">
                                    <?php 
                                        // show a short preview of the description
                                        $desc = strip_tags($job['description']);
                                        echo htmlspecialchars(substr($desc, 0, 150)) . (strlen($desc) > 150 ? '...' : '');
                                    ?>
                                </div>
                                
                                <div class="job-card-actions">
                                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline">View Details</a>
                                    
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <form action="save-job.php" method="POST" class="inline-form">
                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                            <button type="submit" class="btn btn-icon" title="Save Job">
                                                <i class="icon-bookmark"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="job-card-footer">
                                <span class="post-date">
                                    Posted <?php echo timeAgo($job['created_at']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&job_type=<?php echo urlencode($job_type); ?>" class="pagination-prev">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&job_type=<?php echo urlencode($job_type); ?>" 
                                   class="pagination-number <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&job_type=<?php echo urlencode($job_type); ?>" class="pagination-next">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-results">
                    <div class="no-results-icon">
                        <i class="icon-search"></i>
                    </div>
                    <h2>No jobs found</h2>
                    <p>Try adjusting your search or filters to find what you're looking for.</p>
                    <?php if (!empty($search) || !empty($location) || !empty($job_type)): ?>
                        <a href="jobs.php" class="btn btn-primary">Clear all filters</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
// Helper function to format time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

// include footer
require_once 'includes/footer.php';
?> 