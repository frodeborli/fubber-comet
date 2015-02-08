# fubber-comet

A simple to use comet (long-polling) server, serving old-school json and jsonp.

## Installation

### Using composer to install

1. Download and install composer (skip this step if you have already done this)

    `curl -sS https://getcomposer.org/installer | php`

2. Add Fubber Comet as a dependency to your project

    `./composer.phar require fubber/comet:dev-master`

3. Configure Fubber Comet (read below)

4. Start Fubber Comet

    `./vendor/bin/fubber-reactor`

### Configuration

Create a fubber-reactor.json file in your project root. Add a fubber-comet section where you configure fubber comet according to the following json scheme (pay attention to
the database section and http section (put in your own values):

    ```json
{
    "apps": {
        "\\Fubber\\Comet\\Server": {
            "database": "master"
        }
    },
    "database": {
        "master": {
            "dsn": "mysql:host=localhost;dbname=databasename",
            "user": "databaseuser",
            "password": "databasepassword"
        }
    },
    "http": {
        "host": "example.com",
        "port": 80
    }
}
    ```

