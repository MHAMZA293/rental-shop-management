<?php
// ledger.php — Full tenant transaction ledger
require_once 'includes/config.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Ledger';
$tenantId  = (int)($_GET['tenant_id'] ?? 0);

$tenants = $db->query("SELECT id, name FROM tenants ORDER BY name")->fetchAll();

$tenant = null;
$bills  = [];
$summary = [];

if ($tenantId) {
    $s = $db->prepare("SELECT * FROM tenants WHERE id=?");
    $s->execute([$tenantId]);
    $tenant = $s->fetch();

    if ($tenant) {
        $bills = $db->prepare(
            "SELECT b.*, s.shop_number, s.location
             FROM bills b
             JOIN shops s ON s.id = b.shop_id
             WHERE b.tenant_id = ?
             ORDER BY b.bill_month DESC"
        );
        $bills->execute([$tenantId]);
        $bills = $bills->fetchAll();

        // Payments per bill
        foreach ($bills as &$bill) {
            $pmts = $db->prepare(
                "SELECT * FROM payments WHERE bill_id=? ORDER BY payment_date"
            );
            $pmts->execute([$bill['id']]);
            $bill['payments'] = $pmts->fetchAll();
        }
        unset($bill);

        // Summary
        $sum = $db->prepare(
            "SELECT
               COALESCE(SUM(total_amount),0) AS total_billed,
               COALESCE(SUM(amount_paid),0) AS total_paid,
               COALESCE(SUM(outstanding),0) AS total_due,
               COUNT(*) AS bill_count,
               SUM(status='paid') AS paid_count,
               SUM(status='partial') AS partial_count,
               SUM(status='unpaid') AS unpaid_count
             FROM bills WHERE tenant_id=?"
        );
        $sum->execute([$tenantId]);
        $summary = $sum->fetch();
    }
}

require_once 'includes/header.php';
?>

<div class="page-header">
  <div><h1>Tenant Ledger</h1><p>Complete payment history and outstanding balances</p></div>
</div>

