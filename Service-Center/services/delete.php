<?php
include_once '../config.php';
include_once '../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../services/index.php', 'ID послуги не вказано', 'error');
}

$service_id = (int)$_GET['id'];

// Check if service is used in any orders
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        redirectWithMessage('../services/index.php', 'Неможливо видалити послугу, оскільки вона використовується в замовленнях', 'error');
    }
} catch (PDOException $e) {
    redirectWithMessage('../services/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}

// Delete service
try {
    $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);

    redirectWithMessage('../services/index.php', 'Послуга успішно видалена');
} catch (PDOException $e) {
    redirectWithMessage('../services/index.php', 'Помилка видалення послуги: ' . $e->getMessage(), 'error');
}
?>