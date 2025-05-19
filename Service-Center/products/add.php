<?php
include_once '../config.php';
include_once '../includes/functions.php';
include_once '../includes/header.php';

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $name = sanitize($_POST['name']);
    $sku = !empty($_POST['sku']) ? sanitize($_POST['sku']) : null;
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $unit = !empty($_POST['unit']) ? sanitize($_POST['unit']) : 'шт';
    $country = !empty($_POST['country']) ? sanitize($_POST['country']) : null;

    // Validate required fields
    if (empty($name)) {
        $errors[] = 'Назва запчастини обов\'язкова';
    }

    if ($price === false || $price <= 0) {
        $errors[] = 'Ціна повинна бути позитивним числом';
    }

    // Check if SKU already exists
    if (!empty($sku)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM spare_parts WHERE sku = ?");
        $stmt->execute([$sku]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            $errors[] = 'Запчастина з таким артикулом вже існує';
        }
    }

    // If no errors, insert the spare part
    if (empty($errors)) {
        $sql = "INSERT INTO spare_parts (name, sku, price, unit, country) 
                VALUES (?, ?, ?, ?, ?)";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $sku, $price, $unit, $country]);

            redirectWithMessage('../products/index.php', 'Запчастина успішно додана');
        } catch (PDOException $e) {
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}
?>

    <h1>Додати запчастину</h1>

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
            <label for="sku">Артикул</label>
            <input type="text" id="sku" name="sku" value="<?php echo $_POST['sku'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="price">Ціна (грн) *</label>
            <input type="number" id="price" name="price" step="0.01" min="0" required
                   value="<?php echo $_POST['price'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label for="unit">Одиниця виміру</label>
            <input type="text" id="unit" name="unit" value="<?php echo $_POST['unit'] ?? 'шт'; ?>">
        </div>

        <div class="form-group">
            <label for="country">Країна виробництва</label>
            <input type="text" id="country" name="country" value="<?php echo $_POST['country'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Зберегти</button>
            <a href="index.php" class="btn">Скасувати</a>
        </div>
    </form>

<?php include_once '../includes/footer.php'; ?>