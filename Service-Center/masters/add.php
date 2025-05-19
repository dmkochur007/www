<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once '../config.php';
include_once '../includes/functions.php';

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $last_name = isset($_POST['last_name']) ? sanitize($_POST['last_name']) : '';
    $first_name = isset($_POST['first_name']) ? sanitize($_POST['first_name']) : '';
    $phone_number = isset($_POST['phone_number']) ? sanitize($_POST['phone_number']) : '';
    $email = !empty($_POST['email']) ? sanitize($_POST['email']) : null;

    // Validate required fields
    if (empty($last_name)) {
        $errors[] = 'Прізвище обов\'язкове';
    }

    if (empty($first_name)) {
        $errors[] = 'Ім\'я обов\'язкове';
    }

    if (empty($phone_number)) {
        $errors[] = 'Номер телефону обов\'язковий';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Невірний формат email';
    }

    // If no errors, insert the master
    if (empty($errors)) {
        $sql = "INSERT INTO masters (last_name, first_name, phone_number, email) 
                VALUES (?, ?, ?, ?)";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$last_name, $first_name, $phone_number, $email]);

            // Сохраняем сообщение в сессию
            $_SESSION['message'] = 'Майстер успішно доданий';
            $_SESSION['message_type'] = 'success';

            // Выполняем редирект
            header("Location: ../masters/index.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}

include_once '../includes/header.php';
?>

    <div class="actions-bar">
        <h1>Додати майстра</h1>
        <div>
            <a href="index.php" class="btn">Назад до списку</a>
        </div>
    </div>

    <div class="content">
        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="last_name">Прізвище *</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="first_name">Ім'я *</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="phone_number">Номер телефону *</label>
                <input type="tel" id="phone_number" name="phone_number" required value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Зберегти</button>
                <a href="index.php" class="btn btn-danger">Скасувати</a>
            </div>
        </form>
    </div>

<?php include_once '../includes/footer.php'; ?>