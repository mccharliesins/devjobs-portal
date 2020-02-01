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
    $_SESSION['error'] = 'please login to edit jobs.';
    header('Location: login.php');
    exit;
}

// check if user is a recruiter
try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'recruiter') {
        $_SESSION['error'] = 'you do not have permission to edit jobs.';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// get job id from url
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($job_id <= 0) {
    $_SESSION['error'] = 'invalid job id.';
    header('Location: recruiter-dashboard.php');
    exit;
}

// fetch job data, ensure it belongs to the recruiter
try {
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $job = $stmt->fetch();

    if (!$job) {
        $_SESSION['error'] = 'job not found or you do not have permission to edit it.';
        header('Location: recruiter-dashboard.php');
        exit;
    }

    $job_data = [
        'title' => $job['title'],
        'company' => $job['company'],
        'location' => $job['location'],
        'job_type' => $job['job_type'],
        'salary_range' => $job['salary_range'],
        'description' => $job['description'],
        'requirements' => $job['requirements']
    ];
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: recruiter-dashboard.php');
    exit;
}

$errors = [];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate form data
    $job_data = [
        'title' => isset($_POST['title']) ? trim($_POST['title']) : '',
        'company' => isset($_POST['company']) ? trim($_POST['company']) : '',
        'location' => isset($_POST['location']) ? trim($_POST['location']) : '',
        'job_type' => isset($_POST['job_type']) ? trim($_POST['job_type']) : '',
        'salary_range' => isset($_POST['salary_range']) ? trim($_POST['salary_range']) : '',
        'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
        'requirements' => isset($_POST['requirements']) ? trim($_POST['requirements']) : ''
    ];

    // perform validation
    if (empty($job_data['title'])) {
        $errors[] = 'job title is required.';
    }

    if (empty($job_data['company'])) {
        $errors[] = 'company name is required.';
    }

    if (empty($job_data['location'])) {
        $errors[] = 'job location is required.';
    }

    if (empty($job_data['job_type'])) {
        $errors[] = 'job type is required.';
    }

    if (empty($job_data['description'])) {
        $errors[] = 'job description is required.';
    }

    // if no errors, update the job
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE jobs 
                SET title = ?, company = ?, location = ?, job_type = ?, 
                    salary_range = ?, description = ?, requirements = ?, updated_at = NOW()
                WHERE id = ? AND recruiter_id = ?
            ");
            $result = $stmt->execute([
                $job_data['title'],
                $job_data['company'],
                $job_data['location'],
                $job_data['job_type'],
                $job_data['salary_range'],
                $job_data['description'],
                $job_data['requirements'],
                $job_id,
                $_SESSION['user_id']
            ]);

            if ($result) {
                $_SESSION['success'] = 'job updated successfully.';
                header('Location: recruiter-dashboard.php');
                exit;
            } else {
                $errors[] = 'failed to update job.';
            }
        } catch (PDOException $e) {
            $errors[] = 'database error: ' . $e->getMessage();
        }
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <h1>Edit Job</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="form job-form" method="POST">
            <div class="form-group">
                <label for="title">Job Title*</label>
                <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($job_data['title']); ?>" required>
            </div>

            <div class="form-group">
                <label for="company">Company Name*</label>
                <input type="text" name="company" id="company" value="<?php echo htmlspecialchars($job_data['company']); ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Location*</label>
                <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($job_data['location']); ?>" required>
            </div>

            <div class="form-group">
                <label for="job_type">Job Type*</label>
                <select name="job_type" id="job_type" required>
                    <option value="">Select Job Type</option>
                    <option value="full-time" <?php echo $job_data['job_type'] === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                    <option value="part-time" <?php echo $job_data['job_type'] === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                    <option value="contract" <?php echo $job_data['job_type'] === 'contract' ? 'selected' : ''; ?>>Contract</option>
                    <option value="internship" <?php echo $job_data['job_type'] === 'internship' ? 'selected' : ''; ?>>Internship</option>
                </select>
            </div>

            <div class="form-group">
                <label for="salary_range">Salary Range (optional)</label>
                <input type="text" name="salary_range" id="salary_range" value="<?php echo htmlspecialchars($job_data['salary_range']); ?>" placeholder="e.g. 50000-70000">
                <small>Format: min-max (e.g. 50000-70000)</small>
            </div>

            <div class="form-group">
                <label for="description">Job Description*</label>
                <textarea name="description" id="description" rows="10" required><?php echo htmlspecialchars($job_data['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="requirements">Job Requirements (optional)</label>
                <textarea name="requirements" id="requirements" rows="5"><?php echo htmlspecialchars($job_data['requirements']); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Job</button>
                <a href="recruiter-dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 