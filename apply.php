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
    $_SESSION['error'] = 'please login to apply for jobs.';
    header('Location: login.php');
    exit;
}

// get job id from url
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// fetch job details
try {
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        $_SESSION['error'] = 'job not found.';
        header('Location: jobs.php');
        exit;
    }

    // check if user has already applied for this job
    $stmt = $conn->prepare("SELECT * FROM applications WHERE job_id = ? AND user_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $existing_application = $stmt->fetch();

    if ($existing_application) {
        $_SESSION['error'] = 'you have already applied for this job.';
        header('Location: job-details.php?id=' . $job_id);
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch job details.';
    header('Location: jobs.php');
    exit;
}

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate form data
    $cover_letter = isset($_POST['cover_letter']) ? trim($_POST['cover_letter']) : '';
    $errors = [];

    if (empty($cover_letter)) {
        $errors[] = 'cover letter is required.';
    }

    // validate resume upload
    $resume = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['pdf', 'doc', 'docx'];
        $file_info = pathinfo($_FILES['resume']['name']);
        $file_extension = strtolower($file_info['extension']);

        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = 'invalid file format. please upload pdf, doc, or docx.';
        } else {
            $upload_dir = 'uploads/resumes/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $resume = $upload_dir . time() . '_' . $_FILES['resume']['name'];
            if (!move_uploaded_file($_FILES['resume']['tmp_name'], $resume)) {
                $errors[] = 'failed to upload resume.';
                $resume = null;
            }
        }
    } else {
        $errors[] = 'resume is required.';
    }

    // if no errors, save application
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO applications (job_id, user_id, cover_letter, resume, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $result = $stmt->execute([$job_id, $_SESSION['user_id'], $cover_letter, $resume]);

            if ($result) {
                $_SESSION['success'] = 'your application has been submitted successfully.';
                header('Location: my-applications.php');
                exit;
            } else {
                $_SESSION['error'] = 'failed to submit application.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'database error: ' . $e->getMessage();
        }
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="job-application">
            <h1>Apply for <?php echo htmlspecialchars($job['title']); ?></h1>
            <div class="job-meta">
                <span class="company"><?php echo htmlspecialchars($job['company']); ?></span>
                <span class="location"><?php echo htmlspecialchars($job['location']); ?></span>
                <span class="job-type"><?php echo htmlspecialchars($job['job_type']); ?></span>
            </div>

            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="form application-form" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="resume">Resume/CV</label>
                    <input type="file" name="resume" id="resume" required>
                    <small>Upload your resume (PDF, DOC, DOCX format only)</small>
                </div>

                <div class="form-group">
                    <label for="cover_letter">Cover Letter</label>
                    <textarea name="cover_letter" id="cover_letter" rows="10" required><?php echo isset($cover_letter) ? htmlspecialchars($cover_letter) : ''; ?></textarea>
                    <small>Explain why you're interested in this position and why you're a good fit.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                    <a href="job-details.php?id=<?php echo $job_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 