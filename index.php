<script>
    // создать подключение
    var socket = new WebSocket("ws://192.168.0.170:8030");

    // обработчик входящих сообщений
    socket.onmessage = function(event) {
        var incomingMessage = event.data;
        showMessage(incomingMessage);
    };

    // показать сообщение в div#subscribe
    function showMessage(message) {
        var messageElem = document.createElement('div');
        messageElem.appendChild(document.createTextNode(message));
        document.getElementById('subscribe').appendChild(messageElem);
        document.getElementById('subscribe').scrollTop = 9999;
    }

    function send(){
        socket.send(document.getElementById('input').value);
        document.getElementById('input').value = '';
    }
</script>
<style>
    #input{
        padding:5px;
        width:100%;
    }
    #subscribe{
        borser: 1px solid gray;
        height:300px;
        overflow:auto;
    }
</style>

<div id='subscribe'></div>
<form onsubmit="send();return false;">
    <input type="text" id="input">
</form>