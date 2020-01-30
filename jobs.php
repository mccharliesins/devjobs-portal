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
        <h1>Browse Jobs</h1>
        <div class="job-filters">
            <form action="" method="GET">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search jobs...">
                    <button type="submit">Search</button>
                </div>
                <div class="filter-options">
                    <select name="job_type">
                        <option value="">All Types</option>
                        <option value="full-time">Full Time</option>
                        <option value="part-time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="internship">Internship</option>
                    </select>
                    <select name="location">
                        <option value="">All Locations</option>
                        <!-- locations will be populated dynamically -->
                    </select>
                </div>
            </form>
        </div>
        <div class="job-list">
            <p>No jobs available yet.</p>
        </div>
    </div>
</main>

<?php
// include footer
require_once 'includes/footer.php';
?> 