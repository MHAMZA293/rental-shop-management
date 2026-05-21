<?php
// reports.php — Collection reports and outstanding dues
require_once 'includes/config.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Reports';

$reportType = $_GET['type']  ?? 'collection';
$month      = $_GET['month'] ?? date('Y-m');

$monthDt    = date('Y-m-01', strtotime($month));
$monthLabel = date('F Y', strtotime($monthDt));

// ── Monthly Collection Summary ────────────────────────────────
$collectionData = [];
if ($reportType === 'collection') {
    $collectionData = $db->prepare(
        "SELECT t.name AS tenant_name, s.shop_number, s.location,
                b.rent_amount, b.previous_dues, b.total_amount,
                b.amount_paid, b.outstanding, b.status
         FROM bills b
         JOIN tenants t ON t.id = b.tenant_id
         JOIN shops s ON s.id = b.shop_id
         WHERE b.bill_month = ?
         ORDER BY b.status ASC, t.name"
    );
    $collectionData->execute([$monthDt]);
    $collectionData = $collectionData->fetchAll();

    $totals = [
        'billed' => array_sum(array_column($collectionData, 'total_amount')),
        'paid'   => array_sum(array_column($collectionData, 'amount_paid')),
        'due'    => array_sum(array_column($collectionData, 'outstanding')),
    ];
}

// ── Outstanding Dues ──────────────────────────────────────────
$outstandingData = [];
if ($reportType === 'outstanding') {
    $outstandingData = $db->query(
        "SELECT t.id AS tenant_id, t.name AS tenant_name, t.phone, s.shop_number,
                COUNT(b.id) AS bill_count,
                COALESCE(SUM(b.outstanding),0) AS total_due,
                MIN(b.bill_month) AS oldest_due
         FROM bills b
         JOIN tenants t ON t.id = b.tenant_id
         JOIN shops s ON s.id = b.shop_id
         WHERE b.status != 'paid'
         GROUP BY t.id, s.id
         ORDER BY total_due DESC"
    )->fetchAll();
}

// ── Monthly trends (last 12 months) ──────────────────────────
$trendData = $db->query(
    "SELECT DATE_FORMAT(bill_month, '%b %Y') AS month_label,
            COALESCE(SUM(total_amount),0) AS billed,
            COALESCE(SUM(amount_paid),0) AS collected
     FROM bills
     GROUP BY bill_month
     ORDER BY bill_month DESC
     LIMIT 12"
)->fetchAll();
$trendData = array_reverse($trendData);

require_once 'includes/header.php';
?>

<div class="page-header">
  <div><h1>Reports</h1><p>Financial summaries and outstanding analysis</p></div>
  <button onclick="window.print()" class="btn btn-ghost">
    <i class="fa-solid fa-print"></i> Print
  </button>
</div>

<!-- Report Tabs -->
<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <div class="search-bar">
      <a href="reports.php?type=collection&month=<?= $month ?>"
         class="btn <?= $reportType==='collection' ? 'btn-primary' : 'btn-ghost' ?>">
        <i class="fa-solid fa-chart-column"></i> Monthly Collection
      </a>
      <a href="reports.php?type=outstanding"
         class="btn <?= $reportType==='outstanding' ? 'btn-primary' : 'btn-ghost' ?>">
        <i class="fa-solid fa-triangle-exclamation"></i> Outstanding Dues
      </a>
      <a href="reports.php?type=trends"
         class="btn <?= $reportType==='trends' ? 'btn-primary' : 'btn-ghost' ?>">
        <i class="fa-solid fa-chart-line"></i> Trends
      </a>
    </div>
  </div>
</div>

<?php if ($reportType === 'collection'): ?>
<!-- ════ MONTHLY COLLECTION ════ -->
<div class="card mb-4">
  <div class="card-body" style="padding:16px 20px">
    <form method="GET" class="search-bar">
      <input type="hidden" name="type" value="collection">
      <input type="month" name="month" value="<?= $month ?>" style="width:200px">
      <button type="submit" class="btn btn-ghost"><i class="fa-solid fa-filter"></i> Load</button>
    </form>
  </div>
</div>

