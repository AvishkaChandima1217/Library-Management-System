<?php
require __DIR__ . '/db.php';
ini_set('display_errors', '0');
session_start();
$uid = $_SESSION['u'] ?? 0;
session_write_close();

$me = $uid ? one('SELECT u.*, l.name AS lib_name FROM users u LEFT JOIN libraries l ON l.id = u.library_id WHERE u.id = ?', [$uid]) : null;
if (!$me) {
  http_response_code(401);
  exit('{"error":"auth"}');
}

$ROLE = $me['role'];
$IS_ADMIN = $ROLE === 'admin';
$IS_LIB = $ROLE === 'librarian';
$IS_MEM = $ROLE === 'member';

function out($d) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($d, JSON_UNESCAPED_UNICODE);
  exit;
}

function fail($m) {
  out(['error' => $m]);
}

function nextBookCode() {
  $maxNum = (int)db()->query("SELECT COALESCE(MAX(CAST(SUBSTRING(code, 2) AS UNSIGNED)), 0) FROM books WHERE code REGEXP '^B[0-9]+$'")->fetchColumn();
  return sprintf('B%05d', $maxNum + 1);
}

$a = $_GET['a'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$lib_id = $IS_ADMIN ? (int)($_GET['lib_id'] ?? 0) : (int)$me['library_id'];

try {
  switch ($a) {
    case 'libraries':
      out(all_('SELECT id, name FROM libraries ORDER BY id ASC'));

    case 'add_library':
      if (!$IS_ADMIN) fail('අවසර නැත');
      $name = trim($in['name'] ?? '');
      if ($name === '') fail('පුස්තකාලයේ නම අත්‍යවශ්‍යයි!');
      if (one('SELECT id FROM libraries WHERE LOWER(name) = LOWER(?)', [$name])) {
        fail('මෙම නමින් පුස්තකාලයක් දැනටමත් පවතී!');
      }
      st('INSERT INTO libraries(name) VALUES(?)', [$name]);
      $newId = (int)db()->lastInsertId();
      out(['ok' => 1, 'id' => $newId, 'name' => $name]);

    case 'search':
      $q = trim($_GET['q'] ?? '');
      $targetLib = $IS_MEM ? (int)$me['library_id'] : ($IS_ADMIN ? $lib_id : (int)$me['library_id']);
      $w = '1=1' . ($targetLib > 0 ? ' AND b.library_id = ' . $targetLib : '');
      $p = [];
      if ($q !== '') {
        $w .= " AND (b.title LIKE :q ESCAPE '\\\\' OR b.author LIKE :q ESCAPE '\\\\' OR b.code LIKE :q ESCAPE '\\\\')";
        $p['q'] = '%' . addcslashes($q, '%_\\') . '%';
      }
      $g = "FROM books b LEFT JOIN libraries lib ON lib.id = b.library_id WHERE $w GROUP BY b.title, b.author, b.library_id, lib.name";
      out([
        'total' => (int)st("SELECT COUNT(*) FROM (SELECT 1 $g) AS sub_cnt", $p)->fetchColumn(),
        'rows' => all_("SELECT b.title, b.author, b.library_id, lib.name AS lib_name, COUNT(*) AS copies, SUM(EXISTS(SELECT 1 FROM loans l WHERE l.code = b.code AND l.returned IS NULL)) AS lent $g ORDER BY b.title LIMIT 50", $p)
      ]);

    case 'copies':
      $targetLib = $IS_MEM ? (int)$me['library_id'] : ($IS_ADMIN ? $lib_id : (int)$me['library_id']);
      $r = all_(
        'SELECT b.code, b.library_id, lib.name AS lib_name, l.due, u.name, u.username
         FROM books b
         LEFT JOIN libraries lib ON lib.id = b.library_id
         LEFT JOIN loans l ON l.code = b.code AND l.returned IS NULL
         LEFT JOIN users u ON u.id = l.user_id
         WHERE b.title = ? AND b.author = ? ' . ($targetLib > 0 ? 'AND b.library_id = ' . $targetLib : '') . '
         ORDER BY b.code',
        [$_GET['title'] ?? '', $_GET['author'] ?? '']
      );
      if ($IS_MEM) {
        foreach ($r as &$x) {
          unset($x['name'], $x['username']);
        }
      }
      out($r);

    case 'book':
      $code = trim($_GET['code'] ?? '');
      $b = one('SELECT b.*, l.name AS lib_name FROM books b LEFT JOIN libraries l ON l.id = b.library_id WHERE b.code = ?', [$code]);
      if (!$b) {
        out(['book' => null]);
      }
      $loan = one('SELECT l.*, u.name, u.username, u.phone, u.email FROM loans l LEFT JOIN users u ON u.id = l.user_id WHERE l.code = ? AND l.returned IS NULL', [$code]);
      $allCopies = all_('SELECT b.code, l.due FROM books b LEFT JOIN loans l ON l.code = b.code AND l.returned IS NULL WHERE b.title = ? AND b.author = ? AND b.library_id = ? ORDER BY b.code', [$b['title'], $b['author'], $b['library_id']]);
      out(['book' => $b, 'loan' => $loan, 'all_copies' => $allCopies]);

    case 'stats':
      if ($IS_MEM) {
        $current = all_('SELECT l.*, b.title, lib.name AS lib_name FROM loans l LEFT JOIN books b ON b.code = l.code LEFT JOIN libraries lib ON lib.id = b.library_id WHERE l.user_id = ? AND l.returned IS NULL', [$uid]);
        $past = all_('SELECT l.*, b.title, lib.name AS lib_name FROM loans l LEFT JOIN books b ON b.code = l.code LEFT JOIN libraries lib ON lib.id = b.library_id WHERE l.user_id = ? AND l.returned IS NOT NULL', [$uid]);
        out(['current' => $current, 'past' => $past, 'my_lib' => $me['lib_name']]);
      } elseif ($IS_LIB) {
        $lib = (int)$me['library_id'];
        $d = date('c');
        out([
          'titles' => (int)db()->query("SELECT COUNT(*) FROM (SELECT 1 FROM books WHERE library_id = $lib GROUP BY title, author) AS t_cnt")->fetchColumn(),
          'copies' => (int)db()->query("SELECT COUNT(*) FROM books WHERE library_id = $lib")->fetchColumn(),
          'lent' => (int)db()->query("SELECT COUNT(*) FROM loans l JOIN books b ON b.code = l.code WHERE b.library_id = $lib AND l.returned IS NULL")->fetchColumn(),
          'overdue' => (int)st("SELECT COUNT(*) FROM loans l JOIN books b ON b.code = l.code WHERE b.library_id = ? AND l.returned IS NULL AND l.due < ?", [$lib, $d])->fetchColumn(),
          'members' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND library_id = $lib")->fetchColumn(),
        ]);
      } else {
        $wB = $lib_id > 0 ? "WHERE library_id = $lib_id" : "";
        $d = date('c');
        $wL = $lib_id > 0 ? "JOIN books b ON b.code = l.code WHERE b.library_id = $lib_id AND l.returned IS NULL" : "WHERE l.returned IS NULL";
        $wO = $lib_id > 0 ? "JOIN books b ON b.code = l.code WHERE b.library_id = $lib_id AND l.returned IS NULL AND l.due < ?" : "WHERE l.returned IS NULL AND l.due < ?";
        out([
          'titles' => (int)db()->query("SELECT COUNT(*) FROM (SELECT 1 FROM books $wB GROUP BY title, author) AS t_cnt")->fetchColumn(),
          'copies' => (int)db()->query("SELECT COUNT(*) FROM books $wB")->fetchColumn(),
          'lent' => (int)db()->query("SELECT COUNT(*) FROM loans l $wL")->fetchColumn(),
          'overdue' => (int)st("SELECT COUNT(*) FROM loans l $wO", [$d])->fetchColumn(),
          'members' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'member'" . ($lib_id > 0 ? " AND library_id = $lib_id" : ""))->fetchColumn(),
          'librarians' => (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'librarian'" . ($lib_id > 0 ? " AND library_id = $lib_id" : ""))->fetchColumn(),
        ]);
      }
      break;

    case 'lend':
      if (!$IS_LIB) fail('අවසර නැත');
      $b = one('SELECT code, library_id FROM books WHERE code = ?', [trim($in['code'] ?? '')]);
      if (!$b) fail('පොත නැත');
      if ((int)$b['library_id'] !== (int)$me['library_id']) fail('මෙම පොත ඔබේ පුස්තකාලයට අයත් නැත');
      $u = one('SELECT id FROM users WHERE (username = ? OR id = ?) AND library_id = ?', [trim($in['username'] ?? ''), (int)($in['username'] ?? 0), $me['library_id']]);
      if (!$u) fail('සාමාජිකයා මෙම පුස්තකාලයේ නැත');
      $activeLoans = (int)st('SELECT COUNT(*) FROM loans WHERE user_id = ? AND returned IS NULL', [$u['id']])->fetchColumn();
      if ($activeLoans >= 2) fail('උපරිම පොත් සීමාව (පොත් 2ක්) ලබාගෙන ඇත!');
      if (one('SELECT 1 FROM loans WHERE code = ? AND returned IS NULL', [$b['code']])) fail('පොත ණයට දී ඇත');
      st('INSERT INTO loans(code, user_id, date_out, due) VALUES(?, ?, ?, ?)', [$b['code'], $u['id'], date('c'), date('c', time() + 14 * 86400)]);
      out(['ok' => 1]);

    case 'return':
      if (!$IS_LIB) fail('අවසර නැත');
      st('UPDATE loans SET returned = ? WHERE code = ? AND returned IS NULL', [date('c'), trim($in['code'] ?? '')]);
      out(['ok' => 1]);

    case 'members':
      if ($IS_MEM) fail('අවසර නැත');
      $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
      $w = "role = 'member'" . ($targetLib > 0 ? " AND library_id = $targetLib" : "");
      out(['rows' => all_("SELECT u.*, lib.name AS lib_name, (SELECT COUNT(*) FROM loans l WHERE l.user_id = u.id AND l.returned IS NULL) AS held FROM users u LEFT JOIN libraries lib ON lib.id = u.library_id WHERE $w ORDER BY u.id DESC")]);

    case 'add_member':
      if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
      $targetLib = $IS_LIB ? (int)$me['library_id'] : (int)($in['library_id'] ?? 1);
      $name = trim($in['name'] ?? '');
      $username = trim($in['username'] ?? '');
      $phone = trim($in['phone'] ?? '');
      $nic = trim($in['nic'] ?? '');
      $dob = trim($in['dob'] ?? '');
      $address = trim($in['address'] ?? '');
      $email = trim($in['email'] ?? '');
      $password = (string)($in['password'] ?? '');
      if ($name === '' || $username === '' || strlen($password) < 4) fail('නම, Username සහ අවම අක්ෂර 4ක මුරපදය අත්‍යවශ්‍යයි!');
      if (one('SELECT 1 FROM users WHERE username = ?', [$username])) fail('මෙම Username එක දැනටමත් භාවිතයේ ඇත!');
      st('INSERT INTO users(username, pw, role, library_id, name, phone, nic, dob, address, email) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $username, password_hash($password, PASSWORD_DEFAULT), 'member', $targetLib, $name, $phone, $nic, $dob, $address, $email
      ]);
      out(['ok' => 1]);

    case 'delete_member':
      if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
      $id = (int)($in['id'] ?? 0);
      st("DELETE FROM users WHERE id = ? AND role = 'member'", [$id]);
      out(['ok' => 1]);

    case 'librarians':
      if (!$IS_ADMIN) fail('අවසර නැත');
      $w = "u.role = 'librarian'" . ($lib_id > 0 ? " AND u.library_id = $lib_id" : "");
      out(all_("SELECT u.*, l.name AS lib_name FROM users u LEFT JOIN libraries l ON l.id = u.library_id WHERE $w ORDER BY u.id DESC"));

    case 'delete_librarian':
      if (!$IS_ADMIN) fail('අවසර නැත');
      $id = (int)($in['id'] ?? 0);
      st("DELETE FROM users WHERE id = ? AND role = 'librarian'", [$id]);
      out(['ok' => 1]);

    case 'send_otp':
      if (!$IS_ADMIN) fail('අවසර නැත');
      $email = trim($in['email'] ?? '');
      if ($email === '') fail('ඊමේල් ලිපිනය අත්‍යවශ්‍යයි!');
      $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
      session_start();
      $_SESSION['librarian_otp'] = ['email' => $email, 'otp' => $otp, 'exp' => time() + 600];
      session_write_close();
      out(['ok' => 1, 'otp' => $otp]);

    case 'verify_and_add_librarian':
      if (!$IS_ADMIN) fail('අවසර නැත');
      session_start();
      $otpStore = $_SESSION['librarian_otp'] ?? null;
      session_write_close();

      $targetLib = (int)($in['library_id'] ?? ($lib_id > 0 ? $lib_id : 1));
      $name = trim($in['name'] ?? '');
      $empNo = trim($in['emp_no'] ?? $in['username'] ?? '');
      $phone = trim($in['phone'] ?? '');
      $email = trim($in['email'] ?? '');
      $otpInput = trim($in['otp'] ?? '');
      $password = (string)($in['password'] ?? '');

      if ($name === '' || $empNo === '' || $email === '' || $password === '') fail('සියලුම තොරතුරු පුරවන්න!');
      if (!$otpStore || $otpStore['exp'] < time() || strcasecmp($otpStore['email'], $email) !== 0) {
        fail('OTP සැසිය කල් ඉකුත් වී ඇත හෝ ඊමේල් ලිපිනය නොගැලපේ.');
      }
      if ($otpInput !== '' && (string)$otpStore['otp'] !== $otpInput) {
        fail('OTP කේතය වැරදියි!');
      }
      if (one('SELECT 1 FROM users WHERE username = ?', [$empNo])) fail('මෙම සේවක අංකය දැනටමත් පවතී!');

      st('INSERT INTO users(username, pw, role, library_id, name, phone, nic, email) VALUES(?, ?, ?, ?, ?, ?, ?, ?)', [
        $empNo, password_hash($password, PASSWORD_DEFAULT), 'librarian', $targetLib, $name, $phone, $email, $email
      ]);

      session_start();
      unset($_SESSION['librarian_otp']);
      session_write_close();
      out(['ok' => 1]);

    case 'loans':
      if ($IS_MEM) fail('අවසර නැත');
      $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
      $wL = 'l.returned IS NULL' . ($targetLib > 0 ? ' AND b.library_id = ' . $targetLib : '');
      out(all_("SELECT l.*, b.title, b.author, lib.name AS lib_name, u.name, u.username, u.phone, u.email FROM loans l JOIN books b ON b.code = l.code LEFT JOIN libraries lib ON lib.id = b.library_id LEFT JOIN users u ON u.id = l.user_id WHERE $wL ORDER BY l.due"));

    case 'add_single_book':
      if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
      $target_lib = $IS_LIB ? (int)$me['library_id'] : (int)($in['library_id'] ?? 1);
      $title = trim($in['title'] ?? '');
      $author = trim($in['author'] ?? '');
      $code = trim($in['code'] ?? '');
      if ($title === '') fail('පොතේ නම අත්‍යවශ්‍යයි!');
      if ($code === '') {
        $code = nextBookCode();
      }
      if (one('SELECT 1 FROM books WHERE code = ?', [$code])) fail('මෙම Barcode / Code එක දැනටමත් පවතී!');
      st('INSERT INTO books(code, library_id, title, author) VALUES(?, ?, ?, ?)', [$code, $target_lib, $title, $author]);
      out(['ok' => 1, 'code' => $code]);

    case 'import_books':
    case 'import_excel':
      if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
      $target_lib = $IS_LIB ? (int)$me['library_id'] : (int)($in['library_id'] ?? 1);
      $books = $in['books'] ?? [];
      if (empty($books)) fail('දත්ත නැත');
      $num = (int)db()->query("SELECT COALESCE(MAX(CAST(SUBSTRING(code, 2) AS UNSIGNED)), 0) FROM books WHERE code REGEXP '^B[0-9]+$'")->fetchColumn() + 1;
      $created = [];
      $pdo = db();
      $pdo->beginTransaction();
      $stmt = $pdo->prepare('INSERT INTO books(code, library_id, title, author) VALUES(?, ?, ?, ?)');
      foreach ($books as $b) {
        $title = trim($b['title'] ?? '');
        $author = trim($b['author'] ?? '');
        $copies = max(1, (int)($b['copies'] ?? 1));
        if ($title === '') continue;
        for ($i = 0; $i < $copies; $i++) {
          $code = sprintf('B%05d', $num++);
          $stmt->execute([$code, $target_lib, $title, $author]);
          $created[] = ['code' => $code, 'title' => $title, 'author' => $author];
        }
      }
      $pdo->commit();
      out(['ok' => 1, 'added' => count($created), 'created' => $created]);

    case 'audit_check_book':
      if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
      $code = trim($in['code'] ?? '');
      if ($code === '') fail('කේතය අවශ්‍යයි');
      $b = one('SELECT * FROM books WHERE code = ?', [$code]);
      if (!$b) fail('පොත හමු නොවීය');
      if ($IS_LIB && (int)$b['library_id'] !== (int)$me['library_id']) fail('වෙනත් පුස්තකාලයක පොතක්');
      st('INSERT INTO audit_logs(library_id, book_code, checked_by, checked_at) VALUES(?, ?, ?, ?)', [$b['library_id'], $b['code'], $uid, date('c')]);
      out(['ok' => 1, 'book' => $b]);

    case 'download_audit_report':
      if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
      $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
      $status = $_GET['status'] ?? 'all';
      $w = $targetLib > 0 ? 'WHERE b.library_id = ' . $targetLib : '';
      $rows = all_("SELECT b.code, b.title, b.author, lib.name AS lib_name,
        EXISTS(SELECT 1 FROM audit_logs a WHERE a.book_code = b.code) AS audited,
        EXISTS(SELECT 1 FROM loans l WHERE l.code = b.code AND l.returned IS NULL) AS on_loan
        FROM books b LEFT JOIN libraries lib ON lib.id = b.library_id $w ORDER BY b.code");
      if ($status === 'audited' || $status === 'checked') {
        $rows = array_values(array_filter($rows, fn($r) => (int)$r['audited'] === 1));
      } elseif ($status === 'missing' || $status === 'unchecked') {
        $rows = array_values(array_filter($rows, fn($r) => (int)$r['audited'] === 0 && (int)$r['on_loan'] === 0));
      }
      out($rows);

    case 'download_report':
      if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
      $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
      $type = $_GET['report_type'] ?? ($_GET['type'] ?? 'loans');
      if ($type === 'members' || $type === 'members_age') {
        $w = "role = 'member'" . ($targetLib > 0 ? " AND library_id = $targetLib" : "");
        out(all_("SELECT id, username, name, phone, nic, dob, address, email, library_id FROM users WHERE $w ORDER BY id DESC"));
      } elseif ($type === 'books_period') {
        $w = $targetLib > 0 ? "WHERE b.library_id = $targetLib" : "";
        out(all_("SELECT b.code, b.title, b.author, lib.name AS lib_name FROM books b LEFT JOIN libraries lib ON lib.id = b.library_id $w ORDER BY b.code"));
      } else {
        $w = "l.returned IS NULL" . ($targetLib > 0 ? " AND b.library_id = $targetLib" : "");
        out(all_("SELECT l.id, l.code, b.title, u.username, u.name, u.phone, u.email, l.date_out, l.due FROM loans l JOIN books b ON b.code = l.code LEFT JOIN users u ON u.id = l.user_id WHERE $w ORDER BY l.due"));
      }

    case 'get_news':
      if ($IS_LIB) {
        out(all_("SELECT n.*, u.name AS author_name FROM news n LEFT JOIN users u ON u.id = n.created_by WHERE n.target_audience = 'librarian' OR n.target_audience = 'all' ORDER BY n.id DESC"));
      } elseif ($IS_MEM) {
        out(all_("SELECT n.*, u.name AS author_name FROM news n LEFT JOIN users u ON u.id = n.created_by WHERE n.target_audience = 'all' ORDER BY n.id DESC"));
      } else {
        out(all_("SELECT n.*, u.name AS author_name FROM news n LEFT JOIN users u ON u.id = n.created_by ORDER BY n.id DESC"));
      }

    case 'get_admin_news_notifications':
      if (!$IS_ADMIN) fail('අවසර නැත');
      out(all_("SELECT n.*, u.name AS author_name FROM news n LEFT JOIN users u ON u.id = n.created_by WHERE n.author_role = 'librarian' AND n.admin_notified = 1 ORDER BY n.id DESC"));

    case 'mark_news_notified_read':
      if (!$IS_ADMIN) fail('අවසර නැත');
      $id = (int)($in['id'] ?? 0);
      if ($id > 0) {
        st('UPDATE news SET admin_notified = 0 WHERE id = ?', [$id]);
      } else {
        st("UPDATE news SET admin_notified = 0 WHERE author_role = 'librarian'");
      }
      out(['ok' => 1]);

    case 'add_news':
      if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
      $title = trim($in['title'] ?? '');
      $content = trim($in['content'] ?? '');
      $audience = trim($in['target_audience'] ?? 'all');
      if (!in_array($audience, ['all', 'librarian'], true)) $audience = 'all';
      if ($title === '' || $content === '') fail('ශීර්ෂය සහ අන්තර්ගතය අත්‍යවශ්‍යයි!');

      $role = $IS_ADMIN ? 'admin' : 'librarian';
      $notified = $IS_LIB ? 1 : 0;
      st(
        'INSERT INTO news(title, content, target_audience, created_by, author_role, admin_notified, created_at) VALUES(?, ?, ?, ?, ?, ?, ?)',
        [$title, $content, $audience, $uid, $role, $notified, date('c')]
      );
      out(['ok' => 1]);

    case 'edit_news':
      if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
      $id = (int)($in['id'] ?? 0);
      $title = trim($in['title'] ?? '');
      $content = trim($in['content'] ?? '');
      $audience = trim($in['target_audience'] ?? 'all');
      if (!in_array($audience, ['all', 'librarian'], true)) $audience = 'all';
      if ($id <= 0 || $title === '' || $content === '') fail('දත්ත සම්පූර්ණ නැත!');

      $row = one('SELECT * FROM news WHERE id = ?', [$id]);
      if (!$row) fail('පුවත හමු නොවීය');

      if (!$IS_ADMIN) {
        $isOwnNews = (int)($row['created_by'] ?? 0) === (int)$uid;
        $isLibAuthor = empty($row['author_role']) || ($row['author_role'] === 'librarian');
        if (!$isOwnNews || !$isLibAuthor) {
          fail('Admin විසින් එක් කළ හෝ වෙනත් අයෙකුගේ පුවත් සංස්කරණය කිරීමට ඔබට අවසර නැත!');
        }
      }

      $notified = $IS_LIB ? 1 : (int)($row['admin_notified'] ?? 0);
      st(
        'UPDATE news SET title = ?, content = ?, target_audience = ?, admin_notified = ? WHERE id = ?',
        [$title, $content, $audience, $notified, $id]
      );
      out(['ok' => 1]);

    case 'delete_news':
      if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
      $id = (int)($in['id'] ?? 0);
      if ($id <= 0) fail('Invalid ID');

      $row = one('SELECT * FROM news WHERE id = ?', [$id]);
      if (!$row) fail('පුවත හමු නොවීය');

      if (!$IS_ADMIN) {
        $isOwnNews = (int)($row['created_by'] ?? 0) === (int)$uid;
        $isLibAuthor = empty($row['author_role']) || ($row['author_role'] === 'librarian');
        if (!$isOwnNews || !$isLibAuthor) {
          fail('Admin විසින් එක් කළ හෝ වෙනත් අයෙකුගේ පුවත් මකා දැමීමට ඔබට අවසර නැත!');
        }
      }

      st('DELETE FROM news WHERE id = ?', [$id]);
      out(['ok' => 1]);

    default:
      fail('unknown');
  }
} catch (Throwable $e) {
  fail('දෝෂයක්: ' . $e->getMessage());
}
?>