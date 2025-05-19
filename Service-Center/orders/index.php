<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once '../config.php';
include_once '../includes/functions.php';
include_once '../includes/header.php';

// Pagination settings
$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = $current_page < 1 ? 1 : $current_page;
$offset = ($current_page - 1) * $items_per_page;

// Handle filters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$client = isset($_GET['client']) ? $_GET['client'] : '';
$master = isset($_GET['master']) ? $_GET['master'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where_clauses = [];
$params = [];

if (!empty($status)) {
    $where_clauses[] = "o.status = ?";
    $params[] = $status;
}

if (!empty($client)) {
    $where_clauses[] = "(c.last_name LIKE ? OR c.first_name LIKE ? OR c.phone_number LIKE ?)";
    $params[] = "%$client%";
    $params[] = "%$client%";
    $params[] = "%$client%";
}

if (!empty($master)) {
    $where_clauses[] = "(m.last_name LIKE ? OR m.first_name LIKE ?)";
    $params[] = "%$master%";
    $params[] = "%$master%";
}

if (!empty($date_from)) {
    $where_clauses[] = "o.order_date >= ?";
    $params[] = $date_from . " 00:00:00";
}

if (!empty($date_to)) {
    $where_clauses[] = "o.order_date <= ?";
    $params[] = $date_to . " 23:59:59";
}

$where = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Count total items for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM orders o
              JOIN clients c ON o.client_id = c.client_id
              JOIN masters m ON o.master_id = m.master_id
              $where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Prepare the query with pagination
$sql = "SELECT o.order_id, o.order_date, o.status,
               c.last_name as client_last_name, c.first_name as client_first_name, 
               m.last_name as master_last_name, m.first_name as master_first_name,
               (
                   IFNULL((SELECT SUM(s.price * os.quantity) 
                      FROM order_services os 
                      JOIN services s ON os.service_id = s.service_id 
                      WHERE os.order_id = o.order_id), 0) +
                   
                   IFNULL((SELECT SUM(p.price * op.quantity) 
                      FROM order_parts op 
                      JOIN products p ON op.part_id = p.product_id 
                      WHERE op.order_id = o.order_id), 0)
               ) AS total_price
        FROM orders o
        JOIN clients c ON o.client_id = c.client_id
        JOIN masters m ON o.master_id = m.master_id
        $where
        ORDER BY o.order_date DESC
        LIMIT :limit OFFSET :offset";

// Use named parameters instead
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value); // Bind position parameters
}
$stmt->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT); // Explicitly as integer
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT); // Explicitly as integer
$stmt->execute();
$orders = $stmt->fetchAll();

// Get all statuses for filter dropdown
$status_query = $pdo->query("SELECT DISTINCT status FROM orders ORDER BY status");
$statuses = $status_query->fetchAll(PDO::FETCH_COLUMN);
?>

    <div class="actions-bar">
        <h1>Замовлення</h1>
        <div>
            <a href="add.php" class="btn">Додати замовлення</a>
        </div>
    </div>

    <!-- Filter form -->
    <div class="filter-panel">
        <h3>Фільтри</h3>
        <form action="" method="get" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="status">Статус:</label>
                    <select name="status" id="status">
                        <option value="">Всі статуси</option>
                        <?php foreach ($statuses as $status_option): ?>
                            <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo $status === $status_option ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="client">Клієнт:</label>
                    <input type="text" name="client" id="client" value="<?php echo htmlspecialchars($client); ?>" placeholder="Пошук клієнта...">
                </div>

                <div class="filter-group">
                    <label for="master">Майстер:</label>
                    <input type="text" name="master" id="master" value="<?php echo htmlspecialchars($master); ?>" placeholder="Пошук майстра...">
                </div>
            </div>

            <div class="filter-row">
                <div class="filter-group">
                    <label for="date_from">Дата з:</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">Дата по:</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn">Застосувати</button>
                    <a href="index.php" class="btn">Скинути</a>
                </div>
            </div>
        </form>
    </div>

<?php if (empty($orders)): ?>
    <p>Замовлень не знайдено</p>
