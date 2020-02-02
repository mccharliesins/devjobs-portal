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
    $_SESSION['error'] = 'please login to view email logs.';
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

// read email logs
$logs = [];
$log_file = 'logs/email_log.txt';

if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_entries = explode('==== ', $log_content);
    
    // remove the first empty entry if it exists
    if (empty($log_entries[0])) {
        array_shift($log_entries);
    }
    
    // parse log entries
    foreach ($log_entries as $entry) {
        if (empty($entry)) continue;
        
        // add the separator back for proper parsing
        $entry = '==== ' . $entry;
        
        // extract log parts
        preg_match('/==== (.*?) ====\n/', $entry, $date_matches);
        preg_match('/To: (.*?)\n/', $entry, $to_matches);
        preg_match('/Subject: (.*?)\n/', $entry, $subject_matches);
        preg_match('/Headers: (.*?)\n/', $entry, $headers_matches);
        preg_match('/Message: (.*?)(\n\n|$)/s', $entry, $message_matches);
        
        if (isset($date_matches[1]) && isset($to_matches[1]) && isset($subject_matches[1])) {
            $logs[] = [
                'date' => $date_matches[1],
                'to' => $to_matches[1],
                'subject' => $subject_matches[1],
                'headers' => isset($headers_matches[1]) ? $headers_matches[1] : '',
                'message' => isset($message_matches[1]) ? $message_matches[1] : ''
            ];
        }
    }
    
    // sort logs by date (newest first)
    usort($logs, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>Email Logs</h1>
            <a href="admin-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
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

        <div class="email-logs">
            <h2>Recent Email Notifications</h2>
            
            <?php if (empty($logs)): ?>
                <p>No email logs found.</p>
            <?php else: ?>
                <ul class="email-list">
                    <?php foreach ($logs as $log): ?>
                        <li class="email-item">
                            <div class="email-subject"><?php echo htmlspecialchars($log['subject']); ?></div>
                            <div class="email-recipient">To: <?php echo htmlspecialchars($log['to']); ?></div>
                            <div class="email-date"><?php echo htmlspecialchars($log['date']); ?></div>
                            <div class="email-actions">
                                <button class="btn btn-sm btn-secondary toggle-message">Show Message</button>
                            </div>
                            <div class="email-body" style="display: none;">
                                <?php echo nl2br(htmlspecialchars($log['message'])); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle email message visibility
    const toggleButtons = document.querySelectorAll('.toggle-message');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const emailBody = this.parentElement.nextElementSibling;
            const isVisible = emailBody.style.display !== 'none';
            
            // Toggle visibility
            emailBody.style.display = isVisible ? 'none' : 'block';
            this.textContent = isVisible ? 'Show Message' : 'Hide Message';
        });
    });
});
</script>

<?php
// include footer
require_once 'includes/footer.php';
?> 