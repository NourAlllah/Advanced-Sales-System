<?php

require_once 'db.php';
date_default_timezone_set('Africa/Cairo');

$input = json_decode(file_get_contents('php://input'), true);

$errors = [];
$savedOrders = [];
$enrichedOrders = [];

function validateOrder(array $order): array
{
    $errors = [];
    if (empty($order['product_id'])) {
        $errors[] = 'Missing or empty product_id';
    }
    if (!isset($order['quantity']) || !is_numeric($order['quantity']) || $order['quantity'] <= 0) {
        $errors[] = 'Invalid quantity';
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

function getProductPrice(PDO $db, string $productId): float
{
    $stmt = $db->prepare("SELECT price FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) ($product['price'] ?? 0);
}

function processOrder(PDO $db, array $order, array &$errors, array &$savedOrders, array &$enrichedOrders): void
{
    $itemErrors = validateOrder($order);
    if (!empty($itemErrors)) {
        $errors[] = ['product_id' => $order['product_id'] ?? 'N/A', 'errors' => $itemErrors];
        return;
    }

    $product_id = $order['product_id'];
    $quantity = (int) $order['quantity'];
    // Retrieve the price from the products table
    $price = getProductPrice($db, $product_id);
    // Get the current date and time for the order
    $date = date('Y-m-d H:i:s');

    // Check if the product exists in the database
    if (!productExists($db, $product_id)) {
        $errors[] = ['product_id' => $product_id, 'errors' => ['Invalid product_id: Product not found']];
        return;
    }

    try {
        // Insert the order into the database
        $stmt = $db->prepare("INSERT INTO orders (product_id, quantity, price, date) VALUES (:product_id, :quantity, :price, :date)");
        $stmt->execute([
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':price' => $price,
            ':date' => $date
        ]);
        $savedOrders[] = ['product_id' => $product_id, 'message' => 'Order item saved successfully'];

        // Get the product name for the enriched order
        $stmt = $db->prepare("SELECT name FROM products WHERE id = :id");
        $stmt->execute([':id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        // Prepare the enriched order
        $enrichedOrders[] = [
            'product_id' => $product_id,
            'product_name' => $product['name'],
            'quantity' => $quantity,
            'price' => $price,
            'date' => $date
        ];

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save order item: ' . $e->getMessage()]);
        exit;
    }
}

if (isset($input[0]) && is_array($input[0])) {
    foreach ($input as $order) {
        processOrder($db, $order, $errors, $savedOrders, $enrichedOrders);
    }
} else if (is_array($input)) {
    processOrder($db, $input, $errors, $savedOrders, $enrichedOrders);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input format. Expecting JSON object or array of objects.']);
    exit;
}

// 🔄 Send orders and analytics to WebSocket server
if (!empty($enrichedOrders)) {
    $socket = fsockopen("127.0.0.1", 9000, $errno, $errstr, 2);
    if ($socket) {
        fwrite($socket, json_encode([
            'type' => 'new_order',
            'order' => $enrichedOrders
        ]));
        fclose($socket);
    }

    // ✅ FETCH ANALYTICS SAFELY USING CURL
    $ch = curl_init("http://localhost/get-analytics.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $analytics = curl_exec($ch);
    curl_close($ch);

    if ($analytics !== false) {
        $analyticsData = json_decode($analytics, true);
        if ($analyticsData) {
            $analyticsSocket = fsockopen("127.0.0.1", 9000, $errno, $errstr, 2);
            if ($analyticsSocket) {
                fwrite($analyticsSocket, json_encode([
                    'type' => 'analytics_update',
                    'analytics' => $analyticsData
                ]));
                fclose($analyticsSocket);
            }
        } else {
            error_log("Failed to decode analytics JSON.");
        }
    } else {
        error_log("Failed to fetch analytics from analytics.php");
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['errors' => $errors]);
} else {
    echo json_encode(['messages' => $savedOrders]);
}

?>
