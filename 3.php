<?php

function sendToWs($data)
{
    $client = new \swoole_client(SWOOLE_SOCK_UDP);
    $client->connect('127.0.0.1', 9504, 0.5, 0);
    $client->send($data);
}


sendToWs(json_encode([
    'uid' => '15',
    'url' => 'http://www.360.com.sb/xmlrpc.php'
]));