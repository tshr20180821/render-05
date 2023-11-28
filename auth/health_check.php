<?php

include('/usr/src/app/log.php');

$log = new Log();

$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
$log->info("START {$requesturi}");

push_atom();

$log->info('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function push_atom()
{
    global $log;

    $log->info('BEGIN');

    // $log->info('REMOTE_ADDR : ' . $_SERVER['REMOTE_ADDR']);
    $log->info('HTTP_X_FORWARDED_FOR : ' . $_SERVER['HTTP_X_FORWARDED_FOR']);

    header("Content-Type: application/atom+xml");

$atom = <<< __HEREDOC__
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Health Check __FQDN__</title>
  <link href="http://example.org/"/>
  <updated>2022-01-01T00:00:00Z</updated>
  <author>
    <name>__FQDN__</name>
  </author>
  <id>tag:__FQDN__</id>
  <entry>
    <title>__DEPLOY_DATETIME__ Deployed</title>
    <link href="http://example.org/"/>
    <id>tag:__ID__</id>
    <updated>__UPDATED__</updated>
    <summary>SQLite : __SQLITE_VERSION__
Log Size : __LOG_SIZE__MB
Docker Hub php:8.2-apache : __DOCKER_HUB_UPDATED__
apt Check : __APT_RESULT__
npm Check : __NPM_RESULT__</summary>
  </entry>
</feed>
__HEREDOC__;

    $file_size = 0;
    clearstatcache();
    if (file_exists($_ENV['SQLITE_LOG_DB_FILE'])) {
        $file_size = filesize($_ENV['SQLITE_LOG_DB_FILE']) / 1024 / 1024;
    }

    $apt_result = '';
    $npm_result = '';
    $mc = new Memcached();
    $mc->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
    $mc->setSaslAuthData('memcached', getenv('SASL_PASSWORD'));
    $mc->addServer('127.0.0.1', 11211);
    if ($mc->get('CHECK_APT') !== false) {
        $log->info('CHECK_APT : memcached hit');
        $apt_result = trim($mc->get('CHECK_APT'));
    } else {
        $log->info('CHECK_APT : memcached miss');
        $log->info('memcached results : ' . $mc->getResultCode());
    }
    if ($mc->get('CHECK_NPM') !== false) {
        $log->info('CHECK_NPM : memcached hit');
        $npm_result = $mc->get('CHECK_NPM');
    } else {
        $log->info('CHECK_NPM : memcached miss');
    }
    $mc->quit();

    $docker_hub_updated = '';
    if (apcu_exists('last_updated_' . $_ENV['DOCKER_HUB_PHP_TAG'])) {
        $docker_hub_updated = apcu_fetch('last_updated_' . $_ENV['DOCKER_HUB_PHP_TAG']);
    }

    $sqlite_version = '';
    if (apcu_exists('SQLITE_VERSION')) {
        $sqlite_version = apcu_fetch('SQLITE_VERSION');
    }

    $tmp = str_split($_ENV['DEPLOY_DATETIME'], 2);
    $atom = str_replace('__DEPLOY_DATETIME__', $tmp[0] . $tmp[1] . '-' . $tmp[2] . '-' . $tmp[3] . ' ' . $tmp[4] . ':' . $tmp[5] . ':' . $tmp[6], $atom);
    $atom = str_replace('__ID__', $_ENV['RENDER_EXTERNAL_HOSTNAME'] . '-' . uniqid(), $atom);
    $atom = str_replace('__FQDN__', $_ENV['RENDER_EXTERNAL_HOSTNAME'], $atom);
    $atom = str_replace('__UPDATED__', date('Y-m-d') . 'T' . date('H:i:s') . '+09', $atom);
    $atom = str_replace('__SQLITE_VERSION__', $sqlite_version, $atom);
    $atom = str_replace('__LOG_SIZE__', number_format($file_size), $atom);
    $atom = str_replace('__DOCKER_HUB_UPDATED__', $docker_hub_updated, $atom);
    $atom = str_replace('__APT_RESULT__', $apt_result, $atom);
    $atom = str_replace('__NPM_RESULT__', $npm_result, $atom);
    /*
    $atom = str_replace('__PROCESSOR_NAME__', $_ENV['PROCESSOR_NAME'], $atom);
    $atom = str_replace('__APACHE_VERSION__', $_ENV['APACHE_VERSION'], $atom);
    $atom = str_replace('__PHP_VERSION__', $_ENV['PHP_VERSION'], $atom);
    $atom = str_replace('__NODE_VERSION__', $_ENV['NODE_VERSION'], $atom);
    $atom = str_replace('__HOST_VERSION__', $_ENV['HOST_VERSION'], $atom);
    $atom = str_replace('__GUEST_VERSION__', $_ENV['GUEST_VERSION'], $atom);
    $atom = str_replace('__JAVA_VERSION__', $_ENV['JAVA_VERSION'], $atom);
    */

    echo $atom;
}
