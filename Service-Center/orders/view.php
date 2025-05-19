<?php
include_once '../config.php';
include_once '../includes/header.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../orders/index.php', 'ID замовлення не вказано', 'error');
}

$order_id = (int)$_GET['id'];

// Get order data
try {
    // Get order basic info
    $stmt = $pdo->prepare("
        SELECT o.*, 
               c.client_id, c.last_name as client_last_name, c.first_name as client_first_name, c.phone_number as client_phone, c.email as client_email,
               m.master_id, m.last_name as master_last_name, m.first_name as master_first_name, m.phone_number as master_phone
        FROM orders o
        JOIN clients c ON o.client_id = c.client_id
        JOIN masters m ON o.master_id = m.master_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        redirectWithMessage('../orders/index.php', 'Замовлення не знайдено', 'error');
    }

    // Get services for this order
    $stmt = $pdo->prepare("
        SELECT os.*, s.name as service_name, s.price as service_price
        FROM order_services os
        JOIN services s ON os.service_id = s.service_id
        WHERE os.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $services = $stmt->fetchAll();

    // Get parts (products) for this order
    $stmt = $pdo->prepare("
        SELECT op.*, p.name as part_name, p.price as part_price, p.code as part_code
        FROM order_parts op
        JOIN products p ON op.part_id = p.product_id
        WHERE op.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $parts = $stmt->fetchAll();

    // Calculate totals
    $services_total = 0;
    foreach ($services as $service) {
        $services_total += $service['service_price'];
    }

    $parts_total = 0;
    foreach ($parts as $part) {
        $parts_total += $part['quantity'] * $part['part_price'];
    }

} catch (PDOException $e) {
    redirectWithMessage('../orders/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}

// Get status class for badge
$statusClass = 'status-new';
if (strtolower($order['status']) === 'в роботі') {
    $statusClass = 'status-in-progress';
} elseif (strtolower($order['status']) === 'завершено') {
    $statusClass = 'status-completed';
}
?>

    <div class="page-header">
        <h1>Замовлення №<?php echo $order_id; ?></h1>
        <div class="page-actions">
            <a href="edit.php?id=<?php echo $order_id; ?>" class="btn">Редагувати</a>
            <a href="delete.php?id=<?php echo $order_id; ?>" class="btn btn-danger delete-btn">Видалити</a>
            <a href="index.php" class="btn">Всі замовлення</a>
        </div>
    </div>

    <div class="order-details">
        <!-- Order Info -->
        <div class="order-info">
            <h3>Інформація про замовлення</h3>
            <div class="info-group">
                <strong>Дата:</strong> <?php echo date('d.m.Y H:i', strtotime($order['order_date'])); ?>
            </div>
            <div class="info-group">
                <strong>Статус:</strong>
                <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
            </div>
            <div class="info-group">
                <strong>Опис проблеми:</strong>
                <p><?php echo nl2br(htmlspecialchars($order['issue_description'] ?? 'Не вказано')); ?></p>
            </div>
            <?php if (!empty($order['client_device'])): ?>
                <div class="info-group">
                    <strong>Пристрій:</strong> <?php echo htmlspecialchars($order['client_device']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['notes'])): ?>
                <div class="info-group">
                    <strong>Примітки:</strong>
                    <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Client Info -->
        <div class="client-info">
            <h3>Інформація про клієнта</h3>
            <div class="info-group">
                <strong>ПІБ:</strong>
                <?php echo htmlspecialchars($order['client_last_name'] . ' ' . $order['client_first_name']); ?>
            </div>
            <div class="info-group">
                <strong>Телефон:</strong> <?php echo htmlspecialchars($order['client_phone']); ?>
            </div>
            <?php if (!empty($order['client_email'])): ?>
                <div class="info-group">
                    <strong>Email:</strong> <?php echo htmlspecialchars($order['client_email']); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Master Info -->
        <div class="master-info">
            <h3>Інформація про майстра</h3>
            <div class="info-group">
                <strong>ПІБ:</strong>
                <?php echo htmlspecialchars($order['master_last_name'] . ' ' . $order['master_first_name']); ?>
            </div>
            <div class="info-group">
                <strong>Телефон:</strong> <?php echo htmlspecialchars($order['master_phone']); ?>
            </div>
        </div>
    </div>

    <!-- Services -->
    <div class="order-section">
        <h3>Послуги</h3>
        <?php if (empty($services)): ?>
            <p>Не додано жодної послуги до цього замовлення.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Назва послуги</th>
                        <th class="text-right">Ціна</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                            <td class="text-right"><?php echo number_format($service['service_price'], 2); ?> грн</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th>Всього за послуги:</th>
                        <th class="text-right"><?php echo number_format($services_total, 2); ?> грн</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Parts -->
    <div class="order-section">
        <h3>Запчастини</h3>
        <?php if (empty($parts)): ?>
            <p>Не додано жодної запчастини до цього замовлення.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Код</th>
                        <th>Назва</th>
                        <th class="text-right">Кількість</th>
                        <th class="text-right">Ціна</th>
                        <th class="text-right">Сума</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($parts as $part): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($part['part_code'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($part['part_name']); ?></td>
                            <td class="text-right"><?php echo $part['quantity']; ?></td>
                            <td class="text-right"><?php echo number_format($part['part_price'], 2); ?> грн</td>
                            <td class="text-right"><?php echo number_format($part['quantity'] * $part['part_price'], 2); ?> грн</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="4">Всього за запчастини:</th>
                        <th class="text-right"><?php echo number_format($parts_total, 2); ?> грн</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Total Amount -->
    <div class="order-total">
        <div class="total-box">
            <div class="total-row">
                <div class="total-label">Загальна сума:</div>
                <div class="total-value"><?php echo number_format($services_total + $parts_total, 2); ?> грн</div>
            </div>
        </div>
    </div>

    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-actions {
            display: flex;
            gap: 10px;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .order-info, .client-info, .master-info {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .order-section {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-group strong {
            color: var(--accent-color);
            margin-right: 5px;
        }

        .text-right {
            text-align: right;
        }

        .order-total {
            display: flex;
            justify-content: flex-end;
            margin: 30px 0;
        }

        .total-box {
            background-color: var(--card-bg);
            border: 1px solid var(--accent-color);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--box-shadow);
            min-width: 300px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .total-value {
            color: var(--accent-color);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-total {
                justify-content: center;
            }
        }
    </style>

<?php include_once '../includes/footer.php'; ?>