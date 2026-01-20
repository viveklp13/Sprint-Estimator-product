<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'udathu_sprint_estimator');
define('DB_USER', 'udathu_sprint_estimator');
define('DB_PASS', 'J9f9NmZ7i+');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
?>