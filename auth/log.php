<?php

class Log
{
    private const COLOR_LIST = [
        'TRACE' => '34', // blue
        'DEBUG' => '36', // cyan
        'INFO' => '32', // green
        'WARN' => '33', // yellow
        'ERROR' => '31', // red
        'FATAL' => '35', // purple
    ];
    
    function __construct() {
    }
    
    public function trace($message_) {
        $this->output($message_);
    }
    
    public function debug($message_) {
        $this->output($message_);
    }
    
    public function info($message_) {
        $this->output($message_);
    }
    
    public function warn($message_) {
        $this->output($message_);
    }
    
    public function error($message_) {
        $this->output($message_);
    }
    
    public function fatal($message_) {
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
            $line = $value['line'];
            $file = basename($value['file']);
            $function_chain = '';
            foreach (array_reverse($array) as $value) {
                $function_chain .= '[' . $value['function'] . ']';
            }
        }
        $mt = explode('.', microtime(true));
        $mili_sec = '000';
        if (count($mt) == 2) {
            $mili_sec = substr($mt[1] . '000' , 0, 3);
        }
        $log_datetime = date('Y-m-d H:i:s.') . $mili_sec;
        $log_header = $_ENV['DEPLOY_DATETIME'] . ' ' . getmypid() . " ${level} ${file} ${line}";
        file_put_contents('php://stderr', "${log_datetime} \033[0;" . self::COLOR_LIST[$level] . "m${log_header}\033[0m ${function_chain} ${message_}\n");
    }
}
