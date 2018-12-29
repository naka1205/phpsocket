PHPSocket 
=================
基于[Workerman](https://github.com/walkor/Workerman) 改写的简化版。
用于开发基于 Socket 的 HTTP Server

安装
=======
```
composer require naka1205/phpsocket
```

使用
=======

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Naka507\Socket\Server;
$server = new Server();

//服务启动
$server->onWorkerStart = function($worker)
{
    echo "New onWorkerStart\n";
};

//建立连接
$server->onConnect = function($connection)
{
    echo "New Connection\n";
};

//接收请求
$server->onMessage = function($request, $response)
{
    $response->write(' Hello World !!!');
    $response->end();
};
$server->start();

```
### WINDOWS
```
php app.php
```
### LINUX
DEBUG 模式 （默认）
```
php app.php
```
守护进程
```
php app.php -d
```
关闭进程
```
php app.php -s
```
### 浏览器访问
http://127.0.0.1:8000