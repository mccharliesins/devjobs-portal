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
        <h1>Companies</h1>
        <div class="company-filters">
            <form action="" method="GET">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search companies...">
                    <button type="submit">Search</button>
                </div>
            </form>
        </div>
        <div class="company-list">
            <p>No companies available yet.</p>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 