<?php

include('/usr/src/app/log.php');

$log = new Log();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START {$requesturi}");

try {
    crond();
    header("Content-Type: text/plain");
    echo $_ENV['DEPLOY_DATETIME'];
} catch (Exception $ex) {
    $log->warn($ex->getMessage());
}

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function crond()
{
    global $log;
    
    $log->info('BEGIN');
}
