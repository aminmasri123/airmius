<?php

// Loopback-only SMTP receiver for transport tests; it never forwards messages.
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, "Cannot start local SMTP test receiver.\n");
    exit(1);
}
echo stream_socket_get_name($server, false)."\n";
flush();
$connection = stream_socket_accept($server, 15);
if ($connection === false) {
    fclose($server);
    exit(2);
}
stream_set_timeout($connection, 10);
fwrite($connection, "220 localhost test receiver\r\n");
$message = '';
$data = false;
while (($line = fgets($connection)) !== false) {
    if ($data) {
        if ($line === ".\r\n") {
            $data = false;
            file_put_contents($argv[1], $message);
            fwrite($connection, "250 received locally\r\n");
        } else {
            $message .= str_starts_with($line, '..') ? substr($line, 1) : $line;
        }

        continue;
    }
    $command = strtoupper(strtok($line, ' '));
    $command = trim($command);
    if ($command === 'EHLO' || $command === 'HELO') {
        fwrite($connection, "250 localhost\r\n");
    } elseif ($command === 'RCPT') {
        $accepted = ($argv[2] ?? '') !== 'reject' && preg_match('/@example\.test>\s*$/i', $line);
        fwrite($connection, $accepted ? "250 recipient accepted\r\n" : "550 recipient rejected\r\n");
    } elseif ($command === 'DATA') {
        $data = true;
        fwrite($connection, "354 end with dot\r\n");
    } elseif ($command === 'QUIT') {
        fwrite($connection, "221 goodbye\r\n");
        break;
    } elseif (in_array($command, ['MAIL', 'RSET', 'NOOP'], true)) {
        fwrite($connection, "250 OK\r\n");
    } else {
        fwrite($connection, "502 unsupported\r\n");
    }
}
fclose($connection);
fclose($server);
