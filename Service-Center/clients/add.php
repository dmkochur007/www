<?php
// Начинаем сессию в самом начале файла
session_start();

// Включаем буферизацию вывода, чтобы избежать проблем с header()
ob_start();

include_once '../config.php';
include_once '../includes/functions.php';
include_once '../includes/header.php';

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $last_name = sanitize($_POST['last_name']);
    $first_name = sanitize($_POST['first_name']);
    $patronymic = !empty($_POST['patronymic']) ? sanitize($_POST['patronymic']) : null;
    $phone_number = sanitize($_POST['phone_number']);
    $email = !empty($_POST['email']) ? sanitize($_POST['email']) : null;
    $car = !empty($_POST['car']) ? sanitize($_POST['car']) : null; // Новое поле для автомобиля

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

    // If no errors, insert the client
    if (empty($errors)) {
        $sql = "INSERT INTO clients (last_name, first_name, patronymic, phone_number, email, car) 
                VALUES (?, ?, ?, ?, ?, ?)";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$last_name, $first_name, $patronymic, $phone_number, $email, $car]);

            // Сохраняем сообщение в сессии и делаем прямое перенаправление
            $_SESSION['message'] = 'Клієнт успішно доданий';
            $_SESSION['message_type'] = 'success';

            // Очищаем буфер вывода перед перенаправлением
            ob_end_clean();

            // Используем абсолютный путь для перенаправления
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $redirectUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/index.php';

            header("Location: $redirectUrl");
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}

// Остальной код формы остается без изменений
?>

    <h1>Додати клієнта</h1>

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
            <input type="text" id="last_name" name="last_name" required value="<?php echo $_POST['last_name'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="first_name">Ім'я *</label>
            <input type="text" id="first_name" name="first_name" required value="<?php echo $_POST['first_name'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="patronymic">По батькові</label>
            <input type="text" id="patronymic" name="patronymic" value="<?php echo $_POST['patronymic'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="phone_number">Номер телефону *</label>
            <input type="tel" id="phone_number" name="phone_number" required value="<?php echo $_POST['phone_number'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="car">Автомобіль</label>
            <input type="text" id="car" name="car" value="<?php echo $_POST['car'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Зберегти</button>
            <a href="index.php" class="btn">Скасувати</a>
        </div>
    </form>

<?php
// Закрываем буфер вывода
ob_end_flush();
include_once '../includes/footer.php';
?>