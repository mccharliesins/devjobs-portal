<?php
// include header and database connection
require_once 'includes/header.php';
require_once 'includes/db.php';

// set page title
$page_title = 'Browse Jobs';

// define search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$job_type = isset($_GET['job_type']) ? trim($_GET['job_type']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$tag = isset($_GET['tag']) ? intval($_GET['tag']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// fetch total number of jobs matching the filters
try {
    $params = [];
    $where_clauses = ["j.status = 'active'"];
    
    if (!empty($search)) {
        $where_clauses[] = "(j.title LIKE ? OR j.company LIKE ? OR j.description LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($location)) {
        $where_clauses[] = "j.location LIKE ?";
        $params[] = '%' . $location . '%';
    }
    
    if (!empty($job_type)) {
        $where_clauses[] = "j.job_type = ?";
        $params[] = $job_type;
    }
    
    $join_clauses = [];
    
    if (!empty($category)) {
        $join_clauses[] = "JOIN job_categories jc ON j.id = jc.job_id";
        $where_clauses[] = "jc.category_id = ?";
        $params[] = $category;
    }
    
    if (!empty($tag)) {
        $join_clauses[] = "JOIN job_tags jt ON j.id = jt.job_id";
        $where_clauses[] = "jt.tag_id = ?";
        $params[] = $tag;
    }
    
    $join_sql = implode(' ', $join_clauses);
    $where_sql = implode(' AND ', $where_clauses);
    
    $sql = "SELECT COUNT(DISTINCT j.id) as total FROM jobs j $join_sql WHERE $where_sql";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $total_jobs = $stmt->fetchColumn();
    
    // calculate total pages
    $total_pages = ceil($total_jobs / $per_page);
    
    // ensure current page is valid
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    
    // fetch jobs with pagination
    $sort_clause = match($sort) {
        'salary_high' => 'j.salary_range DESC',
        'salary_low' => 'j.salary_range ASC',
        'oldest' => 'j.created_at ASC',
        default => 'j.created_at DESC' // newest first by default
    };
    
    $sql = "
        SELECT DISTINCT j.*, 
            (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
             FROM categories c 
             JOIN job_categories jc ON c.id = jc.category_id 
             WHERE jc.job_id = j.id) AS categories,
            (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') 
             FROM tags t 
             JOIN job_tags jt ON t.id = jt.tag_id 
             WHERE jt.job_id = j.id) AS tags
        FROM jobs j 
        $join_sql
        WHERE $where_sql
        ORDER BY $sort_clause
        LIMIT ?, ?
    ";
    
    $params[] = $offset;
    $params[] = $per_page;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // fetch categories for filter
    $stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // fetch tags for filter
    $stmt = $conn->prepare("SELECT id, name FROM tags ORDER BY name");
    $stmt->execute();
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    $total_jobs = 0;
    $total_pages = 0;
    $jobs = [];
    $categories = [];
    $tags = [];
}
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1>Browse Jobs</h1>
            <p class="lead">Find your next career opportunity</p>
        </div>
        
        <div class="search-filters">
            <form action="jobs.php" method="GET" class="filters-form">
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Search jobs by title, company or keywords" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                
                <div class="advanced-filters">
                    <div class="filter-group">
                        <label for="job_type">Job Type</label>
                        <select name="job_type" id="job_type">
                            <option value="">All Types</option>
                            <option value="full-time" <?php echo $job_type === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                            <option value="part-time" <?php echo $job_type === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                            <option value="contract" <?php echo $job_type === 'contract' ? 'selected' : ''; ?>>Contract</option>
                            <option value="internship" <?php echo $job_type === 'internship' ? 'selected' : ''; ?>>Internship</option>
                            <option value="remote" <?php echo $job_type === 'remote' ? 'selected' : ''; ?>>Remote</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="category">Category</label>
                        <select name="category" id="category">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="tag">Tag</label>
                        <select name="tag" id="tag">
                            <option value="0">All Tags</option>
                            <?php foreach ($tags as $t): ?>
                                <option value="<?php echo $t['id']; ?>" <?php echo $tag == $t['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort">Sort By</label>
                        <select name="sort" id="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="salary_high" <?php echo $sort === 'salary_high' ? 'selected' : ''; ?>>Highest Salary</option>
                            <option value="salary_low" <?php echo $sort === 'salary_low' ? 'selected' : ''; ?>>Lowest Salary</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">Apply Filters</button>
                    <a href="jobs.php" class="btn btn-link">Clear Filters</a>
                </div>
            </form>
        </div>
        
        <div class="job-results">
            <div class="results-header">
                <p class="results-count">Found <?php echo $total_jobs; ?> job<?php echo $total_jobs !== 1 ? 's' : ''; ?></p>
            </div>
            
            <?php if (empty($jobs)): ?>
                <div class="no-results">
                    <p>No jobs found matching your criteria.</p>
                    <p>Try adjusting your search filters or <a href="jobs.php">view all jobs</a>.</p>
                </div>
            <?php else: ?>
                <div class="job-list">
                    <?php foreach ($jobs as $job): ?>
                        <div class="job-card">
                            <div class="job-card-header">
                                <h2 class="job-title">
                                    <a href="job-details.php?id=<?php echo $job['id']; ?>">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </a>
                                </h2>
                                <span class="job-company"><?php echo htmlspecialchars($job['company']); ?></span>
                            </div>
                            
                            <div class="job-card-body">
                                <div class="job-meta">
                                    <span class="job-location"><?php echo htmlspecialchars($job['location']); ?></span>
                                    <span class="job-type"><?php echo htmlspecialchars($job['job_type']); ?></span>
                                    <?php if (!empty($job['salary_range'])): ?>
                                        <span class="job-salary"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($job['categories'])): ?>
                                <div class="job-categories">
                                    <div class="category-tags">
                                        <?php foreach (explode(', ', $job['categories']) as $cat): ?>
                                            <span class="category-tag"><?php echo htmlspecialchars($cat); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['tags'])): ?>
                                <div class="job-tags">
                                    <div class="skill-tags">
                                        <?php foreach (explode(', ', $job['tags']) as $t): ?>
                                            <span class="skill-tag"><?php echo htmlspecialchars($t); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="job-excerpt">
                                    <?php 
                                    $description = htmlspecialchars($job['description']);
                                    echo substr($description, 0, 150) . (strlen($description) > 150 ? '...' : '');
                                    ?>
                                </div>
                            </div>
                            
                            <div class="job-card-footer">
                                <span class="job-date">Posted <?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                                <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-link">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&job_type=<?php echo urlencode($job_type); ?>&category=<?php echo $category; ?>&tag=<?php echo $tag; ?>&sort=<?php echo $sort; ?>" class="pagination-prev">&laquo; Previous</a>
                        <?php endif; ?>
                        
                        <div class="pagination-numbers">
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="pagination-current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&job_type=<?php echo urlencode($job_type); ?>&category=<?php echo $category; ?>&tag=<?php echo $tag; ?>&sort=<?php echo $sort; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location); ?>&job_type=<?php echo urlencode($job_type); ?>&category=<?php echo $category; ?>&tag=<?php echo $tag; ?>&sort=<?php echo $sort; ?>" class="pagination-next">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 