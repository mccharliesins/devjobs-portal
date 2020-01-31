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

// build query
$query = "SELECT * FROM jobs WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR company LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($job_type)) {
    $query .= " AND job_type = ?";
    $params[] = $job_type;
}

if (!empty($location)) {
    $query .= " AND location LIKE ?";
    $params[] = "%$location%";
}

$query .= " ORDER BY created_at DESC";

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
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 