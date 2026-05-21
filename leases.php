<?php
// leases.php — Lease management
require_once 'includes/config.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Leases';
$action    = $_GET['action'] ?? '';
$id        = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        $_POST['tenant_id']        ?? 0,
        $_POST['shop_id']          ?? 0,
        $_POST['start_date']       ?? '',
        $_POST['end_date']         ?: null,
        $_POST['monthly_rent']     ?? 0,
        $_POST['security_deposit'] ?: 0,
        $_POST['status']           ?? 'active',
        $_POST['notes']            ?? '',
    ];

    if ($action === 'add') {
        // Mark shop occupied
        $db->prepare("UPDATE shops SET status='occupied' WHERE id=?")->execute([$data[1]]);
        $stmt = $db->prepare(
            "INSERT INTO leases (tenant_id,shop_id,start_date,end_date,monthly_rent,security_deposit,status,notes)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute($data);
        flash('success', 'Lease created successfully.');
    } elseif ($action === 'edit' && $id) {
        $data[] = $id;
        // If terminated, free the shop
        if ($data[6] === 'terminated' || $data[6] === 'expired') {
            $db->prepare("UPDATE shops SET status='vacant' WHERE id=?")->execute([$data[1]]);
        }
        $stmt = $db->prepare(
            "UPDATE leases SET tenant_id=?,shop_id=?,start_date=?,end_date=?,monthly_rent=?,
             security_deposit=?,status=?,notes=? WHERE id=?"
        );
        $stmt->execute($data);
        flash('success', 'Lease updated.');
    }
    header('Location: leases.php'); exit;
}

if ($action === 'delete' && $id) {
    try {
        $lease = $db->prepare("SELECT shop_id FROM leases WHERE id=?");
        $lease->execute([$id]);
        $l = $lease->fetch();
        $db->prepare("DELETE FROM leases WHERE id=?")->execute([$id]);
        if ($l) $db->prepare("UPDATE shops SET status='vacant' WHERE id=?")->execute([$l['shop_id']]);
        flash('success', 'Lease deleted.');
    } catch (PDOException $e) {
        flash('error', 'Cannot delete lease — payments linked to it.');
    }
    header('Location: leases.php'); exit;
}

$editLease = null;
if ($action === 'edit' && $id) {
    $s = $db->prepare("SELECT * FROM leases WHERE id=?");
    $s->execute([$id]);
    $editLease = $s->fetch();
}

$leases = $db->query(
    "SELECT l.*, t.name AS tenant_name, s.shop_number, s.location
     FROM leases l
     JOIN tenants t ON t.id = l.tenant_id
     JOIN shops s ON s.id = l.shop_id
     ORDER BY l.status ASC, l.start_date DESC"
)->fetchAll();

$tenants = $db->query("SELECT id, name FROM tenants ORDER BY name")->fetchAll();
$shops   = $db->query("SELECT id, shop_number, location FROM shops ORDER BY shop_number")->fetchAll();

require_once 'includes/header.php';

