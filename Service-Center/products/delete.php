<?php
include_once '../config.php';
include_once '../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../products/index.php', 'ID запчастини не вказано', 'error');
}

$part_id = (int)$_GET['id'];

// Check if part is used in any orders
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_parts WHERE part_id = ?");
    $stmt->execute([$part_id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        redirectWithMessage('../products/index.php', 'Неможливо видалити запчастину, оскільки вона використовується в замовленнях', 'error');
    }
} catch (PDOException $e) {
    redirectWithMessage('../products/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}

// Delete spare part
try {
    $stmt = $pdo->prepare("DELETE FROM spare_parts WHERE part_id = ?");
    $stmt->execute([$part_id]);

    redirectWithMessage('../products/index.php', 'Запчастина успішно видалена');
} catch (PDOException $e) {
    redirectWithMessage('../products/index.php', 'Помилка видалення запчастини: ' . $e->getMessage(), 'error');
}
?>