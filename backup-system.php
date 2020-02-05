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

// create backup directory if it doesn't exist
$backup_dir = __DIR__ . '/backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// log the backup action
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

// create backup
try {
    // timestamp for backup filename
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = "{$backup_dir}/devjobs_backup_{$timestamp}.sql";
    
    // get all tables
    $tables = [];
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // start output buffering
    ob_start();
    
    // add database creation statement
    echo "-- DevJobs Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- ------------------------------------------------------\n\n";
    echo "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`;\n";
    echo "USE `" . DB_NAME . "`;\n\n";

    // export each table structure and data
    foreach ($tables as $table) {
        // get table structure
        $stmt = $conn->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo "-- Table structure for table `{$table}`\n";
        echo "DROP TABLE IF EXISTS `{$table}`;\n";
        echo $row[1] . ";\n\n";
        
        // get table data
        $stmt = $conn->query("SELECT * FROM `{$table}`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            echo "-- Dumping data for table `{$table}`\n";
            echo "INSERT INTO `{$table}` VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $rowValues = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $rowValues[] = 'NULL';
                    } else {
                        $rowValues[] = $conn->quote($value);
                    }
                }
                $values[] = '(' . implode(', ', $rowValues) . ')';
            }
            
            echo implode(",\n", $values) . ";\n\n";
        }
    }
    
    // get buffer contents and end buffering
    $sql = ob_get_clean();
    
    // write to file
    if (file_put_contents($backup_file, $sql)) {
        // log success
        log_action('backup_system', 'success', "backup file created: {$backup_file}");
        $_SESSION['success'] = 'system backup created successfully.';
    } else {
        // log failure
        log_action('backup_system', 'failed', "could not write to backup file: {$backup_file}");
        $_SESSION['error'] = 'failed to create backup file.';
    }
} catch (Exception $e) {
    log_action('backup_system', 'failed', $e->getMessage());
    $_SESSION['error'] = 'failed to create system backup: ' . $e->getMessage();
}

// redirect back to system settings
header('Location: system-settings.php');
exit;
?> 