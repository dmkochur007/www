<?php
include_once '../config.php';
include_once '../includes/functions.php';
include_once '../includes/header.php';

$errors = [];
$product = null;

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../products/index.php', 'ID запчастини не вказано', 'error');
}

$product_id = (int)$_GET['id'];

// Add debugging
echo "<div style='background-color: #222; color: #fff; padding: 10px; margin-bottom: 15px; border: 1px solid #444; border-radius: 5px;'>";
echo "<strong>Debug:</strong> Loading product ID: {$product_id}<br>";

// Get product data
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        echo "Product not found in database!<br>";
        redirectWithMessage('../products/index.php', 'Запчастина не знайдена', 'error');
    } else {
        echo "Product found: " . htmlspecialchars($product['name']) . "<br>";
        echo "Product details: <pre>";
        print_r($product);
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
    redirectWithMessage('../products/index.php', 'Помилка бази даних: ' . $e->getMessage(), 'error');
}
echo "</div>";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $name = sanitize($_POST['name']);
    $code = !empty($_POST['code']) ? sanitize($_POST['code']) : null;
    $price = !empty($_POST['price']) ? filter_var($_POST['price'], FILTER_VALIDATE_FLOAT) : null;
    $unit = !empty($_POST['unit']) ? sanitize($_POST['unit']) : 'шт';

    // Validate required fields
    if (empty($name)) {
        $errors[] = 'Назва запчастини обов\'язкова';
    }

    // Check if code already exists and it's not the current product
    if (!empty($code) && $code !== $product['code']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE code = ? AND product_id != ?");
        $stmt->execute([$code, $product_id]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            $errors[] = 'Запчастина з таким кодом вже існує';
        }
    }

    // If no errors, update the product
    if (empty($errors)) {
        try {
            // Show debug info for the update operation
            echo "<div style='background-color: #222; color: #fff; padding: 10px; margin-bottom: 15px; border: 1px solid #444; border-radius: 5px;'>";
            echo "<strong>Debug Update:</strong><br>";
            echo "Updating product ID: {$product_id}<br>";
            echo "Name: " . htmlspecialchars($name) . "<br>";
            echo "Code: " . htmlspecialchars($code ?? 'NULL') . "<br>";
            echo "Price: " . ($price ?? 'NULL') . "<br>";
            echo "Unit: " . htmlspecialchars($unit) . "<br>";

            // Construct the update SQL based on which fields have values
            $updateFields = [];
            $params = [];

            // Name is always updated (it's required)
            $updateFields[] = "name = ?";
            $params[] = $name;

            // Code might be NULL
            $updateFields[] = "code = ?";
            $params[] = $code;

            // Price might be NULL
            $updateFields[] = "price = ?";
            $params[] = $price;

            // Unit will have a default of 'шт' if empty
            $updateFields[] = "unit = ?";
            $params[] = $unit;

            // Add product_id at the end of params array
            $params[] = $product_id;

            $sql = "UPDATE products SET " . implode(", ", $updateFields) . " WHERE product_id = ?";
            echo "SQL: " . $sql . "<br>";
            echo "Params: "; print_r($params); echo "<br>";
            echo "</div>";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            redirectWithMessage('../products/index.php', 'Запчастина успішно оновлена');
        } catch (PDOException $e) {
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}
?>

    <h1>Редагувати запчастину</h1>

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
                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($product['name']); ?>">
        </div>

        <div class="form-group">
            <label for="code">Код</label>
            <input type="text" id="code" name="code"
                   value="<?php echo isset($_POST['code']) ? htmlspecialchars($_POST['code']) : htmlspecialchars($product['code'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="price">Ціна (грн)</label>
            <input type="number" id="price" name="price" step="0.01" min="0"
                   value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : htmlspecialchars($product['price'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="unit">Одиниця виміру</label>
            <input type="text" id="unit" name="unit"
                   value="<?php echo isset($_POST['unit']) ? htmlspecialchars($_POST['unit']) : htmlspecialchars($product['unit'] ?? 'шт'); ?>">
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Зберегти</button>
            <a href="../products/index.php" class="btn">Скасувати</a>
        </div>
    </form>

<?php include_once '../includes/footer.php'; ?>