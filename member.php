<?php
require __DIR__ . '/db.php';
session_start();

if (isset($_GET['logout'])) {
  session_destroy();
  header('Location: member.php');
  exit;
}

$bad = false;
$notRegisteredMsg = '';

if (isset($_POST['u'])) {
  $uInput = trim($_POST['u']);
  $pInput = (string)($_POST['p'] ?? '');

  $r = one(
    'SELECT id, pw, role, library_id FROM users WHERE username = ? OR email = ? LIMIT 1',
    [$uInput, $uInput]
  );

  if ($r && password_verify($pInput, $r['pw'])) {
    if ($r['role'] !== 'member' || empty($r['library_id'])) {
      $notRegisteredMsg = 'මෙම පිවිසුම ලයිබ්‍රේරියන් විසින් ලියාපදිංචි කළ සාමාජිකයන් සඳහා පමණි.';
    } else {
      session_regenerate_id(true);
      $_SESSION['u'] = (int)$r['id'];
      $_SESSION['member_lib_id'] = (int)$r['library_id'];
      header('Location: member.php');
      exit;
    }
  } else {
    $bad = true;
  }
}

$me = null;
if (!empty($_SESSION['u'])) {
  $candidate = one(
    'SELECT u.*, l.name AS lib_name
     FROM users u
     LEFT JOIN libraries l ON l.id = u.library_id
     WHERE u.id = ? AND u.role = "member" AND u.library_id IS NOT NULL',
    [(int)$_SESSION['u']]
  );
  if ($candidate) {
    $me = $candidate;
  } else {
    session_destroy();
  }
}

if (!$me) { ?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>සාමාජික පිවිසුම - පුස්තකාල පද්ධතිය</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>if(localStorage.getItem('theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
</head>
<body>
<div class="top"><div class="in">
  <div class="brand"><i class="fa-solid fa-book-reader"></i> සාමාජික පිවිසුම (Member Login)</div>
  <button class="theme-btn" onclick="toggleTheme()"><i class="fa-solid fa-circle-half-stroke"></i></button>
</div></div>
<div class="wrap" style="max-width:420px;margin-top:60px">
  <form method="post" class="card" style="box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
    <h2 style="text-align:center;color:var(--ac);margin-bottom:8px;"><i class="fa-solid fa-right-to-bracket"></i> සාමාජික ඇතුළු වන්න</h2>
    <p class="muted" style="text-align:center;font-size:13px;margin-top:0;margin-bottom:18px;">
      ලයිබ්‍රේරියන් විසින් ලියාපදිංචි කළ සාමාජිකයන්ට පමණි (එක් පුස්තකාලයකට සීමිතයි).
    </p>
    <label>Username / ලියාපදිංචි අංකය</label><input name="u" autofocus autocomplete="username" required>
    <label>මුරපදය (Password)</label><input type="password" name="p" autocomplete="current-password" required>
    <?php if ($bad) echo '<p style="color:var(--warn);font-size:13px;margin-top:8px;">පරිශීලක නාමය හෝ මුරපදය වැරදියි</p>'; ?>
    <?php if ($notRegisteredMsg) echo '<p style="color:var(--warn);font-size:13px;margin-top:8px;">'.htmlspecialchars($notRegisteredMsg).'</p>'; ?>
    <p style="margin-top:20px;"><button class="btn" style="width:100%;justify-content:center;">ඇතුළු වන්න</button></p>
  </form>
</div>
<script>function toggleTheme(){const h=document.documentElement,c=h.getAttribute('data-theme'),n=c==='dark'?'light':'dark';h.setAttribute('data-theme',n);localStorage.setItem('theme',n);}</script>
</body></html>
<?php exit; }

$libFallbackMap = [1 => 'Mainnoluwa', 2 => 'Mahena', 3 => 'Dedigama', 4 => 'Algama'];
$memberLibId = (int)$me['library_id'];
$displayLibName = !empty($me['lib_name'])
  ? $me['lib_name']
  : ($libFallbackMap[$memberLibId] ?? ('Library #' . $memberLibId));
?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Portal - <?= htmlspecialchars($displayLibName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>if(localStorage.getItem('theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
</head>
<body>
<div class="top"><div class="in">
  <div class="brand"><i class="fa-solid fa-book-reader"></i> <span><?= htmlspecialchars($displayLibName) ?> - සාමාජික පුවරුව</span></div>
  <div class="who" style="display:flex;align-items:center;gap:12px;">
    <button class="theme-btn" onclick="toggleLang()" title="Change Language" style="font-size:13px;font-weight:bold;width:auto;padding:0 12px;border-radius:99px;"><i class="fa-solid fa-language"></i> <span id="langLbl">EN</span></button>
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode"><i class="fa-solid fa-circle-half-stroke"></i></button>
    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($me['name']) ?> | <i class="fa-solid fa-building-columns"></i> <b><?= htmlspecialchars($displayLibName) ?></b></span>
    <a href="?logout=1"><i class="fa-solid fa-power-off"></i> පිටවන්න</a>
  </div>
</div></div>

<div class="wrap">
  <nav id="nav"></nav>
  <div id="view"></div>
</div>

<script src="qrcode.js"></script>
<script>
function toggleTheme(){
  const h = document.documentElement, c = h.getAttribute('data-theme'), n = c==='dark'?'light':'dark';
  h.setAttribute('data-theme', n); localStorage.setItem('theme', n);
}
var lang = localStorage.getItem('lang') || 'si';
function toggleLang(){
  lang = lang === 'si' ? 'en' : 'si';
  localStorage.setItem('lang', lang);
  $('langLbl').innerText = lang === 'si' ? 'EN' : 'සිං';
  render();
}

var ME = <?= json_encode($me, JSON_UNESCAPED_UNICODE) ?>;
var MEMBER_LIB_ID = <?= $memberLibId ?>;
var MEMBER_LIB_NAME = <?= json_encode($displayLibName, JSON_UNESCAPED_UNICODE) ?>;
var tab = 'rules';
var rows = [], total = 0, q = '', LD = null, V = {};

function $(i){return document.getElementById(i)}
async function api(a,p,b){
  var params = Object.assign({ lib_id: MEMBER_LIB_ID }, p || {});
  var r = await fetch('api.php?a='+encodeURIComponent(a)+'&'+new URLSearchParams(params), b?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)}:{});
  if(r.status===401){location.reload();throw new Error('auth');}
  var j = await r.json(); if(j.error){alert(j.error);throw new Error(j.error)} return j;
}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]))}
function fd(d){try{return d?new Date(d).toLocaleDateString(lang=='si'?'si-LK':'en-US',{year:'numeric',month:'long',day:'numeric'}):''}catch(e){return d||''}}
function qr(t){var o=qrcode(0,'M');o.addData(t);o.make();return o.createSvgTag({scalable:true,margin:0})}

