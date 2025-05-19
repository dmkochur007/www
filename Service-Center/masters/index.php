<?php
include_once '../config.php';
include_once '../includes/header.php';

// Handle search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = '';
$params = [];

if (!empty($search)) {
    $where = " WHERE CONCAT(last_name, ' ', first_name, ' ', phone_number, ' ', IFNULL(email, '')) LIKE ?";
    $params[] = "%$search%";
}

// Prepare the query
$sql = "SELECT * FROM masters" . $where . " ORDER BY last_name, first_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$masters = $stmt->fetchAll();
?>

    <div class="actions-bar">
        <h1>Майстри</h1>
        <div>
            <form action="" method="get" class="search-form">
                <input type="text" name="search" placeholder="Пошук майстрів..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">Пошук</button>
            </form>
        </div>
        <div>
            <a href="add.php" class="btn">Додати майстра</a>
        </div>
    </div>

<?php if (empty($masters)): ?>
    <p>Майстрів не знайдено</p>
<?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Прізвище</th>
                <th>Ім'я</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Дії</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($masters as $master): ?>
                <tr>
                    <td><?php echo $master['master_id']; ?></td>
                    <td><?php echo htmlspecialchars($master['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($master['first_name']); ?></td>
                    <td><?php echo htmlspecialchars($master['phone_number']); ?></td>
                    <td><?php echo htmlspecialchars($master['email'] ?? ''); ?></td>
                    <td class="action-buttons">
                        <a href="edit.php?id=<?php echo $master['master_id']; ?>" class="btn">Редагувати</a>
                        <a href="delete.php?id=<?php echo $master['master_id']; ?>" class="btn btn-danger delete-btn">Видалити</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>