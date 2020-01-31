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
    $_SESSION['error'] = 'please login to post a job.';
    header('Location: login.php');
    exit;
}

// initialize variables
$title = $company = $location = $description = $requirements = $salary_range = $job_type = '';
$errors = [];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get form data
    $title = trim($_POST['title']);
    $company = trim($_POST['company']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $salary_range = trim($_POST['salary_range']);
    $job_type = trim($_POST['job_type']);

    // validate form data
    if (empty($title)) {
        $errors[] = 'job title is required';
    }

    if (empty($company)) {
        $errors[] = 'company name is required';
    }

    if (empty($location)) {
        $errors[] = 'location is required';
    }

    if (empty($description)) {
        $errors[] = 'job description is required';
    }

    if (empty($job_type)) {
        $errors[] = 'job type is required';
    }

    // if no errors, insert job into database
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO jobs (title, company, location, description, requirements, salary_range, job_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $company, $location, $description, $requirements, $salary_range, $job_type]);
            
            // redirect to jobs page
            $_SESSION['success'] = 'job posted successfully!';
            header('Location: jobs.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'failed to post job. please try again.';
        }
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <h1>Post a Job</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="form">
            <div class="form-group">
                <label for="title">Job Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
            </div>

            <div class="form-group">
                <label for="company">Company Name</label>
                <input type="text" id="company" name="company" value="<?php echo htmlspecialchars($company); ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Job Description</label>
                <textarea id="description" name="description" rows="5" required><?php echo htmlspecialchars($description); ?></textarea>
            </div>

            <div class="form-group">
                <label for="requirements">Requirements</label>
                <textarea id="requirements" name="requirements" rows="5"><?php echo htmlspecialchars($requirements); ?></textarea>
            </div>

            <div class="form-group">
                <label for="salary_range">Salary Range</label>
                <input type="text" id="salary_range" name="salary_range" value="<?php echo htmlspecialchars($salary_range); ?>">
            </div>

            <div class="form-group">
                <label for="job_type">Job Type</label>
                <select id="job_type" name="job_type" required>
                    <option value="">Select Job Type</option>
                    <option value="full-time" <?php echo $job_type === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                    <option value="part-time" <?php echo $job_type === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                    <option value="contract" <?php echo $job_type === 'contract' ? 'selected' : ''; ?>>Contract</option>
                    <option value="internship" <?php echo $job_type === 'internship' ? 'selected' : ''; ?>>Internship</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Post Job</button>
        </form>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 