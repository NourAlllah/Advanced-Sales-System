<?php

require_once 'db.php';

$input = json_decode(file_get_contents('php://input'), true);

$errors = [];
$savedOrders = [];

function validateOrder(array $order): array
{
    $errors = [];
    if (empty($order['product_id'])) {
        $errors[] = 'Missing or empty product_id';
    }
    if (!isset($order['quantity']) || !is_numeric($order['quantity']) || $order['quantity'] <= 0) {
        $errors[] = 'Invalid quantity';
    }
    if (!isset($order['price']) || !is_numeric($order['price']) || $order['price'] < 0) {
        $errors[] = 'Invalid price';
    }
    if (empty($order['date']) || !strtotime($order['date'])) {
        $errors[] = 'Invalid or missing date (must be a valid date string)';
    }
    return $errors;
}

function productExists(PDO $db, string $productId): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId);
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

function processOrder(PDO $db, array $order, array &$errors, array &$savedOrders): void
{
    $itemErrors = validateOrder($order);
    if (!empty($itemErrors)) {
        $errors[] = ['product_id' => $order['product_id'] ?? 'N/A', 'errors' => $itemErrors];
        return; // Exit the function for this order
    }

    $product_id = $order['product_id'];
    $quantity = (int) $order['quantity'];
    $price = (float) $order['price'];
    $date = $order['date'];

    if (!productExists($db, $product_id)) {
        $errors[] = ['product_id' => $product_id, 'errors' => ['Invalid product_id: Product not found']];
        return; // Exit the function for this order
    }

    try {
        $stmt = $db->prepare("INSERT INTO orders (product_id, quantity, price, date) VALUES (:product_id, :quantity, :price, :date)");
        $stmt->execute([
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':price' => $price,
            ':date' => $date
        ]);
        $savedOrders[] = ['product_id' => $product_id, 'message' => 'Order item saved successfully'];
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save order item: ' . $e->getMessage()]);
        exit;
    }
}

if (is_array($input)) {
    foreach ($input as $order) {
        processOrder($db, $order, $errors, $savedOrders);
    }
} else { 
    processOrder($db, $input, $errors, $savedOrders);
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['errors' => $errors]);
} else {
    echo json_encode(['messages' => $savedOrders]);
}

?>