<?php
// shops.php — Shop CRUD
require_once 'includes/config.php';
requireLogin();

$db        = getDB();
$pageTitle = 'Shops';
$action    = $_GET['action'] ?? '';
$id        = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        $_POST['shop_number']  ?? '',
        $_POST['description']  ?? '',
        $_POST['location']     ?? '',
        $_POST['size_sqft']    ?: null,
        $_POST['base_rent']    ?? 0,
        $_POST['status']       ?? 'vacant',
    ];

    if ($action === 'add') {
        $stmt = $db->prepare(
            "INSERT INTO shops (shop_number,description,location,size_sqft,base_rent,status) VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute($data);
        flash('success', 'Shop added successfully.');
    } elseif ($action === 'edit' && $id) {
        $data[] = $id;
        $stmt = $db->prepare(
            "UPDATE shops SET shop_number=?,description=?,location=?,size_sqft=?,base_rent=?,status=? WHERE id=?"
        );
        $stmt->execute($data);
        flash('success', 'Shop updated.');
    }
    header('Location: shops.php'); exit;
}

if ($action === 'delete' && $id) {
    try {
        $db->prepare("DELETE FROM shops WHERE id=?")->execute([$id]);
        flash('success', 'Shop deleted.');
    } catch (PDOException $e) {
        flash('error', 'Cannot delete — leases or bills linked to this shop.');
    }
    header('Location: shops.php'); exit;
}

$editShop = null;
if ($action === 'edit' && $id) {
    $s = $db->prepare("SELECT * FROM shops WHERE id=?");
    $s->execute([$id]);
    $editShop = $s->fetch();
}

$shops = $db->query(
    "SELECT s.*, t.name AS tenant_name, l.monthly_rent
     FROM shops s
     LEFT JOIN leases l ON l.shop_id = s.id AND l.status = 'active'
     LEFT JOIN tenants t ON t.id = l.tenant_id
     ORDER BY s.shop_number"
)->fetchAll();

require_once 'includes/header.php';

function shopFormFields(array $s = []): void {
    $f = fn($k) => sanitize($s[$k] ?? '');
    $statusOpts = ['vacant' => 'Vacant', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance'];
    echo '<div class="form-grid">';
    echo '<div class="form-group"><label>Shop Number *</label><input type="text" name="shop_number" required value="'.$f('shop_number').'" placeholder="e.g. A-01"></div>';
    echo '<div class="form-group"><label>Location</label><input type="text" name="location" value="'.$f('location').'" placeholder="Block A, Ground Floor"></div>';
    echo '<div class="form-group"><label>Base Rent (PKR) *</label><input type="number" name="base_rent" step="0.01" required value="'.($s['base_rent'] ?? '').'" placeholder="12000"></div>';
    echo '<div class="form-group"><label>Size (sq ft)</label><input type="number" name="size_sqft" step="0.01" value="'.($s['size_sqft'] ?? '').'"></div>';
    echo '<div class="form-group"><label>Status</label><select name="status">';
    foreach ($statusOpts as $val => $lbl) {
        $sel = ($s['status'] ?? 'vacant') === $val ? 'selected' : '';
        echo "<option value='$val' $sel>$lbl</option>";
    }
    echo '</select></div>';
    echo '<div class="form-group full"><label>Description</label><textarea name="description" rows="2">'.$f('description').'</textarea></div>';
    echo '</div>';
}
?>

<div class="page-header">
  <div>
    <h1>Shops</h1>
    <p>Manage all shop units in the market</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('addModal')">
    <i class="fa-solid fa-plus"></i> Add Shop
  </button>
</div>

<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <div class="search-bar">
      <div class="search-input-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="tableSearch" placeholder="Search shops…">
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <?php if ($shops): ?>
    <table>
      <thead>
        <tr><th>Shop #</th><th>Location</th><th>Size</th><th>Base Rent</th><th>Current Tenant</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($shops as $s):
          $statusBadge = match($s['status']) {
            'occupied'    => 'badge-green',
            'vacant'      => 'badge-gray',
            'maintenance' => 'badge-amber',
            default       => 'badge-gray',
          };
        ?>
        <tr>
          <td class="fw-bold mono"><?= sanitize($s['shop_number']) ?></td>
          <td><?= sanitize($s['location'] ?: '—') ?></td>
          <td class="mono"><?= $s['size_sqft'] ? number_format($s['size_sqft']).' sqft' : '—' ?></td>
          <td class="mono text-amber fw-bold"><?= money($s['base_rent']) ?></td>
          <td><?= $s['tenant_name'] ? sanitize($s['tenant_name']) : '<span class="text-muted">Vacant</span>' ?></td>
          <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($s['status']) ?></span></td>
          <td>
            <div class="flex gap-2">
              <a href="shops.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm btn-icon">
                <i class="fa-solid fa-pen"></i>
              </a>
              <a href="shops.php?action=delete&id=<?= $s['id'] ?>"
                 class="btn btn-danger btn-sm btn-icon"
                 onclick="return confirmDelete('Delete shop <?= addslashes(sanitize($s['shop_number'])) ?>?')">
                <i class="fa-solid fa-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-shop"></i><p>No shops added yet.</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Add New Shop</div>
      <button class="modal-close" onclick="closeModal('addModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="shops.php?action=add">
      <div class="modal-body"><?php shopFormFields() ?></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Shop</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<?php if ($editShop): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Edit Shop</div>
      <button class="modal-close" onclick="location.href='shops.php'"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="shops.php?action=edit&id=<?= $editShop['id'] ?>">
      <div class="modal-body"><?php shopFormFields($editShop) ?></div>
      <div class="modal-footer">
        <a href="shops.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
