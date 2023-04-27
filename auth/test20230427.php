<?php

include('./log.php');

$log = new Log();

$dsn = "mysql:host={$_ENV['DB_SERVER']};dbname={$_ENV['DB_NAME']}";
$options = [
    PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
];
$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);

$res = $pdo->query("SHOW CREATE TABLE `m_server`")->fetchColumn(1);

$log->debug($res);

$pdo = null;

$res = preg_replace('/ENGINE=InnoDB.+/', '', $res);
$res = preg_replace('/COLLATE \w+/', '', $res);
$res = str_replace('DEFAULT NULL', '', $res);
$res = str_replace('AUTO_INCREMENT', '', $res);
$res = str_replace(' int ', ' INTEGER ', $res);
$res = preg_replace('/varchar\(\d+\)/', 'TEXT', $res);
$res = preg_replace('/tinyint\(\d+\)/', 'INTEGER', $res);
$res = str_replace('`', '', $res);

$log->debug($res);
