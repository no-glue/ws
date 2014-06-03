# WebSocket Push Server

WebSocket Push Server is a [PHP](http://php.net/) implementation of push server, based on the
[WebSocket](http://www.rfc-editor.org/rfc/rfc6455.txt) protocol.

This is simple, yet working version.

to start, I'd recommend:

    $ php server.php
    $ php -S localhost:8040
  
And then open "localhost:8040" in different browsers.

Then, you can send from js console:

    $ add('name of topic')
  
And in another browser, type:

    $ push('name of topic')
