<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once '../config.php';
include_once '../includes/functions.php';

// Проверяем наличие параметров
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['status'])) {
    redirectWithMessage('../orders/index.php', 'Помилка: Неправильні параметри', 'error');
    exit;
}

$order_id = (int)$_GET['id'];
$new_status = trim($_GET['status']);

// Проверяем, что статус допустимый
$valid_statuses = ['Нове', 'В роботі', 'Завершено', 'Відміненo'];
if (!in_array($new_status, $valid_statuses)) {
    redirectWithMessage('../orders/index.php', 'Помилка: Недопустимий статус', 'error');
    exit;
}

// Проверяем существование заказа
$check_sql = "SELECT order_id FROM orders WHERE order_id = ?";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute([$order_id]);
if (!$check_stmt->fetch()) {
    redirectWithMessage('../orders/index.php', 'Помилка: Замовлення не знайдено', 'error');
    exit;
}

// Обновляем статус заказа
try {
    $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$new_status, $order_id]);

    redirectWithMessage('../orders/index.php', 'Статус замовлення успішно змінено на "' . $new_status . '"');
} catch (PDOException $e) {
    redirectWithMessage('../orders/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}
?>