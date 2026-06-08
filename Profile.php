<?php
require_once 'auth.php';
// Check if user is logged in
if (!isset($_SESSION['username'])) {
  header("Location: LOGIN.php");
  exit();
}

$host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port = 19494;
$dbname = "login_credentials";
$username_db = "avnadmin";
$password_db = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$conn = mysqli_connect($host, $username_db, $password_db, $dbname, $port);

$host = "mysql-1d69cd83-umak-e978.i.aivencloud.com";
$port = 19494;
$dbname = "main";
$username_db = "avnadmin";
$password_db = "AVNS_vZ6RVEWU-0a2Jwp-Zzz";

$conn2 = mysqli_connect($host, $username_db, $password_db, $dbname, $port);


if (!$conn) {
  die("Connection failed: " . mysqli_connect_error());
}


// Get logged-in user's data
$username = $_SESSION['username'];
$query = "SELECT email, username, roles, datejoined FROM employee_credentials WHERE username = ? OR email = ?";
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

$email = $user['email'];
$roles = $user['roles'];
$displayName = ucfirst($username);
$dateJoined = date('M d, Y', strtotime($user['datejoined']));
$firstName = ucfirst($username);


//FOR ACTIVITIES

$activity_query = "SELECT type, action, detail, created_at FROM activity_logs WHERE email = ? ORDER BY created_at DESC LIMIT 20";
$activity_stmt = $conn2->prepare($activity_query);
$activity_stmt->bind_param("s", $email);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();

$recent_activities = [];
while ($row = $activity_result->fetch_assoc()) {
  $recent_activities[] = $row;
}
$activity_json = json_encode($recent_activities);
$activity_stmt->close();




//UPDATE PASSWORD HEREEEEE
if ($_SERVER["REQUEST_METHOD"] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_password') {
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $username = $_SESSION['username'];

    $query = "SELECT password FROM employee_credentials WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || $user['password'] !== $currentPassword) {
      echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
      exit;
    }

    $updateQuery = "UPDATE employee_credentials SET password = ? WHERE username = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ss", $newPassword, $username);
    $result = $updateStmt->execute();
    $updateStmt->close();

    echo json_encode(['success' => $result]);
    exit;
  }
}

?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FiveSix Legazpi Cafe — Profile</title>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link href="Profile.css" rel="stylesheet" type="text/css" />
</head>

