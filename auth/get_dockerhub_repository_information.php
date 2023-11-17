<?php

include('/usr/src/app/log.php');

$log = new Log();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START {$requesturi}");

get_dockerhub_repository_information();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function get_dockerhub_repository_information()
{
    global $log;
    $log->info('BEGIN');
  
    $res = file_get_contents('https://hub.docker.com/v2/repositories/library/php/tags?page_size=100');

    for ($i = 0; $i < 10; $i++) {
        $data = json_decode($res, true);

        foreach ($data['results'] as $data_tag) {
            if ($data_tag['name'] == '8.2-apache') {
                $log->info($data_tag['last_updated']);
                apcu_store('last_updated_8.2-apache', $data_tag['last_updated']);
                break 2;
            }
        }

        $url_next = $data['next'];
        if ($url_next == null) {
            break;
        }
        $res = file_get_contents($url_next);
    }
}
