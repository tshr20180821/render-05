<?php

include('./log.php');

$log = new Log();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

update_sqlite($log);

$log->info("FINISH " . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function update_sqlite($log_)
{
    $log_->info('BEGIN');
    
    $pdo_sqlite = new PDO('sqlite:/tmp/m_cron.db');

    $sql_delete = <<< __HEREDOC__
DELETE FROM m_cron
__HEREDOC__;

    $rc = $pdo_sqlite->exec($sql_delete);
    $log_->info('m_cron delete result : ' . $rc);

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
        $log_->info('insert result : ' . $statement_insert->rowCount() . ' ' . $row['schedule'] . ' ' . $row['uri']);
    }

    $pdo = null;
    $pdo_sqlite = null;
}
