<?php
// includes/header.php — Main layout header
if (session_status() === PHP_SESSION_NONE) session_start();
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?> — <?= $pageTitle ?? 'Dashboard' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fa-solid fa-store"></i></div>
    <div>
      <div class="brand-name"><?= APP_NAME ?></div>
      <div class="brand-sub">Market Manager</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Overview</div>
    <a href="dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
      <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
    </a>

    <div class="nav-section-label">Management</div>
    <a href="tenants.php" class="nav-item <?= $currentPage==='tenants'?'active':'' ?>">
      <i class="fa-solid fa-users"></i><span>Tenants</span>
    </a>
    <a href="shops.php" class="nav-item <?= $currentPage==='shops'?'active':'' ?>">
      <i class="fa-solid fa-shop"></i><span>Shops</span>
    </a>
    <a href="leases.php" class="nav-item <?= $currentPage==='leases'?'active':'' ?>">
      <i class="fa-solid fa-file-contract"></i><span>Leases</span>
    </a>

    <div class="nav-section-label">Billing</div>
    <a href="bills.php" class="nav-item <?= $currentPage==='bills'?'active':'' ?>">
      <i class="fa-solid fa-file-invoice-dollar"></i><span>Bills</span>
    </a>
    <a href="payments.php" class="nav-item <?= $currentPage==='payments'?'active':'' ?>">
      <i class="fa-solid fa-money-bill-wave"></i><span>Payments</span>
    </a>
    <a href="ledger.php" class="nav-item <?= $currentPage==='ledger'?'active':'' ?>">
      <i class="fa-solid fa-book-open"></i><span>Ledger</span>
    </a>

    <div class="nav-section-label">Reports</div>
    <a href="reports.php" class="nav-item <?= $currentPage==='reports'?'active':'' ?>">
      <i class="fa-solid fa-chart-bar"></i><span>Reports</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= sanitize($user['name'] ?? '') ?></div>
        <div class="user-role"><?= ucfirst($user['role'] ?? '') ?></div>
      </div>
    </div>
    <a href="logout.php" class="logout-btn" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
  </div>
</aside>

<!-- Main Content -->
<div class="main-wrapper">
  <!-- Top bar -->
  <header class="topbar">
    <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
    <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
    <div class="topbar-right">
      <span class="topbar-date"><?= date('l, d M Y') ?></span>
    </div>
  </header>

  <!-- Flash messages -->
  <?php
  $success = flash('success');
  $error   = flash('error');
  if ($success): ?>
  <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= sanitize($success) ?></div>
  <?php endif; if ($error): ?>
  <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($error) ?></div>
  <?php endif; ?>

  <main class="page-content">
