<?php
// Database credentials
$DB_HOST = 'localhost';
$DB_USER = 'zozo-user';
$DB_NAME = 'u44232p148476_webshop';
$DB_PASS = 'PN_10Gev@ngenis';

// Create a global $mysqli connection
global $mysqli;
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check for connection errors
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Set the character set to UTF-8
$mysqli->set_charset("utf8");
