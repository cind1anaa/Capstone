<?php
session_start();

// Check if user is logged in and exists in database
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify user exists in database
$check_user = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_user->bind_param("i", $_SESSION["user_id"]);
$check_user->execute();
$user_result = $check_user->get_result();

if ($user_result->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Initialize empty notes and filenames
    $notes = $_POST["notes"] ?? '';
    $payment_filename = null;
    $segregation_filename = null;
    
    // Validate required segregation proof
    if (!isset($_FILES['segregation_proof']) || $_FILES['segregation_proof']['error'] !== UPLOAD_ERR_OK) {
        $error = "Proof of segregation is required";
    } else {
        $segregation_filename = time() . '_' . basename($_FILES['segregation_proof']['name']);
        $segregation_target = "uploads/proofsS/" . $segregation_filename;
        if (!move_uploaded_file($_FILES['segregation_proof']['tmp_name'], $segregation_target)) {
            $error = "Failed to upload segregation proof";
        }
    }

    // Handle optional payment proof
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $payment_filename = time() . '_' . basename($_FILES['payment_proof']['name']);
        $payment_target = "uploads/proofsP/" . $payment_filename;
        move_uploaded_file($_FILES['payment_proof']['tmp_name'], $payment_target);
    }

    // Only proceed if there are no errors
    if (!isset($error)) {
        // Create variables for binding
        $user_id = $_SESSION["user_id"];
        $waste_type = $_POST["waste_type"];
        $waste_quantity = $_POST["waste_quantity"];
        $location = $_POST["location"];
        $collection_date = $_POST["collection_date"];
        $collection_preference = $_POST["collection_preference"];
        $payment_method = $_POST["payment_method"];
        
        // Set default empty string for payment_proof if null
        $payment_filename = $payment_filename ?? '';
        
        $stmt = $conn->prepare("INSERT INTO requests (
            user_id, 
            name, 
            contact_number, 
            waste_type, 
            waste_quantity, 
            location, 
            collection_date, 
            collection_preference, 
            payment_method, 
            payment_proof, 
            segregation_proof, 
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssssssssssss", 
            $user_id,
            $_POST["name"],
            $_POST["contact_number"],
            $waste_type,
            $waste_quantity,
            $location,
            $collection_date,
            $collection_preference,
            $payment_method,
            $payment_filename, // This will be empty string if null
            $segregation_filename,
            $notes
        );

        if ($stmt->execute()) {
            echo "<script>
                alert('Request submitted successfully!');
                window.location.href='Rhistory.php';
            </script>";
            exit();
        } else {
            $error = "Failed to submit request: " . $conn->error;
        }
    }
}

// Fetch notifications for the current user
$notifications_result = [];
$unread_count = 0;
$user_data = [];

// Only fetch if user is logged in
if (isset($_SESSION["user_id"])) {
    // Fetch notifications
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
    $unread_count = $unread_data['unread_count'] ?? 0;

    // Get user info
    $user_query = "SELECT first_name, last_name, avatar FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("i", $_SESSION["user_id"]);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
}

