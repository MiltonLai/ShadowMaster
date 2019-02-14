<?php

class Server extends SwooleBase
{
    private $serv;
    private $sockets;
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->sockets = array();

        $this->serv = new \Swoole\Http\Server($this->config['server_host'], $this->config['server_port']);
        $this->serv->set(array(
            'worker_num' => 3,
            'daemonize' => true,
            'max_request' => 5,
            'dispatch_mode' => 1,
            'reload_async' => true,
            'log_file' => DIR_LOGS . DIRECTORY_SEPARATOR . 'server.log',
            'log_level' => SWOOLE_LOG_TRACE,
            'trace_flags' => SWOOLE_TRACE_ALL,
        ));
        $this->bindCallback($this->serv);
        $this->serv->start();
    }

    public function onWorkerStart(\Swoole\Server $server, int $worker_id)
    {
        parent::onWorkerStart($server, $worker_id);
        $this->resetWorkerSocket($worker_id);
    }

    public function onWorkerStop(\Swoole\Server $server, int $worker_id)
    {
        parent::onWorkerStop($server, $worker_id);
        $this->beforeWorkerStop($worker_id);
    }

    public function onWorkerError(\Swoole\Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        parent::onWorkerError($server, $worker_id, $worker_pid, $exit_code, $signal);
        $this->beforeWorkerStop($worker_id);
    }

    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        $worker_id = $this->serv->worker_id;
        echo '[onRequest] PID:' . $this->serv->master_pid . ', workerId:' . $worker_id . ', uri:' . $request->server['request_uri'] . "\n";
        if ($this->sysFilter($request, $response)) return;

        $socket = $this->sockets[$worker_id];
        $handler = new RequestHandler($this->config, $request, $response, $socket);
        if (!$handler->handle()) {
            echo $handler->getErrorCode() . "\n";
            $this->response($response, 500, 'text/html', 'Error 500', 'Internal error: ' . $handler->getErrorCode());
            $this->serv->stop(-1, true);
        }
    }

    /**
     * Filter the system operation requests
     *
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    private function sysFilter(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool
    {
        $request_uri = $request->server['request_uri'];
        if ($request_uri == '/shutdown') {
            $this->serv->shutdown();
            $this->response($response, 200, 'text/html', 'Message', 'Server is shuttig down');
            return true;
        } elseif ($request_uri == '/reload') {
            $this->serv->reload();
            $this->response($response, 200, 'text/html', 'Message', 'Server is reloading');
            return true;
        }
        return false;
    }

    private function response(\Swoole\Http\Response $response, int $status_code, string $content_type, string $title, string $message) {
        $response->header("Content-Type", $content_type);
        $response->status($status_code);
        $response->end('<html><head><title>' . $title . '</title></head><body>' . $message . '</body></html>');
    }

    private function beforeWorkerStop(int $worker_id) {
        if (isset($this->sockets[$worker_id])) {
            $socket = $this->sockets[$worker_id];
            $socket->close();
            $tmp_socket_file = '/tmp/ss-php.sock.' . $worker_id;
            if (file_exists($tmp_socket_file)) {
                unlink($tmp_socket_file);
            }
        }
    }

    private function resetWorkerSocket(int $worker_id) {
        if (isset($this->sockets[$worker_id])) {
            $socket = $this->sockets[$worker_id];
            $socket->close();
        }

        $socket = new \Swoole\Coroutine\Socket(AF_UNIX, SOCK_DGRAM, 0);
        if (!$socket) {
            throw new RuntimeException('socket_create failed');
        }
        $tmp_socket_file = '/tmp/ss-php.sock.' . $worker_id;
        # In case the socket file exists
        if (file_exists($tmp_socket_file)) {
            unlink($tmp_socket_file);
        }
        if (!$socket->bind($tmp_socket_file)) {
            throw new RuntimeException('unable to bind to ' . $tmp_socket_file);
        }
        if (!$socket->connect('/var/run/shadowsocks-libev.sock', 0, 10)) {
            throw new RuntimeException("unable to connect to shadowsocks socket");
        }
        $this->sockets[$worker_id] = $socket;
    }
}
