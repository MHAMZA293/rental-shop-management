<?php
// ============================================================
// config.php — Database connection & global configuration
// ============================================================

define('DB_HOST', 'your database host');
define('DB_USER', 'your database user ');
define('DB_PASS', 'your database password');
define('DB_NAME', 'rental_shop');
define('APP_NAME', 'ShopLedger Pro');
define('CURRENCY', 'PKR');
define('CURRENCY_SYMBOL', '₨');

// PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Session helpers
function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function currentUser(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user'] ?? [];
}

// Formatting helpers
function money(float $amount): string {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function generateReceiptNo(): string {
    return 'RCP-' . strtoupper(substr(md5(uniqid()), 0, 8));
}

function flash(string $key, string $msg = ''): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($msg) {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $val = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $val;
}