function calcFineDetails(dueStr){
  if(!dueStr) return { extraDays: 0, fine: 0 };
  var dueTs = new Date(dueStr).getTime();
  if(isNaN(dueTs)) return { extraDays: 0, fine: 0 };
  var diffMs = Date.now() - dueTs;
  if(diffMs <= 0) return { extraDays: 0, fine: 0 };
  var extraDays = Math.floor(diffMs / 86400000);
  if(extraDays < 1) extraDays = 1;
  return { extraDays: extraDays, fine: extraDays * 5 };
}

function getTabs(){
  return [
    ['rules', '<i class="fa-solid fa-scale-balanced"></i> ' + (lang=='si'?'නීතිරීති':'Rules & Regulations')],
    ['dash', '<i class="fa-solid fa-gauge-high"></i> ' + (lang=='si'?'උපකරණ පුවරුව':'Dashboard')],
    ['books', '<i class="fa-solid fa-book"></i> ' + (lang=='si'?'පුස්තකාලයේ පොත්':'Library Books')],
    ['borrowed', '<i class="fa-solid fa-book-bookmark"></i> ' + (lang=='si'?'මගේ ණය පොත්':'Borrowed Books')],
    ['fines', '<i class="fa-solid fa-coins"></i> ' + (lang=='si'?'දඩ ගෙවීම්':'Fines')]
  ];
}

function nav(){
  var TABS = getTabs();
  $('nav').innerHTML = TABS.map(t => '<button class="'+(tab===t[0]?'on':'')+'" onclick="go(\''+t[0]+'\')">'+t[1]+'</button>').join('');
}
function go(t){tab=t; render()}
async function render(){
  $('langLbl').innerText = lang === 'si' ? 'EN' : 'සිං';
  nav();
  var v = $('view');
  v.innerHTML = '<div class="card"><p class="muted">'+(lang=='si'?'පූරණය වෙමින් පවතී...':'Loading...')+'</p></div>';
  await V[tab](v);
}