<?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Дата</th>
                <th>Клієнт</th>
                <th>Майстер</th>
                <th>Статус</th>
                <th>Сума</th>
                <th>Дії</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?php echo $order['order_id']; ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($order['order_date'])); ?></td>
                    <td>
                        <?php echo htmlspecialchars($order['client_last_name'] . ' ' . $order['client_first_name']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($order['master_last_name'] . ' ' . $order['master_first_name']); ?>
                    </td>
                    <td>
                        <?php
                        $statusClass = 'status-new';
                        if (strtolower($order['status']) === 'в роботі') {
                            $statusClass = 'status-in-progress';
                        } elseif (strtolower($order['status']) === 'завершено') {
                            $statusClass = 'status-completed';
                        }
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                    </td>
                    <td><?php echo number_format($order['total_price'], 2); ?> грн</td>
                    <td class="action-buttons">
                        <a href="view.php?id=<?php echo $order['order_id']; ?>" class="btn">Деталі</a>
                        <a href="edit.php?id=<?php echo $order['order_id']; ?>" class="btn">Редагувати</a>

                        <div class="dropdown">
                            <button type="button" class="btn dropdown-toggle">Статус</button>
                            <div class="dropdown-menu" style="display: none;">
                                <?php if ($order['status'] !== 'Нове'): ?>
                                    <a href="status.php?id=<?php echo $order['order_id']; ?>&status=Нове" class="dropdown-item">Нове</a>
                                <?php endif; ?>
                                <?php if ($order['status'] !== 'В роботі'): ?>
                                    <a href="status.php?id=<?php echo $order['order_id']; ?>&status=В роботі" class="dropdown-item">В роботі</a>
                                <?php endif; ?>
                                <?php if ($order['status'] !== 'Завершено'): ?>
                                    <a href="status.php?id=<?php echo $order['order_id']; ?>&status=Завершено" class="dropdown-item">Завершено</a>
                                <?php endif; ?>
                                <?php if ($order['status'] !== 'Відміненo'): ?>
                                    <a href="status.php?id=<?php echo $order['order_id']; ?>&status=Відміненo" class="dropdown-item">Відміненo</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <a href="delete.php?id=<?php echo $order['order_id']; ?>" class="btn btn-danger">Видалити</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            // Build query string for pagination links, preserving all filters
            $query_params = [];
            if (!empty($status)) $query_params[] = 'status=' . urlencode($status);
            if (!empty($client)) $query_params[] = 'client=' . urlencode($client);
            if (!empty($master)) $query_params[] = 'master=' . urlencode($master);
            if (!empty($date_from)) $query_params[] = 'date_from=' . urlencode($date_from);
            if (!empty($date_to)) $query_params[] = 'date_to=' . urlencode($date_to);
            $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
            ?>

            <?php if ($current_page > 1): ?>
                <a href="?page=1<?php echo $query_string; ?>" class="pagination-link">«</a>
                <a href="?page=<?php echo $current_page - 1; ?><?php echo $query_string; ?>" class="pagination-link">‹</a>
            <?php endif; ?>

            <?php
            // Determine the range of page numbers to display
            $range = 3; // Number of pages to show on each side of the current page
            $start_page = max(1, $current_page - $range);
            $end_page = min($total_pages, $current_page + $range);

            // Always show page 1
            if ($start_page > 1) {
                echo '<a href="?page=1' . $query_string . '" class="pagination-link">1</a>';
                if ($start_page > 2) {
                    echo '<span class="pagination-ellipsis">...</span>';
                }
            }

            // Display the page range
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = ($i == $current_page) ? 'active' : '';
                echo '<a href="?page=' . $i . $query_string . '" class="pagination-link ' . $active . '">' . $i . '</a>';
            }

            // Always show the last page
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="pagination-ellipsis">...</span>';
                }
                echo '<a href="?page=' . $total_pages . $query_string . '" class="pagination-link">' . $total_pages . '</a>';
            }
            ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?><?php echo $query_string; ?>" class="pagination-link">›</a>
                <a href="?page=<?php echo $total_pages; ?><?php echo $query_string; ?>" class="pagination-link">»</a>
            <?php endif; ?>

            <!-- Direct page input -->
            <form action="" method="get" class="pagination-goto">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>" required>

                <?php foreach (['status', 'client', 'master', 'date_from', 'date_to'] as $param): ?>
                    <?php if (!empty($$param)): ?>
                        <input type="hidden" name="<?php echo $param; ?>" value="<?php echo htmlspecialchars($$param); ?>">
                    <?php endif; ?>
                <?php endforeach; ?>

                <button type="submit" class="btn">Перейти</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="pagination-info">
        Показано <?php echo count($orders); ?> з <?php echo $total_items; ?> замовлень |
        Сторінка <?php echo $current_page; ?> з <?php echo max(1, $total_pages); ?>
    </div>
