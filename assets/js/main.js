// ShopLedger Pro — main.js

// Sidebar toggle (mobile)
const sidebar    = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
if (menuToggle) {
  menuToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', e => {
    if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== menuToggle)
      sidebar.classList.remove('open');
  });
}

// Modal helpers
function openModal(id) {
  document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

// Confirm delete
function confirmDelete(msg) {
  return confirm(msg || 'Are you sure you want to delete this record?');
}

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(a => {
  setTimeout(() => {
    a.style.transition = 'opacity .4s';
    a.style.opacity    = '0';
    setTimeout(() => a.remove(), 400);
  }, 4000);
});

// Live table search
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// Print receipt helper
function printReceipt() {
  window.print();
}
