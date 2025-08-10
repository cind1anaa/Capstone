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
  <meta charset="UTF-8">
  <title>Collection Request</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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

    .text-deepgreen {
      color: #27692A;
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

    .instructions-card {
      border-left: 5px solid #27692A;
      background-color: #ffffff;
    }

    .step-list li {
      line-height: 1.7;
      font-size: 16px;
    }

    .notice-box {
      background-color: #f3fdf5;
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

    @media (max-width: 767px) {
      .custom-tabs .nav-link {
        font-size: 14px;
        padding: 8px 12px;
      }
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
        <a class="nav-link active" href="Rinstructions.php">REQUEST</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
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

<!-- Main Content -->
<div class="container my-5">
  <h2 class="text-deepgreen fw-bold">COLLECTION REQUEST</h2>

  <!-- Tabs -->
  <ul class="nav nav-tabs my-4 custom-tabs">
    <li class="nav-item">
      <a class="nav-link active" href="Rinstructions.php">Instructions</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="request.php">Request</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="Rhistory.php">History</a>
    </li>
  </ul>

  <!-- Instructions Content -->
  <div class="row">
    <div class="col-lg-8">
          <div class="card instructions-card">
            <div class="card-body">
              <h5 class="card-title text-deepgreen fw-bold mb-4">
                <i class="bi bi-info-circle me-2"></i>How to Request Collection
              </h5>
              
              <ol class="step-list">
                <li class="mb-3">
                  <strong>Prepare Your Waste:</strong> Separate your waste into biodegradable, non-biodegradable, and recyclable materials.
                </li>
                <li class="mb-3">
                  <strong>Check Schedule:</strong> Verify your barangay's collection schedule from the municipal office.
                </li>
                <li class="mb-3">
                  <strong>Fill Request Form:</strong> Complete the collection request form with accurate details.
                </li>
                <li class="mb-3">
                  <strong>Submit Request:</strong> Submit your request at least 24 hours before your scheduled collection day.
                </li>
                <li class="mb-3">
                  <strong>Wait for Confirmation:</strong> You will receive a confirmation message within 2 hours.
                </li>
                <li class="mb-3">
                  <strong>Prepare for Collection:</strong> Place your waste outside your home on the scheduled date and time.
                </li>
              </ol>
            </div>
          </div>
        </div>
        
        <div class="col-lg-4">
          <div class="card notice-box">
            <div class="card-body">
              <h6 class="card-title text-deepgreen fw-bold">
                <i class="bi bi-exclamation-triangle me-2"></i>Important Notice
              </h6>
              <ul class="list-unstyled">
                <li class="mb-2">• Collection is free of charge</li>
                <li class="mb-2">• Maximum 3 bags per household</li>
                <li class="mb-2">• Waste must be properly sealed</li>
                <li class="mb-2">• No hazardous materials</li>
                <li class="mb-2">• Collection time: 6:00 AM - 2:00 PM</li>
              </ul>
            </div>
          </div>
          
          <div class="card mt-3">
            <div class="card-body">
              <h6 class="card-title text-deepgreen fw-bold">
                <i class="bi bi-telephone me-2"></i>Contact Information
              </h6>
              <p class="mb-1"><strong>Phone:</strong> (123) 456-7890</p>
              <p class="mb-1"><strong>Email:</strong> menro@malvar.gov.ph</p>
              <p class="mb-0"><strong>Office Hours:</strong> 8:00 AM - 5:00 PM</p>
            </div>
          </div>
        </div>
      </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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