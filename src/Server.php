<?php
namespace Naka507\Socket;

class Server {
    public static $_OS = OS_TYPE_LINUX;
    public static $event = null;
    public static $_gracefulStop = false;
    public static $_outputStream = null;
    public static $_outputDecorated = false;
	public $socket;
	public $protocol;
	public $transport;
	public $onWorkerStart;
	public $onMessage;
	public $onClose;
	public $onError;
	public $onBufferDrain;
	public $onBufferFull;
	function __construct($port = 8000) {
        Http::init();
		$this->port = $port;
		$this->protocol = 'Http';
        $this->socket = null;
        $this->onMessage = $this->onClose = $this->onError = $this->onBufferDrain = $this->onBufferFull = null;

        // Only for cli.
        if (php_sapi_name() != "cli") {
            exit("only run in command line mode \n");
        }

        if ( DIRECTORY_SEPARATOR === '\\' ) {
            Server::$_OS = OS_TYPE_WINDOWS;
        }

	}

	public function start(){
		$local_socket = "tcp://0.0.0.0:".$this->port;
        
		$errno = 0;
		$errmsg = '';
		$this->socket = stream_socket_server($local_socket, $errno, $errmsg);
		if (!$this->socket) {
			throw new Exception($errmsg);
		}
		stream_set_blocking($this->socket, 0);

		if (!Server::$event) {
            Server::$event = new Events();
		}

		Server::$event->add($this->socket, Events::EV_READ, array($this, 'accept'));

		Timer::init(Server::$event);
		
		if (empty($this->onMessage)) {
            $this->onMessage = function () {};
		}

		// Try to emit onWorkerStart callback.
        if ($this->onWorkerStart) {
            try {
                call_user_func($this->onWorkerStart, $this);
            } catch (\Exception $e) {
                Server::log($e);
                // Avoid rapid infinite loop exit.
                sleep(1);
                exit(250);
            } catch (\Error $e) {
                Server::log($e);
                // Avoid rapid infinite loop exit.
                sleep(1);
                exit(250);
            }
        }

		Server::$event->loop();
	}

    public function accept($socket)
    {
        // Accept a connection on server socket.
        set_error_handler(function(){});
        $client = stream_socket_accept($socket, 0, $remote_address);
        restore_error_handler();

        // Thundering herd.
        if (!$client) {
            return;
        }

        $connection                         = new Connection($client, $remote_address);
        $this->connections[$connection->id] = $connection;
        $connection->worker                 = $this;
        $connection->protocol               = $this->protocol;
        $connection->transport              = $this->transport;
        $connection->onMessage              = $this->onMessage;
        $connection->onClose                = $this->onClose;
        $connection->onError                = $this->onError;
        $connection->onBufferDrain          = $this->onBufferDrain;
        $connection->onBufferFull           = $this->onBufferFull;

        // Try to emit onConnect callback.
        if ($this->onConnect) {
            try {
                call_user_func($this->onConnect, $connection);
            } catch (\Exception $e) {
                static::log($e);
                exit(250);
            } catch (\Error $e) {
                static::log($e);
                exit(250);
            }
        }
    }

    public static function getGracefulStop()
    {
        return Server::$_gracefulStop;
    }

    public static function log($msg)
    {
        $msg = $msg . "\n";
        if (!Server::$daemonize) {
            Server::console($msg);
        }
        file_put_contents((string)Server::$logFile, date('Y-m-d H:i:s') . ' ' . 'pid:'
            . (Server::$_OS === OS_TYPE_LINUX ? posix_getpid() : 1) . ' ' . $msg, FILE_APPEND | LOCK_EX);
    }

    public static function console($msg, $decorated = false)
    {
        $stream = Server::outputStream();
        if (!$stream) {
            return false;
        }
        if (!$decorated) {
            $line = $white = $green = $end = '';
            if (Server::$_outputDecorated) {
                $line = "\033[1A\n\033[K";
                $white = "\033[47;30m";
                $green = "\033[32;40m";
                $end = "\033[0m";
            }
            $msg = str_replace(array('<n>', '<w>', '<g>'), array($line, $white, $green), $msg);
            $msg = str_replace(array('</n>', '</w>', '</g>'), $end, $msg);
        } elseif (!Server::$_outputDecorated) {
            return false;
        }
        fwrite($stream, $msg);
        fflush($stream);
        return true;
    }

    private static function outputStream($stream = null)
    {
        if (!$stream) {
            $stream = Server::$_outputStream ? Server::$_outputStream : STDOUT;
        }
        if (!$stream || !is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            return false;
        }
        $stat = fstat($stream);
        if (($stat['mode'] & 0170000) === 0100000) {
            // file
            Server::$_outputDecorated = false;
        } else {
            Server::$_outputDecorated =
                Server::$_OS === OS_TYPE_LINUX &&
                function_exists('posix_isatty') &&
                posix_isatty($stream);
        }
        return Server::$_outputStream = $stream;
    }

}