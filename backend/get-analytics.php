<?php

require_once 'db.php';

function getTotalRevenue(PDO $db): float {
    $stmt = $db->query("SELECT SUM(price * quantity) FROM orders");
    return (float) ($stmt->fetchColumn() ?? 0);
}



function getTopProducts(PDO $db, int $limit = 5): array {
    $stmt = $db->prepare("
        SELECT p.name AS product_name, SUM(o.quantity) AS total_quantity
        FROM orders o
        JOIN products p ON o.product_id = p.id
        GROUP BY o.product_id
        ORDER BY total_quantity DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function getRevenueLastMinute(PDO $db, string $fromTime): float {
    $stmt = $db->prepare("
        SELECT SUM(price * quantity)
        FROM orders
        WHERE datetime(date) >= datetime(:fromTime)
    ");
    $stmt->execute([':fromTime' => $fromTime]);
    return (float) ($stmt->fetchColumn() ?? 0);
}

function getOrdersLastMinute(PDO $db, string $fromTime): int {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM orders
        WHERE datetime(date) >= datetime(:fromTime)
    ");
    $stmt->execute([':fromTime' => $fromTime]);
    return (int) ($stmt->fetchColumn() ?? 0);
}

try {
    $now = time();
    $oneMinuteAgo = date('Y-m-d H:i:s', $now - 60);

    $response = [
        'total_revenue' => getTotalRevenue($db),
        'top_products' => getTopProducts($db),
        'revenue_last_minute' => getRevenueLastMinute($db, $oneMinuteAgo),
        'orders_last_minute' => getOrdersLastMinute($db, $oneMinuteAgo)
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Analytics failed: ' . $e->getMessage()]);
}

?>
