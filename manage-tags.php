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

// handle form submission for adding/editing tags
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // add new tag
    if (isset($_POST['add_tag'])) {
        $name = trim($_POST['name']);
        
        if (empty($name)) {
            $_SESSION['error'] = 'tag name is required.';
        } else {
            try {
                // generate slug from name
                $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9\-\s\.]/', '', $name)));
                
                // check if slug already exists
                $stmt = $conn->prepare("SELECT id FROM tags WHERE slug = ?");
                $stmt->execute([$slug]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error'] = 'a tag with this name already exists.';
                } else {
                    // insert new tag
                    $stmt = $conn->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
                    $stmt->execute([$name, $slug]);
                    
                    $_SESSION['success'] = 'tag added successfully.';
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = 'error adding tag: ' . $e->getMessage();
            }
        }
        
        // redirect to refresh the page
        header('Location: manage-tags.php');
        exit;
    }
    
    // edit tag
    if (isset($_POST['edit_tag'])) {
        $tag_id = (int)$_POST['tag_id'];
        $name = trim($_POST['name']);
        
        if (empty($name)) {
            $_SESSION['error'] = 'tag name is required.';
        } else {
            try {
                // generate slug from name
                $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9\-\s\.]/', '', $name)));
                
                // check if slug already exists for another tag
                $stmt = $conn->prepare("SELECT id FROM tags WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $tag_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error'] = 'a tag with this name already exists.';
                } else {
                    // update tag
                    $stmt = $conn->prepare("UPDATE tags SET name = ?, slug = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $tag_id]);
                    
                    $_SESSION['success'] = 'tag updated successfully.';
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = 'error updating tag: ' . $e->getMessage();
            }
        }
        
        // redirect to refresh the page
        header('Location: manage-tags.php');
        exit;
    }
    
    // delete tag
    if (isset($_POST['delete_tag'])) {
        $tag_id = (int)$_POST['tag_id'];
        
        try {
            // check if tag has jobs
            $stmt = $conn->prepare("SELECT COUNT(*) FROM job_tags WHERE tag_id = ?");
            $stmt->execute([$tag_id]);
            $job_count = $stmt->fetchColumn();
            
            if ($job_count > 0) {
                $_SESSION['error'] = 'cannot delete tag: it is associated with ' . $job_count . ' jobs.';
            } else {
                // delete tag
                $stmt = $conn->prepare("DELETE FROM tags WHERE id = ?");
                $stmt->execute([$tag_id]);
                
                $_SESSION['success'] = 'tag deleted successfully.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'error deleting tag: ' . $e->getMessage();
        }
        
        // redirect to refresh the page
        header('Location: manage-tags.php');
        exit;
    }
}

// get tags with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// search filter
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    // prepare base query
    $query = "SELECT t.*, COUNT(jt.job_id) as job_count 
              FROM tags t
              LEFT JOIN job_tags jt ON t.id = jt.tag_id
              WHERE 1=1";
    $count_query = "SELECT COUNT(*) FROM tags WHERE 1=1";
    $params = [];
    
    // add search condition if search term provided
    if (!empty($search)) {
        $query .= " AND t.name LIKE ?";
        $count_query .= " AND name LIKE ?";
        $params[] = "%$search%";
    }
    
    // add group by and order
    $query .= " GROUP BY t.id ORDER BY t.name ASC LIMIT $offset, $per_page";
    
    // get total count
    $stmt = $conn->prepare($count_query);
    if (!empty($search)) {
        $stmt->execute(["%$search%"]);
    } else {
        $stmt->execute();
    }
    $total_tags = $stmt->fetchColumn();
    
    // get tags for current page
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $tags = $stmt->fetchAll();
    
    // calculate total pages
    $total_pages = ceil($total_tags / $per_page);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch tags: ' . $e->getMessage();
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>Manage Job Tags</h1>
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

        <div class="admin-panels">
            <div class="admin-panel">
                <h2>Add New Tag</h2>
                <form method="POST" action="" class="form">
                    <div class="form-group">
                        <label for="name">Tag Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_tag" class="btn btn-primary">Add Tag</button>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <h2>Tags</h2>
                
                <div class="filters">
                    <form method="GET" action="" class="filter-form">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search tags" 
                                  value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="manage-tags.php" class="btn btn-secondary">Clear</a>
                    </form>
                </div>

                <div class="tags-list">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Jobs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($tags) && !empty($tags)): ?>
                                <?php foreach ($tags as $tag): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tag['id']); ?></td>
                                        <td><?php echo htmlspecialchars($tag['name']); ?></td>
                                        <td><?php echo htmlspecialchars($tag['slug']); ?></td>
                                        <td><?php echo htmlspecialchars($tag['job_count']); ?></td>
                                        <td class="actions">
                                            <button type="button" class="btn btn-small btn-secondary edit-tag-btn" 
                                                   data-id="<?php echo $tag['id']; ?>"
                                                   data-name="<?php echo htmlspecialchars($tag['name']); ?>">
                                                Edit
                                            </button>
                                            
                                            <?php if ($tag['job_count'] == 0): ?>
                                                <form method="POST" action="" class="inline-form" onsubmit="return confirm('are you sure you want to delete this tag?');">
                                                    <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                                    <button type="submit" name="delete_tag" class="btn btn-small btn-danger">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-small btn-danger" disabled title="Cannot delete: tag has associated jobs">Delete</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-results">no tags found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (isset($total_pages) && $total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-small">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                              class="btn btn-small <?php echo $i === $page ? 'btn-active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-small">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Tag Modal -->
    <div id="editTagModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Tag</h2>
            <form method="POST" action="" class="form">
                <input type="hidden" id="edit_tag_id" name="tag_id">
                <div class="form-group">
                    <label for="edit_name">Tag Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="edit_tag" class="btn btn-primary">Update Tag</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Get the modal
var modal = document.getElementById("editTagModal");

// Get the button that opens the modal
var btns = document.getElementsByClassName("edit-tag-btn");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];

// When the user clicks the button, open the modal 
for (var i = 0; i < btns.length; i++) {
    btns[i].onclick = function() {
        var id = this.getAttribute("data-id");
        var name = this.getAttribute("data-name");
        
        document.getElementById("edit_tag_id").value = id;
        document.getElementById("edit_name").value = name;
        
        modal.style.display = "block";
    }
}

// When the user clicks on <span> (x), close the modal
span.onclick = function() {
    modal.style.display = "none";
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>

<?php
// include footer
require_once 'includes/footer.php';
?> 