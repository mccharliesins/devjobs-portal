<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// initialize variables
$username = $email = '';
$errors = [];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // validate form data
    if (empty($username)) {
        $errors[] = 'username is required';
    }

    if (empty($email)) {
        $errors[] = 'email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'invalid email format';
    }

    if (empty($password)) {
        $errors[] = 'password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'password must be at least 6 characters';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'passwords do not match';
    }

    // check if username or email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = 'username or email already exists';
        }
    }

    // if no errors, insert user into database
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password]);
            
            // redirect to login page
            $_SESSION['success'] = 'registration successful! please login.';
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'registration failed. please try again.';
        }
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <h1>Register</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary">Register</button>
        </form>

        <p class="text-center">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 