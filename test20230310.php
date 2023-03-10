<?php

require '/usr/src/app/vendor/autoload.php';
// require_once('/usr/src/app/vendor/log4php/Logger.php');

Logger::configure('/usr/src/app/log4php.xml');

$logger = Logger::getLogger('default');

$logger->debug('debug_message');

echo 'test';
