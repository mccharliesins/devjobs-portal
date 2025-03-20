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

// handle form submission for adding/editing categories
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // add new category
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $_SESSION['error'] = 'category name is required.';
        } else {
            try {
                // generate slug from name
                $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9\-\s]/', '', $name)));
                
                // check if slug already exists
                $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
                $stmt->execute([$slug]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error'] = 'a category with this name already exists.';
                } else {
                    // insert new category
                    $stmt = $conn->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $slug, $description]);
                    
                    $_SESSION['success'] = 'category added successfully.';
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = 'error adding category: ' . $e->getMessage();
            }
        }
        
        // redirect to refresh the page
        header('Location: manage-categories.php');
        exit;
    }
    
    // edit category
    if (isset($_POST['edit_category'])) {
        $category_id = (int)$_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $_SESSION['error'] = 'category name is required.';
        } else {
            try {
                // generate slug from name
                $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9\-\s]/', '', $name)));
                
                // check if slug already exists for another category
                $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                $stmt->execute([$slug, $category_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error'] = 'a category with this name already exists.';
                } else {
                    // update category
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $category_id]);
                    
                    $_SESSION['success'] = 'category updated successfully.';
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = 'error updating category: ' . $e->getMessage();
            }
        }
        
        // redirect to refresh the page
        header('Location: manage-categories.php');
        exit;
    }
    
    // delete category
    if (isset($_POST['delete_category'])) {
        $category_id = (int)$_POST['category_id'];
        
        try {
            // check if category has jobs
            $stmt = $conn->prepare("SELECT COUNT(*) FROM job_categories WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $job_count = $stmt->fetchColumn();
            
            if ($job_count > 0) {
                $_SESSION['error'] = 'cannot delete category: it is associated with ' . $job_count . ' jobs.';
            } else {
                // delete category
                $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                
                $_SESSION['success'] = 'category deleted successfully.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'error deleting category: ' . $e->getMessage();
        }
        
        // redirect to refresh the page
        header('Location: manage-categories.php');
        exit;
    }
}

// get categories with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// search filter
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    // prepare base query
    $query = "SELECT c.*, COUNT(jc.job_id) as job_count 
              FROM categories c
              LEFT JOIN job_categories jc ON c.id = jc.category_id
              WHERE 1=1";
    $count_query = "SELECT COUNT(*) FROM categories WHERE 1=1";
    $params = [];
    
    // add search condition if search term provided
    if (!empty($search)) {
        $query .= " AND (c.name LIKE ? OR c.description LIKE ?)";
        $count_query .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // add group by and order
    $query .= " GROUP BY c.id ORDER BY c.name ASC LIMIT $offset, $per_page";
    
    // get total count
    $stmt = $conn->prepare($count_query);
    if (!empty($search)) {
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt->execute();
    }
    $total_categories = $stmt->fetchColumn();
    
    // get categories for current page
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
    
    // calculate total pages
    $total_pages = ceil($total_categories / $per_page);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch categories: ' . $e->getMessage();
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="dashboard-header">
            <h1>Manage Job Categories</h1>
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
                <h2>Add New Category</h2>
                <form method="POST" action="" class="form">
                    <div class="form-group">
                        <label for="name">Category Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>

            <div class="admin-panel">
                <h2>Categories</h2>
                
                <div class="filters">
                    <form method="GET" action="" class="filter-form">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search categories" 
                                  value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="manage-categories.php" class="btn btn-secondary">Clear</a>
                    </form>
                </div>

                <div class="categories-list">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Description</th>
                                <th>Jobs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($categories) && !empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['id']); ?></td>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($category['description'], 0, 50) . (strlen($category['description']) > 50 ? '...' : '')); ?></td>
                                        <td><?php echo htmlspecialchars($category['job_count']); ?></td>
                                        <td class="actions">
                                            <button type="button" class="btn btn-small btn-secondary edit-category-btn" 
                                                   data-id="<?php echo $category['id']; ?>"
                                                   data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                   data-description="<?php echo htmlspecialchars($category['description']); ?>">
                                                Edit
                                            </button>
                                            
                                            <?php if ($category['job_count'] == 0): ?>
                                                <form method="POST" action="" class="inline-form" onsubmit="return confirm('are you sure you want to delete this category?');">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" name="delete_category" class="btn btn-small btn-danger">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-small btn-danger" disabled title="Cannot delete: category has associated jobs">Delete</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-results">no categories found.</td>
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

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Edit Category</h2>
            <form method="POST" action="" class="form">
                <input type="hidden" id="edit_category_id" name="category_id">
                <div class="form-group">
                    <label for="edit_name">Category Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Get the modal
var modal = document.getElementById("editCategoryModal");

// Get the button that opens the modal
var btns = document.getElementsByClassName("edit-category-btn");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];

// When the user clicks the button, open the modal 
for (var i = 0; i < btns.length; i++) {
    btns[i].onclick = function() {
        var id = this.getAttribute("data-id");
        var name = this.getAttribute("data-name");
        var description = this.getAttribute("data-description");
        
        document.getElementById("edit_category_id").value = id;
        document.getElementById("edit_name").value = name;
        document.getElementById("edit_description").value = description;
        
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