<?php
// include database connection
require_once 'db.php';
require_once 'includes/mailer.php';

// include email functions
require_once 'includes/email_functions.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'please log in to apply for jobs.';
    header('Location: login.php');
    exit;
}

// check if user is a recruiter (recruiters cannot apply for jobs)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'recruiter') {
    $_SESSION['error'] = 'recruiters cannot apply for jobs.';
    header('Location: jobs.php');
    exit;
}

// check if job_id is provided
if (!isset($_GET['job_id']) || empty($_GET['job_id'])) {
    $_SESSION['error'] = 'job id is missing.';
    header('Location: jobs.php');
    exit;
}

$job_id = intval($_GET['job_id']);

// check if the user has already applied for this job
try {
    $stmt = $conn->prepare("SELECT id FROM applications WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$_SESSION['user_id'], $job_id]);
    
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'you have already applied for this job.';
        header("Location: job-details.php?id=$job_id");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header("Location: job-details.php?id=$job_id");
    exit;
}

// fetch job details
try {
    $stmt = $conn->prepare("
        SELECT j.*, c.name as company_name 
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.id
        WHERE j.id = ?
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
    
    if (!$job) {
        $_SESSION['error'] = 'job not found.';
        header('Location: jobs.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch job details: ' . $e->getMessage();
    header('Location: jobs.php');
    exit;
}

// fetch user profile
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'user not found.';
        header('Location: jobs.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch user details: ' . $e->getMessage();
    header('Location: jobs.php');
    exit;
}

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate form data
    $cover_letter = trim($_POST['cover_letter'] ?? '');
    
    $errors = [];
    
    if (empty($cover_letter)) {
        $errors[] = 'cover letter is required.';
    }
    
    // handle resume upload
    $resume_path = $user['resume'] ?? '';
    
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
            $upload_error_messages = [
                UPLOAD_ERR_INI_SIZE => 'the uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'the uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
                UPLOAD_ERR_PARTIAL => 'the uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'no file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'a PHP extension stopped the file upload.'
            ];
            $errors[] = 'resume upload error: ' . ($upload_error_messages[$_FILES['resume']['error']] ?? 'unknown error');
        } else {
            // validate file type
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            if (!in_array($_FILES['resume']['type'], $allowed_types)) {
                $errors[] = 'resume must be a PDF or Word document.';
            } else {
                // generate unique filename
                $upload_dir = 'uploads/resumes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
                $filename = 'resume_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $resume_path = $upload_dir . $filename;
                
                if (!move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path)) {
                    $errors[] = 'failed to upload resume.';
                    $resume_path = $user['resume'] ?? '';
                } else {
                    // update user's resume path if they don't have one
                    if (empty($user['resume'])) {
                        try {
                            $update_stmt = $conn->prepare("UPDATE users SET resume = ? WHERE id = ?");
                            $update_stmt->execute([$resume_path, $_SESSION['user_id']]);
                        } catch (PDOException $e) {
                            // not critical, just log it
                            error_log('Failed to update user resume: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    } elseif (empty($resume_path)) {
        $errors[] = 'resume is required.';
    }
    
    // if no errors, submit application
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // insert application
            $stmt = $conn->prepare("
                INSERT INTO applications (user_id, job_id, cover_letter, resume, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            
            $result = $stmt->execute([
                $_SESSION['user_id'],
                $job_id,
                $cover_letter,
                $resume_path
            ]);
            
            if ($result) {
                $application_id = $conn->lastInsertId();
                
                // get recruiter info for notification
                $recruiter_stmt = $conn->prepare("
                    SELECT u.* FROM users u 
                    WHERE u.id = ?
                ");
                $recruiter_stmt->execute([$job['user_id']]);
                $recruiter = $recruiter_stmt->fetch();
                
                $conn->commit();
                
                // send email notification to applicant
                $email_template = get_application_received_email(
                    $user['username'],
                    $job['title'],
                    $job['company_name'],
                    $job_id
                );
                
                send_email(
                    $user['email'],
                    $email_template['subject'],
                    $email_template['body']
                );
                
                // send email notification to recruiter
                if ($recruiter) {
                    $recruiter_email = get_new_application_email_to_recruiter(
                        $recruiter['username'],
                        $user['username'],
                        $job['title'],
                        $job_id,
                        $application_id
                    );
                    
                    send_email(
                        $recruiter['email'],
                        $recruiter_email['subject'],
                        $recruiter_email['body']
                    );
                }
                
                $_SESSION['success'] = 'your application has been submitted successfully!';
                header("Location: job-details.php?id=$job_id");
                exit;
            } else {
                $conn->rollBack();
                $errors[] = 'failed to submit application.';
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = 'database error: ' . $e->getMessage();
        }
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="job-application">
            <div class="page-header">
                <h1>Apply for <?php echo htmlspecialchars($job['title']); ?></h1>
                <a href="job-details.php?id=<?php echo $job_id; ?>" class="btn btn-secondary">Back to Job Details</a>
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
            
            <div class="job-summary">
                <h2>Job Summary</h2>
                <div class="job-meta">
                    <p><strong>Title:</strong> <?php echo htmlspecialchars($job['title']); ?></p>
                    <p><strong>Company:</strong> <?php echo htmlspecialchars($job['company_name'] ?? 'N/A'); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                    <p><strong>Job Type:</strong> <?php echo htmlspecialchars($job['job_type']); ?></p>
                </div>
            </div>
            
            <div class="application-form-container">
                <form method="POST" enctype="multipart/form-data" class="application-form">
                    <div class="form-group">
                        <label for="resume">Resume</label>
                        <?php if (!empty($user['resume'])): ?>
                            <div class="current-resume">
                                <p>Current resume: <a href="<?php echo htmlspecialchars($user['resume']); ?>" target="_blank"><?php echo basename($user['resume']); ?></a></p>
                                <p>Upload a new resume or use the current one:</p>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx">
                        <small>Upload your resume (PDF or Word document, max 2MB)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="cover_letter">Cover Letter</label>
                        <textarea id="cover_letter" name="cover_letter" rows="10" required><?php echo htmlspecialchars($_POST['cover_letter'] ?? ''); ?></textarea>
                        <small>Explain why you are a good fit for this role</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                        <a href="job-details.php?id=<?php echo $job_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 