<?php
require_once 'auth.php';

if (!isset($_SESSION['username'])) {
    header("Location: LOGIN.php");
    exit();
}

// FiveSix Legazpi Cafe — Orders
$host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port = 19494;
$dbname = "main";
$username = "avnadmin";
$password = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";


// this is for connection of php to mysql
$conn = mysqli_connect($host, $username, $password, $dbname, $port);

/*              WAHAHAHHAHAHA COMMENT OUT MUNA
$host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port = 19494;
$dbname = "login_credentials";
$username_db = "avnadmin";
$password_db = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$conn2 = mysqli_connect($host, $username_db, $password_db, $dbname, $port);

*/

//------------LOGGED IN USER
$username = $_SESSION['username'];
$query = "SELECT email, username, roles, datejoined FROM login_credentials.employee_credentials WHERE username = ? OR email = ?";
$stmt = $conn->prepare($query);
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


if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
} 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['order_id'], $data['status'])) {
        $order_id = intval($data['order_id']);
        $status = $conn->real_escape_string($data['status']);
        $result = $conn->query("UPDATE orders SET status = '$status' WHERE order_id = $order_id");
        echo json_encode(['success' => (bool)$result]);
        $conn->close();
        exit;
    }
}

$items = [];
$sql = "SELECT o.order_id, o.order_ref, o.customer_first, o.customer_last, o.total, o.status FROM orders o ORDER BY order_id DESC";

$queryResult = $conn->query($sql);
$ordersData = [];