<?php endif; ?>

    <style>
        /* Стили для выпадающего меню при использовании портала */
        .dropdown {
            position: relative;
            display: inline-block;
            margin-right: 10px;
        }

        .dropdown-toggle {
            padding-right: 30px;
            position: relative;
        }

        .dropdown-toggle::after {
            content: '';
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid var(--accent-color);
        }

        /* Контейнер для порталов меню */
        #dropdown-menu-container {
            position: absolute;
            z-index: 2000;
            pointer-events: none;
        }

        #dropdown-menu-container .dropdown-menu {
            pointer-events: auto;
            min-width: 160px;
            background-color: var(--card-bg);
            box-shadow: var(--box-shadow);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        /* Оверлей для закрытия меню при клике вне него */
        .dropdown-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 999;
            display: none;
        }

        .dropdown-overlay.active {
            display: block;
        }

        .dropdown-item {
            color: var(--text-color);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background-color: var(--table-hover);
            color: var(--accent-color);
        }
        /* Filter panel styles */
        .filter-panel {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
        }

        .filter-panel h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .filter-buttons {
                width: 100%;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Добавляем оверлей для закрытия меню при клике вне его
            const overlay = document.createElement('div');
            overlay.className = 'dropdown-overlay';
            document.body.appendChild(overlay);

            // Обработка выпадающего меню
            const dropdowns = document.querySelectorAll('.dropdown');

            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.dropdown-toggle');
                const menu = dropdown.querySelector('.dropdown-menu');

                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Если меню уже открыто, закрываем его
                    if (menu.classList.contains('show')) {
                        menu.classList.remove('show');
                        overlay.classList.remove('active');
                        return;
                    }

                    // Закрываем все другие открытые меню
                    document.querySelectorAll('.dropdown-menu.show').forEach(openMenu => {
                        openMenu.classList.remove('show');
                    });

                    // Открываем текущее меню
                    menu.classList.add('show');
                    overlay.classList.add('active');

                    // Позиционируем меню с учетом границ экрана
                    positionDropdownMenu(menu, toggle);
                });
            });

            // Функция для позиционирования выпадающего меню
            function positionDropdownMenu(menu, toggle) {
                // Сбрасываем старые стили позиционирования
                menu.style.left = '';
                menu.style.right = '';
                menu.style.top = '';
                menu.style.bottom = '';
                menu.classList.remove('dropdown-menu-up');

                // Получаем координаты и размеры кнопки и меню
                const toggleRect = toggle.getBoundingClientRect();
                menu.style.display = 'block'; // Временно показываем меню для измерения
                const menuRect = menu.getBoundingClientRect();
                menu.style.display = ''; // Убираем временное отображение

                // Проверяем, выходит ли меню за правую границу экрана
                if (toggleRect.left + menuRect.width > window.innerWidth) {
                    // Если выходит, позиционируем справа
                    menu.style.left = 'auto';
                    menu.style.right = '0';
                } else {
                    // Иначе позиционируем слева
                    menu.style.left = '0';
                    menu.style.right = 'auto';
                }

                // Проверяем, выходит ли меню за нижнюю границу экрана
                const spaceBelow = window.innerHeight - toggleRect.bottom;
                const spaceAbove = toggleRect.top;
                const menuHeight = menuRect.height;

                if (spaceBelow < menuHeight && spaceAbove > menuHeight) {
                    // Если внизу места недостаточно, но вверху достаточно - показываем меню вверх
                    menu.classList.add('dropdown-menu-up');
                }
            }

            // Закрытие меню при клике на оверлей
            overlay.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
                overlay.classList.remove('active');
            });

            // Закрытие меню при клике на элемент меню
            document.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', function() {
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                    overlay.classList.remove('active');
                });
            });

            // Закрытие меню при нажатии клавиши Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                    overlay.classList.remove('active');
                }
            });

            // Перепозиционирование при прокрутке или изменении размера окна
            window.addEventListener('scroll', closeAllDropdowns);
            window.addEventListener('resize', closeAllDropdowns);

            function closeAllDropdowns() {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
                overlay.classList.remove('active');
            }
        });
    </script>
<?php include_once '../includes/footer.php'; ?>