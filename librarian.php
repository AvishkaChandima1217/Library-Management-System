<?php
require_once __DIR__ . '/db.php';
session_start();
if (isset($_GET['logout'])) { session_destroy(); header('Location: librarian.php'); exit; }

$bad = false;
if (isset($_POST['u'])) {
  $r = one('SELECT id, pw, role FROM users WHERE username=?', [trim($_POST['u'])]);
  if ($r && password_verify((string)($_POST['p'] ?? ''), $r['pw']) && in_array($r['role'], ['librarian', 'admin'], true)) {
    session_regenerate_id(true);
    $_SESSION['u'] = $r['id'];
    header('Location: librarian.php');
    exit;
  }
  $bad = true;
}

$me = !empty($_SESSION['u']) ? one('SELECT u.*, l.name as lib_name FROM users u LEFT JOIN libraries l ON l.id=u.library_id WHERE u.id=?', [$_SESSION['u']]) : null;

if (!$me || !in_array($me['role'], ['librarian', 'admin'], true)) { ?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ලයිබ්‍රේරියන් පිවිසුම - පුස්තකාල පද්ධතිය</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>if(localStorage.getItem('theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
</head>
<body>
<div class="top"><div class="in">
  <div class="brand"><i class="fa-solid fa-user-tie"></i> ලයිබ්‍රේරියන් පිවිසුම (Librarian Login)</div>
  <button class="theme-btn" onclick="toggleTheme()"><i class="fa-solid fa-circle-half-stroke"></i></button>
</div></div>
<div class="wrap" style="max-width:400px;margin-top:60px">
  <form method="post" class="card" style="box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
    <h2 style="text-align:center;color:var(--ac);margin-bottom:20px;"><i class="fa-solid fa-right-to-bracket"></i> ලයිබ්‍රේරියන් ඇතුළු වන්න</h2>
    <label>සේවක අංකය / Username</label><input name="u" autofocus autocomplete="username">
    <label>මුරපදය (Password)</label><input type="password" name="p" autocomplete="current-password">
    <?php if ($bad) echo '<p style="color:var(--warn);font-size:13px;margin-top:5px;">පරිශීලක නාමය හෝ මුරපදය වැරදියි</p>'; ?>
    <p style="margin-top:20px;"><button class="btn" style="width:100%;justify-content:center;">ඇතුළු වන්න</button></p>
  </form>
</div>
<script>function toggleTheme(){const h=document.documentElement,c=h.getAttribute('data-theme'),n=c==='dark'?'light':'dark';h.setAttribute('data-theme',n);localStorage.setItem('theme',n);}</script>
</body></html>
<?php exit; }

$libFallbackMap = [1 => 'Mainnoluwa', 2 => 'Mahena', 3 => 'Dedigama', 4 => 'Algama', 5 => 'පුස්තකාලය 5'];
$displayLibName = !empty($me['lib_name']) ? $me['lib_name'] : ($libFallbackMap[(int)($me['library_id'] ?? 0)] ?? ('Library #' . ($me['library_id'] ?? '')));
?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Librarian Dashboard - <?= htmlspecialchars($displayLibName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>if(localStorage.getItem('theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body>
<div class="top"><div class="in">
  <div class="brand"><i class="fa-solid fa-book-open-reader"></i> <span><?= htmlspecialchars($displayLibName) ?> - ලයිබ්‍රේරියන් පුවරුව</span></div>
  <div class="who" style="display:flex;align-items:center;gap:12px;">
    <button class="theme-btn" onclick="toggleLang()" title="Change Language" style="font-size:13px;font-weight:bold;width:auto;padding:0 12px;border-radius:99px;"><i class="fa-solid fa-language"></i> <span id="langLbl">EN</span></button>
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode"><i class="fa-solid fa-circle-half-stroke"></i></button>
    <span><i class="fa-solid fa-building-columns"></i> <b><?= htmlspecialchars($displayLibName) ?></b> | <i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($me['name']) ?></span>
    <a href="?logout=1"><i class="fa-solid fa-power-off"></i> පිටවන්න</a>
  </div>
</div></div>

<div class="wrap">
  <nav id="nav"></nav>
  <div id="view"></div>
</div>

<script src="qrcode.js"></script><script src="barcode.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
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

function getMyLibName() {
  if (ME.lib_name && String(ME.lib_name).trim() !== '') return String(ME.lib_name);
  var map = { 1: 'Mainnoluwa', 2: 'Mahena', 3: 'Dedigama', 4: 'Algama', 5: 'පුස්තකාලය 5' };
  return map[Number(ME.library_id)] || ('Library #' + (ME.library_id || ''));
}

function libBanner(){
  var myLib = getMyLibName();
  return '<div class="card" style="padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border-left:4px solid var(--ac);">'+
    '<span><i class="fa-solid fa-building-columns" style="color:var(--ac);"></i> <b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> <span class="tag">'+esc(myLib)+'</span></span>'+
    '<small class="muted">'+(lang=='si'?'ලයිබ්‍රේරියන්: ':'Librarian: ')+esc(ME.name)+'</small>'+
  '</div>';
}

function calcFineDetails(dueStr, isPaid){
  if(isPaid || !dueStr) return { extraDays: 0, fine: 0 };
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
    ['dash', '<i class="fa-solid fa-chart-pie"></i> ' + (lang=='si'?'උපකරණ පුවරුව':'Dashboard')],
    ['search', '<i class="fa-solid fa-magnifying-glass"></i> ' + (lang=='si'?'පොත් සොයන්න':'Search Books')],
    ['scan', '<i class="fa-solid fa-qrcode"></i> ' + (lang=='si'?'ස්කෑන් / ණයට දීම':'Scan / Issue')],
    ['loans', '<i class="fa-solid fa-book-bookmark"></i> ' + (lang=='si'?'ණයට දුන් පොත්':'Active Loans')],
    ['fines', '<i class="fa-solid fa-coins"></i> ' + (lang=='si'?'දඩ මුදල් (Fines)':'Fines')],
    ['members', '<i class="fa-solid fa-users"></i> ' + (lang=='si'?'සාමාජිකයන්':'Members')],
    ['manage', '<i class="fa-solid fa-sliders"></i> ' + (lang=='si'?'කළමනාකරණය':'Management')],
    ['audit', '<i class="fa-solid fa-clipboard-user"></i> ' + (lang=='si'?'විගණනය (Audit)':'Audit')],
    ['news', '<i class="fa-solid fa-bullhorn"></i> ' + (lang=='si'?'පුවත්':'News')]
  ];
}

var tab = 'dash';
var rows=[], total=0, q='', LD=null, V={}, NEWS_CACHE=[];
function $(i){return document.getElementById(i)}
function g(i){return $(i).value}
async function api(a,p,b){
  var r = await fetch('api.php?a='+encodeURIComponent(a)+'&'+new URLSearchParams(p||{}), b?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)}:{});
  if(r.status===401){location.reload();throw new Error('auth');}
  var j = await r.json(); if(j.error){alert(j.error);throw new Error(j.error)} return j;
}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]))}
function fd(d){try{return d?new Date(d).toLocaleDateString(lang=='si'?'si-LK':'en-US',{year:'numeric',month:'long',day:'numeric'}):''}catch(e){return d||''}}
function qr(t){var o=qrcode(0,'M');o.addData(t);o.make();return o.createSvgTag({scalable:true,margin:0})}

function nav(){
  var TABS = getTabs();
  $('nav').innerHTML=TABS.map(t=>'<button class="'+(tab===t[0]?'on':'')+'" onclick="go(\''+t[0]+'\')">'+t[1]+'</button>').join('');
}
function go(t){tab=t; render()}
async function render(){
  $('langLbl').innerText = lang === 'si' ? 'EN' : 'සිං';
  nav();
  var v=$('view');
  v.innerHTML='<div class="card"><p class="muted">'+(lang=='si'?'පූරණය වෙමින් පවතී...':'Loading...')+'</p></div>';
  try {
    await V[tab](v);
  } catch(err) {
    console.error('Render error on tab ' + tab + ':', err);
    v.innerHTML='<div class="card"><p style="color:var(--warn);">Error: '+esc(err.message||err)+'</p></div>';
  }
}

function canEditOrDeleteNews(n){
  if (!ME) return false;
  if (ME.role === 'admin') return true;
  if (ME.role === 'librarian') {
    var isOwnNews = Number(n.created_by) === Number(ME.id);
    var isLibAuthor = !n.author_role || n.author_role === 'librarian';
    return isOwnNews && isLibAuthor;
  }
  return false;
}

