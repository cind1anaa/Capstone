<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create requests table if it doesn't exist
$create_requests_table = "CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    waste_type VARCHAR(50) NOT NULL,
    waste_quantity VARCHAR(50) NOT NULL,
    urgency_level VARCHAR(20) NOT NULL,
    location TEXT NOT NULL,
    landmarks TEXT,
    request_date DATE NOT NULL,
    request_time TIME NOT NULL,
    collection_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    estimated_cost DECIMAL(10,2),
    bag_size VARCHAR(10) NOT NULL,
    collection_preference VARCHAR(50) NOT NULL,
    special_handling TEXT,
    access_instructions TEXT,
    contact_person VARCHAR(255),
    payment_proof VARCHAR(255),
    segregation_proof VARCHAR(255),
    notes TEXT,
    status ENUM('pending', 'approved', 'rejected', 'collected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if (!$conn->query($create_requests_table)) {
    echo "Error creating table: " . $conn->error;
}

// Fetch notifications for the current user
$notifications_query = "SELECT n.*, u.first_name, u.last_name 
                       FROM notifications n 
                       INNER JOIN users u ON n.sender_id = u.id 
                       WHERE n.recipient_id = ? 
                       ORDER BY n.created_at DESC 
                       LIMIT 10";

$notif_stmt = $conn->prepare($notifications_query);
$notif_stmt->bind_param("i", $_SESSION["user_id"]);
$notif_stmt->execute();
$notifications_result = $notif_stmt->get_result();

// Count unread notifications
$unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE recipient_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $_SESSION["user_id"]);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_data = $unread_result->fetch_assoc();
$unread_count = $unread_data['unread_count'];

// Fetch user's request history
$user_id = $_SESSION["user_id"];
$requests_query = "SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC";
$requests_stmt = $conn->prepare($requests_query);
$requests_stmt->bind_param("i", $user_id);
$requests_stmt->execute();
$requests_result = $requests_stmt->get_result();

// Get user info for display
$user_query = "SELECT first_name, last_name, avatar FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $_SESSION["user_id"]);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
$user_avatar = $user_data['avatar'] ? $user_data['avatar'] : 'images/user.png';

