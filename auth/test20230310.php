<?php

include('./log.php');

$log = new Log();

$log->trace('message');
$log->debug('message');
$log->info('message');
$log->warn('message');
$log->error('message');
$log->fatal('message');

echo 'test12';

$c = new Test20230310();
$c->test20230310();

exit();

class Test20230310
{
    public function test20230310() {
        $log = new Log();
        $log->warn(substr(explode('.', microtime(true))[1], 0, 3));
    }
}
