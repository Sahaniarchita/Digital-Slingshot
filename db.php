<?php
$host = "localhost";
$user = "live";
$pass = "Live@123";
$dbname = "cipla2020";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>