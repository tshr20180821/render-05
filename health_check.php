<?php

$pid = getmypid();
$requesturi = $_SERVER['REQUEST_URI'];
$time_start = microtime(true);
error_log("START ${requesturi} " . date('Y/m/d H:i:s') . ' ' . $_ENV['DEPLOY_DATETIME']);

push_atom();

error_log('FINISH ' . substr((microtime(true) - $time_start), 0, 7) . 's');

exit();

function push_atom()
{
    $log_prefix = '[' . __METHOD__ . ' ' . $_ENV['DEPLOY_DATETIME'] . '] ';
    error_log($log_prefix . 'BEGIN');
    
    header("Content-Type: application/atom+xml");
  
$atom = <<< __HEREDOC__
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
 <title>Health Check __FQDN__</title>
 <link href="https://example.org/"/>
 <updated>2022-01-01T00:00:00Z</updated>
 <author>
   <name>Health Check</name>
 </author>
 <id>urn:uuid:60a76c80-d399-11d9-b93C-0003939e0af8</id>
 <entry>
   <title>Health Check __UPDATED__</title>
   <link href="https://example.org/"/>
   <id>__ID__</id>
   <updated>__UPDATED__</updated>
   <summary>__UPDATED__</summary>
 </entry>
</feed>
__HEREDOC__;
    
    $atom = str_replace('__ID__', uniqid(), $atom);
    $atom = str_replace('__FQDN__', $_ENV['RENDER_EXTERNAL_HOSTNAME'], $atom);
    $atom = str_replace('__UPDATED__', date('Y-m-d') . 'T' . date('H:i:s') . '+09', $atom);

    echo $atom;
}
