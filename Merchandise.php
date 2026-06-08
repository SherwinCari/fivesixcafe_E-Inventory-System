<?php
require_once 'auth.php';
requireRole('admin');

$username = $_SESSION['username'];
$displayName = ucfirst($username);
// ── DB CONNECTION ─────────────────────────────────────────────────────────────
$host     = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port     = 19494;
$dbname   = "main";
$username = "avnadmin";
$password = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$conn = mysqli_connect($host, $username, $password, $dbname, $port);
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }

// ── INGREDIENTS ───────────────────────────────────────────────────────────────
$query  = "SELECT ingredient_id, ingredient_name, category, unit_of_measure AS units, current_quantity AS qty FROM ingredients WHERE is_active = 1";
$result = mysqli_query($conn, $query);

// ── DAILY CHART DATA ──────────────────────────────────────────────────────────
//wag remove comments muna sa part sa baba
// ===== FETCH DAILY DATA FOR CHART =====
$dailyQuery = "
    SELECT
        DATE(created_at) as date,
        DATE_FORMAT(created_at, '%Y-%m-%d') as day_label,
        COUNT(*) as total_transactions,
        COALESCE(SUM(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(detail, 'Cash:', 1),'Total: ₱',-1) AS DECIMAL(10,2))), 0) as total_revenue
    FROM activity_logs
    WHERE type = 'sale'
    GROUP BY DATE(created_at), DATE_FORMAT(created_at, '%Y-%m-%d')
    ORDER BY DATE(created_at) DESC
    LIMIT 30
";
$dailyResult       = mysqli_query($conn, $dailyQuery);
$dailyLabels       = [];
$dailyRevenue      = [];
$dailyTransactions = [];

if ($dailyResult && mysqli_num_rows($dailyResult) > 0) {
    while ($row = mysqli_fetch_assoc($dailyResult)) {
        $dailyLabels[]       = $row['day_label'];
        $dailyRevenue[]      = (float)($row['total_revenue']      ?? 0);
        $dailyTransactions[] = (int)  ($row['total_transactions']  ?? 0);
    }
    // Reverse to show oldest first
    $dailyLabels       = array_reverse($dailyLabels);
    $dailyRevenue      = array_reverse($dailyRevenue);
    $dailyTransactions = array_reverse($dailyTransactions);
}

// ── MONTHLY CHART DATA ────────────────────────────────────────────────────────
// ===== FETCH MONTHLY DATA FOR CHART =====
$monthlyQuery = "
    SELECT
        DATE_FORMAT(created_at, '%b %Y') as month_label,
        YEAR(created_at)  as year,
        MONTH(created_at) as month,
        COUNT(*) as total_transactions,
        COALESCE(SUM(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(detail, 'Cash:', 1),'Total: ₱',-1) AS DECIMAL(10,2))), 0) as total_revenue
    FROM activity_logs
    WHERE type = 'sale'
    GROUP BY YEAR(created_at), MONTH(created_at), DATE_FORMAT(created_at, '%b %Y')
    ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC
";
$monthlyResult       = mysqli_query($conn, $monthlyQuery);
$monthlyLabels       = [];
$monthlyRevenue      = [];
$monthlyTransactions = [];

if ($monthlyResult && mysqli_num_rows($monthlyResult) > 0) {
    while ($row = mysqli_fetch_assoc($monthlyResult)) {
        $monthlyLabels[]       = $row['month_label'];
        $monthlyRevenue[]      = (float)($row['total_revenue']      ?? 0);
        $monthlyTransactions[] = (int)  ($row['total_transactions']  ?? 0);
    }
}

