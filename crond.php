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
    
    if (check_duplicate() == false) {
        return;
    }
    
}

function check_duplicate()
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
    
    $pdo = get_pdo();
    
    // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    $sql_update = <<< __HEREDOC__
UPDATE m_server
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
        $pdo = null;
        error_log($log_prefix . 'ROLLBACK');
        return false;
    }
    
    $pdo->commit();
    $pdo = null;
    error_log($log_prefix . 'COMMIT');
    return true;
}

function get_pdo()
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . ' ' . $_ENV['BUILD_DATETIME'] . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $dsn = "mysql:host={$_ENV['DB_SERVER']};dbname={$_ENV['DB_NAME']}";
    $options = array(
      PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    );
    return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);
}
