<?php

class RequestHandler
{
    private $config;
    private $session_manager;
    private $request;
    private $response;
    private $session;
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

    public function __construct(array $config, SessionManager $session_manager,  \Swoole\Http\Request $request, \Swoole\Http\Response $response, $socket)
    {
        $this->config = $config;
        $this->session_manager = $session_manager;
        $this->request = $request;
        $this->response = $response;
        $this->socket = $socket;
    }

    public function handle(): bool
    {
        if ($this->staticFileFilter()) return true;
        if ($this->sessionFilter()) return true;

        $request_uri = $this->request->server['request_uri'];
        switch ($request_uri) {
            case '/session_start':
                return $this->doSessionStart();
            case '/login':
                return $this->doLogin();
            case '/ping':
                return $this->doPing();
            case '/add':
                return $this->doAdd();
            case '/del':
                return $this->doDel();
            default:
                $this->responseJson(1, 'Unknow request');
                return true;
        }
    }

    public function doSessionStart() : bool
    {
        $secure = $this->request->get['secure'];
        if (empty($secure)) {
            $this->responseHtml(400, 'text/html', 'Error 400', 'Bad request: invalid parameters');
            return true;
        }
        $sid = $this->session_manager->create($secure);
        $this->responseJson(0, null, $sid);
        return true;
    }

    public function doLogin(): bool
    {
        $ip = $this->request->server['remote_addr'];
        echo "IP: $ip\n";
        $sid = $this->request->get['sid'];
        $username = $this->request->get['username'];
        $password = $this->request->get['password'];
        if (empty($sid) || empty($username) || empty($password)) {
            $this->responseHtml(400, 'text/html', 'Error 400', 'Bad request: invalid parameters');
            return true;
        }
        if ($this->session_manager->login($sid, $username, $password)) {
            $this->responseJson(0, 'Success');
            return true;
        } else {
            $this->responseJson(1, 'Failed');
            return false;
        }
    }

    public function doPing() : bool
    {
        $result = $this->send('ping');
        if (!$result) {
            $this->responseJson(1, $this->errCode);
            return false;
        }
        $result = substr($result, strpos($result, '{'));
        $port_array = json_decode($result, true);
        ksort($port_array);
        $this->responseJson(0, null, $port_array);
        return true;
    }

    public function doAdd() : bool
    {
        $port = intval($this->request->get['port']);
        if ($port < $this->config['ss_port_min'] || $port > $this->config['ss_port_max']) {
            $this->responseJson(1, 'This port is not allowed');
            return true;
        }
        $passwd = $this->request->get['name'];
        if (strlen($passwd) < 7) {
            $this->responseJson(1, 'Name is too short');
            return true;
        }
        $msg = 'add: {"server_port":' . $port . ', "password":"' . $passwd . '"}';
        $result = $this->send($msg);
        if (!$result) {
            $this->responseJson(1, $this->errCode);
            return false;
        }
        $this->responseJson(0, $result);
        return true;
    }

    public function doDel() : bool
    {
        $port = intval($this->request->get['port']);
        if ($port < $this->config['ss_port_min'] || $port > $this->config['ss_port_max']) {
            $this->responseJson(1, 'This port is not allowed');
            return true;
        }
        $msg = 'remove: {"server_port":' . $port . '}';
        $result = $this->send($msg);
        if (!$result) {
            $this->responseJson(1, $this->errCode);
            return false;
        }
        $this->responseJson(0, $result);
        return true;
    }

    public function getErrorCode()
    {
        return $this->errCode;
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

    private function sessionFilter() : bool
    {
        $request_uri = $this->request->server['request_uri'];
        if ($request_uri == '/session_start') return false;

        $sid = $this->request->get['sid'];
        $timestamp = intval($this->request->get['ts']);
        $hash = $this->request->get['hash'];
        if (empty($sid) || empty($timestamp) || empty($hash)) {
            $this->responseHtml(400, 'text/html', 'Error 400', 'Bad request');
            return true;
        }
        $dummy = $this->session_manager->get($sid, $timestamp, $hash);
        if (!$dummy) {
            $this->responseHtml(400, 'text/html', 'Error 400', 'Access denied.');
            return true;
        }
        $this->session = $dummy;
        return false;
    }
    /**
     * Filter the requests for static files
     *
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @return bool
     */
    private function staticFileFilter(): bool
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

    private function responseJson(int $code, string $message = null, $data = null)
    {
        $obj = array(
            'code' => $code
        );
        if ($message != null) {
            $obj['message'] = $message;
        }
        if ($data != null) {
            $obj['data'] = $data;
        }
        $this->responseText('application/json', json_encode($obj));
    }

    private function responseText(string $content_type, string $text)
    {
        $this->response->header("Content-Type", $content_type);
        $this->response->end($text);
    }

}
