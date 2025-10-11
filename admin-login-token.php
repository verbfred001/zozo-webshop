<?php
session_start();
header('Content-Type: application/json');

// Simple token validation for admin login
require_once($_SERVER['DOCUMENT_ROOT'] . "/zozo-includes/config.php");

$expected_token = ADMIN_MAGIC_TOKEN; // Change this to a secure token

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';

    if ($token === $expected_token) {
        $_SESSION['admin_logged_in'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
