<?php
// set default timezone
date_default_timezone_set('UTC');

/**
 * send an email notification
 * 
 * @param string $to email recipient
 * @param string $subject email subject
 * @param string $message email content
 * @param array $headers additional headers
 * @return bool true if email was sent, false otherwise
 */
function send_email($to, $subject, $message, $headers = []) {
    // set default headers if not provided
    if (empty($headers)) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: DevJobs <noreply@devjobs.com>',
            'X-Mailer: PHP/' . phpversion()
        ];
    }
    
    // convert headers array to string
    $headers_str = implode("\r\n", $headers);
    
    // in a production environment, you would use mail() function
    // for local development, we'll just log the email
    if ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
        // log email for local development
        $log_file = __DIR__ . '/../logs/email_log.txt';
        $dir = dirname($log_file);
        
        // create logs directory if it doesn't exist
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // prepare log message
        $log_message = "==== " . date('Y-m-d H:i:s') . " ====\n";
        $log_message .= "To: $to\n";
        $log_message .= "Subject: $subject\n";
        $log_message .= "Headers: $headers_str\n";
        $log_message .= "Message: $message\n\n";
        
        // write to log file
        file_put_contents($log_file, $log_message, FILE_APPEND);
        
        return true;
    } else {
        // in production, use mail()
        return mail($to, $subject, $message, $headers_str);
    }
}

/**
 * send application notification to job seeker
 * 
 * @param int $user_id user ID of job seeker
 * @param int $job_id job ID
 * @param string $job_title job title
 * @param string $company company name
 * @param string $status application status
 * @return bool true if email was sent, false otherwise
 */
function send_application_notification_to_applicant($user_id, $job_id, $job_title, $company, $status = 'pending') {
    global $conn;
    
    // get user email
    try {
        $stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        $to = $user['email'];
        $name = $user['first_name'] ?? 'there';
        
        $subject = "your application for $job_title at $company";
        
        // create html message
        $message = "
        <html>
        <head>
            <title>your job application</title>
        </head>
        <body>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                <div style='background-color: #4a6cf7; padding: 20px; color: white; text-align: center;'>
                    <h1>devjobs</h1>
                </div>
                <div style='background-color: #f9f9f9; padding: 20px; border-radius: 5px;'>
                    <h2>hello $name,</h2>
                    <p>we're excited to confirm that your application for <strong>$job_title</strong> at <strong>$company</strong> has been successfully submitted.</p>
                    <p>your application status is currently: <strong>$status</strong></p>
                    <p>you can track the status of your application by visiting your dashboard.</p>
                    <p><a href='http://devjobs.com/user-dashboard.php' style='display: inline-block; background-color: #4a6cf7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>view your application</a></p>
                    <p>we wish you the best of luck with your application!</p>
                </div>
                <div style='padding: 20px; text-align: center; color: #666; font-size: 12px;'>
                    <p>© 2020 devjobs. all rights reserved.</p>
                    <p>if you have any questions, please contact our support team at support@devjobs.com</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return send_email($to, $subject, $message);
    } catch (PDOException $e) {
        error_log("database error: " . $e->getMessage());
        return false;
    }
}

/**
 * send application notification to recruiter
 * 
 * @param int $job_id job ID 
 * @param string $job_title job title
 * @param string $applicant_name applicant name
 * @param string $applicant_email applicant email
 * @return bool true if email was sent, false otherwise
 */
function send_application_notification_to_recruiter($job_id, $job_title, $applicant_name, $applicant_email) {
    global $conn;
    
    // get recruiter email (job owner)
    try {
        $stmt = $conn->prepare("
            SELECT u.email, u.first_name 
            FROM jobs j
            JOIN users u ON j.user_id = u.id
            WHERE j.id = ?
        ");
        $stmt->execute([$job_id]);
        $recruiter = $stmt->fetch();
        
        if (!$recruiter) {
            return false;
        }
        
        $to = $recruiter['email'];
        $name = $recruiter['first_name'] ?? 'there';
        
        $subject = "new application: $job_title";
        
        // create html message
        $message = "
        <html>
        <head>
            <title>new job application</title>
        </head>
        <body>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                <div style='background-color: #4a6cf7; padding: 20px; color: white; text-align: center;'>
                    <h1>devjobs</h1>
                </div>
                <div style='background-color: #f9f9f9; padding: 20px; border-radius: 5px;'>
                    <h2>hello $name,</h2>
                    <p>you have received a new application for the position: <strong>$job_title</strong>.</p>
                    <p><strong>applicant details:</strong></p>
                    <ul>
                        <li>name: $applicant_name</li>
                        <li>email: $applicant_email</li>
                    </ul>
                    <p>you can review this application in your recruiter dashboard.</p>
                    <p><a href='http://devjobs.com/recruiter-dashboard.php' style='display: inline-block; background-color: #4a6cf7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>go to dashboard</a></p>
                </div>
                <div style='padding: 20px; text-align: center; color: #666; font-size: 12px;'>
                    <p>© 2020 devjobs. all rights reserved.</p>
                    <p>if you have any questions, please contact our support team at support@devjobs.com</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return send_email($to, $subject, $message);
    } catch (PDOException $e) {
        error_log("database error: " . $e->getMessage());
        return false;
    }
}

/**
 * send application status update notification
 * 
 * @param int $application_id application ID
 * @param string $status new status
 * @return bool true if email was sent, false otherwise
 */
function send_application_status_update($application_id, $status) {
    global $conn;
    
    // get application and user details
    try {
        $stmt = $conn->prepare("
            SELECT a.*, j.title as job_title, j.company, u.email, u.first_name
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            JOIN users u ON a.user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();
        
        if (!$application) {
            return false;
        }
        
        $to = $application['email'];
        $name = $application['first_name'] ?? 'there';
        $job_title = $application['job_title'];
        $company = $application['company'];
        
        $subject = "update on your $job_title application";
        
        // prepare status message based on status
        $status_message = "";
        switch ($status) {
            case 'reviewing':
                $status_message = "your application is now being reviewed by the hiring team.";
                break;
            case 'interview':
                $status_message = "congratulations! the company wants to schedule an interview with you.";
                break;
            case 'offer':
                $status_message = "great news! the company has extended an offer to you.";
                break;
            case 'rejected':
                $status_message = "unfortunately, the company has decided to proceed with other candidates.";
                break;
            default:
                $status_message = "your application status has been updated to: $status";
        }
        
        // create html message
        $message = "
        <html>
        <head>
            <title>application status update</title>
        </head>
        <body>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                <div style='background-color: #4a6cf7; padding: 20px; color: white; text-align: center;'>
                    <h1>devjobs</h1>
                </div>
                <div style='background-color: #f9f9f9; padding: 20px; border-radius: 5px;'>
                    <h2>hello $name,</h2>
                    <p>we have an update regarding your application for <strong>$job_title</strong> at <strong>$company</strong>.</p>
                    <p><strong>new status: $status</strong></p>
                    <p>$status_message</p>
                    <p>you can check the full details of your application in your dashboard.</p>
                    <p><a href='http://devjobs.com/user-dashboard.php' style='display: inline-block; background-color: #4a6cf7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>view application</a></p>
                </div>
                <div style='padding: 20px; text-align: center; color: #666; font-size: 12px;'>
                    <p>© 2020 devjobs. all rights reserved.</p>
                    <p>if you have any questions, please contact our support team at support@devjobs.com</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return send_email($to, $subject, $message);
    } catch (PDOException $e) {
        error_log("database error: " . $e->getMessage());
        return false;
    }
} 