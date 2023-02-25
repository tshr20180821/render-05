<?php

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s') . ' ' . gethostname());

$rc = crond();

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

exit();

function crond()
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $sem_id = ftok(__FILE__, gethostname()[-1]);
    error_log($log_prefix . 'sem_id : ' . $sem_id);
    // $sem = sem_get($sem_id);
    // sem_acquire($sem);
    
    clearstatcache();
    $lock_file = '/tmp/crond_php_' . date('i');
    if (file_exists($lock_file) == true && (time() - filemtime($lock_file)) < 300) {
        error_log($log_prefix . 'EXISTS LOCK FILE');
        // sem_release($sem);
        return;
    }
    touch($lock_file);
    
    $dsn = "mysql:host={$_ENV['DB_SERVER']};dbname={$_ENV['DB_NAME']}";
    $options = array(
      PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    );
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);

    $sql_select = <<< __HEREDOC__
SELECT M1.server_id
  FROM m_server M1
 WHERE M1.server_id = 1
   AND M1.server_name = :b_server_name
__HEREDOC__;
    $statement_select = $pdo->prepare($sql_select);
    
    $rc = $statement_select->execute([':b_server_name' => gethostname(),]);
    $results = $statement_select->fetchAll();
    $count = count($results);
    $pdo = null;
    
    if ($count != 1) {
        error_log($log_prefix . 'THROUGH');
        touch('/tmp/NODE_STOP_FILE');
        // sem_release($sem);
        return;
    }
    error_log($log_prefix . 'HIT ' . gethostname());
    // sem_release($sem);
}
