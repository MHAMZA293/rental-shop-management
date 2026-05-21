<?php
// bills.php — Generate & manage monthly bills
require_once 'includes/config.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Bills';
$action    = $_GET['action'] ?? '';

// ── Generate Monthly Bills ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generate') {
    $month    = $_POST['bill_month'] ?? date('Y-m-01');
    $monthDt  = date('Y-m-01', strtotime($month));
    $dueDate  = date('Y-m-10', strtotime($monthDt . ' +1 month'));
    $generated = 0;

    $activeLeases = $db->query(
        "SELECT l.*, t.id AS t_id, s.id AS s_id
         FROM leases l
         JOIN tenants t ON t.id = l.tenant_id
         JOIN shops s ON s.id = l.shop_id
         WHERE l.status = 'active'"
    )->fetchAll();

    foreach ($activeLeases as $lease) {
        // Avoid duplicates
        $exists = $db->prepare("SELECT id FROM bills WHERE lease_id=? AND bill_month=?");
        $exists->execute([$lease['id'], $monthDt]);
        if ($exists->fetch()) continue;

        // Previous dues
        $prevDue = $db->prepare(
            "SELECT COALESCE(SUM(outstanding),0) FROM bills
             WHERE tenant_id=? AND bill_month < ? AND status != 'paid'"
        );
        $prevDue->execute([$lease['t_id'], $monthDt]);
        $prevDues = (float)$prevDue->fetchColumn();

        $rent  = (float)$lease['monthly_rent'];
        $total = $rent + $prevDues;

        $stmt = $db->prepare(
            "INSERT INTO bills (lease_id,tenant_id,shop_id,bill_month,rent_amount,previous_dues,total_amount,due_date)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([$lease['id'], $lease['t_id'], $lease['s_id'], $monthDt, $rent, $prevDues, $total, $dueDate]);
        $generated++;
    }

    flash('success', "$generated bill(s) generated for " . date('F Y', strtotime($monthDt)) . ".");
    header('Location: bills.php'); exit;
}

// ── Delete Bill ──────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $db->prepare("DELETE FROM bills WHERE id=? AND amount_paid=0")->execute([$id]);
        flash('success', 'Bill deleted.');
    } catch (PDOException $e) {
        flash('error', 'Cannot delete bill with payments.');
    }
    header('Location: bills.php'); exit;
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterMonth  = $_GET['month']  ?? '';

$where = ['1=1'];
$bind  = [];

if ($filterStatus) { $where[] = 'b.status=?'; $bind[] = $filterStatus; }
if ($filterMonth)  { $where[] = 'b.bill_month=?'; $bind[] = date('Y-m-01', strtotime($filterMonth)); }

$sql = "SELECT b.*, t.name AS tenant_name, s.shop_number
        FROM bills b
        JOIN tenants t ON t.id = b.tenant_id
        JOIN shops s ON s.id = b.shop_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.bill_month DESC, t.name";

$stmt  = $db->prepare($sql);
$stmt->execute($bind);
$bills = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="page-header">
  <div><h1>Bills</h1><p>Generate and manage monthly rent bills</p></div>
  <button class="btn btn-primary" onclick="openModal('generateModal')">
    <i class="fa-solid fa-file-invoice-dollar"></i> Generate Bills
  </button>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="search-bar">
      <div class="search-input-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="tableSearch" placeholder="Search bills…">
      </div>
      <input type="month" name="month" value="<?= htmlspecialchars($filterMonth) ?>" style="width:180px">
      <select name="status" style="width:150px">
        <option value="">All Status</option>
        <option value="unpaid"  <?= $filterStatus==='unpaid'  ?'selected':'' ?>>Unpaid</option>
        <option value="partial" <?= $filterStatus==='partial' ?'selected':'' ?>>Partial</option>
        <option value="paid"    <?= $filterStatus==='paid'    ?'selected':'' ?>>Paid</option>
      </select>
      <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-filter"></i> Filter</button>
      <a href="bills.php" class="btn btn-ghost"><i class="fa-solid fa-xmark"></i></a>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <?php if ($bills): ?>
    <table>
      <thead>
        <tr>
          <th>Tenant</th><th>Shop</th><th>Month</th>
          <th>Rent</th><th>Prev. Dues</th><th>Total</th>
          <th>Paid</th><th>Outstanding</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bills as $b):
          $badge = match($b['status']) {
            'paid'    => 'badge-green',
            'partial' => 'badge-amber',
            default   => 'badge-red',
          };
        ?>
        <tr>
          <td class="fw-bold"><?= sanitize($b['tenant_name']) ?></td>
          <td><span class="badge badge-blue"><?= sanitize($b['shop_number']) ?></span></td>
          <td class="mono"><?= date('M Y', strtotime($b['bill_month'])) ?></td>
          <td class="mono"><?= money($b['rent_amount']) ?></td>
          <td class="mono <?= $b['previous_dues'] > 0 ? 'text-red' : 'text-muted' ?>">
            <?= money($b['previous_dues']) ?>
          </td>
          <td class="mono fw-bold"><?= money($b['total_amount']) ?></td>
          <td class="mono text-green"><?= money($b['amount_paid']) ?></td>
          <td class="mono fw-bold <?= $b['outstanding'] > 0 ? 'text-red' : 'text-muted' ?>">
            <?= money($b['outstanding']) ?>
          </td>
          <td><span class="badge <?= $badge ?>"><?= ucfirst($b['status']) ?></span></td>
          <td>
            <div class="flex gap-2">
              <?php if ($b['status'] !== 'paid'): ?>
              <a href="payments.php?bill_id=<?= $b['id'] ?>" class="btn btn-success btn-sm btn-icon" title="Record Payment">
                <i class="fa-solid fa-money-bill-wave"></i>
              </a>
              <?php endif; ?>
              <?php if ($b['amount_paid'] == 0): ?>
              <a href="bills.php?action=delete&id=<?= $b['id'] ?>"
                 class="btn btn-danger btn-sm btn-icon"
                 onclick="return confirmDelete('Delete this bill?')">
                <i class="fa-solid fa-trash"></i>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-file-invoice-dollar"></i><p>No bills found. Generate bills for a month first.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- GENERATE MODAL -->
<div class="modal-overlay" id="generateModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <div class="modal-title">Generate Monthly Bills</div>
      <button class="modal-close" onclick="closeModal('generateModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="bills.php?action=generate">
      <div class="modal-body">
        <p class="text-muted" style="margin-bottom:18px;font-size:13px">
          This will create bills for all active leases for the selected month, automatically including any previous outstanding dues.
        </p>
        <div class="form-group">
          <label>Billing Month *</label>
          <input type="month" name="bill_month" required value="<?= date('Y-m') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('generateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-bolt"></i> Generate Bills
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
