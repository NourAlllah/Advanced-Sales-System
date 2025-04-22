<?php

$input = json_decode(file_get_contents('php://input'), true);

$errors = [];

if (empty($input['product_id'])) {
    $errors[] = 'Missing or empty product_id';
}

if (!isset($input['quantity']) || !is_numeric($input['quantity']) || $input['quantity'] <= 0) {
    $errors[] = 'Invalid quantity';
}

if (!isset($input['price']) || !is_numeric($input['price']) || $input['price'] < 0) {
    $errors[] = 'Invalid price';
}

if (empty($input['date']) || !strtotime($input['date'])) {
    $errors[] = 'Invalid or missing date (must be a valid date string)';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['errors' => $errors]);
    exit;
}

$product_id = $input['product_id'];
$quantity = (int) $input['quantity'];
$price = (float) $input['price'];
$date = $input['date']; 

try {
    $stmt = $db->prepare("INSERT INTO orders (product_id, quantity, price, date) VALUES (:product_id, :quantity, :price, :date)");
    $stmt->execute([
        ':product_id' => $product_id,
        ':quantity' => $quantity,
        ':price' => $price,
        ':date' => $date
    ]);

    echo json_encode(['message' => 'Order saved successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save order: ' . $e->getMessage()]);
}
