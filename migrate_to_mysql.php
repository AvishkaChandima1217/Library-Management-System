<?php
require __DIR__ . '/config.php';

$sqliteFile = __DIR__ . '/library2.sqlite';
if (!file_exists($sqliteFile)) {
  exit("library2.sqlite file not found.\n");
}

$sqlite = new PDO('sqlite:' . $sqliteFile, null, null, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$mysql = new PDO(
  'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
  DB_USER,
  DB_PASS,
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]
);

// 1. Libraries
$stmtLib = $mysql->prepare('INSERT IGNORE INTO libraries (id, name) VALUES (?, ?)');
foreach ($sqlite->query('SELECT id, name FROM libraries') as $row) {
  $stmtLib->execute([$row['id'], $row['name']]);
}

// 2. Users
$stmtUser = $mysql->prepare('INSERT IGNORE INTO users (id, username, pw, role, library_id, name, phone, nic, dob, address, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($sqlite->query('SELECT * FROM users') as $row) {
  $stmtUser->execute([
    $row['id'],
    $row['username'],
    $row['pw'],
    $row['role'] ?? 'member',
    $row['library_id'] ?? 0,
    $row['name'] ?? '',
    $row['phone'] ?? '',
    $row['nic'] ?? '',
    $row['dob'] ?? '',
    $row['address'] ?? '',
    $row['email'] ?? '',
  ]);
}

// 3. Books
$stmtBook = $mysql->prepare('INSERT IGNORE INTO books (code, library_id, title, author) VALUES (?, ?, ?, ?)');
foreach ($sqlite->query('SELECT code, library_id, title, author FROM books') as $row) {
  $stmtBook->execute([$row['code'], $row['library_id'], $row['title'], $row['author'] ?? '']);
}

// 4. Loans
$stmtLoan = $mysql->prepare('INSERT IGNORE INTO loans (id, code, user_id, date_out, due, returned) VALUES (?, ?, ?, ?, ?, ?)');
foreach ($sqlite->query('SELECT id, code, user_id, date_out, due, returned FROM loans') as $row) {
  $stmtLoan->execute([$row['id'], $row['code'], $row['user_id'], $row['date_out'], $row['due'], $row['returned']]);
}

// 5. Audit Logs
$stmtAudit = $mysql->prepare('INSERT IGNORE INTO audit_logs (id, library_id, book_code, checked_by, checked_at) VALUES (?, ?, ?, ?, ?)');
foreach ($sqlite->query('SELECT id, library_id, book_code, checked_by, checked_at FROM audit_logs') as $row) {
  $stmtAudit->execute([$row['id'], $row['library_id'], $row['book_code'], $row['checked_by'], $row['checked_at']]);
}

// 6. News
$stmtNews = $mysql->prepare('INSERT IGNORE INTO news (id, title, content, target_audience, created_by, author_role, admin_notified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($sqlite->query('SELECT * FROM news') as $row) {
  $stmtNews->execute([
    $row['id'],
    $row['title'],
    $row['content'],
    $row['target_audience'] ?? 'all',
    $row['created_by'] ?? null,
    $row['author_role'] ?? 'admin',
    $row['admin_notified'] ?? 0,
    $row['created_at'] ?? date('c'),
  ]);
}

echo "Migration completed successfully!\n";