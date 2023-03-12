<?php

class Log
{
    function __construct() {
    }
    
    public function info($message_) {
        $array = end(debug_backtrace());
        $file = $array['file'];
        $log_header = date('Y-m-d H:i:s.') . substr(explode(".", (microtime(true) . ""))[1], 0, 3)
            . ' ' . $_ENV['DEPLOY_DATETIME'] . ' ' . getmypid() . ' INFO ' . basename($array['file']) . ' ' . $array['line'];
        file_put_contents('php://stderr', "\033[0;32${log_header}\033[0m" . ' ' . $message_ . "\n");
    }
}
