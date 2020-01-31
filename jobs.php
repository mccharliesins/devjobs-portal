<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$job_type = isset($_GET['job_type']) ? trim($_GET['job_type']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$experience = isset($_GET['experience']) ? trim($_GET['experience']) : '';
$salary_min = isset($_GET['salary_min']) ? (int)$_GET['salary_min'] : '';
$salary_max = isset($_GET['salary_max']) ? (int)$_GET['salary_max'] : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

// pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// build query
$query = "SELECT * FROM jobs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR company LIKE ? OR description LIKE ? OR requirements LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($job_type)) {
    $query .= " AND job_type = ?";
    $params[] = $job_type;
}

if (!empty($location)) {
    $query .= " AND location LIKE ?";
    $params[] = "%$location%";
}

if (!empty($experience)) {
    $query .= " AND requirements LIKE ?";
    $params[] = "%$experience%";
}

if (!empty($salary_min)) {
    $query .= " AND CAST(SUBSTRING_INDEX(salary_range, '-', 1) AS UNSIGNED) >= ?";
    $params[] = $salary_min;
}

if (!empty($salary_max)) {
    $query .= " AND CAST(SUBSTRING_INDEX(salary_range, '-', -1) AS UNSIGNED) <= ?";
    $params[] = $salary_max;
}

// add sorting
switch ($sort) {
    case 'salary_high':
        $query .= " ORDER BY CAST(SUBSTRING_INDEX(salary_range, '-', -1) AS UNSIGNED) DESC";
        break;
    case 'salary_low':
        $query .= " ORDER BY CAST(SUBSTRING_INDEX(salary_range, '-', 1) AS UNSIGNED) ASC";
        break;
    case 'oldest':
        $query .= " ORDER BY created_at ASC";
        break;
    default:
        $query .= " ORDER BY created_at DESC";
}

// get total count for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*)", $query);
$stmt = $conn->prepare($count_query);
$stmt->execute($params);
$total_jobs = $stmt->fetchColumn();
$total_pages = ceil($total_jobs / $per_page);

// add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// execute query
try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    $jobs = [];
    $_SESSION['error'] = 'failed to fetch jobs. please try again.';
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <h1>Browse Jobs</h1>

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

        <div class="job-filters">
            <form action="" method="GET">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search jobs..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">Search</button>
                </div>
                <div class="filter-options">
                    <select name="job_type">
                        <option value="">All Types</option>
                        <option value="full-time" <?php echo $job_type === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                        <option value="part-time" <?php echo $job_type === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                        <option value="contract" <?php echo $job_type === 'contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="internship" <?php echo $job_type === 'internship' ? 'selected' : ''; ?>>Internship</option>
                    </select>
                    <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>">
                    <select name="experience">
                        <option value="">Experience Level</option>
                        <option value="entry" <?php echo $experience === 'entry' ? 'selected' : ''; ?>>Entry Level</option>
                        <option value="mid" <?php echo $experience === 'mid' ? 'selected' : ''; ?>>Mid Level</option>
                        <option value="senior" <?php echo $experience === 'senior' ? 'selected' : ''; ?>>Senior Level</option>
                        <option value="lead" <?php echo $experience === 'lead' ? 'selected' : ''; ?>>Lead</option>
                    </select>
                    <div class="salary-range">
                        <input type="number" name="salary_min" placeholder="Min Salary" value="<?php echo htmlspecialchars($salary_min); ?>">
                        <input type="number" name="salary_max" placeholder="Max Salary" value="<?php echo htmlspecialchars($salary_max); ?>">
                    </div>
                    <select name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="salary_high" <?php echo $sort === 'salary_high' ? 'selected' : ''; ?>>Salary: High to Low</option>
                        <option value="salary_low" <?php echo $sort === 'salary_low' ? 'selected' : ''; ?>>Salary: Low to High</option>
                    </select>
                </div>
            </form>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="text-right">
                <a href="post-job.php" class="btn btn-primary">Post a Job</a>
            </div>
        <?php endif; ?>

        <div class="job-list">
            <?php if (empty($jobs)): ?>
                <p>No jobs available.</p>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <div class="job-card">
                        <h2><?php echo htmlspecialchars($job['title']); ?></h2>
                        <div class="job-meta">
                            <span class="company"><?php echo htmlspecialchars($job['company']); ?></span>
                            <span class="location"><?php echo htmlspecialchars($job['location']); ?></span>
                            <span class="job-type"><?php echo htmlspecialchars($job['job_type']); ?></span>
                            <?php if (!empty($job['salary_range'])): ?>
                                <span class="salary"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="job-description">
                            <?php echo nl2br(htmlspecialchars(substr($job['description'], 0, 200))); ?>...
                        </div>
                        <div class="job-actions">
                            <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&job_type=<?php echo urlencode($job_type); ?>&location=<?php echo urlencode($location); ?>&experience=<?php echo urlencode($experience); ?>&salary_min=<?php echo urlencode($salary_min); ?>&salary_max=<?php echo urlencode($salary_max); ?>&sort=<?php echo urlencode($sort); ?>" 
                               class="<?php echo $page === $i ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
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