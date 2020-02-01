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
    $_SESSION['error'] = 'please login to update application status.';
    header('Location: login.php');
    exit;
}

// check if user is a recruiter
try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'recruiter') {
        $_SESSION['error'] = 'you do not have permission to update application status.';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// check if this is a post request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'invalid request method.';
    header('Location: recruiter-dashboard.php');
    exit;
}

// get form data
$application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// validate data
if ($application_id <= 0 || $job_id <= 0) {
    $_SESSION['error'] = 'invalid application or job id.';
    header('Location: recruiter-dashboard.php');
    exit;
}

if (!in_array($status, ['accepted', 'rejected'])) {
    $_SESSION['error'] = 'invalid status.';
    header('Location: view-applications.php?job_id=' . $job_id);
    exit;
}

// verify that the job belongs to the recruiter and application exists
try {
    $stmt = $conn->prepare("
        SELECT a.*, j.recruiter_id
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        WHERE a.id = ? AND j.id = ?
    ");
    $stmt->execute([$application_id, $job_id]);
    $application = $stmt->fetch();

    if (!$application) {
        $_SESSION['error'] = 'application not found.';
        header('Location: view-applications.php?job_id=' . $job_id);
        exit;
    }

    if ($application['recruiter_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = 'you do not have permission to update this application.';
        header('Location: recruiter-dashboard.php');
        exit;
    }

    if ($application['status'] !== 'pending') {
        $_SESSION['error'] = 'only pending applications can be updated.';
        header('Location: view-applications.php?job_id=' . $job_id);
        exit;
    }

    // update application status
    $stmt = $conn->prepare("UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $application_id]);

    if ($result) {
        $_SESSION['success'] = 'application ' . $status . ' successfully.';
        
        // TODO: Send email notification to applicant about status change
    } else {
        $_SESSION['error'] = 'failed to update application status.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
}

// redirect back to applications page
header('Location: view-applications.php?job_id=' . $job_id);
exit; 