// Set default values if data is not available
$user_name = isset($user_data['first_name']) ? $user_data['first_name'] . ' ' . $user_data['last_name'] : 'User';
$user_avatar = isset($user_data['avatar']) && $user_data['avatar'] ? $user_data['avatar'] : 'images/user.png';
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

    /* Green dropdown */
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

    .form-card {
      border-left: 5px solid #27692A;
      background-color: #ffffff;
      border-radius: 1rem;
    }

    .form-label {
      font-weight: 500;
      color: #27692A;
    }

    .form-control,
    .form-select,
    textarea {
      border-radius: 0.5rem;
      border: 2px solid #27692A;
      background-color: #f6fff6;
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

    .form-check-label {
      color: #27692A;
    }

    .form-check-input {
      border: 2px solid #27692A;
    }

    .form-check-input:checked {
      background-color: #27692A;
      border-color: #27692A;
    }

    .btn-success {
      background-color: #27692A;
      border-color: #27692A;
      border-radius: 30px;
    }

    .btn-success:hover {
      background-color: #1e5221;
      border-color: #1e5221;
    }

    input[type="file"] {
      border: 2px solid #27692A;
      color: #27692A;
      background-color: #f6fff6;
    }

    @media (max-width: 767px) {
      .navbar-collapse {
        background-color: #27692A;
        padding: 1rem;
      }
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

    .alert {
      border-radius: 0.5rem;
      margin-bottom: 1rem;
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
          <li>
            <h6 class="dropdown-header"><img src="<?= htmlspecialchars($user_avatar) ?>" class="rounded-circle me-2" width="24" height="24"> <?= htmlspecialchars($user_name) ?></h6>
          </li>
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
      <a class="nav-link active" href="request.php">Request</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="Rhistory.php">History</a>
    </li>
  </ul>

  <?php if (isset($success_message)): ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($success_message) ?>
    </div>
  <?php endif; ?>

  <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
      <?= htmlspecialchars($error_message) ?>
    </div>
  <?php endif; ?>

  <!-- Request Form -->
  <form class="p-4 shadow-sm form-card" method="POST" enctype="multipart/form-data">
    <div class="row mb-3">
      <div class="col-md-6">
        <label for="name" class="form-label">Name</label>
        <input type="text" id="name" name="name" class="form-control" placeholder="Enter your Name" required>
      </div>
      <div class="col-md-6">
        <label for="contact_number" class="form-label">Contact Number</label>
        <input type="tel" id="contact_number" name="contact_number" class="form-control" placeholder="Enter your contact number" required>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <label for="waste_type" class="form-label">Type of Waste</label>
        <select id="waste_type" name="waste_type" class="form-select" required>
          <option selected disabled>Select Type of Waste</option>
          <option value="Biodegradable">Biodegradable</option>
          <option value="Non-Biodegradable">Non-Biodegradable</option>
          <option value="Recyclable">Recyclable</option>
          <option value="Residual">Residual</option>
        </select>
      </div>
      <div class="col-md-6">
        <label for="waste_quantity" class="form-label">Quantity</label>
        <select id="waste_quantity" name="waste_quantity" class="form-select" required>
          <option selected disabled>Select Quantity</option>
          <option value="Small (1-2 bags)">Small (1-2 bags)</option>
          <option value="Medium (3-5 bags)">Medium (3-5 bags)</option>
          <option value="Large (6-10 bags)">Large (6-10 bags)</option>
        </select>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-12">
        <label for="location" class="form-label">Complete Address</label>
        <textarea id="location" name="location" class="form-control" rows="3" placeholder="Enter your complete address including barangay, street, house number, etc." required></textarea>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <label for="request_date" class="form-label">Request Date</label>
        <input type="date" id="request_date" name="request_date" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label for="collection_date" class="form-label">Collection Date</label>
        <input type="date" id="collection_date" name="collection_date" class="form-control" required>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <label class="form-label">Payment Method</label>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="payment_method" id="cash" value="Cash" required>
          <label class="form-check-label" for="cash">Cash</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="payment_method" id="gcash" value="GCash" required>
          <label class="form-check-label" for="gcash">GCash</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="payment_method" id="paymaya" value="PayMaya" required>
          <label class="form-check-label" for="paymaya">PayMaya</label>
        </div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Collection Time</label>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="collection_preference" id="morning" value="Morning (6AM-12PM)" required>
          <label class="form-check-label" for="morning">Morning</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="collection_preference" id="afternoon" value="Afternoon (12PM-6PM)" required>
          <label class="form-check-label" for="afternoon">Afternoon</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="collection_preference" id="evening" value="Evening (6PM-9PM)" required>
          <label class="form-check-label" for="evening">Evening</label>
        </div>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <label for="segregation_proof" class="form-label">Proof of Segregation</label>
        <input class="form-control" type="file" id="segregation_proof" name="segregation_proof" accept="image/*,.pdf" required>
        <small class="text-muted">Upload photo of properly segregated waste</small> 
      </div>
      <div class="col-md-6">
        <label for="payment_proof" class="form-label">Proof of Payment (Optional)</label>
        <input class="form-control" type="file" id="payment_proof" name="payment_proof" accept="image/*,.pdf">
        <small class="text-muted">Upload payment receipt/screenshot if available</small> 
      </div>
    </div>

    <div class="row mb-4">
      <div class="col-md-12">
        <label for="notes" class="form-label">Additional Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any special instructions or additional details..."></textarea>
      </div>
    </div>

    <div class="text-center">
      <button type="submit" class="btn btn-success px-5">Submit Request</button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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