<?php if ($collectionData): ?>
<!-- Summary stats -->
<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card amber">
    <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="stat-value"><?= money($totals['billed']) ?></div>
    <div class="stat-label">Total Billed</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-value"><?= money($totals['paid']) ?></div>
    <div class="stat-label">Collected</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-value"><?= money($totals['due']) ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon"><i class="fa-solid fa-percent"></i></div>
    <div class="stat-value">
      <?= $totals['billed'] > 0 ? number_format(($totals['paid'] / $totals['billed']) * 100, 1) : '0.0' ?>%
    </div>
    <div class="stat-label">Collection Rate</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Monthly Collection — <?= $monthLabel ?></div>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Tenant</th><th>Shop</th><th>Rent</th><th>Prev.Dues</th><th>Total</th><th>Paid</th><th>Outstanding</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($collectionData as $i => $r):
          $badge = match($r['status']) {
            'paid'    => 'badge-green',
            'partial' => 'badge-amber',
            default   => 'badge-red',
          };
        ?>
        <tr>
          <td class="text-muted mono"><?= $i+1 ?></td>
          <td class="fw-bold"><?= sanitize($r['tenant_name']) ?></td>
          <td><span class="badge badge-blue"><?= sanitize($r['shop_number']) ?></span></td>
          <td class="mono"><?= money($r['rent_amount']) ?></td>
          <td class="mono <?= $r['previous_dues']>0?'text-red':'' ?>"><?= money($r['previous_dues']) ?></td>
          <td class="mono fw-bold"><?= money($r['total_amount']) ?></td>
          <td class="mono text-green"><?= money($r['amount_paid']) ?></td>
          <td class="mono fw-bold <?= $r['outstanding']>0?'text-red':'text-muted' ?>"><?= money($r['outstanding']) ?></td>
          <td><span class="badge <?= $badge ?>"><?= ucfirst($r['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--bg3)">
          <td colspan="3" class="fw-bold" style="padding:12px 16px">TOTALS</td>
          <td class="mono fw-bold" style="padding:12px 16px"><?= money(array_sum(array_column($collectionData,'rent_amount'))) ?></td>
          <td class="mono" style="padding:12px 16px"><?= money(array_sum(array_column($collectionData,'previous_dues'))) ?></td>
          <td class="mono fw-bold" style="padding:12px 16px"><?= money($totals['billed']) ?></td>
          <td class="mono text-green fw-bold" style="padding:12px 16px"><?= money($totals['paid']) ?></td>
          <td class="mono text-red fw-bold" style="padding:12px 16px"><?= money($totals['due']) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card"><div class="empty-state"><i class="fa-solid fa-chart-bar"></i><p>No bills found for <?= $monthLabel ?>. Generate bills first.</p></div></div>
<?php endif; ?>

<?php elseif ($reportType === 'outstanding'): ?>
<!-- ════ OUTSTANDING DUES ════ -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="fa-solid fa-triangle-exclamation text-red"></i> &nbsp;All Outstanding Dues</div>
    <div class="text-muted text-sm">As of <?= date('d M Y') ?></div>
  </div>
  <div class="table-wrapper">
    <?php if ($outstandingData): ?>
    <table>
      <thead>
        <tr><th>#</th><th>Tenant</th><th>Phone</th><th>Shop</th><th>Unpaid Bills</th><th>Oldest Due</th><th>Total Outstanding</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($outstandingData as $i => $r): ?>
        <tr>
          <td class="text-muted mono"><?= $i+1 ?></td>
          <td class="fw-bold"><?= sanitize($r['tenant_name']) ?></td>
          <td><?= sanitize($r['phone'] ?: '—') ?></td>
          <td><span class="badge badge-blue"><?= sanitize($r['shop_number']) ?></span></td>
          <td class="mono"><?= (int)$r['bill_count'] ?> bill(s)</td>
          <td class="mono"><?= date('M Y', strtotime($r['oldest_due'])) ?></td>
          <td class="mono text-red fw-bold"><?= money($r['total_due']) ?></td>
          <td>
            <a href="ledger.php?tenant_id=<?= $r['tenant_id'] ?>" class="btn btn-ghost btn-sm">
              <i class="fa-solid fa-book-open"></i> Ledger
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--bg3)">
          <td colspan="6" class="fw-bold" style="padding:12px 16px">TOTAL OUTSTANDING</td>
          <td class="mono text-red fw-bold" style="padding:12px 16px">
            <?= money(array_sum(array_column($outstandingData,'total_due'))) ?>
          </td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>No outstanding dues! All payments are up to date.</p></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($reportType === 'trends'): ?>
<!-- ════ TRENDS ════ -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Collection Trends (Last 12 Months)</div>
  </div>
  <div class="card-body">
    <?php if ($trendData): ?>
    <!-- Simple bar chart using CSS -->
    <?php
    $maxVal = max(array_map(fn($r) => max($r['billed'], $r['collected']), $trendData)) ?: 1;
    ?>
    <div style="display:flex;gap:16px;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:8px"><div style="width:14px;height:14px;background:var(--accent);border-radius:3px"></div><span class="text-sm">Billed</span></div>
      <div style="display:flex;align-items:center;gap:8px"><div style="width:14px;height:14px;background:var(--green);border-radius:3px"></div><span class="text-sm">Collected</span></div>
    </div>
    <div style="display:flex;align-items:flex-end;gap:8px;height:200px;overflow-x:auto;padding-bottom:8px">
      <?php foreach ($trendData as $row): ?>
      <?php
        $billedH    = ($row['billed']    / $maxVal) * 180;
        $collectedH = ($row['collected'] / $maxVal) * 180;
      ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:4px;min-width:64px">
        <div style="display:flex;gap:3px;align-items:flex-end;height:180px">
          <div style="width:22px;height:<?= $billedH ?>px;background:var(--accent);border-radius:4px 4px 0 0;opacity:.85" title="<?= money($row['billed']) ?>"></div>
          <div style="width:22px;height:<?= $collectedH ?>px;background:var(--green);border-radius:4px 4px 0 0" title="<?= money($row['collected']) ?>"></div>
        </div>
        <div class="text-muted text-sm" style="font-size:10px;text-align:center;white-space:nowrap"><?= $row['month_label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <hr class="divider">

    <table>
      <thead>
        <tr><th>Month</th><th>Billed</th><th>Collected</th><th>Outstanding</th><th>Rate</th></tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($trendData) as $row): ?>
        <?php
          $due  = $row['billed'] - $row['collected'];
          $rate = $row['billed'] > 0 ? ($row['collected'] / $row['billed']) * 100 : 0;
        ?>
        <tr>
          <td class="mono"><?= $row['month_label'] ?></td>
          <td class="mono"><?= money($row['billed']) ?></td>
          <td class="mono text-green"><?= money($row['collected']) ?></td>
          <td class="mono <?= $due > 0 ? 'text-red' : 'text-muted' ?>"><?= money($due) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1;background:var(--bg3);border-radius:4px;height:6px;overflow:hidden">
                <div style="height:100%;width:<?= min(100,$rate) ?>%;background:var(--green);border-radius:4px"></div>
              </div>
              <span class="mono text-sm"><?= number_format($rate,1) ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-chart-line"></i><p>No data yet. Generate some bills first.</p></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
