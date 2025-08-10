
<?php
// Initialize variables
$success = "";
$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = new mysqli("localhost", "root", "", "ground_zero");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Collect form data
    $first = $_POST['first_name'];
    $last = $_POST['last_name'];
    $barangay = $_POST['barangay'];
    $type = $_POST['establishment_type'];
    $establishment = $_POST['establishment_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // file upload
        $file = $_FILES['proof']['name'];
        $target = "uploads/" . basename($file);
        move_uploaded_file($_FILES['proof']['tmp_name'], $target);

        // Check if email already exists
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $result = $checkEmail->get_result();

        if ($result->num_rows > 0) {
            // Email already exists
            echo "<script>
                alert('This email address is already registered. Please use a different email or contact support if you think this is an error.');
                window.location.href = 'register.php';
            </script>";
            exit();
        }
        $checkEmail->close();

        // If email doesn't exist, proceed with registration
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, barangay, establishment_type, establishment_name, email, phone, password, proof_document, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("sssssssss", $first, $last, $barangay, $type, $establishment, $email, $phone, $hashed, $target);

        try {
            if ($stmt->execute()) {
                echo "<script>
                    alert('Registration successful! Please wait for admin approval.');
                    window.location.href = 'login.php';
                </script>";
            } else {
                echo "<script>
                    alert('Registration failed. Please try again.');
                    window.location.href = 'register.php';
                </script>";
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Registration error: " . $e->getMessage());
            echo "<script>
                alert('An error occurred during registration. Please try again.');
                window.location.href = 'register.php';
            </script>";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Create Account - Ground Zero</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body {
      font-size: 0.95rem;
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

    .navbar-brand span {
      font-weight: bold;
    }

    .custom-navbar .nav-link.active {
      background-color: white;
      color: #27692A !important;
      border-radius: 5px;
      padding: 6px 12px;
    }
    
    .register-section {
      position: relative;
      background: url('images/city.jpg') no-repeat center center;
      background-size: cover;
      background-attachment: fixed;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px 10px;
    }

    .register-section::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(39, 105, 42, 0.85) 0%, rgba(39, 105, 42, 0.75) 100%);
      z-index: 1;
    }

    .register-section .register-card {
      position: relative;
      z-index: 2;
    }
    .register-card {
      background: linear-gradient(to bottom right, #5a8d3f, #8dc26f);
      padding: 20px 25px;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 750px;
      color: #fff;
    }
    .form-control,
    .form-select {
      font-size: 0.88rem;
      padding: 6px 10px;
      border-radius: 5px;
      background-color: #f7f7f7;
    }
    .btn-register {
      background-color: #fff;
      color: #27692A;
      font-weight: 600;
      padding: 6px 20px;
      font-size: 0.9rem;
      border-radius: 8px;
    }
    .small-note {
      font-size: 0.75rem;
      color: #f1f1f1;
    }
    .text-warning {
    color: #aa1502ff !important;
}

.input-group .btn-light {
    background-color: #f7f7f7;
    border-color: #ced4da;
}

.input-group .btn-light:hover {
    background-color: #e2e6ea;
}
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark custom-navbar px-4">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="images/logo.png" alt="Ground Zero Logo" height="40" class="me-2" />
      <span>MENRO – Malvar Batangas</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="index.php">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="about.html">ABOUT</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.html">CONTACT</a></li>
      </ul>
    </div>
  </nav>

<section class="register-section">
  <div class="position-absolute top-0 start-0 m-3" style="z-index: 3;">
    <a href="index.php" class="btn btn-outline-light btn-sm">
      <i class="bi bi-arrow-left"></i>
    </a>
  </div>
  <div class="register-card">
    <h4 class="text-center fw-bold mb-3">Create an Account</h4>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" autocomplete="on">
      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label">First Name</label>
          <input type="text" class="form-control" name="first_name" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Last Name</label>
          <input type="text" class="form-control" name="last_name" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Barangay</label>
          <select class="form-select" name="barangay" required>
            <option selected disabled>Select your barangay</option>
            <option>Bagong Pook</option>
            <option>Bilucao</option>
            <option>Bulihan</option>
            <option>Luta del Norte</option>
            <option>Luta del Sur</option>
            <option>Poblacion</option>
            <option>San Andres</option>
            <option>San Fernando</option>
            <option>San Gregorio</option>
            <option>San Isidro East</option>
            <option>San Isidro West</option>
            <option>San Juan</option>
            <option>San Pedro I</option>
            <option>San Pedro II</option>
            <option>San Pioquinto</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Proof of Residency</label>
          <input type="file" class="form-control" name="proof" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Type of Establishment</label>
          <select class="form-select" name="establishment_type" required>
            <option selected disabled>Select your establishment</option>
            <option>Residential</option>
            <option>Commercial</option>
            <option>Industrial</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Name of Establishment</label>
          <input type="text" class="form-control" name="establishment_name" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" autocomplete="email" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone Number</label>
          <input type="tel" class="form-control" name="phone" required />
        </div>
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <div class="input-group">
              <input type="password" class="form-control" name="password" id="password" autocomplete="new-password" required />
              <button class="btn btn-light" type="button" id="togglePassword">
                  <i class="bi bi-eye"></i>
              </button>
          </div>
          <div class="small-note mt-1"> </div>
          <div id="passwordError" class="text-warning small-note mt-1"></div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm Password</label>
          <div class="input-group">
              <input type="password" class="form-control" name="confirm_password" id="confirmPassword" autocomplete="new-password" required />
              <button class="btn btn-light" type="button" id="toggleConfirmPassword">
                  <i class="bi bi-eye"></i>
              </button>
          </div>
          <div id="confirmPasswordError" class="text-warning small-note mt-1"></div>
        </div>
      </div>
      <div class="text-center mt-3">
        <button type="submit" class="btn btn-register">Register</button>
      </div>
      <p class="text-center mt-2 text-white">
        Already have an account? <a href="login.php" class="text-white">Click Here</a>
      </p>
    </form>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const passwordError = document.getElementById('passwordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');

    // Password validation
    password.addEventListener('input', function() {
        const value = this.value;
        let errors = [];

        if (value.length < 8) {
            errors.push('Password must be at least 8 characters');
        }
        if (!/\d/.test(value)) {
            errors.push('Password must contain at least one number');
        }
        if (!/[A-Z]/.test(value)) {
            errors.push('Password must contain at least one uppercase letter');
        }
        if (!/[a-z]/.test(value)) {
            errors.push('Password must contain at least one lowercase letter');
        }
        if (!/[!@#$%^&*]/.test(value)) {
            errors.push('Password must contain at least one special character (!@#$%^&*)');
        }

        passwordError.innerHTML = errors.join('<br>');
        passwordError.style.display = errors.length ? 'block' : 'none';
    });

    // Confirm password validation
    confirmPassword.addEventListener('input', function() {
        if (this.value !== password.value) {
            confirmPasswordError.textContent = 'Passwords do not match';
            confirmPasswordError.style.display = 'block';
        } else {
            confirmPasswordError.style.display = 'none';
        }
    });

    // Toggle password visibility
    togglePassword.addEventListener('click', function() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.querySelector('i').classList.toggle('bi-eye');
        this.querySelector('i').classList.toggle('bi-eye-slash');
    });

    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPassword.setAttribute('type', type);
        this.querySelector('i').classList.toggle('bi-eye');
        this.querySelector('i').classList.toggle('bi-eye-slash');
    });
});
</script>
</body>
</html>
