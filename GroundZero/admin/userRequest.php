<?php
// DB Connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check uploads directory
$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
    error_log("Uploads directory does not exist: $uploadsDir");
} elseif (!is_writable($uploadsDir)) {
    error_log("Uploads directory is not writable: $uploadsDir");
}

// Add this after your database connection
error_log("Uploads directory: " . $uploadsDir);
error_log("Directory exists: " . (is_dir($uploadsDir) ? 'yes' : 'no'));
error_log("Directory writable: " . (is_writable($uploadsDir) ? 'yes' : 'no'));

// Add after database connection
$uploadsDir = __DIR__ . '/../uploads';
error_log("Checking uploads directory:");
error_log("Path: " . $uploadsDir);
error_log("Exists: " . (is_dir($uploadsDir) ? 'yes' : 'no'));
error_log("Readable: " . (is_readable($uploadsDir) ? 'yes' : 'no'));
error_log("Writable: " . (is_writable($uploadsDir) ? 'yes' : 'no'));

// List files in uploads directory
if (is_dir($uploadsDir)) {
    $files = scandir($uploadsDir);
    error_log("Files in uploads directory: " . print_r($files, true));
}

// Add these requires at the top of the file after the DB connection
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Handle accept/reject
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["user_id"])) {
    $user_id = $_POST["user_id"];
    $action = $_POST["action"];
    $status = $action === "accept" ? "accepted" : "rejected";

    // Get user info
    $stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($email, $first_name);
    $stmt->fetch();
    $stmt->close();

    // Configure PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'groundzer0.use@gmail.com';  
        $mail->Password   = 'yxsh tpqt havu frle';        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Add debug mode temporarily
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;  // Set to DEBUG_SERVER for troubleshooting

        // Recipients
        $mail->setFrom('groundzer0.use@gmail.com', 'Ground Zero Admin');
        $mail->addAddress($email, $first_name);
        $mail->addReplyTo('groundzer0.use@gmail.com', 'Ground Zero Admin');

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Account " . ucfirst($status) . " - Ground Zero";
        
        if ($action === "accept") {
            $mail->Body = "
                <p>Dear {$first_name},</p>
                <p>Congratulations! Your account has been approved by the administrator.</p>
                <p>You can now login to your Ground Zero account using your registered email and password.</p>
                <p>Login here: <a href='http://localhost/GroundZero/login.php'>Click to Login</a></p>
                <p>If you have any questions, please don't hesitate to contact us.</p>
                <p>Best regards,<br>Ground Zero Admin Team</p>
            ";
        } else {
            $mail->Body = "
                <p>Dear {$first_name},</p>
                <p>We regret to inform you that your account registration has been rejected and your account has been removed from our system.</p>
                <p>This may be due to incomplete or invalid documentation.</p>
                <p>You may try registering again with complete and valid documents.</p>
                <p>Best regards,<br>Ground Zero Admin Team</p>
            ";
        }

        $mail->send();
        error_log("Email sent successfully to: $email");
        echo "<script>alert('Email sent successfully to: $email');</script>";
    } catch (Exception $e) {
        error_log("Failed to send email. Mailer Error: {$mail->ErrorInfo}");
        echo "<script>alert('Failed to send email: {$mail->ErrorInfo}');</script>";
    }

    // Update status or delete user based on action (after sending email)
    if ($action === "accept") {
        // Update status to accepted
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Delete user from database when rejected
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: userRequest.php");
    exit();
}

