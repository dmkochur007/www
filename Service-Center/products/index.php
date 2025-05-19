<?php
include_once '../config.php';
include_once '../includes/header.php';

// Pagination settings
$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = $current_page < 1 ? 1 : $current_page;
$offset = ($current_page - 1) * $items_per_page;

// Handle search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = '';
$params = [];

if (!empty($search)) {
    $where = " WHERE name LIKE ? OR code LIKE ? OR unit LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total items for pagination
$count_sql = "SELECT COUNT(*) as total FROM products" . $where;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Prepare the query with pagination
$sql = "SELECT * FROM products" . $where . " ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Fetch all results and manually handle pagination
$allProducts = $stmt->fetchAll();
$products = array_slice($allProducts, $offset, $items_per_page);
?>

    <div class="actions-bar">
        <h1>Запчастини</h1>
        <div>
            <form action="" method="get" class="search-form">
                <input type="text" name="search" placeholder="Пошук запчастин..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">Пошук</button>
            </form>
        </div>
        <div>
            <a href="add.php" class="btn">Додати запчастину</a>
        </div>
    </div>

<?php if (empty($allProducts)): ?>
    <p>Запчастин не знайдено. <a href="add.php" class="btn">Додайте нову запчастину</a></p>
<?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Код</th>
                <th>Назва</th>
                <th>Ціна</th>
                <th>Одиниця</th>
                <th>Додано</th>
                <th>Дії</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['product_id']; ?></td>
                    <td><?php echo htmlspecialchars($product['code'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo $product['price'] !== null ? number_format((float)$product['price'], 2) . ' грн' : '-'; ?></td>
                    <td><?php echo htmlspecialchars($product['unit'] ?? 'шт'); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($product['created_at'])); ?></td>
                    <td class="action-buttons">
                        <a href="edit.php?id=<?php echo $product['product_id']; ?>" class="btn">Редагувати</a>
                        <a href="delete.php?id=<?php echo $product['product_id']; ?>" class="btn btn-danger delete-btn">Видалити</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-link">«</a>
                <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-link">‹</a>
            <?php endif; ?>

            <?php
            // Determine the range of page numbers to display
            $range = 3; // Number of pages to show on each side of the current page
            $start_page = max(1, $current_page - $range);
            $end_page = min($total_pages, $current_page + $range);

            // Always show page 1
            if ($start_page > 1) {
                echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="pagination-link">1</a>';
                if ($start_page > 2) {
                    echo '<span class="pagination-ellipsis">...</span>';
                }
            }

            // Display the page range
            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = ($i == $current_page) ? 'active' : '';
                echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="pagination-link ' . $active . '">' . $i . '</a>';
            }

            // Always show the last page
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="pagination-ellipsis">...</span>';
                }
                echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . '" class="pagination-link">' . $total_pages . '</a>';
            }
            ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-link">›</a>
                <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="pagination-link">»</a>
            <?php endif; ?>

            <!-- Direct page input -->
            <form action="" method="get" class="pagination-goto">
                <input type="number" name="page" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>" required>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <button type="submit" class="btn">Перейти</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="pagination-info">
        Показано <?php echo count($products); ?> з <?php echo $total_items; ?> запчастин |
        Сторінка <?php echo $current_page; ?> з <?php echo max(1, $total_pages); ?>
    </div>
<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>
<style>
    /* Add these pagination styles to your existing CSS file if not already there */

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 30px 0 15px;
        flex-wrap: wrap;
        gap: 5px;
    }

    .pagination-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: var(--card-bg);
        color: var(--text-color);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .pagination-link:hover {
        background-color: var(--accent-color);
        color: var(--primary-bg);
    }

    .pagination-link.active {
        background-color: var(--accent-color);
        color: var(--primary-bg);
        cursor: default;
    }

    .pagination-ellipsis {
        padding: 0 10px;
        color: var(--text-muted);
    }

    .pagination-goto {
        margin-left: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pagination-goto input {
        width: 60px;
        text-align: center;
        padding: 8px;
    }

    .pagination-goto button {
        padding: 8px 12px;
        height: 40px;
    }

    .pagination-info {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .pagination {
            gap: 3px;
        }

        .pagination-link {
            width: 35px;
            height: 35px;
            font-size: 0.9rem;
        }

        .pagination-goto {
            margin-top: 15px;
            width: 100%;
            justify-content: center;
        }
    }
</style>
