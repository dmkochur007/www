<?php
include_once '../config.php';
include_once '../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../clients/index.php', 'ID клієнта не вказано', 'error');
}

$client_id = (int)$_GET['id'];

// Check if client has associated orders
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        redirectWithMessage('../clients/index.php', 'Неможливо видалити клієнта, оскільки з ним пов\'язані замовлення', 'error');
    }
} catch (PDOException $e) {
    redirectWithMessage('../clients/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}

// Delete client
try {
    $stmt = $pdo->prepare("DELETE FROM clients WHERE client_id = ?");
    $stmt->execute([$client_id]);

    redirectWithMessage('../clients/index.php', 'Клієнт успішно видалений');
} catch (PDOException $e) {
    redirectWithMessage('../clients/index.php', 'Помилка видалення клієнта: ' . $e->getMessage(), 'error');
}
?>