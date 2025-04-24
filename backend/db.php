<?php

// Connect to SQLite database 
try {
    $db = new PDO('sqlite:' . __DIR__ . '/sales.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create products table
    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            price REAL NOT NULL
        )
    ");

    $db->exec("
        INSERT OR IGNORE INTO products (id, name, price) VALUES
        ('p1', 'Coca Cola', 10.50),
        ('p2', 'Pepsi', 11.40),
        ('p3', 'Coffee', 22.00),
        ('p4', 'Mokhito', 101.50),
        ('p5', 'Spanish latee', 81.40),
        ('p6', 'Lemon', 52.00),
        ('p7', 'Smoothie', 100.50),
        ('p8', 'Mokaa', 31.40),
        ('p9', 'Tea', 2.00),
        ('p10', 'Green Tea', 1.50),
        ('p11', 'Iced Coffee', 21.40),
        ('p12', 'Redbull', 22.00)
    ");

    // Create the orders table 
    $db->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            price REAL NOT NULL,
            date DATETIME
        )
    ");
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>
