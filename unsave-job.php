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
    $_SESSION['error'] = 'please login to remove saved jobs.';
    header('Location: login.php');
    exit;
}

// check if this is a post request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'invalid request method.';
    header('Location: user-dashboard.php?tab=saved');
    exit;
}

// get job id from post data
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;

if ($job_id <= 0) {
    $_SESSION['error'] = 'invalid job id.';
    header('Location: user-dashboard.php?tab=saved');
    exit;
}

// verify that the saved job exists and belongs to the user
try {
    $stmt = $conn->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    $result = $stmt->execute([$_SESSION['user_id'], $job_id]);

    if ($result && $stmt->rowCount() > 0) {
        $_SESSION['success'] = 'job removed from saved jobs.';
    } else {
        $_SESSION['error'] = 'job not found in your saved jobs.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
}

// redirect back to user dashboard
header('Location: user-dashboard.php?tab=saved');
exit; 