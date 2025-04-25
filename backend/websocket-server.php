<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

use React\EventLoop\Factory;
use React\Socket\Server as ReactSocket;
use React\Socket\ConnectionInterface as ReactConnection;

require 'vendor/autoload.php';

class OrderNotifier implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "✅ WebSocket server started and ready.\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "🔌 New connection (ID: {$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Not used
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "❌ Connection closed (ID: {$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "⚠️ Error on connection {$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcastNewOrder(array $orderDetails = []) {
        $productsList = array_map(function ($order) {
            return "{$order['quantity']} x {$order['product_name']}";
        }, $orderDetails);
    
        $productsText = implode(", ", $productsList);
        $summaryMessage = "🛒 New order received containing: {$productsText}";
    
        echo "📢 Broadcasted new order to {$productsText} clients.\n";

        $message = [
            'type' => 'new_order',
            'message' => $summaryMessage,
            'order' => $orderDetails
        ];
    
        foreach ($this->clients as $client) {
            $client->send(json_encode($message));
        }
    
        echo "📢 Broadcasted new order to " . count($this->clients) . " clients.\n";
    }

    public function broadcastAnalytics(array $analyticsData = []) {
        $message = [
            'type' => 'analytics_update',
            'message' => '📊 Analytics updated',
            'analytics' => $analyticsData
        ];
    
        foreach ($this->clients as $client) {
            $client->send(json_encode($message));
        }
    
        echo "📢 Broadcasted analytics update to " . count($this->clients) . " clients.\n";
    }
}

// ===== SETUP WEBSOCKET + TCP LISTENER =====
$loop = Factory::create();
$notifier = new OrderNotifier();

// WebSocket Server
$webSock = new ReactSocket('0.0.0.0:8080', $loop);
$webServer = new IoServer(
    new HttpServer(
        new WsServer($notifier)
    ),
    $webSock,
    $loop
);

// Unified TCP server
$tcpServer = new ReactSocket('127.0.0.1:9000', $loop);
$tcpServer->on('connection', function (ReactConnection $conn) use ($notifier) {
    $conn->on('data', function ($data) use ($conn, $notifier) {

        $payload = json_decode($data, true);

        if (!$payload || !isset($payload['type'])) {
            $conn->end();
            return;
        }

        switch ($payload['type']) {
            case 'new_order':
                if (!empty($payload['order'])) {
                    $notifier->broadcastNewOrder($payload['order']);
                }
                break;

            case 'analytics_update':
                if (!empty($payload['analytics'])) {
                    $notifier->broadcastAnalytics($payload['analytics']);
                }
                break;

            default:
                echo "⚠️ Unknown payload type received.\n";
                break;
        }

        $conn->end();
    });
});

$loop->run();
