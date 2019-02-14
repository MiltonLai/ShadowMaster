<?php

class RequestHandler
{
    private $config;
    private $request;
    private $response;
    private $socket;
    private $errCode;

    private static $static_extensions = [
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'jpg' => 'image/jpg',
        'jpeg' => 'image/jpg',
        'mp4' => 'video/mp4'
    ];

    public function __construct(array $config, \Swoole\Http\Request $request, \Swoole\Http\Response $response, \Swoole\Coroutine\Socket $socket)
    {
        $this->config = $config;
        $this->request = $request;
        $this->response = $response;
        $this->socket = $socket;
    }

    public function handle(): bool
    {
        if ($this->preFilter()) return true;

        $request_uri = $this->request->server['request_uri'];
        switch ($request_uri) {
            case '/ping':
                $result = $this->send('ping');
                if (!$result) {
                    $this->responseText('application/json', '{"code": 1, "message": "' . $this->errCode . '"}');
                    return false;
                }
                $this->responseText('application/json', '{"code": 0, "data": "{' . $result . '}"}');
                return true;

            case '/add':
                $port = intval($this->request->get['port']);
                if ($port < $this->config['port_min'] || $port > $this->config['port_max']) {
                    $this->responseText('application/json', '{"code": 1, "message": "Port is not allowed"}');
                    return true;
                }
                $passwd = $this->request->get['name'];
                if (strlen($passwd) < 7) {
                    $this->responseText('application/json', '{"code": 1, "message": "Name is too short"}');
                    return true;
                }
                $msg = 'add: {"server_port":' . $port . ', "password":"' . $passwd . '"}';
                $result = $this->send($msg);
                if (!$result) {
                    $this->responseText('application/json', '{"code": 1, "message": "' . $this->errCode . '"}');
                    return false;
                }
                $this->responseText('application/json', '{"code": 0, "message": "' . $result . '"}');
                return true;

            case '/del':
                $port = intval($this->request->get['port']);
                if ($port < $this->config['port_min'] || $port > $this->config['port_max']) {
                    $this->responseText('application/json', '{"code": 1, "message": "Port is not allowed"}');
                    return true;
                }
                $msg = 'remove: {"server_port":' . $port . '}';
                $result = $this->send($msg);
                if (!$result) {
                    $this->responseText('application/json', '{"code": 1, "message": "' . $this->errCode . '"}');
                    return false;
                }
                $this->responseText('application/json', '{"code": 0, "message": "' . $result . '"}');
                return true;

            default:
                $this->responseText('application/json', '{"code": 1, "message": "Unknow request"}');
                return true;
        }
    }

    private function send(string $msg): string
    {
        $result = $this->socket->send($msg, 5);
        if (!$result) {
            $this->errCode = $this->socket->errCode;
            return false;
        } elseif ($result != strlen($msg)) {
            $this->errCode = 'Sent incompletely';
            return false;
        }
        return $this->socket->recv(1024);
    }

    public function getErrorCode(): string
    {
        return $this->errCode;
    }

    /**
     * Filter the requests for static files
     *
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    private function preFilter(): bool
    {
        $request_uri = $this->request->server['request_uri'];
        $pos = strrpos($request_uri, '.');
        if (!$pos) return false;

        $extension = substr($request_uri, $pos + 1);
        if (!array_key_exists($extension, RequestHandler::$static_extensions)) return false;

        $file = DIR_WEB . $request_uri;
        if (!file_exists($file)) {
            $this->responseHtml(404, 'text/html', 'Error 404', 'File not found.');
            return true;
        }
        $this->response->header('Content-Type', RequestHandler::$static_extensions[$extension]);
        $this->response->sendfile($file);
        return true;
    }

    private function responseHtml(int $status_code, string $content_type, string $title, string $message)
    {
        $this->response->header("Content-Type", $content_type);
        $this->response->status($status_code);
        $this->response->end('<html><head><title>' . $title . '</title></head><body>' . $message . '</body></html>');
    }

    private function responseText(string $content_type, string $text)
    {
        $this->response->header("Content-Type", $content_type);
        $this->response->end($text);
    }
}
