<?php
require __DIR__ . '/../vendor/autoload.php';

use Naka507\Socket\Server;
$server = new Server();

// Emitted when new connection come
$server->onWorkerStart = function($connection)
{
    echo "New onWorkerStart\n";
};

// Emitted when new connection come
$server->onConnect = function($connection)
{
    echo "New Connection\n";
};

// Emitted when data received
$server->onMessage = function($request, $response)
{
    $response->write(' Hello World !!!');
    $response->end();
};
$server->start();

