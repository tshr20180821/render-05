<?php

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("START ${requesturi} " . date('Y/m/d H:i:s') . ' ' . $_ENV['DEPLOY_DATETIME']);

try {
    crond();
    header("Content-Type: text/plain");
    echo $_ENV['DEPLOY_DATETIME'];
} catch (Exception $ex) {
    error_log($ex->getMessage());
}

error_log("FINISH " . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function crond()
{
    $log_prefix = '[' . __METHOD__ . ' ' . $_ENV['DEPLOY_DATETIME'] . '] ';
    error_log($log_prefix . 'BEGIN');
    
    if ($_SERVER['HTTP_X_DEPLOY_DATETIME'] != $_ENV['DEPLOY_DATETIME']) {
        error_log($log_prefix . 'VERSION NOT MATCH ' . $_SERVER['HTTP_X_DEPLOY_DATETIME']);
        return;
    }
    
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
    
    get_contents_multi($urls, $multi_options);
}

function check_duplicate()
{
    $log_prefix = '[' . __METHOD__ . ' ' . $_ENV['DEPLOY_DATETIME'] . '] ';
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
    $log_prefix = '[' . __METHOD__ . ' ' . $_ENV['DEPLOY_DATETIME'] . '] ';
    error_log($log_prefix . 'BEGIN');
    
    $dsn = "mysql:host={$_ENV['DB_SERVER']};dbname={$_ENV['DB_NAME']}";
    $options = array(
      PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    );
    return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);
}

function get_contents_multi($urls_, $multi_options_ = null)
{
    $log_prefix = '[' . __METHOD__ . ' ' . $_ENV['DEPLOY_DATETIME'] . '] ';
    error_log($log_prefix . 'BEGIN');

    $time_start = microtime(true);

    if (is_null($urls_)) {
        $urls_ = [];
    }
    
    $mh = curl_multi_init();
    if (is_null($multi_options_) === false) {
        foreach ($multi_options_ as $key => $value) {
            $rc = curl_multi_setopt($mh, $key, $value);
            if ($rc === false) {
                error_log($log_prefix . "curl_multi_setopt : ${key} ${value}");
            }
        }
    }

    foreach ($urls_ as $url => $options_add) {
        error_log($log_prefix . 'CURL MULTI Add $url : ' . $url);
        $ch = curl_init();
        $options = [CURLOPT_URL => $url,
                    // CURLOPT_USERAGENT => getenv('USER_AGENT'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_PATH_AS_IS => true,
                    CURLOPT_TCP_FASTOPEN => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
                    // CURLOPT_TIMEOUT => 25,
                    CURLOPT_TIMEOUT => 20,
                   ];

        // if (is_null($options_add) === false && array_key_exists(CURLOPT_USERAGENT, $options_add)) {
        //     unset($options[CURLOPT_USERAGENT]);
        // }
        foreach ($options as $key => $value) {
            $rc = curl_setopt($ch, $key, $value);
            if ($rc == false) {
                error_log($log_prefix . "curl_setopt : ${key} ${value}");
            }
        }
        if (is_null($options_add) === false) {
            foreach ($options_add as $key => $value) {
                $rc = curl_setopt($ch, $key, $value);
                if ($rc == false) {
                    error_log($log_prefix . "curl_setopt : ${key} ${value}");
                }
            }
        }
        curl_multi_add_handle($mh, $ch);
        $list_ch[$url] = $ch;
    }

    $active = null;
    $rc = curl_multi_exec($mh, $active);

    $count = 0;
    while ($active && $rc == CURLM_OK) {
        $count++;
        if (curl_multi_select($mh) == -1) {
            usleep(1);
        }
        $rc = curl_multi_exec($mh, $active);
    }
    error_log($log_prefix . 'loop count : ' . $count);

    $results = [];
    foreach (array_keys($urls_) as $url) {
        $ch = $list_ch[$url];
        $res = curl_getinfo($ch);
        $http_code = (string)$res['http_code'];
        error_log($log_prefix . "CURL Result ${http_code} : ${url}");
        if ($http_code[0] == '2') {
            $result = curl_multi_getcontent($ch);
            $results[$url] = $result;
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        // if (apcu_exists('HTTP_STATUS') === true) {
        //     $dic_http_status = apcu_fetch('HTTP_STATUS');
        // } else {
        //     $dic_http_status = [];
        // }
        // if (array_key_exists($http_code, $dic_http_status) === true) {
        //     $dic_http_status[$http_code]++;
        // } else {
        //     $dic_http_status[$http_code] = 1;
        // }
        // apcu_store('HTTP_STATUS', $dic_http_status);
    }

    curl_multi_close($mh);

    $total_time = substr((microtime(true) - $time_start), 0, 6) . 'sec';

    // error_log("${log_prefix}urls :");
    // $this->logging_object(array_keys($results), $log_prefix);
    error_log("${log_prefix}Total Time : [${total_time}]");
    error_log("${log_prefix}memory_get_usage : " . number_format(memory_get_usage()) . 'byte');

    return $results;
}