V.dash = async function(v){
  var s = await api('stats');
  var myLib = getMyLibName();
  var firstOfMonth = new Date().toISOString().substring(0, 8) + '01';
  var todayStr = new Date().toISOString().substring(0, 10);

  v.innerHTML =
    '<div class="card" style="background: linear-gradient(135deg, var(--ac), #3b82f6); color: #fff; padding: 25px; border-radius: var(--radius); border:none;">'+
      '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">'+
        '<div>'+
          '<h1 style="margin:0;"><i class="fa-solid fa-gauge-high"></i> '+(lang=='si'?'ලයිබ්‍රේරියන් උපකරණ පුවරුව':'Librarian Dashboard')+'</h1>'+
          '<p style="opacity:0.95; margin:6px 0 0 0;">'+(lang=='si'?'ඔබ දැනට පිවිස සිටින පුස්තකාලය: ':'Current Logged-in Library: ')+'<b>'+esc(myLib)+'</b></p>'+
        '</div>'+
        '<span class="tag" style="background:rgba(255,255,255,0.2);color:#fff;border-color:rgba(255,255,255,0.35);font-size:14px;padding:8px 14px;border-radius:999px;"><i class="fa-solid fa-building-columns"></i> '+esc(myLib)+'</span>'+
      '</div>'+
    '</div>'+
    '<div class="tiles" style="margin-top: 20px;">'+
      '<div class="tile"><b>'+s.titles+'</b><span>'+(lang=='si'?'පොත් මාතෘකා':'Book Titles')+'</span></div>'+
      '<div class="tile"><b>'+s.copies+'</b><span>'+(lang=='si'?'පොත් පිටපත්':'Book Copies')+'</span></div>'+
      '<div class="tile"><b>'+s.lent+'</b><span>'+(lang=='si'?'ණයට දුන් පොත්':'Lent Books')+'</span></div>'+
      '<div class="tile bad"><b>'+s.overdue+'</b><span>'+(lang=='si'?'පරක්කු වූ පොත්':'Overdue Books')+'</span></div>'+
      '<div class="tile"><b>'+s.members+'</b><span>'+(lang=='si'?'සාමාජිකයන්':'Members')+'</span></div>'+
    '</div>'+
    '<div class="card" style="margin-top:20px;border:2px solid var(--ac);">'+
      '<h2><i class="fa-solid fa-download" style="color:var(--ac);"></i> '+(lang=='si'?'වාර්තා බාගත කිරීම් (Reports & Downloads)':'Reports & Downloads')+'</h2>'+
      '<p class="muted">'+(lang=='si'?'කාලසීමාව (Start Date - End Date) තෝරා අදාළ වාර්තාව CSV ලෙස බාගන්න.':'Select a date range and download the required CSV report.')+'</p>'+
      '<div class="cols" style="margin-top:12px;">'+
        '<div><label>'+(lang=='si'?'මුල් දිනය (Start Date):':'Start Date:')+'</label><input type="date" id="repStart" value="'+firstOfMonth+'"></div>'+
        '<div><label>'+(lang=='si'?'අවසන් දිනය (End Date):':'End Date:')+'</label><input type="date" id="repEnd" value="'+todayStr+'"></div>'+
      '</div>'+
      '<div class="row" style="gap:10px;margin-top:14px;display:flex;flex-wrap:wrap;">'+
        '<button class="btn" onclick="downloadReport(\'donated_books_period\')"><i class="fa-solid fa-hand-holding-heart"></i> '+(lang=='si'?'පරිත්‍යාග පොත් Download (Donation)':'Download Donated Books')+'</button>'+
        '<button class="btn alt" onclick="downloadReport(\'books_period\')"><i class="fa-solid fa-book"></i> '+(lang=='si'?'පොත් ලැයිස්තුව Download':'Download Books List')+'</button>'+
        '<button class="btn alt" onclick="downloadReport(\'members_age\')"><i class="fa-solid fa-users"></i> '+(lang=='si'?'සාමාජික වයස් වාර්තාව':'Members Age Report')+'</button>'+
        '<button class="btn warn" onclick="downloadReport(\'overdue_loans\')"><i class="fa-solid fa-triangle-exclamation"></i> '+(lang=='si'?'ප්‍රමාද පොත් වාර්තාව':'Overdue Loans Report')+'</button>'+
      '</div>'+
    '</div>';
};

