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
    $_SESSION['error'] = 'please login to access admin functions.';
    header('Location: login.php');
    exit;
}

// check if user is an admin
try {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        $_SESSION['error'] = 'you do not have permission to perform this action.';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// create cache directory if it doesn't exist
$cache_dir = __DIR__ . '/cache';
if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// log the cache clearing action
function log_action($action, $status, $details = '') {
    global $conn;
    
    try {
        // create admin_logs table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // insert log entry
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, status, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $action, $status, $details]);
    } catch (PDOException $e) {
        // silently fail - don't want to break the main functionality if logging fails
    }
}

// clear the cache
try {
    $files_deleted = 0;
    $errors = [];
    
    // scan cache directory for files
    if (file_exists($cache_dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        // delete each file and empty directory
        foreach ($files as $file) {
            $path = $file->getRealPath();
            
            try {
                if ($file->isDir()) {
                    rmdir($path);
                } else {
                    unlink($path);
                    $files_deleted++;
                }
            } catch (Exception $e) {
                $errors[] = "failed to delete {$path}: " . $e->getMessage();
            }
        }
    }
    
    // log the action
    if (empty($errors)) {
        log_action('clear_cache', 'success', "deleted {$files_deleted} cached files");
        $_SESSION['success'] = "cache cleared successfully. {$files_deleted} files removed.";
    } else {
        $error_details = implode('; ', $errors);
        log_action('clear_cache', 'partial', "deleted {$files_deleted} files with errors: {$error_details}");
        $_SESSION['warning'] = "cache partially cleared. {$files_deleted} files removed with some errors.";
    }
} catch (Exception $e) {
    log_action('clear_cache', 'failed', $e->getMessage());
    $_SESSION['error'] = 'failed to clear cache: ' . $e->getMessage();
}

// redirect back to system settings
header('Location: system-settings.php');
exit;
?> 