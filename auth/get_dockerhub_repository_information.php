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
  
    $log->info('target_tag_name : ' . $_ENV['DOCKER_HUB_PHP_TAG']);
    $res = file_get_contents('https://hub.docker.com/v2/repositories/library/php/tags?page_size=100');

    for ($i = 0; $i < 10; $i++) {
        $data = json_decode($res, true);

        foreach ($data['results'] as $data_tag) {
            $log->info('tag_name : ' . $data_tag['name']);
            if ($data_tag['name'] == $_ENV['DOCKER_HUB_PHP_TAG']) {
                $log->info($data_tag['last_updated']);
                apcu_store('last_updated_' . $_ENV['DOCKER_HUB_PHP_TAG'], $data_tag['last_updated']);
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
