<?php

class Log
{
    public $colors = array('INFO' => '32', 'WARN' => '33');
    
    function __construct() {
    }
    
    public function info($message_) {
        $this->output($message_);
    }
    
    public function warn($message_) {
        $this->output($message_);
    }
    
    private function output($message_) {
        $array = debug_backtrace();
        array_shift($array);
        if (count($array) == 1) {
            $value = array_shift($array);
            $level = strtoupper($value['function']);
            $file = basename($value['file']);
            $line = $value['line'];
            $function_chain = '[-]';
        } else {
            $value = array_shift($array);
            $level = strtoupper($value['function']);
            $function_chain = '';
            foreach (array_reverse($array) as $value) {
                $file = basename($value['file']);
                $line = $value['line'];
                $function_chain .= '[' . $value['function'] . ']';
            }
        }
        error_log($level . ' color code : ' . $this->colors[$level]);
        $log_header = date('Y-m-d H:i:s.') . substr(explode(".", (microtime(true) . ""))[1], 0, 3)
            . ' ' . $_ENV['DEPLOY_DATETIME'] . ' ' . trim(getmypid() . " ${level} ${file} ${line}");
        file_put_contents('php://stderr', "\033[0;" . "32m${log_header}\033[0m ${function_chain} ${message_}\n");
    }
}