async function downloadReport(type){
  var start = $('repStart') ? $('repStart').value : '';
  var end = $('repEnd') ? $('repEnd').value : '';
  var data = await api('download_report', { report_type: type, start: start, end: end });

  if(!Array.isArray(data) || !data.length){
    alert(lang=='si'?'තෝරාගත් කාලසීමාව තුළ දත්ත හමු නොවීය!':'No data found for the selected date range!');
    return;
  }

  if(type === 'members_age'){
    var today = new Date();
    data = data.map(function(m){
      var age = m.dob ? Math.floor((today - new Date(m.dob)) / (365.25 * 24 * 60 * 60 * 1000)) : 'N/A';
      return Object.assign({}, m, {
        age: age,
        age_group: (age !== 'N/A' && age < 18) ? 'Under 18 (18ට අඩු)' : '18 and Above (18ට වැඩි)'
      });
    });
  }

  var keys = Object.keys(data[0]);
  var csv = keys.join(',') + '\n';
  data.forEach(function(row){
    csv += keys.map(function(k){ return '"' + String(row[k] == null ? '' : row[k]).replace(/"/g, '""') + '"'; }).join(',') + '\n';
  });

  var blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = type + '_' + (start || 'all') + '_to_' + (end || 'all') + '.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

V.search = async function(v){
  v.innerHTML = libBanner() + '<div class="card" style="border: 2px solid var(--ac);"><input class="big" id="q" autofocus placeholder="'+(lang=='si'?'🔍 පොතේ නම, කතෘ හෝ Barcode එක ලියන්න...':'🔍 Type book title, author or barcode...')+'" value="'+esc(q)+'" oninput="q=this.value;load(1)" style="margin:0;"></div><div id="res"></div>';
  LD={a:'search',p:()=>({q:q}),f:(b,i)=>{
    var a=b.copies-b.lent;
    return '<div class="card"><div class="item" onclick="tog('+i+')"><div><b>'+esc(b.title)+'</b> <span class="tag">'+esc(b.lib_name)+'</span><br><small class="muted">'+esc(b.author)+'</small></div><span class="tag'+(a?'':' out')+'">'+a+' / '+b.copies+' '+(lang=='si'?'ඇත':'available')+'</span></div>'+(b.c?'<div class="cps">'+b.c.map(x=>'<div class="cp"><span><b>'+esc(x.code)+'</b> <div style="display:inline-block;background:#fff;padding:2px;margin-left:10px;border-radius:4px;">'+qr(x.code)+'</div></span>'+(x.due?'<span class="tag out">Due '+fd(x.due)+'</span>':'<span class="tag">Available</span>')+'</div>').join('')+'</div>':'')+'</div>';
  }};
  await load(1);
};
async function load(r){if(r)rows=[];var j=await api(LD.a,Object.assign({off:rows.length},LD.p()));rows=rows.concat(j.rows);total=j.total;paint()}
function paint(){$('res').innerHTML='<p class="muted" style="margin-bottom:10px;">'+total+' '+(lang=='si'?'ප්‍රතිඵල':'results')+'</p>'+rows.map(LD.f).join('')+(rows.length<total?'<p style="text-align:center;margin-top:20px;"><button class="btn alt" onclick="load()">'+(lang=='si'?'තව පෙන්වන්න':'Load More')+'</button></p>':'')}
async function tog(i){var b=rows[i];b.c=b.c?null:await api('copies',{title:b.title,author:b.author});paint()}

V.scan = async function(v){
  v.innerHTML = libBanner() +
    '<div class="cols" style="gap:20px;align-items:flex-start;">'+
      '<div class="card" style="border:2px solid var(--success);box-shadow:0 4px 15px rgba(34,197,94,0.12);margin:0;">'+
        '<h2 style="margin:0 0 6px 0;color:var(--success);"><i class="fa-solid fa-hand-holding-hand"></i> '+(lang=='si'?'1. පොත් ණයට දීම (Issue Book)':'1. Issue Book')+'</h2>'+
        '<p class="muted" style="margin:0 0 14px 0;">'+(lang=='si'?'පොතේ Barcode එක ස්කෑන් කරන්න හෝ ටයිප් කර Enter ඔබන්න.':'Scan or type book barcode and press Enter.')+'</p>'+
        '<div class="row" style="display:flex;gap:10px;">'+
          '<input class="big" id="scIssue" autofocus placeholder="'+(lang=='si'?'උදා: B00001':'e.g. B00001')+'" onkeypress="if(event.key===\'Enter\') lookIssue()" style="flex:1;margin:0;">'+
          '<button class="btn" style="background:var(--success);border-color:var(--success);" onclick="lookIssue()"><i class="fa-solid fa-magnifying-glass"></i> '+(lang=='si'?'සොයන්න':'Find')+'</button>'+
        '</div>'+
        '<div id="detIssue" style="margin-top:16px;"></div>'+
      '</div>'+

      '<div class="card" style="border:2px solid var(--warn);box-shadow:0 4px 15px rgba(239,68,68,0.12);margin:0;">'+
        '<h2 style="margin:0 0 6px 0;color:var(--warn);"><i class="fa-solid fa-rotate-left"></i> '+(lang=='si'?'2. ණයට දුන් පොත් ආපසු ගැනීම (Return Book)':'2. Return Lent Book')+'</h2>'+
        '<p class="muted" style="margin:0 0 14px 0;">'+(lang=='si'?'ණයට දුන් පොතේ Barcode එක ස්කෑන් කරන්න හෝ ටයිප් කර Enter ඔබන්න.':'Scan or type lent book barcode and press Enter.')+'</p>'+
        '<div class="row" style="display:flex;gap:10px;">'+
          '<input class="big" id="scReturn" placeholder="'+(lang=='si'?'උදා: B00001':'e.g. B00001')+'" onkeypress="if(event.key===\'Enter\') lookReturn()" style="flex:1;margin:0;">'+
          '<button class="btn warn" onclick="lookReturn()"><i class="fa-solid fa-magnifying-glass"></i> '+(lang=='si'?'සොයන්න':'Find')+'</button>'+
        '</div>'+
        '<div id="detReturn" style="margin-top:16px;"></div>'+
      '</div>'+
    '</div>' +

  '<div class="card" style="margin-top:20px;border:2px solid var(--success);">'+
    '<h2><i class="fa-solid fa-user-check" style="color:var(--success);"></i> '+(lang=='si'?'සාමාජිකයාගේ Barcode / ID එක මඟින් විස්තර බැලීම':'Lookup Member by Barcode / ID')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'සාමාජිකයාගේ ID එක (උදා: M1) හෝ Username එක ඇතුළත් කර Enter ඔබන්න:':'Enter member ID (e.g. M1) or username and press Enter:')+'</p>'+
    '<div class="row" style="display:flex;gap:10px;margin-top:10px;">'+
      '<input class="big" id="memberScanInput" placeholder="උදා: M1 හෝ username" onkeypress="if(event.key===\'Enter\') loadMemberDetailsManually()" style="flex:1;margin:0;">'+
      '<button class="btn" style="background:var(--success);border-color:var(--success);" onclick="loadMemberDetailsManually()">'+(lang=='si'?'විස්තර බලන්න':'View Details')+'</button>'+
    '</div>'+
    '<div id="manualMemberResult" style="margin-top:15px;"></div>'+
  '</div>';
};

async function lookIssue(){
  var c = g('scIssue').trim();
  if(!c) return;
  var d = $('detIssue');
  try {
    var j = await api('book', {code: c}), b = j.book;
    if(!b){
      d.innerHTML = '<div class="card" style="background:var(--bg);border-left:4px solid var(--warn);margin:0;"><p style="color:var(--warn);margin:0;">'+(lang=='si'?'පොත හමු නොවීය!':'Book not found!')+'</p></div>';
      return;
    }
    var o = j.loan;
    var h = '<div class="card" style="background:var(--bg);border-top:3px solid var(--success);margin:0;">'+
      '<h3 style="margin:0 0 6px 0;">'+esc(b.title)+'</h3>'+
      '<div class="qr">'+qr(b.code)+'</div>'+
      '<p style="margin:8px 0 0 0;font-size:13px;"><b>'+(lang=='si'?'කේතය:':'Code:')+'</b> '+esc(b.code)+' | <b>'+(lang=='si'?'කතෘ:':'Author:')+'</b> '+esc(b.author)+'</p>';

    if(o){
      h += '<div style="background:var(--card);padding:10px;border-radius:8px;margin-top:10px;border-left:4px solid var(--warn);">'+
        '<span class="tag out" style="margin-bottom:5px;display:inline-block;">'+(lang=='si'?'දැනට ණයට දී ඇත':'Lent Out')+'</span>'+
        '<p style="margin:5px 0;"><b>'+(lang=='si'?'සාමාජිකයා:':'Member:')+'</b> '+esc(o.name)+' ('+esc(o.phone)+')</p>'+
        '<p style="margin:0;"><b>'+(lang=='si'?'ආපසු දිය යුතු දිනය:':'Due Date:')+'</b> '+fd(o.due)+'</p>'+
      '</div>';
    } else {
      h += '<p class="tag" style="margin-top:10px;">'+(lang=='si'?'පුස්තකාලයේ ඇත (Available)':'Available')+'</p>'+
        '<div style="margin-top:10px;">'+
          '<label>'+(lang=='si'?'සාමාජිකයාගේ Username හෝ Member ID:':'Member Username or ID:')+'</label>'+
          '<input id="mu" placeholder="M1 හෝ username" onkeypress="if(event.key===\'Enter\') lendBook(\''+esc(b.code)+'\')">'+
          '<button class="btn" onclick="lendBook(\''+esc(b.code)+'\')" style="margin-top:5px;background:var(--success);border-color:var(--success);">'+(lang=='si'?'පොත ණයට දෙන්න':'Lend Book')+'</button>'+
        '</div>';
    }
    d.innerHTML = h + '</div>';
  } catch(e){
    d.innerHTML = '<div class="card" style="background:var(--bg);border-left:4px solid var(--warn);margin:0;"><p style="color:var(--warn);margin:0;">'+(lang=='si'?'දෝෂයක් සිදු විය.':'An error occurred.')+'</p></div>';
  }
}

async function lookReturn(){
  var c = g('scReturn').trim();
  if(!c) return;
  var d = $('detReturn');
  try {
    var j = await api('book', {code: c}), b = j.book;
    if(!b){
      d.innerHTML = '<div class="card" style="background:var(--bg);border-left:4px solid var(--warn);margin:0;"><p style="color:var(--warn);margin:0;">'+(lang=='si'?'පොත හමු නොවීය!':'Book not found!')+'</p></div>';
      return;
    }
    var o = j.loan;
    var h = '<div class="card" style="background:var(--bg);border-top:3px solid var(--warn);margin:0;">'+
      '<h3 style="margin:0 0 6px 0;">'+esc(b.title)+'</h3>'+
      '<div class="qr">'+qr(b.code)+'</div>'+
      '<p style="margin:8px 0 0 0;font-size:13px;"><b>'+(lang=='si'?'කේතය:':'Code:')+'</b> '+esc(b.code)+' | <b>'+(lang=='si'?'කතෘ:':'Author:')+'</b> '+esc(b.author)+'</p>';

    if(o){
      var f = calcFineDetails(o.due, Number(o.fine_paid) === 1);
      h += '<div style="background:var(--card);padding:10px;border-radius:8px;margin-top:10px;border-left:4px solid var(--warn);">'+
        '<span class="tag out" style="margin-bottom:5px;display:inline-block;">'+(lang=='si'?'ණයට දී ඇත':'Lent Out')+'</span>'+
        '<p style="margin:5px 0;"><b>'+(lang=='si'?'සාමාජිකයා:':'Member:')+'</b> '+esc(o.name)+' ('+esc(o.phone)+')</p>'+
        '<p style="margin:0;"><b>'+(lang=='si'?'ආපසු දිය යුතු දිනය:':'Due Date:')+'</b> '+fd(o.due)+'</p>'+
        (f.fine > 0 ? '<p style="margin:6px 0 0 0;color:var(--warn);font-weight:bold;">'+(lang=='si'?'දඩ මුදල (අමතර දින ':'Fine (Extra ')+f.extraDays+(lang=='si'?'): Rs. ':' days): Rs. ')+f.fine.toFixed(2)+'</p>' : (Number(o.fine_paid)===1 ? '<p style="margin:6px 0 0 0;color:var(--success);font-weight:bold;">'+(lang=='si'?'දඩය ගෙවා ඇත':'Fine Paid')+'</p>' : ''))+
      '</div>'+
      '<button class="btn warn" onclick="returnBook(\''+esc(b.code)+'\')" style="margin-top:10px;">'+(lang=='si'?'පොත ආපසු භාරගන්න':'Return Book')+'</button>';
    } else {
      h += '<p class="tag" style="margin-top:10px;">'+(lang=='si'?'මෙම පොත දැනට ණයට දී නැත':'Book is not currently lent out')+'</p>';
    }
    d.innerHTML = h + '</div>';
  } catch(e){
    d.innerHTML = '<div class="card" style="background:var(--bg);border-left:4px solid var(--warn);margin:0;"><p style="color:var(--warn);margin:0;">'+(lang=='si'?'දෝෂයක් සිදු විය.':'An error occurred.')+'</p></div>';
  }
}

async function lendBook(code){
  var memInput = $('mu').value.trim();
  if(!memInput){ alert(lang=='si'?'කරුණාකර සාමාජිකයාගේ Username හෝ ID එක දෙන්න!':'Please enter member username or ID!'); return; }
  await api('lend',{},{code:code, username:memInput});
  alert(lang=='si'?'පොත සාර්ථකව ණයට දෙන ලදී!':'Book successfully lent!');
  lookIssue();
}

async function returnBook(code){
  await api('return',{},{code:code});
  alert(lang=='si'?'පොත සාර්ථකව ආපසු භාරගන්නා ලදී!':'Book successfully returned!');
  lookReturn();
}

async function loadMemberDetailsManually(){
  var val = $('memberScanInput').value.trim();
  var resBox = $('manualMemberResult');
  if(!val) { resBox.innerHTML = ''; return; }

  try {
    var mems = await api('members');
    var cleanVal = val.replace(/^M/i, '');
    var foundMem = mems.rows.find(m => m.id == cleanVal || m.username.toLowerCase() === val.toLowerCase());

    if(!foundMem) {
      resBox.innerHTML = '<div class="card" style="background:var(--bg);border-left:4px solid var(--warn);"><p style="color:var(--warn);margin:0;">'+(lang=='si'?'සාමාජිකයා හමු නොවීය!':'Member not found!')+'</p></div>';
      return;
    }

    var loans = await api('loans');
    var memberLoans = loans.filter(l => l.user_id == foundMem.id);

    var html = '<div class="card" style="background:var(--bg);border-left:4px solid var(--success);">' +
      '<h3 style="margin:0 0 10px 0;"><i class="fa-solid fa-user"></i> '+(lang=='si'?'සාමාජික විස්තර':'Member Details')+'</h3>' +
      '<p style="margin:0 0 5px 0;"><b>'+(lang=='si'?'නම:':'Name:')+'</b> ' + esc(foundMem.name) + '</p>' +
      '<p style="margin:0 0 5px 0;"><b>Member ID:</b> M' + foundMem.id + '</p>' +
      '<p style="margin:0 0 15px 0;"><b>'+(lang=='si'?'දුරකථන අංකය:':'Phone:')+'</b> ' + esc(foundMem.phone || 'N/A') + '</p>' +
      '<h4 style="margin:0 0 10px 0;"><i class="fa-solid fa-book"></i> '+(lang=='si'?'දැනට ණයට ගෙන ඇති පොත්:':'Current Borrowed Books:')+'</h4>';

    if(memberLoans.length > 0) {
      html += memberLoans.map(l => {
        var f = calcFineDetails(l.due, Number(l.fine_paid) === 1);
        return '<div style="background:var(--card);padding:10px;border-radius:6px;margin-top:8px;border:1px solid var(--line);">'+
          '<b>'+(lang=='si'?'පොතේ නම:':'Book:')+'</b> ' + esc(l.title) + ' (Code: ' + esc(l.code) + ')<br>'+
          '<small class="muted">'+(lang=='si'?'ගත්තු දිනය:':'Issued:')+' ' + fd(l.date_out) + ' | <span style="color:var(--warn);font-weight:bold;">'+(lang=='si'?'නැවත ලබාදිය යුතු දිනය:':'Due:')+' ' + fd(l.due) + '</span></small>'+
          (f.fine > 0 ? '<div style="margin-top:4px;color:var(--warn);font-weight:bold;font-size:13px;">'+(lang=='si'?'දඩ මුදල: Rs. ':'Fine: Rs. ')+f.fine.toFixed(2)+' ('+f.extraDays+(lang=='si'?' දින':' days')+')</div>' : '')+
        '</div>';
      }).join('');
    } else {
      html += '<p class="muted" style="margin:0;">'+(lang=='si'?'මෙම සාමාජිකයා විසින් දැනට පොත් කිසිවක් ණයට ගෙන නැත.':'No active books borrowed by this member.')+'</p>';
    }

    html += '</div>';
    resBox.innerHTML = html;
  } catch(e) {
    resBox.innerHTML = '<div class="card" style="background:var(--bg);border-left:4px solid var(--warn);"><p style="color:var(--warn);margin:0;">'+(lang=='si'?'දෝෂයක් සිදු විය.':'An error occurred.')+'</p></div>';
  }
}

async function renewLoanItem(loanId){
  if(!confirm(lang=='si'?'මෙම පොතේ ණය කාලය තවත් දින 14කින් දීර්ඝ කිරීමට අවශ්‍යද?':'Extend this loan by 14 more days?')) return;
  await api('renew_loan', {}, { loan_id: loanId });
  alert(lang=='si'?'ණය කාලය සාර්ථකව දීර්ඝ කරන ලදී!':'Loan renewed for 14 days!');
  render();
}

V.loans = async function(v){
  var r = await api('loans');
  v.innerHTML = libBanner() + '<div class="card" style="border:2px solid var(--success);">'+
    '<h2><i class="fa-solid fa-book-bookmark" style="color:var(--success);"></i> '+(lang=='si'?'පිටතට පොත් ලබාදී ඇති සාමාජිකයන්ගේ විස්තර (Active Loans)':'Active Loans')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'දැනට පුස්තකාලයෙන් පොත් රැගෙන ගොස් ඇති සාමාජිකයන්ගේ සහ අදාළ පොත්වල විස්තර:':'Members who currently have books borrowed:')+'</p>'+
    (r.length ? r.map(l=>
      '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;border-left:4px solid var(--ac);">'+
        '<div>'+
          '<h4 style="margin:0 0 5px 0;">'+esc(l.title)+' <span class="tag">Code: '+esc(l.code)+'</span></h4>'+
          '<p style="margin:0;color:var(--muted);"><i class="fa-solid fa-user"></i> <b>'+(lang=='si'?'සාමාජිකයා:':'Member:')+'</b> '+esc(l.name)+' (ID: M'+l.user_id+') | <i class="fa-solid fa-phone"></i> <b>'+(lang=='si'?'දුරකථන:':'Phone:')+'</b> '+esc(l.phone)+'</p>'+
          '<small class="muted">'+(lang=='si'?'ගත්තු දිනය:':'Issued:')+' '+fd(l.date_out)+'</small>'+
        '</div>'+
        '<div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:8px;">'+
          '<span class="tag out">'+(lang=='si'?'ආපසු දිය යුතු: ':'Due: ')+fd(l.due)+'</span>'+
          '<button class="btn alt" style="padding:5px 10px;font-size:12px;" onclick="renewLoanItem('+l.id+')"><i class="fa-solid fa-calendar-plus"></i> '+(lang=='si'?'දින 14ක් දීර්ඝ කරන්න':'Renew (+14d)')+'</button>'+
        '</div>'+
      '</div>'
    ).join('') : '<p class="muted">'+(lang=='si'?'දැනට කිසිදු සාමාජිකයෙකු විසින් පිටතට පොත් රැගෙන ගොස් නැත.':'No active loans found.')+'</p>')+
  '</div>';
};

async function markFinePaidLib(loanId){
  await api('pay_fine', {}, { loan_id: loanId });
  alert(lang=='si'?'දඩ මුදල ගෙවූ බව සටහන් කරන ලදී!':'Fine marked as paid!');
  render();
}

V.fines = async function(v){
  var loans = await api('loans');
  var fineRows = [];
  var totalFine = 0;

  (Array.isArray(loans) ? loans : []).forEach(function(l){
    var f = calcFineDetails(l.due, Number(l.fine_paid) === 1);
    if(f.fine > 0){
      totalFine += f.fine;
      fineRows.push({
        loanId: l.id,
        memberName: l.name || l.username || ('M' + l.user_id),
        userId: l.user_id,
        phone: l.phone || '',
        bookTitle: l.title,
        bookCode: l.code,
        extraDays: f.extraDays,
        fine: f.fine
      });
    }
  });

  v.innerHTML = libBanner() +
    '<div class="card" style="border:2px solid var(--warn);">'+
      '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">'+
        '<div>'+
          '<h2 style="margin:0 0 4px 0;color:var(--warn);"><i class="fa-solid fa-coins"></i> '+(lang=='si'?'දඩ ගෙවිය යුතු සාමාජිකයන් සහ දඩ මුදල් (Fines)':'Pending Fines')+'</h2>'+
          '<p class="muted" style="margin:0;">'+(lang=='si'?'දින 14ට වඩා ළඟ තබාගත් පොත් සඳහා දිනකට රු. 5.00 බැගින් ගණනය කර ඇත.':'Calculated at Rs. 5.00 per extra day beyond 14 days.')+'</p>'+
        '</div>'+
        '<div style="text-align:right;">'+
          '<div class="muted" style="font-size:12px;">'+(lang=='si'?'මුළු දඩ මුදල':'Total Fine')+'</div>'+
          '<div style="font-size:20px;font-weight:800;color:var(--warn);">Rs. '+totalFine.toFixed(2)+'</div>'+
        '</div>'+
      '</div>'+

      (fineRows.length ? fineRows.map(function(item){
        return '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;border-left:4px solid var(--warn);">'+
          '<div>'+
            '<h4 style="margin:0 0 4px 0;"><i class="fa-solid fa-user"></i> '+esc(item.memberName)+' <small class="muted">(ID: M'+esc(item.userId)+')</small></h4>'+
            '<small class="muted">'+(lang=='si'?'පොත: ':'Book: ')+esc(item.bookTitle)+' ('+esc(item.bookCode)+') • '+item.extraDays+(lang=='si'?' අමතර දින':' extra days')+'</small>'+
          '</div>'+
          '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'+
            '<span class="tag out" style="font-size:14px;padding:6px 12px;">Rs. '+item.fine.toFixed(2)+'</span>'+
            '<button class="btn" style="padding:6px 12px;font-size:12px;background:var(--success);border-color:var(--success);" onclick="markFinePaidLib('+item.loanId+')"><i class="fa-solid fa-check"></i> '+(lang=='si'?'ගෙවන ලදී':'Mark Paid')+'</button>'+
          '</div>'+
        '</div>';
      }).join('') : '<p class="muted" style="margin-top:12px;">'+(lang=='si'?'දැනට දඩ ගෙවිය යුතු සාමාජිකයන් කිසිවෙකු නොමැත.':'No members with pending fines.')+'</p>')+
    '</div>';
};

V.members = async function(v){
  var mems = await api('members');

  var today = new Date();
  var under18 = [];
  var above18 = [];

  mems.rows.forEach(u => {
    if(u.dob) {
      var birthDate = new Date(u.dob);
      var age = today.getFullYear() - birthDate.getFullYear();
      var m = today.getMonth() - birthDate.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) { age--; }

      u.calc_age = age;
      if(age < 18) {
        under18.push(u);
      } else {
        above18.push(u);
      }
    } else {
      u.calc_age = 'N/A';
      above18.push(u);
    }
  });

  v.innerHTML = libBanner() + '<div class="card" style="border:2px solid var(--ac); margin-bottom:20px;">'+
    '<h2><i class="fa-solid fa-user-plus"></i> '+(lang=='si'?'නව සාමාජිකයකු ලියාපදිංචි කිරීම (Add Member)':'Add New Member')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'සාමාජිකයාගේ නම සහ මුරපදය මඟින් ඔහුට පද්ධතියට පිවිසිය (Login) හැක.':'Member can login using their username and password.')+'</p>'+
    '<div class="cols" style="margin-top:15px;">'+
      '<div><label>'+(lang=='si'?'සාමාජිකයාගේ නම (Full Name):':'Full Name:')+'</label><input id="memName" placeholder="සමීර පෙරේරා"></div>'+
      '<div><label>'+(lang=='si'?'ලියාපදිංචි අංකය / Username:':'Registration No / Username:')+'</label><input id="memUsername" placeholder="member123"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'දුරකථන අංකය (Phone Number):':'Phone Number:')+'</label><input id="memPhone" placeholder="0712345678"></div>'+
      '<div><label>'+(lang=='si'?'උපන් දිනය (Birthday):':'Birthday:')+'</label><input type="date" id="memDob"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'ලිපිනය (Address):':'Address:')+'</label><input id="memAddress" placeholder="කොළඹ Road, ගම්පහ"></div>'+
      '<div><label>'+(lang=='si'?'ඊමේල් ලිපිනය (Email - Optional):':'Email (Optional):')+'</label><input type="email" id="memEmail" placeholder="member@gmail.com"></div>'+
    '</div>'+
    '<div style="margin-top:10px;">'+
      '<label>'+(lang=='si'?'මුරපදය (Password):':'Password:')+'</label><input type="password" id="memPassword" placeholder="මුරපදයක් දෙන්න">'+
    '</div>'+
    '<div style="margin-top:15px;"><button class="btn" onclick="addNewMember()"><i class="fa-solid fa-check"></i> '+(lang=='si'?'සාමාජිකයා Register කරන්න':'Register Member')+'</button></div>'+
  '</div>'+

  '<div class="card">'+
    '<h2><i class="fa-solid fa-users"></i> '+(lang=='si'?'සාමාජිකයන් ලැයිස්තුව (වයස් කාණ්ඩ අනුව ස්වයංක්‍රීයව වෙන් වේ)':'Members List (Categorized by Age)')+'</h2>'+

    '<div style="display:flex;gap:10px;margin:20px 0;border-bottom:2px solid var(--line);padding-bottom:15px;flex-wrap:wrap;">'+
      '<button class="btn alt" id="btnAge1" onclick="switchAgeTab(1)" style="border-radius:8px;"><i class="fa-solid fa-child"></i> '+(lang=='si'?'ළමා - 18ට අඩු (':'Under 18 (')+under18.length+')</button>'+
      '<button class="btn" id="btnAge2" onclick="switchAgeTab(2)" style="border-radius:8px;"><i class="fa-solid fa-user"></i> '+(lang=='si'?'වැඩිහිටි - 18ට වැඩි (':'18 and Above (')+above18.length+')</button>'+
    '</div>'+

    '<div id="ageTab1Content" style="display:none;">'+
      '<h3 style="margin-bottom:15px;color:var(--success);"><i class="fa-solid fa-child"></i> '+(lang=='si'?'වයස අවුරුදු 18ට අඩු සාමාජිකයන් (ළමා)':'Members Under 18')+'</h3>'+
      (under18.length ? under18.map(u=>
        '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-left:4px solid var(--success);">'+
          '<div>'+
            '<h4 style="margin:0 0 5px 0;">'+esc(u.name)+' (ID: M'+u.id+') <span class="tag">'+(lang=='si'?'වයස: ':'Age: ')+u.calc_age+'</span> <span class="tag" style="background:var(--success);color:#fff;border-color:var(--success);font-size:11px;">Under 18</span></h4>'+
            '<p style="margin:0;color:var(--muted);"><i class="fa-solid fa-phone"></i> '+esc(u.phone)+' | <b>DOB:</b> '+esc(u.dob)+' | <i class="fa-solid fa-location-dot"></i> '+esc(u.address || 'N/A')+'</p>'+
          '</div>'+
          '<div>'+
            '<button class="btn warn" style="padding:6px 14px;font-size:12px;" onclick="deleteMember('+u.id+')"><i class="fa-solid fa-trash"></i> '+(lang=='si'?'ඉවත් කරන්න':'Delete')+'</button>'+
          '</div>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'වයස 18ට අඩු සාමාජිකයන් නොමැත.':'No members under 18.')+'</p>')+
    '</div>'+

    '<div id="ageTab2Content">'+
      '<h3 style="margin-bottom:15px;color:var(--ac);"><i class="fa-solid fa-user"></i> '+(lang=='si'?'වයස අවුරුදු 18ට වැඩි සාමාජිකයන් (වැඩිහිටි)':'Members 18 and Above')+'</h3>'+
      (above18.length ? above18.map(u=>
        '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-left:4px solid var(--ac);">'+
          '<div>'+
            '<h4 style="margin:0 0 5px 0;">'+esc(u.name)+' (ID: M'+u.id+') '+ (u.calc_age !== 'N/A' ? '<span class="tag">'+(lang=='si'?'වයස: ':'Age: ')+u.calc_age+'</span>' : '') +' <span class="tag out" style="font-size:11px;">18+</span></h4>'+
            '<p style="margin:0;color:var(--muted);"><i class="fa-solid fa-phone"></i> '+esc(u.phone)+' | <b>DOB:</b> '+esc(u.dob || 'N/A')+' | <i class="fa-solid fa-location-dot"></i> '+esc(u.address || 'N/A')+'</p>'+
          '</div>'+
          '<div>'+
            '<button class="btn warn" style="padding:6px 14px;font-size:12px;" onclick="deleteMember('+u.id+')"><i class="fa-solid fa-trash"></i> '+(lang=='si'?'ඉවත් කරන්න':'Delete')+'</button>'+
          '</div>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'වයස 18ට වැඩි සාමාජිකයන් නොමැත.':'No members 18 and above.')+'</p>')+
    '</div>'+
  '</div>';
};

function switchAgeTab(tabNo) {
  var b1 = $('btnAge1'), b2 = $('btnAge2');
  if(tabNo === 1) {
    $('ageTab1Content').style.display = 'block';
    $('ageTab2Content').style.display = 'none';
    if(b1){ b1.className = 'btn'; }
    if(b2){ b2.className = 'btn alt'; }
  } else {
    $('ageTab1Content').style.display = 'none';
    $('ageTab2Content').style.display = 'block';
    if(b1){ b1.className = 'btn alt'; }
    if(b2){ b2.className = 'btn'; }
  }
}

async function addNewMember(){
  var name = $('memName').value.trim();
  var username = $('memUsername').value.trim();
  var phone = $('memPhone').value.trim();
  var dob = $('memDob').value;
  var address = $('memAddress').value.trim();
  var email = $('memEmail').value.trim();
  var password = $('memPassword').value.trim();

  if(!name || !username || !password){
    alert(lang=='si'?'කරුණාකර නම, Username සහ මුරපදය අත්‍යවශ්‍යයෙන් පුරවන්න!':'Please fill name, username and password!');
    return;
  }

  try {
    await api('add_member', {}, {
      name: name,
      username: username,
      phone: phone,
      dob: dob,
      address: address,
      email: email,
      password: password
    });
    alert(lang=='si'?'සාමාජිකයා සාර්ථකව ලියාපදිංචි කරන ලදී!':'Member registered successfully!');
    render();
  } catch(e) {}
}

async function deleteMember(id){
  if(confirm(lang=='si'?'මෙම සාමාජිකයා පද්ධතියෙන් ඉවත් කිරීමට අවශ්‍ය බව ස්ථිරද?':'Are you sure you want to delete this member?')) {
    await api('delete_member', {}, {id: id});
    alert(lang=='si'?'සාර්ථකව ඉවත් කරන ලදී!':'Successfully deleted!');
    render();
  }
}

V.manage = async function(v){
  var todayStr = new Date().toISOString().substring(0, 10);
  v.innerHTML = libBanner() + '<div class="card" style="border:2px solid var(--ac); margin-bottom:20px;">'+
    '<h2><i class="fa-solid fa-user-plus" style="color:var(--ac);"></i> '+(lang=='si'?'නව සාමාජිකයකු ලියාපදිංචි කිරීම (Add New Member)':'Add New Member')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'ඔබේ පුස්තකාලයට ('+esc(getMyLibName())+') නව සාමාජිකයකු ඇතුළත් කරන්න.':'Register a new member for your library ('+esc(getMyLibName())+').')+'</p>'+
    '<div class="cols" style="margin-top:15px;">'+
      '<div><label>'+(lang=='si'?'සාමාජිකයාගේ නම (Full Name):':'Full Name:')+'</label><input id="mngMemName" placeholder="සමීර පෙරේරා"></div>'+
      '<div><label>'+(lang=='si'?'ලියාපදිංචි අංකය / Username:':'Registration No / Username:')+'</label><input id="mngMemUsername" placeholder="member123"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'දුරකථන අංකය (Phone Number):':'Phone Number:')+'</label><input id="mngMemPhone" placeholder="0712345678"></div>'+
      '<div><label>'+(lang=='si'?'උපන් දිනය (Birthday):':'Birthday:')+'</label><input type="date" id="mngMemDob"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'ලිපිනය (Address):':'Address:')+'</label><input id="mngMemAddress" placeholder="කොළඹ Road, ගම්පහ"></div>'+
      '<div><label>'+(lang=='si'?'ඊමේල් ලිපිනය (Email - Optional):':'Email (Optional):')+'</label><input type="email" id="mngMemEmail" placeholder="member@gmail.com"></div>'+
    '</div>'+
    '<div style="margin-top:10px;">'+
      '<label>'+(lang=='si'?'මුරපදය (Password):':'Password:')+'</label><input type="password" id="mngMemPassword" placeholder="මුරපදයක් දෙන්න">'+
    '</div>'+
    '<div style="margin-top:15px;"><button class="btn" onclick="addMemberFromManage()"><i class="fa-solid fa-check"></i> '+(lang=='si'?'සාමාජිකයා Register කරන්න':'Register Member')+'</button></div>'+
  '</div>'+

  '<div class="card" style="border:2px solid var(--ac); margin-bottom:20px;">'+
    '<h2><i class="fa-solid fa-book-medical"></i> '+(lang=='si'?'තනි පොතක් එකතු කිරීම (Add Single Book)':'Add Single Book')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'පොත සඳහා විස්තර ඇතුළත් කර Barcode සමඟ පද්ධතියට එක් කරන්න.':'Add a book with details and generate barcode.')+'</p>'+
    '<div class="cols" style="margin-top:15px;">'+
      '<div><label>'+(lang=='si'?'පරිග්‍රහණ අංකය / Barcode (හිස් නම් ස්වයංක්‍රීයව සෑදේ):':'Accession No / Barcode (Auto if blank):')+'</label><input id="sCode" placeholder="උදා: B00100"></div>'+
      '<div><label>'+(lang=='si'?'ලැබුණ දිනය (Received Date):':'Received Date:')+'</label><input type="date" id="sDate" value="'+todayStr+'"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'පොතේ නම (Book Name):':'Book Name:')+'</label><input id="sTitle" placeholder="පොතේ නම"></div>'+
      '<div><label>'+(lang=='si'?'කතෘ (Author):':'Author:')+'</label><input id="sAuthor" placeholder="කතෘගේ නම"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'ප්‍රකාශන ස්ථානය (Place of Publication):':'Place of Publication:')+'</label><input id="sPubPlace" placeholder="කොළඹ"></div>'+
      '<div><label>'+(lang=='si'?'ප්‍රකාශකයා (Publisher):':'Publisher:')+'</label><input id="sPublisher" placeholder="ප්‍රකාශකයා"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'ප්‍රකාශිත දිනය / වර්ෂය (Publication Year/Date):':'Publication Year/Date:')+'</label><input id="sPubDate" placeholder="2024"></div>'+
      '<div><label>'+(lang=='si'?'පිටු ගණන (Pages):':'Pages:')+'</label><input type="number" id="sPages" placeholder="150"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'මිල (Price):':'Price:')+'</label><input type="number" step="0.01" id="sPrice" placeholder="500.00"></div>'+
      '<div>'+
        '<label><i class="fa-solid fa-hand-holding-heart"></i> '+(lang=='si'?'ලැබුණු ආකාරය (How Received):':'How Received:')+'</label>'+
        '<select id="sReceivedType" onchange="onReceivedTypeChange()">'+
          '<option value="">-- '+(lang=='si'?'තෝරන්න':'Select')+' --</option>'+
          '<option value="purchase">'+(lang=='si'?'මිලදී ගැනීම් (Purchase)':'Purchase (මිලදී ගැනීම්)')+'</option>'+
          '<option value="donation">'+(lang=='si'?'පරිත්‍යාග (Donation)':'Donation (පරිත්‍යාග)')+'</option>'+
        '</select>'+
      '</div>'+
    '</div>'+
    '<div id="sReceivedDescWrap" style="display:none;margin-top:10px;">'+
      '<label id="sReceivedDescLabel"><i class="fa-solid fa-file-lines"></i> '+(lang=='si'?'විස්තරය (Description):':'Description:')+'</label>'+
      '<input id="sReceivedDesc" placeholder="'+(lang=='si'?'කෙටි විස්තරයක් ලියන්න...':'Enter a short description...')+'">'+
    '</div>'+
    '<div style="margin-top:15px;"><button class="btn" onclick="addSingleBook()"><i class="fa-solid fa-check"></i> '+(lang=='si'?'තනි පොත එකතු කර Barcode සාදන්න':'Add Book & Generate Barcode')+'</button></div>'+
    '<div id="singleBookBarcodeBox" style="margin-top:15px;"></div>'+
  '</div>'+

  '<div class="card" style="border:2px solid var(--success);">'+
    '<h2><i class="fa-solid fa-file-excel"></i> '+(lang=='si'?'Excel මඟින් පොත් බල්ක් (Bulk) ඇතුළත් කිරීම':'Bulk Book Import (Excel)')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'Excel ගොනුවක් මඟින් එකවර පොත් රැසක් ඇතුළත් කර Barcode සෑදිය හැක.':'Import multiple books from Excel and generate barcodes.')+'</p>'+
    '<input type="file" id="excelFile" accept=".xlsx,.xls,.csv" style="margin:15px 0;background:var(--bg);">'+
    '<button class="btn" style="background:var(--success);border-color:var(--success);" onclick="uploadBooksExcel()"><i class="fa-solid fa-upload"></i> '+(lang=='si'?'Upload කර Barcodes සාදන්න':'Upload & Generate Barcodes')+'</button>'+
    '<div id="generatedBarcodesBox" style="margin-top:20px;"></div>'+
  '</div>';
};

function onReceivedTypeChange(){
  var sel = $('sReceivedType');
  var wrap = $('sReceivedDescWrap');
  var lbl = $('sReceivedDescLabel');
  var inp = $('sReceivedDesc');
  if(!sel || !wrap || !lbl || !inp) return;

  var val = sel.value;
  if(val === 'purchase'){
    wrap.style.display = 'block';
    lbl.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> ' + (lang=='si'?'මිලදී ගැනීම පිළිබඳ විස්තරය (Purchase Description):':'Purchase Description:');
    inp.placeholder = lang=='si' ? 'උදා: සැපයුම්කරු / බිල්පත් අංකය / මිලදී ගත් විස්තරය...' : 'e.g. Supplier / Invoice / Purchase details...';
  } else if(val === 'donation'){
    wrap.style.display = 'block';
    lbl.innerHTML = '<i class="fa-solid fa-gift"></i> ' + (lang=='si'?'පරිත්‍යාගය පිළිබඳ විස්තරය (Donation Description):':'Donation Description:');
    inp.placeholder = lang=='si' ? 'උදා: පරිත්‍යාග කළ පුද්ගලයා / ආයතනය සහ විස්තරය...' : 'e.g. Donor name / organization & details...';
  } else {
    wrap.style.display = 'none';
    inp.value = '';
  }
}

async function addMemberFromManage(){
  var name = $('mngMemName').value.trim();
  var username = $('mngMemUsername').value.trim();
  var phone = $('mngMemPhone').value.trim();
  var dob = $('mngMemDob').value;
  var address = $('mngMemAddress').value.trim();
  var email = $('mngMemEmail').value.trim();
  var password = $('mngMemPassword').value.trim();

  if(!name || !username || !password){
    alert(lang=='si'?'කරුණාකර නම, Username සහ මුරපදය අත්‍යවශ්‍යයෙන් පුරවන්න!':'Please fill name, username and password!');
    return;
  }

  await api('add_member', {}, {
    name: name,
    username: username,
    phone: phone,
    dob: dob,
    address: address,
    email: email,
    password: password
  });
  alert(lang=='si'?'සාමාජිකයා සාර්ථකව ලියාපදිංචි කරන ලදී!':'Member registered successfully!');
  render();
}

async function addSingleBook(){
  var code = $('sCode').value.trim();
  var date = $('sDate').value;
  var title = $('sTitle').value.trim();
  var author = $('sAuthor').value.trim();
  var pubPlace = $('sPubPlace').value.trim();
  var publisher = $('sPublisher').value.trim();
  var pubDate = $('sPubDate').value.trim();
  var pages = $('sPages').value.trim();
  var price = $('sPrice').value.trim();
  var receivedType = $('sReceivedType').value.trim();
  var receivedDesc = $('sReceivedDesc').value.trim();

  if(!title){ alert(lang=='si'?'පොතේ නම අත්‍යවශ්‍යයි!':'Book title is required!'); return; }
  if(!receivedType){
    alert(lang=='si'?'කරුණාකර "ලැබුණු ආකාරය" (මිලදී ගැනීම් හෝ පරිත්‍යාග) තෝරන්න!':'Please select how the book was received!');
    return;
  }
  if(!receivedDesc){
    alert(lang=='si'?'කරුණාකර තෝරාගත් ආකාරය පිළිබඳ පොඩි විස්තරයක් ඇතුළත් කරන්න!':'Please enter a short description!');
    $('sReceivedDesc').focus();
    return;
  }

  try {
    var res = await api('add_single_book', {}, {
      code: code,
      date: date,
      received_date: date,
      title: title,
      author: author,
      pub_place: pubPlace,
      publisher: publisher,
      pub_date: pubDate,
      pages: pages,
      price: price,
      received_type: receivedType,
      received_desc: receivedDesc,
      source: receivedDesc
    });
    alert(lang=='si'?'පොත සාර්ථකව එකතු කරන ලදී!':'Book added successfully!');
    var genCode = res.code;

    $('singleBookBarcodeBox').innerHTML = '<div id="singlePrintArea" style="background:var(--bg);padding:20px;border-radius:10px;text-align:center;border:1px dashed var(--line);">'+
      '<h4 style="margin:0 0 10px 0;">'+(lang=='si'?'පොතේ නම: ':'Book Title: ')+esc(title)+'</h4>'+
      '<svg class="single-bc-svg" data-code="'+esc(genCode)+'"></svg>'+
      '<div style="margin-top:15px;">'+
        '<button class="btn" onclick="printSingleBarcode()"><i class="fa-solid fa-download"></i> '+(lang=='si'?'මෙම Barcode එක Download / Print කරන්න':'Download / Print Barcode')+'</button>'+
      '</div>'+
    '</div>';
    JsBarcode('.single-bc-svg', genCode, {format: "CODE128", width: 1.8, height: 45});
  } catch(e) {}
}

function printSingleBarcode(){
  var content = $('singlePrintArea').innerHTML;
  var win = window.open('', '', 'height=500,width=600');
  win.document.write('<html><head><title>Download Barcode</title><style>body{font-family:sans-serif;text-align:center;padding:30px;}</style></head><body>');
  win.document.write(content);
  win.document.write('</body></html>');
  win.document.close();
  win.focus();
  setTimeout(() => { win.print(); }, 500);
}

async function uploadBooksExcel(){
  var fileInput = $('excelFile');
  if(!fileInput.files.length){ alert(lang=='si'?'ගොනුවක් තෝරන්න!':'Please choose a file!'); return; }
  var reader = new FileReader();
  reader.onload = async function(e){
    var data = new Uint8Array(e.target.result);
    var workbook = XLSX.read(data, {type: 'array'});
    var sheet = workbook.Sheets[workbook.SheetNames[0]];
    var rows = XLSX.utils.sheet_to_json(sheet);
    var books = rows.map(r => ({title: r.Title || r.title || '', author: r.Author || r.author || '', copies: r.Copies || r.copies || 1})).filter(b => b.title);
    var res = await api('import_books', {}, {books: books});
    var created = res.created || [];

    $('generatedBarcodesBox').innerHTML = '<div style="background:var(--bg);padding:15px;border-radius:10px;">'+
      '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">'+
        '<h3>'+(lang=='si'?'පොත් ':'Added ')+created.length+(lang=='si'?' ක් එකතු කරන ලදී!':' books!')+'</h3>'+
        '<button class="btn" style="background:var(--ac);" onclick="printBulkBarcodes()"><i class="fa-solid fa-download"></i> '+(lang=='si'?'සියලුම Barcodes Download / Print කරන්න':'Download / Print All Barcodes')+'</button>'+
      '</div>'+
      '<div id="bulkPrintableBarcodes" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">'+
      created.map(b => '<div style="background:#fff;color:#000;padding:10px;border:1px dashed #999;border-radius:6px;text-align:center;"><div style="font-size:12px;font-weight:bold;">'+esc(b.title)+'</div><svg class="bc-svg" data-code="'+esc(b.code)+'"></svg></div>').join('')+'</div></div>';
    document.querySelectorAll('.bc-svg').forEach(svg => { JsBarcode(svg, svg.getAttribute('data-code'), {format: "CODE128", width: 1.6, height: 40}); });
  };
  reader.readAsArrayBuffer(fileInput.files[0]);
}

function printBulkBarcodes(){
  var content = $('bulkPrintableBarcodes').innerHTML;
  var win = window.open('', '', 'height=700,width=800');
  win.document.write('<html><head><title>Download Bulk Barcodes</title><style>body{font-family:sans-serif;padding:20px;} div{background:#fff;color:#000;}</style></head><body>');
  win.document.write('<h2 style="text-align:center;">Barcodes</h2><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;">' + content + '</div>');
  win.document.write('</body></html>');
  win.document.close();
  win.focus();
  setTimeout(() => { win.print(); }, 500);
}

V.audit = async function(v){
  v.innerHTML = libBanner() +
    '<div class="card" style="border:2px solid var(--ac);">' +
      '<h2><i class="fa-solid fa-clipboard-check"></i> ' + (lang=='si' ? 'පුස්තකාල විගණනය (Inventory Audit)' : 'Inventory Audit') + '</h2>' +
      '<p class="muted">' + (lang=='si' ? 'පොතේ Barcode එක ස්කෑන් කර Enter ඔබන්න (ස්වයංක්‍රීයව Tick වේ).' : 'Scan barcode and press Enter to tick/audit.') + '</p>' +
      '<input class="big" id="auditCode" autofocus placeholder="' + (lang=='si' ? '🔍 Barcode එක ස්කෑන් කර Enter ඔබන්න...' : '🔍 Scan barcode and press Enter...') + '" onkeypress="if(event.key===\'Enter\') auditBookItem()" style="margin-top:15px;">' +
      '<div id="auditMsg" style="margin-top:15px;font-weight:bold;"></div>' +
    '</div>' +
    '<div class="card" style="margin-top:20px;border:2px solid var(--success);">' +
      '<h2><i class="fa-solid fa-file-arrow-down" style="color:var(--success);"></i> ' + (lang=='si' ? 'විගණන වාර්තා බාගත කිරීම (Audit Reports Download)' : 'Audit Reports Download') + '</h2>' +
      '<p class="muted">' + (lang=='si' ? 'ඔබේ පුස්තකාලයට අදාළව Tick වැටුණු (Checked) සහ Tick වැටුණේ නැති (Unchecked) පොත් ලැයිස්තු වෙන වෙනම CSV ලෙස බාගන්න.' : 'Download Checked and Unchecked book lists for your library separately as CSV.') + '</p>' +
      '<div class="row" style="gap:12px;margin-top:15px;display:flex;flex-wrap:wrap;">' +
        '<button class="btn" onclick="downloadAudit(\'checked\')"><i class="fa-solid fa-check-double"></i> ' + (lang=='si' ? 'Tick වැටුණු පොත් Download (Checked)' : 'Download Ticked Books (Checked)') + '</button>' +
        '<button class="btn warn" onclick="downloadAudit(\'unchecked\')"><i class="fa-solid fa-xmark"></i> ' + (lang=='si' ? 'Tick වැටුණේ නැති පොත් Download (Unchecked)' : 'Download Unticked Books (Unchecked)') + '</button>' +
      '</div>' +
    '</div>';
};

async function auditBookItem() {
  var code = $('auditCode').value.trim(); if(!code) return;
  try {
    var res = await api('audit_check_book', {}, {code: code});
    $('auditMsg').innerHTML = '<span style="color:var(--success);"><i class="fa-solid fa-check-circle"></i> ' + (lang=='si' ? 'පොත සාර්ථකව විගණනය කර (Tick) සටහන් කරන ලදී: ' : 'Audited & Ticked: ') + esc(res.book.title) + ' (' + esc(res.book.code) + ')</span>';
    $('auditCode').value = ''; $('auditCode').focus();
  } catch(e) {
    $('auditMsg').innerHTML = '<span style="color:var(--warn);">' + (lang=='si' ? 'දෝෂයක් හෝ මෙම පොත ඔබේ පුස්තකාලයට අයත් නැත!' : 'Error or book does not belong to your library!') + '</span>';
  }
}

async function downloadAudit(status) {
  var data = await api('download_audit_report', {status: status});
  if(!data.length) {
    alert(lang=='si' ? 'මෙම කාණ්ඩය සඳහා දත්ත හමු නොවීය!' : 'No data found for this category!');
    return;
  }

  var keys = Object.keys(data[0]);
  var csv = keys.join(',') + '\n';
  data.forEach(row => {
    csv += keys.map(k => '"' + String(row[k] ?? '').replace(/"/g, '""') + '"').join(',') + '\n';
  });

  var blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = 'audit_' + status + '_' + new Date().toISOString().substring(0, 10) + '.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

V.news = async function(v){
  var createFormHtml =
    '<div class="card" style="border:2px solid var(--ac); margin-bottom:20px;">'+
      '<h2><i class="fa-solid fa-pen-to-square" style="color:var(--ac);"></i> '+(lang=='si'?'නව පුවතක් එක් කරන්න (Add New News)':'Add New News')+'</h2>'+
      '<div style="margin-top:10px;"><label>'+(lang=='si'?'ශීර්ෂය (Title):':'Title:')+'</label><input id="newsTitle" placeholder="උදා: නව පොත් තොගයක් ලැබී ඇත"></div>'+
      '<div style="margin-top:10px;"><label>'+(lang=='si'?'අන්තර්ගතය (Content):':'Content:')+'</label><textarea id="newsContent" rows="4" placeholder="පුවතේ විස්තරය මෙහි ලියන්න..."></textarea></div>'+
      '<div style="margin-top:15px;"><button class="btn" onclick="submitNewNews()"><i class="fa-solid fa-bullhorn"></i> '+(lang=='si'?'පුවත ප්‍රකාශයට පත් කරන්න':'Publish News')+'</button></div>'+
    '</div>';

  var editBoxHtml = '<div id="newsEditBox" style="display:none;margin-bottom:20px;"></div>';

  v.innerHTML = libBanner() + createFormHtml + editBoxHtml +
    '<div class="card"><h2><i class="fa-solid fa-bullhorn" style="color:var(--ac);"></i> '+(lang=='si'?'පුවත් සහ නිවේදන':'News & Notices')+'</h2><div id="newsListBox"><p class="muted">'+(lang=='si'?'පුවත් පූරණය වෙමින් පවතී...':'Loading news...')+'</p></div></div>';

  try {
    var newsList = await api('get_news');
    NEWS_CACHE = Array.isArray(newsList) ? newsList : [];
    $('newsListBox').innerHTML = NEWS_CACHE.length ? NEWS_CACHE.map(function(n){
      var allowEditDel = canEditOrDeleteNews(n);
      return '<div class="card" style="background:var(--bg);margin-top:12px;border-left:4px solid '+(n.target_audience==='librarian'?'var(--warn)':'var(--ac)')+';">'+
        '<h3 style="margin:0 0 8px 0;">📢 '+esc(n.title)+' '+(n.target_audience==='librarian'?'<span class="tag out" style="font-size:11px;">Librarians Only</span>':'<span class="tag" style="font-size:11px;">Notice</span>')+'</h3>'+
        '<p style="white-space:pre-wrap;margin:0 0 10px 0;">'+esc(n.content)+'</p>'+
        '<small class="muted"><i class="fa-regular fa-clock"></i> '+fd(n.created_at)+(n.author_name?' • '+esc(n.author_name):'')+'</small>'+
        (allowEditDel ?
          '<div style="margin-top:12px;display:flex;gap:8px;">'+
            '<button class="btn alt" style="padding:4px 10px;font-size:12px;" onclick="openEditNews('+n.id+')"><i class="fa-solid fa-pen"></i> '+(lang=='si'?'සංස්කරණය (Edit)':'Edit')+'</button>'+
            '<button class="btn warn" style="padding:4px 10px;font-size:12px;" onclick="deleteNewsItem('+n.id+')"><i class="fa-solid fa-trash"></i> '+(lang=='si'?'මකන්න (Delete)':'Delete')+'</button>'+
          '</div>' : '')+
      '</div>';
    }).join('') : '<p class="muted">'+(lang=='si'?'නිවේදන නැත':'No news available')+'</p>';
  } catch(e) {
    $('newsListBox').innerHTML = '<p class="muted">'+(lang=='si'?'නිවේදන නැත':'No news available')+'</p>';
  }
};

async function submitNewNews(){
  var title = $('newsTitle').value.trim();
  var content = $('newsContent').value.trim();
  var audience = (ME && ME.role === 'librarian') ? 'librarian' : 'all';
  if(!title || !content){
    alert(lang=='si'?'කරුණාකර ශීර්ෂය සහ අන්තර්ගතය පුරවන්න!':'Please fill title and content!');
    return;
  }
  await api('add_news', {}, {title: title, content: content, target_audience: audience});
  alert(lang=='si'?'පුවත සාර්ථකව එකතු කරන ලදී!':'News added successfully!');
  render();
}

function openEditNews(id){
  var n = NEWS_CACHE.find(function(x){ return Number(x.id) === Number(id); });
  if(!n) return;
  var box = $('newsEditBox');
  box.style.display = 'block';
  box.innerHTML =
    '<div class="card" style="border:2px solid var(--warn);">'+
      '<h3><i class="fa-solid fa-pen"></i> '+(lang=='si'?'පුවත සංස්කරණය කරන්න':'Edit News')+'</h3>'+
      '<div style="margin-top:10px;"><label>'+(lang=='si'?'ශීර්ෂය (Title):':'Title:')+'</label><input id="editNewsTitle" value="'+esc(n.title)+'"></div>'+
      '<div style="margin-top:10px;"><label>'+(lang=='si'?'අන්තර්ගතය (Content):':'Content:')+'</label><textarea id="editNewsContent" rows="4">'+esc(n.content)+'</textarea></div>'+
      '<div style="margin-top:12px;display:flex;gap:8px;">'+
        '<button class="btn" onclick="saveEditedNews('+n.id+', \''+esc(n.target_audience || 'librarian')+'\')"><i class="fa-solid fa-check"></i> '+(lang=='si'?'සුරකින්න (Save)':'Save Changes')+'</button>'+
        '<button class="btn alt" onclick="$(\'newsEditBox\').style.display=\'none\'">'+(lang=='si'?'අවලංගු කරන්න':'Cancel')+'</button>'+
      '</div>'+
    '</div>';
  box.scrollIntoView({behavior:'smooth'});
}

async function saveEditedNews(id, audience){
  var title = $('editNewsTitle').value.trim();
  var content = $('editNewsContent').value.trim();
  if(!title || !content){
    alert(lang=='si'?'ශීර්ෂය සහ අන්තර්ගතය අවශ්‍යයි!':'Title and content required!');
    return;
  }
  await api('edit_news', {}, {id: id, title: title, content: content, target_audience: audience || 'librarian'});
  alert(lang=='si'?'පුවත යාවත්කාලීන කරන ලදී!':'News updated successfully!');
  render();
}

async function deleteNewsItem(id){
  if(confirm(lang=='si'?'මෙම පුවත මකා දැමීමට අවශ්‍ය බව ස්ථිරද?':'Are you sure you want to delete this news?')){
    await api('delete_news', {}, {id: id});
    render();
  }
}

render();
</script>
</body>
</html>