<?php

include('/usr/src/app/log.php');

$log = new Log();

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START {$requesturi}");

exec('cd /usr/src/app && java -classpath .:sqlite-jdbc-3.43.2.0.jar:slf4j-api-2.0.9.jar:slf4j-nop-2.0.9.jar -Duser.timezone=Asia/Tokyo -Dfile.encoding=UTF-8 -verbose:gc LogOperationMain &');

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');
