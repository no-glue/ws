<?php

namespace WS;

class MySocketServer
{
    protected $socket;
    protected $clients = [];
    protected $changed;
    protected $listenersMap = [];

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
            return; //no new clients
        }
        $socket_new = socket_accept($this->socket); //accept new socket
        $this->handshake($socket_new);
        unset($this->changed[0]);
    }

    function checkMessageRecieved()
    {
        foreach ($this->changed as $key => $socket) {
            $buffer = null;
            while(socket_recv($socket, $buffer, 10000, 0) >= 1) {

                $request = $this->hybi10Decode($buffer)['payload'];

                //$this->broadbandMessage($this->hybi10Encode(trim($buffer), 'text', false) . PHP_EOL);
                $request = json_decode($request);

                if (!$request) {
                    continue;
                }

                // handle request
                switch ($request->type) {
                    case 'subscribe':
                        if (!isset($request->topic)) {
                            return;
                        }
                        $this->listenersMap[
                            $request->topic
                        ][] = intval($socket);
                        break;
                    case 'unsubscribe':
                        if (!isset($request->topic)) {
                            return;
                        }
                        $key = array_search(intval($socket), $this->listenersMap);
                        if ($key !== false) {
                            unset($this->listenersMap[
                                $request->topic
                            ][$key]);
                        }
                        break;
                    case 'push':
                        if (!isset($request->topic)) {
                            return;
                        }
                        $this->push($request->topic);
                        break;
                }
                break;
            }
            unset($this->changed[$key]);
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
        }
    }

    private function push($topic) {
        $msg = json_encode(array(
            'type' => 'push',
        ));

        $msg = $this->hybi10Encode($msg, 'text', false);

        foreach($this->clients as $client) {
            if (in_array(intval($client), $this->listenersMap[$topic])) {
                socket_write($client,$msg,strlen($msg));
            }
        }
    }

    private function broadbandMessage($msg)
    {
        foreach(array_diff($this->clients, $this->changed) as $client)
        {
            socket_write($client,$msg,strlen($msg));
        }
        return true;
    }

    private function hybi10Encode($payload, $type = 'text', $masked = true)
    {
        $frameHead = array();
        $frame = '';
        $payloadLength = strlen($payload);

        switch($type)
        {
            case 'text':
// first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
// first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
// first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
// first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

// set mask and payload length (using 1, 3 or 9 bytes)
        if($payloadLength > 65535)
        {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for($i = 0; $i < 8; $i++)
            {
                $frameHead[$i+2] = bindec($payloadLengthBin[$i]);
            }
// most significant bit MUST be 0 (close connection if frame too big)
            if($frameHead[2] > 127)
            {
                $this->close(1004);
                return false;
            }
        }
        elseif($payloadLength > 125)
        {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        }
        else
        {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

// convert frame-head to string:
        foreach(array_keys($frameHead) as $i)
        {
            $frameHead[$i] = chr($frameHead[$i]);
        }
        if($masked === true)
        {
// generate a random mask:
            $mask = array();
            for($i = 0; $i < 4; $i++)
            {
                $mask[$i] = chr(rand(0, 255));
            }

            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);

// append payload to frame:
        $framePayload = array();
        for($i = 0; $i < $payloadLength; $i++)
        {
            $frame .= /*($masked === true) ? $payload[$i] ^ $mask[$i % 4] :*/ $payload[$i];
        }

        return $frame;
    }

    private function hybi10Decode($data)
    {
        $payloadLength = '';
        $mask = '';
        $unmaskedPayload = '';
        $decodedData = array();

// estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = ($secondByteBinary[0] == '1') ? true : false;
        $payloadLength = ord($data[1]) & 127;

// close connection if unmasked frame is received:
        if($isMasked === false)
        {
            $this->close(1002);
        }

        switch($opcode)
        {
// text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;

            case 2:
                $decodedData['type'] = 'binary';
                break;

// connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;

// ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;

// pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;

            default:
// Close connection on unknown opcode:
                $this->close(1003);
                break;
        }

        if($payloadLength === 126)
        {
            $mask = substr($data, 4, 4);
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        }
        elseif($payloadLength === 127)
        {
            $mask = substr($data, 10, 4);
            $payloadOffset = 14;
            $tmp = '';
            for($i = 0; $i < 8; $i++)
            {
                $tmp .= sprintf('%08b', ord($data[$i+2]));
            }
            $dataLength = bindec($tmp) + $payloadOffset;
            unset($tmp);
        }
        else
        {
            $mask = substr($data, 2, 4);
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        /**
         * We have to check for large frames here. socket_recv cuts at 1024 bytes
         * so if websocket-frame is > 1024 bytes we have to wait until whole
         * data is transferd.
         */
        if(strlen($data) < $dataLength)
        {
            return false;
        }

        if($isMasked === true)
        {
            for($i = $payloadOffset; $i < $dataLength; $i++)
            {
                $j = $i - $payloadOffset;
                if(isset($data[$i]))
                {
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
                }
            }
            $decodedData['payload'] = $unmaskedPayload;
        }
        else
        {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset);
        }

        return $decodedData;
    }

    /**
     * @param $socket_new
     * @return void
     */
    private function handshake($socket_new)
    {
        $input = socket_read($socket_new, 5000);

        $input = explode("\r\n", $input);
        foreach ($input as $key => &$param) {
            $exploded = explode(':', $param, 2);
            if (count($exploded) > 1) {
                $input[$exploded[0]] = $exploded[1];
                unset($input[$key]);
            }
        }

        $hash = trim($input['Sec-WebSocket-Key']) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $hash = sha1($hash, true);
        $hash = base64_encode($hash);

        $answer = "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: " . $hash . "\r\n\r\n";

        socket_write($socket_new, $answer);
        $this->clients[] = $socket_new;
    }
}

(new MySocketServer('0.0.0.0', 8030))->run();