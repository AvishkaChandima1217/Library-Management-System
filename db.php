<?php
require __DIR__ . '/config.php';
function db() {
  static $p;
  if (!$p) {$p = new PDO('sqlite:' . DB_FILE);
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);$p->exec("
      CREATE TABLE IF NOT EXISTS libraries(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE COLLATE NOCASE, pw TEXT, role TEXT DEFAULT 'member', library_id INTEGER, name TEXT, phone TEXT DEFAULT '', nic TEXT DEFAULT '', dob TEXT DEFAULT '');
      CREATE TABLE IF NOT EXISTS books(code TEXT PRIMARY KEY COLLATE NOCASE, library_id INTEGER NOT NULL, title TEXT NOT NULL, author TEXT DEFAULT '');
      CREATE TABLE IF NOT EXISTS loans(id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL COLLATE NOCASE, user_id INTEGER, date_out TEXT, due TEXT, returned TEXT);
      CREATE TABLE IF NOT EXISTS audit_logs(id INTEGER PRIMARY KEY AUTOINCREMENT, library_id INTEGER NOT NULL, book_code TEXT NOT NULL, checked_by INTEGER, checked_at TEXT);
      CREATE TABLE IF NOT EXISTS news(id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, content TEXT NOT NULL, target_audience TEXT DEFAULT 'all', created_at TEXT);
      
      CREATE INDEX IF NOT EXISTS i_b_title ON books(title);
      CREATE INDEX IF NOT EXISTS i_l_code ON loans(code);
      CREATE INDEX IF NOT EXISTS i_l_user ON loans(user_id);
      CREATE INDEX IF NOT EXISTS i_audit_code ON audit_logs(book_code);
    ");

    if (!(int)$p->query('SELECT COUNT(*) FROM libraries')->fetchColumn()) {
      for ($i = 1; $i <= 5; $i++) {$p->prepare('INSERT INTO libraries(id, name) VALUES(?, ?)')->execute([$i, "පුස්තකාලය $i"]);
      }
    }

    if (!$p->query('SELECT 1 FROM users LIMIT 1')->fetch()) {$p->prepare('INSERT INTO users(username,pw,role,library_id,name) VALUES(?,?,?,?,?)')
        ->execute(['admin', password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 'admin', 0, 'ප්‍රධාන පරිපාලක']);
    }
  }
  return $p;
}

function st($sql, $p = []) {$s = db()->prepare($sql);$s->execute($p); return$s; }
function one($sql,$p = []) { return st($sql,$p)->fetch() ?: null; }
function all_($sql,$p = []) { return st($sql,$p)->fetchAll(); }
?>