// ── JSON FOR JS ───────────────────────────────────────────────────────────────
$dailyChartJSON   = json_encode(['labels' => $dailyLabels,   'revenue' => $dailyRevenue,   'transactions' => $dailyTransactions]);
$monthlyChartJSON = json_encode(['labels' => $monthlyLabels, 'revenue' => $monthlyRevenue, 'transactions' => $monthlyTransactions]);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FiveSix Legazpi Cafe — Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&display=swap" rel="stylesheet" />
  <style>
    @import url("GlobalStyles.css");

    /* ── RESET ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* ── TOKENS ── */
    :root {
      --bg:           var(--primary-cream-light);
      --panel:        var(--primary-cream);
      --card:         var(--card-bg);
      --accent:       var(--primary-brown);
      --accent-light: var(--primary-brown-light);
      --accent-hover: var(--primary-brown-dark);
      --text:         var(--text-dark);
      --muted:        var(--text-muted);
      --border:       #d4c3a8;
      --white:        #ffffff;
      --green:        #3a7d54;
      --green-bg:     #e8f5ee;
      --red:          #c0392b;
      --red-bg:       #faeaea;
      --orange:       var(--primary-brown-light);
      --orange-bg:    #fff7e6;
      --shadow:       0 4px 20px rgba(45,27,7,0.10);
      --sidebar-w:    180px;
    }

    body {
      font-family: var(--font-primary);
      background: var(--bg);
      color: var(--text);
      height: 100vh;
      display: flex;
      flex-direction: row;
      overflow: hidden;
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--sidebar-w);
      min-width: var(--sidebar-w);
      background: var(--panel);
      border-right: 1.5px solid var(--border);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 28px 0 20px;
      flex-shrink: 0;
    }

    .avatar {
      width: 60px; height: 60px;
      border-radius: 50%;
      background: var(--white);
      border: 2px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      font-size: 28px;
      margin-bottom: 10px;
    }

    .sidebar-name { font-size: 13.5px; font-weight: 700; text-align: center; color: var(--text); }
    .sidebar-role { font-size: 11px; color: var(--muted); margin-bottom: 28px; }

    .nav-links { width: 100%; display: flex; flex-direction: column; gap: 2px; }

    .nav-link {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 22px;
      font-size: 13.5px; font-weight: 500;
      color: var(--muted);
      text-decoration: none;
      border-left: 3px solid transparent;
      transition: background 0.18s, color 0.18s;
    }

    .nav-link:hover  { background: var(--border); color: var(--text); }
    .nav-link.active { color: var(--accent); font-weight: 600; border-left: 3px solid var(--accent); background: rgba(123,63,46,0.07); }
    .nav-icon        { font-size: 17px; }
    .sidebar-spacer  { flex: 1; }

    .logout-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 14px 16px;
      font-size: 16px; font-weight: 700;
      color: var(--muted);
      text-decoration: none;
      transition: color 0.18s;
      width: 100%;
      text-align: center;
    }

    .logout-btn:visited,
    .logout-btn:hover,
    .logout-btn:active,
    .logout-btn:focus { text-decoration: none; }

    .logout-btn:hover { color: var(--red); }

    /* ── CONTENT ── */
    .content { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

    /* ── TOPBAR ── */
    .topbar {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 28px; height: 64px;
      background: var(--bg);
      border-bottom: 1.5px solid var(--border);
      flex-shrink: 0;
    }

    .brand      { display: flex; align-items: center; gap: 12px; }
    .brand-icon {
      width: 36px; height: 36px; background: var(--accent); border-radius: 50%;
      display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .brand-name { font-size: 15px; font-weight: 700; color: var(--accent); }

    .search-bar {
      display: flex; align-items: center; gap: 8px;
      background: var(--white); border: 1.5px solid var(--border);
      border-radius: 10px; padding: 7px 14px; width: 240px;
      transition: border-color 0.2s;
    }
    .search-bar:focus-within { border-color: var(--accent-light); }
    .search-bar input {
      border: none; background: transparent;
      font-family: "DM Sans", sans-serif;
      font-size: 13px; color: var(--text); width: 100%; outline: none;
    }
    .search-bar input::placeholder { color: var(--muted); }

    .topbar-right { display: flex; align-items: flex-end; flex-direction: column; gap: 2px; }
    .topbar-date  { font-size: 13px; font-weight: 600; }
    .topbar-time  { font-size: 12px; color: var(--muted); }

    /* ── MAIN SCROLL ── */
    .main-scroll {
      flex: 1; overflow-y: auto;
      padding: 20px 28px 24px;
      display: flex; flex-direction: column; gap: 18px;
    }

    /* ── GRID ── */
    .grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; }

    /* ── CARD ── */
    .card {
      background: var(--card);
      border: 1.5px solid var(--border);
      border-radius: 14px; padding: 16px 18px;
      box-shadow: var(--shadow);
      transition: box-shadow 0.18s, transform 0.18s;
    }
    .card:hover { box-shadow: 0 8px 32px rgba(100,40,10,0.14); transform: translateY(-2px); }

    /* ── KPI ── */
    .kpi { display: flex; gap: 14px; align-items: center; }
    .kpi .icon {
      width: 48px; height: 48px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; flex-shrink: 0;
    }
    .kpi .icon.kpi-icon-revenue { background: rgba(58, 125, 84, 0.12); }
    .kpi .icon.kpi-icon-profit  { background: rgba(58, 125, 84, 0.16); }
    .kpi .icon.kpi-icon-cost    { background: rgba(192, 57, 43, 0.12); }
    .kpi .icon.kpi-icon-orders  { background: rgba(140, 92, 56, 0.14); }
    .kpi .value  { font-weight: 800; font-size: 18px; line-height: 1.2; }
    .kpi .value.value-accent { color: var(--accent); }
    .kpi .value.value-danger { color: var(--red); }
    .kpi .label  { color: var(--muted); font-size: 12px; margin-top: 3px; }
    .trend       { font-size: 12px; font-weight: 600; margin-left: 6px; }
    .trend-up    { color: var(--green); }
    .trend-down  { color: var(--red); }

    /* ── TWO-COL ── */
    .two-col { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; }

    .card-title { font-size: 13.5px; font-weight: 700; margin-bottom: 14px; color: var(--text); }

    /* ── BUTTONS ── */
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; border-radius: 9px;
      border: 1.5px solid var(--border);
      background: var(--white);
      font-family: "DM Sans", sans-serif;
      font-size: 12.5px; font-weight: 600;
      cursor: pointer; color: var(--text);
      transition: 0.18s;
    }
    .btn:hover      { border-color: var(--accent-light); color: var(--accent); }
    .btn.btn-active { background: var(--accent); color: #fff; border-color: var(--accent); }
    .btn-group      { display: flex; gap: 8px; margin-bottom: 14px; }

    /* ── TABLE ── */
    .table { width: 100%; border-collapse: collapse; }
    .table thead th {
      padding: 9px 10px; border-bottom: 1.5px solid var(--border);
      text-align: left; font-size: 12px; font-weight: 700;
      color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em;
    }
    .table tbody td         { padding: 10px; border-bottom: 1px solid var(--border); font-size: 13px; }
    .table tbody tr:last-child td { border-bottom: none; }

    /* ── BADGES ── */
    .badge          { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; }
    .badge-critical { background: var(--red-bg);    color: var(--red); }
    .badge-low      { background: var(--orange-bg); color: var(--orange); }
    .badge-ok       { background: var(--green-bg);  color: var(--green); }

    /* ── ALERT LIST ── */
    .alert-list { display: flex; flex-direction: column; gap: 10px; }
    .alert-item { font-size: 13px; display: flex; align-items: center; gap: 8px; }

    /* ── TABLE CONTROLS ── */
    .table-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }

    /* ── MODAL ── */
    .modal-overlay {
      position: fixed; inset: 0;
      background: rgba(30,10,5,0.5);
      display: flex; align-items: center; justify-content: center;
      z-index: 100; backdrop-filter: blur(3px);
      opacity: 0; pointer-events: none;
      transition: opacity 0.22s;
    }
    .modal-overlay.open { opacity: 1; pointer-events: all; }
    .modal {
      background: var(--white); border-radius: 18px;
      padding: 30px 32px 26px;
      width: 700px; max-width: 96vw; max-height: 85vh; overflow-y: auto;
      box-shadow: 0 16px 60px rgba(80,20,5,0.22);
      position: relative; transform: translateY(16px);
      transition: transform 0.22s;
    }
    .modal-overlay.open .modal { transform: translateY(0); }
    .modal-close {
      position: absolute; top: 14px; right: 18px;
      background: none; border: none; font-size: 20px;
      cursor: pointer; color: var(--muted); transition: color 0.15s;
    }
    .modal-close:hover { color: var(--text); }
    .modal-title { font-size: 17px; font-weight: 700; margin-bottom: 4px; color: var(--accent); }
    .modal-sub   { font-size: 12px; color: var(--muted); margin-bottom: 20px; }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .fade-in { animation: fadeUp 0.32s ease both; }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar        { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track  { background: transparent; }
    ::-webkit-scrollbar-thumb  { background: var(--border); border-radius: 10px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 1200px) { .grid-4 { grid-template-columns: repeat(2,1fr); } }

    @media (max-width: 992px) {
      .two-col { grid-template-columns: 1fr; }
      .sidebar { width: 70px; min-width: 70px; padding: 20px 0; }
      .sidebar-name, .sidebar-role { display: none; }
      .nav-link { justify-content: center; padding: 12px; }
      .nav-link span:not(.nav-icon) { display: none; }
      .avatar { width: 40px; height: 40px; font-size: 20px; }
    }

    @media (max-width: 768px) {
      .grid-4 { grid-template-columns: 1fr; }
      .sidebar {
        width: 100%; min-width: unset; height: 60px;
        flex-direction: row; padding: 0 16px;
        position: fixed; bottom: 0; left: 0;
        border-right: none; border-top: 1.5px solid var(--border);
      }
      .nav-links  { flex-direction: row; gap: 4px; }
      .nav-link   { flex-direction: column; padding: 8px 12px; font-size: 10px; gap: 4px; }
      .nav-icon   { font-size: 20px; }
      .sidebar-spacer, .logout-btn { display: none; }
      .content    { margin-bottom: 60px; }
      .topbar     { padding: 0 16px; height: 56px; }
      .search-bar { width: 180px; }
      .brand-name { display: none; }
    }

    @media (max-width: 480px) { .search-bar { display: none; } }
  </style>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="avatar">👤</div>
    <div class="sidebar-name"><?php echo htmlspecialchars($displayName); ?></div>
    <nav class="nav-links">

    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a class="nav-link active" href="Merchandise.php"><span class="nav-icon">🖥</span> Dashboard</a>
        <a class="nav-link" href="Stocks.php"><span class="nav-icon">📦</span> Stocks</a>
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
        <input type="text" id="searchInput" placeholder="Search products…" />
      </div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-time" id="liveTime"></div>
      </div>
    </div>

    <!-- SCROLLABLE MAIN -->
    <div class="main-scroll">

      <!-- KPI CARDS -->
      <section class="grid-4 fade-in">
        <div class="card kpi">
          <div class="icon kpi-icon-revenue">💰</div>
          <div>
            <div class="value value-accent">₱10,000 <span class="trend trend-up"> </span></div>
            <div class="label">Total Revenue (MTD)</div>
          </div>
        </div>
        <div class="card kpi">
          <div class="icon kpi-icon-profit">📈</div>
          <div>
            <div class="value value-accent">₱<?php echo !empty($dailyRevenue) ? number_format(end($dailyRevenue), 2) : '0.00'; ?><span class="trend trend-up"> </span></div>
            <div class="label">daily income</div>
          </div>
        </div>
        <div class="card kpi">
          <div class="icon kpi-icon-cost">💸</div>
          <div>
            <div class="value value-danger">₱<?php echo !empty($monthlyRevenue) ? number_format(end($monthlyRevenue), 2) : '0.00'; ?> <span class="trend trend-down"> </span></div>
            <div class="label">Monthly Income</div>
          </div>
        </div>
        <div class="card kpi">
          <div class="icon kpi-icon-orders">🛒</div>
          <div>
            <div class="value">150<span class="trend trend-down"></span></div>
            <div class="label">Purchases (MTD)</div>
          </div>
        </div>
      </section>

      <!-- CHART + ALERTS -->
      <section class="two-col fade-in" style="animation-delay:0.08s">

        <div class="card">
          <div class="card-title">Sales Analytics</div>
          <div class="btn-group">
            <button class="btn btn-active" id="btnDaily"   onclick="toggleView('daily')">📅 Daily (30 Days)</button>
            <button class="btn"            id="btnMonthly" onclick="toggleView('monthly')">📊 Monthly</button>
          </div>
          <div style="position:relative; height:260px;">
            <canvas id="myChart"></canvas>
          </div>
          <div style="margin-top:12px; display:flex; gap:12px; align-items:center;">
            <button class="btn" onclick="openReport()">View Report</button>
            <button class="btn" id="exportPdfBtn" onclick="exportToPDF()" style="margin-left: auto;">📥 Export PDF</button>
            <span style="color:var(--muted); font-size:12px;">Toggle between daily and monthly views</span>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Alerts &amp; Inventory Health</div>
          <div class="alert-list" id="alertList"></div>
        </div>

      </section>

      <!-- PRODUCT TABLE -->
      <section class="fade-in" style="animation-delay:0.16s">
        <div class="card">
          <div class="card-title">Inventory Reports</div>
          <div class="table-controls">
            <div style="display:flex; gap:8px;">
              <button class="btn" id="sortName">Sort Name</button>
              <button class="btn" id="sortSold">Sort Stocks</button>
            </div>
          </div>
          <table class="table">
            <thead>
              <tr>
                <th>Ingredients Name</th>
                <th>Category</th>
                <th>Remaining</th>
              </tr>
            </thead>
            <tbody id="productsTable">
              <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                  <tr class="product-row">
                    <td><strong><?= htmlspecialchars($row['ingredient_name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['category']) ?></td>
                    <td><span class="count"><?= (int)$row['qty'] ?></span><?= htmlspecialchars($row['units']) ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="3" style="text-align:center; color:var(--muted); padding:30px 0;">No products found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </div><!-- /main-scroll -->
  </div><!-- /content -->

  <!-- REPORT MODAL -->
  <div class="modal-overlay" id="reportModal">
    <div class="modal">
      <button class="modal-close" onclick="closeReport()">✕</button>
      <div class="modal-title">📊 Sales Report</div>
      <div class="modal-sub">Summary of income by date</div>
      <canvas id="reportChart" height="120"></canvas>
      <table style="width:100%; border-collapse:collapse; margin-top:20px; font-size:14px;">
        <thead>
          <tr style="border-bottom:2px solid var(--border);">
            <th style="text-align:left;  padding:8px 0;">Date</th>
            <th style="text-align:right; padding:8px 0;">Income</th>
          </tr>
        </thead>
        <tbody id="reportTableBody"></tbody>
        <tfoot>
          <tr style="border-top:2px solid var(--border); font-weight:700;">
            <td style="padding:10px 0;">Total</td>
            <td style="text-align:right; padding:10px 0; color:var(--accent);" id="reportTotal"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // ── PHP DATA ─────────────────────────────────────────────────────────────
    window.dailyData   = <?php echo $dailyChartJSON;   ?>;
    window.monthlyData = <?php echo $monthlyChartJSON; ?>;

    // ── CLOCK ────────────────────────────────────────────────────────────────
    function updateClock() {
      const now = new Date();
      document.getElementById('liveDate').textContent = now.toLocaleDateString('en-US', { month:'short', day:'2-digit', year:'numeric' });
      document.getElementById('liveTime').textContent = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', hour12:true });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ── CHART ────────────────────────────────────────────────────────────────
    let chartInstance  = null;
    let reportInstance = null;

    function buildChart(data) {
      if (chartInstance) chartInstance.destroy();
      chartInstance = new Chart(document.getElementById('myChart'), {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [
            { label: 'Revenue (₱)',  data: data.revenue,      backgroundColor: '#3A7D54', borderRadius: 6, yAxisID: 'y'  },
            { label: 'Transactions', data: data.transactions, backgroundColor: '#7BA7D4', borderRadius: 6, yAxisID: 'y1' }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { position: 'top', labels: { font: { family: 'DM Sans', size: 12 } } } },
          scales: {
            y:  { beginAtZero: true, ticks: { font: { family: 'DM Sans', size: 11 } } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { family: 'DM Sans', size: 11 } } }
          }
        }
      });
    }

    function toggleView(v) {
      document.getElementById('btnDaily').classList.toggle('btn-active',   v === 'daily');
      document.getElementById('btnMonthly').classList.toggle('btn-active', v === 'monthly');
      buildChart(v === 'daily' ? window.dailyData : window.monthlyData);
    }

    buildChart(window.dailyData);

    // ── REPORT MODAL ─────────────────────────────────────────────────────────
    function openReport() {
      document.getElementById('reportModal').classList.add('open');
      const data = window.dailyData;

      if (reportInstance) reportInstance.destroy();
      reportInstance = new Chart(document.getElementById('reportChart'), {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [{ label: 'Revenue (₱)', data: data.revenue, backgroundColor: '#3A7D54', borderRadius: 4 }]
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, ticks: { font: { family: 'DM Sans', size: 11 } } } }
        }
      });

      const tbody   = document.getElementById('reportTableBody');
      const totalEl = document.getElementById('reportTotal');
      tbody.innerHTML = '';
      let total = 0;
      data.labels.forEach((label, i) => {
        const rev = data.revenue[i] || 0;
        total += rev;
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--border)';
        tr.innerHTML = `<td style="padding:7px 0;">${label}</td><td style="text-align:right;padding:7px 0;">₱${rev.toLocaleString()}</td>`;
        tbody.appendChild(tr);
      });
      totalEl.textContent = '₱' + total.toLocaleString();
    }

    function closeReport() {
      document.getElementById('reportModal').classList.remove('open');
    }

    // ── SORTING ──────────────────────────────────────────────────────────────
    let sortNameAsc = true, sortSoldAsc = true;

    document.getElementById('sortSold').addEventListener('click', function () {
      const tb   = document.getElementById('productsTable');
      const rows = Array.from(tb.querySelectorAll('.product-row'));
      rows.sort((a, b) => {
        const va = parseInt(a.querySelector('.count').textContent.trim(), 10) || 0;
        const vb = parseInt(b.querySelector('.count').textContent.trim(), 10) || 0;
        return sortSoldAsc ? va - vb : vb - va;
      });
      rows.forEach(r => tb.appendChild(r));
      sortSoldAsc = !sortSoldAsc;
    });

    document.getElementById('sortName').addEventListener('click', function () {
      const tb   = document.getElementById('productsTable');
      const rows = Array.from(tb.querySelectorAll('.product-row'));
      rows.sort((a, b) => {
        const na = a.querySelector('strong').textContent.trim().toLowerCase();
        const nb = b.querySelector('strong').textContent.trim().toLowerCase();
        return sortNameAsc ? na.localeCompare(nb) : nb.localeCompare(na);
      });
      rows.forEach(r => tb.appendChild(r));
      sortNameAsc = !sortNameAsc;
    });

    // ── ALERTS / INVENTORY HEALTH ────────────────────────────────────────────
    function updateInventoryHealth() {
      const rows = document.querySelectorAll('.product-row');
      const list = document.getElementById('alertList');
      list.innerHTML = '';
      rows.forEach(row => {
        const name = row.querySelector('strong').textContent;
        const qty  = parseInt(row.querySelector('.count').textContent);
        let cls = 'badge-ok', label = 'OK';
        if      (qty <= 10) { cls = 'badge-critical'; label = 'CRITICAL'; }
        else if (qty <= 20) { cls = 'badge-low';      label = 'LOW'; }
        const div = document.createElement('div');
        div.className = 'alert-item';
        div.innerHTML = `<span class="badge ${cls}">${label}</span> ${name} — <strong>${qty}</strong> left`;
        list.appendChild(div);
      });
    }

    updateInventoryHealth();

    // ── SEARCH ───────────────────────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', function () {
      const q = this.value.toLowerCase();
      document.querySelectorAll('.product-row').forEach(row => {
        const name = row.querySelector('td strong').textContent.toLowerCase();
        const cat  = row.children[1].textContent.toLowerCase();
        row.style.display = (name.includes(q) || cat.includes(q)) ? '' : 'none';
      });
    });
    
    // ── PDF EXPORT ───────────────────────────────────────────────────────────