function leaseFormFields(array $l, array $tenants, array $shops): void {
    $f = fn($k) => sanitize($l[$k] ?? '');
    echo '<div class="form-grid">';

    echo '<div class="form-group"><label>Tenant *</label><select name="tenant_id" required>';
    echo '<option value="">-- Select Tenant --</option>';
    foreach ($tenants as $t) {
        $sel = ($l['tenant_id'] ?? '') == $t['id'] ? 'selected' : '';
        echo "<option value='{$t['id']}' $sel>" . sanitize($t['name']) . "</option>";
    }
    echo '</select></div>';

    echo '<div class="form-group"><label>Shop *</label><select name="shop_id" required>';
    echo '<option value="">-- Select Shop --</option>';
    foreach ($shops as $s) {
        $sel = ($l['shop_id'] ?? '') == $s['id'] ? 'selected' : '';
        echo "<option value='{$s['id']}' $sel>{$s['shop_number']} — " . sanitize($s['location']) . "</option>";
    }
    echo '</select></div>';

    echo '<div class="form-group"><label>Start Date *</label><input type="date" name="start_date" required value="'.$f('start_date').'"></div>';
    echo '<div class="form-group"><label>End Date</label><input type="date" name="end_date" value="'.$f('end_date').'"></div>';
    echo '<div class="form-group"><label>Monthly Rent (PKR) *</label><input type="number" name="monthly_rent" step="0.01" required value="'.($l['monthly_rent'] ?? '').'"></div>';
    echo '<div class="form-group"><label>Security Deposit (PKR)</label><input type="number" name="security_deposit" step="0.01" value="'.($l['security_deposit'] ?? 0).'"></div>';

    $statuses = ['active' => 'Active', 'terminated' => 'Terminated', 'expired' => 'Expired'];
    echo '<div class="form-group"><label>Status</label><select name="status">';
    foreach ($statuses as $val => $lbl) {
        $sel = ($l['status'] ?? 'active') === $val ? 'selected' : '';
        echo "<option value='$val' $sel>$lbl</option>";
    }
    echo '</select></div>';

    echo '<div class="form-group full"><label>Notes</label><textarea name="notes" rows="2">'.$f('notes').'</textarea></div>';
    echo '</div>';
}
?>

<div class="page-header">
  <div><h1>Leases</h1><p>Assign tenants to shops and manage lease agreements</p></div>
  <button class="btn btn-primary" onclick="openModal('addModal')">
    <i class="fa-solid fa-plus"></i> New Lease
  </button>
</div>

<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <div class="search-input-wrap">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="tableSearch" placeholder="Search leases…">
    </div>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <?php if ($leases): ?>
    <table>
      <thead>
        <tr><th>Tenant</th><th>Shop</th><th>Monthly Rent</th><th>Start Date</th><th>End Date</th><th>Deposit</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($leases as $l):
          $badge = match($l['status']) {
            'active'     => 'badge-green',
            'terminated' => 'badge-red',
            'expired'    => 'badge-gray',
            default      => 'badge-gray',
          };
        ?>
        <tr>
          <td class="fw-bold"><?= sanitize($l['tenant_name']) ?></td>
          <td><span class="badge badge-blue"><?= sanitize($l['shop_number']) ?></span></td>
          <td class="mono text-amber fw-bold"><?= money($l['monthly_rent']) ?></td>
          <td class="mono"><?= date('d M Y', strtotime($l['start_date'])) ?></td>
          <td class="mono"><?= $l['end_date'] ? date('d M Y', strtotime($l['end_date'])) : '<span class="text-muted">Open</span>' ?></td>
          <td class="mono"><?= money($l['security_deposit']) ?></td>
          <td><span class="badge <?= $badge ?>"><?= ucfirst($l['status']) ?></span></td>
          <td>
            <div class="flex gap-2">
              <a href="leases.php?action=edit&id=<?= $l['id'] ?>" class="btn btn-ghost btn-sm btn-icon"><i class="fa-solid fa-pen"></i></a>
              <a href="leases.php?action=delete&id=<?= $l['id'] ?>"
                 class="btn btn-danger btn-sm btn-icon"
                 onclick="return confirmDelete('Delete this lease?')"><i class="fa-solid fa-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-file-contract"></i><p>No leases yet.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Create New Lease</div>
      <button class="modal-close" onclick="closeModal('addModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="leases.php?action=add">
      <div class="modal-body"><?php leaseFormFields([], $tenants, $shops) ?></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Create Lease</button>
      </div>
    </form>
  </div>
</div>

<?php if ($editLease): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Edit Lease</div>
      <button class="modal-close" onclick="location.href='leases.php'"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="leases.php?action=edit&id=<?= $editLease['id'] ?>">
      <div class="modal-body"><?php leaseFormFields($editLease, $tenants, $shops) ?></div>
      <div class="modal-footer">
        <a href="leases.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
