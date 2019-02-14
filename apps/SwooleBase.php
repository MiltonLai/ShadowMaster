<?php

class SwooleBase {

    protected function bindCallback(\Swoole\Server $server) {
        $server->on('Start', array($this, 'onStart'));
        $server->on('Shutdown', array($this, 'onShutdown'));
        $server->on('ManagerStart', array($this, 'onManagerStart'));
        $server->on('ManagerStop', array($this, 'onManagerStop'));
        $server->on('WorkerStart', array($this, 'onWorkerStart'));
        $server->on('WorkerStop', array($this, 'onWorkerStop'));
        $server->on('WorkerExit', array($this, 'onWorkerExit'));
        $server->on('Connect', array($this, 'onConnect'));
        $server->on('Receive', array($this, 'onReceive'));
        $server->on('Close', array($this, 'onClose'));
        $server->on('Finish', array($this, 'onFinish'));
        $server->on('WorkerError', array($this, 'onWorkerError'));
        $server->on('PipeMessage', array($this, 'onPipeMessage'));
        $server->on('Request', array($this, 'onRequest'));
    }

    public function onStart(\Swoole\Server $server) {
        echo '[onStart] PID:'. $server->master_pid . ', workers:' . $server->setting['worker_num'] . "\n";
    }

    public function onShutdown(\Swoole\Server $server) {
        echo '[onShutdown] PID:'. $server->master_pid . "\n";
    }

    public function onManagerStart(\Swoole\Server $server) {
        echo '[onManagerStart] PID:'. $server->master_pid . "\n";
    }

    public function onManagerStop(\Swoole\Server $server) {
        echo '[onManagerStop] PID:'. $server->master_pid . "\n";
    }

    public function onWorkerStart(\Swoole\Server $server, int $worker_id) {
        echo '[onWorkerStart] PID:'. $server->master_pid . ', workerId:' . $worker_id . "\n";
    }

    public function onWorkerStop(\Swoole\Server $server, int $worker_id) {
        echo '[onWorkerStop] PID:'. $server->master_pid . ', workerId:' . $worker_id . "\n";
    }

    public function onWorkerExit(\Swoole\Server $server, int $worker_id) {
        echo '[onWorkerExit] PID:'. $server->master_pid . ', workerId:' . $worker_id . "\n";
    }

    public function onConnect(\Swoole\Server $server, int $fd, int $reactor_id) {
        echo '[onConnect] PID:'. $server->master_pid . ', fd:' . $fd . ', reactorId:' . $reactor_id . "\n";
    }

    public function onReceive(\Swoole\Server $server, int $fd, int $reactor_id, string $data) {
        echo '[onReceive] PID:'. $server->master_pid . ', fd:' . $fd . ', reactorId:' . $reactor_id . ', dataLen:' . strlen($data) . "\n";
    }

    public function onClose(\Swoole\Server $server, int $fd, int $reactor_id) {
        echo '[onClose] PID:'. $server->master_pid . ', fd:' . $fd . ', reactorId:' . $reactor_id . "\n";
    }

    public function onFinish(\Swoole\Server $server, int $task_id, string $data) {
        echo '[onFinish] PID:'. $server->master_pid . ', taskId:' . $task_id . ', dataLen:' . strlen($data) . "\n";
    }

    public function onWorkerError(\Swoole\Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal) {
        echo '[onWorkerError] PID:'. $server->master_pid . ', workerId:' . $worker_id . ', workerPid:' . $worker_pid . "\n";
    }

    public function onPipeMessage(\Swoole\Server $server, int $src_worker_id, $message) {
        echo '[onPipeMessage] workerId:'. $server->worker_id . ', srcWorkerId:' . $src_worker_id . "\n";
    }
}

