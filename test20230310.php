<?php

require '/usr/src/app/vendor/autoload.php';
// require_once('/usr/src/app/vendor/log4php/Logger.php');

Logger::configure('/usr/src/app/log4php.xml');

$logger = Logger::getLogger('default');

$logger->debug('debug_message');

// file_put_contents("php://stderr", "stderr message!\n");
file_put_contents("php://stderr", "\033[0;31mmessage\033[0m\n");

echo 'test4';
