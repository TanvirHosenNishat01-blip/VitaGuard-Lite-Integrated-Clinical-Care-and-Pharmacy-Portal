<?php
$host     = "localhost";
$username = "root";
$password = "";
$database = "vitaguard_db";

// Create Connection
$conn = new mysqli($host, $username, $password, $database);

// Check Connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set UTF-8 Character Set
$conn->set_charset("utf8mb4");
?>