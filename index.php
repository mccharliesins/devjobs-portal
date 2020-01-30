<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// include header
require_once 'includes/header.php';
?>

<main>
    <div class="container">
        <h1>Welcome to DevJobs</h1>
        <p>Your gateway to developer opportunities</p>
        
        <div class="featured-jobs">
            <h2>Featured Jobs</h2>
            <div class="job-grid">
                <!-- job cards will be added here later -->
                <p>No featured jobs available yet.</p>
            </div>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 