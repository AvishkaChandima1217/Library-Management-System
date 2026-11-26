<?php
require __DIR__ . '/db.php';
session_start();

function redirectByRoleIndex(string $role): void {
  if ($role === 'admin') {
    header('Location: admin.php');
    exit;
  }
  if ($role === 'librarian') {
    header('Location: librarian.php');
    exit;
  }
  header('Location: member.php');
  exit;
}

if (!empty($_SESSION['u'])) {
  $curr = one('SELECT id, role FROM users WHERE id = ?', [(int)$_SESSION['u']]);
  if ($curr) {
    redirectByRoleIndex((string)$curr['role']);
  } else {
    session_destroy();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Welcome to Warakapola Pradeshiya Sabha Library</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  * {
    box-sizing: border-box;
  }
  html, body {
    margin: 0;
    padding: 0;
    min-height: 100vh;
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    overflow: hidden;
  }

  /* Full-screen rich scenic backdrop */
  .portal-bg {
    position: relative;
    min-height: 100vh;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    background:
      linear-gradient(180deg, rgba(16, 68, 145, 0.52) 0%, rgba(28, 108, 205, 0.25) 45%, rgba(12, 34, 74, 0.72) 100%),
      linear-gradient(90deg, rgba(16, 42, 24, 0.45) 0%, rgba(36, 118, 214, 0.15) 50%, rgba(16, 36, 68, 0.55) 100%),
      url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=1800&q=80') center/cover no-repeat fixed;
    transition: opacity 0.65s ease, transform 0.65s ease;
  }

  .portal-bg.leaving {
    opacity: 0;
    transform: scale(1.03);
  }

  /* Left books & Right building visual layers */
  .scenic-left {
    position: fixed;
    left: -30px;
    bottom: 64px;
    width: min(36vw, 440px);
    height: min(56vh, 430px);
    background:
      linear-gradient(90deg, rgba(10, 25, 15, 0.15), rgba(10, 25, 15, 0.65)),
      url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=1000&q=80') center/cover no-repeat;
    border-radius: 0 28px 28px 0;
    box-shadow: 12px 16px 36px rgba(0, 0, 0, 0.42);
    opacity: 0.92;
    pointer-events: none;
    z-index: 1;
  }

  .scenic-right {
    position: fixed;
    right: -24px;
    bottom: 64px;
    width: min(36vw, 450px);
    height: min(58vh, 450px);
    background:
      linear-gradient(270deg, rgba(12, 26, 54, 0.18), rgba(12, 26, 54, 0.68)),
      url('https://images.unsplash.com/photo-1564981797816-1043664bf78d?auto=format&fit=crop&w=1000&q=80') center/cover no-repeat;
    border-radius: 28px 0 0 28px;
    box-shadow: -12px 16px 36px rgba(0, 0, 0, 0.42);
    opacity: 0.9;
    pointer-events: none;
    z-index: 1;
  }

  /* Center Splash Animation Content */
  .portal-main {
    position: relative;
    z-index: 3;
    width: 100%;
    max-width: 820px;
    margin: auto;
    padding: 28px 16px 80px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }

  /* Emblem Badge */
  .crest-wrap {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, #fde047, #eab308 62%, #b45309 100%);
    border: 3px solid #fef08a;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4), inset 0 0 0 3px #0f2c59;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    animation: crestPopIn 0.85s cubic-bezier(0.22, 1, 0.36, 1) both, floatCrest 3s ease-in-out 0.85s infinite;
  }
  .crest-inner {
    width: 74px;
    height: 74px;
    border-radius: 50%;
    background: linear-gradient(180deg, #38bdf8 0%, #22c55e 68%, #0284c7 100%);
    border: 2px solid #0f2c59;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 28px;
    text-shadow: 0 2px 6px rgba(0,0,0,0.45);
  }

  /* Hero Title Block */
  .hero-header {
    text-align: center;
    color: #ffffff;
    text-shadow: 0 4px 18px rgba(6, 22, 54, 0.68);
    animation: heroSlideUp 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
  }
  .welcome-script-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-bottom: 2px;
  }
  .welcome-script-row::before,
  .welcome-script-row::after {
    content: "";
    width: 78px;
    height: 1.5px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8));
  }
  .welcome-script-row::after {
    background: linear-gradient(270deg, transparent, rgba(255,255,255,0.8));
  }
  .welcome-script {
    font-family: 'Great Vibes', cursive;
    font-size: clamp(36px, 5.4vw, 48px);
    font-weight: 400;
    color: #ffffff;
    line-height: 1.1;
  }
  .hero-title {
    margin: 4px 0 10px 0;
    font-size: clamp(26px, 4.6vw, 44px);
    font-weight: 800;
    letter-spacing: -0.3px;
    line-height: 1.18;
    color: #ffffff;
  }
  .hero-title span.gold-word {
    color: #facc15;
    text-shadow: 0 3px 16px rgba(0, 0, 0, 0.6);
  }
  .tagline-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    font-family: 'Georgia', 'Times New Roman', serif;
    font-style: italic;
    font-size: clamp(17px, 2.5vw, 23px);
    color: #f8fafc;
  }
  .tagline-row::before,
  .tagline-row::after {
    content: "";
    width: 86px;
    height: 1.5px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.7));
  }
  .tagline-row::after {
    background: linear-gradient(270deg, transparent, rgba(255,255,255,0.7));
  }

  /* Subtle Loading Progress Bar */
  .loader-track {
    margin-top: 28px;
    width: min(260px, 70vw);
    height: 4px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.25);
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    animation: heroSlideUp 0.9s ease 0.3s both;
  }
  .loader-fill {
    width: 0%;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #facc15, #ffffff);
    animation: loadProgress 2.1s ease-in-out forwards;
  }

  /* Bottom Curved Gold + Navy Bar */
  .bottom-curve-bar {
    position: relative;
    z-index: 4;
    width: 100%;
    margin-top: auto;
  }
  .gold-wave {
    width: 100%;
    height: 16px;
    background: linear-gradient(90deg, #eab308, #facc15 50%, #eab308);
    border-radius: 65% 65% 0 0 / 100% 100% 0 0;
  }
  .navy-footer {
    width: 100%;
    background: linear-gradient(90deg, #051d40 0%, #082b5c 50%, #051d40 100%);
    color: #f1f5f9;
    padding: 13px 18px 15px 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 13px;
    letter-spacing: 0.2px;
    text-align: center;
  }
  .navy-footer .sep {
    color: #facc15;
    font-weight: 700;
  }

  @keyframes crestPopIn {
    0% { opacity: 0; transform: scale(0.6) translateY(-16px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
  }
  @keyframes heroSlideUp {
    0% { opacity: 0; transform: translateY(18px); }
    100% { opacity: 1; transform: translateY(0); }
  }
  @keyframes floatCrest {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-5px); }
  }
  @keyframes loadProgress {
    0% { width: 0%; }
    100% { width: 100%; }
  }

  @media (max-width: 768px) {
    .scenic-left, .scenic-right {
      display: none;
    }
    .welcome-script-row::before,
    .welcome-script-row::after,
    .tagline-row::before,
    .tagline-row::after {
      width: 28px;
    }
  }
</style>
</head>
<body>
<div id="portalBg" class="portal-bg">
  <div class="scenic-left" aria-hidden="true"></div>
  <div class="scenic-right" aria-hidden="true"></div>

  <main class="portal-main">
    <div class="crest-wrap" aria-hidden="true">
      <div class="crest-inner">
        <i class="fa-solid fa-mountain-sun"></i>
      </div>
    </div>

    <header class="hero-header">
      <div class="welcome-script-row">
        <div class="welcome-script">Welcome to</div>
      </div>
      <h1 class="hero-title">Warakapola Pradeshiya Sabha <span class="gold-word">Library</span></h1>
      <div class="tagline-row">
        <span>Read &bull; Learn &bull; Grow</span>
      </div>
    </header>

    <div class="loader-track" aria-hidden="true">
      <div class="loader-fill"></div>
    </div>
  </main>

  <footer class="bottom-curve-bar">
    <div class="gold-wave"></div>
    <div class="navy-footer">
      <span><i class="fa-solid fa-location-dot" style="color:#facc15;margin-right:5px;"></i> Location - Warakapola Pradeshiya Sabha - Warakapola</span>
      <span class="sep">/</span>
      <span><i class="fa-solid fa-envelope" style="color:#facc15;margin-right:5px;"></i> Email - warakapolaps@gmail.com</span>
      <span class="sep">/</span>
      <span><i class="fa-solid fa-phone" style="color:#facc15;margin-right:5px;"></i> Phone Number - 0352267275</span>
    </div>
  </footer>
</div>

<script>
window.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    var bg = document.getElementById('portalBg');
    if (bg) bg.classList.add('leaving');
  }, 1750);

  setTimeout(function() {
    window.location.replace('login.php');
  }, 2300);
});
</script>
</body>
</html>