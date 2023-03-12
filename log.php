<?php

class Log
{
    function __construct() {
    }
    
    public function info($message_) {
        output($message_, 'INFO', '32', end(debug_backtrace()));
    }
    
    public function warn($message_) {
        output($message_, 'WARN', '33', end(debug_backtrace()));
    }
    
    private function output($message_, $level_, $color_, $array_) {
        $log_header = date('Y-m-d H:i:s.') . substr(explode(".", (microtime(true) . ""))[1], 0, 3)
            . ' ' . $_ENV['DEPLOY_DATETIME'] . ' ' . getmypid() . " ${level_} " . basename($array_['file']) . ' ' . $array_['line'];
        file_put_contents('php://stderr', "\033[0;${color_}m${log_header}\033[0m" . ' ' . $message_ . "\n");
    }
}
