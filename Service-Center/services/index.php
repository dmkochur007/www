<?php
include_once '../config.php';
include_once '../includes/header.php';

// Handle search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = '';
$params = [];

if (!empty($search)) {
    $where = " WHERE name LIKE ? OR price LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Prepare the query
$sql = "SELECT * FROM services" . $where . " ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();
?>

    <div class="actions-bar">
        <h1>Послуги</h1>
        <div>
            <form action="" method="get" class="search-form">
                <input type="text" name="search" placeholder="Пошук послуг..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">Пошук</button>
            </form>
        </div>
        <div>
            <a href="add.php" class="btn">Додати послугу</a>
        </div>
    </div>

<?php if (empty($services)): ?>
    <p>Послуг не знайдено</p>
<?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Назва</th>
                <th>Ціна</th>
                <th>Дії</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $service): ?>
                <tr>
                    <td><?php echo $service['service_id']; ?></td>
                    <td><?php echo htmlspecialchars($service['name']); ?></td>
                    <td><?php echo number_format($service['price'], 2); ?> грн</td>
                    <td class="action-buttons">
                        <a href="edit.php?id=<?php echo $service['service_id']; ?>" class="btn">Редагувати</a>
                        <a href="delete.php?id=<?php echo $service['service_id']; ?>" class="btn btn-danger delete-btn">Видалити</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

    <style>
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .actions-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>

<?php include_once '../includes/footer.php'; ?>