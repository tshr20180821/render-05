<?php

include('/usr/src/app/log.php');

$log = new Log();

$pid = getmypid();
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
   <title>__DEPLOY_DATETIME__</title>
   <link href="http://example.org/"/>
   <id>tag:__ID__</id>
   <updated>__UPDATED__</updated>
   <summary>__FQDN__ __APT_RESULT__ Log Size : __LOG_SIZE__MB __PROCESSOR_NAME__</summary>
 </entry>
</feed>
__HEREDOC__;
    
    $apt_result = '';
    if (file_exists('/tmp/CHECK_APT')) {
        $apt_result = trim(file_get_contents('/tmp/CHECK_APT')). ' ' . date('Y/m/d H:i:s', filemtime('/tmp/CHECK_APT'));
    }
    
    $file_size = 0;
    if (file_exists('/tmp/sqlitelog.db')) {
        $file_size = filesize('/tmp/sqlitelog.db') / 1024 / 1024;
    }
    
    $tmp = str_split($_ENV['DEPLOY_DATETIME'], 2);
    $atom = str_replace('__DEPLOY_DATETIME__', $tmp[0] . $tmp[1] . '-' . $tmp[2] . '-' . $tmp[3] . ' ' . $tmp[4] . ':' . $tmp[5] . ':' . $tmp[6], $atom);
    $atom = str_replace('__ID__', $_ENV['RENDER_EXTERNAL_HOSTNAME'] . '-' . uniqid(), $atom);
    $atom = str_replace('__FQDN__', $_ENV['RENDER_EXTERNAL_HOSTNAME'], $atom);
    $atom = str_replace('__UPDATED__', date('Y-m-d') . 'T' . date('H:i:s') . '+09', $atom);
    $atom = str_replace('__APT_RESULT__', $apt_result, $atom);
    $atom = str_replace('__LOG_SIZE__', number_format($file_size), $atom);
    $atom = str_replace('__PROCESSOR_NAME__', $_ENV['__PROCESSOR_NAME__'], $atom);

    echo $atom;
}
