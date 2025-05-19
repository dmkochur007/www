<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL for your project - adjust as needed
$base_url = '/energy';
?>
    <!DOCTYPE html>
    <html lang="uk">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Сервісний центр</title>
        <link rel="stylesheet" href="<?php echo $base_url; ?>/css/style.css?t=<?= microtime(true) . rand() ?>" type="text/css" />
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
        <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    </head>
<body>
<body>
<div class="container">
    <header>
        <div class="logo">
            <h1><span class="accent-text">Сервісний</span> центр</h1>
        </div>
        <?php include 'navbar.php'; ?>
    </header>

    <div class="content">
<?php
// Display flash messages if any
if (isset($_SESSION['message'])) {
    echo '<div class="alert ' . $_SESSION['message_type'] . '">' . $_SESSION['message'] . '</div>';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>