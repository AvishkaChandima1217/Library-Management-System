<?php
require __DIR__ . '/db.php';
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
    <label>පරිශීලක නාමය (Username)</label><input name="u" autofocus autocomplete="username">
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

function getTabs(){
  if(IS_ADM) return [
    ['dash', '<i class="fa-solid fa-chart-pie"></i> ' + (lang=='si'?'උපකරණ පුවරුව':'Dashboard')],
    ['search', '<i class="fa-solid fa-magnifying-glass"></i> ' + (lang=='si'?'පොත් සොයන්න':'Search Books')],
    ['loans', '<i class="fa-solid fa-book-bookmark"></i> ' + (lang=='si'?'ණයට දුන් පොත්':'Active Loans')],
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
    ['members', '<i class="fa-solid fa-users"></i> ' + (lang=='si'?'සාමාජිකයන් සහ ලයිබ්‍රේරියන්':'Members & Librarians')],
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

function renderLibSelector(){
  if(!IS_ADM) return;
  $('libSelectorWrap').innerHTML = '<div class="card" style="margin-bottom:15px;padding:12px 20px;display:flex;align-items:center;gap:15px;background:linear-gradient(135deg, var(--ac), #3b82f6);color:#fff;border:none;">'+
    '<b><i class="fa-solid fa-building-columns"></i> '+(lang=='si'?'පුස්තකාලය තෝරන්න:':'Select Library:')+'</b>'+
    '<select id="libSel" onchange="selectedLib=this.value;render()" style="width:250px;padding:8px 12px;border-radius:8px;margin:0;background:var(--card);color:var(--text);border:none;">'+
    '<option value="0">'+(lang=='si'?'සියලුම පුස්තකාල (1-5)':'All Libraries (1-5)')+'</option>'+
    '<option value="1">'+(lang=='si'?'පුස්තකාලය 1':'Library 1')+'</option><option value="2">'+(lang=='si'?'පුස්තකාලය 2':'Library 2')+'</option><option value="3">'+(lang=='si'?'පුස්තකාලය 3':'Library 3')+'</option><option value="4">'+(lang=='si'?'පුස්තකාලය 4':'Library 4')+'</option><option value="5">'+(lang=='si'?'පුස්තකාලය 5':'Library 5')+'</option>'+
    '</select></div>';
  if($('libSel')) $('libSel').value = selectedLib;
}

function nav(){
  var TABS = getTabs();
  $('nav').innerHTML=TABS.map(t=>'<button class="'+(tab===t[0]?'on':'')+'" onclick="go(\''+t[0]+'\')">'+t[1]+'</button>').join('');
}
function go(t){tab=t; render()}
function render(){
  $('langLbl').innerText = lang === 'si' ? 'EN' : 'සිං';
  renderLibSelector(); nav(); var v=$('view'); v.innerHTML=''; V[tab](v);
}

// 1. DASHBOARD
V.dash = async function(v){
  if(IS_MEM) {
    var s = await api('stats');
    var newsList = [];
    try { newsList = await api('get_news'); } catch(e){}

    v.innerHTML = '<div class="card"><h2><i class="fa-solid fa-id-card"></i> '+(lang=='si'?'මගේ ගිණුම':'My Account')+'</h2>'+
      '<p><b>'+(lang=='si'?'නම:':'Name:')+'</b> '+esc(ME.name)+'</p><p><b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> '+esc(s.my_lib)+'</p>'+
      '<h3>'+(lang=='si'?'දැනට ණයට ගෙන ඇති පොත්':'Current Loans')+'</h3>'+
      (s.current.length ? s.current.map(l=>'<p class="tag out">'+esc(l.title)+' ('+(lang=='si'?'ආපසු දිය යුතු: ':'Due: ')+fd(l.due)+')</p>').join('') : '<p class="muted">'+(lang=='si'?'පොත් ගෙන නැත':'No books borrowed')+'</p>')+
    '</div>'+

    '<div class="card" style="margin-top:20px;">'+
      '<h3><i class="fa-solid fa-bell" style="color:var(--ac);"></i> '+(lang=='si'?'ප්‍රධාන නිවේදන (Notices)':'Recent Notices')+'</h3>'+
      (newsList.length ? newsList.map(n=>
        '<div class="card" style="background:var(--bg);margin-top:10px;cursor:pointer;border-left:4px solid var(--ac);" onclick="go(\'news\')">'+
          '<h4>📢 '+esc(n.title)+'</h4>'+
          '<p style="font-size:13px;color:var(--muted);">'+esc(n.content.substring(0, 100))+'...</p>'+
          '<small class="muted">'+fd(n.created_at)+' - <i>'+(lang=='si'?'සම්පූර්ණ විස්තරය බැලීමට ක්ලික් කරන්න':'Click to read full news')+'</i></small>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'නිවේදන නොමැත':'No notices available')+'</p>')+
    '</div>';
    return;
  }

  var s = await api('stats');
  v.innerHTML = '<div class="card" style="background: linear-gradient(135deg, var(--ac), #3b82f6); color: #fff; padding: 25px; border-radius: var(--radius); border:none;">'+
    '<h1><i class="fa-solid fa-gauge-high"></i> '+(lang=='si'?'පරිපාලක උපකරණ පුවරුව':'Admin Dashboard')+'</h1>'+
    '<p style="opacity:0.9;">'+(lang=='si'?'පද්ධතියේ සියලුම පුස්තකාල සහ ක්‍රියාකාරකම් පහසුවෙන් කළමනාකරණය කරන්න.':'Easily manage all library branches and activities.')+'</p>'+
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
      '<button class="btn" onclick="go(\'manage\')"><i class="fa-solid fa-user-plus"></i> '+(lang=='si'?'ලයිබ්‍රේරියන් කෙනෙක් එකතු කරන්න':'Add Librarian')+'</button>'+
      '<button class="btn" onclick="go(\'audit\')"><i class="fa-solid fa-file-arrow-down"></i> '+(lang=='si'?'විගණන වාර්තා බාගන්න':'Download Audit Reports')+'</button>'+
      '<button class="btn" onclick="go(\'news\')"><i class="fa-solid fa-bullhorn"></i> '+(lang=='si'?'නිවේදනයක් පළ කරන්න':'Publish Notice')+'</button>'+
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

// 2. SEARCH VIEW
V.search = function(v){
  var subText = IS_MEM ? '<p class="muted"><i class="fa-solid fa-building-columns"></i> '+(lang=='si'?'ඔබ ලියාපදිංචි වී ඇති පුස්තකාලයේ පොත් ලැයිස්තුව':'Books available in your registered library')+'</p>' : '';
  
  v.innerHTML = subText + 
    '<div class="card" style="margin-bottom:15px; border: 2px solid var(--ac);">'+
      '<h3><i class="fa-solid fa-barcode"></i> '+(lang=='si'?'බාර්කොඩ් ස්කෑන් කරන්න හෝ පොතේ නම/කතෘ ලියන්න':'Scan Barcode or Type Book Name/Author')+'</h3>'+
      '<div class="row" style="display:flex;gap:10px;">'+
        '<input class="big" id="q" autofocus placeholder="'+(lang=='si'?'🔍 Barcode එක ස්කෑන් කරන්න හෝ නම/කතෘ ලියන්න...':'🔍 Scan Barcode or type Name/Author...')+'" value="'+esc(q)+'" oninput="handleSearchInput(this.value)" onkeypress="if(event.key===\'Enter\') handleSearchInput(this.value, true)" style="flex:1;">'+
      '</div>'+
    '</div>'+
    '<div id="barcodeDetailRes" style="margin-bottom:15px;"></div>'+
    '<div id="res"></div>';
  
  LD={a:'search',p:()=>({q:q}),f:(b,i)=>{
    var a=b.copies-b.lent;
    return '<div class="card"><div class="item" onclick="tog('+i+')"><div><b>'+esc(b.title)+'</b> <span class="tag">'+esc(b.lib_name)+'</span><br><small>'+esc(b.author)+'</small></div><span class="tag'+(a?'':' out')+'">'+a+' / '+b.copies+' '+(lang=='si'?'ඇත':'available')+'</span></div>'+
    (b.c?'<div class="cps">'+b.c.map(x=>'<div class="cp"><span><b>'+esc(x.code)+'</b> <div style="display:inline-block;background:#fff;padding:2px;margin-left:10px">'+qr(x.code)+'</div></span>'+(x.due?'<span class="tag out">'+(lang=='si'?'ආපසු ':'Due ') + fd(x.due)+'</span>':'<span class="tag">'+(lang=='si'?'ඇත':'Available')+'</span>')+'</div>').join('')+'</div>':'')+'</div>';
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
        var copiesList = j.all_copies || [];
        
        detailDiv.innerHTML = '<div class="card" style="background:var(--bg); border: 2px solid #28a745;">'+
          '<h3><i class="fa-solid fa-circle-check" style="color:#28a745;"></i> '+(lang=='si'?'ස්කෑන් කළ පොතේ සම්පූර්ණ විස්තර':'Scanned Book Full Details')+'</h3>'+
          '<div class="detail" style="display:flex;gap:15px;align-items:center;margin-top:10px;">'+
            '<div><div class="qr" style="background:#fff;padding:5px;">'+qr(b.code)+'</div></div>'+
            '<div class="grow1">'+
              '<h2>'+esc(b.title)+'</h2>'+
              '<p><b>'+(lang=='si'?'කතෘ:':'Author:')+'</b> '+esc(b.author)+'</p>'+
              '<p><i class="fa-solid fa-building-columns"></i> <b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> '+esc(b.lib_name)+'</p>'+
              '<p><b>'+(lang=='si'?'පොත් කේතය (Barcode):':'Book Code (Barcode):')+'</b> '+esc(b.code)+'</p>'+
              '<p><span class="tag '+(o?'out':'')+'">'+(o ? (lang=='si'?'ණයට දී ඇත (Lent Out)':'Lent Out') : (lang=='si'?'පුස්තකාලයේ ඇත (Available)':'Available in Library'))+'</span></p>'+
            '</div>'+
          '</div>'+
          (o ? '<div class="kv" style="margin-top:10px;font-size:14px;background:var(--card);padding:10px;border-radius:6px;"><span>'+(lang=='si'?'ගත් අය':'Borrower')+'</span><b>'+esc(o.name)+'</b><span>'+(lang=='si'?'දුරකථන':'Phone')+'</span><span>'+esc(o.phone)+'</span><span>'+(lang=='si'?'ආපසු දිය යුතු දිනය':'Due Date')+'</span><span>'+fd(o.due)+'</span></div>' : '')+
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
function paint(){$('res').innerHTML='<p class="muted">'+total+' '+(lang=='si'?'ප්‍රතිඵල':'results')+'</p>'+rows.map(LD.f).join('')+(rows.length<total?'<p><button class="btn alt" onclick="load()">'+(lang=='si'?'තව පෙන්වන්න':'Load More')+'</button></p>':'')}
async function tog(i){var b=rows[i];b.c=b.c?null:await api('copies',{title:b.title,author:b.author});paint()}

// 3. SCAN / ISSUE VIEW
V.scan = function(v){
  v.innerHTML='<div class="card"><h2><i class="fa-solid fa-qrcode"></i> '+(lang=='si'?'පොත් කේතය / නම / කතෘ ස්කෑන් හෝ ටයිප් කරන්න':'Scan or Type Book Code / Name / Author')+'</h2>'+
  '<div class="row"><input class="big" id="sc" placeholder="'+(lang=='si'?'උදා: B00001 හෝ පොතේ නම':'e.g. B00001 or Book Name')+'" onkeypress="if(event.key===\'Enter\') look()"><button class="btn" onclick="look()"><i class="fa-solid fa-magnifying-glass"></i> '+(lang=='si'?'සොයන්න':'Search')+'</button></div></div><div id="det"></div>';
};

async function look(){
  var c=g('sc').trim(); if(!c) return;
  var d=$('det');
  try {
    var j = await api('book',{code:c}), b = j.book;
    if(!b){d.innerHTML='<div class="card"><p style="color:var(--warn)">'+(lang=='si'?'පොත හමු නොවීය!':'Book not found!')+'</p></div>';return}
    var o = j.loan, copiesList = j.all_copies || [];
    var h = '<div class="card"><div class="detail"><div><div class="qr">'+qr(b.code)+'</div></div><div class="grow1"><h2>'+esc(b.title)+'</h2>'+
            '<p><b>'+(lang=='si'?'කතෘ:':'Author:')+'</b> '+esc(b.author)+'</p>'+
            '<p><i class="fa-solid fa-building-columns"></i> <b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> '+esc(b.lib_name)+'</p>'+
            '<p><b>'+(lang=='si'?'පොත් කේතය (Code):':'Book Code:')+'</b> '+esc(b.code)+'</p>'+
            '<p><span class="tag'+(o?' out':'')+'">'+(o?(lang=='si'?'ණයට දී ඇත':'Lent Out'):(lang=='si'?'පුස්තකාලයේ ඇත':'Available in Library'))+'</span></p></div></div>';
    
    if(copiesList.length > 1) {
      h += '<h3>'+(lang=='si'?'මෙම පොතේ අනෙකුත් පිටපත් සහ තත්ත්වය:':'Other copies of this book:')+'</h3>'+
           '<div class="cps">' + copiesList.map(cp => '<div class="cp"><span><b>'+esc(cp.code)+'</b></span> ' + (cp.due ? '<span class="tag out">'+(lang=='si'?'ආපසු දිය යුතු: ':'Due: ')+fd(cp.due)+'</span>' : '<span class="tag">'+(lang=='si'?'ඇත':'Available')+'</span>') + '</div>').join('') + '</div>';
    }

    if(IS_LIB){
      if(o) h+='<div class="kv" style="margin-top:15px;"><span>'+(lang=='si'?'ගත් අය':'Borrower')+'</span><b>'+esc(o.name)+' ('+esc(o.username)+')</b><span>'+(lang=='si'?'දුරකථන':'Phone')+'</span><span>'+esc(o.phone)+'</span><span>'+(lang=='si'?'ආපසු දිය යුතු දිනය':'Due Date')+'</span><span>'+fd(o.due)+'</span></div><button class="btn warn" onclick="returnBook(\''+b.code+'\')" style="margin-top:10px;"><i class="fa-solid fa-rotate-left"></i> '+(lang=='si'?'පොත ආපසු භාරගන්න':'Return Book')+'</button>';
      else h+='<div class="cols" style="margin-top:15px;"><div><label>'+(lang=='si'?'සාමාජිකයාගේ Username හෝ Member ID':'Member Username or ID')+'</label><input id="mu" placeholder="M1 හෝ username"></div></div><br><button class="btn" onclick="lendBook(\''+b.code+'\')" style="margin-top:10px;"><i class="fa-solid fa-hand-holding"></i> '+(lang=='si'?'පොත ණයට දෙන්න (උපරිම 2ක්)':'Lend Book (Max 2)')+'</button>';
    }
    d.innerHTML=h+'</div>';
  } catch(e){ d.innerHTML='<div class="card"><p style="color:var(--warn)">දෝෂයක් සිදු විය.</p></div>'; }
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

// 4. LOANS VIEW
V.loans = async function(v){
  var r = await api('loans');
  var today = new Date();
  var normalLoans = [];
  var fineLoans = [];

  r.forEach(l => {
    var dueDate = new Date(l.due);
    var diffTime = today - dueDate;
    var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays > 0) {
      l.overdue_days = diffDays;
      l.fine_amount = diffDays * 5.00;
      fineLoans.push(l);
    } else {
      normalLoans.push(l);
    }
  });

  v.innerHTML = '<div class="card">'+
    '<h2><i class="fa-solid fa-book-bookmark"></i> '+(lang=='si'?'ණයට දුන් පොත් කළමනාකරණය':'Lent Books Management')+'</h2>'+
    
    '<div style="display:flex;gap:10px;margin:15px 0;border-bottom:2px solid var(--line);padding-bottom:10px;">'+
      '<button class="btn" id="btnTab1" onclick="switchLoanTab(1)"><i class="fa-solid fa-users"></i> '+(lang=='si'?'සාමාජිකයන් ළඟ ඇති පොත් (':'Members Books (') + r.length + ')</button>'+
      '<button class="btn warn" id="btnTab2" onclick="switchLoanTab(2)"><i class="fa-solid fa-triangle-exclamation"></i> '+(lang=='si'?'දඩ ගෙවීම් (Fines) (':'Fine Payments (') + fineLoans.length + ')</button>'+
    '</div>'+

    '<div id="loanTab1Content">'+
      '<h3>'+(lang=='si'?'සාමාජිකයන් විසින් ගෙන ගිය පොත් ලැයිස්තුව':'List of books taken by members')+'</h3>'+
      (r.length ? r.map(l=>
        '<div class="card" style="background:var(--bg);margin-top:10px;">'+
          '<p><b>'+esc(l.title)+'</b> (Code: '+esc(l.code)+')</p>'+
          '<p><i class="fa-solid fa-user"></i> <b>'+(lang=='si'?'සාමාජිකයා:':'Member:')+'</b> '+esc(l.name)+' ('+esc(l.phone)+')</p>'+
          '<small class="muted">'+(lang=='si'?'ආපසු දිය යුතු දිනය:':'Due Date:')+' '+fd(l.due)+'</small>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'පොත් ගෙන ගොස් නැත':'No books lent')+'</p>')+
    '</div>'+

    '<div id="loanTab2Content" style="display:none;">'+
      '<h3>'+(lang=='si'?'දින 14 ඉක්මවූ ප්‍රමාද වූ පොත් සහ දඩ මුදල් (රු. 5.00 / දවසකට)':'Overdue books & Fines (Rs. 5.00 per day)')+'</h3>'+
      (fineLoans.length ? fineLoans.map(l=>
        '<div class="card" style="background:var(--bg);border-left:4px solid var(--warn);margin-top:10px;">'+
          '<p><b>'+esc(l.title)+'</b> (Code: '+esc(l.code)+')</p>'+
          '<p><i class="fa-solid fa-user"></i> <b>'+(lang=='si'?'සාමාජිකයා:':'Member:')+'</b> '+esc(l.name)+' | <b>Phone:</b> '+esc(l.phone)+'</p>'+
          '<p><span class="tag out">'+(lang=='si'?'ප්‍රමාද වූ දින: ':'Overdue Days: ')+l.overdue_days+'</span> | <b>'+(lang=='si'?'ගෙවිය යුතු දඩ මුදලය:':'Total Fine:')+'</b> Rs. '+l.fine_amount.toFixed(2)+'</p>'+
          '<small class="muted">'+(lang=='si'?'ආපසු දිය යුතු දිනය තිබුණේ:':'Original Due Date:')+' '+fd(l.due)+'</small>'+
          '<div style="margin-top:10px;">'+
            '<a href="mailto:'+esc(l.email)+'?subject=Library Overdue Notice&body=Dear '+encodeURIComponent(l.name)+', You have a fine of Rs. '+l.fine_amount.toFixed(2)+' for the overdue book: '+encodeURIComponent(l.title)+'" class="btn" style="font-size:12px;padding:6px 12px;text-decoration:none;"><i class="fa-solid fa-envelope"></i> '+(lang=='si'?'ඊමේල් එකක් යවන්න':'Send Email Notice')+'</a>'+
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

// 5. MEMBERS & LIBRARIANS VIEW
V.members = async function(v){
  var mems = await api('members');
  var libs = await api('librarians');
  
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

  v.innerHTML = '<div class="card">'+
    '<h2><i class="fa-solid fa-user-tie"></i> '+(lang=='si'?'පුස්තකාල ලයිබ්‍රේරියන්වරුන්':'Library Librarians')+'</h2>'+
    (libs.length ? libs.map(l=>
      '<div class="card" style="background:var(--bg);margin-top:10px;border-left:4px solid var(--ac);">'+
        '<p><b>'+esc(l.name)+'</b> (Username/Emp No: '+esc(l.username)+') <span class="tag">'+esc(l.lib_name)+'</span></p>'+
        '<p><i class="fa-solid fa-phone"></i> <b>Phone:</b> '+esc(l.phone || 'N/A')+' | <b>Email:</b> '+esc(l.username || 'N/A')+'</p>'+
      '</div>'
    ).join('') : '<p class="muted">'+(lang=='si'?'ලයිබ්‍රේරියන්වරුන් නොමැත':'No librarians found')+'</p>')+
  '</div>'+

  '<div class="card" style="margin-top:20px;">'+
    '<h2><i class="fa-solid fa-users"></i> '+(lang=='si'?'සාමාජිකයන් කළමනාකරණය (වයස් කාණ්ඩ අනුව)':'Members Management (By Age)')+'</h2>'+
    
    '<div style="display:flex;gap:10px;margin:15px 0;border-bottom:2px solid var(--line);padding-bottom:10px;">'+
      '<button class="btn" id="btnAge1" onclick="switchAgeTab(1)"><i class="fa-solid fa-child"></i> '+(lang=='si'?'වයස 18ට අඩු (':'Under 18 (') + under18.length + ')</button>'+
      '<button class="btn" id="btnAge2" onclick="switchAgeTab(2)"><i class="fa-solid fa-user"></i> '+(lang=='si'?'වයස 18ට වැඩි (':'18 and Above (') + above18.length + ')</button>'+
    '</div>'+

    '<div id="ageTab1Content">'+
      '<h3>'+(lang=='si'?'වයස අවුරුදු 18ට අඩු සාමාජිකයන්':'Members under 18 years old')+'</h3>'+
      (under18.length ? under18.map(u=>
        '<div class="card" style="background:var(--bg);margin-top:10px;">'+
          '<p><b>'+esc(u.name)+'</b> (ID: M'+u.id+') - <b>Age:</b> '+u.calc_age+' yrs <span class="tag">DOB: '+esc(u.dob)+'</span></p>'+
          '<p><i class="fa-solid fa-phone"></i> '+esc(u.phone)+' | <i class="fa-solid fa-building-columns"></i> '+esc(u.lib_name)+'</p>'+
          '<div style="display:inline-block;background:#fff;padding:2px;margin-top:5px;">'+qr('M'+u.id)+'</div>'+
        '</div>'
      ).join('') : '<p class="muted">'+(lang=='si'?'සාමාජිකයන් නොමැත':'No members found')+'</p>')+
    '</div>'+

    '<div id="ageTab2Content" style="display:none;">'+
      '<h3>'+(lang=='si'?'වයස අවුරුදු 18ට වැඩි සාමාජිකයන්':'Members 18 years and above')+'</h3>'+
      (above18.length ? above18.map(u=>
        '<div class="card" style="background:var(--bg);margin-top:10px;">'+
          '<p><b>'+esc(u.name)+'</b> (ID: M'+u.id+') '+ (u.calc_age !== undefined ? '- <b>Age:</b> '+u.calc_age+' yrs' : '') +' <span class="tag">'+esc(u.lib_name)+'</span></p>'+
          '<p><i class="fa-solid fa-phone"></i> '+esc(u.phone)+' | <b>NIC:</b> '+esc(u.nic)+'</p>'+
          '<div style="display:inline-block;background:#fff;padding:2px;margin-top:5px;">'+qr('M'+u.id)+'</div>'+
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

// 6. MANAGEMENT VIEW
V.manage = async function(v){
  var libs = await api('librarians');
  
  var libSelectHtml = '';
  if (IS_ADM) {
    libSelectHtml = '<div style="margin-bottom:10px;">'+
      '<label><b>'+(lang=='si'?'අදාළ පුස්තකාලය තෝරන්න (Select Library):':'Select Library for Librarian:')+'</b></label>'+
      '<select id="libIdForAdd" style="width:100%;padding:8px;border-radius:6px;margin-top:5px;">'+
      '<option value="1" '+(selectedLib==1?'selected':'')+'>'+(lang=='si'?'පුස්තකාලය 1':'Library 1')+'</option>'+
      '<option value="2" '+(selectedLib==2?'selected':'')+'>'+(lang=='si'?'පුස්තකාලය 2':'Library 2')+'</option>'+
      '<option value="3" '+(selectedLib==3?'selected':'')+'>'+(lang=='si'?'පුස්තකාලය 3':'Library 3')+'</option>'+
      '<option value="4" '+(selectedLib==4?'selected':'')+'>'+(lang=='si'?'පුස්තකාලය 4':'Library 4')+'</option>'+
      '<option value="5" '+(selectedLib==5?'selected':'')+'>'+(lang=='si'?'පුස්තකාලය 5':'Library 5')+'</option>'+
      '</select>'+
    '</div>';
  }

  v.innerHTML = '<div class="card" style="margin-bottom:20px; border:2px solid var(--ac);">'+
    '<h2><i class="fa-solid fa-user-plus"></i> '+(lang=='si'?'නව ලයිබ්‍රේරියන්වරයකු එකතු කිරීම':'Add New Librarian')+'</h2>'+
    '<p class="muted">'+(lang=='si'?'නම, සේවක අංකය, දුරකථන අංකය, ඊමේල් ලිපිනය සහ අදාළ පුස්තකාලය තෝරා ලියාපදිංචි කරන්න.':'Enter details and select library to register librarian.')+'</p>'+
    
    libSelectHtml +

    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'සම්පූර්ණ නම (Full Name):':'Full Name:')+'</label><input id="libName" placeholder="ජයන්ත පෙරේරා"></div>'+
      '<div><label>'+(lang=='si'?'සේවක අංකය / ලියාපදිංචි අංකය (Employee / Reg No):':'Employee / Reg Number:')+'</label><input id="libEmpNo" placeholder="LIB101"></div>'+
    '</div>'+
    '<div class="cols" style="margin-top:10px;">'+
      '<div><label>'+(lang=='si'?'දුරකථන අංකය (Phone Number):':'Phone Number:')+'</label><input id="libPhone" placeholder="0712345678"></div>'+
      '<div><label>'+(lang=='si'?'ඊමේල් ලිපිනය (Email Address):':'Email Address:')+'</label><input type="email" id="libEmail" placeholder="librarian@library.com"></div>'+
    '</div>'+
    '<div style="margin-top:15px;">'+
      '<button class="btn" onclick="addNewLibrarian()"><i class="fa-solid fa-user-check"></i> '+(lang=='si'?'ලයිබ්‍රේරියන් එකතු කර OTP ඊමේල් කරන්න':'Add Librarian & Send OTP Email')+'</button>'+
    '</div>'+
  '</div>'+

  '<div class="card">'+
    '<h2><i class="fa-solid fa-user-tie"></i> '+(lang=='si'?'ලියාපදිංචි ලයිබ්‍රේරියන්වරුන්ගේ ලැයිස්තුව (Librarians List)':'Registered Librarians List')+'</h2>'+
    (libs.length ? libs.map(l=>
      '<div class="card" style="background:var(--bg);margin-top:10px;display:flex;justify-content:space-between;align-items:center;">'+
        '<div>'+
          '<p><b>'+esc(l.name)+'</b> (Reg/Emp No: <b>'+esc(l.username)+'</b>) <span class="tag">'+esc(l.lib_name)+'</span></p>'+
          '<p><i class="fa-solid fa-phone"></i> '+esc(l.phone || 'N/A')+' | <i class="fa-solid fa-envelope"></i> '+esc(l.username)+'</p>'+
        '</div>'+
        '<div>'+
          '<button class="btn warn" style="padding:6px 12px;font-size:12px;" onclick="deleteLibrarian('+l.id+')"><i class="fa-solid fa-trash"></i> '+(lang=='si'?'ඉවත් කරන්න':'Delete')+'</button>'+
        '</div>'+
      '</div>'
    ).join('') : '<p class="muted">'+(lang=='si'?'ලයිබ්‍රේරියන්වරුන් නොමැත':'No librarians found')+'</p>')+
  '</div>';
};

async function addNewLibrarian(){
  var name = $('libName').value.trim();
  var empNo = $('libEmpNo').value.trim();
  var phone = $('libPhone').value.trim();
  var email = $('libEmail').value.trim();
  var chosenLibId = $('libIdForAdd') ? $('libIdForAdd').value : (selectedLib > 0 ? selectedLib : 1);

  if(!name || !empNo || !email){
    alert(lang=='si'?'කරුණාකර නම, සේවක අංකය සහ ඊමේල් ලිපිනය ඇතුළත් කරන්න!':'Please enter Name, Employee Number and Email!');
    return;
  }

  var otp = Math.floor(100000 + Math.random() * 900000);

  try {
    await api('add_librarian', {lib_id: chosenLibId}, {
      name: name,
      emp_no: empNo,
      phone: phone,
      email: email,
      password: 'lib' + otp
    });

    alert(lang=='si' ? 'ලයිබ්‍රේරියන් සාර්ථකව එකතු කරන ලදී! OTP ඊමේල් පණිවිඩය යැවීම සඳහා විද්‍යුත් තැපෑල විවෘත වේ.' : 'Librarian added successfully! Opening email client to send OTP.');

    var subject = encodeURIComponent('Library System - Librarian Registration & OTP Verification');
    var body = encodeURIComponent('Dear ' + name + ',\n\nYou have been successfully registered as a Librarian in Library ' + chosenLibId + '.\nYour Employee/Reg Number: ' + empNo + '\nYour Temporary Password: lib' + otp + '\n\nYour OTP Verification Code is: ' + otp + '\n\nPlease login and verify your details.\n\nThank you.');
    
    window.location.href = 'mailto:' + email + '?subject=' + subject + '&body=' + body;

    render();
  } catch(e) {}
}

async function deleteLibrarian(id){
  if(confirm(lang=='si' ? 'මෙම ලයිබ්‍රේරියන්වරයා පද්ධතියෙන් ඉවත් කිරීමට අවශ්‍ය බව ස්ථිරද?' : 'Are you sure you want to delete this librarian?')) {
    await api('delete_librarian', {}, {id: id});
    alert(lang=='si' ? 'සාර්ථකව ඉවත් කරන ලදී!' : 'Successfully deleted!');
    render();
  }
}

// 7. AUDIT VIEW
V.audit = async function(v){
  if(IS_LIB) {
    v.innerHTML = '<div class="card" style="border:2px solid var(--ac);">'+
      '<h2><i class="fa-solid fa-clipboard-check"></i> '+(lang=='si'?'පුස්තකාල විගණනය (Library Inventory Audit)':'Library Inventory Audit')+'</h2>'+
      '<p class="muted">'+(lang=='si'?'පොතේ Barcode එක ස්කෑන් කරන්න හෝ ටයිප් කර Enter ඔබන්න (ස්වයංක්‍රීයව Tick වේ).':'Scan or type book barcode and press Enter to tick/audit.')+'</p>'+
      '<div class="row" style="display:flex;gap:10px;margin-top:15px;">'+
        '<input class="big" id="auditCode" autofocus placeholder="🔍 Barcode (උදා: B00001)..." onkeypress="if(event.key===\'Enter\') auditBookItem()">'+
        '<button class="btn" onclick="auditBookItem()">'+(lang=='si'?'පරීක්ෂා කරන්න (Tick)':'Check (Tick)')+'</button>'+
      '</div>'+
      '<div id="auditMsg" style="margin-top:15px;font-weight:bold;"></div>'+
    '</div>';
  } else {
    var libNameText = selectedLib > 0 ? (lang=='si' ? 'පුස්තකාලය ' + selectedLib : 'Library ' + selectedLib) : (lang=='si' ? 'සියලුම පුස්තකාල (All Libraries)' : 'All Libraries');
    
    v.innerHTML = '<div class="card">'+
      '<h2><i class="fa-solid fa-file-arrow-down"></i> '+(lang=='si'?'විගණන වාර්තා බාගත කිරීම (Audit Reports Download)':'Audit Reports Download')+'</h2>'+
      '<p class="muted"><b>'+(lang=='si'?'තෝරාගත් පුස්තකාලය: ':'Selected Library: ')+'</b> <span class="tag" style="background:var(--ac);color:#fff;">'+libNameText+'</span></p>'+
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
    $('auditMsg').innerHTML = '<div style="background:var(--bg);padding:15px;border-radius:8px;border-left:4px solid #28a745;">'+
      '<p style="color:#28a745;margin-bottom:5px;"><i class="fa-solid fa-circle-check"></i> '+(lang=='si'?'පොත සාර්ථකව විගණනය කර (Tick) සටහන් කරගන්නා ලදී!':'Book successfully audited and ticked!')+'</p>'+
      '<p><b>'+esc(b.title)+'</b> ('+esc(b.code)+') - <small>'+esc(b.author)+'</small></p>'+
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

// 8. NEWS VIEW
V.news = async function(v){
  var newsList = [];
  try { newsList = await api('get_news'); } catch(e) {}

  var adminAddForm = IS_ADM ? 
    '<div class="card" style="margin-bottom:20px;border:2px solid var(--ac);">'+
      '<h2><i class="fa-solid fa-pen-to-square"></i> '+(lang=='si'?'නව පුවතක් හෝ නිවේදනයක් එක් කරන්න':'Add New Notice/News')+'</h2>'+
      '<div style="margin-top:10px;"><label><b>'+(lang=='si'?'ප්‍රේක්ෂකයන් (Target Audience):':'Target Audience:')+'</b></label>'+
        '<select id="newsAudience" style="width:100%;padding:8px;border-radius:6px;margin-top:5px;">'+
          '<option value="all">'+(lang=='si'?'සියලුම දෙනාට (Members & Librarians - Dashboard Notices)':'All (Members & Librarians)')+'</option>'+
          '<option value="librarian">'+(lang=='si'?'ලයිබ්‍රේරියන්වරුන්ට පමණි (Librarians Only)':'Librarians Only')+'</option>'+
        '</select>'+
      '</div>'+
      '<div style="margin-top:10px;"><label>'+(lang=='si'?'ශීර්ෂය (Title):':'Title:')+'</label><input id="newsTitle" placeholder="උදා: නව පොත් තොගයක් ලැබී ඇත"></div>'+
      '<div style="margin-top:10px;"><label>'+(lang=='si'?'අන්තර්ගතය (Content):':'Content:')+'</label><textarea id="newsContent" rows="4" style="width:100%;padding:8px;border-radius:6px;" placeholder="පුවතේ විස්තරය මෙහි ලියන්න..."></textarea></div>'+
      '<div style="margin-top:15px;"><button class="btn" onclick="addNewNews()"><i class="fa-solid fa-bullhorn"></i> '+(lang=='si'?'පුවත ප්‍රකාශයට පත් කරන්න':'Publish News')+'</button></div>'+
    '</div>' : '';

  v.innerHTML = adminAddForm + 
    '<div class="card"><h2><i class="fa-solid fa-bullhorn"></i> '+(lang=='si'?'පුවත් සහ නිවේදන':'News & Notices')+'</h2>'+
    (newsList.length ? newsList.map(n => 
      '<div class="card" style="background:var(--bg); margin-top:10px; position:relative; border-left:4px solid '+(n.target_audience==='librarian'?'var(--warn)':'var(--ac)')+';">'+
        '<h3>📢 '+esc(n.title)+' '+(n.target_audience==='librarian'?'<span class="tag out" style="font-size:11px;">Librarians Only</span>':'<span class="tag" style="font-size:11px;">Notice</span>')+'</h3>'+
        '<p style="white-space:pre-wrap; margin-top:8px;">'+esc(n.content)+'</p>'+
        '<small class="muted">'+fd(n.created_at)+'</small>'+
        (IS_ADM ? '<div style="margin-top:10px;"><button class="btn warn" style="padding:4px 10px;font-size:12px;" onclick="deleteNews('+n.id+')"><i class="fa-solid fa-trash"></i> '+(lang=='si'?'මකන්න':'Delete')+'</button></div>' : '')+
      '</div>'
    ).join('') : '<p class="muted">'+(lang=='si'?'පුවත් සහ නිවේදන නොමැත':'No news or notices available')+'</p>')+
  '</div>';
};

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
  v.innerHTML = '<div class="card"><h2><i class="fa-solid fa-id-card"></i> '+(lang=='si'?'මගේ ගිණුම (Mobile View)':'My Account (Mobile View)')+'</h2>'+
    '<p><b>'+(lang=='si'?'නම:':'Name:')+'</b> '+esc(ME.name)+'</p><p><b>'+(lang=='si'?'පුස්තකාලය:':'Library:')+'</b> '+esc(s.my_lib)+'</p>'+
    '<h3>'+(lang=='si'?'දැනට ණයට ගෙන ඇති පොත්':'Current Loans')+'</h3>'+
    (s.current.length ? s.current.map(l=>'<p class="tag out">'+esc(l.title)+' ('+(lang=='si'?'ආපසු දිය යුතු: ':'Due: ')+fd(l.due)+')</p>').join('') : '<p class="muted">'+(lang=='si'?'පොත් ගෙන නැත':'No books borrowed')+'</p>')+'</div>';
};

render();
</script>
</body>
</html>