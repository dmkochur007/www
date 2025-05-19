<?php
// Common functions used across the application

// Function to sanitize user input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to show success messages
function showSuccess($message) {
    return "<div class='alert success'>{$message}</div>";
}

// Function to show error messages
function showError($message) {
    return "<div class='alert error'>{$message}</div>";
}

// Function to redirect with a message
function redirectWithMessage($location, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: {$location}");
    exit;
}
?>