<?php

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("${pid} START ${requesturi} " . date('Y/m/d H:i:s') . ' ' . $_ENV['BUILD_DATETIME']);

crond();

error_log("${pid} FINISH " . substr((microtime(true) - $time_start), 0, 6) . 's');

exit();

function crond()
{
    $log_prefix = getmypid() . ' [' . __METHOD__ . ' ' . $_ENV['BUILD_DATETIME'] . '] ';
    error_log($log_prefix . 'BEGIN');
    
    if (check_duplicate() == false) {
        return;
    }
    
    $sql_select = <<< __HEREDOC__
SELECT M1.schedule
      ,M1.uri
      ,M1.method
      ,M1.authentication
      ,M1.headers
      ,M1.post_data
      ,M1.memo
  FROM m_cron M1
 WHERE M1.enable = TRUE
 ORDER BY M1.uri
__HEREDOC__;
    
    $timestamp = strtotime('+9 hours');
    
    error_log($log_prefix . 'time : ' . date('Y/m/d H:i'));
    
    $format = [];
    $format[0] = 'i';
    $format[1] = 'H';
    $format[2] = 'd';
    $format[3] = 'm';
    $format[4] = 'D';
    
    $urls = [];
    
    $pdo = get_pdo();
    
    $statement = $pdo->prepare($sql_select);
    $rc = $statement->execute();
    $results = $statement->fetchAll();
    foreach ($results as $row) {
        error_log($log_prefix . $row['schedule'] . ' ' . $row['uri']);
        $schedule = explode(' ', $row['schedule']);
        
        if (count($schedule) != 5) {
            continue;
        }
        
        for ($i = 0; $i < 5; $i++) {
            $is_execute = false;
            $tmp1 = explode(',', $schedule[$i]);
            for ($j = 0; $j < count($tmp1); $j++) {
                if ($tmp1[$j] === '*') {
                    $is_execute = true;
                    break;
                }
                
                if (str_pad($tmp1[$j], 2, '0', STR_PAD_LEFT) === date($format[$i], $timestamp)) {
                    $is_execute = true;
                    break;
                }
                
                if ($i === 4) {
                    continue;
                }
                
                // m-n
                $tmp2 = explode('-', $tmp1[$j]);
                if (count($tmp2) === 2) {
                    $tmp3 = (int)date($format[$i], $timestamp);
                    if ((int)$tmp2[0] <= $tmp3 && $tmp3 <= (int)$tmp2[1]) {
                        $is_execute = true;
                        break;
                    }
                }
                
                // */n
                $tmp2 = explode('*/', $tmp1[$j]);
                if (count($tmp2) === 2) {
                    if ((int)date($format[$i], $timestamp) % (int)$tmp2[1] === 0) {
                        $is_execute = true;
                        break;
                    }
                }
            }
            if ($is_execute === false) {
                break;
            }
        }
        if ($is_execute === false) {
            continue;
        }
        
        // execute
        $options = [CURLOPT_TIMEOUT => 15];

        if (strlen($row['headers']) > 0) {
            $options += [CURLOPT_HTTPHEADER => unserialize(base64_decode($row['headers']))];
        }
        if (strlen($row['authentication']) > 0) {
            $options += [CURLOPT_USERPWD => $row['authentication']];
        }
        
        if ($row['method'] == 'POST') {
            if (strlen($row['post_data']) > 0) {
                $options += [CURLOPT_POST => true,
                             CURLOPT_POSTFIELDS => unserialize(base64_decode($row['post_data'])),
                            ];
            }
        }
        $urls[$row['uri']] = $options;
    }
    
    $pdo = null;
    
    $multi_options = [
        CURLMOPT_PIPELINING => CURLPIPE_MULTIPLEX,
        CURLMOPT_MAX_HOST_CONNECTIONS => 20,
        CURLMOPT_MAXCONNECTS => 20,
    ];
    
    error_log(print_r($urls, true));
    
    // $mu_->get_contents_multi($urls, null, $multi_options);
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
        return false;
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
