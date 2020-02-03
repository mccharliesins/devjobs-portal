<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // number of companies per page
$offset = ($page - 1) * $per_page;

// search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$industry_filter = isset($_GET['industry']) ? trim($_GET['industry']) : '';

// fetch companies with filters
try {
    $params = [];
    $where_clauses = [];
    
    if (!empty($search)) {
        $where_clauses[] = "name LIKE ? OR description LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($industry_filter)) {
        $where_clauses[] = "industry = ?";
        $params[] = $industry_filter;
    }
    
    $where_sql = empty($where_clauses) ? "" : "WHERE " . implode(' AND ', $where_clauses);
    
    // count query
    $count_sql = "SELECT COUNT(*) as total FROM companies $where_sql";
    $stmt = $conn->prepare($count_sql);
    $stmt->execute($params);
    $total_companies = $stmt->fetch()['total'];
    $total_pages = ceil($total_companies / $per_page);
    
    // fetch companies
    $sql = "SELECT * FROM companies $where_sql ORDER BY name ASC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    
    // add limit and offset to params
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt->execute($params);
    $companies = $stmt->fetchAll();
    
    // fetch available industries for filter
    $stmt = $conn->prepare("SELECT DISTINCT industry FROM companies WHERE industry IS NOT NULL ORDER BY industry");
    $stmt->execute();
    $industries = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to fetch companies: ' . $e->getMessage();
    $companies = [];
    $total_pages = 0;
    $industries = [];
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1>Companies</h1>
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'recruiter'): ?>
                <a href="create-company.php" class="btn btn-primary">Add Your Company</a>
            <?php endif; ?>
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
        
        <div class="company-filters">
            <form action="" method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search companies..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-select">
                        <select name="industry">
                            <option value="">All Industries</option>
                            <?php foreach ($industries as $industry): ?>
                                <option value="<?php echo htmlspecialchars($industry); ?>" <?php echo $industry_filter === $industry ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($industry); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <?php if (!empty($search) || !empty($industry_filter)): ?>
                        <a href="companies.php" class="btn btn-secondary">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <?php if (empty($companies)): ?>
            <div class="no-results">
                <p>No companies found matching your criteria.</p>
            </div>
        <?php else: ?>
            <div class="companies-grid">
                <?php foreach ($companies as $company): ?>
                    <div class="company-card">
                        <div class="company-card-header">
                            <?php if (!empty($company['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="<?php echo htmlspecialchars($company['name']); ?> logo" class="company-logo">
                            <?php else: ?>
                                <div class="company-logo-placeholder">
                                    <?php echo strtoupper(substr($company['name'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <h2><?php echo htmlspecialchars($company['name']); ?></h2>
                            
                            <?php if (!empty($company['industry'])): ?>
                                <span class="company-industry badge"><?php echo htmlspecialchars($company['industry']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="company-card-body">
                            <?php if (!empty($company['description'])): ?>
                                <p class="company-description">
                                    <?php echo htmlspecialchars(substr($company['description'], 0, 150)) . (strlen($company['description']) > 150 ? '...' : ''); ?>
                                </p>
                            <?php else: ?>
                                <p class="company-description">No description available.</p>
                            <?php endif; ?>
                            
                            <div class="company-meta">
                                <?php if (!empty($company['headquarters'])): ?>
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($company['headquarters']); ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($company['company_size'])): ?>
                                    <span><i class="fas fa-users"></i> <?php echo htmlspecialchars($company['company_size']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="company-card-footer">
                            <a href="company-profile.php?id=<?php echo $company['id']; ?>" class="btn btn-primary btn-sm">View Profile</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industry_filter); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industry_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industry_filter); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 