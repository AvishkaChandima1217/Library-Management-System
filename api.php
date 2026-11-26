<?php
require_once __DIR__ . '/db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

function out($data) {
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function fail($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['u'])) {
  fail('Unauthorized', 401);
}

$me = one(
  'SELECT u.*, l.name AS lib_name
   FROM users u
   LEFT JOIN libraries l ON l.id = u.library_id
   WHERE u.id = ?',
  [(int)$_SESSION['u']]
);

if (!$me) {
  session_destroy();
  fail('Unauthorized', 401);
}

$role     = $me['role'] ?? 'member';
$IS_ADMIN = ($role === 'admin');
$IS_LIB   = ($role === 'librarian');
$IS_MEM   = ($role === 'member');

$rawBody = file_get_contents('php://input');
$in = [];
if ($rawBody) {
  $decoded = json_decode($rawBody, true);
  if (is_array($decoded)) {
    $in = $decoded;
  }
}
if (!empty($_POST)) {
  $in = array_merge($in, $_POST);
}

$a = $_GET['a'] ?? '';
$reqLibId = isset($_GET['lib_id']) ? (int)$_GET['lib_id'] : (int)($in['lib_id'] ?? 0);

if ($IS_MEM || $IS_LIB) {
  $lib_id = (int)($me['library_id'] ?? 0);
} else {
  $lib_id = $reqLibId;
}

