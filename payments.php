<?php
// payments.php — Record & manage payments
require_once 'includes/config.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Payments';
$action    = $_GET['action'] ?? '';
$billId    = (int)($_GET['bill_id'] ?? 0);

// ── Record payment ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pay') {
    $bId       = (int)($_POST['bill_id'] ?? 0);
    $amount    = (float)($_POST['amount'] ?? 0);
    $method    = $_POST['payment_method'] ?? 'cash';
    $date      = $_POST['payment_date']   ?? date('Y-m-d');
    $ref       = $_POST['reference_no']   ?? '';
    $notes     = $_POST['notes']          ?? '';
    $userId    = currentUser()['id'] ?? null;
    $receiptNo = generateReceiptNo();

    if ($bId && $amount > 0) {
        // Check bill
        $bill = $db->prepare("SELECT * FROM bills WHERE id=?");
        $bill->execute([$bId]);
        $bill = $bill->fetch();

        if ($bill) {
            // Clamp to outstanding
            $maxPay = (float)$bill['outstanding'];
            if ($amount > $maxPay) $amount = $maxPay;

            // Insert payment
            $ins = $db->prepare(
                "INSERT INTO payments (bill_id,tenant_id,amount,payment_date,payment_method,reference_no,notes,receipt_no,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([$bId, $bill['tenant_id'], $amount, $date, $method, $ref, $notes, $receiptNo, $userId]);

            // Update bill
            $newPaid = (float)$bill['amount_paid'] + $amount;
            $status  = $newPaid >= (float)$bill['total_amount'] ? 'paid' : 'partial';
            $db->prepare("UPDATE bills SET amount_paid=?, status=? WHERE id=?")->execute([$newPaid, $status, $bId]);

            flash('success', "Payment of " . money($amount) . " recorded. Receipt: $receiptNo");
            header("Location: payments.php?receipt=" . $db->lastInsertId()); exit;
        }
    }
    flash('error', 'Invalid payment data.');
    header('Location: payments.php'); exit;
}

// ── Show receipt ─────────────────────────────────────────────
$receiptId = (int)($_GET['receipt'] ?? 0);
$receipt   = null;

if ($receiptId) {
    $r = $db->prepare(
        "SELECT p.*, t.name AS tenant_name, t.phone, s.shop_number, s.location,
         b.bill_month, b.rent_amount, b.previous_dues, b.total_amount, b.outstanding
         FROM payments p
         JOIN tenants t ON t.id = p.tenant_id
         JOIN bills b ON b.id = p.bill_id
         JOIN shops s ON s.id = b.shop_id
         WHERE p.id=?"
    );
    $r->execute([$receiptId]);
    $receipt = $r->fetch();
}

// ── Bill for payment form ────────────────────────────────────
$billForPay = null;
if ($billId) {
    $b = $db->prepare(
        "SELECT b.*, t.name AS tenant_name, s.shop_number
         FROM bills b JOIN tenants t ON t.id=b.tenant_id JOIN shops s ON s.id=b.shop_id
         WHERE b.id=?"
    );
    $b->execute([$billId]);
    $billForPay = $b->fetch();
}

// All payments list
$payments = $db->query(
    "SELECT p.*, t.name AS tenant_name, s.shop_number, b.bill_month
     FROM payments p
     JOIN tenants t ON t.id = p.tenant_id
     JOIN bills b ON b.id = p.bill_id
     JOIN shops s ON s.id = b.shop_id
     ORDER BY p.payment_date DESC, p.created_at DESC
     LIMIT 100"
)->fetchAll();

require_once 'includes/header.php';
?>

<div class="page-header">
  <div><h1>Payments</h1><p>Record rent payments and issue receipts</p></div>
</div>

