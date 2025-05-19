<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once '../config.php';
include_once '../includes/functions.php';

// Проверяем наличие ID заказа и валидируем его
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectWithMessage('../orders/index.php', 'Помилка: Неправильний ID замовлення', 'error');
    exit;
}

$order_id = (int)$_GET['id'];
$errors = [];

// Проверяем существование заказа
$check_sql = "SELECT * FROM orders WHERE order_id = ?";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute([$order_id]);
$order = $check_stmt->fetch();
if (!$order) {
    redirectWithMessage('../orders/index.php', 'Помилка: Замовлення не знайдено', 'error');
    exit;
}

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Валидация и сбор данных из формы
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $master_id = isset($_POST['master_id']) ? (int)$_POST['master_id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $issue_description = isset($_POST['issue_description']) ? trim($_POST['issue_description']) : '';
    $client_device = isset($_POST['client_device']) ? trim($_POST['client_device']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    $selected_services = [];
    if (isset($_POST['selected_services']) && is_array($_POST['selected_services'])) {
        foreach ($_POST['selected_services'] as $service) {
            $service_id = filter_var($service, FILTER_VALIDATE_INT);
            if ($service_id !== false && $service_id > 0) {
                $selected_services[] = $service_id;
            }
        }
    }

    $selected_parts = [];
    if (isset($_POST['selected_parts']) && is_array($_POST['selected_parts'])) {
        foreach ($_POST['selected_parts'] as $part) {
            $part_id = filter_var($part, FILTER_VALIDATE_INT);
            if ($part_id !== false && $part_id > 0) {
                $selected_parts[] = $part_id;
            }
        }
    }

    $part_quantities = isset($_POST['part_quantity']) ? $_POST['part_quantity'] : [];

    // Простая валидация
    if ($client_id <= 0) {
        $errors[] = 'Будь ласка, виберіть клієнта';
    }

    if ($master_id <= 0) {
        $errors[] = 'Будь ласка, виберіть майстра';
    }

    if (empty($status)) {
        $errors[] = 'Будь ласка, вкажіть статус';
    }

    // Если нет ошибок валидации, обновляем заказ
    if (empty($errors)) {
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();

            // Обновляем информацию о заказе
            $update_sql = "UPDATE orders SET 
                          client_id = ?, 
                          master_id = ?,
                          status = ?,
                          issue_description = ?,
                          client_device = ?,
                          notes = ?
                          WHERE order_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_result = $update_stmt->execute([
                $client_id,
                $master_id,
                $status,
                $issue_description,
                $client_device,
                $notes,
                $order_id
            ]);

            if (!$update_result) {
                throw new PDOException("Помилка оновлення замовлення");
            }

            // Удаляем старые услуги и добавляем новые
            $delete_services_sql = "DELETE FROM order_services WHERE order_id = ?";
            $delete_services_stmt = $pdo->prepare($delete_services_sql);
            $delete_services_stmt->execute([$order_id]);

            if (!empty($selected_services)) {
                $service_sql = "INSERT INTO order_services (order_id, service_id, quantity) VALUES (?, ?, ?)";
                $service_stmt = $pdo->prepare($service_sql);

                foreach ($selected_services as $service_id) {
                    $service_stmt->execute([$order_id, $service_id, 1]);
                }
            }

            // Удаляем старые запчасти и добавляем новые
            $delete_parts_sql = "DELETE FROM order_parts WHERE order_id = ?";
            $delete_parts_stmt = $pdo->prepare($delete_parts_sql);
            $delete_parts_stmt->execute([$order_id]);

            if (!empty($selected_parts)) {
                $part_sql = "INSERT INTO order_parts (order_id, part_id, quantity) VALUES (?, ?, ?)";
                $part_stmt = $pdo->prepare($part_sql);

                foreach ($selected_parts as $key => $part_id) {
                    $quantity = isset($part_quantities[$key]) ? (int)$part_quantities[$key] : 1;
                    if ($quantity < 1) $quantity = 1;
                    $part_stmt->execute([$order_id, $part_id, $quantity]);
                }
            }

            // Фиксируем изменения
            $pdo->commit();

            redirectWithMessage('../orders/index.php', 'Замовлення успішно оновлено');
            exit;

        } catch (PDOException $e) {
            // Откатываем изменения в случае ошибки
            $pdo->rollBack();
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}

// Загружаем выбранные услуги
$services_sql = "SELECT os.service_id, s.name, s.price 
                FROM order_services os
                JOIN services s ON os.service_id = s.service_id
                WHERE os.order_id = ?";
$services_stmt = $pdo->prepare($services_sql);
$services_stmt->execute([$order_id]);
$selected_services = $services_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Загружаем выбранные запчасти с количеством
$parts_sql = "SELECT op.part_id, op.quantity, p.name, p.code, p.price 
              FROM order_parts op
              JOIN products p ON op.part_id = p.product_id
              WHERE op.order_id = ?";
$parts_stmt = $pdo->prepare($parts_sql);
$parts_stmt->execute([$order_id]);
$selected_parts_data = $parts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Загружаем список клиентов
$clients_query = $pdo->query("SELECT client_id, first_name, last_name, phone_number FROM clients ORDER BY last_name, first_name");
$clients = $clients_query->fetchAll();

// Загружаем список мастеров
$masters_query = $pdo->query("SELECT master_id, first_name, last_name FROM masters ORDER BY last_name, first_name");
$masters = $masters_query->fetchAll();

// Загружаем список услуг - исправленный запрос без поля description
$services_query = $pdo->query("SELECT service_id, name, price FROM services ORDER BY name");
$services = $services_query->fetchAll();

include_once '../includes/header.php';
?>

    <div class="actions-bar">
        <h1>Редагування замовлення #<?php echo $order_id; ?></h1>
        <div>
            <a href="index.php" class="btn">Назад до списку</a>
        </div>
    </div>

    <div class="content">
        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="edit.php?id=<?php echo $order_id; ?>" id="orderForm">
            <div class="form-group">
                <label for="client_id">Клієнт:</label>
                <select name="client_id" id="client_id" required>
                    <option value="">Виберіть клієнта</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['client_id']; ?>" <?php echo $client['client_id'] == $order['client_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['last_name'] . ' ' . $client['first_name'] . ' (' . $client['phone_number'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="master_id">Майстер:</label>
                <select name="master_id" id="master_id" required>
                    <option value="">Виберіть майстра</option>
                    <?php foreach ($masters as $master): ?>
                        <option value="<?php echo $master['master_id']; ?>" <?php echo $master['master_id'] == $order['master_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($master['last_name'] . ' ' . $master['first_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Статус:</label>
                <select name="status" id="status" required>
                    <option value="Нове" <?php echo $order['status'] === 'Нове' ? 'selected' : ''; ?>>Нове</option>
                    <option value="В роботі" <?php echo $order['status'] === 'В роботі' ? 'selected' : ''; ?>>В роботі</option>
                    <option value="Завершено" <?php echo $order['status'] === 'Завершено' ? 'selected' : ''; ?>>Завершено</option>
                    <option value="Відміненo" <?php echo $order['status'] === 'Відміненo' ? 'selected' : ''; ?>>Відміненo</option>
                </select>
            </div>

            <div class="form-group">
                <label for="issue_description">Опис проблеми:</label>
                <textarea name="issue_description" id="issue_description" rows="4"><?php echo htmlspecialchars($order['issue_description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="client_device">Авто клієнта:</label>
                <textarea name="client_device" id="client_device" rows="3"><?php echo htmlspecialchars($order['client_device']); ?></textarea>
            </div>

            <h2>Послуги</h2>
            <div class="services-container">
                <div class="services-list">
                    <?php foreach ($services as $service): ?>
                        <div class="service-item <?php echo in_array($service['service_id'], $selected_services) ? 'service-selected' : ''; ?>">
                            <div class="service-checkbox">
                                <input
                                        type="checkbox"
                                        name="selected_services[]"
                                        id="service-<?php echo $service['service_id']; ?>"
                                        value="<?php echo $service['service_id']; ?>"
                                    <?php echo in_array($service['service_id'], $selected_services) ? 'checked' : ''; ?>
                                >
                            </div>
                            <label for="service-<?php echo $service['service_id']; ?>" class="service-label">
                                <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                <div class="service-price"><?php echo number_format($service['price'], 2); ?> грн</div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Запчасти -->
            <h2>Запчастини</h2>
            <div class="parts-container">
                <div class="parts-search">
                    <input type="text" id="partAutocomplete" placeholder="Пошук запчастини...">
                    <div id="partSuggestions"></div>
                </div>

                <div id="selectedParts">
                    <?php foreach ($selected_parts_data as $part): ?>
                        <div class="part-row" data-part-id="<?php echo $part['part_id']; ?>">
                        <span class="part-name">
                            <?php echo htmlspecialchars($part['name'] . ($part['code'] ? ' (' . $part['code'] . ')' : '')); ?>
                        </span>
                            <input type="hidden" name="selected_parts[]" value="<?php echo $part['part_id']; ?>">
                            <input type="number" name="part_quantity[]" min="1" value="<?php echo (int)$part['quantity']; ?>" class="part-quantity">
                            <span class="part-price"><?php echo number_format($part['price'], 2); ?> грн</span>
                            <button type="button" class="btn btn-small btn-danger remove-part">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Примітки:</label>
                <textarea name="notes" id="notes" rows="3"><?php echo htmlspecialchars($order['notes']); ?></textarea>
            </div>

            <!-- Отображение общей суммы -->
            <div class="form-total">
                <h3>Загальна сума: <span id="total-price">0.00 грн</span></h3>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Зберегти зміни</button>
                <a href="index.php" class="btn btn-danger">Скасувати</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const partAutocomplete = document.getElementById('partAutocomplete');
            const partSuggestions = document.getElementById('partSuggestions');
            const selectedParts = document.getElementById('selectedParts');

            // Функция для обработки поиска запчастей
            function searchParts(query) {
                if (query.length < 2) {
                    partSuggestions.innerHTML = '';
                    return;
                }

                fetch('../api/search_parts.php?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(parts => {
                        partSuggestions.innerHTML = '';

                        if (parts.length === 0) {
                            const noResults = document.createElement('div');
                            noResults.className = 'suggestion-item no-results';
                            noResults.textContent = 'Запчастини не знайдено';
                            partSuggestions.appendChild(noResults);
                            return;
                        }

                        parts.forEach(part => {
                            const item = document.createElement('div');
                            item.className = 'suggestion-item';
                            item.innerHTML = `
                        <div>${escapeHtml(part.name)}</div>
                        <div class="suggestion-code">${part.code ? escapeHtml(part.code) : ''}</div>
                        <div class="suggestion-price">${Number(part.price).toFixed(2)} грн</div>
                    `;
                            item.addEventListener('click', () => {
                                addPart(part);
                                partSuggestions.innerHTML = '';
                                calculateTotal();
                            });
                            partSuggestions.appendChild(item);
                        });
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        partSuggestions.innerHTML = '<div class="suggestion-item error">Помилка пошуку</div>';
                    });
            }

            partAutocomplete.addEventListener('input', function() {
                searchParts(this.value);
            });

            document.addEventListener('click', function(e) {
                if (!partAutocomplete.contains(e.target) && !partSuggestions.contains(e.target)) {
                    partSuggestions.innerHTML = '';
                }
            });

            selectedParts.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-part')) {
                    e.target.closest('.part-row').remove();
                    calculateTotal();
                }
            });

            function addPart(part) {
                if (!part.product_id || isNaN(parseInt(part.product_id))) {
                    console.error('Invalid part ID:', part);
                    return;
                }

                if (selectedParts.querySelector(`[data-part-id="${part.product_id}"]`)) {
                    alert('Ця запчастина вже додана!');
                    return;
                }

                const row = document.createElement('div');
                row.className = 'part-row';
                row.dataset.partId = part.product_id;

                row.innerHTML = `
            <span class="part-name">${escapeHtml(part.name)} ${part.code ? '(' + escapeHtml(part.code) + ')' : ''}</span>
            <input type="hidden" name="selected_parts[]" value="${parseInt(part.product_id)}">
            <input type="number" name="part_quantity[]" min="1" value="1" class="part-quantity">
            <span class="part-price">${Number(part.price).toFixed(2)} грн</span>
            <button type="button" class="btn btn-small btn-danger remove-part">✕</button>
        `;

                selectedParts.appendChild(row);
                partAutocomplete.value = '';

                // Добавляем обработчик события для поля количества
                const quantityInput = row.querySelector('.part-quantity');
                quantityInput.addEventListener('change', calculateTotal);
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Функция для расчета общей суммы
            function calculateTotal() {
                let total = 0;

                // Считаем сумму услуг
                document.querySelectorAll('input[name="selected_services[]"]:checked').forEach(function(checkbox) {
                    const serviceItem = checkbox.closest('.selection-item');
                    const priceText = serviceItem.querySelector('.item-price').textContent;
                    const price = parseFloat(priceText.replace(/[^\d.,]/g, '').replace(',', '.'));

                    if (!isNaN(price)) {
                        total += price;
                    }
                });

                // Считаем сумму запчастей
                document.querySelectorAll('.part-row').forEach(function(row) {
                    const priceText = row.querySelector('.part-price').textContent;
                    const price = parseFloat(priceText.replace(/[^\d.,]/g, '').replace(',', '.'));
                    const quantity = parseInt(row.querySelector('.part-quantity').value) || 1;

                    if (!isNaN(price)) {
                        total += price * quantity;
                    }
                });

                // Отображаем общую сумму
                document.getElementById('total-price').textContent = total.toFixed(2) + ' грн';
            }

            // Добавляем обработчики событий для изменения выбора услуг
            document.querySelectorAll('input[name="selected_services[]"]').forEach(function(checkbox) {
                checkbox.addEventListener('change', calculateTotal);
            });

            // Обновляем сумму при изменении количества запчастей
            selectedParts.addEventListener('change', function(e) {
                if (e.target.classList.contains('part-quantity')) {
                    calculateTotal();
                }
            });

            // Запускаем расчет при загрузке страницы
            calculateTotal();
        });
    </script>

    <style>
        /* Стили для формы редактирования, адаптированные к цветовой схеме */
        .selection-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .selection-item {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            position: relative;
            transition: all 0.2s ease;
        }

        .selection-item:hover {
            box-shadow: 0 0 10px rgba(147, 85, 255, 0.2);
            transform: translateY(-2px);
        }

        .selection-item input[type="checkbox"] {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .selection-item input[type="checkbox"]:checked + label .item-name {
            color: var(--accent-color);
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 10px;
            padding-right: 25px;
            font-size: 1.05rem;
        }

        .item-price {
            color: var(--accent-color);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .parts-container {
            margin: 25px 0;
        }

        .parts-search {
            margin-bottom: 20px;
            position: relative;
        }

        #partAutocomplete {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        #partSuggestions {
            position: absolute;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 8px 8px;
            z-index: 10;
            box-shadow: var(--box-shadow);
            display: none;
        }

        #partSuggestions:not(:empty) {
            display: block;
        }

        .suggestion-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
        }

        .suggestion-item:hover {
            background-color: var(--table-hover);
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-code {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .suggestion-price {
            color: var(--accent-color);
            font-weight: 600;
            margin-top: 5px;
        }

        .part-row {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .part-name {
            flex-grow: 1;
        }

        .part-quantity {
            width: 70px;
            padding: 8px;
            margin: 0 15px;
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-color);
            text-align: center;
        }

        .part-price {
            margin-right: 15px;
            font-weight: 600;
            color: var(--accent-color);
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .form-total {
            margin: 30px 0;
            padding: 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            text-align: right;
            border: 1px solid var(--border-color);
        }

        .form-total h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        #total-price {
            color: var(--accent-color);
            font-size: 1.4rem;
            font-weight: 700;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .selection-container {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button, .form-actions a {
                width: 100%;
                margin-bottom: 10px;
                text-align: center;
            }
        }

        /* Стили для секции услуг с улучшенной поддержкой длинных названий */
        .services-container {
            margin: 25px 0;
            padding: 20px;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: var(--box-shadow);
        }

        .services-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 450px;
            overflow-y: auto;
            padding: 5px;
        }

        .service-item {
            display: flex;
            align-items: center;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            flex-wrap: wrap; /* Позволяет содержимому переноситься на следующую строку при необходимости */
        }

        .service-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .service-selected {
            background: rgba(147, 85, 255, 0.1);
            border: 1px solid var(--accent-color);
        }

        .service-checkbox {
            margin-right: 15px;
            align-self: flex-start; /* Выравнивание чекбокса по верху */
            padding-top: 3px; /* Небольшое смещение вниз для лучшего выравнивания */
        }

        .service-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .service-label {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Выравнивание по верху для длинных названий */
            width: calc(100% - 35px); /* Учитываем ширину чекбокса */
            cursor: pointer;
            flex-wrap: wrap; /* Разрешаем перенос на новую строку */
            gap: 10px; /* Отступ между названием и ценой */
        }

        .service-name {
            font-weight: 500;
            font-size: 1.05rem;
            flex: 1 1 70%; /* Название занимает большую часть, но может сжиматься */
            word-break: break-word; /* Разрешаем перенос длинных слов */
            line-height: 1.4; /* Улучшаем читаемость длинных названий */
            padding-right: 10px; /* Отступ от цены */
        }

        .service-price {
            font-weight: 600;
            color: var(--accent-color);
            font-size: 1.1rem;
            background: rgba(147, 85, 255, 0.1);
            padding: 6px 12px;
            border-radius: 20px;
            min-width: 100px;
            text-align: center;
            flex: 0 0 auto; /* Цена не сжимается */
            white-space: nowrap; /* Цена не переносится */
            align-self: center; /* Центрируем цену вертикально */
        }

        /* Для очень длинных названий или на маленьких экранах */
        @media (max-width: 768px) {
            .service-label {
                flex-direction: column;
                width: calc(100% - 35px);
            }

            .service-name {
                margin-bottom: 8px;
                width: 100%;
            }

            .service-price {
                align-self: flex-start;
            }
        }

        /* Стили для кастомных чекбоксов */
        .service-checkbox input[type="checkbox"] {
            position: relative;
            cursor: pointer;
        }

        .service-checkbox input[type="checkbox"]:before {
            content: "";
            display: block;
            position: absolute;
            width: 20px;
            height: 20px;
            top: 0;
            left: 0;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-color);
            border-radius: 5px;
        }

        .service-checkbox input[type="checkbox"]:checked:before {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .service-checkbox input[type="checkbox"]:checked:after {
            content: '';
            display: block;
            position: absolute;
            width: 5px;
            height: 10px;
            border: solid var(--primary-bg);
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
            top: 4px;
            left: 8px;
        }

        /* Добавьте стили для скроллбара в списке услуг */
        .services-list::-webkit-scrollbar {
            width: 8px;
        }

        .services-list::-webkit-scrollbar-track {
            background: var(--primary-bg);
            border-radius: 10px;
        }

        .services-list::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }

        .services-list::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
        }
    </style>

<?php include_once '../includes/footer.php'; ?>