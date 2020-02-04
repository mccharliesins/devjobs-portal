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

// check if user is logged in and is a recruiter
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'recruiter') {
    $_SESSION['error'] = 'you must be logged in as a recruiter to update application status.';
    header('Location: login.php');
    exit;
}

// check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'invalid request method.';
    header('Location: recruiter-dashboard.php');
    exit;
}

// validate form data
$application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;

if (empty($application_id) || empty($status)) {
    $_SESSION['error'] = 'application id and status are required.';
    header('Location: recruiter-dashboard.php');
    exit;
}

// validate status
$valid_statuses = ['pending', 'reviewing', 'interview', 'accepted', 'rejected'];
if (!in_array($status, $valid_statuses)) {
    $_SESSION['error'] = 'invalid status.';
    header('Location: view-applications.php' . ($job_id ? "?job_id=$job_id" : ''));
    exit;
}

try {
    // check if application exists and belongs to a job posted by the recruiter
    $stmt = $conn->prepare("
        SELECT a.*, j.title as job_title, j.company_id, c.name as company_name, u.username, u.email
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        LEFT JOIN companies c ON j.company_id = c.id
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ? AND j.user_id = ?
    ");
    $stmt->execute([$application_id, $_SESSION['user_id']]);
    $application = $stmt->fetch();
    
    if (!$application) {
        $_SESSION['error'] = 'application not found or you do not have permission to update it.';
        header('Location: view-applications.php' . ($job_id ? "?job_id=$job_id" : ''));
        exit;
    }
    
    // update application status
    $conn->beginTransaction();
    
    $stmt = $conn->prepare("
        UPDATE applications 
        SET status = ?, notes = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $status,
        $notes,
        $application_id
    ]);
    
    if ($result) {
        $conn->commit();
        
        // send email notification to applicant
        $email_template = get_application_status_update_email(
            $application['username'],
            $application['job_title'],
            $application['company_name'] ?? 'N/A',
            $status,
            $application['job_id'],
            $notes
        );
        
        send_email(
            $application['email'],
            $email_template['subject'],
            $email_template['body']
        );
        
        $_SESSION['success'] = 'application status updated successfully.';
    } else {
        $conn->rollBack();
        $_SESSION['error'] = 'failed to update application status.';
    }
} catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
}

// redirect back to the applications page
if ($job_id) {
    header("Location: view-applications.php?job_id=$job_id");
} else {
    header("Location: application-details-recruiter.php?id=$application_id");
}
exit; 