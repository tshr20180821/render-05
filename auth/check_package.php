<?php

include('./log.php');

$log = new Log();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START ${requesturi}");

check_package($log);

$log->info("FINISH " . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function check_package($log_)
{
    $log_->info('BEGIN');
    
    $url = 'https://packages.debian.org/en/bullseye/apache2';
    
    $res = file_get_contents($url);
    
    if (preg_match('/<meta name="Keywords" content="(.+?)">/', $res, $matches) == 1) {
        $log_->info(print_r($matches, true));
    } else {
        $log_->info("UNMATCH");
    }
}
