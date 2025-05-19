<?php
include_once '../config.php';
include_once '../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../masters/index.php', 'ID майстра не вказано', 'error');
}

$master_id = (int)$_GET['id'];

// Check if master has associated orders
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE master_id = ?");
    $stmt->execute([$master_id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        redirectWithMessage('../masters/index.php', 'Неможливо видалити майстра, оскільки з ним пов\'язані замовлення', 'error');
    }
} catch (PDOException $e) {
    redirectWithMessage('../masters/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}

// Delete master
try {
    $stmt = $pdo->prepare("DELETE FROM masters WHERE master_id = ?");
    $stmt->execute([$master_id]);

    redirectWithMessage('../masters/index.php', 'Майстер успішно видалений');
} catch (PDOException $e) {
    redirectWithMessage('../masters/index.php', 'Помилка видалення майстра: ' . $e->getMessage(), 'error');
}
?>