async function getMemberLoansSafe(){
  try {
    var s = await api('stats');
    if (s && Array.isArray(s.current)) return { stats: s, loans: s.current };
  } catch(e) {}
  return { stats: {}, loans: [] };
}

V.rules = async function(v){
  var list = [];
  try {
    list = await api('get_rules');
  } catch(e) {}
  var rulesArr = Array.isArray(list) ? list : [];

  v.innerHTML =
    '<div class="card" style="padding:12px 18px;margin-bottom:16px;border-left:4px solid var(--ac);">'+
      '<i class="fa-solid fa-building-columns" style="color:var(--ac);"></i> <b>'+(lang=='si'?'ඔබ ලියාපදිංචි පුස්තකාලය:':'Your Registered Library:')+'</b> <span class="tag">'+esc(MEMBER_LIB_NAME)+'</span>'+
    '</div>'+
    '<div class="card" style="border:2px solid var(--ac);">'+
      '<h2><i class="fa-solid fa-scale-balanced" style="color:var(--ac);"></i> '+(lang=='si'?'පුස්තකාල නීතිරීති (Rules & Regulations)':'Library Rules & Regulations')+'</h2>'+
      '<p class="muted">'+(lang=='si'?'පරිපාලක (Admin) විසින් ප්‍රකාශයට පත් කර ඇති පුස්තකාල නීතිරීති.':'Rules and regulations published by the administrator.')+'</p>'+
      (rulesArr.length ? rulesArr.map(function(r, idx){
        return '<div class="card" style="background:var(--bg);margin-top:12px;border-left:4px solid var(--ac);">'+
          '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">'+
            '<h3 style="margin:0;">'+(idx + 1)+'. '+esc(r.title)+'</h3>'+
            '<span class="tag">Rule #'+esc(r.id)+'</span>'+
          '</div>'+
          '<p style="white-space:pre-wrap;margin:10px 0;color:var(--text);">'+esc(r.content)+'</p>'+
          '<small class="muted"><i class="fa-regular fa-clock"></i> '+fd(r.created_at)+'</small>'+
        '</div>';
      }).join('') : '<p class="muted" style="margin-top:12px;">'+(lang=='si'?'දැනට නීතිරීති කිසිවක් ඇතුළත් කර නැත.':'No rules or regulations available.')+'</p>')+
    '</div>';
};

V.dash = async function(v){
  var info = await getMemberLoansSafe();
  var s = info.stats || {};
  var currentLoans = info.loans || [];
  var totalFine = 0;
  var overdueCount = 0;

  currentLoans.forEach(function(l){
    var f = calcFineDetails(l.due);
    if(f.fine > 0){
      totalFine += f.fine;
      overdueCount++;
    }
  });

  var libName = s.my_lib || MEMBER_LIB_NAME;

  v.innerHTML =
    '<div class="card" style="background: linear-gradient(135deg, var(--ac), #3b82f6); color: #fff; padding: 24px; border-radius: var(--radius); border:none;">'+
      '<h1 style="margin:0;"><i class="fa-solid fa-id-card"></i> '+(lang=='si'?'සාමාජික උපකරණ පුවරුව':'Member Dashboard')+'</h1>'+
      '<p style="opacity:0.95;margin:8px 0 0 0;">'+
        (lang=='si'?'සාමාජිකයා: ':'Member: ')+'<b>'+esc(ME.name)+'</b> • '+
        (lang=='si'?'ලියාපදිංචි පුස්තකාලය: ':'Registered Library: ')+'<b>'+esc(libName)+'</b>'+
      '</p>'+
    '</div>'+

    '<div class="tiles" style="margin-top:20px;">'+
      '<div class="tile"><b>'+currentLoans.length+'</b><span>'+(lang=='si'?'ළඟ ඇති පොත්':'Borrowed Books')+'</span></div>'+
      '<div class="tile bad"><b>'+overdueCount+'</b><span>'+(lang=='si'?'ප්‍රමාද පොත්':'Overdue Books')+'</span></div>'+
      '<div class="tile '+(totalFine>0?'bad':'')+'"><b>Rs. '+totalFine.toFixed(2)+'</b><span>'+(lang=='si'?'ගෙවිය යුතු දඩය':'Pending Fine')+'</span></div>'+
    '</div>'+

    '<div class="card" style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">'+
      '<button class="btn" onclick="go(\'books\')"><i class="fa-solid fa-book"></i> '+(lang=='si'?'පුස්තකාලයේ පොත් බලන්න':'Browse Library Books')+'</button>'+
      '<button class="btn alt" onclick="go(\'borrowed\')"><i class="fa-solid fa-book-bookmark"></i> '+(lang=='si'?'මගේ ණය පොත්':'My Borrowed Books')+'</button>'+
      '<button class="btn warn" onclick="go(\'fines\')"><i class="fa-solid fa-coins"></i> '+(lang=='si'?'දඩ ගෙවීම් විස්තර':'View Fines')+'</button>'+
    '</div>';
};

