<?php
session_start();
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin.php");
        exit;
    }
    if ($_SESSION['user_role'] === 'driver') {
        header("Location: drivers_dashboard.php");
        exit;
    }
    header("Location: homepage.php");
    exit;
}

$heroImagePath = 'C:\\Users\\yvette\\Downloads\\Nyabugogo.jpeg';
$heroImage = '';
if (is_file($heroImagePath)) {
    $heroImageData = @file_get_contents($heroImagePath);
    if ($heroImageData !== false) {
        $heroImage = 'data:image/jpeg;base64,' . base64_encode($heroImageData);
    }
}

if ($heroImage === '') {
    $heroImage = 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/29/Buses_at_Nyabugogo.jpg/1280px-Buses_at_Nyabugogo.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SwiftPass | Smart Bus Travel</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --blue: #3ea3ff;
      --blue-dark: #0f6fd8;
      --blue-deep: #123c78;
      --sky: #eaf4ff;
      --navy: #1a2c44;
      --text: #1a2c44;
      --muted: #5f738b;
      --surface: #ffffff;
      --line: #dde7f2;
      --bg: #f2f7fc;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--text);
      font-family: 'Source Sans 3', sans-serif;
    }

    a {
      text-decoration: none;
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 20;
      background: rgba(255, 255, 255, 0.96);
      border-bottom: 1px solid var(--line);
      backdrop-filter: blur(8px);
    }

    .nav-shell {
      max-width: 1180px;
      margin: 0 auto;
      padding: 0.9rem 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .brand {
      display: inline-flex;
      align-items: center;
      gap: 0.8rem;
      color: var(--navy);
      font-family: 'Montserrat', sans-serif;
      font-weight: 800;
      font-size: 1.55rem;
      line-height: 1;
    }

    .brand-mark {
      width: 46px;
      height: 46px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, var(--blue), var(--blue-dark));
      color: white;
      box-shadow: 0 10px 22px rgba(15, 111, 216, 0.22);
      font-size: 1.1rem;
    }

    .brand-text {
      display: flex;
      flex-direction: column;
      gap: 0.18rem;
    }

    .brand-kicker {
      font-size: 0.58rem;
      font-weight: 700;
      color: #7f8a96;
      text-transform: uppercase;
      letter-spacing: 0.14em;
    }

    .brand-wordmark {
      font-size: 1.45rem;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--navy);
    }

    .brand-wordmark .mint {
      color: var(--blue-dark);
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 2rem;
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .nav-links a {
      color: var(--navy);
      font-family: 'Montserrat', sans-serif;
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .nav-actions {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      white-space: nowrap;
    }

    .login-link {
      color: #2e86de;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.82rem;
      font-weight: 700;
    }

    .signup-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--blue-dark);
      color: white;
      border-radius: 3px;
      padding: 0.72rem 1rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.76rem;
      font-weight: 700;
      text-transform: uppercase;
      box-shadow: 0 10px 25px rgba(15, 111, 216, 0.22);
    }

    html {
      scroll-behavior: smooth;
    }

    .hero {
      position: relative;
      min-height: 620px;
      background:
        linear-gradient(rgba(7, 36, 82, 0.58), rgba(7, 36, 82, 0.58)),
        url('<?php echo htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8'); ?>') center center / cover no-repeat;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
    }

    .hero-inner {
      max-width: 980px;
      padding: 4rem 1.5rem 5rem;
    }

    .hero h1 {
      margin: 0 auto 1.2rem;
      font-family: 'Montserrat', sans-serif;
      font-size: clamp(2.5rem, 5vw, 4.9rem);
      line-height: 0.98;
      font-weight: 800;
      text-transform: uppercase;
      color: white;
      max-width: 900px;
    }

    .hero h1 .accent {
      color: #8fd0ff;
    }

    .hero p {
      margin: 0 auto 1.8rem;
      max-width: 640px;
      color: rgba(255, 255, 255, 0.94);
      font-size: 1.28rem;
      line-height: 1.5;
    }

    .hero-cta {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.55rem;
      background: var(--blue);
      color: white;
      border-radius: 2px;
      padding: 0.95rem 2.2rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.9rem;
      font-weight: 700;
      text-transform: uppercase;
      box-shadow: 0 14px 28px rgba(62, 163, 255, 0.28);
    }

    .section-block {
      padding: 5rem 0;
      background: #fff;
    }

    .section-block.light {
      background: #f8fbff;
    }

    .section-shell {
      max-width: 1180px;
      margin: 0 auto;
      padding: 0 1.5rem;
    }

    .title-sm {
      display: inline-block;
      color: var(--blue-dark);
      font-family: 'Montserrat', sans-serif;
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .section-divider {
      width: 78px;
      height: 4px;
      border-radius: 999px;
      background: linear-gradient(90deg, var(--blue-dark), #66bcff);
      margin-top: 0.9rem;
    }

    .section-copy h2 {
      margin: 1.35rem 0 0.85rem;
      font-family: 'Montserrat', sans-serif;
      font-size: clamp(2rem, 4vw, 2.9rem);
      font-weight: 800;
      color: var(--navy);
      line-height: 1.2;
    }

    .section-copy p {
      margin: 0;
      color: var(--muted);
      line-height: 1.7;
      font-size: 1.02rem;
    }

    .section-intro {
      max-width: 640px;
    }

    .stack-card {
      background: #fff;
      border: 1px solid #e3edf7;
      border-radius: 24px;
      padding: 1.4rem;
      box-shadow: 0 16px 34px rgba(34, 50, 71, 0.06);
      height: 100%;
    }

    .stack-card h3,
    .stack-card h4 {
      margin: 0 0 0.65rem;
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
      color: var(--navy);
    }

    .stack-card p {
      margin: 0;
      color: var(--muted);
      line-height: 1.65;
    }

    .icon-pill {
      width: 54px;
      height: 54px;
      border-radius: 16px;
      display: grid;
      place-items: center;
      margin-bottom: 1rem;
      background: linear-gradient(135deg, var(--blue), var(--blue-dark));
      color: white;
      font-size: 1.08rem;
      box-shadow: 0 12px 24px rgba(15, 111, 216, 0.16);
    }

    .about-image,
    .work-image {
      border-radius: 24px;
      overflow: hidden;
      box-shadow: 0 22px 46px rgba(34, 50, 71, 0.14);
      min-height: 320px;
      background:
        linear-gradient(rgba(12, 46, 90, 0.18), rgba(12, 46, 90, 0.18)),
        url('<?php echo htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8'); ?>') center center / cover no-repeat;
    }

    .services-box {
      background: #fff;
      border: 1px solid #e3edf7;
      border-radius: 22px;
      padding: 1.4rem;
      box-shadow: 0 14px 30px rgba(34, 50, 71, 0.05);
      height: 100%;
    }

    .services-box .number {
      color: var(--blue-dark);
      font-family: 'Montserrat', sans-serif;
      font-size: 1.25rem;
      font-weight: 800;
    }

    .pill-link {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--blue-dark);
      font-family: 'Montserrat', sans-serif;
      font-size: 0.83rem;
      font-weight: 700;
      text-transform: uppercase;
      margin-top: 1rem;
    }

    .feature-chip {
      display: flex;
      align-items: center;
      gap: 1rem;
      background: #fff;
      border: 1px solid #e3edf7;
      border-radius: 999px;
      padding: 0.9rem 1rem;
      box-shadow: 0 12px 28px rgba(34, 50, 71, 0.05);
    }

    .feature-chip i {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: #e9f4ff;
      color: var(--blue-dark);
    }

    .workflow-item {
      text-align: center;
      padding: 1rem;
    }

    .workflow-item .icon-pill {
      margin-left: auto;
      margin-right: auto;
    }

    .contact-panel {
      background: linear-gradient(135deg, #12366a, #1b5aa6);
      border-radius: 28px;
      padding: 2.5rem;
      color: white;
      text-align: center;
      box-shadow: 0 24px 46px rgba(18, 60, 120, 0.2);
    }

    .contact-panel p {
      color: rgba(255,255,255,0.88);
      max-width: 620px;
      margin: 0.9rem auto 1.6rem;
      line-height: 1.7;
    }

    .contact-panel .stack-card {
      background: rgba(255, 255, 255, 0.14);
      border-color: rgba(255, 255, 255, 0.24);
      color: #ffffff;
    }

    .contact-panel .stack-card h4,
    .contact-panel .stack-card p,
    .contact-panel .stack-card .icon-pill {
      color: #ffffff;
    }

    .contact-panel .stack-card .icon-pill {
      background: rgba(255, 255, 255, 0.18);
      box-shadow: none;
    }

    .action-row {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-soft-light {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.95rem 1.35rem;
      border-radius: 10px;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.86rem;
      font-weight: 700;
      text-transform: uppercase;
      color: white;
      border: 1px solid rgba(255,255,255,0.24);
      background: rgba(255,255,255,0.08);
    }

    @media (max-width: 991px) {
      .nav-shell {
        flex-wrap: wrap;
        justify-content: center;
      }

      .nav-links {
        gap: 1rem;
        flex-wrap: wrap;
        justify-content: center;
      }

      .hero {
        min-height: 560px;
      }

      .feature-chip {
        border-radius: 24px;
      }
    }

    @media (max-width: 576px) {
      .hero h1 {
        font-size: 2.2rem;
      }

      .hero p {
        font-size: 1.02rem;
      }

      .signup-btn,
      .hero-cta {
        width: 100%;
      }

      .nav-actions {
        width: 100%;
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="nav-shell">
      <a href="index.php" class="brand">
        <span class="brand-mark"><i class="fas fa-bus"></i></span>
        <span class="brand-text">
          <span class="brand-kicker">Bus Booking</span>
          <span class="brand-wordmark"><span class="mint">Swift</span>Pass</span>
        </span>
      </a>

      <ul class="nav-links">
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="#contact">Contact</a></li>
      </ul>

      <div class="nav-actions">
        <a href="login.php" class="login-link"><i class="fas fa-user me-1"></i>Login</a>
        <a href="register.php" class="signup-btn"><i class="fas fa-user-plus me-1"></i>Sign Up</a>
      </div>
    </div>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <h1>
        <span class="accent">Simple Bus Booking</span><br>
        Search Trips,<br>
        Pay, And Travel
      </h1>
      <p>
        SwiftPass is a bus booking system for passengers, drivers, and administrators. Search trips, book seats, pay, and manage transport records in one place.
      </p>
      <a href="#how-it-works" class="hero-cta"><i class="fas fa-ticket-alt"></i>Start Here</a>
    </div>
  </section>

  <section class="section-block light" id="how-it-works">
    <div class="section-shell">
      <div class="row align-items-center justify-content-between g-4 g-lg-5">
        <div class="col-lg-6">
          <div class="about-image"></div>
        </div>
        <div class="col-lg-5">
          <div class="section-copy">
            <span class="title-sm">How It Works</span>
            <div class="section-divider"></div>
            <h2>Simple booking from search to ticket</h2>
            <p>
              SwiftPass keeps the passenger journey clear. Search available trips, choose seats, make payment, and keep ticket details in one connected flow.
            </p>
            <a href="register.php" class="pill-link">Create account <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block light" id="contact">
    <div class="section-shell">
      <div class="contact-panel text-center">
        <span class="title-sm">Contact</span>
        <div class="section-divider mx-auto"></div>
        <div class="section-copy">
          <h2>Need help with SwiftPass?</h2>
          <p>
            Contact the team for support with booking, payment, tickets, driver access, or admin assistance.
          </p>
        </div>
        <div class="row justify-content-center g-3 mt-2">
          <div class="col-md-5">
            <div class="stack-card text-start">
              <div class="icon-pill"><i class="fas fa-envelope"></i></div>
              <h4>Email Support</h4>
              <p>support@swiftpass</p>
            </div>
          </div>
          <div class="col-md-5">
            <div class="stack-card text-start">
              <div class="icon-pill"><i class="fas fa-phone-alt"></i></div>
              <h4>Call Support</h4>
              <p>0780506643</p>
            </div>
          </div>
        </div>
        <div class="action-row d-flex flex-wrap justify-content-center gap-3 mt-4">
          <a href="mailto:support@swiftpass" class="signup-btn">Email Support</a>
        </div>
      </div>
    </div>
  </section>
</body>
</html>
