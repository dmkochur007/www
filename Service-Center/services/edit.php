<?php
include_once '../config.php';
include_once '../includes/functions.php';
include_once '../includes/header.php';

$errors = [];
$service = null;

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../services/index.php', 'ID послуги не вказано', 'error');
}

$service_id = (int)$_GET['id'];

// Get service data
try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();

    if (!$service) {
        redirectWithMessage('../services/index.php', 'Послуга не знайдена', 'error');
    }
} catch (PDOException $e) {
    redirectWithMessage('../services/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $name = sanitize($_POST['name']);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);

    // Validate required fields
    if (empty($name)) {
        $errors[] = 'Назва послуги обов\'язкова';
    }

    if ($price === false || $price <= 0) {
        $errors[] = 'Ціна повинна бути позитивним числом';
    }

    // If no errors, update the service
    if (empty($errors)) {
        $sql = "UPDATE services SET name = ?, price = ? WHERE service_id = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $price, $service_id]);

            redirectWithMessage('../services/index.php', 'Послуга успішно оновлена');
        } catch (PDOException $e) {
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}
?>

    <h1>Редагувати послугу</h1>

<?php if (!empty($errors)): ?>
    <div class="alert error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo $error; ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="name">Назва *</label>
            <input type="text" id="name" name="name" required
                   value="<?php echo $_POST['name'] ?? htmlspecialchars($service['name']); ?>">
        </div>

        <div class="form-group">
            <label for="price">Ціна (грн) *</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required
                   value="<?php echo $_POST['price'] ?? htmlspecialchars($service['price']); ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Зберегти</button>
            <a href="index.php" class="btn">Скасувати</a>
        </div>
    </form>

<?php include_once '../includes/footer.php'; ?>