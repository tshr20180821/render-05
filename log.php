<?php

class Log
{
    function __construct() {
    }
    
    public function info($message_) {
        $array = end(debug_backtrace());
        $file = $array['file'];
        
        file_put_contents('php://stderr', "\033[0;32" . date('Y-m-d H:i:s.u') .
                          ' ' . $_ENV['DEPLOY_DATETIME'] . ' ' . getmypid() . ' INFO ' . $array['file'] . ' ' . $array['line'] .  ' ' . $message_ . "\033[0m\n");
    }
}
