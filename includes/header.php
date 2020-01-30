<?php
// check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevJobs - Job Board for Developers</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">
                <a href="/">DevJobs</a>
            </div>
            <ul class="navbar-nav">
                <li><a href="/">Home</a></li>
                <li><a href="/jobs.php">Jobs</a></li>
                <li><a href="/companies.php">Companies</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="/dashboard.php">Dashboard</a></li>
                    <li><a href="/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="/login.php">Login</a></li>
                    <li><a href="/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
</body>
</html> 