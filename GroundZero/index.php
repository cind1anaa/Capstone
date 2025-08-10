<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Ground Zero</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="styles.css" rel="stylesheet" />
  <style>
    .hero-section {
      position: relative;
      background: url('images/city.jpg') no-repeat center center;
      background-size: cover;
      background-attachment: fixed;
      color: white;
      display: flex;
      align-items: center;
      filter: blur(0px);
    }

    .hero-section::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.6) 0%, rgba(255, 255, 255, 0.5) 100%);
      z-index: 1;
      backdrop-filter: blur(2px);
    }

    .hero-section > .container {
      position: relative;
      z-index: 2;
    }

    .hero-section h1.display-3 {
      color: #173D1D;
      text-shadow: 2px 2px 4px rgba(255, 255, 255, 0.8);
      font-weight: 900;
    }

    .hero-section .explore-text {
      background-color: rgba(255, 255, 255, 0.9) !important;
      color: #173D1D !important;
      font-weight: 600;
      text-shadow: none;
    }

    /* Announcements Slider */
    .announcements-section {
      background: linear-gradient(135deg, #27692A 0%, #4a7934 100%);
      padding: 40px 0;
      margin-top: -50px;
      position: relative;
      z-index: 10;
    }

    .announcement-card {
      background: white;
      border-radius: 15px;
      padding: 25px;
      margin: 10px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }

    .announcement-card:hover {
      transform: translateY(-5px);
    }

    .announcement-title {
      color: #27692A;
      font-weight: bold;
      font-size: 1.1rem;
      margin-bottom: 10px;
    }

    .announcement-content {
      color: #666;
      font-size: 0.95rem;
      line-height: 1.5;
    }

    .announcement-date {
      color: #999;
      font-size: 0.85rem;
      font-style: italic;
    }

    .carousel-control-prev,
    .carousel-control-next {
      width: 40px;
      height: 40px;
      background: rgba(39, 105, 42, 0.8);
      border-radius: 50%;
      top: 50%;
      transform: translateY(-50%);
    }

    .carousel-indicators {
      bottom: -30px;
    }

    .carousel-indicators button {
      background-color: #27692A;
      width: 12px;
      height: 12px;
      border-radius: 50%;
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
        <li class="nav-item"><a class="nav-link active" href="index.html">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="about.html">ABOUT</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.html">CONTACT</a></li>
      </ul>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="container h-100">
      <div class="row h-100 align-items-start pt-5">

        <!-- Text Column -->
        <div class="col-md-8 d-flex flex-column align-items-start text-success pe-md-5" style="height: 100%;">
          <h1 class="display-3 fw-bold text-success">GROUND ZERO</h1>
          <p class="explore-text fw-medium bg-light d-inline-block px-3 py-2 rounded">
            Your starting point of Zero Waste
          </p>
                     <a href="register.php" class="btn btn-light-green btn-lg text-white my-3">Get Started →</a>
        </div>

                 <!-- SDG Column -->
         <div class="col-md-4 d-flex flex-column align-items-start gap-3 pt-5">
           <a href="https://sdgs.un.org/goals/goal2" target="_blank" class="text-decoration-none">
             <div class="sdg-hover">
               <img src="images/sdg2.png" alt="SDG 2 - Zero Hunger" class="rounded shadow" />
               <div class="hover-text">
                 <strong>SDG 2: Zero Hunger</strong><br />
                 End hunger, achieve food security, and improve nutrition.<br />
                 <small class="text-warning">Click to learn more →</small>
               </div>
             </div>
           </a>
           <a href="https://sdgs.un.org/goals/goal15" target="_blank" class="text-decoration-none">
             <div class="sdg-hover">
               <img src="images/sdg15.png" alt="SDG 15 - Life on Land" class="rounded shadow" />
               <div class="hover-text">
                 <strong>SDG 15: Life on Land</strong><br />
                 Sustainably manage forests, combat desertification, and halt biodiversity loss.<br />
                 <small class="text-warning">Click to learn more →</small>
               </div>
             </div>
           </a>
           <a href="https://sdgs.un.org/goals/goal12" target="_blank" class="text-decoration-none">
             <div class="sdg-hover">
               <img src="images/sdg12.png" alt="SDG 12 - Responsible Consumption" class="rounded shadow" />
               <div class="hover-text">
                 <strong>SDG 12: Responsible Consumption</strong><br />
                 Ensure sustainable consumption and production patterns.<br />
                 <small class="text-warning">Click to learn more →</small>
               </div>
             </div>
           </a>
         </div>

      </div>
    </div>
  </section>

  <!-- Announcements Section -->
  <section class="announcements-section">
    <div class="container">
      <div class="row">
        <div class="col-12 text-center mb-4">
          <h2 class="text-white fw-bold mb-2">
            <i class="bi bi-megaphone-fill me-2"></i>Latest Announcements
          </h2>
          <p class="text-white-50">Stay updated with the latest news and updates from MENRO</p>
        </div>
      </div>
      
      <div class="row justify-content-center">
        <div class="col-lg-10">
          <div id="announcementsCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-inner">
              <div class="carousel-item active">
                <div class="announcement-card">
                  <div class="announcement-title">
                    <i class="bi bi-calendar-event text-success me-2"></i>
                    Community Clean-up Drive
                  </div>
                  <div class="announcement-content">
                    Join us this Saturday for our monthly community clean-up drive. Let's work together to keep Malvar clean and green! Meet at the Municipal Hall at 7:00 AM.
                  </div>
                  <div class="announcement-date mt-2">
                    <i class="bi bi-clock me-1"></i>Posted: January 15, 2024
                  </div>
                </div>
              </div>
              
              <div class="carousel-item">
                <div class="announcement-card">
                  <div class="announcement-title">
                    <i class="bi bi-recycle text-success me-2"></i>
                    New Waste Segregation Guidelines
                  </div>
                  <div class="announcement-content">
                    Updated waste segregation guidelines are now in effect. Please separate your waste into biodegradable, recyclable, and residual categories. Check our website for detailed instructions.
                  </div>
                  <div class="announcement-date mt-2">
                    <i class="bi bi-clock me-1"></i>Posted: January 12, 2024
                  </div>
                </div>
              </div>
              
              <div class="carousel-item">
                <div class="announcement-card">
                  <div class="announcement-title">
                    <i class="bi bi-tree text-success me-2"></i>
                    Tree Planting Initiative
                  </div>
                  <div class="announcement-content">
                    We're launching a new tree planting initiative across Malvar. Help us plant 1000 trees this year! Contact us to participate in this environmental project.
                  </div>
                  <div class="announcement-date mt-2">
                    <i class="bi bi-clock me-1"></i>Posted: January 10, 2024
                  </div>
                </div>
              </div>
            </div>
            
            <button class="carousel-control-prev" type="button" data-bs-target="#announcementsCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#announcementsCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon"></span>
            </button>
            
            <div class="carousel-indicators">
              <button type="button" data-bs-target="#announcementsCarousel" data-bs-slide-to="0" class="active"></button>
              <button type="button" data-bs-target="#announcementsCarousel" data-bs-slide-to="1"></button>
              <button type="button" data-bs-target="#announcementsCarousel" data-bs-slide-to="2"></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
