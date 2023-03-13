<?php

include('./log.php');

$log = new Log();

$log->trace('message');
$log->debug('message');
$log->info('message');
$log->warn('message');
$log->error('message');
$log->fatal('message');

echo 'test10';
