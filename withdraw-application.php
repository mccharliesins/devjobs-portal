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
    $_SESSION['error'] = 'please login to withdraw applications.';
    header('Location: login.php');
    exit;
}

// check if this is a post request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'invalid request method.';
    header('Location: my-applications.php');
    exit;
}

// get application id from post data
$application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;

if ($application_id <= 0) {
    $_SESSION['error'] = 'invalid application id.';
    header('Location: my-applications.php');
    exit;
}

// verify that the application belongs to the current user and is in pending status
try {
    $stmt = $conn->prepare("SELECT * FROM applications WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$application_id, $_SESSION['user_id']]);
    $application = $stmt->fetch();

    if (!$application) {
        $_SESSION['error'] = 'application not found, already processed, or you do not have permission to withdraw it.';
        header('Location: my-applications.php');
        exit;
    }

    // update application status to withdrawn
    $stmt = $conn->prepare("UPDATE applications SET status = 'withdrawn', updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$application_id]);

    if ($result) {
        $_SESSION['success'] = 'your application has been withdrawn successfully.';
    } else {
        $_SESSION['error'] = 'failed to withdraw application.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
}

// redirect back to my applications page
header('Location: my-applications.php');
exit; 