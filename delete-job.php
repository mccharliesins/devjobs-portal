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
    $_SESSION['error'] = 'please login to delete jobs.';
    header('Location: login.php');
    exit;
}

// check if user is a recruiter
try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'recruiter') {
        $_SESSION['error'] = 'you do not have permission to delete jobs.';
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

// get job id from post data
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;

if ($job_id <= 0) {
    $_SESSION['error'] = 'invalid job id.';
    header('Location: recruiter-dashboard.php');
    exit;
}

// verify that the job belongs to the recruiter
try {
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND recruiter_id = ?");
    $stmt->execute([$job_id, $_SESSION['user_id']]);
    $job = $stmt->fetch();

    if (!$job) {
        $_SESSION['error'] = 'job not found or you do not have permission to delete it.';
        header('Location: recruiter-dashboard.php');
        exit;
    }

    // delete the job (this will cascade delete applications due to foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
    $result = $stmt->execute([$job_id]);

    if ($result) {
        $_SESSION['success'] = 'job deleted successfully.';
    } else {
        $_SESSION['error'] = 'failed to delete job.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
}

// redirect back to recruiter dashboard
header('Location: recruiter-dashboard.php');
exit; 