async function exportToPDF() {
  const btn = document.getElementById('exportPdfBtn');
  btn.disabled = true;
  btn.textContent = '⏳ Generating...';

  try {
    const container = document.getElementById('pdfExportContainer');
    const now = new Date();
    document.getElementById('pdfDate').textContent = now.toLocaleDateString('en-US', { month:'long', day:'2-digit', year:'numeric' });

    // Populate Daily Table
    const dailyTableBody = document.getElementById('pdfDailyTable');
    dailyTableBody.innerHTML = '';
    let dailyTotal = 0;
    let dailyTransTotal = 0;
    window.dailyData.labels.forEach((label, i) => {
      const rev = window.dailyData.revenue[i] || 0;
      const trans = window.dailyData.transactions[i] || 0;
      dailyTotal += rev;
      dailyTransTotal += trans;
      const tr = document.createElement('tr');
      tr.style.borderBottom = '1px solid var(--border)';
      tr.innerHTML = `
        <td style="padding:8px 10px; font-size:12px;">${label}</td>
        <td style="padding:8px 10px; font-size:12px; text-align:right;">₱${rev.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
        <td style="padding:8px 10px; font-size:12px; text-align:right;">${trans}</td>
      `;
      dailyTableBody.appendChild(tr);
    });
    document.getElementById('pdfDailyTotal').textContent = '₱' + dailyTotal.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('pdfDailyTransTotal').textContent = dailyTransTotal;

    // Populate Monthly Table
    const monthlyTableBody = document.getElementById('pdfMonthlyTable');
    monthlyTableBody.innerHTML = '';
    let monthlyTotal = 0;
    let monthlyTransTotal = 0;
    window.monthlyData.labels.forEach((label, i) => {
      const rev = window.monthlyData.revenue[i] || 0;
      const trans = window.monthlyData.transactions[i] || 0;
      monthlyTotal += rev;
      monthlyTransTotal += trans;
      const tr = document.createElement('tr');
      tr.style.borderBottom = '1px solid var(--border)';
      tr.innerHTML = `
        <td style="padding:8px 10px; font-size:12px;">${label}</td>
        <td style="padding:8px 10px; font-size:12px; text-align:right;">₱${rev.toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
        <td style="padding:8px 10px; font-size:12px; text-align:right;">${trans}</td>
      `;
      monthlyTableBody.appendChild(tr);
    });
    document.getElementById('pdfMonthlyTotal').textContent = '₱' + monthlyTotal.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('pdfMonthlyTransTotal').textContent = monthlyTransTotal;

    // Create chart in PDF container
    const pdfChartCanvas = document.getElementById('pdfChart');
    if (window.pdfChartInstance) window.pdfChartInstance.destroy();
    window.pdfChartInstance = new Chart(pdfChartCanvas, {
      type: 'bar',
      data: {
        labels: window.dailyData.labels,
        datasets: [
          { label: 'Revenue (₱)',  data: window.dailyData.revenue,      backgroundColor: '#3A7D54', borderRadius: 6, yAxisID: 'y'  },
          { label: 'Transactions', data: window.dailyData.transactions, backgroundColor: '#7BA7D4', borderRadius: 6, yAxisID: 'y1' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { font: { family: 'DM Sans', size: 14 } } } },
        scales: {
          y:  { beginAtZero: true, ticks: { font: { family: 'DM Sans', size: 12 } } },
          y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { family: 'DM Sans', size: 12 } } }
        }
      }
    });

    // Wait for chart to render
    await new Promise(resolve => setTimeout(resolve, 500));

    // Convert container to canvas
    container.style.display = 'block';
    const canvas = await html2canvas(container, {
      scale: 2,
      logging: false,
      backgroundColor: '#ffffff'
    });
    container.style.display = 'none';

    // Create PDF
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4'
    });

    const imgWidth = 210;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;
    let heightLeft = imgHeight;
    let position = 0;

    const imgData = canvas.toDataURL('image/png');

    while (heightLeft > 0) {
      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
      heightLeft -= 297;
      if (heightLeft > 0) {
        pdf.addPage();
        position = heightLeft - imgHeight;
      }
    }

    pdf.save(`FiveSix_Sales_Report_${now.toISOString().split('T')[0]}.pdf`);

  } catch (error) {
    console.error('PDF Export Error:', error);
    alert('Error generating PDF. Please try again.');
  } finally {
    btn.disabled = false;
    btn.textContent = '📥 Export PDF';
  }
}
</script>

 <!-- PDF EXPORT CONTAINER (Hidden) -->
