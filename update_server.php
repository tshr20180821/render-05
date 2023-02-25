<?php

$dsn = "mysql:host={$_ENV['DB_SERVER']};dbname={$_ENV['DB_NAME']}";
$options = array(
  PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
);
$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $options);

$sql_upsert = <<< __HEREDOC__
INSERT INTO m_server (server_id, server_name)
       VALUES(1, :b_server_name)
    ON DUPLICATE KEY UPDATE server_id = 1
                           ,server_name = :b_server_name
__HEREDOC__;

$statemant_upsert = $pdo->prepare($sql_upsert);

$statement_upsert->execute([':b_server_name' => gethostname(),]);

$pdo = null;
