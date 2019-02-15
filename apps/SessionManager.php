<?php

class SessionManager
{
    /**
     * @var \Swoole\Table
     */
    private $session_table;
    private $session_table_size_limit;
    private $secret;

    public function __construct(array $config, \Swoole\Table $table)
    {
        $this->session_table = $table;
        $this->session_table_size_limit = $config['session_table_size'];
        $this->secret = $config['secret'];
        $this->cleanup();
    }

    public function cleanup()
    {
        $dummy = array();
        foreach ($this->session_table as $row) {
            $dummy[] = $row['sid'];
        }
        foreach ($dummy as $key) {
            $this->session_table->del($key);
        }
    }

    public function size() : int
    {
        return $this->session_table->count();
    }

    public function get_raw(string $sid)
    {
        return $this->session_table->get($sid);
    }

    public function get(string $sid, int $timestamp, string $hash, bool $renew = true)
    {
        if (empty($sid)) return false;
        $session = $this->session_table->get($sid);
        if (!$session) return false;

        if (!$this->validate($session, $timestamp, $hash)) return false;

        if ($renew) {
            $this->session_table->set($sid, array('updated_at' => (int)(microtime(true)*1000)));
        }
        return $session;
    }

    public function delete(string $sid)
    {
        $this->session_table->del($sid);
    }

    public function reset(string $sid)
    {
        if ($this->session_table->exist($sid)) {
            $this->session_table->set($sid, array(
                'uid'       => ANONYMOUS_UID,
                'username'  => 'Anonymous',
                'secure'    => uniqid('', true),
            ));
        }
    }

    public function login(string $sid, string $username, string $password) : bool
    {
        if (empty($sid)) return false;
        if (empty($username)) return false;
        if (empty($password)) return false;

        $session = $this->session_table->get($sid);
        if (!$session) return false;

        $hash = md5($password);
        echo $hash."\n";
        if ($hash == $this->secret) {
            $this->session_table->set($sid, array(
                'uid'       => $username,
                'username'  => $username,
            ));
            return true;
        }
        return false;
    }

    public function logout(string $sid) : bool
    {
        if ($this->session_table->exist($sid)) {
            $this->session_table->set($sid, array(
                'uid'       => ANONYMOUS_UID,
                'username'  => 'Anonymous',
            ));
            return true;
        } else {
            return false;
        }
    }

    public function create(string $secure) : array
    {
        if ($this->session_table->count() >= $this->session_table_size_limit) {
            $this->cleanup();
        }
        $session = array(
            'sid'           => uniqid('', true),
            'uid'           => ANONYMOUS_UID,
            'username'      => 'Anonymous',
            'started_at'    => (int)(microtime(true)*1000),
            'updated_at'    => (int)(microtime(true)*1000),
            'secure'        => $secure,
        );
        $this->session_table->set($session['sid'], $session);
        echo 'Table size: ' . $this->size() . "\n";
        return array(
            'sid'           => $session['sid'],
            'table_size'    => $this->session_table->count()
        );
    }

    private function validate($session, int $timestamp, string $hash) : bool
    {
        $local_hash = md5($session['secure'] . $timestamp);
        echo "Local hash: $local_hash\n";
        if ($local_hash != $hash) {
            return false;
        }
        return ($timestamp - 1800 * 1000 - $session['updated_at']) > 0;
    }
}
