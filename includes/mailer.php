<?php
// mail configuration and helper functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// function to send emails
function send_email($to, $subject, $body, $altBody = '', $attachments = []) {
    // for development/testing purposes, we'll just log emails instead of sending them
    if (true) { // set to false in production
        log_email($to, $subject, $body);
        return true;
    }
    
    // load phpmailer via composer autoload
    require 'vendor/autoload.php';
    
    // create a new phpmailer instance
    $mail = new PHPMailer(true);
    
    try {
        // server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com'; // replace with your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'user@example.com'; // replace with your email
        $mail->Password = 'password'; // replace with your password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // recipients
        $mail->setFrom('noreply@devjobs.com', 'DevJobs Portal');
        $mail->addAddress($to);
        
        // content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ? $altBody : strip_tags($body);
        
        // add attachments if any
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("mailer error: {$mail->ErrorInfo}");
        return false;
    }
}

// function to log emails for development
function log_email($to, $subject, $body) {
    $log_dir = __DIR__ . '/../logs';
    
    // create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/email_log.txt';
    $date = date('Y-m-d H:i:s');
    $content = "====================\n";
    $content .= "Date: $date\n";
    $content .= "To: $to\n";
    $content .= "Subject: $subject\n";
    $content .= "Body:\n$body\n";
    $content .= "====================\n\n";
    
    file_put_contents($log_file, $content, FILE_APPEND);
    return true;
}

// email templates
function get_application_received_email($user_name, $job_title, $company_name, $job_id) {
    $subject = "Application Received: $job_title at $company_name";
    
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <div style="background-color: #4a82c2; padding: 20px; text-align: center; color: white;">
            <h1 style="margin: 0;">Application Received</h1>
        </div>
        <div style="padding: 20px; border: 1px solid #ddd; background-color: #f9f9f9;">
            <p>Hello ' . htmlspecialchars($user_name) . ',</p>
            <p>Thank you for applying to the <strong>' . htmlspecialchars($job_title) . '</strong> position at <strong>' . htmlspecialchars($company_name) . '</strong>.</p>
            <p>Your application has been successfully submitted and is now under review. The recruiter will review your application and get back to you if they feel you are a good fit for the role.</p>
            <p>You can track the status of your application in your <a href="' . get_site_url() . '/user-dashboard.php">dashboard</a>.</p>
            <div style="margin-top: 30px; text-align: center;">
                <a href="' . get_site_url() . '/job-details.php?id=' . $job_id . '" style="display: inline-block; padding: 10px 20px; background-color: #4a82c2; color: white; text-decoration: none; border-radius: 4px;">View Job Details</a>
            </div>
            <p style="margin-top: 30px;">Good luck with your application!</p>
            <p>Best regards,<br>DevJobs Team</p>
        </div>
        <div style="padding: 15px; text-align: center; font-size: 12px; color: #666;">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; ' . date('Y') . ' DevJobs Portal. All rights reserved.</p>
        </div>
    </div>';
    
    return ['subject' => $subject, 'body' => $body];
}

