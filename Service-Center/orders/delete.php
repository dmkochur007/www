<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once '../config.php';
include_once '../includes/functions.php';

// Проверяем наличие ID заказа и валидируем его
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage('../orders/index.php', 'Помилка: Неправильний ID замовлення', 'error');
    exit;
}

$order_id = (int)$_GET['id'];

// Проверяем существование заказа
$check_sql = "SELECT order_id FROM orders WHERE order_id = ?";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute([$order_id]);
if (!$check_stmt->fetch()) {
    redirectWithMessage('../orders/index.php', 'Помилка: Замовлення не знайдено', 'error');
    exit;
}

// При подтверждении удаления
if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    try {
        // Начинаем транзакцию
        $pdo->beginTransaction();

        // Сначала удаляем связанные записи из таблиц order_services и order_parts
        $delete_services_sql = "DELETE FROM order_services WHERE order_id = ?";
        $delete_services_stmt = $pdo->prepare($delete_services_sql);
        $delete_services_stmt->execute([$order_id]);

        $delete_parts_sql = "DELETE FROM order_parts WHERE order_id = ?";
        $delete_parts_stmt = $pdo->prepare($delete_parts_sql);
        $delete_parts_stmt->execute([$order_id]);

        // Затем удаляем сам заказ
        $delete_order_sql = "DELETE FROM orders WHERE order_id = ?";
        $delete_order_stmt = $pdo->prepare($delete_order_sql);
        $delete_order_stmt->execute([$order_id]);

        // Фиксируем изменения в БД
        $pdo->commit();

        redirectWithMessage('../orders/index.php', 'Замовлення успішно видалено');
        exit;
    } catch (PDOException $e) {
        // Откатываем изменения в случае ошибки
        $pdo->rollBack();
        redirectWithMessage('../orders/index.php', 'Помилка видалення: ' . $e->getMessage(), 'error');
        exit;
    }
}

// Получаем информацию о заказе для отображения
$order_sql = "SELECT o.*, 
                    c.last_name as client_last_name, c.first_name as client_first_name,
                    m.last_name as master_last_name, m.first_name as master_first_name
              FROM orders o
              JOIN clients c ON o.client_id = c.client_id
              JOIN masters m ON o.master_id = m.master_id
              WHERE o.order_id = ?";
$order_stmt = $pdo->prepare($order_sql);
$order_stmt->execute([$order_id]);
$order = $order_stmt->fetch();

// Если заказ не найден
if (!$order) {
    redirectWithMessage('../orders/index.php', 'Помилка: Замовлення не знайдено', 'error');
    exit;
}

include_once '../includes/header.php';
?>

    <div class="actions-bar">
        <h1>Видалення замовлення</h1>
        <div>
            <a href="index.php" class="btn">Назад до списку</a>
        </div>
    </div>

    <div class="content">
        <div class="delete-confirmation">
            <h2>Ви дійсно бажаєте видалити це замовлення?</h2>

            <div class="order-details">
                <div class="order-info">
                    <div class="info-group">
                        <strong>ID замовлення:</strong> <?php echo $order['order_id']; ?>
                    </div>
                    <div class="info-group">
                        <strong>Клієнт:</strong> <?php echo htmlspecialchars($order['client_last_name'] . ' ' . $order['client_first_name']); ?>
                    </div>
                    <div class="info-group">
                        <strong>Майстер:</strong> <?php echo htmlspecialchars($order['master_last_name'] . ' ' . $order['master_first_name']); ?>
                    </div>
                    <div class="info-group">
                        <strong>Дата замовлення:</strong> <?php echo date('d.m.Y H:i', strtotime($order['order_date'])); ?>
                    </div>
                    <div class="info-group">
                        <strong>Статус:</strong>
                        <span class="status-badge <?php echo strtolower($order['status']) === 'нове' ? 'status-new' : (strtolower($order['status']) === 'завершено' ? 'status-completed' : 'status-in-progress'); ?>">
                        <?php echo htmlspecialchars($order['status']); ?>
                    </span>
                    </div>
                </div>
            </div>

            <div class="alert error">
                <p>Увага! Це видалить всі дані про замовлення, включаючи вибрані послуги та запчастини. Ця дія незворотня.</p>
            </div>

            <form action="delete.php?id=<?php echo $order_id; ?>" method="post" class="delete-form">
                <input type="hidden" name="confirm_delete" value="yes">
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Так, видалити</button>
                    <a href="index.php" class="btn">Скасувати</a>
                </div>
            </form>
        </div>
    </div>

    <style>
        .delete-confirmation {
            max-width: 700px;
            margin: 0 auto;
        }

        .delete-form {
            background: transparent;
            box-shadow: none;
            border: none;
            padding: 0;
            margin-top: 30px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .order-details {
            margin-bottom: 30px;
        }

        .order-info {
            padding: 25px;
        }

        .info-group {
            margin-bottom: 12px;
            font-size: 1.05rem;
        }

        .info-group strong {
            color: var(--accent-color);
            margin-right: 10px;
        }

        .alert {
            text-align: center;
        }

        .alert p {
            font-size: 1.1rem;
            color: var(--error-color);
        }
    </style>

<?php include_once '../includes/footer.php'; ?>