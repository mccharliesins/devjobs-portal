<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'please login to access this page.';
    header('Location: login.php');
    exit;
}

try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        $_SESSION['error'] = 'you do not have permission to access this page.';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// apply database changes
try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Read and execute categories SQL file
    $categories_sql = file_get_contents('db/categories.sql');
    $conn->exec($categories_sql);
    
    // Read and execute tags SQL file
    $tags_sql = file_get_contents('db/tags.sql');
    $conn->exec($tags_sql);
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = 'categories and tags have been set up successfully.';
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    $_SESSION['error'] = 'error setting up categories and tags: ' . $e->getMessage();
}

// redirect to admin dashboard
header('Location: admin-dashboard.php');
exit;
?> 