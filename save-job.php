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
    $_SESSION['error'] = 'please login to save jobs.';
    header('Location: login.php');
    exit;
}

// check if this is a post request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'invalid request method.';
    header('Location: jobs.php');
    exit;
}

// get job id from post data
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;

if ($job_id <= 0) {
    $_SESSION['error'] = 'invalid job id.';
    header('Location: jobs.php');
    exit;
}

// verify that the job exists
try {
    $stmt = $conn->prepare("SELECT id FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        $_SESSION['error'] = 'job not found.';
        header('Location: jobs.php');
        exit;
    }

    // check if job is already saved
    $stmt = $conn->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ?");
    $stmt->execute([$_SESSION['user_id'], $job_id]);
    $saved_job = $stmt->fetch();

    if ($saved_job) {
        $_SESSION['error'] = 'you have already saved this job.';
    } else {
        // save the job
        $stmt = $conn->prepare("INSERT INTO saved_jobs (user_id, job_id, created_at) VALUES (?, ?, NOW())");
        $result = $stmt->execute([$_SESSION['user_id'], $job_id]);

        if ($result) {
            $_SESSION['success'] = 'job saved successfully.';
        } else {
            $_SESSION['error'] = 'failed to save job.';
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
}

// redirect back to the job details page
$redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'jobs.php';
header('Location: ' . $redirect_to);
exit; 