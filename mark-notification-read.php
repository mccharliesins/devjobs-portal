<?php
// include database connection
require_once 'db.php';

// set default timezone
date_default_timezone_set('UTC');

// start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// check if notification ID is provided
if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Notification ID is required']);
    exit;
}

$notification_id = (int)$_POST['notification_id'];

try {
    // verify that the notification belongs to the user
    $stmt = $conn->prepare("
        SELECT id 
        FROM notifications 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notification_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'Notification not found or not authorized']);
        exit;
    }
    
    // mark notification as read
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$notification_id]);
    
    if ($result) {
        http_response_code(200); // OK
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to mark notification as read']);
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 