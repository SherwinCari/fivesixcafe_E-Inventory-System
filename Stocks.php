<?php
require_once 'auth.php';
requireRole('admin');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: LOGIN.php");
    exit();
}

//for login credentials to connect to the database
$host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port = 19494;
$dbname = "login_credentials";
$username_db = "avnadmin";
$password_db = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$conn2 = mysqli_connect($host, $username_db, $password_db, $dbname, $port);

//----------------- LOGGED IN USER -----------
$username = $_SESSION['username'];
$query = "SELECT email, username, roles, datejoined FROM employee_credentials WHERE username = ? OR email = ?";
$stmt = $conn2->prepare($query);
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: LOGIN.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

$roles = $user['roles'];
$displayName = ucfirst($username);

//login credentials query dont remove comment here
$username = $_SESSION['username'];
$query = "SELECT email, username FROM employee_credentials WHERE username = ? OR email = ?";
$stmt = $conn2->prepare($query);
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

$user = $result->fetch_assoc();
$stmt->close();

$user_email = $user['email'];
$user_name = ucfirst($username);



//for main database
$host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port = 19494;
$dbname = "main";
$username_db = "avnadmin";
$password_db = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$conn = mysqli_connect($host, $username_db, $password_db, $dbname, $port);

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'restock') {

    
    
    $id  = intval($_POST['id']);
    $qty = floatval($_POST['qty']);

    $query = "UPDATE ingredients SET current_quantity = current_quantity + ? WHERE ingredient_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("di", $qty, $id);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
      // Get ingredient name for logging
      $ingredient = $conn->query("SELECT ingredient_name FROM ingredients WHERE ingredient_id = {$id}")->fetch_assoc();
      $ingredient_name = $ingredient['ingredient_name'];

      // Log to activity_logs with logged-in user's info
      $log_query = "INSERT INTO activity_logs (user_id, user_name, type, action, detail, ref, ip_address, created_at, email) 
                    VALUES (1, '$user_name', 'restock', 'Restock recorded', CONCAT('Restocked ', '$ingredient_name', ' by $qty units'), 'FS-', '127.0.0.1', NOW(), '$user_email')";
      $conn->query($log_query);
    }

    echo json_encode(['success' => true]);
    exit;
}
 
 
  if ($action === 'edit') {
    $id       = intval($_POST['id']);
    $name     = $conn->real_escape_string($_POST['name']);
    $qty  = floatval($_POST['qty']);

    $query = "UPDATE ingredients SET ingredient_name = ?, current_quantity = ? WHERE ingredient_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sdi", $name, $qty, $id);
    $result = $stmt->execute();
    $stmt->close();
    if ($result) {
      $ingredient = $conn->query("SELECT ingredient_name FROM ingredients WHERE ingredient_id = $id")->fetch_assoc();
      $ingredient_name = $ingredient['ingredient_name'];

      $log_query = "INSERT INTO activity_logs (user_id, user_name, type, action, detail, ref, ip_address, created_at, email) 
                    VALUES (1, '$user_name', 'stock', 'Stock reduced', CONCAT('Reduced ', '$ingredient_name', ' to $qty units'), 'FS-', '127.0.0.1', NOW(), '$user_email')";
      $conn->query($log_query);
    }
    echo json_encode(['success' => (bool)$result]);
    exit;
  }
 
  //add ingredients
  if ($action === 'add') {
    $name     = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']);
    $unit     = $conn->real_escape_string($_POST['unit']);
    $qty      = floatval($_POST['qty']);
   $query = "INSERT INTO ingredients (ingredient_name, category, unit_of_measure, current_quantity) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssd", $name, $category, $unit, $qty);
    $result = $stmt->execute();
    $insert_id = $stmt->insert_id;
    $stmt->close();
    if ($result) {
      $log_query = "INSERT INTO activity_logs (user_id, user_name, type, action, detail, ref, ip_address, created_at, email) 
                    VALUES (1, '$user_name', 'Add', 'Stock Added', CONCAT('Added ', '$name', ' to stocks'), 'FS-', '127.0.0.1', NOW(), '$user_email')";
      $conn->query($log_query);
    }
    echo json_encode(['success' => (bool)$result, 'id' => $insert_id]);
    exit;
  }
 
  //set ingredients to 0 when deleted for record purposes
  if ($action === 'delete') {
    $id = intval($_POST['id']);
    $ing_stmt = $conn->prepare("SELECT ingredient_name FROM ingredients WHERE ingredient_id = ?");
    $ing_stmt->bind_param("i", $id);
    $ing_stmt->execute();
    $ingredient = $ing_stmt->get_result()->fetch_assoc();
    $ing_stmt->close();

    if (!$ingredient) {
      echo json_encode(['success' => false]);
      exit;
    }

    $ingredient_name = $ingredient['ingredient_name'];

    $del_stmt = $conn->prepare("UPDATE ingredients SET is_active = 0 WHERE ingredient_id = ?");
    $del_stmt->bind_param("i", $id);
    $result = $del_stmt->execute();
    $del_stmt->close();

    if ($result) {
      $log_query = "INSERT INTO activity_logs (user_id, user_name, type, action, detail, ref, ip_address, created_at, email) 
                    VALUES (1, ?, 'Delete', 'Stock Deleted', ?, 'FS-', '127.0.0.1', NOW(),?)";
      $log_stmt = $conn->prepare($log_query);
      $detail = "Deleted $ingredient_name";
      $log_stmt->bind_param("sss", $user_name, $detail, $user_email);
      $log_stmt->execute();
      $log_stmt->close();
    }
    echo json_encode(['success' => (bool)$result]);
    exit;
  }
}