function get_application_status_update_email($user_name, $job_title, $company_name, $status, $job_id, $notes = '') {
    $status_text = ucfirst($status);
    $subject = "Application Status Update: $status_text - $job_title at $company_name";
    
    $status_message = '';
    $status_color = '#4a82c2';
    
    switch ($status) {
        case 'reviewing':
            $status_message = 'Your application is now being reviewed by the hiring team.';
            $status_color = '#ffa500';
            break;
        case 'interview':
            $status_message = 'Congratulations! You have been selected for an interview. The recruiter will contact you with further details.';
            $status_color = '#28a745';
            break;
        case 'accepted':
            $status_message = 'Congratulations! Your application has been accepted. The recruiter will contact you with further details about the next steps.';
            $status_color = '#28a745';
            break;
        case 'rejected':
            $status_message = 'We regret to inform you that your application was not selected for this position. We encourage you to apply for other roles that match your skills and experience.';
            $status_color = '#dc3545';
            break;
        default:
            $status_message = 'Your application status has been updated to "' . $status_text . '".';
    }
    
    $notes_html = '';
    if (!empty($notes)) {
        $notes_html = '
        <div style="margin-top: 20px; padding: 15px; background-color: #f3f3f3; border-left: 4px solid ' . $status_color . ';">
            <h3 style="margin-top: 0;">Notes from the recruiter:</h3>
            <p>' . nl2br(htmlspecialchars($notes)) . '</p>
        </div>';
    }
    
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <div style="background-color: ' . $status_color . '; padding: 20px; text-align: center; color: white;">
            <h1 style="margin: 0;">Application Status Update</h1>
        </div>
        <div style="padding: 20px; border: 1px solid #ddd; background-color: #f9f9f9;">
            <p>Hello ' . htmlspecialchars($user_name) . ',</p>
            <p>Your application for <strong>' . htmlspecialchars($job_title) . '</strong> at <strong>' . htmlspecialchars($company_name) . '</strong> has been updated.</p>
            <div style="margin: 20px 0; padding: 15px; background-color: #f3f3f3; border-left: 4px solid ' . $status_color . ';">
                <h3 style="margin-top: 0;">Status: ' . $status_text . '</h3>
                <p>' . $status_message . '</p>
            </div>
            ' . $notes_html . '
            <div style="margin-top: 30px; text-align: center;">
                <a href="' . get_site_url() . '/user-dashboard.php" style="display: inline-block; padding: 10px 20px; background-color: #4a82c2; color: white; text-decoration: none; border-radius: 4px;">View in Dashboard</a>
            </div>
            <p style="margin-top: 30px;">Best regards,<br>DevJobs Team</p>
        </div>
        <div style="padding: 15px; text-align: center; font-size: 12px; color: #666;">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; ' . date('Y') . ' DevJobs Portal. All rights reserved.</p>
        </div>
    </div>';
    
    return ['subject' => $subject, 'body' => $body];
}

function get_new_application_email_to_recruiter($recruiter_name, $applicant_name, $job_title, $job_id, $application_id) {
    $subject = "New Application: $job_title";
    
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <div style="background-color: #4a82c2; padding: 20px; text-align: center; color: white;">
            <h1 style="margin: 0;">New Application Received</h1>
        </div>
        <div style="padding: 20px; border: 1px solid #ddd; background-color: #f9f9f9;">
            <p>Hello ' . htmlspecialchars($recruiter_name) . ',</p>
            <p>You have received a new application for the <strong>' . htmlspecialchars($job_title) . '</strong> position.</p>
            <div style="margin: 20px 0; padding: 15px; background-color: #f3f3f3; border-left: 4px solid #4a82c2;">
                <h3 style="margin-top: 0;">Applicant: ' . htmlspecialchars($applicant_name) . '</h3>
                <p>Review this application to determine if this candidate is a good fit for the role.</p>
            </div>
            <div style="margin-top: 30px; text-align: center;">
                <a href="' . get_site_url() . '/application-details-recruiter.php?id=' . $application_id . '" style="display: inline-block; padding: 10px 20px; background-color: #4a82c2; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">View Application</a>
                <a href="' . get_site_url() . '/view-applications.php?job_id=' . $job_id . '" style="display: inline-block; padding: 10px 20px; background-color: #5a6268; color: white; text-decoration: none; border-radius: 4px;">View All Applications</a>
            </div>
            <p style="margin-top: 30px;">Best regards,<br>DevJobs Team</p>
        </div>
        <div style="padding: 15px; text-align: center; font-size: 12px; color: #666;">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; ' . date('Y') . ' DevJobs Portal. All rights reserved.</p>
        </div>
    </div>';
    
    return ['subject' => $subject, 'body' => $body];
}

// utility function to get site url
function get_site_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    $path = rtrim($path, '/includes');
    return "$protocol://$host$path";
} 