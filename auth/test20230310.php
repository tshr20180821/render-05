<?php

include('./log.php');

$log = new Log();

echo 'test17';

$c = new Test20230310();
$c->test20230310();

exit();

class Test20230310
{
    public function test20230310() {
        $log = new Log();
        
        $res = file_get_contents('https://auth.docker.io/token?service=registry.docker.io&scope=repository:php:pull');
        
        // $log->warn(print_r(json_decode($res), true));
        
        $token = json_decode($res)->token;
        // $log->warn($token);
        
        $url = 'https://registry-1.docker.io/v2/php/tags/list';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Accept: application/json',]);
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);
        $http_code = (string)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $log->warn('HTTP CODE : ' . $http_code);
        $log->warn($res);
    }
}
