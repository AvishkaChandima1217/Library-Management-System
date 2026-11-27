<?php
require __DIR__ . '/db.php';
session_start();

if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: login.php');
  exit;
}

function redirectByRole(string $role): void {
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
    redirectByRole((string)$curr['role']);
  } else {
    session_destroy();
  }
}

$bad = false;
if (isset($_POST['u'])) {
  $uInput = trim($_POST['u']);
  $pInput = (string)($_POST['p'] ?? '');

  $r = one(
    'SELECT id, pw, role FROM users WHERE username = ? OR email = ? LIMIT 1',
    [$uInput, $uInput]
  );

  if ($r && password_verify($pInput, (string)$r['pw'])) {
    session_regenerate_id(true);
    $_SESSION['u'] = (int)$r['id'];
    $_SESSION['role'] = (string)$r['role'];
    redirectByRole((string)$r['role']);
  } else {
    $bad = true;
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
    overflow-x: hidden;
  }

  /* Full-screen backdrop with continuous live motion */
  .portal-bg {
    position: relative;
    min-height: 100vh;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    overflow: hidden;
  }

  .portal-bg::before {
    content: "";
    position: fixed;
    inset: -32px;
    background:
      linear-gradient(180deg, rgba(16, 68, 145, 0.22) 0%, rgba(28, 108, 205, 0.08) 45%, rgba(12, 34, 74, 0.38) 100%),
      url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=1800&q=85') center/cover no-repeat;
    z-index: 0;
    pointer-events: none;
    animation: bgSlowPanZoom 28s ease-in-out infinite alternate;
    will-change: transform;
  }

  /* Continuous soft ambient light pulse in background */
  .bg-ambient-glow {
    position: fixed;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    background:
      radial-gradient(circle at 22% 28%, rgba(250, 204, 21, 0.16), transparent 42%),
      radial-gradient(circle at 78% 65%, rgba(56, 189, 248, 0.18), transparent 46%);
    animation: ambientPulse 7s ease-in-out infinite alternate;
    will-change: opacity, transform;
  }

  /* Floating particles layer behind login card */
  .bg-particles {
    position: fixed;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    overflow: hidden;
  }

  .bg-particle {
    position: absolute;
    bottom: -24px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(254, 240, 138, 0.95) 0%, rgba(250, 204, 21, 0.35) 55%, transparent 100%);
    box-shadow: 0 0 10px rgba(250, 204, 21, 0.55);
    opacity: 0;
    animation: riseParticle var(--dur, 12s) linear var(--delay, 0s) infinite;
    will-change: transform, opacity;
  }

  /* Center Portal Content */
  .portal-main {
    position: relative;
    z-index: 3;
    width: 100%;
    max-width: 780px;
    padding: 24px 16px 110px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  /* Emblem Badge */
  .crest-wrap {
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: radial-gradient(circle at 35% 30%, #fde047, #eab308 62%, #b45309 100%);
    border: 3px solid #fef08a;
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.35), inset 0 0 0 3px #0f2c59;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    animation: crestPop 0.75s cubic-bezier(0.22, 1, 0.36, 1) both, floatCrest 3.4s ease-in-out 0.75s infinite;
  }
  .crest-inner {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(180deg, #38bdf8 0%, #22c55e 68%, #0284c7 100%);
    border: 2px solid #0f2c59;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 24px;
    text-shadow: 0 2px 6px rgba(0,0,0,0.45);
  }

  /* Hero Title Block */
  .hero-header {
    text-align: center;
    color: #ffffff;
    text-shadow: 0 4px 18px rgba(6, 22, 54, 0.75);
    margin-bottom: 34px;
    animation: heroFadeIn 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both;
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
    width: 72px;
    height: 1.5px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8));
    animation: lineExpand 0.9s ease-out 0.2s both;
  }
  .welcome-script-row::after {
    background: linear-gradient(270deg, transparent, rgba(255,255,255,0.8));
  }
  .welcome-script {
    font-family: 'Great Vibes', cursive;
    font-size: clamp(32px, 5vw, 44px);
    font-weight: 400;
    color: #ffffff;
    line-height: 1.1;
  }
  .hero-title {
    margin: 2px 0 8px 0;
    font-size: clamp(24px, 4.3vw, 41px);
    font-weight: 800;
    letter-spacing: -0.3px;
    line-height: 1.18;
    color: #ffffff;
  }
  .hero-title span.gold-word {
    color: #facc15;
    text-shadow: 0 3px 14px rgba(0, 0, 0, 0.65);
    display: inline-block;
    animation: goldShimmer 4s ease-in-out infinite;
  }
  .tagline-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    font-family: 'Georgia', 'Times New Roman', serif;
    font-style: italic;
    font-size: clamp(16px, 2.4vw, 22px);
    color: #f8fafc;
  }
  .tagline-row::before,
  .tagline-row::after {
    content: "";
    width: 82px;
    height: 1.5px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.7));
    animation: lineExpand 0.9s ease-out 0.3s both;
  }
  .tagline-row::after {
    background: linear-gradient(270deg, transparent, rgba(255,255,255,0.7));
  }

  /* Login Card */
  .login-card-wrap {
    width: 100%;
    max-width: 390px;
    position: relative;
    padding-top: 34px;
    opacity: <?= $bad ? '1' : '0' ?>;
    transform: translateY(<?= $bad ? '0' : '24px' ?>) scale(<?= $bad ? '1' : '0.97' ?>);
    transition: opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1), transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
  }
  .login-card-wrap.show {
    opacity: 1;
    transform: translateY(0) scale(1);
  }

  .top-book-badge {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: linear-gradient(145deg, #0c3b7a, #062149);
    border: 4px solid #ffffff;
    box-shadow: 0 8px 20px rgba(6, 33, 73, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 26px;
    z-index: 4;
    animation: badgeBob 3.6s ease-in-out infinite;
  }

  .login-card {
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(6px);
    border-radius: 20px;
    padding: 44px 26px 24px 26px;
    box-shadow: 0 20px 45px rgba(4, 20, 48, 0.38);
    border: 1px solid rgba(255, 255, 255, 0.85);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }
  .login-card-wrap.show .login-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 24px 52px rgba(4, 20, 48, 0.45);
  }

  .card-heading {
    margin: 0 0 16px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: center;
    font-size: 19px;
    font-weight: 800;
    color: #082b5c;
  }

  .field-box {
    position: relative;
    margin-bottom: 13px;
  }
  .field-icon-left {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #0a2e63;
    font-size: 15px;
    pointer-events: none;
    transition: transform 0.2s ease, color 0.2s ease;
  }
  .field-input {
    width: 100%;
    height: 44px;
    padding: 0 42px 0 40px;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    background: #ffffff;
    color: #0f172a;
    font-size: 14.5px;
    font-weight: 500;
    outline: none;
    transition: border-color 0.22s ease, box-shadow 0.22s ease, transform 0.2s ease;
  }
  .field-input:focus {
    border-color: #0c3b7a;
    box-shadow: 0 0 0 4px rgba(12, 59, 122, 0.16);
    transform: translateY(-1px);
  }
  .field-box:focus-within .field-icon-left {
    color: #eab308;
    transform: translateY(-50%) scale(1.1);
  }
  .field-input::placeholder {
    color: #64748b;
    font-weight: 400;
  }

  .eye-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #475569;
    cursor: pointer;
    padding: 4px 6px;
    font-size: 15px;
    transition: color 0.2s ease, transform 0.2s ease;
  }
  .eye-toggle:hover {
    color: #0a2e63;
    transform: translateY(-50%) scale(1.1);
  }

  .login-submit-btn {
    position: relative;
    overflow: hidden;
    width: 100%;
    height: 46px;
    margin-top: 4px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(180deg, #0b3c7c 0%, #062552 100%);
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.2px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    cursor: pointer;
    box-shadow: 0 8px 18px rgba(6, 37, 82, 0.32);
    transition: transform 0.18s ease, filter 0.2s ease, box-shadow 0.2s ease;
  }
  .login-submit-btn::after {
    content: "";
    position: absolute;
    top: 0;
    left: -120%;
    width: 65%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.28), transparent);
    transform: skewX(-22deg);
    transition: left 0.55s ease;
  }
  .login-submit-btn:hover {
    filter: brightness(1.07);
    box-shadow: 0 11px 22px rgba(6, 37, 82, 0.4);
  }
  .login-submit-btn:hover::after {
    left: 140%;
  }
  .login-submit-btn:active {
    transform: translateY(1px) scale(0.99);
  }

  .err-msg {
    margin: 0 0 12px 0;
    padding: 8px 12px;
    border-radius: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    font-size: 12.5px;
    font-weight: 600;
    text-align: center;
    animation: shakeError 0.42s ease;
  }

  .card-footer-note {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
    text-align: center;
    font-family: 'Georgia', 'Times New Roman', serif;
    font-style: italic;
    font-size: 13px;
    color: #334155;
  }

  /* Bottom Curved Gold + Navy Bar */
  .bottom-curve-bar {
    position: relative;
    z-index: 4;
    width: 100%;
    margin-top: auto;
    animation: footerSlideUp 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
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

  @keyframes bgSlowPanZoom {
    0%   { transform: scale(1) translate3d(0, 0, 0); }
    50%  { transform: scale(1.06) translate3d(-14px, -6px, 0); }
    100% { transform: scale(1.09) translate3d(12px, -10px, 0); }
  }
  @keyframes ambientPulse {
    0%   { opacity: 0.65; transform: scale(1); }
    100% { opacity: 1; transform: scale(1.06); }
  }
  @keyframes riseParticle {
    0% {
      transform: translate3d(0, 0, 0) scale(0.6);
      opacity: 0;
    }
    15% {
      opacity: var(--max-op, 0.75);
    }
    85% {
      opacity: var(--max-op, 0.75);
    }
    100% {
      transform: translate3d(var(--drift-x, 28px), -108vh, 0) scale(1.15);
      opacity: 0;
    }
  }
  @keyframes crestPop {
    from { opacity: 0; transform: scale(0.75) translateY(-10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
  }
  @keyframes heroFadeIn {
    from { opacity: 0; transform: translateY(-14px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  @keyframes lineExpand {
    from { transform: scaleX(0); opacity: 0; }
    to   { transform: scaleX(1); opacity: 1; }
  }
  @keyframes goldShimmer {
    0%, 100% { filter: brightness(1); }
    50%      { filter: brightness(1.18) drop-shadow(0 0 8px rgba(250, 204, 21, 0.45)); }
  }
  @keyframes floatCrest {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-5px); }
  }
  @keyframes badgeBob {
    0%, 100% { transform: translateX(-50%) translateY(0); }
    50%      { transform: translateX(-50%) translateY(-4px); }
  }
  @keyframes shakeError {
    0%, 100% { transform: translateX(0); }
    20%      { transform: translateX(-6px); }
    40%      { transform: translateX(6px); }
    60%      { transform: translateX(-4px); }
    80%      { transform: translateX(4px); }
  }
  @keyframes footerSlideUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
      animation: none !important;
      transition: none !important;
    }
    .login-card-wrap {
      opacity: 1 !important;
      transform: none !important;
    }
  }

  @media (max-width: 768px) {
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
<div class="portal-bg">
  <div class="bg-ambient-glow" aria-hidden="true"></div>
  <div id="bgParticles" class="bg-particles" aria-hidden="true"></div>

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

    <section id="loginCardWrap" class="login-card-wrap <?= $bad ? 'show' : '' ?>">
      <div class="top-book-badge" aria-hidden="true">
        <i class="fa-solid fa-book-open"></i>
      </div>

      <form method="post" class="login-card" autocomplete="on">
        <h2 class="card-heading">Library Management System</h2>

        <?php if ($bad): ?>
          <div class="err-msg">
            <i class="fa-solid fa-circle-exclamation"></i> Invalid username or password
          </div>
        <?php endif; ?>

        <div class="field-box">
          <i class="fa-solid fa-user field-icon-left"></i>
          <input
            type="text"
            name="u"
            class="field-input"
            placeholder="Username"
            autofocus
            autocomplete="username"
            required
          >
        </div>

        <div class="field-box">
          <i class="fa-solid fa-lock field-icon-left"></i>
          <input
            type="password"
            id="pwInput"
            name="p"
            class="field-input"
            placeholder="Password"
            autocomplete="current-password"
            required
          >
          <button type="button" class="eye-toggle" onclick="togglePw()" aria-label="Toggle password visibility">
            <i id="pwEyeIcon" class="fa-solid fa-eye-slash"></i>
          </button>
        </div>

        <button type="submit" class="login-submit-btn">
          <i class="fa-solid fa-right-to-bracket"></i>
          <span>Login</span>
        </button>

        <div class="card-footer-note">Your Knowledge Partner</div>
      </form>
    </section>
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
function togglePw() {
  var inp = document.getElementById('pwInput');
  var ico = document.getElementById('pwEyeIcon');
  if (!inp || !ico) return;
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.classList.remove('fa-eye-slash');
    ico.classList.add('fa-eye');
  } else {
    inp.type = 'password';
    ico.classList.remove('fa-eye');
    ico.classList.add('fa-eye-slash');
  }
}

(function createBgParticles() {
  var container = document.getElementById('bgParticles');
  if (!container) return;
  var count = 18;
  for (var i = 0; i < count; i++) {
    var dot = document.createElement('span');
    dot.className = 'bg-particle';
    var size = 5 + (i % 5) * 2.5;
    var leftPct = ((i * 5.7) % 96) + 2;
    var durSec = 10 + (i % 6) * 2.5;
    var delaySec = -((i * 1.4) % durSec);
    var driftPx = (i % 2 === 0 ? 1 : -1) * (18 + (i % 4) * 10);
    var maxOp = (0.4 + (i % 4) * 0.12).toFixed(2);

    dot.style.width = size + 'px';
    dot.style.height = size + 'px';
    dot.style.left = leftPct + '%';
    dot.style.setProperty('--dur', durSec + 's');
    dot.style.setProperty('--delay', delaySec + 's');
    dot.style.setProperty('--drift-x', driftPx + 'px');
    dot.style.setProperty('--max-op', maxOp);
    container.appendChild(dot);
  }
})();

window.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    var card = document.getElementById('loginCardWrap');
    if (card) card.classList.add('show');
  }, 450);
});
</script>
</body>
</html>