<!-- Tenant selector -->
<div class="card mb-4">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" class="search-bar">
      <div style="flex:1;max-width:400px">
        <select name="tenant_id" onchange="this.form.submit()" style="width:100%">
          <option value="">— Select Tenant to View Ledger —</option>
          <?php foreach ($tenants as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $tenantId == $t['id'] ? 'selected' : '' ?>>
            <?= sanitize($t['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if ($tenant): ?>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card amber">
    <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="stat-value"><?= money($summary['total_billed']) ?></div>
    <div class="stat-label">Total Billed</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-value"><?= money($summary['total_paid']) ?></div>
    <div class="stat-label">Total Paid</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
    <div class="stat-value"><?= money($summary['total_due']) ?></div>
    <div class="stat-label">Outstanding Balance</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-value"><?= (int)$summary['paid_count'] ?>/<?= (int)$summary['bill_count'] ?></div>
    <div class="stat-label">Bills Fully Paid</div>
  </div>
</div>

<!-- Tenant Info -->
<div class="card mb-4">
  <div class="card-header">
    <div class="card-title"><i class="fa-solid fa-user text-amber"></i> &nbsp;<?= sanitize($tenant['name']) ?></div>
    <div class="flex gap-2">
      <a href="tenants.php?action=edit&id=<?= $tenant['id'] ?>" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-pen"></i> Edit
      </a>
    </div>
  </div>
  <div class="card-body">
    <div class="form-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
      <div><span class="text-muted text-sm">CNIC</span><br><span class="mono"><?= sanitize($tenant['cnic'] ?: '—') ?></span></div>
      <div><span class="text-muted text-sm">Phone</span><br><span><?= sanitize($tenant['phone'] ?: '—') ?></span></div>
      <div><span class="text-muted text-sm">Email</span><br><span><?= sanitize($tenant['email'] ?: '—') ?></span></div>
      <div><span class="text-muted text-sm">Address</span><br><span><?= sanitize($tenant['address'] ?: '—') ?></span></div>
    </div>
  </div>
</div>

<!-- Transaction Ledger -->
<?php if ($bills): ?>
  <?php foreach ($bills as $bill): ?>
  <div class="card mb-4">
    <div class="card-header">
      <div>
        <div class="card-title">
          <?= date('F Y', strtotime($bill['bill_month'])) ?>
          — <span class="badge badge-blue"><?= sanitize($bill['shop_number']) ?></span>
        </div>
        <div class="text-muted text-sm mt-1"><?= sanitize($bill['location']) ?></div>
      </div>
      <div style="text-align:right">
        <?php
        $badge = match($bill['status']) {
            'paid'    => 'badge-green',
            'partial' => 'badge-amber',
            default   => 'badge-red',
        };
        echo "<span class='badge $badge' style='margin-bottom:4px'>".ucfirst($bill['status'])."</span>";
        echo "<br><span class='mono fw-bold text-red'>Due: ".money($bill['outstanding'])."</span>";
        ?>
      </div>
    </div>
    <div class="card-body">
      <!-- Bill breakdown -->
      <div style="display:flex;gap:32px;margin-bottom:16px;flex-wrap:wrap">
        <div><span class="text-muted text-sm">Rent</span><br><span class="mono fw-bold"><?= money($bill['rent_amount']) ?></span></div>
        <div><span class="text-muted text-sm">Prev. Dues</span><br><span class="mono <?= $bill['previous_dues']>0?'text-red':'' ?>"><?= money($bill['previous_dues']) ?></span></div>
        <div><span class="text-muted text-sm">Total Bill</span><br><span class="mono fw-bold"><?= money($bill['total_amount']) ?></span></div>
        <div><span class="text-muted text-sm">Amount Paid</span><br><span class="mono text-green"><?= money($bill['amount_paid']) ?></span></div>
        <div><span class="text-muted text-sm">Due Date</span><br><span class="mono"><?= $bill['due_date'] ? date('d M Y', strtotime($bill['due_date'])) : '—' ?></span></div>
      </div>

      <!-- Payments for this bill -->
      <?php if ($bill['payments']): ?>
      <div style="border-top:1px solid var(--border);padding-top:14px">
        <div class="text-muted text-sm" style="margin-bottom:8px;text-transform:uppercase;letter-spacing:.8px">Payment Transactions</div>
        <table>
          <thead>
            <tr><th>Receipt #</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($bill['payments'] as $p): ?>
            <tr>
              <td class="mono text-sm"><?= sanitize($p['receipt_no']) ?></td>
              <td class="mono"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
              <td class="mono text-green fw-bold"><?= money($p['amount']) ?></td>
              <td><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
              <td class="mono text-muted"><?= sanitize($p['reference_no'] ?: '—') ?></td>
              <td>
                <a href="payments.php?receipt=<?= $p['id'] ?>" class="btn btn-ghost btn-sm btn-icon">
                  <i class="fa-solid fa-receipt"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-muted text-sm" style="border-top:1px solid var(--border);padding-top:12px">
        <i class="fa-solid fa-circle-info"></i> No payments recorded for this bill.
        <?php if ($bill['status'] !== 'paid'): ?>
        <a href="payments.php?bill_id=<?= $bill['id'] ?>" class="btn btn-success btn-sm" style="margin-left:12px">
          <i class="fa-solid fa-money-bill-wave"></i> Record Payment
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php else: ?>
<div class="card">
  <div class="empty-state"><i class="fa-solid fa-book-open"></i><p>No bills found for this tenant.</p></div>
</div>
<?php endif; ?>

<?php elseif (!$tenantId): ?>
<div class="card">
  <div class="empty-state">
    <i class="fa-solid fa-hand-pointer"></i>
    <p>Select a tenant above to view their complete ledger</p>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