<?php if ($receipt): ?>
<!-- ════ RECEIPT VIEW ════ -->
<div style="margin-bottom:24px">
  <div class="receipt-wrapper">
    <div class="receipt-header">
      <h2><i class="fa-solid fa-receipt"></i> &nbsp;Payment Receipt</h2>
      <p><?= APP_NAME ?> — Market Management</p>
    </div>
    <div class="receipt-body">
      <div class="receipt-row">
        <span class="label">Receipt No</span>
        <span class="value mono"><?= sanitize($receipt['receipt_no']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">Date</span>
        <span class="value mono"><?= date('d M Y', strtotime($receipt['payment_date'])) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">Tenant</span>
        <span class="value"><?= sanitize($receipt['tenant_name']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">Phone</span>
        <span class="value mono"><?= sanitize($receipt['phone'] ?: '—') ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">Shop</span>
        <span class="value"><?= sanitize($receipt['shop_number']) ?> — <?= sanitize($receipt['location']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">Bill Month</span>
        <span class="value mono"><?= date('F Y', strtotime($receipt['bill_month'])) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">Bill Rent</span>
        <span class="value mono"><?= money($receipt['rent_amount']) ?></span>
      </div>
      <?php if ($receipt['previous_dues'] > 0): ?>
      <div class="receipt-row">
        <span class="label">Previous Dues</span>
        <span class="value mono text-red"><?= money($receipt['previous_dues']) ?></span>
      </div>
      <?php endif; ?>
      <div class="receipt-row">
        <span class="label">Total Bill</span>
        <span class="value mono"><?= money($receipt['total_amount']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">Payment Method</span>
        <span class="value"><?= ucfirst(str_replace('_', ' ', $receipt['payment_method'])) ?></span>
      </div>
      <?php if ($receipt['reference_no']): ?>
      <div class="receipt-row">
        <span class="label">Reference #</span>
        <span class="value mono"><?= sanitize($receipt['reference_no']) ?></span>
      </div>
      <?php endif; ?>
      <div class="receipt-row" style="margin-top:12px;padding-top:16px;border-top:2px solid var(--accent)">
        <span class="label fw-bold" style="color:var(--text);font-size:15px">Amount Paid</span>
        <span class="value receipt-total"><?= money($receipt['amount']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">Remaining Balance</span>
        <span class="value mono <?= $receipt['outstanding'] > 0 ? 'text-red' : 'text-green' ?>">
          <?= money($receipt['outstanding']) ?>
        </span>
      </div>
    </div>
  </div>
  <div style="display:flex;gap:10px;margin-top:14px;max-width:560px;margin-left:auto;margin-right:auto">
    <button onclick="printReceipt()" class="btn btn-ghost"><i class="fa-solid fa-print"></i> Print</button>
    <a href="payments.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back</a>
  </div>
</div>
<?php endif; ?>

<!-- Search & List -->
<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <div class="search-input-wrap">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="tableSearch" placeholder="Search payments…">
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Payment History</div>
  </div>
  <div class="table-wrapper">
    <?php if ($payments): ?>
    <table>
      <thead>
        <tr><th>Receipt #</th><th>Tenant</th><th>Shop</th><th>Month</th><th>Amount</th><th>Method</th><th>Date</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td class="mono text-sm"><?= sanitize($p['receipt_no']) ?></td>
          <td class="fw-bold"><?= sanitize($p['tenant_name']) ?></td>
          <td><span class="badge badge-blue"><?= sanitize($p['shop_number']) ?></span></td>
          <td class="mono"><?= date('M Y', strtotime($p['bill_month'])) ?></td>
          <td class="mono text-green fw-bold"><?= money($p['amount']) ?></td>
          <td><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
          <td class="mono text-muted"><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
          <td>
            <a href="payments.php?receipt=<?= $p['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View Receipt">
              <i class="fa-solid fa-receipt"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-money-bill-wave"></i><p>No payments recorded yet.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- Payment Modal (triggered from bills page) -->
<?php if ($billForPay): ?>
<div class="modal-overlay open" id="payModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <div class="modal-title">Record Payment</div>
      <button class="modal-close" onclick="location.href='bills.php'"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="payments.php?action=pay">
      <input type="hidden" name="bill_id" value="<?= $billForPay['id'] ?>">
      <div class="modal-body">
        <div class="card" style="margin-bottom:20px;background:var(--bg3)">
          <div class="card-body" style="padding:16px">
            <div class="receipt-row"><span class="label">Tenant</span><span class="value"><?= sanitize($billForPay['tenant_name']) ?></span></div>
            <div class="receipt-row"><span class="label">Shop</span><span class="value"><?= sanitize($billForPay['shop_number']) ?></span></div>
            <div class="receipt-row"><span class="label">Month</span><span class="value mono"><?= date('F Y', strtotime($billForPay['bill_month'])) ?></span></div>
            <div class="receipt-row"><span class="label">Outstanding</span><span class="value text-red fw-bold mono"><?= money($billForPay['outstanding']) ?></span></div>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Amount (PKR) *</label>
            <input type="number" name="amount" step="0.01" max="<?= $billForPay['outstanding'] ?>"
                   value="<?= $billForPay['outstanding'] ?>" required>
          </div>
          <div class="form-group">
            <label>Payment Date *</label>
            <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label>Payment Method</label>
            <select name="payment_method">
              <option value="cash">Cash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cheque">Cheque</option>
              <option value="online">Online</option>
            </select>
          </div>
          <div class="form-group">
            <label>Reference No</label>
            <input type="text" name="reference_no" placeholder="Cheque/TXN #">
          </div>
          <div class="form-group full">
            <label>Notes</label>
            <textarea name="notes" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="bills.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-money-bill-wave"></i> Record Payment</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
