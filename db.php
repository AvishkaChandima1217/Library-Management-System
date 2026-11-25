<?php
require_once __DIR__ . '/config.php';

function db() {
  static $p;
  if (!$p) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $p = new PDO($dsn, DB_USER, DB_PASS);
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $p->exec("
      CREATE TABLE IF NOT EXISTS libraries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        pw VARCHAR(255),
        role VARCHAR(50) DEFAULT 'member',
        library_id INT DEFAULT 0,
        name VARCHAR(255),
        phone VARCHAR(50) DEFAULT '',
        nic VARCHAR(50) DEFAULT '',
        dob VARCHAR(50) DEFAULT '',
        address TEXT,
        email VARCHAR(255) DEFAULT ''
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      CREATE TABLE IF NOT EXISTS books (
        code VARCHAR(100) PRIMARY KEY,
        library_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) DEFAULT '',
        received_type VARCHAR(50) DEFAULT 'purchase',
        received_desc TEXT,
        received_date VARCHAR(50) DEFAULT ''
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      CREATE TABLE IF NOT EXISTS loans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(100) NOT NULL,
        user_id INT,
        date_out VARCHAR(50),
        due VARCHAR(50),
        returned VARCHAR(50),
        fine_paid TINYINT(1) DEFAULT 0,
        fine_paid_at VARCHAR(50) NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        library_id INT NOT NULL,
        book_code VARCHAR(100) NOT NULL,
        checked_by INT,
        checked_at VARCHAR(50)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        target_audience VARCHAR(50) DEFAULT 'all',
        created_by INT NULL,
        author_role VARCHAR(50) DEFAULT 'admin',
        admin_notified TINYINT(1) DEFAULT 0,
        created_at VARCHAR(50)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

      CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        library_id INT NOT NULL,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) DEFAULT '',
        status VARCHAR(50) DEFAULT 'pending',
        created_at VARCHAR(50)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $alterStatements = [
      "ALTER TABLE books ADD COLUMN received_type VARCHAR(50) DEFAULT 'purchase'",
      "ALTER TABLE books ADD COLUMN received_desc TEXT",
      "ALTER TABLE books ADD COLUMN received_date VARCHAR(50) DEFAULT ''",
      "ALTER TABLE loans ADD COLUMN fine_paid TINYINT(1) DEFAULT 0",
      "ALTER TABLE loans ADD COLUMN fine_paid_at VARCHAR(50) NULL"
    ];
    foreach ($alterStatements as $sqlAlter) {
      try { $p->exec($sqlAlter); } catch (Throwable $ignore) {}
    }

    if (!(int)$p->query('SELECT COUNT(*) FROM libraries')->fetchColumn()) {
      $defaultLibs = [
        1 => 'Mainnoluwa',
        2 => 'Mahena',
        3 => 'Dedigama',
        4 => 'Algama',
        5 => 'පුස්තකාලය 5'
      ];
      $insLib = $p->prepare('INSERT INTO libraries(id, name) VALUES(?, ?)');
      foreach ($defaultLibs as $id => $name) {
        $insLib->execute([$id, $name]);
      }
    }

    if (!$p->query('SELECT 1 FROM users LIMIT 1')->fetch()) {
      $p->prepare('INSERT INTO users(username, pw, role, library_id, name) VALUES(?, ?, ?, ?, ?)')
        ->execute(['admin', password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 'admin', 0, 'ප්‍රධාන පරිපාලක']);
    }
  }
  return $p;
}

function st($sql, $p = []) {
  $s = db()->prepare($sql);
  $s->execute($p);
  return $s;
}

function one($sql, $p = []) {
  return st($sql, $p)->fetch() ?: null;
}

function all_($sql, $p = []) {
  return st($sql, $p)->fetchAll();
}
?>