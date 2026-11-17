<?php
require __DIR__ . '/db.php';
session_start();
if (isset($_GET['logout'])) { session_destroy(); header('Location: librarian.php'); exit; }

$bad = false;
if (isset($_POST['u'])) {
  $r = one('SELECT id, pw, role FROM users WHERE username=?', [trim($_POST['u'])]);
  if ($r && password_verify((string)($_POST['p'] ?? ''), $r['pw']) && ($r['role'] === 'librarian' || $r['role'] === 'admin')) {
    session_regenerate_id(true); 
    $_SESSION['u'] = $r['id']; 
    header('Location: index.php'); 
    exit;
  }
  $bad = true;
}
?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ලයිබ්‍රේරියන් පිවිසුම</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>if(localStorage.getItem('theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
</head>
<body>
<div class="top">
  <div class="in">
    <div class="brand"><i class="fa-solid fa-user-tie"></i> ලයිබ්‍රේරියන් විශේෂ පිවිසුම</div>
    <button class="theme-btn" onclick="toggleTheme()"><i class="fa-solid fa-circle-half-stroke"></i></button>
  </div>
</div>

<div class="wrap" style="max-width:400px;margin-top:40px">
  <form method="post" class="card">
    <h2><i class="fa-solid fa-right-to-bracket"></i> ලයිබ්‍රේරියන් ඇතුළු වන්න</h2>
    <label>පරිශීලක නාමය</label><input name="u" autofocus autocomplete="username">
    <label>මුරපදය</label><input type="password" name="p" autocomplete="current-password">
    <?php if ($bad) echo '<p style="color:var(--warn)"><i class="fa-solid fa-triangle-exclamation"></i> වැරදි පරිශීලක නාමයක් හෝ මුරපදයක්, නැතහොත් ඔබට ලයිබ්‍රේරියන් අවසර නැත.</p>'; ?>
    <p><button class="btn" style="width:100%"><i class="fa-solid fa-arrow-right-to-bracket"></i> ඇතුළු වන්න</button></p>
    <p style="text-align:center;margin-top:15px"><a href="index.php" class="muted" style="font-size:14px"><i class="fa-solid fa-arrow-left"></i> සාමාන්‍ය පිවිසුම් පිටුවට යන්න</a></p>
  </form>
</div>

<script>
function toggleTheme(){
  const h=document.documentElement, c=h.getAttribute('data-theme'), n=c==='dark'?'light':'dark';
  h.setAttribute('data-theme', n); localStorage.setItem('theme', n);
}
</script>
</body>
</html>