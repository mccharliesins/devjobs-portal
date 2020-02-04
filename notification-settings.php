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
    $_SESSION['error'] = 'please log in to access notification settings.';
    header('Location: login.php');
    exit;
}

// fetch user's notification preferences
try {
    $stmt = $conn->prepare("
        SELECT * FROM notification_preferences WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $preferences = $stmt->fetch();
    
    // if no preferences exist, create default preferences
    if (!$preferences) {
        $stmt = $conn->prepare("
            INSERT INTO notification_preferences 
            (user_id, application_received, application_status_change, new_applicant, job_recommendations) 
            VALUES (?, TRUE, TRUE, TRUE, TRUE)
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        // fetch the newly created preferences
        $stmt = $conn->prepare("
            SELECT * FROM notification_preferences WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $preferences = $stmt->fetch();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch notification preferences: ' . $e->getMessage();
    header('Location: user-dashboard.php');
    exit;
}

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get form data
    $application_received = isset($_POST['application_received']) ? 1 : 0;
    $application_status_change = isset($_POST['application_status_change']) ? 1 : 0;
    $new_applicant = isset($_POST['new_applicant']) ? 1 : 0;
    $job_recommendations = isset($_POST['job_recommendations']) ? 1 : 0;
    
    try {
        // update notification preferences
        $stmt = $conn->prepare("
            UPDATE notification_preferences 
            SET 
                application_received = ?,
                application_status_change = ?,
                new_applicant = ?,
                job_recommendations = ?
            WHERE user_id = ?
        ");
        
        $result = $stmt->execute([
            $application_received,
            $application_status_change,
            $new_applicant,
            $job_recommendations,
            $_SESSION['user_id']
        ]);
        
        if ($result) {
            $_SESSION['success'] = 'notification preferences updated successfully.';
            
            // update the preferences variable to reflect the changes
            $preferences['application_received'] = $application_received;
            $preferences['application_status_change'] = $application_status_change;
            $preferences['new_applicant'] = $new_applicant;
            $preferences['job_recommendations'] = $job_recommendations;
        } else {
            $_SESSION['error'] = 'failed to update notification preferences.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'database error: ' . $e->getMessage();
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1>Notification Settings</h1>
            <?php if ($_SESSION['role'] === 'user'): ?>
                <a href="user-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <?php else: ?>
                <a href="recruiter-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="notification-settings-container">
            <form method="POST" class="form">
                <div class="form-section">
                    <h2>Email Notification Preferences</h2>
                    <p class="form-section-description">Choose which email notifications you'd like to receive from DevJobs.</p>
                    
                    <?php if ($_SESSION['role'] === 'user'): ?>
                        <div class="form-group checkbox-group">
                            <label class="checkbox-container">
                                <input type="checkbox" name="application_received" <?php echo $preferences['application_received'] ? 'checked' : ''; ?>>
                                <span class="checkbox-label">Application Confirmation</span>
                                <small>Receive an email when you submit a job application</small>
                            </label>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-container">
                                <input type="checkbox" name="application_status_change" <?php echo $preferences['application_status_change'] ? 'checked' : ''; ?>>
                                <span class="checkbox-label">Application Status Updates</span>
                                <small>Receive an email when the status of your application changes</small>
                            </label>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-container">
                                <input type="checkbox" name="job_recommendations" <?php echo $preferences['job_recommendations'] ? 'checked' : ''; ?>>
                                <span class="checkbox-label">Job Recommendations</span>
                                <small>Receive job recommendations based on your profile and search history</small>
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['role'] === 'recruiter'): ?>
                        <div class="form-group checkbox-group">
                            <label class="checkbox-container">
                                <input type="checkbox" name="new_applicant" <?php echo $preferences['new_applicant'] ? 'checked' : ''; ?>>
                                <span class="checkbox-label">New Application Alerts</span>
                                <small>Receive an email when a candidate applies to your job listing</small>
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 