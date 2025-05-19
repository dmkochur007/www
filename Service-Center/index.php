<?php
include_once 'config.php';
include_once 'includes/header.php';
?>

    <div class="dashboard">
        <h1 class="welcome">Ласкаво просимо до <span class="accent-text">Сервісного Центру</span></h1>

        <div class="dashboard-stats">
            <?php
            // Get total clients
            $clientsQuery = $pdo->query('SELECT COUNT(*) as total FROM clients');
            $totalClients = $clientsQuery->fetch()['total'];

            // Get total masters
            $mastersQuery = $pdo->query('SELECT COUNT(*) as total FROM masters');
            $totalMasters = $mastersQuery->fetch()['total'];

            // Get total services
            $servicesQuery = $pdo->query('SELECT COUNT(*) as total FROM services');
            $totalServices = $servicesQuery->fetch()['total'];

            // Get total products
            $productsQuery = $pdo->query('SELECT COUNT(*) as total FROM products');
            $totalProducts = $productsQuery->fetch()['total'];

            // Get total orders
            $ordersQuery = $pdo->query('SELECT COUNT(*) as total FROM orders');
            $totalOrders = $ordersQuery->fetch()['total'];

            // Get recent orders (last 5)
            $recentOrdersQuery = $pdo->query('SELECT o.order_id, c.last_name, c.first_name, m.last_name as master_last_name, 
                                         m.first_name as master_first_name, o.order_date, o.status 
                                         FROM orders o 
                                         JOIN clients c ON o.client_id = c.client_id 
                                         JOIN masters m ON o.master_id = m.master_id
                                         ORDER BY o.order_date DESC 
                                         LIMIT 5');
            $recentOrders = $recentOrdersQuery->fetchAll();
            ?>

            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Клієнти</h3>
                    <div class="stat-count"><?php echo $totalClients; ?></div>
                    <a href="clients/index.php" class="btn">Переглянути всі</a>
                </div>

                <div class="stat-box">
                    <h3>Майстри</h3>
                    <div class="stat-count"><?php echo $totalMasters; ?></div>
                    <a href="masters/index.php" class="btn">Переглянути всі</a>
                </div>

                <div class="stat-box">
                    <h3>Послуги</h3>
                    <div class="stat-count"><?php echo $totalServices; ?></div>
                    <a href="services/index.php" class="btn">Переглянути всі</a>
                </div>

                <div class="stat-box">
                    <h3>Запчастини</h3>
                    <div class="stat-count"><?php echo $totalProducts; ?></div>
                    <a href="products/index.php" class="btn">Переглянути всі</a>
                </div>

                <div class="stat-box">
                    <h3>Замовлення</h3>
                    <div class="stat-count"><?php echo $totalOrders; ?></div>
                    <a href="orders/index.php" class="btn">Переглянути всі</a>
                </div>
            </div>

            <div class="recent-orders">
                <h2>Останні замовлення</h2>

                <?php if (empty($recentOrders)): ?>
                    <p>Немає замовлень для відображення</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Клієнт</th>
                                <th>Майстер</th>
                                <th>Дата</th>
                                <th>Статус</th>
                                <th>Дії</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['last_name'] . ' ' . $order['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['master_last_name'] . ' ' . $order['master_first_name']); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'status-new';
                                        if (strtolower($order['status']) == 'в роботі') {
                                            $statusClass = 'status-in-progress';
                                        } elseif (strtolower($order['status']) == 'завершено') {
                                            $statusClass = 'status-completed';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="orders/view.php?id=<?php echo $order['order_id']; ?>" class="btn">Деталі</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php include_once 'includes/footer.php'; ?>