$ing_result = $conn->query("SELECT ingredient_id AS id, ingredient_name AS name, category, unit_of_measure AS unit, current_quantity AS qty FROM ingredients WHERE is_active = 1 ORDER BY category, ingredient_name");
$ingredients_php = [];
while ($row = $ing_result->fetch_assoc()) {
  $row['qty'] = floatval($row['qty']);
  $ingredients_php[] = $row;
}
$ingredients_json = json_encode($ingredients_php);

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FiveSix Legazpi Cafe — Stocks</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="Stocks.css" type="text/css" rel="stylesheet">
  
</head>

<body>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="avatar">👤</div>
    <div class="sidebar-name"><?php echo htmlspecialchars($displayName); ?></div>

    <nav class="nav-links">
    
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a class="nav-link" href="Merchandise.php"><span class="nav-icon">🖥</span> Dashboard</a>
        <a class="nav-link active" href="Stocks.php"><span class="nav-icon">📦</span> Stocks</a>
      <?php endif; ?>
      
      <a class="nav-link" href="Cashier.php"><span class="nav-icon"><img src="IMAGES/P9.png" style="width:22px;height:22px;"></span> Cashier</a>
      <a class="nav-link" href="OrderingSystem.php"><span class="nav-icon">📋</span> Orders</a>
      <a class="nav-link" href="Profile.php"><span class="nav-icon">👤</span> Profile</a>
    </nav>
    <div class="sidebar-spacer"></div>
    <a class="logout-btn" href="LOGIN.php">Log out ➜</a>
  </aside>

  <!-- CONTENT -->
  <div class="content">
    <!-- TOPBAR -->
    <div class="topbar">
      <div class="brand">
        <div class="brand-icon">☕</div>
        <span class="brand-name">FiveSix Legazpi Cafe</span>
      </div>
      <div class="search-bar">
        <span>🔍</span>
        <input type="text" id="searchInput" placeholder="Search products..." oninput="applySearch()">
      </div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-time" id="liveTime"></div>
      </div>
    </div>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-title">📦 Stock Management</div>
      <div class="page-sub">Monitor inventory levels, restock items, and manage product availability.</div>
    </div>

    <!-- KPI ROW -->
    <div class="kpi-row" id="kpiRow"></div>

    <!-- TOOLBAR -->
    <div class="toolbar">
      <span style="font-size:12px;font-weight:600;color:var(--muted);">Filter:</span>
      <button class="filter-chip active" onclick="setFilter('all',this)">All</button>
      <button class="filter-chip chip-ok" onclick="setFilter('ok',this)">In Stock</button>
      <button class="filter-chip chip-low" onclick="setFilter('low',this)">Low</button>
      <button class="filter-chip chip-crit" onclick="setFilter('crit',this)">Critical</button>
      <button class="filter-chip chip-out" onclick="setFilter('out',this)">Out of Stock</button>
      <div class="toolbar-right">
        <button class="add-stock-btn" onclick="openAddProduct()">＋ Add Ingredient</button>
      </div>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
      <table class="stock-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Quantity</th>
            <th>Stock Level</th>
            <th class="center">Status</th>
            <th class="center">Actions</th>
          </tr>
        </thead>
        <tbody id="stockTableBody"></tbody>
      </table>
    </div>
  </div>

  <!-- RESTOCK MODAL -->
  <div class="modal-overlay" id="restockModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal('restockModal')">✕</button>
      <div class="modal-title">Restock Item</div>
      <div class="modal-product-info" id="restockProductInfo"></div>
      <div class="modal-field">
        <label>Current Stock</label>
        <input type="number" id="currentStockDisplay" disabled>
      </div>
      <div class="modal-field">
        <label>Add Quantity</label>
        <input type="number" id="restockQty" placeholder="Enter amount to add..." min="1">
      </div>
      <div class="modal-actions">
        <button class="modal-cancel" onclick="closeModal('restockModal')">Cancel</button>
        <button class="modal-save" onclick="confirmRestock()">Confirm Restock</button>
      </div>
    </div>
  </div>

  <!-- EDIT MODAL -->
  <div class="modal-overlay" id="editModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal('editModal')">✕</button>
      <div class="modal-title">remove </div>
      <input type="hidden" id="editProductId">
      <div class="modal-field">
        <label>Product Name</label>
        <input type="text" id="editName" placeholder="Product name...">
      </div>
      <div class="modal-field">
        <label>Stock Quantity</label>
        <input type="number" id="editStock" placeholder="0" min="0">
      </div>
      <div class="modal-actions">
        <button class="modal-cancel" onclick="closeModal('editModal')">Cancel</button>
        <button class="modal-save" onclick="confirmEdit()">Save Changes</button>
      </div>
    </div>
  </div>


