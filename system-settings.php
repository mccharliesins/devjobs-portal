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
    $_SESSION['error'] = 'please login to access the admin dashboard.';
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

// check if settings table exists, create if not
try {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'settings'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // create settings table
        $conn->exec("CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // insert default settings
        $default_settings = [
            ['site_name', 'DevJobs Portal', 'general'],
            ['site_description', 'A job board for developers and tech professionals', 'general'],
            ['admin_email', 'admin@devjobs.com', 'general'],
            ['jobs_per_page', '10', 'jobs'],
            ['allow_registration', '1', 'users'],
            ['require_email_verification', '0', 'users'],
            ['allow_job_applications', '1', 'jobs'],
            ['maintenance_mode', '0', 'system']
        ];
        
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
        
        foreach ($default_settings as $setting) {
            $stmt->execute($setting);
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'error setting up settings table: ' . $e->getMessage();
}

// handle form submission to update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        // begin transaction
        $conn->beginTransaction();
        
        // prepare statement for updating settings
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        // update each setting
        foreach ($_POST as $key => $value) {
            // skip the submit button and non-setting fields
            if ($key === 'update_settings' || strpos($key, 'setting_') !== 0) {
                continue;
            }
            
            // extract actual setting key (remove 'setting_' prefix)
            $setting_key = substr($key, 8);
            
            // update setting
            $stmt->execute([$value, $setting_key]);
        }
        
        // commit transaction
        $conn->commit();
        
        $_SESSION['success'] = 'system settings updated successfully.';
    } catch (PDOException $e) {
        // rollback in case of error
        $conn->rollBack();
        $_SESSION['error'] = 'error updating settings: ' . $e->getMessage();
    }
    
    // reload page to reflect changes
    header('Location: system-settings.php');
    exit;
}

// fetch all settings grouped by category
try {
    $stmt = $conn->prepare("SELECT * FROM settings ORDER BY setting_group, setting_key");
    $stmt->execute();
    $all_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // organize settings by group
    $settings_by_group = [];
    foreach ($all_settings as $setting) {
        $group = $setting['setting_group'];
        if (!isset($settings_by_group[$group])) {
            $settings_by_group[$group] = [];
        }
        $settings_by_group[$group][] = $setting;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch settings: ' . $e->getMessage();
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>System Settings</h1>
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

        <form method="POST" action="" class="settings-form">
            <?php if (isset($settings_by_group) && !empty($settings_by_group)): ?>
                <?php foreach ($settings_by_group as $group => $settings): ?>
                    <div class="settings-group">
                        <h2><?php echo ucfirst(htmlspecialchars($group)); ?> Settings</h2>
                        
                        <?php foreach ($settings as $setting): ?>
                            <div class="form-group">
                                <label for="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($setting['setting_key']))); ?>
                                </label>
                                
                                <?php if (in_array($setting['setting_key'], ['allow_registration', 'require_email_verification', 'allow_job_applications', 'maintenance_mode'])): ?>
                                    <select name="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>" id="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>">
                                        <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Yes</option>
                                        <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>No</option>
                                    </select>
                                <?php elseif (strpos($setting['setting_key'], 'email') !== false): ?>
                                    <input type="email" name="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>" 
                                           id="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                <?php elseif (is_numeric($setting['setting_value'])): ?>
                                    <input type="number" name="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>" 
                                           id="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                <?php elseif (strlen($setting['setting_value']) > 100): ?>
                                    <textarea name="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>" 
                                              id="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>" 
                                           id="setting_<?php echo htmlspecialchars($setting['setting_key']); ?>" 
                                           value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="form-actions">
                    <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                </div>
            <?php else: ?>
                <p class="no-results">no settings found. please contact the system administrator.</p>
            <?php endif; ?>
        </form>

        <div class="tools-section">
            <h2>System Tools</h2>
            <div class="tool-buttons">
                <a href="clear-cache.php" class="btn btn-secondary" onclick="return confirm('are you sure you want to clear the system cache?');">Clear Cache</a>
                <a href="backup-system.php" class="btn btn-secondary">Backup System</a>
                <a href="view-error-logs.php" class="btn btn-secondary">View Error Logs</a>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 