// Fetch user's requests
$stmt = $conn->prepare("SELECT * FROM requests WHERE user_id = ? ORDER BY request_date DESC");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Collection Request History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #fdfdfd;
      font-family: 'Segoe UI', sans-serif;
    }

    .text-deepgreen {
      color: #27692A;
    }

    /* Navbar styles */
    .custom-navbar {
      background-color: #27692A;
    }

    .custom-navbar .nav-link,
    .custom-navbar .navbar-brand span {
      color: #ffffff;
    }

    .custom-navbar .nav-link.active,
    .custom-navbar .nav-link:hover {
      color: #f1f1f1;
    }

    .custom-navbar .nav-link.active {
      background-color: white;
      color: #27692A !important;
      border-radius: 5px;
      padding: 6px 12px;
    }

    .navbar-brand span {
      font-weight: bold;
    }

    .dropdown-menu {
      background-color: #27692A;
      border: none;
      padding: 0.5rem 0;
      min-width: 180px;
    }

    .dropdown-menu .dropdown-header {
      color: #ffffff;
      font-weight: bold;
      padding: 0.5rem 1rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .dropdown-menu .dropdown-item {
      color: #ffffff;
      padding: 0.5rem 1rem;
    }

    .dropdown-menu .dropdown-item:hover {
      background-color: #1d4f1f;
      color: #ffffff;
    }

    .profile-icon {
      width: 32px;
      height: 32px;
      object-fit: cover;
    }

    @media (max-width: 767px) {
      .navbar-collapse {
        background-color: #27692A;
        padding: 1rem;
      }
    }

    /* Page styles */
    .custom-tabs .nav-link {
      font-weight: 500;
      color: #27692A;
      border: none;
    }

    .custom-tabs .nav-link.active {
      background-color: #27692A;
      color: #fff !important;
      border-radius: 0.5rem 0.5rem 0 0;
    }

         .green-table thead {
       background-color: #cde4cd;
       color: #27692A;
     }

     .green-table tbody tr {
       background-color: #e8f5e8;
       color: #27692A;
     }

     .green-table tbody td {
       color: #27692A !important;
     }

     .green-table tbody td small {
       color: #27692A !important;
     }

     .green-table tbody td span {
       color: #27692A !important;
     }

     .green-table tbody td,
     .green-table tbody td * {
       color: #27692A !important;
     }

     .green-table .text-muted {
       color: #27692A !important;
     }

     .green-table .text-center {
       color: #27692A !important;
     }

     .green-table .badge {
       color: #27692A !important;
     }

     .green-table td,
     .green-table td * {
       color: #27692A !important;
     }

     .green-table * {
       color: #27692A !important;
     }

    .green-table th,
    .green-table td {
      border: 1px solid #a2c9a2;
    }

    .btn-proof {
      background-color: #27692A;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 4px;
      transition: background-color 0.3s;
    }

    .btn-proof:hover {
      background-color: #1d4f1f;
    }

    .btn-proof i {
      font-size: 1rem;
    }

    .btn-export {
      background-color: #27692A;
      color: #fff;
      padding: 8px 25px;
      border-radius: 20px;
      border: none;
    }

    .btn-export:hover {
      background-color: #1e5221;
    }

    .search-bar {
      background-color: #cde4cd;
      border-radius: 50px;
      padding: 0.4rem 1rem;
    }

    .search-bar input {
      border: none;
      background: transparent;
      width: 100%;
    }

    .search-bar input:focus {
      outline: none;
      box-shadow: none;
    }

    .table-container {
      overflow-x: auto;
    }

    /* Notification Dropdown Styles */
    .dropdown-menu.notifications {
      background-color: #ffffff;
      border: 1px solid #ddd;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      max-height: 400px;
      overflow-y: auto;
    }

    .notification-item {
      padding: 8px 12px;
      border-bottom: 1px solid #f0f0f0;
      cursor: pointer;
      transition: background-color 0.2s ease;
      user-select: none;
      position: relative;
      z-index: 10;
    }

    .notification-item:hover {
      background-color: #f8f9fa;
    }

    .notification-item.unread {
      background-color: #e3f2fd;
      border-left: 3px solid #2196f3;
    }

    .notification-item.unread:hover {
      background-color: #bbdefb;
    }

    .notification-item:active {
      background-color: #e8f5e8;
    }

    .notification-item i {
      color: #27692A;
      margin-right: 8px;
      margin-top: 2px;
    }

    /* Status badges */
    .badge-pending {
      background-color: #ffc107;
      color: #000;
    }

    .badge-approved {
      background-color: #28a745;
      color: #fff;
    }

    .badge-rejected {
      background-color: #dc3545;
      color: #fff;
    }

    .badge-collected {
      background-color: #17a2b8;
      color: #fff;
    }

    /* Urgency level badges */
    .badge-low {
      background-color: #28a745;
      color: #fff;
    }

    .badge-medium {
      background-color: #ffc107;
      color: #000;
    }

    .badge-high {
      background-color: #fd7e14;
      color: #fff;
    }

    .badge-emergency {
      background-color: #dc3545;
      color: #fff;
    }

    .modal-header {
      background-color: #27692A;
      color: white;
    }

    .modal-title {
      color: white !important;
    }

        .btn-view {
    background: none;
    border: none;
    color: #27692A;
    cursor: pointer;
    padding: 5px;
    transition: color 0.3s;
}

.btn-view:hover {
    color: #1a4c1d;
}
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
  <a class="navbar-brand d-flex align-items-center" href="#">
    <img src="images/logo.png" alt="Logo" height="40" class="me-2" />
    <span>GROUND ZERO</span>
  </a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
    <ul class="navbar-nav align-items-center gap-2">
      <li class="nav-item">
        <a class="nav-link" href="feed.php">COMMUNITY</a>
      </li>
      <li class="nav-item">
        <a class="nav-link active" href="Rinstructions.php">REQUEST</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          TRACKER
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="food.php">Food Tracker</a></li>
          <li><a class="dropdown-item" href="garbage.php">Garbage Tracker</a></li>
        </ul>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown">
          <i class="bi bi-bell-fill"></i>
          <?php if ($unread_count > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $unread_count ?>
          </span>
          <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end notifications">
          <li class="dropdown-header"><i class="bi bi-bell-fill"></i> Notifications</li>
          <li><hr class="dropdown-divider"></li>
          <?php if ($notifications_result->num_rows > 0): ?>
            <?php while ($notification = $notifications_result->fetch_assoc()): ?>
              <li class="d-flex align-items-start notification-item <?= $notification['is_read'] == 0 ? 'unread' : '' ?>" 
                  data-notification-id="<?= $notification['id'] ?>"
                  data-post-id="<?= $notification['post_id'] ?>">
                <i class="bi bi-<?= $notification['type'] == 'like' ? 'heart-fill' : 'chat-fill' ?>"></i>
                <div>
                  <small><strong><?= htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']) ?></strong> 
                  <?= htmlspecialchars($notification['content']) ?>
                  <br><span class="text-muted"><?= date('M j, g:i A', strtotime($notification['created_at'])) ?></span></small>
                </div>
              </li>
            <?php endwhile; ?>
          <?php else: ?>
            <li><small class="text-muted">No notifications yet</small></li>
          <?php endif; ?>
        </ul>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="<?= htmlspecialchars($user_avatar) ?>" alt="User" class="rounded-circle bg-white p-1 profile-icon" />
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><h6 class="dropdown-header"><img src="<?= htmlspecialchars($user_avatar) ?>" class="rounded-circle me-2" width="24" height="24"> <?= htmlspecialchars($user_name) ?></h6></li>
          <li><a class="dropdown-item" href="Eprofile.php"><i class="bi bi-person-circle me-2"></i>Edit Profile</a></li>
          <li><a class="dropdown-item" href="logout.html"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>

<!-- Main Content -->
<div class="container my-5">
  <h2 class="text-deepgreen fw-bold">COLLECTION REQUEST</h2>

  <!-- Tabs -->
  <ul class="nav nav-tabs my-4 custom-tabs">
    <li class="nav-item">
      <a class="nav-link" href="Rinstructions.php">Instructions</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="request.php">Request</a>
    </li>
    <li class="nav-item">
      <a class="nav-link active" href="Rhistory.php">History</a>
    </li>
  </ul>

  <!-- Search Bar -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="search-bar d-flex align-items-center w-50">
      <input type="text" class="form-control" id="searchInput" placeholder="Search requests...">
      <i class="bi bi-arrow-down-up ms-2"></i>
    </div>
  </div>

  <!-- Table -->
  <div class="table-container">
         <table class="table table-bordered align-middle green-table" id="requestsTable">
       <thead>
         <tr>
           <th>Name</th>
           <th>Contact Number</th>
           <th>Type of Waste</th>
           <th>Quantity</th>
           <th>Complete Address</th>
           <th>Request Date</th>
           <th>Collection Date</th>
           <th>Payment Method</th>
           <th>Collection Time</th>
           <th>Payment Proof</th>
           <th>Segregation Proof</th>
           <th>Additional Notes</th>
           <th>Status</th>
         </tr>
       </thead>
      <tbody>
        <?php if ($requests_result->num_rows > 0): ?>
          <?php while ($request = $requests_result->fetch_assoc()): ?>
                         <tr>
               <td><?= htmlspecialchars($request['name']) ?></td>
               <td><?= htmlspecialchars($request['contact_number'] ?? 'N/A') ?></td>
               <td><?= htmlspecialchars($request['waste_type']) ?></td>
               <td><?= htmlspecialchars($request['waste_quantity'] ?? 'N/A') ?></td>
               <td><?= htmlspecialchars($request['location']) ?></td>
               <td><?= date('m/d/Y', strtotime($request['request_date'])) ?></td>
               <td><?= date('m/d/Y', strtotime($request['collection_date'])) ?></td>
               <td><?= htmlspecialchars($request['payment_method']) ?></td>
               <td><?= htmlspecialchars($request['collection_preference'] ?? 'N/A') ?></td>
               <td>
                 <?php if ($request['payment_proof']): ?>
                     <button class="btn-view" onclick="viewProof('uploads/proofsP/<?= htmlspecialchars($request['payment_proof']) ?>', 'Payment')">
                         <i class="bi bi-eye-fill"></i>
                     </button>
                 <?php else: ?>
                   <span class="text-muted">No file</span>
                 <?php endif; ?>
               </td>
               <td>
                 <?php if ($request['segregation_proof']): ?>
                     <button class="btn-view" onclick="viewProof('uploads/proofsS/<?= htmlspecialchars($request['segregation_proof']) ?>', 'Segregation')">
                         <i class="bi bi-eye-fill"></i>
                     </button>
                 <?php else: ?>
                   <span class="text-muted">No file</span>
                 <?php endif; ?>
               </td>
               <td>
                 <small><?= htmlspecialchars($request['notes'] ?? 'No notes') ?></small>
               </td>
               <td>
                 <span class="badge badge-<?= $request['status'] ?>">
                   <?= ucfirst($request['status']) ?>
                 </span>
               </td>
             </tr>
          <?php endwhile; ?>
        <?php else: ?>
                     <tr>
             <td colspan="14" class="text-center text-deepgreen" style="color: #27692A !important;">No request history found</td>
           </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Export Button -->
  <div class="text-end mt-3">
    <button class="btn btn-export" onclick="exportHistory()">Export</button>
  </div>
</div>

<!-- Proof View Modal -->
<div class="modal fade" id="proofModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="proofModalTitle">Proof Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <img id="proofImage" src="" alt="Proof Document" class="img-fluid" style="max-height: 500px;">
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
  const searchTerm = this.value.toLowerCase();
  const table = document.getElementById('requestsTable');
  const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
  
  for (let row of rows) {
    const cells = row.getElementsByTagName('td');
    let found = false;
    
    for (let cell of cells) {
      if (cell.textContent.toLowerCase().includes(searchTerm)) {
        found = true;
        break;
      }
    }
    
    row.style.display = found ? '' : 'none';
  }
});

