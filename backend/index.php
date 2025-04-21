<?php
header('Content-Type: application/json');
require_once 'db.php';

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$uri = dirname($_SERVER['SCRIPT_NAME']); 
//echo json_encode([$uri]);

// Router
if ($method === 'POST' && $uri === '/orders') {
    require 'routes/post-order.php';
} elseif ($method === 'GET' && $uri === '/analytics') {
    require 'routes/get-analytics.php';
} elseif ($method === 'GET' && $uri === '/recommendations') {
    require 'routes/get-recommendations.php';
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
