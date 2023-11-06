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
    
    function __construct() {

        clearstatcache();
        if (!file_exists('/tmp/sqlitelog.db')) {
            $pdo = new PDO('sqlite:/tmp/sqlitelog.db', NULL, NULL, array(PDO::ATTR_PERSISTENT => TRUE));

            $sql_create = <<< __HEREDOC__
CREATE TABLE t_log (
    seq INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    regist_datetime TIMESTAMP DEFAULT (DATETIME('now','localtime')),
    process_datetime TEXT NOT NULL,
    pid TEXT NOT NULL,
    level TEXT NOT NULL,
    file TEXT NOT NULL,
    line TEXT NOT NULL,
    function TEXT NOT NULL,
    message TEXT,
    tags TEXT,
    status INTEGER NOT NULL
)
__HEREDOC__;

            $rc = $pdo->exec($sql_create);

            exec('cd /usr/src/app && java -classpath .:sqlite-jdbc-3.43.2.0.jar:slf4j-api-2.0.9.jar:slf4j-nop-2.0.9.jar -Duser.timezone=Asia/Tokyo -Dfile.encoding=UTF-8 LogOperationMain &');
        } else {
            $pdo = new PDO('sqlite:/tmp/sqlitelog.db', NULL, NULL, array(PDO::ATTR_PERSISTENT => TRUE));
        }
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA busy_timeout = 10000;');

        $sql_insert = <<< __HEREDOC__
INSERT INTO t_log (process_datetime, pid, level, file, line, function, message, tags, status)
  VALUES (:b_process_datetime, :b_pid, :b_level, :b_file, :b_line, :b_function, :b_message, 'php', 0);
__HEREDOC__;

        $this->_statement_insert = $pdo->prepare($sql_insert);
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
        $log_header = $_ENV['RENDER_EXTERNAL_HOSTNAME'] . ' ' . $_ENV['DEPLOY_DATETIME'] . ' ' . getmypid() . " {$level} {$file} {$line}";

        file_put_contents('php://stderr', "{$log_datetime} \033[0;" . self::COLOR_LIST[$level] . "m{$log_header}\033[0m {$function_chain} {$message_}\n");
        
        $this->_statement_insert->execute(
            [':b_process_datetime' => $log_datetime,
             ':b_pid' => getmypid(),
             ':b_level' => $level,
             ':b_file' => $file,
             ':b_line' => $line,
             ':b_function' => $function_chain,
             ':b_message' => $message_,
            ]
        );
    }
}
