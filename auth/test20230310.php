<?php

include('./log.php');

$log = new Log();

echo 'test13';

$c = new Test20230310();
$c->test20230310();

exit();

class Test20230310
{
    public function test20230310() {
        $log = new Log();
        
        $res = file_get_contents('https://auth.docker.io/token');
        
        $log->warn(print_r(json_decode($res), true));
    }
}
