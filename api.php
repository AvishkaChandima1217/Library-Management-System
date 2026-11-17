<?php
require __DIR__ . '/db.php';
ini_set('display_errors', '0');
session_start(); $uid = $_SESSION['u'] ?? 0; session_write_close();
$me = $uid ? one('SELECT u.*, l.name as lib_name FROM users u LEFT JOIN libraries l ON l.id=u.library_id WHERE u.id=?', [$uid]) : null;
if (!$me) { http_response_code(401); exit('{"error":"auth"}'); }

$ROLE = $me['role'];
$IS_ADMIN = $ROLE === 'admin';
$IS_LIB = $ROLE === 'librarian';
$IS_MEM = $ROLE === 'member';

function out($d) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function fail($m) { out(['error' => $m]); }

$a = $_GET['a'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$o = max(0, (int)($_GET['off'] ?? 0));
$lib_id = $IS_ADMIN ? (int)($_GET['lib_id'] ?? 0) : (int)$me['library_id'];

try {
  switch ($a) {
    case 'libraries':
      out(all_('SELECT * FROM libraries'));

    case 'search':
      $q = trim($_GET['q'] ?? '');
      $targetLib = $IS_MEM ? $me['library_id'] : ($IS_ADMIN ? $lib_id : $me['library_id']);
      $w = "1=1" . ($targetLib > 0 ? " AND b.library_id = $targetLib" : "");
      $p = [];
      if ($q !== '') {
        $w .= " AND (b.title LIKE :q ESCAPE '\\' OR b.author LIKE :q ESCAPE '\\' OR b.code LIKE :q ESCAPE '\\')";
        $p['q'] = '%' . addcslashes($q, '%_\\') . '%';
      }
      $g = "FROM books b LEFT JOIN libraries lib ON lib.id=b.library_id WHERE $w GROUP BY b.title, b.author, b.library_id";
      out([
        'total' => (int)st("SELECT COUNT(*) FROM (SELECT 1 $g)", $p)->fetchColumn(),
        'rows' => all_("SELECT b.title, b.author, b.library_id, lib.name as lib_name, COUNT(*) copies, SUM(EXISTS(SELECT 1 FROM loans l WHERE l.code=b.code AND l.returned IS NULL)) lent $g ORDER BY b.title LIMIT 50 OFFSET $o", $p)
      ]);

    case 'copies':
      $targetLib = $IS_MEM ? $me['library_id'] : ($IS_ADMIN ? $lib_id : $me['library_id']);
      $r = all_('SELECT b.code, b.library_id, lib.name as lib_name, l.due, u.name, u.username FROM books b LEFT JOIN libraries lib ON lib.id=b.library_id LEFT JOIN loans l ON l.code=b.code AND l.returned IS NULL LEFT JOIN users u ON u.id=l.user_id WHERE b.title=? AND b.author=? ' . ($targetLib > 0 ? "AND b.library_id=$targetLib" : "") . ' ORDER BY b.code', [$_GET['title'] ?? '', $_GET['author'] ?? '']);
      if ($IS_MEM) foreach ($r as &$x) { unset($x['name'], $x['username']); }
      out($r);

    case 'book':
      $code = trim($_GET['code'] ?? '');
      $b = one('SELECT b.*, l.name as lib_name FROM books b LEFT JOIN libraries l ON l.id=b.library_id WHERE b.code=? OR b.title LIKE ? OR b.author LIKE ?', [$code, "%$code%", "%$code%"]);
      if (!$b) { out(['book' => null]); }
      $loan = one('SELECT l.*, u.name, u.username, u.phone FROM loans l LEFT JOIN users u ON u.id=l.user_id WHERE l.code=? AND l.returned IS NULL', [$b['code']]);
      $copies = all_('SELECT code, due FROM books b LEFT JOIN loans l ON l.code=b.code AND l.returned IS NULL WHERE b.title=? AND b.author=?', [$b['title'], $b['author']]);
      out(['book' => $b, 'loan' => $loan, 'all_copies' => $copies]);

    case 'audit_check_book':
      if (!$IS_LIB) fail('අවසර නැත');
      $code = trim($in['code'] ?? '');
      $libId = $me['library_id'];
      
      $book = one('SELECT b.*, l.name as lib_name FROM books b LEFT JOIN libraries l ON l.id=b.library_id WHERE b.code=? AND b.library_id=?', [$code, $libId]);
      if(!$book) fail('මෙම පොත ඔබේ පුස්තකාලයට අයත් නැත හෝ හමු නොවීය!');

      if(!one('SELECT 1 FROM audit_logs WHERE library_id=? AND book_code=?', [$libId, $code])) {
        st('INSERT INTO audit_logs(library_id, book_code, checked_by, checked_at) VALUES(?, ?, ?, ?)', [$libId, $code, $me['id'], date('c')]);
      }
      out(['ok' => 1, 'book' => $book]);

    case 'download_audit_report':
      if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
      $targetLib = $IS_LIB ? $me['library_id'] : $lib_id;
      $status = $_GET['status'] ?? 'checked';
      $libFilter = $targetLib > 0 ? "b.library_id = $targetLib" : "1=1";

      if($status === 'checked') {
        $sql = "SELECT b.code, b.title, b.author, l.name as lib_name, a.checked_at, u.name as checked_by_name 
                FROM audit_logs a 
                JOIN books b ON b.code = a.book_code 
                LEFT JOIN libraries l ON l.id = b.library_id 
                LEFT JOIN users u ON u.id = a.checked_by 
                WHERE $libFilter";
      } else {
        $sql = "SELECT b.code, b.title, b.author, l.name as lib_name 
                FROM books b 
                LEFT JOIN libraries l ON l.id = b.library_id 
                WHERE $libFilter AND b.code NOT IN (SELECT book_code FROM audit_logs WHERE library_id = b.library_id)";
      }
      out(all_($sql));

    case 'get_news':
      if ($IS_LIB) {
        out(all_("SELECT * FROM news WHERE target_audience = 'librarian' OR target_audience = 'all' ORDER BY id DESC"));
      } elseif ($IS_MEM) {
        out(all_("SELECT * FROM news WHERE target_audience = 'all' ORDER BY id DESC"));
      } else {
        out(all_('SELECT * FROM news ORDER BY id DESC'));
      }

    case 'add_news':
      if (!$IS_ADMIN) fail('අවසර නැත');
      $title = trim($in['title'] ?? '');
      $content = trim($in['content'] ?? '');
      $audience = trim($in['target_audience'] ?? 'all');
      if(!$title || !$content) fail('ශීර්ෂය සහ අන්තර්ගතය අත්‍යවශ්‍යයි!');
      st('INSERT INTO news(title, content, target_audience, created_at) VALUES(?, ?, ?, ?)', [$title, $content, $audience, date('c')]);
      out(['ok' => 1]);

    case 'delete_news':
      if (!$IS_ADMIN) fail('අවසර නැත');
      $id = (int)($in['id'] ?? 0);
      st('DELETE FROM news WHERE id=?', [$id]);
      out(['ok' => 1]);

    case 'stats':
      if ($IS_MEM) {
        $current = all_('SELECT l.*, b.title, lib.name as lib_name FROM loans l LEFT JOIN books b ON b.code=l.code LEFT JOIN libraries lib ON lib.id=b.library_id WHERE l.user_id=? AND l.returned IS NULL', [$uid]);
        $past = all_('SELECT l.*, b.title, lib.name as lib_name FROM loans l LEFT JOIN books b ON b.code=l.code LEFT JOIN libraries lib ON lib.id=b.library_id WHERE l.user_id=? AND l.returned IS NOT NULL', [$uid]);
        out(['current' => $current, 'past' => $past, 'my_lib' => $me['lib_name']]);
      } elseif ($IS_LIB) {
        $lib = $me['library_id'];
        $d = date('c');
        out([
          'titles' => (int)db()->query("SELECT COUNT(*) FROM (SELECT 1 FROM books WHERE library_id=$lib GROUP BY title, author)")->fetchColumn(),
          'copies' => (int)db()->query("SELECT COUNT(*) FROM books WHERE library_id=$lib")->fetchColumn(),
          'lent' => (int)db()->query("SELECT COUNT(*) FROM loans l JOIN books b ON b.code=l.code WHERE b.library_id=$lib AND l.returned IS NULL")->fetchColumn(),
          'overdue' => (int)db()->query("SELECT COUNT(*) FROM loans l JOIN books b ON b.code=l.code WHERE b.library_id=$lib AND l.returned IS NULL AND l.due<'$d'")->fetchColumn(),
          'members' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='member' AND library_id=$lib")->fetchColumn(),
        ]);
      } else {
        $wB = $lib_id > 0 ? "WHERE library_id=$lib_id" : "";
        $d = date('c');
        $wL = $lib_id > 0 ? "JOIN books b ON b.code=l.code WHERE b.library_id=$lib_id AND l.returned IS NULL" : "WHERE l.returned IS NULL";
        out([
          'titles' => (int)db()->query("SELECT COUNT(*) FROM (SELECT 1 FROM books $wB GROUP BY title, author)")->fetchColumn(),
          'copies' => (int)db()->query("SELECT COUNT(*) FROM books $wB")->fetchColumn(),
          'lent' => (int)db()->query("SELECT COUNT(*) FROM loans l $wL")->fetchColumn(),
          'overdue' => (int)db()->query("SELECT COUNT(*) FROM loans l $wL AND l.due<'$d'")->fetchColumn(),
          'members' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='member' " . ($lib_id > 0 ? "AND library_id=$lib_id" : ""))->fetchColumn(),
          'librarians' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='librarian' " . ($lib_id > 0 ? "AND library_id=$lib_id" : ""))->fetchColumn(),
        ]);
      }
      break;

    case 'download_report':
      if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
      $type = $_GET['report_type'] ?? '';
      $targetLib = $IS_LIB ? $me['library_id'] : $lib_id;
      $libFilter = $targetLib > 0 ? " AND b.library_id = $targetLib" : "";
      $userLibFilter = $targetLib > 0 ? " AND u.library_id = $targetLib" : "";

      if ($type === 'books_period') {
        $data = all_("SELECT b.code, b.title, b.author, l.name as lib_name FROM books b LEFT JOIN libraries l ON l.id=b.library_id WHERE 1=1 $libFilter", []);
        out($data);
      } elseif ($type === 'members_age') {
        $data = all_("SELECT u.id, u.name, u.username, u.phone, u.nic, u.dob, l.name as lib_name FROM users u LEFT JOIN libraries l ON l.id=u.library_id WHERE u.role='member' $userLibFilter", []);
        out($data);
      } elseif ($type === 'overdue_loans') {
        $d = date('c');
        $data = all_("SELECT l.id, l.code, b.title, u.name as member_name, u.phone, l.date_out, l.due, lib.name as lib_name FROM loans l JOIN books b ON b.code=l.code LEFT JOIN libraries lib ON lib.id=b.library_id LEFT JOIN users u ON u.id=l.user_id WHERE l.returned IS NULL AND l.due < '$d' $libFilter", []);
        out($data);
      }
      fail('වැරදි වාර්තා වර්ගයකි');

    case 'lend':
      if (!$IS_LIB) fail('අවසර නැත');
      $b = one('SELECT code, library_id FROM books WHERE code=?', [trim($in['code'] ?? '')]); if (!$b) fail('පොත නැත');
      if ($b['library_id'] != $me['library_id']) fail('මෙම පොත ඔබේ පුස්තකාලයට අයත් නැත');
      $u = one('SELECT id FROM users WHERE (username=? OR id=?) AND library_id=?', [trim($in['username'] ?? ''), (int)($in['username'] ?? 0), $me['library_id']]); if (!$u) fail('සාමාජිකයා මෙම පුස්තකාලයේ නැත');
      
      $activeLoans = (int)db()->query("SELECT COUNT(*) FROM loans WHERE user_id={$u['id']} AND returned IS NULL")->fetchColumn();
      if ($activeLoans >= 2) fail('මෙම සාමාජිකයා දැනටමත් උපරිම පොත් සීමාව (පොත් 2ක්) ලබාගෙන ඇත!');

      if (one('SELECT 1 FROM loans WHERE code=? AND returned IS NULL', [$b['code']])) fail('පොත ණයට දී ඇත');
      st('INSERT INTO loans(code,user_id,date_out,due) VALUES(?,?,?,?)', [$b['code'], $u['id'], date('c'), date('c', time() + 14 * 86400)]); out(['ok' => 1]);

    case 'return':
      if (!$IS_LIB) fail('අවසර නැත');
      st('UPDATE loans SET returned=? WHERE code=? AND returned IS NULL', [date('c'), trim($in['code'] ?? '')]); out(['ok' => 1]);

    case 'members':
      if ($IS_MEM) fail('අවසර නැත');
      $targetLib = $IS_LIB ? $me['library_id'] : $lib_id;
      $w = "role='member' " . ($targetLib > 0 ? "AND library_id=$targetLib" : "");
      out([
        'rows' => all_("SELECT u.*, lib.name as lib_name,
            (SELECT COUNT(*) FROM loans l WHERE l.user_id=u.id AND l.returned IS NULL) held,
            (SELECT COUNT(*) FROM loans l WHERE l.user_id=u.id) total
            FROM users u LEFT JOIN libraries lib ON lib.id=u.library_id WHERE $w ORDER BY u.id DESC")
      ]);

    case 'librarians':
      if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
      $targetLib = $IS_LIB ? $me['library_id'] : $lib_id;
      $w = "u.role='librarian' " . ($targetLib > 0 ? "AND u.library_id=$targetLib" : "");
      out(all_("SELECT u.*, l.name as lib_name FROM users u LEFT JOIN libraries l ON l.id=u.library_id WHERE $w ORDER BY u.id DESC"));

    case 'add_librarian':
      if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
      $targetLib = $IS_LIB ? $me['library_id'] : (int)($in['library_id'] ?? ($lib_id > 0 ? $lib_id : 1));
      $name = trim($in['name'] ?? '');
      $empNo = trim($in['emp_no'] ?? '');
      $phone = trim($in['phone'] ?? '');
      $email = trim($in['email'] ?? '');
      $pw = password_hash(trim($in['password'] ?? 'lib123'), PASSWORD_DEFAULT);
      
      if(!$name || !$empNo || !$email) fail('කරුණාකර සියලුම අත්‍යවශ්‍ය තොරතුරු පුරවන්න!');
      if(one('SELECT 1 FROM users WHERE username=?', [$empNo])) fail('මෙම සේවක අංකය (Employee No) දැනටමත් පවතී!');

      st('INSERT INTO users(username, pw, role, library_id, name, phone) VALUES(?, ?, "librarian", ?, ?, ?)', [$empNo, $pw, $targetLib, $name, $phone]);
      out(['ok' => 1, 'emp_no' => $empNo]);

    case 'delete_librarian':
      if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
      $id = (int)($in['id'] ?? 0);
      st('DELETE FROM users WHERE id=? AND role="librarian"', [$id]);
      out(['ok' => 1]);

    case 'loans':
      if ($IS_MEM) fail('අවසර නැත');
      $targetLib = $IS_LIB ? $me['library_id'] : $lib_id;
      $wL = "l.returned IS NULL" . ($targetLib > 0 ? " AND b.library_id = $targetLib" : "");
      out(all_("SELECT l.*, b.title, b.author, b.library_id, lib.name as lib_name, u.name, u.username, u.phone, u.username as email FROM loans l JOIN books b ON b.code=l.code LEFT JOIN libraries lib ON lib.id=b.library_id LEFT JOIN users u ON u.id=l.user_id WHERE $wL ORDER BY l.due"));

    default: fail('unknown');
  }
} catch (Throwable $e) { fail('දෝෂයක්: ' . $e->getMessage()); }
?>