<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Food Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
      background-color: #fdfdfd;
      font-family: 'Segoe UI', sans-serif;
    }

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

    .tab-btn {
      background-color: #cde4cd;
      color: #27692A;
      border: none;
      padding: 10px 30px;
      border-radius: 0.5rem 0.5rem 0 0;
      font-weight: 600;
      transition: background-color 0.3s, color 0.3s;
    }

    .tab-btn.active {
      background-color: #27692A;
      color: #ffffff !important;
    }

    .form-control,
    .form-select {
      border-color: #a2c9a2;
      background-color: #f5fbf5;
      color: #27692A;
    }

    .form-control:focus,
    .form-select:focus,
    textarea:focus {
      border-color: #1e5221;
      box-shadow: 0 0 0 0.25rem rgba(39, 105, 42, 0.25);
      background-color: #f6fff6;
      color: #27692A;
    }

    .btn-add {
      background-color: #27692A;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 20px;
    }

    .green-table {
      background-color: #f5fbf5;
      border: 1px solid #a2c9a2;
    }

    .green-table thead {
      background-color: #27692A;
      color: white;
    }

    .green-table th {
      border: none;
      padding: 15px;
    }

    .green-table td {
      border: 1px solid #a2c9a2;
      padding: 12px;
    }

    .search-bar {
      position: relative;
    }

    .search-bar input {
      padding-right: 40px;
    }

    .search-bar i {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #27692A;
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
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
  <a class="navbar-brand d-flex align-items-center" href="feed.php">
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
        <a class="nav-link" href="Rinstructions.php">REQUEST</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
          TRACKER
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item active" href="food.php">Food Tracker</a></li>
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
        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle me-1"></i>
          <?= $_SESSION["first_name"] ?? "User" ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><h6 class="dropdown-header">
            <i class="bi bi-person-circle me-2"></i>
            <?= $_SESSION["first_name"] ?> <?= $_SESSION["last_name"] ?? "" ?>
          </h6></li>
          <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
          <li><a class="dropdown-item" href="Eprofile.php"><i class="bi bi-pencil-square me-2"></i>Edit Profile</a></li>
          <li><a class="dropdown-item" href="logout.html"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>

<!-- Content -->
<div class="container py-4">
  <h3 class="text-success fw-bold">FOOD TRACKER</h3>

  <!-- Tabs -->
  <div class="d-flex mb-3">
    <button class="tab-btn active me-2" onclick="showTab('track')">Track</button>
    <button class="tab-btn" onclick="showTab('monitor')">Monitor</button>
  </div>

  <!-- Track Tab -->
  <div id="track-tab" class="tab-content">
    <!-- Form -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <label for="foodName" class="form-label text-success">Food Name</label>
        <input type="text" class="form-control" id="foodName" placeholder="Enter Food Name">
      </div>
      <div class="col-md-3">
        <label for="typeOfFood" class="form-label text-success">Type of Food</label>
        <select class="form-select" id="typeOfFood">
          <option selected>Select Type of Food</option>
          <option>Sardines</option>
          <option>Butter</option>
          <option>Milk</option>
          <option>Chicken</option>
          <option>Bread</option>
        </select>
      </div>
      <div class="col-md-2">
        <label for="amount" class="form-label text-success">Amount</label>
        <input type="number" class="form-control" id="amount" placeholder="Enter Amount">
      </div>
      <div class="col-md-3">
        <label for="expirationDate" class="form-label text-success">Expiration Date</label>
        <input type="date" class="form-control" id="expirationDate">
      </div>
      <div class="col-md-1 d-grid align-items-end">
        <label class="form-label invisible">Add</label>
        <button class="btn-add">ADD</button>
      </div>
    </div>

    <!-- Search Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="search-bar w-25">
        <input type="text" placeholder="Search" />
        <i class="bi bi-search ms-2"></i>
      </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table class="table green-table text-center">
        <thead>
          <tr>
            <th>Food Name</th>
            <th>Type of Food</th>
            <th>Amount</th>
            <th>Expiration Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Sardines</td>
            <td>Sardines</td>
            <td>5 cans</td>
            <td>2024-12-31</td>
            <td><span class="badge bg-success">Good</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1">Edit</button>
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </td>
          </tr>
          <tr>
            <td>Butter</td>
            <td>Butter</td>
            <td>2 packs</td>
            <td>2024-11-15</td>
            <td><span class="badge bg-warning">Expiring Soon</span></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1">Edit</button>
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Monitor Tab -->
  <div id="monitor-tab" class="tab-content" style="display: none;">
    <div class="row">
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title text-success">Food Inventory Summary</h5>
            <p class="card-text">Total Items: 7</p>
            <p class="card-text">Expiring Soon: 2</p>
            <p class="card-text">Expired: 0</p>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title text-success">Recent Activity</h5>
            <ul class="list-unstyled">
              <li>Added Sardines - 2 hours ago</li>
              <li>Updated Butter quantity - 1 day ago</li>
              <li>Removed expired Milk - 3 days ago</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showTab(tabName) {
  // Hide all tab contents
  const tabContents = document.querySelectorAll('.tab-content');
  tabContents.forEach(content => {
    content.style.display = 'none';
  });

  // Remove active class from all tab buttons
  const tabButtons = document.querySelectorAll('.tab-btn');
  tabButtons.forEach(button => {
    button.classList.remove('active');
  });

  // Show selected tab content
  document.getElementById(tabName + '-tab').style.display = 'block';

  // Add active class to clicked button
  event.target.classList.add('active');
}

// Notification functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Notification system initialized');
    
    // Mark notification as read when clicked and navigate to post
    const notificationItems = document.querySelectorAll('.notification-item');
    console.log('Found notification items:', notificationItems.length);
    
    notificationItems.forEach(function(item, index) {
        console.log('Adding click listener to notification item', index);
        
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Notification clicked:', this.getAttribute('data-notification-id'));
            
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
                console.log('Mark as read response:', data);
                if (data.success) {
                    // Remove unread styling
                    this.classList.remove('unread');
                    
                    // Update notification count
                    updateNotificationCount();
                    
                    // Navigate to the post
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