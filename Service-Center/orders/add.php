<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include_once '../config.php';
include_once '../includes/functions.php';

$errors = [];

// Проверяем, инициализирована ли сессия
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Получаем список клиентов с их автомобилями
$clients_query = $pdo->query("SELECT client_id, first_name, last_name, phone_number, car FROM clients ORDER BY last_name, first_name");
$clients = $clients_query->fetchAll();

// Получаем список мастеров
$masters_query = $pdo->query("SELECT master_id, first_name, last_name FROM masters ORDER BY last_name, first_name");
$masters = $masters_query->fetchAll();

// Получаем список услуг
$services_query = $pdo->query("SELECT service_id, name, price FROM services ORDER BY name");
$services = $services_query->fetchAll();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем и очищаем данные
    $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
    $master_id = isset($_POST['master_id']) ? intval($_POST['master_id']) : 0;
    $status = isset($_POST['status']) ? sanitize($_POST['status']) : 'Нове';
    $issue_description = isset($_POST['issue_description']) ? sanitize($_POST['issue_description']) : '';
    $client_device = isset($_POST['client_device']) ? sanitize($_POST['client_device']) : '';
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';

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

    // Валидация
    if ($client_id <= 0) {
        $errors[] = 'Виберіть клієнта';
    }

    if ($master_id <= 0) {
        $errors[] = 'Виберіть майстра';
    }

    // Если нет ошибок, сохраняем заказ
    if (empty($errors)) {
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();

            // Вставляем заказ
            $order_sql = "INSERT INTO orders (client_id, master_id, order_date, status, issue_description, client_device, notes) 
                         VALUES (?, ?, NOW(), ?, ?, ?, ?)";

            $order_stmt = $pdo->prepare($order_sql);
            $order_result = $order_stmt->execute([
                $client_id,
                $master_id,
                $status,
                $issue_description,
                $client_device,
                $notes
            ]);

            $order_id = $pdo->lastInsertId();

            // Сохраняем выбранные услуги
            if (!empty($selected_services)) {
                $service_sql = "INSERT INTO order_services (order_id, service_id, quantity) VALUES (?, ?, ?)";
                $service_stmt = $pdo->prepare($service_sql);

                foreach ($selected_services as $service_id) {
                    $service_stmt->execute([$order_id, $service_id, 1]);
                }
            }

            // Сохраняем выбранные запчасти
            if (!empty($selected_parts)) {
                $part_sql = "INSERT INTO order_parts (order_id, part_id, quantity) VALUES (?, ?, ?)";
                $part_stmt = $pdo->prepare($part_sql);

                foreach ($selected_parts as $key => $part_id) {
                    $quantity = isset($part_quantities[$key]) ? (int)$part_quantities[$key] : 1;
                    if ($quantity < 1) $quantity = 1;
                    $part_stmt->execute([$order_id, $part_id, $quantity]);
                }
            }

            // Фиксируем транзакцию
            $pdo->commit();

            // Сохраняем сообщение об успехе
            $_SESSION['message'] = 'Замовлення успішно створено';
            $_SESSION['message_type'] = 'success';

            // Редирект на страницу списка
            header("Location: index.php");
            exit;

        } catch (PDOException $e) {
            // Отменяем транзакцию в случае ошибки
            $pdo->rollBack();
            $errors[] = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}

