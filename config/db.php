<?php
$host = "127.0.0.1";
$username = "root";
$password = "access";
$database = "dorsu_bulletin_db";
$port = 3307;

$conn = mysqli_init();

if ($conn === false) {
    die("Database initialization failed.");
}

$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

if (!$conn->real_connect($host, $username, $password, $database, $port)) {
    die("Database connection failed: " . mysqli_connect_error());
}

$conn->set_charset("utf8mb4");
?>