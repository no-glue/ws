<script>
    // создать подключение
    var socket = new WebSocket("ws://192.168.0.170:8030");

    // обработчик входящих сообщений
    socket.onmessage = function(event) {
        var incomingMessage = event;
        showMessage(incomingMessage);
    };

    // показать сообщение в div#subscribe
    function showMessage(message) {
        console.log(message);
    }

    window.add = function(topic) {
        socket.send('{type:"subscribe","topic":"' + topic + '"}');
    }
    window.push = function(topic) {
        socket.send('{type:"push","topic":"' + topic + '"}');
    }
</script>
