<?php

class Log
{
    function __construct() {
    }
    
    public function info($message_) {
        // error_log(print_r(debug_backtrace(), true));
        $this->output($message_, 'INFO', '32', debug_backtrace()[1]);
    }
    
    public function warn($message_) {
        $this->output($message_, 'WARN', '33', debug_backtrace()[1]);
    }
    
    private function output($message_, $level_, $color_, $array_) {
        $log_header = date('Y-m-d H:i:s.') . substr(explode(".", (microtime(true) . ""))[1], 0, 3)
            . ' ' . $_ENV['DEPLOY_DATETIME'] . ' ' . getmypid() . " ${level_} " . basename($array_['file']) . ' ' . $array_['line'];
        file_put_contents('php://stderr', "\033[0;${color_}m${log_header}\033[0m" . ' ' . $message_ . "\n");
    }
}