<body>
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="avatar">👤</div>
    <nav class="nav-links">

    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a class="nav-link" href="Merchandise.php"><span class="nav-icon">🖥</span> Dashboard</a>
        <a class="nav-link" href="Stocks.php"><span class="nav-icon">📦</span> Stocks</a>
      <?php endif; ?>
      
      <a class="nav-link" href="Cashier.php"><span class="nav-icon"><img src="IMAGES/P9.png" style="width:22px;height:22px;"></span> Cashier</a>
      <a class="nav-link" href="OrderingSystem.php"><span class="nav-icon">📋</span> Orders</a>
      <a class="nav-link active" href="Profile.php"><span class="nav-icon">👤</span> Profile</a>
    </nav>
    <div class="sidebar-spacer"></div>
    <a class="logout-btn" href="LOGIN.php">Log out ➜</a>
  </aside>

  <!-- CONTENT -->
  <div class="content">
    <div class="topbar">
      <div class="brand">
        <div class="brand-icon">☕</div>
        <span class="brand-name">FiveSix Legazpi Cafe</span>
      </div>
      <div class="topbar-right">
        <div class="topbar-date" id="liveDate"></div>
        <div class="topbar-time" id="liveTime"></div>
      </div>
    </div>

    <div class="main-scroll">
      <!-- LEFT: Profile Card -->
      <div class="profile-card">
        <div class="profile-avatar-big">
          <div class="avatar-edit-btn" title="Change photo">✎</div>
        </div>
        <div class="profile-fullname"><?php echo htmlspecialchars($displayName); ?></div>
        <div class="profile-role-badge"><?php echo htmlspecialchars(strtoupper($roles)); ?></div>
        <div class="profile-stat-row">
          <div class="profile-stat">
            <span class="profile-stat-label">Member since</span>
            <span class="profile-stat-value"><?php echo htmlspecialchars($dateJoined) ?></span>
          </div>
          <div class="profile-stat">
            <span class="profile-stat-label">Last login</span>
            <span class="profile-stat-value">Today</span>
          </div>
          <div class="profile-stat">
            <span class="profile-stat-label">Orders managed</span>
            <span class="profile-stat-value">248</span>
          </div>
          <div class="profile-stat">
            <span class="profile-stat-label">Status</span>
            <span class="profile-stat-value" style="color: var(--green)">● Active</span>
          </div>
        </div>
      </div>

      <!-- RIGHT: Forms -->
      <div class="forms-col">
        <!-- Personal Info -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-title">Personal Information</div>
            <span class="section-badge">Account Details</span>
          </div>
          <div class="form-grid">
            <div class="form-field">
              <label>First Name</label>
              <input type="text" id="firstName" value="<?php echo htmlspecialchars($firstName); ?>" />
            </div>
            <div class="form-field">
              <label>Last Name</label>
              <input type="text" id="lastName" value="" />
            </div>
            <div class="form-field">
              <label>Email Address</label>
              <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" />
            </div>
            <div class="form-field">
              <label>Contact Number</label>
              <input type="tel" id="contact" value="+63 912 345 6789" />
            </div>
            <div class="form-field full">
              <label>Role</label>
              <select id="role">
                <option selected><?php echo htmlspecialchars(strtoupper($roles)); ?></option>
                <option>ADMIN</option>
                <option>STAFF</option>
              </select>
            </div>
          </div>
          <div class="save-row">
            <button class="btn-cancel" onclick="resetPersonal()">
              Reset
            </button>
            <button class="btn-save" onclick="savePersonal()">
              Save Changes
            </button>
          </div>
        </div>

        <!-- Change Password -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-title">Change Password</div>
            <span class="section-badge">Security</span>
          </div>
          <div class="form-grid single">
            <div class="form-field">
              <label>Current Password</label>
              <input
                type="password"
                id="currentPw"
                placeholder="Enter current password..." />
            </div>
            <div class="form-field">
              <label>New Password</label>
              <input
                type="password"
                id="newPw"
                placeholder="Enter new password..."
                oninput="checkStrength()" />
              <div class="pw-strength-bar">
                <div class="pw-strength-fill" id="pwFill"></div>
              </div>
              <div class="pw-strength-label" id="pwLabel"></div>
            </div>
            <div class="form-field">
              <label>Confirm New Password</label>
              <input
                type="password"
                id="confirmPw"
                placeholder="Re-enter new password..." />
            </div>
          </div>
          <div class="save-row">
            <button class="btn-cancel" onclick="clearPassword()">
              Clear
            </button>
            <button class="btn-save" onclick="savePassword()">
              Update Password
            </button>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="section-card">
          <div class="section-header">
            <div class="section-title">Recent Activity</div>
            <span class="section-badge">Last 7 days</span>
          </div>
          <div class="activity-list" id="activityList">
            <!-- Will be populated by JavaScript -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    // Clock
    function updateClock() {
      const now = new Date();
      document.getElementById("liveDate").textContent =
        now.toLocaleDateString("en-US", {
          month: "short",
          day: "2-digit",
          year: "numeric",
        });
      document.getElementById("liveTime").textContent =
        now.toLocaleTimeString("en-US", {
          hour: "2-digit",
          minute: "2-digit",
          hour12: true,
        });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Password strength
    function checkStrength() {
      const val = document.getElementById("newPw").value;
      const fill = document.getElementById("pwFill");
      const lbl = document.getElementById("pwLabel");
      if (!val) {
        fill.style.width = "0";
        lbl.textContent = "";
        return;
      }
      let score = 0;
      if (val.length >= 8) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^a-zA-Z0-9]/.test(val)) score++;
      const levels = [{
          w: "25%",
          color: "#c0392b",
          label: "Weak"
        },
        {
          w: "50%",
          color: "#b87333",
          label: "Fair"
        },
        {
          w: "75%",
          color: "#8c7a20",
          label: "Good"
        },
        {
          w: "100%",
          color: "#3a7d54",
          label: "Strong"
        },
      ];
      const lvl = levels[score - 1] || levels[0];
      fill.style.width = lvl.w;
      fill.style.background = lvl.color;
      lbl.textContent = lvl.label;
      lbl.style.color = lvl.color;
    }

    // Save personal
    function savePersonal() {
      const first = document.getElementById("firstName").value.trim();
      const last = document.getElementById("lastName").value.trim();
      if (!first) {
        showToast("First name cannot be empty.");
        return;
      }
      const fullName = [first, last].filter(Boolean).join(" ");
      document.querySelector(".sidebar-name").textContent = fullName;
      document.querySelector(".profile-fullname").textContent = fullName;
      showToast("✓ Profile updated successfully.");
    }

    function resetPersonal() {
      document.getElementById("firstName").value = "<?php echo htmlspecialchars($firstName); ?>";
      document.getElementById("lastName").value = "";
      document.getElementById("email").value = "<?php echo htmlspecialchars($email); ?>";
      document.getElementById("contact").value = "+63 912 345 6789";
    }

    // Save password
    function savePassword() {
      const cur = document.getElementById("currentPw").value;
      const nw = document.getElementById("newPw").value;
      const conf = document.getElementById("confirmPw").value;
      if (!cur) {
        showToast("Enter current password.");
        return;
      }
      if (!nw) {
        showToast("Enter new password.");
        return;
      }
      if (nw !== conf) {
        showToast("Passwords do not match.");
        return;
      }
      if (nw.length < 6) {
        showToast("Password must be 6+ characters.");
        return;
      }

      fetch('Profile.php', {
          method: 'POST',
          body: new URLSearchParams({
            action: 'update_password',
            currentPassword: cur,
            newPassword: nw
          })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            clearPassword();
            showToast("✓ Password updated.");
          } else {
            showToast("❌ Current password incorrect.");
          }
        });
    }

    function clearPassword() {
      document.getElementById("currentPw").value = "";
      document.getElementById("newPw").value = "";
      document.getElementById("confirmPw").value = "";
      document.getElementById("pwFill").style.width = "0";
      document.getElementById("pwLabel").textContent = "";
    }

    // Toast
    let toastTimer;

    function showToast(msg) {
      const t = document.getElementById("toast");
      t.textContent = msg;
      t.classList.add("show");
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove("show"), 2600);
    }
    // Render recent activity from database
    function renderActivity() {
      const activities = <?= $activity_json ?>;
      const container = document.getElementById('activityList');

      if (!activities || activities.length === 0) {
        container.innerHTML = '<div style="color:var(--muted);padding:20px;text-align:center;">No activity yet</div>';
        return;
      }

      container.innerHTML = activities.map(activity => {
        const date = new Date(activity.created_at);
        const formattedTime = date.toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
          }) +
          ' · ' + date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
          });

        const iconMap = {
          'sale': '💰',
          'stock': '📦',
          'user': '👤'
        };
        const icon = iconMap[activity.type] || '📝';

        return `
            <div class="activity-item">
              <div class="activity-dot ${activity.type === 'sale' ? 'green' : ''}"></div>
              <div>
                <div class="activity-text">
                  ${icon} ${activity.action}
                </div>
                <div class="activity-time">${activity.detail}</div>
                <div class="activity-time" style="font-size:11px;color:var(--muted);">${formattedTime}</div>
              </div>
            </div>`;
      }).join('');
    }

    // Call when page loads
    renderActivity();
  </script>
</body>

</html>