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

// fetch job data
try {
    // get job details
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $job = $stmt->fetch();
    
    if (!$job) {
        $_SESSION['error'] = 'job not found or you do not have permission to edit it.';
        header('Location: recruiter-dashboard.php');
        exit;
    }
    
    // Get current categories for this job
    $stmt = $conn->prepare("
        SELECT category_id FROM job_categories WHERE job_id = ?
    ");
    $stmt->execute([$job_id]);
    $job_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get current tags for this job
    $stmt = $conn->prepare("
        SELECT tag_id FROM job_tags WHERE job_id = ?
    ");
    $stmt->execute([$job_id]);
    $job_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // fetch all categories
    $stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // fetch all tags
    $stmt = $conn->prepare("SELECT id, name FROM tags ORDER BY name");
    $stmt->execute();
    $tags = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'error fetching job data: ' . $e->getMessage();
    header('Location: recruiter-dashboard.php');
    exit;
}

$errors = [];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate form data
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $company = isset($_POST['company']) ? trim($_POST['company']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $job_type = isset($_POST['job_type']) ? trim($_POST['job_type']) : '';
    $salary_range = isset($_POST['salary_range']) ? trim($_POST['salary_range']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $requirements = isset($_POST['requirements']) ? trim($_POST['requirements']) : '';
    $selected_categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $selected_tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    
    // perform validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'job title is required.';
    }
    
    if (empty($company)) {
        $errors[] = 'company name is required.';
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
    
    if (empty($selected_categories)) {
        $errors[] = 'please select at least one job category.';
    }

    // if no errors, update the job
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Update the job
            $stmt = $conn->prepare("
                UPDATE jobs 
                SET title = ?, company = ?, location = ?, job_type = ?, 
                    salary_range = ?, description = ?, requirements = ?, updated_at = NOW()
                WHERE id = ? AND recruiter_id = ?
            ");
            $result = $stmt->execute([
                $title, $company, $location, $job_type, 
                $salary_range, $description, $requirements, 
                $job_id, $_SESSION['user_id']
            ]);
            
            if ($result) {
                // Update categories
                
                // First, delete existing categories
                $stmt = $conn->prepare("DELETE FROM job_categories WHERE job_id = ?");
                $stmt->execute([$job_id]);
                
                // Then insert new selections
                if (!empty($selected_categories)) {
                    $stmt = $conn->prepare("INSERT INTO job_categories (job_id, category_id) VALUES (?, ?)");
                    foreach ($selected_categories as $category_id) {
                        $stmt->execute([$job_id, $category_id]);
                    }
                }
                
                // Update tags
                
                // First, delete existing tags
                $stmt = $conn->prepare("DELETE FROM job_tags WHERE job_id = ?");
                $stmt->execute([$job_id]);
                
                // Then insert new selections
                if (!empty($selected_tags)) {
                    $stmt = $conn->prepare("INSERT INTO job_tags (job_id, tag_id) VALUES (?, ?)");
                    foreach ($selected_tags as $tag_id) {
                        $stmt->execute([$job_id, $tag_id]);
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = 'job updated successfully.';
                header('Location: recruiter-dashboard.php');
                exit;
            } else {
                $conn->rollBack();
                $errors[] = 'failed to update job.';
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
        <div class="page-header">
            <h1>Edit Job</h1>
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

        <form method="POST" action="" class="form">
            <div class="form-section">
                <h2>Job Details</h2>
                
                <div class="form-group">
                    <label for="title">Job Title *</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="company">Company Name *</label>
                    <input type="text" id="company" name="company" value="<?php echo htmlspecialchars($job['company']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($job['location']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="job_type">Job Type *</label>
                    <select id="job_type" name="job_type" required>
                        <option value="">-- Select Job Type --</option>
                        <option value="full-time" <?php echo $job['job_type'] === 'full-time' ? 'selected' : ''; ?>>Full Time</option>
                        <option value="part-time" <?php echo $job['job_type'] === 'part-time' ? 'selected' : ''; ?>>Part Time</option>
                        <option value="contract" <?php echo $job['job_type'] === 'contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="internship" <?php echo $job['job_type'] === 'internship' ? 'selected' : ''; ?>>Internship</option>
                        <option value="remote" <?php echo $job['job_type'] === 'remote' ? 'selected' : ''; ?>>Remote</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="salary_range">Salary Range</label>
                    <input type="text" id="salary_range" name="salary_range" value="<?php echo htmlspecialchars($job['salary_range']); ?>" placeholder="e.g. $50,000 - $70,000">
                </div>
            </div>

            <div class="form-section">
                <h2>Job Categories & Tags</h2>
                
                <div class="form-group">
                    <label>Categories *</label>
                    <div class="checkbox-group">
                        <?php if (isset($categories) && !empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <div class="checkbox-container">
                                    <input type="checkbox" id="cat_<?php echo $category['id']; ?>" name="categories[]" value="<?php echo $category['id']; ?>" 
                                          <?php echo in_array($category['id'], $job_categories) ? 'checked' : ''; ?>>
                                    <label for="cat_<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>no categories available. please contact an administrator.</p>
                        <?php endif; ?>
                    </div>
                    <small>select one or more categories that best describe this job</small>
                </div>
                
                <div class="form-group">
                    <label>Tags</label>
                    <div class="tags-container">
                        <?php if (isset($tags) && !empty($tags)): ?>
                            <select id="tags-select" name="tags[]" multiple>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?php echo $tag['id']; ?>" <?php echo in_array($tag['id'], $job_tags) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tag['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <p>no tags available.</p>
                        <?php endif; ?>
                    </div>
                    <small>select tags that describe skills or technologies required (optional)</small>
                </div>
            </div>
            
            <div class="form-section">
                <h2>Job Description</h2>
                
                <div class="form-group">
                    <label for="description">Job Description *</label>
                    <textarea id="description" name="description" rows="10" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                    <small>describe the responsibilities, work environment, company culture, etc.</small>
                </div>
                <div class="form-group">
                    <label for="requirements">Requirements & Qualifications</label>
                    <textarea id="requirements" name="requirements" rows="8"><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                    <small>list required skills, experience, education, certifications, etc.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Job</button>
                <a href="recruiter-dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
// Simple script to make the tags selection more user-friendly
document.addEventListener('DOMContentLoaded', function() {
    const tagsSelect = document.getElementById('tags-select');
    if (tagsSelect) {
        // This is a simple enhancement, in a real application we might use a proper
        // select2 or similar library for better multi-select functionality
        tagsSelect.size = Math.min(10, tagsSelect.options.length);
    }
});
</script>

<?php
// include footer
require_once 'includes/footer.php';
?> 