switch ($a) {
  case 'libraries':
    out(all_("SELECT id, name FROM libraries ORDER BY id ASC"));

  case 'add_library':
    if (!$IS_ADMIN) fail('අවසර නැත');
    $name = trim($in['name'] ?? '');
    if ($name === '') fail('පුස්තකාලයේ නම ඇතුළත් කරන්න!');
    st('INSERT INTO libraries(name) VALUES(?)', [$name]);
    $newId = (int)db()->lastInsertId();
    out(['ok' => 1, 'id' => $newId, 'name' => $name]);

  case 'stats':
    if ($IS_MEM) {
      $myLibRow = one('SELECT name FROM libraries WHERE id = ?', [$lib_id]);
      $myLibName = $myLibRow['name'] ?? ($me['lib_name'] ?? ('Library #' . $lib_id));
      $current = all_(
        "SELECT l.*, b.title, b.author
         FROM loans l
         JOIN books b ON b.code = l.code
         WHERE l.user_id = ? AND l.returned IS NULL AND b.library_id = ?
         ORDER BY l.due ASC",
        [(int)$me['id'], $lib_id]
      );
      $reservations = all_(
        "SELECT * FROM reservations WHERE user_id = ? AND library_id = ? AND status = 'pending' ORDER BY id DESC",
        [(int)$me['id'], $lib_id]
      );
      out([
        'my_lib' => $myLibName,
        'current' => $current,
        'reservations' => $reservations
      ]);
    }

    $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
    $bWhere = $targetLib > 0 ? "WHERE library_id = " . (int)$targetLib : "";
    $bJoinWhere = $targetLib > 0 ? "WHERE b.library_id = " . (int)$targetLib : "";
    $mWhere = "WHERE role = 'member'" . ($targetLib > 0 ? " AND library_id = " . (int)$targetLib : "");

    $titles = (int)one("SELECT COUNT(DISTINCT CONCAT(title, '|||', author, '|||', library_id)) AS c FROM books $bWhere")['c'];
    $copies = (int)one("SELECT COUNT(*) AS c FROM books $bWhere")['c'];
    $lent   = (int)one("SELECT COUNT(*) AS c FROM loans l JOIN books b ON b.code = l.code $bJoinWhere AND l.returned IS NULL")['c'];
    $overdue = (int)one("SELECT COUNT(*) AS c FROM loans l JOIN books b ON b.code = l.code $bJoinWhere AND l.returned IS NULL AND DATE(l.due) < CURDATE()")['c'];
    $members = (int)one("SELECT COUNT(*) AS c FROM users $mWhere")['c'];

    $myLibRow = one('SELECT name FROM libraries WHERE id = ?', [$targetLib]);
    out([
      'titles' => $titles,
      'copies' => $copies,
      'lent' => $lent,
      'overdue' => $overdue,
      'members' => $members,
      'my_lib' => $myLibRow['name'] ?? ($me['lib_name'] ?? '')
    ]);

  case 'search':
    $q = trim($_GET['q'] ?? '');
    $targetLib = ($IS_MEM || $IS_LIB) ? (int)$me['library_id'] : $lib_id;
    $off = max(0, (int)($_GET['off'] ?? 0));
    $lim = 20;

    $conds = [];
    $params = [];

    if ($targetLib > 0) {
      $conds[] = "b.library_id = ?";
      $params[] = $targetLib;
    }

    if ($q !== '') {
      $conds[] = "(b.title LIKE ? OR b.author LIKE ? OR b.code LIKE ?)";
      $like = '%' . $q . '%';
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
    }

    $whereSql = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

    $totalRow = one(
      "SELECT COUNT(*) AS c FROM (
         SELECT b.title, b.author, b.library_id
         FROM books b
         $whereSql
         GROUP BY b.title, b.author, b.library_id
       ) sub",
      $params
    );
    $total = (int)($totalRow['c'] ?? 0);

    $sql = "SELECT b.title, b.author, b.library_id, l.name AS lib_name,
                   COUNT(*) AS copies,
                   SUM(CASE WHEN EXISTS (
                     SELECT 1 FROM loans ln WHERE ln.code = b.code AND ln.returned IS NULL
                   ) THEN 1 ELSE 0 END) AS lent
            FROM books b
            LEFT JOIN libraries l ON l.id = b.library_id
            $whereSql
            GROUP BY b.title, b.author, b.library_id, l.name
            ORDER BY b.title ASC
            LIMIT $lim OFFSET $off";

    out([
      'rows' => all_($sql, $params),
      'total' => $total
    ]);

  case 'copies':
    $title = trim($_GET['title'] ?? '');
    $author = trim($_GET['author'] ?? '');
    $targetLib = ($IS_MEM || $IS_LIB) ? (int)$me['library_id'] : $lib_id;

    $sql = "SELECT b.code, b.library_id,
                   (SELECT ln.due FROM loans ln WHERE ln.code = b.code AND ln.returned IS NULL ORDER BY ln.id DESC LIMIT 1) AS due
            FROM books b
            WHERE b.title = ? AND b.author = ?";
    $params = [$title, $author];

    if ($targetLib > 0) {
      $sql .= " AND b.library_id = ?";
      $params[] = $targetLib;
    }
    $sql .= " ORDER BY b.code ASC";

    out(all_($sql, $params));

  case 'book':
    $code = trim($_GET['code'] ?? '');
    if ($code === '') fail('Book code required');
    $targetLib = ($IS_MEM || $IS_LIB) ? (int)$me['library_id'] : 0;

    $sql = "SELECT b.*, l.name AS lib_name
            FROM books b
            LEFT JOIN libraries l ON l.id = b.library_id
            WHERE b.code = ?";
    $params = [$code];
    if ($targetLib > 0) {
      $sql .= " AND b.library_id = ?";
      $params[] = $targetLib;
    }
    $book = one($sql . " LIMIT 1", $params);
    if (!$book) {
      out(['book' => null, 'loan' => null, 'all_copies' => []]);
    }

    $loan = one(
      "SELECT ln.*, u.name, u.username, u.phone, u.email
       FROM loans ln
       LEFT JOIN users u ON u.id = ln.user_id
       WHERE ln.code = ? AND ln.returned IS NULL
       ORDER BY ln.id DESC LIMIT 1",
      [$book['code']]
    );

    $allCopies = all_(
      "SELECT b.code,
              (SELECT ln.due FROM loans ln WHERE ln.code = b.code AND ln.returned IS NULL ORDER BY ln.id DESC LIMIT 1) AS due
       FROM books b
       WHERE b.title = ? AND b.author = ? AND b.library_id = ?
       ORDER BY b.code ASC",
      [$book['title'], $book['author'], (int)$book['library_id']]
    );

    out([
      'book' => $book,
      'loan' => $loan,
      'all_copies' => $allCopies
    ]);

  case 'lend':
    if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
    $code = trim($in['code'] ?? '');
    $usernameOrId = trim($in['username'] ?? '');
    if ($code === '' || $usernameOrId === '') fail('Book code සහ Member Username/ID අවශ්‍යයි');

    $book = one('SELECT * FROM books WHERE code = ?', [$code]);
    if (!$book) fail('පොත හමු නොවීය');
    if ($IS_LIB && (int)$book['library_id'] !== (int)$me['library_id']) {
      fail('මෙම පොත ඔබේ පුස්තකාලයට අයත් නැත');
    }

    $activeLoan = one('SELECT id FROM loans WHERE code = ? AND returned IS NULL', [$book['code']]);
    if ($activeLoan) fail('මෙම පොත දැනටමත් ණයට දී ඇත');

    $cleanId = preg_replace('/^M/i', '', $usernameOrId);
    $member = one(
      'SELECT * FROM users WHERE role = "member" AND (username = ? OR id = ?) LIMIT 1',
      [$usernameOrId, (int)$cleanId]
    );
    if (!$member) fail('සාමාජිකයා හමු නොවීය');
    if ((int)$member['library_id'] !== (int)$book['library_id']) {
      fail('සාමාජිකයා ලියාපදිංචි වී ඇත්තේ වෙනත් පුස්තකාලයකටය');
    }

    $heldCount = (int)one('SELECT COUNT(*) AS c FROM loans WHERE user_id = ? AND returned IS NULL', [(int)$member['id']])['c'];
    if ($heldCount >= 2) fail('සාමාජිකයෙකුට එකවර ණයට ගත හැකි උපරිම පොත් ප්‍රමාණය 2කි');

    $now = date('c');
    $due = date('c', strtotime('+14 days'));
    st(
      'INSERT INTO loans(code, user_id, date_out, due, returned, fine_paid) VALUES(?, ?, ?, ?, NULL, 0)',
      [$book['code'], (int)$member['id'], $now, $due]
    );
    out(['ok' => 1, 'due' => $due]);

  case 'return':
    if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
    $code = trim($in['code'] ?? '');
    if ($code === '') fail('Book code අවශ්‍යයි');

    $book = one('SELECT * FROM books WHERE code = ?', [$code]);
    if (!$book) fail('පොත හමු නොවීය');
    if ($IS_LIB && (int)$book['library_id'] !== (int)$me['library_id']) {
      fail('මෙම පොත ඔබේ පුස්තකාලයට අයත් නැත');
    }

    $loan = one('SELECT id FROM loans WHERE code = ? AND returned IS NULL ORDER BY id DESC LIMIT 1', [$book['code']]);
    if (!$loan) fail('මෙම පොත දැනට ණයට දී නැත');

    st('UPDATE loans SET returned = ? WHERE id = ?', [date('c'), (int)$loan['id']]);
    out(['ok' => 1]);

  case 'renew_loan':
    if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
    $loanId = (int)($in['loan_id'] ?? 0);
    if ($loanId <= 0) fail('Invalid loan ID');

    $loan = one(
      "SELECT ln.*, b.library_id
       FROM loans ln
       JOIN books b ON b.code = ln.code
       WHERE ln.id = ? AND ln.returned IS NULL",
      [$loanId]
    );
    if (!$loan) fail('ණය වාර්තාව හමු නොවීය');
    if ($IS_LIB && (int)$loan['library_id'] !== (int)$me['library_id']) {
      fail('මෙම පොත ඔබේ පුස්තකාලයට අයත් නැත');
    }

    $baseTs = strtotime((string)$loan['due']);
    if (!$baseTs || $baseTs < time()) {
      $baseTs = time();
    }
    $newDue = date('c', strtotime('+14 days', $baseTs));
    st('UPDATE loans SET due = ?, fine_paid = 0 WHERE id = ?', [$newDue, $loanId]);
    out(['ok' => 1, 'due' => $newDue]);

  case 'pay_fine':
    if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
    $loanId = (int)($in['loan_id'] ?? 0);
    if ($loanId <= 0) fail('Invalid loan ID');

    $loan = one(
      "SELECT ln.*, b.library_id
       FROM loans ln
       JOIN books b ON b.code = ln.code
       WHERE ln.id = ?",
      [$loanId]
    );
    if (!$loan) fail('ණය වාර්තාව හමු නොවීය');
    if ($IS_LIB && (int)$loan['library_id'] !== (int)$me['library_id']) {
      fail('මෙම පොත ඔබේ පුස්තකාලයට අයත් නැත');
    }

    st('UPDATE loans SET fine_paid = 1, fine_paid_at = ? WHERE id = ?', [date('c'), $loanId]);
    out(['ok' => 1]);

  case 'reserve_book':
    if (!$IS_MEM) fail('සාමාජිකයන්ට පමණි');
    $title = trim($in['title'] ?? '');
    $author = trim($in['author'] ?? '');
    $targetLib = (int)($me['library_id'] ?? 0);
    if ($title === '' || $targetLib <= 0) fail('පොතේ විස්තර අසම්පූර්ණයි');

    $dup = one(
      "SELECT id FROM reservations WHERE library_id = ? AND user_id = ? AND title = ? AND status = 'pending'",
      [$targetLib, (int)$me['id'], $title]
    );
    if ($dup) fail('ඔබ දැනටමත් මෙම පොත වෙන් කර ඇත!');

    st(
      "INSERT INTO reservations(library_id, user_id, title, author, status, created_at) VALUES(?, ?, ?, ?, 'pending', ?)",
      [$targetLib, (int)$me['id'], $title, $author, date('c')]
    );
    out(['ok' => 1]);

  case 'loans':
    if ($IS_MEM) fail('අවසර නැත');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
    $where = $targetLib > 0 ? "AND b.library_id = " . (int)$targetLib : "";

    out(all_(
      "SELECT ln.*, b.title, b.author, b.library_id, l.name AS lib_name,
              u.name, u.username, u.phone, u.email
       FROM loans ln
       JOIN books b ON b.code = ln.code
       LEFT JOIN libraries l ON l.id = b.library_id
       LEFT JOIN users u ON u.id = ln.user_id
       WHERE ln.returned IS NULL $where
       ORDER BY ln.due ASC"
    ));

  case 'members':
    if ($IS_MEM) fail('අවසර නැත');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
    $w = "u.role = 'member'" . ($targetLib > 0 ? " AND u.library_id = " . (int)$targetLib : "");
    out([
      'rows' => all_(
        "SELECT u.*, lib.name AS lib_name,
                (SELECT COUNT(*) FROM loans l WHERE l.user_id = u.id AND l.returned IS NULL) AS held,
                (SELECT COUNT(*) FROM loans l WHERE l.user_id = u.id) AS total
         FROM users u
         LEFT JOIN libraries lib ON lib.id = u.library_id
         WHERE $w
         ORDER BY u.id DESC"
      )
    ]);

  case 'add_member':
    if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : (int)($in['library_id'] ?? ($lib_id > 0 ? $lib_id : 1));
    if ($targetLib <= 0) fail('පුස්තකාලය තෝරා නැත');

    $name = trim($in['name'] ?? '');
    $username = trim($in['username'] ?? '');
    $phone = trim($in['phone'] ?? '');
    $dob = trim($in['dob'] ?? '');
    $address = trim($in['address'] ?? '');
    $email = trim($in['email'] ?? '');
    $password = trim($in['password'] ?? '');

    if ($name === '' || $username === '' || $password === '') {
      fail('නම, Username සහ මුරපදය අත්‍යවශ්‍යයි!');
    }

    $existing = one('SELECT id, library_id FROM users WHERE username = ?', [$username]);
    if ($existing) {
      fail('මෙම Username එක දැනටමත් පුස්තකාලයක ලියාපදිංචි වී ඇත! එක් සාමාජිකයෙකුට ලියාපදිංචි විය හැක්කේ එක් පුස්තකාලයකට පමණි.');
    }

    $hashedPw = password_hash($password, PASSWORD_DEFAULT);
    st(
      'INSERT INTO users(username, pw, role, library_id, name, phone, dob, address, email) VALUES(?, ?, "member", ?, ?, ?, ?, ?, ?)',
      [$username, $hashedPw, $targetLib, $name, $phone, $dob, $address, $email]
    );
    out(['ok' => 1]);

  case 'delete_member':
    if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) fail('Invalid member ID');
    if ($IS_LIB) {
      st('DELETE FROM users WHERE id = ? AND role = "member" AND library_id = ?', [$id, (int)$me['library_id']]);
    } else {
      st('DELETE FROM users WHERE id = ? AND role = "member"', [$id]);
    }
    out(['ok' => 1]);

  case 'librarians':
    if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
    $w = "u.role = 'librarian'" . ($targetLib > 0 ? " AND u.library_id = " . (int)$targetLib : "");
    out(all_(
      "SELECT u.*, l.name AS lib_name
       FROM users u
       LEFT JOIN libraries l ON l.id = u.library_id
       WHERE $w
       ORDER BY u.id DESC"
    ));

  case 'delete_librarian':
    if (!$IS_ADMIN) fail('අවසර නැත');
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) fail('Invalid ID');
    st('DELETE FROM users WHERE id = ? AND role = "librarian"', [$id]);
    out(['ok' => 1]);

  case 'send_otp':
    if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
    $email = trim($in['email'] ?? '');
    if ($email === '') fail('කරුණාකර ඊමේල් ලිපිනයක් ඇතුළත් කරන්න!');
    $otp = rand(100000, 999999);
    $_SESSION['lib_otp'] = (string)$otp;
    out(['ok' => 1, 'otp' => $otp]);

  case 'verify_and_add_librarian':
    if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : (int)($in['library_id'] ?? ($lib_id > 0 ? $lib_id : 1));
    $name = trim($in['name'] ?? '');
    $empNo = trim($in['emp_no'] ?? '');
    $phone = trim($in['phone'] ?? '');
    $email = trim($in['email'] ?? '');
    $password = trim($in['password'] ?? '');

    if ($name === '' || $empNo === '' || $email === '' || $password === '') {
      fail('සියලුම තොරතුරු පුරවන්න!');
    }
    if (one('SELECT 1 FROM users WHERE username = ?', [$empNo])) {
      fail('මෙම සේවක අංකය දැනටමත් පවතී!');
    }

    $hashedPw = password_hash($password, PASSWORD_DEFAULT);
    st(
      'INSERT INTO users(username, pw, role, library_id, name, phone, email) VALUES(?, ?, "librarian", ?, ?, ?, ?)',
      [$empNo, $hashedPw, $targetLib, $name, $phone, $email]
    );
    out(['ok' => 1]);

  case 'add_single_book':
    if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : (int)($in['library_id'] ?? ($lib_id > 0 ? $lib_id : 1));
    if ($targetLib <= 0) $targetLib = 1;

    $code = trim($in['code'] ?? '');
    if ($code === '') {
      $last = one("SELECT code FROM books ORDER BY CHAR_LENGTH(code) DESC, code DESC LIMIT 1");
      $num = $last ? (int)preg_replace('/\D/', '', $last['code']) : 0;
      do {
        $num++;
        $code = 'B' . str_pad((string)$num, 5, '0', STR_PAD_LEFT);
      } while (one('SELECT 1 FROM books WHERE code = ?', [$code]));
    } else {
      if (one('SELECT 1 FROM books WHERE code = ?', [$code])) {
        fail('මෙම පරිග්‍රහණ අංකය / Barcode දැනටමත් පවතී!');
      }
    }

    $title = trim($in['title'] ?? '');
    $author = trim($in['author'] ?? '');
    $receivedDate = trim($in['received_date'] ?? ($in['date'] ?? date('Y-m-d')));
    if ($receivedDate === '') $receivedDate = date('Y-m-d');

    $receivedType = strtolower(trim($in['received_type'] ?? 'purchase'));
    if (!in_array($receivedType, ['purchase', 'donation'], true)) {
      $receivedType = 'purchase';
    }
    $receivedDesc = trim($in['received_desc'] ?? ($in['source'] ?? ''));

    if ($title === '') fail('පොතේ නම අත්‍යවශ්‍යයි!');

    st(
      'INSERT INTO books(code, library_id, title, author, received_type, received_desc, received_date)
       VALUES(?, ?, ?, ?, ?, ?, ?)',
      [$code, $targetLib, $title, $author, $receivedType, $receivedDesc, $receivedDate]
    );
    out(['ok' => 1, 'code' => $code]);

  case 'import_books':
    if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : (int)($in['library_id'] ?? ($lib_id > 0 ? $lib_id : 1));
    if ($targetLib <= 0) $targetLib = 1;
    $books = $in['books'] ?? [];
    if (!is_array($books) || !count($books)) fail('පොත් ලැයිස්තුව හිස්ය');

    $todayStr = date('Y-m-d');
    $created = [];
    foreach ($books as $b) {
      $title = trim($b['title'] ?? '');
      $author = trim($b['author'] ?? '');
      $copies = max(1, (int)($b['copies'] ?? 1));
      $recType = strtolower(trim($b['received_type'] ?? 'purchase'));
      if (!in_array($recType, ['purchase', 'donation'], true)) $recType = 'purchase';
      $recDesc = trim($b['received_desc'] ?? '');
      $recDate = trim($b['received_date'] ?? $todayStr);
      if ($recDate === '') $recDate = $todayStr;
      if ($title === '') continue;

      for ($i = 0; $i < $copies; $i++) {
        do {
          $code = 'B' . str_pad((string)random_int(10000, 999999), 6, '0', STR_PAD_LEFT);
        } while (one('SELECT 1 FROM books WHERE code = ?', [$code]));

        st(
          'INSERT INTO books(code, library_id, title, author, received_type, received_desc, received_date)
           VALUES(?, ?, ?, ?, ?, ?, ?)',
          [$code, $targetLib, $title, $author, $recType, $recDesc, $recDate]
        );
        $created[] = [
          'code' => $code,
          'title' => $title,
          'author' => $author,
          'library_id' => $targetLib
        ];
      }
    }
    out(['ok' => 1, 'created' => $created]);

  case 'audit_check_book':
    if (!$IS_LIB && !$IS_ADMIN) fail('අවසර නැත');
    $code = trim($in['code'] ?? '');
    if ($code === '') fail('Barcode අවශ්‍යයි');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : ($lib_id > 0 ? $lib_id : 0);

    $sql = "SELECT * FROM books WHERE code = ?";
    $params = [$code];
    if ($targetLib > 0) {
      $sql .= " AND library_id = ?";
      $params[] = $targetLib;
    }
    $book = one($sql . " LIMIT 1", $params);
    if (!$book) fail('මෙම පොත අදාළ පුස්තකාලයට අයත් නැත!');

    $bookLibId = (int)$book['library_id'];
    if (!one('SELECT 1 FROM audit_logs WHERE library_id = ? AND book_code = ?', [$bookLibId, $book['code']])) {
      st(
        'INSERT INTO audit_logs(library_id, book_code, checked_by, checked_at) VALUES(?, ?, ?, ?)',
        [$bookLibId, $book['code'], (int)$me['id'], date('c')]
      );
    }
    out(['ok' => 1, 'book' => $book]);

  case 'download_audit_report':
    if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
    $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;
    $status = $_GET['status'] ?? 'checked';
    $libFilter = $targetLib > 0 ? "b.library_id = " . (int)$targetLib : "1=1";

    if ($status === 'unchecked') {
      $sql = "SELECT b.code, b.title, b.author, l.name AS lib_name
              FROM books b
              LEFT JOIN libraries l ON l.id = b.library_id
              WHERE $libFilter
                AND b.code NOT IN (SELECT book_code FROM audit_logs WHERE library_id = b.library_id)
              ORDER BY b.code ASC";
    } else {
      $sql = "SELECT b.code, b.title, b.author, l.name AS lib_name, a.checked_at, u.name AS checked_by_name
              FROM audit_logs a
              JOIN books b ON b.code = a.book_code
              LEFT JOIN libraries l ON l.id = b.library_id
              LEFT JOIN users u ON u.id = a.checked_by
              WHERE $libFilter
              ORDER BY a.id DESC";
    }
    out(all_($sql));

  case 'download_report':
    if ($IS_MEM) fail('අවසර නැත');
    $reportType = $_GET['report_type'] ?? 'books_period';
    $start = trim($_GET['start'] ?? date('Y-m-01'));
    $end   = trim($_GET['end'] ?? date('Y-m-t'));
    $targetLib = $IS_LIB ? (int)$me['library_id'] : $lib_id;

    if ($reportType === 'donated_books_period') {
      $conds = ["LOWER(COALESCE(b.received_type, '')) = 'donation'"];
      $params = [];

      if ($targetLib > 0) {
        $conds[] = "b.library_id = ?";
        $params[] = $targetLib;
      }
      if ($start !== '') {
        $conds[] = "LEFT(COALESCE(NULLIF(b.received_date, ''), CURDATE()), 10) >= ?";
        $params[] = substr($start, 0, 10);
      }
      if ($end !== '') {
        $conds[] = "LEFT(COALESCE(NULLIF(b.received_date, ''), CURDATE()), 10) <= ?";
        $params[] = substr($end, 0, 10);
      }

      $whereSql = 'WHERE ' . implode(' AND ', $conds);
      out(all_(
        "SELECT b.code,
                b.title,
                b.author,
                'පරිත්‍යාග (Donation)' AS received_type,
                b.received_desc AS donation_description,
                COALESCE(NULLIF(b.received_date, ''), '') AS received_date,
                l.name AS lib_name
         FROM books b
         LEFT JOIN libraries l ON l.id = b.library_id
         $whereSql
         ORDER BY b.received_date DESC, b.code ASC",
        $params
      ));
    } elseif ($reportType === 'members_age') {
      $w = "WHERE u.role = 'member'" . ($targetLib > 0 ? " AND u.library_id = " . (int)$targetLib : "");
      out(all_(
        "SELECT u.id, u.name, u.username, u.phone, u.dob, u.nic, l.name AS lib_name
         FROM users u
         LEFT JOIN libraries l ON l.id = u.library_id
         $w
         ORDER BY u.name ASC"
      ));
    } elseif ($reportType === 'overdue_loans') {
      $w = "WHERE ln.returned IS NULL AND DATE(ln.due) < CURDATE()" . ($targetLib > 0 ? " AND b.library_id = " . (int)$targetLib : "");
      out(all_(
        "SELECT ln.code, b.title, b.author, l.name AS lib_name, u.name AS member_name, u.phone, ln.due
         FROM loans ln
         JOIN books b ON b.code = ln.code
         LEFT JOIN libraries l ON l.id = b.library_id
         LEFT JOIN users u ON u.id = ln.user_id
         $w
         ORDER BY ln.due ASC"
      ));
    } else {
      $w = $targetLib > 0 ? "WHERE b.library_id = " . (int)$targetLib : "";
      out(all_(
        "SELECT b.code, b.title, b.author, b.received_type, b.received_desc, b.received_date, l.name AS lib_name
         FROM books b
         LEFT JOIN libraries l ON l.id = b.library_id
         $w
         ORDER BY b.code ASC"
      ));
    }

  case 'get_news':
    if ($IS_MEM) {
      out(all_("SELECT * FROM news WHERE target_audience = 'all' ORDER BY id DESC"));
    }
    out(all_("SELECT * FROM news ORDER BY id DESC"));

  case 'add_news':
    if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
    $title = trim($in['title'] ?? '');
    $content = trim($in['content'] ?? '');
    $audience = ($in['target_audience'] ?? 'all') === 'librarian' ? 'librarian' : 'all';
    if ($IS_LIB) $audience = 'librarian';
    if ($title === '' || $content === '') fail('ශීර්ෂය සහ අන්තර්ගතය අවශ්‍යයි');
    st(
      'INSERT INTO news(title, content, target_audience, created_at) VALUES(?, ?, ?, ?)',
      [$title, $content, $audience, date('c')]
    );
    out(['ok' => 1]);

  case 'edit_news':
    if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
    $id = (int)($in['id'] ?? 0);
    $title = trim($in['title'] ?? '');
    $content = trim($in['content'] ?? '');
    $audience = ($in['target_audience'] ?? 'all') === 'librarian' ? 'librarian' : 'all';
    if ($id <= 0 || $title === '' || $content === '') fail('දත්ත අසම්පූර්ණයි');
    st('UPDATE news SET title = ?, content = ?, target_audience = ? WHERE id = ?', [$title, $content, $audience, $id]);
    out(['ok' => 1]);

  case 'delete_news':
    if (!$IS_ADMIN && !$IS_LIB) fail('අවසර නැත');
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) fail('Invalid ID');
    st('DELETE FROM news WHERE id = ?', [$id]);
    out(['ok' => 1]);

  case 'get_rules':
    if (!$IS_ADMIN && !$IS_LIB && !$IS_MEM) fail('අවසර නැත');
    out(all_("SELECT * FROM rules ORDER BY id ASC"));

  case 'add_rule':
    if (!$IS_ADMIN) fail('අවසර නැත');
    $title = trim((string)($in['title'] ?? ''));
    $content = trim((string)($in['content'] ?? ''));
    if ($title === '' || $content === '') fail('නීතියේ ශීර්ෂය සහ විස්තරය අත්‍යවශ්‍යයි!');
    $now = date('c');
    $uid = (int)($me['id'] ?? 0);
    st('INSERT INTO rules(title, content, created_by, created_at) VALUES(?, ?, ?, ?)', [$title, $content, $uid, $now]);
    out(['ok' => 1]);

  case 'edit_rule':
    if (!$IS_ADMIN) fail('අවසර නැත');
    $id = (int)($in['id'] ?? 0);
    $title = trim((string)($in['title'] ?? ''));
    $content = trim((string)($in['content'] ?? ''));
    if ($id <= 0 || $title === '' || $content === '') fail('දත්ත සම්පූර්ණ නැත!');
    st('UPDATE rules SET title = ?, content = ?, updated_at = ? WHERE id = ?', [$title, $content, date('c'), $id]);
    out(['ok' => 1]);

  case 'delete_rule':
    if (!$IS_ADMIN) fail('අවසර නැත');
    $id = (int)($in['id'] ?? 0);
    if ($id <= 0) fail('Invalid ID');
    st('DELETE FROM rules WHERE id = ?', [$id]);
    out(['ok' => 1]);

  default:
    fail('Unknown action');
}
?>