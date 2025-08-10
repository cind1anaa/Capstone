<?php
session_start();

$conn = new mysqli("localhost", "root", "", "ground_zero");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

        // Fetch requests with status 'collected' or 'rejected'
        $query = "SELECT r.*, u.first_name, u.last_name, r.contact_number, r.waste_quantity 
                  FROM requests r 
                  INNER JOIN users u ON r.user_id = u.id 
                  WHERE r.status IN ('collected', 'rejected')
                  ORDER BY r.request_date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Collection Request - History</title>
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

    .btn-proof {
      background: none;
      border: none;
      color: #27692A;
      cursor: pointer;
      padding: 5px;
      transition: color 0.3s;
    }

    .btn-proof:hover {
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

    .export-container {
      text-align: right;
      margin-top: 20px;
    }

    .export-container .btn {
      padding: 10px 20px;
      font-weight: 500;
    }

    .modal-header {
      background-color: #27692A;
      color: white;
    }

    .modal-content {
      border-radius: 10px;
    }

    .btn-export {
      background-color: #4CAF50;
      color: white;
      padding: 8px 20px;
      border: none;
      border-radius: 5px;
      float: right;
      margin-top: 10px;
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



    .no-data {
      text-align: center;
      padding: 40px;
      color: #666;
      font-style: italic;
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
      <li><a href="collection.php" class="active"><i class="bi bi-trash3-fill"></i> Collection Request</a></li>
    </ul>
    <div class="logout">
      <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>

    <!-- Overlay -->
  <div class="overlay" id="overlay"></div>

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <span class="toggle-btn" id="toggleBtn"><i class="bi bi-list"></i></span>
      <h5 class="m-0">Collection Request History</h5>
    </div>
    <a href="admin_profile.php">
  <i class="bi bi-person-circle profile-icon" style="color: white;"></i>
</a>
  </div>

   <!-- Main Content -->
  <div class="dashboard-container container-fluid" id="dashboardContainer">
    <div class="tabs">
      <button onclick="window.location.href='collection.php'">Request</button>
      <button class="active">History</button>
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
            <th>Quantity</th>
            <th>Location</th>
            <th>Date</th>
            <th>Collection Date</th>
            <th>Payment Method</th>
            <th>Proof of Payment</th>
            <th>Proof of Segregation</th>
                         <th>Notes</th>
             <th>Status</th>
             <th>Action</th>
          </tr>
        </thead>
        <tbody id="requestsTableBody">
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                             <tr data-id="<?= $row['id'] ?>">
                 <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= htmlspecialchars($row['contact_number']) ?></td>
                <td><?= htmlspecialchars($row['waste_type']) ?></td>
                <td><?= htmlspecialchars($row['waste_quantity']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= date('m/d/Y', strtotime($row['request_date'])) ?></td>
                <td><?= date('m/d/Y', strtotime($row['collection_date'])) ?></td>
                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                <td>
                  <?php if ($row['payment_proof']): ?>
                    <button class="btn-proof" onclick="viewProof('<?= htmlspecialchars($row['payment_proof']) ?>', 'Payment')">
                      <i class="bi bi-eye-fill"></i>
                    </button>
                  <?php else: ?>
                    <span class="text-muted">No proof</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($row['segregation_proof']): ?>
                    <button class="btn-proof" onclick="viewProof('<?= htmlspecialchars($row['segregation_proof']) ?>', 'Segregation')">
                      <i class="bi bi-eye-fill"></i>
                    </button>
                  <?php else: ?>
                    <span class="text-muted">No proof</span>
                  <?php endif; ?>
                </td>
                                 <td><?= htmlspecialchars($row['notes'] ?: 'N/A') ?></td>
                 <td>
                   <span class="badge bg-<?= $row['status'] === 'collected' ? 'info' : 'danger' ?>">
                     <?= strtoupper(htmlspecialchars($row['status'])) ?>
                   </span>
                 </td>
                 <td>
                   <button class="btn btn-danger btn-sm" onclick="deleteRequest(<?= $row['id'] ?>)">
                     <i class="bi bi-trash"></i> Delete
                   </button>
                 </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="13" class="no-data">
                <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                <br><br>
                No requests found in history.
                <br>
                <small>Requests will appear here once they are marked as collected or rejected.</small>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="export-container mt-3">
        <button class="btn btn-success" onclick="exportToCSV()">
          <i class="bi bi-download"></i> Export to CSV
        </button>
      </div>
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

  <!-- JavaScript for Sidebar Toggle and Search -->
  <script>
    const toggleBtn = document.getElementById('toggleBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const dashboardContainer = document.getElementById('dashboardContainer');
    const searchInput = document.getElementById('searchInput');

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
    searchInput.addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase();
      const tableBody = document.getElementById('requestsTableBody');
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

    // Sort functionality
    const sortToggle = document.getElementById('sortToggle');
    let sortAsc = true;
    if (sortToggle) {
      sortToggle.addEventListener('click', function() {
        const sortCol = parseInt(this.getAttribute('data-sort-col') || '0', 10);
        const tableBody = document.getElementById('requestsTableBody');
        const rowsArray = Array.from(tableBody.querySelectorAll('tr'));

        rowsArray.sort((a, b) => {
          const aText = (a.querySelectorAll('td')[sortCol]?.textContent || '').trim();
          const bText = (b.querySelectorAll('td')[sortCol]?.textContent || '').trim();
          const comp = aText.localeCompare(bText, undefined, { numeric: true, sensitivity: 'base' });
          return sortAsc ? comp : -comp;
        });

        // Re-append in new order
        rowsArray.forEach(row => tableBody.appendChild(row));
        sortAsc = !sortAsc;
      });
    }

    // Function to view proof images
    function viewProof(path, type) {
      const modal = new bootstrap.Modal(document.getElementById('proofModal'));
      document.querySelector('#proofModal .modal-title').textContent = type + ' Proof';
      
      // Update path based on proof type
      const basePath = type === 'Payment' ? '../uploads/proofsP/' : '../uploads/proofsS/';
      document.getElementById('proofImage').src = basePath + path;
      modal.show();
    }

         // Function to delete request
     function deleteRequest(requestId) {
       if (confirm('Are you sure you want to delete this request? This action cannot be undone.')) {
         fetch('delete_request.php', {
           method: 'POST',
           headers: {
             'Content-Type': 'application/x-www-form-urlencoded',
           },
           body: 'request_id=' + requestId
         })
         .then(response => response.json())
         .then(data => {
           if (data.success) {
             // Remove the row from the table
             const row = document.querySelector(`tr[data-id="${requestId}"]`);
             if (row) {
               row.remove();
             }
             // Reload the page to refresh the table
             location.reload();
           } else {
             alert('Error deleting request: ' + (data.error || 'Unknown error'));
           }
         })
         .catch(error => {
           console.error('Error:', error);
           alert('Error deleting request. Please try again.');
         });
       }
     }

     // Export to CSV functionality
     function exportToCSV() {
      const table = document.querySelector('table');
      const rows = table.querySelectorAll('tbody tr');
      let csv = [];
      
      // Add headers
      const headers = [
        'Name', 'Contact Number', 'Type of Waste', 'Quantity', 'Location', 
        'Date', 'Collection Date', 'Payment Method', 'Notes', 'Status'
      ];
      csv.push(headers.join(','));
      
      // Add data rows
      for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.querySelectorAll('td');
        let rowData = [];
        
        // Skip the Action column (last column) and Proof columns
        for (let j = 0; j < cells.length - 3; j++) {
          let text = cells[j].textContent.trim();
          // Remove any HTML tags and clean the text
          text = text.replace(/<[^>]*>/g, '');
          // Escape quotes and wrap in quotes if contains comma
          if (text.includes(',') || text.includes('"')) {
            text = '"' + text.replace(/"/g, '""') + '"';
          }
          rowData.push(text);
        }
        
        csv.push(rowData.join(','));
      }
      
      const csvContent = csv.join('\n');
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      
      if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'collection_history_' + new Date().toISOString().split('T')[0] + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      } else {
        alert('Download not supported in this browser. Please copy the data manually.');
      }
    }
  </script>
  
  <!-- Make sure Bootstrap JS is included -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
