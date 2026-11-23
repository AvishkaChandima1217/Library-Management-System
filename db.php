<?php
require __DIR__ . '/config.php';

function db() {
  static $p;
  if (!$p) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $p = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
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