<?php
// Начинаем сессию в самом начале файла
session_start();

include_once '../config.php';
include_once '../includes/header.php';

// Handle search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = '';
$params = [];

if (!empty($search)) {
    $where = " WHERE CONCAT(last_name, ' ', first_name, ' ', IFNULL(patronymic, ''), ' ', phone_number, ' ', IFNULL(email, ''), ' ', IFNULL(car, '')) LIKE ?";
    $params[] = "%$search%";
}

// Prepare the query
$sql = "SELECT * FROM clients" . $where . " ORDER BY last_name, first_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();
?>

    <div class="actions-bar">
        <h1>Клієнти</h1>
        <div>
            <form action="" method="get" class="search-form">
                <input type="text" name="search" placeholder="Пошук клієнтів..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">Пошук</button>
                <?php if (!empty($search)): ?>
                    <a href="index.php" class="btn">Скинути</a>
                <?php endif; ?>
            </form>
        </div>
        <div>
            <a href="add.php" class="btn">Додати клієнта</a>
        </div>
    </div>

    <!-- Отображение сообщений об успехе -->
<?php if (isset($_SESSION['message'])): ?>
    <div class="alert <?php echo $_SESSION['message_type'] ?? 'success'; ?>">
        <?php echo $_SESSION['message']; ?>
    </div>
    <?php
    // Очищаем сообщение после отображения
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
    ?>
<?php endif; ?>

<?php if (empty($clients)): ?>
    <p>Клієнтів не знайдено</p>
<?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Прізвище</th>
                <th>Ім'я</th>
                <th>По батькові</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Автомобіль</th>
                <th>Дії</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?php echo $client['client_id']; ?></td>
                    <td><?php echo htmlspecialchars($client['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($client['first_name']); ?></td>
                    <td><?php echo htmlspecialchars($client['patronymic'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($client['phone_number']); ?></td>
                    <td><?php echo htmlspecialchars($client['email'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($client['car'] ?? ''); ?></td>
                    <td class="action-buttons">
                        <a href="edit.php?id=<?php echo $client['client_id']; ?>" class="btn">Редагувати</a>
                        <a href="delete.php?id=<?php echo $client['client_id']; ?>" class="btn btn-danger delete-btn">Видалити</a>
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
            align-items: center;
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert.success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert.error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>

<?php include_once '../includes/footer.php'; ?>