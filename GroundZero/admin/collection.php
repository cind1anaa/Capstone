<?php
session_start();

$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch requests with status (excluding collected and rejected)
$query = "SELECT r.*, u.first_name, u.last_name 
          FROM requests r 
          INNER JOIN users u ON r.user_id = u.id 
          WHERE r.status NOT IN ('collected', 'rejected')
          ORDER BY r.request_date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Collection Request - Ground Zero</title>
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
      padding: 30px 15px;
      transition: margin-left 0.3s ease;
    }

    .sidebar.active ~ .dashboard-container {
      margin-left: 250px;
    }

    .profile-icon {
      font-size: 24px;
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

    .btn-proof, .btn-accept, .btn-reject {
      font-size: 14px;
      border-radius: 20px;
      padding: 4px 10px;
      border: none;
    }

    .btn-proof {
      background-color: #dc3545;
      color: white;
    }

    .btn-accept {
      background-color: #28a745;
      color: white;
    }

    .btn-reject {
      background-color: #dc3545;
      color: white;
    }

    .sidebar ul li a.active {
      background-color: #27692A;
      color: white;
      border-radius: 8px;
      padding: 8px 10px;
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
   
    .btn-done {
      background-color: #17a2b8;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 4px;
      cursor: pointer;
      margin: 0 2px;
    }

    .btn-done:hover {
      background-color: #138496;
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

.badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
}

.btn-sm {
    padding: 3px 8px;
    font-size: 12px;
    margin: 0 2px;
}

.modal-header {
    background-color: #27692A;
    color: white;
}

    .modal-content {
        border-radius: 10px;
    }

    .search-bar {
        background-color: #cfe2c3;
        padding: 8px 15px;
        border-radius: 25px;
        display: flex;
        align-items: center;
        width: 300px;
        box-shadow: none;
    }

    .search-bar input {
        border: none;
        background: transparent;
        outline: none;
        flex: 1;
        font-size: 14px;
        margin-left: 0;
    }

    .search-bar i {
        color: #27692A;
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
      <li><a href="user.html"><i class="bi bi-people-fill"></i> User Management</a></li>
      <li><a href="monitoring.html"><i class="bi bi-recycle"></i> Waste Monitoring</a></li>
      <li><a href="createSched.html"><i class="bi bi-truck"></i> Collection Schedule</a></li>
      <li><a href="announcement.html"><i class="bi bi-megaphone"></i> Announcements</a></li>
      <li><a href="waste.html"><i class="bi bi-bar-chart-fill"></i> Waste Reports</a></li>
      <li><a href="collection.html" class="active"><i class="bi bi-trash3-fill"></i> Collection Request</a></li>
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
      <h5 class="m-0">Collection Request</h5>
    </div>
    <a href="admin_profile.php">
  <i class="bi bi-person-circle profile-icon" style="color: white;"></i>
</a>
  </div>

  <!-- Main -->
  <div class="dashboard-container container-fluid">
    <div class="tabs">
      <button class="active">Request</button>
      <a href="Chistory.php"><button>History</button></a>
    </div>

    <div class="d-flex align-items-center gap-2 mb-3">
      <div class="search-bar">
        <i class="bi bi-search me-2"></i>
        <input type="text" id="searchInput" placeholder="Search by name, contact, location, waste type, or quantity..." />
      </div>
      <button class="btn btn-outline-success btn-sm" id="sortToggle" data-sort-col="5" title="Sort by Date">
        <i class="bi bi-arrow-down-up"></i>
      </button>
    </div>

    <div class="table-container">
      <table class="table table-bordered">
        <thead class="table-success">
          <tr>
            <th>Name</th>
            <th>Contact Number</th>
            <th>Type of Waste</th>
            <th> Quantity</th>
            <th>Location</th>
            <th>Date</th>
            <th>Time</th>
            <th>Collection Date</th>
            <th>Payment Method</th>
            <th>Proof of Payment</th>
            <th>Proof of Segregation</th>
            <th>Notes</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
            <td><?= htmlspecialchars($row['contact_number']) ?></td>
            <td><?= htmlspecialchars($row['waste_type']) ?></td>
            <td><?= htmlspecialchars($row['waste_quantity']) ?></td>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td><?= htmlspecialchars($row['request_date']) ?></td>
            <td><?= htmlspecialchars($row['collection_preference']) ?></td>
            <td><?= htmlspecialchars($row['collection_date']) ?></td>
            <td><?= htmlspecialchars($row['payment_method']) ?></td>
            <td>
                <?php if ($row['payment_proof']): ?>
                    <button class="btn-view" onclick="viewProof('<?= htmlspecialchars($row['payment_proof']) ?>', 'Payment')">
                        <i class="bi bi-eye-fill"></i>
                    </button>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($row['segregation_proof']): ?>
                    <button class="btn-view" onclick="viewProof('<?= htmlspecialchars($row['segregation_proof']) ?>', 'Segregation')">
                        <i class="bi bi-eye-fill"></i>
                    </button>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['notes'] ?: 'N/A') ?></td>
            <td>
                <span class="badge bg-<?= 
                    $row['status'] === 'accepted' ? 'success' : 
                    ($row['status'] === 'rejected' ? 'danger' : 
                    ($row['status'] === 'collected' ? 'info' : 'warning')) 
                ?>">
                    <?= strtoupper(htmlspecialchars($row['status'])) ?>
                </span>
            </td>
            <td>
                <?php if ($row['status'] === 'pending'): ?>
                    <button class="btn btn-success btn-sm" onclick="updateStatus(<?= $row['id'] ?>, 'accepted')">ACCEPT</button>
                    <button class="btn btn-danger btn-sm" onclick="updateStatus(<?= $row['id'] ?>, 'rejected')">REJECT</button>
                <?php elseif ($row['status'] === 'accepted'): ?>
                    <button class="btn btn-info btn-sm" onclick="updateStatus(<?= $row['id'] ?>, 'collected')">MARK AS COLLECTED</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>
</tbody>
      </table>
    </div>
  </div>

  <!-- Modal for viewing proofs -->
<div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proofModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="proofImage" src="" alt="Proof" style="max-width: 100%; max-height: 80vh;">
            </div>
        </div>
    </div>
</div>

<!-- JavaScript functions for modal and status updates -->
<script>
function viewProof(path, type) {
    const modal = new bootstrap.Modal(document.getElementById('proofModal'));
    document.querySelector('#proofModal .modal-title').textContent = type + ' Proof';
    
    // Update path based on proof type
    const basePath = type === 'Payment' ? '../uploads/proofsP/' : '../uploads/proofsS/';
    document.getElementById('proofImage').src = basePath + path;
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.querySelector('.navbar-toggler');
    const dashboardContainer = document.querySelector('.dashboard-container');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.overlay');

    if (toggleBtn && dashboardContainer && sidebar && overlay) {
        toggleBtn.addEventListener('click', function() {
            dashboardContainer.classList.toggle('active');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', function() {
            dashboardContainer.classList.remove('active');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const tableBody = document.querySelector('tbody');
        const rows = tableBody.getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
          const row = rows[i];
          const cells = row.getElementsByTagName('td');
          let found = false;

          for (let j = 0; j < cells.length; j++) {
            const cellText = cells[j].textContent.toLowerCase();
            if (cellText.includes(searchTerm)) {
              found = true;
              break;
            }
          }

          if (found) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        }
      });
    }

    // Sort functionality
    const sortToggle = document.getElementById('sortToggle');
    let sortAsc = true;
    if (sortToggle) {
      sortToggle.addEventListener('click', function() {
        const sortCol = parseInt(this.getAttribute('data-sort-col') || '0', 10);
        const tableBody = document.querySelector('tbody');
        const rowsArray = Array.from(tableBody.querySelectorAll('tr'));

        rowsArray.sort((a, b) => {
          const aText = (a.querySelectorAll('td')[sortCol]?.textContent || '').trim();
          const bText = (b.querySelectorAll('td')[sortCol]?.textContent || '').trim();
          const comp = aText.localeCompare(bText, undefined, { numeric: true, sensitivity: 'base' });
          return sortAsc ? comp : -comp;
        });

        rowsArray.forEach(row => tableBody.appendChild(row));
        sortAsc = !sortAsc;
      });
    }

    // Add function to handle request status updates
    window.updateStatus = function(requestId, status) {
        if (confirm('Are you sure you want to mark this request as ' + status + '?')) {
            fetch('../admin/update_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'request_id=' + requestId + '&status=' + status
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Request marked as ' + status + ' successfully');
                    location.reload();
                } else {
                    alert('Error updating request status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating request status');
            });
        }
    }
});
</script>
  <!-- Make sure Bootstrap JS is included -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