V.books = async function(v){
  v.innerHTML =
    '<div class="card" style="padding:12px 18px;margin-bottom:16px;border-left:4px solid var(--ac);">'+
      '<i class="fa-solid fa-building-columns" style="color:var(--ac);"></i> <b>'+(lang=='si'?'ඔබ ලියාපදිංචි පුස්තකාලය:':'Your Registered Library:')+'</b> <span class="tag">'+esc(MEMBER_LIB_NAME)+'</span>'+
    '</div>'+
    '<div class="card" style="border:2px solid var(--ac);">'+
      '<input class="big" id="q" autofocus placeholder="'+(lang=='si'?'🔍 පොතේ නම හෝ කතෘ සොයන්න...':'🔍 Search book title or author...')+'" value="'+esc(q)+'" oninput="q=this.value;load(1)" style="margin:0;">'+
    '</div>'+
    '<div id="res"></div>';

  LD = {
    a: 'search',
    p: () => ({ q: q, lib_id: MEMBER_LIB_ID }),
    f: (b, i) => {
      if (b.library_id && Number(b.library_id) !== Number(MEMBER_LIB_ID)) return '';
      var avail = (Number(b.copies) || 0) - (Number(b.lent) || 0);
      return '<div class="card"><div class="item" onclick="tog('+i+')">'+
        '<div><b>'+esc(b.title)+'</b> <span class="tag">'+esc(b.lib_name || MEMBER_LIB_NAME)+'</span><br><small class="muted">'+esc(b.author)+'</small></div>'+
        '<span class="tag'+(avail>0?'':' out')+'">'+avail+' / '+b.copies+' '+(lang=='si'?'ඇත':'available')+'</span>'+
      '</div>'+
      (b.c ? '<div class="cps">'+b.c.map(x =>
        '<div class="cp"><span><b>'+esc(x.code)+'</b> <div style="display:inline-block;background:#fff;padding:2px;margin-left:10px;border-radius:4px;">'+qr(x.code)+'</div></span>'+
        (x.due ? '<span class="tag out">'+(lang=='si'?'ආපසු ':'Due ')+fd(x.due)+'</span>' : '<span class="tag">'+(lang=='si'?'ඇත':'Available')+'</span>')+'</div>'
      ).join('')+'</div>' : '')+'</div>';
    }
  };
  await load(1);
};

async function load(r){if(r)rows=[];var j=await api(LD.a,Object.assign({off:rows.length},LD.p()));rows=rows.concat(j.rows);total=j.total;paint()}
function paint(){$('res').innerHTML='<p class="muted" style="margin-bottom:10px;">'+total+' '+(lang=='si'?'ප්‍රතිඵල':'results')+'</p>'+rows.map(LD.f).join('')+(rows.length<total?'<p style="text-align:center;margin-top:20px;"><button class="btn alt" onclick="load()">'+(lang=='si'?'තව පෙන්වන්න':'Load More')+'</button></p>':'')}
async function tog(i){var b=rows[i];b.c=b.c?null:await api('copies',{title:b.title,author:b.author,lib_id:MEMBER_LIB_ID});paint()}