// Update the viewProof function in your script section
function viewProof(path, type) {
    const modal = new bootstrap.Modal(document.getElementById('proofModal'));
    document.getElementById('proofModalTitle').textContent = type + ' Proof';
    document.getElementById('proofImage').src = path;
    
    // Add error handling for image loading
    document.getElementById('proofImage').onerror = function() {
        this.src = 'images/error.png';
        alert('Failed to load image');
    };
    
    modal.show();
}

// Export functionality
function exportHistory() {
  const table = document.getElementById('requestsTable');
  const rows = Array.from(table.getElementsByTagName('tr'));
  
  let csvContent = "data:text/csv;charset=utf-8,";
  
  // Add headers
  const headers = [];
  const headerRow = rows[0];
  for (let cell of headerRow.getElementsByTagName('th')) {
    headers.push(cell.textContent);
  }
  csvContent += headers.join(',') + '\n';
  
  // Add data rows
  for (let i = 1; i < rows.length; i++) {
    const row = rows[i];
    const cells = row.getElementsByTagName('td');
    const rowData = [];
    
    for (let cell of cells) {
      // Clean the cell content (remove HTML tags and extra spaces)
      let cellText = cell.textContent.trim();
      // Escape commas and quotes for CSV
      if (cellText.includes(',') || cellText.includes('"')) {
        cellText = '"' + cellText.replace(/"/g, '""') + '"';
      }
      rowData.push(cellText);
    }
    
    csvContent += rowData.join(',') + '\n';
  }
  
  // Create download link
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement('a');
  link.setAttribute('href', encodedUri);
  link.setAttribute('download', 'request_history.csv');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Notification functionality
document.addEventListener('DOMContentLoaded', function() {
  // Mark notification as read when clicked and navigate to post
  const notificationItems = document.querySelectorAll('.notification-item');
  
  notificationItems.forEach(function(item) {
    item.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const notificationId = this.getAttribute('data-notification-id');
      const postId = this.getAttribute('data-post-id');
      
      // Mark as read via AJAX
      fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Remove unread styling
          this.classList.remove('unread');
          
          // Update notification count
          updateNotificationCount();
          
          // Navigate to the post (redirect to feed.php with post ID)
          if (postId) {
            // Close the notification dropdown
            const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('notifDropdown'));
            if (dropdown) {
              dropdown.hide();
            }
            
            // Redirect to feed.php to see the post
            window.location.href = 'feed.php?scroll_to_post=' + postId;
          }
        }
      })
      .catch(error => console.error('Error:', error));
    });
  });
});

function updateNotificationCount() {
  fetch('get_notification_count.php')
  .then(response => response.json())
  .then(data => {
    const badge = document.querySelector('#notifDropdown .badge');
    if (data.unread_count > 0) {
      if (badge) {
        badge.textContent = data.unread_count;
      } else {
        // Create badge if it doesn't exist
        const newBadge = document.createElement('span');
        newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
        newBadge.textContent = data.unread_count;
        document.querySelector('#notifDropdown').appendChild(newBadge);
      }
    } else {
      // Remove badge if count is 0
      if (badge) {
        badge.remove();
      }
    }
  })
  .catch(error => console.error('Error:', error));
}
</script>
</body>
</html>