// Fetch pending users
$users = $conn->query("SELECT id, first_name, last_name, barangay, establishment_type, 
                      establishment_name, email, phone, 
                      COALESCE(proof_document, proof_path) as proof,
                      status 
                      FROM users 
                      WHERE status = 'pending'");

if (!$users) {
    error_log("Query error: " . $conn->error);
    die("Error fetching users");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Request - Ground Zero</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #f2f5ef;
    }

    .sidebar {
      position: fixed;
      left: -250px;
      top: 0;
      height: 100%;
      width: 250px;
      background-color: #cfe2c3;
      padding: 20px;
      transition: left 0.3s ease;
      z-index: 1050;
    }

    .sidebar.active {
      left: 0;
    }

    .sidebar .logo img {
      height: 50px;
      margin-bottom: 15px;
    }

    .sidebar h4 {
      color: #27692A;
      margin-top: 10px;
    }

    .sidebar ul {
      list-style: none;
      padding: 0;
      margin-top: 20px;
    }

    .sidebar ul li {
      margin: 15px 0;
      font-size: 16px;
    }

    .sidebar ul li i {
      margin-right: 10px;
    }

    .sidebar ul li a {
      color: #000;
      text-decoration: none;
      display: flex;
      align-items: center;
    }

    .topbar {
      background-color: #27692A;
      color: #fff;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1040;
    }

    .toggle-btn {
      font-size: 24px;
      cursor: pointer;
      color: #fff;
    }

    .profile-icon {
      font-size: 24px;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
      background-color: rgba(0, 0, 0, 0.4);
      z-index: 1000;
      display: none;
    }

    .overlay.active {
      display: block;
    }

   .dashboard-container {
      padding: 20px;
      margin-left: 0;
      transition: margin-left 0.3s;
    }
    .sidebar.active ~ .dashboard-container {
      margin-left: 250px;
    }

    .tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }

    .tabs button {
      padding: 10px 20px;
      border: none;
      background-color: #cfe2c3;
      color: #27692A;
      font-weight: bold;
      border-radius: 5px;
    }

    .tabs button.active {
      background-color: #27692A;
      color: #fff;
    }

    .search-bar {
      background-color: #cfe2c3;
      padding: 8px 15px;
      border-radius: 25px;
      display: flex;
      align-items: center;
      width: 300px;
      margin-bottom: 20px;
    }

    .search-bar input {
      border: none;
      background: transparent;
      outline: none;
      flex: 1;
      font-size: 14px;
    }

    .table-container {
      background-color: #fff;
      border-radius: 10px;
      padding: 15px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .table th, .table td {
      text-align: center;
      vertical-align: middle;
    }

    .btn-proof {
      background-color: #dc3545;
      color: #fff;
      font-size: 14px;
      border-radius: 20px;
      padding: 4px 10px;
      border: none;
    }

    .btn-accept {
      background-color: #28a745;
      color: white;
      font-size: 14px;
      border-radius: 20px;
      padding: 4px 10px;
      margin-right: 5px;
      border: none;
    }

    .btn-delete {
      background-color: #e53935;
      color: white;
      font-size: 14px;
      border-radius: 20px;
      padding: 4px 10px;
      border: none;
    }
    .sidebar ul li a.active {
    background-color: #27692A;
    color: white;
    border-radius: 8px;
    padding: 8px 10px;
  }

  .sidebar ul li a.active i {
  color: white;
}
 .logout {
  position: absolute;
  bottom: 10px;
  width: 50%;
  text-align: center;
}

.logout a {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 5px 0;
  color: #000;
  text-decoration: none;
  font-weight: bold;
}

.logout a:hover {
  background-color: #b4d7a9;
  border-radius: 4px;
}

.logout i {
  margin-right: 4px;
}
.modal-content {
        background-color: #fff;
        border-radius: 10px;
    }

    .modal-header {
        background-color: #27692A;
        color: white;
        border-radius: 10px 10px 0 0;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    #proofImage {
        max-width: 100%;
        height: auto;
    }
    .btn-proof {
    background-color: #27692A;
    color: white;
    font-size: 14px;
    border-radius: 20px;
    padding: 4px 12px;
    border: none;
    transition: all 0.3s ease;
}

.btn-proof:hover:not([disabled]) {
    background-color: #1e5420;
    color: white;
    cursor: pointer;
}

.btn-proof[disabled] {
    background-color: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
}

.btn-proof i {
    margin-right: 4px;
}

.modal-dialog.modal-lg {
    max-width: 800px;
}

#proofImage {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="logo text-center">
      <img src="images/logo.png" alt="Logo" />
      <h4>Ground Zero</h4>
    </div>
    <ul>
      <li><a href="dashboard.html"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
      <li><a href="feed.php"><i class="bi bi-envelope-fill"></i> News Feed</a></li>
      <li><a href="user.html" class="active"><i class="bi bi-people-fill"></i> User Management</a></li>
      <li><a href="monitoring.html"><i class="bi bi-recycle"></i> Waste Monitoring</a></li>
      <li><a href="createSched.html"><i class="bi bi-truck"></i> Collection Schedule</a></li>
      <li><a href="announcement.html"><i class="bi bi-megaphone"></i> Announcements</a></li>
      <li><a href="waste.html"><i class="bi bi-bar-chart-fill"></i> Waste Reports</a></li>
      <li><a href="collection.html"><i class="bi bi-trash3-fill"></i> Collection Request</a></li>
    </ul>
        <div class="logout">
        <a href="logout.html"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>

  <!-- Overlay -->
  <div class="overlay" id="overlay"></div>

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <span class="toggle-btn" id="toggleBtn"><i class="bi bi-list"></i></span>
      <h5 class="m-0">User Request</h5>
    </div>
    <a href="admin_profile.php">
  <i class="bi bi-person-circle profile-icon" style="color: white;"></i>
</a>
  </div>

  <!-- Main Content -->
  <div class="dashboard-container container-fluid" id="dashboardContainer">
    <!-- Tabs -->
    <div class="tabs">
      <a href="user.php"><button>All Users</button></a>
      <button class="active">User Requests</button>
    </div>

    <!-- Search -->
    <div class="d-flex align-items-center gap-2 mb-3">
      <div class="search-bar">
        <i class="bi bi-search me-2"></i>
        <input type="text" id="searchInput" placeholder="Search users by any field..." />
      </div>
      <button class="btn btn-outline-success btn-sm" id="sortToggle" data-sort-col="1" title="Sort by First Name">
        <i class="bi bi-arrow-down-up"></i>
      </button>
    </div>

    <!-- Table -->
    <div class="table-container">
      <table class="table table-bordered">
        <thead class="table-success">
          <tr>
            <th>ID</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Barangay</th>
            <th>Establishment Type</th>
            <th>Establishment Name</th>
            <th>Email</th>
            <th>Phone Number</th>
            <th>Proof</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($row = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['id']); ?></td>
                <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                <td><?php echo htmlspecialchars($row['barangay']); ?></td>
                <td><?php echo htmlspecialchars($row['establishment_type']); ?></td>
                <td><?php echo htmlspecialchars($row['establishment_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td>
                    <?php 
                        // Debug logging
                        error_log("User ID: " . $row['id'] . " Proof: " . ($row['proof'] ?? 'none'));
                        
                        // Fix the path construction - remove the extra 'uploads/' prefix
                        $proofPath = '';
                        $hasProof = false;
                        if (!empty($row['proof'])) {
                            // If the path already contains 'uploads/', use it as is
                            if (strpos($row['proof'], 'uploads/') === 0) {
                                $proofPath = '../' . $row['proof'];
                            } else {
                                $proofPath = '../uploads/' . $row['proof'];
                            }
                            $hasProof = file_exists($proofPath);
                        }
                        
                        // More debug logging
                        error_log("Proof path: " . $proofPath);
                        error_log("File exists: " . ($hasProof ? 'yes' : 'no'));
                    ?>
                    <button class="btn-proof" <?php echo $hasProof ? '' : 'disabled'; ?> 
                            onclick="viewProof('<?php echo htmlspecialchars($row['proof'] ?? ''); ?>')">
                        <i class="bi bi-file-earmark"></i> 
                        <?php echo $hasProof ? 'View' : 'No proof'; ?>
                    </button>
                </td>
                <td>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="action" value="accept" class="btn-accept">ACCEPT</button>
                        <button type="submit" name="action" value="reject" class="btn-delete">REJECT</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sidebar Toggle Script -->
  <script>
    const toggleBtn = document.getElementById('toggleBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const dashboardContainer = document.querySelector('.dashboard-container');

    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
      dashboardContainer.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
      dashboardContainer.classList.remove('active');
    });
    // Search functionality
    (function(){
      const searchInput = document.getElementById('searchInput');
      if (!searchInput) return;
      const tableBody = document.querySelector('tbody');
      searchInput.addEventListener('input', function(){
        const term = this.value.toLowerCase();
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(term) ? '' : 'none';
        });
      });
    })();

    // Sort functionality
    (function(){
      const sortToggle = document.getElementById('sortToggle');
      if (!sortToggle) return;
      let asc = true;
      sortToggle.addEventListener('click', function(){
        const col = parseInt(this.getAttribute('data-sort-col') || '0', 10);
        const body = document.querySelector('tbody');
        const rows = Array.from(body.querySelectorAll('tr'));
        rows.sort((a,b)=>{
          const aText = (a.querySelectorAll('td')[col]?.textContent || '').trim();
          const bText = (b.querySelectorAll('td')[col]?.textContent || '').trim();
          const cmp = aText.localeCompare(bText, undefined, { numeric:true, sensitivity:'base' });
          return asc ? cmp : -cmp;
        });
        rows.forEach(r=>body.appendChild(r));
        asc = !asc;
      });
    })();
  </script>

  <!-- Proof Modal -->
    <div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofModalLabel">User Proof Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="proofImage" src="" alt="Proof Document" class="img-fluid">
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewProof(proofPath) {
    if (!proofPath) {
        alert('No proof document available');
        return;
    }
    
    const proofModal = new bootstrap.Modal(document.getElementById('proofModal'));
    const proofImage = document.getElementById('proofImage');
    
    // Clean the path and ensure proper directory structure
    const cleanPath = proofPath.replace(/\\/g, '/');
    let fullPath;
    if (cleanPath.startsWith('uploads/')) {
        fullPath = '../' + cleanPath;
    } else {
        fullPath = '../uploads/' + cleanPath;
    }
    
    console.log('Attempting to load proof:', fullPath); // Debug log
    proofImage.src = fullPath;
    
    proofImage.onerror = function() {
        console.error('Failed to load image:', fullPath); // Debug log
        alert('Error loading proof document. Please check if the file exists.');
        proofModal.hide();
    };
    
    proofModal.show();
}
    </script>
    </body>
</html>
