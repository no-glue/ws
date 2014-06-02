<?php
class MySocketServer
{
    protected $socket;
    protected $clients = [];
    protected $changed;

    function __construct($host = 'localhost', $port = 9000)
    {
        set_time_limit(0);
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        //bind socket to specified host
        socket_bind($socket, 0, $port);
        //listen to port
        socket_listen($socket);
        $this->socket = $socket;
    }

    function __destruct()
    {
        foreach($this->clients as $client) {
            socket_close($client);
        }
        socket_close($this->socket);
    }

    function run()
    {
        while(true) {
            $this->waitForChange();
            $this->checkNewClients();
            $this->checkMessageRecieved();
            $this->checkDisconnect();
        }
    }

    function waitForChange()
    {
        //reset changed
        $this->changed = array_merge([$this->socket], $this->clients);
        //variable call time pass by reference req of socket_select
        $null = null;
        //this next part is blocking so that we don't run away with cpu
        socket_select($this->changed, $null, $null, null);
    }

    function checkNewClients()
    {
        if (!in_array($this->socket, $this->changed)) {
            echo 'ok' . PHP_EOL;
            return; //no new clients
        }
        $socket_new = socket_accept($this->socket); //accept new socket
        $input = socket_read($socket_new, 5000);
        echo $input;
        $input = explode("\r\n", $input);
        foreach ($input as $key => &$param) {
            $exploded = explode(':', $param, 2);
            if (count($exploded) > 1) {
                $input[$exploded[0]] = $exploded[1];
                unset($input[$key]);
            }
        }
        unset($param, $key);
        var_dump($input);

        $hash = trim($input['Sec-WebSocket-Key']) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $hash = sha1($hash,true);
        $hash = base64_encode($hash);

        $answer = "HTTP/1.1 101 Switching Protocols\r\n"
        ."Upgrade: websocket\r\n"
        ."Connection: Upgrade\r\n"
        ."Sec-WebSocket-Accept: ".$hash."\r\n\r\n";
//        ."Sec-WebSocket-Protocol: chat\r\n";

        socket_write($socket_new, $answer);
        $this->clients[] = $socket_new;
        unset($this->changed[0]);
    }

    function checkMessageRecieved()
    {
        foreach ($this->changed as $key => $socket) {
            $buffer = null;
            while(socket_recv($socket, $buffer, 1024, 0) >= 1) {
                $this->broadbandMessage(trim($buffer) . PHP_EOL);
                unset($this->changed[$key]);
                break;
            }
        }
    }

    function checkDisconnect()
    {
        foreach ($this->changed as $changed_socket) {
            $buf = socket_read($changed_socket, 1024, PHP_NORMAL_READ);
            if ($buf !== false) { // check disconnected client
                continue;
            }
            // remove client for $clients array
            $found_socket = array_search($changed_socket, $this->clients);
            socket_getpeername($changed_socket, $ip);
            unset($this->clients[$found_socket]);
            $response = 'client ' . $ip . ' has disconnected';
            $this->broadbandMessage($response);
        }
    }

    function broadbandMessage($msg)
    {
        foreach($this->clients as $client)
        {
            socket_write($client,$msg,strlen($msg));
        }
        return true;
    }
}

(new MySocketServer('0.0.0.0', 8030))->run();