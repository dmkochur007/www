<?php
include_once '../config.php';
include_once '../includes/functions.php';
include_once '../includes/header.php';

$errors = [];
$client = null;

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../clients/index.php', 'ID клієнта не вказано', 'error');
}

$client_id = (int)$_GET['id'];

// Get client data
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        redirectWithMessage('../clients/index.php', 'Клієнт не знайдений', 'error');
    }
} catch (PDOException $e) {
    redirectWithMessage('../clients/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $last_name = sanitize($_POST['last_name']);
    $first_name = sanitize($_POST['first_name']);
    $patronymic = !empty($_POST['patronymic']) ? sanitize($_POST['patronymic']) : null;
    $phone_number = sanitize($_POST['phone_number']);
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

    // If no errors, update the client
    if (empty($errors)) {
        $sql = "UPDATE clients 
                SET last_name = ?, first_name = ?, patronymic = ?, phone_number = ?, email = ? 
                WHERE client_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$last_name, $first_name, $patronymic, $phone_number, $email, $client_id]);

            // Redirect back to clients list with success message
            redirectWithMessage('../clients/index.php', 'Клієнт успішно оновлений');
        } catch (PDOException $e) {
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}
?>

    <h1>Редагувати клієнта</h1>

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
            <input type="text" id="last_name" name="last_name" required
                   value="<?php echo $_POST['last_name'] ?? htmlspecialchars($client['last_name']); ?>">
        </div>

        <div class="form-group">
            <label for="first_name">Ім'я *</label>
            <input type="text" id="first_name" name="first_name" required
                   value="<?php echo $_POST['first_name'] ?? htmlspecialchars($client['first_name']); ?>">
        </div>

        <div class="form-group">
            <label for="patronymic">По батькові</label>
            <input type="text" id="patronymic" name="patronymic"
                   value="<?php echo $_POST['patronymic'] ?? htmlspecialchars($client['patronymic'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="phone_number">Номер телефону *</label>
            <input type="tel" id="phone_number" name="phone_number" required
                   value="<?php echo $_POST['phone_number'] ?? htmlspecialchars($client['phone_number']); ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?php echo $_POST['email'] ?? htmlspecialchars($client['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Зберегти</button>
            <a href="index.php" class="btn">Скасувати</a>
        </div>
    </form>

<?php include_once '../includes/footer.php'; ?>