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

    private $_statement_insert; // pdo prepare statement
    private $_deploy_datetime; // unix time
    private $_ch; // curl channel
    
    function __construct() {
        $this->_deploy_datetime = strtotime(substr($_ENV['DEPLOY_DATETIME'], 0, 4) . '/' . substr($_ENV['DEPLOY_DATETIME'], 4, 2) . '/' . substr($_ENV['DEPLOY_DATETIME'], 6, 2)
             . ' ' . substr($_ENV['DEPLOY_DATETIME'], 8, 2) . ':' . substr($_ENV['DEPLOY_DATETIME'], 10, 2) . ':' . substr($_ENV['DEPLOY_DATETIME'], 12, 2));

        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_URL, 'https://logs-01.loggly.com/inputs/' . $_ENV['LOGGLY_TOKEN']
                    . '/tag/' . $_ENV['RENDER_EXTERNAL_HOSTNAME'] . ',' . $_ENV['RENDER_EXTERNAL_HOSTNAME'] . '_' . $_ENV['DEPLOY_DATETIME'] . '/');
        curl_setopt($this->_ch, CURLOPT_POST, 1);
        curl_setopt($this->_ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain; charset=utf-8',]);
        curl_setopt($this->_ch, CURLOPT_TCP_KEEPALIVE, 1);

        clearstatcache();
        if (!file_exists('/tmp/sqlitelog.db')) {
            $pdo = new PDO('sqlite:/tmp/sqlitelog.db', NULL, NULL, array(PDO::ATTR_PERSISTENT => TRUE));

            $sql_create = <<< __HEREDOC__
CREATE TABLE t_log (
    seq INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    datetime TIMESTAMP DEFAULT (DATETIME('now','localtime')),
    pid TEXT NOT NULL,
    level TEXT NOT NULL,
    file TEXT NOT NULL,
    line TEXT NOT NULL,
    function TEXT NOT NULL,
    message TEXT
)
__HEREDOC__;

            $rc = $pdo->exec($sql_create);
        } else {
            $pdo = new PDO('sqlite:/tmp/sqlitelog.db', NULL, NULL, array(PDO::ATTR_PERSISTENT => TRUE));
        }

        $sql_insert = <<< __HEREDOC__
INSERT INTO t_log (pid, level, file, line, function, message)
  VALUES (:b_pid, :b_level, :b_file, :b_line, :b_function, :b_message);
__HEREDOC__;

        $this->_statement_insert = $pdo->prepare($sql_insert);
    }

    function __destruct() {
        curl_close($this->_ch);
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
        $milli_sec = '000';
        if (count($mt) == 2) {
            $milli_sec = substr($mt[1] . '000' , 0, 3);
        }
        $log_datetime = date('Y-m-d H:i:s.') . $milli_sec;
        $log_header = $_ENV['DEPLOY_DATETIME'] . ' ' . getmypid() . " {$level} {$file} {$line}";

        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, "{$log_datetime} {$log_header} {$function_chain} {$message_}");
        $res = curl_exec($this->_ch);
        $http_code = (string)curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);
        if (strtoupper($value['function']) != 'INFO' || time() - $this->_deploy_datetime < 60 * 5 || $http_code != '200') {
            file_put_contents('php://stderr', "{$log_datetime} \033[0;" . self::COLOR_LIST[$level] . "m{$log_header}\033[0m {$function_chain} {$message_}\n");
        }
        
        $this->_statement_insert->execute(
            [':b_pid' => getmypid(),
             ':b_level' => $level,
             ':b_file' => $file,
             ':b_line' => $line,
             ':b_function' => $function_chain,
             ':b_message' => $message_,
            ]
        );
    }
}
