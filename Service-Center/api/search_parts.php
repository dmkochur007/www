<?php
include_once '../config.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (strlen($query) >= 2) {
    $stmt = $pdo->prepare("
        SELECT product_id, name, code, price 
        FROM products 
        WHERE name LIKE :query OR code LIKE :query 
        LIMIT 10
    ");
    $stmt->execute(['query' => "%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($results);
?>