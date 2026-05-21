<?php
// dashboard.php
require_once 'includes/config.php';
requireLogin();

$db = getDB();
$pageTitle = 'Dashboard';

// Stats
$totalTenants  = $db->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$totalShops    = $db->query("SELECT COUNT(*) FROM shops")->fetchColumn();
$occupiedShops = $db->query("SELECT COUNT(*) FROM shops WHERE status='occupied'")->fetchColumn();
$activeLeases  = $db->query("SELECT COUNT(*) FROM leases WHERE status='active'")->fetchColumn();

$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-t');

$monthCollection = $db->query(
    "SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date BETWEEN '$monthStart' AND '$monthEnd'"
)->fetchColumn();

$totalOutstanding = $db->query(
    "SELECT COALESCE(SUM(outstanding),0) FROM bills WHERE status != 'paid'"
)->fetchColumn();

// Recent payments
$recentPayments = $db->query(
    "SELECT p.*, t.name AS tenant_name, s.shop_number
     FROM payments p
     JOIN tenants t ON t.id = p.tenant_id
     JOIN bills b ON b.id = p.bill_id
     JOIN shops s ON s.id = b.shop_id
     ORDER BY p.created_at DESC LIMIT 8"
)->fetchAll();

// Unpaid/partial bills
$pendingBills = $db->query(
    "SELECT b.*, t.name AS tenant_name, s.shop_number
     FROM bills b
     JOIN tenants t ON t.id = b.tenant_id
     JOIN shops s ON s.id = b.shop_id
     WHERE b.status != 'paid'
     ORDER BY b.bill_month DESC LIMIT 8"
)->fetchAll();

require_once 'includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card amber">
    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
    <div class="stat-value"><?= $totalTenants ?></div>
    <div class="stat-label">Total Tenants</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon"><i class="fa-solid fa-shop"></i></div>
    <div class="stat-value"><?= $occupiedShops ?>/<?= $totalShops ?></div>
    <div class="stat-label">Shops Occupied</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-value"><?= number_format($monthCollection) ?></div>
    <div class="stat-label">Collected This Month</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
    <div class="stat-value"><?= number_format($totalOutstanding) ?></div>
    <div class="stat-label">Total Outstanding</div>
  </div>
</div>

<div class="two-col">
  <!-- Pending Bills -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa-solid fa-clock text-amber"></i> &nbsp;Pending Bills</div>
      <a href="bills.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <?php if ($pendingBills): ?>
      <table>
        <thead>
          <tr>
            <th>Tenant</th>
            <th>Shop</th>
            <th>Month</th>
            <th>Due</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingBills as $b): ?>
          <tr>
            <td><?= sanitize($b['tenant_name']) ?></td>
            <td><span class="badge badge-blue"><?= sanitize($b['shop_number']) ?></span></td>
            <td class="mono"><?= date('M Y', strtotime($b['bill_month'])) ?></td>
            <td class="mono text-red fw-bold"><?= money($b['outstanding']) ?></td>
            <td>
              <?php
              $cls = $b['status'] === 'partial' ? 'badge-amber' : 'badge-red';
              echo "<span class='badge $cls'>" . ucfirst($b['status']) . "</span>";
              ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-check-circle"></i><p>All bills are paid!</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Payments -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fa-solid fa-receipt text-green"></i> &nbsp;Recent Payments</div>
      <a href="payments.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <?php if ($recentPayments): ?>
      <table>
        <thead>
          <tr>
            <th>Tenant</th>
            <th>Shop</th>
            <th>Amount</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentPayments as $p): ?>
          <tr>
            <td><?= sanitize($p['tenant_name']) ?></td>
            <td><span class="badge badge-blue"><?= sanitize($p['shop_number']) ?></span></td>
            <td class="mono text-green fw-bold"><?= money($p['amount']) ?></td>
            <td class="mono text-muted"><?= date('d M', strtotime($p['payment_date'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state"><i class="fa-solid fa-receipt"></i><p>No payments yet.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
