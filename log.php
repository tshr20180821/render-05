<?php

class Log
{
    function __construct() {
    }
    
    public function info($message_) {
        $this->output($message_, 'INFO', '32');
    }
    
    public function warn($message_) {
        $this->output($message_, 'WARN', '33');
    }
    
    private function output($message_, $level_, $color_) {
        $array = debug_backtrace()[2];
        $log_header = date('Y-m-d H:i:s.') . substr(explode(".", (microtime(true) . ""))[1], 0, 3)
            . ' ' . $_ENV['DEPLOY_DATETIME'] . ' ' . getmypid() . " ${level_} " . basename($array['file']) . ' ' . $array['line'] . ' ' . $array['function'];
        file_put_contents('php://stderr', "\033[0;${color_}m${log_header}\033[0m" . ' ' . $message_ . "\n");
    }
}
