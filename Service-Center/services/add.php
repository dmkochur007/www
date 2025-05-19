<?php
include_once '../config.php';
include_once '../includes/functions.php';
include_once '../includes/header.php';

$errors = [];

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

    // If no errors, insert the service
    if (empty($errors)) {
        $sql = "INSERT INTO services (name, price) VALUES (?, ?)";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $price]);

            redirectWithMessage('../services/index.php', 'Послуга успішно додана');
        } catch (PDOException $e) {
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}
?>

    <h1>Додати послугу</h1>

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
            <input type="text" id="name" name="name" required value="<?php echo $_POST['name'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="price">Ціна (грн) *</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required
                   value="<?php echo $_POST['price'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Зберегти</button>
            <a href="index.php" class="btn">Скасувати</a>
        </div>
    </form>

<?php include_once '../includes/footer.php'; ?>