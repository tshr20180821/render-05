<?php

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s') . ' ' . $_ENV['BUILD_DATETIME']);

$rc = crond();

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

exit();

function crond()
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . ' ' . $_ENV['BUILD_DATETIME'] . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $time = time();
    
    clearstatcache();
    $lock_file = '/tmp/crond_php_' . date('i', $time);
    if (file_exists($lock_file) == true && ($time - filemtime($lock_file)) < 300) {
        error_log($log_prefix . 'EXISTS LOCK FILE');
        return;
    }
    touch($lock_file);
    
    $dsn = "mysql:host={$_ENV['DB_SERVER']};dbname={$_ENV['DB_NAME']}";
    $options = array(
      PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    );
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);
    
    // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    $sql_update = <<< __HEREDOC__
UPDATE m_server2
   SET update_time = NOW()
 WHERE server_name = :b_server_name
   AND processed_minute_one_digit = :b_processed_minute_one_digit
   AND update_time < NOW() - INTERVAL 5 MINUTE
__HEREDOC__;
    
    $statement_update = $pdo->prepare($sql_update);
    
    $statement_update->execute([
        ':b_server_name' => $_ENV['RENDER_EXTERNAL_HOSTNAME'],
        ':b_processed_minute_one_digit' => (int)date('i', $time) % 10,
    ]);
    
    if ($statement_update->rowCount() != 1) {
        $pdo->rollBack();
        error_log($log_prefix . 'ROLLBACK');
        return;
    }
    
    $pdo->commit();
    error_log($log_prefix . 'COMMIT');
    
}
