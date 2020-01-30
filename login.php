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
$username = '';
$errors = [];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // get form data
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // validate form data
    if (empty($username)) {
        $errors[] = 'username is required';
    }

    if (empty($password)) {
        $errors[] = 'password is required';
    }

    // if no errors, check credentials
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                // redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $errors[] = 'invalid username or password';
            }
        } catch (PDOException $e) {
            $errors[] = 'login failed. please try again.';
        }
    }
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <h1>Login</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <p><?php echo htmlspecialchars($_SESSION['success']); ?></p>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
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
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <p class="text-center">Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 