<?php

$pid = getmypid();
error_log($pid . ' START ' . __FILE__ . ' ' . $_ENV['DEPLOY_DATETIME']);

$pdo_sqlite = new PDO('sqlite:/usr/src/app/m_cron.db');

$sql_create = <<< __HEREDOC__
CREATE TABLE m_cron (
 schedule TEXT,
 uri TEXT,
 method TEXT,
 authentication TEXT,
 headers TEXT,
 post_data TEXT
)
__HEREDOC__;

$rc = $pdo_sqlite->exec($sql_create);
error_log($pid . ' m_cron create table result : ' . $rc);

$sql_insert = <<< __HEREDOC__
INSERT INTO m_cron VALUES(:b_schedule, :b_uri, :b_method, :b_authentication, :b_headers, :b_post_data)
__HEREDOC__;

$statement_insert = $pdo_sqlite->prepare($sql_insert);

$dsn = "mysql:host={$_ENV['DB_SERVER']};dbname={$_ENV['DB_NAME']}";
$options = array(
    PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
);
$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);

$sql_select = <<< __HEREDOC__
SELECT M1.schedule
      ,M1.uri
      ,M1.method
      ,M1.authentication
      ,M1.headers
      ,M1.post_data
  FROM m_cron M1
 WHERE M1.enable = TRUE
 ORDER BY M1.uri
__HEREDOC__;

$statement_select = $pdo->prepare($sql_select);
$rc = $statement_select->execute();
$results = $statement_select->fetchAll();

foreach ($results as $row) {
    $statement_insert->execute([
        ':b_schedule' => $row['schedule'],
        ':b_uri' => $row['uri'],
        ':b_method' => $row['method'],
        ':b_authentication' => $row['authentication'],
        ':b_headers' => $row['headers'],
        ':b_post_data' => $row['post_data'],
    ]);
    error_log($pid . ' insert result : ' . $statement_insert->rowCount() . ' ' . $row['schedule'] . ' ' . $row['uri']);
}

$pdo = null;
$pdo_sqlite = null;

error_log($pid . ' FINISH ' . __FILE__);
