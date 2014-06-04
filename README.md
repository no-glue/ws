# WebSocket Push Server

WebSocket Push Server is a [PHP](http://php.net/) implementation of push server, based on the
[WebSocket](http://www.rfc-editor.org/rfc/rfc6455.txt) protocol.

This is simple, yet working version.

You can install it by composer by modifying your composer.json:

    "require": {
        ...
        "tirnak/ws" : "dev-master"
    },
    "repositories": [
        {
            "type": "vcs",
            "url":  "git@github.com:tirnak/ws.git"
        }
    ]
    
Then execute in terminal

    $ composer update
    
To run server, create new instance, like :

    (new \WS\PushServer('192.168.0.1', 8030))->run();
    
or, to open port for everyone:

    (new \WS\PushServer('0.0.0.0', 8030))->run();

to start, I'd recommend:

    $ php server.php
    $ php -S localhost:8040
  
And then open "localhost:8040" in different browsers.

Then, you can send from js console:

    $ add('name of topic')
  
And in another browser, type:

    $ push('name of topic')
