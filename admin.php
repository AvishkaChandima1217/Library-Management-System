<?php
require_once __DIR__ . '/db.php';
session_start();
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
$bad = false;
if (isset($_POST['u'])) {
  $r = one('SELECT id,pw FROM users WHERE username=?', [trim($_POST['u'])]);
  if ($r && password_verify((string)($_POST['p'] ?? ''), $r['pw'])) { session_regenerate_id(true); $_SESSION['u'] = $r['id']; header('Location: index.php'); exit; }
  $bad = true;
}
$me = !empty($_SESSION['u']) ? one('SELECT u.*, l.name as lib_name FROM users u LEFT JOIN libraries l ON l.id=u.library_id WHERE u.id=?', [$_SESSION['u']]) : null;

if (!$me) { ?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>පුස්තකාල පද්ධතිය - පිවිසුම</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>if(localStorage.getItem('theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
</head>
<body>
<div class="top"><div class="in">
  <div class="brand"><i class="fa-solid fa-book-open-reader"></i> පුස්තකාල පද්ධතිය</div>
  <button class="theme-btn" onclick="toggleTheme()"><i class="fa-solid fa-circle-half-stroke"></i></button>
</div></div>
<div class="wrap" style="max-width:400px;margin-top:60px">
  <form method="post" class="card" style="box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
    <h2 style="text-align:center;color:var(--ac);margin-bottom:20px;"><i class="fa-solid fa-right-to-bracket"></i> ඇතුළු වන්න</h2>
    <label>පරිශීලක නාමය / ඊමේල් (Username / Email)</label><input name="u" autofocus autocomplete="username">
    <label>මුරපදය (Password)</label><input type="password" name="p" autocomplete="current-password">
    <?php if ($bad) echo '<p style="color:var(--warn);font-size:13px;margin-top:5px;">පරිශීලක නාමය හෝ මුරපදය වැරදියි</p>'; ?>
    <p style="margin-top:20px;"><button class="btn" style="width:100%;justify-content:center;">ඇතුළු වන්න</button></p>
  </form>
</div>
<script>function toggleTheme(){const h=document.documentElement,c=h.getAttribute('data-theme'),n=c==='dark'?'light':'dark';h.setAttribute('data-theme',n);localStorage.setItem('theme',n);}</script>
</body></html>
<?php exit; } ?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>පුස්තකාල කළමනාකරණ පද්ධතිය</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>if(localStorage.getItem('theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
<div class="top"><div class="in">
  <div class="brand"><i class="fa-solid fa-book-open-reader"></i> <span data-i="brand">පුස්තකාල පද්ධතිය</span></div>
  <div class="who" style="display:flex;align-items:center;gap:12px;">
    <button class="theme-btn" onclick="toggleLang()" title="Change Language" style="font-size:13px;font-weight:bold;width:auto;padding:0 12px;border-radius:99px;"><i class="fa-solid fa-language"></i> <span id="langLbl">EN</span></button>
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle Dark/Light Mode"><i class="fa-solid fa-circle-half-stroke"></i></button>
    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($me['name']) ?> (<?= $me['role']==='admin'?'පරිපාලක':($me['role']==='librarian'?'ලයිබ්‍රේරියන් ('.$me['lib_name'].')':'සාමාජික: '.$me['lib_name']) ?>)</span>
    <a href="?logout=1"><i class="fa-solid fa-power-off"></i> පිටවන්න</a>
  </div>
</div></div>

<div class="wrap">
  <div id="libSelectorWrap"></div>
  <nav id="nav"></nav>
  <div id="view"></div>
</div>

<script src="qrcode.js"></script><script src="barcode.js"></script>
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
var IS_ADM = ME.role === 'admin', IS_LIB = ME.role === 'librarian', IS_MEM = ME.role === 'member';
var selectedLib = 0;
var LIBRARIES = [];
var NEWS_CACHE = [];

function getMyLibName() {
  if (ME.lib_name && String(ME.lib_name).trim() !== '') return String(ME.lib_name);
  var map = { 1: 'Mainnoluwa', 2: 'Mahena', 3: 'Dedigama', 4: 'Algama' };
  return map[Number(ME.library_id)] || ('Library #' + (ME.library_id || ''));
}

function libBanner(){
  if(!IS_LIB) return '';
  var myLib = getMyLibName();
  return '<div class="card" style="padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border-left:4px solid var(--ac);">'+
    '<span><i class="fa-solid fa-building-columns" style="color:var(--ac);"></i> <b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> <span class="tag">'+esc(myLib)+'</span></span>'+
    '<small class="muted">'+(lang=='si'?'ලයිබ්‍රේරියන්: ':'Librarian: ')+esc(ME.name)+'</small>'+
  '</div>';
}

function getTabs(){
  if(IS_ADM) return [
    ['dash', '<i class="fa-solid fa-chart-pie"></i> ' + (lang=='si'?'උපකරණ පුවරුව':'Dashboard')],
    ['search', '<i class="fa-solid fa-magnifying-glass"></i> ' + (lang=='si'?'පොත් සොයන්න':'Search Books')],
    ['loans', '<i class="fa-solid fa-book-bookmark"></i> ' + (lang=='si'?'ණයට දුන් පොත්':'Active Loans')],
    ['fines', '<i class="fa-solid fa-coins"></i> ' + (lang=='si'?'දඩ මුදල් (Fines)':'Fines')],
    ['members', '<i class="fa-solid fa-users"></i> ' + (lang=='si'?'සාමාජිකයන් සහ ලයිබ්‍රේරියන්':'Members & Librarians')],
    ['manage', '<i class="fa-solid fa-sliders"></i> ' + (lang=='si'?'කළමනාකරණය':'Management')],
    ['audit', '<i class="fa-solid fa-clipboard-user"></i> ' + (lang=='si'?'විගණනය (Audit)':'System Audit')],
    ['news', '<i class="fa-solid fa-bullhorn"></i> ' + (lang=='si'?'පුවත් සහ නිවේදන':'News & Notices')]
  ];
  if(IS_LIB) return [
    ['dash', '<i class="fa-solid fa-chart-pie"></i> ' + (lang=='si'?'උපකරණ පුවරුව':'Dashboard')],
    ['search', '<i class="fa-solid fa-magnifying-glass"></i> ' + (lang=='si'?'පොත් සොයන්න':'Search Books')],
    ['scan', '<i class="fa-solid fa-qrcode"></i> ' + (lang=='si'?'ස්කෑන් / ණයට දීම':'Scan / Issue')],
    ['loans', '<i class="fa-solid fa-book-bookmark"></i> ' + (lang=='si'?'ණයට දුන් පොත්':'Active Loans')],
    ['members', '<i class="fa-solid fa-users"></i> ' + (lang=='si'?'සාමාජිකයන්':'Members')],
    ['manage', '<i class="fa-solid fa-sliders"></i> ' + (lang=='si'?'කළමනාකරණය':'Management')],
    ['audit', '<i class="fa-solid fa-clipboard-user"></i> ' + (lang=='si'?'විගණනය (Audit)':'System Audit')],
    ['news', '<i class="fa-solid fa-bullhorn"></i> ' + (lang=='si'?'පුවත්':'News')]
  ];
  return [
    ['search', '<i class="fa-solid fa-magnifying-glass"></i> ' + (lang=='si'?'පොත් සොයන්න':'Search Books')],
    ['mine', '<i class="fa-solid fa-book-reader"></i> ' + (lang=='si'?'මගේ ගිණුම':'My Account')],
    ['news', '<i class="fa-solid fa-bullhorn"></i> ' + (lang=='si'?'පුවත්':'News')]
  ];
}

var tab = getTabs()[0][0];
var rows=[], total=0, q='', LD=null, V={};

function $(i){return document.getElementById(i)}
function g(i){return $(i).value}
async function api(a,p,b){
  if(IS_ADM && selectedLib > 0) p = Object.assign({lib_id: selectedLib}, p||{});
  var r = await fetch('api.php?a='+a+'&'+new URLSearchParams(p||{}), b?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)}:{});
  if(r.status===401){location.reload();throw 0}
  var j = await r.json(); if(j.error){alert(j.error);throw new Error(j.error)} return j;
}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]))}
function fd(d){try{return d?new Date(d).toLocaleDateString(lang=='si'?'si-LK':'en-US',{year:'numeric',month:'long',day:'numeric'}):''}catch(e){return d||''}}
function qr(t){var o=qrcode(0,'M');o.addData(t);o.make();return o.createSvgTag({scalable:true,margin:0})}

async function loadLibraries() {
  try {
    LIBRARIES = await api('libraries');
  } catch (e) {
    LIBRARIES = [];
  }
}

function renderLibSelector(){
  if(!IS_ADM) return;
  var opts = '<option value="0">'+(lang=='si'?'සියලුම පුස්තකාල':'All Libraries')+'</option>' +
    LIBRARIES.map(l => '<option value="'+l.id+'">'+esc(l.name)+'</option>').join('');

  $('libSelectorWrap').innerHTML = '<div class="card" style="margin-bottom:20px;padding:15px 20px;display:flex;align-items:center;gap:15px;background:linear-gradient(135deg, var(--ac), #3b82f6);color:#fff;border:none;box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);">'+
    '<b><i class="fa-solid fa-building-columns"></i> '+(lang=='si'?'පුස්තකාලය තෝරන්න:':'Select Library:')+'</b>'+
    '<select id="libSel" onchange="selectedLib=Number(this.value);render()" style="width:250px;padding:8px 12px;border-radius:8px;margin:0;background:var(--card);color:var(--text);border:none;font-weight:600;">'+
    opts +
    '</select></div>';
  if($('libSel')) $('libSel').value = String(selectedLib);
}

