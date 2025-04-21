<?php
header('Content-Type: application/json');
require_once 'db.php';

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/Advanced-Sales-System/backend', '', $uri); 

// Router
if ($method === 'POST' && $uri === '/orders') {
    require 'post-order.php';
} elseif ($method === 'GET' && $uri === '/analytics') {
    require 'get-analytics.php';
} elseif ($method === 'GET' && $uri === '/recommendations') {
    require 'get-recommendations.php';
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