V.borrowed = async function(v){
  var info = await getMemberLoansSafe();
  var loans = info.loans || [];

  v.innerHTML =
    '<div class="card" style="border:2px solid var(--ac);">'+
      '<h2><i class="fa-solid fa-book-bookmark" style="color:var(--ac);"></i> '+(lang=='si'?'ඔබ ණයට ගෙන ඇති පොත්':'Your Borrowed Books')+'</h2>'+
      '<p class="muted">'+(lang=='si'?'පොතක් ළඟ තබාගත හැක්කේ දින 14කි. දින 14 ඉක්මවූ විට දිනකට රු. 5.00 බැගින් දඩ මුදල් අදාළ වේ.':'Standard loan period is 14 days. Rs. 5.00 per extra day applies after due date.')+'</p>'+
      (loans.length ? loans.map(function(l){
        var f = calcFineDetails(l.due);
        return '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;border-left:4px solid '+(f.fine>0?'var(--warn)':'var(--ac)')+';">'+
          '<div>'+
            '<h4 style="margin:0 0 5px 0;">'+esc(l.title)+' '+(l.code?'<span class="tag">Code: '+esc(l.code)+'</span>':'')+'</h4>'+
            '<small class="muted">'+(lang=='si'?'ගත් දිනය: ':'Issued: ')+fd(l.date_out)+' • <span style="color:var(--warn);font-weight:bold;">'+(lang=='si'?'ආපසු දිය යුතු දිනය: ':'Due Date: ')+fd(l.due)+'</span></small>'+
          '</div>'+
          '<div style="text-align:right;">'+
            (f.fine > 0
              ? '<span class="tag out">'+(lang=='si'?'දඩය: Rs. ':'Fine: Rs. ')+f.fine.toFixed(2)+' ('+f.extraDays+(lang=='si'?' දින':' days')+')</span>'
              : '<span class="tag">'+(lang=='si'?'දඩයක් නැත':'No Fine')+'</span>')+
          '</div>'+
        '</div>';
      }).join('') : '<p class="muted" style="margin-top:12px;">'+(lang=='si'?'ඔබ දැනට පොත් කිසිවක් ණයට ගෙන නැත.':'You have no borrowed books currently.')+'</p>')+
    '</div>';
};

V.fines = async function(v){
  var info = await getMemberLoansSafe();
  var loans = info.loans || [];
  var fineItems = [];
  var totalFine = 0;

  loans.forEach(function(l){
    var f = calcFineDetails(l.due);
    if(f.fine > 0){
      totalFine += f.fine;
      fineItems.push({
        title: l.title,
        code: l.code || '',
        due: l.due,
        extraDays: f.extraDays,
        fine: f.fine
      });
    }
  });

  v.innerHTML =
    '<div class="card" style="border:2px solid var(--warn);">'+
      '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">'+
        '<div>'+
          '<h2 style="margin:0 0 4px 0;color:var(--warn);"><i class="fa-solid fa-coins"></i> '+(lang=='si'?'ඔබේ දඩ ගෙවීම් (Your Fines)':'Your Pending Fines')+'</h2>'+
          '<p class="muted" style="margin:0;">'+(lang=='si'?'දින 14ට වඩා ළඟ තබාගත් පොත් සඳහා දිනකට රු. 5.00 බැගින් ගණනය කර ඇත.':'Calculated at Rs. 5.00 per extra day beyond 14 days.')+'</p>'+
        '</div>'+
        '<div style="text-align:right;">'+
          '<div class="muted" style="font-size:12px;">'+(lang=='si'?'ගෙවිය යුතු මුළු දඩ මුදල':'Total Fine Due')+'</div>'+
          '<div style="font-size:22px;font-weight:800;color:var(--warn);">Rs. '+totalFine.toFixed(2)+'</div>'+
        '</div>'+
      '</div>'+
      (fineItems.length ? fineItems.map(function(item){
        return '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;border-left:4px solid var(--warn);">'+
          '<div>'+
            '<h4 style="margin:0 0 4px 0;">'+esc(item.title)+' '+(item.code?'<span class="tag">Code: '+esc(item.code)+'</span>':'')+'</h4>'+
            '<small class="muted">'+(lang=='si'?'නියමිත දිනය: ':'Due: ')+fd(item.due)+' • '+item.extraDays+(lang=='si'?' අමතර දින':' extra days')+'</small>'+
          '</div>'+
          '<div style="text-align:right;">'+
            '<span class="tag out" style="font-size:14px;padding:6px 12px;">Rs. '+item.fine.toFixed(2)+'</span>'+
          '</div>'+
        '</div>';
      }).join('') : '<p class="muted" style="margin-top:12px;">'+(lang=='si'?'ඔබට ගෙවිය යුතු දඩ මුදල් කිසිවක් නොමැත.':'You have no pending fines.')+'</p>')+
    '</div>';
};

render();
</script>
</body>
</html>