<div id="pdfExportContainer" style="display:none; position:fixed; top:-9999px; left:-9999px; width:1200px; background:white; padding:40px; font-family:'DM Sans',sans-serif; color:var(--text);">
  <div style="text-align:center; margin-bottom:30px; border-bottom:2px solid var(--accent); padding-bottom:15px;">
    <h1 style="font-size:28px; color:var(--accent); margin:0 0 5px 0;">☕ FiveSix Legazpi Cafe</h1>
    <p style="color:var(--muted); margin:0; font-size:12px;">Sales Report — <span id="pdfDate"></span></p>
  </div>

  <div style="margin-bottom:30px;">
    <div style="font-size:16px; font-weight:700; color:var(--accent); margin-bottom:15px; border-left:4px solid var(--accent); padding-left:10px;">Daily Sales Income (Last 30 Days)</div>
    <table style="width:100%; border-collapse:collapse;">
      <thead>
        <tr style="background:var(--accent); color:white;">
          <th style="padding:10px; text-align:left; font-weight:700; font-size:12px;">Date</th>
          <th style="padding:10px; text-align:right; font-weight:700; font-size:12px;">Revenue (₱)</th>
          <th style="padding:10px; text-align:right; font-weight:700; font-size:12px;">Transactions</th>
        </tr>
      </thead>
      <tbody id="pdfDailyTable"></tbody>
      <tfoot>
        <tr style="background:var(--accent); color:white; font-weight:700;">
          <td style="padding:10px;">Total Daily</td>
          <td style="text-align:right; padding:10px;" id="pdfDailyTotal">₱0.00</td>
          <td style="text-align:right; padding:10px;" id="pdfDailyTransTotal">0</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div style="margin-bottom:30px;">
    <div style="font-size:16px; font-weight:700; color:var(--accent); margin-bottom:15px; border-left:4px solid var(--accent); padding-left:10px;">Monthly Sales Income</div>
    <table style="width:100%; border-collapse:collapse;">
      <thead>
        <tr style="background:var(--accent); color:white;">
          <th style="padding:10px; text-align:left; font-weight:700; font-size:12px;">Month</th>
          <th style="padding:10px; text-align:right; font-weight:700; font-size:12px;">Revenue (₱)</th>
          <th style="padding:10px; text-align:right; font-weight:700; font-size:12px;">Transactions</th>
        </tr>
      </thead>
      <tbody id="pdfMonthlyTable"></tbody>
      <tfoot>
        <tr style="background:var(--accent); color:white; font-weight:700;">
          <td style="padding:10px;">Total Monthly</td>
          <td style="text-align:right; padding:10px;" id="pdfMonthlyTotal">₱0.00</td>
          <td style="text-align:right; padding:10px;" id="pdfMonthlyTransTotal">0</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div style="margin-bottom:30px;">
    <div style="font-size:16px; font-weight:700; color:var(--accent); margin-bottom:15px; border-left:4px solid var(--accent); padding-left:10px;">Sales Analytics Chart</div>
    <canvas id="pdfChart" width="1000" height="300" style="margin:20px 0;"></canvas>
  </div>
</div>

</body>
</html>