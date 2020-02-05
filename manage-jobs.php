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
    $_SESSION['error'] = 'please login to access the admin dashboard.';
    header('Location: login.php');
    exit;
}

// check if user is an admin
try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        $_SESSION['error'] = 'you do not have permission to access this page.';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// handle job actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // job deletion
    if (isset($_POST['delete_job'])) {
        $job_id = $_POST['job_id'];
        
        try {
            // delete related applications first
            $stmt = $conn->prepare("DELETE FROM applications WHERE job_id = ?");
            $stmt->execute([$job_id]);
            
            // delete saved jobs entries
            $stmt = $conn->prepare("DELETE FROM saved_jobs WHERE job_id = ?");
            $stmt->execute([$job_id]);
            
            // delete job
            $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
            $stmt->execute([$job_id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = 'job listing deleted successfully.';
            } else {
                $_SESSION['error'] = 'failed to delete job listing.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'error deleting job: ' . $e->getMessage();
        }
        
        // reload page to reflect changes
        header('Location: manage-jobs.php');
        exit;
    }
    
    // toggle job status (active/inactive)
    if (isset($_POST['toggle_status'])) {
        $job_id = $_POST['job_id'];
        $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        
        try {
            $stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $job_id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = 'job status updated successfully.';
            } else {
                $_SESSION['error'] = 'failed to update job status.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'error updating job status: ' . $e->getMessage();
        }
        
        // reload page to reflect changes
        header('Location: manage-jobs.php');
        exit;
    }
}

// get jobs with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// search and filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$job_type_filter = isset($_GET['job_type']) ? $_GET['job_type'] : '';

try {
    // prepare base query
    $query = "SELECT j.*, c.name as company_name, u.username as recruiter_name 
              FROM jobs j 
              LEFT JOIN companies c ON j.company_id = c.id
              LEFT JOIN users u ON j.recruiter_id = u.id
              WHERE 1=1";
    
    $count_query = "SELECT COUNT(*) FROM jobs j 
                    LEFT JOIN companies c ON j.company_id = c.id
                    LEFT JOIN users u ON j.recruiter_id = u.id
                    WHERE 1=1";
    
    $params = [];
    
    // add search condition if search term provided
    if (!empty($search)) {
        $query .= " AND (j.title LIKE ? OR j.description LIKE ? OR c.name LIKE ?)";
        $count_query .= " AND (j.title LIKE ? OR j.description LIKE ? OR c.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // add status filter if specified
    if (!empty($status_filter)) {
        $query .= " AND j.status = ?";
        $count_query .= " AND j.status = ?";
        $params[] = $status_filter;
    }
    
    // add job type filter if specified
    if (!empty($job_type_filter)) {
        $query .= " AND j.job_type = ?";
        $count_query .= " AND j.job_type = ?";
        $params[] = $job_type_filter;
    }
    
    // add order and limit
    $query .= " ORDER BY j.created_at DESC LIMIT $offset, $per_page";
    
    // get total count
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_jobs = $stmt->fetchColumn();
    
    // get jobs for current page
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
    
    // calculate total pages
    $total_pages = ceil($total_jobs / $per_page);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch jobs: ' . $e->getMessage();
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>Manage Jobs</h1>
            <a href="admin-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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

        <div class="job-filters">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search jobs by title, description or company" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <select name="job_type">
                        <option value="">All Job Types</option>
                        <option value="full-time" <?php echo $job_type_filter === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                        <option value="part-time" <?php echo $job_type_filter === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                        <option value="contract" <?php echo $job_type_filter === 'contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="internship" <?php echo $job_type_filter === 'internship' ? 'selected' : ''; ?>>Internship</option>
                        <option value="remote" <?php echo $job_type_filter === 'remote' ? 'selected' : ''; ?>>Remote</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="manage-jobs.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <div class="jobs-list">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Company</th>
                        <th>Recruiter</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($jobs) && !empty($jobs)): ?>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($job['id']); ?></td>
                                <td><?php echo htmlspecialchars($job['title']); ?></td>
                                <td><?php echo htmlspecialchars($job['company_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($job['recruiter_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                                <td class="status-<?php echo htmlspecialchars($job['status']); ?>">
                                    <?php echo htmlspecialchars($job['status']); ?>
                                </td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($job['created_at']))); ?></td>
                                <td class="actions">
                                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-small btn-primary">View</a>
                                    
                                    <form method="POST" action="" class="inline-form">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $job['status']; ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-small btn-secondary">
                                            <?php echo $job['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" action="" class="inline-form" onsubmit="return confirm('are you sure you want to delete this job? this will also delete all applications for this job.');">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" name="delete_job" class="btn btn-small btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-results">no jobs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&job_type=<?php echo urlencode($job_type_filter); ?>" class="btn btn-small">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&job_type=<?php echo urlencode($job_type_filter); ?>" 
                       class="btn btn-small <?php echo $i === $page ? 'btn-active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&job_type=<?php echo urlencode($job_type_filter); ?>" class="btn btn-small">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 