<!-- ADD INGREDIENT MODAL -->
<div class="modal-overlay" id="addProductModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addProductModal')">✕</button>
    <div class="modal-title">Add New Ingredient</div>
    <div class="modal-field">
      <label>Ingredient Name</label>
      <input type="text" id="newName" placeholder="e.g. Espresso Beans...">
    </div>
    <div class="modal-field">
      <label>Category</label>
      <select id="newCategory">
        <option value="Espresso">Espresso</option>
        <option value="Milk">Milk</option>
        <option value="Syrup">Syrup</option>
        <option value="Chocolate">Chocolate</option>
        <option value="Topping">Topping</option>
        <option value="Base">Base</option>
        <option value="Non-Caffeine">Non-Caffeine</option>
      </select>
    </div>
    <div class="modal-field">
      <label>Unit of Measure</label>
      <select id="newUnit">
        <option value="g">Grams (g)</option>
        <option value="ml">Milliliters (ml)</option>
        <option value="l">Liters (l)</option>
        <option value="kg">Kilograms (kg)</option>
        <option value="oz">Ounces (oz)</option>
      </select>
    </div>
    <div class="modal-field">
      <label>Initial Quantity</label>
      <input type="number" id="newStock" placeholder="0" min="0" step="0.01">
    </div>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeModal('addProductModal')">Cancel</button>
      <button class="modal-save" onclick="confirmAddProduct()">Add Ingredient</button>
    </div>
  </div>