while ($row = $queryResult->fetch_assoc()){
    $order_id = $row['order_id'];
    
    $items = [];
    $itemSql = "SELECT oi.product_name, oi.unit_price, oi.quantity, p.emoji
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = $order_id";

    $itemResult = $conn->query($itemSql);
    while ($item = $itemResult->fetch_assoc()) {
        $items[] = [
          'name'  => $item['product_name'],
          'price' => (float)$item['unit_price'],
          'qty'   => (int)$item['quantity'],
          'emoji' => $item['emoji'] ?? '☕'
        ];
    }

    $ordersData[] = [
        'id'     => $order_id,
        'label'  => $row['order_ref'],
        'status' => $row['status'] ?? 'pending',
        'avatar' => $row['customer_first'][0] ?? '👤',
        'items'  => $items,
        'total'  => $row['total']
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FiveSix Legazpi Cafe — Orders</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="OrderingSystem.css" rel="stylesheet">
</head>

<body>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="avatar">👤</div>
    <div class="sidebar-name"><?php echo htmlspecialchars($displayName); ?></div>
    <nav class="nav-links">

    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a class="nav-link" href="Merchandise.php"><span class="nav-icon">🖥</span> Dashboard</a>
        <a class="nav-link" href="Stocks.php"><span class="nav-icon">📦</span> Stocks</a>
      <?php endif; ?>
      
      <a class="nav-link" href="Cashier.php"><span class="nav-icon"><img src="IMAGES/P9.png" style="width:22px;height:22px;"></span> Cashier</a>
      <a class="nav-link active" href="OrderingSystem.php"><span class="nav-icon">📋</span> Orders</a>
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
        <input type="text" id="searchInput" placeholder="Search orders..." oninput="applyFilters()">
      </div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-time" id="liveTime"></div>
      </div>
    </div>

    <!-- ORDER TABS -->
    <div class="order-tabs-wrap">
      <div class="order-tabs" id="orderTabs"></div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
      <span class="filter-label">Status:</span>
      <button class="status-chip all active" onclick="setStatusFilter('all', this)">All</button>
      <button class="status-chip completed" onclick="setStatusFilter('completed', this)">Completed</button>
      <button class="status-chip rejected" onclick="setStatusFilter('cancelled', this)">Cancelled</button>
      <span class="filter-right" id="orderCount"></span>
    </div>

    <!-- ORDERS GRID -->
    <div class="orders-scroll">
      <div class="orders-grid" id="ordersGrid"></div>
    </div>

    <!-- PAGINATION -->
    <div class="pagination">
      <button class="page-btn" id="prevBtn" onclick="changePage(-1)">← Previous</button>
      <span class="page-num" id="pageNum"></span>
      <button class="page-btn" id="nextBtn" onclick="changePage(1)">Next →</button>
    </div>
  </div>

  <!-- ORDER DETAIL MODAL -->
  <div class="modal-overlay" id="orderModal">
    <div class="modal">
      <button class="modal-close" onclick="closeModal()">✕</button>
      <div class="modal-title" id="modalTitle"></div>
      <div class="modal-order-items" id="modalItems"></div>
      <hr class="modal-divider">
      <div class="modal-total">
        <span>Total</span>
        <span id="modalTotal" style="color:var(--accent)"></span>
      </div>
      <div class="modal-status-row">
        <select class="modal-status-select" id="modalStatus">
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <button class="modal-save-btn" onclick="saveStatus()">Save Status</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    
    let orders = <?php echo json_encode($ordersData); ?>;
    

    // ─── STATE ───────────────────────────────────────────────────────
    let currentPage = 1;
    const ORDERS_PER_PAGE = 8;
    let statusFilter = 'all';
    let searchQuery = '';
    let selectedOrderId = null;

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

    // ─── FILTERS ─────────────────────────────────────────────────────
    function setStatusFilter(s, btn) {
      statusFilter = s;
      document.querySelectorAll('.status-chip').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentPage = 1;
      render();
    }

    function applyFilters() {
      searchQuery = document.getElementById('searchInput').value.toLowerCase();
      currentPage = 1;
      render();
    }

    function getFiltered() {
      return orders.filter(o => {
        const matchStatus = statusFilter === 'all' || o.status === statusFilter;
        const matchSearch = !searchQuery || o.label.toLowerCase().includes(searchQuery) ||
          o.items.some(i => i.name.toLowerCase().includes(searchQuery));
        return matchStatus && matchSearch;
      });
    }

    // ─── RENDER TABS ─────────────────────────────────────────────────
    function renderTabs() {
      const filtered = getFiltered();
      const total = filtered.length;
      const totalPages = Math.max(1, Math.ceil(total / ORDERS_PER_PAGE));
      const tabsEl = document.getElementById('orderTabs');
      tabsEl.innerHTML = Array.from({
        length: totalPages
      }, (_, i) => {
        const start = i * ORDERS_PER_PAGE;
        const pageOrders = filtered.slice(start, start + ORDERS_PER_PAGE);
        const nums = pageOrders.map(o => o.label).join(', ');
        const firstId = pageOrders[0]?.id;
        const lastId = pageOrders[pageOrders.length - 1]?.id;
        const label = pageOrders.length ? `ORDER #${lastId}` + (pageOrders.length > 1 ? ` ... #${firstId}` : '') : `Page ${i+1}`;
        return `<button class="order-tab ${currentPage === i+1 ? 'active' : ''}" onclick="goPage(${i+1})">${pageOrders.map(o=>o.label).join(' · ')}</button>`;
      }).join('');
      // Simplify to just show order numbers in tab groups of current page's orders
      const start = (currentPage - 1) * ORDERS_PER_PAGE;
      const pageOrders = filtered.slice(start, start + ORDERS_PER_PAGE);
      tabsEl.innerHTML = Array.from({
        length: totalPages
      }, (_, i) => {
        const s = i * ORDERS_PER_PAGE;
        const po = filtered.slice(s, s + ORDERS_PER_PAGE);
        if (!po.length) return '';
        const label = po.map(o => o.label).join(' · ');
        return `<button class="order-tab ${currentPage === i+1 ? 'active' : ''}" onclick="goPage(${i+1})" title="${label}">Page ${i+1} (${po.length} orders)</button>`;
      }).join('');
    }

    // ─── RENDER ORDERS ───────────────────────────────────────────────
    function statusBadge(s) {
      const map = {
        completed: 'badge-completed',
        cancelled: 'badge-rejected',
        rejected: 'badge-rejected',
        pending: 'badge-pending'
      };
      const labels = {
        completed: 'COMPLETED',
        cancelled: 'CANCELLED',
        rejected: 'CANCELLED',
        pending: 'PENDING'
      };
      return `<span class="status-badge ${map[s]}">${labels[s]}</span>`;
    }

    function render() {
      renderTabs();
      const filtered = getFiltered();
      const total = filtered.length;
      const totalPages = Math.max(1, Math.ceil(total / ORDERS_PER_PAGE));
      const start = (currentPage - 1) * ORDERS_PER_PAGE;
      const pageOrders = filtered.slice(start, start + ORDERS_PER_PAGE);

      document.getElementById('orderCount').textContent = `Showing ${pageOrders.length} of ${total} orders`;
      document.getElementById('pageNum').innerHTML = `Page <span>${currentPage}</span> of ${totalPages}`;
      document.getElementById('prevBtn').disabled = currentPage <= 1;
      document.getElementById('nextBtn').disabled = currentPage >= totalPages;

      const grid = document.getElementById('ordersGrid');
      if (!pageOrders.length) {
        grid.innerHTML = `<div class="empty-state"><div class="big">📋</div>No orders found.</div>`;
        return;
      }

      grid.innerHTML = pageOrders.map((order, idx) => {
        const total = order.items.reduce((s, i) => s + i.price * i.qty, 0);
        const showItems = order.items.slice(0, 2);
        const isPending = order.status === 'pending';
        const delay = idx * 0.05;
        return `
      <div class="order-card" style="animation-delay:${delay}s" onclick="openOrder(${order.id})">
        <div class="card-header">
          <span class="card-order-num">${order.label}</span>
          <div class="card-avatar">${order.avatar}</div>
        </div>
        <div class="card-items">
          ${showItems.map(item => `
            <div class="card-item">
              <div class="item-img">${item.emoji}</div>
              <div class="item-info">
                <div class="item-name">${item.name}</div>
                <div class="item-meta">₱${item.price} &nbsp;·&nbsp; Qty: ${item.qty}</div>
              </div>
            </div>
          `).join('')}
          ${order.items.length > 2 ? `<div style="font-size:11.5px;color:var(--muted);padding-left:4px;">+${order.items.length - 2} more item(s)</div>` : ''}
        </div>
        <div class="card-footer">
          <span class="card-item-count">x${order.items.reduce((s,i)=>s+i.qty,0)} items</span>
          <div style="display:flex;align-items:center;gap:8px;">
            ${isPending ? `
              <div class="card-actions" onclick="event.stopPropagation()">
                <button class="action-btn accept" onclick="event.stopPropagation(); quickStatus(${order.id},'completed')" title="Accept">✓</button>
<button class="action-btn reject" onclick="event.stopPropagation(); quickStatus(${order.id},'cancelled')" title="Cancel">✕</button>
              </div>
            ` : statusBadge(order.status)}
          </div>
        </div>
      </div>
    `;
      }).join('');
    }

    // ─── QUICK STATUS ────────────────────────────────────────────────
    function quickStatus(id, status) {
      const o = orders.find(o => o.id == id);
      if (!o) return;
      fetch('OrderingSystem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: id, status: status })
      });
      o.status = status;
      render();
      showToast(`${o.label} marked as ${status}`);
    }

    // ─── MODAL ───────────────────────────────────────────────────────
    function openOrder(id) {
      const o = orders.find(o => o.id === id);
      if (!o) return;
      selectedOrderId = id;
      document.getElementById('modalTitle').textContent = o.label;
      const total = o.items.reduce((s, i) => s + i.price * i.qty, 0);
      document.getElementById('modalItems').innerHTML = o.items.map(item => `
    <div class="modal-item">
      <div class="modal-item-img">${item.emoji}</div>
      <div>
        <div class="modal-item-name">${item.name}</div>
        <div class="modal-item-meta">₱${item.price} × ${item.qty}</div>
      </div>
      <div class="modal-item-price">₱${item.price * item.qty}</div>
    </div>
  `).join('');
      document.getElementById('modalTotal').textContent = `₱${total}`;
      document.getElementById('modalStatus').value = o.status;
      document.getElementById('orderModal').classList.add('open');
    }

    function closeModal() {
      document.getElementById('orderModal').classList.remove('open');
    }

    function saveStatus() {
      const o = orders.find(o => o.id === selectedOrderId);
      if (!o) return;
      const newStatus = document.getElementById('modalStatus').value;

      fetch('OrderingSystem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: o.id, status: newStatus })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          o.status = newStatus;
          closeModal();
          render();
          showToast(`${o.label} updated to ${newStatus}`);
        }
      });
    }

    // ─── PAGINATION ──────────────────────────────────────────────────
    function changePage(delta) {
      const filtered = getFiltered();
      const totalPages = Math.max(1, Math.ceil(filtered.length / ORDERS_PER_PAGE));
      currentPage = Math.min(totalPages, Math.max(1, currentPage + delta));
      render();
    }

    function goPage(p) {
      currentPage = p;
      render();
    }

    // ─── TOAST ───────────────────────────────────────────────────────
    let toastTimer;

    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('show'), 2400);
    }

    render();
  </script>
</body>

</html>
