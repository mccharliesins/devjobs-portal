<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// check if user is logged in and is a recruiter
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'recruiter') {
    $_SESSION['error'] = 'you must be logged in as a recruiter to create a job posting.';
    header('Location: login.php');
    exit;
}

// get user's companies
try {
    $stmt = $conn->prepare("SELECT id, name FROM companies WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $companies = $stmt->fetchAll();
    
    if (empty($companies)) {
        $_SESSION['warning'] = 'you need to create a company profile before posting a job.';
        header('Location: create-company.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch companies: ' . $e->getMessage();
    header('Location: recruiter-dashboard.php');
    exit;
}

// form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get and validate submitted data
    $title = trim($_POST['title'] ?? '');
    $company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    $location = trim($_POST['location'] ?? '');
    $job_type = trim($_POST['job_type'] ?? '');
    $salary_min = !empty($_POST['salary_min']) ? (int)$_POST['salary_min'] : null;
    $salary_max = !empty($_POST['salary_max']) ? (int)$_POST['salary_max'] : null;
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    
    $errors = [];
    
    // validate required fields
    if (empty($title)) {
        $errors[] = 'job title is required.';
    }
    
    if ($company_id <= 0) {
        $errors[] = 'please select a company.';
    } else {
        // verify company belongs to the user
        $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ? AND user_id = ?");
        $stmt->execute([$company_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'invalid company selected.';
        }
    }
    
    if (empty($location)) {
        $errors[] = 'job location is required.';
    }
    
    if (empty($job_type)) {
        $errors[] = 'job type is required.';
    }
    
    if (empty($description)) {
        $errors[] = 'job description is required.';
    }
    
    // validate salary range if provided
    if ($salary_min !== null && $salary_max !== null && $salary_min > $salary_max) {
        $errors[] = 'minimum salary cannot be greater than maximum salary.';
    }
    
    // if no errors, insert job posting
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO jobs (title, company_id, location, description, requirements, 
                                 salary_min, salary_max, job_type, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $title, $company_id, $location, $description, $requirements,
                $salary_min, $salary_max, $job_type, $_SESSION['user_id']
            ]);
            
            if ($result) {
                $job_id = $conn->lastInsertId();
                $_SESSION['success'] = 'job posting created successfully!';
                header('Location: job-details.php?id=' . $job_id);
                exit;
            } else {
                $errors[] = 'failed to create job posting.';
            }
        } catch (PDOException $e) {
            $errors[] = 'database error: ' . $e->getMessage();
        }
    }
}

// job type options for dropdown
$job_types = [
    'Full-time',
    'Part-time',
    'Contract',
    'Freelance',
    'Internship',
    'Remote'
];

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1>Create Job Posting</h1>
            <a href="recruiter-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" class="form job-form">
                <div class="form-section">
                    <h2>Job Details</h2>
                    
                    <div class="form-group">
                        <label for="title">Job Title *</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="company_id">Company *</label>
                        <select id="company_id" name="company_id" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>" <?php echo (isset($_POST['company_id']) && $_POST['company_id'] == $company['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>
                            <a href="create-company.php">Add another company profile</a>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" required>
                        <small>E.g. "New York, NY" or "Remote"</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="job_type">Job Type *</label>
                        <select id="job_type" name="job_type" required>
                            <option value="">Select Job Type</option>
                            <?php foreach ($job_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (isset($_POST['job_type']) && $_POST['job_type'] === $type) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="salary_min">Minimum Salary</label>
                            <input type="number" id="salary_min" name="salary_min" value="<?php echo htmlspecialchars($_POST['salary_min'] ?? ''); ?>" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="salary_max">Maximum Salary</label>
                            <input type="number" id="salary_max" name="salary_max" value="<?php echo htmlspecialchars($_POST['salary_max'] ?? ''); ?>" min="0">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Job Description</h2>
                    
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" rows="8" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <small>Provide a detailed description of the job responsibilities.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="requirements">Requirements</label>
                        <textarea id="requirements" name="requirements" rows="8"><?php echo htmlspecialchars($_POST['requirements'] ?? ''); ?></textarea>
                        <small>List skills, experience, and qualifications required for this position.</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Post Job</button>
                    <a href="recruiter-dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 