</div>

  <div class="toast" id="toast"></div>

  <script>
    let products = <?= $ingredients_json ?>; //GET ALL DATA FROM PHP SIR

    let currentFilter = 'all';
    let searchQuery = '';
    let selectedProductId = null;

    // ─── CLOCK ───────────────────────────────────────────────────────
    function updateClock() {
      const now = new Date();
      document.getElementById('liveDate').textContent = now.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric'
      });
      document.getElementById('liveTime').textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
      });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ─── STOCK HELPERS ───────────────────────────────────────────────
    function getStockLevel(stock) {
      if (stock === 0) return 'out';
      if (stock <= 5) return 'crit';
      if (stock <= 10) return 'low';
      return 'ok';
    }

    function getStockBadge(stock) {
      const lvl = getStockLevel(stock);
      const map = {
        ok: {
          cls: 'badge-ok',
          label: 'In Stock'
        },
        low: {
          cls: 'badge-low',
          label: 'Low Stock'
        },
        crit: {
          cls: 'badge-crit',
          label: 'Critical'
        },
        out: {
          cls: 'badge-out',
          label: 'Out of Stock'
        },
      };
      return `<span class="stock-badge ${map[lvl].cls}">${map[lvl].label}</span>`;
    }

    function getBarColor(stock) {
      const lvl = getStockLevel(stock);
      return {
        ok: '#3a7d54',
        low: '#b07a10',
        crit: '#b04040',
        out: '#bbb'
      } [lvl];
    }

    // ─── FILTER / SEARCH ─────────────────────────────────────────────
    function setFilter(f, btn) {
      currentFilter = f;
      document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderTable();
    }

    function applySearch() {
      searchQuery = document.getElementById('searchInput').value.toLowerCase();
      renderTable();
    }

    function getFiltered() {
      return products.filter(p => {
        const lvl = getStockLevel(p.qty);
        const matchFilter = currentFilter === 'all' || lvl === currentFilter;
        const matchSearch = !searchQuery || p.name.toLowerCase().includes(searchQuery) || p.category.toLowerCase().includes(searchQuery);
        return matchFilter && matchSearch;
      });
    }

    // ─── KPI ─────────────────────────────────────────────────────────
    function renderKPI() {
      const total = products.length;
      const out = products.filter(p => p.qty === 0).length;
      const crit = products.filter(p => p.qty > 0 && p.qty <= 5).length;
      const low = products.filter(p => p.qty > 5 && p.qty <= 10).length;
      const ok = products.filter(p => p.qty > 10).length;
      document.getElementById('kpiRow').innerHTML = `
        <div class="kpi-card">
          <div class="kpi-label">Total Products</div>
          <div class="kpi-value">${total}</div>
          <div class="kpi-sub">across all categories</div>
        </div>
        <div class="kpi-card ok">
          <div class="kpi-label">In Stock</div>
          <div class="kpi-value">${ok}</div>
          <div class="kpi-sub">stock &gt; 10 units</div>
        </div>
        <div class="kpi-card warn">
          <div class="kpi-label">Low / Critical</div>
          <div class="kpi-value">${crit + low}</div>
          <div class="kpi-sub">need restocking soon</div>
        </div>
        <div class="kpi-card alert">
          <div class="kpi-label">Out of Stock</div>
          <div class="kpi-value">${out}</div>
          <div class="kpi-sub">unavailable to sell</div>
        </div>
      `;
    }

    // ─── TABLE ───────────────────────────────────────────────────────
    function renderTable() {
      const filtered = getFiltered();
      const tbody = document.getElementById('stockTableBody');
      if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);font-size:14px;">No products found.</td></tr>`;
        return;
      }
      tbody.innerHTML = filtered.map(p => {
        const maxRef = Math.max(p.qty
, 30);
        const barPct = p.qty
 === 0 ? 0 : Math.round((p.qty
 / maxRef) * 100);
        const barColor = getBarColor(p.qty
);
        return `
      <tr>
        <td style="color:var(--muted);font-size:12.5px;">${p.name}</td>
        <td style="color:var(--muted);font-size:12.5px;">${p.category}</td>
        <td style="font-weight:600;color:var(--accent);">${p.qty}${p.unit}</td>
        <td>
          <div class="stock-bar-wrap">
            <div class="stock-bar-track">
              <div class="stock-bar-fill" style="width:${barPct}%;background:${barColor};"></div>
            </div>
            <span class="stock-num" style="color:${barColor};">${p.qty}</span>
          </div>
        </td>
        <td class="center">${getStockBadge(p.qty)}</td>
        <td class="center">
          <div class="action-row" style="justify-content:center;">
            <button class="tbl-btn" onclick="openRestock(${p.id})">＋ Restock</button>
            <button class="tbl-btn" onclick="openEdit(${p.id})">- Reduce</button>
            <button class="tbl-btn danger" onclick="deleteProduct(${p.id})">✕</button>
          </div>
        </td>
      </tr>
    `;
      }).join('');
    }

    function render() {
      renderKPI();
      renderTable();
    }

    // ─── RESTOCK ─────────────────────────────────────────────────────
    function openRestock(id) {
      const p = products.find(x => x.id == id);
      if (!p) return;
      selectedProductId = id;
      document.getElementById('restockProductInfo').innerHTML = `
    <div class="modal-product-emoji">${p.emoji}</div>
    <div>
      <div class="modal-product-name">${p.name}</div>
      <div class="modal-product-cat">${p.category}</div>
    </div>
  `;
      document.getElementById('currentStockDisplay').value = p.qty;
      document.getElementById('restockQty').value = '';
      openModal('restockModal');
    }

    function confirmRestock() {
      const p = products.find(x => x.id == selectedProductId);
      if (!p) return;
      const qty = parseInt(document.getElementById('restockQty').value) || 0;
      if (qty <= 0) {
        showToast('Please enter a valid quantity.');
        return;
      }
      fetch('Stocks.php', {
          method: 'POST',
          body: new URLSearchParams({
            action: 'restock',
            id: p.id,
            qty: qty
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            p.qty
 += qty;
            closeModal('restockModal');
            render();
            showToast(`✓ ${p.name} restocked by ${qty}. New stock: ${p.qty
}`);
          } else {
            showToast('Failed to restock. Try again.');
          }
        });
    }


    // ─── EDIT ─────────────────────────────────────────────────────────
    function openEdit(id) {
      const p = products.find(x => x.id == id);
      if (!p) return;
      selectedProductId = id;
      document.getElementById('editProductId').value = id;
      document.getElementById('editName').value = p.name;
      document.getElementById('editStock').value = p.qty;
      openModal('editModal');
    }

    function confirmEdit() {

      const p = products.find(x => x.id == selectedProductId);


      if (!p) return;

      const name = document.getElementById('editName').value.trim() || p.name;

      const qty = parseInt(document.getElementById('editStock').value) || 0;

      if (qty > p.qty) {
        showToast('Cannot increase stock here. Use Restock instead.');
        return;
      };

      fetch('Stocks.php', {

        method: 'POST',

        body: new URLSearchParams({
          action: 'edit',
          id: p.id,
          name,
          qty
        })

      })

      .then(res => res.json())

      .then(data => {

        if (data.success) {

          p.name = name;
          p.qty = qty;

          closeModal('editModal');

          render();

          showToast(`✓ ${p.name} updated successfully.`);

        } else {
          showToast('Failed to update.');
        }
      });
    }

    // ─── ADD PRODUCT ─────────────────────────────────────────────────
    function openAddProduct() {
      openModal('addProductModal');
    }

  function confirmAddProduct() {
  const name    = document.getElementById('newName').value.trim();
  const category = document.getElementById('newCategory').value;
  const unit     = document.getElementById('newUnit').value;
  const qty      = parseFloat(document.getElementById('newStock').value) || 0;
  if (!name) { showToast('Product name is required.'); return; }

  fetch('Stocks.php', {
    method: 'POST',
    body: new URLSearchParams({ action: 'add', name, category, unit, qty })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      products.push({ id: data.id, name, category, unit, qty });

      document.getElementById('newName').value = '';
      document.getElementById('newCategory').value = 'Espresso';
      document.getElementById('newUnit').value = 'g';
      document.getElementById('newStock').value = '';

closeModal('addProductModal');
      render();
      showToast(`✓ ${name} added to inventory.`);
    } else {
      showToast('Failed to add product. Try again.');
    }
  });
}

    // ─── DELETE ──────────────────────────────────────────────────────
    function deleteProduct(id) {
  const p = products.find(x => x.id == id);
  if (!p) return;
  if (!confirm(`Remove "${p.name}" from inventory?`)) return;

  fetch('Stocks.php', {
    method: 'POST',
    body: new URLSearchParams({ action: 'delete', id: id })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      products = products.filter(x => x.id !== id);
      render();
      showToast(`${p.name} removed from inventory.`);
    } else {
      showToast('Failed to delete. Try again.');
    }
  });
}

    // ─── MODAL HELPERS ───────────────────────────────────────────────
    function openModal(id) {
      document.getElementById(id).classList.add('open');
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
    }
    document.querySelectorAll('.modal-overlay').forEach(el => {
      el.addEventListener('click', e => {
        if (e.target === el) el.classList.remove('open');
      });
    });

    // ─── TOAST ───────────────────────────────────────────────────────
    let toastTimer;

    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
    }

    render();
  </script>
</body>

</html>