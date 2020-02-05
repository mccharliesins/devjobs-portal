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

// handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // user deletion
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        try {
            // don't allow deleting own account
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['error'] = 'you cannot delete your own admin account.';
            } else {
                // delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = 'user deleted successfully.';
                } else {
                    $_SESSION['error'] = 'failed to delete user.';
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'error deleting user: ' . $e->getMessage();
        }
        
        // reload page to reflect changes
        header('Location: manage-users.php');
        exit;
    }
    
    // update user role
    if (isset($_POST['update_role'])) {
        $user_id = $_POST['user_id'];
        $new_role = $_POST['role'];
        
        if (!in_array($new_role, ['admin', 'recruiter', 'user'])) {
            $_SESSION['error'] = 'invalid role specified.';
            header('Location: manage-users.php');
            exit;
        }
        
        try {
            // don't allow changing own role from admin
            if ($user_id == $_SESSION['user_id'] && $new_role != 'admin') {
                $_SESSION['error'] = 'you cannot change your own admin role.';
            } else {
                // update user role
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = 'user role updated successfully.';
                } else {
                    $_SESSION['error'] = 'no changes made to user role.';
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'error updating user role: ' . $e->getMessage();
        }
        
        // reload page to reflect changes
        header('Location: manage-users.php');
        exit;
    }
}

// get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// search filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

try {
    // prepare base query
    $query = "SELECT * FROM users WHERE 1=1";
    $count_query = "SELECT COUNT(*) FROM users WHERE 1=1";
    $params = [];
    
    // add search condition if search term provided
    if (!empty($search)) {
        $query .= " AND (username LIKE ? OR email LIKE ?)";
        $count_query .= " AND (username LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // add role filter if specified
    if (!empty($role_filter)) {
        $query .= " AND role = ?";
        $count_query .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    // add order and limit
    $query .= " ORDER BY created_at DESC LIMIT $offset, $per_page";
    
    // get total count
    $stmt = $conn->prepare($count_query);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    
    // get users for current page
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // calculate total pages
    $total_pages = ceil($total_users / $per_page);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch users: ' . $e->getMessage();
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>Manage Users</h1>
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

        <div class="user-filters">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search username or email" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="recruiter" <?php echo $role_filter === 'recruiter' ? 'selected' : ''; ?>>Recruiter</option>
                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>Job Seeker</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="manage-users.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <div class="users-list">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($users) && !empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($user['created_at']))); ?></td>
                                <td class="actions">
                                    <form method="POST" action="" class="inline-form">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="role" class="role-select">
                                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Job Seeker</option>
                                            <option value="recruiter" <?php echo $user['role'] === 'recruiter' ? 'selected' : ''; ?>>Recruiter</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <button type="submit" name="update_role" class="btn btn-small btn-secondary">Update</button>
                                    </form>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" action="" class="inline-form" onsubmit="return confirm('are you sure you want to delete this user?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-small btn-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-results">no users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="btn btn-small">Previous</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
                       class="btn btn-small <?php echo $i === $page ? 'btn-active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" class="btn btn-small">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 