function nav(){
  var TABS = getTabs();
  $('nav').innerHTML=TABS.map(t=>'<button class="'+(tab===t[0]?'on':'')+'" onclick="go(\''+t[0]+'\')">'+t[1]+'</button>').join('');
}
function go(t){tab=t; render()}
async function render(){
  $('langLbl').innerText = lang === 'si' ? 'EN' : 'සිං';
  if(IS_ADM) await loadLibraries();
  renderLibSelector(); nav(); var v=$('view'); v.innerHTML=''; await V[tab](v);
}

V.dash = async function(v){
  if(IS_MEM) {
    var s = await api('stats');
    var newsList = [];
    try { newsList = await api('get_news'); } catch(e){}

    v.innerHTML = '<div class="card" style="border-left: 4px solid var(--ac);">'+
      '<h2><i class="fa-solid fa-id-card"></i> '+(lang=='si'?'මගේ ගිණුම':'My Account')+'</h2>'+
      '<p><b>'+(lang=='si'?'නම:':'Name:')+'</b> '+esc(ME.name)+'</p>'+
      '<p><b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> '+esc(s.my_lib)+'</p>'+
      '<h3 style="margin-top:20px;"><i class="fa-solid fa-book"></i> '+(lang=='si'?'දැනට ණයට ගෙන ඇති පොත්':'Current Loans')+'</h3>'+
      (s.current.length ? s.current.map(l=>'<div style="background:var(--bg);padding:10px;border-radius:8px;margin-top:8px;"><b>'+esc(l.title)+'</b> <span class="tag out" style="float:right;">'+(lang=='si'?'ආපසු දිය යුතු: ':'Due: ')+fd(l.due)+'</span></div>').join('') : '<p class="muted">'+(lang=='si'?'පොත් ගෙන නැත':'No books borrowed')+'</p>')+
    '</div>'+

    '<div class="card" style="margin-top:20px;">'+
      '<h2><i class="fa-solid fa-bell" style="color:var(--ac);"></i> '+(lang=='si'?'ප්‍රධාන නිවේදන (Notices)':'Recent Notices')+'</h2>'+
      (newsList.length ? newsList.map(n=>
        '<div class="card" style="background:var(--bg);margin-top:10px;cursor:pointer;border-left:4px solid var(--ac);transition:transform 0.2s;" onclick="go(\'news\')">'+
          '<h4>📢 '+esc(n.title)+'</h4>'+
          '<p style="font-size:13px;color:var(--muted);">'+esc(n.content.substring(0, 100))+'...</p>'+
          '<small class="muted"><i class="fa-regular fa-clock"></i> '+fd(n.created_at)+' - <b style="color:var(--ac);">'+(lang=='si'?'සම්පූර්ණ විස්තරය බැලීමට ක්ලික් කරන්න':'Click to read full news')+'</b></small>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'නිවේදන නොමැත':'No notices available')+'</p>')+
    '</div>';
    return;
  }

  var s = await api('stats');
  var myLib = getMyLibName();
  var headerSub = IS_LIB
    ? ((lang=='si'?'ඔබ දැනට පිවිස සිටින පුස්තකාලය: ':'Current Logged-in Library: ')+'<b>'+esc(myLib)+'</b>')
    : (lang=='si'?'පද්ධතියේ සියලුම පුස්තකාල සහ ක්‍රියාකාරකම් පහසුවෙන් කළමනාකරණය කරන්න.':'Easily manage all library branches and activities.');

  v.innerHTML = '<div class="card" style="background: linear-gradient(135deg, var(--ac), #3b82f6); color: #fff; padding: 25px; border-radius: var(--radius); border:none; box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);">'+
    '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">'+
      '<div>'+
        '<h1 style="margin:0;"><i class="fa-solid fa-gauge-high"></i> '+(lang=='si'?(IS_LIB?'ලයිබ්‍රේරියන් උපකරණ පුවරුව':'පරිපාලක උපකරණ පුවරුව'):(IS_LIB?'Librarian Dashboard':'Admin Dashboard'))+'</h1>'+
        '<p style="opacity:0.9; margin:6px 0 0 0;">'+headerSub+'</p>'+
      '</div>'+
      (IS_LIB ? '<span class="tag" style="background:rgba(255,255,255,0.2);color:#fff;font-size:14px;padding:8px 14px;border-radius:999px;"><i class="fa-solid fa-building-columns"></i> '+esc(myLib)+'</span>' : '')+
    '</div>'+
  '</div>'+

  '<div class="tiles" style="margin-top: 20px;">'+
    '<div class="tile"><b>'+s.titles+'</b><span>'+(lang=='si'?'පොත් මාතෘකා':'Book Titles')+'</span></div>'+
    '<div class="tile"><b>'+s.copies+'</b><span>'+(lang=='si'?'පොත් පිටපත්':'Book Copies')+'</span></div>'+
    '<div class="tile"><b>'+s.lent+'</b><span>'+(lang=='si'?'ණයට දුන් පොත්':'Lent Books')+'</span></div>'+
    '<div class="tile bad"><b>'+s.overdue+'</b><span>'+(lang=='si'?'පරක්කු වූ පොත්':'Overdue Books')+'</span></div>'+
    '<div class="tile"><b>'+s.members+'</b><span>'+(lang=='si'?'සාමාජිකයන්':'Members')+'</span></div>'+
  '</div>'+

  '<div class="card" style="margin-top:20px;">'+
    '<h2><i class="fa-solid fa-bolt" style="color:var(--ac);"></i> '+(lang=='si'?'ක්ෂණික ක්‍රියාමාර්ග (Quick Actions)':'Quick Actions')+'</h2>'+
    '<div class="row" style="gap:12px; margin-top:15px; display:flex; flex-wrap:wrap;">'+
      (IS_ADM ? '<button class="btn" onclick="go(\'manage\')"><i class="fa-solid fa-user-plus"></i> '+(lang=='si'?'ලයිබ්‍රේරියන් කෙනෙක් එකතු කරන්න':'Add Librarian')+'</button>' : '<button class="btn" onclick="go(\'manage\')"><i class="fa-solid fa-user-plus"></i> '+(lang=='si'?'සාමාජිකයකු එකතු කරන්න':'Add Member')+'</button>')+
      '<button class="btn" onclick="go(\'audit\')"><i class="fa-solid fa-file-arrow-down"></i> '+(lang=='si'?'විගණන වාර්තා බාගන්න':'Download Audit Reports')+'</button>'+
      '<button class="btn" onclick="go(\'news\')"><i class="fa-solid fa-bullhorn"></i> '+(lang=='si'?'නිවේදන බලන්න':'View Notices')+'</button>'+
    '</div>'+
  '</div>'+

  '<div class="card" style="margin-top:20px;">'+
    '<h2><i class="fa-solid fa-download"></i> '+(lang=='si'?'විශේෂිත වාර්තා සහ බාගත කිරීම් (Reports & Downloads)':'Special Reports & Downloads')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'අවශ්‍ය කාලසීමාව තෝරා අදාළ වාර්තාව CSV ලෙස බාගන්න.':'Select period and download reports as CSV.')+'</p>'+

    '<div class="cols" style="margin:15px 0;">'+
      '<div><label>'+(lang=='si'?'මුල් දිනය (Start Date):':'Start Date:')+'</label><input type="date" id="repStart" value="'+new Date().toISOString().substring(0,8)+'01"></div>'+
      '<div><label>'+(lang=='si'?'අවසන් දිනය (End Date):':'End Date:')+'</label><input type="date" id="repEnd" value="'+new Date().toISOString().substring(0,10)+'"></div>'+
    '</div>'+

    '<div class="row" style="gap:10px; flex-wrap:wrap;">'+
      '<button class="btn" onclick="downloadReport(\'books_period\')"><i class="fa-solid fa-file-excel"></i> '+(lang=='si'?'පොත් විස්තර වාර්තාව':'Books Details Report')+'</button>'+
      '<button class="btn" onclick="downloadReport(\'members_age\')"><i class="fa-solid fa-users"></i> '+(lang=='si'?'සාමාජික වයස් කාණ්ඩ (18ට වැඩි/අඩු)':'Members Age Group Report')+'</button>'+
      '<button class="btn warn" onclick="downloadReport(\'overdue_loans\')"><i class="fa-solid fa-triangle-exclamation"></i> '+(lang=='si'?'ප්‍රමාද වූ පොත් සහ දඩ වාර්තාව':'Overdue & Fine Report')+'</button>'+
    '</div>'+
  '</div>';
};

async function downloadReport(type){
  var start = $('repStart').value;
  var end = $('repEnd').value;
  var data = await api('download_report', {report_type: type, start: start, end: end});

  if(!data.length){ alert(lang=='si'?'දත්ත හමු නොවීය!':'No data found!'); return; }

  if(type === 'members_age') {
    var today = new Date();
    data = data.map(m => {
      var age = m.dob ? Math.floor((today - new Date(m.dob)) / (365.25 * 24 * 60 * 60 * 1000)) : 'N/A';
      return Object.assign({}, m, { age: age, category: (age !== 'N/A' && age < 18) ? 'Under 18 (18ට අඩු)' : '18 and Above (18ට වැඩි)' });
    });
  }

  var keys = Object.keys(data[0]);
  var csv = keys.join(',') + '\n';
  data.forEach(row => {
    csv += keys.map(k => '"'+(row[k]||'')+'"').join(',') + '\n';
  });

  var blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = type + '_report_' + start + '_to_' + end + '.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

V.search = function(v){
  var subText = IS_MEM ? '<div class="card" style="background:var(--bg);margin-bottom:15px;"><i class="fa-solid fa-building-columns"></i> '+(lang=='si'?'ඔබ ලියාපදිංචි වී ඇති පුස්තකාලයේ පොත් ලැයිස්තුව':'Books available in your registered library')+'</div>' : '';

  v.innerHTML = libBanner() + subText +
    '<div class="card" style="border: 2px solid var(--ac); box-shadow: 0 4px 15px rgba(79,70,229,0.15);">'+
      '<h3><i class="fa-solid fa-barcode"></i> '+(lang=='si'?'බාර්කොඩ් ස්කෑන් කරන්න හෝ පොතේ නම/කතෘ ලියන්න':'Scan Barcode or Type Book Name/Author')+'</h3>'+
      '<div class="row" style="display:flex;gap:10px;margin-top:10px;">'+
        '<input class="big" id="q" autofocus placeholder="'+(lang=='si'?'🔍 Barcode එක ස්කෑන් කරන්න හෝ නම/කතෘ ලියන්න...':'🔍 Scan Barcode or type Name/Author...')+'" value="'+esc(q)+'" oninput="handleSearchInput(this.value)" onkeypress="if(event.key===\'Enter\') handleSearchInput(this.value, true)" style="flex:1;margin:0;">'+
      '</div>'+
    '</div>'+
    '<div id="barcodeDetailRes" style="margin-bottom:15px;"></div>'+
    '<div id="res"></div>';

  LD={a:'search',p:()=>({q:q}),f:(b,i)=>{
    var a=b.copies-b.lent;
    return '<div class="card" style="transition:all 0.2s;"><div class="item" onclick="tog('+i+')" style="padding:4px 0;"><div><b>'+esc(b.title)+'</b> <span class="tag">'+esc(b.lib_name)+'</span><br><small class="muted">'+esc(b.author)+'</small></div><span class="tag'+(a?'':' out')+'" style="font-size:14px;padding:6px 12px;">'+a+' / '+b.copies+' '+(lang=='si'?'ඇත':'available')+'</span></div>'+
    (b.c?'<div class="cps">'+b.c.map(x=>'<div class="cp"><span><b>'+esc(x.code)+'</b> <div style="display:inline-block;background:#fff;padding:2px;margin-left:10px;border-radius:4px;">'+qr(x.code)+'</div></span>'+(x.due?'<span class="tag out">'+(lang=='si'?'ආපසු ':'Due ') + fd(x.due)+'</span>':'<span class="tag">'+(lang=='si'?'ඇත':'Available')+'</span>')+'</div>').join('')+'</div>':'')+'</div>';
  }};
  load(1);
};

async function handleSearchInput(val, forceLookup = false) {
  q = val;
  setTimeout(() => load(1), 250);

  val = val.trim();
  var detailDiv = $('barcodeDetailRes');
  if(!val) { detailDiv.innerHTML = ''; return; }

  if(forceLookup || (val.toUpperCase().startsWith('B') && val.length >= 5)) {
    try {
      var j = await api('book', {code: val});
      var b = j.book;
      if(b) {
        var o = j.loan;
        detailDiv.innerHTML = '<div class="card" style="background:var(--card); border: 2px solid #22c55e; box-shadow: 0 4px 15px rgba(34,197,94,0.15);">'+
          '<h3><i class="fa-solid fa-circle-check" style="color:#22c55e;"></i> '+(lang=='si'?'ස්කෑන් කළ පොතේ සම්පූර්ණ විස්තර':'Scanned Book Full Details')+'</h3>'+
          '<div class="detail" style="display:flex;gap:20px;align-items:center;margin-top:15px;">'+
            '<div><div class="qr" style="background:#fff;padding:8px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">'+qr(b.code)+'</div></div>'+
            '<div class="grow1" style="display:flex;flex-direction:column;gap:5px;">'+
              '<h2 style="margin:0;">'+esc(b.title)+'</h2>'+
              '<p style="margin:0;"><b>'+(lang=='si'?'කතෘ:':'Author:')+'</b> '+esc(b.author)+'</p>'+
              '<p style="margin:0;"><i class="fa-solid fa-building-columns"></i> <b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> '+esc(b.lib_name)+'</p>'+
              '<p style="margin:0;"><b>'+(lang=='si'?'පොත් කේතය (Barcode):':'Book Code (Barcode):')+'</b> '+esc(b.code)+'</p>'+
              '<p style="margin:5px 0 0 0;"><span class="tag '+(o?'out':'')+'">'+(o ? (lang=='si'?'ණයට දී ඇත (Lent Out)':'Lent Out') : (lang=='si'?'පුස්තකාලයේ ඇත (Available)':'Available in Library'))+'</span></p>'+
            '</div>'+
          '</div>'+
          (o ? '<div class="kv" style="margin-top:15px;font-size:14px;background:var(--bg);padding:12px;border-radius:8px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;"><div><span class="muted">'+(lang=='si'?'ගත් අය':'Borrower')+'</span><br><b>'+esc(o.name)+'</b></div><div><span class="muted">'+(lang=='si'?'දුරකථන':'Phone')+'</span><br><b>'+esc(o.phone)+'</b></div><div><span class="muted">'+(lang=='si'?'ආපසු දිය යුතු දිනය':'Due Date')+'</span><br><b>'+fd(o.due)+'</b></div></div>' : '')+
        '</div>';
      } else {
        detailDiv.innerHTML = '';
      }
    } catch(e) {
      detailDiv.innerHTML = '';
    }
  } else {
    detailDiv.innerHTML = '';
  }
}

async function load(r){if(r)rows=[];var j=await api(LD.a,Object.assign({off:rows.length},LD.p()));rows=rows.concat(j.rows);total=j.total;paint()}
function paint(){$('res').innerHTML='<p class="muted" style="margin-bottom:10px;"><i class="fa-solid fa-list"></i> '+total+' '+(lang=='si'?'ප්‍රතිඵල':'results')+'</p>'+rows.map(LD.f).join('')+(rows.length<total?'<p style="text-align:center;margin-top:20px;"><button class="btn alt" onclick="load()" style="padding:10px 30px;"><i class="fa-solid fa-arrow-down"></i> '+(lang=='si'?'තව පෙන්වන්න':'Load More')+'</button></p>':'')}
async function tog(i){var b=rows[i];b.c=b.c?null:await api('copies',{title:b.title,author:b.author});paint()}

V.scan = function(v){
  v.innerHTML = libBanner() + '<div class="card" style="border: 2px solid var(--ac);">'+
    '<h2><i class="fa-solid fa-qrcode"></i> '+(lang=='si'?'පොත් කේතය / නම / කතෘ ස්කෑන් හෝ ටයිප් කරන්න':'Scan or Type Book Code / Name / Author')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'පොත නිකුත් කිරීමට හෝ ආපසු ලබා ගැනීමට Barcode එක ඇතුළත් කරන්න.':'Enter barcode to issue or return book.')+'</p>'+
    '<div class="row" style="display:flex;gap:10px;margin-top:15px;">'+
      '<input class="big" id="sc" placeholder="'+(lang=='si'?'උදා: B00001 හෝ පොතේ නම':'e.g. B00001 or Book Name')+'" onkeypress="if(event.key===\'Enter\') look()" style="flex:1;margin:0;">'+
      '<button class="btn" onclick="look()"><i class="fa-solid fa-magnifying-glass"></i> '+(lang=='si'?'සොයන්න':'Search')+'</button>'+
    '</div>'+
  '</div><div id="det"></div>';
};

async function look(){
  var c=g('sc').trim(); if(!c) return;
  var d=$('det');
  try {
    var j = await api('book',{code:c}), b = j.book;
    if(!b){d.innerHTML='<div class="card" style="border-left:4px solid var(--warn);"><p style="color:var(--warn);margin:0;"><i class="fa-solid fa-triangle-exclamation"></i> '+(lang=='si'?'පොත හමු නොවීය!':'Book not found!')+'</p></div>';return}
    var o = j.loan, copiesList = j.all_copies || [];
    var h = '<div class="card" style="border-top:4px solid var(--ac);"><div class="detail" style="display:flex;gap:20px;align-items:center;"><div><div class="qr" style="background:#fff;padding:8px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">'+qr(b.code)+'</div></div><div class="grow1" style="display:flex;flex-direction:column;gap:5px;"><h2 style="margin:0;">'+esc(b.title)+'</h2>'+
            '<p style="margin:0;"><b>'+(lang=='si'?'කතෘ:':'Author:')+'</b> '+esc(b.author)+'</p>'+
            '<p style="margin:0;"><i class="fa-solid fa-building-columns"></i> <b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> '+esc(b.lib_name)+'</p>'+
            '<p style="margin:0;"><b>'+(lang=='si'?'පොත් කේතය (Code):':'Book Code:')+'</b> '+esc(b.code)+'</p>'+
            '<p style="margin:5px 0 0 0;"><span class="tag'+(o?' out':'')+'">'+(o?(lang=='si'?'ණයට දී ඇත':'Lent Out'):(lang=='si'?'පුස්තකාලයේ ඇත':'Available in Library'))+'</span></p></div></div>';

    if(copiesList.length > 1) {
      h += '<h3 style="margin-top:20px;margin-bottom:10px;"><i class="fa-solid fa-copy"></i> '+(lang=='si'?'මෙම පොතේ අනෙකුත් පිටපත් සහ තත්ත්වය:':'Other copies of this book:')+'</h3>'+
           '<div class="cps" style="display:flex;flex-direction:column;gap:8px;">' + copiesList.map(cp => '<div class="cp" style="background:var(--card);padding:8px 12px;border-radius:6px;border:1px solid var(--line);"><span><b>'+esc(cp.code)+'</b></span> ' + (cp.due ? '<span class="tag out">'+(lang=='si'?'ආපසු දිය යුතු: ':'Due: ')+fd(cp.due)+'</span>' : '<span class="tag">'+(lang=='si'?'ඇත':'Available')+'</span>') + '</div>').join('') + '</div>';
    }

    if(IS_LIB){
      if(o) h+='<div class="card" style="background:var(--bg);margin-top:20px;border-left:4px solid var(--warn);"><h3><i class="fa-solid fa-user-check"></i> '+(lang=='si'?'වත්මන් ණය විස්තර':'Current Loan Details')+'</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:10px 0;"><div><span class="muted">ගත් අය</span><br><b>'+esc(o.name)+' ('+esc(o.username)+')</b></div><div><span class="muted">දුරකථන</span><br><b>'+esc(o.phone)+'</b></div><div><span class="muted">ආපසු දිය යුතු දිනය</span><br><b>'+fd(o.due)+'</b></div></div><button class="btn warn" onclick="returnBook(\''+b.code+'\')" style="margin-top:10px;"><i class="fa-solid fa-rotate-left"></i> '+(lang=='si'?'පොත ආපසු භාරගන්න':'Return Book')+'</button></div>';
      else h+='<div class="card" style="background:var(--bg);margin-top:20px;border-left:4px solid var(--success);"><h3><i class="fa-solid fa-hand-holding-hand"></i> '+(lang=='si'?'පොත ණයට දීම (Issue Book)':'Issue Book')+'</h3><div class="cols" style="margin-top:10px;"><div><label>'+(lang=='si'?'සාමාජිකයාගේ Username හෝ Member ID':'Member Username or ID')+'</label><input id="mu" placeholder="M1 හෝ username" style="margin:5px 0 0 0;"></div></div><button class="btn" onclick="lendBook(\''+b.code+'\')" style="margin-top:15px;background:var(--success);"><i class="fa-solid fa-check"></i> '+(lang=='si'?'පොත ණයට දෙන්න (උපරිම 2ක්)':'Lend Book (Max 2)')+'</button></div>';
    }
    d.innerHTML=h+'</div>';
  } catch(e){ d.innerHTML='<div class="card" style="border-left:4px solid var(--warn);"><p style="color:var(--warn);margin:0;"><i class="fa-solid fa-triangle-exclamation"></i> දෝෂයක් සිදු විය.</p></div>'; }
}

async function lendBook(code){
  var username = g('mu').trim();
  if(!username){ alert(lang=='si'?'කරුණාකර සාමාජිකයාගේ නම හෝ ID ලියන්න!':'Please enter member username or ID!'); return; }
  await api('lend',{},{code:code, username:username});
  alert(lang=='si'?'පොත සාර්ථකව ණයට දෙන ලදී!':'Book successfully lent!'); look();
}

async function returnBook(code){
  await api('return',{},{code:code});
  alert(lang=='si'?'පොත සාර්ථකව ආපසු භාරගන්නා ලදී!':'Book successfully returned!'); look();
}

V.loans = async function(v){
  var r = await api('loans');
  var today = new Date();
  var fineLoans = [];

  r.forEach(l => {
    var dueDate = new Date(l.due);
    var diffTime = today - dueDate;
    var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays > 0 && !Number(l.fine_paid)) {
      l.overdue_days = diffDays;
      l.fine_amount = diffDays * 5.00;
      fineLoans.push(l);
    }
  });

  v.innerHTML = libBanner() + '<div class="card">'+
    '<h2><i class="fa-solid fa-book-bookmark"></i> '+(lang=='si'?'ණයට දුන් පොත් කළමනාකරණය':'Lent Books Management')+'</h2>'+

    '<div style="display:flex;gap:10px;margin:20px 0;border-bottom:2px solid var(--line);padding-bottom:15px;">'+
      '<button class="btn" id="btnTab1" onclick="switchLoanTab(1)" style="border-radius:8px;"><i class="fa-solid fa-users"></i> '+(lang=='si'?'සාමාජිකයන් ළඟ ඇති පොත් (':'Members Books (') + r.length + ')</button>'+
      '<button class="btn warn" id="btnTab2" onclick="switchLoanTab(2)" style="border-radius:8px;"><i class="fa-solid fa-triangle-exclamation"></i> '+(lang=='si'?'දඩ ගෙවීම් (Fines) (':'Fine Payments (') + fineLoans.length + ')</button>'+
    '</div>'+

    '<div id="loanTab1Content">'+
      '<h3 style="margin-bottom:15px;">'+(lang=='si'?'සාමාජිකයන් විසින් ගෙන ගිය පොත් ලැයිස්තුව':'List of books taken by members')+'</h3>'+
      (r.length ? r.map(l=>
        '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;">'+
          '<div>'+
            '<h4 style="margin:0 0 5px 0;">'+esc(l.title)+' <span class="tag" style="font-weight:normal;">Code: '+esc(l.code)+'</span></h4>'+
            '<p style="margin:0;color:var(--muted);"><i class="fa-solid fa-user"></i> <b>'+(lang=='si'?'සාමාජිකයා:':'Member:')+'</b> '+esc(l.name)+' ('+esc(l.phone)+')</p>'+
          '</div>'+
          '<div style="text-align:right;">'+
            '<span class="tag out">'+(lang=='si'?'ආපසු දිය යුතු: ':'Due: ')+fd(l.due)+'</span>'+
          '</div>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'පොත් ගෙන ගොස් නැත':'No books lent')+'</p>')+
    '</div>'+

    '<div id="loanTab2Content" style="display:none;">'+
      '<h3 style="margin-bottom:15px;color:var(--warn);"><i class="fa-solid fa-triangle-exclamation"></i> '+(lang=='si'?'දින 14 ඉක්මවූ ප්‍රමාද වූ පොත් සහ දඩ මුදල් (රු. 5.00 / දවසකට)':'Overdue books & Fines (Rs. 5.00 per day)')+'</h3>'+
      (fineLoans.length ? fineLoans.map(l=>
        '<div class="card" style="background:var(--bg);border-left:4px solid var(--warn);margin-top:12px;">'+
          '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">'+
            '<div>'+
              '<h4 style="margin:0 0 5px 0;">'+esc(l.title)+' (Code: '+esc(l.code)+')</h4>'+
              '<p style="margin:0 0 5px 0;"><i class="fa-solid fa-user"></i> <b>'+(lang=='si'?'සාමාජිකයා:':'Member:')+'</b> '+esc(l.name)+' | <b>Phone:</b> '+esc(l.phone)+'</p>'+
              '<p style="margin:0;"><span class="tag out">'+(lang=='si'?'ප්‍රමාද වූ දින: ':'Overdue Days: ')+l.overdue_days+'</span> | <b>'+(lang=='si'?'ගෙවිය යුතු දඩ මුදලය:':'Total Fine:')+'</b> <span style="color:var(--warn);font-size:16px;">Rs. '+l.fine_amount.toFixed(2)+'</span></p>'+
              '<small class="muted" style="display:block;margin-top:5px;">'+(lang=='si'?'ආපසු දිය යුතු දිනය තිබුණේ:':'Original Due Date:')+' '+fd(l.due)+'</small>'+
            '</div>'+
            '<div style="display:flex;gap:8px;flex-wrap:wrap;">'+
              '<button class="btn" style="font-size:12px;padding:8px 14px;background:var(--success);border-color:var(--success);" onclick="markFinePaid('+l.id+')"><i class="fa-solid fa-check"></i> '+(lang=='si'?'දඩය ගෙවන ලදී':'Mark Paid')+'</button>'+
              (l.email ? '<a href="mailto:'+esc(l.email)+'?subject=Library Overdue Notice&body=Dear '+encodeURIComponent(l.name)+', You have a fine of Rs. '+l.fine_amount.toFixed(2)+' for the overdue book: '+encodeURIComponent(l.title)+'" class="btn" style="font-size:12px;padding:8px 14px;text-decoration:none;"><i class="fa-solid fa-envelope"></i> '+(lang=='si'?'ඊමේල් යවන්න':'Send Email')+'</a>' : '<span class="muted">Email N/A</span>')+
            '</div>'+
          '</div>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'ප්‍රමාද වූ පොත් හෝ දඩ ගෙවීම් නොමැත':'No overdue books or fines')+'</p>')+
    '</div>'+
  '</div>';
};

function switchLoanTab(tabNo) {
  if(tabNo === 1) {
    $('loanTab1Content').style.display = 'block';
    $('loanTab2Content').style.display = 'none';
  } else {
    $('loanTab1Content').style.display = 'none';
    $('loanTab2Content').style.display = 'block';
  }
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

async function markFinePaid(loanId){
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
        bookTitle: l.title,
        bookCode: l.code,
        libName: l.lib_name || '',
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
            '<h4 style="margin:0 0 4px 0;"><i class="fa-solid fa-user"></i> '+esc(item.memberName)+' <small class="muted">(ID: M'+esc(item.userId)+')</small>'+(item.libName ? ' <span class="tag">'+esc(item.libName)+'</span>' : '')+'</h4>'+
            '<small class="muted">'+(lang=='si'?'පොත: ':'Book: ')+esc(item.bookTitle)+' ('+esc(item.bookCode)+') • '+item.extraDays+(lang=='si'?' අමතර දින':' extra days')+'</small>'+
          '</div>'+
          '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'+
            '<span class="tag out" style="font-size:14px;padding:6px 12px;">Rs. '+item.fine.toFixed(2)+'</span>'+
            '<button class="btn" style="padding:6px 12px;font-size:12px;background:var(--success);border-color:var(--success);" onclick="markFinePaid('+item.loanId+')"><i class="fa-solid fa-check"></i> '+(lang=='si'?'ගෙවන ලදී':'Mark Paid')+'</button>'+
          '</div>'+
        '</div>';
      }).join('') : '<p class="muted" style="margin-top:12px;">'+(lang=='si'?'දැනට දඩ ගෙවිය යුතු සාමාජිකයන් කිසිවෙකු නොමැත.':'No members with pending fines.')+'</p>')+
    '</div>';
};

V.members = async function(v){
  var mems = await api('members');
  var libs = IS_ADM ? await api('librarians') : [];

  var today = new Date();
  var under18 = [];
  var above18 = [];

  mems.rows.forEach(u => {
    if(u.dob) {
      var birthDate = new Date(u.dob);
      var age = today.getFullYear() - birthDate.getFullYear();
      var m = today.getMonth() - birthDate.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) { age--; }

      if(age < 18) {
        u.calc_age = age;
        under18.push(u);
      } else {
        u.calc_age = age;
        above18.push(u);
      }
    } else {
      above18.push(u);
    }
  });

  var libsSectionHtml = IS_ADM ? '<div class="card">'+
    '<h2><i class="fa-solid fa-user-tie"></i> '+(lang=='si'?'පුස්තකාල ලයිබ්‍රේරියන්වරුන්':'Library Librarians')+'</h2>'+
    (libs.length ? libs.map(l=>
      '<div class="card" style="background:var(--bg);margin-top:12px;border-left:4px solid var(--ac);display:flex;justify-content:space-between;align-items:center;">'+
        '<div>'+
          '<h4 style="margin:0 0 5px 0;">'+esc(l.name)+' <span class="tag">'+esc(l.lib_name)+'</span></h4>'+
          '<p style="margin:0;color:var(--muted);"><i class="fa-solid fa-id-badge"></i> <b>Reg/Emp No:</b> '+esc(l.username)+' | <i class="fa-solid fa-phone"></i> '+esc(l.phone || 'N/A')+'</p>'+
        '</div>'+
      '</div>'
    ).join('') : '<p class="muted">'+(lang=='si'?'ලයිබ්‍රේරියන්වරුන් නොමැත':'No librarians found')+'</p>')+
  '</div>' : '';

  v.innerHTML = libBanner() + libsSectionHtml +

  '<div class="card" style="'+(IS_ADM ? 'margin-top:20px;' : '')+'">'+
    '<h2><i class="fa-solid fa-users"></i> '+(lang=='si'?'සාමාජිකයන් කළමනාකරණය (වයස් කාණ්ඩ අනුව)':'Members Management (By Age)')+'</h2>'+

    '<div style="display:flex;gap:10px;margin:20px 0;border-bottom:2px solid var(--line);padding-bottom:15px;">'+
      '<button class="btn" id="btnAge1" onclick="switchAgeTab(1)" style="border-radius:8px;"><i class="fa-solid fa-child"></i> '+(lang=='si'?'වයස 18ට අඩු (':'Under 18 (') + under18.length + ')</button>'+
      '<button class="btn" id="btnAge2" onclick="switchAgeTab(2)" style="border-radius:8px;"><i class="fa-solid fa-user"></i> '+(lang=='si'?'වයස 18ට වැඩි (':'18 and Above (') + above18.length + ')</button>'+
    '</div>'+

    '<div id="ageTab1Content">'+
      '<h3 style="margin-bottom:15px;">'+(lang=='si'?'වයස අවුරුදු 18ට අඩු සාමාජිකයන්':'Members under 18 years old')+'</h3>'+
      (under18.length ? under18.map(u=>
        '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;">'+
          '<div>'+
            '<h4 style="margin:0 0 5px 0;">'+esc(u.name)+' (ID: M'+u.id+') <span class="tag">Age: '+u.calc_age+' yrs</span></h4>'+
            '<p style="margin:0;color:var(--muted);"><i class="fa-solid fa-phone"></i> '+esc(u.phone)+' | <i class="fa-solid fa-building-columns"></i> '+esc(u.lib_name)+' | <b>DOB:</b> '+esc(u.dob)+'</p>'+
          '</div>'+
          '<div style="background:#fff;padding:4px;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">'+qr('M'+u.id)+'</div>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'සාමාජිකයන් නොමැත':'No members found')+'</p>')+
    '</div>'+

    '<div id="ageTab2Content" style="display:none;">'+
      '<h3 style="margin-bottom:15px;">'+(lang=='si'?'වයස අවුරුදු 18ට වැඩි සාමාජිකයන්':'Members 18 years and above')+'</h3>'+
      (above18.length ? above18.map(u=>
        '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;">'+
          '<div>'+
            '<h4 style="margin:0 0 5px 0;">'+esc(u.name)+' (ID: M'+u.id+') '+ (u.calc_age !== undefined ? '<span class="tag">Age: '+u.calc_age+' yrs</span>' : '') +' <span class="tag">'+esc(u.lib_name)+'</span></h4>'+
            '<p style="margin:0;color:var(--muted);"><i class="fa-solid fa-phone"></i> '+esc(u.phone)+' | <b>NIC:</b> '+esc(u.nic || 'N/A')+'</p>'+
          '</div>'+
          '<div style="background:#fff;padding:4px;border-radius:6px;box-shadow:0 2px 4px rgba(0,0,0,0.05);">'+qr('M'+u.id)+'</div>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'සාමාජිකයන් නොමැත':'No members found')+'</p>')+
    '</div>'+
  '</div>';
};

function switchAgeTab(tabNo) {
  if(tabNo === 1) {
    $('ageTab1Content').style.display = 'block';
    $('ageTab2Content').style.display = 'none';
  } else {
    $('ageTab1Content').style.display = 'none';
    $('ageTab2Content').style.display = 'block';
  }
}

V.manage = async function(v){
  var libs = IS_ADM ? await api('librarians') : [];
  var todayStr = new Date().toISOString().substring(0, 10);

  var addLibraryHtml = IS_ADM ?
    '<div class="card" style="border:2px solid var(--ac); box-shadow: 0 4px 15px rgba(79,70,229,0.15); margin-bottom:20px;">'+
      '<h2><i class="fa-solid fa-building-circle-plus" style="color:var(--ac);"></i> '+(lang=='si'?'නව පුස්තකාලයක් එකතු කරන්න (Add New Library)':'Add New Library')+'</h2>'+
      '<p class="muted">'+(lang=='si'?'අලුත් පුස්තකාල ශාඛාවක නම ඇතුළත් කර පද්ධතියට එක් කරන්න.':'Enter a new library branch name to add it to the system.')+'</p>'+
      '<div class="row" style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">'+
        '<input id="newLibName" placeholder="'+(lang=='si'?'උදා: Kegalle Library':'e.g. Kegalle Library')+'" style="flex:1;min-width:220px;margin:0;">'+
        '<button class="btn" onclick="addNewLibrary()"><i class="fa-solid fa-plus"></i> '+(lang=='si'?'පුස්තකාලය එකතු කරන්න':'Add Library')+'</button>'+
      '</div>'+
    '</div>' : '';

  var libSelectHtml = '';
  if (IS_ADM) {
    var defaultLib = selectedLib > 0 ? selectedLib : (LIBRARIES[0] ? LIBRARIES[0].id : 1);
    libSelectHtml = '<div style="margin-bottom:15px;">'+
      '<label><b>'+(lang=='si'?'අදාළ පුස්තකාලය තෝරන්න (Select Library):':'Select Library for Librarian:')+'</b></label>'+
      '<select id="libIdForAdd" style="margin-top:5px;">'+
      LIBRARIES.map(l => '<option value="'+l.id+'" '+(defaultLib==l.id?'selected':'')+'>'+esc(l.name)+'</option>').join('')+
      '</select>'+
    '</div>';
  }

  var addSingleBookHtml =
    '<div class="card" style="border:2px solid var(--ac); margin-bottom:20px;">'+
      '<h2><i class="fa-solid fa-book-medical" style="color:var(--ac);"></i> '+(lang=='si'?'තනි පොතක් එකතු කිරීම (Add Single Book)':'Add Single Book')+'</h2>'+
      '<div class="cols" style="margin-top:12px;">'+
        '<div><label>'+(lang=='si'?'පරිග්‍රහණ අංකය / Barcode (හිස් නම් ස්වයංක්‍රීයව සෑදේ):':'Accession No / Barcode (Auto if blank):')+'</label><input id="admBookCode" placeholder="උදා: B00100"></div>'+
        '<div><label>'+(lang=='si'?'ලැබුණ දිනය (Received Date):':'Received Date:')+'</label><input type="date" id="admBookDate" value="'+todayStr+'"></div>'+
      '</div>'+
      '<div class="cols" style="margin-top:10px;">'+
        '<div><label>'+(lang=='si'?'පොතේ නම (Book Title):':'Book Title:')+'</label><input id="admBookTitle" placeholder="පොතේ නම"></div>'+
        '<div><label>'+(lang=='si'?'කතෘ (Author):':'Author:')+'</label><input id="admBookAuthor" placeholder="කතෘගේ නම"></div>'+
      '</div>'+
      '<div class="cols" style="margin-top:10px;">'+
        '<div><label>'+(lang=='si'?'ලැබුණු ආකාරය:':'How Received:')+'</label>'+
          '<select id="admBookRecType">'+
            '<option value="purchase">'+(lang=='si'?'මිලදී ගැනීම් (Purchase)':'Purchase')+'</option>'+
            '<option value="donation">'+(lang=='si'?'පරිත්‍යාග (Donation)':'Donation')+'</option>'+
          '</select>'+
        '</div>'+
        '<div><label>'+(lang=='si'?'විස්තරය (Description):':'Description:')+'</label><input id="admBookRecDesc" placeholder="සැපයුම්කරු / පරිත්‍යාග විස්තරය"></div>'+
      '</div>'+
      '<div style="margin-top:14px;"><button class="btn" onclick="addSingleBookAdmin()"><i class="fa-solid fa-plus"></i> '+(lang=='si'?'පොත එකතු කරන්න':'Add Book')+'</button></div>'+
      '<div id="admSingleBcBox" style="margin-top:12px;"></div>'+
    '</div>';

  var addMemberHtml = IS_LIB ?
    '<div class="card" style="border:2px solid var(--ac); box-shadow: 0 4px 15px rgba(79,70,229,0.15); margin-bottom:20px;">'+
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
    '</div>' : '';

  var adminLibrarianHtml = IS_ADM ?
    '<div class="card" style="border:2px solid var(--ac); box-shadow: 0 4px 15px rgba(79,70,229,0.15); margin-bottom:20px;">'+
      '<h2><i class="fa-solid fa-user-shield" style="color:var(--ac);"></i> '+(lang=='si'?'නව ලයිබ්‍රේරියන්වරයකු එකතු කිරීම (OTP තහවුරු කිරීම)':'Add New Librarian with OTP Verification')+'</h2>'+
      libSelectHtml +
      '<div class="cols" style="margin-top:15px;">'+
        '<div><label>'+(lang=='si'?'සම්පූර්ණ නම:':'Full Name:')+'</label><input id="libName" placeholder="ජයන්ත පෙරේරා"></div>'+
        '<div><label>'+(lang=='si'?'සේවක අංකය / Reg No:':'Employee / Reg Number:')+'</label><input id="libEmpNo" placeholder="LIB101"></div>'+
      '</div>'+
      '<div class="cols" style="margin-top:10px;">'+
        '<div><label>'+(lang=='si'?'දුරකථන අංකය:':'Phone Number:')+'</label><input id="libPhone" placeholder="0712345678"></div>'+
        '<div><label>'+(lang=='si'?'ඊමේල් ලිපිනය:':'Email Address:')+'</label><input type="email" id="libEmail" placeholder="librarian@library.com"></div>'+
      '</div>'+
      '<div style="margin-top:10px;">'+
        '<label>'+(lang=='si'?'මුරපදය:':'Password:')+'</label>'+
        '<input id="libPassword" placeholder="මුරපදයක් ලියන්න">'+
      '</div>'+
      '<div id="otpSection" style="margin-top:15px;">'+
        '<button class="btn" onclick="sendLibrarianOTP()"><i class="fa-solid fa-paper-plane"></i> '+(lang=='si'?'OTP එකක් ජනනය කරන්න':'Generate OTP')+'</button>'+
      '</div>'+
    '</div>'+
    '<div class="card">'+
      '<h2><i class="fa-solid fa-user-tie"></i> '+(lang=='si'?'ලියාපදිංචි ලයිබ්‍රේරියන්වරුන්ගේ ලැයිස්තුව':'Registered Librarians List')+'</h2>'+
      (libs.length ? libs.map(l=>
        '<div class="card" style="background:var(--bg);margin-top:12px;display:flex;justify-content:space-between;align-items:center;">'+
          '<div>'+
            '<h4 style="margin:0 0 5px 0;">'+esc(l.name)+' <span class="tag">'+esc(l.lib_name)+'</span></h4>'+
            '<p style="margin:0;color:var(--muted);"><i class="fa-solid fa-id-badge"></i> <b>Reg/Emp No:</b> '+esc(l.username)+' | <i class="fa-solid fa-phone"></i> '+esc(l.phone || 'N/A')+' | <i class="fa-solid fa-envelope"></i> '+esc(l.email || l.username)+'</p>'+
          '</div>'+
          '<div>'+
            '<button class="btn warn" style="padding:6px 14px;font-size:12px;" onclick="deleteLibrarian('+l.id+')"><i class="fa-solid fa-trash"></i> '+(lang=='si'?'ඉවත් කරන්න':'Delete')+'</button>'+
          '</div>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'ලයිබ්‍රේරියන්වරුන් නොමැත':'No librarians found')+'</p>')+
    '</div>' : '';

  v.innerHTML = libBanner() + addLibraryHtml + addMemberHtml + addSingleBookHtml +

  '<div class="card" style="border:2px solid var(--success); box-shadow: 0 4px 15px rgba(34,197,94,0.15); margin-bottom:20px;">'+
    '<h2><i class="fa-solid fa-file-excel" style="color:var(--success);"></i> '+(lang=='si'?'Excel මඟින් පොත් ඇතුළත් කිරීම සහ Barcode සෑදීම':'Bulk Book Import (Excel) & Auto Barcode')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'Title, Author, Copies යන තීරු (Columns) ඇතුළත් Excel (.xlsx / .csv) ගොනුවක් තෝරන්න.':'Select an Excel (.xlsx / .csv) file with Title, Author, Copies columns.')+'</p>'+
    '<div class="row" style="display:flex;gap:10px;align-items:center;margin-top:15px;flex-wrap:wrap;">'+
      '<input type="file" id="excelFile" accept=".xlsx,.xls,.csv" style="flex:1;margin:0;background:var(--card);">'+
      '<button class="btn" style="background:var(--success);" onclick="uploadBooksExcel()"><i class="fa-solid fa-upload"></i> '+(lang=='si'?'Upload කර Barcodes සාදන්න':'Upload & Generate Barcodes')+'</button>'+
      '<button class="btn alt" onclick="downloadSampleExcel()"><i class="fa-solid fa-download"></i> '+(lang=='si'?'Sample Excel ගොනුව':'Sample Excel')+'</button>'+
    '</div>'+
    '<div id="generatedBarcodesBox" style="margin-top:20px;"></div>'+
  '</div>' + adminLibrarianHtml;
};

async function addSingleBookAdmin(){
  var title = $('admBookTitle').value.trim();
  var author = $('admBookAuthor').value.trim();
  var code = $('admBookCode').value.trim();
  var date = $('admBookDate').value;
  var recType = $('admBookRecType').value;
  var recDesc = $('admBookRecDesc').value.trim();
  var chosenLibId = $('libIdForAdd') ? $('libIdForAdd').value : (selectedLib > 0 ? selectedLib : 1);

  if(!title){ alert(lang=='si'?'පොතේ නම අත්‍යවශ්‍යයි!':'Book title is required!'); return; }

  var res = await api('add_single_book', {lib_id: chosenLibId}, {
    library_id: chosenLibId,
    code: code,
    title: title,
    author: author,
    received_date: date,
    received_type: recType,
    received_desc: recDesc
  });

  $('admSingleBcBox').innerHTML = '<div style="background:var(--bg);padding:14px;border-radius:8px;border:1px dashed var(--line);text-align:center;">'+
    '<b>'+esc(title)+'</b> ('+esc(res.code)+')<br>'+
    '<svg id="admSingleSvg" style="margin-top:8px;"></svg>'+
  '</div>';
  JsBarcode('#admSingleSvg', res.code, {format: "CODE128", width: 1.8, height: 42});
  alert(lang=='si'?'පොත සාර්ථකව එකතු කරන ලදී!':'Book added successfully!');
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

async function addNewLibrary(){
  var name = $('newLibName').value.trim();
  if(!name){
    alert(lang=='si'?'කරුණාකර පුස්තකාලයේ නම ඇතුළත් කරන්න!':'Please enter a library name!');
    return;
  }
  var res = await api('add_library', {}, {name: name});
  selectedLib = Number(res.id);
  alert(lang=='si'?'නව පුස්තකාලය සාර්ථකව එකතු කරන ලදී: '+res.name:'New library added successfully: '+res.name);
  await render();
}

function downloadSampleExcel(){
  var csv = "Title,Author,Copies\nMadol Doova,Martin Wickramasinghe,2\nGamperaliya,Martin Wickramasinghe,3\n";
  var blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'sample_books_upload.csv';
  a.click();
}

async function uploadBooksExcel(){
  var fileInput = $('excelFile');
  if(!fileInput.files.length){ alert(lang=='si'?'කරුණාකර Excel ගොනුවක් තෝරන්න!':'Please select an Excel file!'); return; }

  var reader = new FileReader();
  reader.onload = async function(e){
    var data = new Uint8Array(e.target.result);
    var workbook = XLSX.read(data, {type: 'array'});
    var sheet = workbook.Sheets[workbook.SheetNames[0]];
    var rows = XLSX.utils.sheet_to_json(sheet);

    var books = rows.map(r => ({
      title: r.Title || r.title || r['Book Name'] || '',
      author: r.Author || r.author || '',
      copies: r.Copies || r.copies || r.Quantity || 1
    })).filter(b => b.title);

    if(!books.length){ alert(lang=='si'?'Excel ගොනුවේ වලංගු පොත් විස්තර හමු නොවීය!':'No valid book rows found!'); return; }

    var chosenLibId = $('libIdForAdd') ? $('libIdForAdd').value : (selectedLib > 0 ? selectedLib : 1);
    var res = await api('import_books', {lib_id: chosenLibId}, {books: books});
    var created = res.created || [];

    $('generatedBarcodesBox').innerHTML =
      '<div style="background:var(--bg);padding:15px;border-radius:10px;border:1px solid var(--line);">'+
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">'+
          '<h3 style="margin:0;color:var(--success);"><i class="fa-solid fa-check-circle"></i> '+(lang=='si'?'පොත් පිටපත් '+created.length+' ක් සඳහා Barcodes සාදන ලදී!':'Generated Barcodes for '+created.length+' copies!')+'</h3>'+
          '<button class="btn" onclick="printBarcodesSheet()"><i class="fa-solid fa-print"></i> '+(lang=='si'?'Barcodes Print / PDF ලෙස ගන්න':'Print / Save PDF')+'</button>'+
        '</div>'+
        '<div id="printableBarcodes" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">'+
          created.map(b =>
            '<div style="background:#fff;color:#000;padding:10px;border:1px dashed #999;border-radius:6px;text-align:center;">'+
              '<div style="font-size:12px;font-weight:bold;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+esc(b.title)+'</div>'+
              '<div style="font-size:10px;color:#555;margin-bottom:5px;">'+esc(b.author)+'</div>'+
              '<svg class="bc-svg" data-code="'+esc(b.code)+'"></svg>'+
            '</div>'
          ).join('')+
        '</div>'+
      '</div>';

    document.querySelectorAll('.bc-svg').forEach(svg => {
      JsBarcode(svg, svg.getAttribute('data-code'), {format: "CODE128", width: 1.6, height: 40, fontSize: 12});
    });
  };
  reader.readAsArrayBuffer(fileInput.files[0]);
}

function printBarcodesSheet(){
  var content = $('printableBarcodes').innerHTML;
  var win = window.open('', '', 'width=800,height=600');
  win.document.write('<html><head><title>Print Book Barcodes</title><style>body{font-family:sans-serif;display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:15px;}</style></head><body>'+content+'</body></html>');
  win.document.close();
  win.print();
}

var generatedOTP = '';

async function sendLibrarianOTP(){
  var email = $('libEmail').value.trim();
  var name = $('libName').value.trim();
  var empNo = $('libEmpNo').value.trim();
  var password = $('libPassword').value.trim();

  if(!name || !empNo || !email || !password){
    alert(lang=='si'?'කරුණාකර සියලුම විස්තර පුරවන්න!':'Please fill all details!');
    return;
  }

  var res = await api('send_otp', {}, {email: email});
  generatedOTP = String(res.otp);

  $('otpSection').innerHTML = '<div style="background:var(--bg);padding:15px;border-radius:8px;border:1px solid var(--line);margin-top:10px;">'+
    '<p style="margin:0 0 5px 0;color:var(--success);font-weight:bold;"><i class="fa-solid fa-circle-check"></i> '+(lang=='si'?'OTP අංකය සාර්ථකව ජනනය කරන ලදී!':'OTP generated successfully!')+'</p>'+
    '<p style="margin:0 0 10px 0;font-size:14px;"><b>Generated OTP:</b> <span style="background:var(--card);padding:2px 8px;border:1px solid var(--line);border-radius:4px;font-family:monospace;font-size:16px;color:var(--ac);">'+generatedOTP+'</span> <small class="muted">(මෙම අංකය ලයිබ්‍රේරියන්වරයාට ලබා දෙන්න)</small></p>'+
    '<p style="margin:0 0 10px 0;font-weight:bold;color:var(--ac);"><i class="fa-solid fa-envelope-circle-check"></i> '+(lang=='si'?'ලයිබ්‍රේරියන් ලබා දුන් OTP අංකය පහතින් ඇතුළත් කරන්න:':'Enter the OTP provided by librarian:')+'</p>'+
    '<div style="display:flex;gap:10px;">'+
      '<input id="enteredOTP" placeholder="උදා: '+generatedOTP+'" style="margin:0;flex:1;">'+
      '<button class="btn" style="background:var(--success);" onclick="verifyAndRegisterLibrarian()"><i class="fa-solid fa-check"></i> '+(lang=='si'?'තහවුරු කර Register කරන්න':'Verify & Register')+'</button>'+
    '</div>'+
  '</div>';
}

async function verifyAndRegisterLibrarian(){
  var userOTP = $('enteredOTP').value.trim();
  if(userOTP !== generatedOTP) {
    alert(lang=='si'?'වැරදි OTP අංකයකි! කරුණාකර නැවත උත්සාහ කරන්න.':'Invalid OTP code! Please try again.');
    return;
  }

  var name = $('libName').value.trim();
  var empNo = $('libEmpNo').value.trim();
  var phone = $('libPhone').value.trim();
  var email = $('libEmail').value.trim();
  var password = $('libPassword').value.trim();
  var chosenLibId = $('libIdForAdd') ? $('libIdForAdd').value : (selectedLib > 0 ? selectedLib : 1);

  await api('verify_and_add_librarian', {lib_id: chosenLibId}, {
    library_id: chosenLibId,
    name: name,
    emp_no: empNo,
    phone: phone,
    email: email,
    password: password
  });

  alert(lang=='si'?'OTP තහවුරු වී ලයිබ්‍රේරියන් සාර්ථකව ලියාපදිංචි විය!':'OTP verified & Librarian successfully registered!');
  render();
}

async function deleteLibrarian(id){
  if(confirm(lang=='si' ? 'මෙම ලයිබ්‍රේරියන්වරයා පද්ධතියෙන් ඉවත් කිරීමට අවශ්‍ය බව ස්ථිරද?' : 'Are you sure you want to delete this librarian?')) {
    await api('delete_librarian', {}, {id: id});
    alert(lang=='si' ? 'සාර්ථකව ඉවත් කරන ලදී!' : 'Successfully deleted!');
    render();
  }
}

V.audit = async function(v){
  if(IS_LIB) {
    v.innerHTML = libBanner() + '<div class="card" style="border:2px solid var(--ac); box-shadow: 0 4px 15px rgba(79,70,229,0.15);">'+
      '<h2><i class="fa-solid fa-clipboard-check" style="color:var(--ac);"></i> '+(lang=='si'?'පුස්තකාල විගණනය (Library Inventory Audit)':'Library Inventory Audit')+'</h2>'+
      '<p class="muted">'+(lang=='si'?'පොතේ Barcode එක ස්කෑන් කරන්න හෝ ටයිප් කර Enter ඔබන්න (ස්වයංක්‍රීයව Tick වේ).':'Scan or type book barcode and press Enter to tick/audit.')+'</p>'+
      '<div class="row" style="display:flex;gap:10px;margin-top:15px;">'+
        '<input class="big" id="auditCode" autofocus placeholder="🔍 Barcode (උදා: B00001)..." onkeypress="if(event.key===\'Enter\') auditBookItem()" style="flex:1;margin:0;">'+
        '<button class="btn" onclick="auditBookItem()">'+(lang=='si'?'පරීක්ෂා කරන්න (Tick)':'Check (Tick)')+'</button>'+
      '</div>'+
      '<div id="auditMsg" style="margin-top:15px;font-weight:bold;"></div>'+
    '</div>';
  } else {
    var curLibObj = LIBRARIES.find(l => Number(l.id) === Number(selectedLib));
    var libNameText = selectedLib > 0
      ? (curLibObj ? curLibObj.name : (lang=='si' ? 'පුස්තකාලය ' + selectedLib : 'Library ' + selectedLib))
      : (lang=='si' ? 'සියලුම පුස්තකාල (All Libraries)' : 'All Libraries');

    v.innerHTML = '<div class="card">'+
      '<h2><i class="fa-solid fa-file-arrow-down" style="color:var(--ac);"></i> '+(lang=='si'?'විගණන වාර්තා බාගත කිරීම (Audit Reports Download)':'Audit Reports Download')+'</h2>'+
      '<p class="muted"><b>'+(lang=='si'?'තෝරාගත් පුස්තකාලය: ':'Selected Library: ')+'</b> <span class="tag" style="background:var(--ac);color:#fff;">'+esc(libNameText)+'</span></p>'+
      '<p class="muted">'+(lang=='si'?'ඉහතින් තෝරා ඇති පුස්තකාලයට අදාළව පරීක්ෂා කරන ලද (Checked) සහ පරීක්ෂා නොකළ (Unchecked) පොත් වාර්තා CSV ලෙස බාගන්න.':'Download checked and unchecked books reports for the selected library as CSV.')+'</p>'+
      '<div class="row" style="gap:15px;margin-top:20px;display:flex;flex-wrap:wrap;">'+
        '<button class="btn" onclick="downloadAudit(\'checked\')"><i class="fa-solid fa-file-excel"></i> '+(lang=='si'?'හමු වූ / Tick කළ පොත් වාර්තාව (Checked)':'Checked Books Report')+'</button>'+
        '<button class="btn warn" onclick="downloadAudit(\'unchecked\')"><i class="fa-solid fa-triangle-exclamation"></i> '+(lang=='si'?'හමු නොවූ / Tick නොකළ පොත් වාර්තාව (Unchecked)':'Unchecked Books Report')+'</button>'+
      '</div>'+
    '</div>';
  }
};

async function auditBookItem() {
  var code = $('auditCode').value.trim();
  if(!code) return;
  try {
    var res = await api('audit_check_book', {}, {code: code});
    var b = res.book;
    $('auditMsg').innerHTML = '<div style="background:var(--bg);padding:15px;border-radius:8px;border-left:4px solid #22c55e;">'+
      '<p style="color:#22c55e;margin:0 0 5px 0;"><i class="fa-solid fa-circle-check"></i> '+(lang=='si'?'පොත සාර්ථකව විගණනය කර (Tick) සටහන් කරගන්නා ලදී!':'Book successfully audited and ticked!')+'</p>'+
      '<p style="margin:0;"><b>'+esc(b.title)+'</b> ('+esc(b.code)+') - <small class="muted">'+esc(b.author)+'</small></p>'+
    '</div>';
    $('auditCode').value = '';
    $('auditCode').focus();
  } catch(e) {
    $('auditMsg').innerHTML = '<span style="color:var(--warn);"><i class="fa-solid fa-circle-xmark"></i> '+(lang=='si'?'දෝෂයක් සිදු විය හෝ පොත හමු නොවීය!':'Error or book not found!')+'</span>';
  }
}

async function downloadAudit(status) {
  var data = await api('download_audit_report', {status: status});
  if(!data.length) { alert(lang=='si'?'මෙම කාණ්ඩය සඳහා දත්ත හමු නොවීය!':'No data found for this category!'); return; }

  var keys = Object.keys(data[0]);
  var csv = keys.join(',') + '\n';
  data.forEach(row => {
    csv += keys.map(k => '"'+(row[k]||'')+'"').join(',') + '\n';
  });

  var blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = 'audit_report_' + status + '_' + new Date().toISOString().substring(0,10) + '.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

V.news = async function(v){
  var newsList = [];
  try { newsList = await api('get_news'); } catch(e) {}
  NEWS_CACHE = Array.isArray(newsList) ? newsList : [];

  var adminAddForm = IS_ADM ?
    '<div class="card" style="border:2px solid var(--ac); box-shadow: 0 4px 15px rgba(79,70,229,0.15);">'+
      '<h2><i class="fa-solid fa-pen-to-square" style="color:var(--ac);"></i> '+(lang=='si'?'නව පුවතක් හෝ නිවේදනයක් එක් කරන්න':'Add New Notice/News')+'</h2>'+
      '<div style="margin-top:10px;"><label><b>'+(lang=='si'?'ප්‍රේක්ෂකයන් (Target Audience):':'Target Audience:')+'</b></label>'+
        '<select id="newsAudience" style="margin-top:5px;">'+
          '<option value="all">'+(lang=='si'?'සියලුම දෙනාට (Members & Librarians - Dashboard Notices)':'All (Members & Librarians)')+'</option>'+
          '<option value="librarian">'+(lang=='si'?'ලයිබ්‍රේරියන්වරුන්ට පමණි (Librarians Only)':'Librarians Only')+'</option>'+
        '</select>'+
      '</div>'+
      '<div style="margin-top:10px;"><label>'+(lang=='si'?'ශීර්ෂය (Title):':'Title:')+'</label><input id="newsTitle" placeholder="උදා: නව පොත් තොගයක් ලැබී ඇත"></div>'+
      '<div style="margin-top:10px;"><label>'+(lang=='si'?'අන්තර්ගතය (Content):':'Content:')+'</label><textarea id="newsContent" rows="4" placeholder="පුවතේ විස්තරය මෙහි ලියන්න..."></textarea></div>'+
      '<div style="margin-top:15px;"><button class="btn" onclick="addNewNews()"><i class="fa-solid fa-bullhorn"></i> '+(lang=='si'?'පුවත ප්‍රකාශයට පත් කරන්න':'Publish News')+'</button></div>'+
    '</div>' : '';

  var editBoxHtml = '<div id="admNewsEditBox" style="display:none;margin-bottom:20px;"></div>';

  v.innerHTML = libBanner() + adminAddForm + editBoxHtml +
    '<div class="card"><h2><i class="fa-solid fa-bullhorn" style="color:var(--ac);"></i> '+(lang=='si'?'පුවත් සහ නිවේදන':'News & Notices')+'</h2>'+
    (NEWS_CACHE.length ? NEWS_CACHE.map(n =>
      '<div class="card" style="background:var(--bg); margin-top:12px; position:relative; border-left:4px solid '+(n.target_audience==='librarian'?'var(--warn)':'var(--ac)')+';">'+
        '<h3 style="margin:0 0 8px 0;">📢 '+esc(n.title)+' '+(n.target_audience==='librarian'?'<span class="tag out" style="font-size:11px;">Librarians Only</span>':'<span class="tag" style="font-size:11px;">Notice</span>')+'</h3>'+
        '<p style="white-space:pre-wrap; margin:0 0 10px 0; color:var(--text);">'+esc(n.content)+'</p>'+
        '<small class="muted"><i class="fa-regular fa-clock"></i> '+fd(n.created_at)+'</small>'+
        (IS_ADM ? '<div style="margin-top:12px;display:flex;gap:8px;"><button class="btn alt" style="padding:4px 10px;font-size:12px;" onclick="openAdmEditNews('+n.id+')"><i class="fa-solid fa-pen"></i> '+(lang=='si'?'සංස්කරණය':'Edit')+'</button><button class="btn warn" style="padding:4px 10px;font-size:12px;" onclick="deleteNews('+n.id+')"><i class="fa-solid fa-trash"></i> '+(lang=='si'?'මකන්න':'Delete')+'</button></div>' : '')+
      '</div>'
    ).join('') : '<p class="muted">'+(lang=='si'?'පුවත් සහ නිවේදන නොමැත':'No news or notices available')+'</p>')+
  '</div>';
};

function openAdmEditNews(id){
  var n = NEWS_CACHE.find(x => Number(x.id) === Number(id));
  if(!n) return;
  var box = $('admNewsEditBox');
  box.style.display = 'block';
  box.innerHTML = '<div class="card" style="border:2px solid var(--ac);">'+
    '<h3><i class="fa-solid fa-pen"></i> '+(lang=='si'?'පුවත සංස්කරණය කරන්න':'Edit News')+'</h3>'+
    '<div style="margin-top:10px;"><label>'+(lang=='si'?'ශීර්ෂය:':'Title:')+'</label><input id="admEditNTitle" value="'+esc(n.title)+'"></div>'+
    '<div style="margin-top:10px;"><label>'+(lang=='si'?'අන්තර්ගතය:':'Content:')+'</label><textarea id="admEditNContent" rows="4">'+esc(n.content)+'</textarea></div>'+
    '<div style="margin-top:12px;display:flex;gap:8px;">'+
      '<button class="btn" onclick="saveAdmEditNews('+n.id+', \''+esc(n.target_audience || 'all')+'\')"><i class="fa-solid fa-check"></i> '+(lang=='si'?'සුරකින්න':'Save')+'</button>'+
      '<button class="btn alt" onclick="$(\'admNewsEditBox\').style.display=\'none\'">'+(lang=='si'?'අවලංගු කරන්න':'Cancel')+'</button>'+
    '</div>'+
  '</div>';
}

async function saveAdmEditNews(id, aud){
  var title = $('admEditNTitle').value.trim();
  var content = $('admEditNContent').value.trim();
  if(!title || !content){ alert(lang=='si'?'ශීර්ෂය සහ අන්තර්ගතය අවශ්‍යයි!':'Title and content required!'); return; }
  await api('edit_news', {}, {id: id, title: title, content: content, target_audience: aud});
  alert(lang=='si'?'පුවත සාර්ථකව යාවත්කාලීන කරන ලදී!':'News updated!');
  render();
}

async function addNewNews(){
  var title = $('newsTitle').value.trim();
  var content = $('newsContent').value.trim();
  var audience = $('newsAudience').value;
  if(!title || !content) { alert(lang=='si'?'කරුණාකර ශීර්ෂය සහ අන්තර්ගතය පුරවන්න!':'Please fill title and content!'); return; }

  await api('add_news', {}, {title: title, content: content, target_audience: audience});
  alert(lang=='si'?'පුවත සාර්ථකව එකතු කරන ලදී!':'News added successfully!');
  render();
}

async function deleteNews(id){
  if(confirm(lang=='si'?'මෙම පුවත මකා දැමීමට අවශ්‍ය බව ස්ථිරද?':'Are you sure you want to delete this news?')) {
    await api('delete_news', {}, {id: id});
    render();
  }
}

V.mine = async function(v){
  var s = await api('stats');
  v.innerHTML = '<div class="card" style="border-left:4px solid var(--ac);">'+
    '<h2><i class="fa-solid fa-id-card"></i> '+(lang=='si'?'මගේ ගිණුම':'My Account')+'</h2>'+
    '<p><b>'+(lang=='si'?'නම:':'Name:')+'</b> '+esc(ME.name)+'</p>'+
    '<p><b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> '+esc(s.my_lib)+'</p>'+
    '<h3 style="margin-top:20px;"><i class="fa-solid fa-book"></i> '+(lang=='si'?'දැනට ණයට ගෙන ඇති පොත්':'Current Loans')+'</h3>'+
    (s.current.length ? s.current.map(l=>'<div style="background:var(--bg);padding:10px;border-radius:8px;margin-top:8px;"><b>'+esc(l.title)+'</b> <span class="tag out" style="float:right;">'+(lang=='si'?'ආපසු දිය යුතු: ':'Due: ')+fd(l.due)+'</span></div>').join('') : '<p class="muted">'+(lang=='si'?'පොත් ගෙන නැත':'No books borrowed')+'</p>')+
  '</div>';
};

render();
</script>
</body>
</html>