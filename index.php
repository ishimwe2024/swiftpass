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

    .info-band {
      background: #fff;
      padding: 2.8rem 0 3.2rem;
      border-top: 1px solid rgba(255, 255, 255, 0.3);
    }

    .section-block {
      padding: 5rem 0;
      background: #fff;
      border-top: 1px solid var(--line);
    }

    .section-block.alt {
      background: #f9fbfc;
    }

    .section-shell {
      max-width: 1180px;
      margin: 0 auto;
      padding: 0 1.5rem;
    }

    .section-title {
      text-align: center;
      margin-bottom: 2.8rem;
    }

    .section-title span {
      display: inline-block;
      margin-bottom: 0.8rem;
      color: var(--blue-dark);
      font-family: 'Montserrat', sans-serif;
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .section-title h2 {
      margin: 0 0 0.9rem;
      font-family: 'Montserrat', sans-serif;
      font-size: clamp(2rem, 4vw, 3rem);
      font-weight: 800;
      color: var(--navy);
    }

    .section-title p {
      max-width: 720px;
      margin: 0 auto;
      color: var(--muted);
      font-size: 1.08rem;
      line-height: 1.6;
    }

    .steps-grid,
    .support-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1.5rem;
    }

    .step-card,
    .support-card {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 24px;
      padding: 1.7rem;
      box-shadow: 0 18px 34px rgba(34, 50, 71, 0.05);
    }

    .step-number,
    .card-icon {
      width: 56px;
      height: 56px;
      border-radius: 18px;
      display: grid;
      place-items: center;
      margin-bottom: 1rem;
      background: linear-gradient(135deg, var(--blue), var(--blue-dark));
      color: white;
      font-family: 'Montserrat', sans-serif;
      font-weight: 800;
      font-size: 1.1rem;
    }

    .step-card h3,
    .support-card h3 {
      margin: 0 0 0.75rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--navy);
    }

    .step-card p,
    .support-card p {
      margin: 0;
      color: var(--muted);
      line-height: 1.6;
      font-size: 1rem;
    }

    .find-savings {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 2rem;
      align-items: center;
    }

    .find-panel {
      background: linear-gradient(135deg, #133764, #1f5da5);
      color: white;
      border-radius: 28px;
      padding: 2.2rem;
      box-shadow: 0 22px 40px rgba(29, 48, 69, 0.18);
    }

    .find-panel h3 {
      margin: 0 0 1rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 2rem;
      font-weight: 800;
    }

    .find-panel p {
      margin: 0 0 1.4rem;
      color: rgba(255, 255, 255, 0.88);
      line-height: 1.7;
    }

    .find-list {
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 0.9rem;
    }

    .find-list li {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      padding: 0.9rem 1rem;
    }

    .find-cta {
      display: grid;
      gap: 1rem;
    }

    .cta-tile {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 18px 34px rgba(34, 50, 71, 0.05);
    }

    .cta-tile h4 {
      margin: 0 0 0.7rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--navy);
    }

    .cta-tile p {
      margin: 0 0 1rem;
      color: var(--muted);
      line-height: 1.6;
    }

    .cta-tile a {
      color: var(--blue-dark);
      font-family: 'Montserrat', sans-serif;
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
    }

    .support-layout {
      display: grid;
      grid-template-columns: 0.95fr 1.05fr;
      gap: 2rem;
      align-items: stretch;
    }

    .support-panel {
      background: linear-gradient(160deg, #0f2d57, #1c5ba2);
      color: white;
      border-radius: 32px;
      padding: 2.2rem;
      box-shadow: 0 24px 46px rgba(18, 60, 120, 0.18);
      position: relative;
      overflow: hidden;
    }

    .support-panel::after {
      content: "";
      position: absolute;
      width: 220px;
      height: 220px;
      right: -70px;
      bottom: -70px;
      border-radius: 50%;
      background: rgba(143, 208, 255, 0.16);
    }

    .support-panel h3 {
      position: relative;
      margin: 0 0 0.9rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 2rem;
      font-weight: 800;
    }

    .support-panel p {
      position: relative;
      margin: 0 0 1.4rem;
      color: rgba(255, 255, 255, 0.9);
      line-height: 1.7;
      max-width: 420px;
    }

    .support-points {
      position: relative;
      margin: 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 0.85rem;
    }

    .support-points li {
      display: flex;
      align-items: flex-start;
      gap: 0.8rem;
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      padding: 0.9rem 1rem;
    }

    .support-points i {
      color: #8fd0ff;
      margin-top: 0.2rem;
    }

    .tool-showcase {
      display: grid;
      grid-template-columns: 1.15fr 0.85fr;
      gap: 2rem;
      align-items: start;
    }

    .tool-board {
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(234, 244, 255, 0.98));
      border: 1px solid var(--line);
      border-radius: 30px;
      padding: 1.8rem;
      box-shadow: 0 22px 38px rgba(34, 50, 71, 0.06);
    }

    .tool-board-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1.4rem;
    }

    .tool-board-header h3 {
      margin: 0;
      font-family: 'Montserrat', sans-serif;
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--navy);
    }

    .tool-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.45rem 0.8rem;
      border-radius: 999px;
      background: #dff0ff;
      color: var(--blue-deep);
      font-family: 'Montserrat', sans-serif;
      font-size: 0.74rem;
      font-weight: 700;
      text-transform: uppercase;
    }

    .tool-rows {
      display: grid;
      gap: 1rem;
    }

    .tool-row {
      display: grid;
      grid-template-columns: 52px 1fr auto;
      gap: 1rem;
      align-items: center;
      background: white;
      border: 1px solid #e3edf7;
      border-radius: 20px;
      padding: 1rem 1.1rem;
    }

    .tool-row-icon {
      width: 52px;
      height: 52px;
      border-radius: 16px;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, #d9eeff, #8fd0ff);
      color: var(--blue-deep);
      font-size: 1.1rem;
    }

    .tool-row h4 {
      margin: 0 0 0.2rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 1.02rem;
      font-weight: 700;
      color: var(--navy);
    }

    .tool-row p {
      margin: 0;
      color: var(--muted);
      line-height: 1.5;
    }

    .tool-status {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--blue-dark);
      background: #e8f4ff;
      padding: 0.5rem 0.8rem;
      border-radius: 999px;
    }

    .tips-stack {
      display: grid;
      gap: 1rem;
    }

    .tip-note {
      background: #123c78;
      color: white;
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: 0 20px 36px rgba(18, 60, 120, 0.18);
    }

    .tip-note.light {
      background: white;
      color: var(--text);
      border: 1px solid var(--line);
      box-shadow: 0 18px 34px rgba(34, 50, 71, 0.05);
    }

    .tip-note h4 {
      margin: 0 0 0.7rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 1.12rem;
      font-weight: 700;
    }

    .tip-note p {
      margin: 0;
      line-height: 1.6;
      color: inherit;
      opacity: 0.92;
    }

    .info-grid {
      max-width: 1180px;
      margin: 0 auto;
      padding: 0 1.5rem;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 2rem;
    }

    .info-card {
      text-align: center;
      padding: 0.5rem 1rem;
    }

    .info-card h3 {
      margin: 0 0 0.8rem;
      font-family: 'Montserrat', sans-serif;
      font-size: 1.55rem;
      font-weight: 700;
      color: #1f2e41;
    }

    .info-card p {
      margin: 0 auto 1.1rem;
      max-width: 280px;
      color: var(--text);
      line-height: 1.5;
      font-size: 1rem;
    }

    .meter {
      width: 72px;
      height: 72px;
      margin: 0 auto;
      border-radius: 50%;
      position: relative;
      display: grid;
      place-items: center;
      font-family: 'Montserrat', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      color: #7f8a96;
      background: #fff;
    }

    .meter::before {
      content: "";
      position: absolute;
      inset: 0;
      border-radius: 50%;
      background: conic-gradient(var(--blue) 0 210deg, #e6eaee 210deg 360deg);
      -webkit-mask: radial-gradient(circle at center, transparent 56%, #000 58%);
      mask: radial-gradient(circle at center, transparent 56%, #000 58%);
    }

    .meter.orange::before {
      background: conic-gradient(#6ab8ff 0 130deg, #2e84e6 130deg 250deg, #e6eaee 250deg 360deg);
    }

    .meter.score::before {
      background: conic-gradient(#1f5da5 0 65deg, #3ea3ff 65deg 145deg, #8fd0ff 145deg 270deg, #e6eaee 270deg 360deg);
    }

    .meter span {
      position: relative;
      z-index: 1;
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

      .info-grid,
      .steps-grid,
      .support-grid,
      .find-savings,
      .support-layout,
      .tool-showcase {
        grid-template-columns: 1fr;
        gap: 1.5rem;
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
        <li><a href="#find-savings">Passenger Booking</a></li>
        <li><a href="#support">Driver &amp; Admin</a></li>
        <li><a href="#tools-tips">System Features</a></li>
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

  <section class="info-band">
    <div class="info-grid">
      <div class="info-card">
        <h3>Find a Trip</h3>
        <p>Choose your route, travel date, and available trip.</p>
        <div class="meter"><span>7</span></div>
      </div>

      <div class="info-card">
        <h3>Book and Pay</h3>
        <p>Book your seat and complete payment in one process.</p>
        <div class="meter orange"><span></span></div>
      </div>

      <div class="info-card">
        <h3>Get Your Ticket</h3>
        <p>View your ticket details after payment is completed.</p>
        <div class="meter score"><span>748</span></div>
      </div>
    </div>
  </section>

  <section class="section-block" id="how-it-works">
    <div class="section-shell">
      <div class="section-title">
        <span>How It Works</span>
        <h2>How passengers use the system</h2>
        <p>
          The booking process is simple and direct from trip search to ticket.
        </p>
      </div>

      <div class="steps-grid">
        <div class="step-card">
          <div class="step-number">1</div>
          <h3>Search trips</h3>
          <p>Choose departure, destination, and travel date to see available trips.</p>
        </div>

        <div class="step-card">
          <div class="step-number">2</div>
          <h3>Pay and confirm</h3>
          <p>Choose a payment method, confirm the booking, and complete payment.</p>
        </div>

        <div class="step-card">
          <div class="step-number">3</div>
          <h3>Receive your ticket</h3>
          <p>After payment, the system shows your ticket details for travel.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block alt" id="find-savings">
    <div class="section-shell">
      <div class="section-title">
        <span>Passenger Booking</span>
        <h2>Passenger booking made clear</h2>
        <p>
          Passengers can move from trip search to payment and ticket without confusion.
        </p>
      </div>

      <div class="find-savings">
        <div class="find-panel">
          <h3>Everything needed for booking is connected</h3>
          <p>
            Trip search, seat booking, payment, and ticket details work together in one flow.
          </p>
          <ul class="find-list">
            <li>Search trips by route and date.</li>
            <li>Book seats and continue to payment.</li>
            <li>Keep ticket details after payment.</li>
          </ul>
        </div>

        <div class="find-cta">
          <div class="cta-tile">
            <h4>Create Account</h4>
            <p>Register as a passenger to start using the system.</p>
            <a href="register.php">Create Account</a>
          </div>

          <div class="cta-tile">
            <h4>Search and Book</h4>
            <p>Find trips, choose seats, and continue to payment.</p>
            <a href="login.php">Passenger Login</a>
          </div>

          <div class="cta-tile">
            <h4>View Ticket</h4>
            <p>After payment, ticket details remain available for confirmation.</p>
            <a href="login.php">View System</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block" id="support">
    <div class="section-shell">
      <div class="section-title">
        <span>Driver &amp; Admin</span>
        <h2>Driver and admin access</h2>
        <p>
          Drivers and administrators each have their own pages and tools inside the system.
        </p>
      </div>

      <div class="support-layout">
        <div class="support-panel">
          <h3>Each role has its own workspace</h3>
          <p>
            Drivers and admins use different dashboards, but all records stay connected.
          </p>
          <ul class="support-points">
            <li>
              <i class="fas fa-check-circle"></i>
              <span>Drivers can log in and view their dashboard.</span>
            </li>
            <li>
              <i class="fas fa-check-circle"></i>
              <span>Admins can manage routes, users, bookings, and payments.</span>
            </li>
            <li>
              <i class="fas fa-check-circle"></i>
              <span>Each role sees the tools that match its work.</span>
            </li>
          </ul>
        </div>

        <div class="support-grid">
          <div class="support-card">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <h3>Driver Dashboard</h3>
            <p>Drivers use a dedicated login and dashboard.</p>
          </div>

          <div class="support-card">
            <div class="card-icon"><i class="fas fa-id-card"></i></div>
            <h3>Admin Dashboard</h3>
            <p>Admins manage routes, drivers, users, bookings, and payments.</p>
          </div>

          <div class="support-card">
            <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
            <h3>System Settings</h3>
            <p>Settings help manage account details and system information.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block" id="contact">
    <div class="section-shell">
      <div class="section-title">
        <span>Contact</span>
        <h2>Get in touch with SwiftPass</h2>
        <p>
          If you need help with booking, payment, tickets, driver access, or admin support, use the contact details below.
        </p>
      </div>

      <div class="row g-4 justify-content-center">
        <div class="col-md-10 col-lg-8">
          <div class="cta-tile text-center">
            <h4>Contact Information</h4>
            <p class="mb-2"><strong>Email:</strong>SwiftPass@gmail.com</p>
            <p class="mb-2"><strong>Phone:</strong> +250 7857432</p>
            <p class="mb-0"><strong>Location:</strong> Nyabugogo, Kigali, Rwanda</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section-block alt" id="tools-tips">
    <div class="section-shell">
      <div class="section-title">
        <span>System Features</span>
        <h2>Main parts of the system</h2>
        <p>
          These are the key areas already available in SwiftPass.
        </p>
      </div>

      <div class="tool-showcase">
        <div class="tool-board">
          <div class="tool-board-header">
            <h3>System overview</h3>
            <div class="tool-badge"><i class="fas fa-bolt"></i>Daily flow</div>
          </div>

          <div class="tool-rows">
            <div class="tool-row">
              <div class="tool-row-icon"><i class="fas fa-map-signs"></i></div>
              <div>
                <h4>Routes and Trips</h4>
                <p>Manage routes, destinations, and trip schedules.</p>
              </div>
              <div class="tool-status">Essential</div>
            </div>

            <div class="tool-row">
              <div class="tool-row-icon"><i class="fas fa-money-check-alt"></i></div>
              <div>
                <h4>Bookings and Payments</h4>
                <p>Review booking details, payments, and ticket records.</p>
              </div>
              <div class="tool-status">Active</div>
            </div>

            <div class="tool-row">
              <div class="tool-row-icon"><i class="fas fa-cogs"></i></div>
              <div>
                <h4>Drivers and Users</h4>
                <p>Keep driver and user records in one connected platform.</p>
              </div>
              <div class="tool-status">Stable</div>
            </div>
          </div>
        </div>

        <div class="tips-stack">
          <div class="tip-note">
            <h4>Easy to use</h4>
            <p>Passengers should quickly understand how to search, pay, and get a ticket.</p>
          </div>

          <div class="tip-note light">
            <h4>Connected data</h4>
            <p>Bookings, payments, tickets, and trip details should stay linked together.</p>
          </div>

          <div class="tip-note light">
            <h4>Clear management</h4>
            <p>Important admin actions should stay visible and easy to review.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</body>
</html>
