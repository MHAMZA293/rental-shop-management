<?php
// tenants.php — Tenant CRUD
require_once 'includes/config.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Tenants';
$action    = $_GET['action'] ?? '';
$id        = (int)($_GET['id'] ?? 0);

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        $_POST['name']              ?? '',
        $_POST['cnic']              ?? '',
        $_POST['phone']             ?? '',
        $_POST['email']             ?? '',
        $_POST['address']           ?? '',
        $_POST['emergency_contact'] ?? '',
        $_POST['emergency_phone']   ?? '',
    ];

    if ($action === 'add') {
        $stmt = $db->prepare(
            "INSERT INTO tenants (name,cnic,phone,email,address,emergency_contact,emergency_phone)
             VALUES (?,?,?,?,?,?,?)"
        );
        $stmt->execute($data);
        flash('success', 'Tenant added successfully.');
    } elseif ($action === 'edit' && $id) {
        $data[] = $id;
        $stmt = $db->prepare(
            "UPDATE tenants SET name=?,cnic=?,phone=?,email=?,address=?,
             emergency_contact=?,emergency_phone=? WHERE id=?"
        );
        $stmt->execute($data);
        flash('success', 'Tenant updated successfully.');
    }
    header('Location: tenants.php'); exit;
}

// ── Delete ───────────────────────────────────────────────────
if ($action === 'delete' && $id) {
    try {
        $db->prepare("DELETE FROM tenants WHERE id=?")->execute([$id]);
        flash('success', 'Tenant deleted.');
    } catch (PDOException $e) {
        flash('error', 'Cannot delete tenant — existing leases or bills are linked.');
    }
    header('Location: tenants.php'); exit;
}

// Fetch edit target
$editTenant = null;
if ($action === 'edit' && $id) {
    $editTenant = $db->prepare("SELECT * FROM tenants WHERE id=?");
    $editTenant->execute([$id]);
    $editTenant = $editTenant->fetch();
}

// List
$tenants = $db->query(
    "SELECT t.*, COUNT(DISTINCT l.id) AS lease_count,
     COALESCE(SUM(b.outstanding),0) AS total_due
     FROM tenants t
     LEFT JOIN leases l ON l.tenant_id = t.id AND l.status = 'active'
     LEFT JOIN bills b ON b.tenant_id = t.id AND b.status != 'paid'
     GROUP BY t.id
     ORDER BY t.name"
)->fetchAll();

require_once 'includes/header.php';

// Helper to build form HTML
function tenantFormFields(array $t = []): void {
    $f = fn($k) => sanitize($t[$k] ?? '');
    echo '<div class="form-grid">';
    echo '<div class="form-group"><label>Full Name *</label><input type="text" name="name" required value="'.$f('name').'" placeholder="e.g. Muhammad Ali"></div>';
    echo '<div class="form-group"><label>CNIC</label><input type="text" name="cnic" value="'.$f('cnic').'" placeholder="42201-xxxxxxx-x"></div>';
    echo '<div class="form-group"><label>Phone</label><input type="tel" name="phone" value="'.$f('phone').'" placeholder="0300-0000000"></div>';
    echo '<div class="form-group"><label>Email</label><input type="email" name="email" value="'.$f('email').'" placeholder="tenant@email.com"></div>';
    echo '<div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact" value="'.$f('emergency_contact').'"></div>';
    echo '<div class="form-group"><label>Emergency Phone</label><input type="tel" name="emergency_phone" value="'.$f('emergency_phone').'"></div>';
    echo '<div class="form-group full"><label>Address</label><textarea name="address" rows="2">'.$f('address').'</textarea></div>';
    echo '</div>';
}
?>

<div class="page-header">
  <div>
    <h1>Tenants</h1>
    <p>Manage all tenants in your market</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('addModal')">
    <i class="fa-solid fa-plus"></i> Add Tenant
  </button>
</div>

<!-- Search -->
<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <div class="search-bar">
      <div class="search-input-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="tableSearch" placeholder="Search tenants…">
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <?php if ($tenants): ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>CNIC</th>
          <th>Phone</th>
          <th>Active Leases</th>
          <th>Outstanding</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tenants as $i => $t): ?>
        <tr>
          <td class="text-muted mono"><?= $i+1 ?></td>
          <td class="fw-bold"><?= sanitize($t['name']) ?></td>
          <td class="mono"><?= sanitize($t['cnic'] ?: '—') ?></td>
          <td><?= sanitize($t['phone'] ?: '—') ?></td>
          <td>
            <?php if ($t['lease_count']): ?>
            <span class="badge badge-green"><?= $t['lease_count'] ?> Active</span>
            <?php else: ?>
            <span class="badge badge-gray">None</span>
            <?php endif; ?>
          </td>
          <td class="mono <?= $t['total_due'] > 0 ? 'text-red fw-bold' : 'text-muted' ?>">
            <?= money($t['total_due']) ?>
          </td>
          <td>
            <div class="flex gap-2">
              <a href="ledger.php?tenant_id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Ledger">
                <i class="fa-solid fa-book-open"></i>
              </a>
              <a href="tenants.php?action=edit&id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Edit">
                <i class="fa-solid fa-pen"></i>
              </a>
              <a href="tenants.php?action=delete&id=<?= $t['id'] ?>"
                 class="btn btn-danger btn-sm btn-icon"
                 onclick="return confirmDelete('Delete tenant <?= addslashes(sanitize($t['name'])) ?>?')"
                 title="Delete">
                <i class="fa-solid fa-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <i class="fa-solid fa-users"></i>
      <p>No tenants yet. Add your first tenant!</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Add New Tenant</div>
      <button class="modal-close" onclick="closeModal('addModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="tenants.php?action=add">
      <div class="modal-body">
        <?php tenantFormFields() ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Tenant</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL (auto-open if action=edit) -->
<?php if ($editTenant): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Edit Tenant</div>
      <button class="modal-close" onclick="location.href='tenants.php'"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="tenants.php?action=edit&id=<?= $editTenant['id'] ?>">
      <div class="modal-body">
        <?php tenantFormFields($editTenant) ?>
      </div>
      <div class="modal-footer">
        <a href="tenants.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
