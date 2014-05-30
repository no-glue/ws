<?php
include_once ('server.php');

class PushServer extends WebsocketWorker {
    private $listenersMap;

    protected function onOpen($client, $info) {//вызывается при соединении с новым клиентом

    }

    protected function onClose($client) {//вызывается при закрытии соединения клиентом

    }

    protected function onMessage($client, $data) {//вызывается при получении сообщения от клиента
        $data = $this->decode($data);

        if (!$data['payload']) {
            return;
        }

        if (!mb_check_encoding($data['payload'], 'utf-8')) {
            return;
        }

        $request = json_encode($data['payload']);

        // handle request
        switch ($request['type']) {
            case 'subscribe':
                if (!isset($request['topic'])) {
                    return;
                }
                $this->listenersMap[
                    $request['topic']
                ][] = intval($client);
                break;
            case 'unsubscribe':
                if (!isset($request['topic'])) {
                    return;
                }
                $key = array_search(intval($client), $this->listenersMap);
                if ($key !== false) {
                    unset($this->listenersMap[
                        $request['topic']
                    ][$key]);
                }
            case 'push':
                if (!isset($request['topic'])) {
                    return;
                }
                $this->push($request['topic']);
        }

    }

    private function push($topic) {
        foreach ($this->clients[$topic] as $listener) {
            $this->send(json_encode(array(
               'type' => 'push',
            )), $listener);
        }
    }

    protected function onSend($data) {//вызывается при получении сообщения от мастера
        $this->sendHelper($data);
    }

    protected function send($message, $client) {
        $data = $this->encode($message);

        if (stream_select($read, $client, $except, 0)) {
            print_r($client);
            echo get_resource_type($client);
            @fwrite($client, $data);
        }
    }


}