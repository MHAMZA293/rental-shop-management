<?php
// index.php — Login page
require_once 'includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php'); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = ['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']];
            header('Location: dashboard.php'); exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — ShopLedger Pro</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="login-page">
  <div class="login-bg"></div>
  <div class="login-card">
    <div class="login-logo">
      <div class="login-logo-icon"><i class="fa-solid fa-store"></i></div>
      <div>
        <div class="login-logo-text">ShopLedger Pro</div>
        <div class="login-logo-sub">Market Management System</div>
      </div>
    </div>

    <h1 class="login-heading">Welcome back</h1>
    <p class="login-sub">Sign in to manage your rental shops</p>

    <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:18px">
      <i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="login-form">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="admin@rentalshop.com"
               value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
      </button>
    </form>

    <p style="margin-top:24px;text-align:center;font-size:12px;color:var(--text2)">
      Demo: admin@rentalshop.com / password
    </p>
  </div>
</div>
</body>
</html>
