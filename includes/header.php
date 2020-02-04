<?php
// check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevJobs - Find Your Dream Developer Job</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">DevJobs</a>
                </div>
                <nav class="main-nav">
                    <ul>
                        <li><a href="jobs.php">Browse Jobs</a></li>
                        <li><a href="companies.php">Companies</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'recruiter'): ?>
                                <li><a href="recruiter-dashboard.php">Dashboard</a></li>
                                <li><a href="create-job.php">Post a Job</a></li>
                            <?php else: ?>
                                <li><a href="user-dashboard.php">Dashboard</a></li>
                            <?php endif; ?>
                            
                            <li class="user-menu">
                                <a href="#" class="user-menu-trigger">
                                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                                    <span class="dropdown-icon">▼</span>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a href="profile.php">My Profile</a></li>
                                    <?php if ($_SESSION['role'] === 'user'): ?>
                                        <li><a href="saved-jobs.php">Saved Jobs</a></li>
                                    <?php endif; ?>
                                    <li><a href="notification-settings.php">Notification Settings</a></li>
                                    <li><a href="logout.php">Logout</a></li>
                                </ul>
                            </li>
                            
                            <li class="notification-icon">
                                <a href="#" id="notification-trigger">
                                    <span class="icon">🔔</span>
                                    <?php 
                                        // count unread notifications
                                        try {
                                            $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                                            $stmt->execute([$_SESSION['user_id']]);
                                            $unread_count = $stmt->fetchColumn();
                                            
                                            if ($unread_count > 0): 
                                    ?>
                                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                                    <?php 
                                            endif;
                                        } catch (PDOException $e) {
                                            // silently fail, no need to show errors in the header
                                        }
                                    ?>
                                </a>
                                <div class="notification-dropdown" id="notification-dropdown">
                                    <div class="notification-header">
                                        <h3>Notifications</h3>
                                        <a href="#" class="mark-all-read">Mark all as read</a>
                                    </div>
                                    <ul class="notification-list">
                                        <?php
                                            try {
                                                $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                                                $stmt->execute([$_SESSION['user_id']]);
                                                $notifications = $stmt->fetchAll();
                                                
                                                if (empty($notifications)): 
                                        ?>
                                                    <li class="no-notifications">No notifications yet</li>
                                        <?php 
                                                else:
                                                    foreach ($notifications as $notification): 
                                        ?>
                                                        <li class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                                                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                                            <div class="notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
                                                        </li>
                                        <?php 
                                                    endforeach;
                                                endif;
                                            } catch (PDOException $e) {
                                                // silently fail
                                            }
                                        ?>
                                    </ul>
                                    <div class="notification-footer">
                                        <a href="notifications.php">View all notifications</a>
                                    </div>
                                </div>
                            </li>
                        <?php else: ?>
                            <li><a href="login.php">Login</a></li>
                            <li><a href="register.php" class="btn btn-primary">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="mobile-menu-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </header>
    
    <script>
        // Toggle mobile menu
        document.querySelector('.mobile-menu-toggle').addEventListener('click', function() {
            document.querySelector('.main-nav').classList.toggle('active');
            this.classList.toggle('active');
        });
        
        // User dropdown menu
        document.querySelector('.user-menu-trigger')?.addEventListener('click', function(e) {
            e.preventDefault();
            this.parentNode.classList.toggle('active');
        });
        
        // Notification dropdown
        document.getElementById('notification-trigger')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('notification-dropdown').classList.toggle('show');
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            // User menu
            if (!e.target.closest('.user-menu') && document.querySelector('.user-menu.active')) {
                document.querySelector('.user-menu.active').classList.remove('active');
            }
            
            // Notification dropdown
            if (!e.target.closest('.notification-icon') && document.getElementById('notification-dropdown')?.classList.contains('show')) {
                document.getElementById('notification-dropdown').classList.remove('show');
            }
        });
        
        // Mark notification as read when clicked
        document.querySelectorAll('.notification-item').forEach(function(item) {
            item.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-id');
                const url = this.getAttribute('data-url');
                
                // Send AJAX request to mark as read
                fetch('mark-notification-read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'notification_id=' + notificationId
                })
                .then(response => {
                    // Remove unread class
                    this.classList.remove('unread');
                    
                    // Update notification count
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        const count = parseInt(badge.textContent) - 1;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.textContent = count;
                        }
                    }
                    
                    // Redirect if URL is provided
                    if (url) {
                        window.location.href = url;
                    }
                });
            });
        });
        
        // Mark all notifications as read
        document.querySelector('.mark-all-read')?.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Send AJAX request to mark all as read
            fetch('mark-all-notifications-read.php', {
                method: 'POST'
            })
            .then(response => {
                // Remove all unread classes
                document.querySelectorAll('.notification-item.unread').forEach(function(item) {
                    item.classList.remove('unread');
                });
                
                // Remove notification badge
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.remove();
                }
            });
        });
    </script>
    
<?php
// Helper function to format time ago for notifications
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks != 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}
?>
</body>
</html> 