# fubber-comet

A simple to use comet (long-polling) server, serving old-school json and jsonp.

## Configuration

Create a fubber-reactor.json file in your project root. Add a fubber-comet section where you configure fubber comet according to the following json scheme:

    ```json
    {
        "apps": {
            "\\Fubber\\Comet\\Server": {
                "database": "master"
            }
        },
        "database": {
            "master": {
                "dsn": "mysql:host=localhost;dbname=example",
                "user": "example-user",
                "password": "example-password"
            }
        }
    }
    ```

## Usage

The url /ws/push?c[]=hello&p=themessage will push a message out on the message queue. Any subscriber listening to /ws/subsribe?c[]=hello will receive the message.

