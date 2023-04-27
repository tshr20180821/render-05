<?php

include('./log.php');

$log = new Log();

$dsn = "mysql:host={$_ENV['DB_SERVER']};dbname={$_ENV['DB_NAME']}";
$options = [
    PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
];
$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);

$log->debug($pdo->query("SHOW CREATE TABLE `m_cron`")->fetchColumn(1));

$pdo = null;