include_once '../includes/header.php';
?>

    <div class="actions-bar">
        <h1>Нове замовлення</h1>
        <div>
            <a href="index.php" class="btn">Назад до списку</a>
        </div>
    </div>

    <div class="content">
        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" id="orderForm">
            <div class="form-group">
                <label for="client_id">Клієнт *</label>
                <select name="client_id" id="client_id" required class="styled-select">
                    <option value="">Виберіть клієнта</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['client_id']; ?>"
                            <?php echo (isset($_POST['client_id']) && $_POST['client_id'] == $client['client_id']) ? 'selected' : ''; ?>
                                data-car="<?php echo htmlspecialchars($client['car'] ?? ''); ?>">
                            <?php echo htmlspecialchars($client['last_name'] . ' ' . $client['first_name'] . ' (' . $client['phone_number'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="master_id">Майстер *</label>
                <select name="master_id" id="master_id" required class="styled-select">
                    <option value="">Виберіть майстра</option>
                    <?php foreach ($masters as $master): ?>
                        <option value="<?php echo $master['master_id']; ?>" <?php echo (isset($_POST['master_id']) && $_POST['master_id'] == $master['master_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($master['last_name'] . ' ' . $master['first_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Статус</label>
                <select name="status" id="status" class="styled-select">
                    <option value="Нове" selected>Нове</option>
                    <option value="В роботі" <?php echo (isset($_POST['status']) && $_POST['status'] == 'В роботі') ? 'selected' : ''; ?>>В роботі</option>
                    <option value="Завершено" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Завершено') ? 'selected' : ''; ?>>Завершено</option>
                    <option value="Відміненo" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Відміненo') ? 'selected' : ''; ?>>Відміненo</option>
                </select>
            </div>

            <div class="form-group">
                <label for="issue_description">Опис проблеми</label>
                <textarea name="issue_description" id="issue_description" rows="4"><?php echo isset($_POST['issue_description']) ? htmlspecialchars($_POST['issue_description']) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="client_device">Авто клієнта</label>
                <textarea name="client_device" id="client_device" rows="3" data-edited="false"><?php echo isset($_POST['client_device']) ? htmlspecialchars($_POST['client_device']) : ''; ?></textarea>
                <div class="form-hint">Буде автоматично заповнено даними про автомобіль при виборі клієнта</div>
            </div>

            <!-- Услуги -->
            <h2>Послуги</h2>
            <div class="services-container">
                <div class="services-search">
                    <input type="text" id="serviceSearch" placeholder="Пошук послуг..." autocomplete="off">
                </div>
                <div class="services-list">
                    <?php foreach ($services as $service): ?>
                        <div class="service-item">
                            <div class="service-checkbox">
                                <input
                                        type="checkbox"
                                        name="selected_services[]"
                                        id="service-<?php echo $service['service_id']; ?>"
                                        value="<?php echo $service['service_id']; ?>"
                                    <?php echo (isset($_POST['selected_services']) && in_array($service['service_id'], $_POST['selected_services'])) ? 'checked' : ''; ?>
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
                    <input type="text" id="partAutocomplete" placeholder="Пошук запчастини..." autocomplete="off">
                    <div id="partSuggestions"></div>
                </div>

                <div id="selectedParts">
                    <!-- Здесь будут отображаться выбранные запчасти -->
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Примітки</label>
                <textarea name="notes" id="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
            </div>

            <!-- Отображение общей суммы -->
            <div class="form-total">
                <h3>Загальна сума: <span id="total-price">0.00 грн</span></h3>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Зберегти</button>
                <a href="index.php" class="btn btn-danger">Скасувати</a>
            </div>
        </form>
    </div>

    <style>
        /* Улучшенные стили для селектов с улучшенным контрастом */
        .styled-select {
            background-color: var(--card-bg) !important;
            border: 1px solid var(--accent-color) !important;
            color: var(--text-color) !important;
            padding: 12px 15px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            outline: none !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%239355ff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            background-size: 16px !important;
            padding-right: 40px !important;
        }

        .styled-select:focus {
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 0 2px rgba(147, 85, 255, 0.2) !important;
        }

        .styled-select option {
            background-color: var(--card-bg) !important;
            color: var(--text-color) !important;
            padding: 8px !important;
        }

        /* Стили для контейнера услуг */
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
            margin-top: 15px;
        }

        .service-item {
            display: flex;
            align-items: center;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
            flex-wrap: wrap;
        }

        .service-selected {
            background: rgba(147, 85, 255, 0.1);
            border: 1px solid var(--accent-color);
        }

        .service-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .service-checkbox {
            margin-right: 15px;
            align-self: flex-start;
            padding-top: 3px;
        }

        .service-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .service-label {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            width: calc(100% - 35px);
            cursor: pointer;
            flex-wrap: wrap;
            gap: 10px;
        }

        .service-name {
            font-weight: 500;
            font-size: 1.05rem;
            flex: 1 1 70%;
            word-break: break-word;
            line-height: 1.4;
            padding-right: 10px;
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
            flex: 0 0 auto;
            white-space: nowrap;
            align-self: center;
        }

        /* Стили для поиска услуг */
        .services-search input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            font-size: 1rem;
        }

        .services-search input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(147, 85, 255, 0.2);
            outline: none;
        }

        /* Стили для запчастей */
        .parts-container {
            margin: 25px 0;
            padding: 20px;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: var(--box-shadow);
        }

        .parts-search {
            position: relative;
            margin-bottom: 20px;
        }

        .parts-search input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-color);
            font-size: 1rem;
        }

        .parts-search input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(147, 85, 255, 0.2);
            outline: none;
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
            background-color: rgba(255, 255, 255, 0.05);
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
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .part-name {
            flex-grow: 1;
            word-break: break-word;
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

        /* Улучшенная видимость для мобильных устройств */
        @media (max-width: 768px) {
            .service-label {
                flex-direction: column;
            }

            .service-name {
                margin-bottom: 10px;
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        .form-hint {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
            font-style: italic;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Функция для поиска услуг
            const serviceSearch = document.getElementById('serviceSearch');
            const serviceItems = document.querySelectorAll('.service-item');

            if (serviceSearch) {
                serviceSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();

                    serviceItems.forEach(item => {
                        const serviceName = item.querySelector('.service-name').textContent.toLowerCase();

                        if (serviceName.includes(searchTerm) || searchTerm === '') {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            // Обработка выбора услуг
            serviceItems.forEach(item => {
                // Инициализируем стиль для выбранных элементов
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox.checked) {
                    item.classList.add('service-selected');
                }

                // При клике на любую часть элемента переключаем чекбокс
                item.addEventListener('click', function(e) {
                    // Предотвращаем клик, если пользователь кликнул на сам чекбокс
                    if (e.target.type !== 'checkbox') {
                        const checkbox = this.querySelector('input[type="checkbox"]');
                        checkbox.checked = !checkbox.checked;

                        // Добавляем/убираем класс selected
                        if (checkbox.checked) {
                            this.classList.add('service-selected');
                        } else {
                            this.classList.remove('service-selected');
                        }

                        // Вызываем событие change для пересчета общей суммы
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });

                // Обрабатываем изменения чекбокса для добавления/удаления класса
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        item.classList.add('service-selected');
                    } else {
                        item.classList.remove('service-selected');
                    }

                    calculateTotal();
                });
            });

            // Обработка поиска и выбора запчастей
            const partAutocomplete = document.getElementById('partAutocomplete');
            const partSuggestions = document.getElementById('partSuggestions');
            const selectedParts = document.getElementById('selectedParts');

            if (partAutocomplete) {
                partAutocomplete.addEventListener('input', function() {
                    searchParts(this.value);
                });

                document.addEventListener('click', function(e) {
                    if (!partAutocomplete.contains(e.target) && !partSuggestions.contains(e.target)) {
                        partSuggestions.innerHTML = '';
                    }
                });
            }

            // Функция для поиска запчастей
            function searchParts(query) {
                if (query.length < 2) {
                    partSuggestions.innerHTML = '';
                    return;
                }

                // Замените на ваш реальный API-эндпоинт
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

            // Обработчик для удаления запчастей
            if (selectedParts) {
                selectedParts.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-part')) {
                        e.target.closest('.part-row').remove();
                        calculateTotal();
                    }
                });

                // Обновляем сумму при изменении количества запчастей
                selectedParts.addEventListener('change', function(e) {
                    if (e.target.classList.contains('part-quantity')) {
                        calculateTotal();
                    }
                });
            }

            // Функция для добавления запчасти в список
            function addPart(part) {
                if (!part.product_id || selectedParts.querySelector(`[data-part-id="${part.product_id}"]`)) {
                    return;
                }

                const row = document.createElement('div');
                row.className = 'part-row';
                row.dataset.partId = part.product_id;

                row.innerHTML = `
            <span class="part-name">${escapeHtml(part.name)} ${part.code ? '(' + escapeHtml(part.code) + ')' : ''}</span>
            <input type="hidden" name="selected_parts[]" value="${part.product_id}">
            <input type="number" name="part_quantity[]" min="1" value="1" class="part-quantity">
            <span class="part-price">${Number(part.price).toFixed(2)} грн</span>
            <button type="button" class="btn btn-small btn-danger remove-part">✕</button>
        `;

                selectedParts.appendChild(row);
                partAutocomplete.value = '';

                // Добавляем обработчик события для поля количества
                const quantityInput = row.querySelector('.part-quantity');
                quantityInput.addEventListener('change', calculateTotal);

                calculateTotal();
            }

            // Вспомогательная функция для экранирования HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Функция расчета общей суммы
            function calculateTotal() {
                let total = 0;

                // Считаем сумму услуг
                document.querySelectorAll('input[name="selected_services[]"]:checked').forEach(function(checkbox) {
                    const serviceItem = checkbox.closest('.service-item');
                    const priceText = serviceItem.querySelector('.service-price').textContent;
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
                const totalPriceElement = document.getElementById('total-price');
                if (totalPriceElement) {
                    totalPriceElement.textContent = total.toFixed(2) + ' грн';
                }
            }

            // Запускаем расчет при загрузке страницы
            calculateTotal();
        });
        // Автозаполнение информации об автомобиле
        // Автозаполнение информации об автомобиле
        const clientSelect = document.getElementById('client_id');
        const clientDeviceField = document.getElementById('client_device');

        // Создаем объект для хранения данных об автомобилях клиентов
        const clientCars = {
            <?php foreach ($clients as $client): ?>
            <?php if (!empty($client['car'])): ?>
            "<?php echo $client['client_id']; ?>": "<?php echo htmlspecialchars(trim($client['car']), ENT_QUOTES); ?>",
            <?php endif; ?>
            <?php endforeach; ?>
        };

        // Храним предыдущий выбранный ID клиента
        let previousClientId = clientSelect ? clientSelect.value : '';

        // Обработчик изменения выбора клиента
        if (clientSelect && clientDeviceField) {
            clientSelect.addEventListener('change', function() {
                const selectedClientId = this.value;

                // Проверяем, изменился ли выбранный клиент
                if (selectedClientId !== previousClientId) {
                    // Если поле было заполнено автоматически ранее или оно пустое, то обновляем данные
                    if (clientDeviceField.dataset.autoFilled === 'true' || clientDeviceField.value === '') {
                        if (selectedClientId && clientCars[selectedClientId]) {
                            clientDeviceField.value = 'Автомобіль: ' + clientCars[selectedClientId];
                            clientDeviceField.dataset.autoFilled = 'true';
                        } else {
                            // Если у нового клиента нет информации об автомобиле, очищаем поле
                            clientDeviceField.value = '';
                            clientDeviceField.dataset.autoFilled = 'false';
                        }
                    }

                    // Сохраняем текущий ID клиента как предыдущий для следующего изменения
                    previousClientId = selectedClientId;
                }
            });

            // Отслеживаем ручное редактирование поля
            clientDeviceField.addEventListener('input', function() {
                // Если поле редактируется вручную, отмечаем это
                if (clientDeviceField.dataset.autoFilled === 'true' && this.value !== 'Автомобіль: ' + clientCars[clientSelect.value]) {
                    clientDeviceField.dataset.autoFilled = 'false';
                }
            });
        }
    </script>

<?php include_once '../includes/footer.php'; ?>