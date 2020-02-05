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
        $_SESSION['error'] = 'you do not have permission to access this page.';
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'database error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// get error logs (from PHP error log and our custom logs)
$error_logs = [];

// check for clear logs action
if (isset($_GET['clear']) && $_GET['clear'] === 'all') {
    try {
        // clear all log files in the logs directory
        $files = glob($logs_dir . '/*.log');
        foreach ($files as $file) {
            file_put_contents($file, '');
        }
        
        // log the action
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
            $stmt->execute([$_SESSION['user_id'], 'clear_error_logs', 'success', "Cleared all error logs"]);
        } catch (PDOException $e) {
            // silently fail - don't want to break the main functionality if logging fails
        }
        
        $_SESSION['success'] = 'all error logs cleared successfully.';
        header('Location: view-error-logs.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'failed to clear error logs: ' . $e->getMessage();
    }
}

// collect all log files
try {
    $log_files = glob($logs_dir . '/*.log');
    
    // add application error log if it exists
    if (file_exists($logs_dir . '/error.log')) {
        array_unshift($log_files, $logs_dir . '/error.log');
    }
    
    // read each log file
    foreach ($log_files as $log_file) {
        $log_name = basename($log_file);
        $log_size = filesize($log_file);
        $log_modified = date('Y-m-d H:i:s', filemtime($log_file));
        
        // read the last 50 lines of the log file
        $lines = [];
        $file = new SplFileObject($log_file);
        $file->seek(PHP_INT_MAX); // seek to end of file
        $total_lines = $file->key(); // get total lines
        
        // reset pointer to read the last 50 lines (or all if less than 50)
        $start_line = max(0, $total_lines - 50);
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $line = $file->fgets();
            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }
        
        $error_logs[] = [
            'name' => $log_name,
            'path' => $log_file,
            'size' => $log_size,
            'modified' => $log_modified,
            'lines' => $lines,
            'total_lines' => $total_lines
        ];
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'error reading log files: ' . $e->getMessage();
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>Error Logs</h1>
            <div class="actions">
                <a href="system-settings.php" class="btn btn-secondary">Back to System Settings</a>
                <a href="view-error-logs.php?clear=all" class="btn btn-danger" 
                   onclick="return confirm('are you sure you want to clear all error logs? this cannot be undone.');">
                    Clear All Logs
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <p><?php echo htmlspecialchars($_SESSION['success']); ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <p><?php echo htmlspecialchars($_SESSION['error']); ?></p>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="logs-section">
            <?php if (empty($error_logs)): ?>
                <div class="no-logs">
                    <p>no error logs found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($error_logs as $log): ?>
                    <div class="log-file">
                        <div class="log-header">
                            <h2><?php echo htmlspecialchars($log['name']); ?></h2>
                            <div class="log-meta">
                                <span class="log-size">Size: <?php echo number_format($log['size'] / 1024, 2); ?> KB</span>
                                <span class="log-modified">Modified: <?php echo htmlspecialchars($log['modified']); ?></span>
                                <span class="log-lines">Lines: <?php echo number_format($log['total_lines']); ?></span>
                            </div>
                        </div>
                        
                        <div class="log-content">
                            <?php if (empty($log['lines'])): ?>
                                <p class="empty-log">this log file is empty.</p>
                            <?php else: ?>
                                <pre class="log-lines"><?php 
                                    foreach ($log['lines'] as $line) {
                                        echo htmlspecialchars($line);
                                    }
                                ?></pre>
                                <?php if ($log['total_lines'] > 50): ?>
                                    <p class="log-notice">showing last 50 lines of <?php echo number_format($log['total_lines']); ?> total lines.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="admin-logs-section">
            <h2>Admin Activity Logs</h2>
            
            <?php
            // fetch admin logs
            try {
                // check if admin_logs table exists
                $stmt = $conn->prepare("SHOW TABLES LIKE 'admin_logs'");
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // fetch the last 20 admin logs
                    $stmt = $conn->prepare("
                        SELECT al.*, u.username 
                        FROM admin_logs al
                        JOIN users u ON al.admin_id = u.id
                        ORDER BY al.created_at DESC
                        LIMIT 20
                    ");
                    $stmt->execute();
                    $admin_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($admin_logs)):
                    ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admin_logs as $log): ?>
                                <tr class="log-status-<?php echo htmlspecialchars($log['status']); ?>">
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($log['username']); ?></td>
                                    <td><?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['status']); ?></td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="no-results">no admin logs found.</p>
                    <?php endif;
                } else {
                    echo '<p class="no-results">admin logs table not found.</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="error">error fetching admin logs: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 