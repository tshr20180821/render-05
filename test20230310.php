<?php

require '/usr/local/lib/php/vendor/autoload.php';
require_once('log4php/Logger.php');

Logger::configure('/usr/src/app/log4php.xml');

$logger = Logger::getLogger();

$logger->debug('debug_message');

echo 'test';
