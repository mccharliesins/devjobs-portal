<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// check if user is logged in and is a recruiter
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'recruiter') {
    $_SESSION['error'] = 'you must be logged in as a recruiter to access this page.';
    header('Location: login.php');
    exit;
}

// check if recruiter already has a company
try {
    $stmt = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch();
    $edit_mode = (bool)$company;
} catch (PDOException $e) {
    $_SESSION['error'] = 'failed to check existing company: ' . $e->getMessage();
    header('Location: companies.php');
    exit;
}

// fetch industry options for dropdown
$industries = [
    'Information Technology',
    'Software Development',
    'Internet/E-commerce',
    'Fintech',
    'Healthcare',
    'Education',
    'Marketing/Advertising',
    'Consulting',
    'Retail',
    'Manufacturing',
    'Finance/Banking',
    'Media/Entertainment',
    'Telecommunications',
    'Travel/Tourism',
    'Real Estate',
    'Food & Beverage',
    'Other'
];

// company size options
$company_sizes = [
    '1-10',
    '11-50',
    '51-200',
    '201-500',
    '501-1000',
    '1001-5000',
    '5001-10000',
    '10000+'
];

// form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate form data
    $name = trim($_POST['name'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $founded_year = !empty($_POST['founded_year']) ? (int)$_POST['founded_year'] : null;
    $company_size = trim($_POST['company_size'] ?? '');
    $headquarters = trim($_POST['headquarters'] ?? '');
    $social_linkedin = trim($_POST['social_linkedin'] ?? '');
    $social_twitter = trim($_POST['social_twitter'] ?? '');
    $social_facebook = trim($_POST['social_facebook'] ?? '');
    
    $errors = [];
    
    // validate required fields
    if (empty($name)) {
        $errors[] = 'company name is required.';
    }
    
    // validate website format if provided
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'website must be a valid URL.';
    }
    
    // validate founded year if provided
    if ($founded_year !== null && ($founded_year < 1800 || $founded_year > date('Y'))) {
        $errors[] = 'founded year must be between 1800 and current year.';
    }
    
    // validate social media URLs if provided
    if (!empty($social_linkedin) && !filter_var($social_linkedin, FILTER_VALIDATE_URL)) {
        $errors[] = 'linkedin URL must be valid.';
    }
    
    if (!empty($social_twitter) && !filter_var($social_twitter, FILTER_VALIDATE_URL)) {
        $errors[] = 'twitter URL must be valid.';
    }
    
    if (!empty($social_facebook) && !filter_var($social_facebook, FILTER_VALIDATE_URL)) {
        $errors[] = 'facebook URL must be valid.';
    }
    
    // handle logo upload
    $logo_path = $company['logo'] ?? null; // keep existing logo by default
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $upload_error_messages = [
                UPLOAD_ERR_INI_SIZE => 'the uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'the uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
                UPLOAD_ERR_PARTIAL => 'the uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'no file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'a PHP extension stopped the file upload.'
            ];
            $errors[] = 'logo upload error: ' . ($upload_error_messages[$_FILES['logo']['error']] ?? 'unknown error');
        } else {
            // validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['logo']['type'], $allowed_types)) {
                $errors[] = 'logo must be a jpeg, png, or gif image.';
            } else {
                // process the upload
                $upload_dir = 'uploads/company_logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // generate unique filename
                $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('company_') . '.' . $file_extension;
                $logo_path = $upload_dir . $filename;
                
                // move the uploaded file
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
                    $errors[] = 'failed to save logo. please try again.';
                    $logo_path = $company['logo'] ?? null; // keep existing logo on error
                }
            }
        }
    }
    
    // if no errors, save company
    if (empty($errors)) {
        try {
            if ($edit_mode) {
                // update existing company
                $stmt = $conn->prepare("
                    UPDATE companies
                    SET name = ?, website = ?, description = ?, industry = ?, 
                        founded_year = ?, company_size = ?, headquarters = ?,
                        social_linkedin = ?, social_twitter = ?, social_facebook = ?,
                        logo = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $params = [
                    $name, $website, $description, $industry,
                    $founded_year, $company_size, $headquarters,
                    $social_linkedin, $social_twitter, $social_facebook,
                    $logo_path, $company['id']
                ];
                
                $result = $stmt->execute($params);
                
                if ($result) {
                    $_SESSION['success'] = 'company profile has been updated successfully.';
                    header('Location: company-profile.php?id=' . $company['id']);
                    exit;
                } else {
                    $errors[] = 'failed to update company profile.';
                }
            } else {
                // create new company
                $stmt = $conn->prepare("
                    INSERT INTO companies (name, user_id, website, description, industry, 
                        founded_year, company_size, headquarters,
                        social_linkedin, social_twitter, social_facebook, logo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $params = [
                    $name, $_SESSION['user_id'], $website, $description, $industry,
                    $founded_year, $company_size, $headquarters,
                    $social_linkedin, $social_twitter, $social_facebook,
                    $logo_path
                ];
                
                $result = $stmt->execute($params);
                
                if ($result) {
                    $company_id = $conn->lastInsertId();
                    $_SESSION['success'] = 'company profile has been created successfully.';
                    header('Location: company-profile.php?id=' . $company_id);
                    exit;
                } else {
                    $errors[] = 'failed to create company profile.';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'database error: ' . $e->getMessage();
        }
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <div class="page-header">
            <h1><?php echo $edit_mode ? 'Edit' : 'Create'; ?> Company Profile</h1>
            <a href="<?php echo $edit_mode ? 'company-profile.php?id=' . $company['id'] : 'companies.php'; ?>" class="btn btn-secondary">
                <?php echo $edit_mode ? 'View Profile' : 'Back to Companies'; ?>
            </a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" class="form company-form">
                <div class="form-section">
                    <h2>Basic Information</h2>
                    
                    <div class="form-group">
                        <label for="name">Company Name *</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($company['name'] ?? $_POST['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="logo">Company Logo</label>
                        <?php if (!empty($company['logo'])): ?>
                            <div class="current-logo">
                                <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Current Logo" class="logo-preview">
                                <p>Current logo</p>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif">
                        <small>Recommended size: 200x200px. Max file size: 2MB. Formats: JPEG, PNG, GIF.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($company['website'] ?? $_POST['website'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="6"><?php echo htmlspecialchars($company['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="industry">Industry</label>
                        <select id="industry" name="industry">
                            <option value="">Select Industry</option>
                            <?php foreach ($industries as $industry_option): ?>
                                <option value="<?php echo htmlspecialchars($industry_option); ?>" <?php echo ($company['industry'] ?? $_POST['industry'] ?? '') === $industry_option ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($industry_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="founded_year">Founded Year</label>
                            <input type="number" id="founded_year" name="founded_year" min="1800" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($company['founded_year'] ?? $_POST['founded_year'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="company_size">Company Size</label>
                            <select id="company_size" name="company_size">
                                <option value="">Select Size</option>
                                <?php foreach ($company_sizes as $size_option): ?>
                                    <option value="<?php echo htmlspecialchars($size_option); ?>" <?php echo ($company['company_size'] ?? $_POST['company_size'] ?? '') === $size_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($size_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="headquarters">Headquarters</label>
                        <input type="text" id="headquarters" name="headquarters" value="<?php echo htmlspecialchars($company['headquarters'] ?? $_POST['headquarters'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h2>Social Media</h2>
                    
                    <div class="form-group">
                        <label for="social_linkedin">LinkedIn URL</label>
                        <input type="url" id="social_linkedin" name="social_linkedin" value="<?php echo htmlspecialchars($company['social_linkedin'] ?? $_POST['social_linkedin'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="social_twitter">Twitter URL</label>
                        <input type="url" id="social_twitter" name="social_twitter" value="<?php echo htmlspecialchars($company['social_twitter'] ?? $_POST['social_twitter'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="social_facebook">Facebook URL</label>
                        <input type="url" id="social_facebook" name="social_facebook" value="<?php echo htmlspecialchars($company['social_facebook'] ?? $_POST['social_facebook'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Update' : 'Create'; ?> Company Profile</button>
                    <a href="<?php echo $edit_mode ? 'company-profile.php?id=' . $company['id'] : 'companies.php'; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 